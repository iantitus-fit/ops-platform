---
phase: 01
review: 01-REVIEW.md
titles: json
findings:
  - id: WR-01
    severity: warning
    disposition: open
    title: "A contact-creation race returns 500 and drops the lead"
  - id: WR-02
    severity: warning
    disposition: open
    title: "A dispatch failure after commit is never retried"
  - id: WR-03
    severity: warning
    disposition: open
    title: "The card job retries in a tight loop on any exception"
  - id: WR-04
    severity: warning
    disposition: open
    title: "The records writer creates the business folder when the path is wrong"
  - id: WR-05
    severity: warning
    disposition: open
    title: "A changelog write failure leaves a half-finished sync"
  - id: WR-06
    severity: warning
    disposition: open
    title: "Customer-controlled text is rendered as HTML in owner notifications"
  - id: WR-07
    severity: warning
    disposition: open
    title: "Close and Reopen trust a stale page and are not idempotent"
  - id: WR-08
    severity: warning
    disposition: open
    title: "No upper bound on cost and price lets one number break every lead"
  - id: WR-09
    severity: warning
    disposition: open
    title: "Email matching is unordered and can create duplicate emails"
  - id: WR-10
    severity: warning
    disposition: open
    title: "Test data can collide with the fixed numbers the tests use"
  - id: WR-11
    severity: warning
    disposition: open
    title: "The lead throttle is per IP with no trusted-proxy setup"
  - id: WR-12
    severity: warning
    disposition: open
    title: "Sending an owner reply is not idempotent across failure and tabs"
  - id: WR-13
    severity: warning
    disposition: open
    title: "The public form can steer outbound texts to someone else's number (before Phase 2)"
  - id: IN-01
    severity: info
    disposition: open
    title: "The \"Regex\" validation message is wrong for slugs and intake keys"
  - id: IN-02
    severity: info
    disposition: open
    title: "Scaffolding and duplicate helpers left behind"
  - id: IN-03
    severity: info
    disposition: open
    title: "Rejected syncs add a row each and consume version numbers"
  - id: IN-04
    severity: info
    disposition: open
    title: "The loader silently ignores files with an uppercase extension"
  - id: IN-05
    severity: info
    disposition: open
    title: "`records:rebuild` can delete a card written during the rebuild"
  - id: IN-06
    severity: info
    disposition: open
    title: "`ConversationThread` relies entirely on the Filament panel for authorization"
open: 19
total: 19
recorded: 2026-10-11T15:46:20.952Z
---

# Phase 01: Code Review Disposition

| Finding | Severity | Disposition | Source |
|---------|----------|-------------|--------|
| WR-01 | warning | open | - |
| WR-02 | warning | open | - |
| WR-03 | warning | open | - |
| WR-04 | warning | open | - |
| WR-05 | warning | open | - |
| WR-06 | warning | open | - |
| WR-07 | warning | open | - |
| WR-08 | warning | open | - |
| WR-09 | warning | open | - |
| WR-10 | warning | open | - |
| WR-11 | warning | open | - |
| WR-12 | warning | open | - |
| WR-13 | warning | open | - |
| IN-01 | info | open | - |
| IN-02 | info | open | - |
| IN-03 | info | open | - |
| IN-04 | info | open | - |
| IN-05 | info | open | - |
| IN-06 | info | open | - |

Dispositions: `open` (recorded, not yet triaged), `fixed`, `skipped`, `deferred`.
Set `deferred` by hand and put the reason in the Source cell; both are preserved. A `|` in the reason is kept as prose and escaped on the next run.
Re-running the gate keeps every row it can. A row the current review no longer reports is kept and its Source cell flagged, so a finding does not leave this record silently. ONE exception: when a finding id is REUSED by a different finding, the earlier decision cannot keep a row — the id is taken — and it is dropped. A RECORDED decision (anything but `open`) is named on the console when that happens; a row still at `open` is replaced silently, because `open` records no decision to lose.
