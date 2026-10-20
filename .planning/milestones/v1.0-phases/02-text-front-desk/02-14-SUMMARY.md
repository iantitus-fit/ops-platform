---
phase: 02-text-front-desk
plan: 14
subsystem: send-rule
tags: [laravel, send-rule, jev, outbound, eloquent-enum-casts, cache-lock, pest]

requires:
  - phase: 02-text-front-desk
    provides: RunFrontDeskAgent and AgentRun (02-13); Classifier, TriageReading, TriageQuestions (02-06, 02-07); CodeChecks (02-03); ReplyLabel and the sending rules (02-04); ClaimInboundBurst and ProcessInbound (02-08)
provides:
  - Columns on messages - kind, label, gate_outcome, gate_reason, gate (versioned JSON verdict, D-07)
  - App\Enums\MessageKind, GateOutcome, SendOrigin
  - App\Actions\ComposeAutomatedText (disclosure on the first automated text of a conversation, sign-off on every one, D-50)
  - App\SendRule\DecideSendOrDraft::decide() and App\SendRule\Verdict (three checks, plain reasons)
  - App\Actions\OutboundSender (the one outbound chokepoint, Pitfall 15)
  - App\Actions\RunFrontDesk::handle() and ProcessInbound::handle(ClaimInboundBurst, RunFrontDesk)
  - Pest - Queue::fake([ProcessInbound::class]) in every Feature test, runQueuedFrontDeskJobs() helper
affects: [02-15 verdict depth and owner replies on the chokepoint, 02-16 and 02-17 alerts and holding reply, 02-20 and 02-21 life safety and hand-off, 02-22 draft approval]

plan_head_before: d05525f72a84e873961f3ed4967526dd2f5cd43a
plan_head_after: e77c2a67b8c7bf09e06ce061726dc89a0d24aa5a

actuals:
  tokens: 14640
  tasks: 1
  commits: 2

tech-stack:
  added: []
  patterns:
    - "Pure decision object (DecideSendOrDraft) returning a readonly Verdict that stores itself as message columns"
    - "One outbound chokepoint taking the reply:{conversation id} lock: get() for the owner, block(n) for automated sends"
    - "Queue::fake([Job::class]) holds one job back; a helper runs what was queued exactly once"

key-files:
  created:
    - app/Actions/ComposeAutomatedText.php
    - app/Actions/OutboundSender.php
    - app/Actions/RunFrontDesk.php
    - app/Enums/GateOutcome.php
    - app/Enums/MessageKind.php
    - app/Enums/SendOrigin.php
    - app/SendRule/DecideSendOrDraft.php
    - app/SendRule/Verdict.php
    - database/migrations/2026_10_12_000005_add_front_desk_columns_to_messages_table.php
    - tests/Feature/FrontDesk/ProcessInboundTest.php
  modified:
    - app/Jobs/ProcessInbound.php
    - app/Models/Message.php
    - tests/Pest.php
    - docs/sprints/sprint-1.md

key-decisions:
  - "Every cheap check (label, length, code) is evaluated and recorded on the verdict even after an earlier one fails; the reason is the first failure in the planned order, and Jev runs only when all three passed"
  - "Any Throwable from the classifier holds the reply as classifier_failed (non-ClassifierFailed ones are also reported), so an unexpected driver error can never become silence or a send"
  - "A draft stores the agent's own words without disclosure or sign-off; ComposeAutomatedText adds them when a text actually goes out (D-50)"
  - "OutboundSender refuses an empty body or one over 1,600 characters itself, as the last guard behind the send rule's too_long hold"
  - "A burst claimed while no folder is live is left unanswered (Pitfall 7, as planned); it is not un-claimed"

patterns-established:
  - "Verdict::toAttributes(label) is spread into messages()->create() so one call stores label, outcome, reason and the gate JSON"
  - "Reason codes: label:<label>, too_long, code:<rule>, classifier_failed, classifier:<check>, classifier_intent:<intent>, classifier_unsure:<x>, send_refused; Verdict::plainReasonFor() turns each into owner words"

requirements-completed: [AGNT-02, GATE-01]

