<?php

use App\Ai\Agents\FrontDeskAgent;
use App\Enums\DraftResolution;
use App\Enums\GateOutcome;
use App\Enums\MessageKind;
use App\Enums\MessageStatus;
use App\Enums\ReplyLabel;
use App\Icm\ActiveConfig;
use App\Models\Message;
use App\Models\OwnerAlert;
use App\Models\User;
use Illuminate\Support\Carbon;

const EASY_ANSWER_OWNER_PHONE = '+12025550199';

const EASY_ANSWER_CUSTOMER_PHONE = '+12025550123';

const EASY_ANSWER_ADDED_INFO_LINE = "Got it, I've passed that along to Mike with your question.";

const EASY_ANSWER_PARTIAL_LINE = "I've passed your other question to Mike, and he'll follow up on it.";

const EASY_ANSWER_CHECK_IN_LINE = "Thanks for checking in. Mike still has your question and you're on his list. He'll get back to you today if possible, or after we open at 8 am tomorrow.";

const EASY_ANSWER_FIRST_VISIT_SENTENCE = "If you're a first-time customer, we're running a promotion that takes \$50 off your first visit.";

const EASY_ANSWER_AC_SENTENCE = 'AC repair starts with an $89 diagnostic visit, and we take the $89 off if you go ahead with the repair that visit.';

/**
 * Tuesday 2026-10-13 at 10:00 in the shop's zone, the owner with a phone, and Dana's price question held as a draft
 * with its holding reply and its one alert.
 */
function easyAnswerPriceQuestionHeld(): void
{
    test()->travelTo(Carbon::parse('2026-10-13 10:00', 'America/Chicago'));

    User::factory()->withPhone(EASY_ANSWER_OWNER_PHONE)->create();

    frontDeskCustomerTexts('How much for a new unit?');
    scriptFrontDeskReplies(['reply' => 'Mike will text you a price for a new unit.', 'label' => 'price']);

    runQueuedFrontDeskJobs();
}

/**
 * Tuesday 2026-10-13 at 10:00 in the shop's zone, the owner with a phone, and Dana's booking question held as a draft
 * labeled scheduling because the words promise a day.
 */
function easyAnswerSchedulingQuestionHeld(): void
{
    test()->travelTo(Carbon::parse('2026-10-13 10:00', 'America/Chicago'));

    User::factory()->withPhone(EASY_ANSWER_OWNER_PHONE)->create();

    frontDeskCustomerTexts('Can you come out Thursday morning?');
    scriptFrontDeskReplies(['reply' => 'We guarantee 9 am Thursday. Does that work?', 'label' => 'scheduling']);

    runQueuedFrontDeskJobs();
}

/**
 * Dana texts again a minute later and the front desk answers that burst with the given scripts, one per model call.
 *
 * @param  array<string, mixed>  ...$replies
 */
function easyAnswerCustomerTexts(string $body, array ...$replies): void
{
    static $count = 0;
    $count++;

    test()->travel(1)->minutes();

    test()->post(route('webhooks.text.fake'), fakeTextPayload([
        'MessageSid' => 'SM'.str_pad((string) $count, 32, 'e', STR_PAD_LEFT),
        'Body' => $body,
    ]))->assertOk();
    scriptFrontDeskReplies(...$replies);

    runQueuedFrontDeskJobs();
}

/**
 * The texts that reached Dana's phone, leaving out the alerts to the owner.
 *
 * @return list<array{to: string, body: string, provider_message_id: string}>
 */
function easyAnswerTextsToDana(): array
{
    return array_values(array_filter(
        textsSentToCustomers(),
        fn (array $text): bool => $text['to'] === EASY_ANSWER_CUSTOMER_PHONE,
    ));
}

/**
 * The words as Dana receives them after the first automated text: the sign-off closes them, the disclosure is left out.
 */
function easyAnswerLater(string $words): string
{
    return $words."\n".app(ActiveConfig::class)->voice()['sign_off'];
}

