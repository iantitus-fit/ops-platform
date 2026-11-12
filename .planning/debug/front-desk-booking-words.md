---
status: diagnosed
trigger: "UAT test 4 gaps G-08-4 (front desk did not answer 'What time are you coming?' from the card on a booked contact) and G-08-4b (booking texts say the owner will confirm the time and repeat the link line's words), diagnosed together as a likely common source in FrontDeskPrompt."
created: 2026-11-11T00:00:00Z
updated: 2026-11-11T00:00:00Z
goal: find_root_cause_only
outcome: ROOT CAUSE FOUND (what the model reads, not code behaviour). Fix is a decision for Ian: it reopens 08 D-05 and D-39.
---

## Current Focus

hypothesis: CONFIRMED. The model reads a rule that says a booking is never set until the owner confirms it (FrontDeskPrompt.php:41, which even names the card), reads no description of how booking now works (the system appends the link line, the page books at once, code texts the confirmation), and the scheduling label is defined as "using only the hours and notice in the files". Every sentence it wrote in messages 10, 13 and 20 follows from that.
test: reconstruct the exact instructions, card and history for messages 10, 13 and 20 from code plus stored rows (done, read-only, no model or Jev call)
expecting: n/a, diagnosis complete
next_action: return ROOT CAUSE FOUND with the options table to the caller; Ian decides before any plan
bug_class: Bohrbug for the inputs (deterministic prompt, fixed rows); the model's wording is stochastic, but every input that licenses it is visible and unchanged. SBFL skipped (no failing test covers model behaviour). Knowledge base .planning/debug/knowledge-base.md does not exist; no match. MemPalace not queried (nothing in it could change a prompt reconstruction).

reasoning_checkpoint:
  hypothesis: "The model refuses to state the booked time and says 'the owner will confirm' because FrontDeskPrompt::RULES line 41 tells it the card shows only what the customer said and did, and that a visit, time or booking is never set 'even when the thread mentions one; the owner confirms those', and nothing it reads says the system books at once, texts the confirmation, or appends the link line"
  confirming_evidence:
    - "Reconstructed prompt for message 20: the card carries '- Next appointment: Thursday, November 12, 2026 at 10:00 AM (Central Time), AC repair, Job 1' as the 8th header bullet, a '- Booked: Thursday, November 12, 2026 at 10:00 AM, AC repair' sub-line under Job 1, a 'Visit booked' timeline entry, and the code's own 'You are booked for AC repair on Thursday, November 12 at 10:00 AM' text appears as an assistant turn in history too. The model was shown the time three times and still deflected."
    - "Stored verdict for message 20: label other, agent_reply 'I can't confirm the visit time myself, so the owner will follow up...' (mirrors RULES lines 37, 38, 41)"
    - "Stored original_body of message 10: '...What day works best for you? The owner will confirm the time.' and gate.inputs.agent_reply of message 13: '...Pick a time that works for you, and the owner will confirm the visit.' Both say the owner confirms, which is what line 41 says and is false (appointments.booked_at equals the booking text's sent_at to the second for the page booking)."
    - "grep of the rendered instructions for booking words finds only: line 41, the scheduling label (line 49), the agent body 'it offers to book a visit', hours front matter, book_online flags, and system-replacement's 'rather than booked online'. No file the agent loads holds the link line (booking.md is not in loads), and history/card replace the stored link line with '(The system added the booking link here.)'."
  falsification_test: "Replay message 19 against a prompt whose line 41 is corrected and that describes booking, with a live model. If the model still defers, the cause is the model's priors, not the prompt. Not run: needs a live model call (forbidden here)."
  fix_rationale: "Change what the model reads (the rule that is now false and the missing description of booking), not a list of words to avoid."
  blind_spots: "No stored prompt exists (see Evidence 1), so the prompt is reconstructed; the card was rendered at diagnosis time, not at run time, but every card line that matters predates the run (activities 44-50 vs run at 23:18:27). The model's reasoning for choosing label other is inferred from the structured output. Jev's reading of a reply that states a time is unknown (cannot call Jev)."
  candidate_causes:
    - "code (prompt text): RULES line 41 and lines 37-38 forbid stating a booked time and say the owner confirms (confirmed)"
    - "code (prompt text): no description of booking, link line, instant confirmation (confirmed; this is the second half of the common source)"
    - "config/folder: agents/front-desk.md body carries only D-05's one line, and booking.md (link_line) is not loaded (confirmed contributing; folder cannot override a code rule)"
    - "code (label vocabulary): scheduling is 'using only the hours and notice in the files', so a deferral or a restated booked time has no clean home (contributing, explains label other)"
    - "decision/data: D-39 says the promise label holds a reply that states a time, but the UAT truth says 'labeled scheduling' (documentation conflict, not a defect)"
    - "model: ruled out as the primary cause, the model obeyed the rule as written"
  and_gate: "yes for G-08-4: it needs the rule (41) AND no booking description AND no label that fits a restated booked time. Jev would also have held the reply independently (asks_for_person 0.23 > 0.075 on the words check). For G-08-4b: the false 'owner will confirm' needs rule 41 AND no booking description; the echo of the link line needs the model not knowing what code appends."

