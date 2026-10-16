<?php

use App\Ai\AgentReply;
use App\Ai\Agents\FrontDeskAgent;
use App\Enums\ActivityType;
use App\Enums\AlertReason;
use App\Enums\GateOutcome;
use App\Enums\MessageAuthor;
use App\Enums\MessageKind;
use App\Enums\MessageStatus;
use App\Enums\PausedReason;
use App\Icm\ActiveConfig;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\OwnerAlert;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

const PRICE_SENTENCE_OWNER_PHONE = '+12025550199';

const PRICE_SENTENCE_CUSTOMER_PHONE = '+12025550123';

const PRICE_SENTENCE_PARTIAL_LINE = "I've passed your other question to Mike, and he'll follow up on it.";

const PRICE_SENTENCE_FURNACE = 'Furnace repair starts with an $89 diagnostic visit, and we take the $89 off if you go ahead with the repair that visit.';

const PRICE_SENTENCE_AC = 'AC repair starts with an $89 diagnostic visit, and we take the $89 off if you go ahead with the repair that visit.';

/**
 * Tuesday 2026-10-13 at 10:00 in the shop's zone, the owner with a phone, and Dana's text in the live example folder.
 */
function priceSentenceCustomerTexts(string $body): void
{
    test()->travelTo(Carbon::parse('2026-10-13 10:00', 'America/Chicago'));

    User::factory()->withPhone(PRICE_SENTENCE_OWNER_PHONE)->create();

    frontDeskCustomerTexts($body);
}

/**
 * Dana's text, one scripted model answer and the queued run.
 *
 * @param  array<string, mixed>  $reply
 */
function priceSentenceRun(string $body, array $reply): void
{
    priceSentenceCustomerTexts($body);
    scriptFrontDeskReplies($reply);
    runQueuedFrontDeskJobs();
}

/**
 * The texts that reached Dana's phone, leaving out the alerts to the owner.
 *
 * @return list<array{to: string, body: string, provider_message_id: string}>
 */
function priceSentenceTextsToDana(): array
{
    return array_values(array_filter(
        textsSentToCustomers(),
        fn (array $text): bool => $text['to'] === PRICE_SENTENCE_CUSTOMER_PHONE,
    ));
}

/**
 * A text as Dana reads it: the disclosure first on a first text, a blank line, the words, a new line and the sign-off.
 */
function priceSentenceFirstText(string $words): string
{
    return composedFirstReply($words);
}

it('sends the owner\'s unkeyed price sentence by code, without asking Jev (D-04)', function () {
    priceSentenceRun('How much is the diagnostic for my AC?', [
        'reply' => '',
        'label' => 'faq_answer',
        'service_slug' => 'ac-repair',
        'price_services' => ['ac-repair'],
    ]);

    $texts = priceSentenceTextsToDana();
    $message = Message::query()->where('kind', MessageKind::CodeAnswer->value)->sole();

    expect($texts)->toHaveCount(1)
        ->and($texts[0]['body'])->toBe(priceSentenceFirstText(PRICE_SENTENCE_AC))
        ->and($message->author)->toBe(MessageAuthor::Agent)
        ->and($message->status)->toBe(MessageStatus::Sent)
        ->and($message->gate_outcome)->toBe(GateOutcome::Send)
        ->and($message->kind->value)->toBe('code_answer')
        ->and($message->gate_reason)->toBe('fixed_reply')
        ->and($message->gate['fixed'])->toBeTrue()
        ->and($message->meta['parts'])->toBe(['code_sentences'])
        ->and($message->meta['price_sentences'])->toBe([['slug' => 'ac-repair', 'key' => 'unkeyed', 'amounts_cents' => [8900]]])
        ->and($message->meta['in_reply_to'])->toHaveCount(1);
});

it('does not ask Jev about a sentence code wrote, and holds nothing for the owner (D-04)', function () {
    priceSentenceRun('How much is the diagnostic for my AC?', [
        'reply' => '',
        'label' => 'faq_answer',
        'price_services' => ['ac-repair'],
    ]);

    expect(fakeClassifier()->calls())->toBe([])
        ->and(Message::query()->where('status', MessageStatus::Draft->value)->count())->toBe(0)
        ->and(OwnerAlert::query()->count())->toBe(0);
});

