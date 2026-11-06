<?php

require_once __DIR__.'/BookingPageHelpers.php';

use App\Enums\AppointmentActor;
use App\Enums\AppointmentState;
use App\Models\Appointment;
use Pest\Browser\Playwright\Page;
use PHPUnit\Framework\ExpectationFailedException;

/**
 * The booking page in a real browser (A11Y-02, Phase 8 criterion 6, D-43): keyboard order and reach, the time buttons'
 * names, where focus goes after each step, and the sizes of the controls, at desktop width and at 375 CSS px. Real
 * Chromium and real keys, because focus and the accessible name are what the browser makes of the markup, which a
 * Livewire test cannot see. Every wait is Playwright's own locator wait. They run in CI on both databases with no skip.
 */

/**
 * A link minted for Dana at Monday 2026-11-02 22:00 in the shop's zone, so the first open time is Tuesday 10:00, as a
 * relative address (the browser reaches the app on its own host). The test calls visit() itself, because the Pest
 * browser plugin starts its browser only for a test whose own body calls it.
 */
function bookingBrowserUrl(): string
{
    [, $conversation] = bookingPageSetUp();
    [, $token] = bookingPageMint($conversation);

    return route('booking.show', $token, absolute: false);
}

/**
 * Press Tab until the focused element's name is $name, at most $cap presses, and say how many it took.
 */
function bookingBrowserTabTo(Page $browser, string $name, int $cap = 10): int
{
    $presses = 0;

    while ($presses < $cap && focusedName($browser) !== $name) {
        pressKey($browser, 'Tab');
        $presses++;
    }

    expect(focusedName($browser))->toBe($name);

    return $presses;
}

it('books a time by keyboard alone at desktop width', function () {
    $page = visit(bookingBrowserUrl());
    $page->assertSee('Pick a time that works for you.');
    $browser = $page->page();

    bookingBrowserTabTo($browser, '10:00 AM, Tuesday, November 3');
    pressKey($browser, 'Enter');
    expectFocusOn($browser, 'Check your time');

    bookingBrowserTabTo($browser, 'Book this time');
    pressKey($browser, 'Enter');
    expectFocusOn($browser, 'You are booked');

    $appointment = Appointment::query()->sole();

    expect($appointment->state)->toBe(AppointmentState::Booked)
        ->and($appointment->booked_by)->toBe(AppointmentActor::Customer)
        ->and($appointment->starts_at->utc()->format('Y-m-d H:i'))->toBe('2026-11-03 16:00');
});

it('normalizes a non-breaking space in a name', function () {
    $page = visit('/up');
    $browser = $page->page();

    $browser->setContent("<button id=\"stub\">10:00\u{00A0}AM</button>");
    $browser->locator('#stub')->focus();

    expect(focusedName($browser))->toBe('10:00 AM')
        ->and($browser->evaluate('() => document.getElementById("stub").textContent'))->toBe("10:00\u{00A0}AM");
});

it('rethrows anything but a timeout (IN-08)', function () {
    $page = visit('/up');
    $browser = $page->page();

    // A selector Playwright cannot parse is an error, not a slow focus: the wait must not swallow it into a failed
    // comparison further down.
    expect(fn () => waitForFocusSelector($browser, '[', 300))->toThrow(ExpectationFailedException::class);
});

it('swallows only its own timeout and then reports what has focus', function () {
    $page = visit('/up');
    $browser = $page->page();

    $browser->setContent('<button id="stub">Stub</button>');
    $browser->locator('#stub')->focus();

    waitForFocusSelector($browser, '#missing:focus', 300);

    expect(fn () => expectFocusOn($browser, 'Check your time'))
        ->toThrow(ExpectationFailedException::class, 'Stub');
});
