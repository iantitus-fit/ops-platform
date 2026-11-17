---
status: testing
phase: 09-owner-intelligence
source: [09-VERIFICATION.md]
started: 2026-11-17T17:30:00Z
updated: 2026-11-17T17:30:00Z
---

## Current Test

number: 1
name: Decide WR-01 (router lags a room toggle until the next 00:10 rebuild)
expected: |
  Ian chooses: accept and document, or have the redraw job also rewrite records/CLAUDE.md under the records:owner-intelligence lock (this relaxes 09-04's 'touch no other records file' truth for that one call)
awaiting: user response

## Tests

### 1. Decide WR-01 (router lags a room toggle until the next 00:10 rebuild)
expected: Ian chooses: accept and document, or have the redraw job also rewrite records/CLAUDE.md under the records:owner-intelligence lock (this relaxes 09-04's 'touch no other records file' truth for that one call). CONTEXT D-15 says the router stops naming the files at the next sync or rebuild; the plans scoped the router to rebuild only. Triage WR-02, WR-03 and IN-01 to IN-03 in 09-REVIEW-DISPOSITION.md at the same time.
result: [pending]

### 2. Keyboard-only and VoiceOver walk of the contact screen's Time to booked line and the dashboard's Owner intelligence section; phone-width check for no sideways scroll
expected: Label read with its value, section read under its heading, focus order on the contact screen unchanged, lines wrap at phone width (WINDOWS.md row 83)
result: [pending]

### 3. Push the phase branch and confirm the CI pgsql leg is green for the whole suite
expected: Both sqlite and pgsql legs pass, in particular GateExportTest, TimeToBookedTest, TodayRecordTest, TimeToBookedFileTest, RedrawTest, OwnerIntelligenceBootTest, ContactTimeToBookedTest (WINDOWS.md rows 80, 82, 84, 85, 86, 87)
result: [pending]

### 4. Confirm the 5 BookingPageBrowserTest failures seen on the dev machine pass in CI
expected: Pass on CI (target size 21px, footer hover border); no Phase 9 file touches them (WINDOWS.md row 81)
result: [pending]

### 5. At first deploy (Phase 12): cron runs php artisan schedule:run every minute and records/today.md names the new day after 00:10 business time
expected: Title date advances after 00:10 in the business zone (WINDOWS.md row 88)
result: [pending]

## Summary

total: 5
passed: 0
issues: 0
pending: 5
skipped: 0
blocked: 0

## Gaps
