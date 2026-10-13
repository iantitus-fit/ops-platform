---
status: complete
phase: 02-text-front-desk
source: [02-VERIFICATION.md]
started: 2026-10-13T15:30:34Z
updated: 2026-10-13T19:54:59.688Z
---

## Current Test

[testing complete]

## Tests

### 1. Needs you card at phone size (WINDOWS 10, UAT Test 1 re-run). Text 'How much for a new unit?' from /dev/chat, open the alert link on a phone or at 390x664 in Chrome device mode. Repeat with a ~14-message thread and a several-hundred-character customer text.
expected: Without scrolling: held reason, a 'Customer wrote, <time>:' line, the draft, Send and Discard; customer details collapsed. Scrolling down shows the thread oldest first with its box ending at the latest message.
result: pass

### 2. Live run after icm:sync with the three keys (WINDOWS 6, UAT Test 2 re-run). Text 'My AC is blowing warm air', then a few ordinary intake, scheduling and FAQ texts; read gate:export.
expected: The intake reply sends on its own. Promise and complaint scores stay under 0.15 with margin on other ordinary texts (0.09 against 0.15 is a thin margin); price and discount still hold.
result: pass

### 3. Send order on the screen (WINDOWS 7). Hold a draft from /dev/chat, send it from the conversation screen a few minutes after the holding reply.
expected: Conversation screen: customer text, holding reply, sent draft, each with its own time. /dev/chat the same.
result: pass

### 4. /dev/chat after a held draft (WINDOWS 8).
expected: Only the customer's text and the holding reply show; the draft's words appear after Send.
result: pass

### 5. Dana's draft and a furnace text (WINDOWS 9). Re-run Dana's held draft after icm:sync; text 'My furnace won't turn on.' from a new number.
expected: The draft does not call the visit 'tomorrow' or booked; the furnace text gets a furnace-repair intake question instead of a held 'other'.
result: pass

### 6. Alert link on a real phone (WINDOWS 5, carried from the first verification).
expected: Link opens the conversation; held reason, draft and Send visible without scrolling.
result: pass

### 7. First CI run on Postgres (INFRA-01). The Postgres leg was not run in this round; the new sent_at migration drops and recreates the partial unique index messages_one_live_draft_per_conversation.
expected: Both matrix legs (sqlite, pgsql) green.
result: pass

## Summary

total: 7
passed: 7
issues: 0
pending: 0
skipped: 0
blocked: 0

## Gaps

- gap_id: G-02-1.1
  truth: "The thread under the Needs you card opens at the latest message (D-29)"
  status: resolved
  resolved_by: 02-40-PLAN.md
  resolved_at: 2026-10-13
  reason: "User reported: the thread under the Needs you card starts at the oldest message, not the latest (D-29 says it opens at the latest)"
  severity: minor
  test: 1
  root_cause: "The only open-at-latest mechanism is x-init scrollTop=scrollHeight on the 60vh thread box (conversation-thread.blade.php:40). It scrolls only inside the box, and the box starts ~526px down at phone width, below the header and Needs you card, so its bottom (the latest message) is below the fold; short threads (2-6 messages) don't overflow at all, so the oldest message shows first. Ordering (Conversation::messages ascending) and Alpine init are correct. The D-29 test only checks the attribute string. D-29's two criteria (Send visible without scrolling; thread opens at latest) are in tension under the current layout."
  artifacts:
    - path: "resources/views/livewire/conversation-thread.blade.php"
      issue: "scroll attribute (line 40) only moves the inner box, which sits below the fold behind the Needs you block (lines 2-32)"
    - path: "tests/Feature/Filament/ConversationViewTest.php"
      issue: "lines 80-86 assert the attribute string, not layout or scroll position"
  missing:
    - "DECIDED by Ian 2026-10-12: show the customer's last message in the Needs you card (pulls the Phase 02.1 roadmap follow-up from UAT Test 1 into this fix; remove that 02.1 roadmap line when the fix lands). Thread stays oldest-first and still scrolls its box to the bottom; Send stays visible without scrolling"
    - "A test that checks real layout or scroll position at ~390x664 with a short and a long thread (Pest browser test or headless Chrome)"
  debug_session: ".planning/debug/needs-you-thread-opens-oldest.md"

