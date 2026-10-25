<?php

namespace App\Rooms\ContentGeneration;

use App\Icm\IcmFile;
use App\Rooms\RoomFileType;

/**
 * The content generation room's one new kind of file: the content brief, rooms/content_generation/brief.md. It says who
 * the business writes for and which topics it covers and avoids. The brief is guidance a model reads once the room's
 * writer is built, and nothing in it is enforced (05 D-12): a rule that must hold goes in voice/how-we-talk.md under
 * never_say, where code checks every draft. Its front matter is only its type, so there are no field rules and no
 * further checks.
 */
class ContentBriefType implements RoomFileType
{
    public function type(): string
    {
        return 'content_brief';
    }

    public function rules(IcmFile $file): array
    {
        return [];
    }

    public function checks(IcmFile $file): array
    {
        return [];
    }

    /**
     * A business keeps one brief (05 D-12, D-27).
     */
    public function singleFileLabel(): ?string
    {
        return 'content brief';
    }
}
