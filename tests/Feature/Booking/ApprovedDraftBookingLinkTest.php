<?php

use App\Actions\BookAppointment;
use App\Actions\MintBookingLink;
use App\Actions\ResolveDraft;
use App\Channels\FakeTextChannel;
use App\Channels\TextChannel;
use App\Enums\AlertReason;
use App\Enums\DraftResolution;
use App\Enums\MessageStatus;
use App\Icm\ActiveConfig;
use App\Icm\FolderSync;
use App\Livewire\ConversationThread;
use App\Models\Appointment;
use App\Models\BookingLink;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\IntakeAnswer;
use App\Models\Message;
use App\Models\OwnerAlert;
use App\Models\User;
use App\SendRule\CustomerReading;
use Carbon\CarbonImmutable;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\File;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

const APPROVED_DRAFT_LINK_OWNER_PHONE = '+12025550199';

const APPROVED_DRAFT_LINK_WORDS = 'We guarantee 9 am Thursday. Does that work?';

const APPROVED_DRAFT_LINK_LABEL = 'A booking link for AC repair will be added';

/**
 * Tuesday 2026-11-03 at 11:00 in the shop's zone, the owner signed in, and Dana's booking question answered by a sent
 * holding reply and a held draft labeled scheduling for ac-repair (the words promise a day, so the send rule holds them).
 * The reading of Dana's text is clean unless $reading says otherwise: 'complaint' and 'failed' script Jev, and 'none'
 * removes the stored reading from the draft, as a draft stored before any reading was made would be.
 *
 * @param  array<string, mixed>  $reply  Fields of the scripted reply that replace the defaults.
 * @param  ?Closure(Contact, Conversation): void  $beforeRun  Runs after Dana's text arrived and before the front desk answers.
 */
function approvedDraftLinkHeld(array $reply = [], ?Closure $beforeRun = null, string $reading = 'clean', string $text = 'Can you come out Thursday morning?'): Message
{
    test()->travelTo(CarbonImmutable::parse('2026-11-03 11:00', 'America/Chicago'));

    $owner = User::factory()->withPhone(APPROVED_DRAFT_LINK_OWNER_PHONE)->create();

    frontDeskCustomerTexts($text);

    match ($reading) {
        'complaint' => fakeClassifier()->answerReading('complaint', 0.95),
        'failed' => fakeClassifier()->failReadingWith('Jev is down'),
        default => null,
    };

    scriptFrontDeskReplies([...[
        'reply' => APPROVED_DRAFT_LINK_WORDS,
        'label' => 'scheduling',
        'service_slug' => 'ac-repair',
    ], ...$reply]);

    if ($beforeRun !== null) {
        $beforeRun(Contact::sole(), Conversation::sole());
    }

    runQueuedFrontDeskJobs();
    test()->actingAs($owner);

    $draft = Message::query()->where('status', MessageStatus::Draft->value)->sole();

    if ($reading === 'none') {
        $gate = $draft->gate;
        unset($gate['customer_reading']);
        $draft->forceFill(['gate' => $gate])->save();
        $draft->refresh();
    }

    $stored = CustomerReading::fromStoredIfMade($draft->gate['customer_reading'] ?? null);

    // A dirty default would silently turn these tests into the complaint cases, so the helper checks the reading it made.
    expect($stored?->allowsOwnerSentences() ?? false)->toBe($reading === 'clean');

    return $draft;
}

/**
 * The thread component for the one conversation, as the owner's browser holds it.
 */
function approvedDraftLinkThread(): Testable
{
    return Livewire::test(ConversationThread::class, ['conversation' => Conversation::query()->sole()]);
}

/**
 * The texts that reached Dana's phone, leaving out the alert to the owner.
 *
 * @return list<string>
 */
function approvedDraftLinkTextsToDana(): array
{
    return array_values(array_map(
        fn (array $text): string => $text['body'],
        array_filter(textsSentToCustomers(), fn (array $text): bool => $text['to'] !== APPROVED_DRAFT_LINK_OWNER_PHONE),
    ));
}

/**
 * The words as Dana reads them after the first automated text: the sign-off closes them, the disclosure is left out.
 */
function approvedDraftLinkLater(string $words): string
{
    return $words."\n".app(ActiveConfig::class)->voice()['sign_off'];
}

/**
 * The owner's link line with the placeholder token every real one is the length of.
 */
function approvedDraftLinkMeasuredLine(): string
{
    return 'Pick a time that works for you here: '.route('booking.show', str_repeat('0', 64));
}

/**
 * The token in the one booking address a text carries, or null.
 */
