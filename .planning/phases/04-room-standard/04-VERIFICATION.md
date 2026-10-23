---
phase: 04-room-standard
verified: 2026-10-23T02:30:00Z
status: human_needed
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
  - "app/Rooms/RoomRegistry.php"
  - "app/Rooms/RoomSection.php"
  - "app/Rooms/RoomServiceProvider.php"
  - "composer.json"
  - "docs/SPEC.md"
  - "package.json"
  - "resources/views/filament/rooms/section.blade.php"
  - "tests/Feature/Rooms/PretendRoomBootTest.php"
  - "tests/Feature/Rooms/RoomAccessibilityTest.php"
  - "tests/Rooms/RoomSectionAxe.php"
covered_digest: "v3:sha256:b5c6b07c53ce044624ea643620910f1f3877f79080b4131c5ef0a1d5b7c27515"
behavior_unverified: 0
overrides_applied: 0
human_verification:
  - test: "Push the phase branch and open the tests workflow run. Check the pgsql leg and the sqlite leg."
    expected: "Both legs green, including PretendRoomBootTest (empty and with its example folder) and RoomAccessibilityTest (headless Chromium, axe)."
    why_human: "Roadmap success criterion 3 and 6 say the boot test and the axe check pass on SQLite and Postgres. Only SQLite could be run here (no Postgres server or psql on this machine, and the only running Postgres belongs to another project). The tests carry no driver guard and the workflow has a sqlite/pgsql matrix with Node, npm ci and Chromium installed, but a green Postgres run is not something the code can show. Plan 04-09 marks it as a backstop truth."
  - test: "Interrupted or concurrent sync (plan 04-02 backstop truth): start two icm:sync runs at once against a folder with a room contract, and kill one mid-run."
    expected: "Never a half-checked folder going live; a refusal stores a rejected version and the live one is untouched."
    why_human: "Declared as a backstop truth. The room checks run inside the existing Cache::lock('icm:sync') in FolderSync before activation, and the refusal path is tested, but no test races two syncs, so this stays a human decision."
  - test: "Order independence (plan 04-02 and 04-04 backstop truths): reorder entries in owns, reads and writes_records, and reorder connector files in a folder with a two-owner conflict."
    expected: "The same accept or refuse result; the refusal blames the later file in path order."
    why_human: "Declared as backstop truths. RoomOwnershipTest proves the same refused folder gives the same lines twice, not that every reordering gives the same result."
---

# Phase 4: Room Standard Verification Report

**Phase Goal:** Any room can be added to the business folder and be checked, registered and tested the same way, and two systems can never both own the same kind of record.
**Verified:** 2026-10-23
**Status:** human_needed (all automated checks pass; Postgres CI leg and three declared backstop truths need a person)
**Re-verification:** No, initial verification

## Goal Achievement

