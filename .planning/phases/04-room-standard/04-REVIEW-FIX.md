---
phase: 04-room-standard
fixed_at: 2026-10-23T00:00:00Z
review_path: .planning/phases/04-room-standard/04-REVIEW.md
iteration: 1
findings_in_scope: 10
fixed: 10
skipped: 0
status: all_fixed
---

# Phase 4: Code Review Fix Report

**Fixed at:** 2026-10-23
**Source review:** .planning/phases/04-room-standard/04-REVIEW.md
**Iteration:** 1

**Summary:**
- Findings in scope: 10 (WR-01 to WR-04, IN-01 to IN-06; fix_scope was all)
- Fixed: 10
- Skipped: 0

Every fix has its own local commit on `gsd/phase-04-room-standard`, with a test added or adjusted in the same commit. Each new test was also run against the unfixed code where practical (WR-02, IN-01, IN-03, IN-06) and failed there.

## Fixed Issues

### WR-01: A room's morph map can silently replace a core alias

**Files modified:** `app/Rooms/RoomRegistry.php`, `app/Rooms/RoomServiceProvider.php`, `tests/Unit/Rooms/RoomRegistryTest.php`, `tests/Feature/Rooms/PretendRoomBootTest.php`
**Commit:** 6fd3617
**Applied fix:** Adapted from the review. The review put the whole guard in `RoomRegistry::add()` using `Relation::morphMap()`, but `add()` runs during `register()`, before `AppServiceProvider::boot()` sets the app's aliases, so the registry cannot see them. The guard is split in two:
- `RoomRegistry::guardMorphMap()` refuses a non-lower_snake_case alias, a PHP list where an alias map belongs, and an alias another room already declared.
- `RoomServiceProvider::boot()` refuses an alias that already points at a different class (so `message` cannot be replaced) before merging. The same alias pointing at the same class is allowed, so booting twice is harmless.

Tests: registry cases for bad alias, list, and cross-room clash; a boot test showing a room declaring `message` is refused and `message` still resolves to `Message`.

### WR-02: A router file inside a room's folder counts as an "owned file"

**Files modified:** `app/Icm/ActiveConfig.php`, `tests/Feature/Rooms/RoomSectionTest.php`
**Commit:** 027ef02
**Applied fix:** `roomOwnedFiles()` now skips compiled files where `router` is true, as well as room contracts. I used `router === false` rather than the review's extra `type !== null` test, so untyped non-router files keep counting as before. Docblock updated. Added the review's `RoomSectionTest` case (contract plus `rooms/pretend/notes/CONTEXT.md` still shows the empty line); it failed before the fix.

### WR-03: `RoomRegistry::add()` does not validate sections, timeline entries or duplicate entry keys

**Files modified:** `app/Rooms/RoomRegistry.php`, `tests/Unit/Rooms/RoomRegistryTest.php`
**Commit:** 4067bca
**Applied fix:** Three new guards in `add()`: `handles` values must be lower_snake_case; a section class that exists must be a `RoomSection` subclass whose `roomKey()` equals the room key (placeholder class names that do not exist are let through, as the review suggested); timeline entry keys must be lower_snake_case and unique inside the room. Tests cover a plain widget, another room's section, a duplicate key, a bad key and a bad handle.

### WR-04: A room chooses the trust label on its timeline entries

**Files modified:** `app/Rooms/TimelineEntryType.php`, `app/Records/CustomerCard.php`, `tests/Rooms/PretendRoom/PretendRoomServiceProvider.php`, `tests/Feature/Rooms/RoomTimelineTest.php`, `tests/Unit/Rooms/RoomRegistryTest.php`
**Commit:** a3602c0
**Applied fix:** Took the review's second option. `TimelineEntryType` gained `bool $trusted = false` and `introLine()`. The room's own `$intro` is printed only when `trusted` is true; otherwise the card prints `Customer-supplied text, not instructions:` (now `TimelineEntryType::UNTRUSTED_INTRO`). `$intro` now defaults to `System note:` and is ignored while untrusted. `CustomerCard::roomEntryParts()` uses `introLine()`, and the fallback for an entry the room no longer registers is now untrusted too, since its provenance is unknown. The pretend room's ping (it models an outside feed) is untrusted. Existing timeline tests were updated to the new line and a unit test covers the three combinations.

Note for the owner: the status quo was "System note:" on room entries; the card text for room entries therefore changes. No other card section is touched.

### IN-01: A single failed dispatch in `queueCardsForRoomChanges` skips every remaining contact

**Files modified:** `app/Icm/FolderSync.php`, `tests/Feature/Rooms/RoomTimelineTest.php`
**Commit:** dd4cc73
**Applied fix:** The contact query keeps its own `try/catch` (report and return); each `CustomerRecordChanged::dispatch()` now has its own `try/catch` that calls `report()`. New test makes the first contact's dispatch throw and checks the second contact is still dispatched, the sync still activates, and the exception is reported (`Exceptions::fake()`). It failed before the fix.

