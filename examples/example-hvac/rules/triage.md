---
type: triage
intent_question: "What does `customer_message` want?"
intents:
  - key: other
    means: "Anything else"
    sendable: false
  - key: scheduling
    means: "Wants to book, move or confirm a visit"
    sendable: true
  - key: intake
    means: "Describes the problem or answers a question about it"
    sendable: true
  - key: faq
    means: "Asks a general question about the business"
    sendable: true
  - key: price_question
    means: "Asks what something costs"
    sendable: false
  - key: complaint
    means: "Is unhappy with work, a visit or the business"
    sendable: false
  - key: emergency
    means: "Describes something urgent or dangerous"
    sendable: false
questions:
  price: "Does `draft_reply` state or suggest a price, a cost or a fee?"
  promise: "Does `draft_reply` commit the business to a specific time, date, visit, price or result? Offering to help, or asking a question, is not a promise."
  discount: "Does `draft_reply` offer a discount, a deal or anything free?"
  complaint: "Is `customer_message` unhappy with something this business already did, such as a past repair, a visit, a bill or how they were treated? A customer describing a problem with their own equipment is not complaining about the business."
  life_safety: "Does `customer_message` describe smelling gas, fire or smoke, a carbon monoxide alarm, sparking, or water reaching outlets, the electrical panel or plugged-in appliances?"
  asks_for_person: "Is `customer_message` asking to talk with a real person instead of the assistant?"
---
These are the questions the safety check asks about every reply the assistant writes, before anything goes out. The check reads two texts: `customer_message` is what the customer wrote, and `draft_reply` is what the assistant wants to send back.

The first question sorts the customer's message into one of the intents above. `other` is listed first on purpose: the checker leans toward the first option when it is unsure, so a doubtful message lands in `other`, and `other` never sends on its own.

Only intents marked `sendable: true` can ever send without you. Everything else waits for you as a draft.

The six yes or no questions catch what must never go out unchecked: a price, a promise, a discount, a complaint, a safety problem, or a customer asking for a person. Reword a question if it does not match how your customers write, but keep the names (`price`, `promise`, `discount`, `complaint`, `life_safety`, `asks_for_person`) as they are. The system looks for them by name.
