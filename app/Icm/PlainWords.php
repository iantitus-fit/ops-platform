<?php

namespace App\Icm;

use Illuminate\Support\Str;

/**
 * Turns a failed validation rule into a sentence an owner can act on.
 */
class PlainWords
{
    /**
     * @param  string  $rule  The rule name from Validator::failed(), such as "In".
     * @param  list<mixed>  $parameters  The rule's parameters from Validator::failed().
     * @param  string  $field  The dotted path to the field that failed.
     * @param  mixed  $value  What the owner wrote there, or null when nothing was written.
     */
    public static function render(string $rule, array $parameters, string $field, mixed $value): string
    {
        $name = self::label($field);
        $wrote = '(you wrote '.self::value($value).')';

        return match ($rule) {
            'Required', 'Present' => "{$name} is missing, and it is required",
            'In' => "{$name} must be one of ".implode(', ', $parameters)." {$wrote}",
            'RequiredUnless' => "{$name} is missing; it is required unless ".self::label((string) $parameters[0])." is {$parameters[1]}",
            'Integer' => "{$name} must be a whole number {$wrote}",
            'Boolean' => "{$name} must be true or false {$wrote}",
            'String' => "{$name} must be text; wrap it in quotes {$wrote}",
            'Array' => "{$name} must be a list {$wrote}",
            'Min' => is_array($value)
                ? "{$name} must have at least {$parameters[0]} item(s)"
                : "{$name} must be {$parameters[0]} or more {$wrote}",
            'Timezone' => "{$name} must be a time zone such as America/Chicago {$wrote}",
            'DateFormat' => match ($parameters[0] ?? null) {
                'H:i' => "{$name} must be a 24-hour time such as 08:00 {$wrote}",
                'Y-m-d' => "{$name} must be a date such as 2026-12-25 {$wrote}",
                default => "{$name} is not valid {$wrote}",
            },
            'Regex' => "{$name} must use only lowercase letters, numbers, dashes or underscores {$wrote}",
            default => "{$name} is not valid {$wrote}",
        };
    }

    /**
     * An owner-written value as it should appear inside a message: one short line of text.
     */
    public static function value(mixed $value): string
    {
        return match (true) {
            $value === null => 'nothing',
            is_bool($value) => $value ? 'true' : 'false',
            is_int($value), is_float($value) => (string) $value,
            is_string($value) => self::quoteIfNumeric(self::oneLine($value)),
            is_array($value) => array_is_list($value) ? 'a list' : 'a group of fields',
            default => 'something that is not text',
        };
    }

    /**
     * Owner-written text cut to 80 characters with line breaks turned into spaces, so a value
     * can never start a new line, heading or bullet in changelog.md.
     */
    public static function oneLine(string $text): string
    {
        return Str::limit(trim((string) preg_replace('/\s+/u', ' ', $text)), 80, '...', preserveWords: false);
    }

    /**
     * Text that looks like a number is quoted, because that is how the owner would have to write it.
     */
    private static function quoteIfNumeric(string $text): string
    {
        return is_numeric($text) ? "\"{$text}\"" : $text;
    }

    /**
     * A dotted field path as an owner would say it: list positions count from 1 as "item N", map keys
     * become words, and the last key follows a colon when the path is nested two levels or more.
     *
     * "sources.1.cost_cents_per_lead" reads "sources item 2: cost_cents_per_lead",
     * "weekly.mon.open" reads "weekly mon: open" and "closures.0" reads "closures item 1".
     */
    private static function label(string $path): string
    {
        $parts = explode('.', $path);
        $words = array_map(fn (string $part) => ctype_digit($part) ? 'item '.((int) $part + 1) : $part, $parts);

        if (count($parts) >= 3 && ! ctype_digit(end($parts))) {
            $last = array_pop($words);

            return implode(' ', $words).": {$last}";
        }

        return implode(' ', $words);
    }
}
