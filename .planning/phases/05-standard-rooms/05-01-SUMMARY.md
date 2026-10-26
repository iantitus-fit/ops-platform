---
phase: 05-standard-rooms
plan: 01
subsystem: testing
tags: [pest, rooms, ownership, test-hygiene, 04-D-16]

requires:
  - phase: 04-room-standard
    provides: the room registry, ownership checks, pretend and annex test rooms, connector slots
provides:
  - an annex test room that handles only pretend_note and owns nothing, so it cannot double-claim a file a real room will own
  - exampleRoomKeys(), exampleConnectorPaths() and sortedRoomKeys() in tests/Pest.php
  - Phase 4 refusal-line tests that build their growing lists (room keys, file types, slots, connector files) from code
  - unit coverage of the "no room handles it yet" and "absent room" ownership branches with a test-built registry
affects: [05-02, 05-03, 05-04, 05-05, 05-06, 05-07, 05-08, 05-09, knowledge]

plan_head_before: a32b2b0b0ea12f457ee8b72eb41d42968164029b
plan_head_after: 341cb1a899f38f132ade5f8cb38de736e002f40d

actuals:
  tokens: 7200
  tasks: 2
  commits: 2

tech-stack:
  added: []
  patterns:
    - "Expected refusal sentences are built from the same code lists the sentences use (RoomRegistry::keys/slots/registeredTypes, Loader::TYPED), plus a containment check of the test room's own entry"
    - "Cases that need a file type no room handles use rules/triage.md; a unit test with a test-built registry keeps the branch covered"
    - "A near-miss room folder (room/, singular) replaces a capital-R folder, which collides with rooms/ on a case-insensitive disk"

key-files:
  created:
    - tests/Unit/Icm/RoomChecksTest.php
  modified:
    - tests/Rooms/PretendRoom/AnnexRoom.php
    - tests/Fixtures/annex-room/rooms/annex/CONTEXT.md
    - tests/Pest.php
    - tests/Feature/Rooms/RoomOwnershipTest.php
    - tests/Feature/Rooms/PretendRoomBootTest.php
    - tests/Feature/Rooms/RoomContractTest.php
    - tests/Feature/Rooms/RoomFileTypeTest.php
    - tests/Feature/Rooms/ConnectorFileTest.php
    - tests/Feature/Icm/ValidatorTest.php

key-decisions:
  - "sortedRoomKeys() lives in tests/Pest.php, not RoomOwnershipTest.php, because Pest test-file functions are global only once that file loads and PretendRoomBootTest.php also needs it when run alone"
  - "Inline test-only rooms quiet (handles nothing) and rulebook (handles triage) stand in for the annex wherever a case needs a second room that never collides with a real one"

patterns-established:
  - "Every rewritten refusal case still asserts one exact line or an exact list; no case was removed (it( counts are unchanged in all six edited files)"

requirements-completed: [ROOM-08, ROOM-09, ROOM-10, ROOM-11, ROOM-14]

