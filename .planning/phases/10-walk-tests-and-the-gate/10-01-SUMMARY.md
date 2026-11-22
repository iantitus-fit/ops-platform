---
phase: 10-walk-tests-and-the-gate
plan: 01
subsystem: front-desk
tags: [laravel, pest, reply-cap, booking-text, message-kind, tdd]

requires:
  - phase: 02-text-front-desk
    provides: "D-52 reply cap in RunFrontDesk::replyCapReached"
  - phase: 08-schedule-and-booking
    provides: "booking_text kind, OutboundSender paused refusal (D-26, D-27)"
provides:
  - "MessageKind::notCountedByReplyCap(): the safety line and booking texts are left out of the reply cap (10 D-20)"
  - "D-21 pinned: a conversation the cap paused still refuses a reminder"
affects: [10-walk-tests, customer communication, safety and compliance, operations]

actuals:
  tokens: 5200
  tasks: 2
  commits: 3

tech-stack:
  added: []
  patterns:
    - "Kinds a rule leaves out live in MessageKind as a static list, so the booking text kind is named in only the three files tests/Unit/BookingTextKindTest.php allows"

key-files:
  created: []
  modified:
    - app/Enums/MessageKind.php
    - app/Actions/RunFrontDesk.php
    - tests/Feature/FrontDesk/ReplyCapTest.php
    - tests/Feature/Booking/BookingSendTest.php
    - map/objects/conversations/conversation-and-pause.md
    - map/objects/messages/message-and-verdict.md
    - docs/BLUEPRINT.md
    - .planning/WINDOWS.md

key-decisions:
  - "The left-out kinds sit inside MessageKind::notCountedByReplyCap() (LifeSafety, BookingText), not in RunFrontDesk, so BookingTextKindTest keeps its three-file list (plan's flagged assumption, followed as written)"

patterns-established:
  - "A kind the cap must not count is added to MessageKind::notCountedByReplyCap(); a new kind counts unless added there"

requirements-completed: [WALK-03]

duration: 12min
completed: 2026-11-22
status: complete
plan_head_before: 15652e457c46a315791422304b9bddc6ebe440b9
plan_head_after: 42507b7d44d2a44f76793a1e980bb150addaecaf

coverage:
  - id: D1
    description: "Booking confirmations and reminders no longer count toward the 6-an-hour or 20-a-day reply cap"
    requirement: WALK-03
    verification:
      - kind: unit
        ref: "tests/Feature/FrontDesk/ReplyCapTest.php#does not count booking confirmations or reminders in the hour (D-20)"
        status: pass
      - kind: unit
        ref: "tests/Feature/FrontDesk/ReplyCapTest.php#does not count booking texts in the day (D-20)"
        status: pass
    human_judgment: false
  - id: D2
    description: "Holding replies and every other kind still count, and the life-safety line stays out"
    requirement: WALK-03
    verification:
      - kind: unit
        ref: "tests/Feature/FrontDesk/ReplyCapTest.php#still counts holding replies beside booking texts (D-20)"
        status: pass
      - kind: unit
        ref: "tests/Feature/FrontDesk/ReplyCapTest.php (the seven D-52 cases, unchanged)"
        status: pass
    human_judgment: false
  - id: D3
    description: "A conversation the reply cap paused still refuses a reminder with one timeline note and no alert"
    requirement: WALK-03
    verification:
      - kind: unit
        ref: "tests/Feature/Booking/BookingSendTest.php#still refuses a reminder in a conversation the reply cap paused (D-21)"
        status: pass
    human_judgment: false
  - id: D4
    description: "Map cards and BLUEPRINT match the code"
    verification:
      - kind: other
        ref: "python3 ~/Projects/cartographer/tools/check_map.py ~/Projects/ops-platform --extra-root examples/example-hvac (RESULT: PASS)"
        status: pass
    human_judgment: true
    rationale: "check_map.py checks citations, not whether the card's words are true; a reader should confirm the Reply cap bullet reads right"
  - id: D5
    description: "Postgres leg: orWhereNotIn with enum bindings runs on the CI pgsql leg"
    verification: []
    human_judgment: true
    rationale: "Needs Ian to push the phase branch and read CI; recorded as WINDOWS.md entry 89, never a pause"
---

# Phase 10 Plan 01: The reply cap leaves out booking texts Summary

**The reply cap now counts only texts that answer a customer: `MessageKind::notCountedByReplyCap()` (safety line, booking text) is read by one changed line in `RunFrontDesk::replyCapReached`, so seven booking texts in an hour or twenty in a day no longer pause Dana, holding replies still count, and a cap pause still refuses a reminder.**

