<?php

use App\Enums\AppointmentActor;
use App\Enums\AppointmentState;
use App\Icm\ActiveConfig;
use App\Icm\BusinessClock;
use App\Models\Appointment;
use App\Models\ScheduleBlock;
use App\Models\User;
use App\Rooms\ScheduleAndBooking\Availability;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;

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

/**
 * A booked appointment holding the span from $start for $minutes, both given on the business wall clock. The span is
 * stored in UTC, which is how every real write stores it.
 */
function availabilityAppointment(string $start, int $minutes = 90): Appointment
{
    return Appointment::factory()->at(availabilityChicago($start)->utc(), $minutes)->create();
}

/**
 * A booked block holding the span from $start for $minutes, both given on the business wall clock.
 */
function availabilityBlock(string $start, int $minutes): ScheduleBlock
{
    return ScheduleBlock::factory()->at(availabilityChicago($start), $minutes)->create();
}

/**
 * Set the booking file's step and days ahead before the folder goes live.
 */
function availabilityBookingFile(int $step = 60, int $daysAhead = 14, int $buffer = 30): void
{
    breakFile('rooms/schedule_and_booking/booking.md', fn (string $file): string => str_replace(
        ['step_minutes: 60', 'days_ahead: 14', 'drive_buffer_minutes: 30'],
        ['step_minutes: '.$step, 'days_ahead: '.$daysAhead, 'drive_buffer_minutes: '.$buffer],
        $file,
    ));
}

/**
 * The example hours with no lead time, so a start is held back only by the moment itself.
 */
function availabilityNoLead(): void
{
    breakFile('business/hours.md', fn (string $hours): string => str_replace('booking_lead_minutes: 120', 'booking_lead_minutes: 0', $hours));
}

it('counts the lead from the next opening after hours', function () {
    activateExampleFolder();

    $days = app(Availability::class)->openStarts(availabilityService(), availabilityChicago('2026-11-02 22:00'));

    expect(array_key_first($days))->toBe('2026-11-03')
        ->and(availabilityTimes($days, '2026-11-03')[0])->toBe('10:00');
});

it('counts the lead from now during open hours', function () {
    availabilityBookingFile(step: 20);
    activateExampleFolder();

    $days = app(Availability::class)->openStarts(availabilityService(), availabilityChicago('2026-11-03 11:20'));
    $tuesday = availabilityTimes($days, '2026-11-03');

    expect($tuesday[0])->toBe('13:20')
        ->and($tuesday)->not->toContain('13:00');
});

it('offers 14:00 and 15:00 on an hourly step when now is 11:20', function () {
    activateExampleFolder();

    $days = app(Availability::class)->openStarts(availabilityService(), availabilityChicago('2026-11-03 11:20'));

    expect(availabilityTimes($days, '2026-11-03'))->toBe(['14:00', '15:00']);
});

it('never offers a start that has passed', function () {
    activateExampleFolder();

    $days = app(Availability::class)->openStarts(availabilityService(), availabilityChicago('2026-11-03 16:30'));

    expect($days)->not->toHaveKey('2026-11-03');
});

it('never offers the start at the moment asked, with no lead time', function () {
    availabilityNoLead();
    activateExampleFolder();

    $days = app(Availability::class)->openStarts(availabilityService(), availabilityChicago('2026-11-03 13:00'));

    expect(availabilityTimes($days, '2026-11-03'))->toBe(['14:00', '15:00']);
});

it('skips closed days and offers a short Saturday', function () {
    activateExampleFolder();

    $days = app(Availability::class)->openStarts(availabilityService(), availabilityChicago('2026-11-03 07:00'));

    expect($days)->not->toHaveKey('2026-11-08')
        ->and(availabilityTimes($days, '2026-11-07'))->toBe(['09:00', '10:00', '11:00']);
});

it('skips a closure date', function () {
    activateExampleFolder();

    $days = app(Availability::class)->openStarts(availabilityService(), availabilityChicago('2026-11-23 07:00'));

    expect($days)->not->toHaveKey('2026-11-26')
        ->and($days)->toHaveKey('2026-11-27');
});

it('offers only the days the booking file allows', function () {
    activateExampleFolder();

    $days = app(Availability::class)->openStarts(availabilityService(), availabilityChicago('2026-11-03 07:00'));

    expect(array_key_last($days))->toBe('2026-11-16');

    availabilityBookingFile(daysAhead: 3);
    activateExampleFolder();

    $short = app(Availability::class)->openStarts(availabilityService(), availabilityChicago('2026-11-03 07:00'));

    expect(array_keys($short))->toBe(['2026-11-03', '2026-11-04', '2026-11-05']);
});