it('still rejects a blank reply from the model when it flags no price ask (D-04)', function () {
    expect(fn () => AgentReply::fromStructured(['reply' => '', 'label' => 'faq_answer', 'wants_person' => false, 'price_services' => []]))
        ->toThrow(UnexpectedValueException::class)
        ->and(fn () => AgentReply::fromStructured(['reply' => '  ', 'label' => 'faq_answer', 'wants_person' => false]))
        ->toThrow(UnexpectedValueException::class);
});

it('accepts a blank reply when the model names a service whose price was asked (D-04)', function () {
    $reply = AgentReply::fromStructured(['reply' => '', 'label' => 'faq_answer', 'wants_person' => false, 'price_services' => ['ac-repair']]);

    expect($reply->reply)->toBe('')
        ->and($reply->priceServices)->toBe(['ac-repair']);
});

it('uses the live voice file for the disclosure and sign-off around the sentence', function () {
    priceSentenceRun('How much is the diagnostic for my AC?', [
        'reply' => '',
        'price_services' => ['ac-repair'],
    ]);

    $voice = app(ActiveConfig::class)->voice();
    $body = priceSentenceTextsToDana()[0]['body'] ?? '';

    expect($body)->toStartWith($voice['ai_disclosure']."\n\n")
        ->and($body)->toEndWith("\n".$voice['sign_off']);
});

/**
 * Dana's text, the scripted model answer, a scripted Jev answer and the queued run.
 *
 * @param  array<string, mixed>  $reply
 * @param  array{0: string, 1?: float, 2?: array<string, float>}  $jev
 */
function priceSentenceRunWithJev(string $body, array $reply, array $jev): void
{
    priceSentenceCustomerTexts($body);
    scriptFrontDeskReplies($reply);
    fakeClassifier()->answer(...$jev);
    runQueuedFrontDeskJobs();
}

/**
 * Dana texts again and the front desk answers that burst with the given script.
 *
 * @param  array<string, mixed>  $reply
 */
function priceSentenceCustomerTextsAgain(array $reply): void
{
    static $count = 0;
    $count++;

    test()->post(route('webhooks.text.fake'), fakeTextPayload([
        'MessageSid' => 'SM'.str_pad((string) $count, 32, 'e', STR_PAD_LEFT),
        'Body' => "Following up, text number {$count}",
    ]))->assertOk();
    scriptFrontDeskReplies($reply);

    runQueuedFrontDeskJobs();
}

/**
 * The model's words, padded with letters so the whole text Dana reads (disclosure, sentence, words, sign-off) is
 * exactly the given number of characters long.
 */
function priceSentenceWordsForLength(int $length): string
{
    $voice = app(ActiveConfig::class)->voice();
    $words = 'Yes, we work on Carrier units. ';
    $without = mb_strlen($voice['ai_disclosure']."\n\n".PRICE_SENTENCE_AC.' '.$words."\n".$voice['sign_off']);

    return $words.str_repeat('x', $length - $without);
}

it('sends the sentence and the model\'s words that passed as one text, one sign-off (D-07)', function () {
    $words = 'Yes, we work on Carrier units.';

    priceSentenceRun('How much is the diagnostic, and do you work on Carrier units?', [
        'reply' => $words,
        'label' => 'faq_answer',
        'price_services' => ['ac-repair'],
    ]);

    $texts = priceSentenceTextsToDana();
    $message = Message::query()->where('direction', 'out')->where('author', MessageAuthor::Agent->value)->sole();

    expect($texts)->toHaveCount(1)
        ->and($texts[0]['body'])->toBe(priceSentenceFirstText(PRICE_SENTENCE_AC.' '.$words))
        ->and($message->kind)->toBe(MessageKind::AgentReply)
        ->and($message->meta['parts'])->toBe(['code_sentences', 'model_words'])
        ->and($message->meta['price_sentences'])->toHaveCount(1)
        ->and($message->gate_outcome)->toBe(GateOutcome::Send)
        ->and($message->gate['checks']['classifier']['ran'])->toBeTrue()
        ->and(fakeClassifier()->calls())->toHaveCount(1)
        ->and(fakeClassifier()->calls()[0]['draft_reply'])->toBe($words);
});

