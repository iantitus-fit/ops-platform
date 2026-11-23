<?php

namespace App\Icm;

use App\Enums\SyncOutcome;
use App\Models\IcmVersion;

/**
 * What a sync run did, in a form both the command and the dashboard can show.
 */
readonly class SyncResult
{
    /**
     * @param  list<string>  $errors  Each entry reads "<path>: <message>".
     * @param  list<string>  $notices
     * @param  list<string>  $changelogErrors  Ian's form of the walk lines, for changelog.md only; when empty the changelog prints $errors (10 D-05).
     * @param  list<string>  $staleNotes  One changelog-only line per walk whose recording is stale (10 D-04).
     */
    public function __construct(
        public SyncOutcome $outcome,
        public ?IcmVersion $version,
        public array $errors,
        public array $notices,
        public int $fileCount,
        public string $message,
        public array $changelogErrors = [],
        public array $staleNotes = [],
    ) {}
}
