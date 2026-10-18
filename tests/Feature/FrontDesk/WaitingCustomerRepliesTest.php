<?php

use App\Enums\AlertReason;
use App\Enums\GateOutcome;
use App\Enums\MessageKind;
use App\Enums\MessageStatus;
use App\Icm\ActiveConfig;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\OwnerAlert;
use App\Models\User;
use App\SendRule\HeldReasons;
use Illuminate\Support\Carbon;

const WAITING_REPLIES_OWNER_PHONE = '+12025550199';

const WAITING_REPLIES_CUSTOMER_PHONE = '+12025550123';

const WAITING_REPLIES_CHECK_IN_LINE = "Thanks for checking in. Mike still has your question and you're on his list. He'll get back to you today if possible, or after we open at 8 am tomorrow.";

const WAITING_REPLIES_COMPLAINT_LINE = "I'm sorry about this. I've passed your message straight to Mike, and he'll get back to you today if possible, or after we open at 8 am tomorrow.";

const WAITING_REPLIES_PARTIAL_LINE = "I've passed your other question to Mike, and he'll follow up on it.";

const WAITING_REPLIES_ADDED_INFO_LINE = "Got it, I've passed that along to Mike with your question.";

const WAITING_REPLIES_AC_SENTENCE = 'AC repair starts with an $89 diagnostic visit, and we take the $89 off if you go ahead with the repair that visit.';

/**
 * The fixed lines the owner wrote, each by the words that open it, so a test can count how many ride in one text.
 *
 * @var list<string>
 */
const WAITING_REPLIES_LINE_OPENINGS = [
    "Thanks. I've passed this to Mike",
    "I'm sorry about this.",
    'Thanks for checking in.',
    "I've passed your other question to Mike",
    "Got it, I've passed that along to Mike",
    'We like to take care of our customers.',
];

/**
 * A question held as a draft W at the given time in the shop's zone, with the owner's phone on file, the customer's
 * holding reply sent and one draft alert raised. A price question gives a price draft; scheduling gives a booking draft
 * whose words promise a day.
 */
function waitingRepliesDraftHeld(string $kind = 'price', string $at = '2026-10-13 10:00'): void
{
    test()->travelTo(Carbon::parse($at, 'America/Chicago'));

    User::factory()->withPhone(WAITING_REPLIES_OWNER_PHONE)->create();

    if ($kind === 'scheduling') {
        frontDeskCustomerTexts('Can you come out Thursday morning?');
        scriptFrontDeskReplies(['reply' => 'We guarantee 9 am Thursday. Does that work?', 'label' => 'scheduling']);
    } else {
        frontDeskCustomerTexts('How much for a new unit?');
        scriptFrontDeskReplies(['reply' => 'Mike will text you a price for a new unit.', 'label' => 'price']);
    }

    runQueuedFrontDeskJobs();
}

/**
 * Dana texts again a minute later and the front desk answers that burst with the given script.
 *
 * @param  array<string, mixed>  $reply
 */
function waitingRepliesCustomerTexts(array $reply, string $body = 'Following up on my question'): void
{
    static $count = 0;
    $count++;

    test()->travel(1)->minutes();

    test()->post(route('webhooks.text.fake'), fakeTextPayload([
        'MessageSid' => 'SM'.str_pad((string) $count, 32, 'd', STR_PAD_LEFT),
        'Body' => $body,
    ]))->assertOk();
    scriptFrontDeskReplies($reply);

    runQueuedFrontDeskJobs();
}

/**
 * The texts that reached Dana's phone, leaving out the alerts to the owner.
 *
 * @return list<array{to: string, body: string, provider_message_id: string}>
 */
function waitingRepliesTextsToDana(): array
{
    return array_values(array_filter(
        textsSentToCustomers(),
        fn (array $text): bool => $text['to'] === WAITING_REPLIES_CUSTOMER_PHONE,
    ));
}

/**
 * The words as Dana receives them after the first automated text: the sign-off closes them, the disclosure is left out.
 */
function waitingRepliesLater(string $words): string
{
    return $words."\n".app(ActiveConfig::class)->voice()['sign_off'];
}

/**
 * The one draft that is live now.
 */
function waitingRepliesLiveDraft(): Message
{
    return Message::query()->where('status', MessageStatus::Draft->value)->sole();
}

/**
 * How many of the owner's fixed lines one text holds.
 */
