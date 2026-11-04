<?php

use App\Icm\ActiveConfig;
use App\Rooms\ScheduleAndBooking\BookingSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;

/**
 * The booking file checks (ROOM-12; D-03, D-28): the sync refuses a booking file whose numbers are out of range, whose send
 * window runs backwards, or whose customer lines hold a missing or stray placeholder, an exclamation point or an em dash.
 * Each refusal is a plain line that starts with the file's path and names the field, and the previous version stays live.
 * BookingSettings reads the live file back typed, and is null when there is no file, because code holds no default line (D-03).
 */
const BOOKING_PATH = 'rooms/schedule_and_booking/booking.md';

/**
 * Swap one line of the example booking file for another, by the line's key.
 */
function replaceBookingLine(string $key, string $line): void
{
    breakFile(BOOKING_PATH, fn (string $text): string => preg_replace('/^'.preg_quote($key, '/').':.*$/m', $line, $text, 1));
}

it('refuses a broken booking file in plain words', function (string $key, string $line, string $field) {
    $live = activateExampleFolder();
    replaceBookingLine($key, $line);

    $this->artisan('icm:sync')->assertExitCode(1);

    $errors = rejectedErrors();

    expect($errors)->not->toBeEmpty()
        ->and($errors[0])->toStartWith(BOOKING_PATH.': ')
        ->and($errors[0])->toContain($field)
        ->and(app(ActiveConfig::class)->versionId())->toBe($live->id);
})->with([
    'step_minutes missing' => ['step_minutes', '', 'step_minutes'],
    'step_minutes a string' => ['step_minutes', 'step_minutes: "60"', 'step_minutes'],
    'drive_buffer_minutes negative' => ['drive_buffer_minutes', 'drive_buffer_minutes: -5', 'drive_buffer_minutes'],
    'days_ahead zero' => ['days_ahead', 'days_ahead: 0', 'days_ahead'],
    'link_expires_days zero' => ['link_expires_days', 'link_expires_days: 0', 'link_expires_days'],
    'reminder_hours_before repeated' => ['reminder_hours_before', 'reminder_hours_before: [24, 24]', 'reminder_hours_before'],
    'reminder_hours_before zero' => ['reminder_hours_before', 'reminder_hours_before: [0]', 'reminder_hours_before'],
    'send_window backwards' => ['send_window', 'send_window: {start: "20:00", end: "08:00"}', 'send_window'],
    'send_window start not a time' => ['send_window', 'send_window: {start: "8am", end: "20:00"}', 'send_window'],
    'link_line without the link' => ['link_line', 'link_line: "Pick a time that works for you."', 'link_line'],
    'link_line with the link twice' => ['link_line', 'link_line: "Book here {link} or here {link}"', 'link_line'],
    'link_line with a service' => ['link_line', 'link_line: "Book {service} here: {link}"', 'link_line'],
    'confirmation_line without the time' => ['confirmation_line', 'confirmation_line: "You are booked on {day}."', 'confirmation_line'],
    'confirmation_line with a name' => ['confirmation_line', 'confirmation_line: "Hi {name}, you are booked on {day} at {time}."', 'confirmation_line'],
    'reminder_line with the day twice' => ['reminder_line', 'reminder_line: "A reminder: {day} {day} at {time}."', 'reminder_line'],
    'link_line ending with an exclamation point' => ['link_line', 'link_line: "Pick a time here: {link}!"', 'link_line'],
    'confirmation_line with an em dash' => ['confirmation_line', 'confirmation_line: "You are booked on {day} at {time} — see you then."', 'confirmation_line'],
]);

it('accepts the example booking file and an empty reminder list', function () {
    replaceBookingLine('reminder_hours_before', 'reminder_hours_before: []');

    $this->artisan('icm:sync')->assertExitCode(0);

    expect(BookingSettings::fromConfig(app(ActiveConfig::class))->reminderHoursBefore)->toBe([]);
});

it('refuses a second booking file', function () {
    $live = activateExampleFolder();
    File::copy(icmPath(BOOKING_PATH), icmPath('rooms/schedule_and_booking/booking-2.md'));

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe(['rooms/schedule_and_booking/booking-2.md: this business already has a booking file in '.BOOKING_PATH.'; keep one'])
        ->and(app(ActiveConfig::class)->versionId())->toBe($live->id);
});

it('reads the live file back typed', function () {
    activateExampleFolder();

    $settings = BookingSettings::fromConfig(app(ActiveConfig::class));

    expect($settings->stepMinutes)->toBe(60)
        ->and($settings->driveBufferMinutes)->toBe(30)
        ->and($settings->daysAhead)->toBe(14)
        ->and($settings->linkExpiresDays)->toBe(7)
        ->and($settings->reminderHoursBefore)->toBe([24])
        ->and($settings->sendWindowStart)->toBe('08:00')
        ->and($settings->sendWindowEnd)->toBe('20:00')
        ->and($settings->renderLink('https://x.test/book/abc'))->toBe('Pick a time that works for you here: https://x.test/book/abc');

    $startsAt = CarbonImmutable::parse('2026-11-05 16:00', 'UTC');

    expect($settings->renderConfirmation('AC repair', $startsAt, 'America/Chicago'))
        ->toBe('You are booked for AC repair on Thursday, November 5 at 10:00 AM. To change it, reply to this text.')
        ->and($settings->renderReminder('AC repair', $startsAt, 'America/Chicago'))
        ->toBe('A reminder: AC repair is on Thursday, November 5 at 10:00 AM. To change it, reply to this text.');
});

it('is null with no booking file or with the room off', function (string $drop) {
    File::deleteDirectory(icmPath('rooms/schedule_and_booking'));

    if ($drop === 'file') {
        writeIcmFile('rooms/schedule_and_booking/CONTEXT.md', File::get(base_path('examples/example-hvac/rooms/schedule_and_booking/CONTEXT.md')));
    }

    activateExampleFolder();

    expect(BookingSettings::fromConfig(app(ActiveConfig::class)))->toBeNull();
})->with([
    'no booking file' => 'file',
    'the room off' => 'room',
]);