### Observable Truths (roadmap success criteria, the contract)

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | A room folder holding only its `CONTEXT.md` passes `icm:sync`. Adding the example file validates it, and a broken example is refused in plain words naming file and field while the previous version stays live. | VERIFIED | `Loader::ROOM_CONTRACT` (`#^rooms/([^/]+)/CONTEXT\.md$#`) reads only the contract as a typed file; `FolderSync::compile()` has the `room` arm and per-registered-type lists. `RoomFileTypeTest` row `pinned: yes` expects `rooms/pretend/notes/first-note.md: pinned must be true or false (you wrote yes)`. `PretendRoomBootTest` boots empty and with example. Suite run: 220/220 in Rooms, Unit/Rooms and ArchitectureTest. |
| 2 | A room's `CONTEXT.md` names owned files, read files, what it writes to `records/` (may be nothing), and whether it has an outside system. Today's files stay where they are. | VERIFIED | `RoomChecks::contractErrors()` requires `owns`, `reads`, `writes_records`, `connector`; `Loader::NONE_MEANS_EMPTY` accepts `none` as `[]`. `RoomContractTest` covers missing field, paths inside folder, `records/` statement, unknown room, connector equals slot. `docs/SPEC.md` "Who owns each file" table (line 208) tied to code by `SpecTablesTest`. `examples/example-hvac` has no `rooms/` folder and `exampleFolderFileCount()` still returns 14. `Validator::SINGLETONS` unchanged (seven files). |
| 3 | A room is one Laravel module registering file types, tables, dashboard section and timeline entries; one test boots it empty and with its example folder, passing on SQLite and Postgres. | VERIFIED on SQLite; Postgres routed to human | `Room` descriptor, `RoomRegistry` singleton (`AppServiceProvider:52`), `RoomServiceProvider::register()` adds to the registry, `AdminPanelProvider:61` calls `addWidgetsTo`. `RecordRoomEntry` + `Activity::visibleOnTimeline()` (used by `Contact::timeline()` and `CustomerCard::render()`), migration adds nullable `room`/`entry` columns (additive, `activities.type` is a plain string so no Postgres enum issue). `PretendRoomBootTest` has an `empty` and `with its example folder` dataset, no driver guard, no skip (grep confirms). CI matrix is `[sqlite, pgsql]`. Postgres execution not observed here. |
| 4 | A room with an outside system declares a connector slot (interface with fake driver); connector files say `owns` and `reads` instead of `objects`. | VERIFIED | `PretendFeed` interface, `FakePretendFeed` singleton, driver chosen from config via closure. `ConnectorChecks` requires `slot`, `owns`, `reads` and refuses `objects` with "objects is replaced by owns and reads" (line 32); `OwnableKind` (8 kinds) and `PlatformKind` (projects, texts, timeline) enums, SPEC lists tied to enums. `ConnectorFileTest` passes. |
| 5 | A folder where two connectors own the same kind is refused in plain words and the previous version stays live. | VERIFIED | `ConnectorChecks:122` emits `{kind} is owned by both {first} and {later}; pick one, the other can read it`. `ConnectorFileTest:196` asserts the exact line `connectors/b.md: customers is owned by both connectors/a.md and connectors/b.md; pick one, the other can read it` and `:189` test title asserts the previous version stays live. Rooms double-claim uses the same sentence shape (`RoomChecks:331`). |
| 6 | Each room's dashboard section, including the D-21 empty line, passes axe (WCAG 2.2 AA) run with the room's boot test in CI; the pretend room proves it. | VERIFIED on SQLite; CI run routed to human | `RoomSectionAxe` uses tags `wcag2a, wcag2aa, wcag21a, wcag21aa, wcag22aa` with `target-size` enabled, scoped to `[data-room-section]`, `assertClean` fails when `checked` is 0. `RoomAccessibilityTest` runs the empty line, the furnished section, and an injected alt-less image that must report `image-alt`. All 3 passed in a real headless browser here. `section.blade.php` carries `data-room-section`. `tests.yml` sets up Node, `npm ci`, `npx playwright install --with-deps chromium` and `sockets` on both legs. `playwright` is `"1.63.0"` exactly; `pestphp/pest-plugin-browser ^5.1` is in require-dev. |

**Score:** 6/6 roadmap criteria verified (two with the Postgres/CI leg routed to a human check). 0 present-but-behavior-unverified.

### Plan must-haves

Plan-level truths for 04-01 through 04-09 are covered by the roadmap criteria above and by the passing tests that carry them (220 in the room tests; full suite 1956/1956 on SQLite, 15937 assertions, run once). Spot confirmations beyond the criteria:

- D-20 pretend room never ships: `bootstrap/providers.php` lists only `AppServiceProvider` and `AdminPanelProvider`; no pretend files under `app/Rooms/` or `examples/example-hvac/`.
- D-08: `RoomSection::canView()` asks `ActiveConfig::hasRoom()` at draw time; no boot-time decision.
- D-23: connectors cannot own `texts`, `projects` or `timeline` (`PlatformKind`).
- Ian decision 3: timeline rows of a removed room hidden at read time, never deleted; `FolderSync::queueCardsForRoomChanges()` redraws stored cards when the room set changes.
- No `TBD`/`FIXME`/`XXX`/`TODO`/`HACK` markers in any phase file.

