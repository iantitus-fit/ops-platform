---
phase: 05-standard-rooms
plan: 08
subsystem: rooms
tags: [rooms, google-business-profile, connector-slot, fake-driver, connector-file, single-file-type, icm-sync, filament, axe, pest, tdd]

requires:
  - phase: 04-room-standard
    provides: RoomRegistry slot guard, ConnectorChecks slot refusals, Loader NONE_MEANS_EMPTY, RoomSectionAxe, pretend-room slot pattern
  - phase: 05-standard-rooms
    provides: 05-06 singleFileLabel() and the D-27 single-file check; 05-07 real-room shape and the second-file test shape
provides:
  - the Google Business Profile room (key google_business_profile, label 'Google Business Profile'), registered after money, optional, with the room's own gbp_categories file type
  - the room's read-only slot google_business_profile, interface GoogleListingSource (one method, fetchListing()), bound the TextChannel way to FakeGoogleListingSource, any other driver refused
  - the example folder's first connector file, connectors/google-business-profile.md, owns and reads the bare word none
  - a categories file (primary category and additional categories) and a contract that reads profile, hours, services and offers
  - a dashboard section showing "Primary category: <name>" and "Not connected to Google: this room runs on a stand-in and sends nothing."
  - D-23 done: the empty-slots sentence now has a unit case and its feature case is gone
  - SPEC rows: ownership for the categories file, the gbp_categories type row, Google Business Profile as a reader of hours, services and offers
  - exampleFolderFileCount() returning 23
affects: [05-09, marketing, connectors-and-data, knowledge, safety-and-compliance, CONN-01]

plan_head_before: 6a6d2d32234382a3ca58b5e4f6f20954aa0a5b2a
plan_head_after: bf59a26f1b27a1fa0898ad82303bc820b864797c

actuals:
  tokens: 10000
  tasks: 3
  commits: 6

tech-stack:
  added: []
  patterns:
    - "A room's slot is an interface in the room's own namespace, bound in the room provider's register() to a closure that reads config with a default of fake and refuses any other driver"
    - "The bare word none in a connector file's owns and reads compiles to an empty list; [none] is refused as an unknown kind"
    - "A room section's own lines show only while the room is not empty, so a test that checks them needs the owned file in place"

key-files:
  created:
    - app/Rooms/GoogleBusinessProfile/GoogleListingSource.php
    - app/Rooms/GoogleBusinessProfile/FakeGoogleListingSource.php
    - app/Rooms/GoogleBusinessProfile/GoogleBusinessProfileServiceProvider.php
    - app/Rooms/GoogleBusinessProfile/GoogleBusinessProfileSection.php
    - app/Rooms/GoogleBusinessProfile/GbpCategoriesType.php
    - examples/example-hvac/rooms/google_business_profile/CONTEXT.md
    - examples/example-hvac/rooms/google_business_profile/categories.md
    - examples/example-hvac/connectors/google-business-profile.md
    - tests/Feature/Rooms/GoogleBusinessProfileBootTest.php
    - tests/Unit/Icm/ConnectorChecksTest.php
  modified:
    - app/Rooms/Room.php
    - bootstrap/providers.php
    - tests/Pest.php
    - tests/Feature/Rooms/ConnectorFileTest.php
    - docs/SPEC.md
    - docs/sprints/sprint-1.1.md
    - .planning/WINDOWS.md

key-decisions:
  - "The room's slot has no config file and no .env key: only the fake exists, the closure defaults to fake, and ArchitectureTest keeps env() out of app/"
  - "The slot interface has one read method and no send method, so this phase adds no agent action and no chokepoint (D-11); every later send to Google goes through one that records the owner's approval"
  - "The section decides 'connected' from the bound driver first and a connector file second, so it says 'Not connected to Google' while the fake is bound even though a connector file now exists"
  - "GbpCategoriesType registers only the required primary_category rule; the additional-categories shape checks stay in plan 05-09"

patterns-established:
  - "Second-file D-27 cases use a name that sorts after the kept file; for categories.md the plan's more.md already does, so the shipped-contract and edited-contract cases both use it"

requirements-completed: [ROOM-11]

