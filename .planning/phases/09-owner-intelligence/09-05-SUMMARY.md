---
phase: 09-owner-intelligence
plan: 05
subsystem: owner-intelligence
tags: [laravel, events, queue, unique-job, after-commit, records, folder-sync]

requires:
  - phase: 09-owner-intelligence
    provides: "RecordsWriter::writeOwnerIntelligence() under the records:owner-intelligence lock (09-04); TodayRecord and TimeToBooked (09-01 to 09-03)"
  - phase: 08-schedule-and-booking
    provides: "The six named appointment writers (BookAppointment, CancelAppointment, MarkAppointmentDone, EditVisitNote, BlockTime, RemoveBlock) and tests/Unit/AppointmentWritesTest.php"
provides:
  - "App\\Events\\ScheduleChanged (ShouldDispatchAfterCommit, no payload) fired by the six named actions"
  - "App\\Listeners\\QueueOwnerIntelligenceRedraw, found by its handle() type-hint"
  - "App\\Jobs\\RedrawOwnerIntelligenceRecords: unique until it starts, locked, retried like RenderCustomerCard"
  - "FolderSync redraws at once when a sync turns the owner intelligence or schedule room on or off between two live versions"
affects: [09-06 nightly turnover, owner intelligence, operations]

actuals:
  tokens: 7946
  tasks: 3
  commits: 6

plan_head_before: 580b513afc65150f2d614582caecef6ee7b8c230
plan_head_after: 0c75b78677703c5cd932f55df29fa864197ed667
commits: 6

tech-stack:
  added: []
  patterns:
    - "A payload-free after-commit event whose queued job reads the newest state inside the records lock (D-14)"
    - "No Eloquent model event for a redraw trigger, so factory rows draw nothing"

key-files:
  created:
    - app/Events/ScheduleChanged.php
    - app/Listeners/QueueOwnerIntelligenceRedraw.php
    - app/Jobs/RedrawOwnerIntelligenceRecords.php
    - tests/Feature/OwnerIntelligence/RedrawTest.php
  modified:
    - app/Actions/BookAppointment.php
    - app/Actions/CancelAppointment.php
    - app/Actions/MarkAppointmentDone.php
    - app/Actions/EditVisitNote.php
    - app/Actions/BlockTime.php
    - app/Actions/RemoveBlock.php
    - app/Icm/FolderSync.php
    - docs/BLUEPRINT.md
    - docs/sprints/sprint-1.1.md

key-decisions:
  - "ScheduleChanged carries no payload, so the redraw reads the newest state rather than the state at dispatch time (D-14)"
  - "The sync redraw fires only when the previous version is not null, so the first sync of a new database and every test that fakes the queue and then syncs are unchanged (flagged assumption)"
  - "The six actions stay the only writers; the event, listener and job only read (T-09-19, AppointmentWritesTest unchanged)"

patterns-established:
  - "Dispatch the after-commit event after the early returns, inside the action's transaction, so a no-op queues nothing and a rollback discards the event"

requirements-completed: [RECS-02]

