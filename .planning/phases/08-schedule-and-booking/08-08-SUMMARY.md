---
phase: 08-schedule-and-booking
plan: 08
subsystem: ui
tags: [livewire, booking-page, customer-layout, tailwind-tokens, rate-limit, wcag, pest]

requires:
  - phase: 08-schedule-and-booking
    provides: "ResolveBookingLink and BookAppointment::fromLink() (08-07), Availability::offers() (08-05), BookingSettings dayText/timeText (08-04), MintBookingLink (08-07)"
provides:
  - "App\\Livewire\\BookingPage: the full-page component for /book/{token} (picker, check, book, every dead end), with Locked token, chosen time, flags and version"
  - "The customer layout and eleven anonymous Blade components (day, time, button, notice, error, footer, sms, three inline SVG icons) over UI-SPEC colour and type tokens in app.css"
  - "Route booking.show, the booking limiter (30 a minute per IP), the in-component limit (20 a minute per token hash and IP), KeepBookingLinkPrivate (noindex, no-referrer) and the rate-limited view"
  - "BookingPageContractTest: the UI-SPEC contract checks as a file scan, plus shared test helpers"
  - "A Build the front end step in tests.yml (Ian pushes it)"
affects: [08-09, 08-10, 08-11, 08-12, booking-page, customer-pages, phase-11-design]

actuals:
  tokens: 20000
  tasks: 3
  commits: 5
plan_head_before: 453e9703958b9ea8d391737e0bd5b6e30bd6c9c6
plan_head_after: 96d9cc3cddfa54020aadf46996af4d64a165f00c

tech-stack:
  added: []
  patterns:
    - "A customer layout with a guarded Vite include; token-only Tailwind utilities declared once in @theme inline"
    - "Livewire page whose every browser-visible value is #[Locked] and whose taps are limited inside the component, not only on the route"
    - "The picker stays in the DOM, hidden, while the check step shows, so native details open state and focus return survive"
    - "Test helpers in one required file (BookingPageHelpers.php) so each booking test file runs alone"

key-files:
  created:
    - app/Livewire/BookingPage.php
    - app/Http/Middleware/KeepBookingLinkPrivate.php
    - resources/views/layouts/customer.blade.php
    - resources/views/livewire/booking-page.blade.php
    - resources/views/booking/rate-limited.blade.php
    - resources/views/components/booking/day.blade.php
    - resources/views/components/booking/time.blade.php
    - resources/views/components/booking/button.blade.php
    - resources/views/components/booking/notice.blade.php
    - resources/views/components/booking/error.blade.php
    - resources/views/components/booking/footer.blade.php
    - resources/views/components/booking/sms.blade.php
    - resources/views/components/booking/icon/chevron.blade.php
    - resources/views/components/booking/icon/check.blade.php
    - resources/views/components/booking/icon/exclamation-circle.blade.php
    - tests/Feature/Booking/BookingPageTest.php
    - tests/Feature/Booking/BookingPageThrottleTest.php
    - tests/Feature/Booking/BookingPageContractTest.php
    - tests/Feature/Booking/BookingPageHelpers.php
  modified:
    - resources/css/app.css
    - routes/web.php
    - app/Providers/AppServiceProvider.php
    - app/Support/PhoneNumber.php
    - .github/workflows/tests.yml
    - docs/BLUEPRINT.md
    - docs/sprints/sprint-1.1.md

key-decisions:
  - "The picker is drawn once and hidden (the hidden attribute) while a time is being checked, with a wire:key holding pickerVersion; a taken time bumps the version so the earliest day opens again."
  - "Each day's details element carries wire:ignore.self so a day the customer opened stays open when the component re-renders."
  - "A booking refusal is told apart by its message (taken, link closed, anything else), because BookingRefused has no code; taken gives fresh times, link closed just redraws, anything else shows the failed line."
  - "KeepBookingLinkPrivate is listed before throttle:booking so the 429 response carries the private headers too."
  - "No active folder version gives a 404 on the page and on the 429 view; there is no business name to show."

patterns-established:
  - "Customer strings contain no exclamation point or em dash, proven by rendering every state and scanning its text"
  - "A sentence ending in a component-made link needs that component file to end with no newline, or a stray space shows before the full stop"

