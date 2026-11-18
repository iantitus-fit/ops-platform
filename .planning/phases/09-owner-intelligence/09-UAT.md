---
status: testing
phase: 09-owner-intelligence
source: [09-VERIFICATION.md]
started: 2026-11-18T19:00:00Z
updated: 2026-11-18T19:00:00Z
---

## Current Test

number: 1
name: Keyboard-only and VoiceOver walk of the contact screen's Time to booked line and the dashboard's Owner intelligence section; phone-width check for no sideways scroll
expected: |
  Label read with its value, section read under its heading, focus order on the contact screen unchanged, lines wrap at phone width (WINDOWS.md row 83)
awaiting: user response

## Tests

### 1. Keyboard-only and VoiceOver walk of the contact screen's Time to booked line and the dashboard's Owner intelligence section; phone-width check for no sideways scroll
expected: Label read with its value, section read under its heading, focus order on the contact screen unchanged, lines wrap at phone width (WINDOWS.md row 83)
result: [pending]

### 2. At first deploy (Phase 12): cron runs php artisan schedule:run every minute and records/today.md names the new day after 00:10 business time
expected: Title date advances after 00:10 in the business zone; a late tick inside 00:10 to 01:00 still runs the rebuild once (WINDOWS.md row 88)
result: [pending]

## Summary

total: 2
passed: 0
issues: 0
pending: 2
skipped: 0
blocked: 0

## Gaps

Closed since the 2026-11-17 UAT list (re-verification 2026-11-18): the WR-01 decision (Ian ruled and it was fixed, 28b85b3); the CI pgsql leg for the whole suite and BookingPageBrowserTest on CI (run 44226865631 at 4bb969f, both legs 3347 passed).
