<?php

use App\Actions\SendOwnerReply;
use App\Channels\FakeTextChannel;
use App\Channels\TextChannel;
use App\Enums\MessageKind;
use App\Enums\MessageStatus;
use App\Livewire\ConversationThread;
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
