---
phase: 07-the-spine-and-leads
plan: 04
subsystem: database
tags: [laravel, actions, projects, row-lock, source-scan, arch-test, tdd]
status: complete
requires:
  - phase: 07-the-spine-and-leads
    provides: "Project model, ProjectActor, OpenProject::byHand(), the card's projectParts() arm and the Projects section (plans 07-02, 07-03)"
provides:
  - "CloseProject, ReopenProject and RenameProject, each under a row lock, each adding one fixed-sentence timeline entry"
  - "ActivityType::ProjectClosed, ProjectReopened, ProjectRenamed and their three card arms ('<Word> N closed|reopened|renamed')"
  - "RenameProject::MAX_LENGTH = 100 and the two plain-words refusals"
  - "tests/Unit/ProjectWritesTest.php: a source scan proving only the four project actions write the projects table, plus an arch rule keeping them out of agent code"
affects: [07-05 contact screen (calls the four actions from the relation manager), 07-08 automatic open (adds forNewLead on OpenProject's private open())]
actuals:
  tokens: 9000
  tasks: 3
  commits: 6
plan_head_before: 281e7a0421122fd97cdd451d45359f0e588388bb
plan_head_after: 8ce12c1eceb5306af1c4706e255b543631c2afeb
tech-stack:
  added: []
  patterns:
    - "A writer action that locks the row, refreshes, returns false when nothing would change, and dispatches CustomerRecordChanged only after a real change (the CloseConversation shape)"
    - "A refusal as a DomainException thrown before any write, so no wrapping transaction is aborted (Postgres 25P02 safe)"
    - "A regex source scan of app/ plus known-bad snippets, with one arch() rule for where the actions may be called from"
key-files:
  created:
    - app/Actions/CloseProject.php
    - app/Actions/ReopenProject.php
    - app/Actions/RenameProject.php
    - tests/Feature/Projects/ProjectActionsTest.php
    - tests/Unit/ProjectWritesTest.php
  modified:
    - app/Enums/ActivityType.php
    - app/Records/CustomerCard.php
    - docs/BLUEPRINT.md
    - docs/sprints/sprint-1.1.md
key-decisions:
  - "Who reopened or renamed a project is the signed-in owner, written on the timeline entry as 'the owner'; the projects row keeps user ids only for who opened and who closed (plan Flagged assumption)"
  - "The write scan's relation and query rules use [^;]* between the receiver and the write call, so a chained ->projects()->where(...)->update() is caught, not only a direct ->projects()->update()"
requirements-completed: [PROJ-01]
coverage:
  - id: D1
    description: "The owner closes a project: closed_at, closed_by and closed_by_user_id are recorded, one 'Closed by the owner' entry is added, the card shows 'Job 1 closed', and a stale second tab changes nothing"
    requirement: PROJ-01
    verification:
      - kind: integration
        ref: "tests/Feature/Projects/ProjectActionsTest.php#closes a project and puts it on the card"
        status: pass
      - kind: integration
        ref: "tests/Feature/Projects/ProjectActionsTest.php#closes once from two tabs"
        status: pass
    human_judgment: false
  - id: D2
    description: "The owner reopens a project (clears all three close fields, one entry, allowed while other projects are open, an open project or a stale tab changes nothing)"
    requirement: PROJ-01
    verification:
      - kind: integration
        ref: "tests/Feature/Projects/ProjectActionsTest.php#reopens a closed project"
        status: pass
      - kind: integration
        ref: "tests/Feature/Projects/ProjectActionsTest.php#reopens while the contact has another open project"
        status: pass
      - kind: integration
        ref: "tests/Feature/Projects/ProjectActionsTest.php#leaves an open project alone when it is reopened"
        status: pass
    human_judgment: false
  - id: D3
    description: "The owner renames a project: one tidy line of at most 100 characters (counted as characters), no hidden characters or broken bytes, an empty name clears it, the same name adds no entry, the entry never carries the name"
    requirement: PROJ-01
    verification:
      - kind: integration
        ref: "tests/Feature/Projects/ProjectActionsTest.php#renames a project to one tidy line"
        status: pass
      - kind: integration
        ref: "tests/Feature/Projects/ProjectActionsTest.php#clears the name when it is renamed to nothing"
        status: pass
      - kind: integration
        ref: "tests/Feature/Projects/ProjectActionsTest.php#refuses a name it cannot keep"
        status: pass
      - kind: integration
        ref: "tests/Feature/Projects/ProjectActionsTest.php#keeps a name of exactly 100 characters, counted as characters"
        status: pass
    human_judgment: false
  - id: D4
    description: "None of the four actions changes a contact's status, asks, flags or alerts anyone (D-04, D-07)"
    requirement: PROJ-01
    verification:
      - kind: integration
        ref: "tests/Feature/Projects/ProjectActionsTest.php#never changes the contact status"
        status: pass
      - kind: integration
        ref: "tests/Feature/Projects/ProjectActionsTest.php#asks, flags and alerts no one"
        status: pass
    human_judgment: false
  - id: D5
    description: "Only OpenProject, CloseProject, ReopenProject and RenameProject write the projects table, the scan can fail, and the four are used only from App\\Actions, the contact screen's relation managers, App\\Rooms\\Leads and Tests"
    requirement: PROJ-01
    verification:
      - kind: unit
        ref: "tests/Unit/ProjectWritesTest.php#only the four project actions write the projects table"
        status: pass
      - kind: unit
        ref: "tests/Unit/ProjectWritesTest.php#flags every kind of write, so the scan is not vacuous"
        status: pass
      - kind: unit
        ref: "tests/Unit/ProjectWritesTest.php#only the contact screen and the leads room call the four project actions"
        status: pass
    human_judgment: false
  - id: D6
    description: "The same behavior on Postgres (CI pgsql leg with ProjectActionsTest and ProjectWritesTest)"
    requirement: PROJ-01
    verification: []
    human_judgment: true
    rationale: "Needs the phase branch pushed to CI; left unrun by the executor and recorded as WINDOWS.md entry 37"
duration: 8 min
completed: 2026-10-30
---

# Phase 7 Plan 04: The Owner Closes, Reopens and Renames Projects Summary

**Three row-locked writer actions (CloseProject, ReopenProject, RenameProject) that each record who acted and add one fixed-sentence timeline entry, with a source-scan test proving that only these three and OpenProject ever write the projects table.**

## Performance

- **Duration:** 8 min (2026-10-30T18:49:17Z to about 18:57Z)
- **Tasks:** 3 (tracer, then two expansion tasks)
- **Files:** 9 changed (5 created, 4 modified), plus the planning files
- **Commits:** 6 task commits (measured from the plan ledger: 281e7a0..8ce12c1)

## Accomplishments

- `CloseProject::handle(Project, User): void` locks the row, refreshes, returns when it is already closed, otherwise records `closed_at`, `closed_by` (User) and `closed_by_user_id` and adds one `ProjectClosed` entry "Closed by the owner". `CustomerRecordChanged` is dispatched after the commit and only on a real change.
- `ReopenProject` clears all three close fields and adds one `ProjectReopened` entry; an open project or a stale tab changes nothing; there is no one-open rule (D-05).
- `RenameProject` collapses every whitespace run to one space, trims, maps empty to null, refuses a hidden character or more than 100 characters (`mb_strlen`) with a plain-words `DomainException` before any write, and adds one `ProjectRenamed` entry "Renamed by the owner" that never carries the name. Renaming to the stored name adds nothing.
- The card draws the three new entries as "Job N closed|reopened|renamed" under "System note:".
- `ProjectWritesTest` walks every PHP file under `app/` with regular expressions for the ways Laravel code writes a table. On the real tree it finds writes in exactly the four action files. Ten known-bad snippets prove each rule fires; reads and Filament `fillForm(` / event `created(` look-alikes pass. One arch rule limits who may use the four actions.
- BLUEPRINT sections 3 and 10 have their Built bullets and the sprint walk-through log has the plan 07-04 line.

## Task Commits

1. **Task 1 (tracer), RED:** `711ba9d` test(07-04): the owner closes a project
2. **Task 1, GREEN:** `3aa8d03` feat(07-04): the owner can close a project (D-05, D-11)
3. **Task 2, RED:** `66d0732` test(07-04): the owner reopens and renames a project
4. **Task 2, GREEN:** `dbc3b9c` feat(07-04): the owner can reopen and rename a project (D-05, D-07, D-08)
5. **Task 3, RED:** `b7ac9cb` test(07-04): only the four project actions write projects
6. **Task 3, GREEN:** `8ce12c1` feat(07-04): only the four project actions may write projects (D-11)

## TDD Gate Compliance

RED then GREEN in order for all three tasks; no REFACTOR commit was needed.

- **Task 1 RED:** `gsd check tdd-red-evidence` on a `--log-junit` report returned `RED_EVIDENCE_OK` (`target_test_failed`) for "it closes a project and puts it on the card". Semantic assessment: both tests errored with `Target class [App\Actions\CloseProject] does not exist`, the planned reason.
- **Task 2 RED:** `RED_EVIDENCE_OK` for "it reopens a closed project" (16 of 18 tests failing). Semantic assessment: every failure was `Target class [App\Actions\ReopenProject|RenameProject] does not exist`; the dataset refusal cases failed on `toThrow` because the class was missing. The two Task 1 cases still passed.
- **Task 3 RED:** `RED_EVIDENCE_OK` for "it only the four project actions write the projects table" (11 of 16 failing, 5 passing). Semantic assessment: the tree case failed an `toBe` assertion (expected the four paths, got `[]`) and every known-bad snippet case failed `not->toBe([])`, because the stub returned no findings. The "passes reads", look-alike and arch cases passed at RED by design (they pin that a correct scan does not over-flag; the arch rule already held because nothing outside the allowed namespaces uses the actions).

Tracer gate: the tracer's automated `<verify>` (`tests/Feature/Projects` and `tests/Feature/Records`, 86 passed) ran green on the tree before commit `3aa8d03` and again after it; logged "Tracer verified end-to-end, expanding" and continued with no checkpoint (auto mode off, `human_verify_mode` end-of-phase, automated-only verify).

## Verification

- `php artisan test --compact tests/Feature/Projects tests/Feature/Records`: 102 passed after Task 2.
- `php artisan test --compact tests/Unit/ProjectWritesTest.php tests/Unit/ArchitectureTest.php tests/Feature/Projects`: 88 passed.
- `php artisan test --compact`: **2353 passed (17640 assertions), 1 warning with no detail (the same single warning earlier plans reported, not from these files)**, SQLite. The suite stood at 2319 after 07-03; the 34 new tests are 18 in `ProjectActionsTest` and 16 in `ProjectWritesTest`.
- Acceptance greps all pass. `git diff --quiet main -- tests/Unit/ArchitectureTest.php` exits 0. `git diff --name-only main -- tests` lists only tests/Feature/Projects/ files, tests/Feature/Records/CardInjectionTest.php (from 07-03) and tests/Unit/ProjectWritesTest.php. `git diff --quiet main -- app/Ai app/SendRule tests/Feature/FrontDesk` exits 0: the front desk is untouched.
- The write-confinement scan found no writer outside the four actions in existing code, so no allowed list was widened and no rule loosened.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Missing Critical] RenameProject refuses invalid UTF-8 instead of clearing the stored name**
- **Found during:** Task 2 (RenameProject), flagged by the orchestrator before the run
- **Issue:** `preg_replace` with the `u` flag returns `null` on invalid UTF-8. Cast to a string that reads as an empty name, so a malformed name would have cleared the stored one with a "Renamed" entry.
- **Fix:** A `null` result from the collapsing step is treated as a hidden character: `tidy()` throws the hidden-character sentence before any write. `HiddenCharacters::found()` runs on the collapsed text (so a raw line break, already turned into a space, is not refused), and the input is not scrubbed, because `mb_scrub` would store a question mark instead of refusing.
- **Files modified:** app/Actions/RenameProject.php, tests/Feature/Projects/ProjectActionsTest.php (a 'broken bytes' row, `"Water\xC3heater"`, in the 'refuses a name it cannot keep' dataset; the key is a plain string so the invalid byte never reaches the test name)
- **Verification:** the 'broken bytes' dataset row passes; the name and the entry count are unchanged after the refusal
- **Committed in:** `66d0732` (test row), `dbc3b9c` (code)

---

**Total deviations:** 1 auto-fixed (1 missing critical).
**Impact on plan:** Required for correctness; no scope creep. Two small additions beyond the plan's lists, both supersets and both exercised by the tree scan: the query-write rule also catches `create`-family calls, and the relation-write rule matches a chained `->projects()->...->update(` rather than only a direct call.

## Issues Encountered

None. The write-confinement scan passed against the real tree on its first run, with exactly the four expected files.

## Unrun hand check

The plan's `<human-check>` (after Ian pushes the phase branch, the CI pgsql leg is green with `ProjectActionsTest` and `ProjectWritesTest` included) was left unrun, per the standing rule that GSD runs do not pause for a live human check. Recorded as `unrun-verify` entry 37 in `.planning/WINDOWS.md`, in the same shape as entries 34 to 36. The refusals in this plan are `DomainException`s thrown before any write, so no test needs the `DB::transaction()` wrapper that a database-level refusal needs on Postgres (SQLSTATE 25P02).

## Known Stubs

None.

## Threat Flags

None. T-07-08 (writes outside the four actions) is mitigated by the scan and the arch rule; T-07-09 (name tampering) by the whitespace collapse, the hidden-character and broken-bytes refusal and the 100-character bound; T-07-10 (repudiation) by `closed_by`, `closed_by_user_id` and one fixed-summary entry per change under a row lock. No new endpoint, auth path or schema change was added.

## Next Phase Readiness

Ready for 07-05 (the contact screen). Its relation manager under `App\Filament\Resources\Contacts\RelationManagers` may call the four actions and nothing else may write projects; a `Project*` Filament file there is also scanned for any Eloquent write or Filament write action, so the screen must go through the actions. PROJ-01 is also carried by 07-05 and 07-08, so it is not marked complete in REQUIREMENTS.md here.

## Self-Check: PASSED

- FOUND: app/Actions/CloseProject.php, app/Actions/ReopenProject.php, app/Actions/RenameProject.php, tests/Feature/Projects/ProjectActionsTest.php, tests/Unit/ProjectWritesTest.php
- FOUND commits: 711ba9d, 3aa8d03, 66d0732, dbc3b9c, b7ac9cb, 8ce12c1 (all ancestors of HEAD; `git rev-list --count 281e7a0..HEAD` is 6)
