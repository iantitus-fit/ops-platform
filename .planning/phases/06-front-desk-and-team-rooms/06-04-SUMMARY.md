---
phase: 06-front-desk-and-team-rooms
plan: 04
subsystem: alerts
tags: [laravel, owner-alerts, atomic-claim, conditional-update, scheduler, race-conditions]

requires:
  - phase: 06-front-desk-and-team-rooms
    provides: "The team room (06-03) that owns the owner_alerts table; both room wraps green on Postgres before any ALRT change"
provides:
  - "owner_alerts.send_claimed_at (nullable timestamp) and SendOwnerAlert::CLAIM_WAIT_MINUTES (2)"
  - "SendOwnerAlert claimToSend() and end(): one conditional UPDATE claims the alert right before TextChannel::send, and every outcome write lands only while sent_at, skipped_at and failed_at are all null"
  - "SendOwnerAlert::skip() as one conditional update that also waits out a fresh claim"
  - "SendDueAlerts sweep that leaves fresh claims alone and re-sends stale ones"
  - "OwnerAlertFactory claimed() and staleClaim() states"
  - "tests/Feature/Alerts/AlertSentOnceTest.php: simulated-race tests (15 cases)"
affects: [06-05 alert de-duplication key, 06-06, 06-07, phase-11 phone design pass]

plan_head_before: b37de7b30d0f729954a500f8e35d6530d08c1dd5
plan_head_after: a2418a7e9c89a832b7ecef50a568d31dbf67f6e5

actuals:
  tokens: 6400
  tasks: 2
  commits: 4

tech-stack:
  added: []
  patterns:
    - "Claim by conditional UPDATE: the affected-row count decides who acts, with a time limit so a dead worker's claim is released (WR-03, D-06, D-07)"
    - "Conditional outcome write: end() changes the row only while every outcome column is null and the side effect (timeline entry) follows only when it did"
    - "Races simulated with stale in-memory copies and a re-entrant TextChannel subclass, no two-connection test (D-21)"

key-files:
  created:
    - database/migrations/2026_10_27_000001_add_send_claimed_at_to_owner_alerts_table.php
    - tests/Feature/Alerts/AlertSentOnceTest.php
  modified:
    - app/Actions/SendOwnerAlert.php
    - app/Jobs/SendDueAlerts.php
    - app/Models/OwnerAlert.php
    - database/factories/OwnerAlertFactory.php
    - docs/sprints/sprint-1.1.md
    - docs/BLUEPRINT.md

key-decisions:
  - "The claim is taken immediately before TextChannel::send and after the blank-phone check, so a held alert has send_claimed_at null until it is sent and a no-phone alert never takes a claim (D-07)"
  - "claimToSend() and end() catch Throwable, report() it and return false, so handle() and skip() still never throw (02 D-19)"
  - "A database error in end() after a successful send skips the timeline entry; the entry is written only by the copy that provably ended the alert"
  - "The existing architecture test is run unchanged as D-09's proof; no rule added or tightened (D-19)"

requirements-completed: [ALRT-01]

coverage:
  - id: D1
    description: "Two stale copies of one alert, or a second worker that reaches it mid-send, text the owner once, with one outcome write and one timeline entry"
    requirement: ALRT-01
    verification:
      - kind: unit
        ref: "tests/Feature/Alerts/AlertSentOnceTest.php#texts once when two stale copies of one alert are both sent"
        status: pass
      - kind: unit
        ref: "tests/Feature/Alerts/AlertSentOnceTest.php#texts once when a second worker reaches the alert while the first is still sending"
        status: pass
      - kind: unit
        ref: "tests/Feature/Alerts/AlertSentOnceTest.php#sends nothing for a copy whose row has already ended"
        status: pass
    human_judgment: false
  - id: D2
    description: "A claim is taken right before the send, never for a blank phone or a held alert; a fresh claim is left alone and a stale one is texted once"
    requirement: ALRT-01
    verification:
      - kind: unit
        ref: "tests/Feature/Alerts/AlertSentOnceTest.php#claims the alert right before the text goes out"
        status: pass
      - kind: unit
        ref: "tests/Feature/Alerts/AlertSentOnceTest.php#does not claim a held alert until it falls due"
        status: pass
      - kind: unit
        ref: "tests/Feature/Alerts/AlertSentOnceTest.php#leaves an alert alone while someone else holds a fresh claim on it"
        status: pass
    human_judgment: false
  - id: D3
    description: "A dead sender's alert is re-sent by the sweep at exactly 120 seconds (119 leaves it alone), oldest first, and skip() never ends an alert being sent"
    requirement: ALRT-01
    verification:
      - kind: unit
        ref: "tests/Feature/Alerts/AlertSentOnceTest.php#leaves a fresh claim alone at 119 seconds and sends the alert once at exactly 120"
        status: pass
      - kind: unit
        ref: "tests/Feature/Alerts/AlertSentOnceTest.php#neither skips nor texts an alert whose draft was handled while its text is going out"
        status: pass
      - kind: unit
        ref: "tests/Feature/Alerts/AlertSentOnceTest.php#does not end an alert as skipped while someone holds a fresh claim on it"
        status: pass
    human_judgment: false
  - id: D4
    description: "The same behavior holds on Postgres 17 (affected-row count and the frozen-clock comparison)"
    requirement: ALRT-01
    verification: []
    human_judgment: true
    rationale: "Carried hand check: the CI pgsql leg needs the phase branch pushed by Ian; local Docker was not running. Recorded in WINDOWS.md as unrun-verify for end-of-phase UAT."

