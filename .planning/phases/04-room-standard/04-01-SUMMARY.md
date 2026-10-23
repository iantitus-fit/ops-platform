---
phase: 04-room-standard
plan: 01
subsystem: rooms
tags: [laravel, service-provider, filament, icm-sync, registry, tdd]

requires:
  - phase: 02.1-faster-answers
    provides: the Loader, FolderSync and ActiveConfig the room hooks plug into
provides:
  - "App\\Rooms\\Room, RoomRegistry (singleton), RoomServiceProvider, RoomSection, RoomFileType, TimelineEntryType"
  - "Loader reads rooms/<room>/CONTEXT.md as type room; compiled index['room'] maps room key to contract path"
  - "ActiveConfig::hasRoom() and roomKeys(), both never throwing"
  - "Pretend room (tests only) with enablePretendRoom(), layPretendRoomFolder() and layFixtureFolder() Pest helpers"
affects: [04-02, 04-03, 04-04, 04-05, 04-06, 05-room-phases, 06-room-phases]

actuals:
  tokens: 9086
  tasks: 2
  commits: 4

tech-stack:
  added: []
  patterns:
    - "A room is a plain service provider whose register() only adds a Room descriptor to the RoomRegistry singleton"
    - "Every is-this-room-on decision is made at run time through ActiveConfig::hasRoom(), never at boot"
    - "Room classes are named as strings, so a room may point at classes outside app/Rooms/<Room>/"

key-files:
  created:
    - app/Rooms/Room.php
    - app/Rooms/RoomRegistry.php
    - app/Rooms/RoomServiceProvider.php
    - app/Rooms/RoomSection.php
    - app/Rooms/RoomFileType.php
    - app/Rooms/TimelineEntryType.php
    - resources/views/filament/rooms/section.blade.php
    - tests/Rooms/PretendRoom/PretendRoomServiceProvider.php
    - tests/Rooms/PretendRoom/PretendRoomSection.php
    - tests/Fixtures/pretend-room/rooms/pretend/CONTEXT.md
    - tests/Feature/Rooms/PretendRoomBootTest.php
    - tests/Unit/Rooms/RoomRegistryTest.php
  modified:
    - app/Icm/Loader.php
    - app/Icm/FolderSync.php
    - app/Icm/ActiveConfig.php
    - app/Providers/AppServiceProvider.php
    - app/Providers/Filament/AdminPanelProvider.php
    - tests/Pest.php
    - tests/Feature/Icm/ValidatorTest.php
    - tests/Unit/ArchitectureTest.php
    - CLAUDE.md
    - docs/sprints/sprint-1.1.md

key-decisions:
  - "Loader::ROOM_CONTRACT is a public constant shared by the Loader (router exception) and FolderSync (index key), so the one pattern decides both"
  - "RoomRegistry guards run before anything is stored, so a refused room leaves the registry unchanged"
  - "addWidgetsTo() skips classes the panel already has, so the panel hook and the test helper can both call it"

patterns-established:
  - "Pretend-room test harness: enablePretendRoom() registers the provider after boot and adds its widgets to the already-built panel"
  - "Fixture folders live under tests/Fixtures/<name>/ and are copied into the test's temporary business folder, never into examples/"

requirements-completed: [ROOM-01, ROOM-02, ROOM-05]