## Performance

- **Duration:** 12 min
- **Started:** 2026-11-22T18:33:30Z
- **Completed:** 2026-11-22T18:45:00Z
- **Tasks:** 2
- **Files modified:** 8 (7 planned, plus `.planning/WINDOWS.md`)

## Accomplishments

- D-20: booking confirmations and reminders no longer count toward either cap. The exception is one line at `RunFrontDesk.php:1587` (same line number) reading a new `MessageKind::notCountedByReplyCap()` (`MessageKind.php:77`). RunFrontDesk names neither kind's string nor the booking text case.
- Holding replies and every other automated kind still count; the safety line stays out as before. Six holding replies in the hour still stop the front desk with three booking texts beside them.
- D-21: a reminder in a conversation the cap paused is refused with the single note `Reminder text not sent: the conversation is paused.`, no text, no owner alert. Pinned by its own test; OutboundSender is unchanged.
- Map cards (`conversation-and-pause.md` :21 and :32), the message card and BLUEPRINT section 1 updated in the same commit as the code.

## Task Commits

1. **Task 1 RED: booking texts leave the reply cap (D-20)** - `1543c01` (test)
2. **Task 1 GREEN: booking texts do not count toward the reply cap (D-20)** - `ebef558` (feat; code, map cards and BLUEPRINT together)
3. **Task 2: a cap pause still refuses a reminder (D-21)** - `42507b7` (test only, no code commit by design)

**Plan metadata:** the `docs(10-01)` commit that holds this file, STATE, ROADMAP, REQUIREMENTS and WINDOWS.

## Files Created/Modified

- `app/Enums/MessageKind.php` - `notCountedByReplyCap()` returns `[LifeSafety, BookingText]`
- `app/Actions/RunFrontDesk.php` - `replyCapReached` uses `orWhereNotIn('kind', MessageKind::notCountedByReplyCap())`; docblock reworded inside its three lines
- `tests/Feature/FrontDesk/ReplyCapTest.php` - three D-20 cases appended
- `tests/Feature/Booking/BookingSendTest.php` - one D-21 case after the refusal dataset
- `map/objects/conversations/conversation-and-pause.md`, `map/objects/messages/message-and-verdict.md`, `docs/BLUEPRINT.md` - words match the code
- `.planning/WINDOWS.md` - entry 89, unrun-verify for the CI pgsql leg

## Decisions Made

None beyond the plan. The left-out kinds live in `MessageKind` (the plan's flagged assumption, which differs from 10-RESEARCH's suggested line), so `tests/Unit/BookingTextKindTest.php` passes with its three-file list unchanged.

## Deviations from Plan

One small correction to a test argument, no code deviation.

**1. [Rule 1 - Bug in plan text] The holding-replies case passes 9, not 6, to `replyCapAssertStopped`**
- **Found during:** Task 1 (RED)
- **Issue:** the plan says `replyCapAssertStopped($conversation, 6)`. That helper's second argument is the total count of outbound messages in the conversation (`ReplyCapTest.php:64-74`), and this case seeds three booking texts plus six holding replies, so nine outbound rows exist. Passing 6 would fail on a correct implementation.
- **Fix:** passed 9. The intent (the holding replies alone reach the cap and the conversation is paused with `PausedReason::ReplyCap`) is unchanged.
- **Files modified:** tests/Feature/FrontDesk/ReplyCapTest.php
- **Committed in:** `1543c01`

**Total deviations:** 1 (Rule 1). **Impact:** none on behavior.

## TDD Gate Compliance

