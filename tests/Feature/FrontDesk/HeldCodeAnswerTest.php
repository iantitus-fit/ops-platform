<?php

use App\Channels\TextChannel;
use App\Enums\AlertReason;
use App\Enums\GateOutcome;
use App\Enums\MessageKind;
use App\Enums\MessageStatus;
use App\Icm\ActiveConfig;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\OwnerAlert;
use App\Models\User;
use App\SendRule\FixedReplies;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Mockery\MockInterface;

const HELD_CODE_ANSWER_OWNER_PHONE = '+12025550199';

const HELD_CODE_ANSWER_CUSTOMER_PHONE = '+12025550123';

const HELD_CODE_ANSWER_AC_SENTENCE = 'AC repair starts with an $89 diagnostic visit, and we take the $89 off if you go ahead with the repair that visit.';

const HELD_CODE_ANSWER_FIRST_VISIT = "If you're a first-time customer, we're running a promotion that takes \$50 off your first visit.";

const HELD_CODE_ANSWER_SENIOR = "If you're 65 or older, we take 10% off any repair.";

const HELD_CODE_ANSWER_LARGE_SENTENCE = 'For a larger system, 3 tons and up, a replacement system typically runs $5,000 to $10,000 installed.';

const HELD_CODE_ANSWER_WAITING_WORDS = 'Mike will text you the price for a new system.';

const HELD_CODE_ANSWER_WORDS = 'Yes, we work on Carrier units.';

const HELD_CODE_ANSWER_COMPLAINT_ASK = 'Your tech was rude and I want to complain. How much is a repair?';

/**
 * The scripted model answer for a blank-words price ask for the AC, labeled as the given label.
 *
 * @return array<string, mixed>
 */
function heldCodeAnswerBlankAcScript(string $label = 'complaint'): array
{
    return ['reply' => '', 'label' => $label, 'price_services' => ['ac-repair']];
}

/**
 * Dana's text at the given moment in the shop's zone, the owner with a phone, and the live example folder.
 */
function heldCodeAnswerCustomerTexts(string $body, string $at = '2026-10-13 10:00'): void
{
    test()->travelTo(Carbon::parse($at, 'America/Chicago'));

    User::factory()->withPhone(HELD_CODE_ANSWER_OWNER_PHONE)->create();

    frontDeskCustomerTexts($body);
}

/**
 * Dana's text, one scripted model answer and the queued run.
 *
 * @param  array<string, mixed>  $reply
 */
function heldCodeAnswerRun(string $body, array $reply, string $at = '2026-10-13 10:00'): void
{
    heldCodeAnswerCustomerTexts($body, $at);
    scriptFrontDeskReplies($reply);
    runQueuedFrontDeskJobs();
}

/**
 * The texts that reached Dana's phone, leaving out the alerts to the owner.
 *
 * @return list<array{to: string, body: string, provider_message_id: string}>
 */
function heldCodeAnswerTextsToDana(): array
{
    return array_values(array_filter(
        textsSentToCustomers(),
        fn (array $text): bool => $text['to'] === HELD_CODE_ANSWER_CUSTOMER_PHONE,
    ));
}

/**
 * The one draft the owner has waiting.
 */
function heldCodeAnswerTheDraft(): Message
{
    return Message::query()->where('status', MessageStatus::Draft->value)->sole();
}

/**
 * The draft the owner has waiting, or null, so a test can state what it expects before anything throws.
 */
function heldCodeAnswerMaybeDraft(): ?Message
{
    return Message::query()->where('status', MessageStatus::Draft->value)->first();
}

/**
 * Take complaint off the folder's right_away list. Call it before Dana's text arrives, because the folder is activated
 * when the text does.
 */
function heldCodeAnswerTakeComplaintOffRightAway(): void
{
    breakFile('rules/sending.md', fn (string $text): string => str_replace('right_away: [complaint, ', 'right_away: [', $text));
}

