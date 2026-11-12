---
phase: 08-schedule-and-booking
plan: 11
subsystem: booking
tags: [reminders, send-window, daylight-saving, carbon, pest, tdd]

requires:
  - phase: 08-schedule-and-booking
    provides: BookingSend and Appointment::sends() (08-01), BookingSettings (08-04), BookAppointment byHand() and fromLink() (08-06, 08-07)
provides:
  - ReminderSchedule::rows(), a pure calculation of the confirmation's and each reminder's due time, window moves, silent skips and one row per moment
  - BookAppointment writes one booking_sends row per text inside the booking transaction, in both byHand() and fromLink()
  - ReminderScheduleTest (21 cases, both 2026 daylight-saving days) and BookingSendRowsTest (5 cases)
affects: [08-12, 08-13, 08-14, phase-8-uat]

plan_head_before: 78a1badbf168a9fa3aa92354a57b71044c331e52
plan_head_after: f120717ff29b8e97baa283b3ed7099235bcb99c6

actuals:
  tokens: 6140
  tasks: 3
  commits: 6

tech-stack:
  added: []
  patterns:
    - "A pure schedule class takes the settings and the zone as arguments, so a unit test needs no Laravel boot"
    - "Window days are calendar days on the business wall clock (subDay/addDay on a zoned instant, then setTimeFromTimeString); the lead itself is elapsed hours"
    - "Rows are normalised to UTC where they are stored (BookAppointment), not inside the calculation"

key-files:
  created:
    - app/Rooms/ScheduleAndBooking/ReminderSchedule.php
    - tests/Unit/Booking/ReminderScheduleTest.php
    - tests/Feature/Booking/BookingSendRowsTest.php
  modified:
    - app/Actions/BookAppointment.php
    - docs/BLUEPRINT.md
    - .planning/WINDOWS.md

key-decisions:
  - "08-11: ReminderSchedule returns instants in whatever zone it was given; BookAppointment::writeSendRows() converts due_at to UTC before the insert, because Eloquent's datetime cast stores the wall time of the instance's own zone without converting"
  - "08-11: a reminder due at or before the booking moment gets no row (at-or-before, not only strictly before)"
  - "08-11: when reminders collapse onto one moment the kept row carries the larger lead (as flagged in the plan)"

patterns-established:
  - "Compute due_at once at booking, store it, and let the sender (08-12) only claim and send"

requirements-completed: [BOOK-04]

coverage:
  - id: D1
    description: "A booking stores one send row per text: the confirmation (lead 0) and one per reminder lead, with due_at computed once, in the booking transaction (D-31)"
    requirement: BOOK-04
    verification:
      - kind: integration
        ref: "tests/Feature/Booking/BookingSendRowsTest.php#writes the confirmation and the reminder for a hand booking"
        status: pass
      - kind: integration
        ref: "tests/Feature/Booking/BookingSendRowsTest.php#writes a page booking's rows"
        status: pass
      - kind: integration
        ref: "tests/Feature/Booking/BookingSendRowsTest.php#writes rows in the booking's transaction, so a refused booking leaves none"
        status: pass
    human_judgment: false
  - id: D2
    description: "D-28 and D-29 timing to the minute: page and hand confirmations, window moves with inclusive ends, silent skips, one row per moment, wall-clock days across both daylight-saving changes"
    requirement: BOOK-04
    verification:
      - kind: unit
        ref: "tests/Unit/Booking/ReminderScheduleTest.php (21 cases)"
        status: pass
    human_judgment: false
  - id: D3
    description: "With no booking file a booking writes no send rows (D-03)"
    requirement: BOOK-04
    verification:
      - kind: integration
        ref: "tests/Feature/Booking/BookingSendRowsTest.php#writes no rows with no booking file (D-03)"
        status: pass
    human_judgment: false
  - id: D4
    description: "The same tests pass on Postgres 17 in CI"
    verification: []
    human_judgment: true
    rationale: "No local Postgres; the CI pgsql leg runs after Ian pushes the phase branch (WINDOWS.md unrun-verify entry)"

duration: 11min
completed: 2026-11-06
status: complete
---

# Phase 8 Plan 11: Reminder and confirmation due times Summary

**Each booking now stores when its confirmation and every reminder go out: a pure ReminderSchedule applies D-28 and D-29 on the business wall clock (window moves, silent skips, one row per moment), and both booking paths write the rows inside the booking transaction.**

## Performance

- **Duration:** 11 min
- **Started:** 2026-11-06T20:59:00Z
- **Completed:** 2026-11-06T21:10:00Z
- **Tasks:** 3
- **Files modified:** 6 (3 created, 3 changed)

## Accomplishments

- `ReminderSchedule::rows(startsAt, bookedAt, pageBooking, settings, zone)` returns the rows earliest due first. A page booking's confirmation is due at once with `ignores_window` true; a hand booking's is due at once inside the window, else at the next opening, and is not written when that opening is at or after the start.
- A reminder is its lead in elapsed hours before the start. Outside the window it moves to the end of the window before (previous calendar day's end before opening, that day's end after closing); both ends count as inside. A reminder whose final time is at or before the booking moment gets no row. Reminders landing on one moment keep one row with the larger lead.
- `BookAppointment::writeSendRows()` runs inside the booking transaction in `store()` and `storeFromLink()`, through `$appointment->sends()->create()`. No booking file writes nothing (D-03).
- BLUEPRINT section 4 records the timing as a Built bullet with an "Affects:" line.

## TDD Gate Compliance

