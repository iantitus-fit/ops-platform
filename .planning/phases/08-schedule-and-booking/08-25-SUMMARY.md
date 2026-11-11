---
phase: 08-schedule-and-booking
plan: 25
subsystem: front-desk
tags: [visit-line, held-draft, approve-step, livewire, owner-path, pest, accessibility]
gap_closure: true
gap_ids: [G-08-4]
status: complete

requires:
  - phase: 08-schedule-and-booking
    provides: "08-24 (VisitAskedAbout, the rendered visit line, the sync check of visit_line), D-45 (the booking link box on a held draft, the pattern this plan copies)"
provides:
  - "A held draft for a contact with a visit booked ahead shows the owner a visit line box on the approve step; a checked box adds the owner's visit line after the words at send time, on the owner path (G-08-4 held-draft half)"
  - "VisitAskedAbout::whyNoLine(): the reason a visit line cannot ride now, shared by the screen and the send"
  - "SendOwnerReply::visitLineFor(), visitLineStartsOn() and sendDraft()'s visit line branch with its length refusal and left-off reason"
  - "meta.visit_line_appointment_id or meta.visit_line_left_off on the sent draft row, beside resolved_by"
  - "08-UI-SPEC U-37 with its Copywriting rows and section 6"
affects: [phase-08-uat]

actuals:
  tokens: 14700
  tasks: 3
  commits: 5

plan_head_before: 1c4679b2b7d87b31ace97f50ad66ade5e2f8ba8e
plan_head_after: cebc04a9cfe12b5a09d505fb958df50a9b7bb11a

tech-stack:
  added: []
  patterns:
    - "A second approve-step box that copies the booking link box's rules: a #[Locked] line filled by the server at every render, a checkbox that only asks, conditions re-checked at approval, a refusal rather than a dropped line past 1,600 characters"
    - "One room class (VisitAskedAbout) answers 'can a line ride' for both the screen and the send, as ServiceToBook does for the link"

key-files:
  created:
    - tests/Feature/Booking/ApprovedDraftVisitLineTest.php
    - tests/Feature/Filament/DraftVisitLineKeyboardTest.php
  modified:
    - app/Rooms/ScheduleAndBooking/VisitAskedAbout.php
    - app/Actions/SendOwnerReply.php
    - app/Actions/ResolveDraft.php
    - app/Livewire/ConversationThread.php
    - resources/views/livewire/conversation-thread.blade.php
    - docs/BLUEPRINT.md
    - .planning/phases/08-schedule-and-booking/08-UI-SPEC.md
    - .planning/phases/08-schedule-and-booking/deferred-items.md

key-decisions:
  - "The visit line on a held draft is settled as D-45 settles the link: the owner's approval decides, the box only asks, every condition is checked again at approval, and a line is added only when it was on the owner's screen"
  - "No mint, no new message kind, no agent action: an approved draft is the owner's send through OutboundSender::sendExisting() on the owner origin, recorded with resolved_by (02.1 D-49 governs Automated sends only)"
  - "A condition that fails at approval sends the draft without the line and records meta.visit_line_left_off; a length overrun refuses Send and keeps the draft live rather than dropping the line"

patterns-established:
  - "Pest global helpers in a new test file carry their own prefix (approvedVisitLine) and never rely on helpers from another test file, including the booking link test's resync helper"

requirements-completed: [BOOK-03]

