<?php

use App\Actions\BookAppointment;
use App\Actions\ComposeAutomatedText;
use App\Actions\MintBookingLink;
use App\Actions\OutboundSender;
use App\Actions\SendCombinedReply;
use App\Ai\AgentFile;
use App\Ai\Agents\FrontDeskAgent;
use App\Ai\FrontDeskPrompt;
use App\Channels\FakeTextChannel;
use App\Enums\AlertReason;
use App\Enums\AppointmentState;
use App\Enums\MessageStatus;
use App\Events\CustomerRecordChanged;
use App\Icm\ActiveConfig;
use App\Models\Appointment;
use App\Models\BookingLink;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\IntakeAnswer;
use App\Models\Message;
use App\Models\OwnerAlert;
use App\Models\User;
use App\SendRule\CustomerReading;
use App\SendRule\ReplyParts;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\File;

/**
 * Dana asks to book AC repair on Tuesday 2026-11-03 at 11:00 in the shop's zone, and the front desk's scripted reply is
 * labeled scheduling for ac-repair. The queued job runs, so the reply is sent or held by the real send path.
 *
 * @param  array<string, mixed>  $reply  Fields of the scripted reply that replace the scheduling defaults; the words may be a closure, run once the folder is live.
 * @param  ?Closure(): void  $beforeTexts  Runs before the folder is activated, to change the owner's files.
 * @param  ?Closure(Contact, Conversation): void  $beforeRun  Runs after Dana's text arrived and before the job runs.
 */
function bookingLinkRunsScheduling(array $reply = [], ?Closure $beforeTexts = null, ?Closure $beforeRun = null, string $text = 'Can someone come look at my AC this week?'): void
{
    test()->travelTo(CarbonImmutable::parse('2026-11-03 11:00', 'America/Chicago'));
    User::factory()->withPhone('+12025550199')->create();

    if ($beforeTexts !== null) {
        $beforeTexts();
    }

    frontDeskCustomerTexts($text);

    $reply = [...[
        'reply' => 'We can get a tech out this week.',
        'label' => 'scheduling',
        'service_slug' => 'ac-repair',
    ], ...$reply];

    if ($reply['reply'] instanceof Closure) {
        $reply['reply'] = ($reply['reply'])();
    }

    scriptFrontDeskReplies($reply);

    if ($beforeRun !== null) {
        $beforeRun(Contact::sole(), Conversation::sole());
    }

    runQueuedFrontDeskJobs();
}

/**
 * Dana texts again a minute later and the front desk answers with the scripted reply.
 *
 * @param  array<string, mixed>  $reply
 */
function bookingLinkCustomerTextsAgain(string $body, array $reply): void
{
    static $count = 0;
    $count++;

    test()->travel(1)->minutes();
    test()->post(route('webhooks.text.fake'), fakeTextPayload(['MessageSid' => 'SM'.str_pad((string) $count, 32, 'b', STR_PAD_LEFT), 'Body' => $body]))->assertOk();
    scriptFrontDeskReplies($reply);
    runQueuedFrontDeskJobs();
}

/**
 * The texts that reached Dana's phone, leaving out alerts to the owner.
 *
 * @return list<string>
 */
function bookingLinkTextsToDana(): array
{
    return array_values(array_map(
        fn (array $text): string => $text['body'],
        array_filter(textsSentToCustomers(), fn (array $text): bool => $text['to'] === '+12025550123'),
    ));
}

/**
 * The texts to Dana that hold the given words.
 *
 * @return list<string>
 */
function bookingLinkTextsContaining(string $words): array
{
    return array_values(array_filter(bookingLinkTextsToDana(), fn (string $body): bool => str_contains($body, $words)));
}

/**
 * A booked visit for the contact on Thursday 2026-11-05 at 10:00 in the shop's zone.
 */
function bookingLinkVisitAhead(Contact $contact): Appointment
{
    return Appointment::factory()->for($contact)->at(CarbonImmutable::parse('2026-11-05 10:00', 'America/Chicago'))->create();
}

/**
 * An unbooked link from an earlier scheduling text in the conversation.
 */
function bookingLinkEarlier(Conversation $conversation): BookingLink
{
    return app(MintBookingLink::class)->handle($conversation, 'ac-repair', CarbonImmutable::now())[0];
}

/**
 * The owner's link line as it would read, with the placeholder token every real one is the length of.
 */
