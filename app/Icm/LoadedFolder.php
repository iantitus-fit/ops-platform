<?php

namespace App\Icm;

/**
 * Everything the Loader found in the business folder.
 */
readonly class LoadedFolder
{
    /**
     * @param  list<IcmFile>  $files
     * @param  list<string>  $notices
     */
    public function __construct(
        public array $files,
        public array $notices = [],
    ) {}
}
