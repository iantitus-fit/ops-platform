<?php

use App\Enums\VersionStatus;
use App\Icm\ActiveConfig;
use App\Models\IcmVersion;
use App\Models\Message;
use App\Models\User;
use App\SendRule\PriceSentencePicker;
use Illuminate\Support\Carbon;

const PRICE_LEAD_OWNER_PHONE = '+12025550199';

const PRICE_LEAD_SAME = "Here's that estimate again:";

const PRICE_LEAD_HIGHER = 'Thanks for clarifying. That puts the estimate higher:';

const PRICE_LEAD_LOWER = 'Thanks for clarifying. That brings the estimate down:';

const PRICE_LEAD_CHANGED = 'Thanks for clarifying. That changes the estimate:';

const PRICE_LEAD_WORDS = 'Got it, thank you.';

const PRICE_LEAD_SMALL = 'For a smaller system, under 3 tons, a replacement system typically runs $2,000 to $5,000 installed.';

const PRICE_LEAD_LARGE = 'For a larger system, 3 tons and up, a replacement system typically runs $5,000 to $10,000 installed.';

const PRICE_LEAD_LARGE_WIDER = 'For a larger system, 3 tons and up, a replacement system typically runs $4,000 to $10,000 installed.';

const PRICE_LEAD_DEFAULT = "That's fine if you're not sure what you have. A replacement system typically runs $2,000 to $5,000 installed for a smaller unit and $5,000 to $10,000 for a larger one. We'll lock in an accurate quote once we see your setup.";

/**
 * Tuesday 2026-10-13 at 10:00 in the shop's zone, the owner with a phone, and Dana's first text in the live example
 * folder. The model's script for that text is the one given.
 *
 * @param  array<string, mixed>  $reply
 */
function priceLeadFirstRun(string $body, array $reply): void
{
    test()->travelTo(Carbon::parse('2026-10-13 10:00', 'America/Chicago'));

    User::factory()->withPhone(PRICE_LEAD_OWNER_PHONE)->create();

    frontDeskCustomerTexts($body);
    scriptFrontDeskReplies($reply);
    runQueuedFrontDeskJobs();
}

/**
 * Dana texts again and the front desk answers that burst with the given script.
 *
 * @param  array<string, mixed>  $reply
 */
function priceLeadNextRun(string $body, array $reply): void
{
    static $count = 0;
    $count++;

    test()->travelTo(now()->addMinutes(2));

    test()->post(route('webhooks.text.fake'), fakeTextPayload([
        'MessageSid' => 'SM'.str_pad((string) $count, 32, 'l', STR_PAD_LEFT),
        'Body' => $body,
    ]))->assertOk();
    scriptFrontDeskReplies($reply);

    runQueuedFrontDeskJobs();
}

/**
 * The model's script for a text that gives the size of the system, without flagging the price again.
 *
 * @return array<string, mixed>
 */
function priceLeadAnswer(?string $option, string $words = PRICE_LEAD_WORDS): array
{
    return [
        'reply' => $words,
        'label' => 'intake_question',
        'service_slug' => 'system-replacement',
        'price_services' => [],
        'intake_answers' => [['question_key' => 'unit_size', 'option' => $option, 'raw_text' => $option ?? 'not sure']],
    ];
}

/**
 * Dana asks what a new system costs, is asked the size, and answers it: the sentence for that size has gone out.
 */
function priceLeadAfterAnswer(string $option): void
{
    priceLeadFirstRun('How much for a new system?', [
        'reply' => 'About what size is your current system?',
        'label' => 'intake_question',
        'service_slug' => 'system-replacement',
        'price_services' => ['system-replacement'],
    ]);
    priceLeadNextRun("It's the {$option} one", priceLeadAnswer($option, 'Is your current system still running?'));
}

/**
 * The text Dana read last, the sign-off and nothing before it.
 */
function priceLeadLastText(): string
{
    $texts = array_values(array_filter(textsSentToCustomers(), fn (array $text): bool => $text['to'] === '+12025550123'));

    return $texts[count($texts) - 1]['body'];
}

/**
 * How many texts carried a price sentence. The meta is read in PHP, not with a JSON path in SQL, because the suite runs
 * on SQLite and PostgreSQL.
 */
function priceLeadSentenceMessageCount(): int
{
    return Message::query()->get()->filter(fn (Message $message): bool => ! empty($message->meta['price_sentences'] ?? []))->count();
}

/**
 * The text as Dana reads a later one: the words, a new line and the sign-off.
 */
function priceLeadText(string $words): string
{
    return $words."\n".app(ActiveConfig::class)->voice()['sign_off'];
}

/**
 * Make the large-system sentence start at $4,000, so it overlaps the small one instead of touching it.
 */
function priceLeadWidenLargeRange(): void
{
    breakFile('business/services/system-replacement.md', fn (string $text) => str_replace(
        ['typically runs $5,000 to $10,000 installed.', 'amounts_cents: [500000, 1000000]'],
        ['typically runs $4,000 to $10,000 installed.', 'amounts_cents: [400000, 1000000]'],
        $text,
    ));
}

it('re-sends with the lower lead when a correction brings the price down, even where the ranges touch (D-09, D-39)', function () {
    priceLeadAfterAnswer('Large (3 tons and up)');
    priceLeadNextRun('Actually it is small', priceLeadAnswer('Small (under 3 tons)'));

    expect(priceLeadLastText())->toBe(priceLeadText(PRICE_LEAD_LOWER.' '.PRICE_LEAD_SMALL.' '.PRICE_LEAD_WORDS));
});

