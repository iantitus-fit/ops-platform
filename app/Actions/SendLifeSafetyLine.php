<?php

namespace App\Actions;

use App\Enums\AlertReason;
use App\Enums\GateOutcome;
use App\Enums\MessageAuthor;
use App\Enums\MessageKind;
use App\Enums\MessageStatus;
use App\Enums\PausedReason;
use App\Enums\SendOrigin;
use App\Icm\ActiveConfig;
use App\Icm\Exceptions\NoActiveVersion;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\OwnerAlert;
use App\SendRule\LifeSafety;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Answers a customer text that describes a danger with the safety line, then hands the conversation to the owner
 * (D-15, D-17, D-49).
 *
 * The words are fixed in code (LifeSafety). The line goes out through the one outbound path with the life-safety
 * origin, which passes the pause that stops every other automated text, and it is never counted by the reply cap.
 * Afterwards the assistant steps back and the owner is alerted at once. If the line could not be sent, for any reason
 * at all, or another run holds the send past the wait, the owner is still alerted, with the reason, because a customer
 * in danger who got no answer is exactly what the owner must know. The line is sent, or the owner is told; never
 * neither.
 */
class SendLifeSafetyLine
{
    public function __construct(
        private readonly ActiveConfig $activeConfig,
        private readonly ComposeAutomatedText $composeAutomatedText,
        private readonly OutboundSender $outboundSender,
        private readonly PauseConversation $pauseConversation,
        private readonly RaiseOwnerAlert $raiseOwnerAlert,
    ) {}

    /**
     * Send the safety line for one customer text, pause the conversation and alert the owner.
     *
     * A customer text is answered at most once: when a safety line for it was already sent, no second line goes out
     * and null is returned, but the pause and the owner alert are finished if an earlier run died before making them.
     * When another run is still sending it after gate.life_safety.lock_wait_seconds, this run pauses the
     * conversation and alerts the owner to check that the line went out, then returns null; if the other run succeeds
     * its own alert stands, because RaiseOwnerAlert skips an alert the owner already has for that text. A line that
     * failed to send, with any error, does not count as answered, so a later run tries again, and the owner is told.
     *
     * @param  string  $hazard  gas, general or water
     * @param  string  $trigger  keyword when the words matched in code, classifier when Jev found the danger
     * @return Message|null the stored safety line, or null when this text was already answered, the send failed or
     *                      another run held the lock
     */
    public function handle(Message $inbound, string $hazard, string $trigger): ?Message
    {
        $lock = Cache::lock("life-safety:{$inbound->id}", 30);

        try {
            $lock->block((int) config('gate.life_safety.lock_wait_seconds'));
        } catch (LockTimeoutException) {
            $conversation = $inbound->conversation;

            $this->pauseConversation->handle($conversation, PausedReason::LifeSafety);
            $this->raiseOwnerAlert->handle($conversation, AlertReason::LifeSafety, $inbound, 'Customer described a danger; check the safety message went out');

            return null;
        }

        try {
            return $this->answer($inbound, $hazard, $trigger);
        } finally {
            $lock->release();
        }
    }

    private function answer(Message $inbound, string $hazard, string $trigger): ?Message
    {
        $conversation = $inbound->conversation;

        if ($this->alreadyAnswered($conversation, $inbound)) {
            $this->finishHandOff($conversation, $inbound);

            return null;
        }

        $text = $this->composeAutomatedText->forLifeSafety($conversation, LifeSafety::line($hazard, $this->utilityPhone()));
        $sent = null;
        $why = 'Safety message sent';

        try {
            $sent = $this->outboundSender->send($conversation, $text, MessageAuthor::Agent, SendOrigin::LifeSafety, [
                'kind' => MessageKind::LifeSafety,
                'gate_outcome' => GateOutcome::Send,
                'gate_reason' => 'life_safety',
                'gate' => [
                    'version' => 1,
                    'fixed' => true,
                    'life_safety' => ['hazard' => $hazard, 'trigger' => $trigger],
                    'reason' => ['code' => 'life_safety', 'plain' => null],
                ],
                'meta' => ['in_reply_to' => [$inbound->id]],
            ]);
        } catch (Throwable $exception) {
            report($exception);

            $why = 'Safety message could not be sent';
        }

        $this->pauseConversation->handle($conversation, PausedReason::LifeSafety);
        $this->raiseOwnerAlert->handle($conversation, AlertReason::LifeSafety, $inbound, $why);

        return $sent;
    }

    /**
     * Finish the pause and the owner alert for a text whose safety line already went out.
     *
     * A run can die, or the pause or the alert can fail, after the line was sent. The retry then finds the line and
     * must not stop there, or the conversation would never be paused and the owner never told. The pause comes before
     * the alert, so an alert on file for this text means both are done and the retry leaves a later hand back alone.
     * Otherwise the pause and the alert are made now; both skip what already exists.
     */
    private function finishHandOff(Conversation $conversation, Message $inbound): void
    {
        $ownerTold = OwnerAlert::query()
            ->where('message_id', $inbound->id)
            ->where('reason', AlertReason::LifeSafety->value)
            ->exists();

        if ($ownerTold) {
            return;
        }

        $this->pauseConversation->handle($conversation, PausedReason::LifeSafety);
        $this->raiseOwnerAlert->handle($conversation, AlertReason::LifeSafety, $inbound, 'Safety message sent');
    }

    /**
     * Whether a safety line that reached, or is on its way to, the customer already lists this text as its trigger.
     */
    private function alreadyAnswered(Conversation $conversation, Message $inbound): bool
    {
        return $conversation->messages()
            ->where('kind', MessageKind::LifeSafety)
            ->whereIn('status', [MessageStatus::Queued, MessageStatus::Sent, MessageStatus::Delivered])
            ->get()
            ->contains(fn (Message $line): bool => in_array($inbound->id, (array) ($line->meta['in_reply_to'] ?? []), true));
    }

    /**
     * The gas company's number from rules/replies.md, or null when the folder gives none or none is live yet.
     */
    private function utilityPhone(): ?string
    {
        try {
            $phone = $this->activeConfig->replies()['utility_phone'] ?? null;
        } catch (NoActiveVersion) {
            return null;
        }

        return is_string($phone) ? $phone : null;
    }
}
