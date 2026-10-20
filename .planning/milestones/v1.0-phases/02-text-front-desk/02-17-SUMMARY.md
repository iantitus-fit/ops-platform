---
phase: 02-text-front-desk
plan: 17
subsystem: front-desk
tags: [laravel, send-rule, drafts, partial-unique-index, holding-reply, owner-alerts, pest]

requires:
  - phase: 02-text-front-desk
    provides: RunFrontDesk hold branch with draft_held entry and RaiseOwnerAlert (02-14, 02-16); FixedReplies::holdingReply (02-05); OutboundSender and ComposeAutomatedText (02-14, 02-15); intake answers on RunFrontDesk (02-18)
provides:
  - One live draft per conversation, enforced by the partial unique index messages_one_live_draft_per_conversation on SQLite and Postgres (D-28)
  - MessageStatus::Withdrawn and DraftResolution (approved, edited, discarded, replaced); messages.resolution, resolved_by, resolved_at, original_body; Message::resolvedBy()
  - A newer held draft withdraws the old one as replaced and keeps it as history, in the same transaction that writes the new one
  - Replacement alerts only when urgency rises, or when no alert in the draft chain ever reached the owner (D-22)
  - SendFixedReply::holding() and holdingAlreadySent(): one holding reply per waiting draft, through OutboundSender, no Jev call, time computed at send (D-12, D-13, D-14, D-50)
  - GateTest: the GATE-01 decision table (15 dataset rows plus the send_on_own-only folder)
  - MessageFactory::draft() and withdrawn()
affects: [02-19 handoff and opt-out replies, 02-22 owner approves, edits or discards the draft, 02-24 export of labeled drafts]

plan_head_before: ebbf6babd471cde05367703f1a1d2d88663e4c69
plan_head_after: 5a2097714e81b7de4ff3e90592f4064f3685dc09

actuals:
  tokens: 10900
  tasks: 2
  commits: 4

tech-stack:
  added: []
  patterns:
    - "A partial unique index (create unique index ... where status = 'draft') through DB::statement lets the database refuse a second live row while any number of finished rows share the key"
    - "Withdraw-then-create inside one DB::transaction with lockForUpdate; the replaced draft is never deleted, only marked with a resolution"
    - "Alert dedup across a replacement chain: the new draft's meta.replaces lists the chain, and ownerNeedsAlertFor() looks only at alerts that were actually sent"
    - "SendFixedReply composes fixed text through ComposeAutomatedText and sends through OutboundSender with gate reason fixed_reply, so no classifier call and no second outbound path"

key-files:
  created:
    - database/migrations/2026_10_12_000006_add_draft_resolution_to_messages_table.php
    - app/Enums/DraftResolution.php
    - app/Actions/SendFixedReply.php
    - tests/Feature/FrontDesk/GateTest.php
    - tests/Feature/FrontDesk/DraftReplacementTest.php
    - tests/Feature/FrontDesk/HoldingReplyTest.php
  modified:
    - app/Enums/MessageStatus.php
    - app/Models/Message.php
    - database/factories/MessageFactory.php
    - app/Actions/RunFrontDesk.php
    - tests/Pest.php
    - tests/Feature/FrontDesk/ProcessInboundTest.php
    - tests/Feature/FrontDesk/DisclosureTest.php
    - docs/sprints/sprint-1.md

key-decisions:
  - "A replacement draft's alert is skipped only when an alert for the draft chain was actually sent and was already urgent or the new reason is not urgent; an alert still waiting for opening time, or one that failed, does not count as telling the owner, because the withdrawn draft's waiting alert is dropped as handled by SendDueAlerts"
  - "The chain of replaced drafts is kept in the new draft's meta as replaces (list of message ids), so a third text in a row compares against the whole chain and the owner gets one alert, not one per link"
  - "holdingAlreadySent() treats a draft with resolution approved, edited or discarded and resolved_at at or after the latest holding reply as a real answer, because those drafts keep their original id and creation time while the owner's decision comes later"
  - "Held-draft tests now read textsSentForAgentReplies() and theAgentMessage() ignores kind holding_reply, since a held draft is now followed by a holding reply to the customer"

patterns-established:
  - "Pest helper textsSentForAgentReplies() in tests/Pest.php: texts the fake channel sent, minus holding-reply bodies"
  - "File-specific helper names (gateTest*, draftReplacement*, holdingReply*) for the three new test files"

requirements-completed: [GATE-01, GATE-04]

