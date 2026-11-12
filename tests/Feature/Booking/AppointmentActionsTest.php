<?php

use App\Actions\BookAppointment;
use App\Actions\CancelAppointment;
use App\Actions\EditVisitNote;
use App\Actions\MarkAppointmentDone;
use App\Enums\ActivityType;
use App\Enums\AppointmentActor;
use App\Enums\AppointmentState;
use App\Jobs\SendDueBookingTexts;
use App\Models\Activity;
use App\Models\Appointment;
use App\Models\Contact;
use App\Models\Message;
use App\Models\Project;
use App\Models\ScheduleBlock;
use App\Models\User;
use App\Rooms\ScheduleAndBooking\BookingRefused;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;

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

it('marks a started visit done', function () {
    [$contact, $appointment] = appointmentActionsSetUp();
    $this->travelTo(appointmentActionsChicago('2026-11-05 10:00'));

    app(MarkAppointmentDone::class)->handle($appointment, $this->owner);

    $appointment->refresh();

    expect($appointment->state)->toBe(AppointmentState::Done)
        ->and($appointment->done_by_user_id)->toBe($this->owner->id)
        ->and($appointment->done_at)->not->toBeNull()
        ->and(appointmentActionsEntries($contact, 'visit_done'))->toBe(['Marked done by Ian Owner: AC repair, Thursday, November 5 at 10:00 AM.'])
        ->and($appointment->project->isOpen())->toBeTrue();
});

it('refuses a visit that has not started and changes nothing', function () {
    [$contact, $appointment] = appointmentActionsSetUp();
    $this->travelTo(appointmentActionsChicago('2026-11-05 09:59'));

    expect(fn () => app(MarkAppointmentDone::class)->handle($appointment, $this->owner))
        ->toThrow(DomainException::class, 'A visit can be marked done only once it has started.');

    expect($appointment->fresh()->state)->toBe(AppointmentState::Booked)
        ->and(appointmentActionsEntries($contact, 'visit_done'))->toBe([]);
});

it('marks done once', function () {
    [$contact, $appointment] = appointmentActionsSetUp();
    $this->travelTo(appointmentActionsChicago('2026-11-05 10:00'));

    app(MarkAppointmentDone::class)->handle($appointment, $this->owner);
    $doneAt = $appointment->fresh()->done_at;

    $this->travelTo(appointmentActionsChicago('2026-11-05 10:30'));
    app(MarkAppointmentDone::class)->handle($appointment, User::factory()->create());

    expect($appointment->fresh()->done_at->equalTo($doneAt))->toBeTrue()
        ->and($appointment->fresh()->done_by_user_id)->toBe($this->owner->id)
        ->and(appointmentActionsEntries($contact, 'visit_done'))->toHaveCount(1);
});

it('does not mark a cancelled visit done or a block', function () {
    [$contact, $appointment] = appointmentActionsSetUp();
    app(CancelAppointment::class)->handle($appointment, $this->owner);
    $block = ScheduleBlock::factory()->at(appointmentActionsChicago('2026-11-05 13:00'), 120)->create();
    $this->travelTo(appointmentActionsChicago('2026-11-06 10:00'));

    app(MarkAppointmentDone::class)->handle($appointment, $this->owner);
    app(MarkAppointmentDone::class)->handle(Appointment::query()->withoutGlobalScope('kind')->find($block->id), $this->owner);

    expect($appointment->fresh()->state)->toBe(AppointmentState::Cancelled)
        ->and(ScheduleBlock::query()->find($block->id)->state)->toBe(AppointmentState::Booked)
        ->and(appointmentActionsEntries($contact, 'visit_done'))->toBe([]);
});

it('marks done at the start minute and not a minute before', function () {
    [, $appointment] = appointmentActionsSetUp();

    $this->travelTo(appointmentActionsChicago('2026-11-05 09:59'));
    expect(fn () => app(MarkAppointmentDone::class)->handle($appointment, $this->owner))->toThrow(DomainException::class);

    $this->travelTo(appointmentActionsChicago('2026-11-05 10:00'));
    app(MarkAppointmentDone::class)->handle($appointment, $this->owner);

    expect($appointment->fresh()->state)->toBe(AppointmentState::Done);
});

