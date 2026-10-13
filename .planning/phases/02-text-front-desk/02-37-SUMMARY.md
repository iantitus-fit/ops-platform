---
phase: 02-text-front-desk
plan: 37
subsystem: messaging
tags: [holding-reply, reply-cap, open-leads, sent_at, gap-closure, laravel, pest]

requires:
  - phase: 02-text-front-desk
    provides: messages.sent_at, Message::happenedAt() and the coalesce(sent_at, created_at) order on Conversation::messages() (plan 02-36)
provides:
  - SendFixedReply::holdingAlreadySent() judges against the newest holding reply (reorder('id', 'desc'))
  - RunFrontDesk::replyCapReached() windows on coalesce(sent_at, created_at)
  - RecordsWriter::waitingSince() reports times from Message::happenedAt()
affects: [02-38 (/dev/chat filter), 02-39 (prompt history), 02-40 (Needs you card), 02-41 (map re-point: RunFrontDesk.php:239-246 keeps its line numbers)]

actuals:
  tokens: 3400
  tasks: 3
  commits: 6
plan_head_before: ebef7c5a8a4a1202662194e89c7e332b35b3a2cf
plan_head_after: 2a48966a1b8f36d61078f69ec3c45d3c35c156a1

tech-stack:
  added: []
  patterns:
    - "A newest-first lookup on Conversation::messages() uses reorder('id', 'desc'); latest()/oldest() only add a tie-break after the relation's own order"
    - "Time-window counts on messages compare coalesce(sent_at, created_at) with the Carbon bound as a raw-where parameter"

key-files:
  created: []
  modified:
    - app/Actions/SendFixedReply.php
    - app/Actions/RunFrontDesk.php
    - app/Records/RecordsWriter.php
    - tests/Feature/FrontDesk/HoldingReplyTest.php
    - tests/Feature/FrontDesk/ReplyCapTest.php
    - tests/Feature/Records/SummaryFilesTest.php
    - docs/sprints/sprint-1.md

key-decisions:
  - "Followed the plan: the two 'answered since' rules in holdingAlreadySent() keep their id and resolved_at logic; only the lookup of the newest holding reply changed"
  - "Reply cap counts by when a text went out (G-02-1.2 review decision); a queued text has no send time and counts at creation"

requirements-completed: [GATE-01, GATE-02]

coverage:
  - id: D1
    description: "After one holding reply was answered and a second went out, a third text replacing the waiting draft gets no third holding reply (D-14)"
    requirement: GATE-01
    verification:
      - kind: integration
        ref: "tests/Feature/FrontDesk/HoldingReplyTest.php#sends no third holding reply while the newest one is still unanswered (D-14)"
        status: pass
    human_judgment: false
  - id: D2
    description: "The reply cap counts a draft the owner sent within the hour (or the day) at its send time, though it was written earlier (D-52); queued texts, old texts, drafts, failed sends and the safety line behave as before"
    requirement: GATE-02
    verification:
      - kind: integration
        ref: "tests/Feature/FrontDesk/ReplyCapTest.php#counts a draft the owner sent within the hour, though it was written earlier (D-52)"
        status: pass
      - kind: integration
        ref: "tests/Feature/FrontDesk/ReplyCapTest.php#counts a draft sent within the day by its send time (D-52)"
        status: pass
      - kind: integration
        ref: "tests/Feature/FrontDesk/ReplyCapTest.php#does not count texts older than 24 hours, drafts, failed sends or the safety line (D-52)"
        status: pass
    human_judgment: false
  - id: D3
    description: "open-leads.md shows a draft the owner sent later as the last message, 'replied' at its send time in the business zone"
    requirement: GATE-01
    verification:
      - kind: integration
        ref: "tests/Feature/Records/SummaryFilesTest.php#reports a draft the owner sent later as the last reply, at its send time"
        status: pass
    human_judgment: false

duration: 3min
completed: 2026-10-13
status: complete
---

# Phase 2 Plan 37: Holding reply, reply cap and open-leads move onto the send time Summary

**The newest holding reply (found with reorder) decides whether another goes out, the reply cap windows on coalesce(sent_at, created_at), and open-leads.md reports replies at Message::happenedAt(), closing G-02-1.2 together with 02-36**

## Performance

- **Duration:** 3 min
- **Started:** 2026-10-13T14:45:29Z
- **Completed:** 2026-10-13T14:48:00Z
- **Tasks:** 3
- **Files modified:** 7

## Accomplishments
- `SendFixedReply::holdingAlreadySent()` now finds the newest queued, sent or delivered holding reply with `reorder('id', 'desc')`. Before, `latest('id')` only added a tie-break after the relation's oldest-first order, so a conversation with two holding replies compared against the oldest and could send a third (T-02-86).
- `RunFrontDesk::replyCapReached()` compares `coalesce(sent_at, created_at)` in both windows, so an approved draft written hours ago counts when it went out (T-02-87). The method kept its line count: `map/` still cites `RunFrontDesk.php:239-246`, and the two comparisons are on lines 245-246.
- `RecordsWriter::waitingSince()` reads `happenedAt()` for the "replied" time and the start of the unanswered run; `time()` takes a `CarbonInterface` (T-02-88).
- Walk-through log line "(plan 02-37)" added once to `docs/sprints/sprint-1.md`.

## Task Commits

