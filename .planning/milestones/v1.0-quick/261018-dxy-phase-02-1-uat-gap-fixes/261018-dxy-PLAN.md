---
phase: quick-261018-dxy
plan: 01
type: execute
wave: 1
depends_on: []
gap_ids: [G-02.1-1, G-02.1-2, G-02.1-3]
files_modified:
  - app/Actions/SendFixedReply.php
  - app/Actions/RunFrontDesk.php
  - tests/Feature/FrontDesk/WaitingCustomerRepliesTest.php
  - tests/Feature/FrontDesk/FrontDeskFailureTest.php
  - tests/Feature/FrontDesk/AnsweredDraftTest.php
  - tests/Feature/FrontDesk/FrontDeskInvariantTest.php
  - app/Ai/FrontDeskPrompt.php
  - tests/Feature/FrontDesk/FrontDeskPromptTest.php
  - app/Actions/SendLifeSafetyLine.php
  - tests/Feature/FrontDesk/CustomerReadingTest.php
  - .planning/phases/02.1-faster-answers/02.1-UAT.md
  - .planning/phases/02.1-faster-answers/02.1-CONTEXT.md
  - .planning/WINDOWS.md
  - map/CLAUDE.md
  - map/processes/answer-inbound.md
  - map/objects/messages/message-and-verdict.md
  - map/objects/folder/agent-file-and-version.md
  - map/objects/conversations/conversation-and-pause.md
  - map/objects/contacts/contact-opt-out.md
  - map/objects/intake/intake-answer.md
autonomous: true
requirements: [ANSR-04, ANSR-01, GATE-01]

estimate:
  tokens: 120000
  raw_tokens: 120000
  tasks: 3
  confidence: low

must_haves:
  truths:
    - "G-02.1-1, waiting-draft path (D-34, D-46): the first text held as a complaint in a wait gets the owner's complaint reply. A later text in the same wait held as a complaint gets the check-in reply when it is a check-in, otherwise the added-info reply, and complaint_holding stays at one for that wait. This holds whether the agent's label or Jev's reading found the complaint"
    - "G-02.1-1, the two other paths from quick 261017-bz2: every model down during a current wait, and a held text with no live waiting draft after a close with no answered_by. A second complaint in the same wait gets the added-info reply, not a second complaint reply"
    - "G-02.1-1, alerts: the owner's complaint alert works as today on every path. app/Enums/AlertReason.php, app/Actions/RaiseOwnerAlert.php, app/Enums/MessageKind.php, app/SendRule/ReplyParts.php and app/Actions/SendCombinedReply.php are byte-identical to d074e3b, and no call that raises an alert in RunFrontDesk.php changes. A check-in reply chosen for a repeat complaint carries its own check-in alert, as every check-in reply does (D-28, D-38)"
    - "G-02.1-1, new wait: once the owner has answered (an owner text after the complaint reply), the customer's next complaint gets the complaint reply again"
    - "D-46 matrix: a check for never two complaint replies in one wait runs on every combination the matrix already runs. No new dimension: the matrix still runs 1,440 combinations in 8 slices of 180"
    - "G-02.1-2 (D-04, D-08): FrontDeskPrompt::RULES' price_services line tells the model three things. Name a service only when the customer asked about that exact service. A service the files do not offer, or one that does not clearly match a single service, gets an empty list. A price question that fits more than one service gets an empty list and the which-system question, never a defer-to-the-owner reply. The built instructions for every provider carry it. No test makes a live call"
    - "G-02.1-2 live check, run by the executor: texts 3 ('How much to install a mini split?') and 9a ('How much is the diagnostic?') go twice to each model in the agent file's chain, 8 real calls. Each result is recorded in .planning/WINDOWS.md and 02.1-UAT.md. If keys or a model are unavailable, the check is recorded as unrun with the reason, never faked"
    - "G-02.1-3: when Jev's reading sends the safety line and pauses the conversation (trigger classifier) before the agent runs, the safety-line message's gate holds gate.customer_reading (the reading's answers, life_safety included) beside gate.life_safety {hazard general, trigger classifier}, and no agent call is made. Every other call that sends the safety line with trigger classifier stores the run's reading the same way. Keyword lines are unchanged"
    - "Records: 02.1-UAT.md gaps G-02.1-1 and G-02.1-3 read status resolved with resolved_by 261018-dxy-PLAN.md. G-02.1-2 reads resolved, or failed if the live check ran and failed, and has a live_check line. .planning/WINDOWS.md entries 11 to 17 carry their UAT outcomes. The map cards are re-pointed and stamped 2026-10-18, and map/VERIFICATION.md is unchanged"
    - "php artisan test --compact passes and vendor/bin/pint --dirty --format agent has run"
  artifacts:
    - path: "app/Actions/SendFixedReply.php"
      provides: "complaintReplyAlreadySent(): whether the complaint reply already went out in the customer's current wait; holdingAlreadySent() unchanged in behavior"
      contains: "function complaintReplyAlreadySent"
    - path: "app/Actions/RunFrontDesk.php"
      provides: "answerWhileDraftWaits() and replyWhileWaitIsCurrent() send the complaint line only when no complaint reply went out in this wait; the four classifier safety-line calls pass the run's reading"
      contains: "complaintReplyAlreadySent"
    - path: "app/Ai/FrontDeskPrompt.php"
      provides: "the G-02.1-2 rule inside the price_services line of RULES"
      contains: "Name a service only when the customer asked about that exact service."
    - path: "app/Actions/SendLifeSafetyLine.php"
      provides: "optional CustomerReading parameter stored as gate.customer_reading on the safety line"
      contains: "customer_reading"
    - path: "tests/Feature/FrontDesk/WaitingCustomerRepliesTest.php"
      provides: "changed D-34 test (second complaint that checks in gets the check-in reply), next-line dataset test, new-wait test"
      contains: "G-02.1-1"
    - path: "tests/Feature/FrontDesk/FrontDeskInvariantTest.php"
      provides: "never two complaint replies in one wait, checked on every existing combination"
      contains: "complaintRepliesBefore"
    - path: "tests/Feature/FrontDesk/CustomerReadingTest.php"
      provides: "safety line sent by Jev's reading keeps the reading, no agent call"
      contains: "G-02.1-3"
    - path: ".planning/phases/02.1-faster-answers/02.1-UAT.md"
      provides: "three gaps resolved by this task, the live recheck recorded"
      contains: "resolved_by: 261018-dxy-PLAN.md"
  key_links:
    - from: "RunFrontDesk::answerWhileDraftWaits() and RunFrontDesk::replyWhileWaitIsCurrent()"
      to: "SendFixedReply::complaintReplyAlreadySent()"
      via: "the complaint line is due only when no complaint reply went out in the current wait"
      pattern: "complaintReplyAlreadySent\\("
    - from: "FrontDeskPrompt::RULES"
      to: "RunFrontDeskAgent::handle() (one instructions build, prompted to each provider in the chain)"
      via: "FrontDeskPrompt::instructions()"
      pattern: "Name a service only when"
    - from: "RunFrontDesk::handle() reading pause (~:174-176)"
      to: "SendLifeSafetyLine::handle()"
      via: "fourth argument, the run's CustomerReading, stored as gate.customer_reading"
      pattern: "'classifier', \\$this->reading\\(\\)"
---

<objective>
Ian's request (2026-10-17 and 2026-10-18), tests first. Close the three Phase 02.1 UAT gaps recorded in .planning/phases/02.1-faster-answers/02.1-UAT.md. Each gap's truth and fix are settled there and in 02.1-CONTEXT.md (D-34, D-46, D-04, D-08). Do not reopen them.

