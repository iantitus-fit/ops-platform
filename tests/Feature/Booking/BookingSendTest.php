<?php

use App\Actions\BookAppointment;
use App\Actions\MintBookingLink;
use App\Actions\OpenConversationForText;
use App\Actions\SendBookingText;
use App\Channels\FakeTextChannel;
use App\Channels\TextChannel;
use App\Enums\ActivityType;
use App\Enums\ConversationStatus;
use App\Enums\MessageKind;
use App\Enums\PausedReason;
use App\Enums\SyncOutcome;
use App\Icm\ActiveConfig;
use App\Icm\FolderSync;
use App\Jobs\SendDueBookingTexts;
use App\Models\Activity;
use App\Models\Appointment;
use App\Models\BookingSend;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\OwnerAlert;
use App\Models\Project;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\File;

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

/**
 * The timeline notes a refused booking text left, as their sentences.
 *
 * @return list<string>
 */
function bookingSendNotes(): array
{
    return Activity::query()
        ->where('type', ActivityType::RoomEntry->value)
        ->where('entry', 'booking_text_not_sent')
        ->orderBy('id')
        ->pluck('summary')
        ->all();
}

/**
 * Mark every customer text in the conversation as read.
 */
function bookingSendReadEverything(): void
{
    Message::query()->where('direction', 'in')->update(['handled_at' => now()->utc()]);
}

it('notes and skips a refusal', function (string $case, string $sentence) {
    [$contact, $conversation, , $reminder] = bookingSendSetUp();

    match ($case) {
        'opted out' => Contact::query()->whereKey($contact->id)->update(['sms_opted_out_at' => now()->utc()]),
        'not agreed' => Message::query()->where('direction', 'in')->delete(),
        'paused' => Conversation::query()->whereKey($conversation->id)->update([
            'paused_at' => now()->utc(),
            'paused_reason' => PausedReason::TakenOver->value,
        ]),
    };

    bookingSendSweep();

    $row = $reminder->fresh();

    expect(textsSentToCustomers())->toBeEmpty()
        ->and($row->skipped_at)->not->toBeNull()
        ->and($row->sent_at)->toBeNull()
        ->and($row->failed_at)->toBeNull()
        ->and(bookingSendNotes())->toBe([$sentence])
        ->and(OwnerAlert::query()->count())->toBe(0)
        ->and(Conversation::query()->where('contact_id', $contact->id)->count())->toBe(1);
})->with([
    'opted out' => ['opted out', 'Reminder text not sent: the customer has opted out.'],
    'not agreed' => ['not agreed', 'Reminder text not sent: the customer has not agreed to automatic texts.'],
    'paused' => ['paused', 'Reminder text not sent: the conversation is paused.'],
]);

it('waits while a customer text is unread, then sends (D-26)', function () {
    [, $conversation, , $reminder] = bookingSendSetUp();
    Message::factory()->for($conversation)->create();

    bookingSendSweep();

    $row = $reminder->fresh();

    expect(textsSentToCustomers())->toBeEmpty()
        ->and($row->sent_at)->toBeNull()
        ->and($row->skipped_at)->toBeNull()
        ->and($row->failed_at)->toBeNull()
        ->and($row->send_claimed_at)->toBeNull()
        ->and($row->waited_on_unread_at)->not->toBeNull()
        ->and(bookingSendNotes())->toBeEmpty();

    bookingSendReadEverything();
    bookingSendSweep();

    expect(textsSentToCustomers())->toHaveCount(1)
        ->and($reminder->fresh()->sent_at)->not->toBeNull();
});

it('drops it with a note when the visit is cancelled while waiting (D-26, D-27)', function () {
    [, $conversation, $appointment, $reminder] = bookingSendSetUp();
    Message::factory()->for($conversation)->create();

    bookingSendSweep();

    Appointment::query()->whereKey($appointment->id)->update(['state' => 'cancelled']);

    bookingSendSweep();

    expect(textsSentToCustomers())->toBeEmpty()
        ->and($reminder->fresh()->skipped_at)->not->toBeNull()
        ->and(bookingSendNotes())->toBe(['Reminder text not sent: an earlier text from the customer was still waiting to be read.'])
        ->and(OwnerAlert::query()->count())->toBe(0);
});

