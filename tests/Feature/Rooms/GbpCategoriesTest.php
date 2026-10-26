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

it('refuses a category repeated ignoring case and extra spaces, quoting the first spelling', function () {
    $live = activateExampleFolder();
    writeGbpCategories('HVAC contractor', "additional_categories: [Furnace repair service, Heating contractor, 'furnace  REPAIR service']");

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe(['rooms/google_business_profile/categories.md: additional_categories lists Furnace repair service more than once; write each category once'])
        ->and(app(ActiveConfig::class)->versionId())->toBe($live->id);
});

it('gives one line for a category written three times', function () {
    $live = activateExampleFolder();
    writeGbpCategories('HVAC contractor', 'additional_categories: [Heating contractor, heating contractor, HEATING CONTRACTOR]');

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe(['rooms/google_business_profile/categories.md: additional_categories lists Heating contractor more than once; write each category once'])
        ->and(app(ActiveConfig::class)->versionId())->toBe($live->id);
});

it('refuses a category equal to the primary ignoring case', function () {
    $live = activateExampleFolder();
    writeGbpCategories('HVAC contractor', 'additional_categories: [hvac contractor]');

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe(['rooms/google_business_profile/categories.md: additional_categories repeats the primary category HVAC contractor; leave it out of this list'])
        ->and(app(ActiveConfig::class)->versionId())->toBe($live->id);
});

it('refuses an empty entry by its position', function () {
    $live = activateExampleFolder();
    writeGbpCategories('HVAC contractor', "additional_categories: [Heating contractor, '  ']");

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe(['rooms/google_business_profile/categories.md: additional_categories item 2 is empty; write a category there or remove it'])
        ->and(app(ActiveConfig::class)->versionId())->toBe($live->id);
});

it('does not count blank entries as repeats of each other', function () {
    $live = activateExampleFolder();
    writeGbpCategories('HVAC contractor', "additional_categories: ['', '  ']");

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe([
        'rooms/google_business_profile/categories.md: additional_categories item 1 is empty; write a category there or remove it',
        'rooms/google_business_profile/categories.md: additional_categories item 2 is empty; write a category there or remove it',
    ])->and(app(ActiveConfig::class)->versionId())->toBe($live->id);
});

it('refuses an entry that is not text with the ordinary plain-words line and no other', function () {
    $live = activateExampleFolder();
    writeGbpCategories('HVAC contractor', 'additional_categories: [Heating contractor, 7]');

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe(['rooms/google_business_profile/categories.md: additional_categories item 2 must be text; wrap it in quotes (you wrote 7)'])
        ->and(app(ActiveConfig::class)->versionId())->toBe($live->id);
});

it('gives one line per problem in a fixed order: too many, then repeats, then the primary', function () {
    $live = activateExampleFolder();
    writeGbpCategories('HVAC contractor', 'additional_categories: ['.implode(', ', [...array_map(fn (int $number): string => "Category {$number}", range(1, 9)), 'category   1', 'HVAC Contractor']).']');

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe([
        'rooms/google_business_profile/categories.md: additional_categories has 11 categories; Google takes at most 9 after the primary, so remove 2',
        'rooms/google_business_profile/categories.md: additional_categories lists Category 1 more than once; write each category once',
        'rooms/google_business_profile/categories.md: additional_categories repeats the primary category HVAC contractor; leave it out of this list',
    ])->and(app(ActiveConfig::class)->versionId())->toBe($live->id);
});

it('puts empty entries after the count and before the repeats', function () {
    $live = activateExampleFolder();
    writeGbpCategories('HVAC contractor', "additional_categories: [Heating contractor, '', heating contractor]");

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe([
        'rooms/google_business_profile/categories.md: additional_categories item 2 is empty; write a category there or remove it',
        'rooms/google_business_profile/categories.md: additional_categories lists Heating contractor more than once; write each category once',
    ])->and(app(ActiveConfig::class)->versionId())->toBe($live->id);
});

it('cuts a long repeated value to 80 characters', function () {
    $live = activateExampleFolder();
    $long = str_repeat('Heating and cooling contractor ', 5);
    writeGbpCategories('HVAC contractor', "additional_categories: [{$long}, {$long}]");

    $this->artisan('icm:sync')->assertExitCode(1);

    $line = rejectedErrors()[0];
    $value = str($line)->after('additional_categories lists ')->before(' more than once')->toString();

    expect(rejectedErrors())->toHaveCount(1)
        ->and(mb_strlen($value))->toBe(80)
        ->and($value)->toEndWith('...')
        ->and(app(ActiveConfig::class)->versionId())->toBe($live->id);
});

it('marks a hidden character in a quoted value instead of printing it', function () {
    $live = activateExampleFolder();
    writeGbpCategories('HVAC contractor', 'additional_categories: ["Heating​contractor", "Heating​contractor"]');

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe(['rooms/google_business_profile/categories.md: additional_categories lists Heating[hidden character]contractor more than once; write each category once'])
        ->and(app(ActiveConfig::class)->versionId())->toBe($live->id);
});

it('refuses a blank primary category with the ordinary missing line', function () {
    $live = activateExampleFolder();
    writeGbpCategories('""', null);

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe(['rooms/google_business_profile/categories.md: primary_category is missing, and it is required'])
        ->and(app(ActiveConfig::class)->versionId())->toBe($live->id);
});

it('accepts an empty list, an absent list and the example file as shipped', function (?string $additionalLine) {
    $live = activateExampleFolder();

    if ($additionalLine !== 'shipped') {
        writeGbpCategories('HVAC contractor', $additionalLine);
    }

    $this->artisan('icm:sync')->assertExitCode(0);

    expect(app(ActiveConfig::class)->filesOfType('gbp_categories'))->toHaveCount(1);
})->with([
    'an empty list' => 'additional_categories: []',
    'no list at all' => null,
    'the example as shipped' => 'shipped',
]);
