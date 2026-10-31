---
phase: 07-the-spine-and-leads
verified: 2026-10-31T18:00:00Z
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
  - app/Actions/OpenProject.php
  - app/Enums/ProjectWord.php
  - app/Icm/Validator.php
  - app/Records/CustomerCard.php
  - app/Rooms/Leads/LeadsSection.php
  - app/Rooms/Leads/LeadsServiceProvider.php
  - app/Rooms/Leads/OpenProjectForNewLead.php
  - database/migrations/2026_10_29_000001_create_projects_table.php
  - examples/example-hvac/rooms/leads/CONTEXT.md
covered_digest: "v3:sha256:dae3fe76caf62f36cc5b21c078084b610ed3692c31507762f6d94e5e0ac1f045"
behavior_unverified: 0
overrides_applied: 0
human_verification:
  - test: "WINDOWS rows 34, 35, 36, 37, 39, 40, 42, 43: after Ian pushes the phase branch, the CI tests workflow's pgsql leg is green for the whole suite"
    expected: "Green on Postgres, in particular ProjectWordTest, ProjectRecordTest ('refuses a second project opened by a new lead for one contact'), ProjectCardTest, CardInjectionTest, ProjectActionsTest, ProjectWritesTest, ProjectsRelationManagerTest, LeadsBootTest, LeadsSectionTest, and OpenOnNewLeadTest ('loses a race without an error and leaves one project')"
    why_human: "Roadmap criterion 4 says the suite is green on both databases. Postgres runs only in CI after Ian pushes; verified here on SQLite only (528 phase-area tests passed, orchestrator's full run 2417 passed)."
  - test: "WINDOWS row 38: keyboard-only and VoiceOver walk of a contact screen with two projects"
    expected: "The list is reachable; New, Close, Reopen and Rename work without a mouse; the New and Close confirmations and the rename form take focus and return it; every button has an accessible name"
    why_human: "CLAUDE.md WCAG 2.2 AA rule: automated checks catch only part of it, and owner screens get a keyboard and screen reader walk at UAT."
  - test: "WINDOWS row 41: keyboard-only and VoiceOver walk of /admin with three open projects"
    expected: "Each row's contact link is reached by Tab, announced with the contact's name, opens the contact screen, and the opened date is read as a date"
    why_human: "Same rule; axe is already clean in LeadsBootTest, but the screen reader walk cannot be automated."
---

# Phase 7: The Spine and Leads Verification Report

**Phase Goal:** Every new lead opens a project, named in the business's own word, that ties the piece of work to its contact and conversation.
**Verified:** 2026-10-31
**Status:** human_needed
**Re-verification:** No, initial verification. Verified against the code at HEAD 35d23d4, after the five review fixes, with D-23 as amended in 07-CONTEXT.md.

## Goal Achievement

### Observable Truths (ROADMAP success criteria)

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | `business/profile.md` has one field naming what the business calls a project; the sync accepts a valid word and refuses a missing or invalid one in plain words naming the file and field, previous version staying live | VERIFIED | `app/Enums/ProjectWord.php` (six words with written plurals, `fromProfile()` tidies case and Unicode spaces). `Validator::businessErrors()` returns "business/profile.md: project_word is missing, and it is required; write one of Job, ..." or "project_word must be one of ... (you wrote X)". `examples/example-hvac/business/profile.md:7` sets `project_word: Job`. `ProjectWordTest` 'refuses a missing or unlisted word and keeps the previous version live' asserts exit 1, the exact line, and `versionId()` unchanged (covers deleted line, empty, spaces, Widget, number, list, Cyrillic look-alike, zero-width character). `ProjectWordSpecTest` fails if SPEC.md and the enum disagree; SPEC.md:268-287 lists the words. |
| 2 | A text from a new number, or a web form lead, opens a project tied to contact and conversation; it shows on the customer card and in the dashboard in the business's word | VERIFIED | `Contact::created` hook in `LeadsServiceProvider::boot()` queues `OpenProjectForNewLead` after commit; `OpenProject::forNewLead()` writes the project with `conversation_id` taken from the arrival message. `OpenOnNewLeadTest` posts through the real fake-text webhook and the real `leads.store` form route and asserts `contact_id`, `conversation_id === Message::sole()->conversation_id`, status still Lead, and the card containing "## Jobs / Job 1: open, opened ... by a new lead". Card: `CustomerCard` section "## <plural>" above the timeline; `ProjectCardTest` 'uses the word the profile sets' proves a Matter business sees "## Matters", "Matter 1" and no "Job". Dashboard: `LeadsSection` (+ `leads-section.blade.php`) uses `projectWord()->plural()` and is asserted on `/admin` in `LeadsBootTest` 'shows an open project on the dashboard'; `LeadsSectionTest` 'uses the business's word' (Matter). Contact screen: `ProjectsRelationManager` tab titled with the plural word. |
| 3 | A project is ready to carry appointments, zero, one or many; appointment records arrive in Phase 8 | VERIFIED (by shape, per D-09) | Migration `2026_10_29_000001_create_projects_table.php` has no appointment column and no status column; only a non-unique `contact_id` index plus the lead-only partial unique index, so a project can take any number of appointments through a link on the appointment. The proof of PROJ-01's appointment clause is Phase 8 criterion 2 (D-09). Not a gap. |
| 4 | Front desk behaves exactly as before; only the permitted existing tests change; suite green on SQLite and Postgres | VERIFIED on SQLite; Postgres routed to human | `git diff` from the phase's first commit shows no change to `app/Ai`, `app/SendRule`, `RecordInbound`, `RunFrontDesk`, `ProcessInbound`, `OutboundSender` or `config`. Existing tests changed: `tests/Pest.php:295` (25 to 26), `FrontDeskBootTest.php:90` (New leads guard), `CardInjectionTest.php` (heading count 4 to 6, plus one added case); all inside the D-22 allowance; no profile fixtures needed editing. Marketing's `CONTEXT.md` sentence updated (D-19). Phase-area run: 528 passed (`tests/Feature/Projects`, `tests/Unit/ProjectWritesTest.php`, ProjectsRelationManagerTest, `tests/Feature/Rooms`, `tests/Feature/Records`). Orchestrator reports 2417 passed on SQLite twice. Postgres not run here (human items above). |
| 5 | The leads room meets the standard (folder, CONTEXT.md, example file, module, boot test) and reads the lead sources and offers marketing owns | VERIFIED | `app/Rooms/Leads/LeadsServiceProvider.php` extends `RoomServiceProvider`, registered at `bootstrap/providers.php:24`. `examples/example-hvac/rooms/leads/CONTEXT.md` has `type: room`, `owns: none`, `reads: [business/lead-sources.md, business/offers/*.md]`, `writes_records: none`, `connector: none`. `LeadsBootTest` covers boot, leaving the room out, the dashboard section and axe. `RoomRegistry` gained the WR-09 guard (both registration orders); `RoomWidgetOwnershipTest` covers it. |

