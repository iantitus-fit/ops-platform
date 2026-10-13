---
phase: 02-text-front-desk
plan: 35
subsystem: send-rule
tags: [gate, jev, thresholds, triage, gap-closure, laravel, pest]

requires:
  - phase: 02-text-front-desk
    provides: send-or-draft rule (TriageReading, DecideSendOrDraft), config/gate.php classifier thresholds (D-45 to D-47)
provides:
  - per-question yes/no bars in config/gate.php (price, promise, discount, complaint)
  - TriageReading reads each gate check against its own bar
  - D-56 on record amending D-45
  - Ian's reworded promise and complaint questions committed in the example HVAC folder
affects: [02-UAT re-run, gate:export spot-check, later gap-closure plans 02-36 to 02-41]

actuals:
  tokens: 6000
  tasks: 2
  commits: 3
plan_head_before: c42f29d460bad5342ffdb545d89b9f085fbc7afa
plan_head_after: 20b40e4e3c2ebcde883fe5e46f99f124b1190896

tech-stack:
  added: []
  patterns:
    - "One yes/no bar per gate question inside config('gate.classifier'), stored whole on every verdict"
    - "gate_check_max_yes kept under its old name as the floor of the asks_for_person unsure band"

key-files:
  created: []
  modified:
    - config/gate.php
    - app/SendRule/TriageReading.php
    - tests/Unit/SendRule/TriageReadingTest.php
    - tests/Feature/FrontDesk/GateTest.php
    - examples/example-hvac/rules/triage.md
    - .planning/phases/02-text-front-desk/02-CONTEXT.md

key-decisions:
  - "D-56 (amends D-45): price and discount keep P(yes) <= 0.075; promise and complaint use P(yes) <= 0.15, the looser reading of D-03 named in RESEARCH OQ-1"
  - "gate_check_max_yes stays 0.075 so old stored verdicts read the same and the asks_for_person unsure band keeps its floor"

patterns-established:
  - "Config-match test: the unit fixture requires config/gate.php so the test thresholds cannot drift from the real ones"

requirements-completed: [GATE-01]

coverage:
  - id: D1
    description: "UAT Test 2 reading: a plain intake reply with Jev promise 0.09 and complaint 0.07 and clean code checks sends on its own, end to end through the send rule"
    requirement: GATE-01
    verification:
      - kind: integration
        ref: "tests/Feature/FrontDesk/GateTest.php#UAT Test 2: a plain intake question sends despite Jev's leftover promise and complaint (D-56)"
        status: pass
      - kind: unit
        ref: "tests/Unit/SendRule/TriageReadingTest.php#the UAT intake reading"
        status: pass
    human_judgment: false
  - id: D2
    description: "Promise and complaint hold only above 0.15; price and discount still hold above 0.075; life-safety and asks-for-a-person numbers unchanged"
    requirement: GATE-01
    verification:
      - kind: unit
        ref: "tests/Unit/SendRule/TriageReadingTest.php (promise/complaint/discount/price boundary rows, asks_for_person 0.075/0.0751 rows)"
        status: pass
      - kind: integration
        ref: "tests/Feature/FrontDesk/GateTest.php (promise/complaint just over bar, price/discount strict bar rows)"
        status: pass
    human_judgment: false
  - id: D3
    description: "Every new verdict stores the four per-question bars it was judged by"
    requirement: GATE-01
    verification:
      - kind: integration
        ref: "tests/Feature/FrontDesk/GateVerdictTest.php and tests/Feature/FrontDesk/ProcessInboundTest.php (stored thresholds equal config('gate.classifier'))"
        status: pass
    human_judgment: false
  - id: D4
    description: "Live re-run of UAT Test 2 with real keys, and gate:export spot-check of promise and complaint scores on other ordinary texts (thin margin: 0.09 against 0.15)"
    requirement: GATE-01
    verification: []
    human_judgment: true
    rationale: "Needs live JEV, Anthropic and OpenAI keys and a real Jev score on real texts; the executor cannot run it. Carried to the end-of-phase UAT by Ian's decision."
  - id: D5
    description: "Ian's triage.md wording committed exactly as written, and D-56 recorded beside D-45"
    verification:
      - kind: other
        ref: "git diff --quiet HEAD -- examples/example-hvac/rules/triage.md; grep -c D-56 02-CONTEXT.md = 2"
        status: pass
    human_judgment: false

duration: 2 sessions (Task 1 on 2026-10-12, Task 2 and close-out on 2026-10-13)
completed: 2026-10-13
status: complete
---

# Phase 02 Plan 35: One Yes/No Bar Per Question Summary

**The send rule now judges price, promise, discount and complaint each against its own Jev bar (0.075, 0.15, 0.075, 0.15), so UAT Test 2's plain intake reply (promise 0.09, complaint 0.07) sends on its own while code checks and the label floor still hold every real price, promise, discount and complaint reply.**

## Performance

- **Duration:** two sessions (Task 1 on 2026-10-12, Task 2 and close-out on 2026-10-13)
- **Tasks:** 2 of 2
- **Files modified:** 6 (plus this SUMMARY, STATE.md, ROADMAP.md, WINDOWS.md)

## Accomplishments

