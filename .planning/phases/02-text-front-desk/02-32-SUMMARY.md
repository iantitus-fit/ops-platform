---
phase: 02-text-front-desk
plan: 32
subsystem: gap-closure
tags: [queue, owner-alert, front-desk, timeout, failed-hook, cr-01, wr-01, agnt-04]

requires:
  - phase: 02-text-front-desk
    provides: RaiseOwnerAlert safe with no live version or a Phase 1 owner_alert (02-31), ProcessInbound burst claim, RunFrontDesk, RunFrontDeskAgent provider chain
provides:
  - ReportFrontDeskFailure, the one door for "the front desk could not answer" (timeline note plus front_desk_failed alert, once per text)
  - ProcessInbound that never throws on a front desk error and has tries 1, timeout 300, failOnTimeout and a failed() hook
  - A time budget (gate.agent.chain_budget_seconds, 150) that cuts the tail of the provider chain
  - Queue retry_after defaults of 360, above the job timeout
  - An owner alert instead of a silent drop when the live folder has no sms agent
affects: [02-33, 02-34, 02.1]

plan_head_before: 873adc0685c375d4defedcdd896ce9f413ab9741
plan_head_after: d00891768ee991d307867fcd1ec5c86474af7910

actuals:
  tokens: 6900
  tasks: 3
  commits: 6

tech-stack:
  added: []
  patterns:
    - "One door per owner-facing failure: callers pass fixed words, the door is idempotent per text (an existing front_desk_failed alert for the message means already reported)"
    - "A claimed burst is never released after a crash or a timeout, so an interrupted run cannot lead to a second reply"
    - "Exception text goes to the log through report(), never into a note or an alert"
    - "Time budget skips are recorded as attempts ('skipped: not enough time left in this run') so the owner-facing failure path is the same as all-models-failed"

key-files:
  created:
    - app/Actions/ReportFrontDeskFailure.php
  modified:
    - app/Jobs/ProcessInbound.php
    - app/Actions/RunFrontDesk.php
    - app/Ai/RunFrontDeskAgent.php
    - config/gate.php
    - config/queue.php
    - tests/Feature/FrontDesk/FrontDeskFailureTest.php
    - tests/Feature/FrontDesk/AgentFallbackTest.php
    - docs/sprints/sprint-1.md

key-decisions:
  - "ProcessInbound catches Throwable after NoActiveVersion, calls report(), and reports through ReportFrontDeskFailure; the burst stays claimed"
  - "failed() finds the text by conversation (newest inbound customer message), not by messageId (IN-05 left alone)"
  - "Chain budget is 150 s and job timeout 300 s; retry_after 360 s sits above the timeout; the first provider always runs"
  - "A live folder with no sms agent alerts the owner; zero agents stays legal and the Validator is unchanged"

requirements-completed: [AGNT-04, AGNT-02]

coverage:
  - id: D1
    description: "An unexpected error inside a front desk run ends as one timeline note and one owner alert in fixed words, with nothing texted to the customer and the job not throwing"
    requirement: AGNT-04
    verification:
      - kind: unit
        ref: "tests/Feature/FrontDesk/FrontDeskFailureTest.php#tells the owner and writes a note when something unexpected breaks the run, and texts the customer nothing (CR-01)"
        status: pass
    human_judgment: false
  - id: D2
    description: "failed() tells the owner once when the worker stops a claimed run, skips a text already reported, and ignores a missing conversation"
    requirement: AGNT-04
    verification:
      - kind: unit
        ref: "tests/Feature/FrontDesk/FrontDeskFailureTest.php#tells the owner when the worker stops the run before it finishes"
        status: pass
      - kind: unit
        ref: "tests/Feature/FrontDesk/FrontDeskFailureTest.php#reports a crash once even when failed() runs after the job already reported it"
        status: pass
      - kind: unit
        ref: "tests/Feature/FrontDesk/FrontDeskFailureTest.php#does nothing in failed() for a conversation that no longer exists"
        status: pass
    human_judgment: false
  - id: D3
    description: "ProcessInbound limits (tries 1, timeout 300, failOnTimeout) sit above the chain budget plus Jev plus 120 s, and queue retry_after (360) sits above the job timeout"
    requirement: AGNT-04
    verification:
      - kind: unit
        ref: "tests/Feature/FrontDesk/FrontDeskFailureTest.php#gives the job more time than the slowest front desk run, and the queue more time than the job"
        status: pass
      - kind: other
        ref: "php artisan config:show queue.connections.database.retry_after (360)"
        status: pass
    human_judgment: false
  - id: D4
    description: "The provider chain starts a fallback only when it fits the time budget, in file order, recording skipped entries"
    requirement: AGNT-02
    verification:
      - kind: unit
        ref: "tests/Feature/FrontDesk/AgentFallbackTest.php#stops trying fallbacks once the next one would not finish inside the time budget"
        status: pass
      - kind: unit
        ref: "tests/Feature/FrontDesk/AgentFallbackTest.php#keeps the file order and tries every entry that fits inside the time budget"
        status: pass
    human_judgment: false
  - id: D5
    description: "A live folder with no sms agent (Phase 2 without agents/front-desk.md, or a live Phase 1 version) alerts the owner instead of dropping the text"
    requirement: AGNT-04
    verification:
      - kind: unit
        ref: "tests/Feature/FrontDesk/FrontDeskFailureTest.php#tells the owner when the live folder has no agent for texts (WR-01)"
        status: pass
      - kind: unit
        ref: "tests/Feature/FrontDesk/FrontDeskFailureTest.php#tells the owner, at once, when a Phase 1 version with no agent is still live"
        status: pass
    human_judgment: false
  - id: D6
    description: "In real local development (queue:listen --tries=1 --timeout=0) a run past 300 seconds is stopped by the job's own timeout and failed() alerts the owner"
    requirement: AGNT-04
    verification: []
    human_judgment: true
    rationale: "The worker's pcntl timeout alarm and Laravel's override of --timeout cannot be exercised in the test suite; plan marked this truth verification: backstop"

