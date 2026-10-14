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
use App\SendRule\FixedReplies;
use DomainException;

/**
 * Sends the owner's fixed replies, the ones code fills in and the model never writes (D-12).
 *
 * The text comes from rules/replies.md through FixedReplies, with the time worked out at the moment of sending
 * (D-13), and goes out through the one outbound path with the disclosure and sign-off added like any automated text
 * (D-50). The classifier is not asked: code wrote the words, and the sync already checked them.
 */
class SendFixedReply
{
    public function __construct(
        private readonly FixedReplies $fixedReplies,
        private readonly ComposeAutomatedText $composeAutomatedText,
        private readonly OutboundSender $outboundSender,
    ) {}

    /**
     * Tell the customer that the owner has their question and when the owner will answer. Returns null when this
     * customer already has a holding reply for the question that is still waiting (D-14), or when the text could
     * not go out. The draft and the owner's alert already exist by now, so a refusal or a channel failure is
     * reported to the log and never raised.
     */
    public function holding(Conversation $conversation): ?Message
    {
        if ($this->holdingAlreadySent($conversation)) {
            return null;
        }

        $body = $this->composeAutomatedText->forReply($conversation, $this->fixedReplies->holdingReply(now()));

        try {
            return $this->outboundSender->send($conversation, $body, MessageAuthor::Agent, SendOrigin::Automated, [
                'kind' => MessageKind::HoldingReply,
                'gate_outcome' => GateOutcome::Send,
                'gate_reason' => 'fixed_reply',
                'gate' => ['version' => 1, 'fixed' => true, 'reason' => ['code' => 'fixed_reply', 'plain' => null]],
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
    public function handoff(Conversation $conversation): ?Message
    {
        $body = $this->composeAutomatedText->forReply($conversation, $this->fixedReplies->handoffReply(now()));

        try {
            return $this->outboundSender->send($conversation, $body, MessageAuthor::Agent, SendOrigin::Automated, [
                'kind' => MessageKind::HandoffReply,
                'gate_outcome' => GateOutcome::Send,
                'gate_reason' => 'fixed_reply',
                'gate' => ['version' => 1, 'fixed' => true, 'reason' => ['code' => 'fixed_reply', 'plain' => null]],
            ]);
        } catch (DomainException $exception) {
            report($exception);

            return null;
        }
    }

    /**
     * Whether the customer has already been told the owner will answer, and nothing has answered since (D-14, D-30).
     *
     * The newest message of a kind that starts a wait (MessageKind::startsWait()) is the one that counts, found with
     * reorder() because the relation already sorts oldest first. It stays current until the owner answers: a text the
     * owner sent after it, or a draft the owner approved, edited or discarded, or code closed as answered, since it
     * went out. A draft is written before its holding reply, so its id is lower, and the decision comes later, so
     * resolved_at says whether it ended the wait.
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

        $answeredSince = $conversation->messages()
            ->where(function ($query) use ($latest): void {
                $query
                    ->where(function ($sent) use ($latest): void {
                        $sent->where('direction', MessageDirection::Out)
                            ->where('author', MessageAuthor::Owner)
                            ->whereIn('status', [MessageStatus::Sent, MessageStatus::Delivered])
                            ->where('id', '>', $latest->id);
                    })
                    ->orWhere(function ($resolved) use ($latest): void {
                        $resolved->whereIn('resolution', [DraftResolution::Approved, DraftResolution::Edited, DraftResolution::Discarded, DraftResolution::Answered])
                            ->where('resolved_at', '>=', $latest->created_at);
                    });
            })
            ->exists();

        return ! $answeredSince;
    }
}
