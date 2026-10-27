<?php

use App\Enums\SyncOutcome;
use App\Icm\ActiveConfig;
use App\Icm\Changelog;
use App\Icm\ConnectorChecks;
use App\Icm\FolderSync;
use App\Icm\Loader;
use App\Icm\RoomChecks;
use App\Icm\SyncResult;
use App\Icm\Validator;
use App\Rooms\Room;
use App\Rooms\RoomRegistry;
use Illuminate\Support\Facades\File;

/**
 * These cases sync the example folder through a FolderSync the test builds itself, with a RoomRegistry holding only
 * test rooms, so they reach branches the real rooms make unreachable once the front desk handles every hard-coded
 * type (06 D-14, 05 D-23). The case here replaces RoomOwnershipTest's "keeps the previous version live when the folder
 * is refused for its owners", which needed a test-only room to be the only one that handles the triage type.
 */

/**
 * Run one whole sync with a FolderSync wired by hand to $registry, so the sync sees only the rooms the test registered.
 */
function handBuiltRegistrySync(RoomRegistry $registry): SyncResult
{
    $roomChecks = new RoomChecks($registry);

    return (new FolderSync(
        new Loader($registry),
        new Validator($roomChecks, $registry, new ConnectorChecks($registry, $roomChecks)),
        app(Changelog::class),
        $registry,
    ))->run();
}

/**
 * A test room's contract: a room that owns what it is given, reads nothing, writes no records and has no connector.
 */
function handBuiltRoomContract(string $owns): string
{
    return "---\ntype: room\nowns: {$owns}\nreads: none\nwrites_records: none\nconnector: none\n---\n\n# rulebook room\n\nA room that exists only in tests.\n";
}

it('keeps the previous version live when an absent room\'s file is refused, with a registry the test builds itself', function () {
    $registry = new RoomRegistry;
    $registry->add(new Room(key: 'rulebook', label: 'rulebook', handles: ['triage']));

    File::deleteDirectory(icmPath('rooms'));
    File::deleteDirectory(icmPath('connectors'));
    writeIcmFile('rooms/rulebook/CONTEXT.md', handBuiltRoomContract('[rules/triage.md]'));

    $first = handBuiltRegistrySync($registry);

    expect($first->outcome)->toBe(SyncOutcome::Activated);

    File::deleteDirectory(icmPath('rooms/rulebook'));

    $second = handBuiltRegistrySync($registry);

    expect($second->outcome)->toBe(SyncOutcome::Rejected)
        ->and(rejectedErrors())->toBe(["rules/triage.md: this file belongs to the rulebook room, which this business doesn't use; add rooms/rulebook/ or remove the file"])
        ->and(app(ActiveConfig::class)->versionId())->toBe($first->version->id);
});
