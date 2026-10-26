<?php

namespace App\Rooms\ContentGeneration;

use App\Rooms\Room;
use App\Rooms\RoomServiceProvider;

/**
 * The room where posts and captions will be drafted later. Nothing drafts them yet: it has no agent, model call or
 * publish path this phase (05 D-14), no connector slot, no tables and no timeline entries (D-05). It defines one file
 * type, the content brief, and stays optional (04 D-08).
 */
class ContentGenerationServiceProvider extends RoomServiceProvider
{
    protected function room(): Room
    {
        return new Room(
            key: 'content_generation',
            label: 'content generation',
            fileTypes: [new ContentBriefType],
            sections: [ContentGenerationSection::class],
        );
    }
}
