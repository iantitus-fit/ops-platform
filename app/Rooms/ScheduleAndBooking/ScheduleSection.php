<?php

namespace App\Rooms\ScheduleAndBooking;

use App\Icm\ActiveConfig;
use App\Rooms\RoomSection;
use Throwable;

/**
 * The schedule and booking room's dashboard section. It has its own view so the shared section view and RoomSection
 * itself stay as they are (the leads section does the same). While the booking file is missing it shows one line in
 * place of the platform's standard empty line: the line still names the file that turns the rest on (D-03, 04 D-21).
 * The visits part reads "Nothing booked." with or without the file, because booking by hand works without it (plan
 * 08-14 adds the rows). It only reads. The wording is a placeholder until the wording pass in Phase 11.
 */
class ScheduleSection extends RoomSection
{
    /**
     * What the section says while the booking file is missing.
     */
    public const BOOKING_OFF_LINE = 'Customer texts for booking stay off until rooms/schedule_and_booking/booking.md is added.';

    protected string $view = 'filament.rooms.schedule-section';

    public static function roomKey(): string
    {
        return 'schedule_and_booking';
    }

    /**
     * The visits part. Nothing is booked until plan 08-14 adds the rows.
     *
     * @return list<string>
     */
    protected function contentLines(): array
    {
        return ['Nothing booked.'];
    }

    /**
     * The base class's heading and failure line, with the platform's empty line replaced by the notice about the
     * booking file, and the visits part always shown. A throw while it is built is reported and the section shows the
     * failure line, so the dashboard still loads (05 D-32).
     *
     * @return array{key: string, heading: string, emptyLine: ?string, lines: list<string>, notice: ?string}
     */
    protected function getViewData(): array
    {
        $data = parent::getViewData();

        if ($data['lines'] === [self::FAILED_LINE]) {
            return [...$data, 'notice' => null];
        }

        try {
            $notice = app(ActiveConfig::class)->filesOfType('booking') === [] ? self::BOOKING_OFF_LINE : null;
            $lines = $this->contentLines();
        } catch (Throwable $exception) {
            report($exception);

            return [...$data, 'emptyLine' => null, 'lines' => [self::FAILED_LINE], 'notice' => null];
        }

        return [...$data, 'emptyLine' => null, 'lines' => $lines, 'notice' => $notice];
    }
}
