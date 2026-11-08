<?php

use App\Actions\OutboundSender;
use App\Actions\SendBookingText;
use App\Enums\ActivityType;
use App\Enums\ConversationStatus;
use App\Enums\MessageAuthor;
use App\Enums\MessageKind;
use App\Enums\SendOrigin;
use App\Jobs\SendDueBookingTexts;
use App\Models\Activity;
use App\Models\Appointment;
use App\Models\BookingSend;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * WR-04 (08-REVIEW.md): a refusal is told apart by a constant the sender owns, so a reworded sentence cannot silently
 * change what a caller does. OutboundSender holds every sentence a caller reads as a public constant and returns or
 * throws exactly that constant; SendBookingText decides wait, drop, note or fail by comparing with the constants and
 * keeps no copy of a sentence.
 */

/**
 * A conversation whose customer has texted the business, so nothing blocks an automated text until a test says so.
 *
 * @param  array<string, mixed>  $conversation  Columns that replace the open conversation's defaults.
 */
function refusalSentencesConversation(?Contact $contact = null, array $conversation = []): Conversation
{
    $contact ??= Contact::factory()->create();
    $made = Conversation::factory()->for($contact)->create($conversation);
    Message::factory()->for($made)->create(['handled_at' => now()]);

    return $made;
}

/**
 * The sentence of the refusal a send throws.
 */
function refusalSentencesThrown(Closure $send): string
{
    try {
        $send();
    } catch (DomainException $refusal) {
        return $refusal->getMessage();
    }

    return '';
}

/**
 * Hold the reply lock of a conversation, as a reply in flight does, and wait no time for it: the clock may be frozen by a travelTo, and a lock wait that counts seconds on a frozen clock never ends.
 */
function refusalSentencesHoldReplyLock(Conversation $conversation): void
{
    config(['gate.agent.lock_wait_seconds' => 0]);

    expect(Cache::lock('reply:'.$conversation->id, 30)->get())->toBeTrue();
}

/**
 * The live example folder, the clock at Wednesday 2026-11-04 10:00 in the shop's zone, and Dana Whitfield, who texted and
 * whose open conversation already had one automated text. Her visit is Thursday 2026-11-05 at 10:00, with its 24-hour
 * reminder due now.
 *
 * @return array{0: Conversation, 1: BookingSend}
 */
function refusalSentencesBookingRow(): array
{
    activateExampleFolder();
    test()->travelTo(CarbonImmutable::parse('2026-11-04 10:00', 'America/Chicago')->utc());

    $contact = Contact::factory()->create(['name' => 'Dana Whitfield']);
    $conversation = Conversation::factory()->for($contact)->create();
    Message::factory()->for($conversation)->create(['handled_at' => now()]);
    Message::factory()->for($conversation)->outbound()->create();

    $appointment = Appointment::factory()->at(CarbonImmutable::parse('2026-11-05 10:00', 'America/Chicago'), 90)->create(['contact_id' => $contact->id]);

    $reminder = BookingSend::factory()->for($appointment)->create([
        'lead_minutes' => 1440,
        'due_at' => CarbonImmutable::parse('2026-11-04 10:00', 'America/Chicago')->utc(),
    ]);

    return [$conversation, $reminder];
}

it('returns its refusal sentences from its own constants', function (string $case) {
    $sender = app(OutboundSender::class);

    match ($case) {
        'opted out' => expect($sender->whyBlocked(refusalSentencesConversation(Contact::factory()->optedOut()->create()), SendOrigin::Automated))
            ->toStartWith(OutboundSender::OPTED_OUT),
        'closed' => expect($sender->whyBlocked(refusalSentencesConversation(conversation: ['status' => ConversationStatus::Closed]), SendOrigin::Automated))
            ->toBe(OutboundSender::CLOSED),
        'closed for the owner' => expect($sender->whyBlocked(refusalSentencesConversation(conversation: ['status' => ConversationStatus::Closed]), SendOrigin::Owner))
            ->toBe(OutboundSender::CLOSED_FOR_OWNER),
        'paused' => expect($sender->whyBlocked(Conversation::factory()->paused()->create(), SendOrigin::Automated))
            ->toBe(OutboundSender::PAUSED),
        'not agreed' => expect($sender->whyBlocked(Conversation::factory()->create(), SendOrigin::Automated))
            ->toBe(OutboundSender::NOT_AGREED),
        'no phone' => expect($sender->whyBlocked(refusalSentencesConversation(Contact::factory()->withoutPhone()->create()), SendOrigin::Automated))
            ->toBe(OutboundSender::NO_PHONE),
        'too long' => expect(refusalSentencesThrown(fn () => $sender->send(refusalSentencesConversation(), str_repeat('a', 1601), MessageAuthor::Agent, SendOrigin::Automated)))
            ->toBe(OutboundSender::TOO_LONG),
        'reply in flight' => (function () use ($sender): void {
            $conversation = refusalSentencesConversation();
            refusalSentencesHoldReplyLock($conversation);

            expect(refusalSentencesThrown(fn () => $sender->send($conversation, 'Hello', MessageAuthor::Agent, SendOrigin::Automated)))
                ->toBe(OutboundSender::REPLY_IN_FLIGHT);
        })(),
        'owner reply in flight' => (function () use ($sender): void {
            $conversation = refusalSentencesConversation();
            refusalSentencesHoldReplyLock($conversation);

            expect(refusalSentencesThrown(fn () => $sender->send($conversation, 'Hello', MessageAuthor::Owner, SendOrigin::Owner)))
                ->toBe(OutboundSender::OWNER_REPLY_IN_FLIGHT);
        })(),
    };
})->with(['opted out', 'closed', 'closed for the owner', 'paused', 'not agreed', 'no phone', 'too long', 'reply in flight', 'owner reply in flight']);

it('gives a booking text back while a reply is in flight (WR-04)', function () {
    [$conversation, $reminder] = refusalSentencesBookingRow();
    refusalSentencesHoldReplyLock($conversation);

    app()->call([new SendDueBookingTexts, 'handle']);

    $row = $reminder->fresh();

    expect(textsSentToCustomers())->toBeEmpty()
        ->and($row->sent_at)->toBeNull()
        ->and($row->skipped_at)->toBeNull()
        ->and($row->failed_at)->toBeNull()
        ->and($row->send_claimed_at)->toBeNull()
        ->and(Activity::query()->where('type', ActivityType::RoomEntry->value)->where('entry', 'booking_text_not_sent')->count())->toBe(0)
        ->and(Message::query()->where('kind', MessageKind::BookingText->value)->count())->toBe(0);
});

it('keeps no copy of a sender sentence in SendBookingText (WR-04)', function () {
    $source = file_get_contents((new ReflectionClass(SendBookingText::class))->getFileName());
    $sentences = array_filter((new ReflectionClass(OutboundSender::class))->getConstants(), is_string(...));

    expect($sentences)->not->toBeEmpty();

    foreach ($sentences as $name => $sentence) {
        expect($source)
            ->not->toContain("'{$sentence}'", "SendBookingText holds a copy of OutboundSender::{$name}")
            ->not->toContain("\"{$sentence}\"", "SendBookingText holds a copy of OutboundSender::{$name}");
    }

    expect($source)->not->toContain('->getMessage() ===');
});
