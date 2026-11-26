---
phase: 10-walk-tests-and-the-gate
plan: 11
subsystem: icm-walks
tags: [walk_gate, business_folder_page, icm_sync_console, spec, tdd, pest]

requires:
  - phase: 10-walk-tests-and-the-gate
    provides: "10-05: the gate, SyncResult::changelogErrors and staleNotes, the owner lines on the Rejected version, seedRecording() and layWalk() helpers"
provides:
  - "icm:sync prints Ian's form of each walk line (changelogErrors when present, else the owner lines) on a refusal"
  - "icm:sync prints each stale walk note as a warning on any outcome"
  - "Proof that the Business Folder page and the Sync Now danger notification show the owner's walk lines escaped, with no command and no stale wording, and that a stale walk reads as any passing sync"
  - "SPEC step 5 and the file tree, the example folder's tests guide and router line say walk tests gate every activation"
affects: [10-12 and later plans that read the console, Phase 10 UAT (keyboard and VoiceOver walk of the page)]

actuals:
  tokens: 2500
  tasks: 3
  commits: 6

tech-stack:
  added: []
  patterns:
    - "Page and notification code stay untouched: the owner lines 10-05 stores are the only thing they read, so the proof is a test, not a change"
    - "A Filament notification is read in a test by mounting Filament's Notifications component before assertNotified, which consumes the session"

key-files:
  created: []
  modified:
    - app/Console/Commands/IcmSync.php
    - tests/Feature/Filament/BusinessFolderPageTest.php
    - tests/Feature/Icm/WalkGateTest.php
    - tests/Feature/Walks/WalkHelpers.php
    - docs/SPEC.md
    - examples/example-hvac/tests/README.md
    - examples/example-hvac/CLAUDE.md
    - .planning/WINDOWS.md

key-decisions:
  - "The price walk's text and tape for the page cases live in tests/Feature/Walks/WalkHelpers.php as pricingWalkText() and pricingWalkTape() (append only), so BusinessFolderPageTest gains only its require_once line and new cases, and Pint adds no import to it"
  - "The unrecorded-walk page case reads the danger notification by mounting Notifications instead of assertNotified, because assertNotified consumes the session notification and the status, title and body must all be asserted"

patterns-established:
  - "Owner-facing lines are proven escaped by putting markup in a service name, which the validator accepts, and reading the line on the page and in the notification"

requirements-completed: [WALK-02]

coverage:
  - id: D1
    description: "An unrecorded walk's refusal shows on the Business Folder page under Last sync and in the danger Sync Now notification in the owner's words with no command, and v1 stays live"
    requirement: WALK-02
    verification:
      - kind: integration
        ref: "tests/Feature/Filament/BusinessFolderPageTest.php#lists an unrecorded walk under the last sync with no command (D-05, D-11)"
        status: pass
    human_judgment: false
  - id: D2
    description: "A walk line holding markup from the folder is escaped on the page and in the notification"
    requirement: WALK-02
    verification:
      - kind: integration
        ref: "tests/Feature/Filament/BusinessFolderPageTest.php#escapes a walk line on the page and in the notification (T-10-39)"
        status: pass
    human_judgment: false
  - id: D3
    description: "A stale walk that passes shows as any passing sync: success notification, Last sync reads Live, no stale words, no command"
    requirement: WALK-02
    verification:
      - kind: integration
        ref: "tests/Feature/Filament/BusinessFolderPageTest.php#shows a stale walk exactly as a passing sync (D-04, rulings 1 and 4)"
        status: pass
    human_judgment: false
  - id: D4
    description: "icm:sync prints Ian's line with the walks:record command on a refusal and each stale note as a warning, and a validation refusal prints as before"
    requirement: WALK-02
    verification:
      - kind: integration
        ref: "tests/Feature/Icm/WalkGateTest.php#prints Ian's line on the console (D-05); prints the stale notes on the console as warnings (D-04); tests/Feature/Icm/SyncTest.php"
        status: pass
    human_judgment: false
  - id: D5
    description: "SPEC step 5 and the file tree, the example tests guide and the example router line say walk tests gate every activation, with docs/SPEC.md:303 still the agent row"
    verification:
      - kind: other
        ref: "sed -n 303p docs/SPEC.md | grep -q '^| agent | agents/\\*\\.md |'; check_map.py RESULT: PASS; tests/Feature/Rooms/ExampleRouterTest.php and SpecTablesTest.php"
        status: pass
    human_judgment: false
  - id: D6
    description: "Keyboard-only and VoiceOver walk of the Business Folder page over a rejected sync with a failed walk line and an unrecorded line"
    verification: []
    human_judgment: true
    rationale: "A person has to use the page with a keyboard and a screen reader. Left unrun by Ian's rule (hand checks never pause the run) and recorded as an unrun-verify entry (id 94) in .planning/WINDOWS.md for end-of-phase UAT."

