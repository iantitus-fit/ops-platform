<?php

namespace App\Jobs;

use App\Actions\ClaimInboundBurst;
use App\Actions\RunFrontDesk;
use App\Enums\MessageChannel;
use App\Icm\Exceptions\NoActiveVersion;
use App\Models\Message;
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
 * handle(). The front desk runs here, after the claim.
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
     * that finds nothing to claim has nothing to answer and stops. So does a burst with no text
     * from the customer: the agent only answers a number that has texted the business, so web
     * form leads are claimed and then stop here, and reach the owner through the dashboard and
     * open-leads.md (D-41).
     *
     * Until a first business folder is live there is no agent to ask, so the burst is claimed and left quietly
     * (Pitfall 7) instead of failing the job.
     *
     * @return bool true when the burst holds a text from the customer and went on to the front desk
     */
    public function handle(ClaimInboundBurst $claimInboundBurst, RunFrontDesk $runFrontDesk): bool
    {
        $burst = $claimInboundBurst->handle($this->conversationId);

        if ($burst->isEmpty()) {
            return false;
        }

        if (! $burst->contains(fn (Message $message): bool => $message->channel === MessageChannel::Sms)) {
            return false;
        }

        try {
            $runFrontDesk->handle($burst->first()->conversation, $burst);
        } catch (NoActiveVersion) {
            return true;
        }

        return true;
    }
}
