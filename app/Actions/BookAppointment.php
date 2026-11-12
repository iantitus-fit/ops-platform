<?php

namespace App\Actions;

use App\Enums\AlertReason;
use App\Enums\AppointmentActor;
use App\Enums\AppointmentState;
use App\Icm\ActiveConfig;
use App\Models\Appointment;
use App\Models\BookingLink;
use App\Models\Contact;
use App\Models\Message;
use App\Models\User;
use App\Rooms\RecordRoomEntry;
use App\Rooms\ScheduleAndBooking\Availability;
use App\Rooms\ScheduleAndBooking\BookingRefused;
use App\Rooms\ScheduleAndBooking\BookingSettings;
use App\Rooms\ScheduleAndBooking\ReminderSchedule;
use App\Rooms\ScheduleAndBooking\ResolveBookingLink;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use LogicException;
use Throwable;

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
 *
 * Right after a booking commits, its confirmation text is sent at once when it is due (D-28), through SendBookingText.
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
        private readonly RaiseOwnerAlert $raiseOwnerAlert,
        private readonly ResolveBookingLink $resolveBookingLink,
        private readonly SendBookingText $sendBookingText,
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

        $appointment = $this->underLock(fn (): Appointment => $this->store($contact, $user, $service, $startsAt, $projectId, $note === '' ? null : $note));

        $this->sendConfirmationNow($appointment);

        return $appointment;
    }

    /**
     * The customer books the time they tapped on the booking page, through their link (D-13, D-15). The link row is
     * provenance, not a checks-passed record: a page booking is the customer's tap, not an agent action.
     *
     * Inside the same lock and one transaction as booking by hand, the link must still be able to book and the tapped
     * time must still be one Availability offers (D-07); either failing refuses with nothing stored. The booked alert to
     * the owner is raised after the transaction commits, so a booking that rolls back alerts no one (D-33).
     *
     * @throws BookingRefused when the link is closed, the service is gone, the time is no longer offered, or another
     *                        booking holds the lock.
     */
    public function fromLink(BookingLink $link, CarbonImmutable $startsAt, CarbonImmutable $now): Appointment
    {
        $appointment = $this->underLock(fn (): Appointment => $this->storeFromLink($link, $startsAt, $now));

        $this->alertOwner($link, $appointment);
        $this->sendConfirmationNow($appointment);

        return $appointment;
    }

    /**
     * Send the confirmation right after the booking commits when it is already due (D-28): a page booking's whatever the
     * hour, a hand booking's inside the send window. A hand booking made outside the window has its confirmation due at
     * the next opening, so nothing goes now and the sweep sends it then. The sweep is the backstop for a send that fails
     * here, and the row's claim makes the two safe together, so one text goes out. A failure is reported and never undoes
     * the booking (D-28).
     */
    private function sendConfirmationNow(Appointment $appointment): void
    {
        try {
            $confirmation = $appointment->sends()
                ->where('lead_minutes', 0)
                ->where('due_at', '<=', now()->utc())
                ->first();

            if ($confirmation !== null) {
                $this->sendBookingText->handle($confirmation);
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Run a booking write under the one booking lock and a transaction (D-07).
     *
     * @param  callable(): Appointment  $write
     */
    private function underLock(callable $write): Appointment
    {
        $lock = Cache::lock('schedule:book', 10);

        try {
            $lock->block(5);
        } catch (LockTimeoutException) {
            throw BookingRefused::busy();
        }

        try {
            return DB::transaction($write);
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

        $this->writeSendRows($appointment, $startsAt, false);

        return $appointment;
    }

    /**
     * The page booking, called inside the lock and a transaction: re-read the link, refuse a closed one, re-check the
     * time with Availability::offers(), pick the job, write the row and its one timeline entry.
     */
    private function storeFromLink(BookingLink $link, CarbonImmutable $startsAt, CarbonImmutable $now): Appointment
    {
        $link = BookingLink::query()->with('appointment')->lockForUpdate()->find($link->id) ?? throw BookingRefused::linkClosed();

        // The link's own state, judged on the row held here. It never asks for the open-times list, so a just-taken last
        // time falls through to offers() below and reads as taken(), not as a closed link (D-07, U-09).
        if ($this->resolveBookingLink->closedState($link, $now) !== null) {
            throw BookingRefused::linkClosed();
        }

        $service = $this->config->service($link->service_slug) ?? throw BookingRefused::linkClosed();

        if (! $this->availability->offers($service, $startsAt, $now)) {
            throw BookingRefused::taken();
        }

        $contact = $link->contact;

        $projectId = $this->jobIdForPageBooking($contact, $link);

        // The unique booking_link_id index is the backstop if a state check ever loses a race: the insert runs in its own
        // savepoint, so on Postgres a refused insert does not spoil the outer transaction.
        try {
            $appointment = DB::transaction(fn (): Appointment => $contact->appointments()->create([
                'project_id' => $projectId,
                'booking_link_id' => $link->id,
                'service_slug' => $service['slug'],
                'service_name' => $service['name'],
                'starts_at' => $startsAt->utc(),
                'ends_at' => $startsAt->addMinutes((int) $service['duration_minutes'])->utc(),
                'state' => AppointmentState::Booked,
                'booked_by' => AppointmentActor::Customer,
                'booked_at' => $now,
            ]));
        } catch (UniqueConstraintViolationException) {
            throw BookingRefused::linkClosed();
        }

        $zone = $this->config->timezone();

        $this->recordRoomEntry->handle(
            $contact,
            'schedule_and_booking',
            'visit_booked',
            'Booked by the customer on the booking page: '.$service['name'].', '.BookingSettings::dayText($startsAt, $zone).' at '.BookingSettings::timeText($startsAt, $zone).'.',
            $appointment,
        );

        $this->writeSendRows($appointment, $startsAt, true);

        return $appointment;
    }

    /**
     * Store one row per text this booking will send: the confirmation and each reminder, with the due time worked out
     * once, now (D-28, D-29, D-31). Called inside the booking transaction, so a booking that rolls back leaves no rows.
     * With no booking file nothing customer-facing goes out, so no rows are written (D-03).
     */
    private function writeSendRows(Appointment $appointment, CarbonImmutable $startsAt, bool $pageBooking): void
    {
        $settings = BookingSettings::fromConfig($this->config);

        if ($settings === null) {
            return;
        }

        $rows = ReminderSchedule::rows(
            $startsAt,
            CarbonImmutable::instance($appointment->booked_at),
            $pageBooking,
            $settings,
            $this->config->timezone(),
        );

        foreach ($rows as $row) {
            $appointment->sends()->create([...$row, 'due_at' => $row['due_at']->utc()]);
        }
    }

    /**
     * The job a page booking lands on, without guessing (D-23): the open job tied to the link's conversation when there
     * is exactly one; otherwise the contact's only open job; otherwise a new one opened as the customer. A closed job is
     * never reopened. Called inside the transaction, with the chosen job's row locked. The jobs are reached through the
     * contact's own projects() relation, and only the chosen job's id is returned.
     */
    private function jobIdForPageBooking(Contact $contact, BookingLink $link): int
    {
        $open = fn () => $contact->projects()->whereNull('closed_at')->lockForUpdate();

        $tied = $open()->where('conversation_id', $link->conversation_id)->get();

        if ($tied->count() === 1) {
            return $tied->first()->id;
        }

        $only = $open()->get();

        if ($only->count() === 1) {
            return $only->first()->id;
        }

        return $this->openProject->forBookingPage($contact, $link->conversation_id)->id;
    }

    /**
     * Tell the owner a visit was booked, once, on the text that carried the link (D-33, BOOK-05). The subject is the
     * link's message_id, or else the conversation's message whose meta.booking_link_id names the link, so a crash between
     * the send and the back-fill still finds it. With no such message nothing is raised and the problem is reported:
     * never an alert keyed to message 0 (02.1 IN-11). A failure to alert is reported and never undoes the booking.
     */
    private function alertOwner(BookingLink $link, Appointment $appointment): void
    {
        try {
            $link = $link->fresh(['message', 'conversation']) ?? $link;

            $subject = $link->message ?? Message::query()
                ->where('conversation_id', $link->conversation_id)
                ->where('meta->booking_link_id', $link->id)
                ->orderBy('id')
                ->first();

            if ($subject === null) {
                report(new LogicException("Booking link {$link->id} has no carrying message, so no booked alert was raised."));

                return;
            }

            $zone = $this->config->timezone();

            $this->raiseOwnerAlert->handle(
                $link->conversation,
                AlertReason::AppointmentBooked,
                $subject,
                $appointment->service_name.', '.BookingSettings::dayText($appointment->starts_at, $zone).' at '.BookingSettings::timeText($appointment->starts_at, $zone),
            );
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
