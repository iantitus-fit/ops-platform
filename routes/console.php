<?php

use App\Icm\ActiveConfig;
use App\Jobs\PingSafetyCheck;
use App\Jobs\SendDueAlerts;
use App\Jobs\SendDueBookingTexts;
use App\Jobs\SweepUndispatched;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Safety net for inbound texts that were recorded but never queued (D-42).
Schedule::job(new SweepUndispatched)->everyFiveMinutes()->withoutOverlapping();

// Sends owner alerts held until opening time, unless their draft was handled first (D-20).
Schedule::job(new SendDueAlerts)->everyMinute()->withoutOverlapping();

// Sends booking confirmations and reminders when they come due, once each (D-31).
Schedule::job(new SendDueBookingTexts)->everyMinute()->withoutOverlapping();

// Asks Jev one fixed fictional question, so the owner hears the safety check is down even when no customer texts (D-50).
Schedule::job(new PingSafetyCheck)->everyFiveMinutes()->withoutOverlapping();

// The platform's nightly rebuild of records/ just after midnight in the business's time zone. The zone is read when the
// scheduler asks, not when this file loads, so a changed zone takes effect on the next run (D-09, D-10). Any tick from
// 00:10 to before 01:00 business time lets it through, once per business date: the date is worked out once, in the
// filter, and marked done only after a run that exited 0, so a late tick still runs it and a failed run is tried again
// on the next tick until 01:00 (09-REVIEW WR-03, Ian, 2026-11-17).
$nightlyRebuildDate = null;

Schedule::command('records:rebuild')
    ->name('records:rebuild')
    ->everyMinute()
    ->when(function (ActiveConfig $config) use (&$nightlyRebuildDate): bool {
        $nightlyRebuildDate = null;

        try {
            $local = now($config->displayTimezone());
            $clock = $local->format('H:i');

            if ($clock < '00:10' || $clock >= '01:00' || Cache::has('records:rebuild:done:'.$local->toDateString())) {
                return false;
            }

            $nightlyRebuildDate = $local->toDateString();

            return true;
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    })
    ->onSuccess(function () use (&$nightlyRebuildDate): void {
        if ($nightlyRebuildDate !== null) {
            Cache::put('records:rebuild:done:'.$nightlyRebuildDate, true, now()->addDays(2));
        }
    })
    ->withoutOverlapping(120)
    ->onOneServer();