requirements-completed: [BOOK-02, A11Y-02]

coverage:
  - id: D1
    description: "A customer opens a booking link, sees the offered times under an open earliest day, taps one, checks it, taps Book this time and ends on You are booked with a confirmed appointment, typing nothing"
    requirement: BOOK-02
    verification:
      - kind: integration
        ref: "tests/Feature/Booking/BookingPageTest.php#opens the picker with the earliest day open"
        status: pass
      - kind: integration
        ref: "tests/Feature/Booking/BookingPageTest.php#books after a check"
        status: pass
      - kind: integration
        ref: "tests/Feature/Booking/BookingPageTest.php#goes back to the picker"
        status: pass
    human_judgment: false
  - id: D2
    description: "Every state has one h1 and one plain line with a one-tap text link; a booked link never offers times again; the page shows nothing personal"
    requirement: BOOK-02
    verification:
      - kind: integration
        ref: "tests/Feature/Booking/BookingPageTest.php#shows each dead end with one h1 and one line"
        status: pass
      - kind: integration
        ref: "tests/Feature/Booking/BookingPageTest.php#shows the visit after booking, never times (U-14)"
        status: pass
      - kind: integration
        ref: "tests/Feature/Booking/BookingPageTest.php#never shows the customer's name or phone in any state (D-13)"
        status: pass
    human_judgment: false
  - id: D3
    description: "A time taken meanwhile gives fresh times and a focusable error line tied by aria-describedby; any other failure keeps the check step with a fixed line and reports the exception"
    requirement: BOOK-02
    verification:
      - kind: integration
        ref: "tests/Feature/Booking/BookingPageTest.php#offers fresh times when the time was just taken (D-07)"
        status: pass
      - kind: integration
        ref: "tests/Feature/Booking/BookingPageTest.php#keeps the check step when booking fails another way (T-08-37)"
        status: pass
    human_judgment: false
  - id: D4
    description: "The browser cannot steer the page: Locked properties are refused, an unoffered time is refused, the GET and the in-page taps are both limited with a plain notice, and the private headers are sent"
    requirement: BOOK-02
    verification:
      - kind: integration
        ref: "tests/Feature/Booking/BookingPageTest.php#refuses a browser that changes what the server set (Locked)"
        status: pass
      - kind: integration
        ref: "tests/Feature/Booking/BookingPageTest.php#refuses a time the server did not offer (T-08-32)"
        status: pass
      - kind: integration
        ref: "tests/Feature/Booking/BookingPageThrottleTest.php#limits the taps inside the page (research Pitfall 1)"
        status: pass
      - kind: integration
        ref: "tests/Feature/Booking/BookingPageThrottleTest.php#limits the page GET (D-11)"
        status: pass
      - kind: integration
        ref: "tests/Feature/Booking/BookingPageTest.php#sends the private headers and the no-script line (U-16, U-17)"
        status: pass
    human_judgment: false
  - id: D5
    description: "The customer views meet the UI-SPEC contract checks: no style, hex or raw palette, three sizes and two weights, no arbitrary values, no heroicon, no exclamation point or em dash, the number read from the folder"
    requirement: A11Y-02
    verification:
      - kind: unit
        ref: "tests/Feature/Booking/BookingPageContractTest.php (9 cases, including the bad-snippet companion)"
        status: pass
    human_judgment: false
  - id: D6
    description: "The page meets WCAG 2.2 AA when used: focus lands on the right heading, Choose a different time returns focus to the tapped time, a day stays open across a re-render, 48 px targets, 200 percent zoom, VoiceOver reading"
    requirement: A11Y-02
    verification: []
    human_judgment: true
    rationale: "The browser-side behavior (Alpine focus, wire:ignore.self, wire:loading) is not run by Pest feature tests; browser tests are plan 08-10 and the keyboard, VoiceOver, zoom and target-size walk is Phase 8 UAT (D-43)."

duration: 17min
completed: 2026-11-05
status: complete
---

# Phase 8 Plan 08: The booking page Summary

**The platform's first customer page: a Livewire booking page with native day disclosures, a check step, a single book through BookAppointment::fromLink(), a plain line for every dead end, limits on the GET and on every tap, locked state and private headers, held to a scanned UI-SPEC contract.**

