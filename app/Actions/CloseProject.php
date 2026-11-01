<?php

namespace App\Actions;

use App\Enums\ActivityType;
use App\Enums\ProjectActor;
use App\Events\CustomerRecordChanged;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * One of the four writers of projects (D-11). Closing is not an agent action, so no checks-passed record is kept; it
 * records when and who closed the project and puts one entry on the customer's timeline, whose summary is a fixed
 * platform sentence. Closing never changes the contact's status (D-07), asks nothing and alerts no one (D-04).
 */
class CloseProject
{
    /**
     * The owner closes a project by hand (D-05, D-08).
     *
     * The project is read again under a row lock first. A second tab may have closed it since this page loaded, and
     * closing twice would move closed_at and add a second entry to the timeline, so a project that is already closed
     * is left exactly as it is.
     */
    public function handle(Project $project, User $user): void
    {
        $closed = DB::transaction(function () use ($project, $user): bool {
            Project::query()->lockForUpdate()->find($project->id);
            $project->refresh();

            if ($project->closed_at !== null) {
                return false;
            }

            $closedAt = now();

            $project->update([
                'closed_at' => $closedAt,
                'closed_by' => ProjectActor::User,
                'closed_by_user_id' => $user->id,
            ]);

            $project->contact->activities()->create([
                'type' => ActivityType::ProjectClosed,
                'summary' => 'Closed by the owner',
                'subject_type' => $project->getMorphClass(),
                'subject_id' => $project->id,
                'occurred_at' => $closedAt,
            ]);

            return true;
        });

        if ($closed) {
            CustomerRecordChanged::dispatch($project->contact_id);
        }
    }
}