it('sends no price sentence beside a complaint, sends the complaint reply, alerts the owner and pre-fills the draft (CR-01, D-43, D-44)', function () {
    heldCodeAnswerRun(HELD_CODE_ANSWER_COMPLAINT_ASK, heldCodeAnswerBlankAcScript());

    $draft = heldCodeAnswerMaybeDraft();
    $alert = OwnerAlert::query()->first();
    $complaintReply = Message::query()->where('kind', MessageKind::ComplaintHolding->value)->first();

    expect(Message::query()->where('kind', MessageKind::CodeAnswer->value)->count())->toBe(0)
        ->and(collect(heldCodeAnswerTextsToDana())->contains(fn (array $text): bool => str_contains($text['body'], '$89')))->toBeFalse()
        ->and(heldCodeAnswerTextsToDana())->toHaveCount(1)
        ->and($complaintReply?->body)->toContain("I'm sorry about this.")
        ->and($draft?->gate_outcome)->toBe(GateOutcome::Hold)
        ->and($draft?->gate_reason)->toBe('label:complaint')
        ->and($draft?->body)->toBe(HELD_CODE_ANSWER_AC_SENTENCE)
        ->and($draft?->meta['held_sentences'] ?? null)->toBe([HELD_CODE_ANSWER_AC_SENTENCE])
        ->and($alert?->reason)->toBe(AlertReason::Complaint)
        ->and($alert?->urgent)->toBeTrue()
        ->and($alert?->sent_at)->not->toBeNull();
});

it('alerts at once after hours when the folder lists complaint under right_away (D-43 timing)', function () {
    heldCodeAnswerRun(HELD_CODE_ANSWER_COMPLAINT_ASK, heldCodeAnswerBlankAcScript(), '2026-10-14 02:00');

    $alert = OwnerAlert::query()->first();

    expect(OwnerAlert::query()->count())->toBe(1)
        ->and($alert?->reason)->toBe(AlertReason::Complaint)
        ->and($alert?->urgent)->toBeTrue()
        ->and($alert?->due_at?->equalTo(now()))->toBeTrue()
        ->and($alert?->sent_at)->not->toBeNull();
});

it('stores the complaint alert for the next opening when the folder takes complaint off right_away, and never drops it (D-43 timing)', function () {
    heldCodeAnswerTakeComplaintOffRightAway();

    heldCodeAnswerRun(HELD_CODE_ANSWER_COMPLAINT_ASK, heldCodeAnswerBlankAcScript(), '2026-10-14 02:00');

    $alert = OwnerAlert::query()->first();

    expect(OwnerAlert::query()->count())->toBe(1)
        ->and($alert?->reason)->toBe(AlertReason::Complaint)
        ->and($alert?->urgent)->toBeFalse()
        ->and($alert?->due_at?->equalTo(Carbon::parse('2026-10-14 08:00', 'America/Chicago')))->toBeTrue()
        ->and($alert?->sent_at)->toBeNull()
        ->and(Message::query()->where('kind', MessageKind::ComplaintHolding->value)->count())->toBe(1)
        ->and(Message::query()->where('kind', MessageKind::CodeAnswer->value)->count())->toBe(0)
        ->and(heldCodeAnswerMaybeDraft()?->body)->toBe(HELD_CODE_ANSWER_AC_SENTENCE);
});

it('alerts at once while the shop is open when the folder takes complaint off right_away (D-43 timing)', function () {
    heldCodeAnswerTakeComplaintOffRightAway();

    heldCodeAnswerRun(HELD_CODE_ANSWER_COMPLAINT_ASK, heldCodeAnswerBlankAcScript());

    $alert = OwnerAlert::query()->first();

    expect(OwnerAlert::query()->count())->toBe(1)
        ->and($alert?->reason)->toBe(AlertReason::Complaint)
        ->and($alert?->urgent)->toBeFalse()
        ->and($alert?->due_at?->equalTo(now()))->toBeTrue()
        ->and($alert?->sent_at)->not->toBeNull();
});

