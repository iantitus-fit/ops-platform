<?php

namespace App\Icm\Walks;

use App\SendRule\AsksForPerson;
use App\SendRule\ClassifierResult;
use App\SendRule\CustomerReading;
use App\SendRule\TriageQuestions;
use App\SendRule\TriageReading;
use InvalidArgumentException;

/**
 * The front desk's own hold or send decision on one recorded result, so the report and the front desk cannot disagree
 * (10 D-13; threat T-10-31). It reads nothing and sends nothing: a result, the owner's questions and the bars go in, and
 * a verdict comes out. Where the front desk acts on Jev's scores it is read from the same classes, `TriageReading` for the
 * bars and `CustomerReading` for what a reading holds, never a second copy of the thresholds.
 *
 * A reading (Jev read the customer's text alone) holds only on what RunFrontDesk acts on, and each predicate is read from
 * CustomerReading so the two cannot drift:
 *  - the customer is handed to the owner when the text holds a phrase from AsksForPerson::PHRASES or Jev's asks_for_person
 *    is at or above asks_for_person_min_yes (RunFrontDesk.php:226), before the model is asked and whatever the other scores;
 *  - the reply is held when the reading is a complaint, by the complaint question or the complaint intent
 *    (RunFrontDesk.php:367 and codeHoldReason, :1897-1908);
 *  - the reply is held when Jev is unsure whether the customer asked for a person (codeHoldReason, :1903; DecideSendOrDraft.php:124-126);
 *  - a reading that gave no answer holds too (:1902), which a recorded result never is.
 * A reading's price, promise, discount and intent answers hold nothing: they are recorded and never acted on.
 *
 * A words check (Jev read the customer's text with the model's reply) holds when TriageReading says the reply is not
 * sendable (DecideSendOrDraft.php:116-118), and hands the customer to the owner when its asks_for_person is at or above the
 * bar (RunFrontDesk.php:355). A listed phrase in the customer's text hands off first, as for a reading (:226).
 *
 * `heldBy()` asks about one bar at a time, by that bar's own reasons: a listed phrase is no bar's reason, so it never
 * counts there.
 */
class CalibrationVerdict
{
    /**
     * The reasons each bar can hold a reply by, as TriageReading names them.
     *
     * @var array<string, list<string>>
     */
    public const array BAR_REASONS = [
        'complaint_max_yes' => ['classifier:complaint'],
        'promise_max_yes' => ['classifier:promise'],
        'gate_check_max_yes' => ['classifier_unsure:asks_for_person'],
        'asks_for_person_min_yes' => ['classifier:asks_for_person', 'classifier_unsure:asks_for_person'],
        'intent_min_confidence' => ['classifier_unsure:intent'],
    ];

    /**
     * The reasons a reading acts on. Every other reason TriageReading finds is recorded and never acted on there.
     *
     * @var list<string>
     */
    private const array READING_REASONS = ['classifier:asks_for_person', 'classifier:complaint', 'classifier_intent:complaint', 'classifier_unsure:asks_for_person'];

    /**
     * Whether the front desk would hold or send on this result, and whether it would hand the customer to the owner.
     *
     * @param  array<string, float>  $bars  The thresholds judged by, as config('gate.classifier') holds them.
     * @param  string  $role  CalibrationItem::READING or CalibrationItem::WORDS.
     * @param  string  $customerMessage  What the customer wrote, so a listed phrase hands off as RunFrontDesk.php:226 does.
     * @return array{held: bool, handedOff: bool, reasons: list<string>}
     */
    public function judge(ClassifierResult $result, TriageQuestions $questions, array $bars, string $role, string $customerMessage): array
    {
        $keyword = AsksForPerson::inText($customerMessage);
        $reasons = $keyword ? ['keyword:asks_for_person'] : [];

        if ($role === CalibrationItem::WORDS) {
            $reading = TriageReading::read($result, $questions, $bars);

            return [
                'held' => $keyword || ! $reading->sendable,
                'handedOff' => $keyword || $reading->asksForPerson,
                'reasons' => [...$reasons, ...$reading->reasons],
            ];
        }

        $customer = CustomerReading::fromResult($result, $questions, $bars);
        $handedOff = $keyword || $customer->asksForPerson();

        return [
            'held' => $handedOff || $customer->complaint() || $customer->unsureAboutPerson(),
            'handedOff' => $handedOff,
            'reasons' => [...$reasons, ...array_values(array_filter($customer->reading['reasons'] ?? [], fn (string $reason): bool => in_array($reason, self::READING_REASONS, true)))],
        ];
    }

    /**
     * Whether one bar, by its own reasons, holds this result: for a words check any reason TriageReading finds, for a
     * reading only a reason the front desk acts on from a reading.
     *
     * @param  array<string, float>  $bars
     *
     * @throws InvalidArgumentException when the bar is not one the report moves
     */
    public function heldBy(string $bar, ClassifierResult $result, TriageQuestions $questions, array $bars, string $role): bool
    {
        $reasons = self::BAR_REASONS[$bar] ?? throw new InvalidArgumentException("{$bar} is not a bar the report moves.");
        $found = TriageReading::read($result, $questions, $bars)->reasons;

        if ($role === CalibrationItem::READING) {
            $found = array_values(array_intersect($found, self::READING_REASONS));
        }

        return array_intersect($reasons, $found) !== [];
    }
}