### Required Artifacts

| Artifact | Status | Details |
|----------|--------|---------|
| `app/Rooms/{Room,RoomRegistry,RoomServiceProvider,RoomSection,RoomFileType,TimelineEntryType,RecordRoomEntry}.php` | VERIFIED | Substantive, wired (registry singleton, panel hook, scope users). |
| `app/Icm/RoomChecks.php`, `ConnectorChecks.php` | VERIFIED | Injected into `Validator`, run inside the locked sync. |
| `app/Enums/OwnableKind.php`, `PlatformKind.php` | VERIFIED | Compared with SPEC by `SpecTablesTest`. |
| `resources/views/filament/rooms/section.blade.php` | VERIFIED | `data-room-section` present; shared by all rooms. |
| `tests/Rooms/PretendRoom/*`, `tests/Fixtures/pretend-room/*`, `tests/Rooms/RoomSectionAxe.php`, `tests/Rooms/SpecTables.php` | VERIFIED | Test-only; all present. |
| `database/migrations/2026_10_22_000001_add_room_columns_to_activities_table.php` | VERIFIED | Additive and reversible. |
| `docs/SPEC.md` (ownership table, kinds tables, room row) | VERIFIED | Lines 208, 234, 251. |
| `composer.json`, `package.json`, `.github/workflows/tests.yml`, `.gitignore` | VERIFIED | Match 04-07 and 04-08 must-haves. |

### Key Link Verification

| From | To | Status |
|------|----|--------|
| Loader read of `rooms/<room>/CONTEXT.md` | typed room file | WIRED (`ROOM_CONTRACT`) |
| `FolderSync::compile()` | `index.room` and per-type lists | WIRED (lines 260, 271) |
| `RoomSection::canView()` | `ActiveConfig::hasRoom()` | WIRED |
| `AdminPanelProvider::panel()` | `RoomRegistry::addWidgetsTo()` | WIRED (line 61) |
| `AppServiceProvider::register()` | `RoomRegistry` singleton | WIRED (line 52) |
| `Validator` | `RoomChecks`, `ConnectorChecks` | WIRED (constructor, lines 124, 155, 156) |
| `Contact::timeline()`, `CustomerCard::render()` | `Activity::visibleOnTimeline()` | WIRED |
| `FolderSync::sync()` | `queueCardsForRoomChanges()` | WIRED (line 103) |
| Axe helper | `data-room-section` in shared view | WIRED |

### Data-Flow Trace (Level 4)

| Artifact | Data | Source | Status |
|----------|------|--------|--------|
| `RoomSection` empty line | contract `owns` + owned files | `ActiveConfig::roomContract()` / `roomOwnedFiles()` read the live `IcmVersion.compiled` | FLOWING |
| Customer card room entry | label and intro | `RoomRegistry::timelineEntry()` + `activities` row | FLOWING |

### Behavioral Spot-Checks

| Behavior | Command | Result | Status |
|----------|---------|--------|--------|
| Room standard tests | `php artisan test --compact tests/Feature/Rooms tests/Unit/Rooms tests/Unit/ArchitectureTest.php` | 220 passed, 1016 assertions | PASS |
| Browser a11y + boot test | `php artisan test tests/Feature/Rooms/RoomAccessibilityTest.php tests/Feature/Rooms/PretendRoomBootTest.php` | 16 passed | PASS |
| Whole suite (once) | `php artisan test --compact` | 1956 passed, 15937 assertions, no failures | PASS |

### Probe Execution

Step 7c: SKIPPED. The phase declares no `probe-*.sh` scripts.

### Requirements Coverage

All seven IDs are claimed by a plan and marked complete in REQUIREMENTS.md; none orphaned (REQUIREMENTS.md maps no other ID to Phase 4).

