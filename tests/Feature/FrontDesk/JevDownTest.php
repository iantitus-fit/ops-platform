<?php

use App\Actions\RunFrontDesk;
use App\Ai\AgentReply;
use App\Ai\AgentRun;
use App\Enums\AlertReason;
use App\Enums\MessageDirection;
use App\Enums\MessageKind;
use App\Enums\MessageStatus;
use App\Models\Message;
use App\Models\OwnerAlert;
use App\Models\SafetyCheckOutage;
use App\Models\User;
use App\SendRule\CustomerReading;
use App\SendRule\DecideSendOrDraft;
use App\SendRule\ReadCustomerText;
use App\SendRule\SafetyCheckStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

const JEV_DOWN_OWNER_PHONE = '+12025550199';

const JEV_DOWN_FAILURE = 'Jev could not be reached or took too long to answer.';

const JEV_DOWN_AC = 'My AC is blowing warm air';

const JEV_DOWN_CARRIER = 'Do you work on Carrier units?';

const JEV_DOWN_DIZZY = 'My head is pounding and the kids feel dizzy since the furnace came on.';

/**
 * The shop's zone clock moved to the given time, the owner with a phone, and Jev not answering any call.
 */
function jevDownBegins(string $at): void
{
    test()->travelTo(Carbon::parse($at, 'America/Chicago'));

    User::factory()->withPhone(JEV_DOWN_OWNER_PHONE)->create();

    fakeClassifier()->failWith(JEV_DOWN_FAILURE);
}

/**
 * One customer's text arriving through the fake webhook, with a number and a message id of its own.
 */
function jevDownTextFrom(string $number, string $body): void
{
    test()->post(route('webhooks.text.fake'), fakeTextPayload([
        'From' => $number,
        'Body' => $body,
        'MessageSid' => 'SM'.substr(md5($number.$body), 0, 32),
    ]))->assertOk();
}

/**
 * The texts that reached one customer's phone, leaving out the alerts to the owner.
 *
 * @return list<array{to: string, body: string, provider_message_id: string}>
 */
function jevDownTextsTo(string $number): array
{
    return array_values(array_filter(textsSentToCustomers(), fn (array $text): bool => $text['to'] === $number));
}

/**
 * The messages the front desk kept for one customer, sent or held, oldest first.
 *
 * @return Collection<int, Message>
 */
function jevDownMessagesTo(string $number): Collection
{
    return Message::query()
        ->where('direction', MessageDirection::Out)
        ->whereHas('conversation.contact', fn ($contact) => $contact->where('phone_e164', $number))
        ->orderBy('id')
        ->get();
}

/**
 * Every alert of one reason, oldest first.
 *
 * @return Collection<int, OwnerAlert>
 */
function jevDownAlerts(AlertReason $reason): Collection
{
    return OwnerAlert::query()->where('reason', $reason->value)->orderBy('id')->get();
}

/**
 * When an alert is due, in the shop's zone.
 */
function jevDownDueAt(OwnerAlert $alert): string
{
    return $alert->due_at->setTimezone('America/Chicago')->format('Y-m-d H:i');
}

it('tells the owner once, right away, and answers all three customers when Jev is down at 2 am', function () {
    activateExampleFolder();
    jevDownBegins('2026-10-14 02:00');
    scriptFrontDeskReplies(
        ['reply' => 'Thanks, what is the AC doing?', 'label' => 'intake_question'],
        ['reply' => 'Yes, we work on Carrier units.', 'label' => 'faq_answer'],
        ['reply' => 'Thanks, what is the furnace doing?', 'label' => 'intake_question', 'possible_danger' => true],
    );

    jevDownTextFrom('+12025550121', JEV_DOWN_AC);
    jevDownTextFrom('+12025550122', JEV_DOWN_CARRIER);
    jevDownTextFrom('+12025550123', JEV_DOWN_DIZZY);

    runQueuedFrontDeskJobs();

    $down = jevDownAlerts(AlertReason::SafetyCheckDown);

    expect($down)->toHaveCount(1)
        ->and($down->sole()->conversation_id)->toBeNull()
        ->and($down->sole()->urgent)->toBeTrue()
        ->and($down->sole()->sent_at)->not->toBeNull()
        ->and(jevDownDueAt($down->sole()))->toBe('2026-10-14 02:00');

    $first = jevDownMessagesTo('+12025550121');
    $second = jevDownMessagesTo('+12025550122');
    $third = jevDownMessagesTo('+12025550123');
    $drafts = Message::query()->where('status', MessageStatus::Draft->value)->get();
    $waiting = jevDownAlerts(AlertReason::DraftWaiting);

    expect(jevDownTextsTo('+12025550121'))->toHaveCount(1)
        ->and($first->where('kind', MessageKind::HoldingReply)->count())->toBe(1)
        ->and(jevDownTextsTo('+12025550122'))->toHaveCount(1)
        ->and($second->where('kind', MessageKind::HoldingReply)->count())->toBe(1)
        ->and(jevDownTextsTo('+12025550123'))->toHaveCount(1)
        ->and($third->where('kind', MessageKind::LifeSafety)->count())->toBe(1)
        ->and($third->where('kind', MessageKind::HoldingReply)->count())->toBe(0)
        ->and($drafts)->toHaveCount(3)
        ->and($drafts->pluck('gate_reason')->unique()->all())->toBe(['classifier_failed'])
        ->and($waiting)->toHaveCount(3)
        ->and($waiting->map(fn (OwnerAlert $alert): string => jevDownDueAt($alert))->unique()->all())->toBe(['2026-10-14 08:00'])
        ->and($waiting->whereNotNull('sent_at'))->toHaveCount(0);

    $outage = SafetyCheckOutage::query()->sole();

    expect($outage->ended_at)->toBeNull()
        ->and($outage->down_alerted_at)->not->toBeNull()
        ->and(app(SafetyCheckStatus::class)->isDown())->toBeTrue();
});

