<?php

namespace App\Rooms\OwnerIntelligence;

use App\Rooms\Room;
use App\Rooms\RoomServiceProvider;

/**
 * The room through which the owner, and the owner's own AI, read how the business is doing. It owns no file and no table:
 * it reads messages and appointments, which other rooms and the platform own (06 D-04). It reads business/profile.md for
 * the time zone the day and the 30 days are counted in, and it writes records/today.md and records/time-to-booked.md
 * only while it is live (09-04).
 *
 * Its dashboard section shows the time-to-booked line and nothing else (D-07, D-24). It is optional, so a business can
 * leave rooms/owner_intelligence/ out and the room is simply off: card rendering stays in app/Records/ and the nightly
 * rebuild is the platform's, so nothing depends on it (D-09). It has no connector slot until the MCP server arrives
 * (999.4), and it adds no agent action: its section and its contact line only read.
 */
class OwnerIntelligenceServiceProvider extends RoomServiceProvider
{
    protected function room(): Room
    {
        return new Room(
            key: 'owner_intelligence',
            label: 'owner intelligence',
            sections: [OwnerIntelligenceSection::class],
        );
    }
}
