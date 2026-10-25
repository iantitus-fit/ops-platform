<?php

use App\Icm\ActiveConfig;
use Illuminate\Support\Facades\File;

/**
 * D-02 through icm:sync on the real example folder: company knowledge and marketing are the two rooms a business can
 * never leave out, because each owns a file the sync requires. Leaving one out is one plain line naming the room and why,
 * never a line for each file it owns, and the version that was live stays live. The unit cases for the mechanism live in
 * tests/Unit/Icm/NeverLeftOutTest.php; these prove it is switched on for the two real rooms.
 */
const COMPANY_KNOWLEDGE_LEFT_OUT = 'rooms/company_knowledge/CONTEXT.md: the company knowledge room can never be left out, because it owns the profile, hours and voice files the sync requires; add rooms/company_knowledge/CONTEXT.md';
const MARKETING_LEFT_OUT = 'rooms/marketing/CONTEXT.md: the marketing room can never be left out, because it owns the lead sources file the sync requires; add rooms/marketing/CONTEXT.md';

/**
 * Sync the example folder as it stands and return the lines the sync refused it with.
 *
 * @return list<string>
 */
function neverLeftOutRefusal(): array
{
    test()->artisan('icm:sync')->assertExitCode(1);

    return rejectedErrors();
}

it('refuses a folder with no company knowledge room in one line and keeps the previous version live', function () {
    $live = activateExampleFolder();
    File::deleteDirectory(icmPath('rooms/company_knowledge'));

    expect(neverLeftOutRefusal())->toBe([COMPANY_KNOWLEDGE_LEFT_OUT])
        ->and(app(ActiveConfig::class)->versionId())->toBe($live->id);
});

it('refuses a folder with no marketing room in one line and keeps the previous version live', function () {
    $live = activateExampleFolder();
    File::deleteDirectory(icmPath('rooms/marketing'));

    expect(neverLeftOutRefusal())->toBe([MARKETING_LEFT_OUT])
        ->and(app(ActiveConfig::class)->versionId())->toBe($live->id);
});

it('gives both lines, company knowledge first, when both rooms are left out', function () {
    $live = activateExampleFolder();
    File::deleteDirectory(icmPath('rooms/company_knowledge'));
    File::deleteDirectory(icmPath('rooms/marketing'));

    expect(neverLeftOutRefusal())->toBe([COMPANY_KNOWLEDGE_LEFT_OUT, MARKETING_LEFT_OUT])
        ->and(app(ActiveConfig::class)->versionId())->toBe($live->id);
});

it('never tells the owner to remove a file, and gives the same lines when the same folder is synced twice', function () {
    activateExampleFolder();
    File::deleteDirectory(icmPath('rooms/company_knowledge'));
    File::deleteDirectory(icmPath('rooms/marketing'));

    $first = neverLeftOutRefusal();
    $second = neverLeftOutRefusal();

    expect($second)->toBe($first);

    foreach ($first as $line) {
        expect($line)->not->toContain('remove the file')->not->toContain("doesn't use");
    }
});

it('gives only the missing-contract line when the folder has a stray file but no contract', function () {
    activateExampleFolder();
    File::delete(icmPath('rooms/company_knowledge/CONTEXT.md'));
    writeIcmFile('rooms/company_knowledge/notes.md', "---\ntype: knowledge\n---\nA stray note.");

    expect(neverLeftOutRefusal())->toBe(["rooms/company_knowledge/CONTEXT.md: the company knowledge room's folder has files but no CONTEXT.md; add the room's contract with type: room"]);
});
