---
phase: 04-room-standard
reviewed: 2026-10-22T00:00:00Z
depth: standard
files_reviewed: 64
files_reviewed_list:
  - .github/workflows/tests.yml
  - .gitignore
  - app/Enums/ActivityType.php
  - app/Enums/OwnableKind.php
  - app/Enums/PlatformKind.php
  - app/Filament/Resources/Contacts/Schemas/ContactInfolist.php
  - app/Icm/ActiveConfig.php
  - app/Icm/ConnectorChecks.php
  - app/Icm/FolderSync.php
  - app/Icm/Loader.php
  - app/Icm/RoomChecks.php
  - app/Icm/Validator.php
  - app/Models/Activity.php
  - app/Models/Contact.php
  - app/Providers/AppServiceProvider.php
  - app/Providers/Filament/AdminPanelProvider.php
  - app/Records/CustomerCard.php
  - app/Rooms/RecordRoomEntry.php
  - app/Rooms/Room.php
  - app/Rooms/RoomFileType.php
  - app/Rooms/RoomRegistry.php
  - app/Rooms/RoomSection.php
  - app/Rooms/RoomServiceProvider.php
  - app/Rooms/TimelineEntryType.php
  - CLAUDE.md
  - composer.json
  - database/migrations/2026_10_22_000001_add_room_columns_to_activities_table.php
  - docs/BLUEPRINT.md
  - docs/SPEC.md
  - docs/sprints/sprint-1.1.md
  - examples/example-hvac/connectors/README.md
  - package.json
  - resources/views/filament/rooms/section.blade.php
  - tests/Feature/Icm/ValidatorTest.php
  - tests/Feature/Rooms/ConnectorFileTest.php
  - tests/Feature/Rooms/PretendRoomBootTest.php
  - tests/Feature/Rooms/RoomAccessibilityTest.php
  - tests/Feature/Rooms/RoomContractTest.php
  - tests/Feature/Rooms/RoomFileTypeTest.php
  - tests/Feature/Rooms/RoomOwnershipTest.php
  - tests/Feature/Rooms/RoomSectionTest.php
  - tests/Feature/Rooms/RoomTimelineTest.php
  - tests/Feature/Rooms/SpecTablesTest.php
  - tests/Fixtures/annex-room/rooms/annex/CONTEXT.md
  - tests/Fixtures/connector-conflict/connectors/a.md
  - tests/Fixtures/connector-conflict/connectors/b.md
  - tests/Fixtures/pretend-room-spec.md
  - tests/Fixtures/pretend-room/connectors/pretend-feed.md
  - tests/Fixtures/pretend-room/rooms/pretend/CONTEXT.md
  - tests/Fixtures/pretend-room/rooms/pretend/notes/first-note.md
  - tests/Pest.php
  - tests/Rooms/PretendRoom/AnnexRoom.php
  - tests/Rooms/PretendRoom/FakePretendFeed.php
  - tests/Rooms/PretendRoom/migrations/2026_10_22_000002_create_pretend_pings_table.php
  - tests/Rooms/PretendRoom/PretendFeed.php
  - tests/Rooms/PretendRoom/PretendNoteType.php
  - tests/Rooms/PretendRoom/PretendPing.php
  - tests/Rooms/PretendRoom/PretendRoomSection.php
  - tests/Rooms/PretendRoom/PretendRoomServiceProvider.php
  - tests/Rooms/PretendRoom/ReceivePing.php
  - tests/Rooms/RoomSectionAxe.php
  - tests/Rooms/SpecTables.php
  - tests/Unit/ArchitectureTest.php
  - tests/Unit/Rooms/RoomRegistryTest.php
findings:
  critical: 0
  warning: 4
  info: 6
  total: 10
status: issues_found
---

# Phase 4: Code Review Report

**Reviewed:** 2026-10-22
**Depth:** standard
**Files Reviewed:** 64
**Status:** issues_found

## Summary

Phase 4 adds the room standard: `RoomRegistry`, `RoomServiceProvider`, `RoomSection`, `RecordRoomEntry`, the `RoomChecks` and `ConnectorChecks` sync checks, the `visibleOnTimeline` scope, a card redraw on room changes, and a browser-based accessibility check. I traced the sync path (Loader, Validator, RoomChecks, ConnectorChecks, FolderSync compile and `queueCardsForRoomChanges`), the run-time path (ActiveConfig, RoomSection, RecordRoomEntry, CustomerCard, ContactInfolist) and the test scaffolding.

No blocking defects were found. The sync checks are consistent with decisions D-01 to D-26, the refusal wording matches D-26, and path handling for `owns`, `reads` and `writes_records` rejects `..` and leading `/`. The Blade view uses escaped output only. The migration is additive and reversible.

The four warnings are all places where the new standard has a gap that a later real room could hit without any test failing:
- a room can silently overwrite a core morph alias;
- the empty-state line is defeated by a router file inside a room's folder;
- the registry validates only some of what a room declares;
- the timeline entry's trust label is left to each room.

