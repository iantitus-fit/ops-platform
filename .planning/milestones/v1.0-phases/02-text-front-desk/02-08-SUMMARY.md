---
phase: 02-text-front-desk
plan: 08
subsystem: inbound-dispatch
tags: [laravel-queue, debounce, migrations, pest, sqlite]

requires:
  - phase: 02-text-front-desk
    provides: Postgres and SQLite test foundation (plan 02-01)
provides:
  - "messages.dispatched_at and messages.handled_at (nullable timestamps) with an index on direction + dispatched_at"
  - "Backfill migration: every existing message gets both columns set to created_at"
  - "App\\Actions\\DispatchInbound::handle(Message): bool, the only place ProcessInbound is queued, unique per message"
  - "App\\Actions\\ClaimInboundBurst::handle(int): Collection, one conditional update per row on handled_at"
  - "App\\Jobs\\ProcessInbound(messageId, conversationId) with #[DebounceFor(20, maxWait: 90)] and debounceId() = conversation id"
  - "WR-02 closed: a retried webhook whose message was recorded but never queued is queued then"
affects: [02-09 sweep and form-lead rule, 02-14 front desk agent behind ProcessInbound]

actuals:
  tokens: 5600
  tasks: 1
  commits: 2
plan_head_before: 32b2c543801bb83302e11adcc023908c7ab3d471
plan_head_after: 4dbf4055c356611bd366b6ae2fa546ca0cf45fdc

tech-stack:
  added: []
  patterns:
    - "Claim a row with a conditional update (whereNull + update returns 1) instead of a cache lock; the database settles the race"
    - "Debounce a job by an id (conversation) with an attribute, and treat the debounce as an optimisation, not the guarantee"
    - "Data backfill in its own migration so a test can run up() against rows it built"

key-files:
  created:
    - app/Actions/DispatchInbound.php
    - app/Actions/ClaimInboundBurst.php
    - database/migrations/2026_10_12_000001_add_dispatch_columns_to_messages_table.php
    - database/migrations/2026_10_12_000002_backfill_dispatch_columns_on_messages.php
    - tests/Feature/Inbound/DispatchInboundTest.php
  modified:
    - app/Actions/RecordInbound.php
    - app/Jobs/ProcessInbound.php
    - app/Models/Message.php
    - tests/Feature/Inbound/FakeTextWebhookTest.php
    - docs/sprints/sprint-1.md

key-decisions:
  - "ProcessInbound::handle() claims the burst and returns when it is empty; the agent run is left to plan 02-14"
  - "The retried-webhook test keeps its name (still accurate) and now also asserts dispatched_at is set"

requirements-completed: [AGNT-02]

coverage:
  - id: D1
    description: "A text through the fake webhook is claimed (dispatched_at) and ProcessInbound is queued once with message and conversation ids"
    requirement: AGNT-02
    verification:
      - kind: integration
        ref: "tests/Feature/Inbound/DispatchInboundTest.php#queues the debounced job once for a text that comes in through the fake webhook and marks it dispatched"
        status: pass
    human_judgment: false
  - id: D2
    description: "A retried webhook queues nothing for a dispatched message, and queues a recorded-but-undispatched message (WR-02)"
    requirement: AGNT-02
    verification:
      - kind: integration
        ref: "tests/Feature/Inbound/DispatchInboundTest.php#queues a recorded text that was never queued when the provider retries it (WR-02)"
        status: pass
      - kind: integration
        ref: "tests/Feature/Inbound/FakeTextWebhookTest.php#records a retried webhook once and queues nothing the second time"
        status: pass
    human_judgment: false
  - id: D3
    description: "DispatchInbound claims once (true then false) and ignores outbound and owner messages"
    requirement: AGNT-02
    verification:
      - kind: unit
        ref: "tests/Feature/Inbound/DispatchInboundTest.php#claims a message once: the second call returns false and queues nothing more"
        status: pass
      - kind: unit
        ref: "tests/Feature/Inbound/DispatchInboundTest.php#does not queue an outbound or an owner message"
        status: pass
    human_judgment: false
  - id: D4
    description: "ProcessInbound waits 20 seconds, debounces by conversation id, carries maxWait 90 and is not ShouldBeUnique"
    verification:
      - kind: unit
        ref: "tests/Feature/Inbound/DispatchInboundTest.php#waits 20 seconds and debounces by conversation so a burst is answered together"
        status: pass
    human_judgment: false
  - id: D5
    description: "Running the job twice claims the burst once; the second run claims nothing; other conversations, replies and handled texts are untouched"
    requirement: AGNT-02
    verification:
      - kind: integration
        ref: "tests/Feature/Inbound/DispatchInboundTest.php#claims every unhandled text in the conversation on the first run and nothing on the second"
        status: pass
      - kind: integration
        ref: "tests/Feature/Inbound/DispatchInboundTest.php#hands back the claimed burst oldest first and leaves other conversations, replies and handled texts alone"
        status: pass
    human_judgment: false
  - id: D6
    description: "Backfill sets dispatched_at and handled_at to created_at for existing messages and leaves already-set values alone"
    verification:
      - kind: integration
        ref: "tests/Feature/Inbound/DispatchInboundTest.php#backfills dispatched_at and handled_at to created_at for messages from before this phase"
        status: pass
    human_judgment: false

duration: 6min
completed: 2026-10-12
status: complete
---

# Phase 2 Plan 08: Dispatch Once and Claim the Burst Summary

**An inbound text is claimed with a conditional update on `dispatched_at` and queued exactly once, `ProcessInbound` is debounced by conversation (20 s, max 90 s), and its first step claims the burst by `handled_at` so two runs never answer the same texts.**

## Performance

