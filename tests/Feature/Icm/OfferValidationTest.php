<?php

use App\Enums\VersionStatus;
use App\Icm\ActiveConfig;
use App\Icm\Exceptions\NoActiveVersion;
use App\Models\IcmVersion;
use Illuminate\Support\Facades\File;

const OFFER_VALIDATION_EXAMPLE_SENTENCE = "If you're a first-time customer, we're running a promotion that takes \$50 off your first visit.";

const OFFER_VALIDATION_EXAMPLE_PATH = 'business/offers/first-visit-promotion.md';

/**
 * Write a second offer file next to the example one, a copy with its own slug, sentence and amount.
 */
function offerValidationWriteSpringTuneUp(): void
{
    writeIcmFile('business/offers/spring-tune-up.md', implode("\n", [
        '---',
        'type: offer',
        'name: Spring tune-up',
        'slug: spring-tune-up',
        'sentence: "If you book a tune-up before May, we\'re running a special that takes $20 off the visit."',
        'amount_cents: 2000',
        'start_date: 2027-03-01',
        '---',
        'The business confirms the booking date at the visit.',
        '',
    ]));
}

it('throws NoActiveVersion from the offer accessors before a sync', function (string $method, array $arguments) {
    expect(fn () => app(ActiveConfig::class)->{$method}(...$arguments))->toThrow(NoActiveVersion::class);
})->with([
    'offers' => ['offers', []],
    'offer' => ['offer', ['first-visit-promotion']],
]);

it('puts the example offer live and reads it back through ActiveConfig', function () {
    activateExampleFolder();

    $config = app(ActiveConfig::class);
    $offers = $config->offers();

    expect($offers)->toHaveCount(1)
        ->and($offers[0]['slug'])->toBe('first-visit-promotion')
        ->and($offers[0]['sentence'])->toBe(OFFER_VALIDATION_EXAMPLE_SENTENCE)
        ->and($offers[0]['amount_cents'])->toBe(5000)
        ->and($offers[0]['start_date'])->toBe('2026-10-01')
        ->and($offers[0]['end_date'])->toBe('2026-12-31')
        ->and($offers[0]['path'])->toBe(OFFER_VALIDATION_EXAMPLE_PATH)
        ->and($config->offer('first-visit-promotion')['sentence'])->toBe(OFFER_VALIDATION_EXAMPLE_SENTENCE)
        ->and($config->offer('nope'))->toBeNull();
});

it('keeps every offer file, so a second offer never overwrites the first', function () {
    offerValidationWriteSpringTuneUp();

    activateExampleFolder();

    $config = app(ActiveConfig::class);

    expect(array_column($config->offers(), 'slug'))->toBe(['first-visit-promotion', 'spring-tune-up'])
        ->and($config->offer('spring-tune-up')['amount_cents'])->toBe(2000)
        ->and($config->offer('first-visit-promotion')['amount_cents'])->toBe(5000);
});

it('reads a version compiled before offers existed as having no offers', function () {
    $version = activateExampleFolder();

    $compiled = $version->compiled;
    unset($compiled['index']['offer']);
    $version->update(['compiled' => $compiled]);

    $config = app(ActiveConfig::class);
    $config->refresh();

    expect($config->offers())->toBe([])
        ->and($config->offer('first-visit-promotion'))->toBeNull();
});

it('syncs the example folder with every typed file, the offer included', function () {
    $version = activateExampleFolder();

    expect($version->file_count)->toBe(14)
        ->and(IcmVersion::count())->toBe(1);
});

it('refuses an offer with no start date and says so in plain words', function () {
    breakFile(OFFER_VALIDATION_EXAMPLE_PATH, fn (string $text) => str_replace("start_date: 2026-10-01\n", '', $text));

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe(['business/offers/first-visit-promotion.md: start_date is missing, and it is required']);
});

