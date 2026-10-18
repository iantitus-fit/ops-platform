<?php

use App\Actions\OutboundSender;
use App\Actions\PauseConversation;
use App\Ai\Agents\FrontDeskAgent;
use App\Channels\FakeTextChannel;
use App\Channels\TextChannel;
use App\Enums\ActivityType;
use App\Enums\AlertReason;
use App\Enums\GateOutcome;
use App\Enums\MessageAuthor;
use App\Enums\MessageStatus;
use App\Enums\PausedReason;
use App\Enums\SendOrigin;
use App\Filament\Resources\Conversations\Pages\ViewConversation;
use App\Livewire\ConversationThread;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\OwnerAlert;
use App\Models\User;
use App\Records\CustomerCard;
use Livewire\Livewire;

const TAKE_OVER_OWNER_PHONE = '+12025550199';

const TAKE_OVER_CUSTOMER_PHONE = '+12025550123';

beforeEach(function () {
    $this->owner = User::factory()->withPhone(TAKE_OVER_OWNER_PHONE)->create();
    $this->actingAs($this->owner);
});

/**
 * Dana's first text has arrived, so her conversation exists. The text is not answered yet.
 */
function takeOverDanaHasTexted(string $body = 'My AC is blowing warm air'): Conversation
{
    frontDeskCustomerTexts($body);

    return Conversation::query()->sole();
}

/**
 * The owner presses Take over on the conversation's page.
 */
function takeOverOwnerPressesTakeOver(Conversation $conversation): void
{
    Livewire::test(ViewConversation::class, ['record' => $conversation->id])->callAction('takeOver');
}

/**
 * Dana sends one more text, with its own message id.
 */
function takeOverDanaTextsAgain(string $body, string $id): void
{
    test()->post(route('webhooks.text.fake'), fakeTextPayload([
        'MessageSid' => 'SM'.str_pad($id, 32, 'e', STR_PAD_LEFT),
        'Body' => $body,
    ]))->assertOk();
}

/**
 * The texts that reached Dana's phone, leaving out the alerts to the owner.
 *
 * @return list<array{to: string, body: string, provider_message_id: string}>
 */
function takeOverTextsToDana(): array
{
    return array_values(array_filter(
        textsSentToCustomers(),
        fn (array $text): bool => $text['to'] === TAKE_OVER_CUSTOMER_PHONE,
    ));
}

it('shows Take over on an active conversation and records who took over (D-26)', function () {
    $conversation = Conversation::factory()->create();

    Livewire::test(ViewConversation::class, ['record' => $conversation->id])
        ->assertActionVisible('takeOver')
        ->assertActionHidden('handBack')
        ->callAction('takeOver')
        ->assertActionHidden('takeOver')
        ->assertActionVisible('handBack');

    $conversation->refresh();

    expect($conversation->paused_at)->not->toBeNull()
        ->and($conversation->paused_reason)->toBe(PausedReason::TakenOver)
        ->and($conversation->paused_by)->toBe($this->owner->id)
        ->and($conversation->isPaused())->toBeTrue();
});

it('hides Take over on a closed conversation', function () {
    $conversation = Conversation::factory()->closed()->create();

    Livewire::test(ViewConversation::class, ['record' => $conversation->id])
        ->assertActionHidden('takeOver')
        ->assertActionHidden('handBack');
});

it('answers nothing, never asks the agent, and alerts the owner when the customer texts during a take over (D-24)', function () {
    $conversation = takeOverDanaHasTexted();
    takeOverOwnerPressesTakeOver($conversation);
    scriptFrontDeskReplies(['reply' => 'Yes, the technician is on the way.']);
    takeOverDanaTextsAgain('Are you still coming?', '2');

    runQueuedFrontDeskJobs();

    $alert = OwnerAlert::query()->sole();

    expect(takeOverTextsToDana())->toBe([])
        ->and(Message::query()->where('conversation_id', $conversation->id)->where('direction', 'out')->count())->toBe(0)
        ->and($alert->reason)->toBe(AlertReason::PausedMessage)
        ->and($alert->body)->toContain('Are you still coming?')
        ->and($alert->sent_at)->not->toBeNull();

    FrontDeskAgent::assertNeverPrompted();
});

it('raises one alert quoting the last text when three arrive in one burst while paused (D-24)', function () {
    $conversation = takeOverDanaHasTexted('First text');
    takeOverOwnerPressesTakeOver($conversation);
    takeOverDanaTextsAgain('Second text', '2');
    takeOverDanaTextsAgain('Third text', '3');

    runQueuedFrontDeskJobs();

    $alert = OwnerAlert::query()->sole();

    expect($alert->reason)->toBe(AlertReason::PausedMessage)
        ->and($alert->body)->toContain('Third text')
        ->and($alert->body)->not->toContain('Second text')
        ->and(takeOverTextsToDana())->toBe([]);
});

it('refuses automated sends while paused and lets the owner through', function () {
    $conversation = Conversation::factory()->paused()->create();
    $sender = app(OutboundSender::class);

    expect($sender->whyBlocked($conversation, SendOrigin::Automated))->toBe('The assistant is paused in this conversation.')
        ->and($sender->whyBlocked($conversation, SendOrigin::Owner))->toBeNull();
});