it('ends a cancelled visit\'s rows without a note', function () {
    [, , $appointment, $reminder] = bookingSendSetUp();

    Appointment::query()->whereKey($appointment->id)->update(['state' => 'cancelled']);

    bookingSendSweep();

    expect(textsSentToCustomers())->toBeEmpty()
        ->and($reminder->fresh()->skipped_at)->not->toBeNull()
        ->and(bookingSendNotes())->toBeEmpty()
        ->and(OwnerAlert::query()->count())->toBe(0);
});

it('opens a conversation when none is open (D-30)', function () {
    [$contact, $conversation, , $reminder] = bookingSendSetUp();

    Conversation::query()->whereKey($conversation->id)->update(['status' => 'closed', 'closed_at' => now()->utc()]);
    $projects = Project::query()->count();

    bookingSendSweep();

    $open = Conversation::query()->where('contact_id', $contact->id)->where('status', ConversationStatus::Open->value)->get();

    expect($reminder->fresh()->sent_at)->not->toBeNull()
        ->and($open)->toHaveCount(1)
        ->and($open->first()->id)->not->toBe($conversation->id)
        ->and($open->first()->messages()->count())->toBe(1)
        ->and(textsSentToCustomers()[0]['body'])->toStartWith(app(ActiveConfig::class)->voice()['ai_disclosure'])
        ->and(Project::query()->count())->toBe($projects)
        ->and($contact->fresh()->status)->toBe($contact->status);
});

it('keeps one open conversation when two sends race (Pitfall 14)', function () {
    [$contact, $conversation] = bookingSendSetUp();

    Conversation::query()->whereKey($conversation->id)->update(['status' => 'closed', 'closed_at' => now()->utc()]);
    $raced = false;
    $rival = null;

    DB::listen(function (QueryExecuted $query) use (&$raced, &$rival, $contact): void {
        if ($raced || ! str_starts_with(strtolower($query->sql), 'select') || ! str_contains($query->sql, 'from "conversations"')) {
            return;
        }

        $raced = true;

        $rival = DB::table('conversations')->insertGetId([
            'contact_id' => $contact->id,
            'status' => 'open',
            'created_at' => now()->utc(),
            'updated_at' => now()->utc(),
        ]);
    });

    $opened = app(OpenConversationForText::class)->handle($contact);

    expect($raced)->toBeTrue()
        ->and($opened->id)->toBe($rival)
        ->and(Conversation::query()->where('contact_id', $contact->id)->where('status', 'open')->count())->toBe(1);
});

it('sends the confirmation line for lead 0', function () {
    [, , $appointment] = bookingSendSetUp();

    $confirmation = BookingSend::factory()->confirmation()->for($appointment)->create();

    bookingSendSweep();

    expect(collect(textsSentToCustomers())->pluck('body')->all())->toContain(
        "You are booked for AC repair on Thursday, November 5 at 10:00 AM. To change it, reply to this text.\n".bookingSendSignOff(),
    )
        ->and($confirmation->fresh()->sent_at)->not->toBeNull()
        ->and(Activity::query()->where('summary', 'Confirmation text sent for AC repair, Thursday, November 5 at 10:00 AM.')->count())->toBe(1);
});

it('fails a channel failure without a second note', function () {
    [, , , $reminder] = bookingSendSetUp();

    app()->instance(TextChannel::class, new class extends FakeTextChannel
    {
        public function send(string $toE164, string $body): string
        {
            throw new RuntimeException('Carrier down');
        }
    });

    Exceptions::fake();

    bookingSendSweep();

    $row = $reminder->fresh();

    expect($row->failed_at)->not->toBeNull()
        ->and($row->failure)->toContain('Carrier down')
        ->and($row->sent_at)->toBeNull()
        ->and(bookingSendNotes())->toBeEmpty()
        ->and(Activity::query()->where('type', ActivityType::Note->value)->pluck('summary')->all())
        ->toBe(['Reply not sent: Carrier down'])
        ->and(OwnerAlert::query()->count())->toBe(0);
});

it('keeps the example\'s texts inside the length limit (A1)', function () {
    [$contact, , $appointment] = bookingSendSetUp();

    Message::query()->where('direction', 'out')->delete();
    $confirmation = BookingSend::factory()->confirmation()->for($appointment)->create();

    bookingSendSweep();

    $texts = collect(textsSentToCustomers())->pluck('body');

    expect($texts)->toHaveCount(2)
        ->and($texts->every(fn (string $body): bool => mb_strlen($body) <= 1600))->toBeTrue()
        ->and($texts[0])->toStartWith(app(ActiveConfig::class)->voice()['ai_disclosure'])
        ->and($confirmation->fresh()->sent_at)->not->toBeNull();
});

