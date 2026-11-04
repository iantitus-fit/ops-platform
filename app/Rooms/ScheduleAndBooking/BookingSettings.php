<?php

namespace App\Rooms\ScheduleAndBooking;

use App\Icm\ActiveConfig;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The live booking file, read back typed (D-03). Every booking setting is here except booking_lead_minutes, which stays in
 * business/hours.md. Code holds no default link, confirmation or reminder line: with the room off or no booking file,
 * fromConfig() is null and nothing customer-facing can be built from it. Business configuration is read only through
 * ActiveConfig. The sync has already checked the file (BookingFileType), so the values here are the ones it let through.
 */
final readonly class BookingSettings
{
    /**
     * @param  list<int>  $reminderHoursBefore
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

    private function renderVisitLine(string $line, string $service, CarbonInterface $startsAt, string $zone): string
    {
        return strtr($line, [
            '{service}' => $service,
            '{day}' => self::dayText($startsAt, $zone),
            '{time}' => self::timeText($startsAt, $zone),
        ]);
    }
}
