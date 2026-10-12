<?php

namespace App\SendRule;

/**
 * What the classifier said about one reply. `model` is the snapshot that actually answered, kept so a
 * silent model change shows up in the labeled data.
 */
readonly class ClassifierResult
{
    /**
     * @param  array<string, float>  $intentProbabilities  Chance of each intent, by intent key.
     * @param  array<string, float>  $yesNo  Chance of "yes" for each yes or no question, by question key.
     */
    public function __construct(
        public string $model,
        public string $intent,
        public float $intentConfidence,
        public array $intentProbabilities,
        public array $yesNo,
    ) {}

    /**
     * @return array{model: string, intent: string, intent_confidence: float, intent_probabilities: array<string, float>, yes_no: array<string, float>}
     */
    public function toArray(): array
    {
        return [
            'model' => $this->model,
            'intent' => $this->intent,
            'intent_confidence' => $this->intentConfidence,
            'intent_probabilities' => $this->intentProbabilities,
            'yes_no' => $this->yesNo,
        ];
    }
}
