---
phase: 07-the-spine-and-leads
plan: 05
subsystem: ui
tags: [laravel, filament, relation-manager, livewire, pest, projects, tdd]
status: complete
requires:
  - phase: 07-the-spine-and-leads
    provides: "OpenProject::byHand(), CloseProject, ReopenProject, RenameProject, Project::withNumber() and label(), ActiveConfig::projectWord() (plans 07-01 to 07-04)"
provides:
  - "ProjectsRelationManager: the contact screen's list of projects in the business's word, with a New header action and Close, Reopen and Rename row actions"
  - "ContactResource::getRelations() naming the relation manager"
  - "tests/Feature/Filament/ProjectsRelationManagerTest.php: 11 Livewire tests of the list and the four actions"
affects: [07-06 leads dashboard section, 07-08 automatic open, 11 phone design pass (wording and styling are placeholders)]
actuals:
  tokens: 9000
  tasks: 2
  commits: 4
plan_head_before: 3f5f94453158e572b341f098fe380f52a2c5892f
plan_head_after: 1d08beb3c46e4db38e7e48af34005251171e2609
tech-stack:
  added: []
  patterns:
    - "A Filament relation manager whose every write is an action class call, with isReadOnly() false so a view page keeps its buttons"
    - "Livewire::test() on the relation manager itself with ownerRecord and pageClass, because Filament lazy-loads relation managers on the page"
key-files:
  created:
    - app/Filament/Resources/Contacts/RelationManagers/ProjectsRelationManager.php
    - tests/Feature/Filament/ProjectsRelationManagerTest.php
  modified:
    - app/Filament/Resources/Contacts/ContactResource.php
    - docs/BLUEPRINT.md
    - docs/sprints/sprint-1.1.md
key-decisions:
  - "The list is stock Filament with placeholder wording until Phase 11 (02.1 D-48, D-21); New, Close, Reopen and Rename show whether or not the leads room is live (research A2, adopted)"
  - "The page-level test asserts the relation manager is mounted (assertSeeLivewire) rather than its text, because Filament renders it as a lazy component"
requirements-completed: [PROJ-01, PROJ-02]
coverage:
  - id: D1
    description: "The contact screen lists the contact's projects in opening order, each with its name or '<Word> N', open or closed, when and by whom it opened (New lead, or the user's name) and when it closed, under a title that is the live plural word"
    requirement: PROJ-02
    verification:
      - kind: integration
        ref: "tests/Feature/Filament/ProjectsRelationManagerTest.php#lists a contact's projects in the business's word"
        status: pass
      - kind: integration
        ref: "tests/Feature/Filament/ProjectsRelationManagerTest.php#uses the word the profile sets"
        status: pass
      - kind: integration
        ref: "tests/Feature/Filament/ProjectsRelationManagerTest.php#lists every project for a contact with two open ones"
        status: pass
    human_judgment: false
  - id: D2
    description: "The 'New <word>' header action opens a second job through OpenProject::byHand(), tied to the open conversation, with no alert and no text (D-04), and is not hidden by Filament's read-only default on a view page"
    requirement: PROJ-01
    verification:
      - kind: integration
        ref: "tests/Feature/Filament/ProjectsRelationManagerTest.php#opens a second job from the New action, tied to the open conversation and with nothing sent or alerted"
        status: pass
      - kind: integration
        ref: "tests/Feature/Filament/ProjectsRelationManagerTest.php#is not read-only on the contact screen"
        status: pass
      - kind: integration
        ref: "tests/Feature/Filament/ProjectsRelationManagerTest.php#shows on the contact page"
        status: pass
    human_judgment: false
  - id: D3
    description: "Close (with a confirmation, open rows only), Reopen (closed rows only) and Rename (a one-field form filled with the current name, at most 100 characters) call the three actions with the signed-in user; a refused name shows its plain-words reason escaped in a danger notification"
    requirement: PROJ-01
    verification:
      - kind: integration
        ref: "tests/Feature/Filament/ProjectsRelationManagerTest.php#closes and reopens from the row"
        status: pass
      - kind: integration
        ref: "tests/Feature/Filament/ProjectsRelationManagerTest.php#renames from the row, and an empty name goes back to the word and number"
        status: pass
      - kind: integration
        ref: "tests/Feature/Filament/ProjectsRelationManagerTest.php#starts the rename form with the current name"
        status: pass
      - kind: integration
        ref: "tests/Feature/Filament/ProjectsRelationManagerTest.php#refuses a name over 100 characters on the form"
        status: pass
      - kind: integration
        ref: "tests/Feature/Filament/ProjectsRelationManagerTest.php#shows a refused name's reason without HTML"
        status: pass
    human_judgment: false
  - id: D4
    description: "The relation manager writes nothing itself: no Filament create, edit, delete, associate or bulk action, and the write-confinement scan watching this file passes"
    requirement: PROJ-01
    verification:
      - kind: unit
        ref: "tests/Unit/ProjectWritesTest.php#only the four project actions write the projects table"
        status: pass
    human_judgment: false
  - id: D5
    description: "The contact screen's project list and its actions can be reached and used by keyboard and screen reader (WCAG 2.2 AA)"
    requirement: PROJ-02
    verification: []
    human_judgment: true
    rationale: "Automated checks catch only part of WCAG; the keyboard-only and VoiceOver walk is left for end-of-phase UAT (WINDOWS.md entry 38)"
  - id: D6
    description: "The same behavior on Postgres (CI pgsql leg with ProjectsRelationManagerTest)"
    requirement: PROJ-01
    verification: []
    human_judgment: true
    rationale: "Needs the phase branch pushed to CI; left unrun by the executor and recorded as WINDOWS.md entry 39"
