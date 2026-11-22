---
phase: 10-walk-tests-and-the-gate
plan: 03
subsystem: icm-walks
tags: [walk_test, scratch-world, sqlite, replay, frozen-clock, tdd, pest]

requires:
  - phase: 10-walk-tests-and-the-gate
    provides: "10-02: WalkFile, WalkStep, WalkStepKind, minimalWalkFile() (a walk file read back as values)"
  - phase: 10-walk-tests-and-the-gate
    provides: "10-01: booking texts leave the reply cap, so a walk's reply count matches the live count"
provides:
  - "App\\Icm\\Walks\\ScratchWorld: an in-memory SQLite database named walk, a temporary records folder, a private FakeTextChannel, HTTP shut and a clock only the walk moves; open() swaps, close() restores in reverse and is safe twice; a failed open() restores what it swapped"
  - "WalkTape, ReplayFrontDeskAgent and ReplayClassifier: recorded model replies and Jev's raw scores per turn, refusing (FrontDeskUnavailable, ClassifierFailed) and noting which turn needed what when the recording runs out"
  - "WalkRunner::replay(): says and wait steps through the front desk's real path, closing the world in finally; WalkResult, WalkOutcome, WalkClock::startFor(), CaptureDispatchInbound"
  - "tests/Feature/Walks helpers (walkFileFrom, walkReply, walkScores, walkTape, liveRowCounts) and ReplayTest, ScratchWorldTest, WalkClockTest"
affects: [10-04 books step and checks, 10-05 the gate, 10-06 walks:record]

actuals:
  tokens: 16200
  tasks: 3
  commits: 6

tech-stack:
  added: []
  patterns:
    - "A scratch in-memory SQLite connection made the default for one walk, migrated with Artisan::call, purged on close"
    - "A restore stack: each swap registers its own undo the moment it is made, close() pops them last to first"
    - "Container swaps (instance/bind) remember the old binding and the old instance and put exactly those back"
    - "Break-on-purpose for every new test that is green on its first run, with sha256 before and after"

key-files:
  created:
    - app/Icm/Walks/ScratchWorld.php
    - app/Icm/Walks/WalkTape.php
    - app/Icm/Walks/WalkRunner.php
    - app/Icm/Walks/WalkResult.php
    - app/Icm/Walks/WalkOutcome.php
    - app/Icm/Walks/WalkClock.php
    - app/Icm/Walks/CaptureDispatchInbound.php
    - app/Ai/ReplayFrontDeskAgent.php
    - app/SendRule/ReplayClassifier.php
    - tests/Feature/Walks/WalkHelpers.php
    - tests/Feature/Walks/ReplayTest.php
    - tests/Feature/Walks/ScratchWorldTest.php
    - tests/Feature/Walks/WalkClockTest.php
  modified:
    - docs/sprints/sprint-1.1.md
    - .planning/WINDOWS.md

key-decisions:
  - "Isolation is a scratch in-memory SQLite database, not a rolled-back transaction (Claude's Discretion, 10-RESEARCH A1): a transaction on the live connection would read live appointments and burn sequences"
  - "ScratchWorld keeps a stack of restore closures, one registered per swap, so close() restores exactly what was swapped, in reverse, and a half-opened world can be closed"
  - "The inspect closure of WalkRunner::replay() is called with the open ScratchWorld, so a caller can read the world's fake channel and records folder"
  - "A walk with no named start and a shop that does not open within two weeks starts at the moment the run began"
  - "The tape notes the first exhaustion only; the model seam and the Jev seam both refuse on their own as well, and both are tested directly"

requirements-completed: [WALK-02]

