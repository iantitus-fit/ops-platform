<?php

use App\Actions\SendOwnerAlert;
use App\Channels\FakeTextChannel;
use App\Channels\TextChannel;
use App\Enums\ActivityType;
use App\Enums\AlertReason;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\OwnerAlert;

/**
 * An owner alert is texted once, however many copies of it are sent and however two workers
 * interleave (ALRT-01, WR-03).
 *
 * The races are simulated, on SQLite here and on Postgres in CI, with no two-connection test
 * (06 D-21): a stale copy is an alert loaded twice before either is sent, and a second worker
 * entering mid-send is a text channel that runs the sender again from inside its own send().
 */

/**
 * A due alert for a person with a phone, on Dana Whitfield's conversation, ready to be sent.
 *
 * @param  array<string, mixed>  $attributes
 */
function sentOnceAlert(array $attributes = []): OwnerAlert
{
    $contact = Contact::factory()->create(['name' => 'Dana Whitfield', 'phone_e164' => '+12025550123']);
    $conversation = Conversation::factory()->for($contact)->create();

    return OwnerAlert::factory()->for($conversation)->create(array_merge([
        'reason' => AlertReason::DraftWaiting,
        'to_phone' => '+12025550199',
        'body' => 'Dana Whitfield: Held: asks about price',
        'due_at' => now(),
    ], $attributes));
}

/**
 * The texts the fake text channel has been asked to send, oldest first.
 *
 * @return list<array{to: string, body: string, provider_message_id: string}>
 */
function sentOnceTexts(): array
{
    return app(FakeTextChannel::class)->sent();
}

/**
 * How many owner_alerted entries are on customers' timelines.
 */
function sentOnceTimelineEntries(): int
{
    return Activity::query()->where('type', ActivityType::OwnerAlerted)->count();
}

it('texts once when two stale copies of one alert are both sent', function () {
    $alert = sentOnceAlert();
    $first = OwnerAlert::query()->find($alert->id);
    $second = OwnerAlert::query()->find($alert->id);

    app(SendOwnerAlert::class)->handle($first);
    app(SendOwnerAlert::class)->handle($second);

    $stored = OwnerAlert::query()->find($alert->id);

    expect(sentOnceTexts())->toHaveCount(1)
        ->and(sentOnceTexts()[0]['to'])->toBe('+12025550199')
        ->and($stored->sent_at)->not->toBeNull()
        ->and($stored->provider_message_id)->toBe(sentOnceTexts()[0]['provider_message_id'])
        ->and(sentOnceTimelineEntries())->toBe(1);
});

it('texts once when a second worker reaches the alert while the first is still sending', function () {
    $alert = sentOnceAlert();
    $observed = [];

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

    $channel->whileSending = function () use ($alert, &$observed, $channel): void {
        (new SendOwnerAlert($channel))->handle(OwnerAlert::query()->find($alert->id));

        $stored = OwnerAlert::query()->find($alert->id);

        $observed = [
            'sent_at' => $stored->sent_at,
            'failed_at' => $stored->failed_at,
            'timeline' => sentOnceTimelineEntries(),
        ];
    };

    app()->instance(TextChannel::class, $channel);

    app(SendOwnerAlert::class)->handle(OwnerAlert::query()->find($alert->id));

    expect($channel->sent())->toHaveCount(1)
        ->and($observed['sent_at'])->toBeNull()
        ->and($observed['failed_at'])->toBeNull()
        ->and($observed['timeline'])->toBe(0)
        ->and(OwnerAlert::query()->find($alert->id)->sent_at)->not->toBeNull()
        ->and(sentOnceTimelineEntries())->toBe(1);
});

it('sends nothing for a copy whose row has already ended', function () {
    $alert = sentOnceAlert();
    $staleCopy = OwnerAlert::query()->find($alert->id);

    OwnerAlert::query()->whereKey($alert->id)->update(['sent_at' => now(), 'provider_message_id' => 'someone-else']);

    app(SendOwnerAlert::class)->handle($staleCopy);

    expect(sentOnceTexts())->toBeEmpty()
        ->and(sentOnceTimelineEntries())->toBe(0)
        ->and(OwnerAlert::query()->find($alert->id)->provider_message_id)->toBe('someone-else');
});

it('claims the alert right before the text goes out', function () {
    $alert = sentOnceAlert();

    expect($alert->send_claimed_at)->toBeNull();

    app(SendOwnerAlert::class)->handle($alert);

    expect(sentOnceTexts())->toHaveCount(1)
        ->and(OwnerAlert::query()->find($alert->id)->send_claimed_at)->not->toBeNull();
});

it('takes no claim for an alert that has no phone to text', function () {
    $alert = sentOnceAlert(['to_phone' => null]);

    app(SendOwnerAlert::class)->handle($alert);

    $stored = OwnerAlert::query()->find($alert->id);

    expect(sentOnceTexts())->toBeEmpty()
        ->and($stored->failed_at)->not->toBeNull()
        ->and($stored->failure)->toBe('No phone on file')
        ->and($stored->send_claimed_at)->toBeNull();
});

it('leaves an alert alone while someone else holds a fresh claim on it', function () {
    $held = OwnerAlert::factory()->claimed()->for(sentOnceAlert()->conversation)->create(['to_phone' => '+12025550199']);

    app(SendOwnerAlert::class)->handle($held);

    expect(sentOnceTexts())->toBeEmpty()
        ->and(OwnerAlert::query()->find($held->id)->sent_at)->toBeNull()
        ->and(sentOnceTimelineEntries())->toBe(0);
});

it('texts an alert whose claim has gone stale exactly once', function () {
    $alert = OwnerAlert::factory()->staleClaim()->for(sentOnceAlert()->conversation)->create(['to_phone' => '+12025550199']);

    app(SendOwnerAlert::class)->handle($alert);
    app(SendOwnerAlert::class)->handle(OwnerAlert::query()->find($alert->id));

    expect(sentOnceTexts())->toHaveCount(1)
        ->and(OwnerAlert::query()->find($alert->id)->sent_at)->not->toBeNull();
});

it('never throws when the alert row has been deleted under the copy', function () {
    $alert = sentOnceAlert();
    $copy = OwnerAlert::query()->find($alert->id);

    OwnerAlert::query()->whereKey($alert->id)->delete();

    app(SendOwnerAlert::class)->handle($copy);

    expect(sentOnceTexts())->toBeEmpty();
});
