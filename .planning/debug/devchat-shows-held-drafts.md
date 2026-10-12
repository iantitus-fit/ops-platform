---
status: diagnosed
trigger: "Gap G-02-2.3: /dev/chat shows held drafts as if they were sent; the page should show held drafts as held (or not at all)."
created: 2026-10-12T00:00:00Z
updated: 2026-10-12T00:00:00Z
goal: find_root_cause_only
---

## Current Focus

hypothesis: CONFIRMED. DevChat::thread() (app/Livewire/DevChat.php:90) returns `$conversation->messages()->get()` with no status condition, and dev-chat.blade.php renders author, time and body for every row and never reads `status`. A held draft is a normal row in `messages` (status draft, direction out, author agent), so it renders exactly like a sent agent reply. A discarded or replaced draft (status withdrawn) never leaves the page either.
test: rendered the real DevChat component against database/database.sqlite for the UAT number that holds a furnace draft (read-only; no row written) and compared the draft row with the holding reply that really went out
expecting: n/a, diagnosis complete
next_action: hand root cause to plan-phase --gaps

bug_class: Bohrbug (deterministic, no timing dependence). SBFL skipped: no failing test exists for this behaviour. Knowledge base: .planning/debug/knowledge-base.md does not exist; no prior match.

## Symptoms

expected: /dev/chat shows held drafts as held (or not at all), not as if they were sent to the customer.
actual: "/dev/chat shows held drafts as if they were sent" (UAT test 2; the furnace draft "We do repair furnaces" sat in the page next to the real replies)
errors: none
reproduction: Test 2 in .planning/phases/02-text-front-desk/02-UAT.md: text from /dev/chat so a reply is held as a draft; the draft appears in the page.
started: found in Phase 2 UAT, 2026-10-12. Latent since plan 02-14 (e77c2a6), the first code that writes a draft row.

## Eliminated

- hypothesis: the draft is shown because it was really sent (a send-path bug that marks it sent)
  evidence: the live row is status `draft`, never `sent`. database/database.sqlite message 17 (conversation 9) is status draft, resolved_at null; the owner has not decided it. Nothing was texted to the customer. The page shows it only because the query returns every row.
  timestamp: 2026-10-12

- hypothesis: the page is wrong only because of ordering (G-02-1.2)
  evidence: G-02-1.2 explains where a SENT draft lands. It does not explain why an unsent draft is on the page at all. The two share lines but have different causes: this one is a missing status condition in the query; that one is the sort key and a missing send time.
  timestamp: 2026-10-12

- hypothesis: the data is wrong (rows mislabelled)
  evidence: statuses in the dev database are internally consistent (draft 4, received 15, sent 10). The draft rows carry status draft, kind agent_reply and a gate verdict. The data is right; the view ignores it.
  timestamp: 2026-10-12

## Evidence

- timestamp: 2026-10-12
  checked: app/Livewire/DevChat.php thread() lines 83-91 and resources/views/livewire/dev-chat.blade.php lines 6-13
  found: `return $conversation === null ? new Collection : $conversation->messages()->get();` has no where clause. The view prints `$message->author->name`, `created_at` and `body` and nothing else. The word status does not occur in the file.
  implication: every status in the enum reaches the page, and nothing on the page can tell a draft from a sent reply.

- timestamp: 2026-10-12
  checked: rendered the real component (php artisan tinker, Livewire::test(DevChat::class)->set('from', '+13175550142'), html only, nothing sent or written). Contact 8 / conversation 9 is the furnace run from UAT.
  found: three rows come out:
    Customer | Oct 12, 4:43 pm | My furnace won't turn on.
    Agent    | Oct 12, 4:43 pm | Sorry your furnace won't turn on. We do repair furnaces...   (message 17, status draft, never sent)
    Agent    | Oct 12, 4:43 pm | This is the virtual assistant for Example Heating & Air.      (message 18, holding_reply, status sent)
  implication: reproduces the report. The held draft and the holding reply that really reached the customer are identical in author label, time format and styling. This is also exactly the "We do repair furnaces" text the user flagged in G-02-2.4.

- timestamp: 2026-10-12
  checked: app/Models/Conversation.php:73 messages()
  found: `hasMany(Message::class)->orderBy('created_at')->orderBy('id')`; the docblock calls it "the messages in arrival order". It carries no status meaning.
  implication: the relation is deliberately status-blind, so each consumer must add its own status condition. DevChat added none.

