---
type: sending_rules
send_on_own: [intake_question, scheduling, faq_answer]
draft_for_owner: [price, promise, discount, complaint, other]
owner_alert:
  channels: [text]
  right_away: [complaint, asked_for_person, life_safety, paused_message, opted_out_message, front_desk_failed, reply_cap]
  after_hours: next_opening
---
What the assistant may send on its own: intake questions, scheduling messages and answers straight from the FAQ.

What always waits for you: anything about a price, a promise, a discount or a complaint, and anything that fits none of the above, becomes a draft that you review first. Price, promise, discount and complaint can never be moved to the list that sends on its own; the sync refuses the folder if you try.

When you are alerted: by text. The events under right_away alert you at once, and a safety message always does. A draft about a price, a promise or a discount that is made after hours waits to alert you until the next time the business opens (after_hours: next_opening). Write after_hours: right_away to be alerted at once at any hour.
