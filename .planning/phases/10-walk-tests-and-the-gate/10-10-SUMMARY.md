---
phase: 10-walk-tests-and-the-gate
plan: 10
subsystem: icm-walks
tags: [browser-test, accessibility, axe, business-folder, setup-docs, map, d17, d11, pest]

requires:
  - phase: 10-walk-tests-and-the-gate
    provides: "10-05: the walk lines on the Business Folder page (the Last sync refusal list); 10-06 to 10-09: walks:record, walks:report, --calibration, --export, --variant"
provides:
  - "tests/Feature/Filament/BusinessFolderWalkLinesBrowserTest.php: the walk lines from real syncs read in order after the heading and status line, add no tab stop, fit 375 CSS px and pass axe (color-contrast off, Phase 11)"
  - "docs/SETUP.md section 14: what a walk is, when to run walks:record (D-26), the commands as built, the eight-step decision run (D-17), the follow-up approved-values plan"
  - "answer-inbound and message-and-verdict map sentences for the walk gate (10 D-27, D-02)"
  - "WINDOWS.md entries 101 to 105 (unrun-verify) for the end-of-phase hand checks and Ian's live run"
affects: [Ian's live decision run (D-17 steps 2 and 3), the follow-up approved-values plan (D-17 step 4), end-of-phase UAT]

actuals:
  tokens: 40000
  tasks: 3
  commits: 3

key-files:
  created:
    - tests/Feature/Filament/BusinessFolderWalkLinesBrowserTest.php
  modified:
    - docs/SETUP.md
    - map/processes/answer-inbound.md
    - map/objects/messages/message-and-verdict.md
    - .planning/WINDOWS.md

key-decisions:
  - "The browser test keeps its axe call local (businessFolderWalkLinesAxe) in ContactScreenKeyboardTest's shape, so tests/Pest.php's pageAxeReport() is unchanged"
  - "Section 14 writes every command in the path form --calibration=tests/Fixtures/calibration/calibration-set.json --runs=3 (Ian's instruction, superseding the plan's --calibration --runs=3), and each --variant command in full for the two D-18 files"

requirements-completed: []
# WALK-01 and WALK-02 are not marked complete here on Ian's instruction; the orchestrator owns tracking.

duration: not timed
completed: 2026-11-27
status: complete
commits: 3
plan_head_before: abba127b3a5fab530498d3aaddad7721a5f93056
plan_head_after: c7d82c19ccc6003d9b816a2e49a3bdfc6fa6031f

coverage:
  - id: D1
    description: "The walk lines from real syncs (a failed walk, an unrecorded walk, a walk-file refusal) are plain paragraphs read in order after the Last sync heading and the Rejected status line, at desktop width and 375 CSS px"
    requirement: WALK-02
    verification:
      - kind: browser
        ref: "tests/Feature/Filament/BusinessFolderWalkLinesBrowserTest.php#reads the walk lines in order after the heading and the status line; reads a walk-file refusal the same way"
        status: pass
    human_judgment: false
  - id: D2
    description: "The Last sync section gains no link, button, field or tabindex element, so no new tab stop"
    requirement: WALK-02
    verification:
      - kind: browser
        ref: "tests/Feature/Filament/BusinessFolderWalkLinesBrowserTest.php#adds no tab stop to the Last sync section"
        status: pass
    human_judgment: false
  - id: D3
    description: "At 375 CSS px the page has no sideways scroll with walk lines showing"
    requirement: WALK-02
    verification:
      - kind: browser
        ref: "tests/Feature/Filament/BusinessFolderWalkLinesBrowserTest.php#has no sideways scroll at 375 CSS px with walk lines showing"
        status: pass
    human_judgment: false
  - id: D4
    description: "axe (WCAG 2.2 AA tags and target-size, color-contrast off with the reason written in the test) finds no violation with walk lines showing, at both widths, for both kinds of refusal"
    requirement: WALK-02
    verification:
      - kind: browser
        ref: "tests/Feature/Filament/BusinessFolderWalkLinesBrowserTest.php#finds no accessibility violation with walk lines showing"
        status: pass
    human_judgment: false
  - id: D5
    description: "docs/SETUP.md section 14 gives Ian the exact commands for the recording and the decision, and says the phase closes only after the follow-up approved-values plan"
    verification:
      - kind: command
        ref: "grep checks in Task 2 acceptance (section heading, walks:report, drop the mechanics, the path-form calibration command, no exclamation point against main)"
        status: pass
    human_judgment: true
    rationale: "The commands are written from the source and were never run, because running them reaches the live model and Jev (WINDOWS.md 105). Ian's own run is the proof."
  - id: D6
    description: "The map knows the walk gate: answer-inbound names WalkRunner and the replay classifier is named on the message card; check_map.py passes"
    verification:
      - kind: command
        ref: "python3 ~/Projects/cartographer/tools/check_map.py ~/Projects/ops-platform --extra-root examples/example-hvac prints RESULT: PASS"
        status: pass
    human_judgment: false
  - id: D7
    description: "STATE.md holds Phase 10's close condition; the D-22 to D-26 rulings are in place; COVERAGE.md is one line; config/gate.php and the two Booking constants are unchanged"
    verification:
      - kind: command
        ref: "Task 3 verify command, exit 0 (see Task 3 below)"
        status: pass
    human_judgment: false
  - id: D8
    description: "Keyboard-only and VoiceOver walk, phone walk, Sync Now timing after Ian's recording, both CI legs after the push"
    verification: []
    human_judgment: true
    rationale: "Hand checks are never a pause. Recorded as WINDOWS.md entries 101 to 104 (unrun-verify) for end-of-phase UAT."
---

# Phase 10 Plan 10: The walk lines on the Business Folder page, Ian's decision run and the walk gate in the map Summary

**A Pest browser test that reads the Business Folder page's Last sync walk lines (from real syncs) in order, finds no added tab stop, no sideways scroll at 375 CSS px and no axe violation; docs/SETUP.md section 14 with the exact commands for Ian's recording and his one decision; two map sentences for the walk gate.**

## Performance

- **Tasks:** 3 (Task 1 a tracer, written as a pin test; Tasks 2 and 3 auto)
- **Files:** 1 created, 4 modified; `git diff --stat abba127..HEAD` is 432 insertions and 5 deletions (the 5 deletions are WINDOWS.md header counts and the two map lines extended)
- **Commits:** 3 (measured from `abba127`)

## Accomplishments

- `BusinessFolderWalkLinesBrowserTest` (11 test runs from 5 cases; each case over desktop width 1280 and 375 CSS px where a width applies). World one is two real syncs of a real folder: `tests/a-new.md` with no recording and `tests/b-price.md` with a seeded recording that fails once the ac-repair price policy is `none`. World two is a walk file naming a booking time with no start (D-10). The test reads the section's heading, each paragraph's text in document order, whether the heading comes first in the document, and counts links, buttons, fields and tabindex elements inside it.
- Every "stays live" clause is built from the database: `$version = activateExampleFolder();` then `"{$version->label()} stays live."`. No version label is typed.
- `businessFolderWalkLinesAxe(Page): array` is local to the test file. It uses the WCAG 2.2 AA tags plus `target-size` and `'color-contrast' => ['enabled' => false]`; the docblock says the inherited `#dc2626` on refusal lines falls below 4.5:1 on Filament's dark section background, a known gap Ian made a Phase 11 must-fix on 2026-11-21 (10-UI-SPEC "Out of scope").
- docs/SETUP.md section 14 "Recording the walks and settling the bars (Ian)" (130 lines, no exclamation point, no em dash). Every command is written to match today's `WalksRecord` and `WalksReport` signatures: no placeholder in any command, `--calibration=` always with the path, both D-18 wording files named in full in every `--variant` command, and a plain statement that the calibration run writes Jev's live scores into the committed calibration fixture, which like the exported walk fixtures is committed only with the follow-up approved-values plan.
- Map: the **Hits** paragraph of `map/processes/answer-inbound.md` says the walk gate (`app/Icm/Walks/WalkRunner.php`) drives these steps against a candidate in a world of its own (10 D-27); the **Verdict** bullet of `map/objects/messages/message-and-verdict.md` says `app/SendRule/ReplayClassifier.php` stands in for Jev inside the walk gate only (10 D-02). `check_map.py` prints RESULT: PASS. No map card cites `docs/SETUP.md` (`grep -rln "docs/SETUP" map` finds nothing).

## Task Commits

1. **Task 1: the walk lines browser test** - `404da25` (test)
2. **Task 2: SETUP.md section 14 and the two map sentences** - `970517d` (docs)
3. **Windows ledger entries 101 to 105 (unrun-verify)** - `c7d82c1` (docs)

Task 3 is verification only and made no commit.

## Task 1: the test is a pin, and each case was proved able to fail

There was no failing commit to make: the page markup already existed from 10-05. The first run was red for a harness reason, not an assertion: the Pest browser plugin starts its browser only for a test whose own body calls `visit()`, and the first draft called it inside a helper (every run failed with "WebSocket client is not connected"). Moving `visit()` into each test body fixed it; the page code was never touched. Everything after that passed on its first real run (11 of 11), so every case went through the break-on-purpose protocol, one break at a time, only in the test file, run with `--filter`, then restored byte for byte from a copy.

File touched for every break: `tests/Feature/Filament/BusinessFolderWalkLinesBrowserTest.php`. sha256 before every break and after every restore was the same: `d92b116317263a09a6b13ec0b635c2adb4e52c035a1360a31d07a724dba0248a`. After the last restore the file still hashes to that value, and the committed file is that file. Line numbers below are the line of the broken assertion (the file as committed).

| Case | Break | Red proof | Rows red |
|---|---|---|---|
| reads the walk lines in order | 1a: first walk line expects `tests/z-new.md` | line 151 "Failed asserting that two strings are identical" (expected z-new, actual a-new) | 2 of 2 |
| | 1b: the 375 width check expects `toBeFalse` | line 155 "Failed asserting that true is false" | 1 of 2 (the 375 row only) |
| | 1c: heading-first expects `toBeFalse` | line 148 "Failed asserting that true is false" | 2 of 2 |
| | 1d: status line pattern expects `Accepted` | line 150 "'Rejected · Nov 27, 1:25 pm' matches PCRE pattern /^Accepted ..." | 2 of 2 |
| | 1e: second walk line expects `turn 2` | line 152 identical-strings failure | 2 of 2 |
| reads a walk-file refusal the same way | 2a: expects `script item 3` | line 175 "starts with ..." failure (actual says script item 2) | 2 of 2 |
| | 2b: the 375 width check expects `toBeFalse` | line 178 "Failed asserting that true is false" | 1 of 2 (the 375 row only) |
| | 2c: expects 3 paragraphs | line 173 "actual size 2 matches expected size 3" | 2 of 2 |
| | 2d: status line expects `Accepted` | line 174 "'Rejected · Nov 27, 1:25 pm' matches PCRE pattern /^Accepted · /" | 2 of 2 |
| adds no tab stop | 3: expects 1 stop | line 194 "Failed asserting that 0 is identical to 1" | 2 of 2 |
| has no sideways scroll at 375 | 4a: window width expects 376 | line 209 "Failed asserting that 375 is identical to 376" | 1 of 1 |
| | 4b: the fit check expects `toBeFalse` | line 210 "Failed asserting that true is false" | 1 of 1 |
| finds no accessibility violation | 5b: an `<img src="x.png">` with no alt text inserted before axe runs | assertClean line, `[{"id":"image-alt","impact":"critical","help":"Images must have alternative text",...}]` "Failed asserting that an array is empty" | 4 of 4 |

After each restore the case was green again (the full file was re-run green, 11 of 11, after the last restore and again before the commit).

One break did not go red and is reported as a finding, not a proof: **5a, switching `color-contrast` on, still passed 4 of 4.** In the headless browser's light theme, `#dc2626` on white is 4.83:1, so axe finds no contrast failure. The plan's stated gap (below 4.5:1) is on Filament's dark section background, which this test never renders. The exemption is therefore not hiding anything in this test today, and it is kept as the plan says, with the reason written in the docblock. Proof that axe can fail came from 5b instead. Phase 11 owns the dark-mode contrast fix and a dark-mode check.

## Task 2: STATE.md check and the acceptance results

- `.planning/STATE.md` was verified and not edited (`git status` never showed it modified). The close-condition bullet is at **`.planning/STATE.md:397`**, inside `### Blockers/Concerns` (line 370) and before `### Quick Tasks Completed` (line 400). The plan said line 368 at plan time; it has moved with later edits, and it is in the section.
- `sed -n '/### Blockers\/Concerns/,/### Quick Tasks/p' .planning/STATE.md | grep -q 'Phase 10 cannot close until the follow-up approved-values plan is executed'` succeeded.
- `n=$(git show --name-only --format= HEAD)` on `970517d` does not list `.planning/STATE.md`.
- check_map.py: `RESULT: PASS`.
- Exclamation point count of `docs/SETUP.md` is 0 and equals main's (0). Section 14 holds 0 exclamation points and 0 em dashes.
- **Plan acceptance superseded by Ian's instruction:** the plan's grep `grep -q 'walks:record --calibration --runs=3' docs/SETUP.md` is replaced, because `--calibration=` takes a path. The grep used instead: `grep -qF 'walks:record --calibration=tests/Fixtures/calibration/calibration-set.json --runs=3' docs/SETUP.md` succeeds. The other acceptance greps (`walks:report`, `drop the mechanics`, `WalkRunner.php`, `ReplayClassifier.php`, the section heading) all succeed.

## Task 3: the D-22 to D-26 rulings and the D-17 split (verification only)

The Task 3 verify command exits 0. Where each ruling was found:

- **D-22:** `docs/SPEC.md:227` (the agents row: team "meant to read `escalate_to` in `agents/front-desk.md`; nothing reads it yet") and `app/Rooms/Team/TeamServiceProvider.php:15` ("escalate_to in agents/front-desk.md (nothing reads it yet; parked with the Owner Loop, backlog 999.5, 10 D-22)").
- **D-23:** `map/CLAUDE.md:30` (`intake_answers.icm_version_id` parked on BLUEPRINT's before-first-client list, 10 D-23).
- **D-24:** `map/CLAUDE.md:39` (the handled / claimed / dispatched collision line names `claim_token`).
- **D-25:** `docs/BLUEPRINT.md:584` (the operator notice on the before-first-client list).
- **D-26:** `.planning/ROADMAP.md:476` ("Before the first sync on any new instance, run `walks:record` for each business, or every walk refuses as unrecorded"). The plan cited ROADMAP.md:445; the note moved down with later ROADMAP edits.
- COVERAGE.md is one line ("No external API integration: ..."). `git diff main -- config/gate.php` is empty; the diff of `app/Ai/FrontDeskPrompt.php` against main changes no `public const string BOOKING_` line. `git diff main --stat -- app/Filament resources examples/example-hvac/rules/triage.md` prints nothing. Every walk test file loads `WalkHelpers.php` and runs under the suite's `Http::preventStrayRequests()`; no test passes `allowHttp`.

## Verification

- `php artisan test --compact tests/Feature/Filament/BusinessFolderWalkLinesBrowserTest.php tests/Feature/Filament/BusinessFolderPageTest.php tests/Feature/Filament/ContactScreenKeyboardTest.php tests/Feature/Booking/BookingPageBrowserTest.php`: 73 tests, 73 passed, 612 assertions, none skipped. One earlier run of the same four files had two `BookingPageBrowserTest` cases ("normalizes a non-breaking space in a name" and one other) time out at Playwright's 5000 ms; they passed on the rerun and in the full suite. They are not files this plan touched; noted here as a flake under load, not fixed.
- The two files for Task 1 alone (`BusinessFolderWalkLinesBrowserTest` and `BusinessFolderPageTest`): 46 tests, 46 passed.
- Full suite `php artisan test --compact` on SQLite: 3713 tests, 3713 passed, 27876 assertions.
- `vendor/bin/pint --test`: passed. `check_map.py`: RESULT: PASS.
- The pgsql leg was not run (Postgres is not available here); recorded as WINDOWS.md 104.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] The first draft started the browser from a helper**
- **Found during:** Task 1
- **Issue:** The Pest browser plugin only starts a browser for a test whose own body calls `visit()`. All 11 runs failed with "WebSocket client is not connected".
- **Fix:** Each test body calls `visit()` itself; the helper only waits for the section and returns the page.
- **Files modified:** tests/Feature/Filament/BusinessFolderWalkLinesBrowserTest.php (before its first commit)

### Instruction overrides applied (not deviations)

- Ian's `--calibration=<path>` instruction replaced the plan's `--calibration --runs=3` text and acceptance grep (above).
- Ian's rule that the orchestrator owns tracking: STATE.md, ROADMAP.md and REQUIREMENTS.md were not edited, no `state.*`, `roadmap.update-plan-progress` or `requirements.mark-complete` command ran, and WALK-01 and WALK-02 are not marked complete (`requirements-completed` is left empty above).

**Total deviations:** 1 auto-fixed (Rule 3, test harness). **Impact:** none on scope; no page code changed.

## Flagged for Ian (not ruled on here)

- **Section 14, step 5, the variant exports.** The plan says "(5) `php artisan walks:record --export=tests/Fixtures/walks`, and the same with each --variant". I wrote the literal form, with no walk paths, and said so in the text: each variant export plays every walk in the folder with that wording, not only the three walks of step 3. If Ian wants only the three walks, he adds the three paths to those two commands. The plan did not settle it.
- **The contrast exemption** does not trip in light mode (finding under Task 1). The dark-mode gap the plan describes is real in principle but is not exercised by this test.

## Known Stubs

None created by this plan. The existing placeholder fixtures (WINDOWS.md 96, 98, 99) stand, replaced by Ian's live run.

## Threat Flags

None. No new endpoint, auth path or file access; the test and docs add no trust boundary.

## Hand checks (end-of-phase UAT, never a pause)

Recorded in WINDOWS.md as unrun-verify entries 101 to 105 (commit `c7d82c1`):

1. Keyboard-only and VoiceOver walk of the Business Folder page over a rejected sync with a failed walk line and an unrecorded line (101).
2. Sync Now on the example folder after Ian's recording: time the gate with the shipped walk set; the page shows Filament's loading state and no timeout (102).
3. Phone walk of the Business Folder page with walk lines: no sideways scroll; looks-only findings to `.planning/todos/pending/2026-10-17-phone-design-pass.md` (103).
4. After Ian pushes the phase branch, both CI legs (sqlite and pgsql) green, browser tests included (104).
5. Ian's own run of section 14's commands against the live model and Jev (105).

## Ian's next step (after execution, not an executor task)

Run everything from the repository root, with `ICM_PATH` on the example folder or unset. The full text, with what each command does, is `docs/SETUP.md` section 14. In order:

1. Write the two D-18 Booking wording files, outside the business folder: `tests/Fixtures/booking-wording/say-what-the-words-are-for.json` (name `say-what-the-words-are-for`) and `tests/Fixtures/booking-wording/drop-the-mechanics.json` (name `drop-the-mechanics`). Each is JSON with `name`, `booking_online` (holding `{services}` exactly once) and `booking_visit_booked`; `tests/Fixtures/booking-wording/test-variant.json` shows the shape.
2. `php artisan walks:record` (today's Booking text, the baseline).
3. `php artisan walks:record tests/booked-time-question.md tests/link-again.md tests/dana.md --variant=tests/Fixtures/booking-wording/say-what-the-words-are-for.json` and the same with `--variant=tests/Fixtures/booking-wording/drop-the-mechanics.json`.
4. `php artisan walks:record --calibration=tests/Fixtures/calibration/calibration-set.json --runs=3` (writes Jev's live scores into the committed calibration fixture).
5. `php artisan walks:record --export=tests/Fixtures/walks`, then `php artisan walks:record --export=tests/Fixtures/walks --variant=tests/Fixtures/booking-wording/say-what-the-words-are-for.json`, then the same with `drop-the-mechanics.json`. This records again and replaces the rows from steps 2 and 3, so the report reads the same recording the exported fixtures hold.
6. `php artisan walks:report`, then again with `--bar` for each number under consideration, for example `php artisan walks:report --bar=complaint_max_yes=0.25`.
7. Decide, together, against this one recording: the bars (or a `rules/triage.md` reword for any check where the report prints D-13's stop) and the winning Booking wording.
8. Then plan and execute the follow-up approved-values plan (test-first, commits the refreshed fixtures with the values and the wording, asserts D-14). Phase 10 closes after it, and not before. Do not commit the exported fixtures or the calibration set on their own: under today's bars the plain walks may fail and CI would go red.

End-of-phase UAT hand checks: the four listed under "Hand checks" above (keyboard and VoiceOver walk, Sync Now timing, phone walk, both CI legs).

## Self-Check: PASSED

- `tests/Feature/Filament/BusinessFolderWalkLinesBrowserTest.php`, `docs/SETUP.md`, `map/processes/answer-inbound.md`, `map/objects/messages/message-and-verdict.md`, `.planning/WINDOWS.md` exist and carry the changes above.
- Commits `404da25`, `970517d` and `c7d82c1` are ancestors of HEAD on `gsd/phase-10-walk-tests-and-the-gate`; `git rev-list --count abba127..HEAD` was 3 before this SUMMARY.
- Task acceptance criteria re-run: all pass (Task 1 greps and run; Task 2 greps, STATE.md section check, no STATE.md in the commit, exclamation count equals main; Task 3 verify command exit 0).
