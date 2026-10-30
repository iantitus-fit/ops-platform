<?php

use App\Actions\OpenProject;
use App\Enums\ProjectActor;
use App\Filament\Resources\Contacts\ContactResource;
use App\Filament\Resources\Contacts\Pages\ViewContact;
use App\Filament\Resources\Contacts\RelationManagers\ProjectsRelationManager;
use App\Models\Contact;
use App\Models\Message;
use App\Models\OwnerAlert;
use App\Models\Project;
use App\Models\User;
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

it('is not read-only on the contact screen', function () {
    activateExampleFolder();
    $contact = Contact::factory()->create();

    $list = projectsListFor($contact);

    expect($list->instance()->isReadOnly())->toBeFalse();

    $list->assertTableActionVisible('new');
});

it('shows on the contact page', function () {
    activateExampleFolder();
    $contact = Contact::factory()->create();

    $this->get(ContactResource::getUrl('view', ['record' => $contact]))->assertOk();

    // Filament draws a relation manager as its own lazy-loaded component, so the page itself holds only its place.
    Livewire::test(ViewContact::class, ['record' => $contact->id])
        ->assertSeeLivewire(ProjectsRelationManager::class);

    expect(ContactResource::getRelations())->toBe([ProjectsRelationManager::class]);
});
