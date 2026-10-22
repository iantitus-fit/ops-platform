---
phase: 04-room-standard
plan: 02
subsystem: icm-sync
tags: [laravel, icm-sync, validator, loader, room-contract, tdd, pest]

requires:
  - phase: 04-room-standard
    provides: "RoomRegistry, Room, the Loader's typed room contract, ActiveConfig::hasRoom() and the pretend-room test helpers (plan 04-01)"
provides:
  - "App\\Icm\\RoomChecks: contractErrors(IcmFile), errors(Collection), roomKeyOf(string) in the Validator error shape"
  - "Validator room arm (owns, reads, writes_records, connector) with RoomChecks constructor-injected"
  - "Loader::NONE_MEANS_EMPTY and emptyWhereNone(): the whole-value word none reads as [] for owns, reads and writes_records"
  - "Pretend room slot: PretendFeed interface, FakePretendFeed singleton, driver chosen from pretend.feed.driver"
  - "docs/SPEC.md rooms/<room>/CONTEXT.md in the folder tree and the room file type row"
affects: [04-03, 04-04, 04-05, 04-06, 05-room-phases, 06-room-phases]

actuals:
  tokens: 9490
  tasks: 3
  commits: 6

tech-stack:
  added: []
  patterns:
    - "Room refusals are plain Validator errors, so the existing reject path keeps the previous version live"
    - "The Loader is the only place the word none is read; everything downstream sees []"
    - "A room slot is an interface plus a fake singleton, bound by a closure that reads config each time (the TextChannel pattern)"

key-files:
  created:
    - app/Icm/RoomChecks.php
    - tests/Feature/Rooms/RoomContractTest.php
    - tests/Rooms/PretendRoom/PretendFeed.php
    - tests/Rooms/PretendRoom/FakePretendFeed.php
  modified:
    - app/Icm/Loader.php
    - app/Icm/Validator.php
    - tests/Rooms/PretendRoom/PretendRoomServiceProvider.php
    - tests/Feature/Rooms/PretendRoomBootTest.php
    - docs/SPEC.md
    - docs/sprints/sprint-1.1.md

key-decisions:
  - "An entry earns at most one error: the inside-the-folder check runs first, so writes_records entry ../x.md gets the path sentence and not also the records sentence"
  - "The missing-contract and unknown-room checks count every loaded file under rooms/<name>/ (typed, router or unrecognized), but README.md files are skipped by the Loader, so a README-only folder changes nothing"
  - "Unknown-room and wrong-type sentences name the folder key as written; registered-room sentences use the room's label"
  - "Backslashes count as path separators in the .. and leading-slash check, so ..\\x is refused the same as ../x"

patterns-established:
  - "RoomChecks holds every room refusal sentence in one class, with owner-written values passed through PlainWords::oneLine() or value()"
  - "A test that lays a room's contract registers the room first with enablePretendRoom(), because an unregistered rooms/<name>/ is refused"

requirements-completed: [ROOM-01, ROOM-03, ROOM-04]

