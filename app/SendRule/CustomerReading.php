<?php

namespace App\SendRule;

/**
 * What Jev read in the customer's text alone, ready to store and to act on (D-42, D-45).
 *
 * Jev's complaint, life_safety and asks_for_person answers read the customer's message, so a reading made with an
 * empty draft still answers them. Its price, promise and discount answers are recorded and never acted on: a reading
 * can only hold the model's words, hand the customer to the owner or send the safety line, never make anything send.
 *
 * A reading that failed, or that never ran, answers nothing, and the two are kept apart. `failed()` is a Jev call that
 * went wrong: Jev is down. `missing()` is a reading that was never made, such as no business folder being live or a run
 * that somehow has none; the danger check did not run, and that is not a Jev problem (Ian, 2026-10-16). Everything that
 * holds on a failed reading holds on a missing one, but only a failed reading ever marks Jev down. `fromStored()`
 * rebuilds a reading from the gate JSON of an earlier verdict, and reads a missing one back as missing, not failed.
 */
readonly class CustomerReading
{
    /**
     * @param  bool  $ran  Whether Jev was asked at all.
     * @param  bool  $failed  Whether the call failed or gave an answer that could not be used.
     * @param  bool  $missing  Whether no reading was made at all, which is not a failure of Jev's.
     * @param  ?array{intent: string, intent_confidence: float, intent_probabilities: array<string, float>, yes_no: array<string, float>}  $answers
     * @param  ?array{sendable: bool, reason_code: ?string, reasons: list<string>, life_safety: bool, asks_for_person: bool, thresholds: array<string, float>}  $reading
     */
    public function __construct(
        public bool $ran,
        public bool $failed,
        public ?string $error,
        public ?string $model,
        public ?array $answers,
        public ?array $reading,
        public bool $missing = false,
    ) {}

    /**
     * Read Jev's answers against the owner's thresholds.
     *
     * @param  array<string, float>  $thresholds
     */
    public static function fromResult(ClassifierResult $result, TriageQuestions $questions, array $thresholds): self
    {
        return new self(
            ran: true,
            failed: false,
            error: null,
            model: $result->model,
            answers: [
                'intent' => $result->intent,
                'intent_confidence' => $result->intentConfidence,
                'intent_probabilities' => $result->intentProbabilities,
                'yes_no' => $result->yesNo,
            ],
            reading: TriageReading::read($result, $questions, $thresholds)->toArray(),
        );
    }

    /**
     * A reading from a Jev call that went wrong, such as Jev down or an answer that could not be used.
     */
    public static function failed(string $error, bool $ran = true): self
    {
        return new self(ran: $ran, failed: true, error: $error, model: null, answers: null, reading: null);
    }

    /**
     * A reading that was never made, such as no business folder being live. It answers nothing, and it is not a
     * failure of Jev's: the reason says what was missing.
     */
    public static function missing(string $reason): self
    {
        return new self(ran: false, failed: false, error: $reason, model: null, answers: null, reading: null, missing: true);
    }

    /**
     * The reading kept in an earlier verdict's gate JSON, or null for anything that is not one.
     */
    public static function fromStored(mixed $stored): ?self
    {
        if (! is_array($stored) || ! is_bool($stored['ran'] ?? null) || ! is_bool($stored['failed'] ?? null)) {
            return null;
        }

        return new self(
            ran: $stored['ran'],
            failed: $stored['failed'],
            error: is_string($stored['error'] ?? null) ? $stored['error'] : null,
            model: is_string($stored['model'] ?? null) ? $stored['model'] : null,
            answers: is_array($stored['answers'] ?? null) ? $stored['answers'] : null,
            reading: is_array($stored['reading'] ?? null) ? $stored['reading'] : null,
            missing: ($stored['missing'] ?? false) === true,
        );
    }

    /**
     * The reading kept in an earlier verdict's gate JSON, only when a reading was actually made. A record that is absent,
     * malformed or marked missing counts as no reading at all and gives null (Ian, 2026-10-16). A failed reading is
     * still a reading: a Jev call went wrong, but the customer's text was put to it.
     */
    public static function fromStoredIfMade(mixed $stored): ?self
    {
        $reading = self::fromStored($stored);

        return $reading === null || $reading->missing ? null : $reading;
    }

    /**
     * Whether the reading answers nothing, because the call failed or no reading was made. Both hold the model's
     * words; they differ in what the owner is told.
     */
    public function gaveNoAnswer(): bool
    {
        return $this->failed || $this->missing;
    }

    /**
     * Whether the owner's price and offer sentences may go out on this reading (D-49): Jev was asked, did not fail, and
     * found no complaint. A reading that did not run, failed, is missing or found a complaint holds them.
     */
    public function allowsOwnerSentences(): bool
    {
        return $this->ran && ! $this->gaveNoAnswer() && ! $this->complaint();
    }

    /**
     * Whether Jev read the customer's text as a complaint, by the complaint question or the complaint intent.
     */
    public function complaint(): bool
    {
        return $this->hasReason('classifier:complaint') || $this->hasReason('classifier_intent:complaint');
    }

    /**
     * Whether Jev read the customer's text as describing a danger.
     */
    public function lifeSafety(): bool
    {
        return ! $this->gaveNoAnswer() && ($this->reading['life_safety'] ?? false) === true;
    }

    /**
     * Whether Jev read the customer's text as asking for a person.
     */
    public function asksForPerson(): bool
    {
        return ! $this->gaveNoAnswer() && ($this->reading['asks_for_person'] ?? false) === true;
    }

    /**
     * Whether Jev was unsure the customer asked for a person: not sure enough to hand off, too unsure to send.
     */
    public function unsureAboutPerson(): bool
    {
        return $this->hasReason('classifier_unsure:asks_for_person');
    }

    /**
     * @return array{ran: bool, failed: bool, missing: bool, error: ?string, model: ?string, answers: ?array<string, mixed>, reading: ?array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'ran' => $this->ran,
            'failed' => $this->failed,
            'missing' => $this->missing,
            'error' => $this->error,
            'model' => $this->model,
            'answers' => $this->answers,
            'reading' => $this->reading,
        ];
    }

    private function hasReason(string $reason): bool
    {
        return ! $this->gaveNoAnswer() && in_array($reason, $this->reading['reasons'] ?? [], true);
    }
}
