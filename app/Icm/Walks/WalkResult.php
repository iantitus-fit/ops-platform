<?php

namespace App\Icm\Walks;

/**
 * What one replayed walk came to: which walk, how it ended, the first thing the recording could not answer (when it
 * ran out), the tape it was played from, the first check that did not hold, what the candidate's code did in each step
 * played, and the fingerprint of the candidate (plan 10-04 fills the fingerprint in its last task).
 */
readonly class WalkResult
{
    /**
     * @param  array{turn: int, needed: string}|null  $exhausted  The first turn and call the recording did not hold.
     * @param  WalkFailure|null  $failure  The first check that did not hold, with its turn (null for an end check).
     * @param  list<WalkTurnObservation>|null  $turns  What the candidate's code did in each step played, in script order.
     */
    public function __construct(
        public string $path,
        public WalkOutcome $outcome,
        public ?array $exhausted,
        public WalkTape $tape,
        public ?WalkFailure $failure = null,
        public ?array $turns = null,
        public ?string $fingerprint = null,
    ) {}
}
