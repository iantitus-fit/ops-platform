<?php

use App\Enums\ActivityType;
use App\Enums\MessageKind;
use App\Icm\ActiveConfig;
use App\Jobs\SendDueBookingTexts;
use App\Models\Activity;
use App\Models\Appointment;
use App\Models\BookingSend;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\OwnerAlert;
use Carbon\CarbonImmutable;

/**
 * The confirmation and reminder texts (BOOK-04, D-26 to D-31, A1). The clock is always travelled to a UTC instant: the
 * sender stores and compares every moment in UTC, because Eloquent's datetime cast keeps an instance's own wall time.
 */

/**
 * A moment on the example business's wall clock (America/Chicago).
 */
function bookingSendChicago(string $dateTime): CarbonImmutable
{
    return CarbonImmutable::parse($dateTime, 'America/Chicago');
}

/**
 * The live example folder, the clock at Wednesday 2026-11-04 10:00 in the shop's zone, and Dana Whitfield, who texted
 * (the text is read) and whose open conversation already had one automated text, so the next one carries no AI
 * disclosure. Her visit is Thursday 2026-11-05 at 10:00, with its 24-hour reminder due now. The rows are written
 * directly, so no other text goes out beside the one a test is about.
 *
 * @return array{0: Contact, 1: Conversation, 2: Appointment, 3: BookingSend}
 */
function bookingSendSetUp(?Closure $changeFolder = null): array
{
    if ($changeFolder !== null) {
        $changeFolder();
    }

    activateExampleFolder();
    test()->travelTo(bookingSendChicago('2026-11-04 10:00')->utc());

    $contact = Contact::factory()->create(['name' => 'Dana Whitfield']);
    $conversation = Conversation::factory()->for($contact)->create();
    Message::factory()->for($conversation)->create(['handled_at' => now()]);
    Message::factory()->for($conversation)->outbound()->create();

    $appointment = Appointment::factory()->at(bookingSendChicago('2026-11-05 10:00'), 90)->create(['contact_id' => $contact->id]);

    $reminder = BookingSend::factory()->for($appointment)->create([
        'lead_minutes' => 1440,
        'due_at' => bookingSendChicago('2026-11-04 10:00')->utc(),
    ]);

    return [$contact, $conversation, $appointment, $reminder];
}

/**
 * The voice file's sign-off, which closes every automated text.
 */
function bookingSendSignOff(): string
{
    return app(ActiveConfig::class)->voice()['sign_off'];
}

/**
 * Run the minute sweep the way the scheduler would.
 */
function bookingSendSweep(): void
{
    app()->call([new SendDueBookingTexts, 'handle']);
}

it('sends a due reminder from the sweep', function () {
    [, , $appointment, $reminder] = bookingSendSetUp();

    bookingSendSweep();

    $message = Message::query()->where('kind', MessageKind::BookingText->value)->sole();
    $row = $reminder->fresh();

    expect(textsSentToCustomers())->toHaveCount(1)
        ->and(textsSentToCustomers()[0]['body'])->toBe(
            "A reminder: AC repair is on Thursday, November 5 at 10:00 AM. To change it, reply to this text.\n".bookingSendSignOff(),
        )
        ->and($message->meta['appointment_id'])->toBe($appointment->id)
        ->and($message->meta['parts'])->toBe(['booking_line'])
        ->and($row->sent_at)->not->toBeNull()
        ->and($row->message_id)->toBe($message->id)
        ->and(Activity::query()->where('type', ActivityType::ReplySent->value)->where('summary', 'like', 'Reminder%')->sole()->summary)
        ->toBe('Reminder text sent, 24 hours before: AC repair, Thursday, November 5 at 10:00 AM.')
        ->and(OwnerAlert::query()->count())->toBe(0);
});
