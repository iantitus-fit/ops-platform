---
phase: 10-walk-tests-and-the-gate
plan: 04
subsystem: icm-walks
tags: [walk_test, checks, booking, fingerprint, script-hash, tdd, pest]

requires:
  - phase: 10-walk-tests-and-the-gate
    provides: "10-03: ScratchWorld, WalkRunner::replay() (says and wait arms), WalkTape, ReplayFrontDeskAgent, ReplayClassifier, WalkResult"
  - phase: 10-walk-tests-and-the-gate
    provides: "10-02: WalkFile, WalkStep, WalkCheck and WalkCheckKind (the closed check list)"
provides:
  - "WalkCheck::evaluate(): every D-09 check judged against what the candidate's code did, in plain failure words (the plan's words plus Ian's four lines of 2026-11-23)"
  - "WalkTurnObservation, WalkWorldState, WalkFailure: plain value types; WalkWorldReader: read-only observations of the walk's own world (replies, drafts, hand-over, alerts, booked visit, pending reminder, the card file and records/today.md)"
  - "WalkRunner books arm: BookAppointment::fromLink() on the persona's newest open link, a named time doubling as the 'still offered' check; first failed check stops the walk and is named by turn (null for an end check)"
  - "WalkFingerprint: scriptHash() (D-05, customer turns byte for byte), of(), forActiveConfig() (D-04); FrontDeskPrompt::walkFingerprintMaterial(); WalkResult now carries failure, turns and the candidate's fingerprint"
affects: [10-05 the gate, 10-06 walks:record]

actuals:
  tokens: 14600
  tasks: 3
  commits: 6

tech-stack:
  added: []
  patterns:
    - "Checks are pure: observation, world state and services come in as values, so the failure words are pinned with no app running"
    - "The reader reaches visits through the contact's relation and the send rows, never importing the appointment model, so the write scan has nothing to match"
    - "Break-on-purpose for every new test that was green on its first run, and for each assertion whose RED was only 'class not found', with sha256 and a byte comparison after restore"

key-files:
  created:
    - app/Icm/Walks/WalkTurnObservation.php
    - app/Icm/Walks/WalkWorldState.php
    - app/Icm/Walks/WalkFailure.php
    - app/Icm/Walks/WalkWorldReader.php
    - app/Icm/Walks/WalkFingerprint.php
    - tests/Feature/Walks/WalkStepsTest.php
    - tests/Feature/Walks/WalkFingerprintPromptTest.php
    - tests/Unit/Icm/WalkFingerprintTest.php
  modified:
    - app/Icm/Walks/WalkCheck.php
    - app/Icm/Walks/WalkRunner.php
    - app/Icm/Walks/WalkResult.php
    - app/Ai/FrontDeskPrompt.php
    - map/objects/folder/agent-file-and-version.md
    - tests/Feature/Walks/WalkHelpers.php
    - tests/Unit/Icm/WalkCheckTest.php
    - docs/BLUEPRINT.md
    - .planning/WINDOWS.md

key-decisions:
  - "A step that both handed the conversation to the owner and made a draft cannot be built as a WalkTurnObservation (LogicException): the front desk's hand-over sends only its own fixed line and makes no draft, so the case does not arise, and refusing it keeps any check from choosing words for it (rule 9)"
  - "The price check compares price_sentences only, as the plan says, exactly and byte for byte; a service whose sentence lives only in price_default is not matched"
  - "A booking refusal's plain reason has its trailing full stop removed, so the gate's composed line '<file>, turn <n>: <words>. vN stays live.' does not read 'folder.. v3'"
  - "End checks are evaluated as written against an end observation whose owner-alerted flag is the walk-wide one; a turn-only phrase at the end (the sync refuses it) fails as 'no reply was sent' rather than being skipped"
  - "A candidate with no agent file for texts has the empty prompt material in its fingerprint"

patterns-established:
  - "WalkWorldReader::mark() before a step, ::turn() after it: the step's own rows are the ones with an id above the mark"

requirements-completed: [WALK-02, WALK-03]

