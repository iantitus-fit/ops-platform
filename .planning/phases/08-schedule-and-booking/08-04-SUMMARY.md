---
phase: 08-schedule-and-booking
plan: 04
subsystem: rooms
tags: [room-standard, booking-file, sync-checks, tdd, pest]
requires:
  - phase: 08-schedule-and-booking
    provides: "08-01: Appointment model with booked() and ahead() scopes and the appointments, booking_links and booking_sends tables"
provides:
  - "ScheduleAndBookingServiceProvider: room key schedule_and_booking, label 'schedule and booking', no connector, no slot, optional, tables appointments, booking_links, booking_sends"
  - "Five trusted timeline entry keys (visit_booked, visit_cancelled, visit_done, visit_note_changed, booking_text_not_sent) and morph names appointment, schedule_block, booking_link, booking_send"
  - "BookingFileType (type booking, one per business at rooms/schedule_and_booking/booking.md) with field rules and placeholder, window, exclamation point and em dash checks"
  - "BookingSettings::fromConfig(): typed reader of the live booking file, null with no file or the room off; renderLink, renderConfirmation, renderReminder, dayText, timeText"
  - "ScheduleSection and its view: names rooms/schedule_and_booking/booking.md when it is missing"
  - "D-36: ConnectorChecks refuses a connector that owns schedule while the room is live"
  - "D-37: RoomDropChecks, the first sync check that reads the database, refuses dropping the room with a booked visit ahead"
  - "Example folder rooms/schedule_and_booking/CONTEXT.md and booking.md; exampleFolderFileCount() is 28"
affects: [08-05 onward booking actions, customer communication, operations, knowledge, connectors and data]
requirements-completed: [ROOM-12]
actuals:
  tokens: 16200
  tasks: 3
  commits: 6
plan_head_before: f9102885be75a4220efef4a465e9565f62db6fe9
plan_head_after: 8631ad74e60106ee8ce7d6324f9b640097cea5fd
status: complete
tech-stack:
  added: []
  patterns:
    - "A room's file type carries its own field rules and checks (BookingFileType), so the Validator is untouched"
    - "A sync check that reads the database is built inside FolderSync::sync() from the sync's own registry, so the constructor keeps its four parameters"
key-files:
  created:
    - app/Rooms/ScheduleAndBooking/ScheduleAndBookingServiceProvider.php
    - app/Rooms/ScheduleAndBooking/BookingFileType.php
    - app/Rooms/ScheduleAndBooking/BookingSettings.php
    - app/Rooms/ScheduleAndBooking/ScheduleSection.php
    - resources/views/filament/rooms/schedule-section.blade.php
    - app/Icm/RoomDropChecks.php
    - examples/example-hvac/rooms/schedule_and_booking/CONTEXT.md
    - examples/example-hvac/rooms/schedule_and_booking/booking.md
    - tests/Feature/Rooms/ScheduleAndBookingBootTest.php
    - tests/Feature/Rooms/BookingFileCheckTest.php
    - tests/Feature/Rooms/ScheduleAndBookingSyncTest.php
  modified:
    - bootstrap/providers.php
    - app/Icm/ConnectorChecks.php
    - app/Icm/FolderSync.php
    - docs/SPEC.md
    - docs/BLUEPRINT.md
    - docs/sprints/sprint-1.1.md
    - tests/Pest.php
    - tests/Feature/Rooms/SpecTablesTest.php
key-decisions:
  - "The booking file's placeholders: {link} once in the link line; {day} and {time} once each and {service} at most once in the confirmation and reminder lines; no other word in braces (planner's, D-28)"
  - "Refusal messages name the field and the placeholder only, never the owner's line (T-08-17)"
  - "D-36 message names the connector file and the room folder; the line is on the connector file, field owns"
  - "D-37 line sits on rooms/schedule_and_booking/CONTEXT.md, counts booked visits ahead and shows the earliest in the candidate folder's business zone, falling back to the live version's zone"