function waitingRepliesLinesIn(string $body): int
{
    return count(array_filter(WAITING_REPLIES_LINE_OPENINGS, fn (string $opening): bool => str_contains($body, $opening)));
}

/**
 * @return list<OwnerAlert>
 */
function waitingRepliesCheckInAlerts(): array
{
    return OwnerAlert::query()->where('reason', AlertReason::CheckIn->value)->orderBy('id')->get()->all();
}

it('answers a check-in with the check-in reply and an alert, and leaves the waiting draft untouched (D-28, D-38)', function (?bool $touches) {
    waitingRepliesDraftHeld();

    $waiting = waitingRepliesLiveDraft();

    $script = ['reply' => 'Mike still has your question.', 'label' => 'other', 'waiting_note' => 'check_in', 'touches_waiting' => $touches];

    waitingRepliesCustomerTexts($script, 'Just checking in, have you heard anything?');

    $checkIn = Message::query()->where('kind', MessageKind::CheckInReply->value)->sole();
    $newest = Message::query()->where('direction', 'in')->orderByDesc('id')->first();

    expect(waitingRepliesTextsToDana())->toHaveCount(2)
        ->and(waitingRepliesTextsToDana()[1]['body'])->toBe(waitingRepliesLater(WAITING_REPLIES_CHECK_IN_LINE))
        ->and($checkIn->status)->toBe(MessageStatus::Sent)
        ->and($checkIn->meta['parts'])->toBe(['check_in_line'])
        ->and(waitingRepliesCheckInAlerts())->toHaveCount(1)
        ->and(waitingRepliesCheckInAlerts()[0]->message_id)->toBe($newest->id)
        ->and(waitingRepliesLiveDraft()->id)->toBe($waiting->id)
        ->and(waitingRepliesLiveDraft()->body)->toBe($waiting->body)
        ->and(waitingRepliesLiveDraft()->gate_reason)->toBe($waiting->gate_reason)
        ->and(Message::query()->where('body', 'like', '%Mike still has your question.%')->count())->toBe(0);

    waitingRepliesCustomerTexts($script, 'Hello? Anyone there?');

    expect(Message::query()->where('kind', MessageKind::CheckInReply->value)->count())->toBe(2)
        ->and(waitingRepliesTextsToDana())->toHaveCount(3)
        ->and(waitingRepliesCheckInAlerts())->toHaveCount(2)
        ->and(waitingRepliesLiveDraft()->id)->toBe($waiting->id)
        ->and(waitingRepliesLiveDraft()->body)->toBe($waiting->body)
        ->and(Message::query()->where('body', 'like', '%Mike still has your question.%')->count())->toBe(0);
})->with([
    'touches_waiting true' => [true],
    'touches_waiting missing' => [null],
]);

it('answers a check-in under a scheduling draft with the check-in reply when the guard holds its words (D-28, D-20)', function () {
    waitingRepliesDraftHeld('scheduling');

    $waiting = waitingRepliesLiveDraft();

    waitingRepliesCustomerTexts([
        'reply' => 'We have 9 am Friday open. Does that work?',
        'label' => 'scheduling',
        'touches_waiting' => false,
        'waiting_note' => 'check_in',
    ], 'Just checking in');

    expect(waitingRepliesLiveDraft()->id)->toBe($waiting->id)
        ->and(waitingRepliesLiveDraft()->body)->toBe($waiting->body)
        ->and(waitingRepliesTextsToDana())->toHaveCount(2)
        ->and(waitingRepliesTextsToDana()[1]['body'])->toBe(waitingRepliesLater(WAITING_REPLIES_CHECK_IN_LINE))
        ->and(Message::query()->where('body', 'like', '%9 am Friday%')->count())->toBe(0)
        ->and(waitingRepliesCheckInAlerts())->toHaveCount(1);
});

