<?php

namespace App\Icm\Exceptions;

use RuntimeException;

class IcmFolderMissing extends RuntimeException
{
    public function __construct(string $root)
    {
        parent::__construct("The business folder was not found at {$root}. Set ICM_PATH in .env to the folder's full path.");
    }
}
