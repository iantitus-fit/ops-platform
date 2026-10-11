---
phase: 01-foundation
plan: 02
subsystem: infra
tags: [laravel, validator, icm, changelog, plain-words-errors, pest, datasets, carbon]

requires:
  - phase: 01-foundation
    provides: "Loader problem codes, FolderSync, SyncResult, SyncOutcome::Rejected, VersionStatus::Rejected, Pest temp-folder helpers (plan 01-01)"
provides:
  - "App\\Icm\\Validator: rule sets for all seven Sprint 0 file types plus duplicate, singleton and parse-problem checks"
  - "App\\Icm\\ValidationReport and App\\Icm\\PlainWords: owner-sentence rendering, errors grouped by file in path order"
  - "App\\Icm\\Changelog: changelog.md writer, newest first, business time zone, atomic via File::replace"
  - "FolderSync Rejected outcome: rejected icm_versions row, live version untouched, changelog entry for Activated, Unchanged and Rejected"
  - "icm:sync exits 1 and prints one line per error on rejection"
affects: [01-03 records and inbound, 01-07 Business Folder page, phase-02 agents, phase-03 walk tests]

actuals:
  tokens: 12600
  tasks: 3
  commits: 6

tech-stack:
  added: []
  patterns:
    - "Laravel Validator for logic, PlainWords for wording: failed() rule names map to owner sentences, never Laravel message strings"
    - "bail on every field so one mistake is reported once; present|array for lists that may be empty"
    - "Strict types from YAML: integer:strict, boolean:strict, string catch quoted numbers, yes/no and unquoted phones"
    - "Owner text echoed in messages goes through PlainWords::oneLine (80 characters, one line)"
    - "changelog.md written only through File::replace (temp file plus rename)"
    - "Table-driven rejection tests through a named Pest dataset"

key-files:
  created:
    - app/Icm/Validator.php
    - app/Icm/ValidationReport.php
    - app/Icm/PlainWords.php
    - app/Icm/Changelog.php
    - tests/Feature/Icm/ValidatorTest.php
    - tests/Feature/Icm/ChangelogTest.php
  modified:
    - app/Icm/FolderSync.php
    - app/Icm/Loader.php
    - app/Console/Commands/IcmSync.php
    - tests/Feature/Icm/SyncTest.php
    - docs/sprints/sprint-0.md

key-decisions:
  - "A singleton type is owned by the file at its usual path (for example business/profile.md); any other file with that type gets the error, so a copy named profile-old.md is the one named, not the original"
  - "A missing singleton type is not reported when its usual path holds a file that could not be read, because that file's own error already explains it"
  - "Echoed owner values are cut to 80 characters including the three-dot ending, with line breaks collapsed to spaces"
  - "Every rejected attempt stores its own icm_versions row (fingerprint is not unique), so a repeated broken sync mints another rejected row"
  - "Hours days are checked in code: a day is the word closed or a map of open and close times, and close must be later than open"
  - "The changelog time zone is the candidate profile's when it is sound, else the live version's, else the app zone with an explaining bullet"

patterns-established:
  - "Field paths read in owner style: sources item 2: cost_cents_per_lead, weekly mon: open, closures item 1"
  - "Validator::validate() sees every file kind: Unrecognized files become sentences, Typed files get rules, LaterSprint files and routers are skipped"

requirements-completed: [ICM-02]

