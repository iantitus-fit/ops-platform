---
phase: 02-text-front-desk
plan: 36
subsystem: messaging
tags: [sent_at, thread-order, gap-closure, laravel, pest, sqlite, postgres]

requires:
  - phase: 02-text-front-desk
    provides: OutboundSender transmit path, held drafts that become the sent message on the same row (D-54), one live draft per conversation index (D-28)
provides:
  - messages.sent_at (nullable): when the text channel accepted a text
  - Message::happenedAt(): one immutable "when this happened" for ordering and every time shown
  - Conversation::messages() ordered by coalesce(sent_at, created_at), then id
  - backfill of sent_at for texts sent before this change
  - both thread views (conversation screen, /dev/chat) labelled by happenedAt()
affects: [02-37 (holding reply, reply cap, records readers move onto sent_at), 02-38 (/dev/chat filter), 02-39 (prompt history), 02-40 (Needs you card), 02-41 (map re-point)]

actuals:
  tokens: 6000
  tasks: 2
  commits: 4
plan_head_before: ab9225331b21d621d8153081595bb64cdc75aa07
plan_head_after: 9c736d6e17a3ac47bff1a3d4659a8a9741ca6847

tech-stack:
  added: []
  patterns:
    - "Schema migration that adds a column to messages recreates the partial unique index messages_one_live_draft_per_conversation (drop if exists, create) in up() and down()"
    - "Data backfill lives in its own migration so a test can require the file and call up()"
    - "One send time written once before the DB transaction and reused by the activity, the conversation, the contact and the record-failure fallback"

key-files:
  created:
    - tests/Feature/FrontDesk/SentTimeTest.php
    - database/migrations/2026_10_12_000010_add_sent_at_to_messages_table.php
    - database/migrations/2026_10_12_000011_backfill_sent_at_on_messages.php
  modified:
    - app/Models/Message.php
    - app/Models/Conversation.php
    - app/Actions/OutboundSender.php
    - resources/views/livewire/conversation-thread.blade.php
    - resources/views/livewire/dev-chat.blade.php
    - docs/sprints/sprint-1.md

key-decisions:
  - "A text's send time lives on the message row (messages.sent_at), so a held draft keeps its id and created_at and only moves in the thread when it is sent (D-54 intact)"
  - "Thread order is coalesce(sent_at, created_at) then id, on the relation itself, so every consumer (ConversationThread, DevChat, FrontDeskPrompt::history, RecordsWriter) inherits it"

patterns-established:
  - "Message::happenedAt() is the single source for time shown beside a message; views never read created_at directly"

requirements-completed: [GATE-04, AGNT-02]

coverage:
  - id: D1
    description: "A held draft the owner sends three minutes after the holding reply is listed after it on the conversation screen, same row, original created_at, sent_at at the send moment"
    requirement: GATE-04
    verification:
      - kind: integration
        ref: "tests/Feature/FrontDesk/SentTimeTest.php#lists a held draft the owner sends after the holding reply below it (G-02-1.2, UAT Test 1)"
        status: pass
    human_judgment: false
  - id: D2
    description: "Every text the channel accepts gets sent_at, including when recording the send fails afterwards; drafts, failed, queued and customer texts keep it empty"
    requirement: AGNT-02
    verification:
      - kind: integration
        ref: "tests/Feature/FrontDesk/SentTimeTest.php#records when each text was sent"
        status: pass
      - kind: integration
        ref: "tests/Feature/FrontDesk/SentTimeTest.php#leaves sent_at empty for anything that never went out"
        status: pass
      - kind: integration
        ref: "tests/Feature/FrontDesk/SentTimeTest.php#records the send time even when recording the send fails afterwards"
        status: pass
    human_judgment: false
  - id: D3
    description: "happenedAt() returns sent_at or created_at as an immutable copy that does not change the model when its time zone is changed"
    verification:
      - kind: unit
        ref: "tests/Feature/FrontDesk/SentTimeTest.php#says when a message happened, without changing the model"
        status: pass
    human_judgment: false
  - id: D4
    description: "Texts sent before the change get sent_at from resolved_at or created_at; a stored sent_at and every unsent row are left alone"
    verification:
      - kind: integration
        ref: "tests/Feature/FrontDesk/SentTimeTest.php#backfills the send time of texts sent before this change"
        status: pass
    human_judgment: false
  - id: D5
    description: "/dev/chat and the conversation screen list and label each message by when it happened"
    verification:
      - kind: integration
        ref: "tests/Feature/FrontDesk/SentTimeTest.php#lists the sent draft after the holding reply in /dev/chat"
        status: pass
      - kind: integration
        ref: "tests/Feature/FrontDesk/SentTimeTest.php#shows each message at the time it happened"
        status: pass
    human_judgment: false
  - id: D6
    description: "The database still refuses a second live draft in one conversation after the migration (D-28)"
    verification:
      - kind: integration
        ref: "tests/Feature/FrontDesk/DraftReplacementTest.php (D-28 index tests)"
        status: pass
    human_judgment: false
  - id: D7
    description: "UAT Test 1 order check by hand in the running app"
    verification: []
    human_judgment: true
    rationale: "The executor was told not to run live hand checks; carried to end-of-phase UAT (see Human checks carried to UAT)"

