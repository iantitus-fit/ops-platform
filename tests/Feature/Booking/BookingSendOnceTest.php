<?php

use App\Actions\OutboundSender;
use App\Actions\RaiseOwnerAlert;
use App\Channels\TextChannel;
use App\Enums\ActivityType;
use App\Enums\ConversationStatus;
use App\Enums\MessageAuthor;
use App\Enums\MessageKind;
use App\Enums\MessageStatus;
use App\Enums\SendOrigin;
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

it('notes a text whose sender stopped partway, on the timeline the owner reads (WR-06, D-27)', function (int $leadMinutes, string $note) {
    [, $conversation, , $row] = bookingOnceSetUp();
    BookingSend::query()->whereKey($row->id)->update(['lead_minutes' => $leadMinutes]);
    bookingOnceClaimedSecondsAgo($row, 180);
    bookingOnceLeftBehind($conversation, $row, MessageStatus::Queued);

    bookingOnceSweep();

    expect(textsSentToCustomers())->toBeEmpty()
        ->and($row->fresh()->failed_at)->not->toBeNull()
        ->and(bookingOnceNotes())->toBe([$note])
        ->and(OwnerAlert::query()->count())->toBe(0);

    test()->travel(3)->minutes();
    bookingOnceSweep();

    expect(bookingOnceNotes())->toBe([$note]);
})->with([
    'a reminder' => [1440, 'Reminder text may not have reached the customer: the send stopped partway.'],
    'a confirmation' => [0, 'Confirmation text may not have reached the customer: the send stopped partway.'],
]);

