---
phase: 04-room-standard
plan: 03
subsystem: icm-sync
tags: [laravel, icm-sync, loader, validator, room-ownership, spec-table, tdd, pest]

requires:
  - phase: 04-room-standard
    provides: "RoomRegistry, Room, RoomFileType, the typed room contract and RoomChecks (plans 04-01 and 04-02)"
provides:
  - "Registered room file types are typed files: Loader reads them, Validator applies the room's rules() and checks(), FolderSync compiles each as a list of paths"
  - "ActiveConfig::filesOfType(string $type): a room's files at run time, path-sorted, front matter plus body plus path"
  - "RoomChecks ownership refusals: claim of an unhandled type, house file claim, connector file claim, double claim (file and pattern forms), unclaimed file, file of an absent room, a room's own kind of file outside its folder (two forms)"
  - "Pretend room's pretend_note type and example note; test-only annex room (AnnexRoom) and fixture for two-room refusals"
  - "docs/SPEC.md 'Who owns each file' table, Tests\\Rooms\\SpecTables reader and SpecTablesTest that tie the table to code"
affects: [04-04, 04-05, 04-06, 05-room-phases, 06-room-phases]

actuals:
  tokens: 13708
  tasks: 3
  commits: 6

tech-stack:
  added: []
  patterns:
    - "A room's file type is an object handing the sync its own rules() and checks(); the Validator falls through to it in the default arm"
    - "Every ownership refusal is a plain RoomChecks error computed from the loaded files in path order, so a re-run gives the same lines"
    - "A SPEC table is the map and a test reads it as a string (SpecTables), so SPEC and code cannot drift apart"

key-files:
  created:
    - tests/Rooms/PretendRoom/PretendNoteType.php
    - tests/Rooms/PretendRoom/AnnexRoom.php
    - tests/Rooms/SpecTables.php
    - tests/Fixtures/pretend-room/rooms/pretend/notes/first-note.md
    - tests/Fixtures/annex-room/rooms/annex/CONTEXT.md
    - tests/Fixtures/pretend-room-spec.md
    - tests/Feature/Rooms/RoomFileTypeTest.php
    - tests/Feature/Rooms/RoomOwnershipTest.php
    - tests/Feature/Rooms/SpecTablesTest.php
  modified:
    - app/Icm/Loader.php
    - app/Icm/Validator.php
    - app/Icm/FolderSync.php
    - app/Icm/ActiveConfig.php
    - app/Icm/RoomChecks.php
    - tests/Rooms/PretendRoom/PretendRoomServiceProvider.php
    - docs/SPEC.md
    - docs/BLUEPRINT.md
    - docs/sprints/sprint-1.1.md

key-decisions:
  - "A pretend_note in the compiled index is a list of paths (index['pretend_note'][]), never the singleton default arm, so two notes can coexist (RESEARCH Pitfall 2)"
  - "Ownership checks treat a room as present when its contract loads as a typed room file, whether or not the contract's fields are valid, so one broken contract does not also read as an absent room"
  - "A file that earns the D-04 placement line is skipped by the unclaimed and absent-room checks, so each misplaced file gets exactly one line"
  - "The same owns entry in two contracts is refused by name only when no loaded file matches it; when a file matches, the file form covers it"
  - "The SPEC row for CONTEXT.md files is the pattern *CONTEXT.md owned by the house, with the rooms/*/CONTEXT.md row naming the room's own contract; the sentence under the table says the house owns every CONTEXT.md outside rooms/"

patterns-established:
  - "RoomChecks holds every ownership and placement sentence beside the contract sentences, so wording changes in one class"
  - "A boot-test comparison for a room is SpecTables::patternsOwnedBy(spec, room) equal to the live contract's owns"

requirements-completed: [ROOM-01, ROOM-02]

