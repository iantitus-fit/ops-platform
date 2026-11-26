---
phase: 10-walk-tests-and-the-gate
plan: 05
subsystem: icm-walks
tags: [walk_test, gate, folder-sync, changelog, walk_recordings, tdd, pest]

requires:
  - phase: 10-walk-tests-and-the-gate
    provides: "10-04: WalkRunner checks and books arm, WalkFingerprint (script hash, fingerprint), WalkFailure"
  - phase: 10-walk-tests-and-the-gate
    provides: "10-03: ScratchWorld, WalkRunner::replay(), WalkTape, replay seams"
provides:
  - "walk_recordings table, WalkRecording model (AS_BUILT) and factory (stale(), variant())"
  - "WalkGate::run(): replays each walk of the candidate in path order against its as_built recording before the activation transaction; fails closed on any Throwable"
  - "WalkGateEntry and WalkGateResult: refused(), ownerLines(), ianLines(), staleNotes(); every line through HiddenCharacters::marked, no exclamation point, no em dash"
  - "FolderSync hook: a refused gate stores a Rejected version whose errors are the owner lines, keeps the live version, and gives changelog.md Ian's lines and the stale notes"
  - "SyncResult changelogErrors and staleNotes; Changelog Rejected and Activated arms"
  - "seedRecording() and layWalk() test helpers"
affects: [10-06 walks:record, 10-11 page and notification proof, 10-07 example walks]

actuals:
  tokens: 19400
  tasks: 3
  commits: 5

tech-stack:
  added: []
  patterns:
    - "Owner form and Ian form of one line are built in one pure class (WalkGateResult) so the page and the changelog can never share a command"
    - "A gate that fails closed: any Throwable from a walk is an Errored entry that refuses activation and is reported"
    - "Break-on-purpose matrix of 35 mutants over the four production files, each restored byte for byte by sha256"

key-files:
  created:
    - database/migrations/2026_11_21_000001_create_walk_recordings_table.php
    - app/Models/WalkRecording.php
    - database/factories/WalkRecordingFactory.php
    - app/Icm/Walks/WalkGate.php
    - app/Icm/Walks/WalkGateEntry.php
    - app/Icm/Walks/WalkGateResult.php
    - tests/Feature/Icm/WalkGateTest.php
    - tests/Unit/Icm/WalkWordsTest.php
  modified:
    - app/Icm/FolderSync.php
    - app/Icm/SyncResult.php
    - app/Icm/Changelog.php
    - map/objects/folder/agent-file-and-version.md
    - tests/Feature/Walks/WalkHelpers.php
    - tests/Feature/Icm/WalkFileTest.php
    - docs/BLUEPRINT.md

key-decisions:
  - "A stale note is written for every stale walk whose recording was replayed; it drops 'It still ran and passed its checks.' for a walk that failed or needs a new recording"
  - "An unrecorded walk and a walk that errored are never stale (no recording to compare, no result fingerprint)"
  - "WalkGateResult scrubs every finished line: hidden characters marked, '!' removed, an em dash turned into a hyphen, whatever an error message or an owner-written name holds (Rule 2, Ian's rule 10)"
  - "WalkGate::run keeps the plan's ?IcmVersion $live parameter but does not read it: the lines take the live label from the caller"
  - "Test expectations build the live version's label from the live row ($live->label()), never the literal v1, so the cases hold on PostgreSQL where sequences continue"

patterns-established:
  - "seedRecording(walk, tape, fingerprint?, variant) stores a recording the way the gate reads it; a test that wants a stale one passes a fingerprint"

requirements-completed: [WALK-02]

