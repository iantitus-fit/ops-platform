<?php

use App\Actions\RaiseOwnerAlert;
use App\Channels\FakeTextChannel;
use App\Enums\ActivityType;
use App\Enums\AlertReason;
use App\Enums\MessageStatus;
use App\Enums\VersionStatus;
use App\Jobs\SendDueAlerts;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\IcmVersion;
use App\Models\Message;
use App\Models\OwnerAlert;
use App\Models\User;
use App\Records\CustomerCard;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;

/**
 * Dana Whitfield's open conversation with a draft waiting, with the clock set to the given
 * wall-clock time in the business zone. Tuesday 2026-10-13 10:00 is open; 20:00 is closed.
 *
 * @return array{0: Conversation, 1: Message}
 */
function timingTestConversation(string $at = '2026-10-13 10:00'): array
{
    activateExampleFolder();
    test()->travelTo(Carbon::parse($at, 'America/Chicago'));

    $contact = Contact::factory()->create(['name' => 'Dana Whitfield', 'phone_e164' => '+12025550123']);
    $conversation = Conversation::factory()->for($contact)->create();
    Message::factory()->for($conversation)->create(['body' => 'How much for a new unit?']);
    $draft = Message::factory()->for($conversation)->outbound()->create([
        'body' => 'Mike will text you a price soon.',
        'status' => MessageStatus::Draft,
    ]);

    return [$conversation, $draft];
}

/**
 * The texts the fake text channel has been asked to send, oldest first.
 *
 * @return list<array{to: string, body: string, provider_message_id: string}>
 */
function timingTestSentTexts(): array
{
    return app(FakeTextChannel::class)->sent();
}

/**
 * Run the minute sweep the way the scheduler would.
 */
function runDueAlertSweep(): void
{
    app()->call([new SendDueAlerts, 'handle']);
}

/**
 * A fresh RaiseOwnerAlert, so it reads the folder version that is live now rather than a
 * cached one.
 */
function freshRaiseOwnerAlert(): RaiseOwnerAlert
{
    app()->forgetScopedInstances();

    return app(RaiseOwnerAlert::class);
}

it('sends an alert at once while the shop is open', function () {
    [$conversation, $draft] = timingTestConversation('2026-10-13 10:00');
    User::factory()->withPhone()->create();

    freshRaiseOwnerAlert()->handle($conversation, AlertReason::DraftWaiting, $draft);

    $alert = OwnerAlert::query()->sole();

    expect(timingTestSentTexts())->toHaveCount(1)
        ->and($alert->sent_at)->not->toBeNull()
        ->and($alert->due_at->equalTo(now()))->toBeTrue();
});

it('holds a draft-waiting alert raised after hours until the shop opens, then sends it', function () {
    [$conversation, $draft] = timingTestConversation('2026-10-13 20:00');
    User::factory()->withPhone()->create();

    freshRaiseOwnerAlert()->handle($conversation, AlertReason::DraftWaiting, $draft);

    $alert = OwnerAlert::query()->sole();

    expect($alert->due_at->equalTo(Carbon::parse('2026-10-14 08:00', 'America/Chicago')))->toBeTrue()
        ->and($alert->due_at->setTimezone('America/Chicago')->format('Y-m-d H:i'))->toBe('2026-10-14 08:00')
        ->and($alert->urgent)->toBeFalse()
        ->and($alert->sent_at)->toBeNull()
        ->and(timingTestSentTexts())->toBeEmpty();

    $this->travelTo(Carbon::parse('2026-10-14 07:59', 'America/Chicago'));
    runDueAlertSweep();

    expect(timingTestSentTexts())->toBeEmpty()
        ->and($alert->fresh()->sent_at)->toBeNull();

    $this->travelTo(Carbon::parse('2026-10-14 08:00', 'America/Chicago'));
    runDueAlertSweep();

    expect(timingTestSentTexts())->toHaveCount(1)
        ->and($alert->fresh()->sent_at)->not->toBeNull();

    runDueAlertSweep();

    expect(timingTestSentTexts())->toHaveCount(1);
});

it('sends a right-away alert at once even when the shop is closed', function (AlertReason $reason) {
    [$conversation, $draft] = timingTestConversation('2026-10-13 20:00');
    User::factory()->withPhone()->create();

    freshRaiseOwnerAlert()->handle($conversation, $reason, $draft);

    $alert = OwnerAlert::query()->sole();

    expect(timingTestSentTexts())->toHaveCount(1)
        ->and($alert->urgent)->toBeTrue()
        ->and($alert->sent_at)->not->toBeNull()
        ->and($alert->due_at->equalTo(now()))->toBeTrue();
})->with([
    'complaint' => AlertReason::Complaint,
    'asked for a person' => AlertReason::AskedForPerson,
    'life safety' => AlertReason::LifeSafety,
]);

