<?php

use App\Rooms\OwnerIntelligence\DurationText;

/**
 * D-06: the one formatter for time to booked. Each row is named by its seconds, so a failure says which boundary broke.
 * A Unit test boots nothing, so this stays plain.
 *
 * @return array<string, array{0: int, 1: string}>
 */
function durationTextRows(): array
{
    $rows = [
        0 => 'under a minute',
        59 => 'under a minute',
        60 => '1 minute',
        89 => '1 minute',
        90 => '2 minutes',
        2520 => '42 minutes',
        3660 => '61 minutes',
        5400 => '90 minutes',
        5429 => '90 minutes',
        5430 => '1 hour 31 minutes',
        7200 => '2 hours',
        11400 => '3 hours 10 minutes',
        86400 => '24 hours',
        90060 => '25 hours 1 minute',
        172800 => '48 hours',
        172860 => '2 days',
        176400 => '2 days 1 hour',
        187200 => '2 days 4 hours',
        190740 => '2 days 4 hours',
        262800 => '3 days 1 hour',
    ];

    $named = [];

    foreach ($rows as $seconds => $text) {
        $named["{$seconds} seconds"] = [$seconds, $text];
    }

    return $named;
}

it('formats seconds as the time to booked reads', function (int $seconds, string $text) {
    expect(DurationText::format($seconds))->toBe($text);
})->with(fn (): array => durationTextRows());
