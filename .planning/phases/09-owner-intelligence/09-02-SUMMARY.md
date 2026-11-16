---
phase: 09-owner-intelligence
plan: 02
subsystem: testing
tags: [time-to-booked, book-06, tdd, pest, median, text-keywords, one-query, duration-text]

requires:
  - phase: 08-schedule-and-booking
    provides: appointments.booked_at, the Appointment kind scope, hand-added contacts (08 D-19)
  - phase: 02-front-desk
    provides: TextKeywords::actionFor() and RecordInbound::keywordIn(), the rule for what a keyword text is
provides:
  - DurationText::format(), the one formatter for a length of time ("under a minute", "42 minutes", "1 hour 31 minutes", "2 days 4 hours")
  - TimeToBooked::forContact(), a contact's seconds from first real inbound message to first booking ever, in one SELECT
  - TimeToBooked::summary() and summaryLine(), the 30-day median line in one SELECT, with EMPTY_LINE and WINDOW_DAYS
affects: [09-03 owner-intelligence dashboard section and contact screen line, 09-04 records/time-to-booked.md, owner-intelligence, customer-communication]

actuals:
  tokens: 6600
  tasks: 3
  commits: 6

tech-stack:
  added: []
  patterns:
    - "One SELECT for every contact: a grouped Appointment sub-query joined with joinSub() to the inbound messages, the keyword walk and the median done in PHP"
    - "Time arithmetic on Unix timestamps (getTimestamp() integers), never Carbon diffs"
    - "The 30-day window starts at the business zone's start of today minus 29 days, so it keeps local midnight across a daylight change"

key-files:
  created:
    - app/Rooms/OwnerIntelligence/DurationText.php
    - app/Rooms/OwnerIntelligence/TimeToBooked.php
    - tests/Unit/OwnerIntelligence/DurationTextTest.php
    - tests/Feature/OwnerIntelligence/TimeToBookedTest.php
  modified:
    - docs/sprints/sprint-1.1.md
    - .planning/WINDOWS.md

key-decisions:
  - "TimeToBooked mirrors RecordInbound::keywordIn() in a PHP walk (SMS only, opted-out flag per contact, YES only while opted out) instead of matching keywords in SQL (D-01)"
  - "A message and a booking in the same second give no number; the SQL compares strictly before and the walk drops 0 or less (A4)"
  - "The median is not rounded to a minute before formatting; DurationText rounds, so a median under a minute reads 'under a minute' (A3)"

patterns-established:
  - "A read-only class that imports App\\Models\\Appointment calls no ->save(), ->update(), ->delete(), ->touch() or ->push(), so AppointmentWritesTest does not list it as a writer"

requirements-completed: [BOOK-06]

