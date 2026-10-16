<?php

use App\Actions\OwnerAlertText;
use App\Actions\RaiseOwnerAlert;
use App\Actions\SendOwnerAlert;
use App\Channels\FakeTextChannel;
use App\Enums\ActivityType;
use App\Enums\AlertReason;
use App\Filament\Pages\BusinessFolder;
use App\Icm\ActiveConfig;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\OwnerAlert;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Exceptions;
use Livewire\Livewire;

/**
 * The words a business alert carries in these tests.
 */
const BUSINESS_ALERT_WHY = 'The safety check is not answering, so replies are held for you.';

/**
 * The live example folder with the clock at Wednesday 2026-10-14 02:00 in the business zone,
 * when the shop is closed and the folder holds ordinary alerts until the next opening.
 */
function businessAlertClosedHours(): void
{
    activateExampleFolder();
    test()->travelTo(Carbon::parse('2026-10-14 02:00', 'America/Chicago'));
}

/**
 * A fresh RaiseOwnerAlert, so it reads the folder version that is live now rather than a cached one.
 */
function businessAlertRaiser(): RaiseOwnerAlert
{
    app()->forgetScopedInstances();

    return app(RaiseOwnerAlert::class);
}

/**
 * The texts the fake text channel has been asked to send, oldest first.
 *
 * @return list<array{to: string, body: string, provider_message_id: string}>
 */
function businessAlertSentTexts(): array
{
    return app(FakeTextChannel::class)->sent();
}

it('tells every person with a phone about the business at 2 am, with no conversation', function () {
    businessAlertClosedHours();
    User::factory()->withPhone('+12025550191')->create();
    User::factory()->withPhone('+12025550192')->create();

    $alerts = businessAlertRaiser()->forBusiness(AlertReason::SafetyCheckDown, BUSINESS_ALERT_WHY);

    expect($alerts)->toHaveCount(2)
        ->and(OwnerAlert::query()->count())->toBe(2);

    foreach (OwnerAlert::query()->orderBy('id')->get() as $alert) {
        expect($alert->conversation_id)->toBeNull()
            ->and($alert->message_id)->toBeNull()
            ->and($alert->reason)->toBe(AlertReason::SafetyCheckDown)
            ->and($alert->urgent)->toBeTrue()
            ->and($alert->due_at->equalTo(now()))->toBeTrue()
            ->and($alert->sent_at)->not->toBeNull();
    }

    $texts = businessAlertSentTexts();

    expect($texts)->toHaveCount(2)
        ->and(array_column($texts, 'to'))->toBe(['+12025550191', '+12025550192']);

    foreach ($texts as $text) {
        expect($text['body'])->toStartWith('Safety check is down: '.BUSINESS_ALERT_WHY)
            ->and(str($text['body'])->afterLast("\n")->toString())->toBe(Filament::getPanel('admin')->getUrl());
    }
});

it('writes the reason, the why, a blank line and then the dashboard link, and nothing a customer wrote', function () {
    businessAlertClosedHours();

    $body = app(OwnerAlertText::class)->forBusiness(AlertReason::SafetyCheckDown, BUSINESS_ALERT_WHY);

    expect($body)->toBe(
        'Safety check is down: '.BUSINESS_ALERT_WHY."\n\n".Filament::getPanel('admin')->getUrl()
    );
});

it('names safety_check_down and counts it as urgent with the example folder', function () {
    businessAlertClosedHours();

    expect(AlertReason::SafetyCheckDown->value)->toBe('safety_check_down')
        ->and(AlertReason::SafetyCheckDown->plainWords())->toBe('Safety check is down')
        ->and(businessAlertRaiser()->isUrgent(AlertReason::SafetyCheckDown))->toBeTrue();
});

