<?php

use App\Enums\AlertReason;
use App\Enums\GateOutcome;
use App\Enums\MessageKind;
use App\Enums\MessageStatus;
use App\Models\Message;
use App\Models\OwnerAlert;
use App\Models\User;
use Illuminate\Support\Carbon;

const HELD_CODE_ANSWER_OWNER_PHONE = '+12025550199';

const HELD_CODE_ANSWER_CUSTOMER_PHONE = '+12025550123';

const HELD_CODE_ANSWER_AC_SENTENCE = 'AC repair starts with an $89 diagnostic visit, and we take the $89 off if you go ahead with the repair that visit.';

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
