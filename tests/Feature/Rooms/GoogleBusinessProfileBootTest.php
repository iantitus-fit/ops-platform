<?php

use App\Icm\ActiveConfig;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Rooms\GoogleBusinessProfile\FakeGoogleListingSource;
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
