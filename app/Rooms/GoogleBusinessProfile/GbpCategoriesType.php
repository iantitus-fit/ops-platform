<?php

namespace App\Rooms\GoogleBusinessProfile;

use App\Icm\IcmFile;
use App\Icm\PlainWords;
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
     * Shape only (05 D-07): Google takes at most nine additional categories after the primary, each once, none the same
     * as the primary, compared the way an owner reads them (ignoring case and extra spaces). One line per problem, in a
     * fixed order: too many, then empty entries, then repeats in list order, then equal to the primary. The count is
     * written as its own sentence rather than with the max rule, whose sentence reads wrongly for a list. Only text
     * entries are looked at; the rules above already report an entry that is not text. Whether a name is one of
     * Google's categories waits for the real connector (05 D-07); nothing here calls Google or any outside list.
     * Owner values reach a line only through PlainWords::oneLine().
     */
    public function checks(IcmFile $file): array
    {
        $additional = $file->frontMatter['additional_categories'] ?? null;

        if (! is_array($additional)) {
            return [];
        }

        if (! array_is_list($additional)) {
            return [$this->error($file, PlainWords::render('Array', [], 'additional_categories', $additional))];
        }

        $lines = [];

        if (count($additional) > self::MOST_ADDITIONAL) {
            $remove = count($additional) - self::MOST_ADDITIONAL;
            $lines[] = 'additional_categories has '.count($additional)." categories; Google takes at most 9 after the primary, so remove {$remove}";
        }

        $seen = [];

        foreach ($additional as $position => $category) {
            if (! is_string($category)) {
                continue;
            }

            $key = $this->normalized($category);

            if ($key === '') {
                $number = $position + 1;
                $lines[] = "additional_categories item {$number} is empty; write a category there or remove it";

                continue;
            }

            $seen[$key] ??= ['first' => $category, 'times' => 0];
            $seen[$key]['times']++;
        }

        foreach ($seen as $entry) {
            if ($entry['times'] > 1) {
                $lines[] = 'additional_categories lists '.PlainWords::oneLine($entry['first']).' more than once; write each category once';
            }
        }

        $primary = $file->frontMatter['primary_category'] ?? null;

        if (is_string($primary) && $this->normalized($primary) !== '' && isset($seen[$this->normalized($primary)])) {
            $lines[] = 'additional_categories repeats the primary category '.PlainWords::oneLine($primary).'; leave it out of this list';
        }

        return array_map(fn (string $line): array => $this->error($file, $line), $lines);
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
     * Where the one categories file lives (05 D-29); the SPEC file types table gives the same path.
     */
    public function singleFileDefaultPath(): ?string
    {
        return 'rooms/google_business_profile/categories.md';
    }

    /**
     * @return array{file: string, field: string, message: string}
     */
    private function error(IcmFile $file, string $message): array
    {
        return ['file' => $file->path, 'field' => 'additional_categories', 'message' => $message];
    }

    /**
     * A category as an owner reads it: every run of whitespace is one space, the ends are trimmed and case does not
     * count, so 'furnace  REPAIR service' and 'Furnace repair service' are the same category.
     */
    private function normalized(string $category): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $category)));
    }
}
