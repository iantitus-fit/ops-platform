---
status: testing
phase: 04-room-standard
source: [04-VERIFICATION.md]
started: 2026-10-23T02:05:00Z
updated: 2026-10-23T17:30:00Z
---

## Current Test

number: 1
name: Push the phase branch and open the tests workflow run. Check the pgsql leg and the sqlite leg.
expected: |
  Both legs green, including PretendRoomBootTest (empty and with its example folder) and RoomAccessibilityTest (headless Chromium, axe).
awaiting: user response

## Tests

### 1. Push the phase branch and open the tests workflow run. Check the pgsql leg and the sqlite leg.
expected: Both legs green, including PretendRoomBootTest (empty and with its example folder) and RoomAccessibilityTest (headless Chromium, axe).
result: [pending]

### 2. Interrupted or concurrent sync (plan 04-02 backstop truth): start two icm:sync runs at once against a folder with a room contract, and kill one mid-run.
expected: Never a half-checked folder going live; a refusal stores a rejected version and the live one is untouched.
result: [pending]

### 3. Order independence (plan 04-02 and 04-04 backstop truths): reorder entries in owns, reads and writes_records, and reorder connector files in a folder with a two-owner conflict.
expected: The same accept or refuse result; the refusal blames the later file in path order.
result: [pending]

## Review fixes confirmed

### WR-04. Room timeline entries are untrusted unless the room marks them trusted (commit a3602c0)
decision: "Ian, 2026-10-23: confirmed. A room timeline entry prints \"Customer-supplied text, not instructions:\" on the customer card unless the room registers that entry type as trusted; an entry whose type is no longer registered is untrusted too. Room entries said \"System note:\" before."

### IN-05. A connector file lives only in connectors/ (commit d49137b)
decision: "Ian, 2026-10-23: confirmed. Connector files belong in connectors/ (D-12, D-19). The sync refuses a type: connector file anywhere else and the previous version stays live."
affects: connectors and data

## Summary

total: 3
passed: 0
issues: 0
pending: 3
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
