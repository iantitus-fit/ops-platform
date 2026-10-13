<?php

use App\Enums\SyncOutcome;
use App\Enums\VersionStatus;
use App\Models\IcmVersion;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

it('activates the example folder as v1', function () {
    expect(Artisan::call('icm:sync'))->toBe(0);

    expect(IcmVersion::count())->toBe(1);

    $version = IcmVersion::first();

    $files = exampleFolderFileCount();

    expect(Artisan::output())->toContain("Live as {$version->label()} ({$files} files)")
        ->and($version->status)->toBe(VersionStatus::Active)
        ->and($version->file_count)->toBe(exampleFolderFileCount())
        ->and($version->fingerprint)->toHaveLength(64)
        ->and($version->activated_at)->not->toBeNull();
});

it('shows the active version, its file count and its Chicago go-live time', function () {
    $this->freezeTime();
    $this->artisan('icm:sync')->assertExitCode(0);

    $liveSince = now()->setTimezone('America/Chicago')->format('Y-m-d H:i');
    $label = IcmVersion::sole()->label();

    $this->artisan('icm:status')
        ->expectsOutputToContain("Active version: {$label}")
        ->expectsOutputToContain('Files: '.exampleFolderFileCount())
        ->expectsOutputToContain("Live since: {$liveSince} (America/Chicago)")
        ->assertExitCode(0);
});

it('reports that no version is live yet and exits 1', function () {
    $this->artisan('icm:status')
        ->expectsOutputToContain('No version is live yet. Run php artisan icm:sync.')
        ->assertExitCode(1);

    expect(IcmVersion::count())->toBe(0);
});

it('does not count README, CONTEXT or START-HERE files', function () {
    writeIcmFile('business/extras/README.md', 'Notes for people.');
    writeIcmFile('business/extras/CONTEXT.md', 'Points at other files.');
    writeIcmFile('agents/START-HERE.md', 'Start here.');

    expect(activateExampleFolder()->file_count)->toBe(exampleFolderFileCount());
});

it('skips later-sprint file types with a notice and still activates', function () {
    $notice = 'automations/follow-up.md: type automation is not supported yet, so this file was skipped';
    writeIcmFile('automations/follow-up.md', "---\ntype: automation\nname: Follow-up\n---\nChecks in after a quote.");

    expect(Artisan::call('icm:sync'))->toBe(0);

    $version = IcmVersion::first();

    $files = exampleFolderFileCount();

    expect(Artisan::output())->toContain($notice)->toContain("Live as {$version->label()} ({$files} files)")
        ->and($version->notices)->toContain($notice)
        ->and($version->compiled['files'])->not->toHaveKey('automations/follow-up.md');
});

it('treats a second sync with no changes as a no-op', function () {
    $this->freezeSecond();
    $this->artisan('icm:sync')->assertExitCode(0);

    $label = IcmVersion::sole()->label();

    $this->travel(5)->minutes();

    $this->artisan('icm:sync')
        ->expectsOutputToContain("Already active: {$label}, no changes")
        ->assertExitCode(0);

    $version = IcmVersion::first();

    expect(IcmVersion::count())->toBe(1)
        ->and($version->last_result)->toBe(SyncOutcome::Unchanged)
        ->and($version->last_checked_at->equalTo(now()))->toBeTrue()
        ->and($version->activated_at->equalTo(now()->subMinutes(5)))->toBeTrue();
});

it('goes live as v2 when a router file changes and supersedes v1', function () {
    $first = activateExampleFolder();

    breakFile('CLAUDE.md', fn (string $text) => $text."\nAlso read faq.md.\n");

    expect(Artisan::call('icm:sync'))->toBe(0);

    $second = IcmVersion::query()->latest('id')->first();

    $files = exampleFolderFileCount();

    expect(Artisan::output())->toContain("Live as {$second->label()} ({$files} files)")
        ->and($second->id)->not->toBe($first->id)
        ->and($first->fresh()->status)->toBe(VersionStatus::Superseded)
        ->and(IcmVersion::where('status', VersionStatus::Active->value)->count())->toBe(1);
});

it('does not mint a version when only a README or a records file changes', function () {
    $first = activateExampleFolder();

    breakFile('agents/README.md', fn (string $text) => $text."\nMore words.\n");
    writeIcmFile('records/customers/dana-whitfield-1.md', '# Dana Whitfield');
    writeIcmFile('changelog.md', '## Nothing yet');

    $this->artisan('icm:sync')
        ->expectsOutputToContain("Already active: {$first->label()}, no changes")
        ->assertExitCode(0);

    expect(IcmVersion::count())->toBe(1);
});

it('registers no icm command other than sync and status', function () {
    $icmCommands = collect(array_keys(Artisan::all()))
        ->filter(fn (string $name) => str_starts_with($name, 'icm:'))
        ->sort()
        ->values()
        ->all();

    expect($icmCommands)->toBe(['icm:status', 'icm:sync']);
});

it('refuses to run while another sync holds the lock and changes nothing', function () {
    $lock = Cache::lock('icm:sync', 120);
    expect($lock->get())->toBeTrue();

    $this->artisan('icm:sync')
        ->expectsOutputToContain('A sync is already running. Try again in a moment.')
        ->assertExitCode(1);

    expect(IcmVersion::count())->toBe(0);

    $lock->release();
});

it('rejects a broken service file, keeps the old version live and records why in changelog.md', function () {
    $first = activateExampleFolder();
    $line = 'business/services/ac-repair.md: price_policy must be one of none, starting_at, fixed (you wrote maybe)';

    breakFile('business/services/ac-repair.md', fn (string $text) => str_replace('price_policy: starting_at', 'price_policy: maybe', $text));

    $this->artisan('icm:sync')
        ->expectsOutputToContain("Rejected. {$first->label()} stays live.")
        ->expectsOutputToContain($line)
        ->assertExitCode(1);

    expect(IcmVersion::where('status', VersionStatus::Active->value)->pluck('id')->all())->toBe([$first->id]);

    $rejected = IcmVersion::where('status', VersionStatus::Rejected->value)->sole();

    expect($rejected->compiled)->toBeNull()
        ->and($rejected->errors)->toContain($line);

    $changelog = file(icmPath('changelog.md'), FILE_IGNORE_NEW_LINES);
    $headingIndex = collect($changelog)->search(fn (string $text) => str_starts_with($text, '## '));

    expect($changelog[0])->toBe('# Changelog')
        ->and($changelog[$headingIndex])->toMatch('/^## \d{4}-\d{2}-\d{2} \d{2}:\d{2} · Rejected$/')
        ->and($changelog[$headingIndex + 1])->toBe("- {$line}");
});
