---
phase: 10-walk-tests-and-the-gate
plan: 08
subsystem: icm-walks
tags: [walk_test, fixture, example_walks, weekday_sweep, bars_in_ci, tdd, pest]

requires:
  - phase: 10-walk-tests-and-the-gate
    provides: "10-07: layExampleWalks(), danaFakes(), seedRecordingFromFixture(), stripWalkFiles(), Dana's placeholder fixture and the break-on-purpose evidence format"
  - phase: 10-walk-tests-and-the-gate
    provides: "10-03 to 10-06: WalkRunner::replay(), the gate, walks:record --export, FakeClassifier queueReadings() and queueAnswers()"
provides:
  - "Eight walk files beside Dana in examples/example-hvac/tests/: plain first texts, booked customer asks the time, link again, price, discount, promise, complaint, asks for a person (D-12, D-19)"
  - "Eight committed placeholder recording fixtures in tests/Fixtures/walks/ (source placeholder, fingerprint null), each made with the suite's fakes by a throwaway Pest test"
  - "exampleWalkNames() and exampleWalkFakes() test helpers; ExampleWalksTest (32 cases)"
  - "CI proof that the whole set passes the gate, holds on every weekday of 2026-11-30 to 2026-12-06, matches its fixtures by script hash, is fictional and plain, and is judged by the bars read at test time"
  - "BLUEPRINT knowledge area Built bullet for the walk set, with its Affects line"
