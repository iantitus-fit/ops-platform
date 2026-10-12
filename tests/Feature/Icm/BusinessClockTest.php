<?php

use App\Icm\BusinessClock;
use Carbon\CarbonImmutable;

/**
 * A moment on the wall clock of the example business (America/Chicago).
 */
function chicago(string $dateTime): CarbonImmutable
{
    return CarbonImmutable::parse($dateTime, 'America/Chicago');
}

it('says today while the shop is open with at least an hour left', function (string $moment, string $phrase) {
    activateExampleFolder();

    expect(app(BusinessClock::class)->nextOpenPhrase(chicago($moment)))->toBe($phrase);
})->with([
    'mid-morning' => ['2026-10-13 10:00', 'today'],
    'exactly 60 minutes before close' => ['2026-10-13 16:00', 'today'],
    '59 minutes before close' => ['2026-10-13 16:01', 'when we open at 8 am tomorrow'],
    'before opening' => ['2026-10-13 07:00', 'when we open at 8 am today'],
    'after close on a Friday, Saturday opens later' => ['2026-10-16 18:00', 'when we open at 9 am tomorrow'],
    'Saturday afternoon, Sunday closed' => ['2026-10-17 14:00', 'when we open at 8 am Monday'],
    'Thanksgiving closure is skipped' => ['2026-11-25 18:00', 'when we open at 8 am Friday'],
    'the DST weekend still says 8 am' => ['2026-10-31 14:00', 'when we open at 8 am Monday'],
]);

it('finds the next opening in the business time zone across the end of daylight saving time', function () {
    activateExampleFolder();

    $opening = app(BusinessClock::class)->nextOpening(chicago('2026-10-31 14:00'));

    expect($opening->format('Y-m-d H:i'))->toBe('2026-11-02 08:00')
        ->and($opening->timezoneName)->toBe('America/Chicago')
        ->and($opening->utc()->format('Y-m-d H:i'))->toBe('2026-11-02 14:00');
});

it('only counts an opening that is strictly after the moment asked', function () {
    activateExampleFolder();

    $opening = app(BusinessClock::class)->nextOpening(chicago('2026-10-13 08:00'));

    expect($opening->format('Y-m-d H:i'))->toBe('2026-10-14 08:00');
});

it('writes minutes only when the opening time has them', function () {
    breakFile('business/hours.md', fn (string $hours) => str_replace('"08:00"', '"08:30"', $hours));
    activateExampleFolder();

    expect(app(BusinessClock::class)->nextOpenPhrase(chicago('2026-10-13 18:00')))
        ->toBe('when we open at 8:30 am tomorrow');
});

it('writes an afternoon opening with a lowercase pm', function () {
    breakFile('business/hours.md', fn (string $hours) => str_replace('{open: "09:00", close: "13:00"}', '{open: "13:00", close: "17:00"}', $hours));
    activateExampleFolder();

    expect(app(BusinessClock::class)->nextOpenPhrase(chicago('2026-10-16 18:00')))
        ->toBe('when we open at 1 pm tomorrow');
});

it('says when we reopen when every day is closed', function () {
    breakFile('business/hours.md', fn (string $hours) => preg_replace('/\{open: "[\d:]+", close: "[\d:]+"\}/', 'closed', $hours));
    activateExampleFolder();
    $clock = app(BusinessClock::class);

    expect($clock->nextOpening(chicago('2026-10-13 10:00')))->toBeNull()
        ->and($clock->nextOpenPhrase(chicago('2026-10-13 10:00')))->toBe('when we reopen')
        ->and($clock->isOpenAt(chicago('2026-10-13 10:00')))->toBeFalse();
});

it('treats close as exclusive and a closure date as closed all day', function () {
    activateExampleFolder();
    $clock = app(BusinessClock::class);

    expect($clock->isOpenAt(chicago('2026-10-13 10:00')))->toBeTrue()
        ->and($clock->isOpenAt(chicago('2026-10-13 08:00')))->toBeTrue()
        ->and($clock->isOpenAt(chicago('2026-10-13 17:00')))->toBeFalse()
        ->and($clock->isOpenAt(chicago('2026-10-13 07:59')))->toBeFalse()
        ->and($clock->isOpenAt(chicago('2026-11-26 10:00')))->toBeFalse()
        ->and($clock->isOpenAt(chicago('2026-10-18 10:00')))->toBeFalse();
});

it('reads the moment in the business zone even when it is given in another zone', function () {
    activateExampleFolder();

    $utcMoment = CarbonImmutable::parse('2026-10-13 15:00', 'UTC');

    expect(app(BusinessClock::class)->nextOpenPhrase($utcMoment))->toBe('today');
});

it('reads the margin from config', function () {
    activateExampleFolder();
    config(['gate.next_open.today_margin_minutes' => 120]);

    expect(app(BusinessClock::class)->nextOpenPhrase(chicago('2026-10-13 15:30')))
        ->toBe('when we open at 8 am tomorrow');
});
