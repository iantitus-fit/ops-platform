---
phase: 02-text-front-desk
plan: 22
subsystem: dashboard
tags: [livewire, filament, held-drafts, owner-review, pest, tdd]

requires:
  - phase: 02-text-front-desk
    provides: OutboundSender (one outbound path), SendOwnerReply, ComposeAutomatedText, draft resolution columns and DraftResolution enum, one-live-draft index, ConversationThread
provides:
  - App\Actions\ResolveDraft (approve, edit and send, discard; REPLACED and ALREADY_HANDLED sentences)
  - SendOwnerReply::sendDraft (composes disclosure and sign-off, 1,600 limit on the composed text, never pauses)
  - OutboundSender::sendExisting (claims the draft row under a row lock and sends it; the same row becomes the sent message)
  - ActivityType::DraftDiscarded and the CustomerCard "Draft discarded" entry
  - ConversationThread Needs you block (draftId, draftBody, liveDraft, approveDraft, discardDraft)
affects: [02-23 phone-friendly view and home screen, 02-24 opt-out block, phase verification of GATE-04]

actuals:
  tokens: 11300
  tasks: 1
  commits: 2

plan_head_before: c10099424b2edcfab3e1ab54b56048dde9842a19
plan_head_after: f38b3bf9af288c88dad56401ca428b0f76939ab3

tech-stack:
  added: []
  patterns:
    - "Draft-to-sent on one row: claim under lockForUpdate with a status check inside the transaction, then the shared send path"
    - "Livewire #[Locked] id property filled by the server in render(), lookup scoped to the component's conversation"
    - "OutboundSender::transmit() shared by deliver() (new row) and deliverExisting() (draft row)"

key-files:
  created:
    - app/Actions/ResolveDraft.php
    - tests/Feature/Filament/DraftReviewTest.php
  modified:
    - app/Actions/OutboundSender.php
    - app/Actions/SendOwnerReply.php
    - app/Livewire/ConversationThread.php
    - resources/views/livewire/conversation-thread.blade.php
    - app/Enums/ActivityType.php
    - app/Records/CustomerCard.php
    - tests/Feature/FrontDesk/HeldDraftTest.php
    - docs/sprints/sprint-1.md

key-decisions:
  - "Approved drafts go out through sendExisting(), not handle(): handle() pauses the conversation (owner_replied), and D-25 says approving a draft must not pause the assistant. A taken-over conversation keeps its pause."
  - "The race guard is the status check under lockForUpdate inside sendExisting's claim, not a pre-check in ResolveDraft; approve() only pre-checks so a replaced draft is reported before any complaint about the owner's words."
  - "draftId is #[Locked] and looked up through the conversation's own messages, so a swapped id cannot reach another conversation's draft (T-02-44)."
  - "After a replacement the owner's edit stays in draftBody, draftId moves to the new live draft, and the assistant's new words are shown above the box so nothing typed is lost (D-28)."
  - "When the channel fails or the draft is already gone, the owner's words move to the reply box for another try; the draft row stays as failed with the error."

patterns-established:
  - "A draft's decision lives on its own row: resolution, resolved_by, resolved_at, original_body"
  - "Constants for refusal sentences live on the action that owns them (ResolveDraft::REPLACED), read by the sender"

requirements-completed: [GATE-04]

coverage:
  - id: D1
    description: "Owner sees the held draft and its reason in the conversation and can send it as written; the same message row becomes the sent message with resolution approved, nothing is paused"
    requirement: GATE-04
    verification:
      - kind: integration
        ref: "tests/Feature/Filament/DraftReviewTest.php#sends the draft as written on the same message row and does not pause the assistant (D-25, D-27, D-54)"
        status: pass
    human_judgment: false
  - id: D2
    description: "Owner edits the draft and sends it: exact words trimmed plus sign-off, original_body keeps the agent's words, 1,600 composed characters sends and 1,601 is refused"
    requirement: GATE-04
    verification:
      - kind: integration
        ref: "tests/Feature/Filament/DraftReviewTest.php#sends the owner's edit exactly as typed (and the two boundary tests)"
        status: pass
    human_judgment: false
  - id: D3
    description: "Owner discards a draft: withdrawn, discarded with who and when, timeline and card entry, nothing sent"
    requirement: GATE-04
    verification:
      - kind: integration
        ref: "tests/Feature/Filament/DraftReviewTest.php#discards the draft, sends nothing, and shows it on the timeline and the card (GATE-04)"
        status: pass
    human_judgment: false
  - id: D4
    description: "A replaced draft is never sent, the owner is told the customer wrote again, the edit stays in the box and the new draft is shown"
    requirement: GATE-04
    verification:
      - kind: integration
        ref: "tests/Feature/Filament/DraftReviewTest.php#refuses to send a replaced draft, keeps the owner's edit, and shows the new draft (D-28)"
        status: pass
    human_judgment: false
  - id: D5
    description: "The Needs you block reads clearly and is usable by the owner in the real browser"
    verification: []
    human_judgment: true
    rationale: "Layout and wording were verified only through Livewire component tests; no browser pass was run. Plan 02-23 reworks the view for phones."