it('ends a job by closing and offers a start whose end is exactly closing', function () {
    activateExampleFolder();
    $service = array_merge(availabilityService(), ['duration_minutes' => 60]);

    $days = app(Availability::class)->openStarts($service, availabilityChicago('2026-11-03 07:00'));
    $tuesday = availabilityTimes($days, '2026-11-03');

    expect(end($tuesday))->toBe('16:00')
        ->and($tuesday)->not->toContain('17:00');
});

it('keeps the drive buffer after an appointment', function () {
    activateExampleFolder();
    availabilityAppointment('2026-11-03 12:00', 90);

    $days = app(Availability::class)->openStarts(availabilityService(), availabilityChicago('2026-11-03 07:00'));

    expect(availabilityTimes($days, '2026-11-03'))->toBe(['10:00', '14:00', '15:00']);
});

it('gives a block no buffer of its own', function () {
    activateExampleFolder();
    availabilityBlock('2026-11-03 12:00', 60);

    $days = app(Availability::class)->openStarts(availabilityService(), availabilityChicago('2026-11-03 07:00'));

    expect(availabilityTimes($days, '2026-11-03'))->toBe(['10:00', '13:00', '14:00', '15:00']);
});

it('ignores cancelled and done appointments and removed blocks', function () {
    activateExampleFolder();
    Appointment::factory()->cancelled()->at(availabilityChicago('2026-11-03 12:00')->utc(), 90)->create();
    Appointment::factory()->done()->at(availabilityChicago('2026-11-03 10:00')->utc(), 90)->create();
    ScheduleBlock::factory()->at(availabilityChicago('2026-11-03 13:00'), 60)->create([
        'state' => AppointmentState::Cancelled,
        'cancelled_by' => AppointmentActor::User,
        'cancelled_by_user_id' => User::factory(),
        'cancelled_at' => now(),
    ]);

    $days = app(Availability::class)->openStarts(availabilityService(), availabilityChicago('2026-11-03 07:00'));

    expect(availabilityTimes($days, '2026-11-03'))->toBe(['10:00', '11:00', '12:00', '13:00', '14:00', '15:00']);
});

it('counts against capacity', function () {
    activateExampleFolder();
    $availability = app(Availability::class);
    $start = availabilityChicago('2026-11-03 13:00');
    $end = availabilityChicago('2026-11-03 14:30');

    expect($availability->overlapCount($start, $end, 0))->toBe(0)
        ->and($availability->isFree($start, $end, 0))->toBeTrue();

    availabilityAppointment('2026-11-03 12:00', 90);

    expect($availability->overlapCount($start, $end, 0))->toBe(1)
        ->and($availability->isFree($start, $end, 0))->toBeFalse();

    availabilityBlock('2026-11-03 13:00', 60);

    expect($availability->overlapCount($start, $end, 0))->toBe(2)
        ->and($availability->isFree($start, $end, 0))->toBeFalse();
});

it('agrees between the list and the single-span check', function () {
    activateExampleFolder();
    availabilityAppointment('2026-11-03 12:00', 90);
    availabilityBlock('2026-11-03 16:00', 60);
    $availability = app(Availability::class);
    $service = availabilityService();

    $offered = availabilityTimes($availability->openStarts($service, availabilityChicago('2026-11-02 22:00')), '2026-11-03');
    $dropped = [];

    foreach (['10:00', '11:00', '12:00', '13:00', '14:00', '15:00'] as $time) {
        $start = availabilityChicago('2026-11-03 '.$time);
        $free = $availability->isFree($start, $start->addMinutes($service['duration_minutes']), 30);

        expect($free)->toBe(in_array($time, $offered, true));

        if (! $free) {
            $dropped[] = $time;
        }
    }

    expect($offered)->toBe(['10:00', '14:00'])
        ->and($dropped)->toBe(['11:00', '12:00', '13:00', '15:00']);
});

it('gives an empty list when every day is blocked', function () {
    activateExampleFolder();
    availabilityBlock('2026-11-03 00:00', 60 * 24 * 40);

    $days = app(Availability::class)->openStarts(availabilityService(), availabilityChicago('2026-11-03 07:00'));

    expect($days)->toBe([]);
});

