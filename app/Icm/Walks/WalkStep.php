<?php

namespace App\Icm\Walks;

/**
 * One item of a walk's script. Its turn is its position in the file's script, counting from 1, so the owner can count
 * lines to find it. A customer text carries the words, a wait carries minutes, and a booking carries the time to book
 * or null for the earliest time offered.
 */
readonly class WalkStep
{
    /**
     * @param  list<WalkCheck>  $checks
     */
    public function __construct(
        public int $turn,
        public WalkStepKind $kind,
        public ?string $says,
        public ?int $waitMinutes,
        public ?string $booksAt,
        public array $checks,
    ) {}

    /**
     * A wait as written ("20 minutes", "2 hours", "1 day") in minutes, or null when it is not a whole number of
     * minutes, hours or days.
     */
    public static function waitMinutes(string $text): ?int
    {
        if (preg_match('/^\s*(\d+)\s+(minute|hour|day)s?\s*$/i', $text, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1] * match (strtolower($matches[2])) {
            'minute' => 1,
            'hour' => 60,
            default => 1440,
        };
    }
}