coverage:
  - id: D1
    description: "The Google Business Profile room is registered with its read-only slot, boots empty from icm:sync to /admin with exactly 'Google Business Profile: nothing set up yet. Add rooms/google_business_profile/categories.md to start.', has a contract equal to SPEC's row, and passes axe at WCAG 2.2 Level AA, on SQLite with no driver guard"
    requirement: ROOM-11
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/GoogleBusinessProfileBootTest.php#boots the Google Business Profile room empty"
        status: pass
      - kind: automated_ui
        ref: "tests/Feature/Rooms/GoogleBusinessProfileBootTest.php (RoomSectionAxe::assertClean in both boot rows)"
        status: pass
    human_judgment: false
  - id: D2
    description: "The slot resolves to a fake that returns a fictional listing and sends nothing (Http::assertNothingSent), any other driver name is refused, the interface declares only fetchListing, and the dashboard never fetches (fetch count 0 after /admin)"
    requirement: ROOM-11
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/GoogleBusinessProfileBootTest.php (binds the read-only slot; refuses a driver other than the fake; never fetches the listing; declares exactly one method)"
        status: pass
    human_judgment: false
  - id: D3
    description: "The example connector file fills the slot with owns and reads none (compiled as empty lists), owns written [none] is refused as an unknown kind, and removing the room folder while the connector file stays is refused with the accepted sentence, each time keeping the previous version live"
    requirement: ROOM-11
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/GoogleBusinessProfileBootTest.php (fills its slot; refuses [none]; refuses when the room folder is gone)"
        status: pass
    human_judgment: false
  - id: D4
    description: "D-23: the empty-slots sentence has a unit case passing before the feature case was removed, and no other test was removed"
    verification:
      - kind: unit
        ref: "tests/Unit/Icm/ConnectorChecksTest.php#says the engine has no connector slots yet when no room offers one"
        status: pass
    human_judgment: false
  - id: D5
    description: "The furnished boot shows the categories file's primary category and the not-connected line; a categories file without primary_category is refused; a second categories file is refused in one line naming both files under the shipped contract and under a contract edited to own two paths, keeping the previous version live; two owned paths with one file present activates"
    requirement: ROOM-11
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/GoogleBusinessProfileBootTest.php (with its example folder; without a primary category; 3 D-27 cases)"
        status: pass
    human_judgment: false
  - id: D6
    description: "SPEC gains the ownership row, the gbp_categories row and Google Business Profile as a reader of hours, services and offers, and agrees with the contract"
    requirement: ROOM-11
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/SpecTablesTest.php and the boot test's SpecTables::patternsOwnedBy comparison"
        status: pass
    human_judgment: false
  - id: D7
    description: "The Google Business Profile section's wording and a keyboard-only and screen reader walk read plainly and work"
    requirement: ROOM-11
    verification: []
    human_judgment: true
    rationale: "Automated axe covers part of WCAG; the keyboard and VoiceOver walk is a hand check recorded for Phase 5 UAT (WINDOWS entry 24), and the wording is a placeholder until the wording pass"

duration: 11min
completed: 2026-10-26
status: complete
---

# Phase 5 Plan 08: Google Business Profile room Summary

**The Google Business Profile room lands as the first room with a slot that ships: a read-only GoogleListingSource interface bound to a fake that reaches nothing outside the app, the example folder's first connector file owning and reading none, one categories file kept to one per business, and the empty-slots sentence moved to a unit case.**

## Performance

- **Duration:** 11 min
- **Started:** 2026-10-26T00:13:10Z
- **Completed:** 2026-10-26T00:24:23Z
- **Tasks:** 3 (Task 1 a tracer with tdd, Tasks 2 and 3 tdd)
- **Files:** 10 created, 7 changed (plus this SUMMARY and the planning files)

## Accomplishments

