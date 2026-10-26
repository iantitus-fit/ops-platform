---
phase: 05-standard-rooms
plan: 09
subsystem: rooms
tags: [rooms, google-business-profile, icm-sync, validation, plain-words, pest, tdd]

requires:
  - phase: 05-standard-rooms
    provides: 05-08 GbpCategoriesType (primary_category required, single-file under D-27) and the example categories file
provides:
  - the D-07 shape checks on the Google Business Profile categories file, run through icm:sync
  - additional_categories as an optional list of text, at most 9 entries, each once, none equal to the primary, compared ignoring case and extra spaces
  - one plain refusal line per problem, in a fixed order, with the previous version staying live
  - tests/Feature/Rooms/GbpCategoriesTest.php (19 cases)
affects: [marketing, knowledge, CONN-01]

plan_head_before: a944437c1e04c37f7e103c93398b5a8a920e5fe4
plan_head_after: ee8eb3993a5beb165ae7b8374ea7090890d67c69

actuals:
  tokens: 9000
  tasks: 2
  commits: 4

tech-stack:
  added: []
  patterns:
    - "A room file type's checks() reads the front matter list directly, looks only at text entries, and leaves non-text entries to the rules so one problem is never reported twice"
    - "A count limit on a list is its own sentence in checks(), not the max rule, whose sentence reads wrongly for a list (Pitfall 9)"

key-files:
  created:
    - tests/Feature/Rooms/GbpCategoriesTest.php
  modified:
    - app/Rooms/GoogleBusinessProfile/GbpCategoriesType.php

key-decisions:
  - "A category is compared by collapsing whitespace runs, trimming and lower-casing with mb_strtolower, so case and extra spaces never make two spellings different"
  - "Blank entries are reported as empty by position and are not counted as repeats of each other"
  - "A repeated value is quoted by its first spelling, once however many times it repeats, through PlainWords::oneLine"

requirements-completed: [ROOM-11]

coverage:
  - id: D1
    description: "Ten or more additional categories are refused through icm:sync with one line 'additional_categories has <n> categories; Google takes at most 9 after the primary, so remove <n-9>' and the live version unchanged; nine pass; the count is written in code, not with the max rule"
    requirement: ROOM-11
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/GbpCategoriesTest.php (refuses ten additional categories; says how many to remove; accepts nine)"
        status: pass
    human_judgment: false
  - id: D2
    description: "A repeat (ignoring case and extra spaces), a category equal to the primary, an empty entry, a non-text entry and a non-list each give their exact plain line, one per problem, in the fixed order too many, empty, repeats, primary"
    requirement: ROOM-11
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/GbpCategoriesTest.php (repeat, three times, primary, empty entry, blank entries, not text, fixed order, empty before repeats, group of fields)"
        status: pass
    human_judgment: false
  - id: D3
    description: "Owner values reach a refusal only through PlainWords::oneLine (cut to 80 characters) and a hidden character is marked by the report (T-05-19, D-19); an empty list, an absent list and the example file as shipped pass; a blank primary gets the ordinary missing line"
    requirement: ROOM-11
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/GbpCategoriesTest.php (cuts a long repeated value to 80 characters; marks a hidden character; accepts an empty list, an absent list and the example; refuses a blank primary)"
        status: pass
    human_judgment: false
  - id: D4
    description: "The refusal wording reads plainly to an owner"
    verification: []
    human_judgment: true
    rationale: "The lines are placeholder wording until the wording pass; automated tests pin the current text, not whether it reads well"

duration: 8min
completed: 2026-10-26
status: complete
---

# Phase 5 Plan 09: Google Business Profile categories checks Summary

**The Google Business Profile categories file is now checked for shape through icm:sync: at most nine additional categories, each written once, none the same as the primary, compared ignoring case and extra spaces, with one plain line per problem in a fixed order and the previous version kept live on every refusal.**

## Performance

- **Duration:** 8 min
- **Started:** 2026-10-26T00:25:40Z
- **Completed:** 2026-10-26T00:34:00Z
- **Tasks:** 2 (Task 1 a tracer with tdd, Task 2 tdd)
- **Files:** 1 created, 1 changed (plus this SUMMARY and the planning files)

## Accomplishments

- `GbpCategoriesType::rules()` gains `additional_categories` (`bail|nullable|array`) and `additional_categories.*` (`bail|string`). A value that is not a list, or an entry that is not text, gets the sync's ordinary plain-words line (`additional_categories must be a list (you wrote Heating contractor)`, `additional_categories item 2 must be text; wrap it in quotes (you wrote 7)`).
- `GbpCategoriesType::checks()` returns, in a fixed order, the count line (`additional_categories has 10 categories; Google takes at most 9 after the primary, so remove 1`), one line per empty entry by 1-based position, one line per repeated value in list order quoting its first spelling, and one line when any entry equals the primary. The count is its own sentence, not the max rule (Pitfall 9). There is no `max:` anywhere in the file.
- A private normalizer collapses every run of whitespace to one space, trims, and lower-cases with `mb_strtolower`, so `furnace  REPAIR service` repeats `Furnace repair service`. Blank entries are reported as empty and are not counted as repeats of each other. Non-text entries are skipped in `checks()` because the rules already report them, so a `7` gives exactly one line.
- Owner values (a repeated category, the primary) appear only through `PlainWords::oneLine()`; a 400-character value is cut to 80 characters, and a zero-width character inside a value is marked `[hidden character]` by `ValidationReport::lines()` (T-05-19, D-19). The check never calls Google or any outside list of categories (D-07, D-11).
- `tests/Feature/Rooms/GbpCategoriesTest.php` holds 19 cases, all through `icm:sync` with `rejectedErrors()` and a check that the live version id is unchanged on each refusal.

