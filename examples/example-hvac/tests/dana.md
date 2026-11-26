---
type: walk_test
name: Dana end to end
persona: Dana Whitfield, a homeowner whose AC is blowing warm air
channel: sms
start: "2026-12-01 10:00"
script:
  - says: "My AC is blowing warm air"
    expect: [sent on its own, labeled intake_question]
  - says: "Not cooling"
    expect: [sent on its own, labeled intake_question]
  - says: "Over 10 years"
    expect: [sent on its own, labeled scheduling, carries the booking link]
  - books: "2026-12-02 11:00"
    expect: [booked, owner alerted, reminder queued]
  - wait: "1 day"
expect: [card updated, records updated]
---
Dana texts about her AC blowing warm air, answers the two intake questions, picks a time from the booking link and is booked.
