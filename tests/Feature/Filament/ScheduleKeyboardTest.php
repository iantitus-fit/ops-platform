<?php

use App\Actions\OpenProject;
use App\Models\Appointment;
use App\Models\Contact;
use App\Models\ScheduleBlock;
use App\Models\User;
use Carbon\CarbonImmutable;
use Pest\Browser\Playwright\Page;
use Tests\Rooms\RoomSectionAxe;

/**
 * The keyboard and screen reader walk of the Schedule page (D-43, U-33, WCAG 2.1.1, 2.4.3), as browser tests: Tab reaches
 * Block time from the top of the page, its dialog is filled and submitted by keyboard, a block row's Remove block and a
 * visit row's Cancel visit and Mark done work by keyboard, and once any of the three succeeds keyboard focus lands on the
 * page's Block time button, not on the page body (each makes its own row button leave the page, research Pattern 11). axe
 * finds nothing on the page, with rows and with the Block time dialog open. Real Chromium and real keys, because these
 * faults live in how the browser's focus follows Livewire's re-render, which a Livewire test cannot see. Every wait is
 * Playwright's own locator wait (the plugin's function and URL waits return at once in version 5.1). They run in CI on
 * both databases with no skip.
 */

/**
 * The most Tab presses a walk may take from the top of the Schedule page: the panel's own controls come first (sidebar,
 * top bar), then the page's Block time button, then the Show filter and the rows' buttons.
 */
const SCHEDULE_KEYBOARD_TAB_CAP = 50;

/**
 * Dana Whitfield with one job on the clock at Thursday 2026-11-05 10:30 in the shop's zone: AC repair that started at
 * 10:00 and is still booked, Furnace repair booked ahead for Friday 10:00, a cancelled visit from Wednesday, and a block
 * on Friday from 1:00 PM to 3:00 PM noted "Truck in the shop". The test calls visit() itself, because the Pest browser
 * plugin starts its browser only for a test whose own body calls it.
 *
 * @return array{0: Appointment, 1: Appointment, 2: ScheduleBlock}
 */
function scheduleKeyboardWorld(User $owner): array
{
    activateExampleFolder();
    test()->travelTo(CarbonImmutable::parse('2026-11-05 10:30', 'America/Chicago'));

    $contact = Contact::factory()->create(['name' => 'Dana Whitfield']);
    $job = app(OpenProject::class)->byHand($contact, $owner);

    $started = Appointment::factory()->for($contact)->for($job)->at(CarbonImmutable::parse('2026-11-05 10:00', 'America/Chicago'), 90)->create();
    $ahead = Appointment::factory()->for($contact)->for($job)->at(CarbonImmutable::parse('2026-11-06 10:00', 'America/Chicago'), 90)->create(['service_name' => 'Furnace repair']);
    Appointment::factory()->for($contact)->for($job)->cancelled()->at(CarbonImmutable::parse('2026-11-04 14:00', 'America/Chicago'), 60)->create(['service_name' => 'Duct cleaning']);
    $block = ScheduleBlock::factory()->at(CarbonImmutable::parse('2026-11-06 13:00', 'America/Chicago'), 120)->create(['note' => 'Truck in the shop', 'booked_by_user_id' => $owner->id]);

    return [$started, $ahead, $block];
}

/**
 * Press Tab until the element named $name has focus, within the cap, and return how many presses it took.
 */
function scheduleKeyboardTabTo(Page $browser, string $name): int
{
    $presses = 0;

    while ($presses < SCHEDULE_KEYBOARD_TAB_CAP && focusedName($browser) !== $name) {
        pressKey($browser, 'Tab');
        $presses++;
    }

    expect(focusedName($browser))->toBe($name);

    return $presses;
}

/**
 * Wait until a dialog is open and has keyboard focus somewhere inside it.
 */
function scheduleKeyboardAwaitDialog(Page $browser): void
{
    $browser->locator('.fi-modal-open[aria-modal="true"]:is([role="dialog"], [role="alertdialog"]) :focus')
        ->waitFor(['state' => 'attached']);
}

