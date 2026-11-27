---
type: walk_test
name: Booked customer asks the time
persona: Priya Nair, a homeowner with a visit booked
channel: sms
script:
  - says: "My AC is blowing warm air"
    expect: [sent on its own]
  - says: "Not cooling"
    expect: [sent on its own]
  - says: "Over 10 years"
    expect: [sent on its own, carries the booking link]
  - books: the earliest time offered
    expect: [booked]
  - says: "What time are you coming?"
    expect: [sent on its own, labeled scheduling, carries the visit line]
---
Priya books the earliest visit offered and then asks what time the technician is coming, and the answer must go out on its own with her visit line.