it('sends an answer that does not touch the waiting question and leaves the waiting draft live and untouched (D-18, D-22)', function () {
    easyAnswerPriceQuestionHeld();

    $waiting = Message::query()->where('status', MessageStatus::Draft->value)->sole();

    easyAnswerCustomerTexts('Do you service heat pumps?', [
        'reply' => 'Yes, we service heat pumps.',
        'label' => 'faq_answer',
        'touches_waiting' => false,
    ]);

    $sent = Message::query()->where('body', easyAnswerLater('Yes, we service heat pumps.'))->sole();
    $live = Message::query()->where('status', MessageStatus::Draft->value)->sole();

    expect(easyAnswerTextsToDana())->toHaveCount(2)
        ->and(easyAnswerTextsToDana()[1]['body'])->toBe(easyAnswerLater('Yes, we service heat pumps.'))
        ->and($sent->status)->toBe(MessageStatus::Sent)
        ->and($sent->kind)->toBe(MessageKind::AgentReply)
        ->and($live->id)->toBe($waiting->id)
        ->and($live->body)->toBe($waiting->body)
        ->and($live->gate_reason)->toBe($waiting->gate_reason)
        ->and($live->resolution)->toBeNull()
        ->and(OwnerAlert::query()->count())->toBe(1)
        ->and(Message::query()->where('kind', MessageKind::AddedInfoReply->value)->count())->toBe(0)
        ->and(Message::query()->where('kind', MessageKind::HoldingReply->value)->count())->toBe(1);
});

it('replaces the waiting draft with the reply that touches it and tells the customer it was passed along (D-18, D-29)', function (?bool $touches) {
    easyAnswerPriceQuestionHeld();

    $waiting = Message::query()->where('status', MessageStatus::Draft->value)->sole();

    $reply = [
        'reply' => 'Mike will text you a price for a new unit, and yes, we service heat pumps.',
        'label' => 'faq_answer',
    ];

    easyAnswerCustomerTexts('Do you service heat pumps too?', $touches === null ? $reply : [...$reply, 'touches_waiting' => $touches]);

    $live = Message::query()->where('status', MessageStatus::Draft->value)->sole();
    $addedInfo = Message::query()->where('kind', MessageKind::AddedInfoReply->value)->sole();

    expect($live->id)->not->toBe($waiting->id)
        ->and($live->body)->toBe('Mike will text you a price for a new unit, and yes, we service heat pumps.')
        ->and($live->gate_outcome)->toBe(GateOutcome::Hold)
        ->and($live->gate_reason)->toBe('touches_waiting')
        ->and($waiting->fresh()->status)->toBe(MessageStatus::Withdrawn)
        ->and($waiting->fresh()->resolution)->toBe(DraftResolution::Replaced)
        ->and(easyAnswerTextsToDana())->toHaveCount(2)
        ->and(easyAnswerTextsToDana()[1]['body'])->toBe(easyAnswerLater(EASY_ANSWER_ADDED_INFO_LINE))
        ->and($addedInfo->status)->toBe(MessageStatus::Sent)
        ->and(Message::query()->where('body', easyAnswerLater('Mike will text you a price for a new unit, and yes, we service heat pumps.'))->count())->toBe(0)
        ->and(OwnerAlert::query()->count())->toBe(1);
})->with([
    'touches_waiting true' => [true],
    'touches_waiting missing' => [null],
]);

it('lets only a faq answer or an intake question send while a scheduling draft waits (D-20)', function (string $label, string $words) {
    easyAnswerSchedulingQuestionHeld();

    $waiting = Message::query()->where('status', MessageStatus::Draft->value)->sole();

    expect($waiting->label->value)->toBe('scheduling');

    easyAnswerCustomerTexts('Do you do heat pumps?', ['reply' => $words, 'label' => $label, 'touches_waiting' => false]);

    $sent = Message::query()->where('body', easyAnswerLater($words))->sole();

    expect($sent->status)->toBe(MessageStatus::Sent)
        ->and($sent->gate['checks']['label']['guard'])->toBe(['faq_answer', 'intake_question'])
        ->and($sent->gate['checks']['label']['guard_passed'])->toBeTrue()
        ->and(Message::query()->where('status', MessageStatus::Draft->value)->sole()->id)->toBe($waiting->id)
        ->and(OwnerAlert::query()->count())->toBe(1);
})->with([
    'a faq answer' => ['faq_answer', 'Yes, we service heat pumps.'],
    'an intake question' => ['intake_question', 'What kind of heat pump do you have?'],
]);

