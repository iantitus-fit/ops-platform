---
phase: "09"
slug: "owner-intelligence"
# status lifecycle: draft (seeded by plan-phase) → validated (set by validate-phase §6)
# audit-milestone §5.5 distinguishes NOT-VALIDATED (draft) from PARTIAL (validated + nyquist_compliant: false) (#2117)
status: draft
nyquist_compliant: false
wave_0_complete: false
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
| TBD | TBD | TBD | GATE-05 | — | `final_text` null unless sent or delivered; `status` on every line; failed and queued never read as out | feature | `php artisan test --compact tests/Feature/FrontDesk/GateExportTest.php` | ✅ extend | ⬜ pending |
| TBD | TBD | TBD | GATE-05 | — | `original_text` is `gate.inputs.agent_reply` on every path; null for blank or absent | feature | same | ✅ extend | ⬜ pending |
| TBD | TBD | TBD | GATE-05 | — | six kinds in; holding, handoff, life-safety, booking out; `kind` and the model-words flag on lines | feature | same | ✅ extend | ⬜ pending |
| TBD | TBD | TBD | GATE-05 | — | `answered` draft keeps `resolution`, gains `answered_by_message_id`, null `final_text` | feature | same | ✅ extend | ⬜ pending |
| TBD | TBD | TBD | GATE-05 | — | `--without-text` carries no text in new fields | feature | same | ✅ extend | ⬜ pending |
| TBD | TBD | TBD | BOOK-06 | — | D-06 formatter boundaries and singular forms | unit | `php artisan test --compact tests/Unit/OwnerIntelligence/DurationTextTest.php` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | BOOK-06 | — | D-01 to D-03 start/stop rules; D-07 median and 30-day window in the business zone | feature | `php artisan test --compact tests/Feature/OwnerIntelligence/TimeToBookedTest.php` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | BOOK-06 | — | D-04 query count equal for 3 and 10 contacts, and equal to 1 | feature | same | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | BOOK-06 | — | contact screen line shown, hidden with no number, hidden with room off, hidden and reported to the log when building it throws | feature (Livewire) | `php artisan test --compact tests/Feature/Filament/ContactTimeToBookedTest.php` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | RECS-02 | — | D-13 day: order, states, six reader answers, heading date, zone line, no phone/note/address, empty day, schedule-room-off line | feature | `php artisan test --compact tests/Feature/OwnerIntelligence/TodayRecordTest.php` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | RECS-02 | — | owner text (block note, customer name) with `#`, `\|`, newline or instruction-like sentence stays inside its row or quote | feature | same | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | RECS-02 | — | each of the six actions redraws `today.md` after commit, queued and locked; room-off deletes the files on sync and rebuild | feature | `php artisan test --compact tests/Feature/OwnerIntelligence/RedrawTest.php` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | RECS-02 | — | nightly event runs at 00:10 in the business zone, follows a changed zone, one pass across DST days; `records:rebuild` turns `today.md` over and names what it wrote | feature | `php artisan test --compact tests/Feature/OwnerIntelligence/NightlyRebuildTest.php tests/Feature/Records/RecordsRebuildTest.php` | ❌ W0 / ✅ extend | ⬜ pending |
| TBD | TBD | TBD | RECS-02 | — | router text room on and off; tracked example equals room-on text | feature | `php artisan test --compact tests/Feature/Records/RecordsRebuildTest.php` | ✅ edit :45-51 | ⬜ pending |
| TBD | TBD | TBD | ROOM-13 | — | boot, contract, folder holds only `CONTEXT.md`, optional, section position and lines | feature | `php artisan test --compact tests/Feature/Rooms/OwnerIntelligenceBootTest.php` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | ROOM-13 / A11Y-01 | — | axe finds no violation in the section, with and without a qualifying contact | browser | same file | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | D-26 | — | owner notice reads "the line changed after it was shown" | feature | `php artisan test --compact tests/Feature/Booking/ApprovedDraftVisitLineTest.php` | ✅ edit :270, :285, :289 | ⬜ pending |
| TBD | TBD | TBD | shared code | — | arch scan lists only the six writers; File facade only in `RecordsWriter`; example count 29 | unit/feature | `php artisan test --compact tests/Unit tests/Feature/Icm tests/Feature/Rooms` | ✅ | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Unit/OwnerIntelligence/DurationTextTest.php` — BOOK-06 formatter
- [ ] `tests/Feature/OwnerIntelligence/TimeToBookedTest.php` — BOOK-06, D-04 query count
- [ ] `tests/Feature/OwnerIntelligence/TodayRecordTest.php` — RECS-02, D-13
- [ ] `tests/Feature/OwnerIntelligence/RedrawTest.php` — RECS-02, D-14, D-15
- [ ] `tests/Feature/OwnerIntelligence/NightlyRebuildTest.php` — RECS-02, D-10
- [ ] `tests/Feature/Rooms/OwnerIntelligenceBootTest.php` — ROOM-13, A11Y-01
- [ ] `tests/Feature/Filament/ContactTimeToBookedTest.php` — BOOK-06 on the contact screen
- [ ] Extend `tests/Feature/FrontDesk/GateExportTest.php`, `tests/Feature/Records/RecordsRebuildTest.php`, `tests/Feature/Booking/ApprovedDraftVisitLineTest.php`; `tests/Pest.php:321`

No framework install: Pest is in place. Create tests with `php artisan make:test --pest <Name>` (`--unit` for the formatter).

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Keyboard-only and VoiceOver walk of the contact screen's Time to booked line and the owner intelligence section | ROOM-13, BOOK-06 (D-29) | Automated checks catch only part of WCAG; the contact screen has no axe check of its own (04 D-27 sets it per room section) | Recorded as a `.planning/WINDOWS.md` entry for end-of-phase UAT, never a pause during execution |
| Phone-width check of both lines for sideways scroll | ROOM-13, BOOK-06 (D-29) | Visual layout at a real phone width | Recorded for UAT with the walk above |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 120s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
