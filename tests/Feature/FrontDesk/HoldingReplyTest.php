<?php

use App\Actions\ResolveDraft;
use App\Actions\SendFixedReply;
use App\Enums\AlertReason;
use App\Enums\DraftResolution;
use App\Enums\GateOutcome;
use App\Enums\MessageAuthor;
use App\Enums\MessageKind;
use App\Enums\MessageStatus;
use App\Icm\ActiveConfig;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\OwnerAlert;
use App\Models\User;
use Illuminate\Support\Carbon;

const HOLDING_REPLY_OWNER_PHONE = '+12025550199';

const HOLDING_REPLY_CUSTOMER_PHONE = '+12025550123';

const HOLDING_REPLY_COMPLAINT_LINE = "I'm sorry about this. I've passed your message straight to Mike, and he'll get back to you after we open at 8 am tomorrow.";

const HOLDING_REPLY_DISCOUNT_LINE = "We like to take care of our customers. I'll make sure Mike knows you asked about a discount and he'll pick up the thread.";

const HOLDING_REPLY_GENERAL_LINE = "Thanks. I've passed this to Mike and you're on his list. He'll get back to you after we open at 8 am tomorrow.";

const HOLDING_REPLY_AC_SENTENCE = 'AC repair starts with an $89 diagnostic visit, and we take the $89 off if you go ahead with the repair that visit.';

/**
 * Tuesday 2026-10-13 at the given business-zone time, the owner with a phone, and Dana's price question answered
 * by a held draft.
 */
function holdingReplyPriceQuestionHeld(string $at = '2026-10-13 16:30'): void
{
    test()->travelTo(Carbon::parse($at, 'America/Chicago'));

    User::factory()->withPhone(HOLDING_REPLY_OWNER_PHONE)->create();

    frontDeskCustomerTexts('How much for a new unit?');
    scriptFrontDeskReplies(['reply' => 'Mike will text you a price for a new unit.', 'label' => 'price']);

    runQueuedFrontDeskJobs();
}

/**
 * Dana texts again and the front desk answers that burst with the given script.
 *
 * @param  array<string, mixed>  $reply
 */
function holdingReplyCustomerTextsAgain(array $reply = ['reply' => 'Mike will text you about both.', 'label' => 'price']): void
{
    static $count = 0;
    $count++;

    test()->post(route('webhooks.text.fake'), fakeTextPayload([
        'MessageSid' => 'SM'.str_pad((string) $count, 32, 'f', STR_PAD_LEFT),
        'Body' => "Following up, text number {$count}",
    ]))->assertOk();
    scriptFrontDeskReplies($reply);

    runQueuedFrontDeskJobs();
}

/**
 * The texts that reached Dana's phone, leaving out the alerts to the owner.
 *
 * @return list<array{to: string, body: string, provider_message_id: string}>
 */
function holdingReplyTextsToDana(): array
{
    return array_values(array_filter(
        textsSentToCustomers(),
        fn (array $text): bool => $text['to'] === HOLDING_REPLY_CUSTOMER_PHONE,
    ));
}

/**
 * The holding replies stored so far, oldest first.
 *
 * @return list<Message>
 */
function holdingReplyMessages(): array
{
    return Message::query()->where('kind', MessageKind::HoldingReply->value)->orderBy('id')->get()->all();
}

it('texts the customer a holding reply that says when the owner will answer, with the disclosure and sign-off (D-12, D-13, D-50)', function () {
    holdingReplyPriceQuestionHeld('2026-10-13 16:30');

    $words = "Thanks. I've passed this to Mike and you're on his list. He'll get back to you after we open at 8 am tomorrow.";
    $voice = app(ActiveConfig::class)->voice();
    $holding = holdingReplyMessages();

    expect(holdingReplyTextsToDana())->toHaveCount(1)
        ->and(holdingReplyTextsToDana()[0]['body'])->toBe($voice['ai_disclosure']."\n\n".$words."\n".$voice['sign_off'])
        ->and($holding)->toHaveCount(1)
        ->and($holding[0]->author)->toBe(MessageAuthor::Agent)
        ->and($holding[0]->status)->toBe(MessageStatus::Sent)
        ->and($holding[0]->gate_outcome)->toBe(GateOutcome::Send)
        ->and($holding[0]->gate_reason)->toBe('fixed_reply')
        ->and($holding[0]->gate['reason']['code'])->toBe('fixed_reply');
});

