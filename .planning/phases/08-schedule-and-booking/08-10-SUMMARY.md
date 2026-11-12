---
phase: 08-schedule-and-booking
plan: 10
subsystem: testing
tags: [pest-browser, playwright, axe, wcag-2.2, keyboard, focus, target-size, livewire, tdd]

requires:
  - phase: 08-schedule-and-booking
    provides: the booking page (BookingPage, booking.show, the booking components), MintBookingLink, BookAppointment
provides:
  - BookingPageBrowserTest, 18 cases in real Chromium (keyboard walk at 1280 and 375, time names, Tab order, focus to the taken and failure lines and back to the tapped time, 48 px targets and 8 px gaps, axe on five states)
  - pressKey, focusedName, expectFocusOn, waitForFocusSelector and pageAxeReport in tests/Pest.php (one shared copy for every browser test)
  - A focus wait that swallows only its own Playwright timeout (07 IN-08)
  - A measured Tab cap for the dashboard walk (07 IN-09)
  - A time button whose accessible name is exactly "10:00 AM, Tuesday, November 3" as the browser computes it (U-10)
affects: [08-13, 08-14, phase-8-uat, phase-11-phone-design-pass, booking-page, owner-screens]

plan_head_before: 625c1049ba5e246bf3ebffe73ba7599207d773d0
plan_head_after: be9177fd2887533d30a9deab8a786e7eaa9aaf1a

actuals:
  tokens: 6640
  tasks: 3
  commits: 6

tech-stack:
  added: []
  patterns:
    - "Ask the browser for the name (getByRole with exact) instead of reading the markup: it found a stray space the markup hid"
    - "A wait helper split in two (waitForFocusSelector then the comparison) so the swallow-only-a-timeout rule can be proven on a bad selector"
    - "checkVisibility() decides what is on screen: a closed details still reports a box"
    - "A size check proven on a 47.5 px stub so it can fail"

key-files:
  created:
    - tests/Feature/Booking/BookingPageBrowserTest.php
  modified:
    - tests/Pest.php
    - tests/Feature/Filament/ProjectsRelationManagerKeyboardTest.php
    - tests/Feature/Rooms/LeadsBootTest.php
    - resources/views/components/booking/time.blade.php
    - docs/BLUEPRINT.md
    - docs/sprints/sprint-1.1.md
    - .planning/WINDOWS.md

key-decisions:
  - "The time button's visible time is aria-hidden and one visually hidden span holds the whole name ('10:00 AM, Tuesday, November 3'), because a block-level hidden span after the time made the browser read '10:00 AM , Tuesday, November 3' with a stray space; no aria-label, so Label in Name still holds"
  - "focusedName() builds the name from the element's content the way the browser does (hidden text in, display:none and aria-hidden out, block elements set off by spaces), not from innerText or textContent, so a wire:loading span that is display:none no longer pollutes a button's name"
  - "expectFocusOn() is built on waitForFocusSelector(Page, selector, timeoutMs), a second helper, so the narrowed catch can be proven with a selector Playwright cannot parse and a short timeout"
  - "LEADS_KEYBOARD_TAB_CAP is 23: 11 presses measured on a desktop window and 13 on a phone, the larger plus a margin of 10"

patterns-established:
  - "Browser tests wait with locator()->waitFor() only; pressKey, focusedName and expectFocusOn are the shared way to press and to check focus"
  - "pageAxeReport(Page) runs axe on a whole document; hand it to RoomSectionAxe::assertClean()"

requirements-completed: [A11Y-02]