coverage:
  - id: D1
    description: "A folder holding only rooms/pretend/CONTEXT.md passes the real sync, the live version has the room, and its section is drawn on /admin"
    requirement: ROOM-01
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/PretendRoomBootTest.php#turns the pretend room on when its folder holds only the contract, from the sync to the dashboard"
        status: pass
    human_judgment: false
  - id: D2
    description: "Adding or removing rooms/pretend/ turns the room on or off at the next sync in the same running app, and ActiveConfig::hasRoom() and roomKeys() never throw"
    requirement: ROOM-01
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/PretendRoomBootTest.php#turns the room on and off at each sync in the same running app"
        status: pass
      - kind: integration
        ref: "tests/Feature/Rooms/PretendRoomBootTest.php#answers that no room is on before the first sync, without throwing"
        status: pass
    human_judgment: false
  - id: D3
    description: "Only a CONTEXT.md directly inside rooms/<room>/ is a room contract, matched exactly and case-sensitively; every other CONTEXT.md stays a router and is still fingerprinted"
    requirement: ROOM-01
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/PretendRoomBootTest.php#reads only a CONTEXT.md directly inside rooms/<room>/ as a room contract"
        status: pass
      - kind: integration
        ref: "tests/Feature/Rooms/PretendRoomBootTest.php#matches the room folder name exactly, so Rooms/pretend/CONTEXT.md stays a router"
        status: pass
    human_judgment: false
  - id: D4
    description: "A room is a plain service provider adding one Room to a RoomRegistry singleton; the registry refuses duplicate keys, bad names, clashing file types, reused or half-set slots and missing slot interfaces, and returns rooms in key order"
    requirement: ROOM-02
    verification:
      - kind: unit
        ref: "tests/Unit/Rooms/RoomRegistryTest.php (24 tests)"
        status: pass
      - kind: integration
        ref: "tests/Feature/Rooms/PretendRoomBootTest.php#never lists the pretend room in bootstrap/providers.php"
        status: pass
    human_judgment: false
  - id: D5
    description: "CLAUDE.md Map has an app/Rooms/ row and sprint-1.1.md's walk-through log has seven plain-words lines for the new Laravel concepts"
    requirement: ROOM-05
    verification:
      - kind: other
        ref: "grep -q '(plan 04-01)' docs/sprints/sprint-1.1.md and the CLAUDE.md row-before-Boost-block check"
        status: pass
    human_judgment: true
    rationale: "Whether the walk-through lines explain each concept clearly enough for Ian is a judgment no test makes"

duration: 7min
completed: 2026-10-22
status: complete
plan_head_before: 600d41296dd3a98921a92ec4818c60def99041b4
plan_head_after: d84f6ce0061aee468be4399886ec1f194d0d12c7
commits: 4
---

# Phase 4 Plan 01: Room Standard Tracer Summary

**A room is one plain Laravel service provider that registers a descriptor in a RoomRegistry singleton; a folder holding only `rooms/pretend/CONTEXT.md` passes the real sync, flips `ActiveConfig::hasRoom('pretend')`, and draws (or removes) its dashboard section at the next sync with no restart.**

## Performance

- **Duration:** 7 min
- **Started:** 2026-10-22T21:47:37Z
- **Completed:** 2026-10-22T21:54:44Z
- **Tasks:** 2 (Task 1 tracer, Task 2 registry contract), 4 commits (RED and GREEN for each)
- **Files modified:** 22 (12 created, 10 modified)

## Accomplishments

- The tracer path works end to end: fixture contract, Loader (typed `room`), FolderSync index, ActiveConfig answers, RoomSection `canView()`, and a signed-in GET `/admin` that contains `data-room-section="pretend"` only while the live version has the room.
- The room stays off or on at run time: lay the contract, sync (on), delete the folder, sync (off), all in one running app.
- `RoomRegistry` carries every field and lookup later plans use, with guards that make two rooms unable to collide (duplicate key, non-`lower_snake_case` names, file types clashing with built-ins or each other, reused slot names, half-set slots, slot interfaces that do not exist).
- No room is required: the example folder activates as before, `Validator::SINGLETONS` is untouched, `bootstrap/providers.php` is unchanged, `exampleFolderFileCount()` still returns 14, and the whole suite passes (1781 tests).

## Task Commits

1. **Task 1 RED: pretend room contract turns the room on** - `4cf239a` (test)
2. **Task 1 GREEN: rooms register as providers and a room folder turns its room on** - `7c6e371` (feat)
3. **Task 2 RED: room registry guards and lookups** - `e36ead4` (test)
4. **Task 2 GREEN: room registry contract, guards, map row and walk-through** - `d84f6ce` (feat)

