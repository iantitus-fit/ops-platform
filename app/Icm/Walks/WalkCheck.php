<?php

namespace App\Icm\Walks;

use App\Enums\ReplyLabel;

/**
 * One check from a walk file, read from the owner's own words. The phrase is kept as written so a refusal or a result
 * line can quote it; the kind and the one word after it (a reply label or a service slug) are what the gate looks at.
 */
readonly class WalkCheck
{
    public function __construct(
        public WalkCheckKind $kind,
        public ?string $argument,
        public string $phrase,
    ) {}

    /**
     * Read a phrase from the closed list, or null when it is not on the list. Letter case does not matter, and a curly
     * apostrophe reads the same as a straight one, because a phone keyboard types the curly kind.
     */
    public static function parse(string $phrase): ?self
    {
        $written = trim($phrase);
        $normal = mb_strtolower(str_replace(["\u{2019}", "\u{2018}"], "'", $written));

        foreach (WalkCheckKind::cases() as $kind) {
            if (! $kind->takesWord() && $normal === $kind->phrase()) {
                return new self($kind, null, $written);
            }
        }

        if (preg_match('/^labeled ([a-z_]+)$/', $normal, $matches) === 1 && ReplyLabel::tryFrom($matches[1]) !== null) {
            return new self(WalkCheckKind::Labeled, $matches[1], $written);
        }

        if (preg_match("/^carries the owner's price sentence for ([a-z0-9]+(?:-[a-z0-9]+)*)$/", $normal, $matches) === 1) {
            return new self(WalkCheckKind::CarriesPriceSentence, $matches[1], $written);
        }

        return null;
    }

    /**
     * The phrases an owner may write in one place, for a refusal that lists them: after a customer text, or after a
     * booking or a wait and at the end of the walk.
     *
     * @return list<string>
     */
    public static function vocabulary(bool $afterSays): array
    {
        $kinds = array_filter(
            WalkCheckKind::cases(),
            fn (WalkCheckKind $kind) => $afterSays ? $kind->afterSays() : $kind->afterOutcome(),
        );

        return array_values(array_map(fn (WalkCheckKind $kind) => $kind->phrase(), $kinds));
    }

    /**
     * The first day, date or clock time a piece of text names, as written, or null when it names none. A day is a
     * weekday spelled out or in three letters ("Friday", "fri"), a date is year-month-day, and a clock time is "3pm",
     * "3 pm", "3:30pm" or "15:00". A walk that names one needs a start to anchor it (D-10).
     */
    public static function namedTime(string $text): ?string
    {
        $patterns = [
            '/\b(?:monday|tuesday|wednesday|thursday|friday|saturday|sunday|mon|tue|wed|thu|fri|sat|sun)\b/i',
            '/\b\d{4}-\d{2}-\d{2}\b/',
            '/\b\d{1,2}(?::\d{2})?\s?(?:am|pm)\b/i',
            '/\b(?:[01]?\d|2[0-3]):[0-5]\d\b/',
        ];

        $found = null;
        $foundAt = PHP_INT_MAX;

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches, PREG_OFFSET_CAPTURE) === 1 && $matches[0][1] < $foundAt) {
                $found = $matches[0][0];
                $foundAt = $matches[0][1];
            }
        }

        return $found;
    }
}
