<?php

require_once __DIR__.'/BookingPageHelpers.php';

use App\Actions\BookAppointment;
use App\Enums\AppointmentActor;
use App\Enums\AppointmentState;
use App\Models\Appointment;
use App\Models\ScheduleBlock;
use App\Models\User;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\RateLimiter;
use Pest\Browser\Playwright\Page;
use PHPUnit\Framework\ExpectationFailedException;
use Tests\Rooms\RoomSectionAxe;

/**
 * The booking page in a real browser (A11Y-02, Phase 8 criterion 6, D-43): keyboard order and reach, the time buttons'
 * names, where focus goes after each step, and the sizes of the controls, at desktop width and at 375 CSS px. Real
 * Chromium and real keys, because focus and the accessible name are what the browser makes of the markup, which a
 * Livewire test cannot see. axe runs on every state with the wcag2a, wcag2aa, wcag21aa and wcag22aa tags and the
 * target-size rule (pageAxeReport() in tests/Pest.php). Every wait is Playwright's own locator wait. They run in CI on
 * both databases with no skip.
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

/**
 * Every control the finger meets, as the browser drew it: a button, a summary or a link that is not inside a sentence,
 * with the name it is listed by and the box getBoundingClientRect() gives it. Hidden controls (a closed day's times,
 * the picker while the check step shows) are left out: a closed day's content still reports a box, so
 * checkVisibility() decides, not the size. A link inside a paragraph is an inline link
 * (the sms links in a sentence) and is exempt from the 48 px rule by WCAG 2.5.8's inline exception.
 *
 * @return list<array{name: string, left: float, top: float, right: float, bottom: float, width: float, height: float}>
 */
function bookingBrowserTargets(Page $browser): array
{
    $targets = $browser->evaluate(<<<'JS'
() => Array.from(document.querySelectorAll('button, summary, a[href]'))
  .filter((element) => !element.closest('p, dd, li > a') && element.checkVisibility())
  .map((element) => {
    const box = element.getBoundingClientRect();
    return {
      name: (element.id || element.textContent || element.tagName).replace(/\s+/g, ' ').trim(),
      left: box.left, top: box.top, right: box.right, bottom: box.bottom, width: box.width, height: box.height,
    };
  })
  .filter((target) => target.width > 0 && target.height > 0)
JS);

    return $targets;
}

/**
 * Fail unless every target is at least $minimum CSS px wide and high (WCAG 2.5.8 and the 48 px the booking page sets,
 * U-08). The failure names each control that is too small and the size the browser gave it.
 *
 * @param  list<array{name: string, width: float|int, height: float|int}>  $targets
 */
function bookingBrowserAssertTargetSize(array $targets, float $minimum = 48.0): void
{
    expect($targets)->not->toBeEmpty('no control was measured, so the page was empty or not styled');

    $tooSmall = array_values(array_filter($targets, fn (array $target): bool => $target['width'] < $minimum || $target['height'] < $minimum));

    expect($tooSmall)->toBeEmpty('Smaller than '.$minimum.' px: '.json_encode($tooSmall, JSON_UNESCAPED_SLASHES));
}

/**
 * The least distance between any two of the time buttons' boxes, in CSS px: 0 when two overlap or touch, otherwise the
 * gap between the nearer edges (horizontal, vertical, or the corner-to-corner distance for a diagonal pair).
 */
function bookingBrowserLeastGap(Page $browser): float
{
    $boxes = $browser->evaluate(<<<'JS'
() => Array.from(document.querySelectorAll('#booking-picker button[id^="booking-time-"]'))
  .filter((element) => element.checkVisibility())
  .map((element) => element.getBoundingClientRect())
  .filter((box) => box.width > 0 && box.height > 0)
  .map((box) => ({ left: box.left, top: box.top, right: box.right, bottom: box.bottom }))
JS);

    expect(count($boxes))->toBeGreaterThan(1);

    $least = INF;

    foreach ($boxes as $i => $a) {
        foreach (array_slice($boxes, $i + 1) as $b) {
            $across = max(0, max($a['left'], $b['left']) - min($a['right'], $b['right']));
            $down = max(0, max($a['top'], $b['top']) - min($a['bottom'], $b['bottom']));
            $least = min($least, sqrt($across ** 2 + $down ** 2));
        }
    }

    return $least;
}

/**
 * Tab from where focus is to 'Book this time', press Enter there, and wait for the first heading that follows.
 */
function bookingBrowserPickTime(Page $browser, string $name): void
{
    bookingBrowserTabTo($browser, $name);
    pressKey($browser, 'Enter');
    expectFocusOn($browser, 'Check your time');
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

    $failure = null;

    try {
        expectFocusOn($browser, 'Check your time');
    } catch (ExpectationFailedException $caught) {
        $failure = $caught;
    }

    expect($failure)->toBeInstanceOf(ExpectationFailedException::class)
        ->and($failure->getComparisonFailure()->getActual())->toBe('Stub');
});

