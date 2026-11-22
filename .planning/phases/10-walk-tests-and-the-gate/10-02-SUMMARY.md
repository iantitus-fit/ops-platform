---
phase: 10-walk-tests-and-the-gate
plan: 02
subsystem: icm-sync
tags: [walk_test, validator, loader, compile, plain-words, tdd, pest]

requires:
  - phase: 04-room-standard
    provides: RoomChecks walk-test ownership refusal (RoomChecks.php:332) and the Loader::TYPED list
provides:
  - walk_test is a typed file kind; every walk file is compiled into compiled.index.walk_test (a list, path order)
  - App\Icm\WalkFileChecks, wired onto Validator.php:125, refusing a broken walk file in one plain line naming the file and the field
  - App\Icm\Walks value types (WalkFile, WalkStep, WalkStepKind, WalkCheck, WalkCheckKind) and the closed D-09 check vocabulary
  - D-10 rule: a books item or an expect phrase naming a day, date or clock time is refused when the walk has no start
  - minimalWalkFile() test helper in tests/Pest.php
affects: [10-03, 10-04, 10-05, 10-06, 10-07, walk gate runner, walks:record]

actuals:
  tokens: 14100
  tasks: 2
  commits: 4

tech-stack:
  added: []
  patterns:
    - "Folder-level check class beside RoomChecks, appended line-neutral to the Validator's one array_push"
    - "Compile arm keeps every file of a type as a list (not the default last-one-wins arm)"
    - "Walk file read back through ActiveConfig::filesOfType('walk_test') into readonly value objects"

key-files:
  created:
    - app/Icm/WalkFileChecks.php
    - app/Icm/Walks/WalkFile.php
    - app/Icm/Walks/WalkStep.php
    - app/Icm/Walks/WalkStepKind.php
    - app/Icm/Walks/WalkCheck.php
    - app/Icm/Walks/WalkCheckKind.php
    - tests/Feature/Icm/WalkFileTest.php
    - tests/Unit/Icm/WalkCheckTest.php
  modified:
    - app/Icm/Loader.php
    - app/Icm/FolderSync.php
    - app/Icm/Validator.php
    - tests/Pest.php
    - tests/Feature/Rooms/RouterClaimTest.php
    - tests/Feature/Rooms/RoomOwnershipTest.php
    - map/objects/folder/agent-file-and-version.md
    - docs/BLUEPRINT.md

key-decisions:
  - "WalkFileChecks::errors takes array|Collection, because the Validator holds its sorted Collection and a test hands over the Loader's array"
  - "A D-10 refusal is checked before the must-be-one-of rule, and only for a phrase that is not on the list, so a service slug such as sat-repair is never read as a day"
  - "A books item that names a time echoes the whole value as written; an expect phrase echoes only the time found"
  - "The 51st walk path (in path order) carries the folder-level cap line"

patterns-established:
  - "Break-on-purpose for every new test that is green on its first run, with sha256 before and after restore"

requirements-completed: [WALK-01]