/**
 * Press Tab to the open dialog's submit button and press Enter on it.
 */
function scheduleKeyboardConfirm(Page $browser, string $submitName): void
{
    scheduleKeyboardAwaitDialog($browser);
    scheduleKeyboardTabTo($browser, $submitName);
    pressKey($browser, 'Enter');
}

/**
 * Type each character as its own key press on whatever has focus, the way a person does.
 */
function scheduleKeyboardType(Page $browser, string $text): void
{
    foreach (str_split($text) as $character) {
        pressKey($browser, $character);
    }
}

it('reaches Block time by Tab from the top of the page', function (bool $onPhone) {
    $owner = User::factory()->create(['name' => 'Ian Owner']);
    $this->actingAs($owner);

    scheduleKeyboardWorld($owner);

    $page = $onPhone ? visit('/admin/schedule')->on()->mobile() : visit('/admin/schedule');
    $page->assertSee('Furnace repair');
    $browser = $page->page();

    scheduleKeyboardTabTo($browser, 'Block time');

    expect(focusedName($browser))->toBe('Block time');
})->with([
    'on a desktop window' => [false],
    'on a phone' => [true],
]);

it('names every row button for a screen reader in the accessibility tree', function () {
    $owner = User::factory()->create(['name' => 'Ian Owner']);
    $this->actingAs($owner);

    scheduleKeyboardWorld($owner);

    $page = visit('/admin/schedule');
    $page->assertSee('Furnace repair');
    $browser = $page->page();

    foreach ([
        'Block time',
        'Mark done: AC repair, Thursday, November 5 at 10:00 AM',
        'Cancel visit: AC repair, Thursday, November 5 at 10:00 AM',
        'Edit note: AC repair, Thursday, November 5 at 10:00 AM',
        'Cancel visit: Furnace repair, Friday, November 6 at 10:00 AM',
        'Edit note: Furnace repair, Friday, November 6 at 10:00 AM',
        'Remove block: Friday, November 6, 1:00 PM to 3:00 PM',
    ] as $name) {
        expect($browser->getByRole('button', ['name' => $name, 'exact' => true])->count())->toBe(1, $name);
    }

    // A block never offers a visit's buttons, and a visit never offers Remove block.
    expect($browser->getByRole('button', ['name' => 'Mark done: Furnace repair, Friday, November 6 at 10:00 AM', 'exact' => true])->count())->toBe(0)
        ->and($browser->getByRole('button', ['name' => 'Cancel visit: Truck in the shop', 'exact' => false])->count())->toBe(0);
});

it('fills and submits the Block time dialog by keyboard', function () {
    $owner = User::factory()->create(['name' => 'Ian Owner']);
    $this->actingAs($owner);

    scheduleKeyboardWorld($owner);

    $page = visit('/admin/schedule');
    $page->assertSee('Furnace repair');
    $browser = $page->page();

    scheduleKeyboardTabTo($browser, 'Block time');
    pressKey($browser, 'Enter');
    scheduleKeyboardAwaitDialog($browser);

    // Focus is in the Starts field. Each segment takes its own digits: month, day and year, then Tab to the clock
    // (a year takes up to six digits, so it needs Tab to end it), then A or P for the half of the day.
    scheduleKeyboardType($browser, '11072026');
    pressKey($browser, 'Tab');
    scheduleKeyboardType($browser, '0900A');

    // The field's own calendar button is the next stop, then Ends.
    pressKey($browser, 'Tab');
    pressKey($browser, 'Tab');
    scheduleKeyboardType($browser, '11072026');
    pressKey($browser, 'Tab');
    scheduleKeyboardType($browser, '1200P');

    // The calendar button, then the Note box.
    pressKey($browser, 'Tab');
    pressKey($browser, 'Tab');
    scheduleKeyboardType($browser, 'Gutters');

    scheduleKeyboardTabTo($browser, 'Block time');
    pressKey($browser, 'Enter');

    $browser->locator('tr:has-text("Gutters") .fi-badge:text-is("Blocked")')->waitFor(['state' => 'attached']);

    $block = ScheduleBlock::query()->where('note', 'Gutters')->sole();

    expect($block->starts_at->utc()->format('Y-m-d H:i'))->toBe('2026-11-07 15:00')
        ->and($block->ends_at->utc()->format('Y-m-d H:i'))->toBe('2026-11-07 18:00');
});

