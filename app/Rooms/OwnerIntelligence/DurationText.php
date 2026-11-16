<?php

namespace App\Rooms\OwnerIntelligence;

/**
 * The one formatter for time to booked (D-06): the contact screen's line, the dashboard line and records/time-to-booked.md
 * all print a length of time through it, so every place reads alike.
 *
 * Under a minute reads "under a minute". Otherwise the seconds are rounded to the nearest whole minute (half up), and
 * the text depends on that rounded figure: up to and including 90 minutes it is minutes ("42 minutes"), up to and
 * including 48 hours it is hours and minutes ("1 hour 31 minutes"), and past 48 hours it is days and hours ("2 days 4
 * hours"), with the leftover minutes dropped. A part that comes to zero is left out ("2 hours", "2 days"), and 1 of any
 * unit is singular. Callers pass a positive number of seconds.
 */
class DurationText
{
    /**
     * Past this many minutes the text switches from minutes to hours and minutes.
     */
    private const int MINUTES_ONLY_UP_TO = 90;

    /**
     * Past this many minutes (48 hours) the text switches from hours and minutes to days and hours.
     */
    private const int HOURS_UP_TO = 2880;

    /**
     * The length of time in plain words, for example "42 minutes".
     */
    public static function format(int $seconds): string
    {
        if ($seconds < 60) {
            return 'under a minute';
        }

        $minutes = (int) round($seconds / 60);

        if ($minutes <= self::MINUTES_ONLY_UP_TO) {
            return self::unit($minutes, 'minute');
        }

        if ($minutes <= self::HOURS_UP_TO) {
            return self::join(intdiv($minutes, 60), 'hour', $minutes % 60, 'minute');
        }

        return self::join(intdiv($minutes, 1440), 'day', intdiv($minutes % 1440, 60), 'hour');
    }

    /**
     * Two parts side by side, with a part that is zero left out.
     */
    private static function join(int $larger, string $largerWord, int $smaller, string $smallerWord): string
    {
        return $smaller === 0
            ? self::unit($larger, $largerWord)
            : self::unit($larger, $largerWord).' '.self::unit($smaller, $smallerWord);
    }

    private static function unit(int $count, string $word): string
    {
        return $count.' '.$word.($count === 1 ? '' : 's');
    }
}