it('notes an over-long text and alerts no one (A1, D-27)', function () {
    [, , , $reminder] = bookingSendSetUp(function () {
        breakFile('rooms/schedule_and_booking/booking.md', fn (string $file): string => preg_replace(
            '/^reminder_line: .*$/m',
            'reminder_line: "A reminder: {service} is on {day} at {time}. '.str_repeat('Please keep the way to the door clear. ', 6).'"',
            $file,
        ));
        breakFile('voice/how-we-talk.md', fn (string $file): string => preg_replace(
            '/^ai_disclosure: .*$/m',
            'ai_disclosure: "'.str_repeat('This is the virtual assistant for Example Heating and Air. ', 24).'"',
            $file,
        ));
    });

    Message::query()->where('direction', 'out')->delete();

    bookingSendSweep();

    expect(textsSentToCustomers())->toBeEmpty()
        ->and($reminder->fresh()->skipped_at)->not->toBeNull()
        ->and(bookingSendNotes())->toBe(['Reminder text not sent: the text is longer than 1,600 characters.'])
        ->and(OwnerAlert::query()->count())->toBe(0);
});

it('notes a text with no phone number to send to', function () {
    [$contact, , , $reminder] = bookingSendSetUp();

    Contact::query()->whereKey($contact->id)->update(['phone_e164' => null]);

    bookingSendSweep();

    expect(textsSentToCustomers())->toBeEmpty()
        ->and($reminder->fresh()->skipped_at)->not->toBeNull()
        ->and(bookingSendNotes())->toBe(['Reminder text not sent: the customer has no phone number on file.']);
});

it('ends a row with a note when the booking file is gone', function () {
    [, , , $reminder] = bookingSendSetUp();

    File::delete(icmPath('rooms/schedule_and_booking/booking.md'));
    app(FolderSync::class)->run();

    bookingSendSweep();

    expect(textsSentToCustomers())->toBeEmpty()
        ->and($reminder->fresh()->skipped_at)->not->toBeNull()
        ->and(bookingSendNotes())->toBe(['Reminder text not sent: the booking file is not in the business folder.']);
});

/**
 * Dana's visit text rows made again on a fresh row, claimed this many seconds ago by another sender.
 */
function bookingSendClaimedSecondsAgo(BookingSend $row, int $seconds): void
{
    BookingSend::query()->whereKey($row->id)->update(['send_claimed_at' => now()->utc()->subSeconds($seconds)]);
}

/**
 * The texts the fake channel sent to the customer's own number.
 *
 * @return list<string>
 */
function bookingSendTextsTo(Contact $contact): array
{
    return collect(textsSentToCustomers())->where('to', $contact->phone_e164)->pluck('body')->values()->all();
}

/**
 * Dana, who texted and has read everything, with the clock on the given wall time. No booking row is written; the booking
 * itself writes them. Her only conversation already had an automated text, so no AI disclosure rides along.
 *
 * @return array{0: Contact, 1: Conversation}
 */
function bookingSendCustomerAt(string $wallTime): array
{
    activateExampleFolder();
    test()->travelTo(bookingSendChicago($wallTime)->utc());

    $contact = Contact::factory()->create(['name' => 'Dana Whitfield']);
    $conversation = Conversation::factory()->for($contact)->create();
    Message::factory()->for($conversation)->create(['handled_at' => now()]);
    Message::factory()->for($conversation)->outbound()->create();

    return [$contact, $conversation];
}

it('sends a row once when two senders race', function (int $secondsAgo, bool $sends) {
    [, , , $reminder] = bookingSendSetUp();

    bookingSendClaimedSecondsAgo($reminder, $secondsAgo);

    app(SendBookingText::class)->handle(BookingSend::query()->find($reminder->id));

    expect(textsSentToCustomers())->toHaveCount($sends ? 1 : 0)
        ->and($reminder->fresh()->sent_at !== null)->toBe($sends);
})->with([
    'claimed a minute ago' => [60, false],
    'claimed 1 minute 59 seconds ago' => [119, false],
    'claimed exactly 2 minutes ago' => [120, true],
]);

