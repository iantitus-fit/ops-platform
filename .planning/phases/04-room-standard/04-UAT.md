---
status: testing
phase: 04-room-standard
source: [04-VERIFICATION.md]
started: 2026-10-23T02:05:00Z
updated: 2026-10-23T02:05:00Z
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

## Summary

total: 3
passed: 0
issues: 0
pending: 3
skipped: 0
blocked: 0

## Gaps
