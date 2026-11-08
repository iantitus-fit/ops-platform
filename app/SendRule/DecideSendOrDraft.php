<?php

namespace App\SendRule;

use App\Actions\SendOwnerReply;
use App\Ai\AgentRun;
use App\Enums\ReplyLabel;
use App\Icm\ActiveConfig;
use App\Support\BookingLinkTokens;
use Throwable;

/**
 * Decides whether an agent's reply goes out on its own or waits for the owner as a draft (D-01).
 *
 * Three checks, in this order, and the reply sends only when all of them pass:
 *  1. Its own label: the label is in the owner's send_on_own list, and is never price, promise, discount or
 *     complaint, a floor kept in code that no stored rule can lower.
 *  2. The code checks: the final text, disclosure and sign-off included, fits one text message and breaks none of
 *     CodeChecks' rules. They also hold model words that name a booking address (D-12): the model reads stored texts
 *     that carry /book/[link], and an address it writes would reach the customer broken. Only the model's words are read
 *     for it, never the disclosure, the sign-off or the link line code appends after this verdict.
 *  3. Jev's reading of the owner's triage questions, read against the thresholds in config/gate.php.
 *
 * Jev is asked once per set of words (the words check), vetoed or not, because Jev is fast and cheap and its reading
 * of the words is what later tells a draft held on price alone from one with more in it (D-21, CR-02). A veto keeps
 * its reason, the first one found: the label, the waiting guard, the length or a code finding. Jev's answer can only
 * add to that, never take it away: it can add a hold when the checks would send, a safety line or a hand-off to the
 * person, and it never sends a reply a veto holds (Phase 2 D-02). A call that goes wrong is recorded in the
 * classifier block and also holds the reply; on a vetoed reply the reason stays the veto.
 *
 * Jev is also asked once per run about the customer's text alone (the reading, ReadCustomerText). Its result is passed
 * in and stored on every verdict as customer_reading. A words check can add a hold, a safety line or a handoff, never
 * clear what the reading found: when the words would send, a failed or missing reading holds them as classifier_failed and a
 * reading unsure whether the customer asked for a person holds them as classifier_unsure:asks_for_person. A complaint
 * in the reading does not change the result here; the caller decides what a complaint holds (D-42, D-45).
 *
 * Each words check also moves the safety check's status (D-50): a failed call marks Jev down and an answer marks it up.
 * Being marked down never skips a call; every run still asks.
 *
 * While a draft waits for the owner the caller may pass a guard, a short list of labels that may still send. A reply
 * whose label is not on it is held as waiting_guard:<label>, after the label check and before the length check (D-20).
 */
class DecideSendOrDraft
{
    public function __construct(
        private readonly ActiveConfig $activeConfig,
        private readonly Classifier $classifier,
        private readonly SafetyCheckStatus $safetyCheckStatus,
    ) {}

