---
phase: 10-walk-tests-and-the-gate
plan: 09
subsystem: icm-walks
tags: [calibration, walks_report, walks_record_calibration, two_sided_report, d13, tdd, pest]

requires:
  - phase: 10-walk-tests-and-the-gate
    provides: "10-06: walks:record, WalkRecording rows, the RecordingClassifier and the --export form"
  - phase: 10-walk-tests-and-the-gate
    provides: "10-08: the nine example walks and their eight placeholder fixtures, exampleWalkNames(), seedRecordingFromFixture(), the person walk's keyword ruling"
provides:
  - "The committed calibration set tests/Fixtures/calibration/calibration-set.json: 15 invented texts, the 08-UAT check 4 and check 8 measured scores from the stored verdicts and placeholders for the rest (D-12, D-13)"
  - "CalibrationItem, CalibrationSet (fromArray, withResults, placeholderCount, recorded, resultFromArray), CalibrationVerdict (judge, heldBy), CalibrationReport (lines), CalibrationWalkSource, BookingWordingRun"
  - "walks:report {--calibration=} {--bar=*}: a block for each of the five bars D-15 lets move, D-13's stop per check, the three fixed bars as context, a combined block ending in the D-14 line, and the Booking wording block"
  - "walks:record --calibration= --runs=: the container's Classifier asked about each calibration text, the raw scores written back only when every call answered"
  - "BLUEPRINT section 10 Built bullet (D-13 to D-19) with its Affects line"
