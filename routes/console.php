<?php

use App\Jobs\PingSafetyCheck;
use App\Jobs\SendDueAlerts;
use App\Jobs\SendDueBookingTexts;
use App\Jobs\SweepUndispatched;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
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
