<?php

use App\Actions\CloseProject;
use App\Actions\OpenProject;
use App\Filament\Resources\Contacts\ContactResource;
use App\Models\Contact;
use App\Models\Project;
use App\Models\User;
use Pest\Browser\Playwright\Page;

/**
 * The keyboard and screen reader walk of the contact screen's project list (UAT test 2, WINDOWS row 38), as browser
 * tests: Tab reaches the list from the top of the page (G-07-2.1, review IN-07), every button is found in the
 * accessibility tree by the name a screen reader announces (G-07-2.3), the New dialog takes focus and gives it back,
 * and after Close or Reopen is confirmed with the Enter key focus is still on that row's button, not on the page body
 * (G-07-2.2, WCAG 2.4.3). Real Chromium and real keys, because these faults live in how the browser's focus follows
 * Livewire's re-render, which a Livewire test cannot see. Every wait is Playwright's own (review IN-05). They run in CI
 * on both databases with no skip.
 */

/**
 * The submit button of the confirmation that is open now, inside the open modal window.
 */
const PROJECTS_KEYBOARD_CONFIRM = '.fi-modal-open .fi-modal-footer button[type="submit"]';

/**
 * The most Tab presses the walk may take from the top of the contact page to New job. The panel's own controls come
 * first (sidebar, top bar, the contact's details): 11 presses on a desktop window and 10 on a phone when this was
 * written, so the cap leaves room for a few more without letting the walk wander.
 */
const PROJECTS_KEYBOARD_TAB_CAP = 20;

/**
 * A contact with two projects and the address of its page. The test calls visit() itself, because the Pest browser
 * plugin starts its browser only for a test whose own body calls it.
 *
 * @return array{0: string, 1: Project}
 */
function projectsKeyboardContactPage(User $owner, bool $firstClosed): array
{
    activateExampleFolder();

    $contact = Contact::factory()->create();
    $first = app(OpenProject::class)->byHand($contact, $owner);
    app(OpenProject::class)->byHand($contact, $owner);

    if ($firstClosed) {
        app(CloseProject::class)->handle($first, $owner);
    }

    return [ContactResource::getUrl('view', ['record' => $contact], isAbsolute: false), $first];
}

/**
 * Press one key on whatever has focus now, the way a person does: down and up at the page, not on a chosen element.
 */
function projectsKeyboardPress(Page $browser, string $key): void
{
    $browser->keyDown($key);
    $browser->keyUp($key);
}

/**
 * What has keyboard focus now: its aria-label, else its visible text, else its tag name ("BODY" is focus fallen to the
 * page).
 */
function projectsKeyboardFocusedName(Page $browser): string
{
    return (string) $browser->evaluate(<<<'JS'
() => {
  const element = document.activeElement;
  return element.getAttribute('aria-label') ?? ((element.innerText ?? '').trim() || element.tagName);
}
JS);
}

/**
 * Wait, through Playwright's own locator waiting (its default timeout, 5 seconds), until the focused element is the one
 * named $name: by its aria-label, or by its exact text when it has none. When the wait runs out, the expectation names
 * what has focus instead, so a failure reads as the fault it found. The plugin's waitForFunction() is not used: in
 * pest-plugin-browser 5.1 it never reads Playwright's answer, so it returns at once without waiting.
 */
function projectsKeyboardExpectFocusOn(Page $browser, string $name): void
{
    $quoted = json_encode($name, JSON_THROW_ON_ERROR);

    try {
        $browser->locator("[aria-label={$quoted}]:focus, :focus:not([aria-label]):text-is({$quoted})")
            ->waitFor(['state' => 'attached']);
    } catch (Throwable) {
        // The expectation below reports what has focus.
    }

    expect(projectsKeyboardFocusedName($browser))->toBe($name);
}

/**
 * Press Enter on the open confirmation's submit button once Playwright sees it on the page.
 */
function projectsKeyboardConfirm(Page $browser): void
{
    $confirm = $browser->locator(PROJECTS_KEYBOARD_CONFIRM);
    $confirm->waitFor(['state' => 'visible']);
    $confirm->press('Enter');
}

