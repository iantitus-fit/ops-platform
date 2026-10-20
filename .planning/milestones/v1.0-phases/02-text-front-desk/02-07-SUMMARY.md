---
phase: 02-text-front-desk
plan: 07
subsystem: send-rule
tags: [jev, classifier, fail-closed, laravel-http, pest, triage, thresholds]

requires:
  - phase: 02-text-front-desk
    provides: "Classifier interface, ClassifierResult, ClassifierFailed, TriageQuestions, happy-path JevClassifier and services.jev config (plan 02-06); config/gate.php (plan 02-05)"
provides:
  - "JevClassifier that fails closed on every error, timeout, unreadable body, missing or mistyped answer"
  - "FakeClassifier bound in every Feature test, with the fakeClassifier() Pest helper"
  - "Classifier container binding to JevClassifier"
  - "Validator::triageErrors() for rules/triage.md"
  - "TriageReading: Jev's numbers read against the D-45 to D-47 thresholds"
  - "gate.classifier config and the JEV_* keys in .env.example"
affects: [02-14 send rule, 02-15 front desk, 02-12 owner screens, phase-02 verification]

plan_head_before: 2dd165389d5fa73d2b8ef6443deee79ddbf38ee1
plan_head_after: bc14d0fe4fcfe32fd2e9987fa316857a076f8995

actuals:
  tokens: 11800
  tasks: 2
  commits: 4

tech-stack:
  added: []
  patterns:
    - "Fail closed: every Jev failure becomes ClassifierFailed, chained to the original exception, never carrying the key"
    - "Interface bound to the real driver in the provider, swapped for a scripted fake in tests/Pest.php beforeEach"
    - "Value object (readonly TriageReading) built by a static read() from a result, the questions and the thresholds"

key-files:
  created:
    - app/SendRule/FakeClassifier.php
    - app/SendRule/TriageReading.php
    - tests/Feature/Icm/TriageValidationTest.php
    - tests/Unit/SendRule/TriageReadingTest.php
  modified:
    - app/SendRule/JevClassifier.php
    - app/Icm/Validator.php
    - app/Providers/AppServiceProvider.php
    - tests/Pest.php
    - tests/Feature/SendRule/JevClassifierTest.php
    - config/gate.php
    - .env.example
    - docs/sprints/sprint-1.md

key-decisions:
  - "A missing yes/no answer counts as a certain yes inside TriageReading (and a missing one is also refused by JevClassifier), so an answer that never arrived can only hold a reply"
  - "Intent confidence and noul values are accepted only as JSON numbers (int or float) from 0 to 1; numeric strings such as \"0.1\" fail the call"
  - "TriageReading lists both classifier_intent:<intent> and classifier_unsure:intent when both apply, in the plan's priority order"

patterns-established:
  - "Pest helper fakeClassifier() returns the bound FakeClassifier; a test wanting the real class calls $this->app->forgetInstance(Classifier::class)"
  - "Validator checks that a rule string cannot express live in a per-type method (triageErrors) wired into fileErrors()"

requirements-completed: [GATE-01]

coverage:
  - id: D1
    description: "Every way Jev can fail (blank key, latest model, 401/429/500/524/529, connection failure, non-JSON body, missing/mistyped/out-of-range answer, choice not among the options) throws ClassifierFailed"
    requirement: GATE-01
    verification:
      - kind: integration
        ref: "tests/Feature/SendRule/JevClassifierTest.php (error statuses, failedConnection, unusable answers, blank key, jev-latest)"
        status: pass
    human_judgment: false
  - id: D2
    description: "Answers that omit type but have the right shape are accepted; other is always sent first in the criteria"
    requirement: GATE-01
    verification:
      - kind: integration
        ref: "tests/Feature/SendRule/JevClassifierTest.php#accepts answers that leave out the type; still sends other first"
        status: pass
    human_judgment: false
  - id: D3
    description: "Every Feature test runs with a scripted FakeClassifier; the app resolves JevClassifier"
    requirement: GATE-01
    verification:
      - kind: integration
        ref: "tests/Feature/SendRule/JevClassifierTest.php (fake binding, scripting, failWith, real classifier outside tests)"
        status: pass
    human_judgment: false
  - id: D4
    description: "rules/triage.md is checked: other present and never sendable, no repeated intent key, only the six known questions, each with its exact owner-facing line"
    requirement: GATE-01
    verification:
      - kind: integration
        ref: "tests/Feature/Icm/TriageValidationTest.php"
        status: pass
    human_judgment: false
  - id: D5
    description: "TriageReading applies Ian's thresholds at the exact boundaries (0.85/0.8499, 0.075/0.0751), the life-safety trigger and the asks-for-person band, with reasons in priority order"
    requirement: GATE-01
    verification:
      - kind: unit
        ref: "tests/Unit/SendRule/TriageReadingTest.php"
        status: pass
    human_judgment: false

