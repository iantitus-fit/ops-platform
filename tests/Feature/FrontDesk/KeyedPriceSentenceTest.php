<?php

use App\Actions\RecordIntakeAnswers;
use App\Enums\MessageKind;
use App\Enums\MessageStatus;
use App\Icm\ActiveConfig;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Carbon;

const KEYED_PRICE_OWNER_PHONE = '+12025550199';

const KEYED_PRICE_ASK_SIZE = 'About what size is your current system?';

const KEYED_PRICE_ASK_RUNNING = 'Is your current system still running?';

const KEYED_PRICE_LARGE = 'For a larger system, 3 tons and up, a replacement system typically runs $5,000 to $10,000 installed.';

/**
 * Tuesday 2026-10-13 at 10:00 in the shop's zone, the owner with a phone, and Dana's first text in the live example
 * folder. The model's script for that text is the one given.
 *
 * @param  array<string, mixed>  $reply
 */
function keyedPriceFirstRun(string $body, array $reply): void
{
    test()->travelTo(Carbon::parse('2026-10-13 10:00', 'America/Chicago'));

    User::factory()->withPhone(KEYED_PRICE_OWNER_PHONE)->create();

    frontDeskCustomerTexts($body);
    scriptFrontDeskReplies($reply);
    runQueuedFrontDeskJobs();
}

/**
 * Dana texts again and the front desk answers that burst with the given script.
 *
 * @param  array<string, mixed>  $reply
 */
function keyedPriceNextRun(string $body, array $reply): void
{
    static $count = 0;
    $count++;

    test()->travelTo(now()->addMinutes(2));

    test()->post(route('webhooks.text.fake'), fakeTextPayload([
        'MessageSid' => 'SM'.str_pad((string) $count, 32, 'k', STR_PAD_LEFT),
        'Body' => $body,
    ]))->assertOk();
    scriptFrontDeskReplies($reply);

    runQueuedFrontDeskJobs();
}

/**
 * The model's script for asking the size: it names the service and flags the price.
 *
 * @return array<string, mixed>
 */
function keyedPriceAskSize(): array
{
    return [
        'reply' => KEYED_PRICE_ASK_SIZE,
        'label' => 'intake_question',
        'service_slug' => 'system-replacement',
        'price_services' => ['system-replacement'],
    ];
}

/**
 * The model's script for a later text that answers the size and asks the next question, without flagging the price.
 *
 * @param  array<string, mixed>  $answer
 * @return array<string, mixed>
 */
function keyedPriceAnswerSize(array $answer, string $words = KEYED_PRICE_ASK_RUNNING): array
{
    return [
        'reply' => $words,
        'label' => 'intake_question',
        'service_slug' => 'system-replacement',
        'price_services' => [],
        'intake_answers' => [[...['question_key' => 'unit_size'], ...$answer]],
    ];
}

/**
 * The texts that reached Dana's phone, leaving out the alerts to the owner.
 *
 * @return list<string>
 */
function keyedPriceTextsToDana(): array
{
    return array_values(array_map(
        fn (array $text): string => $text['body'],
        array_filter(textsSentToCustomers(), fn (array $text): bool => $text['to'] === '+12025550123'),
    ));
}

/**
 * A later text as Dana reads it: the words and the sign-off, no disclosure.
 */
function keyedPriceLaterText(string $words): string
{
    return $words."\n".app(ActiveConfig::class)->voice()['sign_off'];
}

it('asks the keying question first and sends the owner\'s sentence for the answer, even when the model forgets the price (D-05)', function () {
    keyedPriceFirstRun('How much for a new system?', keyedPriceAskSize());

    $asked = Message::query()->where('kind', MessageKind::AgentReply->value)->sole();

    expect(keyedPriceTextsToDana())->toBe([composedFirstReply(KEYED_PRICE_ASK_SIZE)])
        ->and($asked->meta['price_pending'])->toBe(['system-replacement'])
        ->and($asked->meta['price_sentences'] ?? [])->toBe([]);

    keyedPriceNextRun("It's a big one, about 4 tons", keyedPriceAnswerSize([
        'option' => 'Large (3 tons and up)',
        'raw_text' => "It's a big one, about 4 tons",
    ]));

    $answered = Message::query()->where('kind', MessageKind::AgentReply->value)->orderByDesc('id')->first();

    expect(keyedPriceTextsToDana())->toHaveCount(2)
        ->and(keyedPriceTextsToDana()[1])->toBe(keyedPriceLaterText(KEYED_PRICE_LARGE.' '.KEYED_PRICE_ASK_RUNNING))
        ->and($answered->status)->toBe(MessageStatus::Sent)
        ->and($answered->meta['price_sentences'])->toBe([[
            'slug' => 'system-replacement',
            'key' => 'unit_size=Large (3 tons and up)',
            'amounts_cents' => [500000, 1000000],
        ]])
        ->and($answered->meta['parts'])->toBe(['code_sentences', 'model_words'])
        ->and($answered->meta['price_pending'])->toBe([]);
});

it('reads the newest intake answer per question as the current one (D-05)', function () {
    keyedPriceFirstRun('How much for a new system?', keyedPriceAskSize());
    keyedPriceNextRun("It's a big one, about 4 tons", keyedPriceAnswerSize([
        'option' => 'Large (3 tons and up)',
        'raw_text' => "It's a big one, about 4 tons",
    ]));

    $current = app(RecordIntakeAnswers::class)->currentFor(Conversation::query()->sole(), 'system-replacement');

    expect($current->keys()->all())->toBe(['unit_size'])
        ->and($current['unit_size']->option)->toBe('Large (3 tons and up)');
});