function bookingLinkMeasuredLine(): string
{
    return 'Pick a time that works for you here: '.route('booking.show', str_repeat('0', 64));
}

/**
 * Words that make the first automated text, with the owner's link line after them, exactly $length characters long.
 */
function bookingLinkWordsMakingText(int $length): string
{
    return str_repeat('a', $length - mb_strlen(composedFirstReply(' '.bookingLinkMeasuredLine())));
}

/**
 * A SendCombinedReply whose send fails with the given exception, so a minted link has a text that never went out.
 */
function bookingLinkSendFailsWith(Throwable $exception): void
{
    app()->bind(SendCombinedReply::class, fn () => new class(app(ComposeAutomatedText::class), app(OutboundSender::class), $exception) extends SendCombinedReply
    {
        public function __construct(ComposeAutomatedText $compose, OutboundSender $sender, private readonly Throwable $failure)
        {
            parent::__construct($compose, $sender);
        }

        public function handle(Conversation $conversation, ReplyParts $parts, CustomerReading $reading, array $attributes = []): Message
        {
            throw $this->failure;
        }
    });
}

/**
 * A MintBookingLink whose recording of the carrying message fails, so the link went out in a text the platform could not
 * tie back to the link (WR-03).
 */
function bookingLinkAttachFails(): void
{
    app()->bind(MintBookingLink::class, fn () => new class(app(ActiveConfig::class)) extends MintBookingLink
    {
        public function attachMessage(BookingLink $link, Message $message): void
        {
            throw new RuntimeException('could not record the carrying message');
        }
    });
}

/**
 * The token in the one booking address the text carries, or null when it carries none.
 */
function bookingLinkTokenIn(string $body): ?string
{
    return preg_match('~/book/([0-9a-f]{64})~', $body, $match) === 1 ? $match[1] : null;
}

it('sends the scheduling reply with the owner\'s link line (D-01)', function () {
    bookingLinkRunsScheduling();

    $token = bookingLinkTokenIn(textsSentToCustomers()[0]['body'] ?? '');

    expect(textsSentToCustomers())->toHaveCount(1)
        ->and($token)->not->toBeNull()
        ->and(BookingLink::query()->count())->toBe(1);

    $link = BookingLink::sole();
    $sent = Message::query()->where('meta->booking_link_id', $link->id)->sole();

    expect($token)->not->toBeNull()
        ->and(textsSentToCustomers()[0]['body'])->toBe(composedFirstReply('We can get a tech out this week. Pick a time that works for you here: '.route('booking.show', $token)))
        ->and($link->service_slug)->toBe('ac-repair')
        ->and($link->conversation_id)->toBe($sent->conversation_id)
        ->and($link->message_id)->toBe($sent->id)
        ->and($link->token_hash)->toBe(hash('sha256', $token))
        ->and($sent->meta['parts'])->toBe(['model_words', 'booking_link']);
});

it('opens the page from the link in the text', function () {
    bookingLinkRunsScheduling();

    $token = bookingLinkTokenIn(textsSentToCustomers()[0]['body'] ?? '');

    expect($token)->not->toBeNull();

    $this->get(route('booking.show', $token))
        ->assertOk()
        ->assertSee('Book AC repair');
});

it('adds no link to a reply labeled anything but scheduling (D-01, D-05)', function (string $label) {
    bookingLinkRunsScheduling(['label' => $label, 'reply' => 'We service AC units.']);

    expect(textsSentToCustomers())->toHaveCount(1)
        ->and(textsSentToCustomers()[0]['body'])->toBe(composedFirstReply('We service AC units.'))
        ->and(BookingLink::query()->count())->toBe(0);
})->with(['faq_answer', 'intake_question']);

it('adds no link and changes nothing else when the room, the file or the service is not there (D-01, D-03)', function (string $case) {
    bookingLinkRunsScheduling(
        beforeTexts: match ($case) {
            'room off' => fn () => File::deleteDirectory(icmPath('rooms/schedule_and_booking')),
            'no booking file' => fn () => File::delete(icmPath('rooms/schedule_and_booking/booking.md')),
            'not book_online' => fn () => null,
            'service missing' => fn () => File::delete(icmPath('business/services/ac-repair.md')),
        },
        reply: $case === 'not book_online' ? ['service_slug' => 'system-replacement'] : [],
    );

    expect(textsSentToCustomers())->toHaveCount(1)
        ->and(textsSentToCustomers()[0]['body'])->toBe(composedFirstReply('We can get a tech out this week.'))
        ->and(BookingLink::query()->count())->toBe(0);
})->with(['room off', 'no booking file', 'not book_online', 'service missing']);