it('books by keyboard at 375 wide', function () {
    $page = visit(bookingBrowserUrl())->resize(375, 812);
    $page->assertSee('Pick a time that works for you.');
    $browser = $page->page();

    bookingBrowserPickTime($browser, '10:00 AM, Tuesday, November 3');
    bookingBrowserTabTo($browser, 'Book this time');
    pressKey($browser, 'Enter');
    expectFocusOn($browser, 'You are booked');

    expect(Appointment::query()->sole()->booked_by)->toBe(AppointmentActor::Customer);
});

it('names every time with its day (U-10)', function () {
    $page = visit(bookingBrowserUrl());
    $page->assertSee('Pick a time that works for you.');
    $browser = $page->page();

    // Open every day so every time button is in the accessibility tree; a closed day's buttons are not.
    $browser->evaluate('() => document.querySelectorAll("#booking-picker details").forEach((day) => { day.open = true; })');

    $times = $browser->evaluate(<<<'JS'
() => Array.from(document.querySelectorAll('#booking-picker details')).flatMap((day) => {
  const label = day.querySelector('summary').innerText.trim();
  return Array.from(day.querySelectorAll('button')).map((button) => ({
    day: label,
    visible: button.querySelector('[aria-hidden="true"]').textContent,
  }));
})
JS);

    expect(count($times))->toBeGreaterThan(20);

    foreach ($times as $time) {
        $expected = normalizedName($time['visible']).', '.$time['day'];

        // The browser's own computed name, not the markup's text: exactly one button carries it, and it begins with the
        // visible text (Label in Name, WCAG 2.5.3).
        expect($browser->getByRole('button', ['name' => $expected, 'exact' => true])->count())->toBe(1, $expected)
            ->and(str_starts_with($expected, normalizedName($time['visible'])))->toBeTrue()
            // The raw text keeps its non-breaking space before AM or PM, so a time never breaks across two lines.
            ->and(preg_match('/\x{00A0}(AM|PM)$/u', $time['visible']))->toBe(1, $expected);
    }
});

it('orders the Tab stops by reading order', function () {
    $page = visit(bookingBrowserUrl());
    $page->assertSee('Pick a time that works for you.');
    $browser = $page->page();

    $days = $browser->evaluate('() => Array.from(document.querySelectorAll("#booking-picker summary")).map((summary) => summary.innerText.trim())');
    $stops = [];

    do {
        pressKey($browser, 'Tab');
        $stops[] = focusedName($browser);
    } while (end($stops) !== 'Accessibility statement' && count($stops) < 40);

    $firstDay = ['10:00 AM', '11:00 AM', '12:00 PM', '1:00 PM', '2:00 PM', '3:00 PM'];

    // The open day's summary, its times earliest first, then the next days' summaries (their times are closed), then
    // the footer's two disclosures.
    expect($stops)->toBe([
        $days[0],
        ...array_map(fn (string $time): string => $time.', '.$days[0], $firstDay),
        ...array_slice($days, 1),
        'Privacy notice',
        'Accessibility statement',
    ]);
});

it('moves focus to the taken line and keeps it described', function () {
    [$contact, $conversation, $job] = bookingPageSetUp();
    [, $token] = bookingPageMint($conversation);

    $page = visit(route('booking.show', $token, absolute: false));
    $page->assertSee('Pick a time that works for you.');
    $browser = $page->page();

    bookingBrowserPickTime($browser, '10:00 AM, Tuesday, November 3');

    app(BookAppointment::class)->byHand($contact, User::factory()->create(), 'ac-repair', bookingPageChicago('2026-11-03 10:00'), $job->id, null);

    bookingBrowserTabTo($browser, 'Book this time');
    pressKey($browser, 'Enter');
    expectFocusOn($browser, 'That time was just taken. These times are open now.');

    expect($browser->evaluate('() => document.querySelector("#booking-picker ul").getAttribute("aria-describedby")'))->toBe('booking-error')
        ->and($browser->evaluate('() => document.getElementById("booking-error").textContent.trim()'))->toContain('That time was just taken.');
});

it('returns focus to the tapped time with its day still open', function () {
    $page = visit(bookingBrowserUrl());
    $page->assertSee('Pick a time that works for you.');
    $browser = $page->page();

    bookingBrowserPickTime($browser, '11:00 AM, Tuesday, November 3');
    bookingBrowserTabTo($browser, 'Choose a different time');
    pressKey($browser, 'Enter');

    expectFocusOn($browser, '11:00 AM, Tuesday, November 3');
    expect($browser->evaluate('() => document.querySelector("#booking-picker details").open'))->toBeTrue();
});

it('shows the failure line when booking fails on the server', function () {
    [, $conversation] = bookingPageSetUp();
    [, $token] = bookingPageMint($conversation);
    Exceptions::fake();

    $this->mock(BookAppointment::class, fn ($mock) => $mock->shouldReceive('fromLink')->andThrow(new RuntimeException('connection refused at db-7')));

    $page = visit(route('booking.show', $token, absolute: false));
    $page->assertSee('Pick a time that works for you.');
    $browser = $page->page();

    bookingBrowserPickTime($browser, '10:00 AM, Tuesday, November 3');
    bookingBrowserTabTo($browser, 'Book this time');
    pressKey($browser, 'Enter');

    expectFocusOn($browser, 'Your time was not booked. Try again, or text Example Heating & Air at (312) 555-0147.');

    // The line sits above the buttons, and nothing of the exception reaches the page.
    expect($browser->evaluate('() => document.getElementById("booking-error").compareDocumentPosition(document.querySelector("section button")) & Node.DOCUMENT_POSITION_FOLLOWING'))->toBeGreaterThan(0)
        ->and($browser->evaluate('() => document.body.textContent.includes("connection refused")'))->toBeFalse()
        ->and(Appointment::query()->count())->toBe(0);
});

