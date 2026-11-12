<?php

use App\Actions\OpenProject;
use App\Filament\Resources\Contacts\ContactResource;
use App\Filament\Widgets\NewLeads;
use App\Icm\ActiveConfig;
use App\Models\Contact;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Rooms\Leads\LeadsSection;
use App\Rooms\Leads\LeadsServiceProvider;
use App\Rooms\RoomRegistry;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\File;
use Pest\Browser\Playwright\Page;
use Tests\Rooms\RoomSectionAxe;
use Tests\Rooms\SpecTables;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

/**
 * The leads room's boot test (ROOM-05, ROOM-07), in the shape the team room set: the room boots and each boot proves the
 * registered room and its contract against SPEC's rows (04 D-14). The leads room owns no file, so its empty folder and
 * its example folder are the same folder and there is one boot case, not two (04 D-21). The section listing open projects
 * (D-15) and its axe check (A11Y-01) are here too. There is no database-driver guard and no skip: CI runs this on SQLite
 * and Postgres.
 */
it('boots the leads room', function () {
    $version = activateExampleFolder();

    $config = app(ActiveConfig::class);
    $room = app(RoomRegistry::class)->get('leads');

    expect($version->file_count)->toBe(exampleFolderFileCount())
        ->and($config->hasRoom('leads'))->toBeTrue();

    expect($room->label)->toBe('leads')
        ->and($room->handles)->toBe([])
        ->and($room->fileTypes)->toBe([])
        ->and($room->slot)->toBeNull()
        ->and($room->slotInterface)->toBeNull()
        ->and($room->sections)->toBe([LeadsSection::class])
        ->and($room->tables)->toBe([])
        ->and($room->timelineEntries)->toBe([])
        ->and($room->morphMap)->toBe([])
        ->and($room->neverLeftOutBecause)->toBeNull()
        ->and($room->claimedWidgets)->toBe([NewLeads::class]);

    $contract = $config->roomContract('leads');

    expect($contract['owns'])->toBe([])
        ->and($contract['owns'])->toBe(SpecTables::patternsOwnedBy(File::get(base_path('docs/SPEC.md')), 'leads'))
        ->and($contract['reads'])->toBe(['business/lead-sources.md', 'business/offers/*.md'])
        ->and($contract['writes_records'])->toBe([])
        ->and($contract['connector'])->toBe('none');

    expect($config->roomOwnedFiles('leads'))->toBe([]);

    $context = File::get(icmPath('rooms/leads/CONTEXT.md'));

    expect($context)->toContain('It owns no file.')
        ->and($context)->toContain('The web form is shared and owned by no room, so it keeps taking leads while this room is off.');

    expect(File::directories(icmPath('rooms/leads')))->toBe([])
        ->and(array_map('basename', File::files(icmPath('rooms/leads'))))->toBe(['CONTEXT.md']);

    expect(app(RoomRegistry::class)->widgetClasses())->not->toContain(NewLeads::class)
        ->toContain(LeadsSection::class);

    $panelWidgets = Filament::getPanel('admin')->getWidgets();

    expect(array_count_values($panelWidgets)[NewLeads::class] ?? 0)->toBe(1);

    $providers = require base_path('bootstrap/providers.php');

    expect($providers[0])->toBe(AppServiceProvider::class)
        ->and($providers)->toContain(LeadsServiceProvider::class);

    $this->get('/admin')
        ->assertOk()
        ->assertSeeInOrder([
            'Drafts waiting',
            'Customers waiting while you handle it',
            'New leads',
            "Today's conversations",
        ]);
});

/**
 * D-03: the leads room is optional. A folder without rooms/leads/ still activates, one file fewer, the room is off, and
 * New leads still shows because the widget is the panel's own and the room only points at it.
 */
it('lets a business leave the leads room out', function () {
    File::deleteDirectory(icmPath('rooms/leads'));

    $version = activateExampleFolder();

    expect($version->file_count)->toBe(exampleFolderFileCount() - 1)
        ->and(app(ActiveConfig::class)->hasRoom('leads'))->toBeFalse();

    $this->get('/admin')
        ->assertOk()
        ->assertSee('New leads');
});

/**
 * D-15, criterion 2: an open project shows in the leads section on the dashboard, in the display time zone and with an
 * absolute date inside a time element, linked by keyboard to its contact's screen (Phase 6 UI review).
 */
