# Phase 10: Walk Tests and the Gate - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-11-20
**Phase:** 10-walk-tests-and-the-gate
**Areas discussed:** Walk-test determinism, Bar calibration (02 D-47, D-56 reopened), Reply cap (02 D-52), Booking section wording, Walk file and failure report, Stale map rulings

Evidence put to Ian before the questions: 02 D-45, D-47, D-52 and D-56 quoted verbatim from `02-CONTEXT.md`; the `config/gate.php` classifier values; `gate_check_max_yes` (0.075) read only as the asks_for_person floor since D-56 (`TriageReading.php:49`), the same number as the strict price and discount bars; booking texts counted toward the cap per `map/objects/conversations/conversation-and-pause.md:21`; 20 stored verdicts in 5 conversations in the local database; Jev's run-to-run spread (asks_for_person 0.41 to 0.50 on one text, 08-UAT check 4).

---

## Scope additions

| Option | Description | Selected |
|--------|-------------|----------|
| Walk file and failure report | What `expect` can state and how a failure reads | ✓ |
| Where a walk runs | Rolled-back transaction versus scratch database | |
| Neither, planner decides | Both to Claude's discretion | |

**User's choice:** Walk file and failure report. Where a walk runs went to Claude's discretion.

| Option | Description | Selected |
|--------|-------------|----------|
| Don't fold (Recommended) | Phone design pass todo stays with Phase 11 | ✓ |
| Fold it in | Would reopen the 11-11 visual rule | |

---

## Walk-test determinism

**Model in the gate**

| Option | Description | Selected |
|--------|-------------|----------|
| Recorded, replayed | A live run saves the replies; the gate replays them | ✓ (with a staleness rule) |
| Written in the walk file | Each turn states the agent's output | |
| Live model every sync | Real, costly, flaky gate | |

**User's choice:** Recorded and replayed, with a staleness rule. The fingerprint is of what the model read. A changed fingerprint still replays and still runs every code-made check; stale is reported, not refused.

**Jev in the gate**

| Option | Description | Selected |
|--------|-------------|----------|
| Live Jev, outcome-tolerant | Real Jev every sync | |
| Recorded with the model | Stand-in: replayed by a test classifier | ✓ |
| Written in the walk file | Stand-in: scores in the file | |

**User's choice:** Recorded with the model. Ian approves this stand-in for Jev inside the gate only, and Jev stays live for every real customer text. Raw scores are replayed and the current bars applied every sync. The scores share the staleness rule, a re-recorded reply re-asks Jev, and the recorded scores feed calibration.

**Clock**

| Option | Description | Selected |
|--------|-------------|----------|
| Fixed start, script moves it | Next opening day 10:00 by default | ✓ |
| Real now | The moment of the sync | |

**Dana as a feature test**

| Option | Description | Selected |
|--------|-------------|----------|
| Same walk file, suite fakes | One Dana file through one runner | ✓ |
| Separate hand-written test | Two Danas | |

**Re-record**

| Option | Description | Selected |
|--------|-------------|----------|
| Ian, by command | `walks:record` per client | ✓ |
| Automatic, in background | Queued after activation | |
| Both | | |

**User's choice:** Ian, by command (`walks:record`). A stale walk is never shown to the owner as something to act on, and the owner-facing line says the change passed. The operator notice goes on BLUEPRINT's before-first-client list and is not built in Phase 10.

**Missing recording**

| Option | Description | Selected |
|--------|-------------|----------|
| Refuse, name the command | Previous version stays live | ✓ (amended) |
| Report, skip that walk | | |
| Record live during sync | | |

**User's choice:** Refuse; the previous version stays live. The owner wording names the file and says the recording is Ian's step, with no command shown. The command and the file go in the changelog line for Ian. The operator notice covers this case too.

**Where recordings live**

| Option | Description | Selected |
|--------|-------------|----------|
| Database | Keyed by walk path and fingerprint | ✓ |
| In the folder, beside the walk | Travels with the folder | |

**User's choice:** Database. Phase 12 input: run `walks:record` per business before the first sync on any new instance. Gate-path tests seed recordings as fixtures.

---

## Bar calibration (02 D-47 and D-56 reopened)

**Method**

| Option | Description | Selected |
|--------|-------------|----------|
| Measure, then you approve | Calibration set recorded live, numbers to Ian | ✓ |
| Set numbers now | From the 08-UAT scores alone | |
| Reword triage first | Change what Jev reads before any number | |

**User's choice:** Measure, then Ian approves. The set includes texts that must still be held alongside the plain texts held today, and the proposal shows both sides at each candidate number. If no number separates plain from real for a check, stop and bring a `rules/triage.md` reword, measured the same way.

**Rule**

| Option | Description | Selected |
|--------|-------------|----------|
| Risky never sends; plain always | Every recorded run | ✓ |
| Risky never; plain mostly | A share Ian sets | |

