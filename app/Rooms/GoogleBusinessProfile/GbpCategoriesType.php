<?php

namespace App\Rooms\GoogleBusinessProfile;

use App\Icm\IcmFile;
use App\Rooms\RoomFileType;

/**
 * The Google Business Profile room's one new kind of file: the categories Google shows, rooms/google_business_profile/
 * categories.md. It holds only what Google needs that the folder does not already hold (05 D-06): a primary category and
 * a list of additional ones, with no description and no service area, which the folder already says in business/
 * profile.md. Whether a name is one of Google's categories is checked when the real connector lands (05 D-07).
 */
class GbpCategoriesType implements RoomFileType
{
    public function type(): string
    {
        return 'gbp_categories';
    }

    /**
     * The primary category is required. The shape checks on the additional list (at most 9, no repeats, none equal to
     * the primary) come in plan 05-09.
     */
    public function rules(IcmFile $file): array
    {
        return [
            'primary_category' => 'bail|required|string',
        ];
    }

    public function checks(IcmFile $file): array
    {
        return [];
    }

    /**
     * A business keeps one categories file (05 D-06, D-27). The check in App\Icm\RoomChecks refuses every other file of
     * this type in rooms/google_business_profile/, wherever the room's contract owns points, so nothing else in this room
     * enforces it.
     */
    public function singleFileLabel(): ?string
    {
        return 'Google Business Profile categories file';
    }
}
