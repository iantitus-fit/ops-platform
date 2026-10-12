---
status: testing
phase: 02-text-front-desk
source: [02-VERIFICATION.md]
started: 2026-10-12T13:16:50Z
updated: 2026-10-12T13:16:50Z
---

## Current Test

number: 1
name: Open an owner-alert link on a real phone over Wi-Fi after triggering a held draft from /dev/chat
expected: |
  Lands on the conversation; held reason, draft and Send are visible without scrolling
awaiting: user response

## Tests

### 1. Open an owner-alert link on a real phone over Wi-Fi after triggering a held draft from /dev/chat
expected: Lands on the conversation; held reason, draft and Send are visible without scrolling
result: [pending]

### 2. Set JEV_API_KEY, ANTHROPIC_API_KEY and OPENAI_API_KEY, run icm:sync for the Phase 2 folder, run a queue worker, text from /dev/chat
expected: A reply that opens with the AI disclosure, ends with the sign-off, asks the matching service's intake question, and a price question is held as a draft
result: [pending]

### 3. Push the branch (no git remote exists yet) and watch the tests workflow
expected: Both matrix legs (sqlite, pgsql) green
result: [pending]

## Summary

total: 3
passed: 0
issues: 0
pending: 3
skipped: 0
blocked: 0

## Gaps
