<?php

use App\Actions\BlockTime;
use App\Actions\BookAppointment;
use App\Actions\CancelAppointment;
use App\Actions\EditVisitNote;
use App\Actions\MarkAppointmentDone;
use App\Actions\RemoveBlock;
use App\Enums\AppointmentState;
use App\Events\ScheduleChanged;
use App\Jobs\RedrawOwnerIntelligenceRecords;
use App\Models\Appointment;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\ScheduleBlock;
use App\Models\User;
use App\Records\RecordsWriter;
use App\Rooms\OwnerIntelligence\TodayRecord;
use App\Rooms\ScheduleAndBooking\BookingRefused;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Attributes\UniqueFor;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;

require_once __DIR__.'/../Booking/BookingPageHelpers.php';

/**
 * RECS-02 and D-14: records/today.md is redrawn after each of the six named actions commits, through one queued, unique
 * redraw. A sync that turns the owner intelligence or schedule room on or off redraws at once (D-15). Every time is
 * written as America/Chicago wall time. Pest functions are global, so every helper here carries the redraw prefix.
 */

/**
 * A moment on 2026-11-13 in the business zone.
 */
function redrawChicago(string $clock): CarbonImmutable
{
    return CarbonImmutable::parse("2026-11-13 {$clock}", 'America/Chicago');
}

/**
 * The live example folder, the clock at 08:00 on 2026-11-13, an owner, and Dana Whitfield, who texted.
 *
 * @return array{0: Contact, 1: User}
 */
function redrawSetUp(): array
{
    activateExampleFolder();
    test()->travelTo(redrawChicago('08:00'));

    $owner = User::factory()->create(['name' => 'Ian Owner']);
    $dana = Contact::factory()->create(['name' => 'Dana Whitfield']);
    $conversation = Conversation::factory()->create(['contact_id' => $dana->id]);
    Message::factory()->create(['conversation_id' => $conversation->id, 'body' => 'My AC is blowing warm air']);

    return [$dana, $owner];
}

it('redraws today.md after a visit is booked by hand (D-14)', function () {
    [$dana, $owner] = redrawSetUp();

    expect(File::exists(icmPath('records/today.md')))->toBeFalse();

    app(BookAppointment::class)->byHand($dana, $owner, 'ac-repair', redrawChicago('13:00'), null, null);

    expect(File::exists(icmPath('records/today.md')))->toBeTrue()
        ->and(File::exists(icmPath('records/time-to-booked.md')))->toBeTrue()
        ->and(File::get(icmPath('records/today.md')))
        ->toContain('1:00 PM to 2:30 PM: AC repair for [Dana Whitfield]')
        ->toContain(', booked');
});

it('redraws only after the transaction commits', function () {
    Queue::fake([RedrawOwnerIntelligenceRecords::class]);

    try {
        DB::transaction(function (): void {
            ScheduleChanged::dispatch();

            throw new RuntimeException('rolled back');
        });
    } catch (RuntimeException) {
        // The write rolled back, so nothing was pushed.
    }

    Queue::assertNothingPushed();

    DB::transaction(function (): void {
        ScheduleChanged::dispatch();
    });

    Queue::assertPushed(RedrawOwnerIntelligenceRecords::class, 1);
});

it('queues one redraw for changes made before it starts', function () {
    Queue::fake([RedrawOwnerIntelligenceRecords::class]);

    ScheduleChanged::dispatch();
    ScheduleChanged::dispatch();

    Queue::assertPushed(RedrawOwnerIntelligenceRecords::class, 1);
});

it('is unique until it starts and never overlaps itself', function () {
    $job = new RedrawOwnerIntelligenceRecords;
    $unique = (new ReflectionClass($job))->getAttributes(UniqueFor::class);
    $middleware = $job->middleware();

    $this->freezeTime();

    expect($job)->toBeInstanceOf(ShouldQueue::class)
        ->and($job)->toBeInstanceOf(ShouldBeUniqueUntilProcessing::class)
        ->and($job->uniqueId())->toBe('owner-intelligence')
        ->and($unique)->toHaveCount(1)
        ->and($unique[0]->newInstance()->uniqueFor)->toBe(300)
        ->and($middleware)->toHaveCount(1)
        ->and($middleware[0])->toBeInstanceOf(WithoutOverlapping::class)
        ->and($middleware[0]->key)->toBe('owner-intelligence')
        ->and($middleware[0]->releaseAfter)->toBe(5)
        ->and($middleware[0]->expiresAfter)->toBe(60)
        ->and($job->maxExceptions)->toBe(3)
        ->and($job->backoff())->toBe([5, 30, 120])
        ->and($job->retryUntil()->getTimestamp() - now()->getTimestamp())->toBe(600);
});

/**
 * A booked 13:00 visit for Dana today, 90 minutes long.
 */
function redrawVisit(Contact $dana, array $state = []): Appointment
{
    return Appointment::factory()->at(redrawChicago('13:00'), 90)->create([
        'contact_id' => $dana->id,
        'service_name' => 'AC repair',
        ...$state,
    ]);
}

it('redraws today.md after a page booking (D-14)', function () {
    [, $conversation] = bookingPageSetUp();
    [$link] = bookingPageMint($conversation);

    File::delete(icmPath('records/today.md'));

    app(BookAppointment::class)->fromLink($link, bookingPageChicago('2026-11-03 10:00'), CarbonImmutable::now());

    expect(File::exists(icmPath('records/today.md')))->toBeTrue()
        ->and(File::get(icmPath('records/today.md')))
        ->toContain('# Schedule for Monday, November 2')
        ->not->toContain('AC repair');
});