coverage:
  - id: D1
    description: "A candidate goes live only when validation and every walk pass; a failed walk, an unrecorded one, one the recording no longer answers and one that errors each keep the previous version live, store a Rejected version and say so in the sync's errors"
    requirement: WALK-02
    verification:
      - kind: integration
        ref: "tests/Feature/Icm/WalkGateTest.php (activates a candidate whose walks pass; refuses a price the folder no longer has; refuses a time the candidate no longer offers; refuses a new walk with no recording; refuses a walk the recording no longer answers; fails closed when a walk stops with an error; says Nothing is live yet on a first sync)"
        status: pass
    human_judgment: false
  - id: D2
    description: "The owner form never holds a command, console wording or the word stale; Ian's form in changelog.md carries walks:record and the file name; no line holds an exclamation point or an em dash; every line marks hidden characters"
    requirement: WALK-02
    verification:
      - kind: unit
        ref: "tests/Unit/Icm/WalkWordsTest.php (every line form with and without a live version; owner lines free of command and stale; no exclamation point or em dash; hidden characters marked)"
        status: pass
    human_judgment: false
  - id: D3
    description: "A stale walk is replayed and never refused; the owner sees what any passing sync shows; changelog.md's Live entry and a Rejected entry from a sync whose walks ran carry one Ian line per stale walk; No changes and a validation refusal carry none"
    requirement: WALK-02
    verification:
      - kind: integration
        ref: "tests/Feature/Icm/WalkGateTest.php (lets a stale walk go live; carries a stale note on a rejected entry; names a stale walk that was refused without saying it passed; adds no walk line when nothing changed; adds no walk line when validation fails)"
        status: pass
    human_judgment: false
  - id: D4
    description: "Recordings live in walk_recordings keyed by walk path, script hash and variant; the gate reads only as_built; the migration uses portable builders"
    requirement: WALK-02
    verification:
      - kind: integration
        ref: "tests/Feature/Icm/WalkGateTest.php#reads only the as_built recording of a walk (D-06); refuses a walk whose customer turn changed; keeps a walk recorded when only its checks, persona or name change"
        status: pass
    human_judgment: false
  - id: D5
    description: "The migration and WalkGateTest run green on PostgreSQL in CI"
    verification: []
    human_judgment: true
    rationale: "Only the CI pgsql leg after Ian pushes can show it; recorded for end-of-phase UAT, never a pause"
  - id: D6
    description: "Sync Now runs the gate inline; the shipped example walk set finishes inside the page's request with no timeout"
    verification: []
    human_judgment: true
    rationale: "UI-SPEC loading backstop: timed at UAT after Ian's recording of the example walks (10-07)"

duration: 15min
completed: 2026-11-23
status: complete
commits: 5
plan_head_before: 73681125f0be05248a808f11b802cc7d986ff8cd
plan_head_after: 41a25b1a9b096d504e64afc22b564710824de381
---

# Phase 10 Plan 05: The walk gate in the sync Summary

**Every sync that would activate a candidate now replays each walk from its recording against it first; a failed, unrecorded, no-longer-answered or erroring walk keeps the previous version live, stores a Rejected version holding one plain owner line per walk, and gives changelog.md Ian's form with `walks:record` and a note for every stale recording.**

## Performance

- **Duration:** about 15 min of clock time by the start and end stamps (the mutation matrix ran in the background)
- **Started:** 2026-11-23T23:06:07Z
- **Completed:** 2026-11-23T23:21Z (verification done; SUMMARY written after)
- **Tasks:** 3 (Task 1 a tracer, Task 2 auto tdd, Task 3 docs)
- **Files:** 8 created, 7 modified

## Accomplishments

- `walk_recordings` (walk_path, script_hash, variant default as_built, fingerprint, payload json, recorded_at, unique on path, hash and variant) with `WalkRecording` and its factory; portable schema builders only.
- `WalkGate::run()` reads each walk's recording on the live connection before that walk's world opens, replays it with `WalkRunner::replay()`, and turns any Throwable into an Errored entry that is reported and refuses activation.
- `WalkGateResult` builds every line in one pure class: the owner form (page and notification), Ian's form (changelog only) and the stale notes, with the copy from the plan and UI-SPEC rulings 1 to 8.
- `FolderSync::sync()` calls the gate after the Unchanged return and before `DB::transaction`; `rejectedByWalks()` mirrors `reject()`; `recorded()` carries the two new `SyncResult` fields when changelog.md cannot be written.
- The agent card's two FolderSync citations moved with the code in the same commit (`:312` agent arm, `:310` offer arm).
- BLUEPRINT row 8 and two Built bullets (sections 8 and 10), each with its Affects line.

