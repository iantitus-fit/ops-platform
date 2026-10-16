<?php

use App\Ai\Agents\FrontDeskAgent;
use App\Enums\AlertReason;
use App\Enums\DraftResolution;
use App\Enums\GateOutcome;
use App\Enums\MessageKind;
use App\Enums\MessageStatus;
use App\Icm\ActiveConfig;
use App\Models\Message;
use App\Models\OwnerAlert;
use App\Models\User;
use Illuminate\Support\Carbon;

const COVER_BOTH_OWNER_PHONE = '+12025550199';

const COVER_BOTH_CUSTOMER_PHONE = '+12025550123';

const COVER_BOTH_COMPLAINT_LINE = "I'm sorry about this. I've passed your message straight to Mike, and he'll get back to you today if possible, or after we open at 8 am tomorrow.";

const COVER_BOTH_FIRST_WORDS = "I'm sorry to hear that. Mike will call you about it.";

const COVER_BOTH_SECOND_WORDS = "I'm sorry about the last visit. Mike will call you about it and text you the price for a new unit.";

/**
 * Tuesday 2026-10-13 at 10:00 in the shop's zone, the owner with a phone, and Dana's price question held as a draft
 * with its holding reply and its one alert.
 */
function coverBothPriceQuestionHeld(): void
{
    test()->travelTo(Carbon::parse('2026-10-13 10:00', 'America/Chicago'));

    User::factory()->withPhone(COVER_BOTH_OWNER_PHONE)->create();

    frontDeskCustomerTexts('How much for a new unit?');
    scriptFrontDeskReplies(['reply' => 'Mike will text you a price for a new unit.', 'label' => 'price']);

    runQueuedFrontDeskJobs();
}

/**
 * Dana texts again a minute later; the model is scripted with the given replies in order, and the run happens.
 *
 * @param  array<string, mixed>  ...$replies
 */
