<?php

namespace App\Rooms\ScheduleAndBooking;

use App\Icm\ActiveConfig;
use App\Icm\BusinessClock;
use App\Models\Appointment;
use App\Models\ScheduleBlock;
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
        $booked = $this->bookedSpans($firstDay->startOfDay(), $firstDay->addDays($settings->daysAhead + 1), $settings->driveBufferMinutes);
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

                if ($this->overlapsIn($booked, $start, $start->addMinutes($duration), $settings->driveBufferMinutes) < self::CAPACITY) {
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
     * Whether a span has free time for one more job: fewer booked appointments and blocks overlap it than CAPACITY
     * allows (D-07). Booking by hand passes a buffer of 0.
     */
    public function isFree(CarbonImmutable $start, CarbonImmutable $end, int $bufferMinutes): bool
    {
        return $this->overlapCount($start, $end, $bufferMinutes) < self::CAPACITY;
    }

    /**
     * How many booked appointments and blocks overlap a span. The drive buffer sits after an appointment (D-08) and
     * after the new span, so it counts at the end of each appointment and at the end of the new span; a block carries
     * no buffer of its own (Ian's A2 answer), only the new span's end buffer counts against it. Each model is read once
     * with bounds computed here and bound as UTC instants, so no SQL date math is needed and it runs the same on SQLite
     * and Postgres.
     */
    public function overlapCount(CarbonImmutable $start, CarbonImmutable $end, int $bufferMinutes): int
    {
        return $this->overlapsIn($this->bookedSpans($start, $end, $bufferMinutes), $start, $end, $bufferMinutes);
    }

    /**
     * The booked appointment and block spans that could touch any span between $from and $to once the buffer is added,
     * as instants. Cancelled and done appointments and removed blocks hold no time and are left out (D-14).
     *
     * @return array{appointments: list<array{CarbonImmutable, CarbonImmutable}>, blocks: list<array{CarbonImmutable, CarbonImmutable}>}
     */
    private function bookedSpans(CarbonImmutable $from, CarbonImmutable $to, int $bufferMinutes): array
    {
        $touchesFrom = $from->subMinutes($bufferMinutes)->utc();
        $touchesTo = $to->addMinutes($bufferMinutes)->utc();

        $spans = static fn ($rows): array => $rows
            ->map(static fn ($row): array => [CarbonImmutable::instance($row->starts_at), CarbonImmutable::instance($row->ends_at)])
            ->all();

        return [
            'appointments' => $spans(Appointment::query()->booked()
                ->where('starts_at', '<', $touchesTo)->where('ends_at', '>', $touchesFrom)
                ->get(['starts_at', 'ends_at'])),
            'blocks' => $spans(ScheduleBlock::query()->booked()
                ->where('starts_at', '<', $touchesTo)->where('ends_at', '>', $touchesFrom)
                ->get(['starts_at', 'ends_at'])),
        ];
    }

    /**
     * The one overlap rule, applied to spans already read (D-07, D-08, A2). An appointment overlaps when it starts
     * before the new span's end plus the buffer and its own end plus the buffer is after the new start. A block
     * overlaps when it starts before the new span's end plus the buffer and ends after the new start.
     *
     * @param  array{appointments: list<array{CarbonImmutable, CarbonImmutable}>, blocks: list<array{CarbonImmutable, CarbonImmutable}>}  $booked
     */
    private function overlapsIn(array $booked, CarbonImmutable $start, CarbonImmutable $end, int $bufferMinutes): int
    {
        $bufferedEnd = $end->addMinutes($bufferMinutes);
        $count = 0;

        foreach ($booked['appointments'] as [$startsAt, $endsAt]) {
            if ($startsAt < $bufferedEnd && $endsAt->addMinutes($bufferMinutes) > $start) {
                $count++;
            }
        }

        foreach ($booked['blocks'] as [$startsAt, $endsAt]) {
            if ($startsAt < $bufferedEnd && $endsAt > $start) {
                $count++;
            }
        }

        return $count;
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
