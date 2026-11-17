<?php

use App\Icm\ActiveConfig;
use App\Models\Appointment;
use App\Models\Contact;
use App\Records\CustomerCard;
use App\Records\RecordsWriter;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Mockery\MockInterface;

/**
 * RECS-02, D-09 and D-10: the platform's nightly records:rebuild is on the scheduler every minute, and a filter lets it
 * through on any tick from 00:10 to before 01:00 in the business's own zone, once per business date: a marker for the
 * date is set only after a run that succeeded, so a late tick still runs it and a failed run is tried again on the next
 * tick (09-REVIEW WR-03, Ian, 2026-11-17). Every time is written as America/Chicago wall time. Pest functions are
 * global, so every helper here carries the nightlyRebuild prefix.
 */

/**
 * A moment on the given day in the business zone.
 */
function nightlyRebuildChicago(string $day, string $clock): CarbonImmutable
{
    return CarbonImmutable::parse("{$day} {$clock}", 'America/Chicago');
}

/**
 * The one scheduled event named records:rebuild.
 */
function nightlyRebuildEvent(): Event
{
    $events = collect(app(Schedule::class)->events())
        ->filter(fn (Event $event): bool => $event->description === 'records:rebuild');

    expect($events)->toHaveCount(1);

    return $events->first();
}

/**
 * Whether the scheduler would let the rebuild through at this moment: the filters alone, as schedule:run asks them.
 */
function nightlyRebuildPassesAt(CarbonImmutable $moment): bool
{
    test()->travelTo($moment);

    return nightlyRebuildEvent()->filtersPass(app());
}

/**
 * One scheduler tick at this moment, taken the way schedule:run takes it: the filters, then the command, then the
 * event's after callbacks with the command's exit code. The command runs in this process through Artisan::call instead
 * of a child process, so it sees the test's database and folder. Returns the exit code, or null when the filters held
 * the rebuild back.
 */
function nightlyRebuildTick(CarbonImmutable $moment): ?int
{
    if (! nightlyRebuildPassesAt($moment)) {
        return null;
    }

    $exitCode = Artisan::call('records:rebuild');
    nightlyRebuildEvent()->finish(app(), $exitCode);

    return $exitCode;
}

it('schedules records:rebuild every minute, one at a time, on one server (D-10)', function () {
    $event = nightlyRebuildEvent();

    expect($event->command)->toContain('records:rebuild')
        ->and($event->expression)->toBe('* * * * *')
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->onOneServer)->toBeTrue()
        ->and($event->expiresAt)->toBe(30);
});

it('lets a tick retry inside the window after a killed run, once its 30-minute lock expires (WR-03)', function () {
    activateExampleFolder();
    $event = nightlyRebuildEvent();

    // A run starts at 00:10 and is killed: its overlap lock is never released and no date marker is set.
    test()->travelTo(nightlyRebuildChicago('2026-11-14', '00:10'));
    expect($event->mutex->create($event))->toBeTrue();

    test()->travelTo(nightlyRebuildChicago('2026-11-14', '00:39'));
    expect($event->mutex->exists($event))->toBeTrue();

    test()->travelTo(nightlyRebuildChicago('2026-11-14', '00:40'));
    expect($event->mutex->exists($event))->toBeFalse()
        ->and(nightlyRebuildTick(nightlyRebuildChicago('2026-11-14', '00:40')))->toBe(0);
});

it('lets it through from 00:10 to before 01:00 in the business zone (D-10, WR-03)', function () {
    activateExampleFolder();

    expect(nightlyRebuildPassesAt(nightlyRebuildChicago('2026-11-14', '00:10')))->toBeTrue()
        ->and(nightlyRebuildPassesAt(nightlyRebuildChicago('2026-11-14', '00:09')))->toBeFalse()
        ->and(nightlyRebuildPassesAt(nightlyRebuildChicago('2026-11-14', '00:11')))->toBeTrue()
        ->and(nightlyRebuildPassesAt(nightlyRebuildChicago('2026-11-14', '00:59')))->toBeTrue()
        ->and(nightlyRebuildPassesAt(nightlyRebuildChicago('2026-11-14', '01:00')))->toBeFalse()
        ->and(nightlyRebuildPassesAt(CarbonImmutable::parse('2026-11-14 00:10', 'UTC')))->toBeFalse();
});

it('runs it on a late tick at 00:25 when 00:10 was missed (WR-03)', function () {
    activateExampleFolder();

    $this->travelTo(nightlyRebuildChicago('2026-11-13', '20:00'));
    app(RecordsWriter::class)->writeOwnerIntelligence();

    expect(nightlyRebuildTick(nightlyRebuildChicago('2026-11-14', '00:25')))->toBe(0)
        ->and(Str::before(File::get(icmPath('records/today.md')), "\n"))->toBe('# Schedule for Saturday, November 14');
});

it('runs it once per business date: a second tick the same date does not (WR-03)', function () {
    activateExampleFolder();

    expect(nightlyRebuildTick(nightlyRebuildChicago('2026-11-14', '00:10')))->toBe(0)
        ->and(nightlyRebuildTick(nightlyRebuildChicago('2026-11-14', '00:10')))->toBeNull()
        ->and(nightlyRebuildTick(nightlyRebuildChicago('2026-11-14', '00:30')))->toBeNull()
        ->and(nightlyRebuildTick(nightlyRebuildChicago('2026-11-15', '00:10')))->toBe(0);
});

