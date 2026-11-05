<?php

use App\Actions\OpenProject;
use App\Filament\Resources\Contacts\ContactResource;
use App\Filament\Resources\Contacts\Pages\ViewContact;
use App\Filament\Resources\Contacts\RelationManagers\AppointmentsRelationManager;
use App\Filament\Resources\Contacts\RelationManagers\ProjectsRelationManager;
use App\Models\Appointment;
use App\Models\Contact;
use App\Models\Message;
use App\Models\Project;
use App\Models\User;
use Carbon\CarbonImmutable;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    $this->owner = User::factory()->create(['name' => 'Ian Owner']);
    $this->actingAs($this->owner);
});

/**
 * The live example folder, the clock at Tuesday 2026-11-03 11:00 in the shop's zone, and Dana Whitfield, who texted and
 * has one open job.
 *
 * @return array{0: Contact, 1: Project}
 */
function appointmentsTabSetUp(): array
{
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
