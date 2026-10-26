<?php

use App\Enums\OwnableKind;
use App\Icm\ActiveConfig;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Rooms\GoogleBusinessProfile\FakeGoogleListingSource;
use App\Rooms\GoogleBusinessProfile\GbpCategoriesType;
use App\Rooms\GoogleBusinessProfile\GoogleBusinessProfileServiceProvider;
use App\Rooms\GoogleBusinessProfile\GoogleListingSource;
use App\Rooms\RoomRegistry;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\Rooms\RoomSectionAxe;
use Tests\Rooms\SpecTables;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

/**
 * The Google Business Profile room's boot test (ROOM-11), in the shape the pretend room and the earlier real rooms set.
 * The room has a connector slot, so it also proves the slot: the interface is read-only, the bound driver is the fake,
 * it reaches nothing outside the app, and the dashboard never asks it for the listing (05 D-11). There is no
 * database-driver guard and no skip: CI runs this on SQLite and Postgres.
 *
 * "Empty" removes the categories file and the connector file, whichever the example folder holds at this point in the
 * plan, so the room is a valid empty room and its section names the file to add (the contract owns the exact path).
 */
it('boots the Google Business Profile room empty', function () {
    $removed = collect(['rooms/google_business_profile/categories.md', 'connectors/google-business-profile.md'])
        ->filter(fn (string $path): bool => File::exists(icmPath($path)))
        ->each(fn (string $path): bool => File::delete(icmPath($path)))
        ->count();

    $version = activateExampleFolder();
    $config = app(ActiveConfig::class);
    $room = app(RoomRegistry::class)->get('google_business_profile');

    expect($version->file_count)->toBe(exampleFolderFileCount() - $removed)
        ->and($config->hasRoom('google_business_profile'))->toBeTrue();

    expect($room->key)->toBe('google_business_profile')
        ->and($room->label)->toBe('Google Business Profile')
        ->and($room->slot)->toBe('google_business_profile')
        ->and($room->slotInterface)->toBe(GoogleListingSource::class)
        ->and($room->handles)->toBe([])
        ->and($room->tables)->toBe([])
        ->and($room->timelineEntries)->toBe([])
        ->and($room->morphMap)->toBe([]);

    $contract = $config->roomContract('google_business_profile');

    expect($contract['owns'])->toBe(SpecTables::patternsOwnedBy(File::get(base_path('docs/SPEC.md')), 'Google Business Profile'))
        ->and($contract['owns'])->toBe(['rooms/google_business_profile/categories.md'])
        ->and($contract['reads'])->toBe(['business/profile.md', 'business/hours.md', 'business/services/*.md', 'business/offers/*.md'])
        ->and($contract['writes_records'])->toBe([])
        ->and($contract['connector'])->toBe('google_business_profile');

    $providers = require base_path('bootstrap/providers.php');

    expect($providers[0])->toBe(AppServiceProvider::class)
        ->and($providers)->toContain(GoogleBusinessProfileServiceProvider::class);

    $this->get('/admin')
        ->assertOk()
        ->assertSee('data-room-section="google_business_profile"', false)
        ->assertSee('Google Business Profile: nothing set up yet. Add rooms/google_business_profile/categories.md to start.');

    $page = visit('/admin');
    $page->assertSee('nothing set up yet');

    RoomSectionAxe::assertClean(RoomSectionAxe::report($page, 'google_business_profile'));
});

/**
 * 05 D-11 and success criterion 4: asking for the slot gives the fake, which returns a fictional listing and reaches
 * nothing outside the app, and any other driver name is refused.
 */
it('binds the read-only slot to a fake that reaches nothing outside the app', function () {
    Http::fake();

    $source = app(GoogleListingSource::class);

    expect($source)->toBeInstanceOf(FakeGoogleListingSource::class);

    $listing = $source->fetchListing();

    expect($listing)->toHaveKeys(['name', 'phone', 'primary_category', 'additional_categories', 'description', 'service_area']);

    Http::assertNothingSent();
});

it('refuses a Google Business Profile driver other than the fake', function () {
    config(['rooms.google_business_profile.driver' => 'live']);

    expect(fn () => app(GoogleListingSource::class))
        ->toThrow(InvalidArgumentException::class, 'live');
});

