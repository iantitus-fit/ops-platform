<?php

namespace App\Rooms;

use Filament\Widgets\Widget;

abstract class RoomSection extends Widget
{
    abstract public static function roomKey(): string;
}
