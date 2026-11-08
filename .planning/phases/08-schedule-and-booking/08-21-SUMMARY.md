---
phase: 08-schedule-and-booking
plan: 21
subsystem: ui
tags: [blade, tailwind, booking-page, privacy-notice, hover-border, pest, pest-browser, wcag]

requires:
  - phase: 08-schedule-and-booking
    provides: "08-20 (booking refusal reasons); the footer partial, the booking contract tests and the browser test file"
provides:
  - "A privacy notice that states only what the page does (WR-05)"
  - "Footer disclosures that turn their own border to the accent on hover (UI-FOOTER-HOVER)"
  - "A contract check that no hover border sits on an element without a border, on every customer state"
affects: [phase-08-uat, attorney-list, phase-11-phone-design-pass]

actuals:
  tokens: 4474
  tasks: 2
  commits: 4

plan_head_before: 9e4b60bff81820d378150c42d0eb1536027a959e
plan_head_after: 54ad876098a0aad976feb1a6ba856019992dbc02

tech-stack:
  added: []
  patterns:
    - "bookingContractHoverBorderWithoutBorder(): a bookingContract helper that parses a page with Dom\\HTMLDocument and returns the tag of every element holding hover:border-accent without border-2; a snippet case guards it against passing vacuously"
    - "A browser case reads getComputedStyle(...).borderTopColor before and after a Playwright locator hover()"

key-files:
  created: []
  modified:
    - resources/views/components/booking/footer.blade.php
    - tests/Feature/Booking/BookingPageTest.php
    - tests/Feature/Booking/BookingPageContractTest.php
    - tests/Feature/Booking/BookingPageBrowserTest.php
    - .planning/phases/08-schedule-and-booking/08-UI-SPEC.md
    - docs/BLUEPRINT.md

key-decisions:
  - "The privacy notice body is exactly: 'This page uses your link to book one visit. It does not ask you to type anything. Like every website, it receives the internet address of your device, and it uses that address to limit repeated tries.' The final wording stays on the attorney list, now with the facts (session cookie, session row keeps address and browser name, rate limiter counts by address)."
  - "The footer hover border moves from the borderless summary to the details element that carries border-2; no other class changed and no arbitrary value was added."

requirements-completed: [BOOK-02, A11Y-02]

coverage:
  - id: D1
    description: "WR-05: every booking page state's privacy disclosure body is the new sentence; no 'placeholder' and no 'phone number or address' in a customer-facing text"
    requirement: BOOK-02
    verification:
      - kind: integration
        ref: "tests/Feature/Booking/BookingPageTest.php#states only what the page does in its privacy notice (WR-05)"
        status: pass
    human_judgment: false
  - id: D2
    description: "UI-FOOTER-HOVER: hover:border-accent sits on each footer details element beside border-2; across every customer state no element holds the hover border without border-2"
    requirement: A11Y-02
    verification:
      - kind: integration
        ref: "tests/Feature/Booking/BookingPageContractTest.php#puts every hover border on an element that has a border (UI review)"
        status: pass
      - kind: integration
        ref: "tests/Feature/Booking/BookingPageContractTest.php#catches a hover border with no border"
        status: pass
    human_judgment: false
  - id: D3
    description: "Hovering the Privacy notice summary turns that details element's border to the accent colour a hovered day summary shows, and leaves the Accessibility statement's border as it was, in real Chromium"
    requirement: A11Y-02
    verification:
      - kind: automated_ui
        ref: "tests/Feature/Booking/BookingPageBrowserTest.php#turns a footer disclosure's border to the accent on hover (UI review)"
        status: pass
    human_judgment: false
  - id: D4
    description: "The final privacy and accessibility wording, and the live keyboard-only and VoiceOver walk of the page footer included"
    verification: []
    human_judgment: true
    rationale: "The wording is an attorney decision (D-44) and the live walk is Phase 8 UAT (WINDOWS entries 55 and 61); no test can assert either"

duration: 17min
completed: 2026-11-08
status: complete
---

# Phase 8 Plan 21: Footer privacy truth and hover border Summary

