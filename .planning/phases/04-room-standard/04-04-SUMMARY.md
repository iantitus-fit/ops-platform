---
phase: 04-room-standard
plan: 04
subsystem: icm-sync
tags: [laravel, icm-sync, connector, owns-reads, kinds-of-record, one-owner-per-fact, spec-table, tdd, pest]

requires:
  - phase: 04-room-standard
    provides: "RoomRegistry with roomWithSlot() and slots(), the room contract's NONE_MEANS_EMPTY rule, RoomChecks present-room logic, SpecTables and the SPEC ownership table (plans 04-01 to 04-03)"
provides:
  - "Connector files are a checked type: slot, owns and reads are required, system, direction and schedule pass through unchecked until CONN-01 (D-24)"
  - "owns and reads accept the word none as well as [], through the room contract's own Loader rule"
  - "OwnableKind (eight kinds, D-22) and PlatformKind (projects, texts, timeline, D-23) enums, and ConnectorChecks refusing objects, platform kinds under owns, unknown or repeated kinds, and a kind in both lists"
  - "Slot checks: a slot no room has (two forms), a slot whose room is not in the folder, a slot already filled; a room with a slot and no connector file stays valid (D-25)"
  - "Two connector files owning one kind are refused with the success-criterion sentence, blaming the later file in path order, previous version stays live (D-26)"
  - "docs/SPEC.md: both kind lists beside the ownership table, tied to the enums by SpecTablesTest; connector row and folder tree updated; BLUEPRINT links to the lists"
affects: [04-05, 04-06, 05-room-phases, 06-room-phases, connectors-and-data]

actuals:
  tokens: 9472
  tasks: 3
  commits: 6

tech-stack:
  added: []
  patterns:
    - "A fixed list of allowed words is a string-backed enum (OwnableKind, PlatformKind) with values(); tryFrom() asks whether an owner-written word is on it"
    - "A file type's refusal sentences live in one checks class (ConnectorChecks beside RoomChecks), per-file in fileErrors() and whole-folder in folderErrors(), both run inside the locked sync before activation"
    - "A SPEC kind table is read as a string by SpecTables and compared with the enum in a test, the same pattern as the ownership table"

key-files:
  created:
    - app/Enums/OwnableKind.php
    - app/Enums/PlatformKind.php
    - app/Icm/ConnectorChecks.php
    - tests/Feature/Rooms/ConnectorFileTest.php
    - tests/Fixtures/pretend-room/connectors/pretend-feed.md
    - tests/Fixtures/connector-conflict/connectors/a.md
    - tests/Fixtures/connector-conflict/connectors/b.md
  modified:
    - app/Icm/Loader.php
    - app/Icm/Validator.php
    - app/Icm/FolderSync.php
    - app/Icm/RoomChecks.php
    - tests/Feature/Icm/ValidatorTest.php
    - tests/Feature/Rooms/RoomFileTypeTest.php
    - tests/Feature/Rooms/SpecTablesTest.php
    - tests/Rooms/SpecTables.php
    - docs/SPEC.md
    - docs/BLUEPRINT.md
    - docs/sprints/sprint-1.1.md
    - examples/example-hvac/connectors/README.md

key-decisions:
  - "A connector file counts toward the two-owner check only when every owns entry is a kind a connector may own, so a file already refused for its kinds (owns [customers, texts]) never blames another file for owning customers"
  - "A kind written twice, or a word that is no kind, is reported once per distinct value, not once per position"
  - "The kind-in-both-lists refusal sits on field reads and covers only OwnableKind values, so a platform kind in owns gets its own sentence and no second line"
  - "RoomChecks::isPresent() is the one public answer to 'is this room here'; ConnectorChecks asks it instead of copying the rule"

patterns-established:
  - "Phase 4 refusal sentences are written once in a *Checks class and pinned by exact-string tests, with an owner-written value always passed through PlainWords::oneLine()"
  - "A plan's type-list sentence appears in two Phase 4 test files (ValidatorTest and RoomFileTypeTest); a new typed file type changes both"

requirements-completed: [CONN-07, ROOM-03]