duration: 6min
completed: 2026-10-13
status: complete
---

# Phase 2 Plan 36: Send time on messages and thread order by when it happened Summary

**messages.sent_at written by the one outbound path, a backfill for old texts, and threads sorted and labelled by coalesce(sent_at, created_at), so a held draft the owner sends later is listed after the holding reply on the same row**

## Performance

- **Duration:** 6 min
- **Started:** 2026-10-13T14:37:00Z
- **Completed:** 2026-10-13T14:43:00Z
- **Tasks:** 2
- **Files modified:** 9

## Accomplishments
- Closed the core of G-02-1.2: after Send, the sent draft is listed after the holding reply on both the conversation screen and /dev/chat, with its own send time.
- `OutboundSender::transmit()` takes one `$sentAt` before the transaction and writes it to the message, the activity, the conversation and the contact; the record-failure fallback writes the same `sent_at`.
- `Message::happenedAt()` is the one "when this happened" (immutable copy), used by both views; `Conversation::messages()` orders by it, so ConversationThread, DevChat, FrontDeskPrompt::history() and RecordsWriter inherit the order.
- The partial unique index `messages_one_live_draft_per_conversation` is dropped and recreated in up() and down() of the schema migration (T-02-83); the D-28 tests pass.
- Backfill migration gives old sent or delivered texts `coalesce(resolved_at, created_at)` and touches nothing else (T-02-85).

## Task Commits

1. **Task 1: Tracer, a held draft sent after the holding reply is listed after it**
   - RED `8438551` (test): five failing tests for send time and thread order
   - GREEN `be007aa` (feat): sent_at column, happenedAt(), OutboundSender writes, send-time ordering
2. **Task 2: Backfill and time labels**
   - RED `c94b017` (test): three failing tests for the backfill and the labels
   - GREEN `9c736d6` (feat): backfill migration, both views on happenedAt(), walk-through log line

**Plan metadata:** committed separately (docs: complete plan)

## TDD evidence

- **RED, Task 1:** order test failed on the planned assertion (draft listed before the holding reply, ids [1,2,3] vs expected [1,3,2]); send-time tests failed on `sent_at` null; the `happenedAt()` test failed on the undefined method. The "leaves sent_at empty" test passed before the change because the attribute read as null on an absent column; it guards the other direction and stays as a regression test.
- **RED, Task 2:** the backfill test failed because the migration file did not exist; the label test failed because '4:33 pm' was never shown (labels read created_at). The /dev/chat order test was already green after Task 1, as expected, since the order lives on the relation; it stays as the regression test for that view.
- **Tracer gate:** Task 1 verify (46 tests: SentTimeTest, DraftReplacementTest, DraftReviewTest, OwnerReplyTest) re-run end-to-end after the change and passing before Task 2 started.

