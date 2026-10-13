<?php

namespace App\SendRule;

use App\Actions\SendOwnerReply;
use App\Ai\AgentRun;
use App\Enums\ReplyLabel;
use App\Icm\ActiveConfig;
use Throwable;

/**
 * Decides whether an agent's reply goes out on its own or waits for the owner as a draft (D-01).
 *
 * Three checks, in this order, and the reply sends only when all of them pass:
 *  1. Its own label: the label is in the owner's send_on_own list, and is never price, promise, discount or
 *     complaint, a floor kept in code that no stored rule can lower.
 *  2. The code checks: the final text, disclosure and sign-off included, fits one text message and breaks none of
 *     CodeChecks' rules.
 *  3. Jev's reading of the owner's triage questions, read against the thresholds in config/gate.php.
 *
 * Jev is asked once, and only when the first two checks pass, so a veto never costs a call and a Jev answer can
 * only hold a reply, never rescue one (D-02). Anything that goes wrong in the call also holds it.
 */
class DecideSendOrDraft
{
    public function __construct(
        private readonly ActiveConfig $activeConfig,
        private readonly Classifier $classifier,
    ) {}

    /**
     * @param  string  $customerText  What the customer wrote, the whole burst, exactly as Jev should read it.
     * @param  string  $composedText  The text as it would reach the customer, with disclosure and sign-off.
     */
    public function decide(AgentRun $run, string $customerText, string $composedText): Verdict
    {
        $reply = $run->reply;
        $label = $reply->label;

        $labelPassed = ! in_array($label, ReplyLabel::neverOnItsOwn(), true)
            && in_array($label->value, $this->activeConfig->sendingRules()['send_on_own'] ?? [], true);

        $characters = mb_strlen($composedText);
        $lengthPassed = $characters <= SendOwnerReply::MAX_LENGTH;

        $findings = CodeChecks::findings($composedText, $this->activeConfig->voice()['never_say'] ?? []);

        $details = [
            'version' => 1,
            'label' => $label->value,
            'wants_person' => $reply->wantsPerson,
            'inputs' => ['customer_text' => $customerText, 'agent_reply' => $reply->reply],
            'checks' => [
                'label' => ['passed' => $labelPassed, 'label' => $label->value],
                'length' => ['passed' => $lengthPassed, 'characters' => $characters, 'limit' => SendOwnerReply::MAX_LENGTH],
                'code' => ['passed' => $findings === [], 'findings' => $findings],
                'classifier' => ['ran' => false, 'model' => null, 'answers' => null, 'error' => null, 'reading' => null],
            ],
            'thresholds' => config('gate.classifier'),
            'reply_model' => ['provider' => $run->provider, 'model' => $run->model],
            'failed_attempts' => $run->failedAttempts,
        ];

        $veto = match (true) {
            ! $labelPassed => "label:{$label->value}",
            ! $lengthPassed => 'too_long',
            $findings !== [] => "code:{$findings[0]['rule']}",
            default => null,
        };

        if ($veto !== null) {
            return Verdict::hold($veto, $details);
        }

        return $this->askJev($details, $customerText, $reply->reply);
    }

    /**
     * Ask Jev the owner's questions about the customer's text and the agent's words, and read the answers.
     *
     * @param  array<string, mixed>  $details
     */
    private function askJev(array $details, string $customerText, string $agentWords): Verdict
    {
        $questions = TriageQuestions::fromConfig($this->activeConfig->triage());
        $details['checks']['classifier']['ran'] = true;

        try {
            $result = $this->classifier->classify($customerText, $agentWords, $questions);
        } catch (Throwable $exception) {
            if (! $exception instanceof ClassifierFailed) {
                report($exception);
            }

            $details['checks']['classifier']['error'] = $exception->getMessage();

            return Verdict::hold('classifier_failed', $details);
        }

        $reading = TriageReading::read($result, $questions, config('gate.classifier'));

        $details['checks']['classifier']['model'] = $result->model;
        $details['checks']['classifier']['answers'] = [
            'intent' => $result->intent,
            'intent_confidence' => $result->intentConfidence,
            'intent_probabilities' => $result->intentProbabilities,
            'yes_no' => $result->yesNo,
        ];
        $details['checks']['classifier']['reading'] = $reading->toArray();

        if (! $reading->sendable) {
            return Verdict::hold($reading->reasonCode ?? 'classifier_failed', $details, $reading->lifeSafety, $reading->asksForPerson);
        }

        return Verdict::send($details, $reading->lifeSafety, $reading->asksForPerson);
    }
}
