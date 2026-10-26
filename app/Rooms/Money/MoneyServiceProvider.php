<?php

namespace App\Rooms\Money;

use App\Rooms\Room;
use App\Rooms\RoomServiceProvider;

/**
 * The room where the business's money work will live later. This phase it is a place for one notes file and nothing
 * more: no payment-link slot, no tables, no payment records and no timeline entries (ROOM-14, 05 D-05; backlog 999.1).
 * It defines one file type, the money notes, and stays optional (04 D-08).
 */
class MoneyServiceProvider extends RoomServiceProvider
{
    protected function room(): Room
    {
        return new Room(
            key: 'money',
            label: 'money',
            fileTypes: [new MoneyNotesType],
            sections: [MoneySection::class],
        );
    }
}
