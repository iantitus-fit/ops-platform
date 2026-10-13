---
phase: 02
review: 02-REVIEW.md
titles: json
findings:
  - id: WR-28
    severity: warning
    disposition: fixed
    title: "Widget still counts queued and failed texts as \"the owner spoke last\" [review 4, was WR-01]"
  - id: WR-29
    severity: warning
    disposition: fixed
    title: "The map note and the enum docblock are stale after the open-leads change [review 4, was WR-02]"
  - id: IN-26
    severity: info
    disposition: open
    title: "`waitingSince()` and `openLeadsMarkdown()` docblocks do not say the thread is filtered [review 4, was IN-01]"
  - id: IN-27
    severity: info
    disposition: open
    title: "Map citation points at the method signature, not at the `happenedAt()` calls [review 4, was IN-02]"
  - id: IN-28
    severity: info
    disposition: open
    title: "Test coverage of the two fixes is one-sided [review 4, was IN-03]"
  - id: CR-01
    severity: critical
    disposition: fixed
    title: "A claimed burst is lost, with no owner alert, when the front desk run fails or times out [slice A, was CR-A01]"
  - id: CR-02
    severity: critical
    disposition: fixed
    title: "Business Folder page crashes while a Phase 1 version is live [slice B, was CR-B01]"
  - id: WR-01
    severity: warning
    disposition: fixed
    title: "A live folder with no SMS agent file drops customer texts silently [slice A, was WR-A01]"
  - id: WR-02
    severity: warning
    disposition: fixed
    title: "`DispatchInbound` marks a message dispatched before the job is pushed, so a failed push strands it forever [slice A, was WR-A02]"
  - id: WR-03
    severity: warning
    disposition: deferred
    title: "Owner alerts can be texted twice (non-atomic send claim) [slice A, was WR-A03]"
  - id: WR-04
    severity: warning
    disposition: deferred
    title: "`RaiseOwnerAlert` de-duplication is check-then-insert with no unique index [slice A, was WR-A04]"
  - id: WR-05
    severity: warning
    disposition: fixed
    title: "Life-safety job failures and lock timeouts end silently, and the customer then gets a \"holding\" text [slice A, was WR-A05]"
  - id: WR-06
    severity: warning
    disposition: fixed
    title: "Life-safety keyword lists miss common phrasings (and over-trigger on ordinary repair calls) [slice A, was WR-A06]"
  - id: WR-07
    severity: warning
    disposition: fixed
    title: "A form that describes a danger gets neither a safety line nor an owner alert [slice A, was WR-A07]"
  - id: WR-08
    severity: warning
    disposition: deferred
    title: "The owner-alert link scrubber is an allow-list of top-level domains [slice A, was WR-A08]"
  - id: WR-09
    severity: warning
    disposition: deferred
    title: "`gate:export` reports failed and queued sends as \"what went out\", and `original_text` differs by path [slice A, was WR-A09]"
  - id: WR-10
    severity: warning
    disposition: deferred
    title: "The code-level price and discount veto has easy bypasses [slice A, was WR-A10]"
  - id: WR-11
    severity: warning
    disposition: deferred
    title: "Unauthenticated request with a nested array field turns a 403 into a 500 [slice B, was WR-B01]"
  - id: WR-12
    severity: warning
    disposition: deferred
    title: "Empty `MessageSid` with a `SmsSid` yields an empty provider message id [slice B, was WR-B02]"
  - id: WR-13
    severity: warning
    disposition: deferred
    title: "Sync Now only catches `IcmFolderMissing`; invalid UTF-8 or a DB error becomes a 500 [slice B, was WR-B03]"
  - id: WR-14
    severity: warning
    disposition: deferred
    title: "`ConversationThread::$conversation` is a public, unlocked model property [slice B, was WR-B04]"
  - id: WR-15
    severity: warning
    disposition: deferred
    title: "The unauthenticated fake webhook is on by default in every environment [slice B, was WR-B05]"
  - id: WR-16
    severity: warning
    disposition: fixed
    title: "The test run is not isolated from `.env`; the documented go-live step breaks the suite [slice C, was WR-C01]"
  - id: WR-17
    severity: warning
    disposition: deferred
    title: "The reply-cap \"does not count\" test cannot detect most of what it claims [slice C, was WR-C02]"
  - id: WR-18
    severity: warning
    disposition: deferred
    title: "`toContain('fi-collapsed')` is always true, so \"collapses the customer details section\" is not tested [slice C, was WR-C03]"
  - id: WR-19
    severity: warning
    disposition: deferred
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
  - id: CR-03
    severity: critical
    disposition: fixed
    title: "A retry after the safety line was sent finds it \"already answered\" and skips the pause and the owner alert [review 2, was CR-01]"
  - id: WR-20
    severity: warning
    disposition: fixed
    title: "The keyword net misses common ways of describing the same dangers (verified) [review 2, was WR-01]"
  - id: WR-21
    severity: warning
    disposition: fixed
    title: "A utility phone the sync accepts is silently dropped from the gas sentence (verified) [review 2, was WR-02]"
  - id: WR-22
    severity: warning
    disposition: deferred
    title: "`ProcessInbound::failed()` reports on the newest customer text, not on the burst that failed [review 2, was WR-03]"
  - id: WR-23
    severity: warning
    disposition: fixed
    title: "The claim rollback in `DispatchInbound` does not cover a push deferred by `afterCommit()` [review 2, was WR-04]"
  - id: WR-24
    severity: warning
    disposition: fixed
    title: "Retry and failure semantics of `RespondToLifeSafety` do not match its comments [review 2, was WR-05]"
  - id: WR-25
    severity: warning
    disposition: fixed
    title: "`LifeSafety` fails open when a regex errors (verified) [review 2, was WR-06]"
  - id: IN-18
    severity: info
    disposition: open
    title: "Exception classification by message prefix [review 2, was IN-01]"
  - id: IN-19
    severity: info
    disposition: open
    title: "Raw provider exception text is persisted on every message verdict [review 2, was IN-02]"
  - id: IN-20
    severity: info
    disposition: open
    title: "Owner-alert settings are parsed in two places, and `alertWarning()` has no type guard [review 2, was IN-03]"
  - id: IN-21
    severity: info
    disposition: open
    title: "`RaiseOwnerAlert` check-then-insert is not atomic, and a no-recipient alert can be deferred [review 2, was IN-04]"
  - id: IN-22
    severity: info
    disposition: open
    title: "Test scaffolding leftovers and a narrow queue guard [review 2, was IN-05]"
  - id: WR-26
    severity: warning
    disposition: fixed
    title: "`RecordsWriter` treats unsent messages as part of the thread (open-leads.md reports \"replied\" for a lead nobody has answered) [review 3, was WR-01]"
  - id: WR-27
    severity: warning
    disposition: fixed
    title: "`PausedConversationsWaiting` decides \"who spoke last\" by `created_at`, so a draft the owner sent later still looks unanswered [review 3, was WR-02]"
  - id: IN-23
    severity: info
    disposition: open
    title: "`TriageReading::read` reads four new threshold keys with no guard [review 3, was IN-01]"
  - id: IN-24
    severity: info
    disposition: open
    title: "Two gate dials for `promise` and `complaint` were loosened together on one live reading [review 3, was IN-02]"
  - id: IN-25
    severity: info
    disposition: open
    title: "Small inconsistencies in `ConversationThread` and the Needs you card [review 3, was IN-03]"