RED commit `1543c01` precedes GREEN commit `ebef558`; no REFACTOR commit was needed. Task 2 is test-only by design (Ian's run rule 2): one `test(10-01)` commit, no code commit, OutboundSender not changed.

### RED evidence (commit 1543c01)

- Run: `vendor/bin/pest tests/Feature/FrontDesk/ReplyCapTest.php`: **10 tests, 2 failed, 8 passed**.
- Failed: `does not count booking confirmations or reminders in the hour (D-20)` and `does not count booking texts in the day (D-20)`. Both: `Failed asserting that actual size 0 matches expected size 1.` at the first assertion (`replyCapTextsToDana()` holds no text, because the booking texts counted, the cap tripped and the conversation was paused). That is the planned assertion failing for the planned reason.
- Passed on first run: `still counts holding replies beside booking texts (D-20)` (expected, it pins existing behavior) and the seven existing D-52 cases. The first-run-green new case is broken on purpose below.
- Machine classifier: the same run with `--log-junit` (record written inside the project, removed afterwards) returned `RED_EVIDENCE_OK`, reason `target_test_failed`, format `junit`, 10 tests, 8 pass, 2 fail, matched test `...ReplyCapTest#it does not count booking texts in the day (D-20)`.
- Semantic assessment: the target ran, failed on the planned assertion, no import, fixture or syntax fault.

### GREEN

`ReplyCapTest.php` plus `BookingTextKindTest.php`: 18 passed. `ReplyCapTest` alone had been 7 passed before the plan.

## Break-on-purpose: new tests that were green on their first run

Two new cases passed on their first run. Each was broken on purpose, seen RED, and restored byte for byte. The broken state was never committed.

**1. `still counts holding replies beside booking texts (D-20)`** (tests/Feature/FrontDesk/ReplyCapTest.php), run after the feat commit.
- File broken: `app/Enums/MessageKind.php` (backup in `/private/tmp/claude-501/scratch10/MessageKind.php.bak`).
- How: `notCountedByReplyCap()` made to return `[LifeSafety, BookingText, HoldingReply]`, so holding replies stop counting.
- RED line: `No query results for model [App\Models\OwnerAlert].` (`ModelNotFoundException` at `ReplyCapTest.php:66`, inside `replyCapAssertStopped`: the cap did not trip, so no alert exists). `Tests: 1 failed (2 assertions)`.
- sha256 before: `9e9afc40b83d7a56a65aab6ee68c7c155efdf6125ec3f4c6868a536c40801844`; after restore: `9e9afc40b83d7a56a65aab6ee68c7c155efdf6125ec3f4c6868a536c40801844` (match). `git diff -- app/Enums/MessageKind.php` is empty (0 bytes).

**2. `still refuses a reminder in a conversation the reply cap paused (D-21)`** (tests/Feature/Booking/BookingSendTest.php), run before its commit.
- File broken: `app/Actions/OutboundSender.php` (backup in `/private/tmp/claude-501/scratch10/OutboundSender.php.bak`).
- How: the paused refusal's condition changed to `$origin === SendOrigin::Automated && $conversation->isPaused() && false`, so a paused conversation no longer refuses.
- RED line: `Failed asserting that an array is empty.` at `BookingSendTest.php:178` (`textsSentToCustomers()` held the reminder). `Tests: 1 failed`.
- sha256 before: `a9b70c828e4322367008109ea17ac25b339462ceab0b727cd776a78c71afdfc1`; after restore: `a9b70c828e4322367008109ea17ac25b339462ceab0b727cd776a78c71afdfc1` (match). `git diff -- app/Actions/OutboundSender.php` is empty, and `git diff --stat main -- app/Actions/OutboundSender.php` prints nothing.

The two Task 1 RED cases failed on their first run, so they needed no break.

## Verification

- Acceptance criteria, Task 1: `notCountedByReplyCap(): array` present; `sed -n 1587p` holds `orWhereNotIn('kind', MessageKind::notCountedByReplyCap())`; `grep -c 'D-20' ReplyCapTest.php` prints 3; both map cards name `notCountedByReplyCap`; the BLUEPRINT `**Built:**` bullet holds `D-20, D-21`; test commit before feat commit. All PASS.
- Acceptance criteria, Task 2: `PausedReason::ReplyCap` and `D-21` in `BookingSendTest.php`; OutboundSender diff against main empty. All PASS.
- Line promises (`sed -n '1577,1579p;1581p;1587p;1591p' app/Actions/RunFrontDesk.php`):

```
     * Whether the assistant has already sent as many texts here as the reply cap allows, in the last hour or the last
     * 24 hours (D-52). Each text counts when it went out; one still queued counts at its creation, a second earlier.
     * Only sent or queued texts that answer a customer count; MessageKind::notCountedByReplyCap() names the two left out.
    private function replyCapReached(Conversation $conversation): bool
            ->where(fn ($query) => $query->whereNull('kind')->orWhereNotIn('kind', MessageKind::notCountedByReplyCap()));
    }
```

  :1581 is still `private function replyCapReached`, :1587 holds the new line, :1591 is the closing brace, the docblock is still three lines at :1577-1579.
- Plan `<verification>` `php artisan test --compact tests/Feature/FrontDesk tests/Feature/Booking tests/Unit/BookingTextKindTest.php`: **1282 passed** (14670 assertions), exit 0. The runner reported 1 warning with no detail text; none of the new or changed files is named.
- `vendor/bin/pint --dirty --format agent` ran before the feat commit and after the Task 2 test: passed both times.
- No existing test edited: `git diff 15652e4 -- tests/ | grep -E '^-[^-]'` (against this plan's start) prints nothing, so no line was removed or changed in any test file by this plan. `BookingTextKindTest.php` passes unchanged. Note on the literal command from the run rules: `git diff main -- tests/ | grep -E '^-[^-]'` prints two lines, both from plan 10-02's two forced fixture edits (`RouterClaimTest` and `RoomOwnershipTest`, already reported in the 10-02 SUMMARY); none comes from this plan.
- `check_map.py` full output after the feat commit (run again after the last commit, same result):

```
check_map: /Users/iantitus/Projects/ops-platform
  tracked files: 1122   extra roots: examples/example-hvac
  map files read: 8 (catalog, 6 cards, 1 other)
  citations checked: 605 path:line, 140 continuations, 64 file-only, 0 plain-text anchors, 2 patterns

FAILURES (0)
  none

UNCHECKED (0): seen, not held to a file
  none found

WHAT THIS CHECK CANNOT CATCH
  - A cited line that still exists but no longer says what the card claims. Code that moves within a file, or a file that grows, keeps every line number in range; only the length of the file is checked, never its text.
  - A file-name citation that matches exactly one tracked file today but not the file the author meant. The name is resolved, the intent is not.
  - Claims with no path:line behind them: table and column names, enum values, function names cited without a file, prose.
  - Whether a status (live, leftover, ghost, half-wired) or a Hits / Does not hit line is true. The checker reads no code for meaning.
  - Whether a verified date is honest, whether its commit ref exists, and whether the card's **Checked:** header agrees with its frontmatter.
  - Line numbers written without a colon (`WorkoutSession 1684`, number columns in a table), approximate lines (`~1143`), and a `:N` with no file earlier on its line. These are listed under UNCHECKED when found; the detector for them is a heuristic and can miss some.
  - A path with no line number written outside backticks, and a backticked dotted name with no slash and an unfamiliar extension (`meta.parts`): neither is treated as a file. Markdown links between map pages (`[card](../objects/x.md)`) and frontmatter `consumes:` / `produces:` lists are not checked either.
  - A file that exists but is not tracked: generated or gitignored output fails as missing-file even though it is on disk. That is the rule (only tracked files count), not a miss, but the failure means 'not committed', not always 'gone'.
  - Whether the catalog routes well: By task lines, ledger lines that name a card by name (`see training-max`) rather than by path, and the 60-line budget are not checked.
  - Anything outside the repo and its --extra-root folders: the database, production rows, other repos, external consumers.

RESULT: PASS
```

## Human check not run (never a pause)

The plan's `<human-check>` (after Ian pushes the phase branch, the CI pgsql leg is green for `ReplyCapTest`, `orWhereNotIn` with enum bindings on Postgres) was left unrun. It is recorded as `.planning/WINDOWS.md` entry 89 (`unrun-verify`, phase 10, `tests/Feature/FrontDesk/ReplyCapTest.php`, open). Local runs are on SQLite.

## Issues Encountered

None. One scratch directory (`storage/gsd-red/`) held the JUnit record for the classifier and was removed; no untracked files remain.

## Known Stubs

None.

## Threat Flags

None. No new network endpoint, auth path, file access or schema change. T-10-01 is mitigated as planned: only the two kinds `notCountedByReplyCap()` lists are left out, and the "still counts holding replies" case plus the unchanged D-52 cases pin the rest. T-10-02 is pinned by the D-21 case.

## Next Phase Readiness

Dana's walk (WALK-03) can run intake, the offer, a link resend, the confirmation and a question without the cap pausing her. Nothing else in the phase is blocked by this plan.

## Self-Check: PASSED

- `app/Enums/MessageKind.php`, `app/Actions/RunFrontDesk.php`, `tests/Feature/FrontDesk/ReplyCapTest.php`, `tests/Feature/Booking/BookingSendTest.php`, the two map cards and `docs/BLUEPRINT.md`: all exist.
- Commits `1543c01`, `ebef558`, `42507b7` are ancestors of HEAD; `git rev-list --count 15652e4..HEAD` was 3 at write time.

---
*Phase: 10-walk-tests-and-the-gate*
*Completed: 2026-11-22*
