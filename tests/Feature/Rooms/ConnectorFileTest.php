<?php

use App\Icm\ActiveConfig;
use Illuminate\Support\Facades\File;

const PRETEND_CONNECTOR_PATH = 'connectors/pretend-feed.md';

/**
 * The pretend room's connector file as the fixture lays it, with one front matter line replaced by $line, or removed
 * when $line is null. Only the first line starting with "$field:" changes.
 */
function pretendConnectorWith(string $field, ?string $line): string
{
    $connector = File::get(base_path('tests/Fixtures/pretend-room/'.PRETEND_CONNECTOR_PATH));

    return (string) preg_replace('/^'.preg_quote($field, '/').':.*\R/m', $line === null ? '' : $line."\n", $connector, 1);
}

/**
 * The compiled front matter of the pretend connector file in the live version.
 *
 * @return array<string, mixed>
 */
function pretendConnectorCompiledFrontMatter(): array
{
    return app(ActiveConfig::class)->version()->compiled['files'][PRETEND_CONNECTOR_PATH]['front_matter'] ?? [];
}

it('checks one connector file and compiles it into the live version', function () {
    enablePretendRoom();
    layPretendRoomFolder('rooms/pretend/CONTEXT.md', PRETEND_CONNECTOR_PATH);

    $version = activateExampleFolder();

    expect($version->file_count)->toBe(exampleFolderFileCount() + 2)
        ->and($version->notices)->toBe([])
        ->and($version->compiled['index']['connector'] ?? null)->toBe([PRETEND_CONNECTOR_PATH]);
});

dataset('connector fields the sync requires', [
    'slot deleted' => ['slot', 'connectors/pretend-feed.md: slot is missing, and it is required'],
    'owns deleted' => ['owns', 'connectors/pretend-feed.md: owns is missing, and it is required'],
    'reads deleted' => ['reads', 'connectors/pretend-feed.md: reads is missing, and it is required'],
]);

it('refuses a connector file with slot, owns or reads missing, naming the file and the field, and keeps the previous version live', function (string $field, string $line) {
    enablePretendRoom();
    layPretendRoomFolder('rooms/pretend/CONTEXT.md');
    $live = activateExampleFolder();
    writeIcmFile(PRETEND_CONNECTOR_PATH, pretendConnectorWith($field, null));

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe([$line])
        ->and(app(ActiveConfig::class)->versionId())->toBe($live->id);
})->with('connector fields the sync requires');

dataset('connector fields the sync passes through', [
    'system deleted' => ['system', null],
    'direction deleted' => ['direction', null],
    'schedule deleted' => ['schedule', null],
    'direction anything at all' => ['direction', 'direction: sideways'],
    'schedule anything at all' => ['schedule', 'schedule: when the moon is full'],
    'system anything at all' => ['system', 'system: 42'],
]);

it('passes system, direction and schedule through unchecked until CONN-01', function (string $field, ?string $line) {
    enablePretendRoom();
    layPretendRoomFolder('rooms/pretend/CONTEXT.md');
    writeIcmFile(PRETEND_CONNECTOR_PATH, pretendConnectorWith($field, $line));

    $version = activateExampleFolder();

    expect($version->compiled['index']['connector'] ?? null)->toBe([PRETEND_CONNECTOR_PATH]);
})->with('connector fields the sync passes through');

dataset('connector lists written as none or as an empty list', [
    'owns none' => ['owns', 'owns: none'],
    'owns empty list' => ['owns', 'owns: []'],
    'reads none' => ['reads', 'reads: none'],
    'reads empty list' => ['reads', 'reads: []'],
]);

it('reads none and [] as the same empty list in owns and reads, as the room contract does', function (string $field, string $line) {
    enablePretendRoom();
    layPretendRoomFolder('rooms/pretend/CONTEXT.md');
    writeIcmFile(PRETEND_CONNECTOR_PATH, pretendConnectorWith($field, $line));

    activateExampleFolder();

    expect(pretendConnectorCompiledFrontMatter()[$field] ?? 'not compiled')->toBe([]);
})->with('connector lists written as none or as an empty list');

it('refuses owns written as a word that is not a list', function () {
    enablePretendRoom();
    layPretendRoomFolder('rooms/pretend/CONTEXT.md');
    writeIcmFile(PRETEND_CONNECTOR_PATH, pretendConnectorWith('owns', 'owns: customers'));

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe(['connectors/pretend-feed.md: owns must be a list (you wrote customers)']);
});