- `GoogleBusinessProfileServiceProvider` returns `Room(key: 'google_business_profile', label: 'Google Business Profile', fileTypes: [GbpCategoriesType], slot: 'google_business_profile', slotInterface: GoogleListingSource, sections: [GoogleBusinessProfileSection])`, listed after money in `bootstrap/providers.php`. No tables, timeline entries or morph map. It stays optional.
- The slot: `GoogleListingSource::fetchListing(): array` is the interface's only method. `register()` makes `FakeGoogleListingSource` a singleton and binds the interface to a closure reading `config('rooms.google_business_profile.driver', 'fake')`; any other name throws `Unknown Google Business Profile driver [x]. Use fake.` No config file, no `.env` key. The fake returns a fictional listing and counts fetches; `Http::assertNothingSent()` holds after a fetch, and the dashboard leaves the count at 0.
- The example folder's first connector file, `connectors/google-business-profile.md`: `slot: google_business_profile`, `owns: none`, `reads: none`, no credential field. It compiles to empty lists; `[none]` is refused as `owns has none, which is not a kind of record; ...`; removing the room folder while it stays is refused with the accepted 04 D-25 sentence.
- The contract (`rooms/google_business_profile/CONTEXT.md`) owns the categories file, reads profile, hours, services and offers, writes nothing and names the slot. Its body names Google's three local ranking factors and says what each read feeds.
- `GbpCategoriesType`: type `gbp_categories`, `primary_category` required, `singleFileLabel()` returns `'Google Business Profile categories file'`, so 05-06's check refuses a second file wherever the contract's owns points. The additional-categories shape checks stay with plan 05-09.
- The section shows `Primary category: HVAC contractor` and `Not connected to Google: this room runs on a stand-in and sends nothing.`; without the categories file the platform draws the empty line naming the file. Axe passes in both boot rows.
- D-23: the unit case in `tests/Unit/Icm/ConnectorChecksTest.php` passed first, then the one feature case in `ConnectorFileTest.php` was removed. No other test was removed.
- SPEC: ownership row for the categories file (owner Google Business Profile), the `gbp_categories` row (saying the sync refuses a second file), and Google Business Profile as a reader of hours, services and offers. `exampleFolderFileCount()` is 23. The `Room` label docblock now allows a proper noun, and one walk-through line was added to `docs/sprints/sprint-1.1.md` marked `(plan 05-08)`.

## Task Commits

1. **Task 1 RED: Google Business Profile boots on a read-only fake slot** - `cafe741` (test)
2. **Task 1 GREEN: Google Business Profile joins the example business on a fake slot** - `44c171a` (feat)
3. **Task 2 RED: the connector file fills the slot with owns and reads none** - `924b0f8` (test)
4. **Task 2 GREEN: the Google Business Profile connector file owns and reads none (D-10, D-23)** - `4038840` (feat)
5. **Task 3 RED: the categories file furnishes the room, one per business** - `1063bf9` (test)
6. **Task 3 GREEN: Google Business Profile categories file; SPEC rows** - `bf59a26` (feat)

**Plan metadata:** committed with this SUMMARY (docs).

## TDD Gate Compliance

- **Task 1 RED:** `cafe741`. All 5 target tests failed on the planned behavior: the empty boot on `hasRoom('google_business_profile')` (`Failed asserting that false is true.`), the slot cases on `Target class [App\Rooms\GoogleBusinessProfile\GoogleListingSource] does not exist`, the dashboard case on the missing section, and the one-method case on the missing interface. Semantic assessment: all fail because the room and its classes do not exist yet, with no syntax, fixture or load fault. `gsd_run check tdd-red-evidence` was not run because Pest's compact JSON is not one of its supported formats (same as 05-02, 05-03, 05-06 and 05-07); RED judged by hand from the runner output.
- **Task 1 GREEN:** `44c171a`; the boot test and SpecTablesTest (33 tests) pass. The full suite then had exactly one failure, the ConnectorFileTest empty-slots case, as the plan warned (D-23); the commit body says so. Tracer gate: `<verify>` was automated only, so under end-of-phase mode it was re-run end to end and expansion continued.
- **Task 2 RED:** `924b0f8`. The D-23 unit case passed first, by design. The three connector boot cases failed on the planned behavior (the connector index lacked the file, `File does not exist at path .../connectors/google-business-profile.md`, and the folder-gone case exited 0 instead of 1).
- **Task 2 GREEN:** `4038840`; the three verify files (49 tests) and then the full suite (2124) pass.
- **Task 3 RED:** `1063bf9`. 5 of 13 target tests failed on the planned behavior: the furnished row (`actual size 0 matches expected size 1`, no `gbp_categories` file yet), the missing-primary case (the type was unknown, so the refusal was about the type), both D-27 refusal cases, and the two-paths-accepted case. The other eight passed in RED by design.
- **Task 3 GREEN:** `bf59a26`; the three verify files (60 tests) and then the full suite (2129, 16706 assertions) pass.
- **REFACTOR:** none needed.