coverage:
  - id: D1
    description: "A conversation has at most one live draft: the database refuses a second status draft row, on SQLite and Postgres"
    requirement: GATE-04
    verification:
      - kind: integration
        ref: "tests/Feature/FrontDesk/DraftReplacementTest.php#refuses a second live draft in one conversation at the database"
        status: pass
    human_judgment: false
  - id: D2
    description: "A newer customer text replaces the draft: the old one is withdrawn with resolution replaced and resolved_at, kept as history, and the new one is the only draft"
    requirement: GATE-04
    verification:
      - kind: integration
        ref: "tests/Feature/FrontDesk/DraftReplacementTest.php#withdraws the old draft as replaced and keeps it when a newer text gets a new draft"
        status: pass
    human_judgment: false
  - id: D3
    description: "A replacement alerts again only when urgency rises (complaint) or when no earlier alert reached the owner; a chain of three drafts gives one alert"
    requirement: GATE-01
    verification:
      - kind: integration
        ref: "tests/Feature/FrontDesk/DraftReplacementTest.php#sends no second alert when the replacement is held for the same kind of reason"
        status: pass
      - kind: integration
        ref: "tests/Feature/FrontDesk/DraftReplacementTest.php#sends a second alert when the replacement is a complaint, because urgency rose"
        status: pass
      - kind: integration
        ref: "tests/Feature/FrontDesk/DraftReplacementTest.php#sends one alert for a chain of replacements, not one per draft"
        status: pass
    human_judgment: false
  - id: D4
    description: "The GATE-01 decision table: label, code checks and Jev each map to the stated send or hold reason; a label not in send_on_own is drafted"
    requirement: GATE-01
    verification:
      - kind: integration
        ref: "tests/Feature/FrontDesk/GateTest.php#turns the label, the code checks and Jev into the stated send or hold"
        status: pass
      - kind: integration
        ref: "tests/Feature/FrontDesk/GateTest.php#drafts a scheduling reply when the folder lets only intake questions send on their own"
        status: pass
    human_judgment: false
  - id: D5
    description: "A held draft is followed by one holding reply with the true time, the disclosure when it is the first automated text, and the sign-off, sent without asking Jev"
    requirement: GATE-01
    verification:
      - kind: integration
        ref: "tests/Feature/FrontDesk/HoldingReplyTest.php#texts the customer a holding reply that says when the owner will answer, with the disclosure and sign-off"
        status: pass
      - kind: integration
        ref: "tests/Feature/FrontDesk/HoldingReplyTest.php#says today while the shop is open and leaves the disclosure out when an agent text already went out"
        status: pass
      - kind: integration
        ref: "tests/Feature/FrontDesk/HoldingReplyTest.php#does not ask Jev about the holding reply, because code wrote it"
        status: pass
    human_judgment: false
  - id: D6
    description: "No second holding reply while the same question waits; a new one after a real answer or a discarded draft"
    requirement: GATE-01
    verification:
      - kind: integration
        ref: "tests/Feature/FrontDesk/HoldingReplyTest.php#sends no second holding reply when a newer text replaces the waiting draft"
        status: pass
      - kind: integration
        ref: "tests/Feature/FrontDesk/HoldingReplyTest.php#sends a new holding reply after a real answer went out"
        status: pass
      - kind: integration
        ref: "tests/Feature/FrontDesk/HoldingReplyTest.php#sends a new holding reply after the owner discarded the waiting draft"
        status: pass
    human_judgment: false

duration: 14 min
completed: 2026-10-12
status: complete
---

# Phase 2 Plan 17: One Live Draft, the Gate Table and the Holding Reply Summary

**A partial unique index keeps one live draft per conversation, a newer text withdraws the old draft as replaced (kept as history) and alerts only when urgency rises, the GATE-01 decision table is under test, and one holding reply with a true time goes to the customer per waiting draft.**

## Performance

- **Duration:** 14 min
- **Started:** 2026-10-12T02:50:00Z
- **Completed:** 2026-10-12T03:04:00Z
- **Tasks:** 2
- **Files modified:** 14 (6 created, 8 modified)

## Accomplishments

