---
phase: quick-261017-bz2
plan: 01
subsystem: front-desk
tags: [front-desk, fixed-reply-order, complaint, map, phase-02.1]
requires: [quick-261016-t5v]
provides:
  - "replyWhileWaitIsCurrent(): one fixed line during a current wait, complaint reply for a complaint, else added-info"
affects: [app/Actions/RunFrontDesk.php, map/]
key-files:
  modified:
    - app/Actions/RunFrontDesk.php
    - tests/Feature/FrontDesk/FrontDeskFailureTest.php
    - tests/Feature/FrontDesk/AnsweredDraftTest.php
    - tests/Feature/FrontDesk/FrontDeskInvariantTest.php
    - map/CLAUDE.md
    - map/processes/answer-inbound.md
    - map/objects/messages/message-and-verdict.md
    - map/objects/folder/agent-file-and-version.md
    - map/objects/conversations/conversation-and-pause.md
    - map/objects/contacts/contact-opt-out.md
    - map/objects/intake/intake-answer.md
    - .planning/phases/02.1-faster-answers/02.1-REVIEW-DISPOSITION.md
    - .planning/phases/02.1-faster-answers/02.1-CONTEXT.md
decisions:
  - "Item 6 complaint test: reading()->complaint() (no verdict exists because the agent never answered)"
  - "Item 7 complaint test: the verdict's reason code in SendFixedReply::COMPLAINT_CODES (covers Jev's reading, the agent's label and the words check's intent)"
requirements: [ANSR-04, AGNT-04]
status: complete
commits: 4
plan_head_before: c907ed430450e7e72bd49d62c36c2d228f23101c
plan_head_after: d49b883d929a56e8043b1ee2e0ddf0991ad11162
actuals:
  tokens: 60000
  tasks: 3
  commits: 4
---

# Phase quick-261017-bz2 Plan 01: Fixed-reply order during a current wait Summary

A complaint that arrives while the customer's earlier wait is still current now gets the owner's complaint reply once, not the added-info line, on the two paths that still chose added-info: every model down (item 6) and a held text with no live waiting draft (item 7). Owner alerts are unchanged.

## Commits (local, on gsd/phase-02.1-faster-answers, nothing pushed)

| Commit | Task | What |
|---|---|---|
| eb09239 | 1 (RED) | Failing tests: item 6 test changed, item 7 dataset test added, D-46 matrix edge and named test added |
| a598477 | 1 (GREEN) | `replyWhileWaitIsCurrent()` in RunFrontDesk, used by `replyWhenFrontDeskCannotAnswer()` and `storeDraft()` |
| 4c17347 | 2 | Map cards: citations re-pointed, untrue sentences fixed, stamps 2026-10-17 @ a598477 |
| d49b883 | 3 | Disposition items 6 and 7 fixed; 02.1-CONTEXT.md records Ian's 2026-10-17 decision |

RED was confirmed before the fix: the three new or changed cases failed because an `added_info_reply` went out where a `complaint_holding` was expected (item 6: ComplaintHolding count 0 vs 1; item 7: body "Got it, I've passed that along to Mike with your question."; matrix: both new edge breaches). The docs commit for this SUMMARY, STATE.md and PLAN.md is left to the orchestrator.

## Tests changed or added

- `tests/Feature/FrontDesk/FrontDeskFailureTest.php`: CHANGED the item 6 complaint test (renamed, not deleted). It pinned the old order (added-info once, ComplaintHolding 0). It now expects two texts to Dana, HoldingReply 1, ComplaintHolding 1, AddedInfoReply 0, newest kind `complaint_holding` with `meta.parts` `['complaint_line']` and the owner's complaint text, one live draft, a Complaint alert and a front_desk_failed alert, and `holdingAlreadySent()` still true. Imports `SendFixedReply` and `FixedReplies`.
- `tests/Feature/FrontDesk/AnsweredDraftTest.php`: ADDED a two-row dataset test for item 7 ("Jev reads a complaint" gives `classifier:complaint`; "the agent labels it a complaint" gives `label:complaint`). Draft closed as answered with no `answered_by`, then Dana's complaint: second text is the complaint reply, kind ComplaintHolding, parts `['complaint_line']`, AddedInfoReply 0, HoldingReply 1, a Complaint alert on the live draft. Imports `FixedReplies`.
- `tests/Feature/FrontDesk/FrontDeskInvariantTest.php`: ADDED a new edge in `invariantBreaches()` (agent asked, every model failed, Jev saw a complaint: a complaint reply must go out and no added-info line), ADDED a named test (`waiting`, `complaint`, `agentFail`), and CHANGED the never-silent docblock sentence that said every model down sends the added-info reply.
- Controls untouched and passing: FrontDeskFailureTest item 6 without a complaint and complaint with no wait, AnsweredDraftTest close with no `answered_by` (non-complaint), HoldingReplyTest, WaitingCustomerRepliesTest.

## Verification

- Full suite `php artisan test --compact` (SQLite): 1680 passed, 14749 assertions, 0 failed.
- `vendor/bin/pint --dirty --format agent`: passed.
- AlertReason.php, MessageKind.php, SendFixedReply.php, RaiseOwnerAlert.php, SendCombinedReply.php and ReplyParts.php are byte-identical to c907ed4.
- Map verify (Task 2) and disposition/context verify (Task 3) both pass. map/VERIFICATION.md is unchanged, and every file it cites is unchanged since cd1bc79.
- PostgreSQL run not done (CI covers it, DEC-06).

## Chosen complaint condition

- Item 6 (`replyWhenFrontDeskCannotAnswer()`): `reading()->complaint()`, because the agent never answered so there is no verdict.
- Item 7 (`storeDraft()` holding branch): `in_array($verdict->reasonCode, SendFixedReply::COMPLAINT_CODES, true)`, the same test `holding()`, `alertReasonFor()` and `answerWhileDraftWaits()` already use.

## What a customer or owner will now see differently

- A customer who is already waiting on the owner and texts a complaint gets the owner's complaint reply (once) instead of "Got it, I've passed that along to Mike with your question", when every model is down or no agent answers texts, and when their text is held as a complaint with no draft waiting (for example after a draft was closed as answered with no answering id).
- Still true: no second full holding reply; a non-complaint text in those states still gets the added-info line.
- The owner sees no change: the complaint alert (on the folder's owner_alert timing) and front_desk_failed are raised exactly as before.
- Because the complaint reply starts a wait, a later held text still gets added-info or the complaint reply, never a second holding reply.

## Deviations from Plan

None. Plan executed as written. Notes:
- The step-16 and step-14 citation `:384-387` (now `:386-391`, the whole `if ($answersWaiting && ...)` block) was tightened because the original range ended on a blank line.
- Two ranges whose end line sat inside a changed hunk were mapped by hand (AgentReply possible_danger parse, RunFrontDesk blank-words hold branches, RaiseOwnerAlert::forBusiness).

## Walk-through log

No new Laravel concept appeared (a private helper and a `DomainException` catch with `report()` are already in the log), so `docs/sprints/sprint-1.1.md` was not edited.

## Follow-ups

- `docs/sprints/sprint-1.1.md` Notes still describe open items 6 and 7 as awaiting Ian. Left for Ian, outside this request.
- Open items 4, 5 and 8 remain open and unchanged.

## Known Stubs

None.

## Threat Flags

None. No new endpoints, auth paths or schema.

## Self-Check: PASSED

- Commits eb09239, a598477, 4c17347 and d49b883 exist on gsd/phase-02.1-faster-answers.
- Changed files exist; app/Actions/RunFrontDesk.php contains `function replyWhileWaitIsCurrent`.
