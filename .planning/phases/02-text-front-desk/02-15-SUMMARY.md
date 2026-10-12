---
phase: 02-text-front-desk
plan: 15
subsystem: send-rule
tags: [laravel, send-rule, outbound, owner-alerts, pest-arch, customer-card, pest]

requires:
  - phase: 02-text-front-desk
    provides: ComposeAutomatedText, DecideSendOrDraft, Verdict, OutboundSender, RunFrontDesk (02-14); RaiseOwnerAlert and AlertReason::FrontDeskFailed (02-10); FrontDeskUnavailable (02-13)
provides:
  - Verdict::plainReasonFor() with one short owner-readable sentence for every reason code (D-07)
  - Customer card labels by author - "Reply from front desk" and "Reply from owner" (Pitfall 8)
  - SendOwnerReply sending through OutboundSender (same public API, same reply lock) (Pitfall 15)
  - RunFrontDesk raising a front_desk_failed owner alert when every model fails or the channel refuses the send (AGNT-04)
  - Arch rule - TextChannel is used only by OutboundSender, SendOwnerAlert, App\Channels, App\Providers and Tests (T-02-27)
  - Tests - DisclosureTest, GateVerdictTest, FrontDeskFailureTest; shared front desk test helpers in tests/Pest.php
affects: [02-16 and 02-17 alerts and holding reply, 02-20 and 02-21 life safety and hand-off, 02-22 draft approval, paused and opted-out checks in OutboundSender::whyBlocked]

plan_head_before: 7fef614b3868c5baf060e4976d9134a5bbfdbac7
plan_head_after: 3ac6e49fdb7def14166183867ad1a66f3c35191d

actuals:
  tokens: 11155
  tasks: 2
  commits: 4

tech-stack:
  added: []
  patterns:
    - "Two-step match on a reason code (kind, then detail) with a default arm, so a new code still shows a sentence"
    - "OutboundSender::whyBlocked() words its reasons for whoever is sending, so one set of checks serves the owner's reply box and the agent"
    - "A public const string on the sender (NOT_SENT) lets the caller tell a channel failure from a refusal without a new exception class"
    - "Pest arch rule that guards a doorway: toOnlyBeUsedIn on an interface"

key-files:
  created:
    - tests/Feature/FrontDesk/DisclosureTest.php
    - tests/Feature/FrontDesk/GateVerdictTest.php
    - tests/Feature/FrontDesk/FrontDeskFailureTest.php
  modified:
    - app/SendRule/Verdict.php
    - app/Records/CustomerCard.php
    - app/Actions/SendOwnerReply.php
    - app/Actions/OutboundSender.php
    - app/Actions/RunFrontDesk.php
    - tests/Pest.php
    - tests/Feature/FrontDesk/ProcessInboundTest.php
    - tests/Feature/Inbound/OwnerReplyTest.php
    - tests/Unit/ArchitectureTest.php
    - docs/sprints/sprint-1.md

key-decisions:
  - "Plain reasons are one sentence per cause, not per check: code:price, label:price, classifier:price and classifier_intent:price_question all read 'Held: asks about price'"
  - "classifier:life_safety reads 'Held: may be urgent' and classifier:asks_for_person reads 'Held: may want to talk to a person', because TriageReading really produces those two codes and a generic fallback would hide them from the owner"
  - "OutboundSender::whyBlocked() returns the owner's wording when the origin is Owner, so SendOwnerReply::whyCannotReply() delegates and every existing refusal sentence is unchanged"
  - "A channel failure is told apart from a refusal by OutboundSender::NOT_SENT, not a new exception class (the plan creates no new classes)"
  - "A channel failure leaves the failed message on the record and alerts the owner; it does not also store a draft. Only a refusal (lock timeout, closed conversation) becomes a send_refused draft"

patterns-established:
  - "Anything that must hold for every sender goes in OutboundSender; callers keep only their own input checks"
  - "Raise owner alerts only through RaiseOwnerAlert::handle(); the subject is the customer's last text so a repeat for the same message is skipped"

requirements-completed: [GATE-01, GATE-02, AGNT-04]

