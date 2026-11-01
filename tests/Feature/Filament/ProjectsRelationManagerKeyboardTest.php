<?php

use App\Actions\CloseProject;
use App\Actions\OpenProject;
use App\Filament\Resources\Contacts\ContactResource;
use App\Models\Contact;
use App\Models\Project;
use App\Models\User;

/**
 * The keyboard proof for WCAG 2.4.3 on the contact screen's project list (UAT gap G-07-2.2): after Close or Reopen is
 * confirmed with the Enter key, keyboard focus is still on that row's button, not on the page body. Real Chromium and
 * real Enter keys, because the fault lived in how the browser's focus follows Livewire's re-render, which a Livewire
 * test cannot see. Kept to two cases because each opens a real browser. They run in CI on both databases with no skip.
 */

/**
 * The submit button of the confirmation that is open now, inside the open modal window.
 */
const PROJECTS_KEYBOARD_CONFIRM = '.fi-modal-open .fi-modal-footer button[type="submit"]';

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
 * Wait, about two seconds at most, until the confirmation's submit button is on the page.
 */
function projectsKeyboardConfirmationOpen(object $page): bool
{
    $selector = json_encode(PROJECTS_KEYBOARD_CONFIRM, JSON_THROW_ON_ERROR);

    return (bool) $page->script(<<<JS
async () => {
  const deadline = performance.now() + 2000;
  while (performance.now() < deadline) {
    const button = document.querySelector({$selector});
    if (button && button.getClientRects().length > 0) { return true; }
    await new Promise((resolve) => requestAnimationFrame(resolve));
  }
  return false;
}
JS);
}

/**
 * Wait, about two seconds at most, until document.activeElement is the button named $label, and return what has focus
 * then: its aria-label, or its tag name when it has none ("BODY" is focus fallen to the page).
 */
function projectsKeyboardFocusedName(object $page, string $label): string
{
    $wanted = json_encode($label, JSON_THROW_ON_ERROR);

    return (string) $page->script(<<<JS
async () => {
  const name = () => document.activeElement.getAttribute('aria-label') ?? document.activeElement.tagName;
  const deadline = performance.now() + 2000;
  while (performance.now() < deadline) {
    if (name() === {$wanted}) { return name(); }
    await new Promise((resolve) => requestAnimationFrame(resolve));
  }
  return name();
}
JS);
}

it('leaves focus on the row\'s Reopen after Close is confirmed by keyboard', function () {
    $owner = User::factory()->create(['name' => 'Ian Owner']);
    $this->actingAs($owner);

    [$url, $first] = projectsKeyboardContactPage($owner, firstClosed: false);

    $page = visit($url);
    $page->assertSee('Job 2');

    $page->keys('[aria-label="Close Job 1"]', 'Enter');

    expect(projectsKeyboardConfirmationOpen($page))->toBeTrue();

    $page->keys(PROJECTS_KEYBOARD_CONFIRM, 'Enter');

    expect(projectsKeyboardFocusedName($page, 'Reopen Job 1'))->toBe('Reopen Job 1')
        ->and($first->refresh()->closed_at)->not->toBeNull();
});

it('leaves focus on the row\'s Close after Reopen is confirmed by keyboard', function () {
    $owner = User::factory()->create(['name' => 'Ian Owner']);
    $this->actingAs($owner);

    [$url, $first] = projectsKeyboardContactPage($owner, firstClosed: true);

    $page = visit($url);
    $page->assertSee('Job 2');

    $page->keys('[aria-label="Reopen Job 1"]', 'Enter');

    expect(projectsKeyboardConfirmationOpen($page))->toBeTrue();

    $page->keys(PROJECTS_KEYBOARD_CONFIRM, 'Enter');

    expect(projectsKeyboardFocusedName($page, 'Close Job 1'))->toBe('Close Job 1')
        ->and($first->refresh()->closed_at)->toBeNull();
});
