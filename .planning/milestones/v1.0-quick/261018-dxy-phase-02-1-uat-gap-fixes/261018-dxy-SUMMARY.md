---
phase: quick-261018-dxy
plan: 01
subsystem: front-desk
tags: [complaint-reply, price-services-prompt, life-safety-gate, uat-gaps, map]
requires:
  - phase: 02.1-faster-answers
    provides: UAT gaps G-02.1-1, G-02.1-2, G-02.1-3 and decisions D-34, D-46, D-04, D-08
provides:
  - complaint reply at most once per wait on all three paths (SendFixedReply::complaintReplyAlreadySent)
  - one price_services rule for both models inside FrontDeskPrompt::RULES
  - gate.customer_reading stored on every safety line Jev's reading or a words check sends
affects: [02.2 discuss, phone design pass, map cards]
tech-stack:
  added: []
  patterns: [shared private wait-ended check (nothingAnsweredSince), optional parameter with spread into the gate array]
key-files:
  created: []
  modified:
    - app/Actions/SendFixedReply.php
    - app/Actions/RunFrontDesk.php
    - app/Actions/SendLifeSafetyLine.php
    - app/Ai/FrontDeskPrompt.php
    - tests/Feature/FrontDesk/WaitingCustomerRepliesTest.php
    - tests/Feature/FrontDesk/FrontDeskFailureTest.php
    - tests/Feature/FrontDesk/AnsweredDraftTest.php
    - tests/Feature/FrontDesk/FrontDeskInvariantTest.php
    - tests/Feature/FrontDesk/FrontDeskPromptTest.php
    - tests/Feature/FrontDesk/CustomerReadingTest.php
    - .planning/phases/02.1-faster-answers/02.1-UAT.md
    - .planning/phases/02.1-faster-answers/02.1-CONTEXT.md
    - .planning/WINDOWS.md
    - docs/sprints/sprint-1.1.md
    - map/CLAUDE.md
    - map/processes/answer-inbound.md
    - map/objects/messages/message-and-verdict.md
    - map/objects/folder/agent-file-and-version.md
    - map/objects/conversations/conversation-and-pause.md
    - map/objects/contacts/contact-opt-out.md
    - map/objects/intake/intake-answer.md
key-decisions:
  - "The complaint reply is sent only when no complaint_holding went out in the current wait; a refused send does not count, and an owner answer ends the wait so the next complaint gets it again"
  - "Alerts are untouched: the five named files are byte-identical to d074e3b and no raiseOwnerAlert line in RunFrontDesk.php changed"
  - "The price rule sits inside the existing price_services line, so FrontDeskPrompt.php is still 327 lines and no map citation moved"
patterns-established:
  - "Same wait-ended logic for two questions: holdingAlreadySent and complaintReplyAlreadySent both call nothingAnsweredSince"
duration: about 70 minutes
completed: 2026-10-18
status: complete
commits: 5
plan_head_before: d074e3bdda1580d20b22314389b56691b2ab203d
plan_head_after: a616fc5ac185a14d156f7ae3e098765ee15b5a56
actuals:
  tokens: 43000
  tasks: 3
  commits: 5
---

# Phase quick-261018-dxy Plan 01: Phase 02.1 UAT gap fixes Summary

The complaint reply now goes out at most once per wait on every path, both reply models follow one price_services rule (8 live calls, all passed), and a safety line sent by Jev's reading or a words check keeps the reading that sent it.

## Commits

All local on `gsd/phase-02.1-faster-answers`. Nothing pushed.

| Task | Commit | What |
|---|---|---|
| 1 RED | `c025681` | test: complaint reply at most once per wait (G-02.1-1) |
| 1 GREEN | `665086b` | fix: `complaintReplyAlreadySent()` and the two reply choices in `RunFrontDesk` |
| 2 RED | `c79a196` | test: price_services rule, reading on the safety line (G-02.1-2, G-02.1-3) |
| 2 GREEN | `0a48008` | fix: rule in the price_services line; `SendLifeSafetyLine` stores `gate.customer_reading`; four classifier calls pass the reading |
| 3 | `a616fc5` | docs: UAT gaps, CONTEXT note, WINDOWS 11 to 17, map cards, walk-through log line |

## Test counts

