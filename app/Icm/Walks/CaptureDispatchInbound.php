<?php

namespace App\Icm\Walks;

use App\Actions\DispatchInbound;
use App\Enums\MessageAuthor;
use App\Enums\MessageDirection;
use App\Models\Message;

/**
 * DispatchInbound for a walk: it claims the customer's text exactly as the real one does, but keeps the message instead
 * of queueing the job that waits out the burst. The walk's runner then runs that job by hand, at once, the way the
 * queue worker would after the wait, so a walk never sits waiting for a debounce (10 D-01).
 */
class CaptureDispatchInbound extends DispatchInbound
{
    /**
     * @var list<Message>
     */
    private array $captured = [];

    /**
     * Keep the message for the runner. The guard and the one conditional claim of dispatched_at are the parent's.
     *
     * @return bool true when this call claimed the message
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

        $this->captured[] = $message;

        return true;
    }

    /**
     * The messages kept since the last call, oldest first, and forget them.
     *
     * @return list<Message>
     */
    public function takeCaptured(): array
    {
        $captured = $this->captured;
        $this->captured = [];

        return $captured;
    }
}
