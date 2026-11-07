<?php

use App\Enums\ContactStatus;
use App\Filament\Resources\Contacts\ContactResource;
use App\Filament\Resources\Contacts\Pages\CreateContact;
use App\Filament\Resources\Contacts\Pages\ListContacts;
use App\Filament\Widgets\NewLeads;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\OwnerAlert;
use App\Models\Project;
use App\Models\User;
use Livewire\Livewire;

/**
 * Adding a contact by hand (D-19, 01 D-12): the owner brings in a customer who called rather than texted. The contact
 * is matched the way an inbound is (phone, then email), starts as a Lead, and opens nothing and sends nothing.
 */
beforeEach(function () {
    $this->owner = User::factory()->create(['name' => 'Ian Owner']);
    $this->actingAs($this->owner);
});

/**
 * Fill the New contact form and press Add contact.
 *
 * @param  array<string, mixed>  $data
 */
function contactByHandAdd(array $data): Livewire\Features\SupportTesting\Testable
{
    return Livewire::test(CreateContact::class)
        ->fillForm(['name' => '', 'phone' => '', 'email' => '', ...$data])
        ->call('create');
}

it('adds a contact by hand', function () {
    activateExampleFolder();

    contactByHandAdd(['name' => 'Sam Ortiz', 'phone' => '(312) 555-0188'])
        ->assertHasNoFormErrors();

    $sam = Contact::query()->sole();

    expect($sam->phone_e164)->toBe('+13125550188')
        ->and($sam->status)->toBe(ContactStatus::Lead)
        ->and($sam->projects()->count())->toBe(0)
        ->and(Project::query()->count())->toBe(0)
        ->and(Conversation::query()->count())->toBe(0)
        ->and(Message::query()->count())->toBe(0)
        ->and(OwnerAlert::query()->count())->toBe(0);

    contactByHandAdd(['name' => 'Pat Lee', 'email' => 'pat@example.com'])
        ->assertRedirect(ContactResource::getUrl('view', ['record' => Contact::query()->where('name', 'Pat Lee')->sole()]));

    Livewire::test(NewLeads::class)->assertCanSeeTableRecords([$sam]);
});

it('lands a later text on the same contact', function () {
    activateExampleFolder();

    contactByHandAdd(['name' => 'Sam Ortiz', 'phone' => '(312) 555-0188'])->assertHasNoFormErrors();

    $this->post(route('webhooks.text.fake'), fakeTextPayload(['From' => '+13125550188']))->assertOk();

    $sam = Contact::query()->sole();

    expect(Message::query()->sole()->conversation->contact_id)->toBe($sam->id);
});

it('stores the email the way an inbound matches it', function () {
    activateExampleFolder();

    contactByHandAdd(['name' => 'Sam Ortiz', 'email' => ' Sam.Ortiz@Example.com '])->assertHasNoFormErrors();

    expect(Contact::query()->sole()->email)->toBe('sam.ortiz@example.com');

    $this->postJson(route('leads.store'), [
        'name' => 'Sam Ortiz',
        'email' => 'SAM.ORTIZ@example.com',
        'source' => 'angi',
        'message' => 'Need a quote',
    ])->assertOk();

    expect(Contact::query()->count())->toBe(1)
        ->and(Message::query()->sole()->conversation->contact_id)->toBe(Contact::query()->sole()->id);
});

it('offers New contact on the list', function () {
    expect(ContactResource::canCreate())->toBeTrue();

    Livewire::test(ListContacts::class)->assertActionExists('create', fn ($action): bool => $action->getLabel() === 'New contact');
});
