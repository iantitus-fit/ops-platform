<?php

namespace App\Icm\Walks;

use App\Models\IcmVersion;

/**
 * The folder as it is on disk, read the way a sync reads it but never made live: its compiled copy when it passes its
 * checks, or the lines that say why it does not, and the version live now. walks:record plays walks against it (10 D-03).
 */
readonly class WalkCandidate
{
    /**
     * @param  array<string, mixed>|null  $compiled  The candidate's compiled copy, or null when it did not pass its checks.
     * @param  list<string>  $errors  One line for each check the candidate failed; empty when it passed.
     * @param  IcmVersion|null  $live  The version live now, or null when nothing is.
     */
    public function __construct(
        public ?array $compiled,
        public array $errors,
        public ?IcmVersion $live,
    ) {}
}