duration: 40min
completed: 2026-10-12
status: complete
---

# Phase 2 Plan 22: Draft Review Summary

**Owner approves, edits and sends, or discards a held draft from the conversation view; the same message row becomes the sent message with who decided, and approving never pauses the assistant.**

## Performance

- **Duration:** about 40 min
- **Completed:** 2026-10-12T03:32Z
- **Tasks:** 1 (tracer, with its test commit)
- **Files modified:** 10 (2 created)

## Accomplishments

- `ResolveDraft::approve()` and `discard()`, with `SendOwnerReply::sendDraft()` composing the text and `OutboundSender::sendExisting()` moving the draft row from draft to queued to sent under the same send lock as every other text.
- A "Needs you" block at the top of the conversation: the held reason, an editable box, a hint about the sign-off, Send and Discard. Drafts and withdrawn rows no longer appear in the thread list.
- D-28 handled: a replaced draft is refused with "The customer wrote again, here's the new draft", nothing is sent, the owner's edit stays in the box and the new draft is shown.
- `draft_discarded` timeline entry and a "Draft discarded" card entry; the card's held-draft entry now says the owner sent it once it has gone out instead of "Draft, not sent".
- 16 new tests in `DraftReviewTest`; full suite 792 tests passing.

## TDD Gate Compliance

- RED (`fbac65d`): 14 of 15 tests failed on the planned behavior (missing `approveDraft`, `discardDraft`, `draftBody`, the Needs you block). The one test that passed already (escaping in the thread) guards behavior that must keep holding in the new block. The T-02-44 test initially failed on a test-setup mistake (two conversations), fixed before the GREEN commit. Semantic assessment: failures were the planned missing members and assertions, not load or fixture faults.
- GREEN (`f38b3bf`): all 16 pass. No refactor commit.
- This plan is `type: execute` with a tracer task, so the `tdd-red-evidence` classifier was not run.

## Task Commits

1. **Task 1 RED: failing draft review tests** - `fbac65d` (test)
2. **Task 1 GREEN: approve, edit or discard a draft** - `f38b3bf` (feat)

**Plan metadata:** committed separately as `docs(02-22): complete draft review plan`.

## Decisions Made

See `key-decisions`. The handoff note from 02-20 asked to check that approved drafts avoid the owner-reply pause; `sendDraft()` calls `sendExisting()` and never `PauseConversation`, and a test confirms a taken-over conversation keeps `taken_over` and an active one stays active.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Existing test asserted the draft appears in the thread with a warning badge**
- **Found during:** Task 1 (full suite run)
- **Issue:** `HeldDraftTest` "shows a held draft in the conversation as a draft in the warning colour" expected the draft as a `draft` badge in the thread list. The plan requires the draft to leave the thread list and live in the Needs you block.
- **Fix:** Rewrote that test to assert the draft and reason appear in the Needs you block, the draft is not in the thread's messages, and it is never shown with a success badge.
- **Files modified:** tests/Feature/FrontDesk/HeldDraftTest.php
- **Committed in:** f38b3bf

**2. [Rule 2 - Missing critical] Customer card mislabeled a sent draft as "Draft, not sent"**
- **Found during:** Task 1
- **Issue:** The `draft_held` card entry reads the draft's current body; after approval the row holds the text that went out, but the label still said it was not sent. The agent reads this card.
- **Fix:** `heldDraftParts()` says "The owner then sent it to the customer:" when the draft's status is queued, sent or delivered. Covered by a new test.
- **Files modified:** app/Records/CustomerCard.php
- **Committed in:** f38b3bf

**3. [Rule 2 - Missing critical] Owner's words after a failed send or an already-handled draft**
- **Found during:** Task 1
- **Issue:** On channel failure the draft row becomes failed, so the Needs you block disappears and the owner's edit would be lost.
- **Fix:** On a refusal, if no live draft remains, the words move to the reply box. Covered by a channel-failure test.
- **Files modified:** app/Livewire/ConversationThread.php
- **Committed in:** f38b3bf

---

**Total deviations:** 3 auto-fixed (1 bug-in-test, 2 missing critical). **Impact:** no scope creep; all within the files and behavior of this plan.

## Issues Encountered

None blocking. The textarea content is filled by Livewire in the browser, not in the server HTML, so the escaping test checks the "The assistant wrote" line and the reason instead.

## Known Stubs

None.

## Threat Flags

None beyond the plan's register. T-02-44 (locked `draftId`, conversation-scoped lookup, status check under a row lock), T-02-45 (escaped echo, `e()` on notification titles) and T-02-46 (resolution columns plus timeline entries) are mitigated and tested.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

02-23 can restyle the Needs you block for phones and add the home screen; the component state (`draftId`, `draftBody`, `liveDraft`) and actions are stable. The unused `Draft` arm in the thread's badge `match` is left in place and is harmless.

---
*Phase: 02-text-front-desk*
*Completed: 2026-10-12*

## Self-Check: PASSED

- FOUND: app/Actions/ResolveDraft.php, tests/Feature/Filament/DraftReviewTest.php
- FOUND commits: fbac65d, f38b3bf
- Acceptance greps and both automated verify commands pass (full suite 792/792).
