<?php

namespace App\Rooms\OwnerIntelligence;

use App\Rooms\RoomSection;

/**
 * The owner intelligence room's dashboard section: the one time-to-booked line (D-07, D-24). Today's visits stay in the
 * schedule room's section (08 D-40). The line is resolved from the container, so a test can swap the class that works it
 * out. A throw while it is built is reported by the base class and the section shows the shared failure line (05 D-32).
 * It only reads.
 */
class OwnerIntelligenceSection extends RoomSection
{
    public static function roomKey(): string
    {
        return 'owner_intelligence';
    }

    /**
     * @return list<string>
     */
    protected function contentLines(): array
    {
        return [app(TimeToBooked::class)->summaryLine()];
    }
}