**Score:** 5/5 truths verified (0 present, behavior-unverified)

### Behavior-dependent invariants (LEAD-05 and D-01 to D-03, D-11, D-23 as amended)

| Invariant | Status | Evidence |
|-----------|--------|----------|
| Opens only for an inbound-created contact; known phone, email in another case, made-up contact open nothing | VERIFIED | `OpenOnNewLeadTest` lines 82-130; the job requires a TextReceived or FormReceived arrival activity |
| After commit, never doubles, never in the inbound's way | VERIFIED | `DB::afterCommit` + try/catch + `report()`; contact row lock, `exists()` check, nested savepoint insert catching `UniqueConstraintViolationException`, partial unique index `projects_one_lead_project_per_contact`; tests 'leaves one project ... when retried', 'loses a race', 'never breaks the inbound when the open cannot be queued / throws' |
| Room checked at run time; no backfill | VERIFIED | `hasRoom('leads')` in `queueFor()` and `handle()`; tests 'queues nothing while the leads room is off', 'opens nothing when the room is off by the time the job runs', 'backfills nothing' |
| D-23 as amended: skip only when the arrival message itself was the STOP | VERIFIED | `arrivalWasStop()` matches the OptedOut activity's subject to the arrival activity's subject; tests 'opens nothing for a first text that is STOP', 'opens a project for a first START or HELP', 'opens a project for a lead whose first text was real and who texted STOP before the job ran'. Example CONTEXT.md text agrees. |
| Review IN-01: a failed open reports | VERIFIED | `failed()` calls `report()`; test 'reports an open that fails for good' |
| Only four actions write projects (D-11) | VERIFIED | `tests/Unit/ProjectWritesTest.php` (widened by WR-02, with a non-vacuity case); `OpenProject`, `CloseProject`, `ReopenProject`, `RenameProject` exist |
| Opening never changes contact status, texts or alerts (D-07, and no new alert) | VERIFIED | Test 'keeps the new lead a lead and in New leads, and texts and alerts no one' |
| Project name is quoted/neutralized on the card (D-13) | VERIFIED | `CardInjectionTest` 'quotes a project name that imitates the owner or a heading' |
| Review WR-03: New asks before opening | VERIFIED | `ProjectsRelationManager` New action has a confirmation modal ("Open a new job for this customer?") |

### Required Artifacts

| Artifact | Status | Details |
|----------|--------|---------|
| `app/Enums/ProjectWord.php` | VERIFIED | Substantive; used by Validator, ActiveConfig, CustomerCard, LeadsSection, relation manager |
| `app/Models/Project.php`, migration, `ProjectFactory` | VERIFIED | Table has contact, nullable conversation, name (100), actor and user columns for open and close, no appointment column |
| `app/Actions/{Open,Close,Reopen,Rename}Project.php` | VERIFIED | Real transactional logic with timeline entries; wired from relation manager and job |
| `app/Rooms/Leads/*` (provider, job, section) | VERIFIED | Wired in `bootstrap/providers.php`; real data from `projects` joined to contacts and lead sources |
| `app/Records/CustomerCard.php` project section and timeline types | VERIFIED | Data flows from `projects` table; section omitted when none |
| `ProjectsRelationManager` on ContactResource | VERIFIED | Registered in `ContactResource` |
| `examples/example-hvac/rooms/leads/CONTEXT.md`, profile and marketing edits | VERIFIED | Present and read by the sync (file count 26) |

