---
phase: 07-the-spine-and-leads
plan: 06
subsystem: rooms
tags: [laravel, room-standard, filament, service-provider, room-registry, pest]

requires:
  - phase: 06-front-desk-and-team-rooms
    provides: claimedWidgets pointers on Room, the team room as the model for a room that owns nothing
  - phase: 07-the-spine-and-leads
    provides: projects table and the actions behind the contact screen (plans 07-01 to 07-05)
provides:
  - "The leads room at the room standard: LeadsServiceProvider (key leads), rooms/leads/CONTEXT.md in the example business, optional (ROOM-07)"
  - "A claim of New leads as a pointer, with the admin dashboard unchanged"
  - "RoomRegistry refuses a widget one room adds in its sections while another claims it, in either order (06 WR-09)"
  - "Marketing's contract says the web form is shared and owned by no room"
affects: [07-07 leads section, 07-08 automatic open, marketing, sales, team, knowledge]

actuals:
  tokens: 9000
  tasks: 2
  commits: 4

plan_head_before: 2cdf166ff2996ce0ffa47df32dd2f7d420545694
plan_head_after: 5cc9f9d386a48d6d6f10994f4c295e2bc099da8b

tech-stack:
  added: []
  patterns:
    - "A rule checked from both sides: each registry guard compares a new room with the rooms already registered, so the refusal holds in either registration order"
    - "A room that owns nothing has one boot case, because its empty folder and example folder are the same (04 D-21)"

key-files:
  created:
    - app/Rooms/Leads/LeadsServiceProvider.php
    - examples/example-hvac/rooms/leads/CONTEXT.md
    - tests/Feature/Rooms/LeadsBootTest.php
    - tests/Unit/Rooms/RoomWidgetOwnershipTest.php
  modified:
    - bootstrap/providers.php
    - app/Rooms/RoomRegistry.php
    - tests/Pest.php
    - tests/Feature/Rooms/FrontDeskBootTest.php
    - examples/example-hvac/rooms/marketing/CONTEXT.md
    - .planning/phases/06-front-desk-and-team-rooms/06-REVIEW-DISPOSITION.md
    - docs/BLUEPRINT.md
    - docs/sprints/sprint-1.1.md

key-decisions:
  - "The leads room is listed after TeamServiceProvider and claims New leads without adding it, so the panel keeps the widget exactly once and in place"
  - "WR-09 is closed by a second check in each of guardSections() and guardClaimedWidgets(), not by a change to widgetClasses() or addWidgetsTo()"
  - "The BLUEPRINT open item for the widget guard was rewritten in place as a Built bullet (section 6), not moved"

patterns-established:
  - "Boot test for an owns-nothing room: registered room, contract equal to SPEC rows, folder holds only CONTEXT.md, panel holds the claimed widget once"

requirements-completed: [ROOM-07]

coverage:
  - id: D1
    description: "The leads room is registered, owns nothing, reads the marketing lead sources and offers, has no connector, writes no records, and holds only CONTEXT.md in the example folder"
    requirement: ROOM-07
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/LeadsBootTest.php#boots the leads room"
        status: pass
    human_judgment: false
  - id: D2
    description: "The leads room is optional: a folder without rooms/leads/ activates one file fewer, hasRoom('leads') is false, and New leads still shows"
    requirement: ROOM-07
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/LeadsBootTest.php#lets a business leave the leads room out"
        status: pass
    human_judgment: false
  - id: D3
    description: "The leads room claims New leads as a pointer; the panel holds it once and the dashboard order is unchanged"
    requirement: ROOM-07
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/LeadsBootTest.php#boots the leads room"
        status: pass
      - kind: integration
        ref: "tests/Feature/Rooms/FrontDeskBootTest.php (line 90, only the leads room claims New leads)"
        status: pass
    human_judgment: false
  - id: D4
    description: "RoomRegistry refuses a widget one room adds in its sections while another claims it, in either registration order, with the named messages"
    verification:
      - kind: unit
        ref: "tests/Unit/Rooms/RoomWidgetOwnershipTest.php (three cases)"
        status: pass
    human_judgment: false
  - id: D5
    description: "Marketing's contract no longer leaves the web form to the leads room and says it is shared and owned by no room"
    verification: []
    human_judgment: true
    rationale: "Wording in an example file; no test asserts marketing's CONTEXT.md body (D-19), so the sentence is read by a person"

duration: 25min
completed: 2026-10-30
status: complete
---

# Phase 7 Plan 06: The leads room at the room standard Summary

**LeadsServiceProvider registers an optional leads room that owns nothing, reads marketing's lead sources and offers, and claims New leads as a pointer, while RoomRegistry now refuses a widget with two owner rooms in either registration order (06 WR-09).**

## Performance

- **Duration:** about 25 min
- **Completed:** 2026-10-30T22:36Z
- **Tasks:** 2
- **Files modified:** 12 (4 created, 8 modified)
- **Commits:** 4 task commits (two RED, two GREEN)

## Accomplishments