duration: 7min
completed: 2026-10-28
status: complete
---

# Phase 6 Plan 04: Owner alerts texted exactly once Summary

**Each owner alert is claimed by one conditional UPDATE right before its text, outcome writes land only on an alert still open, and a claim with no outcome is released after 2 minutes for the every-minute sweep to re-send.**

## Performance

- **Duration:** 7 min
- **Started:** 2026-10-28T13:38:21Z
- **Completed:** 2026-10-28T13:45Z
- **Tasks:** 2 (a tracer and an auto TDD task)
- **Files modified:** 8 (2 created, 6 modified)

## Accomplishments

- WR-03 closed: two stale copies of one alert, and a second worker reaching the alert while the first is still inside `TextChannel::send`, now text the owner once and write one outcome and one `owner_alerted` entry.
- D-06/D-07: the claim is taken only right before the send (after the blank-phone check), and a claim at least `CLAIM_WAIT_MINUTES` (2) old is released. `SendDueAlerts` loads only unended due alerts whose claim is null or stale, so a dead worker's alert goes out about 3 minutes late at worst.
- Pitfall 8: `skip()` is a conditional update that waits out a fresh claim, so a handled draft never ends an alert whose text is going out; it also never overwrites an alert another copy already ended.
- D-08: business-wide alerts from `RaiseOwnerAlert::forBusiness()` use the same claim. The in-memory alert carries its outcome after a send, so `SafetyCheckStatus::claimThenSend()` still reads `sent_at` correctly.
- Tracer gate: the Task 1 `<verify>` (alerts tests) was green end to end before Task 2 expanded; no human gate on the tracer.

## TDD Evidence

**RED (Task 1, `cc0c71a`):** `AlertSentOnceTest.php` with 8 cases. Real Pest output on the old code: 5 failed on the planned assertions (two stale copies: `actual size 2 matches expected size 1`; mid-send second worker: same; ended-copy: `an array is empty` failed because it texted; claim set: `Expecting null not to be null`; deleted row: it texted), 2 errored on the missing `claimed()` and `staleClaim()` factory states (part of GREEN), 1 passed (the blank-phone guard, which was already true). Semantic assessment: the target tests executed and failed on the WR-03 duplicate-send behavior itself, not on syntax, import or fixture faults. The first draft of the mid-send test recursed forever on the old code (no claim to stop the re-entrant send); fixed by making the hook run once, before commit. The Pest output is not one of the formats `gsd_run check tdd-red-evidence` reads (TAP, Surefire JUnit, swift-testing, unittest), so the machine classifier was not run; the evidence is the unmodified Pest failure messages above.

**GREEN (Task 1, `4918ef5`):** migration, model cast, factory states, `claimToSend()`, `end()`. 41 tests across the three alert files green.

**RED (Task 2, `750b3db`):** 7 more cases (15 total). 3 failed on the planned assertion (`skip()` ended a claimed alert, in the sweep and directly, and overwrote an alert another copy had ended); the 119/120-second, ordering, held-alert and business-alert cases already passed because Task 1's claim enforces the same line.

**GREEN (Task 2, `a2418a7`):** sweep clause and conditional `skip()`. 157 tests green across alerts, architecture and send-rule; full suite 2230 passed.

