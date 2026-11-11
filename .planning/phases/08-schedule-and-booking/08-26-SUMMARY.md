---
phase: 08-schedule-and-booking
plan: 26
subsystem: front-desk
tags: [front-desk-prompt, booking-section, rules, d-39-amended, pest]
gap_closure: true
gap_ids: [G-08-4, G-08-4b]
status: complete

requires:
  - phase: 08-schedule-and-booking
    provides: "08-24 (VisitAskedAbout, the visit line on a sent reply), 08-25 (the visit line on a held draft), ServiceToBook (D-01, D-02)"
provides:
  - "FrontDeskPrompt::RULES no longer says a booking waits for the owner: a time the customer proposes is still not set, and a visit the card lists as booked is the business's booking (G-08-4)"
  - "A conditional '## Booking' section after the customer card: BOOKING_VISIT_BOOKED for a visit booked ahead, BOOKING_ONLINE with the sorted book_online slugs for a customer with no visit ahead, left out when booking does not apply (G-08-4, G-08-4b)"
  - "ServiceToBook::visitBookedAhead() and onlineServiceSlugs(), asked by the link send and the prompt alike"
affects: [phase-08-uat]

actuals:
  tokens: 9300
  tasks: 2
  commits: 4

plan_head_before: ca0bae7227d4ac0e9e7e485376a774976e24df0d
plan_head_after: 9521d89d3fac21873f00b250928253775b58bc40

tech-stack:
  added: []
  patterns:
    - "A prompt section built from the same room-class methods the send and the approval ask (ServiceToBook, VisitAskedAbout), so what the model is told cannot drift from what code does"
    - "The section holds fixed code sentences and owner service slugs only: no customer text, no booking address, no owner line"

key-files:
  created:
    - tests/Feature/FrontDesk/BookingSectionTest.php
  modified:
    - app/Ai/FrontDeskPrompt.php
    - app/Rooms/ScheduleAndBooking/ServiceToBook.php
    - tests/Feature/FrontDesk/FrontDeskPromptTest.php
    - docs/BLUEPRINT.md
    - .planning/WINDOWS.md

key-decisions:
  - "The scheduling label's definition (RULES) is not edited: the sentence that a reply about the booked visit is labeled scheduling lives in the visit variant of the section, so a business without booking reads the same rules plus one corrected bullet"
  - "booking.md stays out of the agent's loads and the section quotes no owner line, so the owner's link line and any booking address never reach the model (WR-07, T-08-107)"
  - "A booked customer never gets the online variant: no link rides while a visit is booked, and the visit variant is drawn only while a visit line can ride (VisitAskedAbout::whyNoLine() is null)"

patterns-established:
  - "Pest global helpers in a new test file carry their own prefix (bookingSection) and never rely on helpers from another test file"

requirements-completed: [BOOK-02, BOOK-03]