/**
 * WR-02: the section answers a display question, so a bad driver setting must not take the dashboard down, and must not
 * read as connected. The provider still fails fast for a real caller (the test above and the next one).
 */
it('draws the dashboard and says not connected when the driver setting is bad', function (mixed $driver) {
    activateExampleFolder();
    config(['rooms.google_business_profile.driver' => $driver]);

    $this->get('/admin')
        ->assertOk()
        ->assertSee('data-room-section="google_business_profile"', false)
        ->assertSee('Not connected to Google: this room runs on a stand-in and sends nothing.');
})->with([
    'a name that is not the fake' => ['live'],
    'an array' => [['live']],
    'a number' => [5],
    'a boolean' => [true],
]);

it('refuses a driver setting that is not a string with the same kind of error, not a conversion error', function () {
    config(['rooms.google_business_profile.driver' => ['live']]);

    expect(fn () => app(GoogleListingSource::class))
        ->toThrow(InvalidArgumentException::class, 'array');
});

/**
 * 05 D-11: the dashboard never fetches the listing, so drawing /admin leaves the fake's fetch count at zero.
 */
it('never fetches the listing when the dashboard is drawn', function () {
    activateExampleFolder();

    $this->get('/admin')->assertOk()->assertSee('data-room-section="google_business_profile"', false);

    expect(app(FakeGoogleListingSource::class)->fetchCount())->toBe(0);
});

/**
 * 05 D-11: the slot interface is read-only. Its one method fetches; nothing on it sends to Google.
 */
it('declares exactly one method on the slot interface', function () {
    $methods = collect((new ReflectionClass(GoogleListingSource::class))->getMethods())
        ->map(fn (ReflectionMethod $method): string => $method->getName())
        ->all();

    expect($methods)->toBe(['fetchListing']);
});

/**
 * 05 D-10 and success criterion 4: the example folder's connector file fills the slot and owns and reads no kind of
 * record, so Google is the truth for nothing the platform keeps. The bare word none compiles to an empty list. The bound
 * driver is still the fake. The section's "not connected" line shows once the room has its categories file, so the
 * furnished row of the categories test checks it on the page, not this case.
 */
it('fills its slot with a connector file that owns and reads none', function () {
    activateExampleFolder();
    $config = app(ActiveConfig::class);

    $connector = $config->file('connectors/google-business-profile.md');

    expect($config->version()->compiled['index']['connector'])->toContain('connectors/google-business-profile.md')
        ->and($connector['front_matter']['slot'])->toBe('google_business_profile')
        ->and($connector['front_matter']['owns'])->toBe([])
        ->and($connector['front_matter']['reads'])->toBe([]);

    expect(app(GoogleListingSource::class))->toBeInstanceOf(FakeGoogleListingSource::class);
});

/**
 * 05 D-10: owns written as [none] is a list holding the word none, which is not a kind of record, and is refused.
 */
it('refuses a connector file that writes owns as [none]', function () {
    $live = activateExampleFolder();

    breakFile('connectors/google-business-profile.md', fn (string $contents): string => str_replace('owns: none', 'owns: [none]', $contents));

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe(['connectors/google-business-profile.md: owns has none, which is not a kind of record; the kinds are: '.implode(', ', OwnableKind::values())])
        ->and(app(ActiveConfig::class)->versionId())->toBe($live->id);
});

/**
 * 04 D-25: a connector file names a slot, and the room that offers the slot must be in the folder.
 */
it('refuses the connector file when the room folder is gone', function () {
    $live = activateExampleFolder();

    File::deleteDirectory(icmPath('rooms/google_business_profile'));

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe(['connectors/google-business-profile.md: this file fills the google_business_profile slot, but the Google Business Profile room is not in this folder'])
        ->and(app(ActiveConfig::class)->versionId())->toBe($live->id);
});

/**
 * The furnished boot (ROOM-11, ROOM-05): the example folder as shipped holds the one categories file, the room's own file
 * type reads it, and the section shows the primary category and that the room is not connected to Google.
 */
