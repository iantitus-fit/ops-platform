<?php

use App\Enums\IcmFileKind;
use App\Icm\Loader;

it('marks a file with malformed front matter as unreadable and names the file line', function () {
    writeIcmFile('business/broken.md', "---\ntype: knowledge\nlist: [oops\n---\nBody");

    $broken = collect(app(Loader::class)->load(icmPath())->files)
        ->firstWhere('path', 'business/broken.md');

    expect($broken->kind)->toBe(IcmFileKind::Unrecognized)
        ->and($broken->problem)->toStartWith('unreadable:')
        ->and($broken->problem)->toContain('at line 3');
});

it('skips symlinked files so nothing outside the folder is read', function () {
    $outside = sys_get_temp_dir().'/icm-outside-'.uniqid().'.md';
    file_put_contents($outside, "---\ntype: knowledge\n---\nSecret from outside the folder");

    try {
        symlink($outside, icmPath('business/linked.md'));
        symlink($outside, icmPath('business/CLAUDE.md'));

        $paths = collect(app(Loader::class)->load(icmPath())->files)->pluck('path');

        expect($paths)->not->toContain('business/linked.md')
            ->and($paths)->not->toContain('business/CLAUDE.md')
            ->and($paths)->toContain('business/profile.md');
    } finally {
        @unlink($outside);
    }
});