duration: 6min
completed: 2026-10-12
status: complete
---

# Phase 2 Plan 07: Fail-closed Jev, triage validation and threshold reading Summary

**Jev now fails closed on every error path, rules/triage.md is validated in plain words, every Feature test runs with a scripted FakeClassifier, and TriageReading turns Jev's numbers into send, hold, safety-line or hand-off using Ian's strict thresholds.**

## Performance

- **Duration:** about 6 min (wall clock from the previous plan's last commit)
- **Started:** 2026-10-12T02:04Z
- **Completed:** 2026-10-12T02:11Z
- **Tasks:** 2 (a tracer and a TDD task)
- **Files modified:** 12 (4 created, 8 modified)

## Accomplishments

- `JevClassifier` replaces plan 02-06's happy-path `read()` with the completeness check: a blank key or a `latest` model throws before any request; `ConnectionException` and `RequestException` become `ClassifierFailed` carrying the status and never the key; the body must decode with an `answers` array; the intent needs a `choice` among the options sent and a numeric `confidence` in 0 to 1; each yes/no needs a numeric `noul` in 0 to 1; a present `type` must match the type asked; a missing `type` with the right shape is accepted. No retry.
- `FakeClassifier` (default intent `faq` at 0.95, every yes/no at 0.01, model `fake-jev`; `answer()`, `failWith()`, `calls()`), bound to `Classifier` for every Feature test in `tests/Pest.php` with a `fakeClassifier()` helper. `AppServiceProvider` binds `Classifier` to `JevClassifier`.
- `Validator::triageErrors()`: `other` missing, `other` sendable, repeated intent key, and any question outside the six, each with the exact owner-facing line.
- `TriageReading` and `gate.classifier` (`intent_min_confidence` 0.85, `gate_check_max_yes` 0.075, `life_safety_min_yes` 0.075, `asks_for_person_min_yes` 0.85): floats compared with no rounding, the thresholds copied onto the reading, reasons ordered by priority with `reasonCode` the first.
- `.env.example` documents `JEV_API_KEY`, `JEV_MODEL` and `JEV_BASE_URL` (commented, no values), the pin-never-latest rule and that no key means every agent reply is held.

## Task Commits

1. **Task 1: Jev fails closed, triage files checked, tests use the fake (tracer)**
   - RED: `874b56f` (test) - `test(02-07): add failing fail-closed and triage validation tests`
   - GREEN: `42cbbcb` (feat) - `feat(02-07): Jev fails closed; triage files checked; tests use the fake`
2. **Task 2: TriageReading thresholds (TDD)**
   - RED: `a59ffdf` (test) - `test(02-07): add failing triage reading threshold tests`
   - GREEN: `bc14d0f` (feat) - `feat(02-07): read Jev answers with the D-45 to D-47 thresholds`

**Plan metadata:** committed separately as `docs(02-07): complete fail-closed Jev and triage reading plan`.

Tracer gate: the tracer's automated verify (both test files, then the full suite) was re-run end to end and passed before Task 2 started. Tracer verified end-to-end, expanded.

## TDD Gate Compliance

Both tasks have a `test(02-07)` commit followed by a `feat(02-07)` commit. No REFACTOR commit was needed.

- **Task 1 RED:** 25 failures and 5 errors, all on the planned behavior: `ClassifierFailed` not thrown for untrusted 200 bodies and a blank key, `RequestException` / `ConnectionException` escaping un-wrapped, `Classifier` not instantiable, `fakeClassifier()` undefined. Three triage rows (asks_for_person missing, `sendable: yes`, other listed last) and the accept-path tests already passed because the existing rules covered them; they pin existing behavior.
- **Task 2 RED:** a bare "class not found" is a load failure, not valid RED. To get a real RED I ran the test against a throwaway skeleton `TriageReading` that always returned sendable with no reasons: 19 of 24 tests failed on the planned assertions (for example `Failed asserting that true is identical to false` for every holding row, `null` for `reasonCode`). The skeleton was deleted before the RED commit, so the committed RED state fails by class-not-found; semantic assessment above records the real check. The `gsd_run check tdd-red-evidence` classifier was not run: it has no adapter for Pest's output.

## Files Created/Modified

- `app/SendRule/JevClassifier.php` - fail-closed call and strict answer reading
- `app/SendRule/FakeClassifier.php` - scripted test classifier
- `app/SendRule/TriageReading.php` - thresholds applied to a ClassifierResult
- `app/Icm/Validator.php` - `triageErrors()` and the six-question constant
- `app/Providers/AppServiceProvider.php` - `Classifier` bound to `JevClassifier`
- `tests/Pest.php` - FakeClassifier bound in `beforeEach`, `fakeClassifier()` helper
- `config/gate.php` - `classifier` thresholds with D-45, D-46, D-47 comments
- `.env.example` - commented Jev keys
- `tests/Feature/SendRule/JevClassifierTest.php`, `tests/Feature/Icm/TriageValidationTest.php`, `tests/Unit/SendRule/TriageReadingTest.php` - the tests
- `docs/sprints/sprint-1.md` - three walk-through lines (HTTP client failures, swapping an interface for a fake, float thresholds)

## Decisions Made

- A missing yes/no answer is a hold in two places: `JevClassifier` refuses the call, and `TriageReading` would treat a missing value as a certain yes if it ever received one.
- Numeric strings are refused (`"0.1"` fails the call); only JSON numbers count.
- Both `classifier_intent:<intent>` and `classifier_unsure:intent` are listed when both apply, so the stored reasons show everything that held the reply.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Plan correction] The asks_for_person missing line has no colon after `questions`**
- **Found during:** Task 1 (writing TriageValidationTest)
- **Issue:** The plan's expected line was `rules/triage.md: questions: asks_for_person is missing, and it is required`, with a note to adjust if PlainWords renders two-part paths without a colon. `PlainWords::label()` only adds the colon at three or more path parts, so the existing rule renders `questions asks_for_person is missing, and it is required`.
- **Fix:** The test expects the line PlainWords actually produces, keeping the field name and "is missing" as the plan allowed.
- **Files modified:** tests/Feature/Icm/TriageValidationTest.php
- **Committed in:** 874b56f

