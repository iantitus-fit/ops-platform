<?php

use App\Models\Appointment;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Rooms\OwnerIntelligence\DurationText;
use App\Rooms\OwnerIntelligence\TimeToBooked;
use Carbon\CarbonImmutable;

/**
 * BOOK-06: a contact's time to booked is worked out on read from two recorded facts, the first real inbound message and
 * the first booking ever (D-01 to D-04). Every time below is written as America/Chicago wall time and stored in UTC
 * (the app zone), because Eloquent saves a date as its wall time and drops the zone (research Pitfall 2). Pest functions
 * are global, so every helper here carries the timeToBooked prefix.
 */

/**
 * A moment on 2026-11-13 in the business zone, stored the way the database holds it: in UTC.
 */
function timeToBookedAt(string $clock, string $day = '2026-11-13'): CarbonImmutable
{
    return CarbonImmutable::parse("{$day} {$clock}", 'America/Chicago')->utc();
}

/**
 * An inbound message in the contact's first conversation (made if the contact has none), at the given Chicago time.
 *
 * @param  array<string, mixed>  $attributes
 */
function timeToBookedMessage(Contact $contact, string $body, string $clock, string $day = '2026-11-13', array $attributes = [], bool $web = false): Message
{
    $conversation = Conversation::query()->where('contact_id', $contact->id)->orderBy('id')->first()
        ?? Conversation::factory()->create(['contact_id' => $contact->id]);

    $factory = $web ? Message::factory()->web() : Message::factory();

    return $factory->create(array_merge([
        'conversation_id' => $conversation->id,
        'body' => $body,
        'created_at' => timeToBookedAt($clock, $day),
    ], $attributes));
}

/**
 * A booked appointment for the contact, booked at the given Chicago time (a new project each unless one is passed).
 */
function timeToBookedBooking(Contact $contact, string $clock, string $day = '2026-11-13'): Appointment
{
    return Appointment::factory()->create([
        'contact_id' => $contact->id,
        'booked_at' => timeToBookedAt($clock, $day),
    ]);
}

beforeEach(function () {
    activateExampleFolder();
    $this->travelTo(timeToBookedAt('12:00'));
});

it('works out one contact\'s time to booked', function () {
    $dana = Contact::factory()->create(['name' => 'Dana Whitfield']);
    timeToBookedMessage($dana, 'My AC is blowing warm air', '09:00');
    timeToBookedBooking($dana, '09:42');

    $seconds = app(TimeToBooked::class)->forContact($dana->id);

    expect($seconds)->toBe(2520)
        ->and(DurationText::format($seconds))->toBe('42 minutes');
});

it('skips a keyword-only first text (D-01)', function (string $keyword) {
    $contact = Contact::factory()->create();
    timeToBookedMessage($contact, $keyword, '08:00');
    timeToBookedMessage($contact, 'Can you come out?', '09:00');
    timeToBookedBooking($contact, '09:42');

    expect(app(TimeToBooked::class)->forContact($contact->id))->toBe(2520);
})->with(['STOP' => 'STOP', 'HELP' => 'HELP', 'padded and lower case' => ' Stop ']);

it('treats YES as a keyword only while the contact is opted out', function () {
    $optedOut = Contact::factory()->create();
    timeToBookedMessage($optedOut, 'STOP', '08:00');
    timeToBookedMessage($optedOut, 'YES', '08:30');
    timeToBookedMessage($optedOut, 'Hi', '09:00');
    timeToBookedBooking($optedOut, '09:42');

    $notOptedOut = Contact::factory()->create();
    timeToBookedMessage($notOptedOut, 'YES', '08:00');
    timeToBookedBooking($notOptedOut, '08:42');

    expect(app(TimeToBooked::class)->forContact($optedOut->id))->toBe(2520)
        ->and(app(TimeToBooked::class)->forContact($notOptedOut->id))->toBe(2520);
});

it('starts on a sentence that holds a keyword word', function () {
    $contact = Contact::factory()->create();
    timeToBookedMessage($contact, 'stop the noise please', '09:00');
    timeToBookedBooking($contact, '09:42');

    expect(app(TimeToBooked::class)->forContact($contact->id))->toBe(2520);
});

