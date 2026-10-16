<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PingSafetyCheck implements ShouldQueue
{
    use Queueable;

    public const string TEXT = 'Hi, do you work on heat pumps?';

    public function handle(): void
    {
        //
    }
}