1. G-02.1-1 (major): the complaint reply goes out at most once per wait, on every path (the waiting-draft path, every model down, and a held text with no live waiting draft). A later complaint in the same wait gets the next line in D-34's order: the check-in reply if it is a check-in, otherwise the added-info reply. Owner alerts are unchanged. After the owner answers, the next complaint gets the complaint reply again.
2. G-02.1-2 (major): one prompt rule for both models, inside the price_services line of FrontDeskPrompt::RULES. Then the executor runs the 8-call live check and records it.
3. G-02.1-3 (minor): a safety line sent by Jev's reading (trigger classifier) stores that reading on the safety-line message. Every other classifier-trigger site does the same.
4. Records: the UAT gaps marked resolved, WINDOWS.md entries 11 to 17 updated with their UAT outcomes, the map cards re-checked against the files this task touches (only what became untrue changes), full suite and pint.

Purpose: an upset customer is acknowledged once per wait and never sent the same apology twice. Both models stop guessing a service for a price. Every 911 line has a record of why it went out.
Output: two code tasks, each with a failing-test commit and then a fix commit, and one records commit. All commits are local on gsd/phase-02.1-faster-answers. Never push, never merge, never rewrite history.
</objective>

<execution_context>
@/Users/iantitus/Projects/ops-platform/.claude/gsd-core/workflows/execute-plan.md
@/Users/iantitus/Projects/ops-platform/.claude/gsd-core/templates/summary.md
</execution_context>

<context>
@CLAUDE.md
@.ai/rules/app.md
@.ai/rules/tests.md
@.planning/STATE.md
@.planning/phases/02.1-faster-answers/02.1-UAT.md
@.planning/quick/261017-bz2-phase-02-1-fixed-reply-order-on-the-wait/261017-bz2-SUMMARY.md
@map/CLAUDE.md

Read 02.1-CONTEXT.md only at lines 60-70 (D-29, D-30, D-34) and 112-119 (D-46). Do not load the whole file.

Per CLAUDE.md (Boost), activate the project skills `testing-best-practices` and `laravel-best-practices` before writing tests and code. Read .ai/rules/app.md and .ai/rules/tests.md (both already in context; nothing in them touches this work beyond integer cents).

Live observation at planning time: HEAD d074e3b on gsd/phase-02.1-faster-answers; the last commit that touched app/ is a598477. Line numbers are approximate and move as you edit.

G-02.1-1 (where the complaint reply goes out during a wait):
- Every complaint reply is stored as kind complaint_holding, alone or leading a text (ReplyParts::kind() ~:42-51, a complaint line wins). MessageKind::startsWait() includes ComplaintHolding (MessageKind.php:39-42), so a complaint reply starts or continues a wait.
- SendFixedReply::holdingAlreadySent() (~:137-170) finds the newest message of a startsWait kind with status queued, sent or delivered. The wait is over if an owner text went out after it, or a draft was approved, edited or discarded since it (~:149-163), or answeredAfter() (~:179-186) finds a draft closed as answered by a newer text that carries meta.answered_by.
- Path A, waiting draft: RunFrontDesk::answerWhileDraftWaits() ~:515-594. After storeDraft(sendHolding: false) at ~:579, the fixed line is chosen at ~:585-593. The first branch fires on a complaint reason code (SendFixedReply::COMPLAINT_CODES) or on sentences or an easy answer without a check-in, and calls sendPartialHolding($conversation, $parts, $sentMeta, $verdict->reasonCode, $easy). sendPartialHolding() (~:906-931) builds the complaint line when the reason code is a complaint, else the partial holding line. On a refused send it falls back to SendFixedReply::holding(), which returns null whenever a wait is current. Then come the check-in branch (sendCheckInReply ~:798-817, which raises the CheckIn alert after a successful send), the added-info branch (holdingAlreadySent true, sendAddedInfoReply ~:855-865), and the holding() branch.
- Path B, no live waiting draft: storeDraft() ~:1454-1464 calls replyWhileWaitIsCurrent($conversation, meta, complaint) when holdingAlreadySent() is true.
- Path C, every model down or no agent: replyWhenFrontDeskCannotAnswer() ~:1260-1287 raises the Complaint alert at ~:1269 and then calls replyWhileWaitIsCurrent(..., true) when the wait is current.
- replyWhileWaitIsCurrent() ~:876-891 sends added-info when not a complaint, else the complaint line through SendCombinedReply.
- On paths B and C no check-in is ever detected. $checkIn needs a live waiting draft (~:266), and on path C no agent answered. So the next line there is always added-info, which is what Ian's "check-in if it's a check-in, otherwise added-info" gives.
- Alerts that must not change: storeDraft()'s alertReasonFor()/ownerNeedsAlertFor() (~:1450-1452, ~:1528-1556), sendCheckInReply()'s CheckIn alert, and replyWhenFrontDeskCannotAnswer()'s Complaint alert.
- Docblocks that describe the old choice and must be updated: the class docblock ~:105-110, answerWhileDraftWaits ~:495-497, replyWhileWaitIsCurrent ~:867-872, replyWhenFrontDeskCannotAnswer ~:1248-1256, storeDraft ~:1377-1380.
- The one test that pins two complaint replies in one wait: WaitingCustomerRepliesTest ~:208-234 (second text 'Hello? Did you see my message?' labelled complaint with waiting_note check_in; it expects CheckInReply 0, check-in alerts [] and ComplaintHolding 2). Its helpers are waitingRepliesDraftHeld(), waitingRepliesCustomerTexts(), waitingRepliesTextsToDana(), waitingRepliesLater(), waitingRepliesLiveDraft() and waitingRepliesCheckInAlerts(), plus the consts WAITING_REPLIES_COMPLAINT_LINE, WAITING_REPLIES_CHECK_IN_LINE and WAITING_REPLIES_ADDED_INFO_LINE. The owner-ends-the-wait pattern is at ~:265-275: Message::factory()->for(Conversation::query()->sole())->fromOwner()->create([...]).
- Path C tests: FrontDeskFailureTest ~:373-389 (frontDeskFailureDanaWaitsThenTextsAgain(), which leaves FrontDeskAgent faked down) and ~:408-427 (item 6 complaint test, FRONT_DESK_FAILURE_COMPLAINT, fakeClassifier()->answerReading('complaint', 0.95, ['complaint' => 0.9]), frontDeskFailureAlert()).
- Path B tests: AnsweredDraftTest ~:678-713 (item 7 dataset test). It closes the draft as answered with no answered_by by updating it to status withdrawn, resolution answered and resolved_at now, then sends a complaint with answeredDraftCustomerTexts(). After that first complaint, a new draft is live (storeDraft wrote it). To reach path B again for a second complaint, that new draft must also be closed the same way first.
- D-46 matrix, FrontDeskInvariantTest. invariantRun() ~:281-335 returns waitCurrent and waitingDraftId computed before the marker. invariantCoverBothRun() ~:651-675 builds its own run array. invariantBreaches() ~:441-584 holds the existing checks. invariantCarriesComplaintReply() ~:397-400 tells whether a text carries the complaint reply. In the matrix, every waiting combination's setup (invariantWaitingDraft ~:255-268) sends only a holding reply and the owner never answers, so before the run no complaint reply exists. The docblocks at ~:499-503 and ~:544-546 and ~:557-560 state the complaint choice without the once-per-wait limit; the last one also says the choice holds regardless of whether an earlier wait is current.

