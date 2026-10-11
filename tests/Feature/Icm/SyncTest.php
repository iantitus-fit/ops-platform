<?php

use App\Enums\SyncOutcome;
use App\Enums\VersionStatus;
use App\Models\IcmVersion;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

it('activates the example folder as v1 with eight files', function () {
    $this->artisan('icm:sync')
        ->expectsOutputToContain('Live as v1 (8 files)')
        ->assertExitCode(0);

    expect(IcmVersion::count())->toBe(1);

    $version = IcmVersion::first();

    expect($version->status)->toBe(VersionStatus::Active)
        ->and($version->file_count)->toBe(8)
        ->and($version->fingerprint)->toHaveLength(64)
        ->and($version->activated_at)->not->toBeNull();
});

it('shows the active version, its file count and its Chicago go-live time', function () {
    $this->freezeTime();
    $this->artisan('icm:sync')->assertExitCode(0);

    $liveSince = now()->setTimezone('America/Chicago')->format('Y-m-d H:i');

    $this->artisan('icm:status')
        ->expectsOutputToContain('Active version: v1')
        ->expectsOutputToContain('Files: 8')
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

    expect(activateExampleFolder()->file_count)->toBe(8);
});

it('skips later-sprint file types with a notice and still activates', function () {
    $notice = 'agents/front-desk.md: type agent is not supported yet, so this file was skipped';
    writeIcmFile('agents/front-desk.md', "---\ntype: agent\nname: Front desk\n---\nAnswers texts.");

    $this->artisan('icm:sync')
        ->expectsOutputToContain($notice)
        ->expectsOutputToContain('Live as v1 (8 files)')
        ->assertExitCode(0);

    $version = IcmVersion::first();

    expect($version->notices)->toContain($notice)
        ->and($version->compiled['files'])->not->toHaveKey('agents/front-desk.md');
});

it('treats a second sync with no changes as a no-op', function () {
    $this->freezeSecond();
    $this->artisan('icm:sync')->assertExitCode(0);

    $this->travel(5)->minutes();

    $this->artisan('icm:sync')
        ->expectsOutputToContain('Already active: v1, no changes')
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

    $this->artisan('icm:sync')
        ->expectsOutputToContain('Live as v2 (8 files)')
        ->assertExitCode(0);

    expect($first->fresh()->status)->toBe(VersionStatus::Superseded)
        ->and(IcmVersion::where('status', VersionStatus::Active->value)->count())->toBe(1);
});

it('does not mint a version when only a README or a records file changes', function () {
    activateExampleFolder();

    breakFile('agents/README.md', fn (string $text) => $text."\nMore words.\n");
    writeIcmFile('records/customers/dana-whitfield-1.md', '# Dana Whitfield');
    writeIcmFile('changelog.md', '## Nothing yet');

    $this->artisan('icm:sync')
        ->expectsOutputToContain('Already active: v1, no changes')
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
        ->expectsOutputToContain('Rejected. v1 stays live.')
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
