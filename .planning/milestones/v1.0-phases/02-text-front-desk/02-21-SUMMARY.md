---
phase: 02-text-front-desk
plan: 21
subsystem: send-rule
tags: [life-safety, laravel-queue, owner-alerts, pest, tdd]

requires:
  - phase: 02-text-front-desk
    provides: OutboundSender with SendOrigin::LifeSafety, PauseConversation, RaiseOwnerAlert, ComposeAutomatedText, Verdict::lifeSafety, reply cap that ignores life_safety
provides:
  - App\SendRule\LifeSafety (hazard phrase lists and the three code-fixed sentences)
  - App\Actions\SendLifeSafetyLine (send, pause, alert; once per customer text)
  - App\Jobs\RespondToLifeSafety (immediate, not debounced)
  - ComposeAutomatedText::forLifeSafety (safety sentence, then disclosure on a first automated text, no sign-off)
  - RecordInbound trigger and RunFrontDesk second chance plus Jev second net
affects: [02-24 opt-out block, 02-22, 02-23, phase verification of GATE-01 and GATE-02]

# Actuals (#2632)
actuals:
  tokens: 9700
  tasks: 2
  commits: 3

plan_head_before: 23bee21
plan_head_after: 1088581

tech-stack:
  added: []
  patterns:
    - "Hard-coded safety words as public const arrays, matched with CodeChecks::phrasePattern"
    - "Immediate queued job with afterCommit() that bypasses the debounce"
    - "Per-message Cache lock around a check-then-send, with the check reading meta.in_reply_to"

key-files:
  created:
    - app/SendRule/LifeSafety.php
    - app/Actions/SendLifeSafetyLine.php
    - app/Jobs/RespondToLifeSafety.php
    - tests/Feature/FrontDesk/LifeSafetyTest.php
    - tests/Unit/SendRule/LifeSafetyTest.php
  modified:
    - app/Actions/RecordInbound.php
    - app/Actions/ComposeAutomatedText.php
    - app/Actions/RunFrontDesk.php
    - tests/Pest.php
    - docs/sprints/sprint-1.md

key-decisions:
  - "A safety line that failed to send does not count as having answered the text, so RunFrontDesk's second chance can try again; the owner alert is not repeated because RaiseOwnerAlert already skips an alert the owner has"
  - "forLifeSafety and the utility phone lookup tolerate NoActiveVersion: with no live folder the line still goes out, without disclosure or gas company number"
  - "When Jev's life_safety reading fires the classifier path does not also hand off (asks_for_person); the safety line has already paused and alerted"
  - "RunFrontDesk skips the holding reply once the conversation is paused, since the outbound path would refuse it"

patterns-established:
  - "Pest helpers textsSentForAgentReplies() and theAgentMessage() leave out life_safety messages: the agent's own words are not the fixed safety line"
  - "A later text in a test uses a direct webhook post, because frontDeskCustomerTexts() asserts the folder activates and only works once per test"

requirements-completed: [GATE-02, GATE-01]

coverage:
  - id: D1
    description: "A customer describing gas, fire, smoke, CO, sparks or water at electrical things gets the fixed safety line at once, at any hour, before the burst wait"
    requirement: GATE-02
    verification:
      - kind: integration
        ref: "tests/Feature/FrontDesk/LifeSafetyTest.php#sends the safety line the moment a customer says they smell gas"
        status: pass
      - kind: unit
        ref: "tests/Unit/SendRule/LifeSafetyTest.php#names the danger a customer describes"
        status: pass
    human_judgment: false
  - id: D2
    description: "The safety sentences are fixed in code, never give repair or shut-off steps, and only the gas company phone is filled from the folder"
    requirement: GATE-02
    verification:
      - kind: unit
        ref: "tests/Unit/SendRule/LifeSafetyTest.php#never gives a repair or shut-off step"
        status: pass
    human_judgment: false
  - id: D3
    description: "After the line the conversation pauses (life_safety) and the owner is alerted at once, once per message, including after hours"
    requirement: GATE-01
    verification:
      - kind: integration
        ref: "tests/Feature/FrontDesk/LifeSafetyTest.php#pauses the conversation and alerts the owner at once"
        status: pass
    human_judgment: false
  - id: D4
    description: "The line gets through a taken-over conversation, the reply cap, a repeated webhook and a missing live folder, and a failed send still pauses and alerts"
    requirement: GATE-02
    verification:
      - kind: integration
        ref: "tests/Feature/FrontDesk/LifeSafetyTest.php"
        status: pass
    human_judgment: false
  - id: D5
    description: "Jev's life_safety answer is a second net: line sent, conversation paused, owner alerted, agent reply held as classifier:life_safety"
    requirement: GATE-02
    verification:
      - kind: integration
        ref: "tests/Feature/FrontDesk/LifeSafetyTest.php#catches a danger the words missed through Jev"
        status: pass
    human_judgment: false
  - id: D6
    description: "The safety text reads sensibly to a frightened customer in a real phone thread"
    verification: []
    human_judgment: true
    rationale: "Wording quality and tone on a real device are judgments no test asserts"