it('sends the answer and then the check-in reply as one text when a check-in rides with an easy question (D-36)', function () {
    waitingRepliesDraftHeld();

    $waiting = waitingRepliesLiveDraft();

    waitingRepliesCustomerTexts([
        'reply' => 'Yes, we service heat pumps.',
        'label' => 'faq_answer',
        'touches_waiting' => false,
        'waiting_note' => 'check_in',
    ], 'Any news? Also, do you service heat pumps?');

    $sent = Message::query()->where('kind', MessageKind::AgentReply->value)->where('status', MessageStatus::Sent->value)->sole();

    expect(waitingRepliesTextsToDana())->toHaveCount(2)
        ->and(waitingRepliesTextsToDana()[1]['body'])->toBe(waitingRepliesLater('Yes, we service heat pumps. '.WAITING_REPLIES_CHECK_IN_LINE))
        ->and($sent->meta['parts'])->toBe(['model_words', 'check_in_line'])
        ->and($sent->gate_outcome)->toBe(GateOutcome::Send)
        ->and(waitingRepliesCheckInAlerts())->toHaveCount(1)
        ->and(waitingRepliesLiveDraft()->id)->toBe($waiting->id);
});

it('answers the first complaint in a wait with the complaint reply, and a later complaint that checks in with the check-in reply and only the complaint alert (D-34, G-02.1-1)', function () {
    waitingRepliesDraftHeld();

    $waiting = waitingRepliesLiveDraft();

    waitingRepliesCustomerTexts(['reply' => 'We are sorry to hear that.', 'label' => 'complaint', 'touches_waiting' => true], 'The technician left a mess');

    $draft = waitingRepliesLiveDraft();

    expect($draft->id)->not->toBe($waiting->id)
        ->and($draft->gate_reason)->toBe('label:complaint')
        ->and($draft->body)->toBe('We are sorry to hear that.')
        ->and(waitingRepliesTextsToDana())->toHaveCount(2)
        ->and(waitingRepliesTextsToDana()[1]['body'])->toBe(waitingRepliesLater(WAITING_REPLIES_COMPLAINT_LINE))
        ->and(Message::query()->where('kind', MessageKind::ComplaintHolding->value)->count())->toBe(1);

    $complaintDraft = $draft;

    waitingRepliesCustomerTexts(['reply' => 'We are sorry to hear that.', 'label' => 'complaint', 'touches_waiting' => true, 'waiting_note' => 'check_in'], 'Hello? Did you see my message?');

    expect(waitingRepliesLiveDraft()->id)->not->toBe($complaintDraft->id)
        ->and(waitingRepliesLiveDraft()->gate_reason)->toBe('label:complaint')
        ->and(waitingRepliesLiveDraft()->body)->toBe($complaintDraft->body)
        ->and(waitingRepliesTextsToDana())->toHaveCount(3)
        ->and(waitingRepliesTextsToDana()[2]['body'])->toBe(waitingRepliesLater(WAITING_REPLIES_CHECK_IN_LINE))
        ->and(Message::query()->where('kind', MessageKind::CheckInReply->value)->count())->toBe(1)
        ->and(Message::query()->where('kind', MessageKind::ComplaintHolding->value)->count())->toBe(1)
        ->and(waitingRepliesCheckInAlerts())->toBe([])
        ->and(OwnerAlert::query()->where('reason', AlertReason::Complaint->value)->count())->toBe(1);
});

it('gives a later complaint in the same wait the next line in D-34\'s order, never a second complaint reply (G-02.1-1)', function (bool $jevReadsComplaint, array $script, string $body, string $line, MessageKind $kind, string $reason) {
    waitingRepliesDraftHeld();

    waitingRepliesCustomerTexts(['reply' => 'We are sorry to hear that.', 'label' => 'complaint', 'touches_waiting' => true], 'The technician left a mess');

    expect(Message::query()->where('kind', MessageKind::ComplaintHolding->value)->count())->toBe(1);

    if ($jevReadsComplaint) {
        fakeClassifier()->answerReading('complaint', 0.95, ['complaint' => 0.9]);
    }

    waitingRepliesCustomerTexts($script, $body);

    $other = $kind === MessageKind::CheckInReply ? MessageKind::AddedInfoReply : MessageKind::CheckInReply;

    expect(waitingRepliesTextsToDana())->toHaveCount(3)
        ->and(waitingRepliesTextsToDana()[2]['body'])->toBe(waitingRepliesLater($line))
        ->and(Message::query()->where('kind', $kind->value)->count())->toBe(1)
        ->and(Message::query()->where('kind', $other->value)->count())->toBe(0)
        ->and(Message::query()->where('kind', MessageKind::ComplaintHolding->value)->count())->toBe(1)
        ->and(Message::query()->where('kind', MessageKind::HoldingReply->value)->count())->toBe(1)
        ->and(waitingRepliesLiveDraft()->gate_reason)->toBe($reason)
        ->and(waitingRepliesCheckInAlerts())->toBe([])
        ->and(OwnerAlert::query()->where('reason', AlertReason::Complaint->value)->exists())->toBeTrue();
})->with([
    'a check-in Jev reads as a complaint' => [true, ['reply' => 'Mike still has your question.', 'label' => 'other', 'touches_waiting' => true, 'waiting_note' => 'check_in'], 'Hello? Is anyone going to answer me?', WAITING_REPLIES_CHECK_IN_LINE, MessageKind::CheckInReply, 'classifier:complaint'],
    'an added detail the agent labels a complaint' => [false, ['reply' => 'We are sorry, we will pass that on.', 'label' => 'complaint', 'touches_waiting' => true], 'He also scratched my floor.', WAITING_REPLIES_ADDED_INFO_LINE, MessageKind::AddedInfoReply, 'label:complaint'],
    'an added detail Jev reads as a complaint' => [true, ['reply' => 'Thanks, I will pass that on.', 'label' => 'intake_question', 'touches_waiting' => true], 'And he tracked mud on the carpet.', WAITING_REPLIES_ADDED_INFO_LINE, MessageKind::AddedInfoReply, 'classifier:complaint'],
]);