it('does not ask Jev about the holding reply, because code wrote it, only about the model\'s words (D-12, D-45)', function () {
    holdingReplyPriceQuestionHeld();

    expect(holdingReplyMessages())->toHaveCount(1)
        ->and(array_column(fakeClassifier()->calls(), 'draft_reply'))->toBe(['Mike will text you a price for a new unit.']);
});

it('says today while the shop is open and leaves the disclosure out when an agent text already went out (D-13, D-50)', function () {
    test()->travelTo(Carbon::parse('2026-10-13 10:00', 'America/Chicago'));
    User::factory()->withPhone(HOLDING_REPLY_OWNER_PHONE)->create();

    frontDeskCustomerTexts();
    scriptFrontDeskReplies(['reply' => 'What is the system doing?', 'label' => 'intake_question']);
    runQueuedFrontDeskJobs();

    holdingReplyCustomerTextsAgain();

    $voice = app(ActiveConfig::class)->voice();
    $texts = holdingReplyTextsToDana();
    $body = $texts[1]['body'] ?? '';

    expect($texts)->toHaveCount(2)
        ->and($body)->toEndWith("He'll get back to you today if possible, or after we open at 8 am tomorrow.\n".$voice['sign_off'])
        ->and($body)->not->toContain($voice['ai_disclosure']);
});

it('raises the owner alert before the holding reply goes to the customer', function () {
    holdingReplyPriceQuestionHeld();

    $order = array_map(fn (array $text): string => $text['to'], textsSentToCustomers());

    expect($order)->toBe([HOLDING_REPLY_OWNER_PHONE, HOLDING_REPLY_CUSTOMER_PHONE]);
});

it('sends no second holding reply when a newer text replaces the waiting draft (D-14)', function () {
    holdingReplyPriceQuestionHeld();
    holdingReplyCustomerTextsAgain();

    expect(holdingReplyMessages())->toHaveCount(1)
        ->and(Message::query()->where('kind', MessageKind::AddedInfoReply->value)->count())->toBe(1)
        ->and(holdingReplyTextsToDana())->toHaveCount(2)
        ->and(Message::query()->where('status', MessageStatus::Draft->value)->count())->toBe(1);
});

it('sends a new holding reply after a real answer went out (D-14)', function () {
    holdingReplyPriceQuestionHeld();

    Message::factory()->for(Conversation::query()->sole())->fromOwner()->create(['body' => 'It is $9,000 installed.']);

    holdingReplyCustomerTextsAgain();

    expect(holdingReplyMessages())->toHaveCount(2)
        ->and(holdingReplyTextsToDana())->toHaveCount(2);
});

it('sends no third holding reply while the newest one is still unanswered (D-14)', function () {
    holdingReplyPriceQuestionHeld();

    Message::factory()->for(Conversation::query()->sole())->fromOwner()->create(['body' => 'It is $9,000 installed.']);

    test()->travel(5)->minutes();
    holdingReplyCustomerTextsAgain();

    test()->travel(5)->minutes();
    holdingReplyCustomerTextsAgain();

    expect(holdingReplyMessages())->toHaveCount(2)
        ->and(Message::query()->where('kind', MessageKind::AddedInfoReply->value)->count())->toBe(1)
        ->and(holdingReplyTextsToDana())->toHaveCount(3);
});

it('sends a new holding reply after the owner discarded the waiting draft (D-14)', function () {
    holdingReplyPriceQuestionHeld();

    Message::query()->where('status', MessageStatus::Draft->value)->sole()->update([
        'status' => MessageStatus::Withdrawn,
        'resolution' => DraftResolution::Discarded,
        'resolved_at' => now(),
    ]);

    holdingReplyCustomerTextsAgain();

    expect(holdingReplyMessages())->toHaveCount(2)
        ->and(holdingReplyTextsToDana())->toHaveCount(2);
});

