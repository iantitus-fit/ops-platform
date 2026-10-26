<?php

namespace App\Rooms\ContentGeneration;

use App\Icm\ActiveConfig;
use App\Rooms\RoomSection;

/**
 * The content generation room's dashboard section: whether the business has a content brief. While the brief is absent
 * the section has no lines of its own and the platform draws the room's empty line, which names the file to add
 * (05 D-17, D-18). The wording is a placeholder until the wording pass.
 */
class ContentGenerationSection extends RoomSection
{
    public static function roomKey(): string
    {
        return 'content_generation';
    }

    /**
     * @return list<string>
     */
    protected function contentLines(): array
    {
        return app(ActiveConfig::class)->filesOfType('content_brief') !== [] ? ['Content brief: in place'] : [];
    }
}
