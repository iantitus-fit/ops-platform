<?php

namespace App\Rooms;

use App\Icm\IcmFile;

/**
 * A new kind of file a room defines in the business folder (D-04). The sync accepts the type, validates each file with
 * rules() and checks(), and compiles it like any other typed file. A type may also say a business keeps only one file
 * of it (singleFileLabel()), and the sync then refuses a second one wherever the room's contract owns points (05 D-27). Such
 * a type also names the path the one file normally has (singleFileDefaultPath(), 05 D-29).
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

    /**
     * The path the one file of this type normally has (such as "rooms/money/notes.md"), or null when singleFileLabel() is
     * null. It is the same path the SPEC file types table gives the type, the way Validator::SINGLETONS names the path of
     * each required file. When a business holds this file and another of the type in the room's folder, the default is
     * the one the sync keeps and the other gets the refusal line, so the line always names the file the owner added
     * (05 D-29, refining D-27). When the default is not there, the sync picks the file kept as before. The sync still
     * refuses the whole version either way.
     */
    public function singleFileDefaultPath(): ?string;
}
