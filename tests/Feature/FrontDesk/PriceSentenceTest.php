<?php

use App\Ai\AgentReply;
use App\Enums\GateOutcome;
use App\Enums\MessageAuthor;
use App\Enums\MessageKind;
use App\Enums\MessageStatus;
use App\Icm\ActiveConfig;
use App\Models\Message;
use App\Models\OwnerAlert;
use App\Models\User;
use Illuminate\Support\Carbon;

const PRICE_SENTENCE_OWNER_PHONE = '+12025550199';

const PRICE_SENTENCE_CUSTOMER_PHONE = '+12025550123';

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
