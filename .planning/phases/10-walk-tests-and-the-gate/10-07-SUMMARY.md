---
phase: 10-walk-tests-and-the-gate
plan: 07
subsystem: icm-walks
tags: [dana, walk_test, fixture, folder_copy, stripWalkFiles, tdd, pest]

requires:
  - phase: 10-walk-tests-and-the-gate
    provides: "10-03 to 10-05: WalkRunner, the walk world, the gate that replays as_built recordings, seedRecording() and layWalk()"
  - phase: 10-walk-tests-and-the-gate
    provides: "10-06: walks:record with --export, FolderSync::candidateForWalks(), FakeClassifier::queueReadings() and queueAnswers()"
provides:
  - "examples/example-hvac/tests/dana.md: Dana end to end, the example business's first gating walk (WALK-03)"
  - "stripWalkFiles() in tests/Pest.php, called by the beforeEach folder copy, so no test's copy of the example folder holds a walk file (T1, Pitfall 2)"
  - "tests/Fixtures/walks/dana.json: Dana's recording as a placeholder fixture (source placeholder, fingerprint null) that CI replays against the bars read at test time"
  - "layExampleWalks(), danaFakes() and seedRecordingFromFixture() test helpers; DanaEndToEndTest (8 cases)"
  - "BLUEPRINT knowledge area Built bullet for Dana, with its Affects line"