it('still sends the price sentence by code for a price ask with no complaint, and holds nothing (control)', function () {
    heldCodeAnswerRun('How much is a repair for my AC?', heldCodeAnswerBlankAcScript('faq_answer'));

    $answer = Message::query()->where('kind', MessageKind::CodeAnswer->value)->sole();

    expect(heldCodeAnswerTextsToDana())->toHaveCount(1)
        ->and($answer->body)->toContain(HELD_CODE_ANSWER_AC_SENTENCE)
        ->and(Message::query()->where('status', MessageStatus::Draft->value)->count())->toBe(0)
        ->and(OwnerAlert::query()->count())->toBe(0);
});

/**
 * Dana texts again a minute later and the front desk answers that burst with the given script.
 *
 * @param  array<string, mixed>  $reply
 */
function heldCodeAnswerCustomerTextsAgain(string $body, array $reply): void
{
    static $count = 0;
    $count++;

    test()->travel(1)->minutes();

    test()->post(route('webhooks.text.fake'), fakeTextPayload([
        'MessageSid' => 'SM'.str_pad((string) $count, 32, 'h', STR_PAD_LEFT),
        'Body' => $body,
    ]))->assertOk();
    scriptFrontDeskReplies($reply);

    runQueuedFrontDeskJobs();
}

/**
 * The words as Dana receives them after the first automated text: the sign-off closes them, the disclosure is left out.
 */
function heldCodeAnswerLater(string $words): string
{
    return $words."\n".app(ActiveConfig::class)->voice()['sign_off'];
}

/**
 * Dana's price question about a new unit, held as a draft with its holding reply and its one alert.
 */
function heldCodeAnswerPriceQuestionHeld(): void
{
    heldCodeAnswerRun('How much for a new unit?', ['reply' => 'Mike will text you a price for a new unit.', 'label' => 'price']);
}

/**
 * A second live offer in the folder, a file that sorts before the example one and has no end date.
 */
function heldCodeAnswerWriteSeniorOffer(): void
{
    writeIcmFile('business/offers/a-senior-discount.md', implode("\n", [
        '---',
        'type: offer',
        'name: Senior discount',
        'slug: a-senior-discount',
        'sentence: "'.HELD_CODE_ANSWER_SENIOR.'"',
        'percent: 10',
        'start_date: 2026-10-01',
        '---',
        'The business confirms the customer\'s age at the visit.',
        '',
    ]));
}

it('holds the offer sentence beside a complaint in the customer\'s text, even when the model labels the reply a plain FAQ (CR-01, D-42, D-43)', function () {
    fakeClassifier()->answerReading('complaint', 0.95, ['complaint' => 0.9]);

    heldCodeAnswerRun('Your crew tracked mud through my house. Do you have any discounts for the repair?', [
        'reply' => '',
        'label' => 'faq_answer',
        'asks_discount' => true,
    ]);

    $draft = heldCodeAnswerMaybeDraft();
    $alert = OwnerAlert::query()->first();

    expect(Message::query()->where('kind', MessageKind::CodeAnswer->value)->count())->toBe(0)
        ->and(heldCodeAnswerTextsToDana())->toHaveCount(1)
        ->and(Message::query()->where('kind', MessageKind::ComplaintHolding->value)->count())->toBe(1)
        ->and($draft?->gate_reason)->toBe('classifier:complaint')
        ->and($draft?->body)->toBe(HELD_CODE_ANSWER_FIRST_VISIT)
        ->and($draft?->meta['held_sentences'] ?? null)->toBe([HELD_CODE_ANSWER_FIRST_VISIT])
        ->and(OwnerAlert::query()->count())->toBe(1)
        ->and($alert?->reason)->toBe(AlertReason::Complaint)
        ->and($alert?->urgent)->toBeTrue()
        ->and($alert?->sent_at)->not->toBeNull();
});

