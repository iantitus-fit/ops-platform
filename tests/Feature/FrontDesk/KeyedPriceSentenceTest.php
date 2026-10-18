<?php

use App\Actions\RecordIntakeAnswers;
use App\Enums\MessageKind;
use App\Enums\MessageStatus;
use App\Icm\ActiveConfig;
use App\Models\Conversation;
use App\Models\IntakeAnswer;
use App\Models\Message;
use App\Models\User;
use App\SendRule\PriceSentencePicker;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

const KEYED_PRICE_OWNER_PHONE = '+12025550199';

const KEYED_PRICE_ASK_SIZE = 'About what size is your current system?';

const KEYED_PRICE_ASK_RUNNING = 'Is your current system still running?';

const KEYED_PRICE_REASK = 'About what size is it? Small (under 3 tons), Large (3 tons and up), or Not sure?';

const KEYED_PRICE_SMALL = 'For a smaller system, under 3 tons, a replacement system typically runs $2,000 to $5,000 installed.';

const KEYED_PRICE_DEFAULT = "That's fine if you're not sure what you have. A replacement system typically runs $2,000 to $5,000 installed for a smaller unit and $5,000 to $10,000 for a larger one. We'll lock in an accurate quote once we see your setup.";

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

/**
 * The newest message the front desk wrote and sent, the one that carries the price sentences of the last run.
 */
function keyedPriceLastReply(): Message
{
    return Message::query()->where('kind', MessageKind::AgentReply->value)->orderByDesc('id')->firstOrFail();
}

it('sends the default sentence when the answer is Not sure (D-05)', function () {
    keyedPriceFirstRun('How much for a new system?', keyedPriceAskSize());
    keyedPriceNextRun('Not sure what I have', keyedPriceAnswerSize(['option' => 'Not sure', 'raw_text' => 'Not sure what I have']));

    expect(keyedPriceTextsToDana()[1])->toBe(keyedPriceLaterText(KEYED_PRICE_DEFAULT.' '.KEYED_PRICE_ASK_RUNNING))
        ->and(keyedPriceLastReply()->meta['price_sentences'])->toBe([[
            'slug' => 'system-replacement',
            'key' => 'default',
            'amounts_cents' => [200000, 500000, 1000000],
        ]])
        ->and(keyedPriceLastReply()->meta['price_pending'])->toBe([]);
});

it('re-asks once when the answer matches no option, and sends the default sentence on the second miss (D-06)', function () {
    keyedPriceFirstRun('How much for a new system?', keyedPriceAskSize());
    keyedPriceNextRun('the biggish one', keyedPriceAnswerSize(['option' => null, 'raw_text' => 'the biggish one'], KEYED_PRICE_REASK));

    expect(keyedPriceTextsToDana()[1])->toBe(keyedPriceLaterText(KEYED_PRICE_REASK))
        ->and(keyedPriceLastReply()->meta['price_pending'])->toBe(['system-replacement'])
        ->and(keyedPriceLastReply()->meta['price_sentences'] ?? [])->toBe([]);

    keyedPriceNextRun('the one in the garage', keyedPriceAnswerSize(['option' => null, 'raw_text' => 'the one in the garage']));

    expect(keyedPriceTextsToDana()[2])->toBe(keyedPriceLaterText(KEYED_PRICE_DEFAULT.' '.KEYED_PRICE_ASK_RUNNING))
        ->and(keyedPriceLastReply()->meta['price_sentences'][0]['key'])->toBe('default')
        ->and(keyedPriceLastReply()->meta['price_pending'])->toBe([]);
});

it('sends the matching sentence when the answer matches after one miss (D-06)', function () {
    keyedPriceFirstRun('How much for a new system?', keyedPriceAskSize());
    keyedPriceNextRun('the biggish one', keyedPriceAnswerSize(['option' => null, 'raw_text' => 'the biggish one'], KEYED_PRICE_REASK));
    keyedPriceNextRun('Small, 2 tons', keyedPriceAnswerSize(['option' => 'Small (under 3 tons)', 'raw_text' => 'Small, 2 tons']));

    expect(keyedPriceTextsToDana()[2])->toBe(keyedPriceLaterText(KEYED_PRICE_SMALL.' '.KEYED_PRICE_ASK_RUNNING))
        ->and(keyedPriceLastReply()->meta['price_sentences'][0]['key'])->toBe('unit_size=Small (under 3 tons)');
});

