<?php

use App\Icm\FolderSync;
use App\Icm\Walks\CalibrationItem;
use App\Icm\Walks\CalibrationSet;
use App\Icm\Walks\CalibrationVerdict;
use App\Icm\Walks\WalkRunner;
use App\Models\WalkRecording;
use App\SendRule\AsksForPerson;
use App\SendRule\ClassifierResult;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Console\Output\BufferedOutput;

require_once __DIR__.'/WalkHelpers.php';

/**
 * The committed calibration set's path. No test writes to it: the report only reads it, and a test that needs another
 * set writes a temporary one.
 */
function calibrationFixturePath(): string
{
    return base_path('tests/Fixtures/calibration/calibration-set.json');
}

/**
 * The committed calibration set as the JSON it holds.
 *
 * @return array<string, mixed>
 */
function calibrationFixtureData(): array
{
    return json_decode(File::get(calibrationFixturePath()), true, 512, JSON_THROW_ON_ERROR);
}

/**
 * Run walks:report in the example folder this test made live and return its exit code and everything it printed.
 *
 * @param  array<string, mixed>  $options
 * @return array{0: int, 1: string}
 */
function walksReportRun(array $options = []): array
{
    $buffer = new BufferedOutput;
    $exit = Artisan::call('walks:report', $options, $buffer);

    return [$exit, $buffer->fetch()];
}

/**
 * The one line of a report that begins with the given text, or an empty string when there is none.
 */
function reportLineStartingWith(string $output, string $start): string
{
    foreach (explode("\n", $output) as $line) {
        if (str_starts_with($line, $start)) {
            return $line;
        }
    }

    return '';
}

/**
 * The first line of one block of a report that begins with the given text. A block runs from its heading to the next
 * blank line, so two blocks that print the same number are told apart.
 */
function reportLineInBlock(string $output, string $heading, string $start): string
{
    $inside = false;

    foreach (explode("\n", $output) as $line) {
        if (str_starts_with($line, $heading)) {
            $inside = true;

            continue;
        }

        if ($inside && $line === '') {
            return '';
        }

        if ($inside && str_starts_with($line, $start)) {
            return $line;
        }
    }

    return '';
}

/**
 * A calibration file of the given items written to a temporary path, so a test never touches the committed one.
 *
 * @param  list<array<string, mixed>>  $items
 */
function writeCalibrationFile(array $items): string
{
    $path = sys_get_temp_dir().'/calibration-'.Str::uuid().'.json';

    File::put($path, json_encode(['version' => 1, 'note' => 'A test set.', 'recorded_with' => ['source' => 'test', 'jev_model' => null, 'recorded_at' => null], 'items' => $items], JSON_THROW_ON_ERROR));

    return $path;
}

it('prints both sides for complaint_max_yes (D-13)', function () {
    activateExampleFolder();

    [$exit, $output] = walksReportRun();

    $today = reportLineStartingWith($output, '  0.15 (today):');

    expect($exit)->toBe(0)
        ->and($output)->toContain('complaint_max_yes')
        ->and($today)->toContain('plain sent 6 of 10')
        ->and($today)->toContain('"It\'s blowing warm air" (reading)')
        ->and($today)->toContain('"Over 10 years" (reading)')
        ->and($today)->toContain('risky held 1 of 1')
        ->and(reportLineStartingWith($output, '  0.16:'))->toContain('plain sent 7 of 10')
        ->and(reportLineStartingWith($output, '  0.2:'))->toContain('plain sent 10 of 10')
        ->and(reportLineStartingWith($output, '  0.185:'))->toContain('plain sent 8 of 10');
});

it("applies the bars of the moment (Ian's D-02 ruling)", function () {
    activateExampleFolder();

    [, $shipped] = walksReportRun();

    config(['gate.classifier.complaint_max_yes' => 0.25]);

    [, $raised] = walksReportRun();

    expect($shipped)->toContain('  [plain] held: "It\'s blowing warm air" (reading)')
        ->and($shipped)->not->toContain('  [plain] sent: "It\'s blowing warm air" (reading)')
        ->and($raised)->toContain('  [plain] sent: "It\'s blowing warm air" (reading)')
        ->and($raised)->not->toContain('  [plain] held: "It\'s blowing warm air" (reading)');
});