duration: 30min
completed: 2026-10-12
status: complete
---

# Phase 2 Plan 21: Life-Safety Line Summary

**A customer who texts "I smell gas" gets a code-fixed "leave and call 911 from outside" text within the same webhook request, even in a paused conversation, and the owner is alerted at once while the assistant steps back.**

## Performance

- **Duration:** about 30 min (start time was not captured; estimated)
- **Started:** 2026-10-12 (approx. 02:55Z)
- **Completed:** 2026-10-12T03:24Z
- **Tasks:** 2 of 2
- **Files modified:** 10 (5 created, 5 modified, including one docs file)

## Accomplishments

- `LifeSafety` holds the gas, fire/smoke/CO/sparks and water-at-electrical triggers and the three fixed sentences; plain flooding, "my pilot won't fire" and "switch from propane to natural gas" do not trigger.
- `RecordInbound` queues `RespondToLifeSafety` (no debounce, after commit) for a hazard text; `SendLifeSafetyLine` sends through `SendOrigin::LifeSafety`, pauses with `life_safety` and raises an urgent alert, once per customer text.
- `RunFrontDesk` gives a missed text a second chance before the paused check, raises the paused-message alert only when no alert exists for the burst's last message, and treats Jev's `life_safety` reading as a second net (line sent, draft held as `classifier:life_safety`).
- A failed send still pauses and alerts with "Safety message could not be sent"; with no live business folder the line still goes out.

## Task Commits

1. **Task 1: tracer, "I smell gas" gets the line, the alert and the pause**
   - RED `2ff12c7` (test)
   - GREEN `f2897b2` (feat)
2. **Task 2: trigger list, three sentences, every path the line must survive** - `1088581` (test)

**Plan metadata:** final docs commit (summary, state, roadmap) follows this file.

## TDD Gate Compliance

- Task 1: RED commit `2ff12c7` failed on the planned behavior (no safety line sent, no alert, agent prompted in the burst test; 5 tests, 2 failures and 3 errors), then GREEN `f2897b2` passed. Semantic assessment: each failure was the target assertion for the intended reason.
- Task 2: the Task 1 implementation already contained the phrase lists and the `RunFrontDesk` second net, because the plan specified the unit rows alongside the lists. The new unit and feature tests therefore passed on first run (characterization tests), so there is no separate failing RED for Task 2 and no second `feat` commit. The first run of the feature tests did fail, but only on a test-harness error (`frontDeskCustomerTexts()` asserts the folder activates and cannot be called twice); that was fixed in the tests, not the code. To prove the tests are not vacuous, two mutations were applied and reverted: removing the classifier call in `RunFrontDesk` failed the Jev test, and disabling the already-answered check failed four tests.

## Files Created/Modified

- `app/SendRule/LifeSafety.php` - hazard phrase lists, `hazardIn()`, `line()`
- `app/Actions/SendLifeSafetyLine.php` - send, pause, alert; lock and once-per-text check
- `app/Jobs/RespondToLifeSafety.php` - immediate job
- `app/Actions/ComposeAutomatedText.php` - `forLifeSafety()`
- `app/Actions/RecordInbound.php` - queues the job for a hazard text
- `app/Actions/RunFrontDesk.php` - second chance, paused-burst alert guard, Jev second net, no holding reply when paused
- `tests/Feature/FrontDesk/LifeSafetyTest.php`, `tests/Unit/SendRule/LifeSafetyTest.php` - 14 feature and 29 unit tests
- `tests/Pest.php` - the two "agent's own words" helpers skip life_safety messages
- `docs/sprints/sprint-1.md` - two walk-through lines