it('boots the Google Business Profile room with its example folder', function () {
    $version = activateExampleFolder();
    $config = app(ActiveConfig::class);
    $room = app(RoomRegistry::class)->get('google_business_profile');

    expect($version->file_count)->toBe(exampleFolderFileCount())
        ->and($config->hasRoom('google_business_profile'))->toBeTrue()
        ->and($room->fileTypes)->toHaveCount(1)
        ->and($room->fileTypes[0])->toBeInstanceOf(GbpCategoriesType::class)
        ->and($room->fileTypes[0]->type())->toBe('gbp_categories');

    $categories = $config->filesOfType('gbp_categories');

    expect($categories)->toHaveCount(1)
        ->and($categories[0]['path'])->toBe('rooms/google_business_profile/categories.md')
        ->and($categories[0]['primary_category'])->toBe('HVAC contractor');

    $this->get('/admin')
        ->assertOk()
        ->assertSee('data-room-section="google_business_profile"', false)
        ->assertSee('Google Business Profile')
        ->assertSee('Primary category: HVAC contractor')
        ->assertSee('Not connected to Google: this room runs on a stand-in and sends nothing.')
        ->assertDontSee('Google Business Profile: nothing set up yet');

    $page = visit('/admin');
    $page->assertSee('Primary category: HVAC contractor');

    RoomSectionAxe::assertClean(RoomSectionAxe::report($page, 'google_business_profile'));
});

/**
 * 05 D-07 (shape only): the primary category is required, so a categories file without one is refused, and the version
 * that was live stays live.
 */
it('refuses a categories file without a primary category', function () {
    $live = activateExampleFolder();

    writeIcmFile('rooms/google_business_profile/categories.md', "---\ntype: gbp_categories\nadditional_categories: [Heating contractor]\n---\nThe categories Google shows.");

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe(['rooms/google_business_profile/categories.md: primary_category is missing, and it is required'])
        ->and(app(ActiveConfig::class)->versionId())->toBe($live->id);
});

/**
 * D-27 under the shipped contract: a second categories file in the room's folder is refused in one line naming both
 * files, and the version that was live stays live.
 */
it('refuses a second categories file under the shipped contract', function () {
    $live = activateExampleFolder();
    writeIcmFile('rooms/google_business_profile/more.md', "---\ntype: gbp_categories\nprimary_category: Heating contractor\n---\nMore categories.");

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe(['rooms/google_business_profile/more.md: this business already has a Google Business Profile categories file in rooms/google_business_profile/categories.md; keep one'])
        ->and(app(ActiveConfig::class)->versionId())->toBe($live->id);
});

/**
 * D-27 under a contract the owner edited to own a second path: owning the file does not let a business keep two. The
 * second file sorts after categories.md, the file the sync keeps when a contract claims both.
 */
it('refuses a second categories file under a contract edited to own two paths', function () {
    $live = activateExampleFolder();

    breakFile('rooms/google_business_profile/CONTEXT.md', fn (string $contents): string => str_replace(
        'owns: [rooms/google_business_profile/categories.md]',
        'owns: [rooms/google_business_profile/categories.md, rooms/google_business_profile/more.md]',
        $contents,
    ));
    writeIcmFile('rooms/google_business_profile/more.md', "---\ntype: gbp_categories\nprimary_category: Heating contractor\n---\nMore categories.");

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe(['rooms/google_business_profile/more.md: this business already has a Google Business Profile categories file in rooms/google_business_profile/categories.md; keep one'])
        ->and(app(ActiveConfig::class)->versionId())->toBe($live->id);
});

/**
 * D-27: owning two paths is fine while only one categories file exists, so the contract can be edited ahead of the file.
 */
it('accepts a contract that owns two paths while only one categories file is there', function () {
    activateExampleFolder();

    breakFile('rooms/google_business_profile/CONTEXT.md', fn (string $contents): string => str_replace(
        'owns: [rooms/google_business_profile/categories.md]',
        'owns: [rooms/google_business_profile/categories.md, rooms/google_business_profile/more.md]',
        $contents,
    ));

    $this->artisan('icm:sync')->assertExitCode(0);

    expect(app(ActiveConfig::class)->filesOfType('gbp_categories'))->toHaveCount(1);
});
