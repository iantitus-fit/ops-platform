---
phase: 02
review: 02-REVIEW.md
titles: json
findings:
  - id: CR-01
    severity: critical
    disposition: open
    title: "A claimed burst is lost, with no owner alert, when the front desk run fails or times out [slice A, was CR-A01]"
  - id: CR-02
    severity: critical
    disposition: open
    title: "Business Folder page crashes while a Phase 1 version is live [slice B, was CR-B01]"
  - id: WR-01
    severity: warning
    disposition: open
    title: "A live folder with no SMS agent file drops customer texts silently [slice A, was WR-A01]"
  - id: WR-02
    severity: warning
    disposition: open
    title: "`DispatchInbound` marks a message dispatched before the job is pushed, so a failed push strands it forever [slice A, was WR-A02]"
  - id: WR-03
    severity: warning
    disposition: open
    title: "Owner alerts can be texted twice (non-atomic send claim) [slice A, was WR-A03]"
  - id: WR-04
    severity: warning
    disposition: open
    title: "`RaiseOwnerAlert` de-duplication is check-then-insert with no unique index [slice A, was WR-A04]"
  - id: WR-05
    severity: warning
    disposition: open
    title: "Life-safety job failures and lock timeouts end silently, and the customer then gets a \"holding\" text [slice A, was WR-A05]"
  - id: WR-06
    severity: warning
    disposition: open
    title: "Life-safety keyword lists miss common phrasings (and over-trigger on ordinary repair calls) [slice A, was WR-A06]"
  - id: WR-07
    severity: warning
    disposition: open
    title: "A form that describes a danger gets neither a safety line nor an owner alert [slice A, was WR-A07]"
  - id: WR-08
    severity: warning
    disposition: open
    title: "The owner-alert link scrubber is an allow-list of top-level domains [slice A, was WR-A08]"
  - id: WR-09
    severity: warning
    disposition: open
    title: "`gate:export` reports failed and queued sends as \"what went out\", and `original_text` differs by path [slice A, was WR-A09]"
  - id: WR-10
    severity: warning
    disposition: open
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
    disposition: open
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
  - id: IN-01
    severity: info
    disposition: open
    title: "`HandBackConversation::handle()` accepts `$by` and never uses it [slice A, was IN-A01]"
  - id: IN-02
    severity: info
    disposition: open
    title: "Unreachable branch in `RunFrontDesk` [slice A, was IN-A02]"
  - id: IN-03
    severity: info
    disposition: open
    title: "Test double lives in production code [slice A, was IN-A03]"
  - id: IN-04
    severity: info
    disposition: open
    title: "A burst with a danger text that is not the newest alerts the owner twice [slice A, was IN-A04]"
  - id: IN-05
    severity: info
    disposition: open
    title: "`ProcessInbound::$messageId` is never read [slice A, was IN-A05]"
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
open: 38
total: 38
recorded: 2026-10-12T13:11:24.328236Z
---

# Phase 02: Code Review Disposition

| Finding | Severity | Disposition | Source |
|---------|----------|-------------|--------|
| CR-01 | critical | open | - |
| CR-02 | critical | open | - |
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
| WR-14 | warning | open | - |
| WR-15 | warning | open | - |
| WR-16 | warning | open | - |
| WR-17 | warning | open | - |
| WR-18 | warning | open | - |
| WR-19 | warning | open | - |
| IN-01 | info | open | - |
| IN-02 | info | open | - |
| IN-03 | info | open | - |
| IN-04 | info | open | - |
| IN-05 | info | open | - |
| IN-06 | info | open | - |
| IN-07 | info | open | - |
| IN-08 | info | open | - |
| IN-09 | info | open | - |
| IN-10 | info | open | - |
| IN-11 | info | open | - |
| IN-12 | info | open | - |
| IN-13 | info | open | - |
| IN-14 | info | open | - |
| IN-15 | info | open | - |
| IN-16 | info | open | - |
| IN-17 | info | open | - |

Dispositions: `open` (recorded, not yet triaged), `fixed`, `skipped`, `deferred`.
Set `deferred` by hand and put the reason in the Source cell; both are preserved. A `|` in the reason is kept as prose and escaped on the next run.
Re-running the gate keeps every row it can. A row the current review no longer reports is kept and its Source cell flagged, so a finding does not leave this record silently. ONE exception: when a finding id is REUSED by a different finding, the earlier decision cannot keep a row — the id is taken — and it is dropped. A RECORDED decision (anything but `open`) is named on the console when that happens; a row still at `open` is replaced silently, because `open` records no decision to lose.
