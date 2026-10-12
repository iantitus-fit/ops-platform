<?php

use App\Ai\Agents\FrontDeskAgent;
use App\Enums\AlertReason;
use App\Enums\GateOutcome;
use App\Enums\MessageKind;
use App\Enums\MessageStatus;
use App\Enums\PausedReason;
use App\Icm\ActiveConfig;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\OwnerAlert;
use App\Models\User;
use Illuminate\Support\Carbon;

const HANDOFF_OWNER_PHONE = '+12025550199';

const HANDOFF_CUSTOMER_PHONE = '+12025550123';

/**
 * Tuesday 2026-10-13 at 10:00 in the shop's zone, with the owner reachable by text and Dana's text arrived.
 */
function handoffDanaTexts(string $body): void
{
    test()->travelTo(Carbon::parse('2026-10-13 10:00', 'America/Chicago'));

    User::factory()->withPhone(HANDOFF_OWNER_PHONE)->create();

    frontDeskCustomerTexts($body);
}

/**
 * The texts that reached Dana's phone, leaving out the alerts to the owner.
 *
 * @return list<array{to: string, body: string, provider_message_id: string}>
 */
function handoffTextsToDana(): array
{
    return array_values(array_filter(
        textsSentToCustomers(),
        fn (array $text): bool => $text['to'] === HANDOFF_CUSTOMER_PHONE,
    ));
}

/**
 * The handoff reply as the customer first receives it: disclosure, the fixed words, then the sign-off.
 */
function handoffExpectedText(): string
{
    return composedFirstReply("Of course. I've let Mike know you'd like to talk with a person, and he'll reach out today.");
}

/**
 * Everything a handoff must have done, whichever way the request for a person was found.
 */
function handoffAssertHandedToOwner(): void
{
    $alert = OwnerAlert::query()->sole();
    $reply = Message::query()->where('kind', MessageKind::HandoffReply->value)->sole();
    $conversation = Conversation::query()->sole();

    expect($alert->reason)->toBe(AlertReason::AskedForPerson)
        ->and($alert->urgent)->toBeTrue()
        ->and($alert->sent_at)->not->toBeNull()
        ->and(handoffTextsToDana())->toHaveCount(1)
        ->and(handoffTextsToDana()[0]['body'])->toBe(handoffExpectedText())
        ->and($reply->body)->toBe(handoffExpectedText())
        ->and($reply->status)->toBe(MessageStatus::Sent)
        ->and($reply->gate_reason)->toBe('fixed_reply')
        ->and($conversation->isPaused())->toBeTrue()
        ->and($conversation->paused_reason)->toBe(PausedReason::AskedForPerson)
        ->and(Message::query()->where('status', MessageStatus::Draft->value)->exists())->toBeFalse();
}

it('hands a customer who asks for a real person to the owner without asking the agent (D-23, D-47, D-50)', function () {
    handoffDanaTexts('Can I talk to a real person?');
    scriptFrontDeskReplies(['reply' => 'I can help with that.']);

    runQueuedFrontDeskJobs();

    FrontDeskAgent::assertNeverPrompted();
    handoffAssertHandedToOwner();
});

it('hands over and drops the agent words when the agent says the customer wants a person', function () {
    handoffDanaTexts('Is Mike around today, I would rather not explain this twice');
    scriptFrontDeskReplies(['reply' => 'I can help with that myself.', 'label' => 'faq_answer', 'wants_person' => true]);

    runQueuedFrontDeskJobs();

    handoffAssertHandedToOwner();

    expect(handoffTextsToDana()[0]['body'])->not->toContain('I can help with that myself.');
});

it('hands over when Jev is sure the customer asks for a person (asks_for_person at 0.9)', function () {
    handoffDanaTexts('I would rather deal with the owner on this one');
    scriptFrontDeskReplies(['reply' => 'We are open until 5 pm today.', 'label' => 'faq_answer']);
    fakeClassifier()->answer('faq', 0.95, ['asks_for_person' => 0.9]);

    runQueuedFrontDeskJobs();

    handoffAssertHandedToOwner();

    expect(handoffTextsToDana()[0]['body'])->not->toContain('We are open until 5 pm today.');
});

it('holds the draft and does not pause when Jev is unsure the customer asks for a person (D-47)', function () {
    handoffDanaTexts('Maybe the owner could look at this');
    scriptFrontDeskReplies(['reply' => 'We are open until 5 pm today.', 'label' => 'faq_answer']);
    fakeClassifier()->answer('faq', 0.95, ['asks_for_person' => 0.5]);

    runQueuedFrontDeskJobs();

    $draft = Message::query()->where('status', MessageStatus::Draft->value)->sole();

    expect($draft->gate_outcome)->toBe(GateOutcome::Hold)
        ->and($draft->gate_reason)->toBe('classifier_unsure:asks_for_person')
        ->and(Message::query()->where('kind', MessageKind::HandoffReply->value)->exists())->toBeFalse()
        ->and(OwnerAlert::query()->where('reason', AlertReason::AskedForPerson->value)->exists())->toBeFalse()
        ->and(Conversation::query()->sole()->isPaused())->toBeFalse();
});

it('puts the disclosure only on the first automated text of the conversation (D-50)', function () {
    handoffDanaTexts('Can I speak to the owner?');
    $conversation = Conversation::query()->sole();
    Message::factory()->for($conversation)->outbound()->create(['body' => 'Earlier automated text']);
    scriptFrontDeskReplies(['reply' => 'unused']);

    runQueuedFrontDeskJobs();

    $voice = app(ActiveConfig::class)->voice();
    $body = Message::query()->where('kind', MessageKind::HandoffReply->value)->sole()->body;

    expect($body)->not->toContain($voice['ai_disclosure'])
        ->and($body)->toEndWith("\n".$voice['sign_off']);
});

it('never leaves a customer asking for a person without alerting the owner, for each way of asking', function (string $way) {
    handoffDanaTexts($way === 'phrase' ? 'I want to speak to a person' : 'Could the boss ring me back about this');
    scriptFrontDeskReplies(['reply' => 'Sure.', 'wants_person' => $way === 'agent']);

    runQueuedFrontDeskJobs();

    expect(OwnerAlert::query()->where('reason', AlertReason::AskedForPerson->value)->count())->toBe(1)
        ->and(Conversation::query()->sole()->isPaused())->toBeTrue();
})->with(['phrase', 'agent']);
