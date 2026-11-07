<?php

use App\Actions\BookAppointment;
use App\Actions\CancelAppointment;
use App\Enums\ActivityType;
use App\Enums\AppointmentActor;
use App\Enums\AppointmentState;
use App\Jobs\SendDueBookingTexts;
use App\Models\Activity;
use App\Models\Appointment;
use App\Models\Contact;
use App\Models\Message;
use App\Models\ScheduleBlock;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * The owner's actions on a booked visit: cancel, mark done, keep a note (D-14, D-15, D-16, D-18, D-24). Each one is a
 * named action that records who acted on the row and writes exactly one timeline entry.
 */
beforeEach(function () {
    $this->owner = User::factory()->create(['name' => 'Ian Owner']);
    $this->actingAs($this->owner);
});

/**
 * A moment on the example business's wall clock (America/Chicago).
 */
function appointmentActionsChicago(string $dateTime): CarbonImmutable
{
    return CarbonImmutable::parse($dateTime, 'America/Chicago');
}

/**
 * The live example folder, the clock at Tuesday 2026-11-03 11:00 in the shop's zone, Dana Whitfield, who texted, and her
 * AC repair booked by hand for Thursday 2026-11-05 at 10:00. Its confirmation went out at booking and its 24-hour
 * reminder is pending, due Wednesday at 10:00.
 *
 * @return array{0: Contact, 1: Appointment}
 */
function appointmentActionsSetUp(): array
{
    activateExampleFolder();
    test()->travelTo(appointmentActionsChicago('2026-11-03 11:00'));

    $contact = Contact::factory()->create(['name' => 'Dana Whitfield']);
    $conversation = $contact->conversations()->create();
    Message::factory()->create(['conversation_id' => $conversation->id, 'handled_at' => now()]);

    $appointment = app(BookAppointment::class)->byHand(
        $contact,
        test()->owner,
        'ac-repair',
        appointmentActionsChicago('2026-11-05 10:00'),
        null,
        null,
    );

    return [$contact, $appointment];
}

/**
 * Run the minute sweep the way the scheduler would.
 */
function appointmentActionsSweep(): void
{
    app()->call([new SendDueBookingTexts, 'handle']);
}

/**
 * The summaries of the contact's timeline entries of one room entry kind.
 *
 * @return list<string>
 */
function appointmentActionsEntries(Contact $contact, string $entry): array
{
    return Activity::query()
        ->where('contact_id', $contact->id)
        ->where('type', ActivityType::RoomEntry->value)
        ->where('entry', $entry)
        ->orderBy('id')
        ->pluck('summary')
        ->all();
}

it('cancels a visit and ends its pending texts', function () {
    [$contact, $appointment] = appointmentActionsSetUp();
    $textsBefore = count(textsSentToCustomers());

    app(CancelAppointment::class)->handle($appointment, $this->owner);

    $appointment->refresh();

    expect($appointment->state)->toBe(AppointmentState::Cancelled)
        ->and($appointment->cancelled_by)->toBe(AppointmentActor::User)
        ->and($appointment->cancelled_by_user_id)->toBe($this->owner->id)
        ->and($appointment->cancelled_at)->not->toBeNull()
        ->and(appointmentActionsEntries($contact, 'visit_cancelled'))->toBe(['Cancelled by Ian Owner: AC repair, Thursday, November 5 at 10:00 AM.'])
        ->and(count(textsSentToCustomers()))->toBe($textsBefore);

    $reminder = $appointment->sends()->where('lead_minutes', 1440)->sole();

    expect($reminder->skipped_at)->not->toBeNull()
        ->and($reminder->failure)->toBe('The visit was cancelled.')
        ->and($reminder->sent_at)->toBeNull();

    $this->travelTo(appointmentActionsChicago('2026-11-04 10:00'));
    appointmentActionsSweep();

    expect(count(textsSentToCustomers()))->toBe($textsBefore);
});

it('cancels once', function () {
    [$contact, $appointment] = appointmentActionsSetUp();

    app(CancelAppointment::class)->handle($appointment, $this->owner);

    $cancelledAt = $appointment->fresh()->cancelled_at;
    $other = User::factory()->create(['name' => 'Second Owner']);

    $this->travelTo(appointmentActionsChicago('2026-11-03 12:00'));
    app(CancelAppointment::class)->handle($appointment, $other);

    $row = $appointment->fresh();

    expect($row->cancelled_by_user_id)->toBe($this->owner->id)
        ->and($row->cancelled_at->equalTo($cancelledAt))->toBeTrue()
        ->and(appointmentActionsEntries($contact, 'visit_cancelled'))->toHaveCount(1);
});

it('never cancels a block through the visit action', function () {
    appointmentActionsSetUp();
    $block = ScheduleBlock::factory()->at(appointmentActionsChicago('2026-11-05 13:00'), 120)->create();
    $hydrated = Appointment::query()->withoutGlobalScope('kind')->find($block->id);

    app(CancelAppointment::class)->handle($hydrated, $this->owner);

    expect(ScheduleBlock::query()->find($block->id)->state)->toBe(AppointmentState::Booked)
        ->and(Activity::query()->where('entry', 'visit_cancelled')->count())->toBe(0);
});
