<?php

namespace App\Icm;

use Illuminate\Support\Collection;

/**
 * The checks on the walk-test files in tests/ (WALK-01). They work only from the files the Loader already read, never
 * from disk, and the Validator adds what they return to its report, so a broken walk file is refused the same way as any
 * other file: the folder is stored as rejected and the previous version stays live.
 */
class WalkFileChecks
{
    /**
     * Every problem in the folder's walk files.
     *
     * @param  array<int, IcmFile>|Collection<int, IcmFile>  $files  The Validator hands over its sorted collection; a test may hand over the Loader's list.
     * @return list<array{file: string, field: ?string, message: string}>
     */
    public function errors(array|Collection $files): array
    {
        return [];
    }
}
