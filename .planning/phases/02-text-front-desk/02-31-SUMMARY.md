---
phase: 02-text-front-desk
plan: 31
subsystem: gap-closure
tags: [filament, owner-alert, dispatch, queue, phase1-compat, cr-02, wr-02]

requires:
  - phase: 02-text-front-desk
    provides: pinned test environment (02-30), RaiseOwnerAlert, DispatchInbound, BusinessFolder page
provides:
  - Business Folder page and Sync Now open while a Phase 1 version (free-text owner_alert) is live
  - RaiseOwnerAlert reads owner_alert through one tolerant reader; an unreadable setting sends the alert now
  - DispatchInbound gives dispatched_at back and rethrows when the queue push fails
affects: [02-34, 02.1]

plan_head_before: 217ca9609e74dd61e3fbb6605ade29e12df5eb31
plan_head_after: e1bfa5a2e9ba1e50ac10a7a11ea3695b862da92c

actuals:
  tokens: 6500
  tasks: 3
  commits: 6

tech-stack:
  added: []
  patterns:
    - "Readers (not the Validator) tolerate an older stored shape: is_array guard, a non-array owner_alert counts as text / urgent"
    - "Release-and-rethrow around a PendingDispatch: the push runs when the temporary is destroyed at the end of the statement, inside the try"

key-files:
  created: []
  modified:
    - app/Filament/Pages/BusinessFolder.php
    - app/Actions/RaiseOwnerAlert.php
    - app/Actions/DispatchInbound.php
    - tests/Feature/Filament/BusinessFolderPageTest.php
    - tests/Feature/Alerts/OwnerAlertTimingTest.php
    - tests/Feature/Inbound/DispatchInboundTest.php
    - docs/sprints/sprint-1.md

key-decisions:
  - "A non-array owner_alert on the dashboard counts as the text channel, because every alert goes by text (D-18)"
  - "An owner_alert RaiseOwnerAlert cannot read (Phase 1 string, or no live version) is urgent and due now: an alert may go out sooner, never later (D-19); reversible in a later plan"
  - "A failed ProcessInbound push releases the claim and rethrows rather than adding a staleness sweep"

requirements-completed: [GATE-01, AGNT-02]

coverage:
  - id: D1
    description: "Business Folder page and Sync Now work while a Phase 1 version is live; the no-phone warning still appears and clears"
    requirement: GATE-01
    verification:
      - kind: unit
        ref: "tests/Feature/Filament/BusinessFolderPageTest.php#opens the page and Sync Now while a Phase 1 version is live"
        status: pass
      - kind: unit
        ref: "tests/Feature/Filament/BusinessFolderPageTest.php#syncs forward from the dashboard while a Phase 1 version is live"
        status: pass
      - kind: unit
        ref: "tests/Feature/Filament/BusinessFolderPageTest.php#warns about a missing phone while a Phase 1 version is live, and not once a phone is on file"
        status: pass
    human_judgment: false
  - id: D2
    description: "RaiseOwnerAlert never throws on an unreadable owner_alert; the alert is urgent and sent at once, even after hours; Phase 2 behaviour unchanged"
    requirement: GATE-01
    verification:
      - kind: unit
        ref: "tests/Feature/Alerts/OwnerAlertTimingTest.php#sends at once, after hours, when the live version owner_alert is the Phase 1 free text"
        status: pass
      - kind: unit
        ref: "tests/Feature/Alerts/OwnerAlertTimingTest.php#sends at once and does not throw when no folder version is live"
        status: pass
    human_judgment: false
  - id: D3
    description: "A failed queue push clears the dispatch claim and rethrows; the sweep and the provider retry each queue the text once"
    requirement: AGNT-02
    verification:
      - kind: unit
        ref: "tests/Feature/Inbound/DispatchInboundTest.php#clears the claim and rethrows when the queue push fails, so the text stays sweepable"
        status: pass
      - kind: unit
        ref: "tests/Feature/Inbound/DispatchInboundTest.php#queues a text whose push failed once the sweep runs"
        status: pass
      - kind: integration
        ref: "tests/Feature/Inbound/DispatchInboundTest.php#queues a text whose push failed when the provider retries it"
        status: pass
    human_judgment: false

duration: 20min
completed: 2026-10-12
status: complete
---

# Phase 2 Plan 31: Old Folders and Failed Pushes Summary

**Business Folder, Sync Now and owner alerts tolerate a Phase 1 free-text `owner_alert`, and `DispatchInbound` gives its claim back and rethrows when the queue push fails, so the sweep or the provider retry queues the text (CR-02, WR-02).**

## Performance