duration: 12 min
completed: 2026-10-30
---

# Phase 7 Plan 05: The Contact Screen Lists, Opens, Closes, Reopens and Renames Projects Summary

**A Filament relation manager on the contact screen that lists a contact's projects in the business's word and opens, closes, reopens and renames them by calling the four project actions, with escaped refusal notifications and no Filament write action of its own.**

## Performance

- **Duration:** 12 min (about 2026-10-30T20:07Z to 20:19Z)
- **Tasks:** 2 (tracer, then one expansion task)
- **Files:** 5 changed (2 created, 3 modified), plus the planning files
- **Commits:** 4 task commits (measured from the plan ledger: 3f5f944..1d08beb)

## Accomplishments

- `ProjectsRelationManager` (`$relationship = 'projects'`) shows the contact's projects in opening order. Columns: the word and number or the owner's name, Open or Closed (badge), Opened (in the business's display time zone), Opened by ("New lead", the user's name, or "A removed user") and Closed (placeholder "(open)"). The tab title is the live plural word ("Jobs", "Matters"), and the empty state reads "No jobs yet".
- `isReadOnly()` returns false, so Filament's read-only default for relation managers on a view page does not hide the buttons (research Pitfall 10).
- The "New job" header action calls `OpenProject::byHand()` with the contact and the signed-in user, so the new project is tied to the open conversation and nothing is asked, flagged, alerted or sent (D-04).
- Close (confirmation, open rows only), Reopen (closed rows only) and Rename (one field, `maxLength(100)`, filled with the current name) call `CloseProject`, `ReopenProject` and `RenameProject`. A `DomainException` from Rename becomes a danger notification with `e()` around the reason.
- `ContactResource::getRelations()` names the manager. `ContactResourceTest` and `RoomTimelineTest` pass unchanged with it on the page (D-22).
- BLUEPRINT section 3 has the Built bullet (Affects: sales, operations) and the sprint walk-through log has the `(plan 07-05)` line.

## Task Commits

1. **Task 1 (tracer), RED:** `89a181d` test(07-05): the owner opens a job from the contact screen
2. **Task 1, GREEN:** `c276775` feat(07-05): the contact screen lists projects and opens a new one (D-04, D-14)
3. **Task 2, RED:** `aa23da6` test(07-05): close, reopen and rename from the contact screen
4. **Task 2, GREEN:** `1d08beb` feat(07-05): close, reopen and rename from the contact screen (D-05, D-14)

## TDD Gate Compliance

RED then GREEN in order for both tasks; no REFACTOR commit was needed.

- **Task 1 RED:** `gsd check tdd-red-evidence` on a `--log-junit` report returned `RED_EVIDENCE_OK` (`target_test_failed`) for "it shows on the contact page". Semantic assessment: three tests errored with "Unable to find component" / "Class ... ProjectsRelationManager does not exist" (the planned reason) and the page test failed on its assertion that the page shows the list.
- **Task 2 RED:** `RED_EVIDENCE_OK` for "it starts the rename form with the current name" (null where the current name was expected). Semantic assessment: close, rename-from-row and the refusal cases errored with `ActionNotResolvableException: Action [close|rename] not found on table`, the planned reason. The word-follows-the-profile and two-open-projects cases already passed at RED by design, since they pin behavior the Task 1 code already had.

Tracer gate: the tracer's automated `<verify>` (the new file plus `ContactResourceTest`, `RoomTimelineTest` and `ProjectWritesTest`, 41 passed) ran green on the tree before the feat commit; logged "Tracer verified end-to-end, expanding" and continued with no checkpoint (verify is automated-only, `human_verify_mode` end-of-phase).

## Verification