coverage:
  - id: D1
    description: "pressKey, focusedName and expectFocusOn live once in tests/Pest.php; the focus wait swallows only its own timeout and rethrows anything else; the Projects and Leads keyboard tests call them"
    requirement: A11Y-02
    verification:
      - kind: automated_ui
        ref: "tests/Feature/Booking/BookingPageBrowserTest.php#rethrows anything but a timeout (IN-08)"
        status: pass
      - kind: automated_ui
        ref: "tests/Feature/Booking/BookingPageBrowserTest.php#swallows only its own timeout and then reports what has focus"
        status: pass
      - kind: automated_ui
        ref: "tests/Feature/Booking/BookingPageBrowserTest.php#normalizes a non-breaking space in a name"
        status: pass
      - kind: automated_ui
        ref: "php artisan test --compact tests/Feature/Filament/ProjectsRelationManagerKeyboardTest.php tests/Feature/Rooms/LeadsBootTest.php"
        status: pass
    human_judgment: false
  - id: D2
    description: "A keyboard user books a time on the real booking page at 1280 and at 375 wide: Tab reaches the time, Enter moves focus to 'Check your time', Enter on 'Book this time' books and moves focus to 'You are booked'"
    requirement: A11Y-02
    verification:
      - kind: automated_ui
        ref: "tests/Feature/Booking/BookingPageBrowserTest.php#books a time by keyboard alone at desktop width"
        status: pass
      - kind: automated_ui
        ref: "tests/Feature/Booking/BookingPageBrowserTest.php#books by keyboard at 375 wide"
        status: pass
    human_judgment: false
  - id: D3
    description: "Every time button's name read from the browser is '<time>, <weekday>, <month> <day>', begins with its visible text, and the raw time keeps a non-breaking space before AM or PM; the Tab order is the open day's summary, its times earliest first, the next days' summaries, then the footer's two disclosures"
    requirement: A11Y-02
    verification:
      - kind: automated_ui
        ref: "tests/Feature/Booking/BookingPageBrowserTest.php#names every time with its day (U-10)"
        status: pass
      - kind: automated_ui
        ref: "tests/Feature/Booking/BookingPageBrowserTest.php#orders the Tab stops by reading order"
        status: pass
    human_judgment: false
  - id: D4
    description: "Focus goes to the taken line (and the list is described by it), to the failure line above the buttons when booking fails on the server, and back to the tapped time with its day still open"
    requirement: A11Y-02
    verification:
      - kind: automated_ui
        ref: "tests/Feature/Booking/BookingPageBrowserTest.php#moves focus to the taken line and keeps it described"
        status: pass
      - kind: automated_ui
        ref: "tests/Feature/Booking/BookingPageBrowserTest.php#shows the failure line when booking fails on the server"
        status: pass
      - kind: automated_ui
        ref: "tests/Feature/Booking/BookingPageBrowserTest.php#returns focus to the tapped time with its day still open"
        status: pass
    human_judgment: false
  - id: D5
    description: "Every button and summary measures at least 48 by 48 CSS px by getBoundingClientRect at 375 and at desktop width, neighbouring time buttons are at least 8 px apart, and the size check fails a 47.5 px stub"
    requirement: A11Y-02
    verification:
      - kind: automated_ui
        ref: "tests/Feature/Booking/BookingPageBrowserTest.php#measures the smallest control at 375 and at desktop width"
        status: pass
      - kind: automated_ui
        ref: "tests/Feature/Booking/BookingPageBrowserTest.php#proves the size check can fail"
        status: pass
    human_judgment: false
  - id: D6
    description: "axe (wcag2a, wcag2aa, wcag21a, wcag21aa, wcag22aa, target-size) finds no violation on the picker, the check step, the booked view, an expired link and the no-times state"
    requirement: A11Y-02
    verification:
      - kind: automated_ui
        ref: "tests/Feature/Booking/BookingPageBrowserTest.php#finds no accessibility violation"
        status: pass
    human_judgment: false
  - id: D7
    description: "The dashboard walk's Tab cap is measured (11 desktop, 13 phone) and set to 23 with its reason in the docblock"
    verification:
      - kind: automated_ui
        ref: "tests/Feature/Rooms/LeadsBootTest.php#reaches each leads row by Tab in order and opens its contact with Enter"
        status: pass
    human_judgment: false
  - id: D8
    description: "The live keyboard-only and VoiceOver walk of the booking page, 200 percent zoom, and tap targets on a real phone (criterion 6)"
    verification: []
    human_judgment: true
    rationale: "A screen reader's speech, zoom reflow and a finger on glass are not asserted by any test; recorded as WINDOWS.md entry for Phase 8 UAT"
  - id: D9
    description: "The CI browser tests pass on both database legs against the styled page after Ian pushes the branch with 08-08's build step"
    verification: []
    human_judgment: true
    rationale: "Needs a push, which only Ian does; proven locally against the built assets in public/build"

duration: 21min
completed: 2026-11-06
status: complete
---

# Phase 8 Plan 10: The booking page in a real browser Summary

**The booking page's keyboard order, time names, focus moves, 48 px targets with 8 px gaps and axe pass in real Chromium at 1280 and 375 wide, on one shared key and focus helper that no longer hides errors, with Phase 7's three review items closed**

## Performance

- **Duration:** 21 min
- **Started:** 2026-11-06T15:55:00Z (approximate; the start time was not captured at the first command)
- **Completed:** 2026-11-06T16:16:00Z
- **Tasks:** 3
- **Files modified:** 8 (one created)

## Accomplishments