coverage:
  - id: D1
    description: "ROOM-12: the room boots empty and with the example folder, contract matches SPEC, section is axe-clean and names the booking file when it is missing"
    requirement: ROOM-12
    verification:
      - kind: unit
        ref: "tests/Feature/Rooms/ScheduleAndBookingBootTest.php"
        status: pass
    human_judgment: false
  - id: D2
    description: "D-03, D-28: a broken booking file is refused in a plain line naming the field (17 cases); the live file reads back typed; no file or room off means null"
    requirement: ROOM-12
    verification:
      - kind: unit
        ref: "tests/Feature/Rooms/BookingFileCheckTest.php (22 tests)"
        status: pass
    human_judgment: false
  - id: D3
    description: "D-36 and D-37: one owner of the schedule; no dropped room with a visit booked ahead; past, cancelled, done never block; no live version skips the check; one-minute boundary"
    requirement: ROOM-12
    verification:
      - kind: unit
        ref: "tests/Feature/Rooms/ScheduleAndBookingSyncTest.php (11 tests)"
        status: pass
    human_judgment: false
  - id: D4
    description: "CI pgsql leg green for the D-37 cases"
    human_judgment: true
    rationale: "Needs Ian to push the phase branch; not run locally on Postgres. Recorded as an unrun-verify entry (id 49) in .planning/WINDOWS.md."
---

# Phase 8 Plan 04: Schedule and booking room at the standard Summary

The schedule and booking room now meets the room standard with a checked, typed booking file, and the sync refuses a second owner of the schedule (D-36) and a dropped room while a visit is booked ahead (D-37), the previous version staying live in both cases.

## Accomplishments

- Provider, `BookingFileType`, `ScheduleSection` and its view, registered after `LeadsServiceProvider`; the example folder gains the room's `CONTEXT.md` and `booking.md`; SPEC gains the ownership and file-type rows; `exampleFolderFileCount()` is 28.
- The booking file is checked like the owner's other customer lines: number ranges, a send window whose start is before its end, `{link}` once, `{day}` and `{time}` once each, no stray placeholder, no exclamation point or em dash. Each refusal starts with the file path and names the field.
- `BookingSettings::fromConfig()` reads the live file back typed and is null with no file or the room off, so no default customer line exists anywhere in code.
- `ConnectorChecks` refuses a connector file that owns `schedule` while the room is in the folder; one that only reads it, or the room off, passes.
- `RoomDropChecks` (new) reads booked appointments ahead and refuses a folder that drops the room, naming the count and the earliest in the business zone and pointing to `book_online: false`.
- BLUEPRINT sections 4, 8 and 9 gain Built bullets; the sprint 1.1 walk-through log gains one concept line.

## TDD Gate Compliance

| Task | RED | GREEN |
|---|---|---|
| 1 | 9f03b77 test(08-04): the schedule and booking room boots to the standard | 3e37f0d feat(08-04): the schedule and booking room at the standard (ROOM-12, D-35) |
| 2 | 42d99ba test(08-04): the sync checks the booking file and the app reads it typed | 74f9dc8 feat(08-04): the booking file's checks and its typed reader (D-03, D-28) |
| 3 | 7444988 test(08-04): one owner of the schedule, and no dropped room with visits booked | 8631ad7 feat(08-04): one owner of the schedule and no dropped room with visits ahead (D-36, D-37) |

No REFACTOR commits. RED evidence (JUnit report from Pest, classifier verdict `RED_EVIDENCE_OK`):

- Task 1 (previous executor): RED_EVIDENCE_OK.
- Task 2: 17 refusal cases failed on "Expected status code 1 but received 0" (the file was accepted) and 4 cases errored on the missing `BookingSettings` class. Semantic assessment: the target ran and failed on the planned assertion for the intended reason. "Refuses a second booking file" passed already because the room standard's single-file check (`RoomChecks`) covers any room file type.
- Task 3: the four refusal cases failed on the same status code assertion (exit 0 where 1 was planned); the seven pass-through cases passed, as they should before the checks exist. Semantic assessment: intended failures.

## Task Commits

| Task | Commit | Message |
|---|---|---|
| 1 RED | 9f03b77 | test(08-04): the schedule and booking room boots to the standard |
| 1 GREEN | 3e37f0d | feat(08-04): the schedule and booking room at the standard (ROOM-12, D-35) |
| 2 RED | 42d99ba | test(08-04): the sync checks the booking file and the app reads it typed |
| 2 GREEN | 74f9dc8 | feat(08-04): the booking file's checks and its typed reader (D-03, D-28) |
| 3 RED | 7444988 | test(08-04): one owner of the schedule, and no dropped room with visits booked |
| 3 GREEN | 8631ad7 | feat(08-04): one owner of the schedule and no dropped room with visits ahead (D-36, D-37) |

## Deviations from Plan

### Approved by Ian (checkpoint, 2026-11-04)