function coverBothCustomerTexts(string $body, array ...$replies): void
{
    static $count = 0;
    $count++;

    test()->travel(1)->minutes();

    test()->post(route('webhooks.text.fake'), fakeTextPayload([
        'MessageSid' => 'SM'.str_pad((string) $count, 32, 'c', STR_PAD_LEFT),
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
function coverBothTextsToDana(): array
{
    return array_values(array_filter(
        textsSentToCustomers(),
        fn (array $text): bool => $text['to'] === COVER_BOTH_CUSTOMER_PHONE,
    ));
}

/**
 * The words as Dana receives them after the first automated text: the sign-off closes them, the disclosure is left out.
 */
function coverBothLater(string $words): string
{
    return $words."\n".app(ActiveConfig::class)->voice()['sign_off'];
}

/**
 * The first reply of the new text: the model marked it as not touching the waiting question, but it is a complaint,
 * so the send rule holds it.
 *
 * @return array<string, mixed>
 */
function coverBothFirstReply(): array
{
    return ['reply' => COVER_BOTH_FIRST_WORDS, 'label' => 'complaint', 'touches_waiting' => false];
}

/**
 * The model's second answer, written to cover both questions.
 *
 * @return array<string, mixed>
 */
function coverBothSecondReply(): array
{
    return ['reply' => COVER_BOTH_SECOND_WORDS, 'label' => 'complaint'];
}

it('asks once more for one draft that covers both questions and lets it replace the waiting draft (D-35)', function () {
    coverBothPriceQuestionHeld();

    $waiting = Message::query()->where('status', MessageStatus::Draft->value)->sole();

    coverBothCustomerTexts('Also, I want to complain about the last visit.', coverBothFirstReply(), coverBothSecondReply());

    $live = Message::query()->where('status', MessageStatus::Draft->value)->sole();

    // The fake keeps every prompt of the test: one for the waiting question, then two for this text.
    FrontDeskAgent::assertPromptedTimes(3);
    FrontDeskAgent::assertPrompted(fn ($prompt): bool => str_contains((string) $prompt->agent->instructions(), '## Write one reply that covers both')
        && str_contains((string) $prompt->agent->instructions(), '## Already with the owner'));

    FrontDeskAgent::assertPrompted(fn ($prompt): bool => ! str_contains((string) $prompt->agent->instructions(), '## Write one reply that covers both'));

    expect($waiting->fresh()->status)->toBe(MessageStatus::Withdrawn)
        ->and($waiting->fresh()->resolution)->toBe(DraftResolution::Replaced)
        ->and($live->id)->not->toBe($waiting->id)
        ->and($live->body)->toBe(COVER_BOTH_SECOND_WORDS)
        ->and($live->gate_outcome)->toBe(GateOutcome::Hold)
        ->and($live->gate_reason)->toBe('label:complaint')
        ->and($live->meta['first_reply'])->toBe(['reply' => COVER_BOTH_FIRST_WORDS, 'gate_reason' => 'label:complaint'])
        ->and($live->meta['replaces'])->toBe([$waiting->id]);
});

it('tells Dana with the complaint reply and raises the complaint alert (D-34, D-36)', function () {
    coverBothPriceQuestionHeld();

    coverBothCustomerTexts('Also, I want to complain about the last visit.', coverBothFirstReply(), coverBothSecondReply());

    $complaintLine = Message::query()->where('kind', MessageKind::ComplaintHolding->value)->sole();

    expect(coverBothTextsToDana())->toHaveCount(2)
        ->and(coverBothTextsToDana()[1]['body'])->toBe(coverBothLater(COVER_BOTH_COMPLAINT_LINE))
        ->and($complaintLine->status)->toBe(MessageStatus::Sent)
        ->and(OwnerAlert::query()->where('reason', AlertReason::DraftWaiting->value)->count())->toBe(1)
        ->and(OwnerAlert::query()->where('reason', AlertReason::Complaint->value)->count())->toBe(1)
        ->and(Message::query()->where('body', 'like', '%last visit%')->where('status', MessageStatus::Sent->value)->count())->toBe(0);
});

it('still sends a reply that passes beside the waiting draft with one agent call (D-18)', function () {
    coverBothPriceQuestionHeld();

    $waiting = Message::query()->where('status', MessageStatus::Draft->value)->sole();

    coverBothCustomerTexts('Do you service heat pumps?', [
        'reply' => 'Yes, we service heat pumps.',
        'label' => 'faq_answer',
        'touches_waiting' => false,
    ]);

    // One prompt for the waiting question, one for this text.
    FrontDeskAgent::assertPromptedTimes(2);

    expect(coverBothTextsToDana())->toHaveCount(2)
        ->and(coverBothTextsToDana()[1]['body'])->toBe(coverBothLater('Yes, we service heat pumps.'))
        ->and(Message::query()->where('status', MessageStatus::Draft->value)->sole()->id)->toBe($waiting->id);
});

it('holds a second reply that would send for the first reply\'s reason, so the owner reads why the new text held (D-35)', function () {
    coverBothPriceQuestionHeld();

    coverBothCustomerTexts('Also, I want to complain about the last visit.', coverBothFirstReply(), [
        'reply' => 'Mike has your price question and your complaint about the last visit.',
        'label' => 'faq_answer',
    ]);

    $live = Message::query()->where('status', MessageStatus::Draft->value)->sole();

    expect($live->body)->toBe('Mike has your price question and your complaint about the last visit.')
        ->and($live->gate_outcome)->toBe(GateOutcome::Hold)
        ->and($live->gate_reason)->toBe('label:complaint')
        ->and($live->gate['reason']['plain'])->toBe('Held: complaint')
        ->and($live->gate['inputs']['agent_reply'])->toBe('Mike has your price question and your complaint about the last visit.')
        ->and(Message::query()->where('body', 'like', '%your complaint about the last visit%')->where('status', MessageStatus::Sent->value)->count())->toBe(0);
});

it('asks Jev at most twice in a whole-thread run, once for each reply (D-35)', function () {
    coverBothPriceQuestionHeld();
    $callsBeforeTheRun = count(fakeClassifier()->calls());
    fakeClassifier()->answer('faq', 0.95, ['promise' => 0.9]);

    coverBothCustomerTexts('Do you do heat pumps?', [
        'reply' => 'Yes, we service heat pumps.',
        'label' => 'faq_answer',
        'touches_waiting' => false,
    ], [
        'reply' => 'Yes, we service heat pumps, and Mike will text you the price for a new unit.',
        'label' => 'faq_answer',
    ]);

    $live = Message::query()->where('status', MessageStatus::Draft->value)->sole();

    expect(array_column(array_slice(fakeClassifier()->calls(), $callsBeforeTheRun), 'draft_reply'))->toBe(['Yes, we service heat pumps.', 'Yes, we service heat pumps, and Mike will text you the price for a new unit.'])
        ->and($live->body)->toBe('Yes, we service heat pumps, and Mike will text you the price for a new unit.')
        ->and($live->gate_reason)->toBe($live->meta['first_reply']['gate_reason'])
        ->and($live->gate_reason)->not->toBeNull()
        ->and($live->gate_outcome)->toBe(GateOutcome::Hold);
});

it('does not ask again when the held reply touches the waiting question or has no words', function () {
    coverBothPriceQuestionHeld();

    coverBothCustomerTexts('Also, I want to complain about the last visit.', [
        'reply' => COVER_BOTH_FIRST_WORDS,
        'label' => 'complaint',
        'touches_waiting' => true,
    ]);

    FrontDeskAgent::assertPromptedTimes(2);

    expect(Message::query()->where('status', MessageStatus::Draft->value)->sole()->meta)->not->toHaveKey('first_reply');
});

it('joins the waiting words and the held reply when the second call has no time left (D-35)', function () {
    coverBothPriceQuestionHeld();

    $waiting = Message::query()->where('status', MessageStatus::Draft->value)->sole();
    $calls = 0;

    test()->travel(1)->minutes();
    test()->post(route('webhooks.text.fake'), fakeTextPayload([
        'MessageSid' => 'SM'.str_pad('late', 32, 'c', STR_PAD_LEFT),
        'Body' => 'Also, I want to complain about the last visit.',
    ]))->assertOk();

    FrontDeskAgent::fake(function () use (&$calls) {
        $calls++;

        if ($calls === 1) {
            test()->travel(120)->seconds();
        }

        return [
            'reply' => $calls === 1 ? COVER_BOTH_FIRST_WORDS : COVER_BOTH_SECOND_WORDS,
            'label' => 'complaint',
            'wants_person' => false,
            'service_slug' => null,
            'intake_answers' => [],
            'customer_name' => null,
            'price_services' => [],
            'asks_discount' => false,
            'touches_waiting' => $calls === 1 ? false : null,
            'waiting_note' => null,
            'easy_reply' => null,
            'easy_label' => null,
        ];
    })->preventStrayPrompts();

    runQueuedFrontDeskJobs();

    $live = Message::query()->where('status', MessageStatus::Draft->value)->sole();

    expect($calls)->toBe(1)
        ->and($live->body)->toBe($waiting->body."\n\n".COVER_BOTH_FIRST_WORDS)
        ->and($live->gate_reason)->toBe('label:complaint')
        ->and($live->meta['first_reply'])->toBe(['reply' => COVER_BOTH_FIRST_WORDS, 'gate_reason' => 'label:complaint'])
        ->and($waiting->fresh()->resolution)->toBe(DraftResolution::Replaced)
        ->and(coverBothTextsToDana())->toHaveCount(2)
        ->and(coverBothTextsToDana()[1]['body'])->toBe(coverBothLater(COVER_BOTH_COMPLAINT_LINE));
});

it('joins the waiting words and the held reply when every provider fails on the second call (D-35)', function () {
    coverBothPriceQuestionHeld();

    $waiting = Message::query()->where('status', MessageStatus::Draft->value)->sole();

    coverBothCustomerTexts('Also, I want to complain about the last visit.', coverBothFirstReply());

    $live = Message::query()->where('status', MessageStatus::Draft->value)->sole();

    expect($live->body)->toBe($waiting->body."\n\n".COVER_BOTH_FIRST_WORDS)
        ->and($live->gate_reason)->toBe('label:complaint')
        ->and($live->meta['first_reply']['reply'])->toBe(COVER_BOTH_FIRST_WORDS)
        ->and($live->meta['replaces'])->toBe([$waiting->id])
        ->and(coverBothTextsToDana())->toHaveCount(2)
        ->and(coverBothTextsToDana()[1]['body'])->toBe(coverBothLater(COVER_BOTH_COMPLAINT_LINE))
        ->and(OwnerAlert::query()->where('reason', AlertReason::Complaint->value)->count())->toBe(1);
});

it('joins the waiting words and the held reply when the second reply has no words (D-35)', function () {
    coverBothPriceQuestionHeld();

    $waiting = Message::query()->where('status', MessageStatus::Draft->value)->sole();

    coverBothCustomerTexts('Also, I want to complain about the last visit.', coverBothFirstReply(), [
        'reply' => '',
        'label' => 'complaint',
        'price_services' => ['ac-repair'],
    ]);

    $live = Message::query()->where('status', MessageStatus::Draft->value)->sole();

    expect($live->body)->toBe($waiting->body."\n\n".COVER_BOTH_FIRST_WORDS)
        ->and($live->gate_reason)->toBe('label:complaint')
        ->and(coverBothTextsToDana())->toHaveCount(2);
});
