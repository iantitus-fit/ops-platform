---
phase: 02-text-front-desk
plan: 38
subsystem: messaging
tags: [dev-chat, message-status, gap-closure, laravel, pest]

requires:
  - phase: 02-text-front-desk
    provides: held drafts (D-54), withdrawn drafts, send-time thread order from 02-36
provides:
  - MessageStatus::onTheCustomersPhone(): the one list of statuses a customer has (received, sent, delivered)
  - /dev/chat lists only what is on the customer's phone
  - FrontDeskPrompt::history() reads the same list
affects: [02-39 (edits history() and RULES next), 02-41 (map re-point)]

actuals:
  tokens: 5000
  tasks: 2
  commits: 3
plan_head_before: c4fe52459544d49558eb608d36682fea26954ba7
plan_head_after: 76efe01f83d07935840e61de0e416777753105cd

tech-stack:
  added: []
  patterns:
    - "A rule used by more than one reader lives as a static method on the enum it is about, not as a private constant in one reader"

key-files:
  created: []
  modified:
    - app/Enums/MessageStatus.php
    - app/Livewire/DevChat.php
    - app/Ai/FrontDeskPrompt.php
    - tests/Feature/DevChatTest.php
    - docs/sprints/sprint-1.md

key-decisions:
  - "What a customer has on their phone is defined once, MessageStatus::onTheCustomersPhone() (received, sent, delivered); ConversationThread keeps its own filter so the owner still sees queued and failed texts"

patterns-established:
  - "DevChat tests use a devChat-prefixed helper set because Pest helpers are global"

requirements-completed: [DASH-02, GATE-04]

coverage:
  - id: D1
    description: "/dev/chat never shows a held draft: it shows the customer's text and the holding reply, and not the draft's words"
    requirement: GATE-04
    verification:
      - kind: integration
        ref: "tests/Feature/DevChatTest.php#leaves a held draft off the dev chat page (G-02-2.3)"
        status: pass
    human_judgment: false
  - id: D2
    description: "/dev/chat never shows a withdrawn (discarded or replaced), queued or failed text"
    requirement: GATE-04
    verification:
      - kind: integration
        ref: "tests/Feature/DevChatTest.php#leaves withdrawn, queued and failed texts off the page"
        status: pass
    human_judgment: false
  - id: D3
    description: "/dev/chat shows the customer's texts and every text that reached them (sent, delivered, owner-sent)"
    requirement: DASH-02
    verification:
      - kind: integration
        ref: "tests/Feature/DevChatTest.php#shows the customer's texts and every text that reached them"
        status: pass
    human_judgment: false
  - id: D4
    description: "A held draft appears on /dev/chat once the owner sends it"
    requirement: GATE-04
    verification:
      - kind: integration
        ref: "tests/Feature/DevChatTest.php#shows a held draft once the owner sends it"
        status: pass
    human_judgment: false
  - id: D5
    description: "The customer's-phone statuses are defined once and the model's history reads the same list"
    verification:
      - kind: unit
        ref: "tests/Feature/DevChatTest.php#names the statuses a customer has on their phone"
        status: pass
      - kind: integration
        ref: "tests/Feature/FrontDesk/FrontDeskPromptTest.php#leaves drafts, failed sends and texts not yet sent out of the history"
        status: pass
      - kind: integration
        ref: "tests/Feature/FrontDesk/DraftReplacementTest.php#leaves a withdrawn draft out of the history the next run reads"
        status: pass
    human_judgment: false
  - id: D6
    description: "/dev/chat by hand after a held draft: only the customer's text and the holding reply, then the sent reply after Send"
    verification: []
    human_judgment: true
    rationale: "The executor was told not to run live hand checks; carried to end-of-phase UAT (see Human checks carried to UAT)"

duration: 8min
completed: 2026-10-13
status: complete
---

# Phase 2 Plan 38: /dev/chat shows only what is on the customer's phone Summary

**MessageStatus::onTheCustomersPhone() (received, sent, delivered) is the one definition of what a customer has; /dev/chat filters its thread by it, so held, withdrawn, queued and failed texts stay off the page, and the model's history reads the same list**

## Performance

- **Duration:** 8 min
- **Started:** 2026-10-13T14:44:00Z
- **Completed:** 2026-10-13T14:52:00Z
- **Tasks:** 2
- **Files modified:** 5

## Accomplishments
- Closed G-02-2.3: a price question held for the owner no longer shows the draft's words on /dev/chat; the page shows Dana's text and the holding reply, and the sent reply appears once the owner presses Send, at its send time (02-36 order stands).
- `DevChat::thread()` adds one condition, `whereIn('status', MessageStatus::onTheCustomersPhone())`, and no ordering, so the relation's send-time order is untouched.
- `FrontDeskPrompt` lost its private `SHOWN_STATUSES` constant; `history()` reads the enum method. Behaviour is unchanged, so the two existing history tests pass untouched.
- `ConversationThread` is untouched: the owner still sees queued and failed texts with their badges.

