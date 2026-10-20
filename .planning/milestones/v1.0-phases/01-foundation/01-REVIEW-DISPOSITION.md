---
phase: 01
review: 01-REVIEW.md
titles: json
findings:
  - id: WR-01
    severity: warning
    disposition: fixed
    title: "A contact-creation race returns 500 and drops the lead"
  - id: WR-02
    severity: warning
    disposition: fixed
    title: "A dispatch failure after commit is never retried"
  - id: WR-03
    severity: warning
    disposition: fixed
    title: "The card job retries in a tight loop on any exception"
  - id: WR-04
    severity: warning
    disposition: fixed
    title: "The records writer creates the business folder when the path is wrong"
  - id: WR-05
    severity: warning
    disposition: fixed
    title: "A changelog write failure leaves a half-finished sync"
  - id: WR-06
    severity: warning
    disposition: fixed
    title: "Customer-controlled text is rendered as HTML in owner notifications"
  - id: WR-07
    severity: warning
    disposition: fixed
    title: "Close and Reopen trust a stale page and are not idempotent"
  - id: WR-08
    severity: warning
    disposition: fixed
    title: "No upper bound on cost and price lets one number break every lead"
  - id: WR-09
    severity: warning
    disposition: fixed
    title: "Email matching is unordered and can create duplicate emails"
  - id: WR-10
    severity: warning
    disposition: fixed
    title: "Test data can collide with the fixed numbers the tests use"
  - id: WR-11
    severity: warning
    disposition: deferred
    title: "The lead throttle is per IP with no trusted-proxy setup"
  - id: WR-12
    severity: warning
    disposition: fixed
    title: "Sending an owner reply is not idempotent across failure and tabs"
  - id: WR-13
    severity: warning
    disposition: fixed
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
open: 6
total: 19
recorded: 2026-10-11T17:13:27.789Z
---

# Phase 01: Code Review Disposition

| Finding | Severity | Disposition | Source |
|---------|----------|-------------|--------|
| WR-01 | warning | fixed | 01-REVIEW-FIX.md |
| WR-02 | warning | fixed | Phase 2, fixed 2026-10-11 (D-42): plan 02-08 added the `dispatched_at` claim and the retry path (a retried webhook whose message was recorded but never queued is queued then); plan 02-09 added the five-minute sweep for anything still undispatched |
| WR-03 | warning | fixed | 01-REVIEW-FIX.md |
| WR-04 | warning | fixed | 01-REVIEW-FIX.md |
| WR-05 | warning | fixed | 01-REVIEW-FIX.md |
| WR-06 | warning | fixed | 01-REVIEW-FIX.md |
| WR-07 | warning | fixed | 01-REVIEW-FIX.md |
| WR-08 | warning | fixed | 01-REVIEW-FIX.md |
| WR-09 | warning | fixed | 01-REVIEW-FIX.md |
| WR-10 | warning | fixed | 01-REVIEW-FIX.md |
| WR-11 | warning | deferred | First deploy, with T-01-36 and T-01-37 |
| WR-12 | warning | fixed | 01-REVIEW-FIX.md |
| WR-13 | warning | fixed | Phase 2, fixed 2026-10-11 (D-41, D-43): plan 02-09 keeps form leads away from the front desk and records `meta.matched_by` on each form message; plan 02-18 lets a form name only fill a contact the form created. Texting form leads first needs an SMS-consent step, which stays a deferred idea |
| IN-01 | info | open | - |
| IN-02 | info | open | - |
| IN-03 | info | open | - |
| IN-04 | info | open | - |
| IN-05 | info | open | - |
| IN-06 | info | open | - |

Dispositions: `open` (recorded, not yet triaged), `fixed`, `skipped`, `deferred`.
Set `deferred` by hand and put the reason in the Source cell; both are preserved. A `|` in the reason is kept as prose and escaped on the next run.
Re-running the gate keeps every row it can. A row the current review no longer reports is kept and its Source cell flagged, so a finding does not leave this record silently. ONE exception: when a finding id is REUSED by a different finding, the earlier decision cannot keep a row — the id is taken — and it is dropped. A RECORDED decision (anything but `open`) is named on the console when that happens; a row still at `open` is replaced silently, because `open` records no decision to lose.
