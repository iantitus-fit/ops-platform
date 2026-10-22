---
phase: "4"
slug: "room-standard"
# status lifecycle: draft (seeded by plan-phase) → validated (set by validate-phase §6)
# audit-milestone §5.5 distinguishes NOT-VALIDATED (draft) from PARTIAL (validated + nyquist_compliant: false) (#2117)
status: draft
nyquist_compliant: false
wave_0_complete: false
created: "2026-10-22"
---

# Phase 4 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest 5.3.1 with `pest-plugin-laravel` 5.0.1 and `pest-plugin-arch`; `pestphp/pest-plugin-browser` ^5.1 added for A11Y-01 behind Ian's approval checkpoint |
| **Config file** | `phpunit.xml` (suites `tests/Unit`, `tests/Feature`); shared setup in `tests/Pest.php` |
| **Quick run command** | `php artisan test --compact tests/Feature/Rooms` |
| **Full suite command** | `php artisan test --compact` (CI runs `php artisan test` on both `sqlite` and `pgsql`) |
| **Estimated runtime** | ~60 seconds for `tests/Feature/Rooms` locally with the browser check; full suite as today plus the browser launch |

---

## Sampling Rate

- **After every task commit:** Run the narrowest file for the task, `php artisan test --compact <file>`
- **After every plan wave:** Run `php artisan test --compact tests/Feature/Rooms tests/Feature/Icm tests/Unit`
- **Before `/gsd-verify-work`:** Full suite must be green, on SQLite locally and on both databases in CI
- **Max feedback latency:** 60 seconds

---

## Per-Task Verification Map

Requirement-level map seeded from RESEARCH.md § Validation Architecture. Task IDs are filled in from the plans.

| Task ID | Plan | Wave | Requirement | Threat Ref | Secure Behavior | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|------------|-----------------|-----------|-------------------|-------------|--------|
| TBD | TBD | TBD | ROOM-01 | — | Contract-only room passes; broken example refused naming file and field; previous version stays live | feature | `php artisan test --compact tests/Feature/Rooms/RoomContractTest.php` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | ROOM-01 | — | Unknown room (D-07), double claim (D-15), unregistered type (D-17) refused; only `rooms/<room>/CONTEXT.md` is a room (D-03) | feature | `php artisan test --compact tests/Feature/Rooms/RoomOwnershipTest.php` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | ROOM-02 | — | Pretend room registers file type, table, section, timeline entry from test setup only | feature | `php artisan test --compact tests/Feature/Rooms/PretendRoomBootTest.php` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | ROOM-03 | — | Slot with fake driver; `owns`/`reads`/`slot` checked (D-24, D-25) | feature | `php artisan test --compact tests/Feature/Rooms/ConnectorFileTest.php` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | ROOM-04 | — | Records statement required, may be none | feature | `php artisan test --compact tests/Feature/Rooms/RoomContractTest.php` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | ROOM-05 | — | Boot test empty and with example folder | feature | `php artisan test --compact tests/Feature/Rooms/PretendRoomBootTest.php` (CI matrix: sqlite, pgsql) | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | CONN-07 | — | Two owners refused with the exact D-26 sentence; platform kinds never ownable (D-23) | feature | `php artisan test --compact tests/Feature/Rooms/ConnectorFileTest.php` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | D-14, D-22, D-23 | — | SPEC ownership table and both kind lists equal the code lists | feature | `php artisan test --compact tests/Feature/Rooms/SpecTablesTest.php` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | A11Y-01 | — | axe, five WCAG 2.2 AA tags plus `target-size`, scoped to the pretend section including the D-21 empty line, `checked > 0` guard | browser | `php artisan test --compact tests/Feature/Rooms/RoomAccessibilityTest.php` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | ROOM-02 (timeline) | — | Room entry shows; absent room's rows hidden at read, never deleted; rows with no room always show | feature | `php artisan test --compact tests/Feature/Rooms/RoomTimelineTest.php` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Rooms/PretendRoom/*` — provider, file type, slot interface and fake driver, widget, model, migration (ROOM-02, ROOM-03)
- [ ] `tests/Fixtures/pretend-room/**` — contract, example file, two connector files (ROOM-01, CONN-07)
- [ ] `tests/Pest.php` helpers to enable the pretend room and lay its folder; `exampleFolderFileCount()` stays 14
- [ ] `tests/Feature/Rooms/*` files listed above
- [ ] The one named edit to `tests/Feature/Icm/ValidatorTest.php:67` (the hard-coded list of valid types)
- [ ] Browser check install, each behind a checkpoint Ian approves: `pestphp/pest-plugin-browser` ^5.1 (composer, dev), `playwright` 1.63.0 exact (npm, dev), `npx playwright install chromium` on Ian's Mac (Ian runs it), CI Node + `npm ci` + Chromium + `sockets` extension

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Chromium installed for local browser tests | A11Y-01 | One-time download on Ian's Mac | Ian runs `npx playwright install chromium`, then `php artisan test --compact tests/Feature/Rooms/RoomAccessibilityTest.php` |
| Postgres leg of the boot test and the accessibility check | ROOM-05, A11Y-01 | No local Postgres client; CI runs both legs | Check the CI run on the phase branch shows both `sqlite` and `pgsql` legs green |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 60s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
