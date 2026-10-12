<?php

use App\Actions\InboundData;
use App\Actions\OutboundSender;
use App\Actions\PauseConversation;
use App\Actions\RecordInbound;
use App\Actions\SendLifeSafetyLine;
use App\Ai\Agents\FrontDeskAgent;
use App\Enums\AlertReason;
use App\Enums\GateOutcome;
use App\Enums\MessageAuthor;
use App\Enums\MessageChannel;
use App\Enums\MessageKind;
use App\Enums\MessageStatus;
use App\Enums\PausedReason;
use App\Icm\ActiveConfig;
use App\Jobs\RespondToLifeSafety;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\OwnerAlert;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

const LIFE_SAFETY_OWNER_PHONE = '+12025550199';

const LIFE_SAFETY_CUSTOMER_PHONE = '+12025550123';

const LIFE_SAFETY_GAS_LINE = 'If you smell gas, leave the building now with everyone, and call 911 from outside.';

/**
 * Tuesday 2026-10-13 at 21:00 in the shop's zone (closed), the owner reachable by text, and a customer's text
 * arriving through the fake webhook. The queued ProcessInbound is not run, so only the immediate response can answer.
 *
 * @param  array<string, string>  $payload
 */
function lifeSafetyCustomerTexts(string $body, array $payload = []): void
{
    test()->travelTo(Carbon::parse('2026-10-13 21:00', 'America/Chicago'));

    User::factory()->withPhone(LIFE_SAFETY_OWNER_PHONE)->create();

    frontDeskCustomerTexts($body, $payload);
}

/**
 * The texts that reached the customer's phone, leaving out the alerts to the owner.
 *
 * @return list<array{to: string, body: string, provider_message_id: string}>
 */
function lifeSafetyTextsToCustomer(): array
{
    return array_values(array_filter(
        textsSentToCustomers(),
        fn (array $text): bool => $text['to'] === LIFE_SAFETY_CUSTOMER_PHONE,
    ));
}

/**
 * The texts that reached the owner's phone.
 *
 * @return list<array{to: string, body: string, provider_message_id: string}>
 */
function lifeSafetyTextsToOwner(): array
{
    return array_values(array_filter(
        textsSentToCustomers(),
        fn (array $text): bool => $text['to'] === LIFE_SAFETY_OWNER_PHONE,
    ));
}

/**
 * The safety line as the first automated text reaches the customer: the safety sentence, a blank line, then the
 * disclosure, and no sign-off.
 */
function lifeSafetyExpectedFirstText(string $line): string
{
    return $line."\n\n".app(ActiveConfig::class)->voice()['ai_disclosure'];
}

it('sends the safety line the moment a customer says they smell gas, at any hour (D-15, D-17)', function () {
    lifeSafetyCustomerTexts('I smell gas in the basement');

    $expected = lifeSafetyExpectedFirstText(LIFE_SAFETY_GAS_LINE.' Then call the gas company at +13125550188.');
    $inbound = Message::query()->where('author', MessageAuthor::Customer->value)->sole();
    $line = Message::query()->where('kind', MessageKind::LifeSafety->value)->sole();

    expect(lifeSafetyTextsToCustomer())->toHaveCount(1)
        ->and(lifeSafetyTextsToCustomer()[0]['body'])->toBe($expected)
        ->and($line->body)->toBe($expected)
        ->and($line->author)->toBe(MessageAuthor::Agent)
        ->and($line->status)->toBe(MessageStatus::Sent)
        ->and($line->meta['in_reply_to'])->toBe([$inbound->id])
        ->and($line->gate_reason)->toBe('life_safety');
});

it('puts the safety sentence first and the disclosure after it, with no sign-off (D-17, D-50)', function () {
    lifeSafetyCustomerTexts('I smell gas in the basement');

    $voice = app(ActiveConfig::class)->voice();
    $body = lifeSafetyTextsToCustomer()[0]['body'];

    expect(strpos($body, 'call 911 from outside'))->toBeLessThan(strpos($body, $voice['ai_disclosure']))
        ->and($body)->toStartWith('If you smell gas')
        ->and($body)->toEndWith($voice['ai_disclosure'])
        ->and($body)->not->toContain($voice['sign_off']."\n")
        ->and(str_ends_with($body, $voice['sign_off']))->toBeFalse();
});