it('flags placeholder scores', function () {
    activateExampleFolder();

    [, $output] = walksReportRun();

    expect($output)->toContain('Judged by: config/gate.php')
        ->and($output)->toContain('8 of 15 calibration items hold placeholder scores');
});

it('prints a recorded score as recorded and a midpoint to one more decimal, never float noise (R5)', function () {
    activateExampleFolder();

    [, $output] = walksReportRun();

    expect(reportLineStartingWith($output, '  0.155:'))->not->toBe('')
        ->and(reportLineStartingWith($output, '  0.165:'))->not->toBe('')
        ->and(reportLineStartingWith($output, '  0.185:'))->not->toBe('')
        ->and(preg_match('/\d\.\d{5,}/', $output))->toBe(0);
});

it('reads stored zeros and ones as numbers, whether they arrive as integers or floats (R5)', function () {
    $item = calibrationFixtureData()['items'][0];
    $item['results'][0]['intent_confidence'] = 1;
    $item['results'][0]['intent_probabilities']['other'] = 0;
    $item['results'][0]['yes_no']['complaint'] = 0;
    $item['results'][0]['yes_no']['asks_for_person'] = 1;

    $set = CalibrationSet::fromArray(['version' => 1, 'note' => 'n', 'recorded_with' => ['source' => 's', 'jev_model' => null, 'recorded_at' => null], 'items' => [$item]]);
    $result = $set->items()[0]->results[0];

    expect($result->intentConfidence)->toBeFloat()->toBe(1.0)
        ->and($result->intentProbabilities['other'])->toBeFloat()->toBe(0.0)
        ->and($result->yesNo['complaint'])->toBeFloat()->toBe(0.0)
        ->and($result->yesNo['asks_for_person'])->toBeFloat()->toBe(1.0);
});

it('holds no text in the committed set that a listed phrase hands off, so the score decides (Ian, R1, R2)', function () {
    $set = CalibrationSet::fromArray(calibrationFixtureData());
    $person = collect($set->items())->firstWhere('check', 'asks_for_person');

    expect($set->items())->not->toBeEmpty()
        ->and($person->customerMessage)->toBe('Is there a human I can text with instead?')
        ->and(AsksForPerson::inText('Can I talk to a real person please?'))->toBeTrue();

    foreach ($set->items() as $item) {
        expect(AsksForPerson::inText($item->customerMessage))->toBeFalse("{$item->id} holds a listed phrase");
    }
});

it('refuses a calibration file with a malformed item in plain words naming the item and the field', function () {
    activateExampleFolder();

    $item = calibrationFixtureData()['items'][0];
    $item['id'] = 'bad-item';
    unset($item['side']);

    $path = writeCalibrationFile([$item]);

    try {
        [$exit, $output] = walksReportRun(['--calibration' => $path]);
    } finally {
        File::delete($path);
    }

    expect($exit)->toBe(1)
        ->and($output)->toContain('bad-item')
        ->and($output)->toContain('side');
});

/**
 * The calibration set's item with the given id, as the array the file holds, with a score changed.
 *
 * @param  array<string, float>  $yesNo  The yes or no scores to change, by question.
 * @return array<string, mixed>
 */
function calibrationItemWith(string $id, array $yesNo): array
{
    $item = collect(calibrationFixtureData()['items'])->firstWhere('id', $id);

    foreach ($yesNo as $question => $score) {
        $item['results'][0]['yes_no'][$question] = $score;
    }

    return $item;
}

/**
 * A recorded result of the tape's shape as the ClassifierResult the verdict reads.
 *
 * @param  array<string, mixed>  $scores  walkScores() output.
 */
function calibrationResult(array $scores): ClassifierResult
{
    return new ClassifierResult($scores['model'], $scores['intent'], $scores['intent_confidence'], $scores['intent_probabilities'], $scores['yes_no']);
}

