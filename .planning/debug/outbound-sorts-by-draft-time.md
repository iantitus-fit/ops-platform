---
status: diagnosed
trigger: "Gap G-02-1.2: after Send, the sent reply is listed above the holding reply in /dev/chat because it keeps the draft's creation time; outbound messages should sort by when they were sent."
created: 2026-10-12T00:00:00Z
updated: 2026-10-12T00:00:00Z
goal: find_root_cause_only
audit_acknowledged:
  milestone: v1.0
  at: 2026-10-20
  status: diagnosed
---

## Current Focus

hypothesis: CONFIRMED. Every thread is ordered by messages.created_at then id. A held draft is the same row that later becomes the sent message (D-54), so it keeps the creation time of the moment the agent wrote it. The holding reply is always created after the draft, so it always has an equal or later created_at and a higher id. When the owner sends the draft minutes later, the sent reply therefore sorts above the holding reply. No column on messages records when a message was sent, so nothing can be sorted by send time today.
test: read-only query of database/database.sqlite for the UAT conversation (conversation 1, messages 13, 14, 15) using the same ORDER BY as Conversation::messages()
expecting: n/a, diagnosis complete
next_action: hand root cause to plan-phase --gaps

bug_class: Bohrbug (deterministic, no timing dependence). SBFL skipped: no failing test exists for this behaviour. Knowledge base: .planning/debug/knowledge-base.md does not exist; no prior match.

## Symptoms

expected: Outbound messages sort by when they were sent, so a held draft the owner later sends from the conversation screen appears after the holding reply in /dev/chat (and in the back-office thread).
actual: "after Send, the sent reply is listed above the holding reply in /dev/chat because it keeps the draft's creation time"
errors: none reported
reproduction: Test 1 in .planning/phases/02-text-front-desk/02-UAT.md: text from /dev/chat so a draft is held (a holding reply goes out), then press Send on the conversation screen.
started: found in Phase 2 UAT, 2026-10-12

## Eliminated

- hypothesis: the sent draft is stamped with a wrong or stale time at send, i.e. a bug in how a send is recorded
  evidence: nothing is stamped at all. OutboundSender::transmit() computes $sentAt = now() (app/Actions/OutboundSender.php:261) but uses it only for the ReplySent activity, conversation.last_message_at and contact.last_activity_at (lines 268-277). The message row gets status and provider_message_id only (lines 263-266). The in-place claim in deliverExisting() (lines 220-225) updates status, body, provider and the resolution columns, never created_at.
  timestamp: 2026-10-12

- hypothesis: ordering is wrong only in /dev/chat (view-specific bug)
  evidence: /dev/chat and the back-office thread both call Conversation::messages(); the single order clause is at app/Models/Conversation.php:73. The back-office thread has the same defect.
  timestamp: 2026-10-12

## Evidence

- timestamp: 2026-10-12
  checked: database/database.sqlite, UAT conversation 1, `select id, created_at, resolved_at, status from messages where conversation_id=1 order by created_at, id` (the exact ORDER BY of Conversation::messages())
  found: 13 (customer, 21:30:12), 14 (agent_reply draft, created 21:30:36, resolution approved, resolved_at 21:33:55, status sent), 15 (holding_reply, created 21:30:37, sent). Sorted order is 13, 14, 15. Message 14 was actually sent 3 minutes 18 seconds after message 15, but sorts before it.
  implication: reproduces the UAT report with the real rows. Message 14's only send time on the row is resolved_at (and updated_at, which equals it here).

- timestamp: 2026-10-12
  checked: same database, held-draft/holding-reply pairs in conversations 9, 11, 12, 13 (drafts 17, 22, 25, 28; holding replies 18, 23, 26, 29)
  found: every pair has draft.created_at equal to holding.created_at to the second (21:43:46, 21:47:03, 21:51:26, 21:55:54) and draft id lower than holding id. Conversation 1 (14/15) is one second apart.
  implication: for any draft sent later, created_at ties or precedes the holding reply and the id tie-break also puts the draft first. The wrong order is deterministic, not timing-dependent.

- timestamp: 2026-10-12
  checked: app/Actions/RunFrontDesk.php storeDraft() lines 321-376
  found: the draft row is created inside the transaction (line 346); the holding reply is sent afterwards, after the commit (line 374, SendFixedReply::holding). So holding.id > draft.id and holding.created_at >= draft.created_at, always.
  implication: the draft can never sort after its own holding reply while ordering uses created_at.

