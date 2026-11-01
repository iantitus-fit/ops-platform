<?php

use App\Actions\OpenProject;
use App\Actions\RenameProject;
use App\Enums\ProjectActor;
use App\Filament\Resources\Contacts\ContactResource;
use App\Filament\Resources\Contacts\Pages\ViewContact;
use App\Filament\Resources\Contacts\RelationManagers\ProjectsRelationManager;
use App\Models\Contact;
use App\Models\Message;
use App\Models\OwnerAlert;
use App\Models\Project;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    $this->owner = User::factory()->create(['name' => 'Ian Owner']);
    $this->actingAs($this->owner);
});

/**
 * The contact screen's project list, mounted the way the contact page mounts it.
 */
function projectsListFor(Contact $contact): Testable
{
    return Livewire::test(ProjectsRelationManager::class, [
        'ownerRecord' => $contact,
        'pageClass' => ViewContact::class,
    ]);
}

it('lists a contact\'s projects in the business\'s word', function () {
    activateExampleFolder();
    $contact = Contact::factory()->create();
    $contact->conversations()->create();
    $project = app(OpenProject::class)->byHand($contact, $this->owner);

    expect(ProjectsRelationManager::getTitle($contact, ViewContact::class))->toBe('Jobs');

    projectsListFor($contact)
        ->assertCanSeeTableRecords([$project])
        ->assertSee('Job 1')
        ->assertSee('Open')
        ->assertSee('Ian Owner');
});

it('opens a second job from the New action, tied to the open conversation and with nothing sent or alerted', function () {
    activateExampleFolder();
    $contact = Contact::factory()->create();
    $conversation = $contact->conversations()->create();
    app(OpenProject::class)->byHand($contact, $this->owner);

    projectsListFor($contact)
        ->callTableAction('new')
        ->assertNotified();

    $second = Project::query()->where('contact_id', $contact->id)->orderByDesc('id')->first();

    expect(Project::query()->where('contact_id', $contact->id)->count())->toBe(2)
        ->and($second->opened_by)->toBe(ProjectActor::User)
        ->and($second->opened_by_user_id)->toBe($this->owner->id)
        ->and($second->conversation_id)->toBe($conversation->id)
        ->and(OwnerAlert::query()->count())->toBe(0)
        ->and(Message::query()->count())->toBe(0);

    projectsListFor($contact)->assertSee('Job 2');
});

it('asks before New opens a job, and opens nothing until it is confirmed', function () {
    activateExampleFolder();
    $contact = Contact::factory()->create();

    projectsListFor($contact)
        ->assertTableActionExists('new', fn (Action $action): bool => $action->isConfirmationRequired()
            && $action->getModalHeading() === 'New job'
            && $action->getModalDescription() === 'Open a new job for this customer? It takes the next number and cannot be deleted, only closed.'
            && $action->getModalSubmitActionLabel() === 'Open job')
        ->mountTableAction('new')
        ->assertTableActionMounted('new');

    expect(Project::query()->count())->toBe(0);
});

it('is not read-only on the contact screen', function () {
    activateExampleFolder();
    $contact = Contact::factory()->create();

    $list = projectsListFor($contact);

    expect($list->instance()->isReadOnly())->toBeFalse();

    $list->assertTableActionVisible('new');
});

it('shows on the contact page in its first response, so Tab can reach the list', function () {
    activateExampleFolder();
    $contact = Contact::factory()->create();
    app(OpenProject::class)->byHand($contact, $this->owner);
    app(OpenProject::class)->byHand($contact, $this->owner);

    $this->get(ContactResource::getUrl('view', ['record' => $contact]))
        ->assertOk()
        ->assertSee('Job 1', false)
        ->assertSee('Job 2', false)
        ->assertSee('Rename', false)
        ->assertSee('New job', false)
        ->assertDontSee('__lazyLoad', false);

    expect(ContactResource::getRelations())->toBe([ProjectsRelationManager::class]);
});

it('is not lazy', function () {
    expect(ProjectsRelationManager::isLazy())->toBeFalse()
        ->and(ProjectsRelationManager::getDefaultProperties())->not->toHaveKey('lazy');
});

