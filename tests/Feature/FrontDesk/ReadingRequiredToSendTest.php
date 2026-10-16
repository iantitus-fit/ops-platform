<?php

use App\Actions\OutboundSender;
use App\Actions\RaiseOwnerAlert;
use App\Actions\RunFrontDesk;
use App\Ai\AgentReply;
use App\Ai\AgentRun;
use App\Enums\AlertReason;
use App\Enums\ConversationStatus;
use App\Enums\MessageAuthor;
use App\Enums\MessageDirection;
use App\Enums\MessageKind;
use App\Enums\MessageStatus;
use App\Enums\SendOrigin;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\OwnerAlert;
use App\Models\SafetyCheckOutage;
use App\Models\User;
use App\SendRule\CustomerReading;
use App\SendRule\DecideSendOrDraft;
use App\SendRule\SafetyCheckStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

const READING_REQUIRED_OWNER_PHONE = '+12025550199';

const READING_REQUIRED_CUSTOMER_PHONE = '+12025550123';

const READING_REQUIRED_CUSTOMER_TEXT = 'My AC is blowing warm air';

/**
 * The shop's clock at the given moment, the owner with a phone, Dana's text waiting and the live example folder. The
 * run is not started: a test that wants the front desk to run calls runQueuedFrontDeskJobs() itself.
 */
function readingRequiredBegins(string $at = '2026-10-13 10:00', string $text = READING_REQUIRED_CUSTOMER_TEXT): Conversation
{
    test()->travelTo(Carbon::parse($at, 'America/Chicago'));

    User::factory()->withPhone(READING_REQUIRED_OWNER_PHONE)->create();

    frontDeskCustomerTexts($text);

    return Conversation::query()->sole();
}

/**
 * What Jev read when it found nothing to worry about.
 */
function readingRequiredCleanReading(): CustomerReading
{
    return new CustomerReading(
        ran: true,
        failed: false,
        error: null,
        model: 'fake-jev',
        answers: null,
        reading: ['sendable' => true, 'reason_code' => null, 'reasons' => [], 'life_safety' => false, 'asks_for_person' => false, 'thresholds' => []],
    );
}

/**
 * The fixed gate a holding reply carries, with the given reading when there is one.
 *
 * @return array<string, mixed>
 */
function readingRequiredFixedGate(?CustomerReading $reading = null): array
{
    return [
        'version' => 1,
        'fixed' => true,
        'reason' => ['code' => 'fixed_reply', 'plain' => null],
        ...($reading === null ? [] : ['customer_reading' => $reading->toArray()]),
    ];
}

/**
 * A holding reply sent straight through the one outbound path as an automated text.
 *
 * @param  array<string, mixed>  $gate
 */
function readingRequiredSendHolding(Conversation $conversation, array $gate): Message
{
    return app(OutboundSender::class)->send($conversation, 'Hello', MessageAuthor::Agent, SendOrigin::Automated, [
        'kind' => MessageKind::HoldingReply,
        'gate' => $gate,
    ]);
}

/**
 * Every alert of one reason, oldest first.
 *
 * @return Collection<int, OwnerAlert>
 */
function readingRequiredAlerts(AlertReason $reason): Collection
{
    return OwnerAlert::query()->where('reason', $reason->value)->orderBy('id')->get();
}

/**
 * The texts that reached Dana's phone, leaving out the alerts to the owner.
 *
 * @return list<array{to: string, body: string, provider_message_id: string}>
 */
function readingRequiredTextsToDana(): array
{
    return array_values(array_filter(
        textsSentToCustomers(),
        fn (array $text): bool => $text['to'] === READING_REQUIRED_CUSTOMER_PHONE,
    ));
}

/**
 * When an alert is due, in the shop's zone.
 */
function readingRequiredDueAt(OwnerAlert $alert): string
{
    return $alert->due_at->setTimezone('America/Chicago')->format('Y-m-d H:i');
}

it('puts the run\'s reading on every kind of automated text it sends', function (string $text, array $script, array $jev, MessageKind $kind) {
    test()->travelTo(Carbon::parse('2026-10-13 10:00', 'America/Chicago'));
    User::factory()->withPhone(READING_REQUIRED_OWNER_PHONE)->create();
    frontDeskCustomerTexts($text);
    scriptFrontDeskReplies($script);

    if ($jev !== []) {
        fakeClassifier()->answer(...$jev);
    }

    runQueuedFrontDeskJobs();

    $message = Message::query()->where('kind', $kind->value)->where('status', MessageStatus::Sent->value)->sole();

    expect($message->gate['customer_reading']['ran'] ?? null)->toBeTrue()
        ->and($message->gate['customer_reading']['failed'] ?? null)->toBeFalse();
})->with([
    'the intake question' => ['My AC is blowing warm air', ['reply' => 'Thanks, what is the AC doing?', 'label' => 'intake_question'], [], MessageKind::AgentReply],
    'a holding reply' => ['How much for an AC repair?', ['reply' => 'It is $50.', 'label' => 'price'], [], MessageKind::HoldingReply],
    'a code answer' => ['How much is the diagnostic for my AC?', ['reply' => '', 'label' => 'faq_answer', 'price_services' => ['ac-repair']], [], MessageKind::CodeAnswer],
    'a partial holding text' => ['How much is the diagnostic, and do you work on Carrier units?', ['reply' => 'Yes, we work on Carrier units.', 'label' => 'faq_answer', 'price_services' => ['ac-repair']], ['price_question', 0.95], MessageKind::PartialHolding],
]);

