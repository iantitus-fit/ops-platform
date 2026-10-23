---
phase: 04-room-standard
reviewed: 2026-10-23T00:00:00Z
depth: standard
files_reviewed: 18
files_reviewed_list:
  - app/Icm/ActiveConfig.php
  - app/Icm/ConnectorChecks.php
  - app/Icm/FolderSync.php
  - app/Icm/RoomChecks.php
  - app/Records/CustomerCard.php
  - app/Rooms/RoomRegistry.php
  - app/Rooms/RoomServiceProvider.php
  - app/Rooms/TimelineEntryType.php
  - docs/BLUEPRINT.md
  - tests/Feature/Rooms/ConnectorFileTest.php
  - tests/Feature/Rooms/PretendRoomBootTest.php
  - tests/Feature/Rooms/RoomContractTest.php
  - tests/Feature/Rooms/RoomSectionTest.php
  - tests/Feature/Rooms/RoomTimelineTest.php
  - tests/Pest.php
  - tests/Rooms/PretendRoom/PretendRoomServiceProvider.php
  - tests/Rooms/PretendRoom/ReceivePing.php
  - tests/Unit/Rooms/RoomRegistryTest.php
findings:
  critical: 0
  warning: 2
  info: 2
  total: 4
status: issues_found
---

# Phase 4: Code Review Report (incremental, fix commits 6fd3617..9d6eaeb)

**Reviewed:** 2026-10-23
**Depth:** standard
**Files Reviewed:** 18
**Status:** issues_found

## Summary

I reviewed the ten fix commits against `ccb2b4e` and checked each fix against the finding it closes.

Fixes confirmed correct and free of regressions:
- **WR-02:** `compiled['files'][*]['router']` has been written by `FolderSync::compile()` since Sprint 1 (commit 00c0325), so no stored compiled version lacks the key.
- **WR-03:** the section, handle and timeline-entry guards behave as described. `RoomSection::roomKey()` is static, so the call in `guardSections()` is valid.
- **WR-04:** `TimelineEntryType::introLine()` is the single source of the intro line, and `CustomerCard` falls back to the untrusted line for an unregistered entry.
- **IN-01:** the per-dispatch try/catch is correct. The query failure path still returns early.
- **IN-02 and IN-06:** `Loader::ROOM_CONTRACT` is public, and its capture group is used correctly.
- **IN-03:** the `Relation::morphMap([], false)` reset is safe. Only Feature tests boot an app, and each one re-runs `AppServiceProvider::boot()`. The Unit tests never touch the morph map.
- **IN-04:** the transaction test works under `RefreshDatabase` because the nested transaction becomes a savepoint.
- **IN-05:** `str_starts_with($file->path, 'connectors/')` matches how the Loader writes paths.

The WR-01 fix has two remaining gaps (WR-05, WR-06). Both are cases the original finding described and the split guard does not cover. There are no blocking defects.

## Warnings

### WR-05: The "room may not replace an existing nickname" check works only when the room's provider boots after `AppServiceProvider`

**File:** `app/Rooms/RoomServiceProvider.php:47-56`, `bootstrap/providers.php`
**Issue:** The boot-time collision check compares the room's aliases with `Relation::getMorphedModel()`. That only sees the core aliases if `AppServiceProvider::boot()` has already run. Laravel boots providers in registration order. `bootstrap/providers.php` lists `AppServiceProvider` first today, but nothing enforces it, and the CLAUDE.md map says real rooms register in that same file. Any room listed above `AppServiceProvider` boots first, so the check finds nothing. `AppServiceProvider` then calls `enforceMorphMap()`, which merges as `$app + $existing`, so the app's alias wins and the room's alias is silently dropped. The room's rows are then written under an alias that resolves to the wrong model, which is exactly the failure WR-01 set out to prevent. The registry docblock mentions the ordering assumption, but the code does not check it.
**Fix:** Run the check and the merge after every provider has booted, so the order of the list stops mattering:
```php
public function boot(): void
{
    $this->app->booted(function (): void {
        $morphMap = $this->room()->morphMap;

        foreach ($morphMap as $alias => $class) {
            $existing = Relation::getMorphedModel($alias);

            if ($existing !== null && $existing !== $class) {
                throw new LogicException(/* same message */);
            }
        }

        Relation::morphMap($morphMap);
    });
}
```
`booted()` runs the callback straight away if the app has already booted, so `enablePretendRoom()` and the boot tests keep working. The alternative is to state the ordering rule in `bootstrap/providers.php` and add a test that fails when a room provider precedes `AppServiceProvider`.