it('holds a scheduling reply as waiting_guard:scheduling while a scheduling draft waits, and Jev still reads the words (D-20, D-45)', function () {
    easyAnswerSchedulingQuestionHeld();

    $waiting = Message::query()->where('status', MessageStatus::Draft->value)->sole();

    easyAnswerCustomerTexts('Is Friday open too?', [
        'reply' => 'We have 9 am Friday open. Does that work?',
        'label' => 'scheduling',
        'touches_waiting' => false,
    ]);

    $live = Message::query()->where('status', MessageStatus::Draft->value)->sole();

    expect($live->id)->not->toBe($waiting->id)
        ->and($live->gate_outcome)->toBe(GateOutcome::Hold)
        ->and($live->gate_reason)->toBe('waiting_guard:scheduling')
        ->and($live->gate['reason']['plain'])->toBe('Held: waits behind a question you already have')
        ->and($live->gate['checks']['label']['guard'])->toBe(['faq_answer', 'intake_question'])
        ->and($live->gate['checks']['label']['guard_passed'])->toBeFalse()
        ->and($live->gate['checks']['classifier']['ran'])->toBeTrue()
        ->and(array_column(fakeClassifier()->calls(), 'draft_reply'))->toBe(['We guarantee 9 am Thursday. Does that work?', 'We have 9 am Friday open. Does that work?'])
        ->and(Message::query()->where('body', easyAnswerLater('We have 9 am Friday open. Does that work?'))->count())->toBe(0);
});

it('lets a scheduling reply send while a price draft waits, with no guard (D-20)', function () {
    easyAnswerPriceQuestionHeld();

    $waiting = Message::query()->where('status', MessageStatus::Draft->value)->sole();

    easyAnswerCustomerTexts('Can you come out Thursday morning?', [
        'reply' => 'We have 9 am Thursday open. Does that work?',
        'label' => 'scheduling',
        'touches_waiting' => false,
    ]);

    $sent = Message::query()->where('body', easyAnswerLater('We have 9 am Thursday open. Does that work?'))->sole();

    expect($sent->status)->toBe(MessageStatus::Sent)
        ->and($sent->gate['checks']['label']['guard'])->toBeNull()
        ->and($sent->gate['checks']['label']['guard_passed'])->toBeNull()
        ->and(Message::query()->where('status', MessageStatus::Draft->value)->sole()->id)->toBe($waiting->id);
});

it('records no guard when no draft waits (D-20)', function () {
    test()->travelTo(Carbon::parse('2026-10-13 10:00', 'America/Chicago'));

    frontDeskCustomerTexts('Can you come out Thursday morning?');
    scriptFrontDeskReplies(['reply' => 'We have 9 am Thursday open. Does that work?', 'label' => 'scheduling']);
    runQueuedFrontDeskJobs();

    $sent = Message::query()->where('kind', MessageKind::AgentReply->value)->sole();

    expect($sent->status)->toBe(MessageStatus::Sent)
        ->and($sent->gate['checks']['label']['guard'])->toBeNull()
        ->and($sent->gate['checks']['label']['guard_passed'])->toBeNull();
});

