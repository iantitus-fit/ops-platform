---
phase: 09-owner-intelligence
plan: 03
subsystem: rooms
tags: [owner-intelligence, room-13, book-06, tdd, filament, dashboard-section, contact-screen, axe]

requires:
  - phase: 09-owner-intelligence
    provides: TimeToBooked::forContact(), summaryLine(), EMPTY_LINE and DurationText::format() (09-02)
  - phase: 04-room-standard
    provides: Room, RoomRegistry, RoomServiceProvider, RoomSection, RoomSectionAxe
provides:
  - The owner intelligence room (key owner_intelligence, label owner intelligence) at the room standard, optional, no tables, no slot, no claimed widget, off the never-left-out list
  - OwnerIntelligenceSection, the dashboard section holding the one time-to-booked line, drawn between money and schedule_and_booking
  - examples/example-hvac/rooms/owner_intelligence/CONTEXT.md with the contract (writes_records today.md and time-to-booked.md) and a sample of each file
  - "Time to booked" entry on the contact screen, left out with no number, with the room off, or when it throws (reported)
affects: [09-04 records/today.md and records/time-to-booked.md, owner-intelligence, knowledge, safety-and-compliance]

actuals:
  tokens: 14000
  tasks: 3
  commits: 5

plan_head_before: 5d5b8365c7522a196c16a22dbb7178dbb352a4dc
plan_head_after: da6ffa869deb9882c279f23d27e10ab1a7509cf6
status: complete

tech-stack:
  added: []
  patterns:
    - "A section with no table is a RoomSection subclass with roomKey() and contentLines() only; the base class draws heading, empty line and the failure line"
    - "A collaborator the view needs is resolved with app() so a test can swap it with $this->mock()"
    - "A contact-screen entry that can fail wraps its work in try/catch, calls report() and returns null so the entry is left out"

key-files:
  created:
    - app/Rooms/OwnerIntelligence/OwnerIntelligenceServiceProvider.php
    - app/Rooms/OwnerIntelligence/OwnerIntelligenceSection.php
    - examples/example-hvac/rooms/owner_intelligence/CONTEXT.md
    - tests/Feature/Rooms/OwnerIntelligenceBootTest.php
    - tests/Feature/Filament/ContactTimeToBookedTest.php
  modified:
    - bootstrap/providers.php
    - tests/Pest.php
    - app/Filament/Resources/Contacts/Schemas/ContactInfolist.php
    - docs/BLUEPRINT.md
    - docs/sprints/sprint-1.1.md
    - .planning/WINDOWS.md

key-decisions:
  - "exampleFolderFileCount() moves 28 to 29 for the new CONTEXT.md only; the room owns no file and commits no example records (D-22)"
  - "The section sets no $sort, so it draws in room-key order between money and schedule_and_booking (UI-SPEC)"
  - "The customer card is untouched (git diff against b91758a is empty), so what the front desk's model reads does not change (D-05)"

patterns-established:
  - "Room edges (left out, empty, failing, order, axe) live in the room's boot test, in the Leads and Schedule shape"

requirements-completed: [ROOM-13, BOOK-06]

coverage:
  - id: D1
    description: "The room boots from examples/example-hvac at the room standard: contract matches SPEC's ownership table, folder holds only CONTEXT.md, provider listed after ScheduleAndBookingServiceProvider with AppServiceProvider first"
    requirement: ROOM-13
    verification:
      - kind: unit
        ref: "tests/Feature/Rooms/OwnerIntelligenceBootTest.php#boots the owner intelligence room"
        status: pass
    human_judgment: false
  - id: D2
    description: "The dashboard section shows the median time to booked under 'Owner intelligence'"
    requirement: BOOK-06
    verification:
      - kind: unit
        ref: "tests/Feature/Rooms/OwnerIntelligenceBootTest.php#shows the median time to booked on the dashboard"
        status: pass
    human_judgment: false
  - id: D3
    description: "The room is optional (one file fewer, section hidden); the empty, failing and ordering states of the section hold"
    requirement: ROOM-13
    verification:
      - kind: unit
        ref: "tests/Feature/Rooms/OwnerIntelligenceBootTest.php#lets a business leave the owner intelligence room out (D-08, D-22)"
        status: pass
      - kind: unit
        ref: "tests/Feature/Rooms/OwnerIntelligenceBootTest.php#says so when no new customer booked (D-07)"
        status: pass
      - kind: unit
        ref: "tests/Feature/Rooms/OwnerIntelligenceBootTest.php#shows one line when the section cannot load (05 D-32)"
        status: pass
      - kind: unit
        ref: "tests/Feature/Rooms/OwnerIntelligenceBootTest.php#sits between the money and schedule sections (UI-SPEC)"
        status: pass
    human_judgment: false
  - id: D4
    description: "axe finds no WCAG 2.2 AA violation in the section with and without a qualifying contact"
    requirement: ROOM-13
    verification:
      - kind: unit
        ref: "tests/Feature/Rooms/OwnerIntelligenceBootTest.php#finds no accessibility violation in the section (A11Y-01, D-24)"
        status: pass
    human_judgment: false
  - id: D5
    description: "The contact screen shows Time to booked after Next appointment and before Automatic texts, nothing with no number, with the room off, or when it throws; the card is unchanged"
    requirement: BOOK-06
    verification:
      - kind: unit
        ref: "tests/Feature/Filament/ContactTimeToBookedTest.php (6 cases)"
        status: pass
    human_judgment: false
  - id: D6
    description: "Keyboard-only and VoiceOver walk of the contact line and the section, and phone-width check for sideways scroll"
    requirement: ROOM-13
    verification: []
    human_judgment: true
    rationale: "A screen-reader and phone walk cannot be asserted by a test; recorded in .planning/WINDOWS.md for end-of-phase UAT (D-29)"
