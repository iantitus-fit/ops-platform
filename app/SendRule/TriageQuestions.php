<?php

namespace App\SendRule;

/**
 * The owner's triage questions from rules/triage.md, ready to send to the classifier.
 */
readonly class TriageQuestions
{
    /**
     * @param  array<string, string>  $intents  What each intent means, by intent key, with `other` first.
     * @param  list<string>  $sendableIntents  The intents that may send without the owner.
     * @param  array<string, string>  $yesNo  The yes or no questions, by question key.
     */
    public function __construct(
        public string $intentQuestion,
        public array $intents,
        public array $sendableIntents,
        public array $yesNo,
    ) {}

    /**
     * Build the questions from ActiveConfig::triage().
     *
     * @param  array<string, mixed>  $triage
     */
    public static function fromConfig(array $triage): self
    {
        $intents = [];
        $sendable = [];

        foreach ($triage['intents'] as $intent) {
            $intents[$intent['key']] = $intent['means'];

            if ($intent['sendable'] === true && $intent['key'] !== 'other') {
                $sendable[] = $intent['key'];
            }
        }

        if (isset($intents['other'])) {
            $intents = ['other' => $intents['other']] + $intents;
        }

        return new self($triage['intent_question'], $intents, $sendable, $triage['questions']);
    }

    /**
     * The `questions` object of a Jev request: the intent choice first, then each yes or no question.
     *
     * @return array<string, array<string, mixed>>
     */
    public function toJevQuestions(): array
    {
        $questions = [
            'intent' => [
                'type' => 'choice',
                'instructions' => $this->intentQuestion,
                'criteria' => $this->intents,
            ],
        ];

        foreach ($this->yesNo as $key => $instructions) {
            $questions[$key] = ['type' => 'noul', 'instructions' => $instructions];
        }

        return $questions;
    }
}
