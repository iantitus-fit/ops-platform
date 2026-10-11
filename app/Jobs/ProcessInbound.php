<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessInbound implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $messageId) {}

    /**
     * Execute the job.
     *
     * Empty in Phase 1. Sprint 1's front-desk agent runs here and replies to the message
     * (AGNT-02), so every inbound path already queues it.
     */
    public function handle(): void
    {
        //
    }
}
