<?php

use App\Icm\ActiveConfig;
use App\Rooms\ScheduleAndBooking\Availability;
use Carbon\CarbonImmutable;

/**
 * A moment on the wall clock of the example business (America/Chicago).
 */
function availabilityChicago(string $dateTime): CarbonImmutable
{
    return CarbonImmutable::parse($dateTime, 'America/Chicago');
}

/**
 * One service of the live folder, as ActiveConfig reads it.
 *
 * @return array<string, mixed>
 */
function availabilityService(string $slug = 'ac-repair'): array
{
    return app(ActiveConfig::class)->service($slug);
}

/**
 * The offered starts of one day as H:i in the business zone, or an empty list when the day has no heading.
 *
 * @param  array<string, list<CarbonImmutable>>  $days
 * @return list<string>
 */
function availabilityTimes(array $days, string $date): array
{
    return array_map(fn (CarbonImmutable $start): string => $start->format('H:i'), $days[$date] ?? []);
}

it("offers the example weekday's starts for AC repair", function () {
    activateExampleFolder();

    $days = app(Availability::class)->openStarts(availabilityService('ac-repair'), availabilityChicago('2026-11-03 07:00'));

    expect(array_key_first($days))->toBe('2026-11-03')
        ->and(availabilityTimes($days, '2026-11-03'))->toBe(['10:00', '11:00', '12:00', '13:00', '14:00', '15:00']);
});
