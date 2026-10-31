<?php

namespace App\Rooms\Leads;

use App\Actions\OpenProject;
use App\Enums\ActivityType;
use App\Icm\ActiveConfig;
use App\Models\Contact;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * The leads room's job: it opens the one project a brand-new lead gets (LEAD-05). It runs after the inbound commits,
 * beside RecordInbound, which is not edited (D-02). The room's hook asks for it only while the leads room is live
 * (D-03), and a rolled-back inbound drops the callback, so nothing is queued for a contact that never existed.
 *
 * A crash between the commit and the push loses the open. The owner's New action and Phase 8 booking cover that, and a
 * sweep before the first client will catch it (Deferred, BLUEPRINT section 6).
 *
 * The job writes nothing itself: it asks OpenProject, the chokepoint for opening a project (D-11).
 */
class OpenProjectForNewLead implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $contactId) {}

    /**
     * Queue the open once the inbound's transaction has committed. The hook fires inside that transaction, so this only
     * asks whether the room is live and holds the push back until the commit. Asking at queue time as well as at run
     * time keeps a business without the room from getting a job on its queue at all.
     */
    public static function queueFor(Contact $contact): void
    {
        if (! app(ActiveConfig::class)->hasRoom('leads')) {
            return;
        }

        $contactId = $contact->id;

        DB::afterCommit(fn () => self::dispatch($contactId));
    }

    /**
     * Open the project, if this contact arrived by a text or a form and has none yet. The contact is loaded fresh, so
     * the job sees the database as it is now.
     */
    public function handle(OpenProject $openProject): void
    {
        $contact = Contact::query()->find($this->contactId);

        if ($contact === null) {
            return;
        }

        $arrival = $contact->activities()
            ->whereIn('type', [ActivityType::TextReceived->value, ActivityType::FormReceived->value])
            ->orderBy('id')
            ->first();

        if ($arrival === null) {
            return;
        }

        $openProject->forNewLead($contact, $arrival);
    }
}
