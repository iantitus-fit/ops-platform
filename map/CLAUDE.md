# Map: the text front desk

Fence: the nouns a cold reader must understand before changing how an inbound text becomes a reply or a draft. Checked 2026-10-18 @ 1324f24. The source wins over any card.

## By task
- Change when a reply sends or becomes a draft (checks, thresholds, Jev), how a draft is approved, replaced or closed as answered, or how price or offer sentences are put into a text (the pick itself is step 13 of the process card): open `objects/messages/message-and-verdict.md`. Not `rules/sending.md`, which only feeds the rule.
- Change what the assistant does after Take over, a request for a person, or the reply cap: `objects/conversations/conversation-and-pause.md`. Not the contact's opt-out.
- Stop texting someone, or change STOP and START handling: `objects/contacts/contact-opt-out.md`. Not the conversation's pause.
- Change which model answers, what files it loads, or add a channel: `objects/folder/agent-file-and-version.md`. Not the verdict.
- Change what the agent asks or remembers about a customer: `objects/intake/intake-answer.md`. Not the service file alone.
- Follow a text from webhook to reply, or reorder the steps: `processes/answer-inbound.md`.

## Nouns
- Message and its verdict · live · a text row, and the send rule's decision stored on it · `objects/messages/message-and-verdict.md`
- Conversation and paused state · live · one thread per contact, and the assistant stepping back · `objects/conversations/conversation-and-pause.md`
- Agent file and compiled version · live, `escalate_to` half-wired (parked) · who answers a channel, read from the live copy · `objects/folder/agent-file-and-version.md`
- Intake answer · live, two columns half-wired (parked) · one answer to one intake question · `objects/intake/intake-answer.md`
- Contact opt-out · live · `contacts.sms_opted_out_at`, blocks every text · `objects/contacts/contact-opt-out.md`
- Answer an inbound text (process) · live · record, claim, agent, send rule, send or draft · `processes/answer-inbound.md`
- Contact (stub) · live · the customer record · `app/Models/Contact.php:24`
- Owner alert (stub) · live · one text to the owner, about a conversation or about the whole business (`RaiseOwnerAlert::forBusiness()`, the safety_check_down alerts); card comes with Phase 02.2's map · `app/Actions/RaiseOwnerAlert.php:39`, `:91`
- Customer card (stub) · live · markdown rendered from the database for the agent; `records/` on disk is for the owner's AI, never loaded by the agent · `app/Records/CustomerCard.php`
- Timeline entry (stub) · live · `activities` rows, written by every step · `app/Models/Activity.php`

## Ledger
- `agent_conversations`, `agent_conversation_messages` · leftover · the SDK's memory tables, no referrer · shadows the conversation and message cards
- "approval log" · ghost · a comment at `config/gate.php:41`; the real things are `gate:export` and the stored verdict
- `escalate_to` · half-wired, admitted-but-unrouted, parked for Phase 02.2 · agent file card
- `intake_answers.icm_version_id` (parked for Phase 3), `.message_id` (parked, D-33) · writer-only · intake card
- Deviations: `VERIFICATION.md`, a work list for Ian

## Collisions (Ian's rulings, 2026-10-12)
- **agent**: the agent file; the SDK class `FrontDeskAgent`; `MessageAuthor::Agent`; the SDK's `agent_conversations` tables (leftover).
- **conversation**: our `conversations` table, never the SDK's `agent_conversations`.
- **Gate / gate**: Laravel's authorization facade, versus our stored `gate*` names. Prose says "verdict" and "send rule" (`app/SendRule/`, D-48).
- **send rule / sending rules**: the code, versus the owner's file `rules/sending.md`.
- **draft**: status `draft` only; `withdrawn` is a draft that never went out.
- **handled / claimed / dispatched**: `dispatched_at` queued once; `handled_at` claimed into a run; "handled" for a draft the owner ended.
- **label / kind**: what the agent called its reply, versus what the system sent it as.
- **paused / opted out**: per conversation, automated texts only, versus per contact, every text.
- **version**: an `icm_versions` row, versus `gate.version` in a verdict.
- **message**: a `messages` row, versus the SDK's `Message` class (a turn handed to the model).

## Rule
Load this catalog, then one card. Follow at most one Hits edge. Never load the whole `objects/` folder.
