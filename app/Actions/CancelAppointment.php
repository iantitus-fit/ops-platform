<?php

namespace App\Actions;

use App\Enums\AppointmentActor;
use App\Enums\AppointmentState;
use App\Icm\ActiveConfig;
use App\Models\Appointment;
use App\Models\BookingSend;
use App\Models\User;
use App\Rooms\RecordRoomEntry;
use App\Rooms\ScheduleAndBooking\BookingRefused;
use App\Rooms\ScheduleAndBooking\BookingSettings;
use Illuminate\Support\Facades\DB;

/**
 * The owner cancels a booked visit (D-14, D-16). One of the named writers of the appointments table (D-15). It is not an
 * agent action: no model decides a cancel, so there is no checks-passed record, and tests/Unit/AppointmentWritesTest.php
 * keeps App\Ai and App\SendRule from using it.
 *
 * A cancel is final: a cancelled visit is never restored, and the owner books a new time instead. It stores who cancelled
 * (the actor and the user), so a customer's own cancel can be added later without a new column (D-32). It sends nothing,
 * so the owner tells the customer themselves (D-16), and it puts one entry on the customer's timeline.
 *
 * Every text still pending for the visit ends at once as skipped (D-31). The sender checks that a visit is still booked
 * before every send as the second guard, so a reminder for a cancelled visit never goes out.
 */
class CancelAppointment
{
    /**
     * What a pending text's row says when the cancel ended it.
     */
    public const string SKIPPED_BECAUSE = 'The visit was cancelled.';

    public function __construct(
        private readonly ActiveConfig $config,
        private readonly RecordRoomEntry $recordRoomEntry,
    ) {}

    /**
     * Cancel the visit, once. The row is read again under a lock through the scoped model: a block's id, which the kind
     * scope hides, or a row that is gone, finds nothing and changes nothing (A3). A visit that is already cancelled or
     * done is left exactly as it is, so a second click adds no entry and moves no time.
     *
     * @throws BookingRefused when the room is off.
     */
    public function handle(Appointment $appointment, User $user): void
    {
        if (! $this->config->hasRoom('schedule_and_booking')) {
            throw BookingRefused::roomOff();
        }

        DB::transaction(function () use ($appointment, $user): void {
            $visit = Appointment::query()->lockForUpdate()->find($appointment->id);

            if ($visit === null || ! $visit->isBooked()) {
                return;
            }

            $cancelledAt = now()->utc();

            $visit->update([
                'state' => AppointmentState::Cancelled,
                'cancelled_by' => AppointmentActor::User,
                'cancelled_by_user_id' => $user->id,
                'cancelled_at' => $cancelledAt,
            ]);

            BookingSend::query()
                ->where('appointment_id', $visit->id)
                ->whereNull('sent_at')
                ->whereNull('skipped_at')
                ->whereNull('failed_at')
                ->update(['skipped_at' => $cancelledAt, 'failure' => self::SKIPPED_BECAUSE]);

            $zone = $this->config->timezone();

            $this->recordRoomEntry->handle(
                $visit->contact,
                'schedule_and_booking',
                'visit_cancelled',
                'Cancelled by '.$user->name.': '.$visit->service_name.', '.BookingSettings::dayText($visit->starts_at, $zone).' at '.BookingSettings::timeText($visit->starts_at, $zone).'.',
                $visit,
            );
        });
    }
}
