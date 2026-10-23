---
phase: 04-room-standard
plan: 05
subsystem: rooms
tags: [laravel, eloquent, query-scope, activities, customer-card, folder-sync, filament, tdd, pest]

requires:
  - phase: 04-room-standard
    provides: "RoomRegistry::timelineEntry(), Room::timelineEntries/tables/morphMap, ActiveConfig::hasRoom()/roomKeys(), the pretend room and its PretendFeed slot (plans 04-01, 04-02); FolderSync as plans 04-03 and 04-04 left it"
provides:
  - "activities.room and activities.entry (nullable strings) and ActivityType::RoomEntry"
  - "App\\Rooms\\RecordRoomEntry: the one guarded way room code writes a timeline entry"
  - "Activity::visibleOnTimeline() scope, used by Contact::timeline() and CustomerCard::render()"
  - "CustomerCard room-entry arm and Filament contact-page badge using the room's registered label"
  - "FolderSync::queueCardsForRoomChanges(): a sync that turns a room off or on redraws each affected stored card"
  - "Pretend room table pretend_pings (test-only migration), PretendPing model, ReceivePing action; enablePretendRoom() runs the pending migration"
affects: [04-06, 05-room-phases, 06-room-phases]

actuals:
  tokens: 9326
  tasks: 3
  commits: 6

tech-stack:
  added: []
  patterns:
    - "A room writes to the timeline only through RecordRoomEntry, which does nothing while the room is off and throws for an entry key the room never registered"
    - "One read-time scope decides what the timeline shows; rows of a removed room are hidden, never deleted"
    - "The sync redraws stored cards when the room set changes, as a best-effort step after the version is already live"
    - "A room's test-only table: loadMigrationsFrom() in the test provider plus artisan('migrate') inside the test's transaction"

key-files:
  created:
    - database/migrations/2026_10_22_000001_add_room_columns_to_activities_table.php
    - app/Rooms/RecordRoomEntry.php
    - tests/Feature/Rooms/RoomTimelineTest.php
    - tests/Rooms/PretendRoom/PretendPing.php
    - tests/Rooms/PretendRoom/ReceivePing.php
    - tests/Rooms/PretendRoom/migrations/2026_10_22_000002_create_pretend_pings_table.php
  modified:
    - app/Enums/ActivityType.php
    - app/Models/Activity.php
    - app/Models/Contact.php
    - app/Records/CustomerCard.php
    - app/Filament/Resources/Contacts/Schemas/ContactInfolist.php
    - app/Icm/FolderSync.php
    - tests/Rooms/PretendRoom/PretendRoomServiceProvider.php
    - tests/Pest.php
    - tests/Feature/Rooms/PretendRoomBootTest.php
    - docs/sprints/sprint-1.1.md

key-decisions:
  - "Stored cards follow the room set at the sync: when a sync makes live a version whose room keys differ from the previous live version's, CustomerRecordChanged is dispatched once for each contact with activities rows of a room that went off or on (for Ian's review; the alternative not taken is below)"
  - "RecordRoomEntry is the named chokepoint for a room's timeline writes (CLAUDE.md agent-action rule): it refuses when the room is not in the live version and when the entry key is not registered, and every write is followed by CustomerRecordChanged"
  - "visibleOnTimeline keeps rows with room null or a room in ActiveConfig::roomKeys(), grouped in one where-closure so it combines safely with other conditions"
  - "A card that cannot be queued is reported and never fails a sync whose version is already live (the rule recorded() already follows for the changelog)"

patterns-established:
  - "Room timeline entry: the room registers a TimelineEntryType (key, label, intro); the card and the contact page both look the label up through RoomRegistry and fall back to Room entry"
  - "Test-only room tables never appear in database/migrations/"

requirements-completed: [ROOM-02, ROOM-05]

