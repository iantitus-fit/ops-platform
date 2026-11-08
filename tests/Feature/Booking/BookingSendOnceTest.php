<?php

use App\Enums\ActivityType;
use App\Enums\ConversationStatus;
use App\Enums\MessageKind;
use App\Enums\MessageStatus;
use App\Jobs\SendDueBookingTexts;
use App\Models\Activity;
use App\Models\Appointment;
use App\Models\BookingSend;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use Carbon\CarbonImmutable;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Log;

/**
 * A confirmation or reminder goes out once (BOOK-04, D-31), even when the write that records it fails or the process
 * dies after the send (WR-01 in 08-REVIEW.md), and a reminder due as the send window closes survives a late sweep while
 * one the visit overtook leaves a note (WR-02). The clock is always travelled to a UTC instant, as in
 * BookingSendTest: the sender stores and compares every moment in UTC.
 */

/**
 * A moment on the example business's wall clock (America/Chicago).
 */
function bookingOnceChicago(string $dateTime): CarbonImmutable
{
    return CarbonImmutable::parse($dateTime, 'America/Chicago');
}

/**
 * The live example folder, the clock at Wednesday 2026-11-04 10:00 in the shop's zone, and Dana Whitfield, who texted
 * (the text is read) and whose open conversation already had one automated text, so the next one carries no AI
 * disclosure. Her visit is Thursday 2026-11-05 at 10:00, with its 24-hour reminder due now.
 *
 * @return array{0: Contact, 1: Conversation, 2: Appointment, 3: BookingSend}
 */
function bookingOnceSetUp(): array
{
    activateExampleFolder();
    test()->travelTo(bookingOnceChicago('2026-11-04 10:00')->utc());

    $contact = Contact::factory()->create(['name' => 'Dana Whitfield']);
    $conversation = Conversation::factory()->for($contact)->create();
    Message::factory()->for($conversation)->create(['handled_at' => now()]);
    Message::factory()->for($conversation)->outbound()->create();

    $appointment = Appointment::factory()->at(bookingOnceChicago('2026-11-05 10:00'), 90)->create(['contact_id' => $contact->id]);

    $reminder = BookingSend::factory()->for($appointment)->create([
        'lead_minutes' => 1440,
        'due_at' => bookingOnceChicago('2026-11-04 10:00')->utc(),
    ]);

    return [$contact, $conversation, $appointment, $reminder];
}

/**
 * Run the minute sweep the way the scheduler would.
 */
function bookingOnceSweep(): void
{
    app()->call([new SendDueBookingTexts, 'handle']);
}

/**
 * The timeline notes a booking text that did not go out left, as their sentences.
 *
 * @return list<string>
 */
function bookingOnceNotes(): array
{
    return Activity::query()
        ->where('type', ActivityType::RoomEntry->value)
        ->where('entry', 'booking_text_not_sent')
        ->orderBy('id')
        ->pluck('summary')
        ->all();
}

/**
 * The row claimed this many seconds ago by another sender.
 */
function bookingOnceClaimedSecondsAgo(BookingSend $row, int $seconds): void
{
    BookingSend::query()->whereKey($row->id)->update(['send_claimed_at' => now()->utc()->subSeconds($seconds)]);
}

/**
 * The outbound booking text a sender that died left behind: in the conversation, carrying the row's id, in the given state.
 *
 * @param  array<string, mixed>  $meta
 */
function bookingOnceLeftBehind(Conversation $conversation, BookingSend $row, MessageStatus $status, array $meta = []): Message
{
    return Message::factory()->for($conversation)->outbound()->create([
        'status' => $status,
        'kind' => MessageKind::BookingText,
        'body' => 'A reminder that already went out.',
        'meta' => ['booking_send_id' => $row->id, ...$meta],
    ]);
}

/**
 * Collect every log record written from now on.
 *
 * @return ArrayObject<int, MessageLogged>
 */
function bookingOnceLogRecords(): ArrayObject
{
    $records = new ArrayObject;

    Log::listen(function (MessageLogged $record) use ($records): void {
        $records[] = $record;
    });

    return $records;
}

