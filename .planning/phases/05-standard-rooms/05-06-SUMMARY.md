---
phase: 05-standard-rooms
plan: 06
subsystem: rooms
tags: [rooms, content-generation, content-brief, single-file-type, icm-sync, filament, axe, pest, tdd]

requires:
  - phase: 04-room-standard
    provides: RoomFileType, RoomRegistry, RoomChecks ownership and placement checks, RoomSectionAxe
  - phase: 05-standard-rooms
    provides: 05-03 and 05-05 real-room shape (provider, section, contract-only folder, boot test); 05-04 hidden-character refusals the single-file check relies on
provides:
  - the content generation room (key content_generation), registered after marketing, optional, with the room's own content_brief file type
  - RoomFileType::singleFileLabel() and the RoomChecks single-file check (D-27) that the money and Google Business Profile rooms reuse
  - an example folder rooms/content_generation/ holding the contract and one fictional brief
  - a dashboard section showing "Content brief: in place" or the platform's empty line naming rooms/content_generation/brief.md
  - SPEC rows: ownership, content_brief file type, content generation as a reader of hours, services, FAQ and offers
  - exampleFolderFileCount() returning 18
affects: [05-07, 05-08, 05-09, marketing, knowledge]

plan_head_before: 0904c81406da147e850bf84f7afa6a03cec249cb
plan_head_after: 86be1108603423aff49f5694d37ba7eab31c3a84

actuals:
  tokens: 9000
  tasks: 3
  commits: 5

tech-stack:
  added: []
  patterns:
    - "A room file type says how many files of it a business keeps through singleFileLabel(): a plain name for one, null for any number"
    - "The single-file check reads the room's folder, not the contract's owns, so editing owns cannot let a business keep two"
    - "A refused second file gets one line and never the unclaimed line on top (one line per problem)"

key-files:
  created:
    - app/Rooms/ContentGeneration/ContentBriefType.php
    - app/Rooms/ContentGeneration/ContentGenerationSection.php
    - app/Rooms/ContentGeneration/ContentGenerationServiceProvider.php
    - examples/example-hvac/rooms/content_generation/CONTEXT.md
    - examples/example-hvac/rooms/content_generation/brief.md
    - tests/Feature/Rooms/ContentGenerationBootTest.php
    - tests/Unit/Icm/SingleFileTypeTest.php
  modified:
    - app/Rooms/RoomFileType.php
    - app/Icm/RoomChecks.php
    - bootstrap/providers.php
    - tests/Pest.php
    - tests/Rooms/PretendRoom/PretendNoteType.php
    - tests/Unit/Rooms/RoomRegistryTest.php
    - docs/SPEC.md
    - docs/sprints/sprint-1.1.md
    - .planning/WINDOWS.md

key-decisions:
  - "The contract owns the exact path rooms/content_generation/brief.md only so the empty line names the file; keeping one brief is the job of the D-27 check"
  - "The single-file check keeps the first file a present contract claims (in path order), else the first in path order, and refuses the rest with field type"
  - "The refusal wording stays the plan's placeholder, 'this business already has a <label> in <kept path>; keep one', until the 2026-10-17 wording pass"

patterns-established:
  - "Pest test-file helpers keep file-unique names (singleFileUnitFile, singleFileUnitType, singleFileUnitRegistry, singleFileUnitContract, singleFileUnitErrors)"
  - "Acceptance greps that look for exact refusal lines need the line written out literally in the test, not built by a helper"

requirements-completed: [ROOM-10]

