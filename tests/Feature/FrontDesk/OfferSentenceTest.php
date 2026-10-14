<?php

use App\Ai\AgentReply;
use App\Enums\ActivityType;
use App\Enums\AlertReason;
use App\Enums\GateOutcome;
use App\Enums\MessageAuthor;
use App\Enums\MessageKind;
use App\Enums\MessageStatus;
use App\Models\Contact;
use App\Models\Message;
use App\Models\OwnerAlert;
use App\Models\User;
use Illuminate\Support\Carbon;

const OFFER_SENTENCE_OWNER_PHONE = '+12025550199';

const OFFER_SENTENCE_CUSTOMER_PHONE = '+12025550123';

const OFFER_SENTENCE_SENIOR = "If you're 65 or older, we take 10% off any repair.";

const OFFER_SENTENCE_AC = 'AC repair starts with an $89 diagnostic visit, and we take the $89 off if you go ahead with the repair that visit.';

const OFFER_SENTENCE_DISCOUNT_LINE = "We like to take care of our customers. I'll make sure Mike knows you asked about a discount and he'll pick up the thread.";

const OFFER_SENTENCE_PARTIAL_LINE = "I've passed your other question to Mike, and he'll follow up on it.";

const OFFER_SENTENCE_FIRST_VISIT = "If you're a first-time customer, we're running a promotion that takes \$50 off your first visit.";

/**
 * Dana's text at the given moment in the shop's zone, the owner with a phone, and the live example folder.
 */
function offerSentenceCustomerTexts(string $body, string $at = '2026-10-13 10:00'): void
{
    test()->travelTo(Carbon::parse($at, 'America/Chicago'));

    User::factory()->withPhone(OFFER_SENTENCE_OWNER_PHONE)->create();

    frontDeskCustomerTexts($body);
}

/**
 * Dana's text, one scripted model answer and the queued run.
 *
 * @param  array<string, mixed>  $reply
 */
function offerSentenceRun(string $body, array $reply, string $at = '2026-10-13 10:00'): void
{
    offerSentenceCustomerTexts($body, $at);
    scriptFrontDeskReplies($reply);
    runQueuedFrontDeskJobs();
}

/**
 * The texts that reached Dana's phone, leaving out the alerts to the owner.
 *
 * @return list<array{to: string, body: string, provider_message_id: string}>
 */
function offerSentenceTextsToDana(): array
{
    return array_values(array_filter(
        textsSentToCustomers(),
        fn (array $text): bool => $text['to'] === OFFER_SENTENCE_CUSTOMER_PHONE,
    ));
}

it('sends every live offer in the owner\'s words by code, without asking Jev (D-13)', function () {
    offerSentenceRun('Do you have any discounts?', [
        'reply' => '',
        'label' => 'faq_answer',
        'asks_discount' => true,
    ]);

    $texts = offerSentenceTextsToDana();
    $message = Message::query()->where('kind', MessageKind::CodeAnswer->value)->sole();

    expect($texts)->toHaveCount(1)
        ->and($texts[0]['body'])->toBe(composedFirstReply(OFFER_SENTENCE_FIRST_VISIT))
        ->and($message->author)->toBe(MessageAuthor::Agent)
        ->and($message->status)->toBe(MessageStatus::Sent)
        ->and($message->gate_outcome)->toBe(GateOutcome::Send)
        ->and($message->gate_reason)->toBe('fixed_reply')
        ->and($message->meta['parts'])->toBe(['code_sentences'])
        ->and($message->meta['offers'])->toBe(['first-visit-promotion'])
        ->and(fakeClassifier()->calls())->toBe([])
        ->and(Message::query()->where('status', MessageStatus::Draft->value)->count())->toBe(0)
        ->and(OwnerAlert::query()->count())->toBe(0);
});

it('still rejects a blank reply from the model when it flags no price and no discount ask (D-13)', function () {
    expect(fn () => AgentReply::fromStructured(['reply' => '', 'label' => 'faq_answer', 'wants_person' => false, 'price_services' => [], 'asks_discount' => false]))
        ->toThrow(UnexpectedValueException::class)
        ->and(fn () => AgentReply::fromStructured(['reply' => '  ', 'label' => 'faq_answer', 'wants_person' => false]))
        ->toThrow(UnexpectedValueException::class);
});

it('accepts a blank reply when the model flags a discount ask (D-13)', function () {
    $reply = AgentReply::fromStructured(['reply' => '', 'label' => 'faq_answer', 'wants_person' => false, 'asks_discount' => true]);

    expect($reply->reply)->toBe('')
        ->and($reply->asksDiscount)->toBeTrue();
});

/**
 * The line the owner reads on the held draft: what the timeline and the Needs you list say about why it waits.
 */
function offerSentenceHeldReason(Message $draft): string
{
    return Contact::query()->sole()->activities()->where('type', ActivityType::DraftHeld->value)->where('subject_id', $draft->id)->sole()->summary;
}