affects: [10-09 report (flags placeholder fixtures), 10-10 docs/SETUP.md live run, Ian's live walks:record --export run]

actuals:
  tokens: 21000
  tasks: 2
  commits: 5

tech-stack:
  added: []
  patterns:
    - "Every example walk has one scripted-fakes branch in exampleWalkFakes(); a walk that fails under its fakes is fixed in the fakes, never by loosening the walk"
    - "A bar case lays all nine walks and nine fixtures, sets one config bar, runs icm:sync, and asserts the refusal line with the live label built from the database"
    - "A recording that hands off on the reading holds no model reply for that turn, so a raised bar makes the replay need one and the gate says the walk needs a new recording"

key-files:
  created:
    - examples/example-hvac/tests/plain-first-texts.md
    - examples/example-hvac/tests/booked-time-question.md
    - examples/example-hvac/tests/link-again.md
    - examples/example-hvac/tests/price-question.md
    - examples/example-hvac/tests/discount-question.md
    - examples/example-hvac/tests/promise-question.md
    - examples/example-hvac/tests/complaint.md
    - examples/example-hvac/tests/asks-for-person.md
    - tests/Fixtures/walks/plain-first-texts.json
    - tests/Fixtures/walks/booked-time-question.json
    - tests/Fixtures/walks/link-again.json
    - tests/Fixtures/walks/price-question.json
    - tests/Fixtures/walks/discount-question.json
    - tests/Fixtures/walks/promise-question.json
    - tests/Fixtures/walks/complaint.json
    - tests/Fixtures/walks/asks-for-person.json
    - tests/Feature/Walks/ExampleWalksTest.php
  modified:
    - tests/Feature/Walks/WalkHelpers.php
    - docs/BLUEPRINT.md
    - .planning/WINDOWS.md

key-decisions:
  - "The plain walk's turn 1 reading carries complaint 0.05 at confidence 0.97: above every default (the words checks reach 0.0117), under the shipped 0.15 bar, distinct from Dana's 0.04, so the lowered-bar case (0.03) trips on the walk's own recorded score (Ian, 2026-11-26, rule 7a)"
  - "The person walk's customer sentence avoids the AsksForPerson keyword list so Jev's reading decides (Ian, 2026-11-26, Option A)"
  - "The person-bar case asserts the gate's NeedsRecording line, not the plan's 'not handed to the owner' line (Ian, 2026-11-26)"
  - "Every label in an expectation is built from the database with label(); the file count is exampleFolderFileCount() + count(exampleWalkNames())"

patterns-established:
  - "Fixture helper names stay unique across the Pest process: exampleWalksReplay(), exampleWalksTurn(), exampleWalksNewestChangelogEntry(), exampleWalksFile()"

requirements-completed: [WALK-02]

coverage:
  - id: D1
    description: "The example business ships Dana plus eight walks (plain first texts, booked time question, link again, price, discount, promise, complaint, asks for a person); the full set, laid in with its fixtures, passes the gate and the folder goes live"
    requirement: WALK-02
    verification:
      - kind: integration
        ref: "tests/Feature/Walks/ExampleWalksTest.php#ships the D-12 walk set; every shipped walk passes the gate from its fixture (D-12)"
        status: pass
    human_judgment: false
  - id: D2
    description: "The booked customer's time question sends on its own, labeled scheduling, with the visit line; both link requests send on their own, labeled scheduling, with the booking link"
    requirement: WALK-02
    verification:
      - kind: integration
        ref: "tests/Feature/Walks/ExampleWalksTest.php#the booked customer's time question sends on its own with the visit line (D-19); both link requests send on their own with the booking link (D-19)"
        status: pass
    human_judgment: false
  - id: D3
    description: "The price, discount, promise and complaint walks hold the reply for the owner (price also sends the owner's price sentence, complaint alerts the owner) and the person walk hands the conversation to the owner with an alert"
    requirement: WALK-02
    verification:
      - kind: integration
        ref: "tests/Feature/Walks/ExampleWalksTest.php#the risky walks hold the reply for the owner (D-12); the price walk also sends the owner's own price sentence (D-12); the complaint walk alerts the owner (D-12); the person walk hands the conversation to the owner and alerts them (D-12)"
        status: pass
    human_judgment: false
  - id: D4
    description: "Every walk that names no start passes on each of the seven days 2026-11-30 to 2026-12-06 at 07:00 Chicago; only Dana and the discount walk name a start"
    requirement: WALK-02
    verification:
      - kind: integration
        ref: "tests/Feature/Walks/ExampleWalksTest.php#every walk with no start passes on each weekday (Pitfall 5); names a start only in the walk that depends on a dated offer (Pitfall 12)"
        status: pass
    human_judgment: false
  - id: D5
    description: "Every fixture's script hash equals its walk's customer turns and its source is placeholder or live; every walk file is fictional and plain with nine distinct personas"
    requirement: WALK-02
    verification:
      - kind: unit
        ref: "tests/Feature/Walks/ExampleWalksTest.php#matches every fixture to its walk's customer turns (D-05 in CI); keeps every example walk fictional and plain (D-12)"
        status: pass
    human_judgment: false
  - id: D6
    description: "Today's bars decide in CI: asks_for_person_min_yes 0.99 refuses the person walk and complaint_max_yes 0.03 refuses the plain walk at turn 1 with no re-record, keeping the live version live"
    requirement: WALK-02
    verification:
      - kind: integration
        ref: "tests/Feature/Walks/ExampleWalksTest.php#judges the person walk by today's asks_for_person bar (Ian's D-02 ruling); judges a plain walk by today's complaint bar (Ian's D-02 ruling)"
        status: pass
    human_judgment: false
  - id: D7
    description: "CI pgsql leg for ExampleWalksTest and the full suite after Ian pushes the branch; Ian's live walks:record --export that replaces the eight placeholder fixtures"
    verification: []
    human_judgment: true
    rationale: "Postgres is not available here, and the live model and Jev answer only in Ian's run. Recorded as WINDOWS.md entries 97 (unrun-verify) and 98 (stub), never a pause."

duration: 40min
completed: 2026-11-26
status: complete
commits: 5
plan_head_before: 553e8eb04d6aa17d81b3dce0d7f4b24a0142f421
plan_head_after: 0a7f8969ab89e637861ea3365ef1d26a5319115c
---

# Phase 10 Plan 08: The example business's gating walks Summary

**The example business now ships Dana plus eight gating walks (plain first texts, booked time question, link again, price, discount, promise, complaint, asks for a person) with eight committed placeholder fixtures that CI replays against the bars read at test time: the set passes the gate, holds on every weekday of the week of 2026-11-30, matches its fixtures by script hash, and a raised asks_for_person bar or a lowered complaint bar refuses the matching walk with no re-record.**

## Performance

- **Duration:** about 40 min across two turns (RED commit 2026-11-26 19:40 local, last code commit 19:59, then the full suite)
- **Tasks:** 2 (Task 1 a tracer, Task 2 auto tdd)
- **Files:** 17 created (8 walks, 8 fixtures, ExampleWalksTest), 3 modified (WalkHelpers.php, BLUEPRINT.md, WINDOWS.md)

## Accomplishments

- Eight walk files as the plan words them (front matter in Dana's style, one plain sentence each, no `!`, no em dash, no phone number, nine distinct invented personas). Only `discount-question.md` names a start ("2026-12-01 10:00", inside the first-visit promotion that ends 2026-12-31).
- `exampleWalkNames()` (nine names in path order) and `exampleWalkFakes()` (one scripted branch per walk) were appended to `tests/Feature/Walks/WalkHelpers.php`; 0 deletions.
- Eight placeholder fixtures, each exported once by a throwaway Pest test with the walk's fakes and `Http::preventStrayRequests()` on (asserted with `Http::preventingStrayRequests()` inside it), then deleted. After export only the three textual replacements 10-07 made were applied (`"fingerprint"` to null, `"source"` to `"placeholder"`, the note line with Dana's exact text). No payload was hand-typed.
- `ExampleWalksTest` (32 cases): the exact shipped set; the full set passing the gate (`Live as {label} (` + `exampleFolderFileCount() + count(exampleWalkNames())` + ` files)`, no stale line, no rejected version); the booked time question and both link requests replayed from fixtures; the four held walks, the price sentence, the complaint alert and the person handoff; the weekday sweep (7 dates); the starts check; the D-05 hash check for all nine; the two bar cases; and the fictional-and-plain check.
- BLUEPRINT section 8 Built bullet ("D-02, D-12, D-19") with its Affects line: knowledge, customer communication, safety and compliance.

## Task Commits

1. **Task 1 (tracer): the full example walk set validates and goes live from the committed fixtures**
   - RED `a7233a9` test(10-08): the example walk set passes the gate (D-12, D-19)
   - GREEN `f825f3d` feat(10-08): the example business's gating walks (D-12, D-19)
2. **Task 2: the set holds every weekday, matches its fixtures, is fictional and is judged by the bars of the moment**
   - RED `a402304` test(10-08): the walk set holds every weekday and follows today's bars
   - GREEN `6cdcd02` test(10-08): the walk set follows today's bars in CI (D-02, D-12) (the BLUEPRINT bullet)
3. **Hand check ledger** `0a7f896` docs(10-08): record the CI pgsql hand check and the eight placeholder fixtures in the windows ledger

**Plan metadata:** the docs commit that holds this file (SUMMARY, STATE, ROADMAP, REQUIREMENTS).

Tracer gate (Task 1, no `gate` attribute, automated-only verify, `end-of-phase`): the verify command was re-run after the feat commit (50 passed) and logged "Tracer verified end-to-end, expanding". No checkpoint.

## Deviations from Plan

**1. [Ruling-driven] The person walk's customer sentence**
- **Plan's text:** "I want to talk to a real person, not a bot."
- **New text:** "I would rather deal with a human being than a bot." The checks stay [handed to the owner, owner alerted]; the fake gives turn 1's reading asks_for_person 0.95.
- **Why:** "real person" is on `AsksForPerson::PHRASES`, and `RunFrontDesk::handle()` hands the customer off on that keyword before Jev's reading is read (`RunFrontDesk.php:226`), so the walk would be handed off at any bar and the asks_for_person bar case could never refuse it. `AsksForPerson::inText()` was checked false for the new sentence, which holds no `!`, no em dash and no phone number.
- **Authority:** Ian, 2026-11-26, Option A.

**2. [Ruling-driven] The person-bar case asserts the gate's NeedsRecording line**
- **Plan's wording (and rule 7b):** the sync is refused with "tests/asks-for-person.md, turn 1: the conversation was not handed to the owner. {label} stays live."
- **New wording:** `rejectedErrors()` contains "tests/asks-for-person.md: this walk needs a new recording. Recording it is Ian's step. {$live->label()} stays live." (label read from the database), the sync exits 1 and the live version stays live. The bar stays 0.99.
- **Why:** the recording hands the customer off on the reading before the model is asked, so the tape holds no turn-1 model reply. A bar above the recorded 0.95 stops the handoff, the replay asks the model, the tape has no answer, and the walk ends as NeedsRecording (`WalkGateResult.php:75`), so the check phrase "handed to the owner" is never judged. A live recording would behave the same way. The same mechanism applies to any walk whose recording is handed off before the model is asked.
- **Authority:** Ian, 2026-11-26.

**3. [Rule 5 - fakes] Discount walk's fakes use non-empty model words**
- With blank model words and a live offer, the offer is sent on its own by code (`OfferSentenceTest`), so "held for the owner" would not hold. The fakes give words ("Let me check with the owner about that."), label `discount` and `asks_discount` true, so the reply is held. The fixture is a placeholder; Ian's live run decides what the model actually does. Accepted by Ian as a fakes-only change.

**4. [Rule 5 - fakes] link-again's fake reply**
- The first fake reply for the link requests said "No problem", which is in the folder's `never_say` list (voice/how-we-talk.md), so turn 4 was held by `code:never_say`. The fake was changed to "Sure, I can help with that." and `link-again.json` was re-exported by the same throwaway-test route. The walk was not touched.

**5. [Interpretation] Test split and unit-of-case**
- The plan lists four Task 1 cases; the file holds more, split by concept (the booking walks as three cases, the risky walks as a dataset of four plus a price-sentence case, a complaint-alert case and a person case). Task 2 adds a starts check beyond the plan's list. `walks:record` exits 0 even when a walk's own checks fail under the fakes, so the walks were judged by the replay cases, not by that exit code.

**6. [Interpretation] Task 2's RED commit held no red case**
- Every Task 2 case passed on its first run, because Task 1's GREEN had already made the set true; the plan's own Task 2 action says the bar cases need no app change. Each case was therefore broken on purpose (below). The person-bar case was red at one point before Ian's ruling (it asserted the plan's line); it was fixed to Ian's wording before the RED commit.