it('refuses an automated text with no reading, tells the owner at once and sends nothing', function () {
    $conversation = readingRequiredBegins();

    expect(fn () => readingRequiredSendHolding($conversation, readingRequiredFixedGate()))
        ->toThrow(DomainException::class, OutboundSender::NO_READING);

    expect(readingRequiredAlerts(AlertReason::SafetyCheckMissing))->toHaveCount(1);

    $alert = readingRequiredAlerts(AlertReason::SafetyCheckMissing)->first();

    expect(Message::query()->where('direction', MessageDirection::Out)->count())->toBe(0)
        ->and(readingRequiredTextsToDana())->toBe([])
        ->and($alert->urgent)->toBeTrue()
        ->and($alert->sent_at)->not->toBeNull()
        ->and($alert->body)->toContain('the safety check had not read')
        ->and(readingRequiredAlerts(AlertReason::FrontDeskFailed))->toHaveCount(0);
});

it('sends the safety_check_missing alert at 2 am even when the folder takes front_desk_failed off right_away, once per customer text', function () {
    test()->travelTo(Carbon::parse('2026-10-14 02:00', 'America/Chicago'));
    User::factory()->withPhone(READING_REQUIRED_OWNER_PHONE)->create();
    breakFile('rules/sending.md', fn (string $text): string => str_replace('front_desk_failed, ', '', $text));
    frontDeskCustomerTexts(READING_REQUIRED_CUSTOMER_TEXT);
    $conversation = Conversation::query()->sole();

    expect(fn () => readingRequiredSendHolding($conversation, readingRequiredFixedGate()))
        ->toThrow(DomainException::class, OutboundSender::NO_READING)
        ->and(fn () => readingRequiredSendHolding($conversation, readingRequiredFixedGate()))
        ->toThrow(DomainException::class, OutboundSender::NO_READING);

    expect(readingRequiredAlerts(AlertReason::SafetyCheckMissing))->toHaveCount(1);

    $alert = readingRequiredAlerts(AlertReason::SafetyCheckMissing)->first();

    expect($alert->urgent)->toBeTrue()
        ->and(readingRequiredDueAt($alert))->toBe('2026-10-14 02:00')
        ->and($alert->sent_at)->not->toBeNull()
        ->and(readingRequiredAlerts(AlertReason::FrontDeskFailed))->toHaveCount(0);
});

it('names safety_check_missing and always counts it as urgent', function () {
    activateExampleFolder();

    expect(AlertReason::SafetyCheckMissing->value)->toBe('safety_check_missing')
        ->and(AlertReason::SafetyCheckMissing->plainWords())->toBe('Safety check did not run')
        ->and(app(RaiseOwnerAlert::class)->isUrgent(AlertReason::SafetyCheckMissing))->toBeTrue();
});

it('sends the safety line without any reading', function () {
    $conversation = readingRequiredBegins();

    $sent = app(OutboundSender::class)->send($conversation, 'Leave the house and call 911.', MessageAuthor::Agent, SendOrigin::LifeSafety, [
        'kind' => MessageKind::LifeSafety,
    ]);

    expect($sent->status)->toBe(MessageStatus::Sent)
        ->and(readingRequiredAlerts(AlertReason::SafetyCheckMissing))->toHaveCount(0);
});

it('refuses a paused or closed conversation with its own sentence before looking for a reading', function (string $state, string $sentence) {
    $conversation = readingRequiredBegins();

    if ($state === 'paused') {
        $conversation->update(['paused_at' => now()]);
    } else {
        $conversation->update(['status' => ConversationStatus::Closed]);
    }

    expect(fn () => readingRequiredSendHolding($conversation, readingRequiredFixedGate()))
        ->toThrow(DomainException::class, $sentence);

    expect(readingRequiredAlerts(AlertReason::SafetyCheckMissing))->toHaveCount(0);
})->with([
    'paused' => ['paused', 'The assistant is paused in this conversation.'],
    'closed' => ['closed', 'This conversation is closed.'],
]);

it('treats a stored reading marked missing like no reading at all', function () {
    $conversation = readingRequiredBegins();
    $missing = CustomerReading::missing('The customer text was not read for this run.');

    expect(fn () => readingRequiredSendHolding($conversation, readingRequiredFixedGate($missing)))
        ->toThrow(DomainException::class, OutboundSender::NO_READING);

    expect(readingRequiredAlerts(AlertReason::SafetyCheckMissing))->toHaveCount(1)
        ->and(readingRequiredAlerts(AlertReason::FrontDeskFailed))->toHaveCount(0)
        ->and(Message::query()->where('direction', MessageDirection::Out)->count())->toBe(0);
});

