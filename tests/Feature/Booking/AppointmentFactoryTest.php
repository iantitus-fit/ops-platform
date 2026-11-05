<?php

use App\Models\Appointment;
use Illuminate\Support\Carbon;

it('stores a visit given on the business clock as the right moment in UTC', function () {
    $visit = Appointment::factory()->at(Carbon::parse('2026-11-05 10:00', 'America/Chicago'))->create();

    $stored = $visit->fresh();

    expect($stored->getRawOriginal('starts_at'))->toStartWith('2026-11-05 16:00:00')
        ->and($stored->getRawOriginal('ends_at'))->toStartWith('2026-11-05 17:30:00')
        ->and($stored->starts_at->utc()->toDateTimeString())->toBe('2026-11-05 16:00:00');
});

it('leaves the moment it was given unchanged', function () {
    $start = Carbon::parse('2026-11-05 10:00', 'America/Chicago');

    Appointment::factory()->at($start)->create();

    expect($start->toDateTimeString())->toBe('2026-11-05 10:00:00')
        ->and($start->timezone->getName())->toBe('America/Chicago');
});
