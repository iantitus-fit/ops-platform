---
phase: "7"
slug: "the-spine-and-leads"
# status lifecycle: draft (seeded by plan-phase) → validated (set by validate-phase §6)
# audit-milestone §5.5 distinguishes NOT-VALIDATED (draft) from PARTIAL (validated + nyquist_compliant: false) (#2117)
status: draft
nyquist_compliant: false
wave_0_complete: false
created: "2026-10-29"
---

# Phase 7 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest 5.x on PHPUnit, Laravel `RefreshDatabase`; `pest-plugin-browser` for the axe check |
| **Config file** | `phpunit.xml` (QUEUE_CONNECTION=sync, SQLite `:memory:`); `tests/Pest.php` (fresh copy of `examples/example-hvac` per Feature test) |
| **Quick run command** | `php artisan test --compact tests/Feature/Projects` (or the narrowest file for the task) |
| **Full suite command** | `php artisan test --compact` |
| **Estimated runtime** | ~165 seconds full on SQLite (2278 tests at baseline); Postgres runs in CI only |

---

## Sampling Rate

- **After every task commit:** Run the task's new test file plus the neighbouring existing directory (`tests/Feature/Records`, `tests/Feature/Rooms`, `tests/Feature/Icm`, `tests/Feature/Inbound` as relevant), on SQLite
- **After every plan wave:** Run `php artisan test --compact tests/Feature tests/Unit` for the touched areas
- **Before `/gsd-verify-work`:** Full suite green on SQLite, then Ian pushes and confirms the CI `pgsql` leg (no local Postgres)
- **Max feedback latency:** 165 seconds

---

## Per-Task Verification Map

Filled from the plans as they land. Requirement-level map (from 07-RESEARCH.md § Validation Architecture):

| Requirement | Behavior | Test Type | Automated Command | File Exists | Status |
|-------------|----------|-----------|-------------------|-------------|--------|
| PROJ-02 / criterion 1 | Sync accepts each listed word in any case; refuses missing and unlisted in plain words naming `business/profile.md` and the field; previous version stays live | feature | `php artisan test --compact tests/Feature/Projects/ProjectWordTest.php` | ❌ W0 | ⬜ pending |
| PROJ-02 (D-12) | SPEC word table equals the code's word list | feature | `php artisan test --compact tests/Feature/Projects/ProjectWordSpecTest.php` | ❌ W0 | ⬜ pending |
| PROJ-01 (D-08, D-09) | Project belongs to a contact and optionally a conversation; no appointment column; open or closed; "<word> N" numbering | feature | `php artisan test --compact tests/Feature/Projects/ProjectModelTest.php` | ❌ W0 | ⬜ pending |
| D-11 | Four actions record who acted and add one timeline entry each; second-tab repeat adds none; contact status never changes | feature | `php artisan test --compact tests/Feature/Projects/ProjectActionsTest.php` | ❌ W0 | ⬜ pending |
| D-11 | Nothing but the four actions writes the projects table | unit | `php artisan test --compact tests/Unit/ProjectWritesTest.php` | ❌ W0 | ⬜ pending |
| PROJ-02 / criterion 2 (D-13) | Card section above the timeline in the business's word; omitted with no project; owner name quoted and neutralized | feature | `php artisan test --compact tests/Feature/Projects/ProjectCardTest.php tests/Feature/Records/CardInjectionTest.php` | ❌ W0 / ✅ | ⬜ pending |
| criterion 2 | A business that sets "Matter" sees "Matter" on the card, contact screen and dashboard | feature | `ProjectCardTest`, `ProjectsRelationManagerTest`, `LeadsBootTest` | ❌ W0 | ⬜ pending |
| LEAD-05 (D-01) | New-number text and non-matching form open one project; known number, matching form and factory contact open none | feature | `php artisan test --compact tests/Feature/Projects/OpenOnNewLeadTest.php` | ❌ W0 | ⬜ pending |
| D-02 | Retry or duplicate never opens two; a failed dispatch never breaks the inbound; project links to the lead's conversation | feature | same file | ❌ W0 | ⬜ pending |
| D-03 | Room off at arrival or at run time opens nothing; switching on later backfills nothing | feature | same file | ❌ W0 | ⬜ pending |
| ROOM-07 / criterion 5 | Leads room boots empty and with the example folder; contract matches SPEC; reads lead sources and offers; writes no records; claims `NewLeads`; section axe-clean | feature + browser | `php artisan test --compact tests/Feature/Rooms/LeadsBootTest.php` | ❌ W0 | ⬜ pending |
| D-15 (06 WR-09) | A room cannot claim a widget another room adds, in either registration order | unit | `php artisan test --compact tests/Unit/Rooms/RoomWidgetOwnershipTest.php` | ❌ W0 | ⬜ pending |
| D-14 | New, Close, Reopen and Rename work from the contact screen | feature (Livewire) | `php artisan test --compact tests/Feature/Filament/ProjectsRelationManagerTest.php` | ❌ W0 | ⬜ pending |
| criterion 4 | Front desk unchanged; only the D-22 test edits | regression | `php artisan test --compact tests/Feature/FrontDesk` then full suite | ✅ | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Rooms/SpecTables.php`: add `projectWords()`
- [ ] `database/factories/ProjectFactory.php` with open, closed, opened-by-lead and opened-by-user states
- [ ] Every new test file named in the map above
- [ ] Existing tests changed, named by file and line (D-22): `tests/Feature/Records/CardInjectionTest.php:90`, `tests/Feature/Rooms/FrontDeskBootTest.php:90`, `tests/Pest.php:295` (`exampleFolderFileCount()`, 25 to 26)

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Suite green on Postgres | criterion 4 | No local Postgres | Ian pushes the branch and reads the CI `pgsql` leg |
| Contact screen actions and leads section by keyboard and screen reader | D-15, A11Y (CLAUDE.md) | Automated axe covers only part of WCAG | UAT keyboard-only and screen reader walk |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 165s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
