---
type: walk_test
name: Discount question
persona: Grace Kim, a first-time customer asking about deals
channel: sms
start: "2026-12-01 10:00"
script:
  - says: "Do you have any deals for first-time customers?"
    expect: [held for the owner]
---
Grace asks about deals for first-time customers, and the reply waits for the owner. This walk names a start inside the first visit promotion, which runs to the end of December, so it gives the same answer on any day the sync runs.
