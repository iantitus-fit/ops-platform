---
phase: 04-room-standard
verified: 2026-10-23T21:30:00Z
status: passed
score: 6/6 must-haves verified
covered_files:
  - ".github/workflows/tests.yml"
  - ".planning/phases/04-room-standard/04-01-PLAN.md"
  - ".planning/phases/04-room-standard/04-01-SUMMARY.md"
  - ".planning/phases/04-room-standard/04-02-PLAN.md"
  - ".planning/phases/04-room-standard/04-02-SUMMARY.md"
  - ".planning/phases/04-room-standard/04-03-PLAN.md"
  - ".planning/phases/04-room-standard/04-03-SUMMARY.md"
  - ".planning/phases/04-room-standard/04-04-PLAN.md"
  - ".planning/phases/04-room-standard/04-04-SUMMARY.md"
  - ".planning/phases/04-room-standard/04-05-PLAN.md"
  - ".planning/phases/04-room-standard/04-05-SUMMARY.md"
  - ".planning/phases/04-room-standard/04-06-PLAN.md"
  - ".planning/phases/04-room-standard/04-06-SUMMARY.md"
  - ".planning/phases/04-room-standard/04-07-PLAN.md"
  - ".planning/phases/04-room-standard/04-07-SUMMARY.md"
  - ".planning/phases/04-room-standard/04-08-PLAN.md"
  - ".planning/phases/04-room-standard/04-08-SUMMARY.md"
  - ".planning/phases/04-room-standard/04-09-PLAN.md"
  - ".planning/phases/04-room-standard/04-09-SUMMARY.md"
  - "app/Enums/OwnableKind.php"
  - "app/Enums/PlatformKind.php"
  - "app/Icm/ActiveConfig.php"
  - "app/Icm/ConnectorChecks.php"
  - "app/Icm/FolderSync.php"
  - "app/Icm/Loader.php"
  - "app/Icm/RoomChecks.php"
  - "app/Icm/Validator.php"
  - "app/Models/Activity.php"
  - "app/Records/CustomerCard.php"
  - "app/Rooms/RecordRoomEntry.php"
  - "app/Rooms/Room.php"
  - "app/Rooms/RoomFileType.php"
  - "app/Rooms/RoomRegistry.php"
  - "app/Rooms/RoomSection.php"
  - "app/Rooms/RoomServiceProvider.php"
  - "app/Rooms/TimelineEntryType.php"
  - "composer.json"
  - "docs/SPEC.md"
  - "package.json"
  - "resources/views/filament/rooms/section.blade.php"
  - "tests/Feature/Rooms/ConnectorFileTest.php"
  - "tests/Feature/Rooms/OrderIndependenceTest.php"
  - "tests/Feature/Rooms/PretendRoomBootTest.php"
  - "tests/Feature/Rooms/RoomAccessibilityTest.php"
  - "tests/Feature/Rooms/RoomContractTest.php"
  - "tests/Feature/Rooms/RoomOwnershipTest.php"
  - "tests/Feature/Rooms/RoomSectionTest.php"
  - "tests/Feature/Rooms/RoomTimelineTest.php"
  - "tests/Rooms/RoomSectionAxe.php"
  - "tests/Unit/Rooms/RoomRegistryTest.php"
covered_digest: "v3:sha256:d31bf27c7d68a684860d6493db63c1308ca865601aa99c5b4583189d33215d22"
behavior_unverified: 0
overrides_applied: 0
re_verification:
  previous_status: human_needed
  previous_score: 6/6
  gaps_closed:
    - "Human item 1 (Postgres and CI leg): UAT test 1 passed. I confirmed with gh that run 39998169425 on gsd/phase-04-room-standard (head 4ee2f19) is success on both pest (pgsql) and pest (sqlite)."
    - "Human item 2 (interrupted or concurrent sync): UAT test 2 passed (two syncs at once, kill -9 mid-run, live version untouched)."
    - "Human item 3 (order independence): UAT test 3 passed, now a permanent test, tests/Feature/Rooms/OrderIndependenceTest.php (fcf66d9)."
  gaps_remaining: []
  regressions: []
