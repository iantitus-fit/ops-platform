---
phase: 02
review: 02-REVIEW.md
titles: json
findings:
  - id: WR-01
    severity: warning
    disposition: open
    title: "Widget still counts queued and failed texts as \"the owner spoke last\""
  - id: WR-02
    severity: warning
    disposition: open
    title: "The map note and the enum docblock are stale after the open-leads change"
  - id: IN-01
    severity: info
    disposition: open
    title: "`waitingSince()` and `openLeadsMarkdown()` docblocks do not say the thread is filtered"
  - id: IN-02
    severity: info
    disposition: open
    title: "Map citation points at the method signature, not at the `happenedAt()` calls"
  - id: IN-03
    severity: info
    disposition: open
    title: "Test coverage of the two fixes is one-sided"
  - id: CR-01
    severity: critical
    disposition: fixed
    title: "A retry after the safety line was sent finds it \"already answered\" and skips the pause and the owner alert"
  - id: WR-03
    severity: warning
    disposition: deferred
    title: "`ProcessInbound::failed()` reports on the newest customer text, not on the burst that failed"
  - id: WR-04
    severity: warning
    disposition: fixed
    title: "The claim rollback in `DispatchInbound` does not cover a push deferred by `afterCommit()`"
  - id: WR-05
    severity: warning
    disposition: fixed
    title: "Retry and failure semantics of `RespondToLifeSafety` do not match its comments"
  - id: WR-06
    severity: warning
    disposition: fixed
    title: "`LifeSafety` fails open when a regex errors (verified)"
  - id: IN-04
    severity: info
    disposition: open
    title: "`RaiseOwnerAlert` check-then-insert is not atomic, and a no-recipient alert can be deferred"
  - id: IN-05
    severity: info
    disposition: open
    title: "Test scaffolding leftovers and a narrow queue guard"
  - id: CR-02
    severity: critical
    disposition: open
    title: "Business Folder page crashes while a Phase 1 version is live [slice B, was CR-B01]"
  - id: WR-07
    severity: warning
    disposition: open
    title: "A form that describes a danger gets neither a safety line nor an owner alert [slice A, was WR-A07]"
  - id: WR-08
    severity: warning
    disposition: deferred
    title: "The owner-alert link scrubber is an allow-list of top-level domains [slice A, was WR-A08]"
  - id: WR-09
    severity: warning
    disposition: open
    title: "`gate:export` reports failed and queued sends as \"what went out\", and `original_text` differs by path [slice A, was WR-A09]"
  - id: WR-10
    severity: warning
    disposition: deferred
    title: "The code-level price and discount veto has easy bypasses [slice A, was WR-A10]"
  - id: WR-11
    severity: warning
    disposition: open
    title: "Unauthenticated request with a nested array field turns a 403 into a 500 [slice B, was WR-B01]"
  - id: WR-12
    severity: warning
    disposition: open
    title: "Empty `MessageSid` with a `SmsSid` yields an empty provider message id [slice B, was WR-B02]"
  - id: WR-13
    severity: warning
    disposition: open
    title: "Sync Now only catches `IcmFolderMissing`; invalid UTF-8 or a DB error becomes a 500 [slice B, was WR-B03]"
  - id: WR-14
    severity: warning
    disposition: open
    title: "`ConversationThread::$conversation` is a public, unlocked model property [slice B, was WR-B04]"
  - id: WR-15
    severity: warning
    disposition: deferred
    title: "The unauthenticated fake webhook is on by default in every environment [slice B, was WR-B05]"
  - id: WR-16
    severity: warning
    disposition: open
    title: "The test run is not isolated from `.env`; the documented go-live step breaks the suite [slice C, was WR-C01]"
  - id: WR-17
    severity: warning
    disposition: open
    title: "The reply-cap \"does not count\" test cannot detect most of what it claims [slice C, was WR-C02]"
  - id: WR-18
    severity: warning
    disposition: open
    title: "`toContain('fi-collapsed')` is always true, so \"collapses the customer details section\" is not tested [slice C, was WR-C03]"
  - id: WR-19
    severity: warning
    disposition: open
    title: "\"throws NoActiveVersion from every accessor\" omits the accessors this phase added [slice C, was WR-C04]"
  - id: IN-06
    severity: info
    disposition: open
    title: "Opt-out error code defined twice with different types [slice B, was IN-B01]"
  - id: IN-07
    severity: info
    disposition: open
    title: "Notification titles are escaped inconsistently [slice B, was IN-B02]"
  - id: IN-08
    severity: info
    disposition: open
    title: "A delivery callback that beats the stored sid is dropped for good [slice B, was IN-B03]"
  - id: IN-09
    severity: info
    disposition: open
    title: "CI runs twice for every pull request branch [slice B, was IN-B04]"
  - id: IN-10
    severity: info
    disposition: open
    title: "Pest scaffold leftovers in the shared bootstrap [slice C, was IN-C01]"
  - id: IN-11
    severity: info
    disposition: open
    title: "The same helpers and constants are copied across a dozen files [slice C, was IN-C02]"
  - id: IN-12
    severity: info
    disposition: open
    title: "JevClassifierTest hard-codes the file count the helper exists to centralise [slice C, was IN-C03]"
  - id: IN-13
    severity: info
    disposition: open
    title: "Several test names claim more than they assert [slice C, was IN-C04]"
  - id: IN-14
    severity: info
    disposition: open
    title: "GateExportTest fixture drifts from the real verdict shape it says it copies [slice C, was IN-C05]"
  - id: IN-15
    severity: info
    disposition: open
    title: "sprint-1.md acceptance criterion 3 contradicts the map on `escalate_to` [slice C, was IN-C06]"
  - id: IN-16
    severity: info
    disposition: open
    title: "CLAUDE.md map table omits several app/ folders a new reader will hit first [slice C, was IN-C07]"
  - id: IN-17
    severity: info
    disposition: open
    title: "triage.md tells the owner a position that the code makes irrelevant [slice C, was IN-C08]"
