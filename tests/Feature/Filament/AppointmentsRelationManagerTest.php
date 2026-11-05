<?php

use App\Actions\CloseProject;
use App\Actions\OpenProject;
use App\Filament\Resources\Contacts\ContactResource;
use App\Filament\Resources\Contacts\Pages\ViewContact;
use App\Filament\Resources\Contacts\RelationManagers\AppointmentsRelationManager;
use App\Filament\Resources\Contacts\RelationManagers\ProjectsRelationManager;
use App\Models\Appointment;
use App\Models\Contact;
use App\Models\Message;
use App\Models\OwnerAlert;
use App\Models\Project;
use App\Models\ScheduleBlock;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    $this->owner = User::factory()->create(['name' => 'Ian Owner']);
    $this->actingAs($this->owner);
});

/**
 * The live example folder, the clock at Tuesday 2026-11-03 11:00 in the shop's zone, and Dana Whitfield, who texted and
 * has one open job. $changeFolder may change the folder before it goes live.
 *
 * @return array{0: Contact, 1: Project}
 */
function appointmentsTabSetUp(?Closure $changeFolder = null): array
{
    if ($changeFolder !== null) {
        $changeFolder();
    }

    activateExampleFolder();
    test()->travelTo(CarbonImmutable::parse('2026-11-03 11:00', 'America/Chicago'));

    $contact = Contact::factory()->create(['name' => 'Dana Whitfield']);
    $conversation = $contact->conversations()->create();
    Message::factory()->create(['conversation_id' => $conversation->id]);
    $job = app(OpenProject::class)->byHand($contact, test()->owner);

    return [$contact, $job];
}

/**
 * The contact screen's Appointments tab, mounted the way the contact page mounts it.
 */
function appointmentsTab(Contact $contact): Testable
{
    return Livewire::test(AppointmentsRelationManager::class, [
        'ownerRecord' => $contact,
        'pageClass' => ViewContact::class,
    ]);
}

it('books by hand from the Appointments tab', function () {
    [$contact, $job] = appointmentsTabSetUp();

    appointmentsTab($contact)
        ->callTableAction('bookByHand', data: [
            'service' => 'ac-repair',
            'starts_at' => '2026-11-05 10:00',
            'project' => $job->id,
            'note' => '',
        ])
        ->assertHasNoTableActionErrors()
        ->assertNotified('Booked Thursday, November 5 at 10:00 AM.')
        ->assertSee('AC repair')
        ->assertSee('Job 1')
        ->assertSee('Booked');

    expect(Appointment::query()->sole()->project_id)->toBe($job->id);
});

it('lists the Appointments tab after Projects', function () {
    expect(ContactResource::getRelations())->toBe([
        ProjectsRelationManager::class,
        AppointmentsRelationManager::class,
    ]);
});

/**
 * The error messages the open dialog shows beside one field.
 *
 * @return list<string>
 */
function appointmentsTabFieldErrors(Testable $tab, string $field): array
{
    return collect($tab->errors()->toArray())
        ->filter(fn (array $messages, string $key): bool => str_ends_with($key, '.'.$field))
        ->flatten()
        ->values()
        ->all();
}

/**
 * Book AC repair from the tab, with the form fields given.
 *
 * @param  array<string, mixed>  $data
 */
function appointmentsTabBook(Testable $tab, array $data): Testable
{
    return $tab->callTableAction('bookByHand', data: [
        'service' => 'ac-repair',
        'note' => '',
        ...$data,
    ]);
}

it('refuses a past time beside Starts and accepts one a minute ahead', function () {
    [$contact, $job] = appointmentsTabSetUp();
    $tab = appointmentsTab($contact);

    $tab = appointmentsTabBook($tab, ['starts_at' => '2026-11-03 10:59', 'project' => $job->id])
        ->assertHasTableActionErrors(['starts_at']);

    expect(appointmentsTabFieldErrors($tab, 'starts_at'))->toBe(['Pick a time that has not passed.'])
        ->and(Appointment::query()->count())->toBe(0);

    appointmentsTabBook(appointmentsTab($contact), ['starts_at' => '2026-11-03 11:01', 'project' => $job->id])
        ->assertHasNoTableActionErrors();

    expect(Appointment::query()->count())->toBe(1);
});

