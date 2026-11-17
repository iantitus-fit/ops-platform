---
phase: 09-owner-intelligence
plan: 06
subsystem: infra
tags: [laravel-scheduler, records-rebuild, timezone, daylight-saving, pest]

requires:
  - phase: 09-owner-intelligence
    provides: "records:rebuild extended in 09-04 to write or delete today.md and time-to-booked.md by whether the room is live"
  - phase: 09-owner-intelligence
    provides: "09-05 redraws, so today.md is already current between nights"
provides:
  - "Schedule::command('records:rebuild') entry, considered every minute, passed only at 00:10 in the business zone"
  - "NightlyRebuildTest covering flags, the 00:10 gate, zone change, both daylight-saving days, a bad read, room off and 08 IN-06"
  - "docs/SETUP.md scheduler-every-minute bullet with the skipped-night note"
affects: [phase-12-deploy, owner-intelligence, operations, knowledge]

actuals:
  tokens: 2000
  tasks: 2
  commits: 4

tech-stack:
  added: []
  patterns:
    - "A when() closure on a scheduled command reads the business zone through ActiveConfig when schedule:run asks, never at routes/console.php load"
    - "Scheduler filter tested by filtersPass(app()) under travelTo(), no schedule:run needed"

key-files:
  created:
    - tests/Feature/OwnerIntelligence/NightlyRebuildTest.php
  modified:
    - routes/console.php
    - docs/SETUP.md
    - docs/sprints/sprint-1.1.md
    - docs/BLUEPRINT.md

key-decisions:
  - "The nightly rebuild is scheduled by the platform, not the owner intelligence room, so it runs whether or not the room is live (D-09)"
  - "00:10 in the business zone, decided by a when() filter on an every-minute event rather than dailyAt() or ->timezone(), so the zone is read lazily and follows a synced change (D-10)"
  - "No sweep at a visit's start: the nightly redraw of every card clears a stale Next appointment line, closing 08 IN-06 (D-11)"

patterns-established:
  - "Lazy zone read: any scheduled job that must follow business/profile.md uses a when() closure taking ActiveConfig"

requirements-completed: [RECS-02]

coverage:
  - id: D1
    description: "records:rebuild is on the scheduler every minute, named, withoutOverlapping(120) and onOneServer()"
    requirement: "RECS-02"
    verification:
      - kind: integration
        ref: "tests/Feature/OwnerIntelligence/NightlyRebuildTest.php#schedules records:rebuild every minute, one at a time, on one server (D-10)"
        status: pass
    human_judgment: false
  - id: D2
    description: "The filter passes only at 00:10 in the business zone, follows a synced zone change, and passes exactly once on the 25-hour and 23-hour daylight-saving days"
    requirement: "RECS-02"
    verification:
      - kind: integration
        ref: "tests/Feature/OwnerIntelligence/NightlyRebuildTest.php#lets it through only at 00:10 in the business zone (D-10)"
        status: pass
      - kind: integration
        ref: "tests/Feature/OwnerIntelligence/NightlyRebuildTest.php#follows a changed zone after a sync (D-10)"
        status: pass
      - kind: integration
        ref: "tests/Feature/OwnerIntelligence/NightlyRebuildTest.php#passes once on each daylight-saving day (D-10)"
        status: pass
    human_judgment: false
  - id: D3
    description: "The 00:10 run turns today.md over to the new day, runs with the room off, and clears yesterday's Next appointment line (08 IN-06)"
    requirement: "RECS-02"
    verification:
      - kind: integration
        ref: "tests/Feature/OwnerIntelligence/NightlyRebuildTest.php#turns today.md over to the new day (D-10)"
        status: pass
      - kind: integration
        ref: "tests/Feature/OwnerIntelligence/NightlyRebuildTest.php#runs with the owner intelligence room off (D-09)"
        status: pass
      - kind: integration
        ref: "tests/Feature/OwnerIntelligence/NightlyRebuildTest.php#clears a Next appointment line for a visit that started yesterday (D-11, 08 IN-06)"
        status: pass
    human_judgment: false
  - id: D4
    description: "Loading routes/console.php reads nothing, and a throwing zone read is reported and returns false so schedule:run carries on"
    requirement: "RECS-02"
    verification:
      - kind: integration
        ref: "tests/Feature/OwnerIntelligence/NightlyRebuildTest.php#reads nothing when the schedule loads (Pitfall 1)"
        status: pass
      - kind: integration
        ref: "tests/Feature/OwnerIntelligence/NightlyRebuildTest.php#reports a bad zone read and lets the pass go on"
        status: pass
    human_judgment: false
  - id: D5
    description: "Production cron runs schedule:run every minute and today.md turns over after 00:10 business time on a live deploy; CI pgsql leg green"
    requirement: "RECS-02"
    verification: []
    human_judgment: true
    rationale: "Needs a real cron and a deployed server (Phase 12) and the pushed branch's CI pgsql run; no automated test here can observe either"

