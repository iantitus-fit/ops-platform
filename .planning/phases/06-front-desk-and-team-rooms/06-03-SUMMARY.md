---
phase: 06-front-desk-and-team-rooms
plan: 03
subsystem: rooms
tags: [laravel, room-standard, team, owner-alerts, icm, never-left-out]

requires:
  - phase: 06-front-desk-and-team-rooms
    provides: "Room::$claimedWidgets and RoomRegistry::guardClaimedWidgets() (06-02), the front desk room whose files the team reads"
provides:
  - "App\\Rooms\\Team\\TeamServiceProvider: room key team, owner_alerts table, claims DraftsWaiting as a pointer, can never be left out"
  - "examples/example-hvac/rooms/team/CONTEXT.md: contract that owns none and reads three folder paths"
  - "tests/Feature/Rooms/TeamBootTest.php: boot test and three never-left-out cases"
affects: [phase-06 ALRT work, phase-07 leads room, phase-11 accessibility pass]

plan_head_before: 88afa527a1792357950906d29280de3b78e2145a
plan_head_after: 3570d34f652a4a426b5103cfa6932518f0b4f403

actuals:
  tokens: 4000
  tasks: 2
  commits: 4

tech-stack:
  added: []
  patterns:
    - "A room that owns no file: owns none, one boot case because the empty and example folders are the same folder (04 D-21)"
    - "Never-left-out reason kept on a room that owns nothing, so the sync refuses a folder without it"

key-files:
  created:
    - app/Rooms/Team/TeamServiceProvider.php
    - examples/example-hvac/rooms/team/CONTEXT.md
    - tests/Feature/Rooms/TeamBootTest.php
  modified:
    - bootstrap/providers.php
    - tests/Pest.php
    - docs/SPEC.md
    - docs/BLUEPRINT.md

key-decisions:
  - "The team's who-acted read is body prose in its contract ('who resolved each draft'), never a reads entry; reads holds folder paths only (D-04, T-6-05)"
  - "Never-left-out reason: the front desk alerts the owner through it, and the life-safety alert goes out at any hour"

requirements-completed: [ROOM-15]

coverage:
  - id: D1
    description: "The team is a registered room: key team, owner_alerts table, DraftsWaiting claimed as a pointer, empty contract ownership, three folder-path reads, listed after the front desk in bootstrap/providers.php"
    requirement: ROOM-15
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/TeamBootTest.php#boots the team room"
        status: pass
    human_judgment: false
  - id: D2
    description: "A folder without rooms/team/ is refused with one line and the last good version stays live; with the front desk also gone, two lines in key order, stable across syncs"
    requirement: ROOM-15
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/TeamBootTest.php#refuses a folder with no team room in one line and keeps the previous version live"
        status: pass
      - kind: integration
        ref: "tests/Feature/Rooms/TeamBootTest.php#gives the front desk line then the team line, in key order, when both rooms are left out"
        status: pass
      - kind: integration
        ref: "tests/Feature/Rooms/TeamBootTest.php#gives the same lines in the same order when the same refused folder is synced twice"
        status: pass
    human_judgment: false
  - id: D3
    description: "The wrap changes no alert, approval or who-acted behavior: whole suite passes with the file count (24 to 25) as the only existing test change"
    requirement: ROOM-15
    verification:
      - kind: other
        ref: "php artisan test --compact (2215 passed)"
        status: pass
    human_judgment: false
  - id: D4
    description: "Postgres leg of success criterion 1 (CI pgsql green with both wraps registered)"
    verification: []
    human_judgment: true
    rationale: "Needs Ian to push the phase branch (or a local Postgres run); executors never push and never pause for a hand check"

duration: 14 min
completed: 2026-10-28
status: complete
---

# Phase 6 Plan 03: The Team Room Summary

**The team joins the room standard: one provider, a contract that owns no file and reads three, the owner_alerts table, Drafts waiting claimed as a pointer, and a never-left-out line, with no change to any alert, approval or who-acted record.**

## Performance

- **Duration:** 14 min
- **Started:** 2026-10-28T00:22:00Z
- **Completed:** 2026-10-28T00:37:00Z
- **Tasks:** 2
- **Files modified:** 7 (3 created, 4 modified)

## Accomplishments