- **Duration:** about 20 min
- **Tasks:** 3 (1 tracer, 2 auto, all TDD)
- **Files modified:** 7 (3 app, 3 test, 1 doc)

## Accomplishments

- `BusinessFolder::alertWarning()` reads `owner_alert` once, takes `channels` when it is an array and `['text']` when it is not, so the page and Sync Now answer 200 on the local v1 database and the D-19 no-phone warning still shows and clears.
- `RaiseOwnerAlert::ownerAlertSetting(): ?array` (private) returns the array or null when the value is not an array or `NoActiveVersion` is thrown. `isUrgent()` treats null as urgent and `dueAt()` reads `after_hours` through the same reader, so an unreadable setting sends the alert now. The Validator is unchanged; `alreadyRaised()` and the send path are untouched (WR-03/WR-04 stay in Phase 2.1).
- `DispatchInbound::handle()` wraps the single `ProcessInbound::dispatch(...)->afterCommit();` statement in try/catch; on any `Throwable` it sets `dispatched_at` back to null with `whereKey($message->id)->update(...)` and rethrows. The conditional claim is unchanged, so two racing callers still queue once.
- Walk-through log line "(plan 02-31)" in `docs/sprints/sprint-1.md` explains when a `PendingDispatch` actually pushes.

## TDD Gate Compliance

- **RED** (`d300b42`, `2c47766`, `8dfed8e`): each target failed on the planned fault. Business Folder: `TypeError: Cannot access offset of type string on string` at `BusinessFolder.php:132`. Alerts: the same TypeError and `NoActiveVersion` at `RaiseOwnerAlert.php:88`. Dispatch: `dispatched_at` still set after the failed push (the rethrow already happened, only the release was missing). `gsd_run check tdd-red-evidence` returned `RED_EVIDENCE_OK` / `target_test_failed` for the first target of every task (JUnit XML from `--log-junit`, target named `it <test name>`). Semantic assessment: target executed and failed on the intended assertion or exception, not on a setup fault.
- **GREEN** (`d2a6b0d`, `1b77c6e`, `e1bfa5a`): narrow files pass, then the full suite.
- **REFACTOR:** none needed.

## Task Commits

1. **Task 1 (tracer): Business Folder with a Phase 1 version** - `d300b42` (test), `d2a6b0d` (fix)
2. **Task 2: RaiseOwnerAlert reads owner_alert safely** - `2c47766` (test), `1b77c6e` (fix)
3. **Task 3: failed push leaves the text sweepable, walk-through log** - `8dfed8e` (test), `e1bfa5a` (fix)

The tracer's verify (`tests/Feature/Filament`, 70 tests) passed end to end before Task 2 started.

## Verification

- `php artisan test --compact tests/Feature/Filament/BusinessFolderPageTest.php`: passed (24 tests, 3 new).
- `php artisan test --compact tests/Feature/Alerts tests/Feature/Filament/BusinessFolderPageTest.php`: 70 passed.
- `php artisan test --compact tests/Feature/Inbound`: 145 passed.
- `php artisan test --compact`: 942 passed (934 before + 8 new), SQLite.
- Acceptance greps: `is_array(` present and `['owner_alert']['channels']`, `['owner_alert']['right_away']`, `['owner_alert']['after_hours']` absent; `function ownerAlertSetting` and `catch (NoActiveVersion)` present; `catch (Throwable $exception)`, `'dispatched_at' => null`, `throw $exception;`, `->whereNull('dispatched_at')` and the `ProcessInbound::dispatch(...)->afterCommit();` line present; `(plan 02-31)` appears once.
- Not run: the optional manual check against the local v1 database (Ian's).

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## Known Stubs

None.

## Threat Flags

None. T-02-66, T-02-67 and T-02-68 are mitigated; no new endpoints, auth paths or schema.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

`map/` was left alone as planned: 02-34 re-points the citations into `RaiseOwnerAlert.php`, `BusinessFolder.php` and `DispatchInbound.php` and adds the give-the-claim-back clause to `answer-inbound.md` step 3. WR-03, WR-04 and WR-13 remain open for Phase 2.1.

## Self-Check: PASSED

- FOUND: app/Filament/Pages/BusinessFolder.php, app/Actions/RaiseOwnerAlert.php, app/Actions/DispatchInbound.php, the three test files, docs/sprints/sprint-1.md
- FOUND commits (ancestors of HEAD): d300b42, d2a6b0d, 2c47766, 1b77c6e, 8dfed8e, e1bfa5a
- `.planning/tmp-red/` deleted; `.planning/milestone.lock` untouched.

---
*Phase: 02-text-front-desk*
*Completed: 2026-10-12*
