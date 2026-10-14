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
     * A reply kept for the owner, with the first reason it was held. When the check can say more than its code does,
     * such as which services have no price on file, it passes owner-facing words that replace the code's own sentence;
     * the reason code stays the same either way (D-40).
     *
     * @param  array<string, mixed>  $details
     */
    public static function hold(string $reasonCode, array $details, bool $lifeSafety = false, bool $asksForPerson = false, ?string $plainReason = null): self
    {
        $plain = $plainReason ?? self::plainReasonFor($reasonCode);

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
     * The same verdict turned into a hold for a reason found after the checks, such as a sender that refused. The
     * optional words replace the code's own sentence and leave the reason code alone (D-40).
     */
    public function heldFor(string $reasonCode, ?string $plainReason = null): self
    {
        return self::hold($reasonCode, $this->details, $this->lifeSafety, $this->asksForPerson, $plainReason);
    }

    /**
     * The same verdict with the safety flag raised, for a danger found in another part of the same text.
     */
    public function flaggedLifeSafety(): self
    {
        return new self($this->outcome, $this->reasonCode, $this->plainReason, $this->details, true, $this->asksForPerson);
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
     * A reason code in the words the owner reads on a draft, such as "Held: asks about price".
     *
     * The code says which check held the reply and the detail says why. The owner only needs the why, so
     * "code:price", "label:price" and "classifier:price" all read the same. A code nobody listed reads as
     * "needs your judgment", which is true of every held draft.
     */
    public static function plainReasonFor(string $code): string
    {
        [$kind, $detail] = array_pad(explode(':', $code, 2), 2, '');

        return match ($kind) {
            'label', 'code', 'classifier' => match ($detail) {
                'price' => 'Held: asks about price',
                'promise' => 'Held: makes a promise',
                'discount' => 'Held: mentions a discount',
                'complaint' => 'Held: complaint',
                'never_say' => 'Held: uses a phrase you never say',
                'life_safety' => 'Held: may be urgent',
                'asks_for_person' => 'Held: may want to talk to a person',
                default => 'Held: needs your judgment',
            },
            'classifier_intent' => match ($detail) {
                'price_question' => 'Held: asks about price',
                'complaint' => 'Held: complaint',
                'emergency' => 'Held: may be urgent',
                default => 'Held: needs your judgment',
            },
            'classifier_unsure' => match ($detail) {
                'asks_for_person' => 'Held: may want to talk to a person',
                default => 'Held: the safety check was not sure',
            },
            'price' => match ($detail) {
                'no_sentence' => 'Held: no price on file for this question',
                default => 'Held: asks about price',
            },
            'discount' => match ($detail) {
                'no_live_offer' => 'Held: no offer running today',
                default => 'Held: mentions a discount',
            },
            'touches_waiting' => 'Held: adds to a question you already have',
            'waiting_guard' => 'Held: waits behind a question you already have',
            'too_long' => 'Held: too long for one text',
            'classifier_failed' => 'Held: the safety check did not answer',
            'send_refused' => 'Held: could not send right now',
            default => 'Held: needs your judgment',
        };
    }
}