- **Duration:** 6 min
- **Started:** 2026-10-12T01:30:00Z
- **Completed:** 2026-10-12T01:36:00Z
- **Tasks:** 1 (tracer, TDD)
- **Files modified:** 10

## Accomplishments

- `DispatchInbound` is the single place `ProcessInbound` is queued. The webhook, its retries and (in 02-09) the sweep all go through it, so a message is never queued twice.
- WR-02 is closed: both already-recorded return paths in `RecordInbound` call `DispatchInbound`, so a message recorded but never queued (crash after commit) is queued when the provider retries.
- `ProcessInbound` now takes `(messageId, conversationId)`, carries `#[DebounceFor(20, maxWait: 90)]`, and `handle(ClaimInboundBurst)` claims the burst first. The debounce is an optimisation; the database claim is the guarantee.
- A separate backfill migration sets both new columns to `created_at` for existing messages, so the first sweep after migrating never texts a Phase 1 or seeded customer.

## Task Commits

1. **Task 1 (tracer): end-to-end dispatch claim, debounce and burst claim**
   - RED: `38e4856` (test) - `DispatchInboundTest` plus `dispatched_at` assertion on the retry test
   - GREEN: `4dbf405` (feat) - migrations, actions, job, model, RecordInbound wiring, walk-through log

**Plan metadata:** committed separately (docs: complete plan)

## TDD Gate Compliance

- **RED:** 38e4856. Target tests failed for the planned reason: `Target class [App\Actions\DispatchInbound] does not exist`, `Target class [App\Actions\ClaimInboundBurst] does not exist`, `no such column: dispatched_at`, and `Expecting null not to be null` on `dispatched_at` after a webhook post. Run result: 24 tests, 15 passed (unchanged Phase 1 tests), 3 failures and 6 errors, all in the new or updated tests. Semantic assessment: every failure is on the behavior this plan adds (missing class, missing column, unset claim), not on a syntax, fixture or discovery fault. `gsd check tdd-red-evidence` was not run: it supports TAP, JUnit, swift-testing and unittest reports and this project's Pest JSON line is none of them; semantic inspection of the unmodified output was used instead.
- **GREEN:** 4dbf405. All 98 tests in `tests/Feature/Inbound` plus the money guard pass; full suite 315 passed.
- **REFACTOR:** none needed.

## Files Created/Modified

- `app/Actions/DispatchInbound.php` - conditional-update claim, queues `ProcessInbound` only when it claimed the row; false for non-customer or non-inbound messages
- `app/Actions/ClaimInboundBurst.php` - claims unhandled inbound customer messages of a conversation one row at a time, returns them oldest first
- `app/Jobs/ProcessInbound.php` - debounced job, claims the burst first
- `app/Actions/RecordInbound.php` - dispatches through `DispatchInbound` on the new path and both already-recorded paths
- `app/Models/Message.php` - fillable and datetime casts for the two columns
- `database/migrations/2026_10_12_000001_add_dispatch_columns_to_messages_table.php` - columns and sweep index
- `database/migrations/2026_10_12_000002_backfill_dispatch_columns_on_messages.php` - backfill to `created_at`
- `tests/Feature/Inbound/DispatchInboundTest.php` - new tests; `tests/Feature/Inbound/FakeTextWebhookTest.php` - retry test also asserts `dispatched_at`
- `docs/sprints/sprint-1.md` - three walk-through lines (conditional-update claim, debounced jobs, data migrations)

## Decisions Made

- `ProcessInbound::handle()` stops after the claim; running the front desk is plan 02-14's job, as the plan directs.
- The existing retried-webhook test keeps its name (it still describes the behavior) and gains the `dispatched_at` assertion.

## Deviations from Plan

None - plan executed exactly as written.

Notes, not deviations: the plan's example payload helper `fakeTextPayload()` already exists in `tests/Pest.php` and was used. The migration files were renamed to the planned `2026_10_12_00000N_` names after `make:migration`. `php artisan migrate` was run on the local development database, which applied the backfill.

## Issues Encountered

None.

## Known Stubs

None. `ProcessInbound::handle()` deliberately does nothing after the claim until plan 02-14; that is the plan's stated scope, not a stub that blocks this plan's goal.

## Threat Flags

None. No new endpoint, auth path or trust boundary; T-02-14 (replayed or retried webhook) is mitigated by the existing unique (provider, provider_message_id) plus the `dispatched_at` conditional update, with replay and WR-02 tests.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Plan 02-09 can add the scheduled sweep (`dispatched_at` null, older than two minutes, bounded recency window) calling `DispatchInbound` per row; the backfill makes old rows invisible to it.
- Plan 02-14 can put the front-desk agent inside `ProcessInbound::handle()` after the burst claim. Tests that run the job should keep calling it through `app()->call([$job, 'handle'])`.
- Flagged assumption carried forward: a worker that dies after claiming a burst and before replying leaves that burst unanswered; the owner still sees the texts. Automatic recovery is not part of D-42.

## Self-Check: PASSED

- Created files exist (checked with `ls`/grep): DispatchInbound, ClaimInboundBurst, both migrations, DispatchInboundTest.
- Commits 38e4856 and 4dbf405 are on `gsd/phase-02-text-front-desk`.
- Acceptance criteria re-run: `whereNull('dispatched_at')` and `ProcessInbound::dispatch(` in DispatchInbound; `#[DebounceFor(20, maxWait: 90)]` and `function debounceId(` in ProcessInbound with no ShouldBeUnique; RecordInbound references DispatchInbound and no direct `ProcessInbound::dispatch`; backfill migration present.
- Verification: `php artisan test --compact tests/Feature/Inbound tests/Feature/Quality/MoneyIsIntegerCentsTest.php` 98 passed; full suite 315 passed.

---
*Phase: 02-text-front-desk*
*Completed: 2026-10-12*