### Key Link Verification

| From | To | Status | Details |
|------|----|--------|---------|
| `Contact::created` (LeadsServiceProvider::boot) | `OpenProjectForNewLead::queueFor` | WIRED | Hook registered in boot; no edit to RecordInbound |
| `OpenProjectForNewLead::handle` | `OpenProject::forNewLead` | WIRED | Receives the arrival activity, takes conversation id from its message |
| `Validator::businessErrors` | `ProjectWord::fromProfile` | WIRED | Sync rejects and keeps previous version (test) |
| `ActiveConfig::projectWord` | card, contact screen, dashboard section | WIRED | All three read the word at render time |
| `OpenProject` | `CustomerRecordChanged` | WIRED | Card re-rendered after open |

### Data-Flow Trace (Level 4)

| Artifact | Data | Source | Real data | Status |
|----------|------|--------|-----------|--------|
| Customer card project section | projects rows, word | `projects` table, `ActiveConfig` | Yes (test reads the written card file) | FLOWING |
| Leads dashboard section | open projects, source name | `projects`, `contacts.lead_source_key`, `lead-sources.md` | Yes (`LeadsSectionTest`) | FLOWING |
| Contact screen tab | project list | Eloquent relation | Yes | FLOWING |

### Behavioral Spot-Checks

| Behavior | Command | Result | Status |
|----------|---------|--------|--------|
| Phase-area tests | `php artisan test --compact tests/Feature/Projects tests/Unit/ProjectWritesTest.php tests/Feature/Filament/ProjectsRelationManagerTest.php tests/Feature/Rooms tests/Feature/Records` | 528 passed, 2529 assertions | PASS |

### Probe Execution

Step 7c: SKIPPED (no probes declared by the phase).

### Requirements Coverage

| Requirement | Source Plans | Description | Status | Evidence |
|-------------|--------------|-------------|--------|----------|
| PROJ-01 | 07-02 to 07-05 | Project record ties work to contact and conversation; appointments hang off it | SATISFIED (appointment clause by shape, proof is Phase 8 criterion 2, per D-09) | Table, actions, card, contact screen |
| PROJ-02 | 07-01, 07-03, 07-05 | One profile field naming the project word, checked by sync; dashboard and card use it; front desk frozen | SATISFIED | Truth 1 and 2; front desk files untouched |
| ROOM-07 | 07-06, 07-07 | Leads room to the standard, reads lead sources and offers | SATISFIED | Truth 5 |
| LEAD-05 | 07-08 | New lead opens a project beside the inbound path; nothing the front desk does changes | SATISFIED on SQLite; Postgres pending CI | Invariants table |

All four IDs appear in REQUIREMENTS.md (lines 26, 27, 34, 35; traceability table rows 202-206, marked Complete). No orphaned requirements mapped to Phase 7.

### Anti-Patterns Found

None blocking. No TBD, FIXME, XXX, TODO or HACK in any file changed by the phase. No stubs: every action, the job and both screens have real logic and tests.

| Item | Severity | Note |
|------|----------|------|
| Review IN-02 (stale plan references in docblocks), IN-03 (duplicated whitespace regex, unused `$user` parameter) | Info | Open in 07-REVIEW-DISPOSITION.md; cosmetic, no effect on the goal |
| Review IN-04 (dashboard opened time has no time-zone label) | Info | Deferred to the Phase 11 wording pass by Ian |
| A crash between commit and queue push loses the automatic open | Info | Documented in the job's docblock; covered by the owner's New action, Phase 8 booking, and a recorded pre-first-client sweep (BLUEPRINT section 6) |

### Human Verification Required

1. **Postgres CI leg.** Test: after Ian pushes the phase branch, confirm the pgsql leg of the CI tests workflow is green for the whole suite (WINDOWS rows 34, 35, 36, 37, 39, 40, 42, 43). Expected: green, notably the partial unique index test and 'loses a race without an error and leaves one project'. Why human: Postgres runs only in CI; criterion 4 requires both databases.
2. **Keyboard and VoiceOver walk of the contact screen** (row 38). Expected: list reachable, New, Close, Reopen and Rename usable without a mouse, confirmations and rename form manage focus, buttons have accessible names.
3. **Keyboard and VoiceOver walk of /admin with three open projects** (row 41). Expected: each row link is tabbable, announced with the contact's name, and the opened date reads as a date.

### Gaps Summary

No gaps. All five roadmap criteria and the invariants behind LEAD-05 are met in the code at HEAD, including the five post-review fixes and D-23 as amended. The phase is not marked `passed` only because three pre-recorded hand checks (Postgres CI, two screen reader walks) are open by design and have not been run; they are verification items, not code defects.

---

_Verified: 2026-10-31_
_Verifier: Claude (gsd-verifier)_
