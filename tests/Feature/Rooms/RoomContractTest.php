<?php

use App\Icm\ActiveConfig;
use App\Rooms\Room;
use App\Rooms\RoomRegistry;
use Illuminate\Support\Facades\File;
use Tests\Rooms\PretendRoom\FakePretendFeed;
use Tests\Rooms\PretendRoom\PretendFeed;

/**
 * The pretend room's contract as the fixture lays it, with one field line replaced by $line, or removed when $line is
 * null. Only the front matter's first line starting with "$field:" changes.
 */
function roomContractWith(string $field, ?string $line): string
{
    $contract = File::get(base_path('tests/Fixtures/pretend-room/rooms/pretend/CONTEXT.md'));

    return (string) preg_replace('/^'.preg_quote($field, '/').':.*\R/m', $line === null ? '' : $line."\n", $contract, 1);
}

/**
 * The compiled front matter of one file in the live version.
 *
 * @return array<string, mixed>
 */
function roomContractCompiledFrontMatter(string $path): array
{
    return app(ActiveConfig::class)->version()->compiled['files'][$path]['front_matter'];
}

it('lets the pretend contract as laid turn the room on', function () {
    enablePretendRoom();
    layPretendRoomFolder('rooms/pretend/CONTEXT.md');

    activateExampleFolder();

    expect(app(ActiveConfig::class)->hasRoom('pretend'))->toBeTrue();
});

dataset('broken contract fields', [
    'owns deleted' => ['owns', null, 'rooms/pretend/CONTEXT.md: owns is missing, and it is required'],
    'reads deleted' => ['reads', null, 'rooms/pretend/CONTEXT.md: reads is missing, and it is required'],
    'writes_records deleted' => ['writes_records', null, 'rooms/pretend/CONTEXT.md: writes_records is missing, and it is required'],
    'connector deleted' => ['connector', null, 'rooms/pretend/CONTEXT.md: connector is missing, and it is required'],
    'owns written as a word' => ['owns', 'owns: everything', 'rooms/pretend/CONTEXT.md: owns must be a list (you wrote everything)'],
    'reads written as a word' => ['reads', 'reads: everything', 'rooms/pretend/CONTEXT.md: reads must be a list (you wrote everything)'],
    'writes_records written as a word' => ['writes_records', 'writes_records: everything', 'rooms/pretend/CONTEXT.md: writes_records must be a list (you wrote everything)'],
    'owns written as None, which is not the lower-case word none' => ['owns', 'owns: None', 'rooms/pretend/CONTEXT.md: owns must be a list (you wrote None)'],
    'owns written as nothing' => ['owns', 'owns: nothing', 'rooms/pretend/CONTEXT.md: owns must be a list (you wrote nothing)'],
]);

it('refuses a room contract with a field missing or not a list, naming the file and the field', function (string $field, ?string $line, string $message) {
    enablePretendRoom();
    writeIcmFile('rooms/pretend/CONTEXT.md', roomContractWith($field, $line));

    $this->artisan('icm:sync')
        ->expectsOutputToContain($message)
        ->assertExitCode(1);

    expect(rejectedErrors())->toBe([$message]);
})->with('broken contract fields');

it('keeps the previous version live when a later sync finds the contract broken', function () {
    enablePretendRoom();
    layPretendRoomFolder('rooms/pretend/CONTEXT.md');
    $live = activateExampleFolder();

    writeIcmFile('rooms/pretend/CONTEXT.md', roomContractWith('owns', null));

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe(['rooms/pretend/CONTEXT.md: owns is missing, and it is required'])
        ->and(app(ActiveConfig::class)->versionId())->toBe($live->id)
        ->and(app(ActiveConfig::class)->hasRoom('pretend'))->toBeTrue();
});

it('refuses a room contract anywhere but rooms/<name>/CONTEXT.md, and any other type at a contract path', function () {
    enablePretendRoom();
    writeIcmFile('business/room.md', roomContractWith('connector', 'connector: none'));

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe(['business/room.md: type room belongs only in rooms/<name>/CONTEXT.md, the contract at the top of a room folder']);

    File::delete(icmPath('business/room.md'));
    writeIcmFile('rooms/pretend/CONTEXT.md', str_replace('type: room', 'type: knowledge', roomContractWith('connector', 'connector: pretend_feed')));

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe(["rooms/pretend/CONTEXT.md: type must be room, because this file is the pretend room's contract (you wrote knowledge)"]);
});

