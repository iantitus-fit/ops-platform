---
phase: 08-schedule-and-booking
plan: 22
subsystem: ui
tags: [livewire, blade, rate-limiter, booking-page, focus, wcag-2.4.3, pest, pest-browser]
gap_closure: true
gap_ids: [IN-05]

requires:
  - phase: 08-schedule-and-booking
    provides: "08-20 (the withinLimit() limiter and the rateLimited flag); 08-21 (the footer and contract tests); the browser test helpers from 08-10"
provides:
  - "A Try again button under the in-page rate-limited notice (IN-05 resolved)"
  - "BookingPage::tryAgain() and the locked retried flag"
  - "A notice component with a focus prop and an action slot"
  - "Focus on the heading of whatever the page shows after a retry"
  - "UI-SPEC rows for the new button and the three new focus moves"
affects: [phase-08-uat, phase-11-phone-design-pass]

actuals:
  tokens: 8519
  tasks: 2
  commits: 4

plan_head_before: 4232ab34f6d0956e003fb9644252b635830bc339
plan_head_after: 63264b5090e25e755eabfc5469771f5c2790caac

tech-stack:
  added: []
  patterns:
    - "A control on a limited screen goes through the same RateLimiter::attempt() as the action it retries, so it cannot be used to get around the limit"
    - "Every state branch of the booking page carries a wire:key so Livewire's morph builds a fresh heading and its x-init focus runs"

key-files:
  created: []
  modified:
    - app/Livewire/BookingPage.php
    - resources/views/livewire/booking-page.blade.php
    - resources/views/components/booking/notice.blade.php
    - tests/Feature/Booking/BookingPageThrottleTest.php
    - tests/Feature/Booking/BookingPageBrowserTest.php
    - .planning/phases/08-schedule-and-booking/08-UI-SPEC.md
    - .planning/WINDOWS.md
    - docs/BLUEPRINT.md
    - docs/sprints/sprint-1.1.md

key-decisions:
  - "tryAgain() sets retried to the result of withinLimit(), so a press inside the minute counts nothing and keeps the notice, and the first press after it counts one attempt and clears the flag"
  - "The picker heading takes focus after a retry only when no time is being checked; the check step's own h2 already takes focus, and two focus moves in one render would race"
  - "Every state branch of the page gets a wire:key; without it Livewire reuses the previous branch's h1 and the new heading's x-init never runs"

patterns-established:
  - "Focus after a limited retry: notice heading on appearance, Try again next by Tab, then the heading of what comes back"

requirements-completed: [BOOK-02, A11Y-02]

coverage:
  - id: D1
    description: "IN-05: the in-page rate-limited notice has a Try again button; pressed inside the minute it keeps the notice and adds no attempt, pressed after the minute it brings back the times with no reload"
    requirement: "BOOK-02"
    verification:
      - kind: integration
        ref: "tests/Feature/Booking/BookingPageThrottleTest.php#offers Try again while limited and brings the times back after the minute (IN-05)"
        status: pass
      - kind: integration
        ref: "tests/Feature/Booking/BookingPageThrottleTest.php#keeps Try again from letting a limited address tap (T-08-89)"
        status: pass
    human_judgment: false
  - id: D2
    description: "WCAG 2.4.3: focus moves to the notice heading when it appears, Tab reaches Try again, still-limited press leaves focus on Try again, a successful press lands focus on the heading of what the page shows"
    requirement: "A11Y-02"
    verification:
      - kind: automated_ui
        ref: "tests/Feature/Booking/BookingPageBrowserTest.php#moves focus to the notice, then to Try again, then to the times (IN-05)"
        status: pass
      - kind: integration
        ref: "tests/Feature/Booking/BookingPageThrottleTest.php#puts focus on the heading of what changed while the page waited (IN-05)"
        status: pass
      - kind: integration
        ref: "tests/Feature/Booking/BookingPageThrottleTest.php#returns to the check step when a time was being checked"
        status: pass
    human_judgment: false
  - id: D3
    description: "The 429 page a GET can return is unchanged: no button, the heading takes no focus"
    requirement: "BOOK-02"
    verification:
      - kind: integration
        ref: "tests/Feature/Booking/BookingPageThrottleTest.php#keeps the 429 page as it was"
        status: pass
    human_judgment: false
  - id: D4
    description: "The keyboard-only and VoiceOver walk of the rate-limited notice, with the announcements and the feel of the focus moves"
    verification: []
    human_judgment: true
    rationale: "A screen reader's announcements and the feel of the focus order are not asserted by any test; recorded as WINDOWS entry 78 for Phase 8 UAT"

duration: 17min
completed: 2026-11-08
status: complete
---

# Phase 8 Plan 22: Try again on the rate-limited notice Summary

**A "Try again" button under the booking page's in-page "Please wait a moment" notice, routed through the same per-link, per-address limiter as every tap, with keyboard focus carried from the notice heading to the button to the heading of whatever the page shows next.**

## Performance

- **Duration:** about 17 min
- **Started:** 2026-11-08T21:35Z (approximate; the start time was not captured at the first step)
- **Completed:** 2026-11-08T21:55Z
- **Tasks:** 2 (Task 1 a tracer)
- **Files modified:** 9

