<?php

use App\Icm\ActiveConfig;
use App\Icm\Exceptions\NoActiveVersion;
use App\Icm\Loader;
use App\Rooms\RoomRegistry;
use Illuminate\Support\Facades\File;

const PRETEND_NOTE_PATH = 'rooms/pretend/notes/first-note.md';

/**
 * The pretend room's example note as the fixture lays it, with one front matter line replaced by $line, or removed
 * when $line is null. Only the first line starting with "$field:" changes.
 */
function pretendNoteWith(string $field, ?string $line): string
{
    $note = File::get(base_path('tests/Fixtures/pretend-room/'.PRETEND_NOTE_PATH));

    return (string) preg_replace('/^'.preg_quote($field, '/').':.*\R/m', $line === null ? '' : $line."\n", $note, 1);
}

it('reads the pretend room\'s example note, compiles it and serves it at run time', function () {
    enablePretendRoom();
    layPretendRoomFolder('rooms/pretend/CONTEXT.md', PRETEND_NOTE_PATH);

    $version = activateExampleFolder();

    expect($version->file_count)->toBe(exampleFolderFileCount() + 2)
        ->and($version->compiled['index']['pretend_note'])->toBe([PRETEND_NOTE_PATH]);

    $notes = app(ActiveConfig::class)->filesOfType('pretend_note');

    expect($notes)->toHaveCount(1)
        ->and($notes[0])->toMatchArray(['type' => 'pretend_note', 'title' => 'First note', 'pinned' => true, 'path' => PRETEND_NOTE_PATH])
        ->and($notes[0]['body'])->toContain('pretend plant');
});

it('keeps a list of paths for a type that more than one file has', function () {
    enablePretendRoom();
    layPretendRoomFolder('rooms/pretend/CONTEXT.md', PRETEND_NOTE_PATH);
    writeIcmFile('rooms/pretend/notes/second-note.md', pretendNoteWith('title', 'title: Second note'));

    $version = activateExampleFolder();

    expect($version->compiled['index']['pretend_note'])->toBe([PRETEND_NOTE_PATH, 'rooms/pretend/notes/second-note.md'])
        ->and(array_column(app(ActiveConfig::class)->filesOfType('pretend_note'), 'title'))->toBe(['First note', 'Second note']);
});

dataset('broken pretend notes', [
    'pinned written as yes' => ['pinned', 'pinned: yes', 'rooms/pretend/notes/first-note.md: pinned must be true or false (you wrote yes)'],
    'title deleted' => ['title', null, 'rooms/pretend/notes/first-note.md: title is missing, and it is required'],
    'pinned deleted' => ['pinned', null, 'rooms/pretend/notes/first-note.md: pinned is missing, and it is required'],
]);

it('refuses a broken example note in plain words and keeps the previous version live', function (string $field, ?string $line, string $message) {
    enablePretendRoom();
    layPretendRoomFolder('rooms/pretend/CONTEXT.md', PRETEND_NOTE_PATH);
    $live = activateExampleFolder();

    writeIcmFile(PRETEND_NOTE_PATH, pretendNoteWith($field, $line));

    $this->artisan('icm:sync')
        ->expectsOutputToContain($message)
        ->assertExitCode(1);

    expect(rejectedErrors())->toBe([$message])
        ->and(app(ActiveConfig::class)->versionId())->toBe($live->id)
        ->and(app(ActiveConfig::class)->filesOfType('pretend_note'))->toHaveCount(1);
})->with('broken pretend notes');

it('refuses a pinned note with nothing under its front matter, by the type\'s own check', function () {
    enablePretendRoom();
    layPretendRoomFolder('rooms/pretend/CONTEXT.md', PRETEND_NOTE_PATH);
    $live = activateExampleFolder();

    writeIcmFile(PRETEND_NOTE_PATH, "---\ntype: pretend_note\ntitle: First note\npinned: true\n---\n   \n");

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe(['rooms/pretend/notes/first-note.md: a pinned note needs some words under its front matter'])
        ->and(app(ActiveConfig::class)->versionId())->toBe($live->id);
});

it('lets an unpinned note have no words', function () {
    enablePretendRoom();
    layPretendRoomFolder('rooms/pretend/CONTEXT.md');
    writeIcmFile(PRETEND_NOTE_PATH, "---\ntype: pretend_note\ntitle: Empty one\npinned: false\n---\n");

    activateExampleFolder();

    expect(app(ActiveConfig::class)->filesOfType('pretend_note'))->toHaveCount(1);
});

it('adds the room\'s file types to the sentence that lists the ones the sync knows', function () {
    enablePretendRoom();
    writeIcmFile('notes/recipe.md', "---\ntype: recipe\n---\nBody");

    $this->artisan('icm:sync')->assertExitCode(1);

    $types = [...Loader::TYPED, ...app(RoomRegistry::class)->registeredTypes()];

    expect(rejectedErrors())->toBe(['notes/recipe.md: type must be one of '.implode(', ', $types).' (you wrote recipe)'])
        ->and($types)->toContain('pretend_note');
});

it('does not know the pretend note type while the pretend room is not registered', function () {
    writeIcmFile('notes/first.md', File::get(base_path('tests/Fixtures/pretend-room/'.PRETEND_NOTE_PATH)));

    $this->artisan('icm:sync')->assertExitCode(1);

    $types = [...Loader::TYPED, ...app(RoomRegistry::class)->registeredTypes()];

    expect(rejectedErrors())->toBe(['notes/first.md: type must be one of '.implode(', ', $types).' (you wrote pretend_note)'])
        ->and($types)->not->toContain('pretend_note');
});

it('has no files to serve before the first sync', function () {
    expect(fn () => app(ActiveConfig::class)->filesOfType('pretend_note'))->toThrow(NoActiveVersion::class);
});