coverage:
  - id: D1
    description: "A customer who texts through the fake channel gets the agent's reply in the same conversation, with the AI disclosure, a blank line, the reply, and the sign-off, sent through OutboundSender and the TextChannel (AGNT-02)"
    requirement: AGNT-02
    verification:
      - kind: integration
        ref: "tests/Feature/FrontDesk/ProcessInboundTest.php#it answers a customer text with the disclosure, the reply and the sign-off, and stores the verdict"
        status: pass
      - kind: integration
        ref: "tests/Feature/FrontDesk/ProcessInboundTest.php#it puts the disclosure only on the first automated text of a conversation"
        status: pass
      - kind: integration
        ref: "tests/Feature/FrontDesk/ProcessInboundTest.php#it answers a burst of texts once, with every text of the burst as the customer text"
        status: pass
    human_judgment: false
  - id: D2
    description: "A reply sends on its own only when its label is allowed (never price, promise, discount, complaint, other), the code checks find nothing in the final text, and Jev's reading is sendable; any hold, failure or uncertainty is stored as a draft (GATE-01, D-01)"
    requirement: GATE-01
    verification:
      - kind: integration
        ref: "tests/Feature/FrontDesk/ProcessInboundTest.php#it keeps a price reply as a draft even when the agent labels it a faq answer, and never asks Jev (GATE-01, D-02)"
        status: pass
      - kind: integration
        ref: "tests/Feature/FrontDesk/ProcessInboundTest.php#it never sends a reply the agent labels price, promise, discount or complaint, or other (5 data sets)"
        status: pass
      - kind: integration
        ref: "tests/Feature/FrontDesk/ProcessInboundTest.php#it keeps the floor in code: a stored rule that lists price as sendable still holds a price reply (D-08)"
        status: pass
      - kind: integration
        ref: "tests/Feature/FrontDesk/ProcessInboundTest.php#it holds the reply when Jev is down / reads it as an intent the owner has not made sendable"
        status: pass
    human_judgment: false
  - id: D3
    description: "A code veto means Jev is not called, a Jev answer can only hold and never rescue, and Jev is called once with the burst text and the agent's words (D-02, D-04)"
    verification:
      - kind: integration
        ref: "tests/Feature/FrontDesk/ProcessInboundTest.php#it asks Jev once, about the burst text and the words the agent wrote, not the disclosure or the sign-off (D-04)"
        status: pass
      - kind: integration
        ref: "tests/Feature/FrontDesk/ProcessInboundTest.php#it can only be held by Jev, never rescued"
        status: pass
    human_judgment: false
  - id: D4
    description: "A final text over 1,600 characters is held as a draft with reason too_long, never cut and sent (Pitfall 10)"
    verification:
      - kind: integration
        ref: "tests/Feature/FrontDesk/ProcessInboundTest.php#it holds a final text over 1,600 characters instead of cutting it (Pitfall 10)"
        status: pass
    human_judgment: false
  - id: D5
    description: "Every agent reply stores its verdict on the message: label, gate_outcome, gate_reason and the version 1 gate JSON with inputs, each check, thresholds and the reply model (D-07)"
    verification:
      - kind: integration
        ref: "tests/Feature/FrontDesk/ProcessInboundTest.php#it stores the whole verdict on the message (D-07)"
        status: pass
      - kind: integration
        ref: "tests/Feature/FrontDesk/ProcessInboundTest.php#it stores the rule that held a reply and a plain reason on the verdict"
        status: pass
    human_judgment: false
  - id: D6
    description: "A web form lead is never texted, no folder means nothing sent and nothing thrown, and two runs for one burst send at most one reply (D-41, Pitfall 7, AGNT-02 concurrency)"
    verification:
      - kind: integration
        ref: "tests/Feature/FrontDesk/ProcessInboundTest.php#it never texts a web form lead (D-41), #it sends nothing and throws nothing when no business folder is live yet, #it sends one text when the queued job runs twice"
        status: pass
    human_judgment: false
  - id: D7
    description: "OutboundSender is the single path: it takes the reply lock (owner fails at once, automated waits then gives up), keeps a failed channel send on the record, and refuses closed or phone-less conversations (Pitfall 15)"
    verification:
      - kind: integration
        ref: "tests/Feature/FrontDesk/ProcessInboundTest.php#it keeps a failed send on the record, #it makes an automated send wait for the lock, #it says why a conversation cannot be written to, #it keeps the reply as a draft when the sender refuses it"
        status: pass
    human_judgment: false
  - id: D8
    description: "Whether the plain-words reasons read well to the owner and whether the example reply wording feels right on a real phone"
    verification: []
    human_judgment: true
    rationale: "Wording quality is a judgment call; plan 02-15 tests the reasons in depth and the 02-23 phone check exercises a real handset"