**Total deviations:** 2 ruling-driven, 2 fakes-only, 2 interpretations. **Impact on plan:** none beyond the above; no change reached `app/`, `config/`, `database/` or `routes/`.

## TDD Gate Compliance

Task 1: RED `a7233a9` precedes GREEN `f825f3d`. Task 2: RED `a402304` precedes GREEN `6cdcd02`. No refactor commit. `gsd_run check tdd-red-evidence` was not run: Pest's console output is not one of its supported formats (the same gap as 10-02 to 10-07).

### RED evidence and semantic assessment

**Task 1 (`a7233a9`):** 12 cases, `{"result":"failed","tests":12,"passed":0,"failed":12}`. The exact-set case failed on the real listing (`README.md` and `dana.md` only, nine names expected), and the other eleven failed at `layExampleWalks()` with `Failed asserting that file ".../examples/example-hvac/tests/<name>.md" exists.` Semantic assessment: the targets ran and failed at the first line that needs the new files, the planned absence. That hid the deeper assertions, so every Task 1 case was mutation-checked after GREEN (below).

**Task 2 (`a402304`):** 32 of 32 passed on the first run (see deviation 6); no case was red, so each was broken on purpose.

### Break-on-purpose

For each mutant the file was changed in place, `ExampleWalksTest.php` was run, and the file was restored from a byte copy. The harness (`mutate.py`, in the session scratchpad) compared the full sha256 before and after and printed `True` for every row. No mutated file was committed; `git status --short` afterwards showed only the orchestrator's STATE files and my own work.

