<?php

namespace App\Listeners;

use App\Events\ScheduleChanged;
use App\Jobs\RedrawOwnerIntelligenceRecords;

class QueueOwnerIntelligenceRedraw
{
    /**
     * Queue a redraw of records/today.md and records/time-to-booked.md. Laravel finds this listener by the event
     * type-hinted on handle(), so it needs no registration.
     */
    public function handle(ScheduleChanged $event): void
    {
        RedrawOwnerIntelligenceRecords::dispatch();
    }
}
