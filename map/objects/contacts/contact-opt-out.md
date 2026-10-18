---
type: object
cluster: contacts
status: live
verified: 2026-10-18 @ 0a48008 (source inspection after quick 261018-dxy; owner ruling 2026-10-12)
entity: app/Models/Contact.php
---

# Contact opt-out

**Type:** object · **Mark:** live · **Checked:** 2026-10-18 @ 0a48008 · **Where:** `contacts.sms_opted_out_at`, `app/Models/Contact.php:47`

**One sentence.** `contacts.sms_opted_out_at` is set when the customer texted STOP (or the carrier said the number opted out); while it is set, nobody may text them.

**Why this shape.** One nullable timestamp is both the yes-or-no and the date, so the owner can be told "Opted out by texting STOP on Oct 13" (`OutboundSender.php:143-146`). It sits on the contact, not the conversation, because a customer's consent outlives any one thread (D-40). The check is in the one outbound path, so no sender can skip it.

## Shape
- Column: `database/migrations/2026_10_12_000009_add_sms_opt_out_to_contacts_table.php:17`; cast and `Contact::isOptedOut()` at `Contact.php:40-50`.
- Set on STOP: `RecordInbound.php:197`; cleared on START: `RecordInbound.php:218` (only for a contact who is opted out). Set when the carrier refuses a send, Twilio error 21610: `OutboundSender.php:523-525` (`recordCarrierOptOut`, called at `:275-278`). Set when a status callback reports it: `TwilioStatusCallbackController.php:97`. The first date is kept (`RecordInbound.php:193`).
- What a keyword is: the provider's own word first, then the same words matched in code (`app/Channels/TextKeywords.php:59`). YES opts back in only for a contact who is opted out, so a plain "yes" to an intake question is never swallowed. A keyword text is stored already dispatched and handled (`RecordInbound.php:140`), so the queue, the sweep and the front desk skip it. Twilio sends its own confirmation; the app records the effect and says nothing (`RecordInbound.php:90-93`, D-39).
- Enforced in `OutboundSender::whyBlocked`, first, for every origin: agent, owner, approved draft and the safety line (`OutboundSender.php:143-147`). The front desk also checks before anything else it does, and alerts the owner to call (`RunFrontDesk.php:164-168`, `alertAboutOptedOutBurst` `:1198-1201`). **No reading is made for an opted-out customer:** the opt-out stop comes right after the keyword safety line and before the one Jev reading of the customer's text, so an opted-out customer's text is not sent to Jev at all and no verdict or reading is stored for it (`RunFrontDesk.php:162-171`, D-45). Every text added in Phase 02.1, the holding, check-in and added-info lines and the code answers, goes through the same sender (`SendFixedReply.php:72`, `SendCombinedReply.php:61`), so an opted-out customer gets none of them.

## Words on this card
"Opted out" is per contact and blocks every text. "Paused" is per conversation and blocks automated texts only ([conversation-and-pause](../conversations/conversation-and-pause.md)). A customer who is opted out can still text the business; the message is recorded and the owner is alerted.

## Connected to
Belongs to the contact record (Phase 1); timeline entries `opted_out` and `opted_in` (`RecordInbound.php:199,220`). Looks like it but is not: `conversations.paused_at`, and the contact's `status` column (`app/Enums/ContactStatus.php`).

## If you change this
**Hits:** `OutboundSender::whyBlocked` (every outbound text), `RunFrontDesk` early exit and its owner alert, `RecordInbound` keyword handling, `TwilioStatusCallbackController`, `TextKeywords`. [answer-inbound](../../processes/answer-inbound.md) steps 1, 6 and 16 touch it. Moving the column or adding a channel needs the same check on the new send path.
**Does not hit:** the conversation's paused state; the message verdict (an opted-out burst never reaches the reading or the agent, so no reading and no verdict is made); the customer card (it prints the `opted_out` and `opted_in` timeline entries, `CustomerCard.php:168-169`, and never reads the column).

## Surfaces
Writers: `RecordInbound`, `OutboundSender`, `TwilioStatusCallbackController`. Readers: `OutboundSender`, `RunFrontDesk`, `RecordInbound`, `TwilioStatusCallbackController`. Humans: the customer, by text; the owner, by the alert. External: Twilio calls `webhooks/text/twilio` and `webhooks/text/twilio/status` (`routes/web.php:22,26`) once the number is live; intended, not live, since the text channel is still the fake one (Ian, 2026-10-12).

## See
`app/Models/Contact.php`, `app/Actions/OutboundSender.php`, `app/Channels/TextKeywords.php`; decisions D-39, D-40 in `.planning/phases/02-text-front-desk/02-CONTEXT.md`.
