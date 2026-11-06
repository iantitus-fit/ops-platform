<?php

namespace App\Rooms\ScheduleAndBooking;

use Carbon\CarbonImmutable;

/**
 * When the texts about one booking are due, decided once at the moment of booking (D-31). It is a pure calculation: it
 * reads no configuration and writes nothing, so the settings and the business's zone come in as arguments.
 *
 * The confirmation is lead 0 (D-28). A page booking's confirmation is due at once whatever the hour. A hand booking's
 * is due at once inside the send window and otherwise at the window's next opening, and is not written when that
 * opening is at or after the visit's start.
 *
 * Each reminder is due its lead in hours before the start, in elapsed time (D-29). One due outside the send window goes
 * at the end of the window before: before the window opens it moves to the previous day's window end, after it closes
 * to that day's window end. Both window ends count as inside. A reminder whose time, or moved time, was already past
 * when the booking was made gets no row and is not sent late. Two reminders that land on the same moment keep one row,
 * the one with the larger lead.
 *
 * Window days are found on the business wall clock (D-11): the previous or next day is a calendar day in the zone, and
 * the window's time is set on it, so a daylight-saving change cannot shift the wall time by an hour.
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
        $rows = [];

        $confirmationDue = $pageBooking ? $bookedAt : self::confirmationDue($startsAt, $bookedAt, $settings, $zone);

        if ($confirmationDue !== null) {
            $rows[] = [
                'lead_minutes' => 0,
                'due_at' => $confirmationDue,
                'ignores_window' => $pageBooking,
            ];
        }

        $reminders = [];

        foreach ($settings->reminderHoursBefore as $hours) {
            $due = self::reminderDue($startsAt->subHours($hours), $settings, $zone);

            if ($due->lessThanOrEqualTo($bookedAt)) {
                continue;
            }

            $moment = $due->getTimestamp();
            $lead = $hours * 60;

            if (! isset($reminders[$moment]) || $reminders[$moment]['lead_minutes'] < $lead) {
                $reminders[$moment] = [
                    'lead_minutes' => $lead,
                    'due_at' => $due,
                    'ignores_window' => false,
                ];
            }
        }

        $rows = [...$rows, ...array_values($reminders)];

        usort($rows, fn (array $a, array $b): int => [$a['due_at']->getTimestamp(), $a['lead_minutes']] <=> [$b['due_at']->getTimestamp(), $b['lead_minutes']]);

        return $rows;
    }

    /**
     * A hand booking's confirmation: now when inside the send window, else the window's next opening; null when that
     * opening is at or after the visit's start (D-28).
     */
    private static function confirmationDue(CarbonImmutable $startsAt, CarbonImmutable $bookedAt, BookingSettings $settings, string $zone): ?CarbonImmutable
    {
        $local = $bookedAt->setTimezone($zone);
        $opens = $local->setTimeFromTimeString($settings->sendWindowStart);
        $closes = $local->setTimeFromTimeString($settings->sendWindowEnd);

        if ($local->greaterThanOrEqualTo($opens) && $local->lessThanOrEqualTo($closes)) {
            return $bookedAt;
        }

        $next = $local->lessThan($opens) ? $opens : $local->addDay()->setTimeFromTimeString($settings->sendWindowStart);

        return $next->lessThan($startsAt) ? $next : null;
    }

    /**
     * Where a reminder due at $due really goes: itself inside the send window, else the end of the window before it
     * (D-29).
     */
    private static function reminderDue(CarbonImmutable $due, BookingSettings $settings, string $zone): CarbonImmutable
    {
        $local = $due->setTimezone($zone);

        if ($local->lessThan($local->setTimeFromTimeString($settings->sendWindowStart))) {
            return $local->subDay()->setTimeFromTimeString($settings->sendWindowEnd);
        }

        if ($local->greaterThan($local->setTimeFromTimeString($settings->sendWindowEnd))) {
            return $local->setTimeFromTimeString($settings->sendWindowEnd);
        }

        return $due;
    }
}
