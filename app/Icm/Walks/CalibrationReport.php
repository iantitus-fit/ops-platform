<?php

namespace App\Icm\Walks;

use App\SendRule\ClassifierResult;
use App\SendRule\TriageQuestions;

/**
 * The two-sided report Ian reads before he approves a bar (10 D-13): for each bar Phase 10 lets move, how many plain
 * texts would send on their own at each candidate number and which would be held, and how many risky texts would hold
 * or hand off and which would send. Every row is judged by the bars of the moment, so changing a bar changes the rows
 * (Ian's D-02 ruling). The report chooses nothing and proposes nothing.
 *
 * Every number it prints is one of two kinds (R5): a recorded score or a bar value, as recorded, or a midpoint between two
 * neighbours, rounded to one more decimal than the more precise of the two. A candidate is its printed number: it is
 * rounded first, then deduplicated, then every row is judged at that same rounded value.
 *
 * Plain texts from the walk recordings join every block they apply to; risky texts from a walk join the combined block
 * only, because a walk does not say which check a risky turn tests.
 */
class CalibrationReport
{
    /**
     * The bars that may move (D-15), each with the check it belongs to, the score of a result it reads, and the roles in
     * which the front desk acts on that score: a reading never holds on a promise or an intent (R7).
     *
     * @var array<string, array{check: ?string, score: string, roles: list<string>, stop: bool}>
     */
    private const array BARS = [
        'complaint_max_yes' => ['check' => 'complaint', 'score' => 'complaint', 'roles' => [CalibrationItem::READING, CalibrationItem::WORDS], 'stop' => true],
        'promise_max_yes' => ['check' => 'promise', 'score' => 'promise', 'roles' => [CalibrationItem::WORDS], 'stop' => true],
        'gate_check_max_yes' => ['check' => 'asks_for_person', 'score' => 'asks_for_person', 'roles' => [CalibrationItem::READING, CalibrationItem::WORDS], 'stop' => true],
        'asks_for_person_min_yes' => ['check' => 'asks_for_person', 'score' => 'asks_for_person', 'roles' => [CalibrationItem::READING, CalibrationItem::WORDS], 'stop' => false],
        'intent_min_confidence' => ['check' => null, 'score' => 'intent_confidence', 'roles' => [CalibrationItem::WORDS], 'stop' => false],
    ];

    /**
     * The bars that do not move (D-15), with the decision that keeps each.
     *
     * @var array<string, string>
     */
    public const array FIXED = [
        'price_max_yes' => '02 D-45, D-56',
        'discount_max_yes' => '02 D-45, D-56',
        'life_safety_min_yes' => '02 D-46',
    ];

    /**
     * The bars with a block in this report.
     *
     * @var list<string>
     */
    private const array BLOCKS = ['complaint_max_yes', 'promise_max_yes', 'gate_check_max_yes', 'asks_for_person_min_yes', 'intent_min_confidence'];

    public function __construct(private readonly CalibrationVerdict $verdict = new CalibrationVerdict) {}

    /**
     * The report as lines of plain text, for the console.
     *
     * @param  list<CalibrationItem>  $walkItems  One item for each recorded call in the walks' recordings, one result each.
     * @param  array<string, float>  $bars  The thresholds judged by, every key of config('gate.classifier').
     * @param  array<string, float>  $given  The bars the console gave with --bar, by name.
     * @param  list<BookingWordingRun>  $bookingRuns  Each recorded run of a walk that expects a scheduling reply, replayed.
     * @return list<string>
     */
    public function lines(CalibrationSet $set, array $walkItems, TriageQuestions $questions, array $bars, array $given = [], array $bookingRuns = []): array
    {
        $runs = $this->runs([...$set->items(), ...$walkItems]);
        $lines = $this->header($set, $walkItems, $bars, $given);

        foreach (self::BLOCKS as $bar) {
            array_push($lines, '', ...$this->barBlock($bar, $runs, $questions, $bars, $given));
        }

        return [...$lines, '', ...$this->fixedBars($bars), '', ...$this->combinedBlock($runs, $questions, $bars), '', ...$this->bookingBlock($bookingRuns)];
    }

    /**
     * A score or a bar as recorded: its shortest form that reads back as the same number, with no trailing zero.
     */
    public static function numberText(float $value): string
    {
        for ($decimals = 0; $decimals <= 20; $decimals++) {
            $text = number_format($value, $decimals, '.', '');

            if ((float) $text === $value) {
                return $text;
            }
        }

        return (string) $value;
    }

    /**
     * The midpoint of two neighbours, rounded to one more decimal than the more precise of the two.
     */
    public static function midpoint(float $low, float $high): float
    {
        $decimals = max(self::decimalsOf($low), self::decimalsOf($high)) + 1;

        return round(($low + $high) / 2, $decimals);
    }

