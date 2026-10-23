---
phase: 04-room-standard
plan: 06
subsystem: rooms
tags: [laravel, filament, livewire, blade, pest, icm, room-standard]

requires:
  - phase: 04-room-standard
    provides: "RoomSection and the empty section view (04-01), room contracts and RoomChecks (04-03), connector slot files (04-04), RecordRoomEntry timeline writes and the pretend table (04-05)"
provides:
  - "ActiveConfig::roomContract() and roomOwnedFiles(): the live contract's front matter and the live files its owns entries match (never throw)"
  - "RoomSection::emptyLine(): D-21's one plain line, built from the contract and drawn by the platform the same way for every room"
  - "RoomSection::contentLines(): the hook a room overrides to show its own content once a file is live"
  - "The finished section view (escaped paragraphs, no inline style)"
  - "The room standard's boot test: the pretend room boots empty and with its example folder and proves file type, table, section, timeline entry and slot together"
affects: [04-09 room accessibility check, Phase 5 and later room phases that copy the boot test shape, Phase 11 wording pass]

actuals:
  tokens: 3970
  tasks: 2
  commits: 4

tech-stack:
  added: []
  patterns:
    - "The platform draws the empty line for every room from the room's CONTEXT.md owns list; a room never builds its own empty screen"
    - "A room's boot test is one dataset test with an 'empty' row and a 'with its example folder' row, no database-driver guard"

key-files:
  created:
    - tests/Feature/Rooms/RoomSectionTest.php
  modified:
    - app/Icm/ActiveConfig.php
    - app/Rooms/RoomSection.php
    - resources/views/filament/rooms/section.blade.php
    - tests/Rooms/PretendRoom/PretendRoomSection.php
    - tests/Feature/Rooms/PretendRoomBootTest.php
    - docs/sprints/sprint-1.1.md

key-decisions:
  - "A room whose contract owns nothing is never shown as empty: emptyLine() returns null for an empty owns list, so its section shows whatever contentLines() reports"
  - "roomOwnedFiles() leaves room contracts out of the match, so a contract that matches its own owns pattern cannot make the room look furnished"
  - "The heading label is ucfirst'd once in getViewData() and passed to emptyLine(), so the line and the section heading always agree"

patterns-established:
  - "Empty line wording: '<Label>: nothing set up yet. Add <things> to start.' with /*.md and /* entries read as 'a file to <folder>/'; placeholder wording, no exclamation point or em dash"
  - "Room boot test shape: enable the room, lay the folder, sync, then check required files, tables, slot, owns against the SPEC-shaped row, dashboard and timeline"

requirements-completed: [ROOM-01, ROOM-02, ROOM-05]

coverage:
  - id: D1
    description: "An empty room's dashboard section shows one plain line naming the files that furnish it, built from the room's CONTEXT.md and drawn the same way for every room"
    requirement: ROOM-02
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/RoomSectionTest.php#shows the empty line on the dashboard for a room whose folder holds only the contract"
        status: pass
      - kind: unit
        ref: "tests/Feature/Rooms/RoomSectionTest.php#reads the empty line from the contract as the platform draws it"
        status: pass
    human_judgment: false
  - id: D2
    description: "A room whose contract owns nothing is never shown as empty; once an owned file is live the room's own content shows"
    requirement: ROOM-02
    verification:
      - kind: unit
        ref: "tests/Feature/Rooms/RoomSectionTest.php#reads the empty line from the contract as the platform draws it (a room that owns nothing, a room with an owned file live)"
        status: pass
      - kind: integration
        ref: "tests/Feature/Rooms/PretendRoomBootTest.php#shows the room's own content once a ping has arrived and its pinned note is live"
        status: pass
    human_judgment: false
  - id: D3
    description: "A direct Livewire request to a section whose room is not in the live version is refused with 403"
    requirement: ROOM-01
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/RoomSectionTest.php#refuses a direct request to a section whose room is not in the live version"
        status: pass
    human_judgment: false
  - id: D4
    description: "The pretend room boots empty and with its example folder, proving file type, table, section, timeline entry and connector slot, keeps the seven required files, and its contract owns match its SPEC-shaped row, on both CI databases"
    requirement: ROOM-05
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/PretendRoomBootTest.php#boots the pretend room"
        status: pass
    human_judgment: false
  - id: D5
    description: "The wording of the empty line reads well to the owner and the section markup meets WCAG 2.2 AA"
    requirement: ROOM-02
    verification: []
    human_judgment: true
    rationale: "The wording is a placeholder settled in the Phase 11 wording pass; the automated axe check is plan 04-09 and the keyboard and screen reader walk is a UAT item"

duration: 4min
completed: 2026-10-23
status: complete
plan_head_before: b0e6d2d026a80a193418861c73b91428a23fa02a
plan_head_after: 3e5256feef5237a977e312d80285188f810c8458
commits: 4
---

# Phase 4 Plan 06: Room dashboard section and the room standard's boot test Summary

**Every room's dashboard section now draws one plain "nothing set up yet" line built from its own CONTEXT.md owns list, and one dataset boot test proves the pretend room end to end, empty and with its example folder.**

## Performance

- **Duration:** 4 min
- **Started:** 2026-10-23T01:33:15Z
- **Completed:** 2026-10-23T01:37:00Z
- **Tasks:** 2
- **Files modified:** 7 (one created)