Task 1 mutants:

| File | sha256 before and after (identical) |
|---|---|
| `tests/Fixtures/walks/booked-time-question.json` | `12135ebd7fcc44184948c2657e06d9638650cdb1db6b00f102c48976e129ae76` |
| `tests/Fixtures/walks/link-again.json` | `795aff7cdb6f653e37862911f49545962817c37e9b6ac3da61acedab3bd96538` |
| `examples/example-hvac/tests/link-again.md` | `54de8ad799b925d19614ca5aac896f627e0dd79dbeb115767bfb16a73981accc` |
| `tests/Fixtures/walks/promise-question.json` | `198e4a114964a30e2292bb314095964354335a9775b814369478b63f7683407c` |
| `tests/Fixtures/walks/discount-question.json` | `c4d4e8cb686178262676d4df2c72d6c26a85e14ea56893dc7025d59596086c16` |
| `tests/Fixtures/walks/price-question.json` | `a09e9ab99cfae96f4532f53615c68d80c6eefc090c95e436aca79c3b9788b80e` |
| `tests/Fixtures/walks/complaint.json` | `7f79270552e1bf96aaceda320cc6576bda3e8190f3bda02a6fb6c18c5fc37e07` |
| `tests/Fixtures/walks/asks-for-person.json` | `94f9be40039d1dbccfb5bd8cdae80235b4edadc11dbbad9429bd07850b545f1f` |
| `examples/example-hvac/tests/plain-first-texts.md` | `46470b39cc947e6b3f77b5d8e522b0f0cb88d0c8faf3d8df7e8139ff94ef3593` |
| `examples/example-hvac/tests/promise-question.md` | `f8eecf7bd7511922504229384efd880ae201e885c64f73d9908c3029b82b8a37` |
| `app/Rooms/ScheduleAndBooking/VisitAskedAbout.php` | `5f18eb2068fda0952ce0cd8bc79bf8c8a67447f500be0b66c2c88f5aa2fb7600` |
| `app/Rooms/ScheduleAndBooking/ServiceToBook.php` | `9996bf3539e310061fe26a3087a83223c7aa3afaf321a80ffa5aa12b028d6274` |

