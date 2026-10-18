<?php

use App\Actions\SendOwnerReply;
use App\Channels\FakeTextChannel;
use App\Channels\TextChannel;
use App\Enums\DraftResolution;
use App\Enums\MessageKind;
use App\Enums\MessageStatus;
use App\Livewire\ConversationThread;
use App\Livewire\DevChat;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

const SENT_TIME_OWNER_PHONE = '+12025550199';

const SENT_TIME_DRAFT_WORDS = 'Mike will text you a price for a new unit.';

/**
 * Tuesday 2026-10-13 at 4:30 pm in the business zone, the owner signed in, and Dana's price question answered by a
 * sent holding reply and a held draft, all recorded at 4:30 pm. Returns the owner.
 */
function sentTimePriceQuestionHeld(): User
{
    test()->travelTo(Carbon::parse('2026-10-13 16:30', 'America/Chicago'));

    $owner = User::factory()->withPhone(SENT_TIME_OWNER_PHONE)->create();

    frontDeskCustomerTexts('How much for a new unit?');
    scriptFrontDeskReplies(['reply' => SENT_TIME_DRAFT_WORDS, 'label' => 'price']);
    runQueuedFrontDeskJobs();

    test()->actingAs($owner);

    return $owner;
}

/**
 * The held draft Dana's price question left.
 */
function sentTimeTheDraft(): Message
{
    return Message::query()->where('kind', MessageKind::AgentReply->value)->orderBy('id')->firstOrFail();
}

/**
 * The holding reply that went out beside the held draft.
 */
function sentTimeTheHoldingReply(): Message
{
    return Message::query()->where('kind', MessageKind::HoldingReply->value)->firstOrFail();
}

/**
 * The owner signed in and an open conversation with one question from the customer.
 */
function sentTimeOpenConversation(): Conversation
{
    test()->actingAs(User::factory()->create());

    $contact = Contact::factory()->create(['name' => 'Pat Rivera', 'phone_e164' => '+12025550177']);
    $conversation = Conversation::factory()->for($contact)->create();
    Message::factory()->for($conversation)->create(['body' => 'Is Tuesday OK?']);

    return $conversation;
}

it('lists a held draft the owner sends after the holding reply below it (G-02-1.2, UAT Test 1)', function () {
    sentTimePriceQuestionHeld();
    $draft = sentTimeTheDraft();
    $holdingReply = sentTimeTheHoldingReply();
    $customerText = Message::query()->where('author', 'customer')->sole();

    $this->travel(3)->minutes();

    Livewire::test(ConversationThread::class, ['conversation' => Conversation::query()->sole()])
        ->call('approveDraft')
        ->assertHasNoErrors();

    $conversation = Conversation::query()->sole();
    $thread = Livewire::test(ConversationThread::class, ['conversation' => $conversation]);
    $draft->refresh();

    expect($thread->instance()->messages->pluck('id')->all())->toBe([$customerText->id, $holdingReply->id, $draft->id])
        ->and($conversation->messages()->pluck('id')->last())->toBe($draft->id)
        ->and($draft->id)->toBe(sentTimeTheDraft()->id)
        ->and($draft->status)->toBe(MessageStatus::Sent)
        ->and($draft->created_at->equalTo(Carbon::parse('2026-10-13 16:30', 'America/Chicago')))->toBeTrue()
        ->and($draft->sent_at->equalTo(Carbon::parse('2026-10-13 16:33', 'America/Chicago')))->toBeTrue();
});

it('records when each text was sent', function () {
    sentTimePriceQuestionHeld();

    expect(sentTimeTheHoldingReply()->sent_at->equalTo(Carbon::parse('2026-10-13 16:30', 'America/Chicago')))->toBeTrue();

    $conversation = Conversation::query()->sole();
    $this->travel(10)->minutes();

    $reply = app(SendOwnerReply::class)->handle($conversation, 'Mike is on his way.');

    expect($reply->fresh()->sent_at->equalTo(Carbon::parse('2026-10-13 16:40', 'America/Chicago')))->toBeTrue();
});

it('leaves sent_at empty for anything that never went out', function () {
    sentTimePriceQuestionHeld();

    expect(sentTimeTheDraft()->sent_at)->toBeNull()
        ->and(Message::query()->where('author', 'customer')->sole()->sent_at)->toBeNull();

    $conversation = sentTimeOpenConversation();
    $this->app->bind(TextChannel::class, fn () => new class extends FakeTextChannel
    {
        public function send(string $toE164, string $body): string
        {
            throw new RuntimeException('Carrier rejected the number');
        }
    });

    expect(fn () => app(SendOwnerReply::class)->handle($conversation, 'Tuesday at 9 works'))->toThrow(DomainException::class);

    $failed = $conversation->messages()->where('status', MessageStatus::Failed->value)->sole();

    expect($failed->sent_at)->toBeNull();
});

it('records the send time even when recording the send fails afterwards', function () {
    $conversation = sentTimeOpenConversation();
    $this->travelTo(Carbon::parse('2026-10-13 16:30', 'America/Chicago'));
    $failed = false;

    // Stand in for a database error between the carrier accepting the text and the record of it.
    Message::updating(function () use (&$failed): void {
        if (! $failed) {
            $failed = true;

            throw new RuntimeException('database went away');
        }
    });

    $message = app(SendOwnerReply::class)->handle($conversation, 'Tuesday at 9 works');

    expect($message->status)->toBe(MessageStatus::Sent)
        ->and(Message::query()->findOrFail($message->id)->sent_at->equalTo(Carbon::parse('2026-10-13 16:30', 'America/Chicago')))->toBeTrue();
});

