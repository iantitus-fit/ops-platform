---
phase: 02-text-front-desk
plan: 40
subsystem: filament-ui
tags: [needs-you-card, conversation-thread, gap-closure, livewire, blade, pest]

requires:
  - phase: 02-text-front-desk
    provides: Message::happenedAt() and send-time thread order (02-36), the Needs you card with draft, Send and Discard (02-22, D-29)
provides:
  - ConversationThread::lastCustomerMessage(): the newest inbound message, as a Computed property
  - A "Customer wrote, {time}:" line inside the Needs you card, squished, cut to 160 characters and escaped
  - Phase 02.1's Scope no longer carries the Needs you card suggestion
affects: [02-41 (map re-point), end-of-phase UAT Test 1]

actuals:
  tokens: 3500
  tasks: 2
  commits: 3
plan_head_before: 5187a5622435737ff0c8fc3f3290eaf8a5414ab9
plan_head_after: d63d51ab67a85c26b242c4800d6e9cfa249e7826

tech-stack:
  added: []
  patterns:
    - "Short text in a small space: Str::limit(Str::squish($text), 160), echoed with Blade's escaping {{ }}"

key-files:
  created: []
  modified:
    - app/Livewire/ConversationThread.php
    - resources/views/livewire/conversation-thread.blade.php
    - tests/Feature/Filament/ConversationViewTest.php
    - docs/sprints/sprint-1.md
    - .planning/ROADMAP.md

key-decisions:
  - "The card's customer line finds the newest inbound text by id, the way OwnerAlertText does, so the card and the owner's alert show the same words"

requirements-completed: [GATE-04]

coverage:
  - id: D1
    description: "A held draft's Needs you card shows the customer's last message with its time in the business zone, between the held reason and the draft box, above Send and Discard; an older customer text is not in the card"
    requirement: GATE-04
    verification:
      - kind: integration
        ref: "tests/Feature/Filament/ConversationViewTest.php#shows the customer's last message in the Needs you card, above the draft and Send (G-02-1.1)"
        status: pass
    human_judgment: false
  - id: D2
    description: "A long customer message is cut to 160 characters on one collapsed line in the card and shown whole in the thread"
    verification:
      - kind: integration
        ref: "tests/Feature/Filament/ConversationViewTest.php#cuts a long last message in the card and shows it whole in the thread (G-02-1.1)"
        status: pass
    human_judgment: false
  - id: D3
    description: "The customer's words are escaped in the card (T-02-95)"
    verification:
      - kind: integration
        ref: "tests/Feature/Filament/ConversationViewTest.php#escapes the customer's words in the card (T-02-45)"
        status: pass
    human_judgment: false
  - id: D4
    description: "No Customer wrote line without a held draft; the scroll box, 60vh limit and collapsed customer details are unchanged"
    verification:
      - kind: integration
        ref: "tests/Feature/Filament/ConversationViewTest.php#shows no Customer wrote line without a held draft (G-02-1.1)"
        status: pass
      - kind: integration
        ref: "tests/Feature/Filament/ConversationViewTest.php#opens the thread at the latest message inside a bounded box (D-29)"
        status: pass
    human_judgment: false
  - id: D5
    description: "At 390x664 or on a phone, the held reason, Customer wrote line, draft, Send and Discard are all visible without scrolling, with a short thread, a long thread and a long customer text"
    verification: []
    human_judgment: true
    rationale: "No browser-test package exists and none may be added without Ian's approval; the layout is a hand check, carried to end-of-phase UAT"

duration: 4min
completed: 2026-10-13
status: complete
---

# Phase 2 Plan 40: Customer's last message in the Needs you card Summary

**The Needs you card now shows "Customer wrote, {time}:" with the newest inbound text (squished, cut to 160 characters, escaped) between the held reason and the draft box, so the owner sees what they are answering without scrolling; the thread box is untouched (G-02-1.1).**

## Performance

- **Duration:** 4 min
- **Started:** 2026-10-13T14:59:50Z
- **Completed:** 2026-10-13T15:04:00Z
- **Tasks:** 2
- **Files modified:** 5

## Accomplishments
- `ConversationThread::lastCustomerMessage()` (`#[Computed]`) returns the newest inbound message with `reorder('id', 'desc')` and direction In, the same way `OwnerAlertText` finds it.
- The Needs you section gets one paragraph, in the style of "The assistant wrote:", labelled "Customer wrote, {M j, g:i a in the business zone}:" using `happenedAt()`; the words are `Str::limit(Str::squish(body), 160)`, or "(no words)" for a picture-only text. All values go through Blade's escaping echo.
- The thread box, its `x-init` scroll attribute, `max-height: 60vh` and `overflow-y: auto`, the paused banner and the reply form are unchanged (verified by the diff and by the existing D-29 tests).
- Phase 02.1's Scope bullet "Carried from Phase 2 UAT ... Test 1" is deleted with one scoped line removal (0 insertions, 1 deletion); the neighbouring "Carried from the Phase 2 UI review" bullet stays.
- Walk-through log line "Shortening text for a small space (plan 02-40)" added at the end of `docs/sprints/sprint-1.md`.

