<?php

namespace App\Actions;

use App\Enums\ActivityType;
use App\Enums\ProjectActor;
use App\Events\CustomerRecordChanged;
use App\Icm\ActiveConfig;
use App\Models\Project;
use App\Models\User;
use App\Rooms\ScheduleAndBooking\BookingSettings;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * One of the four writers of projects (D-11). Closing is not an agent action, so no checks-passed record is kept; it
 * records when and who closed the project and puts one entry on the customer's timeline, whose summary is a fixed
 * platform sentence. Closing never changes the contact's status (D-07), asks nothing and alerts no one (D-04).
 *
 * A project with a visit still booked ahead is not closed (08 D-25): the owner cancels or finishes the visit first. This
 * adds one condition to closing and nothing else; 07 D-05 stands, so no job closes on its own.
 */
class CloseProject
{
    public function __construct(private readonly ActiveConfig $config) {}

    /**
     * The owner closes a project by hand (D-05, D-08).
     *
     * The project is read again under a row lock first. A second tab may have closed it since this page loaded, and
     * closing twice would move closed_at and add a second entry to the timeline, so a project that is already closed
     * is left exactly as it is.
     *
     * @throws DomainException when a visit is still booked ahead on the project (08 D-25).
     */
    public function handle(Project $project, User $user): void
    {
        $closed = DB::transaction(function () use ($project, $user): bool {
            Project::query()->lockForUpdate()->find($project->id);
            $project->refresh();

            if ($project->closed_at !== null) {
                return false;
            }

            $blocked = $this->blockedBy($project);

            if ($blocked !== null) {
                throw new DomainException($blocked);
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

    /**
     * The sentence that says why this project cannot be closed now, or null when it can (08 D-25, U-31): it names the job
     * and the next visit still booked ahead, as "Job 2 has a booked visit on Thursday, November 5 at 10:00 AM. Cancel that
     * visit first, then close." A visit that has started, or is cancelled or done, does not block. handle() refuses with
     * this sentence inside its lock, and the Close dialog reads it when it opens, so the screen is a courtesy and the
     * action is the guard.
     */
    public function blockedBy(Project $project): ?string
    {
        $visit = $project->appointments()->booked()->ahead()->orderBy('starts_at')->first();

        if ($visit === null) {
            return null;
        }

        $number = (int) Project::query()->withNumber()->whereKey($project->id)->value('number');
        $zone = $this->config->displayTimezone();

        return $project->label($this->config->projectWord(), $number)
            .' has a booked visit on '.BookingSettings::dayText($visit->starts_at, $zone).' at '.BookingSettings::timeText($visit->starts_at, $zone)
            .'. Cancel that visit first, then close.';
    }
}
