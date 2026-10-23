---
phase: 04-room-standard
reviewed: 2026-10-23T00:00:00Z
depth: standard
files_reviewed: 1
files_reviewed_list:
  - tests/Feature/Rooms/OrderIndependenceTest.php
findings:
  critical: 0
  warning: 0
  info: 2
  total: 2
status: issues_found
---

# Phase 4: Code Review Report

**Reviewed:** 2026-10-23
**Depth:** standard
**Files Reviewed:** 1
**Status:** issues_found

## Summary

Incremental re-review. The only change since 57c9ace is the new regression test `tests/Feature/Rooms/OrderIndependenceTest.php` (commit fcf66d9). I read it against the code it guards (`RoomChecks`, `ConnectorChecks`, `FolderSync`, `Loader`, `ActiveConfig::roomOwnedFiles`) and the helpers in `tests/Pest.php`. I also ran it: 3 tests, 21 assertions, all passing.

The test is correct. Each assertion matches what the code does, the helpers do what the test assumes, and no shared state leaks:
- A rejected sync always creates a new row (`FolderSync::reject`), so `rejectedErrors()` reads a fresh row on each loop pass in test 3.
- The test-local helpers `pretendContractListing` and `contractSyncResult` have no name clash anywhere in `tests/`.
- The pinned error order in test 2 follows the recorded decision (Ian, 2026-10-23, UAT test 3).
- The "is a hours file" wording is recorded as G-04-3 and was not re-reported.

No bugs and no security problems. Two minor test-quality notes follow.

## Info

### IN-09: Write-order arrangements in the connector test cannot change what the code sees

**File:** `tests/Feature/Rooms/OrderIndependenceTest.php:113-132`
**Issue:** The arrangements "written first" and "written second" differ only in the order the two files are created on disk. `Loader::load` reads the folder with `->sortByName()` (`app/Icm/Loader.php:77`), so creation order never reaches the checks. Arrangements 1 and 2 are the same input to the code, and so are 3 and 4. The content swap is what really varies the input. The "written second" labels suggest write-order coverage the test does not have, and removing the sort would probably not make it fail. The test still guards path-order blame and which-kind-in-which-file.
**Fix:** Keep the content swap. Either drop the write-order duplicates, or say in a short comment that the Loader's name sort is what makes write order irrelevant. For a real guard on the sort, add a Loader-level test that checks the order of `files` directly.

### IN-10: Test 2's name says "the same refusal" but its assertions pin two different line orders

**File:** `tests/Feature/Rooms/OrderIndependenceTest.php:78, 97-98`
**Issue:** The title reads "gives the same refusal ... whatever order its owns entries are written in". The assertions require `$written['errors']` to be `[faq, hours]` and `$reversed['errors']` to be `[hours, faq]`, which are not equal. The comment at lines 93-94 explains this, but the test name contradicts it. A reader scanning test names, or a failure message, gets the opposite impression.
**Fix:** Rename it to match what it pins, for example "gives the same outcome and the same set of refusal lines for a contract whatever order its owns entries are written in, with lines about one file following the entry order".

---

_Reviewed: 2026-10-23_
_Reviewer: Claude (gsd-code-reviewer)_
_Depth: standard_