## Symptoms

expected: G-08-4: with a booked visit on the card, the front desk answers "What time are you coming?" from the card, labeled scheduling (D-39). G-08-4b: the front desk's booking texts never say the owner will confirm the time and do not repeat the link line's words.
actual: Message 20 (+13175550177, visit Thursday Nov 12 10:00 AM on the card): draft "Thanks for checking. I can't confirm the visit time myself, so the owner will follow up with you on exactly when we're coming.", label other, held (label:other); customer got the holding reply. Message 10 draft said "What day works best for you? The owner will confirm the time." Message 13 (sent) said "Pick a time that works for you, and the owner will confirm the visit. Pick a time that works for you here: [link]".
errors: none
reproduction: UAT test 4, Dev chat on the local dev database (database/database.sqlite)
started: found at Phase 8 UAT, 2026-11-10

## Eliminated

- hypothesis: the card for contact +13175550177 did not carry the appointment at the time of message 20, so the model could not have answered
  evidence: appointment 6 (contact 7, state booked, starts 2026-11-12 16:00 UTC = 10:00 AM Central, booked_at 23:17:43 by user) and activities 44-50 (Visit booked at 23:17:43, confirmation reply at 23:17:43, inbound text at 23:18:01) all predate the run (draft written 23:18:27). The rendered card shows the Next appointment line and the Booked sub-line.
  timestamp: 2026-11-11
- hypothesis: the model repeated the link line because it read the link line
  evidence: rooms/schedule_and_booking/booking.md is not in agents/front-desk.md loads; FrontDeskPrompt::storedLinkLine()/withoutBookingAddress() (FrontDeskPrompt.php:355-379) replace the stored link line with '(The system added the booking link here.)' in both history and card; the rendered prompt for message 13 holds no 'Pick a time' text before the run. The echo is two paraphrases of the same natural sentence, not a copy.
  timestamp: 2026-11-11
- hypothesis: message 10 was held because of its wording
  evidence: gate.reason is classifier:complaint, from Jev's reading of the customer text 'Over 10 years' (complaint 0.20 vs 0.15). Words check passed (sendable true). Wording is unrelated; this is the deferred Phase 10 calibration item.
  timestamp: 2026-11-11
- hypothesis: the WR-07 link-hiding code was not live for message 13
  evidence: commit e7aba5c (2026-11-09 08:58 -0600) predates the Nov 10 UAT run; git log shows no later change to app/Ai/FrontDeskPrompt.php; git status clean for app/.
  timestamp: 2026-11-11
- hypothesis: the live ICM version changed between the runs, so the instructions differ from what I rendered
  evidence: icm_versions has one row (id 1, active, activated 2026-11-09 17:51:48, before every run).
  timestamp: 2026-11-11

## Evidence

- timestamp: 2026-11-11
  checked: whether the run stored its prompt or card
  found: no. messages.gate holds only version, label, wants_person, inputs {customer_text, agent_reply}, customer_reading, checks, thresholds, reply_model {provider, model}, failed_attempts, reason. agent_conversations and agent_conversation_messages have 0 rows (SDK memory tables, no referrer per map/CLAUDE.md). laravel.log holds no prompt text.
  implication: the prompt is reconstructed from code (FrontDeskPrompt::instructions/history/promptText) plus the stored rows; reconstruction scripts are in the scratchpad, read-only, no model call.
