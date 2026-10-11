---
status: testing
phase: 01-foundation
source: [01-VERIFICATION.md]
started: 2026-10-11T15:55:00Z
updated: 2026-10-11T15:55:00Z
---

## Current Test

number: 1
name: Dashboard walk-through (sign in, Conversations, Contacts, reply, close and reopen)
expected: |
  Thread and timeline read clearly, the reply appears in the thread as sent, Close and Reopen change the status, and the notifications read as plain sentences.
awaiting: user response

## Tests

### 1. Dashboard walk-through: sign in to /admin, open Conversations and Contacts, read a thread, type and send a reply, close and reopen a conversation (windows ledger #3, plan 01-06 Task 3)
expected: Thread and timeline read clearly, the reply appears in the thread as sent, Close and Reopen change the status, and the notifications read as plain sentences.
result: [pending]

### 2. Local tools walk-through: open /dev/chat, text as a customer, watch the thread poll, then open Business Folder and click Sync Now, then open a rendered card under examples/example-hvac/records/ (windows ledger #4, plan 01-07 Task 3)
expected: The text shows on the thread within a poll, Sync Now shows a notification and the page updates, and the card shows the text. Dana shares the /dev/chat default number (+12025550123), so use another number such as +13125550199 to see a fifth contact.
result: [pending]

### 3. Interrupted changelog write: interrupt a changelog write (for example make the folder read-only mid-write, or kill the process) and confirm changelog.md keeps its previous content
expected: changelog.md is intact, not truncated.
result: [pending]

## Summary

total: 3
passed: 0
issues: 0
pending: 3
skipped: 0
blocked: 0

## Gaps