it('sends a passing easy answer with the partial holding line when a later complaint in the same wait asks it, never a second complaint reply (D-36, G-02.1-1)', function () {
    waitingRepliesDraftHeld();

    waitingRepliesCustomerTexts(['reply' => 'We are sorry to hear that.', 'label' => 'complaint', 'touches_waiting' => true], 'The technician left a mess');

    waitingRepliesCustomerTexts([
        'reply' => 'We are sorry, we will pass that on.',
        'label' => 'complaint',
        'touches_waiting' => true,
        'easy_reply' => 'Yes, we service heat pumps.',
        'easy_label' => 'faq_answer',
    ], 'He also scratched my floor. Do you service heat pumps?');

    $sent = Message::query()->where('kind', MessageKind::PartialHolding->value)->sole();

    expect(waitingRepliesTextsToDana())->toHaveCount(3)
        ->and(waitingRepliesTextsToDana()[2]['body'])->toBe(waitingRepliesLater('Yes, we service heat pumps. '.WAITING_REPLIES_PARTIAL_LINE))
        ->and($sent->meta['parts'])->toBe(['model_words', 'partial_holding_line'])
        ->and($sent->gate_outcome)->toBe(GateOutcome::Send)
        ->and(Message::query()->where('kind', MessageKind::ComplaintHolding->value)->count())->toBe(1)
        ->and(Message::query()->where('kind', MessageKind::AddedInfoReply->value)->count())->toBe(0)
        ->and(waitingRepliesLiveDraft()->gate_reason)->toBe('label:complaint')
        ->and(waitingRepliesCheckInAlerts())->toBe([])
        ->and(OwnerAlert::query()->where('reason', AlertReason::Complaint->value)->count())->toBe(1);
});

it('gives the complaint reply again once the owner has answered and a new wait starts (G-02.1-1)', function () {
    waitingRepliesDraftHeld();

    waitingRepliesCustomerTexts(['reply' => 'We are sorry to hear that.', 'label' => 'complaint', 'touches_waiting' => true], 'The technician left a mess');

    Message::factory()->for(Conversation::query()->sole())->fromOwner()->create(['body' => 'Sorry about that, I will call you this afternoon.']);

    waitingRepliesCustomerTexts(['reply' => 'We are sorry to hear that.', 'label' => 'complaint', 'touches_waiting' => true], 'The AC is still blowing warm air and nobody came back.');

    expect(waitingRepliesTextsToDana())->toHaveCount(3)
        ->and(waitingRepliesTextsToDana()[2]['body'])->toBe(waitingRepliesLater(WAITING_REPLIES_COMPLAINT_LINE))
        ->and(Message::query()->where('kind', MessageKind::ComplaintHolding->value)->count())->toBe(2)
        ->and(Message::query()->where('kind', MessageKind::AddedInfoReply->value)->count())->toBe(0);
});