/**
 * The model's script for a text that touches the waiting question and also asks something easy.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function easyAnswerSplitScript(array $overrides = []): array
{
    return [
        'reply' => 'Mike will text you about Friday and the price.',
        'label' => 'price',
        'touches_waiting' => true,
        'easy_reply' => 'Yes, we service heat pumps.',
        'easy_label' => 'faq_answer',
        ...$overrides,
    ];
}

it('answers the easy part now with the partial holding line and keeps the touching part as the fresh draft (D-19, D-20)', function () {
    easyAnswerPriceQuestionHeld();

    $waiting = Message::query()->where('status', MessageStatus::Draft->value)->sole();

    easyAnswerCustomerTexts('Do you do heat pumps? Also Friday might work better', easyAnswerSplitScript(['label' => 'scheduling']));

    $sent = Message::query()->where('kind', MessageKind::PartialHolding->value)->sole();
    $live = Message::query()->where('status', MessageStatus::Draft->value)->sole();

    expect(easyAnswerTextsToDana())->toHaveCount(2)
        ->and(easyAnswerTextsToDana()[1]['body'])->toBe(easyAnswerLater('Yes, we service heat pumps. '.EASY_ANSWER_PARTIAL_LINE))
        ->and($sent->meta['parts'])->toBe(['model_words', 'partial_holding_line'])
        ->and($sent->gate_outcome)->toBe(GateOutcome::Send)
        ->and($sent->label)->toBe(ReplyLabel::FaqAnswer)
        ->and($sent->gate['checks']['label']['guard'])->toBe(['faq_answer', 'intake_question'])
        ->and($sent->gate['checks']['label']['guard_passed'])->toBeTrue()
        ->and($sent->gate['inputs']['agent_reply'])->toBe('Yes, we service heat pumps.')
        ->and($live->id)->not->toBe($waiting->id)
        ->and($live->body)->toBe('Mike will text you about Friday and the price.')
        ->and($live->gate_reason)->toBe('touches_waiting')
        ->and($waiting->fresh()->resolution)->toBe(DraftResolution::Replaced)
        ->and(array_column(fakeClassifier()->calls(), 'draft_reply'))->toBe(['Mike will text you a price for a new unit.', 'Mike will text you about Friday and the price.', 'Yes, we service heat pumps.'])
        ->and(Message::query()->where('body', 'like', '%Friday%')->where('status', MessageStatus::Sent->value)->count())->toBe(0)
        ->and(OwnerAlert::query()->count())->toBe(1);
});

it('sends no easy words when Jev holds the easy part, and still tells the customer it was passed along (D-20)', function () {
    easyAnswerPriceQuestionHeld();
    fakeClassifier()->answer('faq', 0.95, ['promise' => 0.9]);

    easyAnswerCustomerTexts('Do you do heat pumps? Also Friday might work better', easyAnswerSplitScript());

    expect(array_column(fakeClassifier()->calls(), 'draft_reply'))->toBe(['Mike will text you a price for a new unit.', 'Mike will text you about Friday and the price.', 'Yes, we service heat pumps.'])
        ->and(easyAnswerTextsToDana())->toHaveCount(2)
        ->and(easyAnswerTextsToDana()[1]['body'])->toBe(easyAnswerLater(EASY_ANSWER_ADDED_INFO_LINE))
        ->and(Message::query()->where('kind', MessageKind::AddedInfoReply->value)->count())->toBe(1)
        ->and(Message::query()->where('body', 'like', '%heat pumps%')->where('status', MessageStatus::Sent->value)->count())->toBe(0)
        ->and(Message::query()->where('status', MessageStatus::Draft->value)->sole()->gate_reason)->toBe('label:price');
});

it('holds a scheduling easy part with the guard, and Jev still reads its words (D-20, D-45)', function () {
    easyAnswerPriceQuestionHeld();

    easyAnswerCustomerTexts('Is Friday open? Also, do you do heat pumps?', easyAnswerSplitScript([
        'easy_reply' => 'We have 9 am Friday open. Does that work?',
        'easy_label' => 'scheduling',
    ]));

    expect(array_column(fakeClassifier()->calls(), 'draft_reply'))->toBe(['Mike will text you a price for a new unit.', 'Mike will text you about Friday and the price.', 'We have 9 am Friday open. Does that work?'])
        ->and(easyAnswerTextsToDana())->toHaveCount(2)
        ->and(easyAnswerTextsToDana()[1]['body'])->toBe(easyAnswerLater(EASY_ANSWER_ADDED_INFO_LINE))
        ->and(Message::query()->where('body', 'like', '%9 am Friday%')->where('status', MessageStatus::Sent->value)->count())->toBe(0);
});

it('does not split without a clear easy part on a clear touch (ANSR-03)', function (?bool $touches, string $easyReply, ?string $easyLabel) {
    easyAnswerPriceQuestionHeld();

    $waiting = Message::query()->where('status', MessageStatus::Draft->value)->sole();

    easyAnswerCustomerTexts('Do you do heat pumps? Also Friday might work better', easyAnswerSplitScript([
        'touches_waiting' => $touches,
        'easy_reply' => $easyReply,
        'easy_label' => $easyLabel,
    ]));

    expect(array_column(fakeClassifier()->calls(), 'draft_reply'))->toBe(['Mike will text you a price for a new unit.', 'Mike will text you about Friday and the price.'])
        ->and(easyAnswerTextsToDana())->toHaveCount(2)
        ->and(easyAnswerTextsToDana()[1]['body'])->toBe(easyAnswerLater(EASY_ANSWER_ADDED_INFO_LINE))
        ->and(Message::query()->where('kind', MessageKind::PartialHolding->value)->count())->toBe(0)
        ->and(Message::query()->where('status', MessageStatus::Draft->value)->sole()->id)->not->toBe($waiting->id);
})->with([
    'a blank easy reply' => [true, '', 'faq_answer'],
    'no easy label' => [true, 'Yes, we service heat pumps.', null],
    'an easy label outside the vocabulary' => [true, 'Yes, we service heat pumps.', 'banana'],
    'touches_waiting missing' => [null, 'Yes, we service heat pumps.', 'faq_answer'],
]);

it('puts the price sentence, the easy words and the partial holding line in one text (D-19, D-36)', function () {
    easyAnswerSchedulingQuestionHeld();

    $waiting = Message::query()->where('status', MessageStatus::Draft->value)->sole();

    easyAnswerCustomerTexts('How much is an AC repair? Also, do you do heat pumps?', easyAnswerSplitScript([
        'reply' => 'Mike will text you about Thursday.',
        'label' => 'scheduling',
        'price_services' => ['ac-repair'],
    ]));

    $sent = Message::query()->where('kind', MessageKind::PartialHolding->value)->sole();

    expect(easyAnswerTextsToDana())->toHaveCount(2)
        ->and(easyAnswerTextsToDana()[1]['body'])->toBe(easyAnswerLater(EASY_ANSWER_AC_SENTENCE.' Yes, we service heat pumps. '.EASY_ANSWER_PARTIAL_LINE))
        ->and($sent->meta['parts'])->toBe(['code_sentences', 'model_words', 'partial_holding_line'])
        ->and($sent->meta['price_sentences'][0]['slug'])->toBe('ac-repair')
        ->and(Message::query()->where('status', MessageStatus::Draft->value)->sole()->id)->not->toBe($waiting->id)
        ->and(Message::query()->where('status', MessageStatus::Draft->value)->sole()->gate_reason)->toBe('waiting_guard:scheduling');
});

it('lets a check-in line lead the text that carries the easy words (D-28, D-36)', function () {
    easyAnswerPriceQuestionHeld();

    $waiting = Message::query()->where('status', MessageStatus::Draft->value)->sole();

    easyAnswerCustomerTexts('Any news? Also, do you do heat pumps?', easyAnswerSplitScript([
        'reply' => 'Mike still has your question.',
        'label' => 'faq_answer',
        'waiting_note' => 'check_in',
    ]));

    $sent = Message::query()->where('kind', MessageKind::AgentReply->value)->where('status', MessageStatus::Sent->value)->sole();

    expect(easyAnswerTextsToDana())->toHaveCount(2)
        ->and(easyAnswerTextsToDana()[1]['body'])->toBe(easyAnswerLater('Yes, we service heat pumps. '.EASY_ANSWER_CHECK_IN_LINE))
        ->and($sent->meta['parts'])->toBe(['model_words', 'check_in_line'])
        ->and(Message::query()->where('status', MessageStatus::Draft->value)->sole()->id)->toBe($waiting->id)
        ->and(OwnerAlert::query()->count())->toBe(2);
});

it('sends only the safety line when Jev reads a danger in the easy part (D-19)', function () {
    easyAnswerPriceQuestionHeld();
    fakeClassifier()->answer('faq', 0.95, ['life_safety' => 0.9]);

    easyAnswerCustomerTexts('Do you do heat pumps? Also my basement is flooding', easyAnswerSplitScript());

    expect(Message::query()->where('kind', MessageKind::LifeSafety->value)->count())->toBe(1)
        ->and(Message::query()->where('kind', MessageKind::PartialHolding->value)->count())->toBe(0)
        ->and(Message::query()->where('body', 'like', '%heat pumps%')->where('status', MessageStatus::Sent->value)->count())->toBe(0)
        ->and(Message::query()->where('status', MessageStatus::Draft->value)->sole()->gate_reason)->toBe('label:price');
});

/**
 * Dana changes the waiting booking and asks about discounts: the model's blank words carry the discount ask, and it
 * reads the text as touching the waiting question. The second reply is the whole-thread draft it is asked for.
 *
 * @param  array<string, mixed>  ...$secondReplies
 */
