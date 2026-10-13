<?php

use App\Enums\DraftResolution;
use App\Enums\GateOutcome;
use App\Enums\MessageAuthor;
use App\Enums\MessageKind;
use App\Enums\MessageStatus;
use App\Icm\ActiveConfig;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Carbon;

const HOLDING_REPLY_OWNER_PHONE = '+12025550199';

const HOLDING_REPLY_CUSTOMER_PHONE = '+12025550123';

/**
 * Tuesday 2026-10-13 at the given business-zone time, the owner with a phone, and Dana's price question answered
 * by a held draft.
 */
function holdingReplyPriceQuestionHeld(string $at = '2026-10-13 16:30'): void
{
    test()->travelTo(Carbon::parse($at, 'America/Chicago'));

    User::factory()->withPhone(HOLDING_REPLY_OWNER_PHONE)->create();

    frontDeskCustomerTexts('How much for a new unit?');
    scriptFrontDeskReplies(['reply' => 'Mike will text you a price for a new unit.', 'label' => 'price']);

    runQueuedFrontDeskJobs();
}

/**
 * Dana texts again and the front desk answers that burst with the given script.
 *
 * @param  array<string, mixed>  $reply
 */
function holdingReplyCustomerTextsAgain(array $reply = ['reply' => 'Mike will text you about both.', 'label' => 'price']): void
{
    static $count = 0;
    $count++;

    test()->post(route('webhooks.text.fake'), fakeTextPayload([
        'MessageSid' => 'SM'.str_pad((string) $count, 32, 'f', STR_PAD_LEFT),
        'Body' => "Following up, text number {$count}",
    ]))->assertOk();
    scriptFrontDeskReplies($reply);

    runQueuedFrontDeskJobs();
}

/**
 * The texts that reached Dana's phone, leaving out the alerts to the owner.
 *
 * @return list<array{to: string, body: string, provider_message_id: string}>
 */
function holdingReplyTextsToDana(): array
{
    return array_values(array_filter(
        textsSentToCustomers(),
        fn (array $text): bool => $text['to'] === HOLDING_REPLY_CUSTOMER_PHONE,
    ));
}

/**
 * The holding replies stored so far, oldest first.
 *
 * @return list<Message>
 */
function holdingReplyMessages(): array
{
    return Message::query()->where('kind', MessageKind::HoldingReply->value)->orderBy('id')->get()->all();
}

it('texts the customer a holding reply that says when the owner will answer, with the disclosure and sign-off (D-12, D-13, D-50)', function () {
    holdingReplyPriceQuestionHeld('2026-10-13 16:30');

    $words = "Thanks! Mike answers questions like this himself. I've sent him your message, and he'll get back to you when we open at 8 am tomorrow.";
    $voice = app(ActiveConfig::class)->voice();
    $holding = holdingReplyMessages();

    expect(holdingReplyTextsToDana())->toHaveCount(1)
        ->and(holdingReplyTextsToDana()[0]['body'])->toBe($voice['ai_disclosure']."\n\n".$words."\n".$voice['sign_off'])
        ->and($holding)->toHaveCount(1)
        ->and($holding[0]->author)->toBe(MessageAuthor::Agent)
        ->and($holding[0]->status)->toBe(MessageStatus::Sent)
        ->and($holding[0]->gate_outcome)->toBe(GateOutcome::Send)
        ->and($holding[0]->gate_reason)->toBe('fixed_reply')
        ->and($holding[0]->gate['reason']['code'])->toBe('fixed_reply');
});

it('does not ask Jev about the holding reply, because code wrote it (D-12)', function () {
    holdingReplyPriceQuestionHeld();

    expect(holdingReplyMessages())->toHaveCount(1)
        ->and(fakeClassifier()->calls())->toBe([]);
});

