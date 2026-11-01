---
phase: 07-the-spine-and-leads
verified: 2026-11-01T20:00:00Z
status: passed
score: 5/5 must-haves verified
covered_files:
  - .planning/phases/07-the-spine-and-leads/07-01-PLAN.md
  - .planning/phases/07-the-spine-and-leads/07-01-SUMMARY.md
  - .planning/phases/07-the-spine-and-leads/07-02-PLAN.md
  - .planning/phases/07-the-spine-and-leads/07-02-SUMMARY.md
  - .planning/phases/07-the-spine-and-leads/07-03-PLAN.md
  - .planning/phases/07-the-spine-and-leads/07-03-SUMMARY.md
  - .planning/phases/07-the-spine-and-leads/07-04-PLAN.md
  - .planning/phases/07-the-spine-and-leads/07-04-SUMMARY.md
  - .planning/phases/07-the-spine-and-leads/07-05-PLAN.md
  - .planning/phases/07-the-spine-and-leads/07-05-SUMMARY.md
  - .planning/phases/07-the-spine-and-leads/07-06-PLAN.md
  - .planning/phases/07-the-spine-and-leads/07-06-SUMMARY.md
  - .planning/phases/07-the-spine-and-leads/07-07-PLAN.md
  - .planning/phases/07-the-spine-and-leads/07-07-SUMMARY.md
  - .planning/phases/07-the-spine-and-leads/07-08-PLAN.md
  - .planning/phases/07-the-spine-and-leads/07-08-SUMMARY.md
  - .planning/phases/07-the-spine-and-leads/07-09-PLAN.md
  - .planning/phases/07-the-spine-and-leads/07-09-SUMMARY.md
  - app/Actions/OpenProject.php
  - app/Enums/ProjectWord.php
  - app/Filament/Resources/Contacts/RelationManagers/ProjectsRelationManager.php
  - app/Icm/Validator.php
  - app/Records/CustomerCard.php
  - app/Rooms/Leads/LeadsSection.php
  - app/Rooms/Leads/LeadsServiceProvider.php
  - app/Rooms/Leads/OpenProjectForNewLead.php
  - database/migrations/2026_10_29_000001_create_projects_table.php
  - examples/example-hvac/rooms/leads/CONTEXT.md
  - tests/Feature/Filament/ProjectsRelationManagerKeyboardTest.php
  - tests/Feature/Filament/ProjectsRelationManagerTest.php
  - tests/Feature/Rooms/LeadsBootTest.php
covered_digest: "v3:sha256:ba22a853470be052251754c1f5d1a443993e30b8ed6cb495400737293d689756"
behavior_unverified: 0
overrides_applied: 0
re_verification:
  previous_status: human_needed
  previous_score: 5/5
  gaps_closed: []
  gaps_remaining: []
  regressions: []
deferred:
  - truth: "Live VoiceOver / Safari listen of the contact screen's project list (WINDOWS 38, 44) and of the leads section"
    addressed_in: "Phase 11"
    evidence: "ROADMAP Phase 11 requirements include A11Y-03 (every owner-facing screen passes the automated check and a keyboard-only and VoiceOver walk at 375 and 390 wide). WINDOWS.md rows 38 and 44 are assigned to phase 11 and open. Moved by Ian 2026-11-01, as Phase 5 did."
---

# Phase 7: The Spine and Leads Verification Report

**Phase Goal:** Every new lead opens a project, named in the business's own word, that ties the piece of work to its contact and conversation.
**Verified:** 2026-11-01
**Status:** passed
**Re-verification:** Yes. The prior report (d207d1f, human_needed, 5/5) went stale after test-only commits 08eeec0 and a9497bc plus docs. No gaps were open. The two human items it listed have since been resolved or reassigned (see Human Verification).

## Staleness check

`git diff --name-only d207d1f HEAD`, minus `.planning/`, lists exactly: `docs/BLUEPRINT.md`, `docs/sprints/sprint-1.1.md`, `tests/Feature/Filament/ProjectsRelationManagerKeyboardTest.php`, `tests/Feature/Rooms/LeadsBootTest.php`. Nothing under `app/`, `database/`, `config/`, `examples/`, `resources/`, `routes/` or `bootstrap/` changed. The claim that source changes are test-only holds. Application behaviour is the same as at d207d1f; what changed is the strength of the proof.

