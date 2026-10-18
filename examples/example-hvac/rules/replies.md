---
type: set_replies
owner_name: Mike
utility_phone: "+13125550188"
holding_reply: "Thanks. I've passed this to {owner_name} and you're on his list. He'll get back to you {today_or_next_open}."
partial_holding_reply: "I've passed your other question to {owner_name}, and he'll follow up on it."
check_in_reply: "Thanks for checking in. {owner_name} still has your question and you're on his list. He'll get back to you {today_or_next_open}."
added_info_reply: "Got it, I've passed that along to {owner_name} with your question."
complaint_reply: "I'm sorry about this. I've passed your message straight to {owner_name}, and he'll get back to you {today_or_next_open}."
discount_reply: "We like to take care of our customers. I'll make sure {owner_name} knows you asked about a discount and he'll pick up the thread."
handoff_reply: "Of course. I've let {owner_name} know you'd like to talk with a person, and he'll reach out {next_open}."
price_leads:
  same: "Here's that estimate again:"
  higher: "Thanks for clarifying. That puts the estimate higher:"
  lower: "Thanks for clarifying. That brings the estimate down:"
  changed: "Thanks for clarifying. That changes the estimate:"
---
These texts go out without you. Each one is for a moment when a customer is waiting on you, and each says the same thing in its own way: you have their question and they are in line.

What each one is for:

- holding_reply goes out when a question is one only you can answer. It puts the customer in line and says when you will get back to them.
- partial_holding_reply goes out when the assistant answered part of a message and left the rest for you.
- check_in_reply goes out when a customer writes again to ask whether anyone saw their question.
- added_info_reply goes out when a customer adds a detail to a question you already hold.
- complaint_reply goes out when a customer is unhappy. It is always sent, and it is the one place the assistant says sorry.
- handoff_reply goes out when a customer asks to talk with a person.
- discount_reply is optional. It goes out when a customer asks for a discount. Leave it out and the holding reply goes instead. It is the only text here that may use the word discount, and it still cannot offer one.
- price_leads are the short lines that go in front of a price sentence the assistant sends a second time. Which one goes out depends on whether the price stayed the same, went up, went down or changed. You need them as soon as any service file has price sentences, and not before.

Every one of these can say who you are and when you will answer, and nothing else: no prices, no promises, and none of the phrases in the voice file's never_say list. The system checks every text, and every price lead, each time the folder is synced, and refuses one that breaks this.

The fill-ins in curly braces are filled in for you. {owner_name} is your name from above and {business_name} comes from the business profile. The times come from the opening hours, worked out at the moment the text goes out, so they are always true:

- {today_or_next_open} reads "today if possible, or after we open at 8 am tomorrow" while the shop is open, and "after we open at 8 am tomorrow" once it is closed.
- {next_open} reads "when we open at 8 am tomorrow".
- {next_opening} is the opening alone, such as "8 am tomorrow".
- {check_in_time} is a time you promise to check back by. It is off until you add a check_in_hours line (a whole number from 1 to 12), and the sync refuses a text that uses it while that line is missing.

The phone number is used only in the safety message, to tell a customer who smells gas which utility to call. It is optional.
