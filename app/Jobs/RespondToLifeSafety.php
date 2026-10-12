<?php

namespace App\Jobs;

use App\Actions\SendLifeSafetyLine;
use App\Models\Message;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

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
     * A failed run is tried again; the line is sent at most once per text however many runs there are.
     */
    public int $tries = 3;

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
}
