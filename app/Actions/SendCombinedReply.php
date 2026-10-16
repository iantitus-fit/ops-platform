<?php

namespace App\Actions;

use App\Enums\GateOutcome;
use App\Enums\MessageAuthor;
use App\Enums\MessageKind;
use App\Enums\SendOrigin;
use App\Models\Conversation;
use App\Models\Message;
use App\SendRule\CustomerReading;
use App\SendRule\ReplyParts;
use DomainException;

/**
 * Sends one text made of owner-written sentences, fixed lines and, when they passed the send rule, the model's words
 * (D-07, D-30).
 *
 * A text with no model words in it was written entirely by code from the owner's own sentences, which the sync already
 * checked, so it records a fixed-reply verdict and skips the classifier. A text with model words in it takes the
 * verdict attributes the caller got from the send rule. Either way it goes out through the one outbound path with the
 * disclosure and sign-off added like any automated text (D-50). Every text carries the run's reading of the customer's
 * text in its gate, which the outbound path demands of an automated text (D-45, D-49).
 */
class SendCombinedReply
{
    public function __construct(
        private readonly ComposeAutomatedText $composeAutomatedText,
        private readonly OutboundSender $outboundSender,
    ) {}

    /**
     * @param  CustomerReading  $reading  What Jev read in the customer's text for this run; a gate the caller built keeps its own.
     * @param  array<string, mixed>  $attributes  Further message columns, such as the verdict of the model's words and meta.
     *
     * @throws DomainException what OutboundSender throws
     */
    public function handle(Conversation $conversation, ReplyParts $parts, CustomerReading $reading, array $attributes = []): Message
    {
        $kind = $parts->kind();

        $attributes = [
            ...$attributes,
            'kind' => $kind,
            'meta' => [...($attributes['meta'] ?? []), 'parts' => $parts->partNames()],
            'activity_summary' => $this->summaryFor($kind),
        ];

        if (! $parts->hasModelWords()) {
            $attributes = [
                ...$attributes,
                'gate_outcome' => GateOutcome::Send,
                'gate_reason' => 'fixed_reply',
                'gate' => ['version' => 1, 'fixed' => true, 'reason' => ['code' => 'fixed_reply', 'plain' => null], 'customer_reading' => $reading->toArray()],
                'label' => null,
            ];
        } elseif (! isset($attributes['gate']['customer_reading'])) {
            $attributes['gate'] = [...($attributes['gate'] ?? []), 'customer_reading' => $reading->toArray()];
        }

        return $this->outboundSender->send(
            $conversation,
            $this->composeAutomatedText->forReply($conversation, $parts->body()),
            MessageAuthor::Agent,
            SendOrigin::Automated,
            $attributes,
        );
    }

    /**
     * How long the text would be as the customer receives it, disclosure and sign-off included.
     */
    public function composedLength(Conversation $conversation, ReplyParts $parts): int
    {
        return mb_strlen($this->composeAutomatedText->forReply($conversation, $parts->body()));
    }

    private function summaryFor(MessageKind $kind): string
    {
        return match ($kind) {
            MessageKind::CodeAnswer => "Owner's price or offer sentence sent",
            MessageKind::PartialHolding => 'Answer sent; the rest is with the owner',
            MessageKind::CheckInReply => 'Check-in reply sent',
            MessageKind::AddedInfoReply => 'Added-info reply sent',
            MessageKind::ComplaintHolding => 'Complaint reply sent',
            default => 'Reply sent by front desk',
        };
    }
}