it('says today while the shop is open and leaves the disclosure out when an agent text already went out (D-13, D-50)', function () {
    test()->travelTo(Carbon::parse('2026-10-13 10:00', 'America/Chicago'));
    User::factory()->withPhone(HOLDING_REPLY_OWNER_PHONE)->create();

    frontDeskCustomerTexts();
    scriptFrontDeskReplies(['reply' => 'What is the system doing?', 'label' => 'intake_question']);
    runQueuedFrontDeskJobs();

    holdingReplyCustomerTextsAgain();

    $voice = app(ActiveConfig::class)->voice();
    $texts = holdingReplyTextsToDana();
    $body = $texts[1]['body'] ?? '';

    expect($texts)->toHaveCount(2)
        ->and($body)->toEndWith("he'll get back to you today.\n".$voice['sign_off'])
        ->and($body)->not->toContain($voice['ai_disclosure']);
});

it('raises the owner alert before the holding reply goes to the customer', function () {
    holdingReplyPriceQuestionHeld();

    $order = array_map(fn (array $text): string => $text['to'], textsSentToCustomers());

    expect($order)->toBe([HOLDING_REPLY_OWNER_PHONE, HOLDING_REPLY_CUSTOMER_PHONE]);
});

it('sends no second holding reply when a newer text replaces the waiting draft (D-14)', function () {
    holdingReplyPriceQuestionHeld();
    holdingReplyCustomerTextsAgain();

    expect(holdingReplyMessages())->toHaveCount(1)
        ->and(holdingReplyTextsToDana())->toHaveCount(1)
        ->and(Message::query()->where('status', MessageStatus::Draft->value)->count())->toBe(1);
});

it('sends a new holding reply after a real answer went out (D-14)', function () {
    holdingReplyPriceQuestionHeld();

    Message::factory()->for(Conversation::query()->sole())->fromOwner()->create(['body' => 'It is $9,000 installed.']);

    holdingReplyCustomerTextsAgain();

    expect(holdingReplyMessages())->toHaveCount(2)
        ->and(holdingReplyTextsToDana())->toHaveCount(2);
});

it('sends no third holding reply while the newest one is still unanswered (D-14)', function () {
    holdingReplyPriceQuestionHeld();

    Message::factory()->for(Conversation::query()->sole())->fromOwner()->create(['body' => 'It is $9,000 installed.']);

    test()->travel(5)->minutes();
    holdingReplyCustomerTextsAgain();

    test()->travel(5)->minutes();
    holdingReplyCustomerTextsAgain();

    expect(holdingReplyMessages())->toHaveCount(2)
        ->and(holdingReplyTextsToDana())->toHaveCount(2);
});

it('sends a new holding reply after the owner discarded the waiting draft (D-14)', function () {
    holdingReplyPriceQuestionHeld();

    Message::query()->where('status', MessageStatus::Draft->value)->sole()->update([
        'status' => MessageStatus::Withdrawn,
        'resolution' => DraftResolution::Discarded,
        'resolved_at' => now(),
    ]);

    holdingReplyCustomerTextsAgain();

    expect(holdingReplyMessages())->toHaveCount(2)
        ->and(holdingReplyTextsToDana())->toHaveCount(2);
});

it('sends no holding reply for a reply that goes out on its own', function () {
    test()->travelTo(Carbon::parse('2026-10-13 10:00', 'America/Chicago'));

    frontDeskCustomerTexts();
    scriptFrontDeskReplies(['reply' => 'What is the system doing?', 'label' => 'intake_question']);
    runQueuedFrontDeskJobs();

    expect(holdingReplyMessages())->toBe([])
        ->and(holdingReplyTextsToDana())->toHaveCount(1);
});

it('still sends the holding reply when the draft is held because the safety check was unsure (flagged assumption)', function () {
    test()->travelTo(Carbon::parse('2026-10-13 10:00', 'America/Chicago'));

    frontDeskCustomerTexts();
    scriptFrontDeskReplies(['reply' => 'We are open until 5 pm today.', 'label' => 'faq_answer']);
    fakeClassifier()->answer('faq', 0.84);
    runQueuedFrontDeskJobs();

    expect(holdingReplyMessages())->toHaveCount(1)
        ->and(Message::query()->where('gate_reason', 'classifier_unsure:intent')->sole()->status)->toBe(MessageStatus::Draft);
});
