---
type: object
cluster: intake
status: live
verified: 2026-10-13 @ 2bac8cb (working tree, source inspection; owner ruling 2026-10-12)
entity: app/Models/IntakeAnswer.php
---

# Intake answer

**Type:** object · **Mark:** live; two columns half-wired (`icm_version_id`, `message_id`: writer-only, intent parked) · **Checked:** 2026-10-13 @ 2bac8cb · **Where:** `app/Models/IntakeAnswer.php:20`

**One sentence.** An `intake_answers` row is one answer a customer gave to one of a service's intake questions, found by the agent in their texts; a correction is a new row, never an update.

**Why this shape.** The current answer is the highest id for a conversation, service and question, and the earlier rows stay as history (D-34), so nothing the customer said is overwritten. The model's answer is untrusted: a question key the service does not ask is dropped, an option that matches none of the question's options is stored as null with the customer's own words kept in `raw_text` (`RecordIntakeAnswers.php:18-22`).

## Shape
- Table: `database/migrations/2026_10_12_000007_create_intake_answers_table.php:19`; lookup index `:30`. Columns: `conversation_id`, `message_id`, `icm_version_id`, `service_slug`, `question_key`, `option` (nullable), `raw_text`.
- Written once per answer, together with an `intake_answered` timeline entry, inside one transaction: `RecordIntakeAnswers.php:56-71`. Called by `RunFrontDesk.php:133` after the agent answers and before the send rule runs, so an answer is stored even when the reply is held.
- The agent proposes answers in its structured output (`AgentReply.php:58`, schema `FrontDeskAgent.php:60`); the question list comes from the live service file (`RecordIntakeAnswers.php:92`).
- Read: `CustomerCard::intakeAnswerLines` (`CustomerCard.php:299`, latest per question) and the timeline arm (`CustomerCard.php:276`). Morph alias `intake_answer` at `AppServiceProvider.php:77`.

## Half-wired columns
- `icm_version_id`: written on every answer (`RecordIntakeAnswers.php:58`), read by no code (relation at `IntakeAnswer.php:50`, no caller). Direction: writer-only. Intent: **parked for Phase 3**, per its migration (`database/migrations/2026_10_12_000007_create_intake_answers_table.php:14-16`, "so Phase 3 can tell which wording of the question the customer answered") and D-33 (Ian, 2026-10-12).
- `message_id`: written on every answer (`RecordIntakeAnswers.php:57`), read by no code (relation at `IntakeAnswer.php:40`, no caller). Direction: writer-only. Intent: **parked**, declared in D-33 ("the source message"): it is the answer's provenance, so a correction row points at the text that corrected it (Ian, 2026-10-12).
- Source for the declaration: D-33, `.planning/phases/02-text-front-desk/02-CONTEXT.md:71`. Intended consumer, not live: Phase 3 booking reads this table (same line).

## Connected to
Belongs to a conversation (`IntakeAnswer.php:30`), a message and an `icm_versions` row (nullable `message_id`, set null on delete). Looks like it but is not: the service file's `intake:` list (the questions, owner-written, in the folder) and `meta` on a message.

## If you change this
**Hits:** `RecordIntakeAnswers`, the customer card (heading and "Intake answers" section), the timeline entry type `intake_answered`, and the agent's output schema if the answer shape changes. [answer-inbound](../../processes/answer-inbound.md) step 8 writes it.
**Does not hit:** the send rule (an answer is stored before the verdict and never read by it); the contact's name (filled by `RunFrontDesk::fillEmptyName`, a different path); the folder's service file (read, never written).

## Surfaces
Writer: `RecordIntakeAnswers`. Readers: `CustomerCard` only. Humans: the owner, through the card and timeline. External consumers: none today; Phase 3 booking is the intended one (D-33).

## See
`app/Actions/RecordIntakeAnswers.php`, `app/Models/IntakeAnswer.php`; decisions D-33, D-34 in `.planning/phases/02-text-front-desk/02-CONTEXT.md`.