it('offers nothing with no booking file', function () {
    File::deleteDirectory(icmPath('rooms/schedule_and_booking'));
    activateExampleFolder();

    $availability = app(Availability::class);
    $days = $availability->openStarts(availabilityService(), availabilityChicago('2026-11-03 07:00'));

    expect($days)->toBe([])
        ->and($availability->isFree(availabilityChicago('2026-11-03 10:00'), availabilityChicago('2026-11-03 11:30'), 0))->toBeTrue();
});

it('reads no clock and no folder, and writes nothing', function () {
    $source = File::get(app_path('Rooms/ScheduleAndBooking/Availability.php'));

    expect($source)->not->toMatch('/\bnow\s*\(|::now\s*\(|CarbonImmutable::parse|Carbon::parse|icm\.path|file_get_contents|File::|Storage::|Loader/');
});

/**
 * A folder open every day from 00:00 to 06:00 with no lead time, a 60-minute step and no drive buffer, so the only
 * thing a daylight-saving day can change is which instants fit between opening and closing. Returns a 60-minute service.
 *
 * @return array<string, mixed>
 */
function availabilityEarlyFolder(): array
{
    $days = collect(['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'])
        ->map(fn (string $day): string => '  '.$day.': {open: "00:00", close: "06:00"}')
        ->implode("\n");

    writeIcmFile('business/hours.md', "---\ntype: hours\nweekly:\n{$days}\nclosures: []\nbooking_lead_minutes: 0\n---\nOpen early.\n");
    availabilityBookingFile(buffer: 0);
    activateExampleFolder();

    return array_merge(availabilityService(), ['duration_minutes' => 60]);
}

/**
 * The offered starts of one day as UTC H:i, so two instants that read alike on the wall clock stay apart.
 *
 * @param  list<CarbonImmutable>  $starts
 * @return list<string>
 */
function availabilityUtcTimes(array $starts): array
{
    return array_map(fn (CarbonImmutable $start): string => $start->utc()->format('H:i'), $starts);
}

it('never offers the missing hour on the spring-forward day', function () {
    $service = availabilityEarlyFolder();

    $days = app(Availability::class)->openStarts($service, availabilityChicago('2026-03-07 12:00'));
    $starts = $days['2026-03-08'];
    $window = app(BusinessClock::class)->openWindowOn($starts[0]);

    expect(availabilityUtcTimes($starts))->toBe(['06:00', '07:00', '08:00', '09:00', '10:00'])
        ->and(availabilityTimes($days, '2026-03-08'))->toBe(['00:00', '01:00', '03:00', '04:00', '05:00'])
        ->and(end($starts)->addMinutes(60)->equalTo($window['close']))->toBeTrue();
});

it('offers distinct increasing instants on the fall-back day', function () {
    $service = availabilityEarlyFolder();

    $days = app(Availability::class)->openStarts($service, availabilityChicago('2026-10-31 12:00'));
    $starts = $days['2026-11-01'];
    $window = app(BusinessClock::class)->openWindowOn($starts[0]);
    $gaps = array_map(fn (int $i): int => $starts[$i]->getTimestamp() - $starts[$i - 1]->getTimestamp(), range(1, count($starts) - 1));

    expect(availabilityUtcTimes($starts))->toBe(['05:00', '06:00', '07:00', '08:00', '09:00', '10:00', '11:00'])
        ->and($gaps)->toBe(array_fill(0, 6, 3600))
        ->and(end($starts)->addMinutes(60)->equalTo($window['close']))->toBeTrue();
});

it('answers the tap-time question', function () {
    activateExampleFolder();
    $availability = app(Availability::class);
    $service = availabilityService();
    $now = availabilityChicago('2026-11-02 22:00');
    $start = availabilityChicago('2026-11-03 11:00');

    expect($availability->offers($service, $start, $now))->toBeTrue()
        ->and($availability->offers($service, $start->utc(), $now))->toBeTrue()
        ->and($availability->offers($service, $start->addMinute(), $now))->toBeFalse()
        ->and($availability->offers($service, availabilityChicago('2026-11-03 09:00'), availabilityChicago('2026-11-03 16:30')))->toBeFalse()
        ->and($availability->offers($service, availabilityChicago('2026-11-26 10:00'), availabilityChicago('2026-11-23 07:00')))->toBeFalse();

    availabilityAppointment('2026-11-03 11:00', 90);

    expect($availability->offers($service, $start, $now))->toBeFalse();
});