it('adds no link while a visit is booked ahead, and adds one after it is cancelled (D-01)', function () {
    bookingLinkRunsScheduling(beforeRun: fn (Contact $contact) => bookingLinkVisitAhead($contact));

    expect(bookingLinkTextsToDana())->toHaveCount(1)
        ->and(bookingLinkTextsToDana()[0])->toBe(composedFirstReply('We can get a tech out this week.'))
        ->and(BookingLink::query()->count())->toBe(0);

    Appointment::sole()->forceFill(['state' => AppointmentState::Cancelled])->save();

    bookingLinkCustomerTextsAgain('Can you come another day?', ['reply' => 'Yes, we can come another day.', 'label' => 'scheduling', 'service_slug' => 'ac-repair']);

    expect(BookingLink::query()->count())->toBe(1)
        ->and(bookingLinkTextsToDana()[1])->toContain('Yes, we can come another day. Pick a time that works for you here: ');
});

it('books the one service the intake answers point to when the reply names none (D-02)', function () {
    bookingLinkRunsScheduling(
        reply: ['service_slug' => null],
        beforeRun: fn (Contact $contact, Conversation $conversation) => IntakeAnswer::factory()->create(['conversation_id' => $conversation->id, 'icm_version_id' => app(ActiveConfig::class)->versionId(), 'service_slug' => 'ac-repair']),
    );

    expect(BookingLink::sole()->service_slug)->toBe('ac-repair')
        ->and(bookingLinkTokenIn(textsSentToCustomers()[0]['body']))->not->toBeNull();
});

it('adds no link when the reply names no service and the intake answers point to two (D-02)', function () {
    bookingLinkRunsScheduling(
        reply: ['service_slug' => null],
        beforeRun: function (Contact $contact, Conversation $conversation): void {
            foreach (['ac-repair', 'furnace-repair'] as $slug) {
                IntakeAnswer::factory()->create(['conversation_id' => $conversation->id, 'icm_version_id' => app(ActiveConfig::class)->versionId(), 'service_slug' => $slug]);
            }
        },
    );

    expect(textsSentToCustomers())->toHaveCount(1)
        ->and(textsSentToCustomers()[0]['body'])->toBe(composedFirstReply('We can get a tech out this week.'))
        ->and(BookingLink::query()->count())->toBe(0);
});

/**
 * Postgres refuses SELECT DISTINCT with an ORDER BY on a column the query does not select (42P10), and SQLite allows it,
 * so the intake answers' own id order must not ride along on the distinct service query.
 */
it('asks the intake answers for their services without an order Postgres refuses beside distinct (D-02)', function () {
    $intakeQueries = [];
    DB::listen(function (QueryExecuted $query) use (&$intakeQueries): void {
        if (str_contains($query->sql, 'from "intake_answers"') && str_contains($query->sql, 'distinct')) {
            $intakeQueries[] = $query->sql;
        }
    });

    bookingLinkRunsScheduling(
        reply: ['service_slug' => null],
        beforeRun: fn (Contact $contact, Conversation $conversation) => IntakeAnswer::factory()->create(['conversation_id' => $conversation->id, 'icm_version_id' => app(ActiveConfig::class)->versionId(), 'service_slug' => 'ac-repair']),
    );

    expect($intakeQueries)->not->toBeEmpty()
        ->and(collect($intakeQueries)->filter(fn (string $sql): bool => str_contains($sql, 'order by'))->all())->toBe([])
        ->and(BookingLink::sole()->service_slug)->toBe('ac-repair');
});

it('adds no link when the reply names no service and nothing points to one (D-02)', function () {
    bookingLinkRunsScheduling(['service_slug' => null]);

    expect(textsSentToCustomers()[0]['body'])->toBe(composedFirstReply('We can get a tech out this week.'))
        ->and(BookingLink::query()->count())->toBe(0);
});

it('revokes the link when the send is refused, and gives back the earlier one (D-12)', function () {
    $earlier = null;

    bookingLinkRunsScheduling(beforeRun: function (Contact $contact, Conversation $conversation) use (&$earlier): void {
        $earlier = bookingLinkEarlier($conversation);
        bookingLinkSendFailsWith(new DomainException('The customer opted out'));
    });

    $minted = BookingLink::query()->where('id', '!=', $earlier->id)->get();

    expect(bookingLinkTextsContaining('We can get a tech out this week.'))->toBeEmpty()
        ->and($minted)->toHaveCount(1)
        ->and($minted->first()->revoked_at)->not->toBeNull()
        ->and($earlier->fresh()->superseded_at)->toBeNull();
});

