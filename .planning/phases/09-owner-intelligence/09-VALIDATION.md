---
phase: "09"
slug: "owner-intelligence"
# status lifecycle: draft (seeded by plan-phase) → validated (set by validate-phase §6)
# audit-milestone §5.5 distinguishes NOT-VALIDATED (draft) from PARTIAL (validated + nyquist_compliant: false) (#2117)
status: validated
nyquist_compliant: true
wave_0_complete: true
created: "2026-11-14"
---

# Phase 09 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.
> Seeded from `09-RESEARCH.md` § Validation Architecture.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest 5.3.1 on PHPUnit; `tests/Pest.php` binds `TestCase` + `RefreshDatabase` to `Feature`; `Unit` is plain |
| **Config file** | `phpunit.xml`, `tests/Pest.php` |
| **Quick run command** | `php artisan test --compact tests/Unit/OwnerIntelligence tests/Feature/OwnerIntelligence tests/Feature/Records tests/Feature/FrontDesk/GateExportTest.php` |
| **Full suite command** | `php artisan test --compact` |
| **Estimated runtime** | ~60 seconds quick run; full suite several minutes (baseline: `GateExportTest` + `tests/Feature/Records` = 56 tests, 269 assertions) |

---

## Sampling Rate

- **After every task commit:** Run the single test file for that task, `php artisan test --compact <file>` (Phase 8's proven verify form)
- **After every plan wave:** Run the quick run command plus `tests/Feature/Booking tests/Feature/Rooms tests/Feature/Filament tests/Unit`
- **Before `/gsd-verify-work`:** Full suite must be green (SQLite locally; the Postgres leg runs in CI after Ian pushes)
- **Max feedback latency:** 120 seconds

---

## Per-Task Verification Map

Task IDs are filled in by the planner; the requirement-to-test map below is the contract each task's `<automated>` command draws from.

| Task ID | Plan | Wave | Requirement | Threat Ref | Secure Behavior | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|------------|-----------------|-----------|-------------------|-------------|--------|
| 09-01-T1 | 09-01 | 1 | GATE-05 | T-09-01 | `final_text` null unless sent or delivered; `status` on every line; failed and queued never read as out | feature | `php artisan test --compact tests/Feature/FrontDesk/GateExportTest.php` | ✅ extend | ✅ green |
| 09-01-T2 | 09-01 | 1 | GATE-05 | T-09-02 | `original_text` is `gate.inputs.agent_reply` on every path; null for blank or absent | feature | same | ✅ extend | ✅ green |
| 09-01-T2 | 09-01 | 1 | GATE-05 | — | six kinds in; holding, handoff, life-safety, booking out; `kind` and the model-words flag on lines | feature | same | ✅ extend | ✅ green |
| 09-01-T2 | 09-01 | 1 | GATE-05 | — | `answered` draft keeps `resolution`, gains `answered_by_message_id`, null `final_text` | feature | same | ✅ extend | ✅ green |
| 09-01-T2 | 09-01 | 1 | GATE-05 | T-09-03 | `--without-text` carries no text in new fields | feature | same | ✅ extend | ✅ green |
| 09-02-T1 | 09-02 | 1 | BOOK-06 | — | D-06 formatter boundaries and singular forms | unit | `php artisan test --compact tests/Unit/OwnerIntelligence/DurationTextTest.php` | ✅ | ✅ green |
| 09-02-T2, 09-02-T3 | 09-02 | 1 | BOOK-06 | T-09-06 | D-01 to D-03 start/stop rules; D-07 median and 30-day window in the business zone | feature | `php artisan test --compact tests/Feature/OwnerIntelligence/TimeToBookedTest.php` | ✅ | ✅ green |
| 09-02-T3 | 09-02 | 1 | BOOK-06 | T-09-05 | D-04 query count equal for 3 and 10 contacts, and equal to 1 | feature | same | ✅ | ✅ green |
| 09-03-T2 | 09-03 | 2 | BOOK-06 | T-09-08 | contact screen line shown, hidden with no number, hidden with room off, hidden and reported to the log when building it throws | feature (Livewire) | `php artisan test --compact tests/Feature/Filament/ContactTimeToBookedTest.php` | ✅ | ✅ green |
| 09-04-T1, 09-04-T2 | 09-04 | 3 | RECS-02 | T-09-12 | D-13 day: order, states, six reader answers, heading date, zone line, no phone/note/address, empty day, schedule-room-off line | feature | `php artisan test --compact tests/Feature/OwnerIntelligence/TodayRecordTest.php` | ✅ | ✅ green |
| 09-04-T2 | 09-04 | 3 | RECS-02 | T-09-11 | owner text (block note, customer name) with `#`, `\|`, newline or instruction-like sentence stays inside its row or quote | feature | same | ✅ | ✅ green |
| 09-04-T2 | 09-04 | 3 | RECS-02, BOOK-06 | T-09-13, T-09-14 | time-to-booked.md content; room off deletes both files; the lock is taken before any read | feature | `php artisan test --compact tests/Feature/OwnerIntelligence/TimeToBookedFileTest.php` | ✅ | ✅ green |
| 09-05-T1, 09-05-T2, 09-05-T3 | 09-05 | 4 | RECS-02 | T-09-16, T-09-18 | each of the six actions redraws `today.md` after commit, queued and locked; no-ops and rollbacks do not; room-off deletes the files at the sync | feature | `php artisan test --compact tests/Feature/OwnerIntelligence/RedrawTest.php` | ✅ | ✅ green |
| 09-06-T1, 09-06-T2 | 09-06 | 5 | RECS-02 | T-09-20, T-09-21, T-09-22 | nightly event runs from 00:10 in the business zone (once per date since WR-03, row below), follows a changed zone, one pass across DST days; `records:rebuild` turns `today.md` over | feature | `php artisan test --compact tests/Feature/OwnerIntelligence/NightlyRebuildTest.php tests/Feature/Records/RecordsRebuildTest.php` | ✅ | ✅ green |
| 09-04-T3 | 09-04 | 3 | RECS-02 | T-09-13 | router text room on and off; tracked example equals room-on text; `records:rebuild` names what it wrote | feature | `php artisan test --compact tests/Feature/Records/RecordsRebuildTest.php` | ✅ | ✅ green |
| 09-03-T1, 09-03-T3 | 09-03 | 2 | ROOM-13 | T-09-10 | boot, contract, folder holds only `CONTEXT.md`, optional, section position and lines | feature | `php artisan test --compact tests/Feature/Rooms/OwnerIntelligenceBootTest.php` | ✅ | ✅ green |
| 09-03-T3 | 09-03 | 2 | ROOM-13 / A11Y-01 | T-09-08 | axe finds no violation in the section, with and without a qualifying contact | browser | same file | ✅ | ✅ green |
| 09-01-T3 | 09-01 | 1 | D-26 | T-09-04 | owner notice reads "the line changed after it was shown" | feature | `php artisan test --compact tests/Feature/Booking/ApprovedDraftVisitLineTest.php` | ✅ | ✅ green |
| 09-03-T1, 09-04-T1, 09-05-T1 | 09-03, 09-04, 09-05 | 2-4 | shared code | T-09-19 | arch scan lists only the six writers; File facade only in `RecordsWriter`; example count 29 | unit/feature | `php artisan test --compact tests/Unit tests/Feature/Icm tests/Feature/Rooms` | ✅ | ✅ green |
| WR-01 (review) | 09-04, 09-05 | — | RECS-02 | T-09-13 | a sync that turns the room off or on rewrites the router in the same lock that writes or deletes both files | feature | `php artisan test --compact tests/Feature/OwnerIntelligence/RedrawTest.php` | ✅ extend | ✅ green |
| WR-02 (review) | 09-02 | — | BOOK-06 | T-09-06 | `RecordInbound` records the keyword decision as `meta.keyword`; `TimeToBooked` trusts a present key and reconstructs only for rows without it | feature | `php artisan test --compact tests/Feature/Inbound/OptOutTest.php tests/Feature/OwnerIntelligence/TimeToBookedTest.php` | ✅ extend | ✅ green |
| WR-03 (review) | 09-06 | — | RECS-02 | T-09-20 | rebuild runs once per business-local date on any tick from 00:10 to before 01:00; a failed run is retried in the window; the 30-minute lock lets a hard-killed run be retried | feature | `php artisan test --compact tests/Feature/OwnerIntelligence/NightlyRebuildTest.php` | ✅ extend | ✅ green |
| IN-02 (review) | 09-03, 09-04 | — | RECS-02, ROOM-13 | — | the room's CONTEXT.md `today.md` sample equals what `TodayRecord` writes for the D-13 day | feature | `php artisan test --compact tests/Feature/OwnerIntelligence/TodayRecordTest.php` | ✅ extend | ✅ green |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [x] `tests/Unit/OwnerIntelligence/DurationTextTest.php` — BOOK-06 formatter
- [x] `tests/Feature/OwnerIntelligence/TimeToBookedTest.php` — BOOK-06, D-04 query count
- [x] `tests/Feature/OwnerIntelligence/TodayRecordTest.php` — RECS-02, D-13
- [x] `tests/Feature/OwnerIntelligence/TimeToBookedFileTest.php` — RECS-02, D-15, D-23 (added at planning)
- [x] `tests/Feature/OwnerIntelligence/RedrawTest.php` — RECS-02, D-14, D-15
- [x] `tests/Feature/OwnerIntelligence/NightlyRebuildTest.php` — RECS-02, D-10
- [x] `tests/Feature/Rooms/OwnerIntelligenceBootTest.php` — ROOM-13, A11Y-01
- [x] `tests/Feature/Filament/ContactTimeToBookedTest.php` — BOOK-06 on the contact screen
- [x] Extend `tests/Feature/FrontDesk/GateExportTest.php`, `tests/Feature/Records/RecordsRebuildTest.php`, `tests/Feature/Booking/ApprovedDraftVisitLineTest.php`; `tests/Pest.php:321`

No framework install: Pest is in place. Create tests with `php artisan make:test --pest <Name>` (`--unit` for the formatter).

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Keyboard-only and VoiceOver walk of the contact screen's Time to booked line and the owner intelligence section | ROOM-13, BOOK-06 (D-29) | Automated checks catch only part of WCAG; the contact screen has no axe check of its own (04 D-27 sets it per room section) | Recorded as a `.planning/WINDOWS.md` entry for end-of-phase UAT, never a pause during execution |
| Phone-width check of both lines for sideways scroll | ROOM-13, BOOK-06 (D-29) | Visual layout at a real phone width | Recorded for UAT with the walk above |

---

## Validation Sign-Off

- [x] All tasks have `<automated>` verify or Wave 0 dependencies
- [x] Sampling continuity: no 3 consecutive tasks without automated verify
- [x] Wave 0 covers all MISSING references
- [x] No watch-mode flags
- [x] Feedback latency < 120s
- [x] `nyquist_compliant: true` set in frontmatter

**Approval:** validated 2026-11-17 by /gsd-validate-phase 09 after 09-06: every mapped test file exists and is green (166 tests, 3748 assertions; shared-code scans 1190 green; full suite 3333 green on SQLite). The two manual-only checks stay for end-of-phase UAT.

Re-validated 2026-11-18 by /gsd-validate-phase 09 after the review fixes (WR-01 to WR-04; IN-01, IN-02, IN-04, IN-06, IN-07): each tested fix (WR-01, WR-02, WR-03, IN-02) has its own row above, all in existing test files; WR-04, IN-01, IN-04, IN-06 and IN-07 changed wording or docblocks only. Every mapped test file is green (245 tests, 4135 assertions, `tests/Feature/Inbound/OptOutTest.php` included; shared-code scans 1190 green). `nyquist_compliant` corrected to `true`: the 2026-11-17 run ticked it above but left the frontmatter at `false`.

## Validation Audit 2026-11-17

| Metric | Count |
|---|---|
| Gaps found | 0 |
| Resolved | 0 |
| Escalated | 0 |