it('gives a discount ask with a complaint and no live offer the complaint reply, not the discount reply, and a complaint alert (D-34)', function () {
    fakeClassifier()->answerReading('complaint', 0.95, ['complaint' => 0.9]);

    heldCodeAnswerRun('Your crew tracked mud through my house. Do you have any discounts for the repair?', [
        'reply' => '',
        'label' => 'faq_answer',
        'asks_discount' => true,
    ], '2026-09-20 10:00');

    $draft = heldCodeAnswerMaybeDraft();

    expect(Message::query()->where('kind', MessageKind::DiscountHolding->value)->count())->toBe(0)
        ->and(Message::query()->where('kind', MessageKind::ComplaintHolding->value)->count())->toBe(1)
        ->and($draft?->gate_reason)->toBe('classifier:complaint')
        ->and($draft?->body)->toBe('')
        ->and(OwnerAlert::query()->count())->toBe(1)
        ->and(OwnerAlert::query()->first()?->reason)->toBe(AlertReason::Complaint);
});

it('puts every live offer sentence in the one draft, in the order a sent text uses, and sends none (ANSR-02 adjacency)', function () {
    heldCodeAnswerWriteSeniorOffer();
    fakeClassifier()->answerReading('complaint', 0.95, ['complaint' => 0.9]);

    heldCodeAnswerRun('Your crew tracked mud through my house. Do you have any discounts for the repair?', [
        'reply' => '',
        'label' => 'faq_answer',
        'asks_discount' => true,
    ]);

    $draft = heldCodeAnswerMaybeDraft();

    expect(Message::query()->where('kind', MessageKind::CodeAnswer->value)->count())->toBe(0)
        ->and(heldCodeAnswerTextsToDana())->toHaveCount(1)
        ->and($draft?->body)->toBe(HELD_CODE_ANSWER_SENIOR.' '.HELD_CODE_ANSWER_FIRST_VISIT)
        ->and($draft?->meta['held_sentences'] ?? null)->toBe([HELD_CODE_ANSWER_SENIOR, HELD_CODE_ANSWER_FIRST_VISIT]);
});

it('holds a price sentence for a promise label on blank words, tells Dana with the holding reply, and does not force the alert right away (D-44)', function () {
    heldCodeAnswerRun('Can you fix my AC today and how much?', heldCodeAnswerBlankAcScript('promise'));

    $draft = heldCodeAnswerMaybeDraft();

    expect(Message::query()->where('kind', MessageKind::CodeAnswer->value)->count())->toBe(0)
        ->and(Message::query()->where('kind', MessageKind::HoldingReply->value)->count())->toBe(1)
        ->and($draft?->gate_reason)->toBe('label:promise')
        ->and($draft?->body)->toBe(HELD_CODE_ANSWER_AC_SENTENCE)
        ->and(OwnerAlert::query()->first()?->reason)->toBe(AlertReason::DraftWaiting);
});

it('holds a price sentence, pre-filled, when the reading is unsure whether the customer asked for a person (D-42, D-44)', function () {
    fakeClassifier()->answerReading('faq', 0.95, ['asks_for_person' => 0.5]);

    heldCodeAnswerRun('How much is a repair for my AC?', heldCodeAnswerBlankAcScript('faq_answer'));

    $draft = heldCodeAnswerMaybeDraft();

    expect(Message::query()->where('kind', MessageKind::CodeAnswer->value)->count())->toBe(0)
        ->and($draft?->gate_reason)->toBe('classifier_unsure:asks_for_person')
        ->and($draft?->body)->toBe(HELD_CODE_ANSWER_AC_SENTENCE)
        ->and(Message::query()->where('kind', MessageKind::HoldingReply->value)->count())->toBe(1);
});

