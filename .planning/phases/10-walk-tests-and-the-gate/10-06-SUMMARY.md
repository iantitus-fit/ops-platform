---
phase: 10-walk-tests-and-the-gate
plan: 06
subsystem: icm-walks
tags: [walks_record, recording, decorators, booking_wording, export, tdd, pest]

requires:
  - phase: 10-walk-tests-and-the-gate
    provides: "10-05: walk_recordings table, WalkRecording (AS_BUILT), the gate that replays as_built rows, seedRecording() and layWalk() helpers"
  - phase: 10-walk-tests-and-the-gate
    provides: "10-03/10-04: ScratchWorld::open(allowHttp), WalkRunner, WalkTape, WalkFingerprint"
provides:
  - "php artisan walks:record {walk?*} {--variant=} {--export=}: plays each walk once against the folder as it is, keeps the model's replies and Jev's raw scores, sends nothing, stores one row per walk"
  - "RecordingFrontDeskAgent (extends RunFrontDeskAgent) and RecordingClassifier (implements Classifier): wrap the real ones and keep each reply and raw score on the tape"
  - "WalkRunner::record(WalkFile, array, ?Closure, ?BookingSectionWording): WalkResult, sharing one private play() loop with replay()"
  - "WalkTape recording side: recordAgentReply, recordReading, recordWordsCheck, noteRecordingFailure, recordingFailures, and recorded_with in the payload"
  - "FolderSync::candidateForWalks(): WalkCandidate (compiled, errors, live), the folder as a sync reads it, never made live"
  - "BookingSectionWording::fromArray(): a measured Booking wording supplied as data (D-18); FrontDeskPrompt reads its two texts through bookingVisitBookedText() and bookingOnlineText()"
  - "FakeClassifier::queueReadings() and queueAnswers()"
