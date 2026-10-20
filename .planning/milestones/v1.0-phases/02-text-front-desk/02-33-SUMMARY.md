---
phase: 02-text-front-desk
plan: 33
subsystem: gap-closure
tags: [life-safety, owner-alert, send-rule, web-form, queue, wr-05, wr-06, wr-07, gate-01, gate-02]
status: complete

requires:
  - phase: 02-text-front-desk
    provides: ReportFrontDeskFailure and the ProcessInbound failure paths (02-32), SendLifeSafetyLine, RaiseOwnerAlert, PauseConversation, LifeSafety trigger lists
provides:
  - A safety line that is sent or the owner is told, never neither (failed() hook, Throwable catch, lock-timeout pause and alert)
  - gate.life_safety.lock_wait_seconds (10)
  - No holding reply after a danger the classifier found
  - Ten added gas and fire phrases and a narrowed water-plus-electrical pairing, with a water heater name that is not water
  - An urgent life_safety alert for a web form that describes a danger, with no text to the lead
affects: [02-34, 02.1]

plan_head_before: c02c14bb1566a285cbbac4c7f52fcb32d8c74034
plan_head_after: 4cd98fc81f32fde2f021cd8f629ba5d5f619b2c7

actuals:
  tokens: 4300
  tasks: 3
  commits: 6

tech-stack:
  added: []
  patterns:
    - "A fail-safe action ends in one of two states: the customer was told, or the owner was told. Every failure branch (any Throwable, lock timeout, job failed for good) pauses and alerts through the same two idempotent actions"
    - "Trigger lists hold phrases or live-power words only; an appliance name that contains a water word is removed from the text before the water check"
    - "The form danger alert is raised in ProcessInbound, the one place a form is looked at after the claim; RecordInbound stays SMS-only"

key-files:
  created: []
  modified:
    - app/Jobs/RespondToLifeSafety.php
    - app/Actions/SendLifeSafetyLine.php
    - app/Actions/RunFrontDesk.php
    - app/SendRule/LifeSafety.php
    - app/Jobs/ProcessInbound.php
    - config/gate.php
    - tests/Feature/FrontDesk/LifeSafetyTest.php
    - tests/Unit/SendRule/LifeSafetyTest.php
    - docs/sprints/sprint-1.md

key-decisions:
  - "SendLifeSafetyLine::answer() catches Throwable, not only DomainException; the exception goes to report() and its words never reach the alert"
  - "A lock timeout in SendLifeSafetyLine pauses the conversation and raises the idempotent life_safety alert 'Customer described a danger; check the safety message went out' instead of returning silently"
  - "RespondToLifeSafety::failed() pauses and alerts after the last try; both actions skip work already done"
  - "storeDraft() skips the holding reply whenever the verdict found a danger, whether or not the safety line went out"
  - "ELECTRICAL_WORDS drops bare panel, appliance and appliances and gains plugged-in; 'water heater' is stripped before the water words are checked (NOT_WATER_PHRASES)"
  - "Dangerous web forms alert the owner from ProcessInbound before the form-only stop; nothing is texted to the lead (D-41) and RecordInbound is unchanged"

requirements-completed: [GATE-02, GATE-01]

