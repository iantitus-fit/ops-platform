---
type: walk_test
name: Customer asks for the link again
persona: Marcus Lee, a homeowner who lost the booking link
channel: sms
script:
  - says: "My AC is blowing warm air"
    expect: [sent on its own]
  - says: "Not cooling"
    expect: [sent on its own]
  - says: "Over 10 years"
    expect: [sent on its own, carries the booking link]
  - says: "Sorry, I lost that link. Can you send it again?"
    expect: [sent on its own, labeled scheduling, carries the booking link]
  - says: "Can you send the link again?"
    expect: [sent on its own, labeled scheduling, carries the booking link]
---
Marcus loses the booking link and asks for it twice, and each answer must go out on its own with a booking link.
