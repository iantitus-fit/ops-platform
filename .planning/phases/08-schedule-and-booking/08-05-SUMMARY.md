---
phase: 08-schedule-and-booking
plan: 05
subsystem: booking
tags: [availability, business-clock, dst, carbon, overlap, capacity, pest]

requires:
  - phase: 08-schedule-and-booking
    provides: "Appointment and ScheduleBlock models with booked() scopes (08-01), BookingSettings and the booking file (08-04)"
provides:
  - "BusinessClock::openWindowOn(): the one public window for a business day"
  - "App\\Rooms\\ScheduleAndBooking\\Availability: openStarts(), offers(), isFree(), overlapCount(), CAPACITY"
  - "AvailabilityTest: BOOK-01's rules at their edges, both DST days, the buffer and blocks, the tap-time re-check"
affects: [08-06, 08-07, 08-08, booking-page, booking-by-hand]

actuals:
  tokens: 7727
  tasks: 3
  commits: 6
plan_head_before: b81f0a7c87902a86d4a805c885151cb8917d3d97
plan_head_after: d1a43fb11e5b0e76995c53bc8cff19e79ccc12c4

tech-stack:
  added: []
  patterns:
    - "Wall-clock days from the business clock, elapsed-minute stepping from the opening instant (DST safe)"
    - "One PHP overlap rule applied to rows read once per range; bounds computed in PHP and bound as UTC instants"
    - "Count check against a CAPACITY constant, so crews later are a number, not a rewrite"

key-files:
  created:
    - app/Rooms/ScheduleAndBooking/Availability.php
    - tests/Feature/Booking/AvailabilityTest.php
  modified:
    - app/Icm/BusinessClock.php
    - tests/Feature/Icm/BusinessClockTest.php
    - docs/BLUEPRINT.md
    - docs/sprints/sprint-1.1.md

key-decisions:
  - "offers() compares the exact instant with equalTo(), microseconds included, so a start a minute or a second off is refused (T-08-18)"
  - "openStarts() reads the booked appointments and blocks for its whole range once (two queries) and applies the same overlap rule isFree() applies per span"
  - "Day anchor for the range is noon on the business wall clock, so a day-offset never lands on a missing DST hour"

patterns-established:
  - "Test fixtures store spans in UTC (->utc() before create); a Carbon in the business zone is written as its wall time and reads six hours off"

requirements-completed: [BOOK-01]

coverage:
  - id: D1
    description: "Only starts inside hours, outside closures, past the lead time (counted from the later of now and the next opening), long enough for the service and ending by closing are offered, on the business timezone"
    requirement: BOOK-01
    verification:
      - kind: unit
        ref: "tests/Feature/Booking/AvailabilityTest.php#offers the example weekday's starts for AC repair"
        status: pass
      - kind: unit
        ref: "tests/Feature/Booking/AvailabilityTest.php#counts the lead from the next opening after hours"
        status: pass
      - kind: unit
        ref: "tests/Feature/Booking/AvailabilityTest.php#ends a job by closing and offers a start whose end is exactly closing"
        status: pass
    human_judgment: false
  - id: D2
    description: "Overlap count: appointments carry the drive buffer at their end, blocks carry none, only booked rows count, capacity 1"
    requirement: BOOK-01
    verification:
      - kind: unit
        ref: "tests/Feature/Booking/AvailabilityTest.php#keeps the drive buffer after an appointment"
        status: pass
      - kind: unit
        ref: "tests/Feature/Booking/AvailabilityTest.php#gives a block no buffer of its own"
        status: pass
      - kind: unit
        ref: "tests/Feature/Booking/AvailabilityTest.php#agrees between the list and the single-span check"
        status: pass
    human_judgment: false
  - id: D3
    description: "Both daylight-saving days: five starts on the spring-forward day with none at a 02:xx wall time, seven distinct 60-minute-apart instants on the fall-back day"
    requirement: BOOK-01
    verification:
      - kind: unit
        ref: "tests/Feature/Booking/AvailabilityTest.php#never offers the missing hour on the spring-forward day"
        status: pass
      - kind: unit
        ref: "tests/Feature/Booking/AvailabilityTest.php#offers distinct increasing instants on the fall-back day"
        status: pass
    human_judgment: false
  - id: D4
    description: "offers() answers true only for a start the recomputed list holds (a minute off, past, closure, taken-after-draw all refused)"
    requirement: BOOK-01
    verification:
      - kind: unit
        ref: "tests/Feature/Booking/AvailabilityTest.php#answers the tap-time question"
        status: pass
    human_judgment: false
  - id: D5
    description: "The Postgres run of the overlap and DST cases (timestamp comparisons)"
    verification: []
    human_judgment: true
    rationale: "Needs Ian to push the phase branch so the CI pgsql leg runs; not run locally (WINDOWS entry 50)"