## Performance

- **Duration:** 17 min
- **Started:** 2026-11-05T21:36:16Z
- **Completed:** 2026-11-05T21:53:00Z
- **Tasks:** 3
- **Files modified:** 26 (19 created, 7 modified)

## Accomplishments

- BOOK-02 end to end: a link opens a styled phone-first page, a tap and a check end in a confirmed appointment on the "You are booked" view, with nothing typed and the only write going through `BookAppointment::fromLink()`.
- Every state a customer can reach (does not work, replaced, expired, service unavailable, no times, booked, cancelled, done, rate limited, taken, failed) has one h1 and one plain line with a one-tap `sms:` link whose number comes from `profile.phone` through `ActiveConfig`.
- The browser cannot steer the page: token, chosen time, flags and version are `#[Locked]`; every tapped time is re-checked by `Availability::offers()` and again under the booking lock; the GET is limited at 30 a minute per IP and every tap and book at 20 a minute per token hash and IP, over either of which the page says "Please wait a moment".
- UI-SPEC contract held by a file scan and a render-every-state scan, with a companion that proves each rule fires on a bad snippet. CI gains `npm run build` for plan 08-10's browser tests.

## Task Commits

1. **Task 1 (tracer): a customer opens, picks, checks and books**
   - RED `731f134` (test)
   - GREEN `8fa2593` (feat)
2. **Task 2: every state, the failures and the limits**
   - RED `71160d6` (test)
   - GREEN `0850116` (feat)
3. **Task 3: the contract scan, the CI front-end build and the docs** - `96d9cc3` (feat)

**Plan metadata:** the docs(08-08) commit that adds this SUMMARY, STATE, ROADMAP and WINDOWS.

## Files Created/Modified

- `app/Livewire/BookingPage.php` - the page: choose(), back(), book(), the in-component limit, render data and titles
- `app/Http/Middleware/KeepBookingLinkPrivate.php` - X-Robots-Tag and Referrer-Policy on the GET
- `app/Providers/AppServiceProvider.php` - the `booking` limiter with a custom 429 view
- `app/Support/PhoneNumber.php` - `national()` for "(312) 555-0147"
- `resources/css/app.css` - UI-SPEC `@theme inline` tokens and the `@layer base` focus ring and summary marker
- `resources/views/layouts/customer.blade.php`, `livewire/booking-page.blade.php`, `booking/rate-limited.blade.php`, `components/booking/*` - the page, its states and parts
- `tests/Feature/Booking/BookingPage{,Throttle,Contract}Test.php`, `BookingPageHelpers.php` - 40 cases and the shared helpers
- `.github/workflows/tests.yml` - Build the front end step. **Ian pushes this file.**
- `docs/BLUEPRINT.md`, `docs/sprints/sprint-1.1.md` - sections 1 and 10 Built bullets, the walk-through line

## Decisions Made

See key-decisions above. The notable one: the picker stays in the DOM, hidden, during the check step (research Pitfall 6), so open days and focus return need no server bookkeeping.

## TDD Gate Compliance

All three tasks have RED commits where RED applied. RED evidence is the Pest JSON summary line; the repository's runner does not emit a format the `check tdd-red-evidence` classifier reads, so the semantic assessment stands in, as in 08-06 and 08-07.

- **Task 1 RED (731f134):** 3 of 3 failed: `Route [booking.show] not defined` and `Unable to find component: [App\Livewire\BookingPage]`. Semantic assessment: the planned behavior is absent; no syntax or fixture fault. GREEN 8fa2593.
- **Task 2 RED (71160d6):** 29 cases, 11 passed and 18 failed. The 18 failed on the planned behavior (no notice states: h1 count 0 instead of 1; no taken or failed error line; no X-Robots-Tag header; no busy button markup; the 31st GET was 200, not 429; the 21st tap was not limited). The 11 that passed were Task 1's three, the five Locked-property cases (Task 1's `#[Locked]` already refused them), the 404 case, the never-shows-personal-data case and the double Book case, all satisfied by Task 1. GREEN 0850116.
- **Task 3:** the contract test passed on first run against Tasks 1 and 2's views (no view broke a rule), so it has no separate RED commit; it was committed with the CI step and docs as one `feat` commit rather than a `test` then `feat` pair. It is not vacuous: a mutation (an exclamation point added to the notice view) failed the render scan, and the bad-snippet dataset proves each file rule fires.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Shared test helpers moved to their own file**
- **Found during:** Task 3
- **Issue:** BookingPageThrottleTest and BookingPageContractTest used helpers defined in BookingPageTest.php, so either failed when run alone. `tests/Pest.php` could not take them (the plan says this plan only adds test files).
- **Fix:** New `tests/Feature/Booking/BookingPageHelpers.php`, loaded by `require_once` from each of the three test files.
- **Files modified:** the three test files, the new helpers file
- **Committed in:** 96d9cc3