coverage:
  - id: D1
    description: "A walk replays a customer turn through the front desk's real path in an empty world of its own, with the model and Jev replaced by the tape, and leaves the live app exactly as it found it"
    requirement: WALK-02
    verification:
      - kind: integration
        ref: "tests/Feature/Walks/ReplayTest.php#replays one customer turn in a world of its own (D-01); #leaves the live app exactly as it found it after one turn (D-01)"
        status: pass
    human_judgment: false
  - id: D2
    description: "A walk sends nothing real (Twilio configured), reads no live row, alerts no real owner, and restores every global even when the walk throws or opening fails part way"
    requirement: WALK-02
    verification:
      - kind: integration
        ref: "tests/Feature/Walks/ScratchWorldTest.php (10 cases)"
        status: pass
    human_judgment: false
  - id: D3
    description: "Replay returns Jev's raw scores and the bars are today's config('gate.classifier'); a missing model reply or Jev reading refuses and the walk needs a new recording"
    requirement: WALK-02
    verification:
      - kind: integration
        ref: "tests/Feature/Walks/ReplayTest.php (replays raw scores; applies the current bars; four D-27 cases)"
        status: pass
    human_judgment: false
  - id: D4
    description: "A walk runs on a frozen clock: default start 10:00 on the next opening's day (clamped to that day's hours), a named start as written, the clock moved only by waits, today.md redrawn when a wait enters a new day"
    requirement: WALK-02
    verification:
      - kind: integration
        ref: "tests/Feature/Walks/WalkClockTest.php (10 cases)"
        status: pass
    human_judgment: false
  - id: D5
    description: "The default 10:00 start, the clamp rule and the plain-words walk-through log line are the executor's reading of D-07"
    verification: []
    human_judgment: true
    rationale: "The log line in docs/sprints/sprint-1.1.md is teaching text for Ian; only Ian can say whether it explains the new concepts. The CI pgsql leg (live Postgres, walk in-memory SQLite) cannot be run here."

duration: ~15min
completed: 2026-11-22
status: complete
commits: 6
plan_head_before: cfcfccbb9e0f0dd4ef35f67366f9d4a165245671
plan_head_after: 13f95ee4ddc341b937f81eb2dbd45af34e938b97
---

# Phase 10 Plan 03: The walk's world of its own Summary

**One walk now runs the front desk's real path against a candidate in an empty in-memory SQLite world, with the model and Jev answering from a tape, on a clock only the walk moves, sending nothing real and restoring every global even when it throws.**

## Performance

- **Duration:** about 15 min
- **Started:** 2026-11-22T18:46:58Z
- **Completed:** 2026-11-22T19:03Z (SUMMARY written)
- **Tasks:** 3 (Task 1 a tracer, Tasks 2 and 3 auto; all `tdd="true"`)
- **Files:** 13 created, 2 modified (docs/sprints/sprint-1.1.md, .planning/WINDOWS.md)

## Accomplishments

