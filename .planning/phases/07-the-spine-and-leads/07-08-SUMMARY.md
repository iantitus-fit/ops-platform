---
phase: 07-the-spine-and-leads
plan: 08
subsystem: leads
tags: [laravel, queue, after-commit, savepoint, eloquent-events, pest]

requires:
  - phase: 07-the-spine-and-leads
    provides: OpenProject (07-02), the customer card Jobs section (07-03), the projects write guard (07-04), the leads room and its section (07-06, 07-07)
provides:
  - OpenProject::forNewLead(): lock the contact, skip any contact with a project, tie the project to the arrival's conversation, savepoint insert
  - OpenProjectForNewLead job (room checked at queue time and run time, opt-out check, tries 5, backoff 5/30/120/300)
  - Contact::created hook in LeadsServiceProvider::boot(), beside RecordInbound which is not edited
  - Automatic-open paragraph in the example leads room CONTEXT.md
affects: [phase-08-schedule-and-booking, leads, customer-card, front-desk]

actuals:
  tokens: 31000
  tasks: 3
  commits: 6
plan_head_before: 29f8a856b795a6382f1aa35725f26ea104667ad1
plan_head_after: 53637218edf7f1cfacea8689a9951930481d7044
commits: 6

tech-stack:
  added: []
  patterns:
    - "Model event hook that only queues: Contact::created asks hasRoom() and queues in DB::afterCommit, boot() reads nothing"
    - "DB::afterCommit callback with try/catch + report() so a failed push never reaches the committed inbound"
    - "Nested DB::transaction as a savepoint around an insert whose unique violation means 'already done'"

key-files:
  created:
    - app/Rooms/Leads/OpenProjectForNewLead.php
    - tests/Feature/Projects/OpenOnNewLeadTest.php
  modified:
    - app/Actions/OpenProject.php
    - app/Rooms/Leads/LeadsServiceProvider.php
    - tests/Feature/Records/CardInjectionTest.php
    - examples/example-hvac/rooms/leads/CONTEXT.md
    - docs/BLUEPRINT.md
    - docs/sprints/sprint-1.1.md

key-decisions:
  - "The push runs inside DB::afterCommit with its own try/catch, not ->afterCommit() inside a try, so the catch covers the push (plan design note)"
  - "The room is checked twice, at queue time (keeps the Queue::fake() tests unchanged) and at run time (D-03)"
  - "The arrival is the contact's lowest-id text_received or form_received activity; its message's conversation is the project's conversation (D-06)"

patterns-established:
  - "Failure isolation for a side effect of an inbound: report, never raise"

requirements-completed: [LEAD-05, PROJ-01, PROJ-02]

coverage:
  - id: D1
    description: "A text from a never-seen number, or a web form matching no contact, opens one project (opened_by lead, no user) tied to the contact and the conversation, shown on the card"
    requirement: LEAD-05
    verification:
      - kind: integration
        ref: "tests/Feature/Projects/OpenOnNewLeadTest.php#opens a project when a text comes from a new number"
        status: pass
      - kind: integration
        ref: "tests/Feature/Projects/OpenOnNewLeadTest.php#opens a project when a web form matches no one"
        status: pass
    human_judgment: false
  - id: D2
    description: "Known numbers, matching forms (phone written another way, email in another case), made-up contacts and a first STOP open nothing; the lead stays a lead, no text, no alert"
    requirement: LEAD-05
    verification:
      - kind: integration
        ref: "tests/Feature/Projects/OpenOnNewLeadTest.php (opens nothing for ..., opens a project for a first START or HELP, keeps the new lead a lead)"
        status: pass
    human_judgment: false
  - id: D3
    description: "The open never doubles and never delays the inbound: room off at either moment, no backfill, existing project, lost race, queue down, open throws, retries"
    requirement: LEAD-05
    verification:
      - kind: integration
        ref: "tests/Feature/Projects/OpenOnNewLeadTest.php#loses a race without an error and leaves one project"
        status: pass
      - kind: integration
        ref: "tests/Feature/Projects/OpenOnNewLeadTest.php#never breaks the inbound when the open cannot be queued"
        status: pass
      - kind: integration
        ref: "tests/Feature/Projects/OpenOnNewLeadTest.php#never breaks the inbound when the open throws"
        status: pass
    human_judgment: false
  - id: D4
    description: "The front desk behaves exactly as before and sees the project only through the card; RecordInbound, app/Ai, app/SendRule, rules and agents are untouched"
    requirement: PROJ-02
    verification:
      - kind: other
        ref: "git diff --quiet main -- app/Actions/RecordInbound.php app/Ai app/SendRule examples/example-hvac/rules examples/example-hvac/agents (exit 0); php artisan test (2407 passed)"
        status: pass
    human_judgment: false
  - id: D5
    description: "The savepoint and the partial unique index behave the same on Postgres"
    requirement: PROJ-01
    verification: []
    human_judgment: true
    rationale: "Tests ran on SQLite only; the CI pgsql leg runs after Ian pushes (WINDOWS.md entry 43)"