coverage:
  - id: D1
    description: "DurationText::format() holds every D-06 boundary from under a minute to days and hours"
    requirement: BOOK-06
    verification:
      - kind: unit
        ref: "tests/Unit/OwnerIntelligence/DurationTextTest.php#formats seconds as the time to booked reads (20 rows)"
        status: pass
    human_judgment: false
  - id: D2
    description: "One contact's time to booked is the first booking's booked_at minus the first real message's created_at, in seconds"
    requirement: BOOK-06
    verification:
      - kind: unit
        ref: "tests/Feature/OwnerIntelligence/TimeToBookedTest.php#works out one contact's time to booked"
        status: pass
    human_judgment: false
  - id: D3
    description: "A keyword-only SMS (STOP, HELP, padded or lower case, YES while opted out) never starts the clock; a sentence holding a keyword word and a web form reading STOP do"
    requirement: BOOK-06
    verification:
      - kind: unit
        ref: "tests/Feature/OwnerIntelligence/TimeToBookedTest.php#skips a keyword-only first text (D-01)"
        status: pass
      - kind: unit
        ref: "tests/Feature/OwnerIntelligence/TimeToBookedTest.php#treats YES as a keyword only while the contact is opted out"
        status: pass
      - kind: unit
        ref: "tests/Feature/OwnerIntelligence/TimeToBookedTest.php#starts on a web form, even one that reads STOP"
        status: pass
    human_judgment: false
  - id: D4
    description: "The clock stops at the first booking ever, across projects, a cancelled booking and a page booking; no message before it gives null, never zero or negative"
    requirement: BOOK-06
    verification:
      - kind: unit
        ref: "tests/Feature/OwnerIntelligence/TimeToBookedTest.php#stops at the first booking ever, whichever project and whoever booked it (D-02)"
        status: pass
      - kind: unit
        ref: "tests/Feature/OwnerIntelligence/TimeToBookedTest.php#gives no number when there is no message before the first booking (D-03)"
        status: pass
    human_judgment: false
  - id: D5
    description: "summaryLine() reads the D-07 sentences (odd and even median, singular, under a minute, empty), counts 30 days in the business zone and leaves out contacts with no number"
    requirement: BOOK-06
    verification:
      - kind: unit
        ref: "tests/Feature/OwnerIntelligence/TimeToBookedTest.php#gives the median of an odd count (D-07)"
        status: pass
      - kind: unit
        ref: "tests/Feature/OwnerIntelligence/TimeToBookedTest.php#counts the 30 days in the business zone"
        status: pass
      - kind: unit
        ref: "tests/Feature/OwnerIntelligence/TimeToBookedTest.php#leaves out contacts with no number (D-03)"
        status: pass
    human_judgment: false
  - id: D6
    description: "The summary and one contact each cost exactly one query, whether 3 or 10 contacts qualify (D-04, T-09-05)"
    requirement: BOOK-06
    verification:
      - kind: unit
        ref: "tests/Feature/OwnerIntelligence/TimeToBookedTest.php#works out the summary in one query (D-04)"
        status: pass
    human_judgment: false
  - id: D7
    description: "The CI Postgres leg passes for TimeToBookedTest (grouped sub-select with a having bound, timestamp comparison, Assumption A9)"
    verification: []
    human_judgment: true
    rationale: "Postgres is not run locally; confirmed on CI after Ian pushes the phase branch (WINDOWS.md entry 82)"

duration: 24min
completed: 2026-11-16
status: complete
plan_head_before: 3c5103c4f4228357df0fc4f038686fd76942fcf0
plan_head_after: c34e0f00007de7bc4fbc0b4c75971d06e1979499
---

# Phase 9 Plan 2: Time to booked Summary

**Time to booked (BOOK-06) is worked out on read from a contact's first real inbound message to their first booking ever, for one contact and as a 30-day median line, each in one SELECT, through one shared DurationText formatter.**

## Performance