- A keyboard user books Tuesday 10:00 on the real page at desktop width and at 375: Tab reaches the time, Enter lands focus on "Check your time", Enter on "Book this time" lands it on "You are booked", and one appointment is booked by the customer.
- The browser found one real markup fault: each time button's name was "10:00 AM , Tuesday, November 3" (a stray space before the comma, because the hidden day span is drawn as a block). The visible time is now `aria-hidden` and one hidden span carries the whole name, so the name is exactly "10:00 AM, Tuesday, November 3" (U-10).
- `pressKey()`, `focusedName()`, `expectFocusOn()`, `waitForFocusSelector()` and `pageAxeReport()` live once in `tests/Pest.php`. The focus wait now catches only Playwright's own timeout (IN-08); a selector Playwright cannot parse fails the test, proven by a case. The Projects and Leads keyboard tests call the shared helpers with no assertion changed (IN-11).
- Target sizes are measured with `getBoundingClientRect()` on the styled page (every button and summary at least 48 by 48, time buttons at least 8 px apart, at 375 and 1280), and the size check is proven on a 47.5 px stub.
- axe is clean on the picker, check step, booked view, expired link and no-times state.
- The dashboard walk's Tab cap is measured: 11 presses on a desktop window, 13 on a phone; the cap is now 23 (13 plus a margin of 10), with the reason in its docblock (IN-09).

## TDD Evidence

**RED 1 (06d3d1a):** target `it books a time by keyboard alone at desktop width`, in BookingPageBrowserTest.php, run with `php artisan test --compact tests/Feature/Booking/BookingPageBrowserTest.php --log-junit=...`, exit 1, JUnit report. `gsd_run check tdd-red-evidence` returned `RED_EVIDENCE_OK` (`target_test_failed`). semanticAssessment: the target ran and errored with `Call to undefined function focusedName()`, the planned reason (the shared helpers did not exist yet). The other three cases failed the same way or on the missing `waitForFocusSelector()`.

**GREEN 1 (3477e51):** all four cases pass; the Projects and Leads keyboard files (12 cases) still pass on the shared helpers.

**RED 2 (a10ec08):** target `it finds no accessibility violation` (picker), same command, exit 1, `RED_EVIDENCE_OK`. semanticAssessment: the five axe cases errored with `Call to undefined function pageAxeReport()`, the planned reason. Honest note: the other 13 of the 18 cases (the 375 walk, names, Tab order, focus moves, sizes) were already green at this RED, because the view already met them once the time button's name was fixed in GREEN 1; they are tests that pin the behavior, not tests that drove new code. One case (the gap check) first failed on a bug in the test's own measuring (a closed `details` still reports a box), fixed in the test with `checkVisibility()` before the RED commit.

**GREEN 2 (a3c0805):** `pageAxeReport()` added; all 18 cases pass and axe is clean on every state without any markup change.

**Task 3 (7ccd78e):** a measurement commit, not a RED/GREEN pair (a constant and its docblock, with the existing walk as the test).

## Task Commits

1. **Task 1: Tracer: keyboard walk and shared helpers**
   - `06d3d1a` test: the booking page by keyboard in a real browser (RED)
   - `3477e51` feat: the booking page walked by keyboard; one shared key and focus helper (GREEN; includes the time.blade.php fix)
   - Tracer gate: `<verify>` re-run end to end (16 cases across the three files) and passed; logged "Tracer verified end-to-end, expanding".
2. **Task 2: The rest of criterion 6**
   - `a10ec08` test: names, focus, sizes and axe on every booking page state (RED)
   - `a3c0805` feat: the booking page passes criterion 6's automated checks (GREEN)
3. **Task 3: Tab cap measured, docs close the three items**
   - `7ccd78e` test: measure the dashboard walk's Tab cap (IN-09)
   - `be9177f` docs: close 07 IN-08, IN-09 and IN-11; record the booking page's browser checks

**Plan metadata:** the docs(08-10) completion commit follows this file.

## Files Created/Modified

- `tests/Feature/Booking/BookingPageBrowserTest.php` - 18 browser cases for the booking page
- `tests/Pest.php` - shared `pressKey`, `normalizedName`, `focusedName`, `waitForFocusSelector`, `expectFocusOn`, `pageAxeReport`
- `resources/views/components/booking/time.blade.php` - visible time `aria-hidden`, hidden span holds the full name
- `tests/Feature/Filament/ProjectsRelationManagerKeyboardTest.php` - own press, name and focus helpers removed, calls point at the shared ones
- `tests/Feature/Rooms/LeadsBootTest.php` - own press helper removed; `LEADS_KEYBOARD_TAB_CAP` is 23 with its measured docblock
- `docs/BLUEPRINT.md` - section 10: two Built bullets, the open upkeep item removed
- `docs/sprints/sprint-1.1.md` - walk-through line (plan 08-10)
- `.planning/WINDOWS.md` - two unrun-verify entries (60 and 61)

