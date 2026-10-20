---
phase: 02-text-front-desk
plan: 06
subsystem: send-rule
tags: [jev, typesafe, openrouter, classifier, triage, icm, laravel-http-client]

requires:
  - phase: 02-text-front-desk
    provides: Validator SINGLETONS and rulesFor(), ActiveConfig::singleton(), exampleFolderFileCount() (plan 02-01); fixed replies and business clock (plan 02-05)
provides:
  - rules/triage.md (type triage) live through the sync, with ActiveConfig::triage()
  - App\SendRule\Classifier interface, ClassifierResult, ClassifierFailed, TriageQuestions
  - JevClassifier: one System One request per reply, answers read back with the served model
  - services.jev config (base_url, key, model, connect_timeout, timeout)
affects: [02-07 classifier failure handling and fake, 02-14 send path]

plan_head_before: 30cc8b7d2f68f241c23d2701cba4175b48f28e3d
plan_head_after: 316f5fd35eca9b0701300dc7b08a518cf8b500cb

actuals:
  tokens: 11000
  tasks: 1
  commits: 2

tech-stack:
  added: []
  patterns:
    - "Driver interface plus Http-backed implementation, mirroring TextChannel"
    - "Readonly value objects built from ActiveConfig through a named constructor"

key-files:
  created:
    - examples/example-hvac/rules/triage.md
    - app/SendRule/Classifier.php
    - app/SendRule/ClassifierResult.php
    - app/SendRule/ClassifierFailed.php
    - app/SendRule/TriageQuestions.php
    - app/SendRule/JevClassifier.php
    - tests/Feature/SendRule/JevClassifierTest.php
  modified:
    - examples/example-hvac/rules/CONTEXT.md
    - app/Icm/Loader.php
    - app/Icm/Validator.php
    - app/Icm/ActiveConfig.php
    - config/services.php
    - tests/Pest.php
    - tests/Feature/Icm/ValidatorTest.php
    - docs/sprints/sprint-1.md

key-decisions:
  - "A model string containing 'latest' is refused with ClassifierFailed before any request is made (D-05)"
  - "Request state is an object with exactly customer_message and draft_reply; top-level body keys are exactly model, state, questions"
  - "Happy-path parsing only; completeness check and every refusal belong to plan 02-07"

patterns-established:
  - "File-specific Pest helper jevAnswersPayload() builds a valid Jev response for the example triage questions"

requirements-completed: [GATE-01]

coverage:
  - id: D1
    description: "rules/triage.md goes live through the sync; the example folder counts 11 files and ActiveConfig::triage() reads it"
    requirement: GATE-01
    verification:
      - kind: unit
        ref: "tests/Feature/SendRule/JevClassifierTest.php#it puts the owner triage file in the live folder"
        status: pass
      - kind: unit
        ref: "tests/Feature/SendRule/JevClassifierTest.php#it reads the triage questions with other first and only three sendable intents"
        status: pass
    human_judgment: false
  - id: D2
    description: "One Jev request per reply holding only model, the two texts and the questions, with other first and six noul questions"
    requirement: GATE-01
    verification:
      - kind: unit
        ref: "tests/Feature/SendRule/JevClassifierTest.php#it sends one request holding only the model, the two texts and the questions"
        status: pass
      - kind: unit
        ref: "tests/Feature/SendRule/JevClassifierTest.php#it asks one choice question with other first and six yes or no questions"
        status: pass
      - kind: unit
        ref: "tests/Feature/SendRule/JevClassifierTest.php#it never sends a name, a phone number or a customer card to Jev"
        status: pass
    human_judgment: false
  - id: D3
    description: "Jev's answer comes back as a ClassifierResult carrying the served model; a latest model is refused"
    requirement: GATE-01
    verification:
      - kind: unit
        ref: "tests/Feature/SendRule/JevClassifierTest.php#it reads Jev answers back with the served model"
        status: pass
      - kind: unit
        ref: "tests/Feature/SendRule/JevClassifierTest.php#it refuses a model that moves with new releases"
        status: pass
    human_judgment: false
  - id: D4
    description: "A live call against OpenRouter with Ian's key accepts typesafe/jev-1.13 and returns the answer shapes this driver reads"
    verification: []
    human_judgment: true
    rationale: "Tests never touch the network; research assumption A1 (model string accepted on OpenRouter) is only settled by one live call with a real key"

duration: 25min
completed: 2026-10-12
status: complete
---

# Phase 2 Plan 06: Triage Questions and Jev Driver Summary

**The owner's triage questions live in `rules/triage.md` and go through the sync, and `JevClassifier` posts them to OpenRouter's System One endpoint in one request per reply, reading back intent, six yes/no numbers and the served model.**

## Performance

- **Duration:** 25 min
- **Completed:** 2026-10-12T01:59:08Z
- **Tasks:** 1 (tracer)
- **Files modified:** 15