it('keeps the waiting draft body when a check-in whose words are held for their own reason replaces it (D-28)', function () {
    waitingRepliesDraftHeld();

    $waiting = waitingRepliesLiveDraft();

    waitingRepliesCustomerTexts(['reply' => 'Mike still has your question.', 'label' => 'complaint', 'touches_waiting' => true, 'waiting_note' => 'check_in'], 'This is taking forever');

    expect(waitingRepliesLiveDraft()->id)->not->toBe($waiting->id)
        ->and(waitingRepliesLiveDraft()->body)->toBe($waiting->body)
        ->and(waitingRepliesLiveDraft()->gate_reason)->toBe('label:complaint')
        ->and(waitingRepliesCheckInAlerts())->toBe([])
        ->and(Message::query()->where('body', 'like', '%Mike still has your question.%')->count())->toBe(0);
});

it('stores the waiting draft\'s own evidence on the kept body and the check-in words\' verdict beside it (WR-07)', function () {
    waitingRepliesDraftHeld();

    $waiting = waitingRepliesLiveDraft();

    waitingRepliesCustomerTexts(['reply' => 'Mike will be there Friday, I promise.', 'label' => 'promise', 'touches_waiting' => true, 'waiting_note' => 'check_in'], 'Any update on my question?');

    $live = waitingRepliesLiveDraft();

    expect($live->id)->not->toBe($waiting->id)
        ->and($live->body)->toBe($waiting->body)
        ->and($live->label)->toBe($waiting->label)
        ->and($live->gate_reason)->toBe($waiting->gate_reason)
        ->and($live->gate['reason'])->toBe($waiting->gate['reason'])
        ->and($live->gate['inputs']['agent_reply'])->toBe($waiting->gate['inputs']['agent_reply'])
        ->and($live->gate['checks'])->toBe($waiting->gate['checks'])
        ->and($live->meta['check_in_reply_gate']['inputs']['agent_reply'])->toBe('Mike will be there Friday, I promise.')
        ->and($live->meta['check_in_reply_gate']['reason']['code'])->toBe('label:promise')
        ->and(HeldReasons::priceAlone($live))->toBe(HeldReasons::priceAlone($waiting))
        ->and(waitingRepliesTextsToDana()[1]['body'])->toBe(waitingRepliesLater(WAITING_REPLIES_CHECK_IN_LINE))
        ->and(waitingRepliesCheckInAlerts())->toHaveCount(1);
});

it('keeps the new reading and the complaint reason on the kept body when Jev reads the check-in as a complaint (WR-07)', function () {
    waitingRepliesDraftHeld();

    $waiting = waitingRepliesLiveDraft();
    fakeClassifier()->answerReading('complaint', 0.95, ['complaint' => 0.9]);

    waitingRepliesCustomerTexts(['reply' => 'Mike still has your question.', 'label' => 'other', 'touches_waiting' => true, 'waiting_note' => 'check_in'], 'Hello? This is ridiculous, nobody has called me back.');

    $live = waitingRepliesLiveDraft();

    expect($live->body)->toBe($waiting->body)
        ->and($live->gate_reason)->toBe('classifier:complaint')
        ->and($live->gate['inputs']['agent_reply'])->toBe($waiting->gate['inputs']['agent_reply'])
        ->and($live->gate['customer_reading'])->toBe($live->meta['check_in_reply_gate']['customer_reading'])
        ->and($live->gate['customer_reading'])->not->toBe($waiting->gate['customer_reading'])
        ->and($live->meta['check_in_reply_gate']['inputs']['agent_reply'])->toBe('Mike still has your question.')
        ->and(HeldReasons::priceAlone($live))->toBeFalse();
});

it('puts the owner sentence and then the partial holding line in one text when the rest is held (D-36)', function () {
    waitingRepliesDraftHeld();

    waitingRepliesCustomerTexts([
        'reply' => 'Mike will text you a price for a new unit.',
        'label' => 'price',
        'touches_waiting' => true,
        'price_services' => ['ac-repair'],
    ], 'And how much is the diagnostic for my AC?');

    expect(waitingRepliesTextsToDana())->toHaveCount(2)
        ->and(waitingRepliesTextsToDana()[1]['body'])->toBe(waitingRepliesLater(WAITING_REPLIES_AC_SENTENCE.' '.WAITING_REPLIES_PARTIAL_LINE))
        ->and(Message::query()->where('kind', MessageKind::PartialHolding->value)->count())->toBe(1);
});