## Task Commits

1. **Task 1 (tracer): a recorded walk passes and the candidate goes live; a price the folder no longer has refuses it**
   - RED `fe55976` test(10-05): the sync walks the candidate before activation (WALK-02)
   - GREEN `ffd9d88` feat(10-05): the sync walks the candidate before it goes live (WALK-02)
2. **Task 2: unrecorded, needs a new recording, stale, error, nothing live, unchanged, validation-refused**
   - `e36af42` test(10-05): unrecorded, needs a new recording, stale and nothing live (D-04, D-05, D-27)
   - `4db6bf1` test(10-05): a refusal that opened a walk world still reads the live version (Pitfall 6)
   - no feat commit: see Deviations 1
3. **Task 3: BLUEPRINT** - `41a25b1` docs(10-05): BLUEPRINT says walk tests gate every activation (WALK-02)

**Plan metadata:** the docs commit that holds this file (SUMMARY, STATE, ROADMAP, REQUIREMENTS).

Tracer gate (Task 1, no `gate` attribute, automated-only verify, `end-of-phase`): the verify command was re-run after both commits (73 passed, `RESULT: PASS`), logged "Tracer verified end-to-end, expanding", and Task 2 started. No checkpoint.

## TDD Gate Compliance

Task 1: RED `fe55976` precedes GREEN `ffd9d88`. Task 2: the test commits `e36af42` and `4db6bf1` exist, but there is no `feat(10-05)` commit after them, because Task 1's GREEN already built every line form and every Task 2 case passed on its first run (Deviations 1). `gsd_run check tdd-red-evidence` was not run: Pest's console output is not one of its supported formats (the same gap as 10-02 to 10-04).

### RED evidence and semantic assessment

**Task 1 (`fe55976`):** `{"result":"failed","tests":35,"passed":31,"errors":4}`. The four errors are the three new refusal and activation cases and the forced WalkFileTest compile case, each `Class "App\Models\WalkRecording" not found` raised from `seedRecording()`. The fifth new case, `runs no walk when the candidate has none`, passed. Semantic assessment: the planned absence (no table, no model, no gate). A missing class hides test bugs, so after GREEN every new case was also mutation-checked (below); GREEN was `{"result":"passed","tests":73,"passed":73}`.

**Task 2 (`e36af42`):** 55 cases passed in the first full run, so there is no RED. Two cases failed on my own first draft of the tests (a trailing newline the rtrimmed entry does not have, and a hidden-character case that gave a stale walk an unrecorded entry); both were test bugs fixed before the commit.

### Passed on first run

Every case below passed the first time it ran against code that exists. For each, the code it covers was broken on purpose, the case seen red, and the file restored byte for byte (sha256 before and after identical; `git status --short app` empty).

Sha256 of the production files before and after every mutation round: `WalkGate.php` `2cb0c7a9ad0f7ce88e4d62ba952a8c1c14d4e29e972ba764cbbdf8702072b533`, `WalkGateResult.php` `4bf003c4458cc0b596a5e697c486f5f9943db0b8c05221ec7bcc26be86df595c`, `Changelog.php` `62cc681b6a2a7a139d5046d3e9a3a83526c950f7cd5cda6491ec1f1345c72670`, `FolderSync.php` `c217e4570c28b70f2f519b697be7c88b92f56ebbc95352d5daff45e041286278`, `ScratchWorld.php` `1ebd1c3e8163417e2332d7549e3a42eb7cdccf0d8b6016f460aa5a44ea8043bf`.

