<?php

use App\Icm\ActiveConfig;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Rooms\Marketing\MarketingServiceProvider;
use App\Rooms\RoomRegistry;
use App\SendRule\OfferSentences;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Tests\Rooms\RoomSectionAxe;
use Tests\Rooms\SpecTables;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

/**
 * Move the clock to a moment in the business's time zone, the way the safety check tests do.
 */
function marketingAt(string $moment): void
{
    test()->travelTo(Carbon::parse($moment, 'America/Chicago'));
}

/**
 * The marketing room's boot test (ROOM-05, ROOM-09), in the shape the pretend room and company knowledge set: the room
 * boots empty and with its example folder, each boot proves the registered room, its contract against SPEC's rows
 * (04 D-14), its dashboard section and the axe check on that section (A11Y-01, D-18). The room has no slot, tables or
 * timeline entries (D-05), so those proofs are that it adds none. There is no database-driver guard and no skip: CI
 * runs this on SQLite and Postgres.
 *
 * "Empty" removes the one offer and keeps business/lead-sources.md, which the sync requires (D-03). The section then
 * says there is no offer live today.
 */
it('boots the marketing room', function (bool $empty) {
    if ($empty) {
        File::delete(icmPath('business/offers/first-visit-promotion.md'));
    }

    $version = activateExampleFolder();
    marketingAt('2026-11-02 12:00');

    $config = app(ActiveConfig::class);
    $room = app(RoomRegistry::class)->get('marketing');

    expect($version->file_count)->toBe(exampleFolderFileCount() - ($empty ? 1 : 0))
        ->and($config->hasRoom('marketing'))->toBeTrue();

    expect($room->label)->toBe('marketing')
        ->and($room->handles)->toBe(['lead_sources', 'offer'])
        ->and($room->fileTypes)->toBe([])
        ->and($room->slot)->toBeNull()
        ->and($room->slotInterface)->toBeNull()
        ->and($room->tables)->toBe([])
        ->and($room->timelineEntries)->toBe([])
        ->and($room->morphMap)->toBe([]);

    $contract = $config->roomContract('marketing');

    expect($contract['owns'])->toBe(SpecTables::patternsOwnedBy(File::get(base_path('docs/SPEC.md')), 'marketing'))
        ->and($contract['reads'])->toBe(['business/profile.md'])
        ->and($contract['writes_records'])->toBe([])
        ->and($contract['connector'])->toBe('none');

    $owned = $config->roomOwnedFiles('marketing');

    expect($owned)->toContain('business/lead-sources.md');

    if (! $empty) {
        expect($owned)->toContain('business/offers/first-visit-promotion.md');
    }

    $providers = require base_path('bootstrap/providers.php');

    expect($providers[0])->toBe(AppServiceProvider::class)
        ->and($providers)->toContain(MarketingServiceProvider::class);

    $dashboard = $this->get('/admin')
        ->assertOk()
        ->assertSee('data-room-section="marketing"', false)
        ->assertSee('Marketing')
        ->assertDontSee('Marketing: nothing set up yet')
        ->assertSee('Lead sources: 4');

    if ($empty) {
        $dashboard->assertSee('Offers live today: none');
    } else {
        $dashboard->assertSee('Offers live today: First visit promotion');
    }

    $page = visit('/admin');
    $page->assertSee('Lead sources: 4');

    RoomSectionAxe::assertClean(RoomSectionAxe::report($page, 'marketing'));
})->with([
    'empty' => [true],
    'with its example folder' => [false],
]);

/**
 * D-17: the section never works out "live today" for itself. At each of four moments in the business's time zone it
 * names exactly the offers the front desk would send, by name, and the moments straddle the start date and the end date
 * (inclusive, so 11:30 pm on the end date is still live although UTC has rolled to the next day).
 */
it('names the offers the front desk would send at each moment', function (string $moment, bool $live) {
    activateExampleFolder();
    marketingAt($moment);

    $config = app(ActiveConfig::class);
    $names = array_map(
        fn (array $sentence): string => (string) $config->offer($sentence['slug'])['name'],
        app(OfferSentences::class)->liveToday(),
    );

    expect($names !== [])->toBe($live);

    $line = $live ? 'Offers live today: '.implode(', ', $names) : 'Offers live today: none';

    $this->get('/admin')->assertOk()->assertSee($line);

    if ($live) {
        $this->get('/admin')->assertDontSee('Offers live today: none');
    }
})->with([
    'before the start date' => ['2026-09-15 12:00', false],
    'inside the window' => ['2026-11-02 12:00', true],
    'at 11:30 pm on the end date' => ['2026-12-31 23:30', true],
    'after the end date' => ['2027-01-05 12:00', false],
]);

/**
 * 04 D-17 with a real room: marketing may not claim a knowledge file, which company knowledge handles. The line names
 * both rooms, and the version that was live stays live.
 */
it('refuses a marketing contract that claims the FAQ and names the company knowledge room', function () {
    $live = activateExampleFolder();

    breakFile('rooms/marketing/CONTEXT.md', fn (string $contents): string => str_replace(
        'owns: [business/lead-sources.md, business/offers/*.md]',
        'owns: [business/lead-sources.md, business/offers/*.md, business/faq.md]',
        $contents,
    ));

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe(['rooms/marketing/CONTEXT.md: business/faq.md is a knowledge file, which the company knowledge room handles; marketing can read it'])
        ->and(app(ActiveConfig::class)->versionId())->toBe($live->id);
});
