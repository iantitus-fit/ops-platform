<?php

use App\Actions\OutboundSender;
use App\Enums\AlertReason;
use App\Enums\GateOutcome;
use App\Enums\MessageAuthor;
use App\Enums\MessageDirection;
use App\Enums\MessageKind;
use App\Enums\MessageStatus;
use App\Enums\SendOrigin;
use App\Models\Appointment;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\OwnerAlert;
use App\Models\User;
use App\SendRule\CustomerReading;
use App\SendRule\ReplyParts;
use Illuminate\Support\Carbon;

/**
 * A customer who has texted, an open conversation, and a booked visit on 2026-11-05 at 16:00 UTC, with the clock at
 * 2026-11-04 15:00 UTC so the visit is a day ahead. The folder is live and no customer text is waiting.
 *
 * @return array{0: Conversation, 1: Appointment}
 */
function bookingKindBegins(): array
{
    activateExampleFolder();
    test()->travelTo(Carbon::parse('2026-11-04 15:00', 'UTC'));

    $contact = Contact::factory()->create();
    $conversation = Conversation::factory()->for($contact)->create();

    Message::factory()->for($conversation)->create(['handled_at' => now()]);

    $appointment = Appointment::factory()->create([
        'contact_id' => $contact->id,
        'starts_at' => Carbon::parse('2026-11-05 16:00', 'UTC'),
    ]);

    return [$conversation, $appointment];
}

/**
 * A booking text sent straight through the one outbound path, with the meta a caller names.
 *
 * @param  array<string, mixed>  $meta
 */
function bookingKindSend(Conversation $conversation, array $meta): Message
{
    return app(OutboundSender::class)->send($conversation, 'Reminder line', MessageAuthor::Agent, SendOrigin::Automated, [
        'kind' => MessageKind::BookingText,
        'meta' => $meta,
    ]);
}

/**
 * The sentence a send was refused with.
 *
 * @param  callable(): mixed  $send
 */
function bookingKindRefusal(callable $send): string
{
    try {
        $send();
    } catch (DomainException $exception) {
        return $exception->getMessage();
    }

    return 'It was sent.';
}

/**
 * The attributes of an agent reply made of model words and a booking link, with the given reading on its gate.
 *
 * @return array<string, mixed>
 */
function bookingKindLinkAttributes(CustomerReading $reading): array
{
    return [
        'kind' => MessageKind::AgentReply,
        'gate_outcome' => GateOutcome::Send,
        'gate' => [
            'version' => 1,
            'customer_reading' => $reading->toArray(),
            'checks' => ['classifier' => ['ran' => true, 'error' => null, 'reading' => ['sendable' => true]]],
        ],
        'meta' => ['parts' => ['model_words', 'booking_link']],
    ];
}

it('sends a booking text with no reading of a customer text (D-26)', function () {
    [$conversation, $appointment] = bookingKindBegins();

    $message = bookingKindSend($conversation, ['appointment_id' => $appointment->id, 'parts' => ['booking_line']]);

    expect($message->kind)->toBe(MessageKind::BookingText)
        ->and($message->status)->toBe(MessageStatus::Sent)
        ->and(OwnerAlert::query()->count())->toBe(0);
});

it('refuses a booking text that does not name its appointment, its parts or its customer, and carries the model\'s words', function (string $case) {
    [$conversation, $appointment] = bookingKindBegins();

    $otherCustomersVisit = Appointment::factory()->create([
        'contact_id' => Contact::factory()->create()->id,
        'starts_at' => Carbon::parse('2026-11-05 16:00', 'UTC'),
    ]);

    $meta = match ($case) {
        'no appointment id' => ['parts' => ['booking_line']],
        'a text for an id' => ['appointment_id' => 'abc', 'parts' => ['booking_line']],
        'another contact\'s appointment' => ['appointment_id' => $otherCustomersVisit->id, 'parts' => ['booking_line']],
        'no parts' => ['appointment_id' => $appointment->id],
        'empty parts' => ['appointment_id' => $appointment->id, 'parts' => []],
        'the model\'s words' => ['appointment_id' => $appointment->id, 'parts' => ['booking_line', 'model_words']],
    };

    expect(bookingKindRefusal(fn () => bookingKindSend($conversation, $meta)))->toStartWith(OutboundSender::BOOKING_TEXT_REFUSED)
        ->and(Message::query()->where('direction', MessageDirection::Out)->count())->toBe(0)
        ->and(OwnerAlert::query()->count())->toBe(0);
})->with([
    'no appointment id',
    'a text for an id',
    'another contact\'s appointment',
    'no parts',
    'empty parts',
    'the model\'s words',
]);

