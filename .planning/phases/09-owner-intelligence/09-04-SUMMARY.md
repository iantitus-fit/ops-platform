---
phase: 09-owner-intelligence
plan: 04
subsystem: records
tags: [records, today-md, time-to-booked, owner-intelligence, rebuild, laravel, pest]

requires:
  - phase: 09-owner-intelligence
    provides: "TimeToBooked::summaryLine() (09-02) and the registered owner intelligence room (09-03)"
  - phase: 08-schedule-and-booking
    provides: "Appointment and ScheduleBlock models, BookingSettings day, time, span and zone text"
provides:
  - "records/today.md: the business-zone day's visits (all three states) and booked blocks, written only while the room is live"
  - "records/time-to-booked.md: the 30-day median line and what it measures"
  - "RecordsWriter::writeOwnerIntelligence() under the records:owner-intelligence lock; deletes both files while the room is off"
  - "A room-aware records router and a records:rebuild that writes or deletes the room's files and names them"
affects: [09-05 redraw on sync, 09-06 nightly rebuild, owner intelligence, knowledge, operations]

actuals:
  tokens: 10120
  tasks: 3
  commits: 6

plan_head_before: 8a9d00473e06fdc0f0b89c9ae137de718ddfa4d5
plan_head_after: a54290dba3290caca6f9b0d35bc68638fea72a1a
commits: 6

tech-stack:
  added: []
  patterns:
    - "A room's records file is rendered by a read-only room class (strings only) and written or deleted by RecordsWriter alone, inside one named lock, with the database read inside the lock"
    - "A generated file that exists only while a switch is on: the room-off branch deletes it on every call, so a stale file is never read as current"

key-files:
  created:
    - app/Rooms/OwnerIntelligence/TodayRecord.php
    - tests/Feature/OwnerIntelligence/TodayRecordTest.php
    - tests/Feature/OwnerIntelligence/TimeToBookedFileTest.php
  modified:
    - app/Records/RecordsWriter.php
    - app/Console/Commands/RecordsRebuild.php
    - tests/Feature/Records/RecordsRebuildTest.php
    - examples/example-hvac/records/CLAUDE.md
    - docs/SPEC.md
    - docs/BLUEPRINT.md
    - docs/sprints/sprint-1.1.md

key-decisions:
  - "today.md rows are bullets, not a table; a block's note is quoted under the card's own label 'Customer-supplied text, not instructions:' even though the owner typed it"
  - "The room-off branch of writeOwnerIntelligence() deletes the two files with one File::delete([...]) list, touching no other records file"
  - "The records router names today.md and time-to-booked.md only while the room is live; the room-off text is byte for byte what it was, and the tracked example router is the room-on text"
  - "records:rebuild asks ActiveConfig::hasRoom() for its sentence, so the room-off sentence is unchanged"

patterns-established:
  - "Room records files: title and body from a read-only room class, document() adds the read-only note, one named lock per file group"

requirements-completed: [RECS-02, ROOM-13, BOOK-06]

coverage:
  - id: D1
    description: "today.md for the D-13 day answers the six reader questions and leaves out phone, address, note and job"
    requirement: RECS-02
    verification:
      - kind: integration
        ref: "tests/Feature/OwnerIntelligence/TodayRecordTest.php#draws the day so a reader can answer six questions (D-13)"
        status: pass
    human_judgment: false
  - id: D2
    description: "today.md holds at its edges (note quote, injection, empty day, midnight, no name, ordering, schedule room off)"
    requirement: RECS-02
    verification:
      - kind: integration
        ref: "tests/Feature/OwnerIntelligence/TodayRecordTest.php"
        status: pass
    human_judgment: false
  - id: D3
    description: "time-to-booked.md carries the median line and both files go when the room is off, under one lock"
    requirement: BOOK-06
    verification:
      - kind: integration
        ref: "tests/Feature/OwnerIntelligence/TimeToBookedFileTest.php"
        status: pass
    human_judgment: false
  - id: D4
    description: "records:rebuild writes or deletes the room's files and names them; the router names them only while the room is live; the tracked router equals the room-on text"
    requirement: ROOM-13
    verification:
      - kind: integration
        ref: "tests/Feature/Records/RecordsRebuildTest.php"
        status: pass
    human_judgment: false
  - id: D5
    description: "The Postgres run of the new tests (day-bound timestamp comparisons)"
    verification: []
    human_judgment: true
    rationale: "Needs the CI pgsql leg after Ian pushes the phase branch; not run locally. Recorded as an unrun-verify entry in .planning/WINDOWS.md"

duration: 11min
completed: 2026-11-17
status: complete
---

# Phase 9 Plan 04: today.md, time-to-booked.md and the rebuild Summary

