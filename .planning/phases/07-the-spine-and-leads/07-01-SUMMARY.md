---
phase: 07-the-spine-and-leads
plan: 01
subsystem: icm
tags: [laravel, enum, icm-sync, validation, spec]
requires:
  - phase: 04-room-standard
    provides: the SpecTables reader and the SPEC-versus-code test pattern (04 D-14, D-22)
provides:
  - "App\\Enums\\ProjectWord: six words with plurals, fromProfile() and choices()"
  - "Validator::businessErrors(): refuses a missing or unlisted project_word in plain words"
  - "ActiveConfig::projectWord(): never throws, falls back to Project"
  - "project_word: Job in the example business"
  - "docs/SPEC.md 'What a business calls a project' table, tied to the code by a test"
affects: [07-03 customer card, 07-05 contact screen, 07-07 dashboard]
actuals:
  tokens: 5800
  tasks: 2
  commits: 4
plan_head_before: 20da3ed62db2cd3b3e661dda1322ddb291680bdc
plan_head_after: a02c42ff8895b1a056ea6ccecc75befd8dee1a1e
tech-stack:
  added: []
  patterns:
    - "A hand-written Validator check where the stock Laravel rule cannot do case-blind matching or list the choices"
    - "A never-throw ActiveConfig accessor with a code default (mirrors displayTimezone())"
    - "SPEC table compared with an enum by a test (04 pattern)"
key-files:
  created:
    - app/Enums/ProjectWord.php
    - tests/Feature/Projects/ProjectWordTest.php
    - tests/Feature/Projects/ProjectWordSpecTest.php
  modified:
    - app/Icm/Validator.php
    - app/Icm/ActiveConfig.php
    - examples/example-hvac/business/profile.md
    - docs/SPEC.md
    - docs/BLUEPRINT.md
    - docs/sprints/sprint-1.1.md
key-decisions:
  - "The missing-word line reads 'project_word is missing, and it is required; write one of Job, Project, ...' (house wording plus the choices)"
  - "projectWord() falls back to Project with no live version or an older compiled version (research A1)"
requirements-completed: [PROJ-02]
coverage:
  - id: D1
    description: "The sync accepts Job, Project, Matter, Case, Work order or Engagement in any letter case and with any run of spaces, and ActiveConfig::projectWord() returns the matching ProjectWord"
    requirement: PROJ-02
    verification:
      - kind: integration
        ref: "tests/Feature/Projects/ProjectWordTest.php#accepts each listed word in any letter case"
        status: pass
    human_judgment: false
  - id: D2
    description: "A missing, empty, unlisted, non-text, lookalike or zero-width word is refused with one plain line naming business/profile.md, project_word and the choices, and the previous version stays live"
    requirement: PROJ-02
    verification:
      - kind: integration
        ref: "tests/Feature/Projects/ProjectWordTest.php#refuses a missing or unlisted word and keeps the previous version live"
        status: pass
    human_judgment: false
  - id: D3
    description: "projectWord() never throws: Project before the first sync and under a version compiled without the field"
    requirement: PROJ-02
    verification:
      - kind: integration
        ref: "tests/Feature/Projects/ProjectWordTest.php#never throws when asked for the word"
        status: pass
    human_judgment: false
  - id: D4
    description: "docs/SPEC.md lists the same six words and plurals in the same order as ProjectWord, and the business file type row names project_word"
    requirement: PROJ-02
    verification:
      - kind: unit
        ref: "tests/Feature/Projects/ProjectWordSpecTest.php#lists in SPEC exactly the words in ProjectWord, with their plurals, in order"
        status: pass
    human_judgment: false
  - id: D5
    description: "The same sync result on Postgres (the CI pgsql leg) with both new test files included"
    requirement: PROJ-02
    verification: []
    human_judgment: true
    rationale: "Needs the phase branch pushed and the CI pgsql leg run; Ian pushes, so it is recorded for end-of-phase UAT"
duration: 8min
completed: 2026-10-30
status: complete
---

# Phase 7 Plan 01: The Business's Word for a Project Summary

**`business/profile.md` now carries `project_word`, checked by the sync against a six-word `ProjectWord` enum in any letter case, refused in one plain line with the last good version still live, read back through a never-throwing `ActiveConfig::projectWord()`, and tied to a SPEC table by a test.**

## Performance

- **Duration:** 8 min
- **Started:** 2026-10-30T12:29:35Z
- **Completed:** 2026-10-30T12:37:24Z
- **Tasks:** 2
- **Files modified:** 9 (3 created, 6 modified)

## Accomplishments
- `ProjectWord` enum: Job, Project, Matter, Case, Work order, Engagement, each with a written-out plural; `fromProfile()` trims, collapses every run of spaces (including Unicode separators) to one and lower-cases with `mb_strtolower`, so `Work  ORDER` works while a Cyrillic lookalike or a zero-width character is refused.
- `Validator::businessErrors()` returns the exact lines from D-12 (missing: "... is missing, and it is required; write one of ..."; unlisted, number or list: "... must be one of ... (you wrote X)"), wired into `fileErrors()`; the example business sets `project_word: Job` in the same commit as the check (research Pitfall 1), so no existing test changed.
- `ActiveConfig::projectWord()` never throws and returns `ProjectWord::Project` before the first sync or under a version compiled without the field.
- `docs/SPEC.md` gains "What a business calls a project" and the business row names the field; `ProjectWordSpecTest` fails if SPEC and the code disagree.

