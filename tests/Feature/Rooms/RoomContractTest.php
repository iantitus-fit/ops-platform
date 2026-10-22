<?php

use App\Icm\ActiveConfig;
use Illuminate\Support\Facades\File;

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
