<?php

use App\Icm\ActiveConfig;
use App\Icm\Exceptions\NoActiveVersion;
use App\Models\IcmVersion;

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