- The database refuses a second `status = 'draft'` row in a conversation (SQLite and Postgres; the whole FrontDesk folder and then the full suite of 695 tests pass on both). `RunFrontDesk::storeDraft()` withdraws the live draft as `replaced`, stamps `resolved_at`, and creates the new one in one transaction.
- Replacement alerts follow D-22: a held complaint over a held price draft alerts again; a second price hold does not; a chain of three gives one alert.
- `GateTest` pins the GATE-01 table: intake and scheduling send; price, discount and promise in the words, the price, promise, discount, complaint and other labels, Jev down, unsure, seeing a price, reading a complaint, and a 1,700-character text each hold with the stated reason; a folder whose `send_on_own` lists only `intake_question` drafts a scheduling reply as `label:scheduling` (D-08).
- `SendFixedReply::holding()` sends the folder's holding reply through `OutboundSender` after the alert: no classifier call, "when we open at 8 am tomorrow" at Tue 16:30 and "today" at Tue 10:00, disclosure only when it is the first automated text, sign-off always, stored as kind `holding_reply` with gate reason `fixed_reply`.

## Task Commits

1. **Task 1: One live draft per conversation, replaced drafts kept, alerts only when urgency rises, the GATE-01 table** (tracer)
   - `bd179ec` (test) failing gate table and draft replacement tests
   - `56d0510` (feat) one live draft per conversation
2. **Task 2: One honest holding reply per waiting draft** (tdd)
   - `c6f928e` (test) failing holding reply tests
   - `5a20977` (feat) one holding reply per waiting draft

**Plan metadata:** committed after this file (docs: complete plan).

## TDD Gate Compliance

- **RED (Task 2):** `HoldingReplyTest` run with `--log-junit`; the report was classified `RED_EVIDENCE_OK` (`target_test_failed`) by `gsd_run check tdd-red-evidence`. The target test "texts the customer a holding reply ..." failed on `expect(holdingReplyTextsToDana())->toHaveCount(1)` (actual 0), which is the planned missing behavior. semanticAssessment: the target executed, failed on the intended assertion, and no setup, import or fixture fault was involved; 8 of 9 tests failed, the 9th (no holding reply for a reply that sends on its own) passes by design.
- **RED (Task 1, a tracer, not a tdd task):** `DraftReplacementTest` failed on the undefined `MessageStatus::Withdrawn`, the undefined `draft()` and `withdrawn()` factory states, and the D-22 alert count (2 where 1 was expected). `GateTest` (all 16 cases) and two `DraftReplacementTest` cases (complaint alert, alert when the first never reached the owner) already passed on the existing code. They are characterization tests that pin behavior the new code must keep, not an unexpected green: the plan described the whole table, and these rows had not been under test before.
- **GREEN:** both `feat(02-17)` commits follow their `test(02-17)` commits. No REFACTOR commit was needed.

## Files Created/Modified

- `database/migrations/2026_10_12_000006_add_draft_resolution_to_messages_table.php` - resolution, resolved_by, resolved_at, original_body; the partial unique index (down() drops it and the foreign key)
- `app/Enums/DraftResolution.php`, `app/Enums/MessageStatus.php` - four resolutions; `Withdrawn` status
- `app/Models/Message.php` - fillable, casts, `resolvedBy()`
- `database/factories/MessageFactory.php` - `draft()` and `withdrawn()` states
- `app/Actions/RunFrontDesk.php` - withdraw-then-create, `ownerNeedsAlertFor()`, holding reply after the alert
- `app/Actions/SendFixedReply.php` - `holding()` and `holdingAlreadySent()`
- `tests/Feature/FrontDesk/GateTest.php`, `DraftReplacementTest.php`, `HoldingReplyTest.php` - new
- `tests/Pest.php`, `ProcessInboundTest.php`, `DisclosureTest.php` - adapted to the holding reply
- `docs/sprints/sprint-1.md` - walk-through entry for the partial unique index and withdraw-then-create

## Decisions Made

See key-decisions above. The main one: the plan's D-22 rule ("replaced draft's alert was not urgent") is applied only to alerts that actually reached the owner. Otherwise a delayed alert (after hours) on the withdrawn draft is dropped by `SendDueAlerts` as handled, and the replacement raises none, so the owner would never hear about the question.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Missing Critical] Alert rule counts only alerts that reached the owner, and tracks the whole replacement chain**
- **Found during:** Task 1 (RunFrontDesk alert rule)
- **Issue:** Taken literally, D-22 would leave the owner unalerted when the first draft's alert was still waiting for opening time (SendDueAlerts skips a delayed alert whose draft is no longer a draft), and a third text in a row would compare against a draft with no alert and alert again.
- **Fix:** `ownerNeedsAlertFor()` raises the alert when no alert in the chain has `sent_at`, otherwise only when the new reason is urgent and no sent alert in the chain was. The chain is the new draft's `meta.replaces` id list.
- **Files modified:** app/Actions/RunFrontDesk.php; tests added in DraftReplacementTest (after-hours case, chain of three)
- **Verification:** `DraftReplacementTest` passes on SQLite and Postgres
- **Committed in:** 56d0510