coverage:
  - id: D1
    description: "RULES line 41 is replaced by the two D-39-amended sentences; the old 'owner confirms those' rule is gone; a time the customer proposes is still not set (G-02-2.4)"
    requirement: "BOOK-03"
    verification:
      - kind: unit
        ref: "tests/Feature/FrontDesk/FrontDeskPromptTest.php (tells the model a time the customer proposes is not set, and a visit booked on the card is the business's; no longer tells the model the owner confirms a booking)"
        status: pass
    human_judgment: false
  - id: D2
    description: "A booked customer's real run reads '## Booking' with BOOKING_VISIT_BOOKED between the card and Right now, with no booking address and no online variant"
    requirement: "BOOK-03"
    verification:
      - kind: unit
        ref: "tests/Feature/FrontDesk/BookingSectionTest.php#tells a booked customer's run that the system adds the visit's day and time (D-39 amended)"
        status: pass
    human_judgment: false
  - id: D3
    description: "An unbooked customer's run reads BOOKING_ONLINE with the book_online slugs sorted and joined as 'a, b and c' (one alone, two as 'a and b'), never system-replacement"
    requirement: "BOOK-02"
    verification:
      - kind: unit
        ref: "tests/Feature/FrontDesk/BookingSectionTest.php (tells an unbooked customer's run which services book online ..., lists one service on its own, lists two services as a and b)"
        status: pass
    human_judgment: false
  - id: D4
    description: "The section is left out when booking does not apply (room off, no booking file, no service booked online and no visit, a visit with the room off, a visit with a version synced before visit_line), sits after the card and before 'Already with the owner' and 'Right now', holds no '/book/', '!' or em dash, and booking.md is not in the agent's loads"
    requirement: "BOOK-03"
    verification:
      - kind: unit
        ref: "tests/Feature/FrontDesk/BookingSectionTest.php (leaves the Booking section out ..., puts the Booking section after the card ..., writes the Booking section with no booking address ..., keeps booking.md out of what the model reads)"
        status: pass
    human_judgment: false
  - id: D5
    description: "The Dev chat re-walk of D-05, D-39 and the link-again case, on a live Dev chat with the live model, after php artisan icm:sync"
    requirement: "BOOK-03"
    verification: []
    human_judgment: true
    rationale: "A live run with the real reply model; recorded as WINDOWS entry 79 (unrun-verify) for end-of-phase UAT, never run or waited on by the executor (Ian's rule)"
---

# Phase 8 Plan 26: What the front desk reads about booking Summary

**FrontDeskPrompt::RULES no longer tells the model a booking waits for the owner; a time the customer proposes is still not set, a visit the card lists as booked is the business's, and a conditional `## Booking` section, built from the same ServiceToBook and VisitAskedAbout conditions the send uses, tells the model what code adds (the visit's day and time, or the owner's link line) and that the page books at once.**

**Duration:** about 12 minutes | **Started:** 2026-11-11T21:17Z | **Completed:** 2026-11-11T21:30Z | **Tasks:** 2 | **Files:** 6 (1 created, 5 modified)

## What was built

- **Task 1 (tracer).** RULES line 41 is replaced by one bullet holding the two D-39-amended sentences; no other RULES line changed, the scheduling label included. `ServiceToBook::visitBookedAhead()` is the one method `check()` (for `VISIT_BOOKED`) and the prompt both ask. `FrontDeskPrompt` gains `BOOKING_VISIT_BOOKED`, a private `bookingSection()` and two container-injected dependencies (`ServiceToBook`, `VisitAskedAbout`); `instructions()` appends the section right after the card part. The visit variant is drawn only while `VisitAskedAbout::whyNoLine()` is null, so a room off or a version synced before `visit_line` leaves it out.
- **Task 2.** `ServiceToBook::onlineServiceSlugs()` (empty with the room off or no booking file; otherwise the `book_online` slugs, sorted). `FrontDeskPrompt::BOOKING_ONLINE` with `{services}` and a private `listed()`. `bookingSection()` returns the visit variant or null for a booked customer (never the online variant), the online variant when at least one service books online, and null otherwise. BLUEPRINT section 1 extended with the G-08-4b half and the Decided bullet on the card marked as amended. WINDOWS entry 79 records the live re-walk.
- **Tests.** Eight cases through a real run and by condition in the new `BookingSectionTest.php` (twelve with datasets): the booked run, the online list (three, one and two services), five left-out conditions, placement against the waiting draft, the no-address/no-`!`/no-em-dash check over the constants and rendered sections, and booking.md kept out of the loads.

## TDD Gate Compliance

| Task | RED commit | GREEN commit |
|------|-----------|--------------|
| 1 | fa496c3 (the rewritten pinned case and the new 'no longer confirms' case failed on the old RULES line; the booked-run case errored on the then-missing `BOOKING_VISIT_BOOKED` constant) | 8cfc8c6 |
| 2 | 7e9a995 (online-services, one-service, two-service, placement and style cases failed: no section without a visit, `BOOKING_ONLINE` missing; one-service and two-service failed on a null section) | 9521d89 |