duration: 6min
completed: 2026-10-12
status: complete
---

# Phase 2 Plan 32: Silent-drop family around the burst claim Summary

**A customer text the front desk claims now either gets answered or reaches the owner: any throw inside a run, a worker timeout, a dead worker, a slow provider chain or a folder with no sms agent ends as one timeline note and one front_desk_failed alert in fixed words, via the new ReportFrontDeskFailure.**

## Performance

- **Duration:** 6 min
- **Started:** 2026-10-12T17:41:15Z
- **Completed:** 2026-10-12T17:47:00Z
- **Tasks:** 3
- **Files modified:** 9 (1 created, 8 modified)

## Accomplishments

- `ReportFrontDeskFailure` writes the "Front desk could not answer: ..." note and raises the `front_desk_failed` alert, and does nothing if that text already has such an alert, so a catch-all plus a later `failed()` can never double-report.
- `ProcessInbound` no longer throws on a front desk error (CR-01): `catch (Throwable)` logs through `report()` and reports in fixed words; the claim is kept so the customer cannot be texted twice. It also carries `tries 1`, `timeout 300`, `failOnTimeout true` and a `failed()` hook for timeouts and dead workers.
- `RunFrontDeskAgent` honours `gate.agent.chain_budget_seconds` (150): after the first provider, a fallback is skipped (recorded as `skipped: not enough time left in this run`) unless elapsed plus `timeout_seconds` fits. Order is never changed.
- Queue `retry_after` defaults for database and redis go from 90 to 360, above the 300 s job timeout, with a comment explaining why.
- `RunFrontDesk` (WR-01) alerts the owner "No front desk agent is set up for texts" instead of returning quietly; its all-models-failed path now uses the same door with byte-identical wording, and the private `noteThatNoModelAnswered()` is gone. The class docblock's false "never throws" sentence is rewritten.
- Walk-through log line "(plan 02-32)" added to `docs/sprints/sprint-1.md`.

## TDD Evidence

- **RED 1 (Task 1):** `aebb076`. The crash test failed because the job threw the mocked `RuntimeException` from `RecordIntakeAnswers`. Machine check: `gsd check tdd-red-evidence` returned `RED_EVIDENCE_OK` on the JUnit report. Semantic assessment: the target test executed, reached `RecordIntakeAnswers`, and failed on the missing guard, the planned gap.
- **RED 2 (Task 2):** `e1bbd60`. Seven new/updated targets failed: three `failed()` tests and the limits test (`failed()` and `$tries` did not exist) and the budget test (the fallback was still prompted). `RED_EVIDENCE_OK` for the budget and worker-stop targets. The ordering probe ("keeps the file order...") passes on old code by design, as it pins existing behaviour.
- **RED 3 (Task 3):** `058559c`. Both WR-01 tests failed with no owner alert (the bare `return`). `RED_EVIDENCE_OK` for both.
- **GREEN:** `1351b42` (Task 1), `1e2eba2` (Task 2), `d008917` (Task 3). No refactor commits were needed.