it('holds a price sentence, pre-filled, when the reading failed, and the holding reply still goes out (D-42, D-44)', function () {
    fakeClassifier()->failReadingWith('Jev is down');

    heldCodeAnswerRun('How much is a repair for my AC?', heldCodeAnswerBlankAcScript('faq_answer'));

    $draft = heldCodeAnswerMaybeDraft();

    expect(Message::query()->where('kind', MessageKind::CodeAnswer->value)->count())->toBe(0)
        ->and($draft?->gate_reason)->toBe('classifier_failed')
        ->and($draft?->body)->toBe(HELD_CODE_ANSWER_AC_SENTENCE)
        ->and(Message::query()->where('kind', MessageKind::HoldingReply->value)->count())->toBe(1);
});

it('treats a reading complaint answer of exactly 0.15 as no complaint and 0.16 as one (ANSR-01 boundary)', function (float $answer, bool $held) {
    fakeClassifier()->answerReading('faq', 0.95, ['complaint' => $answer]);

    heldCodeAnswerRun('How much is a repair for my AC?', heldCodeAnswerBlankAcScript('faq_answer'));

    if (! $held) {
        expect(Message::query()->where('kind', MessageKind::CodeAnswer->value)->count())->toBe(1)
            ->and(Message::query()->where('status', MessageStatus::Draft->value)->count())->toBe(0);

        return;
    }

    expect(Message::query()->where('kind', MessageKind::CodeAnswer->value)->count())->toBe(0)
        ->and(heldCodeAnswerMaybeDraft()?->gate_reason)->toBe('classifier:complaint')
        ->and(heldCodeAnswerMaybeDraft()?->body)->toBe(HELD_CODE_ANSWER_AC_SENTENCE);
})->with([
    'exactly complaint_max_yes' => [0.15, false],
    'just above complaint_max_yes' => [0.16, true],
]);

it('pre-fills the sentence byte for byte as code renders it, the owner\'s lead included (ANSR-01 precision)', function () {
    heldCodeAnswerRun('How much is the diagnostic for my AC?', heldCodeAnswerBlankAcScript('faq_answer'));

    expect(Message::query()->where('kind', MessageKind::CodeAnswer->value)->count())->toBe(1);

    fakeClassifier()->answerReading('complaint', 0.95, ['complaint' => 0.9]);

    heldCodeAnswerCustomerTextsAgain('That tech was rude. What was the diagnostic price again?', ['reply' => '', 'price_services' => ['ac-repair']]);

    $lead = app(FixedReplies::class)->priceLead('same', now());
    $draft = heldCodeAnswerMaybeDraft();

    expect(Message::query()->where('kind', MessageKind::CodeAnswer->value)->count())->toBe(1)
        ->and($draft?->body)->toBe(trim((string) $lead).' '.HELD_CODE_ANSWER_AC_SENTENCE)
        ->and($draft?->meta['held_sentences'] ?? null)->toBe([trim((string) $lead).' '.HELD_CODE_ANSWER_AC_SENTENCE]);
});

it('sends only the complaint reply when a complaint comes with easy words and a price ask, and holds the words then the sentence (D-36, D-43)', function () {
    fakeClassifier()->answerReading('complaint', 0.95, ['complaint' => 0.9]);

    heldCodeAnswerRun('Your tech was rude. How much is the AC diagnostic, and do you work on Carrier units?', [
        'reply' => HELD_CODE_ANSWER_WORDS,
        'label' => 'faq_answer',
        'price_services' => ['ac-repair'],
    ]);

    $draft = heldCodeAnswerMaybeDraft();

    expect(heldCodeAnswerTextsToDana())->toHaveCount(1)
        ->and(collect(heldCodeAnswerTextsToDana())->contains(fn (array $text): bool => str_contains($text['body'], '$89')))->toBeFalse()
        ->and(Message::query()->where('kind', MessageKind::ComplaintHolding->value)->count())->toBe(1)
        ->and($draft?->body)->toBe(HELD_CODE_ANSWER_WORDS."\n\n".HELD_CODE_ANSWER_AC_SENTENCE)
        ->and($draft?->meta['held_sentences'] ?? null)->toBe([HELD_CODE_ANSWER_AC_SENTENCE])
        ->and(OwnerAlert::query()->first()?->reason)->toBe(AlertReason::Complaint)
        ->and(OwnerAlert::query()->first()?->urgent)->toBeTrue();
});

