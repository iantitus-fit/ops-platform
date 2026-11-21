# Phase 10: Walk Tests and the Gate - Research

**Researched:** 2026-11-21
**Domain:** Laravel 13 sync gate (folder candidate, scripted-customer replay), recorded model and classifier replay, scratch-world isolation, plain-words refusal lines, bar calibration report, reply-cap exception
**Confidence:** HIGH on the code facts and the test inventory (read this session); MEDIUM on the isolation recommendation (a design choice the CONTEXT leaves to Claude's Discretion, argued below, not yet built)

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

Copied verbatim from `10-CONTEXT.md` `## Implementation Decisions` (the citation key table and the "Locked, carried forward" list are in that file; the decisions follow).

**Chokepoint rule.** Phase 10 adds no agent action on a real customer. A walk and a recording run drive the front desk's real path, but a walk never sends a real text, never writes a live row and never alerts the owner (how it is kept off live data is the planner's, Claude's Discretion). The gate's refusal is code deciding on code-made checks. No new chokepoint is named.

#### Walk-test determinism
- **D-01:** The gate replays recordings. A recording holds the front desk model's replies to a walk's customer turns, captured in one live run. Every sync replays them against the candidate, and every code-made check runs against the candidate as it is: the price sentence picked, the times offered, the booking, the owner alert, the card, the reminder. Both example failures in ROADMAP criterion 2 (a price not in the folder, an expected time no longer offered) come from code (`PriceSentencePicker`, `Availability`), so the gate catches them whatever words were recorded. Affects: knowledge, customer communication, safety and compliance.
- **D-02 (Ian's explicit approval of a stand-in for Jev, 2026-11-20):** inside the walk gate only, Jev's readings are replayed from the recording by a test classifier. Jev stays live for every real customer text, and nowhere else in the platform does anything stand in for Jev. The recording keeps Jev's raw scores, not its verdicts, and every sync applies the current bars in `config/gate.php` to them. A bar change is tested by the next sync; only Jev's own drift waits for a re-record. The recorded scores are also an input to the bar calibration (D-13). Affects: safety and compliance, knowledge.
- **D-03:** One live run records the model's replies and Jev's scores together, against the candidate, and sends nothing. Re-recording a reply re-asks Jev about the new words. The command is `walks:record`, and Ian runs it per client. Nothing re-records automatically. Affects: knowledge, operations.
- **D-04:** A recording is stale when the fingerprint of what was read has changed. The fingerprint covers the front desk prompt as built from the folder and the questions Jev is asked from `rules/triage.md`. A stale walk is still replayed and every code-made check still runs; it is reported, never refused. A stale walk is never shown to the owner as something to act on: the owner-facing line says the change passed its checks and nothing is needed from them. The changelog line for Ian names each stale walk and the `walks:record` command. Affects: knowledge, operations, safety and compliance.
- **D-05:** A walk with no recording is refused, and the previous version stays live. That covers a new walk file and any change to the walk's own script (any customer turn), because the recording no longer answers that conversation. The owner-facing wording names the file and says the recording is Ian's step; no command is shown to the owner. The changelog line carries the command and the file name for Ian. Ian records a new walk before it goes into a client's folder, so this refusal should be rare. Affects: knowledge, operations.
- **D-06:** Recordings live in the database, keyed by walk file path and fingerprint, never in the business folder. A re-record therefore never changes the folder or mints a version. Tests of the gate path seed recordings as fixtures. A fresh install or a new instance has none until `walks:record` runs (D-26). Affects: knowledge, connectors and data. - **Reversibility:** costly - moving recordings into the folder later needs a migration out of the new table, a sync change so recordings stay out of the fingerprint, and new fixtures.
- **D-07:** A walk runs on a frozen clock. The default start is the candidate's next opening day at 10:00 in the business's time zone. A walk file may name its own start and add waits between turns; the clock moves only by those waits. Affects: knowledge, operations.
- **D-08:** Dana's feature test feeds the example folder's Dana walk file to the same runner, using the suite's existing fakes (`FrontDeskAgent::fake()` and `FakeClassifier`, `tests/Pest.php:70,75`). That is existing suite practice, not a new stand-in. It then asserts the appointment, the owner alert, the customer card, `records/today.md` and the queued reminder. One Dana file, so the walk and the feature test cannot drift apart. Affects: customer communication, operations, owner intelligence.

#### Walk-test files and the failure report
- **D-09:** A walk may state checks after any turn and at the end: for example, after turn 2, "sent on its own" or "carries the owner's price sentence for ac-repair"; at the end, "booked", "owner alerted", "reminder queued". A booking turn either says "books the earliest time offered" or names a time; naming a time doubles as the "expected time is offered" check. Affects: knowledge, customer communication.
- **D-10:** A check that names a day, a date or a clock time is valid only when the walk file also names its start. Otherwise the sync refuses the walk file in plain words naming the file and the field. Without a named start, a walk uses relative wording ("the earliest time offered", "the time it picked"), and the example walks default to relative wording. Affects: knowledge.
- **D-11:** Walks run only when validation passes. A failed walk is one plain line in the same refusal list and changelog entry as today's validation refusals, naming the file, the turn and the check, and saying the previous version stays live. Example: "tests/dana.md, turn 3: the reply did not carry the price sentence for AC repair. v3 stays live." It shows on the Business Folder page where refusals show today. The page gets no new section. Affects: knowledge, team.
- **D-12:** `examples/example-hvac/tests/` ships these gating walks:
  - Dana end to end.
  - The 08-UAT walks: the plain texts that must send, and the two booking messages ("What time are you coming?" booked, "Can you send the link again?" unbooked).
  - One walk per risky check that must hold or hand off: price, discount, promise, complaint, asking for a person. A folder change that would let a risky reply send is then refused at the gate.

  All of them use plain names and fictional customers, with no real client data. The full calibration set stays in the repo's test fixtures, outside the owner's folder. Affects: knowledge, customer communication, safety and compliance.

#### Bar calibration (02 D-47 and D-56 reopened; D-45's intent clause reopened by Ian)
- **D-13:** Measure, then Ian approves. The calibration set holds:
  - the plain texts held today (the three 08-UAT check 4 texts, plain intake answers);
  - texts that must still be held: a real price, discount, promise and complaint, and a real request for a person.

  The proposal shows both sides at each candidate number. If the measured spread shows that no number separates plain from real for a check, stop. Bring Ian a `rules/triage.md` reword for that check, measured the same way, instead of a number that lets real ones send. Affects: safety and compliance, customer communication, knowledge.
- **D-14:** The rule the numbers must meet, across every recorded run: every risky text holds or hands off, and every plain text sends on its own. Where both cannot hold, the risky side wins and D-13's stop applies. Affects: safety and compliance, customer communication.
- **D-15:** These bars may move:
  - `complaint_max_yes` and `promise_max_yes` (0.15; 02 D-56).
  - The asks_for_person band: `gate_check_max_yes` as its unsure floor (0.075) and `asks_for_person_min_yes` (0.85; 02 D-47).
  - `intent_min_confidence` (0.85). This reopens 02 D-45's intent clause and the 0.85 reading of 02 D-03 for intent, Ian's explicit call (2026-11-20).

  These do not move: `price_max_yes` and `discount_max_yes` (0.075; D-45's strict reading as kept by D-56), and `life_safety_min_yes` (0.075; 02 D-46). Affects: safety and compliance, customer communication, knowledge. - **Reversibility:** reversible - values in `config/gate.php`, copied onto every verdict, so the approval record shows what each reply was judged by.
- **D-16:** If the asks_for_person floor moves, `gate_check_max_yes` changes value under its own name. Since D-56, price and discount read their own keys, and `gate_check_max_yes` is read only as the floor (`app/SendRule/TriageReading.php:49`). Stored verdicts keep reading the same, and the comment in `config/gate.php` says plainly that the key is only the floor. Affects: safety and compliance.
- **D-17:** Sequencing:
  1. Plans build `walks:record`, the calibration set and the two-sided report.
  2. Ian runs the live recording.
  3. The bar proposal (or a triage reword, D-13) and the Booking wording results (D-18, D-19) come back to Ian as one decision. The bars and the wording are approved together, against one recording.
  4. A follow-up plan writes the approved values and the chosen wording test-first.

  The phase closes only after that plan. Executors never run the live recording; the never-pause rule for live checks holds. Affects: safety and compliance, customer communication, knowledge.

#### Booking section wording (FrontDeskPrompt `BOOKING_ONLINE`, `BOOKING_VISIT_BOOKED`)
- **D-18:** The same live run (D-17) measures three versions of both sections:
  - "say what the words are for": the facts framed as what the model's own words do, with the code-added line as context it never needs to mention;
  - "drop the mechanics": what to do and what is true for the customer, without saying the system adds a line;
  - today's text, as the baseline, so the effect of the new bars alone is visible.

  Showing the model the owner's line is not measured, so 08 WR-07 stands. The change is to what the model reads; no rule names words to avoid (Ian's METHOD file). Ian picks the winner from the results. Affects: customer communication, safety and compliance, knowledge.
- **D-19:** The pass mark: in every recorded run, "What time are you coming?" from a booked customer and "Can you send the link again?" from an unbooked one each send on their own, labeled scheduling, with the right parts (`visit_line`, `booking_link`). The 08-UAT offer and lost-link texts run too, as regressions. Whether the words repeat the code-added line is judged by Ian reading the report, never by a code check that names words. Affects: customer communication, safety and compliance.

#### Reply cap (02 D-52 amended)
- **D-20:** 02 D-52 is amended: the reply cap counts texts that answer a customer text. Booking confirmations and reminders (`booking_text`, 08 D-26) no longer count, because a booking or the clock starts them, never an inbound, so they cannot ping-pong. Holding replies and every other kind still count, and the life-safety line stays out as before. The code change is one exception in `replyCapReached` (`app/Actions/RunFrontDesk.php:1581-1591`), test-first. The map's conversation card (`map/objects/conversations/conversation-and-pause.md:21`, `:32`) changes in the same commit as the code, not before. Affects: customer communication, safety and compliance, operations.
- **D-21:** A paused conversation still refuses a booking confirmation or reminder, whether the cap or anything else paused it (`OutboundSender.php:251-252`; 08 D-26, D-27 unchanged): one timeline note, no alert. Affects: customer communication, operations.

#### Stale map rulings (doc-only, ruled in this discuss)
- **D-22:** `escalate_to` is required by `app/Icm/Validator.php:260` and parsed into `AgentFile::$escalateTo` (`app/Ai/AgentFile.php:45`), but no code reads it. The docs now say the team room is meant to read it, with the wiring parked with the Owner Loop (backlog 999.5). Reworded: `docs/BLUEPRINT.md` (Sales hand-off line and the team "Built" line), `docs/SPEC.md` (ownership table, `agents/*.md` row), `examples/example-hvac/rooms/team/CONTEXT.md` and the `TeamServiceProvider` docblock. The team room's `reads:` front matter keeps `agents/front-desk.md` (it states intent), so the sync and `TeamBootTest` are unchanged. This is a wording clarification of 04 D-12's table row ("team (reads `escalate_to`...)"), which now reads as "meant to read"; 04-CONTEXT.md itself is not edited. Affects: team, knowledge, customer communication.
- **D-23:** `intake_answers.icm_version_id` is written at `RecordIntakeAnswers.php:58` and read by nothing. It was parked "for Phase 3", which shipped as Phase 8 without reading it. Its purpose (telling which wording of a question the customer answered) is re-parked as a before-first-client item in BLUEPRINT's customer communication area. `map/CLAUDE.md`'s ledger line and the intake card point there instead of Phase 3. The shipped migration file is untouched. Affects: customer communication, knowledge.
- **D-24:** `map/CLAUDE.md`'s Collisions line "handled / claimed / dispatched" gains `claim_token`: the run that claimed a text, written with `handled_at` by the same update (`app/Actions/ClaimInboundBurst.php:43`, Phase 6). Affects: customer communication, team.

#### Items placed elsewhere by this discuss
- **D-25:** How Ian learns that a client's walks are stale (D-04) or unrecorded (D-05), an operator notice, goes on BLUEPRINT's before-first-client list. It is not built in Phase 10. It also covers D-05's case, so a client's unrelated edits do not wait on Ian noticing. Affects: knowledge, operations, team.
- **D-26:** A Phase 12 input (ROADMAP Phase 12 planning notes and BLUEPRINT): at first deploy and on any new instance, run `walks:record` for each business before its first sync. Otherwise every walk refuses as unrecorded (D-05, D-06). Affects: knowledge, operations, safety and compliance.

Also carried (from "Locked, carried forward"): DEC-01 to DEC-14 (PROJECT.md); 02 D-01 to D-56 except as reopened or amended; 02.1 D-01 to D-51 (D-49 as amended by 08 D-26); 04 D-01 to D-27; 05 D-01 to D-33; 06 D-01 to D-21; 07 D-01 to D-23 (D-21 and D-23 as amended); 08 D-01 to D-46 (D-39 as amended 2026-11-11); 09 D-01 to D-29. 08 WR-07 stands: the Booking section quotes no owner line and holds no booking address (D-18). WCAG 2.2 Level AA, the chokepoint rule and the 2026-11-11 visual rule (CLAUDE.md, Rules). Carried facts: the code reads business configuration only through `App\Icm\ActiveConfig`; a refused sync keeps the previous version live; walk tests belong to the house and no room may own one (`app/Icm/RoomChecks.php:332`); every customer text goes through `OutboundSender`.

### Claude's Discretion
- How a walk is kept off live data (a rolled-back transaction or a separate scratch database), within the limits above: no real text, no live row, no owner alert.
- How fine-grained the fingerprint is (per walk or per turn), so long as D-04 and D-05 hold.
- The walk file's field syntax inside SPEC's persona, channel, script and expect, and the plain wording of each refusal line, in the house's existing refusal style.
- The recordings table's shape and how the gate's replay classifier is built (`FakeClassifier` is the nearest pattern).
- How the two-sided calibration report is laid out for Ian.

### Deferred Ideas (OUT OF SCOPE)
- Operator notice for stale or unrecorded walks: BLUEPRINT before-first-client list (D-25).
- `walks:record` before the first sync on any new instance: Phase 12 input (D-26).
- Automatic re-recording in the background: not chosen (D-03); revisit only with the operator notice.
- A reader for `intake_answers.icm_version_id`: before-first-client list (D-23).
- Wiring `escalate_to`: Owner Loop, backlog 999.5 (D-22).
- Reviewed todo, not folded: phone design pass (`.planning/todos/pending/2026-10-17-phone-design-pass.md`), Phase 11; a looks-only finding on the walk failure lines goes there.

### Binding for this planning run (Ian, from the orchestrator prompt)
1. D-17 split: plans build `walks:record`, the replay (recorded model replies plus Jev's RAW scores, CURRENT `config/gate.php` bars applied at gate time), the calibration set and the two-sided report. No task runs `walks:record`, the live model or Jev; no CI job calls a live provider. The approved-values plan (bars, triage reword, Booking wording) is NOT planned now.
2. Test-first per wave (tdd_mode on). Name, by file and line, each EXISTING test the change forces to edit.
3. D-20 is test-first in `replyCapReached` and the same commit updates `map/objects/conversations/conversation-and-pause.md` (:21, :32). Any plan changing a file the map cites updates that card in the same commit and runs `python3 ~/Projects/cartographer/tools/check_map.py ~/Projects/ops-platform --extra-root examples/example-hvac`.
4. Accessibility: a Pest browser test in CI for the walk lines on the Business Folder page (read in order after the section heading and status line, at desktop and at 375 wide), Phase 7 pattern; contrast exempt (known Phase 11 gap, 10-UI-SPEC.md).
5. Examples stay fictional; walk files and the calibration set use invented customers. Customer- and owner-facing strings: no exclamation points or em dashes.
6. Not in scope: operator notice (D-25), Phase 12 walks:record step (D-26), `escalate_to` wiring, an `icm_version_id` reader, any design work. D-22/D-23/D-24 are doc-only and in scope, but see Finding F-1 (already landed).
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| WALK-01 | Walk-test files (`tests/*.md`, type `walk_test`: persona, channel, script, expect) are validated by the sync with plain-words errors and compiled into the candidate version. | Loader moves `walk_test` from `LATER_SPRINT` to `TYPED` (Loader.php:24, :31); `compile()` needs an explicit list arm or the last walk file silently wins (FolderSync.php:305); validation lives in a new `WalkFileChecks` appended to the existing `array_push` at Validator.php:125 so no cited line moves; syntax proposed in "Walk file syntax". |
| WALK-02 | Every sync walk-tests the candidate; activation only if validation and every walk pass; otherwise previous version stays live, message names test and check, attempt appended to `changelog.md` and shown on the Business Folder page. | Gate runs in `FolderSync::sync()` after validation and the unchanged check, before the activation transaction (FolderSync.php:79-92); candidate read through a scratch database whose `icm_versions` row is the candidate; refusal flows through `reject()` (errors stored on the rejected `IcmVersion`, which the page already reads, SyncStatus.php:41-48), the Sync Now notification (BusinessFolder.php:239-243) and `Changelog` (Changelog.php:40). Two line forms carried by two new optional `SyncResult` fields. |
| WALK-03 | "Dana end to end" passes as a walk test and as a feature test. | One Dana walk file in `examples/example-hvac/tests/`, run by the same `WalkRunner` the gate uses; feature test records it with the suite's fakes then runs the gate. Dana needs a NAMED start, a NAMED time and a wait (see Finding F-5): the earliest offered time can never queue a reminder, and `today.md` only lists a visit on its own day. |
</phase_requirements>

## Summary

The gate is a new step in `FolderSync::sync()` between the unchanged check and the activation transaction (FolderSync.php:79-92). It builds a throwaway "scratch world" for each walk, drives the front desk's real path in-process (RecordInbound, the held ProcessInbound job, RunFrontDesk, OutboundSender, BookAppointment), and judges the result with code-made checks. The model and Jev are replaced inside the scratch world only: a `ReplayFrontDeskAgent` (subclass of `RunFrontDeskAgent`, in `App\Ai`) returns recorded structured replies, and a `ReplayClassifier` (beside `FakeClassifier`) returns recorded RAW `ClassifierResult`s. Because `TriageReading::read()` is called with `config('gate.classifier')` at the moment of use (ReadCustomerText.php:53, DecideSendOrDraft.php:188), the CURRENT bars apply to recorded raw scores with no extra code (D-02).

**Recommendation on isolation (Claude's Discretion): use a scratch SQLite database, not a rolled-back transaction.** A transaction on the live connection lets a walk read live appointments, schedule blocks, users, contacts and open outages; `Availability::openStarts` reads every live appointment (Availability.php:55, `bookedSpans` at :133), so a real booking could steal "the expected time" and falsely refuse a client's sync. A scratch database is an empty, deterministic world; full migration of all 39 migrations took 0.30 s wall including boot [VERIFIED: ran `php artisan migrate` against a temp sqlite file this session]. It also removes the pgsql sequence burn, the `ShouldDispatchAfterCommit` deferral (CustomerRecordChanged.php:9), unique-job cache locks, and nested-savepoint behaviour on two engines.

`walks:record` is the same runner with real collaborators wrapped by recording decorators; every collaborator is a container binding, so the whole command is testable offline with `FrontDeskAgent::fake()` and `FakeClassifier` exactly as `tests/Pest.php:70,75` already arranges. The two-sided report re-applies the real `TriageReading::read()` to the recorded raw scores under each candidate bar, and prints, per movable bar and candidate number, which plain texts would be held and which risky texts would send, with the D-13 stop when no number separates them.

**Primary recommendation:** Build in this order, test-first: (1) D-20 reply-cap exception (independent, one line, line-neutral so the ~40 map citations after RunFrontDesk.php:1591 stay true); (2) walk-file Loader/compile/validate (WALK-01) plus the Pest.php change that strips `tests/` from every test's folder copy; (3) `ScratchWorld` + replay seams + `WalkRecording` table; (4) `WalkRunner` + check vocabulary + `WalkGate` wired into `FolderSync` with the two line forms; (5) `walks:record`, Dana and the example walks, the calibration set and report; (6) the browser test and doc edits.

## Findings the planner must not miss

- **F-1. D-22, D-23, D-24, D-25 and D-26 doc edits already landed in commit 919dc18** ("docs(10): capture phase context"): SPEC.md:227, TeamServiceProvider.php:15, examples/example-hvac/rooms/team/CONTEXT.md:15, BLUEPRINT.md:172/231/436/577/736, map/CLAUDE.md (ledger and Collisions lines), map/objects/intake/intake-answer.md:26, ROADMAP.md:445 [VERIFIED: `git show --stat 919dc18`, grep this session]. Plan these as a verification-only task (grep for the words), not as edits.
- **F-2. Existing example folder has no walk files** (`examples/example-hvac/tests/` holds only README.md) [VERIFIED: `find` this session], and `tests/README.md:3` still says "arrive in Sprint 2"; `examples/example-hvac/CLAUDE.md:13` says "(not live yet)"; `docs/SPEC.md:89` and `:195` say "Sprint 2". Doc edits needed.
- **F-3. 492 call sites of `activateExampleFolder()` in 141 test files** each sync a copy of `examples/example-hvac` made at `tests/Pest.php:85-88`. Once walk files ship in the example, every one of those syncs would run the gate and refuse as unrecorded. The fix is one line in the shared copy step (strip the walk files), not 141 edits. Baseline: 3347 tests, 26094 assertions, passed, 330 s locally on sqlite [VERIFIED: `php artisan test --compact` this session].
- **F-4. `compile()` default arm overwrites** (FolderSync.php:305): `default => $compiled['index'][$file->type] = $file->path` would keep only the LAST walk file. Needs `'walk_test' => $compiled['index']['walk_test'][] = $file->path` and the init array (FolderSync.php:281) needs the key.
- **F-5. Dana cannot use "the earliest time offered" if she is to get a reminder, and `today.md` only lists a visit on its own day.** `ReminderSchedule::rows` skips a reminder whose due time is at or before the booking time (ReminderSchedule.php, `if ($due->lessThanOrEqualTo($bookedAt)) continue;`), and booking.md ships `reminder_hours_before: [24]`. With the default start (next opening day 10:00) and lead 120 minutes, the earliest offered start is 12:00 the same day, 24 hours before it is yesterday, so no reminder row. On a Saturday start (hours 09:00 to 13:00, 90 minute service) the earliest offer is Monday 08:00 and a reminder DOES queue, so the outcome would depend on the weekday of the sync. `TodayRecord::body($now)` lists visits that START on the business-zone day of `$now` (TodayRecord.php:57-71). So Dana must name a `start`, name a booking time at least a day and an hour later, and end with a `wait` into the visit day if `today.md` is asserted. Same time-bomb elsewhere: `business/offers/first-visit-promotion.md` has `end_date: 2026-12-31`, so a discount walk without a named start fails every sync after that date.
- **F-6. A walk on the live connection would see real bookings.** See Summary. This is why scratch database is recommended.
- **F-7. `RaiseOwnerAlert::handle` sends the owner a text at once when the alert is due** (RaiseOwnerAlert.php, `if ($alert->wasRecentlyCreated && $dueAt->lessThanOrEqualTo(now())) { $this->sendOwnerAlert->handle($alert); }`) and `forBusiness()` always does. The scratch world must therefore make `TextChannel` resolve to a private `FakeTextChannel` or the owner gets real texts from a gate run.
- **F-8. `ArchitectureTest.php` constrains where new code may live** (see "Architecture constraints").
- **F-9. No existing test asserts the Loader skips `walk_test`, and none asserts booking texts count against the reply cap.** The only `walk_test` fixtures are three strings (RouterClaimTest.php:56, RoomOwnershipTest.php:80, and the dataset type list at RoomRegistryTest.php:85). The only forced edits are listed in "Existing tests this phase forces to change".
- **F-10. `map/CLAUDE.md` Collisions line and ledger already carry D-23/D-24.** `check_map.py` currently PASSES (600 path:line citations, 0 failures) [VERIFIED: ran this session].

## Architectural Responsibility Map

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|-------------|----------------|-----------|
| Read and compile walk files | API / Backend (sync: Loader, FolderSync) | Database (compiled JSON on `icm_versions`) | Only the Loader reads the folder (ArchitectureTest.php:3-9, app.md rule) |
| Validate walk files, plain-words refusals | API / Backend (Validator, new WalkFileChecks) | Frontend Server (Business Folder page shows lines) | Same `<path>: <message>` pipeline as every other type |
| Run walks against the candidate (the gate) | API / Backend (new `App\Icm\Walks`) | Database (scratch SQLite, recordings table) | A sync step; no browser or page involvement |
| Replay model and Jev | API / Backend (`App\Ai`, `App\SendRule` seams) | Database (recordings) | Must sit at the two container seams the front desk already resolves |
| Hold a refusal and show it | Frontend Server (Filament page, notification) | Database (`icm_versions.errors`), Files (`changelog.md`) | Page reads the rejected row; no new section (D-11) |
| Reply cap exception (D-20) | API / Backend (`RunFrontDesk::replyCapReached`) | Docs (map card) | One code line plus the map card |
| Calibration report | API / Backend (console command) | Database (recordings) | "Not a screen" (UI-SPEC Out of scope) |
| Accessibility of walk lines | Browser (Pest browser test, axe) | Frontend Server (existing `<p>` markup) | Contrast exempt, Phase 11 |

## Standard Stack

No new package is needed. Everything below is already installed [VERIFIED: `composer show --direct`, `php -v`, `node --version`, `npx playwright --version` this session].

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| laravel/framework | 13.35.0 | Container (`offsetUnset`, `getBindings`, `isShared`, `forgetInstance`: Container.php:275, 1666, 1735, 1865), `DatabaseManager::setDefaultConnection`/`purge` (DatabaseManager.php:404, 307), migrator for the scratch DB | The scratch world is built from stock Laravel pieces |
| laravel/ai | 1.2.0 | `FrontDeskAgent` and its `fake()` (Promptable.php:554, `Ai::fakeAgent`) | Used only in `RunFrontDeskAgent` (arch rule); the replay agent does NOT use the SDK fake in production code |
| nesbot/carbon (via framework) | 3.14.2 | `Carbon::setTestNow` sets the default factory shared by Carbon and CarbonImmutable (vendor/nesbot/carbon/src/Carbon/Traits/Test.php:50-53); `Carbon::getTestNow()` for restore | Frozen clock |
| pestphp/pest | 5.3.1 | Tests, `arch()` | Project standard |
| pestphp/pest-plugin-browser | 5.1.2 | `visit()`, `resize()`, `->on()->mobile()`, bundled axe (`window.axe`) | Phase 7 and Phase 8 pattern |
| filament/filament | 5.10.1 | Business Folder page, Sync Now notification | Unchanged; no new component |
| PHP | 8.5.12 | `pdo_sqlite` and `pdo_pgsql` both present locally | New runtime need: `pdo_sqlite` on the deployed host (A3) |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Playwright (npx) | 1.63.0 local; version read from package-lock in CI | Browser test | The 375-wide walk-line test |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Scratch SQLite database | Rolled-back transaction on the live connection | Transaction is cheaper (no migrate) but reads live appointments/blocks/users, burns pgsql sequence values, defers `ShouldDispatchAfterCommit` events to an outer commit that never comes, and writes (even if rolled back) to live tables. Rejected for the gate. |
| `ReplayFrontDeskAgent extends RunFrontDeskAgent` | `FrontDeskAgent::fake(Closure)` inside production code | The SDK fake is global state on `AiManager`, and the arch rule limits `FrontDeskAgent` to `App\Ai`. A bound subclass is local to the scratch world and restores cleanly. |
| `CaptureDispatchInbound extends DispatchInbound` | `Queue::fake([ProcessInbound::class])` in production code | `DispatchInbound` is documented as "the only place ProcessInbound is queued" (DispatchInbound.php:23); capturing there needs no framework fake and works the same under the test suite's own `Queue::fake` (Pest.php:81). |

**Installation:** none. `composer.json` is unchanged.

## Package Legitimacy Audit

No external package is added by this phase, so no `package-legitimacy check` is needed. All names above come from `composer show --direct` on this repo.

| Package | Registry | Age | Downloads | Source Repo | Verdict | Disposition |
|---------|----------|-----|-----------|-------------|---------|-------------|
| (none added) | - | - | - | - | - | - |

**Packages removed due to SLOP verdict:** none
**Packages flagged SUS:** none

## Architecture Patterns

### System Architecture Diagram

```
  owner edits folder / Ian runs icm:sync / owner clicks Sync Now
                         |
                  FolderSync::run()  (cache lock icm:sync)
                         |
   Loader::load  ->  Validator::validate (+ RoomDropChecks)      <- WALK-01: walk files typed,
                         |                                          WalkFileChecks refuses bad ones
           errors? --yes--> reject() ---------------------------------------------+
                         |no                                                       |
           fingerprint == live? --yes--> Unchanged (no walks run) ----------------+--> recorded()
                         |no                                                       |   (Changelog)
        WalkGate::run(candidate compiled, live version)           <- NEW (WALK-02)  |
          for each walk file, path order:                                          |
            lookup WalkRecording(path, script_hash)                                |
              none -> UNRECORDED (refuse)                                          |
            ScratchWorld::open(candidate)                                          |
              scratch sqlite + migrate, candidate IcmVersion row (Active)          |
              swap: default DB, icm.path, clock, TextChannel, Classifier,          |
                    RunFrontDeskAgent, DispatchInbound, queue, cache, http         |
            WalkRunner: per script step                                            |
              says  -> RecordInbound -> (captured ProcessInbound) -> RunFrontDesk  |
                       -> ReplayFrontDeskAgent / ReplayClassifier (raw scores,     |
                          current bars via TriageReading) -> OutboundSender        |
                          -> fake channel                                          |
              wait  -> move frozen clock                                           |
              book  -> Availability / BookAppointment::fromLink                    |
              checks after each turn and at the end (code-made, from scratch DB)   |
            ScratchWorld::close() (restore everything, drop DB, delete temp dir)   |
          result: failures[], unrecorded[], stale[]                                |
         refused? --yes--> reject(): Rejected IcmVersion.errors = OWNER form ------+
                         |no                                                       |
        DB::transaction: supersede + create Active IcmVersion (as today)           |
        forgetInstance(ActiveConfig), queueCardsForRoomChanges -> recorded() ------+
```

### Recommended Project Structure
```
app/Icm/Walks/               # in App\Icm so IcmVersion use stays inside the arch rule
  WalkFile.php               # value object from a compiled walk_test file
  WalkFileChecks.php         # WALK-01 validation, plain words, called from Validator::validate
  WalkCheck.php              # closed check vocabulary: phrase -> parse -> evaluate -> failure words
  ScratchWorld.php           # open()/close(): every swap and its restore
  WalkRunner.php             # runs one walk in a ScratchWorld (Replay or Record mode)
  WalkGate.php               # runs every walk of a candidate; builds WalkGateResult
  WalkGateResult.php         # failures, unrecorded, stale; ownerLines(), ianLines()
  WalkFingerprint.php        # script hash and prompt/triage fingerprint
  CaptureDispatchInbound.php # extends App\Actions\DispatchInbound
  CalibrationSet.php         # reads tests/Fixtures/calibration/*
  CalibrationReport.php      # two-sided tables
app/Ai/
  ReplayFrontDeskAgent.php   # extends RunFrontDeskAgent (arch: FrontDeskAgent only inside App\Ai)
  RecordingFrontDeskAgent.php# wraps a real RunFrontDeskAgent, keeps AgentReply::toArray()
app/SendRule/
  ReplayClassifier.php       # implements Classifier, beside FakeClassifier
  RecordingClassifier.php    # wraps the container's Classifier, keeps ClassifierResult::toArray()
app/Models/WalkRecording.php + database/factories/WalkRecordingFactory.php
database/migrations/<date>_create_walk_recordings_table.php
app/Console/Commands/WalksRecord.php, WalksReport.php   # #[Signature]/#[Description] attributes like IcmSync.php:11-13
examples/example-hvac/tests/*.md                         # the D-12 walks
tests/Fixtures/calibration/                              # the calibration set (D-12: outside the owner's folder)
```
Do not create a base folder under `app/` (CLAUDE.md "Stick to existing directory structure"); `app/Icm/Walks/` is inside an existing base.

### Pattern 1: Scratch world (isolation)
**What:** one object that snapshots, swaps and restores every global a walk touches, always in `finally`.
**Swaps, and why each is needed:**

| Global | Swap | Why | Restore |
|--------|------|-----|---------|
| Default DB connection | `config(['database.connections.walk' => [...config('database.connections.sqlite'), 'database' => ':memory:', 'url' => null]])`, `DB::setDefaultConnection('walk')`, `Artisan::call('migrate', ['--database' => 'walk', '--force' => true])` | Empty deterministic world; no live row read or written; queue and database cache stores follow the default connection into the scratch DB | `DB::setDefaultConnection($original)`, `DB::purge('walk')` |
| Candidate version | Insert the candidate as the Active `IcmVersion` in the scratch DB (id 1) | `intake_answers.icm_version_id` is a foreign key (migration 2026_10_12_000007:23) and `ActiveConfig::version()` reads the newest Active row (ActiveConfig.php:27-33) | Dropped with the DB |
| `ActiveConfig` | `app()->forgetInstance(ActiveConfig::class)` before the walk and after (it is bound `scoped`, AppServiceProvider.php:54, and caches `$this->version`, ActiveConfig.php:20) | A held instance would keep serving the candidate (or the live version) after the swap | forgetInstance again |
| `config('icm.path')` | a fresh temp dir | `RecordsWriter::root()` writes `config('icm.path').'/records'` (RecordsWriter.php:46-48); cards and `today.md` must not land in the owner's folder | restore config; `Illuminate\Filesystem\Filesystem::deleteDirectory` (not the `File` facade: ArchitectureTest.php:42-48) |
| Clock | `Carbon::setTestNow($start)`; waits call it again | Frozen clock (D-07). Carbon 3 shares one test-now across Carbon and CarbonImmutable. | restore the PREVIOUS `Carbon::getTestNow()`, never null (tests such as ChangelogTest.php:7-9 set their own) |
| Text channel | `config(['channels.text.driver' => 'fake'])` and `app()->instance(FakeTextChannel::class, new FakeTextChannel)` | `TextChannel` is a closure bind reading that config (AppServiceProvider.php:61-69). Do NOT reference `TextChannel` itself: the arch rule allows it only in OutboundSender, SendOwnerAlert, App\Channels, App\Providers | restore the previous instance or forget it |
| Classifier | `app()->instance(Classifier::class, $replayOrRecording)` | ReadCustomerText and DecideSendOrDraft take `Classifier` by constructor (ReadCustomerText.php:25-29, DecideSendOrDraft.php:45-49) | snapshot first: if `isShared(Classifier::class)` there was an instance (the suite, Pest.php:70) so put it back; otherwise `forgetInstance` (production `bind`, AppServiceProvider.php:58) |
| Front desk model | `app()->bind(RunFrontDeskAgent::class, ...)` | `RunFrontDesk` takes it by constructor (RunFrontDesk.php:137) | `unset(app()[RunFrontDeskAgent::class])` (`offsetUnset`, Container.php:1865) |
| Inbound dispatch | `app()->instance(DispatchInbound::class, new CaptureDispatchInbound)` | `RecordInbound` calls `DispatchInbound::handle` (RecordInbound.php:85); capturing there hands the walk the message so it can run `ProcessInbound::handle` itself, as `runQueuedFrontDeskJobs()` does (Pest.php:174-189) | restore |
| Queue and cache | `config(['queue.default' => 'sync', 'cache.default' => 'array'])`, forget `cache.store` | Belt and braces for non-database drivers; with database drivers they already point into the scratch DB | restore config |
| HTTP | Replay mode only: `Http::preventStrayRequests()` and turn it off after | Any stray request is a bug in the gate; Record mode NEEDS HTTP (real model and Jev) | restore |

**Always** restore in `finally`, in reverse order, and assert in a test that every global is back (see Validation Architecture, ScratchWorldTest).

### Pattern 2: Replay seams
`ReplayClassifier` keys by turn and call kind, not by content: turn N holds `reading` (the call with an empty draft, as FakeClassifier tells them apart, FakeClassifier.php:126) and `words[0..k]` in call order. It builds `new ClassifierResult(model:, intent:, intentConfidence:, intentProbabilities:, yesNo:)` from the stored `ClassifierResult::toArray()` shape (ClassifierResult.php:24-34), so `TriageReading::read()` applies the current bars. A request beyond what was recorded throws `ClassifierFailed` (fail closed, holds the reply) and is noted on the runner so the gate can say so (Open Question 2).

`ReplayFrontDeskAgent::handle(AgentFile, Conversation, Collection, ?Message, bool, ?CarbonInterface): AgentRun` returns `new AgentRun(AgentReply::fromStructured($stored), 'recorded', 'recorded', [], now()->toImmutable())`. `AgentReply::toArray()` and `fromStructured()` round-trip (AgentReply.php:42, :130-145) [VERIFIED: read both]. A turn can need up to two agent calls (cover-both draft, RunFrontDeskAgent.php:25-29), so store a list per turn.

### Pattern 3: Gate placement and the two line forms
Gate after the unchanged return (FolderSync.php:79-90) and before the `DB::transaction` (FolderSync.php:92). Keep `compile()` inside the transaction where it is; call it again for the gate (cheap) so the cited compile lines move as little as possible. Put the gate body in a new private method at the END of the class and call it with a 3-line block in `sync()`.

`SyncResult` is `readonly` with six positional params (SyncResult.php:17-24) and `FolderSync::recorded()` REBUILDS it positionally when the changelog write fails (FolderSync.php:217-224): add the new fields as trailing optional params AND pass them there:

```php
public array $changelogErrors = [], // Ian form; when empty the changelog prints $errors
public array $staleNotes = [],      // Ian form, changelog only (ruling 1, 5)
```
`Changelog::entry` Rejected arm (Changelog.php:40) becomes `[...($result->changelogErrors ?: $result->errors), ...$result->staleNotes, $this->liveBullet()]`; the Activated arm appends `staleNotes` after the notice bullets (Changelog.php:41-44); Unchanged never carries them (Changelog.php:45, ruling 5). `liveBullet()` stays last so the entry keeps its shape (ruling 6).

| State | Page "Last sync" | Sync Now notification | changelog.md |
|-------|------------------|-----------------------|--------------|
| Walk failed | `IcmVersion.errors` = owner lines (SyncStatus.php:41-48 reads it) | `$result->errors` escaped with `e()` (BusinessFolder.php:239-243) | `changelogErrors` (same text) + stale + live bullet |
| Unrecorded | owner form in `errors` | owner form | Ian form in `changelogErrors` + stale + live bullet |
| Live, stale recording | "Live" as today, nothing else | success as today | "Live as vN" entry + one stale bullet per stale walk |

`IcmSync` prints `$result->errors` (IcmSync.php:42); Ian runs it, so printing `staleNotes` as `warn` lines is useful and tests-cheap, but is not in the UI-SPEC (Open Question 6).

### Pattern 4: Walk file syntax (Claude's Discretion; concrete proposal)
Front matter follows the house style (flat YAML, closed words, `PlainWords::render` messages). All checks are plain phrases from ONE closed list in `WalkCheck`, so the validator can say "expect item 2 must be one of ..." with `PlainWords::render('In', ...)` (PlainWords.php, 'In' arm).

```yaml
---
type: walk_test
name: Dana end to end
persona: Dana Whitfield, an AC owner who texts at the start of the workday
channel: sms
start: "2026-12-01 10:00"          # optional; business time zone; required when a check names a day, date or time (D-10)
script:
  - says: "My AC is blowing warm air"
    expect: [sent on its own, labeled intake_question]
  - says: "Not cooling"
  - says: "Over 10 years"
    expect: [sent on its own, labeled scheduling, carries the booking link]
  - books: "2026-12-02 11:00"        # or: books: the earliest time offered
  - wait: 1 day
expect:
  - booked
  - owner alerted
  - reminder queued
  - card updated
  - records updated
---
Dana texts about a warm air problem, answers the intake questions, and books a visit.
```
Rules: `type`, `persona` (text), `channel` (in `sms`, like agent files, Validator.php:252), `script` (list, at least one `says`; items are exactly one of `says`, `wait`, `books`; cap the list, for example 30 items, so a typo cannot run forever), `expect` (list, may be empty when every check sits on a turn). Per-turn and end checks, closed list: `sent on its own`, `held for the owner`, `handed to the owner`, `labeled <label>` (the eight `ReplyLabel` values), `carries the owner's price sentence for <slug>` (D-09's own words), `carries the booking link`, `carries the visit line`, `owner alerted`, and at the end `booked`, `reminder queued`, `card updated`, `records updated`. A `books` turn is "the earliest time offered" or a quoted local time. D-10 detection: any `expect`/`books` text holding a weekday name, an ISO date or a clock time (`3pm`, `15:00`) while `start` is absent is refused, echoing the owner's own words through `PlainWords::oneLine`. Placeholder wording to follow UI-SPEC: "tests/dana.md: expect names a time (3pm) but the walk has no start. Name a start for the walk, or word the check as the earliest time offered."

`persona` is a plain string. The runner assigns a number from a fixed fictional pool (+1202555 0100 to 0199) that is not already in the scratch DB, so persona carries no phone. The contact name arrives from the model's `customer_name` as in life.

### Pattern 5: Fingerprint and recording key (D-04, D-05, D-06)
Two hashes, so "unrecorded" and "stale" stay different states:
- **script_hash** (identity, D-05): sha256 of the ordered `says` texts only. Changing a customer turn changes it, so the recording no longer answers the conversation (refuse as unrecorded). Changing `expect`, `persona`, `wait`, `books` or `start` does not: checks are code and need no recording.
- **fingerprint** (staleness, D-04): sha256 of the front desk prompt as built from the candidate and the triage questions. Build it from a NEW method added at the END of `FrontDeskPrompt` (cited lines stay put): `FrontDeskPrompt::RULES` (FrontDeskPrompt.php:37-72), the agent file `body` and each loaded file's front matter and body in `loadedPaths` order (FrontDeskPrompt.php:136-151, :261-283), `BOOKING_ONLINE` and `BOOKING_VISIT_BOOKED` (:93, :105) with the `{services}` list, and `TriageQuestions::toJevQuestions()` (TriageQuestions.php:52-66) as JSON. Do NOT hash `instructions()` whole: it ends with the customer card, the Booking section for that contact and "Right now" (FrontDeskPrompt.php:141-160, :385-391), which change every run. The recorded provider chain is stored for the report but kept out of the fingerprint to follow D-04 literally (A7). When the BOOKING constants change in the follow-up plan, every recording goes stale on its own, which is the intent.

Table `walk_recordings`: `id`, `kind` string (`walk` or `calibration`, A8), `walk_path` string, `script_hash` string(64), `fingerprint` string(64), `payload` json, `recorded_at` timestamp, timestamps; unique (`walk_path`, `script_hash`). Style per `create_booking_sends_table.php`: `$table->id()`, `$table->json(...)`, a doc comment on `up()` explaining the key, `$table->unique([...])`, `down()` drops the table. Add a model with `#[Fillable]` and a `casts()` method like `IcmVersion`, and a factory (laravel/core rule: new model gets a factory). Re-record is `updateOrCreate` on the unique key (D-06: never touches the folder or mints a version). No money columns, so the integer-cents rule does not apply.

`payload` shape (proposal):
```json
{"version": 1,
 "turns": {"1": {"agent": [ {AgentReply::toArray()} ],
                  "classifier": [ {"kind":"reading","customer_message":"...","draft_reply":"","result": {ClassifierResult::toArray()}},
                                  {"kind":"words","customer_message":"...","draft_reply":"...","result": {...}} ]}},
 "recorded_with": {"agent": {"provider":"...","model":"..."}, "jev_model":"..."}}
```
Keep `customer_message` and `draft_reply` so the report can print them and replay can sanity-check alignment.

### Pattern 6: Frozen clock and "next opening day at 10:00"
`BusinessClock::nextOpening(CarbonInterface $after)` returns the next open moment (BusinessClock.php:50; `openWindowOn` :41 returns `{open, close}` for a day or null). Default start = the day of `nextOpening(realNow)` in the business zone, at 10:00 clamped into that day's window (use the window's open if 10:00 is before it, and fall back to open if 10:00 is at or after close). `BusinessClock` takes `ActiveConfig` (BusinessClock.php:21) so it must be resolved AFTER the scratch world is open. "Now" is read everywhere as `now()` (RunFrontDesk.php:606, :1589) and `CarbonImmutable::now()` (BookingPage.php render), both frozen by one `Carbon::setTestNow`. Messages tie-break on id (Conversation.php:73-76), so a thread of same-second messages keeps its order [VERIFIED: read].

### Pattern 7: Two-sided calibration report
Items come from the recordings table (every classifier call of every recording, labeled by the walk's own expectation: a turn expecting `sent on its own` is plain, one expecting `held for the owner` or `handed to the owner` is risky) plus the calibration set (D-13: the three 08-UAT check 4 texts, plain intake answers, and one real price, discount, promise, complaint and request for a person each, all invented). For each movable bar (D-15) and each candidate number (the current value, each distinct observed score for that question, and midpoints between neighbours), recompute every item with `TriageReading::read($result, $questions, [...config('gate.classifier'), $bar => $candidate])`, then apply the front desk's own reading of that result: reading-level holds are complaint, asks_for_person (hand off) and unsure asks_for_person (`CustomerReading::complaint/asksForPerson/unsureAboutPerson`, CustomerReading.php:129-155); a words check holds when `TriageReading::sendable` is false (DecideSendOrDraft.php:116-118). Print per bar and candidate: plain sent n of N (name the held ones), risky held m of M (name any that would send). D-13's stop is mechanical: for a "yes above the bar holds" question (complaint, promise) a number separates only when `max(plain yes) < min(risky yes)`; otherwise print "No number separates plain from real for <check>. Overlap: a to b. Do not raise this bar; bring a reword of rules/triage.md for <check>." The price, discount and life_safety bars are printed as fixed context, never as candidates (D-15). Stored verdicts also carry raw scores (`gate.checks.classifier.answers`, `gate.customer_reading.answers`; DecideSendOrDraft.php:190-196, CustomerReading.php:51-58) and `gate:export` already prints them (GateExport.php:94-133), so a `--from-verdicts` source is possible later but is not needed to meet D-13.

### Anti-Patterns to Avoid
- **Reading the live database from a walk.** `Availability::openStarts` and `TodayRecord` read all appointments (Availability.php:50-63, TodayRecord.php:80-91).
- **Hoisting `compile()` out of the transaction** just to share it: shifts every FolderSync citation below it for no gain.
- **A required new constructor parameter on `FolderSync` or `Validator`.** `HandBuiltRegistrySyncTest.php:28-33` builds both by hand with 4 and 3 arguments. Build new collaborators inside (like `RoomDropChecks`, FolderSync.php:69) or give them optional defaults.
- **Importing `App\Models\Appointment` into a file that also writes.** `AppointmentWritesTest.php` flags ANY Eloquent write in a file that imports an appointment model. Keep reads of appointments in a separate read-only class from the code that writes recordings.
- **Naming anything `Gate`.** CLAUDE.md map Collisions: `Gate` is Laravel's facade; prose says "walk gate" or "send rule".
- **Putting `FrontDeskAgent`, `TextChannel` or the `File` facade in the new namespace** (ArchitectureTest.php:42-48, :54-56, :58-66).

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Plain-words refusal text | New sentence builders | `PlainWords::render/oneLine`, `ValidationReport` lines, `HiddenCharacters::marked` (ValidationReport.php:41-48) | Owner values echoed in a line can carry line breaks or invisible marks (05 D-19) |
| Reading the folder or YAML | Any YAML or file code in the walk path | `Loader` only (ArchitectureTest.php:3-9) | Arch rule; the gate reads compiled JSON via `ActiveConfig::filesOfType('walk_test')` (ActiveConfig.php:309-315) which already works once `compile()` lists the type |
| Applying bars to scores | Your own threshold logic | `TriageReading::read` (TriageReading.php:43-87) | The single place bars become holds; the report and the gate must agree with the front desk |
| Booking a time | Direct `Appointment` writes | `BookAppointment::fromLink($link, $startsAt, $now)` (BookAppointment.php:109) and `Availability::openStarts/offers` | The same domain calls the booking page makes (BookingPage.php:130-139); needs the `BookingLink` row, not the token (the token is stored only as a hash) |
| Owner alert, card, records | Counting in the runner | `owner_alerts` rows (AlertReason::AppointmentBooked), `CustomerCard::render`, `TodayRecord::body` | Real outputs, from the scratch DB |
| Sending | A second sender | The fake channel behind `OutboundSender` | CONTEXT: no new chokepoint |
| Fake text carrier | A new stub | `FakeTextChannel` (App\Channels) | Already in the repo |
| Migrating the scratch DB | Hand-built schema | `Artisan::call('migrate', ['--database' => 'walk'])` | 39 migrations, 0.30 s measured; the pretend-room pattern shows room migrations join the migrator path |

**Key insight:** every piece of a walk already exists as a named action with a container seam. The new code is orchestration, restore discipline, recordings and wording.

## Runtime State Inventory

Not a rename or migration phase. Omitted. (One adjacent note: the developer's local database will refuse every `icm:sync` of the example folder after this phase ships until `walks:record` has run, D-05 and D-26. Document it in `docs/SETUP.md`.)

## Existing tests this phase forces to change (the most important output)

Verified by reading each file and grepping the whole `tests/` tree this session. Every edit is test-first per wave (tdd_mode).

### Forced edits

| # | File:line | Today | Why it breaks | Required edit |
|---|-----------|-------|---------------|---------------|
| T1 | `tests/Pest.php:85-88` (the `beforeEach` copy: `File::copyDirectory(base_path('examples/example-hvac'), $folder); File::delete($folder.'/changelog.md'); resetRecordsToTrackedFiles($folder.'/records'); config(['icm.path' => $folder]);`) | Every Feature test syncs a copy of the example folder | Once `examples/example-hvac/tests/*.md` walk files exist they are copied, typed and gated; the 492 `activateExampleFolder()` calls (141 files use it or `frontDeskCustomerTexts()`), every `icm:sync` and every Livewire Sync Now would run N walks and refuse them as unrecorded | Add `File::deleteDirectory($folder.'/tests');` after line 86. Then `exampleFolderFileCount()` (Pest.php:319-322, returns 29) stays 29 and its ~25 users (SyncTest, ChangelogTest, ValidatorTest, BusinessFolderPageTest, the room BootTests, JevClassifierTest, AgentFileTest and others) need no edit. Update the doc comment at Pest.php:316-318 to say walk files are stripped. Add helpers: `layExampleWalks(string ...$names)` (copy named files from `base_path('examples/example-hvac/tests')` into `icmPath('tests')`), `minimalWalkFile()` (a valid one-turn walk as a string), and `seedRecording(...)`. Gate tests lay walks in on purpose and add `+ N` to `exampleFolderFileCount()` themselves. |
| T2 | `tests/Feature/Rooms/RouterClaimTest.php:56` (`writeIcmFile('tests/walk.md', "---\ntype: walk_test\n---\nA walk through the front desk.");`) and the expectation at `:59` | Expects the sync refusal to be exactly `['rooms/pretend/CONTEXT.md: tests/walk.md belongs to the house (routers and walk tests); pretend can read it']` (test at `:53-60`) | A `walk_test` file with no persona, channel or script is now a typed file and is refused too, so `rejectedErrors()` gains extra `tests/walk.md: ... is missing` lines and `toBe([one line])` fails | Replace the file text with `minimalWalkFile()` so the only refusal is the ownership one |
| T3 | `tests/Feature/Rooms/RoomOwnershipTest.php:80` (dataset row `'a walk test' => ['tests/walk.md', 'tests/walk.md', "---\ntype: walk_test\n---\nA walk through the front desk.", '...belongs to the house (routers and walk tests); pretend can read it']`) used by the test at `:84-93` | Same | Same | Same: pass a valid minimal walk file in the third element (dataset rows are evaluated at load time, so call a plain PHP string constant or a function defined in the file, not a helper that needs the app) |

### Tests that stay green but must be re-run for the reason given (no edit expected)
| File:line | Asserts | Why it should still pass |
|-----------|---------|--------------------------|
| `tests/Unit/Rooms/RoomRegistryTest.php:85` | a room may not define `walk_test` | `RoomRegistry::RESERVED_TYPES` (RoomRegistry.php:35) still lists it; RoomRegistry.php:209 also rejects anything in `Loader::TYPED` |
| `tests/Feature/Icm/ValidatorTest.php:61`, `tests/Feature/Rooms/RoomFileTypeTest.php:100,111` | the "type must be one of ..." list | They build the expected list from `Loader::TYPED` plus registered types, so `walk_test` joins both sides automatically (Validator.php:1542 uses the same constant) |
| `tests/Feature/Icm/SyncTest.php:56-67`, `ChangelogTest.php:80-88`, `BusinessFolderPageTest.php:138-143` | `automation` is "not supported yet" | `automation` stays in `LATER_SPRINT`; only `walk_test` moves (Loader.php:31) |
| `tests/Feature/Rooms/SpecTablesTest.php:94-108` | every typed and router file of `examples/example-hvac` matches a SPEC ownership row | `tests/*.md` row exists (SPEC.md:237). Do not edit the ownership table |
| `tests/Feature/Rooms/ExampleRouterTest.php:11-28` | the example router names every top-level folder | `tests/` is already named (example CLAUDE.md:13); the "(not live yet)" wording can change |
| `tests/Feature/Rooms/HandBuiltRegistrySyncTest.php:28-33` | a hand-built 4-argument `FolderSync` and 3-argument `Validator` still sync | Do not add required constructor parameters |
| `tests/Feature/Icm/ChangelogTest.php:7-9` and the headings at `:44-100` | `Carbon::setTestNow(2026-10-10 19:32)` makes the entry heading | Only matters if a gate runs; with no walk files it must not run at all. The gate tests must restore the previous test-now exactly |
| `tests/Feature/Quality/TestFolderCopyTest.php:5-15` | records folder holds only the two tracked files | Unaffected |
| `tests/Unit/ArchitectureTest.php:11-19, 42-48, 54-56, 58-66` | arch rules | New code placement (see below) |
| `tests/Unit/AppointmentWritesTest.php` | no Eloquent write in a file that imports an appointment model | Keep walk reads in read-only classes |
| `tests/Feature/FrontDesk/ReplyCapTest.php:78-167`, `PriceSentenceTest.php:465-502`, `WaitingCustomerRepliesTest.php:426-439`, `CustomerReadingTest.php:321-364`, `FrontDeskInvariantTest.php:285,662`, `LifeSafetyTest.php:213` | the cap pauses and alerts; counts AgentReply, holding, code answers; the safety line never counts | None builds a `booking_text` row, so the D-20 exception breaks none (grep of `booking_text` in tests/Feature/FrontDesk finds only GateExportTest.php:178,432, which is about the export) |
| `tests/Feature/FrontDesk/GateExportTest.php:178,432` | booking_text is left out of `gate:export` | Unaffected |

### Tests the phase ADDS to existing files
- `tests/Feature/FrontDesk/ReplyCapTest.php`: add after `:167` a case "does not count booking texts (D-20)" using the existing helper `replyCapAgentTextsSent($conversation, [..], MessageKind::BookingText)` (ReplyCapTest.php:37-47). Also confirm D-21 (a paused conversation still refuses a `booking_text`) is already covered in `tests/Feature/Booking/OutboundBookingKindTest.php` or `BookingSendTest.php` before writing a new case; this research did not open those files, so check first.
- `tests/Feature/Rooms/RoomOwnershipTest.php` and `RouterClaimTest.php`: keep the cases, only the fixture text changes (T2, T3).
- `tests/Feature/Filament/BusinessFolderPageTest.php`: add a Livewire case that a gate refusal lists its lines under Last sync and escapes them (pattern at `:65-93`).

## Map cards that cite files this phase changes

`check_map.py` today: PASS, 600 path:line citations, 140 continuations, 64 file-only, 2 patterns, 0 failures, 0 unchecked [VERIFIED: ran `python3 ~/Projects/cartographer/tools/check_map.py ~/Projects/ops-platform --extra-root examples/example-hvac` this session].

**What it checks** (read from the script docstring and `CHECKS`, check_map.py:1-50): the cited file exists AND is git-tracked (`git ls-files`: a new file cited before it is committed fails as missing); a bare name matches exactly one tracked file (ambiguous-name: do not add a second file with the basename of a cited one); a cited line is not past the end of the file; a card marked `verified:` has a date and at least one citation; the catalog and the card files agree. **What it cannot catch** (its own CANNOT_CATCH list): a line that exists but no longer says what the card claims. So inserted lines pass the checker while silently moving every citation below them. The plan that changes a cited file must re-find and re-write the line numbers by hand, in the same commit.

| Code file this phase changes | Cards and cited lines | What to do |
|------------------------------|-----------------------|------------|
| `app/Actions/RunFrontDesk.php` (D-20, line 1587 only) | `objects/conversations/conversation-and-pause.md`:20 (`:1605`), :21 (`:1581-1591`, `:1586`, `:1587`), :22 (`:1681-1690`), :32; `processes/answer-inbound.md`:27 (`:1581-1591`, `:1681-1690`), :28 (`:1601-1610`), :30 (`:1833`), :35 (`:1786-1825`, `:1829-1831`, `:1833-1843`), :54 (`:1829-1831`); `objects/folder/agent-file-and-version.md`:21 (`:1626-1653`), :29 (`:1601`); `objects/messages/message-and-verdict.md`:22 (`:1786-1804`), :26 (`:1833-1843`), :29 (`:1795`, `:1783`), :34 (`:1856`, `:1879-1889`, `:1778-1780`), :36 (`:1905`, `:1900`), :49 (`:1806`); plus about 80 more citations below 1575 in answer-inbound.md | Make the edit LINE-NEUTRAL: line 1587 becomes `->where(fn ($query) => $query->whereNull('kind')->orWhereNotIn('kind', [MessageKind::LifeSafety, MessageKind::BookingText]));` and the docblock at :1577-1579 is reworded inside its three lines. Then no citation moves. Rewrite the prose in `conversation-and-pause.md`:21 (it says "the booking confirmation and reminders (`booking_text`...) count" and cites `:1586/:1587`) and :32 ("a new kind that must not count would need an exception in `replyCapReached`": the exception now exists). Check `processes/answer-inbound.md`:27 step 8 for any statement that booking texts count (none found). |
| `app/Icm/FolderSync.php` (gate call, `compile` arm and init key) | `objects/folder/agent-file-and-version.md`:21 (`FolderSync.php:301`), :26 (`FolderSync.php:299`) | The gate call adds about 3 lines above `compile()`; re-find the `offer` and `agent` arms and update both cites |
| `app/Icm/Loader.php` (`walk_test` to `TYPED`) | `agent-file-and-version.md`:26 (`Loader.php:24`) | Line-neutral if `'walk_test'` is added to the same line 24 and removed from line 31 |
| `app/Icm/Validator.php` (append `WalkFileChecks` call) | `agent-file-and-version.md`:18 (`:250-261`, `:252`), :19 (`:714-733`, `:727`, `:765`), :26 (`:56`, `:229-235`, `:262-274`, `:275-282`, `:842`, `:867-1017`, `:1072`), :29 (`:260`) | Line-neutral: append `...(new WalkFileChecks)->errors($files)` to the existing `array_push(...)` line (Validator.php:125); do not add a `fileErrors` match arm (walk_test falls to the `default` arms at :159 and :314, which return `[]` for a type no room defines, which is what we want) |
| `app/Icm/ActiveConfig.php` | `:27`, `:193`, `:282`, `:296`, `:323` (agent-file card :21, :26, :32; message-and-verdict :39) | No change needed: `filesOfType('walk_test')` exists |
| `app/Filament/Pages/BusinessFolder.php` and the Blade view | `agent-file-and-version.md`:21 (`:92`), :26 (`:156`), :38 (`:100`) | No change needed: lines reach the page through `IcmVersion.errors` |
| `app/Ai/FrontDeskPrompt.php` (fingerprint method) | cites at :37-72, :93, :105, :135-169, :197, :208, :223, :249-252, :261-283, :293-306, :336, :416-421 across 5 cards | Add the new method AFTER `textOf` (end of class, line 469); nothing moves. The BOOKING wording changes belong to the later plan and will move citations then |
| `app/SendRule/*`, `app/Ai/RunFrontDeskAgent.php`, `app/Actions/DispatchInbound.php`, `ProcessInbound.php`, `config/gate.php`, `GateExport.php` | `TriageReading.php:61-65`, `RunFrontDeskAgent.php:47,60-66,90`, `config/gate.php:41,71-80,101-103,124-127,142`, `GateExport.php:30,58,94-133,97,141` | Unchanged by this phase. New classes are added beside them. `config/gate.php` changes only in the later approved-values plan |
| New map content | `map/processes/answer-inbound.md` "If you change this / Hits" (line 57) | Add one sentence: the walk gate (`app/Icm/Walks/WalkRunner.php`) drives this process against a candidate, so reordering steps 1 to 21 changes what a walk sees. Only cite files that are already committed |

Each plan that edits a row above ends its last task with the `check_map.py` command from the orchestrator prompt and updates the card in the SAME commit.

## Architecture constraints from existing arch and scan tests

- `tests/Unit/ArchitectureTest.php:11-19`: `IcmVersion` only in `App\Icm`, `App\Console\Commands`, `App\Filament\Pages`, `App\Models`, `Database\Factories`, `Database\Seeders`, `Tests`. Walk code that touches `IcmVersion` lives in `App\Icm\Walks`.
- `:42-48`: the `File` facade only in `App\Icm\Loader`, `App\Icm\Changelog`, `App\Records\RecordsWriter`. Use `Illuminate\Filesystem\Filesystem` (a different class) or native `mkdir`/`sys_get_temp_dir()` in the scratch world. Also `:21-40` forbids `file_get_contents`, `fopen`, `glob`, `scandir` in `App\Http, Actions, Livewire, Filament, Channels, Jobs, Models, Records, Rooms, Listeners, Events, Support` (not `App\Icm`).
- `:54-56`: `App\Ai\Agents\FrontDeskAgent` only in `App\Ai` and Tests, so the replay and recording agents live in `App\Ai` and never name `FrontDeskAgent`.
- `:58-66`: `App\Channels\TextChannel` only in OutboundSender, SendOwnerAlert, App\Channels, App\Providers, Tests: set `channels.text.driver` and instance `FakeTextChannel`, never bind `TextChannel`.
- `tests/Unit/AppointmentWritesTest.php` and `ProjectWritesTest.php`: source scans for table writes.

## Common Pitfalls

### Pitfall 1: only the last walk file is compiled
**What goes wrong:** `compile()` falls into `default => $compiled['index'][$file->type] = $file->path` (FolderSync.php:305) and a second walk overwrites the first.
**How to avoid:** explicit list arm plus `'walk_test' => []` in the init array (FolderSync.php:281). Test with two walk files.
**Warning signs:** `ActiveConfig::filesOfType('walk_test')` returns one path.

### Pitfall 2: the gate refuses the whole test suite
**What goes wrong:** see T1. **How to avoid:** strip `tests/` in Pest.php:85-88 in the same commit that first ships a walk file.

### Pitfall 3: a walk reads live bookings
See F-6. **How to avoid:** scratch database. Assert in a test that a live `Appointment` in the real DB does not change the times a walk is offered.

### Pitfall 4: Dana books but no reminder queues, or `today.md` is empty
See F-5. **How to avoid:** named start, named booking time at least 25 hours after the booking moment, then a `wait` into the visit day for the `records updated` check. Verify with `ReminderSchedule::rows` unit expectations already in `tests/Unit/Booking/ReminderScheduleTest.php`.

### Pitfall 5: a walk that passes on Tuesday and fails on Saturday
**What goes wrong:** the default start is "next opening day"; Saturday's window is 09:00 to 13:00 and Sunday is closed (examples/example-hvac/business/hours.md), so "earliest time offered" moves. **How to avoid:** relative wording only for checks that are weekday-proof (sent on its own, labeled); name a start for anything about times; run the example walks in a test at a fixed real date for each weekday.

### Pitfall 6: a held container instance leaks the candidate or the scratch DB
**What goes wrong:** `ActiveConfig` is `scoped` and caches its version (ActiveConfig.php:20, AppServiceProvider.php:54). A Livewire Sync Now request that rejects would render the page from a stale instance. **How to avoid:** `forgetInstance` on open and in `finally`; a test that after a REJECTED sync `app(ActiveConfig::class)->version()->id` is the live one.

### Pitfall 7: restoring the clock to null
**What goes wrong:** tests (`ChangelogTest.php:7-9`, `travelTo` in 40 other files) set `Carbon::setTestNow`. Clearing it breaks them. **How to avoid:** `$previous = Carbon::getTestNow(); ... Carbon::setTestNow($previous)`.

### Pitfall 8: owner gets a real text from a gate run
See F-7. **How to avoid:** the channel swap, plus a `ScratchWorldTest` that sets `channels.text.driver` to `twilio` before the gate and asserts no HTTP request and the real driver is back after.

### Pitfall 9: replay drift that is not "stale"
A candidate can make the front desk ask for MORE model or Jev calls than were recorded. The replay agent and classifier must fail closed and the gate must say so in plain words (turn number, "the front desk needed a model reply that was not recorded"), count it as a failure, and keep it distinct from stale (Open Question 2).

### Pitfall 10: owner-facing text carries `!` or an em dash
The gate lines, the walk-file refusals and the Ian-form lines are new owner-facing strings. Add a test that scans every string the gate can produce for `!` and the em dash character (precedent: validators refuse them in owner lines).

### Pitfall 11: Sync Now runs the gate inline
Filament runs `FolderSync::run()` inside the request (BusinessFolder.php:228). Per walk cost is about 0.15 s of migration plus the run. Keep the example set small, build the scratch DB once per walk, and record timings in the UAT note (UI-SPEC "loading" backstop). A time budget on the whole gate that refuses in plain words is cheaper than a web timeout.

### Pitfall 12: example offer and closures are dated
`first-visit-promotion.md` ends 2026-12-31; `hours.md` closes 2026-11-26 and 2026-12-25. Any walk about offers or times names a start inside those dates.

## Code Examples

### The gate hook in `FolderSync::sync()` (shape, not final code)
```php
// after the unchanged return (FolderSync.php:79-90), before DB::transaction (FolderSync.php:92)
$refusal = $this->refusedByWalks($folder, $compiledFiles->all(), $active, $fileCount, $fingerprint, $root);

if ($refusal !== null) {
    return $refusal;          // a SyncResult from rejectedByGate(), already recorded()
}
```
`refusedByWalks()` returns null when the candidate has no walk files (so every existing test and every folder without walks behaves exactly as today) or when every walk passes; it carries stale notes forward to the Activated result.

### Line forms (placeholders from 10-UI-SPEC.md)
```
failed   (owner = Ian = changelog): tests/dana.md, turn 3: the reply did not carry the price sentence for AC repair. v3 stays live.
         nothing live:              tests/dana.md, turn 3: ... Nothing is live yet.
unrecorded owner:                   tests/dana.md: this walk has no recording yet. Recording it is Ian's step. v3 stays live.
unrecorded Ian (changelog only):    tests/dana.md: no recording. Run `walks:record` for tests/dana.md. v3 stays live.
stale Ian (changelog only):         tests/dana.md is stale: its recording is older than the front desk prompt or the triage questions. It still ran and passed its checks. Run `walks:record` to refresh it.
```
One failing line per failed walk, naming the FIRST failed check in the order the walk states them (ruling 2). Every line passes `HiddenCharacters::marked` and `PlainWords::oneLine` for any echoed owner text.

### Replay classifier (shape)
```php
final class ReplayClassifier implements Classifier
{
    public function __construct(private array $turns) {}   // from WalkRecording.payload
    public function classify(string $customerMessage, string $draftReply, TriageQuestions $questions): ClassifierResult
    {
        // reading when $draftReply === '' (FakeClassifier.php:126), else the next recorded words check of the current turn
        // build ClassifierResult from the stored toArray() shape; raw scores only, never a verdict
        // nothing left to give: throw new ClassifierFailed('...')
    }
    public function startTurn(int $n): void {}
}
```

### Recording in tests with the suite's own fakes (WALK-03, D-08)
```php
scriptFrontDeskReplies(...$danaReplies);                     // Pest.php:149-168, FrontDeskAgent::fake
fakeClassifier()->answerReading('intake', 0.97)->answer('intake', 0.97);   // FakeClassifier.php:47,62
$this->artisan('walks:record', ['walk' => ['tests/dana.md']])->assertExitCode(0);
// then lay the walk, sync, and the gate replays what was just recorded
```
A recording test needs different scores per call; extend `FakeClassifier` with an optional queue (`answerSequence()`), or define a small test-only subclass in `tests/`. `FakeClassifier` has no map citations, so editing it is free.

## State of the Art

| Old approach | Current approach | When changed | Impact |
|--------------|------------------|--------------|--------|
| Walk tests skipped with a notice (`LATER_SPRINT`) | Typed, validated, compiled, gated | This phase | `Loader.php:31` loses `walk_test`; SPEC.md:89 and :195 lose "Sprint 2" |
| Booking confirmations and reminders count against the reply cap | They do not (D-20) | This phase | 08-UAT check 4 pause no longer happens |
| Bars `0.15` and `0.85` set by reading 02 D-03 | Measured from recorded raw scores, Ian approves | After Ian's live run (not planned now) | Report is the input |

**Deprecated/outdated:** `examples/example-hvac/tests/README.md` ("arrive in Sprint 2"), the example router's "(not live yet)" for `tests/`.

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|---------------|
| A1 | A scratch SQLite database is the better isolation than a rolled-back transaction (CONTEXT leaves it to Claude). Reasoning above, but Ian has not ruled. | Summary, Pattern 1 | If Ian prefers a transaction, the plan must also delete live schedule rows inside it, which is a live-row write |
| A2 | Production queue and cache are the Laravel defaults (database); not read from `.env` (access denied this session). The scratch world sets sync and array anyway | Pattern 1 | Low; both are overridden for the walk |
| A3 | The deployed host has `pdo_sqlite` (present locally and in the CI `extensions:` list, `.github/workflows/tests.yml`) | Environment Availability | A host without it cannot run the gate; the gate must then refuse in plain words, never skip |
| A4 | A recorded call that is missing (the candidate needs more model or Jev calls than were recorded) is a gate failure, not a stale note | Pitfall 9, Open Question 2 | Too strict: a harmless prompt tweak refuses a sync; too lax: a changed flow slips through |
| A5 | `persona` is a plain string and the runner picks the phone from a fictional pool | Pattern 4 | If Ian wants a phone in the file the validator needs a pattern and a collision rule |
| A6 | The gate does not run when the sync is Unchanged (UI-SPEC "No changes: as today") | Pattern 3, Open Question 1 | A bar change in `config/gate.php` is then only tested by the next CHANGED sync, which D-02 says ("tested by the next sync") |
| A7 | The provider chain (`provider`, `model` in the agent file) stays out of the fingerprint, following D-04 literally | Pattern 5 | A model swap would not mark recordings stale |
| A8 | The calibration set lives in `tests/Fixtures/calibration/` and its raw scores go in `walk_recordings` with `kind = 'calibration'` | Pattern 5, 7 | If Ian wants a file artifact instead, the report reads a JSON file |
| A9 | Command shape `walks:record {walk?*} {--calibration}` and `walks:report` | Pattern 4 | Argument syntax is the planner's (UI-SPEC) |
| A10 | Dana's booking time is named and a `wait` follows (F-5) | Pattern 4 | If Ian insists on relative wording for Dana, the vocabulary needs "the earliest time offered a day or more ahead" |
| A11 | One fresh scratch DB per walk (about 0.15 s) rather than one per gate | Pitfall 11 | Slower gate; shared DB leaks one walk's rows into the next |
| A12 | Capturing `DispatchInbound` is enough to hold `ProcessInbound`; other jobs run inline on the sync queue in the scratch world | Pattern 1 | A job that needs a worker semantic (debounce) would behave differently; `ProcessInbound::handle` is called directly, as `runQueuedFrontDeskJobs()` does |
| A13 | Laravel defers `ShouldDispatchAfterCommit` events and `afterCommit` jobs until the outermost commit, and unique-job locks use the cache contract | Alternatives table | Only affects the rejected transaction approach |

## Open Questions

1. **Does the gate run when the folder is unchanged?**
   - What we know: UI-SPEC says "No changes" behaves as today with no stale line; D-02 says a bar change "is tested by the next sync".
   - What's unclear: a bar change with an unchanged folder is not tested until the folder changes.
   - Recommendation: do not run on Unchanged (cheap, matches UI-SPEC); add `walks:record --check` style dry run only if Ian wants it later.
2. **What does the gate say when a candidate needs more model or Jev calls than recorded?** Recommendation: failure line "turn N: the front desk needed a reply that was not recorded", not a stale note (A4).
3. **What does `walks:record` record against?** D-03 says "the candidate". Recommendation: the folder on disk, loaded, validated and compiled exactly as a sync would, then run in a scratch world; it refuses when validation fails; with no argument it records every walk file, with a path argument only that walk.
4. **Where is the calibration set recorded and kept?** Recommendation in A8; run by `walks:record --calibration` so Ian runs one live command.
5. **Ian's own dev database.** After this phase every `icm:sync` of the example folder refuses until `walks:record` runs. Recommendation: one paragraph in `docs/SETUP.md` and in the Phase 12 input that already exists (ROADMAP.md:445).
6. **Should `icm:sync` print the Ian-form stale lines on the console?** Recommendation yes (as `warn`), not required by the UI-SPEC.
7. **Wave 0 helper for per-call scores in tests.** Extend `FakeClassifier` (`answerSequence`) versus a test-only subclass. Recommendation: extend `FakeClassifier`; it has no map citations.

## Environment Availability

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| PHP 8.5 | everything | yes | 8.5.12 | - |
| `pdo_sqlite` | scratch DB, tests | yes (local); in CI extension list | - | none: refuse in plain words |
| `pdo_pgsql` | CI pgsql leg | yes (local) | - | - |
| Postgres server | CI pgsql leg only | not probed (`pg_isready` not installed); CI service `postgres:17` | - | CI runs it |
| Node | build, Playwright | yes | v24.12.0 | - |
| Playwright / Chromium | browser test | yes | npx 1.63.0, `~/Library/Caches/ms-playwright` holds chromium 1181, 1223, 1243 | CI installs with `npx playwright install chromium` |
| Python 3 | `check_map.py` | yes | 3.9.6 | - |
| Live model, Jev, Twilio | NOT used by any plan | - | - | - |

**Missing dependencies with no fallback:** none for planning.

## Validation Architecture

Nyquist is enabled (`.planning/config.json` has no `nyquist_validation` key; `tdd_mode: true`, `test_command: php artisan test`).

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Pest 5.3.1 on PHPUnit config `phpunit.xml` (sqlite `:memory:`, `QUEUE_CONNECTION=sync`, `CACHE_STORE=array`) |
| Config file | `phpunit.xml`; global setup `tests/Pest.php` (`->in('Feature')` beforeEach at :37-89) |
| Quick run command | `php artisan test --compact <path>` |
| Full suite command | `php artisan test --compact` (3347 tests, about 330 s locally; CI runs sqlite and pgsql legs with the browser install) |

### Phase Requirements to Test Map
| Req / Decision | Behavior | Test Type | Automated Command | File Exists? |
|----------------|----------|-----------|-------------------|--------------|
| WALK-01 | a valid walk file is typed, validated, compiled into `index.walk_test` (two files both present) | Feature | `php artisan test --compact tests/Feature/Icm/WalkFileTest.php` | Wave 0 |
| WALK-01 | each broken walk field is refused in plain words naming file and field (dataset like ValidatorTest.php:15-37); D-10 day/date/time without `start`; echoes the owner's value | Feature | same | Wave 0 |
| WALK-01 | `walk_test` still reserved for rooms; ownership by a room still refused | Unit/Feature | `php artisan test --compact tests/Unit/Rooms/RoomRegistryTest.php tests/Feature/Rooms/RouterClaimTest.php tests/Feature/Rooms/RoomOwnershipTest.php` | Exists (edit T2, T3) |
| WALK-02 | pass: all walks pass, candidate goes live exactly as before, no extra version row | Feature | `php artisan test --compact tests/Feature/Icm/WalkGateTest.php` | Wave 0 |
| WALK-02 | fail: a price not in the folder (remove `price_sentences`) and an expected time no longer offered (change hours) each refuse, previous version stays live, line names file, turn, check, version; changelog and page show it | Feature | same | Wave 0 |
| WALK-02 | unrecorded: new walk and a changed customer turn refuse (owner form on page and notification, Ian form in changelog) | Feature | same | Wave 0 |
| WALK-02 | stale: still runs, never refused; owner sees plain "Live"; changelog carries the Ian line on Live and Rejected entries, not on No changes or a validation refusal (rulings 1, 5) | Feature | same | Wave 0 |
| WALK-02 | first sync, nothing live: "Nothing is live yet." | Feature | same | Wave 0 |
| WALK-02 | the gate does not run on Unchanged or on a validation refusal | Feature | same | Wave 0 |
| D-02 | recorded RAW scores meet the CURRENT bars: change `config('gate.classifier.complaint_max_yes')` and the same recording flips plain to held, no re-record | Feature | `php artisan test --compact tests/Feature/Walks/ReplayTest.php` | Wave 0 |
| D-03 | `walks:record` with fakes stores replies and raw scores, sends nothing, leaves no live row, no file, no clock or binding change | Feature | `php artisan test --compact tests/Feature/Walks/WalksRecordCommandTest.php` | Wave 0 |
| Isolation | after a gate or record run: DB default, `icm.path`, test-now, `Classifier` instance, `FakeTextChannel`, queue and cache config, HTTP prevention are restored; a live appointment does not change offered times; `channels.text.driver=twilio` still sends nothing real | Feature | `php artisan test --compact tests/Feature/Walks/ScratchWorldTest.php` | Wave 0 |
| D-04/D-05 | fingerprint stable across runs, changes with agent body, a loaded file, the BOOKING constant, a triage question; script hash changes only with `says` | Unit | `php artisan test --compact tests/Unit/Icm/WalkFingerprintTest.php` | Wave 0 |
| D-07 | frozen clock, default start, waits move it, previous test-now restored | Feature | `php artisan test --compact tests/Feature/Walks/WalkClockTest.php` | Wave 0 |
| D-09/D-10 | each check phrase evaluates correctly and fails in plain words | Unit | `php artisan test --compact tests/Unit/Icm/WalkCheckTest.php` | Wave 0 |
| WALK-03 | Dana as a walk (recorded with fakes, replayed by the gate) AND as a feature test (appointment, owner alert, card, `today.md`, reminder row) | Feature | `php artisan test --compact tests/Feature/Walks/DanaEndToEndTest.php` | Wave 0 |
| D-12 | every shipped example walk validates and runs green with scripted fakes; weekday-proof (run at a fixed real date per weekday) | Feature | `php artisan test --compact tests/Feature/Walks/ExampleWalksTest.php` | Wave 0 |
| D-13/D-14 | the report shows both sides per candidate, names held plain texts and sending risky texts, prints the stop text when no number separates | Feature | `php artisan test --compact tests/Feature/Walks/CalibrationReportTest.php` | Wave 0 |
| D-20 | booking_text does not count toward either cap; holding replies still count; life-safety still excluded; paused conversation still refuses a booking text (D-21) | Feature | `php artisan test --compact tests/Feature/FrontDesk/ReplyCapTest.php` | Exists (add after :167) |
| D-20/map | conversation card updated in the same commit; citations hold | Command | `python3 ~/Projects/cartographer/tools/check_map.py ~/Projects/ops-platform --extra-root examples/example-hvac` | Exists |
| D-22..D-26 | the doc rewordings are present (landed in 919dc18) | Command | `git grep -n "meant to read" -- docs examples app map` and `git grep -n "walks:record" -- docs .planning/ROADMAP.md` | Exists |
| Accessibility | walk lines read in order after the "Last sync" heading and the status line, no new tab stop, no sideways scroll, axe clean except contrast; desktop and `->resize(375, 812)` | Browser | `php artisan test --compact tests/Feature/Filament/BusinessFolderWalkLinesBrowserTest.php` | Wave 0 |
| Arch | new code placement | Unit | `php artisan test --compact tests/Unit/ArchitectureTest.php tests/Unit/AppointmentWritesTest.php` | Exists |
| Strings | no `!` or em dash in any gate-produced string | Unit | `php artisan test --compact tests/Unit/Icm/WalkWordsTest.php` | Wave 0 |

### Browser test pattern (Phase 7, found and confirmed)
Phase 7's browser tests are `tests/Feature/Filament/ProjectsRelationManagerKeyboardTest.php` (phone via `visit($url)->on()->mobile()`, `:70`) and `ContactScreenKeyboardTest.php` (own axe function with an exclusion, `:217-241`); Phase 8's `tests/Feature/Booking/BookingPageBrowserTest.php:198-199,325-337` uses `->resize(375, 812)`, the form Ian asked for. Shared helpers in `tests/Pest.php`: `pressKey` (:490), `focusedName` (:516), `expectFocusOn`, `waitForOpenDialogIdle`, and `pageAxeReport(Page)` (:610) which runs axe with `wcag2a, wcag2aa, wcag21a, wcag21aa, wcag22aa` plus `target-size`. Setup: `$this->actingAs(User::factory()->create())`, put the world in place BEFORE `visit()` (the plugin starts its browser only for a test whose own body calls `visit`), `$page->assertSee(...)`, `$browser = $page->page()`. `pageAxeReport` has no parameter to exclude a rule: add one (for example `bool $contrast = true`, setting `'rules' => ['target-size' => [...], 'color-contrast' => ['enabled' => false]]` when false) or copy the local-function approach at ContactScreenKeyboardTest.php:217-241; either way the test states why contrast is exempt (UI-SPEC "Out of scope", Phase 11 must-fix). The world: a rejected `IcmVersion` made with `IcmVersion::factory()->rejected()` with its `errors` set to a failed-walk line, an unrecorded line and a walk-file line (factory state at database/factories/IcmVersionFactory.php:52), plus an Active version so the page draws. Assertions: the `<section>` headed "Last sync" lists its `<p>` children in document order (heading, then the "Rejected" status `<p>`, then the lines in the order stored); the section contains no focusable element added by this phase; at 375 `document.documentElement.scrollWidth <= window.innerWidth`; axe report empty with contrast off. CI runs browser tests inside `php artisan test` on both legs: `.github/workflows/tests.yml` runs `npm ci`, `npm run build`, reads the Playwright version from `package-lock.json`, caches `~/.cache/ms-playwright`, and runs `npx playwright install chromium` (step "Install the browser for the accessibility check") before `php artisan test`. No separate job, no skip.

### Sampling Rate
- **Per task commit:** the narrowest test file named above, plus `php artisan test --compact tests/Unit/ArchitectureTest.php` when a new class lands.
- **Per wave merge:** `php artisan test --compact tests/Feature/Icm tests/Feature/Walks tests/Feature/Rooms tests/Feature/FrontDesk/ReplyCapTest.php tests/Unit` and `check_map.py`.
- **Phase gate:** full `php artisan test --compact` green on sqlite locally and on both CI legs before `/gsd-verify-work`. Hand checks (UAT): the keyboard-only and screen reader walk of the Business Folder page, Sync Now timing with the shipped example set, and Ian's live recording are recorded for end-of-phase UAT, never paused on (memory: "Hand checks go to UAT").

### Wave 0 Gaps
- [ ] `tests/Pest.php`: strip walk files in the copy (T1), add `layExampleWalks()`, `minimalWalkFile()`, `seedRecording()`
- [ ] `tests/Feature/Icm/WalkFileTest.php`, `WalkGateTest.php`
- [ ] `tests/Feature/Walks/` : `ScratchWorldTest`, `ReplayTest`, `WalksRecordCommandTest`, `WalkClockTest`, `DanaEndToEndTest`, `ExampleWalksTest`, `CalibrationReportTest`
- [ ] `tests/Unit/Icm/` : `WalkFingerprintTest`, `WalkCheckTest`, `WalkWordsTest`
- [ ] `tests/Feature/Filament/BusinessFolderWalkLinesBrowserTest.php`
- [ ] `FakeClassifier` sequence support (Open Question 7)
- [ ] `database/factories/WalkRecordingFactory.php`
- [ ] No new framework install

## Security Domain

`security_enforcement` is not set in config, so it is enabled.

### Applicable ASVS Categories
| ASVS Category | Applies | Standard Control |
|---------------|---------|-----------------|
| V2 Authentication | no | Sync Now is behind the Filament panel login; `walks:*` are console commands |
| V3 Session Management | no | unchanged |
| V4 Access Control | yes | Sync Now stays on the existing authenticated Filament page; no new route |
| V5 Input Validation | yes | walk files are owner-written front matter: validate with Laravel rules and `PlainWords`; closed check vocabulary; cap script length; refuse hidden characters (`HiddenCharacters`) |
| V6 Cryptography | no | none added; recordings hold only fictional text |
| V7 Error handling and logging | yes | a thrown walk reports once and fails closed with a plain line; `FakeTextChannel` logs length only outside local (FakeTextChannel.php `send`) |
| V12 Files | yes | scratch records dir created under the system temp dir, removed in `finally`; never the owner's folder |

### Known Threat Patterns for this stack
| Pattern | STRIDE | Standard Mitigation |
|---------|--------|---------------------|
| A gate run sends a real text or alerts the real owner (F-7) | Tampering / Info disclosure | fake channel instance, driver forced to `fake`, `Http::preventStrayRequests` in Replay mode, a test that runs with `twilio` configured |
| A walk writes or reads a live row | Tampering | scratch database; test that live row counts are unchanged |
| Owner-written walk text injects a line break or heading into `changelog.md` or the page | Tampering | `PlainWords::oneLine` (80 characters, one line) and `HiddenCharacters::marked` on every line; Blade `{{ }}` and `e()` already escape |
| A huge or looping walk file stalls Sync Now (DoS) | DoS | cap script items and walks per sync; whole-gate time budget that refuses in plain words |
| Recordings leak real customer data | Info disclosure | walk files and the calibration set use invented customers; recordings are produced only from walk scripts; no `gate:export` text is copied into them |
| Jev key exposure in Record mode | Info disclosure | key stays in `services.jev.key`; Record mode only; Replay mode and tests never reach HTTP |

## Sources

### Primary (HIGH confidence)
- Codebase files read this session with line numbers cited above: `app/Icm/{FolderSync,Loader,Validator,ActiveConfig,Changelog,SyncResult,SyncStatus,ValidationReport,PlainWords,RoomChecks,RoomDropChecks,BusinessClock}.php`, `app/Actions/{RunFrontDesk,RecordInbound,DispatchInbound,InboundData,BookAppointment,SendBookingText,RaiseOwnerAlert,OutboundSender}.php`, `app/Jobs/{ProcessInbound,RenderCustomerCard}.php`, `app/Ai/{FrontDeskPrompt,RunFrontDeskAgent,AgentReply,AgentRun,AgentFile}.php`, `app/Ai/Agents/FrontDeskAgent.php`, `app/SendRule/{TriageReading,FakeClassifier,JevClassifier,ReadCustomerText,DecideSendOrDraft,CustomerReading,TriageQuestions,ClassifierResult}.php`, `app/Rooms/ScheduleAndBooking/{Availability,ReminderSchedule}.php`, `app/Rooms/OwnerIntelligence/TodayRecord.php`, `app/Records/RecordsWriter.php`, `app/Channels/FakeTextChannel.php`, `app/Console/Commands/{IcmSync,GateExport}.php`, `app/Filament/Pages/BusinessFolder.php`, the Business Folder Blade view, `app/Providers/AppServiceProvider.php`, `app/Models/IcmVersion.php`, `app/Enums/{AlertReason,MessageStatus,MessageKind,PausedReason,VersionStatus}.php`, `config/{gate,icm}.php`, `phpunit.xml`, `.github/workflows/tests.yml`, `tests/Pest.php`, the test files named in the inventory, `examples/example-hvac/**`.
- vendor source: `vendor/nesbot/carbon/src/Carbon/Traits/Test.php:50-53`, `vendor/laravel/framework/src/Illuminate/Container/Container.php` method list, `DatabaseManager.php` method list, `vendor/laravel/ai/src/Promptable.php:552-556`.
- Commands run: `php artisan test --compact` (3347 passed, 329.99 s), `check_map.py` (PASS), `php artisan migrate` against a temporary SQLite file in the scratchpad (39 migrations, 0.298 s total with boot), `composer show --direct`, `git show --stat 919dc18`.
- `.planning` documents: 10-CONTEXT.md, 10-UI-SPEC.md, REQUIREMENTS.md WALK-01..03, ROADMAP.md Phase 10 and Phase 12 notes, 08-UAT.md checks 4 and 8, `map/**` cards, `docs/SPEC.md` and `docs/BLUEPRINT.md` lines cited.

### Secondary (MEDIUM confidence)
- Laravel behaviours stated from framework knowledge and not re-read in vendor this session (tagged A12 and A13): `ShouldDispatchAfterCommit` deferral, unique-job locking, savepoint behaviour on pgsql. They only matter for the rejected transaction approach.

### Tertiary (LOW confidence)
- Production `.env` values (A2): reading `.env.example` was denied by the sandbox.

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - no new packages; versions read from `composer show`.
- Architecture: MEDIUM - the seams and the sync placement are read from code; the scratch-database choice is a recommendation that needs Ian's nod (A1).
- Pitfalls: HIGH - each one traces to a file and line.
- Test inventory: HIGH - every file opened, plus a whole-tree grep.

**Research date:** 2026-11-21
**Valid until:** 2026-12-05 (the map line numbers and the example folder's dated offer will drift sooner; re-run `check_map.py` and re-grep `walk_test` at plan time)