duration: 6min
completed: 2026-10-12
status: complete
---

# Phase 2 Plan 14: Front Desk Through the Send Rule Summary

**A customer's text is answered by the agent, wrapped in the AI disclosure and sign-off, gated by label floor, code checks and one Jev call, and then sent through the single OutboundSender or kept as a draft with a versioned verdict stored on the message**

## Performance

- **Duration:** 6 min
- **Started:** 2026-10-12T02:30:15Z
- **Completed:** 2026-10-12T02:36:00Z
- **Tasks:** 1
- **Files modified:** 14

## Accomplishments

- `DecideSendOrDraft` runs the three checks in the planned order. The label must be in `send_on_own` and never price, promise, discount or complaint (a floor in code that a stored rule cannot lower). The composed text must fit 1,600 characters and pass `CodeChecks`. Jev is called once, and only after both pass. Jev can only hold.
- `OutboundSender` is the one outbound chokepoint: `reply:{conversation id}` lock (owner `get()`, automated `block(5)`), queued then sent only on a channel id, failure kept as a failed message plus a note, a `ReplySent` activity and `CustomerRecordChanged`.
- `RunFrontDesk` runs the agent, composes the text (disclosure only on the first automated text, sign-off always), decides, and sends or stores a draft (the agent's own words) with kind, label, outcome, reason and the gate JSON (version 1) and `meta.in_reply_to`.
- `ProcessInbound::handle(ClaimInboundBurst, RunFrontDesk)` runs the front desk after the claim and the D-41 guard, and leaves quietly when no folder is live.
- `tests/Pest.php` holds `ProcessInbound` back in every Feature test and `runQueuedFrontDeskJobs()` runs what was queued, once. The full suite is 596 tests, all green.

## TDD Gate Compliance

- Task 1 (tracer): RED `f8f4078` (28 tests, 26 failing; `gsd_run check tdd-red-evidence` returned `RED_EVIDENCE_OK` from the JUnit report; semantic check: the target test failed on its first assertion, `toHaveCount(1)` on the texts sent, "actual size 0", because the front desk was not wired into `ProcessInbound`). GREEN `e77c2a6`. No refactor commit. Tracer verified end-to-end by re-running the plan's verify commands before finishing (69 and 596 tests passing).

## Task Commits

1. **Task 1: End-to-end front desk reply through the three checks** - `f8f4078` (test), `e77c2a6` (feat)

**Plan metadata:** committed with this summary (docs).

## Files Created/Modified

- `database/migrations/2026_10_12_000005_add_front_desk_columns_to_messages_table.php` - nullable kind, label, gate_outcome, gate_reason (strings) and gate (json); no money-word column names
- `app/Models/Message.php` - fillable and casts (MessageKind, ReplyLabel, GateOutcome enums; gate as array)
- `app/Enums/{MessageKind,GateOutcome,SendOrigin}.php` - the three vocabularies
- `app/Actions/ComposeAutomatedText.php` - disclosure, words, sign-off
- `app/SendRule/DecideSendOrDraft.php`, `app/SendRule/Verdict.php` - the decision and its stored form with plain reasons
- `app/Actions/OutboundSender.php` - the one outbound path
- `app/Actions/RunFrontDesk.php` - agent run, compose, decide, send or draft
- `app/Jobs/ProcessInbound.php` - runs the front desk after the claim
- `tests/Pest.php` - `Queue::fake([ProcessInbound::class])`, `runQueuedFrontDeskJobs()`
- `tests/Feature/FrontDesk/ProcessInboundTest.php` - 28 tests
- `docs/sprints/sprint-1.md` - three walk-through lines (cache locks, selective queue fake, enum and JSON casts)

## Decisions Made

- All three cheap checks are evaluated and recorded on every verdict, not just the first failure, because the calibration dataset (D-07) is more useful when it shows everything that was true. The held reason is still the first failure in the planned order.
- Any exception from the classifier call, not only `ClassifierFailed`, holds the reply as `classifier_failed`, and the unexpected ones are reported to the log.
- Drafts keep only the agent's words; the disclosure and sign-off are composed at send time so they land on whichever automated text reaches the customer first.
- `OutboundSender` refuses a blank body and a body over 1,600 characters on its own, so no caller can hand the channel a text the send rule would have held.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Missing Critical] Chokepoint guards for blank and over-long bodies**
- **Found during:** Task 1 (OutboundSender)
- **Issue:** The plan's chokepoint checked only closed conversation and missing phone. Plans 02-15 and later add more callers (fixed replies, approved drafts) and Pitfall 10 says a text over 1,600 characters must never be cut and sent; a single path with no length guard would trust every caller.
- **Fix:** `OutboundSender::deliver()` throws a `DomainException` for an empty body or one over `SendOwnerReply::MAX_LENGTH`.
- **Files modified:** app/Actions/OutboundSender.php
- **Verification:** the existing too_long test still holds the reply as a draft before the sender is reached; the full suite passes.
- **Committed in:** e77c2a6

