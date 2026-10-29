---
phase: 06-front-desk-and-team-rooms
plan: 02
subsystem: rooms
tags: [room-registry, front-desk, filament-widgets, icm, pest]

requires:
  - phase: 06-front-desk-and-team-rooms
    provides: 06-01 moved the three triage-only ownership tests to hand-built registries, so the front desk can handle every hard-coded type without hollowing out an existing test
  - phase: 05-standard-rooms
    provides: the room standard (RoomServiceProvider, Room, RoomRegistry, never-left-out) and the Marketing and Google Business Profile analogs
provides:
  - App\Rooms\FrontDesk\FrontDeskServiceProvider, room key front_desk, handling sending_rules, set_replies, triage and agent, with the text slot named as a string
  - examples/example-hvac/rooms/front_desk/CONTEXT.md, the room's contract in the example business (connector text)
  - Room::$claimedWidgets and RoomRegistry::guardClaimedWidgets(), a checked pointer at dashboard widgets the panel already finds
  - the front desk can never be left out (one refusal line, previous version stays live)
affects: [06-front-desk-and-team-rooms, rooms, team, folder-sync, dashboard]

plan_head_before: fd2cd0867c031ede410e9f3bc09220c3ee4fa4ef
plan_head_after: ad42ce7f6132f73b3198aa6a90def9af9f42c3f7

actuals:
  tokens: 7100
  tasks: 2
  commits: 4

tech-stack:
  added: []
  patterns:
    - "A slot interface named as a quoted string in a room provider so the architecture rule on TextChannel passes unchanged (06 D-19)"
    - "A descriptor field that points without registering: Room::$claimedWidgets is checked at add() and never read by the panel"

key-files:
  created:
    - app/Rooms/FrontDesk/FrontDeskServiceProvider.php
    - examples/example-hvac/rooms/front_desk/CONTEXT.md
    - tests/Feature/Rooms/FrontDeskBootTest.php
    - tests/Unit/Rooms/RoomClaimedWidgetsTest.php
  modified:
    - app/Rooms/Room.php
    - app/Rooms/RoomRegistry.php
    - bootstrap/providers.php
    - tests/Pest.php
    - docs/SPEC.md
    - docs/BLUEPRINT.md
    - docs/sprints/sprint-1.1.md

key-decisions:
  - "The text slot interface is the quoted string 'App\\Channels\\TextChannel' with no import, so ArchitectureTest ('only the outbound chokepoint and the alert sender send texts') passes unchanged; guardSlot() still checks the interface exists at boot (06 D-19)"
  - "guardClaimedWidgets() refuses a class that does not exist, unlike guardSections(), because a claim names a widget the panel already has (06 D-18)"
  - "The 'empty' boot row removes agents/front-desk.md, the one front desk file the sync does not require (research A5)"

patterns-established:
  - "A room that wraps existing code has no register() or boot() override and no section: it is one provider and one contract"

requirements-completed: [ROOM-06]

coverage:
  - id: D1
    description: "The front desk is a room: provider registered after GoogleBusinessProfile, contract in the example business, boots empty and with its example folder, contract matches SPEC's front desk rows plus agents/*.md"
    requirement: ROOM-06
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/FrontDeskBootTest.php#boots the front desk room"
        status: pass
    human_judgment: false
  - id: D2
    description: "A folder without rooms/front_desk/ is refused with one never-left-out line and the previous version stays live"
    requirement: ROOM-06
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/FrontDeskBootTest.php#refuses a folder with no front desk room in one line and keeps the previous version live"
        status: pass
    human_judgment: false
  - id: D3
    description: "The text slot names TextChannel as a string, the architecture rule passes unchanged, and the fake still fills the slot"
    requirement: ROOM-06
    verification:
      - kind: unit
        ref: "tests/Unit/ArchitectureTest.php#only the outbound chokepoint and the alert sender send texts"
        status: pass
      - kind: integration
        ref: "tests/Feature/Rooms/FrontDeskBootTest.php#boots the front desk room"
        status: pass
    human_judgment: false
  - id: D4
    description: "Room::$claimedWidgets and its guard: accepted claims stay off the panel list, a missing class, a non-widget and a doubly claimed widget are refused at boot"
    requirement: ROOM-06
    verification:
      - kind: unit
        ref: "tests/Unit/Rooms/RoomClaimedWidgetsTest.php"
        status: pass
    human_judgment: false
  - id: D5
    description: "The dashboard shows Drafts waiting, Customers waiting while you handle it, New leads and Today's conversations in that order, each claimed widget held once, NewLeads claimed by no room"
    requirement: ROOM-06
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/FrontDeskBootTest.php#boots the front desk room"
        status: pass
    human_judgment: false
  - id: D6
    description: "The wrap changes no reply, hold, alert or screen: the full suite passes with the example folder file count the only existing test change"
    requirement: ROOM-06
    verification:
      - kind: integration
        ref: "php artisan test --compact (2211 passed, SQLite)"
        status: pass
    human_judgment: false
  - id: D7
    description: "Success criterion 2 on Postgres: the CI pgsql leg is green with the front desk registered"
    requirement: ROOM-06
    verification: []
    human_judgment: true
    rationale: "Needs Ian to push the phase branch; carried to end-of-phase UAT and recorded as WINDOWS.md entry 28"

duration: 8min
completed: 2026-10-27
status: complete
---

# Phase 6 Plan 02: The Front Desk Joins the Room Standard Summary

**The front desk is a registered room (`front_desk`) with a contract in the example business, a text slot named as a string so the architecture rule is untouched, and a checked `claimedWidgets` pointer for Today's conversations and Paused conversations, with no behavior change.**

## Performance