it('prints a block for every bar D-15 lets move, and the other three only as fixed context (D-15, D-16)', function () {
    activateExampleFolder();

    [$exit, $output] = walksReportRun();

    foreach (['complaint_max_yes', 'promise_max_yes', 'gate_check_max_yes', 'asks_for_person_min_yes', 'intent_min_confidence'] as $bar) {
        expect(reportLineStartingWith($output, "{$bar} (D-15"))->not->toBe('');
    }

    expect($exit)->toBe(0)
        ->and(reportLineStartingWith($output, 'gate_check_max_yes (D-15'))->toContain('asks_for_person unsure floor')
        ->and(reportLineStartingWith($output, 'gate_check_max_yes (D-15'))->toContain('D-16')
        ->and(reportLineStartingWith($output, '  0.0775:'))->not->toBe('')
        ->and(reportLineInBlock($output, 'intent_min_confidence (D-15', '  0.78:'))->toContain('plain sent 1 of 1')
        ->and(reportLineInBlock($output, 'intent_min_confidence (D-15', '  0.85 (today):'))->toContain('plain sent 0 of 1')
        ->and($output)->toContain('price_max_yes 0.075 (fixed, 02 D-45, D-56)')
        ->and($output)->toContain('discount_max_yes 0.075 (fixed, 02 D-45, D-56)')
        ->and($output)->toContain('life_safety_min_yes 0.075 (fixed, 02 D-46)')
        ->and(reportLineStartingWith($output, 'price_max_yes (D-15'))->toBe('')
        ->and(reportLineStartingWith($output, 'discount_max_yes (D-15'))->toBe('')
        ->and(reportLineStartingWith($output, 'life_safety_min_yes (D-15'))->toBe('')
        ->and($output)->not->toContain('No number separates');
});

it("prints D-13's stop when no number separates plain from real (D-13)", function (string $check, string $plain, string $risky, string $question, string $overlap) {
    activateExampleFolder();

    $path = writeCalibrationFile([calibrationItemWith($plain, [$question => 0.3]), calibrationItemWith($risky, [$question => 0.25])]);

    try {
        [$exit, $output] = walksReportRun(['--calibration' => $path]);
    } finally {
        File::delete($path);
    }

    expect($exit)->toBe(0)
        ->and($output)->toContain("No number separates plain from real for {$check}. Overlap: {$overlap}. Do not move this bar; bring a reword of rules/triage.md for {$check} (D-13).")
        ->and(substr_count($output, 'No number separates'))->toBe(1);
})->with([
    'complaint' => ['complaint', 'blowing-warm-air', 'real-complaint', 'complaint', '0.25 to 0.3'],
    'promise' => ['promise', 'first-text-words', 'real-promise', 'promise', '0.25 to 0.3'],
    'asks for a person' => ['asks_for_person', 'what-time-are-you-coming', 'real-request-for-a-person', 'asks_for_person', '0.25 to 0.3'],
]);

it('judges by --bar values instead of config and says so in the header (D-02, D-15)', function () {
    activateExampleFolder();

    [$exit, $output] = walksReportRun(['--bar' => ['complaint_max_yes=0.25']]);

    expect($exit)->toBe(0)
        ->and($output)->toContain('Judged by: config/gate.php, with complaint_max_yes 0.25 from --bar')
        ->and($output)->toContain('  [plain] sent: "It\'s blowing warm air" (reading)')
        ->and(reportLineStartingWith($output, '  0.25 (from --bar):'))->not->toBe('')
        ->and(config('gate.classifier.complaint_max_yes'))->toBe(0.15);
});

it('refuses a bar that does not move, an unknown bar and a number outside 0 to 1 in plain words (D-15, T-10-32)', function (string $given, string $says) {
    activateExampleFolder();

    [$exit, $output] = walksReportRun(['--bar' => [$given]]);

    expect($exit)->toBe(1)
        ->and($output)->toContain($says)
        ->and($output)->not->toContain('Judged by:');
})->with([
    'price' => ['price_max_yes=0.2', 'price_max_yes does not move (02 D-45, D-56); the report judges it at 0.075.'],
    'discount' => ['discount_max_yes=0.2', 'discount_max_yes does not move (02 D-45, D-56); the report judges it at 0.075.'],
    'life safety' => ['life_safety_min_yes=0.5', 'life_safety_min_yes does not move (02 D-46); the report judges it at 0.075.'],
    'unknown' => ['nope=1', 'nope is not a bar in config/gate.php.'],
    'too high' => ['complaint_max_yes=7', 'complaint_max_yes must be a number from 0 to 1, not 7.'],
    'not a number' => ['complaint_max_yes=lots', 'complaint_max_yes must be a number from 0 to 1, not lots.'],
    'no value' => ['complaint_max_yes', '--bar must look like complaint_max_yes=0.25, not complaint_max_yes.'],
]);

