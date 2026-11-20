<?php

use App\Icm\ActiveConfig;
use App\Models\Appointment;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Rooms\OwnerIntelligence\DurationText;
use App\Rooms\OwnerIntelligence\TimeToBooked;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

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

it('does not start the clock on a YES from a contact the carrier opted out (WR-02, D-01)', function () {
    Queue::fake();
    $dana = Contact::factory()->create(['name' => 'Dana Whitfield', 'phone_e164' => '+12025550123']);

    // What OutboundSender::recordCarrierOptOut() and the status callback write on a carrier block: no STOP is on file.
    $dana->update(['sms_opted_out_at' => timeToBookedAt('07:00')]);

    $this->travelTo(timeToBookedAt('08:00'));
    $this->post(route('webhooks.text.fake'), fakeTextPayload(['MessageSid' => 'SM'.str_repeat('1', 32), 'Body' => 'YES']))->assertOk();
    $this->travelTo(timeToBookedAt('09:00'));
    $this->post(route('webhooks.text.fake'), fakeTextPayload(['MessageSid' => 'SM'.str_repeat('2', 32), 'Body' => 'My AC is blowing warm air']))->assertOk();
    timeToBookedBooking($dana, '09:42');
    $this->travelTo(timeToBookedAt('12:00'));

    expect(app(TimeToBooked::class)->forContact($dana->id))->toBe(2520)
        ->and($dana->refresh()->isOptedOut())->toBeFalse()
        ->and(Message::query()->where('body', 'YES')->sole()->meta)->toHaveKey('keyword', 'start');
});

