<?php

namespace App\Rooms;

use Illuminate\Support\ServiceProvider;

abstract class RoomServiceProvider extends ServiceProvider
{
    abstract protected function room(): Room;
}