coverage:
  - id: D1
    description: "A connector file is read as a typed file, requires slot, owns and reads (a missing one is refused naming the file and the field), passes system, direction and schedule through unchecked, and is compiled into the live version's connector index as a list of paths"
    requirement: CONN-07
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/ConnectorFileTest.php#checks one connector file and compiles it into the live version, #refuses a connector file with slot, owns or reads missing (3 rows), #passes system, direction and schedule through unchecked until CONN-01 (6 rows)"
        status: pass
    human_judgment: false
  - id: D2
    description: "owns and reads accept none and [] as the same empty list through Loader::NONE_MEANS_EMPTY; owns written as a bare word is refused with the list sentence; [none] as a list entry is an unknown kind"
    requirement: CONN-07
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/ConnectorFileTest.php#reads none and [] as the same empty list in owns and reads (4 rows), #refuses owns written as a word that is not a list, #refuses a kind in owns or reads that is not allowed there (none as a one-entry list row)"
        status: pass
    human_judgment: false
  - id: D3
    description: "owns holds only the eight ownable kinds: texts, projects and timeline are refused under owns with the platform-owns sentence, unknown words (conversations, none, drafts under reads) and repeated or doubly listed kinds are refused, invoices is accepted, and objects is refused with the use-owns-and-reads sentence"
    requirement: CONN-07
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/ConnectorFileTest.php#refuses a kind in owns or reads that is not allowed there (11 rows), #accepts a kind the platform keeps no records of yet, #refuses a connector file that still says objects"
        status: pass
    human_judgment: false
  - id: D4
    description: "A connector file's slot links it to its room: an unknown slot (slots listed, or the engine has none yet, or slot: none), a slot whose room is not in the folder, and a second file in one slot are each refused with one sentence; a room with a slot and no connector file is valid"
    requirement: ROOM-03
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/ConnectorFileTest.php#refuses a slot that no room has, #says the engine has no connector slots yet, #treats slot: none as a slot name no room has, #refuses a connector file for a slot whose room is not in the folder, #refuses a second connector file filling a slot that is already filled, #leaves a room with a slot and no connector file as a valid empty room, #shows an owner-written slot on one line"
        status: pass
    human_judgment: false
  - id: D5
    description: "Two connector files owning the same kind are refused with exactly 'connectors/b.md: customers is owned by both connectors/a.md and connectors/b.md; pick one, the other can read it', each later file is blamed in path order, and the previous version stays live"
    requirement: CONN-07
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/ConnectorFileTest.php#refuses two connector files that own the same kind with the exact sentence, and keeps the previous version live, #blames each later file that owns a kind an earlier one owns, #lets two connector files own different kinds, #does not count a connector file whose owns already failed the kind check as an owner"
        status: pass
    human_judgment: false
  - id: D6
    description: "docs/SPEC.md lists exactly the OwnableKind and PlatformKind values under 'Kinds of record a connector may own' and 'Kinds of record the platform owns', no kind is on both lists, and SpecTablesTest fails if SPEC and the enums disagree"
    requirement: ROOM-03
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/SpecTablesTest.php#lists in SPEC exactly the kinds a connector may own, #lists in SPEC exactly the kinds the platform owns, #never puts one kind of record on both lists, #reads the kinds of record from the two kind tables"
        status: pass
    human_judgment: false
  - id: D7
    description: "The new connector refusal sentences, the two SPEC kind lists with their prose, and the rewritten example connectors README read clearly to an owner"
    requirement: CONN-07
    verification: []
    human_judgment: true
    rationale: "The sentences are placeholder wording in the style of Ian's accepted refusals and the SPEC and README prose is new; whether they are clear to Ian is a judgment no test makes"

duration: 9min
completed: 2026-10-23
status: complete
plan_head_before: 30c059ac125d14b69e0cbf813d5817f4b5505ed7
plan_head_after: 01eea35bbec26fa09b9b842245b00e3e5b9f0416
commits: 6
---

# Phase 4 Plan 04: Connector Files and One Owner per Kind of Record Summary

**Connector files are now a checked type: `slot`, `owns` and `reads` are required, `owns` may hold only the eight `OwnableKind` values and never the platform's `texts`, `projects` or `timeline`, a slot ties each file to the room that fills it, and two files owning one kind are refused with "customers is owned by both connectors/a.md and connectors/b.md; pick one, the other can read it" while the previous version stays live.**

## Performance