it('sends no holding reply for a reply that goes out on its own', function () {
    test()->travelTo(Carbon::parse('2026-10-13 10:00', 'America/Chicago'));

    frontDeskCustomerTexts();
    scriptFrontDeskReplies(['reply' => 'What is the system doing?', 'label' => 'intake_question']);
    runQueuedFrontDeskJobs();

    expect(holdingReplyMessages())->toBe([])
        ->and(holdingReplyTextsToDana())->toHaveCount(1);
});

it('still sends the holding reply when the draft is held because the safety check was unsure (flagged assumption)', function () {
    test()->travelTo(Carbon::parse('2026-10-13 10:00', 'America/Chicago'));

    frontDeskCustomerTexts();
    scriptFrontDeskReplies(['reply' => 'We are open until 5 pm today.', 'label' => 'faq_answer']);
    fakeClassifier()->answer('faq', 0.84);
    runQueuedFrontDeskJobs();

    expect(holdingReplyMessages())->toHaveCount(1)
        ->and(Message::query()->where('gate_reason', 'classifier_unsure:intent')->sole()->status)->toBe(MessageStatus::Draft);
});

it('counts every kind of line that starts a wait as the holding reply, and an owner text ends that wait (D-30)', function (MessageKind $kind) {
    holdingReplyPriceQuestionHeld();

    $conversation = Conversation::query()->sole();
    holdingReplyMessages()[0]->update(['kind' => $kind]);

    expect(app(SendFixedReply::class)->holdingAlreadySent($conversation))->toBeTrue();

    Message::factory()->for($conversation)->fromOwner()->create(['body' => 'It is $9,000 installed.']);

    expect(app(SendFixedReply::class)->holdingAlreadySent($conversation))->toBeFalse();
})->with(fn (): array => array_combine(
    array_map(fn (MessageKind $kind): string => $kind->value, MessageKind::startsWait()),
    array_map(fn (MessageKind $kind): array => [$kind], MessageKind::startsWait()),
));

it('keeps the wait going when an automated text goes out while the draft waits (D-30)', function (string $kind) {
    holdingReplyPriceQuestionHeld();

    $conversation = Conversation::query()->sole();

    Message::factory()->for($conversation)->outbound()->create([
        'kind' => MessageKind::from($kind),
        'body' => 'An automated line that does not answer the waiting question.',
    ]);

    expect(app(SendFixedReply::class)->holdingAlreadySent($conversation))->toBeTrue();
})->with(['check_in_reply', 'added_info_reply', 'code_answer', 'agent_reply']);

it('ends the wait when the waiting draft is closed as answered (D-21, D-30)', function () {
    holdingReplyPriceQuestionHeld();

    $conversation = Conversation::query()->sole();

    expect(app(SendFixedReply::class)->holdingAlreadySent($conversation))->toBeTrue();

    $answer = Message::factory()->outbound()->for($conversation)->create([
        'kind' => MessageKind::CodeAnswer,
        'body' => 'A repair visit is $89.',
    ]);

    app(ResolveDraft::class)->closeAsAnswered(Message::query()->where('status', MessageStatus::Draft->value)->sole(), $answer);

    expect(app(SendFixedReply::class)->holdingAlreadySent($conversation))->toBeFalse();
});

/**
 * Tuesday 2026-10-13 at 16:30 in the shop's zone, the owner with a phone, and Dana's text answered by one scripted
 * model reply that the label holds.
 *
 * @param  array<string, mixed>  $extra  Further fields of the scripted reply, such as price_services.
 */
function holdingReplyHeldFor(string $customerText, string $label, string $words, array $extra = []): void
{
    test()->travelTo(Carbon::parse('2026-10-13 16:30', 'America/Chicago'));

    User::factory()->withPhone(HOLDING_REPLY_OWNER_PHONE)->create();

    frontDeskCustomerTexts($customerText);
    scriptFrontDeskReplies([...['reply' => $words, 'label' => $label], ...$extra]);

    runQueuedFrontDeskJobs();
}

