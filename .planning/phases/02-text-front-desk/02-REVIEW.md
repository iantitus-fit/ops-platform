---
phase: 02-text-front-desk
reviewed: 2026-10-13T00:00:00Z
depth: standard
files_reviewed: 41
files_reviewed_list:
  - app/Actions/OutboundSender.php
  - app/Actions/RunFrontDesk.php
  - app/Actions/SendFixedReply.php
  - app/Ai/FrontDeskPrompt.php
  - app/Enums/MessageStatus.php
  - app/Livewire/ConversationThread.php
  - app/Livewire/DevChat.php
  - app/Models/Conversation.php
  - app/Models/Message.php
  - app/Records/RecordsWriter.php
  - app/SendRule/TriageReading.php
  - config/gate.php
  - database/migrations/2026_10_12_000010_add_sent_at_to_messages_table.php
  - database/migrations/2026_10_12_000011_backfill_sent_at_on_messages.php
  - docs/sprints/sprint-1.md
  - examples/example-hvac/business/services/CONTEXT.md
  - examples/example-hvac/business/services/furnace-repair.md
  - examples/example-hvac/rules/triage.md
  - map/objects/contacts/contact-opt-out.md
  - map/objects/conversations/conversation-and-pause.md
  - map/objects/folder/agent-file-and-version.md
  - map/objects/intake/intake-answer.md
  - map/objects/messages/message-and-verdict.md
  - map/processes/answer-inbound.md
  - map/VERIFICATION.md
  - resources/views/livewire/conversation-thread.blade.php
  - resources/views/livewire/dev-chat.blade.php
  - tests/Feature/DevChatTest.php
  - tests/Feature/Filament/ConversationViewTest.php
  - tests/Feature/FrontDesk/DraftReplacementTest.php
  - tests/Feature/FrontDesk/FrontDeskPromptTest.php
  - tests/Feature/FrontDesk/GateTest.php
  - tests/Feature/FrontDesk/HoldingReplyTest.php
  - tests/Feature/FrontDesk/ReplyCapTest.php
  - tests/Feature/FrontDesk/SentTimeTest.php
  - tests/Feature/Icm/ActiveConfigTest.php
  - tests/Feature/Icm/ValidatorTest.php
  - tests/Feature/Records/SummaryFilesTest.php
  - tests/Feature/SendRule/JevClassifierTest.php
  - tests/Pest.php
  - tests/Unit/SendRule/TriageReadingTest.php
findings:
  critical: 0
  warning: 2
  info: 3
  total: 5
status: issues_found
---

# Phase 02: Code Review Report (gap plans 02-35 to 02-41)

**Reviewed:** 2026-10-13
**Depth:** standard
**Files Reviewed:** 41
**Status:** issues_found

## Summary

Scope was the changes since `4fbbd45` in the listed files. I read the full diff, including the changed files outside the
list (`DispatchInbound`, `SendLifeSafetyLine`, `LifeSafety`, `ProcessInbound`, `RespondToLifeSafety`), for context. I
also ran the affected suites (`tests/Feature/FrontDesk`, `DevChatTest`, `ConversationViewTest`, `tests/Feature/Records`,
`tests/Unit/SendRule`, `tests/Feature/Icm`): 649 tests pass.

The core of this batch holds up:

- `sent_at` is captured once after the channel accepts the text and reused in the transaction, the activity row, the
  conversation, and the fallback path.
- `reorder('id', 'desc')` correctly replaces the new relation order in `SendFixedReply::holdingAlreadySent`,
  `OwnerAlertText`, and `ConversationThread::lastCustomerMessage`.
- `FrontDeskPrompt::history()` now skips the burst instead of stopping at it, and drops customer texts newer than the
  burst.
- The Needs you card escapes customer text.
- The backfill is correct for the statuses it touches.

The new send-time ordering (D-54) was not carried through to every reader of "the thread" or "who spoke last". That
leaves two WARNING-level inconsistencies, both described below. No security issue or data-loss defect was found in this
scope. Jev is left as is, as the project requires.

## Warnings

### WR-01: `RecordsWriter` treats unsent messages as part of the thread (open-leads.md reports "replied" for a lead nobody has answered)

**File:** `app/Records/RecordsWriter.php:232-290` (touched in this batch at `:267`, `:274`)
**Issue:** `openLeadsMarkdown()` loads `openConversation.messages` with no status filter. `waitingSince()` and
`lastMessage()` then treat the last row as what happened last. A held draft (status `draft`), a withdrawn or failed
text, or a still-queued text is an outbound row and is usually the newest row.

A customer whose price question is held for the owner is the case this file exists for. That lead's last row is the
unsent draft, so:

- "Waiting since" reads `replied <time the draft was written>` instead of the time the customer started waiting.
- "Last message" prints the AI's unsent draft body, such as the very price or promise the gate refused to send.

This batch edited both lines to use `happenedAt()` and added a test for a *sent* draft. It added none for a *held*
draft, so the gap is still open. The thread view in `ConversationThread::messages()` and the model history already
exclude these statuses. This file did not get the same treatment.

**Fix:** Filter to what reached the customer, using the one shared list the batch just introduced:
```php
$messages = $contact->openConversation->messages
    ->filter(fn (Message $m): bool => in_array($m->status, MessageStatus::onTheCustomersPhone(), true))
    ->values();
```
Add a SummaryFilesTest case with a held draft newer than the customer's text. It should expect the customer's text time
and the customer's text as the last message.

### WR-02: `PausedConversationsWaiting` decides "who spoke last" by `created_at`, so a draft the owner sent later still looks unanswered

**File:** `app/Filament/Widgets/PausedConversationsWaiting.php:29-34` (a changed-behavior consumer; the file itself is not in the diff)
**Issue:** D-54 moved a message to its send time via `coalesce(sent_at, created_at)`, so an owner-approved draft sorts
after a customer text that arrived while the draft waited. This widget's "latest author" subquery still orders by
`created_at desc`.

Scenario: a conversation is paused (take over, or asked for a person). It has a live draft D, created at t1. The
customer texts again at t2. The owner approves D at t3. `sendDraft` does not clear the pause, and `whyBlocked` allows
an owner send while paused.

- The conversation view lists D last, at t3, so the owner has answered.
- The dashboard's latest-author subquery sorts D at t1, before the customer's t2 text, so it picks the customer.
- The widget shows the customer under "Customers waiting while you handle it" after the owner replied.

The thread and the dashboard now disagree about the same conversation.

**Fix:** Use the same ordering definition as `Conversation::messages()`:
```php
->orderByRaw('coalesce(sent_at, created_at) desc')
->orderByDesc('id')
```
Then add a widget test that mirrors the scenario above.

## Info

### IN-01: `TriageReading::read` reads four new threshold keys with no guard

**File:** `app/SendRule/TriageReading.php:62`
**Issue:** `$thresholds["{$check}_max_yes"]` raises an undefined-key `ErrorException` if a deployment has a stale
published or cached `gate.classifier` array without the four new keys. `DecideSendOrDraft::askJev()` calls `read()`
outside its `try`. The run therefore fails with the owner told, and the customer's text goes unanswered. It fails
closed, which is safe, but it is an avoidable hard failure. The new unit test, `uses the same thresholds as
config/gate.php`, guards only the repo copy of the config, not a deployed one.
**Fix:** Either fall back to the old key (`$thresholds["{$check}_max_yes"] ?? $thresholds['gate_check_max_yes']`), or
make the failure explicit and held with `Verdict::hold('classifier_failed', ...)` by moving `read()` inside the guarded
block.

### IN-02: Two gate dials for `promise` and `complaint` were loosened together on one live reading

**File:** `config/gate.php:46-80`, `examples/example-hvac/rules/triage.md:28-30`
**Issue:** D-56 raises the `promise` and `complaint` bars from 0.075 to 0.15 and also rewords both Jev questions to be
narrower. Both change the classifier's behavior on the same check: a higher bar, and a question that scores lower. The
stated evidence is one live observation (promise 0.09 on an intake question). This is a recorded decision, so it is not
reopened here. The tuning record shows no way to tell later which of the two changes moved the score. Per the "Gate
checks are tuned by `gate:export`" intent, record the before/after scores for the reworded questions alongside the new
bars when the next live run happens.
**Fix:** Capture the answers from the next live run in the phase notes. No code change needed.

### IN-03: Small inconsistencies in `ConversationThread` and the Needs you card

**File:** `app/Livewire/ConversationThread.php:225`, `resources/views/livewire/conversation-thread.blade.php:13-15`
**Issue:**
1. The cleanup `unset($this->liveDraft, $this->messages, $this->draftNotAlerted)` does not include the new
   `lastCustomerMessage` computed property. It is harmless today, because the card is hidden once the draft is
   resolved. It is the sort of omission that bites when an action starts re-rendering the card in the same request.
2. `Str::limit(Str::squish($body), 160) ?: '(no words)'` treats a body of exactly `0` as empty, because `'0'` is
   falsy in PHP. A customer who texts "0" shows as "(no words)". Use `=== ''` instead.
**Fix:** Add `$this->lastCustomerMessage` to the `unset(...)` list, and compare the limited string with `=== ''`.

---

_Reviewed: 2026-10-13_
_Reviewer: Claude (gsd-code-reviewer)_
_Depth: standard_
