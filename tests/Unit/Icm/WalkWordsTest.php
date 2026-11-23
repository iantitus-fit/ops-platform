<?php

use App\Icm\Walks\WalkFailure;
use App\Icm\Walks\WalkGateEntry;
use App\Icm\Walks\WalkGateResult;
use App\Icm\Walks\WalkOutcome;

/**
 * One walk's gate entry, built by hand with any field a case wants to change. By default the walk was recorded, passed
 * and is not stale.
 *
 * @param  array<string, mixed>  $with
 */
function wordsEntry(array $with = []): WalkGateEntry
{
    return new WalkGateEntry(...[
        'path' => 'tests/dana.md',
        'recorded' => true,
        'outcome' => WalkOutcome::Passed,
        'failure' => null,
        'exhausted' => null,
        'stale' => false,
        'error' => null,
        ...$with,
    ]);
}

/**
 * Every kind of walk entry a refusal can come from, each with its owner line and Ian's line for a live version "v3".
 *
 * @return array<string, array{WalkGateEntry, string, string}>
 */
function wordsRefusals(): array
{
    return [
        'failed at a turn' => [
            wordsEntry(['outcome' => WalkOutcome::Failed, 'failure' => new WalkFailure(3, 'the reply did not carry the price sentence for AC repair')]),
            'tests/dana.md, turn 3: the reply did not carry the price sentence for AC repair. v3 stays live.',
            'tests/dana.md, turn 3: the reply did not carry the price sentence for AC repair. v3 stays live.',
        ],
        'failed at the end' => [
            wordsEntry(['outcome' => WalkOutcome::Failed, 'failure' => new WalkFailure(null, 'the owner was not alerted')]),
            'tests/dana.md, at the end: the owner was not alerted. v3 stays live.',
            'tests/dana.md, at the end: the owner was not alerted. v3 stays live.',
        ],
        'no recording' => [
            wordsEntry(['recorded' => false, 'outcome' => null]),
            "tests/dana.md: this walk has no recording yet. Recording it is Ian's step. v3 stays live.",
            'tests/dana.md: no recording. Run `walks:record tests/dana.md`. v3 stays live.',
        ],
        'needs a model reply' => [
            wordsEntry(['outcome' => WalkOutcome::NeedsRecording, 'exhausted' => ['turn' => 3, 'needed' => 'model reply']]),
            "tests/dana.md: this walk needs a new recording. Recording it is Ian's step. v3 stays live.",
            'tests/dana.md: the recording no longer answers this walk (turn 3 needed a model reply it does not hold). Run `walks:record tests/dana.md`. v3 stays live.',
        ],
        'needs a Jev reading' => [
            wordsEntry(['outcome' => WalkOutcome::NeedsRecording, 'exhausted' => ['turn' => 2, 'needed' => 'Jev reading']]),
            "tests/dana.md: this walk needs a new recording. Recording it is Ian's step. v3 stays live.",
            'tests/dana.md: the recording no longer answers this walk (turn 2 needed a Jev reading it does not hold). Run `walks:record tests/dana.md`. v3 stays live.',
        ],
        'stopped with an error' => [
            wordsEntry(['outcome' => WalkOutcome::Errored, 'error' => 'disk full']),
            'tests/dana.md: this walk stopped with an error before it finished. v3 stays live.',
            'tests/dana.md: the walk stopped with an error (disk full). v3 stays live.',
        ],
    ];
}

it('says each refused walk to the owner in plain words with the live version named', function (WalkGateEntry $entry, string $owner) {
    expect((new WalkGateResult([$entry]))->ownerLines('v3'))->toBe([$owner]);
})->with(wordsRefusals());

it('says each refused walk to Ian with the command and the file name', function (WalkGateEntry $entry, string $owner, string $ian) {
    expect((new WalkGateResult([$entry]))->ianLines('v3'))->toBe([$ian]);
})->with(wordsRefusals());

it('closes each line with Nothing is live yet when nothing is live (ruling 3)', function (WalkGateEntry $entry) {
    $result = new WalkGateResult([$entry]);

    expect($result->ownerLines(null)[0])->toEndWith(' Nothing is live yet.')->not->toContain('stays live')
        ->and($result->ianLines(null)[0])->toEndWith(' Nothing is live yet.')->not->toContain('stays live');
})->with(array_map(fn (array $row) => [$row[0]], wordsRefusals()));

it('says each stale walk to Ian only, with a shorter line after a refusal (D-04, ruling 5)', function (WalkOutcome $outcome, string $line) {
    $result = new WalkGateResult([wordsEntry(['outcome' => $outcome, 'stale' => true])]);

    expect($result->staleNotes())->toBe([$line]);
})->with([
    'after a pass' => [WalkOutcome::Passed, 'tests/dana.md is stale: its recording is older than the front desk prompt or the triage questions. It still ran and passed its checks. Run `walks:record tests/dana.md` to refresh it.'],
    'after a failure' => [WalkOutcome::Failed, 'tests/dana.md is stale: its recording is older than the front desk prompt or the triage questions. Run `walks:record tests/dana.md` to refresh it.'],
    'after a recording that no longer answers' => [WalkOutcome::NeedsRecording, 'tests/dana.md is stale: its recording is older than the front desk prompt or the triage questions. Run `walks:record tests/dana.md` to refresh it.'],
]);