- Task 1 narrow set (WaitingCustomerReplies, FrontDeskFailure, AnsweredDraft, HoldingReply, CoverBothDraft, HeldCodeAnswer): 191 passed, 1,166 assertions.
- D-46 matrix (`FrontDeskInvariantTest`): 20 passed, 7,303 assertions. Still 1,440 combinations in 8 slices of 180; the new check runs on the combinations that already ran.
- Task 2 set (FrontDeskPrompt, CustomerReading, LifeSafety, PossibleDanger, JevDown): 130 passed, 710 assertions.
- Full suite before the records commit: `php artisan test --compact` gave 1,693 passed, 14,871 assertions, 0 failures. Pint ran after each code task and before the records commit and found nothing to change. PostgreSQL run not done locally (CI covers it, DEC-06).

RED runs failed on the intended assertions: the three waiting-draft tests (and every dataset row) on the reply text, the every-model-down test on the `ComplaintHolding` count (2 instead of 1), the no-answered_by rows on the reply text, the three prompt rows on the missing sentences, and both reading tests on the missing `gate.customer_reading`. One caveat: in the every-model-down RED run the first failing assertion was the reply count, which stops the chain, so I did not separately watch the alert counts pass before the fix. They pass after it with alert code untouched, and the test asserts 2 Complaint and 2 FrontDeskFailed alerts.

## Tests changed and added (none deleted)

Modified:
- `WaitingCustomerRepliesTest`: the D-34 complaint-wins-over-check-in test is renamed and now expects the check-in reply for the second text (3 texts to Dana, `CheckInReply` 1, `ComplaintHolding` 1, one CheckIn alert, a Complaint alert).
- `FrontDeskInvariantTest`: `invariantRun()` and `invariantCoverBothRun()` return `complaintRepliesBefore`; new helper `invariantComplaintRepliesSent()`; new D-46 check "two complaint replies went out in one wait"; the ANSR-04 adjacency edge and the could-not-answer edge now apply to the first complaint of a wait only; docblocks reworded.

Added:
- `WaitingCustomerRepliesTest`: next-line dataset (3 rows), new-wait test.
- `FrontDeskFailureTest`: second complaint with every model down gets the added-info reply.
- `AnsweredDraftTest`: second complaint after drafts closed with no `answered_by` (2 rows).
- `FrontDeskPromptTest`: three-sentence dataset on RULES, the instructions and the price_services line; plain-words test for that line.
- `CustomerReadingTest`: reading stored when the reading alone pauses (no agent call); reading stored when a words check sends the line.

No test outside the named files needed changing.

## Live check of the price rule (G-02.1-2)

Run after the rule was committed (`0a48008`). Probe was a throwaway script in the session scratchpad, on a scratchpad copy of the dev SQLite file (asserted before any query), with Event, Queue and Bus faked. Same prompt build and parsing as `RunFrontDeskAgent`. Nothing sent. `git status` showed nothing from the probe; copy deleted. The folder in the copy had a live version (chain: anthropic claude-sonnet-5-5, then openai gpt-6.1-sol).

| # | Model | Text | Run | price_services | Label | Reply | Result |
|---|---|---|---|---|---|---|---|
| 1 | claude-sonnet-5-5 | 3 mini split | 1 | [] | other | "Thanks for asking about a mini-split install. I don't have install pricing for that, so the owner will follow up with you. Which town is the install in, so we can make sure it's in our service area?" | pass |
| 2 | claude-sonnet-5-5 | 3 mini split | 2 | [] | other | "Thanks for asking! Our files don't list mini-split installs, so I'll have the owner follow up with you on that. Is this for a new spot in your home, or to replace a system you already have?" | pass |
| 3 | gpt-6.1-sol | 3 mini split | 1 | [] | price | "The owner will follow up about mini-split installation options and pricing." | pass |
| 4 | gpt-6.1-sol | 3 mini split | 2 | [] | price | "The owner will follow up about mini-split installation and pricing." | pass |
| 5 | claude-sonnet-5-5 | 9a diagnostic | 1 | [] | intake_question | "Happy to help with that. Is the diagnostic for your AC or your furnace?" | pass |
| 6 | claude-sonnet-5-5 | 9a diagnostic | 2 | [] | intake_question | "Happy to help with that. Is the diagnostic for your AC or your furnace?" | pass |
| 7 | gpt-6.1-sol | 9a diagnostic | 1 | [] | intake_question | "Is the diagnostic for an AC system or a furnace?" | pass |
| 8 | gpt-6.1-sol | 9a diagnostic | 2 | [] | intake_question | "Is the diagnostic for an AC system or a furnace?" | pass |

