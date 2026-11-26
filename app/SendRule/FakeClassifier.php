<?php

namespace App\SendRule;

/**
 * A classifier for tests that says what the test told it to and never leaves the app.
 *
 * It tells the two kinds of call apart by the draft reply, as Jev does. A reading asks about the customer's text alone
 * and sends an empty draft; a words check sends the model's words as well. `answer()` scripts the words checks and
 * `answerReading()` the readings. Until a test scripts either, it reads everything as a clear, sendable one: intent
 * faq at 0.95 with every yes or no question at 0.01. `queueReadings()` and `queueAnswers()` give each call its own scores,
 * so a recording test can show that every call's raw scores reach the tape (10-RESEARCH Open Question 7).
 */
class FakeClassifier implements Classifier
{
    private string $intent = 'faq';

    private float $confidence = 0.95;

    /**
     * @var array<string, float>
     */
    private array $yesNoOverrides = [];

    private string $readingIntent = 'faq';

    private float $readingConfidence = 0.95;

    /**
     * @var array<string, float>
     */
    private array $readingYesNoOverrides = [];

    private ?string $failure = null;

    private ?string $readingFailure = null;

    /**
     * Scores queued for the readings, handed out one per call before the fixed reading answer.
     *
     * @var list<array{intent?: string, confidence?: float, yes_no?: array<string, float>}>
     */
    private array $queuedReadings = [];

    /**
     * Scores queued for the words checks, handed out one per call before the fixed answer.
     *
     * @var list<array{intent?: string, confidence?: float, yes_no?: array<string, float>}>
     */
    private array $queuedAnswers = [];

    /**
     * @var list<array{customer_message: string, draft_reply: string}>
     */
    private array $calls = [];

    /**
     * Script the next answers to a words check. Yes or no overrides are merged over the 0.01 default for each question.
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
     * Script the next answers to a reading of the customer's text alone.
     *
     * @param  array<string, float>  $yesNo
     */
    public function answerReading(string $intent = 'faq', float $confidence = 0.95, array $yesNo = []): self
    {
        $this->readingIntent = $intent;
        $this->readingConfidence = $confidence;
        $this->readingYesNoOverrides = $yesNo;
        $this->readingFailure = null;

        return $this;
    }

    /**
     * Queue scores for the next readings of the customer's text alone, one entry per call, so a recording test can give
     * each call its own raw scores. An entry leaves out what it does not care about and gets the fixed reading answer's
     * intent, confidence and yes or no overrides for it. Once the queue is empty the fixed reading answer applies again.
     *
     * @param  array{intent?: string, confidence?: float, yes_no?: array<string, float>}  ...$readings
     */
    public function queueReadings(array ...$readings): self
    {
        array_push($this->queuedReadings, ...$readings);

        return $this;
    }

    /**
     * Queue scores for the next words checks, one entry per call, in the same way queueReadings() does for readings.
     *
     * @param  array{intent?: string, confidence?: float, yes_no?: array<string, float>}  ...$answers
     */
    public function queueAnswers(array ...$answers): self
    {
        array_push($this->queuedAnswers, ...$answers);

        return $this;
    }

    /**
     * Make every call fail the way Jev down, slow or unreadable would, readings included.
     */
    public function failWith(string $reason): self
    {
        $this->failure = $reason;

        return $this;
    }

    /**
     * Make only the readings fail; the words checks keep answering.
     */
    public function failReadingWith(string $reason): self
    {
        $this->readingFailure = $reason;

        return $this;
    }

    /**
     * The words checks, in order: the calls that carried the model's words.
     *
     * @return list<array{customer_message: string, draft_reply: string}>
     */
    public function calls(): array
    {
        return array_values(array_filter($this->calls, fn (array $call): bool => $call['draft_reply'] !== ''));
    }

    /**
     * The readings, in order: the calls that carried an empty draft reply.
     *
     * @return list<array{customer_message: string, draft_reply: string}>
     */
    public function readingCalls(): array
    {
        return array_values(array_filter($this->calls, fn (array $call): bool => $call['draft_reply'] === ''));
    }

    /**
     * Every call, readings and words checks together, in the order they were made.
     *
     * @return list<array{customer_message: string, draft_reply: string}>
     */
    public function allCalls(): array
    {
        return $this->calls;
    }

    public function classify(string $customerMessage, string $draftReply, TriageQuestions $questions): ClassifierResult
    {
        $this->calls[] = ['customer_message' => $customerMessage, 'draft_reply' => $draftReply];

        $isReading = $draftReply === '';
        $failure = $this->failure ?? ($isReading ? $this->readingFailure : null);

        if ($failure !== null) {
            throw new ClassifierFailed($failure);
        }

        $queued = $isReading ? array_shift($this->queuedReadings) : array_shift($this->queuedAnswers);

        $intent = $queued['intent'] ?? ($isReading ? $this->readingIntent : $this->intent);
        $confidence = $queued['confidence'] ?? ($isReading ? $this->readingConfidence : $this->confidence);
        $overrides = $queued['yes_no'] ?? ($isReading ? $this->readingYesNoOverrides : $this->yesNoOverrides);

        $others = array_diff(array_map('strval', array_keys($questions->intents)), [$intent]);
        $share = $others === [] ? 0.0 : (1 - $confidence) / count($others);
        $probabilities = [$intent => $confidence];

        foreach ($others as $other) {
            $probabilities[$other] = $share;
        }

        return new ClassifierResult(
            model: 'fake-jev',
            intent: $intent,
            intentConfidence: $confidence,
            intentProbabilities: $probabilities,
            yesNo: array_merge(array_map(fn () => 0.01, $questions->yesNo), $overrides),
        );
    }
}
