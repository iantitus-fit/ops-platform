---
phase: "6"
slug: "front-desk-and-team-rooms"
# status lifecycle: draft (seeded by plan-phase) → validated (set by validate-phase §6)
# audit-milestone §5.5 distinguishes NOT-VALIDATED (draft) from PARTIAL (validated + nyquist_compliant: false) (#2117)
status: validated
nyquist_compliant: true
wave_0_complete: true
created: "2026-10-27"
---

# Phase 6 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest 5.3.1 on PHPUnit, Laravel `RefreshDatabase` |
| **Config file** | `phpunit.xml` (QUEUE_CONNECTION=sync, SQLite `:memory:`); `tests/Pest.php` binds helpers to `tests/Feature` only |
| **Quick run command** | `php artisan test --compact tests/Feature/Rooms tests/Unit` |
| **Full suite command** | `php artisan test --compact` |
| **Estimated runtime** | ~22 seconds quick, ~150 seconds full (SQLite); Postgres about 6 times slower |

---

## Sampling Rate

- **After every task commit:** Run the narrowest test file for that task, on SQLite
- **After every plan wave:** Run `php artisan test --compact tests/Feature/Rooms tests/Unit tests/Feature/Alerts tests/Feature/FrontDesk`
- **Before `/gsd-verify-work`:** Full suite green on SQLite and on `pgsql` (CI matrix)
- **Max feedback latency:** 150 seconds

---

## Per-Task Verification Map

Filled from the plans as they land. Requirement-level map (from 06-RESEARCH.md):

| Requirement | Behavior | Test Type | Automated Command | File Exists | Status |
|-------------|----------|-----------|-------------------|-------------|--------|
| ROOM-06 | D-14 prep: line-185 hand-built registry case; three feature tests removed | feature | `php artisan test --compact tests/Feature/Rooms/RoomOwnershipTest.php tests/Unit/Icm/RoomChecksTest.php` | ✅ | ✅ green |
| ROOM-06 | Front desk room boots empty and with its example folder; contract, slot, never-left-out line | feature | `php artisan test --compact tests/Feature/Rooms/FrontDeskBootTest.php` | ✅ | ✅ green |
| ROOM-15 | Team room boots; owns none, reads three paths, tables `owner_alerts`; `DraftsWaiting` still on `/admin` | feature | `php artisan test --compact tests/Feature/Rooms/TeamBootTest.php` | ✅ | ✅ green |
| ROOM-06/15 | Existing suite unchanged on both databases | full suite | `php artisan test --compact` | ✅ | ✅ green |
| ALRT-01 | Stale copies and a re-entrant worker text once; stale claim re-sent after 2 minutes; ended alerts stay ended | feature | `php artisan test --compact tests/Feature/Alerts` | ✅ | ✅ green |
| ALRT-01 | Duplicate `dedupe_key` returns the existing row and sends nothing; NULL keys repeat; migration backfill tolerates duplicates | feature | `php artisan test --compact tests/Feature/Alerts` | ✅ | ✅ green |
| ALRT-01 (D-09) | Only `SendOwnerAlert` texts an owner alert | arch | `php artisan test --compact tests/Unit/ArchitectureTest.php` | ✅ | ✅ green |
| ALRT-02 | Claim token written by the claim; `failed()` reports only this run's rows; later text and STOP text not blamed | feature | `php artisan test --compact tests/Feature/FrontDesk/FrontDeskFailureTest.php` | ✅ | ✅ green |
| ALRT-02 (D-12(4)) | `unserialize(serialize($job))->failed()` reports the right burst | feature | same | ✅ | ✅ green |
| ALRT-02 (D-11) | Catch path then `failed()` (and reverse) write one note and one alert | feature | same | ✅ (FailureReportOnceTest.php) | ✅ green |

Per task (from the plans, 2026-10-27):

