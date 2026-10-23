---
phase: 04-room-standard
fixed_at: 2026-10-23T00:00:00Z
review_path: .planning/phases/04-room-standard/04-REVIEW.md
iteration: 1
findings_in_scope: 4
fixed: 4
skipped: 0
status: all_fixed
---

# Phase 4: Code Review Fix Report

**Fixed at:** 2026-10-23
**Source review:** .planning/phases/04-room-standard/04-REVIEW.md
**Iteration:** 1 (this overwrites the earlier report, which covered WR-01 to IN-06)

**Summary:**
- Findings in scope: 4
- Fixed: 4
- Skipped: 0

**Where verification ran:** in the main checkout. `workflow.use_worktrees` is `false`, so no worktree was created. After the fixes, the full suite (`php artisan test --compact`) passed: 1978 tests, 16014 assertions, 0 failures (1 pre-existing warning). Each logic fix had its failing test written first and seen failing before the fix.

## Fixed Issues

### WR-05: The "room may not replace an existing nickname" check works only when the room's provider boots after `AppServiceProvider`

**Files modified:** `app/Rooms/RoomServiceProvider.php`, `tests/Feature/Rooms/PretendRoomBootTest.php`, `docs/sprints/sprint-1.1.md`
**Commit:** b05fc52
**Applied fix:** `RoomServiceProvider::boot()` now does the alias check and the `Relation::morphMap()` merge inside `$this->app->booted(...)`, so it runs after every provider has booted, whatever order `bootstrap/providers.php` lists them in. If the app has already booted, Laravel runs the callback at once, so `enablePretendRoom()` and the existing boot tests work unchanged. The new test mocks the app so the callback is held back, boots the provider before `AppServiceProvider`, then runs the callback and expects the refusal. It failed before the fix (the callback was never queued). Status: fixed, requires human verification (logic change, ordering behaviour).

New Laravel concept, in plain words: `$this->app->booted($callback)` stores a function that Laravel calls once all providers have finished booting, or calls straight away if booting is already over. That makes the room's check wait until the app's own nicknames are in place. One line was added to the walk-through log at the end of `docs/sprints/sprint-1.1.md`, the newest sprint file.

### WR-06: A room can map an existing model class under a new alias, which changes the alias that model is stored under

**Files modified:** `app/Rooms/RoomRegistry.php`, `app/Rooms/RoomServiceProvider.php`, `tests/Unit/Rooms/RoomRegistryTest.php`, `tests/Feature/Rooms/PretendRoomBootTest.php`
**Commit:** 4c4dbae
**Applied fix:** `RoomRegistry::guardMorphMap()` now requires each value to be a string. When the class exists, it must be an Eloquent `Model` subclass; a class name that does not exist is let through, the same allowance `guardSections()` uses for placeholder names. It also refuses a class mapped under two aliases in one room, or already mapped by another room. `RoomServiceProvider::boot()` refuses a class that already has a different nickname in `Relation::morphMap()`, such as `['customer' => Contact::class]` when `contact` is already there. Tests: registry tests for a non-string value, a non-model class, the same class in two rooms and the same class twice in one room; a boot test for the `Contact` case, which also asserts `Contact` still stores as `contact`. All four failed before the fix. Status: fixed, requires human verification (logic change).

### IN-07: The "does not carry the pretend room's morph alias into the next test" test is only meaningful when it runs straight after its neighbour

**Files modified:** `tests/Feature/Rooms/PretendRoomBootTest.php`
**Commit:** 3d7bfd7
**Applied fix:** I replaced the order-dependent test with a self-contained one, titled "drops the pretend room's morph alias when the morph map is reset, as tests/Pest.php does after every Feature test". It enables the room, asserts `pretend_ping` resolves, calls `Relation::morphMap([], false)`, and asserts it is gone. It passes alone under `--filter` and in any order. This tests the reset call itself. It does not independently prove that the `afterEach` in `tests/Pest.php` runs it, and I did not add a `depends()` pair for that.

### IN-08: A misplaced connector file can take the slot or kind from the correctly placed one and get it blamed

**Files modified:** `app/Icm/ConnectorChecks.php`, `tests/Feature/Rooms/ConnectorFileTest.php`
**Commit:** 41a4800
**Applied fix:** `folderErrors()` now keeps only connector files whose path starts with `connectors/`. A misplaced file such as `a/crm.md` is refused once, by `fileErrors()`, and is never named as the rival of `connectors/crm.md`. The new test lays `a/crm.md` and `connectors/crm.md` with the same slot and the same owned kind, and expects only the placement refusal. Before the fix it also produced the confusing slot and owner lines.

## Skipped Issues

None. G-04-1 (the raw path with a line break in refusal lines, `ValidationReport.php:45`) was not in this review and stays deferred to Phase 5. No fix reopened a recorded decision, and no BLUEPRINT change was needed.

---

_Fixed: 2026-10-23_
_Fixer: Claude (gsd-code-fixer)_
_Iteration: 1_