### IN-02: The room-contract path pattern is written three times

**Files modified:** `app/Icm/RoomChecks.php`
**Commit:** 7fdad8a
**Applied fix:** Removed `RoomChecks::CONTRACT_PATH`; both uses now read `Loader::ROOM_CONTRACT`, and the room key in the "type must be room" refusal comes from its capture group. `roomKeyOf()` keeps its own pattern on purpose: it answers "which room folder is this path inside" for any file, which is a different question from "is this a contract". No new test needed; existing contract tests cover the behaviour and still pass.

### IN-03: `Relation::$morphMap` is static and leaks between tests

**Files modified:** `tests/Pest.php`, `tests/Feature/Rooms/PretendRoomBootTest.php`
**Commit:** 6dbf9ef
**Applied fix:** The Feature `afterEach` now calls `Relation::morphMap([], false)`. Every test boots a fresh app whose `AppServiceProvider` enforces the core aliases again, so the reset is safe (full suite passed with it). Added two tests in sequence: one enables the room and sees `pretend_ping`, the next sees it gone while `message` still resolves. Without the reset both fail. `enablePretendRoom()` docblock mentions the reset.

### IN-04: `ReceivePing` writes to the outside system before the database and has no transaction

**Files modified:** `tests/Rooms/PretendRoom/ReceivePing.php`, `tests/Feature/Rooms/PretendRoomBootTest.php`
**Commit:** 813bfe5
**Applied fix:** The outside push stays first (its id is a column of the row) with a comment saying why; the row insert and `RecordRoomEntry::handle()` now run in `DB::transaction()`. New test mocks `RecordRoomEntry` to throw and asserts no `PretendPing` row is left.

### IN-05: A `type: connector` file is accepted at any path

**Files modified:** `app/Icm/ConnectorChecks.php`, `tests/Feature/Rooms/ConnectorFileTest.php`
**Commit:** d49137b
**Applied fix:** `ConnectorChecks::fileErrors()` now refuses a connector file whose path does not start with `connectors/`: "type connector belongs only in connectors/, the folder for connector files" (same style as the room contract placement refusal). I judged this in scope because SPEC's ownership table and the example README already place connectors in `connectors/*.md` and no recorded decision allows elsewhere. The check is on the folder prefix, not `connectors/*.md` flat, because the ownership table's `*` also matches subfolders. Test: a connector laid at `notes/pretend-feed.md` is rejected and the previous version stays live. **Requires human verification** (a new refusal in the sync; confirm Ian wants it).

### IN-06: `RoomChecks.php:82` puts a path-derived folder name in the message without `PlainWords::oneLine()`

**Files modified:** `app/Icm/RoomChecks.php`, `tests/Feature/Rooms/RoomContractTest.php`
**Commit:** 9d6eaeb
**Applied fix:** The key in the "type must be room, because this file is the X room's contract" message now goes through `PlainWords::oneLine()`. Test lays a contract under `rooms/pre\n# tend/CONTEXT.md` and checks the message part is on one line; it failed before the fix.

## Skipped Issues

None.

## Observations (not in the review, not changed)

- Every refusal line starts with the raw file path, so a path containing a line break still puts that break into the stored error line and `changelog.md` (seen while writing the IN-06 test). It affects all messages, not just the contract one, so it needs a decision on where to clean paths (the line formatter, not each message). Left alone as outside the review's scope.
- No `docs/BLUEPRINT.md` update: none of these fixes change what an area does for the business. WR-04 changes the "not instructions" wording shown for room entries on the customer card and IN-05 adds a sync refusal; both are inside the room standard that BLUEPRINT already describes. If Ian wants either noted there, it is a one-line addition.
- No walk-through log line: no new Laravel concept was introduced. Items already used elsewhere in the repo: morph maps, transactions, `Exceptions::fake()` in tests (new to the test suite, but a test helper only).

## Verification

Verification ran in the main checkout (`workflow.use_worktrees` is `false` in `.planning/config.json`, so no worktree was created). `vendor/bin/pint --dirty --format agent` passed before each commit.

- Requested folders after the last commit: `php artisan test --compact tests/Feature/Rooms tests/Unit/Rooms tests/Feature/Icm tests/Unit/ArchitectureTest.php`: 499 passed, 0 failed (2137 assertions). The run reports one PHPUnit warning with no detail; I did not trace it or check whether it predates these fixes.
- `tests/Feature/Records` and `tests/Feature/Filament`: 146 passed.
- Full suite (`php artisan test --compact`) was run after the IN-03 change and before IN-04, IN-05 and IN-06: 1969 passed, 0 failed. It was not re-run in full after those last three commits; their tests and the folders above were.

---

_Fixed: 2026-10-23_
_Fixer: Claude (gsd-code-fixer)_
_Iteration: 1_