duration: 16min
completed: 2026-11-17
status: complete
plan_head_before: c0920d0efeca0fa63d31c6f24a9f5e51bcf26cfd
plan_head_after: 146f9ea7614d4bf78da6bf79dead30015393af20
---

# Phase 9 Plan 6: Nightly Rebuild Summary

**One `Schedule::command('records:rebuild')` entry, passed by a `when()` filter only when the clock in the business's zone reads 00:10, one run at a time on one server, with 10 Pest cases covering both daylight-saving days, a zone change and a throwing read.**

## Performance

- **Duration:** about 16 min
- **Started:** 2026-11-17T16:15:00Z (approximate)
- **Completed:** 2026-11-17T16:31:00Z
- **Tasks:** 2
- **Files modified:** 5 (1 created, 4 modified)

## Accomplishments
- `routes/console.php` schedules `records:rebuild` every minute, named, `withoutOverlapping(120)`, `onOneServer()`, gated by a `when()` closure that asks `ActiveConfig::displayTimezone()` when the scheduler asks (never at file load) and returns `now($zone)->format('H:i') === '00:10'`. A throwing read is reported and returns false.
- The 00:10 run turns `today.md` over to the new day, works with the owner intelligence room off (card written, no `today.md`), and clears a card's Next appointment line for a visit that started the day before, closing Phase 8 review IN-06 with no start-of-visit sweep.
- docs/SETUP.md section 12 says the scheduler must run every minute and names the few zones where 00:10 is skipped one night a year; the walk-through log and BLUEPRINT section 7 (Built bullet, row 7) are updated.

## TDD Gate Compliance

- **RED (763bbb5):** `NightlyRebuildTest.php` with four cases. Three failed on the planned assertion (`expect($events)->toHaveCount(1)` got 0: no event named `records:rebuild`); the fourth (loading `routes/console.php` runs no query) passed at once, because a file with no read trivially reads nothing. It stays as a guard on the prohibition. Evidence: Pest `--log-junit` report fed to `check tdd-red-evidence` returned `RED_EVIDENCE_OK` (`target_test_failed`, format junit). Semantic assessment: the target test executed and failed because no schedule entry existed, not from a load, syntax or fixture fault.
- **GREEN (6c6f70e):** `routes/console.php` entry; all 13 tests across NightlyRebuildTest, BookingScheduleTest and SweepUndispatchedTest passed.
- **Task 2 RED/GREEN:** the six added cases (zone change, two daylight-saving days as a dataset, bad read, room off, IN-06) all passed at once against Task 1's entry (45c325c); no `fix(09-06)` commit was needed. No expectation was changed.
- **REFACTOR:** none needed.

## Task Commits