coverage:
  - id: D1
    description: "Every D-09 check runs in the order written against what the candidate's code did and fails in plain words naming its turn (or the end); the walk stops at the first failure"
    requirement: WALK-02
    verification:
      - kind: integration
        ref: "tests/Feature/Walks/WalkStepsTest.php#fails at the first check that fails, naming its turn; #plays no turn after the one that failed; #passes when every check holds"
        status: pass
      - kind: unit
        ref: "tests/Unit/Icm/WalkCheckTest.php (evaluate cases: 26 check evaluations, 5 rulings by Ian, no exclamation point or dash, label from a held draft, the refused double case)"
        status: pass
    human_judgment: false
  - id: D2
    description: "A books step books through BookAppointment::fromLink() on the persona's newest open link; a named time no longer offered, a missing link and a refused booking fail in plain words; the booked visit, its pending reminder, the card and records/today.md are checked"
    requirement: WALK-03
    verification:
      - kind: integration
        ref: "tests/Feature/Walks/WalkStepsTest.php (books the named time and queues its reminder; fails when the expected time is no longer offered; books the earliest time offered; fails when no booking link was sent; shows the visit on the card and in records after a wait; fails the card and records checks when nothing was booked)"
        status: pass
    human_judgment: false
  - id: D3
    description: "A price sentence the candidate no longer holds fails whatever words were recorded, and a reworded one passes, because the sentence is the candidate's own"
    requirement: WALK-02
    verification:
      - kind: integration
        ref: "tests/Feature/Walks/WalkStepsTest.php (the three price sentence cases)"
        status: pass
    human_judgment: false
  - id: D4
    description: "The script hash covers the customer turns only, byte for byte; the fingerprint covers the front desk prompt material and the Jev questions, is stable across customers and runs, and changes with the agent body, a loaded file or the triage questions but not with a file the agent does not load; the result carries the candidate's"
    requirement: WALK-02
    verification:
      - kind: unit
        ref: "tests/Unit/Icm/WalkFingerprintTest.php"
        status: pass
      - kind: integration
        ref: "tests/Feature/Walks/WalkFingerprintPromptTest.php"
        status: pass
    human_judgment: false
  - id: D5
    description: "The wording of the failure lines that the plan did not give (Ian's four) and the choice to refuse a hand-over-and-draft turn rather than word it"
    verification: []
    human_judgment: true
    rationale: "Wording and the refusal rule are Ian's to judge; tests pin the words but only Ian can say they read right to an owner."

duration: 16min
completed: 2026-11-23
status: complete
commits: 6
plan_head_before: 62bcf3d7c027de28fbd09bc325b2ae22c7b7bb49
plan_head_after: bfa8078dddf91f29742e81e2b1a9e3f29a43bc77
---

# Phase 10 Plan 04: Walk checks, the books step and the fingerprint Summary

**A walk now books through the booking page's own `BookAppointment::fromLink()`, runs its D-09 checks in the order written against what the candidate's code did in its own world, stops at the first failure in plain words naming the turn, catches a time no longer offered and a price sentence no longer held whatever words were recorded, and carries a script hash of its customer turns and a fingerprint of the front desk prompt material and the Jev questions.**

## Performance

- **Duration:** about 16 min
- **Started:** 2026-11-23T16:41:07Z
- **Completed:** 2026-11-23T16:58Z (SUMMARY written)
- **Tasks:** 3 (Task 1 a tracer, Tasks 2 and 3 auto; all `tdd="true"`)
- **Files:** 8 created, 9 modified

## Accomplishments

- `WalkCheck::evaluate(WalkTurnObservation, WalkWorldState, array $services): ?string` is pure. It holds the plan's failure words and Ian's four lines, and the price check compares each of the service's `price_sentences` exactly against the text sent that turn, using the candidate's own service name in the failure.
- `WalkWorldReader` reads the walk's world only (`mark()`, `turn()`, `world()`): a reply counts as sent on its own when an `AgentReply` or one of `MessageKind::assembledByCode()` went out and no draft was made in the step; held when a draft was made; handed when the conversation is now paused for `asked_for_person` and was not before. `world()` reads the booked visit, its pending reminder (a send row with a lead above 0 and no sent, skipped or failed time), the card file at `RecordsWriter::root()` plus `CustomerCard::filename()` and `records/today.md`, matched on the line `CustomerCard` and `TodayRecord` write.
- `WalkRunner::replay()` plays the books arm (newest open link, `Availability::openStarts()` for the earliest time or `offers()` for a named one, then `BookAppointment::fromLink()`), evaluates each step's checks in order, stops at the first failure and then evaluates the end checks. `WalkResult` carries a `WalkFailure` (turn, words), the observation of every step played and the candidate's fingerprint.
- `WalkFingerprint::scriptHash()` (sha256 over the says texts joined by NUL, exact bytes), `of()` and `forActiveConfig()`; `FrontDeskPrompt::walkFingerprintMaterial()` is the last method of the class (the only hunk starts at line 469, so no cited line moved); the agent-file map card names it.