it('ends the row on the text that went out when the sender throws after the send (WR-06)', function () {
    [, , , $reminder] = bookingOnceSetUp();
    Exceptions::fake();

    app()->bind(OutboundSender::class, fn () => new class(app(TextChannel::class), app(ActiveConfig::class), app(RaiseOwnerAlert::class)) extends OutboundSender
    {
        public function send(Conversation $conversation, string $body, MessageAuthor $author, SendOrigin $origin, array $attributes = []): Message
        {
            parent::send($conversation, $body, $author, $origin, $attributes);

            throw new RuntimeException('a step after the send failed');
        }
    });

    bookingOnceSweep();

    $sent = Message::query()->where('kind', MessageKind::BookingText->value)->sole();
    $row = $reminder->fresh();

    expect(textsSentToCustomers())->toHaveCount(1)
        ->and($sent->status)->toBe(MessageStatus::Sent)
        ->and($row->sent_at)->not->toBeNull()
        ->and($row->failed_at)->toBeNull()
        ->and($row->message_id)->toBe($sent->id)
        ->and(bookingOnceNotes())->toBeEmpty();

    Exceptions::assertReported(fn (RuntimeException $exception): bool => $exception->getMessage() === 'a step after the send failed');
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

/**
 * The row due at one wall time, with the clock at another, both on Wednesday 2026-11-04 in the shop's zone.
 */
function bookingOnceDueAtReachedAt(BookingSend $row, string $dueTime, string $reachedTime): void
{
    BookingSend::query()->whereKey($row->id)->update(['due_at' => bookingOnceChicago('2026-11-04 '.$dueTime)->utc()]);
    test()->travelTo(bookingOnceChicago('2026-11-04 '.$reachedTime)->utc());
}

it('sends a row due at the close when the sweep is a few minutes late (WR-02)', function (string $dueTime, string $reachedTime, bool $sends) {
    [, , , $reminder] = bookingOnceSetUp();
    bookingOnceDueAtReachedAt($reminder, $dueTime, $reachedTime);

    bookingOnceSweep();

    $row = $reminder->fresh();

    expect(textsSentToCustomers())->toHaveCount($sends ? 1 : 0)
        ->and($row->sent_at !== null)->toBe($sends)
        ->and($row->send_claimed_at !== null)->toBe($sends);

    if (! $sends) {
        expect($row->due_at->format('Y-m-d H:i'))->toBe('2026-11-05 14:00');
    }
})->with([
    'due at the close, reached on time' => ['20:00', '20:00', true],
    'due at the close, reached 3 minutes late' => ['20:00', '20:03', true],
    'due at the close, reached 5 minutes late' => ['20:00', '20:05', true],
    'due at the close, reached 6 minutes late' => ['20:00', '20:06', false],
    'due just before the close, reached 6 minutes after it' => ['19:58', '20:04', true],
    'due after the close, reached inside the grace' => ['20:02', '20:04', false],
]);

it('still moves a row that came due after the close (D-29)', function () {
    [, , , $reminder] = bookingOnceSetUp();
    bookingOnceDueAtReachedAt($reminder, '21:00', '21:00');

    bookingOnceSweep();

    $row = $reminder->fresh();

    expect(textsSentToCustomers())->toBeEmpty()
        ->and($row->sent_at)->toBeNull()
        ->and($row->send_claimed_at)->toBeNull()
        ->and($row->due_at->format('Y-m-d H:i'))->toBe('2026-11-05 14:00');
});

/**
 * Dana's visit moved to Thursday 2026-11-05 at 07:00 in the shop's zone, with the row due Wednesday 20:00 and reached at
 * 20:30: the sweep holds it for the window's next opening, Thursday 08:00, an hour after the visit began.
 */
function bookingOnceOvertakenVisit(BookingSend $row, Appointment $appointment, int $leadMinutes = 1440): void
{
    $starts = bookingOnceChicago('2026-11-05 07:00')->utc();

    Appointment::query()->whereKey($appointment->id)->update(['starts_at' => $starts, 'ends_at' => $starts->addMinutes(90)]);
    BookingSend::query()->whereKey($row->id)->update(['lead_minutes' => $leadMinutes]);
    bookingOnceDueAtReachedAt($row, '20:00', '20:30');

    bookingOnceSweep();

    expect(textsSentToCustomers())->toBeEmpty()
        ->and($row->fresh()->due_at->format('Y-m-d H:i'))->toBe('2026-11-05 14:00');

    test()->travelTo(bookingOnceChicago('2026-11-05 08:00')->utc());
}

it('notes a reminder the visit overtook (WR-02, D-27)', function () {
    [, , $appointment, $reminder] = bookingOnceSetUp();
    bookingOnceOvertakenVisit($reminder, $appointment);

    bookingOnceSweep();

    expect(textsSentToCustomers())->toBeEmpty()
        ->and($reminder->fresh()->skipped_at)->not->toBeNull()
        ->and(bookingOnceNotes())->toBe(['Reminder text not sent: the visit started first.'])
        ->and(OwnerAlert::query()->count())->toBe(0);
});

it('notes a confirmation the visit overtook (WR-02)', function () {
    [, , $appointment, $confirmation] = bookingOnceSetUp();
    bookingOnceOvertakenVisit($confirmation, $appointment, 0);

    bookingOnceSweep();

    expect(textsSentToCustomers())->toBeEmpty()
        ->and($confirmation->fresh()->skipped_at)->not->toBeNull()
        ->and(bookingOnceNotes())->toBe(['Confirmation text not sent: the visit started first.'])
        ->and(OwnerAlert::query()->count())->toBe(0);
});

it('notes a visit marked done before its moved reminder came (WR-02)', function () {
    [, , $appointment, $reminder] = bookingOnceSetUp();
    bookingOnceOvertakenVisit($reminder, $appointment);
    Appointment::query()->whereKey($appointment->id)->update(['state' => 'done']);

    bookingOnceSweep();

    expect(textsSentToCustomers())->toBeEmpty()
        ->and(bookingOnceNotes())->toBe(['Reminder text not sent: the visit started first.']);
});

it('writes no note for a cancelled visit, even one that would have started (WR-02, D-16)', function () {
    [, , $appointment, $reminder] = bookingOnceSetUp();
    bookingOnceOvertakenVisit($reminder, $appointment);
    Appointment::query()->whereKey($appointment->id)->update(['state' => 'cancelled']);

    bookingOnceSweep();

    expect(textsSentToCustomers())->toBeEmpty()
        ->and($reminder->fresh()->skipped_at)->not->toBeNull()
        ->and(bookingOnceNotes())->toBeEmpty()
        ->and(OwnerAlert::query()->count())->toBe(0);
});

it('keeps the unread reason for a row that waited on an unread customer text (WR-02, D-26)', function () {
    [, , $appointment, $reminder] = bookingOnceSetUp();
    bookingOnceOvertakenVisit($reminder, $appointment);
    BookingSend::query()->whereKey($reminder->id)->update(['waited_on_unread_at' => now()->utc()->subHour()]);

    bookingOnceSweep();

    expect(textsSentToCustomers())->toBeEmpty()
        ->and(bookingOnceNotes())->toBe(['Reminder text not sent: an earlier text from the customer was still waiting to be read.']);
});
