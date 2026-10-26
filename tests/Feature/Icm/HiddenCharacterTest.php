<?php

use App\Enums\VersionStatus;
use App\Icm\ActiveConfig;
use App\Models\IcmVersion;
use App\Rooms\RoomRegistry;
use Illuminate\Support\Facades\File;

/**
 * The refusal sentence for a name with a hidden character (D-19, D-25). The wording is a placeholder until the wording pass.
 */
function hiddenNameSentence(): string
{
    return 'this name has a hidden character, such as a line break, a tab or an invisible mark; rename the file or folder, because a hidden character can make one name look like another';
}

it('refuses a file name with a line break and shows [line break] in the stored line and in changelog.md', function () {
    $live = activateExampleFolder();

    writeIcmFile("business/notes\nx.md", "---\ntype: knowledge\ntitle: Notes\n---\nBody");

    $line = 'business/notes[line break]x.md: '.hiddenNameSentence();

    $this->artisan('icm:sync')
        ->expectsOutputToContain($line)
        ->assertExitCode(1);

    expect(rejectedErrors())->toBe([$line])
        ->and(app(ActiveConfig::class)->versionId())->toBe($live->id)
        ->and(IcmVersion::where('status', VersionStatus::Active->value)->count())->toBe(1)
        ->and(File::get(icmPath('changelog.md')))->toContain('business/notes[line break]x.md');
});

/**
 * Every kind of hidden character the owner could put in a name, with the mark the refusal line must show instead.
 * ZWJ and ZWNJ are refused on purpose (D-25).
 */
dataset('hidden characters in a name', [
    'a tab' => ["\t", '[hidden character]'],
    'a carriage return' => ["\r", '[line break]'],
    'NEL U+0085' => ["\u{0085}", '[line break]'],
    'a line separator U+2028' => ["\u{2028}", '[line break]'],
    'a paragraph separator U+2029' => ["\u{2029}", '[line break]'],
    'a zero-width space U+200B' => ["\u{200B}", '[hidden character]'],
    'a zero-width non-joiner U+200C' => ["\u{200C}", '[hidden character]'],
    'a zero-width joiner U+200D' => ["\u{200D}", '[hidden character]'],
    'a right-to-left override U+202E' => ["\u{202E}", '[hidden character]'],
    'a left-to-right isolate U+2066' => ["\u{2066}", '[hidden character]'],
    'a soft hyphen U+00AD' => ["\u{00AD}", '[hidden character]'],
    'a byte order mark U+FEFF' => ["\u{FEFF}", '[hidden character]'],
    'a tag character U+E0041' => ["\u{E0041}", '[hidden character]'],
]);

it('refuses a name with any hidden character in one line that shows a mark', function (string $character, string $mark) {
    writeIcmFile("business/odd{$character}name.md", "---\ntype: knowledge\ntitle: Odd\n---\nBody");

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe(["business/odd{$mark}name.md: ".hiddenNameSentence()]);
})->with('hidden characters in a name');

it('refuses a folder name with a line break once, without also saying the room is unknown', function () {
    enablePretendRoom();
    writeIcmFile("rooms/pre\n# tend/CONTEXT.md", "---\ntype: knowledge\n---\nBody");

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe(['rooms/pre[line break]# tend/CONTEXT.md: '.hiddenNameSentence()]);
});

it('refuses a README file inside a folder with a hidden character in its name', function () {
    writeIcmFile("business/ho\u{202E}urs/README.md", 'Just words.');

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe(['business/ho[hidden character]urs/README.md: '.hiddenNameSentence()]);
});

/**
 * Lay the pretend room's contract and its one note, then add a second note under $name.
 */
function layPretendRoomWithNote(string $name): void
{
    enablePretendRoom();
    layPretendRoomFolder('rooms/pretend/CONTEXT.md', 'rooms/pretend/notes/first-note.md');
    writeIcmFile("rooms/pretend/notes/{$name}", File::get(icmPath('rooms/pretend/notes/first-note.md')));
}

/**
 * How many pretend notes the live version holds.
 */
function pretendNotesLive(): int
{
    return collect(app(ActiveConfig::class)->version()->compiled['files'])->where('type', 'pretend_note')->count();
}

it('lets names pass that are only not English or not ASCII', function (string $name) {
    layPretendRoomWithNote($name);

    activateExampleFolder();

    expect(pretendNotesLive())->toBe(2);
})->with([
    'café composed (NFC)' => ["caf\u{00E9}.md"],
    'Cyrillic letters' => ["\u{0437}\u{0430}\u{043C}\u{0435}\u{0442}\u{043A}\u{0430}.md"],
    'CJK characters' => ["\u{30E1}\u{30E2}.md"],
    'an emoji with the variation selector' => ["sun\u{2600}\u{FE0F}.md"],
]);

it('lets café pass when the disk returns it decomposed (NFD)', function () {
    layPretendRoomWithNote("cafe\u{0301}.md");

    activateExampleFolder();

    expect(pretendNotesLive())->toBe(2);
});

it('refuses an emoji sequence joined with a zero-width joiner', function () {
    layPretendRoomWithNote("family\u{1F468}\u{200D}\u{1F469}.md");

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe(["rooms/pretend/notes/family\u{1F468}[hidden character]\u{1F469}.md: ".hiddenNameSentence()]);
});

it('marks a hidden character in a value the owner wrote in a contract', function () {
    enablePretendRoom();
    $contract = File::get(base_path('tests/Fixtures/pretend-room/rooms/pretend/CONTEXT.md'));
    writeIcmFile('rooms/pretend/CONTEXT.md', str_replace('owns: [rooms/pretend/notes/*.md]', 'owns: [rooms/pretend/notes/*.md, "/\\u202Ex.md"]', $contract));

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe(['rooms/pretend/CONTEXT.md: owns item 2 must be a path inside this folder, with no .. and no leading / (you wrote /[hidden character]x.md)']);
});

it('marks a hidden character in a connector slot', function () {
    enablePretendRoom();
    layPretendRoomFolder('rooms/pretend/CONTEXT.md');
    $connector = File::get(base_path('tests/Fixtures/pretend-room/connectors/pretend-feed.md'));
    writeIcmFile('connectors/pretend-feed.md', str_replace('slot: pretend_feed', 'slot: "pre\\u202Etend"', $connector));

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe(['connectors/pretend-feed.md: this file fills the pre[hidden character]tend slot, which no room has; the slots are: '.implode(', ', app(RoomRegistry::class)->slots())]);
});
