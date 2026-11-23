<?php

namespace App\Icm\Walks;

use App\Enums\MessageAuthor;
use App\Enums\MessageDirection;
use App\Enums\MessageKind;
use App\Enums\MessageStatus;
use App\Enums\PausedReason;
use App\Models\BookingSend;
use App\Models\Contact;
use App\Models\Message;
use App\Models\OwnerAlert;
use Illuminate\Support\Collection;

/**
 * Reads what the candidate's code did in the walk's world, so the checks can judge it. It reads only, and only while a
 * walk's world is open: the database it asks is the walk's own and the records folder it looks at is the walk's temporary
 * one, so it can never read a live row or the owner's folder (10 T-10-11). It writes nothing and names no write call,
 * a collection's push included, because a scan of app/ reads every file that touches visits for the ways Laravel code
 * writes them.
 *
 * It never names the booking text kind. A walk judges a booking's texts through the booking's send rows, not through the
 * replies a customer's text is answered with.
 */
class WalkWorldReader
{
    /**
     * The statuses of a text that has gone out or is on its way out.
     *
     * @var list<MessageStatus>
     */
    private const array WENT_OUT = [MessageStatus::Queued, MessageStatus::Sent, MessageStatus::Delivered];

    /**
     * The walk's one customer, or null before they have texted.
     */
    public function persona(): ?Contact
    {
        return Contact::query()->where('phone_e164', WalkRunner::PERSONA_PHONE)->first();
    }

    /**
     * Where the world stands before a step, so the step's own rows can be told from what came earlier.
     *
     * @return array{message: int, alert: int, paused: string|null}
     */
    public function mark(): array
    {
        return [
            'message' => (int) Message::query()->max('id'),
            'alert' => (int) OwnerAlert::query()->max('id'),
            'paused' => $this->pausedReasonOf($this->persona()),
        ];
    }

    /**
     * What the front desk and the owner's alerts did in one step. The step's rows are the ones made after the marks taken
     * before it.
     *
     * Sent on its own: a front desk reply (the model's words, or one of the kinds code assembles from the owner's
     * sentences) went out, and no draft was made in the step. Held: a draft was made. Handed over: the conversation is now
     * paused for a customer who asked for a person and was not before.
     *
     * @param  array{message: int, alert: int, paused: string|null}  $before
     */
    public function turn(int $turn, WalkStepKind $kind, array $before, ?Contact $persona): WalkTurnObservation
    {
        $ownerAlerted = OwnerAlert::query()->where('id', '>', $before['alert'])->exists();

        if ($persona === null) {
            return WalkTurnObservation::quiet($turn, $kind, $ownerAlerted);
        }

        $made = Message::query()
            ->whereIn('conversation_id', $persona->conversations()->select('id'))
            ->where('id', '>', $before['message'])
            ->where('direction', MessageDirection::Out)
            ->where('author', MessageAuthor::Agent)
            ->orderBy('id')
            ->get();

        $wentOut = $made->filter(fn (Message $message): bool => in_array($message->status, self::WENT_OUT, true));
        $replies = $wentOut->filter(fn (Message $message): bool => $message->kind === MessageKind::AgentReply || in_array($message->kind, MessageKind::assembledByCode(), true));
        $drafts = $made->filter(fn (Message $message): bool => $message->status === MessageStatus::Draft);

        $handedToOwner = $before['paused'] !== PausedReason::AskedForPerson->value
            && $this->pausedReasonOf($persona) === PausedReason::AskedForPerson->value;

        return new WalkTurnObservation(
            turn: $turn,
            kind: $kind,
            sentOnItsOwn: $replies->isNotEmpty() && $drafts->isEmpty(),
            heldForOwner: $drafts->isNotEmpty(),
            handedToOwner: $handedToOwner,
            label: $this->firstLabel($replies) ?? $this->firstLabel($drafts),
            parts: $this->partsOf($replies),
            sentText: $wentOut->map(fn (Message $message): string => (string) $message->body)->implode("\n"),
            ownerAlerted: $ownerAlerted,
        );
    }

    /**
     * What the world holds now, for the outcome checks: the booked visit, its reminder still waiting to go, and any alert
     * the owner has been sent.
     */
    public function world(?Contact $persona): WalkWorldState
    {
        $visitIds = $persona === null ? [] : $persona->appointments()->booked()->pluck('id')->all();

        return new WalkWorldState(
            booked: $visitIds !== [],
            reminderQueued: $visitIds !== [] && $this->reminderWaits($visitIds),
            cardShowsVisit: false,
            todayListsVisit: false,
            ownerAlertedInWalk: OwnerAlert::query()->exists(),
        );
    }

    /**
     * Whether a booked visit has a reminder (a send row with a lead above the confirmation's 0) not yet sent, skipped or
     * failed.
     *
     * @param  list<int>  $visitIds
     */
    private function reminderWaits(array $visitIds): bool
    {
        return BookingSend::query()
            ->whereIn('appointment_id', $visitIds)
            ->where('lead_minutes', '>', 0)
            ->whereNull('sent_at')
            ->whereNull('skipped_at')
            ->whereNull('failed_at')
            ->exists();
    }

    private function pausedReasonOf(?Contact $persona): ?string
    {
        if ($persona === null) {
            return null;
        }

        $reason = $persona->conversations()->whereNotNull('paused_at')->orderByDesc('id')->value('paused_reason');

        return $reason instanceof PausedReason ? $reason->value : ($reason === null ? null : (string) $reason);
    }

    /**
     * @param  Collection<int, Message>  $messages
     */
    private function firstLabel(Collection $messages): ?string
    {
        foreach ($messages as $message) {
            if ($message->label !== null) {
                return $message->label->value;
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, Message>  $messages
     * @return list<string>
     */
    private function partsOf(Collection $messages): array
    {
        $parts = [];

        foreach ($messages as $message) {
            foreach ((array) data_get($message->meta, 'parts', []) as $part) {
                if (is_string($part)) {
                    $parts[$part] = true;
                }
            }
        }

        return array_keys($parts);
    }
}
