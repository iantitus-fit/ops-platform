<?php

use App\Actions\CloseProject;
use App\Actions\OpenProject;
use App\Enums\ActivityType;
use App\Enums\ContactStatus;
use App\Enums\ProjectActor;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Project;
use App\Models\User;
use App\Records\CustomerCard;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

/**
 * The customer's card file, as the sync-queue render left it.
 */
function projectActionsCardFor(Contact $contact): string
{
    return File::get(icmPath('records/customers/'.app(CustomerCard::class)->filename($contact)));
}

it('closes a project and puts it on the card', function () {
    activateExampleFolder();
    $this->travelTo(Carbon::parse('2026-10-29 14:02', 'America/Chicago'));

    $user = User::factory()->create();
    $contact = Contact::factory()->create(['name' => 'Dana Whitfield', 'status' => ContactStatus::Lead]);
    $project = app(OpenProject::class)->byHand($contact, $user);

    $this->travelTo(Carbon::parse('2026-10-30 09:00', 'America/Chicago'));

    app(CloseProject::class)->handle($project, $user);

    $project->refresh();

    expect($project->closed_at->equalTo(now()))->toBeTrue()
        ->and($project->closed_by)->toBe(ProjectActor::User)
        ->and($project->closed_by_user_id)->toBe($user->id)
        ->and($project->isOpen())->toBeFalse();

    $activity = Activity::query()->where('type', ActivityType::ProjectClosed)->sole();

    expect($activity->contact_id)->toBe($contact->id)
        ->and($activity->subject_type)->toBe('project')
        ->and($activity->subject_id)->toBe($project->id)
        ->and($activity->summary)->toBe('Closed by the owner')
        ->and($activity->occurred_at->equalTo($project->closed_at))->toBeTrue();

    $card = projectActionsCardFor($contact);

    expect($card)->toContain('- Job 1: closed, opened 2026-10-29 14:02 by the owner')
        ->toContain("· Job 1 closed\n\nSystem note:\n\n> Closed by the owner");
    expect($contact->fresh()->status)->toBe(ContactStatus::Lead);
});

it('closes once from two tabs', function () {
    activateExampleFolder();
    $this->travelTo(Carbon::parse('2026-10-29 14:02', 'America/Chicago'));

    $user = User::factory()->create();
    $contact = Contact::factory()->create();
    $project = app(OpenProject::class)->byHand($contact, $user);
    $staleCopy = Project::query()->findOrFail($project->id);

    $this->travelTo(Carbon::parse('2026-10-30 09:00', 'America/Chicago'));
    $firstCloseAt = now();
    app(CloseProject::class)->handle($project, $user);

    $this->travelTo(Carbon::parse('2026-10-30 09:01', 'America/Chicago'));
    app(CloseProject::class)->handle($staleCopy, $user);

    expect($project->fresh()->closed_at->equalTo($firstCloseAt))->toBeTrue()
        ->and(Activity::query()->where('type', ActivityType::ProjectClosed)->count())->toBe(1);
});