- gap_id: G-02-1.2
  truth: "Outbound messages sort by when they were sent, so a draft sent from the conversation screen appears after the holding reply in /dev/chat"
  status: resolved
  resolved_by: 02-41-PLAN.md
  resolved_at: 2026-10-13
  reason: "User reported: after Send, the sent reply is listed above the holding reply in /dev/chat because it keeps the draft's creation time; outbound messages should sort by when they were sent"
  severity: minor
  test: 1
  root_cause: "Every thread orders by messages.created_at, id (Conversation.php:73) and every time label shows created_at. A held draft is the same row that later becomes the sent message (D-54), and the holding reply is created after it (RunFrontDesk.php:346 vs :374), so a later-sent draft always sorts above the holding reply. messages has no send-time column; OutboundSender::transmit() computes $sentAt (line 261) but writes it only to the activity and last_message_at/last_activity_at."
  artifacts:
    - path: "app/Models/Conversation.php"
      issue: "messages() orders by created_at, id (line 73); inherited by ConversationThread, DevChat, FrontDeskPrompt::history, RecordsWriter"
    - path: "app/Actions/OutboundSender.php"
      issue: "send time (line 261, and markSentAfterRecordingFailed line 315) never written to the message row"
    - path: "resources/views/livewire/conversation-thread.blade.php"
      issue: "time label (line 52) shows created_at"
    - path: "resources/views/livewire/dev-chat.blade.php"
      issue: "time label (line 10) shows created_at"
    - path: "app/Actions/SendFixedReply.php"
      issue: "side finding: line 97 ->latest('id')->first() on messages() compiles to order by created_at asc, id asc, id desc and returns the oldest holding reply"
  missing:
    - "Nullable messages.sent_at, written in OutboundSender::transmit() and markSentAfterRecordingFailed(); backfill sent/delivered rows with coalesce(resolved_at, created_at); recreate the partial unique index messages_one_live_draft_per_conversation in the same migration (SQLite table rebuild, plan 02-19)"
    - "Order Conversation::messages() by coalesce(sent_at, created_at), id; one Message accessor for when-this-happened used by both blade labels, FrontDeskPrompt and RecordsWriter"
    - "Decide deliberately whether FrontDeskPrompt::history() skips burst messages instead of breaking at the first (lines 112-114), since a sent draft can now sort after the burst"
    - "Review RunFrontDesk reply cap (lines 245-246) and SendFixedReply::holdingAlreadySent() against the new send time; fix the latest('id') stacking at SendFixedReply.php:97"
    - "Regression test that advances the clock between holding reply and approval and asserts order in ConversationThread and DevChat"
  debug_session: ".planning/debug/outbound-sorts-by-draft-time.md"