- The leads room joins the room standard (ROOM-07): provider with key `leads`, listed after `TeamServiceProvider` in `bootstrap/providers.php`; `rooms/leads/CONTEXT.md` in the example business with `owns: none`, `reads: [business/lead-sources.md, business/offers/*.md]`, `writes_records: none`, `connector: none`; the room lists no tables, file types, slot, timeline entries or model nicknames.
- The room is optional (D-03): a folder without `rooms/leads/` activates with one file fewer, and New leads still shows on `/admin`.
- The room claims `NewLeads` in `claimedWidgets` (D-15). `widgetClasses()` never includes it, the admin panel holds it once, and the dashboard order (Drafts waiting, Customers waiting while you handle it, New leads, Today's conversations) is unchanged.
- WR-09 closed: `guardSections()` and `guardClaimedWidgets()` each check the rooms already registered, so the refusal fires whichever room registers first. The 06-REVIEW-DISPOSITION row is `fixed`.
- Marketing's CONTEXT.md now says the web form is shared and owned by no room (D-19).
- The two named test lines changed and nothing else among existing lines: `tests/Pest.php:295` (`return 26;`) and `tests/Feature/Rooms/FrontDeskBootTest.php:90`. `Room.php`, `RoomChecks.php` and `RecordsWriter.php` are unchanged (D-17, D-20).

## Task Commits

1. **Task 1: the leads room is registered, optional, and claims New leads (tracer)**
   - RED `c168583` (test): two cases fail on the planned assertions (`hasRoom('leads')` false; file count 25 where 24 expected, since no room exists yet)
   - GREEN `87439ac` (feat)
2. **Task 2: one owner room per widget, and the web form stays shared**
   - RED `0162735` (test): the two refusal cases fail with "Exception [LogicException] not thrown."; the accept case passes
   - GREEN `5cc9f9d` (feat)

**Plan metadata:** recorded in the docs commit that follows this summary.

## TDD Gate Compliance

Both tasks have a `test(07-06)` commit before the matching `feat(07-06)` commit. RED semantic assessment: in Task 1 the target tests ran and failed on the planned assertions (no leads room registered), not on setup or syntax errors. In Task 2 exactly the two refusal cases failed because no refusal existed; the third (accept) case passed as expected. No separate `gsd_run check tdd-red-evidence` record was produced: Pest's console report is not one of the classifier's supported formats, so the evidence is the recorded Pest JSON summary lines above.

## Files Created/Modified

- `app/Rooms/Leads/LeadsServiceProvider.php` - the leads room's description
- `bootstrap/providers.php` - registers it after `TeamServiceProvider`
- `examples/example-hvac/rooms/leads/CONTEXT.md` - the room's contract in the example business
- `tests/Feature/Rooms/LeadsBootTest.php` - boot and optional cases
- `tests/Unit/Rooms/RoomWidgetOwnershipTest.php` - the WR-09 cases in both registration orders
- `app/Rooms/RoomRegistry.php` - the two WR-09 refusals and extended docblocks
- `tests/Pest.php` - `exampleFolderFileCount()` 25 to 26 (line 295)
- `tests/Feature/Rooms/FrontDeskBootTest.php` - line 90, the New leads claim guard
- `examples/example-hvac/rooms/marketing/CONTEXT.md` - D-19 sentence
- `.planning/phases/06-front-desk-and-team-rooms/06-REVIEW-DISPOSITION.md` - WR-09 `fixed`
- `docs/BLUEPRINT.md` - Built bullets in sections 2, 3 and 6
- `docs/sprints/sprint-1.1.md` - one walk-through line (plan 07-06)

## Decisions Made

None beyond the plan. The widget guard is a second check inside each existing guard, so `widgetClasses()` and `addWidgetsTo()` are untouched.

## Deviations from Plan

None - plan executed exactly as written.

BLUEPRINT note: the earlier Decided bullets at section 1 and section 6 that say "New leads stays unclaimed until the leads room" are now out of date. The plan did not ask for them to change, so they were left; a later docs pass can reword them.

## Issues Encountered

None. The full suite passes on SQLite (2369 tests) after each task commit.

## Known Stubs

None.

## Threat Flags

None. No new network endpoint, auth path or file access; the threat register items T-07-14, T-07-15 and T-07-16 are covered by LeadsBootTest, RoomWidgetOwnershipTest and the optional case.

## Human check left unrun (carried to UAT)

After Ian pushes the phase branch, the CI pgsql leg should be green with LeadsBootTest, FrontDeskBootTest and RoomWidgetOwnershipTest included (ROOM-05 on Postgres for the leads room). This was not run (a GSD run never pauses for a live hand check). Recorded as `unrun-verify` entry 40 in `.planning/WINDOWS.md`.

## Next Phase Readiness

Plan 07-07 adds the section listing open projects and its axe check; 07-08 adds the automatic open and extends this contract's body. Nothing blocks them.

## Self-Check: PASSED

- Created files exist: LeadsServiceProvider.php, rooms/leads/CONTEXT.md, LeadsBootTest.php, RoomWidgetOwnershipTest.php, this SUMMARY.
- Commits `c168583`, `87439ac`, `0162735`, `5cc9f9d` are ancestors of HEAD; `git rev-list --count` from the plan's start HEAD is 4.
- All acceptance criteria for both tasks were re-run and pass; `php artisan test --compact` passes (2369 tests).

---
*Phase: 07-the-spine-and-leads*
*Completed: 2026-10-30*
