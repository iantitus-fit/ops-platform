---
phase: 06-front-desk-and-team-rooms
plan: 01
subsystem: testing
tags: [pest, room-registry, folder-sync, icm, test-prep]

requires:
  - phase: 05-standard-rooms
    provides: the hand-built RoomRegistry unit-test pattern (05 D-23) and RoomChecks branches the plan keeps covered
provides:
  - tests/Feature/Rooms/HandBuiltRegistrySyncTest.php, a whole-sync case over a registry the test builds, covering the "previous version stays live after an absent room's file is refused" branch
  - RoomOwnershipTest without its three triage-only cases, so the front desk wrap can land changing only the example folder's file count
affects: [06-front-desk-and-team-rooms, rooms, folder-sync]

plan_head_before: 691de08b2ddbf1e68aa903a60e9d2da84d84ed7e
plan_head_after: 8c1fec7010caca4e22a5cada1f4c7d0328d23a60

actuals:
  tokens: 2600
  tasks: 2
  commits: 2

tech-stack:
  added: []
  patterns:
    - "Hand-built FolderSync: new FolderSync(new Loader($registry), new Validator(...), app(Changelog::class), $registry) so a test syncs against only the rooms it registers"

key-files:
  created:
    - tests/Feature/Rooms/HandBuiltRegistrySyncTest.php
  modified:
    - tests/Feature/Rooms/RoomOwnershipTest.php
    - tests/Unit/Icm/RoomChecksTest.php
    - docs/BLUEPRINT.md

key-decisions:
  - "The line-77, line-160 and line-185 branches all follow the unit-first pattern; none is re-pointed at a test-only room, because the front desk will handle every hard-coded type (06 D-14, research Pitfall 1)"

patterns-established:
  - "A test that needs the real sync but not the real rooms builds its own FolderSync over a test-only RoomRegistry and removes rooms/ and connectors/ from the temporary folder"

requirements-completed: [ROOM-06]

coverage:
  - id: D1
    description: "The line-185 branch (an absent room that alone handles a file's type is refused with one line, and the previous version stays live) has its own case over a hand-built registry, passing on today's code"
    requirement: ROOM-06
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/HandBuiltRegistrySyncTest.php#keeps the previous version live when an absent room's file is refused, with a registry the test builds itself"
        status: pass
    human_judgment: false
  - id: D2
    description: "The line-77 and line-160 branches stay covered by the two existing hand-built RoomChecks cases"
    requirement: ROOM-06
    verification:
      - kind: unit
        ref: "tests/Unit/Icm/RoomChecksTest.php#says no room handles a type yet when a present room claims a file of that type"
        status: pass
      - kind: unit
        ref: "tests/Unit/Icm/RoomChecksTest.php#names the absent room when the only room that handles a type is not in the files"
        status: pass
    human_judgment: false
  - id: D3
    description: "RoomOwnershipTest loses exactly its three triage-only cases (18 to 15) and the whole suite stays green"
    requirement: ROOM-06
    verification:
      - kind: other
        ref: "php artisan test --compact (2202 passed)"
        status: pass
    human_judgment: false
  - id: D4
    description: "The new case also passes on Postgres in CI"
    requirement: ROOM-06
    verification: []
    human_judgment: true
    rationale: "Needs Ian to push the phase branch so the CI pgsql leg runs; carried to end-of-phase UAT and recorded in .planning/WINDOWS.md"

duration: 8min
completed: 2026-10-27
status: complete
---

# Phase 6 Plan 01: Move the triage-only ownership tests Summary

**The three triage-only RoomOwnershipTest cases are gone, each branch they reached now has a hand-built-registry test (one new whole-sync case, two existing RoomChecks cases), and the suite is green on today's code.**

## Performance

- **Duration:** 8 min
- **Started:** 2026-10-27T21:41:55Z
- **Completed:** 2026-10-27T21:50:00Z
- **Tasks:** 2
- **Files modified:** 4 (1 created, 3 modified)

## Accomplishments

- `tests/Feature/Rooms/HandBuiltRegistrySyncTest.php` builds its own `FolderSync` over a registry holding one test room (`rulebook`, handles `triage`). The first sync activates; with the room's folder removed the second is refused with exactly the one line naming the rulebook room, and `ActiveConfig::versionId()` still equals the first version.
- `RoomOwnershipTest` loses exactly the cases at old lines 77, 160 and 185 (18 cases to 15). Its helpers and imports are all still used.
- `RoomChecksTest`'s header comment now explains why the hand-built cases stay (every hard-coded type gets a real room in Phase 6) and points at the new feature file. No case changed.
- BLUEPRINT section 8's D-14 bullet is marked `Built` in the same commit as the removal.

## Task Commits

1. **Task 1: Tracer, hand-built registry sync keeps the previous version live** - `8b0cbd4` (test)
2. **Task 2: Remove the three triage-only feature tests, update the comment, mark BLUEPRINT built** - `8c1fec7` (test)

**Plan metadata:** recorded in the docs commit that follows this file.

Tracer gate: `php artisan test --compact tests/Feature/Rooms/HandBuiltRegistrySyncTest.php tests/Unit/Icm/RoomChecksTest.php` re-run end to end passed (5 tests) before Task 2 began.

## Files Created/Modified

- `tests/Feature/Rooms/HandBuiltRegistrySyncTest.php` - the line-185 case, with `handBuiltRegistrySync()` and `handBuiltRoomContract()` helpers (names unique in the suite)
- `tests/Feature/Rooms/RoomOwnershipTest.php` - three cases removed
- `tests/Unit/Icm/RoomChecksTest.php` - header comment only
- `docs/BLUEPRINT.md` - D-14 bullet now `Built`, says the tests moved in plan 06-01

## Decisions Made

None beyond the plan. All three branches follow the unit-first pattern; none is re-pointed at a test-only room (06 D-14, research Pitfall 1).

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None. The first test run was a full-suite baseline (2205 passing, which counted the new file's generated stub); after the work the suite is 2202 passing (the stub is replaced by the real case, minus the three removed).

## Hand Check Carried to UAT

Postgres: the new case has no database-driver guard. After Ian pushes the phase branch, the CI tests workflow's pgsql leg should run `tests/Feature/Rooms/HandBuiltRegistrySyncTest.php` and the rest of the suite green. Recorded as an `unrun-verify` entry in `.planning/WINDOWS.md`; no pause.

## Known Stubs

None.

## Threat Flags

None. The plan adds no endpoint, agent action or schema change; T-6-01 is mitigated as planned (each branch has a passing hand-built test before removal, and exactly three cases were removed).

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

The front desk wrap can now land: the only test fallout from handling `sending_rules`, `set_replies`, `triage` and `agent` should be the example folder's file count. No blockers.

## Self-Check: PASSED

- FOUND: tests/Feature/Rooms/HandBuiltRegistrySyncTest.php
- FOUND commits 8b0cbd4 and 8c1fec7 (both ancestors of HEAD)
- `grep -c '^it(' tests/Feature/Rooms/RoomOwnershipTest.php` is 15; Task 2 commit shows 3 removed `it(` lines and 0 added; RoomChecksTest has no `it(` line changes
- Task 1 commit lists only the new test file; Task 2 commit lists exactly RoomOwnershipTest, RoomChecksTest and BLUEPRINT
- `php artisan test --compact`: 2202 passed

---
*Phase: 06-front-desk-and-team-rooms*
*Completed: 2026-10-27*
