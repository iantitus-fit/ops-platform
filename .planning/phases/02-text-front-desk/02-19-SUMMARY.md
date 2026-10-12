---
phase: 02-text-front-desk
plan: 19
subsystem: front-desk
tags: [laravel, filament, pause, take-over, outbound-sender, owner-alerts]

requires:
  - phase: 02-text-front-desk
    provides: "OutboundSender, SendOrigin, RaiseOwnerAlert, AlertReason::PausedMessage, RunFrontDesk, holding reply (plans 02-10, 02-14, 02-17)"
provides:
  - "One paused state on conversations (paused_at, paused_reason, paused_by) and Conversation::isPaused()"
  - "PausedReason enum with bannerLead() and timelineSummary()"
  - "PauseConversation and HandBackConversation actions (one way in, one way out)"
  - "Take over / Hand back header actions on the conversation page"
  - "OutboundSender refusal of every automated text while paused, checked inside the send lock"
  - "paused_message owner alert per customer burst, agent never asked while paused"
affects: [02-20 owner-reply pause and handoff, 02-21 life-safety line, reply cap]

actuals:
  tokens: 8000
  tasks: 1
  commits: 2
plan_head_before: 97c291e6eb59a1f5db3305a0fbab0cc0b9465a01
plan_head_after: 670b77cc80107e37ce4e1f4da8cbb4ae9a10cd7e

tech-stack:
  added: []
  patterns:
    - "Nullable timestamp as an on/off state that also records when"
    - "Pause re-checked in OutboundSender::deliver() after the lock and refresh, so a late Take over wins"
    - "Partial unique index recreated after a SQLite table rebuild"

key-files:
  created:
    - database/migrations/2026_10_12_000008_add_pause_columns_to_conversations_table.php
    - app/Enums/PausedReason.php
    - app/Actions/PauseConversation.php
    - app/Actions/HandBackConversation.php
    - tests/Feature/FrontDesk/TakeOverTest.php
  modified:
    - app/Models/Conversation.php
    - database/factories/ConversationFactory.php
    - app/Filament/Resources/Conversations/Pages/ViewConversation.php
    - app/Actions/OutboundSender.php
    - app/Actions/RunFrontDesk.php
    - app/Enums/ActivityType.php
    - app/Records/CustomerCard.php
    - docs/sprints/sprint-1.md

key-decisions:
  - "Pause is checked in OutboundSender::whyBlocked() for SendOrigin::Automated only; Owner and LifeSafety pass (D-24)"
  - "RunFrontDesk checks the pause first, before the agent file is read, so a paused conversation never costs a model call and the owner is alerted even before a folder is live"
  - "A pause belongs to the conversation, not the contact: a new conversation after a close starts active (flagged assumption)"
  - "The migration drops and recreates conversations_one_open_per_contact after adding paused_by, because the SQLite table rebuild keeps it only as a plain unique index"

patterns-established:
  - "Every cause of a pause goes through PauseConversation with a PausedReason; plan 02-20 and the safety line reuse it"
  - "Hand back is one action whatever paused the conversation"

requirements-completed: [GATE-03]

coverage:
  - id: D1
    description: "Owner can take over a conversation from the dashboard with one header action, and hand it back; the control swaps between Take over and Hand back"
    requirement: GATE-03
    verification:
      - kind: automated_ui
        ref: "tests/Feature/FrontDesk/TakeOverTest.php#shows Take over on an active conversation and records who took over (D-26)"
        status: pass
      - kind: automated_ui
        ref: "tests/Feature/FrontDesk/TakeOverTest.php#hides Take over on a closed conversation"
        status: pass
    human_judgment: false
  - id: D2
    description: "While paused the agent is not asked, nothing is sent to the customer, and the owner gets one paused_message alert per burst quoting the last text"
    requirement: GATE-03
    verification:
      - kind: integration
        ref: "tests/Feature/FrontDesk/TakeOverTest.php#answers nothing, never asks the agent, and alerts the owner when the customer texts during a take over (D-24)"
        status: pass
      - kind: integration
        ref: "tests/Feature/FrontDesk/TakeOverTest.php#raises one alert quoting the last text when three arrive in one burst while paused (D-24)"
        status: pass
    human_judgment: false
  - id: D3
    description: "OutboundSender refuses automated sends while paused and lets the owner through; a Take over that lands mid-run turns the reply into a send_refused draft"
    requirement: GATE-03
    verification:
      - kind: unit
        ref: "tests/Feature/FrontDesk/TakeOverTest.php#refuses automated sends while paused and lets the owner through"
        status: pass
      - kind: integration
        ref: "tests/Feature/FrontDesk/TakeOverTest.php#holds a reply composed before Take over as a refused draft and sends nothing"
        status: pass
    human_judgment: false
  - id: D4
    description: "Hand back clears the pause and the agent answers the next text; timeline and customer card record both changes"
    requirement: GATE-03
    verification:
      - kind: integration
        ref: "tests/Feature/FrontDesk/TakeOverTest.php#lets the agent answer again once the owner hands the conversation back"
        status: pass
      - kind: integration
        ref: "tests/Feature/FrontDesk/TakeOverTest.php#writes agent_paused and agent_resumed to the timeline and the card (D-23)"
        status: pass
    human_judgment: false
  - id: D5
    description: "How the Take over / Hand back buttons look and read on the live dashboard"
    verification: []
    human_judgment: true
    rationale: "Button colour, placement and wording are visual judgments no test asserts; the behaviour is covered by D1"

