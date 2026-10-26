---
phase: 05
review: 05-REVIEW.md
titles: json
findings:
  - id: WR-01
    severity: warning
    disposition: fixed
    title: "A file with non-UTF-8 bytes between card or ID digit groups gets past the number check"
  - id: IN-01
    severity: info
    disposition: open
    title: "`GoogleBusinessProfileSection::connectedToGoogle()` can never return true, and the live-driver list repeats a fact the provider already holds"
  - id: IN-02
    severity: info
    disposition: open
    title: "The scan reads only ASCII digits, so fullwidth and other Unicode digits are not seen"
  - id: WR-02
    severity: warning
    disposition: fixed
    title: "`GoogleBusinessProfileSection` can take down the whole dashboard on a bad driver setting"
  - id: WR-03
    severity: warning
    disposition: fixed
    title: "`RoomChecks::bothClaimError` puts the owner's raw `owns` entry in the message"
  - id: IN-03
    severity: info
    disposition: deferred
    title: "`GbpCategoriesType` comparison edge cases"
  - id: IN-04
    severity: info
    disposition: fixed
    title: "`OfferSentences` usage in `MarketingSection` indexes the result of `offer()` without a null guard"
  - id: IN-05
    severity: info
    disposition: fixed
    title: "Example folder router does not mention the new folders"
open: 2
total: 8
recorded: 2026-10-26T17:56:24.560Z
---

# Phase 05: Code Review Disposition

| Finding | Severity | Disposition | Source |
|---------|----------|-------------|--------|
| WR-01 | warning | fixed | 1d76a8e (failing tests), 0fef6e8 (fix) |
| IN-01 | info | open | - |
| IN-02 | info | open | - |
| WR-02 | warning | fixed | 05-REVIEW-FIX.md (not in the current review) |
| WR-03 | warning | fixed | 05-REVIEW-FIX.md (not in the current review) |
| IN-03 | info | deferred | the invalid-text part was fixed in b4a6178; the hidden-characters part waits for the deferred hidden-characters-in-values decision (not in the current review) |
| IN-04 | info | fixed | 05-REVIEW-FIX.md (not in the current review) |
| IN-05 | info | fixed | 05-REVIEW-FIX.md (not in the current review) |

Dispositions: `open` (recorded, not yet triaged), `fixed`, `skipped`, `deferred`.
Set `deferred` by hand and put the reason in the Source cell; both are preserved. A `|` in the reason is kept as prose and escaped on the next run.
Re-running the gate keeps every row it can. A row the current review no longer reports is kept and its Source cell flagged, so a finding does not leave this record silently. ONE exception: when a finding id is REUSED by a different finding, the earlier decision cannot keep a row — the id is taken — and it is dropped. A RECORDED decision (anything but `open`) is named on the console when that happens; a row still at `open` is replaced silently, because `open` records no decision to lose.
