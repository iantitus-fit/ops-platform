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
| 04-01-T1, 04-01-T2 | 04-01 | 1 | ROOM-01, ROOM-02 | T-04-01, T-04-02, T-04-03 | Contract-only room turns on; only `rooms/<room>/CONTEXT.md` is a room (D-03); registry guards | feature + unit | `php artisan test --compact tests/Feature/Rooms/PretendRoomBootTest.php tests/Unit/Rooms/RoomRegistryTest.php` | ❌ W0 | ⬜ pending |
| 04-02-T1, 04-02-T2 | 04-02 | 2 | ROOM-01, ROOM-04 | T-04-05, T-04-06, T-04-07, T-04-32 | Contract fields required; owns, reads and writes_records accept none or [] and both load as the same empty list, any other non-list word refused (`None` and `nothing` included); `[none]` checked like any entry (`owns: [none]` loads as `['none']`, `writes_records: [none]` refused as not under records/); paths inside the folder, records statement under records/, unknown room (D-07) refused; previous version stays live | feature | `php artisan test --compact tests/Feature/Rooms/RoomContractTest.php` | ❌ W0 | ⬜ pending |
| 04-02-T3 | 04-02 | 2 | ROOM-03 | T-04-08 | Slot interface with a fake driver; contract connector equals the registered slot or none (D-25) | feature | `php artisan test --compact tests/Feature/Rooms/RoomContractTest.php` | ❌ W0 | ⬜ pending |
| 04-03-T1 | 04-03 | 3 | ROOM-02 | T-04-11 | Room file type validated and compiled; broken example refused naming file and field; previous version stays live | feature | `php artisan test --compact tests/Feature/Rooms/RoomFileTypeTest.php` | ❌ W0 | ⬜ pending |
| 04-03-T2 | 04-03 | 3 | ROOM-01, ROOM-02 | T-04-09, T-04-10, T-04-33 | D-04 placement (a room's own kind of file outside `rooms/<room>/` refused naming the folder, in one line that also offers to add the folder or remove the file when the room's folder is absent, never with the D-10 line on top; the same file inside accepted, today's types exempt, previous version stays live), D-10, D-11, D-15, D-17 and one-owner refusals | feature | `php artisan test --compact tests/Feature/Rooms/RoomOwnershipTest.php` | ❌ W0 | ⬜ pending |
| 04-03-T3, 04-04-T2 | 04-03, 04-04 | 3, 4 | D-12, D-14, D-22, D-23 | — | SPEC ownership table and both kind lists equal the code | feature | `php artisan test --compact tests/Feature/Rooms/SpecTablesTest.php` | ❌ W0 | ⬜ pending |
| 04-04-T1, T2, T3 | 04-04 | 4 | CONN-07, ROOM-03 | T-04-13, T-04-14, T-04-15, T-04-32 (shared with 04-02) | owns/reads/slot checked, owns and reads accepting none or [] through the room contract's Loader rule; `owns: [none]` refused as an unknown kind (D-22) and `slot: none` refused as an unknown slot (D-25); objects refused; platform kinds never owned; exact D-26 sentence; previous version stays live | feature | `php artisan test --compact tests/Feature/Rooms/ConnectorFileTest.php` | ❌ W0 | ⬜ pending |
| 04-05-T1, T2 | 04-05 | 5 | ROOM-02 (timeline) | T-04-18, T-04-19, T-04-20, T-04-31 | Room entry drawn; absent room's rows hidden at read, never deleted; rows with no room always show; a sync that turns a room off or on redraws each stored card holding its entries | feature | `php artisan test --compact tests/Feature/Rooms/RoomTimelineTest.php` | ❌ W0 | ⬜ pending |
| 04-05-T3, 04-06-T2 | 04-05, 04-06 | 5, 6 | ROOM-02, ROOM-05 | T-04-23 | Boot test empty and with example folder: file type, table, section, entry, slot (CI matrix: sqlite, pgsql) | feature | `php artisan test --compact tests/Feature/Rooms/PretendRoomBootTest.php` | ❌ W0 | ⬜ pending |
| 04-06-T1 | 04-06 | 6 | ROOM-01 (D-21) | T-04-22 | One plain empty line from the contract; never for a room owning nothing; hidden section 403 | feature | `php artisan test --compact tests/Feature/Rooms/RoomSectionTest.php` | ❌ W0 | ⬜ pending |
| 04-07-T2, 04-08-T2 | 04-07, 04-08 | 1, 2 | A11Y-01 (tools) | T-04-SC, T-04-26 | Plugin ^5.1 and playwright 1.63.0 exact, each after Ian's approval; CI sockets, Node, npm ci, Chromium | config | `composer show pestphp/pest-plugin-browser` and the package.json version check in 04-08 | n/a | ⬜ pending |
| 04-09-T1, T2 | 04-09 | 7 | A11Y-01 | T-04-28 | axe, five WCAG 2.2 AA tags plus `target-size`, scoped to the pretend section including the D-21 empty line, `checked > 0` guard, injected violation caught | browser | `php artisan test --compact tests/Feature/Rooms/RoomAccessibilityTest.php` | ❌ W0 | ⬜ pending |

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
