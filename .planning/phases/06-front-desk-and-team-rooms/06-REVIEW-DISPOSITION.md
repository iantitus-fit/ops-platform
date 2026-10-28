---
phase: 06
review: 06-REVIEW.md
titles: json
findings:
  - id: WR-01
    severity: warning
    disposition: open
    title: "`ProcessInbound::$claimToken` is uninitialized on any job queued before this deploy, so both `handle()` and `failed()` throw"
  - id: WR-02
    severity: warning
    disposition: open
    title: "A failure is reported against different rows depending on which path reports first"
  - id: WR-03
    severity: warning
    disposition: open
    title: "Giving the report claim back can itself throw and replace the original exception"
  - id: WR-04
    severity: warning
    disposition: open
    title: "`RoomRegistry::guardClaimedWidgets` misses duplicates inside one room and overlap with the room's own sections"
  - id: IN-01
    severity: info
    disposition: open
    title: "`Message` mass-assignment allows the two new claim columns, unlike the equivalent alert column"
  - id: IN-02
    severity: info
    disposition: open
    title: "Recipient lookup and alert-row construction are duplicated between `handle()` and `forBusiness()`"
open: 6
total: 6
recorded: 2026-10-28T16:49:31.956Z
---

# Phase 06: Code Review Disposition

| Finding | Severity | Disposition | Source |
|---------|----------|-------------|--------|
| WR-01 | warning | open | - |
| WR-02 | warning | open | - |
| WR-03 | warning | open | - |
| WR-04 | warning | open | - |
| IN-01 | info | open | - |
| IN-02 | info | open | - |

Dispositions: `open` (recorded, not yet triaged), `fixed`, `skipped`, `deferred`.
Set `deferred` by hand and put the reason in the Source cell; both are preserved. A `|` in the reason is kept as prose and escaped on the next run.
Re-running the gate keeps every row it can. A row the current review no longer reports is kept and its Source cell flagged, so a finding does not leave this record silently. ONE exception: when a finding id is REUSED by a different finding, the earlier decision cannot keep a row — the id is taken — and it is dropped. A RECORDED decision (anything but `open`) is named on the console when that happens; a row still at `open` is replaced silently, because `open` records no decision to lose.