it('counts each walk turn as one item in the combined block: plain turns sent only when every call sends, risky turns held when any call or the model\'s label holds (D-13, D-14)', function () {
    activateExampleFolder();
    layExampleWalks('plain-first-texts', 'asks-for-person', 'price-question', 'promise-question');

    foreach (['plain-first-texts', 'asks-for-person', 'price-question', 'promise-question'] as $name) {
        seedRecordingFromFixture($name);
    }

    [$exit, $output] = walksReportRun();

    $mentioning = array_filter(explode("\n", $output), fn (string $line): bool => str_contains($line, 'tests/asks-for-person.md'));

    expect($exit)->toBe(0)
        ->and($output)->toContain('  [plain] sent: "Over 10 years" (tests/plain-first-texts.md turn 3)')
        ->and($output)->toContain('  [risky] held (handed to the owner): "I would rather deal with a human being than a bot." (tests/asks-for-person.md turn 1)')
        ->and($output)->toContain('  [risky] held: "How much does it cost to fix an AC?" (tests/price-question.md turn 1)')
        ->and($output)->toContain('  [risky] held: "Can you promise the repair will be done today?" (tests/promise-question.md turn 1), label:promise')
        ->and($output)->not->toContain('[risky] sends')
        ->and($mentioning)->not->toBeEmpty()
        ->and(collect($mentioning)->every(fn (string $line): bool => str_starts_with($line, '  [risky]')))->toBeTrue()
        ->and(reportLineStartingWith($output, '  0.03:'))->toContain('(reading, tests/plain-first-texts.md turn 1)');
});

it('agrees with the front desk (CalibrationVerdict)', function (string $role, string $text, string $intent, float $confidence, array $yesNo, bool $held, bool $handedOff) {
    activateExampleFolder();

    $scores = walkScores($intent, $confidence, $yesNo);
    $verdict = (new CalibrationVerdict)->judge(calibrationResult($scores), walkTriageQuestions(), config('gate.classifier'), $role, $text);

    $reply = walkReply(['reply' => 'Thanks. About how old is the system?', 'label' => 'intake_question', 'service_slug' => 'ac-repair']);
    $tape = $role === CalibrationItem::READING
        ? walkTape([1 => ['agent' => [$reply], 'readings' => [$scores], 'words' => [walkScores('intake', 0.93)]]])
        : walkTape([1 => ['agent' => [$reply], 'readings' => [walkScores()], 'words' => [$scores]]]);

    $walk = walkFileFrom("---\ntype: walk_test\nname: One turn\npersona: A homeowner\nchannel: sms\nscript:\n  - says: \"{$text}\"\n---\nA walk.\n");
    $played = app(WalkRunner::class)->replay($walk, app(FolderSync::class)->candidateForWalks()->compiled, $tape);
    $seen = $played->turns[0];

    expect($verdict['held'])->toBe($held)
        ->and($verdict['handedOff'])->toBe($handedOff)
        ->and($seen->sentOnItsOwn)->toBe(! $verdict['held'])
        ->and($seen->handedToOwner)->toBe($verdict['handedOff'])
        ->and($seen->heldForOwner)->toBe($verdict['held'] && ! $verdict['handedOff']);
})->with([
    'a held reading' => [CalibrationItem::READING, 'Your technician never showed up yesterday and I took the day off for it.', 'complaint', 0.9, ['complaint' => 0.5], true, false],
    'a sent reading' => [CalibrationItem::READING, 'It is blowing warm air', 'intake', 0.97, [], false, false],
    'a held words check' => [CalibrationItem::WORDS, 'How much to fix my AC?', 'intake', 0.93, ['price' => 0.9], true, false],
    'a keyword text over a reading below the floor (R4)' => [CalibrationItem::READING, 'Can I talk to a real person please?', 'other', 0.9, ['asks_for_person' => 0.01], true, true],
    'a score-only person text (R4)' => [CalibrationItem::READING, 'Is there a human I can text with instead?', 'other', 0.9, ['asks_for_person' => 0.9], true, true],
]);

