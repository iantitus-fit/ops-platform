<?php

use App\Actions\OpenProject;
use App\Filament\Resources\Contacts\ContactResource;
use App\Models\Appointment;
use App\Models\Contact;
use App\Models\User;
use Carbon\CarbonImmutable;
use Pest\Browser\Playwright\Page;
use Tests\Rooms\RoomSectionAxe;

/**
 * The keyboard and screen reader walk of the contact screen's Appointments tab (D-43, U-33, WCAG 2.1.1, 2.4.3), as
 * browser tests: Tab reaches Book by hand from the top of the page, the Cancel visit and Mark done dialogs work by
 * keyboard, and once either is confirmed keyboard focus lands on the tab's Book by hand button, not on the page body
 * (the row's own buttons are gone, research Pattern 11). axe finds nothing on the tab or in an open dialog. Real Chromium
 * and real keys, because these faults live in how the browser's focus follows Livewire's re-render, which a Livewire
 * test cannot see. Every wait is Playwright's own locator wait (the plugin's function and URL waits return at once in
 * version 5.1). They run in CI on both databases with no skip.
 */

/**
 * The submit button of the confirmation that is open now, inside the open modal window.
 */
const APPOINTMENTS_KEYBOARD_CONFIRM = '.fi-modal-open .fi-modal-footer button[type="submit"]';

/**
 * The most Tab presses a walk may take from the top of the contact page. The panel's own controls come first (sidebar, top
 * bar, the contact's details), then the Projects tab's buttons, then the Appointments tab.
 */
const APPOINTMENTS_KEYBOARD_TAB_CAP = 40;

/**
 * Dana Whitfield with one job and three visits on the clock at Thursday 2026-11-05 10:30 in the shop's zone: AC repair
 * that started at 10:00 and is still booked, Furnace repair booked ahead for Friday, and a cancelled visit from
 * Wednesday. Returns the address of her page. The test calls visit() itself, because the Pest browser plugin starts its
 * browser only for a test whose own body calls it.
 *
 * @return array{0: string, 1: Appointment, 2: Appointment}
 */
function appointmentsKeyboardContactPage(User $owner): array
{
    activateExampleFolder();
    test()->travelTo(CarbonImmutable::parse('2026-11-05 10:30', 'America/Chicago'));

    $contact = Contact::factory()->create(['name' => 'Dana Whitfield']);
    $job = app(OpenProject::class)->byHand($contact, $owner);

    $started = Appointment::factory()->for($contact)->for($job)->at(CarbonImmutable::parse('2026-11-05 10:00', 'America/Chicago'), 90)->create();
    $ahead = Appointment::factory()->for($contact)->for($job)->at(CarbonImmutable::parse('2026-11-06 10:00', 'America/Chicago'), 90)->create(['service_name' => 'Furnace repair']);
    Appointment::factory()->for($contact)->for($job)->cancelled()->at(CarbonImmutable::parse('2026-11-04 14:00', 'America/Chicago'), 60)->create(['service_name' => 'Duct cleaning']);

    return [ContactResource::getUrl('view', ['record' => $contact], isAbsolute: false), $started, $ahead];
}

/**
 * The contact page opened on its Appointments tab. The page lists its relation managers as tabs and draws one at a time;
 * Appointments is the second, so the address names it (?relation=1). The first test below walks to that tab by keyboard.
 */
function appointmentsKeyboardTabUrl(string $contactUrl): string
{
    return $contactUrl.'?relation=1';
}

/**
 * Press Tab until the element named $name has focus, within the cap, and return how many presses it took.
 */
function appointmentsKeyboardTabTo(Page $browser, string $name): int
{
    $presses = 0;

    while ($presses < APPOINTMENTS_KEYBOARD_TAB_CAP && focusedName($browser) !== $name) {
        pressKey($browser, 'Tab');
        $presses++;
    }

    expect(focusedName($browser))->toBe($name);

    return $presses;
}

/**
 * Wait until a confirmation dialog is open and has keyboard focus somewhere inside it.
 */
