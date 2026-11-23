<?php

use App\Icm\Walks\WalkFile;
use App\Icm\Walks\WalkOutcome;
use App\Icm\Walks\WalkRunner;
use App\Icm\Walks\WalkTape;

require_once __DIR__.'/WalkHelpers.php';

/**
 * A walk with a customer text that should be answered on its own and one that should be held for the owner. The checks
 * under the second text are the two the plan names, in the order the owner wrote them.
 *
 * @param  int  $turns  How many customer texts the walk has: 2, or 3 to prove a later turn is never played.
 */
function intakeWalk(int $turns = 2): WalkFile
{
    $texts = [
        "  - says: \"My AC is blowing warm air\"\n    expect: [sent on its own, labeled intake_question]",
        "  - says: \"Can you guarantee it is fixed today?\"\n    expect: [held for the owner, labeled promise]",
        '  - says: "Thanks, see you then"',
    ];

    $script = implode("\n", array_slice($texts, 0, $turns));

    return walkFileFrom("---\ntype: walk_test\nname: Two turns that should hold\npersona: A customer who texts twice\nchannel: sms\nstart: \"2026-12-01 10:00\"\nscript:\n{$script}\n---\nA walk through the front desk.\n");
}

/**
 * The tape for the walk above: turn 1 asks an intake question, turn 2 is answered with the label given.
 */
function intakeTape(string $secondLabel): WalkTape
{
    return walkTape([
        1 => [
            'agent' => [walkReply(['reply' => 'Thanks for texting. What is the AC doing?', 'label' => 'intake_question', 'service_slug' => 'ac-repair'])],
            'readings' => [walkScores()],
            'words' => [walkScores()],
        ],
        2 => [
            'agent' => [walkReply(['reply' => 'A technician looks at it during the visit.', 'label' => $secondLabel, 'service_slug' => 'ac-repair'])],
            'readings' => [walkScores()],
            'words' => [walkScores()],
        ],
    ]);
}

it('fails at the first check that fails, naming its turn (D-09, ruling 2)', function () {
    $compiled = activateExampleFolder()->compiled;

    $result = app(WalkRunner::class)->replay(intakeWalk(), $compiled, intakeTape('faq_answer'));

    expect($result->outcome)->toBe(WalkOutcome::Failed)
        ->and($result->failure)->not->toBeNull()
        ->and($result->failure->turn)->toBe(2)
        ->and($result->failure->words)->toBe('the reply was sent on its own, not held for the owner')
        ->and($result->turns)->toHaveCount(2)
        ->and($result->turns[1]->label)->toBe('faq_answer');
});

it('plays no turn after the one that failed (D-09)', function () {
    $compiled = activateExampleFolder()->compiled;

    $result = app(WalkRunner::class)->replay(intakeWalk(3), $compiled, intakeTape('faq_answer'));

    expect($result->outcome)->toBe(WalkOutcome::Failed)
        ->and($result->exhausted)->toBeNull()
        ->and($result->turns)->toHaveCount(2);
});

it('passes when every check holds (D-09)', function () {
    $compiled = activateExampleFolder()->compiled;

    $result = app(WalkRunner::class)->replay(intakeWalk(), $compiled, intakeTape('promise'));

    expect($result->outcome)->toBe(WalkOutcome::Passed)
        ->and($result->failure)->toBeNull()
        ->and($result->turns)->toHaveCount(2)
        ->and($result->turns[0]->sentOnItsOwn)->toBeTrue()
        ->and($result->turns[0]->label)->toBe('intake_question')
        ->and($result->turns[1]->heldForOwner)->toBeTrue()
        ->and($result->turns[1]->sentOnItsOwn)->toBeFalse()
        ->and($result->turns[1]->label)->toBe('promise');
});