- gap_id: G-02-2.1
  truth: "An ordinary intake reply with clean code checks and no real price, discount, promise or complaint content sends on its own instead of being held"
  status: resolved
  resolved_by: 02-41-PLAN.md
  resolved_at: 2026-10-13
  reason: "User reported: ordinary intake replies never send on their own. \"My AC is blowing warm air\" got a correct intake question (code checks clean, Jev intent intake 1.0) but Jev scored complaint 0.47 and promise 0.30 against the 0.075 bar. After rewording the complaint and promise questions in examples/example-hvac/rules/triage.md (uncommitted; keep it) they fell to 0.07 and 0.09 and the reply was still held on promise. Proposed fix: keep the new triage wording; make the yes/no bar per question in config/gate.php (price and discount stay 0.075, promise and complaint go to 0.15); this amends D-45 and must be recorded; life-safety and asks_for_person thresholds unchanged."
  severity: major
  test: 2
  root_cause: "Two causes together, each enough to hold: (1) one shared bar, config/gate.php:64 gate_check_max_yes = 0.075, applied by TriageReading.php:62 to price, promise, discount and complaint alike (the strictest reading of D-03/D-45; 02-RESEARCH OQ-1 predicted it would hold clear messages and named 0.15 as the looser alternative); (2) the old promise/complaint wording in triage.md scored a plain intake exchange at promise 0.30 / complaint 0.47. Stored verdicts replayed through TriageReading::read() reproduce the reasons exactly, so this is calibration, not a comparison bug. Message 28 (new wording, ICM v3) still held on promise 0.09 > 0.075."
  artifacts:
    - path: "config/gate.php"
      issue: "single gate_check_max_yes (line 64) for all four questions; docblock lines 49-51"
    - path: "app/SendRule/TriageReading.php"
      issue: "loop at line 62 reads one bar; line 49 also uses gate_check_max_yes as the asks_for_person unsure floor"
    - path: "tests/Unit/SendRule/TriageReadingTest.php"
      issue: "readingThresholds() fixture (line 14) hard-codes the flat shape and is the toArray expectation (line 109)"
    - path: ".planning/phases/02-text-front-desk/02-CONTEXT.md"
      issue: "D-45 (line 91) recorded only here; last decision is D-55"
    - path: "examples/example-hvac/rules/triage.md"
      issue: "uncommitted reword by Ian; keep it"
  missing:
    - "Per-question bars inside the classifier array: price and discount 0.075, promise and complaint 0.15 (e.g. promise_max_yes and complaint_max_yes keys, leaving gate_check_max_yes at 0.075 so the asks_for_person unsure floor and old verdict rows are unchanged)"
    - "TriageReading reads each check's own bar; life_safety_min_yes and asks_for_person_min_yes unchanged"
    - "Tests first: update the fixture; boundary rows promise/complaint 0.15 sends, 0.1501 holds, promise 0.0751 now sends, price/discount 0.0751 still hold"
    - "Record D-56 amending D-45 in 02-CONTEXT.md (cite OQ-1) with a pointer on the D-45 line"
    - "Commit the triage.md reword with the fix"
    - "Re-run UAT Test 2 and spot-check more intake, scheduling and FAQ texts via gate:export (promise 0.09 vs 0.15 is a thin margin)"
  debug_session: ".planning/debug/intake-replies-always-held.md"

- gap_id: G-02-2.2
  truth: "The front-desk agent knows the current date and time, so relative dates in the thread (\"tomorrow at 9\") are read correctly"
  status: resolved
  resolved_by: 02-41-PLAN.md
  resolved_at: 2026-10-13
  reason: "User reported: Dana's draft said her visit is \"tomorrow at 9\" because she wrote \"tomorrow at 9\" on Oct 11; that visit was this morning. FrontDeskPrompt gives the model no current date or time, and thread messages carry no timestamps. Proposed fix: put the current date, weekday and time in the business's time zone in the prompt, and a timestamp on each thread message."
  severity: major
  test: 2
  root_cause: "FrontDeskPrompt::instructions() (app/Ai/FrontDeskPrompt.php:69-92) never reads the clock, and history()/textOf() (lines 106-146, 192-203) pass each turn as a bare body, so the model echoed Dana's Oct 11 'tomorrow at 9' on Oct 12. The code-side holding reply (BusinessClock) was correct; only the model lacks the clock. The customer card has business-zone timestamps but is a weak indirect source. App timezone is UTC, so the fix must convert to ActiveConfig::displayTimezone()."
  artifacts:
    - path: "app/Ai/FrontDeskPrompt.php"
      issue: "no current date/time section; history turns carry no timestamps; RULES has no guidance on reading relative dates"
    - path: "tests/Feature/FrontDesk/FrontDeskPromptTest.php"
      issue: "exact-string history assertions (lines 91-153) will change when prefixes are added"
    - path: "tests/Feature/FrontDesk/DraftReplacementTest.php"
      issue: "withdrawn-draft history test (~lines 121-135) asserts exact strings"
  missing:
    - "A final 'Right now' section in instructions(): weekday, date and time to the minute in ActiveConfig::displayTimezone(), placed last so the rest stays a stable prefix"
    - "Per-turn time prefix in history() using the G-02-1.2 when-this-happened accessor (sent_at falling back to created_at); placeholders unprefixed; promptText() unchanged (it feeds Jev and AsksForPerson)"
    - "RULES line: earlier texts start with the time they were sent; relative days in an earlier text mean that text's day; never write a bracketed time in a reply"
    - "Tests: freeze an evening where Chicago and UTC dates differ; an approved draft sent later than written; Right now is last; the RULES clause"
    - "Sequence after or together with G-02-1.2"
  debug_session: ".planning/debug/agent-lacks-current-date.md"