duration: 12min
completed: 2026-10-11
status: complete
---

# Phase 2 Plan 19: Take Over and Hand Back Summary

**One paused state on the conversation with Take over / Hand back header actions, an OutboundSender that refuses every automated text while paused (re-checked inside the send lock), and a paused_message owner alert per customer burst instead of an agent run.**

## Performance

- **Duration:** about 12 min
- **Completed:** 2026-10-12T03:10Z
- **Tasks:** 1 (tracer, RED then GREEN)
- **Files modified:** 13 (5 created, 8 modified)

## Accomplishments

- `conversations.paused_at / paused_reason / paused_by`, `Conversation::isPaused()`, `pausedBy()`, `ConversationFactory::paused()`.
- `PauseConversation` and `HandBackConversation` in the `CloseConversation` style: transaction, row lock, no-op when already in the state, a timeline entry, `CustomerRecordChanged`.
- `takeOver` and `handBack` header actions on `ViewConversation`; only one shows at a time, and Take over hides on a closed conversation.
- `OutboundSender::whyBlocked()` returns `The assistant is paused in this conversation.` for `SendOrigin::Automated`; `deliver()` asks inside the lock on a refreshed conversation, so a Take over during a run wins and `RunFrontDesk` holds the reply as a `send_refused` draft.
- `RunFrontDesk::handle()` raises `RaiseOwnerAlert` with `AlertReason::PausedMessage` for the burst's last text and returns before the agent is asked.
- `agent_paused` / `agent_resumed` timeline entries, rendered on the customer card as `Assistant paused` / `Assistant resumed` under `System note:`.

## Task Commits

1. **Task 1: take over, alert on new texts, hand back** (tracer)
   - RED `f356dc0` test(02-19): add failing take over and hand back tests
   - GREEN `670b77c` feat(02-19): take over and hand back

Verified end to end by the tracer's own tests: `TakeOverTest` (9 tests), `tests/Feature/Filament`, `tests/Feature/Records` (88 passing), and the full suite (704 passing).

## TDD Gate Compliance

RED commit `f356dc0` precedes GREEN commit `670b77c`. RED evidence: before any production code, 9 tests ran; 6 failed on the planned behaviour (`takeOver` action not found on `ViewConversation`), 3 errored on the missing `ConversationFactory::paused()` state or on the missing `PauseConversation` class the mid-run test calls. Semantic assessment: every failure is the target assertion or a missing symbol of the planned feature, none is a syntax or fixture fault. No REFACTOR commit was needed.

## Decisions Made

- Pause check sits in `whyBlocked()` for Automated origin only, so the owner's reply box and the future safety line are unaffected.
- `RunFrontDesk` checks the pause before reading the agent file, so a paused conversation never spends a model call.
- Pause is per conversation; a new conversation after a close starts active.
- `HandBackConversation::handle()` accepts `?User $by` as the plan's interface specifies, but there is no column for who handed back and the timeline wording is fixed by the plan, so the argument is not stored yet.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] The pause migration dropped the "one open conversation per customer" partial index**
- **Found during:** Task 1, GREEN (running `tests/Feature/Filament`)
- **Issue:** Adding the `paused_by` foreign key makes SQLite rebuild the `conversations` table. The rebuild kept `conversations_one_open_per_contact` only as a plain unique index (the `where status = 'open'` was lost), so a customer's closed and open conversations collided in two existing `ConversationResourceTest` tests.
- **Fix:** The migration, in `up()` and `down()`, drops that index and recreates it as the partial index after the table changes. Checked on a fresh SQLite file: migrate, rollback and migrate again all leave the partial index in place.
- **Files modified:** `database/migrations/2026_10_12_000008_add_pause_columns_to_conversations_table.php`
- **Verification:** `tests/Feature/Filament` and the full suite pass.
- **Committed in:** `670b77c`

**2. [Rule 3 - Blocking] Local dev database repaired by hand**
- **Found during:** Task 1, GREEN (`php artisan migrate`)
- **Issue:** The first `migrate` run against `database/database.sqlite` left the three columns in place but the migration unrecorded, and the partial index missing, so a rerun failed with "duplicate column name".
- **Fix:** Recreated the partial index and recorded the migration in `migrations` (batch 8) with sqlite3; no data was touched and `migrate` now reports nothing to migrate. This only affects Ian's local dev database, not the repo.
- **Verification:** `.indexes conversations` shows the partial index with its `where` clause.

---

**Total deviations:** 2 auto-fixed (1 bug, 1 blocking)
**Impact on plan:** Both were necessary; no scope change.

## Issues Encountered

None beyond the deviations above.

## User Setup Required

None - no external service configuration required. If the local dev database was migrated on another machine, run `php artisan migrate` there; the migration is safe on a fresh database.

## Known Stubs

None.

## Next Phase Readiness

Plan 02-20 can pause for `OwnerReplied`, `AskedForPerson` and `ReplyCap` through `PauseConversation::handle()`, and the safety line (02-21) can send with `SendOrigin::LifeSafety` and then pause as `LifeSafety`. The banner text for a paused conversation is available as `PausedReason::bannerLead()`; the conversation page does not show a banner yet.

## Self-Check: PASSED

All created files exist and both task commits are in the branch history.