| Requirement | Source Plan(s) | Status | Evidence |
|-------------|----------------|--------|----------|
| ROOM-01 | 04-01, 04-02, 04-03, 04-06 | SATISFIED | Contract folder, empty room syncs, one owner per file, SPEC table. |
| ROOM-02 | 04-01, 04-03, 04-05, 04-06 | SATISFIED | File types, tables, section, timeline entries registered through `Room`. |
| ROOM-03 | 04-02, 04-04 | SATISFIED | Slot interface + fake driver; `connector` field in contract. |
| ROOM-04 | 04-02 | SATISFIED | `writes_records` checked (`records/...` paths, `none` or `[]`). |
| ROOM-05 | 04-01, 04-05, 04-06 | SATISFIED on SQLite | Boot test empty and with example; Postgres leg is a CI check. |
| CONN-07 | 04-04 | SATISFIED | `owns`/`reads`, `objects` refused, two-owner refusal. |
| A11Y-01 | 04-07, 04-08, 04-09 | SATISFIED on SQLite | Axe on the pretend section, empty and furnished, with teeth; CI wiring present. |

### Anti-Patterns Found

None blocking. No stubs, no debt markers, no placeholder returns in phase code.

Advisory items from `04-REVIEW.md` (dispositions still `open`, none critical). I confirmed WR-02 and WR-04 in the code:

| File | Finding | Severity | Impact |
|------|---------|----------|--------|
| `app/Icm/ActiveConfig.php:132-135` | WR-02: `roomOwnedFiles()` skips only `type === 'room'`, so a router `CONTEXT.md` inside a room's subfolder that matches an `owns` pattern counts as an owned file and hides the D-21 empty line. | Warning | An edge of the empty line. The pretend room's own path works. No real room exists until Phase 5. |
| `app/Rooms/TimelineEntryType.php`, `app/Records/CustomerCard.php:299-306` | WR-04: the room picks the trust intro line (the example is "System note:") and nothing defaults it to the untrusted wording. | Warning | A future room could present outside text to the owner's AI as a trusted system line. Not exercised by Phase 4 code; worth fixing before Phase 5 adds real rooms. |
| `app/Rooms/RoomRegistry.php` | WR-01 (a room's morph map can replace a core alias), WR-03 (sections, timeline entry keys and `handles` not validated). | Warning | Guard gaps a later room could hit; no current room does. |
| Various | IN-01 to IN-06 (single failed dispatch skips remaining contacts, repeated path pattern, static morph map leaking between tests, `ReceivePing` ordering, connector accepted at any path, unsanitised folder name in one message). | Info | Hardening. |

These do not make a must-have false, so they are not gaps. They should be triaged (fixed, deferred or skipped) before Phase 5 starts, and WR-04 and WR-03 are the two that bite hardest once real rooms exist.

### Human Verification Required

1. **Postgres and CI leg**
   - **Test:** Push the phase branch and read the tests workflow for both `sqlite` and `pgsql` legs.
   - **Expected:** Both green, including `PretendRoomBootTest` and `RoomAccessibilityTest` (Chromium installed by the workflow).
   - **Why human:** Criteria 3 and 6 name Postgres. No Postgres could be run here, and plan 04-09 declares this as a backstop truth.

2. **Interrupted or concurrent sync**
   - **Test:** Run two `icm:sync` at once, and kill one mid-run, with a room contract in the folder.
   - **Expected:** No half-checked folder goes live.
   - **Why human:** Backstop truth from 04-02. The lock and the reject path exist; no test races two syncs.

3. **Order independence**
   - **Test:** Reorder `owns`/`reads`/`writes_records` entries and connector files.
   - **Expected:** Same verdict; the later file in path order is the one blamed.
   - **Why human:** Backstop truths from 04-02 and 04-04.

### Gaps Summary

No gaps. Every roadmap success criterion has code behind it and passing tests: the full suite on SQLite is 1956/1956, the room tests are 220/220, and the axe check runs in a real headless browser. The status is `human_needed` rather than `passed` only because the Postgres leg of criteria 3 and 6 cannot be observed from this machine, and three plan-declared backstop truths have no explicit test evidence. Four open review warnings (WR-01 to WR-04) are untriaged and should be dealt with before Phase 5 builds real rooms on this standard.

---

_Verified: 2026-10-23_
_Verifier: Claude (gsd-verifier)_