it('sends no sentence beside a complaint while a draft waits, withdraws the waiting draft, and keeps its words in the fresh draft (D-43, D-44)', function () {
    heldCodeAnswerPriceQuestionHeld();
    $waiting = heldCodeAnswerTheDraft();

    fakeClassifier()->answerReading('complaint', 0.95, ['complaint' => 0.9]);

    heldCodeAnswerCustomerTextsAgain('Your tech never showed up. How much is the AC diagnostic anyway?', [
        'reply' => '',
        'price_services' => ['ac-repair'],
        'touches_waiting' => true,
    ]);

    $live = heldCodeAnswerMaybeDraft();

    expect(Message::query()->where('kind', MessageKind::CodeAnswer->value)->count())->toBe(0)
        ->and(collect(heldCodeAnswerTextsToDana())->contains(fn (array $text): bool => str_contains($text['body'], '$89')))->toBeFalse()
        ->and(Message::query()->where('kind', MessageKind::ComplaintHolding->value)->count())->toBe(1)
        ->and($waiting->fresh()->status)->toBe(MessageStatus::Withdrawn)
        ->and($waiting->fresh()->resolution?->value)->toBe('replaced')
        ->and($live?->id)->not->toBe($waiting->id)
        ->and($live?->body)->toBe('Mike will text you a price for a new unit.'."\n\n".HELD_CODE_ANSWER_AC_SENTENCE)
        ->and(OwnerAlert::query()->where('reason', AlertReason::Complaint->value)->count())->toBe(1);
});

it('holds the sentence, pre-filled, when Jev finds a danger in the model\'s words, and sends only the safety line (D-44)', function () {
    fakeClassifier()->answer('faq', 0.95, ['life_safety' => 0.9]);

    heldCodeAnswerRun('How much is the AC diagnostic, and do you work on Carrier units?', [
        'reply' => HELD_CODE_ANSWER_WORDS,
        'label' => 'faq_answer',
        'price_services' => ['ac-repair'],
    ]);

    $draft = heldCodeAnswerMaybeDraft();

    expect(Message::query()->where('kind', MessageKind::LifeSafety->value)->count())->toBe(1)
        ->and(Message::query()->where('kind', MessageKind::CodeAnswer->value)->count())->toBe(0)
        ->and(heldCodeAnswerTextsToDana())->toHaveCount(1)
        ->and($draft?->body)->toBe(HELD_CODE_ANSWER_WORDS."\n\n".HELD_CODE_ANSWER_AC_SENTENCE)
        ->and($draft?->meta['held_sentences'] ?? null)->toBe([HELD_CODE_ANSWER_AC_SENTENCE]);
});

it('lets easy words that passed their own checks ride with the complaint line while no price sentence goes out (D-36)', function () {
    heldCodeAnswerPriceQuestionHeld();

    fakeClassifier()->answerReading('faq', 0.95, ['complaint' => 0.01]);

    heldCodeAnswerCustomerTextsAgain('Your tech was rude. Do you do heat pumps? And what is the AC diagnostic?', [
        'reply' => 'Sorry about the tech. Mike will text you.',
        'label' => 'complaint',
        'touches_waiting' => true,
        'easy_reply' => 'Yes, we service heat pumps.',
        'easy_label' => 'faq_answer',
        'price_services' => ['ac-repair'],
    ]);

    $toDana = heldCodeAnswerTextsToDana();
    $complaintReply = app(FixedReplies::class)->complaintReply(now());

    expect($toDana)->toHaveCount(2)
        ->and($toDana[1]['body'])->toBe(heldCodeAnswerLater($complaintReply.' Yes, we service heat pumps.'))
        ->and($toDana[1]['body'])->not->toContain('$89')
        ->and(Message::query()->where('kind', MessageKind::ComplaintHolding->value)->count())->toBe(1)
        ->and(heldCodeAnswerMaybeDraft()?->body)->toBe('Sorry about the tech. Mike will text you.'."\n\n".HELD_CODE_ANSWER_AC_SENTENCE);
});