## Accomplishments
- `ActiveConfig::roomContract()` and `roomOwnedFiles()` read the live contract and the live files it owns, and never throw (empty answers before the first sync or when the room is off).
- `RoomSection::emptyLine()` builds D-21's line (`Pretend: nothing set up yet. Add a file to rooms/pretend/notes/ to start.`), with folder entries, exact paths and two or three entries joined with commas and "or"; a room that owns nothing, or one with an owned file live, gets no empty line.
- The section view prints the empty line or one paragraph per content line, through escaped `{{ }}` output only and with no inline style. This closes the 04-01 stub (WINDOWS.md entry 18, marked fixed).
- `PretendRoomSection::contentLines()` shows `Pings so far: N` and `Pinned: <title>` read through `ActiveConfig::filesOfType()`.
- The room standard's boot test (`it('boots the pretend room')`, rows `empty` and `with its example folder`) proves the file type, table, section, timeline entry and slot together, keeps the seven required files (D-06), and compares the live contract's owns with the SPEC-shaped row (D-14). It has no driver guard, so CI runs it on SQLite and Postgres.

## Task Commits

1. **Task 1: Tracer: a contract-only pretend room shows D-21's one plain line**
   - RED `328352a` (test): `test(04-06): the empty room line comes from the contract`
   - GREEN `7325e8a` (feat): `feat(04-06): an empty room's section names its files`
2. **Task 2: The room boot test, empty and with its example folder**
   - RED `755980f` (test): `test(04-06): the pretend room boots empty and with its example folder`
   - GREEN `3e5256f` (feat): `feat(04-06): the room standard's boot test passes empty and furnished`

**Plan metadata:** committed after this file (docs: complete plan)

## TDD Gate Compliance

Both tasks have a `test(04-06)` commit before the `feat(04-06)` commit. RED evidence, with the semantic assessment:
- Task 1 RED: `RoomSectionTest` ran 8 tests, 7 failing on the planned assertions (the dashboard lacked the empty line; `RoomSection::emptyLine` did not exist for all six dataset rows). The 403 test already passed because `canView()` came from 04-01; it is kept as the regression guard the plan asks for.
- Task 2 RED: `PretendRoomBootTest` ran 13 tests, 2 failing on the planned assertions (`Pinned: First note` and `Pings so far: 1` missing from the dashboard). The `empty` row passed at RED because the empty line and all five proofs already worked after Task 1; only the furnished content was new.
- The Pest JSON reporter is not a format `check tdd-red-evidence` parses, so the classifier was not run; the assessment above is from reading each failure message, which named the planned assertion.

## Files Created/Modified
- `app/Icm/ActiveConfig.php` - `roomContract()` and `roomOwnedFiles()`, never throwing
- `app/Rooms/RoomSection.php` - `emptyLine()`, `contentLines()` hook, view data keys `emptyLine` and `lines`
- `resources/views/filament/rooms/section.blade.php` - the one section view every room uses
- `tests/Rooms/PretendRoom/PretendRoomSection.php` - the pretend room's content lines
- `tests/Feature/Rooms/RoomSectionTest.php` - the one test of the D-21 line (created)
- `tests/Feature/Rooms/PretendRoomBootTest.php` - the room boot test and the content test
- `docs/sprints/sprint-1.1.md` - walk-through line for a widget's `getViewData()`

## Decisions Made
- A contract that owns nothing is never "empty" (D-21): `emptyLine()` returns null and the room's own `contentLines()` shows.
- Room contracts are excluded from `roomOwnedFiles()`, so a room cannot look furnished by matching its own contract.
- Label capitalisation happens once in `getViewData()`, so the section heading and the line cannot disagree.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## Known Stubs

None. The 04-01 stub (empty section view, WINDOWS.md entry 18) is resolved.

## Unrun checks for UAT

The plan carried no `<human-check>`. Open items that belong to later steps, not this plan: the automated axe check of this markup is plan 04-09; a keyboard-only and screen reader walk of the owner dashboard section is a UAT item (CLAUDE.md WCAG rule); the empty-line wording is a placeholder for the Phase 11 wording pass.

## Threat Flags

None. T-04-22 is mitigated (owns entries printed only through escaped `{{ }}`; no `{!!` in the view) and T-04-23 is mitigated and tested (hidden section refused with 403).

## Next Phase Readiness
- Ready for plan 04-09: the section markup is real paragraphs inside `data-room-section`, which the axe helper can read.
- Full suite: 1953 passing (was 1942 after 04-05). `exampleFolderFileCount()` is still 14 and `examples/example-hvac` gained no file.

## Self-Check: PASSED

- Files exist: RoomSectionTest.php, RoomSection.php, section.blade.php, ActiveConfig.php, PretendRoomBootTest.php, PretendRoomSection.php, sprint-1.1.md.
- Commits `328352a`, `7325e8a`, `755980f`, `3e5256f` are ancestors of HEAD.
- Acceptance greps: `function emptyLine`, `function roomOwnedFiles`, `RoomSectionAxe`, `(plan 04-06)`, `return 14;` present; `{!!` and `getDriverName` absent.
- `php artisan test --compact tests/Feature/Rooms` and the full suite exit 0.

---
*Phase: 04-room-standard*
*Completed: 2026-10-23*
