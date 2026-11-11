<?php

use App\Enums\DraftResolution;
use App\Enums\MessageStatus;
use App\Icm\ActiveConfig;
use App\Livewire\ConversationThread;
use App\Models\Appointment;
use App\Models\BookingLink;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\SendRule\CustomerReading;
use Carbon\CarbonImmutable;
use Filament\Notifications\Notification;
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
