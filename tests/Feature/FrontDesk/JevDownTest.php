<?php

use App\Actions\RunFrontDesk;
use App\Ai\AgentReply;
use App\Ai\AgentRun;
use App\Enums\AlertReason;
use App\Enums\MessageDirection;
use App\Enums\MessageKind;
use App\Enums\MessageStatus;
use App\Models\Conversation;
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

it('sends only the holding reply to a text the keywords do not catch while Jev is down, and tells the owner the safety check is down (D-49)', function () {
    activateExampleFolder();
    jevDownBegins('2026-10-13 10:00');
    scriptFrontDeskReplies(['reply' => 'Thanks, what is the AC doing?', 'label' => 'intake_question']);

    jevDownTextFrom('+12025550121', JEV_DOWN_AC);

    runQueuedFrontDeskJobs();

    $sent = jevDownMessagesTo('+12025550121')->where('status', MessageStatus::Sent);

    expect(jevDownTextsTo('+12025550121'))->toHaveCount(1)
        ->and($sent->pluck('kind')->all())->toBe([MessageKind::HoldingReply])
        ->and(Message::query()->whereIn('kind', [MessageKind::AgentReply->value, MessageKind::CodeAnswer->value])->where('status', MessageStatus::Sent->value)->count())->toBe(0)
        ->and(jevDownAlerts(AlertReason::SafetyCheckDown))->toHaveCount(1);
});

const JEV_DOWN_AC_SENTENCE = 'AC repair starts with an $89 diagnostic visit, and we take the $89 off if you go ahead with the repair that visit.';

const JEV_DOWN_FIRST_VISIT_OFFER = "If you're a first-time customer, we're running a promotion that takes \$50 off your first visit.";

const JEV_DOWN_COMPLAINT_WORDS = 'Sorry about the mess, Mike will call you.';

/**
 * Wednesday 2026-10-14 at 02:00 with Jev down: Dana texts and the front desk answers with the scripted reply. The
 * folder is activated first, with the lines the given edits make to rules/sending.md.
 *
 * @param  array<string, mixed>  $reply
 * @param  array<string, string>  $sendingEdits  Text in rules/sending.md => its replacement.
 */
function jevDownAtNightRun(string $body, array $reply, array $sendingEdits = []): void
{
    test()->travelTo(Carbon::parse('2026-10-14 02:00', 'America/Chicago'));
    User::factory()->withPhone(JEV_DOWN_OWNER_PHONE)->create();

    foreach ($sendingEdits as $from => $to) {
        breakFile('rules/sending.md', fn (string $text): string => str_replace($from, $to, $text));
    }

    activateExampleFolder();
    fakeClassifier()->failWith(JEV_DOWN_FAILURE);
    scriptFrontDeskReplies($reply);

    jevDownTextFrom('+12025550121', $body);

    runQueuedFrontDeskJobs();
}

it('holds a price sentence while Jev is down, pre-filled in the draft, and sends the holding reply (D-50)', function () {
    jevDownAtNightRun('How much is an AC repair?', ['reply' => '', 'label' => 'faq_answer', 'price_services' => ['ac-repair']]);

    $draft = Message::query()->where('status', MessageStatus::Draft->value)->first();
    $waiting = jevDownAlerts(AlertReason::DraftWaiting);

    expect(Message::query()->where('kind', MessageKind::CodeAnswer->value)->count())->toBe(0)
        ->and(collect(jevDownTextsTo('+12025550121'))->contains(fn (array $text): bool => str_contains($text['body'], '$89')))->toBeFalse()
        ->and($draft?->gate_reason)->toBe('classifier_failed')
        ->and($draft?->body)->toBe(JEV_DOWN_AC_SENTENCE)
        ->and(jevDownMessagesTo('+12025550121')->where('kind', MessageKind::HoldingReply)->where('status', MessageStatus::Sent))->toHaveCount(1)
        ->and($waiting)->toHaveCount(1)
        ->and(jevDownDueAt($waiting->first()))->toBe('2026-10-14 08:00')
        ->and($waiting->first()->sent_at)->toBeNull();
});

