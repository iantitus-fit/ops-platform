<?php

namespace App\Rooms\Marketing;

use App\Rooms\Room;
use App\Rooms\RoomServiceProvider;

/**
 * The room that holds where leads come from and what the business is offering: the lead sources and the offers. Both
 * stay where they are, by kind (04 D-19), so the room adds no new file type, slot, table or timeline entry (05 D-05)
 * and has no register() or boot() of its own. The web lead form is not a slot and marketing does not claim lead intake.
 */
class MarketingServiceProvider extends RoomServiceProvider
{
    protected function room(): Room
    {
        return new Room(
            key: 'marketing',
            label: 'marketing',
            handles: ['lead_sources', 'offer'],
            sections: [MarketingSection::class],
            neverLeftOutBecause: 'it owns the lead sources file the sync requires',
        );
    }
}