**2. [Rule 2 - Missing critical] `PhoneNumber::national()` and a `booking/sms` component added**
- **Found during:** Task 1
- **Issue:** The booked view and every dead end need "(312) 555-0147" as an `sms:` link; nothing formatted a number, and the link would otherwise be repeated about ten times.
- **Fix:** `App\Support\PhoneNumber::national()` (libphonenumber, falls back to the raw value) and `components/booking/sms.blade.php`. The component file has no trailing newline on purpose, or a stray space shows before the full stop that follows it.
- **Files modified:** app/Support/PhoneNumber.php, resources/views/components/booking/sms.blade.php
- **Committed in:** 8fa2593

**3. [Rule 2 - Missing critical] 404 when no folder version is live**
- **Found during:** Task 2
- **Issue:** With no active version the page (and the 429 view) have no business name to show and `ActiveConfig` throws.
- **Fix:** both answer 404; one test.
- **Committed in:** 0850116

**4. [Rule 3 - Blocking] The unknown-versus-revoked comparison compares the page body, not the whole document**
- **Found during:** Task 2 GREEN
- **Issue:** Livewire injects its scripts only on the first full-page response in a process, so two GETs in one test differed outside the page.
- **Fix:** the test compares the title and the main element with Livewire ids removed.
- **Committed in:** 0850116

---

**Total deviations:** 4 auto-fixed (2 blocking, 2 missing critical), plus the Task 3 single-commit note under TDD Gate Compliance.
**Impact on plan:** all additive; no scope creep. Files outside the plan's list: `PhoneNumber.php`, `booking/sms.blade.php`, `BookingPageHelpers.php`.

## Issues Encountered

- A leftover `public/hot` file on this machine (a Vite dev server was left running) makes the layout load from the dev server instead of the build; the tests are unaffected. CI has neither file until its new build step runs.

## Known Stubs

None. The privacy notice and accessibility statement bodies are placeholder wording by design (U-15, D-44) and wait for the attorney list; they are not a data stub.

## Threat Flags

None beyond the plan's threat model; T-08-32 to T-08-37 are each mitigated and tested (Locked and re-check, both limits, private headers and meta, nothing personal shown, Blade escaping with no raw output, fixed failure line with the exception reported).

## Hand Checks Left For UAT (recorded in .planning/WINDOWS.md, entries 54 to 56)

- After Ian pushes the phase branch (with the tests.yml build step), the CI legs are green and the build step ran before the tests. Not run here.
- Phase 8 UAT: keyboard-only and VoiceOver walk of the booking page, 200 percent zoom and the 48 px targets on a phone (criterion 6, D-43).
- Browser-side behavior no feature test can run (Alpine focus moves, focus return to the tapped time, `wire:loading` setting `aria-disabled`, a day staying open across a re-render) is for plan 08-10's browser tests.

## User Setup Required

None - no external service configuration required. Ian pushes `.github/workflows/tests.yml`.

## Next Phase Readiness

Plan 08-09 can append the link to the texts; 08-10 can measure a styled page in CI once the build step is pushed. No blockers.

## Self-Check: PASSED

- All 19 created files present on disk; commits 731f134, 8fa2593, 71160d6, 0850116, 96d9cc3 reachable from HEAD.
- `php artisan test --compact tests/Feature/Booking`: 177 passed. `php artisan test --compact`: 2718 passed. `npm run build` exits 0. Pint clean.

---
*Phase: 08-schedule-and-booking*
*Completed: 2026-11-05*
