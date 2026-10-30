---
phase: 07-the-spine-and-leads
plan: 02
subsystem: database
tags: [laravel, eloquent, migration, partial-unique-index, customer-card, tdd]
requires:
  - phase: 07-the-spine-and-leads
    provides: "ProjectWord and ActiveConfig::projectWord() (plan 07-01)"
provides:
  - "The platform projects table with the partial unique index projects_one_lead_project_per_contact"
  - "App\\Models\\Project with relations, isOpen(), withNumber() scope and label()"
  - "App\\Enums\\ProjectActor (lead, user) and ActivityType::ProjectOpened"
  - "App\\Actions\\OpenProject::byHand() with a private open() shared with plan 07-08"
  - "Morph nickname 'project' and Contact::projects()"
  - "The customer card draws '<Word> N opened' in the live word"
affects: [07-03 customer card, 07-04 write guard, 07-05 contact screen, 07-07 dashboard, 07-08 automatic open, 08 schedule and booking]
actuals:
  tokens: 8650
  tasks: 2
  commits: 4
plan_head_before: 677f8d467ca4199a612aad1297a3462a40890a4f
plan_head_after: 6caee9a8cefb5ba6eef13c9f6962ca80ffccab59
tech-stack:
  added: []
  patterns:
    - "One writer action with a locked contact row, a private open() and an after-commit CustomerRecordChanged (mirrors CloseConversation)"
    - "A correlated count subquery in addSelect, exposed as a #[Scope] (withNumber)"
    - "A timeline arm that composes its label from the live word and a per-contact number, with a fixed platform sentence as the body"
key-files:
  created:
    - database/migrations/2026_10_29_000001_create_projects_table.php
    - app/Models/Project.php
    - database/factories/ProjectFactory.php
    - app/Enums/ProjectActor.php
    - app/Actions/OpenProject.php
    - tests/Feature/Projects/ProjectRecordTest.php
  modified:
    - app/Enums/ActivityType.php
    - app/Providers/AppServiceProvider.php
    - app/Models/Contact.php
    - app/Records/CustomerCard.php
    - docs/BLUEPRINT.md
    - docs/sprints/sprint-1.1.md
key-decisions:
  - "closed_at null means open: no status column, no appointment column (research A3, D-09)"
  - "Timeline summaries are two fixed platform sentences; the card composes '<Word> N opened' when it renders, so changing Job to Matter relabels history (research A4)"
requirements-completed: [PROJ-01]
coverage:
  - id: D1
    description: "The owner's open writes one project tied to the contact and its open conversation (none when no conversation is open), records who opened it, adds one ProjectOpened timeline entry, and leaves the contact's status alone"
    requirement: PROJ-01
    verification:
      - kind: integration
        ref: "tests/Feature/Projects/ProjectRecordTest.php#opens a project by hand, tied to the open conversation, and puts it on the card"
        status: pass
      - kind: integration
        ref: "tests/Feature/Projects/ProjectRecordTest.php#stores no conversation when none is open"
        status: pass
    human_judgment: false
  - id: D2
    description: "The customer card draws '### <time> · Job N opened' under 'System note:' in the live word, for a contact with one or several projects"
    requirement: PROJ-01
    verification:
      - kind: integration
        ref: "tests/Feature/Projects/ProjectRecordTest.php#opens a second project by hand while the first is open"
        status: pass
      - kind: integration
        ref: "php artisan test --compact tests/Feature/Records (existing card tests, unchanged)"
        status: pass
    human_judgment: false
  - id: D3
    description: "The record's shape: no appointment column, any number opened by hand, one opened by a new lead at most, numbered per contact in opening order, label falls back to the name, nullOnDelete and cascade, owned by no room"
    requirement: PROJ-01
    verification:
      - kind: integration
        ref: "tests/Feature/Projects/ProjectRecordTest.php (has no appointment column; lets one contact hold any number; numbers each contact's projects; keeps who opened it when the user is deleted; is owned by no room)"
        status: pass
    human_judgment: false
  - id: D4
    description: "The partial unique index refuses a second lead-opened project on Postgres (the CI pgsql leg)"
    requirement: PROJ-01
    verification:
      - kind: integration
        ref: "tests/Feature/Projects/ProjectRecordTest.php#refuses a second project opened by a new lead for one contact (SQLite pass; Postgres unrun)"
        status: unknown
    human_judgment: true
    rationale: "Needs the phase branch pushed and the CI pgsql leg run; Ian pushes, so it is recorded for end-of-phase UAT (WINDOWS entry 35)"