it('trusts the keyword decision recorded on the message over the words (WR-02)', function () {
    $recordedPlain = Contact::factory()->create();
    timeToBookedMessage($recordedPlain, 'STOP', '09:00', attributes: ['meta' => ['keyword' => null]]);
    timeToBookedBooking($recordedPlain, '09:42');

    $recordedKeyword = Contact::factory()->create();
    timeToBookedMessage($recordedKeyword, 'Hi there', '08:00', attributes: ['meta' => ['keyword' => 'help']]);
    timeToBookedMessage($recordedKeyword, 'Can you come out?', '09:00', attributes: ['meta' => ['keyword' => null]]);
    timeToBookedBooking($recordedKeyword, '09:42');

    expect(app(TimeToBooked::class)->forContact($recordedPlain->id))->toBe(2520)
        ->and(app(TimeToBooked::class)->forContact($recordedKeyword->id))->toBe(2520);
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

/**
 * A contact who texted at 9:00:00 on the given day and was first booked the given number of seconds later.
 */
function timeToBookedTook(int $seconds, string $day = '2026-11-10'): Contact
{
    $contact = Contact::factory()->create();
    timeToBookedMessage($contact, 'My AC is blowing warm air', '09:00:00', $day);
    Appointment::factory()->create([
        'contact_id' => $contact->id,
        'booked_at' => timeToBookedAt('09:00:00', $day)->addSeconds($seconds),
    ]);

    return $contact;
}

it('gives the median of an odd count (D-07)', function () {
    foreach ([600, 2520, 18000] as $seconds) {
        timeToBookedTook($seconds);
    }

    expect(app(TimeToBooked::class)->summaryLine())
        ->toBe('Half of your 3 new customers in the last 30 days booked within 42 minutes.');
});

it('gives the mean of the two middle values for an even count', function () {
    foreach ([600, 2400, 3000, 18000] as $seconds) {
        timeToBookedTook($seconds);
    }

    expect(app(TimeToBooked::class)->summaryLine())
        ->toBe('Half of your 4 new customers in the last 30 days booked within 45 minutes.');
});

it('says in under a minute when the median is under a minute', function () {
    foreach ([20, 50] as $seconds) {
        timeToBookedTook($seconds);
    }

    expect(app(TimeToBooked::class)->summaryLine())
        ->toBe('Half of your 2 new customers in the last 30 days booked in under a minute.');
});

it('says one customer in the singular', function () {
    timeToBookedTook(2520);

    expect(app(TimeToBooked::class)->summaryLine())
        ->toBe('Your 1 new customer in the last 30 days booked within 42 minutes.');
});

it('says in under a minute for one customer', function () {
    timeToBookedTook(40);

    expect(app(TimeToBooked::class)->summaryLine())
        ->toBe('Your 1 new customer in the last 30 days booked in under a minute.');
});

it('counts the 30 days in the business zone', function () {
    // The window starts at 2026-10-15 00:00:00 America/Chicago, so 2026-10-14 23:59:59 is out: today and the 29 days before it, across the November 1
    // daylight change.
    $inside = Contact::factory()->create();
    timeToBookedMessage($inside, 'Hello', '20:00', '2026-10-14');
    Appointment::factory()->create(['contact_id' => $inside->id, 'booked_at' => timeToBookedAt('00:00:00', '2026-10-15')]);

    $outside = Contact::factory()->create();
    timeToBookedMessage($outside, 'Hello', '20:00', '2026-10-14');
    Appointment::factory()->create(['contact_id' => $outside->id, 'booked_at' => timeToBookedAt('23:59:59', '2026-10-14')]);

    // First booked on 2026-10-01; the second booking this week does not make them new (D-02).
    $returning = Contact::factory()->create();
    timeToBookedMessage($returning, 'Hello', '09:00', '2026-10-01');
    timeToBookedBooking($returning, '09:30', '2026-10-01');
    timeToBookedBooking($returning, '10:00', '2026-11-12');

    $summary = app(TimeToBooked::class)->summary();

    expect($summary['count'])->toBe(1)
        ->and($summary['median_seconds'])->toBe(4 * 3600);
});

it('leaves out contacts with no number (D-03)', function () {
    timeToBookedTook(2520);

    $handAdded = Contact::factory()->create();
    timeToBookedBooking($handAdded, '10:00', '2026-11-10');

    $stopped = Contact::factory()->create();
    timeToBookedMessage($stopped, 'STOP', '09:00', '2026-11-10');
    timeToBookedBooking($stopped, '10:00', '2026-11-10');

    expect(app(TimeToBooked::class)->summary())->toBe(['count' => 1, 'median_seconds' => 2520]);
});

it('says so when no new customer booked (D-07)', function () {
    expect(app(TimeToBooked::class)->summaryLine())
        ->toBe(TimeToBooked::EMPTY_LINE)
        ->toBe('No new customers booked in the last 30 days.')
        ->and(app(TimeToBooked::class)->summary())->toBe(['count' => 0, 'median_seconds' => null]);
});

it('works out the summary in one query (D-04)', function () {
    $timeToBooked = app(TimeToBooked::class);
    app(ActiveConfig::class)->displayTimezone();

    $first = null;

    foreach ([600, 2520, 18000] as $seconds) {
        $first ??= timeToBookedTook($seconds);
        timeToBookedTook($seconds);
    }

    $timeToBooked->summaryLine();
    DB::enableQueryLog();
    DB::flushQueryLog();
    $timeToBooked->summaryLine();
    $withThree = count(DB::getQueryLog());
    DB::flushQueryLog();

    foreach (range(1, 7) as $number) {
        $contact = timeToBookedTook($number * 600);
        timeToBookedMessage($contact, 'Are you still coming?', '09:10:00', '2026-11-10');
    }

    $stopFirst = Contact::factory()->create();
    timeToBookedMessage($stopFirst, 'STOP', '08:00', '2026-11-10');
    timeToBookedMessage($stopFirst, 'Hello', '09:00', '2026-11-10');
    timeToBookedBooking($stopFirst, '09:30', '2026-11-10');

    timeToBookedBooking(Contact::factory()->create(), '10:00', '2026-11-10');
    timeToBookedBooking($first, '12:00', '2026-11-12');

    DB::flushQueryLog();
    $timeToBooked->summaryLine();
    $withTen = count(DB::getQueryLog());
    DB::flushQueryLog();

    $timeToBooked->forContact($first->id);
    $forOne = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($withThree)->toBe(1)
        ->and($withTen)->toBe($withThree)
        ->and($forOne)->toBe(1);
});
