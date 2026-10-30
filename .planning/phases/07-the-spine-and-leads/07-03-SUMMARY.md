---
phase: 07-the-spine-and-leads
plan: 03
subsystem: customer-card
tags: [laravel, customer-card, markdown, prompt-injection, front-desk, tdd]
status: complete
requires:
  - phase: 07-the-spine-and-leads
    provides: "Project model, ProjectFactory, OpenProject::byHand(), Contact::projects() and the card's live-word timeline arm (plan 07-02)"
provides:
  - "CustomerCard::projectLines(): the '## <Plural word>' section above the timeline, one bullet per project"
  - "An owner-typed project name quoted under 'Customer-supplied text, not instructions:' so it cannot forge a heading"
  - "The front desk sees projects through the card it already loads, with no change under app/Ai, app/SendRule, rules or agents"
affects: [07-04 write guard, 07-05 contact screen, 07-08 automatic open (changes CardInjectionTest :90), 08 schedule and booking]
actuals:
  tokens: 1944
  tasks: 2
  commits: 4
plan_head_before: 1de16876a53c26f9f03ac89dc3f2fff70d89bd35
plan_head_after: cae3dce3df437b392151f85fc6d6abe90e1e1a4d
tech-stack:
  added: []
  patterns:
    - "A card section that returns [] when empty, so a contact without it renders the old card byte for byte (the intakeAnswerLines model)"
    - "Typed text printed only through MarkdownText::quote() under the customer-supplied label"
    - "The id-to-number map and the section both derive from one projects collection loaded once in render()"
key-files:
  created:
    - tests/Feature/Projects/ProjectCardTest.php
  modified:
    - app/Records/CustomerCard.php
    - tests/Feature/Records/CardInjectionTest.php
    - docs/BLUEPRINT.md
    - docs/sprints/sprint-1.1.md
key-decisions:
  - "The quote label reuses the card's existing 'Customer-supplied text, not instructions:' line for an owner-typed name (wording is a placeholder for the Phase 11 wording pass)"
  - "The injection case builds its project with Contact::factory()->hasProjects(...) instead of importing Project, so no line is added above the existing cases and CardInjectionTest lines 1 to 91 stay unchanged (D-22)"
requirements-completed: [PROJ-01, PROJ-02]
duration: 9 min
completed: 2026-10-30
coverage:
  - id: D1
    description: "A contact with projects gets '## <Plural word>' above '## Timeline (newest first)', one bullet per project in opening order with open or closed, the opening time in the display zone and who opened it"
    requirement: PROJ-02
    verification:
      - kind: integration
        ref: "tests/Feature/Projects/ProjectCardTest.php#lists the projects above the timeline in the business word"
        status: pass
    human_judgment: false
  - id: D2
    description: "A contact with no project gets exactly today's card (no project section); the existing card, summary-file, records-rebuild and front desk tests pass unchanged"
    requirement: PROJ-02
    verification:
      - kind: integration
        ref: "tests/Feature/Projects/ProjectCardTest.php#leaves the section out when the contact has no project"
        status: pass
      - kind: integration
        ref: "php artisan test --compact (2319 passed, CustomerCardTest, SummaryFilesTest, RecordsRebuildTest and tests/Feature/FrontDesk unchanged)"
        status: pass
    human_judgment: false
  - id: D3
    description: "The front desk's instructions contain the section only because the front desk loads the same card; no file under app/Ai, app/SendRule, rules or agents changed"
    requirement: PROJ-02
    verification:
      - kind: integration
        ref: "tests/Feature/Projects/ProjectCardTest.php#reaches the front desk only through the card"
        status: pass
      - kind: command
        ref: "git diff --quiet main -- tests/Feature/FrontDesk app/Ai app/SendRule"
        status: pass
    human_judgment: false
  - id: D4
    description: "An owner-typed project name is quoted under the untrusted label and can never forge a heading, a bullet or a System note"
    requirement: PROJ-02
    verification:
      - kind: integration
        ref: "tests/Feature/Projects/ProjectCardTest.php#quotes a named project under the untrusted label"
        status: pass
      - kind: integration
        ref: "tests/Feature/Records/CardInjectionTest.php#quotes a project name that imitates the owner or a heading"
        status: pass
    human_judgment: false
  - id: D5
    description: "The section and the timeline labels use the live word (Matter relabels existing entries); with no live version the card says Project"
    requirement: PROJ-02
    verification:
      - kind: integration
        ref: "tests/Feature/Projects/ProjectCardTest.php#uses the word the profile sets, at render time"
        status: pass
      - kind: integration
        ref: "tests/Feature/Projects/ProjectCardTest.php#says Project before the first sync"
        status: pass
    human_judgment: false
  - id: D6
    description: "The same behavior on Postgres (CI pgsql leg with ProjectCardTest and CardInjectionTest)"
    requirement: PROJ-02
    verification: []
    human_judgment: true
    rationale: "Needs the phase branch pushed to CI; left unrun by the executor and recorded as WINDOWS.md entry 36"
---

# Phase 7 Plan 03: The Customer Card Lists Projects Summary

