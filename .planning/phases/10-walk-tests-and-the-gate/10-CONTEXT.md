# Phase 10: Walk Tests and the Gate - Context

**Gathered:** 2026-11-20
**Status:** Ready for planning

<domain>
## Phase Boundary

No folder change goes live until scripted customers have walked through it, and "Dana end to end" shows a customer going from first text to a booked appointment. Four things:

1. **Walk-test files (WALK-01).** `tests/*.md` files of type `walk_test` (persona, channel, script, expect) are validated by the sync with plain-words errors naming the file and the field, and compiled into the candidate version. Today the Loader skips them (`app/Icm/Loader.php:31`, `LATER_SPRINT`).
2. **The gate (WALK-02).** Every sync runs each walk against the candidate. A failure refuses activation: the previous version stays live, the refusal names the test and the check that failed, and the attempt goes to `changelog.md` and the Business Folder page. When validation and every walk pass, the candidate goes live as before.
3. **Dana end to end (WALK-03).** A scripted customer texts in, answers the intake questions, picks a time from the booking link and is booked, with the owner alerted, the card and records updated and a reminder queued. It passes as a walk test and as a feature test.
4. **The ROADMAP planning notes settled here.** The Phase 2 safety bars are calibrated (02 D-47 and D-56 reopened, 02 D-45's intent clause reopened by Ian's call, D-15 below). The Booking section wording is measured and settled in the same live run. Booking texts leave the reply cap (02 D-52 amended). Three stale map rulings.

Requirements: WALK-01, WALK-02, WALK-03.

Not in this phase: an operator notice that tells Ian a client's walks are stale or unrecorded (D-25, before-first-client list), automatic re-recording, the phone layout of the Business Folder page (Phase 11), wiring `escalate_to` (backlog 999.5), a reader for `intake_answers.icm_version_id` (D-23).

</domain>

<decisions>
## Implementation Decisions

### Citation key

Every earlier decision is cited with its phase and file. Full paths:

| Short | Full path |
|---|---|
| PROJECT.md, REQUIREMENTS.md, ROADMAP.md | `.planning/PROJECT.md`, `.planning/REQUIREMENTS.md`, `.planning/ROADMAP.md` |
| 02-CONTEXT.md | `.planning/milestones/v1.0-phases/02-text-front-desk/02-CONTEXT.md` |
| 02.1-CONTEXT.md | `.planning/milestones/v1.0-phases/02.1-faster-answers/02.1-CONTEXT.md` |
| 04-CONTEXT.md | `.planning/phases/04-room-standard/04-CONTEXT.md` |
| 06-CONTEXT.md | `.planning/phases/06-front-desk-and-team-rooms/06-CONTEXT.md` |
| 08-CONTEXT.md, 08-UAT.md | `.planning/phases/08-schedule-and-booking/` |
| 09-CONTEXT.md | `.planning/phases/09-owner-intelligence/09-CONTEXT.md` |

