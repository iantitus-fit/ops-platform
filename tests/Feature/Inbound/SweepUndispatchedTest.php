<?php

use App\Jobs\ProcessInbound;
use App\Jobs\SweepUndispatched;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-10-12 12:00:00'));
});

afterEach(fn () => Carbon::setTestNow());

/**
 * An undispatched inbound customer text created the given number of minutes ago.
 */
function undispatchedTextCreatedMinutesAgo(int $minutes, ?Conversation $conversation = null): Message
{
    return Message::factory()
        ->for($conversation ?? Conversation::factory()->create())
        ->create(['dispatched_at' => null, 'created_at' => now()->subMinutes($minutes)]);
}

function runSweep(): void
{
    app()->call([new SweepUndispatched, 'handle']);
}

it('queues an inbound text that is three minutes old and never dispatched', function () {
    Queue::fake();
    $message = undispatchedTextCreatedMinutesAgo(3);

    runSweep();

    expect($message->fresh()->dispatched_at)->not->toBeNull();

    Queue::assertPushed(
        ProcessInbound::class,
        fn (ProcessInbound $job) => $job->messageId === $message->id && $job->conversationId === $message->conversation_id,
    );
});

it('leaves a text that is only one minute old for the webhook to queue', function () {
    Queue::fake();
    $message = undispatchedTextCreatedMinutesAgo(1);

    runSweep();

    expect($message->fresh()->dispatched_at)->toBeNull();

    Queue::assertNothingPushed();
});

it('never sweeps a text older than 24 hours', function () {
    Queue::fake();
    $message = undispatchedTextCreatedMinutesAgo(25 * 60);

    runSweep();

    expect($message->fresh()->dispatched_at)->toBeNull();

    Queue::assertNothingPushed();
});

it('does not sweep outbound or owner messages', function () {
    Queue::fake();
    $conversation = Conversation::factory()->create();
    $agentReply = Message::factory()->for($conversation)->outbound()->create(['created_at' => now()->subMinutes(10)]);
    $ownerReply = Message::factory()->for($conversation)->fromOwner()->create(['created_at' => now()->subMinutes(10)]);

    runSweep();

    expect($agentReply->fresh()->dispatched_at)->toBeNull()
        ->and($ownerReply->fresh()->dispatched_at)->toBeNull();

    Queue::assertNothingPushed();
});

it('does not queue a text that was already dispatched', function () {
    Queue::fake();
    $message = undispatchedTextCreatedMinutesAgo(10);
    $message->update(['dispatched_at' => now()->subMinutes(9)]);

    runSweep();

    Queue::assertNothingPushed();
});

it('queues a text once when the sweep runs twice', function () {
    Queue::fake();
    undispatchedTextCreatedMinutesAgo(5);

    runSweep();
    runSweep();

    Queue::assertPushed(ProcessInbound::class, 1);
});

it('never queues a message from before this phase once the backfill has run (T-02-15)', function () {
    Queue::fake();
    $message = undispatchedTextCreatedMinutesAgo(60);
    Message::query()->update(['dispatched_at' => null, 'handled_at' => null]);

    $migration = require database_path('migrations/2026_10_12_000002_backfill_dispatch_columns_on_messages.php');
    $migration->up();

    Carbon::setTestNow(now()->addMinutes(10));
    runSweep();

    expect($message->fresh()->dispatched_at->equalTo($message->created_at))->toBeTrue();

    Queue::assertNothingPushed();
});

it('is on the scheduler every five minutes without overlapping', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn (Event $event) => str_contains($event->description ?? '', 'SweepUndispatched'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('*/5 * * * *')
        ->and($event->withoutOverlapping)->toBeTrue();
});
