<?php

namespace App\Rooms\ScheduleAndBooking;

use App\Filament\Pages\Schedule;
use App\Filament\Resources\Contacts\ContactResource;
use App\Icm\ActiveConfig;
use App\Models\Appointment;
use App\Rooms\RoomSection;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * The schedule and booking room's dashboard section (D-03, D-40, U-30): the next booked visits, today's first, at most
 * five, one row each. A row carries a link to the contact's screen and an absolute date and time inside a time element,
 * the service and the job, drawn the way the leads section draws its rows. Past five it says how many are booked and links
 * to the Schedule page. A block and a cancelled or done visit never appear, because the section reads the scoped
 * Appointment model's booked visits only (A3).
 *
 * The section has its own view so the shared section view and RoomSection itself stay as they are (the leads section does
 * the same). While the booking file is missing it shows one line in place of the platform's standard empty line: the line
 * still names the file that turns the rest on (D-03, 04 D-21). The visits part reads the same with or without the file,
 * because booking by hand works without it. It only reads. The wording is a placeholder until the wording pass in Phase 11.
 */
class ScheduleSection extends RoomSection
{
    /**
     * What the section says while the booking file is missing.
     */
    public const BOOKING_OFF_LINE = 'Customer texts for booking stay off until rooms/schedule_and_booking/booking.md is added.';

    /**
     * The most rows the section draws; every visit stays on the Schedule page.
     */
    public const int SHOWN = 5;

    protected string $view = 'filament.rooms.schedule-section';

    public static function roomKey(): string
    {
        return 'schedule_and_booking';
    }

    /**
     * The base class's heading and failure line, with the platform's empty line replaced by the notice about the booking
     * file, and the visits part always shown. A throw while it is built is reported and the section shows the failure
     * line, so the dashboard still loads (05 D-32).
     *
     * @return array{key: string, heading: string, emptyLine: ?string, lines: list<string>, notice: ?string, rows: list<array{contact: string, url: string, when: string, datetime: string, service: string, job: string}>, moreUrl: ?string}
     */
    protected function getViewData(): array
    {
        $data = parent::getViewData();

        if ($data['lines'] === [self::FAILED_LINE]) {
            return [...$data, 'notice' => null, 'rows' => [], 'moreUrl' => null];
        }

        try {
            $notice = app(ActiveConfig::class)->filesOfType('booking') === [] ? self::BOOKING_OFF_LINE : null;
            ['rows' => $rows, 'total' => $total] = $this->visits();
            $moreUrl = $total > self::SHOWN ? Schedule::getUrl() : null;
        } catch (Throwable $exception) {
            report($exception);

            return [...$data, 'emptyLine' => null, 'lines' => [self::FAILED_LINE], 'notice' => null, 'rows' => [], 'moreUrl' => null];
        }

        $lines = match (true) {
            $total === 0 => ['Nothing booked.'],
            $total > self::SHOWN => ['Showing the next '.self::SHOWN." of {$total} booked visits."],
            default => [],
        };

        return [...$data, 'emptyLine' => null, 'lines' => $lines, 'notice' => $notice, 'rows' => $rows, 'moreUrl' => $moreUrl];
    }

    /**
     * The booked visits from the start of today in the business's zone, soonest first, with each contact and job loaded
     * up front so the number of queries does not grow with the number of rows, and how many there are in all.
     *
     * @return array{rows: list<array{contact: string, url: string, when: string, datetime: string, service: string, job: string}>, total: int}
     */
    private function visits(): array
    {
        $config = app(ActiveConfig::class);
        $word = $config->projectWord();
        $zone = $config->displayTimezone();
        $visits = Appointment::query()->booked()->where('appointments.starts_at', '>=', CarbonImmutable::now($zone)->startOfDay()->utc());

        return [
            'total' => (clone $visits)->count(),
            'rows' => $visits
                ->with(['contact', 'project' => fn ($project) => $project->withNumber()])
                ->orderBy('appointments.starts_at')
                ->orderBy('appointments.id')
                ->limit(self::SHOWN)
                ->get()
                ->map(fn (Appointment $visit): array => [
                    'contact' => $visit->contact?->name ?: '(no name)',
                    'url' => ContactResource::getUrl('view', ['record' => $visit->contact]),
                    'when' => BookingSettings::dayText($visit->starts_at, $zone).' at '.BookingSettings::timeText($visit->starts_at, $zone),
                    'datetime' => CarbonImmutable::instance($visit->starts_at)->setTimezone($zone)->toIso8601String(),
                    'service' => $visit->service_name,
                    'job' => $visit->project === null ? $word->singular() : $visit->project->label($word, (int) $visit->project->number),
                ])
                ->all(),
        ];
    }
}