it('holds the model\'s words and raises one safety_check_missing alert when a run reaches the reading() fallback, leaving Jev marked up', function () {
    $conversation = readingRequiredBegins('2026-10-14 10:00');

    $reading = (new ReflectionMethod(RunFrontDesk::class, 'reading'))->invoke(app(RunFrontDesk::class));
    $words = 'Thanks, what is the AC doing?';
    $run = new AgentRun(AgentReply::fromStructured(['reply' => $words, 'label' => 'intake_question', 'wants_person' => false]), 'anthropic', 'claude', []);
    $verdict = app(DecideSendOrDraft::class)->decide($run, READING_REQUIRED_CUSTOMER_TEXT, $words, $reading);

    expect($reading->missing)->toBeTrue()
        ->and($verdict->outcome->value)->toBe('hold');

    $holding = fn () => readingRequiredSendHolding($conversation, readingRequiredFixedGate($reading));
    $agentReply = fn () => app(OutboundSender::class)->send($conversation, $words, MessageAuthor::Agent, SendOrigin::Automated, [
        'kind' => MessageKind::AgentReply,
        ...$verdict->toAttributes($run->reply->label),
    ]);

    expect($holding)->toThrow(DomainException::class, OutboundSender::NO_READING)
        ->and($agentReply)->toThrow(DomainException::class, OutboundSender::NO_READING);

    expect(readingRequiredAlerts(AlertReason::SafetyCheckMissing))->toHaveCount(1);

    $alert = readingRequiredAlerts(AlertReason::SafetyCheckMissing)->first();

    expect($alert->urgent)->toBeTrue()
        ->and($alert->sent_at)->not->toBeNull()
        ->and(readingRequiredAlerts(AlertReason::FrontDeskFailed))->toHaveCount(0)
        ->and(Message::query()->whereIn('kind', [MessageKind::AgentReply->value, MessageKind::CodeAnswer->value])->count())->toBe(0)
        ->and(readingRequiredTextsToDana())->toBe([])
        ->and(SafetyCheckOutage::query()->count())->toBe(0)
        ->and(app(SafetyCheckStatus::class)->isDown())->toBeFalse()
        ->and(readingRequiredAlerts(AlertReason::SafetyCheckDown))->toHaveCount(0);
});

it('raises exactly one safety_check_missing alert naming that no business folder is live, and sends nothing a model wrote, when a customer texts with no folder live (Ian, 2026-10-16)', function () {
    test()->travelTo(Carbon::parse('2026-10-14 10:00', 'America/Chicago'));
    User::factory()->withPhone(READING_REQUIRED_OWNER_PHONE)->create();
    scriptFrontDeskReplies(['reply' => 'Thanks, what is the AC doing?', 'label' => 'intake_question']);

    test()->post(route('webhooks.text.fake'), fakeTextPayload(['Body' => READING_REQUIRED_CUSTOMER_TEXT]))->assertOk();

    runQueuedFrontDeskJobs();

    expect(readingRequiredAlerts(AlertReason::SafetyCheckMissing))->toHaveCount(1);

    $alert = readingRequiredAlerts(AlertReason::SafetyCheckMissing)->first();

    expect($alert->urgent)->toBeTrue()
        ->and($alert->sent_at)->not->toBeNull()
        ->and(readingRequiredDueAt($alert))->toBe('2026-10-14 10:00')
        ->and($alert->body)->toContain('no business folder is live')
        ->and($alert->body)->toContain(READING_REQUIRED_CUSTOMER_TEXT)
        ->and(readingRequiredTextsToDana())->toBe([])
        ->and(Message::query()->where('direction', MessageDirection::Out)->count())->toBe(0)
        ->and(fakeClassifier()->allCalls())->toBe([])
        ->and(SafetyCheckOutage::query()->count())->toBe(0)
        ->and(app(SafetyCheckStatus::class)->isDown())->toBeFalse()
        ->and(readingRequiredAlerts(AlertReason::SafetyCheckDown))->toHaveCount(0)
        ->and(readingRequiredAlerts(AlertReason::FrontDeskFailed))->toHaveCount(0);
});

it('still raises the safety_check_missing alert when the conversation has no customer text to point at', function () {
    $conversation = Conversation::factory()->for(Contact::factory()->create(['phone_e164' => '+12025550177']))->create();
    User::factory()->withPhone(READING_REQUIRED_OWNER_PHONE)->create();
    activateExampleFolder();

    expect(fn () => readingRequiredSendHolding($conversation, readingRequiredFixedGate()))
        ->toThrow(DomainException::class, OutboundSender::NO_READING);

    expect(readingRequiredAlerts(AlertReason::SafetyCheckMissing))->toHaveCount(1);

    $alert = readingRequiredAlerts(AlertReason::SafetyCheckMissing)->first();

    expect($alert->message_id)->toBeNull()
        ->and($alert->conversation_id)->toBe($conversation->id);
});