dataset('empty list fields', [
    'owns as none' => ['owns', 'owns: none'],
    'owns as []' => ['owns', 'owns: []'],
    'reads as none' => ['reads', 'reads: none'],
    'reads as []' => ['reads', 'reads: []'],
    'writes_records as none' => ['writes_records', 'writes_records: none'],
    'writes_records as []' => ['writes_records', 'writes_records: []'],
]);

it('reads the word none and [] as the same empty list', function (string $field, string $line) {
    enablePretendRoom();
    writeIcmFile('rooms/pretend/CONTEXT.md', roomContractWith($field, $line));

    activateExampleFolder();

    expect(app(ActiveConfig::class)->hasRoom('pretend'))->toBeTrue()
        ->and(roomContractCompiledFrontMatter('rooms/pretend/CONTEXT.md')[$field])->toBe([]);
})->with('empty list fields');

it('reads owns: [none] as a one-entry list, not as the word none', function () {
    enablePretendRoom();
    writeIcmFile('rooms/pretend/CONTEXT.md', roomContractWith('owns', 'owns: [none]'));

    activateExampleFolder();

    expect(app(ActiveConfig::class)->hasRoom('pretend'))->toBeTrue()
        ->and(roomContractCompiledFrontMatter('rooms/pretend/CONTEXT.md')['owns'])->toBe(['none']);
});

dataset('paths outside the folder', [
    'owns going up' => ['owns', '"../secrets.md"', 'owns item 1 must be a path inside this folder, with no .. and no leading / (you wrote ../secrets.md)'],
    'owns from the root' => ['owns', '"/etc/passwd"', 'owns item 1 must be a path inside this folder, with no .. and no leading / (you wrote /etc/passwd)'],
    'owns with .. in the middle' => ['owns', '"business/../x.md"', 'owns item 1 must be a path inside this folder, with no .. and no leading / (you wrote business/../x.md)'],
    'owns with an empty entry' => ['owns', '""', 'owns item 1 is empty; write a path inside this folder'],
    'reads going up' => ['reads', '"../secrets.md"', 'reads item 1 must be a path inside this folder, with no .. and no leading / (you wrote ../secrets.md)'],
    'reads from the root' => ['reads', '"/etc/passwd"', 'reads item 1 must be a path inside this folder, with no .. and no leading / (you wrote /etc/passwd)'],
    'reads with .. in the middle' => ['reads', '"business/../x.md"', 'reads item 1 must be a path inside this folder, with no .. and no leading / (you wrote business/../x.md)'],
    'reads with an empty entry' => ['reads', '""', 'reads item 1 is empty; write a path inside this folder'],
    'writes_records going up' => ['writes_records', '"../secrets.md"', 'writes_records item 1 must be a path inside this folder, with no .. and no leading / (you wrote ../secrets.md)'],
    'writes_records from the root' => ['writes_records', '"/etc/passwd"', 'writes_records item 1 must be a path inside this folder, with no .. and no leading / (you wrote /etc/passwd)'],
    'writes_records climbing out of records/' => ['writes_records', '"records/../x.md"', 'writes_records item 1 must be a path inside this folder, with no .. and no leading / (you wrote records/../x.md)'],
    'writes_records with an empty entry' => ['writes_records', '""', 'writes_records item 1 is empty; write a path inside this folder'],
]);

it('refuses a path that leaves the folder, naming the file and the item', function (string $field, string $entry, string $message) {
    enablePretendRoom();
    writeIcmFile('rooms/pretend/CONTEXT.md', roomContractWith($field, "{$field}: [{$entry}]"));

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe(["rooms/pretend/CONTEXT.md: {$message}"]);
})->with('paths outside the folder');

it('names the item that is wrong when only the second entry leaves the folder', function () {
    enablePretendRoom();
    writeIcmFile('rooms/pretend/CONTEXT.md', roomContractWith('owns', 'owns: [rooms/pretend/notes/a.md, ../x.md]'));

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe(['rooms/pretend/CONTEXT.md: owns item 2 must be a path inside this folder, with no .. and no leading / (you wrote ../x.md)']);
});

dataset('records statements outside records', [
    'a file outside records/' => ['notes.md', 'notes.md'],
    'the bare word records' => ['records', 'records'],
    'records/ with nothing after it' => ['records/', 'records/'],
    'a one-entry list holding the word none' => ['none', 'none'],
]);

it('refuses a records statement that is not a path under records/', function (string $entry, string $wrote) {
    enablePretendRoom();
    writeIcmFile('rooms/pretend/CONTEXT.md', roomContractWith('writes_records', "writes_records: [{$entry}]"));

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe(["rooms/pretend/CONTEXT.md: writes_records item 1 must be a path under records/, such as records/today.md (you wrote {$wrote})"]);
})->with('records statements outside records');

