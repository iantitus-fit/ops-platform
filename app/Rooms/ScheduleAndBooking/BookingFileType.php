<?php

namespace App\Rooms\ScheduleAndBooking;

use App\Icm\IcmFile;
use App\Rooms\RoomFileType;

/**
 * The schedule and booking room's one new kind of file: the booking file, rooms/schedule_and_booking/booking.md. It
 * holds every booking setting (D-03). The rules and checks ride on this class, the room standard (04 D-04); the
 * Validator itself is not edited for the room.
 */
class BookingFileType implements RoomFileType
{
    public function type(): string
    {
        return 'booking';
    }

    public function rules(IcmFile $file): array
    {
        return [];
    }

    public function checks(IcmFile $file): array
    {
        return [];
    }

    /**
     * A business keeps one booking file (D-03). The check in App\Icm\RoomChecks refuses every other file of this type
     * in rooms/schedule_and_booking/, wherever the room's contract owns points.
     */
    public function singleFileLabel(): ?string
    {
        return 'booking file';
    }

    /**
     * Where the one booking file lives (D-03); the SPEC file types table gives the same path.
     */
    public function singleFileDefaultPath(): ?string
    {
        return 'rooms/schedule_and_booking/booking.md';
    }
}
