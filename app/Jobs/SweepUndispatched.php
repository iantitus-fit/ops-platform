<?php

namespace App\Jobs;

use App\Actions\DispatchInbound;
use App\Enums\MessageAuthor;
use App\Enums\MessageDirection;
use App\Models\Message;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Queues any inbound customer text that was recorded but never queued (D-42).
 *
 * A form post never retries and a provider can stop retrying, so a text whose queue push was
 * lost would otherwise wait forever. This runs on the scheduler and sends each such text through
 * DispatchInbound, the same claim the webhook uses, so a text is never queued twice. The window
 * is a second guard: messages younger than two minutes are left to the webhook, and messages
 * older than 24 hours are never touched, so an old customer cannot be texted by a sweep.
 */
class SweepUndispatched implements ShouldQueue
{
    use Queueable;

    public const int MIN_AGE_MINUTES = 2;

    public const int WINDOW_HOURS = 24;

    public function handle(DispatchInbound $dispatchInbound): void
    {
        Message::query()
            ->where('direction', MessageDirection::In)
            ->where('author', MessageAuthor::Customer)
            ->whereNull('dispatched_at')
            ->where('created_at', '>=', now()->subHours(self::WINDOW_HOURS))
            ->where('created_at', '<=', now()->subMinutes(self::MIN_AGE_MINUTES))
            ->lazyById()
            ->each(fn (Message $message) => $dispatchInbound->handle($message));
    }
}
