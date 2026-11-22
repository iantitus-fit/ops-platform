<?php

use App\Icm\Walks\ScratchWorld;
use App\Icm\Walks\WalkFile;
use App\Icm\Walks\WalkRunner;
use App\Icm\Walks\WalkTape;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

require_once __DIR__.'/WalkHelpers.php';

/**
 * A walk file with the script items given, one customer text at most, and a start only when one is named.
 *
 * @param  list<string>  $items  Script items such as 'says: "Hi"' or 'wait: "2 hours"'.
 */
function clockWalk(array $items, ?string $start = null): WalkFile
{
    $startLine = $start === null ? '' : "start: \"{$start}\"\n";
    $script = implode("\n", array_map(fn (string $item): string => "  - {$item}", $items));

    return walkFileFrom("---\ntype: walk_test\nname: A clock walk\npersona: A customer who waits\nchannel: sms\n{$startLine}script:\n{$script}\nexpect: [sent on its own]\n---\nA walk on a frozen clock.\n");
}

/**
 * A tape that answers one customer turn with a sendable question.
 */
function clockTape(): WalkTape
{
    return walkTape([1 => [
        'agent' => [walkReply(['reply' => 'Thanks for texting. What is the AC doing?', 'label' => 'intake_question', 'service_slug' => 'ac-repair'])],
        'readings' => [walkScores()],
        'words' => [walkScores()],
    ]]);
}

/**
 * The candidate's compiled copy with its Saturday hours replaced.
 *
 * @param  array{open: string, close: string}  $saturday
 * @return array<string, mixed>
 */
function clockCandidateWithSaturday(array $saturday): array
{
    $compiled = activateExampleFolder()->compiled;
    $compiled['files'][$compiled['index']['hours']]['front_matter']['weekly']['sat'] = $saturday;

    return $compiled;
}

/**
 * Play the walk with the test clock at $testNow (a business-zone moment) and say what moment the walk's own clock showed
 * at the end, in the business zone as Y-m-d H:i.
 *
 * @param  array<string, mixed>|null  $compiled
 */
function clockWalkEnd(string $testNow, WalkFile $walk, ?array $compiled = null): string
{
    $compiled ??= activateExampleFolder()->compiled;
    test()->travelTo(CarbonImmutable::parse($testNow, 'America/Chicago'));
    $end = '';

    app(WalkRunner::class)->replay($walk, $compiled, clockTape(), function (ScratchWorld $world) use (&$end): void {
        $end = $world->now()->setTimezone('America/Chicago')->format('Y-m-d H:i');
    });

    return $end;
}

it('starts at 10:00 on the business day of the next opening (D-07)', function (string $testNow, string $expected) {
    expect(clockWalkEnd($testNow, clockWalk(['says: "Hi, is anyone there?"'])))->toBe($expected);
})->with(fn () => [
    'a Tuesday before opening' => ['2026-12-01 07:00', '2026-12-01 10:00'],
    'a Tuesday after closing' => ['2026-12-01 15:00', '2026-12-02 10:00'],
    'a Saturday before opening' => ['2026-12-05 07:00', '2026-12-05 10:00'],
    'a Sunday' => ['2026-12-06 12:00', '2026-12-07 10:00'],
    'the evening before a closure' => ['2026-11-25 18:00', '2026-11-27 10:00'],
]);

it("starts at the day's opening when 10:00 falls outside its hours (D-07)", function () {
    $compiled = clockCandidateWithSaturday(['open' => '07:00', 'close' => '09:30']);

    expect(clockWalkEnd('2026-12-05 05:00', clockWalk(['says: "Hi, is anyone there?"']), $compiled))->toBe('2026-12-05 07:00');
});

it('starts at a named start whatever the test clock says (D-07)', function () {
    expect(clockWalkEnd('2026-11-20 03:00', clockWalk(['says: "Hi, is anyone there?"'], '2026-12-02 14:30')))->toBe('2026-12-02 14:30');
});

it('moves the clock only by its waits (D-07)', function () {
    $walk = clockWalk(['says: "Hi, is anyone there?"', 'wait: "2 hours"'], '2026-12-01 10:00');

    expect(clockWalkEnd('2026-11-20 03:00', $walk))->toBe('2026-12-01 12:00');
});

it('redraws records/today.md when a wait crosses into a new day (D-07)', function () {
    $compiled = activateExampleFolder()->compiled;
    $this->travelTo(CarbonImmutable::parse('2026-11-20 03:00', 'America/Chicago'));
    $seen = [];

    foreach (['2 hours' => 'two hours', '1 day' => 'one day'] as $wait => $key) {
        app(WalkRunner::class)->replay(clockWalk(["wait: \"{$wait}\""], '2026-12-01 10:00'), $compiled, clockTape(), function (ScratchWorld $world) use (&$seen, $key): void {
            $path = $world->recordsPath().'/records/today.md';
            $seen[$key] = File::exists($path) ? File::get($path) : null;
        });
    }

    expect($seen['two hours'])->toBeNull()
        ->and($seen['one day'])->not->toBeNull()
        ->and((string) $seen['one day'])->toContain('Schedule for Wednesday, December 2');
});

it('puts the test clock back after a walk (D-07)', function () {
    $compiled = activateExampleFolder()->compiled;
    $this->travelTo(CarbonImmutable::parse('2026-11-20 03:00', 'America/Chicago'));
    $before = Carbon::getTestNow();

    app(WalkRunner::class)->replay(clockWalk(['says: "Hi, is anyone there?"', 'wait: "1 day"'], '2026-12-01 10:00'), $compiled, clockTape());

    expect(Carbon::getTestNow())->toEqual($before);
});