## Rulings by Ian (2026-11-23)

Used character for character, each with its own `WalkCheckTest` evaluate() case (`rulings by Ian on a turn with no reply`):

| Check | What happened in the turn | Words |
|---|---|---|
| held for the owner | the conversation was handed to the owner that turn | `the conversation was handed to the owner, not held for the owner` |
| held for the owner | nothing was sent and no draft was made | `no reply was held for the owner` |
| labeled `<label>` | the conversation was handed to the owner that turn (no sent reply, no draft to read) | `the conversation was handed to the owner, so no reply was labeled <label>` (e.g. `... so no reply was labeled promise`) |
| labeled `<label>` | nothing was sent and no draft was made | `no reply was made, so none was labeled <label>` (e.g. `no reply was made, so none was labeled promise`) |

The plan's own lines still cover the sent case (`the reply was sent on its own, not held for the owner`) and a wrong label (`the reply was labeled faq_answer, not promise`). Kept from the previous executor, not rulings: end checks evaluate against an end observation whose owner-alerted flag is `WalkWorldState::ownerAlertedInWalk`; turn checks use the step's own alert flag; `WalkWorldReader` holds no `->push(`, `->update(` or `->save(` anywhere, comments included.

## Task Commits

1. **Task 1 (tracer): a walk whose second turn should hold but sends fails, naming turn 2 and the check**
   - RED `48ed78c` test(10-04): a walk fails at its first failed check (D-09)
   - GREEN `3059b28` feat(10-04): walk checks run in order and fail in plain words (D-09)
2. **Task 2: the books step, the expected time and the price sentence from code, the outcome checks**
   - RED `15f5100` test(10-04): books, expected time and price sentence from code (D-09)
   - GREEN `a9e9291` feat(10-04): the books step and the outcome checks (D-09)
3. **Task 3: the script hash and the candidate fingerprint**
   - RED `48a66de` test(10-04): script hash and candidate fingerprint (D-04, D-05)
   - GREEN `bfa8078` feat(10-04): walk fingerprint and script hash (D-04, D-05)

**Plan metadata:** the docs commit that holds this file (SUMMARY, STATE, ROADMAP, REQUIREMENTS, WINDOWS, BLUEPRINT).

Tracer gate (Task 1, no `gate` attribute, automated-only verify, `end-of-phase`): the verify command was re-run after the commits (125 passed), logged "Tracer verified end-to-end, expanding", and Task 2 started. No checkpoint.

## TDD Gate Compliance

RED then GREEN for every task; `git log --grep '^test(10-04)'` precedes `^feat(10-04)` each time; no REFACTOR commit was needed. `gsd_run check tdd-red-evidence` was not run: Pest's console output is not one of its supported report formats and no JUnit record was captured (the same gap as 10-02 and 10-03). The RED assessment is by reading each failing case's message.

### RED evidence and semantic assessment

**Task 1 (`48ed78c`): 3 failures and 35 errors, 42 pre-existing cases passing.** Every `evaluate()` case errored with `Call to undefined method App\Icm\Walks\WalkCheck::evaluate()` (the helper cases with `Class "App\Icm\Walks\WalkTurnObservation" not found`); the two WalkStepsTest cases that needed a failure got `Passed` where `Failed` was expected, and the three-turn case got `NeedsRecording` (turn 3 was reached, so nothing had stopped the walk); the passing-walk case could not read `$result->turns` (null). Semantic assessment: the planned absence. The 125-case verify passed after GREEN.

**Task 2 (`15f5100`): 5 of 9 new WalkStepsTest cases failed, 4 passed.** Failed: books the named time (`Passed` expected, `Failed` got: no books arm and no outcome evaluation), no longer offered (turn null, not 4), earliest time offered, no booking link (`Failed` expected, `Passed` got), card and records after a wait. Semantic assessment: the planned absence of the books arm. The four that passed on their first run are listed below.