coverage:
  - id: D1
    description: "Content generation is registered with its content_brief type and boots from icm:sync to /admin with the example brief, section 'Content brief: in place', contract equal to SPEC's row, and exampleFolderFileCount() 18"
    requirement: ROOM-10
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/ContentGenerationBootTest.php#boots the content generation room with data set with its example folder"
        status: pass
    human_judgment: false
  - id: D2
    description: "The room boots empty (brief removed) with exactly 'Content generation: nothing set up yet. Add rooms/content_generation/brief.md to start.', and passes axe at WCAG 2.2 Level AA in both rows on SQLite with no driver guard"
    requirement: ROOM-10
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/ContentGenerationBootTest.php#boots the content generation room with data set empty"
        status: pass
      - kind: automated_ui
        ref: "tests/Feature/Rooms/ContentGenerationBootTest.php (RoomSectionAxe::assertClean in both rows)"
        status: pass
    human_judgment: false
  - id: D3
    description: "A second content brief is refused in one line naming both files under the shipped contract, under a contract edited to own two paths and under one edited to own the folder's *.md, each time keeping the previous version live; two owned paths with one brief present activates"
    requirement: ROOM-10
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/ContentGenerationBootTest.php (4 D-27 cases)"
        status: pass
      - kind: unit
        ref: "tests/Unit/Icm/SingleFileTypeTest.php (6 cases: two owned, owned one kept, pattern, one file, any number, outside the folder)"
        status: pass
    human_judgment: false
  - id: D4
    description: "A brief outside rooms/content_generation/ gets only the placement line, a contract naming a connector is refused, and the room has no slot and no agent file"
    requirement: ROOM-10
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/ContentGenerationBootTest.php (refuses a content brief outside the room folder; refuses a contract that names a connector; has no content generation agent this phase)"
        status: pass
    human_judgment: false
  - id: D5
    description: "The pretend room's two notes still sync together and the registry tests still pass after the interface gained singleFileLabel()"
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/RoomFileTypeTest.php, tests/Unit/Rooms/RoomRegistryTest.php (unchanged assertions)"
        status: pass
    human_judgment: false
  - id: D6
    description: "SPEC gains the ownership row, the content_brief file type row and content generation as a reader of four file rows, and agrees with the contract"
    requirement: ROOM-10
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/SpecTablesTest.php and the boot test's SpecTables::patternsOwnedBy comparison"
        status: pass
    human_judgment: false
  - id: D7
    description: "The content generation section's wording and a keyboard-only and screen reader walk read plainly and work"
    requirement: ROOM-10
    verification: []
    human_judgment: true
    rationale: "Automated axe covers part of WCAG; the keyboard and VoiceOver walk is a hand check recorded for Phase 5 UAT (WINDOWS entry 22), and the wording is a placeholder until the wording pass"

duration: 8min
completed: 2026-10-25
status: complete
---

# Phase 5 Plan 06: Content generation room Summary

**Content generation is the first room with its own file type: an optional room holding one content brief, and, tests first, a check that refuses a second file of any single-file room type wherever the contract's owns points, so a business keeps one brief even after the owner edits the contract.**

## Performance

- **Duration:** 8 min
- **Started:** 2026-10-25T23:47:49Z
- **Completed:** 2026-10-25T23:56:30Z
- **Tasks:** 3 (Task 1 a tracer with tdd, Task 2 tdd)
- **Files modified:** 14 in the plan's list (7 created, 7 changed), plus the sprint walk-through log, WINDOWS.md and this plan's planning files

## Accomplishments