it('starts on a web form, even one that reads STOP', function () {
    $contact = Contact::factory()->create();
    timeToBookedMessage($contact, 'STOP', '09:00', web: true);
    timeToBookedBooking($contact, '09:42');

    expect(app(TimeToBooked::class)->forContact($contact->id))->toBe(2520);
});

it('reads every conversation of the contact', function () {
    $contact = Contact::factory()->create();
    $older = Conversation::factory()->closed()->create(['contact_id' => $contact->id]);
    $newer = Conversation::factory()->create(['contact_id' => $contact->id]);
    timeToBookedMessage($contact, 'Is anyone there?', '09:20', attributes: ['conversation_id' => $newer->id]);
    timeToBookedMessage($contact, 'My AC is blowing warm air', '09:00', attributes: ['conversation_id' => $older->id]);
    timeToBookedBooking($contact, '09:42');

    expect(app(TimeToBooked::class)->forContact($contact->id))->toBe(2520);
});

it('stops at the first booking ever, whichever project and whoever booked it (D-02)', function () {
    $twoProjects = Contact::factory()->create();
    timeToBookedMessage($twoProjects, 'My AC is blowing warm air', '09:00');
    timeToBookedBooking($twoProjects, '10:30');
    timeToBookedBooking($twoProjects, '09:42');

    $cancelled = Contact::factory()->create();
    timeToBookedMessage($cancelled, 'My AC is blowing warm air', '09:00');
    Appointment::factory()->cancelled()->create(['contact_id' => $cancelled->id, 'booked_at' => timeToBookedAt('09:42')]);
    timeToBookedBooking($cancelled, '10:30');

    $byCustomer = Contact::factory()->create();
    timeToBookedMessage($byCustomer, 'My AC is blowing warm air', '09:00');
    Appointment::factory()->byCustomer()->create(['contact_id' => $byCustomer->id, 'booked_at' => timeToBookedAt('09:42')]);

    $returning = Contact::factory()->create();
    timeToBookedMessage($returning, 'My AC is blowing warm air', '09:00', '2026-09-01');
    timeToBookedBooking($returning, '09:42', '2026-09-01');
    timeToBookedMessage($returning, 'Back again', '08:00', '2026-11-12');
    timeToBookedBooking($returning, '08:10', '2026-11-12');

    $timeToBooked = app(TimeToBooked::class);

    expect($timeToBooked->forContact($twoProjects->id))->toBe(2520)
        ->and($timeToBooked->forContact($cancelled->id))->toBe(2520)
        ->and($timeToBooked->forContact($byCustomer->id))->toBe(2520)
        ->and($timeToBooked->forContact($returning->id))->toBe(2520);
});

it('gives no number when there is no message before the first booking (D-03)', function () {
    $timeToBooked = app(TimeToBooked::class);

    $onlyBooking = Contact::factory()->create();
    timeToBookedBooking($onlyBooking, '09:42');

    $messageAfter = Contact::factory()->create();
    timeToBookedMessage($messageAfter, 'Thanks for booking', '10:00');
    timeToBookedBooking($messageAfter, '09:42');

    $sameSecond = Contact::factory()->create();
    timeToBookedMessage($sameSecond, 'Book me now', '09:42');
    timeToBookedBooking($sameSecond, '09:42');

    $neverBooked = Contact::factory()->create();
    timeToBookedMessage($neverBooked, 'My AC is blowing warm air', '09:00');

    $onlyKeywords = Contact::factory()->create();
    timeToBookedMessage($onlyKeywords, 'STOP', '08:00');
    timeToBookedMessage($onlyKeywords, 'HELP', '08:30');
    timeToBookedBooking($onlyKeywords, '09:42');

    expect($timeToBooked->forContact($onlyBooking->id))->toBeNull()
        ->and($timeToBooked->forContact($messageAfter->id))->toBeNull()
        ->and($timeToBooked->forContact($sameSecond->id))->toBeNull()
        ->and($timeToBooked->forContact($neverBooked->id))->toBeNull()
        ->and($timeToBooked->forContact($onlyKeywords->id))->toBeNull()
        ->and($timeToBooked->forContact(999999))->toBeNull();
});
