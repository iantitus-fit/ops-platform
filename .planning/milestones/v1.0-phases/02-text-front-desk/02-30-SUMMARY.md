---
phase: 02-text-front-desk
plan: 30
subsystem: testing
tags: [pest, phpunit, config-pin, twilio, jev, test-isolation]

requires:
  - phase: 02-text-front-desk
    provides: TEXT_CHANNEL driver switch, Twilio and Jev config keys, TestSafetyNetTest
provides:
  - Feature tests run on the fake text driver with blank Twilio and Jev keys whatever .env or the shell says
  - phpunit.xml forces the same values for the whole suite and the app's own boot
  - Child-process guard test that fails when no layer pins the test environment
  - Guard that config/services.php still defaults JEV_MODEL to typesafe/jev-1.13
affects: [02-31, 02-32, 02-33, 02-34, go-live setup]

plan_head_before: c7f13f1d63be1312eedc3fbecb1d8bda559f39e5
plan_head_after: f2eca7a99d46d9af2f270a9463b259f33f9430b9

actuals:
  tokens: 4500
  tasks: 2
  commits: 3

tech-stack:
  added: []
  patterns:
    - "Two-layer test environment pin: phpunit.xml env+server lines, then config([...]) in the shared Feature beforeEach"
    - "Hostile-environment guard: Process::env([...])->run() starts a child Pest run and reads a --log-junit file"

key-files:
  created: []
  modified:
    - tests/Pest.php
    - tests/Feature/Quality/TestSafetyNetTest.php
    - phpunit.xml
    - docs/sprints/sprint-1.md

key-decisions:
  - "phpunit.xml carries <server> lines next to the forced <env> lines, because Laravel's env() reads $_SERVER before $_ENV and PHPUnit 13 does not touch $_SERVER for <env>"
  - "The beforeEach config([...]) pin stays as the second layer; it also beats a cached config"

patterns-established:
  - "A test that needs a Twilio or Jev value sets it itself with config([...]) after the shared pin"

requirements-completed: [INFRA-01]

coverage:
  - id: D1
    description: "Every Feature test sees channels.text.driver fake, null Twilio and Jev keys, and the pinned Jev model and base URL"
    requirement: INFRA-01
    verification:
      - kind: unit
        ref: "tests/Feature/Quality/TestSafetyNetTest.php#runs every Feature test on the fake text driver with no live Twilio or Jev keys"
        status: pass
    human_judgment: false
  - id: D2
    description: "A hostile environment (twilio driver, live-looking keys) cannot reach the suite; a child Pest run proves it"
    requirement: INFRA-01
    verification:
      - kind: integration
        ref: "tests/Feature/Quality/TestSafetyNetTest.php#keeps the fake driver and blank keys when the environment says twilio and holds live-looking keys"
        status: pass
    human_judgment: false
  - id: D3
    description: "config/services.php keeps typesafe/jev-1.13 as the JEV_MODEL default"
    verification:
      - kind: unit
        ref: "tests/Feature/Quality/TestSafetyNetTest.php#keeps typesafe/jev-1.13 as the Jev model default in config/services.php"
        status: pass
    human_judgment: false
  - id: D4
    description: "Full suite (934 tests) passes both clean and with the go-live settings from docs/SETUP.md section 11 exported"
    requirement: INFRA-01
    verification:
      - kind: other
        ref: "php artisan test --compact (clean) and the same command with TEXT_CHANNEL=twilio and live-looking TWILIO_*/JEV_* exported"
        status: pass
    human_judgment: false

duration: 20min
completed: 2026-10-12
status: complete
---

# Phase 2 Plan 30: Pinned Test Environment Summary

**Test suite pinned to the fake text driver and blank Twilio/Jev keys in two layers (phpunit.xml env+server lines and the shared Feature beforeEach), proven by a child-process Pest run under a hostile environment (WR-16).**

## Performance

