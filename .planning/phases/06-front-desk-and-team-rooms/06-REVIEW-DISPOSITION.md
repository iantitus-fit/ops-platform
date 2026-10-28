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
    disposition: fixed
    title: "The legacy \"already reported\" query runs after the claim is taken but outside the try, so a database error there leaves the claim held and the owner never told"
  - id: WR-06
    severity: warning
    disposition: fixed
    title: "The report claim has no expiry, so a failed release (the case WR-03 now swallows) or a process death between claim and write loses the alert for good"
  - id: IN-03
    severity: info
    disposition: fixed
    title: "`guardClaimedWidgets` does not validate element types, unlike `guardHandles`"
  - id: IN-04
    severity: info
    disposition: fixed
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
open: 1
total: 10
recorded: 2026-10-28T21:10:00.000Z
---

# Phase 06: Code Review Disposition

| Finding | Severity | Disposition | Source |
|---------|----------|-------------|--------|
| WR-02 | warning | skipped | design kept: 06-07 kept RunFrontDesk's report subject ($burst->last()) so its front_desk_failed alerts stay on one message and its one-alert check holds; the claim on subjectOf() still makes exactly one report. Pinned by FailureReportOnceTest 'reports a mixed burst once, on its newest row, when RunFrontDesk reports first and failed() runs after' (10092d9) (Ian, 2026-10-28) |
| WR-05 | warning | fixed | the already-reported lookup runs inside the try, so a throw there gives the claim back and the original exception escapes; test 0724e37, fix 5777864 (Ian, 2026-10-28) |
| WR-06 | warning | fixed | fixed for the dead worker: the report claim is failure_report_claimed_at, a 2-minute lease like the alert send claim, and failure_reported_at marks a finished report; a reporter that died after claiming is reported by the next path, once. The double failure (report and release both fail, failed() inside the lease) is recorded as an open item in BLUEPRINT section 6; tests d95e5d3, fix ff8749e, BLUEPRINT f940c9e (Ian, 2026-10-28) |
| IN-03 | info | fixed | guardClaimedWidgets refuses a non-string entry with a LogicException, checked first in the loop; test e78c127, fix e0fbb55 (Ian, 2026-10-28) |
| IN-04 | info | fixed | the WR-03 test matches the SET clause '"failure_report_claimed_at" = ?' with a null binding 0, with a comment on why; 92f321f (Ian, 2026-10-28) |
| WR-01 | warning | skipped | won't fix: no live instance can hold a ProcessInbound payload from before 06-06, because the first deploy (Phase 12) starts with an empty queue; the suggested token minted lazily in handle() is the D-12 pattern the 06-06 checker rejected. BLUEPRINT section 10 records the upgrade rule: drain the queue (queue:work --stop-when-empty) when a queued job gains a required field (Ian, 2026-10-28) (not in the current review) |
| WR-03 | warning | fixed | the claim release runs in its own try, a release failure goes to report() and the original exception is always rethrown; test 10092d9, fix 983fdb3 (Ian, 2026-10-28) (not in the current review) |
| WR-04 | warning | fixed | guardClaimedWidgets refuses a widget claimed twice in one room and a widget both in sections and claimed; tests 0dc67a3, fix be95f45, BLUEPRINT D-18 lines (Ian, 2026-10-28) (not in the current review) |
| IN-01 | info | fixed | claim_token and failure_reported_at left Message's Fillable; test 081fafb, fix 5ea3d20 (Ian, 2026-10-28) (not in the current review) |
| IN-02 | info | open | - (not in the current review) |

Dispositions: `open` (recorded, not yet triaged), `fixed`, `skipped`, `deferred`.
Set `deferred` by hand and put the reason in the Source cell; both are preserved. A `|` in the reason is kept as prose and escaped on the next run.
Re-running the gate keeps every row it can. A row the current review no longer reports is kept and its Source cell flagged, so a finding does not leave this record silently. ONE exception: when a finding id is REUSED by a different finding, the earlier decision cannot keep a row — the id is taken — and it is dropped. A RECORDED decision (anything but `open`) is named on the console when that happens; a row still at `open` is replaced silently, because `open` records no decision to lose.