it('removes a block by keyboard and keeps focus on Block time', function () {
    $owner = User::factory()->create(['name' => 'Ian Owner']);
    $this->actingAs($owner);

    [, , $block] = scheduleKeyboardWorld($owner);

    $page = visit('/admin/schedule');
    $page->assertSee('Truck in the shop');
    $browser = $page->page();

    scheduleKeyboardTabTo($browser, 'Remove block: Friday, November 6, 1:00 PM to 3:00 PM');
    pressKey($browser, 'Enter');

    scheduleKeyboardConfirm($browser, 'Remove block');

    $browser->locator('tr:has-text("Truck in the shop")')->waitFor(['state' => 'detached']);

    expect($block->refresh()->state->value)->toBe('cancelled');

    expectFocusOn($browser, 'Block time');
});

it('cancels a visit from the schedule by keyboard and keeps focus', function () {
    $owner = User::factory()->create(['name' => 'Ian Owner']);
    $this->actingAs($owner);

    [, $ahead] = scheduleKeyboardWorld($owner);

    $page = visit('/admin/schedule');
    $page->assertSee('Furnace repair');
    $browser = $page->page();

    scheduleKeyboardTabTo($browser, 'Cancel visit: Furnace repair, Friday, November 6 at 10:00 AM');
    pressKey($browser, 'Enter');

    scheduleKeyboardConfirm($browser, 'Cancel appointment');

    $browser->locator('tr:has-text("Furnace repair")')->waitFor(['state' => 'detached']);

    expect($ahead->refresh()->state->value)->toBe('cancelled');

    expectFocusOn($browser, 'Block time');

    // The row's badge reads Cancelled under the Cancelled filter.
    $browser->getByLabel('Show', true)->selectOption('cancelled');
    $browser->locator('tr:has-text("Furnace repair") .fi-badge:text-is("Cancelled")')->waitFor(['state' => 'attached']);
});

it('marks a visit done from the schedule by keyboard and keeps focus', function () {
    $owner = User::factory()->create(['name' => 'Ian Owner']);
    $this->actingAs($owner);

    [$started] = scheduleKeyboardWorld($owner);

    $page = visit('/admin/schedule');
    $page->assertSee('Furnace repair');
    $browser = $page->page();

    scheduleKeyboardTabTo($browser, 'Mark done: AC repair, Thursday, November 5 at 10:00 AM');
    pressKey($browser, 'Enter');

    scheduleKeyboardConfirm($browser, 'Mark done');

    $browser->locator('tr:has-text("AC repair")')->waitFor(['state' => 'detached']);

    expect($started->refresh()->state->value)->toBe('done');

    expectFocusOn($browser, 'Block time');
});

it('finds no accessibility violation on the page or in the Block time dialog (D-43)', function (bool $dialogOpen) {
    $owner = User::factory()->create(['name' => 'Ian Owner']);
    $this->actingAs($owner);

    scheduleKeyboardWorld($owner);

    $page = visit('/admin/schedule');
    $page->assertSee('Furnace repair');
    $browser = $page->page();

    if ($dialogOpen) {
        $browser->getByRole('button', ['name' => 'Block time', 'exact' => true])->press('Enter');
        scheduleKeyboardAwaitDialog($browser);
        $browser->evaluate('async () => { await Promise.all(document.getAnimations().map((animation) => animation.finished.catch(() => null))); }');
    }

    RoomSectionAxe::assertClean(pageAxeReport($browser));
})->with([
    'with its rows' => [false],
    'with the Block time dialog open' => [true],
]);
