---
phase: 06
review: 06-REVIEW.md
titles: json
findings:
  - id: WR-02
    severity: warning
    disposition: skipped
    title: "A failure is reported against different rows depending on which path reports first"
  - id: WR-05
    severity: warning
    disposition: open
    title: "The legacy \"already reported\" query runs after the claim is taken but outside the try, so a database error there leaves the claim held and the owner never told"
  - id: WR-06
    severity: warning
    disposition: open
    title: "The report claim has no expiry, so a failed release (the case WR-03 now swallows) or a process death between claim and write loses the alert for good"
  - id: IN-03
    severity: info
    disposition: open
    title: "`guardClaimedWidgets` does not validate element types, unlike `guardHandles`"
  - id: IN-04
    severity: info
    disposition: open
    title: "The WR-03 test depends on the exact SQL shape of the release update"
  - id: WR-01
    severity: warning
    disposition: skipped
    title: "`ProcessInbound::$claimToken` is uninitialized on any job queued before this deploy, so both `handle()` and `failed()` throw"
  - id: WR-03
    severity: warning
    disposition: fixed
    title: "Giving the report claim back can itself throw and replace the original exception"
  - id: WR-04
    severity: warning
    disposition: fixed
    title: "`RoomRegistry::guardClaimedWidgets` misses duplicates inside one room and overlap with the room's own sections"
  - id: IN-01
    severity: info
    disposition: fixed
    title: "`Message` mass-assignment allows the two new claim columns, unlike the equivalent alert column"
  - id: IN-02
    severity: info
    disposition: open
    title: "Recipient lookup and alert-row construction are duplicated between `handle()` and `forBusiness()`"
open: 5
total: 10
recorded: 2026-10-28T18:59:54.906Z
---

# Phase 06: Code Review Disposition

| Finding | Severity | Disposition | Source |
|---------|----------|-------------|--------|
| WR-02 | warning | skipped | design kept: 06-07 kept RunFrontDesk's report subject ($burst->last()) so its front_desk_failed alerts stay on one message and its one-alert check holds; the claim on subjectOf() still makes exactly one report. Pinned by FailureReportOnceTest 'reports a mixed burst once, on its newest row, when RunFrontDesk reports first and failed() runs after' (10092d9) (Ian, 2026-10-28) |
| WR-05 | warning | open | - |
| WR-06 | warning | open | - |
| IN-03 | info | open | - |
| IN-04 | info | open | - |
| WR-01 | warning | skipped | won't fix: no live instance can hold a ProcessInbound payload from before 06-06, because the first deploy (Phase 12) starts with an empty queue; the suggested token minted lazily in handle() is the D-12 pattern the 06-06 checker rejected. BLUEPRINT section 10 records the upgrade rule: drain the queue (queue:work --stop-when-empty) when a queued job gains a required field (Ian, 2026-10-28) (not in the current review) |
| WR-03 | warning | fixed | the claim release runs in its own try, a release failure goes to report() and the original exception is always rethrown; test 10092d9, fix 983fdb3 (Ian, 2026-10-28) (not in the current review) |
| WR-04 | warning | fixed | guardClaimedWidgets refuses a widget claimed twice in one room and a widget both in sections and claimed; tests 0dc67a3, fix be95f45, BLUEPRINT D-18 lines (Ian, 2026-10-28) (not in the current review) |
| IN-01 | info | fixed | claim_token and failure_reported_at left Message's Fillable; test 081fafb, fix 5ea3d20 (Ian, 2026-10-28) (not in the current review) |
| IN-02 | info | open | - (not in the current review) |

Dispositions: `open` (recorded, not yet triaged), `fixed`, `skipped`, `deferred`.
Set `deferred` by hand and put the reason in the Source cell; both are preserved. A `|` in the reason is kept as prose and escaped on the next run.
Re-running the gate keeps every row it can. A row the current review no longer reports is kept and its Source cell flagged, so a finding does not leave this record silently. ONE exception: when a finding id is REUSED by a different finding, the earlier decision cannot keep a row — the id is taken — and it is dropped. A RECORDED decision (anything but `open`) is named on the console when that happens; a row still at `open` is replaced silently, because `open` records no decision to lose.