it('holds the sentence when only the words check finds the complaint, with a clean reading of the customer\'s text (D-43)', function () {
    fakeClassifier()->answer('complaint', 0.95, ['complaint' => 0.9]);

    heldCodeAnswerRun('How much is the AC diagnostic, and do you work on Carrier units?', [
        'reply' => HELD_CODE_ANSWER_WORDS,
        'label' => 'faq_answer',
        'price_services' => ['ac-repair'],
    ]);

    $draft = heldCodeAnswerMaybeDraft();

    expect(Message::query()->where('kind', MessageKind::CodeAnswer->value)->count())->toBe(0)
        ->and(heldCodeAnswerTextsToDana())->toHaveCount(1)
        ->and(Message::query()->where('kind', MessageKind::ComplaintHolding->value)->count())->toBe(1)
        ->and($draft?->gate_reason)->toBe('classifier:complaint')
        ->and($draft?->body)->toBe(HELD_CODE_ANSWER_WORDS."\n\n".HELD_CODE_ANSWER_AC_SENTENCE)
        ->and(OwnerAlert::query()->where('reason', AlertReason::Complaint->value)->count())->toBe(1);
});

it('does not close a waiting draft as answered when the words check finds a complaint beside the sentence that would have answered it (D-43)', function () {
    heldCodeAnswerRun('How much is a repair?', ['reply' => 'Mike will text you the repair price.', 'label' => 'price']);
    $waiting = heldCodeAnswerTheDraft();

    fakeClassifier()->answer('complaint', 0.95, ['complaint' => 0.9]);

    heldCodeAnswerCustomerTextsAgain('It is the AC, and your tech was rude.', [
        'reply' => 'Sorry about that.',
        'label' => 'faq_answer',
        'price_services' => ['ac-repair'],
        'touches_waiting' => true,
    ]);

    $live = heldCodeAnswerMaybeDraft();

    expect(Message::query()->where('kind', MessageKind::CodeAnswer->value)->count())->toBe(0)
        ->and($waiting->fresh()->resolution?->value)->toBe('replaced')
        ->and($live?->body)->toBe('Sorry about that.'."\n\n".HELD_CODE_ANSWER_AC_SENTENCE)
        ->and(Message::query()->where('kind', MessageKind::ComplaintHolding->value)->count())->toBe(1);
});

/**
 * Take the replacement system's default sentence out of the folder, so a "Not sure" size has no sentence on file, and
 * hold Dana's price question as a draft that waits on the replacement system's price.
 */
function heldCodeAnswerReplacementPriceHeld(): Message
{
    breakFile('business/services/system-replacement.md', fn (string $text): string => (string) preg_replace('/^price_default:\n(?:  .*\n)+/m', '', $text));

    heldCodeAnswerRun('How much for a new system? Not sure what size I have.', [
        'reply' => HELD_CODE_ANSWER_WAITING_WORDS,
        'label' => 'price',
        'service_slug' => 'system-replacement',
        'price_services' => ['system-replacement'],
        'intake_answers' => [['question_key' => 'unit_size', 'option' => 'Not sure', 'raw_text' => 'Not sure what size I have']],
    ]);

    return heldCodeAnswerTheDraft();
}

/**
 * The model's script for the later text that says the size is large, so the replacement system's sentence closes the wait.
 *
 * @return array<string, mixed>
 */
function heldCodeAnswerLargeScript(): array
{
    return [
        'reply' => '',
        'service_slug' => 'system-replacement',
        'price_services' => ['system-replacement'],
        'intake_answers' => [['question_key' => 'unit_size', 'option' => 'Large (3 tons and up)', 'raw_text' => "I checked, it's about 4 tons"]],
        'touches_waiting' => true,
    ];
}