it('never sends again when the row could not be marked sent (WR-01)', function () {
    [, , , $reminder] = bookingOnceSetUp();
    Exceptions::fake();
    $records = bookingOnceLogRecords();
    $threw = false;

    DB::beforeExecuting(function (string $query) use (&$threw): void {
        // The write that marks a row sent is the one update that sets sent_at. The claim's own update names sent_at only in
        // its where clause, so the SET clause tells the two apart.
        $sql = strtolower($query);

        if (! $threw && str_starts_with($sql, 'update') && str_contains($sql, '"booking_sends"') && str_contains($sql, 'set "sent_at"')) {
            $threw = true;

            throw new RuntimeException('the sent write failed');
        }
    });

    bookingOnceSweep();

    $first = Message::query()->where('kind', MessageKind::BookingText->value)->sole();
    $row = $reminder->fresh();
    $critical = collect($records)->filter(fn (MessageLogged $record): bool => $record->level === 'critical');
    $logged = $critical->map(fn (MessageLogged $record): string => $record->message.json_encode($record->context))->implode(' ');

    expect(textsSentToCustomers())->toHaveCount(1)
        ->and($row->send_claimed_at)->not->toBeNull()
        ->and($row->sent_at)->toBeNull()
        ->and($row->skipped_at)->toBeNull()
        ->and($row->failed_at)->toBeNull()
        ->and($critical)->toHaveCount(1)
        ->and($critical->first()->context['booking_send_id'])->toBe($reminder->id)
        ->and($critical->first()->context['message_id'])->toBe($first->id)
        ->and($logged)->not->toContain('A reminder: AC repair')
        ->and($logged)->not->toContain('Thursday, November 5');

    Exceptions::assertReported(fn (RuntimeException $exception): bool => $exception->getMessage() === 'the sent write failed');

    $this->travel(3)->minutes();
    bookingOnceSweep();

    $row = $reminder->fresh();

    expect(textsSentToCustomers())->toHaveCount(1)
        ->and($row->sent_at)->not->toBeNull()
        ->and($row->message_id)->toBe($first->id)
        ->and(bookingOnceNotes())->toBeEmpty();
});

it('ends the row on a sent message it finds instead of sending (WR-01)', function () {
    [, $conversation, , $reminder] = bookingOnceSetUp();
    bookingOnceClaimedSecondsAgo($reminder, 180);
    $left = bookingOnceLeftBehind($conversation, $reminder, MessageStatus::Sent);

    bookingOnceSweep();

    $row = $reminder->fresh();

    expect(textsSentToCustomers())->toBeEmpty()
        ->and($row->sent_at)->not->toBeNull()
        ->and($row->message_id)->toBe($left->id);
});

it('ends the row failed on a failed message it finds, without sending (WR-01)', function () {
    [, $conversation, , $reminder] = bookingOnceSetUp();
    bookingOnceClaimedSecondsAgo($reminder, 180);
    bookingOnceLeftBehind($conversation, $reminder, MessageStatus::Failed, ['error' => 'The reply was not sent: no answer']);

    bookingOnceSweep();

    $row = $reminder->fresh();

    expect(textsSentToCustomers())->toBeEmpty()
        ->and($row->failed_at)->not->toBeNull()
        ->and($row->sent_at)->toBeNull()
        ->and($row->failure)->toContain('no answer');
});

it('does not send again after a sender stopped mid-send (WR-01)', function () {
    [, $conversation, , $reminder] = bookingOnceSetUp();
    bookingOnceClaimedSecondsAgo($reminder, 180);
    bookingOnceLeftBehind($conversation, $reminder, MessageStatus::Queued);
    $records = bookingOnceLogRecords();

    bookingOnceSweep();

    $row = $reminder->fresh();
    $critical = collect($records)->filter(fn (MessageLogged $record): bool => $record->level === 'critical');

    expect(textsSentToCustomers())->toBeEmpty()
        ->and($row->failed_at)->not->toBeNull()
        ->and($row->sent_at)->toBeNull()
        ->and($row->failure)->toBe('The send stopped partway, so it was not sent again.')
        ->and($critical)->toHaveCount(1)
        ->and($critical->first()->context['booking_send_id'])->toBe($reminder->id);
});

it('finds the message in another conversation of the contact (WR-01, D-30)', function () {
    [$contact, , , $reminder] = bookingOnceSetUp();
    bookingOnceClaimedSecondsAgo($reminder, 180);
    $other = Conversation::factory()->for($contact)->create(['status' => ConversationStatus::Closed]);
    $left = bookingOnceLeftBehind($other, $reminder, MessageStatus::Delivered);

    bookingOnceSweep();

    $row = $reminder->fresh();

    expect(textsSentToCustomers())->toBeEmpty()
        ->and($row->sent_at)->not->toBeNull()
        ->and($row->message_id)->toBe($left->id);
});
