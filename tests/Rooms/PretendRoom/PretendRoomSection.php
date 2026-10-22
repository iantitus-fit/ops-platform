<?php

namespace Tests\Rooms\PretendRoom;

use App\Rooms\RoomSection;

/**
 * The pretend room's dashboard section: the shared room section view under the key "pretend".
 */
class PretendRoomSection extends RoomSection
{
    public static function roomKey(): string
    {
        return 'pretend';
    }
}