affects: [10-08 example gating walks, 10-09 report (flags placeholders), 10-10 docs/SETUP.md live run, Ian's live walks:record --export run]

actuals:
  tokens: 6300
  tasks: 2
  commits: 4

tech-stack:
  added: []
  patterns:
    - "A walk test copies an example walk into its folder on purpose with layExampleWalks(); the shared beforeEach copy strips them"
    - "A committed recording fixture is the form walks:record --export writes; a null fingerprint means placeholder, so the helper stamps the live version's fingerprint and the fixture is fresh"
    - "The feature-test proof reads the walk's own world inside the runner's inspect closure, then asserts after the world closes"

key-files:
  created:
    - examples/example-hvac/tests/dana.md
    - tests/Fixtures/walks/dana.json
    - tests/Feature/Walks/DanaEndToEndTest.php
  modified:
    - tests/Pest.php
    - tests/Feature/Walks/WalkHelpers.php
    - docs/BLUEPRINT.md
    - .planning/WINDOWS.md

key-decisions:
  - "The Dana feature case asserts the appointment, owner alert, card, today.md and reminder before it asserts the runner's own outcome, so a break in any one reads as that one's failure and not as a failed walk"
  - "Dana's reading and words checks use intent intake (sendable); only turn 1's reading carries a non-default score (complaint 0.04, under the shipped 0.15 bar and over the lowered 0.005 bar)"

patterns-established:
  - "Fixture helper names: seedRecordingFromFixture(name) reads tests/Fixtures/walks/<name>.json"

requirements-completed: [WALK-03]

coverage:
  - id: D1
    description: "Dana's walk file runs end to end through the front desk's real path as a feature test: one booked ac-repair visit at 2026-12-02 11:00 in the business zone, one AppointmentBooked owner alert, her card naming AC repair and the visit's day, records/today.md listing the visit, one pending 24-hour reminder, and the runner's outcome Passed"
    requirement: WALK-03
    verification:
      - kind: integration
        ref: "tests/Feature/Walks/DanaEndToEndTest.php#Dana goes from her first text to a booked visit (WALK-03 as a feature test, D-08)"
        status: pass
    human_judgment: false
  - id: D2
    description: "Every test's copy of the example folder leaves walk files out (README.md stays), the 29-file count is unchanged and the full suite is green with the first example walk shipped"
    requirement: WALK-03
    verification:
      - kind: integration
        ref: "tests/Feature/Walks/DanaEndToEndTest.php#leaves walk files out of every test copy of the example folder (T1)"
        status: pass
      - kind: other
        ref: "php artisan test --compact (3629 passed)"
        status: pass
    human_judgment: false
  - id: D3
    description: "Dana's file is fictional and plain: named start, no exclamation point, no em dash, no phone number"
    requirement: WALK-03
    verification:
      - kind: unit
        ref: "tests/Feature/Walks/DanaEndToEndTest.php#keeps Dana fictional and plain (D-12)"
        status: pass
    human_judgment: false
  - id: D4
    description: "Dana passes as a walk test: after walks:record the next sync activates and the changelog reads 'Live as <label> (30 files)' with no stale line; the committed fixture replays and goes live"
    requirement: WALK-03
    verification:
      - kind: integration
        ref: "tests/Feature/Walks/DanaEndToEndTest.php#Dana passes at the gate after walks:record (WALK-03 as a walk test); replays Dana's committed fixture and goes live (Ian's D-02 ruling)"
        status: pass
    human_judgment: false
  - id: D5
    description: "The fixture is bound to Dana's customer turns by script hash, replays against the bars read at test time (a lowered complaint bar refuses it at turn 1 in plain words) and a closed Wednesday refuses it at turn 4, keeping the live version live"
    requirement: WALK-03
    verification:
      - kind: integration
        ref: "tests/Feature/Walks/DanaEndToEndTest.php#matches the fixture to Dana's customer turns, so a changed turn fails the suite (D-05 in CI); applies today's bars to the fixture (Ian's D-02 ruling); refuses Dana when the folder has no Wednesday opening and keeps the live version live (criterion 2)"
        status: pass
    human_judgment: false
  - id: D6
    description: "CI pgsql leg for DanaEndToEndTest and the full suite after Ian pushes the branch; Ian's live walks:record --export that replaces the placeholder fixture"
    verification: []
    human_judgment: true
    rationale: "Postgres is not available here, and the live model and Jev answer only in Ian's run. Recorded as WINDOWS.md entries 95 (unrun-verify) and 96 (stub), never a pause."

duration: 20min
completed: 2026-11-26
status: complete
commits: 4
plan_head_before: 5d235cce6701b801571d73d313015ccf544f8854
plan_head_after: 66c6dc8063be6f71422ff462051c881733a13ae7
---

# Phase 10 Plan 07: Dana end to end Summary

**"Dana end to end" ships as the example business's first walk file, passes as a feature test (appointment, owner alert, card, today.md and a 24-hour reminder asserted by the test itself) and as a walk test at the gate from a placeholder fixture CI replays against today's bars, while `stripWalkFiles()` keeps every other test's folder copy walk-free.**

## Performance

- **Duration:** about 20 min (started 2026-11-26T23:18:12Z, last code commit about 23:33Z, SUMMARY after the final suite run)
- **Tasks:** 2 (Task 1 a tracer, Task 2 auto tdd)
- **Files:** 3 created, 4 modified (plus the two orchestrator-owned STATE files)

## Accomplishments

- `examples/example-hvac/tests/dana.md` is the plan's file as given: start 2026-12-01 10:00 (a Tuesday), books 2026-12-02 11:00, waits a day, ends on `card updated, records updated`. No `!`, no em dash, no phone number.
- `tests/Pest.php` gains `stripWalkFiles()` and the one call line after `File::delete($folder.'/changelog.md');` (line 87), plus one sentence in `exampleFolderFileCount()`'s doc comment. `return 29;` and every other line are as they were.
- The feature case lays Dana's file in, scripts the suite's fakes with `danaFakes()` and runs `WalkRunner::record()` over the candidate. Inside the open walk world it reads the appointment (contact, ac-repair, 2026-12-02 11:00 America/Chicago, booked), the owner alerts (exactly one, AppointmentBooked), her card (names AC repair and Wednesday, December 2, 2026), `today.md` (lists the 11:00 visit) and the pending reminder rows (one, lead 1440); it asserts those and then the runner's outcome, Passed.
- Task 2: `walks:record` then `icm:sync` activates (changelog `Live as <label> (30 files)`, no stale line); the committed placeholder fixture seeds through `seedRecordingFromFixture()` and the sync goes live; the fixture's script hash equals the Dana file's; with `gate.classifier.complaint_max_yes` set to 0.005 the same fixture is refused with `tests/dana.md, turn 1: the reply was held for the owner, not sent on its own. <live label> stays live.`; with Wednesday closed it is refused with `tests/dana.md, turn 4: Wednesday, December 2 at 11:00 AM was not offered. <live label> stays live.` Every label in an expectation is built from `label()` of a version read from the database (Ian's Postgres-safe rule).

## Task Commits

1. **Task 1 (tracer): Dana as a feature test; test copies leave walk files out**
   - RED `d29b326` test(10-07): Dana end to end as a feature test (WALK-03)
   - GREEN `ded1f2b` feat(10-07): Dana end to end; test copies leave walk files out (WALK-03, T1)
2. **Task 2: Dana as a walk test from a committed fixture**
   - RED `9216167` test(10-07): Dana passes as a walk test from a committed fixture (WALK-03)
   - GREEN `66c6dc8` feat(10-07): Dana's committed recording fixture (WALK-03)
3. **Hand check ledger** `4a206d5` docs(10-07): record the CI pgsql hand check and the placeholder fixture in the windows ledger

**Plan metadata:** the docs commit that holds this file (SUMMARY, STATE, ROADMAP, REQUIREMENTS).

Tracer gate (Task 1, no `gate` attribute, automated-only verify, `end-of-phase`): the verify command was re-run after the feat commit (814 passed) and logged "Tracer verified end-to-end, expanding". No checkpoint.

## How the fixture was produced

`tests/Fixtures/walks/dana.json` was made with the recorder, never by hand-typing scores. A throwaway Pest test, `tests/Feature/Walks/ThrowawayDanaExportTest.php`, did this and nothing else:

```php
expect(Http::preventingStrayRequests())->toBeTrue();
layExampleWalks('dana');
danaFakes();
$this->artisan('walks:record', ['walk' => ['tests/dana.md'], '--export' => base_path('tests/Fixtures/walks')])->assertExitCode(0);
```

It ran once under `php artisan test --compact` (1 passed) with the suite's fakes and `Http::preventStrayRequests()` on (asserted in the test), then was deleted (`rm`). It was never added to git: `git ls-files tests/Feature/Walks` lists no throwaway file and `git status --short` was clean of it. The export was then edited by textual replacement of three things only: `"fingerprint"` to `null`, `"source"` to `"placeholder"`, and a `"note"` line added after it (placeholder recording made with the test suite's scripted model and classifier, not a live run; Ian's `walks:record --export` replaces it). The payload is byte for byte as exported. No live model, Jev or Twilio call was made at any point; `walks:record` ran only inside Pest.

## TDD Gate Compliance

Task 1: RED `d29b326` precedes GREEN `ded1f2b`. Task 2: RED `9216167` precedes GREEN `66c6dc8`. No refactor commit. `gsd_run check tdd-red-evidence` was not run: Pest's console output is not one of its supported formats (the same gap as 10-02 to 10-06).

### RED evidence and semantic assessment

**Task 1 (`d29b326`):** `{"result":"failed","tests":3,"passed":0,"failed":2,"errors":1}`. All three cases stopped on the planned absence: `Failed asserting that file ".../examples/example-hvac/tests/dana.md" exists.` (the feature case inside `layExampleWalks()`, and the T1 case) and `File does not exist at path .../examples/example-hvac/tests/dana.md.` (the plain-file case). Semantic assessment: the three targets ran and failed at the first line that needs Dana's file, for the planned reason. Because that hid the deeper assertions, every case was mutation-checked after GREEN (below).

**Task 2 (`9216167`):** `{"result":"failed","tests":8,"passed":4,"errors":4}`. The four fixture cases errored with `File does not exist at path .../tests/Fixtures/walks/dana.json.` (planned absence). The case that needs no fixture, `Dana passes at the gate after walks:record`, passed on its first run and was mutation-checked.

### Passed on first run, break-on-purpose

For each mutant the file was changed in place, the test file run, and the file restored from a copy; the harness compared the full sha256 before and after and printed `True` for every one. The files were never committed in a mutated state. `git status --short` afterwards showed only the orchestrator's STATE files plus my own uncommitted work.

Task 1, sha256 before and after each mutation (identical):

| File | sha256 |
|---|---|
| `examples/example-hvac/tests/dana.md` | `4289087029a9b97af192840be737af1903324dd2f6aa10521df17c3a82b6d91f` |
| `tests/Pest.php` | `70b0c64b1cae00c0868dd1011535750c8896eeada43048a647381fc3de25fed8` |
| `app/Actions/BookAppointment.php` | `907a2950d342f55f563a8064f35a6a8d4e35acebb2a0f887928409de2093b82c` |
| `app/Records/RecordsWriter.php` | `299944184fb0463e8139bc9a51f8f2931188b2215c1286e90aa742f45953c304` |
| `app/Rooms/ScheduleAndBooking/ReminderSchedule.php` | `a7714a5d7e49f9aaab5077b0a3f138ff1e2dc6d839f2a2966c5e118720999ada` |

| Test | File broken | How | Red line |
|---|---|---|---|
| feature case | dana.md | `books` 11:00 to 12:00 | appointment array not identical (starts) |
| feature case | dana.md | wait step removed | `today.md` still reads "Schedule for Tuesday, December 1" |
| feature case | dana.md | books 2026-12-01 12:00 (too soon for a reminder) | appointment array not identical |
| feature case | ReminderSchedule.php | skip test replaced by `if (true) {` | reminders `[1440]` vs `[]` |
| feature case | BookAppointment.php | return before the owner alert | alerts `[AppointmentBooked]` vs `[]` |
| feature case | RecordsWriter.php | card write removed | card is null (`toContain` on null) |
| feature case | RecordsWriter.php | today.md write removed | today is null (`toContain` on null) |
| T1 case | Pest.php | the `stripWalkFiles` call line removed | `tests/` holds dana.md as well as README.md (and the sync case fails) |
| T1 case | Pest.php | strip condition inverted (README deleted, walks kept) | README.md missing |
| D-12 case | dana.md | `!` added; em dash added; `202-555-0142` added | `not to contain '!'`, `'—'`, `'555'` (one each) |
| walk-file cases | dana.md | `start:` line removed | named-start check and file text fail |

(The order of the feature case's expectations was changed once, after a first round showed the runner's own outcome check hiding the individual ones: world facts first, outcome last, and a second round, then a third for the reminder and today lines, killed every mutant above.)

Task 2, sha256 before and after (identical):

| File | sha256 |
|---|---|
| `tests/Fixtures/walks/dana.json` | `8c53ab693b23498b2335d13e37eec536e3af872f8e840c0bf9ebd8abd16bf1bf` |
| `app/Console/Commands/WalksRecord.php` | `7432a1ddd8f0d2b25076966cb59a809a790d4ab7b455f860cd2c624300b2c3f4` |
| `app/Icm/Walks/WalkRunner.php` | `0340f537a65fb11bbd8ae97b609481220fc3c624e98bd2cd2449592390584bdf` |
| `examples/example-hvac/tests/dana.md` | `4289087029a9b97af192840be737af1903324dd2f6aa10521df17c3a82b6d91f` |

| Mutant | Cases red | Red line |
|---|---|---|
| WalksRecord stores an empty tape | record-then-sync | `Expected status code 0 but received 1.` (sync refuses) |
| WalksRecord stores a wrong fingerprint | record-then-sync | `Expecting 'Live as v2 (30 files)...' not to contain 'is stale'` |
| dana.md `carries the booking link` turned into `held for the owner` | 4 | feature case, record-then-sync, fixture replay, Wednesday case |
| fixture `"label": "scheduling"` changed | 2 | fixture replay exit 1, Wednesday case line |
| dana.md customer turn "Not cooling" changed | 4 | script hash differs, sync refused (exit 1) |
| fixture `"source": "guess"` | 1 | `an array contains 'guess'` |
| dana.md books a Thursday | 4 | appointment, sync exit 1, Wednesday case |
| WalkRunner offer check turned off (`if (false)`) | 1 | Wednesday case line differs |
| fixture `walk_path` changed | 4 | exit 1, path string, sync lines |
| fixture turn 1 complaint 0.04 lowered to 0.001 | 0 (survived) | see below |

**One mutant survived: the fixture's turn-1 complaint score.** At the plan's lowered bar of 0.005, every score the fakes give is at least 0.01, so turn 1's words check also trips it; the "applies today's bars" case therefore passes whatever turn 1's reading holds. That is a property of the bar number the plan and Ian's rule 7 fix (`config(['gate.classifier.complaint_max_yes' => 0.005])`), so I kept it as written. The case still proves what D-02 asks: the bar is read at test time from config and a recorded score refuses the same fixture; it does not isolate the 0.04. A bar of about 0.02 would isolate it. I did not pin 0.04 inside the fixture, because Ian's live export will hold other scores.

## Wording

Walk lines are the ones 10-04 and 10-05 already print. This plan adds no console line or owner-facing string. Dana's file has no comments, so the whole file is owner-facing text and holds no `!` and no em dash. The fixture's `note` holds no `!` and no em dash either.

## Existing tests

`git diff --numstat 5d235cc HEAD` shows 0 deletions in every file: `tests/Pest.php` +16 -0, `tests/Feature/Walks/WalkHelpers.php` +76 -0, `docs/BLUEPRINT.md` +1 -0, and the three created files. `git diff 5d235cc -- tests/Pest.php` is additions only:

```
@@ -84,6 +84,7 @@
         File::copyDirectory(base_path('examples/example-hvac'), $folder);
         File::delete($folder.'/changelog.md');
+        stripWalkFiles($folder.'/tests');
         resetRecordsToTrackedFiles($folder.'/records');
         config(['icm.path' => $folder]);
@@ -312,9 +313,24 @@ function resetRecordsToTrackedFiles(string $records): void
+/**
+ * Leave the example's walk files out of a copied tests/ folder, ...
+ */
+function stripWalkFiles(string $tests): void
+{
+    foreach (File::files($tests, true) as $file) {
+        if ($file->getFilename() !== 'README.md') {
+            File::delete($file->getPathname());
+        }
+    }
+}
+
 /**
  * How many typed files examples/example-hvac holds. ...
+ * Walk files are stripped from every test's copy of the folder (stripWalkFiles()), so they do not count here, and a test
+ * that gates on walks lays them in on purpose.
  */
 function exampleFolderFileCount(): int
```

The re-runs the plan named passed unedited: `tests/Feature/Rooms/SpecTablesTest.php`, `ExampleRouterTest.php`, `tests/Feature/Quality/TestFolderCopyTest.php` (inside the Task 1 verify run of `tests/Feature/Icm tests/Feature/Rooms tests/Feature/Quality`, 814 passed) and the whole suite.

## Verification

- Task 1 verify (`DanaEndToEndTest`, `tests/Feature/Icm`, `tests/Feature/Rooms`, `tests/Feature/Quality`): 814 passed, before and after the commit.
- Task 2 verify (`tests/Feature/Walks`, `tests/Feature/Icm/WalkGateTest.php`): 107 passed.
- Acceptance checks, all pass: `stripWalkFiles($folder.'/tests');` on line 87 with `copyDirectory` on 85 and `changelog.md` on 86; `return 29;`; `start: "2026-12-01 10:00"` and `books: "2026-12-02 11:00"` in dana.md; `grep -c '!'` and `grep -c '—'` of dana.md both 0; fixture holds `"walk_path": "tests/dana.md"` and `"source": "placeholder"`; no throwaway file in `git ls-files tests/Feature/Walks`; `WALK-03` on a `**Built:**` line of BLUEPRINT.
- **Whole suite, `php artisan test --compact`, run after the last code commit:** `{"tool":"pest","result":"passed","tests":3629,"passed":3629,"assertions":27198,"duration_ms":309084,"warnings":1,"warning_details":[]}`. The one warning carries no detail (the same as 10-05 and 10-06). A first full run after Task 1's GREEN was also green (3624 passed).
- `python3 ~/Projects/cartographer/tools/check_map.py ~/Projects/ops-platform --extra-root examples/example-hvac`: `RESULT: PASS` (also after each feat commit). `vendor/bin/pint --test`: passed. `vendor/bin/pint --dirty --format agent` passed before each commit.
- No cited map line moved: the map cites `tests/Pest.php:70` only, and the new line is 87.

## Decisions Made

See `key-decisions`. The recorded D-xx rulings are unchanged.

## Deviations from Plan

**1. [Interpretation] The feature case asserts the world before the runner's outcome**
- The plan lists the walk outcome first. A first mutation round showed that a break in the booking, the card or today.md failed the runner's own check first, so the test's own assertions were not what turned red. The expectations are now world facts first and the outcome last. This is in `DanaEndToEndTest.php`, a file this plan created (the change is in the Task 1 GREEN commit, not the RED one).

**2. [Interpretation] The strip helper deletes by file name, not by an allow-list of types**
- `stripWalkFiles()` deletes every file under the copied `tests/` except `README.md`, exactly as the plan words it, including files in subfolders (`File::files($tests, true)` with hidden files). The example's `tests/` holds only README.md and dana.md today.

**Total deviations:** 0 auto-fixed, 2 interpretations. **Impact on plan:** none; no change reached `app/`, `config/`, `database/` or `routes/`.

## Issues Encountered

- The first mutation round showed the order of the feature case's expectations hid the individual checks (Deviation 1).
- One mutant survived: the lowered bar of 0.005 is under every score the fakes give, so turn 1's complaint score is not isolated (above).

## Known Stubs

- `tests/Fixtures/walks/dana.json`: the recording is a placeholder (`"source": "placeholder"`, `"fingerprint": null`, a `note`), made with the suite's scripted model and classifier. Intended by the plan (T-10-26); replaced by Ian's `walks:record --export` live run. Recorded in `.planning/WINDOWS.md` as entry 96 (stub, open).

## Threat Flags

None. T-10-25: Dana Whitfield and the runner's 555-01xx number are fictional, her file holds no phone and the fixture came from the suite's fakes; the plain-file case scans dana.md. T-10-26: the fixture says placeholder in `source` and in `note`. T-10-27: `stripWalkFiles()` shipped in the same commit as dana.md and the full suite ran green before and after.

## Hand checks for end-of-phase UAT

- CI pgsql leg: after Ian pushes the phase branch, `DanaEndToEndTest` and the full suite green on Postgres. Recorded as `unrun-verify` entry 95 in `.planning/WINDOWS.md` (commit `4a206d5`). Not a pause.
- Ian's live `walks:record --export` to replace the placeholder (already covered by entry 93 for the recorder, and entry 96 for this fixture).

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- The example business has a walk and the test copies leave it out, so 10-08 can add the other gating walks to `examples/example-hvac/tests/` and a test that wants them lays them in on purpose.
- 10-09 can flag `source: placeholder` fixtures in its report.
- Nothing blocks.

## Self-Check: PASSED

- Created files exist (`[ -f ]`): `examples/example-hvac/tests/dana.md`, `tests/Fixtures/walks/dana.json`, `tests/Feature/Walks/DanaEndToEndTest.php`.
- Commits `d29b326`, `ded1f2b`, `9216167`, `66c6dc8`, `4a206d5` are ancestors of HEAD; `git rev-list --count 5d235cc..66c6dc8` is 4, matching `commits: 4`.
- `git status --short` held only the orchestrator's STATE edits before this commit; no throwaway test exists on disk or in git.

---
*Phase: 10-walk-tests-and-the-gate*
*Completed: 2026-11-26*