coverage:
  - id: D1
    description: "On a held draft for a booked contact, about scheduling by label or by the stored reading's intent, the approve step shows one checkbox labeled 'The visit line will be added: {line}' and no booking link box; Send with the box checked sends the owner's words, one space, the visit line, then the sign-off through the owner path; the line is never written into the draft; the row records meta.visit_line_appointment_id, resolution and resolved_by"
    requirement: "BOOK-03"
    verification:
      - kind: unit
        ref: "tests/Feature/Booking/ApprovedDraftVisitLineTest.php#adds the visit line when the owner approves a booked customer's held draft (D-39 amended, D-45)"
        status: pass
      - kind: unit
        ref: "tests/Feature/Booking/ApprovedDraftVisitLineTest.php#adds the visit line after the owner's own words on an edited draft (D-39 amended, D-45)"
        status: pass
    human_judgment: false
  - id: D2
    description: "The box starts checked on a clean reading and unchecked on a complaint, a failed reading or none, the owner's choice decides, and a draft that replaces the one on screen resets the box to its own starting state"
    requirement: "BOOK-03"
    verification:
      - kind: unit
        ref: "tests/Feature/Booking/ApprovedDraftVisitLineTest.php (starts the box unchecked ..., sends the visit line when the owner checks a box that started unchecked ..., resets the box to the starting state ...)"
        status: pass
    human_judgment: false
  - id: D3
    description: "Every condition is checked again at approval: a cancelled visit, a deleted booking file or a version synced before visit_line sends the draft without the line with the warning notice and meta.visit_line_left_off; a line is added only when it was on screen; a draft not about scheduling draws no line"
    requirement: "BOOK-03"
    verification:
      - kind: unit
        ref: "tests/Feature/Booking/ApprovedDraftVisitLineTest.php (checks every condition at approval ..., adds no visit line the owner was not shown, draws no visit line when neither the label nor the reading is about scheduling, follows the conditions while the same draft stays on screen)"
        status: pass
    human_judgment: false
  - id: D4
    description: "UAT message 20 shape: a draft labeled other whose customer text read as scheduling, for a booked contact, shows the visit line box"
    requirement: "BOOK-03"
    verification:
      - kind: unit
        ref: "tests/Feature/Booking/ApprovedDraftVisitLineTest.php#shows the visit line on a draft labeled other when the customer's text read as scheduling (UAT message 20)"
        status: pass
    human_judgment: false
  - id: D5
    description: "Send is refused beside the draft box with 'With the visit line this text is over 1,600 characters. Shorten it, or leave the visit line off.' and the draft stays live; at exactly 1,600 it sends"
    requirement: "BOOK-03"
    verification:
      - kind: unit
        ref: "tests/Feature/Booking/ApprovedDraftVisitLineTest.php#refuses Send when the visit line would pass 1,600 characters, and sends at exactly 1,600 (U-37)"
        status: pass
    human_judgment: false
  - id: D6
    description: "In real Chromium the box has no axe violation (WCAG 2.2 AA tags and target-size), is one Tab from the draft box, is named by the visible line and unticks with Space"
    requirement: "BOOK-03"
    verification:
      - kind: unit
        ref: "tests/Feature/Filament/DraftVisitLineKeyboardTest.php (finds no accessibility violation in the visit line box (D-43), reaches the box with one Tab from the draft, names it by the line, and unticks it with Space)"
        status: pass
    human_judgment: false
  - id: D7
    description: "The live VoiceOver listen to the visit line box, and a real booked customer's held reply approved by the owner on a phone"
    requirement: "BOOK-03"
    verification: []
    human_judgment: true
    rationale: "Screen reader speech and the live walk cannot be asserted by a test; U-37 section 6 assigns the VoiceOver listen to the Phase 11 owner-screen pass, and the live G-08-4 walk belongs to end-of-phase UAT"
---

# Phase 8 Plan 25: The visit line on a booked customer's held draft Summary

**A booked customer's reply that the send rule holds can now reach them with the visit's day and time: the approve step shows the owner a checkbox labeled with the exact visit line, and a checked box adds the owner's line after the words at send time on the owner's own send, with every condition checked again and the length refusal rather than a dropped line.**

**Duration:** about 19 minutes | **Started:** 2026-11-11T20:56Z | **Completed:** 2026-11-11T21:16Z | **Tasks:** 3 | **Files:** 10 (2 created, 8 modified)

## What was built

