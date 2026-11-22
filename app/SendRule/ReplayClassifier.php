<?php

namespace App\SendRule;

use App\Icm\Walks\WalkTape;

/**
 * Jev's recorded scores, inside a walk only.
 *
 * This is a stand-in for Jev, used only while a walk runs against a scratch world, and only with Ian's explicit approval
 * (10 D-02): a walk that called Jev for real would cost money and give a different answer each time. It hands back the
 * RAW scores the recording holds, never a verdict. The bars those scores are judged by are read from config('gate.classifier')
 * at the moment they are applied (ReadCustomerText and DecideSendOrDraft), so changing a bar flips the same recording
 * with no new recording.
 *
 * A reading of the customer's text alone sends an empty draft reply (FakeClassifier tells the two kinds of call apart
 * the same way); anything else is a check of the model's words. When the recording holds no answer left for the turn it
 * refuses with ClassifierFailed, which the send rule already treats as "hold, never send", and the walk then needs a new
 * recording (10 D-27). It is bound only while a scratch world is open and the binding is gone when the world closes.
 */
class ReplayClassifier implements Classifier
{
    public function __construct(private readonly WalkTape $tape) {}

    /**
     * @throws ClassifierFailed when the recording holds no Jev reading left for this turn
     */
    public function classify(string $customerMessage, string $draftReply, TriageQuestions $questions): ClassifierResult
    {
        $stored = $draftReply === '' ? $this->tape->nextReading() : $this->tape->nextWordsCheck();

        if ($stored === null) {
            throw new ClassifierFailed('the recording holds no Jev reading for this turn');
        }

        $scores = (array) ($stored['result'] ?? []);

        return new ClassifierResult(
            model: (string) ($scores['model'] ?? 'recorded'),
            intent: (string) ($scores['intent'] ?? ''),
            intentConfidence: (float) ($scores['intent_confidence'] ?? 0.0),
            intentProbabilities: array_map('floatval', (array) ($scores['intent_probabilities'] ?? [])),
            yesNo: array_map('floatval', (array) ($scores['yes_no'] ?? [])),
        );
    }
}
