---
phase: 02-text-front-desk
plan: 01
subsystem: testing
tags: [pest, postgres, github-actions, ci, http-fake]

requires:
  - phase: 01-foundation
    provides: the 281-test Pest suite and the example-hvac folder it copies
provides:
  - The full Pest suite passing on SQLite and on PostgreSQL 17 (local docker recipe in docs/SETUP.md)
  - .github/workflows/tests.yml running both databases on every push and pull request
  - Http::preventStrayRequests() under every Feature test, plus TestSafetyNetTest
  - exampleFolderFileCount() global test helper (8) as the one place the example folder's file count lives
  - docs/sprints/sprint-1.md with the Phase 2 done-when list and the walk-through log
affects: [02-02, 02-03, 02-06, every later Phase 2 plan]

plan_head_before: 93ce0784d0bca220747a980b7cd7e41bd981ccd6
plan_head_after: cc71e7a7b91be728f9ff5db52e246f1193fb2768

actuals:
  tokens: 6250
  tasks: 2
  commits: 3

tech-stack:
  added: []
  patterns:
    - "Tests read version labels from the IcmVersion row (label()), never as literals"
    - "File-specific suite-wide helpers in tests/Pest.php; exampleFolderFileCount() is the one count of example-folder files"
    - "Environment variables override phpunit.xml <env> defaults, so CI and the local recipe pick the database from the shell"

key-files:
  created:
    - .github/workflows/tests.yml
    - tests/Feature/Quality/TestSafetyNetTest.php
    - docs/sprints/sprint-1.md
  modified:
    - tests/Pest.php
    - tests/Feature/Icm/SyncTest.php
    - tests/Feature/Icm/ChangelogTest.php
    - tests/Feature/Icm/SyncIgnoresRecordsTest.php
    - tests/Feature/Icm/ValidatorTest.php
    - tests/Feature/Filament/BusinessFolderPageTest.php
    - tests/Feature/Records/RecordsRebuildTest.php
    - tests/Feature/Records/CustomerCardTest.php
    - docs/SETUP.md

key-decisions:
  - "Fixed the 17 failures and 1 error on Postgres test-side only: labels built from the row, long-name fixture cut to 250 characters; no app code and no column change"
  - "Actions pinned to full commit SHAs (actions/checkout v7.0.1, shivammathur/setup-php 2.40.0) looked up through gh"
  - "Later-sprint fixtures moved to automations/follow-up.md (type automation) so plan 02-02 can make type agent a real type without a path collision"

patterns-established:
  - "Stray HTTP fails every Feature test; a test that needs HTTP calls Http::fake itself"
  - "Quality test compares DB::connection()->getDriverName() with env('DB_CONNECTION'), so a run never silently uses the wrong database"

requirements-completed: [INFRA-01]

coverage:
  - id: D1
    description: "The unchanged suite passes on PostgreSQL 17 through the local docker recipe and on SQLite"
    requirement: INFRA-01
    verification:
      - kind: integration
        ref: "DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=ops_test DB_USERNAME=postgres DB_PASSWORD=probe php artisan test --compact (284 passed)"
        status: pass
      - kind: integration
        ref: "php artisan test --compact (284 passed)"
        status: pass
    human_judgment: false
  - id: D2
    description: "A workflow file runs the suite in a sqlite and pgsql matrix in GitHub Actions"
    requirement: INFRA-01
    verification: []
    human_judgment: true
    rationale: "The repo has no git remote, so the first CI run cannot be observed from this machine; Ian pushes the branch and confirms the workflow"
  - id: D3
    description: "A stray web request fails any Feature test, and a wrong database driver fails a quality test"
    verification:
      - kind: unit
        ref: "tests/Feature/Quality/TestSafetyNetTest.php"
        status: pass
    human_judgment: false
  - id: D4
    description: "The later-sprint fixtures use type automation at automations/follow-up.md and still show a 'Not supported yet' notice"
    verification:
      - kind: unit
        ref: "tests/Feature/Icm/SyncTest.php, tests/Feature/Icm/ChangelogTest.php, tests/Feature/Filament/BusinessFolderPageTest.php"
        status: pass
    human_judgment: false
  - id: D5
    description: "Every statement of the example folder's file count reads exampleFolderFileCount()"
    verification:
      - kind: other
        ref: "grep -rn '8 files\\|toBe(8)\\|toBe(9)' tests returns no match"
        status: pass
    human_judgment: false
  - id: D6
    description: "docs/sprints/sprint-1.md exists with the Phase 2 goal, done-when list and walk-through log; docs/SETUP.md has the Postgres recipe"
    verification: []
    human_judgment: true
    rationale: "Documentation wording; no test asserts it reads well"

duration: 4min
completed: 2026-10-12
status: complete
---

# Phase 2 Plan 01: Wave 0 Test Foundation Summary

**The 281-test suite (284 with the safety net) now passes on SQLite and PostgreSQL 17, a two-database GitHub Actions workflow with SHA-pinned actions is in place, and every Feature test fails on a stray web request.**

## Performance

- **Duration:** 4 min
- **Started:** 2026-10-12T01:21:48Z
- **Completed:** 2026-10-12T01:25:28Z
- **Tasks:** 2
- **Files modified:** 12 (3 created, 9 modified)

## Accomplishments

