<?php

namespace App\Rooms\Money;

use App\Icm\IcmFile;
use App\Rooms\RoomFileType;

/**
 * The money room's one new kind of file: the money notes, rooms/money/notes.md. They are the owner's plain-words notes on
 * how the business gets paid today, written for whoever sets up the money work later (backlog 999.1). Nothing reads them
 * to act in v2.0. Its front matter is only its type, so there are no field rules.
 */
class MoneyNotesType implements RoomFileType
{
    public function type(): string
    {
        return 'money_notes';
    }

    public function rules(IcmFile $file): array
    {
        return [];
    }

    /**
     * The one check: the file may not hold what looks like a card, bank routing, Social Security or tax ID number
     * (05 D-16, D-22, D-26). The whole file as written is scanned, front matter included. The message is a fixed
     * sentence and never quotes what was found, so a number can never reach a stored version or changelog.md.
     */
    public function checks(IcmFile $file): array
    {
        if (! (new AccountNumberScan)->findsAccountNumber($file->raw)) {
            return [];
        }

        return [[
            'file' => $file->path,
            'field' => null,
            'message' => 'this file has what looks like a card, bank routing, Social Security or tax ID number; account, card and ID numbers never go in the folder',
        ]];
    }

    /**
     * A business keeps one notes file (05 D-15, D-27). The check in App\Icm\RoomChecks refuses every other file of this
     * type in rooms/money/, wherever the room's contract owns points, so nothing else in this room enforces it.
     */
    public function singleFileLabel(): ?string
    {
        return 'money notes file';
    }

    /**
     * Where the one notes file lives (05 D-29); the SPEC file types table gives the same path.
     */
    public function singleFileDefaultPath(): ?string
    {
        return 'rooms/money/notes.md';
    }
}
