---
status: diagnosed
trigger: "Gap G-02-2.1 (major): ordinary intake replies never send on their own. 'My AC is blowing warm air' got a correct intake question (clean code checks, Jev intent intake 1.0) but Jev scored complaint 0.47 and promise 0.30 against the 0.075 bar, so it was held. After rewording the complaint and promise questions in examples/example-hvac/rules/triage.md (uncommitted, keep it) they fell to 0.07 and 0.09 and the reply was still held on promise. Proposed fix: per-question bar in config/gate.php (price/discount 0.075, promise/complaint 0.15); amends D-45."
created: 2026-10-12T00:00:00Z
updated: 2026-10-12T00:00:00Z
goal: find_root_cause_only
audit_acknowledged:
  milestone: v1.0
  at: 2026-10-20
  status: diagnosed
---

## Current Focus

hypothesis: CONFIRMED (AND-gate fired, two contributing causes). (1) The old promise and complaint question wording made Jev score an ordinary intake exchange at 0.30 / 0.47. (2) One shared bar, gate.classifier.gate_check_max_yes = 0.075, is applied to price, promise, discount and complaint alike (TriageReading.php:62), and 0.075 is the strictest literal reading of D-03/D-45 (abs(2p-1) >= 0.85), which RESEARCH already warned "will hold some clear messages". After the reword, Jev's residual noise on promise (0.09) is above 0.075 but well under the researched looser reading (0.15).
test: replayed the two stored Jev score sets through the real TriageReading::read() with current thresholds; compared with stored verdicts for messages 25 and 28
expecting: n/a, diagnosis complete
next_action: hand root cause and fix map to plan-phase --gaps

reasoning_checkpoint (diagnose-only, fields filled for the planner):
  hypothesis: "Held because Jev's promise/complaint P(yes) on benign intake text exceeds the single shared 0.075 bar; neither the reword alone nor the bar alone clears it"
  confirming_evidence:
    - "Stored verdict msg 25 (old wording): promise 0.30, complaint 0.47, reasons [classifier:complaint, classifier:promise]; replay through TriageReading::read() gives identical reasons"
    - "Stored verdict msg 28 (new wording, icm_version 3): promise 0.09, complaint 0.07, reasons [classifier:promise]; replay gives identical reasons"
  falsification_test: "If msg 28's scores (0.09 / 0.07) were run with a promise bar of 0.15 and the reply still held, the hypothesis would be wrong; by TriageReading.php:62 arithmetic it sends (every other check already passes)"
  fix_rationale: "The user's fix changes only the bar for promise and complaint; code checks, the label floor and Jev-can-only-hold (D-02) are untouched, so it widens only what Jev alone may pass"
  blind_spots: "Only one live message (2 samples) of calibration; 0.09 vs 0.15 is a thin margin; no live run yet with a bar of 0.15; Jev scores for other ordinary intake texts (scheduling, faq) not sampled"
  candidate_causes:
    - "code: single shared bar for four different questions (TriageReading.php:62)"
    - "config: 0.075 value in config/gate.php:64 chosen as strictest reading (D-45), flagged in RESEARCH OQ-1 as likely to over-hold"
    - "data: question wording in rules/triage.md elicited inflated scores (0.30 / 0.47 vs 0.09 / 0.07 after reword)"
    - "environment: Jev model typesafe/jev-1.13-20260917 scoring behavior (same model on both runs, so not a variable)"
  and_gate: "yes. Wording alone (msg 28) still held on promise; bar alone with old wording (msg 25: 0.47 / 0.30) still held on both. Both changes are needed for this message to send."

## Symptoms

expected: An ordinary intake reply with clean code checks and no real price, discount, promise or complaint content sends on its own instead of being held.
actual: Held. Jev complaint 0.47, promise 0.30 against 0.075 bar (before triage reword); complaint 0.07, promise 0.09 after reword, still held on promise.
errors: none (gate_reason classifier:complaint / classifier:promise; plain reason "Held: makes a promise")
reproduction: Test 2 in .planning/milestones/v1.0-phases/02-text-front-desk/02-UAT.md (live keys, icm:sync, queue worker, text "My AC is blowing warm air" from /dev/chat)
started: found in Phase 2 UAT, 2026-10-12

## Eliminated

- hypothesis: the code checks, label or length checks are holding the reply
  evidence: stored gate JSON for msgs 25 and 28 has checks.code.passed true and the reply label intake_question is in send_on_own; only the classifier reading lists reasons
  timestamp: 2026-10-12

- hypothesis: Jev intent or confidence is the blocker
  evidence: intent intake at confidence 1.0 on both (>= 0.85, intake is sendable); reasons contain no classifier_intent / classifier_unsure
  timestamp: 2026-10-12

- hypothesis: a bug in how TriageReading compares or reads the numbers
  evidence: replaying the stored scores through TriageReading::read() reproduces the stored reasons exactly; `>` against 0.075 is the specified behavior (D-45)
  timestamp: 2026-10-12

