<?php

use App\Ai\AgentReply;
use App\Ai\AgentRun;
use App\Enums\AlertReason;
use App\Enums\MessageChannel;
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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Sleep;

const SAFETY_CHECK_OUTAGE_OWNER_PHONE = '+12025550199';

/**
 * The live example folder, the owner with a phone, and the clock at the given time in the shop's zone.
 */
function safetyCheckOutageBegins(string $at = '2026-10-14 02:00'): void
{
    activateExampleFolder();

    User::factory()->withPhone(SAFETY_CHECK_OUTAGE_OWNER_PHONE)->create();

    safetyCheckOutageAt($at);
}

/**
 * Move the clock to the given time in the shop's zone.
 */
function safetyCheckOutageAt(string $at): void
{
    test()->travelTo(Carbon::parse($at, 'America/Chicago'));
}

/**
 * A fresh status object, so it reads the folder version that is live now rather than a cached one.
 */
function safetyCheckOutageStatus(): SafetyCheckStatus
{
    app()->forgetScopedInstances();

    return app(SafetyCheckStatus::class);
}

/**
 * The owner's safety_check_down alerts, oldest first.
 *
 * @return Collection<int, OwnerAlert>
 */
function safetyCheckOutageAlerts(): Collection
{
    return OwnerAlert::query()->where('reason', AlertReason::SafetyCheckDown->value)->orderBy('id')->get();
}

/**
 * The alerts that said the safety check is not answering.
 *
 * @return Collection<int, OwnerAlert>
 */
function safetyCheckOutageDownAlerts(): Collection
{
    return safetyCheckOutageAlerts()->filter(fn (OwnerAlert $alert): bool => str_contains($alert->body, 'not answering'))->values();
}

/**
 * The alerts that said the safety check is back.
 *
 * @return Collection<int, OwnerAlert>
 */
function safetyCheckOutageBackAlerts(): Collection
{
    return safetyCheckOutageAlerts()->filter(fn (OwnerAlert $alert): bool => str_contains($alert->body, 'is back'))->values();
}

/**
 * A text from a customer that arrived at the given time, in a conversation of its own.
 */
function safetyCheckOutageCustomerText(string $at, array $state = []): Message
{
    return Message::factory()->for(Conversation::factory())->create([
        'created_at' => Carbon::parse($at, 'America/Chicago'),
        ...$state,
    ]);
}

it('tells the owner once that the safety check is back, counting the customer texts that came in at 02:10, 02:20 and 03:00', function () {
    safetyCheckOutageBegins('2026-10-14 02:00');
    safetyCheckOutageStatus()->recordFailure('Jev is down.');

    safetyCheckOutageCustomerText('2026-10-14 02:10');
    safetyCheckOutageCustomerText('2026-10-14 02:20');
    safetyCheckOutageCustomerText('2026-10-14 03:00');
    Message::factory()->for(Conversation::factory())->outbound()->create(['created_at' => Carbon::parse('2026-10-14 02:30', 'America/Chicago')]);
    safetyCheckOutageCustomerText('2026-10-14 02:40', ['channel' => MessageChannel::Web]);

    safetyCheckOutageAt('2026-10-14 03:30');
    safetyCheckOutageStatus()->recordSuccess();

    $back = safetyCheckOutageBackAlerts();
    $outage = SafetyCheckOutage::query()->sole();

    expect($back)->toHaveCount(1)
        ->and($back->sole()->body)->toContain('back')
        ->and($back->sole()->body)->toContain('3 customer texts came in while it was down')
        ->and($back->sole()->sent_at)->not->toBeNull()
        ->and($outage->ended_at->setTimezone('America/Chicago')->format('H:i'))->toBe('03:30')
        ->and($outage->up_alerted_at)->not->toBeNull();

    safetyCheckOutageStatus()->recordSuccess();

    expect(safetyCheckOutageAlerts())->toHaveCount(2);
});

it('says one customer text came in, not texts', function () {
    safetyCheckOutageBegins('2026-10-14 02:00');
    safetyCheckOutageStatus()->recordFailure('Jev is down.');
    safetyCheckOutageCustomerText('2026-10-14 02:10');

    safetyCheckOutageAt('2026-10-14 02:20');
    safetyCheckOutageStatus()->recordSuccess();

    $back = safetyCheckOutageBackAlerts();

    expect($back)->toHaveCount(1)
        ->and($back->first()->body)->toContain('1 customer text came in while it was down');
});