coverage:
  - id: D1
    description: "A room contract missing owns, reads, writes_records or connector, or holding a non-list word there, is refused with a line naming the file and the field; a refusal stores a rejected version and the previous version stays live"
    requirement: ROOM-01
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/RoomContractTest.php#refuses a room contract with a field missing or not a list, naming the file and the field (9 rows)"
        status: pass
      - kind: integration
        ref: "tests/Feature/Rooms/RoomContractTest.php#keeps the previous version live when a later sync finds the contract broken"
        status: pass
    human_judgment: false
  - id: D2
    description: "The word none and [] both load as the same empty list for owns, reads and writes_records; None, nothing and [none] are not the word none"
    requirement: ROOM-01
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/RoomContractTest.php#reads the word none and [] as the same empty list (6 rows) and #reads owns: [none] as a one-entry list, not as the word none"
        status: pass
    human_judgment: false
  - id: D3
    description: "type: room anywhere but rooms/<name>/CONTEXT.md is refused, and another type at a contract path is refused, both on the type field"
    requirement: ROOM-01
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/RoomContractTest.php#refuses a room contract anywhere but rooms/<name>/CONTEXT.md, and any other type at a contract path"
        status: pass
    human_judgment: false
  - id: D4
    description: "Entries in owns, reads and writes_records must be non-empty paths inside the folder; writes_records entries must sit under records/ (records and records/ alone are refused); an empty writes_records or owns passes"
    requirement: ROOM-04
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/RoomContractTest.php#refuses a path that leaves the folder, naming the file and the item (12 rows), #refuses a records statement that is not a path under records/ (4 rows), #accepts paths under records/, an empty owns and an empty writes_records"
        status: pass
    human_judgment: false
  - id: D5
    description: "A registered room's folder with files but no CONTEXT.md is refused at rooms/<key>/CONTEXT.md; a folder for a room the engine lacks is refused once naming the rooms that exist (or no rooms yet); a README-only folder changes nothing"
    requirement: ROOM-01
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/RoomContractTest.php#names the missing contract of a registered room, #refuses a folder for a room this engine lacks, #says there are no rooms yet, #changes nothing for a rooms/<name>/ folder that holds only README files"
        status: pass
    human_judgment: false
  - id: D6
    description: "The pretend room's slot is an interface with a fake singleton driver chosen from config each time; a contract's connector must equal the room's slot, or none for a room with no slot"
    requirement: ROOM-03
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/RoomContractTest.php#gives the pretend room a slot, #picks the pretend feed driver from config each time it is asked, #lets the pretend contract name its own slot and refuses any other connector, #asks a room with no slot to write connector: none"
        status: pass
    human_judgment: false
  - id: D7
    description: "docs/SPEC.md shows rooms/<room>/CONTEXT.md in the folder tree and a room row in the file types table, and sprint-1.1.md's walk-through log has three plain-words lines for the new Laravel concepts"
    requirement: ROOM-01
    verification:
      - kind: other
        ref: "grep -q 'writes_records' docs/SPEC.md and grep -q '(plan 04-02)' docs/sprints/sprint-1.1.md"
        status: pass
    human_judgment: true
    rationale: "Whether the SPEC wording and the walk-through lines are clear to Ian is a judgment no test makes"

duration: 7min
completed: 2026-10-22
status: complete
plan_head_before: c0319150b0d291a9df112570d710baf5d4d1d7f7
plan_head_after: e23249915556a9144c7a1186755e7c93ac497b9a
commits: 6
---

# Phase 4 Plan 02: Room Contract Checks Summary

**The sync now checks a room's contract field by field in plain words (lists or the word `none`, paths inside the folder, records under `records/`, the contract only at `rooms/<name>/CONTEXT.md`, unknown room folders refused, connector equal to the room's slot), and the pretend room has a real `PretendFeed` slot with a fake driver.**

## Performance

- **Duration:** 7 min
- **Started:** 2026-10-22T22:04:06Z
- **Completed:** 2026-10-22T22:11:26Z
- **Tasks:** 3 (Task 1 tracer, Task 2 paths and unknown rooms, Task 3 the slot), 6 commits (RED and GREEN for each)
- **Files modified:** 10 (4 created, 6 modified)

## Accomplishments

