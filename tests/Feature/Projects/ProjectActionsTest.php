<?php

use App\Actions\CloseProject;
use App\Actions\OpenProject;
use App\Actions\RenameProject;
use App\Actions\ReopenProject;
use App\Enums\ActivityType;
use App\Enums\ContactStatus;
use App\Enums\MessageDirection;
use App\Enums\ProjectActor;
use App\Enums\ProjectWord;
use App\Models\Activity;
use App\Models\Appointment;
use App\Models\Contact;
use App\Models\Message;
use App\Models\OwnerAlert;
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

it('reopens a closed project', function () {
    activateExampleFolder();
    $this->travelTo(Carbon::parse('2026-10-29 14:02', 'America/Chicago'));

    $user = User::factory()->create();
    $contact = Contact::factory()->create(['name' => 'Dana Whitfield']);
    $project = app(OpenProject::class)->byHand($contact, $user);
    app(CloseProject::class)->handle($project, $user);
    $staleCopy = Project::query()->findOrFail($project->id);

    $this->travelTo(Carbon::parse('2026-10-30 09:00', 'America/Chicago'));
    app(ReopenProject::class)->handle($project, $user);

    $project->refresh();

    expect($project->closed_at)->toBeNull()
        ->and($project->closed_by)->toBeNull()
        ->and($project->closed_by_user_id)->toBeNull()
        ->and($project->isOpen())->toBeTrue();

    $activity = Activity::query()->where('type', ActivityType::ProjectReopened)->sole();

    expect($activity->contact_id)->toBe($contact->id)
        ->and($activity->subject_type)->toBe('project')
        ->and($activity->subject_id)->toBe($project->id)
        ->and($activity->summary)->toBe('Reopened by the owner');

    $card = projectActionsCardFor($contact);

    expect($card)->toContain('- Job 1: open')
        ->toContain("· Job 1 reopened\n\nSystem note:\n\n> Reopened by the owner");

    app(ReopenProject::class)->handle($staleCopy, $user);
    app(ReopenProject::class)->handle($project, $user);

    expect(Activity::query()->where('type', ActivityType::ProjectReopened)->count())->toBe(1);
});

it('leaves an open project alone when it is reopened', function () {
    $user = User::factory()->create();
    $project = Project::factory()->openedByUser($user)->create();

    app(ReopenProject::class)->handle($project, $user);

    expect($project->fresh()->isOpen())->toBeTrue()
        ->and(Activity::query()->where('type', ActivityType::ProjectReopened)->count())->toBe(0);
});

it('reopens while the contact has another open project', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->create();
    $closed = Project::factory()->for($contact)->openedByUser($user)->closed($user)->create();
    $other = Project::factory()->for($contact)->openedByUser($user)->create();

    app(ReopenProject::class)->handle($closed, $user);

    expect($closed->fresh()->isOpen())->toBeTrue()
        ->and($other->fresh()->isOpen())->toBeTrue()
        ->and($contact->projects()->whereNull('closed_at')->count())->toBe(2);
});

it('renames a project to one tidy line', function () {
    activateExampleFolder();
    $this->travelTo(Carbon::parse('2026-10-29 14:02', 'America/Chicago'));

    $user = User::factory()->create();
    $contact = Contact::factory()->create(['name' => 'Dana Whitfield']);
    $project = app(OpenProject::class)->byHand($contact, $user);

    app(RenameProject::class)->handle($project, $user, "  Replace\nthe   water heater ");

    expect($project->fresh()->name)->toBe('Replace the water heater');

    $activity = Activity::query()->where('type', ActivityType::ProjectRenamed)->sole();

    expect($activity->contact_id)->toBe($contact->id)
        ->and($activity->subject_type)->toBe('project')
        ->and($activity->subject_id)->toBe($project->id)
        ->and($activity->summary)->toBe('Renamed by the owner')
        ->and($activity->summary)->not->toContain('water');

    $card = projectActionsCardFor($contact);

    expect($card)->toContain('> Replace the water heater')
        ->toContain("· Job 1 renamed\n\nSystem note:\n\n> Renamed by the owner");

    app(RenameProject::class)->handle($project, $user, 'Replace the water heater');
    app(RenameProject::class)->handle(Project::query()->findOrFail($project->id), $user, " Replace the\twater heater\n");

    expect(Activity::query()->where('type', ActivityType::ProjectRenamed)->count())->toBe(1);
});

it('clears the name when it is renamed to nothing', function (?string $empty) {
    $user = User::factory()->create();
    $project = Project::factory()->openedByUser($user)->create(['name' => 'Replace the water heater']);

    app(RenameProject::class)->handle($project, $user, $empty);

    $project->refresh();

    expect($project->name)->toBeNull()
        ->and($project->label(ProjectWord::Job, 1))->toBe('Job 1')
        ->and(Activity::query()->where('type', ActivityType::ProjectRenamed)->count())->toBe(1);

    app(RenameProject::class)->handle($project, $user, $empty);

    expect(Activity::query()->where('type', ActivityType::ProjectRenamed)->count())->toBe(1);
})->with([
    'empty text' => [''],
    'only spaces' => ['   '],
    'null' => [null],
]);

