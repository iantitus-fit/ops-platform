<?php

use App\Enums\IcmFileKind;
use App\Icm\IcmFile;
use App\Icm\Loader;
use App\Icm\RoomChecks;
use App\Rooms\Room;
use App\Rooms\RoomRegistry;

/**
 * These branches are reached in the running app only through a kind of file no room handles, or through an absent room
 * that alone handles a type. From Phase 6 the front desk handles sending_rules, set_replies, triage and agent, so every
 * hard-coded type has a real room that can never be left out, and these cases keep the branches covered with a registry
 * the test builds itself (05 D-23, 06 D-14). The branch where the previous version stays live after such a refusal needs
 * a whole sync, so its case is in tests/Feature/Rooms/HandBuiltRegistrySyncTest.php. The last case covers a registry with
 * no rooms, which stops being reachable once the first real room is registered (05 D-23).
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

/**
 * IN-01: a path ending in a line break is not a room contract. PCRE's $ also matches before a final newline, so the
 * pattern must end in \z. The Loader's hidden-name check refuses such a name first, but RoomChecks and FolderSync::compile
 * rely on this pattern alone for the contract path, so it must hold by itself.
 */
it('does not take a path with a trailing line break for a room contract', function () {
    expect(preg_match(Loader::ROOM_CONTRACT, "rooms/foo/CONTEXT.md\n"))->toBe(0)
        ->and(preg_match(Loader::ROOM_CONTRACT, 'rooms/foo/CONTEXT.md'))->toBe(1);

    $errors = (new RoomChecks(new RoomRegistry))->contractErrors(
        roomChecksUnitFile("rooms/foo/CONTEXT.md\n", 'room', ['owns' => [], 'reads' => [], 'writes_records' => [], 'connector' => 'none']),
    );

    expect(array_column($errors, 'message'))->toContain('type room belongs only in rooms/<name>/CONTEXT.md, the contract at the top of a room folder');
});