## Decisions Made

See `key-decisions` above. In short: the name fix keeps the hidden-text mechanism and avoids `aria-label`; `focusedName()` builds a content name the way the browser does; `expectFocusOn()` is split so the narrowed catch is provable; the Tab cap is 23.

## Measurement for IN-09

The dashboard walk from the top of `/admin` to the last of three leads rows took **11 Tab presses on a desktop window and 13 on a phone** (measured 2026-11-06 with a temporary, uncommitted test). Cap = 13 + 10 = 23.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Time button's accessible name had a stray space**
- **Found during:** Task 1 (the keyboard walk could not find "10:00 AM, Tuesday, November 3" in 10 Tab presses; the browser's name was "10:00 AM , Tuesday, November 3", confirmed with `getByRole(..., exact)` returning 0 for the plain name and 1 for the spaced one)
- **Issue:** the visually hidden day span (`position: absolute`, so drawn as a block) made the browser put a space before its leading comma
- **Fix:** the visible time is `<span aria-hidden="true">` and the hidden span holds `{{ $time }}, {{ $day }}`; still no `aria-label`, so Label in Name (2.5.3) holds. The existing page tests (which look for the time and the day inside the button) pass unchanged.
- **Files modified:** `resources/views/components/booking/time.blade.php`
- **Verification:** the walk, the names test and `tests/Feature/Booking` (259 cases) pass
- **Committed in:** 3477e51

**2. [Rule 1 - Bug] The planned focus-name helper would have read hidden text**
- **Found during:** Task 1 (the first cut read `textContent`, which includes the `display: none` "Booking your time" span inside the Book button, so the name was "Book this timeBooking your time")
- **Fix:** `focusedName()` builds the name from content the way the browser does (skips hidden and aria-hidden, sets block elements off by spaces) and returns the tag name for BODY
- **Files modified:** `tests/Pest.php`
- **Committed in:** 3477e51

---

**Total deviations:** 2 auto-fixed (both Rule 1)
**Impact on plan:** The first is exactly the markup fault the plan expected the browser to find. The second is a helper detail. No scope creep.

## Issues Encountered

- The shell's `grep` in this environment is a wrapper around ugrep, which reads the acceptance criterion `grep -q 'function pressKey(Page $browser, string $key): void'` as not matching. With POSIX `command grep` every grep criterion passes. The files are as the criteria ask.
- A closed `<details>` still reports a bounding box in Chromium, so the gap check first counted hidden buttons as overlapping; `checkVisibility()` fixed it in the test.

## Known Stubs

None.

## Threat Flags

None. The plan adds tests, one view tweak (aria-hidden on a visible time) and docs; no endpoint, auth path or schema.

## Unrun Hand Checks (recorded for Phase 8 UAT, never a pause)

Both are recorded in `.planning/WINDOWS.md` as `unrun-verify` entries:

- Entry 60: after Ian pushes the phase branch (with 08-08's build step), the CI browser tests pass on both the sqlite and pgsql legs against the styled page. Proven locally only (public/build exists on this machine).
- Entry 61: Phase 8 UAT on the booking page: a keyboard-only and VoiceOver walk, 200 percent zoom, and tap targets on a real phone (criterion 6, D-43); Phase 11 re-checks at 375 and 390.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- `pageAxeReport()` is ready for the owner-screen browser tests in 08-13 and 08-14.
- Before CI shows these green, Ian pushes the branch with 08-08's build step.
- Flagged assumption kept: a cut network is checked at UAT; a server-side failure backstops it in the browser.

## Self-Check: PASSED

Created files exist (`tests/Feature/Booking/BookingPageBrowserTest.php`); modified files exist; commits 06d3d1a, 3477e51, a10ec08, a3c0805, 7ccd78e and be9177f are ancestors of HEAD; `evaluation-scope` lists the plan's commits; `git rev-list --count` from the plan's base shows 6. Verification: `tests/Feature/Booking`, `LeadsBootTest` and `ProjectsRelationManagerKeyboardTest` pass (259 cases, none skipped); the full suite passes (2790 cases, 20228 assertions, SQLite).

---
*Phase: 08-schedule-and-booking*
*Completed: 2026-11-06*
