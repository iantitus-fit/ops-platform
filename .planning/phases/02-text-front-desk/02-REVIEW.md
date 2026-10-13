---
phase: 02-text-front-desk
reviewed: 2026-10-13T00:00:00Z
depth: standard
files_reviewed: 5
files_reviewed_list:
  - app/Filament/Widgets/PausedConversationsWaiting.php
  - app/Records/RecordsWriter.php
  - map/objects/messages/message-and-verdict.md
  - tests/Feature/Filament/DashboardWidgetsTest.php
  - tests/Feature/Records/SummaryFilesTest.php
findings:
  critical: 0
  warning: 2
  info: 3
  total: 5
status: issues_found
---

# Phase 2: Code Review Report (incremental re-review)

**Reviewed:** 2026-10-13
**Depth:** standard
**Files Reviewed:** 5
**Status:** issues_found

## Summary

This re-reviews the fixes for the earlier WR-26 and WR-27 (review 3's WR-01 and WR-02). I read `git diff c0bdd8c HEAD` for the five files, then read the current widget, `RecordsWriter`, the message and conversation models, the status enum and the message factory.

**WR-26 (open-leads counted messages that never reached the customer): fixed.**
- `openLeadsMarkdown()` now filters the thread to `MessageStatus::onTheCustomersPhone()` (received, sent, delivered) before it computes both `waitingSince()` and `lastMessage()`.
- The comparison is strict, and `status` is cast to the enum, so `in_array(..., true)` is correct.
- `->values()` re-indexes the collection. Without it `->last()` would still work, but this is safer.
- Inbound messages are always written as `Received` (`RecordInbound.php:145`), so no real customer text is dropped by the filter.
- The new Pest test fails on the old code: the failed text at 14:07 would have been the last message.

**WR-27 (paused widget used draft creation time to decide who spoke last): fixed for the ordering.**
- The correlated subquery now orders by `coalesce(sent_at, created_at) desc`, then `id desc`. This matches `Conversation::messages()` and `Message::happenedAt()`.
- The new test would have failed under the old `created_at` ordering: the customer text at -20 minutes would have beaten the draft created at -30 minutes. It passes under the new ordering because the send time is -5 minutes.

No security issues and no new crash paths. The two WARNINGs below concern consistency left over from the fixes: the widget uses a different definition of "reached the customer" than open-leads now does, and the map note is stale.

## Warnings

### WR-28: Widget still counts queued and failed texts as "the owner spoke last" [review 4, was WR-01]

**File:** `app/Filament/Widgets/PausedConversationsWaiting.php:32`
**Issue:**
- The subquery excludes only `Draft` and `Withdrawn` (`whereNotIn('status', [Draft, Withdrawn])`).
- A `Queued` text, or a `Failed` one (`OutboundSender.php:334,358`), therefore counts as the latest speaker.
- Take a paused conversation where the customer texted and the owner replied from the dashboard, but the send failed. The customer never got the reply and is still waiting.
- The widget drops that conversation from "Customers waiting while you handle it", which hides the exact case the widget exists to surface.
- The open-leads fix now uses `onTheCustomersPhone()` for the same question ("who spoke last"), so the two views of the same fact disagree.
- The widget's docblock says "where the customer spoke last, so they are waiting on the owner". The owner's text did not reach the customer.

**Fix:** Use the one shared definition, the same way `RecordsWriter` now does:
```php
->whereIn('status', MessageStatus::onTheCustomersPhone())
```
Bind the enum values rather than the enum cases if the query builder needs raw strings: `array_map(fn (MessageStatus $s) => $s->value, MessageStatus::onTheCustomersPhone())`. Add a test with a failed owner text sent after the customer's text (the conversation must still be listed). If keeping queued and failed counted is a deliberate decision, record it in the widget docblock and the map note.

### WR-29: The map note and the enum docblock are stale after the open-leads change [review 4, was WR-02]

**File:** `map/objects/messages/message-and-verdict.md:21` and `:34`; `app/Enums/MessageStatus.php:17-22` (the docblock, outside this scope but the source of the claim)
**Issue:**
- Line 21 says "What a customer has on their phone is `onTheCustomersPhone()` ... read by `/dev/chat` (`DevChat.php:94`) and by the model's history".
- Line 34 ("Hits") repeats that list.
- Both are now incomplete: `RecordsWriter.php:242` also reads it, for `open-leads.md`.
- The `MessageStatus::onTheCustomersPhone()` docblock says it is the one definition for "the dev chat page, the model's history".
- The map note also does not mention that `PausedConversationsWaiting.php:32` uses a different list (draft and withdrawn only). That is the divergence in WR-28.
- The map is the document used to find every reader of a field before changing it. A reader who trusts line 21 would change the enum list and miss `open-leads.md`.
- The only line edit in this commit (261 to 264) updated a line number but not the content that changed.

**Fix:** Add `RecordsWriter.php:242` to the readers on lines 21 and 34, and update the enum docblock to match. If the widget keeps its own list, add one sentence saying why, beside the owner-thread sentence.

## Info

### IN-26: `waitingSince()` and `openLeadsMarkdown()` docblocks do not say the thread is filtered [review 4, was IN-01]

**File:** `app/Records/RecordsWriter.php:226-229, 257-263`
**Issue:**
- The docblocks describe "the oldest message in the unanswered run" and "the owner replied".
- The file now shows only texts that reached the customer, and that rule is not documented.
- A later reader could pass an unfiltered collection to `waitingSince()` and reintroduce WR-26 with no warning.

**Fix:** Add "`$messages` holds only the texts the customer has on their phone, oldest first" to the `@param` description.

### IN-27: Map citation points at the method signature, not at the `happenedAt()` calls [review 4, was IN-02]

**File:** `map/objects/messages/message-and-verdict.md:20`
**Issue:**
- `RecordsWriter.php:264` is the `waitingSince` declaration. The `happenedAt()` calls are at lines 273 and 278.
- The citation is close enough to find the method, but the previous citation (261) pointed at a call site.
- It will drift again with any edit above it.

**Fix:** Cite `RecordsWriter.php:273,278`, or cite the method by name.

### IN-28: Test coverage of the two fixes is one-sided [review 4, was IN-03]

**File:** `tests/Feature/Filament/DashboardWidgetsTest.php:104-119`; `tests/Feature/Records/SummaryFilesTest.php:158-179`
**Issue:**
- The widget test covers only the case where the owner's send time is newer than the customer's text. It has no inverse control, where a draft was approved before the customer's later text and the conversation must stay listed.
- Without it, a regression to "any outbound hides the conversation" would pass.
- The open-leads test covers only a thread with one surviving customer text. It has no case for "owner replied, then a later failed or draft message", which would exercise the `replied ...` branch of `waitingSince()` after filtering.
- It also has no case where every message is filtered out (the `none` branch).

**Fix:** Add one inverse widget test, and one open-leads case where the last real message is the owner's with a held draft after it.

---

_Reviewed: 2026-10-13_
_Reviewer: Claude (gsd-code-reviewer)_
_Depth: standard_
