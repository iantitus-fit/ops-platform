<?php

namespace App\Rooms\ScheduleAndBooking;

use App\Icm\ActiveConfig;
use App\Icm\BusinessClock;
use Carbon\CarbonImmutable;

/**
 * The times a customer may book (BOOK-01), asked by the booking page, the tap-time re-check and booking by hand.
 *
 * Days and opening hours come from the business's own wall clock through BusinessClock, and each start is found by
 * adding elapsed minutes to the day's opening instant, so a daylight saving day never offers a time that does not exist
 * or the same instant twice (D-11). Business configuration is read only through ActiveConfig. The moment is passed in,
 * never read from the clock here, and nothing is written: this class reads and is not an action.
 */
class Availability
{
    /**
     * How many jobs may share one stretch of time (D-07). A count check, so a setting or crews later change this one
     * number and not the rule.
     */
    public const int CAPACITY = 1;

    public function __construct(private ActiveConfig $config, private BusinessClock $clock) {}

    /**
     * The start times offered for a service, grouped by business-zone date 'Y-m-d', earliest first, only days that have
     * one (D-09). A start is offered when it is in that day's hours, at or after the lead time counted from the later
     * of now and the next opening (D-10), after now, ends by that day's closing (D-07) and has free time (D-06 to D-08).
     * With no booking file the page offers nothing (D-03).
     *
     * @param  array<string, mixed>  $service
     * @return array<string, list<CarbonImmutable>>
     */
    public function openStarts(array $service, CarbonImmutable $now): array
    {
        $settings = BookingSettings::fromConfig($this->config);

        if ($settings === null) {
            return [];
        }

        $earliest = $this->earliestStart($now);

        if ($earliest === null) {
            return [];
        }

        $duration = (int) $service['duration_minutes'];
        $zone = $this->config->timezone();
        $firstDay = $now->setTimezone($zone)->setTime(12, 0);
        $days = [];

        for ($offset = 0; $offset < $settings->daysAhead; $offset++) {
            $day = $firstDay->addDays($offset);
            $window = $this->clock->openWindowOn($day);

            if ($window === null) {
                continue;
            }

            $starts = [];

            for ($start = $window['open']; $start->addMinutes($duration) <= $window['close']; $start = $start->addMinutes($settings->stepMinutes)) {
                if ($start < $earliest || $start <= $now) {
                    continue;
                }

                if ($this->isFree($start, $start->addMinutes($duration), $settings->driveBufferMinutes)) {
                    $starts[] = $start;
                }
            }

            if ($starts !== []) {
                $days[$day->toDateString()] = $starts;
            }
        }

        return $days;
    }

    /**
     * Whether a span has free time for one more job. For now every span counts as free; the overlap rule follows.
     */
    public function isFree(CarbonImmutable $start, CarbonImmutable $end, int $bufferMinutes): bool
    {
        return true;
    }

    /**
     * The earliest instant a start may have: the lead time counted from the later of now and the next opening (D-10).
     * Null when the business does not open within the next two weeks.
     */
    private function earliestStart(CarbonImmutable $now): ?CarbonImmutable
    {
        $from = $this->clock->isOpenAt($now) ? $now : $this->clock->nextOpening($now);

        if ($from === null) {
            return null;
        }

        return $from->addMinutes((int) ($this->config->hours()['booking_lead_minutes'] ?? 0));
    }
}
