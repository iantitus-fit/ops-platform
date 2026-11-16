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
