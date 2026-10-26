<?php

use App\Icm\ActiveConfig;

/**
 * The Google Business Profile categories file's shape checks (ROOM-11; 05 D-07): the list holds at most nine more
 * categories after the primary, each written once, none equal to the primary, compared ignoring case and extra spaces.
 * Shape only: whether a name is one of Google's categories waits for the real connector. Every refusal is one plain
 * line on the file and field, and the version that was live stays live.
 */
const GBP_CATEGORIES_FILE = 'rooms/google_business_profile/categories.md';

/**
 * Write rooms/google_business_profile/categories.md with the given primary category and, when not null, one
 * additional_categories front matter line written exactly as the owner would type it.
 */
function writeGbpCategories(string $primary, ?string $additionalLine): void
{
    $extra = $additionalLine === null ? '' : $additionalLine."\n";

    writeIcmFile(GBP_CATEGORIES_FILE, "---\ntype: gbp_categories\nprimary_category: {$primary}\n{$extra}---\n\nThe categories Google shows.\n");
}

/**
 * An additional_categories line holding this many distinct categories, written as a front matter list.
 */
function gbpCategoryList(int $count): string
{
    $names = array_map(fn (int $number): string => "Category {$number}", range(1, $count));

    return 'additional_categories: ['.implode(', ', $names).']';
}

it('refuses ten additional categories in one plain line and keeps the live version', function () {
    $live = activateExampleFolder();
    writeGbpCategories('HVAC contractor', gbpCategoryList(10));

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe(['rooms/google_business_profile/categories.md: additional_categories has 10 categories; Google takes at most 9 after the primary, so remove 1'])
        ->and(app(ActiveConfig::class)->versionId())->toBe($live->id);
});

it('says how many to remove when there are far too many categories', function () {
    $live = activateExampleFolder();
    writeGbpCategories('HVAC contractor', gbpCategoryList(14));

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe(['rooms/google_business_profile/categories.md: additional_categories has 14 categories; Google takes at most 9 after the primary, so remove 5'])
        ->and(app(ActiveConfig::class)->versionId())->toBe($live->id);
});

it('accepts nine additional categories', function () {
    $live = activateExampleFolder();
    writeGbpCategories('HVAC contractor', gbpCategoryList(9));

    $this->artisan('icm:sync')->assertExitCode(0);

    expect(app(ActiveConfig::class)->versionId())->not->toBe($live->id)
        ->and(app(ActiveConfig::class)->filesOfType('gbp_categories')[0]['additional_categories'])->toHaveCount(9);
});

it('refuses additional categories that are not a list', function () {
    $live = activateExampleFolder();
    writeGbpCategories('HVAC contractor', 'additional_categories: Heating contractor');

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe(['rooms/google_business_profile/categories.md: additional_categories must be a list (you wrote Heating contractor)'])
        ->and(app(ActiveConfig::class)->versionId())->toBe($live->id);
});
