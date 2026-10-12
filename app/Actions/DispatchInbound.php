<?php

namespace App\Actions;

use App\Enums\MessageAuthor;
use App\Enums\MessageDirection;
use App\Jobs\ProcessInbound;
use App\Models\Message;

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

        ProcessInbound::dispatch($message->id, $message->conversation_id)->afterCommit();

        return true;
    }
}
