<?php

namespace App\Actions;

use App\Enums\DraftResolution;
use App\Enums\GateOutcome;
use App\Enums\MessageAuthor;
use App\Enums\MessageDirection;
use App\Enums\MessageKind;
use App\Enums\MessageStatus;
use App\Enums\SendOrigin;
use App\Models\Conversation;
use App\Models\Message;
use App\SendRule\CustomerReading;
use App\SendRule\FixedReplies;
use DomainException;

/**
 * Sends the owner's fixed replies, the ones code fills in and the model never writes (D-12).
 *
 * The text comes from rules/replies.md through FixedReplies, with the time worked out at the moment of sending
 * (D-13), and goes out through the one outbound path with the disclosure and sign-off added like any automated text
 * (D-50). The classifier is not asked: code wrote the words, and the sync already checked them. Each text carries the
 * run's reading of the customer's text in its gate, because the one outbound path refuses an automated text without one
 * (D-45, D-49); a reading that failed still counts, one that is missing does not.
 */
class SendFixedReply
{
    /**
     * The reason codes of a held complaint, from the label, the code check or Jev's intent (D-31).
     *
     * @var list<string>
     */
    public const array COMPLAINT_CODES = ['label:complaint', 'classifier:complaint', 'classifier_intent:complaint'];

    /**
     * The reason codes of a held discount question: the label, the code check, Jev, or no offer running today (D-14, D-31).
     *
     * @var list<string>
     */
    public const array DISCOUNT_CODES = ['label:discount', 'code:discount', 'classifier:discount', 'discount:no_live_offer'];

    public function __construct(
        private readonly FixedReplies $fixedReplies,
        private readonly ComposeAutomatedText $composeAutomatedText,
        private readonly OutboundSender $outboundSender,
    ) {}

    /**
     * Tell the customer that the owner has their question and when the owner will answer, in the owner's words for
     * why it was held: the complaint reply for a complaint, the discount reply for a discount question, and the
     * general holding reply for everything else (D-31). A folder with no discount reply sends the holding reply's
     * words. Returns null when this customer already has a holding reply for the question that is still waiting
     * (D-14), or when the text could not go out. The draft and the owner's alert already exist by now, so a refusal
     * or a channel failure is reported to the log and never raised.
     */
    public function holding(Conversation $conversation, CustomerReading $reading, ?string $reasonCode = null): ?Message
    {
        if ($this->holdingAlreadySent($conversation)) {
            return null;
        }

        [$words, $kind] = match (true) {
            in_array($reasonCode, self::COMPLAINT_CODES, true) => [$this->fixedReplies->complaintReply(now()), MessageKind::ComplaintHolding],
            in_array($reasonCode, self::DISCOUNT_CODES, true) => [$this->fixedReplies->discountReply(now()), MessageKind::DiscountHolding],
            default => [$this->fixedReplies->holdingReply(now()), MessageKind::HoldingReply],
        };

        $body = $this->composeAutomatedText->forReply($conversation, $words);

        try {
            return $this->outboundSender->send($conversation, $body, MessageAuthor::Agent, SendOrigin::Automated, [
                'kind' => $kind,
                'gate_outcome' => GateOutcome::Send,
                'gate_reason' => 'fixed_reply',
                'gate' => $this->fixedGate($reading),
            ]);
        } catch (DomainException $exception) {
            report($exception);

            return null;
        }
    }

    /**
     * Tell the customer who asked for a person that the owner knows and when the owner will reach out (D-23). Like
     * holding(), the words come from rules/replies.md with the time worked out now, and the disclosure and sign-off
     * go around them (D-50). Unlike holding(), it always sends: a customer who asks again is told again. Returns null
     * when the text could not go out; the owner's alert already exists by then, so that is reported to the log.
     */
    public function handoff(Conversation $conversation, CustomerReading $reading): ?Message
    {
        $body = $this->composeAutomatedText->forReply($conversation, $this->fixedReplies->handoffReply(now()));

        try {
            return $this->outboundSender->send($conversation, $body, MessageAuthor::Agent, SendOrigin::Automated, [
                'kind' => MessageKind::HandoffReply,
                'gate_outcome' => GateOutcome::Send,
                'gate_reason' => 'fixed_reply',
                'gate' => $this->fixedGate($reading),
            ]);
        } catch (DomainException $exception) {
            report($exception);

            return null;
        }
    }

