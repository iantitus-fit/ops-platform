<?php

use App\Actions\OpenProject;
use App\Enums\ActivityType;
use App\Enums\ContactStatus;
use App\Enums\ProjectActor;
use App\Enums\ProjectWord;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Project;
use App\Models\User;
use App\Records\CustomerCard;
use App\Rooms\Room;
use App\Rooms\RoomRegistry;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

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

it('has no appointment column', function () {
    expect(Schema::getColumnListing('projects'))->toEqualCanonicalizing([
        'id', 'contact_id', 'conversation_id', 'name', 'opened_by', 'opened_by_user_id',
        'opened_at', 'closed_at', 'closed_by', 'closed_by_user_id', 'created_at', 'updated_at',
    ]);

    foreach (Schema::getColumnListing('projects') as $column) {
        expect($column)->not->toContain('appointment');
    }
});

it('lets one contact hold any number of projects opened by hand', function () {
    $contact = Contact::factory()->create();
    $user = User::factory()->create();

    Project::factory()->openedByUser($user)->create(['contact_id' => $contact->id]);
    Project::factory()->openedByUser($user)->create(['contact_id' => $contact->id]);
    Project::factory()->openedByUser($user)->closed($user)->create(['contact_id' => $contact->id]);

    expect($contact->projects()->count())->toBe(3)
        ->and($contact->projects()->whereNull('closed_at')->count())->toBe(2);
});

it('refuses a second project opened by a new lead for one contact', function () {
    $contact = Contact::factory()->create();
    $other = Contact::factory()->create();

    Project::factory()->openedByLead()->create(['contact_id' => $contact->id]);
    Project::factory()->openedByLead()->create(['contact_id' => $other->id]);

    expect(fn () => Project::factory()->openedByLead()->create(['contact_id' => $contact->id]))
        ->toThrow(UniqueConstraintViolationException::class);

    expect(Project::query()->where('opened_by', ProjectActor::Lead)->count())->toBe(2);
});

it('gives the factory states the actors they name', function () {
    $user = User::factory()->create();

    $lead = Project::factory()->openedByLead()->make();
    $byHand = Project::factory()->openedByUser($user)->make();
    $closed = Project::factory()->closed($user)->make();
    $closedNobody = Project::factory()->closed()->make();

    expect($lead->opened_by)->toBe(ProjectActor::Lead)
        ->and($lead->opened_by_user_id)->toBeNull()
        ->and($byHand->opened_by)->toBe(ProjectActor::User)
        ->and($byHand->opened_by_user_id)->toBe($user->id)
        ->and($closed->closed_at)->not->toBeNull()
        ->and($closed->closed_by)->toBe(ProjectActor::User)
        ->and($closed->closed_by_user_id)->toBe($user->id)
        ->and($closedNobody->closed_at)->not->toBeNull()
        ->and($closedNobody->closed_by)->toBeNull();
});

it("numbers each contact's projects in opening order", function () {
    $a = Contact::factory()->create();
    $b = Contact::factory()->create();

    foreach ([$a, $b, $a, $a] as $contact) {
        Project::factory()->create(['contact_id' => $contact->id]);
    }

    $projects = Project::query()->withNumber()->orderBy('id')->get();

    expect($projects->pluck('number')->map(fn ($number) => (int) $number)->all())->toBe([1, 1, 2, 3]);

    expect($projects[2]->label(ProjectWord::Job, (int) $projects[2]->number))->toBe('Job 2')
        ->and($projects[1]->label(ProjectWord::Matter, (int) $projects[1]->number))->toBe('Matter 1');

    $projects[3]->update(['name' => '  Water heater swap  ']);

    expect($projects[3]->fresh()->label(ProjectWord::Job, 3))->toBe('Water heater swap');
});

it('keeps who opened it when the user is deleted, and goes with its contact', function () {
    $user = User::factory()->create();
    $project = Project::factory()->openedByUser($user)->create();

    $user->delete();

    expect($project->fresh()->opened_by)->toBe(ProjectActor::User)
        ->and($project->fresh()->opened_by_user_id)->toBeNull();

    $project->contact->delete();

    expect(Project::query()->count())->toBe(0);
});

it('is owned by no room', function () {
    $tables = collect(app(RoomRegistry::class)->all())->flatMap(fn (Room $room): array => $room->tables);

    expect($tables)->not->toContain('projects');
});