open: 28
total: 60
recorded: 2026-10-13T20:55:05.278Z
---

# Phase 02: Code Review Disposition

| Finding | Severity | Disposition | Source |
|---------|----------|-------------|--------|
| WR-28 | warning | fixed | abe9d57 (test f6764c9) |
| WR-29 | warning | fixed | a468e3f |
| IN-26 | info | open | - |
| IN-27 | info | open | - |
| IN-28 | info | open | - |
| CR-01 | critical | fixed | 02-32: 1351b42, 1e2eba2 (tests aebb076, e1bbd60) (not in the current review) |
| CR-02 | critical | fixed | 02-31: d2a6b0d, 1b77c6e (tests d300b42, 2c47766) (not in the current review) |
| WR-01 | warning | fixed | 02-32: d008917 (test 058559c) (not in the current review) |
| WR-02 | warning | fixed | 02-31: e1bfa5a (test 8dfed8e) (not in the current review) |
| WR-03 | warning | deferred | Phase 2.1 (2.1 rebuilds alerts) (not in the current review) |
| WR-04 | warning | deferred | Phase 2.1 (2.1 rebuilds alerts) (not in the current review) |
| WR-05 | warning | fixed | 02-33: 73a43ae (test 780ecb3) (not in the current review) |
| WR-06 | warning | fixed | 02-33: a72f8bd, c7f13f1 (test e229e2d) (not in the current review) |
| WR-07 | warning | fixed | 02-33: 4cd98fc (test 7b2dd82) (not in the current review) |
| WR-08 | warning | deferred | before first client (not in the current review) |
| WR-09 | warning | deferred | Phase 2.1, gate:export feeds threshold tuning (not in the current review) |
| WR-10 | warning | deferred | before first client (not in the current review) |
| WR-11 | warning | deferred | before first deploy (not in the current review) |
| WR-12 | warning | deferred | before Twilio go-live (not in the current review) |
| WR-13 | warning | deferred | before first deploy (not in the current review) |
| WR-14 | warning | deferred | before first deploy (not in the current review) |
| WR-15 | warning | deferred | before first deploy (same root as T-01-37) (not in the current review) |
| WR-16 | warning | fixed | 02-30: cbe36a3, f2eca7a (test 589229e) (not in the current review) |
| WR-17 | warning | deferred | Phase 2.1, test hardening (not in the current review) |
| WR-18 | warning | deferred | Phase 2.1, test hardening (not in the current review) |
| WR-19 | warning | deferred | Phase 2.1, test hardening (not in the current review) |
| IN-01 | info | open | - (not in the current review) |
| IN-02 | info | open | - (not in the current review) |
| IN-03 | info | open | - (not in the current review) |
| IN-04 | info | open | - (not in the current review) |
| IN-05 | info | open | - (not in the current review) |
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
| CR-03 | critical | fixed | 2741862, 6643e49 (review 2 fix report, c3ec1cf) (not in the current review) |
| WR-20 | warning | fixed | d3f1fd3 (review 2 fix report, c3ec1cf) (not in the current review) |
| WR-21 | warning | fixed | 2a33e64 (review 2 fix report, c3ec1cf) (not in the current review) |
| WR-22 | warning | deferred | partly fixed (bfc52cf); remaining duplicate-alert path deferred to Phase 02.1 with the owner-alert de-dup work; preferred fix is a claim token on the claimed rows, not a time window (not in the current review) |
| WR-23 | warning | fixed | 8a36b93 (review 2 fix report, c3ec1cf) (not in the current review) |
| WR-24 | warning | fixed | 9d97dd7 (review 2 fix report, c3ec1cf) (not in the current review) |
| WR-25 | warning | fixed | 3cb84f4 (review 2 fix report, c3ec1cf) (not in the current review) |
| IN-18 | info | open | - (not in the current review) |
| IN-19 | info | open | - (not in the current review) |
| IN-20 | info | open | - (not in the current review) |
| IN-21 | info | open | - (not in the current review) |
| IN-22 | info | open | - (not in the current review) |
| WR-26 | warning | fixed | 02-REVIEW-FIX.md (not in the current review) |
| WR-27 | warning | fixed | 02-REVIEW-FIX.md (not in the current review) |
| IN-23 | info | open | - (not in the current review) |
| IN-24 | info | open | - (not in the current review) |
| IN-25 | info | open | - (not in the current review) |

Dispositions: `open` (recorded, not yet triaged), `fixed`, `skipped`, `deferred`.
Set `deferred` by hand and put the reason in the Source cell; both are preserved. A `|` in the reason is kept as prose and escaped on the next run.
Re-running the gate keeps every row it can. A row the current review no longer reports is kept and its Source cell flagged, so a finding does not leave this record silently. ONE exception: when a finding id is REUSED by a different finding, the earlier decision cannot keep a row — the id is taken — and it is dropped. A RECORDED decision (anything but `open`) is named on the console when that happens; a row still at `open` is replaced silently, because `open` records no decision to lose.
