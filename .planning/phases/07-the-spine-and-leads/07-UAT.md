---
status: testing
phase: 07-the-spine-and-leads
source: [07-VERIFICATION.md]
started: 2026-10-31T18:05:00Z
updated: 2026-10-31T18:05:00Z
---

## Current Test

number: 1
name: Postgres leg of CI green for the whole phase (WINDOWS rows 34, 35, 36, 37, 39, 40, 42, 43)
expected: |
  After Ian pushes the phase branch, the CI tests workflow's pgsql leg is green for the whole suite, in particular ProjectWordTest, ProjectRecordTest ('refuses a second project opened by a new lead for one contact'), ProjectCardTest, CardInjectionTest, ProjectActionsTest, ProjectWritesTest, ProjectsRelationManagerTest, LeadsBootTest, LeadsSectionTest, and OpenOnNewLeadTest ('loses a race without an error and leaves one project').
awaiting: user response

## Tests

### 1. Postgres leg of CI green for the whole phase (WINDOWS rows 34, 35, 36, 37, 39, 40, 42, 43)
expected: After Ian pushes the phase branch, the CI tests workflow's pgsql leg is green for the whole suite, in particular ProjectWordTest, ProjectRecordTest ('refuses a second project opened by a new lead for one contact'), ProjectCardTest, CardInjectionTest, ProjectActionsTest, ProjectWritesTest, ProjectsRelationManagerTest, LeadsBootTest, LeadsSectionTest, and OpenOnNewLeadTest ('loses a race without an error and leaves one project'). Roadmap criterion 4 asks for both databases; SQLite is green at 2417 passed.
result: [pending]

### 2. Keyboard-only and VoiceOver walk of a contact screen with two projects (WINDOWS row 38)
expected: The list is reachable; New, Close, Reopen and Rename work without a mouse; the New and Close confirmations and the rename form take focus and return it; every button has an accessible name.
result: [pending]

### 3. Keyboard-only and VoiceOver walk of /admin with three open projects (WINDOWS row 41)
expected: Each row's contact link is reached by Tab, announced with the contact's name, opens the contact screen, and the opened date is read as a date.
result: [pending]

## Summary

total: 3
passed: 0
issues: 0
pending: 3
skipped: 0
blocked: 0

## Gaps
