<?php

use App\Actions\PauseConversation;
use App\Channels\FakeTextChannel;
use App\Channels\TextChannel;
use App\Enums\ActivityType;
use App\Enums\DraftResolution;
use App\Enums\MessageAuthor;
use App\Enums\MessageKind;
use App\Enums\MessageStatus;
use App\Enums\PausedReason;
use App\Livewire\ConversationThread;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Records\CustomerCard;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

const DRAFT_REVIEW_OWNER_PHONE = '+12025550199';

const DRAFT_REVIEW_DRAFT_WORDS = 'Mike will text you a price for a new unit.';

const DRAFT_REVIEW_SIGN_OFF = 'Example Heating & Air';

/**
 * Tuesday 2026-10-13 at 4:30 pm in the business zone, the owner signed in, and Dana's price question answered by a
 * sent holding reply and a held draft. Returns the owner.
 */
function draftReviewPriceQuestionHeld(): User
{
    test()->travelTo(Carbon::parse('2026-10-13 16:30', 'America/Chicago'));

    $owner = User::factory()->withPhone(DRAFT_REVIEW_OWNER_PHONE)->create();

    frontDeskCustomerTexts('How much for a new unit?');
    scriptFrontDeskReplies(['reply' => DRAFT_REVIEW_DRAFT_WORDS, 'label' => 'price']);
    runQueuedFrontDeskJobs();

    test()->actingAs($owner);

    return $owner;
}

/**
 * Dana texts again and the front desk answers that burst with a new held draft, which replaces the waiting one.
 */
function draftReviewCustomerTextsAgain(): void
{
    test()->post(route('webhooks.text.fake'), fakeTextPayload([
        'MessageSid' => 'SM'.str_repeat('e', 32),
        'Body' => 'Also, do you do heat pumps?',
    ]))->assertOk();
    scriptFrontDeskReplies(['reply' => 'Mike will text you about both.', 'label' => 'price']);

    runQueuedFrontDeskJobs();
}

/**
 * The draft Dana's price question left, whatever has become of it since.
 */
function draftReviewTheDraft(): Message
{
    return Message::query()->where('kind', MessageKind::AgentReply->value)->orderBy('id')->firstOrFail();
}

/**
 * The thread component for the one conversation.
 */
function draftReviewThread(): Testable
{
    return Livewire::test(ConversationThread::class, ['conversation' => Conversation::query()->sole()]);
}

/**
 * The texts that reached Dana's phone, leaving out the alert to the owner.
 *
 * @return list<array{to: string, body: string, provider_message_id: string}>
 */
function draftReviewTextsToDana(): array
{
    return array_values(array_filter(textsSentToCustomers(), fn (array $text): bool => $text['to'] !== DRAFT_REVIEW_OWNER_PHONE));
}

it('shows the held reason and the draft in an editable box, and keeps the draft out of the thread list (GATE-04)', function () {
    draftReviewPriceQuestionHeld();
    $draft = draftReviewTheDraft();

    $thread = draftReviewThread();

    $thread->assertSee('Needs you')
        ->assertSee('Held: asks about price')
        ->assertSet('draftId', $draft->id)
        ->assertSet('draftBody', DRAFT_REVIEW_DRAFT_WORDS);

    expect($thread->instance()->messages->pluck('id')->all())->not->toContain($draft->id);
});

it('sends the draft as written on the same message row and does not pause the assistant (D-25, D-27, D-54)', function () {
    $owner = draftReviewPriceQuestionHeld();
    $draft = draftReviewTheDraft();
    $messagesBefore = Message::query()->count();

    draftReviewThread()
        ->call('approveDraft')
        ->assertHasNoErrors()
        ->assertNotified(Notification::make()->title('Draft sent')->success())
        ->assertSet('draftId', null)
        ->assertSet('draftBody', '');

    $sent = DRAFT_REVIEW_DRAFT_WORDS."\n".DRAFT_REVIEW_SIGN_OFF;
    $draft->refresh();

    expect($draft->status)->toBe(MessageStatus::Sent)
        ->and($draft->author)->toBe(MessageAuthor::Agent)
        ->and($draft->body)->toBe($sent)
        ->and($draft->resolution)->toBe(DraftResolution::Approved)
        ->and($draft->resolved_by)->toBe($owner->id)
        ->and($draft->resolved_at)->not->toBeNull()
        ->and($draft->original_body)->toBeNull()
        ->and($draft->provider_message_id)->toStartWith('fake-')
        ->and(Message::query()->count())->toBe($messagesBefore)
        ->and(Conversation::query()->sole()->isPaused())->toBeFalse()
        ->and(draftReviewTextsToDana()[1]['body'])->toBe($sent)
        ->and(draftReviewTextsToDana())->toHaveCount(2)
        ->and(Conversation::query()->sole()->contact->activities()->where('summary', 'Draft approved by owner and sent')->count())->toBe(1);
});