it('gives the full holding reply again when the owner already ended the wait with their own text (flagged assumption)', function () {
    waitingRepliesDraftHeld();

    Message::factory()->for(Conversation::query()->sole())->fromOwner()->create(['body' => 'It is $9,000 installed.']);

    waitingRepliesCustomerTexts(['reply' => 'Mike will text you about both.', 'label' => 'price', 'touches_waiting' => true]);

    expect(Message::query()->where('kind', MessageKind::HoldingReply->value)->count())->toBe(2)
        ->and(Message::query()->where('kind', MessageKind::AddedInfoReply->value)->count())->toBe(0)
        ->and(waitingRepliesTextsToDana())->toHaveCount(2);
});

it('keeps the waiting draft words when a blank reply comes with a code hold, and reassures the customer (D-29)', function () {
    waitingRepliesDraftHeld('price', '2027-02-01 10:00');

    $waiting = waitingRepliesLiveDraft();

    waitingRepliesCustomerTexts(['reply' => '', 'label' => 'faq_answer', 'asks_discount' => true, 'touches_waiting' => true], 'Can I get a discount on that too?');

    expect(waitingRepliesLiveDraft()->id)->not->toBe($waiting->id)
        ->and(waitingRepliesLiveDraft()->body)->toBe($waiting->body)
        ->and(waitingRepliesLiveDraft()->gate_reason)->toBe('discount:no_live_offer')
        ->and(waitingRepliesTextsToDana())->toHaveCount(2)
        ->and(waitingRepliesTextsToDana()[1]['body'])->toBe(waitingRepliesLater(WAITING_REPLIES_ADDED_INFO_LINE));
});

it('pauses a customer who keeps checking in once the reply cap is reached (T-02.1-27)', function () {
    config(['gate.reply_cap.per_hour' => 3]);

    waitingRepliesDraftHeld();

    $checkIn = ['reply' => 'Mike still has your question.', 'label' => 'other', 'waiting_note' => 'check_in', 'touches_waiting' => true];

    waitingRepliesCustomerTexts($checkIn, 'Any news?');
    waitingRepliesCustomerTexts($checkIn, 'Any news now?');
    waitingRepliesCustomerTexts($checkIn, 'Hello?');

    expect(Message::query()->where('kind', MessageKind::CheckInReply->value)->count())->toBe(2)
        ->and(waitingRepliesTextsToDana())->toHaveCount(3)
        ->and(OwnerAlert::query()->where('reason', AlertReason::ReplyCap->value)->count())->toBe(1)
        ->and(Conversation::query()->sole()->isPaused())->toBeTrue();
});

it('sends the customer exactly one text, whatever they write while a draft waits (D-29)', function (string $draft, array $script) {
    waitingRepliesDraftHeld($draft);

    $before = count(waitingRepliesTextsToDana());

    waitingRepliesCustomerTexts($script, 'Something new from the customer');

    $texts = waitingRepliesTextsToDana();

    expect($texts)->toHaveCount($before + 1)
        ->and(Message::query()->where('status', MessageStatus::Draft->value)->count())->toBeLessThanOrEqual(1)
        ->and(waitingRepliesLinesIn($texts[$before]['body']))->toBeLessThanOrEqual(1);
})->with([
    'an easy answer that passes' => ['price', ['reply' => 'Yes, we service heat pumps.', 'label' => 'faq_answer', 'touches_waiting' => false]],
    'an easy answer that is held' => ['price', ['reply' => 'Mike will text you the price for that.', 'label' => 'price', 'touches_waiting' => false]],
    'a reply that touches the waiting question' => ['price', ['reply' => 'Mike will text you about both.', 'label' => 'price', 'touches_waiting' => true]],
    'a reply with no touches flag' => ['price', ['reply' => 'Mike will text you about both.', 'label' => 'price']],
    'a check-in' => ['price', ['reply' => 'Mike still has your question.', 'label' => 'other', 'touches_waiting' => true, 'waiting_note' => 'check_in']],
    'a complaint' => ['price', ['reply' => 'We are sorry to hear that.', 'label' => 'complaint', 'touches_waiting' => true]],
    'a blank reply with a sentence' => ['price', ['reply' => '', 'label' => 'faq_answer', 'price_services' => ['ac-repair']]],
    'a scheduling reply under a scheduling draft' => ['scheduling', ['reply' => 'We have 9 am Friday open. Does that work?', 'label' => 'scheduling', 'touches_waiting' => false]],
]);
