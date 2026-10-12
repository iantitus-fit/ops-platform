<?php

use App\Enums\SyncOutcome;
use App\Icm\FolderSync;
use App\Models\IcmVersion;

it('ignores a broken file under records and a root changelog.md', function () {
    $cleanFingerprint = activateExampleFolder()->fingerprint;

    writeIcmFile('records/customers/x.md', "---\ntype: [oops\n---\ngarbage");
    writeIcmFile('changelog.md', "---\ntype: hours\nweekly: nonsense\n---\ngarbage");

    $result = app(FolderSync::class)->run();

    expect($result->outcome)->toBe(SyncOutcome::Unchanged)
        ->and($result->version->fingerprint)->toBe($cleanFingerprint)
        ->and(IcmVersion::count())->toBe(1);
});

it('activates a folder that starts out with a broken records file and a changelog', function () {
    writeIcmFile('records/customers/x.md', "---\ntype: [oops\n---\ngarbage");
    writeIcmFile('changelog.md', "---\ntype: hours\n---\ngarbage");

    $version = activateExampleFolder();

    expect($version->file_count)->toBe(exampleFolderFileCount())
        ->and($version->compiled['files'])->not->toHaveKeys(['records/customers/x.md', 'changelog.md']);
});

it('still reads a typed file inside a nested business/records folder', function () {
    writeIcmFile('business/records/notes.md', "---\ntype: knowledge\n---\nOld job notes.");

    $version = activateExampleFolder();

    expect($version->file_count)->toBe(exampleFolderFileCount() + 1)
        ->and($version->compiled['files'])->toHaveKey('business/records/notes.md');
});