| Case | File broken | How | Red line |
|---|---|---|---|
| ships the set | `promise-question.md` | file removed, then restored | arrays not identical (`9 => 'promise-qu...'` missing) |
| ships the set | `extra.md` | extra file added, then removed | arrays not identical |
| gate | `plain-first-texts.md` | turn 1 check `held for the owner` | `Expected status code 0 but received 1.` |
| booked time | `booked-time-question.json` | turn 5 label to `faq_answer` | `'scheduling'` vs `'faq_answer'` |
| booked time | `VisitAskedAbout.php` | `find()` returns null | `Failed asserting that an array contains 'visit_line'.` |
| link requests | `link-again.json` | turn 4 label to `faq_answer` | `'scheduling'` vs `'faq_answer'`, turn 5 then missing |
| link requests | `link-again.json` | turn 5 label to `faq_answer` | `'scheduling'` vs `'faq_answer'`, and the walk outcome Failed |
| link requests | `ServiceToBook.php` plus the link checks removed from `link-again.md` | `handle()` returns null | `Failed asserting that an array contains 'booking_link'.` (both cases) |
| risky: promise | `promise-question.json` | label to `faq_answer` | `Failed asserting that false is true.` (held) |
| risky: discount | `discount-question.json` | label to `faq_answer` | held false |
| risky: price | `price-question.json` | label, words, both intents and the words-check price score changed | held false |
| price sentence | `price-question.json` | `price_services` emptied | the sent text lacks the price sentence |
| complaint (held, alert) | `complaint.json` | label, both intents and both complaint scores changed | held false and alert false |
| person | `asks-for-person.json` | asks_for_person 0.95 to 0.01 | `null is an instance of WalkTurnObservation` (needs a recording) |

Survivors that were harder-mutated: price-question words and label alone (the words-check price score still held it), complaint label and reading alone (the words check still held it). Both were killed once every holding score was changed (rows above).

Task 2 mutants:

