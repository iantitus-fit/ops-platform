<?php

namespace App\Jobs;

use App\Actions\ClaimInboundBurst;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\DebounceFor;

/**
 * Handles the inbound customer texts of one conversation as a burst.
 *
 * Each text queues this job through DispatchInbound; the debounce makes the job wait about 20
 * seconds after the last text in the conversation (at most 90 seconds from the first), so a
 * burst of texts is answered together. A debounced job cannot also be unique, and the debounce
 * token lives in the cache and can fail open, so the real guarantee is the database claim in
 * handle(). Plan 02-14 runs the front desk here, after the claim.
 */
#[DebounceFor(20, maxWait: 90)]
class ProcessInbound implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $messageId, public int $conversationId) {}

    /**
     * Debounce by conversation, so every text in one conversation shares one waiting job.
     */
    public function debounceId(): string
    {
        return (string) $this->conversationId;
    }

    /**
     * Execute the job.
     *
     * The first step claims every unhandled inbound customer message in the conversation. A run
     * that finds nothing to claim has nothing to answer and stops.
     */
    public function handle(ClaimInboundBurst $claimInboundBurst): void
    {
        $burst = $claimInboundBurst->handle($this->conversationId);

        if ($burst->isEmpty()) {
            return;
        }
    }
}
