<?php

namespace Tests\Rooms\PretendRoom;

use App\Rooms\Room;
use App\Rooms\RoomServiceProvider;

/**
 * The room that exists only in tests. It is never listed in bootstrap/providers.php: enablePretendRoom() in
 * tests/Pest.php registers it for the one test that asks (D-20).
 */
class PretendRoomServiceProvider extends RoomServiceProvider
{
    protected function room(): Room
    {
        return new Room(key: 'pretend', label: 'pretend', sections: [PretendRoomSection::class]);
    }
}
