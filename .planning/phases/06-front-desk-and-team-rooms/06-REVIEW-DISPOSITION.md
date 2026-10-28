---
phase: 06
review: 06-REVIEW.md
titles: json
findings:
  - id: WR-01
    severity: warning
    disposition: open
    title: "The report is written against the caller's row, but the claim and the \"already reported\" check use two different rows"
  - id: WR-02
    severity: warning
    disposition: open
    title: "A report claim taken by a reporter that then dies is not recovered when `failed()` arrives inside the lease"
  - id: WR-03
    severity: warning
    disposition: open
    title: "A room can claim a widget that another room adds in its sections, and the reverse"
  - id: IN-01
    severity: info
    disposition: open
    title: "The first migration was edited in place and its column changed meaning"
  - id: IN-02
    severity: info
    disposition: open
    title: "Two tests depend on the exact SQL text and the order of bindings"
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
  - id: WR-04
    severity: warning
    disposition: fixed
    title: "`RoomRegistry::guardClaimedWidgets` misses duplicates inside one room and overlap with the room's own sections"
open: 5
total: 10
recorded: 2026-10-28T23:16:52.205Z
---

# Phase 06: Code Review Disposition

| Finding | Severity | Disposition | Source |
|---------|----------|-------------|--------|
| WR-01 | warning | open | - |
| WR-02 | warning | open | - |
| WR-03 | warning | open | - |
| IN-01 | info | open | - |
| IN-02 | info | open | - |
| WR-05 | warning | fixed | the already-reported lookup runs inside the try, so a throw there gives the claim back and the original exception escapes; test 0724e37, fix 5777864 (Ian, 2026-10-28) (not in the current review) |
| WR-06 | warning | fixed | fixed for the dead worker: the report claim is failure_report_claimed_at, a 2-minute lease like the alert send claim, and failure_reported_at marks a finished report; a reporter that died after claiming is reported by the next path. Two narrow repeats remain, never a loss: a crash after the note and before the alert gives one alert and a second note; and on a mixed burst, a RunFrontDesk crash after its alert (on the form row) and before the report is marked finished gives a second alert, because failed() looks for an alert on the text row (the WR-02 split, kept). The double failure (report and release both fail, failed() inside the lease) is recorded as an open item in BLUEPRINT section 6; tests d95e5d3, fix ff8749e, BLUEPRINT f940c9e (Ian, 2026-10-28) (not in the current review) |
| IN-03 | info | fixed | guardClaimedWidgets refuses a non-string entry with a LogicException, checked first in the loop; test e78c127, fix e0fbb55 (Ian, 2026-10-28) (not in the current review) |
| IN-04 | info | fixed | the WR-03 test matches the SET clause '"failure_report_claimed_at" = ?' with a null binding 0, with a comment on why; 92f321f (Ian, 2026-10-28) (not in the current review) |
| WR-04 | warning | fixed | guardClaimedWidgets refuses a widget claimed twice in one room and a widget both in sections and claimed; tests 0dc67a3, fix be95f45, BLUEPRINT D-18 lines (Ian, 2026-10-28) (not in the current review) |

Dispositions: `open` (recorded, not yet triaged), `fixed`, `skipped`, `deferred`.
Set `deferred` by hand and put the reason in the Source cell; both are preserved. A `|` in the reason is kept as prose and escaped on the next run.
Re-running the gate keeps every row it can. A row the current review no longer reports is kept and its Source cell flagged, so a finding does not leave this record silently. ONE exception: when a finding id is REUSED by a different finding, the earlier decision cannot keep a row — the id is taken — and it is dropped. A RECORDED decision (anything but `open`) is named on the console when that happens; a row still at `open` is replaced silently, because `open` records no decision to lose.
