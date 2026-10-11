<?php

use App\Enums\VersionStatus;
use App\Icm\FolderSync;
use App\Models\IcmVersion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-10-10 19:32:00', 'UTC'));
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * The current text of the temporary folder's changelog.md.
 */
function changelogText(): string
{
    return File::get(icmPath('changelog.md'));
}

/**
 * Every entry heading in changelog.md, newest first.
 *
 * @return list<string>
 */
function changelogHeadings(): array
{
    preg_match_all('/^## .*$/m', changelogText(), $matches);

    return $matches[0];
}

it('creates changelog.md with a header and records the first sync as live', function () {
    expect(icmPath('changelog.md'))->not->toBeFile();

    $this->artisan('icm:sync')->assertExitCode(0);

    expect(File::lines(icmPath('changelog.md'))->first())->toBe('# Changelog')
        ->and(changelogHeadings())->toBe(['## 2026-10-10 14:32 · Live as v1 (8 files)']);
});

it('records an unchanged sync above the live entry', function () {
    $this->artisan('icm:sync')->assertExitCode(0);
    $this->artisan('icm:sync')->assertExitCode(0);

    expect(changelogHeadings())->toBe([
        '## 2026-10-10 14:32 · No changes',
        '## 2026-10-10 14:32 · Live as v1 (8 files)',
    ])->and(changelogText())->toContain("· No changes\n- Already active: v1\n");
});

it('puts a rejected sync on top and keeps one header', function () {
    $this->artisan('icm:sync')->assertExitCode(0);
    $this->artisan('icm:sync')->assertExitCode(0);

    breakFile('business/profile.md', fn (string $text) => str_replace("timezone: America/Chicago\n", '', $text));
    $this->artisan('icm:sync')->assertExitCode(1);

    expect(changelogHeadings())->toBe([
        '## 2026-10-10 14:32 · Rejected',
        '## 2026-10-10 14:32 · No changes',
        '## 2026-10-10 14:32 · Live as v1 (8 files)',
    ])->and(substr_count(changelogText(), '# Changelog'))->toBe(1);
});

it('lists a later-sprint file as not supported yet on the live entry', function () {
    writeIcmFile('agents/front-desk.md', "---\ntype: agent\nname: Front desk\n---\nAnswers texts.");

    $this->artisan('icm:sync')->assertExitCode(0);

    expect(changelogText())->toContain("· Live as v1 (8 files)\n- Not supported yet: agents/front-desk.md (type agent)\n");
});

it('writes in the time zone of the new profile when the sync goes live', function () {
    activateExampleFolder();

    breakFile('business/profile.md', fn (string $text) => str_replace('timezone: America/Chicago', 'timezone: America/New_York', $text));
    $this->artisan('icm:sync')->assertExitCode(0);

    expect(changelogHeadings()[0])->toBe('## 2026-10-10 15:32 · Live as v2 (8 files)');
});

it('writes a rejection in the live version time zone when profile.md is the broken file', function () {
    activateExampleFolder();

    breakFile('business/profile.md', fn (string $text) => str_replace("timezone: America/Chicago\n", '', $text));
    $this->artisan('icm:sync')->assertExitCode(1);

    expect(changelogHeadings()[0])->toBe('## 2026-10-10 14:32 · Rejected');
});

it('falls back to UTC and says so when profile.md is broken and nothing is live yet', function () {
    breakFile('business/profile.md', fn (string $text) => str_replace("timezone: America/Chicago\n", '', $text));
    $this->artisan('icm:sync')->assertExitCode(1);

    $note = '- Times are in UTC because business/profile.md could not be read and no version is live yet';
    $bullets = collect(explode("\n", changelogText()))->filter(fn (string $line) => str_starts_with($line, '- '))->values();

    expect(changelogHeadings())->toBe(['## 2026-10-10 19:32 · Rejected'])
        ->and($bullets->last())->toBe($note)
        ->and($bullets->all())->toContain('- business/profile.md: timezone is missing, and it is required', '- Nothing is live yet');
});

it('writes nothing when another sync holds the lock', function () {
    $this->artisan('icm:sync')->assertExitCode(0);
    $before = changelogText();

    $lock = Cache::lock('icm:sync', 120);
    expect($lock->get())->toBeTrue();

    $this->artisan('icm:sync')->assertExitCode(1);

    $lock->release();

    expect(changelogText())->toBe($before);
});

it('keeps text the owner wrote above the entries', function () {
    writeIcmFile('changelog.md', "# Changelog\n\nMy own note: ask Dana before changing prices.\n");

    $this->artisan('icm:sync')->assertExitCode(0);

    expect(changelogText())->toStartWith("# Changelog\n\nMy own note: ask Dana before changing prices.\n")
        ->and(changelogHeadings())->toBe(['## 2026-10-10 14:32 · Live as v1 (8 files)'])
        ->and(substr_count(changelogText(), '# Changelog'))->toBe(1);
});

it('keeps one bullet per error by cutting long values and collapsing line breaks', function () {
    $evil = 'x\n## 2099-01-01 00:00 · Live as v99 (1 files)\n'.str_repeat('a', 200);

    breakFile('business/profile.md', fn (string $text) => str_replace('timezone: America/Chicago', 'timezone: "'.$evil.'"', $text));
    $this->artisan('icm:sync')->assertExitCode(1);

    $bullets = collect(explode("\n", changelogText()))->filter(fn (string $line) => str_starts_with($line, '- business/profile.md'));

    preg_match('/\(you wrote (.*)\)$/', $bullets->first(), $echoed);

    expect(changelogHeadings())->toBe(['## 2026-10-10 19:32 · Rejected'])
        ->and($bullets)->toHaveCount(1)
        ->and(mb_strlen($echoed[1]))->toBe(80)
        ->and($echoed[1])->toEndWith('...');
});

it('leaves the live version untouched and never lets a rejected candidate be read', function () {
    $live = activateExampleFolder();

    breakFile('business/profile.md', fn (string $text) => str_replace("timezone: America/Chicago\n", '', $text));
    app(FolderSync::class)->run();

    expect(IcmVersion::where('status', VersionStatus::Active->value)->sole()->id)->toBe($live->id)
        ->and(IcmVersion::where('status', VersionStatus::Rejected->value)->sole()->compiled)->toBeNull();
});
