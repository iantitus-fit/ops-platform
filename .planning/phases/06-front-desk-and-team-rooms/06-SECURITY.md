---
phase: "6"
slug: "front-desk-and-team-rooms"
status: verified
# threats_open = count of OPEN threats at or above workflow.security_block_on severity (the blocking gate)
threats_open: 0
asvs_level: 1
created: "2026-10-28"
---

# Phase 6 — Security

> Per-phase security contract: threat register, accepted risks, and audit trail.

---

## Trust Boundaries

| Boundary | Description | Data Crossing |
|----------|-------------|---------------|
| owner-written folder to the sync | The sync refuses a folder whose files have no room, and keeps the last good version live | Business configuration (ICM files) |
| owner-written contract to the sync | The front desk contract's owns list decides which files the room claims; the team contract's reads and the room folder's presence decide whether the version goes live | Room contracts (`rooms/front_desk/CONTEXT.md`, `rooms/team/CONTEXT.md`) |
| room descriptor to the outbound path | The slot names the interface every customer text goes through | Interface name only; no channel code imported |
| app to the text carrier | A text that leaves cannot be called back | Owner alert texts |
| queue workers and the scheduler to owner_alerts | Several processes may hold copies of one alert row at once | Alert rows and their send claim |
| concurrent raises to owner_alerts | Two workers may pass alreadyRaised() before either inserts | Alert rows, dedupe_key |
| migration to stored rows | The backfill rewrites a column on every existing alert | dedupe_key on stored alerts |
| queued payload to the worker | The job is serialized into the jobs table and rebuilt by the worker and by failed() | Message id, conversation id, claim token |
| the run's catch, the queue's failed() and RunFrontDesk | Up to three code paths may report one run's failure, possibly at once | Failure note and front_desk_failed alert |
| customer messages to the owner's alert | A failure report names one customer message to the owner | Fixed words only; no exception text |

---

## Threat Register

