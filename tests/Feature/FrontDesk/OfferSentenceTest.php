<?php

use App\Ai\AgentReply;
use App\Enums\GateOutcome;
use App\Enums\MessageAuthor;
use App\Enums\MessageKind;
use App\Enums\MessageStatus;
use App\Models\Message;
use App\Models\OwnerAlert;
use App\Models\User;
use Illuminate\Support\Carbon;

const OFFER_SENTENCE_OWNER_PHONE = '+12025550199';

const OFFER_SENTENCE_CUSTOMER_PHONE = '+12025550123';

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