function easyAnswerFridayWithDiscountAsk(?bool $touches, array ...$secondReplies): void
{
    easyAnswerCustomerTexts('Actually Friday works better. Any discounts?', ...[
        ['reply' => '', 'label' => 'faq_answer', 'asks_discount' => true, 'touches_waiting' => $touches],
        ...$secondReplies,
    ]);
}

it('sends the offer with the partial line and replaces the stale draft with a whole-thread one when a blank-words answer touches it (WR-04, D-18, D-35)', function () {
    easyAnswerSchedulingQuestionHeld();

    $waiting = Message::query()->where('status', MessageStatus::Draft->value)->sole();

    easyAnswerFridayWithDiscountAsk(true, ['reply' => 'We have 9 am Friday open. Does that work?', 'label' => 'scheduling']);

    $live = Message::query()->where('status', MessageStatus::Draft->value)->sole();
    $sent = Message::query()->where('kind', MessageKind::PartialHolding->value)->sole();

    expect(easyAnswerTextsToDana())->toHaveCount(2)
        ->and(easyAnswerTextsToDana()[1]['body'])->toBe(easyAnswerLater(EASY_ANSWER_FIRST_VISIT_SENTENCE.' '.EASY_ANSWER_PARTIAL_LINE))
        ->and($sent->meta['parts'])->toBe(['code_sentences', 'partial_holding_line'])
        ->and($waiting->fresh()->status)->toBe(MessageStatus::Withdrawn)
        ->and($waiting->fresh()->resolution)->toBe(DraftResolution::Replaced)
        ->and($live->id)->not->toBe($waiting->id)
        ->and($live->body)->toBe('We have 9 am Friday open. Does that work?')
        ->and($live->gate_reason)->toBe('touches_waiting')
        ->and($live->gate['checks']['label']['guard'])->toBe(['faq_answer', 'intake_question'])
        ->and(array_column(fakeClassifier()->calls(), 'draft_reply'))->toBe(['We guarantee 9 am Thursday. Does that work?', 'We have 9 am Friday open. Does that work?'])
        ->and(OwnerAlert::query()->count())->toBe(1);
});

