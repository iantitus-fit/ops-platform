<?php

namespace App\Actions;

use App\Enums\AppointmentActor;
use App\Enums\AppointmentState;
use App\Icm\ActiveConfig;
use App\Models\Appointment;
use App\Models\Contact;
use App\Models\User;
use App\Rooms\RecordRoomEntry;
use App\Rooms\ScheduleAndBooking\Availability;
use App\Rooms\ScheduleAndBooking\BookingRefused;
use App\Rooms\ScheduleAndBooking\BookingSettings;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The named action that books a visit, and the only writer of the appointments table beside the ones later plans add
 * (D-15). It is not an agent action: no model decides or writes an appointment, so there is no checks-passed record, and
 * tests/Unit/AppointmentWritesTest.php keeps App\Ai and App\SendRule from using it. The day an agent books, the plan that
 * adds it names a chokepoint.
 *
 * Booking by hand (D-16, D-17) takes any service and any time that has not passed and does not overlap a booked
 * appointment or block while only one job may run at a time. Business hours and the lead time do not apply to the owner.
 * The overlap is checked again inside the booking lock and one transaction (D-07), so two bookings for overlapping spans
 * never both store. lockForUpdate() does nothing on SQLite, so the cache lock is the guard on both drivers (the cache
 * store must be shared between workers, as production's is). The cross-process race is covered by the lock's design, not
 * by a concurrent test.
 *
 * The job is the owner's explicit choice (D-22): an open job of this customer, or a new one opened through OpenProject.
 * A closed job is refused and never reopened (D-25). Booking never changes the contact's status (D-41) and raises no
 * owner alert (D-33). With no booking file there is no drive buffer (D-03). The service's slug and name are copied from
 * the folder onto the row (D-42), so later edits to the folder do not rewrite history.
 */
class BookAppointment
{
    /**
     * The most characters a visit note may hold.
     */
    public const int NOTE_MAX_LENGTH = 200;

    public function __construct(
        private readonly ActiveConfig $config,
        private readonly Availability $availability,
        private readonly OpenProject $openProject,
        private readonly RecordRoomEntry $recordRoomEntry,
    ) {}

    /**
     * The owner books a visit for a customer by hand. $projectId is one of the customer's open jobs, or null to open a
     * new one.
     *
     * @throws BookingRefused when the room is off, the service is gone, the time has passed or is taken, the note is too
     *                        long, the job is not open, or another booking holds the lock.
     */
    public function byHand(Contact $contact, User $user, string $serviceSlug, CarbonImmutable $startsAt, ?int $projectId, ?string $visitNote): Appointment
    {
        if (! $this->config->hasRoom('schedule_and_booking')) {
            throw BookingRefused::roomOff();
        }

        $service = $this->config->service($serviceSlug) ?? throw BookingRefused::noSuchService();

        if ($startsAt->lessThanOrEqualTo(now())) {
            throw BookingRefused::pastStart();
        }

        $note = $visitNote === null ? '' : trim($visitNote);

        if (mb_strlen($note) > self::NOTE_MAX_LENGTH) {
            throw BookingRefused::noteTooLong();
        }

        $lock = Cache::lock('schedule:book', 10);

        try {
            $lock->block(5);
        } catch (LockTimeoutException) {
            throw BookingRefused::busy();
        }

        try {
            return DB::transaction(fn (): Appointment => $this->store($contact, $user, $service, $startsAt, $projectId, $note === '' ? null : $note));
        } finally {
            $lock->release();
        }
    }

    /**
     * Re-check the time, pick or open the job, write the row and its one timeline entry. Called inside the lock and a
     * transaction.
     *
     * @param  array<string, mixed>  $service
     */
    private function store(Contact $contact, User $user, array $service, CarbonImmutable $startsAt, ?int $projectId, ?string $note): Appointment
    {
        $endsAt = $startsAt->addMinutes((int) $service['duration_minutes']);
        $buffer = BookingSettings::fromConfig($this->config)?->driveBufferMinutes ?? 0;

        if (! $this->availability->isFree($startsAt, $endsAt, $buffer)) {
            throw BookingRefused::taken();
        }

        $project = $projectId === null
            ? $this->openProject->byHand($contact, $user)
            : ($contact->projects()->whereKey($projectId)->whereNull('closed_at')->lockForUpdate()->first() ?? throw BookingRefused::projectNotOpen());

        $appointment = $contact->appointments()->create([
            'project_id' => $project->id,
            'service_slug' => $service['slug'],
            'service_name' => $service['name'],
            'starts_at' => $startsAt->utc(),
            'ends_at' => $endsAt->utc(),
            'state' => AppointmentState::Booked,
            'booked_by' => AppointmentActor::User,
            'booked_by_user_id' => $user->id,
            'booked_at' => now(),
            'note' => $note,
        ]);

        $zone = $this->config->timezone();

        $this->recordRoomEntry->handle(
            $contact,
            'schedule_and_booking',
            'visit_booked',
            'Booked by '.$user->name.': '.$service['name'].', '.BookingSettings::dayText($startsAt, $zone).' at '.BookingSettings::timeText($startsAt, $zone).'.',
            $appointment,
        );

        return $appointment;
    }
}