it('never refuses a walk for being stale, and never shows the owner a stale walk (D-04)', function () {
    $result = new WalkGateResult([wordsEntry(['stale' => true])]);

    expect($result->refused())->toBeFalse()
        ->and($result->ownerLines('v3'))->toBe([])
        ->and($result->ianLines('v3'))->toBe([]);
});

it('refuses for each kind of refused walk and for none other', function (WalkGateEntry $entry) {
    expect((new WalkGateResult([$entry]))->refused())->toBeTrue();
})->with(array_map(fn (array $row) => [$row[0]], wordsRefusals()));

it('leaves a result with no walk and a result of passing walks unrefused', function () {
    expect(WalkGateResult::none()->refused())->toBeFalse()
        ->and(WalkGateResult::none()->entries())->toBe([])
        ->and((new WalkGateResult([wordsEntry(), wordsEntry(['path' => 'tests/ben.md'])]))->refused())->toBeFalse();
});

it('keeps the owner lines free of any command, console wording and the word stale (D-04, D-05)', function () {
    $entries = array_map(fn (array $row) => $row[0], array_values(wordsRefusals()));
    $lines = (new WalkGateResult([...$entries, wordsEntry(['stale' => true])]))->ownerLines('v3');

    expect($lines)->toHaveCount(count($entries));

    foreach ($lines as $line) {
        expect($line)->not->toContain('walks:record')->not->toContain('`')->not->toContain('artisan')->not->toContain('stale')->not->toContain('Run ');
    }
});

it('puts no exclamation point and no em dash in any line, whatever an error or a name holds', function () {
    $entries = [
        ...array_map(fn (array $row) => $row[0], array_values(wordsRefusals())),
        wordsEntry(['path' => 'tests/ben!.md', 'outcome' => WalkOutcome::Errored, 'error' => 'out of room! — retry later', 'stale' => true]),
        wordsEntry(['path' => 'tests/cara.md', 'outcome' => WalkOutcome::Failed, 'failure' => new WalkFailure(1, 'the reply held a word! and a dash — here'), 'stale' => true]),
    ];

    $result = new WalkGateResult($entries);

    foreach ([...$result->ownerLines('v3'), ...$result->ownerLines(null), ...$result->ianLines('v3'), ...$result->ianLines(null), ...$result->staleNotes()] as $line) {
        expect($line)->not->toContain('!')->not->toContain('—');
    }
});

it('marks a line break or a hidden character in a name instead of printing it (T-10-16)', function () {
    $name = "tests/da\nna\u{200B}.md";
    $unrecorded = new WalkGateResult([wordsEntry(['path' => $name, 'recorded' => false, 'outcome' => null])]);
    $stale = new WalkGateResult([wordsEntry(['path' => $name, 'stale' => true])]);

    expect($unrecorded->ownerLines('v3'))->toBe(["tests/da[line break]na[hidden character].md: this walk has no recording yet. Recording it is Ian's step. v3 stays live."])
        ->and($unrecorded->ianLines('v3')[0])->toBe('tests/da[line break]na[hidden character].md: no recording. Run `walks:record tests/da[line break]na[hidden character].md`. v3 stays live.')
        ->and($stale->staleNotes()[0])->not->toContain("\n")->toContain('tests/da[line break]na[hidden character].md is stale');
});

it('keeps the walks in the order given and one line per refused walk (edge: ordering, many)', function () {
    $result = new WalkGateResult([
        wordsEntry(['path' => 'tests/a.md', 'recorded' => false, 'outcome' => null]),
        wordsEntry(['path' => 'tests/b.md']),
        wordsEntry(['path' => 'tests/c.md', 'outcome' => WalkOutcome::Failed, 'failure' => new WalkFailure(2, 'the reply was held for the owner, not sent on its own')]),
        wordsEntry(['path' => 'tests/d.md', 'outcome' => WalkOutcome::NeedsRecording, 'exhausted' => ['turn' => 1, 'needed' => 'model reply']]),
    ]);

    expect(array_map(fn (WalkGateEntry $entry) => $entry->path, $result->entries()))->toBe(['tests/a.md', 'tests/b.md', 'tests/c.md', 'tests/d.md'])
        ->and($result->ownerLines('v3'))->toBe([
            "tests/a.md: this walk has no recording yet. Recording it is Ian's step. v3 stays live.",
            'tests/c.md, turn 2: the reply was held for the owner, not sent on its own. v3 stays live.',
            "tests/d.md: this walk needs a new recording. Recording it is Ian's step. v3 stays live.",
        ]);
});