**Bars that may move** (multi-select)

| Option | Description | Selected |
|--------|-------------|----------|
| complaint, promise | Reopens D-56 | ✓ |
| asks_for_person band | Reopens D-47 | ✓ |
| intent confidence | Also reopens 02 D-45 and the 0.85 reading of D-03 | ✓ |
| price, discount | Reopens D-45's strict reading | |

**User's choice:** complaint and promise, the asks_for_person band, and intent confidence. The intent pick reopens 02 D-45's intent clause by Ian's explicit call.

**Floor key**

| Option | Description | Selected |
|--------|-------------|----------|
| Move it under its name | `gate_check_max_yes` value only | ✓ |
| New key, keep the old | Verdict shape changes | |

**Sequencing**

| Option | Description | Selected |
|--------|-------------|----------|
| Split: build, you run, decide | Plans build tooling, Ian records live, a follow-up plan writes values | ✓ |
| Executor runs it live | Unattended spend | |

**User's choice:** Split. The Booking section wording is measured in the same live run and comes back in the same decision; the bars and the wording are approved together, against one recording. The phase closes only after the follow-up plan.

---

## Reply cap (02 D-52)

**Counting**

| Option | Description | Selected |
|--------|-------------|----------|
| Don't count them | Amends D-52: the cap counts texts answering a customer text | ✓ |
| Keep counting, raise the number | D-52 stands, per_hour up | |
| Keep as is | | |

**Pause versus booking texts**

| Option | Description | Selected |
|--------|-------------|----------|
| Still refused | 08 D-26, D-27 unchanged | ✓ |
| Let booking texts through | Reopens 08 D-26, D-27 and the meaning of a pause | |

---

## Booking section wording

**Candidates** (multi-select)

| Option | Description | Selected |
|--------|-------------|----------|
| Say what the words are for | Facts framed as what the model's words do | ✓ |
| Drop the mechanics | What to do and what is true, no added-line talk | ✓ |
| Show the line that follows | Reopens 08 WR-07 | |
| Today's text, as baseline | Effect of the new bars alone | ✓ |

**Pass mark**

| Option | Description | Selected |
|--------|-------------|----------|
| Every run sends; you read | Ian judges the words, no code check that names words | ✓ |
| Most runs send; you read | | |

---

## Walk file and failure report

**Fingerprint**

| Option | Description | Selected |
|--------|-------------|----------|
| Prompt + Jev's questions | A triage.md edit marks stale too | ✓ (extended) |
| Model prompt only | | |

**User's choice:** Prompt plus Jev's questions, reported not refused. A change to the walk's own script counts as unrecorded and follows the missing-recording rule.

**Expect**

| Option | Description | Selected |
|--------|-------------|----------|
| After any turn and at the end | Per-turn and end checks | ✓ (extended) |
| End state only | | |

**User's choice:** After any turn and at the end. A check naming a day, date or clock time is valid only when the walk names its start; otherwise the sync refuses the walk file, naming the file and the field. The example walks default to relative wording.

**Report**

| Option | Description | Selected |
|--------|-------------|----------|
| Same list, walks after checks | One plain line beside today's refusals | ✓ |
| Separate walk-test section | New page section | |

**What ships in the example**

| Option | Description | Selected |
|--------|-------------|----------|
| Dana + the 08-UAT walks | Risky texts in repo fixtures | ✓ (extended) |
| Whole calibration set | Every script gates the example | |

**User's choice:** Dana, the 08-UAT walks, plus one walk per risky check that must hold or hand off (price, discount, promise, complaint, asking for a person). Fictional data only. The full calibration set stays in repo fixtures.

---

## Stale map rulings

| Item | Options | Selected |
|------|---------|----------|
| (a) `escalate_to` | "Meant to read", parked with 999.5 / same and drop it from `reads:` (reopens 06 D-03) / remove it (reopens 04 D-12) | "Meant to read", parked (Rec.) |
| (b) `intake_answers.icm_version_id` | Re-park on BLUEPRINT before-first-client / drop the column (reopens 02 D-33) | BLUEPRINT before-first-client (Rec.) |
| (c) Collisions line | Add `claim_token` / leave | Add it (Rec.) |

---

## Claude's Discretion

- How a walk is kept off live data (transaction or scratch database), with no real text, no live row and no owner alert.
- Fingerprint granularity, walk-file field syntax, refusal wording in the house style, recordings table shape, the replay classifier's build, and the calibration report layout.

## Deferred Ideas

- Operator notice for stale or unrecorded walks: BLUEPRINT before-first-client list.
- `walks:record` before the first sync on a new instance: ROADMAP Phase 12 planning notes.
- Automatic re-recording: not chosen.
- Phone design pass todo: stays with Phase 11.
