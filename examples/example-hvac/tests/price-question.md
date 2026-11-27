---
type: walk_test
name: Price question
persona: Ana Ruiz, a homeowner asking what a repair costs
channel: sms
script:
  - says: "How much does it cost to fix an AC?"
    expect: [carries the owner's price sentence for ac-repair, held for the owner]
---
Ana asks what an AC repair costs, so the owner's own price sentence goes out and anything else the assistant wrote about price waits for the owner.
