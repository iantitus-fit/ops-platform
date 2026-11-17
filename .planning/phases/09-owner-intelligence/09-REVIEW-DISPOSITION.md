---
phase: 09
review: 09-REVIEW.md
titles: json
findings:
  - id: WR-01
    severity: warning
    disposition: fixed
    title: "A room toggle deletes the two files at once but leaves the router naming them until the nightly rebuild"
  - id: WR-02
    severity: warning
    disposition: fixed
    title: "Opt-out reconstruction does not match the record-time rule it says it mirrors"
  - id: WR-03
    severity: warning
    disposition: fixed
    title: "The nightly rebuild has a one-minute window and no catch-up"
  - id: IN-01
    severity: info
    disposition: fixed
    title: "`EditVisitNote` fires `ScheduleChanged` for a change nothing in the new files shows"
  - id: IN-02
    severity: info
    disposition: fixed
    title: "The room's CONTEXT.md sample does not match what `TodayRecord` writes"
  - id: IN-03
    severity: info
    disposition: skipped
    title: "Platform code now imports the optional room's classes"
open: 0
total: 6
recorded: 2026-11-17T16:47:45.302Z
---

# Phase 09: Code Review Disposition

| Finding | Severity | Disposition | Source |
|---------|----------|-------------|--------|
| WR-01 | warning | fixed | Ian, 2026-11-17: fixed test first as ruled: writeOwnerIntelligence() rewrites records/CLAUDE.md inside the records:owner-intelligence lock, in the same section that writes or deletes today.md and time-to-booked.md, so the router follows the room at the next sync (D-15, D-25); rebuild() no longer writes the router separately and its output line is unchanged; the BLUEPRINT "next sync or rebuild" sentence is now true. Test: RedrawTest syncs with the room, without it (router names neither file) and with it again (router is the tracked room-on text); test 3a82ac4, fix 28b85b3 |
| WR-02 | warning | fixed | Ian, 2026-11-17: fixed test first, the reviewer's first option: RecordInbound stores the keyword decision on every inbound it records as meta.keyword (stop, start, help, or null for an ordinary text or a form); TimeToBooked trusts a present key and keeps the old reconstruction only for rows without it (recorded before this change); still one SELECT (D-04). Test: a contact opted out by a carrier error whose first inbound is YES does not start the clock; test 3263376, fix b2a4fc6 |
| WR-03 | warning | fixed | Ian, 2026-11-17: fixed test first as ruled: the rebuild runs once per business-local date on any tick from 00:10 to before 01:00, guarded by a per-date cache marker that onSuccess sets only after the command exits 0, so a late tick still runs it and a failed run is retried within the window; onOneServer() and the lazy zone read kept; NightlyRebuildTest's 00:11 assertion and daylight-saving test changed, three tests added; docs/SETUP.md updated; test 35128c9, fix e27fd7f. Follow-up (Ian, 2026-11-17): the overlap lock lowered from 120 to 30 minutes so a hard-killed run's lock expires inside the window and the next tick retries; NightlyRebuildTest's expiresAt assertion changed to 30 and a killed-run retry test added; docs/SETUP.md says a run killed after 00:30 still skips the night; test 311191c, fix e7dfba1 |
| IN-01 | info | fixed | Ian, 2026-11-17: kept by design (D-14): EditVisitNote keeps its ScheduleChanged dispatch, and its docblock now says the dispatch is deliberate (the reviewer's second option). Docblock only, so no failing test; ca192d6 |
| IN-02 | info | fixed | Ian, 2026-11-17: fixed test first as ruled: the today.md sample in examples/example-hvac/rooms/owner_intelligence/CONTEXT.md is the D-13 day as TodayRecord writes it (title, read-only note, bullets with card links, a block with its note quoted under the label), and TodayRecordTest renders that day and checks it equals the sample; test 11e1b51, fix 4d38d46 |
| IN-03 | info | skipped | Ian, 2026-11-17: accepted, no change. The room's code ships with the platform; only the folder is optional, and every use is behind hasRoom() (D-09) |

Dispositions: `open` (recorded, not yet triaged), `fixed`, `skipped`, `deferred`.
Set `deferred` by hand and put the reason in the Source cell; both are preserved. A `|` in the reason is kept as prose and escaped on the next run.
Re-running the gate keeps every row it can. A row the current review no longer reports is kept and its Source cell flagged, so a finding does not leave this record silently. ONE exception: when a finding id is REUSED by a different finding, the earlier decision cannot keep a row — the id is taken — and it is dropped. A RECORDED decision (anything but `open`) is named on the console when that happens; a row still at `open` is replaced silently, because `open` records no decision to lose.
