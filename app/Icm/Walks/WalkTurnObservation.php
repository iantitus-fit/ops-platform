<?php

namespace App\Icm\Walks;

use LogicException;

/**
 * What the candidate's code did in one step of a walk, as plain facts the checks can judge. It is a value: it holds no
 * model and reads nothing, so a check can be tried on a hand-built one with no app running.
 *
 * Sent on its own means a front desk reply went out and no draft was made in that step. Held for the owner means a
 * draft was made. Handed to the owner means the conversation was paused for a customer who asked for a person. The
 * hand-over sends only its own fixed line and makes no draft, so a step that both handed over and made a draft is not
 * something the front desk does, and this value refuses to hold it rather than let a check choose between the two.
 */
readonly class WalkTurnObservation
{
    /**
     * @param  list<string>  $parts  The parts of the sent reply, such as model_words, booking_link and visit_line.
     * @param  string  $sentText  The bodies of the texts sent in the step, one per line.
     */
    public function __construct(
        public int $turn,
        public WalkStepKind $kind,
        public bool $sentOnItsOwn,
        public bool $heldForOwner,
        public bool $handedToOwner,
        public ?string $label,
        public array $parts,
        public string $sentText,
        public bool $ownerAlerted,
    ) {
        if ($heldForOwner && $handedToOwner) {
            throw new LogicException('a step that handed the conversation to the owner and also made a draft cannot be judged');
        }
    }

    /**
     * A step in which nothing was sent, held or handed over, apart from whether the owner was alerted.
     */
    public static function quiet(int $turn, WalkStepKind $kind, bool $ownerAlerted = false): self
    {
        return new self($turn, $kind, false, false, false, null, [], '', $ownerAlerted);
    }
}
