<?php

use App\Rooms\ScheduleAndBooking\BookingSettings;
use App\Rooms\ScheduleAndBooking\ReminderSchedule;
use Carbon\CarbonImmutable;

/**
 * These are the D-28 and D-29 examples and edges, to the minute. Unit tests do not boot Laravel: ReminderSchedule takes
 * the settings and the zone as arguments, so Carbon and a directly built BookingSettings are all that is needed. The
 * business is on America/Chicago with a send window of 08:00 to 20:00 unless a case says otherwise.
 */
const REMINDER_ZONE = 'America/Chicago';

/**
 * A moment on the business's wall clock.
 */
function reminderAt(string $dateTime): CarbonImmutable
{
    return CarbonImmutable::parse($dateTime, REMINDER_ZONE);
}

/**
 * The booking settings with the given reminder hours and the 08:00 to 20:00 window.
 *
 * @param  list<int>  $hours
 */
function reminderSettings(array $hours): BookingSettings
{
    return new BookingSettings(
        stepMinutes: 30,
        driveBufferMinutes: 0,
        daysAhead: 30,
        linkExpiresDays: 7,
        reminderHoursBefore: $hours,
        sendWindowStart: '08:00',
        sendWindowEnd: '20:00',
        linkLine: 'Pick a time: {link}',
        confirmationLine: 'Booked {service} {day} {time}.',
        reminderLine: 'Reminder {service} {day} {time}.',
    );
}

/**
 * The rows for a booking, each as lead minutes => the due time on the business's wall clock.
 *
 * @param  list<int>  $hours
 * @return array<int, string>
 */
function reminderDueTimes(string $startsAt, string $bookedAt, bool $pageBooking, array $hours): array
{
    $due = [];

    foreach (ReminderSchedule::rows(reminderAt($startsAt), reminderAt($bookedAt), $pageBooking, reminderSettings($hours), REMINDER_ZONE) as $row) {
        $due[$row['lead_minutes']] = $row['due_at']->setTimezone(REMINDER_ZONE)->format('Y-m-d H:i');
    }

    return $due;
}

it('moves a 2-hour reminder for an 8 AM visit to 8 PM the evening before', function () {
    $due = reminderDueTimes('2026-11-06 08:00', '2026-11-03 11:00', false, [2]);

    expect($due[120])->toBe('2026-11-05 20:00');
});

it('keeps a 24-hour reminder for an 8 AM visit at 8 AM the day before', function () {
    $due = reminderDueTimes('2026-11-06 08:00', '2026-11-03 11:00', false, [24]);

    expect($due[1440])->toBe('2026-11-05 08:00');
});

it('keeps a reminder due exactly at the window end', function () {
    $due = reminderDueTimes('2026-11-06 22:00', '2026-11-03 11:00', false, [2]);

    expect($due[120])->toBe('2026-11-06 20:00');
});

it('moves a reminder due a minute after the window end to that day\'s window end', function () {
    $due = reminderDueTimes('2026-11-06 22:01', '2026-11-03 11:00', false, [2]);

    expect($due[120])->toBe('2026-11-06 20:00');
});

it('moves a reminder due a minute before the window start to the previous day\'s window end', function () {
    $due = reminderDueTimes('2026-11-06 09:59', '2026-11-03 11:00', false, [2]);

    expect($due[120])->toBe('2026-11-05 20:00');
});

it('skips a reminder already past at booking', function () {
    $due = reminderDueTimes('2026-11-05 15:00', '2026-11-05 09:00', false, [24]);

    expect($due)->not->toHaveKey(1440);
});

it('skips a moved reminder already past at booking', function () {
    $due = reminderDueTimes('2026-11-06 08:00', '2026-11-05 20:30', false, [2]);

    expect($due)->not->toHaveKey(120);
});

it('keeps one row for two reminders that land together, with the larger lead', function () {
    $due = reminderDueTimes('2026-11-06 08:00', '2026-11-03 11:00', false, [2, 3]);

    expect($due)->not->toHaveKey(120)
        ->and($due[180])->toBe('2026-11-05 20:00');
});

it('writes only the confirmation with no reminders', function () {
    $due = reminderDueTimes('2026-11-06 08:00', '2026-11-03 11:00', false, []);

    expect(array_keys($due))->toBe([0]);
});

it('sends a page booking\'s confirmation whatever the hour', function () {
    $rows = ReminderSchedule::rows(reminderAt('2026-11-05 10:00'), reminderAt('2026-11-03 22:00'), true, reminderSettings([]), REMINDER_ZONE);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['lead_minutes'])->toBe(0)
        ->and($rows[0]['due_at']->setTimezone(REMINDER_ZONE)->format('Y-m-d H:i'))->toBe('2026-11-03 22:00')
        ->and($rows[0]['ignores_window'])->toBeTrue();
});

