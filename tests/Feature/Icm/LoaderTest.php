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
