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
        $wrote = '(you wrote '.self::value($value).')';

        return match ($rule) {
            'Required', 'Present' => "{$field} is missing, and it is required",
            'In' => "{$field} must be one of ".implode(', ', $parameters)." {$wrote}",
            'RequiredUnless' => "{$field} is missing; it is required unless {$parameters[0]} is {$parameters[1]}",
            default => "{$field} is not valid {$wrote}",
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
            is_string($value) => self::text($value),
            is_array($value) => array_is_list($value) ? 'a list' : 'a group of fields',
            default => 'something that is not text',
        };
    }

    /**
     * Text cut to 80 characters with line breaks turned into spaces, so one value stays one line.
     * Text that looks like a number is quoted, because that is how the owner would have to write it.
     */
    private static function text(string $value): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $value));

        if (is_numeric($text)) {
            $text = "\"{$text}\"";
        }

        return Str::limit($text, 80, '...', preserveWords: false);
    }
}
