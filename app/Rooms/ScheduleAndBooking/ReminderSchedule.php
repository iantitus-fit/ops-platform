<?php

namespace App\Rooms\ScheduleAndBooking;

use Carbon\CarbonImmutable;

/**
 * When the texts about one booking are due, decided once at the moment of booking (D-31). It is a pure calculation: it
 * reads no configuration and writes nothing, so the settings and the business's zone come in as arguments (D-03 and
 * the architecture rule that business configuration is read only through ActiveConfig).
 *
 * The confirmation is lead 0 (D-28). Each reminder is its lead in hours before the start, in elapsed time (D-29).
 */
final class ReminderSchedule
{
    /**
     * The rows to store for a booking, earliest due first.
     *
     * @return list<array{lead_minutes: int, due_at: CarbonImmutable, ignores_window: bool}>
     */
    public static function rows(CarbonImmutable $startsAt, CarbonImmutable $bookedAt, bool $pageBooking, BookingSettings $settings, string $zone): array
    {
        $rows = [[
            'lead_minutes' => 0,
            'due_at' => $bookedAt,
            'ignores_window' => $pageBooking,
        ]];

        foreach ($settings->reminderHoursBefore as $hours) {
            $rows[] = [
                'lead_minutes' => $hours * 60,
                'due_at' => $startsAt->subHours($hours),
                'ignores_window' => false,
            ];
        }

        return $rows;
    }
}