coverage:
  - id: D1
    description: "The AI disclosure opens the first automated text of each conversation (also when the owner texted first, and again in a new conversation after a close) and only that one; the sign-off ends every agent text"
    requirement: GATE-02
    verification:
      - kind: integration
        ref: "tests/Feature/FrontDesk/DisclosureTest.php"
        status: pass
    human_judgment: false
  - id: D2
    description: "A reply containing a never_say phrase is never sent; it is held as code:never_say with the plain reason 'Held: uses a phrase you never say'"
    requirement: GATE-02
    verification:
      - kind: integration
        ref: "tests/Feature/FrontDesk/DisclosureTest.php#it holds a reply that says a never_say phrase and sends nothing (GATE-02)"
        status: pass
    human_judgment: false
  - id: D3
    description: "Every reply's verdict is stored on its message with inputs, checks, thresholds, reply model, failed provider attempts and a plain reason; Jev is asked once per reply about the burst text and the agent's words only"
    requirement: GATE-01
    verification:
      - kind: integration
        ref: "tests/Feature/FrontDesk/GateVerdictTest.php"
        status: pass
    human_judgment: false
  - id: D4
    description: "The customer card labels front desk replies 'Reply from front desk' and owner replies 'Reply from owner'"
    verification:
      - kind: integration
        ref: "tests/Feature/FrontDesk/GateVerdictTest.php#it labels a front desk reply and an owner reply differently on the customer card (Pitfall 8)"
        status: pass
    human_judgment: false
  - id: D5
    description: "Owner replies leave through OutboundSender with the shared reply lock, and an arch rule keeps TextChannel out of every other class"
    requirement: GATE-01
    verification:
      - kind: unit
        ref: "tests/Unit/ArchitectureTest.php#only the outbound chokepoint and the alert sender send texts"
        status: pass
      - kind: integration
        ref: "tests/Feature/Inbound/OwnerReplyTest.php"
        status: pass
    human_judgment: false
  - id: D6
    description: "A front desk that cannot answer, or whose send the channel refused, tells the owner (front_desk_failed), texts the customer nothing and does not throw; a send blocked by the owner's lock becomes a send_refused draft"
    requirement: AGNT-04
    verification:
      - kind: integration
        ref: "tests/Feature/FrontDesk/FrontDeskFailureTest.php"
        status: pass
    human_judgment: false
  - id: D7
    description: "A live reply from /dev/chat with real model keys starts with the AI disclosure and ends with the sign-off, and a price question produces a draft instead of a reply"
    verification: []
    human_judgment: true
    rationale: "Needs real ANTHROPIC_API_KEY and JEV_API_KEY and a running dev server; the scripted tests cover the same rules but not the live providers"

duration: 10min
completed: 2026-10-12
status: complete
---

# Phase 2 Plan 15: Disclosure, Verdict Record and One Outbound Path Summary

**Plain-word hold reasons for every verdict code, front desk vs owner labels on the customer card, owner replies sent through the same `OutboundSender` lock as the front desk (guarded by an arch rule on `TextChannel`), and a `front_desk_failed` owner alert whenever the front desk cannot answer or cannot send**

## Performance

- **Duration:** ~10 min
- **Started:** 2026-10-12T02:36:40Z
- **Completed:** 2026-10-12T02:46:00Z
- **Tasks:** 2
- **Files modified:** 13

## Accomplishments

- `Verdict::plainReasonFor()` now returns one short sentence per cause (`Held: asks about price`, `Held: makes a promise`, `Held: could not send right now`, and so on), stored in `gate.reason.plain` on every held draft.
- The customer card says `Reply from front desk (sms, sent)` / `Front desk reply:` for agent texts and keeps `Reply from owner` for the owner, so the agent reading the card as history cannot mistake one for the other.
- `SendOwnerReply` keeps its public API (`handle()`, `whyCannotReply()`, `MAX_LENGTH`) and every refusal sentence, and now sends through `OutboundSender::send(..., SendOrigin::Owner)`; it no longer holds a `TextChannel` or its own lock.
- `RunFrontDesk` raises `AlertReason::FrontDeskFailed` through `RaiseOwnerAlert` when every provider fails ("Front desk could not answer") or the channel refuses the send ("The reply could not be sent"), leaves the failed message on the record, and never throws.
- A Pest arch rule allows `App\Channels\TextChannel` only in `OutboundSender`, `SendOwnerAlert`, `App\Channels`, `App\Providers` and `Tests`.
- Disclosure once per conversation, owner-first, new-conversation, sign-off and `never_say` veto behaviours now have their own tests; the verdict record (including the fallback provider path) and the one-Jev-call-per-reply rule are pinned in `GateVerdictTest`.