duration: 15min
completed: 2026-10-31
status: complete
---

# Phase 7 Plan 08: Every new lead opens a project Summary

**A text from a never-seen number, or a web form matching no one, now opens one project for that contact after the inbound commits, through a leads-room job that is gated on the room, skipped for a first STOP, retried, never doubled, and unable to fail or delay the inbound.**

## Performance

- **Duration:** about 15 min
- **Started:** 2026-10-31T14:55:25Z
- **Completed:** 2026-10-31T15:10Z
- **Tasks:** 3 (1 tracer, 2 auto, all TDD)
- **Files modified:** 8 (2 created)

## Accomplishments
- `OpenProject::forNewLead()` locks the contact row, returns null when the contact has any project, takes the conversation from the arrival message, and inserts inside a savepoint whose `UniqueConstraintViolationException` means "already opened".
- `OpenProjectForNewLead` queues in `DB::afterCommit` with try/catch and `report()`, checks `hasRoom('leads')` at queue time and run time, returns for an opted-out contact (a first STOP), and has `$tries = 5` with `backoff()` of 5, 30, 120 and 300 seconds.
- `LeadsServiceProvider::boot()` calls `parent::boot()` then listens for `Contact::created`; `RecordInbound` is not edited and the front desk is untouched.
- Tracer verified end-to-end (full suite green, 2388 tests) before Tasks 2 and 3 expanded it.

## TDD Gate Compliance

All three tasks have a `test(07-08)` commit before the `feat(07-08)` commit. No refactor commits were needed.

**RED evidence (semantic assessment, inspected by hand; the project runs Pest, not a format the `tdd-red-evidence` classifier reads):**
- Task 1: both target cases failed at `Project::sole()` ("No query results for model Project") after `Contact::sole()` passed, which is the planned assertion (no project opens). Valid.
- Task 2: only 'opens nothing for a first text that is STOP' failed, on `Project::count()` ("1 is identical to 0"), the planned assertion. The other ten cases passed at once, as the plan expected: they prove behavior RecordInbound's own matching and Task 1's arrival check already give (known number, form matching by phone or by email, made-up contact, first START or HELP, picture-only text, lead stays a lead).
- Task 3: five failed as targeted: 'opens nothing when the room is off by the time the job runs' (1 is not 0), 'loses a race without an error' (the SQLite UNIQUE violation escaped), both 'never breaks the inbound' cases (500 instead of 200), and 'retries a failed open' (undefined `$tries`). 'queues nothing while the leads room is off', 'queues one open carrying the new contact while the room is on', 'backfills nothing' and 'does nothing for a contact who already has a project' (plus the retry-leaves-one case) passed at once, as the plan expected, because they prove Task 1's queue-time check and exists check.

## Task Commits

1. **Task 1: tracer, a new lead opens a project** - `0c78074` (test), `276f634` (feat)
2. **Task 2: only an inbound-created contact gets one, a first STOP none** - `8180060` (test), `ba6b778` (feat)
3. **Task 3: never two, never in the way** - `8acb5cf` (test), `5363721` (feat)

