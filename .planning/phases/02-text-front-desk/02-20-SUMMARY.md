---
phase: 02-text-front-desk
plan: 20
subsystem: front-desk
tags: [laravel, livewire, pause, handoff, reply-cap, owner-alerts, send-rule]

requires:
  - phase: 02-text-front-desk
    provides: "PauseConversation, PausedReason, Conversation::isPaused(), RunFrontDesk pause check, RaiseOwnerAlert, FixedReplies::handoffReply(), SendFixedReply::holding(), Verdict::asksForPerson (plans 02-05, 02-14, 02-17, 02-19)"
provides:
  - "A fresh owner reply pauses the assistant (owner_replied) and the thread shows the handling banner"
  - "AsksForPerson: unambiguous request-for-a-person phrases (no bare human, no call me)"
  - "SendFixedReply::handoff(): the fixed handoff reply with disclosure and sign-off, always sent"
  - "Handoff on a phrase, the agent's wants_person, or Jev's asks_for_person at 0.85 or more: owner alerted at once, customer told, assistant paused"
  - "Reply cap (gate.reply_cap per_hour 6, per_day 20) checked before the agent runs: pause with reply_cap and alert"
affects: [02-21 life-safety line (never counted by the cap), 02-22 held drafts (sendDraft must not pause)]

actuals:
  tokens: 9600
  tasks: 2
  commits: 4
plan_head_before: cd8cd5d19e564f9da8caf4d0651e54635a272110
plan_head_after: 80f923342556df726e8807e6d470aa6a700bdbd2

tech-stack:
  added: []
  patterns:
    - "Alert, then send the fixed reply, then pause: the outbound path refuses automated texts once paused"
    - "One reusable query closure counted over two time windows for a cap"
    - "Computed Livewire property for a banner that follows a state set elsewhere"

key-files:
  created:
    - app/SendRule/AsksForPerson.php
    - tests/Unit/SendRule/AsksForPersonTest.php
    - tests/Feature/FrontDesk/HandoffTest.php
    - tests/Feature/FrontDesk/ReplyCapTest.php
  modified:
    - app/Actions/SendOwnerReply.php
    - app/Actions/SendFixedReply.php
    - app/Actions/RunFrontDesk.php
    - app/Livewire/ConversationThread.php
    - resources/views/livewire/conversation-thread.blade.php
    - app/SendRule/CodeChecks.php
    - config/gate.php
    - tests/Feature/FrontDesk/TakeOverTest.php
    - docs/sprints/sprint-1.md

key-decisions:
  - "Order in RunFrontDesk after the paused check: reply cap, then phrase match, then the agent, then wants_person, then the verdict's asksForPerson"
  - "A handoff alerts the owner first, sends the customer the handoff reply second and pauses last, because OutboundSender refuses automated sends while paused"
  - "CodeChecks::phrasePattern made public so AsksForPerson matches with the same normalisation and word boundaries instead of a copy"
  - "The cap counts agent-authored outbound texts that are queued, sent or delivered, with kind null or anything but life_safety; holding and handoff replies count"

patterns-established:
  - "Owner send pauses only after OutboundSender returned a message; a failed send never pauses and an already paused conversation keeps its reason"

requirements-completed: [GATE-03, GATE-02]

