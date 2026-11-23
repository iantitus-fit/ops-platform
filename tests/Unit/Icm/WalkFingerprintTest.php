<?php

use App\Icm\Walks\WalkCheck;
use App\Icm\Walks\WalkFile;
use App\Icm\Walks\WalkFingerprint;
use App\Icm\Walks\WalkStep;
use App\Icm\Walks\WalkStepKind;

/**
 * A walk file as a value, with any field a case wants to change replaced. Two customer texts, a wait, a booking and a
 * check under the first text.
 *
 * @param  array<string, mixed>  $with
 */
function fingerprintWalk(array $with = []): WalkFile
{
    return new WalkFile(...[
        'path' => 'tests/dana.md',
        'name' => 'Dana books a visit',
        'persona' => 'A homeowner whose air conditioner stopped',
        'channel' => 'sms',
        'start' => '2026-12-01 10:00',
        'steps' => [
            new WalkStep(1, WalkStepKind::Says, 'My AC stopped working', null, null, [WalkCheck::parse('sent on its own')]),
            new WalkStep(2, WalkStepKind::Wait, null, 120, null, []),
            new WalkStep(3, WalkStepKind::Says, 'Over 10 years', null, null, []),
            new WalkStep(4, WalkStepKind::Books, null, null, '2026-12-02 11:00', []),
        ],
        'endChecks' => [WalkCheck::parse('booked')],
        ...$with,
    ]);
}

it('hashes the customer turns the same way every time (D-05)', function () {
    expect(WalkFingerprint::scriptHash(fingerprintWalk()))->toBe(WalkFingerprint::scriptHash(fingerprintWalk()))
        ->and(WalkFingerprint::scriptHash(fingerprintWalk()))->toMatch('/^[0-9a-f]{64}$/');
});

it('changes the script hash when a customer turn changes, even by one space or one letter of case (D-05)', function (string $changed) {
    $steps = fingerprintWalk()->steps;
    $steps[0] = new WalkStep(1, WalkStepKind::Says, $changed, null, null, $steps[0]->checks);

    expect(WalkFingerprint::scriptHash(fingerprintWalk(['steps' => $steps])))->not->toBe(WalkFingerprint::scriptHash(fingerprintWalk()));
})->with([
    'a different word' => ['My AC stopped cooling'],
    'one trailing space' => ['My AC stopped working '],
    'one leading space' => [' My AC stopped working'],
    'one capital letter' => ['my AC stopped working'],
    'one doubled space' => ['My AC  stopped working'],
    'a non-breaking space' => ["My AC\u{00A0}stopped working"],
]);

it('changes the script hash when the customer turns are in another order or one is missing (D-05)', function () {
    $steps = fingerprintWalk()->steps;
    $swapped = [
        new WalkStep(1, WalkStepKind::Says, 'Over 10 years', null, null, []),
        $steps[1],
        new WalkStep(3, WalkStepKind::Says, 'My AC stopped working', null, null, []),
        $steps[3],
    ];

    expect(WalkFingerprint::scriptHash(fingerprintWalk(['steps' => $swapped])))->not->toBe(WalkFingerprint::scriptHash(fingerprintWalk()))
        ->and(WalkFingerprint::scriptHash(fingerprintWalk(['steps' => [$steps[0]]])))->not->toBe(WalkFingerprint::scriptHash(fingerprintWalk()));
});

it('leaves the script hash alone when anything but a customer turn changes (D-05)', function (string $field) {
    $steps = fingerprintWalk()->steps;

    $changed = match ($field) {
        'an expect under a turn' => ['steps' => [new WalkStep(1, WalkStepKind::Says, 'My AC stopped working', null, null, [WalkCheck::parse('held for the owner')]), $steps[1], $steps[2], $steps[3]]],
        'the end checks' => ['endChecks' => []],
        'the persona' => ['persona' => 'Someone else entirely'],
        'the name' => ['name' => 'A new name'],
        'a wait' => ['steps' => [$steps[0], new WalkStep(2, WalkStepKind::Wait, null, 1440, null, []), $steps[2], $steps[3]]],
        'a books item' => ['steps' => [$steps[0], $steps[1], $steps[2], new WalkStep(4, WalkStepKind::Books, null, null, null, [])]],
        'the start' => ['start' => '2026-12-05 09:00'],
        'the path' => ['path' => 'tests/other.md'],
    };

    expect(WalkFingerprint::scriptHash(fingerprintWalk($changed)))->toBe(WalkFingerprint::scriptHash(fingerprintWalk()));
})->with(['an expect under a turn', 'the end checks', 'the persona', 'the name', 'a wait', 'a books item', 'the start', 'the path']);

it('makes a fingerprint from the prompt material and the Jev questions, and changes it when either changes (D-04)', function () {
    $questions = ['intent' => ['type' => 'choice', 'instructions' => 'What does the customer want?', 'criteria' => ['faq' => 'Asks a question']]];
    $base = WalkFingerprint::of('the prompt', $questions);

    expect($base)->toMatch('/^[0-9a-f]{64}$/')
        ->and(WalkFingerprint::of('the prompt', $questions))->toBe($base)
        ->and(WalkFingerprint::of('the prompt, changed', $questions))->not->toBe($base)
        ->and(WalkFingerprint::of('the prompt', [...$questions, 'price' => ['type' => 'noul', 'instructions' => 'Does the reply state a price?']]))->not->toBe($base)
        ->and(WalkFingerprint::of('the prompt', [...$questions, 'intent' => [...$questions['intent'], 'instructions' => 'What does the customer want here?']]))->not->toBe($base);
});

it('does not let the prompt material and the questions run together (D-04)', function () {
    expect(WalkFingerprint::of('ab', ['c']))->not->toBe(WalkFingerprint::of('a', ['b', 'c']));
});