function approvedDraftLinkTokenIn(string $body): ?string
{
    return preg_match('~/book/([0-9a-f]{64})~', $body, $match) === 1 ? $match[1] : null;
}

/**
 * Change the live folder and make it live again, then hand out fresh scoped objects.
 */
function approvedDraftLinkResync(Closure $changeFolder): void
{
    $changeFolder();

    app(FolderSync::class)->run();
    app()->forgetScopedInstances();
}

/**
 * Put the room back the way the example folder has it.
 */
function approvedDraftLinkRoomOn(): void
{
    approvedDraftLinkResync(fn () => File::copyDirectory(base_path('examples/example-hvac/rooms/schedule_and_booking'), icmPath('rooms/schedule_and_booking')));
}

/**
 * An unbooked link from an earlier scheduling text in the conversation.
 */
function approvedDraftLinkEarlier(): BookingLink
{
    return app(MintBookingLink::class)->handle(Conversation::sole(), 'ac-repair', CarbonImmutable::now())[0];
}

it('adds the booking link when the owner approves a held scheduling draft (D-45)', function () {
    $draft = approvedDraftLinkHeld();

    expect($draft->body)->not->toContain('/book/');

    approvedDraftLinkThread()
        ->assertSee(APPROVED_DRAFT_LINK_LABEL)
        ->assertSet('addBookingLink', true)
        ->assertSet('bookingLinkService', 'AC repair')
        ->call('approveDraft')
        ->assertHasNoErrors()
        ->assertNotified(Notification::make()->title('Draft sent')->success());

    $text = approvedDraftLinkTextsToDana()[1];
    $token = approvedDraftLinkTokenIn($text);
    $link = BookingLink::sole();
    $sent = $draft->fresh();

    expect($token)->not->toBeNull()
        ->and($text)->toBe(approvedDraftLinkLater(APPROVED_DRAFT_LINK_WORDS.' Pick a time that works for you here: '.route('booking.show', $token)))
        ->and($sent->id)->toBe($draft->id)
        ->and($sent->resolution)->toBe(DraftResolution::Approved)
        ->and($sent->resolved_by)->toBe(User::query()->where('phone', APPROVED_DRAFT_LINK_OWNER_PHONE)->sole()->id)
        ->and($link->service_slug)->toBe('ac-repair')
        ->and($link->conversation_id)->toBe($sent->conversation_id)
        ->and($link->message_id)->toBe($draft->id)
        ->and($link->token_hash)->toBe(hash('sha256', $token))
        ->and($sent->meta['booking_link_id'])->toBe($link->id)
        ->and($sent->meta['service_slug'])->toBe('ac-repair');
});

it('adds the link after the owner\'s own words on an edited draft (D-45)', function () {
    $draft = approvedDraftLinkHeld();

    approvedDraftLinkThread()
        ->set('draftBody', 'Thursday at 9 works. See you then.')
        ->call('approveDraft')
        ->assertHasNoErrors();

    $text = approvedDraftLinkTextsToDana()[1];

    expect($text)->toBe(approvedDraftLinkLater('Thursday at 9 works. See you then. Pick a time that works for you here: '.route('booking.show', approvedDraftLinkTokenIn($text))))
        ->and($draft->fresh()->original_body)->toBe(APPROVED_DRAFT_LINK_WORDS)
        ->and($draft->fresh()->original_body)->not->toContain('/book/');
});

it('sends the draft as today with the box left off (D-45)', function () {
    $draft = approvedDraftLinkHeld();

    approvedDraftLinkThread()
        ->set('addBookingLink', false)
        ->call('approveDraft')
        ->assertNotified(Notification::make()->title('Draft sent')->success());

    expect(approvedDraftLinkTextsToDana()[1])->toBe(approvedDraftLinkLater(APPROVED_DRAFT_LINK_WORDS))
        ->and(BookingLink::query()->count())->toBe(0)
        ->and($draft->fresh()->meta)->not->toHaveKey('booking_link_left_off')
        ->and($draft->fresh()->meta)->not->toHaveKey('booking_link_id');
});