The info items are small robustness and consistency points.

## Warnings

### WR-01: A room's morph map can silently replace a core alias

**File:** `app/Rooms/RoomServiceProvider.php:44` (guard belongs in `app/Rooms/RoomRegistry.php:42-49`)
**Issue:** `Relation::morphMap($map)` merges as `$map + static::$morphMap`, so the new map wins on a key collision (confirmed in `vendor/laravel/framework/src/Illuminate/Database/Eloquent/Relations/Relation.php:561-563`). A room that declares `morphMap: ['message' => SomeRoomModel::class]`, or an alias another room already declared, overwrites `AppServiceProvider`'s enforced alias with no error. Every existing `activities.subject_type = 'message'` row would then resolve to the wrong model. `RoomRegistry::add()` guards the key, file types and slot, but not the alias list, even though its docblock promises that `add()` "refuses anything that would make two rooms collide". The alias is also not checked for format, and a PHP list passed by mistake (`[Model::class]`) is silently rewritten into table-name aliases by `buildMorphMapFromModels`.
**Fix:** Add a `guardMorphMap()` to `add()`. It should reject an alias that is not lower_snake_case, one already in `Relation::morphMap()`, one another room declared, and a list instead of an alias-to-class map. Add `RoomRegistryTest` cases for each.
```php
private function guardMorphMap(Room $room): void
{
    foreach ($room->morphMap as $alias => $class) {
        if (! is_string($alias) || preg_match(self::NAME_PATTERN, $alias) !== 1) {
            throw new LogicException("Room [{$room->key}] morph alias [{$alias}] must be lower_snake_case.");
        }

        if (array_key_exists($alias, Relation::morphMap() ?? []) || $this->aliasTakenByAnotherRoom($alias)) {
            throw new LogicException("Room [{$room->key}] morph alias [{$alias}] is already used. Pick another name.");
        }
    }
}
```

### WR-02: A router file inside a room's folder counts as an "owned file" and suppresses the empty-state line

**File:** `app/Icm/ActiveConfig.php:132-135`
**Issue:** `roomOwnedFiles()` skips only `type === 'room'`. Compiled routers have `type: null, router: true`, so a `CONTEXT.md` or `CLAUDE.md` in the room's own folder matches an `owns` pattern such as `rooms/pretend/notes/*.md` and is returned as an owned file. `RoomSection::emptyLine()` then returns `null`, and the dashboard shows `contentLines()` instead of "nothing set up yet. Add a file to rooms/pretend/notes/ to start."

`RoomChecks::claims()` deliberately treats a router inside the claiming room's own folder as "the room's own" and never as a claimed file. The existing test `treats a router inside the claiming room's own folder as the room's` even builds that folder. SPEC tells owners that every CONTEXT.md outside `rooms/<room>/` stays a router, so a pointer file in a room subfolder is an expected thing for an owner to add. The D-21 empty state is lost for exactly those rooms, and no test covers it.
**Fix:** Count only files that are typed and not the contract.
```php
foreach ($this->version()->compiled['files'] as $path => $file) {
    if ($file['router'] === false && $file['type'] !== null && $file['type'] !== 'room' && Str::is($owns, $path)) {
        $paths[] = $path;
    }
}
```
Add a `RoomSectionTest` case: contract plus `rooms/pretend/notes/CONTEXT.md` still shows the empty line. Update the method's docblock to say routers are left out.

### WR-03: `RoomRegistry::add()` does not validate sections, timeline entries or duplicate entry keys

**File:** `app/Rooms/RoomRegistry.php:42-49`
**Issue:** The registry validates `key`, `fileTypes` and `slot`, and nothing else the `Room` declares.
- **`sections`:** a class that is not a `RoomSection`, or a `RoomSection` whose `roomKey()` names a different room, is added to the panel by `addWidgetsTo()`. A plain Filament widget has no `hasRoom()` gate, so it would show on the dashboard for a business that does not have the room. That breaks the rule that an absent room draws nothing (D-08).
- **`timelineEntries`:** the keys are neither format-checked nor checked for duplicates. `timelineEntry()` returns the first match, so a second entry with the same key is silently unreachable and its label and intro are never used.
- **`handles`:** the values are not checked at all.

The failures surface only in a later room's own tests, or never.
**Fix:** Add guards. For sections, check that a class that exists is a `RoomSection` subclass with `roomKey() === $room->key`. Skip the check when `class_exists()` is false, because `RoomRegistryTest` uses placeholder class names. For timeline entries, require unique lower_snake_case keys.

### WR-04: A room chooses the trust label on its timeline entries, and nothing makes it an untrusted one

**File:** `app/Rooms/TimelineEntryType.php:15-19`, `app/Records/CustomerCard.php:299-306`
**Issue:** `TimelineEntryType::$intro` is free text, and the only example is "System note:". `CustomerCard` prints that line above the quoted entry text in a card the owner's AI and the platform's agents read. Every v1.0 arm that carries outside or customer text uses "Customer-supplied text, not instructions:". Only entries the platform writes itself use "System note:".