duration: 7min
completed: 2026-11-05
status: complete
---

# Phase 8 Plan 05: Availability Summary

**A pure Availability class that offers booking starts from the business's hours, closures, lead time (from the later of now and the next opening), service length, step, range and drive buffer, with a one-job-at-a-time overlap count, correct on both daylight-saving days.**

## Performance

- **Duration:** 7 min
- **Started:** 2026-11-05T16:43:13Z
- **Completed:** 2026-11-05T16:50:16Z
- **Tasks:** 3
- **Files modified:** 6 (plus the WINDOWS ledger and planning state in the metadata commit)

## Accomplishments
- `BusinessClock::openWindowOn()` exposes one business day's window publicly while `windowOn()` stays private and unchanged.
- `Availability::openStarts()` steps elapsed minutes from each day's opening on the business wall clock, offers a start only at or after the lead time, after now, ending by closing and free of overlaps, and groups by business-zone date for `days_ahead` days. With no booking file it offers nothing (D-03).
- `overlapCount()` and `isFree()` apply one overlap rule: an appointment carries the buffer at its end, a block carries none (A2), the new span's buffer applies to both; `CAPACITY = 1` is a count check. `openStarts()` reads the booked rows for its whole range in two queries.
- `offers()` is the tap-time re-check: recompute the list server-side and compare the exact instant (T-08-18).
- BLUEPRINT section 4 Built bullet and the walk-through line (plan 08-05) are written.

## TDD Gate Compliance

All three tasks have a RED commit (`test(08-05)`) before the GREEN commit (`feat(08-05)`); no REFACTOR commit was needed.

- **Task 1 RED (88b1a55):** against uncommitted stubs (an `openStarts()` returning `[]`, an `openWindowOn()` returning null), the example-weekday test failed on the planned assertion ("null is identical to '2026-11-03'") and the BusinessClock window test failed on "Trying to access array offset on null". Semantic assessment: both exercised the planned behavior and failed for the intended reason (the behavior was absent), not a load or syntax fault. GREEN d3f4fbe.
- **Task 2 RED (f2fb6a8):** buffer, block, list-versus-single-span and every-day-blocked failed on assertions (each listed all six hourly starts instead of the reduced list); 'counts against capacity' errored because `overlapCount()` did not exist. Already passing from Task 1 (recorded, not a RED defect): lead after hours, lead during open hours, past starts, closures and closed days, range, end by closing, no booking file, and the source scan. GREEN 61c645e.
- **Task 3 RED (9dc37f9):** 'answers the tap-time question' errored (`offers()` undefined). Both DST cases already passed from Task 1's elapsed-minute stepping, as the plan anticipated. GREEN d1a43fb.

Unexpected-green check: the cancelled/done/removed-row case and the DST cases passed in RED by design of the implementation, not because the tests were hollow (the cancelled/done case was first a false pass; see Deviations).

## Task Commits

1. **Task 1: Tracer, one weekday's starts** - `88b1a55` (test), `d3f4fbe` (feat)
2. **Task 2: the rules at their edges and the overlap count** - `f2fb6a8` (test), `61c645e` (feat)
3. **Task 3: DST days and the tap-time question, docs** - `9dc37f9` (test), `d1a43fb` (feat)

