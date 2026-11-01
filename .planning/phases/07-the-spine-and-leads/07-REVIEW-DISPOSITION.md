---
phase: 07
review: 07-REVIEW.md
titles: json
findings:
  - id: IN-08
    severity: info
    disposition: deferred
    title: "The focus wait swallows every error, not only its timeout"
  - id: IN-09
    severity: info
    disposition: deferred
    title: "The dashboard walk's Tab cap is not measured"
  - id: IN-10
    severity: info
    disposition: open
    title: "The new docblocks cite ledger rows and review ids that have already moved"
  - id: IN-11
    severity: info
    disposition: deferred
    title: "The key-press helper is written twice"
  - id: IN-05
    severity: info
    disposition: fixed
    title: "The browser test's two-second waits may flake on a slow CI runner"
  - id: IN-06
    severity: info
    disposition: open
    title: "New docblocks cite UAT gap, threat and plan ids that go stale once the phase is archived"
  - id: IN-07
    severity: info
    disposition: fixed
    title: "The browser test focuses the row button directly, so Tab reaching the list is proven only without a browser"
  - id: WR-01
    severity: warning
    disposition: fixed
    title: "The opt-out check runs when the job runs, not when the lead arrived, so a real lead can silently lose its project"
  - id: WR-02
    severity: warning
    disposition: fixed
    title: "The \"only four actions write projects\" scan has easy-to-hit blind spots"
  - id: WR-03
    severity: warning
    disposition: fixed
    title: "\"New job\" has no confirmation or double-submit guard, and spurious projects cannot be removed"
  - id: IN-01
    severity: info
    disposition: fixed
    title: "The job drops work silently and has no `failed()` handler"
  - id: IN-02
    severity: info
    disposition: open
    title: "Stale plan references in docblocks"
  - id: IN-03
    severity: info
    disposition: open
    title: "Duplicated whitespace-collapse regex and an unused `$user` parameter"
  - id: IN-04
    severity: info
    disposition: deferred
    title: "The dashboard's opened time has no time-zone label"
open: 4
total: 14
recorded: 2026-11-01T19:20:45.610Z
---

# Phase 07: Code Review Disposition

| Finding | Severity | Disposition | Source |
|---------|----------|-------------|--------|
| IN-08 | info | deferred | Phase 8's first browser walk (the booking page, A11Y-02), fixed together there (Ian, review round 3); docs/BLUEPRINT.md section 10: the focus wait rethrows anything but a timeout |
| IN-09 | info | deferred | Phase 8's first browser walk (the booking page, A11Y-02), fixed together there (Ian, review round 3); docs/BLUEPRINT.md section 10: the dashboard Tab cap is measured and recorded |
| IN-10 | info | open | Folded into IN-06 (Ian, review round 3): docblocks citing WINDOWS row numbers go stale like other plan and gap ids |
| IN-11 | info | deferred | Phase 8's first browser walk (the booking page, A11Y-02), fixed together there (Ian, review round 3); docs/BLUEPRINT.md section 10: the key-press helper moves to tests/Pest.php |
| IN-05 | info | fixed | 08eeec0 (Playwright locator waits, 5-second timeout, replace the fixed 2-second loops) (not in the current review) |
| IN-06 | info | open | - (not in the current review) |
| IN-07 | info | fixed | 08eeec0 (Tab walk from the top of the page to New job, on a desktop window and on a phone) (not in the current review) |
| WR-01 | warning | fixed | b8c90e5 (D-23 amended) (not in the current review) |
| WR-02 | warning | fixed | 1a4a7cf (not in the current review) |
| WR-03 | warning | fixed | f6bb22c (not in the current review) |
| IN-01 | info | fixed | 207916e (not in the current review) |
| IN-02 | info | open | - (not in the current review) |
| IN-03 | info | open | - (not in the current review) |
| IN-04 | info | deferred | Phase 11 wording pass (Ian, 2026-10-31); docs/BLUEPRINT.md section 1 open items (not in the current review) |

Dispositions: `open` (recorded, not yet triaged), `fixed`, `skipped`, `deferred`.
Set `deferred` by hand and put the reason in the Source cell; both are preserved. A `|` in the reason is kept as prose and escaped on the next run.
Re-running the gate keeps every row it can. A row the current review no longer reports is kept and its Source cell flagged, so a finding does not leave this record silently. ONE exception: when a finding id is REUSED by a different finding, the earlier decision cannot keep a row — the id is taken — and it is dropped. A RECORDED decision (anything but `open`) is named on the console when that happens; a row still at `open` is replaced silently, because `open` records no decision to lose.

Triage (Ian, 2026-10-31): WR-01, WR-02, WR-03 and IN-01 fixed test first, one commit each; IN-04 deferred to the Phase 11 wording pass; IN-02 and IN-03 stay open as info. UI review finding 3 (Rename's too-long message) fixed in b155df7 and noted in 07-UI-REVIEW.md.

Triage (Ian, 2026-11-01): IN-05 and IN-07 fixed in 08eeec0, which turns the contact screen's keyboard and screen reader walk into browser tests; a9497bc adds the dashboard's leads walk. IN-06 stays open as info.

Triage (Ian, 2026-11-01, review round 3; stop rule: no locked-rule or correctness finding among them): IN-08, IN-09 and IN-11 deferred to Phase 8's first browser walk (the booking page, A11Y-02), fixed together there: the focus wait rethrows anything but a timeout, the dashboard Tab cap is measured and recorded, and the key-press helper moves to tests/Pest.php. IN-10 is folded into IN-06, open as info.
