<?php

use App\Icm\ActiveConfig;
use App\Icm\PlainWords;
use App\Rooms\Room;
use App\Rooms\RoomRegistry;
use Illuminate\Support\Facades\File;
use Tests\Rooms\PretendRoom\AnnexRoom;

/**
 * Switch both test-only rooms on for this test: the pretend room first, then the annex room beside it.
 */
function enableAnnexRoom(): void
{
    enablePretendRoom();
    app(RoomRegistry::class)->add(AnnexRoom::room());
}

/**
 * A test room's contract as its fixture lays it, with the owns line replaced by $owns.
 */
function ownershipContract(string $room, string $owns): string
{
    $contract = File::get(base_path("tests/Fixtures/{$room}-room/rooms/{$room}/CONTEXT.md"));

    return (string) preg_replace('/^owns:.*\R/m', 'owns: '.$owns."\n", $contract, 1);
}

/**
 * The pretend room's example note exactly as its fixture holds it.
 */
function pretendNoteContents(): string
{
    return File::get(base_path('tests/Fixtures/pretend-room/rooms/pretend/notes/first-note.md'));
}

/**
 * Write a contract for a test-only room that reads nothing, writes no records and has no connector.
 */
function plainRoomContract(string $room, string $owns): string
{
    return "---\ntype: room\nowns: {$owns}\nreads: none\nwrites_records: none\nconnector: none\n---\n\n# {$room} room\n\nA room that exists only in tests.\n";
}

/**
 * Run the sync, expect the refusal, and hand back the lines it was refused with.
 *
 * @return list<string>
 */
function refusedOwnershipLines(): array
{
    test()->artisan('icm:sync')->assertExitCode(1);

    return rejectedErrors();
}

it('lets both test rooms into one folder when each owns what it handles', function () {
    enableAnnexRoom();
    layFixtureFolder('annex-room');
    layPretendRoomFolder('rooms/pretend/CONTEXT.md', 'rooms/pretend/notes/first-note.md');

    $version = activateExampleFolder();

    expect($version->file_count)->toBe(exampleFolderFileCount() + 3)
        ->and(app(ActiveConfig::class)->roomKeys())->toBe(sortedRoomKeys([...exampleRoomKeys(), 'annex', 'pretend']));
});

it('refuses a claim of a file the claiming room does not handle, naming the room that does', function () {
    enablePretendRoom();
    app(RoomRegistry::class)->add(new Room(key: 'quiet', label: 'quiet'));
    layPretendRoomFolder('rooms/pretend/CONTEXT.md', 'rooms/pretend/notes/first-note.md');
    writeIcmFile('rooms/quiet/CONTEXT.md', plainRoomContract('quiet', '[rooms/pretend/notes/first-note.md]'));

    expect(refusedOwnershipLines())->toBe(['rooms/quiet/CONTEXT.md: rooms/pretend/notes/first-note.md is a pretend_note file, which the pretend room handles; quiet can read it']);
});

dataset('files that are not a room\'s to claim', [
    'a router' => ['CLAUDE.md', null, null, 'rooms/pretend/CONTEXT.md: CLAUDE.md belongs to the house (routers and walk tests); pretend can read it'],
    'a router in the folder root' => ['START-HERE.md', null, null, 'rooms/pretend/CONTEXT.md: START-HERE.md belongs to the house (routers and walk tests); pretend can read it'],
    'a walk test' => ['tests/walk.md', 'tests/walk.md', "---\ntype: walk_test\n---\nA walk through the front desk.", 'rooms/pretend/CONTEXT.md: tests/walk.md belongs to the house (routers and walk tests); pretend can read it'],
    'a connector file' => ['connectors/feed.md', 'connectors/feed.md', "---\ntype: connector\nslot: pretend_feed\nowns: []\nreads: []\n---\nThe feed.", 'rooms/pretend/CONTEXT.md: connectors/feed.md is a connector file; a room owns its connector through its slot, so leave it out of owns'],
]);

it('refuses a claim of the house\'s files and of a connector file', function (string $claimed, ?string $written, ?string $contents, string $line) {
    enablePretendRoom();

    if ($written !== null) {
        writeIcmFile($written, $contents);
    }

    writeIcmFile('rooms/pretend/CONTEXT.md', ownershipContract('pretend', "[{$claimed}]"));

    expect(refusedOwnershipLines())->toBe([$line]);
})->with('files that are not a room\'s to claim');

it('treats a router inside the claiming room\'s own folder as the room\'s, not the house\'s', function () {
    enablePretendRoom();
    layPretendRoomFolder('rooms/pretend/CONTEXT.md');
    writeIcmFile('rooms/pretend/notes/CONTEXT.md', 'Points at the notes.');
    writeIcmFile('rooms/pretend/CONTEXT.md', ownershipContract('pretend', '[rooms/pretend/notes/*.md]'));

    activateExampleFolder();

    expect(app(ActiveConfig::class)->hasRoom('pretend'))->toBeTrue();
});

it('refuses a file two rooms both handle and both claim, blaming the later contract', function () {
    enableAnnexRoom();
    layFixtureFolder('annex-room');
    layPretendRoomFolder('rooms/pretend/CONTEXT.md', 'rooms/pretend/notes/first-note.md');
    writeIcmFile('rooms/annex/CONTEXT.md', ownershipContract('annex', '[rooms/pretend/notes/*.md]'));

    expect(refusedOwnershipLines())->toBe(['rooms/pretend/CONTEXT.md: rooms/pretend/notes/first-note.md is claimed by both rooms/annex/CONTEXT.md and rooms/pretend/CONTEXT.md; pick one, the other can read it']);
});