**1. [Approved - existing test] SpecTablesTest.php lists the single-file types by hand**
- **Found during:** Task 1 GREEN (the previous executor stopped here; full suite was 2533/2534 with this the only failure)
- **Issue:** the test "declares for each single-file type the default path that SPEC gives it" lists `content_brief`, `gbp_categories` and `money_notes`. `BookingFileType` makes `booking` a fourth single-file type, so the expected array was out of date. The plan names only tests/Pest.php:295 as a changed existing test line.
- **Fix:** one added line, `'booking' => $specFiles['booking'],`, first in the array (the test sorts keys, and `booking` sorts before `content_brief`). No assertion was weakened.
- **Effect on the plan's checks:** the acceptance criterion "git diff --name-only --diff-filter=M $BASE -- tests prints exactly tests/Pest.php" prints two files, tests/Pest.php and tests/Feature/Rooms/SpecTablesTest.php.
- **Files modified:** tests/Feature/Rooms/SpecTablesTest.php
- **Commit:** 3e37f0d

### Auto-fixed Issues

**2. [Rule 1 - Bug] The D-37 test helper stored appointment starts as if UTC**
- **Found during:** Task 3 GREEN
- **Issue:** a Chicago moment passed to the factory was saved with its wall clock, so the visit at 10:00 Chicago read 4:00 AM in the business zone and the expected line did not match. The app stores and compares UTC (D-06).
- **Fix:** a `chicagoMoment()` helper in the test turns business-zone times into UTC (`->utc()`) for both `travelTo` and the appointment starts. Same cause and fix as 08-03.
- **Files modified:** tests/Feature/Rooms/ScheduleAndBookingSyncTest.php
- **Commit:** 8631ad7 (the RED file was committed before the helper existed; the helper is a test fix, no assertion changed)

**3. [Rule 3 - Blocking] The D-36 test cannot add a second connector on the same slot**
- **Found during:** Task 3 test design
- **Issue:** a connector file needs a `slot` some room offers, and the platform has one (`google_business_profile`), already filled by the example's connector file, so a second file would earn a second, unrelated refusal line.
- **Fix:** the test deletes the example's `connectors/google-business-profile.md` before laying `connectors/calendar.md` on that slot, so the only refusal is the schedule one.
- **Files modified:** tests/Feature/Rooms/ScheduleAndBookingSyncTest.php
- **Commit:** 7444988

**4. [Process] A duplicate WINDOWS.md entry was written and removed**
- `windows append` ran twice by mistake (ids 49 and 50, the second a shorter description). Entry 50 and its JSON block were removed by hand and the counts corrected (open 19, total 49); entry 49 stands.

**Total deviations:** 4 (1 approved by Ian, 2 auto-fixed in test code, 1 ledger tidy). **Impact:** none on behavior.

## Verification

- `php artisan test --compact` (full suite, SQLite): 2565 passed, 18619 assertions.
- `php artisan test --compact tests/Feature/Rooms tests/Feature/Icm tests/Unit/AppointmentWritesTest.php`: 722 passed. `RoomDropChecks` passes the appointment-writes scan (it imports `Appointment` and holds no write).
- Acceptance greps pass: `OwnableKind::Schedule` in ConnectorChecks, `new RoomDropChecks(` in FolderSync, `book_online: false` in RoomDropChecks, `Phase 8 context D-03, D-35` in BLUEPRINT, `'reminder_hours_before.*'` in BookingFileType. FolderSync's constructor still has 4 `private` parameters. `app/Icm/Validator.php` has no diff since the plan base. HandBuiltRegistrySyncTest.php is unchanged.
- Pint passed on every commit.

## Unrun checks (never a pause)

- **Postgres:** after Ian pushes the phase branch, confirm the CI pgsql leg is green, in particular `ScheduleAndBookingSyncTest`'s D-37 cases (the first sync check that reads the database). Recorded as an `unrun-verify` entry (id 49) in `.planning/WINDOWS.md`.

## Known Stubs

None. `ScheduleSection` shows "Nothing booked." by plan; plan 08-14 adds the visit rows.

## Threat Flags

None. No new endpoint, auth path or file access beyond the plan's threat model. T-08-14 to T-08-17 are mitigated and tested (stray placeholders, exclamation points and em dashes refused; second owner of the schedule refused; dropped room with booked visits refused; messages name fields and placeholders only).

## Issues Encountered

None beyond the approved SpecTablesTest line.

## Next

Ready for 08-05.

## Self-Check: PASSED

All created and modified files exist; commits 9f03b77, 3e37f0d, 42d99ba, 74f9dc8, 7444988 and 8631ad7 are on the branch.
