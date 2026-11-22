<?php

namespace App\Icm\Walks;

/**
 * What one replayed walk came to: which walk, how it ended, the first thing the recording could not answer (when it
 * ran out), and the tape it was played from. Plan 10-04 fills the failure, the turns and the fingerprint; until then
 * they are empty.
 */
readonly class WalkResult
{
    /**
     * @param  array{turn: int, needed: string}|null  $exhausted  The first turn and call the recording did not hold.
     * @param  array<string, mixed>|null  $failure  The check that did not hold (plan 10-04).
     * @param  array<int, array<string, mixed>>|null  $turns  What happened in each turn (plan 10-04).
     */
    public function __construct(
        public string $path,
        public WalkOutcome $outcome,
        public ?array $exhausted,
        public WalkTape $tape,
        public ?array $failure = null,
        public ?array $turns = null,
        public ?string $fingerprint = null,
    ) {}
}