G-02.1-2:
- FrontDeskPrompt::RULES is one nowdoc (~:29-64). The price_services bullet is the single line ~:58. RunFrontDeskAgent::handle() builds instructions once (~:53) and prompts each provider in AgentFile::providerChain() with them (~:60-82), so one rule in RULES reaches both models. The example agent file chain is anthropic claude-sonnet-5-5, then openai gpt-6.1-sol.
- The file is 327 lines. map/VERIFICATION.md cites FrontDeskPrompt.php:140 (function history), and several cards cite :41, :42, :58, :63 and later lines. Keeping the rule inside the existing price_services line moves no line, so those citations stay true.
- FrontDeskPromptTest ~:522-533 is the dataset pattern (RULES and instructions($agent, $contact) both contain the sentence; promptTestThread() gives $agent and $contact). ~:557-563 is the pattern for one bullet line with no exclamation point and no em dash.
- No live harness is in the repo. The UAT test 2 probe was a throwaway, deleted after it ran (02.1-UAT.md test 2 note).

G-02.1-3:
- SendLifeSafetyLine::handle(Message $inbound, string $hazard, string $trigger) ~:58 calls answer() ~:79, which stores gate version, fixed, life_safety {hazard, trigger} and reason (~:95-103). OutboundSender never asks the safety line for a reading (SendOrigin::LifeSafety). GateExport reads only AgentReply rows, so a new gate key on the safety line changes no export.
- Callers with trigger classifier in RunFrontDesk: ~:175 (the reading, before paused, cap, person and agent), ~:298 (the main reply's verdict), ~:344 (the easy part's verdict) and ~:747 (the whole-thread draft's verdict). The agent-trigger calls ~:221 and ~:713 and the keyword calls (answerDanger ~:1134, RespondToLifeSafety.php:62) are out of scope and stay as they are.
- CustomerReading::toArray() (~:161-172) gives ran, failed, missing, error, model, answers and reading. LifeSafetyTest ~:282 asserts gate.life_safety equals exactly ['hazard' => 'general', 'trigger' => 'classifier']. A sibling key keeps that passing. CustomerReadingTest ~:54-73 is the reading-sends-the-line pattern (customerReadingDanaTexts(), CUSTOMER_READING_DIZZY, fakeClassifier()->answerReading('emergency', 0.95, ['life_safety' => 0.9])), and ~:180-192 shows FrontDeskAgent::assertNeverPrompted(). LifeSafetyTest ~:270-290 is the words-check-sends-the-line pattern (site ~:298).

Records:
- 02.1-UAT.md ## Gaps: each gap block has two-space-indented keys (gap_id, truth, status: failed, reason, fix or decision, severity, test, artifacts, missing).
- .planning/WINDOWS.md is a ledger owned by gsd-tools. Change statuses only through `node .claude/gsd-core/bin/gsd-tools.cjs windows fixed <id>` and `node .claude/gsd-core/bin/gsd-tools.cjs windows waive <id> "<reason>"`. `fixed` takes no reason. Prose written below the closing four-backtick fence of the JSON block survives every CLI write, so the outcomes go there.
- Map cards are stamped with the 2026-10-17 stamps from quick 261017-bz2 in the frontmatter `verified:` line and the `**Checked:**` line. map/CLAUDE.md has the same stamp in its Fence line. The convention (plans 02.1-27 and 261017-bz2): cite a symbol beside every path:line, a `:N` shorthand continues the last named file, stamp at the last commit that touched app code, and verify by printing the code line each citation lands on.
- Statements on the cards that this task makes untrue or incomplete (read at planning time):
  - answer-inbound.md step 10. The D-51 sentence says the complaint reply goes out when the reading found a complaint, no matter whether an earlier wait is current.
  - answer-inbound.md step 16. During a current wait, the complaint reply goes out when the verdict's reason is a complaint.
  - answer-inbound.md step 18. The order begins "the complaint reply when the words were a complaint".
  - answer-inbound.md step 7. The reading is stored on every verdict and fixed reply, and the safety line is not mentioned.
  - message-and-verdict.md line 26 ("Which kinds start a wait"). A held text during a current wait gets the complaint reply when held as a complaint.
  - message-and-verdict.md line 31 ("The run's reading"). It lists where gate.customer_reading is stored, and the safety line is not in the list.
  - agent-file-and-version.md line 20. The no-agent path's one text includes "the complaint reply when the reading found a complaint", with no once-per-wait limit.
</context>

<tasks>

<task type="tracer" tdd="true">
  <name>Task 1: The complaint reply goes out at most once per wait, on all three paths (G-02.1-1)</name>
  <files>tests/Feature/FrontDesk/WaitingCustomerRepliesTest.php, tests/Feature/FrontDesk/FrontDeskFailureTest.php, tests/Feature/FrontDesk/AnsweredDraftTest.php, tests/Feature/FrontDesk/FrontDeskInvariantTest.php, app/Actions/SendFixedReply.php, app/Actions/RunFrontDesk.php</files>
  <read_first>
    - app/Actions/SendFixedReply.php (whole file, 187 lines)
    - app/Actions/RunFrontDesk.php: class docblock ~:93-112, handle() ~:253-330, answerWhileDraftWaits ~:486-594, sendCheckInReply ~:788-817, sendAddedInfoReply and replyWhileWaitIsCurrent ~:849-891, sendPartialHolding ~:893-931, replyWhenFrontDeskCannotAnswer ~:1247-1287, storeDraft ~:1370-1465
    - tests/Feature/FrontDesk/WaitingCustomerRepliesTest.php ~:1-130 (helpers, consts) and ~:208-275
    - tests/Feature/FrontDesk/FrontDeskFailureTest.php ~:290-330 (helpers) and ~:373-427
    - tests/Feature/FrontDesk/AnsweredDraftTest.php ~:160-220 (helpers), ~:600-713
    - tests/Feature/FrontDesk/FrontDeskInvariantTest.php ~:270-460 and ~:485-600 and ~:641-675
    - .planning/phases/02.1-faster-answers/02.1-CONTEXT.md lines 68-70 and 112-119 only
  </read_first>
  <behavior>
    - Waiting draft, the changed D-34 test: a first complaint (label complaint) gets the complaint reply. The second text, 'Hello? Did you see my message?' (label complaint, waiting_note check_in), now gets the check-in reply: CheckInReply 1, ComplaintHolding 1, one CheckIn alert, a Complaint alert on file
    - Waiting draft, the next line after a first complaint: a check-in Jev reads as a complaint gets the check-in reply; an added detail the agent labels a complaint gets the added-info reply; an added detail Jev reads as a complaint gets the added-info reply. ComplaintHolding stays 1 and HoldingReply stays 1 in every row
    - New wait: complaint reply, then an owner text, then another complaint gives a second complaint reply (ComplaintHolding 2, AddedInfoReply 0)
    - Every model down: a second complaint in the same wait gets the added-info reply, ComplaintHolding 1, with a Complaint alert and a front_desk_failed alert for each complaint text, as today
    - No answered_by: a second complaint in the same wait, after the newer draft is also closed with no answered_by, gets the added-info reply, ComplaintHolding 1, and a Complaint alert, for both the Jev reading and the agent label
    - D-46 matrix: no combination sends two complaint replies in one wait, and the combination count stays 1,440
  </behavior>
  <action>
Tests first (RED), then the fix (GREEN). Commit the failing tests on their own, then the fix. This implements G-02.1-1 per D-34's once-per-wait bullet and D-46's new invariant (02.1-CONTEXT.md:70 and :119). Do not reopen them.

RED, the tests:

1. WaitingCustomerRepliesTest ~:208-234. Modify this test; never delete it. Rename it to: "answers the first complaint in a wait with the complaint reply, and a later complaint that checks in with the check-in reply (D-34, G-02.1-1)". Keep everything up to and including the first-text expectations. For the second text (same script and body as now), keep the expectations that the live draft is a new id, its gate_reason is label:complaint and its body is the complaint draft's body. Replace the three expectations that pinned the old order (check-in alerts empty, CheckInReply 0, ComplaintHolding 2) with these:
   - waitingRepliesTextsToDana() has 3 texts, and the third is waitingRepliesLater(WAITING_REPLIES_CHECK_IN_LINE);
   - CheckInReply count 1 and ComplaintHolding count 1;
   - waitingRepliesCheckInAlerts() has 1 alert. A check-in reply carries its D-28/D-38 alert like every check-in reply. Record this in the SUMMARY as a judgment call for Ian;
   - an OwnerAlert with reason Complaint exists.

2. In the same file, right after it, add a dataset test: "gives a later complaint in the same wait the next line in D-34's order, never a second complaint reply (G-02.1-1)". Setup: waitingRepliesDraftHeld(), then the first complaint exactly as in step 1 (label complaint, touches_waiting true, 'The technician left a mess'), and expect ComplaintHolding 1. Then, when the row says Jev reads a complaint, call fakeClassifier()->answerReading('complaint', 0.95, ['complaint' => 0.9]). Then send the row's script and body with waitingRepliesCustomerTexts(). Rows (script, body, expected line const, expected kind, expected live-draft gate_reason):
   - 'a check-in Jev reads as a complaint': Jev reads a complaint; script reply 'Mike still has your question.', label other, touches_waiting true, waiting_note check_in; body 'Hello? Is anyone going to answer me?'; expect WAITING_REPLIES_CHECK_IN_LINE, kind CheckInReply, reason classifier:complaint.
   - 'an added detail the agent labels a complaint': no Jev complaint; script reply 'We are sorry, we will pass that on.', label complaint, touches_waiting true; body 'He also scratched my floor.'; expect WAITING_REPLIES_ADDED_INFO_LINE, kind AddedInfoReply, reason label:complaint.
   - 'an added detail Jev reads as a complaint': Jev reads a complaint; script reply 'Thanks, I will pass that on.', label intake_question, touches_waiting true; body 'And he tracked mud on the carpet.'; expect WAITING_REPLIES_ADDED_INFO_LINE, kind AddedInfoReply, reason classifier:complaint.
   In every row expect: 3 texts to Dana and the third is waitingRepliesLater(the expected line); the expected kind's count is 1 and the other of CheckInReply and AddedInfoReply is 0; ComplaintHolding 1; HoldingReply 1; the live draft's gate_reason is the expected reason; a Complaint OwnerAlert exists. If a row's actual gate_reason differs from the reason listed above, check whether the code chose it for a reason unrelated to this fix. If so, record the actual reason and say so in the SUMMARY. Only the reply lines are under test here.

3. In the same file add: "gives the complaint reply again once the owner has answered and a new wait starts (G-02.1-1)". Setup: waitingRepliesDraftHeld(), then the first complaint as above. Then the owner ends the wait with Message::factory()->for(Conversation::query()->sole())->fromOwner()->create(['body' => 'Sorry about that, I will call you this afternoon.']), the pattern of the test at ~:265. Then a second complaint (label complaint, touches_waiting true, body 'The AC is still blowing warm air and nobody came back.'). Expect: 3 texts to Dana, the third is waitingRepliesLater(WAITING_REPLIES_COMPLAINT_LINE), ComplaintHolding 2, AddedInfoReply 0.

4. FrontDeskFailureTest: add after the item 6 complaint test (~:408): "sends the added-info reply, not a second complaint reply, when a second complaint finds every model down in the same wait (G-02.1-1)". Setup as the item 6 complaint test: frontDeskFailureDanaWaitsThenTextsAgain(FRONT_DESK_FAILURE_COMPLAINT), the complaint reading, runQueuedFrontDeskJobs(). Then travel one minute, post a second complaint through route('webhooks.text.fake') with fakeTextPayload, MessageSid 'SM' followed by 32 'f' characters and body 'Still nothing, and that tech was rude.', and runQueuedFrontDeskJobs() again (FrontDeskAgent stays faked down). Expect: 3 texts to Dana; ComplaintHolding 1; AddedInfoReply 1; the newest outbound kind is AddedInfoReply; HoldingReply 1; 2 Complaint alerts and 2 FrontDeskFailed alerts (one of each per complaint text); SendFixedReply::holdingAlreadySent() still true. In the RED run, confirm that only the reply assertions fail and the alert counts already hold. That proves alert routing is untouched.

5. AnsweredDraftTest: add after the item 7 dataset test (~:678-713) a dataset test with the same two rows and arguments: "answers a second complaint in the same wait with the added-info line, not a second complaint reply, after drafts closed with no answered_by (G-02.1-1)". Setup as item 7 through the first complaint text, and expect its complaint reply (ComplaintHolding 1). Then close the newly live draft the same way the test closed the first one (status withdrawn, resolution answered, resolved_at now, no answered_by), so the next text takes the no-waiting-draft path. Then answeredDraftCustomerTexts('And nobody has called me back about it.', [reply 'We are sorry to hear that.', label the row's label]). Leave the row's Jev complaint reading in force. Expect: 3 texts to Dana, and the newest is answeredDraftLater of FixedReplies::addedInfoReply(now()); newest kind AddedInfoReply; ComplaintHolding 1; AddedInfoReply 1; HoldingReply 1; the live draft's gate_reason is the row's expected reason; a Complaint OwnerAlert on the live draft exists.

6. FrontDeskInvariantTest. The check runs on the combinations the matrix already runs. This is NOT a new dimension (Ian: the PostgreSQL run must not double):
   - invariantRun(): before the marker is taken, compute complaintRepliesBefore. It is the number of the customer's outbound messages, status queued, sent or delivered, that invariantCarriesComplaintReply() accepts; 0 when there is no conversation yet. Return it in the run array and add it to the @return shape. Add 'complaintRepliesBefore' => 0 to invariantCoverBothRun()'s run array and its @return shape (its setup sends only a holding reply).
   - invariantBreaches(): after the never-two-holding-replies block, add the D-46 check with a docblock citing D-46 and D-34 (Ian, 2026-10-17, G-02.1-1). Count this run's sent texts that carry the complaint reply. It is a breach, worded "{label}: two complaint replies went out in one wait", when that count is above 1, or when it is 1 and complaintRepliesBefore is above 0 and invariantWaitEndedByAnswer($run) is false. In the docblock, say that the matrix setup never lets the owner answer, so every earlier complaint reply belongs to the current wait.
   - Guard the two existing complaint edges (the ANSR-04 adjacency edge ~:547 and the front-desk-could-not-answer edge ~:561) with complaintRepliesBefore being 0. Reword their docblocks, and the docblock at ~:499-503, to say "the first complaint of a wait". Drop the phrase that says the choice holds regardless of whether an earlier wait is current.
   - Leave the combination count assertion (INVARIANT_SLICE_SIZE * INVARIANT_SLICES, 1,440) and invariantAllCombinations() untouched.

Run the narrowest set. Steps 1 to 5 must fail on the reply assertions before the fix. Step 6 passes before and after. Commit locally: test(quick-261018-dxy): the complaint reply goes out at most once per wait (G-02.1-1).

GREEN, the fix:

7. SendFixedReply: move the "nothing has answered since this message" logic of holdingAlreadySent() into one private method, for example nothingAnsweredSince(Conversation $conversation, Message $since): bool. That logic is the owner-text and approved/edited/discarded query plus answeredAfter(). holdingAlreadySent() keeps exactly its current behavior: the newest startsWait message, false when there is none, else that check. Add public complaintReplyAlreadySent(Conversation $conversation): bool. It finds the newest message of kind ComplaintHolding with status queued, sent or delivered, using reorder('id', 'desc') like holdingAlreadySent(). It returns false when there is none, else nothingAnsweredSince() on it. Write a PHPDoc block that says why. The complaint reply is kind complaint_holding whether it goes alone or leads a text (ReplyParts::kind()), and it starts a wait. So when nothing has answered since it, it belongs to the customer's current wait, and the customer has already been told once (D-34, G-02.1-1). A refused or failed complaint send does not count, so the next complaint tries again. Once the owner answers, it no longer counts, and the next complaint gets the reply again.

8. RunFrontDesk::replyWhileWaitIsCurrent(): send the complaint line only when $complaint is true and SendFixedReply::complaintReplyAlreadySent() is false. Otherwise send the added-info line. This one change covers paths B and C. Do not touch the Complaint alert in replyWhenFrontDeskCannotAnswer().

9. RunFrontDesk::answerWhileDraftWaits() fixed-line choice (~:585-593). Work out once whether the complaint line is due: the verdict's reason code is in SendFixedReply::COMPLAINT_CODES and complaintReplyAlreadySent() is false. Use that in place of the bare complaint test in the first branch. Pass the verdict's reason code to sendPartialHolding() only when the complaint line is due, and null otherwise. A repeat complaint then falls through to D-34's next line: the check-in reply on a check-in, otherwise the added-info reply. In the rare case where a passing easy answer rides, the partial holding line takes added-info's place, as D-36 already orders it for any text. Write that case down in the SUMMARY. The last branch (holding()) keeps $verdict->reasonCode.

10. Docblocks: update the class docblock (~:105-110), answerWhileDraftWaits (~:495-497), replyWhileWaitIsCurrent (~:867-872), replyWhenFrontDeskCannotAnswer (~:1248-1256) and storeDraft (~:1377-1380). Each should say the complaint reply goes out at most once per wait, a later complaint in the wait gets the next line, and alerts are unchanged (D-34; Ian, 2026-10-17, G-02.1-1). Use PHPDoc, not inline comments.

11. Do not change any alert: alertReasonFor(), ownerNeedsAlertFor(), sendCheckInReply()'s CheckIn alert, every RaiseOwnerAlert call, AlertReason, RaiseOwnerAlert. Do not change MessageKind, ReplyParts or SendCombinedReply. No new MessageKind and no migration.

12. If a test outside the four named files fails, read it. If it pins a second complaint reply in one wait, update it to D-34's once-per-wait rule (modify, never delete) and list it in the SUMMARY. Any other failure is a regression: fix the code, not the test.

13. Run vendor/bin/pint --dirty --format agent. Commit locally: fix(quick-261018-dxy): the complaint reply goes out at most once per wait (G-02.1-1). Stage only this task's files; never stage .planning/milestone.lock.

Walk-through log (CLAUDE.md): no new Laravel concept is expected here. A private helper, reorder() and Pest datasets are already in docs/sprints/sprint-1.1.md's log. Add a line only if one does appear.
  </action>
  <verify>
    <automated>php artisan test --compact tests/Feature/FrontDesk/WaitingCustomerRepliesTest.php tests/Feature/FrontDesk/FrontDeskFailureTest.php tests/Feature/FrontDesk/AnsweredDraftTest.php tests/Feature/FrontDesk/HoldingReplyTest.php tests/Feature/FrontDesk/CoverBothDraftTest.php tests/Feature/FrontDesk/HeldCodeAnswerTest.php && php artisan test --compact tests/Feature/FrontDesk/FrontDeskInvariantTest.php && git diff --quiet d074e3b -- app/Enums/AlertReason.php app/Enums/MessageKind.php app/Actions/RaiseOwnerAlert.php app/Actions/SendCombinedReply.php app/SendRule/ReplyParts.php && RFD_DIFF="$(git diff -U0 d074e3b -- app/Actions/RunFrontDesk.php)" && test -z "$(printf '%s\n' "$RFD_DIFF" | grep -E '^[-+][^-+]' | grep -F 'raiseOwnerAlert->')" && grep -q "function complaintReplyAlreadySent" app/Actions/SendFixedReply.php</automated>
  </verify>
  <done>Two commits exist: the failing tests, then the fix. On every path, a second complaint in one wait gets the check-in or added-info reply and never a second complaint_holding. A new wait gives the complaint reply again. Alerts and the five byte-identical files are untouched. The D-46 matrix passes with the new check and still runs 1,440 combinations.</done>
</task>

<task type="auto" tdd="true">
  <name>Task 2: One price_services rule for both models (G-02.1-2), and Jev's reading stored on the safety line it sends (G-02.1-3)</name>
  <files>tests/Feature/FrontDesk/FrontDeskPromptTest.php, tests/Feature/FrontDesk/CustomerReadingTest.php, app/Ai/FrontDeskPrompt.php, app/Actions/SendLifeSafetyLine.php, app/Actions/RunFrontDesk.php</files>
  <read_first>
    - app/Ai/FrontDeskPrompt.php ~:25-64 (RULES)
    - tests/Feature/FrontDesk/FrontDeskPromptTest.php ~:1-60 (helpers) and ~:515-563
    - app/Actions/SendLifeSafetyLine.php (whole file, 175 lines)
    - app/SendRule/CustomerReading.php ~:60-110 and ~:125-172 (fromStored, lifeSafety, toArray)
    - tests/Feature/FrontDesk/CustomerReadingTest.php ~:1-75 and ~:170-195
    - tests/Feature/FrontDesk/LifeSafetyTest.php ~:265-290
    - app/Actions/RunFrontDesk.php: find the four calls with trigger 'classifier' using grep -n "'classifier')" app/Actions/RunFrontDesk.php
  </read_first>
  <behavior>
    - The price_services line of FrontDeskPrompt::RULES and the built instructions both contain the three G-02.1-2 sentences; the line has no exclamation point and no em dash; no test makes a live call
    - A plain text Jev reads as life_safety gives one safety line whose gate.life_safety is exactly {hazard general, trigger classifier} and whose gate.customer_reading is the run's reading (ran, not failed, not missing, life_safety in its answers); no agent call, no draft, the conversation paused for life safety
    - A safety line the words check sends (trigger classifier, site ~:298) carries the run's reading in gate.customer_reading, the same reading the run's draft stores
  </behavior>
  <action>
Tests first (RED), then the fix (GREEN). Commit the failing tests on their own, then the fix.

RED:

1. FrontDeskPromptTest: add the test "tells both models to name a service only when asked about that exact service, and to ask which system when a price fits more than one (G-02.1-2, D-04, D-08)", as a dataset over these three sentences:
   - "Name a service only when the customer asked about that exact service."
   - "A service the business files do not offer, or one that does not clearly match a single service, gets an empty list."
   - "When a price question fits more than one service, such as a repair or a diagnostic that could be for the AC or the furnace, leave the list empty and ask which system it is for; never reply that the owner will follow up instead."
   For each sentence, expect that it is in FrontDeskPrompt::RULES, in app(FrontDeskPrompt::class)->instructions($agent, $contact) (promptTestThread()), and in the single RULES line that starts with '- price_services:'. Add a second test: that '- price_services:' line contains no '!' and no '—'. Follow the possible_danger line test at ~:557. No live call; nothing else changes in this file.

2. CustomerReadingTest: add "stores Jev's reading on the safety line when the reading alone pauses the conversation, and asks no agent (G-02.1-3)". Use customerReadingDanaTexts(CUSTOMER_READING_DIZZY), the plain text with no price ask, and fakeClassifier()->answerReading('emergency', 0.95, ['life_safety' => 0.9]). Run runQueuedFrontDeskJobs(). Expect:
   - the LifeSafety message is sole, and its gate.life_safety is exactly ['hazard' => 'general', 'trigger' => 'classifier'];
   - its gate.customer_reading is set, with ran true, failed false and missing false, and its answers include life_safety. Read FakeClassifier and CustomerReading for the stored shape, and assert the exact stored answers rather than a guess;
   - CustomerReading::fromStored() on that array gives lifeSafety() true;
   - no Draft message exists, the conversation's paused_reason is LifeSafety, and FrontDeskAgent::assertNeverPrompted() holds.

3. CustomerReadingTest: add "stores the run's reading on a safety line the words check sends (G-02.1-3)". Use the setup of LifeSafetyTest ~:270-290: a plain text with a clean reading, the agent scripted with an intake question, and the words check reading life_safety through fakeClassifier()->answer(...). Expect the LifeSafety message's gate.life_safety trigger to be classifier, and its gate.customer_reading to equal the gate.customer_reading of the draft that run stored.

Run the two files: the new tests must fail. Commit locally: test(quick-261018-dxy): one price_services rule for both models, and the reading on the safety line (G-02.1-2, G-02.1-3).

GREEN:

4. FrontDeskPrompt::RULES, per G-02.1-2 (D-04, D-08). In the price_services bullet (~:58), right after "the slugs of the services whose price the customer asks about, or an empty list.", insert the three sentences from step 1, word for word, in that order, then keep the rest of the bullet as it is. Keep it all on that one line, so no line of app/Ai/FrontDeskPrompt.php moves and the file stays 327 lines (map citations into it, including map/VERIFICATION.md's :140, stay true). This one rule reaches both models, because RunFrontDeskAgent builds the instructions once and prompts each provider with them. Add no per-provider prompt. Do not edit the general "When they do not say" rule; Ian's rule is specific to price asks.

5. SendLifeSafetyLine, per G-02.1-3. Add a fourth optional parameter to handle(): ?CustomerReading $reading = null (App\SendRule\CustomerReading). Document it with @param: the run's reading of the customer's text when Jev's reading or a words check found the danger; null on the keyword path, which sends before any Jev call (D-45). Pass it to answer(). In the gate, add 'customer_reading' => $reading->toArray() only when a reading is given. Keep the existing keys and life_safety exactly as they are. Update the class or method docblock: every safety line that Jev triggered keeps the reading that sent it (Ian, 2026-10-17, G-02.1-3). The lock-timeout path sends nothing and is unchanged.

6. RunFrontDesk: in each of the four calls whose trigger is 'classifier' (~:175, ~:298, ~:344, ~:747), pass $this->reading() as the fourth positional argument. Leave the two 'agent' calls and answerDanger()'s keyword call unchanged. RespondToLifeSafety.php is not touched.

7. Run the narrowest set, then the safety-line neighbours: LifeSafetyTest, PossibleDangerTest, JevDownTest. Run vendor/bin/pint --dirty --format agent. Commit locally: fix(quick-261018-dxy): one price_services rule for both models, and the reading on the safety line (G-02.1-2, G-02.1-3). Stage only this task's files.

The live check for G-02.1-2 runs in Task 3, after this commit, so it exercises the committed rule.
  </action>
  <verify>
    <automated>php artisan test --compact tests/Feature/FrontDesk/FrontDeskPromptTest.php tests/Feature/FrontDesk/CustomerReadingTest.php tests/Feature/FrontDesk/LifeSafetyTest.php tests/Feature/FrontDesk/PossibleDangerTest.php tests/Feature/FrontDesk/JevDownTest.php && test "$(wc -l < app/Ai/FrontDeskPrompt.php)" -eq 327 && sed -n 140p app/Ai/FrontDeskPrompt.php | grep -q "function history" && grep -qF "Name a service only when the customer asked about that exact service." app/Ai/FrontDeskPrompt.php && test "$(grep -cF "'classifier', \$this->reading())" app/Actions/RunFrontDesk.php)" -ge 4 && test "$(grep -cF "'general', 'classifier');" app/Actions/RunFrontDesk.php)" -eq 0 && grep -qF "customer_reading" app/Actions/SendLifeSafetyLine.php && git diff --quiet d074e3b -- app/Jobs/RespondToLifeSafety.php</automated>
  </verify>
  <done>Two commits exist: the failing tests, then the fix. The rule sits inside the price_services line and no line of FrontDeskPrompt.php moved. A safety line triggered by Jev's reading or a words check stores gate.customer_reading. Keyword and agent-triggered lines are unchanged.</done>
</task>

<task type="auto">
  <name>Task 3: Live check of the price rule (8 calls), then the records: UAT gaps, WINDOWS 11 to 17, the CONTEXT note, the map cards, full suite</name>
  <files>.planning/phases/02.1-faster-answers/02.1-UAT.md, .planning/phases/02.1-faster-answers/02.1-CONTEXT.md, .planning/WINDOWS.md, map/CLAUDE.md, map/processes/answer-inbound.md, map/objects/messages/message-and-verdict.md, map/objects/folder/agent-file-and-version.md, map/objects/conversations/conversation-and-pause.md, map/objects/contacts/contact-opt-out.md, map/objects/intake/intake-answer.md</files>
  <read_first>
    - app/Ai/RunFrontDeskAgent.php ~:46-90 (how a real call is made and parsed)
    - app/Ai/AgentFile.php ~:25-66 (forChannel, providerChain)
    - .planning/phases/02.1-faster-answers/02.1-UAT.md (whole file, 101 lines)
    - .planning/WINDOWS.md lines 1-35 (header and table only; the JSON block mirrors it)
    - map/processes/answer-inbound.md, map/objects/messages/message-and-verdict.md (the cards with the most citations into the changed files)
  </read_first>
  <action>
A. The live check (Ian asked the executor to run it rather than leave it open; the 2026-10-18 instruction in this task's description). No live call ever goes into the test suite. The probe is a throwaway in your session scratchpad directory, never inside the repo, never committed. Never read or print .env: let Laravel load it.
   1. Find the dev database file with php artisan config:show database.default and php artisan config:show database.connections.sqlite.database. Copy that file into the scratchpad. If the default connection is not sqlite, record the check as unrun ("dev database is not SQLite; no safe copy") and go to B.
   2. Write a PHP script in the scratchpad that boots the app from the repo root: require vendor/autoload.php and bootstrap/app.php, then bootstrap the console kernel. Before any query, the script points the sqlite connection at the copy: set config database.connections.sqlite.database to the copy's path, purge the connection, then assert that DB::connection()->getDatabaseName() is the copy and abort otherwise. Then call Event::fake(), Queue::fake() and Bus::fake(), so no listener, job or customer-card write runs. If ActiveConfig has no live version in the copy, run icm:sync against it once through Artisan::call inside the same script.
   3. For each of the two texts, "How much to install a mini split?" (text 3) and "How much is the diagnostic?" (text 9a), create one fresh fictional contact in the copy. Use Contact::query()->forceCreate with a 555-01xx number, status lead and lead_source_key text, as FrontDeskInvariantTest does. No wait is open and no history exists. Build the instructions with app(FrontDeskPrompt::class)->instructions($agent, $contact), where $agent = AgentFile::forChannel(app(ActiveConfig::class), 'sms'). The history is empty and the prompt text is the text itself. Then, for each provider and model in $agent->providerChain(), run twice: new FrontDeskAgent($instructions, []) prompted with the text, provider [provider => model] and timeout config('gate.agent.timeout_seconds'), exactly as RunFrontDeskAgent::handle() does. Parse each answer with AgentReply::fromStructured($response->toArray()). Print provider, model, run number, priceServices, label and the reply words for all 8 calls, and save the output in the scratchpad. Nothing goes through OutboundSender, so no text is sent.
   4. Judge each call against the gap's truth (02.1-UAT.md G-02.1-2). Text 3 passes when priceServices is []. Text 9a passes when priceServices is [] and the reply asks which system it is for (AC or furnace), and does not tell the customer the owner will follow up. Quote each 9a reply in the record.
   5. If a key is missing, a provider rejects the call or a model is unavailable, record the check as unrun with the exact reason (provider, error class, first line of the message, with any key redacted), never a guessed result. If it runs and any call fails the judgment, do not tune the prompt in this task. Record the results as they are and flag them for Ian in the SUMMARY.
   6. Afterwards, run git status --porcelain. Nothing may have changed under examples/, database/ or anywhere else from the probe. Delete the scratchpad copy and script, or leave them in the scratchpad only.

B. 02.1-UAT.md. Edit; never rewrite the file.
   - In each gap block, change the status line and add three keys after it: resolved_by: 261018-dxy-PLAN.md, resolved_at: 2026-10-18, and artifacts listing the files that fixed it (replacing the empty artifacts placeholder).
   - G-02.1-1 and G-02.1-3 read status: resolved.
   - G-02.1-2 reads status: resolved when the live check passed or was unrun, plus a line live_check: passed, or live_check: unrun, followed by the reason. When the check ran and failed, it keeps status: failed, has no resolved_by, and gets a live_check: failed line with the failing calls.
   - Append to test 2's note one short paragraph: "Live recheck after quick task 261018-dxy (2026-10-18): ..." Give the call count, the models, each text's price_services per run, the 9a replies and the verdict.
   - Append one sentence to test 3's note: the reading that paused text B is now stored on the safety line (G-02.1-3, 261018-dxy).
   - Update the frontmatter updated: timestamp. Leave the tests' result lines and the Summary counts as they are; they are the UAT's own record.

C. 02.1-CONTEXT.md, one sentence only. At the end of D-34's once-per-wait bullet (line 70), replace the closing sentence that says the change is not built yet and waits for a quick task after UAT with: "Built in quick task 261018-dxy (2026-10-18)." Change nothing else in the file.

D. .planning/WINDOWS.md, through the ledger CLI first, then prose:
   - node .claude/gsd-core/bin/gsd-tools.cjs windows fixed for 11, 14, 15, 16 and 17;
   - for 12, fixed only when the live check passed; otherwise leave it open;
   - node .claude/gsd-core/bin/gsd-tools.cjs windows waive 13 with this reason: "UAT test 1 (Ian, 2026-10-17): the phone walk was done and the look fails on a phone; recorded as a design issue for a separate phone design pass (.planning/todos/pending/2026-10-17-phone-design-pass.md), not a 02.1 gap".
   Then, below the closing fence of the JSON block, add a section "## Phase 02.1 UAT outcomes (entries 11 to 17)". Add one line per entry, starting "- 11:" through "- 17:", each naming the UAT test, the date, the result and where anything held now lives, from 02.1-UAT.md:
   - 11: test 1 behavior pass on conversations 23 and 24; "Already answered" skipped as a timing trick, covered by AnsweredDraftTest and DraftReviewTest; the look goes to the phone design pass.
   - 12: test 2 issue G-02.1-2; the 44-call probe; this task's 8-call live recheck result.
   - 13: as waived.
   - 14: test 3 pass by Ian's ruling; Jev read 0.05 on text A, under the 0.075 bar, and the reply model's possible_danger sent the line; Jev tuning for CO symptoms is held in docs/phase-2.2-owner-input.md section 7, due before the first client goes live; the pausing reading is now stored (G-02.1-3).
   - 15: test 3, possible_danger true on all 8 reply-model runs.
   - 16: test 4 pass (Ian, 2026-10-18); phone delivery not tested (Twilio trial, A2P), deferred to Twilio go-live.
   - 17: test 1 behavior pass; the look goes to the phone design pass.
   Run node .claude/gsd-core/bin/gsd-tools.cjs windows status --raw afterwards. It must parse and show the prose kept.

E. The map cards. Re-check them against the four app files this task changed (RunFrontDesk.php, SendFixedReply.php, SendLifeSafetyLine.php, and FrontDeskPrompt.php, whose lines did not move). Change only what became untrue. For RunFrontDesk.php, SendFixedReply.php and SendLifeSafetyLine.php, list every citation in the six cards and map/CLAUDE.md, resolving `:N` shorthand to the last named file. Re-point each from git diff d074e3b on that file, then print the line it now lands on to confirm it names the symbol or statement the card says; check both ends of a range. Fix the statements listed in this plan's context:
   - step 10: during a current wait, a complaint gets the complaint reply only when none went out in that wait yet (SendFixedReply::complaintReplyAlreadySent), otherwise the added-info reply, and the complaint alert stays. Remove the clause that says the choice holds regardless of whether an earlier wait is current;
   - steps 16 and 18: the complaint reply at most once per wait, then the check-in or added-info reply;
   - step 7 and message-and-verdict line 31: add that the reading is stored on the safety line when Jev's reading or a words check sends it (trigger classifier);
   - message-and-verdict line 26: the complaint reply at most once per wait;
   - agent-file-and-version line 20: the same limit, if its sentence now reads untrue.
   answer-inbound.md and message-and-verdict.md must each contain the phrases "at most once per wait", "complaintReplyAlreadySent" and "stored on the safety line". Let S be git log -1 --format=%h -- app/ (Task 2's fix commit). Stamp every card's verified: line and **Checked:** line, and map/CLAUDE.md's Fence line, as 2026-10-18 @ S, with "(source inspection after quick 261018-dxy; owner ruling 2026-10-12)" in the verified: line. Leave map/VERIFICATION.md unchanged.

F. Run php artisan test --compact (the whole suite) and vendor/bin/pint --dirty --format agent (it should find nothing; if it changes PHP, that belongs in a code commit, so stop and report it). Check docs/sprints/sprint-1.1.md's walk-through log: add a line only if Tasks 1 or 2 introduced a new Laravel concept.

G. Commit locally, one commit for this task: docs(quick-261018-dxy): live price-rule check, UAT gaps resolved, WINDOWS 11 to 17, map re-checked. Stage only this task's files; never .planning/milestone.lock. The orchestrator commits the SUMMARY, STATE.md and this PLAN. Never push.
  </action>
  <verify>
    <automated>bash -c 'U=.planning/phases/02.1-faster-answers/02.1-UAT.md; C=.planning/phases/02.1-faster-answers/02.1-CONTEXT.md; awk "/^- gap_id: /{g=\$3} g && /^  status: /{st[g]=\$2} g && /^  resolved_by: /{rb[g]=\$2} g && /^  live_check: /{lc[g]=1} END{ok=(st[\"G-02.1-1\"]==\"resolved\" && st[\"G-02.1-3\"]==\"resolved\" && rb[\"G-02.1-1\"] ~ /261018-dxy/ && rb[\"G-02.1-3\"] ~ /261018-dxy/ && lc[\"G-02.1-2\"]==1 && (st[\"G-02.1-2\"]==\"resolved\" || st[\"G-02.1-2\"]==\"failed\")); exit ok?0:1}" "$U" || { echo "UAT gaps"; exit 1; }; grep -qF "Live recheck after quick task 261018-dxy" "$U" || { echo "UAT note"; exit 1; }; grep -qF "Not yet built: it is fixed with the other UAT issues" "$C" && { echo "CONTEXT old"; exit 1; }; grep -qF "Built in quick task 261018-dxy (2026-10-18)." "$C" || { echo "CONTEXT new"; exit 1; }; node -e "const r=JSON.parse(require(\"child_process\").execSync(\"node .claude/gsd-core/bin/gsd-tools.cjs windows status --raw\").toString()); const s={}; for (const e of r.ledger.entries) s[e.id]=e.status; const ok=[11,14,15,16,17].every(i=>s[i]===\"fixed\") && s[13]===\"waived\" && [\"fixed\",\"open\"].includes(s[12]); process.exit(ok?0:1)" || { echo "WINDOWS statuses"; exit 1; }; grep -qF "## Phase 02.1 UAT outcomes (entries 11 to 17)" .planning/WINDOWS.md || { echo "WINDOWS prose"; exit 1; }; for i in 11 12 13 14 15 16 17; do grep -q "^- $i: " .planning/WINDOWS.md || { echo "WINDOWS line $i"; exit 1; }; done; S=$(git log -1 --format=%h -- app/); bad=0; for f in map/CLAUDE.md map/objects/*/*.md map/processes/*.md; do grep -q "2026-10-18 @ $S" "$f" || { echo "NO STAMP $f"; bad=1; }; grep -q "a598477" "$f" && { echo "OLD STAMP $f"; bad=1; }; done; for f in map/CLAUDE.md map/VERIFICATION.md map/objects/*/*.md map/processes/*.md; do for c in $(grep -oE "[A-Za-z0-9_/.-]+\.php:[0-9]+" "$f" | sort -u); do p=${c%%:*}; n=${c##*:}; q=$(git ls-files -- "$p" "*/$p") || exit 1; q=${q%%[[:space:]]*}; if [ -z "$q" ] || [ "$n" -gt "$(wc -l < "$q")" ]; then echo "BAD $f $c"; bad=1; fi; done; done; git diff --quiet d074e3b -- map/VERIFICATION.md || { echo "VERIFICATION changed"; bad=1; }; for t in "at most once per wait" "complaintReplyAlreadySent" "stored on the safety line"; do for f in map/processes/answer-inbound.md map/objects/messages/message-and-verdict.md; do grep -qF "$t" "$f" || { echo "MISSING $t in $f"; bad=1; }; done; done; grep -v "^#" map/processes/answer-inbound.md | grep -qF "whether or not an earlier wait is current" && { echo "OLD STEP 10"; bad=1; }; exit $bad' && php artisan test --compact</automated>
  </verify>
  <done>The live check ran and its 8 results are recorded in 02.1-UAT.md and WINDOWS.md, or it is recorded as unrun with the exact reason. G-02.1-1 and G-02.1-3 are resolved by 261018-dxy-PLAN.md, and G-02.1-2 is resolved or failed according to the live result. WINDOWS entries 11 to 17 carry their outcomes through the CLI plus a prose section. D-34's not-built note now names this task. Every map citation lands, the cards are stamped 2026-10-18 at Task 2's fix commit, and map/VERIFICATION.md is unchanged. The full suite passes, pint finds nothing, and one docs commit exists.</done>
</task>

</tasks>

<threat_model>
## Trust Boundaries

| Boundary | Description |
|----------|-------------|
| customer text → front desk (model, Jev) | Untrusted words choose price_services and can describe a danger or a complaint |
| front desk → customer phone | Automated texts: the complaint, check-in and added-info lines and the safety line |
| executor's live probe → model providers | Real API keys from .env leave the machine in one direction, as auth to the providers |
| live probe → repo and dev database | The probe must not write either; only its recorded results enter the repo |

## STRIDE Threat Register

| Threat ID | Category | Component | Severity | Disposition | Mitigation Plan |
|-----------|----------|-----------|----------|-------------|-----------------|
| T-261018-dxy-01 | Tampering | FrontDeskPrompt price_services rule | medium | mitigate | The rule only narrows naming. Code still sends only owner-written sentences that name their own service (D-08 sync check). A slug not in the folder, or [], holds as a price draft with the holding reply (D-04). The live check confirms both models follow the rule |
| T-261018-dxy-02 | Repudiation | SendLifeSafetyLine gate | medium | mitigate | Every safety line that Jev's reading or a words check triggers stores gate.customer_reading, so each 911 line records why it went out (G-02.1-3). CustomerReadingTest pins the reading-pause case and the words-check case |
| T-261018-dxy-03 | Denial of Service | Complaint reply during a wait | low | mitigate | The complaint reply goes at most once per wait through SendFixedReply::complaintReplyAlreadySent() on all three paths, and the D-46 matrix checks never two in one wait. The reply cap (D-52) is unchanged |
| T-261018-dxy-04 | Repudiation | Owner complaint alert | medium | mitigate | No alert code changes: AlertReason.php and RaiseOwnerAlert.php are byte-identical and RunFrontDesk.php's alert calls are untouched (Task 1 verify). Tests assert a Complaint alert on every path, and the RED run proves the alert counts held before the fix |
| T-261018-dxy-05 | Information Disclosure | Live probe | medium | mitigate | The script lives only in the session scratchpad and is never committed. It never reads or prints .env (Laravel loads it). Errors are recorded with keys redacted. Only fictional texts and model outputs enter the repo |
| T-261018-dxy-06 | Tampering | Live probe vs dev database and examples/records | medium | mitigate | The probe works on a scratchpad copy of the SQLite file, switched at runtime and asserted before any query. Event, Queue and Bus fakes stop listeners, jobs and customer-card writes. git status --porcelain must show nothing from the probe |
| T-261018-dxy-SC | Tampering | composer/npm installs | low | accept | No package is installed or upgraded by this plan. Dependency changes need Ian's approval (CLAUDE.md) |
</threat_model>

<verification>
- Task 1: the narrow front-desk tests and the D-46 matrix pass; the alert files, MessageKind, ReplyParts and SendCombinedReply are byte-identical to d074e3b; no line of RunFrontDesk.php that raises an alert changed.
- Task 2: the prompt, reading and safety-line tests pass; FrontDeskPrompt.php is still 327 lines with history() at :140; all four classifier safety-line calls pass the reading; RespondToLifeSafety.php is unchanged.
- Task 3: the UAT gaps and live_check lines, the CONTEXT sentence, the WINDOWS statuses and prose, the map stamps and citations, and an unchanged map/VERIFICATION.md all check out; php artisan test --compact passes.
- PostgreSQL run not done locally (CI covers it, DEC-06). The matrix size is unchanged, so its PostgreSQL time does not grow.
</verification>

<success_criteria>
- No customer gets two complaint replies in one wait, on any path. A later complaint gets the check-in or added-info reply, and the owner's complaint alerts are exactly as before.
- After the owner answers, the next complaint gets the complaint reply again.
- Both models get one price_services rule. The live recheck of texts 3 and 9a is recorded with real results, or recorded as unrun with the reason.
- Every safety line Jev triggered carries the reading that triggered it.
- The UAT gaps, WINDOWS entries 11 to 17, D-34's note and the map cards are true to the code. The full suite passes and pint is clean.
- Five or six local commits on gsd/phase-02.1-faster-answers: Task 1 test and fix, Task 2 test and fix, Task 3 docs. Nothing pushed.
</success_criteria>

<output>
Create `.planning/quick/261018-dxy-phase-02-1-uat-gap-fixes/261018-dxy-SUMMARY.md` when done. Include:
- the commits;
- the tests changed (modified, never deleted) and added;
- the live check's 8 results, or the unrun reason;
- what a customer or owner now sees differently;
- these judgment calls for Ian:
  - the check-in reply that answers a repeat complaint carries its own check-in alert, as every check-in reply does (D-28, D-38);
  - a repeat complaint with a passing easy answer gets the partial holding line in added-info's place, by D-36's existing order;
  - any reason code in Task 1 step 2 that differed from the plan and why.

Also flag for Ian: GSD's UAT predicate (uat-predicate.cjs isTestGapResolved) only credits a resolved_by that names a PLAN file inside the phase directory, with a matching SUMMARY and gap_ids. This plan lives in .planning/quick/, so /gsd-audit-uat may still list UAT tests 1 and 2 as blocking even though the gaps are resolved. No files were moved to work around it.
</output>