coverage:
  - id: D1
    description: "A fresh reply the owner types pauses the assistant with owner_replied and records who; a failed send does not pause; a take over keeps taken_over"
    requirement: GATE-03
    verification:
      - kind: integration
        ref: "tests/Feature/FrontDesk/TakeOverTest.php#pauses the assistant with reason owner_replied when the owner sends a fresh reply (D-25)"
        status: pass
      - kind: integration
        ref: "tests/Feature/FrontDesk/TakeOverTest.php#does not pause when the owner reply could not be sent"
        status: pass
    human_judgment: false
  - id: D2
    description: "The conversation thread shows the handling banner on a paused conversation (with the asked-for-a-person lead) and none on an active one"
    requirement: GATE-03
    verification:
      - kind: integration
        ref: "tests/Feature/FrontDesk/TakeOverTest.php#shows the handling banner on a paused thread and none on an active one (D-25)"
        status: pass
    human_judgment: true
    rationale: "Tests assert the banner words are in the rendered output; how the Filament section looks on the real page needs a person to look at it"
  - id: D3
    description: "A customer who asks for a person (phrase, agent wants_person, or Jev at 0.9) gets the handoff reply, the owner is alerted at once, and the conversation pauses; the agent's words are not sent"
    requirement: GATE-02
    verification:
      - kind: integration
        ref: "tests/Feature/FrontDesk/HandoffTest.php"
        status: pass
      - kind: unit
        ref: "tests/Unit/SendRule/AsksForPersonTest.php"
        status: pass
    human_judgment: false
  - id: D4
    description: "Jev's unsure asks_for_person holds the draft as classifier_unsure:asks_for_person and does not pause or hand off"
    requirement: GATE-02
    verification:
      - kind: integration
        ref: "tests/Feature/FrontDesk/HandoffTest.php#holds the draft and does not pause when Jev is unsure the customer asks for a person (D-47)"
        status: pass
    human_judgment: false
  - id: D5
    description: "Reply cap of 6 per hour and 20 per day stops the agent from running, pauses with reply_cap and alerts the owner; life-safety texts, drafts, failed sends and old texts are not counted"
    requirement: GATE-02
    verification:
      - kind: integration
        ref: "tests/Feature/FrontDesk/ReplyCapTest.php"
        status: pass
    human_judgment: false

duration: 25min
completed: 2026-10-12
status: complete
---

# Phase 02 Plan 20: Owner-reply pause, handoff to a person and reply cap Summary

**The assistant now steps back on its own when the owner types a reply, hands a customer who asks for a person to the owner (alert, fixed handoff text, pause), and stops at 6 texts an hour or 20 a day per conversation.**

## Performance

- **Duration:** about 25 min
- **Completed:** 2026-10-12
- **Tasks:** 2 (tracer plus one TDD task)
- **Files modified:** 13 (4 created, 9 modified)

## Accomplishments
- `SendOwnerReply::handle(..., ?User $by)` pauses with `owner_replied` after a sent reply; `ConversationThread::pausedBanner()` and the view show "You're handling this. Hand back to the assistant when done." with the "The customer asked for a person." lead where it applies.
- `AsksForPerson` phrase list (no bare "human" or "call me"), `SendFixedReply::handoff()`, and a three-way handoff trigger in `RunFrontDesk`: phrase before the agent runs, `wants_person` after, and the verdict's `asksForPerson` (Jev at 0.85 or more) in place of sending or drafting.
- Reply cap in `config/gate.php` and `RunFrontDesk`, checked right after the paused check and before the agent, with a `reply_cap` alert and pause.
- Full suite passes: 733 tests.

## Task Commits

1. **Task 1 (tracer): owner reply pauses the assistant and the thread says so**
   - RED `fffda51` (test): failing owner reply pause tests
   - GREEN `3ceff0f` (feat): owner replies pause the assistant
2. **Task 2 (TDD): handoff and reply cap**
   - RED `3ee76ef` (test): failing handoff and reply cap tests
   - GREEN `80f9233` (feat): hand off to a person and cap runaway conversations

**Plan metadata:** see the docs(02-20) commit that follows this file.

## TDD Gate Compliance