it('says when a message happened, without changing the model', function () {
    $message = Message::factory()->for(Conversation::factory()->create())->create([
        'created_at' => Carbon::parse('2026-10-13 16:30', 'UTC'),
    ]);

    expect($message->happenedAt()->equalTo($message->created_at))->toBeTrue();

    $message->sent_at = Carbon::parse('2026-10-13 16:33', 'UTC');
    $timezoneBefore = $message->sent_at->getTimezone()->getName();
    $happened = $message->happenedAt();

    expect($happened)->toBeInstanceOf(CarbonImmutable::class)
        ->and($happened->equalTo($message->sent_at))->toBeTrue();

    $happened->setTimezone('America/Chicago');

    expect($message->sent_at->getTimezone()->getName())->toBe($timezoneBefore);
});

it('backfills the send time of texts sent before this change', function () {
    $conversation = Conversation::factory()->create();
    $sent = fn (array $attributes) => Message::factory()->outbound()->for($conversation)->create($attributes);
    $nine = Carbon::parse('2026-10-01 09:00', 'UTC');

    $agentText = $sent(['created_at' => $nine]);
    $delivered = $sent(['status' => MessageStatus::Delivered, 'created_at' => $nine->copy()->addMinutes(10)]);
    $approved = $sent(['created_at' => $nine, 'resolution' => DraftResolution::Approved, 'resolved_at' => $nine->copy()->addMinutes(5)]);
    $edited = $sent(['created_at' => $nine, 'resolution' => DraftResolution::Edited, 'resolved_at' => $nine->copy()->addMinutes(7)]);
    $alreadyStamped = $sent(['created_at' => $nine, 'sent_at' => $nine->copy()->addHour()]);

    $received = Message::factory()->for($conversation)->create(['created_at' => $nine]);
    $draft = Message::factory()->draft()->for($conversation)->create(['created_at' => $nine]);
    $withdrawn = Message::factory()->withdrawn()->for($conversation)->create(['created_at' => $nine]);
    $queued = $sent(['status' => MessageStatus::Queued, 'created_at' => $nine]);
    $failed = $sent(['status' => MessageStatus::Failed, 'created_at' => $nine]);

    $migration = require database_path('migrations/2026_10_12_000011_backfill_sent_at_on_messages.php');
    $migration->up();

    expect($agentText->fresh()->sent_at->equalTo($nine))->toBeTrue()
        ->and($delivered->fresh()->sent_at->equalTo($nine->copy()->addMinutes(10)))->toBeTrue()
        ->and($approved->fresh()->sent_at->equalTo($nine->copy()->addMinutes(5)))->toBeTrue()
        ->and($edited->fresh()->sent_at->equalTo($nine->copy()->addMinutes(7)))->toBeTrue()
        ->and($alreadyStamped->fresh()->sent_at->equalTo($nine->copy()->addHour()))->toBeTrue()
        ->and($received->fresh()->sent_at)->toBeNull()
        ->and($draft->fresh()->sent_at)->toBeNull()
        ->and($withdrawn->fresh()->sent_at)->toBeNull()
        ->and($queued->fresh()->sent_at)->toBeNull()
        ->and($failed->fresh()->sent_at)->toBeNull();
});

it('lists the sent draft after the holding reply in /dev/chat', function () {
    $this->app['env'] = 'local';
    sentTimePriceQuestionHeld();
    $holdingReply = sentTimeTheHoldingReply();
    $draft = sentTimeTheDraft();
    $customerText = Message::query()->where('author', 'customer')->sole();

    $this->travel(3)->minutes();

    Livewire::test(ConversationThread::class, ['conversation' => Conversation::query()->sole()])->call('approveDraft');

    Livewire::test(DevChat::class)
        ->set('from', '+12025550123')
        ->assertSeeInOrder(['on his list', SENT_TIME_DRAFT_WORDS])
        ->tap(fn ($chat) => expect($chat->instance()->thread->pluck('id')->all())->toBe([$customerText->id, $holdingReply->id, $draft->id]));
});

it('shows each message at the time it happened', function () {
    $this->app['env'] = 'local';
    sentTimePriceQuestionHeld();

    $this->travel(3)->minutes();

    $thread = Livewire::test(ConversationThread::class, ['conversation' => Conversation::query()->sole()])->call('approveDraft');

    $thread->assertSeeInOrder(['Oct 13, 4:30 pm', 'Oct 13, 4:33 pm', SENT_TIME_DRAFT_WORDS]);

    Livewire::test(DevChat::class)
        ->set('from', '+12025550123')
        ->assertSeeInOrder(['Oct 13, 4:30 pm', 'Oct 13, 4:33 pm', SENT_TIME_DRAFT_WORDS]);

    expect(sentTimeTheDraft()->created_at->setTimezone('America/Chicago')->format('M j, g:i a'))->toBe('Oct 13, 4:30 pm')
        ->and(sentTimeTheDraft()->happenedAt()->setTimezone('America/Chicago')->format('M j, g:i a'))->toBe('Oct 13, 4:33 pm');
});