---

**Total deviations:** 1 (a plan-sanctioned wording adjustment)
**Impact on plan:** None. No scope change.

## Issues Encountered

None. A stray `sed` command that I mistyped while editing a docblock failed without changing anything and was replaced by the intended edit.

## Known Stubs

None.

## Threat Surface

No new surface beyond the plan's threat model. T-02-11 (Jev can only hold, `TriageReading` never sends), T-02-12 (3 s connect and 8 s timeout, no retry, every failure a `ClassifierFailed`) and T-02-13 (model pinned from config, `latest` refused, served model kept on each result) are mitigated and tested.

## User Setup Required

None for the build. Before live replies can send, Ian needs `JEV_API_KEY` in `.env`; without it every agent reply is held as a draft by design.

## Next Phase Readiness

- Plan 02-14 can call `app(Classifier::class)->classify(...)` once per reply, wrap it in `try/catch (ClassifierFailed)` to hold, and feed the result to `TriageReading::read($result, $questions, config('gate.classifier'))`.
- The JevClassifier response shape (`type`, `choice`, `confidence`, `noul`) is the documented one from the plan; it has not been exercised against the live endpoint in this plan (tests use `Http::fake`).

## Self-Check: PASSED

- Created files exist: FakeClassifier.php, TriageReading.php, TriageValidationTest.php, TriageReadingTest.php.
- Commits `874b56f`, `42cbbcb`, `a59ffdf`, `bc14d0f` are all ancestors of HEAD.
- All acceptance criteria greps pass; full suite passed (505 tests) after the last commit.
