<?php

use App\Actions\AddContactByHand;
use App\Filament\Resources\Contacts\ContactResource;
use App\Models\Contact;
use App\Models\User;
use Pest\Browser\Playwright\Page;
use Tests\Rooms\RoomSectionAxe;

/**
 * The keyboard and screen reader walk of the New contact form and the automatic-texts switch (D-43, WCAG 2.1.1, 2.4.3,
 * 3.3.1), as browser tests: Tab reaches New contact on the contacts list and Enter opens the form; Name, Phone and Add
 * contact are reached by Tab; a matching phone puts its refusal beside Phone, tied to the field; on a contact who never
 * texted, Tab reaches Turn on automatic texts, Enter opens the dialog, and once it is confirmed focus stays on the same
 * button, now reading Turn off automatic texts. axe finds nothing on the create page, the contact screen, or the open
 * dialog. Real Chromium and real keys. Every wait is Playwright's own locator wait (the plugin's function and URL waits
 * return at once in version 5.1). They run in CI on both databases with no skip.
 */

/**
 * The most Tab presses a walk may take from the top of a page: the panel's own controls come first (sidebar, top bar),
 * then the page's own.
 */
const CONTACT_SCREEN_KEYBOARD_TAB_CAP = 50;

/**
 * Dana Whitfield (saved with +13125550147, so a matching phone has someone to name) and Sam Ortiz, added by hand, who has
 * never texted. Returns the address of the contacts list and of Sam's screen. The test calls visit() itself, because the
 * Pest browser plugin starts its browser only for a test whose own body calls it.
 *
 * @return array{0: string, 1: string}
 */
function contactScreenKeyboardWorld(): array
{
    activateExampleFolder();

    Contact::factory()->create(['name' => 'Dana Whitfield', 'phone_e164' => '+13125550147']);
    $sam = app(AddContactByHand::class)->handle('Sam Ortiz', '(312) 555-0188', null);

    return [
        ContactResource::getUrl('index', isAbsolute: false),
        ContactResource::getUrl('view', ['record' => $sam], isAbsolute: false),
    ];
}

/**
 * Press Tab until $reached says the focused element is the one wanted, within the cap.
 *
 * @param  callable(Page): bool  $reached
 */
function contactScreenKeyboardTabUntil(Page $browser, callable $reached, string $wanted): void
{
    $presses = 0;

    while ($presses < CONTACT_SCREEN_KEYBOARD_TAB_CAP && ! $reached($browser)) {
        pressKey($browser, 'Tab');
        $presses++;
    }

    expect($reached($browser))->toBeTrue("Tab never reached {$wanted}; focus is on ".focusedName($browser));
}

/**
 * Press Tab until the element named $name has focus.
 */
function contactScreenKeyboardTabTo(Page $browser, string $name): void
{
    contactScreenKeyboardTabUntil($browser, fn (Page $page): bool => focusedName($page) === $name, $name);
}

/**
 * Press Tab until the form field with this name has focus. Filament gives each field an id ending in its name.
 */
function contactScreenKeyboardTabToField(Page $browser, string $field): void
{
    contactScreenKeyboardTabUntil(
        $browser,
        fn (Page $page): bool => (bool) $page->evaluate("() => document.activeElement.id.endsWith('.{$field}') || document.activeElement.id === '{$field}'"),
        "the {$field} field",
    );
}

/**
 * Type each character as its own key press on whatever has focus, the way a person does.
 */
function contactScreenKeyboardType(Page $browser, string $text): void
{
    foreach (mb_str_split($text) as $character) {
        pressKey($browser, $character);
    }
}

/**
 * Wait until a dialog is open and has keyboard focus somewhere inside it.
 */
function contactScreenKeyboardAwaitDialog(Page $browser): void
{
    $browser->locator('.fi-modal-open[aria-modal="true"]:is([role="dialog"], [role="alertdialog"]) :focus')
        ->waitFor(['state' => 'attached']);
}

/**
 * Wait until the New contact form is on the page.
 */
function contactScreenKeyboardAwaitForm(Page $browser): void
{
    $browser->getByRole('button', ['name' => 'Add contact', 'exact' => true])->waitFor(['state' => 'attached']);
}

it('opens the New contact form by keyboard from the contacts list', function () {
    $this->actingAs(User::factory()->create(['name' => 'Ian Owner']));

    [$listUrl] = contactScreenKeyboardWorld();

    $page = visit($listUrl);
    $page->assertSee('Dana Whitfield');
    $browser = $page->page();

    contactScreenKeyboardTabTo($browser, 'New contact');
    pressKey($browser, 'Enter');
    contactScreenKeyboardAwaitForm($browser);

    contactScreenKeyboardTabToField($browser, 'name');
    contactScreenKeyboardTabToField($browser, 'phone');
    contactScreenKeyboardTabToField($browser, 'email');
    contactScreenKeyboardTabTo($browser, 'Add contact');

    expect(focusedName($browser))->toBe('Add contact');
});

it('adds a contact by keyboard and lands on the contact screen', function () {
    $this->actingAs(User::factory()->create(['name' => 'Ian Owner']));

    [$listUrl] = contactScreenKeyboardWorld();

    $page = visit(ContactResource::getUrl('create', isAbsolute: false));
    $page->assertSee('New contact');
    $browser = $page->page();

    contactScreenKeyboardTabToField($browser, 'name');
    contactScreenKeyboardType($browser, 'Pat Lee');
    contactScreenKeyboardTabToField($browser, 'phone');
    contactScreenKeyboardType($browser, '3125550166');
    contactScreenKeyboardTabTo($browser, 'Add contact');
    pressKey($browser, 'Enter');

    $browser->getByRole('button', ['name' => 'Turn on automatic texts', 'exact' => true])->waitFor(['state' => 'attached']);

    expect(Contact::query()->where('name', 'Pat Lee')->sole()->phone_e164)->toBe('+13125550166');
});