it('holds an offer sentence while Jev is down, pre-filled in the draft, and sends the holding reply (D-50)', function () {
    jevDownAtNightRun('Do you have any discounts?', ['reply' => '', 'label' => 'faq_answer', 'asks_discount' => true]);

    $draft = Message::query()->where('status', MessageStatus::Draft->value)->first();

    expect(Message::query()->where('kind', MessageKind::CodeAnswer->value)->count())->toBe(0)
        ->and(collect(jevDownTextsTo('+12025550121'))->contains(fn (array $text): bool => str_contains($text['body'], '$50')))->toBeFalse()
        ->and($draft?->gate_reason)->toBe('classifier_failed')
        ->and($draft?->body)->toBe(JEV_DOWN_FIRST_VISIT_OFFER)
        ->and(jevDownMessagesTo('+12025550121')->where('kind', MessageKind::HoldingReply)->where('status', MessageStatus::Sent))->toHaveCount(1);
});

it('sends the complaint reply and a right-away complaint alert when the agent labels its words a complaint while Jev is down (D-50)', function () {
    jevDownAtNightRun('Your tech tracked mud through my house.', ['reply' => JEV_DOWN_COMPLAINT_WORDS, 'label' => 'complaint']);

    $complaint = jevDownAlerts(AlertReason::Complaint);

    expect(jevDownMessagesTo('+12025550121')->where('kind', MessageKind::ComplaintHolding)->where('status', MessageStatus::Sent))->toHaveCount(1)
        ->and(collect(jevDownTextsTo('+12025550121'))->contains(fn (array $text): bool => str_contains($text['body'], JEV_DOWN_COMPLAINT_WORDS)))->toBeFalse()
        ->and($complaint)->toHaveCount(1)
        ->and($complaint->first()->urgent)->toBeTrue()
        ->and(jevDownDueAt($complaint->first()))->toBe('2026-10-14 02:00')
        ->and($complaint->first()->sent_at)->not->toBeNull()
        ->and(jevDownAlerts(AlertReason::SafetyCheckDown))->toHaveCount(1);
});

it('stores the complaint alert for the next opening when complaint is off right_away while Jev is down, and still sends the one safety_check_down alert at once (D-50)', function () {
    jevDownAtNightRun('Your tech tracked mud through my house.', ['reply' => JEV_DOWN_COMPLAINT_WORDS, 'label' => 'complaint'], ['right_away: [complaint, ' => 'right_away: [']);

    $complaint = jevDownAlerts(AlertReason::Complaint);
    $down = jevDownAlerts(AlertReason::SafetyCheckDown);

    expect(jevDownMessagesTo('+12025550121')->where('kind', MessageKind::ComplaintHolding)->where('status', MessageStatus::Sent))->toHaveCount(1)
        ->and($complaint)->toHaveCount(1)
        ->and($complaint->first()->urgent)->toBeFalse()
        ->and(jevDownDueAt($complaint->first()))->toBe('2026-10-14 08:00')
        ->and($complaint->first()->sent_at)->toBeNull()
        ->and($down)->toHaveCount(1)
        ->and(jevDownDueAt($down->first()))->toBe('2026-10-14 02:00')
        ->and($down->first()->sent_at)->not->toBeNull();
});

it('hands a request for a person to the owner while Jev is down (D-50)', function () {
    jevDownAtNightRun('Can someone please look at my AC today?', ['reply' => 'I can help with that myself.', 'label' => 'faq_answer', 'wants_person' => true]);

    expect(jevDownMessagesTo('+12025550121')->where('kind', MessageKind::HandoffReply)->where('status', MessageStatus::Sent))->toHaveCount(1)
        ->and(jevDownAlerts(AlertReason::AskedForPerson))->toHaveCount(1)
        ->and(Message::query()->where('status', MessageStatus::Draft->value)->count())->toBe(0)
        ->and(Conversation::query()->sole()->isPaused())->toBeTrue()
        ->and(jevDownAlerts(AlertReason::SafetyCheckDown))->toHaveCount(1);
});

it('keeps an ordinary held draft\'s alert for the next opening while the safety_check_down alert goes out at once (D-50)', function () {
    jevDownAtNightRun(JEV_DOWN_AC, ['reply' => 'Thanks, what is the AC doing?', 'label' => 'intake_question']);

    $waiting = jevDownAlerts(AlertReason::DraftWaiting);
    $down = jevDownAlerts(AlertReason::SafetyCheckDown);

    expect($waiting)->toHaveCount(1)
        ->and(jevDownDueAt($waiting->first()))->toBe('2026-10-14 08:00')
        ->and($waiting->first()->sent_at)->toBeNull()
        ->and($down)->toHaveCount(1)
        ->and(jevDownDueAt($down->first()))->toBe('2026-10-14 02:00')
        ->and($down->first()->sent_at)->not->toBeNull();
});
