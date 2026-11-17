---
phase: 09
review: 09-REVIEW.md
titles: json
findings:
  - id: WR-01
    severity: warning
    disposition: open
    title: "A room toggle deletes the two files at once but leaves the router naming them until the nightly rebuild"
  - id: WR-02
    severity: warning
    disposition: open
    title: "Opt-out reconstruction does not match the record-time rule it says it mirrors"
  - id: WR-03
    severity: warning
    disposition: open
    title: "The nightly rebuild has a one-minute window and no catch-up"
  - id: IN-01
    severity: info
    disposition: open
    title: "`EditVisitNote` fires `ScheduleChanged` for a change nothing in the new files shows"
  - id: IN-02
    severity: info
    disposition: open
    title: "The room's CONTEXT.md sample does not match what `TodayRecord` writes"
  - id: IN-03
    severity: info
    disposition: open
    title: "Platform code now imports the optional room's classes"
open: 6
total: 6
recorded: 2026-11-17T16:47:45.302Z
---

# Phase 09: Code Review Disposition

| Finding | Severity | Disposition | Source |
|---------|----------|-------------|--------|
| WR-01 | warning | open | - |
| WR-02 | warning | open | - |
| WR-03 | warning | open | - |
| IN-01 | info | open | - |
| IN-02 | info | open | - |
| IN-03 | info | open | - |

Dispositions: `open` (recorded, not yet triaged), `fixed`, `skipped`, `deferred`.
Set `deferred` by hand and put the reason in the Source cell; both are preserved. A `|` in the reason is kept as prose and escaped on the next run.
Re-running the gate keeps every row it can. A row the current review no longer reports is kept and its Source cell flagged, so a finding does not leave this record silently. ONE exception: when a finding id is REUSED by a different finding, the earlier decision cannot keep a row — the id is taken — and it is dropped. A RECORDED decision (anything but `open`) is named on the console when that happens; a row still at `open` is replaced silently, because `open` records no decision to lose.
