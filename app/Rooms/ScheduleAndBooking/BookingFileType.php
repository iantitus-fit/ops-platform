<?php

namespace App\Rooms\ScheduleAndBooking;

use App\Icm\IcmFile;
use App\Rooms\RoomFileType;

/**
 * The schedule and booking room's one new kind of file: the booking file, rooms/schedule_and_booking/booking.md. It
 * holds every booking setting (D-03). The rules and checks ride on this class, the room standard (04 D-04); the
 * Validator itself is not edited for the room.
 */
class BookingFileType implements RoomFileType
{
    public function type(): string
    {
        return 'booking';
    }

    /**
     * The booking file's field rules (D-03). The numbers carry the planner's limits: step 5 to 240 minutes, buffer 0 to 240,
     * 1 to 60 days ahead, links lasting 1 to 30 days, at most five reminders of 1 to 336 hours, and lines up to 300
     * characters (link, confirmation, reminder and visit lines). The reminder list may be empty (no reminders), so it is `present`, not `required`.
     *
     * @return array<string, mixed>
     */
    public function rules(IcmFile $file): array
    {
        return [
            'step_minutes' => 'bail|required|integer:strict|min:5|max:240',
            'drive_buffer_minutes' => 'bail|required|integer:strict|min:0|max:240',
            'days_ahead' => 'bail|required|integer:strict|min:1|max:60',
            'link_expires_days' => 'bail|required|integer:strict|min:1|max:30',
            'reminder_hours_before' => 'bail|present|array|max:5',
            'reminder_hours_before.*' => 'bail|integer:strict|min:1|max:336|distinct',
            'send_window' => 'bail|required|array',
            'send_window.start' => 'bail|required|string',
            'send_window.end' => 'bail|required|string',
            'link_line' => 'bail|required|string|max:300',
            'confirmation_line' => 'bail|required|string|max:300',
            'reminder_line' => 'bail|required|string|max:300',
            'visit_line' => 'bail|required|string|max:300',
        ];
    }

    /**
     * What a rule string cannot say (D-03, D-28; the placeholders are the planner's). The send window is two 24-hour times,
     * start before end. The link line holds {link} exactly once and no other word in braces. The confirmation, reminder and
     * visit lines (D-39 amended) each hold {day} and {time} exactly once, {service} at most once, and no other word in braces. No line holds an
     * exclamation point or an em dash (02.1 D-41). Every message names the field and at most the placeholder, never the
     * owner's line, so a refusal cannot carry customer wording into a stored version or changelog.md (T-08-17).
     *
     * @return list<array{file: string, field: ?string, message: string}>
     */
    public function checks(IcmFile $file): array
    {
        $errors = [];
        $front = $file->frontMatter;
        $window = is_array($front['send_window'] ?? null) ? $front['send_window'] : [];
        $start = $window['start'] ?? null;
        $end = $window['end'] ?? null;

        foreach (['start' => $start, 'end' => $end] as $side => $time) {
            if (is_string($time) && ! $this->isTime($time)) {
                $errors[] = $this->error($file, "send_window.{$side}", "send_window {$side} must be a 24-hour time such as 08:00");
            }
        }

        if ($this->isTime($start) && $this->isTime($end) && strcmp($start, $end) >= 0) {
            $errors[] = $this->error($file, 'send_window', 'send_window start must be earlier than its end');
        }

        array_push(
            $errors,
            ...$this->lineErrors($file, 'link_line', ['link' => [1, 1]]),
            ...$this->lineErrors($file, 'confirmation_line', ['day' => [1, 1], 'time' => [1, 1], 'service' => [0, 1]]),
            ...$this->lineErrors($file, 'reminder_line', ['day' => [1, 1], 'time' => [1, 1], 'service' => [0, 1]]),
            ...$this->lineErrors($file, 'visit_line', ['day' => [1, 1], 'time' => [1, 1], 'service' => [0, 1]]),
        );

        return $errors;
    }

    /**
     * A business keeps one booking file (D-03). The check in App\Icm\RoomChecks refuses every other file of this type
     * in rooms/schedule_and_booking/, wherever the room's contract owns points.
     */
    public function singleFileLabel(): ?string
    {
        return 'booking file';
    }

    /**
     * Where the one booking file lives (D-03); the SPEC file types table gives the same path.
     */
    public function singleFileDefaultPath(): ?string
    {
        return 'rooms/schedule_and_booking/booking.md';
    }

    /**
     * The errors for one customer line. $allowed maps each placeholder the line may use to its [fewest, most] uses; any
     * other word in braces is refused.
     *
     * @param  array<string, array{0: int, 1: int}>  $allowed
     * @return list<array{file: string, field: ?string, message: string}>
     */
    private function lineErrors(IcmFile $file, string $field, array $allowed): array
    {
        $line = $file->frontMatter[$field] ?? null;

        if (! is_string($line)) {
            return [];
        }

        $errors = [];
        preg_match_all('/\{([^{}]*)\}/', $line, $found);
        $counts = array_count_values($found[1]);
        $names = array_keys($allowed);

        if (array_diff(array_keys($counts), $names) !== []) {
            $errors[] = $this->error($file, $field, "{$field} may use only ".$this->listed($names));
        }

        foreach ($allowed as $name => [$fewest, $most]) {
            $uses = $counts[$name] ?? 0;

            if ($uses < $fewest || $uses > $most) {
                $errors[] = $this->error($file, $field, $fewest === 1
                    ? "{$field} must hold {{$name}} exactly once"
                    : "{$field} may use {{$name}} at most once");
            }
        }

        if (str_contains($line, '!')) {
            $errors[] = $this->error($file, $field, "{$field} has an exclamation point; texts to customers never use one");
        }

        if (str_contains($line, "\u{2014}")) {
            $errors[] = $this->error($file, $field, "{$field} has an em dash; texts to customers never use one");
        }

        return $errors;
    }

    /**
     * Placeholder names as an owner reads them: "{service}, {day} and {time}".
     *
     * @param  list<string>  $names
     */
    private function listed(array $names): string
    {
        $braced = array_map(fn (string $name): string => '{'.$name.'}', $names);
        $last = array_pop($braced);

        return $braced === [] ? $last : implode(', ', $braced).' and '.$last;
    }

    private function isTime(mixed $value): bool
    {
        return is_string($value) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) === 1;
    }

    /**
     * @return array{file: string, field: ?string, message: string}
     */
    private function error(IcmFile $file, string $field, string $message): array
    {
        return ['file' => $file->path, 'field' => $field, 'message' => $message];
    }
}