it('sends the sentence with the partial holding line when the model\'s words hold, and drafts the words (D-07, D-30)', function () {
    $words = 'Yes, we work on Carrier units.';

    priceSentenceRunWithJev('How much is the diagnostic, and do you work on Carrier units?', [
        'reply' => $words,
        'label' => 'faq_answer',
        'price_services' => ['ac-repair'],
    ], ['price_question', 0.95]);

    $texts = priceSentenceTextsToDana();
    $partial = Message::query()->where('kind', MessageKind::PartialHolding->value)->sole();
    $draft = Message::query()->where('status', MessageStatus::Draft->value)->sole();

    expect($texts)->toHaveCount(1)
        ->and($texts[0]['body'])->toBe(priceSentenceFirstText(PRICE_SENTENCE_AC.' '.PRICE_SENTENCE_PARTIAL_LINE))
        ->and($partial->meta['parts'])->toBe(['code_sentences', 'partial_holding_line'])
        ->and($partial->meta['price_sentences'])->toHaveCount(1)
        ->and($draft->body)->toBe($words)
        ->and($draft->gate_reason)->toBe('classifier_intent:price_question')
        ->and(OwnerAlert::query()->count())->toBe(1)
        ->and(Message::query()->where('kind', MessageKind::HoldingReply->value)->count())->toBe(0);
});

it('sends no full holding reply for a later held draft in the same wait (D-30)', function () {
    priceSentenceRunWithJev('How much is the diagnostic, and do you work on Carrier units?', [
        'reply' => 'Yes, we work on Carrier units.',
        'label' => 'faq_answer',
        'price_services' => ['ac-repair'],
    ], ['price_question', 0.95]);

    priceSentenceCustomerTextsAgain(['reply' => 'Mike will text you the price.', 'label' => 'price']);

    expect(Message::query()->where('kind', MessageKind::HoldingReply->value)->count())->toBe(0)
        ->and(Message::query()->where('kind', MessageKind::AddedInfoReply->value)->count())->toBe(1)
        ->and(priceSentenceTextsToDana())->toHaveCount(2)
        ->and(Message::query()->where('status', MessageStatus::Draft->value)->count())->toBe(1);
});

it('holds a price the model writes itself as code:price and still sends the sentence with the partial line (D-04)', function () {
    priceSentenceRun('How much is the diagnostic, and do you work on Carrier units?', [
        'reply' => "It's $89 and yes, we do Carrier.",
        'label' => 'faq_answer',
        'price_services' => ['ac-repair'],
    ]);

    $draft = Message::query()->where('status', MessageStatus::Draft->value)->sole();

    expect($draft->gate_reason)->toBe('code:price')
        ->and($draft->body)->toBe("It's $89 and yes, we do Carrier.")
        ->and(priceSentenceTextsToDana())->toHaveCount(1)
        ->and(priceSentenceTextsToDana()[0]['body'])->toBe(priceSentenceFirstText(PRICE_SENTENCE_AC.' '.PRICE_SENTENCE_PARTIAL_LINE))
        ->and(array_column(fakeClassifier()->calls(), 'draft_reply'))->toBe(["It's $89 and yes, we do Carrier."]);
});

it('sends both sentences in one text, in the order asked (D-08)', function () {
    priceSentenceRun('How much is the diagnostic for my AC and my furnace?', [
        'reply' => '',
        'price_services' => ['ac-repair', 'furnace-repair'],
    ]);

    $message = Message::query()->where('kind', MessageKind::CodeAnswer->value)->sole();

    expect(priceSentenceTextsToDana())->toHaveCount(1)
        ->and(priceSentenceTextsToDana()[0]['body'])->toBe(priceSentenceFirstText(PRICE_SENTENCE_AC.' '.PRICE_SENTENCE_FURNACE))
        ->and(array_column($message->meta['price_sentences'], 'slug'))->toBe(['ac-repair', 'furnace-repair']);
});