## TDD Gate Compliance

- **RED (task 1):** `test(07-01)` `2f4a1f6`. 21 tests, all failing. `gsd check tdd-red-evidence` (JUnit report from Pest `--log-junit`) returned `RED_EVIDENCE_OK` for the target row "a word that is not on the list". Semantic assessment: the refused-word rows failed on `Expected status code 1 but received 0` (the sync accepted any profile), the accepted-word rows failed on the planned fixture assertion (the example has no `project_word` line), and the four remaining failures were `ActiveConfig::projectWord()` and `ProjectWord` not existing, which is the planned reason.
- **GREEN (task 1):** `feat(07-01)` `11ac5f0`, 21 of 21 pass, full suite 2299 of 2299.
- **RED (task 2):** `test(07-01)` `176a35c`. Target "lists in SPEC exactly the words in ProjectWord" failed with `Expecting [] not to be empty` (no heading yet); `RED_EVIDENCE_OK`. The business-row test also failed. The reader-fixture test ("reads only the rows under that heading") passed at RED because it exercises the existing `SpecTables::rows()` with the new file-local helper, not the SPEC edit; it is a characterization test, not a RED target.
- **GREEN (task 2):** `feat(07-01)` `a02c42f`, 54 of 54 pass in `tests/Feature/Projects` plus `SpecTablesTest`.
- **REFACTOR:** none needed.

Tracer gate: the tracer's two automated `<verify>` commands (the focused run and the full suite) passed after the feat commit, so expansion to Task 2 went ahead (tracer verified end-to-end).

## Task Commits

1. **Task 1 RED:** `2f4a1f6` (test) - the sync checks the project word in business/profile.md
2. **Task 1 GREEN:** `11ac5f0` (feat) - ProjectWord, Validator check, ActiveConfig::projectWord(), example line, BLUEPRINT bullet
3. **Task 2 RED:** `176a35c` (test) - SPEC lists the project words the code knows
4. **Task 2 GREEN:** `a02c42f` (feat) - SPEC table, business row, BLUEPRINT extension, walk-through line

**Plan metadata:** recorded in the docs(07-01) commit that follows this file.

## Files Created/Modified
- `app/Enums/ProjectWord.php` - the one list of words, with plurals, tolerant matching and the choices for error lines
- `app/Icm/Validator.php` - `businessErrors()` and its wiring in `fileErrors()`
- `app/Icm/ActiveConfig.php` - `projectWord()` plus the class docblock's never-throw list
- `examples/example-hvac/business/profile.md` - `project_word: Job`
- `tests/Feature/Projects/ProjectWordTest.php` - accept, refuse, never-throw, order and non-text cases
- `tests/Feature/Projects/ProjectWordSpecTest.php` - SPEC versus code, reader scope, business row
- `docs/SPEC.md`, `docs/BLUEPRINT.md`, `docs/sprints/sprint-1.1.md` - SPEC table and row, Built bullet, walk-through line

## Decisions Made
- Followed the plan. The missing-word line keeps the house wording and adds the choices (the plan's flagged assumption); the fallback to Project is one line in `ActiveConfig` if Ian wants another default (research A1).

## Deviations from Plan

None - plan executed exactly as written.

Two small notes, neither a deviation: (1) the datasets in `ProjectWordTest` name the expected case by its stored value (`'work order'`) rather than the enum case, so the file loads before `ProjectWord` exists and RED fails on assertions rather than a class-not-found at collection; (2) the plan's acceptance `grep -q` lines contain `$` and `(`, which macOS grep reads as regex in some positions, so I checked them with `grep -qF`; all passed.

## Issues Encountered
None. No existing test changed (`git diff --name-only main -- tests` lists only the two new files); `app/Ai`, `app/SendRule`, `app/Actions` and `examples/example-hvac/rules` are untouched.

## Unrun human check (end-of-phase UAT)
- After Ian pushes the phase branch, the CI tests workflow's pgsql leg should be green with `ProjectWordTest` and `ProjectWordSpecTest` included (success criterion 1 on Postgres). Left unrun; recorded as an `unrun-verify` entry in `.planning/WINDOWS.md`. Locally the whole suite passed on SQLite (2299 tests).

## Known Stubs
None.

## Threat Flags
None. T-07-01 and T-07-02 are mitigated as planned: lookalike and zero-width words are refused as unlisted, and the echoed value goes through `PlainWords::render()`.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Plans 07-03, 07-05 and 07-07 can name projects with `app(ActiveConfig::class)->projectWord()->singular()` / `->plural()`; screens set the casing.
- No blockers.

## Self-Check: PASSED

- Files: `app/Enums/ProjectWord.php`, `tests/Feature/Projects/ProjectWordTest.php`, `tests/Feature/Projects/ProjectWordSpecTest.php` found on disk.
- Commits `2f4a1f6`, `11ac5f0`, `176a35c`, `a02c42f` are ancestors of HEAD.
- Plan verification: `php artisan test --compact tests/Feature/Projects tests/Feature/Icm tests/Feature/Rooms/SpecTablesTest.php` passes (345 tests); full suite passed after Task 1 (2299 tests), and Task 2 changed only docs and a new test file.

---
*Phase: 07-the-spine-and-leads*
*Completed: 2026-10-30*
