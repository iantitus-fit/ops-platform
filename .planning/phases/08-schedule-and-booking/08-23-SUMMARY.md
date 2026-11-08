---
phase: 08-schedule-and-booking
plan: 23
subsystem: ui
tags: [filament, schedule, timezone, confirmation-forecast, upcoming-past, pest]
gap_closure: true
gap_ids: [UI-ZONE-NAME, IN-03, IN-04, IN-01]

requires:
  - phase: 08-schedule-and-booking
    provides: "08-14 (the Schedule page and the Appointments tab), BookingSettings::zoneName() (U-11), BookAppointment's drive buffer (D-08)"
provides:
  - "Both owner dialogs say 'Times are in Central Time.' under Starts, never a raw zone id (UI-ZONE-NAME resolved)"
  - "The Book by hand dialog forecasts no confirmation for a customer with no phone number (IN-03 resolved)"
  - "Schedule's Past lists booked rows by their end, so Upcoming and Past never share a row (IN-04 resolved)"
  - "Availability's docblocks match the code on the hand-booking drive buffer (IN-01 resolved)"
affects: [phase-08-uat, phase-11-owner-screen-pass]

actuals:
  tokens: 4550
  tasks: 3
  commits: 6

plan_head_before: 5ce7be0588b96c64fd78c580f33164cc7daa2fbc
plan_head_after: 4a4b632f7833aca97fc5e9d7d5882960ff76786e

tech-stack:
  added: []
  patterns:
    - "Owner-facing helper text names the business zone through BookingSettings::zoneName(); the picker's ->timezone() keeps the id"
    - "Past/Upcoming split booked rows by ends_at against the start of today in the business zone, done visits by starts_at, as one grouped where"

key-files:
  created: []
  modified:
    - app/Filament/Pages/Schedule.php
    - app/Filament/Resources/Contacts/RelationManagers/AppointmentsRelationManager.php
    - app/Rooms/ScheduleAndBooking/Availability.php
    - tests/Feature/Filament/SchedulePageTest.php
    - tests/Feature/Filament/AppointmentsRelationManagerTest.php
    - .planning/phases/08-schedule-and-booking/08-UI-SPEC.md
    - docs/BLUEPRINT.md

key-decisions:
  - "The Book by hand dialog's helper had the same raw zone id as the Block time dialog (U-32), so both were fixed in Task 1"
  - "The optional 'will go out at the next opening' variant of the no-phone line was considered and not added: the line is drawn when the dialog opens, before a time is picked, and the send window's rule lives in SendBookingText; the default line stays true then because the text goes out at the window's next opening (D-28)"
  - "Past keeps done visits on their start (D-40); only booked rows (visits and blocks) move to end-based splitting"

patterns-established:
  - "A forecast line in a dialog is added as a match arm before the default, in the order of what stops the send"

requirements-completed: [BOOK-03]

coverage:
  - id: D1
    description: "Block time and Book by hand dialogs say 'Times are in Central Time.' under Starts and never print America/Chicago"
    requirement: "BOOK-03"
    verification:
      - kind: unit
        ref: "tests/Feature/Filament/SchedulePageTest.php#names the zone in plain words in the Block time dialog (UI review)"
        status: pass
      - kind: unit
        ref: "tests/Feature/Filament/AppointmentsRelationManagerTest.php#names the zone in plain words in the Book by hand dialog (UI review)"
        status: pass
    human_judgment: false
  - id: D2
    description: "A customer with no phone number gets 'No confirmation text will go out. This customer has no phone number on file.' in the Book by hand dialog; the four existing lines are unchanged"
    requirement: "BOOK-03"
    verification:
      - kind: unit
        ref: "tests/Feature/Filament/AppointmentsRelationManagerTest.php#says no confirmation goes out to a customer with no phone (IN-03)"
        status: pass
      - kind: unit
        ref: "tests/Feature/Filament/AppointmentsRelationManagerTest.php#says whether a confirmation text will go out"
        status: pass
    human_judgment: false
  - id: D3
    description: "A block or visit that crosses midnight is under Upcoming until it ends and under Past only after, never both; one ending exactly at the start of today is Upcoming"
    requirement: "BOOK-03"
    verification:
      - kind: unit
        ref: "tests/Feature/Filament/SchedulePageTest.php#keeps a span over midnight out of Past until it has ended (IN-04)"
        status: pass
      - kind: unit
        ref: "tests/Feature/Filament/SchedulePageTest.php#filters by Show"
        status: pass
    human_judgment: false
  - id: D4
    description: "Availability's docblocks say hand bookings pass the booking file's drive buffer, or 0 with no booking file"
    verification:
      - kind: other
        ref: "grep -c \"the file's drive buffer, or 0 with no booking file\" app/Rooms/ScheduleAndBooking/Availability.php (prints 2)"
        status: pass
    human_judgment: false

duration: 22min
completed: 2026-11-08
status: complete
---

# Phase 8 Plan 23: Owner-screen findings Summary

**Both owner dialogs name the business zone in plain words ("Central Time"), the hand-booking forecast never promises a text to a customer with no phone, Schedule's Past splits booked rows by their end so a span over midnight is never in both views, and Availability's docblocks match the code.**

## Performance

- **Duration:** 22 min
- **Started:** 2026-11-08T22:17:00Z
- **Completed:** 2026-11-08T22:39:37Z
- **Tasks:** 3
- **Files modified:** 7

## Accomplishments