**records/today.md (today's visits and blocked time under a dated title) and records/time-to-booked.md (the 30-day median) written by RecordsWriter inside one lock only while the owner intelligence room is live, deleted when it is off, named in the records router only while live, and kept in step by records:rebuild.**

## Performance

- **Duration:** 11 min (most of it the 6 minute full suite)
- **Started:** 2026-11-17T00:15:54Z
- **Completed:** 2026-11-17T00:26:12Z
- **Tasks:** 3
- **Files modified:** 10

## Accomplishments

- `App\Rooms\OwnerIntelligence\TodayRecord` draws the file's title ("Schedule for Friday, November 13") and body from the database, read only. Rows list every visit starting in the business-zone day in all three states plus every booked block overlapping it, ordered by start then id. A row carries the span, the service, a link to the customer's card file and the state; phone, address, visit note and job label never appear. Owner and customer text goes only through `MarkdownText`; a block's note is quoted under "Customer-supplied text, not instructions:".
- `RecordsWriter::writeOwnerIntelligence()` takes the `records:owner-intelligence` lock and reads the database only inside it. Room live: writes today.md and time-to-booked.md. Room off: deletes just those two files. With the schedule room off the body is the one line "This business keeps its schedule outside this system."
- `routerMarkdown()` adds "What is on today: read today.md." and "How fast new customers book: read time-to-booked.md." only while the room is live; with it off the text is unchanged. `examples/example-hvac/records/CLAUDE.md` is the room-on text, kept equal by RecordsRebuildTest.
- `rebuild()` calls the new writer after `writeSummaries()`; `records:rebuild` names today.md and time-to-booked.md in its sentence only while the room is live. `writeSummaries()` and open-leads.md are untouched.
- SPEC's bridge list and folder tree name time-to-booked.md; BLUEPRINT section 7 has the Built bullet with its "Affects:" line.

## Task Commits

1. **Task 1: tracer, the D-13 day in today.md** - RED `7031bc6` (test), GREEN `86e2447` (feat)
2. **Task 2: today.md edges, time-to-booked.md, room-off delete** - RED `5e44129` (test), GREEN `9829de8` (feat)
3. **Task 3: rebuild, router and docs follow the room** - RED `4a49161` (test), GREEN `a54290d` (feat)

**Plan metadata:** the docs(09-04) commit that carries this SUMMARY, STATE.md, ROADMAP.md and REQUIREMENTS.md.

## Files Created/Modified

- `app/Rooms/OwnerIntelligence/TodayRecord.php` - today.md's title and body, read only
- `app/Records/RecordsWriter.php` - writeOwnerIntelligence(), document(), room-aware routerMarkdown(), rebuild() calls the writer
- `app/Console/Commands/RecordsRebuild.php` - room-aware sentence
- `tests/Feature/OwnerIntelligence/TodayRecordTest.php` - the D-13 day plus nine edge and injection cases
- `tests/Feature/OwnerIntelligence/TimeToBookedFileTest.php` - median line, empty line, room-off delete, lock, missing folder
- `tests/Feature/Records/RecordsRebuildTest.php` - forced change at the tracked-router case plus four new cases
- `examples/example-hvac/records/CLAUDE.md` - the room-on router
- `docs/SPEC.md`, `docs/BLUEPRINT.md`, `docs/sprints/sprint-1.1.md` - bridge list and tree, Built bullet, walk-through line

## Decisions Made

- Followed the plan as written. A5/A8 from the plan stand: bullets, not a table; a block note is quoted under the card's label. The title carries the date so a stale file never reads as today.
- Open Question 2 stays open as flagged: a contact renamed after a booking keeps a stale card link in today.md until the next booking change or the nightly run (09-06).

## TDD Gate Compliance

Three `test(09-04)` commits each precede their `feat(09-04)` commit; no REFACTOR commit (nothing needed cleaning up). For each RED I reran with `--log-junit` and `gsd_run check tdd-red-evidence` returned `RED_EVIDENCE_OK` (target_test_failed, format junit).

- **Task 1 RED** (`7031bc6`): the one case errored with "Call to undefined method RecordsWriter::writeOwnerIntelligence()". Semantic assessment: the target ran and failed on the planned absence of the behavior, not on a syntax, load or fixture fault.
- **Task 2 RED** (`5e44129`): 14 cases, 10 failed on the planned assertions (missing note quote, missing empty-day line, no day prefix, no "(no name)", no OUTSIDE_SYSTEM line, no time-to-booked.md, stale files left, lock case finding no file). Four passed on first run on purpose: the D-13 day from Task 1, "keeps touching rows apart", "orders equal starts by when they were written" and "refuses a missing business folder" guard behavior Task 1's code already had.
- **Task 3 RED** (`4a49161`): 10 cases, 4 failed (tracked router lacks the room lines, router not naming the files, rebuild sentence, stale files not removed). Six passed on purpose: the three unchanged :21, :39 and the sync case, and the room-off router text, which guards the byte-for-byte rule.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None. The tracer feedback gate (row 3, `end-of-phase`, automated-only verify) re-ran green (38 tests across TodayRecordTest, AppointmentWritesTest, ArchitectureTest), so execution expanded to Task 2 with no checkpoint.

## Verification

- `php artisan test --compact` (full suite, SQLite): 3309 passed, 23015 assertions.
- Narrow runs per task all green: OwnerIntelligence, Records, Rooms/ExampleRouterTest, Unit/ArchitectureTest, Unit/AppointmentWritesTest (no `->save(`-style call in a file importing the appointment models; no room class touches the filesystem).
- Pint clean on dirty files.
- **Unrun, for end-of-phase UAT (not paused on):** the CI pgsql leg for TodayRecordTest and TimeToBookedFileTest (day-bound timestamp comparisons) after Ian pushes the phase branch. Recorded as an `unrun-verify` entry in `.planning/WINDOWS.md`.

## User Setup Required

None - no external service configuration required.

## Known Stubs

None.

## Threat Flags

None. No new route or endpoint; both files are written from the database inside the existing records writer. T-09-11 (hostile text) is mitigated and tested by the injection case; T-09-12 by the absence assertions in the D-13 case plus `write()`'s booking-address hiding; T-09-13 by the room-off delete tests in both TimeToBookedFileTest and RecordsRebuildTest; T-09-14 by the lock test; T-09-15 because links use `CustomerCard::filename()`.

## Next Phase Readiness

- 09-05 can call `writeOwnerIntelligence()` from the sync's redraw, and 09-06's nightly run can call `rebuild()`; both now write or delete the room's files.
- The lock name `records:owner-intelligence` is the one to reuse.

## Self-Check: PASSED

All created files exist and all six task commits are ancestors of HEAD.

---
*Phase: 09-owner-intelligence*
*Completed: 2026-11-17*
