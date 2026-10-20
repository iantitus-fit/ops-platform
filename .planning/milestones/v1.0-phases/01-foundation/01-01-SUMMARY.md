---
phase: 01-foundation
plan: 01
subsystem: infra
tags: [laravel, icm, yaml-front-matter, symfony-yaml, eloquent, artisan, pest, boost]

requires: []
provides:
  - "examples/example-hvac: fictional Example Heating & Air business folder (8 typed files plus router files)"
  - "icm_versions table, IcmVersion model, factory with active/rejected/superseded states"
  - "App\\Icm\\Loader, FolderSync (lock, fingerprint, no-op, transactional activation), SyncResult"
  - "App\\Icm\\ActiveConfig (scoped): the only runtime read path for business configuration"
  - "php artisan icm:sync and icm:status"
  - "Pest temp-folder harness and helpers icmPath, activateExampleFolder, breakFile, writeIcmFile"
  - ".ai/rules: integer cents and config-only-through-ActiveConfig, guarded by tests"
affects: [01-02 validator and changelog, 01-03 records and inbound, 01-07 Business Folder page, phase-02 agents]

actuals:
  tokens: 13126
  tasks: 3
  commits: 7

tech-stack:
  added: []
  patterns:
    - "Single reader of the folder: only App\\Icm\\Loader uses Yaml and Finder (arch-tested)"
    - "Compiled version holds typed front matter and body plus router files as raw text; fingerprint is sha256 over path-sorted compiled bytes"
    - "ActiveConfig bound scoped; FolderSync forgets it after activation"
    - "Sync serialized with Cache::lock('icm:sync'); activation in one DB transaction"
    - "Every Feature test runs against a temp copy of the example folder"

key-files:
  created:
    - examples/example-hvac/
    - app/Icm/Loader.php
    - app/Icm/FolderSync.php
    - app/Icm/ActiveConfig.php
    - app/Icm/SyncResult.php
    - app/Icm/IcmFile.php
    - app/Icm/LoadedFolder.php
    - app/Icm/Exceptions/NoActiveVersion.php
    - app/Icm/Exceptions/IcmFolderMissing.php
    - app/Models/IcmVersion.php
    - app/Enums/VersionStatus.php
    - app/Enums/SyncOutcome.php
    - app/Enums/IcmFileKind.php
    - app/Console/Commands/IcmSync.php
    - app/Console/Commands/IcmStatus.php
    - config/icm.php
    - database/migrations/2026_10_11_125152_create_icm_versions_table.php
    - database/factories/IcmVersionFactory.php
    - .ai/rules/index.md
    - .ai/rules/app.md
    - .ai/rules/migrations.md
    - .ai/rules/factories.md
    - .ai/rules/tests.md
    - tests/Feature/Icm/SyncTest.php
    - tests/Feature/Icm/SyncIgnoresRecordsTest.php
    - tests/Feature/Icm/ActiveConfigTest.php
    - tests/Feature/Icm/LoaderTest.php
    - tests/Feature/Quality/ProjectRulesTest.php
    - tests/Unit/ArchitectureTest.php
  modified:
    - app/Providers/AppServiceProvider.php
    - tests/Pest.php
    - phpunit.xml
    - .gitignore
    - .env.example
    - docs/sprints/sprint-0.md

key-decisions:
  - "The vN label is the icm_versions row id (RESEARCH A8); rejections will consume numbers"
  - "Router files (CLAUDE.md, CONTEXT.md, START-HERE.md) are compiled and fingerprinted, so editing one mints a new version"
  - "An unchanged sync returns the active row in the result and carries the run's notices"
  - "Loader reports malformed front matter as problem 'unreadable:<parser message>' with the file line (parser line plus one for the opening ---); plan 01-02 renders it"
  - "ActiveConfig singleton accessors assume business, hours, lead_sources, voice and sending_rules each exist once; plan 01-02's validator must enforce that before a version can activate"

patterns-established:
  - "Feature tests copy examples/example-hvac to a temp dir in beforeEach and delete it in afterEach; phpunit.xml sets ICM_PATH to a nonexistent path so a missed override fails loudly"
  - "Project rules are recorded with Boost's RuleRepository, never hand-written"

requirements-completed: [ICM-01, QUAL-01]

