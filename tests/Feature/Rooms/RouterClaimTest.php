<?php

use App\Icm\ActiveConfig;
use Illuminate\Support\Facades\File;

/**
 * The pretend room's contract as its fixture lays it, with the owns line replaced by $owns. Pest test-file functions
 * are global only once their file loads, so this file keeps its own rather than borrowing RoomOwnershipTest's.
 */
function routerClaimContract(string $owns): string
{
    $contract = File::get(base_path('tests/Fixtures/pretend-room/rooms/pretend/CONTEXT.md'));

    return (string) preg_replace('/^owns:.*\R/m', 'owns: '.$owns."\n", $contract, 1);
}

/**
 * Run the sync, expect the refusal, and hand back the lines it was refused with.
 *
 * @return list<string>
 */
function routerClaimRefusal(): array
{
    test()->artisan('icm:sync')->assertExitCode(1);

    return rejectedErrors();
}

/**
 * The pattern is quoted because a bare * at the start of a YAML value is read as an alias, not as text.
 */
it('lets a pattern in owns pass over a router it happens to match', function () {
    enablePretendRoom();
    layPretendRoomFolder('rooms/pretend/CONTEXT.md', 'rooms/pretend/notes/first-note.md');
    writeIcmFile('business/notes/CONTEXT.md', 'Points at the notes.');
    writeIcmFile('rooms/pretend/CONTEXT.md', routerClaimContract('["*/notes/*.md"]'));

    activateExampleFolder();

    expect(app(ActiveConfig::class)->roomOwnedFiles('pretend'))->toBe(['rooms/pretend/notes/first-note.md'])
        ->and(app(ActiveConfig::class)->version()->compiled['files']['business/notes/CONTEXT.md']['router'])->toBeTrue();
});

it('still refuses a router the contract names exactly', function () {
    enablePretendRoom();
    layPretendRoomFolder('rooms/pretend/CONTEXT.md', 'rooms/pretend/notes/first-note.md');
    writeIcmFile('business/notes/CONTEXT.md', 'Points at the notes.');
    writeIcmFile('rooms/pretend/CONTEXT.md', routerClaimContract('[rooms/pretend/notes/*.md, business/notes/CONTEXT.md]'));

    expect(routerClaimRefusal())->toBe(['rooms/pretend/CONTEXT.md: business/notes/CONTEXT.md belongs to the house (routers and walk tests); pretend can read it']);
});

it('still refuses a walk test a pattern matches', function () {
    enablePretendRoom();
    layPretendRoomFolder('rooms/pretend/CONTEXT.md', 'rooms/pretend/notes/first-note.md');
    writeIcmFile('tests/walk.md', "---\ntype: walk_test\n---\nA walk through the front desk.");
    writeIcmFile('rooms/pretend/CONTEXT.md', routerClaimContract('[rooms/pretend/notes/*.md, tests/*.md]'));

    expect(routerClaimRefusal())->toBe(['rooms/pretend/CONTEXT.md: tests/walk.md belongs to the house (routers and walk tests); pretend can read it']);
});