## Task Commits

1. **Task 1 RED: Google takes at most 9 more categories** - `e4ce7ce` (test)
2. **Task 1 GREEN: at most nine categories after the primary** - `d74e9c8` (feat)
3. **Task 2 RED: no repeats and none equal to the primary** - `6d8c69d` (test)
4. **Task 2 GREEN: categories are compared ignoring case and spaces** - `ee8eb39` (feat)

**Plan metadata:** committed with this SUMMARY (docs).

## TDD Gate Compliance

- **Task 1 RED:** `e4ce7ce`. Of 4 target tests, 3 failed on the planned behavior (`Expected status code 1 but received 0`: ten categories, fourteen categories, non-list were all accepted) and the nine-categories case passed by design. Semantic assessment: each failure is the sync accepting a file the plan says must be refused, with no syntax, load or fixture fault. `gsd_run check tdd-red-evidence` was not run because Pest's compact JSON is not one of its supported formats (same as 05-02 through 05-08); RED judged by hand from the runner output.
- **Task 1 GREEN:** `d74e9c8`; both verify files (17 tests) pass. Tracer gate: `<verify>` is automated only, so under end-of-phase mode it was re-run end to end and expansion continued. (The non-list case already passed after GREEN through the new `array` rule; the old rules had no `additional_categories` rule at all, so the sync accepted the string, which is why it failed in RED.)
- **Task 2 RED:** `6d8c69d`. Of 18 tests, 9 failed on the planned behavior (repeat, three-times, primary, empty entry, blank entries, empty-before-repeats, long value, hidden character, and the fixed-order case, which got only the count line), and 9 passed by design (the Task 1 four, the not-text entry, the blank primary, and the three accept cases).
- **Task 2 GREEN:** `ee8eb39`; both verify files (32 tests) and then the full suite (2148 tests, 16781 assertions) pass.
- **REFACTOR:** none needed.

## Decisions Made

- See `key-decisions`. In short: owner-style comparison by whitespace collapse, trim and `mb_strtolower`; blank entries are empty, not repeats; a repeat is quoted once by its first spelling.

## Deviations from Plan

**1. [Rule 2 - Missing critical functionality] A map written where a list belongs passed the `array` rule**
- **Found during:** Task 2 GREEN
- **Issue:** Laravel's `array` rule accepts a map such as `additional_categories: {first: Heating contractor}`, so the file would activate with a shape the plan calls "not a list", and the 1-based entry positions in the empty-entry line would not mean anything.
- **Fix:** `checks()` returns the existing `Array` plain-words line (`additional_categories must be a list (you wrote a group of fields)`) when the value is an array that is not a list. One added test covers it.
- **Files modified:** app/Rooms/GoogleBusinessProfile/GbpCategoriesType.php, tests/Feature/Rooms/GbpCategoriesTest.php
- **Commit:** `ee8eb39`

**2. [Rule 1 - Test hygiene] A literal zero-width character in the test source**
- **Found during:** Task 2 RED commit
- **Issue:** The hidden-character case first landed with the invisible character written literally in the PHP file, which is invisible to a reader. The case was committed in `6d8c69d` that way.
- **Fix:** rewritten in the GREEN commit with `"\u{200B}"` in a variable so the source shows what it tests.
- **Files modified:** tests/Feature/Rooms/GbpCategoriesTest.php
- **Commit:** `ee8eb39`

**Total deviations:** 2 auto-fixed (1 missing check, 1 test hygiene). **Impact:** none on scope; the first closes a hole in the plan's "not a list" behavior.

## Issues Encountered

None. Final full suite: 2148 passed, 16781 assertions.

## Verification

- Task 1 verify (17 tests), Task 2 verify (32 tests, then the full suite, 2148) pass.
- Acceptance greps pass: `Google takes at most 9 after the primary` and `mb_strtolower` and `PlainWords::oneLine` present in `GbpCategoriesType.php`, zero `max:`; the test file holds the exact lines for ten, repeat, primary, empty entry, not-text entry and the three-line case.
- Postgres: no local Postgres. The new tests have no driver guard, so CI's pgsql leg runs them when Ian pushes the branch. The phase gate (CI green on both databases) is still ahead before `/gsd-verify-work`.
- Nothing in this plan called Google or any outside network.

## Hand checks for UAT

None new. The refusal wording is placeholder until the wording pass (coverage D4). Nothing was added to `.planning/WINDOWS.md`.

## Known Stubs

None.

## Threat Flags

None. T-05-19 is mitigated and covered by the long-value and hidden-character tests. No agent action was added, so no chokepoint is named.

## Walk-through log

No new Laravel concept appeared in this plan (checks() on a room file type was explained in 05-02 and 05-08), so no line was added to the sprint file.

## Next Phase Readiness

This is the last plan of Phase 5. All nine plans have a SUMMARY. Ahead: the full suite is green locally (2148); Ian pushes the branch so CI runs both databases, then `/gsd-verify-work` and the end-of-phase UAT, which collects the unrun hand checks recorded in `.planning/WINDOWS.md`.

## Self-Check: PASSED

- Created and changed files exist: `tests/Feature/Rooms/GbpCategoriesTest.php`, `app/Rooms/GoogleBusinessProfile/GbpCategoriesType.php`.
- Commits `e4ce7ce`, `d74e9c8`, `6d8c69d`, `ee8eb39` are ancestors of HEAD.
- Full suite passes (2148).

---
*Phase: 05-standard-rooms*
*Completed: 2026-10-26*