it('stops a flapping safety check from texting the owner more than one down alert in 60 minutes (02:59 and 03:00)', function () {
    safetyCheckOutageBegins('2026-10-14 02:00');
    safetyCheckOutageStatus()->recordFailure('Jev is down.');

    safetyCheckOutageAt('2026-10-14 02:05');
    safetyCheckOutageStatus()->recordSuccess();

    safetyCheckOutageAt('2026-10-14 02:30');
    safetyCheckOutageStatus()->recordFailure('Jev is down again.');

    expect(safetyCheckOutageDownAlerts())->toHaveCount(1);

    safetyCheckOutageAt('2026-10-14 02:59');
    safetyCheckOutageStatus()->recordFailure('Jev is still down.');

    expect(safetyCheckOutageDownAlerts())->toHaveCount(1)
        ->and(SafetyCheckOutage::query()->count())->toBe(2)
        ->and(SafetyCheckOutage::query()->whereNull('ended_at')->sole()->down_alerted_at)->toBeNull();

    safetyCheckOutageAt('2026-10-14 03:00');
    safetyCheckOutageStatus()->recordFailure('Jev is still down.');

    expect(safetyCheckOutageDownAlerts())->toHaveCount(2)
        ->and(SafetyCheckOutage::query()->whereNull('ended_at')->sole()->down_alerted_at)->not->toBeNull();

    safetyCheckOutageAt('2026-10-14 03:10');
    safetyCheckOutageStatus()->recordSuccess();

    expect(safetyCheckOutageBackAlerts())->toHaveCount(2)
        ->and(SafetyCheckOutage::query()->orderByDesc('id')->first()->up_alerted_at)->not->toBeNull();
});

it('sends no back-up alert for an outage whose down alert was held back and that ends first', function () {
    safetyCheckOutageBegins('2026-10-14 02:00');
    safetyCheckOutageStatus()->recordFailure('Jev is down.');

    safetyCheckOutageAt('2026-10-14 02:05');
    safetyCheckOutageStatus()->recordSuccess();

    safetyCheckOutageAt('2026-10-14 02:30');
    safetyCheckOutageStatus()->recordFailure('Jev is down again.');

    safetyCheckOutageAt('2026-10-14 02:40');
    safetyCheckOutageStatus()->recordSuccess();

    $second = SafetyCheckOutage::query()->orderByDesc('id')->first();

    expect(safetyCheckOutageAlerts())->toHaveCount(2)
        ->and(safetyCheckOutageBackAlerts())->toHaveCount(1)
        ->and($second->ended_at)->not->toBeNull()
        ->and($second->down_alerted_at)->toBeNull()
        ->and($second->up_alerted_at)->toBeNull();
});

it('opens one outage and raises one alert when two failures arrive back to back', function () {
    safetyCheckOutageBegins();

    safetyCheckOutageStatus()->recordFailure('Jev is down.');
    safetyCheckOutageStatus()->recordFailure('Jev is down.');

    expect(SafetyCheckOutage::query()->count())->toBe(1)
        ->and(safetyCheckOutageAlerts())->toHaveCount(1);
});

it('waits for a lock another holder has, then acts once', function () {
    safetyCheckOutageBegins();
    Sleep::fake(syncWithCarbon: true);
    Cache::lock('safety-check-status', 1)->get();

    safetyCheckOutageStatus()->recordFailure('Jev is down.');

    expect(SafetyCheckOutage::query()->count())->toBe(1)
        ->and(safetyCheckOutageAlerts())->toHaveCount(1);
});

it('reports and skips a status change when the lock cannot be had, and never throws', function () {
    safetyCheckOutageBegins();
    Sleep::fake(syncWithCarbon: true);
    Cache::lock('safety-check-status', 60)->get();

    expect(fn () => safetyCheckOutageStatus()->recordFailure('Jev is down.'))->not->toThrow(Throwable::class)
        ->and(SafetyCheckOutage::query()->count())->toBe(0)
        ->and(safetyCheckOutageAlerts())->toHaveCount(0);
});

/**
 * One agent reply for the words check to read.
 */
function safetyCheckOutageRun(): AgentRun
{
    return new AgentRun(AgentReply::fromStructured(['reply' => 'Thanks, what is the AC doing?', 'label' => 'intake_question', 'wants_person' => false]), 'anthropic', 'claude', []);
}

it('marks Jev down on a failed words check and up on a successful one', function () {
    safetyCheckOutageBegins();
    $decide = app(DecideSendOrDraft::class);
    $reading = CustomerReading::missing('Not read in this test.');

    fakeClassifier()->failWith('Jev is down.');
    $decide->decide(safetyCheckOutageRun(), 'My AC is blowing warm air', 'Thanks, what is the AC doing?', $reading);

    expect(safetyCheckOutageStatus()->isDown())->toBeTrue()
        ->and(safetyCheckOutageAlerts())->toHaveCount(1);

    fakeClassifier()->answer('faq');
    $decide->decide(safetyCheckOutageRun(), 'My AC is blowing warm air', 'Thanks, what is the AC doing?', $reading);

    expect(safetyCheckOutageStatus()->isDown())->toBeFalse();
});

it('still reads and checks every run while an outage is open, and the answers close it', function () {
    safetyCheckOutageBegins('2026-10-14 10:00');
    SafetyCheckOutage::factory()->create(['started_at' => now()->subMinutes(10), 'down_alerted_at' => now()->subMinutes(10)]);
    test()->post(route('webhooks.text.fake'), fakeTextPayload())->assertOk();
    scriptFrontDeskReplies(['reply' => 'Thanks, what is the system doing?', 'label' => 'intake_question']);

    runQueuedFrontDeskJobs();

    expect(fakeClassifier()->allCalls())->toHaveCount(2)
        ->and(fakeClassifier()->readingCalls())->toHaveCount(1)
        ->and(fakeClassifier()->calls())->toHaveCount(1)
        ->and(safetyCheckOutageStatus()->isDown())->toBeFalse();
});
