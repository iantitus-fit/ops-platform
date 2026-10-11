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
     */
    public function __construct(
        public SyncOutcome $outcome,
        public ?IcmVersion $version,
        public array $errors,
        public array $notices,
        public int $fileCount,
        public string $message,
    ) {}
}
