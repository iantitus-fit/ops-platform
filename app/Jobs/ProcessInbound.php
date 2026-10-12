<?php

namespace App\Jobs;

use App\Actions\ClaimInboundBurst;
use App\Actions\ReportFrontDeskFailure;
use App\Actions\RunFrontDesk;
use App\Enums\MessageAuthor;
use App\Enums\MessageChannel;
use App\Enums\MessageDirection;
use App\Icm\Exceptions\NoActiveVersion;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\DebounceFor;
use Throwable;

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
     * One attempt only: a retry would find the burst already claimed and do nothing.
     */
    public int $tries = 1;

    /**
     * Seconds the worker lets one run last. Above the provider chain budget plus Jev plus the sends.
     */
    public int $timeout = 300;

    /**
     * A run that passes its timeout counts as failed, so failed() runs and the owner is told.
     */
    public bool $failOnTimeout = true;

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
     * Anything else that goes wrong inside the front desk is logged and reported to the owner as a note and an
     * alert in fixed words (CR-01, AGNT-04). The claim is not released: part of the run may already have texted the
     * customer, and a second run could text them again.
     *
     * @return bool true when the burst holds a text from the customer and went on to the front desk
     */
    public function handle(ClaimInboundBurst $claimInboundBurst, RunFrontDesk $runFrontDesk, ReportFrontDeskFailure $reportFrontDeskFailure): bool
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
        } catch (Throwable $exception) {
            report($exception);

            $reportFrontDeskFailure->handle($burst->first()->conversation, $burst->last(), 'it stopped with an error', 'Front desk stopped with an error');
        }

        return true;
    }

    /**
     * Tell the owner a claimed burst was never answered because the run was stopped.
     *
     * Laravel calls this when the job times out or is failed after its worker died. The burst stays claimed, so the
     * customer cannot get two replies, and ReportFrontDeskFailure skips a text it already reported. The exception's
     * words are never copied into the note or the alert.
     */
    public function failed(?Throwable $exception): void
    {
        $conversation = Conversation::query()->find($this->conversationId);

        if ($conversation === null) {
            return;
        }

        $text = $conversation->messages()
            ->where('direction', MessageDirection::In)
            ->where('author', MessageAuthor::Customer)
            ->latest('id')
            ->first();

        if ($text === null) {
            return;
        }

        app(ReportFrontDeskFailure::class)->handle($conversation, $text, 'it stopped before finishing', 'Front desk stopped before answering');
    }
}