coverage:
  - id: D1
    description: "The pretend room's pretend_note example is read as a typed file, validated by the room's own rules and checks, compiled into the live version as a list of paths, and served through ActiveConfig::filesOfType('pretend_note'); file_count is exampleFolderFileCount() + 2"
    requirement: ROOM-02
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/RoomFileTypeTest.php#reads the pretend room's example note, compiles it and serves it at run time, #keeps a list of paths for a type that more than one file has, #lets an unpinned note have no words"
        status: pass
    human_judgment: false
  - id: D2
    description: "A broken example note (pinned: yes, title or pinned missing, a pinned note with no words) is refused with a plain-words line naming the file and the field, and the previous version stays live; the unknown-type line lists the room's types only while the room is registered"
    requirement: ROOM-02
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/RoomFileTypeTest.php#refuses a broken example note in plain words and keeps the previous version live (3 rows), #refuses a pinned note with nothing under its front matter, #adds the room's file types to the sentence that lists the ones the sync knows, #does not know the pretend note type while the pretend room is not registered"
        status: pass
    human_judgment: false
  - id: D3
    description: "A contract may claim only files of the types its room handles: a claim of knowledge by a room that does not handle it names the room that does (or says none yet); routers, walk tests and connector files cannot be claimed; a router inside the claiming room's own folder is the room's"
    requirement: ROOM-01
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/RoomOwnershipTest.php#refuses a claim of a file the claiming room does not handle, naming the room that does, #refuses a claim of a file no room handles yet, #refuses a claim of the house's files and of a connector file (4 rows), #treats a router inside the claiming room's own folder as the room's, not the house's"
        status: pass
    human_judgment: false
  - id: D4
    description: "Two contracts claiming one file, or the same owns entry before any file matches it, are refused on the later contract in path order; a file nobody lists while a room that handles its type is here is refused; a file whose only handling rooms are absent is refused with the add-or-remove line; the previous version stays live and a re-run gives the same lines"
    requirement: ROOM-01
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/RoomOwnershipTest.php#refuses a file two rooms both handle and both claim, #refuses the same owns entry in two contracts even when no file matches it yet, #refuses a file nobody lists under owns, #names the room a file belongs to when the business folder does not have that room, #keeps the previous version live when the folder is refused for its owners, #gives the same lines in the same order"
        status: pass
    human_judgment: false
  - id: D5
    description: "A room's own kind of file must sit under rooms/<room>/: anywhere else (another room's folder included) it gets exactly one line, the move-it line while that room's folder is in the business folder and one line offering both ways out when it is absent; today's kinds of file (business/faq.md) get no placement line; a file inside a room folder with no contract gets only the missing-contract line plus the move-it line"
    requirement: ROOM-02
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/RoomOwnershipTest.php#keeps a room's own kind of file in its folder, and leaves today's kinds of file where they are, #refuses a room's own kind of file inside another room's folder, #offers both ways out for a room's own kind of file when that room's folder is not in the business folder, #says only the missing contract and the move-it line when the room's folder is there without its contract, #passes the example folder untouched when no room handles any of its files"
        status: pass
    human_judgment: false
  - id: D6
    description: "docs/SPEC.md has a 'Who owns each file' table with one backticked pattern per row for every file in the D-12 table (rules/sending.md owned by the front desk, not split), SpecTablesTest fails if a row is dropped, an owner is outside the floor plan or the house, or a file of the example business is unmatched, and the comparison a room's boot test uses is proven on the pretend room against a SPEC-shaped fixture; BLUEPRINT links to the table"
    requirement: ROOM-01
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/SpecTablesTest.php (24 tests)"
        status: pass
    human_judgment: false
  - id: D7
    description: "The new refusal sentences in RoomChecks, the SPEC table prose and the Str::is walk-through line read clearly to an owner"
    requirement: ROOM-01
    verification: []
    human_judgment: true
    rationale: "The placement and ownership sentences are placeholder wording in the style of Ian's accepted refusals; whether they and the SPEC and walk-through text are clear to Ian is a judgment no test makes"

duration: 10min
completed: 2026-10-23
status: complete
plan_head_before: a3dcb19afc1badcebbc1f482a83b509ffdc71e83
plan_head_after: 5e443fae245dc0b0e1c11207f9deb5ade28845b1
commits: 6
---

# Phase 4 Plan 03: File Types and One Owner per File Summary