coverage:
  - id: D1
    description: "When RespondToLifeSafety fails for good, the conversation is paused (life_safety) and an urgent life_safety alert says 'Safety message could not be sent'"
    requirement: GATE-01
    verification:
      - kind: integration
        ref: "tests/Feature/FrontDesk/LifeSafetyTest.php#pauses and alerts the owner when the safety job fails for good (WR-05)"
        status: pass
    human_judgment: false
  - id: D2
    description: "A safety send that throws anything, not only a refusal, still pauses and alerts the owner and does not throw"
    requirement: GATE-01
    verification:
      - kind: integration
        ref: "tests/Feature/FrontDesk/LifeSafetyTest.php#pauses and alerts the owner when the safety send fails with any error, not only a refusal"
        status: pass
    human_judgment: false
  - id: D3
    description: "A second run that cannot get the safety lock within gate.life_safety.lock_wait_seconds pauses the conversation and raises the check-it-went-out alert"
    requirement: GATE-01
    verification:
      - kind: integration
        ref: "tests/Feature/FrontDesk/LifeSafetyTest.php#pauses and alerts the owner when another run holds the safety lock past the wait"
        status: pass
    human_judgment: false
  - id: D4
    description: "A reply held because the classifier found a danger is never followed by the holding reply"
    requirement: GATE-01
    verification:
      - kind: integration
        ref: "tests/Feature/FrontDesk/LifeSafetyTest.php#sends no holding reply when the classifier found a danger and the safety line did not go out"
        status: pass
    human_judgment: false
  - id: D5
    description: "Common gas and fire phrasings trigger the safety line, bare appliance and panel leaks and the water heater name do not, the propane/natural gas question stays quiet, and empty text and case, spacing and hyphen encoding behave"
    requirement: GATE-02
    verification:
      - kind: unit
        ref: "tests/Unit/SendRule/LifeSafetyTest.php#names the danger a customer describes, and says nothing for ordinary repair talk (D-15)"
        status: pass
      - kind: unit
        ref: "tests/Unit/SendRule/LifeSafetyTest.php#keeps the gas list to phrases, never a bare fuel word (93ce078)"
        status: pass
    human_judgment: false
  - id: D6
    description: "A web form that describes a danger alerts the owner at once (urgent life_safety, 'Web form describes a danger'), texts the lead nothing and does not ask the agent; a harmless form alerts no one; a form sharing a burst with a text alerts and the text is still answered"
    requirement: GATE-01
    verification:
      - kind: integration
        ref: "tests/Feature/FrontDesk/LifeSafetyTest.php#alerts the owner at once, and texts the lead nothing, when a web form describes a danger (WR-07, D-41)"
        status: pass
      - kind: integration
        ref: "tests/Feature/FrontDesk/LifeSafetyTest.php#alerts no one for a web form with no danger"
        status: pass
      - kind: integration
        ref: "tests/Feature/FrontDesk/LifeSafetyTest.php#alerts for a dangerous form that shares a burst with a customer text, and still answers the text"
        status: pass
    human_judgment: false
---

# Phase 2 Plan 33: Life-safety family (WR-05, WR-06, WR-07) Summary

A customer who describes a danger now always gets the safety line or the owner is alerted: every failure path of the line pauses and alerts, the holding reply never follows a danger, the trigger lists catch the missed gas and fire phrasings without firing on appliance leaks, and a dangerous web form pages the owner with no text to the lead.

## Performance

- **Started:** 2026-10-12 (inline sequential run on the phase branch)
- **Completed:** 2026-10-12T17:55Z
- **Tasks:** 3 of 3
- **Files modified:** 9
- **Tests:** full suite 985 passed (4158 assertions) on SQLite. The Postgres recipe from 02-VALIDATION.md was not run by this executor.

## Accomplishments

- WR-05: `RespondToLifeSafety::failed()` pauses and alerts after the last try. `SendLifeSafetyLine` catches `Throwable` on the send and, on a lock timeout, pauses and raises "Customer described a danger; check the safety message went out". `gate.life_safety.lock_wait_seconds` (10) sets the wait. `RunFrontDesk::storeDraft()` skips the holding reply when the verdict found a danger.
- WR-06: six gas phrases and four fire phrases added; `ELECTRICAL_WORDS` loses bare `panel`, `appliance`, `appliances` and gains `plugged-in`; `water heater` is removed from the text before the water words are checked, so the appliance name alone is never water.
- WR-07: `ProcessInbound` raises an urgent life_safety alert for each web form message in the claimed burst whose body describes a danger, before the form-only stop. The lead is sent nothing; `RecordInbound` is unchanged.
- Walk-through log line "(plan 02-33)" added to `docs/sprints/sprint-1.md`.

## TDD Evidence

