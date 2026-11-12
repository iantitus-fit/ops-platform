<?php

namespace App\Actions;

use App\Enums\AppointmentActor;
use App\Enums\AppointmentState;
use App\Icm\ActiveConfig;
use App\Models\ScheduleBlock;
use App\Models\User;
use App\Rooms\ScheduleAndBooking\BookingRefused;
use Illuminate\Support\Facades\DB;

/**
 * The owner frees a blocked span (D-16). One of the named writers of the appointments table (D-15). It is not an agent
 * action: no model decides it, so there is no checks-passed record, and tests/Unit/AppointmentWritesTest.php keeps
 * App\Ai and App\SendRule from using it.
 *
 * The row stays, cancelled, and keeps who removed it and when (D-15). Like BlockTime it writes no timeline entry, because
 * a block has no customer (decided gap 2). Removing is final and a second remove changes nothing.
 */
class RemoveBlock
{
    public function __construct(private readonly ActiveConfig $config) {}

    /**
     * Remove the block, once. The row is read again under a lock through the scoped model: an appointment's id, which
     * the kind scope hides, or a row that is gone, finds nothing and changes nothing (A3, T-08-59). A block that is
     * already removed is left exactly as it is.
     *
     * @throws BookingRefused when the room is off.
     */
    public function handle(ScheduleBlock $block, User $user): void
    {
        if (! $this->config->hasRoom('schedule_and_booking')) {
            throw BookingRefused::roomOff();
        }

        DB::transaction(function () use ($block, $user): void {
            $held = ScheduleBlock::query()->lockForUpdate()->find($block->id);

            if ($held === null || ! $held->isBooked()) {
                return;
            }

            $held->update([
                'state' => AppointmentState::Cancelled,
                'cancelled_by' => AppointmentActor::User,
                'cancelled_by_user_id' => $user->id,
                'cancelled_at' => now()->utc(),
            ]);
        });
    }
}