it('reaches New job by Tab from the top of the contact page', function (bool $onPhone) {
    $owner = User::factory()->create(['name' => 'Ian Owner']);
    $this->actingAs($owner);

    [$url, $first] = projectsKeyboardContactPage($owner, firstClosed: false);

    $page = $onPhone ? visit($url)->on()->mobile() : visit($url);

    // The contact's name is drawn with the page whatever happens to the list, so the walk starts on a loaded page
    // without waiting for the list itself.
    $page->assertSee($first->contact->name);
    $browser = $page->page();

    $presses = 0;

    while ($presses < PROJECTS_KEYBOARD_TAB_CAP && projectsKeyboardFocusedName($browser) !== 'New job') {
        projectsKeyboardPress($browser, 'Tab');
        $presses++;
    }

    expect(projectsKeyboardFocusedName($browser))->toBe('New job');

    if (! $onPhone) {
        expect($browser->evaluate('() => window.scrollY'))->toBe(0);
    }
})->with([
    // On a desktop window the list sits in view, and the walk reaches New job without the page scrolling at all.
    'on a desktop window, without scrolling' => [false],
    // On a phone the list starts below the fold. A list drawn later than the page (G-07-2.1) loads only when it
    // scrolls into view, which a Tab walk never makes it do, so this is the case that fails if the list goes lazy
    // again. The test itself never scrolls; the browser moves the view to follow focus, as it does for a person.
    'on a phone, where the list starts below the fold' => [true],
]);

it('names every row button for a screen reader in the accessibility tree', function () {
    $owner = User::factory()->create(['name' => 'Ian Owner']);
    $this->actingAs($owner);

    [$url] = projectsKeyboardContactPage($owner, firstClosed: false);

    $page = visit($url);
    $page->assertSee('Job 2');
    $browser = $page->page();

    foreach (['New job', 'Close Job 1', 'Rename Job 1', 'Close Job 2', 'Rename Job 2'] as $name) {
        expect($browser->getByRole('button', ['name' => $name, 'exact' => true])->count())->toBe(1, $name);
    }
});

it('moves focus into the New job dialog and back to New job on Escape', function () {
    $owner = User::factory()->create(['name' => 'Ian Owner']);
    $this->actingAs($owner);

    [$url] = projectsKeyboardContactPage($owner, firstClosed: false);

    $page = visit($url);
    $page->assertSee('Job 2');
    $browser = $page->page();

    $browser->getByRole('button', ['name' => 'New job', 'exact' => true])->press('Enter');

    // New job asks first, so Filament draws it as an alertdialog, the role a confirmation carries.
    $browser->locator('.fi-modal-open[aria-modal="true"]:is([role="dialog"], [role="alertdialog"]) :focus')
        ->waitFor(['state' => 'attached']);

    projectsKeyboardPress($browser, 'Escape');

    projectsKeyboardExpectFocusOn($browser, 'New job');
    expect($browser->locator('.fi-modal-open')->count())->toBe(0);
});

it('keeps focus on the row\'s button through Close and Reopen confirmed by keyboard', function () {
    $owner = User::factory()->create(['name' => 'Ian Owner']);
    $this->actingAs($owner);

    [$url, $first] = projectsKeyboardContactPage($owner, firstClosed: false);

    $page = visit($url);
    $page->assertSee('Job 2');
    $browser = $page->page();

    $browser->getByRole('button', ['name' => 'Close Job 1', 'exact' => true])->press('Enter');
    projectsKeyboardConfirm($browser);

    projectsKeyboardExpectFocusOn($browser, 'Reopen Job 1');
    expect($first->refresh()->closed_at)->not->toBeNull();

    projectsKeyboardPress($browser, 'Enter');
    projectsKeyboardConfirm($browser);

    projectsKeyboardExpectFocusOn($browser, 'Close Job 1');
    expect($first->refresh()->closed_at)->toBeNull();
});

it('leaves focus on the row\'s Close after Reopen is confirmed by keyboard', function () {
    $owner = User::factory()->create(['name' => 'Ian Owner']);
    $this->actingAs($owner);

    [$url, $first] = projectsKeyboardContactPage($owner, firstClosed: true);

    $page = visit($url);
    $page->assertSee('Job 2');
    $browser = $page->page();

    $browser->getByRole('button', ['name' => 'Reopen Job 1', 'exact' => true])->press('Enter');
    projectsKeyboardConfirm($browser);

    projectsKeyboardExpectFocusOn($browser, 'Close Job 1');
    expect($first->refresh()->closed_at)->toBeNull();
});