coverage:
  - id: D1
    description: "The annex test room handles only pretend_note and its fixture contract owns none, so it can never claim a file a real room will own"
    requirement: ROOM-08
    verification:
      - kind: unit
        ref: "tests/Feature/Rooms/RoomOwnershipTest.php (17 cases), ConnectorFileTest.php and OrderIndependenceTest.php, which still lay the annex fixture"
        status: pass
    human_judgment: false
  - id: D2
    description: "The ownership cases that needed a file type no room handles now use rules/triage.md, with unit tests covering both branches against a registry the test builds"
    requirement: ROOM-09
    verification:
      - kind: unit
        ref: "tests/Unit/Icm/RoomChecksTest.php#says no room handles a type yet when a present room claims a file of that type"
        status: pass
      - kind: unit
        ref: "tests/Unit/Icm/RoomChecksTest.php#names the absent room when the only room that handles a type is not in the files"
        status: pass
    human_judgment: false
  - id: D3
    description: "Growing lists in refusal expectations (room keys, file types, slots, example connector files) are built from code and the example folder, with a containment check for the test room's own entry"
    requirement: ROOM-10
    verification:
      - kind: unit
        ref: "tests/Feature/Rooms/RoomContractTest.php, RoomFileTypeTest.php, ConnectorFileTest.php, PretendRoomBootTest.php, tests/Feature/Icm/ValidatorTest.php"
        status: pass
    human_judgment: false
  - id: D4
    description: "The room-folder case-sensitivity proof no longer writes Rooms/pretend/CONTEXT.md, and the furnished boot row names the pretend section's own empty line"
    requirement: ROOM-11
    verification:
      - kind: unit
        ref: "tests/Feature/Rooms/PretendRoomBootTest.php#matches the room folder name exactly, so only rooms/<name>/CONTEXT.md is a contract"
        status: pass
    human_judgment: false
  - id: D5
    description: "The whole suite is green with no real room registered and exampleFolderFileCount() still returns 14"
    requirement: ROOM-14
    verification:
      - kind: other
        ref: "php artisan test --compact (1983 passed, 16048 assertions)"
        status: pass
    human_judgment: false

duration: 7min
completed: 2026-10-25
status: complete
---

# Phase 5 Plan 1: Make the Phase 4 suite hold when the five real rooms land Summary

**The annex test room now handles only pretend_note and owns nothing, and every Phase 4 test that hard-coded a list real rooms will grow (room keys, file types, connector slots, example connector files) builds that list from the code, so the suite is green now and shaped to stay green as each room lands.**

## Performance

- **Duration:** 7 min
- **Started:** 2026-10-25T22:56:00Z
- **Completed:** 2026-10-25T23:03:01Z
- **Tasks:** 2
- **Files modified:** 10 (1 created, 9 changed; tests only)

## Accomplishments

- Annex test room narrowed: `handles: ['pretend_note']`, fixture contract `owns: none`. It can no longer double-claim `business/faq.md`, which company knowledge will own.
- `RoomOwnershipTest` keeps all 17 cases. The cases that leaned on the annex's `knowledge` now use the pretend note, an inline `quiet` room (handles nothing) or an inline `rulebook` room (handles `triage`). Each still asserts one exact line.
- `tests/Unit/Icm/RoomChecksTest.php` covers the "no room handles yet" claim line and the absent-room line with a registry the test builds, so neither branch loses its coverage when the front desk room handles triage in Phase 6.
- `exampleRoomKeys()`, `exampleConnectorPaths()` and `sortedRoomKeys()` added to `tests/Pest.php`; `exampleFolderFileCount()` untouched at 14.
- `RoomContractTest`, `RoomFileTypeTest`, `ValidatorTest`, `ConnectorFileTest` and `PretendRoomBootTest` now compute room-key, type-list, slot-list and connector-index expectations from `RoomRegistry`, `Loader::TYPED` and the example folder, each with a containment check for the test room's own entry.
- Pitfall 7 fixed: the case-sensitivity proof uses `room/pretend/CONTEXT.md` plus a direct `Loader::ROOM_CONTRACT` match check, so it no longer lands inside `rooms/` on a case-insensitive disk.
- Pitfall 10 fixed: the furnished boot row asserts `Pretend: nothing set up yet` is absent, so another room's empty line never fails it.

## Task Commits

1. **Task 1: Tracer: narrow the annex test room and run the ownership suite end to end** - `bd18757` (test)
2. **Task 2: Build the growing lists from code, fix the near-miss** - `341cb1a` (test)

Task 1 was a `type="tracer"`. Its `<verify>` carried only an automated command, so under end-of-phase mode it was re-run (66 tests passed) and expansion continued. No app code changed, so there was no RED/GREEN split to make; neither task carries `tdd="true"`.

**Plan metadata:** committed with this SUMMARY (docs: complete plan).

## Files Created/Modified

