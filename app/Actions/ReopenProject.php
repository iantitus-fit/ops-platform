<?php

namespace App\Actions;

use App\Enums\ActivityType;
use App\Events\CustomerRecordChanged;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * One of the four writers of projects (D-11). Reopening is not an agent action, so no checks-passed record is kept; it
 * clears the close and puts one entry on the customer's timeline, whose summary is a fixed platform sentence. Reopening
 * never changes the contact's status (D-07), asks nothing and alerts no one (D-04).
 */
class ReopenProject
{
    /**
     * The owner reopens a closed project by hand. A contact may have several projects open at once, so another open
     * project is no reason to refuse (D-05).
     *
     * The projects row keeps user ids only for who opened and who closed (D-08), so who reopened it is the signed-in
     * owner, written on the timeline entry as "the owner". The project is read again under a row lock first, so a
     * second tab that finds it already open changes nothing and adds no second entry (D-11).
     */
    public function handle(Project $project, User $user): void
    {
        $reopened = DB::transaction(function () use ($project): bool {
            Project::query()->lockForUpdate()->find($project->id);
            $project->refresh();

            if ($project->closed_at === null) {
                return false;
            }

            $project->update([
                'closed_at' => null,
                'closed_by' => null,
                'closed_by_user_id' => null,
            ]);

            $project->contact->activities()->create([
                'type' => ActivityType::ProjectReopened,
                'summary' => 'Reopened by the owner',
                'subject_type' => $project->getMorphClass(),
                'subject_id' => $project->id,
                'occurred_at' => now(),
            ]);

            return true;
        });

        if ($reopened) {
            CustomerRecordChanged::dispatch($project->contact_id);
        }
    }
}