it('revokes the link when anything else fails, and gives back the earlier one (D-12)', function () {
    $earlier = null;

    try {
        bookingLinkRunsScheduling(beforeRun: function (Contact $contact, Conversation $conversation) use (&$earlier): void {
            $earlier = bookingLinkEarlier($conversation);
            bookingLinkSendFailsWith(new RuntimeException('The database went away'));
        });
    } catch (RuntimeException) {
        // The run throws as it does today; ProcessInbound reports it.
    }

    $minted = BookingLink::query()->where('id', '!=', $earlier->id)->get();

    expect(bookingLinkTextsContaining('We can get a tech out this week.'))->toBeEmpty()
        ->and($minted)->toHaveCount(1)
        ->and($minted->first()->revoked_at)->not->toBeNull()
        ->and($earlier->fresh()->superseded_at)->toBeNull();
});

it('keeps a delivered link live when recording its message fails (WR-03)', function () {
    $earlier = null;
    Exceptions::fake();

    bookingLinkRunsScheduling(beforeRun: function (Contact $contact, Conversation $conversation) use (&$earlier): void {
        $earlier = bookingLinkEarlier($conversation);
        bookingLinkAttachFails();
    });

    $text = bookingLinkTextsContaining('We can get a tech out this week.');
    $token = bookingLinkTokenIn($text[0] ?? '');
    $link = BookingLink::query()->where('id', '!=', $earlier->id)->sole();
    $carrying = Message::query()->where('meta->booking_link_id', $link->id)->sole();

    expect($text)->toHaveCount(1)
        ->and($token)->not->toBeNull()
        ->and($link->token_hash)->toBe(hash('sha256', $token))
        ->and($link->fresh()->revoked_at)->toBeNull()
        ->and($earlier->fresh()->superseded_at)->not->toBeNull()
        ->and($carrying->status)->toBe(MessageStatus::Sent)
        ->and(OwnerAlert::query()->where('reason', AlertReason::FrontDeskFailed->value)->count())->toBe(0)
        ->and(Message::query()->where('status', MessageStatus::Draft->value)->count())->toBe(0);

    Exceptions::assertReported(fn (RuntimeException $exception): bool => $exception->getMessage() === 'could not record the carrying message');

    app(BookAppointment::class)->fromLink($link->fresh(), CarbonImmutable::parse('2026-11-05 10:00', 'America/Chicago'), CarbonImmutable::now());

    $alert = OwnerAlert::query()->where('reason', AlertReason::AppointmentBooked->value)->sole();

    expect(Appointment::query()->count())->toBe(1)
        ->and($alert->message_id)->toBe($carrying->id);
});

it('keeps the link when the card redraw after the send fails (WR-03)', function () {
    Exceptions::fake();
    $failed = false;

    bookingLinkRunsScheduling(beforeRun: function () use (&$failed): void {
        Event::listen(CustomerRecordChanged::class, function () use (&$failed): void {
            $toDana = array_filter(app(FakeTextChannel::class)->sent(), fn (array $text): bool => $text['to'] === '+12025550123');

            if (! $failed && $toDana !== []) {
                $failed = true;

                throw new RuntimeException('queue down');
            }
        });
    });

    $link = BookingLink::sole();
    $carrying = Message::query()->where('meta->booking_link_id', $link->id)->sole();

    expect($failed)->toBeTrue()
        ->and(bookingLinkTokenIn(bookingLinkTextsContaining('We can get a tech out this week.')[0] ?? ''))->not->toBeNull()
        ->and($link->revoked_at)->toBeNull()
        ->and($carrying->status)->toBe(MessageStatus::Sent)
        ->and($link->message_id)->toBe($carrying->id);

    Exceptions::assertReported(fn (RuntimeException $exception): bool => $exception->getMessage() === 'queue down');
});