---

# Phase 9 Plan 03: Owner intelligence room and time to booked Summary

**The owner intelligence room now boots at the room standard, shows the 30-day median time to booked in its own dashboard section, and shows each contact's own "Time to booked" on the contact screen, with the customer card unchanged.**

## Performance

- **Duration:** about 12 min
- **Started:** 2026-11-16T23:21:21Z
- **Completed:** 2026-11-16T23:33Z
- **Tasks:** 3
- **Files:** 5 created, 6 modified

## Accomplishments

- `OwnerIntelligenceServiceProvider` registers key `owner_intelligence`, label `owner intelligence`, one section, nothing else; it is listed after `ScheduleAndBookingServiceProvider`.
- `OwnerIntelligenceSection` returns `[app(TimeToBooked::class)->summaryLine()]`, so the section reads "Your 1 new customer in the last 30 days booked within 42 minutes." or the empty line, and shows the shared failure line if the line throws.
- The example `CONTEXT.md` states the contract the sync checks (`owns: none`, `reads: [business/profile.md]`, `writes_records: [records/today.md, records/time-to-booked.md]`, `connector: none`) and shows a sample of each records file. The example folder file count is now 29.
- `ContactInfolist` gains a `Time to booked` entry between Next appointment and Automatic texts, fed by `timeToBookedLine()`: null with the room off or no number, reported and null on a throw.
- BLUEPRINT section 7 has the Built bullet; sprint-1.1 walk-through log has the container and `mock()` line.

## TDD record

- **Task 1 RED** (`fbc8bdf`): both cases failed on the intended assertion (`hasRoom('owner_intelligence')` false; section missing from /admin). Evidence rerun with `--log-junit` and `gsd_run check tdd-red-evidence` returned `RED_EVIDENCE_OK` (target_test_failed). Semantic assessment: the target ran and failed on the planned room-absent assertion, not on a load or fixture fault. **GREEN** (`383a647`): the Rooms, Unit/Rooms and Icm suites passed (769 tests).
- **Task 2 RED** (`d26a31a`): after fixing a return-type import slip in my own test helper (an error, not RED, caught before committing), four of six cases failed on the missing line; `RED_EVIDENCE_OK`. The two that passed on the first run, "no line with no number" and "customer card unchanged", guard prohibitions that the code must keep and are not unexpected greens of the new behavior. **GREEN** (`8d3f545`): 28 tests across the contact, by-hand and card-injection files passed.
- **Task 3** (`da6ffa8`): the five edge cases all passed on first run, as the plan expected, because Tasks 1 and 2 built the behavior. None failed, so no fix commit was needed. The axe cases ran in Chromium and passed.

## Task Commits

1. Task 1 RED: `fbc8bdf` test(09-03): the owner intelligence room boots and shows time to booked
2. Task 1 GREEN: `383a647` feat(09-03): the owner intelligence room and its time-to-booked section
3. Task 2 RED: `d26a31a` test(09-03): Time to booked on the contact screen
4. Task 2 GREEN: `8d3f545` feat(09-03): Time to booked on the contact screen
5. Task 3: `da6ffa8` test(09-03): the room's edges and axe check (with BLUEPRINT, WINDOWS and sprint log)

## Deviations from Plan

None - plan executed exactly as written. The sprint-1.1 walk-through line and the two WINDOWS entries were required by the plan and project rules.

## Hand checks not run

- The keyboard-only and VoiceOver walk and the phone-width check of both lines are unrun by design (D-29) and recorded as an `unrun-verify` entry in `.planning/WINDOWS.md` for end-of-phase UAT.
- The CI pgsql leg for `OwnerIntelligenceBootTest` and `ContactTimeToBookedTest` is also recorded in WINDOWS.md; it can only run after Ian pushes.

## Known Stubs

None.

## Threat Flags

None. No new route or endpoint; both surfaces sit inside the existing panel login and the section is room-gated by `canView()`. T-09-08 is mitigated and tested with a throwing `TimeToBooked` on both surfaces; T-09-10 is covered by the boot test pinning owns, reads, writes_records and connector.

## Verification

- `php artisan test --compact` full suite: 3291 passed, 22930 assertions (SQLite).
- All task acceptance criteria re-run and passing; `git diff --quiet b91758a -- app/Records/CustomerCard.php` succeeds.

## Next Phase Readiness

Ready for 09-04, which writes `records/today.md` and `records/time-to-booked.md` for the room that now exists.

## Self-Check: PASSED

All created files exist on disk and all five task commits are ancestors of HEAD.