- `tests/Rooms/PretendRoom/AnnexRoom.php` - handles only `pretend_note`
- `tests/Fixtures/annex-room/rooms/annex/CONTEXT.md` - `owns: none`
- `tests/Pest.php` - `exampleRoomKeys()`, `exampleConnectorPaths()`, `sortedRoomKeys()`
- `tests/Unit/Icm/RoomChecksTest.php` - new; two unit cases and the helper `roomChecksUnitFile()`
- `tests/Feature/Rooms/RoomOwnershipTest.php` - cases rewritten onto pretend note, `quiet` and `rulebook`; new helper `plainRoomContract()`
- `tests/Feature/Rooms/PretendRoomBootTest.php` - room keys, providers list, near-miss folder, furnished-row empty line
- `tests/Feature/Rooms/RoomContractTest.php` - pretend notes instead of knowledge files; rooms sentence from `keys()`
- `tests/Feature/Rooms/RoomFileTypeTest.php` and `tests/Feature/Icm/ValidatorTest.php` - type-list sentence built from `Loader::TYPED` plus `registeredTypes()`
- `tests/Feature/Rooms/ConnectorFileTest.php` - connector index merges `exampleConnectorPaths()`; slot sentences from `slots()`; helpers `expectedConnectorPaths()` and `connectorSlotsSentence()`

## Decisions Made

- `sortedRoomKeys()` is in `tests/Pest.php` rather than the ownership test file, so `PretendRoomBootTest.php` can use it when run on its own.
- `plainRoomContract()` (a contract with `reads: none`, `writes_records: none`, `connector: none`) is a small new helper in `RoomOwnershipTest.php`, used by the `quiet` and `rulebook` cases.
- `ConnectorFileTest.php` leaves `says the engine has no connector slots yet when no room offers one` untouched, as the plan says; plan 05-08 moves it to a unit test (D-23).

## Deviations from Plan

None - plan executed exactly as written. The extra helpers above (`sortedRoomKeys`, `plainRoomContract`, `expectedConnectorPaths`, `connectorSlotsSentence`) are implementation detail inside files the plan already lists.

## Issues Encountered

None. Baseline before changes: 1981 tests passed. After: 1983 passed (the two new unit cases), 16048 assertions.

## Verification

- `php artisan test --compact tests/Feature/Rooms/RoomOwnershipTest.php tests/Feature/Rooms/ConnectorFileTest.php tests/Feature/Rooms/OrderIndependenceTest.php tests/Unit/Icm/RoomChecksTest.php`: 66 passed.
- `php artisan test --compact tests/Feature/Rooms tests/Feature/Icm/ValidatorTest.php tests/Unit`: 537 passed.
- `php artisan test --compact`: 1983 passed.
- Acceptance greps all pass: annex handles/owns lines, helper names, `return 14;`, the three exact strings, `it(` counts equal before and after in all six edited feature files, the zero-match greps for the old hard-coded lists, `room/pretend/CONTEXT.md`, `ROOM_CONTRACT`, and `Pretend: nothing set up yet')`.
- `git log --name-only --grep='(05-01)' -- app examples bootstrap` prints nothing: this plan changed tests only.
- Pint passed on both commits.

## User Setup Required

None - no external service configuration required.

## Hand checks

None carried by this plan, so nothing was added to `.planning/WINDOWS.md`. No stubs introduced.

## Walk-through log

No new Laravel concept appears in this plan (Pest datasets, test-file helper functions and unit tests were already in use), so no line was added to the sprint walk-through log.

## Next Phase Readiness

Ready for plan 05-02. The suite is green with no real room registered, and the Phase 4 tests that a real room's landing would have broken are already in the form that passes before and after. Plan 05-03 adds the Phase 6 carry note about the three `triage` ownership cases to `docs/BLUEPRINT.md` and the registry-with-no-rooms unit case (D-23).

## Threat Flags

None. No new network endpoint, auth path, file access pattern or schema change.

---
*Phase: 05-standard-rooms*
*Completed: 2026-10-25*

## Self-Check: PASSED

Created and changed files exist on disk; commits `bd18757` and `341cb1a` are ancestors of HEAD; the full suite passes.