## Decisions Made

- A failed safety send does not count as "answered", so a later run retries; alerts stay single because `RaiseOwnerAlert` dedupes by message and reason.
- `utility_phone` is re-checked against a plain-phone pattern inside `LifeSafety::line()`, so a value that bypassed the sync still cannot add words.
- When Jev's `life_safety` fires, the handoff (`asks_for_person`) reply is skipped: the conversation is already paused and the owner already alerted.
- The holding reply is not attempted once the conversation is paused (it would only be refused and logged).

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Existing ProcessInbound test broke because the Jev life_safety path now also sends a safety line**
- **Found during:** Task 1 (full FrontDesk run after the RunFrontDesk change)
- **Issue:** `theAgentMessage()` used `sole()` over outbound messages and found the new safety line next to the held draft; `textsSentForAgentReplies()` likewise counted it.
- **Fix:** Both shared helpers in `tests/Pest.php` now leave out `life_safety` messages, matching how they already leave out holding replies.
- **Files modified:** tests/Pest.php
- **Verification:** `php artisan test --compact tests/Feature/FrontDesk` and the full suite pass.
- **Committed in:** f2897b2

**2. [Rule 2 - Missing critical] Safety line must survive a missing live business folder**
- **Found during:** Task 1 design
- **Issue:** `ComposeAutomatedText` and the utility phone lookup throw `NoActiveVersion` before a first folder is live, which on the sync queue would 500 the webhook instead of warning a customer.
- **Fix:** `forLifeSafety()` and `SendLifeSafetyLine::utilityPhone()` catch it and send the bare line; covered by a test.
- **Files modified:** app/Actions/ComposeAutomatedText.php, app/Actions/SendLifeSafetyLine.php
- **Committed in:** f2897b2, test in 1088581

**3. [Rule 2 - Missing critical] Race between the immediate job and RunFrontDesk's second chance**
- **Found during:** Task 1 design
- **Issue:** Both can reach the "already answered?" check together and send two lines for one text.
- **Fix:** A `Cache::lock("life-safety:{message id}")` wraps the check and send.
- **Files modified:** app/Actions/SendLifeSafetyLine.php
- **Committed in:** f2897b2

**4. [Rule 1 - Bug] RunFrontDesk held conversation went through the holding reply while paused**
- **Found during:** Task 2 (Jev second net)
- **Issue:** After the classifier path pauses the conversation, `storeDraft()` would attempt the holding reply, which `OutboundSender` refuses and `report()` logs.
- **Fix:** `storeDraft()` skips the holding reply when the conversation is paused.
- **Files modified:** app/Actions/RunFrontDesk.php
- **Committed in:** f2897b2

---

**Total deviations:** 4 auto-fixed (2 bugs, 2 missing critical). **Impact on plan:** All needed for correctness; no scope change.

## Issues Encountered

None beyond the test-harness slip noted under TDD Gate Compliance.

## User Setup Required

None - no external service configuration required.

## Known Stubs

None.

## Next Phase Readiness

- Plan 02-24 adds the opt-out check in `OutboundSender::whyBlocked()`; the safety line will then be refused for an opted-out customer and the failure path alerts the owner, as the flagged assumption expects.
- Only English text is sent; a non-English hazard relies on Jev's second net, which also sends the English line.

## Self-Check: PASSED

- Created files exist: LifeSafety.php, SendLifeSafetyLine.php, RespondToLifeSafety.php, both test files.
- Commits `2ff12c7`, `f2897b2`, `1088581` are ancestors of HEAD.
- `php artisan test --compact tests/Feature/FrontDesk tests/Unit/SendRule` (289 tests) and the full suite (776 tests) pass.

---
*Phase: 02-text-front-desk*
*Completed: 2026-10-12*
