<?php

use App\Icm\ActiveConfig;
use App\Models\Appointment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

/**
 * The two refusals that protect one owner per fact and booked visits (ROOM-12; D-36, D-37). A connector that owns the
 * schedule while the schedule and booking room is live would leave two owners of it, and a folder that drops the room
 * while a visit is booked ahead would silence that visit's reminders. D-37 is the first sync check that reads the
 * database, so CI runs these cases on SQLite and Postgres. Every refusal leaves the previous version live.
 */
const CALENDAR_CONNECTOR = 'connectors/calendar.md';

/**
 * Lay a connector file that fills the Google Business Profile slot (the one slot the platform has) and owns or reads the
 * schedule. The example's own connector file is removed first, because a slot takes one connector file.
 */
function layScheduleConnector(string $field): void
{
    File::delete(icmPath('connectors/google-business-profile.md'));

    $owns = $field === 'owns' ? '[schedule]' : 'none';
    $reads = $field === 'reads' ? '[schedule]' : 'none';

    writeIcmFile(CALENDAR_CONNECTOR, "---\ntype: connector\nsystem: A calendar\ndirection: in\nslot: google_business_profile\nowns: {$owns}\nreads: {$reads}\nschedule: none\n---\n\n# A calendar\n");
}

/**
 * Drop the schedule and booking room from the business folder.
 */
function dropScheduleRoom(): void
{
    File::deleteDirectory(icmPath('rooms/schedule_and_booking'));
}

it('refuses a connector that owns the schedule while the room is live', function () {
    $live = activateExampleFolder();
    layScheduleConnector('owns');

    $this->artisan('icm:sync')->assertExitCode(1);

    $errors = rejectedErrors();

    expect($errors)->toHaveCount(1)
        ->and($errors[0])->toStartWith(CALENDAR_CONNECTOR.': ')
        ->and($errors[0])->toContain('rooms/schedule_and_booking')
        ->and($errors[0])->toContain('turn the room off, or have the connector read the schedule instead')
        ->and(app(ActiveConfig::class)->versionId())->toBe($live->id);
});

it('lets a connector read the schedule', function () {
    activateExampleFolder();
    layScheduleConnector('reads');

    $this->artisan('icm:sync')->assertExitCode(0);

    expect(app(ActiveConfig::class)->hasRoom('schedule_and_booking'))->toBeTrue();
});

it('lets the room off and a connector owning the schedule pass', function () {
    dropScheduleRoom();
    layScheduleConnector('owns');

    $this->artisan('icm:sync')->assertExitCode(0);

    expect(app(ActiveConfig::class)->hasRoom('schedule_and_booking'))->toBeFalse();
});

/**
 * Two booked visits ahead at the moment the check runs: Thursday 2026-11-05 10:00 and Friday 2026-11-06 09:00 in the
 * business's zone, with "now" Tuesday 2026-11-03.
 */
function bookTwoVisitsAhead(): void
{
    test()->travelTo(Carbon::parse('2026-11-03 12:00', 'America/Chicago'));

    Appointment::factory()->at(Carbon::parse('2026-11-06 09:00', 'America/Chicago'))->create();
    Appointment::factory()->at(Carbon::parse('2026-11-05 10:00', 'America/Chicago'))->create();
}

it('refuses dropping the room with a visit booked ahead', function () {
    $live = activateExampleFolder();
    bookTwoVisitsAhead();
    dropScheduleRoom();

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe(['rooms/schedule_and_booking/CONTEXT.md: the schedule and booking room has 2 booked visits still ahead, the earliest on Thursday, November 5 at 10:00 AM; keep the room, and to stop new bookings set book_online: false on the services instead'])
        ->and(app(ActiveConfig::class)->versionId())->toBe($live->id)
        ->and(app(ActiveConfig::class)->hasRoom('schedule_and_booking'))->toBeTrue();
});

it('says one booked visit in the singular', function () {
    activateExampleFolder();
    test()->travelTo(Carbon::parse('2026-11-03 12:00', 'America/Chicago'));
    Appointment::factory()->at(Carbon::parse('2026-11-05 10:00', 'America/Chicago'))->create();
    dropScheduleRoom();

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors()[0])->toContain('has 1 booked visit still ahead');
});

it('drops the room when no visit is ahead', function (Closure $lay) {
    activateExampleFolder();
    test()->travelTo(Carbon::parse('2026-11-03 12:00', 'America/Chicago'));
    $lay();
    dropScheduleRoom();

    $this->artisan('icm:sync')->assertExitCode(0);

    expect(app(ActiveConfig::class)->hasRoom('schedule_and_booking'))->toBeFalse();
})->with([
    'no visit at all' => fn () => function () {},
    'one cancelled ahead' => fn () => fn () => Appointment::factory()->cancelled()->at(now()->addDay())->create(),
    'one done' => fn () => fn () => Appointment::factory()->done()->at(now()->addDay())->create(),
    'one that started a minute ago' => fn () => fn () => Appointment::factory()->at(now()->subMinute())->create(),
]);

it('blocks on a visit one minute ahead', function () {
    activateExampleFolder();
    test()->travelTo(Carbon::parse('2026-11-03 12:00', 'America/Chicago'));
    Appointment::factory()->at(now()->addMinute())->create();
    dropScheduleRoom();

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors()[0])->toContain('has 1 booked visit still ahead');
});

it('skips the check with no live version', function () {
    test()->travelTo(Carbon::parse('2026-11-03 12:00', 'America/Chicago'));
    Appointment::factory()->at(now()->addDay())->create();
    dropScheduleRoom();

    $this->artisan('icm:sync')->assertExitCode(0);

    expect(app(ActiveConfig::class)->hasRoom('schedule_and_booking'))->toBeFalse();
});