Task 2 RED, as the plan said: the five left-out cases and the booking.md case already passed from Task 1, pinning the conditions the section was built on. No REFACTOR commit.

## Existing tests changed

- **tests/Feature/FrontDesk/FrontDeskPromptTest.php:324-332** (the pinned case 'tells the model the thread is not a booking (G-02-2.4)', forced by the rewrite): renamed 'tells the model a time the customer proposes is not set, and a visit booked on the card is the business's (G-02-2.4, D-39 amended)', its dataset now the two new sentences; a new case follows it, 'no longer tells the model the owner confirms a booking (D-39 amended)', asserting the two old phrases are gone from RULES (the file's new lines run to about :343).
- No other test changed and none failed: the whole suite passes (3,205 tests, 22,601 assertions), including `BookingLinkTest` (no '/book/' in the instructions), 'puts Right now last' and the Already-with-the-owner ordering case.

## Deviations from Plan

None - plan executed exactly as written. Small notes, none a behavior change:

- The acceptance grep `grep -q 'private function bookingSection(Contact $contact)'` returns no match with this machine's BRE grep (the `$contact)` after the `(`), while `grep -F` of the same text matches line 277 of `FrontDeskPrompt.php`; the criterion is met, checked with `-F`.
- Added one test beyond the plan's list: 'lists two services as a and b', so the `a and b` shape the plan names for two slugs is pinned as well as the one-slug and three-slug shapes.

**Total deviations:** 0 auto-fixed.

## Verification

- Task 1: `BookingSectionTest`, `FrontDeskPromptTest`, `BookingLinkTest` pass (83 tests); `tests/Feature/FrontDesk tests/Feature/Booking tests/Feature/Alerts` passes (1,333 tests).
- Task 2: `BookingSectionTest`, `FrontDeskPromptTest`, `BookingLinkTest`, `VisitLineTest` pass (114 tests); `php artisan test --compact` passes (3,205 tests, none failed; the 1 warning is pre-existing and not from this plan's files).
- All acceptance greps pass (`-F` for the `bookingSection` signature, see above). Pint passes.

## Known Stubs

None.

## Threat Flags

None. T-08-105 (customer text in the section): the section holds only constants and service slugs from the synced folder. T-08-106 (a promised line or link code would not add): built from `visitBookedAhead()`, `onlineServiceSlugs()` and `whyNoLine()`, left out per condition, each tested. T-08-107 (a booking address or owner line reaching the model): no address, no owner line, booking.md not in the loads, tested.

## Unrun hand checks

Carried to end-of-phase UAT as WINDOWS entry 79 (`unrun-verify`, app/Ai/FrontDeskPrompt.php): the Dev chat re-walk after `php artisan icm:sync`, so the live version carries `visit_line`, on fresh numbers:

1. D-05: after the last intake answer the offer comes back labeled scheduling with the owner's link line, and its own words neither ask the customer to pick a time nor say the owner will confirm (G-08-4b).
2. D-39 as amended: on a contact with a visit booked ahead, "What time are you coming?" sends on its own labeled scheduling with parts `model_words` and `visit_line`, the model's words give no day or time, and the owner gets one `change_request` alert (G-08-4).
3. The link-again case: "Can you send the link again?" gives a fresh link line or a held draft, never `/book/[link]` to the customer, and the words do not repeat the link line.
4. A held reply on a booked contact shows "The visit line will be added" with its box, and Send adds the line after the words (08-25).

## Next

Ready for the next plan in Phase 8, or end-of-phase UAT (G-08-4 and G-08-4b are ready for the re-walk).

## Self-Check: PASSED

- Created file exists: tests/Feature/FrontDesk/BookingSectionTest.php.
- Commits fa496c3, 8cfc8c6, 7e9a995, 9521d89 are on the branch (4 commits measured from the ledger base).