it('pauses the conversation and alerts the owner at once, even after hours (D-49)', function () {
    lifeSafetyCustomerTexts('I smell gas in the basement');

    $alert = OwnerAlert::query()->sole();
    $conversation = Conversation::query()->sole();

    expect($alert->reason)->toBe(AlertReason::LifeSafety)
        ->and($alert->urgent)->toBeTrue()
        ->and($alert->sent_at)->not->toBeNull()
        ->and($alert->body)->toContain('Safety message sent')
        ->and(lifeSafetyTextsToOwner())->toHaveCount(1)
        ->and($conversation->isPaused())->toBeTrue()
        ->and($conversation->paused_reason)->toBe(PausedReason::LifeSafety);
});

it('asks the agent nothing and sends nothing more when the burst wait ends, and alerts only once', function () {
    lifeSafetyCustomerTexts('I smell gas in the basement');
    scriptFrontDeskReplies(['reply' => 'Happy to help with that.']);

    runQueuedFrontDeskJobs();

    FrontDeskAgent::assertNeverPrompted();

    expect(lifeSafetyTextsToCustomer())->toHaveCount(1)
        ->and(OwnerAlert::query()->count())->toBe(1)
        ->and(lifeSafetyTextsToOwner())->toHaveCount(1)
        ->and(Message::query()->where('direction', 'out')->count())->toBe(1);
});

it('ends the gas line after "from outside." when the folder has no utility phone (D-16)', function () {
    $this->travelTo(Carbon::parse('2026-10-13 21:00', 'America/Chicago'));
    breakFile('rules/replies.md', fn (string $contents): string => preg_replace('/^utility_phone:.*\R/m', '', $contents));

    lifeSafetyCustomerTexts('I smell gas in the basement');

    expect(lifeSafetyTextsToCustomer())->toHaveCount(1)
        ->and(lifeSafetyTextsToCustomer()[0]['body'])->toBe(lifeSafetyExpectedFirstText(LIFE_SAFETY_GAS_LINE));
});

const LIFE_SAFETY_GENERAL_LINE = 'Please get everyone out to safety now and call 911 from outside.';

/**
 * A further text from the same customer through the fake webhook, once the example folder is live. The letter picks
 * the MessageSid, so the same letter twice is the provider delivering the same text again.
 */
function lifeSafetyLaterText(string $body, string $sidLetter): void
{
    test()->post(route('webhooks.text.fake'), fakeTextPayload(['Body' => $body, 'MessageSid' => 'SM'.str_repeat($sidLetter, 32)]))->assertOk();
}

/**
 * A first text that is not about danger, then the owner taking over before the next text arrives.
 */
function lifeSafetyOwnerHasTakenOver(): Conversation
{
    lifeSafetyCustomerTexts('Are you open tomorrow?', ['MessageSid' => 'SM'.str_repeat('a', 32)]);

    $conversation = Conversation::query()->sole();
    $conversation->update(['paused_at' => now(), 'paused_reason' => PausedReason::TakenOver]);

    return $conversation;
}

it('still sends the safety line in a conversation the owner took over, and keeps the pause reason (D-17)', function () {
    $conversation = lifeSafetyOwnerHasTakenOver();

    lifeSafetyLaterText('the outlet is sparking', 'b');

    $alert = OwnerAlert::query()->sole();

    expect(lifeSafetyTextsToCustomer())->toHaveCount(1)
        ->and(lifeSafetyTextsToCustomer()[0]['body'])->toBe(lifeSafetyExpectedFirstText(LIFE_SAFETY_GENERAL_LINE))
        ->and($conversation->fresh()->paused_reason)->toBe(PausedReason::TakenOver)
        ->and($alert->reason)->toBe(AlertReason::LifeSafety)
        ->and($alert->sent_at)->not->toBeNull();
});

