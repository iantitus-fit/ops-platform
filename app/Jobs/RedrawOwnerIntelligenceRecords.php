<?php

namespace App\Jobs;

use App\Records\RecordsWriter;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\UniqueFor;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Redraws records/today.md and records/time-to-booked.md from the database (D-14), and the router records/CLAUDE.md
 * beside them, so the router follows the room at the next sync (D-25, 09-REVIEW WR-01).
 *
 * Two changes before the job starts queue one job, which reads the database inside the records lock. A change during a
 * redraw queues one more, because the lock is released as the job starts. Redraws never overlap. The unique lock lapses
 * after 300 seconds so a crash cannot hold it. The job only reads appointments and blocks (08 D-15).
 */
#[UniqueFor(300)]
class RedrawOwnerIntelligenceRecords implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    /**
     * Real failures (a full disk, a lock that timed out) stop after three exceptions. Releases by the overlap middleware
     * are not exceptions, so they do not count toward this.
     */
    public int $maxExceptions = 3;

    public function uniqueId(): string
    {
        return 'owner-intelligence';
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('owner-intelligence'))->releaseAfter(5)->expireAfter(60)];
    }

    /**
     * Every release by the overlap middleware counts as an attempt, so a time limit is used in place of an attempt count.
     */
    public function retryUntil(): DateTimeInterface
    {
        return now()->addMinutes(10);
    }

    /**
     * Seconds to wait before each retry after a real failure, so a failing job does not retry in a tight loop for the
     * whole ten minutes.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [5, 30, 120];
    }

    /**
     * The room's files are written fresh, so they show the database as it is now rather than as it was when the event
     * fired. With the room off the same call deletes them and the router stops naming them (D-15, D-25).
     */
    public function handle(RecordsWriter $recordsWriter): void
    {
        $recordsWriter->writeOwnerIntelligence();
    }
}