it('drops a booking text whose appointment is no longer booked or no longer ahead', function (string $case) {
    [$conversation, $appointment] = bookingKindBegins();

    match ($case) {
        'cancelled' => Appointment::query()->whereKey($appointment->id)->update(['state' => 'cancelled']),
        'done' => Appointment::query()->whereKey($appointment->id)->update(['state' => 'done']),
        'starts exactly now' => Appointment::query()->whereKey($appointment->id)->update(['starts_at' => now()]),
        'started an hour ago' => Appointment::query()->whereKey($appointment->id)->update(['starts_at' => now()->subHour()]),
    };

    expect(bookingKindRefusal(fn () => bookingKindSend($conversation, ['appointment_id' => $appointment->id, 'parts' => ['booking_line']])))
        ->toBe(OutboundSender::APPOINTMENT_NOT_AHEAD)
        ->and(Message::query()->where('direction', MessageDirection::Out)->count())->toBe(0)
        ->and(OwnerAlert::query()->count())->toBe(0);
})->with(['cancelled', 'done', 'starts exactly now', 'started an hour ago']);

it('sends a booking text for a visit that starts a minute from now', function () {
    [$conversation, $appointment] = bookingKindBegins();

    Appointment::query()->whereKey($appointment->id)->update(['starts_at' => now()->addMinute()]);

    expect(bookingKindSend($conversation, ['appointment_id' => $appointment->id, 'parts' => ['booking_line']])->status)->toBe(MessageStatus::Sent);
});

it('waits while a customer text is unread, and goes out once it is handled', function () {
    [$conversation, $appointment] = bookingKindBegins();
    $meta = ['appointment_id' => $appointment->id, 'parts' => ['booking_line']];

    $unread = Message::factory()->for($conversation)->create(['body' => 'Someone is hurt here']);

    expect(bookingKindRefusal(fn () => bookingKindSend($conversation, $meta)))->toBe(OutboundSender::UNREAD_CUSTOMER_TEXT)
        ->and(Message::query()->where('direction', MessageDirection::Out)->count())->toBe(0)
        ->and(OwnerAlert::query()->count())->toBe(0);

    $unread->update(['handled_at' => now()]);

    expect(bookingKindSend($conversation, $meta)->status)->toBe(MessageStatus::Sent);
});

it('checks opt-out first and the automatic-texts rule for a booking text', function () {
    [$conversation, $appointment] = bookingKindBegins();

    $optedOut = Conversation::factory()->for(Contact::factory()->optedOut()->create())->create();
    $neverTexted = Conversation::factory()->for(Contact::factory()->create())->create();

    expect(bookingKindRefusal(fn () => bookingKindSend($optedOut, ['parts' => ['booking_line']])))->toStartWith('Opted out by texting STOP on')
        ->and(bookingKindRefusal(fn () => bookingKindSend($neverTexted, ['appointment_id' => $appointment->id, 'parts' => ['booking_line']])))->toBe(OutboundSender::NOT_AGREED);
});

it('keeps the reading rule for every other kind', function () {
    [$conversation] = bookingKindBegins();
    User::factory()->withPhone('+12025550199')->create();

    $refusal = bookingKindRefusal(fn () => app(OutboundSender::class)->send($conversation, 'Hello', MessageAuthor::Agent, SendOrigin::Automated, [
        'kind' => MessageKind::AgentReply,
    ]));

    expect($refusal)->toStartWith(OutboundSender::NO_READING)
        ->and(OwnerAlert::query()->where('reason', AlertReason::SafetyCheckMissing->value)->count())->toBe(1);
});

it('holds a booking link to the owner-sentence rule (D-01)', function (bool $complaint) {
    [$conversation] = bookingKindBegins();

    $reading = new CustomerReading(
        ran: true,
        failed: false,
        error: null,
        model: 'fake-jev',
        answers: null,
        reading: [
            'sendable' => ! $complaint,
            'reason_code' => $complaint ? 'classifier:complaint' : null,
            'reasons' => $complaint ? ['classifier:complaint'] : [],
            'life_safety' => false,
            'asks_for_person' => false,
            'thresholds' => [],
        ],
    );

    $send = fn () => app(OutboundSender::class)->send($conversation, 'Sure. Pick a time here: https://x.test/book/abc', MessageAuthor::Agent, SendOrigin::Automated, bookingKindLinkAttributes($reading));

    if ($complaint) {
        expect(bookingKindRefusal($send))->toStartWith(OutboundSender::NO_READING)->toContain('a booking link needs a clean reading')
            ->and(Message::query()->where('direction', MessageDirection::Out)->count())->toBe(0);
    } else {
        expect($send()->status)->toBe(MessageStatus::Sent);
    }
})->with([
    'a complaint reading holds it' => true,
    'a clean reading lets it out' => false,
]);

it('puts the booking line last (D-01)', function () {
    $parts = new ReplyParts(modelWords: 'Sure.', bookingLine: 'Pick a time here: https://x.test/book/abc');

    expect($parts->body())->toBe('Sure. Pick a time here: https://x.test/book/abc')
        ->and($parts->partNames())->toBe(['model_words', 'booking_link'])
        ->and($parts->kind())->toBe(MessageKind::AgentReply);
});