it('checks every condition at approval and says why the link was left off (D-45)', function (string $case, string $reason) {
    $draft = approvedDraftLinkHeld(
        reply: $case === 'two intake services' ? ['service_slug' => null] : [],
        beforeRun: $case === 'two intake services'
            ? fn (Contact $contact, Conversation $conversation) => IntakeAnswer::factory()->create(['conversation_id' => $conversation->id, 'icm_version_id' => app(ActiveConfig::class)->versionId(), 'service_slug' => 'ac-repair'])
            : null,
    );

    $thread = approvedDraftLinkThread()->assertSet('bookingLinkService', 'AC repair')->assertSet('addBookingLink', true);

    match ($case) {
        'room off' => approvedDraftLinkResync(fn () => File::deleteDirectory(icmPath('rooms/schedule_and_booking'))),
        'booking file deleted' => approvedDraftLinkResync(fn () => File::delete(icmPath('rooms/schedule_and_booking/booking.md'))),
        'not book_online' => approvedDraftLinkResync(fn () => breakFile('business/services/ac-repair.md', fn (string $file): string => str_replace('book_online: true', 'book_online: false', $file))),
        'service file deleted' => approvedDraftLinkResync(fn () => File::delete(icmPath('business/services/ac-repair.md'))),
        'visit booked ahead' => Appointment::factory()->for(Contact::sole())->at(CarbonImmutable::parse('2026-11-05 10:00', 'America/Chicago'))->create(),
        'two intake services' => IntakeAnswer::factory()->create(['conversation_id' => Conversation::sole()->id, 'icm_version_id' => app(ActiveConfig::class)->versionId(), 'service_slug' => 'furnace-repair']),
    };

    $thread->call('approveDraft')
        ->assertHasNoErrors()
        ->assertNotified(Notification::make()->title('Draft sent without the booking link: '.$reason)->warning());

    expect(approvedDraftLinkTextsToDana()[1])->toBe(approvedDraftLinkLater(APPROVED_DRAFT_LINK_WORDS))
        ->and(BookingLink::query()->count())->toBe(0)
        ->and($draft->fresh()->status)->toBe(MessageStatus::Sent)
        ->and($draft->fresh()->meta['booking_link_left_off'])->toBe($reason);
})->with([
    'the room turned off' => ['room off', 'online booking is turned off'],
    'the booking file deleted' => ['booking file deleted', 'online booking is turned off'],
    'the service no longer book_online' => ['not book_online', 'the service can no longer be booked online'],
    'the service file deleted' => ['service file deleted', 'the service can no longer be booked online'],
    'a visit booked ahead' => ['visit booked ahead', 'a visit is already booked'],
    'a second intake service' => ['two intake services', 'the conversation no longer points to one service'],
]);

it('adds no link the owner was not shown (D-45)', function () {
    $draft = approvedDraftLinkHeld();

    approvedDraftLinkResync(fn () => File::deleteDirectory(icmPath('rooms/schedule_and_booking')));

    $thread = approvedDraftLinkThread()->assertSet('bookingLinkService', null)->assertDontSee(APPROVED_DRAFT_LINK_LABEL);

    // The browser sets the box anyway; this round trip renders with the room still off.
    $thread->set('addBookingLink', true);

    approvedDraftLinkRoomOn();

    $thread->call('approveDraft')
        ->assertNotified(Notification::make()->title('Draft sent')->success());

    expect(approvedDraftLinkTextsToDana()[1])->toBe(approvedDraftLinkLater(APPROVED_DRAFT_LINK_WORDS))
        ->and(BookingLink::query()->count())->toBe(0)
        ->and($draft->fresh()->meta)->not->toHaveKey('booking_link_left_off');
});

it('follows the conditions while the same draft stays on screen (U-36)', function () {
    approvedDraftLinkHeld();

    $thread = approvedDraftLinkThread()->set('addBookingLink', false)->assertSet('bookingLinkService', 'AC repair');

    approvedDraftLinkResync(fn () => File::deleteDirectory(icmPath('rooms/schedule_and_booking')));
    $thread->call('$refresh')->assertSet('bookingLinkService', null)->assertDontSee(APPROVED_DRAFT_LINK_LABEL);

    approvedDraftLinkRoomOn();
    $thread->call('$refresh')->assertSet('bookingLinkService', 'AC repair')->assertSee(APPROVED_DRAFT_LINK_LABEL)->assertSet('addBookingLink', false);
});

it('never adds a link to a draft labeled anything else, even when the browser sets the box (T-08-69)', function () {
    $draft = approvedDraftLinkHeld(['reply' => 'Mike will text you a price.', 'label' => 'price'], text: 'How much for a new unit?');

    approvedDraftLinkThread()
        ->assertSet('bookingLinkService', null)
        ->assertDontSee('A booking link for')
        ->set('addBookingLink', true)
        ->call('approveDraft')
        ->assertNotified(Notification::make()->title('Draft sent')->success());

    expect(approvedDraftLinkTextsToDana()[1])->toBe(approvedDraftLinkLater('Mike will text you a price.'))
        ->and(BookingLink::query()->count())->toBe(0)
        ->and($draft->fresh()->meta)->not->toHaveKey('booking_link_id');
});