## Decisions Made

- See `key-decisions`. In short: no config file or env key for a fake-only driver; no send method on the interface; the section keys "connected" on the bound driver first; only the primary-category rule here.

## Deviations from Plan

**1. [Rule 1 - Plan defect] Task 2's "section still shows the not-connected line" case cannot hold yet**
- **Found during:** Task 2 GREEN
- **Issue:** The plan has Task 2's boot case on the example folder as shipped assert that the section shows the not-connected line. At Task 2 the example folder has no categories file (Task 3 adds it), so the room is still empty, and `RoomSection::getViewData()` draws the platform's empty line and no content lines while a room is empty (05 D-21). The line cannot appear until Task 3.
- **Fix:** Task 2's case asserts the cause instead (the bound driver is the fake) and its docblock says where the on-page check lives. Task 3's furnished row asserts `Not connected to Google: this room runs on a stand-in and sends nothing.` on the page, so the behavior is still covered. The shared `RoomSection` was not changed.
- **Files modified:** tests/Feature/Rooms/GoogleBusinessProfileBootTest.php
- **Commit:** `4038840`

**Total deviations:** 1 auto-fixed (plan defect in a test's expectation order). **Impact:** none on behavior or scope.

## Issues Encountered

None. Final full suite: 2129 passed, 16706 assertions.

## Verification

- Task 1 verify (33 tests), Task 2 verify (49 tests, then the full suite, 2124) and Task 3 verify (60 tests, then the full suite, 2129) pass.
- Acceptance greps pass: one `public function` in the interface and `function fetchListing(): array`, `rooms.google_business_profile.driver', 'fake'` with zero `env(`, `label: 'Google Business Profile'`, `relevance, distance and prominence` and `connector: google_business_profile` in the contract, `return 21;` then `22;` then `23;`, `owns: none` and `reads: none` as whole lines, zero credential words in the connector file, zero `says the engine has no connector slots yet` in ConnectorFileTest and `no connector slots yet` in the unit test, `return 'gbp_categories';`, `return 'Google Business Profile categories file';`, the exact `more.md` line in the boot test, `refuses a second Google Business Profile categories file` and `| gbp_categories |` in SPEC, 8 mentions of Google Business Profile in SPEC, zero `description` or `service_area` in the categories file.
- Postgres: no local Postgres. The boot test has no driver guard, so CI's pgsql leg runs it when Ian pushes the branch.
- Nothing in this plan called Google or any outside network; the slot ran on the fake only.

## Hand checks for UAT

- **Keyboard-only and VoiceOver walk of the Google Business Profile section on /admin** (D-18), empty and with the categories file in place. Not run by the executor. Recorded as an `unrun-verify` entry (id 24) in `.planning/WINDOWS.md`. Nothing was paused for it.
- Wording of the section lines is a placeholder until the wording pass.

## Known Stubs

None. `FakeGoogleListingSource` is an intentional stand-in, named as one in its docblock, the section line and the connector file; the real connector is CONN-01.

## Threat Flags

None. T-05-16 (the only bound driver is the fake, one read method, no send method, nothing sent, the dashboard never fetches), T-05-17 (owns and reads are `none`, `[none]` refused), T-05-18 (no credential field in the connector file) and T-05-22 (a second categories file refused wherever owns points) are mitigated and covered by tests or acceptance greps.

## Walk-through log

One line added to `docs/sprints/sprint-1.1.md` marked `(plan 05-08)`: binding a slot interface to a closure that reads config each time and returns the shared fake, the TextChannel and pretend-feed shape in a room that ships.

## Next Phase Readiness

Ready for 05-09, which adds the shape checks on the additional-categories list (at most 9, no repeats, none equal to the primary) to `GbpCategoriesType::checks()`. A room section's own lines show only while the room is not empty, so a test that checks them needs the owned file in place (this plan's finding).

## Self-Check: PASSED

- Created files exist: interface, fake, provider, section, type, contract, categories file, connector file, boot test, unit test.
- Commits `cafe741`, `44c171a`, `924b0f8`, `4038840`, `1063bf9`, `bf59a26` are ancestors of HEAD.
- Full suite passes (2129).

---
*Phase: 05-standard-rooms*
*Completed: 2026-10-26*