**Plan metadata:** the docs commit that carries this file (docs: complete plan)

## TDD Gate Compliance

Both tasks produced a `test(04-01)` commit before the `feat(04-01)` commit. No REFACTOR commit was needed.

**RED evidence, Task 1** (`gsd_run check tdd-red-evidence` returned `RED_EVIDENCE_OK`, JUnit XML from `pest --log-junit`):
- Target: `it turns the pretend room on when its folder holds only the contract, from the sync to the dashboard`.
- Failed on the planned assertion: `compiled files['rooms/pretend/CONTEXT.md']` had `type` null (the Loader still treated the contract as a router) where `room` was expected.
- Semantic assessment: the target executed and failed for the intended reason, not a load, import or fixture fault. Of the 7 tests in the file, 3 failed on assertions and 4 errored with `Call to undefined method ActiveConfig::hasRoom()`, the method this task adds.

**RED evidence, Task 2** (`RED_EVIDENCE_OK`):
- Target: `it refuses a second room with the same key`; failed with `Exception [LogicException] not thrown.`, the planned behavior. Semantic assessment: executed and failed on the planned assertion. Other tests failed with `Unknown named parameter $fileTypes` and similar because `Room` had no such fields yet, which is the expected RED for new constructor fields.

## Files Created/Modified

- `app/Rooms/Room.php` - readonly descriptor: key, label, handles, fileTypes, slot, slotInterface, sections, tables, timelineEntries, morphMap; `handledTypes()`
- `app/Rooms/RoomRegistry.php` - the singleton list of rooms with guards, lookups and `addWidgetsTo(Panel)`
- `app/Rooms/RoomServiceProvider.php` - abstract base: `register()` records the room, `boot()` merges its morph map
- `app/Rooms/RoomSection.php` - abstract dashboard widget, `canView()` asks `ActiveConfig::hasRoom()`
- `app/Rooms/RoomFileType.php`, `app/Rooms/TimelineEntryType.php` - the interface and value object rooms hand the registry
- `resources/views/filament/rooms/section.blade.php` - shared section view carrying `data-room-section`
- `app/Icm/Loader.php`, `FolderSync.php`, `ActiveConfig.php` - the three small hooks (typed `room`, index by folder name, never-throwing readers)
- `app/Providers/AppServiceProvider.php`, `Filament/AdminPanelProvider.php` - singleton binding and the panel hook
- `tests/Rooms/PretendRoom/*`, `tests/Fixtures/pretend-room/rooms/pretend/CONTEXT.md`, `tests/Pest.php` - the pretend room and its helpers
- `tests/Feature/Rooms/PretendRoomBootTest.php`, `tests/Unit/Rooms/RoomRegistryTest.php` - the tracer and the registry tests
- `tests/Feature/Icm/ValidatorTest.php` - the one existing-test line Phase 4 may edit (now lists `room`)
- `tests/Unit/ArchitectureTest.php`, `CLAUDE.md`, `docs/sprints/sprint-1.1.md` - arch rule for `App\Rooms`, Map row, seven walk-through lines

## Decisions Made

- `Loader::ROOM_CONTRACT` is one public constant with a capture group. The Loader tests it to let a contract past the router branch, and FolderSync reads the folder name from the same match, so the two cannot drift apart.
- `RoomRegistry::add()` runs all three guards before storing anything, so a refused room leaves the registry as it was.
- `addWidgetsTo()` drops classes the panel already lists, so `AdminPanelProvider::panel()` and `enablePretendRoom()` can both call it without a duplicate.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None. The tracer feedback gate (interactive, `end-of-phase` mode, automated-only `<verify>`) re-ran `tests/Feature/Rooms` and `tests/Feature/Icm` after the tracer commit, passed, and expansion proceeded.