- timestamp: 2026-11-11
  checked: what the model read for message 20 (instructions, rendered by FrontDeskPrompt::instructions for contact 7)
  found: RULES (FrontDeskPrompt.php:31-66); agent body (examples/example-hvac/agents/front-desk.md:19-23); business/profile.md, hours.md, faq.md, the four service files, voice/how-we-talk.md, rules/sending.md; then the card; then Right now. Card header bullets include '- Next appointment: Thursday, November 12, 2026 at 10:00 AM (Central Time), AC repair, Job 1' (CustomerCard.php:86, :254-269), then 'Intake answers', then '## Jobs' with '  - Booked: Thursday, November 12, 2026 at 10:00 AM, AC repair' (CustomerCard.php:281-303), then the timeline with 'Visit booked' and 'You are booked for AC repair on Thursday, November 12 at 10:00 AM'. History (assistant turn, 5:17 pm) repeats the same confirmation text. Prompt: 'What time are you coming?'.
  implication: the data was in front of the model three times. It was told in RULES line 41 that the card shows what the customer said and did, not what the business agreed, and never to say a time is set.
- timestamp: 2026-11-11
  checked: the instruction lines that produce the words
  found: FrontDeskPrompt.php:37 'Use only the business files below as facts. When they do not say, tell the customer the owner will follow up.'; :38 'Never promise a time, a visit or a result.'; :41 'The thread and the customer card show what the customer said and did, not what the business has agreed to. Never say a visit, a time or a booking is set, even when the thread mentions one; the owner confirms those.'; :49 'scheduling: the reply is about booking or timing, using only the hours and notice in the files.'; :54 'other: anything else.'; agent body :22 'It leaves promises and complaints to the owner.'
  implication: message 20's deflection ("I can't confirm the visit time myself, so the owner will follow up") is lines 37, 38 and 41 followed literally; label other is line 54 because the reply uses no hours or notice (line 49). Messages 10 and 13's 'the owner will confirm' is line 41.
- timestamp: 2026-11-11
  checked: origin of line 41
  found: introduced by f425e91 (2026-10-13, fix(02-39)) for G-02-2.4, to stop the model restating a visit time that a customer wrote in the thread ('tomorrow at 9') as booked (see .planning/debug/agent-lacks-current-date.md Resolution item 8; tests/Feature/FrontDesk/FrontDeskPromptTest.php:322-332 pins both sentences). Written when the only appointments were customer claims.
  implication: the rule was right in Phase 2 and became false for system-recorded bookings in Phase 8. D-38 put system-recorded appointment lines on the card but D-39 ('No new front-desk rule') and D-05 ('FrontDeskPrompt unchanged') left the rule untouched.
- timestamp: 2026-11-11
  checked: whether the model is told the system adds the link line or the page books at once
  found: no. Link-related text it can read: (a) the note '(The system added the booking link here.)' (FrontDeskPrompt.php:72, applied at :343-368) in EARLIER turns only, so absent on the first offer (message 10); (b) 'book_online: true' in the ac-repair front matter with no explanation; (c) system-replacement body 'a visit is set up with the owner rather than booked online'. Nothing says code appends the owner's link line to a scheduling reply (RunFrontDesk.php:601-615, ServiceToBook.php), that the page books at once, or that code texts the confirmation. Message 10's first offer therefore had no knowledge of any of it; message 13 had only the after-the-fact note.
  implication: with no description of booking, the model's only account of how a visit gets set is line 41: the owner confirms. It also writes its own 'pick a time' invitation, because it cannot know the system will append one that says the same.
- timestamp: 2026-11-11
  checked: that 'the owner will confirm' is false now
  found: appointments 5 (page booking) booked_at 2026-11-10 23:09:24, state booked, booked_by customer; message 14 (booking_text) sent 23:09:24. No owner step exists. Owner is only alerted (appointment_booked, D-33). For message 19 a change_request alert fired (owner_alerts id 7, 'Asked about the booked visit: AC repair, Thursday, November 12 at 10:00 AM'), as D-34 designs; skipped only because the dev owner has no phone.
  implication: 4b's phrase is factually wrong for the page path; D-34 already puts the owner in the loop on a booked customer's scheduling text without the model deferring to them.