**2. [Rule 2 - Missing Critical] Any classifier exception holds the reply**
- **Found during:** Task 1 (DecideSendOrDraft)
- **Issue:** Catching only `ClassifierFailed` would let an unexpected driver error escape the job after the burst was already claimed, leaving the customer silently unanswered.
- **Fix:** `catch (Throwable)` holds as `classifier_failed`; non-`ClassifierFailed` exceptions are also passed to `report()`.
- **Files modified:** app/SendRule/DecideSendOrDraft.php
- **Verification:** "holds the reply when Jev is down" passes.
- **Committed in:** e77c2a6

**3. [Test fix] Over-long reply fixture was too long by itself**
- **Found during:** Task 1 (GREEN)
- **Issue:** The RED commit's too-long test used 70 repeats (1,819 characters) so the agent's own words already exceeded 1,600, and the test's own precondition (words under 1,600, composed over 1,600) failed.
- **Fix:** 57 repeats, so only the disclosure and sign-off push the text over the limit.
- **Files modified:** tests/Feature/FrontDesk/ProcessInboundTest.php
- **Committed in:** e77c2a6

---

**Total deviations:** 3 (2 Rule 2 missing critical, 1 test fixture fix)
**Impact on plan:** Both Rule 2 additions are fail-closed hardening inside files the plan already lists. No scope creep.

## Issues Encountered

- A text that arrives before any business folder is live is claimed by `ProcessInbound` and then never answered (the plan's Pitfall 7 behaviour: return quietly). Once a first folder syncs, that earlier text is not retried because `handled_at` is already set. This matches the plan; a later plan may want to un-claim instead.
- When the channel fails, `OutboundSender` leaves a `failed` message and `RunFrontDesk` then also stores the reply as a draft with reason `send_refused`, as the plan specifies, so the thread shows both rows.

## Known Stubs

None.

## Threat Flags

None. T-02-26 and T-02-28 are mitigated as planned: the label floor, code veto before Jev and Jev-can-only-hold have tests that script the model to try, and every reply stores its verdict.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Plan 02-15 can test the plain reasons and the gate shape in depth, add the owner alert for `FrontDeskUnavailable` (`->attempts`), and move owner replies onto `OutboundSender`.
- The verdict already carries `lifeSafety` and `asksForPerson` from Jev's reading (and `wants_person` in the details) for plans 02-20 and 02-21 to act on.
- `OutboundSender::whyBlocked()` is the place for the paused and opted-out checks.

## Self-Check: PASSED

- Created files exist: ComposeAutomatedText, OutboundSender, RunFrontDesk, GateOutcome, MessageKind, SendOrigin, DecideSendOrDraft, Verdict, the migration and ProcessInboundTest (all FOUND).
- Commits `f8f4078` and `e77c2a6` are ancestors of HEAD.
- Acceptance criteria re-run: DecideSendOrDraft contains neverOnItsOwn, findings(, ->classify( and TriageReading::read; OutboundSender contains the `reply:` lock, `block(` and `get()`; ProcessInbound contains RunFrontDesk and NoActiveVersion; Pest.php contains `Queue::fake([ProcessInbound::class])` and `function runQueuedFrontDeskJobs(`; the classifier-calls-empty assertion passes.
- `php artisan test --compact tests/Feature/FrontDesk tests/Feature/Quality/MoneyIsIntegerCentsTest.php` passes (69 tests); `php artisan test --compact` passes (596 tests).

---
*Phase: 02-text-front-desk*
*Completed: 2026-10-12*