- **RED (task 1):** TakeOverTest additions failed on the planned assertions (`isPaused()` false after an owner send; banner text missing from the thread). Two guard tests (failed send does not pause, taken_over kept) passed already by design, since they protect existing behaviour. Semantic assessment: the failing tests failed for the intended reason.
- **RED (task 2):** `AsksForPersonTest` errored because the class did not exist (the expected failure for a new class); HandoffTest failed with "An unexpected prompt was received" and zero alerts; ReplyCapTest cases that expect a stop failed. Tests that describe the existing sending path (five texts still answers, unsure band holds) passed already by design. Semantic assessment: every behaviour-adding test failed on its planned assertion, none on a syntax or fixture fault. The `gsd check tdd-red-evidence` classifier has no Pest JSON adapter, so the evidence is the command output and this assessment, not a classifier verdict.
- **GREEN:** both targeted commands and the full suite pass. No REFACTOR commit was needed.

## Files Created/Modified
- `app/SendRule/AsksForPerson.php` - unambiguous phrases and `inText()`
- `app/Actions/SendFixedReply.php` - `handoff()`
- `app/Actions/RunFrontDesk.php` - reply cap, handoff triggers, `handOffToOwner()`
- `app/Actions/SendOwnerReply.php` - pause with `owner_replied` after a sent reply
- `app/Livewire/ConversationThread.php`, `resources/views/livewire/conversation-thread.blade.php` - `pausedBanner()` and the banner
- `config/gate.php` - `reply_cap.per_hour` 6, `per_day` 20
- `app/SendRule/CodeChecks.php` - `phrasePattern()` made public
- `tests/Unit/SendRule/AsksForPersonTest.php`, `tests/Feature/FrontDesk/HandoffTest.php`, `ReplyCapTest.php`, `TakeOverTest.php`
- `docs/sprints/sprint-1.md` - two walk-through lines

## Decisions Made
- Order inside `RunFrontDesk`: paused check, reply cap, phrase handoff, agent, `wants_person`, send rule, `asksForPerson`. The cap and phrase checks sit before the agent so they cost no model call.
- The handoff alerts, sends, then pauses, because the outbound path refuses automated texts once paused.
- The cap counts holding and handoff replies as agent texts (they are agent-authored); only `life_safety` is exempt, as the plan says.
- Task 1 banner is a Filament section rendering the escaped sentence from `pausedBanner()`.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Made `CodeChecks::phrasePattern()` public**
- **Found during:** Task 2 (AsksForPerson)
- **Issue:** The plan says to use the same normalisation and word boundaries as CodeChecks, but the pattern builder was private, so the only other way was a copy that could drift.
- **Fix:** Changed the visibility to `public static` with a doc line saying why.
- **Files modified:** `app/SendRule/CodeChecks.php` (not in the plan's file list)
- **Verification:** `CodeChecksTest` and the full suite pass.
- **Committed in:** `80f9233`

---

**Total deviations:** 1 auto-fixed (1 blocking)
**Impact on plan:** Removes duplicated matching code; no behaviour change to the veto lists.

## Issues Encountered
None. The unsure-band behaviour needed no code, as planned: `TriageReading` already holds it.

## Known Stubs
None.

## Threat Flags
None. The reply cap mitigates T-02-38; T-02-40 is accepted as planned.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Ready for 02-21 (life-safety line): the cap query already ignores `kind = life_safety`, and `PausedReason::LifeSafety` exists.
- 02-22 must send approved drafts through a path that does not call `SendOwnerReply::handle()`, or the approval would pause the conversation (the plan's `sendDraft` note).
- The agent's own words are not stored when a handoff happens (flagged assumption); the owner reads the thread.

## Self-Check: PASSED

- Created files exist: `app/SendRule/AsksForPerson.php`, `tests/Unit/SendRule/AsksForPersonTest.php`, `tests/Feature/FrontDesk/HandoffTest.php`, `tests/Feature/FrontDesk/ReplyCapTest.php`.
- Commits `fffda51`, `3ceff0f`, `3ee76ef`, `80f9233` are on the branch; 4 commits since `plan_head_before`.
- Both `<automated>` commands exit 0; full suite 733 passed.

---
*Phase: 02-text-front-desk*
*Completed: 2026-10-12*