**A room now adds its own kind of file to the sync (the pretend room's `pretend_note` is read, validated by the room's rules, compiled and served through `ActiveConfig::filesOfType()`), every file has exactly one owner enforced in plain words (claims limited to what a room handles, double claims, unclaimed files, files of an absent room, and a room's own file outside its folder), and the D-12 ownership table sits in SPEC tied to the code by a test.**

## Performance

- **Duration:** 10 min
- **Started:** 2026-10-23T00:52:46Z
- **Completed:** 2026-10-23T01:03:18Z
- **Tasks:** 3 (Task 1 tracer, Task 2 ownership and placement, Task 3 SPEC table), 6 commits (RED and GREEN for each)
- **Files modified:** 18 (9 created, 9 modified)

## Accomplishments

- The tracer path works end to end: `rooms/pretend/notes/first-note.md` (`type: pretend_note`) is a typed file because the Loader asks `RoomRegistry::registeredTypes()`; the Validator takes its rules from the room (`pinned: yes` is refused as `rooms/pretend/notes/first-note.md: pinned must be true or false (you wrote yes)`) and its own check; FolderSync compiles it into `index['pretend_note']` as a list; the broken example is refused with the previous version live.
- The unknown-type sentence lists the built-in types and then every registered room type, so with the pretend room registered it ends `, pretend_note (you wrote recipe)`.
- Ownership is enforced as each room lands (D-15): a contract may claim only types its room handles (D-17), never the house's files (routers outside its own folder, walk tests) or a connector file (D-11, D-25); two contracts claiming one file, or the same owns entry before any file matches it, are refused on the later contract; a file nobody lists while its handling room is here, and a file whose handling rooms are all absent, are refused with the accepted sentences (ROOM-01, D-10).
- D-04 placement is enforced for room-registered types only: a room's own kind of file outside `rooms/<room>/` (another room's folder included) gets one line, `this is a pretend file, which belongs in rooms/pretend/; move it there`, or, when the room's folder is absent, one line offering both ways out (Ian's 2026-10-22 change). Today's types stay where they are, and the example folder with no rooms still activates untouched.
- `docs/SPEC.md` has `### Who owns each file` beside the folder tree with all 17 D-12 rows; `SpecTablesTest` ties it to code (rows, owners, the example business, and the boot-test comparison on a SPEC-shaped fixture); BLUEPRINT links to it.
- The whole suite passes (1883 tests, up from 1829) and `exampleFolderFileCount()` still returns 14.

## Task Commits

1. **Task 1 RED: a room's own file type goes through the sync** - `8f0b2db` (test)
2. **Task 1 GREEN: rooms register their own file types with the sync** - `2cee1c8` (feat)
3. **Task 2 RED: one owner per file, and a room's own files sit in its folder** - `e079699` (test)
4. **Task 2 GREEN: one owner per file, and a room's own files in its folder** - `effcbcd` (feat)
5. **Task 3 RED: SPEC ownership table matches the code** - `3e8b4c7` (test)
6. **Task 3 GREEN: ownership table in SPEC, tied to the code** - `5e443fa` (docs, the commit type the plan names)

**Plan metadata:** the docs commit that carries this file (docs: complete plan)

## TDD Gate Compliance

All three tasks produced a `test(04-03)` commit before the matching GREEN commit. Task 3's GREEN commit is typed `docs(04-03)`, as the plan specifies, because it is mostly SPEC and BLUEPRINT text plus the test-support class, so the `feat(04-03)` grep finds two GREEN commits, not three. No REFACTOR commit was needed.