## Task Commits

1. **Task 1 (tracer): disclosure, sign-off, never_say, plain reasons, verdict record, card labels**
   - RED `e416560` (test) - failing disclosure, verdict and card label tests
   - GREEN `1b0144c` (feat) - plain reasons, card labels, walk-through log
2. **Task 2 (tdd): owner replies on the chokepoint and front desk failures alert the owner**
   - RED `a8b5347` (test) - failing chokepoint and front desk failure tests
   - GREEN `3ac6e49` (feat) - one outbound path for owner and front desk

**Plan metadata:** recorded in the `docs(02-15)` commit that follows this summary.

Tracer feedback gate: Task 1's `<verify>` carries only an automated command and human-verify mode is `end-of-phase`, so it was re-run end to end after the commit (140 tests passed) and expansion continued.

## TDD Gate Compliance

Both tasks produced a `test(02-15)` commit before the matching `feat(02-15)` commit. No refactor commit was needed.

**RED, Task 1** (`e416560`): `vendor/bin/pest tests/Feature/FrontDesk/GateVerdictTest.php --log-junit ...` exited 1 with 27 of 30 tests failing. `gsd_run check tdd-red-evidence` returned `RED_EVIDENCE_OK` (`target_test_failed`) for the card label test. Semantic assessment: the target test ran and failed on the planned assertion, because the card printed `Reply from owner (sms, sent)` for the agent's reply instead of `Reply from front desk (sms, sent)`; the dataset rows failed on the old wordier reason sentences. The disclosure and verdict-shape tests already passed because plan 02-14 had built that behaviour; they are kept as regression cover.

**RED, Task 2** (`a8b5347`): the same command over `FrontDeskFailureTest`, `OwnerReplyTest` and `ArchitectureTest` exited 1 with 3 failures and `RED_EVIDENCE_OK` for the "every model failed" test. Semantic assessment: it failed on `OwnerAlert::query()->count()` being 0 where 1 was planned (no alert raised), the channel-refusal test failed on 2 outbound messages where 1 was planned (a stray draft beside the failed message), and the arch rule failed because `SendOwnerReply` imported `TextChannel`. My first version of two of these tests used `sole()` and errored instead of failing an assertion; I rewrote them to assert on counts so the RED was a genuine assertion failure before committing.

## Files Created/Modified

- `app/SendRule/Verdict.php` - `plainReasonFor()` rewritten as a two-step `match` returning the owner's sentences
- `app/Records/CustomerCard.php` - `replyParts()` labels by message author
- `app/Actions/SendOwnerReply.php` - thin wrapper: validates the owner's words, delegates to `OutboundSender`
- `app/Actions/OutboundSender.php` - `whyBlocked()` is worded for the owner when the owner sends; `NOT_SENT` constant
- `app/Actions/RunFrontDesk.php` - raises the `front_desk_failed` alert for both failure paths
- `tests/Feature/FrontDesk/DisclosureTest.php`, `GateVerdictTest.php`, `FrontDeskFailureTest.php` - new
- `tests/Pest.php` - the four front desk test helpers moved here from `ProcessInboundTest.php`
- `tests/Feature/Inbound/OwnerReplyTest.php`, `tests/Unit/ArchitectureTest.php` - new assertions
- `docs/sprints/sprint-1.md` - three walk-through lines (waiting locks, two-step match, arch rule on a doorway)

## Decisions Made