it('keeps a delivered link live when releasing the send lock fails after the send (WR-08)', function () {
    Exceptions::fake();

    bookingLinkRunsScheduling(beforeRun: fn () => replyLockReleaseFails());

    $link = BookingLink::sole();
    $carrying = Message::query()->where('meta->booking_link_id', $link->id)->sole();

    expect(bookingLinkTokenIn(bookingLinkTextsContaining('We can get a tech out this week.')[0] ?? ''))->not->toBeNull()
        ->and($link->revoked_at)->toBeNull()
        ->and($carrying->status)->toBe(MessageStatus::Sent)
        ->and($link->message_id)->toBe($carrying->id)
        ->and(OwnerAlert::query()->where('reason', AlertReason::FrontDeskFailed->value)->count())->toBe(0)
        ->and(Message::query()->where('status', MessageStatus::Draft->value)->count())->toBe(0);

    Exceptions::assertReported(fn (RuntimeException $exception): bool => $exception->getMessage() === 'the lock release failed');
});

it('gives the model no booking address when an earlier text carried a link (WR-07)', function () {
    bookingLinkRunsScheduling();
    bookingLinkCustomerTextsAgain('Thanks. Should I clear a path to the unit?', ['reply' => 'Yes, a clear path helps the tech.', 'label' => 'faq_answer']);

    $history = null;

    FrontDeskAgent::assertPrompted(function ($prompt) use (&$history): bool {
        if ($prompt->prompt !== 'Thanks. Should I clear a path to the unit?') {
            return false;
        }

        $history = implode("\n", array_map(fn ($turn): string => $turn->content, iterator_to_array($prompt->agent->messages())));

        return true;
    });

    expect(Message::query()->whereNotNull('meta->booking_link_id')->sole()->body)->toContain('/book/[link]')
        ->and($history)->toContain('We can get a tech out this week. (The system added the booking link here.)')
        ->and(stripos($history, '/book/'))->toBeFalse()
        ->and($history)->not->toContain('Pick a time that works for you here');
});

it('supersedes the first link with the second, each with its own text (D-12)', function () {
    bookingLinkRunsScheduling();
    bookingLinkCustomerTextsAgain('Can you come Friday instead?', ['reply' => 'Friday works too.', 'label' => 'scheduling', 'service_slug' => 'ac-repair']);

    [$first, $second] = BookingLink::query()->orderBy('id')->get()->all();

    expect(BookingLink::query()->count())->toBe(2)
        ->and($first->superseded_at)->not->toBeNull()
        ->and($second->superseded_at)->toBeNull()
        ->and($first->message_id)->not->toBe($second->message_id)
        ->and(Message::query()->where('meta->booking_link_id', $second->id)->sole()->id)->toBe($second->message_id);
});

it('drops the link at the length limit and keeps the earlier one, and keeps it at exactly 1,600 characters (D-12)', function () {
    $earlier = null;

    bookingLinkRunsScheduling(
        reply: ['reply' => fn (): string => bookingLinkWordsMakingText(1601)],
        beforeRun: function (Contact $contact, Conversation $conversation) use (&$earlier): void {
            $earlier = bookingLinkEarlier($conversation);
        },
    );

    expect(textsSentToCustomers())->toHaveCount(1)
        ->and(textsSentToCustomers()[0]['body'])->toBe(composedFirstReply(bookingLinkWordsMakingText(1601)))
        ->and(BookingLink::query()->count())->toBe(1)
        ->and($earlier->fresh()->superseded_at)->toBeNull();
});

it('keeps the link when the text with it is exactly 1,600 characters (D-12)', function () {
    bookingLinkRunsScheduling(['reply' => fn (): string => bookingLinkWordsMakingText(1600)]);

    expect(mb_strlen(textsSentToCustomers()[0]['body']))->toBe(1600)
        ->and(BookingLink::query()->count())->toBe(1);
});

it('adds the link to a scheduling reply sent while a price draft waits, and the waiting draft keeps no URL (D-01)', function () {
    test()->travelTo(CarbonImmutable::parse('2026-11-03 11:00', 'America/Chicago'));
    User::factory()->withPhone('+12025550199')->create();
    frontDeskCustomerTexts('How much for a new unit?');
    scriptFrontDeskReplies(['reply' => 'Mike will text you a price for a new unit.', 'label' => 'price']);
    runQueuedFrontDeskJobs();

    $waiting = Message::query()->where('status', MessageStatus::Draft->value)->sole();

    bookingLinkCustomerTextsAgain('Can you come out Thursday morning?', [
        'reply' => 'We have 9 am Thursday open.',
        'label' => 'scheduling',
        'service_slug' => 'ac-repair',
        'touches_waiting' => false,
    ]);

    expect(BookingLink::sole()->service_slug)->toBe('ac-repair')
        ->and(bookingLinkTextsToDana()[1])->toContain('We have 9 am Thursday open. Pick a time that works for you here: ')
        ->and(Message::query()->where('status', MessageStatus::Draft->value)->sole()->id)->toBe($waiting->id)
        ->and($waiting->fresh()->body)->not->toContain('/book/');
});