| Task | Requirement | Automated command (SQLite) | New test file | Status |
|------|-------------|----------------------------|---------------|--------|
| 06-01 T1 | ROOM-06 (D-14) | `php artisan test --compact tests/Feature/Rooms/HandBuiltRegistrySyncTest.php tests/Unit/Icm/RoomChecksTest.php` | HandBuiltRegistrySyncTest.php | ✅ green |
| 06-01 T2 | ROOM-06 (D-14) | `php artisan test --compact tests/Feature/Rooms tests/Unit`; `php artisan test --compact` | none (three removed) | ✅ green |
| 06-02 T1 | ROOM-06 | `php artisan test --compact tests/Feature/Rooms/FrontDeskBootTest.php tests/Unit/ArchitectureTest.php tests/Feature/Rooms/SpecTablesTest.php tests/Feature/Rooms/NeverLeftOutTest.php tests/Unit/Icm/NeverLeftOutTest.php`; `php artisan test --compact` | FrontDeskBootTest.php | ✅ green |
| 06-02 T2 | ROOM-06 (D-18) | `php artisan test --compact tests/Unit/Rooms tests/Feature/Rooms/FrontDeskBootTest.php tests/Feature/Rooms/RoomSectionTest.php tests/Feature/Rooms/PretendRoomBootTest.php`; `php artisan test --compact` | RoomClaimedWidgetsTest.php | ✅ green |
| 06-03 T1 | ROOM-15 | `php artisan test --compact tests/Feature/Rooms/TeamBootTest.php tests/Feature/Rooms/FrontDeskBootTest.php tests/Unit/Rooms tests/Unit/ArchitectureTest.php`; `php artisan test --compact` | TeamBootTest.php | ✅ green |
| 06-03 T2 | ROOM-15 | `php artisan test --compact tests/Feature/Rooms/TeamBootTest.php tests/Feature/Rooms/NeverLeftOutTest.php tests/Unit/Icm/NeverLeftOutTest.php tests/Feature/Rooms/SpecTablesTest.php`; `php artisan test --compact` | (TeamBootTest.php) | ✅ green |
| 06-04 T1 | ALRT-01 | `php artisan test --compact tests/Feature/Alerts/AlertSentOnceTest.php tests/Feature/Alerts/OwnerAlertTest.php tests/Feature/Alerts/BusinessAlertTest.php` | AlertSentOnceTest.php | ✅ green |
| 06-04 T2 | ALRT-01 (D-09) | `php artisan test --compact tests/Feature/Alerts tests/Unit/ArchitectureTest.php tests/Feature/SendRule`; `php artisan test --compact` | (AlertSentOnceTest.php) | ✅ green |
| 06-05 T1 | ALRT-01 | `php artisan test --compact tests/Feature/Alerts/AlertDedupeKeyTest.php tests/Feature/Alerts/OwnerAlertTest.php tests/Feature/Alerts/BusinessAlertTest.php tests/Feature/Alerts/AlertSentOnceTest.php` | AlertDedupeKeyTest.php | ✅ green |
| 06-05 T2 | ALRT-01 | `php artisan test --compact tests/Feature/Alerts tests/Feature/SendRule tests/Feature/FrontDesk/FrontDeskFailureTest.php`; `php artisan test --compact` | (AlertDedupeKeyTest.php) | ✅ green |
| 06-06 T1 | ALRT-02 (D-12) | `php artisan test --compact tests/Feature/FrontDesk/ClaimTokenTest.php tests/Feature/FrontDesk/ProcessInboundTest.php tests/Feature/Inbound tests/Feature/FrontDesk/FrontDeskFailureTest.php` | ClaimTokenTest.php | ✅ green |
| 06-06 T2 | ALRT-02 (D-12 (4), D-20) | `php artisan test --compact tests/Feature/FrontDesk/ClaimTokenTest.php tests/Feature/FrontDesk/FrontDeskFailureTest.php tests/Feature/Inbound tests/Feature/Alerts`; `php artisan test --compact` | (ClaimTokenTest.php) | ✅ green |
| 06-07 T1 | ALRT-02 (D-11) | `php artisan test --compact tests/Feature/FrontDesk/FailureReportOnceTest.php tests/Feature/FrontDesk/FrontDeskFailureTest.php tests/Feature/FrontDesk/ClaimTokenTest.php` | FailureReportOnceTest.php | ✅ green |
| 06-07 T2 | ALRT-02 (D-20) | `php artisan test --compact tests/Feature/FrontDesk tests/Feature/Inbound tests/Feature/Alerts`; `php artisan test --compact` | (FailureReportOnceTest.php) | ✅ green |

Every task has an automated command; no three tasks in a row lack one. The Postgres leg of each plan is a carried hand check (CI pgsql), recorded as an unrun-verify entry, never a pause. D-19 replaces the research's "additive arch rule": ArchitectureTest is run unchanged as D-09's proof.

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [x] `tests/Feature/Rooms/FrontDeskBootTest.php` and `tests/Feature/Rooms/TeamBootTest.php`, modeled on `MarketingBootTest.php`
- [x] The line-185 hand-built registry case (under `tests/Feature/`)
- [x] New alert race and dedupe tests, the serialize-then-`failed()` test, the additive arch rule
- [x] Additive `RoomRegistryTest` cases for the widget-claim field (if D-03 uses a `Room` field)
- [x] `OwnerAlertFactory` states (claimed, stale claim) so tests don't hand-set columns

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Full suite on Postgres | ROOM-06, ROOM-15, ALRT-01, ALRT-02 | Local runs use SQLite; Postgres runs in the CI matrix | Push is Ian's; confirm the CI `pgsql` job is green before UAT |
| Two real OS processes racing one alert | ALRT-01 | Tests simulate the race with stale copies, a re-entrant send and a duplicate key (research A3) | Carried to UAT if Ian wants it |

---

## Validation Sign-Off

- [x] All tasks have `<automated>` verify or Wave 0 dependencies
- [x] Sampling continuity: no 3 consecutive tasks without automated verify
- [x] Wave 0 covers all MISSING references
- [x] No watch-mode flags
- [x] Feedback latency < 150s
- [x] `nyquist_compliant: true` set in frontmatter

**Approval:** approved 2026-10-28 (validate-phase audit after 06-07: 1728 phase-area tests and the 2267-test full suite green on SQLite; Postgres leg manual-only, WINDOWS.md entries 30-33)

## Validation Audit 2026-10-28

| Metric | Count |
|---|---|
| Gaps found | 0 |
| Resolved | 0 |
| Escalated | 0 |