    private static function decimalsOf(float $value): int
    {
        $text = self::numberText($value);
        $point = strpos($text, '.');

        return $point === false ? 0 : strlen($text) - $point - 1;
    }

    /**
     * @param  list<CalibrationItem>  $walkItems
     * @param  array<string, float>  $bars
     * @param  array<string, float>  $given
     * @return list<string>
     */
    private function header(CalibrationSet $set, array $walkItems, array $bars, array $given): array
    {
        $judgedBy = 'config/gate.php';

        if ($given !== []) {
            $judgedBy .= ', with '.implode(', ', array_map(fn (string $bar, float $value): string => $bar.' '.self::numberText($value), array_keys($given), $given)).' from --bar';
        }

        $total = count($set->items());

        return [
            'walks:report: how each safety bar treats the plain and the risky texts recorded so far. It changes nothing.',
            "Judged by: {$judgedBy}",
            'Bars judged: '.implode(', ', array_map(fn (string $bar, float $value): string => $bar.' '.self::numberText($value), array_keys($bars), $bars)),
            "Items: {$total} calibration items and ".count($walkItems)." walk recording items. {$set->placeholderCount()} of {$total} calibration items hold placeholder scores, not measurements.",
        ];
    }

    /**
     * One line of the report for each run of each item: the item's text, the side, the role and the result.
     *
     * @param  list<CalibrationItem>  $items
     * @return list<array{item: CalibrationItem, result: ClassifierResult, label: string}>
     */
    private function runs(array $items): array
    {
        $runs = [];

        foreach ($items as $item) {
            foreach ($item->results as $index => $result) {
                $label = '"'.$item->customerMessage.'" ('.($item->role === CalibrationItem::READING ? 'reading' : 'reply check');
                $label .= $item->source === CalibrationItem::WALK ? ', '.$item->id : '';
                $label .= ')';
                $label .= count($item->results) > 1 ? ' run '.($index + 1) : '';

                $runs[] = ['item' => $item, 'result' => $result, 'label' => $label];
            }
        }

        return $runs;
    }

    /**
     * One bar's rows: each candidate number, how many plain texts send on their own, how many risky texts hold.
     *
     * @param  list<array{item: CalibrationItem, result: ClassifierResult, label: string}>  $runs
     * @param  array<string, float>  $bars
     * @param  array<string, float>  $given
     * @return list<string>
     */
    private function barBlock(string $bar, array $runs, TriageQuestions $questions, array $bars, array $given): array
    {
        $spec = self::BARS[$bar];
        $plain = array_values(array_filter($runs, fn (array $run): bool => $run['item']->side === CalibrationItem::PLAIN && in_array($run['item']->role, $spec['roles'], true)));
        $risky = array_values(array_filter($runs, fn (array $run): bool => $run['item']->side === CalibrationItem::RISKY && $spec['check'] !== null && $run['item']->check === $spec['check'] && in_array($run['item']->role, $spec['roles'], true)));

        $lines = $this->headingFor($bar, $bars);
        $separates = false;

        // The floor and the hand-off bar share one band, so both are read by the band's reasons.
        $reasonsOf = $bar === 'gate_check_max_yes' ? 'asks_for_person_min_yes' : $bar;

        foreach ($this->candidates($bar, [...$plain, ...$risky], $bars, isset($given[$bar])) as $candidate) {
            $trial = [...$bars, $bar => $candidate['value']];
            $held = fn (array $run): bool => $this->verdict->heldBy($reasonsOf, $run['result'], $questions, $trial, $run['item']->role);

            $plainHeld = array_values(array_filter($plain, $held));
            $riskySends = array_values(array_filter($risky, fn (array $run): bool => ! $held($run)));

            $separates = $separates || ($risky !== [] && $plainHeld === [] && $riskySends === []);

            $row = '  '.self::numberText($candidate['value']).$candidate['mark'].': plain sent '.(count($plain) - count($plainHeld)).' of '.count($plain).$this->named('held', $plainHeld).'; ';
            $row .= $spec['check'] === null
                ? 'risky: none is judged by this bar alone, see the combined block below'
                : 'risky held '.(count($risky) - count($riskySends)).' of '.count($risky).$this->named('would send', $riskySends);

            $lines[] = $row;
        }

        if ($spec['stop'] && $risky !== [] && $plain !== [] && ! $separates) {
            $check = (string) $spec['check'];
            $lines[] = "No number separates plain from real for {$check}. Overlap: ".self::numberText($this->lowest($risky, $spec['score'])).' to '.self::numberText($this->highest($plain, $spec['score'])).". Do not move this bar; bring a reword of rules/triage.md for {$check} (D-13).";
        }

        return $lines;
    }

