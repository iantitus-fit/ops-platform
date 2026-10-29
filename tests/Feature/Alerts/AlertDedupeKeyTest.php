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

it('gives each reason its own alert on one message', function () {
    [$conversation, $draft] = dedupeKeyConversation();
    User::factory()->withPhone('+12025550199')->create();

    $waiting = app(RaiseOwnerAlert::class)->handle($conversation, AlertReason::DraftWaiting, $draft);
    $complaint = app(RaiseOwnerAlert::class)->handle($conversation, AlertReason::Complaint, $draft);

    expect($waiting)->toHaveCount(1)
        ->and($complaint)->toHaveCount(1)
        ->and(OwnerAlert::query()->pluck('dedupe_key')->unique())->toHaveCount(2)
        ->and(dedupeKeySentTexts())->toHaveCount(2);
});

it('gives each person with a phone their own alert for one reason', function () {
    [$conversation, $draft] = dedupeKeyConversation();
    User::factory()->withPhone('+12025550199')->create();
    User::factory()->withPhone('+13125550188')->create();

    $alerts = app(RaiseOwnerAlert::class)->handle($conversation, AlertReason::DraftWaiting, $draft);

    expect($alerts)->toHaveCount(2)
        ->and(OwnerAlert::query()->pluck('dedupe_key')->unique())->toHaveCount(2)
        ->and(collect(dedupeKeySentTexts())->pluck('to')->all())->toBe(['+12025550199', '+13125550188']);
});

it('gives a second message in the same conversation its own alert', function () {
    [$conversation, $draft] = dedupeKeyConversation();
    $laterText = Message::factory()->for($conversation)->create(['body' => 'Also, do you do water heaters?']);
    User::factory()->withPhone('+12025550199')->create();

    app(RaiseOwnerAlert::class)->handle($conversation, AlertReason::DraftWaiting, $draft);
    app(RaiseOwnerAlert::class)->handle($conversation, AlertReason::DraftWaiting, $laterText);

    expect(OwnerAlert::query()->count())->toBe(2)
        ->and(dedupeKeySentTexts())->toHaveCount(2);
});

it('stores one failed alert when nobody has a phone and two workers raise it at once', function () {
    [$conversation, $draft] = dedupeKeyConversation();
    User::factory()->create();

    dedupeKeyOtherWorkerRaces($conversation, AlertReason::DraftWaiting, $draft);

    $lostTheRace = app(RaiseOwnerAlert::class)->handle($conversation, AlertReason::DraftWaiting, $draft);
    $alert = OwnerAlert::query()->sole();

    expect($lostTheRace)->toBeEmpty()
        ->and($alert->user_id)->toBeNull()
        ->and($alert->failed_at)->not->toBeNull()
        ->and($alert->dedupe_key)->toEndWith(':u0');
});

it('de-duplicates an alert that has no subject message', function () {
    [$conversation] = dedupeKeyConversation();
    User::factory()->withPhone('+12025550199')->create();

    dedupeKeyOtherWorkerRaces($conversation, AlertReason::PausedMessage, null);

    $lostTheRace = app(RaiseOwnerAlert::class)->handle($conversation, AlertReason::PausedMessage);
    $alert = OwnerAlert::query()->sole();

    expect($lostTheRace)->toBeEmpty()
        ->and($alert->message_id)->toBeNull()
        ->and($alert->dedupe_key)->toContain(':m0:')
        ->and(dedupeKeySentTexts())->toHaveCount(1);
});

it('gives stored alerts their keys and keeps the key on the first of each duplicate group only', function () {
    $conversation = Conversation::factory()->create();
    $message = Message::factory()->for($conversation)->create();
    $user = User::factory()->withPhone('+12025550199')->create();

    $stored = fn (array $attributes) => OwnerAlert::factory()->for($conversation)->create(array_merge([
        'message_id' => $message->id,
        'user_id' => $user->id,
        'reason' => AlertReason::DraftWaiting,
    ], $attributes));

    $first = $stored([]);
    $twin = $stored([]);
    $otherReason = $stored(['reason' => AlertReason::Complaint]);
    $noMessageNoPerson = $stored(['message_id' => null, 'user_id' => null]);
    $business = OwnerAlert::factory()->create(['conversation_id' => null, 'reason' => AlertReason::SafetyCheckDown]);

    $migration = require database_path('migrations/2026_10_27_000003_backfill_dedupe_key_on_owner_alerts.php');
    $migration->up();

    expect($first->fresh()->dedupe_key)->toBe(OwnerAlert::dedupeKey($conversation->id, $message->id, AlertReason::DraftWaiting, $user->id))
        ->and($twin->fresh()->dedupe_key)->toBeNull()
        ->and($otherReason->fresh()->dedupe_key)->toBe(OwnerAlert::dedupeKey($conversation->id, $message->id, AlertReason::Complaint, $user->id))
        ->and($noMessageNoPerson->fresh()->dedupe_key)->toBe(OwnerAlert::dedupeKey($conversation->id, null, AlertReason::DraftWaiting, null))
        ->and($noMessageNoPerson->fresh()->dedupe_key)->toEndWith(':u0')
        ->and($business->fresh()->dedupe_key)->toBeNull();
});

it('changes nothing when the backfill runs a second time', function () {
    $conversation = Conversation::factory()->create();
    $first = OwnerAlert::factory()->for($conversation)->create(['reason' => AlertReason::DraftWaiting]);
    $twin = OwnerAlert::factory()->for($conversation)->create(['reason' => AlertReason::DraftWaiting]);

    $migration = require database_path('migrations/2026_10_27_000003_backfill_dedupe_key_on_owner_alerts.php');
    $migration->up();
    $keys = OwnerAlert::query()->orderBy('id')->pluck('dedupe_key')->all();
    $migration->up();

    expect(OwnerAlert::query()->orderBy('id')->pluck('dedupe_key')->all())->toBe($keys)
        ->and($keys[0])->not->toBeNull()
        ->and($keys[1])->toBeNull();
});