## Task Commits

1. **Task 1: Tracer, /dev/chat shows only what is on the customer's phone**
   - RED `0a1fd19` (test): five tests for held, withdrawn, queued, failed, delivered and owner texts, the sent draft, and the enum list
   - GREEN `781148c` (fix): `MessageStatus::onTheCustomersPhone()` and the `whereIn` in `DevChat::thread()`
2. **Task 2: The model's history reads the same definition**
   - `76efe01` (refactor): constant removed, `history()` reads the enum method, walk-through log line

**Plan metadata:** committed separately (docs: complete plan)

## TDD evidence

- **RED, Task 1:** the held-draft test failed on "Expecting [...] not to contain 2" (the draft id was in the thread), the withdrawn/queued/failed test failed on the first withdrawn id, and the enum test failed on the undefined method. "shows the customer's texts..." and "shows a held draft once the owner sends it" passed before the change, as expected: the page showed everything, so they guard the other direction and stay as regression tests.
- **Mutation check:** with the `whereIn` removed from `DevChat::thread()`, `DevChatTest.php` ran 19 tests with 2 failing (the held-draft test and the withdrawn/queued/failed test); the `whereIn` was restored and all pass.
- **Tracer gate:** Task 1 verify (`tests/Feature/DevChatTest.php`, `tests/Feature/FrontDesk/SentTimeTest.php`) re-run end-to-end after the fix: 27 passed. Logged "Tracer verified end-to-end, expanding", then Task 2 started.
- **Task 2 (refactor under existing tests), before and after:** `FrontDeskPromptTest.php` plus `DraftReplacementTest.php` ran 21 passed before the change and 21 passed after. `tests/Feature/FrontDesk`: 245 passed.

## Files Created/Modified
- `app/Enums/MessageStatus.php` - `onTheCustomersPhone()` returning Received, Sent, Delivered
- `app/Livewire/DevChat.php` - `thread()` limited to those statuses; docblock explains why
- `app/Ai/FrontDeskPrompt.php` - private status constant removed; `history()` and its docblock name the enum method
- `tests/Feature/DevChatTest.php` - five new tests and a `devChat`-prefixed helper set
- `docs/sprints/sprint-1.md` - walk-through log line "(plan 02-38)"

## Decisions Made
- Followed the plan. No new decision for PROJECT.md: the rule "a simulated customer phone shows what a customer would have" is recorded in the plan and in the enum docblock.

## Deviations from Plan

None - plan executed exactly as written.

One test-code fix before the RED commit: the helper first used `test()->app['env']`, which is a protected property on the Pest test case; it uses `app()['env']` instead. Test-only.

## Issues Encountered
None.

## Known Stubs

None.

## Threat Flags

None. T-02-89 and T-02-90 are mitigated as planned (one definition, tests for draft, withdrawn, queued and failed rows, a mutation check; /dev/chat stays local-only and its 404 tests pass).

## Human checks carried to UAT

Ian's standing decision for this run: no live hand check pauses execution. Task 1's human-check was not run by the executor and is recorded in `.planning/WINDOWS.md` (entry 8, unrun-verify). Hand-check steps, carried to end-of-phase UAT:

**/dev/chat after a held draft (G-02-2.3):** run `php artisan serve` and `php artisan queue:listen`. Text "How much for a new unit?" from /dev/chat. Expect only your text and the holding reply ("This is the virtual assistant for ...") on the page, and no draft. Press Send on the conversation screen in /admin; the sent reply then appears below the holding reply.

## Verification
- `php artisan test --compact tests/Feature/DevChatTest.php tests/Feature/FrontDesk/SentTimeTest.php`: 27 passed.
- `php artisan test --compact tests/Feature/FrontDesk`: 245 passed.
- `php artisan test --compact` (full suite, SQLite): 1058 passed, 4395 assertions.
- The Postgres leg from 02-VALIDATION.md was not run here; the change is a plain `where status in (...)` on both drivers (left for CI).
- Acceptance criteria: `MessageStatus` has `public static function onTheCustomersPhone(): array`; `DevChat` contains `whereIn('status', MessageStatus::onTheCustomersPhone())`; `git log --grep='(02-38)' -- app/Livewire/ConversationThread.php` prints nothing; `grep -c SHOWN_STATUSES app/Ai/FrontDeskPrompt.php` prints 0; the `test(02-38)` commit precedes the `fix(02-38)` commit.

## Next Phase Readiness
- 02-39 can edit `FrontDeskPrompt::history()` and RULES; its status check already reads the shared list.

## Self-Check: PASSED

All modified files exist, commits 0a1fd19, 781148c and 76efe01 are on gsd/phase-02-text-front-desk, and the full suite is green.

---
*Phase: 02-text-front-desk*
*Completed: 2026-10-13*