- timestamp: 2026-11-11
  checked: why message 20 was held, in code and in the stored verdict
  found: gate.checks.label.passed=false, label other, reason code label:other, plain 'Held: needs your judgment'. DecideSendOrDraft.php:62-64 computes labelPassed = label not in ReplyLabel::neverOnItsOwn() (ReplyLabel.php, price/promise/discount/complaint) AND label in the live rules/sending.md send_on_own [intake_question, scheduling, faq_answer]; other is in draft_for_owner. The veto match at :98-99 returns 'label:other'. Jev was still asked (:106). Jev's words check on the model's actual reply: intent scheduling 1.0, promise 0.08, asks_for_person 0.23, reading sendable=false, reason classifier_unsure:asks_for_person (TriageReading.php:49,75-76: 0.23 > gate_check_max_yes 0.075 and < asks_for_person_min_yes 0.85, config/gate.php:73,79). The customer-text reading was clean (scheduling 0.99, asks_for_person 0.07). The holding reply (message 21, kind holding_reply, SendFixedReply/FixedReplies) went out at 23:18:27.
  implication: had the model labeled the same words scheduling, Jev's words check would still have held them. The 'owner will follow up' wording reads to Jev as a hand-off toward a person.
- timestamp: 2026-11-11
  checked: message 13 verdict
  found: label scheduling, gate_outcome send, parts [model_words, booking_link]; model words promise 0.10 (limit 0.15), asks_for_person 0.03, so 'the owner will confirm the visit' passes every check. Code appended the link line (RunFrontDesk.php:608-615, withBookingLine) to words that already said 'Pick a time that works for you'.
  implication: the send rule has no check for 'says the owner confirms' or for duplicating the link line, so nothing but the prompt can prevent either.
- timestamp: 2026-11-11
  checked: D-39 versus the UAT truth
  found: 08-CONTEXT D-39: 'No new front-desk rule: the existing promise label still holds a reply that states a time or a visit.' 08-16-PLAN.md:184 and 08-16-SUMMARY.md:201: 'it states the booked time or is held by the promise label'. D-01 says a booked customer asking about their time 'gets the reply without a second link' (a scheduling reply). The UAT gap truth says 'answers ... from the card, labeled scheduling'. triage.md's promise question: 'Does draft_reply commit the business to a specific time, date, visit, price or result?'
  implication: D-39 as decided could never produce 'answered from the card, labeled scheduling, sent on its own': a reply stating a time is held by the model's promise label or by Jev's promise question. The observed behaviour matches D-39's 'held' half in effect (customer got the holding reply) but with label other, and without the model using the card. Ian must say which outcome he wants before a fix is planned.
- timestamp: 2026-11-11
  checked: latent hazard found on the way
  found: the link rides only when ServiceToBook allows it (room live with booking file, service resolved and book_online, no visit already booked ahead; ServiceToBook.php:67-79), but the model is never told, so words like 'here's the booking link again' can reach a customer with no link behind them (VISIT_BOOKED, SERVICE_UNCLEAR, BOOKING_OFF).
  implication: any fix that tells the model about the link must be true by construction (conditional on the same conditions), or it creates a promise the code cannot keep.

## Resolution

root_cause: |
  One shared source, in what the model reads, plus two contributing gaps.
  Shared: app/Ai/FrontDeskPrompt.php:41 (with :37-38) tells the model the customer card shows only what the customer said and did, that a visit, time or booking is never to be said set 'even when the thread mentions one', and that 'the owner confirms those'. That was written in Phase 2 for customer-claimed times (G-02-2.4, f425e91). Phase 8 made bookings system-recorded and instant, put them on the card (D-38), and left the rule and the prompt untouched (D-05, D-39). The model therefore (a) will not state a booked time even when the card, the history and the booking text all show it (message 20), and (b) tells customers the owner will confirm a booking the page books at once (messages 10, 13).
  Second shared gap: nothing the model reads says the system appends the owner's link line to a scheduling reply, that the page books at once, or that code texts the confirmation. booking.md is not loaded and earlier link lines are shown as a note. So the model writes its own 'pick a time' invitation and the link line, appended by code, repeats it. It is a paraphrase echo, not a copy.
  Contributing for G-08-4 only: the label vocabulary (:49) defines scheduling as 'using only the hours and notice in the files', so a deflection lands in other (:54) and is held by DecideSendOrDraft.php:62-64,98-99; Jev would also have held the words (asks_for_person 0.23). And D-39 itself says a reply stating a time is held under promise, which conflicts with the UAT truth 'labeled scheduling'.
  G-08-4 and G-08-4b share the source (line 41 plus the missing booking description). They are not fully the same defect: G-08-4 also needs the label and D-39 question settled.
fix: not applied (diagnose only)
verification: not applicable
files_changed: []
