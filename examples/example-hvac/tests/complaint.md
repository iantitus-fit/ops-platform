---
type: walk_test
name: Complaint about a past visit
persona: Rita Okafor, a customer unhappy with a past visit
channel: sms
script:
  - says: "Your technician never showed up yesterday and I took the day off for it."
    expect: [held for the owner, owner alerted]
---
Rita complains about a visit that did not happen, so the reply waits for the owner and the owner is told right away.