**REFACTOR:** none needed.

## Task Commits

1. **Task 1: Tracer: claim an alert right before the text** - `cc0c71a` (test), `4918ef5` (feat, carries the BLUEPRINT Built bullet and the walk-through line)
2. **Task 2: Sweep re-sends a dead sender's alert; skip() waits for a send in flight** - `750b3db` (test), `a2418a7` (feat, extends the BLUEPRINT bullet)

**Plan metadata:** recorded in the docs commit that follows this file (docs: complete plan).

## Files Created/Modified

- `database/migrations/2026_10_27_000001_add_send_claimed_at_to_owner_alerts_table.php` - nullable `send_claimed_at` with a drop in `down()`
- `app/Actions/SendOwnerAlert.php` - `CLAIM_WAIT_MINUTES`, `claimToSend()`, `end()`, conditional `skip()`
- `app/Jobs/SendDueAlerts.php` - stale-claim clause on the sweep query; `lazyById()` kept
- `app/Models/OwnerAlert.php` - `send_claimed_at` datetime cast, not fillable
- `database/factories/OwnerAlertFactory.php` - `send_claimed_at` null default, `claimed()`, `staleClaim()`
- `tests/Feature/Alerts/AlertSentOnceTest.php` - simulated-race tests with file-local helpers
- `docs/sprints/sprint-1.1.md` - walk-through line (plan 06-04)
- `docs/BLUEPRINT.md` - section 6 Built bullet (Affects: team, safety and compliance)

## Decisions Made

- A failed claim query is treated as "not claimed" and a failed outcome write as "not ended": both are `report()`ed and `handle()` returns, keeping the never-throws promise. The cost is that if the outcome write itself errors after a successful send, no timeline entry is written for that send; the claim then goes stale and the sweep may re-send it, which is the one duplicate window the plan already accepts.
- Only the claim time limit, not the architecture test, was touched for D-09/D-19: `tests/Unit/ArchitectureTest.php` is unchanged from main and passes.

## Deviations from Plan

None - plan executed exactly as written. (The mid-send test's first draft recursed on the pre-fix code; that was a test-authoring fix made before the RED commit, not a deviation from the plan.)

## Teaching note (Laravel concepts)

Added to the walk-through log in `docs/sprints/sprint-1.1.md`: grouping an OR with a `where(fn ...)` closure, and using `update()`'s returned row count as the "who won" signal.

## Issues Encountered

- A local `php artisan test` of the whole suite takes about 150 seconds and ran past the tool timeout once; it was run in the background and finished green.

## Known Stubs

None.

## Threat Flags

None. The new column and conditional updates sit inside the plan's threat register (T-6-07, T-6-08, T-6-09); no new endpoint, auth path or trust boundary.

## Unrun hand check (carried to UAT, not a pause)

Postgres leg: after Ian pushes the phase branch, the CI pgsql job should run `tests/Feature/Alerts/AlertSentOnceTest.php` green (the conditional update's affected-row count and the frozen-clock 119/120 comparison on Postgres 17). A local run needs Docker (it was not running here): `docker run -d --rm --name ops-pg -e POSTGRES_PASSWORD=postgres -e POSTGRES_DB=ops_test -p 55432:5432 postgres:17`, then `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=ops_test DB_USERNAME=postgres DB_PASSWORD=postgres php artisan test --compact tests/Feature/Alerts`, then `docker stop ops-pg`. Recorded as an `unrun-verify` entry in `.planning/WINDOWS.md`.

## Next Phase Readiness

Ready for 06-05 (the unique de-duplication key, success criterion 4's other half). The send claim and the outcome writes are in place; 06-05 changes how alerts are raised, not how they are sent.

## Self-Check: PASSED

- Files exist: migration, `AlertSentOnceTest.php`, `SendOwnerAlert.php`, `SendDueAlerts.php`, `OwnerAlertFactory.php` (checked).
- Commits `cc0c71a`, `4918ef5`, `750b3db`, `a2418a7` are on the branch; `git rev-list --count b37de7b..HEAD` is 4.
- Acceptance greps for both tasks pass; `tests/Unit/ArchitectureTest.php` and the three existing alert test files are unchanged from main.
- `php artisan test --compact`: 2230 passed.

---
*Phase: 06-front-desk-and-team-rooms*
*Completed: 2026-10-28*