See `key-decisions` above. The main one: a channel failure keeps only the failed message and alerts the owner, while a plain refusal (such as the owner holding the lock) still becomes a `send_refused` draft.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Moved shared front desk test helpers into tests/Pest.php**
- **Found during:** Task 1 (writing the new test files)
- **Issue:** `frontDeskCustomerTexts()`, `textsSentToCustomers()`, `composedFirstReply()` and `theAgentMessage()` lived in `ProcessInboundTest.php`, so `DisclosureTest` or `GateVerdictTest` would fail with "undefined function" when run on their own, as the plan's narrow verify commands do.
- **Fix:** Moved them to `tests/Pest.php` next to the other shared helpers and removed the now-unused imports from `ProcessInboundTest.php`.
- **Files modified:** `tests/Pest.php`, `tests/Feature/FrontDesk/ProcessInboundTest.php`
- **Verification:** `ProcessInboundTest` still passes (28 tests) and the new files pass when run alone.
- **Committed in:** `e416560`

**2. [Rule 2 - Missing critical] Plain reasons for classifier:life_safety and classifier:asks_for_person**
- **Found during:** Task 1 (reading `TriageReading`)
- **Issue:** The plan's list says "any other code reads 'needs your judgment'", but `TriageReading` really produces `classifier:life_safety` and `classifier:asks_for_person`, and the owner should not see a safety hold described as a generic judgment call.
- **Fix:** Mapped them to `Held: may be urgent` and `Held: may want to talk to a person`, matching the other urgent and person-related rows.
- **Files modified:** `app/SendRule/Verdict.php`, `tests/Feature/FrontDesk/GateVerdictTest.php`
- **Committed in:** `1b0144c`

**3. [Rule 3 - Blocking] OutboundSender::whyBlocked() worded for the owner**
- **Found during:** Task 2
- **Issue:** The plan has `SendOwnerReply::whyCannotReply()` return `OutboundSender::whyBlocked(..., SendOrigin::Owner)`, but that method returned the automated wording ("This conversation is closed."), which would break the existing OwnerReplyTest sentences ("...Reopen it to reply.", "No phone number on file. Reply by email: ...").
- **Fix:** `whyBlocked()` now returns the owner's sentences when the origin is `Owner`, and the automated sentences otherwise.
- **Files modified:** `app/Actions/OutboundSender.php`
- **Verification:** all OwnerReplyTest tests pass unchanged.
- **Committed in:** `3ac6e49`

### Plan items that needed no change

- `ComposeAutomatedText.php`, `DecideSendOrDraft.php` were listed in `files_modified` but plan 02-14 already satisfied their behaviours (disclosure rule, sign-off, never_say veto, verdict keys), so only tests were added for them.
- The plan text says the failure note should list the providers tried. The existing note text `Front desk could not answer: every model failed` is asserted exactly by `ProcessInboundTest` and by this plan's behaviour list, so it was left as is; the attempts are still available on `FrontDeskUnavailable->attempts`.

---

**Total deviations:** 3 auto-fixed (2 blocking, 1 missing critical)
**Impact on plan:** All three were needed for the plan's own behaviours to hold. No scope creep.

## Issues Encountered

None beyond the RED-test rewrite described under TDD Gate Compliance.

## User Setup Required

None - no external service configuration required. The live `/dev/chat` check (deliverable D7) needs `ANTHROPIC_API_KEY` and `JEV_API_KEY` in `.env` and is left for the end-of-phase review.

## Known Stubs

None. Scan of the changed files found no placeholder data or empty values that reach output.

## Threat Flags

None. This plan removes a direct `TextChannel` user (`SendOwnerReply`) and adds an arch guard, so the send surface is smaller than before (T-02-27 mitigated, T-02-29 and T-02-30 mitigated as planned).

## Next Phase Readiness

- `OutboundSender::whyBlocked()` is the single place for the paused and opted-out checks; both the reply box and the front desk will pick them up.
- Owner alerts for held drafts (`draft_waiting`) and for the other `AlertReason` cases are still to come in later plans; `Verdict` plain reasons are ready to be used as their `why` text.
- Full suite: 637 tests passing.

---
*Phase: 02-text-front-desk*
*Completed: 2026-10-12*

## Self-Check: PASSED

All created files exist, all four task commits (`e416560`, `1b0144c`, `a8b5347`, `3ac6e49`) are ancestors of HEAD, and the full suite passes.