| File | sha256 before and after (identical) |
|---|---|
| `tests/Fixtures/walks/asks-for-person.json` | `94f9be40039d1dbccfb5bd8cdae80235b4edadc11dbbad9429bd07850b545f1f` |
| `tests/Fixtures/walks/plain-first-texts.json` | `1a79923ef8e289bb6bc7cc0d2dd0c27b5ca46e83326ae7a85d60e75f3770230e` |
| `examples/example-hvac/tests/link-again.md` | `54de8ad799b925d19614ca5aac896f627e0dd79dbeb115767bfb16a73981accc` |
| `examples/example-hvac/tests/discount-question.md` | `c8a8eb9ee779b614502055017d07bc875615387929a4df5ae51f433f11f9bd4e` |
| `tests/Fixtures/walks/promise-question.json` | `198e4a114964a30e2292bb314095964354335a9775b814369478b63f7683407c` |
| `examples/example-hvac/tests/complaint.md` | `d9ad2c345e43210fa0a964e0972e63166c6625a843798fbae9455b5714763909` |
| `examples/example-hvac/tests/price-question.md` | `932d08b5b35108079eb2c5f81240d62ade1d7e906d1db022d9a1b9c255a74d25` |
| `examples/example-hvac/tests/promise-question.md` | `f8eecf7bd7511922504229384efd880ae201e885c64f73d9908c3029b82b8a37` |
| `app/Icm/Walks/WalkClock.php` | `075c68cd0ea814516e4424408e025530447a40d95f1d868a8b2e7026f6e7eb8a` |

| Case | File broken | How | Red line |
|---|---|---|---|
| person bar | `asks-for-person.json` | asks_for_person 0.95 raised to 0.995 | `Expected status code 1 but received 0.` (the sync passes) |
| plain bar | `plain-first-texts.json` | turn 1 complaint 0.05 lowered to 0.02 (under the 0.03 bar) | `an array contains 'tests/plain-first-texts.md, turn 1: the reply was held for the owner, not sent on its own. v1 stays live.'` |
| weekday sweep | `promise-question.json` | label to `faq_answer` | `two arrays are identical` for all 7 dates (promise outcome Failed) |
| starts / sweep count | `discount-question.md` | `start:` line removed | `actual size 8 matches expected size 7` (7 dates) and the starts array lacks `discount-question` |
| D-05 hash | `link-again.md` | customer turn "Can you send the link again?" changed | script hash `d87cf3bc...` vs `9de0dcc6...` for link-again |
| fictional | `complaint.md` | `!` added | `not to contain '!'` |
| fictional | `price-question.md` | em dash added | `not to contain '—'` |
| fictional | `promise-question.md` | `202-555-0142` added | `not to match '/\d{3}[\s.-]\d{4}/'` |
| fictional | `complaint.md` | ten-digit run added | `not to match '/\d{10,}/'` |
| fictional | `complaint.md` | persona changed to "Tom Becker" | `actual size 8 matches expected size 9` |

The weekday sweep's weekday dimension itself did not turn red: with `WalkClock::startFor()` changed to start at 10:00 on the run's own day (ignoring openings, so Sunday 2026-12-06 would start while the shop is closed) the whole sweep still passed (8 of 8). So none of today's walks is sensitive to the starting weekday, which is the property the sweep asserts; the sweep's failure paths were shown by the outcome and count mutants above.

## Wording

The walk files are the plan's customer texts, except the person walk (deviation 1). Each walk's body is one plain sentence naming what the customer does and what must happen. No `!`, no em dash and no phone number appear in any walk file or fixture note (the fictional case scans all nine walks). The fixtures' `note` is Dana's text exactly.

## Existing tests

`git diff --numstat 553e8eb -- tests/` shows 0 deletions in every file: `ExampleWalksTest.php` +260 -0 (new), `WalkHelpers.php` +137 -0, and eight new fixtures. `tests/Pest.php`, `DanaEndToEndTest.php` and every other existing test file are untouched. `config/gate.php` is byte for byte (sha256 `bb98e5a3613dd4855e8f512723784631b496cd1adcb6795a5942ff13280030de`, `git diff --quiet main -- config/gate.php` exits 0). `git diff --quiet 553e8eb -- app config database routes` exits 0, so the two Booking constants in `app/Ai/FrontDeskPrompt.php` are unchanged.

