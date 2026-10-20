---
phase: 02-text-front-desk
plan: 09
subsystem: inbound-dispatch
tags: [laravel-scheduler, queue, pest, d-41, d-42]

requires:
  - phase: 02-text-front-desk
    provides: DispatchInbound claim, ClaimInboundBurst, dispatched_at/handled_at backfill (plan 02-08)
provides:
  - "App\\Jobs\\SweepUndispatched: scheduled safety net queuing inbound customer texts aged 2 minutes to 24 hours with no dispatched_at, through DispatchInbound"
  - "Schedule::job(new SweepUndispatched)->everyFiveMinutes()->withoutOverlapping() in routes/console.php"
  - "schedule:work added to composer run dev when the app is local"
  - "meta.matched_by (phone, email, new) on web form messages"
  - "ProcessInbound stops after the claim unless the burst holds an SMS from the customer; handle() returns bool"
affects: [02-14 front desk run inside ProcessInbound, dashboard open leads, first deploy cron]

actuals:
  tokens: 4300
  tasks: 1
  commits: 2
plan_head_before: 6f808cd78411481d1ca2d9c9d93df79775568f45
plan_head_after: 35a47e8cbc614cf9663b61d91057cdbd0a0e51ba

tech-stack:
  added: []
  patterns:
    - "Scheduled queued job with withoutOverlapping, reusing the same conditional-update claim as the webhook"
    - "Time-window second guard (min age, max age) on any sweep that could message customers"

key-files:
  created:
    - app/Jobs/SweepUndispatched.php
    - tests/Feature/Inbound/SweepUndispatchedTest.php
  modified:
    - routes/console.php
    - app/Providers/AppServiceProvider.php
    - app/Actions/RecordInbound.php
    - app/Jobs/ProcessInbound.php
    - tests/Feature/Inbound/DispatchInboundTest.php
    - tests/Feature/Inbound/ContactMatchingTest.php
    - docs/sprints/sprint-1.md

key-decisions:
  - "ProcessInbound::handle() returns bool (true = goes on to the front desk) so the D-41 stop is observable in a test before plan 02-14 adds the agent run"
  - "Walk-through log gets one new line (scheduler); debounce, conditional-update claim and data-migration lines were already written by plan 02-08 and are not repeated"
  - "The sweep also queues web form messages; ProcessInbound is what stops them, so there is one rule in one place"

patterns-established:
  - "Form leads are claimed (handled_at set) and then stop; the agent only answers a conversation whose burst holds an SMS from the customer"

requirements-completed: [AGNT-02]

coverage:
  - id: D1
    description: "Scheduled sweep queues undispatched inbound texts aged 2 minutes to 24 hours through DispatchInbound, once, and never backfilled or old rows"
    requirement: AGNT-02
    verification:
      - kind: unit
        ref: "tests/Feature/Inbound/SweepUndispatchedTest.php"
        status: pass
    human_judgment: false
  - id: D2
    description: "SweepUndispatched is on the scheduler every five minutes with overlap protection"
    requirement: AGNT-02
    verification:
      - kind: unit
        ref: "tests/Feature/Inbound/SweepUndispatchedTest.php#is on the scheduler every five minutes without overlapping"
        status: pass
      - kind: other
        ref: "php artisan schedule:list"
        status: pass
    human_judgment: false
  - id: D3
    description: "Web form messages record meta.matched_by (phone, email, new) and a form-only burst stops after the claim"
    requirement: AGNT-02
    verification:
      - kind: unit
        ref: "tests/Feature/Inbound/ContactMatchingTest.php (matched_by cases)"
        status: pass
      - kind: unit
        ref: "tests/Feature/Inbound/DispatchInboundTest.php#claims a burst of only web form messages and stops"
        status: pass
    human_judgment: false
  - id: D4
    description: "composer run dev runs the scheduler locally (schedule:work registered through DevCommands)"
    verification: []
    human_judgment: true
    rationale: "Registered in AppServiceProvider boot when the app is local; no automated test starts composer run dev. Confirm the 'schedule' tab appears next time dev is started."

duration: 14min
completed: 2026-10-12
status: complete
---

# Phase 2 Plan 09: Sweep Undispatched and Form-Lead Stop Summary

**A five-minute scheduled sweep queues any inbound text the webhook never queued (through the same DispatchInbound claim, inside a 2-minute to 24-hour window), web form leads record how they matched and stop before the front desk, and `composer run dev` now runs the scheduler.**

## Performance

- **Duration:** 14 min
- **Started:** 2026-10-12T01:29:00Z
- **Completed:** 2026-10-12T01:43:36Z
- **Tasks:** 1 (tracer)
- **Files modified:** 9

