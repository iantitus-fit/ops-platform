---
type: object
cluster: messages
status: live
verified: 2026-10-12 @ 04f95e7 (working tree, source inspection; owner ruling 2026-10-12)
entity: app/Models/Message.php
---

# Message and its verdict

**Type:** object · **Mark:** live · **Checked:** 2026-10-12 @ 04f95e7 · **Where:** `app/Models/Message.php:42`, `app/SendRule/Verdict.php:15`

**One sentence.** A `messages` row is one text or form post in a conversation, either direction; on an automated reply it also holds the send rule's verdict: `gate_outcome`, `gate_reason` and the `gate` JSON (code name `gate*`, prose name "verdict").

**Why this shape.** One row carries the customer's text, or the agent's words, the owner's decision on them and the delivery, so a draft is never copied or deleted (D-54, D-28). The verdict lives on the message because it is the calibration data later tuning learns from; `gate.version` lets a new shape sit beside the old (D-07, `DecideSendOrDraft.php:49`).

## Shape
- Table and columns: `database/migrations/2026_10_11_131452_create_messages_table.php:18`; claims `database/migrations/2026_10_12_000001_add_dispatch_columns_to_messages_table.php:20-21`; verdict, kind, label `database/migrations/2026_10_12_000005_add_front_desk_columns_to_messages_table.php:21-25`; draft ending `database/migrations/2026_10_12_000006_add_draft_resolution_to_messages_table.php:20-23`. Casts: `Message.php:52`.
- Status: `app/Enums/MessageStatus.php:9-17`. `delivered` is set only by the Twilio status callback (`TwilioStatusCallbackController.php:117`). `withdrawn` is a draft that never went out.
- One live draft per conversation, enforced by the partial unique index at `database/migrations/2026_10_12_000006_add_draft_resolution_to_messages_table.php:26`. A newer draft withdraws the old one as `replaced` (`RunFrontDesk.php:334-341`). A draft ends approved, edited, discarded or replaced (`app/Enums/DraftResolution.php:9-15`).
- `dispatched_at` = queued once (`DispatchInbound.php:38-41`). `handled_at` = claimed into a reply run (`ClaimInboundBurst.php:32-37`), set even if no reply follows: a claimed text that gets no reply because no agent answers texts (`RunFrontDesk.php:113-117`), or because the run failed, is reported to the owner with a note on the timeline (`ReportFrontDeskFailure.php:31`). Say "claimed" for `handled_at`; "handled" only for a draft the owner ended (`ResolveDraft.php:26`).
- `kind` is what the system sent it as (`app/Enums/MessageKind.php:8`); `label` is what the agent called its reply, one of eight words (`app/Enums/ReplyLabel.php:9`). `MessageAuthor::Agent` marks every automated text, fixed ones included (`MessageAuthor.php:8`).
- Verdict: built by `DecideSendOrDraft::decide` (`DecideSendOrDraft.php:35`): label check, length, code checks, then Jev asked once, and Jev can only hold, never rescue (`:21-22`, `:83`). Stored through `Verdict::toAttributes` (`Verdict.php:78`). The classifier is bound at `AppServiceProvider.php:47`; tests swap in `FakeClassifier` (`tests/Pest.php:45`).

## Words on this card
"Draft" means status `draft` only; a draft the owner approves is the same row, no longer a draft. "Send rule" is the code in `app/SendRule/`; "sending rules" is the owner's file `rules/sending.md` (`ActiveConfig.php:98`), an input to it. The SDK class `Laravel\Ai\Messages\Message` (`FrontDeskPrompt.php:13`, imported as `AiMessage`) is a different thing: the turns handed to the model, not a row.

## Connected to
Belongs to a conversation (`Message.php:76`); has many owner alerts (`Message.php:96`); `resolved_by` is a user. Looks like it but is not: the SDK's `agent_conversation_messages` table (leftover, nothing writes it).

## If you change this
**Hits:** the verdict shape is read by `CustomerCard.php:256` and `DraftsWaiting.php:37` (plain reason) and `GateExport.php:52,88-97` (the labeled-data export, reads and prints `gate`); the claim columns by `SweepUndispatched.php:34` and `ClaimInboundBurst.php:28`; status by `ConversationThread.php:122` and `FrontDeskPrompt.php:59`. Process: [answer-inbound](../../processes/answer-inbound.md). Conversation pause state is read by the same send path: [conversation-and-pause](../conversations/conversation-and-pause.md).
**Does not hit:** `owner_alerts` (own table; it only points at a message and is read through `Message::alertFailure`); the agent file; `rules/sending.md` (read when a verdict is made, never written by it); the SDK's `agent_conversation*` tables.

## Surfaces
Writers: `RecordInbound.php:139` (inbound), `OutboundSender.php:184` (queued outbound), `RunFrontDesk.php:346` (draft), `SendFixedReply.php:48,72` and `SendLifeSafetyLine.php:95` (fixed texts), `ResolveDraft.php:82` and `OutboundSender.php:213` (draft ends). Readers: above. Humans: the owner, in the thread and the Drafts widget. External consumers: none today; nothing outside the repo reads the `gate:export` file (Ian, 2026-10-12).

## See
`app/Models/Message.php`, `app/SendRule/Verdict.php`, `app/SendRule/DecideSendOrDraft.php`; decisions D-07, D-28, D-54 in `.planning/phases/02-text-front-desk/02-CONTEXT.md`.