- UI-ZONE-NAME (08-UI-REVIEW.md priority fix 2): the Starts helper in the Block time dialog (Schedule page) and the Book by hand dialog (contact screen) reads "Times are in Central Time." through `BookingSettings::zoneName()`. The pickers keep the zone id they need. The Book by hand dialog carried the same raw id (U-32), so it was fixed in the same step.
- IN-03: `confirmationStatus()` has a fifth arm, "No confirmation text will go out. This customer has no phone number on file.", after the booking-file arm and before the default. 08-UI-SPEC.md lists the line.
- IN-04: `Schedule::showRows('past')` lists booked rows whose `ends_at` is before the start of today in the business zone, and done visits whose `starts_at` is before it, as one grouped where. A block from Monday 22:00 to Wednesday 22:00 is under Upcoming until it ends. A visit ending exactly at the start of today is Upcoming.
- IN-01: both Availability docblocks (`offers()` and `isFree()`) now say hand bookings pass the file's drive buffer, or 0 with no booking file. No code change.
- docs/BLUEPRINT.md section 4's Built bullet records the Past rule and the plain zone name (Affects: operations, team, kept).

## Considered and not added

The optional "will go out at the next opening" variant of the no-phone line (the ruling allowed it) was not added. The line is drawn when the dialog opens, before a time is chosen, and the send window's rule lives in `SendBookingText`. The default line stays true at that moment, because the text does go out at the window's next opening (D-28). Ian may ask for it later.

## Task Commits

1. **Task 1: Tracer, both dialogs name the zone in plain words**
   - RED `18b9b8a` (test): both cases failed on the planned assertion, the HTML read "Times are in America/Chicago."
   - GREEN `2725385` (fix)
2. **Task 2: No confirmation forecast for a customer with no phone (IN-03)**
   - RED `de2e314` (test): failed because the dialog said a text would go out
   - GREEN `7a4e925` (fix), with the UI-SPEC row
3. **Task 3: Past by end, docblocks (IN-04, IN-01)**
   - RED `3fb49fc` (test): the Tuesday Past view listed the spans that started before today
   - GREEN `4a4b632` (fix), with the Availability docblocks and the BLUEPRINT bullet

**Plan metadata:** recorded in the docs commit that follows this file.

## TDD Gate Compliance

Each behavior-adding task has its `test(08-23)` commit before its `fix(08-23)` commit. Each RED run failed on the planned assertion for the intended reason (a real assertion failure on the rendered HTML or the table's listed records, not a load or fixture fault). The `gsd_run check tdd-red-evidence` classifier was not run: this is a Pest/JSON report, not one of its supported formats, so the RED check was a read of the failing assertion in the Pest JSON output. The gate type is `execute`, not `tdd`, and the commits use `fix` for the GREEN step as the plan specified.

## Files Created/Modified

- `app/Filament/Pages/Schedule.php` - plain zone name in the Block time helper; Past by end for booked rows; showRows docblock
- `app/Filament/Resources/Contacts/RelationManagers/AppointmentsRelationManager.php` - plain zone name in Book by hand; no-phone arm in `confirmationStatus()` and its docblock
- `app/Rooms/ScheduleAndBooking/Availability.php` - two docblock sentences
- `tests/Feature/Filament/SchedulePageTest.php` - Block time zone-name case; IN-04 span-over-midnight case
- `tests/Feature/Filament/AppointmentsRelationManagerTest.php` - Book by hand zone-name case; IN-03 no-phone case
- `.planning/phases/08-schedule-and-booking/08-UI-SPEC.md` - the fifth confirmation line
- `docs/BLUEPRINT.md` - section 4 Built bullet

## Decisions Made

See key-decisions above. The plan was followed as written.

## Deviations from Plan

None - plan executed exactly as written. No existing test failed; 'filters by Show' and the four lines of 'says whether a confirmation text will go out' are unchanged and pass.

## Issues Encountered

None.

## Verification

- `php artisan test --compact tests/Feature/Filament/SchedulePageTest.php tests/Feature/Filament/AppointmentsRelationManagerTest.php` passed (72 tests).
- `php artisan test --compact tests/Feature/Filament/AppointmentsRelationManagerTest.php tests/Feature/Booking/BookingSendTest.php` passed (68 tests).
- `php artisan test --compact tests/Feature/Filament/SchedulePageTest.php tests/Feature/Booking/AvailabilityTest.php tests/Feature/Booking/BookAppointmentTest.php` passed (73 tests).
- `php artisan test --compact` passed: 3126 tests, 22057 assertions, on SQLite.
- Every task's acceptance criteria were run and pass; `vendor/bin/pint --dirty --format agent` passed.

## Hand checks

No new hand check. The owner screens' live VoiceOver listen stays with the Phase 11 owner-screen pass (WINDOWS entries 65 and 68), and their CI pgsql legs are entries 64 and 67, as the plan stated.

## Known Stubs

None.

## Threat Flags

None. No new endpoint, auth path or schema change; the three mitigations (T-08-91, T-08-92, T-08-93) are in place and tested.

## Next Phase Readiness

Plan 08-23 closes the owner-screen findings from the Phase 8 UI review and code review that were assigned to the gap plans. Phase 8 is ready for its end-of-phase verification and UAT.

## Self-Check: PASSED

All seven modified files exist; commits `18b9b8a`, `2725385`, `de2e314`, `7a4e925`, `3fb49fc` and `4a4b632` are on the branch.

---
*Phase: 08-schedule-and-booking*
*Completed: 2026-11-08*