Verdict: 8 of 8 pass by the gap's own truth (text 3 gets `[]`; 9a gets `[]` and a which-system question, no defer-to-the-owner). The fallback no longer names `system-replacement` for a mini split and no longer defers 9a. Recorded in 02.1-UAT.md (test 2 note and the gap's `live_check: passed`) and WINDOWS entry 12.

## What a customer or owner now sees differently

- A customer who is upset gets the owner's complaint reply once per wait. Their next complaint in the same wait gets the check-in reply if it is a check-in, otherwise "Got it, I've passed that along". After the owner answers, the next complaint gets the complaint reply again.
- The owner's complaint alerts are exactly as before, one per complaint text.
- A price question about a service the files do not list, or that fits more than one service, no longer sends a guessed price sentence from either model; the customer is asked which system.
- Every 911 line Jev's reading or a words check sends now has the reading that sent it on its message (`gate.customer_reading`).

## Deviations from Plan

None. The plan was executed as written. Two small additions beyond the named files, both required by the plan's own rules:

- `docs/sprints/sprint-1.1.md` got one walk-through-log line (optional parameter with `= null`, and spreading a conditional array into the gate), because CLAUDE.md requires a line for the first appearance of a concept and the plan said to add one only if a new concept appeared. It was committed with the Task 3 docs commit.
- In `answer-inbound.md` one pre-existing citation resolved to the wrong file under the "`:N` continues the last named file" rule (step 16, `:1454-1464` after `SendFixedReply.php:34`); it was a `RunFrontDesk.php` range, so I named the file explicitly (`RunFrontDesk.php:1464-1474`).

## Judgment calls for Ian

1. A check-in reply chosen for a repeat complaint carries its own check-in alert, as every check-in reply does (D-28, D-38). So the second text in the changed D-34 test raises one CheckIn alert beside the Complaint alert. If you want repeat complaints to alert only as complaints, that is a small change in `sendCheckInReply()`'s caller.
2. A repeat complaint that rides with a passing easy answer gets the partial holding line in added-info's place, by D-36's existing order for any text. Rare, and not covered by a test.
3. Every reason code in the next-line dataset matched the plan (`classifier:complaint`, `label:complaint`, `classifier:complaint`). Nothing differed.
4. `holding()` on the last branch of `answerWhileDraftWaits` still gets the verdict's reason code, as the plan ordered. If the complaint reply was already sent and `holdingAlreadySent()` were false at the same moment, that branch could still send a complaint reply. I could not construct that state (a sent complaint reply is itself a wait-starter), so I left it as planned.
5. Task 1's `complaintReplyAlreadySent()` looks only at the newest `complaint_holding` message. A holding reply that follows a complaint in the same wait does not hide it.

## Known Stubs

None added.

## Threat Flags

None. No new endpoints, auth paths or schema. `gate.customer_reading` is an extra JSON key on an existing message column. The live probe used real provider keys loaded by Laravel (never read or printed), the script lived only in the session scratchpad, and no result with a key in it was recorded.

## Flag for Ian: /gsd-audit-uat

GSD's UAT predicate (`uat-predicate.cjs` `isTestGapResolved`) only credits a `resolved_by` that names a PLAN file inside the phase directory, with a matching SUMMARY and `gap_ids`. This plan lives in `.planning/quick/`, so `/gsd-audit-uat` may still list UAT tests 1 and 2 as blocking even though the gaps are resolved. No files were moved to work around it.

## Self-Check: PASSED

- Commits `c025681`, `665086b`, `c79a196`, `0a48008`, `a616fc5` all exist on `gsd/phase-02.1-faster-answers` (`git rev-list --count d074e3b..HEAD` gives 5).
- `app/Enums/AlertReason.php`, `app/Enums/MessageKind.php`, `app/Actions/RaiseOwnerAlert.php`, `app/Actions/SendCombinedReply.php`, `app/SendRule/ReplyParts.php` byte-identical to d074e3b; no `raiseOwnerAlert->` line in the `RunFrontDesk.php` diff; `app/Jobs/RespondToLifeSafety.php` and `map/VERIFICATION.md` unchanged.
- `FrontDeskPrompt.php` is 327 lines and `history()` is still at line 140.
- The plan's Task 3 verify script exited 0 (UAT gaps, CONTEXT sentence, WINDOWS statuses and prose, map stamps and citation bounds, required phrases).
- `.planning/milestone.lock` left alone, untracked.