coverage:
  - id: D1
    description: "icm:sync reads the example folder, compiles it and makes it active as v1 with 8 files"
    requirement: ICM-01
    verification:
      - kind: integration
        ref: "tests/Feature/Icm/SyncTest.php#activates the example folder as v1 with eight files"
        status: pass
    human_judgment: false
  - id: D2
    description: "icm:status shows the active version, file count and Chicago go-live time, and exits 1 when nothing is live"
    requirement: ICM-01
    verification:
      - kind: integration
        ref: "tests/Feature/Icm/SyncTest.php#shows the active version, its file count and its Chicago go-live time"
        status: pass
      - kind: integration
        ref: "tests/Feature/Icm/SyncTest.php#reports that no version is live yet and exits 1"
        status: pass
    human_judgment: false
  - id: D3
    description: "The sync compiles only owner-written files: ignores records/ and root changelog.md, reports later-sprint types as notices, no-ops on an unchanged fingerprint, and refuses to run twice at once"
    requirement: ICM-01
    verification:
      - kind: integration
        ref: "tests/Feature/Icm/SyncIgnoresRecordsTest.php"
        status: pass
      - kind: integration
        ref: "tests/Feature/Icm/SyncTest.php"
        status: pass
    human_judgment: false
  - id: D4
    description: "ActiveConfig exposes typed accessors and raw file lookup, throws NoActiveVersion when nothing is live, returns money as integers, and is scoped"
    requirement: ICM-01
    verification:
      - kind: integration
        ref: "tests/Feature/Icm/ActiveConfigTest.php"
        status: pass
    human_judgment: false
  - id: D5
    description: "The money and ActiveConfig rules are recorded in .ai/rules and YAML/Finder use is pinned to the Loader"
    requirement: QUAL-01
    verification:
      - kind: integration
        ref: "tests/Feature/Quality/ProjectRulesTest.php"
        status: pass
      - kind: unit
        ref: "tests/Unit/ArchitectureTest.php"
        status: pass
    human_judgment: false
  - id: D6
    description: "The example business folder reads like a believable fictional HVAC company an owner could edit"
    verification: []
    human_judgment: true
    rationale: "Wording, realism and usefulness of the fixture content are for Ian to read; tests only assert structure and counts"

duration: 8min
completed: 2026-10-11
status: complete
plan_head_before: 65179dc1b1cb6e9e38830d0e61e88e1af3ac4517
plan_head_after: c57214c61d84cb2b468a175ae2b6aa9e81578df5
---

# Phase 1 Plan 01: Folder Sync Summary

**icm:sync compiles the Example Heating & Air folder into a fingerprinted, lock-protected icm_versions row that App\Icm\ActiveConfig serves as the only runtime config source, with the integer-cents and config-read-path rules recorded in .ai/rules.**

## Performance

- **Duration:** 8 min
- **Started:** 2026-10-11T12:50:12Z
- **Completed:** 2026-10-11T12:58:45Z
- **Tasks:** 3
- **Files modified:** 52 (35 outside the example folder)

## Accomplishments

- A real sync of the example folder goes live as v1 with 8 files; `icm:status` shows version, file count and go-live time in America/Chicago. Verified by `SyncTest`.
- The sync compiles exactly what the owner wrote: top-level `records/` and root `changelog.md` never reach it (nested `business/records/` still does), README/CONTEXT/START-HERE add 0 to the count, later-sprint types become notices, an unchanged folder is a no-op that touches `last_checked_at` and `last_result`, and a held `icm:sync` lock returns Busy.
- `ActiveConfig` returns typed config (business, hours, lead sources, services, voice, sending rules, files) with money as PHP integers, throws `NoActiveVersion` before the first sync, and refreshes after an in-process sync.
- Both Boost project rules are recorded in `.ai/rules` and tests fail if they disappear or if YAML/Finder leak out of the Loader.

## Task Commits

Each task was committed atomically (TDD: test, then feat):

1. **Task 1: End-to-end sync and status (tracer)**
   - RED `ea2d130` (test): failing end-to-end sync test plus the temp-folder harness
   - GREEN `00c0325` (feat): example folder, table, model, Loader, FolderSync, ActiveConfig, commands
   - Tracer verified end to end (SyncTest green, `git check-ignore` checks pass) before expansion.
2. **Task 2: Compile rules, no-op syncs, sync lock**
   - Fix `90ce3f6` (fix): Loader crash on malformed front matter, found while writing the RED tests (see Deviations)
   - RED `882e10c` (test): failing compile and no-op tests
   - GREEN `1769297` (feat): exclusions, later-sprint notices, no-op, cache lock
3. **Task 3: ActiveConfig read API and project rules**
   - RED `5506c4d` (test): failing ActiveConfig, rules and arch tests
   - GREEN `c57214c` (feat): accessors, `.ai/rules`, walk-through log

**Plan metadata:** recorded in the docs commit that follows this file.

## RED Evidence (TDD)

All three RED runs were classified with `gsd_run check tdd-red-evidence` from the unchanged PHPUnit JUnit report and returned `RED_EVIDENCE_OK` (`target_test_failed`).

- Task 1: all 3 SyncTest tests errored with `The command "icm:sync" does not exist.` Semantic assessment: the target executed and failed for the planned reason.
- Task 2: 6 of 14 tests failed on planned assertions (no `Unchanged` outcome, file count 9 instead of 8, missing notice, missing no-op message, exit 0 instead of 1 while locked).
- Task 3: accessor tests failed on undefined methods and rule tests on missing `.ai/rules` files. The three arch tests and a few already-implemented cases passed at RED because they guard code that Tasks 1 and 2 already satisfy.

