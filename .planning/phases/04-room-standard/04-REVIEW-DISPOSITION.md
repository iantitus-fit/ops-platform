---
phase: 04
review: 04-REVIEW.md
titles: json
findings:
  - id: WR-01
    severity: warning
    disposition: fixed
    title: "A room's morph map can silently replace a core alias"
  - id: WR-02
    severity: warning
    disposition: fixed
    title: "A router file inside a room's folder counts as an \"owned file\" and suppresses the empty-state line"
  - id: WR-03
    severity: warning
    disposition: fixed
    title: "`RoomRegistry::add()` does not validate sections, timeline entries or duplicate entry keys"
  - id: WR-04
    severity: warning
    disposition: fixed
    title: "A room chooses the trust label on its timeline entries, and nothing makes it an untrusted one"
  - id: IN-01
    severity: info
    disposition: fixed
    title: "A single failed dispatch in `queueCardsForRoomChanges` skips every remaining contact"
  - id: IN-02
    severity: info
    disposition: fixed
    title: "The room-contract path pattern is written three times"
  - id: IN-03
    severity: info
    disposition: fixed
    title: "`Relation::$morphMap` is static and leaks between tests"
  - id: IN-04
    severity: info
    disposition: fixed
    title: "`ReceivePing`, the template other rooms copy, writes to the outside system before the database and has no transaction"
  - id: IN-05
    severity: info
    disposition: fixed
    title: "A `type: connector` file is accepted at any path"
  - id: IN-06
    severity: info
    disposition: fixed
    title: "`RoomChecks.php:82` puts a path-derived folder name in the message without `PlainWords::oneLine()`"
open: 0
total: 10
recorded: 2026-10-23T17:01:58.546Z
---

# Phase 04: Code Review Disposition

| Finding | Severity | Disposition | Source |
|---------|----------|-------------|--------|
| WR-01 | warning | fixed | 04-REVIEW-FIX.md |
| WR-02 | warning | fixed | 04-REVIEW-FIX.md |
| WR-03 | warning | fixed | 04-REVIEW-FIX.md |
| WR-04 | warning | fixed | 04-REVIEW-FIX.md |
| IN-01 | info | fixed | 04-REVIEW-FIX.md |
| IN-02 | info | fixed | 04-REVIEW-FIX.md |
| IN-03 | info | fixed | 04-REVIEW-FIX.md |
| IN-04 | info | fixed | 04-REVIEW-FIX.md |
| IN-05 | info | fixed | 04-REVIEW-FIX.md |
| IN-06 | info | fixed | 04-REVIEW-FIX.md |

Dispositions: `open` (recorded, not yet triaged), `fixed`, `skipped`, `deferred`.
Set `deferred` by hand and put the reason in the Source cell; both are preserved. A `|` in the reason is kept as prose and escaped on the next run.
Re-running the gate keeps every row it can. A row the current review no longer reports is kept and its Source cell flagged, so a finding does not leave this record silently. ONE exception: when a finding id is REUSED by a different finding, the earlier decision cannot keep a row — the id is taken — and it is dropped. A RECORDED decision (anything but `open`) is named on the console when that happens; a row still at `open` is replaced silently, because `open` records no decision to lose.
