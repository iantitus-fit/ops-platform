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
    /**
     * How many categories Google takes after the primary.
     */
    private const int MOST_ADDITIONAL = 9;

    public function type(): string
    {
        return 'gbp_categories';
    }

    /**
     * The primary category is required text. The additional categories are optional: a list of text, which the
     * checks() below then keep to nine, each once. A list that is not a list, or an entry that is not text, is refused
     * here in the sync's ordinary plain words.
     */
    public function rules(IcmFile $file): array
    {
        return [
            'primary_category' => 'bail|required|string',
            'additional_categories' => 'bail|nullable|array',
            'additional_categories.*' => 'bail|string',
        ];
    }

    /**
     * Shape only (05 D-07): Google takes at most nine additional categories after the primary. The count is written as
     * its own sentence rather than with the max rule, whose sentence reads wrongly for a list. Whether a name is one of
     * Google's categories waits for the real connector; nothing here calls Google or any outside list.
     */
    public function checks(IcmFile $file): array
    {
        $additional = $file->frontMatter['additional_categories'] ?? null;

        if (! is_array($additional) || count($additional) <= self::MOST_ADDITIONAL) {
            return [];
        }

        $remove = count($additional) - self::MOST_ADDITIONAL;

        return [$this->error($file, 'additional_categories has '.count($additional)." categories; Google takes at most 9 after the primary, so remove {$remove}")];
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

    /**
     * @return array{file: string, field: string, message: string}
     */
    private function error(IcmFile $file, string $message): array
    {
        return ['file' => $file->path, 'field' => 'additional_categories', 'message' => $message];
    }
}
