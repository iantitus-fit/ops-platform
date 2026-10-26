<?php

use App\Enums\VersionStatus;
use App\Icm\ActiveConfig;
use App\Models\IcmVersion;
use Illuminate\Support\Facades\File;

/**
 * The refusal sentence for a file that is not valid UTF-8 (D-33). The wording is a placeholder until the wording pass.
 */
function notUtf8Sentence(): string
{
    return 'this file is not saved as plain text (UTF-8); open it and save it as UTF-8';
}

/**
 * Folder files of each kind the Loader reads: a typed file, a router and a room file. Each passes every other check, so
 * the bytes alone decide.
 */
dataset('example files saved by an older Windows editor', [
    'a knowledge file' => 'business/faq.md',
    'a router' => 'CLAUDE.md',
    'the money notes, with no number in them' => 'rooms/money/notes.md',
]);

it('refuses a file that is not valid UTF-8 in one plain line naming it, and keeps the previous version live', function (string $path) {
    $live = activateExampleFolder();

    // 0x96 is how Windows-1252 saves an en dash; on its own it is not valid UTF-8.
    File::append(icmPath($path), "\nOpen Monday \x96 Friday.\n");

    $line = "{$path}: ".notUtf8Sentence();

    $this->artisan('icm:sync')
        ->expectsOutputToContain($line)
        ->assertExitCode(1);

    expect(rejectedErrors())->toBe([$line])
        ->and(app(ActiveConfig::class)->versionId())->toBe($live->id)
        ->and(IcmVersion::where('status', VersionStatus::Active->value)->count())->toBe(1);
})->with('example files saved by an older Windows editor');