## Known Stubs

- `resources/views/filament/rooms/section.blade.php` line 3: the section body is empty on purpose. The plan says Plan 04-06 fills it with the room's line (D-21). Recorded in `.planning/WINDOWS.md` as a `stub` entry. It does not block this plan's goal, which is the path from folder to a drawn section.

## Human Checks Carried to UAT

None. This plan has no `<human-check>`. The keyboard-only and screen reader walk of the room section belongs to the accessibility plans later in the phase.

## Concepts Explained for Ian

Each is also one line in the walk-through log at the end of `docs/sprints/sprint-1.1.md`.

- **Service provider.** A class whose job is to set things up when the app starts. Laravel builds the app, then asks every provider listed in `bootstrap/providers.php` to do its setup. `AppServiceProvider` is the one you know. A room gets its own provider (`RoomServiceProvider` is the base) so one file says everything the room adds.
- **`register()` versus `boot()`.** `register()` runs first for every provider and may only say what exists, never use other services, because they may not be set up yet. `boot()` runs after all of them have registered, so it can use anything. In `RoomServiceProvider`, `register()` hands the room's description to the registry and `boot()` adds the room's model nicknames with `Relation::morphMap()`.
- **Boot order.** All `register()` calls, then all `boot()` calls. Filament builds the admin panel (`AdminPanelProvider::panel()`) during its own boot, which is after every `register()`, so a room that registered itself is already known when the panel asks the registry for widgets. A room that waited for its own `boot()` would be too late.
- **Singleton registry.** `singleton(RoomRegistry::class)` in `AppServiceProvider` means everyone who asks for `RoomRegistry` gets the same object, so rooms add themselves and the dashboard reads the same list. It also explains why `AppServiceProvider` must stay first in `bootstrap/providers.php`.
- **Registering a provider in a test.** `app()->register(PretendRoomServiceProvider::class)` does what Laravel does at start-up, one provider at a time; because the app already started, `register()` and `boot()` both run at once. That is how the pretend room exists only in tests. The panel was built earlier, so `enablePretendRoom()` also adds the room's widgets to it.
- **Widget `canView()`.** Filament calls the static `canView()` on each dashboard widget before drawing it. `RoomSection::canView()` asks `ActiveConfig::hasRoom()`, so a business without the room folder never sees the section.
- **Deciding at run time, not at boot.** Boot happens once when a process starts, and a queue worker can run for days while the folder is synced. `ActiveConfig` is rebuilt per request and per job, so asking it when the code runs always sees the live version. Asking at boot would be stale in a worker and would crash on a fresh database with no live version.

## User Setup Required

None - no external service configuration required.

## Threat Flags

None. The only new surface is the dashboard widget, which T-04-04 already covers: `canView()` hides it and Filament answers 403 if a hidden widget is requested directly.

## Next Phase Readiness

- Plan 04-02 can add the contract's field checks (`owns`, `reads`, `writes_records`, `connector`) and refuse a `type: room` file anywhere but `rooms/<room>/CONTEXT.md`; today such a file is typed and counted but not indexed.
- The registry already exposes `registeredTypes()`, `fileType()`, `roomsHandling()`, `roomWithSlot()`, `slots()` and `timelineEntry()` for the plans that wire the Loader, Validator and timeline to it. The Loader does not consult `registeredTypes()` yet, so a room-defined file type is still unrecognized until that plan lands.
- Plan 04-07 (Composer dev package install) is untouched; no dependency changed here.

## Self-Check: PASSED

All 12 created files exist on disk, commits `4cf239a`, `7c6e371`, `e36ead4` and `d84f6ce` are on the branch, `check evaluation-scope --plan 04-01` resolved with commits, and `tests/Feature/Rooms`, `tests/Feature/Icm`, `tests/Unit` and the whole suite (1781 tests) pass.

---
*Phase: 04-room-standard*
*Completed: 2026-10-22*