it('refuses the same owns entry in two contracts even when no file matches it yet', function () {
    enableAnnexRoom();
    layFixtureFolder('annex-room');
    layPretendRoomFolder('rooms/pretend/CONTEXT.md');
    writeIcmFile('rooms/annex/CONTEXT.md', ownershipContract('annex', '[rooms/pretend/notes/*.md]'));

    expect(refusedOwnershipLines())->toBe(['rooms/pretend/CONTEXT.md: rooms/pretend/notes/*.md is claimed by both rooms/annex/CONTEXT.md and rooms/pretend/CONTEXT.md; pick one, the other can read it']);
});

/**
 * WR-03: the owns entry is the owner's own text, so it is quoted the way PlainWords quotes every owner value: one line,
 * cut at 80 characters. The entry here matches no file, so it is not a loaded path.
 */
it('shortens a very long owns entry two contracts both write, as every owner value is', function () {
    enableAnnexRoom();
    layFixtureFolder('annex-room');
    layPretendRoomFolder('rooms/pretend/CONTEXT.md');
    $entry = 'rooms/pretend/notes/'.str_repeat('long-name-', 12).'*.md';
    writeIcmFile('rooms/annex/CONTEXT.md', ownershipContract('annex', "[{$entry}]"));
    writeIcmFile('rooms/pretend/CONTEXT.md', ownershipContract('pretend', "[{$entry}]"));

    $lines = refusedOwnershipLines();

    expect(mb_strlen($entry))->toBeGreaterThan(80)
        ->and($lines)->toBe(['rooms/pretend/CONTEXT.md: '.PlainWords::oneLine($entry).' is claimed by both rooms/annex/CONTEXT.md and rooms/pretend/CONTEXT.md; pick one, the other can read it'])
        ->and($lines[0])->not->toContain($entry);
});

it('refuses a file nobody lists under owns while a room that handles its type is here', function () {
    enableAnnexRoom();
    layFixtureFolder('annex-room');
    layPretendRoomFolder('rooms/pretend/CONTEXT.md', 'rooms/pretend/notes/first-note.md');
    writeIcmFile('rooms/pretend/CONTEXT.md', ownershipContract('pretend', '[]'));

    expect(refusedOwnershipLines())->toBe(['rooms/pretend/notes/first-note.md: no room lists this pretend_note file under owns; the annex room handles it, so add it to rooms/annex/CONTEXT.md']);
});

it('offers both ways out for a room\'s own kind of file when that room\'s folder is not in the business folder', function () {
    enablePretendRoom();
    writeIcmFile('business/pretend-note.md', pretendNoteContents());

    expect(refusedOwnershipLines())->toBe(["business/pretend-note.md: this is a pretend file, which belongs in rooms/pretend/; this business doesn't use the pretend room, so add rooms/pretend/ and move it there, or remove the file"]);
});

it('says only the missing contract and the move-it line when the room\'s folder is there without its contract', function () {
    enablePretendRoom();
    layPretendRoomFolder('rooms/pretend/notes/first-note.md');
    writeIcmFile('business/pretend-note.md', pretendNoteContents());

    expect(refusedOwnershipLines())->toBe([
        'business/pretend-note.md: this is a pretend file, which belongs in rooms/pretend/; move it there',
        "rooms/pretend/CONTEXT.md: the pretend room's folder has files but no CONTEXT.md; add the room's contract with type: room",
    ]);
});

it('gives the same lines in the same order when the same refused folder is synced twice', function () {
    enablePretendRoom();
    layPretendRoomFolder('rooms/pretend/notes/first-note.md');
    writeIcmFile('business/pretend-note.md', pretendNoteContents());

    $first = refusedOwnershipLines();
    $second = refusedOwnershipLines();

    expect($second)->toBe($first)->and($first)->toHaveCount(2);
});

it('keeps a room\'s own kind of file in its folder, and leaves today\'s kinds of file where they are', function () {
    enablePretendRoom();
    layPretendRoomFolder('rooms/pretend/CONTEXT.md', 'rooms/pretend/notes/first-note.md');
    $live = activateExampleFolder();

    expect(File::exists(icmPath('business/faq.md')))->toBeTrue();

    writeIcmFile('notes/first.md', pretendNoteContents());
    File::delete(icmPath('rooms/pretend/notes/first-note.md'));

    expect(refusedOwnershipLines())->toBe(['notes/first.md: this is a pretend file, which belongs in rooms/pretend/; move it there'])
        ->and(app(ActiveConfig::class)->versionId())->toBe($live->id);
});

it('refuses a room\'s own kind of file inside another room\'s folder', function () {
    enableAnnexRoom();
    layFixtureFolder('annex-room');
    layPretendRoomFolder('rooms/pretend/CONTEXT.md');
    writeIcmFile('rooms/annex/notes/stray.md', pretendNoteContents());

    expect(refusedOwnershipLines())->toBe(['rooms/annex/notes/stray.md: this is a pretend file, which belongs in rooms/pretend/; move it there']);
});

it('refuses a folder with a registered room\'s own kind of file that its contract does not list', function () {
    enablePretendRoom();
    layPretendRoomFolder('rooms/pretend/CONTEXT.md', 'rooms/pretend/notes/first-note.md');
    writeIcmFile('rooms/pretend/CONTEXT.md', ownershipContract('pretend', '[]'));

    expect(refusedOwnershipLines())->toBe(['rooms/pretend/notes/first-note.md: no room lists this pretend_note file under owns; the pretend room handles it, so add it to rooms/pretend/CONTEXT.md']);
});

it('passes the example folder untouched when the pretend room is registered but not in the folder', function () {
    enablePretendRoom();

    $version = activateExampleFolder();

    expect($version->file_count)->toBe(exampleFolderFileCount());
});
