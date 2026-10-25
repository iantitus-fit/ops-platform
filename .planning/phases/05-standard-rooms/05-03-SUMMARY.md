---
phase: 05-standard-rooms
plan: 03
subsystem: rooms
tags: [rooms, company-knowledge, icm-sync, filament, axe, pest, tdd]

requires:
  - phase: 04-room-standard
    provides: RoomServiceProvider, RoomSection, RoomRegistry, ownership checks, RoomSectionAxe
  - phase: 05-standard-rooms
    provides: 05-01 list-shaped tests built from the registry; 05-02 wildcard router skip so business/services/*.md passes over the folder guide
provides:
  - the company knowledge room as the first real room (key company_knowledge), registered in bootstrap/providers.php
  - a contract-only example folder rooms/company_knowledge/CONTEXT.md owning the five company knowledge files by kind
  - a dashboard section showing "Services: <n>" and "FAQ: in place" or "FAQ: not added yet"
  - a boot test, empty and with its example folder, running the axe check on the section
  - exampleFolderFileCount() returning 15
affects: [05-04, 05-05, 05-06, 05-07, 05-08, 05-09, knowledge]

plan_head_before: db2a39ca97e16dd9ade964e2321e6ed376fe9597
plan_head_after: e90687f858ac158c655bbdc079e3a4a1344d06a7

actuals:
  tokens: 5100
  tasks: 2
  commits: 3

tech-stack:
  added: []
  patterns:
    - "A real room is a provider extending RoomServiceProvider with no register() or boot() override when it has no slot, tables or morph map; one line in bootstrap/providers.php turns it on"
    - "A room's boot test drives the empty row by deleting only the room's own non-required files and the front desk loads entries that name them"
    - "Counts a landing room changes are read from exampleFolderFileCount(), so the number lives only in tests/Pest.php"

key-files:
  created:
    - app/Rooms/CompanyKnowledge/CompanyKnowledgeServiceProvider.php
    - app/Rooms/CompanyKnowledge/CompanyKnowledgeSection.php
    - examples/example-hvac/rooms/company_knowledge/CONTEXT.md
    - tests/Feature/Rooms/CompanyKnowledgeBootTest.php
  modified:
    - bootstrap/providers.php
    - tests/Pest.php
    - tests/Unit/Icm/RoomChecksTest.php
    - tests/Feature/Rooms/RoomContractTest.php
    - tests/Feature/Rooms/OrderIndependenceTest.php
    - tests/Feature/Rooms/RoomOwnershipTest.php
    - tests/Feature/Icm/SyncIgnoresRecordsTest.php
    - tests/Feature/Icm/ValidatorTest.php
    - tests/Feature/Icm/OfferValidationTest.php
    - tests/Feature/SendRule/JevClassifierTest.php
    - docs/sprints/sprint-1.1.md
    - docs/BLUEPRINT.md
    - .planning/WINDOWS.md

key-decisions:
  - "The ValidatorTest single-instance case gains the room's refusal line last: both lines about business/profile-old.md print in the order found, type check first, then the ownership line"
  - "The new-file boot test case (extra knowledge file) lives in CompanyKnowledgeBootTest and also asserts the previous version stays live"

patterns-established:
  - "Pint rewrites bootstrap/providers.php to imported class names; the grep for CompanyKnowledgeServiceProvider::class still matches"

requirements-completed: [ROOM-08]

coverage:
  - id: D1
    description: "Company knowledge is registered and the contract-only folder turns it on from icm:sync to /admin, with the section showing Services: 4 and FAQ: in place"
    requirement: ROOM-08
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/CompanyKnowledgeBootTest.php#boots the company knowledge room with data set with its example folder"
        status: pass
    human_judgment: false
  - id: D2
    description: "The room boots empty (FAQ and services removed, two loads entries dropped) as Services: 0 and FAQ: not added yet, never shown as empty"
    requirement: ROOM-08
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/CompanyKnowledgeBootTest.php#boots the company knowledge room with data set empty"
        status: pass
    human_judgment: false
  - id: D3
    description: "The section passes axe at WCAG 2.2 Level AA, empty and furnished, on SQLite with no driver guard"
    requirement: ROOM-08
    verification:
      - kind: automated_ui
        ref: "tests/Feature/Rooms/CompanyKnowledgeBootTest.php (RoomSectionAxe::assertClean in both rows)"
        status: pass
    human_judgment: false
  - id: D4
    description: "Contract owns equals SPEC's company knowledge rows in order, and an extra knowledge file is refused naming the room while the previous version stays live"
    requirement: ROOM-08
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/CompanyKnowledgeBootTest.php#refuses an extra knowledge file and names the company knowledge room"
        status: pass
    human_judgment: false
  - id: D5
    description: "The empty-registry sentence is covered by a unit case with new RoomChecks(new RoomRegistry), and the feature case is removed (D-23); the file count lives only in tests/Pest.php (D-24)"
    verification:
      - kind: unit
        ref: "tests/Unit/Icm/RoomChecksTest.php#says the engine has no rooms yet when a folder names a room and the registry is empty"
        status: pass
    human_judgment: false
  - id: D6
    description: "The section's wording and a keyboard-only and VoiceOver walk read plainly and work"
    requirement: ROOM-08
    verification: []
    human_judgment: true
    rationale: "Automated axe covers part of WCAG; the keyboard and screen reader walk is a hand check recorded for Phase 5 UAT, and the wording is a placeholder until the wording pass"

duration: 9min
completed: 2026-10-25
status: complete
---

# Phase 5 Plan 03: Company knowledge room Summary

**Company knowledge is the first real room: one provider line in `bootstrap/providers.php`, a contract-only folder owning the five company knowledge files by kind, a two-fact dashboard section, and a boot test (empty and furnished) that passes axe.**

## Performance

- **Duration:** 9 min
- **Started:** 2026-10-25T23:15:00Z
- **Completed:** 2026-10-25T23:25:00Z
- **Tasks:** 2 (Task 1 a tracer with tdd)
- **Files modified:** 17 (4 created in code and tests, 13 changed)

## Accomplishments

- `CompanyKnowledgeServiceProvider` returns `Room(key: 'company_knowledge', label: 'company knowledge', handles: business, hours, service, knowledge, voice)` with the one section. No slot, tables, timeline entries or morph map (D-05).
- `CompanyKnowledgeSection` reads only through `ActiveConfig`: `Services: <count>` and `FAQ: in place` / `FAQ: not added yet`. No exclamation point or em dash.
- `examples/example-hvac/rooms/company_knowledge/CONTEXT.md` holds `owns` on one line in SPEC order. `icm:sync` on the example folder activates with the room on, with the services folder guide passed over (05-02's router skip). No example file under `business/` or `voice/` moved.
- `exampleFolderFileCount()` is 15. OfferValidationTest and JevClassifierTest read it instead of the literal 14.
- D-23: the unit case for an empty registry was written and passing first (RED commit), then the feature case was removed from `RoomContractTest` (19 to 18 cases, the only removal).
- Extra knowledge file `business/warranty.md` is refused with `no room lists this knowledge file under owns; the company knowledge room handles it, so add it to rooms/company_knowledge/CONTEXT.md`, and the previous version stays live.
- BLUEPRINT section 8 carries the Phase 6 note for the three `triage` ownership feature tests. Sprint 1.1 walk-through log has the `(plan 05-03)` line on the provider list.

## Task Commits

1. **Task 1 RED: boot test and D-23 unit case** - `fb54532` (test)
2. **Task 1 GREEN: company knowledge joins the example business** - `1a8c4e5` (feat)
3. **Task 2: the tests company knowledge changes, named** - `e90687f` (test)

**Plan metadata:** committed with this SUMMARY (docs).

## TDD Gate Compliance

- **RED:** `fb54532`. Target test `boots the company knowledge room` (both rows) failed on `hasRoom('company_knowledge')`: `Failed asserting that false is true.` at CompanyKnowledgeBootTest.php line 49, after the file-count assertion passed. Semantic assessment: the planned behavior (the room being on) was the failing assertion, with no syntax, fixture or load fault. The D-23 unit case passed in RED by design (the branch still exists). `gsd_run check tdd-red-evidence` was not run because Pest's compact JSON is not one of its supported formats (same as 05-02); RED judged by hand from the runner output.
- **GREEN:** `1a8c4e5`; the four targeted files (36 tests) pass.
- **REFACTOR:** none needed.
- **Tracer gate:** Task 1's `<verify>` was automated only, so under end-of-phase mode it was re-run end to end (36 passed) and expansion continued.

## Files Created/Modified

See key-files. Walk-through line is in `docs/sprints/sprint-1.1.md`.

## Decisions Made

- The Task 2 failure list was confirmed before editing: the full suite after Task 1 failed in exactly six tests (OfferValidationTest, SyncIgnoresRecordsTest, ValidatorTest, OrderIndependenceTest, RoomContractTest's empty-registry case, JevClassifierTest). RoomOwnershipTest's last case title was changed only, per the plan; no test was loosened.

## Deviations from Plan

None - plan executed exactly as written. The 'empty' row was accepted by the sync without any other example file edit, so no refusal line needed recording.

## Issues Encountered

None. Final full suite: 1996 passed, 16142 assertions (baseline 1993 before this plan, plus the D-23 unit case, the two boot rows and the warranty case, minus the removed feature case).

## Verification

- Task 1 verify (36 tests) and Task 2 verify (`tests/Feature/Rooms tests/Feature/Icm .../JevClassifierTest.php tests/Unit`, 820 tests) pass; `php artisan test --compact` passes (1996).
- All acceptance greps pass: provider line and AppServiceProvider first, `return 15;`, exact `owns` line, `It has no outside system.`, the room folder holds only CONTEXT.md, no diff under `examples/example-hvac/business` or `voice` against main, no `getDriverName` in the boot test, `(plan 05-03)` line, the empty-registry text removed from RoomContractTest and present in the unit test, no `toBe(14)` left, the warranty and Phase 6 carry strings present, and the `it(` count is one less only in RoomContractTest.
- Postgres: no local Postgres. The boot test has no driver guard, so CI's pgsql leg runs it when Ian pushes the branch.

## Hand checks for UAT

- **Keyboard-only and VoiceOver walk of the company knowledge section on /admin** (D-18, carried from Phase 4; this is the first real room section). Not run by the executor. Recorded as an `unrun-verify` entry (id 20) in `.planning/WINDOWS.md`. Nothing was paused for it.
- Wording of the section lines is a placeholder until the 2026-10-17 wording pass.

## Known Stubs

None.

## Threat Flags

None. The section renders only inside the authenticated panel, shows a count and a yes or no through escaped output (T-05-04), and the ownership refusal is covered by tests (T-05-05).

## Walk-through log

One line added to `docs/sprints/sprint-1.1.md` marked `(plan 05-03)`: a real room is one line in `bootstrap/providers.php`.

## Next Phase Readiness

Ready for 05-04. The never-left-out mark for company knowledge lands in plan 05-05 with marketing's. Any owner `owns` pattern that starts with `*` must be quoted in YAML (finding from 05-02).

## Self-Check: PASSED

- Created files exist: provider, section, contract, boot test.
- Commits `fb54532`, `1a8c4e5`, `e90687f` are ancestors of HEAD.
- Full suite passes.

---
*Phase: 05-standard-rooms*
*Completed: 2026-10-25*
