---
phase: 07-the-spine-and-leads
verified: 2026-11-01T17:00:00Z
status: human_needed
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
covered_digest: "v3:sha256:8115f8ae6e6185388c00ce57c432eca493a26856448115370bf1faa17cdb7342"
behavior_unverified: 0
overrides_applied: 0
re_verification:
  previous_status: human_needed
  previous_score: 5/5
  gaps_closed:
    - "G-07-2.1: contact screen project list reachable by Tab (drawn in the first response)"
    - "G-07-2.2: focus stays on the row's button after Close and after Reopen"
    - "G-07-2.3: each row button names its project (Close Job 1, Rename Johnny), visible text unchanged"
  gaps_remaining: []
  regressions: []
human_verification:
  - test: "WINDOWS 44: retest of UAT test 2 (entry 38) with VoiceOver in Safari and by keyboard, on a contact with two projects"
    expected: "Tab from the top reaches the list; Close opens a dialog announced as 'Close Job 1?', confirming leaves focus on the row's button read as 'Reopen Job 1, button', and the Closed notification is announced; Reopen opens 'Reopen Job 1?' and confirming leaves focus on 'Close Job 1'; every row button is announced with its project (Close Johnny, Rename Job 2); New and Rename still take and return focus; the row buttons look and space as before"
    why_human: "CLAUDE.md WCAG 2.2 AA rule: customer pages and owner screens get a keyboard-only and screen reader walk at UAT. The Chromium Enter-key proof does not cover VoiceOver or Safari. This is the retest that decides UAT test 2."
  - test: "WINDOWS 45 (with the still-open Postgres rows 34-37, 39, 40, 42, 43): after Ian pushes the phase branch, the CI tests workflow is green on both the sqlite and pgsql legs, with ProjectsRelationManagerTest and ProjectsRelationManagerKeyboardTest included"
    expected: "Green on both legs; the Chromium focus proof passes on a CI runner (review IN-05 notes its two-second waits could flake on a slow runner)"
    why_human: "Postgres and the CI browser run exist only after the push, which is Ian's. Verified here on SQLite only."
---

# Phase 7: The Spine and Leads Verification Report

**Phase Goal:** Every new lead opens a project, named in the business's own word, that ties the piece of work to its contact and conversation.
**Verified:** 2026-11-01
**Status:** human_needed
**Re-verification:** Yes, after gap closure plan 07-09 (UAT gaps G-07-2.1, G-07-2.2, G-07-2.3). The earlier report (2026-10-31, human_needed, 5/5) was checked for regression and its evidence re-run. All nine plans covered.

## Goal Achievement

### Observable Truths (ROADMAP success criteria)

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | `business/profile.md` has one field naming the project word; the sync accepts a valid word and refuses a missing or invalid one in plain words naming file and field, previous version staying live | VERIFIED | `app/Enums/ProjectWord.php`, `Validator::businessErrors()`; `ProjectWordTest` and `ProjectWordSpecTest` pass in the re-run of `tests/Feature/Projects` (part of 493 passed). Unchanged by 07-09. |
| 2 | A text from a new number, or a web form lead, opens a project tied to contact and conversation; shows on the card and dashboard in the business's word | VERIFIED | `LeadsServiceProvider::boot()` hook, `OpenProjectForNewLead`, `OpenProject::forNewLead()`; `OpenOnNewLeadTest`, `ProjectCardTest`, `LeadsSectionTest`, `LeadsBootTest` pass. The contact screen list (a third surface) now also reaches a keyboard user (see 07-09 table). |
| 3 | A project is ready to carry appointments, zero, one or many; appointment records arrive in Phase 8 | VERIFIED (by shape, D-09) | Migration has no appointment column and no status column; proof of the appointment clause is Phase 8 criterion 2. Not a gap. |
| 4 | Front desk behaves exactly as before; only permitted existing tests change; suite green on SQLite and Postgres | VERIFIED on SQLite; Postgres routed to human | `git diff --stat main` over `app/Ai`, `app/SendRule`, `RecordInbound`, `RunFrontDesk`, `OutboundSender` and `config` is empty. `git diff --name-only --diff-filter=M main -- tests` lists exactly `tests/Feature/Records/CardInjectionTest.php`, `tests/Feature/Rooms/FrontDeskBootTest.php`, `tests/Pest.php` (D-22 holds after 07-09; the two tests it rewrote are Phase 7 files). Orchestrator: full suite 2428 passed twice. Postgres is CI-only. |
| 5 | The leads room meets the standard and reads the lead sources and offers marketing owns | VERIFIED | `LeadsServiceProvider` extends `RoomServiceProvider`, registered in `bootstrap/providers.php`; example `rooms/leads/CONTEXT.md`; `LeadsBootTest` and `RoomWidgetOwnershipTest` pass in the `tests/Feature/Rooms` re-run. |

**Score:** 5/5 truths verified (0 present, behavior-unverified)

### Gap closure 07-09 (UAT test 2, re-verified against code)

