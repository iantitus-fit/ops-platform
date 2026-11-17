<?php

use App\Actions\BookAppointment;
use App\Events\ScheduleChanged;
use App\Jobs\RedrawOwnerIntelligenceRecords;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Attributes\UniqueFor;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;

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