## Files Created/Modified
- `database/migrations/2026_10_12_000010_add_sent_at_to_messages_table.php` - nullable sent_at, partial unique index recreated in up() and down()
- `database/migrations/2026_10_12_000011_backfill_sent_at_on_messages.php` - sent_at for texts sent before this change
- `app/Models/Message.php` - sent_at fillable and cast, `happenedAt()`
- `app/Models/Conversation.php` - `messages()` orders by `coalesce(sent_at, created_at)` then id; docblock rewritten
- `app/Actions/OutboundSender.php` - `$sentAt` taken once, written on transmit and in `markSentAfterRecordingFailed()`
- `resources/views/livewire/conversation-thread.blade.php`, `resources/views/livewire/dev-chat.blade.php` - time label via `happenedAt()`
- `tests/Feature/FrontDesk/SentTimeTest.php` - eight tests
- `docs/sprints/sprint-1.md` - walk-through log line "(plan 02-36)"

## Decisions Made
- Followed the plan: the send time lives on the existing row, so D-54 (same row, original created_at) and D-28 hold. No new decisions to record in PROJECT.md.

## Deviations from Plan

None - plan executed exactly as written.

One test-code fix before the first RED commit: the sibling-style helper `sentTimeOpenConversation()` first used Dana's phone (+12025550123), which collided with Dana's contact in the same test; it uses +12025550177 (Pat Rivera) instead. Test-only, caught before the RED commit. The /dev/chat order assertion uses a sentence from the holding reply rather than its whole body, because the body carries HTML-escaped apostrophes and an ampersand and a multi-line match was brittle.

## Issues Encountered
- BSD `sed -i` failed in this environment; the two view edits were made with the Edit tool instead. No effect on the result.

## Known Stubs

None.

## Threat Flags

None. No new endpoints, auth paths or trust-boundary schema beyond the plan's threat model (T-02-83, T-02-84, T-02-85 mitigated as planned).

## Human checks carried to UAT

Ian's standing decision for this run: no live hand check pauses execution. Task 2's human-check was not run by the executor and is recorded in `.planning/WINDOWS.md` (entry 7, unrun-verify). Hand-check steps, carried to end-of-phase UAT:

**UAT Test 1, the order part:** run `php artisan serve`, `php artisan queue:listen` and `php artisan schedule:work`. Text "How much for a new unit?" from /dev/chat. When the held draft and the holding reply have appeared, wait a minute or two, open the conversation in /admin and press Send. Expect /dev/chat and the conversation screen to list the holding reply first and the sent reply after it, each with its own time.

`php artisan migrate --no-interaction` has already run on the local database, so Ian's existing UAT rows show the new order.

## Verification
- `php artisan test --compact tests/Feature/FrontDesk/SentTimeTest.php tests/Feature/FrontDesk/DraftReplacementTest.php tests/Feature/Filament/DraftReviewTest.php tests/Feature/Inbound/OwnerReplyTest.php`: 46 passed.
- `php artisan test --compact tests/Feature/FrontDesk/SentTimeTest.php tests/Feature/DevChatTest.php tests/Feature/Filament/ConversationViewTest.php tests/Feature/Inbound/DispatchInboundTest.php`: 44 passed.
- `php artisan test --compact` (full suite, SQLite): 1049 passed, 4344 assertions.
- The Postgres leg from 02-VALIDATION.md was not run here; `coalesce` and the partial index are standard on both drivers (left for CI).
- Local SQLite database: `messages_one_live_draft_per_conversation` is still the partial index after migrating.

## Next Phase Readiness
- 02-37 can move the holding reply, reply cap and records readers onto `sent_at` / `happenedAt()`; 02-38 filters /dev/chat; 02-39 uses it in the prompt; 02-41 re-points `map/`.

## Self-Check: PASSED

All created files exist on disk, and commits 8438551, be007aa, c94b017 and 9c736d6 are on gsd/phase-02-text-front-desk. Acceptance criteria re-run: migration contains sent_at, the index name and its recreation in up() and down(); Message has `happenedAt(): CarbonImmutable` and the `sent_at` cast; Conversation has the coalesce order; OutboundSender writes `'sent_at' => $sentAt` twice; both views use `happenedAt()`; sprint-1.md contains "(plan 02-36)" once; the full suite is green.

---
*Phase: 02-text-front-desk*
*Completed: 2026-10-13*