### WR-06: A room can map an existing model class under a new alias, which changes the alias that model is stored under

**File:** `app/Rooms/RoomRegistry.php:253-266` (`guardMorphMap`), `app/Rooms/RoomServiceProvider.php:47-58`
**Issue:** Both guards check the alias (the key) only. The class (the value) is never checked. `Relation::morphMap($map)` merges as `$map + static::$morphMap`, so the room's entries come first. `Model::getMorphClass()` returns `array_search(static::class, $morphMap, true)`, the first alias for the class. A room that declares `morphMap: ['customer' => Contact::class]`, or two rooms that map the same class under different aliases, passes every guard. From then on `Contact` is written to `activities.subject_type` as `customer` instead of `contact`. Old rows (`contact`) and new rows (`customer`) coexist, and if that room is later removed, every `customer` row stops resolving and Laravel's enforced morph map throws. Non-string or non-model values, such as `['x' => 'not a class']`, are also accepted until something tries to load them.
**Fix:** In `guardMorphMap()`, require each value to be a string naming a `Model` subclass when the class exists, with the same "skip when `class_exists()` is false" allowance the section guard uses. Refuse a class that another room already maps. In `RoomServiceProvider::boot()`, refuse a class already present in `Relation::morphMap()` under a different alias:
```php
$alias = array_search($class, Relation::morphMap(), true);

if ($alias !== false && $alias !== $aliasBeingAdded) {
    throw new LogicException("Room [{$key}] maps [{$class}], which already has the nickname [{$alias}].");
}
```
Add registry and boot tests for both cases.

## Info

### IN-07: The "does not carry the pretend room's morph alias into the next test" test is only meaningful when it runs straight after its neighbour

**File:** `tests/Feature/Rooms/PretendRoomBootTest.php:156-168`
**Issue:** The IN-03 regression test depends on the previous test having enabled the room in the same process. Run alone with `--filter`, or under random order or parallel runs, it asserts `pretend_ping` is null when nothing ever added it, so it passes whether or not the `afterEach` reset exists. The fixer's report says both tests fail without the reset, which holds only for the declared order.
**Fix:** Do both halves in one test. Enable the room, then run the teardown reset explicitly and assert the alias is gone:
```php
enablePretendRoom();
expect(Relation::getMorphedModel('pretend_ping'))->toBe(PretendPing::class);
Relation::morphMap([], false);
expect(Relation::getMorphedModel('pretend_ping'))->toBeNull();
```
That checks the reset call itself. To check that `Pest.php`'s `afterEach` really runs it, keep the paired tests and mark them `->depends()` or put them in one `describe` block.

### IN-08: A misplaced connector file can take the slot or kind from the correctly placed one and get it blamed

**File:** `app/Icm/ConnectorChecks.php:31-33`, `:58-63`
**Issue:** The IN-05 refusal is added in `fileErrors()`, but `folderErrors()` still selects connector files by type alone. A misplaced file whose path sorts before `connectors/` (for example `a/crm.md`) takes the slot or kind first. The correctly placed `connectors/crm.md` then gets "the pretend_feed slot is already filled by a/crm.md" or "customers is owned by both a/crm.md and connectors/crm.md". The sync is refused either way, and the placement error is also listed, so this is confusing wording, not a wrong outcome.
**Fix:** In `folderErrors()`, filter to files whose path starts with `connectors/`, since a misplaced file has already been refused. That way it cannot be named as another file's rival.

---

_Reviewed: 2026-10-23_
_Reviewer: Claude (gsd-code-reviewer)_
_Depth: standard_
