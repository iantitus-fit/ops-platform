<?php

use App\Ai\AgentReply;
use App\Enums\AlertReason;
use App\Enums\GateOutcome;
use App\Enums\MessageKind;
use App\Enums\MessageStatus;
use App\Enums\PausedReason;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\OwnerAlert;
use App\Models\User;
use App\SendRule\Verdict;
use Illuminate\Support\Carbon;

const POSSIBLE_DANGER_OWNER_PHONE = '+12025550199';

const POSSIBLE_DANGER_CUSTOMER_PHONE = '+12025550123';

const POSSIBLE_DANGER_TEXT = 'My head is pounding and the kids feel dizzy since the furnace came on. How much is a furnace repair?';

const POSSIBLE_DANGER_FURNACE_SENTENCE = 'Furnace repair starts with an $89 diagnostic visit, and we take the $89 off if you go ahead with the repair that visit.';

/**
 * Tuesday 2026-10-13 at 10:00 in the shop's zone, the owner with a phone, and Dana's text in the live example folder.
 * The reading is left clean, as when Jev misses the danger.
 */
function possibleDangerDanaTexts(string $body = POSSIBLE_DANGER_TEXT): void
{
    test()->travelTo(Carbon::parse('2026-10-13 10:00', 'America/Chicago'));

    User::factory()->withPhone(POSSIBLE_DANGER_OWNER_PHONE)->create();

    frontDeskCustomerTexts($body);
}

/**
 * The texts that reached Dana's phone, leaving out the alerts to the owner.
 *
 * @return list<array{to: string, body: string, provider_message_id: string}>
 */
function possibleDangerTextsToDana(): array
{
    return array_values(array_filter(
        textsSentToCustomers(),
        fn (array $text): bool => $text['to'] === POSSIBLE_DANGER_CUSTOMER_PHONE,
    ));
}

/**
 * The one draft the run kept for the owner.
 */
function possibleDangerDraft(): ?Message
{
    return Message::query()->where('status', MessageStatus::Draft->value)->first();
}

/**
 * The reasons of every alert the owner has, oldest first.
 *
 * @return list<string>
 */
function possibleDangerAlertReasons(): array
{
    return OwnerAlert::query()->orderBy('id')->get()->map(fn (OwnerAlert $alert): string => $alert->reason->value)->all();
}

it('sends the safety line first, with no price sentence, when the agent says the text may describe a danger (D-45 third net)', function () {
    possibleDangerDanaTexts();
    scriptFrontDeskReplies(['reply' => '', 'price_services' => ['furnace-repair'], 'possible_danger' => true]);

    runQueuedFrontDeskJobs();

    $line = Message::query()->where('kind', MessageKind::LifeSafety->value)->first();

    expect(possibleDangerTextsToDana())->toHaveCount(1)
        ->and($line?->gate['life_safety']['trigger'])->toBe('agent')
        ->and(Message::query()->where('kind', MessageKind::CodeAnswer->value)->count())->toBe(0)
        ->and(Message::query()->where('kind', MessageKind::HoldingReply->value)->count())->toBe(0)
        ->and(Conversation::query()->sole()->paused_reason)->toBe(PausedReason::LifeSafety)
        ->and(possibleDangerAlertReasons())->toContain(AlertReason::LifeSafety->value);
});

it('holds the furnace price sentence in a draft marked agent:possible_danger when the agent suspects a danger', function () {
    possibleDangerDanaTexts();
    scriptFrontDeskReplies(['reply' => '', 'price_services' => ['furnace-repair'], 'possible_danger' => true]);

    runQueuedFrontDeskJobs();

    $draft = possibleDangerDraft();

    expect(Message::query()->where('status', MessageStatus::Draft->value)->count())->toBe(1)
        ->and($draft?->gate_outcome)->toBe(GateOutcome::Hold)
        ->and($draft?->gate_reason)->toBe('agent:possible_danger')
        ->and($draft?->body)->toBe(POSSIBLE_DANGER_FURNACE_SENTENCE)
        ->and($draft?->meta['held_sentences'])->toBe([POSSIBLE_DANGER_FURNACE_SENTENCE]);
});

it('sends only the safety line, and holds the words with the sentence, when the agent writes words and suspects a danger', function () {
    possibleDangerDanaTexts();
    scriptFrontDeskReplies([
        'reply' => 'Sorry to hear that. What is the furnace doing?',
        'label' => 'intake_question',
        'price_services' => ['furnace-repair'],
        'possible_danger' => true,
    ]);

    runQueuedFrontDeskJobs();

    $line = Message::query()->where('kind', MessageKind::LifeSafety->value)->first();
    $draft = possibleDangerDraft();

    expect(possibleDangerTextsToDana())->toHaveCount(1)
        ->and($line?->gate['life_safety']['trigger'])->toBe('agent')
        ->and($draft?->gate_reason)->toBe('agent:possible_danger')
        ->and($draft?->body)->toBe("Sorry to hear that. What is the furnace doing?\n\n".POSSIBLE_DANGER_FURNACE_SENTENCE);
});