| Gap / must-have | Status | Evidence |
|-----------------|--------|----------|
| G-07-2.1 (WCAG 2.1.1): list in the first response, no lazy placeholder | VERIFIED | `ProjectsRelationManager` has `protected static bool $isLazy = false;` (line 37). Tests 'shows on the contact page in its first response, so Tab can reach the list' (real GET; asserts "Job 1", "Job 2", "Rename", "New job" present and `__lazyLoad` absent) and 'is not lazy' pass. The old placeholder-passing `assertSeeLivewire` check is gone. |
| G-07-2.3 (WCAG 2.5.3): row buttons carry `aria-label` = visible word + row label | VERIFIED | `rowButtonName()` returns `['aria-label' => e($action->getLabel().' '.$this->rowLabel(...))]`, used on both `toggle` and `rename`; `rowLabel()` is the single helper also used by the label column and modal copy. Tests: 'names each row button by its project, visible text unchanged', 'puts the visible word first', 'names the button after a rename', 'carries the names in the contact page's first response' pass. |
| G-07-2.3 escaping (T-07-25): owner text reaches the attribute only through `e()` | VERIFIED | Code uses `e()`; test 'escapes an owner-typed name in a button's name' (hostile double quote, ampersand, b and script elements) passes. |
| G-07-2.2 (WCAG 2.4.3): one stable Close/Reopen action, `wire:key` unchanged across toggles | VERIFIED | One action named `toggle`; no `close`/`reopen` actions remain (`assertTableActionDoesNotExist`). Test 'closes and reopens from one row button that keeps its place' reads the rendered `wire:key` before Close, after Close and after Reopen and asserts byte-identity, plus label and colour (gray while Close, primary while Reopen). Passes. |
| G-07-2.2 focus: in a real Chromium with real Enter keys, focus stays on the row's button | VERIFIED (automated); VoiceOver/Safari is human | `ProjectsRelationManagerKeyboardTest` (2 cases, executed here, 2 passed, 10 assertions): Enter on `Close Job 1`, Enter on the confirmation, `document.activeElement` named `Reopen Job 1`, project actually closed; and the reverse. Not vacuous: asserts the confirmation opened and the DB state changed. |
| Ian's revision, T-07-26: both directions confirm, named in plain words, decided from state at press time, submit never does the opposite | VERIFIED | Code: heading `fn => (isOpen ? 'Close ' : 'Reopen ').label.'?'`, description per state, `requiresConfirmation()`, `fillForm` sets a Hidden `direction` from state when the dialog opens, action branches on `$data['direction']`. Tests 'asks before closing and before reopening, naming the job', 'opens the Reopen confirmation when the page still shows Close for a project closed elsewhere, and cancelling changes nothing', 'does what the dialog said when the project changes while it is open' pass. |
| D-21: stock Filament only | VERIFIED | `git diff` for 07-09 touches only `ProjectsRelationManager.php` and two test files under `app/`, `resources/`, `tests/`; no view, CSS, JS string or published Filament file. |
| D-11: relation manager writes nothing itself | VERIFIED | Writes go through `CloseProject`/`ReopenProject`/`RenameProject`/`OpenProject`; `tests/Unit/ProjectWritesTest.php` passes unchanged. |
| D-22: no pre-Phase-7 test changed | VERIFIED | Modified-test list above is exactly the permitted three. |
| Prohibitions (no new text, alert or write path; no custom view/CSS/JS), tier `test` | VERIFIED (enforced) | Write path: `ProjectWritesTest` is wired and green (non-vacuity case present). Custom view/CSS/JS: no such file in the 07-09 diff; no `unverified-prohibition` flag needed. |

### Behavior-dependent invariants (LEAD-05; unchanged since the 2026-10-31 verification, re-run)

| Invariant | Status | Evidence |
|-----------|--------|----------|
| Opens only for an inbound-created contact; after commit, never doubles, never in the inbound's way; room checked at run time; no backfill; D-23 as amended; failed open reports; opening changes no status, text or alert | VERIFIED | `OpenOnNewLeadTest` and neighbours pass in the `tests/Feature/Projects` run (493 passed across Projects, Rooms, Records). No file in `app/Rooms/Leads` or `app/Actions` changed since the first verification. |
| Only four actions write projects (D-11) | VERIFIED | `ProjectWritesTest` passes. |
| Project name quoted on the card (D-13) | VERIFIED | `CardInjectionTest` passes. |

### Required Artifacts

| Artifact | Status | Details |
|----------|--------|---------|
| `ProjectsRelationManager.php` | VERIFIED | Read in full: substantive (non-lazy flag, `toggle`, `rename`, `new`, shared label helpers), wired through `ContactResource::getRelations()` (asserted in test) |
| `ProjectsRelationManagerTest.php`, `ProjectsRelationManagerKeyboardTest.php` | VERIFIED | Present, 22 and 2 cases, executed and passing |
| All artifacts from the first verification | VERIFIED (regression) | Present; no changes outside the three 07-09 files; phase-area tests green |