coverage:
  - id: D1
    description: "Breaking ac-repair.md (price_policy: maybe) makes icm:sync exit 1 with the plain-words line, v1 stays the only active row, a rejected row stores the line, and changelog.md gets a Rejected entry on top"
    requirement: ICM-02
    verification:
      - kind: integration
        ref: "tests/Feature/Icm/SyncTest.php#rejects a broken service file, keeps the old version live and records why in changelog.md"
        status: pass
      - kind: integration
        ref: "tests/Feature/Icm/ChangelogTest.php#leaves the live version untouched and never lets a rejected candidate be read"
        status: pass
    human_judgment: false
  - id: D2
    description: "Every Sprint 0 file type has a rule set and each broken field produces an owner-sized sentence naming file, field, allowed values and what was written (17 dataset rows plus slug, type and parse cases)"
    requirement: ICM-02
    verification:
      - kind: integration
        ref: "tests/Feature/Icm/ValidatorTest.php#rejects a broken field with a sentence that names the file, the field and what was written"
        status: pass
      - kind: integration
        ref: "tests/Feature/Icm/ValidatorTest.php#rejects a file whose type or front matter cannot be understood"
        status: pass
      - kind: integration
        ref: "tests/Feature/Icm/ValidatorTest.php#names the file and the file line when the YAML itself cannot be read"
        status: pass
    human_judgment: false
  - id: D3
    description: "A failed sync reports every error grouped by file in path order; duplicate lead source keys, service slugs and intake keys, and missing or repeated single-instance types are plain-words errors"
    requirement: ICM-02
    verification:
      - kind: integration
        ref: "tests/Feature/Icm/ValidatorTest.php#reports every error in the folder grouped by file in path order, not just the first"
        status: pass
      - kind: integration
        ref: "tests/Feature/Icm/ValidatorTest.php#requires each of the five single-instance types to exist exactly once"
        status: pass
      - kind: integration
        ref: "tests/Feature/Icm/ValidatorTest.php#names every missing single-instance type when the folder has no typed files"
        status: pass
    human_judgment: false
  - id: D4
    description: "Empty lists, CRLF line endings, a byte-order mark, unknown extra keys and unquoted closure dates validate and compile the same as their plain copies"
    requirement: ICM-02
    verification:
      - kind: integration
        ref: "tests/Feature/Icm/ValidatorTest.php#accepts empty lists where a list is allowed to be empty"
        status: pass
      - kind: integration
        ref: "tests/Feature/Icm/ValidatorTest.php#treats a file saved with Windows line endings and a byte-order mark the same as its plain copy"
        status: pass
      - kind: integration
        ref: "tests/Feature/Icm/ValidatorTest.php#ignores keys it does not know and accepts an unquoted closure date as text"
        status: pass
    human_judgment: false
  - id: D5
    description: "Every attempt except a Busy one lands in changelog.md as the newest entry (Live, No changes, Rejected), in the business time zone with a stated UTC fallback, keeping owner text above the entries and one bullet per error"
    requirement: ICM-02
    verification:
      - kind: integration
        ref: "tests/Feature/Icm/ChangelogTest.php"
        status: pass
    human_judgment: false
  - id: D6
    description: "The error sentences and changelog entries read naturally to the business owner"
    verification: []
    human_judgment: true
    rationale: "Tests pin the exact wording from the plan; whether it is the wording an owner would want to read is for Ian to judge. Known imprecision: the lowercase-letters sentence mentions both dashes and underscores for every field, although slugs allow only dashes and intake keys only underscores"

duration: 8min
completed: 2026-10-11
status: complete
plan_head_before: 185c33e2268cdb73d76a1a31732df454ac189cf4
plan_head_after: 78ffba4a23e57f31d11af3012280f8f1be71a53b
---

# Phase 1 Plan 02: Validator and Changelog Summary

**A broken folder change is rejected with plain-words errors that name the file and field, the live version stays active, and every sync attempt is recorded newest-first in the folder's changelog.md in the business time zone.**

## Performance

- **Duration:** 8 min
- **Started:** 2026-10-11T13:01:53Z
- **Completed:** 2026-10-11T13:10:33Z
- **Tasks:** 3
- **Files modified:** 11 (6 new classes and tests, 5 edited)

## Accomplishments

- `price_policy: maybe` in `business/services/ac-repair.md` now exits `icm:sync` with 1 and prints exactly `business/services/ac-repair.md: price_policy must be one of none, starting_at, fixed (you wrote maybe)`. v1 stays active, a rejected row stores the line, and `changelog.md` is created with `# Changelog` and a Rejected entry on top.
- All seven Sprint 0 types have rule sets. YAML traps get owner-sized fixes: an unquoted phone says to wrap it in quotes, `"90"` in an integer field says whole number, `yes` says true or false, an unquoted closure date is accepted as a `Y-m-d` string.
- All errors in a folder are reported together, grouped by file in path order. Duplicate lead source keys, duplicate service slugs, duplicate intake keys, and missing or repeated single-instance types (business, hours, lead sources, voice, sending rules) are each a plain-words error.
- `changelog.md` records Live, No changes and Rejected entries, newest first, in the business time zone (candidate profile, else the live version, else UTC with an explaining bullet). A Busy sync writes nothing. Writes go through `File::replace`.
- Verified on the real example folder: `php artisan migrate`, `icm:sync` (Live as v1, 8 files), a second `icm:sync` (Already active), `icm:status`, newest entry on top of `examples/example-hvac/changelog.md`, and `git status --porcelain examples/` is empty.