it('re-asks a miss that follows a match, because only misses since the last match count (D-06, WR-06)', function () {
    keyedPriceFirstRun('How much for a new system?', keyedPriceAskSize());
    keyedPriceNextRun('the biggish one', keyedPriceAnswerSize(['option' => null, 'raw_text' => 'the biggish one'], KEYED_PRICE_REASK));
    keyedPriceNextRun('Small, 2 tons', keyedPriceAnswerSize(['option' => 'Small (under 3 tons)', 'raw_text' => 'Small, 2 tons']));
    keyedPriceNextRun('wait, it might be the one in the attic', keyedPriceAnswerSize(['option' => null, 'raw_text' => 'wait, it might be the one in the attic'], KEYED_PRICE_REASK));

    expect(keyedPriceTextsToDana()[3])->toBe(keyedPriceLaterText(KEYED_PRICE_REASK))
        ->and(keyedPriceLastReply()->meta['price_sentences'] ?? [])->toBe([]);

    keyedPriceNextRun('still not sure, the attic one', keyedPriceAnswerSize(['option' => null, 'raw_text' => 'still not sure, the attic one']));

    expect(keyedPriceTextsToDana()[4])->toBe(keyedPriceLaterText('Thanks for clarifying. That changes the estimate: '.KEYED_PRICE_DEFAULT.' '.KEYED_PRICE_ASK_RUNNING))
        ->and(keyedPriceLastReply()->meta['price_sentences'][0]['key'])->toBe('default');
});

it('reads the pending price list only from a text that went out or a live draft, never from a failed send (IN-04)', function () {
    keyedPriceFirstRun('Do you service heat pumps?', ['reply' => 'Yes, we service heat pumps.', 'label' => 'faq_answer']);

    $conversation = Conversation::query()->sole();
    $inbound = Message::query()->where('direction', 'in')->sole();

    test()->travelTo(now()->addMinute());

    Message::factory()->for($conversation)->outbound()->create([
        'body' => KEYED_PRICE_ASK_SIZE,
        'status' => MessageStatus::Failed,
        'sent_at' => null,
        'meta' => ['in_reply_to' => $inbound->id, 'price_pending' => ['system-replacement']],
    ]);

    keyedPriceNextRun('What time do you open?', ['reply' => 'We open at 8 am.', 'label' => 'faq_answer']);

    $answered = Message::query()->where('kind', MessageKind::AgentReply->value)->where('status', MessageStatus::Sent->value)->orderByDesc('id')->first();

    expect($answered->body)->toContain('We open at 8 am.')
        ->and($answered->meta['price_pending'] ?? [])->toBe([])
        ->and($answered->meta['price_slugs'] ?? [])->toBe([]);
});

it('holds the price for the owner when no option fits and the file has no default sentence (D-05)', function () {
    breakFile('business/services/system-replacement.md', fn (string $text) => preg_replace('/^price_default:\n(?:  .*\n)+/m', '', $text));

    keyedPriceFirstRun('How much for a new system?', keyedPriceAskSize());
    keyedPriceNextRun('Not sure what I have', keyedPriceAnswerSize(['option' => 'Not sure', 'raw_text' => 'Not sure what I have']));

    $draft = Message::query()->where('status', MessageStatus::Draft->value)->sole();

    expect($draft->gate_reason)->toBe('price:no_sentence')
        ->and($draft->gate['reason']['plain'])->toBe('Held: no price on file for Replacement system')
        ->and(Message::query()->where('kind', MessageKind::HoldingReply->value)->count())->toBe(1)
        ->and(Message::query()->where('kind', MessageKind::CodeAnswer->value)->count())->toBe(0)
        ->and(collect(keyedPriceTextsToDana())->contains(fn (string $text): bool => str_contains($text, '$')))->toBeFalse();
});

it('never lets a non-keying question block the sentence (D-05)', function () {
    keyedPriceFirstRun('How much for a new system?', keyedPriceAskSize());
    keyedPriceNextRun('Large, and I do not know about the rest', [
        ...keyedPriceAnswerSize(['option' => 'Large (3 tons and up)', 'raw_text' => 'Large']),
        'intake_answers' => [
            ['question_key' => 'unit_size', 'option' => 'Large (3 tons and up)', 'raw_text' => 'Large'],
            ['question_key' => 'condition', 'option' => null, 'raw_text' => 'it sort of works'],
        ],
    ]);

    expect(keyedPriceTextsToDana()[1])->toBe(keyedPriceLaterText(KEYED_PRICE_LARGE.' '.KEYED_PRICE_ASK_RUNNING))
        ->and(IntakeAnswer::query()->where('question_key', 'condition')->count())->toBe(1);
});

it('picks the same sentence key every time it runs on the same rows (T-02.1-20)', function () {
    keyedPriceFirstRun('How much for a new system?', keyedPriceAskSize());
    keyedPriceNextRun('Small, 2 tons', keyedPriceAnswerSize(['option' => 'Small (under 3 tons)', 'raw_text' => 'Small, 2 tons']));

    $conversation = Conversation::query()->sole();
    $picker = app(PriceSentencePicker::class);

    $first = $picker->pick($conversation, 'system-replacement', true, new Collection);
    $second = $picker->pick($conversation, 'system-replacement', true, new Collection);

    expect($first->sentenceKey)->toBe('unit_size=Small (under 3 tons)')
        ->and($second->sentenceKey)->toBe($first->sentenceKey)
        ->and($second->sentence)->toBe($first->sentence);
});