function appointmentsKeyboardAwaitDialog(Page $browser): void
{
    $browser->locator('.fi-modal-open[aria-modal="true"]:is([role="dialog"], [role="alertdialog"]) :focus')
        ->waitFor(['state' => 'attached']);
}

/**
 * Run axe with the WCAG 2.2 AA tags and the target-size rule on the Appointments tab and its open dialog, after every
 * transition on the page has finished: axe reads a colour mid-fade as a contrast fault. The rest of the contact screen
 * (the details list above the tabs) is not this tab's and is left to the Phase 11 owner-screen pass.
 *
 * @return array{checked: int, violations: list<array<string, mixed>>}
 */
function appointmentsKeyboardAxe(Page $browser): array
{
    $context = json_encode(['include' => [['.fi-resource-relation-manager'], ['.fi-modal-open']]], JSON_THROW_ON_ERROR);
    $options = json_encode([
        'runOnly' => ['type' => 'tag', 'values' => ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']],
        'rules' => ['target-size' => ['enabled' => true]],
    ], JSON_THROW_ON_ERROR);

    $report = $browser->evaluate(<<<JS
async () => {
  await Promise.all(document.getAnimations().map((animation) => animation.finished.catch(() => null)));
  const results = await window.axe.run({$context}, {$options});
  return {
    checked: results.passes.length + results.incomplete.length + results.violations.length,
    violations: results.violations.map(v => ({ id: v.id, impact: v.impact, help: v.help, targets: v.nodes.map(n => n.target) })),
  };
}
JS);

    return ['checked' => (int) ($report['checked'] ?? 0), 'violations' => $report['violations'] ?? []];
}

/**
 * Press Enter on the open confirmation's submit button, reached by Tab from wherever the dialog put focus.
 */
function appointmentsKeyboardConfirm(Page $browser, string $submitName): void
{
    appointmentsKeyboardAwaitDialog($browser);
    appointmentsKeyboardTabTo($browser, $submitName);
    pressKey($browser, 'Enter');
}

it('reaches the Appointments tab and Book by hand by Tab from the top of the contact page', function (bool $onPhone) {
    $owner = User::factory()->create(['name' => 'Ian Owner']);
    $this->actingAs($owner);

    [$url] = appointmentsKeyboardContactPage($owner);

    $page = $onPhone ? visit($url)->on()->mobile() : visit($url);

    // The contact's name is drawn with the page whatever happens to the tabs, so the walk starts on a loaded page.
    $page->assertSee('Dana Whitfield');
    $browser = $page->page();

    appointmentsKeyboardTabTo($browser, 'Appointments');
    pressKey($browser, 'Enter');

    $browser->getByRole('button', ['name' => 'Book by hand', 'exact' => true])->waitFor(['state' => 'attached']);

    appointmentsKeyboardTabTo($browser, 'Book by hand');

    expect(focusedName($browser))->toBe('Book by hand');
})->with([
    'on a desktop window' => [false],
    'on a phone, where the tab starts below the fold' => [true],
]);

it('names every row button for a screen reader in the accessibility tree', function () {
    $owner = User::factory()->create(['name' => 'Ian Owner']);
    $this->actingAs($owner);

    [$contactUrl] = appointmentsKeyboardContactPage($owner);

    $page = visit(appointmentsKeyboardTabUrl($contactUrl));
    $page->assertSee('Furnace repair');
    $browser = $page->page();

    foreach ([
        'Book by hand',
        'Mark done: AC repair, Thursday, November 5 at 10:00 AM',
        'Cancel visit: AC repair, Thursday, November 5 at 10:00 AM',
        'Edit note: AC repair, Thursday, November 5 at 10:00 AM',
        'Cancel visit: Furnace repair, Friday, November 6 at 10:00 AM',
        'Edit note: Furnace repair, Friday, November 6 at 10:00 AM',
    ] as $name) {
        expect($browser->getByRole('button', ['name' => $name, 'exact' => true])->count())->toBe(1, $name);
    }

    // Mark done waits for the start, and a cancelled visit offers nothing.
    expect($browser->getByRole('button', ['name' => 'Mark done: Furnace repair, Friday, November 6 at 10:00 AM', 'exact' => true])->count())->toBe(0)
        ->and($browser->getByRole('button', ['name' => 'Cancel visit: Duct cleaning, Wednesday, November 4 at 2:00 PM', 'exact' => true])->count())->toBe(0);
});

it('cancels a visit by keyboard and keeps focus on Book by hand', function () {
    $owner = User::factory()->create(['name' => 'Ian Owner']);
    $this->actingAs($owner);

    [$contactUrl, $started] = appointmentsKeyboardContactPage($owner);

    $page = visit(appointmentsKeyboardTabUrl($contactUrl));
    $page->assertSee('Furnace repair');
    $browser = $page->page();

    appointmentsKeyboardTabTo($browser, 'Cancel visit: AC repair, Thursday, November 5 at 10:00 AM');
    pressKey($browser, 'Enter');

    appointmentsKeyboardConfirm($browser, 'Cancel appointment');

    $browser->locator('tr:has-text("AC repair") .fi-badge:text-is("Cancelled")')->waitFor(['state' => 'attached']);

    expect($started->refresh()->state->value)->toBe('cancelled');

    expectFocusOn($browser, 'Book by hand');
});

it('marks a visit done by keyboard and keeps focus on Book by hand', function () {
    $owner = User::factory()->create(['name' => 'Ian Owner']);
    $this->actingAs($owner);

    [$contactUrl, $started] = appointmentsKeyboardContactPage($owner);

    $page = visit(appointmentsKeyboardTabUrl($contactUrl));
    $page->assertSee('Furnace repair');
    $browser = $page->page();

    appointmentsKeyboardTabTo($browser, 'Mark done: AC repair, Thursday, November 5 at 10:00 AM');
    pressKey($browser, 'Enter');

    appointmentsKeyboardConfirm($browser, 'Mark done');

    $browser->locator('tr:has-text("AC repair") .fi-badge:text-is("Done")')->waitFor(['state' => 'attached']);

    expect($started->refresh()->state->value)->toBe('done');

    expectFocusOn($browser, 'Book by hand');
});

it('closes the cancel dialog with Escape and gives focus back to the button that opened it', function () {
    $owner = User::factory()->create(['name' => 'Ian Owner']);
    $this->actingAs($owner);

    [$contactUrl, $started] = appointmentsKeyboardContactPage($owner);

    $page = visit(appointmentsKeyboardTabUrl($contactUrl));
    $page->assertSee('Furnace repair');
    $browser = $page->page();

    appointmentsKeyboardTabTo($browser, 'Cancel visit: AC repair, Thursday, November 5 at 10:00 AM');
    pressKey($browser, 'Enter');
    appointmentsKeyboardAwaitDialog($browser);

    pressKey($browser, 'Escape');

    expectFocusOn($browser, 'Cancel visit: AC repair, Thursday, November 5 at 10:00 AM');
    expect($browser->locator('.fi-modal-open')->count())->toBe(0)
        ->and($started->refresh()->isBooked())->toBeTrue();
});

it('finds no accessibility violation on the tab or in its open dialogs (D-43)', function (string $state) {
    $owner = User::factory()->create(['name' => 'Ian Owner']);
    $this->actingAs($owner);

    [$contactUrl] = appointmentsKeyboardContactPage($owner);

    $page = visit(appointmentsKeyboardTabUrl($contactUrl));
    $page->assertSee('Furnace repair');
    $browser = $page->page();

    if ($state !== 'tab') {
        $name = $state === 'cancel dialog'
            ? 'Cancel visit: AC repair, Thursday, November 5 at 10:00 AM'
            : 'Mark done: AC repair, Thursday, November 5 at 10:00 AM';

        $browser->getByRole('button', ['name' => $name, 'exact' => true])->press('Enter');
        appointmentsKeyboardAwaitDialog($browser);
        waitForOpenDialogIdle($browser);
    }

    RoomSectionAxe::assertClean(appointmentsKeyboardAxe($browser));
})->with(['tab', 'cancel dialog', 'mark done dialog']);
