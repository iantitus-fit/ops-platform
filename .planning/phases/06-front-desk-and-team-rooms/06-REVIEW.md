---
phase: 06-front-desk-and-team-rooms
reviewed: 2026-10-28T00:00:00Z
depth: standard
files_reviewed: 9
files_reviewed_list:
  - .gitignore
  - app/Actions/ReportFrontDeskFailure.php
  - app/Models/Message.php
  - app/Rooms/RoomRegistry.php
  - docs/BLUEPRINT.md
  - docs/sprints/sprint-1.1.md
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

This is the review-fix delta (diff base 2d7c012): the nested release try/catch in `ReportFrontDeskFailure`, `claim_token` and `failure_reported_at` removed from `Message`'s `#[Fillable]`, the two new `RoomRegistry::guardClaimedWidgets` guards, the matching tests and docs, and a `.gitignore` entry.

The fixes themselves do what they say. Production code writes both columns only through `Message::query()->...->update()`, which ignores `$fillable`. The factory sets them with the guard off. I found no other mass-assignment writer (grep of `app`, `database`, `tests`). The `.gitignore` entry and the doc edits are fine.

The remaining problems sit around the fixes, in the report-once claim itself. They are gaps in the "never a missing alert" guarantee (02.1 IN-11) that the new code claims to protect. No security issues or blockers.

Finding IDs: WR-02 re-raises the earlier review's WR-02. WR-05, WR-06, IN-03 and IN-04 are new and continue the numbering after the earlier review's WR-01 to WR-04 and IN-01 to IN-02. References in the bodies to "WR-03" or "the WR-02 test" mean the earlier review's fixes and tests.

## Warnings

### WR-02: A failure is reported against different rows depending on which path reports first

**File:** `app/Actions/ReportFrontDeskFailure.php:66-76, 106-121`; callers `app/Actions/RunFrontDesk.php:203, 214`; test `tests/Feature/FrontDesk/FailureReportOnceTest.php:155-170`
**Issue:** This re-raises the earlier review's WR-02, which the disposition ledger records as skipped (design kept). `takeTheReportClaim()` ignores the row passed in and claims `subjectOf($rows)` (newest SMS row, per D-20). The note (`subject_id`), the alert (`$text`) and the `alreadyReported` lookup still use the passed `$text`. `ProcessInbound::handle()` and `failed()` pass `subjectOf($burst)`, so they agree with the claim. `RunFrontDesk` passes `$burst->last()` instead. On a mixed burst (text then web form) the claim goes on the text row while the alert and note go on the web form row. The docblock at lines 22-24 says it "does not matter which of the run's rows a caller passes", which is true for counting but false for where the report lands. The WR-02 test at line 155 locks in the form-row subject, while the catch-path test `reports her text, not the newer web form...` expects the text row, so the same burst is reported against different messages depending on which path wins. D-20 and the `ProcessInbound` docblock say the newest text message. The first call in the test at line 126 (`handle(..., $form, ...)`) asserts only counts, not the subject, so it does not catch this.
**Fix:** Make the action authoritative about the subject, so every caller agrees:
```php
$subject = $claimedRow ?? $text;
// use $subject for activities()->create(... 'subject_id' => $subject->id ...),
// the alreadyReported lookup, and raiseOwnerAlert->handle(..., $subject, $why)
```
Then change the test at line 155 to expect the text row (or change D-20 and its callers deliberately, but not leave them split).

### WR-05: The legacy "already reported" query runs after the claim is taken but outside the try, so a database error there leaves the claim held and the owner never told

**File:** `app/Actions/ReportFrontDeskFailure.php:46-61`
**Issue:** `takeTheReportClaim()` sets `failure_reported_at` at line 47. The `OwnerAlert::...->exists()` query at lines 54-57 then runs before the `try` that starts at line 63. If that query throws (connection drop, lock timeout), the exception escapes with the claim still set and nothing written. The giving-back path (`giveTheReportClaimBack`) is never reached. When failed() later runs and calls `handle()` again, `takeTheReportClaim()` returns null and the method returns silently. The result is a permanent missing alert, which is exactly the outcome the class docblock says is worse than a duplicate note. The new WR-03 fix covers a failure while releasing the claim, but not this earlier window.
**Fix:** Move the `alreadyReported` check inside the `try`, or take the claim after it:
```php
try {
    $alreadyReported = OwnerAlert::query()
        ->where('message_id', $text->id)
        ->where('reason', AlertReason::FrontDeskFailed->value)
        ->exists();

    if ($alreadyReported) {
        return;
    }

    $contact = $conversation->contact;
    // ... note, event, alert as before
} catch (Throwable $exception) {
    if ($claimedRow !== null) {
        $this->giveTheReportClaimBack($claimedRow);
    }

    throw $exception;
}
```

### WR-06: The report claim has no expiry, so a failed release (the case WR-03 now swallows) or a process death between claim and write loses the alert for good

**File:** `app/Actions/ReportFrontDeskFailure.php:91-98, 118`
**Issue:** `giveTheReportClaimBack()` deliberately downgrades a release failure to `report()`. After that, `failure_reported_at` stays set on the report row with no alert written, and no later path can ever report: every caller sees `$claimed === 0` and returns. The same permanent loss happens if the worker is killed between line 118 and the alert write. The owner-alert send claim in the same phase avoids this with a 2-minute expiry plus sweep (BLUEPRINT section 6, D-06/D-07). This claim has no such recovery. The new test asserts only that the original exception escapes and the release failure is reported. It does not assert any way to recover the report.
**Fix:** Either treat `failure_reported_at` as a lease (reclaim when it is older than N minutes and no `front_desk_failed` alert exists for the row), or record the gap. At minimum add a sweep or log line so a held claim with no alert is detectable. Lease sketch:
```php
->where(fn ($q) => $q->whereNull('failure_reported_at')
    ->orWhere('failure_reported_at', '<=', now()->subMinutes(2)))
```
This works only together with the `alreadyReported` alert check, which already runs after the claim.

## Info

### IN-03: `guardClaimedWidgets` does not validate element types, unlike `guardHandles`

**File:** `app/Rooms/RoomRegistry.php:331-348`
**Issue:** A non-string entry in `claimedWidgets` (a typo such as an array or an int) reaches the `"[{$widget}]"` interpolation or `class_exists()` and surfaces as an "Array to string conversion" warning or a `TypeError` instead of the readable `LogicException` every other guard gives. `guardHandles` and `guardMorphMap` check `is_string` first. The new duplicate and sections checks also compare before the existence check, so a bad entry would be reported oddly.
**Fix:** Add at the top of the loop:
```php
if (! is_string($widget)) {
    throw new LogicException("Room [{$room->key}] claims a widget that is not a class name.");
}
```

### IN-04: The WR-03 test depends on the exact SQL shape of the release update

**File:** `tests/Feature/FrontDesk/FailureReportOnceTest.php:176-180`
**Issue:** The `DB::beforeExecuting` hook identifies the release by `str_starts_with(lower($query), 'update')`, the substring `failure_reported_at`, and `$bindings[0] === null`. It relies on Eloquent putting the nulled column first in the bindings. If `updated_at` handling or column order changes, the hook silently never fires. The final `Exceptions::assertReported` makes the test fail rather than pass vacuously, so this is robustness only, not a false pass.
**Fix:** Optional: match on `$bindings[0] === null && str_contains($query, '"failure_reported_at" = ?')` and add a comment saying why binding 0 is the null.

---

_Reviewed: 2026-10-28_
_Reviewer: Claude (gsd-code-reviewer)_
_Depth: standard_