it('does not alert a second time for that text when the burst wait ends in a conversation the owner took over', function () {
    lifeSafetyOwnerHasTakenOver();
    lifeSafetyLaterText('the outlet is sparking', 'b');
    scriptFrontDeskReplies(['reply' => 'unused']);

    runQueuedFrontDeskJobs();

    FrontDeskAgent::assertNeverPrompted();

    expect(OwnerAlert::query()->pluck('reason')->all())->toBe([AlertReason::LifeSafety])
        ->and(lifeSafetyTextsToCustomer())->toHaveCount(1);
});

it('is never counted or blocked by the reply cap (D-52)', function () {
    lifeSafetyCustomerTexts('Are you still coming?', ['MessageSid' => 'SM'.str_repeat('a', 32)]);
    $conversation = Conversation::query()->sole();

    foreach ([5, 10, 20, 30, 40, 55] as $minutes) {
        Message::factory()->for($conversation)->outbound()->create([
            'kind' => MessageKind::AgentReply,
            'body' => 'Earlier automated text',
            'created_at' => now()->subMinutes($minutes),
        ]);
    }

    lifeSafetyLaterText('I smell gas', 'b');
    scriptFrontDeskReplies(['reply' => 'unused']);
    runQueuedFrontDeskJobs();

    expect(lifeSafetyTextsToCustomer())->toHaveCount(1)
        ->and(lifeSafetyTextsToCustomer()[0]['body'])->toStartWith('If you smell gas')
        ->and(OwnerAlert::query()->pluck('reason')->all())->toBe([AlertReason::LifeSafety])
        ->and($conversation->fresh()->paused_reason)->toBe(PausedReason::LifeSafety);
});

it('leaves the disclosure off when the conversation already had an automated text (D-50)', function () {
    lifeSafetyCustomerTexts('Are you still coming?', ['MessageSid' => 'SM'.str_repeat('a', 32)]);
    Message::factory()->for(Conversation::query()->sole())->outbound()->create(['body' => 'Earlier automated text']);

    lifeSafetyLaterText('I smell gas', 'b');

    expect(lifeSafetyTextsToCustomer())->toHaveCount(1)
        ->and(lifeSafetyTextsToCustomer()[0]['body'])->toBe(LIFE_SAFETY_GAS_LINE.' Then call the gas company at +13125550188.');
});

it('sends no second safety line when the provider delivers the same text again', function () {
    lifeSafetyCustomerTexts('I smell gas in the basement');

    lifeSafetyLaterText('I smell gas in the basement', 'c');

    $inbound = Message::query()->where('author', MessageAuthor::Customer->value)->sole();

    expect(lifeSafetyTextsToCustomer())->toHaveCount(1)
        ->and(app(SendLifeSafetyLine::class)->handle($inbound, 'gas', 'keyword'))->toBeNull()
        ->and(lifeSafetyTextsToCustomer())->toHaveCount(1)
        ->and(OwnerAlert::query()->count())->toBe(1);
});

it('answers a second text that describes a different danger in a paused conversation with its own line', function () {
    lifeSafetyCustomerTexts('I smell gas in the basement', ['MessageSid' => 'SM'.str_repeat('a', 32)]);

    lifeSafetyLaterText('now the outlet is sparking', 'b');

    expect(lifeSafetyTextsToCustomer())->toHaveCount(2)
        ->and(lifeSafetyTextsToCustomer()[1]['body'])->toBe(LIFE_SAFETY_GENERAL_LINE)
        ->and(OwnerAlert::query()->count())->toBe(2);
});