it('redraws after a cancel', function () {
    [$dana, $owner] = redrawSetUp();
    $visit = redrawVisit($dana);

    File::delete(icmPath('records/today.md'));

    app(CancelAppointment::class)->handle($visit, $owner);

    expect(File::exists(icmPath('records/today.md')))->toBeTrue()
        ->and(File::get(icmPath('records/today.md')))->toContain('AC repair for [Dana Whitfield]')
        ->toContain(', cancelled');
});

it('redraws after a visit is marked done', function () {
    [$dana, $owner] = redrawSetUp();
    $visit = redrawVisit($dana);

    File::delete(icmPath('records/today.md'));
    $this->travelTo(redrawChicago('14:00'));

    app(MarkAppointmentDone::class)->handle($visit, $owner);

    expect(File::exists(icmPath('records/today.md')))->toBeTrue()
        ->and(File::get(icmPath('records/today.md')))->toContain('AC repair for [Dana Whitfield]')
        ->toContain(', done');
});

it('redraws after a note change, never printing the note', function () {
    [$dana, $owner] = redrawSetUp();
    $visit = redrawVisit($dana);

    File::delete(icmPath('records/today.md'));

    app(EditVisitNote::class)->handle($visit, $owner, '12 Elm Street');

    expect(File::exists(icmPath('records/today.md')))->toBeTrue()
        ->and(File::get(icmPath('records/today.md')))->not->toContain('Elm Street');
});

it('queues no redraw when nothing changed', function () {
    [$dana, $owner] = redrawSetUp();
    $cancelled = redrawVisit($dana, ['state' => AppointmentState::Cancelled]);
    $noted = redrawVisit($dana, ['note' => 'Gate code at the door', 'starts_at' => redrawChicago('16:00')->utc(), 'ends_at' => redrawChicago('17:00')->utc()]);
    $done = Appointment::factory()->at(redrawChicago('09:00'), 60)->done()->create(['contact_id' => $dana->id]);
    $booked = redrawVisit($dana, ['starts_at' => redrawChicago('19:00')->utc(), 'ends_at' => redrawChicago('20:00')->utc()]);

    Queue::fake([RedrawOwnerIntelligenceRecords::class]);

    $this->travelTo(redrawChicago('10:00'));
    app(CancelAppointment::class)->handle($cancelled->fresh(), $owner);
    app(EditVisitNote::class)->handle($noted, $owner, 'Gate code at the door');
    app(MarkAppointmentDone::class)->handle($done, $owner);

    expect(fn () => app(BookAppointment::class)->byHand($dana, $owner, 'ac-repair', redrawChicago('19:30'), null, null))
        ->toThrow(BookingRefused::class);

    Queue::assertNothingPushed();
});

it('draws nothing for a factory row (prohibition)', function () {
    activateExampleFolder();
    Queue::fake([RedrawOwnerIntelligenceRecords::class]);

    Appointment::factory()->create();
    ScheduleBlock::factory()->create();

    Queue::assertNothingPushed();
});

it('redraws after a block is added and removed (D-14)', function () {
    [, $owner] = redrawSetUp();

    $block = app(BlockTime::class)->handle($owner, redrawChicago('11:00'), redrawChicago('12:00'), 'Truck in the shop');

    expect(File::exists(icmPath('records/today.md')))->toBeTrue()
        ->and(File::get(icmPath('records/today.md')))
        ->toContain('11:00 AM to 12:00 PM: Blocked')
        ->toContain('> Truck in the shop');

    app(RemoveBlock::class)->handle($block, $owner);

    expect(File::get(icmPath('records/today.md')))->toContain(TodayRecord::EMPTY_DAY);

    Queue::fake([RedrawOwnerIntelligenceRecords::class]);

    app(RemoveBlock::class)->handle($block, $owner);

    Queue::assertNothingPushed();
});

it('deletes the files at once when a sync turns the room off, and writes them when it comes back (D-15)', function () {
    activateExampleFolder();
    app(RecordsWriter::class)->writeOwnerIntelligence();

    $context = File::get(icmPath('rooms/owner_intelligence/CONTEXT.md'));
    File::deleteDirectory(icmPath('rooms/owner_intelligence'));
    activateExampleFolder();

    expect(File::exists(icmPath('records/today.md')))->toBeFalse()
        ->and(File::exists(icmPath('records/time-to-booked.md')))->toBeFalse();

    writeIcmFile('rooms/owner_intelligence/CONTEXT.md', $context);
    activateExampleFolder();

    expect(File::exists(icmPath('records/today.md')))->toBeTrue()
        ->and(File::exists(icmPath('records/time-to-booked.md')))->toBeTrue();
});

it('redraws at once when the schedule room is turned off', function () {
    activateExampleFolder();
    $this->travelTo(redrawChicago('08:00'));
    app(RecordsWriter::class)->writeOwnerIntelligence();

    File::deleteDirectory(icmPath('rooms/schedule_and_booking'));
    activateExampleFolder();

    expect(File::get(icmPath('records/today.md')))->toContain(TodayRecord::OUTSIDE_SYSTEM);
});

it('queues no redraw on the first sync, or for other rooms', function () {
    Queue::fake([RedrawOwnerIntelligenceRecords::class]);

    activateExampleFolder();

    Queue::assertNothingPushed();

    File::deleteDirectory(icmPath('rooms/leads'));
    activateExampleFolder();

    Queue::assertNothingPushed();
});
