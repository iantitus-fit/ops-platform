<?php

namespace App\SendRule;

/**
 * Jev's answers for one reply, read against Ian's thresholds (D-45, D-46, D-47).
 *
 * The reading never sends anything. It only says whether Jev agrees a reply could go, and passes on two signals
 * the front desk acts on: the safety line and the hand-off. Jev can only hold; it can never make a reply send
 * that the code checks have stopped (D-02).
 *
 * Jev's numbers are compared as the floats they arrived as, with no rounding, and the thresholds used are kept on
 * the reading so a stored verdict shows what it was judged by.
 */
readonly class TriageReading
{
    /**
     * The yes or no gate checks that hold a reply, in the order they are reported (after life safety and the
     * hand-off, before the intent reasons).
     *
     * @var list<string>
     */
    private const GATE_CHECKS = ['complaint', 'price', 'promise', 'discount'];

    /**
     * @param  list<string>  $reasons  Every reason the reply holds, strongest first; empty when it is sendable.
     * @param  array<string, float>  $thresholds  The threshold values this reading was made with.
     */
    public function __construct(
        public bool $sendable,
        public ?string $reasonCode,
        public array $reasons,
        public bool $lifeSafety,
        public bool $asksForPerson,
        public array $thresholds,
    ) {}

    /**
     * Read the classifier's answers. An answer that is missing counts as a certain yes, so it holds.
     *
     * @param  array<string, float>  $thresholds  intent_min_confidence, gate_check_max_yes, life_safety_min_yes, asks_for_person_min_yes
     */
    public static function read(ClassifierResult $result, TriageQuestions $questions, array $thresholds): self
    {
        $yes = fn (string $key): float => $result->yesNo[$key] ?? 1.0;

        $lifeSafety = $yes('life_safety') > $thresholds['life_safety_min_yes'];
        $asksForPerson = $yes('asks_for_person') >= $thresholds['asks_for_person_min_yes'];
        $unsureAboutPerson = ! $asksForPerson && $yes('asks_for_person') > $thresholds['gate_check_max_yes'];

        $reasons = [];

        if ($lifeSafety) {
            $reasons[] = 'classifier:life_safety';
        }

        if ($asksForPerson) {
            $reasons[] = 'classifier:asks_for_person';
        }

        foreach (self::GATE_CHECKS as $check) {
            if ($yes($check) > $thresholds['gate_check_max_yes']) {
                $reasons[] = "classifier:{$check}";
            }
        }

        if (! in_array($result->intent, $questions->sendableIntents, true)) {
            $reasons[] = "classifier_intent:{$result->intent}";
        }

        if ($result->intentConfidence < $thresholds['intent_min_confidence']) {
            $reasons[] = 'classifier_unsure:intent';
        }

        if ($unsureAboutPerson) {
            $reasons[] = 'classifier_unsure:asks_for_person';
        }

        return new self(
            sendable: $reasons === [],
            reasonCode: $reasons[0] ?? null,
            reasons: $reasons,
            lifeSafety: $lifeSafety,
            asksForPerson: $asksForPerson,
            thresholds: $thresholds,
        );
    }

    /**
     * @return array{sendable: bool, reason_code: ?string, reasons: list<string>, life_safety: bool, asks_for_person: bool, thresholds: array<string, float>}
     */
    public function toArray(): array
    {
        return [
            'sendable' => $this->sendable,
            'reason_code' => $this->reasonCode,
            'reasons' => $this->reasons,
            'life_safety' => $this->lifeSafety,
            'asks_for_person' => $this->asksForPerson,
            'thresholds' => $this->thresholds,
        ];
    }
}
