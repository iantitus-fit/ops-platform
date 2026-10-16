<?php

namespace App\Actions;

use App\Enums\AlertReason;
use App\Icm\ActiveConfig;
use App\Icm\BusinessClock;
use App\Icm\Exceptions\NoActiveVersion;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\OwnerAlert;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class RaiseOwnerAlert
{
    public function __construct(
        private readonly ActiveConfig $activeConfig,
        private readonly BusinessClock $businessClock,
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
     * An urgent alert is due now. So is any alert raised while the shop is open, or when the
     * folder says after_hours: right_away. Any other alert raised while the shop is closed is
     * stored with a due_at of the next opening and left for SendDueAlerts to send (D-20).
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
        $dueAt = $this->dueAt($urgent);

        return $pendingIds->map(function (?int $userId) use ($conversation, $reason, $subject, $recipients, $body, $urgent, $dueAt): OwnerAlert {
            $alert = OwnerAlert::query()->create([
                'conversation_id' => $conversation->id,
                'message_id' => $subject?->id,
                'user_id' => $userId,
                'reason' => $reason,
                'urgent' => $urgent,
                'due_at' => $dueAt,
                'to_phone' => $userId === null ? null : trim((string) $recipients->firstWhere('id', $userId)?->phone),
                'body' => $body,
            ]);

            if ($dueAt->lessThanOrEqualTo(now())) {
                $this->sendOwnerAlert->handle($alert);
            }

            return $alert;
        })->values();
    }

    /**
     * Alert the owner about the business itself rather than one conversation, such as the safety
     * check going down (D-50): one stored alert per person with a phone on file, or one failed
     * alert when nobody has a phone (D-19). The alert has no conversation and no message, is
     * urgent, due now and sent at once through SendOwnerAlert, at any hour.
     *
     * Nothing here dedupes on purpose: the caller decides when an alert is worth sending. Nothing
     * here throws on a send failure.
     *
     * @return Collection<int, OwnerAlert> the alerts stored by this call, in recipient order
     */
    public function forBusiness(AlertReason $reason, string $why): Collection
    {
        $recipients = User::query()->orderBy('id')->get()
            ->filter(fn (User $user): bool => filled($user->phone))
            ->values();

        $recipientIds = $recipients->isEmpty() ? [null] : $recipients->pluck('id')->all();
        $body = $this->alertText->forBusiness($reason, $why);

        return collect($recipientIds)->map(function (?int $userId) use ($reason, $recipients, $body): OwnerAlert {
            $alert = OwnerAlert::query()->create([
                'conversation_id' => null,
                'message_id' => null,
                'user_id' => $userId,
                'reason' => $reason,
                'urgent' => true,
                'due_at' => now(),
                'to_phone' => $userId === null ? null : trim((string) $recipients->firstWhere('id', $userId)?->phone),
                'body' => $body,
            ]);

            $this->sendOwnerAlert->handle($alert);

            return $alert;
        })->values();
    }

    /**
     * Whether the owner wants this event at once: a safety message and the safety check being
     * down always do (the only two forced right away, whatever the folder says), and so does any
     * event the business folder lists under owner_alert right_away. An owner_alert the code
     * cannot read (no live version, or a folder from before the front desk) counts as urgent:
     * the alert goes out now rather than risk being held, because an alert must never fail
     * silently (D-19).
     */
    public function isUrgent(AlertReason $reason): bool
    {
        if ($reason === AlertReason::LifeSafety || $reason === AlertReason::SafetyCheckDown) {
            return true;
        }

        $setting = $this->ownerAlertSetting();

        if ($setting === null) {
            return true;
        }

        return in_array($reason->value, (array) ($setting['right_away'] ?? []), true);
    }

    /**
     * When the alert should go out: now for an urgent alert, while the shop is open, or when
     * the owner wants after-hours alerts right away; otherwise the next opening (D-20). A folder
     * that never opens has no next opening, and the alert then goes out now rather than never.
     * The opening is converted to the app's time zone because a date is stored as its own wall
     * clock, so a Chicago 8 am would otherwise be saved as 8 am UTC. An owner_alert the code cannot
     * read is never a reason to wait: isUrgent() has already made such an alert due now.
     */
    private function dueAt(bool $urgent): CarbonInterface
    {
        $now = now();

        if ($urgent || $this->businessClock->isOpenAt($now)) {
            return $now;
        }

        if (($this->ownerAlertSetting()['after_hours'] ?? null) === 'right_away') {
            return $now;
        }

        $opening = $this->businessClock->nextOpening($now);

        return $opening === null ? $now : $opening->setTimezone(config('app.timezone'));
    }

    /**
     * The live folder's owner_alert settings (channels, right_away, after_hours), or null when they cannot be
     * read: nothing is live, or the folder is from Phase 1, where owner_alert was a sentence the owner wrote.
     * Only readers tolerate that old shape; the Validator still demands the Phase 2 one.
     *
     * @return array<string, mixed>|null
     */
    private function ownerAlertSetting(): ?array
    {
        try {
            $setting = $this->activeConfig->sendingRules()['owner_alert'] ?? null;
        } catch (NoActiveVersion) {
            return null;
        }

        return is_array($setting) ? $setting : null;
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
