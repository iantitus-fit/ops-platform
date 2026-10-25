<?php

use App\Enums\IcmFileKind;
use App\Icm\IcmFile;
use App\Icm\RoomChecks;
use App\Rooms\Room;
use App\Rooms\RoomRegistry;

/**
 * A loaded file for the never-left-out cases. The router kind is for a file the Loader would treat as a folder guide.
 *
 * @param  array<string, mixed>  $frontMatter
 */
function neverLeftOutFile(string $path, string $type, array $frontMatter = [], IcmFileKind $kind = IcmFileKind::Typed): IcmFile
{
    return new IcmFile(
        path: $path,
        kind: $kind,
        type: $kind === IcmFileKind::Typed ? $type : null,
        frontMatter: $kind === IcmFileKind::Typed ? ['type' => $type, ...$frontMatter] : [],
        body: 'Words.',
        raw: '',
    );
}

/**
 * A registry holding the room `keeper`, which handles the knowledge file type, marked or not as one a business can
 * never leave out.
 */
function neverLeftOutRegistry(?string $because): RoomRegistry
{
    $registry = new RoomRegistry;
    $registry->add($because === null
        ? new Room(key: 'keeper', label: 'keeper', handles: ['knowledge'])
        : new Room(key: 'keeper', label: 'keeper', handles: ['knowledge'], neverLeftOutBecause: $because));

    return $registry;
}

/**
 * The contract of the room `keeper`, owning the FAQ.
 *
 * @param  array<string, mixed>  $frontMatter
 */
function keeperContract(array $frontMatter = []): IcmFile
{
    return neverLeftOutFile('rooms/keeper/CONTEXT.md', 'room', [
        'owns' => ['business/faq.md'],
        'reads' => [],
        'writes_records' => [],
        'connector' => 'none',
        ...$frontMatter,
    ]);
}

it('gives one line naming the room and why when a room that can never be left out is absent', function () {
    $errors = (new RoomChecks(neverLeftOutRegistry('it owns the FAQ the front desk reads')))->errors(collect([
        neverLeftOutFile('business/faq.md', 'knowledge'),
    ]));

    expect($errors)->toBe([[
        'file' => 'rooms/keeper/CONTEXT.md',
        'field' => null,
        'message' => 'the keeper room can never be left out, because it owns the FAQ the front desk reads; add rooms/keeper/CONTEXT.md',
    ]]);
});

it('keeps the per-file line for a room that may be left out', function () {
    $errors = (new RoomChecks(neverLeftOutRegistry(null)))->errors(collect([
        neverLeftOutFile('business/faq.md', 'knowledge'),
    ]));

    expect($errors)->toBe([[
        'file' => 'business/faq.md',
        'field' => null,
        'message' => "this file belongs to the keeper room, which this business doesn't use; add rooms/keeper/ or remove the file",
    ]]);
});

it('gives only the missing-contract line when the room folder holds files but no contract', function () {
    $errors = (new RoomChecks(neverLeftOutRegistry('it owns the FAQ the front desk reads')))->errors(collect([
        neverLeftOutFile('rooms/keeper/notes/CONTEXT.md', '', kind: IcmFileKind::Router),
        neverLeftOutFile('business/faq.md', 'knowledge'),
    ]));

    expect($errors)->toBe([[
        'file' => 'rooms/keeper/CONTEXT.md',
        'field' => null,
        'message' => "the keeper room's folder has files but no CONTEXT.md; add the room's contract with type: room",
    ]]);
});

it('gives nothing when the room that can never be left out is here and owns its file', function () {
    $errors = (new RoomChecks(neverLeftOutRegistry('it owns the FAQ the front desk reads')))->errors(collect([
        keeperContract(),
        neverLeftOutFile('business/faq.md', 'knowledge'),
    ]));

    expect($errors)->toBe([]);
});

it('does not call a room absent when its contract is here but owns is not a list', function () {
    $errors = (new RoomChecks(neverLeftOutRegistry('it owns the FAQ the front desk reads')))->errors(collect([
        keeperContract(['owns' => 'business/faq.md']),
        neverLeftOutFile('business/faq.md', 'knowledge'),
    ]));

    expect($errors)->toBe([[
        'file' => 'business/faq.md',
        'field' => null,
        'message' => 'no room lists this knowledge file under owns; the keeper room handles it, so add it to rooms/keeper/CONTEXT.md',
    ]]);
});

it('gives one line for each absent room that can never be left out, in key order, and none for files', function () {
    $registry = neverLeftOutRegistry('it owns the FAQ the front desk reads');
    $registry->add(new Room(key: 'ledger', label: 'ledger', handles: ['lead_sources'], neverLeftOutBecause: 'it owns the lead sources file the sync requires'));

    $errors = (new RoomChecks($registry))->errors(collect([
        neverLeftOutFile('business/faq.md', 'knowledge'),
        neverLeftOutFile('business/lead-sources.md', 'lead_sources'),
    ]));

    expect($errors)->toBe([
        [
            'file' => 'rooms/keeper/CONTEXT.md',
            'field' => null,
            'message' => 'the keeper room can never be left out, because it owns the FAQ the front desk reads; add rooms/keeper/CONTEXT.md',
        ],
        [
            'file' => 'rooms/ledger/CONTEXT.md',
            'field' => null,
            'message' => 'the ledger room can never be left out, because it owns the lead sources file the sync requires; add rooms/ledger/CONTEXT.md',
        ],
    ]);
});

it('gives the same lines in the same order when the same files are checked twice', function () {
    $registry = neverLeftOutRegistry('it owns the FAQ the front desk reads');
    $registry->add(new Room(key: 'ledger', label: 'ledger', handles: ['lead_sources'], neverLeftOutBecause: 'it owns the lead sources file the sync requires'));
    $files = collect([
        neverLeftOutFile('business/lead-sources.md', 'lead_sources'),
        neverLeftOutFile('business/faq.md', 'knowledge'),
    ]);

    $checks = new RoomChecks($registry);

    expect($checks->errors($files))->toBe($checks->errors($files));
});
