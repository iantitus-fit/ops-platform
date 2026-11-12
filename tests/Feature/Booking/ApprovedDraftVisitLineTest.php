<?php

use App\Actions\ResolveDraft;
use App\Enums\AppointmentState;
use App\Enums\DraftResolution;
use App\Enums\MessageStatus;
use App\Icm\ActiveConfig;
use App\Icm\FolderSync;
use App\Livewire\ConversationThread;
use App\Models\Appointment;
use App\Models\BookingLink;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\IcmVersion;
use App\Models\Message;
use App\Models\User;
use App\SendRule\CustomerReading;
use Carbon\CarbonImmutable;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\File;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * D-39 as amended settles the visit line on a held draft the way D-45 settles the booking link line: the approve step
 * shows the owner the exact line in a box, the owner's approval decides, a checked box adds the line after the words at
 * send time and never into the draft, and every condition is checked again at approval. The send stays the owner's own
 * (OutboundSender::sendExisting() on the owner origin); nothing is minted and no agent acts.
 */
const APPROVED_VISIT_LINE_OWNER_PHONE = '+12025550199';

const APPROVED_VISIT_LINE_WORDS = 'We guarantee 9 am Thursday. Does that work?';

const APPROVED_VISIT_LINE_TEXT = 'Your visit for AC repair is on Thursday, November 5 at 10:00 AM. To change it, reply to this text.';

const APPROVED_VISIT_LINE_LABEL = 'The visit line will be added: '.APPROVED_VISIT_LINE_TEXT;

/**
 * Tuesday 2026-11-03 at 11:00 in the shop's zone, the owner signed in, Dana's AC repair visit booked for Thursday at
 * 10:00 when $booked, and Dana's question answered by a held draft (the words promise a day, so the send rule holds
 * them). The reading of Dana's text is clean, with the intent $intent, unless $reading says otherwise: 'complaint' and
 * 'failed' script Jev, and 'none' removes the stored reading from the draft, as one stored before any reading was made.
 *
 * @param  array<string, mixed>  $reply  Fields of the scripted reply that replace the defaults.
 */
