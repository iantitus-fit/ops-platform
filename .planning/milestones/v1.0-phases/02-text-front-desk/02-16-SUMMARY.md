---
phase: 02-text-front-desk
plan: 16
subsystem: front-desk
tags: [laravel, send-rule, held-drafts, owner-alerts, customer-card, filament, pest]

requires:
  - phase: 02-text-front-desk
    provides: RunFrontDesk hold branch and draft rows (02-14); Verdict::plainReasonFor() and the full verdict record (02-15); RaiseOwnerAlert, OwnerAlertText, AlertReason (02-10, 02-11)
provides:
  - A held reply is stored as a draft and a draft_held timeline entry in one transaction, then the owner is alerted after commit (GATE-01)
  - Complaint holds alert as complaint (urgent, at once); every other hold alerts as draft_waiting with the plain reason as the alert's why (D-07, D-20)
  - ActivityType::DraftHeld and the customer card section "Draft held for owner" with the plain reason and the draft quoted
  - Conversation view draft badge in the warning colour; sent and delivered stay success, any other never-sent status gray
  - Tests - HeldDraftTest (5 tests)
affects: [02-17 one live draft per conversation, decision table and holding reply, 02-22 owner approves, edits or discards the draft]

plan_head_before: 24ca9334e833d7afb43c342c6afba0e6b1bd3439
plan_head_after: ec13173f15c4f059b1c40c32bbaa1903da10ed27

actuals:
  tokens: 3922
  tasks: 1
  commits: 2

tech-stack:
  added: []
  patterns:
    - "Write the record in a DB::transaction that returns the saved model, then do the irreversible side effect (the text to the owner) after the commit"
    - "A complaint is recognised by the send rule's reason code (label:, classifier: or classifier_intent: complaint), everything else held is draft_waiting"
    - "CustomerCard::section() stays an exhaustive match; a new ActivityType case needs its arm (Pitfall 8)"

key-files:
  created:
    - tests/Feature/FrontDesk/HeldDraftTest.php
  modified:
    - app/Actions/RunFrontDesk.php
    - app/Enums/ActivityType.php
    - app/Records/CustomerCard.php
    - resources/views/livewire/conversation-thread.blade.php
    - tests/Feature/FrontDesk/ProcessInboundTest.php
    - docs/sprints/sprint-1.md

key-decisions:
  - "The send_refused hold (a send the owner's lock refused) also goes through storeDraft, so it too lands on the timeline and alerts the owner as draft_waiting with 'Held: could not send right now'"
  - "The card quotes the plain reason from the draft's own gate.reason.plain and falls back to the activity summary, so a card still reads if the draft row lacks a gate"

requirements-completed: [GATE-01]

coverage:
  - id: D1
    description: "A reply held by the send rule is stored as a draft, never sent to the customer, and keeps its plain reason (Held: asks about price)"
    requirement: GATE-01
    verification:
      - kind: integration
        ref: "tests/Feature/FrontDesk/HeldDraftTest.php#keeps a price answer as a draft with its plain reason and sends none of it to the customer"
        status: pass
    human_judgment: false
  - id: D2
    description: "The owner's phone gets one alert whose first line is the customer's name and the plain reason, quoting the customer's text and the draft; reason draft_waiting with the draft as its message"
    requirement: GATE-01
    verification:
      - kind: integration
        ref: "tests/Feature/FrontDesk/HeldDraftTest.php#texts the owner one alert that names the customer and the plain reason, and quotes both sides"
        status: pass
    human_judgment: false
  - id: D3
    description: "A held complaint reply raises a complaint alert that is urgent and sent at once, even at 20:00"
    requirement: GATE-01
    verification:
      - kind: integration
        ref: "tests/Feature/FrontDesk/HeldDraftTest.php#alerts a held complaint reply as a complaint, at once, even after hours"
        status: pass
    human_judgment: false
  - id: D4
    description: "The customer's timeline gets a draft_held entry on the draft and the card renders it under 'Draft held for owner'"
    requirement: GATE-01
    verification:
      - kind: integration
        ref: "tests/Feature/FrontDesk/HeldDraftTest.php#puts a draft_held entry on the customer timeline and shows it on the card"
        status: pass
    human_judgment: false
  - id: D5
    description: "The conversation view shows a held draft with a draft badge in the warning colour, never success"
    verification:
      - kind: integration
        ref: "tests/Feature/FrontDesk/HeldDraftTest.php#shows a held draft in the conversation as a draft in the warning colour, never as sent"
        status: pass
    human_judgment: true
    rationale: "The test proves the badge class; whether warning amber reads as 'waiting on you' in the real dashboard is a visual call"

duration: 14min
completed: 2026-10-12
status: complete
---

# Phase 2 Plan 16: Held draft alert and timeline Summary

**A reply the send rule holds is now saved with its plain reason, written to the customer's timeline as draft_held in the same transaction, and texted to the owner after commit as a complaint (urgent) or a draft waiting, with the draft badge in warning amber.**

## Performance

- **Duration:** about 14 min
- **Completed:** 2026-10-12
- **Tasks:** 1 (tracer, TDD: one RED commit, one GREEN commit)
- **Files modified:** 7 (one new test file)

## Accomplishments