- `ContentGenerationServiceProvider` returns `Room(key: 'content_generation', label: 'content generation', fileTypes: [ContentBriefType], sections: [ContentGenerationSection])`, listed after marketing in `bootstrap/providers.php`. No slot, tables, timeline entries, agent or publish path (D-05, D-14). `Loader::TYPED` still lists exactly the thirteen built-in types; the Loader reads `content_brief` because the room registers it (04 D-19).
- `ContentBriefType`: front matter is only its type, no rules, no checks (D-12). `singleFileLabel()` returns `'content brief'`.
- Example folder: `rooms/content_generation/CONTEXT.md` owns `[rooms/content_generation/brief.md]` (equal to SPEC's row), reads the six D-13 files, writes nothing to records, connector none, and ends `It has no connector and no outside system.` `brief.md` is one fictional brief (who we write for, topics we cover, topics we avoid) with none of the never_say words.
- `ContentGenerationSection` shows `Content brief: in place`, or nothing, so the platform draws `Content generation: nothing set up yet. Add rooms/content_generation/brief.md to start.` The axe check passes in both boot rows.
- D-27: `RoomFileType::singleFileLabel(): ?string` and `RoomChecks::singleFileErrors()`. For each registered room's file types with a non-null label, the files of that type under `rooms/<room>/` are counted; the first a present contract claims (else the first in path order) is kept and each other file gets one line `<file>: this business already has a <label> in <kept>; keep one`, field `type`, and never also the unclaimed line. A file of the type outside the folder gets only the placement line. Money (05-07) and Google Business Profile (05-08) reuse this method.
- `PretendNoteType` and the registry test's anonymous type return `null` (any number); `RoomFileTypeTest`'s two pretend notes still sync, no assertion changed.
- SPEC: ownership row for `rooms/content_generation/brief.md` (owner content generation), the `content_brief` row in the file types table (saying the sync refuses a second content brief), and content generation added as a reader of hours, services, FAQ and offers. `exampleFolderFileCount()` is 18.

## Task Commits

1. **Task 1 RED: content generation boots empty and with its brief** - `57ff612` (test)
2. **Task 1 GREEN: content generation joins the example business** - `2b18177` (feat)
3. **Task 2 RED: a business keeps one content brief wherever owns points (D-27)** - `4938dc3` (test)
4. **Task 2 GREEN: the sync refuses a second file of a single-file room type (D-27)** - `254e5f0` (feat)
5. **Task 3: briefs stay in their room, no connector; SPEC rows** - `86be110` (test)

**Plan metadata:** committed with this SUMMARY (docs).

## TDD Gate Compliance

- **Task 1 RED:** `57ff612`. Target test `boots the content generation room` (both rows) failed: the furnished row on `hasRoom('content_generation')` (`Failed asserting that false is true.`), the empty row earlier on the file count (`16 is identical to 15`, because deleting a brief that did not yet exist removed nothing). Semantic assessment: both fail because the room is not on yet, with no syntax, fixture or load fault. `gsd_run check tdd-red-evidence` was not run because Pest's compact JSON is not one of its supported formats (same as 05-02 and 05-03); RED judged by hand from the runner output.
- **Task 1 GREEN:** `2b18177`; the four verify files (71 tests) pass, and the full suite (2060) passes. Tracer gate: `<verify>` was automated only, so under end-of-phase mode it was re-run end to end and expansion continued.
- **Task 2 RED:** `4938dc3`. Six of twelve target tests failed on the planned behavior: the unit "two owned" and "pattern" cases got `[]` where one or two single-file lines were expected, "owned one is kept" got the unclaimed line `no room lists this solo_note file under owns...` instead of the single-file line, and the three refusal feature cases got the unclaimed line or none. The one-file, any-number, outside-the-folder and two-path-accepted cases passed in RED by design (they pin behavior the sync already had or must keep).
- **Task 2 GREEN:** `254e5f0`; the five verify files (77 tests) and `tests/Feature/Rooms tests/Unit` (569) pass.
- **REFACTOR:** none needed.

## Decisions Made

- The kept file is chosen from `$claimants` (what a present contract validly claims) over the files sorted by path, matching the plan's "first in path order when it owns several, first in path order when it owns none".
- The `errors()` and `ownershipErrors()` docblocks name the new check, as the plan asked.

## Deviations from Plan

None - plan executed exactly as written. Two small notes: the exact-line acceptance greps need the refusal lines written out literally in the boot test, so the first draft's helper that built the line was replaced with literal strings in Task 3's commit; and the sprint walk-through log gained one line for this plan, as CLAUDE.md asks for a new concept (an interface method every class must supply).

## Issues Encountered

None. Final full suite: 2073 passed, 16425 assertions (2060 after Task 1).

## Verification

- Task 1 verify (71 tests), Task 2 verify (77 tests, then 569 for `tests/Feature/Rooms tests/Unit`) and Task 3 verify (37 tests, then the full suite, 2073) all pass.
- Acceptance greps pass: `Loader::TYPED` unchanged, provider line, `return 18;`, brief front matter and no never_say word, contract text, SPEC rows (`| content_brief |`, `refuses a second content brief`, `content generation` count 8), `singleFileLabel` in the interface and in all three implementations, `; keep one` in RoomChecks, `agents/` holds only `front-desk.md` and `README.md`.
- Postgres: no local Postgres. The boot test has no driver guard, so CI's pgsql leg runs it when Ian pushes the branch.

## Hand checks for UAT

- **Keyboard-only and VoiceOver walk of the content generation section on /admin** (D-18), empty and with the brief in place. Not run by the executor. Recorded as an `unrun-verify` entry (id 22) in `.planning/WINDOWS.md`. Nothing was paused for it.
- Wording of the section lines and the `keep one` refusal is a placeholder until the 2026-10-17 wording pass.

## Known Stubs

None.

## Threat Flags

None. T-05-12 (a connector on a room with no slot) and T-05-20 (a contract edited to own a second brief) are mitigated and covered by tests; T-05-11 is accepted because no agent reads the brief this phase.

## Walk-through log

One line added to `docs/sprints/sprint-1.1.md` marked `(plan 05-06)`: an interface method every implementing class must supply, and a check that reads the folder rather than the contract.

## Next Phase Readiness

Ready for 05-07. The money and Google Business Profile rooms declare `singleFileLabel()` on their own file types: a plain name for a type a business keeps one of, `null` otherwise. Any owner `owns` pattern that starts with `*` must be quoted in YAML (finding from 05-02).

## Self-Check: PASSED

- Created files exist: provider, section, file type, contract, brief, boot test, unit test.
- Commits `57ff612`, `2b18177`, `4938dc3`, `254e5f0`, `86be110` are ancestors of HEAD.
- Full suite passes (2073).

---
*Phase: 05-standard-rooms*
*Completed: 2026-10-25*