duration: 15min
completed: 2026-11-26
status: complete
commits: 6
plan_head_before: c6acf58673b5b50af6f35af29ae697e05de79977
plan_head_after: 21ccd0625f81bdeb508f191b34cfbd8a748a7d1f
---

# Phase 10 Plan 11: Walk lines on the page and the console Summary

**The owner's walk lines are proven on the Business Folder page and in the danger Sync Now notification (escaped, no command, no stale wording), `icm:sync` now prints Ian's form of each refused walk and a warning for each stale walk, and SPEC and the example folder's guides say walk tests gate every activation with `docs/SPEC.md:303` still the agent row.**

## Performance

- **Duration:** about 15 min of clock time (the start time was not captured; the first commit is 14:08 local and the SUMMARY is written about 20:15Z)
- **Tasks:** 3 (Task 1 a tracer, Task 2 auto tdd, Task 3 docs)
- **Files:** 0 created, 8 modified (the page, `SyncStatus` and the Blade view are unchanged)

## Accomplishments

- `IcmSync` prints `$result->changelogErrors ?: $result->errors` on a refusal, so Ian reads "tests/price.md: no recording. Run `walks:record tests/price.md`. v1 stays live." while a validation refusal (no Ian form) prints as before (SyncTest.php:143-155 passes unedited).
- `IcmSync` prints each `$result->staleNotes` entry with `warn()` after the notices, on an Activated or a Rejected sync.
- Three page cases in `BusinessFolderPageTest`: an unrecorded walk (page line, danger notification with the same line, no `walks:record`, v1 still the only Active version), escaping of `AC <i>sneaky</i> repair` in a failed walk's line on the page and in the notification body, and a stale passing walk reading as any passing sync (title "Live as v2 (N files)", status success, no body, Last sync "Live", no "stale", no "walks:record", no "Rejected").
- Two console cases in `WalkGateTest` (Ian's line, the stale warning).
- SPEC step 5 loses "(from Sprint 2)" and says each walk replays Ian's recording and that a walk with no recording or one that fails a check keeps the previous version live, all on the two lines step 5 already held; the file tree loses "(Sprint 2)". The example folder's `tests/README.md` and `CLAUDE.md:13` say each walk plays against every change before it goes live.

## Task Commits

1. **Task 1 (tracer): an unrecorded walk on the page, in the notification and on the console**
   - RED `5e7b962` test(10-11): an unrecorded walk on the page, in the notification and on the console (D-05, D-11)
   - GREEN `63c79bc` feat(10-11): icm:sync prints Ian's form of each walk line (D-05, D-27)
2. **Task 2: escaped walk lines, a stale walk as a passing sync, stale notes on the console**
   - RED `fcf6cb4` test(10-11): escaped walk lines, a stale walk on the page and stale notes on the console (D-04)
   - GREEN `2b99592` feat(10-11): icm:sync warns about each stale walk (D-04)
3. **Task 3: SPEC and the example folder's guides** `01ae002` docs(10-11): SPEC and the example folder say walk tests gate every activation (WALK-02)
4. **Hand check ledger** `21ccd06` docs(10-11): record the Business Folder keyboard and screen reader hand check in the windows ledger

**Plan metadata:** the docs commit that holds this file (SUMMARY, STATE, ROADMAP, REQUIREMENTS).

Tracer gate (Task 1, no `gate` attribute, automated-only verify, `end-of-phase`): the verify command was re-run after the feat commit (67 passed, `check_map.py` RESULT: PASS) and logged "Tracer verified end-to-end, expanding". No checkpoint.

## TDD Gate Compliance

Task 1: RED `5e7b962` precedes GREEN `63c79bc`. Task 2: RED `fcf6cb4` precedes GREEN `2b99592`. Task 3 is `tdd="false"`. No refactor commit. `gsd_run check tdd-red-evidence` was not run: Pest's console output is not one of its supported formats (the same gap as 10-02 to 10-06).

### RED evidence and semantic assessment

**Task 1 (`5e7b962`):** `{"result":"failed","tests":56,"passed":55,"failed":1}`. The failing case is `WalkGateTest` "prints Ian's line on the console (D-05)": `Output does not contain "tests/price.md: no recording. Run `walks:record tests/price.md`. v1 stays live."`. Semantic assessment: the target test ran and failed on the planned assertion (the console printed the owner form, which holds no command). The page case passed on its first run, as the plan expected (a pin on 10-05's code).

**Task 2 (`fcf6cb4`):** `{"result":"failed","tests":59,"passed":58,"failed":1}`. The failing case is `WalkGateTest` "prints the stale notes on the console as warnings (D-04)": `Output does not contain "tests/price.md is stale: its recording is older than ... to refresh it."`. Semantic assessment: the planned absence (IcmSync printed no stale note). The two page cases passed on their first run, as the plan expected.

GREEN for Task 1: `{"result":"passed","tests":67,"passed":67,"assertions":303}`. GREEN for Task 2: `{"result":"passed","tests":382,"passed":382,"assertions":1578}` (`BusinessFolderPageTest` plus `tests/Feature/Icm`).

### Passed on first run, break-on-purpose

Three new cases passed on their first run (the plan's pins). For each mutant the production line was changed in the working tree, the case seen red, and the file restored. Before and after each round the sha256 of the mutated file was identical (each round printed `same=True`) and `git diff --stat -- <file>` printed nothing. No broken state was committed (the mutation rounds ran before the test commits and `git status --short` was empty of production files at each commit).

File sha256 (identical before and after every round): `WalkGateResult.php` c01989ca4d4790b41d83115e7fec57fe9d35fab7accdb1fbf03a9b163456a228, `BusinessFolder.php` 77e879f1d46ca62fa5532874b7cd34b9967cdff92cf8c7cc6633860c377b2c17, `business-folder.blade.php` 8a40386504102b7b3099616db666d555424cf0a8e1690078fa5a454fafff74fd, `FolderSync.php` 2f284415eb9cbda687a93537a9039cedcc743da811a85adde8edaf36d9f88d9a.

**Case 1: lists an unrecorded walk under the last sync with no command (D-05, D-11)**

| Mutant | Red line |
|---|---|
| the owner line reads "this walk has no recording. Run walks:record." (`WalkGateResult::ownerLines`) | `Failed asserting that 'tests/price.md: this walk has no recording. Run walks:record. Ian&#039;s step. v1 stays live.' contains "this walk has no recording yet".` |
| `ownerLines()` returns Ian's lines | `Failed asserting that 'tests/price.md: no recording. Run `walks:record tests/price.md`. v1 stays live.' contains "this walk has no recording yet".` |
| the owner line gains " Run walks:record." after "stays live." | `Expecting 'tests/price.md: this walk has...ecord.' not to contain 'walks:record'.` |
| the Rejected notification made `success()` instead of `danger()` (`BusinessFolder::runSync`) | `Failed asserting that two strings are identical. -'danger' +'success'` |
| the page's `@foreach ($lastSync['errors'] ...)` loop emptied (blade) | `Failed asserting that '"zmerbcG1fNJ0mdI0uYOu" wire:name="App\Filament\Pages\BusinessFolder" ...' contains "tests/price.md: this walk has no recording yet. ..."` (the page HTML) |

An earlier version of this case used `assertNotified`, and the `success()` mutant survived it (assertNotified checks only the title); the case was strengthened to read the notification's status, title and body (mounting `Notifications`, which `assertNotified` would otherwise consume), then every mutant above was killed.

**Case 2: escapes a walk line on the page and in the notification (T-10-39)**

| Mutant | Red line |
|---|---|
| the notification body no longer maps lines through `e()` (`BusinessFolder::runSync`) | `Failed asserting that 'tests/price.md, turn 1: the reply did not carry the price sentence for AC <i>sneaky</i> repair. v1 stays live.' contains "the reply did not carry the price sentence for AC &lt;i&gt;sneaky&lt;/i&gt; repair"` |
| the page prints `{!! $error !!}` instead of `{{ $error }}` (blade) | `Failed asserting that '"D7N14F5lNa56uCgLKIUB" wire:name="App\Filament\Pages\BusinessFolder" ...' contains "the reply did not carry the price sentence for AC &lt;i&gt;sneaky&lt;/i&gt; repair"` (the page HTML) |

**Case 3: shows a stale walk exactly as a passing sync (D-04, rulings 1 and 4)**

| Mutant | Red line |
|---|---|
| a stale walk refuses the candidate (`WalkGateResult::refuses` adds `|| $entry->stale`) | `Failed asserting that two strings are identical. -'v2' +'v1'` (the stale walk was refused, so no v2 went live) |
| a stale line added to the stored version's notices (`FolderSync`, so the page's Last sync prints it) | `Failed asserting that '"kU7p6uMYrToqt2jkL4GQ" wire:name="App\Filament\Pages\BusinessFolder" ...' contains ...` (the page HTML held the stale line; the first attempt of this mutant referenced `$walks` out of scope and errored, so it was redone with a literal line) |
| " stale" appended to the Activated message (`FolderSync`) | `Failed asserting that two strings are identical. -'Live as v2 (30 files)' +'Live as v2 (30 files) stale'` |

The plan's flagged fallback (markup in a walk file name if the validator refuses markup in a service name) was not needed: the validator accepts the service name `AC <i>sneaky</i> repair` and the failed walk's line carries it as written, so the case holds the markup in the service name as the plan's main text says.

## Wording

None written beyond the plan's exact strings. The SPEC addition reads "Each walk replays Ian's recording of the conversation, and a walk with no recording, or one that fails a check, keeps the previous version live." The example `tests/README.md` reads: "Each file here is a scripted customer (`type: walk_test`). The sync plays every one of them against a change before it goes live, using Ian's recording of the conversation." / "A broken file, a walk with no recording or a failed check keeps the version that is live now, and the Business Folder page says which file and check." (No exclamation point and no em dash in either.)

## Cited lines and check_map

- `map/` holds no citation of `IcmSync.php`, `BusinessFolderPageTest.php`, `WalkGateTest.php`, `WalkHelpers.php` or the example folder's `tests/README.md` and `CLAUDE.md` (grep of `map/`, as at plan time). The only card citing a file this plan changed is `map/objects/folder/agent-file-and-version.md :45` with `docs/SPEC.md:303`; this plan did not edit it (`git show --name-only HEAD` of the docs commit does not list it).
- **SPEC.md:303 after Task 3:** `sed -n 303p docs/SPEC.md` prints `| agent | agents/*.md | name, channel, provider, model, fallback (list), loads (...`, the agent row. Step 5 stays on lines 89 and 90 (6 changed lines in the SPEC diff: 3 removed and 3 added, no line added or removed overall).
- **check_map.py** (`python3 ~/Projects/cartographer/tools/check_map.py ~/Projects/ops-platform --extra-root examples/example-hvac`): `RESULT: PASS` after Task 1's feat commit and after Task 3 (and the Task 3 verify command, which includes it, exited 0).

## Existing tests

`git diff 29d83f1 -- tests | grep '^-[^-]'` prints nothing: no existing line in any test file changed or was removed. `tests/Feature/Filament/BusinessFolderPageTest.php` gained its one `require_once __DIR__.'/../Walks/WalkHelpers.php';` line after its `use` block and new cases after its last case (no new `use` line; Pint's `fully_qualified_strict_types` imported a first attempt that used fully qualified class names, so the shared price-walk helpers moved to `WalkHelpers.php`). `tests/Feature/Icm/WalkGateTest.php` and `tests/Feature/Walks/WalkHelpers.php` gained lines only.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] The price walk's text and tape for the page cases are in `WalkHelpers.php`**
- **Found during:** Task 1 (the plan's page cases need the price walk and its tape; `gatePriceWalkText()` and `gatePriceTape()` live in `WalkGateTest.php`, which is not loaded when the page test runs alone, and `BusinessFolderPageTest` may gain only its `require_once` line and cases)
- **Issue:** a helper in `BusinessFolderPageTest` needs `WalkTape` imported, and Pint imports fully qualified names, so the file would gain a `use` line
- **Fix:** appended `pricingWalkText()` and `pricingWalkTape()` to `tests/Feature/Walks/WalkHelpers.php` (append only, which Ian's rule 4 allows) and used them from the page cases
- **Files modified:** tests/Feature/Walks/WalkHelpers.php
- **Committed in:** `5e7b962`

**2. [Rule 1 - Bug in the plan's test design] The unrecorded-walk page case reads the notification by mounting `Notifications`**
- **Found during:** Task 1 break-on-purpose
- **Issue:** the plan's `assertNotified` checks only the title, so a notification made `success()` instead of `danger()` passed the case, though the plan's truth says the notification is the danger one; and `assertNotified` consumes the session notification
- **Fix:** the case mounts `Notifications` (as the existing escaping case does) and asserts the title, the `danger` status and that the body holds the owner line and no `walks:record`
- **Files modified:** tests/Feature/Filament/BusinessFolderPageTest.php
- **Committed in:** `5e7b962`

**Total deviations:** 2 auto-fixed (1 blocking, 1 test-design). **Impact on plan:** none on behaviour; both keep to Ian's test-line rule and the plan's truths.

## Issues Encountered

- A stray empty-looking file named `success` appeared at the repo root while mutation-testing (an unquoted `>` in one of my own `echo` lines wrote `M4 notification danger-` into it). I deleted that one file before the first commit; it was never staged.

## Verification

- Task 1 verify (`BusinessFolderPageTest`, `WalkGateTest`, `SyncTest`, then `check_map.py`): 67 passed and `RESULT: PASS`.
- Task 2 verify (`BusinessFolderPageTest`, `tests/Feature/Icm`): 382 passed.
- Task 3 verify (`ExampleRouterTest`, `SpecTablesTest`, `SyncTest`, `check_map.py`, the line-303 grep): 43 passed, `RESULT: PASS`, line 303 is the agent row.
- Acceptance checks: `grep 'changelogErrors ?: $result->errors'` and `grep staleNotes` in `IcmSync.php` succeed; `git diff --quiet main -- app/Filament/Pages/BusinessFolder.php app/Icm/SyncStatus.php resources/views/filament/pages/business-folder.blade.php` exits 0; `config/gate.php` and the two Booking constants of `FrontDeskPrompt.php` are not touched by this plan (`git diff c6acf58 --stat` lists none of them); the SPEC, README and CLAUDE.md greps all succeed.
- `vendor/bin/pint --dirty --format agent` passed before each code commit.
- **Whole suite, `php artisan test --compact`:** `{"tool":"pest","result":"passed","tests":3619,"passed":3619,"assertions":27137,"duration_ms":302650,"warnings":1,"warning_details":[]}`. The one warning carries no detail; I did not chase it (the same as 10-05 and 10-06).
- No test and no command of mine reached the live model, Jev or Twilio. `walks:record` and `icm:sync` were not run from the shell; `icm:sync` ran only inside the Pest suite (`$this->artisan(...)`), with `FrontDeskAgent::fake()` and `FakeClassifier` and `Http::preventStrayRequests()` on.

## Hand check (left for UAT)

The plan's keyboard-only and VoiceOver walk of the Business Folder page over a rejected sync with a failed walk line and an unrecorded line was not run. It is recorded as an `unrun-verify` entry (id 94) in `.planning/WINDOWS.md` for end-of-phase UAT.

## Known Stubs

None.

## Threat Flags

None. No new endpoint, auth path, file access or schema: `IcmSync` prints lines the sync already stores, and the page and notification are unchanged.

## Decisions Made

- Page cases share the price walk through `WalkHelpers.php` (above), so `BusinessFolderPageTest` gains only the allowed lines.

## Next Phase Readiness

Plans that follow can rely on `icm:sync` printing Ian's walk lines and stale warnings. The page and notification need nothing more; the only open item for this plan is the keyboard and VoiceOver walk at UAT.

## Self-Check: PASSED

- Files modified exist: `app/Console/Commands/IcmSync.php`, the three test files, `docs/SPEC.md`, `examples/example-hvac/tests/README.md`, `examples/example-hvac/CLAUDE.md`, `.planning/WINDOWS.md`.
- Commits `5e7b962`, `63c79bc`, `fcf6cb4`, `2b99592`, `01ae002`, `21ccd06` are on `gsd/phase-10-walk-tests-and-the-gate` (`git rev-list --count c6acf58..HEAD` printed 6 before this SUMMARY's commit).
- RED precedes GREEN for each tdd task (`git log` order: `5e7b962` before `63c79bc`, `fcf6cb4` before `2b99592`).