| Threat ID | Category | Component | Severity | Disposition | Mitigation | Status |
|-----------|----------|-----------|----------|-------------|------------|--------|
| T-6-01 | Tampering | removal of three RoomOwnershipTest cases (06-01) | low | mitigate | `tests/Feature/Rooms/HandBuiltRegistrySyncTest.php` and the `tests/Unit/Icm/RoomChecksTest.php` cases keep each branch tested with a hand-built registry | closed |
| T-6-02 | Tampering | `rooms/front_desk/CONTEXT.md` owns (06-02) | low | mitigate | `FrontDeskBootTest.php:56` pins owns to SPEC's rows plus `agents/*.md`; `:138` pins the never-left-out refusal | closed |
| T-6-03 | Elevation of privilege | the text slot naming `App\Channels\TextChannel` (06-02) | medium | mitigate | `FrontDeskServiceProvider.php:31` names the interface as a string, no `App\Channels` import; `ArchitectureTest.php:59` rule on TextChannel users unchanged | closed |
| T-6-04 | Information disclosure | claimed dashboard widgets (06-02) | low | mitigate | `RoomRegistry.php:342-348` refuses a claim of a missing class or a non-Widget, and `:332-338` a widget claimed twice or both added and claimed (WR-04); widgets stay on the authenticated `/admin` panel | closed |
| T-6-05 | Tampering | `rooms/team/CONTEXT.md` reads (06-03) | low | mitigate | `TeamBootTest.php:48` pins reads to the three paths; who resolved each draft is body prose (`:54`) | closed |
| T-6-06 | Denial of service | a business folder without the team room (06-03) | medium | mitigate | `TeamBootTest.php:80` pins the never-left-out refusal for the team room; the last good version stays live | closed |
| T-6-07 | Tampering | `SendOwnerAlert::handle()` racing copies (06-04) | high | mitigate | `SendOwnerAlert.php:107-112` one conditional UPDATE claims the row before the send; `tests/Feature/Alerts/AlertSentOnceTest.php` | closed |
| T-6-08 | Denial of service | a claim left by a dead worker hiding an alert (06-04) | high | mitigate | `SendOwnerAlert::CLAIM_WAIT_MINUTES = 2`; `SendDueAlerts.php:45-46` re-sends a stale claim; 119/120-second boundary test | closed |
| T-6-09 | Tampering | contradictory outcomes (sent and skipped) (06-04) | medium | mitigate | Outcome writes require `sent_at`, `skipped_at` and `failed_at` all null (`SendOwnerAlert.php:107-109`) | closed |
| T-6-10 | Tampering | `RaiseOwnerAlert` check-then-insert (06-05) | medium | mitigate | Unique index on `dedupe_key` (migration 000004) plus `createOrFirst` (`RaiseOwnerAlert.php:65`); `AlertDedupeKeyTest.php` | closed |
| T-6-11 | Denial of service | the key silencing a business-wide alert (06-05) | high | mitigate | `forBusiness()` uses plain `create()` with no key (`RaiseOwnerAlert.php:100-110`); `AlertDedupeKeyTest.php:161-162` stores and sends two safety_check_down alerts | closed |
| T-6-12 | Denial of service | the unique index failing on stored duplicates (06-05) | low | mitigate | Backfill migration 000003 keeps the key on the lowest id of each group and leaves the rest NULL; tested on duplicate rows | closed |
| T-6-13 | Repudiation | `ProcessInbound::failed()` blaming the wrong text (06-06) | medium | mitigate | `ProcessInbound.php:181` selects rows by `claim_token`; later-text, STOP-text and two-run tests in `ClaimTokenTest.php` | closed |
| T-6-14 | Information disclosure | the failure note and alert (06-06) | medium | mitigate | Fixed words only; `ClaimTokenTest.php:126` and `FailureReportOnceTest.php:164-165,217` assert the exception text never reaches note or alert | closed |
| T-6-15 | Tampering | a token lost between handle() and failed() in production (06-06) | medium | mitigate | `ProcessInbound.php:59-66` token is a constructor property in the serialized payload; `unserialize(serialize($job))->failed()` tests | closed |
| T-6-16 | Denial of service | a failed run left unreported because the report broke (06-07) | high | mitigate | `ReportFrontDeskFailure.php:79-82` gives the claim back and rethrows; a give-back that throws is reported, never thrown, so the error that broke the report escapes (`:91-98`, WR-03); release-on-failure and release-that-throws tests in `FailureReportOnceTest.php` | closed |
| T-6-17 | Tampering | two notes or alerts from racing paths (06-07) | medium | mitigate | `ReportFrontDeskFailure.php:118` one conditional UPDATE on `failure_reported_at` of the token's report row, a column `Message` no longer mass-assigns (IN-01); catch-first, failed()-first, RunFrontDesk-first and mixed-burst tests | closed |
| T-6-18 | Denial of service | a failed web-form-only burst never reported (06-07) | medium | mitigate | The try covers the dangerous-form alert and the catch reports `subjectOf($burst)` (`ProcessInbound.php:110-122`); form-only catch test | closed |
| T-6-SC | Tampering | npm/pip/cargo installs (all plans) | high | accept | No plan installs a package; `composer.json`, `composer.lock`, `package.json` and `package-lock.json` unchanged across the phase (79588a6..HEAD) | closed |

*Status: open · closed · open — below high threshold (non-blocking)*
*Severity: critical > high > medium > low — only open threats at or above workflow.security_block_on count toward threats_open*
*Disposition: mitigate (implementation required) · accept (documented risk) · transfer (third-party)*

---

## Accepted Risks Log

| Risk ID | Threat Ref | Rationale | Accepted By | Date |
|---------|------------|-----------|-------------|------|
| AR-6-01 | T-6-SC | The phase installs no package, so there is no supply-chain surface to mitigate; accepted in every plan's threat model | Plan-time threat model (06-01 to 06-07) | 2026-10-27 |

*Accepted risks do not resurface in future audit runs.*

---

## Security Audit Trail

| Audit Date | Threats Total | Closed | Open | Run By |
|------------|---------------|--------|------|--------|
| 2026-10-28 | 19 | 19 | 0 | secure-phase (orchestrator, L1 grep depth; register authored at plan time) |
| 2026-10-28 | 19 | 19 | 0 | secure-phase re-audit after the review fixes WR-03, WR-04 and IN-01 (orchestrator, L1 grep depth); evidence lines refreshed for T-6-04, T-6-16 and T-6-17 |

The Postgres leg of the race mitigations (T-6-07, T-6-10, T-6-17) is carried to UAT as `.planning/WINDOWS.md` entries 30-33, not a security gap.

---

## Sign-Off

- [x] All threats have a disposition (mitigate / accept / transfer)
- [x] Accepted risks documented in Accepted Risks Log
- [x] `threats_open: 0` confirmed
- [x] `status: verified` set in frontmatter

**Approval:** verified 2026-10-28

## Security Audit 2026-10-28

| Metric | Count |
|---|---|
| Threats found | 19 |
| Closed | 19 |
| Open | 0 |