it('refuses a name it cannot keep', function (string $name, string $refusal) {
    $user = User::factory()->create();
    $project = Project::factory()->openedByUser($user)->create(['name' => 'Kitchen remodel']);

    expect(fn () => app(RenameProject::class)->handle($project, $user, $name))
        ->toThrow(DomainException::class, $refusal);

    expect($project->fresh()->name)->toBe('Kitchen remodel')
        ->and(Activity::query()->where('type', ActivityType::ProjectRenamed)->count())->toBe(0);
})->with([
    'a hundred and one characters' => [str_repeat('a', 101), 'A name can be at most 100 characters.'],
    'a zero-width space' => ["Water\u{200B}heater", 'A name cannot hold a hidden character, such as a direction mark or a zero-width space. Type it again.'],
    'a direction override' => ["Water\u{202E}heater", 'A name cannot hold a hidden character, such as a direction mark or a zero-width space. Type it again.'],
    'broken bytes' => ["Water\xC3heater", 'A name cannot hold a hidden character, such as a direction mark or a zero-width space. Type it again.'],
]);

it('keeps a name of exactly 100 characters, counted as characters', function (string $name) {
    $user = User::factory()->create();
    $project = Project::factory()->openedByUser($user)->create();

    app(RenameProject::class)->handle($project, $user, $name);

    expect($project->fresh()->name)->toBe($name);
})->with([
    'a hundred letters' => [str_repeat('a', 100)],
    'a hundred two-byte letters' => [str_repeat('é', 100)],
]);

it('never changes the contact status', function (bool $customer) {
    $user = User::factory()->create();
    $contact = $customer ? Contact::factory()->customer()->create() : Contact::factory()->create(['status' => ContactStatus::Lead]);
    $status = $contact->status;

    $project = app(OpenProject::class)->byHand($contact, $user);
    expect($contact->fresh()->status)->toBe($status);

    app(RenameProject::class)->handle($project, $user, 'Replace the water heater');
    expect($contact->fresh()->status)->toBe($status);

    app(CloseProject::class)->handle($project, $user);
    expect($contact->fresh()->status)->toBe($status);

    app(ReopenProject::class)->handle($project, $user);
    expect($contact->fresh()->status)->toBe($status);
})->with([
    'a lead' => [false],
    'a customer' => [true],
]);

it('asks, flags and alerts no one', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->create();

    $project = app(OpenProject::class)->byHand($contact, $user);
    app(RenameProject::class)->handle($project, $user, 'Replace the water heater');
    app(CloseProject::class)->handle($project, $user);
    app(ReopenProject::class)->handle($project, $user);

    expect(OwnerAlert::query()->count())->toBe(0)
        ->and(Message::query()->where('direction', MessageDirection::Out)->count())->toBe(0);
});

/**
 * Dana's job with the clock at Tuesday 2026-11-03 11:00 in the shop's zone and a booked AC repair $startsAt (Chicago).
 * $state is a factory state name or null for a plain booked visit.
 *
 * @return array{0: Project, 1: User, 2: Appointment}
 */
function projectActionsJobWithVisit(string $startsAt, ?string $state = null): array
{
    activateExampleFolder();
    test()->travelTo(Carbon::parse('2026-11-03 11:00', 'America/Chicago'));

    $user = User::factory()->create();
    $contact = Contact::factory()->create(['name' => 'Dana Whitfield']);
    $project = app(OpenProject::class)->byHand($contact, $user);

    $factory = Appointment::factory()->for($contact)->for($project)->at(Carbon::parse($startsAt, 'America/Chicago'), 90);

    return [$project, $user, ($state === null ? $factory : $factory->{$state}())->create()];
}

it('refuses to close a job with a visit booked ahead', function () {
    [$project, $user] = projectActionsJobWithVisit('2026-11-05 10:00');

    expect(fn () => app(CloseProject::class)->handle($project, $user))
        ->toThrow(DomainException::class, 'Job 1 has a booked visit on Thursday, November 5 at 10:00 AM. Cancel that visit first, then close.');

    expect($project->fresh()->isOpen())->toBeTrue()
        ->and(Activity::query()->where('type', ActivityType::ProjectClosed)->count())->toBe(0);
});

it('names the job by its own name when it has one', function () {
    [$project, $user] = projectActionsJobWithVisit('2026-11-05 10:00');
    app(RenameProject::class)->handle($project, $user, 'Johnny');

    expect(fn () => app(CloseProject::class)->handle($project, $user))
        ->toThrow(DomainException::class, 'Johnny has a booked visit on Thursday, November 5 at 10:00 AM.');
});

it('closes a job whose visit was cancelled or is done', function (string $state) {
    [$project, $user] = projectActionsJobWithVisit('2026-11-05 10:00', $state);

    app(CloseProject::class)->handle($project, $user);

    expect($project->fresh()->isOpen())->toBeFalse();
})->with(['cancelled', 'done']);

it('blocks a close for a visit a minute ahead and not for one that started a minute ago', function () {
    [$project, $user] = projectActionsJobWithVisit('2026-11-03 11:01');

    expect(fn () => app(CloseProject::class)->handle($project, $user))->toThrow(DomainException::class);

    $this->travelTo(Carbon::parse('2026-11-03 11:02', 'America/Chicago'));

    app(CloseProject::class)->handle($project, $user);

    expect($project->fresh()->isOpen())->toBeFalse();
});

it('does not refuse closing a job that is already closed', function () {
    [$project, $user] = projectActionsJobWithVisit('2026-11-05 10:00', 'cancelled');
    app(CloseProject::class)->handle($project, $user);
    Appointment::factory()->for($project->contact)->for($project)->at(Carbon::parse('2026-11-06 10:00', 'America/Chicago'), 90)->create();

    app(CloseProject::class)->handle($project, $user);

    expect(Activity::query()->where('type', ActivityType::ProjectClosed)->count())->toBe(1);
});
