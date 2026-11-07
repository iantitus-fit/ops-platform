<?php

namespace App\Actions;

use App\Enums\AppointmentState;
use App\Icm\ActiveConfig;
use App\Models\Appointment;
use App\Models\User;
use App\Rooms\RecordRoomEntry;
use App\Rooms\ScheduleAndBooking\BookingRefused;
use App\Rooms\ScheduleAndBooking\BookingSettings;
use Illuminate\Support\Facades\DB;

/**
 * The owner marks a started visit done (D-14). One of the named writers of the appointments table (D-15). It is not an
 * agent action: no model decides it, so there is no checks-passed record, and tests/Unit/AppointmentWritesTest.php keeps
 * App\Ai and App\SendRule from using it.
 *
 * Done is not undone. It stores who marked it and when, and puts one entry on the customer's timeline. A visit can be
 * marked done only once its start has passed, to the minute.
 *
 * The job closes only when the owner asked for it and this was the job's last booked visit (D-24): no job ever closes
 * because a visit was marked done (07 D-05). The close goes through CloseProject, the one writer of a job's closing, so
 * this class never touches a project row.
 */
class MarkAppointmentDone
{
    public function __construct(
        private readonly ActiveConfig $config,
        private readonly RecordRoomEntry $recordRoomEntry,
        private readonly CloseProject $closeProject,
    ) {}

    /**
     * Mark the visit done, once. The row is read again under a lock through the scoped model: a block's id, which the kind
     * scope hides, or a row that is gone, finds nothing and changes nothing (A3). A visit that is already done or
     * cancelled is left exactly as it is, so a second click adds no entry.
     *
     * When $alsoCloseProject is true and no other appointment on the job is still booked, the job is closed by the owner.
     * That close is not undone if it is refused, for example because a new visit was booked a moment ago: the refusal
     * reaches the caller in the job's own words and the visit stays done.
     *
     * @throws BookingRefused when the room is off or the visit has not started.
     */
    public function handle(Appointment $appointment, User $user, bool $alsoCloseProject = false): void
    {
        if (! $this->config->hasRoom('schedule_and_booking')) {
            throw BookingRefused::roomOff();
        }

        $done = DB::transaction(function () use ($appointment, $user): ?Appointment {
            $visit = Appointment::query()->lockForUpdate()->find($appointment->id);

            if ($visit === null || ! $visit->isBooked()) {
                return null;
            }

            if ($visit->starts_at->greaterThan(now())) {
                throw BookingRefused::notStarted();
            }

            $doneAt = now()->utc();

            $visit->update([
                'state' => AppointmentState::Done,
                'done_by_user_id' => $user->id,
                'done_at' => $doneAt,
            ]);

            $zone = $this->config->timezone();

            $this->recordRoomEntry->handle(
                $visit->contact,
                'schedule_and_booking',
                'visit_done',
                'Marked done by '.$user->name.': '.$visit->service_name.', '.BookingSettings::dayText($visit->starts_at, $zone).' at '.BookingSettings::timeText($visit->starts_at, $zone).'.',
                $visit,
            );

            return $visit;
        });

        if ($done !== null && $alsoCloseProject && ! $done->project->appointments()->booked()->exists()) {
            $this->closeProject->handle($done->project, $user);
        }
    }
}