it('sends a combined text of exactly 1,600 characters whole and drafts the words at 1,601 (D-07)', function (int $length, bool $sendsWhole) {
    priceSentenceCustomerTexts('How much is the diagnostic, and do you work on Carrier units?');
    scriptFrontDeskReplies([
        'reply' => trim(priceSentenceWordsForLength($length)),
        'label' => 'faq_answer',
        'price_services' => ['ac-repair'],
    ]);
    runQueuedFrontDeskJobs();

    $texts = priceSentenceTextsToDana();

    if ($sendsWhole) {
        expect($texts)->toHaveCount(1)
            ->and(mb_strlen($texts[0]['body']))->toBe(1600)
            ->and(Message::query()->where('status', MessageStatus::Draft->value)->count())->toBe(0);

        return;
    }

    $draft = Message::query()->where('status', MessageStatus::Draft->value)->sole();

    expect($draft->gate_reason)->toBe('too_long')
        ->and($texts)->toHaveCount(1)
        ->and($texts[0]['body'])->toBe(priceSentenceFirstText(PRICE_SENTENCE_AC.' '.PRICE_SENTENCE_PARTIAL_LINE));
})->with([
    'exactly 1,600' => [1600, true],
    'one over, 1,601' => [1601, false],
]);

it('stops the sentence for the run when Jev finds a danger in the model\'s words (D-11)', function () {
    priceSentenceRunWithJev('How much is the diagnostic? My thermostat smells funny', [
        'reply' => 'Yes, we work on Carrier units.',
        'label' => 'faq_answer',
        'price_services' => ['ac-repair'],
    ], ['faq', 0.95, ['life_safety' => 0.2]]);

    $sentOut = Message::query()
        ->where('direction', 'out')
        ->where('author', MessageAuthor::Agent->value)
        ->where('status', '!=', MessageStatus::Draft->value)
        ->get();

    expect($sentOut->pluck('kind')->all())->toBe([MessageKind::LifeSafety])
        ->and(priceSentenceTextsToDana())->toHaveCount(1)
        ->and(priceSentenceTextsToDana()[0]['body'])->not->toContain('$89')
        ->and(Message::query()->where('status', MessageStatus::Draft->value)->count())->toBe(1);
});

/**
 * The line the owner reads on the held price draft: what the timeline and the Needs you list say about why it waits.
 */
function priceSentenceHeldReason(Message $draft): string
{
    return Contact::query()->sole()->activities()->where('type', ActivityType::DraftHeld->value)->where('subject_id', $draft->id)->sole()->summary;
}

it('holds a price_policy none service as a price draft with the holding reply and sends no sentence (D-04)', function () {
    priceSentenceRun('How much is a furnace tune-up?', [
        'reply' => 'Mike will text you the tune-up price.',
        'label' => 'price',
        'price_services' => ['furnace-tune-up'],
    ]);

    $draft = Message::query()->where('status', MessageStatus::Draft->value)->sole();

    expect($draft->gate_reason)->toBe('label:price')
        ->and(Message::query()->where('kind', MessageKind::HoldingReply->value)->count())->toBe(1)
        ->and(Message::query()->where('kind', MessageKind::CodeAnswer->value)->count())->toBe(0)
        ->and(priceSentenceTextsToDana())->toHaveCount(1)
        ->and(priceSentenceTextsToDana()[0]['body'])->not->toContain('$');
});

