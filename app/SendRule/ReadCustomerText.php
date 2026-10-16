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
 */
class ReadCustomerText
{
    public function __construct(
        private readonly ActiveConfig $activeConfig,
        private readonly Classifier $classifier,
    ) {}

    public function handle(string $customerText): CustomerReading
    {
        try {
            $questions = TriageQuestions::fromConfig($this->activeConfig->triage());
        } catch (NoActiveVersion) {
            return CustomerReading::failed('No business folder is live, so the safety check has no questions to ask.', ran: false);
        }

        try {
            $result = $this->classifier->classify($customerText, '', $questions);
        } catch (Throwable $exception) {
            if (! $exception instanceof ClassifierFailed) {
                report($exception);
            }

            return CustomerReading::failed($exception->getMessage());
        }

        return CustomerReading::fromResult($result, $questions, config('gate.classifier'));
    }
}