**RED evidence** (`gsd_run check tdd-red-evidence` returned `RED_EVIDENCE_OK` for each, JUnit XML from `--log-junit`):
- Task 1: target `it adds the room's file types to the sentence that lists the ones the sync knows`; failed on the planned assertion (the line ended `, room (you wrote recipe)` without `pretend_note`). 9 of 10 tests failed on assertions; `does not know the pretend note type while the pretend room is not registered` passed, as expected for a case that already holds. Semantic assessment: the target executed and failed for the intended reason; the other failures are the sync refusing the example note as an unknown type, and `filesOfType()` not existing yet.
- Task 2: target `it says only the missing contract and the move-it line when the room's folder is there without its contract`; failed on array identity because the move-it line was absent. 17 of 20 failed on assertions (mostly `Expected status code 1 but received 0`, the sync accepting what it must refuse); the 3 that passed are cases that already hold (both rooms laid cleanly, an own-folder router, the example folder). Semantic assessment: executed and failed on the planned assertion.
- Task 3: target `it reads only the rows under the heading, up to the next heading`; failed because the `SpecTables` shell returned empty lists. All 24 failed on assertions. Semantic assessment: executed and failed for the intended reason. `SpecTables` was committed with method signatures returning `[]` rather than as an empty class, so the RED failures are assertions and not "undefined method" errors.

**Tracer feedback gate:** Task 1 is `type="tracer"`; the interactive, `end-of-phase`, automated-only `<verify>` re-ran `tests/Feature/Rooms` and `tests/Feature/Icm` (328 tests) with the final Task 1 files, passed, and expansion proceeded.

## Files Created/Modified

- `app/Icm/Loader.php` - constructor takes `RoomRegistry`; a registered room type is `IcmFileKind::Typed`
- `app/Icm/Validator.php` - constructor takes `RoomRegistry`; default arms take the room type's `rules()` and `checks()`; the unknown-type sentence lists room types
- `app/Icm/FolderSync.php` - constructor takes `RoomRegistry`; a registered type is appended to `index[<type>][]` before the match
- `app/Icm/ActiveConfig.php` - `filesOfType(string $type)`
- `app/Icm/RoomChecks.php` - claims, double claims, unclaimed, absent-room and placement checks; private `roomDefiningType()`
- `tests/Rooms/PretendRoom/PretendNoteType.php`, `PretendRoomServiceProvider.php` - the pretend room's new type (title, pinned, a pinned note needs words)
- `tests/Rooms/PretendRoom/AnnexRoom.php`, `tests/Fixtures/annex-room/rooms/annex/CONTEXT.md` - the test-only second room
- `tests/Fixtures/pretend-room/rooms/pretend/notes/first-note.md` - the pretend room's one fictional example file (D-05)
- `tests/Rooms/SpecTables.php`, `tests/Fixtures/pretend-room-spec.md` - the SPEC table reader and its fixture
- `tests/Feature/Rooms/RoomFileTypeTest.php`, `RoomOwnershipTest.php`, `SpecTablesTest.php` - 10, 20 and 24 tests
- `docs/SPEC.md` - the placement sentence in the rooms paragraph and the `Who owns each file` table with its prose
- `docs/BLUEPRINT.md` - the room standard bullet links to the SPEC table
- `docs/sprints/sprint-1.1.md` - one walk-through line (`Str::is`, and which contract is blamed)

## Decisions Made

- The compiled index keeps each registered type as a list of paths, so two files of one room type coexist; `filesOfType()` sorts by path and tolerates a version compiled before the type existed.
- A room counts as present when its contract loads as a typed room file, even with field errors, so a single broken contract produces its own field lines and no "absent room" lines on top.
- A file with a placement line is excluded from the unclaimed and absent-room checks, so it never gets a second line.
- The identical-entry check (before any file exists) fires only when no loaded file matches the entry, so it never doubles up with the file form.
- The SPEC row for folder CONTEXT.md files is the pattern `*CONTEXT.md` (the plan's pattern), with a note in the owner cell that it means every CONTEXT.md outside `rooms/`; the `rooms/*/CONTEXT.md` row is the room's own contract.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Missing Critical] RED-step stubs so tests fail on assertions**
- **Found during:** Task 3 (RED)
- **Issue:** The plan says to create `SpecTables` as an empty class shell in RED; with no methods every test would error with "undefined method", which is a weaker RED than an assertion failure (tdd.md INVALID_RED guidance).
- **Fix:** Committed the shell with the three method signatures returning `[]`; GREEN replaced the bodies.
- **Files modified:** `tests/Rooms/SpecTables.php`
- **Committed in:** `3e8b4c7` (RED), `5e443fa` (GREEN)

