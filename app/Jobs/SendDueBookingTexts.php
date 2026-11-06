<?php

namespace App\Jobs;

use App\Actions\SendBookingText;
use App\Actions\SendOwnerAlert;
use App\Icm\ActiveConfig;
use App\Models\BookingSend;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Sends the confirmations and reminders that have come due, once each (BOOK-04, D-31).
 *
 * It is a copy of the owner-alert sweep (SendDueAlerts): a stored row per text plus a sweep every minute, so a restart
 * or a flushed queue cannot lose a pending text and a text that has ended is never picked up. A row another sender
 * holds is left alone, and one whose sender died is picked up again once its claim is SendOwnerAlert::CLAIM_WAIT_MINUTES
 * old. The first thing it does is check that the room is live, as a room's code must when it runs (the
 * RoomServiceProvider rule); with the room off, no row is touched.
 */
class SendDueBookingTexts implements ShouldQueue
{
    use Queueable;

    public function handle(SendBookingText $sender, ActiveConfig $config): void
    {
        if (! $config->hasRoom('schedule_and_booking')) {
            return;
        }

        BookingSend::query()
            ->whereNull('sent_at')
            ->whereNull('skipped_at')
            ->whereNull('failed_at')
            ->where('due_at', '<=', now()->utc())
            ->where(function (Builder $query): void {
                $query->whereNull('send_claimed_at')
                    ->orWhere('send_claimed_at', '<=', now()->utc()->subMinutes(SendOwnerAlert::CLAIM_WAIT_MINUTES));
            })
            ->lazyById()
            ->each(fn (BookingSend $send) => $sender->handle($send));
    }
}
