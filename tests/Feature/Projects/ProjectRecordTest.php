<?php

use App\Actions\OpenProject;
use App\Enums\ActivityType;
use App\Enums\ContactStatus;
use App\Enums\ProjectActor;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\User;
use App\Records\CustomerCard;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

/**
 * The customer's card file, as the sync-queue render left it.
 */
function projectCardFor(Contact $contact): string
{
    return File::get(icmPath('records/customers/'.app(CustomerCard::class)->filename($contact)));
}

it('opens a project by hand, tied to the open conversation, and puts it on the card', function () {
    activateExampleFolder();
    $this->travelTo(Carbon::parse('2026-10-29 14:02', 'America/Chicago'));

    $user = User::factory()->create(['name' => 'Ian Owner']);
    $contact = Contact::factory()->create(['name' => 'Dana Whitfield', 'status' => ContactStatus::Lead]);
    $conversation = Conversation::factory()->create(['contact_id' => $contact->id]);

    $project = app(OpenProject::class)->byHand($contact, $user);

    expect($project->contact_id)->toBe($contact->id)
        ->and($project->conversation_id)->toBe($conversation->id)
        ->and($project->opened_by)->toBe(ProjectActor::User)
        ->and($project->opened_by_user_id)->toBe($user->id)
        ->and($project->opened_at->equalTo(now()))->toBeTrue()
        ->and($project->closed_at)->toBeNull()
        ->and($project->closed_by)->toBeNull()
        ->and($project->closed_by_user_id)->toBeNull()
        ->and($project->name)->toBeNull()
        ->and($project->isOpen())->toBeTrue();

    $activity = Activity::query()->where('type', ActivityType::ProjectOpened)->sole();

    expect($activity->contact_id)->toBe($contact->id)
        ->and($activity->subject_type)->toBe('project')
        ->and($activity->subject_id)->toBe($project->id)
        ->and($activity->summary)->toBe('Opened by the owner')
        ->and($activity->occurred_at->equalTo($project->opened_at))->toBeTrue();

    expect(projectCardFor($contact))->toContain("### 2026-10-29 14:02 · Job 1 opened\n\nSystem note:\n\n> Opened by the owner");
    expect($contact->fresh()->status)->toBe(ContactStatus::Lead);
});

it('stores no conversation when none is open', function () {
    activateExampleFolder();

    $user = User::factory()->create();
    $onlyClosed = Contact::factory()->create();
    Conversation::factory()->closed()->create(['contact_id' => $onlyClosed->id]);
    $noConversation = Contact::factory()->create();

    expect(app(OpenProject::class)->byHand($onlyClosed, $user)->conversation_id)->toBeNull()
        ->and(app(OpenProject::class)->byHand($noConversation, $user)->conversation_id)->toBeNull();
});

it('opens a second project by hand while the first is open', function () {
    activateExampleFolder();

    $user = User::factory()->create();
    $contact = Contact::factory()->create(['name' => 'Dana Whitfield']);

    $first = app(OpenProject::class)->byHand($contact, $user);
    $second = app(OpenProject::class)->byHand($contact, $user);

    expect($first->isOpen())->toBeTrue()
        ->and($second->isOpen())->toBeTrue()
        ->and($contact->projects()->count())->toBe(2);

    $card = projectCardFor($contact);

    expect($card)->toContain('· Job 2 opened')
        ->toContain('· Job 1 opened');
});