- "How much for a new unit?" now ends as a draft with `gate.reason.plain` `Held: asks about price`, nothing sent to the customer, one `draft_waiting` alert on the owner's phone starting `Dana Whitfield: Held: asks about price` and quoting both sides.
- A held complaint (`label:complaint`, `classifier:complaint`, `classifier_intent:complaint`) raises a `complaint` alert, urgent, sent immediately even at Tue 20:00.
- The draft and its `draft_held` activity are created in one `DB::transaction`; the alert is raised after the commit and `CustomerRecordChanged` is dispatched, so the card re-renders (T-02-60).
- `CustomerCard` has a `DraftHeld` arm: label `Draft held for owner`, intro `<plain reason>. Draft, not sent:`, the draft quoted.
- Conversation thread badge: `Draft` and `Queued` warning, `Sent` and `Delivered` success, `Failed` danger, anything else gray (plan 02-17's `withdrawn` will fall to gray).

## Task Commits

1. **Task 1 (tracer): held draft end to end**
   - RED: `910b1d0` test(02-16): add failing held draft alert tests
   - GREEN: `ec13173` feat(02-16): held drafts alert the owner and show on the timeline

**Plan metadata:** the docs(02-16) commit that carries this file.

## TDD Gate Compliance

- RED: `tests/Feature/FrontDesk/HeldDraftTest.php`, 5 tests, run `php artisan test --compact tests/Feature/FrontDesk/HeldDraftTest.php`. Result before the code: 1 passed, 2 failed assertions, 2 errored. Semantic assessment: the passing test is the "draft exists with its plain reason and nothing reaches the customer" guard, which was already true from plan 02-15 and is kept as a regression check. The other four failed for the planned reason: no alert existed (`actual size 0 matches expected size 1`, `No query results for model [OwnerAlert]`), no `draft_held` activity existed (`No query results for model [Activity]`), and the badge was not in the warning colour. The test refers to `draft_held` as a string, so the failures are not a missing-enum fatal. The RED evidence classifier (`gsd_run check tdd-red-evidence`) has no adapter for Pest's report, so it was not run; the assessment above is by inspection of the real output.
- GREEN: the same file, 5 of 5 pass, 33 assertions.
- REFACTOR: none needed.
- Both gate commits are present in order (`test(02-16)` then `feat(02-16)`).

## Files Created/Modified

- `tests/Feature/FrontDesk/HeldDraftTest.php` - price hold, alert text, complaint urgency, timeline and card, badge colour
- `app/Actions/RunFrontDesk.php` - `storeDraft()` now writes draft and `draft_held` entry in a transaction, then alerts; `alertReasonFor()` picks complaint or draft_waiting
- `app/Enums/ActivityType.php` - `DraftHeld = 'draft_held'`
- `app/Records/CustomerCard.php` - `DraftHeld` arm via `heldDraftParts()`
- `resources/views/livewire/conversation-thread.blade.php` - badge colour map
- `tests/Feature/FrontDesk/ProcessInboundTest.php` - one sending-rules mock gained `owner_alert` (see deviations)
- `docs/sprints/sprint-1.md` - one walk-through line

## Decisions Made

- The `send_refused` hold shares the same path, so a send the reply lock refused now also reaches the owner and the timeline, which is what GATE-01 asks of every held reply.
- The card's plain reason comes from the draft's gate, with the activity summary as the fallback.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Existing test mocked sending rules without `owner_alert`**
- **Found during:** Task 1, full suite run
- **Issue:** `ProcessInboundTest` "keeps the floor in code" partial-mocks `ActiveConfig::sendingRules()` with only `send_on_own` and `draft_for_owner`. A hold now raises an alert, and `RaiseOwnerAlert::isUrgent()` reads `owner_alert.right_away`, so the mock threw `Undefined array key "owner_alert"`. The real folder always carries that key (the sync validates it).
- **Fix:** Added `owner_alert` (`channels`, `right_away`, `after_hours`) to that mock's return value. No assertion changed.
- **Files modified:** `tests/Feature/FrontDesk/ProcessInboundTest.php`
- **Verification:** full suite 642 of 642 pass
- **Committed in:** `ec13173`

---

**Total deviations:** 1 auto-fixed (1 blocking)
**Impact on plan:** Test fixture only; no scope creep.

## Issues Encountered

- My first test helper called `activateExampleFolder()` and `frontDeskCustomerTexts()` calls it too; the second activation reports `Unchanged` and its assertion failed. Removed the duplicate call before the RED commit.

## Known Stubs

None.

## Threat Flags

None. The alert quotes customer text only through the existing `OwnerAlertText::clean()` path; no new endpoint or trust boundary.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

Ready for 02-17 (one live draft per conversation, GATE-01 decision table, holding reply) and 02-22 (approve, edit, discard). The draft rows carry kind agent_reply, status draft and the verdict; the `draft_held` entry's subject is the draft, so an approval can find its timeline entry.

## Self-Check: PASSED

- FOUND: tests/Feature/FrontDesk/HeldDraftTest.php
- FOUND: commits 910b1d0 (RED) and ec13173 (GREEN)
- `php artisan test --compact`: 642 passed

---
*Phase: 02-text-front-desk*
*Completed: 2026-10-12*