- `TeamServiceProvider` registers room `team` with tables `['owner_alerts']`, no handles, slot, sections, timeline entries or morph map, and claims `DraftsWaiting` in `claimedWidgets`. It is listed after `FrontDeskServiceProvider` with `AppServiceProvider` still first.
- `examples/example-hvac/rooms/team/CONTEXT.md` has `owns: none`, `reads: [rules/sending.md, rules/replies.md, agents/front-desk.md]`, `writes_records: none`, `connector: none`. The body says the team "reads who resolved each draft" as prose. The example folder holds 25 files.
- A folder without `rooms/team/` is refused with the one never-left-out line. With both the front desk and the team removed, the two lines come front desk first, and a second sync gives the same lines. The live version stays live in each case.
- The dashboard still shows Drafts waiting, Customers waiting while you handle it, New leads and Today's conversations in that order, and the panel holds DraftsWaiting once.
- SPEC says "the front desk and team from Phase 6". BLUEPRINT section 6 has the team Built bullet, with the never-left-out words, and glance row 6 is updated.

## Task Commits

1. **Task 1 (tracer): the team is registered** - RED `79a271a` (test), GREEN `23980b3` (feat)
2. **Task 2: the team can never be left out** - RED `774306e` (test), GREEN `3570d34` (feat)

**Plan metadata:** committed with this SUMMARY (docs: complete plan)

## TDD Gate Compliance

- Task 1 RED: `hasRoom('team')` returned false (assertion 3 of 28 failed at the planned point; file_count already matched at 24). Semantic assessment: the target test ran and failed on the intended assertion because the provider and contract did not exist yet, not on a syntax or fixture fault.
- Task 2 RED: the three new cases failed. The sync exited 0 instead of 1 for the team-only removal, and the two-room cases showed only the front desk line. Semantic assessment: they failed on the planned assertions because the team had no never-left-out reason.
- Both GREEN commits followed their RED commits, and the targeted and full suites passed (2212 tests after Task 1, 2215 after Task 2). No REFACTOR commit was needed.
- The separate `gsd_run check tdd-red-evidence` classifier is not wired for Pest output, so RED was assessed by reading the runner's JSON failure messages.

## Tracer Gate

After Task 1 the tracer `<verify>` (targeted suite and full suite) was re-run end to end and passed (60 targeted tests, 2212 total), so expansion went ahead.

## Deviations from Plan

None - plan executed exactly as written. One note: Task 1's BLUEPRINT Built bullet was written with "and reads who resolved each draft from `messages`", and Task 2 reworded that clause to add the never-left-out words, as the plan directed.

## Issues Encountered

None. The only existing test change is `exampleFolderFileCount()` 24 to 25 in `tests/Pest.php`. `git diff main -- tests` also lists the 06-01 and 06-02 test files, which earlier plans changed.

## Unrun hand check (for end-of-phase UAT)

The plan's Postgres check was left unrun, as Ian's standing rule requires. After Ian pushes the phase branch, the CI tests workflow's pgsql leg should be green with both wraps registered and no ALRT change yet; the commit at the end of this plan proves success criterion 1 on Postgres. A local run is possible with `docker run -d --rm --name ops-pg -e POSTGRES_PASSWORD=postgres -e POSTGRES_DB=ops_test -p 55432:5432 postgres:17`, then `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=ops_test DB_USERNAME=postgres DB_PASSWORD=postgres php artisan test --compact`, then `docker stop ops-pg`. It is recorded as an `unrun-verify` entry in `.planning/WINDOWS.md`.

## Known Stubs

None.

## Threat Flags

None. No new endpoint, auth path or schema change; T-6-05 and T-6-06 are mitigated as planned (reads holds folder paths only and the boot test pins them; the never-left-out line protects the owner's alerts).

## Walk-through log

No new Laravel concept appeared (the claimedWidgets pointer was explained in 06-02), so no walk-through line was added.

## Next Phase Readiness

Both wraps are in before any ALRT work (success criterion 1). Plan 06-04 can start on the alert changes with the team room's table and widget pointer already in place.

## Self-Check: PASSED

- Files present: app/Rooms/Team/TeamServiceProvider.php, examples/example-hvac/rooms/team/CONTEXT.md, tests/Feature/Rooms/TeamBootTest.php.
- Commits present: 79a271a, 23980b3, 774306e, 3570d34.
- Acceptance criteria for both tasks re-run and passing; plan-level verification (full suite) passes.

---
*Phase: 06-front-desk-and-team-rooms*
*Completed: 2026-10-28*