- **Duration:** 24 min
- **Started:** 2026-11-16T19:47:00Z (plan clock started after 09-01's docs commit)
- **Completed:** 2026-11-16T20:11:00Z
- **Tasks:** 3
- **Files:** 4 created, 2 modified (plus the WINDOWS.md ledger entry)

## Accomplishments

- `DurationText::format()` is the one formatter (D-06): under a minute; minutes up to and including 90; hours and minutes up to and including 48 hours; days and hours past that; a zero part dropped, singular for 1. Twenty dataset rows pin every boundary.
- `TimeToBooked::forContact()` runs one SELECT: a grouped Appointment query gives each contact's `min(booked_at)`, `joinSub()` joins it to the inbound messages before it, and a PHP walk takes the first real message. Nothing is stored and no writer was added.
- The walk mirrors `RecordInbound::keywordIn()`: only an SMS is matched, with `TextKeywords::actionFor()`, tracking the opted-out state so YES is a keyword only while opted out. A web form that reads STOP starts the clock; a sentence with a keyword word does too.
- `summary()` and `summaryLine()` give the D-07 median line over contacts first booked in the last 30 days (the business zone's start of today minus 29 days, in one SELECT), with `EMPTY_LINE` and `WINDOW_DAYS`. A median under a minute reads "booked in under a minute".

## Task Commits

1. **Task 1 (tracer): 42 minutes from first text to first booking**
   - RED `ac3670e` (test) - 21 tests failed on "class not found" (neither class existed yet)
   - GREEN `f887c2a` (feat)
2. **Task 2: the clock starts at the first real message**
   - RED `4233b14` (test) - the keyword cases failed on "6120 is identical to 2520" and "6120 is null" (the 08:00 STOP started the clock)
   - GREEN `0992260` (feat)
3. **Task 3: the 30-day median line in one query**
   - RED `0154bc2` (test) - failed on `summary()` and `summaryLine()` not existing
   - GREEN `c34e0f0` (feat)

**Plan metadata:** the docs commit that carries this SUMMARY, STATE.md, ROADMAP.md, REQUIREMENTS.md and WINDOWS.md.

## TDD Gate Compliance

All three tasks have a `test(09-02)` commit before its `feat(09-02)` commit. No REFACTOR commit: nothing needed cleaning up. The `gsd check tdd-red-evidence` classifier was not run: Pest's JSON summary line is not one of its supported report formats. The RED assessment was done by hand against the failure messages. Task 1's RED fails on missing classes by design (the plan says "no classes yet"). Task 2's RED fails on the planned assertion for exactly the keyword cases, and the timing cases (sentence with a keyword word, web form, first booking ever) passed on the Task 1 code on purpose.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug in the plan's test fixture] "reads every conversation of the contact" cannot use two open conversations**
- **Found during:** Task 2 (RED run)
- **Issue:** the plan's case uses an older conversation and a newer one for the same contact, but `conversations` has a partial unique index (`conversations_one_open_per_contact`), so two open threads for one contact are impossible and the factory threw an integrity error.
- **Fix:** the older thread is built with `Conversation::factory()->closed()`, which is the real shape (a closed old thread and a new open one). The query was already written to read every conversation.
- **Files modified:** tests/Feature/OwnerIntelligence/TimeToBookedTest.php
- **Commit:** 4233b14

**2. [Rule 1 - Bug in my test] Query log not flushed before counting**
- **Found during:** Task 3 (GREEN run)
- **Issue:** the D-04 test read 98 queries because `DB::enableQueryLog()` kept earlier factory queries; the count was of the log, not of `summaryLine()`.
- **Fix:** `DB::flushQueryLog()` right after enabling and again after adding the seven contacts, so each count is only the call being measured. 1, 1 and 1 queries.
- **Files modified:** tests/Feature/OwnerIntelligence/TimeToBookedTest.php
- **Commit:** c34e0f0 (the test file went in the same commit as the code; the RED commit 0154bc2 carried the first version of the test)

**Total deviations:** 2 auto-fixed (both in test fixtures). **Impact:** none on the code or on any decision.

Minor execution notes, not deviations: `grep` in this shell reads ERE, so the plan's acceptance `grep -q 'public static function format(int $seconds): string'` printed 0 until run with `-F`; with `-F` every acceptance grep passes. The acceptance line `grep -c "it('"` counts 8 lines, with the keyword case a dataset of three.

## Issues Encountered

- Full suite on SQLite: 3277 tests, 3272 pass, 5 fail. The five are the Pest browser tests in `tests/Feature/Booking/BookingPageBrowserTest.php` already recorded in `.planning/phases/09-owner-intelligence/deferred-items.md` (touch-target size, footer hover border); this plan touches no booking page, view or CSS. Not fixed (scope boundary). All 56 tests of this plan and `AppointmentWritesTest` and `ArchitectureTest` pass.

## Unrun Human Checks

- Postgres: after Ian pushes the phase branch, the CI pgsql leg should be green for `TimeToBookedTest` (the grouped sub-select with a having bound and the timestamp comparison, Assumption A9). Not run locally. Recorded as WINDOWS.md entry 82 (unrun-verify) for the end-of-phase UAT.

## Known Stubs

None.

## Threat Flags

None. The classes only read, add no endpoint, auth path or schema change, and customer bodies are compared in memory and never returned or logged (T-09-07 accepted). T-09-05 (one query) and T-09-06 (keyword and strict-before checks) are covered by the Task 3 and Task 2 tests.

## Next Phase Readiness

09-03 can read `TimeToBooked::forContact()` for the contact screen line and `summaryLine()` for the dashboard section, and 09-04 can print the same through `DurationText`. BLUEPRINT gets its Built bullet in 09-03, where the number first shows.

## Self-Check: PASSED

- Files exist: app/Rooms/OwnerIntelligence/DurationText.php, app/Rooms/OwnerIntelligence/TimeToBooked.php, tests/Unit/OwnerIntelligence/DurationTextTest.php, tests/Feature/OwnerIntelligence/TimeToBookedTest.php.
- Commits are on the branch: ac3670e, f887c2a, 4233b14, 0992260, 0154bc2, c34e0f0.
- `php artisan test --compact tests/Unit/OwnerIntelligence tests/Feature/OwnerIntelligence tests/Unit/AppointmentWritesTest.php tests/Unit/ArchitectureTest.php`: 76 passed.