## Task Commits

Each task was committed atomically (TDD: test, then feat):

1. **Task 1: Tracer, a broken service file is rejected end to end**
   - RED `f6b7522` (test): failing rejected-sync test
   - GREEN `c836182` (feat): Validator (service rules), PlainWords, ValidationReport, Changelog (Rejected), FolderSync rejection, icm:sync output
   - Tracer verified end to end (SyncTest green, full suite green) before expansion.
2. **Task 2: Rule sets for every Sprint 0 file type**
   - RED `23383ba` (test): 29 ValidatorTest cases, 25 failing
   - GREEN `97ed4e0` (feat): rule sets, cross-file checks, parse-problem sentences, owner-style field paths
3. **Task 3: Every sync attempt in changelog.md**
   - RED `a9826de` (test): 11 ChangelogTest cases, 9 failing
   - GREEN `78ffba4` (feat): Live and No changes entries, notices, time zone choice and fallback note, walk-through log

**Plan metadata:** recorded in the docs commit that follows this file.

## RED Evidence (TDD)

Each RED run was classified with `gsd_run check tdd-red-evidence` from the unchanged PHPUnit JUnit report and returned `RED_EVIDENCE_OK` (`target_test_failed`).

- Task 1: target "rejects a broken service file ..." failed because `icm:sync` exited 0 and activated the broken file. Semantic assessment: the planned assertion (exit code 1) failed for the planned reason.
- Task 2: target "requires each of the five single-instance types to exist exactly once" failed because the sync exited 0 with no hours file. 25 of 29 tests failed. The 3 positive tests (empty lists, CRLF plus BOM, unknown keys and unquoted dates) and the deleted-price row passed at RED because they guard behavior the Loader and the Task 1 rules already provided.
- Task 3: target "puts a rejected sync on top and keeps one header" failed because only the Rejected entry existed (Live and No changes were never recorded). 9 of 11 tests failed, 5 of them erroring on a changelog.md that did not exist yet. The echoed-value test is a regression guard for T-01-07.

## Files Created/Modified

- `app/Icm/Validator.php` - per-type rule sets, hours day checks, duplicate and singleton checks, parse-problem sentences
- `app/Icm/PlainWords.php` - rule name to owner sentence, owner-style field paths, `value()` and `oneLine()` for echoed text
- `app/Icm/ValidationReport.php` - errors, `lines()` sorted by file path, `hasErrors()`, `hasErrorsFor()`
- `app/Icm/Changelog.php` - changelog.md entries, header creation, newest-first insertion, `File::replace`
- `app/Icm/FolderSync.php` - validation before the no-op check, rejected row, changelog recording and time zone choice
- `app/Icm/Loader.php` - `TYPED` constant made public so the validator can list the seven types
- `app/Console/Commands/IcmSync.php` - prints the rejection message and each error, exits 1
- `tests/Feature/Icm/ValidatorTest.php`, `ChangelogTest.php`, `SyncTest.php` - rejection, rule-set and changelog tests
- `tests/Pest.php` - temp copy no longer carries over a real changelog.md
- `docs/sprints/sprint-0.md` - four new walk-through log lines

## Decisions Made

See `key-decisions` above. All stay inside the plan's discretion; none reopens a locked decision.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Plan contradicted itself on which file owns a repeated single-instance type**
- **Found during:** Task 2 (GREEN)
- **Issue:** The plan says a duplicate is "reported against the later path" and also expects `business/profile-old.md: type business is already used by business/profile.md`. By path order `profile-old.md` sorts before `profile.md` (the dash sorts before the dot), so the first rule named the wrong file.
- **Fix:** The file at the type's usual path owns the type; every other file with that type gets the error. This produces the plan's expected line and is the more useful message for an owner.
- **Files modified:** `app/Icm/Validator.php`
- **Verification:** `ValidatorTest` "requires each of the five single-instance types to exist exactly once" passes
- **Committed in:** `97ed4e0`