coverage:
  - id: D1
    description: "records/today.md is redrawn after a visit is booked by hand, from the booking page, cancelled, marked done, or its note changed (D-14)"
    requirement: RECS-02
    verification:
      - kind: integration
        ref: "tests/Feature/OwnerIntelligence/RedrawTest.php#redraws today.md after a visit is booked by hand (D-14)"
        status: pass
      - kind: integration
        ref: "tests/Feature/OwnerIntelligence/RedrawTest.php#redraws today.md after a page booking (D-14)"
        status: pass
      - kind: integration
        ref: "tests/Feature/OwnerIntelligence/RedrawTest.php#redraws after a cancel"
        status: pass
      - kind: integration
        ref: "tests/Feature/OwnerIntelligence/RedrawTest.php#redraws after a visit is marked done"
        status: pass
      - kind: integration
        ref: "tests/Feature/OwnerIntelligence/RedrawTest.php#redraws after a note change, never printing the note"
        status: pass
    human_judgment: false
  - id: D2
    description: "Adding and removing a block redraws today.md (D-14)"
    requirement: RECS-02
    verification:
      - kind: integration
        ref: "tests/Feature/OwnerIntelligence/RedrawTest.php#redraws after a block is added and removed (D-14)"
        status: pass
    human_judgment: false
  - id: D3
    description: "The redraw waits for the commit, queues once for a burst, is unique until it starts and never overlaps itself (T-09-16, T-09-17)"
    requirement: RECS-02
    verification:
      - kind: integration
        ref: "tests/Feature/OwnerIntelligence/RedrawTest.php#redraws only after the transaction commits"
        status: pass
      - kind: integration
        ref: "tests/Feature/OwnerIntelligence/RedrawTest.php#queues one redraw for changes made before it starts"
        status: pass
      - kind: unit
        ref: "tests/Feature/OwnerIntelligence/RedrawTest.php#is unique until it starts and never overlaps itself"
        status: pass
    human_judgment: false
  - id: D4
    description: "An action that changes nothing, a refused booking and a factory row queue no redraw (D-14 prohibitions)"
    requirement: RECS-02
    verification:
      - kind: integration
        ref: "tests/Feature/OwnerIntelligence/RedrawTest.php#queues no redraw when nothing changed"
        status: pass
      - kind: integration
        ref: "tests/Feature/OwnerIntelligence/RedrawTest.php#draws nothing for a factory row (prohibition)"
        status: pass
    human_judgment: false
  - id: D5
    description: "A sync that turns the owner intelligence or schedule room off or on redraws at once; the first sync and other rooms queue none (D-15, T-09-18)"
    requirement: RECS-02
    verification:
      - kind: integration
        ref: "tests/Feature/OwnerIntelligence/RedrawTest.php#deletes the files at once when a sync turns the room off, and writes them when it comes back (D-15)"
        status: pass
      - kind: integration
        ref: "tests/Feature/OwnerIntelligence/RedrawTest.php#redraws at once when the schedule room is turned off"
        status: pass
      - kind: integration
        ref: "tests/Feature/OwnerIntelligence/RedrawTest.php#queues no redraw on the first sync, or for other rooms"
        status: pass
    human_judgment: false
  - id: D6
    description: "The after-commit dispatch inside the booking lock behaves on Postgres (research Pitfall 5)"
    verification: []
    human_judgment: true
    rationale: "Tests ran on SQLite only; the CI pgsql leg runs after Ian pushes the phase branch and is recorded as an unrun-verify entry in .planning/WINDOWS.md"

duration: 14min
completed: 2026-11-17
status: complete
---

# Phase 9 Plan 05: Redraw today.md on every schedule change Summary

**records/today.md is redrawn through a payload-free after-commit ScheduleChanged event, a listener and one unique, locked, queued RedrawOwnerIntelligenceRecords job, fired by the six named appointment actions and by a sync that switches the owner intelligence or schedule room.**

## Performance

- **Duration:** 14 min
- **Started:** 2026-11-17T02:12:17Z
- **Completed:** 2026-11-17T02:26:00Z
- **Tasks:** 3 (one tracer, two auto; all `tdd="true"`)
- **Files modified:** 13 (4 created, 9 modified), plus the planning files

## Accomplishments

- A visit booked by hand, booked from the page, cancelled, marked done or given a new note redraws `records/today.md` once its transaction commits; a block added or removed does the same. The redraw is one queued job, unique until it starts, locked with `WithoutOverlapping`, with RenderCustomerCard's retry shape (three exceptions, backoff 5/30/120, ten-minute window).
- An action that changes nothing (a second cancel, the same note again, a done visit marked done again), a booking refused for overlap and a rolled-back transaction queue no redraw. Factory rows draw nothing because no Eloquent model event is used.
- A sync that turns the owner intelligence room or the schedule and booking room off or on, between two live versions, queues the redraw at once: the files are deleted when the room goes off and written when it comes back, with no wait for midnight. The first sync and a change to another room queue none.
- `docs/BLUEPRINT.md` section 4 has the Built bullet, and the sprint-1.1 walk-through log has the one-line concept entry.

## Task Commits

1. **Task 1 (tracer): booking by hand redraws today.md** - RED `bb23e0b` (test), GREEN `f283e70` (feat)
2. **Task 2: page booking, cancel, done and note change redraw; no-ops do not** - RED `9b963db` (test), GREEN `1988844` (feat)
3. **Task 3: blocks and the room switch redraw** - RED `5381904` (test), GREEN `0c75b78` (feat)

**Plan metadata:** recorded in the docs commit that follows this file (docs: complete plan).

## TDD Gate Compliance

All three gates present for each task: three `test(09-05)` commits precede three `feat(09-05)` commits. No REFACTOR commit was needed.

