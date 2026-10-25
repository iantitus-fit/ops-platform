<?php

use App\Enums\VersionStatus;
use App\Icm\ActiveConfig;
use App\Models\IcmVersion;
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