**Task 3 (`48a66de`): 26 of 26 errored with `Class "App\Icm\Walks\WalkFingerprint" not found`.** Semantic assessment: the planned absence, but a missing class hides test bugs (10-03 had one), so every assertion was also mutation-checked after GREEN (below). That check found one test that could not fail: `does not let the prompt material and the questions run together`. A JSON encoding is self-delimiting, so no two different inputs collide even with no NUL byte between them. It was dropped in the feat commit, and a composition case (`builds its material from the rules, the agent body, each loaded file and the two Booking texts, and never the customer card`) added in its place.

### Passed on first run

Four new Task 2 cases passed on their first run, because Task 1 already held the price check and the card and records checks were false until Task 2. Each was broken on purpose, seen red, and the file restored byte for byte (a `cp` backup in the scratchpad, `cmp` identical, `git diff --stat` empty for the committed `WalkCheck.php`; `WalkWorldReader.php` was uncommitted, so `cmp` against the backup).

| Test | File broken | How | Red line |
|---|---|---|---|
| fails a price sentence the candidate no longer has | WalkCheck.php | the price check always holds | `WalkOutcome ... Failed expected, Passed actual` |
| passes the price sentence check against the example folder | WalkCheck.php | the price check never holds | `Passed expected, Failed actual` |
| passes a price sentence the candidate only reworded | WalkCheck.php | compared against the example's own words, not the candidate's | `Passed expected, Failed actual` |
| fails the card and records checks when nothing was booked | WalkWorldReader.php | card check always holds | `Failed expected, Passed actual` |

sha256 of `app/Icm/Walks/WalkCheck.php` before and after: `3e9bd17c6af38ad458b0f7ca33a4123399c9fb4e1b58c2714423fc1355d460ce`. Of `app/Icm/Walks/WalkWorldReader.php`: `7c813f6e18ac6380327dd7f59f6cd800e01e1094bb1ad6bc230993986115d8cf`.

Two more breaks on `WalkWorldReader.php` (card check never holds; records check never holds) turned `shows the visit on the card and in records after a wait` red (`Passed expected, Failed actual`), to show each check is read for real.

Task 3, after GREEN, eleven mutation checks (sha256 of the production files before and after, all `cmp` identical: `WalkFingerprint.php` `137593d38c0004290660200d72882b63a2531059d1cd24c52d343c8de6e47cec`, `FrontDeskPrompt.php` `6cfb2938874390cb0be3bff72ca0ccc02c574b043cbed5777d68fe5c7376ed86`, `WalkRunner.php` `ab76594e8dbba734fcb3acc368d46e78a15a9784696633742237d83cd90c6260`), each seen red:

| # | Break | Red in |
|---|---|---|
| 1 | script hash trims each text | `changes the script hash when a customer turn changes` (leading and trailing space rows) |
| 2 | script hash folds case | the same case (the capital letter row) |
| 3 | script hash also covers persona, start and the end checks | `leaves the script hash alone when anything but a customer turn changes` (3 rows) |
| 4 | `of()` ignores the Jev questions | `makes a fingerprint from the prompt material and the Jev questions` |
| 5 | material drops every loaded file | the agent-file-body, loaded-file and composition cases |
| 6 | material drops the loaded file's front matter | the hours front matter row |
| 7 | material gets the time added | `is the same twice, and the same whoever the customer is` |
| 8 | material takes every file in the folder, loaded or not | `does not change when a file the agent does not load changes` |
| 9 | material drops the online Booking text | the composition case |
| 10 | the runner returns no fingerprint | `returns the candidate fingerprint on the result, not the live one` |
| 11 | the runner returns the live fingerprint | the same case |

## Files Created/Modified

- `app/Icm/Walks/WalkTurnObservation.php`, `WalkWorldState.php`, `WalkFailure.php` - the value types the checks judge
- `app/Icm/Walks/WalkWorldReader.php` - read-only reads of the walk's world
- `app/Icm/Walks/WalkCheck.php` - `evaluate()` and the failure words
- `app/Icm/Walks/WalkRunner.php` - books arm, check evaluation, fingerprint on the result
- `app/Icm/Walks/WalkResult.php` - `failure` is a `WalkFailure`, `turns` a list of observations
- `app/Icm/Walks/WalkFingerprint.php` - script hash and fingerprint
- `app/Ai/FrontDeskPrompt.php` - `walkFingerprintMaterial()` after `textOf` (one hunk, starting at line 469)
- `map/objects/folder/agent-file-and-version.md` - the Hits sentence (`FrontDeskPrompt.php:478`)
- `tests/Feature/Walks/WalkStepsTest.php`, `WalkFingerprintPromptTest.php`, `tests/Unit/Icm/WalkFingerprintTest.php` - new
- `tests/Feature/Walks/WalkHelpers.php`, `tests/Unit/Icm/WalkCheckTest.php` - additions only at the end (`git diff -U0 62bcf3d` shows no removed line and one `+` hunk each, after the original last line)
- `docs/BLUEPRINT.md` - the one Built line Ian gave, after the 10-02 Built line
- `.planning/WINDOWS.md` - entry 91 marked fixed

