<?php

use Illuminate\Support\Facades\File;

it('lists all four rule globs in the Boost rules index', function () {
    $index = File::get(base_path('.ai/rules/index.md'));

    expect($index)->toContain('app/**')
        ->toContain('database/migrations/**')
        ->toContain('database/factories/**')
        ->toContain('tests/**');
});

it('records both app rules in one area file', function () {
    $rules = File::get(base_path('.ai/rules/app.md'));

    expect($rules)->toContain('## Money is integer cents')
        ->toContain('## Business configuration is read only through ActiveConfig')
        ->toContain('App\Icm\ActiveConfig');
});

it('records the money rule for migrations, factories and tests', function (string $file) {
    expect(File::get(base_path(".ai/rules/{$file}")))->toContain('integer cents');
})->with(['migrations.md', 'factories.md', 'tests.md']);