## Verification

- Task 1 verify (`ExampleWalksTest`, `DanaEndToEndTest`, `SpecTablesTest`): 50 passed, before and after the feat commit and after the break-on-purpose round. `tests/Feature/Icm`, `Rooms`, `Quality`: 811 passed.
- Task 2 verify (`tests/Feature/Walks`): 115 passed.
- Acceptance checks, all pass: 10 `.md` files and 9 `.json` fixtures, 9 with `"source": "placeholder"`; `What time are you coming?` and the lost-link text in their walks; `asks_for_person_min_yes`, `complaint_max_yes` and `2026-12-06` in `ExampleWalksTest.php`; `D-02, D-12, D-19` on a `**Built:**` BLUEPRINT line; no throwaway test in `git ls-files tests/`.
- **Whole suite, `php artisan test --compact`, after the last code commit:** `{"tool":"pest","result":"passed","tests":3661,"passed":3661,"assertions":27499,"duration_ms":329255,"warnings":1}`. The one warning carries no detail (as in 10-05 to 10-07).
- `python3 ~/Projects/cartographer/tools/check_map.py ~/Projects/ops-platform --extra-root examples/example-hvac`: `RESULT: PASS`. `vendor/bin/pint --test`: passed.

## Decisions Made

See `key-decisions`. The recorded D-xx rulings are unchanged; the two rulings of 2026-11-26 above are Ian's.

## Issues Encountered

- `walks:record` exits 0 even when a walk's checks fail under the fakes, so the first export of `link-again.json` held a turn-4 hold that only the replay case showed (deviation 4).
- The first break-on-purpose mutants for the price and complaint cases survived, because the words check held the reply on its own score; they were killed by changing every holding score.

## Known Stubs

- `tests/Fixtures/walks/{plain-first-texts,booked-time-question,link-again,price-question,discount-question,promise-question,complaint,asks-for-person}.json`: the recordings are placeholders (`"source": "placeholder"`, `"fingerprint": null`, a `note`), made with the suite's scripted model and classifier; their scores are placeholders meeting today's bars, not measurements. Intended by the plan (T-10-28 to T-10-30); replaced by Ian's `walks:record --export` live run. Recorded in `.planning/WINDOWS.md` as entry 98 (stub, open).

## Threat Flags

None. T-10-28: every walk uses an invented persona and the fictional case scans for `!`, em dashes, long digit runs and phone-shaped numbers. T-10-29: the weekday sweep passes on all seven days and only the discount walk (and Dana) names a start. T-10-30: the plain and person bar cases show a bar change flips a walk; D-14 is asserted by the follow-up plan against Ian's recording.

## Hand checks for end-of-phase UAT

- CI pgsql leg: after Ian pushes the phase branch, `ExampleWalksTest` and the full suite green on Postgres. Recorded as `unrun-verify` entry 97 in `.planning/WINDOWS.md` (commit `0a7f896`). Not a pause.
- Ian's live `walks:record --export` for the eight new placeholder fixtures (WINDOWS.md entry 98, stub).

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- The shipped walk set is complete (D-12) and CI judges it by whatever bars `config/gate.php` holds at test time.
- 10-09 can flag every `source: placeholder` fixture in its report.
- Nothing blocks.

## Self-Check: PASSED

- Created files exist (`[ -f ]`): the eight walk files, the eight fixtures and `tests/Feature/Walks/ExampleWalksTest.php`.
- Commits `a7233a9`, `f825f3d`, `a402304`, `6cdcd02`, `0a7f896` are ancestors of HEAD; `git rev-list --count 553e8eb..0a7f896` is 5, matching `commits: 5`.
- `git status --short` held only the orchestrator's STATE edits before this commit; no throwaway test or mutated file exists on disk or in git.

---
*Phase: 10-walk-tests-and-the-gate*
*Completed: 2026-11-26*