## Task Commits

1. **Task 1: tracer, an unexpected error reaches the owner** - `aebb076` (test), `1351b42` (fix)
2. **Task 2: tries, timeout, failed(), chain budget, retry_after** - `e1bbd60` (test), `1e2eba2` (fix)
3. **Task 3: no sms agent alerts the owner (WR-01)** - `058559c` (test), `d008917` (fix)

**Plan metadata:** committed separately (docs: complete plan)

Tracer gate: the Task 1 `<verify>` command was re-run end to end and passed (32 tests in the two named files, 205 in `tests/Feature/FrontDesk`) before expansion.

## Files Created/Modified

- `app/Actions/ReportFrontDeskFailure.php` - the one door for note plus alert, idempotent per text
- `app/Jobs/ProcessInbound.php` - catch-all, `$tries`, `$timeout`, `$failOnTimeout`, `failed()`
- `app/Actions/RunFrontDesk.php` - injects the new action, no-agent alert, docblock rewrite, private note method removed
- `app/Ai/RunFrontDeskAgent.php` - time budget with skipped entries
- `config/gate.php` - `agent.chain_budget_seconds = 150`
- `config/queue.php` - `retry_after` 360 for database and redis
- `tests/Feature/FrontDesk/FrontDeskFailureTest.php` - seven new tests
- `tests/Feature/FrontDesk/AgentFallbackTest.php` - two new tests (budget cut and ordering probe)
- `docs/sprints/sprint-1.md` - walk-through log line

## Decisions Made

- Catch-all returns `true` after reporting; the burst stays claimed (reversible: a later plan can release or re-queue instead).
- `failed()` resolves `ReportFrontDeskFailure` with `app()` because Laravel calls `failed()` without method injection.
- The first provider always runs; only fallbacks are budgeted.

## Deviations from Plan

### Auto-fixed Issues

None to code. Two small test-level choices worth recording:

**1. [Test choice] The crash test calls `runQueuedFrontDeskJobs()` directly instead of `expect(fn () => ...)->not->toThrow(Throwable::class)`**
- **Found during:** Task 1 (RED)
- **Issue:** In this Pest version `not->toThrow(Throwable::class)` passed even though the job threw a `RuntimeException` (verified with a throwaway debug test, since removed), so it would have hidden the RED failure and would also not prove the job stopped throwing.
- **Fix:** The new test calls the helper plainly, so any throw fails the test. The pre-existing all-models-failed test, which uses the weaker form, was left unchanged as instructed.
- **Files modified:** tests/Feature/FrontDesk/FrontDeskFailureTest.php
- **Committed in:** aebb076

**2. [Extra coverage] AgentFallbackTest gained two tests, not one**
- The second ("keeps the file order and tries every entry that fits inside the time budget") is the AGNT-04 ordering probe from the plan's must-haves; it passes on both old and new code by design.

---

**Total deviations:** 0 code deviations, 2 test-level notes
**Impact on plan:** None; scope unchanged.

## Issues Encountered

- The existing test `tells the owner, and texts the customer nothing, when every model failed` (and likely other older tests) uses `expect(fn () => ...)->not->toThrow(Throwable::class)`, which does not fail when a `RuntimeException` is thrown here. Out of scope for this plan, not changed. Candidate for a Phase 2.1 sweep.
- Full suite green on SQLite (951 passed) and on the PostgreSQL 17 recipe from 02-VALIDATION.md (951 passed).

## Known Stubs

None.

## User Setup Required

None - no external service configuration required. Developers with `DB_QUEUE_RETRY_AFTER` or `REDIS_QUEUE_RETRY_AFTER` set in `.env` must keep them above 300.

## Next Phase Readiness

- 02-33 can add the form danger alert at the web-only branch of `ProcessInbound::handle()` (left as is here).
- 02-34 must re-point every `map/` citation into `RunFrontDesk.php`, `ProcessInbound.php`, `RunFrontDeskAgent.php` and `config/gate.php`, and rewrite "no agent means it returns quietly".
- WR-03/WR-04 (alert send claim, de-duplication) remain Phase 2.1; IN-05 and IN-02 untouched.

## Self-Check: PASSED

- Created file present: `app/Actions/ReportFrontDeskFailure.php`
- Commits present on the branch: aebb076, 1351b42, e1bbd60, 1e2eba2, 058559c, d008917
- Acceptance greps and `php artisan test --compact` (951 passed) run after the last task commit.

---
*Phase: 02-text-front-desk*
*Completed: 2026-10-12*