    /**
     * @param  string  $customerText  What the customer wrote, the whole burst, exactly as Jev should read it.
     * @param  string  $composedText  The text as it would reach the customer, with disclosure and sign-off.
     * @param  CustomerReading  $reading  What Jev read in the customer's text alone for this run.
     * @param  ?list<ReplyLabel>  $onlyLabels  The only labels that may send while a draft waits; null when no guard applies.
     */
    public function decide(AgentRun $run, string $customerText, string $composedText, CustomerReading $reading, ?array $onlyLabels = null): Verdict
    {
        $reply = $run->reply;
        $label = $reply->label;

        $labelPassed = ! in_array($label, ReplyLabel::neverOnItsOwn(), true)
            && in_array($label->value, $this->activeConfig->sendingRules()['send_on_own'] ?? [], true);

        $guardPassed = $onlyLabels === null ? null : in_array($label, $onlyLabels, true);

        $characters = mb_strlen($composedText);
        $lengthPassed = $characters <= SendOwnerReply::MAX_LENGTH;

        $findings = CodeChecks::findings($composedText, $this->activeConfig->voice()['never_say'] ?? []);

        if (BookingLinkTokens::namesBookingAddress($reply->reply)) {
            $findings = [['rule' => 'booking_link', 'match' => '/book/'], ...$findings];
        }

        $details = [
            'version' => 1,
            'label' => $label->value,
            'wants_person' => $reply->wantsPerson,
            'inputs' => ['customer_text' => $customerText, 'agent_reply' => $reply->reply],
            'customer_reading' => $reading->toArray(),
            'checks' => [
                'label' => [
                    'passed' => $labelPassed,
                    'label' => $label->value,
                    'guard' => $onlyLabels === null ? null : array_map(fn (ReplyLabel $guarded): string => $guarded->value, $onlyLabels),
                    'guard_passed' => $guardPassed,
                ],
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
            $guardPassed === false => "waiting_guard:{$label->value}",
            ! $lengthPassed => 'too_long',
            $findings !== [] => "code:{$findings[0]['rule']}",
            default => null,
        };

        [$details, $jevReading] = $this->askJev($details, $customerText, $reply->reply);

        if ($veto !== null) {
            return Verdict::hold($veto, $details, $jevReading?->lifeSafety ?? false, $jevReading?->asksForPerson ?? false);
        }

        if ($jevReading === null) {
            return Verdict::hold('classifier_failed', $details);
        }

        if (! $jevReading->sendable) {
            return Verdict::hold($jevReading->reasonCode ?? 'classifier_failed', $details, $jevReading->lifeSafety, $jevReading->asksForPerson);
        }

        if ($reading->gaveNoAnswer()) {
            return Verdict::hold('classifier_failed', $details);
        }

        if ($reading->unsureAboutPerson()) {
            return Verdict::hold('classifier_unsure:asks_for_person', $details);
        }

        return Verdict::send($details, $jevReading->lifeSafety, $jevReading->asksForPerson);
    }

    /**
     * A hold for a reply that has no model words, such as a price question code cannot answer. The draft still records
     * what was and was not checked, so the owner and later tuning read the same shape as any other held reply: the
     * label, length and code checks did not run (passed is null), and Jev was not asked (Phase 2 D-07).
     */
    public function holdWithoutWords(AgentRun $run, string $customerText, CustomerReading $reading, string $reasonCode, ?string $plainReason = null): Verdict
    {
        $reply = $run->reply;

        $details = [
            'version' => 1,
            'label' => $reply->label->value,
            'wants_person' => $reply->wantsPerson,
            'inputs' => ['customer_text' => $customerText, 'agent_reply' => ''],
            'customer_reading' => $reading->toArray(),
            'checks' => [
                'label' => ['passed' => null, 'label' => $reply->label->value, 'guard' => null, 'guard_passed' => null],
                'length' => ['passed' => null, 'characters' => 0, 'limit' => SendOwnerReply::MAX_LENGTH],
                'code' => ['passed' => null, 'findings' => []],
                'classifier' => ['ran' => false, 'model' => null, 'answers' => null, 'error' => null, 'reading' => null],
            ],
            'thresholds' => config('gate.classifier'),
            'reply_model' => ['provider' => $run->provider, 'model' => $run->model],
            'failed_attempts' => $run->failedAttempts,
        ];

        return Verdict::hold($reasonCode, $details, plainReason: $plainReason);
    }

    /**
     * Ask Jev the owner's questions about the customer's text and the agent's words, and fill the classifier block of
     * the details with what came back. A call that failed leaves its message in the block and returns no reading.
     *
     * @param  array<string, mixed>  $details
     * @return array{0: array<string, mixed>, 1: ?TriageReading} The details with the classifier block filled, and Jev's reading or null when the call failed.
     */
    private function askJev(array $details, string $customerText, string $agentWords): array
    {
        $questions = TriageQuestions::fromConfig($this->activeConfig->triage());
        $details['checks']['classifier']['ran'] = true;

        try {
            $result = $this->classifier->classify($customerText, $agentWords, $questions);
        } catch (Throwable $exception) {
            if (! $exception instanceof ClassifierFailed) {
                report($exception);
            }

            $this->safetyCheckStatus->recordFailure($exception->getMessage());

            $details['checks']['classifier']['error'] = $exception->getMessage();

            return [$details, null];
        }

        $this->safetyCheckStatus->recordSuccess();

        $reading = TriageReading::read($result, $questions, config('gate.classifier'));

        $details['checks']['classifier']['model'] = $result->model;
        $details['checks']['classifier']['answers'] = [
            'intent' => $result->intent,
            'intent_confidence' => $result->intentConfidence,
            'intent_probabilities' => $result->intentProbabilities,
            'yes_no' => $result->yesNo,
        ];
        $details['checks']['classifier']['reading'] = $reading->toArray();

        return [$details, $reading];
    }
}