affects: [10-07 example walks, 10-10 docs/SETUP.md live run, 10-11 page and notification proof, Ian's live recording run]

actuals:
  tokens: 14600
  tasks: 3
  commits: 6

tech-stack:
  added: []
  patterns:
    - "A recording decorator wraps what the container holds and is bound by the walk world only; the world forgets it on close"
    - "A wording supplied as data is bound by ScratchWorld::instance(), read at the use site with app()->bound(), and gone with the world"
    - "Console lines that hold owner or provider text are written raw with HiddenCharacters::marked"

key-files:
  created:
    - app/Console/Commands/WalksRecord.php
    - app/Ai/RecordingFrontDeskAgent.php
    - app/SendRule/RecordingClassifier.php
    - app/Ai/BookingSectionWording.php
    - app/Icm/Walks/WalkCandidate.php
    - tests/Feature/Walks/WalksRecordCommandTest.php
    - tests/Fixtures/booking-wording/test-variant.json
  modified:
    - app/Icm/Walks/WalkRunner.php
    - app/Icm/Walks/WalkTape.php
    - app/Icm/FolderSync.php
    - app/Ai/FrontDeskPrompt.php
    - app/SendRule/FakeClassifier.php
    - map/objects/folder/agent-file-and-version.md
    - docs/sprints/sprint-1.1.md
    - docs/BLUEPRINT.md
    - .planning/WINDOWS.md

key-decisions:
  - "walks:record keeps a recording even when its checks did not pass under today's bars; the sync decides (D-17). It stores nothing for a walk where the model or Jev did not answer, and ends with exit 1 after the rest are recorded."
  - "The variant parameter of WalkRunner::record() arrives in Task 2, with BookingSectionWording, so no commit refers to a class that does not exist yet"
  - "Every console line goes through one raw writer with hidden characters marked; no line uses the console's error styling"
  - "An exception that is not a provider failure (for example the world cannot open) is not caught, so the command stops with Laravel's own error and nothing is stored for that walk"

patterns-established:
  - "A test that scripts a model with FrontDeskAgent::fake() twice in one test reads all the prompts of both runs together: the SDK fake keeps its recorded prompts across fake() calls"

requirements-completed: [WALK-02]

coverage:
  - id: D1
    description: "walks:record plays each walk once against the folder as it is with the model and Jev the container provides, keeps replies and raw scores (no verdict) in one recording per walk, and the next sync replays that recording and goes live"
    requirement: WALK-02
    verification:
      - kind: integration
        ref: "tests/Feature/Walks/WalksRecordCommandTest.php#records a walk and the next sync replays it (D-03, D-06)"
        status: pass
      - kind: unit
        ref: "tests/Feature/Walks/WalksRecordCommandTest.php#hands out queued Jev scores in order before the fixed answer (D-03)"
        status: pass
    human_judgment: false
  - id: D2
    description: "The recorder sends nothing and writes nothing live: no text, no live row, no file in the business folder, no version, clock and Classifier binding as before; a recording is replaced in place; every walk in path order, only the named ones, nothing live yet"
    requirement: WALK-02
    verification:
      - kind: integration
        ref: "tests/Feature/Walks/WalksRecordCommandTest.php#sends nothing and writes nothing live (chokepoint rule); records a walk again in place (D-06); records every walk in path order with no argument, with nothing live yet (D-03, D-26); records only the walks it is given (D-03)"
        status: pass
    human_judgment: false
  - id: D3
    description: "A folder that fails its checks, a walk that is not in it, and a model or Jev that did not answer each stop in plain words with exit 1 and store nothing for that walk; a recording whose checks did not pass is kept and says so"
    requirement: WALK-02
    verification:
      - kind: integration
        ref: "tests/Feature/Walks/WalksRecordCommandTest.php#refuses a folder that fails its checks; refuses a walk that is not in the folder; keeps no recording when Jev did not answer; keeps no recording when the model did not answer, and records the walks after it; keeps a recording whose checks did not pass and says so"
        status: pass
    human_judgment: false
  - id: D4
    description: "walks:record --variant records the Booking section's two texts from a JSON wording file under the wording's name in the walk world only; the gate never reads a variant row; bad wording files are refused in plain words; the shipped constants and config/gate.php are unchanged"
    requirement: WALK-02
    verification:
      - kind: integration
        ref: "tests/Feature/Walks/WalksRecordCommandTest.php#records a Booking wording variant as data (D-18); never lets the gate read a variant recording (D-18); refuses a wording file that cannot be used and records nothing (D-18); refuses a wording file that is not there (D-18)"
        status: pass
      - kind: other
        ref: "git diff --quiet main -- config/gate.php; git diff main -- app/Ai/FrontDeskPrompt.php holds no added or removed BOOKING_ constant line"
        status: pass
    human_judgment: false
  - id: D5
    description: "walks:record --export writes each kept recording as one JSON file in the form the committed fixtures use"
    requirement: WALK-02
    verification:
      - kind: integration
        ref: "tests/Feature/Walks/WalksRecordCommandTest.php#exports each recording as JSON (Ian's D-02 ruling); writes no export file for a walk it did not record (D-02)"
        status: pass
    human_judgment: false
  - id: D6
    description: "Ian's first live run: walks:record, --variant and --export against the real model and Jev"
    verification: []
    human_judgment: true
    rationale: "Every test uses the suite's fakes (D-17 split); the real providers answer only in Ian's run. Recorded as an unrun-verify entry (id 93) in .planning/WINDOWS.md, never a pause."

duration: 20min
completed: 2026-11-26
status: complete
commits: 6
plan_head_before: 29d83f15541037ae01c2b9c4317d5e681aca9a43
plan_head_after: 34d49bd66d16ea68bc05b9f4e44d2bbe4420ba1b
---

# Phase 10 Plan 06: walks:record Summary

**`walks:record` plays each walk once against the folder as it is with the real model and Jev wrapped by recording decorators, keeps the replies and raw scores in one `walk_recordings` row per walk (replaced in place, never a version, never a live write), can record the Booking section from a JSON wording file under its own name, and can export each recording as a fixture JSON.**

## Performance

- **Duration:** about 20 min of clock time (the start time was not captured; the first commit is 19:44Z and the SUMMARY is written about 20:00Z)
- **Tasks:** 3 (Task 1 a tracer, Task 2 auto tdd, Task 3 docs)
- **Files:** 7 created, 9 modified

## Accomplishments

- `RecordingFrontDeskAgent` and `RecordingClassifier` wrap the container's real agent and Classifier, keep `AgentReply::toArray()` and `ClassifierResult::toArray()` on the `WalkTape` (readings by an empty draft, words checks otherwise) and note a provider that did not answer before rethrowing the same exception.
- `WalkRunner::record()` opens a world with HTTP left open, resolves the real Classifier and agent first, then binds the recorders; both modes share one private `play()` loop (`replay()` behaves as before; every Walks and Icm test still passes).
- `FolderSync::candidateForWalks()` returns a `WalkCandidate` (compiled copy or the validation lines, plus the live version) with the same load, validation, room drop checks and compile as a sync; it writes no version and runs no walk.
- `walks:record` stores `WalkRecording::updateOrCreate` on (walk path, script hash, variant), prints one line per walk, refuses a folder that fails its checks and a walk that is not in the folder, and stores nothing for a walk the model or Jev did not answer.
- `--variant=<file>` reads a JSON wording through `BookingSectionWording::fromArray()`, binds it in the walk world only (FrontDeskPrompt reads it at lines 296 and 305 through two private methods), and stores the row under the wording's name; the gate still reads only `as_built`.
- `--export=<folder>` writes `<walk file name>.json` (with `.<variant>` for a variant) holding walk_path, script_hash, variant, fingerprint, recorded_at, source `live` and payload.
- `FakeClassifier::queueReadings()` / `queueAnswers()` give each call its own scores.

## Task Commits

1. **Task 1 (tracer): walks:record records one walk and the next sync replays it**
   - RED `d04c23d` test(10-06): walks:record records a walk the sync then replays (D-03)
   - GREEN `94c77e7` feat(10-06): walks:record keeps the model's replies and Jev's raw scores (D-03, D-06)
2. **Task 2: the recorder sends nothing and writes nothing live; variants and export**
   - RED `5d46e40` test(10-06): the recorder writes nothing live and records wording variants as data (D-18)
   - GREEN `e0febf7` feat(10-06): record Booking wording variants as data and export recordings (D-18)
3. **Task 3: docs** `27f1980` docs(10-06): walks:record in the walk-through log and BLUEPRINT
4. **Hand check ledger** `34d49bd` docs(10-06): record the live recording hand check in the windows ledger

**Plan metadata:** the docs commit that holds this file (SUMMARY, STATE, ROADMAP, REQUIREMENTS).

Tracer gate (Task 1, no `gate` attribute, automated-only verify, `end-of-phase`): the verify command was re-run after the feat commit (38 passed) and logged "Tracer verified end-to-end, expanding". No checkpoint.

## TDD Gate Compliance

Task 1: RED `d04c23d` precedes GREEN `94c77e7`. Task 2: RED `5d46e40` precedes GREEN `e0febf7`. No refactor commit. `gsd_run check tdd-red-evidence` was not run: Pest's console output is not one of its supported formats (the same gap as 10-02 to 10-05).

### RED evidence and semantic assessment

**Task 1 (`d04c23d`):** `{"result":"failed","tests":2,"passed":0,"errors":2}`, both `Call to undefined method App\SendRule\FakeClassifier::queueReadings()`. Semantic assessment: the planned absence (no queue, no command). Because a missing method hides test bugs, every case was also mutation-checked after GREEN (below). GREEN was `{"result":"passed","tests":2,"passed":2,"assertions":34}`.

**Task 2 (`5d46e40`):** `{"result":"failed","tests":23,"passed":11,"errors":12}`. The 12 errors are the cases that use `--variant` (10) and `--export` (2): `The "--variant" option does not exist.` and `The "--export" option does not exist.` Semantic assessment: the planned absence of the two options. The other 11 (2 tracer cases and 9 new) passed on their first run: see below. GREEN was `{"result":"passed","tests":23,"passed":23,"assertions":152}`.

### Passed on first run, break-on-purpose

For each mutant the production line was changed, the case seen red, and the file restored. Before and after every round the sha256 of each mutated file was identical (the round printed `True` for each). The files were not yet committed when a round ran; the commit's own diff holds only the intended changes, and `git status --short app tests` is empty afterwards.

Task 1 (11 mutants, all killed; sha256 of the files before and after: RecordingFrontDeskAgent `12a4979d...94d3`, WalksRecord `f75eeff9...cf85`, WalkRunner `5ed5ace1...39ab`, WalkTape `e2e360c3...a227`, FakeClassifier `c40f1899...4877`, RecordingClassifier `fab2e38e...0dbc`):

| Mutant | Red line |
|---|---|
| words check recorded as a reading (`RecordingClassifier`) | `Failed asserting that actual size 2 matches expected size 1.` |
| readings not recorded | `Failed asserting that actual size 0 matches expected size 1.` |
| agent reply not kept (`RecordingFrontDeskAgent`) | `Failed asserting that two arrays are identical.` (the turn's agent list) |
| queue peeks instead of shifting (`FakeClassifier`) | `Failed asserting that two arrays are identical.` ('scheduling' for 'faq') |
| words queue ignored | `Failed asserting that 0.95 is identical to 0.93.` |
| `recorded_with` dropped (`WalkTape`) | `Undefined array key "recorded_with"` |
| row stored under another variant (command) | `Failed asserting that two strings are identical.` ('other' for 'as_built') |
| payload replaced by an empty tape | `Undefined array key 1` |
| wrong fingerprint stored | `Failed asserting that two strings are identical.` |
| classifier left unwrapped in `record()` | `Failed asserting that actual size 0 matches expected size 1.` |
| words check stored without the draft | `Expecting '' not to be ''.` |

Task 2 (30 mutants, 28 killed, 2 equivalent; sha256 after the round: BookingSectionWording `a4acda01...ff7`, FrontDeskPrompt `d728ab2e...ff61`, RecordingFrontDeskAgent `12a4979d...94d3`, WalksRecord `f5e50039...9b16`, FolderSync `2f284415...8d9d88`, WalkRunner `0340f537...4bdf`, RecordingClassifier `fab2e38e...0dbc`, each equal to its value before):

| Case | Mutant | Red line |
|---|---|---|
| sends nothing, writes nothing live | the recording world is never closed (`WalkRunner::record`) | `Expected status code 0 but received 1.` (the app is not put back) |
| same | `candidateForWalks()` runs a real sync | `Failed asserting that 2 is identical to 1.` (a version was minted); 3 cases red |
| records again in place | `updateOrCreate` keyed on `recorded_at` too | `UNIQUE constraint failed: walk_recordings.walk_path, ...` |
| same | payload left out of the update; `recorded_at` left out | `NOT NULL constraint failed: walk_recordings.payload` and `...recorded_at` (10 cases red each) |
| path order, no argument | walks in reverse order | `Failed asserting that two arrays are identical.` (hello and price swapped) |
| only the walks it is given | named walks ignored | `Failed asserting that 1 is identical to 0.` |
| checks did not pass | outcome always read as passed | `Failed asserting that 'Recorded tests/price.md: ... Its checks passed.' starts with ...` |
| refuses a folder that fails | closing sentence dropped | `Failed asserting that 1 is greater than 1.` |
| refuses a walk not in the folder | the check removed | `Failed asserting that two arrays are identical.` |
| Jev did not answer | failure not noted; failed walk stored anyway; exit stays success | `Failed asserting that 0 is identical to 1.` (3 cases red for the last two) |
| model did not answer | failure not noted | `Failed asserting that 0 is identical to 1.` |
| variant as data | variant not bound in the world; online text read from the constant | `Failed asserting that false is true.` |
| same | fingerprint reads the constants | `Expecting '93c9fa15...' not to be '93c9fa15...'.` |
| same | variant row stored as built | `Failed asserting that two arrays are identical.` (`as_built` only) |
| bad wording file | `{services}` twice allowed; as_built allowed; name pattern open; visit text and online text unchecked | one data set red each (`0 is identical to 1`, wrong message, `Argument #3 ($visitBooked) must be of type string, null given`) |
| missing and non-JSON wording file | existence check removed; JSON error swallowed | `File does not exist at path ...` and the expected JSON message missing |
| export | `source` dropped; variant suffix dropped; a file written for a walk not kept | key list differs; `booking.test-variant.json` missing; a file was written |

Two mutants survived, both equivalent: removing `rtrim(..., '.')` on a failed walk's words (no check's words end in a full stop today, so it is a defence only; the case still checks the line ends in one full stop and not two) and dropping `$candidate->errors !== []` from the refusal test (`candidateForWalks()` returns a null copy exactly when it returns errors, so the other half still refuses).

## Wording

Console lines (Ian's, not the owner's; the plan's words exactly):

- `Recorded <path>: <n> model replies, <m> Jev readings, <k> Jev reply checks. Its checks passed.`
- `Recorded <path>: <n> model replies, <m> Jev readings, <k> Jev reply checks. Its checks did not pass: <the walk's failure words>.`
- Each count is singular for exactly one (`1 model reply, 1 Jev reading, 1 Jev reply check`) and plural otherwise, 0 included (Ian, 2026-11-26; superseded the single `Jev readings` count, commits `84c1631` and `4e92265`).
- `<path>, turn <n>: <the model|Jev> did not answer (<message>). Nothing was recorded for this walk.`
- `The folder does not pass its checks, so nothing was recorded.`
- `<path> is not a walk test in the folder.`

Where the plan delegated wording (plain words, no exclamation point, no em dash), the final text is:

- `booking_online must hold {services} exactly once, where the list of services goes.`
- `booking_online must be text.`
- `booking_visit_booked must be text.`
- `The wording name must use only lowercase letters, digits and dashes, such as shorter-text.`
- `The wording name cannot be as_built, because the sync replays that recording. Use another name.`
- `The wording file <given path> was not found.`
- `The wording file could not be read as JSON.`

Every line passes through `HiddenCharacters::marked` and is written raw, so an owner file name, a check's words or a provider's error text is shown as written.

## Cited lines and check_map

Cards that cite changed files: `map/objects/folder/agent-file-and-version.md` only (grep of `map/` for `FolderSync.php` and `FrontDeskPrompt.php` and the rest of the cited files).

- **FolderSync.php:** the plan said nothing in it moves, but the `use App\Icm\Walks\WalkCandidate;` import line shifted the two cited lines by one. The card cites `:313` (agent arm) and `:311` (offer arm) from commit `94c77e7`, the same commit as the code. After the change `sed -n 311p` prints the `'offer' =>` arm and `sed -n 313p` prints the `'agent' =>` arm.
- **FrontDeskPrompt.php:** only lines 296 and 305 changed in place and two private methods were added after the last method (`bookingVisitBookedText` at 501, `bookingOnlineText` at 510); no cited line moved (`19`, `37-72`, `49`, `71`, `135-169`, `208`, `223`, `261-283`, `336`, `478`). The card's Prompt bullet gained one sentence citing `:296`, `:305`, `:501` and `:510`, in commit `e0febf7`, and `git log -1 --format=%H` for the code and the card are both `e0febf7da8066500f88f29d2aeb506d33b311268`.
- `RunFrontDeskAgent.php` was not edited.
- `python3 ~/Projects/cartographer/tools/check_map.py ~/Projects/ops-platform --extra-root examples/example-hvac` printed `RESULT: PASS` after Task 1's feat commit, after Task 2's feat commit and again at the end.

## Existing tests

No existing line in any test file changed or was removed. `tests/Feature/Walks/WalkHelpers.php` was not edited. The re-runs named by the plan passed unedited: `tests/Feature/Walks`, `tests/Feature/Icm/WalkGateTest.php`, `tests/Unit/ArchitectureTest.php`, `tests/Feature/FrontDesk`, `tests/Feature/Booking` (1369 tests together with Walks and WalkGateTest).

## Verification

- Task 1 verify (`WalksRecordCommandTest`, `WalkGateTest`, `ArchitectureTest`): 38 passed.
- Task 2 verify (`tests/Feature/Walks`, `WalkGateTest`, `tests/Feature/FrontDesk`, `tests/Feature/Booking`, then `check_map.py`): 1369 passed and `RESULT: PASS`.
- Task 3 verify: the two greps print `VERIFY-OK`.
- Acceptance checks: `sed -n 296p` and `305p` of `FrontDeskPrompt.php` hold the two method calls; `git diff main -- app/Ai/FrontDeskPrompt.php` has no added or removed `public const string BOOKING_` line; `git diff --quiet main -- config/gate.php` exits 0; the `fromArray` and `candidateForWalks` signatures match (checked with `command grep`, because this shell's `grep` is a wrapper whose `$` handling differs from the plan's pattern).
- `vendor/bin/pint --dirty --format agent` passed before each code commit.
- **Whole suite, `php artisan test --compact`:** `{"tool":"pest","result":"passed","tests":3614,"passed":3614,"assertions":27102,"duration_ms":308886,"warnings":1,"warning_details":[]}`. The one warning carries no detail; I did not chase it (the same as 10-05).
- No test and no command of mine reached the live model, Jev or Twilio. `walks:record` ran only inside the Pest suite (`$this->artisan(...)` and `Artisan::call(...)`), always with `FrontDeskAgent::fake()` and `FakeClassifier`, and `Http::preventStrayRequests()` stayed on.

## Decisions Made

See `key-decisions`. Superseded (Ian, 2026-11-26): the executor first counted every Jev result as `<m> Jev readings` (a one-turn price walk printed `2`). Ian ruled that the line names the kinds apart, singular and plural right, with a count of 0 still printed: `Recorded tests/price.md: 1 model reply, 1 Jev reading, 1 Jev reply check. Its checks passed.` Test commit `84c1631` (only the expected strings of this plan's own cases that print the line changed, plus two new cases for the plural and the 0 forms, both red first), fix commit `4e92265`.

## Deviations from Plan

**1. [Plan conflict] The import line moved FolderSync's two cited lines**
- **Found during:** Task 1. The plan said `FolderSync.php` only gains a method at the end, so no cited line moves, and puts the map edit in Task 2. The new return type needs an import line near the top.
- **Fix:** kept the import (the plan's acceptance grep needs the short type name `WalkCandidate`; a `Walks\WalkCandidate` name would also have worked at runtime but fails that grep) and updated the card's `:312` and `:310` to `:313` and `:311` in the same commit as the code, as the run rule says. The card's other change stays in Task 2.
- **Commit:** `94c77e7`.

**2. [Order] `WalkRunner::record()` gets its `$variant` parameter in Task 2**
- The plan lists the signature with the variant in Task 1 but uses it in Task 2. `BookingSectionWording` is a Task 2 file, so Task 1's commit would have named a class that does not exist. Task 1's `record()` has three parameters; Task 2's adds the fourth. The final signature is the plan's.

**3. [Interpretation] Two corrections to my own test file in the Task 2 GREEN commit**
- The variant case read the model's instructions with `FrontDeskAgent::assertPrompted`, but the SDK fake keeps the recorded prompts of every `fake()` call in the test, so the first draft read the as-built prompt twice. It now reads all six instructions together (three as built, then three with the wording). A `not->toEndWith('..')` was added to the checks-did-not-pass case. Both are in `WalksRecordCommandTest.php`, a file this plan created; no pre-existing test line changed.

**4. [Interpretation] Edge choices where the plan was silent** (made without asking; Ian kept all of them, 2026-11-26)
- An exception from a world that cannot open is not caught; it stops the command with Laravel's own error. A named path is matched exactly as written (`tests/price.md`). Named paths that are not walks are all refused before any walk runs. `--variant` is read before the folder is looked at, so a bad wording file stops the run before anything is played. A relative wording path is tried as given, then from the project root. Hidden characters in any line are marked, so a provider's multi-line error is one line.

**Total deviations:** 1 plan conflict (resolved by the run's own map rule), 1 order difference, 2 interpretations. **Impact on plan:** none on scope; the shipped constants, `config/gate.php` and every earlier test are untouched.

## Issues Encountered

- `Artisan::output()` returned the output of the nested `migrate` the walk world runs, not of `walks:record`; the test helper passes its own `BufferedOutput`. Real runs are not affected (the nested call has its own buffer).
- The first Booking-variant draft of the test saw the as-built prompt (Deviation 3).

## Known Stubs

None.

## Threat Flags

None. The only live write is the recording row (T-10-20: counts, files, versions, clock and bindings asserted unchanged; mutants for an unclosed world and a real sync killed). T-10-21: nothing stores a key; tests never reach HTTP. T-10-22: the wording is bound by `ScratchWorld::instance()` and gone on close (asserted), the constants are unchanged. T-10-23: a variant row alone leaves the walk unrecorded at sync time (asserted). T-10-24: no row for a walk where the model or Jev did not answer (asserted, with mutants).

## Hand checks for end-of-phase UAT

- Ian's first live `walks:record` (and a live `--variant` and `--export` run) against the real model and Jev. Not run here by Ian's rule; recorded as an `unrun-verify` entry (id 93) in `.planning/WINDOWS.md` and commit `34d49bd`. The 10-10 documentation carries the steps.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Ian can record the example walks (10-07 and 10-10 write the steps), compare Booking wordings with `--variant`, and refresh the committed fixtures with `--export`.
- 10-11 prints the sync's stale lines in `icm:sync`; nothing here touches its files.
- Nothing blocks.

## Self-Check: PASSED

- All seven created files exist (`[ -f ]`): `WalksRecord.php`, `RecordingFrontDeskAgent.php`, `RecordingClassifier.php`, `BookingSectionWording.php`, `WalkCandidate.php`, `WalksRecordCommandTest.php`, `test-variant.json`.
- Commits `d04c23d`, `94c77e7`, `5d46e40`, `e0febf7`, `27f1980`, `34d49bd` are ancestors of HEAD; `git rev-list --count 29d83f15..HEAD` is 6, matching `commits: 6`.
- The whole suite passed after the last code and docs commit (3614 passed).

---
*Phase: 10-walk-tests-and-the-gate*
*Completed: 2026-11-26*