open: 30
total: 38
recorded: 2026-10-13T19:57:13.320Z
---

# Phase 02: Code Review Disposition

| Finding | Severity | Disposition | Source |
|---------|----------|-------------|--------|
| WR-01 | warning | open | - |
| WR-02 | warning | open | - |
| IN-01 | info | open | - |
| IN-02 | info | open | - |
| IN-03 | info | open | - |
| CR-01 | critical | fixed | 02-REVIEW-FIX.md (not in the current review) |
| WR-03 | warning | deferred | partly fixed (bfc52cf); remaining duplicate-alert path deferred to Phase 02.1 with the owner-alert de-dup work; preferred fix is a claim token on the claimed rows, not a time window (not in the current review) |
| WR-04 | warning | fixed | 02-REVIEW-FIX.md (not in the current review) |
| WR-05 | warning | fixed | 02-REVIEW-FIX.md (not in the current review) |
| WR-06 | warning | fixed | 02-REVIEW-FIX.md (not in the current review) |
| IN-04 | info | open | - (not in the current review) |
| IN-05 | info | open | - (not in the current review) |
| CR-02 | critical | open | - (not in the current review) |
| WR-07 | warning | open | - (not in the current review) |
| WR-08 | warning | deferred | before first client (not in the current review) |
| WR-09 | warning | open | - (not in the current review) |
| WR-10 | warning | deferred | before first client (not in the current review) |
| WR-11 | warning | open | - (not in the current review) |
| WR-12 | warning | open | - (not in the current review) |
| WR-13 | warning | open | - (not in the current review) |
| WR-14 | warning | open | - (not in the current review) |
| WR-15 | warning | deferred | before first deploy (same root as T-01-37) (not in the current review) |
| WR-16 | warning | open | - (not in the current review) |
| WR-17 | warning | open | - (not in the current review) |
| WR-18 | warning | open | - (not in the current review) |
| WR-19 | warning | open | - (not in the current review) |
| IN-06 | info | open | - (not in the current review) |
| IN-07 | info | open | - (not in the current review) |
| IN-08 | info | open | - (not in the current review) |
| IN-09 | info | open | - (not in the current review) |
| IN-10 | info | open | - (not in the current review) |
| IN-11 | info | open | - (not in the current review) |
| IN-12 | info | open | - (not in the current review) |
| IN-13 | info | open | - (not in the current review) |
| IN-14 | info | open | - (not in the current review) |
| IN-15 | info | open | - (not in the current review) |
| IN-16 | info | open | - (not in the current review) |
| IN-17 | info | open | - (not in the current review) |

Dispositions: `open` (recorded, not yet triaged), `fixed`, `skipped`, `deferred`.
Set `deferred` by hand and put the reason in the Source cell; both are preserved. A `|` in the reason is kept as prose and escaped on the next run.
Re-running the gate keeps every row it can. A row the current review no longer reports is kept and its Source cell flagged, so a finding does not leave this record silently. ONE exception: when a finding id is REUSED by a different finding, the earlier decision cannot keep a row — the id is taken — and it is dropped. A RECORDED decision (anything but `open`) is named on the console when that happens; a row still at `open` is replaced silently, because `open` records no decision to lose.