## Goal Achievement

### Observable Truths (ROADMAP success criteria)

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | `business/profile.md` has one field naming the project word; the sync accepts a valid word and refuses a missing or invalid one in plain words, previous version staying live | VERIFIED | `app/Enums/ProjectWord.php`, `Validator`; `tests/Feature/Projects` (ProjectWordTest, ProjectWordSpecTest) green in the 494-test re-run |
| 2 | A text from a new number, or a web form lead, opens a project tied to contact and conversation; it shows on the card and the dashboard in the business's word | VERIFIED | `LeadsServiceProvider` hook, `OpenProjectForNewLead`, `OpenProject::forNewLead()`; OpenOnNewLeadTest, ProjectCardTest, LeadsSectionTest, LeadsBootTest green. The dashboard rows are now also proven reachable by Tab, in order, with Enter opening the contact, in real Chromium (new test in LeadsBootTest) |
| 3 | A project is ready to carry appointments (zero, one or many); appointment records arrive in Phase 8 | VERIFIED (by shape, D-09) | Migration has no appointment column; proof of the appointment clause belongs to Phase 8 criterion 2. Not a gap |
| 4 | Front desk behaves exactly as before; only permitted existing tests change; suite green on SQLite and Postgres | VERIFIED | `git diff --stat main -- app/Ai app/SendRule config` is empty. `git diff --name-only --diff-filter=M main -- tests` lists exactly `tests/Feature/Records/CardInjectionTest.php`, `tests/Feature/Rooms/FrontDeskBootTest.php`, `tests/Pest.php` (D-22). CI run 41337489371 on 94ab259, checked with `gh run view`: conclusion success, both `pest (pgsql)` and `pest (sqlite)` jobs success. CI run 41226007469 (on 35d23d4) also success |
| 5 | The leads room meets the room standard and reads the lead sources and offers marketing owns | VERIFIED | `LeadsServiceProvider` extends `RoomServiceProvider`; example `rooms/leads/CONTEXT.md`; LeadsBootTest and RoomWidgetOwnershipTest green |

**Score:** 5/5 truths verified (0 present, behavior-unverified)

### Gap closure 07-09 (re-checked, no regression)

| Must-have | Status | Evidence |
|-----------|--------|----------|
| G-07-2.1: list in the first response | VERIFIED | `protected static bool $isLazy = false;` at `ProjectsRelationManager.php:40`. Now also proven in a browser: Tab from the top reaches "New job" within the press cap on a desktop window (page never scrolls) and on a phone, where a lazy list would never be reached |
| G-07-2.2: focus stays on the row's button after Close and Reopen | VERIFIED | One stable `toggle` action (line 116). Browser tests press Enter on `Close Job 1`, Enter on the confirmation, and assert focus is on `Reopen Job 1` and `closed_at` is set; then the reverse; and Reopen-first from a closed project. Each asserts the DB state, so none is vacuous |
| G-07-2.3: each row button named by its project | VERIFIED | `rowButtonName()` returns `aria-label` from `e(label.' '.rowLabel)`. Browser test finds `getByRole('button', name, exact)` for `New job`, `Close Job 1/2`, `Rename Job 1/2`, count 1 each, in the real accessibility tree |
| Ian's revision T-07-26: both directions confirm, named in plain words | VERIFIED | Modal heading `Close X?` / `Reopen X?` at line 121; New-job dialog takes focus in and returns it to "New job" on Escape (browser test) |
| D-21 stock Filament only; D-11 only four actions write projects | VERIFIED | No view/CSS/JS in the diff; `tests/Unit/ProjectWritesTest.php` green |

### Behavioral Spot-Checks and re-run evidence