## Decisions Made

See `key-decisions` above. The two most worth Ian's eye: the refusal of a hand-over-and-draft turn, and counting only `price_sentences` (not `price_default`) for the price check.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug, in my own test] A separator test that could not fail**
- **Found during:** Task 3 mutation check
- **Issue:** `does not let the prompt material and the questions run together` passed with no separator at all, because JSON is self-delimiting.
- **Fix:** removed it and added a composition case that does fail when a part of the material is dropped.
- **Files modified:** `tests/Unit/Icm/WalkFingerprintTest.php`, `tests/Feature/Walks/WalkFingerprintPromptTest.php`
- **Committed in:** `bfa8078`

### Other deviations

**2. [Conflict between two instructions] Pint wants the added test code in `WalkCheckTest.php` to import its classes**
- `vendor/bin/pint --dirty` rewrote the appended code to short names and added three `use` lines to the file's import block (hunk `@@ -5,0 +6,3 @@`). Ian's rule 3 says no `use` added to that block, only fully qualified names in the appended code. I undid pint's change and kept the fully qualified names. As a result `vendor/bin/pint --test` reports exactly one file, `tests/Unit/Icm/WalkCheckTest.php`, with the `fully_qualified_strict_types` fixer. No other file is flagged. There is no pint step in CI (`.github` holds none). `WalkHelpers.php` needed none of this (its appended code uses only classes the file already imports); pint's only change there was one `single_quote` on a line I wrote.
- Superseded on 2026-11-23 (Ian): pint's imports were committed in `f7155c1`, and `vendor/bin/pint --test` no longer flags the file.

**3. [Plan wording] `WalkWorldReader` does not import the appointment model**
- The plan expected it to; it reads visits through `$persona->appointments()->booked()` instead. The write scan therefore has nothing to match, and the reader still holds no write call.

**4. [Decision, flagged for Ian] `WalkTurnObservation` refuses a step that both handed over and made a draft**
- The previous executor's question (rule 9). Reading `RunFrontDesk::handOffToOwner()` shows the hand-over sends only the fixed handoff line and makes no draft, so the case does not arise. Rather than let `sent on its own` choose between two true sentences, the value throws a `LogicException` ('a step that handed the conversation to the owner and also made a draft cannot be judged'). Tested (`refuses to hold a turn that both handed over and made a draft`). If Ian would rather the walk name one of the two in words, that is a one-line change in `WalkCheck::whySentOnItsOwnFailed()` plus lifting the constructor guard.

