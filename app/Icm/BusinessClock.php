<?php

namespace App\Icm;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Answers "is the shop open" and "when does it open next" from business/hours.md.
 *
 * Every calculation is done on the wall clock of the business's own time zone (never in UTC), so a
 * daylight saving change still gives "8 am" and a closure date is closed for the whole local day.
 */
class BusinessClock
{
    /**
     * How many days ahead nextOpening() looks. A folder closed for longer has no next opening.
     */
    private const DAYS_AHEAD = 13;

    public function __construct(private ActiveConfig $config) {}

    /**
     * Whether the shop is open at that moment. The opening time counts, the closing time does not.
     */
    public function isOpenAt(CarbonInterface $moment): bool
    {
        $local = $this->inBusinessZone($moment);
        $window = $this->windowOn($local);

        return $window !== null && $local >= $window['open'] && $local < $window['close'];
    }

    /**
     * The first opening strictly after the moment, or null when the shop does not open within the
     * next two weeks.
     */
    public function nextOpening(CarbonInterface $after): ?CarbonImmutable
    {
        $local = $this->inBusinessZone($after);

        for ($daysAhead = 0; $daysAhead <= self::DAYS_AHEAD; $daysAhead++) {
            $window = $this->windowOn($local->addDays($daysAhead));

            if ($window !== null && $window['open'] > $local) {
                return $window['open'];
            }
        }

        return null;
    }

    /**
     * The words that fill {next_open} in a fixed reply: "today" while the shop is open with at least
     * the configured margin before closing, otherwise "when we open at 8 am tomorrow" and its like.
     */
    public function nextOpenPhrase(CarbonInterface $moment): string
    {
        $local = $this->inBusinessZone($moment);
        $window = $this->windowOn($local);

        if ($window !== null && $this->isOpenAt($local) && $this->hasMarginBeforeClose($local, $window['close'])) {
            return 'today';
        }

        $opening = $this->nextOpening($local);

        if ($opening === null) {
            return 'when we reopen';
        }

        return 'when we open at '.$this->timeWords($opening).' '.$this->dayWords($local, $opening);
    }

    private function inBusinessZone(CarbonInterface $moment): CarbonImmutable
    {
        return CarbonImmutable::instance($moment)->setTimezone($this->config->timezone());
    }

    /**
     * The opening and closing instants of the local day that $day falls on, or null when that day
     * is closed or is a closure date.
     *
     * @return array{open: CarbonImmutable, close: CarbonImmutable}|null
     */
    private function windowOn(CarbonImmutable $day): ?array
    {
        $hours = $this->config->hours();

        if (in_array($day->toDateString(), $hours['closures'] ?? [], true)) {
            return null;
        }

        $entry = $hours['weekly'][strtolower($day->format('D'))] ?? 'closed';

        if (! is_array($entry)) {
            return null;
        }

        return [
            'open' => $day->setTimeFromTimeString($entry['open']),
            'close' => $day->setTimeFromTimeString($entry['close']),
        ];
    }

    private function hasMarginBeforeClose(CarbonImmutable $local, CarbonImmutable $close): bool
    {
        $marginMinutes = (int) config('gate.next_open.today_margin_minutes');

        return $local->addMinutes($marginMinutes) <= $close;
    }

    /**
     * "8 am" on the hour, "8:30 am" otherwise.
     */
    private function timeWords(CarbonImmutable $time): string
    {
        return $time->minute === 0 ? $time->format('g a') : $time->format('g:i a');
    }

    /**
     * "today", "tomorrow" or the weekday name, counted in calendar days of the business zone.
     */
    private function dayWords(CarbonImmutable $local, CarbonImmutable $opening): string
    {
        return match ($opening->toDateString()) {
            $local->toDateString() => 'today',
            $local->addDay()->toDateString() => 'tomorrow',
            default => $opening->format('l'),
        };
    }
}