coverage:
  - id: D1
    description: "walk_test is a typed file kind and every walk file compiles into compiled.index.walk_test in path order"
    requirement: WALK-01
    verification:
      - kind: integration
        ref: "tests/Feature/Icm/WalkFileTest.php#compiles every walk file into the candidate (WALK-01)"
        status: pass
      - kind: integration
        ref: "tests/Feature/Icm/WalkFileTest.php#leaves a folder with no walk files as today (edge: empty)"
        status: pass
    human_judgment: false
  - id: D2
    description: "A compiled walk file reads back as a WalkFile with numbered steps and parsed checks"
    requirement: WALK-01
    verification:
      - kind: integration
        ref: "tests/Feature/Icm/WalkFileTest.php#reads a compiled walk file as a WalkFile"
        status: pass
      - kind: unit
        ref: "tests/Unit/Icm/WalkCheckTest.php (parse, vocabulary)"
        status: pass
    human_judgment: false
  - id: D3
    description: "A broken walk file is refused in one plain line naming the file and the field; the previous version stays live"
    requirement: WALK-01
    verification:
      - kind: integration
        ref: "tests/Feature/Icm/WalkFileTest.php#refuses a broken walk file in plain words naming the file and the field"
        status: pass
    human_judgment: true
    rationale: "Three refusal sentences (books not a time, says too long, a check under a script item naming a time) were worded by the executor from the plan's templates; Ian should read them."
  - id: D4
    description: "D-10: a time named with no start is refused in the UI-SPEC words; with a start a named booking time is valid"
    requirement: WALK-01
    verification:
      - kind: integration
        ref: "tests/Feature/Icm/WalkFileTest.php#refuses a broken walk file ... (three no-start rows) and #gives no error for a books item that names a time when the walk has a start"
        status: pass
      - kind: unit
        ref: "tests/Unit/Icm/WalkCheckTest.php#finds a day, a date or a clock time named in a phrase"
        status: pass
    human_judgment: false
  - id: D5
    description: "Caps and echo safety: 30 script items, 1,600-character says, 14-day wait, 50 walk files; an owner value stays on one line"
    requirement: WALK-01
    verification:
      - kind: integration
        ref: "tests/Feature/Icm/WalkFileTest.php#refuses a folder with more than 50 walk files, #echoes an owner value on one line, dataset rows for the other caps"
        status: pass
    human_judgment: false
  - id: D6
    description: "Walk tests stay house-owned: a room claiming tests/walk.md is still refused with the ownership line only"
    requirement: WALK-01
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/RouterClaimTest.php#still refuses a walk test a pattern matches; tests/Feature/Rooms/RoomOwnershipTest.php#a walk test row"
        status: pass
    human_judgment: false

duration: ~30min (start time not captured; estimated)
completed: 2026-11-22
status: complete
commits: 4
plan_head_before: f5df66836ca4f7d6c7deb407dc45e6d17f812d95
plan_head_after: 536f9c12799241a41fca597e3f6c03632ae79020
---

# Phase 10 Plan 02: Walk files are typed, checked and compiled Summary

**Walk-test files are a typed kind: the sync compiles every one into `compiled.index.walk_test`, and refuses a broken one in a plain line naming the file and the field, including D-10's rule that a named time needs a named start.**

## Performance