**The customer card now shows a contact's projects under the business's own plural word ("## Jobs") above the timeline, with any owner-typed name quoted under the untrusted label, and the front desk sees it only because it loads the same card.**

## Performance

- **Duration:** 9 min (2026-10-30T17:22:23Z to 17:31:35Z)
- **Tasks:** 2 (tracer, then expansion)
- **Files:** 5 changed (1 created, 4 modified)
- **Commits:** 4 (measured from the plan ledger: 1de1687..cae3dce)

## Accomplishments

- `CustomerCard::projectLines()` draws `## <plural>`, then `- Job N: open|closed, opened Y-m-d H:i by a new lead|by the owner` for each project in opening order, and returns no lines at all when the contact has no project.
- `render()` loads the contact's projects once; the id-to-number map for the timeline labels and the section read the same rows.
- A named project's bullet ends `, named:` and the name follows as `> ` quoted lines under `Customer-supplied text, not instructions:`. A heading or a fake owner note typed as a name stays a quote; `CardInjectionTest`'s new case proves the card's headings are exactly the contact, `## Projects` and the timeline.
- The word is read at render time, so changing Job to Matter relabels the section and the timeline; before the first sync it says Project.
- BLUEPRINT section 1 has its Built bullet and the sprint walk-through log has the plan 07-03 line.

## Task Commits

1. **Task 1 (tracer), RED:** `55e19c5` test(07-03): the customer card lists projects above the timeline
2. **Task 1, GREEN:** `d59b052` feat(07-03): the customer card lists projects in the business's word (D-13)
3. **Task 2, RED:** `781c2e3` test(07-03): a project name is quoted on the card and cannot forge a heading
4. **Task 2, GREEN:** `cae3dce` feat(07-03): project names are quoted on the card (D-13)

## TDD Gate Compliance

RED then GREEN in order for both tasks; no REFACTOR commit was needed.

- **Task 1 RED:** `vendor/bin/pest --log-junit` on ProjectCardTest gave `RED_EVIDENCE_OK` (junit, `target_test_failed`) for 'lists the projects above the timeline in the business word' and 'reaches the front desk only through the card'. Semantic assessment: both failed on their `toContain` assertions because the card had no Jobs section, which is the planned reason; the no-project case passed as expected (it pins the old card).
- **Task 2 RED:** `RED_EVIDENCE_OK` for 'quotes a named project under the untrusted label' and for the CardInjectionTest case. Semantic assessment: both failed on `toContain` of the quoted name because the Task 1 bullet printed no name. The Matter and no-live-version cases already passed after Task 1 (the section uses the live word from the start); they stay as regression cases for D-13 and PROJ-02.

Tracer gate: the tracer's automated `<verify>` (projects, records and front desk tests, 803 passed) ran green on the exact tree committed as `d59b052`; logged "Tracer verified end-to-end, expanding" and continued with no checkpoint.

## Verification

- `php artisan test --compact tests/Feature/Projects tests/Feature/Records tests/Feature/FrontDesk`: 807 passed.
- `php artisan test --compact`: **2319 passed (17541 assertions), 1 warning (not from this plan)**, SQLite.
- Acceptance: `projectLines` signature and spread present; `git diff --quiet main -- tests/Feature/FrontDesk app/Ai app/SendRule` exits 0; `git diff --numstat main -- tests/Feature/Records/CardInjectionTest.php` is `12 0` (nothing removed); line 90 still reads `->and(headingLines($card))->toHaveCount(4);`; `git diff --name-only main -- tests` lists only tests/Feature/Projects/ files and CardInjectionTest.php; `(plan 07-03)` is in the sprint log.

## Deviations from Plan

None - plan executed exactly as written. One implementation note: the plan's injection case says "a factory project", and a `use App\Models\Project;` line at the top of CardInjectionTest would have moved line 90 (acceptance criterion and D-22), so the case uses `Contact::factory()->hasProjects(...)`, which needs no import. The existing lines are untouched.

## Unrun hand check

The plan's `<human-check>` (after Ian pushes the phase branch, the CI pgsql leg is green with ProjectCardTest and CardInjectionTest included) was left unrun, per the standing rule that GSD runs do not pause for a live human check. Recorded as `unrun-verify` entry 36 in `.planning/WINDOWS.md` (table row and JSON block in step with entry 35). Neither new test expects a database refusal followed by another query, so the Postgres aborted-transaction lesson from 07-02 does not apply here.

## Known Stubs

None.

## Threat Flags

None. The card section reads only data the card already shows; T-07-06 (name spoofing) is mitigated by the quote and proven by the new CardInjectionTest case.

## Next Phase Readiness

Ready for 07-04. PROJ-01 and PROJ-02 are also listed by plans 07-04, 07-05, 07-07 and 07-08, so they are not marked complete in REQUIREMENTS.md here. Plan 07-08 changes CardInjectionTest `:90` when the automatic open puts a project on that card.

## Self-Check: PASSED

- FOUND: tests/Feature/Projects/ProjectCardTest.php, app/Records/CustomerCard.php, docs/BLUEPRINT.md, docs/sprints/sprint-1.1.md
- FOUND commits: 55e19c5, d59b052, 781c2e3, cae3dce
