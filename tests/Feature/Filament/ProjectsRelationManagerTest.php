<?php

use App\Actions\CloseProject;
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
use Dom\HTMLDocument;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    $this->owner = User::factory()->create(['name' => 'Ian Owner']);
    $this->actingAs($this->owner);
});

/**
 * The row buttons of a rendered project list, in row order: each one's trimmed visible text, accessible name and
 * wire:key. The link-styled buttons in the table body are the row actions.
 *
 * @return list<array{text: string, label: string|null, key: string|null}>
 */
function projectsRowButtons(string $html): array
{
    $buttons = [];

    foreach (HTMLDocument::createFromString($html, LIBXML_NOERROR)->querySelectorAll('tbody button.fi-ac-link-action') as $button) {
        $buttons[] = [
            'text' => trim($button->textContent),
            'label' => $button->getAttribute('aria-label'),
            'key' => $button->getAttribute('wire:key'),
        ];
    }

    return $buttons;
}

/**
 * One contact with the three projects the button-name cases read: Job 1 open, Johnny open, Job 3 closed.
 *
 * @return array{0: Contact, 1: Project, 2: Project, 3: Project}
 */
function projectsNamedFixture(User $owner): array
{
    $contact = Contact::factory()->create();
    $first = app(OpenProject::class)->byHand($contact, $owner);
    $second = app(OpenProject::class)->byHand($contact, $owner);
    app(RenameProject::class)->handle($second, $owner, 'Johnny');
    $third = app(OpenProject::class)->byHand($contact, $owner);
    app(CloseProject::class)->handle($third, $owner);

    return [$contact, $first, $second, $third];
}

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

it('names each row button by its project, visible text unchanged', function () {
    activateExampleFolder();
    [$contact] = projectsNamedFixture($this->owner);

    projectsListFor($contact)
        ->assertSeeHtml('aria-label="Close Job 1"')
        ->assertSeeHtml('aria-label="Rename Job 1"')
        ->assertSeeHtml('aria-label="Close Johnny"')
        ->assertSeeHtml('aria-label="Rename Johnny"')
        ->assertSeeHtml('aria-label="Reopen Job 3"')
        ->assertSeeHtml('aria-label="Rename Job 3"');
});

it('puts the visible word first in every row button\'s name', function () {
    activateExampleFolder();
    [$contact] = projectsNamedFixture($this->owner);

    $pairs = array_map(
        fn (array $button): array => [$button['text'], $button['label']],
        projectsRowButtons(projectsListFor($contact)->html()),
    );

    expect($pairs)->toBe([
        ['Close', 'Close Job 1'],
        ['Rename', 'Rename Job 1'],
        ['Close', 'Close Johnny'],
        ['Rename', 'Rename Johnny'],
        ['Reopen', 'Reopen Job 3'],
        ['Rename', 'Rename Job 3'],
    ]);
});

it('names the button after a rename', function () {
    activateExampleFolder();
    $contact = Contact::factory()->create();
    $project = app(OpenProject::class)->byHand($contact, $this->owner);

    projectsListFor($contact)
        ->assertSeeHtml('aria-label="Rename Job 1"')
        ->callTableAction('rename', $project, ['name' => 'Renamed'])
        ->assertSeeHtml('aria-label="Rename Renamed"')
        ->assertDontSeeHtml('aria-label="Rename Job 1"');
});

it('escapes an owner-typed name in a button\'s name', function () {
    activateExampleFolder();
    $contact = Contact::factory()->create();
    $hostile = app(OpenProject::class)->byHand($contact, $this->owner);
    $name = 'Johnny "the <b>boss</b>" & co\'s &amp; <script>x</script>';
    app(RenameProject::class)->handle($hostile, $this->owner, $name);
    $ampersand = app(OpenProject::class)->byHand($contact, $this->owner);
    app(RenameProject::class)->handle($ampersand, $this->owner, 'Smith & Sons');

    $list = projectsListFor($contact)
        ->assertSeeHtml('aria-label="'.e('Close '.$name).'"')
        ->assertSeeHtml('aria-label="'.e('Rename '.$name).'"')
        ->assertSeeHtml('aria-label="Close Smith &amp; Sons"')
        ->assertDontSeeHtml('Smith &amp;amp; Sons')
        ->assertDontSeeHtml('aria-label="Close Smith & Sons"');

    $html = $list->html();
    $labels = array_column(projectsRowButtons($html), 'label');
    $document = HTMLDocument::createFromString($html, LIBXML_NOERROR);
    $injectedScripts = array_filter(
        iterator_to_array($document->querySelectorAll('script')),
        fn ($script): bool => trim($script->textContent) === 'x',
    );

    expect($labels)->toContain('Close '.$name, 'Rename '.$name, 'Close Smith & Sons')
        ->and($document->querySelectorAll('b')->length)->toBe(0)
        ->and($injectedScripts)->toBe([]);
});

it('carries the names in the contact page\'s first response', function () {
    activateExampleFolder();
    $contact = Contact::factory()->create();
    app(OpenProject::class)->byHand($contact, $this->owner);

    $this->get(ContactResource::getUrl('view', ['record' => $contact]))
        ->assertOk()
        ->assertSee('aria-label="Close Job 1"', false);
});
