---
status: complete
phase: 09-owner-intelligence
source: [09-VERIFICATION.md]
started: 2026-11-18T19:00:00Z
updated: 2026-11-18T20:56:10.089Z
---

## Current Test

[testing complete]

## Tests

### 1. Keyboard-only and VoiceOver walk of the contact screen's Time to booked line and the dashboard's Owner intelligence section; phone-width check for no sideways scroll
expected: Label read with its value, section read under its heading, focus order on the contact screen unchanged, lines wrap at phone width (WINDOWS.md row 83)
result: pass
notes: |
  Ian, 2026-11-18. Contact 6: Time to booked shows "22 minutes" in the Contact box after Last activity; VoiceOver reads the label with its value in on-screen order. Tab skips it, as it does every plain-text field; focus order unchanged (breadcrumbs, Jobs/Appointments tabs, job row).
  Contact 5: no Time to booked line, as designed.
  Dashboard: the Owner intelligence section shows after a folder sync; VoiceOver reads the heading, then the summary.
  The dev folder had last synced 2026-11-11, before the room existed, so the room was off until Sync Now. Not a defect.
  Two older screen reader findings, not Phase 9 (VoiceOver in Chrome reads the contact Phone value as one whole number; "empty term" before every label and value pair), recorded as Phase 11 inputs in ROADMAP.md and BLUEPRINT.md (8c2d19e), not fixed.

### 2. At first deploy (Phase 12): cron runs php artisan schedule:run every minute and records/today.md names the new day after 00:10 business time
expected: Title date advances after 00:10 in the business zone; a late tick inside 00:10 to 01:00 still runs the rebuild once (WINDOWS.md row 88)
result: skipped
reason: "Deferred follow-up: deferred to Phase 12 at first deploy (WINDOWS.md row 88 stays open); needs a real scheduler and wall clock. Ian, 2026-11-18."

## Deferred Follow-Ups

- test: 2
  idea: "Deferred to Phase 12 at first deploy (WINDOWS.md row 88 stays open); needs a real scheduler and wall clock."
  deferred_at: 2026-11-18

## Summary

total: 2
passed: 1
issues: 0
pending: 0
skipped: 1
blocked: 0

## Gaps

Closed since the 2026-11-17 UAT list (re-verification 2026-11-18): the WR-01 decision (Ian ruled and it was fixed, 28b85b3); the CI pgsql leg for the whole suite and BookingPageBrowserTest on CI (run 44226865631 at 4bb969f, both legs 3347 passed; confirmed again by run 44374318071 at 0df383a).