duration: 6min
completed: 2026-10-30
status: complete
---

# Phase 7 Plan 02: The Spine, the Project Record and the Owner's Open Summary

**A platform-owned `projects` table with a partial unique index (one lead-opened project per contact, any number opened by hand), the `Project` model with per-contact numbering, and `OpenProject::byHand()`, which ties a project to the contact and its open conversation and puts one fixed-sentence entry on the timeline that the customer card draws as "Job 1 opened" in the business's live word.**

## Performance

- **Duration:** 6 min
- **Started:** 2026-10-30T15:39:46Z
- **Completed:** 2026-10-30T15:46:00Z
- **Tasks:** 2
- **Files modified:** 12 (6 created, 6 modified)

## Accomplishments
- `projects` table: `contact_id` cascades, `conversation_id` and the two user ids null on delete, `name` up to 100 characters, `opened_by`/`closed_by` as `ProjectActor`, `closed_at` null means open. No appointment or status column. `projects_one_lead_project_per_contact` is a partial unique index in the same form as the conversations one.
- `OpenProject::byHand()` locks the contact row, takes the contact's open conversation at that moment (or none), writes the project and one `ProjectOpened` activity ("Opened by the owner"), never touches the contact's status, and dispatches `CustomerRecordChanged` after the commit. The private `open()` is ready for plan 07-08's `forNewLead()`.
- `CustomerCard` reads the live word and each project's 1-based place in the contact's opening order, and draws `### <time> · Job N opened` under `System note:`; a project missing from the numbering is labelled by the word alone.
- `Project::withNumber()` counts per contact in one correlated subquery (SQLite and Postgres safe); `label()` returns the trimmed name else "<Word> N". Factory states `openedByLead()`, `openedByUser()`, `closed()`.
- Morph nickname `'project'`, `Contact::projects()`, BLUEPRINT Built bullets in Sales and Operations, one walk-through line.

## TDD Gate Compliance

- **RED (task 1):** `5f22e1f` (test). 3 tests, all errors: `Target class [App\Actions\OpenProject] does not exist.` `gsd check tdd-red-evidence` (JUnit from `--log-junit`) returned `RED_EVIDENCE_OK`. Semantic assessment: the target ran and failed for the planned reason (the writer class does not exist), not on a syntax, import or fixture fault.
- **GREEN (task 1):** `c2c7b36` (feat). 53 of 53 pass across ProjectRecordTest, tests/Feature/Records and ContactResourceTest.
- **RED (task 2):** `cf816e4` (test). 10 tests, 5 failing. `RED_EVIDENCE_OK` for the target "numbers each contact's projects in opening order", which failed with `BadMethodCallException: Call to undefined method Eloquent\Builder::withNumber()`. The other four failures were the undefined factory states `openedByUser()` and `openedByLead()` (the case "has no appointment column", the room-ownership case and the first three Task 1 cases passed at RED because the shape was already in the Task 1 migration; they are characterization cases, not RED targets). Semantic assessment: planned reason.
- **GREEN (task 2):** `6caee9a` (feat). 85 of 85 pass in the plan's focused command; the full suite passed 2312 of 2312.
- **REFACTOR:** none needed.

Tracer gate: the tracer's automated `<verify>` passed after its feat commit (53 of 53, re-run once more before expanding), so Task 2 went ahead.

## Task Commits

1. **Task 1 RED:** `5f22e1f` (test) - the owner's open writes a project and the card shows it
2. **Task 1 GREEN:** `c2c7b36` (feat) - migration, model, enum, factory, morph nickname, OpenProject, card arm
3. **Task 2 RED:** `cf816e4` (test) - the project record's shape and numbering
4. **Task 2 GREEN:** `6caee9a` (feat) - withNumber(), label(), factory states, BLUEPRINT bullets, walk-through line

