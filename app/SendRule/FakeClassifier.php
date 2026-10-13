<?php

namespace App\SendRule;

/**
 * A classifier for tests that says what the test told it to and never leaves the app. Until a test scripts it,
 * it reads every reply as a clear, sendable one: intent faq at 0.95 with every yes or no question at 0.01.
 */
class FakeClassifier implements Classifier
{
    private string $intent = 'faq';

    private float $confidence = 0.95;

    /**
     * @var array<string, float>
     */
    private array $yesNoOverrides = [];

    private ?string $failure = null;

    /**
     * @var list<array{customer_message: string, draft_reply: string}>
     */
    private array $calls = [];

    /**
     * Script the next answers. Yes or no overrides are merged over the 0.01 default for each question.
     *
     * @param  array<string, float>  $yesNo
     */
    public function answer(string $intent, float $confidence = 0.95, array $yesNo = []): self
    {
        $this->intent = $intent;
        $this->confidence = $confidence;
        $this->yesNoOverrides = $yesNo;
        $this->failure = null;

        return $this;
    }

    /**
     * Make every call fail the way Jev down, slow or unreadable would.
     */
    public function failWith(string $reason): self
    {
        $this->failure = $reason;

        return $this;
    }

    /**
     * The texts the classifier was asked about, in order.
     *
     * @return list<array{customer_message: string, draft_reply: string}>
     */
    public function calls(): array
    {
        return $this->calls;
    }

    public function classify(string $customerMessage, string $draftReply, TriageQuestions $questions): ClassifierResult
    {
        $this->calls[] = ['customer_message' => $customerMessage, 'draft_reply' => $draftReply];

        if ($this->failure !== null) {
            throw new ClassifierFailed($this->failure);
        }

        $others = array_diff(array_map('strval', array_keys($questions->intents)), [$this->intent]);
        $share = $others === [] ? 0.0 : (1 - $this->confidence) / count($others);
        $probabilities = [$this->intent => $this->confidence];

        foreach ($others as $other) {
            $probabilities[$other] = $share;
        }

        return new ClassifierResult(
            model: 'fake-jev',
            intent: $this->intent,
            intentConfidence: $this->confidence,
            intentProbabilities: $probabilities,
            yesNo: array_merge(array_map(fn () => 0.01, $questions->yesNo), $this->yesNoOverrides),
        );
    }
}
