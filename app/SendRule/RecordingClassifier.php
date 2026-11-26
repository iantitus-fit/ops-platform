<?php

namespace App\SendRule;

use App\Icm\Walks\WalkTape;

/**
 * Jev, with each of its raw scores kept on a tape, inside a recording walk only.
 *
 * walks:record wraps the Classifier the container holds (the real Jev for Ian's run) in this class while a scratch world is
 * open. Every call goes through to the wrapped one with the same arguments, and the result's raw scores are kept as they
 * came: a reading when the draft reply is empty, a check of the model's words when it is not (the way FakeClassifier tells
 * them apart). It keeps scores and never a verdict, so the bars in config/gate.php are applied at the time the recording
 * is played and a changed bar needs no new recording. When Jev did not answer, that is noted on the tape and the same
 * exception goes on, so the send rule holds as it would for a customer and the command knows the recording is incomplete.
 */
class RecordingClassifier implements Classifier
{
    public function __construct(private readonly Classifier $inner, private readonly WalkTape $tape) {}

    /**
     * @throws ClassifierFailed when the wrapped classifier did not answer
     */
    public function classify(string $customerMessage, string $draftReply, TriageQuestions $questions): ClassifierResult
    {
        try {
            $result = $this->inner->classify($customerMessage, $draftReply, $questions);
        } catch (ClassifierFailed $failed) {
            $this->tape->noteRecordingFailure('Jev', $failed->getMessage());

            throw $failed;
        }

        if ($draftReply === '') {
            $this->tape->recordReading($customerMessage, $result->toArray());
        } else {
            $this->tape->recordWordsCheck($customerMessage, $draftReply, $result->toArray());
        }

        return $result;
    }
}