it('closes the job only when asked, on its last booked visit', function (bool $alsoClose, bool $anotherBooked, bool $jobClosed) {
    [$contact, $appointment] = appointmentActionsSetUp();

    if ($anotherBooked) {
        Appointment::factory()->for($contact)->for($appointment->project)
            ->at(appointmentActionsChicago('2026-11-12 10:00'), 90)->create();
    }

    $this->travelTo(appointmentActionsChicago('2026-11-05 10:00'));

    app(MarkAppointmentDone::class)->handle($appointment, $this->owner, $alsoClose);

    $job = Project::query()->findOrFail($appointment->project_id);

    expect($job->isOpen())->toBe(! $jobClosed)
        ->and($contact->activities()->where('type', ActivityType::ProjectClosed->value)->count())->toBe($jobClosed ? 1 : 0);

    if ($jobClosed) {
        expect($job->closed_by_user_id)->toBe($this->owner->id);
    }
})->with([
    'ticked, the last booked visit' => [true, false, true],
    'not ticked, the last booked visit' => [false, false, false],
    'ticked, but another visit is still booked' => [true, true, false],
]);

it('keeps the visit note and writes a quiet entry', function () {
    [$contact, $appointment] = appointmentActionsSetUp();

    app(EditVisitNote::class)->handle($appointment, $this->owner, '  Gate code 4412  ');

    expect($appointment->fresh()->note)->toBe('Gate code 4412')
        ->and(appointmentActionsEntries($contact, 'visit_note_changed'))->toBe(['Ian Owner changed the visit note.'])
        ->and(implode(' ', appointmentActionsEntries($contact, 'visit_note_changed')))->not->toContain('Gate code');
});

it('clears the note with nothing, and writes nothing for the same note again', function () {
    [$contact, $appointment] = appointmentActionsSetUp();

    app(EditVisitNote::class)->handle($appointment, $this->owner, 'Gate code 4412');
    app(EditVisitNote::class)->handle($appointment, $this->owner, 'Gate code 4412');

    expect(appointmentActionsEntries($contact, 'visit_note_changed'))->toHaveCount(1);

    app(EditVisitNote::class)->handle($appointment, $this->owner, '');

    expect($appointment->fresh()->note)->toBeNull()
        ->and(appointmentActionsEntries($contact, 'visit_note_changed'))->toHaveCount(2);

    app(EditVisitNote::class)->handle($appointment, $this->owner, null);

    expect(appointmentActionsEntries($contact, 'visit_note_changed'))->toHaveCount(2);
});

it('refuses a note over 200 characters and keeps 200', function () {
    [, $appointment] = appointmentActionsSetUp();

    expect(fn () => app(EditVisitNote::class)->handle($appointment, $this->owner, str_repeat('a', 201)))
        ->toThrow(BookingRefused::class, 'A note can be at most 200 characters.');

    app(EditVisitNote::class)->handle($appointment, $this->owner, str_repeat('é', 200));

    expect(mb_strlen($appointment->fresh()->note))->toBe(200);
});

it('refuses a note on a cancelled or done visit', function (string $how) {
    [$contact, $appointment] = appointmentActionsSetUp();

    if ($how === 'cancelled') {
        app(CancelAppointment::class)->handle($appointment, $this->owner);
    } else {
        $this->travelTo(appointmentActionsChicago('2026-11-05 10:00'));
        app(MarkAppointmentDone::class)->handle($appointment, $this->owner);
    }

    expect(fn () => app(EditVisitNote::class)->handle($appointment, $this->owner, 'Too late'))
        ->toThrow(BookingRefused::class);

    expect($appointment->fresh()->note)->toBeNull()
        ->and(appointmentActionsEntries($contact, 'visit_note_changed'))->toBe([]);
})->with(['cancelled', 'done']);

it('never edits a block\'s note through the visit action', function () {
    appointmentActionsSetUp();
    $block = ScheduleBlock::factory()->at(appointmentActionsChicago('2026-11-05 13:00'), 120)->create(['note' => 'Truck in the shop']);

    app(EditVisitNote::class)->handle(Appointment::query()->withoutGlobalScope('kind')->find($block->id), $this->owner, 'Changed');

    expect(ScheduleBlock::query()->find($block->id)->note)->toBe('Truck in the shop')
        ->and(Activity::query()->where('entry', 'visit_note_changed')->count())->toBe(0);
});

it('is refused with the room off', function (string $action) {
    File::deleteDirectory(icmPath('rooms/schedule_and_booking'));
    activateExampleFolder();
    $appointment = Appointment::factory()->create();

    $run = match ($action) {
        'cancel' => fn () => app(CancelAppointment::class)->handle($appointment, $this->owner),
        'done' => fn () => app(MarkAppointmentDone::class)->handle($appointment, $this->owner),
        'note' => fn () => app(EditVisitNote::class)->handle($appointment, $this->owner, 'Hello'),
    };

    expect($run)->toThrow(BookingRefused::class, 'The schedule and booking room is off.')
        ->and($appointment->fresh()->state)->toBe(AppointmentState::Booked)
        ->and($appointment->fresh()->note)->toBeNull();
})->with(['cancel', 'done', 'note']);