it('sends the reply that fits why the first draft was held (D-31)', function (string $customerText, string $label, string $words, string $line, MessageKind $kind) {
    holdingReplyHeldFor($customerText, $label, $words);

    $sent = Message::query()->where('kind', $kind->value)->sole();

    expect(holdingReplyTextsToDana())->toHaveCount(1)
        ->and(holdingReplyTextsToDana()[0]['body'])->toBe(composedFirstReply($line))
        ->and($sent->gate_reason)->toBe('fixed_reply')
        ->and($sent->gate_outcome)->toBe(GateOutcome::Send)
        ->and(array_column(fakeClassifier()->calls(), 'draft_reply'))->toBe([$words])
        ->and(Message::query()->where('status', MessageStatus::Draft->value)->count())->toBe(1);
})->with([
    'a complaint' => ['The technician left a mess in my basement', 'complaint', 'We are sorry to hear that.', HOLDING_REPLY_COMPLAINT_LINE, MessageKind::ComplaintHolding],
    'a discount' => ['Can you give me a discount?', 'discount', 'Let me check on that for you.', HOLDING_REPLY_DISCOUNT_LINE, MessageKind::DiscountHolding],
    'a price' => ['How much for a new unit?', 'price', 'Mike will text you a price.', HOLDING_REPLY_GENERAL_LINE, MessageKind::HoldingReply],
]);

it('raises the complaint alert for a held complaint beside the complaint reply (D-31)', function () {
    holdingReplyHeldFor('The technician left a mess in my basement', 'complaint', 'We are sorry to hear that.');

    expect(OwnerAlert::query()->sole()->reason)->toBe(AlertReason::Complaint);
});

it('sends only the complaint reply when a price sentence would have ridden in the same text, and keeps the sentence in the draft (D-36, D-43)', function () {
    holdingReplyHeldFor('The technician left a mess, and how much is the diagnostic for my AC?', 'complaint', 'We are sorry to hear that.', [
        'price_services' => ['ac-repair'],
    ]);

    $draft = Message::query()->where('status', MessageStatus::Draft->value)->sole();

    expect(holdingReplyTextsToDana())->toHaveCount(1)
        ->and(holdingReplyTextsToDana()[0]['body'])->toBe(composedFirstReply(HOLDING_REPLY_COMPLAINT_LINE))
        ->and(Message::query()->where('kind', MessageKind::ComplaintHolding->value)->count())->toBe(1)
        ->and(Message::query()->where('kind', MessageKind::PartialHolding->value)->count())->toBe(0)
        ->and($draft->gate_reason)->toBe('label:complaint')
        ->and($draft->body)->toBe('We are sorry to hear that.'."\n\n".HOLDING_REPLY_AC_SENTENCE)
        ->and($draft->meta['held_sentences'])->toBe([HOLDING_REPLY_AC_SENTENCE]);
});

it('sends no new holding reply for a later held draft after a complaint or discount reply (D-30)', function (string $customerText, string $label, string $words, MessageKind $kind) {
    holdingReplyHeldFor($customerText, $label, $words);

    holdingReplyCustomerTextsAgain(['reply' => 'Mike will text you about both.', 'label' => 'price']);

    expect(Message::query()->where('kind', $kind->value)->count())->toBe(1)
        ->and(Message::query()->where('kind', MessageKind::HoldingReply->value)->count())->toBe(0)
        ->and(Message::query()->where('kind', MessageKind::AddedInfoReply->value)->count())->toBe(1)
        ->and(holdingReplyTextsToDana())->toHaveCount(2)
        ->and(Message::query()->where('status', MessageStatus::Draft->value)->count())->toBe(1);
})->with([
    'a complaint' => ['The technician left a mess in my basement', 'complaint', 'We are sorry to hear that.', MessageKind::ComplaintHolding],
    'a discount' => ['Can you give me a discount?', 'discount', 'Let me check on that for you.', MessageKind::DiscountHolding],
]);