- **Task 1 (tracer).** `VisitAskedAbout::whyNoLine()` gives the two reasons the owner is told ('online booking is turned off', 'no visit is booked ahead'). `SendOwnerReply` gains `visitLineFor()`, `visitLineStartsOn()` (sharing one private reading check with `bookingLinkStartsOn()`), a private `storedIntent()`, and a `sendDraftAskingForVisitLine()` branch of `sendDraft()` that re-asks `whyNoLine()`, measures the length with the line, refuses over 1,600 with `TOO_LONG_WITH_VISIT_LINE`, and sends through `sendExisting()` on the owner origin with `meta.visit_line_appointment_id`. `ResolveDraft::approve()` passes the new flag through. `ConversationThread` has `addVisitLine` and a `#[Locked] visitLine` filled at every render, asks only while a line was drawn, and shows the warning notice when `meta.visit_line_left_off` is set. The view adds the box (`data-visit-line-choice`, `#add-visit-line`) right after the booking link block. BLUEPRINT section 1 gets the Built bullet.
- **Task 2.** Eleven further cases pin the D-45 rules for the visit line: edited words, the box left off, the starting state and the owner's decision for a complaint, a failed reading and no reading, the three left-off reasons, a line the owner was not shown (including a direct `ResolveDraft::approve()` call), the UAT message 20 shape, a draft not about scheduling, following the conditions while one draft stays on screen, the reset on a replacing draft, the 1,601 and 1,600 boundary, and the two boxes never showing together. BLUEPRINT's bullet is extended with these rules.
- **Task 3.** `DraftVisitLineKeyboardTest` in real Chromium (axe with the WCAG 2.2 AA tags and target-size on the box's wrapper, one Tab from the draft box, accessible name equal to the visible line, Space unticks). 08-UI-SPEC gains U-37, three Copywriting rows and section 6.

## TDD Gate Compliance

| Task | RED commit | GREEN commit |
|------|-----------|--------------|
| 1 | ac2d1ce (`assertSet('visitLine', ...)` saw null: the thread had no visitLine; the helper's own reading check passed first, so the failure was the planned assertion, not a fixture fault) | 92de4ae |
| 2 | 4218621 (no case failed at RED: see below) | none needed; docs commit 06ffce5 |
| 3 | not a TDD task (`type="auto"` without `tdd`); the browser test passed first run against Task 1's markup | cebc04a |

Task 2 RED: the plan allowed that cases which Task 1 already built would pass at RED. Every case passed on its first real run (22 of 23 on the first attempt; the 23rd failed on a missing `use App\Actions\ResolveDraft;` in the test file itself, a fault of mine fixed before the commit, not a behavior failure). So no Task 2 case produced RED evidence, and there is no Task 2 GREEN code: the only change after the test commit is the BLUEPRINT bullet, committed as `docs(08-25)` rather than the plan's `feat(08-25)` because a `feat` commit with no code would mislead. A mutation spot check (making `whyNoLine()` skip its no-visit test) left the 'visit cancelled' case green, because `sendDraftAskingForVisitLine()` also falls back to 'no visit is booked ahead' when `find()` returns no visit; that second guard is deliberate, so the case still pins the behavior, not the single line of `whyNoLine()`.

No REFACTOR commit.

## Existing tests changed

None. `ApprovedDraftBookingLinkTest.php:236-270` (visit booked after the thread rendered) and `DraftBookingLinkKeyboardTest.php` pass unchanged, as the plan expected.

## Deviations from Plan

None under deviation rules 1 to 4. Three small departures, none a behavior change:

- **Commit type for Task 2's second commit:** `docs(08-25)` instead of `feat(08-25)`, for the reason above.
- **Resync helper:** the plan named `approvedDraftLinkResync()` for the 'booking file deleted' case. The new test file defines its own `approvedVisitLineResync()`, following the pattern recorded in 08-24 that a test file never depends on helpers from another test file (they exist only when that file has been loaded).
- **Task 3 markup fix:** none needed. The browser cases passed against Task 1's markup, so `conversation-thread.blade.php` was not touched in Task 3.

**Total deviations:** 0 auto-fixed.

## Verification

- Task 1: `ApprovedDraftVisitLineTest` + `ApprovedDraftBookingLinkTest` pass; `tests/Feature/Booking tests/Feature/FrontDesk tests/Feature/Filament tests/Unit/AppointmentWritesTest.php` passes (1,469 tests).
- Task 2: the two files pass (54 tests). The broad command passed in 1,467 of 1,468 tests; the one failure was `BookingPageBrowserTest` 'books by keyboard at 375 wide' (see below), which passes alone.
- Task 3: both keyboard files pass (4 tests, real Chromium, none skipped). The whole suite ran 3,189 tests: 3,188 passed, 1 failed, `BookingPageBrowserTest` 'normalizes a non-breaking space in a name' (a Playwright 5000ms timeout). That file passed 20 of 20 on three separate runs on its own. This is a timing flake under suite load in the customer booking page's browser tests, in a different case each time, in a file this plan does not touch; it is logged in `deferred-items.md` and not fixed here (scope boundary). 08-24's full run of 3,164 tests passed, and this plan adds 25 tests.
- All acceptance greps pass. Pint passes.

## Known Stubs

None.

## Threat Flags

None. T-08-102 (a browser setting `addVisitLine` for a line never drawn) is covered by the locked `visitLine` and the 'adds no visit line the owner was not shown' and 'draws no visit line when neither ...' cases; T-08-103 (a visit cancelled between drawing and Send) by the three left-off cases; T-08-104 by `resolved_by` plus `meta.visit_line_appointment_id` or `meta.visit_line_left_off` on the row.

## Unrun hand checks

Not part of this plan's verification and left for end-of-phase UAT: a live walk of G-08-4 on a phone (a real booked customer's held reply approved by the owner shows and sends the visit line), and the live VoiceOver listen to the box, which U-37 section 6 assigns to the Phase 11 owner-screen pass.

## Next

Ready for the next plan in Phase 8, or end-of-phase UAT.

## Self-Check: PASSED

- Created files exist: tests/Feature/Booking/ApprovedDraftVisitLineTest.php, tests/Feature/Filament/DraftVisitLineKeyboardTest.php.
- Commits ac2d1ce, 92de4ae, 4218621, 06ffce5, cebc04a are on the branch (5 commits measured from the ledger base).
