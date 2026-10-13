<?php

namespace App\Jobs;

use App\Actions\SendOwnerAlert;
use App\Enums\AlertReason;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Models\OwnerAlert;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Sends the owner alerts that were held until opening time and have now fallen due (D-20).
 *
 * A stored table of alerts plus a sweep every minute is used instead of a delayed queue job for
 * three reasons: the draft may be handled before the alert is due, so there must be a moment to
 * look again before texting; the wait can be a whole weekend, longer than a queue is meant to
 * hold a job; and a restart or a flushed queue cannot lose a pending alert, because the alert is
 * a row in the database (RESEARCH "Delayed alerts"). An alert that has already ended is never
 * picked up, so running the sweep twice cannot text twice.
 */
class SendDueAlerts implements ShouldQueue
{
    use Queueable;

    /**
     * Why an alert was dropped when its draft had been dealt with before it fell due.
     */
    public const string HANDLED_BEFORE_DUE = 'Handled before it was due';

    public function handle(SendOwnerAlert $sendOwnerAlert): void
    {
        OwnerAlert::query()
            ->whereNull('sent_at')
            ->whereNull('skipped_at')
            ->whereNull('failed_at')
            ->where('due_at', '<=', now())
            ->lazyById()
            ->each(function (OwnerAlert $alert) use ($sendOwnerAlert): void {
                if ($this->draftWasHandled($alert)) {
                    $sendOwnerAlert->skip($alert, self::HANDLED_BEFORE_DUE);

                    return;
                }

                $sendOwnerAlert->handle($alert);
            });
    }

    /**
     * Whether the alert is about a draft that is no longer waiting for the owner. Only a handled
     * draft makes a delayed alert stale; every other delayed alert is sent when it falls due.
     */
    private function draftWasHandled(OwnerAlert $alert): bool
    {
        if (! in_array($alert->reason, [AlertReason::DraftWaiting, AlertReason::Complaint], true)) {
            return false;
        }

        $message = $alert->message;

        return $message !== null
            && $message->direction === MessageDirection::Out
            && $message->status !== MessageStatus::Draft;
    }
}