/*
| Each row: the text to find in the example offer, the text to put in its place, and the exact line the owner must see.
*/
dataset('offerValidation broken offers', [
    'both amount_cents and percent' => ["amount_cents: 5000\n", "amount_cents: 5000\npercent: 10\n", 'business/offers/first-visit-promotion.md: write either amount_cents or percent, not both'],
    'neither amount_cents nor percent' => ["amount_cents: 5000\n", '', 'business/offers/first-visit-promotion.md: amount_cents or percent is missing, and the offer needs one'],
    'a sentence amount the field does not back' => ['takes $50 off', 'takes $40 off', "business/offers/first-visit-promotion.md: sentence: the sentence's amounts (\$40) do not match amount_cents (\$50)"],
    'a second amount the field does not back' => ['takes $50 off', 'takes $50 off, plus $25 off a tune-up', "business/offers/first-visit-promotion.md: sentence: the sentence's amounts (\$25, \$50) do not match amount_cents (\$50)"],
    'an amount written in words' => ['takes $50 off', 'takes 50 bucks off', 'business/offers/first-visit-promotion.md: sentence: write every amount as $ and digits, such as $5,000, not bucks'],
    'an amount in k shorthand' => ['takes $50 off', 'takes $5k off', 'business/offers/first-visit-promotion.md: sentence: write every amount as $ and digits, such as $5,000, not $5k'],
    'a bare number beside a price word' => ['takes $50 off your first visit', 'takes a fee of 50 off your first visit', 'business/offers/first-visit-promotion.md: sentence: write every amount with a $ sign (50)'],
    'no amount or percentage' => ['takes $50 off', 'takes something off', 'business/offers/first-visit-promotion.md: sentence: the sentence needs at least one amount written as $ and digits, or a percentage'],
    'a promise' => ["we're running a promotion", "we're running a guaranteed promotion", 'business/offers/first-visit-promotion.md: sentence: makes a promise (guaranteed); an offer sentence goes out without you, so it cannot make a promise'],
    'a phrase the voice file bans' => ["If you're a first-time", "No problem. If you're a first-time", 'business/offers/first-visit-promotion.md: sentence: uses a phrase the voice file says never to use (No problem)'],
    'a fill-in' => ["we're running", '{owner_name} is running', 'business/offers/first-visit-promotion.md: sentence: an offer sentence cannot use fill-ins ({owner_name})'],
    'an end date before the start date' => ['end_date: 2026-12-31', 'end_date: 2026-09-30', 'business/offers/first-visit-promotion.md: end_date (2026-09-30) is before start_date (2026-10-01)'],
    'a start date written the wrong way round' => ['start_date: 2026-10-01', 'start_date: 10/01/2026', 'business/offers/first-visit-promotion.md: start_date must be a date such as 2026-12-25 (you wrote 10/01/2026)'],
    'an end date that is not a date' => ['end_date: 2026-12-31', 'end_date: soon', 'business/offers/first-visit-promotion.md: end_date must be a date such as 2026-12-25 (you wrote soon)'],
    'an amount of zero' => ['amount_cents: 5000', 'amount_cents: 0', 'business/offers/first-visit-promotion.md: amount_cents must be 1 or more (you wrote 0)'],
]);

it('refuses an offer that breaks a check and keeps nothing live', function (string $search, string $replace, string $line) {
    expect(File::get(icmPath(OFFER_VALIDATION_EXAMPLE_PATH)))->toContain($search);

    breakFile(OFFER_VALIDATION_EXAMPLE_PATH, fn (string $text) => str_replace($search, $replace, $text));

    $this->artisan('icm:sync')
        ->expectsOutputToContain($line)
        ->assertExitCode(1);

    expect(rejectedErrors())->toBe([$line])
        ->and(IcmVersion::where('status', VersionStatus::Active->value)->count())->toBe(0);
})->with('offerValidation broken offers');

it('refuses a percentage offer whose sentence names a different percentage', function () {
    offerValidationMakePercentOffer(10);
    breakFile(OFFER_VALIDATION_EXAMPLE_PATH, fn (string $text) => str_replace('takes 10% off', 'takes 15% off', $text));

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe(["business/offers/first-visit-promotion.md: sentence: the sentence's percentages (15%) do not match percent (10%)"]);
});

it('refuses a percentage offer whose sentence also names an amount', function () {
    offerValidationMakePercentOffer(10);
    breakFile(OFFER_VALIDATION_EXAMPLE_PATH, fn (string $text) => str_replace('takes 10% off', 'takes 10% off, up to $50 off', $text));

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe(["business/offers/first-visit-promotion.md: sentence: the sentence's amounts (\$50) do not match amount_cents (none)"]);
});

