<?php

use App\Actions\ClaimInboundBurst;
use App\Actions\DispatchInbound;
use App\Jobs\ProcessInbound;
use App\Jobs\SweepUndispatched;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Database\QueryException;
use Illuminate\Queue\Attributes\DebounceFor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Testing\Fakes\QueueFake;

/**
 * One conversation holding the given number of inbound customer texts, none yet dispatched or handled.
 *
 * @return array{Conversation, list<Message>}
 */
function dispatchTestConversationWithTexts(int $count): array
{
    $conversation = Conversation::factory()->create();
    $messages = [];

    for ($index = 0; $index < $count; $index++) {
        $messages[] = Message::factory()->for($conversation)->create(['body' => "text {$index}"]);
    }

    return [$conversation, $messages];
}

it('queues the debounced job once for a text that comes in through the fake webhook and marks it dispatched', function () {
    Queue::fake();

    $this->post(route('webhooks.text.fake'), fakeTextPayload())->assertOk();

    $message = Message::sole();

    expect($message->dispatched_at)->not->toBeNull();

    Queue::assertPushed(ProcessInbound::class, 1);
    Queue::assertPushed(
        ProcessInbound::class,
        fn (ProcessInbound $job) => $job->messageId === $message->id && $job->conversationId === $message->conversation_id,
    );
});

it('queues nothing more when the provider retries a text it already delivered', function () {
    Queue::fake();

    $this->post(route('webhooks.text.fake'), fakeTextPayload())->assertOk();
    $this->post(route('webhooks.text.fake'), fakeTextPayload())->assertOk();

    expect(Message::count())->toBe(1);

    Queue::assertPushed(ProcessInbound::class, 1);
});

it('queues a recorded text that was never queued when the provider retries it (WR-02)', function () {
    Queue::fake();

    $this->post(route('webhooks.text.fake'), fakeTextPayload())->assertOk();
    Queue::assertPushed(ProcessInbound::class, 1);

    // The process died after the commit and before the queue push: the row exists, undispatched.
    Message::sole()->update(['dispatched_at' => null]);

    $this->post(route('webhooks.text.fake'), fakeTextPayload())->assertOk();

    expect(Message::count())->toBe(1)
        ->and(Message::sole()->dispatched_at)->not->toBeNull();

    Queue::assertPushed(ProcessInbound::class, 2);
});

it('claims a message once: the second call returns false and queues nothing more', function () {
    Queue::fake();
    [, $messages] = dispatchTestConversationWithTexts(1);

    $dispatchInbound = app(DispatchInbound::class);

    expect($dispatchInbound->handle($messages[0]))->toBeTrue()
        ->and($dispatchInbound->handle($messages[0]->fresh()))->toBeFalse()
        ->and($messages[0]->fresh()->dispatched_at)->not->toBeNull();

    Queue::assertPushed(ProcessInbound::class, 1);
});

it('does not queue an outbound or an owner message', function () {
    Queue::fake();
    $conversation = Conversation::factory()->create();
    $agentReply = Message::factory()->for($conversation)->outbound()->create();
    $ownerReply = Message::factory()->for($conversation)->fromOwner()->create();

    $dispatchInbound = app(DispatchInbound::class);

    expect($dispatchInbound->handle($agentReply))->toBeFalse()
        ->and($dispatchInbound->handle($ownerReply))->toBeFalse()
        ->and($agentReply->fresh()->dispatched_at)->toBeNull()
        ->and($ownerReply->fresh()->dispatched_at)->toBeNull();

    Queue::assertNothingPushed();
});

it('waits 20 seconds and debounces by conversation so a burst is answered together', function () {
    Queue::fake();
    [$conversation, $messages] = dispatchTestConversationWithTexts(1);

    app(DispatchInbound::class)->handle($messages[0]);

    Queue::assertPushed(
        ProcessInbound::class,
        fn (ProcessInbound $job) => $job->delay === 20 && $job->debounceId() === (string) $conversation->id,
    );

    $attribute = (new ReflectionClass(ProcessInbound::class))->getAttributes(DebounceFor::class)[0]->newInstance();

    expect($attribute->debounceFor)->toBe(20)
        ->and($attribute->maxWait)->toBe(90)
        ->and(new ProcessInbound(1, 1))->not->toBeInstanceOf(ShouldBeUnique::class);
});

it('claims every unhandled text in the conversation on the first run and nothing on the second', function () {
    [$conversation, $messages] = dispatchTestConversationWithTexts(3);
    $job = new ProcessInbound($messages[2]->id, $conversation->id);

    app()->call([$job, 'handle']);

    expect(Message::query()->whereNull('handled_at')->count())->toBe(0);

    $handledAt = $messages[0]->fresh()->handled_at;

    Carbon::setTestNow(now()->addMinute());
    app()->call([$job, 'handle']);
    Carbon::setTestNow();

    expect($messages[0]->fresh()->handled_at->equalTo($handledAt))->toBeTrue()
        ->and(app(ClaimInboundBurst::class)->handle($conversation->id))->toBeEmpty();
});

it('hands back the claimed burst oldest first and leaves other conversations, replies and handled texts alone', function () {
    [$conversation, $messages] = dispatchTestConversationWithTexts(3);
    $messages[0]->update(['handled_at' => now()->subMinute()]);
    $agentReply = Message::factory()->for($conversation)->outbound()->create();
    [, $otherMessages] = dispatchTestConversationWithTexts(1);

    $claimed = app(ClaimInboundBurst::class)->handle($conversation->id);

    expect($claimed->pluck('id')->all())->toBe([$messages[1]->id, $messages[2]->id])
        ->and($agentReply->fresh()->handled_at)->toBeNull()
        ->and($otherMessages[0]->fresh()->handled_at)->toBeNull();
});