coverage:
  - id: D1
    description: "RecordRoomEntry writes a room_entry activities row with the room and entry keys, and the customer card draws it with the room's registered label and intro (the pretend room's ping_received shows as Pretend ping under System note:)"
    requirement: ROOM-02
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/RoomTimelineTest.php#writes a room entry and the customer card draws it with the label and intro the room registered"
        status: pass
    human_judgment: false
  - id: D2
    description: "An entry the room's code no longer registers is drawn as Room entry with its summary on the card and as a Room entry badge on the contact page, and never throws"
    requirement: ROOM-02
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/RoomTimelineTest.php#draws a room entry its room no longer registers as a plain room entry, without throwing"
        status: pass
      - kind: integration
        ref: "tests/Feature/Rooms/RoomTimelineTest.php#draws the badge of an entry its room no longer registers as Room entry"
        status: pass
    human_judgment: false
  - id: D3
    description: "D-08: an idle room writes nothing (RecordRoomEntry returns null, no row) and an unregistered entry key throws LogicException"
    requirement: ROOM-02
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/RoomTimelineTest.php#writes nothing while the room is not in the live version and #refuses an entry key the room never registered"
        status: pass
      - kind: integration
        ref: "tests/Feature/Rooms/PretendRoomBootTest.php#does nothing with a ping while the pretend room is not in the live version"
        status: pass
    human_judgment: false
  - id: D4
    description: "Decision 3: entries of a removed room are hidden on the card and the contact timeline, never deleted (row count unchanged), shown again when the folder returns, and rows with room null always show, including before any version is live"
    requirement: ROOM-02
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/RoomTimelineTest.php#hides a removed room's entries wherever the timeline is read and deletes nothing, #shows the entries again when the room folder comes back, #shows rows with no room and hides room rows before any version is live, without throwing"
        status: pass
    human_judgment: false
  - id: D5
    description: "The sync redraws the stored card in records/ for each contact holding a room's entries when it turns the room off or on, queues nothing when the room set is unchanged or the folder is unchanged, and the stored card file loses and regains the entry"
    requirement: ROOM-02
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/RoomTimelineTest.php#redraws the cards of the contacts holding a room's entries when a sync turns the room off or on, and no others, #redraws nothing when a sync leaves the room set as it was, or changes nothing at all, #keeps the stored card in records/ in step with the room"
        status: pass
    human_judgment: false
  - id: D6
    description: "The pretend room's table pretend_pings exists only inside the test that enabled the room, and ReceivePing pushes a note through the PretendFeed slot, stores the row with the feed id and writes a ping_received entry whose subject is that row (morph alias pretend_ping)"
    requirement: ROOM-05
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/PretendRoomBootTest.php#has the pretend room's table only inside the test that enabled the room and #sends a ping through the slot, stores it in the room's table and puts it on the timeline"
        status: pass
    human_judgment: false
  - id: D7
    description: "No existing card, timeline or infolist test changed, and the whole suite passes"
    requirement: ROOM-02
    verification:
      - kind: unit
        ref: "php artisan test --compact (1942 passed)"
        status: pass
    human_judgment: false
  - id: D8
    description: "The four walk-through lines in sprint-1.1.md (query scope, enum case, migrations in a test, merging model nicknames) explain the new Laravel concepts clearly enough for Ian"
    requirement: ROOM-05
    verification:
      - kind: other
        ref: "grep -c '(plan 04-05)' docs/sprints/sprint-1.1.md"
        status: pass
    human_judgment: true
    rationale: "Whether the wording is clear to Ian is a judgment no test makes"

duration: 7min
completed: 2026-10-23
status: complete
plan_head_before: f426f728e2c28b8a68c2ecc8d26f399104b1db54
plan_head_after: dd0a88f0770dcc18e25f0c79c7da645ae32d48fc
commits: 6
---

# Phase 4 Plan 05: Room Timeline Entries Summary

**Rooms write customer-timeline entries through one guarded action (`RecordRoomEntry`, `ActivityType::RoomEntry`, nullable `room`/`entry` columns); a single `Activity::visibleOnTimeline()` scope hides a removed room's entries on the card and contact page without deleting them, and the sync redraws the stored card whenever it turns a room off or on.**

