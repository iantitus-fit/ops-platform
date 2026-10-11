<?php

use App\Enums\ActivityType;
use App\Enums\ContactStatus;
use App\Filament\Resources\Contacts\Pages\ListContacts;
use App\Filament\Resources\Contacts\Pages\ViewContact;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

/**
 * A timeline entry for a contact, pointing at a message in the contact's conversation.
 *
 * @param  array<string, mixed>  $attributes
 */
function timelineEntry(Contact $contact, string $body, array $attributes = []): Activity
{
    $conversation = $contact->openConversation()->first() ?? $contact->conversations()->create();
    $message = Message::factory()->for($conversation)->create(['body' => $body]);

    return Activity::factory()->for($contact)->create([
        'subject_type' => $message->getMorphClass(),
        'subject_id' => $message->id,
        ...$attributes,
    ]);
}

it('lists contacts with the most recent activity first, breaking ties by id', function () {
    $oldest = Contact::factory()->create(['last_activity_at' => now()->subDays(2)]);
    $sameSecondFirst = Contact::factory()->create(['last_activity_at' => '2026-10-11 09:00:00']);
    $sameSecondSecond = Contact::factory()->create(['last_activity_at' => '2026-10-11 09:00:00']);
    $newest = Contact::factory()->create(['last_activity_at' => now()]);

    Livewire::test(ListContacts::class)
        ->assertCanSeeTableRecords([$newest, $sameSecondSecond, $sameSecondFirst, $oldest], inOrder: true);
});

it('finds a contact by phone', function () {
    $dana = Contact::factory()->create(['phone_e164' => '+12025550123']);
    $other = Contact::factory()->create(['phone_e164' => '+13125550147']);

    Livewire::test(ListContacts::class)
        ->searchTable('+1202555')
        ->assertCanSeeTableRecords([$dana])
        ->assertCanNotSeeTableRecords([$other]);
});

it('says there are no contacts when the list is empty', function () {
    Livewire::test(ListContacts::class)->assertSee('No contacts');
});

it('shows the cost per lead in dollars and leaves it blank when unknown', function () {
    $costed = Contact::factory()->fromSource('angi', 4500)->create();
    $unknown = Contact::factory()->create(['acquisition_cost_cents' => null]);

    Livewire::test(ListContacts::class)
        ->assertTableColumnFormattedStateSet('acquisition_cost_cents', '$45.00', record: $costed)
        ->assertTableColumnFormattedStateSet('acquisition_cost_cents', null, record: $unknown);

    expect($costed->fresh()->acquisition_cost_cents)->toBe(4500);
});

it('shows a contact with the first-touch source and a timeline newest first', function () {
    $contact = Contact::factory()->fromSource('angi', 4500)->create([
        'name' => 'Dana Whitfield',
        'phone_e164' => '+12025550123',
        'email' => 'dana@example.com',
        'status' => ContactStatus::Lead,
    ]);
    timelineEntry($contact, 'Older text body', [
        'type' => ActivityType::TextReceived,
        'occurred_at' => '2026-10-10 09:00:00',
    ]);
    timelineEntry($contact, 'Newer form body', [
        'type' => ActivityType::FormReceived,
        'lead_source_key' => 'thumbtack',
        'cost_cents' => 2500,
        'occurred_at' => '2026-10-11 09:00:00',
    ]);

    Livewire::test(ViewContact::class, ['record' => $contact->id])
        ->assertSee('Dana Whitfield')
        ->assertSee('+12025550123')
        ->assertSee('dana@example.com')
        ->assertSee('angi')
        ->assertSee('$45.00')
        ->assertSeeInOrder(['Newer form body', 'thumbtack', '$25.00', 'Older text body']);
});

it('returns the timeline ordered by when it happened, then by id, both newest first', function () {
    Carbon::setTestNow('2026-10-11 12:00:00');
    $contact = Contact::factory()->create();
    $older = Activity::factory()->for($contact)->create(['occurred_at' => now()->subHour()]);
    $sameSecondFirst = Activity::factory()->for($contact)->create(['occurred_at' => now()]);
    $sameSecondSecond = Activity::factory()->for($contact)->create(['occurred_at' => now()]);

    expect($contact->timeline->pluck('id')->all())
        ->toBe([$sameSecondSecond->id, $sameSecondFirst->id, $older->id]);
});

it('shows customer-written text as plain text, never as HTML', function () {
    $contact = Contact::factory()->create();
    timelineEntry($contact, '<b>bold</b><script>alert(1)</script>', ['type' => ActivityType::TextReceived]);

    Livewire::test(ViewContact::class, ['record' => $contact->id])
        ->assertDontSeeHtml('<b>bold</b>')
        ->assertDontSeeHtml('<script>alert(1)</script>')
        ->assertSee('<b>bold</b>');
});
