---
phase: 05
review: 05-REVIEW.md
titles: json
findings:
  - id: WR-01
    severity: warning
    disposition: fixed
    title: "The card/ID scan can be bypassed by ordinary separators and by a preceding number"
  - id: WR-02
    severity: warning
    disposition: open
    title: "`GoogleBusinessProfileSection` can take down the whole dashboard on a bad driver setting"
  - id: WR-03
    severity: warning
    disposition: fixed
    title: "`RoomChecks::bothClaimError` puts the owner's raw `owns` entry in the message"
  - id: IN-01
    severity: info
    disposition: open
    title: "`Loader::ROOM_CONTRACT` ends in `$`, which accepts a trailing newline"
  - id: IN-02
    severity: info
    disposition: fixed
    title: "`CompanyKnowledgeSection` hardcodes the FAQ path"
  - id: IN-03
    severity: info
    disposition: open
    title: "`GbpCategoriesType` comparison edge cases"
  - id: IN-04
    severity: info
    disposition: open
    title: "`OfferSentences` usage in `MarketingSection` indexes the result of `offer()` without a null guard"
  - id: IN-05
    severity: info
    disposition: fixed
    title: "Example folder router does not mention the new folders"
open: 4
total: 8
recorded: 2026-10-26T02:33:48.954Z
---

# Phase 05: Code Review Disposition

| Finding | Severity | Disposition | Source |
|---------|----------|-------------|--------|
| WR-01 | warning | fixed | 05-REVIEW-FIX.md |
| WR-02 | warning | open | - |
| WR-03 | warning | fixed | 05-REVIEW-FIX.md |
| IN-01 | info | open | - |
| IN-02 | info | fixed | 05-REVIEW-FIX.md |
| IN-03 | info | open | - |
| IN-04 | info | open | - |
| IN-05 | info | fixed | 05-REVIEW-FIX.md |

Dispositions: `open` (recorded, not yet triaged), `fixed`, `skipped`, `deferred`.
Set `deferred` by hand and put the reason in the Source cell; both are preserved. A `|` in the reason is kept as prose and escaped on the next run.
Re-running the gate keeps every row it can. A row the current review no longer reports is kept and its Source cell flagged, so a finding does not leave this record silently. ONE exception: when a finding id is REUSED by a different finding, the earlier decision cannot keep a row — the id is taken — and it is dropped. A RECORDED decision (anything but `open`) is named on the console when that happens; a row still at `open` is replaced silently, because `open` records no decision to lose.