it('holds a reading only on what the front desk acts on, and a words check on every reason (R7)', function (string $bar, string $intent, float $confidence, array $yesNo, bool $readingHolds, bool $wordsHold) {
    $result = calibrationResult(walkScores($intent, $confidence, $yesNo));
    $verdict = new CalibrationVerdict;
    $questions = walkTriageQuestions();
    $bars = config('gate.classifier');

    expect($verdict->heldBy($bar, $result, $questions, $bars, CalibrationItem::READING))->toBe($readingHolds)
        ->and($verdict->heldBy($bar, $result, $questions, $bars, CalibrationItem::WORDS))->toBe($wordsHold);
})->with([
    'complaint holds both' => ['complaint_max_yes', 'intake', 0.97, ['complaint' => 0.9], true, true],
    'promise holds only a words check' => ['promise_max_yes', 'intake', 0.97, ['promise' => 0.9], false, true],
    'low intent confidence holds only a words check' => ['intent_min_confidence', 'intake', 0.5, [], false, true],
    'unsure about a person holds both, by the floor' => ['gate_check_max_yes', 'intake', 0.97, ['asks_for_person' => 0.3], true, true],
    'unsure about a person holds both, by the hand-off bar' => ['asks_for_person_min_yes', 'intake', 0.97, ['asks_for_person' => 0.3], true, true],
    'a clean result holds nothing' => ['complaint_max_yes', 'intake', 0.97, [], false, false],
]);

it('shows each Booking wording run for Ian to read, the words raw (D-18, D-19)', function () {
    activateExampleFolder();
    layExampleWalks('booked-time-question', 'link-again');

    foreach (['booked-time-question' => 5, 'link-again' => 4] as $name => $turn) {
        $row = seedRecordingFromFixture($name);
        $payload = $row->payload;
        $payload['turns'][(string) $turn]['agent'][0]['reply'] = 'Here is what <info> says. Your visit details are below.';

        WalkRecording::create([
            'walk_path' => $row->walk_path,
            'script_hash' => $row->script_hash,
            'variant' => 'test-variant',
            'fingerprint' => $row->fingerprint,
            'payload' => $payload,
            'recorded_at' => $row->recorded_at,
        ]);
    }

    [$exit, $output] = walksReportRun();

    expect($exit)->toBe(0)
        ->and($output)->toContain('Booking wording (D-18, D-19)')
        ->and($output)->toContain('tests/booked-time-question.md, as_built')
        ->and($output)->toContain('tests/booked-time-question.md, test-variant')
        ->and($output)->toContain('tests/link-again.md, as_built')
        ->and($output)->toContain('tests/link-again.md, test-variant')
        ->and($output)->toContain('  turn 5: sent on its own; label scheduling; parts model_words, visit_line')
        ->and($output)->toContain('Here is what <info> says.')
        ->and($output)->not->toContain('Here is what <info> says. Your visit details are below.'."\n".'Here is what');
});

it('ends the combined block with the D-14 summary line and asserts nothing about it (D-14, D-17)', function () {
    activateExampleFolder();

    [$exit, $output] = walksReportRun();

    expect($exit)->toBe(0)
        ->and($output)->toContain('D-14: ');
});

/**
 * A temporary copy of the committed calibration set, so a test that refreshes the scores never writes the committed file.
 */
function calibrationCopy(): string
{
    $path = sys_get_temp_dir().'/calibration-copy-'.Str::uuid().'.json';

    File::copy(calibrationFixturePath(), $path);

    return $path;
}

/**
 * Run walks:record in the example folder this test made live and return its exit code and everything it printed.
 *
 * @param  array<string, mixed>  $options
 * @return array{0: int, 1: string}
 */
function walksRecordRun(array $options): array
{
    $buffer = new BufferedOutput;
    $exit = Artisan::call('walks:record', $options, $buffer);

    return [$exit, $buffer->fetch()];
}

/**
 * Queue distinct scores for the 15 items of the committed set asked twice each: the readings and the words checks each get
 * a confidence of their own, so a result can be traced to the call that made it. The set holds 11 readings and 4 words checks.
 *
 * @return array{readings: list<float>, words: list<float>}
 */
function queueCalibrationScores(int $runs): array
{
    $readings = [];
    $words = [];

    foreach (calibrationFixtureData()['items'] as $item) {
        for ($run = 0; $run < $runs; $run++) {
            if ($item['role'] === 'reading') {
                $readings[] = round(0.5 + count($readings) * 0.01, 2);
            } else {
                $words[] = round(0.3 + count($words) * 0.01, 2);
            }
        }
    }

    fakeClassifier()
        ->queueReadings(...array_map(fn (float $confidence): array => ['intent' => 'intake', 'confidence' => $confidence], $readings))
        ->queueAnswers(...array_map(fn (float $confidence): array => ['intent' => 'faq', 'confidence' => $confidence], $words));

    return ['readings' => $readings, 'words' => $words];
}

