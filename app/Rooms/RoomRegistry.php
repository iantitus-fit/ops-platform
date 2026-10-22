<?php

namespace App\Rooms;

use Filament\Panel;

class RoomRegistry
{
    public function add(Room $room): void {}

    public function has(string $key): bool
    {
        return false;
    }

    public function addWidgetsTo(Panel $panel): Panel
    {
        return $panel;
    }
}
