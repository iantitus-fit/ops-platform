---
status: complete
phase: 05-standard-rooms
source: [05-VERIFICATION.md]
started: 2026-10-25T00:00:00Z
updated: 2026-10-26T16:54:07.814Z
---

## Current Test

[testing complete]

## Tests

### 1. Keyboard-only and VoiceOver walk of each new room section on /admin, plus the marketing offers line on the day an offer ends
expected: Every section (company knowledge; marketing; content generation empty and with the brief; money empty and with the notes; Google Business Profile empty and with the categories file) is reachable and readable by keyboard and screen reader, headings and lines are announced in a sensible order, and the offers line matches what the front desk would send. (WINDOWS.md unrun-verify entries 20 to 24; carries the Phase 4 walk.)
result: pass
notes: "All five sections checked empty and furnished (v9 and v10 of a scratch copy). Screen reader structure was checked from Chrome's accessibility tree instead of a live VoiceOver walk (Ian's call): each section is a region named by a real H2, lines read in on-screen order, nothing hidden, no focusable elements; the full VoiceOver walk is part of the Phase 11 owner-screen pass (A11Y-03). Zoom at 200%: nothing cut off, no sideways scrolling. Offers line: an offer whose end_date is today shows as live (v11); one that ended yesterday shows \"none\" (v12)."

### 2. Push the branch and read the CI result for the pgsql leg
expected: All five room boot tests (empty and with the example folder) and the rest of the suite pass on Postgres 17 as they do on SQLite (2148/2148 locally).
result: pass
notes: "CI run #10 on 755957f passed on both SQLite and Postgres."

## Summary

total: 2
passed: 2
issues: 0
pending: 0
skipped: 0
blocked: 0

## Gaps

[none yet]

## Notes for Close

<!-- Not Phase 5 gaps. Add to docs/BLUEPRINT.md open items when the phase closes. -->
- The Drafts waiting table has 10 links with no name in its Alert column (Phase 11, A11Y-03).
- Each room's empty line repeats the room name right under its heading (wording pass, 04 D-21).
- Room section status lines have no affordance: "Not connected to Google" and the empty lines read like any other sentence, with no action, link or distinct treatment (not by color alone) (Phase 11 layout pass; 05-UI-REVIEW.md item 1).
- Room section empty lines are developer-oriented, built from raw contract paths such as "Add notes/money.md to start." (Phase 11 wording pass; 05-UI-REVIEW.md item 2).