- hypothesis: the reworded triage.md was never picked up, so the reply was held under the old wording
  evidence: icm_versions id 2 compiled holds the old promise wording ("promise a result, a time or an outcome?"), id 3 (activated 2026-10-12 21:55:02) holds the new one; msg 25 (21:51:26) ran under v2 and scored 0.30 / 0.47, msg 28 (21:55:54) ran under v3 and scored 0.09 / 0.07. The reword is live and effective, just not enough.
  timestamp: 2026-10-12

## Evidence

- timestamp: 2026-10-12
  checked: config/gate.php:62-67 and app/SendRule/TriageReading.php:23,49,62
  found: one scalar `gate_check_max_yes` 0.075. TriageReading::GATE_CHECKS = [complaint, price, promise, discount]; the loop at :61-65 compares each `$yes($check) > $thresholds['gate_check_max_yes']`. The same scalar is also the LOWER bound of the asks_for_person "unsure" band at :49.
  implication: (1) confirmed single shared bar for all four yes/no gate checks. (2) The asks_for_person unsure band is coupled to the same key; splitting or removing gate_check_max_yes must keep :49 reading 0.075 or asks_for_person behavior changes, contrary to "asks_for_person thresholds unchanged".

- timestamp: 2026-10-12
  checked: where life_safety_min_yes and asks_for_person_min_yes are read
  found: only TriageReading.php:47 (life_safety_min_yes, 0.075, separate key) and :48 (asks_for_person_min_yes, 0.85, separate key); config/gate.php:65-66. No other consumer in app/.
  implication: life-safety and asks_for_person-handoff thresholds are separate keys and stay untouched by a change limited to promise and complaint. Only the asks_for_person unsure floor (:49) shares gate_check_max_yes.

- timestamp: 2026-10-12
  checked: dev database, messages 25 and 28 (kind agent_reply), gate JSON
  found: msg 25 (21:51:26, icm v2): intent intake 1.0, yes/no price 0.02, promise 0.30, discount 0.01, complaint 0.47, life_safety 0.01, asks_for_person 0.02; reasons [classifier:complaint, classifier:promise]. msg 28 (21:55:54, icm v3): promise 0.09, complaint 0.07, others <= 0.02; reasons [classifier:promise]. Both: code check passed, label intake_question, model typesafe/jev-1.13-20260917, gate version 1.
  implication: matches the user's report exactly. After the reword, ONLY promise (0.09) blocks the send; complaint 0.07 is already under 0.075 by 0.005. With a 0.15 bar on promise and complaint, msg 28 would send; msg 25's old-wording scores (0.30 / 0.47) would still hold, so the reword must stay.

- timestamp: 2026-10-12
  checked: replay of both stored score sets through App\SendRule\TriageReading::read() with the current thresholds (scratchpad script, no app code changed)
  found: msg 25 -> sendable false, reasons ["classifier:complaint","classifier:promise"]; msg 28 -> sendable false, reasons ["classifier:promise"]. Identical to stored.
  implication: the behavior is deterministic and as coded; this is a calibration/design gap, not a defect in the comparison.

- timestamp: 2026-10-12
  checked: .planning/milestones/v1.0-phases/02-text-front-desk/02-RESEARCH.md:415-418 and :677 (OQ-1), 02-CONTEXT.md:91 (D-45)
  found: 0.075 comes from reading D-03's "0.85" as abs(2p-1) >= 0.85. RESEARCH predicted: "Docs examples of clear 'no' answers include 0.02 and 0.07, so a 0.075 cut-off will hold some clear messages ... the looser alternative is p_max >= 0.85 and noul <= 0.15. Recommendation: ship strict, keep both numbers in config/gate.php, store them on each verdict, relax from the approval log."
  implication: 0.15 is the already-documented looser reading of D-03 (P(no) >= 0.85), not an arbitrary number, and "relax from the log" is the designed path. The amendment keeps D-03's 0.85 meaning for promise/complaint under the alternative reading and keeps the strict reading for price and discount.

- timestamp: 2026-10-12
  checked: where D-45 is recorded
  found: only .planning/milestones/v1.0-phases/02-text-front-desk/02-CONTEXT.md:91 (plus quoted in 02-07-PLAN.md, 02-07-SUMMARY.md, RESEARCH OQ-1). Not in .planning/PROJECT.md (DEC-01..DEC-14 only) and not in docs/DECISIONS.md. Last Phase 2 decision id is D-55 (02-CONTEXT.md:101). map/ cards contain no threshold numbers.
  implication: the amendment is recorded in 02-CONTEXT.md (new decision, next free id D-56, saying it amends D-45) and D-45's own line should get a pointer. No PROJECT.md / docs/DECISIONS.md edit is required to stay consistent.