- Reproduced the Postgres baseline first (281 tests, 263 passed, 17 failed, 1 error), then fixed it test-side: version labels come from `IcmVersion::label()` and the long-name fixture is 250 characters.
- `.github/workflows/tests.yml` runs `php artisan test` in a `sqlite` and `pgsql` matrix with a `postgres:17` service, `permissions: contents: read`, and a step that prints the live driver.
- `Http::preventStrayRequests()` in the Feature-wide `beforeEach`, proved by `TestSafetyNetTest` (stray request throws, a faked request still answers, driver equals `DB_CONNECTION`).
- `exampleFolderFileCount()` replaces every literal example-folder file count in five test files.
- Three fixtures moved from `agents/front-desk.md` (`type: agent`) to `automations/follow-up.md` (`type: automation`).
- `docs/sprints/sprint-1.md` opened with three walk-through lines; `docs/SETUP.md` gained the Postgres recipe.

## Task Commits

1. **Task 1: Suite green on PostgreSQL, CI workflow runs both databases (tracer)** - `745a0d2` (test)
2. **Task 2: Safety net, fixtures off type agent, sprint file (TDD)**
   - RED: `3337e9a` (test)
   - GREEN: `cc71e7a` (test, the commit message the plan prescribed)

**Plan metadata:** committed separately (docs: complete plan)

## TDD note (Task 2)

- RED: `TestSafetyNetTest` "fails any test that makes a real web request without faking it first" failed with `Exception [RuntimeException] not thrown.` (the real request went out); the other two tests passed, as expected, since faking and the driver check already held. Semantic assessment: the target test ran and failed on the planned assertion for the intended reason. The TDD runtime gate was inactive for this task (its files are tests, docs and `tests/Pest.php`, no app source), and the project's Pest JSON report is not a format `gsd_run check tdd-red-evidence` reads, so no classifier record was produced.
- GREEN: `Http::preventStrayRequests()` in `tests/Pest.php`; all 3 tests pass, then both full suites.
- No REFACTOR commit.
- The plan prescribed a `test(02-01)` message for the GREEN commit because the change is test infrastructure, so there is no `feat(02-01)` commit. The plan is `type: execute`, not `type: tdd`, so the plan-level gate sequence does not apply.

## Files Created/Modified

- `.github/workflows/tests.yml` - CI on SQLite and PostgreSQL 17, actions pinned to SHAs
- `tests/Pest.php` - `Http::preventStrayRequests()` in the Feature-wide `beforeEach`; `exampleFolderFileCount()` helper
- `tests/Feature/Quality/TestSafetyNetTest.php` - proof of the safety net and the driver check
- `tests/Feature/Icm/SyncTest.php`, `ChangelogTest.php`, `tests/Feature/Filament/BusinessFolderPageTest.php`, `tests/Feature/Records/RecordsRebuildTest.php` - labels from the row; fixtures and counts through the helper
- `tests/Feature/Icm/SyncIgnoresRecordsTest.php`, `ValidatorTest.php` - counts through the helper
- `tests/Feature/Records/CustomerCardTest.php` - 250-character long-name fixture
- `docs/sprints/sprint-1.md` - Phase 2 sprint file and walk-through log
- `docs/SETUP.md` - local PostgreSQL recipe

## Decisions Made

- Test-side fixes only, as research found; the `contacts.name` column was not widened.
- `actions/checkout` pinned to `3d3c42e5aac5ba805825da76410c181273ba90b1` (v7.0.1) and `shivammathur/setup-php` to `eb7c497e18156a6bbabfef1d3a82760b9eda3962` (2.40.0), both resolved through `gh api`, so the `@v7` / `@v2` fallback was not needed.
- Tests that read a count of example-folder files interpolate a local `$files = exampleFolderFileCount()` into the expected string.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None. The first `postgres:17` run had to pull the image, which only made the first baseline slower.

## User Setup Required

None - no external service configuration required. Ian pushes the branch to confirm the first real CI run (the repo has no git remote).

## Known Stubs

None.

## Threat Flags

None. The workflow adds no secrets and `permissions: contents: read`; the plan's T-02-01, T-02-02 and T-02-03 mitigations are all in place.

## Next Phase Readiness

- Ready for 02-02. That plan makes `type: agent` real and adds `agents/front-desk.md` to the example folder, so it must raise `exampleFolderFileCount()` from 8 (the one number to change).
- Open: the first CI run is unverified until Ian pushes (flagged assumption; the local docker recipe is the automated stand-in). Port 55432 was free on this machine.

## Self-Check: PASSED

- Created files exist: `.github/workflows/tests.yml`, `tests/Feature/Quality/TestSafetyNetTest.php`, `docs/sprints/sprint-1.md`.
- Commits `745a0d2`, `3337e9a`, `cc71e7a` are on `gsd/phase-02-text-front-desk`.
- Acceptance criteria re-run: workflow contains `pgsql`, `sqlite`, `postgres:17`, `contents: read`, `php artisan test`; `label()` appears in SyncTest, ChangelogTest and BusinessFolderPageTest; the long name is 25 x 10 = 250 characters; `grep -rn "type: agent" tests/Feature` finds nothing; `exampleFolderFileCount()` appears in all five files; sprint-1.md has `## Walk-through log`, `## Done when` and `preventStrayRequests`; SETUP.md has `postgres:17` and `DB_CONNECTION=pgsql`.
- Verification: 284 passed on SQLite and 284 passed on PostgreSQL 17.

---
*Phase: 02-text-front-desk*
*Completed: 2026-10-12*