it('backfills dispatched_at and handled_at to created_at for messages from before this phase', function () {
    $createdAt = Carbon::parse('2026-10-01 09:30:00');
    [, $messages] = dispatchTestConversationWithTexts(2);
    Message::query()->update(['created_at' => $createdAt, 'dispatched_at' => null, 'handled_at' => null]);
    $alreadyDispatched = Message::factory()->for($messages[0]->conversation)->create([
        'dispatched_at' => Carbon::parse('2026-10-02 10:00:00'),
        'handled_at' => Carbon::parse('2026-10-02 10:00:20'),
    ]);

    $migration = require database_path('migrations/2026_10_12_000002_backfill_dispatch_columns_on_messages.php');
    $migration->up();

    foreach ($messages as $message) {
        expect($message->fresh()->dispatched_at->equalTo($createdAt))->toBeTrue()
            ->and($message->fresh()->handled_at->equalTo($createdAt))->toBeTrue();
    }

    expect($alreadyDispatched->fresh()->dispatched_at->toDateTimeString())->toBe('2026-10-02 10:00:00')
        ->and($alreadyDispatched->fresh()->handled_at->toDateTimeString())->toBe('2026-10-02 10:00:20');
});

it('claims a burst of only web form messages and stops, so a form lead never reaches the front desk (D-41)', function () {
    $conversation = Conversation::factory()->create();
    $formMessages = Message::factory()->web()->for($conversation)->count(2)->create();
    $job = new ProcessInbound($formMessages[1]->id, $conversation->id);

    $reachedFrontDesk = app()->call([$job, 'handle']);

    expect($reachedFrontDesk)->toBeFalse()
        ->and(Message::query()->whereNull('handled_at')->count())->toBe(0);
});

it('goes on to the front desk when the burst holds a text from the customer, even next to a form message', function () {
    $conversation = Conversation::factory()->create();
    Message::factory()->web()->for($conversation)->create();
    $text = Message::factory()->for($conversation)->create();
    $job = new ProcessInbound($text->id, $conversation->id);

    expect(app()->call([$job, 'handle']))->toBeTrue();
});

/**
 * Swap in a queue that cannot be reached: pushing a job throws, and so does pushing one with a delay,
 * which is what the debounced ProcessInbound does. A later Queue::fake() brings the working fake back.
 */
function dispatchTestFailingQueue(): QueueFake
{
    $queue = new class(app(), [ProcessInbound::class], Queue::getFacadeRoot()) extends QueueFake
    {
        public function push($job, $data = '', $queue = null)
        {
            throw new RuntimeException('queue down');
        }

        public function later($delay, $job, $data = '', $queue = null)
        {
            throw new RuntimeException('queue down');
        }
    };

    Queue::swap($queue);

    return $queue;
}

it('clears the claim and rethrows when the queue push fails, so the text stays sweepable', function () {
    [, $messages] = dispatchTestConversationWithTexts(1);
    dispatchTestFailingQueue();

    expect(fn () => app(DispatchInbound::class)->handle($messages[0]))
        ->toThrow(RuntimeException::class, 'queue down')
        ->and($messages[0]->fresh()->dispatched_at)->toBeNull();
});

/**
 * Swap back to the real queue manager on a database queue whose jobs table is gone, so a push throws the way an
 * unreachable queue does. Unlike the fake above, the real queue honours afterCommit(): inside a transaction it holds
 * the push back until the commit.
 */
function dispatchTestBrokenDatabaseQueue(): void
{
    app()->forgetInstance('queue');
    Queue::clearResolvedInstance('queue');

    config(['queue.default' => 'database']);
    Schema::drop('jobs');
}

it('clears the claim when the queue push fails even if a caller wraps the dispatch in a transaction (WR-04)', function () {
    [, $messages] = dispatchTestConversationWithTexts(1);
    dispatchTestBrokenDatabaseQueue();

    expect(fn () => DB::transaction(fn () => app(DispatchInbound::class)->handle($messages[0])))
        ->toThrow(QueryException::class)
        ->and($messages[0]->fresh()->dispatched_at)->toBeNull();
});

it('queues a text whose push failed once the sweep runs', function () {
    [, $messages] = dispatchTestConversationWithTexts(1);
    dispatchTestFailingQueue();

    expect(fn () => app(DispatchInbound::class)->handle($messages[0]))->toThrow(RuntimeException::class);

    Queue::fake([ProcessInbound::class]);
    $this->travel(3)->minutes();
    app()->call([new SweepUndispatched, 'handle']);

    expect($messages[0]->fresh()->dispatched_at)->not->toBeNull();

    Queue::assertPushed(ProcessInbound::class, 1);
    Queue::assertPushed(ProcessInbound::class, fn (ProcessInbound $job) => $job->messageId === $messages[0]->id);
});

it('queues a text whose push failed when the provider retries it', function () {
    dispatchTestFailingQueue();

    $this->post(route('webhooks.text.fake'), fakeTextPayload())->assertStatus(500);

    expect(Message::sole()->dispatched_at)->toBeNull();

    Queue::fake([ProcessInbound::class]);

    $this->post(route('webhooks.text.fake'), fakeTextPayload())->assertOk();

    expect(Message::count())->toBe(1)
        ->and(Message::sole()->dispatched_at)->not->toBeNull();

    Queue::assertPushed(ProcessInbound::class, 1);
});