- `ScratchWorld::open($compiled)` builds a database of its own (an SQLite `:memory:` connection named `walk`, migrated with the app's own migrations, made the default), a temporary records folder in place of `config('icm.path')`, the `fake` text driver with a private `FakeTextChannel`, `queue.default = sync`, an array cache store, HTTP stray-request prevention, a forgotten `ActiveConfig`, then inserts the candidate as the one Active `IcmVersion` and one fictional owner (`OWNER_PHONE` `+12025550199`). Singletons and scoped bindings were checked (docblock): only `ActiveConfig` holds folder config; `RoomRegistry` and `FakeGoogleListingSource` hold nothing the folder changes.
- `WalkRunner::replay()` runs each customer turn through `RecordInbound`, captures the held `ProcessInbound` job with `CaptureDispatchInbound` and runs it by hand, with `ReplayClassifier` and `ReplayFrontDeskAgent` bound only while the world is open. `close()` runs in `finally`.
- `WalkTape` hands out only the current turn's entries in order and notes the first thing it could not answer (`['turn' => n, 'needed' => 'model reply' | 'Jev reading']`); both seams refuse on their own, and the outcome is `NeedsRecording`.
- `WalkClock::startFor()`: a named start as written in the business zone, else 10:00 on the day of the next opening (clamped to that day's opening when 10:00 is outside its hours). A wait moves the clock only by its minutes; a wait into a new business-zone day calls `RecordsWriter::writeOwnerIntelligence()` once.
- One walk-through log line added to `docs/sprints/sprint-1.1.md`.

## Task Commits

1. **Task 1 (tracer): one customer turn replayed in a world of its own**
   - RED `d62dd5c` test(10-03): one turn replays in a world of its own (D-01, D-02)
   - GREEN `a824ea3` feat(10-03): replay a walk turn in a world of its own (D-01, D-02)
2. **Task 2: nothing real, no live row, no real owner alerted, every global restored**
   - RED `c3c239e` test(10-03): a walk leaves the live app as it found it
   - GREEN `e93bfd4` fix(10-03): a walk restores every global in finally (chokepoint rule)
3. **Task 3: frozen clock, current bars, failing closed**
   - RED `8748ca5` test(10-03): frozen clock, current bars and failing closed (D-02, D-07, D-27)
   - GREEN `13f95ee` feat(10-03): frozen clock with default start and waits; fail closed (D-07, D-27)

**Plan metadata:** the docs commit that holds this file.

Tracer gate (Task 1, no `gate` attribute, automated-only verify, `end-of-phase` mode): the verify command was re-run after the commits (71 passed), logged "Tracer verified end-to-end, expanding", and Task 2 started. No checkpoint.

## TDD Gate Compliance

RED then GREEN for each task, test commit before code commit each time; no REFACTOR commit was needed. `git log` shows `test(10-03)` before `feat(10-03)`/`fix(10-03)` for all three tasks. The machine classifier `gsd_run check tdd-red-evidence` was not run: Pest's console output is not one of its supported report formats and no JUnit record was captured. The RED assessment below is by reading each failing case's message (a gap, as in 10-02, not a pass of the classifier).

### RED evidence and semantic assessment

**Task 1 (`d62dd5c`): 3 of 3 failed, 0 passed.** All three failed with `Class "App\Icm\Walks\WalkTape" not found` (the helper's `walkTape()` reaches the missing class; `ReplayClassifier` and `WalkRunner` are missing too). Semantic assessment: the planned absence. One weakness found afterwards: the missing class hid a test bug (the contact was looked up by `phone`, but the column is `phone_e164`), seen only after the classes existed and fixed in the GREEN commit (deviation 1).

**Task 2 (`c3c239e`): 1 of 10 failed, 9 passed.** The failing case is `restores every global when opening fails part way`: `Failed asserting that two arrays are identical` (database `walk` instead of `sqlite`, the walk connection still configured and open, `icm.path` still the temporary folder, cache `walk` instead of `array`, a new FakeTextChannel). Semantic assessment: exactly the missing behavior (the world's open() did not undo its swaps when it failed). A side effect worth stating: that failure leaves the process in a swapped state, so the cases after it in the same run failed too (`cannot VACUUM from within a transaction`, `table "migrations" already exists`); run apart with `--filter='/^(?!.*opening fails)/'` the other 9 passed. That contamination is the very danger T-10-10 names. The 9 green cases each got a break-on-purpose (below). The fix commit made all 10 pass in one run.

**Task 3 (`8748ca5`): 8 of 15 new cases failed, 7 passed** (WalkClockTest's 10 cases and 5 new ReplayTest cases; the two seam-level cases were added after the first RED run and passed on their first run like the other ReplayTest cases). Failed (all WalkClockTest): the five dataset rows of `starts at 10:00 on the business day of the next opening` (the walk started at the moment the run began, e.g. expected `2026-12-01 10:00`, got `2026-12-01 07:00`; expected `2026-12-07 10:00`, got `2026-12-06 12:00`), `starts at the day's opening when 10:00 falls outside its hours` (expected `07:00`, got `05:00`), `moves the clock only by its waits` (expected `12:00`, got `10:00`) and `redraws records/today.md when a wait crosses into a new day` (no file written; the test asserts `not->toBeNull()` first). Passed on the first run (7, all broken on purpose below): `starts at a named start whatever the test clock says`, `puts the test clock back after a walk`, and the ReplayTest cases `applies the current bars`, both walk-level D-27 cases and the two seam-level D-27 cases. Semantic assessment: every failure is the planned absence (no default start, no wait step).

### Break-on-purpose: new tests that were green on their first run

Each test below passed on its first run, so its production file was broken on purpose, the test run and seen RED, the file restored from a backup in `/private/tmp/claude-501/scratch10-03/`, the sha256 compared (all matched) and `git diff --stat -- <file>` shown empty (all four files were already committed, so a clean diff proves a byte-for-byte restore). The broken state was never committed.

**Task 2 cases** (`tests/Feature/Walks/ScratchWorldTest.php`; sha256 of `app/Icm/Walks/ScratchWorld.php` before and after every break: `b94a860ebd79d93b9c044147a2ea28baabd7c368250dee739e0f3ee12c31fd02`; of `app/Icm/Walks/WalkRunner.php`: `93acd848232a3aaa225b95d1be418c57e1c8125fb97800750a108932f2a4e87a`):

| Test | File broken | How | RED line |
|---|---|---|---|
| sends nothing real with Twilio configured | ScratchWorld.php | removed `config(['channels.text.driver' => 'fake'])` | `Failed asserting that two strings are identical. -'fake' +'twilio'` |
| shuts HTTP for the whole walk and puts it back | ScratchWorld.php | skipped `shutHttp()` | `Failed asserting that false is true.` |
| never reads a live row | ScratchWorld.php | removed `DB::setDefaultConnection('walk')` | `Expecting [...] not to contain 'Live Lucy'.` |
| alerts no real owner | ScratchWorld.php | removed the `instance(FakeTextChannel::class, ...)` swap | `Failed asserting that an array contains '+12025550199'.` |
| restores every global after a walk that throws | WalkRunner.php | dropped the `finally`, closing only on the normal path | `Failed asserting that two arrays are identical. -'database' => 'sqlite' +'database' => 'walk'` (and the walk connection, icm path, cache, channel) |
| restores every global after a walk that throws | ScratchWorld.php | removed the clock restore | `-'testNow' => '2026-11-20T08:15:00+00:00' +'testNow' => '2026-12-01T10:00:00-06:00'` |
| closes safely twice and the second close changes nothing | ScratchWorld.php | removed the `closed` guard and the pop (second close replays the restores) | `Failed asserting that two strings are identical. -'database' +'sync'` |
| reads the live version after a walk | ScratchWorld.php | removed the close-time `forgetInstance(ActiveConfig)` | `Failed asserting that 1 is identical to 3.` |
| reads the live version after a walk | ScratchWorld.php | removed the open-time `forgetInstance(ActiveConfig)` | `-'walk' +'ffe69812...'` (the live fingerprint) |
| starts every walk from an empty world | ScratchWorld.php | removed `DB::purge('walk')` | `UniqueConstraintViolationException` (the second walk's owner insert) |
| writes no card into the owner's folder | ScratchWorld.php | removed the `icm.path` swap | `Expecting '/var/folders/.../walk-world-...' not to be '/var/folders/.../icm-...'` |

**Task 3 cases** (sha256 before and after each break, all matching; `ScratchWorld.php` `1ebd1c3e8163417e2332d7549e3a42eb7cdccf0d8b6016f460aa5a44ea8043bf`, `ReplayClassifier.php` `f18e9368248203d89581521730e238f041345d6577431ba5e4d7dcc4c9e81709`, `ReplayFrontDeskAgent.php` `6309b944d9b541ad6c750853834d928e7ab53c2dcc08dfbe0f98f5064f7195de`, `WalkRunner.php` `93acd848232a3aaa225b95d1be418c57e1c8125fb97800750a108932f2a4e87a`, `WalkTape.php` `283a754e23d9bb172fba632d7d8119ab1c10b4dfb2a0dac431f9953b07403e59`):

| Test | File broken | How | RED line |
|---|---|---|---|
| applies the current bars to recorded scores (D-02) | ReplayClassifier.php | baked the shipped 0.15 bar into the replayed scores (scores at or under 0.15 read 0.0) | `-'tightened bar' => 0 +'tightened bar' => 1` |
| refuses ... when a Jev reading is missing (D-27, walk level) | ReplayClassifier.php | returned a guessed clean result instead of throwing | `Failed asserting that 1 is identical to 0.` (an agent reply was sent on a guessed reading) |
| refuses to guess a Jev reading ... (seam) | ReplayClassifier.php | same guess | `Exception [App\SendRule\ClassifierFailed] not thrown.` |
| refuses to guess a model reply ... (seam) | ReplayFrontDeskAgent.php | reused the last turn's reply when the tape was empty | `Exception [App\Ai\FrontDeskUnavailable] not thrown.` |
| refuses ... when a model reply is missing (D-27, walk level) | WalkTape.php | stopped noting the exhaustion | `Failed asserting that two variables reference the same object. -NeedsRecording +Passed` |
| starts at a named start whatever the test clock says | WalkRunner.php | ignored the named start | `-'2026-12-02 14:30' +'2026-11-20 03:00'` |
| puts the test clock back after a walk | ScratchWorld.php | removed the clock restore | `-2026-11-20T03:00:00.000000-0600 +2026-12-01T10:00:00.000000-0600` |

**An honest weakness the break found.** The first version of the walk-level case `refuses ... when a model reply is missing (D-27)` stayed GREEN when the model seam was broken to reuse turn 1's reply: the send rule held the duplicate on its own, so nothing visible changed (the outcome still came from the tape's note). A test that cannot see the guess is not a test of the refusal. I added the two seam-level cases (`ReplayFrontDeskAgent` and `ReplayClassifier` called directly) that do go RED under the guess, and added an "agent replies sent = 0" assertion to the walk-level Jev case. The walk-level model case now has its own break (exhaustion not noted).

## Verification (acceptance criteria and plan commands)

- Task 1: all five greps pass (`class ReplayFrontDeskAgent extends RunFrontDeskAgent`, `class ReplayClassifier implements Classifier`, `DB::setDefaultConnection('walk')`, `'--database' => 'walk'`, `PERSONA_PHONE = '+12025550142'`; no `Facades\File;` under `app/Icm/Walks/`). Automated: 71 passed.
- Task 2: `grep -c "^it("` prints 10 (at least 7); `finally` is in `WalkRunner.php`; `Carbon::getTestNow()` is in `ScratchWorld.php`. Automated: 13 passed.
- Task 3: the `startFor` signature, `writeOwnerIntelligence()` in `WalkRunner.php`, `'needed' => 'model reply'` and `'Jev reading'` in `WalkTape.php` and `(plan 10-03)` in the sprint file all match (`grep -F`). Automated: 73 passed (Walks + ArchitectureTest + AppointmentWritesTest + BookingTextKindTest).
- Plan `<verification>`: `php artisan test --compact tests/Feature/Walks tests/Unit` gave 533 passed (1157 assertions). `tests/Unit/BookingTextKindTest.php` and `tests/Unit/ArchitectureTest.php` pass unchanged. `vendor/bin/pint --test` passes.
- Rule 4 check: `git diff 1543c01~1 -- tests/ | grep -E '^-[^-]'` prints nothing (0 lines). No existing test was edited; all four test files are new.
- The full suite was not run by me (the orchestrator runs it after the wave).
- **Not run, by instruction (never a pause):** the plan's `<human-check>`: after Ian pushes the phase branch, the CI pgsql leg is green for `tests/Feature/Walks` (live Postgres, walk in-memory SQLite). Recorded as `unrun-verify` entry 90 in `.planning/WINDOWS.md`.

## Decisions Made

- Scratch in-memory SQLite database rather than a rolled-back transaction (reasons in `ScratchWorld`'s docblock).
- A restore stack inside `ScratchWorld` (one undo per swap, popped in reverse) so a failed `open()` closes cleanly and `close()` is idempotent.
- `inspect` is called with the open `ScratchWorld`.
- A shop that does not open within 13 days gives a walk no next opening; it starts at the moment the run began.
- `ReplayClassifier` treats a words check as a Jev call for exhaustion (`needed: 'Jev reading'`), as the plan's single vocabulary says.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug, in my own test] The contact was looked up by `phone`, not `phone_e164`**
- **Found during:** Task 1 GREEN (the RED run failed earlier, on the missing classes, so it was hidden)
- **Fix:** one word in `tests/Feature/Walks/ReplayTest.php`, committed with the GREEN commit `a824ea3`.

**2. [Rule 3 - Blocking] `make:enum` wrote `WalkOutcome` to `app/Enums/Icm/Walks/`**
- **Fix:** moved to `app/Icm/Walks/` (as in 10-02), namespace rewritten, empty folders removed.

**3. [Rule 2 - Missing test coverage, added] Cases beyond the plan's list**
- ScratchWorldTest: `shuts HTTP for the whole walk and puts it back`, `restores every global when opening fails part way`, `closes safely twice and the second close changes nothing`; the empty-world case also counts versions and users, because contacts alone could not tell a reused world from a fresh one.
- ReplayTest: the two seam-level D-27 cases and an "agent replies sent = 0" assertion (see the honest weakness above).
- The partial-open case is the one Task 2 RED that failed. The plan's Task 2 behavior list did not name it, but its GREEN text and the edge truth ("survives a failure part way through open()") do.

**4. [Interpretation] The D-27 model-reply case holds turn 2's readings but no model reply**
- The plan says "a two-turn walk whose tape holds turn 1 only" and expects `['turn' => 2, 'needed' => 'model reply']`. Reading comes before the model in `RunFrontDesk`, and the tape notes the first exhaustion, so a turn 2 with nothing at all would say `'Jev reading'`. The case therefore gives turn 2 its readings and words checks and leaves out only the model reply. The behavior (first exhaustion noted; both seams refuse) is the plan's.

**5. [Verification note] The `<automated>` for Task 3 and the acceptance greps use `grep -F`** for the lines holding `$` (same BSD grep note as 10-02).

---

**Total deviations:** 5 (1 Rule 1 in my own test, 1 Rule 3, 1 added coverage, 1 interpretation, 1 note)
**Impact on plan:** None on scope or behavior. No existing file in `app/` or `tests/` was edited; every app class is new.

## Known Stubs

- `WalkRunner` plays a `books` step as a no-op (`WalkRunner.php:68`); plan 10-04 adds the booking step and the checks. `WalkResult`'s `failure`, `turns` and `fingerprint` stay empty until 10-04 fills them. Both are the plan's stated intent, not a defect; recorded as WINDOWS.md entry 91 (`stub`) so the ship gate sees them if 10-04 does not land.

## Threat Flags

None. A runtime database connection, a temporary folder and a frozen clock are the plan's own surface (T-10-06 to T-10-10, all mitigated and tested: Twilio configured and a live owner present, a live appointment invisible, live row counts unchanged, the suite's Classifier and channel objects back after a normal and a throwing walk, every global compared).

## Issues Encountered

- A failing Task 2 case leaves the process swapped and cascades into later cases in the same run (see RED evidence). It is a test-ordering hazard only while the code is broken.
- No network call to any model, Jev or SMS provider was made. The suite's fakes and `Http::preventStrayRequests()` stayed in force; the tests that turn prevention off do it with `Http::fake()` and assert nothing was sent.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- 10-04 adds the books arm and the checks to `WalkRunner::replay()`; `WalkResult` already has the fields it will fill. 10-05 (the gate) turns a missing `pdo_sqlite` into a plain refusal, as the plan's A3 says; pdo_sqlite is present here.
- Nothing blocks.

## Self-Check: PASSED

- All 13 created files exist (`[ -f ]`).
- Commits `d62dd5c`, `a824ea3`, `c3c239e`, `e93bfd4`, `8748ca5`, `13f95ee` are ancestors of HEAD (`git merge-base --is-ancestor`); `git rev-list --count cfcfccb..HEAD` is 6, matching `commits: 6`.
- `php artisan test --compact tests/Feature/Walks tests/Unit` passed (533) after the last commit.

---
*Phase: 10-walk-tests-and-the-gate*
*Completed: 2026-11-22*