it('texts once when two stale copies of one row are both sent', function () {
    [, , , $reminder] = bookingSendSetUp();
    $first = BookingSend::query()->find($reminder->id);
    $second = BookingSend::query()->find($reminder->id);

    app(SendBookingText::class)->handle($first);
    app(SendBookingText::class)->handle($second);

    expect(textsSentToCustomers())->toHaveCount(1);
});

it('sends one text when the sweep enters while the first sender is mid-send', function () {
    [, , , $reminder] = bookingSendSetUp();

    $channel = new class extends FakeTextChannel
    {
        public ?Closure $whileSending = null;

        public function send(string $toE164, string $body): string
        {
            $hook = $this->whileSending;
            $this->whileSending = null;

            if ($hook !== null) {
                $hook();
            }

            return parent::send($toE164, $body);
        }
    };

    $channel->whileSending = fn () => app(SendBookingText::class)->handle(BookingSend::query()->find($reminder->id));

    app()->instance(TextChannel::class, $channel);

    app(SendBookingText::class)->handle($reminder);

    expect($channel->sent())->toHaveCount(1)
        ->and($reminder->fresh()->sent_at)->not->toBeNull();
});

it('checks again before resending a released row (D-31)', function () {
    [, , $appointment, $reminder] = bookingSendSetUp();

    bookingSendClaimedSecondsAgo($reminder, 180);
    Appointment::query()->whereKey($appointment->id)->update(['state' => 'cancelled']);

    bookingSendSweep();

    expect(textsSentToCustomers())->toBeEmpty()
        ->and($reminder->fresh()->skipped_at)->not->toBeNull()
        ->and(bookingSendNotes())->toBeEmpty();
});

it('holds a row outside the send window and moves its due time (D-29)', function (string $now, string $movedTo, string $opening) {
    [, , , $reminder] = bookingSendSetUp();
    $this->travelTo(bookingSendChicago($now)->utc());
    BookingSend::query()->whereKey($reminder->id)->update(['due_at' => now()->utc()]);

    bookingSendSweep();

    $row = $reminder->fresh();

    expect(textsSentToCustomers())->toBeEmpty()
        ->and($row->sent_at)->toBeNull()
        ->and($row->skipped_at)->toBeNull()
        ->and($row->send_claimed_at)->toBeNull()
        ->and($row->due_at->format('Y-m-d H:i'))->toBe($movedTo);

    $this->travelTo(bookingSendChicago($opening)->utc());
    bookingSendSweep();

    expect(textsSentToCustomers())->toHaveCount(1)
        ->and($reminder->fresh()->sent_at)->not->toBeNull();
})->with([
    'before the window opens' => ['2026-11-04 07:00', '2026-11-04 14:00', '2026-11-04 08:00'],
    'after the window closes' => ['2026-11-04 21:00', '2026-11-05 14:00', '2026-11-05 08:00'],
]);

it('sends a row due exactly when the window closes (D-29)', function () {
    [, , , $reminder] = bookingSendSetUp();
    $this->travelTo(bookingSendChicago('2026-11-04 20:00')->utc());
    BookingSend::query()->whereKey($reminder->id)->update(['due_at' => now()->utc()]);

    bookingSendSweep();

    expect(textsSentToCustomers())->toHaveCount(1);
});

it('sends a row that ignores the window at any hour (D-28)', function () {
    [, , $appointment] = bookingSendSetUp();
    $this->travelTo(bookingSendChicago('2026-11-04 22:00')->utc());

    BookingSend::factory()->confirmation()->for($appointment)->create(['ignores_window' => true]);
    BookingSend::query()->where('lead_minutes', 1440)->delete();

    bookingSendSweep();

    expect(textsSentToCustomers())->toHaveCount(1);
});

it('confirms a page booking at once whatever the hour (D-28)', function () {
    [$contact, $conversation] = bookingSendCustomerAt('2026-11-03 22:00');

    [$link] = app(MintBookingLink::class)->handle($conversation, 'ac-repair', CarbonImmutable::now());
    $carrier = Message::factory()->for($conversation)->outbound()->create([
        'body' => 'Here is the link to book.',
        'meta' => ['booking_link_id' => $link->id],
    ]);
    app(MintBookingLink::class)->attachMessage($link, $carrier);

    $appointment = app(BookAppointment::class)->fromLink($link->fresh(), bookingSendChicago('2026-11-05 10:00'), CarbonImmutable::now());

    $confirmation = $appointment->sends()->where('lead_minutes', 0)->sole();

    expect(bookingSendTextsTo($contact))->toHaveCount(1)
        ->and(bookingSendTextsTo($contact)[0])->toStartWith('You are booked for AC repair on Thursday, November 5 at 10:00 AM.')
        ->and($confirmation->sent_at)->not->toBeNull();
});