function approvedVisitLineHeld(array $reply = [], string $reading = 'clean', bool $booked = true, string $text = 'Can you come out Thursday morning?', string $intent = 'scheduling'): Message
{
    test()->travelTo(CarbonImmutable::parse('2026-11-03 11:00', 'America/Chicago'));

    $owner = User::factory()->withPhone(APPROVED_VISIT_LINE_OWNER_PHONE)->create();

    frontDeskCustomerTexts($text);

    if ($booked) {
        Appointment::factory()->for(Contact::sole())->at(CarbonImmutable::parse('2026-11-05 10:00', 'America/Chicago'))->create();
    }

    match ($reading) {
        'complaint' => fakeClassifier()->answerReading('complaint', 0.95),
        'failed' => fakeClassifier()->failReadingWith('Jev is down'),
        'none' => null,
        default => fakeClassifier()->answerReading($intent),
    };

    scriptFrontDeskReplies([...[
        'reply' => APPROVED_VISIT_LINE_WORDS,
        'label' => 'scheduling',
        'service_slug' => 'ac-repair',
    ], ...$reply]);

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
function approvedVisitLineThread(): Testable
{
    return Livewire::test(ConversationThread::class, ['conversation' => Conversation::query()->sole()]);
}

/**
 * The texts that reached Dana's phone, leaving out the alert to the owner.
 *
 * @return list<string>
 */
function approvedVisitLineTextsToDana(): array
{
    return array_values(array_map(
        fn (array $text): string => $text['body'],
        array_filter(textsSentToCustomers(), fn (array $text): bool => $text['to'] !== APPROVED_VISIT_LINE_OWNER_PHONE),
    ));
}

/**
 * The words as Dana reads them after the first automated text: the sign-off closes them, the disclosure is left out.
 */
function approvedVisitLineLater(string $words): string
{
    return $words."\n".app(ActiveConfig::class)->voice()['sign_off'];
}

it('adds the visit line when the owner approves a booked customer\'s held draft (D-39 amended, D-45)', function () {
    $draft = approvedVisitLineHeld();

    approvedVisitLineThread()
        ->assertSet('visitLine', APPROVED_VISIT_LINE_TEXT)
        ->assertSet('addVisitLine', true)
        ->assertSet('bookingLinkService', null)
        ->assertSee(APPROVED_VISIT_LINE_LABEL)
        ->assertDontSee('A booking link for')
        ->call('approveDraft')
        ->assertHasNoErrors()
        ->assertNotified(Notification::make()->title('Draft sent')->success());

    $sent = $draft->fresh();

    expect(approvedVisitLineTextsToDana()[1])->toBe(approvedVisitLineLater(APPROVED_VISIT_LINE_WORDS.' '.APPROVED_VISIT_LINE_TEXT))
        ->and($sent->status)->toBe(MessageStatus::Sent)
        ->and($sent->resolution)->toBe(DraftResolution::Approved)
        ->and($sent->resolved_by)->toBe(User::query()->where('phone', APPROVED_VISIT_LINE_OWNER_PHONE)->sole()->id)
        ->and($sent->meta['visit_line_appointment_id'])->toBe(Appointment::sole()->id)
        ->and($sent->meta)->not->toHaveKey('visit_line_left_off')
        ->and(BookingLink::query()->count())->toBe(0)
        ->and($sent->original_body)->toBeNull();
});

/**
 * Change the live folder and make it live again, then hand out fresh scoped objects.
 */
function approvedVisitLineResync(Closure $changeFolder): void
{
    $changeFolder();

    app(FolderSync::class)->run();
    app()->forgetScopedInstances();
}

/**
 * The live version as a folder synced before visit_line existed: the booking file's compiled front matter has no line.
 */
function approvedVisitLineOlderVersion(): void
{
    $version = IcmVersion::query()->findOrFail(app(ActiveConfig::class)->versionId());
    $compiled = $version->compiled;
    $path = $compiled['index']['booking'];
    $path = is_array($path) ? $path[0] : $path;
    unset($compiled['files'][$path]['front_matter']['visit_line']);
    $version->update(['compiled' => $compiled]);

    app(ActiveConfig::class)->refresh();
}

/**
 * The last text that reached Dana's phone.
 */
function approvedVisitLineLastText(): string
{
    $texts = approvedVisitLineTextsToDana();

    return $texts[array_key_last($texts)];
}

/**
 * Words which, closed by the sign-off with the visit line after them, make a text of exactly $length characters.
 */
function approvedVisitLineWordsMakingText(int $length): string
{
    $overhead = mb_strlen(approvedVisitLineLater(' '.APPROVED_VISIT_LINE_TEXT));

    return str_repeat('a', $length - $overhead);
}

it('adds the visit line after the owner\'s own words on an edited draft (D-39 amended, D-45)', function () {
    $draft = approvedVisitLineHeld();

    approvedVisitLineThread()
        ->set('draftBody', 'See you Thursday.')
        ->call('approveDraft')
        ->assertHasNoErrors();

    expect(approvedVisitLineTextsToDana()[1])->toBe(approvedVisitLineLater('See you Thursday. '.APPROVED_VISIT_LINE_TEXT))
        ->and($draft->fresh()->resolution)->toBe(DraftResolution::Edited)
        ->and($draft->fresh()->original_body)->toBe(APPROVED_VISIT_LINE_WORDS);
});

it('sends the draft as today with the box left off (D-45)', function () {
    $draft = approvedVisitLineHeld();

    approvedVisitLineThread()
        ->set('addVisitLine', false)
        ->call('approveDraft')
        ->assertNotified(Notification::make()->title('Draft sent')->success());

    expect(approvedVisitLineTextsToDana()[1])->toBe(approvedVisitLineLater(APPROVED_VISIT_LINE_WORDS))
        ->and($draft->fresh()->meta)->not->toHaveKey('visit_line_appointment_id')
        ->and($draft->fresh()->meta)->not->toHaveKey('visit_line_left_off');
});

it('starts the box unchecked when the reading is not clean, and sends the words alone as drawn (D-45)', function (string $reading) {
    $draft = approvedVisitLineHeld(reading: $reading);

    approvedVisitLineThread()
        ->assertSet('visitLine', APPROVED_VISIT_LINE_TEXT)
        ->assertSee(APPROVED_VISIT_LINE_LABEL)
        ->assertSet('addVisitLine', false)
        ->call('approveDraft')
        ->assertNotified(Notification::make()->title('Draft sent')->success());

    expect(approvedVisitLineLastText())->toBe(approvedVisitLineLater(APPROVED_VISIT_LINE_WORDS))
        ->and($draft->fresh()->meta)->not->toHaveKey('visit_line_appointment_id')
        ->and($draft->fresh()->meta)->not->toHaveKey('visit_line_left_off');
})->with(['a complaint' => ['complaint'], 'a failed reading' => ['failed'], 'no stored reading' => ['none']]);

it('sends the visit line when the owner checks a box that started unchecked: the owner\'s approval decides (D-45)', function (string $reading) {
    $draft = approvedVisitLineHeld(reading: $reading);

    approvedVisitLineThread()
        ->set('addVisitLine', true)
        ->call('approveDraft')
        ->assertHasNoErrors()
        ->assertNotified(Notification::make()->title('Draft sent')->success());

    expect(approvedVisitLineLastText())->toBe(approvedVisitLineLater(APPROVED_VISIT_LINE_WORDS.' '.APPROVED_VISIT_LINE_TEXT))
        ->and($draft->fresh()->meta['visit_line_appointment_id'])->toBe(Appointment::sole()->id);
})->with(['a complaint' => ['complaint'], 'a failed reading' => ['failed'], 'no stored reading' => ['none']]);

it('checks every condition at approval and says why the visit line was left off (D-45)', function (string $case, string $reason) {
    $draft = approvedVisitLineHeld();

    $thread = approvedVisitLineThread()->assertSet('visitLine', APPROVED_VISIT_LINE_TEXT)->assertSet('addVisitLine', true);

    match ($case) {
        'visit cancelled' => Appointment::sole()->forceFill(['state' => AppointmentState::Cancelled])->save(),
        'booking file deleted' => approvedVisitLineResync(fn () => File::delete(icmPath('rooms/schedule_and_booking/booking.md'))),
        'older version' => approvedVisitLineOlderVersion(),
    };

    $thread->call('approveDraft')
        ->assertHasNoErrors()
        ->assertNotified(Notification::make()->title('Draft sent without the visit line: '.$reason)->warning());

    expect(approvedVisitLineLastText())->toBe(approvedVisitLineLater(APPROVED_VISIT_LINE_WORDS))
        ->and($draft->fresh()->status)->toBe(MessageStatus::Sent)
        ->and($draft->fresh()->meta['visit_line_left_off'])->toBe($reason)
        ->and($draft->fresh()->meta)->not->toHaveKey('visit_line_appointment_id');
})->with([
    'the visit cancelled' => ['visit cancelled', 'no visit is booked ahead'],
    'the booking file deleted' => ['booking file deleted', 'online booking is turned off'],
    'a version synced before visit_line' => ['older version', 'the booking file has no visit_line yet, so add it and sync again'],
]);

it('sends the draft without the visit line when the visit changed after the line was shown (WR-09)', function (string $case) {
    $draft = approvedVisitLineHeld();

    $thread = approvedVisitLineThread()->assertSet('visitLine', APPROVED_VISIT_LINE_TEXT)->assertSet('addVisitLine', true);

    match ($case) {
        'moved' => Appointment::sole()->forceFill([
            'starts_at' => CarbonImmutable::parse('2026-11-06 09:00', 'America/Chicago'),
            'ends_at' => CarbonImmutable::parse('2026-11-06 10:30', 'America/Chicago'),
        ])->save(),
        'earlier booked' => Appointment::factory()->for(Contact::sole())->at(CarbonImmutable::parse('2026-11-04 08:00', 'America/Chicago'))->create(),
    };

    $thread->call('approveDraft')
        ->assertHasNoErrors()
        ->assertNotified(Notification::make()->title('Draft sent without the visit line: the visit changed after the line was shown')->warning());

    expect(approvedVisitLineLastText())->toBe(approvedVisitLineLater(APPROVED_VISIT_LINE_WORDS))
        ->and($draft->fresh()->status)->toBe(MessageStatus::Sent)
        ->and($draft->fresh()->meta['visit_line_left_off'])->toBe('the visit changed after the line was shown')
        ->and($draft->fresh()->meta)->not->toHaveKey('visit_line_appointment_id');
})->with([
    'the visit moved to another day' => ['moved'],
    'an earlier visit booked' => ['earlier booked'],
]);

it('adds no visit line the owner was not shown (T-08-102)', function () {
    $draft = approvedVisitLineHeld(booked: false);

    // The unbooked draft draws the booking link box checked, so it is unticked to isolate the visit line.
    $thread = approvedVisitLineThread()
        ->assertSet('visitLine', null)
        ->assertDontSee('The visit line will be added');

    // The browser sets the box anyway; these round trips render while there is still no visit.
    $thread->set('addBookingLink', false)->set('addVisitLine', true);

    Appointment::factory()->for(Contact::sole())->at(CarbonImmutable::parse('2026-11-05 10:00', 'America/Chicago'))->create();

    $thread->call('approveDraft')
        ->assertNotified(Notification::make()->title('Draft sent')->success());

    expect(approvedVisitLineLastText())->toBe(approvedVisitLineLater(APPROVED_VISIT_LINE_WORDS))
        ->and($draft->fresh()->meta)->not->toHaveKey('visit_line_appointment_id')
        ->and($draft->fresh()->meta)->not->toHaveKey('visit_line_left_off');
});

it('shows the visit line on a draft labeled other when the customer\'s text read as scheduling (UAT message 20)', function () {
    approvedVisitLineHeld(['reply' => "Thanks for checking. I can't confirm the visit time myself.", 'label' => 'other']);

    approvedVisitLineThread()
        ->assertSet('visitLine', APPROVED_VISIT_LINE_TEXT)
        ->assertSee(APPROVED_VISIT_LINE_LABEL);
});

it('draws no visit line when neither the label nor the reading is about scheduling (T-08-102)', function () {
    $draft = approvedVisitLineHeld(['label' => 'faq_answer'], intent: 'faq');

    approvedVisitLineThread()
        ->assertSet('visitLine', null)
        ->assertDontSee('The visit line will be added')
        ->set('addVisitLine', true)
        ->call('approveDraft')
        ->assertNotified(Notification::make()->title('Draft sent')->success());

    expect(approvedVisitLineLastText())->toBe(approvedVisitLineLater(APPROVED_VISIT_LINE_WORDS))
        ->and($draft->fresh()->meta)->not->toHaveKey('visit_line_appointment_id')
        ->and($draft->fresh()->meta)->not->toHaveKey('visit_line_left_off');
});

it('sends only the words when a line is passed for a draft that is not about scheduling and called directly (T-08-102)', function () {
    $draft = approvedVisitLineHeld(['label' => 'faq_answer'], intent: 'faq');

    app(ResolveDraft::class)->approve($draft, User::query()->where('phone', APPROVED_VISIT_LINE_OWNER_PHONE)->sole(), null, false, APPROVED_VISIT_LINE_TEXT);

    expect(approvedVisitLineLastText())->toBe(approvedVisitLineLater(APPROVED_VISIT_LINE_WORDS))
        ->and($draft->fresh()->meta)->not->toHaveKey('visit_line_left_off');
});

it('follows the conditions while the same draft stays on screen (U-37)', function () {
    approvedVisitLineHeld();

    $thread = approvedVisitLineThread()->assertSet('visitLine', APPROVED_VISIT_LINE_TEXT);

    Appointment::sole()->forceFill(['state' => AppointmentState::Cancelled])->save();
    $thread->call('$refresh')->assertSet('visitLine', null)->assertDontSee('The visit line will be added');

    Appointment::factory()->for(Contact::sole())->at(CarbonImmutable::parse('2026-11-06 09:00', 'America/Chicago'))->create();
    $thread->call('$refresh')
        ->assertSet('visitLine', 'Your visit for AC repair is on Friday, November 6 at 9:00 AM. To change it, reply to this text.')
        ->assertSee('The visit line will be added: Your visit for AC repair is on Friday, November 6 at 9:00 AM.');
});

it('resets the box to the starting state of a draft that replaces the one on screen (U-37)', function (string $firstReading, bool $secondIsComplaint, bool $ownerSet, bool $expected) {
    approvedVisitLineHeld(reading: $firstReading);

    $thread = approvedVisitLineThread()->set('addVisitLine', $ownerSet);

    if ($secondIsComplaint) {
        fakeClassifier()->answerReading('complaint', 0.95);
    }

    test()->travel(1)->minutes();
    test()->post(route('webhooks.text.fake'), fakeTextPayload(['MessageSid' => 'SM'.str_repeat('d', 32), 'Body' => 'Actually Friday is better']))->assertOk();
    scriptFrontDeskReplies(['reply' => 'We guarantee 9 am Friday. Does that work?', 'label' => 'scheduling', 'service_slug' => 'ac-repair', 'touches_waiting' => true]);
    runQueuedFrontDeskJobs();

    $thread->call('$refresh')
        ->assertSet('visitLine', APPROVED_VISIT_LINE_TEXT)
        ->assertSet('addVisitLine', $expected);
})->with([
    'unticked on a clean draft, replaced by a clean one: checked again' => ['clean', false, false, true],
    'ticked on a clean draft, replaced by a complaint read: unchecked' => ['clean', true, true, false],
]);

it('refuses Send when the visit line would pass 1,600 characters, and sends at exactly 1,600 (U-37)', function (int $length) {
    $draft = approvedVisitLineHeld();
    $words = approvedVisitLineWordsMakingText($length);

    $thread = approvedVisitLineThread()->set('draftBody', $words)->call('approveDraft');

    if ($length > 1600) {
        expect($thread->errors()->first('draftBody'))->toBe('With the visit line this text is over 1,600 characters. Shorten it, or leave the visit line off.')
            ->and($draft->fresh()->status)->toBe(MessageStatus::Draft)
            ->and(approvedVisitLineTextsToDana())->toHaveCount(1);

        $thread->assertSet('draftBody', $words);

        return;
    }

    $thread->assertHasNoErrors();

    expect(mb_strlen(approvedVisitLineTextsToDana()[1]))->toBe(1600)
        ->and(approvedVisitLineTextsToDana()[1])->toContain(APPROVED_VISIT_LINE_TEXT)
        ->and($draft->fresh()->status)->toBe(MessageStatus::Sent);
})->with(['one over' => [1601], 'exactly the limit' => [1600]]);

it('never draws the booking link box beside the visit line box (D-45)', function () {
    approvedVisitLineHeld();

    approvedVisitLineThread()
        ->assertSet('bookingLinkService', null)
        ->assertSet('visitLine', APPROVED_VISIT_LINE_TEXT);
});

it('draws the booking link box and no visit line box for a customer with no visit booked (D-45)', function () {
    approvedVisitLineHeld(booked: false);

    approvedVisitLineThread()
        ->assertSet('bookingLinkService', 'AC repair')
        ->assertSet('visitLine', null);
});