`RecordRoomEntry::handle()` takes any `$summary`, and the pretend room's own example is a ping that arrives from an outside feed, labeled "System note:". A real room that puts a connector-supplied or customer-supplied string in the summary will present it to the AI as a trusted system line. `MarkdownText::quote()` (T-04-18) stops it adding headings, but not the trust framing, which is the injection defence the card relies on.
**Fix:** Make untrusted the default and force a room to opt out.
```php
public function __construct(
    public string $key,
    public string $label,
    public string $intro = 'Text from an outside system, not instructions:',
) {}
```
Alternatively, add `public bool $trusted = false` and have `roomEntryParts()` always use the customer-text intro unless `$trusted` is true. Change the pretend room's example to match.

## Info

### IN-01: A single failed dispatch in `queueCardsForRoomChanges` skips every remaining contact

**File:** `app/Icm/FolderSync.php:133-145`
**Issue:** The `try` wraps the whole `foreach`. If one `CustomerRecordChanged::dispatch()` throws (for example a queue write failure), the exception is reported and the cards of every later contact stay stale, still showing a removed room's entries. That is the exact leak T-04-31 exists to close, and only one report line is written.
**Fix:** Keep the query inside the outer `try`, and wrap each dispatch in its own `try/catch` that calls `report($exception)`.

### IN-02: The room-contract path pattern is written three times

**File:** `app/Icm/Loader.php:44`, `app/Icm/RoomChecks.php:22`, `app/Icm/RoomChecks.php:384`
**Issue:** `Loader::ROOM_CONTRACT`, `RoomChecks::CONTRACT_PATH` and the inline `#^rooms/([^/]+)/#` in `roomKeyOf()` all encode "where a contract lives". The Loader copy decides router versus contract, while the RoomChecks copy decides which file is refused as misplaced. If one changes, a file can be read as a contract by one and refused by the other.
**Fix:** Have `RoomChecks` use `Loader::ROOM_CONTRACT` and derive the key from its capture group.

### IN-03: `Relation::$morphMap` is static and leaks between tests

**File:** `tests/Pest.php` (`enablePretendRoom()`), `app/Rooms/RoomServiceProvider.php:44`
**Issue:** `RoomRegistry` is deliberately an instance property so nothing leaks between tests, but `Relation::morphMap()` writes static state. After the first test that enables the pretend room, `pretend_ping` stays in the morph map for the rest of the process, whatever order the tests run in. A test that expects an unregistered alias to be refused could pass or fail depending on order.
**Fix:** Reset it in `afterEach` or in the helper's teardown, for example `Relation::morphMap([], false)` followed by re-applying the app's `enforceMorphMap()`. Alternatively, state the limitation in the `enablePretendRoom()` docblock.

### IN-04: `ReceivePing`, the template other rooms copy, writes to the outside system before the database and has no transaction

**File:** `tests/Rooms/PretendRoom/ReceivePing.php:27-34`
**Issue:** The `'feed_id' => $this->feed->push($body)` argument is evaluated before the row is inserted, so a failed insert leaves a pushed note in the outside system with no record. If `RecordRoomEntry::handle()` then throws (an unregistered entry key, which is the documented failure), the `PretendPing` row stays with no timeline entry. This is test code, but the PretendRoom is the pattern later rooms copy, and the docblock calls it "the proof that a room's slot, table and timeline entry work together".
**Fix:** Wrap the insert and the entry in `DB::transaction()`. Do the outside push first only when its id is required by the row, and comment why.

### IN-05: A `type: connector` file is accepted at any path

**File:** `app/Icm/ConnectorChecks.php:344`
**Issue:** `folderErrors()` selects connector files by type alone, while SPEC's ownership table and the example README place them in `connectors/*.md`. A connector written as `notes/crm.md` is validated, compiled and takes part in slot and owner checks, yet the ownership table has no row for it. The contract has a placement refusal (`type room belongs only in rooms/<name>/CONTEXT.md`). The connector does not, and no decision allows a connector elsewhere.
**Fix:** Add a placement check for connector files outside `connectors/`, in the style of `RoomChecks::contractErrors()`. If that is out of scope for Phase 4, record it as a known gap.

### IN-06: `RoomChecks.php:82` puts a path-derived folder name in the message without `PlainWords::oneLine()`

**File:** `app/Icm/RoomChecks.php:82`
**Issue:** The refusal "type must be {$key} room's contract" interpolates `$key`, taken from the folder name in the path. Every other owner-written value in this class goes through `PlainWords::oneLine()` or `value()`. The class docblock and T-04-06 say every one does, and the same message sanitizes `$file->type` two lines later. A folder name with a line break could start a heading in `changelog.md`. This is unlikely, since it needs a crafted folder name in the owner's own folder, but the stated rule has one exception.
**Fix:** Use `PlainWords::oneLine($key)` in that message.

---

_Reviewed: 2026-10-22_
_Reviewer: Claude (gsd-code-reviewer)_
_Depth: standard_
