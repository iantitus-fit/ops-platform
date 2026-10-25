<?php

use App\Enums\IcmFileKind;
use App\Icm\IcmFile;
use App\Icm\RoomChecks;
use App\Rooms\Room;
use App\Rooms\RoomRegistry;

/**
 * These branches are reached in the running app only through a kind of file no room handles, which stops existing once
 * the front desk room handles triage in Phase 6, so this unit test keeps them covered with a registry the test builds
 * itself. The last case covers a registry with no rooms, which stops being reachable once the first real room is
 * registered (D-23).
 */

/**
 * A typed file as the Loader would hand it to RoomChecks, built by hand so no folder is read.
 *
 * @param  array<string, mixed>  $frontMatter
 */
function roomChecksUnitFile(string $path, string $type, array $frontMatter = []): IcmFile
{
    return new IcmFile(
        path: $path,
        kind: IcmFileKind::Typed,
        type: $type,
        frontMatter: ['type' => $type, ...$frontMatter],
        body: 'Words.',
        raw: '',
    );
}

it('says no room handles a type yet when a present room claims a file of that type', function () {
    $registry = new RoomRegistry;
    $registry->add(new Room(key: 'quiet', label: 'quiet'));

    $errors = (new RoomChecks($registry))->errors(collect([
        roomChecksUnitFile('rooms/quiet/CONTEXT.md', 'room', ['owns' => ['business/faq.md'], 'reads' => [], 'writes_records' => [], 'connector' => 'none']),
        roomChecksUnitFile('business/faq.md', 'knowledge'),
    ]));

    expect($errors)->toBe([[
        'file' => 'rooms/quiet/CONTEXT.md',
        'field' => 'owns',
        'message' => 'business/faq.md is a knowledge file, which no room handles yet; quiet can read it',
    ]]);
});

it('names the absent room when the only room that handles a type is not in the files', function () {
    $registry = new RoomRegistry;
    $registry->add(new Room(key: 'keeper', label: 'keeper', handles: ['knowledge']));

    $errors = (new RoomChecks($registry))->errors(collect([
        roomChecksUnitFile('business/faq.md', 'knowledge'),
    ]));

    expect($errors)->toBe([[
        'file' => 'business/faq.md',
        'field' => null,
        'message' => "this file belongs to the keeper room, which this business doesn't use; add rooms/keeper/ or remove the file",
    ]]);
});

it('says the engine has no rooms yet when a folder names a room and the registry is empty', function () {
    $errors = (new RoomChecks(new RoomRegistry))->errors(collect([
        roomChecksUnitFile('rooms/foo/CONTEXT.md', 'room', ['owns' => [], 'reads' => [], 'writes_records' => [], 'connector' => 'none']),
    ]));

    expect($errors)->toContain([
        'file' => 'rooms/foo/',
        'field' => null,
        'message' => 'this engine has no foo room, and no rooms yet',
    ]);
});
