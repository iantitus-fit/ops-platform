<?php

namespace App\Actions;

use App\Enums\MessageAuthor;
use App\Enums\MessageDirection;
use App\Jobs\ProcessInbound;
use App\Models\Message;
use Throwable;

class DispatchInbound
{
    /**
     * Queue ProcessInbound for an inbound customer message, once.
     *
     * This is the only place ProcessInbound is queued, so the webhook, its retries and the
     * sweep can never queue a message twice (D-42). The claim is one conditional update: it
     * changes the row only while dispatched_at is still empty, and only the caller whose
     * update changed a row goes on to queue the job.
     *
     * A push that fails gives the claim back: dispatched_at goes back to null and the exception
     * is rethrown, so the provider's retry (RecordInbound's already-recorded path) or the sweep
     * queues the text later (WR-02). The job is still dispatched through ProcessInbound::dispatch(),
     * so the debounce still applies.
     *
     * The push is not held back with afterCommit(): that would move it to the end of any transaction a caller opened
     * around this action, outside the try below, and a failed push would then leave the claim set with nothing
     * queued (WR-04). Pushing here is safe because the job waits out the debounce before it reads the message.
     *
     * @return bool true when this call claimed the message and queued the job
     */
    public function handle(Message $message): bool
    {
        if ($message->direction !== MessageDirection::In || $message->author !== MessageAuthor::Customer) {
            return false;
        }

        $claimed = Message::query()
            ->whereKey($message->id)
            ->whereNull('dispatched_at')
            ->update(['dispatched_at' => now()]);

        if ($claimed !== 1) {
            return false;
        }

        try {
            ProcessInbound::dispatch($message->id, $message->conversation_id);
        } catch (Throwable $exception) {
            Message::query()->whereKey($message->id)->update(['dispatched_at' => null]);

            throw $exception;
        }

        return true;
    }
}
