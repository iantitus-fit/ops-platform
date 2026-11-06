<?php

use App\Actions\OpenConversationForText;
use App\Channels\FakeTextChannel;
use App\Channels\TextChannel;
use App\Enums\ActivityType;
use App\Enums\ConversationStatus;
use App\Enums\MessageKind;
use App\Enums\PausedReason;
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
