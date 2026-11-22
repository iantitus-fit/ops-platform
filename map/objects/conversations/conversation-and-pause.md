---
type: object
cluster: conversations
status: live
verified: 2026-11-20 @ 57cfcfa (source inspection after Phases 3 to 9; owner ruling 2026-10-12)
entity: app/Models/Conversation.php
---

# Conversation and its paused state

**Type:** object · **Mark:** live · **Checked:** 2026-11-20 @ 57cfcfa · **Where:** `app/Models/Conversation.php:15`

**One sentence.** A `conversations` row is one thread per contact, at most one open at a time; its paused state (`paused_at`, `paused_reason`, `paused_by`) says the assistant has stepped back, why, and who did it.

**Why this shape.** The database itself refuses a second open conversation for one contact (partial unique index, `database/migrations/2026_10_11_131451_create_conversations_table.php:27`). There is one paused state for every way the assistant can step back, so there is one Hand back (D-23); a null `paused_at` means active, and the same column says when. The pause belongs to the conversation, not the customer: a conversation opened after a close starts active (`Conversation.php:56-63`).

## Shape
- Table: `database/migrations/2026_10_11_131451_create_conversations_table.php:18`. Pause columns: `database/migrations/2026_10_12_000008_add_pause_columns_to_conversations_table.php:23-25`. That migration drops and recreates the one-open index (`:44-48`) because SQLite's table rebuild loses its `where` clause.
- Open conversation is found or made in `RecordInbound.php:380` (`:390` creates) when the customer texts; a booking confirmation or reminder for a contact with none open makes one the same way (`OpenConversationForText.php:23`, `:33`, called by `SendBookingText.php:61`, Phase 8 D-30). `ConversationStatus` is `open` or `closed` (`app/Enums/ConversationStatus.php`); closed refuses every send (`OutboundSender.php:247-249`).
- Pause reasons: taken over, asked for a person, owner replied, life safety, reply cap (`app/Enums/PausedReason.php:9-15`). All five go through `PauseConversation::handle` (`PauseConversation.php:34`), from eight call sites: take over `ViewConversation.php:29`, reply cap `RunFrontDesk.php:221`, asked for a person `RunFrontDesk.php:1605`, owner reply `SendOwnerReply.php:92`, and the safety line in four places: after its send or a failed send (`SendLifeSafetyLine.php:119`, and `:144` when a retry finishes a pause an earlier run missed), when the wait for its send lock runs out (`SendLifeSafetyLine.php:72`), and when `RespondToLifeSafety` fails for good (`failed()`, `RespondToLifeSafety.php:86`). Hand back: `HandBackConversation.php:31`. Both re-read the row under a lock and do nothing if it is already in the state asked for.
- Reply cap: at most 6 agent texts an hour and 20 a day per conversation (`config/gate.php:124-127`, counted in `replyCapReached`, `RunFrontDesk.php:1581-1591`); each text counts at the time it went out (`sent_at`, or `created_at` while it is still queued), so a draft the owner sends counts when it was sent. The cap counts the texts the assistant authored that answer a customer text and were queued, sent or delivered (`RunFrontDesk.php:1586`), so the holding, partial holding, complaint, discount, check-in and added-info lines and the code answers added in Phase 02.1 all count. Two kinds are left out, the safety line and the booking confirmation and reminders (`MessageKind::notCountedByReplyCap()`, `MessageKind.php:77`, read at `RunFrontDesk.php:1587`; 10 D-20 amending 02 D-52): the safety line so it can neither use up the cap nor be stopped, and the booking texts (`booking_text`, Agent-authored and automated, `SendBookingText.php:204-210`, Phase 8) because a booking or the clock starts them and they cannot ping-pong with a customer. At the cap the conversation pauses and the owner is alerted (D-52); a complaint or a request for a person in the text alerts first (see the reading, below). A conversation the cap paused still refuses a booking text, with one timeline note (`OutboundSender.php:251-252`, 08 D-26, 10 D-21).
- **A paused or capped conversation still gets the reading (D-45, amended 2026-10-16).** The run makes its one Jev reading of the customer's text before the paused check and the reply cap (`RunFrontDesk.php:204`, `:211`, `:218`), so a paused or capped conversation is read like any other. The reading's safety line still goes out, because the sender lets the safety line through a pause (`RunFrontDesk.php:207-209`, `OutboundSender.php:251-253`). The reading's complaint and its request for a person (or a keyword phrase for one) alert the owner on the folder's own `owner_alert` timing and nothing else is sent to the customer (`alertAboutReading`, `RunFrontDesk.php:1681-1690`, called at `:212` and `:219`); the one "new message while you are handling it" alert follows only when the newest text has no alert yet (`alertAboutPausedBurst`, `RunFrontDesk.php:1567-1574`). Only the safety alerts are forced right away; every other alert is timed, never dropped.
- Take over and Hand back are the owner's two controls (`ViewConversation.php:24` and `:33`); the banner is `ConversationThread::pausedBanner` (`ConversationThread.php:278`).

## Words on this card
"Paused" is per conversation and blocks automated texts only; the owner's own reply and the safety line still pass (`OutboundSender.php:251-253`). "Opted out" is per contact and blocks every text (see [contact-opt-out](../contacts/contact-opt-out.md)). "Conversation" here is our table, never the SDK's `agent_conversations`.

## Connected to
Belongs to a contact (`Conversation.php:41`); has many messages, in the order they happened (`:73`), and intake answers (`:83`); `paused_by` is a user (`:51`). Looks like it but is not: the SDK's `agent_conversations` table (leftover, nothing reads or writes it).

## If you change this
**Hits:** the send path (`OutboundSender::whyBlocked`, `OutboundSender.php:236`), the front desk's early exits (`RunFrontDesk.php:211`, `:218`), the owner's controls and banner, the timeline (`agent_paused` and `agent_resumed` activities), and [answer-inbound](../../processes/answer-inbound.md) at its pause and alert steps (2, 7, 8, 9 and 16 to 18). The reply cap counts every automated kind except those `MessageKind::notCountedByReplyCap()` lists (`MessageKind.php:77`), so a new kind counts unless it is added there; no change to `replyCapReached` is needed. A new pause reason needs a case in `PausedReason` with its banner lead and timeline line.
**Does not hit:** the contact's opt-out (a different column on `contacts`); the message verdict (a held draft does not pause anything, and approving one does not pause either, D-25); the agent file.

## Surfaces
Writers: the eight pause call sites above (the safety line's four included), `HandBackConversation`, `CloseConversation`, `ReopenConversation`, `RecordInbound.php:390`, `OpenConversationForText.php:33`. Readers: `OutboundSender.php:251`, `RunFrontDesk.php:211`, `ViewConversation.php`, `ConversationThread.php`. Humans: the owner. External consumers: none.

## See
`app/Models/Conversation.php`, `app/Actions/PauseConversation.php`; decisions D-23, D-24, D-52 in `.planning/milestones/v1.0-phases/02-text-front-desk/02-CONTEXT.md`.
