---
phase: 06-front-desk-and-team-rooms
reviewed: 2026-10-28T00:00:00Z
depth: standard
files_reviewed: 9
files_reviewed_list:
  - app/Actions/ReportFrontDeskFailure.php
  - app/Models/Message.php
  - app/Rooms/RoomRegistry.php
  - database/migrations/2026_10_27_000006_add_failure_reported_at_to_messages_table.php
  - database/migrations/2026_10_28_000001_add_failure_report_claimed_at_to_messages_table.php
  - docs/BLUEPRINT.md
  - tests/Feature/FrontDesk/ClaimTokenTest.php
  - tests/Feature/FrontDesk/FailureReportOnceTest.php
  - tests/Unit/Rooms/RoomClaimedWidgetsTest.php
findings:
  critical: 0
  warning: 3
  info: 2
  total: 5
status: issues_found
---

# Phase 6: Code Review Report

**Reviewed:** 2026-10-28
**Depth:** standard
**Files Reviewed:** 9
**Status:** issues_found

## Summary

I reviewed the report-claim lease in `ReportFrontDeskFailure`, the new Message columns and migrations, the claimed-widget guard in `RoomRegistry`, and the three test files. I also read the callers in `app/Jobs/ProcessInbound.php` and `app/Actions/RunFrontDesk.php`. The three suites pass (40 tests).

I found no security issues. The conditional-update claim is sound, and the fixed-words rule keeps exception text out of notes and alerts.

The main weakness is that the "claim once per token" guarantee holds for the claim row but not for what is written. The note and alert are keyed to whichever row the caller passed. The two callers pass different rows for a mixed text-plus-form burst. The lease also does not cover the case it was built for when `failed()` runs promptly.

## Warnings

### WR-01: The report is written against the caller's row, but the claim and the "already reported" check use two different rows

**File:** `app/Actions/ReportFrontDeskFailure.php:57-85` (callers: `app/Actions/RunFrontDesk.php:203,214`, `app/Jobs/ProcessInbound.php:189`)

**Issue:**
- The class docblock says "it does not matter which of the run's rows a caller passes". That holds only for the claim, which is taken on `$reportRow`, the newest SMS of the token.
- The note's `subject_id` and the alert's `message_id` use the passed `$text`, and the `alreadyReported` check looks up `$text->id` (lines 66-85).
- `RunFrontDesk` passes `$burst->last()`, the newest row, which is the web form in a mixed burst. `failed()` and the `ProcessInbound` catch pass `subjectOf($burst)`, the newest SMS.
- `FailureReportOnceTest` line 168 asserts the alert is tied to the form on the RunFrontDesk path. `FailureReportOnceTest` line 362 asserts it is tied to the text on the catch path. One failure is therefore reported against different messages depending on which path wins.
- It also breaks the lease's repeat-safety. Suppose RunFrontDesk writes the note and alert against the form, then dies, or `markTheReportFinished` fails (it is swallowed). After 2 minutes `failed()` takes the claim and passes the text. `alreadyReported` looks at the text id, finds nothing, and writes a second note and a second alert. The "repeat, never lose" trade-off in BLUEPRINT and WR-06 covers a crash between two writes. Here the repeat is guaranteed for any mixed burst, even though the first report finished its writes.

**Fix:** Make the claim row the single subject of the report, so the stated invariant is true:
```php
$subject = $claimedRow ?? $text;

$alreadyReported = OwnerAlert::query()
    ->where('message_id', $subject->id)
    ->where('reason', AlertReason::FrontDeskFailed->value)
    ->exists();
// ...use $subject for subject_id, getMorphClass() and raiseOwnerAlert->handle(...)
```
Also change `RunFrontDesk` to pass `ReportFrontDeskFailure::subjectOf($burst)` instead of `$burst->last()`, and update the WR-02 test at line 168 to expect the text.

### WR-02: A report claim taken by a reporter that then dies is not recovered when `failed()` arrives inside the lease

**File:** `app/Actions/ReportFrontDeskFailure.php:45,146-155`

**Issue:**
- The lease is meant to recover from a reporter that died after taking the claim (WR-06). It only helps if some path calls `handle()` again after `CLAIM_WAIT_MINUTES`.
- `failed()` is called once per failed job, and normally right after the timeout or crash. `RunFrontDesk` takes the claim at the end of a run, then dies (timeout or kill) before writing. `failed()` then arrives within seconds, finds a live claim, and returns silently (`$claimedRow === null`). Nothing calls `handle()` again, so the owner is never told.
- The tests exercise this only by calling `failed()` 3 minutes late (lines 173-187, 208-219), a timing the queue does not normally produce.
- This is the "missing alert is worse than a second note" case the design says it avoids.

**Fix:** Let the last-resort path ignore a live claim. For example, give `handle()` a `bool $lastResort = false` that `failed()` sets, and use it to skip the `failure_report_claimed_at` age check while still honouring `failure_reported_at`. If a double report from `failed()` racing a live reporter is acceptable, say so in the docblock. Alternatively, add a scheduled sweep of claims older than the lease with no `failure_reported_at`.

### WR-03: A room can claim a widget that another room adds in its sections, and the reverse

**File:** `app/Rooms/RoomRegistry.php:329-362`

**Issue:**
- `guardClaimedWidgets` checks a claim against the room's own `sections` and against other rooms' `claimedWidgets`. It never checks against other rooms' `sections`, and `guardSections` never checks against other rooms' claims.
- A room A that adds `FooSection` in `sections` and a room B that claims `FooSection` both register without error. The docblock promises "Each widget has one owner room".
- It also leaves a claim on a `RoomSection` that hides itself when its own room is off, so the claiming room points at a widget it does not control.

**Fix:** In `guardClaimedWidgets`, inside the loop over `$this->rooms`, also refuse when `in_array($widget, $other->sections, true)`. In `guardSections`, refuse a section any registered room already claims. Add tests for both orders.

## Info

### IN-01: The first migration was edited in place and its column changed meaning

**File:** `database/migrations/2026_10_27_000006_add_failure_reported_at_to_messages_table.php:12-16`

**Issue:**
- `failure_reported_at` was a claim ("set when the owner is told", given back on failure). It is now a "finished" marker, and a new column holds the claim.
- If any database ran the old code, rows where the old code set `failure_reported_at` as a claim and then crashed are now permanently treated as finished. Those runs are never reported.
- This is harmless if the migration never left the dev branch (not verified here). Otherwise it needs a data fix.

**Fix:** Confirm the original migration never shipped. If it did, add a migration that clears `failure_reported_at` where no matching `front_desk_failed` alert exists.

### IN-02: Two tests depend on the exact SQL text and the order of bindings

**File:** `tests/Feature/FrontDesk/FailureReportOnceTest.php:225-232,243-249`

**Issue:**
- The WR-03 test matches the quoted column `"failure_report_claimed_at" = ?` and relies on `bindings[0]` being the claim value, with `updated_at` after it. The WR-05 test matches a `select exists` string.
- Both pass today on SQLite. A change to Laravel's `updated_at` merge order or to the grammar silently makes the interception a no-op. The WR-03 test fails via `assertReported`, so it is not vacuous, but the WR-05 test has no such backstop.
- It passes without the throw if the string never matches, because the `RuntimeException` it expects is also never thrown. I did not run it with the match broken.

**Fix:** Assert that the interceptor fired (a `$fired` flag checked with `expect($broke)->toBeTrue()`), as the WR-05 test's `$broke` almost does.

---

_Reviewed: 2026-10-28_
_Reviewer: Claude (gsd-code-reviewer)_
_Depth: standard_