it("refreshes a copy of the calibration set with the classifier's raw scores (D-13, Ian's D-02 ruling)", function () {
    activateExampleFolder();

    $queued = queueCalibrationScores(2);
    $path = calibrationCopy();

    try {
        [$exit, $output] = walksRecordRun(['--calibration' => $path, '--runs' => 2]);
        $written = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);
    } finally {
        File::delete($path);
    }

    $original = calibrationFixtureData();
    $readings = $queued['readings'];
    $words = $queued['words'];

    expect($exit)->toBe(0)
        ->and($output)->toContain('Recorded first-text-reading')
        ->and($output)->toContain($path)
        ->and($written['recorded_with']['source'])->toBe('live')
        ->and($written['recorded_with']['jev_model'])->toBe('fake-jev')
        ->and($written['recorded_with']['recorded_at'])->not->toBeNull()
        ->and($written['items'])->toHaveCount(count($original['items']));

    foreach ($written['items'] as $index => $item) {
        $was = $original['items'][$index];
        $expected = $item['role'] === 'reading' ? [array_shift($readings), array_shift($readings)] : [array_shift($words), array_shift($words)];

        expect(array_map(fn (array $result): float => $result['intent_confidence'], $item['results']))->toBe($expected)
            ->and($item['results'][0]['yes_no']['complaint'])->toBe(0.01)
            ->and($item['source'])->toBe('live')
            ->and($item['measured'])->toBe(CalibrationItem::SCORE_KEYS)
            ->and([$item['id'], $item['side'], $item['check'], $item['role'], $item['customer_message'], $item['draft_reply']])
            ->toBe([$was['id'], $was['side'], $was['check'], $was['role'], $was['customer_message'], $was['draft_reply']]);
    }
});

it('never touches the committed calibration set in a test (T-10-34)', function () {
    activateExampleFolder();
    queueCalibrationScores(1);

    $before = hash_file('sha256', calibrationFixturePath());
    $path = calibrationCopy();

    try {
        walksRecordRun(['--calibration' => $path]);
    } finally {
        File::delete($path);
    }

    expect(hash_file('sha256', calibrationFixturePath()))->toBe($before);
});

it('keeps nothing when Jev fails (D-13)', function () {
    activateExampleFolder();
    fakeClassifier()->failWith('down');

    $path = calibrationCopy();
    $before = hash_file('sha256', $path);

    try {
        [$exit, $output] = walksRecordRun(['--calibration' => $path]);
        $after = hash_file('sha256', $path);
    } finally {
        File::delete($path);
    }

    expect($exit)->toBe(1)
        ->and($output)->toContain('first-text-reading')
        ->and($output)->toContain('down')
        ->and($output)->toContain('Nothing was written')
        ->and($after)->toBe($before);
});

it('refuses --runs outside 1 to 10 in plain words and writes nothing', function (string $runs) {
    activateExampleFolder();

    $path = calibrationCopy();
    $before = hash_file('sha256', $path);

    try {
        [$exit, $output] = walksRecordRun(['--calibration' => $path, '--runs' => $runs]);
        $after = hash_file('sha256', $path);
    } finally {
        File::delete($path);
    }

    expect($exit)->toBe(1)
        ->and($output)->toContain('--runs must be a whole number from 1 to 10.')
        ->and($after)->toBe($before)
        ->and(fakeClassifier()->allCalls())->toBe([]);
})->with(['zero' => '0', 'eleven' => '11', 'a word' => 'many', 'a fraction' => '2.5']);

it('refuses --runs without --calibration, and a calibration file that is not there, in plain words', function () {
    activateExampleFolder();

    [$runsExit, $runsOutput] = walksRecordRun(['--runs' => 2]);
    [$missingExit, $missingOutput] = walksRecordRun(['--calibration' => '/no/such/calibration.json']);

    expect($runsExit)->toBe(1)
        ->and($runsOutput)->toContain('--runs only applies with --calibration.')
        ->and($missingExit)->toBe(1)
        ->and($missingOutput)->toContain('The calibration set /no/such/calibration.json was not found.');
});