- `php artisan test --compact tests/Feature/Filament tests/Feature/Projects tests/Unit/ProjectWritesTest.php tests/Feature/Rooms/RoomTimelineTest.php`: 202 passed.
- `php artisan test --compact`: **2364 passed (17727 assertions), 1 warning with no detail (the same single warning earlier plans reported)**, SQLite. The suite stood at 2353 after 07-04; the 11 new tests are all in `ProjectsRelationManagerTest`.
- Acceptance greps pass: `isReadOnly(): bool`, the `$relationship` line, `ProjectsRelationManager::class` in `ContactResource`, the three `Action::make(...)` names, `requiresConfirmation`, `e($exception->getMessage())`, `(plan 07-05)` in the sprint file; the count of `CreateAction|EditAction|DeleteAction|AssociateAction|BulkAction` is 0. `git diff --name-only --diff-filter=M main -- tests` lists only `tests/Feature/Records/CardInjectionTest.php` (from 07-03). `git diff --name-only main -- tests/Feature/Filament` lists only the new test file.
- Pint passes on the changed files.

## Decisions Made

- The wording stays plain and unstyled until Phase 11, and the actions show whether or not the leads room is live (research A2, adopted; one `visible()` condition changes it if Ian wants it tied to the room).
- The "Opened" notification names the new project by its number, counted with `where('id', '<=', ...)` on the contact's own projects, the same count `withNumber()` uses.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] The page-level test could not assert the list's text**
- **Found during:** Task 1 (GREEN)
- **Issue:** The plan's behavior case said a signed-in GET of the contact page "contains 'Jobs' and 'New job'". Filament renders a relation manager as a lazy-loaded Livewire component, so the first page response holds only a placeholder and neither string is in it. The RED test had that assumption and failed for that reason after the code was right.
- **Fix:** The test now asserts the page is OK, that `Livewire::test(ViewContact::class, ['record' => ...])->assertSeeLivewire(ProjectsRelationManager::class)`, and that `ContactResource::getRelations()` is exactly the manager. The title "Jobs" and the label "New job" are asserted by the tests that mount the relation manager itself (`getTitle()`, `assertTableActionHasLabel`, `assertSee`).
- **Files modified:** tests/Feature/Filament/ProjectsRelationManagerTest.php
- **Verification:** all 11 tests pass
- **Committed in:** `c276775`

**2. [Rule 3 - Blocking] The scaffolded relation manager had to be set aside for the RED commit**
- **Found during:** Task 1 (RED)
- **Issue:** `make:filament-relation-manager` writes the finished class straight away, which would have put the class on disk before the failing-test commit.
- **Fix:** Moved the generated file aside, committed the failing test, then wrote the class (every generated create, edit, delete, associate and bulk action removed). No effect on the final code.
- **Committed in:** `89a181d` (test), `c276775` (class)

---

**Total deviations:** 2 auto-fixed (1 bug in a test assumption, 1 blocking process note).
**Impact on plan:** None on scope; the behavior is as planned, with the text assertions moved to the tests that can see it.

## Issues Encountered

- Laravel Boost's `search-docs` tool was not available in this run; I read the installed Filament 5.10.1 source (`RelationManager::isReadOnly()`, `TestAction`, the table `TestsActions` helpers) instead. Header actions on a table are called with `callTableAction('new')` and no record.
- The `$relationship` acceptance grep returns false under zsh with a double-quoted `\$relationship` (a regex quirk); the same check with `grep -F` passes.

## Unrun hand checks

Both `<human-check>` items were left unrun, per Ian's standing rule that GSD runs do not pause for a live human check, and recorded as `unrun-verify` entries in `.planning/WINDOWS.md`:

- **Entry 38:** UAT keyboard-only and VoiceOver walk of a contact screen with two projects (the list is reachable; New, Close, Reopen and Rename work without a mouse; the close confirmation and the rename form take focus and return it; each button has an accessible name; CLAUDE.md WCAG 2.2 AA).
- **Entry 39:** after Ian pushes the phase branch, the CI pgsql leg is green with `ProjectsRelationManagerTest` included.

## Known Stubs

None. The screen wording is a deliberate placeholder for the Phase 11 wording pass (named in the plan), not a stub that blocks the goal.

## Threat Flags

None. T-07-11 (the screen sits behind the admin panel's auth, every action passes `auth()->user()`), T-07-12 (every owner-text notification title goes through `e()`, tested with a `<b>` and a direction mark in a refused name) and T-07-13 (no Filament write action in the file; `ProjectWritesTest` watches it) are mitigated as planned. No new endpoint, auth path or schema change.

## Next Phase Readiness

Ready for 07-06. PROJ-01 and PROJ-02 are also carried by other plans of this phase, so they are marked complete only if the shared-ID gate allows it.

## Self-Check: PASSED

- FOUND: app/Filament/Resources/Contacts/RelationManagers/ProjectsRelationManager.php, tests/Feature/Filament/ProjectsRelationManagerTest.php, app/Filament/Resources/Contacts/ContactResource.php (names the manager)
- FOUND commits: 89a181d, c276775, aa23da6, 1d08beb (all ancestors of HEAD; `git rev-list --count 3f5f944..HEAD` is 4)