- **Duration:** about 30 min (the start time was not captured; estimated from the run's clock)
- **Completed:** 2026-11-22T16:58Z (code), summary written after
- **Tasks:** 2 (Task 1 a tracer, Task 2 auto; both `tdd="true"`)
- **Files modified:** 16 (8 new, 8 changed)

## Accomplishments

- `walk_test` moved from `Loader::LATER_SPRINT` (`Loader.php:31`) to `Loader::TYPED` (`Loader.php:24`), each on its own line; `automation` stays skipped. `FolderSync::compile` keeps every walk path in `compiled.index.walk_test` (init key on the same line 281, new arm after the connector arm). `FolderSync.php:299` and `:301` and `Validator.php:125` did not move.
- `WalkFileChecks` refuses name, persona, channel (sms only), start, script (1 to 30 items, at least one says, each item exactly one of says, wait or books), wait (at most 14 days), books, every expect phrase against the closed D-09 list, a walk with no check, and a folder with more than 50 walk files. Messages hold no exclamation point or em dash; every echoed owner value goes through `PlainWords`.
- `WalkCheck::parse` reads the twelve D-09 phrases (straight or curly apostrophe, any letter case, `labeled <label>` for the eight `ReplyLabel` values, a price sentence for a service slug); `WalkCheck::namedTime` finds a weekday, ISO date or clock time.
- `WalkFile::fromCompiled` gives the future gate runner name, persona, channel, start, numbered steps (position in `script`, counting from 1), parsed checks, end checks and `customerTurns()`.
- Map card and BLUEPRINT section 8 updated (Built bullet, WALK-01, D-09, D-10, Affects: knowledge).

## Task Commits

1. **Task 1 (tracer): walk files typed, validated, compiled, read back**
   - RED `cf160b8` test(10-02): walk files compile into the candidate (WALK-01)
   - GREEN `fd91a47` feat(10-02): walk files are typed and compiled (WALK-01)
2. **Task 2: broken walk file refused in plain words; time without a start refused**
   - RED `6960bb4` test(10-02): walk files are refused in plain words (WALK-01, D-10)
   - GREEN `536f9c1` feat(10-02): walk files are refused in plain words (WALK-01, D-09, D-10)

**Plan metadata:** the docs commit that holds this file (see `git log`).

Tracer gate (Task 1, no `gate` attribute, automated-only verify, `end-of-phase` mode): the verify command was re-run end to end after the commits, passed (47 tests, `RESULT: PASS`), logged "Tracer verified end-to-end, expanding", and Task 2 started.

## TDD Gate Compliance

RED then GREEN for both tasks; no REFACTOR commit was needed. `git log --grep` finds `test(10-02)` before `feat(10-02)` for each task.

### RED evidence and semantic assessment

- **Task 1** (`cf160b8`): 20 of the new cases failed, 0 passed. 17 `WalkCheckTest` cases (the lazy phrase dataset counted once, plus the labeled, null and vocabulary cases) errored with `Class "App\Icm\Walks\WalkCheck" not found` or `WalkCheckKind not found`; 1 `WalkFileTest` case errored with `Class "App\Icm\Walks\WalkFile" not found`; the two cases that read the index errored with `Undefined array key "walk_test"` (the Loader still skipped the type). Semantic assessment: every failure is the planned absence (missing class, or the walk file not typed and compiled), not a syntax, setup or fixture fault. Datasets were made lazy (`fn () => [...]`) so each case fails on its own instead of the whole file failing to load.
- **Task 2** (`6960bb4`): 37 of 73 cases failed, 36 passed. The 25 `WalkFileTest` refusal cases (23 dataset rows, the 50-file cap, the one-line echo) failed with `Expected status code 1 but received 0` (the sync accepted the broken file because `WalkFileChecks` returned nothing); 12 `WalkCheckTest::namedTime` cases errored with `Call to undefined method WalkCheck::namedTime()`. The 36 passing cases were the Task 1 cases (33) plus the three first-run-green cases listed below. Semantic assessment: every failure is the planned missing behavior.
- The machine classifier `gsd_run check tdd-red-evidence` was not run: Pest's console output is not one of its supported report formats and no JUnit record was captured. The semantic inspection above is by reading each failing case's message. This is recorded as a gap, not a pass of the classifier.

### Break-on-purpose: new tests that were green on their first run

Three new cases passed in the Task 2 RED run. Each was broken on purpose, seen RED, and restored byte for byte.

**1. `keeps exclamation points and em dashes out of every refusal`** (tests/Feature/Icm/WalkFileTest.php)

- First attempt, break A: added `!` to the expected line of two dataset rows (`...is required!`). Result: **the test stayed GREEN** (`passed 1 1`). The test was wrong: `expect($line)->not->toContain('!', "\u{2014}")` in Pest passes unless the line holds both characters. A real defect in my test, caught by this step. Restored from backup (sha256 `948c5a6d0ea8cd1977f031ddaeb19fa25aa921b8374559424a66b9a19414c2c6` before and after, `diff` empty). Fixed the test to check each character in its own `not->toContain` call (still uncommitted, so the RED commit holds the fixed version).
- Break B (fixed test), sha256 before: `e15b4a13c93fc0c965d8958219d2b1b03fce3dafa85656c001448b0c20dbc70b`. Broke it by adding `!` to the two `... is required` expected lines. RED line: `Expecting 'tests/dana.md: name is missin…uired!' not to contain '!'.`
- Break C, same file: added an em dash to the `channel voice` expected line. RED line: `Expecting 'tests/dana.md: channel must b…e) — x' not to contain '—'.`
- After restore: sha256 `e15b4a13c93fc0c965d8958219d2b1b03fce3dafa85656c001448b0c20dbc70b` (matches), `diff` against the backup empty. (This is a test file, so the string-inspection case was broken by editing an expected line, as the run instructions said.)

**2. `gives no error for a books item that names a time when the walk has a start`** and **3. `does not read a service slug that looks like a day as a time`** (tests/Feature/Icm/WalkFileTest.php)

- Production file `app/Icm/WalkFileChecks.php` (the Task 1 stub that returned `[]`), sha256 before: `5f8f2b4560357395de651763ac61046f32aeb213c5f739f1545fda2093a35f37`.
- Broke it by making `errors()` return one error (`broken on purpose`) instead of `[]`.
- RED lines (both cases failed, `failed tests 2 passed 0`): `Failed asserting that two arrays are identical. --- Expected +++ Actual ... Array &0 [] +Array &0 [ 0 => Array &1 [ 'file' => 'tests/dana.md', 'field' => null, 'message' => 'broken on purpose' ] ]`.
- Restored from backup. sha256 after: `5f8f2b4560357395de651763ac61046f32aeb213c5f739f1545fda2093a35f37` (matches), `git diff -- app/Icm/WalkFileChecks.php` empty (0 lines). The broken state was never committed.

Task 1 had no first-run-green case (all 20 were RED), so no break was needed there. The two forced fixture edits in `RouterClaimTest` and `RoomOwnershipTest` are edits to existing cases, not new ones; they were checked the other way: after `WalkFileChecks` landed and before the edit, exactly those two cases failed (21 rows red: the ownership line plus four extra lines `name`, `persona`, `channel`, `script is missing`), and after the one-line edits each they went green.

## Verification (acceptance criteria and plan commands)

Task 1: `sed -n 24p Loader.php` holds `'walk_test'`; `sed -n 31p` is `LATER_SPRINT = ['automation']`; `sed -n 281p FolderSync.php` holds `'walk_test' => []`; lines 299 and 301 still hold the `offer` and `agent` arms; `Validator.php:125` holds `(new WalkFileChecks)->errors($files)`; `minimalWalkFile()` is in Pest.php and `sed -n 85p tests/Pest.php` is still the `copyDirectory` line; the map card says `compiled.index.walk_test`. Automated verify: `47 passed`, `RESULT: PASS`.

Task 2: `Name a start for the walk, or word the check as the earliest time offered.` is in `WalkFileChecks.php`; `namedTime` signature is in `WalkCheck.php`; `RouterClaimTest.php:56` and `RoomOwnershipTest.php:80` hold `minimalWalkFile()` and `RouterClaimTest.php:59` still holds the ownership text; the `git diff main` count prints `4`; BLUEPRINT line 563 is a `**Built:**` bullet ending `Phase 10 context D-09, D-10). Affects: knowledge.` Automated verify: 542 passed, `RESULT: PASS`.

Plan `<verification>`: `php artisan test --compact tests/Feature/Icm tests/Feature/Rooms tests/Unit` gave `1263 passed`; `check_map.py` printed `RESULT: PASS`. `vendor/bin/pint --dirty --format agent` was clean before each feat commit.

## Decisions Made

- `WalkFileChecks::errors` accepts `array|Collection`.
- D-10 is checked before the must-be-one-of rule, but only for a phrase that is not on the list, so a slug like `sat-repair` is not read as a day.
- A books item naming a time echoes the whole value (`2026-12-02 11:00`, as the plan's example shows); an expect phrase echoes the time it found (`3pm`).
- An unquoted `start:` or `books:` that YAML turns into a date loses its time, so it reads as a bad value and is refused with "in quotes" wording.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] `errors(array $files)` could not be wired as the plan wrote it**
- **Found during:** Task 1
- **Issue:** The plan gives `public function errors(array $files): array` and wires `(new WalkFileChecks)->errors($files)` at `Validator.php:125`, where `$files` is a sorted `Collection`; an `array`-only signature throws a TypeError. The plan's Task 2 tests also hand it the Loader's plain array.
- **Fix:** `errors(array|Collection $files): array`, normalized with `collect()`. The `must_haves` artifact string `public function errors(array $files): array` therefore does not appear literally.
- **Files modified:** app/Icm/WalkFileChecks.php
- **Committed in:** fd91a47

**2. [Rule 3 - Blocking] `make:enum` wrote the enums to app/Enums/Icm/Walks/**
- **Found during:** Task 1
- **Issue:** The artisan generator places enums under `app/Enums`; the plan puts them in `app/Icm/Walks/`.
- **Fix:** Moved both files to `app/Icm/Walks/`, removed the empty generated folders, rewrote the namespaces.
- **Committed in:** fd91a47

**3. [Rule 1 - Bug, in my own test] The exclamation-point and em-dash test could not fail**
- **Found during:** Task 2 break-on-purpose (break A above)
- **Issue:** `->not->toContain('!', em dash)` passes unless both characters are present.
- **Fix:** one `not->toContain` per character. Re-broken and seen RED (breaks B and C).
- **Committed in:** 6960bb4

**4. [Verification note] Two plan acceptance greps cannot run as written on macOS**
- `sed -n 125p ... | grep -q '(new WalkFileChecks)->errors($files)'` and `grep -q 'public static function namedTime(string $text): ?string'` return no match under BSD grep's basic regex (a `$` before a letter). Both pass with `grep -qF` (fixed string). The lines are there.

**5. [Plan placement] The test commit for Task 2 also holds two extra cases and three extra dataset rows the plan did not list**
- `does not read a service slug that looks like a day as a time`, a `persona missing` row, a `start not a real date` row, a `says too long` row, `an end check that is not a list`, `item with none of says, wait or books`, and `a check under an item naming a time with no start`. They cover the T-10-04 caps and edges the plan asks tests for. No existing test was edited beyond the two named.

---

**Total deviations:** 5 (2 Rule 3, 1 Rule 1 in my own test, 2 notes)
**Impact on plan:** None on scope or behavior. No file outside `files_modified` was touched. No map-cited line moved.

## Refusal wording the plan gave only by pattern (for Ian to read)

The plan and UI-SPEC give these exactly and they are used verbatim: name or persona missing, channel, start, script missing, no says item, 31 items, exactly one of says, wait or books, wait "soon", the two D-10 lines, no check, the 50-file cap, and the must-be-one-of lists. Four sentences were written by the executor from those templates because the plan describes the rule but not the words:

- wait over 14 days: `script item 3 wait must be 14 days or less (you wrote 20 days)` (the house `Max` template)
- books not a time: `script item 4 books must be the earliest time offered or a date and time such as 2026-12-02 11:00 (you wrote whenever)` (the words come from the plan's truths)
- says too long: `script item 1 says is too long; a text can have at most 1600 characters`
- a check under a script item naming a time with no start: `script item 2 expect names a time (friday) but the walk has no start. Name a start ...` (the D-10 line with the item named)

They are all in `walkFileRefusals()` in `tests/Feature/Icm/WalkFileTest.php` and in `app/Icm/WalkFileChecks.php`, one place each, so a rewording is a two-line change.

## Known Stubs

None. `WalkFile`, `WalkStep` and the check types are fully built; nothing reads them yet (the gate runner in 10-05 does), which is the plan's intent, not a stub.

## Threat Flags

None. No new endpoint, auth path or schema; the only new surface is owner-written front matter, which is the plan's T-10-03 to T-10-05 and is mitigated (one-line echo test, caps with tests, room ownership tests still green).

## Issues Encountered

- `.planning/STATE.md` and `.planning/state.json` were already modified when the run started (unrelated to this plan); they are updated and committed with the plan's docs commit.
- The Pest browser plugin was not involved; no network calls to any model, Jev or SMS provider were made (the suite's `Http::preventStrayRequests()` and the fakes stayed in force).

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- 10-03 onward can read walks through `ActiveConfig::filesOfType('walk_test')` and `WalkFile::fromCompiled()`; a valid candidate now carries `compiled.index.walk_test`.
- Nothing blocks. The four sentences above are the only wording awaiting Ian's eye.

## Self-Check: PASSED

- Created files exist (all 8), confirmed with `[ -f ]`.
- Commits `cf160b8`, `fd91a47`, `6960bb4`, `536f9c1` are ancestors of HEAD (`git merge-base --is-ancestor`).
- `git rev-list --count f5df668..HEAD` before this docs commit is 4, matching `commits: 4`.

---
*Phase: 10-walk-tests-and-the-gate*
*Completed: 2026-11-22*