**2. [Rule 2 - Missing Critical] A decoy row in the SPEC fixture and extra tests**
- **Found during:** Task 3 (RED)
- **Issue:** A fixture with only the pretend row cannot prove the parser stops at the next heading, and nothing asserted one pattern per row or the BLUEPRINT link.
- **Fix:** `tests/Fixtures/pretend-room-spec.md` also holds a row under a later heading that must not be read; SpecTablesTest adds the one-pattern-per-row, BLUEPRINT-link and `rules/sending.md`-not-split tests.
- **Files modified:** `tests/Fixtures/pretend-room-spec.md`, `tests/Feature/Rooms/SpecTablesTest.php`
- **Committed in:** `3e8b4c7`

---

**Total deviations:** 2 auto-fixed (2 missing critical, both test-strength)
**Impact on plan:** Both strengthen the proof the plan asks for. No scope creep; no app behavior beyond the plan.

## Issues Encountered

None. The plan's AnnexRoom, fixture and test file are listed under both the Task 2 RED and GREEN steps; they were created once, in the RED commit, because the RED tests need them to fail on assertions.

## Known Stubs

None. No placeholder values flow anywhere from this plan.

## Human Checks Carried to UAT

None. This plan has no `<human-check>`. The wording of the new ownership and placement sentences is the part an owner reads; they all live in `RoomChecks` so they can be reworded together (coverage D7).

## Concepts Explained for Ian

Constructor injection is the same idea as in plan 04-02: `Loader`, `Validator` and `FolderSync` each now start with a `RoomRegistry` in their constructor, and Laravel builds and hands it over because nobody writes `new Loader`. The new concept is one line in the walk-through log at the end of `docs/sprints/sprint-1.1.md`:

- **Matching a path against an owns pattern.** `Str::is('rooms/pretend/notes/*.md', $path)` asks "does this text fit this pattern", where `*` stands for any run of characters (slashes included) and everything else must match exactly. Each contract's owns entries are tried against every loaded file this way. When two contracts claim one file the checks go through the contracts in path order, so the earlier is the first owner and the later one is blamed, which is also why the same folder gives the same lines every time.

## User Setup Required

None - no external service configuration required.

## Threat Flags

None. The new surfaces are the ones the plan's threat model names: T-04-09 and T-04-10 (claims and absent rooms, refused in `RoomChecks`), T-04-11 (room-supplied rules still go through Laravel's validator and PlainWords), T-04-12 (the cost is files times patterns inside the existing sync lock) and T-04-33 (placement, tested in `RoomOwnershipTest`). `RoomChecks` messages that carry owner-written values reuse the paths the Loader already reported; no new network, auth or file-access path was added.

## Next Phase Readiness

- Plan 04-04 can add the connector type: `Loader::LATER_SPRINT` still lists `connector`, and `RoomChecks` already treats a connector file by type, so moving it to `TYPED` keeps the "leave it out of owns" refusal working. `SpecTables` is where the kind lists go.
- A real room's boot test (Phase 5) can compare its contract's `owns` with `SpecTables::patternsOwnedBy(docs/SPEC.md, '<room>')`.
- No room handles `agent` (Ian's 2026-10-22 decision 4), so `agents/*.md` passes untouched until the front desk room's phase.
- STATE.md's "Plan: N of 9" counter was not hand-edited, as instructed.

## Self-Check: PASSED

All 9 created files exist on disk, commits `8f0b2db`, `2cee1c8`, `e079699`, `effcbcd`, `3e8b4c7` and `5e443fa` are on the branch, `check evaluation-scope --plan 04-03` resolved with commits, every task acceptance grep passes (`registeredTypes` in Loader and FolderSync, `function filesOfType`, the RoomChecks sentences, `refuses one anywhere else` in SPEC, `(plan 04-03)` in the sprint log, `### Who owns each file`, `SPEC.md#who-owns-each-file`), `git status --porcelain examples/` is empty, `return 14;` is still in `tests/Pest.php`, and `tests/Feature/Rooms`, `tests/Feature/Icm`, `tests/Unit` and the whole suite (1883 tests) pass.

---
*Phase: 04-room-standard*
*Completed: 2026-10-23*