**Plan metadata:** recorded in the docs(07-02) commit that follows this file.

## Files Created/Modified
- `database/migrations/2026_10_29_000001_create_projects_table.php` - the table and the partial unique index
- `app/Models/Project.php` - relations, casts, `isOpen()`, `withNumber()`, `label()`
- `database/factories/ProjectFactory.php` - default hand-opened project and three states
- `app/Enums/ProjectActor.php` - lead or user
- `app/Enums/ActivityType.php` - `ProjectOpened = 'project_opened'`
- `app/Providers/AppServiceProvider.php` - `'project' => Project::class` in the enforced morph map
- `app/Models/Contact.php` - `projects(): HasMany`
- `app/Actions/OpenProject.php` - `byHand()` and the shared private `open()`
- `app/Records/CustomerCard.php` - the `ProjectOpened` arm, `projectParts()`, `projectNumbers()`
- `tests/Feature/Projects/ProjectRecordTest.php` - 10 cases
- `docs/BLUEPRINT.md`, `docs/sprints/sprint-1.1.md` - Built bullets (sections 3 and 4), walk-through line (plan 07-02)

## Decisions Made
- Followed the plan. `projectNumbers()` is a small loop over `pluck('id')` rather than a flip-and-map chain, for readability.
- `byHand()` locks the contact row before reading the open conversation, so plan 07-08's automatic open cannot interleave with it.

## Deviations from Plan

None - plan executed exactly as written.

Notes, not deviations: (1) `php artisan make:model --migration` stamps the file with today's date, so it was renamed to `2026_10_29_000001_create_projects_table.php` as the plan directed; (2) the acceptance line `git diff --name-only main -- tests` lists three files under `tests/Feature/Projects/` because the two 07-01 test files are also ahead of main on this branch; all are under the permitted directory and no existing test changed; (3) `git diff --name-only main -- database/migrations` prints nothing because the new migration was untracked at that moment; after the commit the only migration ahead of main is the projects one.

## Issues Encountered
None. The full suite reported one warning with no detail (`"warnings":1`); it is not from this plan's files.

## Unrun human check (end-of-phase UAT)
- After Ian pushes the phase branch, the CI tests workflow's pgsql leg should be green with `ProjectRecordTest` included, in particular "refuses a second project opened by a new lead for one contact" (the partial unique index on Postgres). Left unrun, never a pause; recorded as an `unrun-verify` entry (id 35) in `.planning/WINDOWS.md`. Locally the whole suite passed on SQLite (2312 tests).

## Known Stubs
None.

## Threat Flags
None. T-07-03 (partial unique index plus its test), T-07-04 (`opened_by`, `opened_by_user_id`, `opened_at` and one timeline entry on every open, `nullOnDelete`) and T-07-05 (two fixed summaries, label built from the word list and an integer) are mitigated as planned. No file under `app/Ai`, `app/SendRule` or `RecordInbound` changed.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Plan 07-03 can add the card's project section using `Project::withNumber()` and `label()`; `CustomerCard` already carries the word and the numbers.
- Plan 07-04's write-guard test can name `OpenProject` as the one writer so far; plan 07-08 adds `forNewLead()` on the private `open()`.
- No blockers.

## Self-Check: PASSED

- Files found on disk: the migration, `Project.php`, `ProjectFactory.php`, `ProjectActor.php`, `OpenProject.php`, `ProjectRecordTest.php`.
- Commits `5f22e1f`, `c2c7b36`, `cf816e4`, `6caee9a` are ancestors of HEAD; `git rev-list --count 677f8d4..HEAD` is 4.
- Plan verification: focused command 85 of 85 pass; full `php artisan test --compact` 2312 of 2312 pass on SQLite with no existing test changed; `git diff --quiet main -- app/Actions/RecordInbound.php app/Ai app/SendRule` exits 0.

---
*Phase: 07-the-spine-and-leads*
*Completed: 2026-10-30*
