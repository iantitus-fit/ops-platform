<?php

use Illuminate\Support\Facades\File;

/**
 * IN-05: the example business's router (CLAUDE.md) only points, and a router that skips a folder leaves an owner or an AI
 * reading the folder with no way to find what lives there. It names every top-level folder the example holds, including
 * rooms/ and connectors/, which Phase 5 filled with typed files.
 */
it('names every top-level folder of the example business in its router', function () {
    $example = base_path('examples/example-hvac');
    $router = File::get("{$example}/CLAUDE.md");

    $folders = collect(File::directories($example))
        ->map(fn (string $path): string => basename($path))
        ->sort()
        ->values();

    expect($folders->all())->toContain('rooms', 'connectors');

    $unnamed = $folders->reject(fn (string $folder): bool => str_contains($router, "`{$folder}/"))->values();

    expect($unnamed->all())->toBe([]);
});

it('points to the rooms and connectors folders in plain words', function () {
    $router = File::get(base_path('examples/example-hvac/CLAUDE.md'));

    expect($router)->toContain('`rooms/`')
        ->and($router)->toContain('`connectors/`');
});
