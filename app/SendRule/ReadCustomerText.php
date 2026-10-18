<?php

namespace App\SendRule;

use App\Icm\ActiveConfig;
use App\Icm\Exceptions\NoActiveVersion;
use Throwable;

/**
 * The one reading call per run: Jev is asked the owner's triage questions about the customer's text alone, with an
 * empty draft reply (D-42, D-45).
 *
 * Only the answers about the customer's own words mean anything here (complaint, life_safety, asks_for_person), and
 * the reading only ever holds, hands off or sends the safety line. One call, a short timeout, no retry. A call that
 * goes wrong gives a failed reading rather than an exception, so the run goes on and the reading's failure holds the
 * model's words later.
 *
 * The call's result also moves the safety check's status (D-50): a failure marks Jev down and an answer marks it up.
 * With no business folder live there are no questions to ask and no call is made, so the reading is marked missing,
 * never failed, and the status is left alone: the danger check did not run, and that is not a Jev problem (Ian,
 * 2026-10-16).
 */
class ReadCustomerText
{
    public function __construct(
        private readonly ActiveConfig $activeConfig,
        private readonly Classifier $classifier,
        private readonly SafetyCheckStatus $safetyCheckStatus,
    ) {}

    public function handle(string $customerText): CustomerReading
    {
        try {
            $questions = TriageQuestions::fromConfig($this->activeConfig->triage());
        } catch (NoActiveVersion) {
            return CustomerReading::missing('The customer text was not read: no business folder is live, so the safety check has no questions to ask.');
        }

        try {
            $result = $this->classifier->classify($customerText, '', $questions);
        } catch (Throwable $exception) {
            if (! $exception instanceof ClassifierFailed) {
                report($exception);
            }

            $this->safetyCheckStatus->recordFailure($exception->getMessage());

            return CustomerReading::failed($exception->getMessage());
        }

        $this->safetyCheckStatus->recordSuccess();

        return CustomerReading::fromResult($result, $questions, config('gate.classifier'));
    }
}