## Accomplishments
- `rules/triage.md` (type `triage`) with seven intents (`other` first, never sendable), the intent question and the six yes/no questions including `asks_for_person` (D-11, D-47); the Loader, Validator rules and `ActiveConfig::triage()` accept it; the example folder now counts 11 files.
- `Classifier` interface, `ClassifierResult` (keeps the served `model`), `ClassifierFailed`, `TriageQuestions::fromConfig()` and `toJevQuestions()` (choice question with `other` first, then six `noul` questions).
- `JevClassifier` sends `POST {base}/systemone` with a bearer token, short timeouts and `throw()`, the body being exactly `model`, `state` (`customer_message`, `draft_reply`) and `questions` (D-04, D-05). The key and texts are never logged, only lengths and status.
- `services.jev` config pinned to `typesafe/jev-1.13`; any model containing "latest" is refused before a request is made.

## Task Commits

1. **Task 1 RED:** `8d5b214` (test) - failing Jev request and triage tests (8 tests, 3 assertion failures, 5 missing-class errors)
2. **Task 1 GREEN:** `316f5fd` (feat) - triage questions in the folder and the Jev driver

**Plan metadata:** committed separately (docs: complete plan)

## Files Created/Modified
- `examples/example-hvac/rules/triage.md` - owner-visible Jev questions
- `examples/example-hvac/rules/CONTEXT.md` - lists the new file
- `app/Icm/Loader.php`, `app/Icm/Validator.php`, `app/Icm/ActiveConfig.php` - `triage` type, rules and accessor
- `app/SendRule/{Classifier,ClassifierResult,ClassifierFailed,TriageQuestions,JevClassifier}.php` - the classifier seam and Jev driver
- `config/services.php` - `jev` block
- `tests/Feature/SendRule/JevClassifierTest.php` - the plan's behaviors
- `tests/Pest.php` - `exampleFolderFileCount()` is now 11
- `tests/Feature/Icm/ValidatorTest.php` - the unknown-type message now lists `triage`
- `docs/sprints/sprint-1.md` - two walk-through lines (HTTP client and faking; readonly classes)

## Decisions Made
- Refuse a `latest` model in the driver now rather than waiting for 02-07, because it is a must-have truth of this plan (D-05) and costs one line.
- `JevClassifier::read()` defaults missing values to 0.0 and empty strings; this is deliberately happy-path only and plan 02-07 replaces it with the completeness check that makes every gap a `ClassifierFailed`.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Existing validator test listed the allowed types**
- **Found during:** Task 1 (full-suite run)
- **Issue:** `ValidatorTest` asserts the "type must be one of ..." message; adding `triage` to `Loader::TYPED` changed it.
- **Fix:** Added `triage` to the expected message.
- **Files modified:** tests/Feature/Icm/ValidatorTest.php
- **Verification:** full suite passes (423 tests)
- **Committed in:** 316f5fd

**2. [Rule 3 - Blocking] `make:exception` wrote to `app/Exceptions/SendRule/`**
- **Found during:** Task 1
- **Issue:** The generator put `ClassifierFailed` outside `app/SendRule/`, where the plan places it.
- **Fix:** Removed the generated folder and wrote the class in `app/SendRule/ClassifierFailed.php`.
- **Committed in:** 316f5fd

---

**Total deviations:** 2 auto-fixed (1 bug, 1 blocking)
**Impact on plan:** None on scope.

## Issues Encountered
None beyond the deviations above.

## Known Stubs
None. `JevClassifier::read()` is intentionally happy-path; plan 02-07 (named in the plan) adds every refusal.

## User Setup Required
Live Jev calls need `JEV_API_KEY` (OpenRouter dashboard, Keys) and optionally `JEV_MODEL` / `JEV_BASE_URL`. Without a key every agent reply is held as a draft once 02-07 lands. Plan 02-07 documents these in `.env.example`.

## Threat Flags
None. The one new boundary (App to OpenRouter/Jev) is T-02-10, mitigated by the request-shape tests.

## TDD Gate Compliance
RED commit `8d5b214` (`test(02-06)`) precedes GREEN `316f5fd` (`feat(02-06)`). The RED run failed on the planned behaviors: the 10-versus-11 file count, missing `triage` config, and missing classifier classes. The task was `type="tracer"`, so no `tdd-red-evidence` record was generated.

## Next Phase Readiness
Ready for 02-07: add the cross-field triage checks (missing or sendable `other`, unknown keys, repeated intent key), the completeness check, every refusal as `ClassifierFailed`, the fake classifier and `.env.example` keys. Open item: one live call with Ian's key settles research assumption A1.

## Self-Check: PASSED
- Created files exist; commits `8d5b214` and `316f5fd` are in HEAD history.
- Both automated verify commands pass (155 focused tests, 423 full suite).

---
*Phase: 02-text-front-desk*
*Completed: 2026-10-12*