**The booking page's privacy notice now states only what the code does, and each footer disclosure turns its own border to the accent on hover, proven in Chromium and by a contract check over every customer state.**

## Performance

- **Duration:** 17 min
- **Started:** 2026-11-08T20:36:00Z (approximate; not captured at the first command)
- **Completed:** 2026-11-08T20:53:19Z
- **Tasks:** 2
- **Files modified:** 6

## Gaps resolved

- **WR-05 (08-REVIEW.md): resolved.** The new privacy sentence, exactly: "This page uses your link to book one visit. It does not ask you to type anything. Like every website, it receives the internet address of your device, and it uses that address to limit repeated tries." The sentence saying the wording stands in for the example business is gone, and so is the claim that the page asks for no name, phone number or address.
- **UI-FOOTER-HOVER (08-UI-REVIEW.md priority fix 1): resolved.** `hover:border-accent` now sits on both footer `details` elements, beside `border-2 border-line`; neither summary carries it.

## Accomplishments

- The footer privacy body is the new sentence on every state (the picker page and the does-not-work page are asserted; the contract run touches every state's footer, which is one partial).
- The attorney list in BLUEPRINT section 10 now carries the facts for the final wording: the page sets a session cookie, the database session row keeps the device's internet address and browser name for the session's life, and the rate limiter counts taps by address. A Built bullet records the new notice, with an "Affects:" line.
- 08-UI-SPEC.md: the Copywriting row is relabelled "Footer, privacy (interim wording until the attorney list, D-44)" with the new body; the U-15 footer section and the Color section's accent list gained their notes; Approval records the 2026-11-07 amendment by 08-21.
- A new contract check (`bookingContractHoverBorderWithoutBorder`) fails on any element with `hover:border-accent` and no `border-2`, on every state in `bookingContractEveryState()`; its snippet case proves it flags a borderless summary and passes a bordered details element, and the every-state case also asserts the page really contains hover borders, so it cannot pass on an empty page.
- A real-browser case reads the computed `border-top-color` of both footer disclosures, hovers the Privacy notice summary, and checks the first changed, the second did not, and the first day's hovered border has the same colour (the accent token).

## Task Commits

1. **Task 1 (tracer): the privacy notice says only what the page does (WR-05)**
   - RED `2c19330` (test): the exact-body case on two states
   - GREEN `fdd4e56` (fix): footer partial, 08-UI-SPEC, BLUEPRINT
2. **Task 2: the footer disclosures turn their border to the accent on hover (UI-FOOTER-HOVER)**
   - RED `3f84e40` (test): the every-state contract case, its snippet guard, the browser case
   - GREEN `54ad876` (fix): hover class moved to the details elements, 08-UI-SPEC Color list

**Plan metadata:** committed with this SUMMARY (docs: complete plan).

## TDD Gate Compliance

Both tasks followed RED then GREEN with the plan's commit messages; no REFACTOR commit was needed.

- **RED 1 (`2c19330`):** target `it states only what the page does in its privacy notice (WR-05)`, run with `php artisan test --compact tests/Feature/Booking/BookingPageTest.php --filter='privacy notice' --log-junit=...`, exit 1, JUnit report. Both dataset rows failed on the exact-body assertion (actual: the old placeholder sentence). `gsd_run check tdd-red-evidence` returned `RED_EVIDENCE_OK` / `target_test_failed`. Semantic assessment: the target executed and failed on the planned assertion for the intended reason (old wording), not on setup.
- **RED 2 (`3f84e40`):** targets `puts every hover border on an element that has a border (UI review)` and `turns a footer disclosure's border to the accent on hover (UI review)`, same runner with JUnit; the third new case (`catches a hover border with no border`) passed, as the plan expected (it guards the helper). Both targets returned `RED_EVIDENCE_OK` / `target_test_failed`. Semantic assessment: the contract case flagged exactly two `summary` elements (the footer's), and the browser case failed on `not->toBe` because the border colour did not change on hover (`oklch(0.553 0.013 58.071)` before and after).
- Notes on the classifier: Pest's JUnit target names carry the dataset suffix, so the dataset case needed its fully qualified name with the suffix; the evidence record had to sit inside the project directory, so I wrote it as a temporary file and deleted it. Nothing was committed.

## Verification

- Task 1 verify, `php artisan test --compact tests/Feature/Booking/BookingPageTest.php tests/Feature/Booking/BookingPageContractTest.php`: 54 tests, 54 passed, exit 0 (tracer verified end to end before Task 2 began).
- Task 2 / plan verify, the three booking files together: 75 tests, 75 passed, exit 0, run twice in a row, no skipped case (the result line lists only passed tests).
- Full suite, `php artisan test --compact`: 3113 tests, 3113 passed, exit 0.
- Acceptance greps: `placeholder` count in footer.blade.php is 0; `limit repeated tries` is in footer.blade.php and 08-UI-SPEC.md; `WR-05` and `session cookie` are in docs/BLUEPRINT.md; `summary class="...hover:border-accent` count is 0 and `hover:border-accent` count is 2 in footer.blade.php; `border-top-color` is in BookingPageBrowserTest.php; `08-21` is in 08-UI-SPEC.md.
- `vendor/bin/pint --dirty --format agent` passed after each task.

## Decisions Made

- The new privacy sentence is the plan's exact text (Ian's ruling 2026-11-07, D-43, D-44). The accessibility statement is unchanged, as the plan flagged.
- `npm run build` was not needed: `hover:border-accent` already existed in the built CSS through day.blade.php, and the browser case saw the class change.

## Deviations from Plan

None - plan executed exactly as written.

The existing cases in BookingPageTest, BookingPageContractTest and BookingPageBrowserTest all stayed as written and all pass; none was edited.

## Issues Encountered

- **Task 2 precondition met on the second run, not the first.** The first run of `BookingPageBrowserTest.php` before Task 2 failed two existing cases, `rethrows anything but a timeout (IN-08)` and `swallows only its own timeout and then reports what has focus` (tests/Feature/Booking/BookingPageBrowserTest.php, at their `$page->page()` line, about lines 168 and 178), with "Timeout 5000ms exceeded" from `Client.php:117`. A rerun of those two together failed the same way; each alone passed; the full file then passed 18 of 18 and passed again with my changes. The same kind of failure (three cases timing out at `$page->page()`) hit the first run after the Task 2 GREEN edit and then did not recur in two further runs of all three files, and the full suite passed with the browser cases included. This reads as a cold-start timeout of the first Chromium launches after a quiet spell, not a fault in this plan's changes; I did not touch those cases or the helper. It is worth knowing for CI: the first browser run after idle can time out in `->page()` and a rerun passes. I did not treat the precondition as unmet after the green full-file run.

## Known Stubs

None.

## Threat Flags

None. No new endpoint, auth path or schema; T-08-87 (a notice claiming what the code does not do) is mitigated by the exact-body test, and T-08-88 (a style change bringing an arbitrary value or raw colour in) by the existing contract scans, which stay green on every state.

## Hand checks carried

None new. The live keyboard-only and VoiceOver walk of the page, footer included, stays with Phase 8 UAT (WINDOWS entries 55 and 61, both already open). No WINDOWS entry was added.

## User Setup Required

None - no external service configuration required.

## Walk-through log

No new Laravel concept appeared in this plan, so no line was added to a sprint file. One PHP 8.5 item worth knowing: `Dom\HTMLDocument::createFromString()` is the new standards-based HTML parser, used in the new tests with `querySelector()` and `querySelectorAll()` so the checks read classes and text as a browser would.

## Next Phase Readiness

- WR-05 and UI-FOOTER-HOVER are closed. The attorney list carries the facts for the final privacy wording.
- Remaining Phase 8 work is the end-of-phase UAT (WINDOWS 55 and 61) and whatever later gap plans follow.

## Self-Check: PASSED

- Modified files exist on disk: footer.blade.php, the three test files, 08-UI-SPEC.md, BLUEPRINT.md.
- Commits `2c19330`, `fdd4e56`, `3f84e40`, `54ad876` are ancestors of HEAD; `git rev-list --count 9e4b60b..HEAD` before this commit was 4.

---
*Phase: 08-schedule-and-booking*
*Completed: 2026-11-08*