it('tries a failed run again on the next tick (WR-03)', function () {
    activateExampleFolder();
    $folder = icmPath();
    File::moveDirectory($folder, $folder.'-away');

    expect(nightlyRebuildTick(nightlyRebuildChicago('2026-11-14', '00:10')))->toBe(1);

    File::moveDirectory($folder.'-away', $folder);

    expect(nightlyRebuildTick(nightlyRebuildChicago('2026-11-14', '00:11')))->toBe(0)
        ->and(nightlyRebuildTick(nightlyRebuildChicago('2026-11-14', '00:12')))->toBeNull();
});

it('turns today.md over to the new day (D-10)', function () {
    activateExampleFolder();

    $this->travelTo(nightlyRebuildChicago('2026-11-13', '20:00'));
    app(RecordsWriter::class)->writeOwnerIntelligence();

    expect(Str::before(File::get(icmPath('records/today.md')), "\n"))->toBe('# Schedule for Friday, November 13');

    expect(nightlyRebuildPassesAt(nightlyRebuildChicago('2026-11-14', '00:10')))->toBeTrue();

    $this->artisan('records:rebuild')->assertExitCode(0);

    expect(Str::before(File::get(icmPath('records/today.md')), "\n"))->toBe('# Schedule for Saturday, November 14');
});

it('reads nothing when the schedule loads (Pitfall 1)', function () {
    DB::enableQueryLog();
    DB::flushQueryLog();

    require base_path('routes/console.php');

    expect(DB::getQueryLog())->toBeEmpty();
});

it('follows a changed zone after a sync (D-10)', function () {
    activateExampleFolder();

    breakFile('business/profile.md', fn (string $file): string => str_replace('timezone: America/Chicago', 'timezone: America/New_York', $file));
    $this->artisan('icm:sync')->assertExitCode(0);

    expect(nightlyRebuildPassesAt(nightlyRebuildChicago('2026-11-14', '00:10')))->toBeFalse()
        ->and(nightlyRebuildPassesAt(CarbonImmutable::parse('2026-11-14 00:10', 'America/New_York')))->toBeTrue();
});

it('runs once on each daylight-saving day (D-10, WR-03)', function (string $day, int $minutes) {
    activateExampleFolder();

    $start = nightlyRebuildChicago($day, '00:00')->utc();
    $runs = 0;

    for ($minute = 0; $minute < $minutes; $minute++) {
        $runs += nightlyRebuildTick($start->addMinutes($minute)) === 0 ? 1 : 0;
    }

    expect(nightlyRebuildChicago($day, '00:00')->addDay()->utc()->diffInMinutes($start, true))->toBe((float) $minutes)
        ->and($runs)->toBe(1);
})->with([
    'the 25-hour fall-back day' => ['2026-11-01', 1500],
    'the 23-hour spring-forward day' => ['2026-03-08', 1380],
]);

it('reports a bad zone read and lets the pass go on', function () {
    Exceptions::fake();
    $this->mock(ActiveConfig::class, function (MockInterface $mock): void {
        $mock->shouldReceive('displayTimezone')->andThrow(new RuntimeException('the zone could not be read'));
    });

    expect(nightlyRebuildEvent()->filtersPass(app()))->toBeFalse();

    Exceptions::assertReported(RuntimeException::class);
});

it('runs with the owner intelligence room off (D-09)', function () {
    File::deleteDirectory(icmPath('rooms/owner_intelligence'));
    activateExampleFolder();
    $dana = Contact::factory()->create(['name' => 'Dana Whitfield']);

    expect(nightlyRebuildPassesAt(nightlyRebuildChicago('2026-11-14', '00:10')))->toBeTrue();

    $this->artisan('records:rebuild')->assertExitCode(0);

    expect(File::exists(icmPath('records/customers/'.app(CustomerCard::class)->filename($dana))))->toBeTrue()
        ->and(File::exists(icmPath('records/today.md')))->toBeFalse();
});

it('clears a Next appointment line for a visit that started yesterday (D-11, 08 IN-06)', function () {
    activateExampleFolder();
    $dana = Contact::factory()->create(['name' => 'Dana Whitfield']);
    $card = icmPath('records/customers/'.app(CustomerCard::class)->filename($dana));

    $this->travelTo(nightlyRebuildChicago('2026-11-13', '08:00'));
    Appointment::factory()->at(nightlyRebuildChicago('2026-11-13', '09:00')->utc(), 90)->create(['contact_id' => $dana->id]);
    app(RecordsWriter::class)->writeCard($dana);

    expect(File::get($card))->toContain('- Next appointment:');

    expect(nightlyRebuildPassesAt(nightlyRebuildChicago('2026-11-14', '00:10')))->toBeTrue();

    $this->artisan('records:rebuild')->assertExitCode(0);

    expect(File::get($card))->not->toContain('- Next appointment:');
});