it('always treats life safety as urgent, even when the stored folder leaves it off right_away', function () {
    [$conversation, $draft] = timingTestConversation('2026-10-13 20:00');
    User::factory()->withPhone()->create();

    $version = IcmVersion::query()->where('status', VersionStatus::Active->value)->latest('id')->firstOrFail();
    $compiled = $version->compiled;
    $compiled['files']['rules/sending.md']['front_matter']['owner_alert']['right_away'] = ['complaint'];
    $version->update(['compiled' => $compiled]);

    $raise = freshRaiseOwnerAlert();

    expect($raise->isUrgent(AlertReason::LifeSafety))->toBeTrue()
        ->and($raise->isUrgent(AlertReason::DraftWaiting))->toBeFalse();

    $raise->handle($conversation, AlertReason::LifeSafety, $draft);

    $alert = OwnerAlert::query()->sole();

    expect($alert->urgent)->toBeTrue()
        ->and($alert->sent_at)->not->toBeNull()
        ->and(timingTestSentTexts())->toHaveCount(1);
});

it('sends at once after hours when the folder says after_hours: right_away', function () {
    [$conversation, $draft] = timingTestConversation('2026-10-13 20:00');
    User::factory()->withPhone()->create();

    breakFile('rules/sending.md', fn (string $text) => str_replace('after_hours: next_opening', 'after_hours: right_away', $text));
    activateExampleFolder();

    freshRaiseOwnerAlert()->handle($conversation, AlertReason::DraftWaiting, $draft);

    expect(timingTestSentTexts())->toHaveCount(1)
        ->and(OwnerAlert::query()->sole()->sent_at)->not->toBeNull();
});

it('skips a delayed alert whose draft was handled before it fell due', function () {
    [$conversation, $draft] = timingTestConversation('2026-10-13 20:00');
    User::factory()->withPhone()->create();

    freshRaiseOwnerAlert()->handle($conversation, AlertReason::DraftWaiting, $draft);

    $draft->update(['status' => MessageStatus::Sent]);

    $this->travelTo(Carbon::parse('2026-10-14 08:00', 'America/Chicago'));
    runDueAlertSweep();

    $alert = OwnerAlert::query()->sole();

    expect($alert->skipped_at)->not->toBeNull()
        ->and($alert->failure)->toBe('Handled before it was due')
        ->and($alert->sent_at)->toBeNull()
        ->and(timingTestSentTexts())->toBeEmpty();
});

it('sends a delayed alert that is not about a draft even when it falls due later', function () {
    [$conversation, $draft] = timingTestConversation('2026-10-13 20:00');
    User::factory()->withPhone()->create();

    OwnerAlert::factory()->for($conversation)->create([
        'message_id' => $draft->id,
        'reason' => AlertReason::PausedMessage,
        'to_phone' => '+12025550199',
        'due_at' => Carbon::parse('2026-10-14 08:00', 'America/Chicago'),
    ]);
    $draft->update(['status' => MessageStatus::Sent]);

    $this->travelTo(Carbon::parse('2026-10-14 08:00', 'America/Chicago'));
    runDueAlertSweep();

    expect(timingTestSentTexts())->toHaveCount(1);
});

it('is on the scheduler every minute without overlapping', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn (Event $event) => str_contains($event->description ?? '', 'SendDueAlerts'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('* * * * *')
        ->and($event->withoutOverlapping)->toBeTrue();
});

it('says complaint is urgent and draft waiting is not with the example folder', function () {
    timingTestConversation();

    $raise = freshRaiseOwnerAlert();

    expect($raise->isUrgent(AlertReason::Complaint))->toBeTrue()
        ->and($raise->isUrgent(AlertReason::DraftWaiting))->toBeFalse();
});

it('puts a sent alert on the customer timeline and shows it on the card', function () {
    [$conversation, $draft] = timingTestConversation();
    User::factory()->withPhone()->create();

    freshRaiseOwnerAlert()->handle($conversation, AlertReason::DraftWaiting, $draft);

    $alert = OwnerAlert::query()->sole();
    $activity = Activity::query()->where('type', ActivityType::OwnerAlerted)->sole();

    expect($activity->contact_id)->toBe($conversation->contact_id)
        ->and($activity->summary)->toBe('Owner alerted by text: Draft waiting')
        ->and($activity->subject_type)->toBe('owner_alert')
        ->and($activity->subject_id)->toBe($alert->id)
        ->and($activity->occurred_at->equalTo(now()))->toBeTrue();

    $card = app(CustomerCard::class)->render($conversation->contact);

    expect($card)->toContain('· Owner alert')
        ->and($card)->toContain('> Owner alerted by text: Draft waiting');
});