| Behavior | Command | Result | Status |
|----------|---------|--------|--------|
| Keyboard browser tests, relation manager, leads boot, write scan | `php artisan test --compact tests/Feature/Filament/ProjectsRelationManagerKeyboardTest.php tests/Feature/Rooms/LeadsBootTest.php tests/Feature/Filament/ProjectsRelationManagerTest.php tests/Unit/ProjectWritesTest.php` | 56 passed, 295 assertions | PASS |
| Phase-area regression | `php artisan test --compact tests/Feature/Projects tests/Feature/Rooms tests/Feature/Records` | 494 passed, 2413 assertions | PASS |
| Formatting | `vendor/bin/pint --test --dirty` | passed | PASS |
| Debt markers | grep TBD/FIXME/XXX over phase-touched app and test files | none | PASS |
| CI both databases | `gh run view 41337489371` | success on pgsql and sqlite legs, head 94ab259 | PASS |

The orchestrator's full-suite figure (2433 passed) was not re-run here; the CI runs above (both databases, the whole suite) are the independent evidence, and the phase-area subset was re-run. Orchestrator gates reported but not re-run by this verifier: TDD gate 0 violations, code review 0 critical / 0 warning / 4 info (5dad5e6), security threats_open 0.

### Required Artifacts / Key Links / Data flow

All artifacts from the first verification and 07-09 are present, substantive and wired; none changed in source since the prior report. `toggle` to `CloseProject`/`ReopenProject`, `rowButtonName()` to `rowLabel()`, `LeadsServiceProvider` hook to `OpenProject`, `Validator` to `ActiveConfig::projectWord` remain WIRED (covered by the green tests above). Contact list and dashboard rows read real `projects` rows (FLOWING).

### Probe Execution

SKIPPED (no probes declared by the phase).

### Requirements Coverage

| Requirement | Source Plans | Description | Status | Evidence |
|-------------|--------------|-------------|--------|----------|
| PROJ-01 | 07-02..07-05, 07-09 | Project ties work to contact and conversation; appointments hang off it | SATISFIED (appointment clause by shape, proof in Phase 8 per D-09) | Truths 2, 3 |
| PROJ-02 | 07-01, 07-03, 07-05, 07-09 | One profile field naming the project word; dashboard and card use it; front desk frozen | SATISFIED | Truths 1, 2, 4 |
| ROOM-07 | 07-06, 07-07 | Leads room to the room standard, reads lead sources and offers | SATISFIED | Truth 5 |
| LEAD-05 | 07-08 | New lead opens a project beside the inbound path; front desk unchanged; green on both databases | SATISFIED | Truths 2, 4; CI pgsql leg green |

All four IDs appear in REQUIREMENTS.md (lines 26, 27, 34, 35 checked; traceability rows 202 to 206 Complete). No orphaned Phase 7 requirements.

### Anti-Patterns Found

None blocking. Open items are info only and recorded in `07-REVIEW-DISPOSITION.md` (IN-05 and IN-07 were fixed by 08eeec0 and a9497bc; IN-06 cosmetic docblock staleness, IN-04 time-zone label deferred to Phase 11 wording pass).

### Human Verification Required

None for Phase 7. Evidence for each item the prior report listed:

- Prior item 2 (CI on both legs): closed. WINDOWS 45 and 34 to 37, 39, 40, 42, 43 are `fixed` with CI evidence, and I confirmed both CI runs are green via `gh`.
- Prior item 1 (WINDOWS 44 VoiceOver/Safari retest): Ian moved the live VoiceOver listen to the Phase 11 owner-screen pass (A11Y-03). WINDOWS rows 38 and 44 are `open` and assigned to phase 11; ROADMAP Phase 11 carries A11Y-03. Phase 7's own keyboard, focus and accessible-name promises are proven by the Chromium tests (real Tab and Enter, accessibility-tree role-and-name lookup), and UAT test 3 (WINDOWS 41) passed. The VoiceOver listen is therefore deferred, not a Phase 7 promise left unmet.

### Gaps Summary

No gaps. Application code is unchanged since the prior verification; the two test-only commits strengthen the proof of the UAT-gap fixes (Tab reach, focus, names) in a real browser and add the dashboard keyboard walk. All five roadmap criteria hold, all four requirement IDs are accounted for, and the Postgres leg is confirmed green in CI.

---

_Verified: 2026-11-01_
_Verifier: Claude (gsd-verifier)_