    /**
     * What a block's bar does, in plain words, above its rows.
     *
     * @param  array<string, float>  $bars
     * @return list<string>
     */
    private function headingFor(string $bar, array $bars): array
    {
        return match ($bar) {
            'complaint_max_yes' => ["complaint_max_yes (D-15): a reply holds when Jev's chance of yes for complaint is above the bar. Both a reading and a reply check hold on it."],
            'promise_max_yes' => ["promise_max_yes (D-15): a reply holds when Jev's chance of yes for promise is above the bar. Only a reply check holds on it; a reading's promise answer is recorded and never acted on."],
            'gate_check_max_yes' => ["gate_check_max_yes (D-15, D-16): the asks_for_person unsure floor. A text is held for the owner when Jev's chance of asks_for_person is above it and under asks_for_person_min_yes (".self::numberText($bars['asks_for_person_min_yes']).'), and handed to the owner at or above that. Its value and the comment in config/gate.php change only in the follow-up plan, and the key keeps its name.'],
            'asks_for_person_min_yes' => [
                'asks_for_person_min_yes (D-15): at or above this number the customer is handed to the owner; the unsure floor gate_check_max_yes stays at '.self::numberText($bars['gate_check_max_yes']).' in these rows.',
                'The stop for asks_for_person is judged under gate_check_max_yes, the lower edge of the same band.',
            ],
            default => ["intent_min_confidence (D-15): a reply check holds when Jev's confidence in its intent is under the bar. A reading's intent is recorded and never acted on, and no risky text is judged by this bar alone."],
        };
    }

    /**
     * The bars that do not move (D-15), printed for context and never as candidates.
     *
     * @param  array<string, float>  $bars
     * @return list<string>
     */
    private function fixedBars(array $bars): array
    {
        $lines = ['Fixed bars (D-15), shown for context and never as candidates:'];

        foreach (self::FIXED as $bar => $decision) {
            $lines[] = "  {$bar} ".self::numberText($bars[$bar])." (fixed, {$decision})";
        }

        return $lines;
    }

    /**
     * Each recorded run of a booking walk, replayed under the judged bars: whether each booking turn sent on its own, its
     * label, its parts and the model's words, raw. Nothing here names a phrase: Ian reads the words and decides whether
     * they repeat the line code adds (D-19).
     *
     * @param  list<BookingWordingRun>  $runs
     * @return list<string>
     */
    private function bookingBlock(array $runs): array
    {
        $lines = ['Booking wording (D-18, D-19): each recorded run of a walk that expects a scheduling reply, replayed from its recording under the bars above. Read the words and judge whether they repeat the line code adds; no check names words.'];

        if ($runs === []) {
            return [...$lines, 'No recorded run of such a walk was found, so there is nothing to show.'];
        }

        foreach ($runs as $run) {
            $lines[] = "{$run->walkPath}, {$run->variant}: {$run->summary}";

            foreach ($run->turns as $seen) {
                $what = match (true) {
                    $seen->sentOnItsOwn => 'sent on its own',
                    $seen->handedToOwner => 'handed to the owner',
                    $seen->heldForOwner => 'held for the owner',
                    default => 'nothing was sent',
                };

                $lines[] = "  turn {$seen->turn}: {$what}; label ".($seen->label ?? 'none').'; parts '.($seen->parts === [] ? 'none' : implode(', ', $seen->parts));

                foreach (preg_split('/\R/u', $seen->sentText) ?: [] as $text) {
                    $lines[] = '    words: '.$text;
                }
            }
        }

        return $lines;
    }

    /**
     * The candidate numbers for a bar: the value judged, every distinct recorded score for the bar's question, and the
     * midpoint of each pair of neighbours, each rounded before it is compared, sorted from lowest.
     *
     * @param  list<array{item: CalibrationItem, result: ClassifierResult, label: string}>  $scoped
     * @param  array<string, float>  $bars
     * @return list<array{value: float, mark: string}>
     */
    private function candidates(string $bar, array $scoped, array $bars, bool $fromBar): array
    {
        $score = self::BARS[$bar]['score'];
        $known = [self::numberText($bars[$bar]) => ['value' => $bars[$bar], 'mark' => $fromBar ? ' (from --bar)' : ' (today)']];

        foreach ($scoped as $run) {
            $value = $this->scoreOf($run['result'], $score);

            $known[self::numberText($value)] ??= ['value' => $value, 'mark' => ''];
        }

        $sorted = array_values($known);

        usort($sorted, fn (array $a, array $b): int => $a['value'] <=> $b['value']);

        for ($i = 0; $i + 1 < count($sorted); $i++) {
            $middle = self::midpoint($sorted[$i]['value'], $sorted[$i + 1]['value']);

            $known[self::numberText($middle)] ??= ['value' => $middle, 'mark' => ''];
        }

        $all = array_values($known);

        usort($all, fn (array $a, array $b): int => $a['value'] <=> $b['value']);

        return $all;
    }

