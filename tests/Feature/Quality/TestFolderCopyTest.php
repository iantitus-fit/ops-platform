<?php

use Illuminate\Support\Facades\File;

it('starts each test with only the records files git tracks, whatever a local run left in the example folder', function () {
    $entries = collect(File::allFiles(icmPath('records'), true))
        ->map(fn (SplFileInfo $file) => $file->getRelativePathname())
        ->merge(collect(File::directories(icmPath('records')))->map(fn (string $directory) => basename($directory).'/'))
        ->sort()
        ->values()
        ->all();

    expect($entries)->toBe(['CLAUDE.md', 'README.md']);
});