it('catches a danger the words missed through Jev, sends the line, pauses and holds the reply (D-46)', function () {
    lifeSafetyCustomerTexts('my thermostat smells funny');
    scriptFrontDeskReplies(['reply' => 'What is the system doing?', 'label' => 'intake_question']);
    fakeClassifier()->answer('faq', 0.95, ['life_safety' => 0.2]);

    runQueuedFrontDeskJobs();

    $line = Message::query()->where('kind', MessageKind::LifeSafety->value)->sole();
    $draft = Message::query()->where('status', MessageStatus::Draft->value)->sole();
    $alert = OwnerAlert::query()->where('reason', AlertReason::LifeSafety->value)->sole();
    $conversation = Conversation::query()->sole();

    expect(lifeSafetyTextsToCustomer())->toHaveCount(1)
        ->and(lifeSafetyTextsToCustomer()[0]['body'])->toBe(lifeSafetyExpectedFirstText(LIFE_SAFETY_GENERAL_LINE))
        ->and($line->gate['life_safety'])->toBe(['hazard' => 'general', 'trigger' => 'classifier'])
        ->and($conversation->isPaused())->toBeTrue()
        ->and($conversation->paused_reason)->toBe(PausedReason::LifeSafety)
        ->and($alert->urgent)->toBeTrue()
        ->and($alert->sent_at)->not->toBeNull()
        ->and($draft->gate_outcome)->toBe(GateOutcome::Hold)
        ->and($draft->gate_reason)->toBe('classifier:life_safety')
        ->and($draft->body)->toBe('What is the system doing?');
});

it('alerts the owner at once and pauses when the safety line cannot be sent (T-02-43)', function () {
    User::factory()->withPhone(LIFE_SAFETY_OWNER_PHONE)->create();
    activateExampleFolder();
    config(['channels.text.driver' => 'twilio']);

    app(RecordInbound::class)->handle(new InboundData(
        channel: MessageChannel::Sms,
        source: 'text',
        body: 'I smell gas in the basement',
        phone: LIFE_SAFETY_CUSTOMER_PHONE,
        email: null,
        name: null,
        provider: 'twilio',
        providerMessageId: 'SM'.str_repeat('d', 32),
    ));

    $alert = OwnerAlert::query()->sole();
    $conversation = Conversation::query()->sole();

    expect($alert->reason)->toBe(AlertReason::LifeSafety)
        ->and($alert->urgent)->toBeTrue()
        ->and($alert->body)->toContain('Safety message could not be sent')
        ->and($conversation->isPaused())->toBeTrue()
        ->and($conversation->paused_reason)->toBe(PausedReason::LifeSafety)
        ->and(Message::query()->where('kind', MessageKind::LifeSafety->value)->sole()->status)->toBe(MessageStatus::Failed);
});

it('still sends the safety line when no business folder is live yet', function () {
    User::factory()->withPhone(LIFE_SAFETY_OWNER_PHONE)->create();

    $this->post(route('webhooks.text.fake'), fakeTextPayload(['Body' => 'I smell gas in the basement']))->assertOk();

    expect(lifeSafetyTextsToCustomer())->toHaveCount(1)
        ->and(lifeSafetyTextsToCustomer()[0]['body'])->toBe(LIFE_SAFETY_GAS_LINE)
        ->and(Conversation::query()->sole()->isPaused())->toBeTrue();
});

it('pauses and alerts the owner when the safety job fails for good (WR-05)', function () {
    User::factory()->withPhone(LIFE_SAFETY_OWNER_PHONE)->create();
    activateExampleFolder();

    $message = Message::factory()->create(['body' => 'I smell gas in the basement']);

    (new RespondToLifeSafety($message->id, 'gas'))->failed(new RuntimeException('worker died'));

    $alert = OwnerAlert::query()->sole();
    $conversation = $message->conversation->refresh();

    expect($conversation->isPaused())->toBeTrue()
        ->and($conversation->paused_reason)->toBe(PausedReason::LifeSafety)
        ->and($alert->reason)->toBe(AlertReason::LifeSafety)
        ->and($alert->urgent)->toBeTrue()
        ->and($alert->sent_at)->not->toBeNull()
        ->and($alert->body)->toContain('Safety message could not be sent')
        ->and($alert->body)->not->toContain('worker died')
        ->and(lifeSafetyTextsToOwner())->toHaveCount(1);
});

