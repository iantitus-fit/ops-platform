<?php

namespace App\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired by the six named actions (08 D-15) right after their write, inside their transaction, and held until it commits,
 * so a rolled-back write redraws nothing. It carries nothing, so the redraw reads the newest state (D-14).
 */
class ScheduleChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;
}