it('sends the owner\'s edit exactly as typed, trimmed, with the sign-off, and keeps the agent\'s words (GATE-04, D-54)', function () {
    $owner = draftReviewPriceQuestionHeld();
    $draft = draftReviewTheDraft();

    draftReviewThread()
        ->set('draftBody', "  Mike can give you a price on Thursday morning.  \n")
        ->call('approveDraft')
        ->assertHasNoErrors();

    $draft->refresh();

    expect($draft->status)->toBe(MessageStatus::Sent)
        ->and($draft->resolution)->toBe(DraftResolution::Edited)
        ->and($draft->resolved_by)->toBe($owner->id)
        ->and($draft->original_body)->toBe(DRAFT_REVIEW_DRAFT_WORDS)
        ->and($draft->body)->toBe("Mike can give you a price on Thursday morning.\n".DRAFT_REVIEW_SIGN_OFF)
        ->and(draftReviewTextsToDana()[1]['body'])->toBe($draft->body)
        ->and(Conversation::query()->sole()->contact->activities()->where('summary', 'Draft edited by owner and sent')->count())->toBe(1);
});

it('treats the draft retyped without a change as sent as written', function () {
    draftReviewPriceQuestionHeld();
    $draft = draftReviewTheDraft();

    draftReviewThread()->set('draftBody', DRAFT_REVIEW_DRAFT_WORDS.' ')->call('approveDraft');

    expect($draft->refresh()->resolution)->toBe(DraftResolution::Approved)
        ->and($draft->original_body)->toBeNull();
});

it('discards the draft, sends nothing, and shows it on the timeline and the card (GATE-04)', function () {
    $owner = draftReviewPriceQuestionHeld();
    $draft = draftReviewTheDraft();
    $textsBefore = count(textsSentToCustomers());

    draftReviewThread()
        ->call('discardDraft')
        ->assertNotified(Notification::make()->title('Draft discarded')->success())
        ->assertSet('draftId', null)
        ->assertSet('draftBody', '');

    $draft->refresh();
    $contact = Conversation::query()->sole()->contact;
    $activity = $contact->activities()->where('type', ActivityType::DraftDiscarded)->sole();

    expect($draft->status)->toBe(MessageStatus::Withdrawn)
        ->and($draft->resolution)->toBe(DraftResolution::Discarded)
        ->and($draft->resolved_by)->toBe($owner->id)
        ->and($draft->resolved_at)->not->toBeNull()
        ->and($draft->body)->toBe(DRAFT_REVIEW_DRAFT_WORDS)
        ->and(count(textsSentToCustomers()))->toBe($textsBefore)
        ->and($activity->summary)->toBe('Draft discarded by owner')
        ->and(app(CustomerCard::class)->render($contact->fresh()))->toContain('Draft discarded by owner');
});

it('tells the card the held draft was later sent by the owner, not left unsent', function () {
    draftReviewPriceQuestionHeld();

    draftReviewThread()->call('approveDraft');

    $card = app(CustomerCard::class)->render(Conversation::query()->sole()->contact->fresh());

    expect($card)->toContain('The owner then sent it to the customer:')
        ->and($card)->not->toContain('Draft, not sent:');
});

it('refuses to send a replaced draft, keeps the owner\'s edit, and shows the new draft (D-28)', function () {
    draftReviewPriceQuestionHeld();
    $old = draftReviewTheDraft();

    $thread = draftReviewThread()->set('draftBody', 'Mike can give you a price on Thursday morning.');

    draftReviewCustomerTextsAgain();
    $textsBefore = count(textsSentToCustomers());

    $thread->call('approveDraft')
        ->assertNotified(Notification::make()->title(e("The customer wrote again, here's the new draft"))->danger())
        ->assertSet('draftBody', 'Mike can give you a price on Thursday morning.')
        ->assertSee('Mike will text you about both.');

    $new = Message::query()->where('status', MessageStatus::Draft->value)->sole();

    expect($old->refresh()->status)->toBe(MessageStatus::Withdrawn)
        ->and($old->resolution)->toBe(DraftResolution::Replaced)
        ->and($new->id)->not->toBe($old->id)
        ->and(count(textsSentToCustomers()))->toBe($textsBefore)
        ->and($thread->get('draftId'))->toBe($new->id);
});

it('refuses to discard a replaced draft and leaves the new one waiting (D-28)', function () {
    draftReviewPriceQuestionHeld();

    $thread = draftReviewThread();

    draftReviewCustomerTextsAgain();

    $thread->call('discardDraft')
        ->assertNotified(Notification::make()->title(e("The customer wrote again, here's the new draft"))->danger());

    expect(Message::query()->where('status', MessageStatus::Draft->value)->count())->toBe(1)
        ->and(Message::query()->where('resolution', DraftResolution::Discarded->value)->exists())->toBeFalse();
});