it('re-sends with the higher lead when a correction raises the price, even where the ranges touch (D-09, D-39)', function () {
    priceLeadAfterAnswer('Small (under 3 tons)');
    priceLeadNextRun('Actually it is large', priceLeadAnswer('Large (3 tons and up)'));

    expect(priceLeadLastText())->toBe(priceLeadText(PRICE_LEAD_HIGHER.' '.PRICE_LEAD_LARGE.' '.PRICE_LEAD_WORDS));
});

it('re-sends with the changed lead only when the two ranges overlap (D-09, D-39)', function (string $first, string $second, string $sentence) {
    priceLeadWidenLargeRange();
    priceLeadAfterAnswer($first);
    priceLeadNextRun("Actually it is {$second}", priceLeadAnswer($second));

    expect(priceLeadLastText())->toBe(priceLeadText(PRICE_LEAD_CHANGED.' '.$sentence.' '.PRICE_LEAD_WORDS));
})->with([
    'small then large' => ['Small (under 3 tons)', 'Large (3 tons and up)', PRICE_LEAD_LARGE_WIDER],
    'large then small' => ['Large (3 tons and up)', 'Small (under 3 tons)', PRICE_LEAD_SMALL],
]);

it('sends no sentence when the correction maps to the sentence already sent (D-09)', function () {
    priceLeadAfterAnswer('Large (3 tons and up)');

    priceLeadNextRun('Yes, definitely large', priceLeadAnswer('Large (3 tons and up)'));

    expect(priceLeadLastText())->toBe(priceLeadText(PRICE_LEAD_WORDS))
        ->and(priceLeadSentenceMessageCount())->toBe(1);
});

it('sends the default sentence with the changed lead when a correction is Not sure (D-09)', function () {
    priceLeadAfterAnswer('Large (3 tons and up)');
    priceLeadNextRun('Actually I am not sure', priceLeadAnswer('Not sure'));

    expect(priceLeadLastText())->toBe(priceLeadText(PRICE_LEAD_CHANGED.' '.PRICE_LEAD_DEFAULT.' '.PRICE_LEAD_WORDS));
});

it('re-sends the last sentence with the same lead when the customer asks again and nothing changed (D-09)', function () {
    priceLeadAfterAnswer('Large (3 tons and up)');
    priceLeadNextRun('So what was that price again?', [
        'reply' => '',
        'label' => 'faq_answer',
        'service_slug' => 'system-replacement',
        'price_services' => ['system-replacement'],
    ]);

    expect(priceLeadLastText())->toBe(priceLeadText(PRICE_LEAD_SAME.' '.PRICE_LEAD_LARGE));
});

it('puts the same lead in front of the unkeyed sentence the second time it is asked (D-09)', function () {
    $ask = ['reply' => '', 'label' => 'faq_answer', 'price_services' => ['ac-repair']];
    $acSentence = 'AC repair starts with an $89 diagnostic visit, and we take the $89 off if you go ahead with the repair that visit.';

    priceLeadFirstRun('How much is the diagnostic for my AC?', $ask);

    expect(priceLeadLastText())->toBe(composedFirstReply($acSentence));

    priceLeadNextRun('And the diagnostic was how much?', $ask);

    expect(priceLeadLastText())->toBe(priceLeadText(PRICE_LEAD_SAME.' '.$acSentence));
});

it('sends no sentence for a correction when no sentence went out before and no price was asked (D-09)', function () {
    priceLeadFirstRun('I think it is a big unit', priceLeadAnswer('Large (3 tons and up)'));

    expect(priceLeadLastText())->toBe(composedFirstReply(PRICE_LEAD_WORDS))
        ->and(priceLeadSentenceMessageCount())->toBe(0);
});

it('sends the sentence alone when the live version has no price leads (Pitfall 11)', function () {
    priceLeadAfterAnswer('Large (3 tons and up)');

    $version = IcmVersion::query()->where('status', VersionStatus::Active->value)->sole();
    $compiled = $version->compiled;
    unset($compiled['files']['rules/replies.md']['front_matter']['price_leads']);
    $version->update(['compiled' => $compiled]);
    app(ActiveConfig::class)->refresh();

    priceLeadNextRun('Actually it is small', priceLeadAnswer('Small (under 3 tons)'));

    expect(priceLeadLastText())->toBe(priceLeadText(PRICE_LEAD_SMALL.' '.PRICE_LEAD_WORDS));
});

it('names how the new range compares with the old by integer cents (D-39)', function (array $old, array $new, string $direction) {
    expect(app(PriceSentencePicker::class)->direction($old, $new))->toBe($direction);
})->with([
    'identical' => [[200000, 500000], [200000, 500000], 'same'],
    'wholly higher' => [[200000, 500000], [600000, 1000000], 'higher'],
    'wholly lower' => [[600000, 1000000], [200000, 500000], 'lower'],
    'starts where the old ended' => [[200000, 500000], [500000, 1000000], 'higher'],
    'ends where the old started' => [[500000, 1000000], [200000, 500000], 'lower'],
    'one cent less than touching' => [[200000, 500000], [499900, 1000000], 'changed'],
    'a three-amount set against a pair' => [[200000, 500000, 1000000], [500000, 1000000], 'changed'],
    'one price the same' => [[8900], [8900], 'same'],
    'one price a cent up' => [[8900], [8901], 'higher'],
    'one price a cent down' => [[8901], [8900], 'lower'],
]);
