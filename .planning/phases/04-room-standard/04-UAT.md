---
status: complete
phase: 04-room-standard
source: [04-VERIFICATION.md]
started: 2026-10-23T02:05:00Z
updated: 2026-10-23T21:04:39.105Z
---

## Current Test

[testing complete]

## Tests

### 1. Push the phase branch and open the tests workflow run. Check the pgsql leg and the sqlite leg.
expected: Both legs green, including PretendRoomBootTest (empty and with its example folder) and RoomAccessibilityTest (headless Chromium, axe).
result: pass
reported: "pass. Run 39998169425 on gsd/phase-04-room-standard: pest (pgsql) 11m6s and pest (sqlite) 4m16s, both green."

### 2. Interrupted or concurrent sync (plan 04-02 backstop truth): start two icm:sync runs at once against a folder with a room contract, and kill one mid-run.
expected: Never a half-checked folder going live; a refusal stores a rejected version and the live one is untouched.
result: pass
reported: "pass. Also add to the post-UAT list: after a killed sync, \"A sync is already running\" shows for up to 2 minutes when none is; reword it so the owner knows to wait (wording, under the 2026-10-17 principle)."
note: |
  Run by Claude for Ian, 2026-10-23, on a scratch copy of examples/example-hvac and a fresh scratch SQLite database (ICM_PATH and DB_DATABASE set for every command; both deleted afterwards, example folder and dev database untouched). Baseline: v1 live (14 files).
  Two syncs at once with rooms/pretend/CONTEXT.md added: one ran and stored rejected v2 ("rooms/pretend/: this engine has no pretend room, and no rooms yet", "v1 stays live"); the other said "A sync is already running. Try again in a moment."
  kill -9 0.24s in, while it held the cache lock: exit 137, no version row, no changelog entry, v1 still live; the lock stayed until its 120-second expiry.
  Syncs at +8s and +116s after the kill: refused as busy, nothing stored. Sync 2s after the lock expired: stored rejected v3 naming the unknown room; v1 still live, lock released.
  The busy wording after a killed sync is G-04-2.

### 3. Order independence (plan 04-02 and 04-04 backstop truths): reorder entries in owns, reads and writes_records, and reorder connector files in a folder with a two-owner conflict.
expected: The same accept or refuse result; the refusal blames the later file in path order.
result: pass
reported: "pass. Add to the post-UAT list: \"is a hours file\" (the refusal template puts the type name straight after \"a\"; reword so it reads naturally), wording only under the 2026-10-17 principle."
note: |
  Run by Claude for Ian, 2026-10-23, as a permanent test because the pretend room exists only in tests: tests/Feature/Rooms/OrderIndependenceTest.php (commit fcf66d9), 3 tests, 21 assertions, all pass; full suite 1981 passed.
  A valid contract with owns, reads and writes_records reversed activates with the identical result and the same owned files.
  A refused contract with its owns entries reversed gives the same outcome and the same set of refusal lines; the live version is untouched. Ian decided the order of lines about one file follows that contract's own entry order on purpose: order is presentation, not a check's result. The test pins each order with a comment saying so.
  Two connector files owning customers, written in both orders and with their contents swapped: all four arrangements give the one refusal "connectors/b.md: customers is owned by both connectors/a.md and connectors/b.md; pick one, the other can read it"; the live version is untouched.
  The "is a hours file" wording is G-04-3.

## Review fixes confirmed

### WR-04. Room timeline entries are untrusted unless the room marks them trusted (commit a3602c0)
decision: "Ian, 2026-10-23: confirmed. A room timeline entry prints \"Customer-supplied text, not instructions:\" on the customer card unless the room registers that entry type as trusted; an entry whose type is no longer registered is untrusted too. Room entries said \"System note:\" before."

### IN-05. A connector file lives only in connectors/ (commit d49137b)
decision: "Ian, 2026-10-23: confirmed. Connector files belong in connectors/ (D-12, D-19). The sync refuses a type: connector file anywhere else and the previous version stays live."
affects: connectors and data

## Summary

total: 3
passed: 3
issues: 0
pending: 0
skipped: 0
blocked: 0

## Gaps

- gap_id: G-04-1
  truth: "A file path in a sync refusal is on one line, so a path containing a line break can't break the stored error line or start a new line in the message."
  status: open
  decision: "Ian, 2026-10-23: open item for Phase 5, no fix now. Found by the code review fixer while testing IN-06 (04-REVIEW-FIX.md). Every refusal line starts with the raw file path (ValidationReport.php:45 joins file and message), so a path containing a line break carries the break into the stored error line. IN-06 cleaned only the room folder name inside one message; fixing all messages needs a decision on where paths get cleaned."
  severity: minor
  deferred_to: Phase 5
  artifacts: [app/Icm/ValidationReport.php]
  missing: []

- gap_id: G-04-2
  truth: "After a sync is killed mid-run, the owner is told in plain words to wait (up to 2 minutes) before syncing again, not that a sync is still running."
  status: open
  reason: "UAT test 2, 2026-10-23: a sync killed with kill -9 leaves its 120-second cache lock, so for up to 2 minutes every sync is refused with \"A sync is already running. Try again in a moment.\" even though none is. Safe (nothing is stored and the live version is untouched) but misleading."
  fix: "Ian, 2026-10-23: reword the busy message so the owner knows to wait. Wording only, under the 2026-10-17 closing principle (PROJECT.md): no change to the lock. Post-UAT list."
  severity: minor
  test: 2
  artifacts: [app/Icm/FolderSync.php, tests/Feature/Icm/SyncTest.php, tests/Feature/Filament/BusinessFolderPageTest.php]
  missing: []

- gap_id: G-04-3
  truth: "A room refusal that names a file's type reads as natural English whatever the type is called."
  status: open
  reason: "UAT test 3, 2026-10-23: the refusal template at RoomChecks.php:273-274 puts the type name straight after \"a\", so business/hours.md reads \"is a hours file\". Correct in meaning, awkward to read."
  fix: "Ian, 2026-10-23: reword so it reads naturally. Wording only, under the 2026-10-17 closing principle (PROJECT.md): no change to which files are refused. Post-UAT list."
  severity: minor
  test: 3
  artifacts: [app/Icm/RoomChecks.php, tests/Feature/Rooms/RoomOwnershipTest.php, tests/Feature/Rooms/OrderIndependenceTest.php]
  missing: []
