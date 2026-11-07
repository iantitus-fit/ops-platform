<?php

use App\Actions\AddContactByHand;
use App\Actions\ContactRefused;
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
use Livewire\Features\SupportTesting\Testable;
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
function contactByHandAdd(array $data): Testable
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

/**
 * Dana Whitfield, already saved with a phone and an email.
 */
function contactByHandDana(): Contact
{
    return Contact::factory()->create([
        'name' => 'Dana Whitfield',
        'phone_e164' => '+13125550147',
        'email' => 'dana@example.com',
    ]);
}

it('refuses a phone that matches a contact beside the Phone field', function (string $typed) {
    contactByHandDana();

    contactByHandAdd(['name' => 'Sam Ortiz', 'phone' => $typed])
        ->assertHasErrors(['data.phone' => 'This number is already saved as Dana Whitfield.']);

    expect(Contact::query()->count())->toBe(1);
})->with([
    'national' => '(312) 555-0147',
    'dots' => '312.555.0147',
    'with the country code' => '+1 312 555 0147',
]);

it('refuses an email that matches a contact in another letter case beside the Email field', function () {
    contactByHandDana();

    contactByHandAdd(['name' => 'Sam Ortiz', 'phone' => '(312) 555-0188', 'email' => 'DANA@Example.com'])
        ->assertHasErrors(['data.email' => 'This email is already saved as Dana Whitfield.']);

    expect(Contact::query()->count())->toBe(1);
});

it('checks the phone before the email', function () {
    contactByHandDana();
    Contact::factory()->create(['name' => 'Pat Lee', 'email' => 'pat@example.com']);

    contactByHandAdd(['name' => 'Sam Ortiz', 'phone' => '312-555-0147', 'email' => 'pat@example.com'])
        ->assertHasErrors(['data.phone' => 'This number is already saved as Dana Whitfield.'])
        ->assertHasNoErrors('data.email');
});

it('names a matching contact with no name as (no name)', function () {
    Contact::factory()->create(['name' => null, 'phone_e164' => '+13125550147']);

    contactByHandAdd(['name' => 'Sam Ortiz', 'phone' => '312-555-0147'])
        ->assertHasErrors(['data.phone' => 'This number is already saved as (no name).']);
});

it('asks for a phone or an email beside Phone', function () {
    contactByHandAdd(['name' => 'Sam Ortiz'])
        ->assertHasErrors(['data.phone' => 'Enter a phone number or an email.']);

    expect(Contact::query()->count())->toBe(0);
});

it('says a number it cannot read is not a US phone number', function () {
    contactByHandAdd(['name' => 'Sam Ortiz', 'phone' => '555'])
        ->assertHasErrors(['data.phone' => 'This is not a US phone number we can text. Use one such as 312 555 0147.']);

    expect(Contact::query()->count())->toBe(0);
});

it('requires a name of at most 100 characters', function () {
    contactByHandAdd(['name' => '', 'phone' => '(312) 555-0188'])->assertHasFormErrors(['name' => 'required']);
    contactByHandAdd(['name' => str_repeat('a', 101), 'phone' => '(312) 555-0188'])->assertHasFormErrors(['name' => 'max']);

    expect(Contact::query()->count())->toBe(0);
});

it('turns a race on the unique phone into the same field error', function () {
    Contact::factory()->create(['name' => 'Dana Whitfield', 'phone_e164' => '+13125550147']);

    // The lookup finds nothing, as it would for a request that read the table just before the other one wrote.
    $racing = new class extends AddContactByHand
    {
        protected function matchByPhone(string $e164): ?Contact
        {
            return null;
        }
    };

    expect(fn () => $racing->handle('Sam Ortiz', '312-555-0147', null))
        ->toThrow(ContactRefused::class, 'This number is already saved as Dana Whitfield.');

    expect(Contact::query()->count())->toBe(1);
});