- **RED, Task 1:** the by-hand booking case failed on `expect(File::exists(records/today.md))->toBeTrue()` ("Failed asserting that false is true"); the other three cases errored on `Class "App\Events\ScheduleChanged" not found` and `Class "App\Jobs\RedrawOwnerIntelligenceRecords" not found`. Semantic assessment: the by-hand case executed and failed on the planned behavior (today.md not written after the booking); the three class-level cases cannot fail any other way before the classes exist, and failed for exactly that intended reason. The first draft of that case called `File::get` and so errored rather than asserting; it was tightened to assert existence first before the RED commit.
- **RED, Task 2:** four cases failed on `toBeTrue` for the missing today.md (page booking, cancel, done, note change). The no-op and factory cases passed in RED by design (nothing dispatches yet) and keep guarding the GREEN placement.
- **RED, Task 3:** three failed on assertions (block add, room-off delete `Failed asserting that true is false`, schedule-room-off line). The room-back-on and first-sync/other-room cases passed in RED by design.
- `gsd_run check tdd-red-evidence` was not run: PHPUnit/Pest output is not one of the report formats the classifier supports (TAP, Maven JUnit, swift-testing, unittest); the semantic inspection above stands in and is stated here rather than implied.

## Files Created/Modified

- `app/Events/ScheduleChanged.php` - the payload-free after-commit signal
- `app/Listeners/QueueOwnerIntelligenceRedraw.php` - queues the redraw, found by its type-hint
- `app/Jobs/RedrawOwnerIntelligenceRecords.php` - the unique queued redraw calling `writeOwnerIntelligence()`
- `app/Actions/{BookAppointment,CancelAppointment,MarkAppointmentDone,EditVisitNote,BlockTime,RemoveBlock}.php` - one dispatch line each (two in BookAppointment) plus a docblock sentence
- `app/Icm/FolderSync.php` - dispatches on an owner-intelligence or schedule room switch between two live versions, inside its own try/catch
- `tests/Feature/OwnerIntelligence/RedrawTest.php` - 14 cases
- `docs/BLUEPRINT.md`, `docs/sprints/sprint-1.1.md` - Built bullet and walk-through line

## Decisions Made

- The event carries nothing, so the job always reads the newest state (D-14).
- The sync redraw is limited to a change between two live versions, as the plan flagged, so tests that fake the queue and then do a first sync are untouched.
- Open Question 2 and 3 are left as flagged in the plan: a contact rename and a time-zone change do not redraw today.md at once; the next booking change or the nightly run (09-06) catches up.

## Deviations from Plan

None - plan executed exactly as written. Two small additions beyond the listed files are required by CLAUDE.md, not deviations in scope: the walk-through log line in `docs/sprints/sprint-1.1.md` and the `.planning/WINDOWS.md` unrun-verify entry the plan asked for.

**Total deviations:** 0.

## Issues Encountered

- The first draft of the page-booking test passed `now()` (an `Illuminate\Support\Carbon`) to `fromLink()`, which wants a `CarbonImmutable`; fixed to `CarbonImmutable::now()` before its RED commit.
- No test outside this plan failed because a redraw job appeared, so no existing test needed changing.

## Verification

- `php artisan test --compact tests/Feature/OwnerIntelligence tests/Feature/Booking tests/Feature/Filament tests/Feature/Icm tests/Feature/Rooms tests/Feature/Inbound tests/Unit`: 2139 passed.
- Full suite `php artisan test --compact` on SQLite: 3323 passed, 0 failed (23077 assertions).
- `tests/Unit/AppointmentWritesTest.php` (23 tests) still lists only the six named actions.
- Acceptance greps for all three tasks pass (`ScheduleChanged::dispatch()` appears twice in BookAppointment, once in each of the other five actions, once in FolderSync).
- **Not run (hand check, goes to end-of-phase UAT):** the CI pgsql leg for RedrawTest and the Booking folder after Ian pushes the phase branch (after-commit dispatch inside the booking lock, research Pitfall 5). Recorded as an `unrun-verify` entry in `.planning/WINDOWS.md`.

## Known Stubs

None.

## Threat Flags

None. T-09-16 to T-09-19 are mitigated as planned (after-commit event and rollback test; unique job and burst test; sync dispatch and off/on test; AppointmentWritesTest unchanged).

## Next Phase Readiness

Plan 09-06 (the nightly turnover) can call the same `RedrawOwnerIntelligenceRecords` job or `writeOwnerIntelligence()` directly; both are safe under the shared records lock.

## Self-Check: PASSED

All four created files exist; all six task commits are ancestors of HEAD; the RED and GREEN commit patterns (`test(9-5)`, `feat(9-5)`) match three each.

---
*Phase: 09-owner-intelligence*
*Completed: 2026-11-17*
