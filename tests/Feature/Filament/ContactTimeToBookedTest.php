<?php

use App\Filament\Resources\Contacts\Pages\ViewContact;
use App\Models\Appointment;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Records\CustomerCard;
use App\Rooms\OwnerIntelligence\TimeToBooked;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\File;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * BOOK-06, D-05: the contact screen shows "Time to booked" for a contact who has a number, after Next appointment and
 * before Automatic texts, and shows nothing for a contact without one, with the room off, or when the number cannot be
 * worked out. The customer card is not touched. The clock is frozen at 2026-11-13 12:00 in the business zone and every
 * time is stored in UTC. Pest functions are global, so every helper here carries the contactTimeToBooked prefix.
 */
beforeEach(function () {
    $this->actingAs(User::factory()->create());

    activateExampleFolder();
    $this->travelTo(contactTimeToBookedAt('12:00'));
});

/**
 * A moment on 2026-11-13 in the business zone, stored the way the database holds it: in UTC.
 */
function contactTimeToBookedAt(string $clock): CarbonImmutable
{
    return CarbonImmutable::parse("2026-11-13 {$clock}", 'America/Chicago')->utc();
}

/**
 * A contact whose first message came at the first time and who was booked at the second, as a text or a web form.
 */
function contactTimeToBookedContact(string $name, string $texted, string $booked, bool $web = false): Contact
{
    $contact = Contact::factory()->create(['name' => $name]);
    $conversation = Conversation::factory()->create(['contact_id' => $contact->id]);
    $factory = $web ? Message::factory()->web() : Message::factory();

    $factory->create([
        'conversation_id' => $conversation->id,
        'body' => 'My AC is blowing warm air',
        'created_at' => contactTimeToBookedAt($texted),
    ]);

    Appointment::factory()->create([
        'contact_id' => $contact->id,
        'booked_at' => contactTimeToBookedAt($booked),
    ]);

    return $contact;
}

/**
 * The contact's screen, the way the owner opens it.
 */
function contactTimeToBookedScreen(Contact $contact): Testable
{
    return Livewire::test(ViewContact::class, ['record' => $contact->getRouteKey()]);
}

it('shows the time to booked on the contact screen (D-05)', function () {
    $dana = contactTimeToBookedContact('Dana Whitfield', '09:00', '09:42');

    contactTimeToBookedScreen($dana)
        ->assertSee('Time to booked')
        ->assertSee('42 minutes');
});

it('places it after Next appointment and before Automatic texts', function () {
    $dana = contactTimeToBookedContact('Dana Whitfield', '09:00', '09:42', web: true);

    contactTimeToBookedScreen($dana)
        ->assertSeeInOrder(['Next appointment', 'Time to booked', '42 minutes', 'Automatic texts']);
});

it('shows no line with no number (D-03)', function () {
    $booked = Contact::factory()->create(['name' => 'Marcus Lee']);
    Appointment::factory()->create(['contact_id' => $booked->id, 'booked_at' => contactTimeToBookedAt('09:42')]);

    $never = Contact::factory()->create(['name' => 'Lee Park']);
    Message::factory()->create([
        'conversation_id' => Conversation::factory()->create(['contact_id' => $never->id])->id,
        'created_at' => contactTimeToBookedAt('09:00'),
    ]);

    contactTimeToBookedScreen($booked)->assertSee('Marcus Lee')->assertDontSee('Time to booked');
    contactTimeToBookedScreen($never)->assertSee('Lee Park')->assertDontSee('Time to booked');
});

it('hides the line while the room is off and brings it back (D-08)', function () {
    $dana = contactTimeToBookedContact('Dana Whitfield', '09:00', '09:42');
    $context = File::get(base_path('examples/example-hvac/rooms/owner_intelligence/CONTEXT.md'));

    File::deleteDirectory(icmPath('rooms/owner_intelligence'));
    activateExampleFolder();

    contactTimeToBookedScreen($dana)->assertSee('Dana Whitfield')->assertDontSee('Time to booked');

    writeIcmFile('rooms/owner_intelligence/CONTEXT.md', $context);
    activateExampleFolder();

    contactTimeToBookedScreen($dana)->assertSee('Time to booked')->assertSee('42 minutes');
});

it('leaves the line out and reports when it cannot be worked out', function () {
    $dana = contactTimeToBookedContact('Dana Whitfield', '09:00', '09:42');

    Exceptions::fake();
    $this->mock(TimeToBooked::class, function ($mock): void {
        $mock->shouldReceive('forContact')->andThrow(new RuntimeException('could not work it out'));
    });

    contactTimeToBookedScreen($dana)
        ->assertSee('Dana Whitfield')
        ->assertDontSee('Time to booked');

    Exceptions::assertReported(RuntimeException::class);
});

it('leaves the customer card unchanged (D-05)', function () {
    $dana = contactTimeToBookedContact('Dana Whitfield', '09:00', '09:42');

    expect(app(CustomerCard::class)->render($dana))->not->toContain('Time to booked');
});
