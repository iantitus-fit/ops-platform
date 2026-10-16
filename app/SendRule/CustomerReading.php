<?php

namespace App\SendRule;

/**
 * What Jev read in the customer's text alone, ready to store and to act on (D-42, D-45).
 *
 * Jev's complaint, life_safety and asks_for_person answers read the customer's message, so a reading made with an
 * empty draft still answers them. Its price, promise and discount answers are recorded and never acted on: a reading
 * can only hold the model's words, hand the customer to the owner or send the safety line, never make anything send.
 *
 * A reading that did not run, or that failed, answers nothing. `failed()` is the one builder for that, and
 * `fromStored()` rebuilds a reading from the gate JSON of an earlier verdict.
 */
readonly class CustomerReading
{
    /**
     * @param  bool  $ran  Whether Jev was asked at all.
     * @param  bool  $failed  Whether the call failed or gave an answer that could not be used.
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
     * A reading that gave no answer, such as Jev down or no business folder live.
     */
    public static function failed(string $error, bool $ran = true): self
    {
        return new self(ran: $ran, failed: true, error: $error, model: null, answers: null, reading: null);
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
        );
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
        return ! $this->failed && ($this->reading['life_safety'] ?? false) === true;
    }

    /**
     * Whether Jev read the customer's text as asking for a person.
     */
    public function asksForPerson(): bool
    {
        return ! $this->failed && ($this->reading['asks_for_person'] ?? false) === true;
    }

    /**
     * Whether Jev was unsure the customer asked for a person: not sure enough to hand off, too unsure to send.
     */
    public function unsureAboutPerson(): bool
    {
        return $this->hasReason('classifier_unsure:asks_for_person');
    }

    /**
     * @return array{ran: bool, failed: bool, error: ?string, model: ?string, answers: ?array<string, mixed>, reading: ?array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'ran' => $this->ran,
            'failed' => $this->failed,
            'error' => $this->error,
            'model' => $this->model,
            'answers' => $this->answers,
            'reading' => $this->reading,
        ];
    }

    private function hasReason(string $reason): bool
    {
        return ! $this->failed && in_array($reason, $this->reading['reasons'] ?? [], true);
    }
}