**Plan metadata:** the docs commit that follows this file.

## Files Created/Modified
- `app/Actions/OpenProject.php` - `forNewLead()` with row lock, any-project check, savepoint insert
- `app/Rooms/Leads/OpenProjectForNewLead.php` - the queued open
- `app/Rooms/Leads/LeadsServiceProvider.php` - `boot()` with the `Contact::created` hook
- `tests/Feature/Projects/OpenOnNewLeadTest.php` - 21 cases
- `tests/Feature/Records/CardInjectionTest.php` - line 90, `toHaveCount(4)` to `toHaveCount(6)` (D-22)
- `examples/example-hvac/rooms/leads/CONTEXT.md` - the automatic-open paragraph
- `docs/BLUEPRINT.md` - Built bullets in sections 1, 3 and 10, and the glance row for section 3
- `docs/sprints/sprint-1.1.md` - one walk-through line (plan 07-08)

## Decisions Made
- Followed the plan's design note: the try/catch sits around the push inside the after-commit callback, so a queue that is down is caught where it happens.
- The queue-time room check landed in Task 1 (not Task 3) because the plan names it as the thing that keeps the `Queue::fake()` tests unchanged; the run-time check landed in Task 3, test first.

## D-22 audit (whole phase)
`git diff --name-only --diff-filter=M main -- tests` lists exactly `tests/Feature/Records/CardInjectionTest.php`, `tests/Feature/Rooms/FrontDeskBootTest.php` and `tests/Pest.php`. `git diff --numstat` shows 1 removed line in each of FrontDeskBootTest.php and Pest.php, and 1 in CardInjectionTest.php, which also adds 13 lines (the 07-03 case). The line this plan changed is CardInjectionTest.php:90; `:36` is unchanged. `git diff --quiet main -- app/Actions/RecordInbound.php app/Ai app/SendRule examples/example-hvac/rules examples/example-hvac/agents` exits 0.

## Deviations from Plan

None - plan executed exactly as written. No existing test other than the three named lines needed to change.

## Issues Encountered
- A heredoc to `/tmp/x` left in a Bash call hung for the 120 second timeout (my own slip, no effect on the repo; the file was removed).

## Known Stubs
None.

## Threat Flags
None. The new surface (a model event listener and a queued job) is covered by T-07-20 to T-07-24 in the plan's threat model.

## Authentication Gates
None.

## User Setup Required
None - no external service configuration required.

## Unrun checks (recorded, never paused)
- **Postgres / CI pgsql leg:** after Ian pushes the phase branch, the whole suite is green on pgsql, in particular 'loses a race without an error and leaves one project' (savepoint and partial index on Postgres) and the three changed test lines. Recorded as `.planning/WINDOWS.md` entry 43 (`unrun-verify`).
- Tests ran on SQLite only here. The lost-race case inserts the competing row from a `DB::listen` callback right after the job's "any project" select, so the job's own insert meets the unique index inside its savepoint.

## Next Phase Readiness
Phase 7 plans are all complete; the phase is ready for verification. Criterion 3 is met by shape (D-09): a gap about appointments is answered by Phase 8 criterion 2, not by an appointments table here. Deferred (already in BLUEPRINT section 6): a crash between the commit and the push loses the open, covered by the owner's New action and Phase 8 booking, with a pre-first-client sweep to catch it.

## Self-Check: PASSED
- Files exist: `app/Rooms/Leads/OpenProjectForNewLead.php`, `tests/Feature/Projects/OpenOnNewLeadTest.php`, and the modified files above.
- Commits `0c78074`, `276f634`, `8180060`, `ba6b778`, `8acb5cf`, `5363721` are ancestors of HEAD; `git rev-list --count` from the ledger base gives 6.
- All task `<acceptance_criteria>` re-run and passing; `php artisan test` 2407 passed on SQLite.