it('puts an alert that could not be sent on the timeline too', function () {
    [$conversation, $draft] = timingTestConversation();
    User::factory()->create();

    freshRaiseOwnerAlert()->handle($conversation, AlertReason::DraftWaiting, $draft);

    $activity = Activity::query()->where('type', ActivityType::OwnerAlerted)->sole();

    expect($activity->summary)->toBe('Owner alert not sent: No phone on file');

    $card = app(CustomerCard::class)->render($conversation->contact);

    expect($card)->toContain('· Owner alert')
        ->and($card)->toContain('> Owner alert not sent: No phone on file');
});

it('puts a skipped alert on the timeline', function () {
    [$conversation, $draft] = timingTestConversation('2026-10-13 20:00');
    User::factory()->withPhone()->create();

    freshRaiseOwnerAlert()->handle($conversation, AlertReason::DraftWaiting, $draft);
    $draft->update(['status' => MessageStatus::Sent]);

    $this->travelTo(Carbon::parse('2026-10-14 08:00', 'America/Chicago'));
    runDueAlertSweep();

    expect(Activity::query()->where('type', ActivityType::OwnerAlerted)->sole()->summary)
        ->toBe('Owner alert not sent: Handled before it was due');
});

it('sends at once, after hours, when the live version owner_alert is the Phase 1 free text', function () {
    [$conversation, $draft] = timingTestConversation('2026-10-13 20:00');
    User::factory()->withPhone()->create();

    $version = IcmVersion::query()->where('status', VersionStatus::Active->value)->latest('id')->firstOrFail();
    $compiled = $version->compiled;
    $compiled['files']['rules/sending.md']['front_matter']['owner_alert'] = 'Text the owner when a draft is waiting or a customer asks for a person';
    $version->update(['compiled' => $compiled]);

    freshRaiseOwnerAlert()->handle($conversation, AlertReason::DraftWaiting, $draft);

    $alert = OwnerAlert::query()->sole();

    expect($alert->urgent)->toBeTrue()
        ->and($alert->due_at->equalTo(now()))->toBeTrue()
        ->and($alert->sent_at)->not->toBeNull()
        ->and(timingTestSentTexts())->toHaveCount(1);
});

it('sends at once and does not throw when no folder version is live', function () {
    [$conversation, $draft] = timingTestConversation('2026-10-13 20:00');
    User::factory()->withPhone()->create();

    IcmVersion::query()->where('status', VersionStatus::Active->value)->update(['status' => VersionStatus::Superseded->value]);

    freshRaiseOwnerAlert()->handle($conversation, AlertReason::FrontDeskFailed, $draft);

    $alert = OwnerAlert::query()->sole();

    expect($alert->urgent)->toBeTrue()
        ->and($alert->sent_at)->not->toBeNull()
        ->and(timingTestSentTexts())->toHaveCount(1);
});

it('puts a check-in on the right-away list in the example folder', function () {
    activateExampleFolder();

    expect(freshRaiseOwnerAlert()->isUrgent(AlertReason::CheckIn))->toBeTrue();
});

it('lets the owner move a check-in off right_away so an after-hours check-in waits for opening', function () {
    [$conversation, $draft] = timingTestConversation('2026-10-13 18:00');
    User::factory()->withPhone()->create();

    breakFile('rules/sending.md', fn (string $text) => str_replace(', check_in]', ']', $text));
    activateExampleFolder();

    $raise = freshRaiseOwnerAlert();

    expect($raise->isUrgent(AlertReason::CheckIn))->toBeFalse();

    $raise->handle($conversation, AlertReason::CheckIn, $draft);

    $alert = OwnerAlert::query()->sole();

    expect($alert->urgent)->toBeFalse()
        ->and($alert->due_at->setTimezone('America/Chicago')->format('Y-m-d H:i'))->toBe('2026-10-14 08:00')
        ->and($alert->sent_at)->toBeNull()
        ->and(timingTestSentTexts())->toBeEmpty();
});

it('names a check-in alert in plain words', function () {
    expect(AlertReason::CheckIn->plainWords())->toBe('Customer checked in')
        ->and(AlertReason::values())->toContain('check_in');
});