it('refuses an overlap beside Starts, drive buffer included', function () {
    [$contact, $job] = appointmentsTabSetUp();
    Appointment::factory()->at(CarbonImmutable::parse('2026-11-05 10:00', 'America/Chicago'), 90)->create();

    $tab = appointmentsTabBook(appointmentsTab($contact), ['starts_at' => '2026-11-05 11:45', 'project' => $job->id])
        ->assertHasTableActionErrors(['starts_at']);

    expect(appointmentsTabFieldErrors($tab, 'starts_at'))->toBe(['That time is taken by another booking or blocked time. Pick another time.']);

    appointmentsTabBook(appointmentsTab($contact), ['starts_at' => '2026-11-05 12:00', 'project' => $job->id])
        ->assertHasNoTableActionErrors();
});

it('defaults the Job select to the only open job, to New with none and to nothing with two', function () {
    [$contact, $job] = appointmentsTabSetUp();

    appointmentsTab($contact)
        ->mountTableAction('bookByHand')
        ->assertTableActionDataSet(['project' => $job->id]);

    app(OpenProject::class)->byHand($contact, $this->owner);

    appointmentsTab($contact)
        ->mountTableAction('bookByHand')
        ->assertTableActionDataSet(['project' => null]);

    $fresh = Contact::factory()->create();

    appointmentsTab($fresh)
        ->mountTableAction('bookByHand')
        ->assertTableActionDataSet(['project' => 'new']);
});

it('makes the note optional and opens a new job when there is no open one', function () {
    appointmentsTabSetUp();
    $fresh = Contact::factory()->create();

    appointmentsTabBook(appointmentsTab($fresh), ['starts_at' => '2026-11-05 10:00', 'project' => 'new'])
        ->assertHasNoTableActionErrors();

    expect($fresh->projects()->count())->toBe(1)
        ->and($fresh->appointments()->sole()->note)->toBeNull();
});

it('refuses an unpicked job beside Job instead of opening one', function () {
    [$contact] = appointmentsTabSetUp();
    app(OpenProject::class)->byHand($contact, $this->owner);

    $tab = appointmentsTabBook(appointmentsTab($contact), ['starts_at' => '2026-11-05 10:00', 'project' => null])
        ->assertHasTableActionErrors(['project']);

    expect(appointmentsTabFieldErrors($tab, 'project'))->toBe(['Pick a job.'])
        ->and(Appointment::query()->count())->toBe(0)
        ->and($contact->projects()->count())->toBe(2);
});

it('offers only open jobs, plus New', function () {
    [$contact, $job] = appointmentsTabSetUp();
    $second = app(OpenProject::class)->byHand($contact, $this->owner);
    app(CloseProject::class)->handle($job, $this->owner);

    $tab = appointmentsTab($contact)->mountTableAction('bookByHand');

    $livewire = $tab->instance();
    $options = $livewire->{$livewire->getMountedActionSchemaName()}->getComponent('project')->getOptions();

    expect($options)->toBe([$second->id => 'Job 2', 'new' => 'New job']);
});

it('opens a new job when New is picked', function () {
    [$contact, $job] = appointmentsTabSetUp();

    appointmentsTabBook(appointmentsTab($contact), ['starts_at' => '2026-11-05 10:00', 'project' => 'new'])
        ->assertHasNoTableActionErrors();

    expect($contact->projects()->count())->toBe(2)
        ->and($contact->appointments()->sole()->project_id)->not->toBe($job->id);
});

it('refuses a long note beside Visit note', function () {
    [$contact, $job] = appointmentsTabSetUp();

    $tab = appointmentsTabBook(appointmentsTab($contact), ['starts_at' => '2026-11-05 10:00', 'project' => $job->id, 'note' => str_repeat('a', 201)])
        ->assertHasTableActionErrors(['note']);

    expect(appointmentsTabFieldErrors($tab, 'note'))->toBe(['A note can be at most 200 characters.'])
        ->and(Appointment::query()->count())->toBe(0);
});

it('leaves the contact\'s status alone and raises no alert', function () {
    [$contact, $job] = appointmentsTabSetUp();
    $status = $contact->status;

    appointmentsTabBook(appointmentsTab($contact), ['starts_at' => '2026-11-05 10:00', 'project' => $job->id]);

    expect($contact->refresh()->status)->toBe($status)
        ->and(OwnerAlert::query()->count())->toBe(0);
});

it('hides Book by hand and refuses with the room off', function () {
    [$contact] = appointmentsTabSetUp(fn () => File::deleteDirectory(icmPath('rooms/schedule_and_booking')));

    appointmentsTab($contact)->assertTableActionHidden('bookByHand');
});

