<?php

use App\Jobs\SendDueBookingTexts;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

it('runs the sweep every minute without overlapping', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn (Event $event): bool => $event->description === SendDueBookingTexts::class);

    expect($events)->toHaveCount(1)
        ->and($events->first()->expression)->toBe('* * * * *')
        ->and($events->first()->withoutOverlapping)->toBeTrue();
});