it('holds a passing reply that rides with a price question code cannot answer, naming the service (D-04, D-40)', function () {
    priceSentenceRun('How much is a furnace tune-up?', [
        'reply' => 'Is it gas or electric?',
        'label' => 'intake_question',
        'price_services' => ['furnace-tune-up'],
    ]);

    $draft = Message::query()->where('status', MessageStatus::Draft->value)->sole();

    expect($draft->gate_reason)->toBe('price:no_sentence')
        ->and($draft->body)->toBe('Is it gas or electric?')
        ->and($draft->gate['reason']['plain'])->toBe('Held: no price on file for Furnace tune-up')
        ->and(priceSentenceHeldReason($draft))->toBe('Held: no price on file for Furnace tune-up')
        ->and(Message::query()->where('kind', MessageKind::HoldingReply->value)->count())->toBe(1)
        ->and(OwnerAlert::query()->count())->toBe(1);
});

it('holds a slug the folder does not have as an empty price draft, never showing the slug to the owner (D-08, D-40)', function () {
    priceSentenceRun('How much to fix my roof?', [
        'reply' => '',
        'label' => 'faq_answer',
        'price_services' => ['roof-repair'],
    ]);

    $draft = Message::query()->where('status', MessageStatus::Draft->value)->sole();

    expect($draft->body)->toBe('')
        ->and($draft->gate_reason)->toBe('price:no_sentence')
        ->and($draft->gate['checks']['classifier']['ran'])->toBeFalse()
        ->and($draft->gate['reason']['plain'])->toBe('Held: no price on file for a service not in your folder')
        ->and(priceSentenceHeldReason($draft))->not->toContain('roof-repair')
        ->and(OwnerAlert::query()->count())->toBe(1)
        ->and(Message::query()->where('kind', MessageKind::HoldingReply->value)->count())->toBe(1)
        ->and(fakeClassifier()->calls())->toBe([]);
});

it('names every service with no price on file, in the order asked (D-40)', function () {
    priceSentenceRun('How much for a tune-up and a roof?', [
        'reply' => '',
        'price_services' => ['furnace-tune-up', 'roof-repair'],
    ]);

    $draft = Message::query()->where('status', MessageStatus::Draft->value)->sole();

    expect($draft->gate['reason']['plain'])->toBe('Held: no price on file for Furnace tune-up and a service not in your folder');
});

it('holds a service whose sentences are keyed to answers until the keyed pick exists (D-04)', function () {
    priceSentenceRun('How much for a new system?', [
        'reply' => '',
        'price_services' => ['system-replacement'],
    ]);

    $draft = Message::query()->where('status', MessageStatus::Draft->value)->sole();

    expect($draft->gate_reason)->toBe('price:no_sentence')
        ->and($draft->gate['reason']['plain'])->toBe('Held: no price on file for Replacement system')
        ->and(Message::query()->where('kind', MessageKind::CodeAnswer->value)->count())->toBe(0);
});

it('sends the sentence it has with the partial line and holds the service it has none for (D-08)', function () {
    priceSentenceRun('How much for an AC visit and a roof?', [
        'reply' => '',
        'price_services' => ['ac-repair', 'roof-repair'],
    ]);

    $draft = Message::query()->where('status', MessageStatus::Draft->value)->sole();

    expect($draft->body)->toBe('')
        ->and($draft->gate['reason']['plain'])->toBe('Held: no price on file for a service not in your folder')
        ->and(priceSentenceTextsToDana())->toHaveCount(1)
        ->and(priceSentenceTextsToDana()[0]['body'])->toBe(priceSentenceFirstText(PRICE_SENTENCE_AC.' '.PRICE_SENTENCE_PARTIAL_LINE))
        ->and(Message::query()->where('kind', MessageKind::HoldingReply->value)->count())->toBe(0);
});

it('still holds a price the model writes itself when no service is flagged (D-04)', function () {
    priceSentenceRun('How much is the diagnostic?', [
        'reply' => 'A diagnostic is $89.',
        'label' => 'faq_answer',
        'price_services' => [],
    ]);

    $draft = Message::query()->where('status', MessageStatus::Draft->value)->sole();

    expect($draft->gate_reason)->toBe('code:price')
        ->and(Message::query()->where('kind', MessageKind::CodeAnswer->value)->count())->toBe(0);
});

