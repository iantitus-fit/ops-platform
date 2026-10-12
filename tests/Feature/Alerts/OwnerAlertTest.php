<?php

use App\Actions\RaiseOwnerAlert;
use App\Actions\SendOwnerAlert;
use App\Channels\FakeTextChannel;
use App\Enums\AlertReason;
use App\Enums\MessageStatus;
use App\Filament\Pages\BusinessFolder;
use App\Filament\Resources\Conversations\ConversationResource;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\OwnerAlert;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Dana Whitfield's open conversation on a Tuesday morning while the shop is open, with her last
 * text and a draft waiting for the owner. Returns the conversation and the draft.
 *
 * @param  array<string, mixed>  $contact
 * @return array{0: Conversation, 1: Message}
 */
function ownerAlertTestConversation(
    string $customerText = 'How much for a new unit? See www.example.com or email dana@example.com or call 202-555-0143',
    array $contact = [],
): array {
    activateExampleFolder();
    test()->travelTo(Carbon::parse('2026-10-13 10:00', 'America/Chicago'));

    $contact = Contact::factory()->create(array_merge(['name' => 'Dana Whitfield', 'phone_e164' => '+12025550123'], $contact));
    $conversation = Conversation::factory()->for($contact)->create();
    Message::factory()->for($conversation)->create(['body' => $customerText]);
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
function ownerAlertTestSentTexts(): array
{
    return app(FakeTextChannel::class)->sent();
}

it('texts the owner a short alert built by code with our link last', function () {
    [$conversation, $draft] = ownerAlertTestConversation();
    User::factory()->withPhone('+12025550199')->create();

    app(RaiseOwnerAlert::class)->handle($conversation, AlertReason::DraftWaiting, $draft, 'Held: asks about price');

    $sent = ownerAlertTestSentTexts();
    $url = ConversationResource::getUrl('view', ['record' => $conversation]);

    expect($sent)->toHaveCount(1)
        ->and($sent[0]['to'])->toBe('+12025550199')
        ->and(explode("\n", $sent[0]['body']))->toBe([
            'Dana Whitfield: Held: asks about price',
            'They wrote: "How much for a new unit? See [link] or email [email] or call [number]"',
            'Draft: "Mike will text you a price soon."',
            '',
            $url,
        ])
        ->and(substr_count($sent[0]['body'], 'http'))->toBe(1)
        ->and($sent[0]['body'])->not->toContain('www.')
        ->and($sent[0]['body'])->not->toContain('@');
});

it('stores a sent alert with who it went to and the provider id', function () {
    [$conversation, $draft] = ownerAlertTestConversation();
    $owner = User::factory()->withPhone('+12025550199')->create();

    $alerts = app(RaiseOwnerAlert::class)->handle($conversation, AlertReason::DraftWaiting, $draft, 'Held: asks about price');

    $alert = OwnerAlert::query()->sole();

    expect($alerts)->toHaveCount(1)
        ->and($alert->user_id)->toBe($owner->id)
        ->and($alert->to_phone)->toBe('+12025550199')
        ->and($alert->reason)->toBe(AlertReason::DraftWaiting)
        ->and($alert->urgent)->toBeFalse()
        ->and($alert->message_id)->toBe($draft->id)
        ->and($alert->sent_at)->not->toBeNull()
        ->and($alert->failed_at)->toBeNull()
        ->and($alert->provider_message_id)->toBe(ownerAlertTestSentTexts()[0]['provider_message_id']);
});

it('marks a life safety alert urgent and an alert on the right-away list urgent', function () {
    [$conversation] = ownerAlertTestConversation();
    User::factory()->withPhone()->create();
    $raise = app(RaiseOwnerAlert::class);

    expect($raise->isUrgent(AlertReason::LifeSafety))->toBeTrue()
        ->and($raise->isUrgent(AlertReason::Complaint))->toBeTrue()
        ->and($raise->isUrgent(AlertReason::DraftWaiting))->toBeFalse();

    $raise->handle($conversation, AlertReason::Complaint);

    expect(OwnerAlert::query()->sole()->urgent)->toBeTrue();
});

it('swaps a link inside the contact name for [link]', function () {
    [$conversation] = ownerAlertTestConversation(contact: ['name' => 'Dana www.evil.com']);
    User::factory()->withPhone()->create();

    app(RaiseOwnerAlert::class)->handle($conversation, AlertReason::AskedForPerson);

    expect(ownerAlertTestSentTexts()[0]['body'])->toStartWith('Dana [link]: Asked for a person');
});

it('swaps a bare domain and a domain with a path for [link]', function () {
    [$conversation] = ownerAlertTestConversation('Try evil.com or shop.example.org/deals and http://x.co/a?b=1 or https://secure.example.net');
    User::factory()->withPhone()->create();

    app(RaiseOwnerAlert::class)->handle($conversation, AlertReason::AskedForPerson);

    expect(ownerAlertTestSentTexts()[0]['body'])
        ->toContain('They wrote: "Try [link] or [link] and [link] or [link]"')
        ->and(substr_count(ownerAlertTestSentTexts()[0]['body'], 'http'))->toBe(1);
});

it('swaps phone numbers written with spaces, dashes and brackets for [number]', function () {
    [$conversation] = ownerAlertTestConversation('Call +1 (202) 555-0143 or 2025550143 or 202.555.0143 about 3 units');
    User::factory()->withPhone()->create();

    app(RaiseOwnerAlert::class)->handle($conversation, AlertReason::AskedForPerson);

    expect(ownerAlertTestSentTexts()[0]['body'])
        ->toContain('They wrote: "Call [number] or [number] or [number] about 3 units"');
});

it('shows the phone when the contact has no name', function () {
    [$conversation] = ownerAlertTestConversation(contact: ['name' => null]);
    User::factory()->withPhone()->create();

    app(RaiseOwnerAlert::class)->handle($conversation, AlertReason::AskedForPerson);

    expect(ownerAlertTestSentTexts()[0]['body'])->toStartWith('+12025550123: Asked for a person');
});

it('cuts a long customer text to 120 characters ending in three plain dots', function () {
    [$conversation] = ownerAlertTestConversation(str_repeat('a', 300));
    User::factory()->withPhone()->create();

    app(RaiseOwnerAlert::class)->handle($conversation, AlertReason::AskedForPerson);

    $line = explode("\n", ownerAlertTestSentTexts()[0]['body'])[1];
    $quoted = substr($line, strlen('They wrote: "'), -1);

    expect(mb_strlen($quoted))->toBe(120)
        ->and($quoted)->toEndWith('...')
        ->and(mb_check_encoding($quoted, 'ASCII'))->toBeTrue();
});

it('cuts a long draft to 160 characters', function () {
    [$conversation, $draft] = ownerAlertTestConversation();
    $draft->update(['body' => str_repeat('b', 400)]);
    User::factory()->withPhone()->create();

    app(RaiseOwnerAlert::class)->handle($conversation, AlertReason::DraftWaiting, $draft);

    $line = explode("\n", ownerAlertTestSentTexts()[0]['body'])[2];

    expect(mb_strlen(substr($line, strlen('Draft: "'), -1)))->toBe(160);
});

it('says the reason in plain words when none is given and leaves out the draft line when there is no draft', function () {
    [$conversation] = ownerAlertTestConversation();
    User::factory()->withPhone()->create();

    app(RaiseOwnerAlert::class)->handle($conversation, AlertReason::Complaint);

    $lines = explode("\n", ownerAlertTestSentTexts()[0]['body']);

    expect($lines[0])->toBe('Dana Whitfield: Complaint')
        ->and($lines)->toHaveCount(4)
        ->and($lines[2])->toBe('');
});

it('stores a failed alert and sends nothing when no user has a phone on file', function () {
    [$conversation, $draft] = ownerAlertTestConversation();
    User::factory()->create();

    $alerts = app(RaiseOwnerAlert::class)->handle($conversation, AlertReason::DraftWaiting, $draft);

    $alert = OwnerAlert::query()->sole();

    expect($alerts)->toHaveCount(1)
        ->and($alert->user_id)->toBeNull()
        ->and($alert->failed_at)->not->toBeNull()
        ->and($alert->failure)->toBe('No phone on file')
        ->and($alert->sent_at)->toBeNull()
        ->and(ownerAlertTestSentTexts())->toBeEmpty();
});

it('does not store a second failed alert when the same no-phone alert is raised again', function () {
    [$conversation, $draft] = ownerAlertTestConversation();

    app(RaiseOwnerAlert::class)->handle($conversation, AlertReason::DraftWaiting, $draft);
    app(RaiseOwnerAlert::class)->handle($conversation, AlertReason::DraftWaiting, $draft);

    expect(OwnerAlert::query()->count())->toBe(1);
});

it('texts every user who has a phone and skips users who have none', function () {
    [$conversation, $draft] = ownerAlertTestConversation();
    User::factory()->withPhone('+12025550199')->create();
    User::factory()->withPhone('+13125550188')->create();
    User::factory()->create();

    app(RaiseOwnerAlert::class)->handle($conversation, AlertReason::DraftWaiting, $draft);

    expect(collect(ownerAlertTestSentTexts())->pluck('to')->all())->toBe(['+12025550199', '+13125550188'])
        ->and(OwnerAlert::query()->count())->toBe(2);
});

it('sends one alert per recipient for the same message and reason however often it is raised', function () {
    [$conversation, $draft] = ownerAlertTestConversation();
    User::factory()->withPhone('+12025550199')->create();
    User::factory()->withPhone('+13125550188')->create();

    $first = app(RaiseOwnerAlert::class)->handle($conversation, AlertReason::DraftWaiting, $draft);
    $second = app(RaiseOwnerAlert::class)->handle($conversation, AlertReason::DraftWaiting, $draft);

    expect($first)->toHaveCount(2)
        ->and($second)->toBeEmpty()
        ->and(ownerAlertTestSentTexts())->toHaveCount(2)
        ->and(OwnerAlert::query()->count())->toBe(2);
});

it('alerts again for a different reason on the same message', function () {
    [$conversation, $draft] = ownerAlertTestConversation();
    User::factory()->withPhone()->create();

    app(RaiseOwnerAlert::class)->handle($conversation, AlertReason::DraftWaiting, $draft);
    app(RaiseOwnerAlert::class)->handle($conversation, AlertReason::Complaint, $draft);

    expect(ownerAlertTestSentTexts())->toHaveCount(2);
});

it('stores a channel failure on the alert and raises nothing to the caller', function () {
    [$conversation, $draft] = ownerAlertTestConversation();
    User::factory()->withPhone()->create();
    config(['channels.text.driver' => 'twilio']);

    $alerts = app(RaiseOwnerAlert::class)->handle($conversation, AlertReason::DraftWaiting, $draft);

    $alert = $alerts->sole()->refresh();

    expect($alert->failed_at)->not->toBeNull()
        ->and($alert->failure)->not->toBeEmpty()
        ->and(mb_strlen($alert->failure))->toBeLessThanOrEqual(200)
        ->and($alert->sent_at)->toBeNull()
        ->and($alert->provider_message_id)->toBeNull();
});

it('does not send an alert that was already sent, skipped or failed', function (string $column) {
    [$conversation] = ownerAlertTestConversation();
    $alert = OwnerAlert::factory()->for($conversation)->create([
        'to_phone' => '+12025550199',
        $column => now(),
    ]);

    app(SendOwnerAlert::class)->handle($alert);

    expect(ownerAlertTestSentTexts())->toBeEmpty();
})->with(['sent_at', 'skipped_at', 'failed_at']);

it('uses the owner_alert morph name', function () {
    [$conversation] = ownerAlertTestConversation();
    $alert = OwnerAlert::factory()->for($conversation)->create();

    expect($alert->getMorphClass())->toBe('owner_alert');
});

it('stores an alert as failed with how to undo it when the owner phone has opted out (Pitfall 17)', function () {
    [$conversation, $draft] = ownerAlertTestConversation();
    User::factory()->withPhone('+12025550199')->create();

    test()->post(route('webhooks.text.fake'), fakeTextPayload([
        'MessageSid' => 'SM'.str_repeat('9', 32),
        'From' => '+12025550199',
        'Body' => 'STOP',
    ]))->assertOk();

    $alert = app(RaiseOwnerAlert::class)->handle($conversation, AlertReason::DraftWaiting, $draft, 'Held: asks about price')->sole()->refresh();

    expect($alert->failed_at)->not->toBeNull()
        ->and($alert->sent_at)->toBeNull()
        ->and($alert->failure)->toBe('Your phone opted out of texts (STOP). Text START to the business number to get alerts again.')
        ->and(ownerAlertTestSentTexts())->toBe([]);

    Livewire\Livewire::test(BusinessFolder::class)
        ->assertSee('The last alert was not sent: Your phone opted out of texts (STOP). Text START to the business number to get alerts again.');
});

it('sends alerts to the owner phone again after it texts START (Pitfall 17)', function () {
    [$conversation, $draft] = ownerAlertTestConversation();
    User::factory()->withPhone('+12025550199')->create();

    foreach (['STOP' => '8', 'START' => '7'] as $word => $sid) {
        test()->post(route('webhooks.text.fake'), fakeTextPayload([
            'MessageSid' => 'SM'.str_repeat($sid, 32),
            'From' => '+12025550199',
            'Body' => $word,
        ]))->assertOk();
    }

    $alert = app(RaiseOwnerAlert::class)->handle($conversation, AlertReason::DraftWaiting, $draft)->sole()->refresh();

    expect($alert->sent_at)->not->toBeNull()
        ->and($alert->failed_at)->toBeNull();
});
