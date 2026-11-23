<?php

namespace App\Icm\Walks;

/**
 * What the gate found for one walk file of the candidate: whether a recording was held for it, how the replay ended,
 * the first check that failed, the first call the recording could not answer, whether the recording is stale and, when
 * the walk could not run at all, the error's message on one line.
 */
readonly class WalkGateEntry
{
    /**
     * @param  array{turn: int, needed: string}|null  $exhausted  The first turn and call the recording did not hold.
     */
    public function __construct(
        public string $path,
        public bool $recorded,
        public ?WalkOutcome $outcome,
        public ?WalkFailure $failure,
        public ?array $exhausted,
        public bool $stale,
        public ?string $error,
    ) {}
}
