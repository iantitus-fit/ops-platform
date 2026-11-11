<?php

namespace App\Rooms\ScheduleAndBooking;

use App\Icm\ActiveConfig;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use IntlDateFormatter;

/**
 * The live booking file, read back typed (D-03). Every booking setting is here except booking_lead_minutes, which stays in
 * business/hours.md. Code holds no default link, confirmation, reminder or visit line: with the room off or no booking file,
 * fromConfig() is null and nothing customer-facing can be built from it. Business configuration is read only through
 * ActiveConfig. The sync has already checked the file (BookingFileType), so the values here are the ones it let through.
 */
final readonly class BookingSettings
{
    /**
     * @param  list<int>  $reminderHoursBefore
     * @param  ?string  $visitLine  Null only for a live version compiled before visit_line existed (D-39 amended).
     */
    public function __construct(
        public int $stepMinutes,
        public int $driveBufferMinutes,
        public int $daysAhead,
        public int $linkExpiresDays,
        public array $reminderHoursBefore,
        public string $sendWindowStart,
        public string $sendWindowEnd,
        public string $linkLine,
        public string $confirmationLine,
        public string $reminderLine,
        public ?string $visitLine = null,
    ) {}

    /**
     * The settings of the live version, or null when the room is not live or the folder has no booking file.
     */
    public static function fromConfig(ActiveConfig $config): ?self
    {
        if (! $config->hasRoom('schedule_and_booking')) {
            return null;
        }

        $file = $config->filesOfType('booking')[0] ?? null;

        if ($file === null) {
            return null;
        }

        return new self(
            stepMinutes: (int) $file['step_minutes'],
            driveBufferMinutes: (int) $file['drive_buffer_minutes'],
            daysAhead: (int) $file['days_ahead'],
            linkExpiresDays: (int) $file['link_expires_days'],
            reminderHoursBefore: array_values(array_map('intval', $file['reminder_hours_before'])),
            sendWindowStart: (string) $file['send_window']['start'],
            sendWindowEnd: (string) $file['send_window']['end'],
            linkLine: (string) $file['link_line'],
            confirmationLine: (string) $file['confirmation_line'],
            reminderLine: (string) $file['reminder_line'],
            visitLine: isset($file['visit_line']) ? (string) $file['visit_line'] : null,
        );
    }

    /**
     * The owner's link line with the booking link put in.
     */
    public function renderLink(string $url): string
    {
        return strtr($this->linkLine, ['{link}' => $url]);
    }

    /**
     * The owner's confirmation line for a visit, with the day and time in the business's zone.
     */
    public function renderConfirmation(string $service, CarbonInterface $startsAt, string $zone): string
    {
        return $this->renderVisitLine($this->confirmationLine, $service, $startsAt, $zone);
    }

    /**
     * The owner's reminder line for a visit, with the day and time in the business's zone.
     */
    public function renderReminder(string $service, CarbonInterface $startsAt, string $zone): string
    {
        return $this->renderVisitLine($this->reminderLine, $service, $startsAt, $zone);
    }

    /**
     * The owner's visit line for a booked visit the customer asked about (D-39 amended), with the service, day and time
     * in the business's zone. Null only for a live version compiled before visit_line existed, which the sync requires
     * from plan 08-24 on. Code still holds no default line (D-03), so such a version sends no visit line until the owner
     * syncs again, the same shape as ActiveConfig::projectWord()'s older-version case (07-01).
     */
    public function renderVisit(string $service, CarbonInterface $startsAt, string $zone): ?string
    {
        return $this->visitLine === null ? null : $this->renderVisitLine($this->visitLine, $service, $startsAt, $zone);
    }

    /**
     * The day as a customer reads it, in the given zone: "Thursday, November 5".
     */
    public static function dayText(CarbonInterface $at, string $zone): string
    {
        return CarbonImmutable::instance($at)->setTimezone($zone)->format('l, F j');
    }

    /**
     * The time as a customer reads it, in the given zone: "10:00 AM".
     */
    public static function timeText(CarbonInterface $at, string $zone): string
    {
        return CarbonImmutable::instance($at)->setTimezone($zone)->format('g:i A');
    }

    /**
     * A span as the owner reads it after its start day, in the given zone: "8:00 AM to 12:00 PM" on one day, and with
     * the end's day named when the span crosses midnight: "8:00 AM to Tuesday, November 3, 12:00 PM". A caller puts the
     * start's dayText() and a comma in front.
     */
    public static function spanText(CarbonInterface $from, CarbonInterface $to, string $zone): string
    {
        if (self::dayText($from, $zone) === self::dayText($to, $zone)) {
            return self::timeText($from, $zone).' to '.self::timeText($to, $zone);
        }

        return self::timeText($from, $zone).' to '.self::dayText($to, $zone).', '.self::timeText($to, $zone);
    }

    /**
     * The business zone as a customer says it: ICU's generic long name ("Central Time"), the IANA id when ICU gives none
     * (U-11). It does not change with daylight time and is never read from the visitor's phone (D-11).
     */
    public static function zoneName(string $zone): string
    {
        $name = (new IntlDateFormatter('en_US', IntlDateFormatter::NONE, IntlDateFormatter::NONE, $zone, IntlDateFormatter::GREGORIAN, 'vvvv'))
            ->format(CarbonImmutable::now());

        return is_string($name) && $name !== '' ? $name : $zone;
    }

    private function renderVisitLine(string $line, string $service, CarbonInterface $startsAt, string $zone): string
    {
        return strtr($line, [
            '{service}' => $service,
            '{day}' => self::dayText($startsAt, $zone),
            '{time}' => self::timeText($startsAt, $zone),
        ]);
    }
}
