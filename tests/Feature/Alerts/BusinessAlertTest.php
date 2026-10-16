<?php

use App\Actions\OwnerAlertText;
use App\Actions\RaiseOwnerAlert;
use App\Channels\FakeTextChannel;
use App\Enums\AlertReason;
use App\Models\OwnerAlert;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;

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
