---
phase: 05-standard-rooms
plan: 02
subsystem: testing
tags: [rooms, icm-sync, room-checks, never-left-out, ownership, pest, tdd]

requires:
  - phase: 04-room-standard
    provides: RoomChecks ownership rules, the Room descriptor, the pretend room and its test kit
  - phase: 05-standard-rooms
    provides: plan 05-01 made the list-shaped tests grow with real rooms
provides:
  - Room::$neverLeftOutBecause, a trailing optional constructor argument (null for a room a business may leave out)
  - one sync line per absent never-left-out room, naming the room and the reason, and no per-file "or remove the file" lines for the files it handles
  - a * pattern in owns passes over routers, so company knowledge can own business/services/*.md beside business/services/CONTEXT.md
affects: [05-03, 05-04, 05-05, 05-06, knowledge]

plan_head_before: 6b78b8ded3a8ebf1de109741d3610113f08940d5
plan_head_after: 1f752f595cb2682c6ec14ef856a69456bf621100

actuals:
  tokens: 5000
  tasks: 2
  commits: 5

tech-stack:
  added: []
  patterns:
    - "A room-level fact (the never-left-out mark) is a trailing optional constructor argument on the readonly Room, so every existing new Room(...) call is unchanged"
    - "D-02 is proven at unit level with a registry the test builds, so no later phase has to change the test when a real room lands"
    - "Test-file helper functions get file-unique names (neverLeftOutFile, routerClaimContract) because Pest functions are global"

key-files:
  created:
    - tests/Unit/Icm/NeverLeftOutTest.php
    - tests/Feature/Rooms/RouterClaimTest.php
  modified:
    - app/Rooms/Room.php
    - app/Icm/RoomChecks.php
    - docs/SPEC.md
    - docs/BLUEPRINT.md

key-decisions:
  - "The never-left-out line is written only when the room is absent and its folder holds no loaded file (Pitfall 2), so a folder with files keeps the existing missing-contract line alone, and a present room with a broken contract gets no never-left-out line"
  - "The per-file absent-room line is skipped when any room handling the type carries the mark, so one missing folder gives one line and the owner is never told to remove a required file"
  - "The wildcard router skip is gated on the router kind as well as the * in the entry, so a pattern still reaches claimProblem() for a walk test or a connector and is refused"

patterns-established:
  - "Never-left-out wording is a placeholder until the 2026-10-17 wording pass; it lives only in RoomChecks::errors()"

requirements-completed: [ROOM-08, ROOM-09]

coverage:
  - id: D1
    description: "A room marked as one the business can never leave out gives exactly one line (room and reason) when its folder is absent, and none of its files is told to be removed"
    requirement: ROOM-08
    verification:
      - kind: unit
        ref: "tests/Unit/Icm/NeverLeftOutTest.php (7 cases)"
        status: pass
    human_judgment: false
  - id: D2
    description: "An unmarked room keeps Phase 4's per-file absent-room line; a folder with files but no contract keeps only the missing-contract line; a present room with a broken contract gets no never-left-out line"
    requirement: ROOM-09
    verification:
      - kind: unit
        ref: "tests/Unit/Icm/NeverLeftOutTest.php#keeps the per-file line for a room that may be left out"
        status: pass
      - kind: integration
        ref: "tests/Feature/Rooms/RoomOwnershipTest.php and RoomContractTest.php (Phase 4 cases, unchanged)"
        status: pass
    human_judgment: false
  - id: D3
    description: "A * pattern in owns passes over a router it matches; a router named exactly and a walk test matched by a pattern are still refused with the house line"
    requirement: ROOM-09
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/RouterClaimTest.php (3 cases, through icm:sync)"
        status: pass
    human_judgment: false
  - id: D4
    description: "SPEC and BLUEPRINT say the wildcard router skip and the never-left-out line"
    verification:
      - kind: other
        ref: "tests/Feature/Rooms/SpecTablesTest.php (table rows unchanged) and grep of the added sentences"
        status: pass
    human_judgment: true
    rationale: "Whether the added sentences read plainly to the owner is a wording judgment; the wording of the new refusal line is also a placeholder until the 2026-10-17 wording pass"

duration: 7min
completed: 2026-10-25
status: complete
---

# Phase 5 Plan 02: Never-left-out check and wildcard router skip Summary

**RoomChecks now names a missing required room once with its reason (Room::$neverLeftOutBecause), and a `*` pattern in a room's owns passes over the house's routers, so company knowledge can own `business/services/*.md` beside its folder guide.**

## Performance

- **Duration:** 7 min
- **Started:** 2026-10-25T23:05:04Z
- **Completed:** 2026-10-25T23:12:04Z
- **Tasks:** 2
- **Files modified:** 6 (2 created, 4 modified)

## Accomplishments

- `Room` gains a trailing `?string $neverLeftOutBecause = null`. When a marked room is absent (contract not loaded as a typed room file) and `rooms/<key>/` holds no loaded file, `errors()` gives one line on `rooms/<key>/CONTEXT.md`: `the <label> room can never be left out, because <reason>; add rooms/<key>/CONTEXT.md`. Two absent marked rooms give two lines in key order; the same files checked twice give the same lines.
- `unclaimedOrAbsentError()` returns null when any room handling the type is marked, so one missing folder gives one line, not one per file, and the owner is never told to remove a required file. Unmarked rooms keep the Phase 4 line exactly.
- `claims()` skips a router matched by a `*` entry before `claimProblem()` runs. A router named exactly in owns, and a walk test matched by a pattern, are still refused with the house line (T-05-02).
- SPEC "Who owns each file" gets the two sentences; BLUEPRINT section 8 gets the services-guide clause ending `Affects: knowledge.`, in the same commit as the code.

## Task Commits

1. **Task 1 RED: never-left-out unit cases** - `ded443a` (test)
2. **Task 1 GREEN: name a missing never-left-out room in one line** - `dfc9c54` (feat)
3. **Task 2 RED: a pattern in owns passes over routers** - `89f8dfb` (test)
4. **Task 2 RED fix: quote the owns pattern** - `860c081` (test)
5. **Task 2 GREEN: owns patterns leave the house's routers alone** - `1f752f5` (feat, with SPEC and BLUEPRINT)

**Plan metadata:** committed after this file (docs: complete plan)

## TDD Gate Compliance

- **RED (Task 1):** `ded443a`. Target tests: six of seven cases in `NeverLeftOutTest` errored with `Unknown named parameter $neverLeftOutBecause` (the mark did not exist yet); the unmarked control case passed, as intended, because it characterizes Phase 4 behavior. Semantic assessment: the failure is the planned feature being absent (the new constructor argument is part of the behavior under test), not a syntax, import or fixture fault. `gsd_run check tdd-red-evidence` was not run: it supports TAP, JUnit, swift-testing and unittest reports, and Pest's compact JSON is none of those, so RED was judged by hand from the real runner output.
- **RED (Task 2), first attempt invalid:** `89f8dfb`. The first RED run failed, but on `the front matter could not be read at line 3: Reference "/notes/*.md" does not exist`: a bare `*` at the start of a YAML value is an alias, so the test failed on a fixture fault, not on the router claim. This was caught before GREEN. Corrected in `860c081` (pattern quoted as `["*/notes/*.md"]`); the GREEN change was reverted from the working tree (`git checkout -- app/Icm/RoomChecks.php`, patch kept in the scratchpad) to re-prove RED, which then showed the sync rejected with exactly `rooms/pretend/CONTEXT.md: business/notes/CONTEXT.md belongs to the house (routers and walk tests); pretend can read it`. Tests 2 and 3 of that file pass in RED by design (they pin existing refusals).
- **GREEN:** `dfc9c54` and `1f752f5`; both targeted runs pass.
- **REFACTOR:** none needed.
- Full suite: 1993 tests, 1993 passed.

## Files Created/Modified

- `app/Rooms/Room.php` - `neverLeftOutBecause` argument and docblock
- `app/Icm/RoomChecks.php` - never-left-out line in `errors()`, per-file skip in `unclaimedOrAbsentError()`, wildcard router skip in `claims()`, docblocks
- `tests/Unit/Icm/NeverLeftOutTest.php` - seven cases with a test-built registry (helpers `neverLeftOutFile`, `neverLeftOutRegistry`, `keeperContract`)
- `tests/Feature/Rooms/RouterClaimTest.php` - three cases through `icm:sync` with the pretend room
- `docs/SPEC.md`, `docs/BLUEPRINT.md` - the added sentences

## Decisions Made

See key-decisions. Net: the router skip checks the file is a router as well as the entry holding `*`, which the plan's key_link pattern allows (the `str_contains($entry, '*')` test is present) and which keeps walk tests and connectors refused.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] The planned Test 1 pattern was invalid YAML**
- **Found during:** Task 2 (RED)
- **Issue:** The plan writes the pretend contract as owning `[*/notes/*.md]`. In YAML a value starting with `*` is an alias, so the sync rejected the contract on a parse error, which is not the behavior under test.
- **Fix:** Quote the pattern in the test (`["*/notes/*.md"]`) and re-prove RED against the pre-GREEN code (see TDD Gate Compliance). Added a one-line docblock saying why it is quoted.
- **Files modified:** `tests/Feature/Rooms/RouterClaimTest.php`
- **Verification:** RED shows the planned house line; GREEN passes.
- **Committed in:** `860c081`

---

**Total deviations:** 1 auto-fixed (1 bug in the plan's test input).
**Impact on plan:** No scope change. Worth knowing for plan 05-05: company knowledge's real pattern `business/services/*.md` starts with a letter, so it needs no quoting, but any owner pattern that starts with `*` must be quoted.

## Issues Encountered

- A stray `cat > /tmp/x` in one of my own commands blocked on stdin and was killed; no files were affected. The full suite takes about two minutes, longer than the foreground timeout, so it ran in the background.

## Known Stubs

None.

## Threat Flags

None. No new endpoint, auth path or file access; both mitigations in the plan's threat model (T-05-02, T-05-03) are covered by tests.

## Human checks

None in this plan. No `unrun-verify` entry was added to `.planning/WINDOWS.md`.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Plan 05-05 can mark company knowledge and marketing with `neverLeftOutBecause` and prove the line through `icm:sync` on the real example folder; company knowledge's `business/services/*.md` will not claim the services folder guide.
- The never-left-out wording is a placeholder until the 2026-10-17 wording pass (it lives in one place, `RoomChecks::errors()`).

## Self-Check: PASSED

- Created files found: `tests/Unit/Icm/NeverLeftOutTest.php`, `tests/Feature/Rooms/RouterClaimTest.php`
- Commits found: `ded443a`, `dfc9c54`, `89f8dfb`, `860c081`, `1f752f5`

---
*Phase: 05-standard-rooms*
*Completed: 2026-10-25*
