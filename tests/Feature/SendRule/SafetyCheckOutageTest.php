<?php

use App\Ai\AgentReply;
use App\Ai\AgentRun;
use App\Channels\FakeTextChannel;
use App\Enums\AlertReason;
use App\Enums\MessageChannel;
use App\Jobs\PingSafetyCheck;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\OwnerAlert;
use App\Models\SafetyCheckOutage;
use App\Models\User;
use App\SendRule\CustomerReading;
use App\SendRule\DecideSendOrDraft;
use App\SendRule\SafetyCheckStatus;
use Filament\Facades\Filament;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
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
 * A time in the shop's zone as the database stores it, in the app's own zone.
 */
function safetyCheckOutageStored(string $at): Carbon
{
    return Carbon::parse($at, 'America/Chicago')->setTimezone(config('app.timezone'));
}

/**
 * A text from a customer that arrived at the given time, in a conversation of its own.
 */
function safetyCheckOutageCustomerText(string $at, array $state = []): Message
{
    return Message::factory()->for(Conversation::factory())->create([
        'created_at' => safetyCheckOutageStored($at),
        ...$state,
    ]);
}

it('tells the owner once that the safety check is back, counting the customer texts that came in at 02:10, 02:20 and 03:00', function () {
    safetyCheckOutageBegins('2026-10-14 02:00');
    safetyCheckOutageStatus()->recordFailure('Jev is down.');

    safetyCheckOutageCustomerText('2026-10-14 02:10');
    safetyCheckOutageCustomerText('2026-10-14 02:20');
    safetyCheckOutageCustomerText('2026-10-14 03:00');
    Message::factory()->for(Conversation::factory())->outbound()->create(['created_at' => safetyCheckOutageStored('2026-10-14 02:30')]);
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

it('opens the back-up text with the back headline, never the down words, and ends with the dashboard link (WR-01)', function () {
    safetyCheckOutageBegins('2026-10-14 02:00');
    safetyCheckOutageStatus()->recordFailure('Jev is down.');

    safetyCheckOutageCustomerText('2026-10-14 02:10');
    safetyCheckOutageCustomerText('2026-10-14 02:20');
    safetyCheckOutageCustomerText('2026-10-14 03:00');

    safetyCheckOutageAt('2026-10-14 03:30');
    safetyCheckOutageStatus()->recordSuccess();

    $back = safetyCheckOutageBackAlerts()->sole();

    expect($back->body)->toStartWith('Safety check is back: ')
        ->and($back->body)->not->toContain('is down:')
        ->and($back->body)->toContain('3 customer texts came in while it was down')
        ->and($back->body)->toEndWith("\n\n".Filament::getPanel('admin')->getUrl())
        ->and($back->reason)->toBe(AlertReason::SafetyCheckDown)
        ->and($back->urgent)->toBeTrue();

    $toOwner = collect(app(FakeTextChannel::class)->sent())->where('to', SAFETY_CHECK_OUTAGE_OWNER_PHONE)->pluck('body');

    expect($toOwner->last())->toBe($back->body);
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
    return new AgentRun(AgentReply::fromStructured(['reply' => 'Thanks, what is the AC doing?', 'label' => 'intake_question', 'wants_person' => false, 'possible_danger' => false]), 'anthropic', 'claude', []);
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

/**
 * Run the health ping the way the scheduler would.
 */
function safetyCheckOutagePing(): void
{
    app()->forgetScopedInstances();

    app()->call([new PingSafetyCheck, 'handle']);
}

it('pings Jev with a fixed fictional text and an empty draft, and a successful ping ends an open outage', function () {
    safetyCheckOutageBegins('2026-10-14 02:00');
    safetyCheckOutageStatus()->recordFailure('Jev is down.');

    safetyCheckOutageAt('2026-10-14 02:05');
    safetyCheckOutagePing();

    $calls = fakeClassifier()->allCalls();

    expect($calls)->toHaveCount(1)
        ->and($calls[0]['customer_message'])->toBe(PingSafetyCheck::TEXT)
        ->and($calls[0]['draft_reply'])->toBe('')
        ->and(PingSafetyCheck::TEXT)->toBe('Hi, do you work on heat pumps?')
        ->and(safetyCheckOutageStatus()->isDown())->toBeFalse()
        ->and(safetyCheckOutageBackAlerts())->toHaveCount(1);
});

it('opens an outage and tells the owner at once when the ping fails on a night with no customer texts', function () {
    safetyCheckOutageBegins('2026-10-14 03:00');
    fakeClassifier()->failWith('Jev could not be reached or took too long to answer.');

    safetyCheckOutagePing();

    $down = safetyCheckOutageDownAlerts();

    expect($down)->toHaveCount(1)
        ->and($down->sole()->conversation_id)->toBeNull()
        ->and($down->sole()->sent_at)->not->toBeNull()
        ->and(SafetyCheckOutage::query()->sole()->down_alerted_at)->not->toBeNull();

    safetyCheckOutageAt('2026-10-14 03:05');
    safetyCheckOutagePing();

    expect(safetyCheckOutageAlerts())->toHaveCount(1)
        ->and(SafetyCheckOutage::query()->count())->toBe(1);
});

it('asks nothing and changes nothing when no business folder is live', function () {
    User::factory()->withPhone(SAFETY_CHECK_OUTAGE_OWNER_PHONE)->create();
    SafetyCheckOutage::factory()->create();

    expect(fn () => safetyCheckOutagePing())->not->toThrow(Throwable::class)
        ->and(fakeClassifier()->allCalls())->toBe([])
        ->and(SafetyCheckOutage::query()->count())->toBe(1)
        ->and(SafetyCheckOutage::query()->sole()->ended_at)->toBeNull()
        ->and(OwnerAlert::query()->count())->toBe(0);
});

it('is on the scheduler every five minutes without overlapping', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn (Event $event) => str_contains($event->description ?? '', 'PingSafetyCheck'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('*/5 * * * *')
        ->and($event->withoutOverlapping)->toBeTrue();
});
