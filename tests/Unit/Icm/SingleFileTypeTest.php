<?php

use App\Enums\IcmFileKind;
use App\Icm\IcmFile;
use App\Icm\RoomChecks;
use App\Rooms\Room;
use App\Rooms\RoomFileType;
use App\Rooms\RoomRegistry;

/**
 * A loaded typed file for the single-file cases.
 *
 * @param  array<string, mixed>  $frontMatter
 */
function singleFileUnitFile(string $path, string $type, array $frontMatter = []): IcmFile
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

/**
 * A room file type with no rules that keeps one file when it has a label, and any number when the label is null.
 */
function singleFileUnitType(string $type, ?string $label): RoomFileType
{
    return new class($type, $label) implements RoomFileType
    {
        public function __construct(private readonly string $type, private readonly ?string $label) {}

        public function type(): string
        {
            return $this->type;
        }

        public function rules(IcmFile $file): array
        {
            return [];
        }

        public function checks(IcmFile $file): array
        {
            return [];
        }

        public function singleFileLabel(): ?string
        {
            return $this->label;
        }
    };
}

/**
 * A registry with two rooms: `solo`, whose solo_note keeps one file, and `many`, whose many_note keeps any number.
 */
function singleFileUnitRegistry(): RoomRegistry
{
    $registry = new RoomRegistry;
    $registry->add(new Room(key: 'solo', label: 'solo', fileTypes: [singleFileUnitType('solo_note', 'solo note')]));
    $registry->add(new Room(key: 'many', label: 'many', fileTypes: [singleFileUnitType('many_note', null)]));

    return $registry;
}

/**
 * A room contract owning the given paths.
 *
 * @param  list<string>  $owns
 */
function singleFileUnitContract(string $room, array $owns): IcmFile
{
    return singleFileUnitFile("rooms/{$room}/CONTEXT.md", 'room', [
        'owns' => $owns,
        'reads' => [],
        'writes_records' => [],
        'connector' => 'none',
    ]);
}

/**
 * @param  list<IcmFile>  $files  In path order, the way the Validator hands them over.
 * @return list<array{file: string, field: ?string, message: string}>
 */
function singleFileUnitErrors(array $files): array
{
    return (new RoomChecks(singleFileUnitRegistry()))->errors(collect($files));
}

it('refuses the second of two owned files of a single-file type, naming the first', function () {
    $errors = singleFileUnitErrors([
        singleFileUnitContract('solo', ['rooms/solo/a.md', 'rooms/solo/b.md']),
        singleFileUnitFile('rooms/solo/a.md', 'solo_note'),
        singleFileUnitFile('rooms/solo/b.md', 'solo_note'),
    ]);

    expect($errors)->toBe([[
        'file' => 'rooms/solo/b.md',
        'field' => 'type',
        'message' => 'this business already has a solo note in rooms/solo/a.md; keep one',
    ]]);
});

it('keeps the file the contract owns and refuses the other, with no unclaimed line on top', function () {
    $errors = singleFileUnitErrors([
        singleFileUnitContract('solo', ['rooms/solo/b.md']),
        singleFileUnitFile('rooms/solo/a.md', 'solo_note'),
        singleFileUnitFile('rooms/solo/b.md', 'solo_note'),
    ]);

    expect($errors)->toBe([[
        'file' => 'rooms/solo/a.md',
        'field' => 'type',
        'message' => 'this business already has a solo note in rooms/solo/b.md; keep one',
    ]]);
});

it('keeps the first in path order and refuses the rest when a pattern owns them all', function () {
    $errors = singleFileUnitErrors([
        singleFileUnitContract('solo', ['rooms/solo/*.md']),
        singleFileUnitFile('rooms/solo/a.md', 'solo_note'),
        singleFileUnitFile('rooms/solo/b.md', 'solo_note'),
        singleFileUnitFile('rooms/solo/c.md', 'solo_note'),
    ]);

    expect($errors)->toBe([
        ['file' => 'rooms/solo/b.md', 'field' => 'type', 'message' => 'this business already has a solo note in rooms/solo/a.md; keep one'],
        ['file' => 'rooms/solo/c.md', 'field' => 'type', 'message' => 'this business already has a solo note in rooms/solo/a.md; keep one'],
    ]);
});

it('accepts one file of a single-file type', function () {
    $errors = singleFileUnitErrors([
        singleFileUnitContract('solo', ['rooms/solo/a.md']),
        singleFileUnitFile('rooms/solo/a.md', 'solo_note'),
    ]);

    expect($errors)->toBe([]);
});

it('accepts any number of files of a type with no single-file label', function () {
    $errors = singleFileUnitErrors([
        singleFileUnitContract('many', ['rooms/many/a.md', 'rooms/many/b.md']),
        singleFileUnitFile('rooms/many/a.md', 'many_note'),
        singleFileUnitFile('rooms/many/b.md', 'many_note'),
    ]);

    expect($errors)->toBe([]);
});

it('gives a file outside the room folder only the placement line and does not count it', function () {
    $errors = singleFileUnitErrors([
        singleFileUnitFile('business/solo.md', 'solo_note'),
        singleFileUnitContract('solo', ['rooms/solo/a.md']),
        singleFileUnitFile('rooms/solo/a.md', 'solo_note'),
    ]);

    expect($errors)->toBe([[
        'file' => 'business/solo.md',
        'field' => null,
        'message' => 'this is a solo file, which belongs in rooms/solo/; move it there',
    ]]);
});
