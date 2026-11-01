---
phase: 07-the-spine-and-leads
reviewed: 2026-11-01T15:55:08Z
depth: standard
files_reviewed: 5
files_reviewed_list:
  - app/Filament/Resources/Contacts/RelationManagers/ProjectsRelationManager.php
  - docs/BLUEPRINT.md
  - docs/sprints/sprint-1.1.md
  - tests/Feature/Filament/ProjectsRelationManagerKeyboardTest.php
  - tests/Feature/Filament/ProjectsRelationManagerTest.php
findings:
  critical: 0
  warning: 0
  info: 3
  total: 3
status: issues_found
---

# Phase 7: Code Review Report

**Reviewed:** 2026-11-01T15:55:08Z
**Depth:** standard
**Files Reviewed:** 5
**Status:** issues_found

## Summary

This is an incremental re-review. Its scope is what changed since the last review (fcf5b8c, 2026-10-31): the gap-closure plan 07-09, which changes the contact screen's project list. The findings of the 2026-10-31 review (WR-01 to WR-03, IN-01 to IN-04) and Ian's decisions on them are kept in 07-REVIEW-DISPOSITION.md. New findings here continue the numbering so no id is reused.

I read the relation manager in full, the two test files, the 07-09 changes to BLUEPRINT and the sprint log, and the Filament views the new code relies on. The changes hold up:

- **Not lazy:** `$isLazy = false` is the documented switch. The page-level HTTP test proves the first response carries the rows and no `__lazyLoad` placeholder.
- **Escaping:** the per-row `aria-label` goes through `e()` exactly once.
  - Filament prints extra attributes unescaped, and the hostile-name test proves the parsed attribute equals the raw string, with nothing injected.
  - The modal heading and description, which now carry owner-typed names ("Close Johnny?"), are printed with `{{ }}` in `filament/support` `components/modal/index.blade.php` (lines 293 and 303) and stripped and escaped in its `aria-label` (line 146), so they are safe too.
- **Stale submit:** the `toggle` action decides the dialog from the record Filament resolves fresh when the button is pressed. The Hidden `direction` field carries that decision to the submit. `CloseProject` and `ReopenProject` lock the row and do nothing on a repeat, so a stale or tampered submit does what the dialog said or nothing.
- **Write paths:** the action still writes only through the four project actions (D-11).
- **Shared label:** `rowLabel()` reads `number` from the table query's `withNumber()` select. Filament resolves a mounted action's record through that same query, and the "Reopen Job 1?" heading tests prove the number is present.

I found no bugs, no security issues and no warnings. The three info items are test robustness and comment hygiene.

## Info

### IN-05: The browser test's two-second waits may flake on a slow CI runner

**File:** `tests/Feature/Filament/ProjectsRelationManagerKeyboardTest.php:52`
**Issue:** Each wait polls for at most two seconds:
- `projectsKeyboardConfirmationOpen()` waits for the modal's submit button.
- `projectsKeyboardFocusedName()` (line 74) waits for focus to settle on the row button.

Both waits sit behind a Livewire round trip. The confirmation round trip mounts the action. The submit round trip runs a database transaction and re-renders the table. On a cold CI runner, especially the Postgres leg where this test runs for the first time (WINDOWS entry 45), two seconds is a thin margin. A timeout reports focus on `BODY` or a missing dialog, which looks exactly like the G-07-2.2 regression the test guards. A flake would therefore be misread as a real failure.
**Fix:** Raise both deadlines to about 5000 ms. The waits return as soon as the condition holds, so a passing run costs nothing more. Alternatively, wait for Livewire to finish its request before reading `document.activeElement`.

### IN-06: New docblocks cite UAT gap, threat and plan ids that go stale once the phase is archived

**File:** `app/Filament/Resources/Contacts/RelationManagers/ProjectsRelationManager.php:37`
**Issue:** The new comments explain the code well, but they also cite project-history ids:
- "UAT gap G-07-2.1" and "(02-23, 04-RESEARCH)" at lines 37-38.
- "UAT gap G-07-2.2" at line 109 and "T-07-26, mitigated" at line 114.
- "UAT gap G-07-2.3, T-07-25" at line 185.

These point into `.planning/` artifacts that get archived at milestone close. This is the same class as the still-open IN-02. The WCAG criterion references (2.1.1, 2.4.3, 2.5.3) and the decision ids (D-11) are durable and worth keeping.
**Fix:** Keep the WCAG and D-xx references and drop the UAT gap, threat and plan ids, together with IN-02 if Ian takes that one.

### IN-07: The browser test focuses the row button directly, so Tab reaching the list is proven only without a browser

**File:** `tests/Feature/Filament/ProjectsRelationManagerKeyboardTest.php:93`
**Issue:** `$page->keys('[aria-label="Close Job 1"]', 'Enter')` focuses the button by selector before pressing Enter, and line 112 does the same for Reopen. That is right for G-07-2.2 (focus after the confirmation). But G-07-2.1 (Tab reaches the list from the top of the page) is proven only by the HTTP first-response test, which shows the buttons are in the HTML. It does not show they are in the Tab order: an inert, hidden or `tabindex="-1"` ancestor would pass it. The VoiceOver and Safari retest (WINDOWS entry 44) covers this by hand, so it is a gap in automation, not in assurance.
**Fix:** Optionally add one browser case. From the page body, press Tab until `document.activeElement` has `aria-label` "Close Job 1", with a bounded number of presses, so G-07-2.1 is proven in a real browser too.

---

_Reviewed: 2026-11-01T15:55:08Z_
_Reviewer: Claude (gsd-code-reviewer workflow, run inline by the execute-phase code_review_gate fork)_
_Depth: standard_
