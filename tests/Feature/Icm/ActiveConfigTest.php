<?php

use App\Icm\ActiveConfig;
use App\Icm\Exceptions\NoActiveVersion;
use Illuminate\Support\Facades\File;

it('throws NoActiveVersion from every accessor before a sync', function (string $method, array $arguments) {
    expect(fn () => app(ActiveConfig::class)->{$method}(...$arguments))
        ->toThrow(NoActiveVersion::class, 'No business folder version is live yet. Run php artisan icm:sync.');
})->with([
    'version' => ['version', []],
    'timezone' => ['timezone', []],
    'business' => ['business', []],
    'hours' => ['hours', []],
    'leadSources' => ['leadSources', []],
    'leadSource' => ['leadSource', ['angi']],
    'services' => ['services', []],
    'service' => ['service', ['ac-repair']],
    'voice' => ['voice', []],
    'sendingRules' => ['sendingRules', []],
    'files' => ['files', []],
    'file' => ['file', ['business/faq.md']],
]);

it('reads the business profile and time zone from the live version', function () {
    activateExampleFolder();
    $config = app(ActiveConfig::class);

    expect($config->business()['name'])->toBe('Example Heating & Air')
        ->and($config->business()['service_area'])->toBe(['Chicago', 'Evanston', 'Oak Park', 'Skokie'])
        ->and($config->business()['body'])->toContain('family-run')
        ->and($config->timezone())->toBe('America/Chicago');
});

it('returns lead source costs as integer cents and null for an unknown key', function () {
    activateExampleFolder();
    $config = app(ActiveConfig::class);

    expect($config->leadSource('angi'))->toBe(['key' => 'angi', 'name' => 'Angi', 'cost_cents_per_lead' => 4500])
        ->and($config->leadSource('yelp'))->toBeNull()
        ->and($config->leadSources())->toHaveCount(4);
});

it('returns a service with its price as an integer and its intake questions', function () {
    activateExampleFolder();
    $config = app(ActiveConfig::class);

    $acRepair = $config->service('ac-repair');

    expect($acRepair['price_cents'])->toBe(8900)
        ->and(array_column($acRepair['intake'], 'key'))->toBe(['symptom', 'system_age'])
        ->and($config->service('nope'))->toBeNull()
        ->and($config->services())->toHaveCount(4);
});

it('reads hours, voice and sending rules', function () {
    activateExampleFolder();
    $config = app(ActiveConfig::class);

    expect($config->hours()['weekly']['sun'])->toBe('closed')
        ->and($config->hours()['closures'])->toBe(['2026-11-26', '2026-12-25'])
        ->and($config->voice()['never_say'])->toHaveCount(3)
        ->and($config->sendingRules()['draft_for_owner'])->toContain('price');
});

it('lists the typed files with their types and the router files without one', function () {
    activateExampleFolder();
    $files = collect(app(ActiveConfig::class)->files());

    expect($files->whereNotNull('type')->pluck('path')->all())->toHaveCount(exampleFolderFileCount())
        ->and($files->firstWhere('path', 'business/profile.md')['type'])->toBe('business')
        ->and($files->whereNull('type')->pluck('path')->sort()->values()->all())->toBe([
            'CLAUDE.md',
            'START-HERE.md',
            'business/CONTEXT.md',
            'business/services/CONTEXT.md',
            'rules/CONTEXT.md',
        ]);
});

it('returns any compiled file whole without reading the disk', function () {
    activateExampleFolder();
    $routerText = File::get(icmPath('CLAUDE.md'));
    File::deleteDirectory(config('icm.path'));

    $config = app(ActiveConfig::class);

    expect($config->file('business/faq.md')['body'])->toContain('diagnostic fee')
        ->and($config->file('CLAUDE.md')['body'])->toBe($routerText)
        ->and($config->file('records/README.md'))->toBeNull();
});

it('sees the new version after a sync in the same process', function () {
    activateExampleFolder();
    expect(app(ActiveConfig::class)->business()['name'])->toBe('Example Heating & Air');

    breakFile('business/profile.md', fn (string $text) => str_replace('name: Example Heating & Air', 'name: Example Cooling & Air', $text));
    activateExampleFolder();

    expect(app(ActiveConfig::class)->business()['name'])->toBe('Example Cooling & Air');
});

it('shares one instance within a scope and builds a new one after the scope is forgotten', function () {
    $first = app(ActiveConfig::class);

    expect(app(ActiveConfig::class))->toBe($first);

    app()->forgetScopedInstances();

    expect(app(ActiveConfig::class))->not->toBe($first);
});