## Gap closed

- **IN-05: resolved.** The #[Locked] rateLimited flag used to stay true until a reload and the notice had nothing to press. It now has a button, `tryAgain()` clears the flag once the minute is up, and the 429 page is left as it was.

## New customer string

- **"Try again"** (the button label). No exclamation point and no em dash. It is the only new string a customer reads; it is recorded in 08-UI-SPEC.md's Rate limited row and U-19.

## Accomplishments

- `BookingPage::tryAgain()` sets `retried` to the result of `withinLimit()`. `RateLimiter::attempt()` refuses without counting while the minute is not up, so five presses while limited left the attempts at 20 (T-08-89), and after 61 seconds one press counted exactly one attempt.
- `#[Locked] public bool $retried`, reset by `choose()`, `back()` and `book()`, passed to the view; a browser cannot set it (a test proves the refusal).
- The notice component takes a `focus` prop (tabindex -1 and `x-init="$el.focus()"` on the h1 when true) and an optional `action` slot under the line. The in-page notice passes focus and holds the secondary full-width-on-phones Try again button; the 429 page passes neither and is byte-for-byte what it was.
- After a retry the heading of what the page shows takes focus once: "Book AC repair" on the picker, "Check your time" when a time was being checked (its existing focus), "You are booked", the cancelled and done notices, and every dead-end notice.
- 08-UI-SPEC.md: Rate limited rows in the Booking Page Contract and the Copywriting Contract, U-19, the focus and announcement map (three new rows), and an Approval note for the 08-22 amendment.

## How the browser case tripped the limit

By `RateLimiter::hit()`, as the plan's first choice. The test hits the page's key (`'booking:'.hash('sha256', $token).':127.0.0.1'`) 20 times before `visit()`, then the first keyboard tap on a time is the 21st and shows the notice. The browser run's address is 127.0.0.1 (the notice appeared on that first tap and the test then passed through every focus step), so the fallback of 21 keyboard taps was not needed. Steps proven in real Chromium: focus on "Please wait a moment", Tab to "Try again", Enter inside the minute leaves the notice and focus on "Try again", `RateLimiter::clear()`, Enter brings the picker with focus on "Book AC repair". The browser case ran and passed; none is skipped (waits are the existing `expectFocusOn()` locator waits, not `waitForFunction()` or `waitForURL()`).

## Task Commits

1. **Task 1 RED:** `6ac7996` (test) the rate-limited notice can be retried without a reload
2. **Task 1 GREEN:** `318df29` (fix) Try again brings the times back after the minute, with focus kept (IN-05)
3. **Task 2 RED:** `497400d` (test) a retry puts focus on the heading of what the page now shows
4. **Task 2 GREEN:** `63264b5` (fix) a retry puts focus on the heading of what the page now shows (IN-05)

**Plan metadata:** the docs commit that follows this file (SUMMARY, STATE, ROADMAP, REQUIREMENTS).

## TDD Gate Compliance

RED then GREEN, in order, for both tasks (`test(08-22)` precedes `fix(08-22)` each time; the commit messages are the ones the plan names, so the gate's `feat(08-22)` pattern does not match: the commit type is `fix` by the plan's own choice). No refactor commit.

- **Task 1 RED** (run before the first commit): of the four target cases three failed and one guard passed. "offers Try again..." failed at BookingPageThrottleTest.php line 106 on the planned assertion (no button with the text Try again: 0 is not 1). "keeps Try again from letting a limited address tap" errored with "Public method [tryAgain] not found on component". The browser case failed in `expectFocusOn()` with BODY where the notice heading was expected (no focus attributes). The "keeps the 429 page as it was" case passed in RED by design: it guards the unchanged page.
- **Task 2 RED:** the four dataset cases (booked, cancelled, done, expired) failed at BookingPageThrottleTest.php line 198, "Failed asserting that false is true", on the planned assertion that the heading carries tabindex="-1" and x-init after a retry. "returns to the check step when a time was being checked" passed in RED because the check step's h2 already carried its focus attributes; it is a guard for the picker-heading exclusion, not a target test.
- **Semantic assessment:** each target test executed and failed on the planned assertion for the intended reason; no zero-test, fixture, syntax or setup fault. The `check tdd-red-evidence` classifier was not run: it reads TAP, JUnit, swift-testing and unittest reports, and Pest's JSON summary is none of those, so the evidence is the unmodified Pest output quoted above plus this assessment.

## Files Created/Modified

- `app/Livewire/BookingPage.php` - `tryAgain()`, the locked `retried` flag, `retried` passed to the view
- `resources/views/livewire/booking-page.blade.php` - the Try again button in the notice's action slot; focus on the picker, booked, cancelled, done and dead-end headings after a retry; a wire:key on each state branch
- `resources/views/components/booking/notice.blade.php` - the `focus` prop and the `action` slot
- `tests/Feature/Booking/BookingPageThrottleTest.php` - six new cases (one a four-row dataset)
- `tests/Feature/Booking/BookingPageBrowserTest.php` - the focus-order case in Chromium
- `.planning/phases/08-schedule-and-booking/08-UI-SPEC.md` - Try again in the contract, U-19, the focus map, Approval
- `.planning/WINDOWS.md` - entry 78
- `docs/BLUEPRINT.md` - one Built line (safety and compliance)
- `docs/sprints/sprint-1.1.md` - one walk-through line (plan 08-22)