it('writes no customer timeline entry and reports no error when a business alert is sent', function () {
    businessAlertClosedHours();
    User::factory()->withPhone()->create();
    Exceptions::fake();

    businessAlertRaiser()->forBusiness(AlertReason::SafetyCheckDown, BUSINESS_ALERT_WHY);

    Exceptions::assertNothingReported();

    expect(OwnerAlert::query()->sole()->sent_at)->not->toBeNull()
        ->and(Activity::query()->count())->toBe(0);
});

it('writes no customer timeline entry and reports no error when a business alert is skipped', function () {
    businessAlertClosedHours();
    Exceptions::fake();

    $alert = OwnerAlert::factory()->create([
        'conversation_id' => null,
        'reason' => AlertReason::SafetyCheckDown,
    ]);

    app(SendOwnerAlert::class)->skip($alert, 'Handled before it was due');

    Exceptions::assertNothingReported();

    expect($alert->fresh()->skipped_at)->not->toBeNull()
        ->and(Activity::query()->count())->toBe(0);
});

it('stores one failed alert when nobody has a phone and shows it on the Business Folder page', function () {
    businessAlertClosedHours();
    User::factory()->create();
    Exceptions::fake();

    $alerts = businessAlertRaiser()->forBusiness(AlertReason::SafetyCheckDown, BUSINESS_ALERT_WHY);

    Exceptions::assertNothingReported();

    $alert = OwnerAlert::query()->sole();

    expect($alerts)->toHaveCount(1)
        ->and($alert->user_id)->toBeNull()
        ->and($alert->to_phone)->toBeNull()
        ->and($alert->conversation_id)->toBeNull()
        ->and($alert->failed_at)->not->toBeNull()
        ->and($alert->failure)->toBe('No phone on file')
        ->and($alert->sent_at)->toBeNull()
        ->and(businessAlertSentTexts())->toBeEmpty()
        ->and(Activity::query()->count())->toBe(0);

    $this->actingAs(User::factory()->create());

    Livewire::test(BusinessFolder::class)
        ->assertSee('The last alert was not sent: No phone on file');
});

it('still puts a conversation alert on the customer timeline', function () {
    businessAlertClosedHours();
    User::factory()->withPhone()->create();

    $conversation = Conversation::factory()->for(Contact::factory()->create(['name' => 'Dana Whitfield']))->create();

    businessAlertRaiser()->handle($conversation, AlertReason::Complaint);

    $activity = Activity::query()->where('type', ActivityType::OwnerAlerted)->sole();

    expect($activity->contact_id)->toBe($conversation->contact_id)
        ->and($activity->summary)->toBe('Owner alerted by text: Complaint');
});

it('syncs a folder that lists safety_check_down under right_away', function () {
    breakFile('rules/sending.md', fn (string $text) => str_replace('right_away: [complaint,', 'right_away: [safety_check_down, complaint,', $text));

    activateExampleFolder();

    expect(app(ActiveConfig::class)->sendingRules()['owner_alert']['right_away'])->toContain('safety_check_down');
});

it('still sends a business alert at once at 2 am when the folder leaves safety_check_down off right_away', function () {
    businessAlertClosedHours();
    User::factory()->withPhone()->create();

    $raise = businessAlertRaiser();

    expect(app(ActiveConfig::class)->sendingRules()['owner_alert']['right_away'])->not->toContain('safety_check_down')
        ->and(app(ActiveConfig::class)->sendingRules()['owner_alert']['after_hours'])->toBe('next_opening')
        ->and($raise->isUrgent(AlertReason::SafetyCheckDown))->toBeTrue()
        ->and($raise->isUrgent(AlertReason::DraftWaiting))->toBeFalse();

    $raise->forBusiness(AlertReason::SafetyCheckDown, BUSINESS_ALERT_WHY);

    $alert = OwnerAlert::query()->sole();

    expect($alert->urgent)->toBeTrue()
        ->and($alert->due_at->equalTo(now()))->toBeTrue()
        ->and($alert->sent_at)->not->toBeNull()
        ->and(businessAlertSentTexts())->toHaveCount(1);
});