affects: [10-10 docs/SETUP.md live run, Ian's live walks:record --calibration run (D-17 step 2), the follow-up approved-values plan (D-17 step 4)]

actuals:
  tokens: 27600
  tasks: 3
  commits: 8

tech-stack:
  added: []
  patterns:
    - "The report judges recorded raw scores by config('gate.classifier') read at run time, so a bar change flips the same item's verdict (Ian's D-02 ruling)"
    - "CalibrationVerdict reads the front desk's own predicates (TriageReading, CustomerReading, AsksForPerson) and never a second copy of a threshold; the docblock names each predicate and its RunFrontDesk or DecideSendOrDraft line"
    - "Every number the report prints is a recorded score or bar as recorded, or a midpoint rounded to one more decimal than its more precise neighbour; a candidate is rounded, then deduplicated, then judged at that same number (R5)"
    - "A walk turn is one text in the combined block: plain only when every Jev call sends, risky when any call or the model's never-on-its-own label holds it"

key-files:
  created:
    - tests/Fixtures/calibration/calibration-set.json
    - tests/Feature/Walks/CalibrationReportTest.php
    - app/Icm/Walks/CalibrationItem.php
    - app/Icm/Walks/CalibrationSet.php
    - app/Icm/Walks/CalibrationVerdict.php
    - app/Icm/Walks/CalibrationReport.php
    - app/Icm/Walks/CalibrationWalkSource.php
    - app/Icm/Walks/BookingWordingRun.php
    - app/Console/Commands/WalksReport.php
  modified:
    - app/Console/Commands/WalksRecord.php
    - docs/BLUEPRINT.md
    - .planning/WINDOWS.md

key-decisions:
  - "A calibration text holds no phrase from AsksForPerson::PHRASES, so the score decides the person bars (Ian, R1, R2); a test pins it"
  - "CalibrationVerdict mirrors the keyword hand-off at RunFrontDesk.php:226 and holds a reading only on what the front desk acts on (Ian, R3, R7)"
  - "The report prints each number as recorded or as a rounded midpoint and judges each row at the printed number (Ian, R5)"
  - "The 08-UAT items use the measured scores from the stored verdicts, two plain items for the first text (Ian, R6)"
  - "D-13's stop is judged once per check: complaint under complaint_max_yes, promise under promise_max_yes, asks_for_person under gate_check_max_yes, the lower edge of its band"

patterns-established:
  - "Test helper names stay unique across the Pest process: calibrationFixturePath(), calibrationFixtureData(), walksReportRun(), reportLineStartingWith(), reportLineInBlock(), writeCalibrationFile(), calibrationItemWith(), calibrationResult(), calibrationCopy(), walksRecordRun(), queueCalibrationScores()"

requirements-completed: [WALK-02]

coverage:
  - id: D1
    description: "The calibration set is a committed fixture of invented texts with the 08-UAT measured scores, placeholders marked by source and measured keys, and no text a listed phrase hands off"
    requirement: WALK-02
    verification:
      - kind: unit
        ref: "tests/Feature/Walks/CalibrationReportTest.php#holds no text in the committed set that a listed phrase hands off, so the score decides (Ian, R1, R2); flags placeholder scores; reads stored zeros and ones as numbers"
        status: pass
    human_judgment: false
  - id: D2
    description: "walks:report prints both sides at each candidate number for every bar D-15 lets move, judged by the bars of the moment, with every number as recorded or a rounded midpoint"
    requirement: WALK-02
    verification:
      - kind: integration
        ref: "tests/Feature/Walks/CalibrationReportTest.php#prints both sides for complaint_max_yes (D-13); applies the bars of the moment; prints a block for every bar D-15 lets move; prints a recorded score as recorded and a midpoint to one more decimal"
        status: pass
    human_judgment: false
  - id: D3
    description: "D-13's stop is printed per check when no number separates plain from real, and --bar refuses the three fixed bars, an unknown bar and a number outside 0 to 1 in plain words"
    requirement: WALK-02
    verification:
      - kind: integration
        ref: "tests/Feature/Walks/CalibrationReportTest.php#prints D-13's stop when no number separates plain from real; refuses a bar that does not move, an unknown bar and a number outside 0 to 1; judges by --bar values instead of config"
        status: pass
    human_judgment: false
  - id: D4
    description: "CalibrationVerdict agrees with a one-turn walk replayed from the same result for a held reading, a sent reading, a held words check, a keyword text and a score-only person text, and holds a reading only on what the front desk acts on"
    requirement: WALK-02
    verification:
      - kind: integration
        ref: "tests/Feature/Walks/CalibrationReportTest.php#agrees with the front desk (CalibrationVerdict); holds a reading only on what the front desk acts on, and a words check on every reason (R7)"
        status: pass
    human_judgment: false
  - id: D5
    description: "Walk recordings join the report as items and each recording of a booking walk is replayed and laid out with its words raw, for Ian to read"
    requirement: WALK-02
    verification:
      - kind: integration
        ref: "tests/Feature/Walks/CalibrationReportTest.php#counts each walk turn as one item in the combined block; shows each Booking wording run for Ian to read, the words raw (D-18, D-19)"
        status: pass
    human_judgment: false
  - id: D6
    description: "walks:record --calibration refreshes a copy's scores from the container's Classifier, keeps nothing when a call fails, refuses a bad --runs, and a test never writes the committed set"
    requirement: WALK-02
    verification:
      - kind: integration
        ref: "tests/Feature/Walks/CalibrationReportTest.php#refreshes a copy of the calibration set with the classifier's raw scores; never touches the committed calibration set in a test; keeps nothing when Jev fails; refuses --runs outside 1 to 10; refuses --runs without --calibration"
        status: pass
    human_judgment: false
  - id: D7
    description: "Ian's own walks:record --calibration run against the live Jev (D-17 step 2), which replaces the eight placeholder items, and his reading of the report"
    verification: []
    human_judgment: true
    rationale: "Only the live Jev scores real texts, and a GSD run never makes that call. Recorded as WINDOWS.md entries 99 (stub) and 100 (unrun-verify), never a pause."

duration: 45min
completed: 2026-11-27
status: complete
commits: 8
plan_head_before: 7f671c3e610a3c44dd8923d218c47572982526b3
plan_head_after: e408a2d29fc701926db67bd64592b80b2e6da94f
---

# Phase 10 Plan 09: The calibration set and the two-sided report Summary

**A committed calibration set (the 08-UAT measured scores plus invented placeholders), `walks:report` showing for every movable bar which plain texts each candidate number would hold and which risky texts it would let send under the bars of the moment, D-13's stop, the fixed bars, each Booking wording run laid out raw, and `walks:record --calibration` to refresh the scores from Jev; nothing writes a bar or a wording.**

## Performance

- **Duration:** about 45 min (first RED commit 08:44 local, last code commit 08:57, then the full suite and the break-on-purpose round)
- **Tasks:** 3 (Task 1 a tracer, Tasks 2 and 3 auto tdd)
- **Files:** 9 created, 3 modified (WalksRecord.php, BLUEPRINT.md, WINDOWS.md); `git diff --stat 7f671c3..HEAD -- app tests docs` is 2632 insertions and 1 deletion (the `walks:record` signature line)

## Accomplishments

- `tests/Fixtures/calibration/calibration-set.json`: 15 items. Seven plain items hold measured scores from the stored 08-UAT verdicts (Ian's R6): "Hi, my AC isn't cooling. Can someone come take a look?" as two items (the reading, and the words check on the model's reply), "It's blowing warm air", "Over 10 years" (check 4), "What time are you coming?", "Can you send the link again?", "Not cooling" (check 8). Three plain intake answers and five risky texts (complaint reading, "Is there a human I can text with instead?" person reading, price, discount and promise words checks) hold placeholders (`source` placeholder). Eight of 15 hold placeholders, and the report's header says so. No `!`, no em dash, no phone number.
- `CalibrationVerdict` judges a result as the front desk does, per role (R3, R7), and its docblock names each predicate and its line: `RunFrontDesk.php:226` (keyword or asks_for_person hands off), `:367` and `codeHoldReason` `:1897-1908` (a reading's complaint, unsure about a person, no answer), `DecideSendOrDraft.php:116-118` and `:124-126` (a words check), `RunFrontDesk.php:355` (a words check that asks for a person hands off).
- `CalibrationReport` prints, for each of `complaint_max_yes`, `promise_max_yes`, `gate_check_max_yes` (named the asks_for_person unsure floor, D-16), `asks_for_person_min_yes` and `intent_min_confidence`, one row per candidate (the judged value, every distinct recorded score for the question, the rounded midpoints), then `price_max_yes`, `discount_max_yes` and `life_safety_min_yes` as fixed context, a combined block of every text judged by `judge()`, the D-14 summary line, and the Booking wording block.
- `walks:report --bar` accepts only the five movable keys and refuses `price_max_yes`, `discount_max_yes`, `life_safety_min_yes` ("does not move (02 D-45, D-56)" and "(02 D-46)"), unknown keys and numbers outside 0 to 1 in plain words.
- `CalibrationWalkSource` makes one item for each recorded Jev call in the as_built recordings, labeled by the walk's own expectation, and replays each recording of a walk that expects a scheduling reply for the Booking wording block (the judged bars are put in config for the replay and put back after).
- `walks:record --calibration=<path> --runs=<1..10>` opens a scratch world with HTTP allowed, asks the container's Classifier about each text (an empty draft for a reading, the item's reply for a words check), and writes the set back once, only when every call answered, marked `live` with every score measured and `recorded_with` naming the model and the time.

## Task Commits

1. **Task 1 (tracer): walks:report reads the committed set and prints both sides for complaint_max_yes**
   - RED `2f346ab` test(10-09): the two-sided report over the calibration set (D-13)
   - GREEN `74e8d44` feat(10-09): walks:report shows both sides of a bar (D-13)
2. **Task 2: every movable bar, D-13's stop, the fixed bars, --bar, walk recordings as items, the Booking wording block**
   - RED `d56c631` test(10-09): every bar, the stop, --bar and the Booking wording block (D-13, D-15, D-18)
   - RED (design change, see deviation 8) `3c5e125` test(10-09): a walk turn is one item in the combined block (D-13, D-14)
   - GREEN `3cb4196` feat(10-09): the full two-sided report and the Booking wording block (D-13, D-15, D-18, D-19)
3. **Task 3: walks:record --calibration, BLUEPRINT**
   - RED `e5fd1ad` test(10-09): walks:record --calibration refreshes the set (D-13)
   - GREEN `555d73b` feat(10-09): walks:record --calibration; BLUEPRINT records the report (D-13)
4. **Ledger** `e408a2d` docs(10-09): record the calibration placeholders and Ian's live run in the windows ledger

**Plan metadata:** the docs commit that holds this file (SUMMARY, STATE, ROADMAP, REQUIREMENTS).

Tracer gate (Task 1, no `gate` attribute, automated-only verify, `end-of-phase`): the verify command was re-run after the feat commit (21 passed) and logged "Tracer verified end-to-end, expanding". No checkpoint.

## Deviations from Plan

### Ruling-driven (Ian, 2026-11-27)

**1. [Ruling R1] The risky person text avoids the keyword list.** Quote: "The calibration set's risky person text holds no phrase from App\SendRule\AsksForPerson::PHRASES, the same rule as the person walk." The fixture's person reading is "Is there a human I can text with instead?" in place of the plan's "Can I talk to a real person please?", so `RunFrontDesk.php:226` cannot hand it off before Jev's score is read. `AsksForPerson::inText()` is false for it, and the placeholder hands off by score (asks_for_person 0.95 at or above 0.85).

**2. [Ruling R2] A test pins that no calibration text holds a listed phrase.** Quote: "Add a test ... that no item in the committed calibration fixture has a customer_message for which AsksForPerson::inText() is true." Added to the Task 1 RED commit (`holds no text in the committed set that a listed phrase hands off`).

**3. [Ruling R3] `judge()` takes the customer's text.** Quote: "judge() takes the customer's text as a parameter: add `string $customerMessage` to the plan's signature. When AsksForPerson::inText($customerMessage) is true, the result is handed off (held true, handedOff true) whatever the scores." Signature: `judge(ClassifierResult, TriageQuestions, array $bars, string $role, string $customerMessage)`. It applies to a reading and to a words check, because the front desk hands off on the keyword before the model is asked. `heldBy()` stays per bar; a listed phrase is no bar's reason.

**4. [Ruling R4] The 'agrees with the front desk' case.** Quote: "keeps its held reading, sent reading and held words check. It adds two more texts." Five rows: a held reading, a sent reading, a held words check, a keyword text ("Can I talk to a real person please?", in the test only, reading asks_for_person 0.01) and a score-only person text (asks_for_person 0.9). For each, `judge()` matches a one-turn walk replayed from a tape holding the same result: sentOnItsOwn is the opposite of held, handedToOwner equals handedOff.

**5. [Ruling R5] Number printing.** Quote: "A midpoint, rounded to one more decimal than the more precise of its two neighbours ... Each candidate IS its printed number: round it first, then deduplicate the candidates, then judge the row at that rounded value." `CalibrationReport::numberText()` prints the shortest form that reads back as the same float (0.075, 0.16, 0.2, never 0.15500000000000003); `midpoint()` rounds; rows are keyed by printed text and judged at that same float. Scores arrive as integer or float and are read as floats (`CalibrationSet::score()`).

**6. [Ruling R6] The 08-UAT items come from the stored verdicts.** Quote: "Use exactly these values." The first text is two plain items (reading: intent scheduling 0.85, asks 0.5; words: scheduling 0.78, asks 0.41, promise 0.08), "It's blowing warm air" (complaint 0.16), "Over 10 years" (0.2), "What time are you coming?" (asks 0.08), "Can you send the link again?" (complaint 0.2) and "Not cooling" (complaint 0.17), each with every score key listed as measured and model `typesafe/jev-1.13-20260917`. The plan's "intake" assumption and its numbers were not used. I queried no database. The fixture's top-level note says what Ian wrote in R6. Rows changed against the plan: complaint_max_yes now has recorded scores 0.16, 0.17 and 0.2, and four plain texts are held at 0.15.

**7. [Ruling R7] Mirroring per role.** Quote: "A reading holds only on what RunFrontDesk acts on ... A words check holds when TriageReading::read(...)->sendable is false." Read from the code, not the summary: a reading's `judge()` is `handedOff || complaint() || unsureAboutPerson()` from `CustomerReading::fromResult`, and `heldBy()` for a reading keeps only the reasons `classifier:asks_for_person`, `classifier:complaint`, `classifier_intent:complaint` and `classifier_unsure:asks_for_person`, so `heldBy('promise_max_yes' or 'intent_min_confidence', ...)` on a reading is false. A reading's life_safety answer is not a hold in `RunFrontDesk::handle()` (it sends the safety line at `:207` and the held verdict comes from the words check), so it holds nothing here either.

### Other deviations

**8. [Rule 2 - correctness of the report] A walk turn is one text, and the model's label counts.** Found during Task 2 GREEN, reading the first real dump of the combined block: judged call by call, the price walk's reading "sends" (a reading holds nothing on price), and the promise and discount walks "sent" by Jev's placeholder scores although the front desk holds them on the model's label (never on its own, `DecideSendOrDraft` check 1). That would have printed "D-14: not met, 5 risky texts would send" for texts the gate holds. Fix: the combined block groups every call of one walk turn into one text, which holds when any call holds or when a recorded reply carries a never-on-its-own label (`ReplyLabel::neverOnItsOwn()`, a floor no stored rule can lower), printed as `label:promise`. Per-bar rows still judge call by call. Code checks on the words (length, never_say) are not replayed here; the walk gate covers them. The expectation was changed in its own RED commit `3c5e125` before the code commit `3cb4196`. That commit's message has a wrong second bullet ("reads the reply cap of the report's output through its own buffer"); it should say the test helper reads the report's output through its own buffer, so the replay's migrations do not overwrite `Artisan::output()`. History is not rewritten, so it stays.

**9. [Rule 1 - bug] `numberText()` hid float noise.** Break-on-purpose showed the midpoint-rounding mutant surviving: the shortest-form loop stopped at 15 decimals and fell back to `(string)`, which printed 0.155 for 0.15500000000000003 while the row was judged at the noisy number. The loop now goes to 20 decimals, so noise is visible and the test catches it. Found and fixed before the Task 1 commit.

**10. [Interpretation] Signatures and files beyond the plan's list.** `heldBy()` takes a `string $role` (R7 needs it); `lines()` takes `$given` and `$bookingRuns`; the replay and the recordings-as-items live in `CalibrationWalkSource` and `BookingWordingRun`, so `CalibrationReport` stays free of the database. `CalibrationItem` carries a `vetoes` list (empty for a calibration item) for deviation 8.

**11. [Interpretation] D-13's stop is judged once per check, and the asks bars share a band.** complaint under `complaint_max_yes`, promise under `promise_max_yes`, asks_for_person under `gate_check_max_yes` (printed once; `asks_for_person_min_yes` says where it is judged). A bar's rows for the two asks bars read the band's reasons (asks_for_person at or above the hand-off bar, or unsure above the floor), so a person text handed off at 0.95 reads as held in the floor's rows. `heldBy('gate_check_max_yes')` itself stays the narrower "unsure" reason as the plan states. Plain texts are judged only by bars a role acts on (a reading never holds on promise or intent), so promise and intent rows count words checks only. The intent block says no risky text is judged by that bar alone.

**12. [Interpretation] `walks:record --runs` without `--calibration` is refused in plain words** ("--runs only applies with --calibration."), so a stray option never looks like it did something. The existing `walks:record` tests were not touched and still pass.

**13. [Interpretation] The report's tests read output through a `BufferedOutput`.** `Artisan::output()` returns the last call's buffer, and the replays' migrations overwrite it, so `walksReportRun()` and `walksRecordRun()` pass their own buffer, as `WalksRecordCommandTest` does.

**Total deviations:** 7 ruling-driven, 1 Rule 2, 1 Rule 1, 4 interpretations. **Impact on plan:** none reached `config/`, `database/` or `routes/`; `config/gate.php`, `rules/triage.md` and `app/Ai/FrontDeskPrompt.php` are untouched.

## TDD Gate Compliance

Each task's RED commit precedes its GREEN commit: Task 1 `2f346ab` then `74e8d44`; Task 2 `d56c631` and `3c5e125` then `3cb4196`; Task 3 `e5fd1ad` then `555d73b`. No refactor commit. `gsd_run check tdd-red-evidence` was not run: Pest's console output is not one of its supported formats (the same gap as 10-02 to 10-08).

### RED evidence and semantic assessment

- **Task 1 (`2f346ab`):** 7 of 7 failed: five with `The command "walks:report" does not exist.`, two with `Class "App\Icm\Walks\CalibrationSet" not found`. Semantic assessment: the targets ran and failed at the first line that needs the new code, the planned absence. That hid the deeper assertions, so each was mutation-checked after GREEN (below).
- **Task 2 (`d56c631`):** 5 of 33 failed on the planned absence (no blocks beyond complaint, no stop for promise or asks_for_person, no walk items, no Booking block, no fixed context); 28 passed. Of the 28, 19 cases passed on their first run (the D-13 complaint stop row, the `--bar` cases, the five CalibrationVerdict rows, the six `heldBy` rows, the D-14 line), because Task 1 had already built `CalibrationVerdict`, the complaint block and the command's `--bar` parsing. Each of those was broken on purpose (below). `3c5e125`: 1 of 33 failed (the old label and no label veto), as intended.
- **Task 3 (`e5fd1ad`):** 8 of 41 failed with `The "--calibration" option does not exist.` or `The "--runs" option does not exist.`, the planned absence.

### Break-on-purpose

Each mutant changed one file in place, the named test file was run and failed, the file was restored from a byte copy, and `mutate.py` (session scratchpad) compared the full sha256 before and after (identical in every row) and ran the file green again. Nothing mutated was committed.

| File | sha256 before and after (identical) |
|---|---|
| `tests/Fixtures/calibration/calibration-set.json` | `a5f880802a1f25d9737c9ff2aca9711d81742705b14c7b5e86c2b0e7c5a338aa` |
| `tests/Feature/Walks/CalibrationReportTest.php` | `0721cdfd87d5003c534213a2306fc5c4f71b575a8cf44358c2734e2d521e72a9` |
| `app/Icm/Walks/CalibrationVerdict.php` | `459d41fbc5558cae5ba8f743c575a8c6cdcc16799b929d3081403a67af448877` |
| `app/Icm/Walks/CalibrationReport.php` | `139991690e0ead577b93c0a75da73fbe8451ac96ad6275e23ca4464d31f3b17c` |
| `app/Icm/Walks/CalibrationSet.php` | `1d895892e8d8031de4c73f15ee369c16fc82ea44a50ded6014332a3e2d297c49` |
| `app/Icm/Walks/CalibrationItem.php` | `63b6ce148f8172a85320e39c6e1aefd120b9308ddaf9df39a48cd1ed6b9de290` |
| `app/Console/Commands/WalksReport.php` | `edbc7907c70120be9ea17cc580075859e6aaa66b05bf9cc473d598659ac80389` |
| `app/Console/Commands/WalksRecord.php` | `85ae4501154ac9da370963c7f5116459e08bbf26e31761508c5dfdd8dd0c7b21` |

| Test (CalibrationReportTest.php) | File broken | How | Red line |
|---|---|---|---|
| no listed phrase in the set (R2) | fixture | "Not sure" became "Not sure, talk to someone" | `not-sure holds a listed phrase` |
| applies the bars of the moment | CalibrationVerdict | a reading's `complaint()` hold removed | `[plain] held: "It's blowing warm air"` missing at the shipped bar |
| refuses a malformed item | CalibrationSet | the side check disabled | `CalibrationItem::__construct(): Argument #2 ($side) must be of type string, null given` |
| both sides for complaint_max_yes | CalibrationReport | every plain text counted as sent | `0.15 (today): plain sent 10 of 10` against 6 of 10 |
| reads stored zeros and ones | CalibrationSet | an integer score refused | `Failed asserting that 1 is identical to 0.` (the other cases red too) |
| midpoint, no float noise (R5) | CalibrationReport | the midpoint not rounded | `Failed asserting that 1 is identical to 0.` (a long digit run printed) |
| judges by --bar | WalksReport | `--bar` values ignored | header and `[plain] sent:` line missing |
| refuses a bar (x7 rows) | WalksReport | fixed-bar refusal off; unknown-bar check off; upper range 1 to 100 | each row's expected line missing, exit 0 |
| agrees with the front desk | CalibrationVerdict | keyword hand-off off (R3); a reading's score hand-off off; a words check never holds | the matching rows red: `held` or `handedOff` not equal to the replayed walk |
| holds a reading only on what the front desk acts on (R7) | CalibrationVerdict | the reading filter in `heldBy()` off | promise and intent rows: `true` where `false` expected |
| D-14 line | CalibrationReport | "D-14: not met" renamed | `contains "D-14: "` fails |
| D-13 stop, complaint row | CalibrationReport | stop printed when a number does separate | the stop missing from the overlap rows |
| keeps nothing when Jev fails | WalksRecord | a failed call skips the item instead of ending the run | the file changed, exit 0 |
| --runs outside 1 to 10 | WalksRecord | the upper bound 10 to 100 | `--runs=11` accepted |
| never touches the committed set | test | the hash expectation turned round | `Failed asserting that '<hash>' is not identical to '<hash>'` |
| refreshes a copy | CalibrationItem | the refreshed source kept as `placeholder` | `source` not `live` |

One survivor taught something: removing the `(float)` cast in `CalibrationSet::score()` did not turn the integer test red, because `ClassifierResult`'s typed float properties coerce an integer in PHP's weak mode. The test pins the accepted behavior (an integer is accepted and read as a float); refusing integers is what turns it red, and that mutant is in the table.

## Wording

Every invented text in the fixture and the tests has no `!`, no em dash, no phone number and no real data. The Task 3 BLUEPRINT bullet is one `**Built:**` line carrying "Phase 10 context D-13 to D-19" and "Affects: safety and compliance, customer communication, knowledge."

## What the report shows now

With the committed set and the shipped bars (walk recordings not seeded) the report prints, for example, `0.15 (today): plain sent 6 of 10 (held: "It's blowing warm air" ... "Over 10 years" ... "Can you send the link again?" ... "Not cooling" ...); risky held 1 of 1`, rows at 0.155, 0.16, 0.165, 0.17, 0.185 and 0.2, and no stop for complaint, promise or asks_for_person, because the plain recorded scores top out under the risky placeholders. Those risky scores are placeholders, so that "separates" says nothing about Jev until Ian's live run. The combined block says "D-14: not met under these bars" for the committed set, and no test asserts D-14 (D-17).

## Existing tests

`git diff 7f671c3 -- tests/ | grep -E '^-[^-]'` prints nothing (0 lines). `tests/Feature/Walks/WalksRecordCommandTest.php` is byte for byte (sha256 `27011002a8c2c4b3f37c237299cdbdf4063e373635179f9ce77615df6eb33834`) and `tests/Feature/Walks/WalkHelpers.php` is unchanged (`e4344e7fe5d033a45fa66f9da9663859312a5feb6cfb36d6faee6995e86a18cc`); the only test files touched are the new `CalibrationReportTest.php` and the new fixture.

## Verification

- Task 1 verify (`CalibrationReportTest`, `ArchitectureTest`): 21 passed. Task 2 and Task 3 verify (`tests/Feature/Walks`): 156 passed.
- Acceptance checks, all pass: the fixture holds `"source": "08-UAT check 4"` and `"08-UAT check 8"`, `0.78` and `0.16`; `#[Signature('walks:report` in WalksReport.php; `TriageReading::read(` in CalibrationVerdict.php; `Do not move this bar; bring a reword of rules/triage.md for` in CalibrationReport.php; `does not move (02 D-45, D-56)` in WalksReport.php; `gate_check_max_yes` and `asks_for_person_min_yes` in CalibrationReport.php; `{--calibration=` and `{--runs=1` in WalksRecord.php; `D-13 to D-19` on a BLUEPRINT `**Built:**` line. `git diff --quiet main -- config/gate.php examples/example-hvac/rules/triage.md` exits 0.
- Byte-for-byte (H6), after every commit and after the suite: `config/gate.php` `bb98e5a3613dd4855e8f512723784631b496cd1adcb6795a5942ff13280030de`; `examples/example-hvac/rules/triage.md` `9cc8564a6bbf8b43dc12290f2a1a8d99513883d81cc6154e909c3046d00a8691`; `BOOKING_ONLINE` `b1b7f1c18145e840f48de12dd2da7f1d58206d82662c7e63dde902f9be249b64`; `BOOKING_VISIT_BOOKED` `46919a0e558f3e30adbc5012d50eb18f617d8eb52a1c2fb08ea9b6defdec1470`; `git diff --quiet 7f671c3 -- app/Ai/FrontDeskPrompt.php` exits 0.
- Committed fixture (H7): sha256 `a5f880802a1f25d9737c9ff2aca9711d81742705b14c7b5e86c2b0e7c5a338aa` right after the RED commit and after every later test run, `git diff --quiet -- tests/Fixtures/calibration/calibration-set.json` exit 0 each time, and unchanged after the full suite. It was never changed after its commit.
- **Whole suite, `php artisan test --compact`, after the last code commit:** `{"tool":"pest","result":"passed","tests":3702,"passed":3702,"assertions":27804,"duration_ms":324631,"warnings":1,"warning_details":[]}`. The one warning carries no detail (as in 10-05 to 10-08).
- `python3 ~/Projects/cartographer/tools/check_map.py ~/Projects/ops-platform --extra-root examples/example-hvac`: `RESULT: PASS`. `vendor/bin/pint --test`: `{"tool":"pint","result":"passed"}`.

## Holds

H1 held: every commit on `gsd/phase-10-walk-tests-and-the-gate`, no push, merge, rebase, amend, reset or stash. H2 held: no `walks:record`, `walks:report`, `icm:sync` or tinker outside a Pest test, no query on the dev database; tests use the fakes and the in-memory sqlite database and pass `--calibration` a temporary copy. H3 held (table above). H4 held (table above). H5 held (quoted above). H6 and H7 held. H8 held. H9 held (the Built bullet carries its Affects line). H10 held (suite, check_map, pint above; no cited map line moved). H11 held: Ian's `walks:record --calibration` run is D-17 step 2 and was not run; it is recorded, not a pause.

## Decisions Made

See `key-decisions`. The recorded D-xx rulings are unchanged; R1 to R7 above are Ian's (2026-11-27).

## Issues Encountered

- The first Task 2 GREEN run printed a walk's migrations into the test's captured output (`Artisan::output()` is the last call's buffer); the helpers now pass their own buffer (deviation 13).
- A regex edit of the `does not move` message left a doubled phrase for one run; it was corrected by hand and the `walks:report` cases pin the exact words.
- The `--bar` parser, the calibration-set reader and the `--calibration` path resolution each read the same file shape; `WalksReport` and `WalksRecord` repeat about 20 lines of it. Left as it is.

## Known Stubs

- `tests/Fixtures/calibration/calibration-set.json`: eight items hold placeholder scores (`"source": "placeholder"`, `"measured": []`): the three plain intake answers ("Under 5 years", "5 to 10 years", "Not sure") and the five risky texts. Intended by the plan and by R6 (T-10-33); replaced by Ian's `walks:record --calibration` run, after which every item reads `live`. Recorded in `.planning/WINDOWS.md` as entry 99 (stub, open). The eight example walk fixtures are still placeholders too (entry 98).

## Threat Flags

| Flag | File | Description |
|------|------|-------------|
| threat_flag: file_write | app/Console/Commands/WalksRecord.php | `--calibration` writes the refreshed set as JSON to the path the operator gives; it is a console option Ian runs, writes only the set, and a test passes it a temporary copy and checks the committed file's hash (T-10-34) |

T-10-31 (report disagreeing with the front desk): `CalibrationVerdict` reads the front desk's own classes and five one-turn walk replays pin it, plus the per-role `heldBy` cases. T-10-32 (`--bar` moving a fixed bar): refused with the decision that keeps it. T-10-33 (placeholders read as measurements): `source` and `measured` on every item, the header count, and WINDOWS entry 99. T-10-35 (customer words as console formatting): the report writes raw, with hidden characters marked, and a reply word `<info>` prints as typed.

## Hand checks for end-of-phase UAT

- Ian's own `walks:record --calibration` run against the live Jev (D-17 step 2), then `walks:report` read as one decision with the Booking wording runs (D-17 step 3). Recorded as WINDOWS entries 99 and 100. Not a pause.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- 10-10 can document the live run (`walks:record --calibration`, then `walks:report`) in `docs/SETUP.md`.
- The follow-up approved-values plan (D-17 step 4) reads `walks:report` for its numbers; nothing here writes a bar or a wording.
- Nothing blocks.

## Self-Check: PASSED

- Created files exist (`[ -f ]`): the fixture, `CalibrationReportTest.php`, `CalibrationItem`, `CalibrationSet`, `CalibrationVerdict`, `CalibrationReport`, `CalibrationWalkSource`, `BookingWordingRun` and `WalksReport`; `WalksRecord.php` is modified.
- Commits `2f346ab`, `74e8d44`, `d56c631`, `3c5e125`, `3cb4196`, `e5fd1ad`, `555d73b`, `e408a2d` are ancestors of HEAD; `git rev-list --count 7f671c3..e408a2d` is 8, matching `commits: 8`.
- `git status --short` held only the orchestrator's STATE edits before this commit; no mutated file remains.

---
*Phase: 10-walk-tests-and-the-gate*
*Completed: 2026-11-27*