it('refuses an amount offer whose sentence names a percentage', function () {
    breakFile(OFFER_VALIDATION_EXAMPLE_PATH, fn (string $text) => str_replace('takes $50 off', 'takes $50 off, or 10% off', $text));

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe(["business/offers/first-visit-promotion.md: sentence: the sentence's percentages (10%) do not match percent (none)"]);
});

it('refuses a percent outside 1 to 100', function (int $percent, string $line) {
    offerValidationMakePercentOffer($percent);

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toContain($line);
})->with([
    'more than 100' => [150, 'business/offers/first-visit-promotion.md: percent must be 100 or less (you wrote 150)'],
    'zero' => [0, 'business/offers/first-visit-promotion.md: percent must be 1 or more (you wrote 0)'],
]);

it('lets a percentage offer through when the sentence names that percentage', function () {
    offerValidationMakePercentOffer(10);

    activateExampleFolder();

    $offer = app(ActiveConfig::class)->offer('first-visit-promotion');

    expect($offer['percent'])->toBe(10)
        ->and($offer)->not->toHaveKey('amount_cents');
});

it('lets the percentage be written as the word percent', function () {
    offerValidationMakePercentOffer(10);
    breakFile(OFFER_VALIDATION_EXAMPLE_PATH, fn (string $text) => str_replace('takes 10% off', 'takes 10 percent off', $text));

    activateExampleFolder();

    expect(app(ActiveConfig::class)->offer('first-visit-promotion')['percent'])->toBe(10);
});

it('lets an offer run with no end date', function () {
    breakFile(OFFER_VALIDATION_EXAMPLE_PATH, fn (string $text) => str_replace("end_date: 2026-12-31\n", '', $text));

    activateExampleFolder();

    expect(app(ActiveConfig::class)->offer('first-visit-promotion'))->not->toHaveKey('end_date');
});

it('lets an offer end on the day it starts', function () {
    breakFile(OFFER_VALIDATION_EXAMPLE_PATH, fn (string $text) => str_replace('end_date: 2026-12-31', 'end_date: 2026-10-01', $text));

    activateExampleFolder();

    expect(app(ActiveConfig::class)->offer('first-visit-promotion')['end_date'])->toBe('2026-10-01');
});

it('refuses a second offer file that uses the same slug and names the file that has it first', function () {
    writeIcmFile('business/offers/z-copy.md', File::get(icmPath(OFFER_VALIDATION_EXAMPLE_PATH)));

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe(['business/offers/z-copy.md: slug first-visit-promotion is already used by business/offers/first-visit-promotion.md']);
});

it('lets an offer and a service share a slug, because each type keeps its own slugs', function () {
    writeIcmFile('business/offers/ac-repair.md', str_replace('slug: first-visit-promotion', 'slug: ac-repair', File::get(icmPath(OFFER_VALIDATION_EXAMPLE_PATH))));

    activateExampleFolder();

    $config = app(ActiveConfig::class);

    expect($config->offer('ac-repair')['name'])->toBe('First visit promotion')
        ->and($config->service('ac-repair')['name'])->toBe('AC repair');
});

it('reports a broken offer beside a good one without losing either', function () {
    offerValidationWriteSpringTuneUp();
    breakFile('business/offers/spring-tune-up.md', fn (string $text) => str_replace('$20 off', '$25 off', $text));

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe(["business/offers/spring-tune-up.md: sentence: the sentence's amounts (\$25) do not match amount_cents (\$20)"]);
});

it('ships a README the owner reads that explains the offer file', function () {
    $readme = File::get(base_path('examples/example-hvac/business/offers/README.md'));

    expect($readme)->toContain('end_date')
        ->and($readme)->toContain('start_date')
        ->and($readme)->toContain('amount_cents');
});

/**
 * Turn the example offer into a percentage offer with this percent, its sentence naming 10%.
 */
function offerValidationMakePercentOffer(int $percent): void
{
    breakFile(OFFER_VALIDATION_EXAMPLE_PATH, fn (string $text) => str_replace(
        ['amount_cents: 5000', 'takes $50 off'],
        ["percent: {$percent}", 'takes 10% off'],
        $text,
    ));
}