**2. [Rule 3 - Blocking] Validator needed the Loader's type list**
- **Found during:** Task 2
- **Issue:** The unknown-type sentence must list the seven Sprint 0 types, and `Loader::TYPED` was private.
- **Fix:** Made the constant public instead of duplicating the list.
- **Files modified:** `app/Icm/Loader.php`
- **Verification:** arch tests still pass
- **Committed in:** `97ed4e0`

**3. [Rule 2 - Missing Critical] Unreadable singleton file produced a second, misleading error**
- **Found during:** Task 2 (GREEN)
- **Issue:** A hours.md with a tab-indented line is Unrecognized, so the folder also looked like it had no hours file. The owner would get two errors for one mistake.
- **Fix:** A missing single-instance type is not reported when its usual path holds a file that could not be read.
- **Files modified:** `app/Icm/Validator.php`
- **Verification:** "names the file and the file line when the YAML itself cannot be read" expects exactly one error
- **Committed in:** `97ed4e0`

**4. [Rule 1 - Bug] Echoed values were cut to 83 characters, not 80**
- **Found during:** Task 3 (GREEN)
- **Issue:** `Str::limit($text, 80, '...')` keeps 80 characters and then adds the dots.
- **Fix:** Keep 77 characters plus `...` when the text is longer than 80.
- **Files modified:** `app/Icm/PlainWords.php`
- **Verification:** "keeps one bullet per error by cutting long values and collapsing line breaks" asserts 80
- **Committed in:** `78ffba4`

**5. [Rule 1 - Bug] Test harness copied a developer's real changelog.md into every temp folder**
- **Found during:** final full-suite run, after the real-folder sanity check created `examples/example-hvac/changelog.md` (gitignored)
- **Issue:** `tests/Pest.php` copies the whole example folder, so the real changelog entries leaked into the temp copy and `ChangelogTest` saw extra headings. This is the manual-run dirtying risk noted in RESEARCH Pitfall 5; it only shows once a developer runs `icm:sync` on the real folder.
- **Fix:** The harness deletes `changelog.md` from the temp copy after copying.
- **Files modified:** `tests/Pest.php`
- **Verification:** full suite passes (85 tests) with the real changelog.md present
- **Committed in:** `706da5a`

---

**Total deviations:** 5 auto-fixed (3 Rule 1, 1 Rule 2, 1 Rule 3)
**Impact on plan:** All were needed for correct, non-misleading owner messages and stable tests. No scope creep; two extra tests (owner text kept above entries, echoed value cut) cover plan truths that had no named test.

## Issues Encountered

- The tdd-red-evidence classifier refuses record paths outside the project, so the RED records were written to `storage/logs/gsd-red/` (gitignored) instead of the scratch folder.
- Task 3's `<human-check>` was run by the executor on the real folder as an automated sanity pass (see Accomplishments). It ran `php artisan migrate` on the local development database, so v1 is now live there. Ian can still run the same commands at the end-of-phase review.
- The wording `must use only lowercase letters, numbers, dashes or underscores` is the plan's one sentence for every pattern rule. It is slightly loose: slugs and lead source keys follow different dash and underscore rules (slug: dashes only, intake key: underscores only, source key: both).

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Plan 01-03 and later can rely on `ActiveConfig` singleton accessors: the validator now guarantees business, hours, lead sources, voice and sending rules each exist once before any version goes live.
- `SyncResult` for a rejection carries the rejected row in `version` and the error lines in `errors`; the Business Folder page (01-07) can show them directly.
- Each repeated broken sync stores another rejected `icm_versions` row. Plan 01-07 should show only the newest rejected row, or a retention rule can be added later.
- If a changelog write fails (for example a read-only folder) after a version is activated, `icm:sync` surfaces the error after the version is already live. No rollback is attempted.

## Self-Check: PASSED

All created files exist and all six task commits (`f6b7522`, `c836182`, `23383ba`, `97ed4e0`, `a9826de`, `78ffba4`) are ancestors of HEAD. Full suite: 85 passed. Acceptance greps for all three tasks pass. `git status --porcelain examples/` is empty.

---
*Phase: 01-foundation*
*Completed: 2026-10-11*
