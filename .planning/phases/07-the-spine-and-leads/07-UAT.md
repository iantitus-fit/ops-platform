---
status: complete
phase: 07-the-spine-and-leads
source: [07-VERIFICATION.md]
started: 2026-10-31T18:05:00Z
updated: 2026-10-31T21:32:16.651Z
---

## Current Test

[testing complete]

## Tests

### 1. Postgres leg of CI green for the whole phase (WINDOWS rows 34, 35, 36, 37, 39, 40, 42, 43)
expected: After Ian pushes the phase branch, the CI tests workflow's pgsql leg is green for the whole suite, in particular ProjectWordTest, ProjectRecordTest ('refuses a second project opened by a new lead for one contact'), ProjectCardTest, CardInjectionTest, ProjectActionsTest, ProjectWritesTest, ProjectsRelationManagerTest, LeadsBootTest, LeadsSectionTest, and OpenOnNewLeadTest ('loses a race without an error and leaves one project'). Roadmap criterion 4 asks for both databases; SQLite is green at 2417 passed.
result: pass

### 2. Keyboard-only and VoiceOver walk of a contact screen with two projects (WINDOWS row 38)
expected: The list is reachable; New, Close, Reopen and Rename work without a mouse; the New and Close confirmations and the rename form take focus and return it; every button has an accessible name.
result: issue
reported: |
  Issues, all on the contact screen's Jobs list:
  1. Not reachable by Tab: the Jobs list is a lazy-loaded relation manager that loads only when scrolled into view, so tabbing from the top goes from the breadcrumb straight out of the page. Every dashboard widget and room section already sets $isLazy = false; ProjectsRelationManager does not. Blocks keyboard use (WCAG 2.1.1).
  2. Focus lost after Close: confirming Close replaces the row's Close button with Reopen, so focus has nowhere to return and drops to the top of the page (the next Tab lands on Skip to content). Reopen likely does the same. Focus should land on the row's replacement action (Reopen after Close, Close after Reopen), never the top of the page (WCAG 2.4.3).
  3. Ian wants each row button to name its job for screen readers: "Close Johnny", "Reopen Job 1", "Rename Job 2", using the job's label, with the visible text unchanged.
  Working: New job and its confirmation by keyboard with focus returning to New job; the Rename form takes focus and returns it to Rename; Reopen works; VoiceOver names every button.
severity: blocker

### 3. Keyboard-only and VoiceOver walk of /admin with three open projects (WINDOWS row 41)
expected: Each row's contact link is reached by Tab, announced with the contact's name, opens the contact screen, and the opened date is read as a date.
result: pass

## Summary

total: 3
passed: 2
issues: 1
pending: 0
skipped: 0
blocked: 0

## Gaps

- gap_id: G-07-2.1
  truth: "The contact screen's project list is reachable by Tab from the top of the page (WCAG 2.1.1)"
  status: failed
  reason: "User reported: Not reachable by Tab: the Jobs list is a lazy-loaded relation manager that loads only when scrolled into view, so tabbing from the top goes from the breadcrumb straight out of the page. Every dashboard widget and room section already sets $isLazy = false; ProjectsRelationManager does not. Blocks keyboard use (WCAG 2.1.1)."
  severity: blocker
  test: 2
  artifacts: []
  missing: []
- gap_id: G-07-2.2
  truth: "After Close or Reopen is confirmed, focus lands on the row's replacement action (Reopen after Close, Close after Reopen), never the top of the page (WCAG 2.4.3)"
  status: failed
  reason: "User reported: Focus lost after Close: confirming Close replaces the row's Close button with Reopen, so focus has nowhere to return and drops to the top of the page (the next Tab lands on Skip to content). Reopen likely does the same. Focus should land on the row's replacement action (Reopen after Close, Close after Reopen), never the top of the page (WCAG 2.4.3)."
  severity: major
  test: 2
  artifacts: []
  missing: []
- gap_id: G-07-2.3
  truth: "Each row action's accessible name names its project by label (\"Close Johnny\", \"Reopen Job 1\", \"Rename Job 2\"), visible text unchanged"
  status: failed
  reason: "User reported: Ian wants each row button to name its job for screen readers: \"Close Johnny\", \"Reopen Job 1\", \"Rename Job 2\", using the job's label, with the visible text unchanged."
  severity: major
  test: 2
  artifacts: []
  missing: []