## Accomplishments
- `SweepUndispatched` finds inbound customer messages with null `dispatched_at`, created between 24 hours and 2 minutes ago, and sends each through `DispatchInbound`, so running it twice or racing the webhook queues a text once (D-42, closes WR-02's sweep half).
- T-02-15 mitigated: a test runs the plan 02-08 backfill, lets 10 minutes pass and proves nothing is swept.
- `meta.matched_by` is `phone`, `email` or `new` on web messages, merged next to `meta.form` (D-41, closes WR-13's "record matched_by").
- `ProcessInbound` claims the burst and then returns unless it holds an SMS from the customer, so a form lead never reaches the front desk (T-02-16).
- `schedule:work` registered via `DevCommands::artisan` for local, and the scheduler entry shows in `php artisan schedule:list` as `*/5 * * * *`.

## Task Commits

1. **Task 1 (tracer), RED:** `d22cdb4` (test) - failing sweep, schedule, matched_by and form-lead tests
2. **Task 1 (tracer), GREEN:** `35a47e8` (feat) - sweep job, schedule entry, dev scheduler, matched_by, form-lead stop, walk-through line

**Plan metadata:** recorded in the docs commit that follows this file.

## TDD Gate Compliance

- **RED:** `d22cdb4`. The run reported 14 failures out of 106 tests (4 failures, 10 errors). The sweep tests errored with `Class "App\Jobs\SweepUndispatched" not found` and the scheduler test failed on `Expecting null not to be null` (no scheduled event), which are the planned behaviors absent. The three `matched_by` tests errored on `Undefined array key "matched_by"` and the key-presence test failed on `has the key [matched_by]`. The two ProcessInbound tests failed on `Failed asserting that null is false/true` because `handle()` returned void. Semantic assessment: every failure is the target behavior missing, not setup, import or fixture breakage; the Pest JSON summary is not one of the formats `gsd_run check tdd-red-evidence` parses, so the machine classifier was not run and this assessment is by inspection of the real output.
- **GREEN:** `35a47e8`. Inbound folder 106/106, full suite 372/372 (baseline 358 before this plan).
- **REFACTOR:** none needed.

## Files Created/Modified
- `app/Jobs/SweepUndispatched.php` - scheduled sweep, `MIN_AGE_MINUTES = 2`, `WINDOW_HOURS = 24`
- `routes/console.php` - `Schedule::job(...)->everyFiveMinutes()->withoutOverlapping()`
- `app/Providers/AppServiceProvider.php` - `DevCommands::artisan('schedule:work', 'schedule')` when local
- `app/Actions/RecordInbound.php` - `matchOrCreateContact()` also returns phone/email/new; merged into web message meta
- `app/Jobs/ProcessInbound.php` - stops unless the burst holds an SMS; returns bool
- `tests/Feature/Inbound/SweepUndispatchedTest.php` - new; window, idempotence, backfill, scheduler entry
- `tests/Feature/Inbound/ContactMatchingTest.php`, `DispatchInboundTest.php` - matched_by and form-lead stop cases
- `docs/sprints/sprint-1.md` - scheduler walk-through line

## Decisions Made
- `ProcessInbound::handle()` now returns a bool so the form-lead stop can be asserted today; plan 02-14 replaces the `return true` with the front desk run.
- Only the scheduler walk-through line was added: the plan also listed debounce, conditional-update claim and data-migration lines, but plan 02-08 already wrote those, so repeating them would duplicate the log. The acceptance criteria (`DebounceFor`, `schedule:run`) are met.
- The sweep does not filter by channel; the single D-41 rule lives in `ProcessInbound`.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] ProcessInbound::handle() return type changed from void to bool**
- **Found during:** Task 1 RED
- **Issue:** The behavior "ProcessInbound returns before anything that could text the customer" had no observable effect to assert (the front desk run does not exist until 02-14), so the test could not distinguish a stop from a pass-through.
- **Fix:** `handle()` returns true when the burst reaches the front desk, false when it stops.
- **Files modified:** app/Jobs/ProcessInbound.php, tests/Feature/Inbound/DispatchInboundTest.php
- **Verification:** both new DispatchInboundTest cases pass; existing tests unaffected.
- **Committed in:** 35a47e8

---

**Total deviations:** 1 auto-fixed (1 blocking). **Impact on plan:** No scope change; queue workers ignore a job's return value.

## Issues Encountered
None. Research flagged assumption A7 (SQLITE_BUSY with three writers); it did not appear, so `config/database.php` is unchanged.

## Known Stubs
None. `ProcessInbound::handle()` returning true is the documented hook point for plan 02-14, not a stub that blocks this plan's goal.

## User Setup Required
None for this plan. Production needs one cron line running `php artisan schedule:run` every minute; that arrives with the first deploy (noted in sprint-1.md).

## Next Phase Readiness
Ready for plan 02-10. The remaining front desk plans run inside `ProcessInbound` after the SMS check.

## Self-Check: PASSED
- Created files exist: app/Jobs/SweepUndispatched.php, tests/Feature/Inbound/SweepUndispatchedTest.php
- Commits d22cdb4 and 35a47e8 are ancestors of HEAD; `git rev-list --count 6f808cd..HEAD` = 2.
- Acceptance criteria re-run: console.php has `Schedule::job(new SweepUndispatched)` and `everyFiveMinutes()`; job has both constants; AppServiceProvider has `schedule:work`; ContactMatchingTest has `matched_by`; sprint-1 log has `DebounceFor` and `schedule:run`.
- `php artisan test --compact tests/Feature/Inbound` 106/106; full suite 372/372; `php artisan schedule:list` shows `*/5 * * * * App\Jobs\SweepUndispatched`.

---
*Phase: 02-text-front-desk*
*Completed: 2026-10-12*