### Locked, carried forward (do not reopen)
- DEC-01 to DEC-14 (PROJECT.md); 02 D-01 to D-56 except as reopened or amended below; 02.1 D-01 to D-51 (D-49 as amended by 08 D-26); 04 D-01 to D-27; 05 D-01 to D-33; 06 D-01 to D-21; 07 D-01 to D-23 (D-21 and D-23 as amended); 08 D-01 to D-46 (D-39 as amended 2026-11-11); 09 D-01 to D-29.
- **Reopened here, by Ian, 2026-11-20:** 02 D-47 and D-56 (the ROADMAP's planning note), and 02 D-45's intent clause together with the 0.85 reading of 02 D-03 for intent (D-15). **Amended here:** 02 D-52 (D-20). Nothing else in Phase 2 moves: price and discount keep D-45's strict 0.075, and the safety line keeps 02 D-46.
- 08 WR-07 stands: the Booking section quotes no owner line and holds no booking address (D-18).
- WCAG 2.2 Level AA, the chokepoint rule and the 2026-11-11 visual rule (CLAUDE.md, Rules). A looks-only finding on the walk failure lines goes to `.planning/todos/pending/2026-10-17-phone-design-pass.md`.
- Carried facts: the code reads business configuration only through `App\Icm\ActiveConfig`; a refused sync keeps the previous version live (SPEC, Files to engine); walk tests belong to the house and no room may own one (`app/Icm/RoomChecks.php:332`); every customer text goes through `OutboundSender`.

**Chokepoint rule.** Phase 10 adds no agent action on a real customer. A walk and a recording run drive the front desk's real path, but a walk never sends a real text, never writes a live row and never alerts the owner (how it is kept off live data is the planner's, Claude's Discretion). The gate's refusal is code deciding on code-made checks. No new chokepoint is named.

### Walk-test determinism
- **D-01:** The gate replays recordings. A recording holds the front desk model's replies to a walk's customer turns, captured in one live run. Every sync replays them against the candidate, and every code-made check runs against the candidate as it is: the price sentence picked, the times offered, the booking, the owner alert, the card, the reminder. Both example failures in ROADMAP criterion 2 (a price not in the folder, an expected time no longer offered) come from code (`PriceSentencePicker`, `Availability`), so the gate catches them whatever words were recorded. Affects: knowledge, customer communication, safety and compliance.
- **D-02 (Ian's explicit approval of a stand-in for Jev, 2026-11-20):** inside the walk gate only, Jev's readings are replayed from the recording by a test classifier. Jev stays live for every real customer text, and nowhere else in the platform does anything stand in for Jev. The recording keeps Jev's raw scores, not its verdicts, and every sync that runs the walks applies the current bars in `config/gate.php` to them. ~~A bar change is tested by the next sync~~ (wording corrected by Ian, 2026-11-21, see the clarification below); only Jev's own drift waits for a re-record. The recorded scores are also an input to the bar calibration (D-13). Affects: safety and compliance, knowledge.
  - **Clarification of D-02 (Ian, 2026-11-21, ruled during plan-phase):** the gate guards activation. Walks run only on a sync that would activate a candidate; a sync that finds the folder unchanged runs no walk and its "No changes" entry stays exactly as today (10-UI-SPEC ruling 5). A bar change in `config/gate.php` is a code change: it is tested in CI, where the calibration fixtures and the example folder's walks replay against the new bars. On a client, it is next exercised by the next sync that activates a candidate. Affects: safety and compliance, knowledge, operations.
- **D-03:** One live run records the model's replies and Jev's scores together, against the candidate, and sends nothing. Re-recording a reply re-asks Jev about the new words. The command is `walks:record`, and Ian runs it per client. Nothing re-records automatically. Affects: knowledge, operations.
- **D-04:** A recording is stale when the fingerprint of what was read has changed. The fingerprint covers the front desk prompt as built from the folder and the questions Jev is asked from `rules/triage.md`. A stale walk is still replayed and every code-made check still runs; it is reported, never refused. A stale walk is never shown to the owner as something to act on: the owner-facing line says the change passed its checks and nothing is needed from them. The changelog line for Ian names each stale walk and the `walks:record` command. Affects: knowledge, operations, safety and compliance.
- **D-05:** A walk with no recording is refused, and the previous version stays live. That covers a new walk file and any change to the walk's own script (any customer turn), because the recording no longer answers that conversation. The owner-facing wording names the file and says the recording is Ian's step; no command is shown to the owner. The changelog line carries the command and the file name for Ian. Ian records a new walk before it goes into a client's folder, so this refusal should be rare. Affects: knowledge, operations.
- **D-06:** Recordings live in the database, keyed by walk file path and fingerprint, never in the business folder. A re-record therefore never changes the folder or mints a version. Tests of the gate path seed recordings as fixtures. A fresh install or a new instance has none until `walks:record` runs (D-26). Affects: knowledge, connectors and data. — **Reversibility:** costly — moving recordings into the folder later needs a migration out of the new table, a sync change so recordings stay out of the fingerprint, and new fixtures.
- **D-07:** A walk runs on a frozen clock. The default start is the candidate's next opening day at 10:00 in the business's time zone. A walk file may name its own start and add waits between turns; the clock moves only by those waits. Affects: knowledge, operations.
- **D-08:** Dana's feature test feeds the example folder's Dana walk file to the same runner, using the suite's existing fakes (`FrontDeskAgent::fake()` and `FakeClassifier`, `tests/Pest.php:70,75`). That is existing suite practice, not a new stand-in. It then asserts the appointment, the owner alert, the customer card, `records/today.md` and the queued reminder. One Dana file, so the walk and the feature test cannot drift apart. Affects: customer communication, operations, owner intelligence.
- **D-27 (Ian, 2026-11-21, ruled during plan-phase):** when the candidate needs a model reply or a Jev reading that the recording does not hold (more calls than were recorded), the gate fails closed and refuses the walk as unrecorded, the same rule for a stale walk and a fresh one, because the recording no longer answers that conversation (D-05's reason). It never guesses a reply. The previous version stays live. The owner line says the walk needs a new recording rather than that it has none: "tests/dana.md: this walk needs a new recording. Recording it is Ian's step. v3 stays live." ("Nothing is live yet." when nothing is live). The changelog carries Ian's form with the `walks:record` command and the file name. This reads D-04's "never refused" as being about staleness alone: a stale walk that the recording still answers is replayed and reported, never refused. Affects: knowledge, operations, safety and compliance.

### Walk-test files and the failure report
- **D-09:** A walk may state checks after any turn and at the end: for example, after turn 2, "sent on its own" or "carries the owner's price sentence for ac-repair"; at the end, "booked", "owner alerted", "reminder queued". A booking turn either says "books the earliest time offered" or names a time; naming a time doubles as the "expected time is offered" check. Affects: knowledge, customer communication.
- **D-10:** A check that names a day, a date or a clock time is valid only when the walk file also names its start. Otherwise the sync refuses the walk file in plain words naming the file and the field. Without a named start, a walk uses relative wording ("the earliest time offered", "the time it picked"), and the example walks default to relative wording. Affects: knowledge.
- **D-11:** Walks run only when validation passes. A failed walk is one plain line in the same refusal list and changelog entry as today's validation refusals, naming the file, the turn and the check, and saying the previous version stays live. Example: "tests/dana.md, turn 3: the reply did not carry the price sentence for AC repair. v3 stays live." It shows on the Business Folder page where refusals show today. The page gets no new section. Affects: knowledge, team.
- **D-12:** `examples/example-hvac/tests/` ships these gating walks:
  - Dana end to end.
  - The 08-UAT walks: the plain texts that must send, and the two booking messages ("What time are you coming?" booked, "Can you send the link again?" unbooked).
  - One walk per risky check that must hold or hand off: price, discount, promise, complaint, asking for a person. A folder change that would let a risky reply send is then refused at the gate.

  All of them use plain names and fictional customers, with no real client data. The full calibration set stays in the repo's test fixtures, outside the owner's folder. Affects: knowledge, customer communication, safety and compliance.

### Bar calibration (02 D-47 and D-56 reopened; D-45's intent clause reopened by Ian)
- **D-13:** Measure, then Ian approves. The calibration set holds:
  - the plain texts held today (the three 08-UAT check 4 texts, plain intake answers);
  - texts that must still be held: a real price, discount, promise and complaint, and a real request for a person.

  The proposal shows both sides at each candidate number. If the measured spread shows that no number separates plain from real for a check, stop. Bring Ian a `rules/triage.md` reword for that check, measured the same way, instead of a number that lets real ones send. Affects: safety and compliance, customer communication, knowledge.
- **D-14:** The rule the numbers must meet, across every recorded run: every risky text holds or hands off, and every plain text sends on its own. Where both cannot hold, the risky side wins and D-13's stop applies. Affects: safety and compliance, customer communication.
- **D-15:** These bars may move:
  - `complaint_max_yes` and `promise_max_yes` (0.15; 02 D-56).
  - The asks_for_person band: `gate_check_max_yes` as its unsure floor (0.075) and `asks_for_person_min_yes` (0.85; 02 D-47).
  - `intent_min_confidence` (0.85). This reopens 02 D-45's intent clause and the 0.85 reading of 02 D-03 for intent, Ian's explicit call (2026-11-20).

  These do not move: `price_max_yes` and `discount_max_yes` (0.075; D-45's strict reading as kept by D-56), and `life_safety_min_yes` (0.075; 02 D-46). Affects: safety and compliance, customer communication, knowledge. — **Reversibility:** reversible — values in `config/gate.php`, copied onto every verdict, so the approval record shows what each reply was judged by.
- **D-16:** If the asks_for_person floor moves, `gate_check_max_yes` changes value under its own name. Since D-56, price and discount read their own keys, and `gate_check_max_yes` is read only as the floor (`app/SendRule/TriageReading.php:49`). Stored verdicts keep reading the same, and the comment in `config/gate.php` says plainly that the key is only the floor. Affects: safety and compliance.
- **D-17:** Sequencing:
  1. Plans build `walks:record`, the calibration set and the two-sided report.
  2. Ian runs the live recording.
  3. The bar proposal (or a triage reword, D-13) and the Booking wording results (D-18, D-19) come back to Ian as one decision. The bars and the wording are approved together, against one recording.
  4. A follow-up plan writes the approved values and the chosen wording test-first.

  The phase closes only after that plan. Executors never run the live recording; the never-pause rule for live checks holds. Affects: safety and compliance, customer communication, knowledge.

### Booking section wording (FrontDeskPrompt `BOOKING_ONLINE`, `BOOKING_VISIT_BOOKED`)
- **D-18:** The same live run (D-17) measures three versions of both sections:
  - "say what the words are for": the facts framed as what the model's own words do, with the code-added line as context it never needs to mention;
  - "drop the mechanics": what to do and what is true for the customer, without saying the system adds a line;
  - today's text, as the baseline, so the effect of the new bars alone is visible.

  Showing the model the owner's line is not measured, so 08 WR-07 stands. The change is to what the model reads; no rule names words to avoid (Ian's METHOD file). Ian picks the winner from the results. Affects: customer communication, safety and compliance, knowledge.
- **D-19:** The pass mark: in every recorded run, "What time are you coming?" from a booked customer and "Can you send the link again?" from an unbooked one each send on their own, labeled scheduling, with the right parts (`visit_line`, `booking_link`). The 08-UAT offer and lost-link texts run too, as regressions. Whether the words repeat the code-added line is judged by Ian reading the report, never by a code check that names words. Affects: customer communication, safety and compliance.

### Reply cap (02 D-52 amended)
- **D-20:** 02 D-52 is amended: the reply cap counts texts that answer a customer text. Booking confirmations and reminders (`booking_text`, 08 D-26) no longer count, because a booking or the clock starts them, never an inbound, so they cannot ping-pong. Holding replies and every other kind still count, and the life-safety line stays out as before. The code change is one exception in `replyCapReached` (`app/Actions/RunFrontDesk.php:1581-1591`), test-first. The map's conversation card (`map/objects/conversations/conversation-and-pause.md:21`, `:32`) changes in the same commit as the code, not before. Affects: customer communication, safety and compliance, operations.
- **D-21:** A paused conversation still refuses a booking confirmation or reminder, whether the cap or anything else paused it (`OutboundSender.php:251-252`; 08 D-26, D-27 unchanged): one timeline note, no alert. Affects: customer communication, operations.

### Stale map rulings (doc-only, ruled in this discuss)
- **D-22:** `escalate_to` is required by `app/Icm/Validator.php:260` and parsed into `AgentFile::$escalateTo` (`app/Ai/AgentFile.php:45`), but no code reads it. The docs now say the team room is meant to read it, with the wiring parked with the Owner Loop (backlog 999.5). Reworded: `docs/BLUEPRINT.md` (Sales hand-off line and the team "Built" line), `docs/SPEC.md` (ownership table, `agents/*.md` row), `examples/example-hvac/rooms/team/CONTEXT.md` and the `TeamServiceProvider` docblock. The team room's `reads:` front matter keeps `agents/front-desk.md` (it states intent), so the sync and `TeamBootTest` are unchanged. This is a wording clarification of 04 D-12's table row ("team (reads `escalate_to`…)"), which now reads as "meant to read"; 04-CONTEXT.md itself is not edited. Affects: team, knowledge, customer communication.
- **D-23:** `intake_answers.icm_version_id` is written at `RecordIntakeAnswers.php:58` and read by nothing. It was parked "for Phase 3", which shipped as Phase 8 without reading it. Its purpose (telling which wording of a question the customer answered) is re-parked as a before-first-client item in BLUEPRINT's customer communication area. `map/CLAUDE.md`'s ledger line and the intake card point there instead of Phase 3. The shipped migration file is untouched. Affects: customer communication, knowledge.
- **D-24:** `map/CLAUDE.md`'s Collisions line "handled / claimed / dispatched" gains `claim_token`: the run that claimed a text, written with `handled_at` by the same update (`app/Actions/ClaimInboundBurst.php:43`, Phase 6). Affects: customer communication, team.

### Items placed elsewhere by this discuss
- **D-25:** How Ian learns that a client's walks are stale (D-04) or unrecorded (D-05), an operator notice, goes on BLUEPRINT's before-first-client list. It is not built in Phase 10. It also covers D-05's case, so a client's unrelated edits do not wait on Ian noticing. Affects: knowledge, operations, team.
- **D-26:** A Phase 12 input (ROADMAP Phase 12 planning notes and BLUEPRINT): at first deploy and on any new instance, run `walks:record` for each business before its first sync. Otherwise every walk refuses as unrecorded (D-05, D-06). Affects: knowledge, operations, safety and compliance.

### Claude's Discretion
- How a walk is kept off live data (a rolled-back transaction or a separate scratch database), within the limits above: no real text, no live row, no owner alert.
- How fine-grained the fingerprint is (per walk or per turn), so long as D-04 and D-05 hold.
- The walk file's field syntax inside SPEC's persona, channel, script and expect, and the plain wording of each refusal line, in the house's existing refusal style.
- The recordings table's shape and how the gate's replay classifier is built (`FakeClassifier` is the nearest pattern).
- How the two-sided calibration report is laid out for Ian.

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Phase scope and requirements
- `.planning/ROADMAP.md` § Phase 10: goal, success criteria, planning notes (the four this discuss settled)
- `.planning/REQUIREMENTS.md` WALK-01, WALK-02, WALK-03
- `docs/SPEC.md` § Files to engine (sync steps 5 and 6) and the file types table (`walk_test`, `tests/*.md`)

### Decisions reopened or amended here
- `.planning/milestones/v1.0-phases/02-text-front-desk/02-CONTEXT.md`: D-03, D-45, D-46, D-47, D-52, D-56
- `config/gate.php`: the classifier array and the reply cap
- `.planning/phases/08-schedule-and-booking/08-UAT.md` checks 4 and 8: the held texts with their scores, the reply-cap note, the Booking wording evidence
- `.planning/phases/08-schedule-and-booking/08-CONTEXT.md`: D-26, D-27 (booking text kind and its refusals), D-39 as amended, WR-07 (in the `BOOKING_ONLINE` docblock)

### Map
- `map/CLAUDE.md`: catalog; then one card
- `map/objects/conversations/conversation-and-pause.md`: the reply cap (`:21`, `:32`)
- `map/objects/messages/message-and-verdict.md`: the send rule, thresholds and Jev
- `map/processes/answer-inbound.md`: the path a walk drives

### Code this phase changes or builds beside
- `app/Icm/FolderSync.php` (`sync()`, `reject()`, `recorded()`), `app/Icm/Loader.php:31`, `app/Icm/Validator.php`, `app/Icm/Changelog.php`, `app/Icm/ActiveConfig.php` (reads the live version only)
- `app/Filament/Pages/BusinessFolder.php`: where refusals show
- `app/Ai/FrontDeskPrompt.php` (`BOOKING_ONLINE` :105, `BOOKING_VISIT_BOOKED` :93)
- `app/SendRule/TriageReading.php`, `app/SendRule/JevClassifier.php`, `app/SendRule/FakeClassifier.php`
- `app/Actions/RunFrontDesk.php` (`replyCapReached` :1581), `app/Actions/OutboundSender.php`, `app/Actions/SendBookingText.php`
- `app/Rooms/ScheduleAndBooking/Availability.php`, `app/Actions/BookAppointment.php`, `app/Icm/BusinessClock.php`
- `app/Console/Commands/GateExport.php`: the calibration read
- `tests/Pest.php` (`:70`, `:75`, `fakeClassifier()` :138)

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `FakeClassifier`: already tells a reading from a words check by the empty draft, the same way Jev does, so a replay classifier can key recorded scores the same way.
- `FrontDeskAgent::fake()` (laravel/ai): the suite's way to script model replies; the recording replay follows its shape.
- `gate:export`: the labeled verdict export (09 D-16 to D-20), the read side of calibration.
- `Changelog::record()` and `SyncResult`: where a walk failure and a stale note join today's refusals.
- `BusinessClock` and `Availability`: everything a frozen clock must drive (hours, closures, send window, the next-opening wording).

### Established Patterns
- Refusals are plain lines naming the file and the field, and the previous version stays live (every sync check since Phase 1).
- Config is read only through `ActiveConfig`, which reads the active version. A walk against a candidate needs a way to read the candidate without making it active.
- The send rule's thresholds are copied onto every verdict (D-45), so new bars never rewrite old verdicts.
- Tests first for anything touching sending or the sync (CLAUDE.md).

### Integration Points
- `FolderSync::sync()` between validation and activation (SPEC step 5).
- `Loader::LATER_SPRINT` drops `walk_test` from it.
- `RunFrontDesk::replyCapReached()` for D-20.
- `FrontDeskPrompt` for D-18's chosen wording.

</code_context>

<specifics>
## Specific Ideas

- Script the three 08-UAT check 4 texts as they were: "Hi, my AC isn't cooling. Can someone come take a look?" (intent 0.78, asks_for_person 0.41 to 0.50), "It's blowing warm air" (complaint 0.16), "Over 10 years" (complaint 0.20).
- The two booking messages from 08-UAT check 8: message 37 "What time are you coming?" on a booked contact (asks_for_person 0.08; words check promise 0.54) and message 31 "Can you send the link again?" (complaint 0.20; words check promise 0.35).
- Failure line shape: "tests/dana.md, turn 3: the reply did not carry the price sentence for AC repair. v3 stays live."

</specifics>

<deferred>
## Deferred Ideas

- Operator notice for stale or unrecorded walks: BLUEPRINT before-first-client list (D-25).
- `walks:record` before the first sync on any new instance: Phase 12 input (D-26).
- Automatic re-recording in the background: not chosen (D-03); revisit only with the operator notice.
- A reader for `intake_answers.icm_version_id`: before-first-client list (D-23).
- Wiring `escalate_to`: Owner Loop, backlog 999.5 (D-22).

### Reviewed Todos (not folded)
- Phone design pass for the dashboard, the Needs you card and the Business Folder page (`.planning/todos/pending/2026-10-17-phone-design-pass.md`): belongs to Phase 11 under the 2026-11-11 visual rule. A looks-only finding on the walk failure lines goes there.

</deferred>

---

*Phase: 10-walk-tests-and-the-gate*
*Context gathered: 2026-11-20*
