<?php

use App\Actions\RaiseOwnerAlert;
use App\Enums\AlertReason;
use App\Icm\ActiveConfig;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\OwnerAlert;
use App\Models\User;
use Illuminate\Support\Carbon;

const BOOKING_ALERT_VISIT = 'AC repair, Thursday, November 5 at 10:00 AM';

/**
 * Dana's conversation: her old text about warm air, then a later outbound agent message M that stands for the text
 * that carried the booking link, with the clock set to the given time in the business zone and the owner on file.
 *
 * @return array{0: Conversation, 1: Message}
 */
function bookingAlertConversation(string $at = '2026-11-03 11:00'): array
{
    activateExampleFolder();
    test()->travelTo(Carbon::parse($at, 'America/Chicago')->utc());

    User::factory()->withPhone('+12025550199')->create();
    $contact = Contact::factory()->create(['name' => 'Dana Whitfield', 'phone_e164' => '+12025550123']);
    $conversation = Conversation::factory()->for($contact)->create();
    Message::factory()->for($conversation)->create(['body' => 'My AC is blowing warm air', 'created_at' => now()->subDays(3)]);
    $link = Message::factory()->for($conversation)->outbound()->create(['body' => 'Here is the link to book.']);

    return [$conversation, $link];
}

/**
 * A fresh RaiseOwnerAlert, so it reads the folder and the clock as they are now.
 */
function bookingAlertRaise(): RaiseOwnerAlert
{
    app()->forgetScopedInstances();

    return app(RaiseOwnerAlert::class);
}

it('words the booked alert by the visit and quotes no customer text (D-33)', function () {
    [$conversation, $link] = bookingAlertConversation();

    bookingAlertRaise()->handle($conversation, AlertReason::AppointmentBooked, $link, BOOKING_ALERT_VISIT);

    $alerts = OwnerAlert::query()->get();

    expect($alerts)->toHaveCount(1)
        ->and($alerts[0]->urgent)->toBeFalse()
        ->and($alerts[0]->due_at->toIso8601String())->toBe(now()->toIso8601String())
        ->and($alerts[0]->message_id)->toBe($link->id)
        ->and($alerts[0]->body)->toContain(BOOKING_ALERT_VISIT)
        ->and($alerts[0]->body)->not->toContain('They wrote')
        ->and($alerts[0]->body)->not->toContain('warm air')
        ->and(AlertReason::AppointmentBooked->plainWords())->toBe('Visit booked');
});

it('waits for the next opening when the visit is booked after hours (D-33)', function () {
    [$conversation, $link] = bookingAlertConversation('2026-11-03 22:00');

    bookingAlertRaise()->handle($conversation, AlertReason::AppointmentBooked, $link, BOOKING_ALERT_VISIT);

    $alert = OwnerAlert::query()->sole();

    expect($alert->sent_at)->toBeNull()
        ->and($alert->due_at->setTimezone('America/Chicago')->format('Y-m-d H:i'))->toBe('2026-11-04 08:00');
});

it('stores one booked alert per key and a second for another message (D-33, 02.1 IN-11)', function () {
    [$conversation, $link] = bookingAlertConversation();
    $later = Message::factory()->for($conversation)->outbound()->create(['body' => 'Here is the link to book again.']);
    $owner = User::query()->sole();

    bookingAlertRaise()->handle($conversation, AlertReason::AppointmentBooked, $link, BOOKING_ALERT_VISIT);
    bookingAlertRaise()->handle($conversation, AlertReason::AppointmentBooked, $link, BOOKING_ALERT_VISIT);

    expect(OwnerAlert::query()->count())->toBe(1)
        ->and(OwnerAlert::query()->sole()->dedupe_key)->toBe("c{$conversation->id}:m{$link->id}:appointment_booked:u{$owner->id}");

    bookingAlertRaise()->handle($conversation, AlertReason::AppointmentBooked, $later, BOOKING_ALERT_VISIT);

    expect(OwnerAlert::query()->count())->toBe(2);
});

it('keeps the change request\'s quote of the customer (D-34)', function () {
    [$conversation, $link] = bookingAlertConversation();
    $inbound = Message::factory()->for($conversation)->create(['body' => 'Can you come Wednesday instead?']);

    bookingAlertRaise()->handle($conversation, AlertReason::ChangeRequest, $inbound, 'Asked about the booked visit: '.BOOKING_ALERT_VISIT);

    expect(OwnerAlert::query()->sole()->body)->toContain('They wrote: "Can you come Wednesday instead?"');
});

it('accepts both new reasons under right_away (D-33, D-34)', function () {
    breakFile('rules/sending.md', fn (string $text): string => str_replace('reply_cap, change_request, check_in]', 'reply_cap, change_request, check_in, appointment_booked]', $text));

    activateExampleFolder();

    expect(app(ActiveConfig::class)->sendingRules()['owner_alert']['right_away'])->toContain('appointment_booked', 'change_request');
});
