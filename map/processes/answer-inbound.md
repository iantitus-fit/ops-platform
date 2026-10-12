---
type: process
status: live
consumes: [../objects/messages/message-and-verdict.md, ../objects/conversations/conversation-and-pause.md, ../objects/folder/agent-file-and-version.md, ../objects/contacts/contact-opt-out.md]
produces: [../objects/messages/message-and-verdict.md, ../objects/intake/intake-answer.md, ../objects/conversations/conversation-and-pause.md]
verified: 2026-10-12 @ be50284 (working tree, source inspection; owner ruling 2026-10-12)
---

# Answer an inbound text

**Type:** process · **Mark:** live · **Checked:** 2026-10-12 @ be50284 · **Where:** `app/Actions/RecordInbound.php:49` to `app/Actions/RunFrontDesk.php:79`

**One sentence.** A customer's text is recorded, claimed once with any texts sent just after it, answered by the agent, judged by the send rule, and then either sent as a reply or kept as a draft for the owner.

**Input, movement, output.** Input: one inbound text from a provider webhook. Movement: record, claim, ask the agent, decide, send or hold. Output: a `messages` row for the text, usually a second row for the answer (sent or a draft with its verdict), maybe intake answers, a pause, and owner alerts.

**Why this shape.** Each stage is guarded by a database claim, not a queue promise, so a retried webhook, a failed-open debounce and the sweep can never answer one text twice (D-42, `ClaimInboundBurst.php:12-20`). The agent has no tools: it only proposes words and a label, and code decides whether they go out (`FrontDeskAgent.php:15-19`). The danger text skips the wait on purpose (D-17).

## Steps
1. **Record.** The webhook calls `RecordInbound::handle` (`TwilioTextWebhookController.php:24`, `FakeTextWebhookController.php:21`). One transaction finds or makes the contact, finds or opens the conversation, stores the text as `received` and writes a timeline entry (`RecordInbound.php:127-184`). A text the provider already delivered returns the stored row (`:51-57`). STOP, START and HELP end here (`:76-81`; see [contact-opt-out](../objects/contacts/contact-opt-out.md)).
2. **Safety line.** A text whose words describe gas, fire or water danger queues `RespondToLifeSafety` at once (`RecordInbound.php:109-121`, `RespondToLifeSafety.php:34`). `SendLifeSafetyLine` sends the fixed line, pauses and alerts (`SendLifeSafetyLine.php:52,100`). `RunFrontDesk` takes a second look at step 6.
3. **Queue once.** `DispatchInbound::handle` flips `dispatched_at` with a conditional update and only then queues `ProcessInbound` (`DispatchInbound.php:28-37`). `SweepUndispatched` re-sends texts recorded but never queued, aged 2 minutes to 24 hours (`SweepUndispatched.php:25-38`, scheduled `routes/console.php:14`).
4. **Wait for the burst.** `ProcessInbound` waits about 20 seconds after the last text, 90 at most (`ProcessInbound.php:23`).
5. **Claim.** `ClaimInboundBurst::handle` sets `handled_at` on each unclaimed customer text, one atomic update per row (`ClaimInboundBurst.php:22-39`). Nothing claimed: stop. No SMS text in the burst: stop (`ProcessInbound.php:63`). No live folder version yet: the burst is claimed and left quietly (`:69-71`).
6. **Early exits in `RunFrontDesk::handle`** (`RunFrontDesk.php:79-106`), in order: second look at danger (`:81`); opted out, so alert the owner (`:83`); paused, so alert once per burst (`:89`); reply cap reached, so pause and alert (`:95`); plain words asking for a person, so hand off: alert, fixed handoff reply, pause (`:102`, `:252-257`).
7. **Ask the agent.** `AgentFile::forChannel('sms')` (`:108`) and `RunFrontDeskAgent` try each model in file order (`RunFrontDeskAgent.php:44`; see [agent-file-and-version](../objects/folder/agent-file-and-version.md)). All fail: note, alert, stop (`RunFrontDesk.php:114-121`). The agent wanting a person: hand off (`:123`).
8. **Keep what it learned.** Intake answers (`:129`, [intake-answer](../objects/intake/intake-answer.md)) and an empty contact name (`:130`) are stored before the send rule runs, even if the reply is then held.
9. **Decide.** `ComposeAutomatedText::forReply` adds disclosure and sign-off (`ComposeAutomatedText.php:26`); `DecideSendOrDraft::decide` returns the verdict (`RunFrontDesk.php:134`). A classifier-found danger sends the safety line (`:136`); Jev reading "asks for a person" hands off (`:139`).
10. **Send or draft.** Verdict `send`: `OutboundSender::send` (`:152`); a channel failure alerts the owner (`:162-166`), a carrier opt-out alerts the owner to call (`:168`), any other refusal becomes a `send_refused` draft (`:175`). Verdict `hold`: `storeDraft` withdraws any older draft, writes the new draft with its timeline entry in one transaction (`:315-359`), alerts the owner after commit (`:363`), and sends one holding reply unless paused (`:367`). See [message-and-verdict](../objects/messages/message-and-verdict.md).
11. **Alert.** `RaiseOwnerAlert::handle` stores an `owner_alerts` row per recipient, due now if urgent or the shop is open, else at the next opening (`RaiseOwnerAlert.php:38`); `SendDueAlerts` sends them (`routes/console.php:17`). Owner alerts have no card yet: it comes with Phase 2.1's map (Ian, 2026-10-12).

## If you change this
**Hits:** all four object cards above, plus [conversation-and-pause](../objects/conversations/conversation-and-pause.md) at steps 2, 6 and 10. Reordering steps 6 or 8 changes what is stored when a reply is held. A new inbound channel must go through `RecordInbound` and `DispatchInbound`, not around them.
**Does not hit:** web form leads: they enter through the same `RecordInbound` but stop at `ProcessInbound.php:63` and reach the owner through the dashboard and `open-leads.md` (D-41; Ian, 2026-10-12: they stay a "does not hit"). The owner's own reply (`SendOwnerReply`, a separate caller of `OutboundSender`). The SDK's `agent_conversations` memory (the thread is passed in by `FrontDeskPrompt.php:106`). Booking (Phase 3).

## Surfaces
Triggered by: Twilio calling `webhooks/text/twilio` and `/status` (`routes/web.php:22,26`), once the number is live; a client's site form posting to `leads` (`routes/web.php:30`); both intended consumers, not live, since the text channel is still the fake one and nothing posts to leads (Ian, 2026-10-12). Runs on: the queue worker and the scheduler. Humans: the owner, through alerts, the thread and the Drafts widget.

## See
`app/Actions/RunFrontDesk.php`, `app/Actions/RecordInbound.php`, `app/Jobs/ProcessInbound.php`; decisions D-17, D-42, D-52 in `.planning/phases/02-text-front-desk/02-CONTEXT.md`.