it('pauses and alerts the owner when the safety send fails with any error, not only a refusal', function () {
    $this->mock(OutboundSender::class, fn ($mock) => $mock->shouldReceive('send')->andThrow(new RuntimeException('database went away')));

    lifeSafetyCustomerTexts('I smell gas in the basement');

    $alert = OwnerAlert::query()->sole();
    $conversation = Conversation::query()->sole();

    expect($alert->reason)->toBe(AlertReason::LifeSafety)
        ->and($alert->body)->toContain('Safety message could not be sent')
        ->and($conversation->isPaused())->toBeTrue()
        ->and($conversation->paused_reason)->toBe(PausedReason::LifeSafety);
});

it('pauses and alerts the owner when another run holds the safety lock past the wait', function () {
    User::factory()->withPhone(LIFE_SAFETY_OWNER_PHONE)->create();
    activateExampleFolder();
    config(['gate.life_safety.lock_wait_seconds' => 0]);

    $message = Message::factory()->create(['body' => 'I smell gas in the basement']);
    $heldLock = Cache::lock("life-safety:{$message->id}", 30);
    $heldLock->get();

    $result = app(SendLifeSafetyLine::class)->handle($message, 'gas', 'keyword');

    $alert = OwnerAlert::query()->sole();
    $conversation = $message->conversation->refresh();

    expect($result)->toBeNull()
        ->and($conversation->isPaused())->toBeTrue()
        ->and($conversation->paused_reason)->toBe(PausedReason::LifeSafety)
        ->and($alert->reason)->toBe(AlertReason::LifeSafety)
        ->and($alert->body)->toContain('check the safety message went out');

    $heldLock->release();
});

it('pauses and alerts on the retry when the pause failed once after the safety line had already gone out (CR-01)', function () {
    User::factory()->withPhone(LIFE_SAFETY_OWNER_PHONE)->create();
    activateExampleFolder();

    $message = Message::factory()->create(['body' => 'I smell gas in the basement']);
    $realPause = new PauseConversation;
    $pauseCalls = 0;

    $this->mock(PauseConversation::class, fn ($mock) => $mock->shouldReceive('handle')->andReturnUsing(
        function (Conversation $conversation, PausedReason $reason) use (&$pauseCalls, $realPause): void {
            $pauseCalls++;

            if ($pauseCalls === 1) {
                throw new RuntimeException('database went away');
            }

            $realPause->handle($conversation, $reason);
        },
    ));

    expect(fn () => app(SendLifeSafetyLine::class)->handle($message, 'gas', 'keyword'))->toThrow(RuntimeException::class);

    expect(Message::query()->where('kind', MessageKind::LifeSafety->value)->sole()->status)->toBe(MessageStatus::Sent)
        ->and($message->conversation->refresh()->isPaused())->toBeFalse()
        ->and(OwnerAlert::query()->count())->toBe(0);

    $retry = app(SendLifeSafetyLine::class)->handle($message, 'gas', 'keyword');

    $alert = OwnerAlert::query()->sole();

    expect($retry)->toBeNull()
        ->and($message->conversation->refresh()->isPaused())->toBeTrue()
        ->and($message->conversation->paused_reason)->toBe(PausedReason::LifeSafety)
        ->and($alert->reason)->toBe(AlertReason::LifeSafety)
        ->and($alert->urgent)->toBeTrue()
        ->and($alert->body)->toContain('Safety message sent')
        ->and(Message::query()->where('kind', MessageKind::LifeSafety->value)->count())->toBe(1)
        ->and(lifeSafetyTextsToOwner())->toHaveCount(1);
});

it('does not pause again on a repeat run once the pause and the alert are both done, so a hand back stands (CR-01)', function () {
    User::factory()->withPhone(LIFE_SAFETY_OWNER_PHONE)->create();
    activateExampleFolder();

    $message = Message::factory()->create(['body' => 'I smell gas in the basement']);

    app(SendLifeSafetyLine::class)->handle($message, 'gas', 'keyword');

    $conversation = $message->conversation->refresh();
    $conversation->update(['paused_at' => null, 'paused_reason' => null, 'paused_by' => null]);

    app(SendLifeSafetyLine::class)->handle($message, 'gas', 'keyword');

    expect($conversation->refresh()->isPaused())->toBeFalse()
        ->and(OwnerAlert::query()->count())->toBe(1)
        ->and(Message::query()->where('kind', MessageKind::LifeSafety->value)->count())->toBe(1);
});

