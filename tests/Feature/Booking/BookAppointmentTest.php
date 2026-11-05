<?php

use App\Actions\BookAppointment;
use App\Actions\OpenProject;
use App\Enums\ActivityType;
use App\Enums\AppointmentActor;
use App\Enums\AppointmentState;
use App\Models\Appointment;
use App\Models\Contact;
use App\Models\Message;
use App\Models\Project;
use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->owner = User::factory()->create(['name' => 'Ian Owner']);
    $this->actingAs($this->owner);
});

/**
 * A moment on the example business's wall clock (America/Chicago), as the owner types it.
 */
function bookByHandChicago(string $dateTime): CarbonImmutable
{
    return CarbonImmutable::parse($dateTime, 'America/Chicago');
}

/**
 * The live example folder, the clock at Tuesday 2026-11-03 11:00 in the shop's zone, and Dana Whitfield, who texted and
 * has one open job.
 *
 * @return array{0: Contact, 1: Project}
 */
function bookByHandSetUp(): array
{
    activateExampleFolder();
    test()->travelTo(bookByHandChicago('2026-11-03 11:00'));

    $contact = Contact::factory()->create(['name' => 'Dana Whitfield']);
    $conversation = $contact->conversations()->create();
    Message::factory()->create(['conversation_id' => $conversation->id]);
    $job = app(OpenProject::class)->byHand($contact, test()->owner);

    return [$contact, $job];
}

it('books a visit by hand on the open job', function () {
    [$contact, $job] = bookByHandSetUp();

    $appointment = app(BookAppointment::class)->byHand(
        $contact,
        $this->owner,
        'ac-repair',
        bookByHandChicago('2026-11-05 10:00'),
        $job->id,
        null,
    );

    expect($appointment->project_id)->toBe($job->id)
        ->and($appointment->contact_id)->toBe($contact->id)
        ->and($appointment->starts_at->utc()->format('Y-m-d H:i'))->toBe('2026-11-05 16:00')
        ->and($appointment->ends_at->utc()->format('Y-m-d H:i'))->toBe('2026-11-05 17:30')
        ->and($appointment->service_slug)->toBe('ac-repair')
        ->and($appointment->service_name)->toBe('AC repair')
        ->and($appointment->state)->toBe(AppointmentState::Booked)
        ->and($appointment->booked_by)->toBe(AppointmentActor::User)
        ->and($appointment->booked_by_user_id)->toBe($this->owner->id);

    $entry = $contact->activities()->where('type', ActivityType::RoomEntry->value)->sole();

    expect($entry->room)->toBe('schedule_and_booking')
        ->and($entry->entry)->toBe('visit_booked')
        ->and($entry->summary)->toBe('Booked by Ian Owner: AC repair, Thursday, November 5 at 10:00 AM.')
        ->and($entry->subject_id)->toBe($appointment->id);
});

it('lists the appointment on the job it was booked for', function () {
    [$contact, $job] = bookByHandSetUp();

    app(BookAppointment::class)->byHand($contact, $this->owner, 'ac-repair', bookByHandChicago('2026-11-05 10:00'), $job->id, null);

    expect(Appointment::query()->count())->toBe(1)
        ->and($job->appointments()->count())->toBe(1);
});