it('holds just the words when the agent suspects a danger and no price sentence was picked', function () {
    possibleDangerDanaTexts('My head is pounding and the kids feel dizzy since the furnace came on.');
    scriptFrontDeskReplies([
        'reply' => 'Sorry to hear that. What is the furnace doing?',
        'label' => 'intake_question',
        'possible_danger' => true,
    ]);

    runQueuedFrontDeskJobs();

    $draft = possibleDangerDraft();

    expect(possibleDangerTextsToDana())->toHaveCount(1)
        ->and($draft?->gate_reason)->toBe('agent:possible_danger')
        ->and($draft?->body)->toBe('Sorry to hear that. What is the furnace doing?')
        ->and(Message::query()->where('kind', MessageKind::HoldingReply->value)->count())->toBe(0);
});

it('sends the safety line and tells the owner the customer asked for a person, with no handoff text', function () {
    possibleDangerDanaTexts();
    scriptFrontDeskReplies(['reply' => 'I will get someone for you.', 'label' => 'other', 'wants_person' => true, 'possible_danger' => true]);

    runQueuedFrontDeskJobs();

    expect(possibleDangerTextsToDana())->toHaveCount(1)
        ->and(Message::query()->where('kind', MessageKind::LifeSafety->value)->first()?->gate['life_safety']['trigger'])->toBe('agent')
        ->and(Message::query()->where('kind', MessageKind::HandoffReply->value)->count())->toBe(0)
        ->and(possibleDangerAlertReasons())->toContain(AlertReason::LifeSafety->value, AlertReason::AskedForPerson->value)
        ->and(Conversation::query()->sole()->paused_reason)->toBe(PausedReason::LifeSafety);
});

it('sends one safety line when Jev already read the danger and the agent agrees', function () {
    possibleDangerDanaTexts();
    fakeClassifier()->answerReading('emergency', 0.95, ['life_safety' => 0.9]);
    scriptFrontDeskReplies(['reply' => '', 'price_services' => ['furnace-repair'], 'possible_danger' => true]);

    runQueuedFrontDeskJobs();

    $line = Message::query()->where('kind', MessageKind::LifeSafety->value)->first();

    expect(possibleDangerTextsToDana())->toHaveCount(1)
        ->and($line?->gate['life_safety']['trigger'])->toBe('classifier');
});

it('runs as before when the agent does not suspect a danger (control)', function () {
    possibleDangerDanaTexts();
    scriptFrontDeskReplies(['reply' => '', 'price_services' => ['furnace-repair'], 'possible_danger' => false]);

    runQueuedFrontDeskJobs();

    $texts = possibleDangerTextsToDana();

    expect($texts)->toHaveCount(1)
        ->and($texts[0]['body'])->toContain(POSSIBLE_DANGER_FURNACE_SENTENCE)
        ->and(Message::query()->where('kind', MessageKind::LifeSafety->value)->count())->toBe(0)
        ->and(Message::query()->where('kind', MessageKind::CodeAnswer->value)->count())->toBe(1)
        ->and(Conversation::query()->sole()->paused_reason)->toBeNull()
        ->and(possibleDangerDraft())->toBeNull();
});

it('reads possible_danger as true only when the model gives exactly true', function () {
    $reply = AgentReply::fromStructured(['reply' => 'Hi', 'label' => 'faq_answer', 'wants_person' => false, 'possible_danger' => true]);

    expect($reply->possibleDanger)->toBeTrue()
        ->and($reply->toArray()['possible_danger'])->toBeTrue();
});

it('rejects a missing, null or malformed possible_danger like wants_person, so the chain tries the next model (WR-02)', function (array $extra) {
    expect(fn () => AgentReply::fromStructured(['reply' => 'Hi', 'label' => 'faq_answer', 'wants_person' => false, ...$extra]))
        ->toThrow(UnexpectedValueException::class, 'possible_danger');
})->with([
    'missing' => [[]],
    'null' => [['possible_danger' => null]],
    'the word yes' => [['possible_danger' => 'yes']],
    'the number one' => [['possible_danger' => 1]],
]);

it('does not carry possible_danger into the easy part of a reply', function () {
    $reply = AgentReply::fromStructured([
        'reply' => 'Hi',
        'label' => 'faq_answer',
        'wants_person' => false,
        'touches_waiting' => true,
        'easy_reply' => 'We open at 8.',
        'easy_label' => 'faq_answer',
        'possible_danger' => true,
    ]);

    expect($reply->easyPart()?->possibleDanger)->toBeFalse();
});

it('tells the owner a possible_danger draft may be urgent', function () {
    possibleDangerDanaTexts();
    scriptFrontDeskReplies(['reply' => '', 'price_services' => ['furnace-repair'], 'possible_danger' => true]);

    runQueuedFrontDeskJobs();

    expect(possibleDangerDraft()?->gate['reason']['plain'])->toBe('Held: may be urgent');
});

it('words the agent:possible_danger code as may be urgent and any other agent code as needing judgment', function () {
    expect(Verdict::plainReasonFor('agent:possible_danger'))->toBe('Held: may be urgent')
        ->and(Verdict::plainReasonFor('agent:something_else'))->toBe('Held: needs your judgment');
});
