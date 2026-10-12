<?php

namespace App\Actions;

use App\Enums\AlertReason;
use App\Icm\ActiveConfig;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\OwnerAlert;
use App\Models\User;
use Illuminate\Support\Collection;

class RaiseOwnerAlert
{
    public function __construct(
        private readonly ActiveConfig $activeConfig,
        private readonly OwnerAlertText $alertText,
        private readonly SendOwnerAlert $sendOwnerAlert,
    ) {}

    /**
     * Alert the owner about a conversation: one stored alert per person with a phone on file,
     * each sent through SendOwnerAlert (GATE-01, D-18).
     *
     * Raising the same reason for the same message again is harmless: anyone who already has
     * that alert is skipped and nothing more is sent. With nobody to text, one alert is stored
     * as failed so the gap shows (D-19). Nothing here throws on a send failure.
     *
     * Every alert is due now in this plan; the after-hours wait arrives with plan 02-11.
     *
     * @return Collection<int, OwnerAlert> the alerts stored by this call, in recipient order
     */
    public function handle(Conversation $conversation, AlertReason $reason, ?Message $subject = null, ?string $why = null): Collection
    {
        $recipients = User::query()->orderBy('id')->get()
            ->filter(fn (User $user): bool => filled($user->phone))
            ->values();

        $recipientIds = $recipients->isEmpty() ? [null] : $recipients->pluck('id')->all();

        $pendingIds = collect($recipientIds)
            ->reject(fn (?int $userId): bool => $this->alreadyRaised($conversation, $reason, $subject, $userId))
            ->values();

        if ($pendingIds->isEmpty()) {
            return new Collection;
        }

        $body = $this->alertText->build($conversation, $reason, $subject, $why);
        $urgent = $this->isUrgent($reason);

        return $pendingIds->map(function (?int $userId) use ($conversation, $reason, $subject, $recipients, $body, $urgent): OwnerAlert {
            $alert = OwnerAlert::query()->create([
                'conversation_id' => $conversation->id,
                'message_id' => $subject?->id,
                'user_id' => $userId,
                'reason' => $reason,
                'urgent' => $urgent,
                'due_at' => now(),
                'to_phone' => $userId === null ? null : trim((string) $recipients->firstWhere('id', $userId)?->phone),
                'body' => $body,
            ]);

            $this->sendOwnerAlert->handle($alert);

            return $alert;
        })->values();
    }

    /**
     * Whether the owner wants this event at once: a safety message always does, and so does
     * any event the business folder lists under owner_alert right_away.
     */
    public function isUrgent(AlertReason $reason): bool
    {
        if ($reason === AlertReason::LifeSafety) {
            return true;
        }

        return in_array($reason->value, $this->activeConfig->sendingRules()['owner_alert']['right_away'], true);
    }

    private function alreadyRaised(Conversation $conversation, AlertReason $reason, ?Message $subject, ?int $userId): bool
    {
        return OwnerAlert::query()
            ->where('conversation_id', $conversation->id)
            ->where('message_id', $subject?->id)
            ->where('reason', $reason->value)
            ->where('user_id', $userId)
            ->exists();
    }
}
