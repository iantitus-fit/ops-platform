<?php

namespace App\Icm\Exceptions;

use RuntimeException;

class NoActiveVersion extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('No business folder version is live yet. Run php artisan icm:sync.');
    }
}