**2. [Rule 3 - Blocking] Existing held-draft tests broke when a holding reply started going out**
- **Found during:** Task 2 (full suite after GREEN)
- **Issue:** `theAgentMessage()` used `->sole()` on outbound messages, and many tests asserted `textsSentToCustomers()` is empty for a held draft. Both now see the holding reply. `DisclosureTest` "does not count a held draft as the disclosure" also changed meaning: the holding reply is now the first automated text and carries the disclosure.
- **Fix:** `theAgentMessage()` skips `holding_reply`; new `textsSentForAgentReplies()` helper (texts minus holding-reply bodies) used by the held-draft assertions; the DisclosureTest case rewritten to check the disclosure lands on the holding reply and is not repeated on the later intake question.
- **Files modified:** tests/Pest.php, tests/Feature/FrontDesk/ProcessInboundTest.php, tests/Feature/FrontDesk/DisclosureTest.php
- **Verification:** full suite 695 of 695 on SQLite and on Postgres
- **Committed in:** 5a20977

**3. [Rule 2 - Missing Critical] Approved and edited drafts also end a holding-reply wait**
- **Found during:** Task 2 (`holdingAlreadySent`)
- **Issue:** The plan lists sent non-holding outbound messages and discarded drafts as real answers. Plan 02-22 may send an approved or edited draft by flipping that same row (keeping its old id), which an id-order check would miss, so a later question would never get a new holding reply.
- **Fix:** drafts with resolution approved, edited or discarded and `resolved_at` at or after the latest holding reply count as a real answer.
- **Files modified:** app/Actions/SendFixedReply.php
- **Verification:** discarded case covered by HoldingReplyTest; approved and edited are the same query branch and are exercised by plan 02-22
- **Committed in:** 5a20977

---

**Total deviations:** 3 auto-fixed (2 missing critical, 1 blocking)
**Impact on plan:** All three are needed for correct behavior (owner always hears; tests match the new holding reply; plan 02-22 fits). No scope creep.

## Issues Encountered

- On macOS `sed -i -E` treats `-E` as a backup suffix and left a stray `GateTest.php-E`. It was removed before the first commit and never staged.

## Known Stubs

None.

## Threat Flags

None. The new send path (holding reply) goes through the existing `OutboundSender` and is listed as T-02-32 in the plan; T-02-31 and T-02-33 are mitigated by the index, the transaction and the D-22 rule.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Plan 02-22 can approve, edit or discard the one live draft: `DraftResolution`, `resolved_by`, `resolved_at` and `original_body` are in place, and `holdingAlreadySent()` already reads approved, edited and discarded resolutions.
- The handoff reply (`FixedReplies::handoffReply`) can reuse `SendFixedReply` as the plan's artifact note says ("holding and later handoff").
- Flagged assumption kept: the holding reply is sent even when the draft was held for an unclear Jev answer (covered by a test).

## Self-Check: PASSED

- Created files found on disk: migration, DraftResolution, SendFixedReply, GateTest, DraftReplacementTest, HoldingReplyTest.
- Commits bd179ec, 56d0510, c6f928e, 5a20977 are ancestors of HEAD; RED (`test(02-17)`) precedes GREEN (`feat(02-17)`) for both tasks.
- Acceptance criteria re-run: migration contains `messages_one_live_draft_per_conversation` and `where status = 'draft'`; `case Withdrawn = 'withdrawn';` and `case Replaced = 'replaced';` present; GateTest has 15 dataset rows plus the folder case and passes; DraftReplacementTest contains `UniqueConstraintViolationException`; SendFixedReply contains `function holding(`, `function holdingAlreadySent(` and `fixed_reply`; HoldingReplyTest contains `when we open at 8 am tomorrow` and `today.`.
- `php artisan test --compact tests/Feature/FrontDesk tests/Feature/Quality/MoneyIsIntegerCentsTest.php` passes; the full suite passes (695 tests) on SQLite and on Postgres 17.

---
*Phase: 02-text-front-desk*
*Completed: 2026-10-12*
