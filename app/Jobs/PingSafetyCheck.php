<?php

namespace App\Jobs;

use App\Icm\ActiveConfig;
use App\Icm\Exceptions\NoActiveVersion;
use App\SendRule\Classifier;
use App\SendRule\ClassifierFailed;
use App\SendRule\SafetyCheckStatus;
use App\SendRule\TriageQuestions;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Asks Jev one fixed question every five minutes, so the owner learns the safety check is down even on a night when
 * no customer texts (D-50).
 *
 * The ping sends a fixed, fictional sentence and an empty draft reply, the same shape as the reading of a customer's
 * text, and never a real customer's words, name or number (Phase 2 D-05: message text only). It asks the live triage
 * questions, the same ones a customer's text meets. A usable answer marks Jev up and any failure marks it down. With
 * no business folder live there are no questions to ask, so the ping asks nothing and changes nothing.
 */
class PingSafetyCheck implements ShouldQueue
{
    use Queueable;

    /**
     * The sentence every ping asks about. Fictional, with no customer data in it.
     */
    public const string TEXT = 'Hi, do you work on heat pumps?';

    public int $tries = 1;

    public function handle(Classifier $classifier, ActiveConfig $activeConfig, SafetyCheckStatus $safetyCheckStatus): void
    {
        try {
            $questions = TriageQuestions::fromConfig($activeConfig->triage());
        } catch (NoActiveVersion) {
            return;
        }

        try {
            $classifier->classify(self::TEXT, '', $questions);
        } catch (Throwable $exception) {
            if (! $exception instanceof ClassifierFailed) {
                report($exception);
            }

            $safetyCheckStatus->recordFailure($exception->getMessage());

            return;
        }

        $safetyCheckStatus->recordSuccess();
    }
}