it('sends only the words and sign-off when the flag is set on a price draft called directly (T-08-69)', function () {
    $draft = approvedDraftLinkHeld(['reply' => 'Mike will text you a price.', 'label' => 'price'], text: 'How much for a new unit?');

    app(ResolveDraft::class)->approve($draft, User::query()->where('phone', APPROVED_DRAFT_LINK_OWNER_PHONE)->sole(), null, true);

    expect(approvedDraftLinkTextsToDana()[1])->toBe(approvedDraftLinkLater('Mike will text you a price.'))
        ->and(BookingLink::query()->count())->toBe(0)
        ->and($draft->fresh()->meta)->not->toHaveKey('booking_link_id')
        ->and($draft->fresh()->meta)->not->toHaveKey('booking_link_left_off');
});

it('starts the box unchecked when the reading is not clean, and sends no link as drawn (D-45)', function (string $reading) {
    $draft = approvedDraftLinkHeld(reading: $reading);

    approvedDraftLinkThread()
        ->assertSee(APPROVED_DRAFT_LINK_LABEL)
        ->assertSet('addBookingLink', false)
        ->call('approveDraft')
        ->assertNotified(Notification::make()->title('Draft sent')->success());

    expect(approvedDraftLinkTextsToDana()[array_key_last(approvedDraftLinkTextsToDana())])->toBe(approvedDraftLinkLater(APPROVED_DRAFT_LINK_WORDS))
        ->and(BookingLink::query()->count())->toBe(0)
        ->and($draft->fresh()->meta)->not->toHaveKey('booking_link_left_off');
})->with(['a complaint' => ['complaint'], 'a failed reading' => ['failed'], 'no stored reading' => ['none']]);

it('sends the link when the owner checks a box that started unchecked: the owner\'s approval decides (D-45)', function (string $reading) {
    $draft = approvedDraftLinkHeld(reading: $reading);

    approvedDraftLinkThread()
        ->set('addBookingLink', true)
        ->call('approveDraft')
        ->assertHasNoErrors()
        ->assertNotified(Notification::make()->title('Draft sent')->success());

    $text = approvedDraftLinkTextsToDana()[array_key_last(approvedDraftLinkTextsToDana())];

    expect($text)->toBe(approvedDraftLinkLater(APPROVED_DRAFT_LINK_WORDS.' Pick a time that works for you here: '.route('booking.show', approvedDraftLinkTokenIn($text))))
        ->and(BookingLink::sole()->message_id)->toBe($draft->id)
        ->and($draft->fresh()->status)->toBe(MessageStatus::Sent);
})->with(['a complaint' => ['complaint'], 'a failed reading' => ['failed'], 'no stored reading' => ['none']]);

it('mints nothing when the approval is refused (D-45)', function () {
    approvedDraftLinkHeld();

    $thread = approvedDraftLinkThread();
    $before = count(approvedDraftLinkTextsToDana());

    Contact::sole()->forceFill(['sms_opted_out_at' => now()])->save();

    $thread->call('approveDraft')->assertHasErrors('draftBody');

    expect(BookingLink::query()->count())->toBe(0)
        ->and(approvedDraftLinkTextsToDana())->toHaveCount($before);
});

it('revokes the link and gives back the earlier one when the text channel fails (D-12, D-45)', function () {
    $draft = approvedDraftLinkHeld();
    $earlier = approvedDraftLinkEarlier();

    $this->app->bind(TextChannel::class, fn () => new class extends FakeTextChannel
    {
        public function send(string $toE164, string $body): string
        {
            throw new RuntimeException('Carrier rejected the number');
        }
    });

    approvedDraftLinkThread()
        ->call('approveDraft')
        ->assertNotified(Notification::make()->title('The reply was not sent: Carrier rejected the number')->danger());

    $minted = BookingLink::query()->where('id', '!=', $earlier->id)->sole();

    expect($draft->fresh()->status)->toBe(MessageStatus::Failed)
        ->and($draft->fresh()->meta['booking_link_id'])->toBe($minted->id)
        ->and($minted->revoked_at)->not->toBeNull()
        ->and($earlier->fresh()->superseded_at)->toBeNull();
});