| Task | RED commit | RED evidence | GREEN commit |
|------|-----------|--------------|--------------|
| 1 | 780ecb3 | Target "sends no holding reply when the classifier found a danger..." returned RED_EVIDENCE_OK (JUnit via `--log-junit`): the assertion `1 === 0` failed. The other three new tests failed on the planned behavior (undefined `failed()`, a 500 from an uncaught RuntimeException, no alert after a lock timeout). The 14 older tests passed. | 73a43ae |
| 2 | e229e2d | Target "keeps the gas list to phrases, never a bare fuel word (93ce078)" returned RED_EVIDENCE_OK: `ELECTRICAL_WORDS` still contained `panel`. 18 dataset rows also failed, each on a phrase or pairing the plan names. | a72f8bd |
| 3 | 7b2dd82 | Target "alerts the owner at once, and texts the lead nothing, when a web form describes a danger" returned RED_EVIDENCE_OK: no OwnerAlert existed. The shared-burst test failed the same way. The no-danger form test passed already, as intended (it pins the existing behavior). | 4cd98fc |

Semantic assessment: in each RED run the target executed and failed on the planned assertion for the intended reason, not on a syntax, fixture or load fault. No REFACTOR commits were needed.

## Task Commits

1. Task 1 (tracer): `780ecb3` test, `73a43ae` fix. Tracer verified end-to-end (`LifeSafetyTest` and `HoldingReplyTest`, 27 passed) and the FrontDesk directory (217 passed) before expanding.
2. Task 2: `e229e2d` test, `a72f8bd` fix.
3. Task 3: `7b2dd82` test, `4cd98fc` feat.

## Files Created/Modified

- `app/Jobs/RespondToLifeSafety.php` - `failed()` hook
- `app/Actions/SendLifeSafetyLine.php` - Throwable catch, lock-timeout pause and alert, configurable wait, docblocks
- `app/Actions/RunFrontDesk.php` - holding reply skipped on a danger verdict, docblock sentence
- `app/SendRule/LifeSafety.php` - new phrases, narrowed `ELECTRICAL_WORDS`, `NOT_WATER_PHRASES`, `withoutApplianceNames()`
- `app/Jobs/ProcessInbound.php` - `alertOwnerToDangerousForms()` and the new `RaiseOwnerAlert` parameter
- `config/gate.php` - `life_safety.lock_wait_seconds`
- `tests/Feature/FrontDesk/LifeSafetyTest.php` - seven new tests and a `lifeSafetyFormPosts()` helper
- `tests/Unit/SendRule/LifeSafetyTest.php` - 26 new dataset rows and the list-shape test
- `docs/sprints/sprint-1.md` - one walk-through log line

## Decisions Made

See `key-decisions` above. Related findings left alone as the plan said: IN-02, IN-04, WR-08, WR-10.

## Deviations from Plan

None - plan executed exactly as written.

The RED evidence needed a real report format, so the RED runs used Pest's `--log-junit` output with a scratch script outside the repo; nothing from it was committed. The new docblock sentence in `storeDraft()` and the `RunFrontDesk` condition change shift the lines below them by a few lines, which 02-34 re-points in `map/`, as the plan intends.

**Total deviations:** 0.

## Auth Gates

None.

## Known Stubs

None.

## Threat Flags

None. The new alert path in `ProcessInbound` is the mitigation for T-02-77; the spoofed-form exposure is T-02-78 and stays accepted as the plan records it.

## Issues Encountered

One note for the reviewer: `alertOwnerToDangerousForms()` runs outside the front-desk try block. If raising the alert throws, the job fails, and `ProcessInbound::failed()` raises the "Front desk stopped before answering" alert for the newest customer text, so the owner is still told. A burst that holds both a text and a dangerous form would skip its front-desk run in that case. Wrapping it was left out to keep the plan's scope.

## Next Phase Readiness

Ready for 02-34, which re-points the `map/` citations this plan moved and records the form danger alert in answer-inbound.md.

## Self-Check: PASSED

All nine modified files exist, and commits 780ecb3, 73a43ae, e229e2d, a72f8bd, 7b2dd82 and 4cd98fc are on the phase branch. `git rev-list --count c02c14b..HEAD` before this summary is 6.
