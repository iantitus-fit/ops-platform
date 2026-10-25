<?php

namespace App\Rooms;

use App\Icm\IcmFile;

/**
 * A new kind of file a room defines in the business folder (D-04). The sync accepts the type, validates each file with
 * rules() and checks(), and compiles it like any other typed file. A type may also say a business keeps only one file
 * of it (singleFileLabel()), and the sync then refuses a second one wherever the room's contract owns points (05 D-27).
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

    /**
     * The plain name of the one file of this type a business keeps, used mid-sentence (such as "content brief"), or null
     * when a business may keep any number. When it is not null the sync keeps one file of the type in the room's folder
     * and refuses every other, wherever the room's contract owns points, so the rule holds after the owner edits the
     * contract (05 D-27).
     */
    public function singleFileLabel(): ?string;
}