- timestamp: 2026-10-12
  checked: app/Actions/ResolveDraft.php, app/Actions/SendOwnerReply.php sendDraft() lines 78-103, app/Actions/OutboundSender.php sendExisting()/deliverExisting()/transmit()
  found: approving a draft updates the same row (D-54, 02-CONTEXT.md line 100): status draft -> queued -> sent, resolved_at = now() written at claim time (SendOwnerReply.php:99). created_at is never touched.
  implication: the same-row design (D-54) is what makes a sent draft keep its draft-creation time. Cloning the draft into a new row would break D-54, so the fix must supply a send time instead.

- timestamp: 2026-10-12
  checked: whether messages has any send-time column (migrations 2026_10_11_131452, _000001, _000005, _000006; grep sent_at across app, database, routes, resources, tests, docs)
  found: columns are created_at, updated_at, dispatched_at (inbound dispatch), handled_at (inbound claim), resolved_at (draft decision only). No sent_at on messages. sent_at exists only on owner_alerts (SendOwnerAlert.php:64). Message $fillable and casts have no sent time.
  implication: answers the question asked. A sent_at column does not exist, so it is populated on neither auto-sent nor owner-sent messages. For an auto-sent or owner-typed message created_at is within about a second of the send (the row is created as queued just before the channel call), so created_at is an acceptable stand-in. For a sent draft it is wrong by the whole time the draft waited. resolved_at is the closest existing value for a sent draft (set just before the channel call) but it is null for every other message. updated_at is unusable: TwilioStatusCallbackController.php:70 updates the row again on each delivery callback.

- timestamp: 2026-10-12
  checked: every site that orders or labels messages (grep orderBy, latest, created_at, messages() across app, resources, routes)
  found: see "Ordering sites" below.
  implication: one root (the ordering key plus missing sent time), several consumers.

### Ordering sites

Orders by created_at, id (all inherit from Conversation::messages()):
1. app/Models/Conversation.php:73 - the single definition: `hasMany(Message::class)->orderBy('created_at')->orderBy('id')`. The docblock (lines 66-68) calls it "arrival order".
2. app/Livewire/ConversationThread.php:119-124 messages() - back-office thread and owner-alert landing page; filters out Draft and Withdrawn, so a sent draft is included and misplaced. Rendered by Filament ConversationInfolist (app/Filament/Resources/Conversations/Schemas/ConversationInfolist.php:18).
3. app/Livewire/DevChat.php:90 thread() - `$conversation->messages()->get()`; this is the /dev/chat list the user saw. Unfiltered (also shows Draft and Withdrawn; that is G-02-2.3).
4. app/Ai/FrontDeskPrompt.php:111-118 history() - the thread the front-desk model reads. Same order, plus a `break` at the first burst message (line 112-114). A sent draft sits among the old turns in draft order, so the agent can be shown the owner's later reply before the holding reply that really went out first. See "Watch for" below.
5. app/Records/RecordsWriter.php:260-275 waitingSince() and 247 lastMessage() - the open-leads.md summary, fed by `with('openConversation.messages')` (line 235). Uses `$messages->last()` as the newest message and reads `->created_at` for "replied <time>", so a late-sent draft can be reported as an old reply or not as the last message.

Labels that show a message time (must change with the order, or the displayed time contradicts the displayed order):
6. resources/views/livewire/conversation-thread.blade.php:52 - `$message->created_at ... format('M j, g:i a')`.
7. resources/views/livewire/dev-chat.blade.php:10 - same.

Uses created_at as if it were send time (adjacent, lower priority, decide deliberately):
8. app/Actions/RunFrontDesk.php:245-246 replyCapReached() - counts sends in the last hour/day by created_at. An approved draft (author stays Agent) sent hours after it was written is counted by its draft time.
9. app/Actions/SendFixedReply.php:92-120 holdingAlreadySent() - id-based ("id > latest holding id") plus `resolved_at >= latest->created_at` for drafts; its docblock (line 90) says "drafts keep their original place in the thread while the owner's decision comes later". This logic was written around the draft keeping its old position; it keeps working if a send time is added but is the reason id order must not be reused as a proxy for send order.

