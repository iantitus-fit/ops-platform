<?php

use App\Ai\Agents\FrontDeskAgent;
use App\Enums\AlertReason;
use App\Enums\MessageAuthor;
use App\Enums\MessageKind;
use App\Enums\MessageStatus;
use App\Enums\PausedReason;
use App\Icm\ActiveConfig;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\OwnerAlert;
use App\Models\User;
use Illuminate\Support\Carbon;

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