it('lets the agent answer again once the owner hands the conversation back', function () {
    $conversation = takeOverDanaHasTexted();
    takeOverOwnerPressesTakeOver($conversation);
    takeOverDanaTextsAgain('Are you still coming?', '2');
    runQueuedFrontDeskJobs();

    Livewire::test(ViewConversation::class, ['record' => $conversation->id])
        ->callAction('handBack')
        ->assertActionVisible('takeOver')
        ->assertActionHidden('handBack');

    $conversation->refresh();

    expect($conversation->paused_at)->toBeNull()
        ->and($conversation->paused_reason)->toBeNull()
        ->and($conversation->paused_by)->toBeNull();

    scriptFrontDeskReplies(['reply' => 'What is the system doing?', 'label' => 'intake_question']);
    takeOverDanaTextsAgain('Hello again', '3');
    runQueuedFrontDeskJobs();

    expect(takeOverTextsToDana())->toHaveCount(1)
        ->and(takeOverTextsToDana()[0]['body'])->toContain('What is the system doing?');
});

it('writes agent_paused and agent_resumed to the timeline and the card (D-23)', function () {
    $conversation = takeOverDanaHasTexted();
    takeOverOwnerPressesTakeOver($conversation);
    Livewire::test(ViewConversation::class, ['record' => $conversation->id])->callAction('handBack');

    $contact = $conversation->contact;
    $summaries = $contact->activities()
        ->whereIn('type', [ActivityType::AgentPaused, ActivityType::AgentResumed])
        ->orderBy('id')
        ->get()
        ->mapWithKeys(fn ($activity): array => [$activity->type->value => $activity->summary])
        ->all();

    $card = app(CustomerCard::class)->render($contact);

    expect($summaries)->toBe([
        'agent_paused' => 'Owner took over the conversation',
        'agent_resumed' => 'Handed back to the assistant',
    ])
        ->and($card)->toContain('Assistant paused')
        ->toContain('Owner took over the conversation')
        ->toContain('Assistant resumed')
        ->toContain('Handed back to the assistant');
});

it('holds a reply composed before Take over as a refused draft and sends nothing', function () {
    $conversation = takeOverDanaHasTexted();

    FrontDeskAgent::fake(function () use ($conversation) {
        app(PauseConversation::class)->handle($conversation->fresh(), PausedReason::TakenOver);

        return [
            'reply' => 'What is the system doing?',
            'label' => 'intake_question',
            'wants_person' => false,
            'service_slug' => null,
            'intake_answers' => [],
            'customer_name' => null,
            'possible_danger' => false,
        ];
    })->preventStrayPrompts();

    runQueuedFrontDeskJobs();

    $draft = Message::query()->where('conversation_id', $conversation->id)->where('direction', 'out')->sole();

    expect(takeOverTextsToDana())->toBe([])
        ->and($draft->status)->toBe(MessageStatus::Draft)
        ->and($draft->gate_outcome)->toBe(GateOutcome::Hold)
        ->and($draft->gate_reason)->toBe('send_refused');
});

it('refuses an automated send on a paused conversation inside the sender', function () {
    $conversation = Conversation::factory()->paused()->create();

    expect(fn () => app(OutboundSender::class)->send(
        $conversation,
        'Hello',
        MessageAuthor::Agent,
        SendOrigin::Automated,
    ))->toThrow(DomainException::class, 'The assistant is paused in this conversation.');

    expect(takeOverTextsToDana())->toBe([]);
});

it('pauses the assistant with reason owner_replied when the owner sends a fresh reply (D-25)', function () {
    $conversation = takeOverDanaHasTexted();

    Livewire::test(ConversationThread::class, ['conversation' => $conversation])
        ->set('body', 'I can come at 3')
        ->call('sendReply')
        ->assertHasNoErrors();

    $conversation->refresh();

    expect(takeOverTextsToDana())->toHaveCount(1)
        ->and($conversation->isPaused())->toBeTrue()
        ->and($conversation->paused_reason)->toBe(PausedReason::OwnerReplied)
        ->and($conversation->paused_by)->toBe($this->owner->id);
});

it('shows the handling banner on a paused thread and none on an active one (D-25)', function () {
    $active = Conversation::factory()->create();
    $takenOver = Conversation::factory()->paused(PausedReason::TakenOver)->create();
    $replied = Conversation::factory()->paused(PausedReason::OwnerReplied)->create();
    $asked = Conversation::factory()->paused(PausedReason::AskedForPerson)->create();

    Livewire::test(ConversationThread::class, ['conversation' => $active])
        ->assertDontSee("You're handling this.");

    Livewire::test(ConversationThread::class, ['conversation' => $takenOver])
        ->assertSee(e("You're handling this. Hand back to the assistant when done."), false);

    Livewire::test(ConversationThread::class, ['conversation' => $replied])
        ->assertSee(e("You're handling this. Hand back to the assistant when done."), false);

    Livewire::test(ConversationThread::class, ['conversation' => $asked])
        ->assertSee(e("The customer asked for a person. You're handling this. Hand back to the assistant when done."), false);
});

it('does not pause when the owner reply could not be sent', function () {
    $conversation = takeOverDanaHasTexted();
    $this->app->bind(TextChannel::class, fn () => new class extends FakeTextChannel
    {
        public function send(string $toE164, string $body): string
        {
            throw new RuntimeException('Carrier rejected the number');
        }
    });

    Livewire::test(ConversationThread::class, ['conversation' => $conversation])
        ->set('body', 'I can come at 3')
        ->call('sendReply');

    expect($conversation->fresh()->isPaused())->toBeFalse();
});

it('keeps the reason taken_over when the owner replies after taking over', function () {
    $conversation = takeOverDanaHasTexted();
    takeOverOwnerPressesTakeOver($conversation);

    Livewire::test(ConversationThread::class, ['conversation' => $conversation->fresh()])
        ->set('body', 'On my way')
        ->call('sendReply')
        ->assertHasNoErrors();

    expect($conversation->fresh()->paused_reason)->toBe(PausedReason::TakenOver);
});
