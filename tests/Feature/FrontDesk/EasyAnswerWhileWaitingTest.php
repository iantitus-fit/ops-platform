<?php

use App\Enums\DraftResolution;
use App\Enums\GateOutcome;
use App\Enums\MessageKind;
use App\Enums\MessageStatus;
use App\Icm\ActiveConfig;
use App\Models\Message;
use App\Models\OwnerAlert;
use App\Models\User;
use Illuminate\Support\Carbon;

const EASY_ANSWER_OWNER_PHONE = '+12025550199';

const EASY_ANSWER_CUSTOMER_PHONE = '+12025550123';

const EASY_ANSWER_ADDED_INFO_LINE = "Got it, I've passed that along to Mike with your question.";

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
 * Dana texts again a minute later and the front desk answers that burst with the given script.
 *
 * @param  array<string, mixed>  $reply
 */
function easyAnswerCustomerTexts(string $body, array $reply): void
{
    static $count = 0;
    $count++;

    test()->travel(1)->minutes();

    test()->post(route('webhooks.text.fake'), fakeTextPayload([
        'MessageSid' => 'SM'.str_pad((string) $count, 32, 'e', STR_PAD_LEFT),
        'Body' => $body,
    ]))->assertOk();
    scriptFrontDeskReplies($reply);

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
