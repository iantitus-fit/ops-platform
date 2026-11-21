---
phase: "10"
slug: "walk-tests-and-the-gate"
# status lifecycle: draft (seeded by plan-phase) → validated (set by validate-phase §6)
# audit-milestone §5.5 distinguishes NOT-VALIDATED (draft) from PARTIAL (validated + nyquist_compliant: false) (#2117)
status: draft
nyquist_compliant: false
wave_0_complete: false
created: "2026-11-21"
---

# Phase 10 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution. Source: `10-RESEARCH.md` § Validation Architecture.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest 5.3.1 on PHPUnit (`phpunit.xml`: sqlite `:memory:`, `QUEUE_CONNECTION=sync`, `CACHE_STORE=array`); Pest browser plugin with Playwright Chromium for the browser test |
| **Config file** | `phpunit.xml`; global setup `tests/Pest.php` (`->in('Feature')` beforeEach at :37-89) |
| **Quick run command** | `php artisan test --compact <path>` (the narrowest file the task touches) |
| **Full suite command** | `php artisan test --compact` |
| **Estimated runtime** | ~330 seconds locally (3347 tests at baseline); CI runs sqlite and pgsql legs |

---

## Sampling Rate

- **After every task commit:** the task's own test file(s), plus `php artisan test --compact tests/Unit/ArchitectureTest.php tests/Unit/AppointmentWritesTest.php` when a new class lands
- **After every plan wave:** `php artisan test --compact tests/Feature/Icm tests/Feature/Walks tests/Feature/Rooms tests/Feature/FrontDesk/ReplyCapTest.php tests/Unit` and `python3 ~/Projects/cartographer/tools/check_map.py ~/Projects/ops-platform --extra-root examples/example-hvac`
- **Before `/gsd-verify-work`:** full suite green on sqlite locally and on both CI legs
- **Max feedback latency:** 60 seconds for a task-level run

No task runs `walks:record` against a live provider, the live model or Jev. Every recording in tests is made with `FrontDeskAgent::fake()` and `FakeClassifier`.

---

## Per-Task Verification Map

Task IDs are filled by the planner; each row below is a requirement or decision that at least one task's `<automated>` verify must cover.

| Task ID | Plan | Wave | Requirement | Threat Ref | Secure Behavior | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|------------|-----------------|-----------|-------------------|-------------|--------|
| TBD | TBD | TBD | D-20 | — | booking_text does not count toward the reply cap; holding replies still count; life-safety still excluded; a paused conversation still refuses a booking text (D-21) | feature | `php artisan test --compact tests/Feature/FrontDesk/ReplyCapTest.php` | ✅ (add after :167) | ⬜ pending |
| TBD | TBD | TBD | WALK-01 | — | a valid walk file is typed, validated, compiled (two files both in `index.walk_test`); each broken field refused in plain words naming file and field; D-10 time without start refused | feature | `php artisan test --compact tests/Feature/Icm/WalkFileTest.php` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | WALK-01 | — | walk tests stay house-owned; T2/T3 fixtures edited only | feature/unit | `php artisan test --compact tests/Unit/Rooms/RoomRegistryTest.php tests/Feature/Rooms/RouterClaimTest.php tests/Feature/Rooms/RoomOwnershipTest.php` | ✅ (edit) | ⬜ pending |
| TBD | TBD | TBD | Isolation | — | a gate or record run sends no real text, writes no live row, alerts no owner, and restores DB, `icm.path`, test-now, bindings and channel driver | feature | `php artisan test --compact tests/Feature/Walks/ScratchWorldTest.php` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | D-02 | — | recorded RAW scores meet the CURRENT `config/gate.php` bars at gate time | feature | `php artisan test --compact tests/Feature/Walks/ReplayTest.php` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | D-04/D-05 | — | fingerprint stable, changes with prompt or triage questions; script hash changes only with customer turns | unit | `php artisan test --compact tests/Unit/Icm/WalkFingerprintTest.php` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | D-07 | — | frozen clock, default start, waits, previous test-now restored | feature | `php artisan test --compact tests/Feature/Walks/WalkClockTest.php` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | D-09/D-10 | — | each check phrase evaluates and fails in plain words | unit | `php artisan test --compact tests/Unit/Icm/WalkCheckTest.php` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | WALK-02 | — | pass goes live as before; price and time failures refuse with previous version live; unrecorded owner/Ian forms; stale never refused and never shown to owner; first sync "Nothing is live yet." | feature | `php artisan test --compact tests/Feature/Icm/WalkGateTest.php` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | D-03 | — | `walks:record` (with fakes) stores replies and raw scores, sends nothing | feature | `php artisan test --compact tests/Feature/Walks/WalksRecordCommandTest.php` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | WALK-03 | — | Dana as a walk and as a feature test: appointment, owner alert, card, `records/today.md`, reminder queued | feature | `php artisan test --compact tests/Feature/Walks/DanaEndToEndTest.php` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | D-12 | — | every shipped example walk validates and runs green with scripted fakes, weekday-proof | feature | `php artisan test --compact tests/Feature/Walks/ExampleWalksTest.php` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | D-13/D-14 | — | the two-sided report shows both sides per candidate number and prints D-13's stop when no number separates | feature | `php artisan test --compact tests/Feature/Walks/CalibrationReportTest.php` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | Accessibility | — | walk lines read in order after the "Last sync" heading and status line, no new tab stop, no sideways scroll at 375, axe clean with contrast exempt (Phase 11 gap) | browser | `php artisan test --compact tests/Feature/Filament/BusinessFolderWalkLinesBrowserTest.php` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | Strings | — | no `!` or em dash in any gate-produced string | unit | `php artisan test --compact tests/Unit/Icm/WalkWordsTest.php` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | Map | — | cards citing changed files updated in the same commit | command | `python3 ~/Projects/cartographer/tools/check_map.py ~/Projects/ops-platform --extra-root examples/example-hvac` | ✅ | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Pest.php`: strip `tests/` from the example-folder copy (:85-88), add `layExampleWalks()`, `minimalWalkFile()`, `seedRecording()`
- [ ] `tests/Feature/Icm/WalkFileTest.php`, `tests/Feature/Icm/WalkGateTest.php`
- [ ] `tests/Feature/Walks/`: `ScratchWorldTest`, `ReplayTest`, `WalksRecordCommandTest`, `WalkClockTest`, `DanaEndToEndTest`, `ExampleWalksTest`, `CalibrationReportTest`
- [ ] `tests/Unit/Icm/`: `WalkFingerprintTest`, `WalkCheckTest`, `WalkWordsTest`
- [ ] `tests/Feature/Filament/BusinessFolderWalkLinesBrowserTest.php`
- [ ] `FakeClassifier` per-call sequence support
- [ ] `database/factories/WalkRecordingFactory.php`

No new framework install.

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Ian's live recording and the bar / Booking wording decision | D-17 | Calls the live model and Jev; executors never run it | After execution: `walks:record` (and the calibration run) per client, read the two-sided report, approve bars and wording together; the follow-up plan writes them |
| Keyboard-only and screen reader walk of the Business Folder page with walk lines | Accessibility | Automated checks catch only part of WCAG (CLAUDE.md) | UAT: VoiceOver over a rejected sync with a failed walk line and an unrecorded line |
| Sync Now finishes with the shipped example walk set inside the request | UI-SPEC loading backstop | Timing on real hardware | UAT: press Sync Now on the example folder after recording, note the time |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 60s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