    /**
     * A recorded score for one question. An answer that is missing counts as a certain yes, as TriageReading reads it.
     */
    private function scoreOf(ClassifierResult $result, string $score): float
    {
        return $score === 'intent_confidence' ? $result->intentConfidence : ($result->yesNo[$score] ?? 1.0);
    }

    /**
     * @param  list<array{item: CalibrationItem, result: ClassifierResult, label: string}>  $runs
     */
    private function lowest(array $runs, string $score): float
    {
        return min(array_map(fn (array $run): float => $this->scoreOf($run['result'], $score), $runs));
    }

    /**
     * @param  list<array{item: CalibrationItem, result: ClassifierResult, label: string}>  $runs
     */
    private function highest(array $runs, string $score): float
    {
        return max(array_map(fn (array $run): float => $this->scoreOf($run['result'], $score), $runs));
    }

    /**
     * " (held: a, b)" naming the runs, or nothing when there are none.
     *
     * @param  list<array{item: CalibrationItem, result: ClassifierResult, label: string}>  $runs
     */
    private function named(string $what, array $runs): string
    {
        return $runs === [] ? '' : " ({$what}: ".implode(', ', array_column($runs, 'label')).')';
    }

    /**
     * Every text judged as the front desk would judge it under the bars: one line for each, then the D-14 summary line. A
     * walk's turn is one text however many Jev calls it made: it sends only when every call sends and nothing else holds
     * it, and it holds, or hands off, when any call or the model's never-on-its-own label does.
     *
     * @param  list<array{item: CalibrationItem, result: ClassifierResult, label: string}>  $runs
     * @param  array<string, float>  $bars
     * @return list<string>
     */
    private function combinedBlock(array $runs, TriageQuestions $questions, array $bars): array
    {
        $lines = ['All bars together, judged by the bars above: a plain text must send on its own and a risky one must hold or hand off.'];
        $plainHeld = 0;
        $plain = 0;
        $riskyHeld = 0;
        $risky = 0;

        foreach ($this->texts($runs) as $text) {
            $held = $text['item']->vetoes !== [];
            $handedOff = false;
            $reasons = $text['item']->vetoes;

            foreach ($text['runs'] as $run) {
                $verdict = $this->verdict->judge($run['result'], $questions, $bars, $run['item']->role, $run['item']->customerMessage);

                if ($verdict['held']) {
                    $held = true;
                    $handedOff = $handedOff || $verdict['handedOff'];
                    $reasons = [...$reasons, ...$verdict['reasons']];
                }
            }

            $isPlain = $text['item']->side === CalibrationItem::PLAIN;

            if ($isPlain) {
                $plain++;
                $plainHeld += $held ? 1 : 0;
            } else {
                $risky++;
                $riskyHeld += $held ? 1 : 0;
            }

            $what = match (true) {
                ! $held => $isPlain ? 'sent' : 'sends',
                $handedOff => 'held (handed to the owner)',
                default => 'held',
            };

            $lines[] = '  ['.($isPlain ? 'plain' : 'risky').'] '.$what.': '.$text['label'].($held ? ', '.implode(', ', array_values(array_unique($reasons))) : '');
        }

        $lines[] = 'Plain sent on their own: '.($plain - $plainHeld)." of {$plain}. Risky held or handed off: {$riskyHeld} of {$risky}.";
        $lines[] = $plainHeld === 0 && $riskyHeld === $risky
            ? 'D-14: every risky text holds or hands off and every plain text sends on its own under these bars.'
            : "D-14: not met under these bars. Plain texts held: {$plainHeld} of {$plain}. Risky texts that would send: ".($risky - $riskyHeld)." of {$risky}.";

        return $lines;
    }

    /**
     * The runs as texts: each run of a calibration item is a text of its own, and every call of one walk turn is one text.
     *
     * @param  list<array{item: CalibrationItem, result: ClassifierResult, label: string}>  $runs
     * @return list<array{item: CalibrationItem, label: string, runs: list<array{item: CalibrationItem, result: ClassifierResult, label: string}>}>
     */
    private function texts(array $runs): array
    {
        $texts = [];

        foreach ($runs as $run) {
            $fromWalk = $run['item']->source === CalibrationItem::WALK;
            $key = $fromWalk ? $run['item']->id : (string) count($texts);

            if (! isset($texts[$key])) {
                $texts[$key] = ['item' => $run['item'], 'label' => $fromWalk ? '"'.$run['item']->customerMessage.'" ('.$run['item']->id.')' : $run['label'], 'runs' => []];
            }

            $texts[$key]['runs'][] = $run;
        }

        return array_values($texts);
    }
}