**5. [Interpretation] `price_default` is not counted by the price check**
- The plan names `price_sentences`. A service that sends only its `price_default` sentence (a keyed service's fallback) would fail a walk that expects its price sentence. None of the example folder's services is keyed, so no test covers it.

**6. [Interpretation] A booking refusal's reason loses its trailing full stop**
- `BookingRefused` messages end in a period; the words are `the booking was refused: <reason>` so the gate's composed line does not read `..`. Not exercised by a test (the example folder has no way to make `fromLink()` refuse after `offers()` passed).

**7. [Interpretation] `WalkWorldReader` takes a nullable persona**
- A wait before any customer text has no contact yet; `turn()` and `world()` accept null and return the quiet observation and an empty world.

**8. [Interpretation] A candidate with no agent file for texts fingerprints the empty prompt material**
- `forActiveConfig()` uses `''` there. A walk cannot be played against such a candidate anyway.

**9. [Note] `grep` on `$` lines uses `grep -F`** for the plan's acceptance greps (the BSD grep quirk noted in 10-02 and 10-03; the `evaluate(` signature grep fails as a regex and passes as `-F`).

**10. [Ruling by Ian, 2026-11-23, after the plan] The price check accepts the owner's default sentence (reverses deviation 5)**
- Code sends a service's `price_default` sentence as the owner's own sentence when no keyed option fits (`PriceSentencePicker::pickDefault`). The example's `system-replacement` has one, so a walk where the customer answers "Not sure" failed the check although the owner's words went out. `WalkCheck::whyPriceSentenceFailed()` now also accepts `$services[$slug]['price_default']['sentence']` when it is a non-empty string, compared exactly, after the keyed sentences. `ActiveConfig::services()` already carried `price_default`, so nothing else changed. Tested in `WalkCheckTest` (`price checks with a default sentence`) and `WalkStepsTest` (the check reads the candidate's own default).

---

**Total deviations:** 1 auto-fixed (own test), 1 instruction conflict (pint), 1 plan-wording difference, 5 interpretations flagged, 1 later ruling by Ian (10).
**Impact on plan:** None on scope. No existing test was edited; `WalkCheckTest.php` and `WalkHelpers.php` changed by additions only; `AppointmentWritesTest.php` is byte-identical to main.

## Issues Encountered

- A RED that fails only on a missing class cannot show a test is sound; the Task 3 mutation checks were added for that reason and found the one dead test (deviation 1).
- No network call to any model, Jev or SMS provider was made. The suite's fakes and `Http::preventStrayRequests()` stayed in force.

## Verification

- Task 1 verify (WalkStepsTest, WalkCheckTest, AppointmentWritesTest, BookingTextKindTest, ArchitectureTest): 125 passed.
- Task 2 verify (`tests/Feature/Walks`, AppointmentWritesTest, ArchitectureTest): 77 passed. `grep -c "^it(" WalkStepsTest.php` prints 12 (at least 8). `fromLink(` is in `WalkRunner.php`; no `use App\Models\Appointment;` outside comments.
- Task 3 verify (`WalkFingerprintTest`, `tests/Feature/Walks`, `tests/Feature/FrontDesk`) plus `check_map.py`: 838 passed, `RESULT: PASS`. The `FrontDeskPrompt.php` diff against main is one hunk, `@@ -468,0 +469,27 @@`.
- Plan `<verification>`: `php artisan test --compact tests/Feature/Walks tests/Unit tests/Feature/FrontDesk` gave 1378 passed (12873 assertions); `check_map.py` printed `RESULT: PASS`. `vendor/bin/pint --test` flags only `WalkCheckTest.php` (deviation 2). The full suite was not run (the orchestrator runs it after the wave).
- `git diff -U0 62bcf3d -- tests/Unit/Icm/WalkCheckTest.php tests/Feature/Walks/WalkHelpers.php | grep -E '^-[^-]'` prints nothing; `git diff --stat main -- tests/Unit/AppointmentWritesTest.php` prints nothing.
- WINDOWS.md entry 91 (the books no-op and empty fingerprint stub) is marked fixed; no books no-op and no empty fingerprint remain.

## Known Stubs

None. Entry 91 is resolved. `WalkResult` has no script hash field by design: `WalkFingerprint::scriptHash(WalkFile)` computes it from the walk, and the BLUEPRINT line's "carries a script hash of its customer turns" is true in that sense only. 10-05 and 10-06 are where a recording is matched to it.

## Threat Flags

None. The books step writes an appointment, a reminder row and a booking text only in the walk's own database and records folder (T-10-13: `WalkRunner` does not import the appointment model, `WalkWorldReader` holds no write call, `AppointmentWritesTest` is green and unchanged). T-10-11 and T-10-12 hold: the reader runs only inside an open world, and the order and words of the checks are pinned by `WalkStepsTest` and `WalkCheckTest`.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- 10-05 can build the owner line from `WalkResult->path`, `->failure->turn` and `->failure->words` and match a recording on `WalkFingerprint::scriptHash()` and the fingerprint on the result.
- Nothing blocks. One thing for Ian before 10-05 relies on it: deviations 4 and 5.

## Self-Check: PASSED

- All eight created files exist (`[ -f ]`).
- Commits `48ed78c`, `3059b28`, `15f5100`, `a9e9291`, `48a66de`, `bfa8078` are ancestors of HEAD (`git merge-base --is-ancestor`); `git rev-list --count 62bcf3d..HEAD` is 6, matching `commits: 6`; `gsd_run check evaluation-scope --plan 10-04 --commits-only` resolved with the same commits.
- `php artisan test --compact tests/Feature/Walks tests/Unit tests/Feature/FrontDesk` passed (1378) after the last code commit.

---
*Phase: 10-walk-tests-and-the-gate*
*Completed: 2026-11-23*
