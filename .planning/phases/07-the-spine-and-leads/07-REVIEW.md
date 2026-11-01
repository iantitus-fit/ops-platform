---
phase: 07-the-spine-and-leads
reviewed: 2026-11-01T19:40:00Z
depth: standard
files_reviewed: 2
files_reviewed_list:
  - tests/Feature/Filament/ProjectsRelationManagerKeyboardTest.php
  - tests/Feature/Rooms/LeadsBootTest.php
findings:
  critical: 0
  warning: 0
  info: 4
  total: 4
status: issues_found
---

# Phase 7: Code Review Report

**Reviewed:** 2026-11-01T19:40:00Z
**Depth:** standard
**Files Reviewed:** 2
**Status:** issues_found

## Summary

This is an incremental re-review. Its scope is what changed since the last review (570073a, 2026-11-01): the two browser test commits made for Ian's request to turn Phase 7's keyboard and screen reader walks into tests, 08eeec0 (`ProjectsRelationManagerKeyboardTest.php`) and a9497bc (`LeadsBootTest.php`). The other files in that window are docs (`docs/BLUEPRINT.md`, `docs/sprints/sprint-1.1.md`) and planning files, which this review does not cover. No application code changed. The earlier findings (WR-01 to WR-03, IN-01 to IN-07) and Ian's decisions on them are kept in 07-REVIEW-DISPOSITION.md. New findings continue the numbering, so no id is reused.

The reviewer agent could not be started from the fork that ran this gate, so the review was done in that fork at standard depth, in the agent's format.

The tests do what they claim. Each wait goes through Playwright's locator wait, which reads Playwright's answer. The Tab walks press real keys at the page. The phone case was checked by mutation: it fails with `$isLazy = true`. The names case fails without the aria-labels, and the dashboard order check fails when the rows are listed oldest first. There are no bugs, no security issues and no test that can pass for the wrong reason, with the one exception in IN-08. The remaining items are info.

## Info

### IN-08: The focus wait swallows every error, not only its timeout

**File:** `tests/Feature/Filament/ProjectsRelationManagerKeyboardTest.php:87-92`
**Issue:** `projectsKeyboardExpectFocusOn()` wraps the locator wait in `catch (Throwable)`, so that a timeout falls through to the expectation, which names what has focus. But the browser plugin turns every Playwright error into the same `ExpectationFailedException`, carrying Playwright's message. That includes a malformed selector and a protocol failure, as well as a timeout. If the selector `[aria-label=…]:focus, :focus:not([aria-label]):text-is(…)` stopped parsing, for example after a Playwright upgrade changed how `:text-is` mixes with CSS, the wait would fail at once and be swallowed. The test would then read focus without waiting, which is the IN-05 failure mode, hidden: green on a fast machine, flaky on a slow one.
**Fix:** Swallow only the timeout and rethrow anything else:
```php
} catch (Throwable $exception) {
    if (! str_contains($exception->getMessage(), 'Timeout')) {
        throw $exception;
    }
}
```

### IN-09: The dashboard walk's Tab cap is not measured

**File:** `tests/Feature/Rooms/LeadsBootTest.php:156-161`
**Issue:** `PROJECTS_KEYBOARD_TAB_CAP` records the press counts it was set from (11 on desktop, 10 on a phone) and leaves a little room. `LEADS_KEYBOARD_TAB_CAP = 60` gives no count. A regression that adds many tab stops before the leads section, such as a section that makes every row focusable, would still pass at 60, so the cap bounds the loop but guards little.
**Fix:** Measure the presses the walk takes to reach the third row, record the number in the docblock as the contact file does, and set the cap a few presses above it.

### IN-10: The new docblocks cite ledger rows and review ids that have already moved

**File:** `tests/Feature/Filament/ProjectsRelationManagerKeyboardTest.php:11-18`; `tests/Feature/Rooms/LeadsBootTest.php:187-191`
**Issue:** The test docblocks name WINDOWS row 38, row 41, UAT tests 2 and 3, gaps G-07-2.1 to G-07-2.3, and review findings IN-05 and IN-07. Row 38 was moved to Phase 11 in 94ab259 and now tracks the live VoiceOver listen, not this test, and row 41 is closed. A later reader following "WINDOWS row 38" reaches an open Phase 11 hand check and may think this test is that check. This is the same kind of issue as IN-02 and IN-06, which are still open, and it grows the set.
**Fix:** If Ian keeps the ids, say what they were ("the walk UAT test 2 asked for") rather than pointing at a live row, or fold this into the IN-06 decision.

### IN-11: The key-press helper is written twice

**File:** `tests/Feature/Filament/ProjectsRelationManagerKeyboardTest.php:57-61`; `tests/Feature/Rooms/LeadsBootTest.php:166-170`
**Issue:** `projectsKeyboardPress()` and `leadsKeyboardPress()` are the same two lines under two names. Both must exist because each file runs on its own, and Pest loads a test file's global functions only with that file. The pattern will be copied again for the next browser walk: Phase 8's booking page A11Y-02, and the Phase 11 owner screens.
**Fix:** When the next browser walk is written, move the helper (and possibly the focused-name reader) into `tests/Pest.php` beside `activateExampleFolder()`, which Phase 7 already changes under D-22. No change is needed now.

---

_Reviewed: 2026-11-01T19:40:00Z_
_Reviewer: Claude (gsd-code-review gate, run in a fork at standard depth)_
_Depth: standard_