it('accepts paths under records/, an empty owns and an empty writes_records', function (string $field, string $line) {
    enablePretendRoom();
    writeIcmFile('rooms/pretend/CONTEXT.md', roomContractWith($field, $line));

    activateExampleFolder();

    expect(app(ActiveConfig::class)->hasRoom('pretend'))->toBeTrue();
})->with([
    'a file under records/' => ['writes_records', 'writes_records: [records/today.md]'],
    'a folder under records/' => ['writes_records', 'writes_records: [records/customers/]'],
    'writes_records empty' => ['writes_records', 'writes_records: []'],
    'owns empty' => ['owns', 'owns: []'],
]);

it('changes nothing for a rooms/<name>/ folder that holds only README files', function () {
    enablePretendRoom();
    writeIcmFile('rooms/pretend/README.md', 'Notes for people, never read by the sync.');

    $version = activateExampleFolder();

    expect($version->file_count)->toBe(exampleFolderFileCount())
        ->and(app(ActiveConfig::class)->hasRoom('pretend'))->toBeFalse();
});

it('names the missing contract of a registered room whose folder holds other files', function () {
    enablePretendRoom();
    writeIcmFile('rooms/pretend/notes/note.md', "---\ntype: knowledge\n---\nA note.");

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe(["rooms/pretend/CONTEXT.md: the pretend room's folder has files but no CONTEXT.md; add the room's contract with type: room"]);
});

it('refuses a folder for a room this engine lacks, naming the rooms that exist, and keeps the previous version live', function () {
    enablePretendRoom();
    $live = activateExampleFolder();
    layPretendRoomFolder('rooms/pretend/CONTEXT.md');
    writeIcmFile('rooms/foo/CONTEXT.md', roomContractWith('connector', 'connector: none'));

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe(['rooms/foo/: this engine has no foo room; the rooms are: pretend'])
        ->and(app(ActiveConfig::class)->versionId())->toBe($live->id);
});

it('says there are no rooms yet when a folder names a room and the engine has none', function () {
    $live = activateExampleFolder();
    writeIcmFile('rooms/foo/CONTEXT.md', roomContractWith('connector', 'connector: none'));

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe(['rooms/foo/: this engine has no foo room, and no rooms yet'])
        ->and(app(ActiveConfig::class)->versionId())->toBe($live->id);
});

it('gives the pretend room a slot whose driver is a fake that remembers every push and reaches nothing outside', function () {
    enablePretendRoom();

    $feed = app(PretendFeed::class);
    $id = $feed->push('hello');

    expect($feed)->toBeInstanceOf(FakePretendFeed::class)
        ->and(app(PretendFeed::class))->toBe($feed)
        ->and($feed->name())->toBe('fake')
        ->and($id)->toStartWith('fake-')
        ->and($feed->pushed())->toBe([['note' => 'hello', 'id' => $id]]);
});

it('picks the pretend feed driver from config each time it is asked', function () {
    enablePretendRoom();
    app(PretendFeed::class);

    config(['pretend.feed.driver' => 'nope']);

    expect(fn () => app(PretendFeed::class))->toThrow(InvalidArgumentException::class, 'Unknown pretend feed driver [nope]. Use fake.');
});

it('lets the pretend contract name its own slot and refuses any other connector', function () {
    enablePretendRoom();
    writeIcmFile('rooms/pretend/CONTEXT.md', roomContractWith('connector', 'connector: pretend_feed'));

    activateExampleFolder();

    expect(app(ActiveConfig::class)->hasRoom('pretend'))->toBeTrue();

    writeIcmFile('rooms/pretend/CONTEXT.md', roomContractWith('connector', 'connector: none'));

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe(["rooms/pretend/CONTEXT.md: connector must be pretend_feed, the pretend room's slot (you wrote none)"]);
});

it('asks a room with no slot to write connector: none', function () {
    enablePretendRoom();
    app(RoomRegistry::class)->add(new Room(key: 'quiet', label: 'quiet'));
    writeIcmFile('rooms/quiet/CONTEXT.md', roomContractWith('connector', 'connector: none'));

    activateExampleFolder();

    expect(app(ActiveConfig::class)->hasRoom('quiet'))->toBeTrue();

    writeIcmFile('rooms/quiet/CONTEXT.md', roomContractWith('connector', 'connector: some_feed'));

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe(['rooms/quiet/CONTEXT.md: connector must be none, because the quiet room has no connector slot (you wrote some_feed)']);
});