- **Duration:** 9 min
- **Started:** 2026-10-23T01:07:55Z
- **Completed:** 2026-10-23T01:17:00Z
- **Tasks:** 3 (Task 1 tracer, Task 2 kind lists, Task 3 slots and two owners), 6 commits (RED and GREEN for each)
- **Files modified:** 19 (7 created, 12 modified)

## Accomplishments

- The tracer path works end to end: `connectors/pretend-feed.md` (`type: connector`) is no longer skipped with a "not supported yet" notice; the Loader types it, the Validator requires `slot`, `owns` and `reads` (a missing one is `connectors/pretend-feed.md: owns is missing, and it is required`), `FolderSync` compiles it into `index['connector']` as a list of paths, and `system`, `direction` and `schedule` pass through with any value (D-24).
- `owns` and `reads` accept `none` and `[]` through the same `Loader::NONE_MEANS_EMPTY` constant the room contract uses; both load as `[]`, `owns: customers` is refused with the list sentence, and `slot` is deliberately not listed so `slot: none` is an unknown slot (T-04-34).
- `OwnableKind` (customers, sales_activity, invoices, payments, expenses, work_orders, schedule, documents) and `PlatformKind` (projects, texts, timeline) are the two lists in code; `ConnectorChecks` refuses `objects` (CONN-07), a platform kind under `owns` (T-04-13), a word that is no kind (`conversations`, `none`, `drafts`), a kind twice in one list, and a kind in both lists, each as one plain sentence.
- `ConnectorChecks::folderErrors()` refuses a slot no room has (with the slots listed, or "this engine has no connector slots yet"), a slot whose room is not in the folder, a second file in one slot (D-25, T-04-15), and two files owning one kind with the exact success-criterion sentence on the later file in path order (D-26, T-04-14).
- `docs/SPEC.md` has both kind lists beside the ownership table and a connector row that says what is checked now and what waits for CONN-01; `SpecTablesTest` ties each list to its enum, in order; BLUEPRINT links to them; the example folder's connectors README is rewritten in plain words. The whole suite passes (1926 tests, up from 1883) and `exampleFolderFileCount()` is still 14.

## Task Commits

1. **Task 1 RED: a connector file is checked and compiled** - `c28d88a` (test)
2. **Task 1 GREEN: connector files are a checked type** - `8aebd94` (feat)
3. **Task 2 RED: connector kinds checked against one list** - `c705bab` (test)
4. **Task 2 GREEN: connector kinds, platform kinds and the SPEC lists** - `e77a1a3` (feat)
5. **Task 3 RED: connector slots and two owners of one kind** - `a060b8e` (test)
6. **Task 3 GREEN: connector slots and one owner per kind of record** - `01eea35` (feat)

**Plan metadata:** the docs commit that carries this file (docs: complete plan)

## TDD Gate Compliance

All three tasks produced a `test(04-04)` commit before the matching `feat(04-04)` commit; no REFACTOR commit was needed.

**RED evidence** (`gsd_run check tdd-red-evidence` returned `RED_EVIDENCE_OK` for each, JUnit XML from `--log-junit`):
- Task 1: target `it checks one connector file and compiles it into the live version`; failed on `Failed asserting that 15 is identical to 16` (the file was still skipped, so `file_count` did not count it). All 15 tests in the file failed on assertions. Semantic assessment: the target ran and failed on the planned assertion for the intended reason.
- Task 2: target `it refuses a connector file that still says objects, telling the owner to use owns and reads`; failed on `Expected status code 1 but received 0` (the sync accepted the file). 13 failures on assertions plus 2 errors, both `Class "App\Enums\OwnableKind" not found` / `PlatformKind` in SpecTablesTest, which is the planned state since the enums are GREEN work; the target is not one of the errors. Semantic assessment: executed and failed on the planned assertion. Three tests already held and passed (an entry that is not text is already refused by the field rules, invoices is accepted, the two lists are disjoint only after they exist, which the added `not->toBeEmpty()` makes fail).
- Task 3: target `it refuses two connector files that own the same kind with the exact sentence, and keeps the previous version live`; failed on `Expected status code 1 but received 0` (nothing yet compares what two files own). 8 of 39 failed, all on assertions; the others are cases that already hold. Semantic assessment: executed and failed for the intended reason.

**Tracer feedback gate:** Task 1 is `type="tracer"`; the interactive, `end-of-phase`, automated-only `<verify>` re-ran `tests/Feature/Rooms` and `tests/Feature/Icm` (387 tests) with the final Task 1 files, passed, and expansion proceeded.