it('holds a hand booking\'s confirmation for the window', function () {
    $rows = ReminderSchedule::rows(reminderAt('2026-11-05 10:00'), reminderAt('2026-11-03 22:00'), false, reminderSettings([]), REMINDER_ZONE);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['due_at']->setTimezone(REMINDER_ZONE)->format('Y-m-d H:i'))->toBe('2026-11-04 08:00')
        ->and($rows[0]['ignores_window'])->toBeFalse();
});

it('sends a hand booking\'s confirmation at once inside the window', function () {
    $due = reminderDueTimes('2026-11-05 10:00', '2026-11-03 20:00', false, []);

    expect($due[0])->toBe('2026-11-03 20:00');
});

it('opens a hand booking\'s confirmation at the window start when booked before it opens', function () {
    $due = reminderDueTimes('2026-11-03 13:00', '2026-11-03 06:00', false, []);

    expect($due[0])->toBe('2026-11-03 08:00');
});

it('writes no confirmation for a hand booking when the window opens after the visit starts', function () {
    $due = reminderDueTimes('2026-11-03 07:30', '2026-11-03 06:00', false, []);

    expect($due)->toBe([]);
});

it('keeps elapsed hours across the spring-forward night', function () {
    $rows = ReminderSchedule::rows(reminderAt('2026-03-08 12:00'), reminderAt('2026-03-02 11:00'), false, reminderSettings([24]), REMINDER_ZONE);
    $reminder = collect($rows)->firstWhere('lead_minutes', 1440);

    expect($reminder['due_at']->utc()->format('Y-m-d H:i'))->toBe('2026-03-07 17:00')
        ->and($reminder['due_at']->setTimezone(REMINDER_ZONE)->format('H:i'))->toBe('11:00');
});

it('moves to the right wall-clock evening across the spring-forward night', function () {
    $rows = ReminderSchedule::rows(reminderAt('2026-03-09 08:00'), reminderAt('2026-03-02 11:00'), false, reminderSettings([2]), REMINDER_ZONE);
    $reminder = collect($rows)->firstWhere('lead_minutes', 120);

    expect($reminder['due_at']->utc()->format('Y-m-d H:i'))->toBe('2026-03-09 01:00')
        ->and($reminder['due_at']->setTimezone(REMINDER_ZONE)->format('Y-m-d H:i'))->toBe('2026-03-08 20:00');
});

it('keeps elapsed hours across the fall-back night', function () {
    $rows = ReminderSchedule::rows(reminderAt('2026-11-02 08:00'), reminderAt('2026-10-28 11:00'), false, reminderSettings([14]), REMINDER_ZONE);
    $reminder = collect($rows)->firstWhere('lead_minutes', 840);

    expect($reminder['due_at']->utc()->format('Y-m-d H:i'))->toBe('2026-11-02 00:00')
        ->and($reminder['due_at']->setTimezone(REMINDER_ZONE)->format('Y-m-d H:i'))->toBe('2026-11-01 18:00');
});

it('keeps a reminder that lands exactly on the window end on the fall-back night', function () {
    $rows = ReminderSchedule::rows(reminderAt('2026-11-01 08:00'), reminderAt('2026-10-28 11:00'), false, reminderSettings([13]), REMINDER_ZONE);
    $reminder = collect($rows)->firstWhere('lead_minutes', 780);

    expect($reminder['due_at']->utc()->format('Y-m-d H:i'))->toBe('2026-11-01 01:00')
        ->and($reminder['due_at']->setTimezone(REMINDER_ZONE)->format('Y-m-d H:i'))->toBe('2026-10-31 20:00');
});

it('moves to the right wall-clock evening across the fall-back night', function () {
    $rows = ReminderSchedule::rows(reminderAt('2026-11-01 08:00'), reminderAt('2026-10-28 11:00'), false, reminderSettings([2]), REMINDER_ZONE);
    $reminder = collect($rows)->firstWhere('lead_minutes', 120);

    expect($reminder['due_at']->utc()->format('Y-m-d H:i'))->toBe('2026-11-01 01:00')
        ->and($reminder['due_at']->setTimezone(REMINDER_ZONE)->format('Y-m-d H:i'))->toBe('2026-10-31 20:00');
});

it('returns the rows earliest due first', function () {
    $due = reminderDueTimes('2026-11-06 10:00', '2026-11-02 11:00', false, [24, 72, 5]);

    expect(array_keys($due))->toBe([0, 4320, 1440, 300]);
});

it('reads no configuration and writes nothing', function () {
    $source = file_get_contents(dirname(__DIR__, 3).'/app/Rooms/ScheduleAndBooking/ReminderSchedule.php');

    expect($source)->not->toContain('use App\\Icm\\ActiveConfig')
        ->and($source)->not->toContain('use Illuminate')
        ->and($source)->not->toContain('->create(')
        ->and($source)->not->toContain('->save(');
});
