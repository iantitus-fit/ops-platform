<?php

namespace App\Actions;

use App\Events\ScheduleChanged;
use App\Icm\ActiveConfig;
use App\Models\Appointment;
use App\Models\User;
use App\Rooms\RecordRoomEntry;
use App\Rooms\ScheduleAndBooking\BookingRefused;
use Illuminate\Support\Facades\DB;

/**
 * The owner adds, changes or clears the note on a booked visit (D-18). One of the named writers of the appointments table
 * (D-15). It is not an agent action: no model writes a note, so there is no checks-passed record, and
 * tests/Unit/AppointmentWritesTest.php keeps App\Ai and App\SendRule from using it.
 *
 * The note is owner-typed text. The timeline entry says only that the note changed, in a platform sentence, and never
 * quotes it, so owner text cannot ride into a trusted summary; the customer card quotes the note itself, under its
 * customer-supplied-text line (plan 08-16).
 */
class EditVisitNote
{
    public function __construct(
        private readonly ActiveConfig $config,
        private readonly RecordRoomEntry $recordRoomEntry,
    ) {}

    /**
     * Keep the note. It is trimmed and an empty one clears it. The row is read again under a lock through the scoped
     * model: a block's id, which the kind scope hides, or a row that is gone, finds nothing and changes nothing (A3). The
     * same note again writes nothing. A changed note fires ScheduleChanged after its write, so today.md is redrawn once it
     * commits (D-14).
     *
     * The dispatch is deliberate, not an omission to tidy away: today.md never prints a note (D-12) and time to booked
     * does not read one, so today the redraw writes the same text, but D-14 names every visit change, a note included,
     * as a redraw trigger, so a later file that does show the note is redrawn without anyone remembering to add the
     * dispatch here (Ian, 2026-11-17, 09-REVIEW IN-01).
     *
     * @throws BookingRefused when the room is off, the note is over 200 characters, or the visit is no longer booked.
     */
    public function handle(Appointment $appointment, User $user, ?string $note): void
    {
        if (! $this->config->hasRoom('schedule_and_booking')) {
            throw BookingRefused::roomOff();
        }

        $note = trim((string) $note);

        if (mb_strlen($note) > BookAppointment::NOTE_MAX_LENGTH) {
            throw BookingRefused::noteTooLong();
        }

        $note = $note === '' ? null : $note;

        DB::transaction(function () use ($appointment, $user, $note): void {
            $visit = Appointment::query()->lockForUpdate()->find($appointment->id);

            if ($visit === null) {
                return;
            }

            if (! $visit->isBooked()) {
                throw BookingRefused::notBooked();
            }

            if ($visit->note === $note) {
                return;
            }

            $visit->update(['note' => $note]);

            $this->recordRoomEntry->handle(
                $visit->contact,
                'schedule_and_booking',
                'visit_note_changed',
                $user->name.' changed the visit note.',
                $visit,
            );

            ScheduleChanged::dispatch();
        });
    }
}
