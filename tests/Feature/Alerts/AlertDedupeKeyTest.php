<?php

use App\Actions\OwnerAlertText;
use App\Actions\RaiseOwnerAlert;
use App\Channels\FakeTextChannel;
use App\Enums\AlertReason;
use App\Enums\MessageStatus;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\OwnerAlert;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * An owner alert is stored once however many workers raise it at once (ALRT-01, WR-04).
 *
 * The race is simulated, on SQLite here and on Postgres in CI, with no two-connection test
 * (06 D-21): RaiseOwnerAlert calls OwnerAlertText::build() after its alreadyRaised() check and
 * before its insert, so a text builder that runs a second, complete raise of the same alert
 * from inside build() is exactly "the other worker got in between the check and the insert".
 */

/**
 * Dana Whitfield's open conversation on a Tuesday morning while the shop is open, with her last
 * text and a draft waiting for the owner. Returns the conversation and the draft.
 *
 * @return array{0: Conversation, 1: Message}
 */
function dedupeKeyConversation(): array
{
    activateExampleFolder();
    test()->travelTo(Carbon::parse('2026-10-13 10:00', 'America/Chicago'));

    $contact = Contact::factory()->create(['name' => 'Dana Whitfield', 'phone_e164' => '+12025550123']);
    $conversation = Conversation::factory()->for($contact)->create();
    Message::factory()->for($conversation)->create(['body' => 'How much for a new unit?']);
    $draft = Message::factory()->for($conversation)->outbound()->create([
        'body' => 'Mike will text you a price soon.',
        'status' => MessageStatus::Draft,
    ]);

    return [$conversation, $draft];
}

/**
 * The texts the fake text channel has been asked to send, oldest first.
 *
 * @return list<array{to: string, body: string, provider_message_id: string}>
 */
function dedupeKeySentTexts(): array
{
    return app(FakeTextChannel::class)->sent();
}

/**
 * Make the next alert text a raise builds start another worker's complete raise of the same
 * alert first, the way a second queue worker would get in between the check and the insert.
 * Returns the alerts that other worker stored, filled in once the race has run.
 *
 * @return ArrayObject<int, mixed>
 */
function dedupeKeyOtherWorkerRaces(Conversation $conversation, AlertReason $reason, ?Message $subject): ArrayObject
{
    $otherWorkerStored = new ArrayObject;

    $textBuilder = new class($otherWorkerStored, $conversation, $reason, $subject) extends OwnerAlertText
    {
        private bool $otherWorkerHasRun = false;

        /**
         * @param  ArrayObject<int, mixed>  $otherWorkerStored
         */
        public function __construct(
            private readonly ArrayObject $otherWorkerStored,
            private readonly Conversation $racedConversation,
            private readonly AlertReason $racedReason,
            private readonly ?Message $racedSubject,
        ) {}

        public function build(Conversation $conversation, AlertReason $reason, ?Message $subject, ?string $why): string
        {
            if (! $this->otherWorkerHasRun) {
                $this->otherWorkerHasRun = true;

                foreach (app(RaiseOwnerAlert::class)->handle($this->racedConversation, $this->racedReason, $this->racedSubject) as $alert) {
                    $this->otherWorkerStored->append($alert);
                }
            }

            return parent::build($conversation, $reason, $subject, $why);
        }
    };

    app()->instance(OwnerAlertText::class, $textBuilder);

    return $otherWorkerStored;
}

it('stores one alert and sends one text when two workers raise the same alert at once', function () {
    [$conversation, $draft] = dedupeKeyConversation();
    User::factory()->withPhone('+12025550199')->create();

    $otherWorkerStored = dedupeKeyOtherWorkerRaces($conversation, AlertReason::DraftWaiting, $draft);

    $lostTheRace = app(RaiseOwnerAlert::class)->handle($conversation, AlertReason::DraftWaiting, $draft);

    expect($lostTheRace)->toBeEmpty()
        ->and($otherWorkerStored)->toHaveCount(1)
        ->and(OwnerAlert::query()->where('conversation_id', $conversation->id)->where('message_id', $draft->id)->where('reason', AlertReason::DraftWaiting->value)->count())->toBe(1)
        ->and(dedupeKeySentTexts())->toHaveCount(1);
});

it('leaves the alert the other worker stored as it was when the second worker loses', function () {
    [$conversation, $draft] = dedupeKeyConversation();
    User::factory()->withPhone('+12025550199')->create();

    dedupeKeyOtherWorkerRaces($conversation, AlertReason::DraftWaiting, $draft);

    app(RaiseOwnerAlert::class)->handle($conversation, AlertReason::DraftWaiting, $draft);

    $alert = OwnerAlert::query()->sole();

    expect($alert->sent_at)->not->toBeNull()
        ->and($alert->failed_at)->toBeNull()
        ->and($alert->skipped_at)->toBeNull();
});

it('keeps working in the same transaction after the duplicate is refused', function () {
    [$conversation, $draft] = dedupeKeyConversation();
    User::factory()->withPhone('+12025550199')->create();

    dedupeKeyOtherWorkerRaces($conversation, AlertReason::DraftWaiting, $draft);

    app(RaiseOwnerAlert::class)->handle($conversation, AlertReason::DraftWaiting, $draft);
    $another = app(RaiseOwnerAlert::class)->handle($conversation, AlertReason::Complaint, $draft);

    expect($another)->toHaveCount(1)
        ->and(OwnerAlert::query()->count())->toBe(2)
        ->and(dedupeKeySentTexts())->toHaveCount(2);
});

it('stores each conversation alert under the key OwnerAlert builds for it', function () {
    [$conversation, $draft] = dedupeKeyConversation();
    $user = User::factory()->withPhone('+12025550199')->create();

    app(RaiseOwnerAlert::class)->handle($conversation, AlertReason::DraftWaiting, $draft);

    expect(OwnerAlert::query()->sole()->dedupe_key)
        ->toBe(OwnerAlert::dedupeKey($conversation->id, $draft->id, AlertReason::DraftWaiting, $user->id));
});

it('builds the key from the conversation, message, reason and person, with 0 for a missing message or person', function () {
    expect(OwnerAlert::dedupeKey(12, 34, AlertReason::DraftWaiting, 5))->toBe('c12:m34:draft_waiting:u5')
        ->and(OwnerAlert::dedupeKey(12, null, AlertReason::Complaint, null))->toBe('c12:m0:complaint:u0');
});

it('stores business-wide alerts with no key and sends every one of them', function () {
    User::factory()->withPhone('+12025550199')->create();

    app(RaiseOwnerAlert::class)->forBusiness(AlertReason::SafetyCheckDown, 'The safety check is down.');
    app(RaiseOwnerAlert::class)->forBusiness(AlertReason::SafetyCheckDown, 'The safety check is down.');

    expect(OwnerAlert::query()->count())->toBe(2)
        ->and(OwnerAlert::query()->whereNotNull('dedupe_key')->count())->toBe(0)
        ->and(dedupeKeySentTexts())->toHaveCount(2);
});