Task 1 (after GREEN):

| Case | What was broken | Red line |
|---|---|---|
| runs no walk when the candidate has none | `WalkGate::run` calls `replay()` before the empty-list return | `The class \App\Icm\Walks\WalkResult is marked readonly and its methods cannot be replaced` (the mocked runner was called) |
| activates a candidate whose walks pass; compile case of WalkFileTest | `WalkGateResult::refused()` always true | `Failed asserting that two variables reference the same object ... Rejected` (expected Activated) |
| refuses a price ...; refuses a time ... | `WalkGate::run` returns `none()` first | `Expected status code 1 but received 0.` |
| all four refusal and activation cases | recording lookup asks for variant `other` | `Expected status code 0 but received 1.` and lines reading `this walk has no recording yet` |

Task 2: every new case was killed by at least one of 35 mutants. The ones that matter most, by case:

| Case | Mutant (file) | Red |
|---|---|---|
| unrecorded owner and Ian lines | M1 wording changed, M3 owner line leaks the command, M14 Ian line loses the file name (WalkGateResult) | 9, 10 and 5 cases red |
| changed customer turn | M32 recording lookup ignores the script hash (WalkGate) | `Failed asserting that two variables reference the same object` (Activated instead of Rejected) |
| keeps a walk recorded when only checks change | M31 recording keyed on the whole file (WalkGate) | 16 cases red |
| needs a new recording, fresh and stale; Jev reading | M2 wording, M4 need always "model reply" (WalkGateResult) | 3 and 2 cases red |
| stale go-live; stale on a rejected entry; stale after a refusal | M6, M7 passed-sentence always or never, M15 stale never computed (WalkGate), M21, M22, M27, M29 (Changelog, FolderSync) | 3, 4, 6, 3, 2, 3 and 3 cases red |
| no walk when nothing changed; validation fails | M23, M24 gate run before the Unchanged return or the validation refusal (FolderSync) | 2 and 3 cases red |
| Nothing is live yet | M5 live clause ignores null (WalkGateResult) | 2 cases red |
| fails closed when a walk errors | M16 no catch, M17 no report, M12 message dropped | 1, 1 and 2 cases red |
| path order | M19 reversed (WalkGate) | 2 cases red |
| as_built only (D-06) | M18 variant ignored (WalkGate) | 1 case red |
| lines kept when changelog.md cannot be written | M28, M30 `recorded()` drops a field (FolderSync) | 2 and 1 cases red |
| no exclamation point or em dash; hidden characters | M9, M10, M11 (WalkGateResult) | 1 case red each |
| WalkWordsTest refuses / unrefused | M33 `refused()` false, M34 true | 17 and 22 cases red |
| Pitfall 6 | M35 `ScratchWorld` stops forgetting `ActiveConfig` on close | first draft SURVIVED (no world opened, the walk was unrecorded, and ids matched); case rewritten in `4db6bf1` to seed a recording and compare fingerprints; then `Failed asserting that 1 is identical to 3.` |

One equivalent mutant: removing `sort($paths, SORT_STRING)` in `WalkGate::run` changes nothing, because `FolderSync::compile()` already builds the walk index from files sorted by path; the sort is a defensive guard and the reversed-order mutant (M19) is what the ordering case kills.

## Cited lines and check_map

FolderSync.php is cited by `map/objects/folder/agent-file-and-version.md`. Task 1's feat commit `ffd9d88` moved the compile arms and re-cited the card in the same commit (`git show --name-only` lists both). After the change:

```
$ sed -n 312p app/Icm/FolderSync.php
                'agent' => $compiled['index']['agent'][$file->frontMatter['channel'] ?? $file->path] = $file->path,
$ sed -n 310p app/Icm/FolderSync.php
                'offer' => $compiled['index']['offer'][$file->frontMatter['slug'] ?? $file->path] = $file->path,
```