- **RED (Task 1)** `a6e45a1`: `BookingSendRowsTest` "writes the confirmation and the reminder for a hand booking" failed on its planned assertion ("actual size 0 matches expected size 2": no send rows existed). Semantic assessment: the target test ran, booted the example folder, booked the visit, and failed on the planned count, not on a setup or syntax fault. The Pest JSON report is not a format `gsd_run check tdd-red-evidence` supports, so the machine classifier was not run; the assessment above is the inspection.
- **GREEN (Task 1)** `da905e9`.
- **RED (Task 2)** `76bda24`: 12 of 21 `ReminderScheduleTest` cases failed on the planned due-time assertions (window moves, skips, collapse, hand-booking confirmation, DST evening, row order); the other 9 passed against the Task 1 stub by design (inside-window cases, page confirmation, empty list, elapsed-hour DST cases, purity guard). Semantic assessment: every failure was a wrong or extra due time, not an error.
- **GREEN (Task 2)** `f13ef05`.
- **Task 3** `ba0b293` (test), `f120717` (docs): the new cases passed with Tasks 1 and 2 unchanged; no fix was needed.
- No REFACTOR commit; nothing to clean.

## Task Commits

1. **Task 1: tracer, a hand booking writes its confirmation and reminder** - `a6e45a1` (test), `da905e9` (feat)
2. **Task 2: D-28 and D-29 to the minute** - `76bda24` (test), `f13ef05` (feat)
3. **Task 3: page bookings, no booking file, a refused booking, BLUEPRINT** - `ba0b293` (test), `f120717` (docs)

Tracer gate (auto, end-of-phase): the tracer's verify command was re-run after GREEN (40 tests passed) before expanding.

## Files Created/Modified

- `app/Rooms/ScheduleAndBooking/ReminderSchedule.php` - the pure due-time calculation
- `app/Actions/BookAppointment.php` - `writeSendRows()` called from both booking paths
- `tests/Unit/Booking/ReminderScheduleTest.php` - 21 unit cases, no Laravel boot
- `tests/Feature/Booking/BookingSendRowsTest.php` - 5 feature cases on the example folder
- `docs/BLUEPRINT.md` - section 4 Built bullet
- `.planning/WINDOWS.md` - the Postgres hand check as an `unrun-verify` entry

## Decisions Made

- `due_at` is converted to UTC where the row is written. The first run of the tracer test showed a Chicago-zoned instant stored as its wall time (10:00 instead of 16:00 UTC), because Eloquent's datetime cast does not convert zones; the calculation itself stays zone-agnostic.
- "Already passed" is at-or-before the booking moment, so a reminder due the very second of booking is not sent as a catch-up.
- Concept for the walk-through: a Carbon instant is one moment in time, but writing it to a database column without `->utc()` stores the clock reading in that instance's zone. This is the same rule `BookAppointment` already follows for `starts_at`; the plan stated no new log line is needed.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Reminder due_at stored as wall time, not UTC**
- **Found during:** Task 1 (GREEN, first run)
- **Issue:** `$startsAt->subHours()` on the Chicago-zoned start kept the Chicago zone, and the datetime cast stored 10:00 instead of 16:00 UTC.
- **Fix:** `writeSendRows()` writes `$row['due_at']->utc()`.
- **Files modified:** `app/Actions/BookAppointment.php`
- **Verification:** the tracer test passes with UTC values.
- **Committed in:** `da905e9`

**2. [Minor test edits made before the RED commit]** Two cases I first wrote were wrong and were fixed before `76bda24`: the "earliest due first" case booked too late for its 72-hour lead (booked Monday instead of Tuesday), and the purity guard matched the word `ActiveConfig` in a docblock (it now checks for a `use` import, Illuminate imports and writes).

**Additions beyond the plan:** two extra unit cases (hand confirmation at once inside the window, and opening at the start when booked before it; a spring-forward window-move case) and one extra feature case (an after-hours hand booking waits for the window), so 21 and 5 cases against the plan's minimum of 13.

**Total deviations:** 1 auto-fixed bug (Rule 1). **Impact:** none on scope.

## Issues Encountered

- The plan's acceptance criterion `grep -q 'public static function rows(CarbonImmutable $startsAt, ...'` does not match under this machine's basic-regex grep (the `$` and parentheses), though the signature is present on one line exactly as written; `grep -F` matches it. The criterion's intent is met.
- The "refused booking leaves no rows" case proves a refused booking stores no rows. It does not prove rollback of rows already written, since the refusal comes before the appointment row; the writes sit after the appointment create inside the same `DB::transaction`, so a later failure would roll them back.

## Authentication Gates

None.

## Known Stubs

None.

## Threat Flags

None. T-08-45 (window, ends inclusive, wall clock) and T-08-46 (collapse at creation plus the unique index) are mitigated here; the send-time window re-check is 08-12.

## Hand check left for UAT

Not run, by design: after Ian pushes the phase branch, the CI pgsql leg should be green for `BookingSendRowsTest`. Recorded as an `unrun-verify` entry in `.planning/WINDOWS.md`. No SQL was added, so the risk is low; the dates go through the Eloquent datetime cast on both drivers.

## Verification

- `php artisan test --compact tests/Feature/Booking tests/Unit/Booking`: 274 passed.
- `php artisan test --compact`: 2817 passed on SQLite, no existing test changed.
- `git diff --name-only --diff-filter=M "$BASE" -- tests` printed nothing (BASE verified as 78a1bad).
- `AppointmentWritesTest` passed with its writer list unchanged.

## Next Phase Readiness

Ready for 08-12: the sender only has to claim due `booking_sends` rows, re-check the window and the appointment, and send through `OutboundSender`.

## Self-Check: PASSED

- Files created exist: ReminderSchedule.php, ReminderScheduleTest.php, BookingSendRowsTest.php (found).
- Commits a6e45a1, da905e9, 76bda24, f13ef05, ba0b293, f120717 are ancestors of HEAD.