## Files Created/Modified

- `app/Enums/OwnableKind.php`, `app/Enums/PlatformKind.php` - the two lists of kinds, each with `values()`
- `app/Icm/ConnectorChecks.php` - `fileErrors()` (objects, kinds, repeats, both lists) and `folderErrors()` (slots, two owners); every sentence lives here
- `app/Icm/Loader.php` - `connector` is typed, leaves `LATER_SPRINT`, and joins `NONE_MEANS_EMPTY` for owns and reads
- `app/Icm/Validator.php` - `ConnectorChecks` injected; `connector` rule arm and post-check arm; `folderErrors()` appended in `validate()`
- `app/Icm/FolderSync.php` - `index['connector']` is a seeded list of paths
- `app/Icm/RoomChecks.php` - public `isPresent()` over the existing present-room rule
- `tests/Feature/Rooms/ConnectorFileTest.php` - 39 tests; `tests/Feature/Rooms/SpecTablesTest.php` - four more; `tests/Rooms/SpecTables.php` - `ownableKinds()` and `platformKinds()`
- `tests/Fixtures/pretend-room/connectors/pretend-feed.md`, `tests/Fixtures/connector-conflict/connectors/a.md` and `b.md` - fictional connector files, no credentials
- `tests/Feature/Icm/ValidatorTest.php` (line 67) and `tests/Feature/Rooms/RoomFileTypeTest.php` (two lines) - the type-list sentence now ends with `connector`
- `docs/SPEC.md`, `docs/BLUEPRINT.md`, `examples/example-hvac/connectors/README.md`, `docs/sprints/sprint-1.1.md` - the two kind lists, connector row and folder tree, the BLUEPRINT link, the plain-words README, one walk-through line

## Decisions Made

- A connector file counts toward the two-owner check only when every `owns` entry is an `OwnableKind` value, as the plan says "files whose owns entries passed the kind check". A file refused for `owns: [customers, texts]` therefore never blames another file for owning `customers`; the owner fixes the kinds first and then sees the conflict if there is one (pinned by `does not count a connector file whose owns already failed the kind check as an owner`).
- A repeated or unknown word is reported once per distinct value, so `owns: [texts, texts]` gets one platform-owns line, not two.
- The "in both owns and reads" line is on field `reads` and fires only for `OwnableKind` values, so a platform kind written in both lists earns only its owns line.
- `RoomChecks::isPresent()` was made public rather than copying the present-room rule, as the plan's "reuse it, do not copy it" asks; `presentRooms()` now calls it too.
- `ConnectorChecks` takes `RoomRegistry` and `RoomChecks` in Task 3, where they are first used, not in Task 2, so no task commits an unused dependency.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Two Phase 4 type-list sentences also changed**
- **Found during:** Task 1 (GREEN)
- **Issue:** The plan names `ValidatorTest.php:67` as the one existing-test line to edit, but `RoomFileTypeTest` (written in plan 04-03) also asserts the sync's "type must be one of ..." sentence, twice. Adding `connector` to `Loader::TYPED` broke both.
- **Fix:** Added `connector` to the expected sentence in both places (`..., room, connector, pretend_note (you wrote recipe)` and `..., room, connector (you wrote pretend_note)`). These are Phase 4's own tests, not pre-Phase-4 tests, so the "only ValidatorTest:67" restriction on earlier tests is not touched.
- **Files modified:** `tests/Feature/Rooms/RoomFileTypeTest.php`
- **Verification:** `php artisan test --compact tests/Feature/Rooms tests/Feature/Icm` passes (387 tests)
- **Committed in:** `8aebd94`

**2. [Rule 2 - Missing Critical] Extra tests so the plan's guarantees are pinned**
- **Found during:** Tasks 2 and 3 (RED)
- **Issue:** The plan's behavior blocks did not cover: an owner-written value with a line break (T-04-17), a non-text list entry, a kind read twice, a second owner that is itself refused for its kinds, two files owning different kinds, `SpecTables` readers stopping at the next heading, and the disjointness of the two lists being checked against empty lists.
- **Fix:** Added a dataset row or test for each (`a kind with a line break in it`, `an entry that is not text`, `a kind read twice`, `does not count a connector file whose owns already failed ...`, `lets two connector files own different kinds ...`, `shows an owner-written slot on one line`, `reads the kinds of record from the two kind tables ...`, `not->toBeEmpty()` on the disjoint test). `SpecTables` was committed in RED with method shells returning `[]` so those tests fail on assertions.
- **Files modified:** `tests/Feature/Rooms/ConnectorFileTest.php`, `tests/Feature/Rooms/SpecTablesTest.php`, `tests/Rooms/SpecTables.php`
- **Committed in:** `c705bab`, `a060b8e`, `e77a1a3`