it('shows an open project on the dashboard', function () {
    activateExampleFolder();
    test()->travelTo(Carbon::parse('2026-10-29 14:02', 'America/Chicago'));

    $owner = User::factory()->create(['name' => 'Ian Owner']);
    $this->actingAs($owner);
    $contact = Contact::factory()->create(['name' => 'Dana Whitfield']);
    app(OpenProject::class)->byHand($contact, $owner);

    $this->get('/admin')
        ->assertOk()
        ->assertSee('data-room-section="leads"', false)
        ->assertSee('Leads')
        ->assertSee('Dana Whitfield')
        ->assertSee('Job 1')
        ->assertSee('opened')
        ->assertSee('2026-10-29 14:02')
        ->assertSee('datetime="2026-10-29T14:02:00-05:00"', false)
        ->assertSee('opened by Ian Owner')
        ->assertSee('href="'.ContactResource::getUrl('view', ['record' => $contact]).'"', false);
});

/**
 * A11Y-01: axe finds nothing in the leads section with no open project and with one.
 */
it('finds no accessibility violation in the leads section', function (bool $withProject) {
    activateExampleFolder();

    if ($withProject) {
        $owner = User::factory()->create(['name' => 'Ian Owner']);
        $this->actingAs($owner);
        app(OpenProject::class)->byHand(Contact::factory()->create(['name' => 'Dana Whitfield']), $owner);
    }

    $page = visit('/admin');
    $page->assertSee('Leads');

    RoomSectionAxe::assertClean(RoomSectionAxe::report($page, 'leads'));
})->with([
    'with no open project' => [false],
    'with an open project' => [true],
]);

/**
 * The most Tab presses the dashboard walk may take from the top of /admin to the last of three leads rows. This was
 * measured on 2026-11-06 (plan 08-10, review item 07 IN-09): the walk took 11 presses on a desktop window and 13 on a
 * phone, so the cap is the larger, 13, plus a margin of 10. The margin leaves room for one more room section above the
 * leads without letting the walk wander; a walk that takes more than 23 presses means focus left the page or the
 * order changed, and should fail.
 */
const LEADS_KEYBOARD_TAB_CAP = 23;

/**
 * The contact name of the leads row link that has focus now, or null when focus is anywhere else.
 */
function leadsKeyboardFocusedRow(Page $browser): ?string
{
    $name = $browser->evaluate(<<<'JS'
() => {
  const element = document.activeElement;
  return element.matches('[data-leads-row] a') ? element.innerText.trim() : null;
}
JS);

    return is_string($name) ? $name : null;
}

/**
 * The keyboard walk of the dashboard (UAT test 3, WINDOWS row 41), as a browser test: from the top of /admin, Tab
 * reaches each leads row's link in the order the section lists them, newest first, and Enter on a row opens that
 * contact's page. Real Chromium and real keys, with Playwright's own waiting.
 */
it('reaches each leads row by Tab in order and opens its contact with Enter', function () {
    activateExampleFolder();

    $owner = User::factory()->create(['name' => 'Ian Owner']);
    $this->actingAs($owner);

    $contacts = [];

    foreach (['Avery Lane' => '2026-10-29 09:00', 'Blake Moreno' => '2026-10-29 10:00', 'Casey Ortiz' => '2026-10-29 11:00'] as $name => $openedAt) {
        $this->travelTo(Carbon::parse($openedAt, 'America/Chicago'));
        $contacts[$name] = Contact::factory()->create(['name' => $name]);
        app(OpenProject::class)->byHand($contacts[$name], $owner);
    }

    $this->travelBack();

    $page = visit('/admin');
    $page->assertSee('Casey Ortiz');
    $browser = $page->page();

    $reached = [];
    $presses = 0;

    while ($presses < LEADS_KEYBOARD_TAB_CAP && count($reached) < 3) {
        pressKey($browser, 'Tab');
        $presses++;

        $row = leadsKeyboardFocusedRow($browser);

        if ($row !== null && end($reached) !== $row) {
            $reached[] = $row;
        }
    }

    expect($reached)->toBe(['Casey Ortiz', 'Blake Moreno', 'Avery Lane']);

    pressKey($browser, 'Enter');

    // The contact page is open once its project list's New job button is on it. Playwright's own locator wait is used
    // because the plugin's waitForURL() never reads Playwright's answer in pest-plugin-browser 5.1, so it does not wait.
    $browser->getByRole('button', ['name' => 'New job', 'exact' => true])->waitFor(['state' => 'attached']);

    $page->assertPathIs(ContactResource::getUrl('view', ['record' => $contacts['Avery Lane']], isAbsolute: false));
});