- timestamp: 2026-10-12
  checked: tests that pin 0.075 or the flat threshold shape
  found: tests/Unit/SendRule/TriageReadingTest.php:14 (readingThresholds() hard-codes the flat 4-key array and is also the expected value in the toArray test at :109) and dataset rows :59-60 (price at 0.075 / 0.0751), :69-70 (asks_for_person at 0.075 / 0.0751, the unsure floor), :115 (no-rounding check on price 0.07500000001). Promise and complaint rows are 0.5 (:56) and 0.3 (:58): both stay held under a 0.15 bar. Feature tests GateVerdictTest.php:27 and ProcessInboundTest.php:243 assert `$gate['thresholds']` toBe(config('gate.classifier')), so they follow whatever shape the config has. No Feature test scripts promise or complaint in the 0.075-0.15 band (grep of tests/ found none outside TriageReadingTest and JevClassifierTest, whose values are 0.01-0.04 or 1).
  implication: no existing test flips from the change in values; TriageReadingTest needs its threshold fixture updated to the new shape plus new boundary rows (promise/complaint exactly 0.15 sends, 0.1501 holds, promise 0.0751 now sends, price/discount still hold at 0.0751, asks_for_person floor still 0.075). Per CLAUDE.md, tests first.

- timestamp: 2026-10-12
  checked: whether the triage.md wording change is picked up outside icm:sync / ActiveConfig, and whether any test fixture copies it
  found: app code reads triage only through ActiveConfig::triage() (ActiveConfig.php:119, compiled active icm_versions row); examples/example-hvac is copied at runtime into a temp folder by tests/Pest.php:76 for every Feature test. The only test text that quotes a triage question verbatim is TriageValidationTest.php:17 (the asks_for_person line, unchanged by the diff). Nothing quotes the promise or complaint wording (grep of the old and new wording across tests/ and app/ hits only examples/example-hvac/rules/triage.md and docs in .planning).
  implication: the uncommitted reword goes live only through icm:sync (already done: v3) and flows into tests automatically through the folder copy without breaking anything. It is safe to keep. It is uncommitted, so the fix plan must either commit it (it is a business-file change by Ian) or leave committing to Ian; the plan must not stash or reset it.

- timestamp: 2026-10-12
  checked: audit trail: what a stored verdict records about the bar
  found: DecideSendOrDraft.php:59 stores `thresholds` = config('gate.classifier') wholesale at the top of the gate JSON, and TriageReading::toArray() (:100) stores the same array again under checks.classifier.reading.thresholds (DecideSendOrDraft.php:109). Both copy whatever keys the config array holds at decision time. gate.version stays 1. No icm version id is stored on the verdict.
  implication: if the per-question bars live inside the `classifier` array in config/gate.php, every new verdict records which bar applied per question with no extra code, and old verdicts keep their flat 0.075 record. If they were placed under a different config key they would NOT be recorded. Minor, separate observation: verdicts do not record which ICM version (triage wording) produced the scores, so rows 25 and 28 are only distinguishable by timestamp. Not required for this fix.

- timestamp: 2026-10-12
  checked: what stays a hard veto when the Jev promise/complaint bar loosens
  found: ReplyLabel::neverOnItsOwn() (price, promise, discount, complaint labels never send, ReplyLabel.php:35) and CodeChecks (dollar amounts, price words near numbers, DISCOUNT_PHRASES, PROMISE_PHRASES such as "guarantee", "definitely", "for sure", CodeChecks.php:25-38) both run before Jev and Jev can only hold (D-02, DecideSendOrDraft.php:64-75). The complaint INTENT (customer sounds like a complaint) is a separate Jev choice answer and complaint is not a sendable intent (classifier_intent:complaint holds).
  implication: the change widens only Jev's extra yes/no net for promise and complaint; explicit promise wording, prices, discounts and complaint-labelled/-intent replies are still held by code and by the intent check.

## Resolution

root_cause: "Two contributing causes (AND-gate). (1) config/gate.php:64 holds one scalar, gate_check_max_yes = 0.075, and app/SendRule/TriageReading.php:62 applies it to price, promise, discount and complaint alike. 0.075 is the strictest literal reading of D-03/D-45 (abs(2p-1) >= 0.85); RESEARCH OQ-1 (02-RESEARCH.md:418) already said it would hold some clear messages and named 0.15 (P(no) >= 0.85) as the looser alternative. (2) The original promise and complaint question wording in examples/example-hvac/rules/triage.md made Jev score a plain intake exchange at promise 0.30 / complaint 0.47; the uncommitted reword cut those to 0.09 / 0.07, but promise 0.09 still sits above the shared 0.075 bar, so the reply still holds. The reword alone and the per-question bar alone each leave this message held."
fix: not applied (diagnose-only). Direction: keep the triage.md reword; make the yes/no bar per question inside the `classifier` array in config/gate.php (price 0.075, discount 0.075, promise 0.15, complaint 0.15); have TriageReading read each gate check's own bar; keep life_safety_min_yes (0.075) and asks_for_person_min_yes (0.85) untouched and keep the asks_for_person unsure floor at 0.075; record the amendment.
verification: n/a
files_changed: []