it('sends the draft alert at once when Jev is down at 10:00 and the shop is open', function () {
    activateExampleFolder();
    jevDownBegins('2026-10-14 10:00');
    scriptFrontDeskReplies(['reply' => 'Thanks, what is the AC doing?', 'label' => 'intake_question']);

    jevDownTextFrom('+12025550121', JEV_DOWN_AC);

    runQueuedFrontDeskJobs();

    $waiting = jevDownAlerts(AlertReason::DraftWaiting);

    expect(jevDownAlerts(AlertReason::SafetyCheckDown))->toHaveCount(1)
        ->and($waiting)->toHaveCount(1)
        ->and($waiting->sole()->sent_at)->not->toBeNull();
});

it('marks a reading missing, not failed, when a run reaches the reading() fallback, and holds the words without marking Jev down', function () {
    activateExampleFolder();
    test()->travelTo(Carbon::parse('2026-10-14 10:00', 'America/Chicago'));

    $reading = (new ReflectionMethod(RunFrontDesk::class, 'reading'))->invoke(app(RunFrontDesk::class));
    $stored = CustomerReading::fromStored(json_decode(json_encode($reading->toArray()), true));

    expect($reading->toArray()['missing'] ?? null)->toBeTrue()
        ->and($reading->toArray()['failed'])->toBeFalse()
        ->and($stored?->toArray()['missing'] ?? null)->toBeTrue()
        ->and($stored?->toArray()['failed'])->toBeFalse();

    $run = new AgentRun(AgentReply::fromStructured(['reply' => 'Thanks, what is the AC doing?', 'label' => 'intake_question', 'wants_person' => false]), 'anthropic', 'claude', []);
    $verdict = app(DecideSendOrDraft::class)->decide($run, JEV_DOWN_AC, 'Thanks, what is the AC doing?', $reading);

    expect($verdict->outcome->value)->toBe('hold')
        ->and($verdict->reasonCode)->toBe('classifier_failed')
        ->and(SafetyCheckOutage::query()->count())->toBe(0)
        ->and(app(SafetyCheckStatus::class)->isDown())->toBeFalse()
        ->and(jevDownAlerts(AlertReason::SafetyCheckDown))->toHaveCount(0)
        ->and(textsSentForAgentReplies())->toBe([])
        ->and(Message::query()->whereIn('kind', [MessageKind::AgentReply->value, MessageKind::CodeAnswer->value])->count())->toBe(0);
});

it('marks a reading missing, not failed, when no business folder is live, and sends nothing a model wrote', function () {
    User::factory()->withPhone(JEV_DOWN_OWNER_PHONE)->create();
    test()->travelTo(Carbon::parse('2026-10-14 10:00', 'America/Chicago'));
    scriptFrontDeskReplies(['reply' => 'Thanks, what is the AC doing?', 'label' => 'intake_question']);

    jevDownTextFrom('+12025550121', JEV_DOWN_AC);

    runQueuedFrontDeskJobs();

    $reading = app(ReadCustomerText::class)->handle(JEV_DOWN_AC);
    $stored = CustomerReading::fromStored(json_decode(json_encode($reading->toArray()), true));

    expect($reading->toArray()['missing'] ?? null)->toBeTrue()
        ->and($reading->toArray()['failed'])->toBeFalse()
        ->and($reading->error)->toContain('no business folder is live')
        ->and($stored?->toArray()['missing'] ?? null)->toBeTrue()
        ->and($stored?->error)->toContain('no business folder is live')
        ->and(fakeClassifier()->allCalls())->toBe([])
        ->and(jevDownTextsTo('+12025550121'))->toBe([])
        ->and(jevDownAlerts(AlertReason::SafetyCheckMissing))->toHaveCount(1)
        ->and(Message::query()->where('direction', MessageDirection::Out)->count())->toBe(0)
        ->and(SafetyCheckOutage::query()->count())->toBe(0)
        ->and(app(SafetyCheckStatus::class)->isDown())->toBeFalse()
        ->and(jevDownAlerts(AlertReason::SafetyCheckDown))->toHaveCount(0);
});
