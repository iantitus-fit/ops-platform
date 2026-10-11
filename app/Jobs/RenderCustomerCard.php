<?php

namespace App\Jobs;

use App\Models\Contact;
use App\Records\RecordsWriter;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\UniqueFor;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Re-renders one customer's card from the database.
 *
 * Two events before the job starts queue one job, which reads both from the database. An event
 * during a render queues one more, because the lock is released as the job starts. Renders for
 * one customer never overlap. The unique lock lapses after 300 seconds so a crash cannot hold it.
 */
#[UniqueFor(300)]
class RenderCustomerCard implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public function __construct(public int $contactId) {}

    public function uniqueId(): string
    {
        return (string) $this->contactId;
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping((string) $this->contactId))->releaseAfter(5)->expireAfter(60)];
    }

    /**
     * Every release by the overlap middleware counts as an attempt, so a time limit is used
     * in place of an attempt count.
     */
    public function retryUntil(): DateTimeInterface
    {
        return now()->addMinutes(10);
    }

    /**
     * The contact is loaded fresh, so the card shows the database as it is now rather than as
     * it was when the event fired. The summaries are rewritten after the card either way,
     * since a new, renamed or deleted customer changes both.
     */
    public function handle(RecordsWriter $recordsWriter): void
    {
        $contact = Contact::query()->find($this->contactId);

        if ($contact === null) {
            $recordsWriter->removeCard($this->contactId);
        } else {
            $recordsWriter->writeCard($contact);
        }

        $recordsWriter->writeSummaries();
    }
}