it('refuses a draft that was already sent from another tab and sends nothing twice', function () {
    draftReviewPriceQuestionHeld();

    $thread = draftReviewThread();
    $other = draftReviewThread();

    $thread->call('approveDraft')->assertHasNoErrors();
    $textsBefore = count(textsSentToCustomers());

    $other->call('approveDraft')
        ->assertNotified(Notification::make()->title('This draft was already handled.')->danger());

    expect(count(textsSentToCustomers()))->toBe($textsBefore);
});

it('refuses an empty box with a sentence and leaves the draft waiting', function () {
    draftReviewPriceQuestionHeld();
    $textsBefore = count(textsSentToCustomers());

    draftReviewThread()
        ->set('draftBody', '   ')
        ->call('approveDraft')
        ->assertHasErrors(['draftBody'])
        ->assertSee('Type a message first.');

    expect(draftReviewTheDraft()->status)->toBe(MessageStatus::Draft)
        ->and(count(textsSentToCustomers()))->toBe($textsBefore);
});

it('sends an edit whose composed text is exactly 1,600 characters (GATE-04 boundary)', function () {
    draftReviewPriceQuestionHeld();
    $room = 1600 - mb_strlen("\n".DRAFT_REVIEW_SIGN_OFF);

    draftReviewThread()
        ->set('draftBody', str_repeat('a', $room))
        ->call('approveDraft')
        ->assertHasNoErrors();

    $draft = draftReviewTheDraft()->refresh();

    expect($draft->status)->toBe(MessageStatus::Sent)
        ->and(mb_strlen($draft->body))->toBe(1600);
});

it('refuses an edit whose composed text is 1,601 characters and keeps it in the box (GATE-04 boundary)', function () {
    draftReviewPriceQuestionHeld();
    $textsBefore = count(textsSentToCustomers());
    $tooLong = str_repeat('a', 1600 - mb_strlen("\n".DRAFT_REVIEW_SIGN_OFF) + 1);

    draftReviewThread()
        ->set('draftBody', $tooLong)
        ->call('approveDraft')
        ->assertHasErrors(['draftBody'])
        ->assertSee('Keep replies to 1,600 characters or fewer.')
        ->assertSet('draftBody', $tooLong);

    expect(draftReviewTheDraft()->status)->toBe(MessageStatus::Draft)
        ->and(count(textsSentToCustomers()))->toBe($textsBefore);
});

it('sends the draft in a taken-over conversation and the pause stays', function () {
    $owner = draftReviewPriceQuestionHeld();

    app(PauseConversation::class)->handle(Conversation::query()->sole(), PausedReason::TakenOver, $owner);

    draftReviewThread()->call('approveDraft')->assertHasNoErrors();

    $conversation = Conversation::query()->sole();

    expect(draftReviewTheDraft()->status)->toBe(MessageStatus::Sent)
        ->and($conversation->isPaused())->toBeTrue()
        ->and($conversation->paused_reason)->toBe(PausedReason::TakenOver);
});

it('keeps the owner\'s words in the reply box when the text channel fails, and records the failure on the draft row', function () {
    draftReviewPriceQuestionHeld();
    $draft = draftReviewTheDraft();

    $this->app->bind(TextChannel::class, fn () => new class extends FakeTextChannel
    {
        public function send(string $toE164, string $body): string
        {
            throw new RuntimeException('Carrier rejected the number');
        }
    });

    draftReviewThread()
        ->set('draftBody', 'Mike can give you a price on Thursday morning.')
        ->call('approveDraft')
        ->assertNotified(Notification::make()->title('The reply was not sent: Carrier rejected the number')->danger())
        ->assertSet('body', 'Mike can give you a price on Thursday morning.');

    expect($draft->refresh()->status)->toBe(MessageStatus::Failed)
        ->and($draft->meta['error'])->toBe('Carrier rejected the number');
});

it('does not let the browser point the component at a different message (T-02-44)', function () {
    draftReviewPriceQuestionHeld();
    $thread = draftReviewThread();
    $other = Message::factory()->for(Conversation::factory()->create())->draft()->create();

    expect(fn () => $thread->set('draftId', $other->id))
        ->toThrow('Cannot update locked property');
});

it('escapes the draft and the reason in the needs-you block (T-02-45)', function () {
    draftReviewPriceQuestionHeld();

    $thread = draftReviewThread();

    draftReviewTheDraft()->update([
        'body' => '<script>alert(1)</script> Mike will call.',
        'gate' => ['version' => 1, 'reason' => ['code' => 'code:price', 'plain' => 'Held: <img src=x onerror=alert(2)>']],
    ]);

    $html = $thread->call('$refresh')->html();

    expect($html)->not->toContain('<script>alert(1)')
        ->and($html)->not->toContain('<img src=x')
        ->and($html)->toContain('Held: &lt;img src=x onerror=alert(2)&gt;')
        ->and($html)->toContain('&lt;script&gt;alert(1)&lt;/script&gt; Mike will call.');
});
