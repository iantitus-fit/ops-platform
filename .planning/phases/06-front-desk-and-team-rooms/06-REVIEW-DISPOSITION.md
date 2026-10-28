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
  - id: WR-07
    severity: warning
    disposition: skipped
    title: "The report is written against the caller's row, but the claim and the \"already reported\" check use two different rows"
  - id: WR-08
    severity: warning
    disposition: deferred
    title: "A report claim taken by a reporter that then dies is not recovered when `failed()` arrives inside the lease"
  - id: WR-09
    severity: warning
    disposition: open
    title: "A room can claim a widget that another room adds in its sections, and the reverse"
  - id: IN-05
    severity: info
    disposition: skipped
    title: "The first migration was edited in place and its column changed meaning"
  - id: IN-06
    severity: info
    disposition: open
    title: "Two tests depend on the exact SQL text and the order of bindings"
open: 3
total: 15
recorded: 2026-10-28T23:45:00.000Z
---

# Phase 06: Code Review Disposition

| Finding | Severity | Disposition | Source |
|---------|----------|-------------|--------|
| WR-02 | warning | skipped | design kept: 06-07 kept RunFrontDesk's report subject ($burst->last()) so its front_desk_failed alerts stay on one message and its one-alert check holds; the claim on subjectOf() still makes exactly one report. Pinned by FailureReportOnceTest 'reports a mixed burst once, on its newest row, when RunFrontDesk reports first and failed() runs after' (10092d9) (Ian, 2026-10-28) |
| WR-05 | warning | fixed | the already-reported lookup runs inside the try, so a throw there gives the claim back and the original exception escapes; test 0724e37, fix 5777864 (Ian, 2026-10-28) |
| WR-06 | warning | fixed | fixed for the dead worker: the report claim is failure_report_claimed_at, a 2-minute lease like the alert send claim, and failure_reported_at marks a finished report; a reporter that died after claiming is reported by the next path. Two narrow repeats remain, never a loss: a crash after the note and before the alert gives one alert and a second note; and on a mixed burst, a RunFrontDesk crash after its alert (on the form row) and before the report is marked finished gives a second alert, because failed() looks for an alert on the text row (the WR-02 split, kept). The double failure (report and release both fail, failed() inside the lease) is recorded as an open item in BLUEPRINT section 6; tests d95e5d3, fix ff8749e, BLUEPRINT f940c9e (Ian, 2026-10-28) |
| IN-03 | info | fixed | guardClaimedWidgets refuses a non-string entry with a LogicException, checked first in the loop; test e78c127, fix e0fbb55 (Ian, 2026-10-28) |
| IN-04 | info | fixed | the WR-03 test matches the SET clause '"failure_report_claimed_at" = ?' with a null binding 0, with a comment on why; 92f321f (Ian, 2026-10-28) |
| WR-01 | warning | skipped | won't fix: no live instance can hold a ProcessInbound payload from before 06-06, because the first deploy (Phase 12) starts with an empty queue; the suggested token minted lazily in handle() is the D-12 pattern the 06-06 checker rejected. BLUEPRINT section 10 records the upgrade rule: drain the queue (queue:work --stop-when-empty) when a queued job gains a required field (Ian, 2026-10-28) (not in the current review) |
| WR-03 | warning | fixed | the claim release runs in its own try, a release failure goes to report() and the original exception is always rethrown; test 10092d9, fix 983fdb3 (Ian, 2026-10-28) (not in the current review) |
| WR-04 | warning | fixed | guardClaimedWidgets refuses a widget claimed twice in one room and a widget both in sections and claimed; tests 0dc67a3, fix be95f45, BLUEPRINT D-18 lines (Ian, 2026-10-28) (not in the current review) |
| IN-01 | info | fixed | claim_token and failure_reported_at left Message's Fillable; test 081fafb, fix 5ea3d20 (Ian, 2026-10-28) (not in the current review) |
| IN-02 | info | open | - (not in the current review) |
| WR-07 | warning | skipped | accepted: on a mixed burst, a crash between the alert and the finished mark gives a second note and alert; this is the repeat case already recorded under WR-06, and a repeat is kept over a loss (02.1 IN-11) (Ian, 2026-10-28) |
| WR-08 | warning | deferred | not fixed in Phase 6: a run that times out while its report is being written gets failed() at once, inside the 2-minute lease, so the claimed report stays unwritten; recorded in BLUEPRINT section 6 with the double-failure open item, which one scheduled sweep closes too (Ian, 2026-10-28) |
| WR-09 | warning | open | no room adds sections today; check when the leads room lands in Phase 7; BLUEPRINT section 6 open item (Ian, 2026-10-28) |
| IN-05 | info | skipped | no action: the edit to migration 000006 was its comment only, and the migration was never on main (Ian, 2026-10-28) |
| IN-06 | info | open | info; tests tied to exact SQL text (Ian, 2026-10-28) |

Dispositions: `open` (recorded, not yet triaged), `fixed`, `skipped`, `deferred`.
Set `deferred` by hand and put the reason in the Source cell; both are preserved. A `|` in the reason is kept as prose and escaped on the next run.
Re-running the gate keeps every row it can. A row the current review no longer reports is kept and its Source cell flagged, so a finding does not leave this record silently. ONE exception: when a finding id is REUSED by a different finding, the earlier decision cannot keep a row — the id is taken — and it is dropped. A RECORDED decision (anything but `open`) is named on the console when that happens; a row still at `open` is replaced silently, because `open` records no decision to lose.
