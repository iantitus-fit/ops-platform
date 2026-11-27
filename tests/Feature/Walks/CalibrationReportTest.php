<?php

use App\Icm\Walks\CalibrationSet;
use App\SendRule\AsksForPerson;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

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
    $exit = Artisan::call('walks:report', $options);

    return [$exit, Artisan::output()];
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
