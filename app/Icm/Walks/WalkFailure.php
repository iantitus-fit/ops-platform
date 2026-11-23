<?php

namespace App\Icm\Walks;

/**
 * The first check of a walk that did not hold: which item of the script it sat under (null when it was one of the end
 * checks) and the check in plain words. The owner line that names the file and the live version is composed by the gate.
 */
readonly class WalkFailure
{
    public function __construct(
        public ?int $turn,
        public string $words,
    ) {}
}
