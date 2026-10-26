<?php

use App\Enums\IcmFileKind;
use App\Icm\ConnectorChecks;
use App\Icm\IcmFile;
use App\Icm\RoomChecks;
use App\Rooms\RoomRegistry;

/**
 * This branch is reached in the running app only while no room offers a connector slot, which stops being true once the
 * first room with a slot ships, so this unit test keeps the sentence covered with an empty registry the test builds
 * itself (05 D-23). It replaces the feature case that used to prove it in ConnectorFileTest.
 */

/**
 * A connector file as the Loader would hand it to ConnectorChecks, built by hand so no folder is read.
 *
 * @param  array<string, mixed>  $frontMatter
 */
function connectorChecksUnitFile(string $path, string $slot, array $frontMatter = []): IcmFile
{
    return new IcmFile(
        path: $path,
        kind: IcmFileKind::Typed,
        type: 'connector',
        frontMatter: ['type' => 'connector', 'slot' => $slot, 'owns' => [], 'reads' => [], ...$frontMatter],
        body: 'Words.',
        raw: '',
    );
}

it('says the engine has no connector slots yet when no room offers one', function () {
    $registry = new RoomRegistry;

    $errors = (new ConnectorChecks($registry, new RoomChecks($registry)))->folderErrors(collect([
        connectorChecksUnitFile('connectors/x.md', 'nowhere'),
    ]));

    expect($errors)->toBe([[
        'file' => 'connectors/x.md',
        'field' => 'slot',
        'message' => 'this file fills the nowhere slot, which no room has; this engine has no connector slots yet',
    ]]);
});
