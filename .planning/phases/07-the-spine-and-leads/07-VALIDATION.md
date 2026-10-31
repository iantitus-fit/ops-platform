---
phase: "7"
slug: "the-spine-and-leads"
# status lifecycle: draft (seeded by plan-phase) → validated (set by validate-phase §6)
# audit-milestone §5.5 distinguishes NOT-VALIDATED (draft) from PARTIAL (validated + nyquist_compliant: false) (#2117)
status: validated
nyquist_compliant: true
wave_0_complete: true
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

Filled from the plans (eight plans, one per wave, sequential because every plan updates docs/BLUEPRINT.md and the walk-through log in the same commit as its change). Each task writes its failing test first (tdd_mode); the plan's last task also runs the full suite.

| Task ID | Plan | Wave | Requirement | Threat Ref | Secure Behavior | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|------------|-----------------|-----------|-------------------|-------------|--------|
| 07-01-T1 | 01 | 1 | PROJ-02 / criterion 1 (D-12) | T-07-01, T-07-02 | Sync accepts the six words in any case; refuses missing and unlisted in one plain line naming `business/profile.md`, `project_word` and the choices; previous version stays live; `projectWord()` never throws | feature | `php artisan test --compact tests/Feature/Projects/ProjectWordTest.php tests/Feature/Icm` then full suite | ✅ | ✅ green |
| 07-01-T2 | 01 | 1 | PROJ-02 (D-12) | none | SPEC word table equals `ProjectWord::cases()` | feature | `php artisan test --compact tests/Feature/Projects tests/Feature/Rooms/SpecTablesTest.php` | ✅ | ✅ green |
| 07-02-T1 | 02 | 2 | PROJ-01 (D-06, D-08, D-10, D-11) | T-07-04, T-07-05 | Owner's open writes one project tied to the open conversation; card timeline shows "Job 1 opened" | feature | `php artisan test --compact tests/Feature/Projects/ProjectRecordTest.php tests/Feature/Records tests/Feature/Filament/ContactResourceTest.php` | ✅ | ✅ green |
| 07-02-T2 | 02 | 2 | PROJ-01 (D-05, D-09, D-10) | T-07-03 | No appointment column; any number by hand; one lead project per contact (partial unique index); numbering; owned by no room | feature | `php artisan test --compact tests/Feature/Projects tests/Feature/Records tests/Feature/Rooms/FrontDeskBootTest.php tests/Feature/Rooms/TeamBootTest.php` then full suite | ✅ | ✅ green |
| 07-03-T1 | 03 | 3 | PROJ-02 / criterion 2 (D-13) | T-07-07 | Card section above the timeline in the business's word; omitted with no project; front desk sees it through the card | feature | `php artisan test --compact tests/Feature/Projects tests/Feature/Records tests/Feature/FrontDesk` | ✅ | ✅ green |
| 07-03-T2 | 03 | 3 | PROJ-02 (D-08, D-13) | T-07-06 | Owner-typed name quoted under the untrusted label; cannot forge a heading (new CardInjectionTest case); Matter relabels | feature | same, then full suite | ✅ (case added) | ✅ green |
| 07-04-T1 | 04 | 4 | PROJ-01 (D-05, D-11) | T-07-10 | Close records who and when; second tab adds no second entry | feature | `php artisan test --compact tests/Feature/Projects tests/Feature/Records` | ✅ | ✅ green |
| 07-04-T2 | 04 | 4 | PROJ-01 (D-05, D-07, D-08) | T-07-09 | Reopen and rename; names one line, at most 100, no hidden characters; status never changes; no alert or text | feature | same | ✅ | ✅ green |
| 07-04-T3 | 04 | 4 | PROJ-01 (D-11) | T-07-08 | Only the four actions write projects (non-vacuous scan); arch keeps them out of App\Ai | unit | `php artisan test --compact tests/Unit/ProjectWritesTest.php tests/Unit/ArchitectureTest.php tests/Feature/Projects` then full suite | ✅ | ✅ green |
| 07-05-T1 | 05 | 5 | PROJ-01 (D-04, D-14) | T-07-11, T-07-13 | Contact screen lists projects; "New <word>" opens one by hand; relation manager not read-only | feature (Livewire) | `php artisan test --compact tests/Feature/Filament/ProjectsRelationManagerTest.php tests/Feature/Filament/ContactResourceTest.php tests/Feature/Rooms/RoomTimelineTest.php tests/Unit/ProjectWritesTest.php` | ✅ | ✅ green |
| 07-05-T2 | 05 | 5 | PROJ-02 (D-05, D-08, D-14) | T-07-12 | Close, Reopen, Rename rows; Matter word; refused name escaped | feature (Livewire) | `php artisan test --compact tests/Feature/Filament tests/Feature/Projects tests/Unit/ProjectWritesTest.php tests/Feature/Rooms/RoomTimelineTest.php` then full suite | ✅ | ✅ green |
| 07-06-T1 | 06 | 6 | ROOM-07 / criterion 5 (D-03, D-15, D-17, D-18, D-20) | T-07-14, T-07-16 | Leads room boots, owns nothing, reads lead sources and offers, optional, claims New leads; `Pest.php:295` and `FrontDeskBootTest.php:90` the only test lines changed | feature | `php artisan test --compact tests/Feature/Rooms/LeadsBootTest.php tests/Feature/Rooms/FrontDeskBootTest.php tests/Feature/Rooms/TeamBootTest.php tests/Feature/Rooms/SpecTablesTest.php tests/Feature/Rooms/NeverLeftOutTest.php tests/Unit/Icm/NeverLeftOutTest.php` then full suite | ✅ | ✅ green |
| 07-06-T2 | 06 | 6 | ROOM-07 (D-15 WR-09, D-19) | T-07-15 | A widget one room adds cannot be claimed by another, either order | unit | `php artisan test --compact tests/Unit/Rooms tests/Feature/Rooms` then full suite | ✅ | ✅ green |
| 07-07-T1 | 07 | 7 | ROOM-07 (D-15, D-21, A11Y-01) | T-07-17 | Leads section lists open projects with a keyboard link and an absolute date; axe clean with and without projects | feature + browser | `php artisan test --compact tests/Feature/Rooms/LeadsBootTest.php tests/Feature/Rooms/RoomSectionTest.php tests/Feature/Rooms/RoomAccessibilityTest.php tests/Unit/ProjectWritesTest.php tests/Unit/ArchitectureTest.php` | ✅ | ✅ green |
| 07-07-T2 | 07 | 7 | PROJ-02 / criterion 2 (D-05, D-15, D-16) | T-07-18, T-07-19 | Lead source or opener per row; newest 20 and a total; Matter; hidden when room off while projects stay; failure line; flat query count | feature | `php artisan test --compact tests/Feature/Rooms tests/Feature/Filament/ProjectsRelationManagerTest.php tests/Unit/ProjectWritesTest.php` then full suite | ✅ | ✅ green |
| 07-08-T1 | 08 | 8 | LEAD-05 / criterion 2 (D-01, D-02, D-06) | T-07-20 | New-number text and unmatched form open one project after the commit; `CardInjectionTest.php:90` 4 to 6 | feature | `php artisan test --compact tests/Feature/Projects/OpenOnNewLeadTest.php tests/Feature/Records tests/Feature/Inbound tests/Unit/ProjectWritesTest.php` then full suite | ✅ | ✅ green |
| 07-08-T2 | 08 | 8 | LEAD-05 (D-01, D-04, D-07, D-23) | T-07-22, T-07-24 | Known number, matching form, factory contact and first STOP open nothing; START/HELP/picture-only open one; lead stays a Lead; no text or alert | feature | `php artisan test --compact tests/Feature/Projects/OpenOnNewLeadTest.php tests/Feature/Inbound tests/Feature/Filament/DashboardWidgetsTest.php` | ✅ | ✅ green |
| 07-08-T3 | 08 | 8 | LEAD-05 / criterion 4 (D-02, D-03) | T-07-20, T-07-21, T-07-23 | Room off at arrival or run time; no backfill; existing project; lost race (savepoint); queue down; open throws; retries | feature | `php artisan test --compact tests/Feature/Projects tests/Feature/Inbound tests/Feature/FrontDesk tests/Feature/Alerts tests/Feature/Records tests/Unit/ProjectWritesTest.php` then full suite | ✅ | ✅ green |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [x] `tests/Rooms/SpecTables.php` stays unchanged: plan 07-01's ProjectWordSpecTest reads the SPEC rows with the public `SpecTables::rows()` and a file-local helper, so no shared test file is edited (D-22)
- [x] `database/factories/ProjectFactory.php` with openedByLead(), openedByUser(User) and closed(?User) states (plan 07-02)
- [x] Every new test file named in the map above: tests/Feature/Projects/{ProjectWordTest, ProjectWordSpecTest, ProjectRecordTest, ProjectCardTest, ProjectActionsTest, OpenOnNewLeadTest}.php, tests/Unit/ProjectWritesTest.php, tests/Feature/Filament/ProjectsRelationManagerTest.php, tests/Feature/Rooms/{LeadsBootTest, LeadsSectionTest}.php, tests/Unit/Rooms/RoomWidgetOwnershipTest.php
- [x] Existing tests changed, named by file and line (D-22): `tests/Feature/Records/CardInjectionTest.php:90` (plan 07-08; the file also gains one new case in plan 07-03, as D-13 names), `tests/Feature/Rooms/FrontDeskBootTest.php:90` (plan 07-06), `tests/Pest.php:295` (`exampleFolderFileCount()`, 25 to 26, plan 07-06). CONTEXT.md's `:36` is unchanged. The profile-fixture and marketing-CONTEXT.md allowances are unused (no such tests exist, grep verified at planning).

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Suite green on Postgres | criterion 4 | No local Postgres | Ian pushes the branch and reads the CI `pgsql` leg |
| Contact screen actions and leads section by keyboard and screen reader | D-15, A11Y (CLAUDE.md) | Automated axe covers only part of WCAG | UAT keyboard-only and screen reader walk |

---

## Validation Sign-Off

- [x] All tasks have `<automated>` verify or Wave 0 dependencies
- [x] Sampling continuity: no 3 consecutive tasks without automated verify
- [x] Wave 0 covers all MISSING references
- [x] No watch-mode flags
- [x] Feedback latency < 165s for the per-task commands (the full suite now takes about 178s on SQLite, 2407 tests)
- [x] `nyquist_compliant: true` set in frontmatter

**Approval:** approved 2026-10-31 (all 18 tasks green in the full suite, 2407 passed on SQLite; Postgres and the keyboard and screen reader walk stay manual-only)

## Validation Audit 2026-10-31

| Metric | Count |
|---|---|
| Gaps found | 0 |
| Resolved | 0 |
| Escalated | 0 |