it('sends nothing and never asks the agent in a paused conversation (D-11)', function () {
    priceSentenceCustomerTexts('How much is the diagnostic?');
    Conversation::query()->sole()->update(['paused_at' => now(), 'paused_reason' => PausedReason::TakenOver]);
    scriptFrontDeskReplies(['reply' => '', 'price_services' => ['ac-repair']]);

    runQueuedFrontDeskJobs();

    FrontDeskAgent::assertNeverPrompted();

    expect(priceSentenceTextsToDana())->toBe([])
        ->and(Message::query()->where('kind', MessageKind::CodeAnswer->value)->count())->toBe(0);
});

it('sends nothing to a contact who opted out (D-11)', function () {
    priceSentenceCustomerTexts('How much is the diagnostic?');
    Contact::query()->sole()->update(['sms_opted_out_at' => now()]);
    scriptFrontDeskReplies(['reply' => '', 'price_services' => ['ac-repair']]);

    runQueuedFrontDeskJobs();

    expect(priceSentenceTextsToDana())->toBe([])
        ->and(Message::query()->where('kind', MessageKind::CodeAnswer->value)->count())->toBe(0);
});

it('pauses for the reply cap without sending a sentence when the hour is already full (D-11)', function () {
    config(['gate.reply_cap.per_hour' => 1]);
    priceSentenceCustomerTexts('How much is the diagnostic?');
    Message::factory()->for(Conversation::query()->sole())->outbound()->create([
        'kind' => MessageKind::AgentReply,
        'body' => 'Earlier automated text',
        'created_at' => now()->subMinutes(5),
    ]);
    scriptFrontDeskReplies(['reply' => '', 'price_services' => ['ac-repair']]);

    runQueuedFrontDeskJobs();

    FrontDeskAgent::assertNeverPrompted();

    expect(priceSentenceTextsToDana())->toBe([])
        ->and(OwnerAlert::query()->sole()->reason)->toBe(AlertReason::ReplyCap)
        ->and(Conversation::query()->sole()->paused_reason)->toBe(PausedReason::ReplyCap);
});

it('counts the code answer against the reply cap, so the following burst pauses (D-11)', function () {
    config(['gate.reply_cap.per_hour' => 2]);
    priceSentenceCustomerTexts('How much is the diagnostic?');
    Message::factory()->for(Conversation::query()->sole())->outbound()->create([
        'kind' => MessageKind::AgentReply,
        'body' => 'Earlier automated text',
        'created_at' => now()->subMinutes(5),
    ]);
    scriptFrontDeskReplies(['reply' => '', 'price_services' => ['ac-repair']]);

    runQueuedFrontDeskJobs();

    expect(priceSentenceTextsToDana())->toHaveCount(1);

    priceSentenceCustomerTextsAgain(['reply' => '', 'price_services' => ['ac-repair']]);

    expect(priceSentenceTextsToDana())->toHaveCount(1)
        ->and(OwnerAlert::query()->where('reason', AlertReason::ReplyCap->value)->count())->toBe(1)
        ->and(Conversation::query()->sole()->paused_reason)->toBe(PausedReason::ReplyCap);
});

it('keeps the price question for the owner as a draft that holds the sentence when it cannot be sent right now (D-04, D-44)', function () {
    config(['gate.agent.lock_wait_seconds' => 0]);
    priceSentenceCustomerTexts('How much is the diagnostic?');
    $lock = Cache::lock('reply:'.Conversation::query()->sole()->id, 30);
    $lock->get();
    scriptFrontDeskReplies(['reply' => '', 'price_services' => ['ac-repair']]);

    runQueuedFrontDeskJobs();

    $lock->release();

    $draft = Message::query()->where('status', MessageStatus::Draft->value)->sole();

    expect($draft->body)->toBe(PRICE_SENTENCE_AC)
        ->and($draft->meta['held_sentences'])->toBe([PRICE_SENTENCE_AC])
        ->and($draft->gate_reason)->toBe('send_refused')
        ->and(OwnerAlert::query()->count())->toBe(1)
        ->and(Message::query()->where('kind', MessageKind::CodeAnswer->value)->count())->toBe(0);
});
