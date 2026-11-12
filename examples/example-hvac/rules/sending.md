---
type: sending_rules
send_on_own: [intake_question, scheduling, faq_answer]
draft_for_owner: [price, promise, discount, complaint, other]
owner_alert:
  channels: [text]
  right_away: [complaint, asked_for_person, life_safety, paused_message, opted_out_message, front_desk_failed, reply_cap, change_request, check_in]
  after_hours: next_opening
---
What the assistant may send on its own: intake questions, scheduling messages and answers straight from the FAQ.

What always waits for you: anything about a price, a promise, a discount or a complaint, and anything that fits none of the above, becomes a draft that you review first. Price, promise, discount and complaint can never be moved to the list that sends on its own; the sync refuses the folder if you try.

When you are alerted: by text. The events under right_away alert you at once, and a safety message always does. So does the safety check itself: if it stops answering, you are texted at once at any hour (safety_check_down), and you are texted the same way if a text is ever stopped because the safety check did not run on it (safety_check_missing). Neither needs listing under right_away. A customer checking in on a waiting question is on that list to begin with; take check_in off it and a check-in after hours waits until the business opens, like a draft. When a customer who has a visit booked texts about it, you are told at once (change_request), so a move is never promised without the visit being changed. A visit booked on the booking page alerts you while the business is open, and at the next opening after hours (appointment_booked); it is not on the right_away list. A draft about a price, a promise or a discount that is made after hours waits to alert you until the next time the business opens (after_hours: next_opening). Write after_hours: right_away to be alerted at once at any hour.
