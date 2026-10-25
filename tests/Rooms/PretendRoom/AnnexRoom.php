<?php

namespace Tests\Rooms\PretendRoom;

use App\Rooms\Room;

/**
 * A second room that exists only in tests, so the refusals that need two rooms can be proven: a file two contracts
 * claim, a file only another room handles, a room that is registered but not in the folder. It handles the pretend
 * room's note type and nothing a real room handles, so it never collides with company knowledge, marketing or any later
 * room. It is never registered at start-up (D-20).
 */
class AnnexRoom
{
    public static function room(): Room
    {
        return new Room(
            key: 'annex',
            label: 'annex',
            handles: ['pretend_note'],
            slot: 'annex_feed',
            slotInterface: PretendFeed::class,
        );
    }
}