it('keeps the owner\'s sentence in a send_refused draft when a code-only send is refused (D-44, accepted 02.1-08 call)', function () {
    config(['gate.agent.lock_wait_seconds' => 0]);

    heldCodeAnswerCustomerTexts('How much is a repair for my AC?');
    scriptFrontDeskReplies(heldCodeAnswerBlankAcScript('faq_answer'));

    $lock = Cache::lock('reply:'.Conversation::query()->sole()->id, 30);
    $lock->get();

    try {
        runQueuedFrontDeskJobs();
    } finally {
        $lock->release();
    }

    $draft = heldCodeAnswerMaybeDraft();

    expect(Message::query()->where('kind', MessageKind::CodeAnswer->value)->count())->toBe(0)
        ->and(collect(heldCodeAnswerTextsToDana())->contains(fn (array $text): bool => str_contains($text['body'], '$89')))->toBeFalse()
        ->and($draft?->gate_reason)->toBe('send_refused')
        ->and($draft?->body)->toBe(HELD_CODE_ANSWER_AC_SENTENCE)
        ->and($draft?->meta['held_sentences'] ?? null)->toBe([HELD_CODE_ANSWER_AC_SENTENCE])
        ->and(OwnerAlert::query()->count())->toBe(1);
});

it('never replaces the waiting draft with an empty one when the closing send is refused, and keeps its words with the sentence (D-47)', function () {
    config(['gate.agent.lock_wait_seconds' => 0]);

    $waiting = heldCodeAnswerReplacementPriceHeld();

    expect($waiting->gate_reason)->toBe('label:price')
        ->and($waiting->body)->toBe(HELD_CODE_ANSWER_WAITING_WORDS)
        ->and($waiting->meta['price_open'])->toBe(['system-replacement']);

    $lock = Cache::lock('reply:'.Conversation::query()->sole()->id, 600);
    $lock->get();

    try {
        heldCodeAnswerCustomerTextsAgain("I checked, it's about 4 tons", heldCodeAnswerLargeScript());
    } finally {
        $lock->release();
    }

    $live = heldCodeAnswerMaybeDraft();

    expect(Message::query()->where('kind', MessageKind::CodeAnswer->value)->count())->toBe(0)
        ->and($waiting->fresh()->resolution?->value)->toBe('replaced')
        ->and($live?->id)->not->toBe($waiting->id)
        ->and($live?->gate_reason)->toBe('send_refused')
        ->and($live?->body)->toBe(HELD_CODE_ANSWER_WAITING_WORDS."\n\n".HELD_CODE_ANSWER_LARGE_SENTENCE)
        ->and($live?->body)->toContain('a replacement system typically runs $5,000 to $10,000 installed.')
        ->and($live?->meta['held_sentences'] ?? null)->toBe([HELD_CODE_ANSWER_LARGE_SENTENCE]);
});

it('leaves the waiting draft live when the closing send fails in the channel, as before (D-21)', function () {
    $waiting = heldCodeAnswerReplacementPriceHeld();

    $this->mock(TextChannel::class, function (MockInterface $channel) {
        $channel->shouldReceive('name')->andReturn('fake');
        $channel->shouldReceive('send')->andThrow(new RuntimeException('carrier down'));
    });

    heldCodeAnswerCustomerTextsAgain("I checked, it's about 4 tons", heldCodeAnswerLargeScript());

    $live = heldCodeAnswerMaybeDraft();

    expect($live?->id)->toBe($waiting->id)
        ->and($live?->resolution)->toBeNull()
        ->and($live?->body)->toBe(HELD_CODE_ANSWER_WAITING_WORDS)
        ->and(Message::query()->where('kind', MessageKind::CodeAnswer->value)->sole()->status)->toBe(MessageStatus::Failed);
});