- **Duration:** 8 min
- **Started:** 2026-10-27T22:20:09Z
- **Completed:** 2026-10-27T22:28:36Z
- **Tasks:** 2 (both TDD, 4 task commits)
- **Files modified:** 11

## Accomplishments

- `FrontDeskServiceProvider` (key `front_desk`, label `front desk`) handles `sending_rules`, `set_replies`, `triage` and `agent`, offers the `text` slot, lists the `messages` and `conversations` tables, and can never be left out. It is listed after `GoogleBusinessProfileServiceProvider` with `AppServiceProvider` still first.
- `examples/example-hvac/rooms/front_desk/CONTEXT.md` owns SPEC's three front desk rules files followed by `agents/*.md`; `exampleFolderFileCount()` moves 23 to 24, the only existing test change.
- The boot test proves the room empty (no `agents/front-desk.md`, file count one less) and with its example folder, the contract against SPEC's rows, the fake text channel binding, no `text` connector file, the four dashboard headings in order, and the one-line never-left-out refusal.
- `Room::$claimedWidgets` (trailing optional) and `RoomRegistry::guardClaimedWidgets()` refuse a missing class, a non-Filament-widget class and a widget another room claims. A claim never reaches `widgetClasses()` or the panel, so the dashboard is unchanged. The front desk claims `TodaysConversations` and `PausedConversationsWaiting`; `NewLeads` is claimed by no room.
- SPEC, BLUEPRINT (sections 1 and 9, glance row 1) and the sprint 1.1 walk-through log updated.

## Task Commits

1. **Task 1: tracer, the front desk is registered** - `ff223eb` (test, RED), `6b94108` (feat, GREEN)
2. **Task 2: claimed widgets as pointers** - `98333f5` (test, RED), `ad42ce7` (feat, GREEN)

**Plan metadata:** recorded in the docs commit that follows this file.

## TDD Gate Compliance

- Task 1 RED: `tests/Feature/Rooms/FrontDeskBootTest.php` failed on `hasRoom('front_desk')` (false) for both dataset rows and on the sync exiting 0 instead of 1 for the never-left-out case; the contract-body case errored because `rooms/front_desk/CONTEXT.md` did not exist. These are the planned assertions failing for the intended reason, no load or syntax fault. GREEN: all 4 cases pass; full suite 2206 passed.
- Task 2 RED: `RoomClaimedWidgetsTest` failed with `Unknown named parameter $claimedWidgets` and the no-claims default case on a null property, the intended missing-field failure. GREEN: 74 room and unit tests pass; full suite 2211 passed.
- RED then GREEN commits are present in order for both tasks; no refactor commits were needed.
- Tracer gate: `<verify>` re-ran end to end (targeted tests plus full suite) before Task 2 started; passed.

## Files Created/Modified

- `app/Rooms/FrontDesk/FrontDeskServiceProvider.php` - the room's description; points at today's code
- `examples/example-hvac/rooms/front_desk/CONTEXT.md` - the room contract (`connector: text`)
- `app/Rooms/Room.php` - `claimedWidgets` field; `$handles` docblock now says the front desk handles agent files
- `app/Rooms/RoomRegistry.php` - `guardClaimedWidgets()` called from `add()`
- `bootstrap/providers.php` - provider listed after Google Business Profile
- `tests/Pest.php` - `exampleFolderFileCount()` 23 to 24
- `tests/Feature/Rooms/FrontDeskBootTest.php`, `tests/Unit/Rooms/RoomClaimedWidgetsTest.php` - new tests
- `docs/SPEC.md`, `docs/BLUEPRINT.md`, `docs/sprints/sprint-1.1.md` - prose, Built bullets, walk-through line

## Decisions Made

- Followed the plan. The interface is a quoted string, the new guard refuses a nonexistent class (D-18, unlike `guardSections()`), and "empty" removes `agents/front-desk.md` (research A5, flagged assumption: only that dataset row changes if Ian wants a different "empty").

## Deviations from Plan

None - plan executed exactly as written.

`tests/Unit/ArchitectureTest.php`, `tests/Unit/Rooms/RoomRegistryTest.php`, `examples/example-hvac/rules/` and `examples/example-hvac/agents/` are unchanged against `main`. No ALRT-01 or ALRT-02 change is in this plan.

## Issues Encountered

None.

## Known Stubs

None.

## Threat Flags

None. The mitigations T-6-02, T-6-03 and T-6-04 are in place: the boot test pins `owns` to SPEC plus `agents/*.md`; the provider imports nothing from `App\Channels` and the architecture rule passes unchanged; claims never reach the panel and the guard refuses a non-widget.

## Carried Hand Check (not run, goes to end-of-phase UAT)

After Ian pushes the phase branch, the CI tests workflow's pgsql leg must be green with the front desk registered, `FrontDeskBootTest` and `RoomClaimedWidgetsTest` included (success criterion 2 on Postgres). Recorded as an `unrun-verify` entry (28) in `.planning/WINDOWS.md`. Nothing paused. The wrap changes no screen and adds no section, so no axe call is added; the dashboard keyboard and screen reader walk for these widgets is Phase 11's (A11Y-03).

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- The front desk room is live in the example business; later plans can build on `claimedWidgets` (the team room claims Drafts waiting) and on the front desk's registered `text` slot.
- No blockers.

## Self-Check: PASSED

- Created files exist: FrontDeskServiceProvider.php, rooms/front_desk/CONTEXT.md, FrontDeskBootTest.php, RoomClaimedWidgetsTest.php.
- Commits `ff223eb`, `6b94108`, `98333f5`, `ad42ce7` are on the branch.
- Full suite on SQLite: 2211 passed.

---
*Phase: 06-front-desk-and-team-rooms*
*Completed: 2026-10-27*
