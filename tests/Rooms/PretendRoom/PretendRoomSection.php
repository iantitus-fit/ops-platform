<?php

namespace Tests\Rooms\PretendRoom;

use App\Icm\ActiveConfig;
use App\Rooms\RoomSection;

/**
 * The pretend room's dashboard section: the shared room section view under the key "pretend". canView() hides it when
 * the room is off and Filament refuses a hidden widget's requests, so these reads only run while the room is on.
 */
class PretendRoomSection extends RoomSection
{
    public static function roomKey(): string
    {
        return 'pretend';
    }

    /**
     * The ping count, then the title of each pinned note in the live version.
     *
     * @return list<string>
     */
    protected function contentLines(): array
    {
        $lines = ['Pings so far: '.PretendPing::query()->count()];

        foreach (app(ActiveConfig::class)->filesOfType('pretend_note') as $note) {
            if ($note['pinned'] === true) {
                $lines[] = 'Pinned: '.$note['title'];
            }
        }

        return $lines;
    }
}