- gap_id: G-02-2.3
  truth: "/dev/chat shows held drafts as held, not as if they were sent"
  status: resolved
  resolved_by: 02-41-PLAN.md
  resolved_at: 2026-10-13
  reason: "User reported: /dev/chat shows held drafts as if they were sent"
  severity: minor
  test: 2
  root_cause: "DevChat::thread() (app/Livewire/DevChat.php:90) lists $conversation->messages()->get() with no status filter, and dev-chat.blade.php:6-13 never reads status, so a held draft (status draft, D-54) and withdrawn drafts render like sent replies. Missed copy: plan 01-07 built DevChat to mirror ConversationThread; plans 02-14/02-17/02-22 added draft/withdrawn rows and 02-22 filtered ConversationThread (line 122) but never DevChat. DevChatTest has no draft/withdrawn/queued/failed case."
  artifacts:
    - path: "app/Livewire/DevChat.php"
      issue: "unfiltered ->get() at line 90"
    - path: "tests/Feature/DevChatTest.php"
      issue: "no coverage for draft, withdrawn, queued or failed rows"
    - path: "app/Ai/FrontDeskPrompt.php"
      issue: "SHOWN_STATUSES (line 59) already holds the reached-the-customer list; reuse instead of a fifth copy"
  missing:
    - "Restrict DevChat::thread() to Received, Sent and Delivered, defined once (MessageStatus method or Message scope) and shared with FrontDeskPrompt::SHOWN_STATUSES; keep the filter separate from the relation's orderBy"
    - "Tests first in DevChatTest: live draft, withdrawn, queued and failed absent; received, sent, delivered and owner-sent present; an approved draft appears after ResolveDraft::approve (assert membership, not order)"
    - "Ship with or after G-02-1.2 (same lines; a sent draft would otherwise appear at its old draft time)"
  debug_session: ".planning/debug/devchat-shows-held-drafts.md"

- gap_id: G-02-2.4
  truth: "Drafts make no claims the business files don't support"
  status: resolved
  resolved_by: 02-39-PLAN.md
  resolved_at: 2026-10-13
  reason: "User reported: the furnace draft said \"We do repair furnaces\", which no business file says"
  severity: minor
  test: 2
  root_cause: "Premise does not hold for draft 17: 'We do repair furnaces' is supported by business/profile.md:9 and business/faq.md:14, both loaded by agents/front-desk.md. The example folder contradicts itself: the prose says the business repairs furnaces but business/services/ has only ac-repair.md and furnace-tune-up.md, so 'my furnace won't turn on' matched no service and was held as other. A truly unsupported claim did occur in draft 14 ('still on our list for tomorrow at 9, pending the owner's confirmation'): nobody confirmed 9 and Phase 2 has no bookings. RULES lines 33-36 don't say the thread and customer card are what the customer said rather than what the business agreed, and line 34 forbids promising a visit but not restating one from the thread."
  artifacts:
    - path: "examples/example-hvac/business/services/"
      issue: "no furnace-repair service, contradicting profile.md:9 and faq.md:14 (Layer 1, owner-editable)"
    - path: "app/Ai/FrontDeskPrompt.php"
      issue: "RULES lines 33-36 don't separate facts (business files) from what the customer said (thread, card), and don't forbid restating a visit or time as booked (Layer 2)"
  missing:
    - "DECIDED by Ian 2026-10-12: add examples/example-hvac/business/services/furnace-repair.md (slug, intake questions, price policy) so the folder agrees with profile.md and faq.md; furnace-repair texts then get an intake question instead of a held other"
    - "Layer 2: RULES sentence(s): the thread and customer card show what the customer said, not what the business has agreed; never state that a visit, time or booking exists, since the owner confirms those; toContain test in FrontDeskPromptTest; same RULES edit as G-02-2.2"
  debug_session: ".planning/debug/draft-unsupported-claim.md"

## Deferred Follow-Ups

- test: 1
  idea: "Show the customer's last message in the Needs you card, so the owner sees what they're answering without scrolling (suggested for Phase 2.1)"
  deferred_at: 2026-10-12
