<?php

namespace App\Rooms\Leads;

use App\Actions\OpenProject;
use App\Enums\ActivityType;
use App\Icm\ActiveConfig;
use App\Models\Activity;
use App\Models\Contact;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The leads room's job: it opens the one project a brand-new lead gets (LEAD-05). It runs after the inbound commits,
 * beside RecordInbound, which is not edited (D-02). The room's hook asks for it only while the leads room is live
 * (D-03), and a rolled-back inbound drops the callback, so nothing is queued for a contact that never existed.
 *
 * A crash between the commit and the push loses the open. The owner's New action and Phase 8 booking cover that, and a
 * sweep before the first client will catch it (Deferred, BLUEPRINT section 6).
 *
 * It does nothing when the lead's first message was itself the STOP (D-23 as amended, review WR-01): RecordInbound
 * records that opt-out with the message as its subject, in the same transaction that creates the contact. A lead who
 * writes about a job and then texts STOP before the job runs still gets a project, and stays opted out. A first START
 * or HELP still opens one.
 *
 * The room is checked when the job is queued and again when it runs: the room may be turned off in between (D-03), and
 * ActiveConfig is scoped, so a worker sees a sync made after the push.
 *
 * The job writes nothing itself: it asks OpenProject, the chokepoint for opening a project (D-11).
 */
class OpenProjectForNewLead implements ShouldQueue
{
    use Queueable;

    /**
     * A failed open is tried again, waiting longer each time (backoff()).
     */
    public int $tries = 5;

    public function __construct(public int $contactId) {}

    /**
     * Seconds to wait before each retry.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [5, 30, 120, 300];
    }

    /**
     * Queue the open once the inbound's transaction has committed. The hook fires inside that transaction, so this only
     * asks whether the room is live and holds the push back until the commit. Asking at queue time as well as at run
     * time keeps a business without the room from getting a job on its queue at all.
     *
     * The push sits in try/catch inside the after-commit callback, so a queue that cannot take the job, or an open that
     * throws when the queue runs it at once, is reported and never reaches the inbound that already committed (D-02).
     */
    public static function queueFor(Contact $contact): void
    {
        if (! app(ActiveConfig::class)->hasRoom('leads')) {
            return;
        }

        $contactId = $contact->id;

        DB::afterCommit(function () use ($contactId): void {
            try {
                self::dispatch($contactId);
            } catch (Throwable $exception) {
                report($exception);
            }
        });
    }

    /**
     * Open the project, if the leads room is still live and this contact arrived by a text or a form and has none yet. The contact is loaded fresh, so
     * the job sees the database as it is now.
     */
    public function handle(OpenProject $openProject): void
    {
        if (! app(ActiveConfig::class)->hasRoom('leads')) {
            return;
        }

        $contact = Contact::query()->find($this->contactId);

        if ($contact === null) {
            return;
        }

        $arrival = $contact->activities()
            ->whereIn('type', [ActivityType::TextReceived->value, ActivityType::FormReceived->value])
            ->orderBy('id')
            ->first();

        if ($arrival === null || $this->arrivalWasStop($contact, $arrival)) {
            return;
        }

        $openProject->forNewLead($contact, $arrival);
    }

    /**
     * Report an open that used up its tries (review IN-01), so a lead left without a project shows in the log. The
     * expected skips above (room off, first message was STOP, already has a project) are not failures and log nothing.
     */
    public function failed(?Throwable $exception): void
    {
        if ($exception !== null) {
            report($exception);
        }
    }

    /**
     * Whether the message the lead arrived with is the one that opted them out (D-23 as amended): RecordInbound puts
     * the opt-out on the timeline with that message as its subject. A STOP sent after a real first text does not count,
     * so that lead still gets a project; nothing is ever texted to an opted-out number (02 D-40).
     */
    private function arrivalWasStop(Contact $contact, Activity $arrival): bool
    {
        if ($arrival->subject_type === null) {
            return false;
        }

        return $contact->activities()
            ->where('type', ActivityType::OptedOut->value)
            ->where('subject_type', $arrival->subject_type)
            ->where('subject_id', $arrival->subject_id)
            ->exists();
    }
}