- G-02-2.1 closed in code: `config/gate.php` has `price_max_yes` 0.075, `promise_max_yes` 0.15, `discount_max_yes` 0.075 and `complaint_max_yes` 0.15 inside the classifier array; `TriageReading::read()` compares each gate check against its own key. `gate_check_max_yes` stays 0.075 as the floor of the `asks_for_person` unsure band. `life_safety_min_yes` (0.075) and `asks_for_person_min_yes` (0.85) are untouched.
- Because `DecideSendOrDraft` stores `config('gate.classifier')` whole on every verdict, every new verdict records the four bars it was judged by (T-02-82). Verdicts stored before D-56 keep their flat record.
- Boundary tests in both layers: exactly 0.15 sends, 0.1501 holds (promise and complaint); 0.0751 (held before D-56) now sends for those two; price and discount still hold at 0.0751; a unit test requires `config/gate.php` so the fixture cannot drift from the real config.
- D-56 is recorded in `02-CONTEXT.md` under a new "UAT decisions" subsection, and D-45's line points to it. Ian's reworded promise and complaint questions in `examples/example-hvac/rules/triage.md` are committed byte for byte as he left them.

## Task Commits

1. **Task 1: Tracer: UAT Test 2's intake reply sends on its own, judged by a bar per question** (TDD)
   - RED: `cf2b3ca` (test) - failing GateTest rows, TriageReadingTest fixture, new dataset rows and config-match test
   - GREEN: `9231260` (feat) - per-question bars in `config/gate.php`, `TriageReading` reads them
2. **Task 2: Record D-56 and commit Ian's promise and complaint wording** - `20b40e4` (docs)

**Plan metadata:** the docs(02-35) close-out commit that carries this SUMMARY, STATE.md, ROADMAP.md and WINDOWS.md.

## TDD Gate Compliance

- RED gate: `cf2b3ca test(02-35)` was committed first and failed because the config had no per-question keys and the reading used one bar.
- GREEN gate: `9231260 feat(02-35)` followed and passed. No refactor commit was needed.
- Re-verified at HEAD at the start of the second session: `php artisan test --compact tests/Unit/SendRule/TriageReadingTest.php tests/Feature/FrontDesk/GateTest.php tests/Feature/FrontDesk/GateVerdictTest.php tests/Feature/FrontDesk/ProcessInboundTest.php` passed (113 tests, 525 assertions). Task 2's `TriageValidationTest` and `JevClassifierTest` passed (46 tests, 141 assertions) against the folder with Ian's wording.

## Files Created/Modified

- `config/gate.php` - four per-question bars in the classifier array; docblock explains `gate_check_max_yes` is now only the `asks_for_person` unsure floor (the "approval log" sentence stays on line 41)
- `app/SendRule/TriageReading.php` - each gate check read against `{check}_max_yes`; D-56 added to the class docblock
- `tests/Unit/SendRule/TriageReadingTest.php` - new thresholds shape, config-match test, boundary rows
- `tests/Feature/FrontDesk/GateTest.php` - five end-to-end rows for the UAT text
- `examples/example-hvac/rules/triage.md` - Ian's reworded promise and complaint questions, committed unchanged
- `.planning/phases/02-text-front-desk/02-CONTEXT.md` - D-56 added, D-45 amended pointer

## Decisions Made

- D-56 (Ian, amends D-45): promise and complaint 0.15, price and discount 0.075. Why 0.15: it is the looser reading of D-03's 0.85 (P(no) >= 0.85) that RESEARCH OQ-1 already named. Code checks and the label floor still hold every price, promise, discount and complaint reply (D-02); Jev can only hold.
- `gate_check_max_yes` kept under its old name so verdicts stored before D-56 read the same.

## Deviations from Plan

None - plan executed exactly as written. (Ian's decision to carry the Task 1 live hand check to the end-of-phase UAT instead of pausing at the tracer checkpoint is a resume instruction, not a deviation from the plan's tasks.)

## Issues Encountered

None.

## Carried to UAT: live hand check (Task 1 human-check, not run by the executor)

Recorded in `.planning/WINDOWS.md` as an unrun verify (entry 6), following plan 02-23's precedent. Steps:

1. Make sure `.env` has `JEV_API_KEY`, `ANTHROPIC_API_KEY` and `OPENAI_API_KEY`.
2. Run `php artisan icm:sync`, then `php artisan queue:listen`.
3. From `/dev/chat`, text "My AC is blowing warm air". Expect the intake question to go out on its own: no Needs you draft, no holding reply.
4. From fresh numbers, text a few more ordinary messages, for example "Can you come Thursday morning?", "Do you service heat pumps?" and "It is about ten years old".
5. Run `php artisan gate:export --since=2026-10-12 --without-text` and read the promise and complaint score of each decision.
6. Promise 0.09 against 0.15 is a thin margin. Note any ordinary reply that is still held, with its scores, for Ian.

Still to run once, by Ian: the full suite `php artisan test --compact`, and the Postgres recipe in 02-VALIDATION.md (the plan's `<verification>` lists both; the executor ran the four feature/unit files above plus the two Task 2 files).

## User Setup Required

None - no external service configuration required.

## Known Stubs

None.

## Threat Flags

None. T-02-81 (promise bar moved to 0.15) is mitigated as planned: only promise and complaint moved, code checks and the label floor still run first, boundary rows at 0.15 and 0.1501 exist, and the live spot-check is carried to UAT. T-02-82 is mitigated by the per-question bars being stored on every verdict.

## Next Phase Readiness

- The rest of the Phase 2 gap-closure plans (02-36 to 02-41) do not depend on this one.
- Open item: the live hand check above, to run at the end-of-phase UAT.

## Self-Check: PASSED

- Commits `cf2b3ca`, `9231260` and `20b40e4` are all ancestors of HEAD.
- `config/gate.php`, `app/SendRule/TriageReading.php`, both test files, `examples/example-hvac/rules/triage.md` and `02-CONTEXT.md` exist and are committed; `git diff HEAD` on `triage.md` is empty.
- `commits: 3` is measured with `git rev-list --count c42f29d..HEAD` before the metadata commit.

---
*Phase: 02-text-front-desk*
*Completed: 2026-10-13*