- A broken contract is refused with a line such as `rooms/pretend/CONTEXT.md: owns is missing, and it is required`; the refusal rides the Validator's existing report, so the rejected version is stored and the live one stays live (tested with a second sync after a first activation).
- `none` and `[]` are the same empty list, decided in one place: `Loader::NONE_MEANS_EMPTY` swaps the whole-value lower-case word for `[]` before validation and compile. `None`, `nothing` and `[none]` are left as written, so the first two are refused as non-lists and `owns: [none]` compiles as `['none']`.
- Path entries are checked one by one (empty, leading `/` or `\`, any `..` part), and `writes_records` must start with `records/` and have something after it, so `records` and `records/` are refused.
- Folder-wide checks: a registered room whose folder has files but no contract, a folder for a room the engine lacks (two sentence forms, depending on whether any room exists), and a typed non-room file at a contract path.
- The pretend room offers slot `pretend_feed`: `PretendFeed` interface, `FakePretendFeed` singleton that remembers every push, and a bound closure that reads `pretend.feed.driver` each time and throws for any driver but `fake`. The contract's `connector` must equal the room's slot, or `none` for a room with no slot.
- The whole suite passes (1829 tests) and `exampleFolderFileCount()` still returns 14.

## Task Commits

1. **Task 1 RED: room contract fields are required** - `1ded0c5` (test)
2. **Task 1 GREEN: the sync checks a room's contract fields and place** - `38177ca` (feat)
3. **Task 2 RED: room paths, records statement and unknown rooms** - `cfa87bf` (test)
4. **Task 2 GREEN: room paths, records statement, missing contract and unknown room refusals** - `cb0952f` (feat)
5. **Task 3 RED: the pretend slot and the contract's connector field** - `2bfcf84` (test)
6. **Task 3 GREEN: pretend connector slot and the contract's connector check** - `e232499` (feat)

**Plan metadata:** the docs commit that carries this file (docs: complete plan)

## TDD Gate Compliance

All three tasks produced a `test(04-02)` commit before the matching `feat(04-02)` commit. No REFACTOR commit was needed.

**RED evidence** (`gsd_run check tdd-red-evidence` returned `RED_EVIDENCE_OK` for each, JUnit XML from `--log-junit`):
- Task 1: target `it keeps the previous version live when a later sync finds the contract broken`; failed on `Expected status code 1 but received 0`, meaning the sync accepted a contract with `owns` deleted where it must refuse. 14 of 19 tests failed on assertions, the other 5 (the fixture as laid, the `[]` rows and `owns: [none]`) passed, as the plan expected for the rows that already hold. Semantic assessment: the target executed and failed on the planned assertion, not on a load or fixture fault.
- Task 2: target `it refuses a records statement that is not a path under records/` (row: the bare word `records`); failed with exit code 0 where 1 was expected because no path check existed yet. 20 of 44 tests failed on assertions. Semantic assessment: executed and failed for the intended reason.
- Task 3: target `it lets the pretend contract name its own slot and refuses any other connector`; failed with exit code 0 where 1 was expected because the connector was not compared with the slot yet. 2 failed on assertions and 2 errored with the missing `PretendFeed` class, the expected RED for an interface this task adds. Semantic assessment: the target executed and failed on the planned assertion.

**Tracer feedback gate:** Task 1 is `type="tracer"`; the interactive, `end-of-phase`, automated-only `<verify>` re-ran `tests/Feature/Rooms` and `tests/Feature/Icm` (289 tests) with the final Task 1 files, passed, and expansion proceeded.

## Files Created/Modified

- `app/Icm/RoomChecks.php` - every room refusal sentence: placement, wrong type at a contract path, path items, records statement, missing contract, unknown room, connector against slot
- `app/Icm/Validator.php` - constructor takes `RoomChecks`; `room` arm in `rulesFor()` and in the post-check match; whole-folder errors appended
- `app/Icm/Loader.php` - `NONE_MEANS_EMPTY` and `emptyWhereNone()`, called right after the type is known
- `tests/Feature/Rooms/RoomContractTest.php` - 48 tests across the three tasks
- `tests/Rooms/PretendRoom/PretendFeed.php`, `FakePretendFeed.php` - the slot interface and its fake driver
- `tests/Rooms/PretendRoom/PretendRoomServiceProvider.php` - slot name and interface on the Room; the binding in `register()`
- `tests/Feature/Rooms/PretendRoomBootTest.php` - one line added (see Deviations)
- `docs/SPEC.md` - `rooms/<room>/CONTEXT.md` in the folder tree, a paragraph on rooms, and the `room` file type row
- `docs/sprints/sprint-1.1.md` - three walk-through lines (constructor injection, the Loader's one rule for `none`, binding an interface to a closure)

## Decisions Made

- An entry earns at most one error. The inside-the-folder check runs first, so a `writes_records` entry such as `../x.md` or `records/../x.md` gets the path sentence only, not the records sentence as well. The plan's dataset implied this single-line shape.
- Backslash counts as a separator in the `..` and leading-slash check, a small widening of the plan's "split on `/`", so `..\x` is refused like `../x`.
- The unknown-room and wrong-type sentences name the folder key as written; sentences about a registered room use its label (the two are equal for the pretend room).
- `RoomChecks` takes the `RoomRegistry` in its constructor, so the Validator needed no other change and no code calls `new Validator`.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] An existing Pretend room test laid a contract without registering the room**
- **Found during:** Task 2 (GREEN, first run of `tests/Feature/Rooms`)
- **Issue:** `PretendRoomBootTest` "reads only a CONTEXT.md directly inside rooms/<room>/ as a room contract" laid `rooms/pretend/CONTEXT.md` without calling `enablePretendRoom()`. Before this plan an unregistered room folder was accepted; D-07 now refuses it, so the sync returned Rejected instead of Activated. The plan's "no existing test changes" prohibition and D-07 cannot both hold for this test.
- **Fix:** Added `enablePretendRoom();` as the first line of that one test. Nothing else in the test changed, and the test's own claim (only a contract directly inside `rooms/<room>/` is not a router) is unchanged.
- **Files modified:** `tests/Feature/Rooms/PretendRoomBootTest.php`
- **Verification:** `tests/Feature/Rooms`, `tests/Feature/Icm`, `tests/Unit` and the whole suite pass.
- **Committed in:** `cb0952f` (Task 2 GREEN)

**2. [Rule 2 - Missing Critical] Walk-through lines for the new Laravel concepts**
- **Found during:** Task 3
- **Issue:** `CLAUDE.md` requires a plain-words explanation and a walk-through log line the first time a Laravel concept appears; the plan's `files_modified` did not list `docs/sprints/sprint-1.1.md`.
- **Fix:** Appended three lines (constructor injection, the Loader's one rule for the word `none`, binding an interface to a closure).
- **Files modified:** `docs/sprints/sprint-1.1.md`
- **Committed in:** `e232499` (Task 3 GREEN)

---

**Total deviations:** 2 auto-fixed (1 bug in an existing test's setup, 1 missing project-rule requirement)
**Impact on plan:** Both necessary. No scope creep. The test edit is a single added line; no assertion in any existing test changed.

## Issues Encountered

None beyond the deviation above.

## Known Stubs

None. No placeholder values flow to any screen from this plan.

## Human Checks Carried to UAT

None. This plan has no `<human-check>`. The wording of the refusal sentences in `RoomChecks` is the part an owner reads; they are all in one class so they can be reworded together (RESEARCH Open Question 4).

## Concepts Explained for Ian

Each is also one line in the walk-through log at the end of `docs/sprints/sprint-1.1.md`.

- **Constructor injection.** `Validator` now begins with `public function __construct(private RoomChecks $roomChecks) {}`. Nothing calls `new Validator`; `FolderSync` asks Laravel for one, and Laravel reads the constructor, builds a `RoomChecks` (which needs the `RoomRegistry` singleton) and hands it over. Adding a dependency took no change anywhere else.
- **One place that reads a word.** `Loader::NONE_MEANS_EMPTY` lists which fields of which file type may be written as the word `none`, and `emptyWhereNone()` swaps that exact lower-case word for `[]` as the file is read. Everything after the Loader sees an empty list either way and never tests for the word.
- **Binding an interface to a closure.** `bind(PretendFeed::class, fn ...)` says "when someone asks for a `PretendFeed`, run this and use the result". Because it is `bind` and not `singleton`, the closure runs on every ask, so changing `pretend.feed.driver` changes the driver; the fake itself is a `singleton`, so every ask returns the one object that remembers the pushes. This is the same shape as `TextChannel`.

## User Setup Required

None - no external service configuration required.

## Threat Flags

None. The surfaces this plan touches (owner-written paths and connector values) are the ones T-04-05, T-04-06, T-04-07, T-04-08 and T-04-32 already cover, and each has a test: paths (`refuses a path that leaves the folder`), sentences (every owner value passes through `PlainWords`), unknown rooms, connector against slot, and `none` handling.

## Next Phase Readiness

- Plan 04-03 can read `owns` from the compiled contract knowing it is always a list of non-empty paths inside the room's folder, `[]` when written `none`, and `RoomChecks::roomKeyOf()` is available for ownership checks.
- Plan 04-04 adds the connector type's `owns` and `reads` to `Loader::NONE_MEANS_EMPTY` (`'connector' => ['owns', 'reads']`) and moves `connector` out of `LATER_SPRINT`.
- The Loader still does not consult `RoomRegistry::registeredTypes()`, as noted at the end of 04-01; that remains for the plan that wires room-defined file types.
- Plan 04-08 (npm playwright install) is paused at its approval checkpoint; no dependency changed here.

## Self-Check: PASSED

All 4 created files exist on disk, commits `1ded0c5`, `38177ca`, `cfa87bf`, `cb0952f`, `2bfcf84` and `e232499` are on the branch, `check evaluation-scope --plan 04-02` resolved with commits, all task acceptance greps pass, and `tests/Feature/Rooms`, `tests/Feature/Icm`, `tests/Unit` and the whole suite (1829 tests) pass.

---
*Phase: 04-room-standard*
*Completed: 2026-10-22*