it('confirms a hand booking at once inside the window and later outside it (D-28)', function () {
    [$contact] = bookingSendCustomerAt('2026-11-03 11:00');
    $owner = User::factory()->create();

    $inside = app(BookAppointment::class)->byHand($contact, $owner, 'ac-repair', bookingSendChicago('2026-11-05 10:00'), null, null);

    expect(bookingSendTextsTo($contact))->toHaveCount(1)
        ->and($inside->sends()->where('lead_minutes', 0)->sole()->sent_at)->not->toBeNull();

    $this->travelTo(bookingSendChicago('2026-11-03 21:00')->utc());

    $outside = app(BookAppointment::class)->byHand($contact, $owner, 'ac-repair', bookingSendChicago('2026-11-06 10:00'), null, null);

    expect(bookingSendTextsTo($contact))->toHaveCount(1)
        ->and($outside->sends()->where('lead_minutes', 0)->sole()->sent_at)->toBeNull();

    $this->travelTo(bookingSendChicago('2026-11-04 08:00')->utc());
    bookingSendSweep();

    expect(bookingSendTextsTo($contact))->toHaveCount(2)
        ->and(bookingSendTextsTo($contact)[1])->toStartWith('You are booked for AC repair on Friday, November 6 at 10:00 AM.')
        ->and($outside->sends()->where('lead_minutes', 0)->sole()->sent_at)->not->toBeNull();
});

it('never undoes a booking when the immediate send throws', function () {
    [$contact] = bookingSendCustomerAt('2026-11-03 11:00');
    $owner = User::factory()->create();
    $real = app(SendBookingText::class);

    app()->bind(SendBookingText::class, fn () => new class extends SendBookingText
    {
        public function __construct() {}

        public function handle(BookingSend $send): void
        {
            throw new RuntimeException('the sender is down');
        }
    });

    Exceptions::fake();

    $appointment = app(BookAppointment::class)->byHand($contact, $owner, 'ac-repair', bookingSendChicago('2026-11-05 10:00'), null, null);

    Exceptions::assertReported(fn (RuntimeException $exception): bool => $exception->getMessage() === 'the sender is down');

    $confirmation = $appointment->sends()->where('lead_minutes', 0)->sole();

    expect(Appointment::query()->whereKey($appointment->id)->exists())->toBeTrue()
        ->and($confirmation->sent_at)->toBeNull();

    $real->handle($confirmation);

    expect(bookingSendTextsTo($contact))->toHaveCount(1)
        ->and($confirmation->fresh()->sent_at)->not->toBeNull();
});

it('does nothing while the room is off', function () {
    [, , $appointment, $reminder] = bookingSendSetUp();

    Appointment::query()->whereKey($appointment->id)->update([
        'starts_at' => now()->utc()->subHour(),
        'ends_at' => now()->utc()->subMinutes(30),
    ]);

    File::deleteDirectory(icmPath('rooms/schedule_and_booking'));
    expect(app(FolderSync::class)->run()->outcome)->toBe(SyncOutcome::Activated)
        ->and(app(ActiveConfig::class)->hasRoom('schedule_and_booking'))->toBeFalse();

    bookingSendSweep();

    $row = $reminder->fresh();

    expect(textsSentToCustomers())->toBeEmpty()
        ->and($row->send_claimed_at)->toBeNull()
        ->and($row->sent_at)->toBeNull()
        ->and($row->skipped_at)->toBeNull()
        ->and($row->failed_at)->toBeNull();
});

it('never sends a cancelled visit\'s pending rows', function () {
    [, , $appointment, $reminder] = bookingSendSetUp();
    $confirmation = BookingSend::factory()->confirmation()->for($appointment)->create();

    Appointment::query()->whereKey($appointment->id)->update(['state' => 'cancelled']);

    bookingSendSweep();

    expect(textsSentToCustomers())->toBeEmpty()
        ->and($reminder->fresh()->skipped_at)->not->toBeNull()
        ->and($confirmation->fresh()->skipped_at)->not->toBeNull()
        ->and(bookingSendNotes())->toBeEmpty()
        ->and(OwnerAlert::query()->count())->toBe(0);
});