1. **Task 1: Tracer, the scheduler passes records:rebuild at 00:10 business time** - `763bbb5` (test), `6c6f70e` (feat)
2. **Task 2: zone change, daylight-saving days, bad read, room off, IN-06** - `45c325c` (test), `146f9ea` (docs)

**Plan metadata:** the final `docs(09-06)` commit (SUMMARY, STATE, ROADMAP, REQUIREMENTS, WINDOWS).

## Files Created/Modified
- `routes/console.php` - the nightly `records:rebuild` entry with its lazy 00:10 filter
- `tests/Feature/OwnerIntelligence/NightlyRebuildTest.php` - 10 cases (the daylight-saving case is a two-row dataset)
- `docs/SETUP.md` - scheduler-every-minute bullet and the skipped-night note
- `docs/sprints/sprint-1.1.md` - walk-through line `(plan 09-06)`
- `docs/BLUEPRINT.md` - section 7 Built bullet and the row 7 wording

## Decisions Made
- Followed the plan's decisions D-09, D-10 and D-11 as written. The closure catches `Throwable`, reports it and returns false so one bad read never ends the `schedule:run` pass for the alert and reminder jobs (T-09-22).

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] `use Throwable;` is rejected in routes/console.php**
- **Found during:** Task 1 (GREEN)
- **Issue:** The plan said to import `Throwable`. In a file with no namespace, PHP warns "The use statement with non-compound name 'Throwable' has no effect", and Laravel turns that warning into an ErrorException on every Artisan boot.
- **Fix:** No import; the closure writes `catch (Throwable $exception)`, which resolves to the global class. (Pint's `fully_qualified_strict_types` rewrites a leading backslash back to this form.)
- **Files modified:** routes/console.php
- **Verification:** NightlyRebuildTest, BookingScheduleTest, SweepUndispatchedTest all pass; `php artisan schedule:list` lists the event.
- **Committed in:** 6c6f70e

---

**Total deviations:** 1 auto-fixed (1 blocking)
**Impact on plan:** None on behavior; one import line omitted.

## Issues Encountered
- The full suite reports `"warnings":1` with no detail; the run is otherwise green (3333 passed). The warning is not from files this plan touched; not investigated further.

## Verification
- `php artisan test --compact tests/Feature/OwnerIntelligence/NightlyRebuildTest.php tests/Feature/Booking/BookingScheduleTest.php tests/Feature/Inbound/SweepUndispatchedTest.php`: 13 passed.
- `php artisan test --compact tests/Feature/OwnerIntelligence tests/Feature/Records`: 105 passed.
- Phase gate `php artisan test --compact` on SQLite: 3333 passed, 26006 assertions, exit 0.
- Acceptance greps (routes/console.php, SETUP, sprint log, BLUEPRINT) all succeed; `php artisan schedule:list` lists `records:rebuild`.
- Left unrun by design and recorded as `unrun-verify` entries in `.planning/WINDOWS.md` for end-of-phase UAT: (1) CI pgsql leg green for the whole suite after Ian pushes the branch; (2) at first deploy (Phase 12), cron runs `schedule:run` every minute and `records/today.md` names the new day after 00:10 business time.

## Known Stubs

None.

## Threat Flags

None. The plan's register (T-09-20 to T-09-22) is mitigated and tested; T-09-23 is accepted and documented in SETUP.

## User Setup Required

None - no external service configuration required. Production needs cron running `php artisan schedule:run` every minute (documented in docs/SETUP.md section 12; first exercised at Phase 12 deploy).

## Next Phase Readiness
- This is the last plan of Phase 9; all six plans have SUMMARYs. Ready for phase verification and end-of-phase UAT (including the two recorded hand checks).

## Self-Check: PASSED

- `tests/Feature/OwnerIntelligence/NightlyRebuildTest.php` and the five touched files exist.
- Commits 763bbb5, 6c6f70e, 45c325c, 146f9ea are ancestors of HEAD.

---
*Phase: 09-owner-intelligence*
*Completed: 2026-11-17*
