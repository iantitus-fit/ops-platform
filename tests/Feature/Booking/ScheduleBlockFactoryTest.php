<?php

use App\Models\ScheduleBlock;
use Illuminate\Support\Carbon;

it('stores a block given on the business clock as the right moment in UTC', function () {
    $block = ScheduleBlock::factory()->at(Carbon::parse('2026-11-05 10:00', 'America/Chicago'), 120)->create();

    $stored = $block->fresh();

    expect($stored->getRawOriginal('starts_at'))->toStartWith('2026-11-05 16:00:00')
        ->and($stored->getRawOriginal('ends_at'))->toStartWith('2026-11-05 18:00:00')
        ->and($stored->starts_at->utc()->toDateTimeString())->toBe('2026-11-05 16:00:00');
});

it('leaves the moment it was given unchanged', function () {
    $start = Carbon::parse('2026-11-05 10:00', 'America/Chicago');

    ScheduleBlock::factory()->at($start, 120)->create();

    expect($start->toDateTimeString())->toBe('2026-11-05 10:00:00')
        ->and($start->timezone->getName())->toBe('America/Chicago');
});