### Key Link Verification

| From | To | Status | Details |
|------|----|--------|---------|
| `toggle` action | `CloseProject` / `ReopenProject` | WIRED | `action()` branches on the carried direction; tests assert `closed_by_user_id` and `closed_at` |
| `rowButtonName()` | `rowLabel()` (shared with label column) | WIRED | One helper, test asserts column and buttons agree |
| Row button | Browser focus | WIRED | Chromium test proves the same element keeps focus |
| `LeadsServiceProvider` hook, `Validator`, `ActiveConfig::projectWord`, `OpenProject` and `CustomerRecordChanged` | (unchanged) | WIRED | Regression re-run green |

### Data-Flow Trace (Level 4)

| Artifact | Data | Source | Real data | Status |
|----------|------|--------|-----------|--------|
| Contact screen list | rows, `number`, names, aria-labels | `projects` via `withNumber()`; labels from `Project::label` | Yes (first-response test reads real HTML) | FLOWING |
| Card section, dashboard section | projects, word | `projects` table, `ActiveConfig` | Yes | FLOWING |

### Behavioral Spot-Checks

| Behavior | Command | Result | Status |
|----------|---------|--------|--------|
| 07-09 behaviors, D-11 scan, ContactResource | `php artisan test --compact tests/Feature/Filament/ProjectsRelationManagerTest.php tests/Feature/Filament/ProjectsRelationManagerKeyboardTest.php tests/Unit/ProjectWritesTest.php tests/Feature/Filament/ContactResourceTest.php` | 53 passed, 239 assertions | PASS |
| Real-keyboard focus proof | `php artisan test --compact tests/Feature/Filament/ProjectsRelationManagerKeyboardTest.php` | 2 passed, 10 assertions | PASS |
| Phase-area regression | `php artisan test --compact tests/Feature/Projects tests/Feature/Rooms tests/Feature/Records` | 493 passed, 2409 assertions | PASS |
| Formatting | `vendor/bin/pint --test --dirty` | passed | PASS |

Orchestrator gates this run: full suite 2428 passed twice (SQLite); validation nyquist_compliant (20ac26e); security 28/28 closed, threats_open 0 (a5b75f5); UI review 20/24 (b370bb0); code review 0 critical / 0 warning / 3 info (570073a).

### Probe Execution

Step 7c: SKIPPED (no probes declared by the phase).

### Requirements Coverage

| Requirement | Source Plans | Description | Status | Evidence |
|-------------|--------------|-------------|--------|----------|
| PROJ-01 | 07-02 to 07-05, 07-09 | Project ties work to contact and conversation; appointments hang off it | SATISFIED (appointment clause by shape, proof in Phase 8 per D-09) | Table, actions, card, contact screen now keyboard reachable |
| PROJ-02 | 07-01, 07-03, 07-05, 07-09 | One profile field naming the project word; dashboard and card use it; front desk frozen | SATISFIED | Truths 1, 2, 4 |
| ROOM-07 | 07-06, 07-07 | Leads room to the standard, reads lead sources and offers | SATISFIED | Truth 5 |
| LEAD-05 | 07-08 | New lead opens a project beside the inbound path; nothing the front desk does changes | SATISFIED on SQLite; Postgres pending CI | Invariants table |

All four IDs are declared in plan frontmatter and listed in REQUIREMENTS.md (lines 26, 27, 34, 35; traceability rows marked Complete). No orphaned requirements mapped to Phase 7.

### Anti-Patterns Found

No TBD, FIXME or XXX in any phase-touched file; no stubs. Open items are info only and recorded in `07-REVIEW-DISPOSITION.md`.

| Item | Severity | Note |
|------|----------|------|
| IN-05: browser test's two-second waits may flake on a slow CI runner | Info | Surface at WINDOWS 45 |
| IN-06: docblocks cite UAT, threat and plan ids that go stale | Info | Cosmetic |
| IN-07: browser test focuses the button directly, so Tab reach is proven only by the first-response test, not in a browser | Info | Covered by the human retest (WINDOWS 44) |
| Earlier IN-02, IN-03, IN-04; crash-between-commit-and-queue | Info | Unchanged from the first verification |

### Human Verification Required

1. **WINDOWS 44, the UAT test 2 retest.** Keyboard and VoiceOver in Safari on a contact with two projects (details in frontmatter). This decides whether UAT test 2 moves from issue to pass.
2. **WINDOWS 45, CI on both legs after Ian pushes**, which also carries the still-open Postgres rows (34 to 37, 39, 40, 42, 43) that UAT test 1 already recorded as passing, per the ledger's own status.

### Gaps Summary

No code gaps. The three UAT gaps are closed in the code, each by a passing test that reads the real response or drives a real browser, and the first verification's five criteria show no regression. The phase is `human_needed` only because the VoiceOver and Safari retest and the post-push CI run cannot be done by a verifier; neither is a code defect.

---

_Verified: 2026-11-01_
_Verifier: Claude (gsd-verifier)_