The card cites `FolderSync.php:312` (agent arm) and `:310` (offer arm). They were printed again after Task 2 and after Task 3 (no FolderSync change in either). `check_map.py` printed `RESULT: PASS` after Task 1 (before and after the re-cite), after Task 2 and after Task 3.

## Existing tests

- Re-run unedited: `HandBuiltRegistrySyncTest` 1 passed, `ChangelogTest` 12 passed, `SyncTest` 11 passed, `BusinessFolderPageTest` 32 passed. `git diff 7368112 --stat` on those four and on `tests/Pest.php` is empty.
- `WalkFileTest`: one added line (`require_once __DIR__.'/../Walks/WalkHelpers.php';`) and two `seedRecording(...)` lines in the compile case, before its `icm:sync` line. `git diff -U0 7368112` shows no removed line in `WalkFileTest.php` or `WalkHelpers.php`. `WalkHelpers.php` gained three `use` lines (WalkFingerprint, WalkRecording; sorted into the block) and the two helper functions at the end.
- The first case of WalkGateTest builds its heading from `exampleFolderFileCount() + 1`, never 30.

## Verification

- Plan `<verification>`: `php artisan test --compact tests/Feature/Icm tests/Feature/Walks tests/Feature/Rooms tests/Feature/Filament/BusinessFolderPageTest.php tests/Feature/FrontDesk/ReplyCapTest.php tests/Unit` gave `{"result":"passed","tests":1471,"passed":1471,"assertions":5419,"warnings":1}`. The one warning carries no detail in Pest's output; I did not chase it.
- Task 1 verify: 73 passed and `RESULT: PASS`. Task 2 verify (run as `tests/Feature/Icm tests/Unit/Icm/WalkWordsTest.php`): 378 passed and `RESULT: PASS`. Task 3 verify (the four greps): `VERIFY-OK`.
- `vendor/bin/pint --test` passes for the whole project.
- No test called a live model, Jev or a text channel; the suite's `Http::preventStrayRequests()` and the replay seams stayed in force. `walks:record` was not run.

## Decisions Made

See `key-decisions`. The two most worth Ian's eye: the scrub in `WalkGateResult::plain()` (Deviations 3) and the stale note on a refused walk (the shorter sentence).

## Deviations from Plan

**1. [Order of work] Task 2 has no production change and no feat commit**
- Task 1's GREEN needed every line form to pass its own tests (the failed form, the unrecorded and needs-new-recording forms for the Rejected row and changelog, the stale notes), so `WalkGateResult` was built in full there. Every Task 2 case then passed on its first run. Per Ian's rule 3 I did not invent a production change; I mutation-checked every case instead (above) and fixed the one weak case (Pitfall 6) in a test commit. The plan's Task 2 acceptance line "git log shows the test commit before the feat commit" cannot hold for Task 2; the Task 1 pair holds.

**2. [Plan command] Task 2's verify command cannot run as written**
- `php artisan test --compact tests/Feature/Icm/WalkGateTest.php tests/Unit/Icm/WalkWordsTest.php tests/Feature/Icm` fails with Pest's `TestCaseAlreadyInUse` (a file and its own directory are both named). I ran `tests/Feature/Icm tests/Unit/Icm/WalkWordsTest.php`, which covers the same files.