## Performance

- **Duration:** 7 min
- **Started:** 2026-10-23T01:21:38Z
- **Completed:** 2026-10-23T01:29:00Z
- **Tasks:** 3 (Task 1 tracer, Task 2 visibility and card redraw, Task 3 pretend table and slot in use), 6 commits (RED and GREEN for each)
- **Files modified:** 16 (6 created, 10 modified)

## Accomplishments

- A room writes timeline entries only through `App\Rooms\RecordRoomEntry`. It returns null and writes nothing while the room is not in the live version, throws `LogicException` for an entry key the room never registered, and dispatches `CustomerRecordChanged` after a write. The pretend room's `ping_received` draws as `### ... · Pretend ping` under `System note:` with the summary quoted through the existing `MarkdownText::quote()`.
- `Activity::visibleOnTimeline()` (`#[Scope]`) keeps rows with `room` null or a room in `ActiveConfig::roomKeys()`. `Contact::timeline()` (the Filament contact page) and `CustomerCard::render()` both apply it. Removing the room folder hides the entries, the `activities` row count is unchanged, and adding the folder back shows them again. Before any version is live, room-null rows show and room rows stay hidden.
- The contact page badge for a room entry shows the room's registered label (`Pretend ping`), `Room entry` when the room's code no longer registers it, and the type's own value for every other type, unchanged.
- `FolderSync::queueCardsForRoomChanges()` runs on the Activated path right after `ActiveConfig` is forgotten. It compares the previous and new live versions' room keys, and for each contact with activities rows of a room that went off or on dispatches `CustomerRecordChanged` once. An unchanged room set, an unchanged folder and a rejected sync queue nothing. The stored card file under `records/customers/` loses `Pretend ping` when the room is removed and regains it when the folder returns.
- The pretend room now has a table, model and action: `pretend_pings` (test-only migration run inside the test's transaction by `enablePretendRoom()`), `PretendPing` (morph alias `pretend_ping`) and `ReceivePing`, which pushes a note through the `PretendFeed` slot (fake driver), stores the row with the feed id and writes the timeline entry with the ping as subject. The table is absent before `enablePretendRoom()` and `find database/migrations -name '*pretend*'` prints nothing.
- Whole suite: 1942 tests pass (1926 after 04-04, plus 16 new); `exampleFolderFileCount()` still returns 14.

## Task Commits

1. **Task 1 RED: a room writes a timeline entry the card draws** - `8ccac40` (test)
2. **Task 1 GREEN: rooms write timeline entries the card draws** - `aeb107c` (feat)
3. **Task 2 RED: removed rooms' entries are hidden, not deleted** - `ec64a8a` (test)
4. **Task 2 GREEN: one read rule hides a removed room's timeline entries** - `469a3ac` (feat)
5. **Task 3 RED: the pretend room's table and slot in use** - `92a42d8` (test)
6. **Task 3 GREEN: pretend room table and slot in use** - `dd0a88f` (feat)

**Plan metadata:** the docs commit that carries this file (docs: complete plan)

## TDD Gate Compliance

All three tasks produced a `test(04-05)` commit before the matching `feat(04-05)` commit. No REFACTOR commit was needed.

**RED evidence** (`gsd_run check tdd-red-evidence` returned `RED_EVIDENCE_OK` for each, JUnit XML from `pest --log-junit`):

- **Task 1:** target `it refuses an entry key the room never registered`. It failed because `App\Rooms\RecordRoomEntry` did not exist yet, so no `LogicException` naming the entry key was thrown. All 5 tests in the file failed, 4 of them erroring with `Target class [App\Rooms\RecordRoomEntry] does not exist` or `Undefined constant ActivityType::RoomEntry`, the expected RED for a class and enum case this task adds. Semantic assessment: the target executed and failed on the planned assertion for the intended reason (the behavior is absent), not on a syntax, fixture or load fault.
- **Task 2:** target `it hides a removed room's entries wherever the timeline is read and deletes nothing`. It failed on `Expecting the card not to contain 'Pretend ping'`, since no read rule existed. 6 of 13 tests failed on assertions; the 7 that passed were the Task 1 tests, a "shows the entries again" case and a "redraws nothing when the room set is unchanged" case, which hold trivially before the change. Semantic assessment: executed and failed on the planned assertion.
- **Task 3:** target `it has the pretend room's table only inside the test that enabled the room`. It failed on `Failed asserting that two arrays are identical`, because the pretend Room's `tables` list was empty where `['pretend_pings']` was expected. The other two new tests errored on the missing `ReceivePing` class, the expected RED for a class this task adds. Semantic assessment: the target executed and failed on the planned assertion.

**Tracer feedback gate:** Task 1 is `type="tracer"`. The run is interactive, `workflow.human_verify_mode` is `end-of-phase` and the tracer's `<verify>` is automated only, so the gate re-ran `tests/Feature/Rooms tests/Feature/Icm tests/Feature/Filament tests/Unit` (820 tests) against the committed Task 1 files, passed, and expansion proceeded.

## Files Created/Modified

- `database/migrations/2026_10_22_000001_add_room_columns_to_activities_table.php` - nullable `room` and `entry` columns, no foreign key, no money column; `down()` drops both
- `app/Enums/ActivityType.php`, `app/Models/Activity.php` - `RoomEntry` case; `room` and `entry` fillable; the `visibleOnTimeline` scope
- `app/Rooms/RecordRoomEntry.php` - the one way room code writes a timeline entry
- `app/Records/CustomerCard.php` - `RoomRegistry` in the constructor, the `RoomEntry` arm and `roomEntryParts()`, and `visibleOnTimeline()` on the activities query
- `app/Models/Contact.php` - `timeline()` applies the scope; `activities()` stays unscoped for writers
- `app/Filament/Resources/Contacts/Schemas/ContactInfolist.php` - the type badge formatter
- `app/Icm/FolderSync.php` - `queueCardsForRoomChanges()` on the Activated path
- `tests/Feature/Rooms/RoomTimelineTest.php` - 13 tests across Tasks 1 and 2
- `tests/Feature/Rooms/PretendRoomBootTest.php` - 3 tests added at the end (the table, the ping, the idle room); no existing test touched
- `tests/Rooms/PretendRoom/PretendRoomServiceProvider.php`, `PretendPing.php`, `ReceivePing.php`, `migrations/2026_10_22_000002_create_pretend_pings_table.php` - the pretend room's entry type, table, model, action and test-only migration
- `tests/Pest.php` - `enablePretendRoom()` runs the pending migration
- `docs/sprints/sprint-1.1.md` - four walk-through lines marked `(plan 04-05)`

## Decisions Made

- **Stored cards follow the room set at the sync (for Ian's review).** The stored card in `records/` is drawn through `visibleOnTimeline()` only when it is rendered, so a sync that changes the room set queues a redraw of each affected card: `CustomerRecordChanged` once per contact with rows of a room that went off or on. A removed room's entries leave the card the owner's AI reads at that sync, and return with the folder. The alternative not taken was to let stored cards catch up at the customer's next event or at `records:rebuild`, which would leave a removed room's entries on the card until then.
- **Chokepoint named.** The plan adds an agent-adjacent write (a room writing to the activities record). Its chokepoint is `App\Rooms\RecordRoomEntry`: it refuses without the check this kind needs (the room is in the live version, the entry key is registered) and the row it writes records the room and entry that wrote it.
- `visibleOnTimeline` groups its conditions in one where-closure, so `room is null OR room in (...)` can never leak into other conditions on the same query.
- Pretend model `PretendPing` has no factory: it is a test-only model created only through `ReceivePing`, so a factory would be dead code.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Test helper rendered the stored card before the note existed**
- **Found during:** Task 2 (GREEN, first run)
- **Issue:** `roomTimelineContactWithEntryAndNote()` in my own new `RoomTimelineTest.php` wrote the room entry first and the room-null note second. The card is drawn when the entry is written, before the note exists, so the stored-card test saw a card without the note. The product behavior was correct; the test setup was wrong.
- **Fix:** The helper creates the note first, then writes the entry.
- **Files modified:** `tests/Feature/Rooms/RoomTimelineTest.php` (a file this plan created; it went in with the Task 2 GREEN commit)
- **Verification:** `tests/Feature/Rooms/RoomTimelineTest.php` passes (13 tests)
- **Committed in:** `469a3ac`

---

**Total deviations:** 1 auto-fixed (1 bug in the plan's own new test setup)
**Impact on plan:** None. No existing test was edited; no file outside the plan's list changed.

## Issues Encountered

None beyond the deviation above.

## Known Stubs

None. No placeholder value flows to any screen from this plan.

## Human Checks Carried to UAT

None. This plan has no `<human-check>`. The contact page badge was verified through a Livewire render in the automated suite; the keyboard-only and screen reader walk of the owner screens belongs to the accessibility plans and UAT later in the phase.

## Concepts Explained for Ian

Each is also one line in the walk-through log at the end of `docs/sprints/sprint-1.1.md`.

- **A query scope.** `visibleOnTimeline` on `Activity` is a named filter you can add to any query for activities: `$contact->activities()->visibleOnTimeline()`. It keeps rows whose `room` is empty (everything v1.0 writes) and rows of a room that is in the live version. Both the contact page (`Contact::timeline()`) and the card (`CustomerCard::render()`) call it, so the rule about a removed room lives in one place and the two screens cannot disagree. Rows are only hidden when read, never deleted.
- **Adding an enum case without touching old rows.** `ActivityType::RoomEntry` is one more case on the list. `Activity` casts its `type` column to that enum, so every existing row still turns into the case it always did; the new `room` and `entry` columns say which room wrote a `room_entry` row and which of its kinds it is.
- **Migrations in a test.** The pretend room's table is made by a migration that lives only under `tests/`. `loadMigrationsFrom()` in the test provider adds that folder to where Laravel looks, and `enablePretendRoom()` then runs `artisan('migrate')` inside the test's own transaction, which is rolled back at the end, so the table exists only in the test that asked for the room.
- **Merging model nicknames.** `Relation::morphMap()` in `RoomServiceProvider::boot()` adds a room's short model names (`pretend_ping`) to the list `AppServiceProvider` enforces, so a room's own model can be the subject of a timeline row. Calling `enforceMorphMap()` again would replace the list instead.

## User Setup Required

None - no external service configuration required.

## Threat Flags

None. Every new surface is in the plan's register: T-04-18 (room text quoted through `MarkdownText::quote()`), T-04-19 (rows never deleted, row count asserted), T-04-20 (`RecordRoomEntry` guards), T-04-21 (`roomKeys()` never throws before the first sync), T-04-31 (stored card redrawn at the sync; a queueing failure is reported and never fails the sync).

## Next Phase Readiness

- Plan 04-06 can use `RecordRoomEntry` and `Activity::visibleOnTimeline()` as they are; real rooms register a `TimelineEntryType`, add their model aliases through `Room::morphMap`, and call `RecordRoomEntry::handle()` for every timeline write.
- `docs/BLUEPRINT.md` needed no edit: it already lists timeline entries in the room standard block (plan note).
- STATE.md's "Plan: N of 9" counter was not hand-edited; it was updated only with the `state` commands.

## Self-Check: PASSED

All 6 created files exist on disk, commits `8ccac40`, `aeb107c`, `ec64a8a`, `469a3ac`, `92a42d8` and `dd0a88f` are on the branch, `check evaluation-scope --plan 04-05 --commits-only` resolved with commits, every task's acceptance greps pass, and `tests/Feature/Rooms`, `tests/Feature/Records`, `tests/Feature/Filament`, `tests/Feature/Icm` and the whole suite (1942 tests) pass.

---
*Phase: 04-room-standard*
*Completed: 2026-10-23*
