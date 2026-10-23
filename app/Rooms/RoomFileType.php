<?php

namespace App\Rooms;

use App\Icm\IcmFile;

/**
 * A new kind of file a room defines in the business folder (D-04). The sync accepts the type, validates each file with
 * rules() and checks(), and compiles it like any other typed file.
 */
interface RoomFileType
{
    /**
     * The front matter type word, lower snake case, such as "pretend_note".
     */
    public function type(): string;

    /**
     * Laravel validator rules for this file's front matter, in the style of Validator::rulesFor().
     *
     * @return array<string, mixed>
     */
    public function rules(IcmFile $file): array;

    /**
     * Checks that rules cannot express, as errors in the Validator::error() shape.
     *
     * @return list<array{file: string, field: ?string, message: string}>
     */
    public function checks(IcmFile $file): array;
}