deferred:
  - truth: "A file path in a sync refusal is on one line, so a path containing a line break cannot break the stored error line (G-04-1)"
    addressed_in: "Phase 5 (by Ian's recorded decision in 04-UAT.md, not by Phase 5's roadmap text)"
    evidence: "04-UAT.md G-04-1: 'open item for Phase 5, no fix now', severity minor. ROADMAP Phase 5 success criteria do not name it, so it must be carried into Phase 5 planning by hand."
  - truth: "An owner screen showing a room's dashboard section passes a keyboard-only and VoiceOver walk (CLAUDE.md WCAG rule)"
    addressed_in: "Phase 5 UAT, on the first real room section (Ian, 2026-10-23)"
    evidence: "The pretend room's section exists only in tests, so no owner screen shows a room section yet. The automated axe check (A11Y-01) passed here and on both CI legs."
---

# Phase 4: Room Standard Verification Report

**Phase Goal:** Any room can be added to the business folder and be checked, registered and tested the same way, and two systems can never both own the same kind of record.
**Verified:** 2026-10-23
**Status:** passed
**Re-verification:** Yes. The earlier report (human_needed) went stale when two rounds of review fixes (6fd3617 to 41a4800) and a new regression test (fcf66d9) changed covered files. I re-read the current source, re-ran the suite, and folded in the UAT results.

## Goal Achievement

### Observable Truths (ROADMAP success criteria, the contract)

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | A room folder holding only its `CONTEXT.md` passes `icm:sync`. Adding the example file validates it, and a broken example is refused in plain words naming file and field while the previous version stays live. | VERIFIED | `Loader::ROOM_CONTRACT` (`#^rooms/([^/]+)/CONTEXT\.md$#`) is used by `Loader` (line 100: a contract is not treated as a router), `FolderSync::compile()` (line 278, `index.room`) and `RoomChecks` (lines 35, 72). `Validator::fileErrors()` routes `room` to `RoomChecks::contractErrors()` and any registered type to `RoomRegistry::fileType()->checks()`. `Validator::validate()` adds `RoomChecks::errors()` and `ConnectorChecks::folderErrors()` to the report, and a failing report takes the existing reject path. `PretendRoomBootTest` has `empty` and `with its example folder` datasets; `RoomFileTypeTest` pins the plain-words file-and-field refusal. All pass in the full run (see spot-checks). |
| 2 | A room's `CONTEXT.md` names owned files, read files, what it writes to `records/` (may be nothing), and whether it has an outside system. Today's files stay where they are. | VERIFIED | `RoomChecks::contractErrors()` checks `owns`, `reads`, `writes_records` entry by entry (`PATH_LISTS`) and `connectorErrors()`. `Loader` has `NONE_MEANS_EMPTY`. `RoomContractTest` covers the cases. `docs/SPEC.md` has the "Who owns each file" table (line 208) and the room row (line 276), tied to code by `SpecTablesTest`. `examples/example-hvac` has no `rooms/` folder, so today's files are untouched. |
| 3 | A room is one Laravel module registering file types, tables, dashboard section and timeline entries; one test boots it empty and with its example folder, passing on SQLite and Postgres. | VERIFIED | `Room` descriptor (file types, slot, sections, tables, timeline entries, morph map) -> `RoomRegistry` singleton (`AppServiceProvider:52`) -> `RoomServiceProvider::register()` adds it -> `AdminPanelProvider:61` calls `addWidgetsTo()`. `PretendRoomBootTest` carries no driver guard and no skip. SQLite: full suite run here. Postgres: GitHub run 39998169425 on head 4ee2f19 is green for `pest (pgsql)` and `pest (sqlite)`, and 4ee2f19 already holds every source change from the review fixes. The only commit after it that touches non-planning files adds `OrderIndependenceTest.php`. |
| 4 | A room with an outside system declares a connector slot (interface with fake driver); connector files say `owns` and `reads` instead of `objects`. | VERIFIED | `RoomRegistry::guardSlot()` requires slot name plus an existing interface. `ConnectorChecks::fileErrors()` refuses `objects` ("objects is replaced by owns and reads", line 36) and checks `owns` against `OwnableKind` (8 kinds) and `PlatformKind`. The fake driver and interface are test-side (`PretendFeed`, `FakePretendFeed`). SPEC lists tied to the enums by `SpecTablesTest`. |
| 5 | A folder where two connectors own the same kind is refused in plain words and the previous version stays live. | VERIFIED | `ConnectorChecks::ownerErrors()` line 127 emits `{kind} is owned by both {first} and {later}; pick one, the other can read it`, working over files in path order so the later file is blamed. IN-08 (41a4800) now filters `folderErrors()` to files under `connectors/`, so a misplaced file is not named as a rival. `OrderIndependenceTest` pins the exact line `connectors/b.md: customers is owned by both connectors/a.md and connectors/b.md; pick one, the other can read it` for four arrangements and checks the live version is untouched. |
| 6 | Each room's dashboard section, including the D-21 empty line, passes axe (WCAG 2.2 AA), run with the room's boot test in CI; the pretend room proves it. | VERIFIED | `RoomSectionAxe` runs tags `wcag2a, wcag2aa, wcag21a, wcag21aa, wcag22aa` with `target-size` enabled, and fails when `checked` is 0. `section.blade.php` carries `data-room-section` and is shared by all rooms; `RoomSection::getViewData()` draws the empty line for every room. `RoomAccessibilityTest` has no skip and no driver guard. `tests.yml` installs Node 24, `npm ci`, and `npx playwright install --with-deps chromium` on both matrix legs; `playwright` is pinned `1.63.0` and `pestphp/pest-plugin-browser ^5.1` is in `composer.json`. It passed in a real browser in my full run and on both CI legs. |