    /**
     * The gate of a fixed reply: code wrote the words, so no words check ran, and the run's reading is attached.
     *
     * @return array<string, mixed>
     */
    private function fixedGate(CustomerReading $reading): array
    {
        return [
            'version' => 1,
            'fixed' => true,
            'reason' => ['code' => 'fixed_reply', 'plain' => null],
            'customer_reading' => $reading->toArray(),
        ];
    }

    /**
     * Whether the customer has already been told the owner will answer, and nothing has answered since (D-14, D-30).
     *
     * The newest message of a kind that starts a wait (MessageKind::startsWait()) is the one that counts, found with
     * reorder() because the relation already sorts oldest first. It stays current until the owner answers: a text the
     * owner sent after it, or a draft the owner approved, edited or discarded, or code closed as answered, since it
     * went out. A draft is written before its holding reply, so its id is lower, and the decision comes later, so
     * resolved_at says whether it ended the wait. A close made by the text that started the wait does not end that
     * wait: only a draft answered by a newer text does (WR-01).
     *
     * Automated texts never end the wait. While a draft waits the assistant may send answers, check-in lines and
     * added-info lines, and none of them answers the question the owner still has.
     */
    public function holdingAlreadySent(Conversation $conversation): bool
    {
        $latest = $conversation->messages()
            ->whereIn('kind', MessageKind::startsWait())
            ->whereIn('status', [MessageStatus::Queued, MessageStatus::Sent, MessageStatus::Delivered])
            ->reorder('id', 'desc')
            ->first();

        if ($latest === null) {
            return false;
        }

        return $this->nothingAnsweredSince($conversation, $latest);
    }

    /**
     * Whether the owner's complaint reply already went out in the customer's current wait (D-34; Ian, 2026-10-17, G-02.1-1).
     *
     * The complaint reply is kind complaint_holding whether it goes alone or leads a text (ReplyParts::kind()), and it
     * starts a wait. So when nothing has answered since the newest one, it belongs to the customer's current wait and the
     * customer has already been told once: a later complaint in that wait gets the check-in or added-info reply instead.
     * A refused or failed complaint send does not count, so the next complaint tries again. Once the owner answers, it no
     * longer counts, and the next complaint gets the complaint reply again.
     */
    public function complaintReplyAlreadySent(Conversation $conversation): bool
    {
        $latest = $conversation->messages()
            ->where('kind', MessageKind::ComplaintHolding)
            ->whereIn('status', [MessageStatus::Queued, MessageStatus::Sent, MessageStatus::Delivered])
            ->reorder('id', 'desc')
            ->first();

        if ($latest === null) {
            return false;
        }

        return $this->nothingAnsweredSince($conversation, $latest);
    }

    /**
     * Whether nothing has answered the customer since a wait-starter went out: no text the owner sent after it, no draft
     * the owner approved, edited or discarded since it, and no draft code closed as answered by a newer text (D-30, WR-01).
     */
    private function nothingAnsweredSince(Conversation $conversation, Message $latest): bool
    {
        $endedByOwner = $conversation->messages()
            ->where(function ($query) use ($latest): void {
                $query
                    ->where(function ($sent) use ($latest): void {
                        $sent->where('direction', MessageDirection::Out)
                            ->where('author', MessageAuthor::Owner)
                            ->whereIn('status', [MessageStatus::Sent, MessageStatus::Delivered])
                            ->where('id', '>', $latest->id);
                    })
                    ->orWhere(function ($resolved) use ($latest): void {
                        $resolved->whereIn('resolution', [DraftResolution::Approved, DraftResolution::Edited, DraftResolution::Discarded])
                            ->where('resolved_at', '>=', $latest->created_at);
                    });
            })
            ->exists();

        if ($endedByOwner) {
            return false;
        }

        return ! $this->answeredAfter($conversation, $latest);
    }

    /**
     * Whether a draft was closed as answered by a text newer than the wait-starter. A close made by the wait-starter
     * itself, such as the partial holding line that went out with an owner sentence, answers the draft it closed but
     * not the question the line says is still with the owner, so the wait goes on (WR-01). The comparison is made in PHP
     * so SQLite and PostgreSQL agree. A draft closed without an answering id does not count: missing evidence never
     * counts as proof (Ian, 2026-10-16, the same rule as CR-02). closeAsAnswered() always records the id.
     */
    private function answeredAfter(Conversation $conversation, Message $waitStarter): bool
    {
        return $conversation->messages()
            ->where('resolution', DraftResolution::Answered)
            ->where('resolved_at', '>=', $waitStarter->created_at)
            ->get()
            ->contains(fn (Message $draft): bool => isset($draft->meta['answered_by']) && (int) $draft->meta['answered_by'] > $waitStarter->id);
    }
}