it('measures the smallest control at 375 and at desktop width', function (int $width) {
    $page = visit(bookingBrowserUrl())->resize($width, 812);
    $page->assertSee('Pick a time that works for you.');
    $browser = $page->page();

    bookingBrowserAssertTargetSize(bookingBrowserTargets($browser));
    expect(bookingBrowserLeastGap($browser))->toBeGreaterThanOrEqual(8.0);

    bookingBrowserPickTime($browser, '10:00 AM, Tuesday, November 3');

    bookingBrowserAssertTargetSize(bookingBrowserTargets($browser));
})->with([
    'at 375 CSS px' => [375],
    'at desktop width' => [1280],
]);

it('proves the size check can fail', function () {
    expect(fn () => bookingBrowserAssertTargetSize([['name' => 'stub', 'width' => 47.5, 'height' => 48.0]]))->toThrow(ExpectationFailedException::class)
        ->and(fn () => bookingBrowserAssertTargetSize([['name' => 'stub', 'width' => 48.0, 'height' => 47.5]]))->toThrow(ExpectationFailedException::class);

    bookingBrowserAssertTargetSize([['name' => 'stub', 'width' => 48.0, 'height' => 48.0]]);
});

it('finds no accessibility violation', function (string $state) {
    [, $conversation] = bookingPageSetUp();

    switch ($state) {
        case 'expired':
            [$link, $token] = bookingPageMint($conversation);
            $this->travelTo($link->expires_at->toImmutable()->addMinute());
            app()->forgetScopedInstances();

            break;
        case 'no times':
            [, $token] = bookingPageMint($conversation);
            ScheduleBlock::factory()->at(bookingPageChicago('2026-11-03 00:00'), 60 * 24 * 17)->create();

            break;
        default:
            [, $token] = bookingPageMint($conversation);
    }

    $page = visit(route('booking.show', $token, absolute: false));
    $browser = $page->page();

    if ($state === 'check step' || $state === 'booked') {
        bookingBrowserPickTime($browser, '10:00 AM, Tuesday, November 3');
    }

    if ($state === 'booked') {
        bookingBrowserTabTo($browser, 'Book this time');
        pressKey($browser, 'Enter');
        expectFocusOn($browser, 'You are booked');
    }

    RoomSectionAxe::assertClean(pageAxeReport($browser));
})->with(['picker', 'check step', 'booked', 'expired', 'no times']);

it('turns a footer disclosure\'s border to the accent on hover (UI review)', function () {
    $page = visit(bookingBrowserUrl());
    $page->assertSee('Pick a time that works for you.');
    $browser = $page->page();

    $footerBorders = fn (): array => $browser->evaluate('() => Array.from(document.querySelectorAll("footer details")).map((element) => getComputedStyle(element).borderTopColor)');

    $before = $footerBorders();

    $browser->locator('footer summary:has-text("Privacy notice")')->hover();

    $hovered = $footerBorders();

    $browser->locator('#booking-picker details > summary')->first()->hover();

    $day = $browser->evaluate('() => getComputedStyle(document.querySelector("#booking-picker details > summary")).borderTopColor');

    expect($before)->toHaveCount(2)
        ->and($hovered[0])->not->toBe($before[0])
        ->and($hovered[1])->toBe($before[1])
        ->and($day)->toBe($hovered[0]);
});

it('moves focus to the notice, then to Try again, then to the times (IN-05)', function () {
    [, $conversation] = bookingPageSetUp();
    [, $token] = bookingPageMint($conversation);

    $key = 'booking:'.hash('sha256', $token).':127.0.0.1';

    foreach (range(1, 20) as $hit) {
        RateLimiter::hit($key, 60);
    }

    $page = visit(route('booking.show', $token, absolute: false));
    $page->assertSee('Pick a time that works for you.');
    $browser = $page->page();

    bookingBrowserTabTo($browser, '10:00 AM, Tuesday, November 3');
    pressKey($browser, 'Enter');
    expectFocusOn($browser, 'Please wait a moment');

    pressKey($browser, 'Tab');
    expectFocusOn($browser, 'Try again');

    pressKey($browser, 'Enter');
    expectFocusOn($browser, 'Try again');
    expect($browser->evaluate('() => document.querySelector("h1").textContent.trim()'))->toBe('Please wait a moment');

    RateLimiter::clear($key);

    pressKey($browser, 'Enter');
    expectFocusOn($browser, 'Book AC repair');
    expect($browser->evaluate('() => document.body.textContent.includes("Please wait a moment")'))->toBeFalse();
});