- **Duration:** about 20 min for the continuation (the first executor's RED work is not timed here)
- **Completed:** 2026-10-12T17:32:29Z
- **Tasks:** 2
- **Files modified:** 4

## Accomplishments

- `tests/Pest.php`: the shared Feature `beforeEach` sets `channels.text.driver` to `fake`, the five `services.twilio.*` keys and `services.jev.key` to null, `services.jev.model` to `typesafe/jev-1.13` and `services.jev.base_url` to the OpenRouter URL, after the app boots.
- `phpunit.xml`: forced `<env>` lines (plus matching `<server>` lines) for `TEXT_CHANNEL`, `TWILIO_*`, `JEV_API_KEY`, `JEV_MODEL`, `JEV_BASE_URL`. `DB_*` is not forced, so CI can still pick Postgres.
- `tests/Feature/Quality/TestSafetyNetTest.php`: three new tests (in-suite pin assertion, hostile-environment child run reading a `--log-junit` file, and a file-text guard on the `JEV_MODEL` default).
- `docs/sprints/sprint-1.md`: one walk-through log line "(plan 02-30)".

## TDD Gate Compliance

- **RED** (`589229e`): the hostile-environment guard failed on the target assertion "expected 'fake', got 'twilio'". `gsd_run check tdd-red-evidence` returned `RED_EVIDENCE_OK` (recorded by the earlier executor). Semantic assessment: the target test executed and failed on the planned assertion for the intended reason (driver not pinned), not on a load or setup fault.
- **GREEN** (`cbe36a3`): the `beforeEach` pin; narrow file 6/6, hostile-shell run of three files 59/59, full suite 934/934.
- **REFACTOR:** none needed.

## Task Commits

1. **Task 1: tracer, hostile environment cannot reach a Feature test** - `589229e` (test, RED), `cbe36a3` (feat, GREEN)
2. **Task 2: phpunit.xml pin, walk-through log, production-shaped full run** - `f2eca7a` (chore)

**Plan metadata:** recorded in the docs commit that follows this file.

## Verification

- `php artisan test --compact tests/Feature/Quality/TestSafetyNetTest.php`: 6 passed.
- Hostile shell run of FakeTextWebhookTest, JevClassifierTest and TestSafetyNetTest: 59 passed.
- `php artisan test --compact`: 934 passed (SQLite).
- Same full run with `TEXT_CHANNEL=twilio`, all five `TWILIO_*` and `JEV_*` set to live-looking values: 934 passed.
- Mutation check (pin removed from `tests/Pest.php` locally, not committed, then restored): with the first `<env>`-only version of phpunit.xml the guard FAILED (see Deviations); after adding the `<server>` lines it passed both with a clean shell and with `TEXT_CHANNEL=twilio TWILIO_AUTH_TOKEN=live-token JEV_API_KEY=live-key JEV_MODEL=typesafe/jev-latest` exported. So phpunit.xml alone holds the line, and the `beforeEach` is a true second layer.
- Not run: the Postgres leg (no Postgres CI run available here); both layers are driver-independent and `DB_*` is untouched.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Forced `<env force="true">` alone does not beat a shell export in this stack**
- **Found during:** Task 2 (mutation check)
- **Issue:** With the `beforeEach` pin removed, the guard still saw `twilio`. PHPUnit 13's `<env>` handler sets `putenv` and `$_ENV` but never `$_SERVER`; Laravel's `env()` reads `$_SERVER` first, and a shell export lands in `$_SERVER`. A probe printed `$_ENV=fake`, `$_SERVER=twilio`, `env()=twilio`. The plan's claim that a forced `<env>` line beats the real environment does not hold here.
- **Fix:** Added a matching `<server name=... value=.../>` line for each of the nine variables. `<server>` overwrites `$_SERVER` unconditionally. The forced `<env>` lines stay (they are in the acceptance criteria and still cover `getenv`/`$_ENV`). The walk-through log line mentions this.
- **Files modified:** phpunit.xml, docs/sprints/sprint-1.md
- **Verification:** mutation check above; full suite clean and production-shaped.
- **Committed in:** f2eca7a

---

**Total deviations:** 1 auto-fixed (1 bug in the plan's premise)
**Impact on plan:** Needed for the plan's own "phpunit.xml layer holds alone" truth. No scope creep: same file, same nine variables.

## Issues Encountered

- The earlier executor stopped on `php artisan test` permission denials; the user added allow rules and every verify command ran without denial in this continuation.
- Reading `.env` is blocked by a secret-read hook; not needed, the probe printed only `TEXT_CHANNEL` values.

## Known Stubs

None.

## Threat Flags

None. Mitigations T-02-64 and T-02-65 are in place; no new endpoints or trust-boundary surface.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

Later gap plans (02-31 to 02-34) run on a pinned environment. `docs/SETUP.md` section 11 can set `TEXT_CHANNEL=twilio` and the Twilio keys in `.env` without turning the suite red.

## Self-Check: PASSED

- FOUND: tests/Pest.php, tests/Feature/Quality/TestSafetyNetTest.php, phpunit.xml, docs/sprints/sprint-1.md
- FOUND commits: 589229e, cbe36a3, f2eca7a (all ancestors of HEAD)
- `.planning/tmp-red/` deleted; `.planning/milestone.lock` untouched.

---
*Phase: 02-text-front-desk*
*Completed: 2026-10-12*