- timestamp: 2026-10-12
  checked: how every other consumer of a conversation's messages decides which rows count (grep MessageStatus:: across app, resources)
  found: the codebase has four separate answers to "which messages are part of the thread":
    - app/Livewire/ConversationThread.php:122 whereNotIn [Draft, Withdrawn] (owner view; keeps Queued and Failed and badges them)
    - app/Filament/Widgets/PausedConversationsWaiting.php:32 whereNotIn [Draft, Withdrawn]
    - app/Ai/FrontDeskPrompt.php:59 SHOWN_STATUSES = [Received, Sent, Delivered] ("ones that arrived, or that reached the customer"), used at line 114
    - app/Livewire/DevChat.php:90 nothing
  implication: DevChat is the one consumer with no rule. FrontDeskPrompt's constant is the existing definition of "what the customer has", which is what a simulated customer phone should show.

- timestamp: 2026-10-12
  checked: git history and plan files for who introduced /dev/chat and why DevChat was not updated
  found: |
    /dev/chat was built in plan 01-07 (commits 3e561a4 test, ba97585 feat), requirement DASH-02: "lets Ian text 'as a customer' and watch the thread update" (REQUIREMENTS.md:32; docs/sprints/sprint-0.md Done when #9). The plan's read_first told the executor to use ConversationThread's message list "to mirror" (01-07-PLAN.md:143) and the view spec was "each message: author, time, body" (line 158). MessageStatus then had Draft as an unused case (55de073); no code wrote a draft until plan 02-14 (e77c2a6). Withdrawn arrived in 02-17 (56d0510). Plan 02-22 (f38b3bf) took drafts and withdrawn rows out of ConversationThread and moved the live draft into the Needs you block; its changed files do not include DevChat.php or dev-chat.blade.php. None of the plans 02-14, 02-16, 02-17 or 02-22 mention DevChat, and tests/Feature/DevChatTest.php has no draft, withdrawn, queued or failed case. The only Phase 2 plan that touched DevChat is 02-25 (opt-out keyword), unrelated.
  implication: DevChat was copied from the thread when every outbound row was a sent or failed text. When drafts became rows in the same table (D-54: the draft is the same row that becomes the sent message), the back-office copy was fixed and the dev copy was missed. No test pinned the dev page, so nothing failed. The back-office intent is stated in HeldDraftTest.php:106 ("never in the thread as if it were sent") and the MessageStatus docblock ("withdrawn ... is never shown to the customer or the model").

- timestamp: 2026-10-12
  checked: which statuses a customer would NOT have received. Sources: OutboundSender.php (deliver 171-195, deliverExisting 200-237, transmit 243-288, recordFailure 326, recordCarrierOptOut 350), ResolveDraft.php discard() 71-96, RunFrontDesk.php storeDraft 321-376, TwilioStatusCallbackController.php record() 46 and rank() 127
  found: |
    Reached the customer, or is the customer's own text: received (inbound), sent, delivered.
    Not received by the customer:
    - draft: held for the owner. Never handed to the channel. Live ones belong in the Needs you block and the DraftsWaiting widget.
    - withdrawn: a draft that was discarded (ResolveDraft.php:83, resolution discarded) or replaced by a newer one (RunFrontDesk.php:338, resolution replaced). Kept for the record, never sent. Today these stay on /dev/chat forever.
    - queued: a row created or claimed just before the channel call, moved to sent in the same request (OutboundSender.php:190, 223, 264). Transient. A row stuck here means the process died around the channel call, so delivery is unknown. Customer-visible only once it becomes sent.
    - failed: the channel refused (recordFailure, OutboundSender.php:330), the carrier refused with 21610 (recordCarrierOptOut, :354), or Twilio later reported failed/undelivered for a message that had been sent (callback controller :118, rank :132). In every case the customer does not have the text. The owner needs to see these, so the back-office thread keeps them with a danger badge; a phone view should not.
    A sent draft is the same row after Draft -> Queued -> Sent (D-54), so it appears on /dev/chat only from the moment the owner sends it; where it lands is then G-02-1.2's job.
  implication: the customer-phone rule is exactly FrontDeskPrompt::SHOWN_STATUSES: received, sent, delivered.

- timestamp: 2026-10-12
  checked: whether a "held for owner" marker is worth adding on /dev/chat
  found: the held state already shows indirectly. A held draft makes the system send the holding reply ("This is the virtual assistant for ..."), which is on the phone view as a real sent message (message 18). The draft itself, its reason (gate.reason.plain) and Send/Discard live on the conversation screen, which is where UAT test 1 sends the owner. A marker for the live draft would re-create the original confusion unless styled as clearly not-a-bubble, and a marker for withdrawn rows is noise.
  implication: leave drafts off the page. If Ian wants developer visibility, a single muted line for the live draft ("Held for owner, not sent") outside the bubble list is the only safe form. Recommend not adding it in this fix.

## Overlap with G-02-1.2 (outbound-sorts-by-draft-time.md)

- Same two files and lines: DevChat.php:90 (query) and dev-chat.blade.php:10 (time label). G-02-1.2 changes the sort key and the label; this gap changes which rows come back. They are independent defects and do not conflict logically, but a plan that edits both should be one task or two tasks in a fixed order, and the regression test for G-02-1.2 ("sent reply comes after the holding reply in DevChat") must still build its sent draft as status sent. Filtering first is harmless: a Draft -> Sent row passes the filter once sent.
- After this fix a sent draft appears on /dev/chat for the first time only when the owner sends it, and with today's created_at ordering and label it will appear at its draft time, above the holding reply and possibly above a later customer text. So fixing G-02-2.3 alone makes G-02-1.2 more visible in dev chat, not less. Ship them together or order G-02-1.2 first.
- G-02-1.2's own notes say: `latest()`/`oldest()` on `messages()` stack behind the relation's `orderBy`. If the filter is added as a relation or scope, keep it separate from ordering.

## Resolution

root_cause: DevChat::thread() (app/Livewire/DevChat.php:90) lists the conversation with `$conversation->messages()->get()` and no status condition, and the view (resources/views/livewire/dev-chat.blade.php:6-13) prints author, time and body without ever reading status. A held draft is an ordinary outbound agent row in `messages` with status `draft` (D-54), so it renders identically to a sent reply, and a discarded or replaced draft (status `withdrawn`) stays on the page for good. The page was built in plan 01-07 as a copy of the thread list when every outbound row was sent or failed; plans 02-14, 02-17 and 02-22 introduced draft and withdrawn rows and filtered them out of ConversationThread (ConversationThread.php:122), but none listed DevChat, and DevChatTest has no draft case. There is no single definition of "what the customer has received": four consumers re-declare it, and DevChat has none. Verified by rendering the component for the UAT furnace conversation: draft 17 (status draft, not sent) and holding reply 18 (status sent) come out as identical "Agent" rows.
fix: (not applied, diagnose-only)
verification: n/a
files_changed: []

### Notes for the planner (non-binding)

- Smallest change: in DevChat::thread() restrict to the statuses the customer has, `whereIn('status', [Received, Sent, Delivered])`. That is exactly FrontDeskPrompt::SHOWN_STATUSES (FrontDeskPrompt.php:59). Cleaner: define that list once (for example a method on MessageStatus or a scope on Message) and use it from both DevChat and FrontDeskPrompt, so the "what the customer has" rule is stated in one place. Do not change ConversationThread's filter: the owner view must keep Queued and Failed rows visible with their badges.
- What /dev/chat should show per status: received yes (the customer's own text), sent yes, delivered yes; draft no, withdrawn no, queued no, failed no. Failed rows remain visible to the owner on the conversation screen.
- No view change is needed for the filter itself. The blade can keep printing author, time and body, because every remaining row is something the customer has.
- Tests (tests first per CLAUDE.md, Pest, in tests/Feature/DevChatTest.php, file-specific helper names per the 01-06 note): create a conversation for the dev number with the factories (`Message::factory()->draft()`, `->withdrawn()`, `->outbound()`, `->fromOwner()`, plus a queued and a failed state set inline) and assert the component's `thread` ids and rendered HTML. Follow DraftReviewTest.php:98 (`$thread->instance()->messages->pluck('id')`) and HeldDraftTest.php:106-121. Cases: live draft body absent; withdrawn (discarded and replaced) absent; queued and failed absent; received, sent, delivered and owner-sent present; and a draft that is then approved via ResolveDraft::approve appears afterwards. Add a mutation check: drop the whereIn and confirm the draft case fails.
- Test hazard from G-02-1.2 applies: timestamps have one-second resolution, so keep assertions on membership, not order, in this gap's tests.
- Also update the explanatory sentence in dev-chat.blade.php line 3 only if Ian wants it to say that held drafts are not shown; optional.