## Files Created/Modified

- `examples/example-hvac/**` - fictional business folder (8 typed files, 5 router files, 5 READMEs)
- `app/Icm/Loader.php` - the only code that reads the folder (Finder plus YAML front matter)
- `app/Icm/FolderSync.php` - lock, load, compile, fingerprint, no-op, transactional activation
- `app/Icm/ActiveConfig.php` - typed read API over the compiled live version
- `app/Models/IcmVersion.php`, `database/migrations/*_create_icm_versions_table.php`, `database/factories/IcmVersionFactory.php` - version storage
- `app/Console/Commands/IcmSync.php`, `IcmStatus.php` - `icm:sync`, `icm:status`
- `config/icm.php`, `.env.example`, `phpunit.xml` - `ICM_PATH` plumbing
- `.gitignore` - anchored rules for generated `records/*` and `changelog.md`
- `tests/Pest.php` - temp-copy harness and helpers
- `.ai/rules/*` - recorded project rules
- `docs/sprints/sprint-0.md` - nine walk-through log lines

## Decisions Made

See `key-decisions` above. All are within the plan's discretion areas; none reopens a locked decision.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Loader called a method that does not exist on symfony/yaml 8**
- **Found during:** Task 2 (running the RED tests)
- **Issue:** Task 1's Loader used `ParseException::getRawMessage()`, which symfony/yaml 8.1 does not have, so any file with malformed front matter crashed the whole sync with a fatal error instead of being reported.
- **Fix:** Shift the parser's line number by one for the opening `---` with `setParsedLine()` and use `getMessage()`, so the problem reads `unreadable:... at line N` with the real file line.
- **Files modified:** `app/Icm/Loader.php`, new `tests/Feature/Icm/LoaderTest.php` (not in the plan's file list; added as the regression test for this fix)
- **Verification:** `LoaderTest` passes; Task 2 RED run then failed only on planned assertions
- **Committed in:** `90ce3f6`

**2. [Rule 1 - Bug] No-op test compared sub-second time to a second-precision column**
- **Found during:** Task 2 (GREEN)
- **Issue:** The test used `freezeTime()`, which keeps microseconds, while SQLite stores whole seconds, so the `equalTo(now())` assertions could never hold. Test bug only; production code was correct.
- **Fix:** Use `freezeSecond()` in that test.
- **Files modified:** `tests/Feature/Icm/SyncTest.php`
- **Verification:** all 14 Icm tests pass
- **Committed in:** `1769297`

---

**Total deviations:** 2 auto-fixed (2 Rule 1)
**Impact on plan:** Both were necessary for correctness. One extra test file; no scope creep.

## Issues Encountered

- The Boost `record-rule` MCP tool and `database-schema` tool were not available in this session. As the plan allows, the five rules were recorded through `Laravel\Boost\Rules\RuleRepository` (`normalizeGlob` then `write`, the same calls the MCP tool makes) via `php artisan tinker --execute`, and the missing-table check used `php artisan db:table icm_versions`. No `.ai/rules` file was hand-written.
- `make:enum Enums/X` and `make:exception` placed files in the wrong folders on the first pass (`app/Enums/Enums/`, `app/Exceptions/Icm/...`). Caught before any commit and regenerated at the right paths (`make:enum X`, `make:class Icm/Exceptions/X`).
- `actuals.tokens` (13,126) is far below the plan's 95,000 estimate; the estimate was marked low confidence.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Plan 01-02 can add `Validator`, the `Rejected` outcome, the changelog writer and the "singleton types exist exactly once" check on top of `Loader` problems (`no_front_matter`, `unreadable:`, `empty`, `missing_type`, `unknown_type:`) and `SyncResult::$errors`. `SyncOutcome::Rejected` and `VersionStatus::Rejected` exist but nothing produces them yet.
- The no-op sync does not yet append a "No changes" line to `changelog.md` (D-08/D-09); that belongs to 01-02 with the rest of the changelog work.
- `ActiveConfig` accessors for singleton types assume the file exists. Until 01-02 validates that, a folder missing `business/profile.md` can still activate and then fail on first read.
- `.ai/rules` now exists: read `.ai/rules/index.md` and the matching rule files before editing in later plans.
- Pre-existing uncommitted `.planning/STATE.md`, `.planning/state.json` and `.planning/milestone.lock` were left alone until the metadata commit.

## Self-Check: PASSED

All created files exist and all seven task commits (`ea2d130`, `00c0325`, `90ce3f6`, `882e10c`, `1769297`, `5506c4d`, `c57214c`) are ancestors of HEAD. Full suite: 44 passed. `git check-ignore` checks pass. `evaluation-scope` resolved 7 plan commits.

---
*Phase: 01-foundation*
*Completed: 2026-10-11*