it('sends no holding reply when the classifier found a danger and the safety line did not go out', function () {
    $this->mock(SendLifeSafetyLine::class, fn ($mock) => $mock->shouldReceive('handle')->andReturnNull());

    lifeSafetyCustomerTexts('my thermostat smells funny');
    scriptFrontDeskReplies(['reply' => 'What is the system doing?', 'label' => 'intake_question']);
    fakeClassifier()->answer('faq', 0.95, ['life_safety' => 0.2]);

    runQueuedFrontDeskJobs();

    $draft = Message::query()->where('status', MessageStatus::Draft->value)->sole();

    expect($draft->gate_reason)->toBe('classifier:life_safety')
        ->and(Message::query()->where('kind', MessageKind::HoldingReply->value)->count())->toBe(0)
        ->and(lifeSafetyTextsToCustomer())->toBeEmpty();
});

/**
 * A web form posted to POST /leads by the example customer, with a message of the test's choosing.
 */
function lifeSafetyFormPosts(string $message): void
{
    test()->postJson(route('leads.store'), [
        'name' => 'Dana Whitfield',
        'phone' => '(202) 555-0123',
        'email' => 'dana@example.com',
        'message' => $message,
        'source' => 'angi',
    ])->assertOk();
}

it('alerts the owner at once, and texts the lead nothing, when a web form describes a danger (WR-07, D-41)', function () {
    activateExampleFolder();
    test()->travelTo(Carbon::parse('2026-10-13 21:00', 'America/Chicago'));
    User::factory()->withPhone(LIFE_SAFETY_OWNER_PHONE)->create();
    scriptFrontDeskReplies(['reply' => 'unused']);

    lifeSafetyFormPosts('I smell gas in the basement');
    runQueuedFrontDeskJobs();

    $alert = OwnerAlert::query()->sole();

    FrontDeskAgent::assertNeverPrompted();

    expect($alert->reason)->toBe(AlertReason::LifeSafety)
        ->and($alert->urgent)->toBeTrue()
        ->and($alert->sent_at)->not->toBeNull()
        ->and($alert->body)->toContain('Web form describes a danger')
        ->and(lifeSafetyTextsToOwner())->toHaveCount(1)
        ->and(lifeSafetyTextsToCustomer())->toBeEmpty()
        ->and(Message::query()->where('direction', 'out')->count())->toBe(0);
});

it('alerts no one for a web form with no danger', function () {
    activateExampleFolder();
    User::factory()->withPhone(LIFE_SAFETY_OWNER_PHONE)->create();
    scriptFrontDeskReplies(['reply' => 'unused']);

    lifeSafetyFormPosts('Need AC looked at this week');
    runQueuedFrontDeskJobs();

    expect(OwnerAlert::query()->count())->toBe(0)
        ->and(textsSentToCustomers())->toBeEmpty();
});

it('alerts for a dangerous form that shares a burst with a customer text, and still answers the text', function () {
    test()->travelTo(Carbon::parse('2026-10-13 10:00', 'America/Chicago'));
    User::factory()->withPhone(LIFE_SAFETY_OWNER_PHONE)->create();
    scriptFrontDeskReplies(['reply' => 'What is the system doing?', 'label' => 'intake_question']);

    frontDeskCustomerTexts('My AC is blowing warm air');
    lifeSafetyFormPosts('I smell gas in the basement');
    runQueuedFrontDeskJobs();

    $form = Message::query()->where('channel', MessageChannel::Web->value)->sole();
    $alert = OwnerAlert::query()->where('reason', AlertReason::LifeSafety->value)->sole();

    FrontDeskAgent::assertPromptedTimes(1);

    expect($alert->message_id)->toBe($form->id)
        ->and($alert->body)->toContain('Web form describes a danger')
        ->and(lifeSafetyTextsToCustomer())->toHaveCount(1)
        ->and(lifeSafetyTextsToCustomer()[0]['body'])->toBe(composedFirstReply('What is the system doing?'));
});
