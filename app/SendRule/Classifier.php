<?php

namespace App\SendRule;

interface Classifier
{
    /**
     * Ask the classifier the owner's triage questions about one customer message and the reply drafted for it.
     *
     * @throws ClassifierFailed
     */
    public function classify(string $customerMessage, string $draftReply, TriageQuestions $questions): ClassifierResult;
}