**Score:** 6/6 roadmap criteria verified. 0 present-but-behavior-unverified.

### Behavior-dependent truths (backstop truths from plans 04-02, 04-04, 04-09)

These were `human_needed` last time. Each now has direct evidence, not just presence.

| Truth | Evidence | Status |
|-------|----------|--------|
| Interrupted or concurrent sync never leaves a half-checked folder live | `FolderSync` takes `Cache::lock('icm:sync', 120)` (line 36) and returns `SyncOutcome::Busy` when it cannot. UAT test 2 (Claude ran it for Ian on a scratch copy): two syncs at once gave one rejected v2 and one "already running"; `kill -9` mid-run left no version row and v1 live; a sync after lock expiry stored rejected v3. Ian marked it pass. | VERIFIED (UAT) |
| Order independence of `owns`/`reads`/`writes_records` and connector files | `tests/Feature/Rooms/OrderIndependenceTest.php`, 3 tests: a valid contract reversed activates with the same owned files; a refused contract reversed gives the same outcome and the same set of lines (line order within one file follows entry order, by Ian's decision); four connector arrangements give one identical refusal. Ran in my full suite. Ian marked UAT test 3 pass. | VERIFIED |
| Postgres leg of criteria 3 and 6 | GitHub run 39998169425, head 4ee2f19, `pest (pgsql)` success and `pest (sqlite)` success (confirmed with `gh run view`). | VERIFIED |

### Plan must-haves spot confirmations

- D-20, the pretend room never ships: `bootstrap/providers.php` lists only `AppServiceProvider` and `AdminPanelProvider`. `git ls-files | grep -i pretend` returns files only under `tests/`.
- D-08, a room turns on and off with the live version: `RoomSection::canView()` asks `ActiveConfig::hasRoom()` at draw time. `RoomServiceProvider` documents that register and boot never read the folder or database.
- D-23, a connector cannot own `texts`, `projects` or `timeline`: `PlatformKind` check in `ConnectorChecks::ownsErrors()` (line 155).
- Ownership: `RoomChecks::ownershipErrors()` covers a file two contracts claim, a file nobody claims, a file whose room is absent, and a room's own file type outside its folder.
- Room timeline rows: removed room entries are hidden at read time, never deleted (`Activity::visibleOnTimeline()`). `FolderSync::queueCardsForRoomChanges()` is called from `sync()` and, after the IN-01 fix, one failed dispatch no longer skips the rest.
- No `TBD`, `FIXME` or `XXX` in any Phase 4 file (grep over `app/Rooms`, `RoomChecks`, `ConnectorChecks`, the enums, the view and the room tests).

### Post-first-verification code changes (the reason for this re-run)

Re-read in the current source, not taken from `04-REVIEW-FIX.md`:

| Fix | Where I saw it | Status |
|-----|----------------|--------|
| WR-01, WR-05, WR-06 (morph alias and model guards, check runs after all providers boot) | `RoomServiceProvider::boot()` wraps the check and merge in `$this->app->booted(...)`; `RoomRegistry::guardMorphMap()` requires string alias and class, Model subclass, one nickname per class, no clashes across rooms | VERIFIED |
| WR-02 (router in a room folder is not an owned file) | `ActiveConfig::roomOwnedFiles()` line 133 adds `$file['router'] === false`; `FolderSync` line 264 writes the `router` key into `compiled.files`, so the read is backed by real data | VERIFIED |
| WR-03 (handles, sections, timeline entries validated) | `RoomRegistry::guardHandles`, `guardSections`, `guardTimelineEntries` called from `add()` | VERIFIED |
| WR-04 (room timeline entries untrusted by default) | `TimelineEntryType::introLine()` returns `UNTRUSTED_INTRO` unless `trusted`; `CustomerCard::roomEntryParts()` uses it for both a registered entry and an entry whose type is no longer registered. Ian confirmed in UAT. | VERIFIED |
| IN-05, IN-08 (connector file only in `connectors/`) | `ConnectorChecks::fileErrors()` line 31 and `folderErrors()` line 61. Ian confirmed IN-05 in UAT. | VERIFIED |

### Required Artifacts

| Artifact | Status | Details |
|----------|--------|---------|
| `app/Rooms/{Room,RoomRegistry,RoomServiceProvider,RoomSection,RoomFileType,TimelineEntryType,RecordRoomEntry}.php` | VERIFIED | Substantive (46 to 338 lines), wired through the registry singleton, the panel hook, and the sync. |
| `app/Icm/RoomChecks.php`, `app/Icm/ConnectorChecks.php` | VERIFIED | Injected into `Validator`'s constructor and called in `validate()` and `fileErrors()`. |
| `app/Enums/OwnableKind.php`, `app/Enums/PlatformKind.php` | VERIFIED | Used by `ConnectorChecks`; compared with SPEC by `SpecTablesTest`. |
| `resources/views/filament/rooms/section.blade.php` | VERIFIED | `data-room-section` present, shared. |
| Test-only pretend room (`tests/Rooms/PretendRoom/*`, `tests/Fixtures/pretend-room/*`, `tests/Rooms/RoomSectionAxe.php`) | VERIFIED | Present; nothing under `app/` or `examples/`. |
| Migration adding `room` and `entry` to `activities` | VERIFIED | Additive; ran on SQLite here and on Postgres in CI. |
| `docs/SPEC.md` (ownership table, kinds tables, room row) | VERIFIED | Lines 208, 234 to 261, 276, 279. |
| `composer.json`, `package.json`, `.github/workflows/tests.yml` | VERIFIED | Match plans 04-07 and 04-08. |

### Key Link Verification

| From | To | Status |
|------|----|--------|
| `Loader::ROOM_CONTRACT` | typed `room` file and `index.room` | WIRED (`Loader:100`, `FolderSync:278`) |
| `Validator::validate()` | `RoomChecks::errors()`, `ConnectorChecks::folderErrors()` | WIRED (line 124) |
| `Validator::fileErrors()` | `contractErrors`, `fileErrors`, registered `RoomFileType::checks()` | WIRED (match arms) |
| `FolderSync::sync()` | `Cache::lock('icm:sync')`, reject path, `queueCardsForRoomChanges()` | WIRED (lines 36, 103) |
| `RoomSection::canView()` | `ActiveConfig::hasRoom()` | WIRED |
| `AdminPanelProvider::panel()` | `RoomRegistry::addWidgetsTo()` | WIRED (line 61) |
| `AppServiceProvider::register()` | `RoomRegistry` singleton | WIRED (line 52) |
| `CustomerCard::roomEntryParts()` | `TimelineEntryType::introLine()` | WIRED |
| Axe helper | `data-room-section` in shared view | WIRED |

### Data-Flow Trace (Level 4)

| Artifact | Data | Source | Status |
|----------|------|--------|--------|
| `RoomSection` empty line | contract `owns` and live owned files | `ActiveConfig::roomContract()` / `roomOwnedFiles()` read the live `IcmVersion.compiled` | FLOWING |
| Customer card room entry | label, trust line, summary | `RoomRegistry::timelineEntry()` plus the `activities` row | FLOWING |

### Behavioral Spot-Checks

| Behavior | Command | Result | Status |
|----------|---------|--------|--------|
| Whole suite, run once, covers the room tests, the browser axe test, `OrderIndependenceTest` | `php artisan test --compact` | 1981 passed, 16035 assertions, exit 0, 1 pre-existing warning | PASS |
| Postgres and SQLite CI legs | `gh run view 39998169425` | head 4ee2f19, conclusion success on both jobs | PASS |

### Probe Execution

Step 7c: SKIPPED. The phase declares no `probe-*.sh` scripts.

### Requirements Coverage

All seven IDs are claimed by a plan (04-01 to 04-09 frontmatter) and marked Complete in REQUIREMENTS.md. REQUIREMENTS.md maps no other ID to Phase 4, so none is orphaned. ROOM-06 to ROOM-09 belong to Phases 5 to 7.

| Requirement | Source Plan(s) | Status | Evidence |
|-------------|----------------|--------|----------|
| ROOM-01 | 04-01, 04-02, 04-03, 04-06 | SATISFIED | Contract folder, empty room syncs, one owner per file (`RoomChecks::ownershipErrors`), SPEC table. |
| ROOM-02 | 04-01, 04-03, 04-05, 04-06 | SATISFIED | File types, tables, section and timeline entries registered through `Room`. |
| ROOM-03 | 04-02, 04-04 | SATISFIED | Slot interface plus fake driver; `connector` field in the contract checked by `connectorErrors()`. |
| ROOM-04 | 04-02 | SATISFIED | `writes_records` checked under `records/`; `none` or `[]` allowed. |
| ROOM-05 | 04-01, 04-05, 04-06 | SATISFIED | Boot test empty and with example; green on SQLite here and on both CI legs. |
| CONN-07 | 04-04 | SATISFIED | `owns`/`reads`, `objects` refused, two-owner refusal in plain words. |
| A11Y-01 | 04-07, 04-08, 04-09 | SATISFIED | Axe on the pretend section, empty and furnished, with an alt-less-image tooth; CI wiring present and green. |

### Anti-Patterns Found

None blocking. No stubs, debt markers or placeholder returns in phase code.

Open items, none of which falsifies a must-have:

| Item | Severity | Notes |
|------|----------|-------|
| G-04-1: a file path containing a line break is carried into the stored error line (`ValidationReport.php:45` joins file and message raw) | Minor | Confirmed in code. Ian deferred it to Phase 5. See Deferred below. |
| G-04-2: after a killed sync the owner sees "A sync is already running. Try again in a moment." for up to 2 minutes (`FolderSync.php:39`) | Minor, wording | Confirmed in code. Safe: nothing stored, live version untouched. Ian put it on the post-UAT list. |
| G-04-3: refusal reads "is a hours file" (`RoomChecks.php:273-274`) | Minor, wording | Confirmed in code. Meaning is correct. Ian put it on the post-UAT list. |
| IN-09: the connector test's "written first/second" arrangements are identical input, because `Loader::load` sorts by name | Info | Content-swap arrangements still guard the blame order. |
| IN-10: test 2's title says "same refusal" but pins two line orders | Info | Naming only; a comment explains it. |

### Deferred Items

| # | Item | Addressed In | Evidence |
|---|------|--------------|----------|
| 1 | G-04-1, path with a line break in a refusal line | Phase 5, by Ian's decision in `04-UAT.md` | Phase 5's ROADMAP success criteria do not mention it, so it needs to be picked up in Phase 5 discuss or plan by hand or it will be lost. |

### Decisions recorded at the transition (Ian, 2026-10-23)

1. **Keyboard and VoiceOver walk:** moves to Phase 5's UAT, on the first real room section. The pretend room's section exists only in tests, so there is no owner screen to walk in Phase 4. The automated axe check (A11Y-01) stays the Phase 4 evidence.
2. **Placeholder wording:** the empty-room line (D-21) and the new placement, ownership and connector refusals keep their placeholder wording until the 2026-10-17 wording pass (closing principle, PROJECT.md). G-04-2 and G-04-3 join that pass.
3. **Decision 3 stands as planned:** a removed room's timeline entries are hidden when read and never deleted, they come back with the folder, and a sync that turns a room off or on redraws the stored cards that hold its entries (plan 04-05, the behaviour chosen for Ian's review).

### Human Verification Required

None for Phase 4. The keyboard and VoiceOver walk is deferred to Phase 5's UAT (decision 1 above). The three items from the earlier report were taken through UAT (3 passed, 0 issues) and I cross-checked the evidence: CI run read back with `gh`, the order test run in the full suite, and the lock and reject path read in `FolderSync`.

One caution for completeness, not a gap: `OrderIndependenceTest` (fcf66d9) was added after the green CI run and has been run on SQLite only. It carries no driver-specific code, and the next push will run it on both legs.

### Gaps Summary

No gaps. Every roadmap success criterion is backed by current code, a passing test, and, for the Postgres and browser parts, a green CI run. Three minor items (G-04-1, G-04-2, G-04-3) are recorded in `04-UAT.md` as open, and Ian has recorded them as not blocking. They are wording and a deferred path-cleaning question, and no truth in the contract depends on them.

---

_Verified: 2026-10-23_
_Verifier: Claude (gsd-verifier)_