/**
 * A second live offer in the folder, a file that sorts before the example one and has no end date.
 */
function offerSentenceWriteSeniorOffer(): void
{
    writeIcmFile('business/offers/a-senior-discount.md', implode("\n", [
        '---',
        'type: offer',
        'name: Senior discount',
        'slug: a-senior-discount',
        'sentence: "'.OFFER_SENTENCE_SENIOR.'"',
        'percent: 10',
        'start_date: 2026-10-01',
        '---',
        'The business confirms the customer\'s age at the visit.',
        '',
    ]));
}

it('sends the offer on its start day and its end day, and not the day before or after, in the shop\'s zone (D-13, T-02.1-25)', function (string $at, bool $live) {
    offerSentenceRun('Do you have any discounts?', ['reply' => '', 'asks_discount' => true], $at);

    if ($live) {
        expect(offerSentenceTextsToDana())->toHaveCount(1)
            ->and(offerSentenceTextsToDana()[0]['body'])->toBe(composedFirstReply(OFFER_SENTENCE_FIRST_VISIT))
            ->and(Message::query()->where('status', MessageStatus::Draft->value)->count())->toBe(0);

        return;
    }

    $draft = Message::query()->where('status', MessageStatus::Draft->value)->sole();

    expect($draft->body)->toBe('')
        ->and($draft->gate_reason)->toBe('discount:no_live_offer')
        ->and(Message::query()->where('kind', MessageKind::CodeAnswer->value)->count())->toBe(0)
        ->and(OwnerAlert::query()->sole()->reason)->toBe(AlertReason::DraftWaiting);
})->with([
    'the start day at 00:05' => ['2026-10-01 00:05', true],
    'the day before the start at 23:55' => ['2026-09-30 23:55', false],
    'the end day at 23:30, already the next day in UTC' => ['2026-12-31 23:30', true],
    'the day after the end at 00:30' => ['2027-01-01 00:30', false],
]);

it('sends several live offers in one text, in folder path order (D-13)', function () {
    offerSentenceWriteSeniorOffer();

    offerSentenceRun('Do you have any discounts?', ['reply' => '', 'asks_discount' => true]);

    $message = Message::query()->where('kind', MessageKind::CodeAnswer->value)->sole();

    expect(offerSentenceTextsToDana())->toHaveCount(1)
        ->and(offerSentenceTextsToDana()[0]['body'])->toBe(composedFirstReply(OFFER_SENTENCE_SENIOR.' '.OFFER_SENTENCE_FIRST_VISIT))
        ->and($message->meta['offers'])->toBe(['a-senior-discount', 'first-visit-promotion']);
});

it('holds a discount question for the owner when no offer is running, with the reason in the owner\'s words (D-14, D-40)', function () {
    offerSentenceRun('Do you have any discounts?', ['reply' => '', 'asks_discount' => true], '2027-02-01 10:00');

    $draft = Message::query()->where('status', MessageStatus::Draft->value)->sole();

    expect($draft->body)->toBe('')
        ->and($draft->gate_reason)->toBe('discount:no_live_offer')
        ->and($draft->gate['checks']['classifier']['ran'])->toBeFalse()
        ->and(offerSentenceHeldReason($draft))->toBe('Held: no offer running today')
        ->and(OwnerAlert::query()->sole()->reason)->toBe(AlertReason::DraftWaiting)
        ->and(Message::query()->where('kind', MessageKind::CodeAnswer->value)->count())->toBe(0)
        ->and(fakeClassifier()->calls())->toBe([])
        ->and(offerSentenceTextsToDana())->toHaveCount(1)
        ->and(offerSentenceTextsToDana()[0]['body'])->toBe(composedFirstReply(OFFER_SENTENCE_DISCOUNT_LINE))
        ->and(Message::query()->where('kind', MessageKind::DiscountHolding->value)->count())->toBe(1)
        ->and(Message::query()->where('kind', MessageKind::HoldingReply->value)->count())->toBe(0);
});

it('still sends the holding reply when the folder has no discount reply (D-14)', function () {
    breakFile('rules/replies.md', fn (string $contents): string => (string) preg_replace('/^discount_reply:.*\n/m', '', $contents));

    offerSentenceRun('Do you have any discounts?', ['reply' => '', 'asks_discount' => true], '2027-02-01 10:00');

    expect(Message::query()->where('kind', MessageKind::HoldingReply->value)->count())->toBe(1)
        ->and(offerSentenceTextsToDana())->toHaveCount(1);
});

