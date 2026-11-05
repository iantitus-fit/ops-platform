<?php

use App\Actions\BookAppointment;
use App\Actions\CloseProject;
use App\Actions\OpenProject;
use App\Enums\ActivityType;
use App\Enums\AppointmentActor;
use App\Enums\AppointmentState;
use App\Models\Appointment;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\OwnerAlert;
use App\Models\Project;
use App\Models\ScheduleBlock;
use App\Models\User;
use App\Rooms\ScheduleAndBooking\BookingRefused;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Sleep;

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
 * has one open job. $changeFolder may change the folder before it goes live.
 *
 * @return array{0: Contact, 1: Project}
 */
function bookByHandSetUp(?Closure $changeFolder = null): array
{
    if ($changeFolder !== null) {
        $changeFolder();
    }

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

/**
 * Book AC repair by hand at a time on the shop's wall clock, on the given job.
 */
function bookByHandAt(Contact $contact, string $start, ?int $projectId, ?string $note = null, string $service = 'ac-repair'): Appointment
{
    return app(BookAppointment::class)->byHand($contact, test()->owner, $service, bookByHandChicago($start), $projectId, $note);
}

it('refuses a time that has passed and accepts one a minute ahead', function () {
    [$contact, $job] = bookByHandSetUp();

    expect(fn () => bookByHandAt($contact, '2026-11-03 11:00', $job->id))
        ->toThrow(BookingRefused::class, 'Pick a time that has not passed.')
        ->and(fn () => bookByHandAt($contact, '2026-11-03 10:59', $job->id))
        ->toThrow(BookingRefused::class, 'Pick a time that has not passed.')
        ->and(Appointment::query()->count())->toBe(0);

    expect(bookByHandAt($contact, '2026-11-03 11:01', $job->id)->starts_at->utc()->format('H:i'))->toBe('17:01');
});

it('names the field of a refusal', function () {
    [$contact, $job] = bookByHandSetUp();

    $refusal = null;

    try {
        bookByHandAt($contact, '2026-11-03 10:00', $job->id);
    } catch (BookingRefused $caught) {
        $refusal = $caught;
    }

    expect($refusal?->field)->toBe('starts_at');
});

it('refuses an overlap with a booked visit and its drive buffer, and accepts one that starts where the buffer ends', function () {
    [$contact, $job] = bookByHandSetUp();
    Appointment::factory()->at(bookByHandChicago('2026-11-05 10:00'), 90)->create();

    expect(fn () => bookByHandAt($contact, '2026-11-05 11:45', $job->id))
        ->toThrow(BookingRefused::class, 'That time is taken by another booking or blocked time. Pick another time.')
        ->and(Appointment::query()->count())->toBe(1);

    expect(bookByHandAt($contact, '2026-11-05 12:00', $job->id)->starts_at->utc()->format('H:i'))->toBe('18:00');
});

it('uses no drive buffer when there is no booking file', function () {
    [$contact, $job] = bookByHandSetUp(fn () => File::delete(icmPath('rooms/schedule_and_booking/booking.md')));
    Appointment::factory()->at(bookByHandChicago('2026-11-05 10:00'), 90)->create();

    expect(bookByHandAt($contact, '2026-11-05 11:30', $job->id)->starts_at->utc()->format('H:i'))->toBe('17:30');
});

it('refuses a time over a block', function () {
    [$contact, $job] = bookByHandSetUp();
    ScheduleBlock::factory()->at(bookByHandChicago('2026-11-05 13:00'), 120)->create();

    expect(fn () => bookByHandAt($contact, '2026-11-05 14:00', $job->id))
        ->toThrow(BookingRefused::class, 'That time is taken by another booking or blocked time. Pick another time.');
});

it('allows a time outside hours or inside the lead time', function () {
    [$contact, $job] = bookByHandSetUp();

    expect(bookByHandAt($contact, '2026-11-08 10:00', $job->id))->toBeInstanceOf(Appointment::class)
        ->and(bookByHandAt($contact, '2026-11-03 11:30', $job->id))->toBeInstanceOf(Appointment::class);
});

it('refuses while another booking holds the lock, and two overlapping bookings never both store', function () {
    [$contact, $job] = bookByHandSetUp();
    Sleep::fake(syncWithCarbon: true);

    $held = Cache::lock('schedule:book', 60);
    expect($held->get())->toBeTrue();

    expect(fn () => bookByHandAt($contact, '2026-11-05 10:00', $job->id))
        ->toThrow(BookingRefused::class, 'Another booking is being made. Try again.')
        ->and(Appointment::query()->count())->toBe(0);

    $held->release();

    bookByHandAt($contact, '2026-11-05 10:00', $job->id);

    expect(fn () => bookByHandAt($contact, '2026-11-05 10:30', $job->id))
        ->toThrow(BookingRefused::class, 'That time is taken by another booking or blocked time. Pick another time.')
        ->and(Appointment::query()->count())->toBe(1);
});

it('opens a new job when asked and never reopens a closed one', function () {
    [$contact, $job] = bookByHandSetUp();
    app(CloseProject::class)->handle($job, $this->owner);

    $appointment = bookByHandAt($contact, '2026-11-05 10:00', null);

    $second = Project::query()->whereKey($appointment->project_id)->sole();

    expect($appointment->project_id)->not->toBe($job->id)
        ->and($second->opened_by_user_id)->toBe($this->owner->id)
        ->and($second->conversation_id)->toBe($contact->openConversation()->value('id'))
        ->and($job->refresh()->isOpen())->toBeFalse();
});

it('opens a new job tied to no conversation for a contact who has none, and creates no conversation', function () {
    activateExampleFolder();
    $this->travelTo(bookByHandChicago('2026-11-03 11:00'));
    $contact = Contact::factory()->create();

    $appointment = bookByHandAt($contact, '2026-11-05 10:00', null);

    expect(Project::query()->whereKey($appointment->project_id)->sole()->conversation_id)->toBeNull()
        ->and(Conversation::query()->count())->toBe(0);
});

it('refuses a closed job and another customer\'s job', function () {
    [$contact, $job] = bookByHandSetUp();
    $other = app(OpenProject::class)->byHand(Contact::factory()->create(), $this->owner);

    expect(fn () => bookByHandAt($contact, '2026-11-05 10:00', $other->id))
        ->toThrow(BookingRefused::class, 'That job is closed. Pick an open job or a new one.');

    app(CloseProject::class)->handle($job, $this->owner);

    expect(fn () => bookByHandAt($contact, '2026-11-05 10:00', $job->id))
        ->toThrow(BookingRefused::class, 'That job is closed. Pick an open job or a new one.')
        ->and(Appointment::query()->count())->toBe(0)
        ->and($job->refresh()->isOpen())->toBeFalse();
});

it('leaves the contact\'s status alone and raises no owner alert', function () {
    [$contact, $job] = bookByHandSetUp();
    $status = $contact->status;

    bookByHandAt($contact, '2026-11-05 10:00', $job->id);

    expect($contact->refresh()->status)->toBe($status)
        ->and(OwnerAlert::query()->count())->toBe(0);
});

it('is refused with the room off', function () {
    [$contact, $job] = bookByHandSetUp(fn () => File::deleteDirectory(icmPath('rooms/schedule_and_booking')));

    expect(fn () => bookByHandAt($contact, '2026-11-05 10:00', $job->id))
        ->toThrow(BookingRefused::class, 'The schedule and booking room is off.')
        ->and(Appointment::query()->count())->toBe(0);
});

it('refuses a service that is not in the folder', function () {
    [$contact, $job] = bookByHandSetUp();

    expect(fn () => bookByHandAt($contact, '2026-11-05 10:00', $job->id, null, 'moon-landing'))
        ->toThrow(BookingRefused::class, 'That service is no longer in the business folder.');
});

it('stores a trimmed note and refuses one over 200 characters', function () {
    [$contact, $job] = bookByHandSetUp();

    expect(fn () => bookByHandAt($contact, '2026-11-05 10:00', $job->id, str_repeat('a', 201)))
        ->toThrow(BookingRefused::class, 'A note can be at most 200 characters.');

    expect(bookByHandAt($contact, '2026-11-05 10:00', $job->id, '  Gate code 1234  ')->note)->toBe('Gate code 1234')
        ->and(bookByHandAt($contact, '2026-11-06 10:00', $job->id, '   ')->note)->toBeNull();
});
