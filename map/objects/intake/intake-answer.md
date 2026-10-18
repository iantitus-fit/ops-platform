---
type: object
cluster: intake
status: live
verified: 2026-10-18 @ 1324f24 (source inspection after the 02.1 close-out fixes, 2026-10-18; owner ruling 2026-10-12)
entity: app/Models/IntakeAnswer.php
---

# Intake answer

**Type:** object · **Mark:** live; two columns half-wired (`icm_version_id`, `message_id`: writer-only, intent parked) · **Checked:** 2026-10-18 @ 1324f24 · **Where:** `app/Models/IntakeAnswer.php:20`

**One sentence.** An `intake_answers` row is one answer a customer gave to one of a service's intake questions, found by the agent in their texts; a correction is a new row, never an update.

**Why this shape.** The current answer is the highest id for a conversation, service and question (`RecordIntakeAnswers::currentFor`, `RecordIntakeAnswers.php:93-100`), and the earlier rows stay as history (D-34), so nothing the customer said is overwritten. The model's answer is untrusted: a question key the service does not ask is dropped, an option that matches none of the question's options is stored as null with the customer's own words kept in `raw_text` (`RecordIntakeAnswers.php:18-22`).

## Shape
- Table: `database/migrations/2026_10_12_000007_create_intake_answers_table.php:19`; lookup index `:30`. Columns: `conversation_id`, `message_id`, `icm_version_id`, `service_slug`, `question_key`, `option` (nullable), `raw_text`.
- Written once per answer, together with an `intake_answered` timeline entry, inside one transaction: `RecordIntakeAnswers.php:56-71`. Called by `RunFrontDesk.php:238` after the agent answers and before the send rule runs, so an answer is stored even when the reply is held; the rows it stores are handed on to the price pick in the same run (`RunFrontDesk.php:241-242`).
- The agent proposes answers in its structured output (`AgentReply.php:79`, kept by `AgentReply.php:200-224`, schema `FrontDeskAgent.php:60`); the question list comes from the live service file (`RecordIntakeAnswers.php:35`, `:108-124`).
- **Answers now pick the price sentence (Phase 02.1).** The newest row per question (`currentFor`, `RecordIntakeAnswers.php:93`) is what `PriceSentencePicker::pickKeyed` reads for each question a service's price sentences are keyed on (`PriceSentencePicker.php:188-224`, the read at `:191`). With no row for a question, the price is pending and the customer is asked first (`PricePick::needsAnswers`, `PricePick.php:46`; the run remembers it in `meta.price_pending`, `RunFrontDesk.php:359`, and reads it back next run at `:1067-1081`). An answer that matches none of the question's options is asked again once, then not, counting only misses since the newest answer that matched (`PriceSentencePicker.php:199`, `unmatchedCount` `:231-243`, the match bound at `:237`, Phase 2.1 D-06, WR-06); after the second miss, and for any combination the owner has no sentence for, the owner's default sentence goes out, or the price holds if the file has none (`pickDefault`, `:250-259`). The model is told to ask the first unanswered question and to re-ask an unmatched one once, listing the options (`FrontDeskPrompt.php:41`, `:58`).
- **A correction re-sends.** A new row for a keying question makes the picker run again for that service even though the customer did not ask the price again (`pickPrices`, `RunFrontDesk.php:1036-1046`, `PriceSentencePicker.php:54`, `:128-137`). If it picks a different sentence than the customer was last told, it goes out at once behind the owner's lead for how the price moved: higher, lower or changed (`PriceSentencePicker.php:144-157`, `direction` `:105-120`, `FixedReplies::priceLead` `FixedReplies.php:83`); an identical sentence is not repeated unless the customer asks again (`PriceSentencePicker.php:152-154`).
- Read: `CustomerCard::intakeAnswerLines` (`CustomerCard.php:298`, latest per question), the timeline arm (`CustomerCard.php:165`, `intakeAnswerParts` `:275`) and the price picker (above). Morph alias `intake_answer` at `AppServiceProvider.php:77`.

## Half-wired columns
- `icm_version_id`: written on every answer (`RecordIntakeAnswers.php:58`), read by no code (relation at `IntakeAnswer.php:50`, no caller). Direction: writer-only. Intent: **parked for Phase 3**, per its migration (`database/migrations/2026_10_12_000007_create_intake_answers_table.php:14-16`, "so Phase 3 can tell which wording of the question the customer answered") and D-33 (Ian, 2026-10-12).
- `message_id`: written on every answer (`RecordIntakeAnswers.php:57`), read by no code (relation at `IntakeAnswer.php:40`, no caller). Direction: writer-only. Intent: **parked**, declared in D-33 ("the source message"): it is the answer's provenance, so a correction row points at the text that corrected it (Ian, 2026-10-12).
- Source for the declaration: D-33, `.planning/phases/02-text-front-desk/02-CONTEXT.md:71`. Intended consumer, not live: Phase 3 booking reads this table (same line).

## Connected to
Belongs to a conversation (`IntakeAnswer.php:30`), a message and an `icm_versions` row (nullable `message_id`, set null on delete). Looks like it but is not: the service file's `intake:` list (the questions, owner-written, in the folder) and `meta` on a message.

## If you change this
**Hits:** `RecordIntakeAnswers`, the customer card (heading and "Intake answers" section), the timeline entry type `intake_answered`, the price picker (`PriceSentencePicker`, which keys sentences on these rows, so renaming a question key or its options changes which sentence goes out), and the agent's output schema if the answer shape changes. [answer-inbound](../../processes/answer-inbound.md) step 12 writes it and step 13 reads it.
**Does not hit:** the send rule's verdict (an answer is stored before the verdict, and neither the code checks nor Jev read it; only the price picker does, before the verdict); the contact's name (filled by `RunFrontDesk::fillEmptyName`, a different path); the folder's service file (read, never written).

## Surfaces
Writer: `RecordIntakeAnswers`. Readers: `CustomerCard` and `PriceSentencePicker`. Humans: the owner, through the card and timeline. External consumers: none today; Phase 3 booking is the intended one (D-33).

## See
`app/Actions/RecordIntakeAnswers.php`, `app/Models/IntakeAnswer.php`, `app/SendRule/PriceSentencePicker.php`; decisions D-33, D-34 in `.planning/phases/02-text-front-desk/02-CONTEXT.md`.