it('never puts the link in a held draft, and mints none (D-45)', function () {
    bookingLinkRunsScheduling(['reply' => 'We guarantee 9 am Thursday. Does that work?'], text: 'Can you come out Thursday morning?');

    $draft = Message::query()->where('status', MessageStatus::Draft->value)->sole();

    expect($draft->body)->toBe('We guarantee 9 am Thursday. Does that work?')
        ->and($draft->body)->not->toContain('/book/')
        ->and(BookingLink::query()->count())->toBe(0);
});

it('records the service on a held scheduling draft and on no other draft (D-45)', function () {
    bookingLinkRunsScheduling(['reply' => 'We guarantee 9 am Thursday. Does that work?'], text: 'Can you come out Thursday morning?');

    expect(Message::query()->where('status', MessageStatus::Draft->value)->sole()->meta['service_slug'] ?? null)->toBe('ac-repair');
});

it('records the service on the draft that replaces a held scheduling draft (D-45)', function () {
    bookingLinkRunsScheduling(['reply' => 'We guarantee 9 am Thursday. Does that work?'], text: 'Can you come out Thursday morning?');

    $first = Message::query()->where('status', MessageStatus::Draft->value)->sole();

    bookingLinkCustomerTextsAgain('Actually Friday is better', [
        'reply' => 'We guarantee 9 am Friday. Does that work?',
        'label' => 'scheduling',
        'service_slug' => 'furnace-repair',
        'touches_waiting' => true,
    ]);

    $live = Message::query()->where('status', MessageStatus::Draft->value)->sole();

    expect($live->id)->not->toBe($first->id)
        ->and($live->meta['service_slug'] ?? null)->toBe('furnace-repair');
});

it('keeps the waiting draft\'s service on a draft carried over from it (D-45)', function () {
    bookingLinkRunsScheduling(['reply' => 'We guarantee 9 am Thursday. Does that work?'], text: 'Can you come out Thursday morning?');

    $first = Message::query()->where('status', MessageStatus::Draft->value)->sole();
    fakeClassifier()->answer('faq', 0.95, ['promise' => 0.9]);

    bookingLinkCustomerTextsAgain('Any news?', [
        'reply' => 'Mike still has your question.',
        'label' => 'faq_answer',
        'service_slug' => 'furnace-repair',
        'waiting_note' => 'check_in',
        'touches_waiting' => true,
    ]);

    $live = Message::query()->where('status', MessageStatus::Draft->value)->sole();

    expect($live->id)->not->toBe($first->id)
        ->and($live->label->value)->toBe('scheduling')
        ->and($live->meta['service_slug'] ?? null)->toBe('ac-repair');
});

it('records no service on a held scheduling draft whose reply named none, nor on a held price draft (D-45)', function () {
    bookingLinkRunsScheduling(['reply' => 'We guarantee 9 am Thursday. Does that work?', 'service_slug' => null], text: 'Can you come out Thursday morning?');

    expect(Message::query()->where('status', MessageStatus::Draft->value)->sole()->meta)->not->toHaveKey('service_slug');

    bookingLinkCustomerTextsAgain('How much is a new unit?', ['reply' => 'Mike will text you a price.', 'label' => 'price', 'service_slug' => 'ac-repair', 'touches_waiting' => true]);

    expect(Message::query()->where('status', MessageStatus::Draft->value)->sole()->meta)->not->toHaveKey('service_slug');
});

it('tells the front desk, in the owner\'s own words, to offer a visit once intake is done (D-05)', function () {
    activateExampleFolder();

    $line = 'Once the last intake question is answered, it offers to book a visit.';
    $config = app(ActiveConfig::class);
    $contact = Contact::factory()->create(['name' => 'Dana Whitfield', 'phone_e164' => '+12025550123']);

    expect($config->agent('sms')['body'])->toContain($line)
        ->and(app(FrontDeskPrompt::class)->instructions(AgentFile::forChannel($config, 'sms'), $contact))->toContain($line);
});
