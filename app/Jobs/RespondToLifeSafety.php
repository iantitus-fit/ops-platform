<?php

namespace App\Jobs;

use App\Actions\PauseConversation;
use App\Actions\RaiseOwnerAlert;
use App\Actions\SendLifeSafetyLine;
use App\Enums\AlertReason;
use App\Enums\PausedReason;
use App\Models\Message;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Sends the safety line for one customer text that describes a danger, right away (D-17).
 *
 * Unlike ProcessInbound there is no debounce and no delay: the burst wait is for ordinary conversation, and a
 * customer who smells gas must not sit through it. SendLifeSafetyLine sends at most one line per customer text,
 * so a queue retry or the front desk's own second look cannot send a second one.
 */
class RespondToLifeSafety implements ShouldQueue
{
    use Queueable;

    /**
     * A run that throws is tried again, and the line is sent at most once per text however many runs there are.
     *
     * A line the channel refuses does not make the run throw: SendLifeSafetyLine catches that, pauses and alerts the
     * owner, and the front desk's own second look tries the text again when the burst wait ends. What fails a run is
     * the pause or the alert throwing, and the retry then finishes whichever of the two is missing.
     */
    public int $tries = 3;

    /**
     * Seconds the worker lets one run last: up to 5 on the reply lock, 10 on the safety lock and the channel's own
     * time limit for the send, then the pause and the alert to the owner.
     */
    public int $timeout = 120;

    /**
     * A run that passes its timeout counts as failed, so failed() runs and the owner is told.
     */
    public bool $failOnTimeout = true;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $messageId, public string $hazard) {}

    /**
     * Execute the job.
     */
    public function handle(SendLifeSafetyLine $sendLifeSafetyLine): void
    {
        $message = Message::query()->find($this->messageId);

        if ($message === null) {
            return;
        }

        $sendLifeSafetyLine->handle($message, $this->hazard, 'keyword');
    }

    /**
     * Runs after the last try fails: the conversation is paused and the owner is told, because a customer who
     * described a danger must never be left with nobody knowing (WR-05). The alert says whether the safety line went
     * out: "could not be sent" when no line is on its way to the customer, and "sent, but check the conversation was
     * paused" when the line did go out and only the pause or the alert failed. The exception's words are never copied
     * into the alert. Both steps skip what is already done, so an alert already raised for this text is left alone.
     */
    public function failed(?Throwable $exception): void
    {
        $message = Message::query()->find($this->messageId);

        if ($message === null) {
            return;
        }

        $conversation = $message->conversation;

        $why = app(SendLifeSafetyLine::class)->lineSentFor($message)
            ? 'Safety message sent, but check the conversation was paused'
            : 'Safety message could not be sent';

        app(PauseConversation::class)->handle($conversation, PausedReason::LifeSafety);
        app(RaiseOwnerAlert::class)->handle($conversation, AlertReason::LifeSafety, $message, $why);
    }
}
