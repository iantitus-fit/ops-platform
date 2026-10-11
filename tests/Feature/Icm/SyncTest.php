<?php

use App\Enums\VersionStatus;
use App\Models\IcmVersion;

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