it('closes and reopens from the row', function () {
    activateExampleFolder();
    $contact = Contact::factory()->create();
    $project = app(OpenProject::class)->byHand($contact, $this->owner);

    projectsListFor($contact)
        ->assertTableActionVisible('close', $project)
        ->assertTableActionHidden('reopen', $project)
        ->callTableAction('close', $project)
        ->assertNotified();

    $project->refresh();

    expect($project->closed_at)->not->toBeNull()
        ->and($project->closed_by_user_id)->toBe($this->owner->id);

    projectsListFor($contact)
        ->assertSee('Closed')
        ->assertTableActionVisible('reopen', $project)
        ->assertTableActionHidden('close', $project)
        ->callTableAction('reopen', $project);

    expect($project->refresh()->closed_at)->toBeNull();
});

it('renames from the row, and an empty name goes back to the word and number', function () {
    activateExampleFolder();
    $contact = Contact::factory()->create();
    $project = app(OpenProject::class)->byHand($contact, $this->owner);

    projectsListFor($contact)
        ->callTableAction('rename', $project, ['name' => 'Replace the water heater'])
        ->assertSee('Replace the water heater')
        ->assertDontSee('Job 1');

    expect($project->refresh()->name)->toBe('Replace the water heater');

    projectsListFor($contact)
        ->callTableAction('rename', $project, ['name' => ''])
        ->assertSee('Job 1');

    expect($project->refresh()->name)->toBeNull();
});

it('starts the rename form with the current name', function () {
    activateExampleFolder();
    $contact = Contact::factory()->create();
    $project = app(OpenProject::class)->byHand($contact, $this->owner);
    app(RenameProject::class)->handle($project, $this->owner, 'Replace the water heater');

    projectsListFor($contact)
        ->mountTableAction('rename', $project)
        ->assertTableActionDataSet(['name' => 'Replace the water heater']);
});

it('refuses a name over 100 characters on the form', function () {
    activateExampleFolder();
    $contact = Contact::factory()->create();
    $project = app(OpenProject::class)->byHand($contact, $this->owner);

    projectsListFor($contact)
        ->callTableAction('rename', $project, ['name' => str_repeat('a', 101)])
        ->assertHasTableActionErrors(['name' => 'max'])
        ->assertHasTableActionErrors(['name' => 'A name can be at most 100 characters.']);

    expect($project->refresh()->name)->toBeNull();
});

it('shows a refused name\'s reason without HTML', function () {
    activateExampleFolder();
    $contact = Contact::factory()->create();
    $project = app(OpenProject::class)->byHand($contact, $this->owner);

    $reason = 'A name cannot hold a hidden character, such as a direction mark or a zero-width space. Type it again.';

    projectsListFor($contact)
        ->callTableAction('rename', $project, ['name' => "Water\u{202E}<b>heater</b>"])
        ->assertNotified(Notification::make()->danger()->title(e($reason)));

    expect($project->refresh()->name)->toBeNull();
});

it('uses the word the profile sets', function () {
    breakFile('business/profile.md', fn (string $text) => str_replace('project_word: Job', 'project_word: Matter', $text));
    activateExampleFolder();
    $contact = Contact::factory()->create();
    $project = app(OpenProject::class)->byHand($contact, $this->owner);

    expect(ProjectsRelationManager::getTitle($contact, ViewContact::class))->toBe('Matters');

    projectsListFor($contact)
        ->assertTableActionHasLabel('new', 'New matter')
        ->assertSee('Matter 1')
        ->assertCanSeeTableRecords([$project]);
});

it('lists every project for a contact with two open ones', function () {
    activateExampleFolder();
    $contact = Contact::factory()->create();
    $first = app(OpenProject::class)->byHand($contact, $this->owner);
    $second = app(OpenProject::class)->byHand($contact, $this->owner);

    projectsListFor($contact)
        ->assertCanSeeTableRecords([$first, $second], inOrder: true)
        ->assertSee('Job 1')
        ->assertSee('Job 2');
});