it('mints nothing when a newer draft replaced the one on screen (D-28, D-45)', function () {
    approvedDraftLinkHeld();

    $thread = approvedDraftLinkThread();

    test()->travel(1)->minutes();
    test()->post(route('webhooks.text.fake'), fakeTextPayload(['MessageSid' => 'SM'.str_repeat('e', 32), 'Body' => 'Actually Friday is better']))->assertOk();
    scriptFrontDeskReplies(['reply' => 'We guarantee 9 am Friday. Does that work?', 'label' => 'scheduling', 'service_slug' => 'ac-repair', 'touches_waiting' => true]);
    runQueuedFrontDeskJobs();

    $thread->call('approveDraft');

    expect(BookingLink::query()->whereNull('revoked_at')->count())->toBe(0)
        ->and(approvedDraftLinkTextsToDana())->toHaveCount(2);
});

it('refuses Send when the link would pass 1,600 characters, and sends at exactly 1,600 (U-36)', function () {
    approvedDraftLinkHeld();

    $overhead = mb_strlen(approvedDraftLinkLater(' '.approvedDraftLinkMeasuredLine()));
    $tooLong = str_repeat('a', 1601 - $overhead);

    $thread = approvedDraftLinkThread()->set('draftBody', $tooLong)->call('approveDraft');

    expect($thread->errors()->first('draftBody'))->toBe('With the booking link this text is over 1,600 characters. Shorten it, or leave the link off.')
        ->and(BookingLink::query()->count())->toBe(0)
        ->and(approvedDraftLinkTextsToDana())->toHaveCount(1);

    $thread->assertSet('draftBody', $tooLong)
        ->set('addBookingLink', false)
        ->call('approveDraft');

    expect(approvedDraftLinkTextsToDana())->toHaveCount(2)
        ->and(BookingLink::query()->count())->toBe(0);
});

it('sends with the link when the composed text is exactly 1,600 characters (U-36)', function () {
    approvedDraftLinkHeld();

    $overhead = mb_strlen(approvedDraftLinkLater(' '.approvedDraftLinkMeasuredLine()));

    approvedDraftLinkThread()
        ->set('draftBody', str_repeat('a', 1600 - $overhead))
        ->call('approveDraft')
        ->assertHasNoErrors();

    expect(mb_strlen(approvedDraftLinkTextsToDana()[1]))->toBe(1600)
        ->and(BookingLink::query()->count())->toBe(1);
});

it('supersedes an earlier unbooked link (D-12)', function () {
    approvedDraftLinkHeld();
    $earlier = approvedDraftLinkEarlier();

    approvedDraftLinkThread()->call('approveDraft')->assertHasNoErrors();

    expect(BookingLink::query()->count())->toBe(2)
        ->and($earlier->fresh()->superseded_at)->not->toBeNull()
        ->and(BookingLink::query()->where('id', '!=', $earlier->id)->sole()->superseded_at)->toBeNull();
});

it('resets the box to the starting state of a draft that replaces the one on screen (U-36)', function (string $firstReading, bool $secondIsComplaint, bool $ownerSet, bool $expected) {
    approvedDraftLinkHeld(reading: $firstReading);

    $thread = approvedDraftLinkThread()->set('addBookingLink', $ownerSet);

    if ($secondIsComplaint) {
        fakeClassifier()->answerReading('complaint', 0.95);
    }

    test()->travel(1)->minutes();
    test()->post(route('webhooks.text.fake'), fakeTextPayload(['MessageSid' => 'SM'.str_repeat('d', 32), 'Body' => 'Actually the furnace is the problem']))->assertOk();
    scriptFrontDeskReplies(['reply' => 'We guarantee 9 am Friday for the furnace. Does that work?', 'label' => 'scheduling', 'service_slug' => 'furnace-repair', 'touches_waiting' => true]);
    runQueuedFrontDeskJobs();

    $thread->call('$refresh')
        ->assertSet('bookingLinkService', 'Furnace repair')
        ->assertSet('addBookingLink', $expected);
})->with([
    'unticked on a clean draft, replaced by a clean one: checked again' => ['clean', false, false, true],
    'ticked on a clean draft, replaced by a complaint read: unchecked' => ['clean', true, true, false],
]);

it('raises the booked alert on the sent draft row\'s key when the customer books through the link (D-33)', function () {
    $draft = approvedDraftLinkHeld();

    approvedDraftLinkThread()->call('approveDraft')->assertHasNoErrors();

    app(BookAppointment::class)->fromLink(BookingLink::sole(), CarbonImmutable::parse('2026-11-05 10:00', 'America/Chicago'), CarbonImmutable::now());

    $alert = OwnerAlert::query()->where('reason', AlertReason::AppointmentBooked->value)->sole();

    expect($alert->message_id)->toBe($draft->id)
        ->and($alert->dedupe_key)->toContain(':m'.$draft->id.':');
});
