<?php

namespace App\Icm\Walks;

use App\Icm\BusinessClock;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Decides the moment a walk starts (10 D-07).
 *
 * A walk that names a start begins exactly there, written in the business's time zone. A walk that names none begins at
 * 10:00 on the business-zone day of the shop's next opening, counted from the moment the run began, so a walk run late on a
 * Saturday still meets an open shop on Monday. When 10:00 falls outside that day's hours (before the shop opens, or at or
 * after it closes) the walk begins at that day's opening instead. A shop that does not open within two weeks has no next
 * opening, so the walk begins at the moment the run began.
 */
class WalkClock
{
    /**
     * @param  string  $zone  The business's time zone, such as "America/Chicago".
     */
    public function startFor(WalkFile $walk, BusinessClock $clock, string $zone, CarbonInterface $runBegan): CarbonImmutable
    {
        if ($walk->start !== null) {
            return CarbonImmutable::createFromFormat('Y-m-d H:i', $walk->start, $zone) ?: CarbonImmutable::instance($runBegan);
        }

        $opening = $clock->nextOpening($runBegan);

        if ($opening === null) {
            return CarbonImmutable::instance($runBegan);
        }

        $day = $opening->setTimezone($zone);
        $window = $clock->openWindowOn($day);
        $tenOClock = $day->setTime(10, 0);

        if ($window === null || $tenOClock < $window['open'] || $tenOClock >= $window['close']) {
            return $window['open'] ?? $opening;
        }

        return $tenOClock;
    }
}
