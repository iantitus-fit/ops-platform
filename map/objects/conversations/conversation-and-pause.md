---
type: object
cluster: conversations
status: live
verified: 2026-10-12 @ be50284 (working tree, source inspection; owner ruling 2026-10-12)
entity: app/Models/Conversation.php
---

# Conversation and its paused state

**Type:** object · **Mark:** live · **Checked:** 2026-10-12 @ be50284 · **Where:** `app/Models/Conversation.php:15`

**One sentence.** A `conversations` row is one thread per contact, at most one open at a time; its paused state (`paused_at`, `paused_reason`, `paused_by`) says the assistant has stepped back, why, and who did it.

**Why this shape.** The database itself refuses a second open conversation for one contact (partial unique index, `database/migrations/2026_10_11_131451_create_conversations_table.php:27`). There is one paused state for every way the assistant can step back, so there is one Hand back (D-23); a null `paused_at` means active, and the same column says when. The pause belongs to the conversation, not the customer: a conversation opened after a close starts active (`Conversation.php:56-63`).

## Shape
- Table: `database/migrations/2026_10_11_131451_create_conversations_table.php:18`. Pause columns: `database/migrations/2026_10_12_000008_add_pause_columns_to_conversations_table.php:23-25`. That migration drops and recreates the one-open index (`:44-48`) because SQLite's table rebuild loses its `where` clause.
- Open conversation is found or made in `RecordInbound.php:369` (`:379` creates). `ConversationStatus` is `open` or `closed` (`app/Enums/ConversationStatus.php`); closed refuses every send (`OutboundSender.php:127`).
- Pause reasons: taken over, asked for a person, owner replied, life safety, reply cap (`app/Enums/PausedReason.php:9-15`). All five go through `PauseConversation::handle` (`PauseConversation.php:34`): take over `ViewConversation.php:29`, reply cap `RunFrontDesk.php:97`, asked for a person `RunFrontDesk.php:256`, owner reply `SendOwnerReply.php:59`, safety line `SendLifeSafetyLine.php:100`. Hand back: `HandBackConversation.php:31`. Both re-read the row under a lock and do nothing if it is already in the state asked for.
- Reply cap: at most 6 agent texts an hour and 20 a day per conversation (`config/gate.php:106`, counted at `RunFrontDesk.php:233`); the safety line is not counted. At the cap the conversation pauses and the owner is alerted (D-52).
- Take over and Hand back are the owner's two controls (`ViewConversation.php:24` and `:33`); the banner is `ConversationThread::pausedBanner` (`ConversationThread.php:172`).

## Words on this card
"Paused" is per conversation and blocks automated texts only; the owner's own reply and the safety line still pass (`OutboundSender.php:131`). "Opted out" is per contact and blocks every text (see [contact-opt-out](../contacts/contact-opt-out.md)). "Conversation" here is our table, never the SDK's `agent_conversations`.

## Connected to
Belongs to a contact (`Conversation.php:41`); has many messages (`:71`) and intake answers (`:81`); `paused_by` is a user (`:51`). Looks like it but is not: the SDK's `agent_conversations` table (leftover, nothing reads or writes it).

## If you change this
**Hits:** the send path (`OutboundSender::whyBlocked`, `OutboundSender.php:116`), the front desk's early exits (`RunFrontDesk.php:89`), the owner's controls and banner, the timeline (`agent_paused` and `agent_resumed` activities), and [answer-inbound](../../processes/answer-inbound.md) at its pause and alert steps. A new pause reason needs a case in `PausedReason` with its banner lead and timeline line.
**Does not hit:** the contact's opt-out (a different column on `contacts`); the message verdict (a held draft does not pause anything, and approving one does not pause either, D-25); the agent file.

## Surfaces
Writers: the five pause call sites above, `HandBackConversation`, `CloseConversation`, `ReopenConversation`, `RecordInbound.php:379`. Readers: `OutboundSender.php:131`, `RunFrontDesk.php:89`, `ViewConversation.php`, `ConversationThread.php`. Humans: the owner. External consumers: none.

## See
`app/Models/Conversation.php`, `app/Actions/PauseConversation.php`; decisions D-23, D-24, D-52 in `.planning/phases/02-text-front-desk/02-CONTEXT.md`.