---

**Total deviations:** 2 auto-fixed (1 blocking, 1 missing critical)
**Impact on plan:** Both strengthen the proof the plan asks for. No scope creep; no app behavior beyond the plan.

## Issues Encountered

None. The acceptance greps that name exact refusal lines are satisfied by literal strings in `ConnectorFileTest` (the `owns: [none]` row and the missing-field rows are written out in full rather than built from a constant).

## Known Stubs

None. No placeholder values flow anywhere from this plan.

## Human Checks Carried to UAT

None. This plan has no `<human-check>`. The wording of the new connector sentences, the two SPEC kind lists and the example README is the part an owner reads (coverage D7); the sentences all live in `ConnectorChecks` so they can be reworded together.

## Concepts Explained for Ian

One new concept, added to the walk-through log at the end of `docs/sprints/sprint-1.1.md`:

- **A list of allowed words as an enum, and asking whether a word is on it.** `OwnableKind` is a string-backed enum: a fixed list of named choices that each carry a text value (`case SalesActivity = 'sales_activity'`). `OwnableKind::cases()` returns every choice, `values()` turns them into the plain words an owner writes, and `OwnableKind::tryFrom('texts')` asks "is this word one of the choices" and answers with the choice or `null` instead of throwing. That is why `ConnectorChecks` can test any word an owner typed without a try/catch, and why the sync, the refusal sentences and the SPEC table (through a test) all read one list.

## User Setup Required

None - no external service configuration required.

## Threat Flags

None. The surfaces are the ones the plan's threat model names: T-04-13 (platform kinds refused under owns, tested for texts, projects and timeline), T-04-14 (two owners refused with the exact line, previous version live), T-04-15 (unknown slot, absent room, second file in a slot), T-04-16 (system, direction and schedule pass through; accepted, Phase 4 ships no real connector and SPEC and the README both say credentials never go in the folder), T-04-17 (every owner-written value in `ConnectorChecks` goes through `PlainWords::oneLine()`, tested with a line break), and T-04-34 (connector owns and reads share `NONE_MEANS_EMPTY`; `slot: none` is an unknown slot). No new network, auth or file-access path was added; the checks run on files the Loader already read, inside the existing sync lock.

## Next Phase Readiness

- Plans 04-05 and 04-06 can build on a checked connector type: `index['connector']` is in every compiled version, and `RoomRegistry::roomWithSlot()` now decides which room owns a connector file.
- Whether the front desk's text slot gets a connector file is Phase 6's call (D-25) and was not touched; `agents/*.md` still has no owner room (Ian's 2026-10-22 decision 4).
- CONN-01 (the first real connector) adds the checks on `system`, `direction` and `schedule` and the no-secrets scan; the SPEC connector row says so.
- STATE.md's "Plan: N of 9" counter was not hand-edited, as instructed.

## Self-Check: PASSED

All 7 created files exist on disk, commits `c28d88a`, `8aebd94`, `c705bab`, `e77a1a3`, `a060b8e` and `01eea35` are on the branch, `check evaluation-scope --plan 04-04` resolved with commits, every task acceptance grep passes (`offer, room, connector (you wrote recipe)`, `'automation', 'walk_test'`, `NONE_MEANS_EMPTY` with `'connector'`, `case SalesActivity`, `case Texts`, both SPEC headings, `SPEC.md#kinds-of-record-a-connector-may-own`, `connectorChecks->folderErrors`, the exact D-26 line, the exact `none` slot line, `slot` in the README), `return 14;` is still in `tests/Pest.php`, and `tests/Feature/Rooms`, `tests/Feature/Icm`, `tests/Unit` and the whole suite (1926 tests) pass.

---
*Phase: 04-room-standard*
*Completed: 2026-10-23*