## Task Commits

1. **Task 1: Tracer, the Needs you card shows what the customer last said**
   - RED `73a36ba` (test): four tests for the card line, the 160-character cut, escaping and the no-draft case
   - GREEN `208b5aa` (feat): `lastCustomerMessage()`, the card line, walk-through log line, test setup fix (see Deviations)
2. **Task 2: Remove the landed suggestion from Phase 02.1's scope**
   - `d63d51a` (docs): one ROADMAP.md line removed

**Plan metadata:** committed separately (docs: complete plan)

## TDD evidence

- **RED:** three of the four new tests failed for the planned reason (no "Customer wrote" line; the 160-character cut and the escaped words were absent from the card). The "no Customer wrote line without a held draft" test passed before the change, as expected for a negative case; it stays as a regression test.
- **Tracer gate:** Task 1's verify (ConversationViewTest, DraftReviewTest, OwnerReplyTest: 44 tests) re-run after the commit and passing before Task 2 started. The human-check was not run (see below).

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] The first test's setup made the "older" text the one with the higher id**
- **Found during:** Task 1 GREEN
- **Issue:** The RED test created 'Hi, my AC is acting up' after the helper's own first text, so although its `created_at` was 20 minutes ago, it had the higher id and `reorder('id', 'desc')` correctly returned it. The card showed it with the 4:10 pm time. The code matched the plan (and `OwnerAlertText`); the test fixture was unrealistic, because an earlier text always has the lower id.
- **Fix:** The test now turns the helper's first text into the 20-minutes-ago 'Hi, my AC is acting up' (lowest id) and creates the held question as a later row. Production code kept `reorder('id', 'desc')` as the plan and acceptance criteria require.
- **Files modified:** tests/Feature/Filament/ConversationViewTest.php
- **Commit:** 208b5aa (the RED commit 73a36ba is kept as it was; history is not rewritten)

**Total deviations:** 1 auto-fixed (test fixture). **Impact:** none on behavior or scope.

## Human checks carried to UAT

Ian's standing decision for this run: the Task 1 live hand check is carried forward to end-of-phase UAT and no checkpoint was returned. It is recorded in `.planning/WINDOWS.md` (entry 10, unrun-verify). Steps, carried to UAT:

**UAT Test 1 layout re-run (390x664 or a real phone):**
1. Run `php artisan serve --host=0.0.0.0`, `php artisan queue:listen` and `php artisan schedule:work`.
2. Text "How much for a new unit?" from /dev/chat so a draft is held.
3. Open the alert link on a real phone over Wi-Fi, or in Chrome device mode at 390x664.
4. Expect, without scrolling: the held reason, a "Customer wrote" line with your text and its time, the draft, Send and Discard; customer details collapsed.
5. Scroll down: the thread is oldest-first and its box ends at the latest message.
6. Repeat with a long thread (about 14 messages) and with a long customer text (several hundred characters): Send is still visible without scrolling.

## Issues Encountered

None.

## Known Stubs

None.

## Threat Flags

None. T-02-95 (markup in the customer's words) is mitigated by Blade's escaping echo and covered by the escaping test; T-02-96 (long text pushes Send down) is mitigated by the 160-character cut and left to the hand check above; T-02-SC: no package installed, composer.json and package.json unchanged.

## Verification
- `php artisan test --compact tests/Feature/Filament/ConversationViewTest.php tests/Feature/Filament/DraftReviewTest.php tests/Feature/Inbound/OwnerReplyTest.php`: 44 passed.
- `php artisan test --compact` (full suite, SQLite): 1074 passed, 4449 assertions.
- The Postgres leg from 02-VALIDATION.md was not run here; this plan adds no query that differs by driver beyond the existing `reorder('id', 'desc')` pattern.
- Task 2: `grep -c 'answering without scrolling' .planning/ROADMAP.md` prints 0; the commit's numstat is `0 1 .planning/ROADMAP.md`.

## Next Phase Readiness
- 02-41 can re-point `map/` (the card's new customer line is part of the owner-facing screen). The automated layout test the diagnosis wanted still needs a browser-test package and Ian's approval.

## Self-Check: PASSED

Modified files exist; commits 73a36ba, 208b5aa and d63d51a are on gsd/phase-02-text-front-desk. Acceptance criteria re-run: `public function lastCustomerMessage(): ?Message` and `reorder('id', 'desc')` in ConversationThread.php; `Customer wrote`, `Str::limit(` and `Str::squish(` appear before `wire:submit="approveDraft"` in the view; no `x-init` line changed in the 02-40 commits; sprint-1.md contains "(plan 02-40)" once; the three test files pass; `test(02-40):` precedes the feat commit.

---
*Phase: 02-text-front-desk*
*Completed: 2026-10-13*