Correct as is (leave alone): app/Filament/Widgets/DraftsWaiting.php:38,50 (a waiting draft's age is its creation time); app/Console/Commands/GateExport.php:95 (`decided_at` is when the send rule decided, which is draft creation); the customer-card timeline (ReplySent activity already uses occurred_at = real send time, OutboundSender.php:273).

- timestamp: 2026-10-12
  checked: app/Actions/SendFixedReply.php:97 and Eloquent ordering on a relation that already has orderBy
  found: `$conversation->messages()->where(...)->latest('id')->first()` compiles to `order by "created_at" asc, "id" asc, "id" desc` (confirmed with toSql() on the relation). `latest('id')` does not replace the relation's order, so it returns the oldest holding reply, not the newest. (OwnerAlertText.php:122 correctly uses reorder('id','desc').)
  implication: a separate latent defect, not the cause of G-02-1.2, but it sits on the same relation. Whoever changes the relation's order key should know that `latest()`/`oldest()` on `messages()` stack behind it. Not investigated further.

## Overlap with other gaps

- G-02-1.1 (needs-you-thread-opens-oldest.md): same blade file, conversation-thread.blade.php. That fix touches the scroll box at line 40; this one touches the time label at line 52 and the query in ConversationThread::messages(). "Latest message" in G-02-1.1 only means the right thing once this ordering is sent-time order. Expect a merge touch, no logic conflict.
- G-02-2.3 (/dev/chat shows held drafts as sent): same two files as the DevChat sites above (DevChat.php:90, dev-chat.blade.php:10). It is the unfiltered `->get()` with no status shown. Once drafts are filtered out of /dev/chat, a draft shows up there only after it is sent, and then this ordering fix decides where. Not investigated further.
- G-02-2.2 (agent knows date and time; timestamp on each thread message): wants a per-message "when" in FrontDeskPrompt::history(). It should use the same send-time value this gap introduces, so the model sees the time a message went out, in thread order. One shared accessor on Message serves both.

## Resolution

root_cause: Threads are ordered by messages.created_at, id (app/Models/Conversation.php:73) and displayed with created_at (conversation-thread.blade.php:52, dev-chat.blade.php:10), but a held draft is the same row that becomes the sent message (D-54) so it keeps the time the agent wrote it, and the holding reply is always created after it (RunFrontDesk.php:346 then :374). The messages table has no send-time column at all: OutboundSender::transmit() computes $sentAt = now() (OutboundSender.php:261) and writes it only to the activity, conversation and contact rows, not to the message. So an owner-sent draft sorts above the holding reply that went out before it. Verified on UAT conversation 1: draft 14 (created 21:30:36, sent 21:33:55) sorts before holding reply 15 (created and sent 21:30:37).
fix: (not applied, diagnose-only)
verification: n/a
files_changed: []

### Notes for the planner (non-binding)

- Smallest root-level change: add a nullable `sent_at` timestamp to messages; write it in OutboundSender::transmit() (reuse the existing $sentAt, line 261) and in markSentAfterRecordingFailed() (line 315); backfill existing sent/delivered rows (`coalesce(resolved_at, created_at)`, like the 000002 backfill migration precedent); order Conversation::messages() by `coalesce(sent_at, created_at)` then id so drafts, queued and failed rows (null sent_at) keep their creation time; add one Message accessor for "when this happened" and use it in the two blade labels, FrontDeskPrompt (G-02-2.2) and RecordsWriter. coalesce works on both SQLite and Postgres (CI runs both legs). Respect the migration rule in .ai/rules/migrations.md and the 02-19 note about rebuilding the SQLite table (a new nullable column on messages rebuilds it and turns the partial unique index messages_one_live_draft_per_conversation into a plain one; recreate it in the same migration).
- Test hazard: timestamps are one-second resolution and frozen clocks (Carbon::setTestNow, as in OwnerReplyTest.php:237) give the holding reply and the approved draft the same timestamp, where the id tie-break puts the draft first again. A regression test for this gap must advance the clock between the holding reply and the approval (travel) and assert the sent reply comes after the holding reply, in both ConversationThread and DevChat.
- Watch for: FrontDeskPrompt::history() stops at the first burst message (`break`, lines 112-114). Once order is by send time, an owner-sent draft that went out after the customer's newest text arrived sorts after the burst and is dropped from the model's history (today it is kept, by accident of its old created_at). Decide on purpose whether the history should skip burst messages instead of stopping at the first.
- Do not clone the draft into a new row to get a new created_at: it breaks D-54 (one row per decision) and the holdingAlreadySent() logic.