1. **Task 1: Tracer, the newest holding reply decides (D-14)**
   - RED `f9e5911` (test): failing test with 3 holding replies where 2 were expected
   - GREEN `33f5ab7` (fix): reorder('id', 'desc'), docblock rewritten, walk-through line
2. **Task 2: Reply cap counts by send time (D-52)**
   - RED `c7e2c2b` (test): two failing tests, hour and day
   - GREEN `bb68f83` (fix): both windows on coalesce(sent_at, created_at)
3. **Task 3: open-leads.md at the time it was sent**
   - RED `3c8e3b5` (test): failing on 09:00 versus 09:10
   - GREEN `2a48966` (fix): happenedAt() in waitingSince(), CarbonInterface in time()

**Plan metadata:** committed separately (docs: complete plan)

## TDD evidence

- **RED, Task 1:** "Failed asserting that actual size 3 matches expected size 2" (a third holding reply went out).
- **RED, Task 2:** both new tests ended with "No query results for model [OwnerAlert]": the front desk ran instead of stopping at the cap, so no cap alert existed. Failure is the planned one.
- **RED, Task 3:** the row read `replied 2026-10-11 09:00` where `09:10` was expected.
- **Tracer gate:** Task 1 verify (HoldingReplyTest, DraftReplacementTest, DraftReviewTest: 34 tests) re-run end to end after the fix and passing before Task 2 started.

## latest( / oldest( on messages() in app/

`git grep -nE "(latest|oldest)\(" -- app` found:
- `app/Actions/SendFixedReply.php:97` `->latest('id')` on `messages()`: fixed, now `reorder('id', 'desc')`.
- `app/Models/Conversation.php:68`: a docblock sentence warning about this exact trap; no code.
- `app/Actions/OwnerAlertText.php:121-125`: already `reorder('id', 'desc')` (the precedent), not matched by the grep.
- Not on `messages()` and left alone: `app/Jobs/ProcessInbound.php:157` (`Message::query()` with its own where clauses, no relation order), `app/Livewire/DevChat.php:88` (`conversations()`), `app/Filament/Pages/BusinessFolder.php`, `app/Icm/*` (other models).

No other consumer chains `latest()` or `oldest()` on `messages()`.

## Files Created/Modified
- `app/Actions/SendFixedReply.php` - newest holding reply via `reorder('id', 'desc')`; docblock no longer says drafts keep their place in the thread
- `app/Actions/RunFrontDesk.php` - reply cap windows on `coalesce(sent_at, created_at)`; docblock says each text counts when it went out
- `app/Records/RecordsWriter.php` - `waitingSince()` on `happenedAt()`; `time()` accepts `CarbonInterface`; import swapped from `Illuminate\Support\Carbon` to `Carbon\CarbonInterface`
- `tests/Feature/FrontDesk/HoldingReplyTest.php` - one test (no third holding reply)
- `tests/Feature/FrontDesk/ReplyCapTest.php` - two tests (hour and day by send time)
- `tests/Feature/Records/SummaryFilesTest.php` - one test (sent draft in open-leads.md)
- `docs/sprints/sprint-1.md` - walk-through log line "(plan 02-37)"

## Decisions Made
- None new. Followed the G-02-1.2 review decisions recorded in the plan (the "answered since" rules stay as they are; the cap counts by send time).

## Deviations from Plan

None - plan executed exactly as written.

Two small notes, neither a deviation: the replyCapReached() docblock was reworded (not only appended to) so the method kept its line count for `map/`'s `RunFrontDesk.php:239-246` citation; and the ReplyCap test names carry "(D-52)" for consistency with their siblings.

## Issues Encountered

None.

## Known Stubs

None.

## Threat Flags

None. No new endpoints, auth paths or schema; T-02-86, T-02-87 and T-02-88 mitigated as planned.

## User Setup Required

None - no external service configuration required.

## Verification
- `php artisan test --compact tests/Feature/FrontDesk/HoldingReplyTest.php tests/Feature/FrontDesk/DraftReplacementTest.php tests/Feature/Filament/DraftReviewTest.php`: 34 passed.
- `php artisan test --compact tests/Feature/FrontDesk/ReplyCapTest.php tests/Feature/FrontDesk/LifeSafetyTest.php`: 33 passed.
- `php artisan test --compact tests/Feature/Records`: 42 passed.
- `php artisan test --compact` (full suite, SQLite): 1053 passed, 4370 assertions.
- The Postgres leg from 02-VALIDATION.md was not run here (`coalesce` and a bound timestamp are standard on both drivers; left for CI).

## Next Phase Readiness
- 02-38 (/dev/chat filter), 02-39 (prompt history), 02-40 (Needs you card) and 02-41 (map re-point) can proceed. 02-41 note: the `replyCapReached()` body lines are unchanged in number, so the `RunFrontDesk.php:239-246` citation still points at the same statements.

## Self-Check: PASSED

Modified files exist on disk; commits f9e5911, 33f5ab7, c7e2c2b, bb68f83, 3c8e3b5 and 2a48966 are on gsd/phase-02-text-front-desk. Acceptance criteria re-run: SendFixedReply contains `reorder('id', 'desc')`; RunFrontDesk contains `coalesce(sent_at, created_at)` twice in replyCapReached(); RecordsWriter contains `happenedAt()` in waitingSince(); sprint-1.md contains "(plan 02-37)" once; `test(02-37)` commits precede each fix commit; the full suite is green.

---
*Phase: 02-text-front-desk*
*Completed: 2026-10-13*