it('puts the refusal for a matching phone beside Phone, tied to the field', function () {
    $this->actingAs(User::factory()->create(['name' => 'Ian Owner']));

    contactScreenKeyboardWorld();

    $page = visit(ContactResource::getUrl('create', isAbsolute: false));
    $page->assertSee('New contact');
    $browser = $page->page();

    contactScreenKeyboardTabToField($browser, 'name');
    contactScreenKeyboardType($browser, 'Another Dana');
    contactScreenKeyboardTabToField($browser, 'phone');
    contactScreenKeyboardType($browser, '(312) 555-0147');
    contactScreenKeyboardTabTo($browser, 'Add contact');
    pressKey($browser, 'Enter');

    $refusal = 'This number is already saved as Dana Whitfield.';

    $browser->getByText($refusal, true)->waitFor(['state' => 'visible']);

    $tied = $browser->evaluate(<<<'JS'
() => {
  const input = Array.from(document.querySelectorAll('input')).find((field) => field.id.endsWith('.phone'));
  const describedBy = (input.getAttribute('aria-describedby') ?? '').split(/\s+/).filter(Boolean);
  const wrapper = input.closest('.fi-fo-field');
  return {
    invalid: input.getAttribute('aria-invalid'),
    describedByText: describedBy.map((id) => document.getElementById(id)?.textContent ?? '').join(' '),
    insideTheFieldsOwnBlock: wrapper !== null && wrapper.textContent.includes('This number is already saved as Dana Whitfield.'),
  };
}
JS);

    expect($tied['insideTheFieldsOwnBlock'])->toBeTrue()
        ->and($tied['invalid'])->toBe('true')
        ->and(normalizedName($tied['describedByText']))->toContain($refusal);

    expect(Contact::query()->count())->toBe(2);
});

it('turns automatic texts on by keyboard and keeps focus on the same button', function () {
    $this->actingAs(User::factory()->create(['name' => 'Ian Owner']));

    [, $samUrl] = contactScreenKeyboardWorld();

    $page = visit($samUrl);
    $page->assertSee('Sam Ortiz');
    $browser = $page->page();

    contactScreenKeyboardTabTo($browser, 'Turn on automatic texts');
    pressKey($browser, 'Enter');
    contactScreenKeyboardAwaitDialog($browser);

    expect($browser->getByText('Turn on automatic texts for Sam Ortiz?', true)->count())->toBe(1);

    contactScreenKeyboardTabTo($browser, 'Turn on');
    pressKey($browser, 'Enter');

    expectFocusOn($browser, 'Turn off automatic texts');

    expect(Contact::query()->where('name', 'Sam Ortiz')->sole()->automatic_texts_on_at)->not->toBeNull();
});

/**
 * Run axe with the WCAG 2.2 AA tags and the target-size rule on the whole page, except the contact details list's
 * placeholders ("(no email)", "(unknown)"), whose gray text fails color-contrast. That list is Phase 7's and 08-13 carried
 * its contrast to the Phase 11 owner-screen pass (STATE.md, 08-13); everything else on the page is checked, the new
 * Automatic texts and Next appointment lines and the switch's button and dialog included.
 *
 * @return array{checked: int, violations: list<array<string, mixed>>}
 */
function contactScreenKeyboardAxe(Page $browser): array
{
    $context = json_encode(['exclude' => [['.fi-in-placeholder']]], JSON_THROW_ON_ERROR);
    $options = json_encode([
        'runOnly' => ['type' => 'tag', 'values' => ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']],
        'rules' => ['target-size' => ['enabled' => true]],
    ], JSON_THROW_ON_ERROR);

    $report = $browser->evaluate(<<<JS
async () => {
  const results = await window.axe.run({$context}, {$options});
  return {
    checked: results.passes.length + results.incomplete.length + results.violations.length,
    violations: results.violations.map(v => ({ id: v.id, impact: v.impact, help: v.help, targets: v.nodes.map(n => n.target) })),
  };
}
JS);

    return ['checked' => (int) ($report['checked'] ?? 0), 'violations' => $report['violations'] ?? []];
}

it('finds no accessibility violation on the new contact page, the contact screen or its open dialog (D-43)', function (string $state) {
    $this->actingAs(User::factory()->create(['name' => 'Ian Owner']));

    [, $samUrl] = contactScreenKeyboardWorld();

    $page = visit($state === 'create page' ? ContactResource::getUrl('create', isAbsolute: false) : $samUrl);
    $page->assertSee($state === 'create page' ? 'New contact' : 'Sam Ortiz');
    $browser = $page->page();

    if ($state === 'dialog open') {
        $browser->getByRole('button', ['name' => 'Turn on automatic texts', 'exact' => true])->press('Enter');
        contactScreenKeyboardAwaitDialog($browser);
    }

    $browser->evaluate('async () => { await Promise.all(document.getAnimations().map((animation) => animation.finished.catch(() => null))); }');

    RoomSectionAxe::assertClean(contactScreenKeyboardAxe($browser));
})->with(['create page', 'contact screen', 'dialog open']);