## Decisions Made

- The picker heading takes focus after a retry only when no time is being checked (`$retried && ! $checking`). With a time chosen the check step's own h2 takes focus, and two x-init focus calls in one render would race. The Task 1 Livewire case therefore reaches the picker by tapping 20 times, calling `back()`, then tapping the 21st; the 21 `choose()` calls are all with an offered time and the limit trips as the plan describes.
- Try again is the secondary button variant, as the plan says, full width on phones like the check step's buttons.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] wire:key on every state branch so the new heading is a new element**
- **Found during:** Task 1 (the browser case)
- **Issue:** with the plan's markup the browser case passed every step up to the last, then focus fell to BODY after a successful Try again. Livewire's morph reused the previous branch's `div` and `h1` in place, patched the attributes, and never ran `x-init` on the reused element.
- **Fix:** `wire:key="rate-limited"` on the notice (through `$attributes` on the notice root) and `wire:key="picker"` on the picker root in Task 1; in Task 2 a key on the booked root and on each remaining dead-end notice. This is the same morph behavior the sprint 1.1 walk-through log already records for plan 07-09, so no new log line.
- **Files modified:** booking-page.blade.php, notice.blade.php (the root takes `$attributes->class('space-y-6')`; with no attributes the 429 page's markup is unchanged)
- **Verification:** the browser case passes; the Task 2 markup cases pass; all 446 Booking tests pass
- **Committed in:** 318df29 and 63264b5

**2. [Rule 2 - Missing critical] BLUEPRINT Built line and a walk-through log line**
- **Found during:** Task 2
- **Issue:** the plan names "Affects: customer communication, safety and compliance" but not docs/BLUEPRINT.md or the walk-through log, and CLAUDE.md requires BLUEPRINT to be updated in the same commit as a change that touches an area, and a log line for a Laravel concept first met in a plan (a limited action that cannot get around its limit, and a named slot).
- **Fix:** one Built bullet under section 10 and one line in docs/sprints/sprint-1.1.md marked `(plan 08-22)`.
- **Files modified:** docs/BLUEPRINT.md, docs/sprints/sprint-1.1.md
- **Committed in:** 63264b5

**3. [Additions beyond the plan's named cases]**
- A T-08-89 case (five Try again presses while limited leave attempts at 20, and `retried` refuses a browser's set), and cancelled and done rows in the Task 2 dataset next to booked and expired, because the implementation covers those headings and the plan's threat register mitigates T-08-89 by assertion. All pass.

---

**Total deviations:** 3 (1 Rule 1, 1 Rule 2, 1 additive test coverage)
**Impact on plan:** none on scope. The wire:key fix was needed for the plan's own focus requirement to hold in a real browser. The plan's stop rule did not trip: 'limits the taps inside the page (research Pitfall 1)', the GET throttle case and every existing case in BookingPageTest, BookingPageContractTest and BookingPageBrowserTest pass unedited.

## Issues Encountered

- Test results: `php artisan test --compact tests/Feature/Booking` passed 446 of 446 with no skipped case; the full `php artisan test --compact` passed 3122 of 3122 (22029 assertions). The one warning in the summary line is the same single warning present in the browser file's baseline run before any change.
- `grep -q 'public bool $retried = false;'` returned false under BSD grep (the `$` in the pattern); the check passes with `grep -qF`. No code issue.

## Carried hand check

- **Phase 8 UAT, the rate-limited notice by keyboard and VoiceOver:** the heading is read, Tab reaches Try again, the times return after the minute with focus on their heading. Not run (hand checks are never a pause). Recorded as **WINDOWS entry 78** (`unrun-verify`, open), joined to the walks of entries 55 and 61. CI's browser legs run BookingPageBrowserTest after Ian pushes.

## Known Stubs

None.

## Threat Flags

None. No new endpoint, auth path or schema; `tryAgain()` is one more Livewire method behind the existing limiter (T-08-89 mitigated and asserted, T-08-90 mitigated in Chromium and in markup per state, T-08-SC accepted, nothing installed).

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Plan 22 closes IN-05, the last open gap in this plan's scope; the Phase 8 UAT walk (entries 55, 61, 75 and now 78) is what remains by hand.

## Self-Check: PASSED

- Files: BookingPage.php, booking-page.blade.php, notice.blade.php, both test files, 08-UI-SPEC.md, WINDOWS.md (entry 78), BLUEPRINT.md and sprint-1.1.md all present and modified.
- Commits 6ac7996, 318df29, 497400d, 63264b5 are ancestors of HEAD; `git rev-list --count` from the plan's start HEAD gives 4.
- Acceptance greps for both tasks pass; `grep -c 'Try again' resources/views/booking/rate-limited.blade.php` prints 0.

---
*Phase: 08-schedule-and-booking*
*Completed: 2026-11-08*