it('keeps the waiting draft live and untouched when the second call cannot answer (WR-04)', function () {
    easyAnswerSchedulingQuestionHeld();

    $waiting = Message::query()->where('status', MessageStatus::Draft->value)->sole();

    easyAnswerFridayWithDiscountAsk(true);

    $live = Message::query()->where('status', MessageStatus::Draft->value)->sole();

    expect(easyAnswerTextsToDana())->toHaveCount(2)
        ->and(easyAnswerTextsToDana()[1]['body'])->toBe(easyAnswerLater(EASY_ANSWER_FIRST_VISIT_SENTENCE.' '.EASY_ANSWER_PARTIAL_LINE))
        ->and($live->id)->toBe($waiting->id)
        ->and($live->body)->toBe($waiting->body)
        ->and($live->resolution)->toBeNull()
        ->and(OwnerAlert::query()->count())->toBe(1);
});

it('keeps the waiting draft live and untouched when the second reply has no words (WR-04)', function () {
    easyAnswerSchedulingQuestionHeld();

    $waiting = Message::query()->where('status', MessageStatus::Draft->value)->sole();

    easyAnswerFridayWithDiscountAsk(true, ['reply' => '', 'label' => 'scheduling', 'asks_discount' => true]);

    $live = Message::query()->where('status', MessageStatus::Draft->value)->sole();

    expect(easyAnswerTextsToDana())->toHaveCount(2)
        ->and(easyAnswerTextsToDana()[1]['body'])->toBe(easyAnswerLater(EASY_ANSWER_FIRST_VISIT_SENTENCE.' '.EASY_ANSWER_PARTIAL_LINE))
        ->and($live->id)->toBe($waiting->id)
        ->and($live->body)->toBe($waiting->body)
        ->and($live->resolution)->toBeNull();
});

it('sends only the offer and leaves the waiting draft live, with no second call, when the text does not clearly touch it (WR-04, D-18)', function (?bool $touches) {
    easyAnswerSchedulingQuestionHeld();

    $waiting = Message::query()->where('status', MessageStatus::Draft->value)->sole();

    easyAnswerFridayWithDiscountAsk($touches);

    $live = Message::query()->where('status', MessageStatus::Draft->value)->sole();

    FrontDeskAgent::assertPromptedTimes(2);

    expect(easyAnswerTextsToDana())->toHaveCount(2)
        ->and(easyAnswerTextsToDana()[1]['body'])->toBe(easyAnswerLater(EASY_ANSWER_FIRST_VISIT_SENTENCE))
        ->and(Message::query()->where('kind', MessageKind::PartialHolding->value)->count())->toBe(0)
        ->and($live->id)->toBe($waiting->id)
        ->and($live->resolution)->toBeNull();
})->with([
    'touches_waiting false' => [false],
    'touches_waiting missing' => [null],
]);