it('reads the owner\'s time in the business zone on both change days', function (string $today, string $typed, string $stored) {
    [$contact, $job] = appointmentsTabSetUp();
    $this->travelTo(CarbonImmutable::parse($today, 'America/Chicago'));

    appointmentsTabBook(appointmentsTab($contact), ['starts_at' => $typed, 'project' => $job->id])
        ->assertHasNoTableActionErrors();

    expect($contact->appointments()->sole()->starts_at->utc()->format('Y-m-d H:i'))->toBe($stored);
})->with([
    'the missing hour in spring' => ['2026-03-06 09:00', '2026-03-08 02:30', '2026-03-08 08:30'],
    'the repeated hour in autumn' => ['2026-10-30 09:00', '2026-11-01 01:30', '2026-11-01 06:30'],
]);

it('shows the Appointments tab empty state and two visits on one job', function () {
    [$contact, $job] = appointmentsTabSetUp();

    appointmentsTab($contact)
        ->assertSee('No appointments yet')
        ->assertSee('Use Book by hand to add one.');

    Appointment::factory()->for($contact)->for($job)->at(CarbonImmutable::parse('2026-11-05 10:00', 'America/Chicago'), 90)->create();
    Appointment::factory()->for($contact)->for($job)->at(CarbonImmutable::parse('2026-11-06 10:00', 'America/Chicago'), 90)->create(['service_name' => 'Furnace repair']);

    appointmentsTab($contact)
        ->assertSee('Thursday, November 5, 2026 at 10:00 AM')
        ->assertSee('Friday, November 6, 2026 at 10:00 AM')
        ->assertSee('Furnace repair')
        ->assertDontSee('No appointments yet');
});

/**
 * The open Book by hand dialog's form as it renders. The standalone tab test does not draw the modal, so the mounted
 * schema is rendered directly.
 */
function appointmentsTabDialogHtml(Testable $tab): string
{
    $livewire = $tab->mountTableAction('bookByHand')->instance();

    return $livewire->{$livewire->getMountedActionSchemaName()}->toHtml();
}

it('says whether a confirmation text will go out', function (string $case, string $sentence) {
    $folder = $case === 'no booking file' ? fn () => File::delete(icmPath('rooms/schedule_and_booking/booking.md')) : null;
    [$texted] = appointmentsTabSetUp($folder);

    $contact = match ($case) {
        'texted', 'no booking file' => $texted,
        'web form' => Contact::factory()->create(),
        'opted out' => Contact::factory()->optedOut()->create(),
    };

    expect(appointmentsTabDialogHtml(appointmentsTab($contact)))->toContain($sentence);
})->with([
    'a contact who texted' => ['texted', 'A confirmation text will go out.'],
    'a web-form contact with the switch off' => ['web form', 'No confirmation text will go out. This customer has not agreed to automatic texts.'],
    'an opted-out contact' => ['opted out', 'No confirmation text will go out. This customer has opted out.'],
    'no booking file' => ['no booking file', 'No confirmation text will go out. Customer texts for booking are off until rooms/schedule_and_booking/booking.md is added.'],
]);

it('never shows a block on the tab', function () {
    [$contact, $job] = appointmentsTabSetUp();
    $visit = Appointment::factory()->for($contact)->for($job)->at(CarbonImmutable::parse('2026-11-05 10:00', 'America/Chicago'), 90)->create();
    ScheduleBlock::factory()->at(CarbonImmutable::parse('2026-11-05 13:00', 'America/Chicago'), 120)->create(['note' => 'Truck in the shop']);

    appointmentsTab($contact)
        ->assertCanSeeTableRecords([$visit])
        ->assertCountTableRecords(1)
        ->assertDontSee('Truck in the shop');
});

it('escapes a hostile visit note', function () {
    [$contact, $job] = appointmentsTabSetUp();
    Appointment::factory()->for($contact)->for($job)->at(CarbonImmutable::parse('2026-11-05 10:00', 'America/Chicago'), 90)->create(['note' => '"><script>alert(1)</script>']);

    appointmentsTab($contact)
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
});

it('has no Filament create, edit or delete action', function () {
    [$contact] = appointmentsTabSetUp();

    appointmentsTab($contact)
        ->assertTableActionDoesNotExist('create')
        ->assertTableActionDoesNotExist('edit')
        ->assertTableActionDoesNotExist('delete');
});