it('sends the offer sentence and then the model\'s words that passed, in one text (D-07, D-13)', function () {
    $words = 'Yes, we service heat pumps.';

    offerSentenceRun('Do you have any discounts, and do you service heat pumps?', [
        'reply' => $words,
        'label' => 'faq_answer',
        'asks_discount' => true,
    ]);

    $message = Message::query()->where('direction', 'out')->where('author', MessageAuthor::Agent->value)->sole();

    expect(offerSentenceTextsToDana())->toHaveCount(1)
        ->and(offerSentenceTextsToDana()[0]['body'])->toBe(composedFirstReply(OFFER_SENTENCE_FIRST_VISIT.' '.$words))
        ->and($message->kind)->toBe(MessageKind::AgentReply)
        ->and($message->meta['parts'])->toBe(['code_sentences', 'model_words'])
        ->and($message->meta['offers'])->toBe(['first-visit-promotion'])
        ->and(fakeClassifier()->calls())->toHaveCount(1)
        ->and(fakeClassifier()->calls()[0]['draft_reply'])->toBe($words);
});

it('sends the offer with the partial holding line when the model\'s words hold, and drafts the words (D-07, D-13)', function () {
    $words = 'Yes, we service heat pumps.';

    offerSentenceCustomerTexts('Do you have any discounts, and do you service heat pumps?');
    scriptFrontDeskReplies(['reply' => $words, 'label' => 'faq_answer', 'asks_discount' => true]);
    fakeClassifier()->answer('price_question', 0.95);
    runQueuedFrontDeskJobs();

    $draft = Message::query()->where('status', MessageStatus::Draft->value)->sole();
    $partial = Message::query()->where('kind', MessageKind::PartialHolding->value)->sole();

    expect(offerSentenceTextsToDana())->toHaveCount(1)
        ->and(offerSentenceTextsToDana()[0]['body'])->toBe(composedFirstReply(OFFER_SENTENCE_FIRST_VISIT.' '.OFFER_SENTENCE_PARTIAL_LINE))
        ->and($partial->meta['offers'])->toBe(['first-visit-promotion'])
        ->and($draft->body)->toBe($words)
        ->and(Message::query()->where('kind', MessageKind::HoldingReply->value)->count())->toBe(0);
});

it('puts the price sentence first and the offer sentence after it in one text (D-07, D-13)', function () {
    offerSentenceRun('How much is the diagnostic for my AC, and any discounts?', [
        'reply' => '',
        'price_services' => ['ac-repair'],
        'asks_discount' => true,
    ]);

    $message = Message::query()->where('kind', MessageKind::CodeAnswer->value)->sole();

    expect(offerSentenceTextsToDana())->toHaveCount(1)
        ->and(offerSentenceTextsToDana()[0]['body'])->toBe(composedFirstReply(OFFER_SENTENCE_AC.' '.OFFER_SENTENCE_FIRST_VISIT))
        ->and($message->meta['price_sentences'])->toHaveCount(1)
        ->and($message->meta['offers'])->toBe(['first-visit-promotion']);
});

it('sends the price sentence with the partial holding line when no offer is running (D-07, D-14)', function () {
    offerSentenceRun('How much is the diagnostic for my AC, and any discounts?', [
        'reply' => '',
        'price_services' => ['ac-repair'],
        'asks_discount' => true,
    ], '2027-02-01 10:00');

    $draft = Message::query()->where('status', MessageStatus::Draft->value)->sole();

    expect($draft->body)->toBe('')
        ->and($draft->gate_reason)->toBe('discount:no_live_offer')
        ->and(offerSentenceTextsToDana())->toHaveCount(1)
        ->and(offerSentenceTextsToDana()[0]['body'])->toBe(composedFirstReply(OFFER_SENTENCE_AC.' '.OFFER_SENTENCE_PARTIAL_LINE))
        ->and(Message::query()->where('kind', MessageKind::HoldingReply->value)->count())->toBe(0);
});

it('holds the model\'s words for the owner when no offer is running, and sends none of them (D-14)', function () {
    offerSentenceRun('Do you have any discounts, and do you service heat pumps?', [
        'reply' => 'Yes, we service heat pumps.',
        'label' => 'faq_answer',
        'asks_discount' => true,
    ], '2027-02-01 10:00');

    $draft = Message::query()->where('status', MessageStatus::Draft->value)->sole();

    expect($draft->body)->toBe('Yes, we service heat pumps.')
        ->and($draft->gate_reason)->toBe('discount:no_live_offer')
        ->and(implode(' ', array_column(offerSentenceTextsToDana(), 'body')))->not->toContain('heat pumps')
        ->and(Message::query()->where('kind', MessageKind::DiscountHolding->value)->count())->toBe(1);
});

it('names the price as the reason when both a price and an offer cannot be answered (flagged assumption)', function () {
    offerSentenceRun('How much is a furnace tune-up, and any discounts?', [
        'reply' => '',
        'price_services' => ['furnace-tune-up'],
        'asks_discount' => true,
    ], '2027-02-01 10:00');

    $draft = Message::query()->where('status', MessageStatus::Draft->value)->sole();

    expect($draft->gate_reason)->toBe('price:no_sentence')
        ->and($draft->gate['reason']['plain'])->toBe('Held: no price on file for Furnace tune-up')
        ->and(Message::query()->where('kind', MessageKind::HoldingReply->value)->count())->toBe(1);
});