**Plan metadata:** the docs(08-05) commit that follows this file.

## Files Created/Modified
- `app/Rooms/ScheduleAndBooking/Availability.php` - openStarts, offers, isFree, overlapCount, CAPACITY
- `app/Icm/BusinessClock.php` - public `openWindowOn()`
- `tests/Feature/Booking/AvailabilityTest.php` - 21 cases: BOOK-01 rules, edges, DST days, tap-time check, source scan
- `tests/Feature/Icm/BusinessClockTest.php` - one new case for `openWindowOn()`
- `docs/BLUEPRINT.md`, `docs/sprints/sprint-1.1.md` - Built bullet and walk-through line
- `.planning/WINDOWS.md` - entry 50 (unrun-verify) for the Postgres CI leg

## Decisions Made
- `offers()` uses `equalTo()` on the offered instant, so microseconds count; the compared instant is the exact one, never rounded.
- The day anchor for each offset is noon on the business wall clock, so adding days never lands on a nonexistent DST hour.
- The overlap rule is written once (`overlapsIn()`); the list and the single-span check differ only in where the rows come from.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Test fixtures stored business-zone wall time as if UTC**
- **Found during:** Task 2 (GREEN)
- **Issue:** My first helpers passed a Chicago-zoned Carbon to the factory; Eloquent stores a Carbon's own wall time, so the rows read six hours off and no overlap was found. The 'ignores cancelled, done and removed' case was a false pass until fixed.
- **Fix:** Helpers and that case convert with `->utc()` before saving (the same rule 08-04 recorded).
- **Files modified:** tests/Feature/Booking/AvailabilityTest.php
- **Verification:** all 21 AvailabilityTest cases pass with real rows in play.
- **Committed in:** 61c645e

**2. [Rule 1 - Bug] The no-clock source scan matched its own docblock wording**
- **Found during:** Task 3 (GREEN)
- **Issue:** "offers right now (T-08-18)" tripped the scan for `now (`.
- **Fix:** reworded the docblock to "at the moment passed in".
- **Files modified:** app/Rooms/ScheduleAndBooking/Availability.php
- **Committed in:** d1a43fb

---

**Total deviations:** 2 auto-fixed (both Rule 1, both in code or tests written in this plan)
**Impact on plan:** None on scope. One extra case beyond the plan's list: 'offers 14:00 and 15:00 on an hourly step when now is 11:20', pinning the plan's stated expectation separately from the step-20 case.

## Issues Encountered
- The RED run for Task 1 needed uncommitted stub methods so the tests failed on assertions rather than on a missing class; only the tests were committed in the RED commit.

## Human check carried (not run, never a pause)
After Ian pushes the phase branch, the CI pgsql leg should be green, in particular AvailabilityTest's overlap and DST cases (timestamp comparisons on Postgres). Recorded as `.planning/WINDOWS.md` entry 50 (unrun-verify) for the end-of-phase UAT.

## Verification
- `php artisan test --compact tests/Feature/Booking/AvailabilityTest.php tests/Feature/Icm/BusinessClockTest.php tests/Feature/Rooms/ScheduleAndBookingBootTest.php tests/Unit/AppointmentWritesTest.php`: 86 passed.
- Full `php artisan test --compact` on SQLite: 2587 passed, 18696 assertions, 0 failed; no existing test changed.
- `vendor/bin/pint --dirty --format agent` clean on every commit. AppointmentWritesTest's writer list is still `[]`.

## Known Stubs
None.

## Threat Flags
None. T-08-18 (tapped start) is mitigated by `offers()`; T-08-19 by passing the moment in and reading the zone from ActiveConfig; T-08-20 accepted as planned (two range queries per draw).

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- The booking action (08-07) can call `offers()` inside its lock and `isFree()` for booking by hand; the page (08-08) calls `openStarts()`.
- Postgres confirmation of the overlap and DST cases waits for the CI run after Ian pushes the branch.

## Self-Check: PASSED

---
*Phase: 08-schedule-and-booking*
*Completed: 2026-11-05*
