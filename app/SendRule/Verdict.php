<?php

namespace App\SendRule;

use App\Enums\GateOutcome;
use App\Enums\ReplyLabel;

/**
 * What the send rule decided about one reply, with the reason in code and in plain words and the detail of every
 * check, ready to be stored on the message (D-07).
 *
 * The details are the calibration data later tuning learns from, so their shape is versioned: a key `version`
 * lets a later shape sit next to this one instead of splitting the history.
 */
readonly class Verdict
{
    /**
     * @param  array<string, mixed>  $details  The stored gate JSON, `reason` included.
     */
    public function __construct(
        public GateOutcome $outcome,
        public ?string $reasonCode,
        public ?string $plainReason,
        public array $details,
        public bool $lifeSafety = false,
        public bool $asksForPerson = false,
    ) {}

    /**
     * A reply every check let through.
     *
     * @param  array<string, mixed>  $details
     */
    public static function send(array $details, bool $lifeSafety = false, bool $asksForPerson = false): self
    {
        return new self(
            GateOutcome::Send,
            null,
            null,
            [...$details, 'reason' => ['code' => null, 'plain' => null]],
            $lifeSafety,
            $asksForPerson,
        );
    }

    /**
     * A reply kept for the owner, with the first reason it was held.
     *
     * @param  array<string, mixed>  $details
     */
    public static function hold(string $reasonCode, array $details, bool $lifeSafety = false, bool $asksForPerson = false): self
    {
        $plain = self::plainReasonFor($reasonCode);

        return new self(
            GateOutcome::Hold,
            $reasonCode,
            $plain,
            [...$details, 'reason' => ['code' => $reasonCode, 'plain' => $plain]],
            $lifeSafety,
            $asksForPerson,
        );
    }

    /**
     * The same verdict turned into a hold for a reason found after the checks, such as a sender that refused.
     */
    public function heldFor(string $reasonCode): self
    {
        return self::hold($reasonCode, $this->details, $this->lifeSafety, $this->asksForPerson);
    }

    /**
     * The message columns this verdict fills: label, gate_outcome, gate_reason and the gate JSON.
     *
     * @return array{label: ?ReplyLabel, gate_outcome: GateOutcome, gate_reason: ?string, gate: array<string, mixed>}
     */
    public function toAttributes(?ReplyLabel $label): array
    {
        return [
            'label' => $label,
            'gate_outcome' => $this->outcome,
            'gate_reason' => $this->reasonCode,
            'gate' => $this->details,
        ];
    }

    /**
     * A reason code in the words the owner reads on a draft, such as "Held: it mentions a price".
     */
    public static function plainReasonFor(string $code): string
    {
        [$kind, $detail] = array_pad(explode(':', $code, 2), 2, '');

        return match ($kind) {
            'label' => 'Held: '.match ($detail) {
                'price' => 'the reply is about a price',
                'promise' => 'the reply makes a promise',
                'discount' => 'the reply is about a discount',
                'complaint' => 'the reply answers a complaint',
                'other' => 'the reply fits none of the kinds that send on their own',
                default => 'this kind of reply is not on your list that sends on its own',
            },
            'too_long' => 'Held: the text is longer than one text message allows (1,600 characters)',
            'code' => 'Held: the reply '.CodeChecks::describe($detail),
            'classifier_failed' => 'Held: the safety check could not be reached',
            'classifier' => 'Held: '.match ($detail) {
                'life_safety' => 'the customer may be describing a safety problem',
                'asks_for_person' => 'the customer asks to talk to a person',
                'complaint' => 'the safety check reads the customer as unhappy',
                'price' => 'the safety check thinks the reply states a price',
                'promise' => 'the safety check thinks the reply makes a promise',
                'discount' => 'the safety check thinks the reply offers a discount',
                default => 'the safety check flagged the reply',
            },
            'classifier_intent' => 'Held: the safety check read the customer as asking about '.str_replace('_', ' ', $detail).', which does not send on its own',
            'classifier_unsure' => 'Held: the safety check was not sure about '.str_replace('_', ' ', $detail),
            'send_refused' => 'Held: the text could not be sent to this customer',
            default => "Held: {$code}",
        };
    }
}
