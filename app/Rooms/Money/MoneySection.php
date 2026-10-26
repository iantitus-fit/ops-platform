<?php

namespace App\Rooms\Money;

use App\Icm\ActiveConfig;
use App\Rooms\RoomSection;

/**
 * The money room's dashboard section: whether the business has its payment notes. While the notes file is absent the
 * section has no lines of its own and the platform draws the room's empty line, which names the file to add (05 D-17,
 * D-18). The wording is a placeholder until the wording pass.
 */
class MoneySection extends RoomSection
{
    public static function roomKey(): string
    {
        return 'money';
    }

    /**
     * @return list<string>
     */
    protected function contentLines(): array
    {
        return app(ActiveConfig::class)->filesOfType('money_notes') !== [] ? ['Payment notes: in place'] : [];
    }
}
