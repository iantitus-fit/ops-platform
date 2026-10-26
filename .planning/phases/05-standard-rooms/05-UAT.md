---
status: testing
phase: 05-standard-rooms
source: [05-VERIFICATION.md]
started: 2026-10-25T00:00:00Z
updated: 2026-10-25T00:00:00Z
---

## Current Test

number: 1
name: Keyboard-only and VoiceOver walk of each new room section on /admin, plus the marketing offers line on the day an offer ends
expected: |
  Every section (company knowledge; marketing; content generation empty and with the brief; money empty and with the notes; Google Business Profile empty and with the categories file) is reachable and readable by keyboard and screen reader, headings and lines are announced in a sensible order, and the offers line matches what the front desk would send.
awaiting: user response

## Tests

### 1. Keyboard-only and VoiceOver walk of each new room section on /admin, plus the marketing offers line on the day an offer ends
expected: Every section (company knowledge; marketing; content generation empty and with the brief; money empty and with the notes; Google Business Profile empty and with the categories file) is reachable and readable by keyboard and screen reader, headings and lines are announced in a sensible order, and the offers line matches what the front desk would send. (WINDOWS.md unrun-verify entries 20 to 24; carries the Phase 4 walk.)
result: [pending]

### 2. Push the branch and read the CI result for the pgsql leg
expected: All five room boot tests (empty and with the example folder) and the rest of the suite pass on Postgres 17 as they do on SQLite (2148/2148 locally).
result: [pending]

## Summary

total: 2
passed: 0
issues: 0
pending: 2
skipped: 0
blocked: 0

## Gaps