**3. [Rule 2 - missing critical] The scrub of exclamation points and em dashes**
- **Found during:** Task 1 (rule 10 and the threat register's T-10-16).
- **Issue:** a line holds an exception message or an owner-written name and failure words, any of which can hold `!` or an em dash, which no gate-produced string may.
- **Fix:** `WalkGateResult::plain()` marks hidden characters, deletes `!` and turns an em dash into a hyphen in every finished line. Pinned by `WalkWordsTest` (names, error text and failure words holding both) and mutants M9 and M10.
- **Files modified:** `app/Icm/Walks/WalkGateResult.php`. **Committed in:** `ffd9d88`.
- Flagged for Ian: it changes an owner's own character in a path, which the Loader already refuses for hidden characters but not for `!`.
- Superseded 2026-11-26 (Ian): the scrub was removed. `plain()` only marks hidden characters; owner values and error text are shown as written.

**4. [Interpretation] The rejected price case also carries a stale line**
- The plan's second case says the changelog entry holds the failure line and `- v1 stays live`. The front desk agent loads `business/services/` and `business/hours.md`, so breaking `ac-repair.md` or `hours.md` changes the fingerprint and the seeded recording is stale (ruling 5). The case asserts the whole entry exactly: failure line, the short stale line, then the live bullet. Both the failure line and the live bullet are as planned.

**5. [Interpretation] Test labels come from the live row**
- The plan writes `v1`, `v2` and `Live as v2`. Cases build the label from `$live->label()` and `$active->label()` so they hold on PostgreSQL, where sequences continue across tests.

**6. [Interpretation] Edge choices where the plan was silent**
- An unrecorded or errored walk is never stale. An empty exception message prints the exception's class name. A failure's words lose a trailing full stop before the closing clause. `WalkGate::run` accepts `?IcmVersion $live` as the plan's signature requires but does not read it. The migration file is named as planned (`2026_11_21_000001_...`, renamed from the generated name).

**7. [Extra cases, additions only]** `reads only the as_built recording`, `keeps a walk recorded when only its checks, persona or name change`, `says a Jev reading ...`, and two cases that the new `SyncResult` fields survive an unwritable changelog.md. WalkGateTest has 21 `it(` cases (the plan asks for 14).

**Total deviations:** 1 Rule 2 addition, 1 order-of-work difference, 1 unrunnable plan command, 4 interpretations. **Impact on plan:** none on scope; no existing test was weakened.

## Issues Encountered

- A first draft of two Task 2 cases was wrong (a trailing newline the entry does not have; a stale flag on an unrecorded entry). Both fixed before the commit.
- The first Pitfall 6 case was vacuous (see the table). Found by a break-on-purpose of `ScratchWorld`, fixed in `4db6bf1`.

## Known Stubs

None.

## Threat Flags

None. The gate reads one new table on the live connection and writes nothing but the Rejected version row it was already allowed to write. T-10-14 to T-10-19 hold: the gate sits before `DB::transaction` (mutants M23 and M24 prove its place), owner and Ian lines are built apart (M3), lines are scrubbed (M9 to M11), the world forgets `ActiveConfig` (M35), and a walk that errors refuses (M16).

## Hand checks for end-of-phase UAT

- After Ian pushes the branch: the CI pgsql leg is green for `WalkGateTest` and the `walk_recordings` migration (the migration uses portable builders and no raw SQL).
- UI-SPEC loading backstop: Sync Now runs the gate inline; time the shipped example walk set inside the page request after Ian's recording (10-07).

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- 10-06 (`walks:record`) writes `walk_recordings` rows keyed as `seedRecording()` keys them (path, `WalkFingerprint::scriptHash()`, `as_built`, `WalkFingerprint::forActiveConfig()`, `WalkTape::toPayload()`).
- 10-11 proves the page and the Sync Now notification render the Rejected row's errors and the sync's errors, and prints Ian's lines and the stale notes in `icm:sync`; the lines they read are filled here.
- Nothing blocks.

## Self-Check: PASSED

- All eight created files exist (`[ -f ]`).
- Commits `fe55976`, `ffd9d88`, `e36af42`, `4db6bf1`, `41a25b1` are ancestors of HEAD; `git rev-list --count 73681125..HEAD` is 5, matching `commits: 5`.
- The plan verification command passed after the last code and docs commit (1471 passed).

---
*Phase: 10-walk-tests-and-the-gate*
*Completed: 2026-11-23*
