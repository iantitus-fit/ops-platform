---
type: set_replies
owner_name: Mike
utility_phone: "+13125550188"
holding_reply: "Thanks! {owner_name} answers questions like this himself. I've sent him your message, and he'll get back to you {next_open}."
handoff_reply: "Of course. I've let {owner_name} know you'd like to talk with a person, and he'll reach out {next_open}."
complaint_reply: "I'm sorry about this. I've passed your message straight to {owner_name}, and he'll get back to you {today_or_next_open}."
---
These two texts go out without you. The assistant sends the holding reply when a question is one only you can answer, and the handoff reply when a customer asks for a person.

They can say who you are and when you will answer, and nothing else: no prices, no discounts and no promises, and none of the phrases in the voice file's never_say list. The system checks this every time the folder is synced, and refuses a text that breaks it.

The fill-ins in curly braces are filled in for you: {owner_name} is your name from above, {business_name} comes from the business profile, and {next_open} is the next time the shop is open, worked out from the opening hours.

The phone number is used only in the safety message, to tell a customer who smells gas which utility to call. It is optional.
