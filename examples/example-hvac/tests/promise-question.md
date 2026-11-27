---
type: walk_test
name: Promise request
persona: Leo Brandt, a homeowner who wants a promise
channel: sms
script:
  - says: "Can you promise the repair will be done today?"
    expect: [held for the owner]
---
Leo asks for a promise about when the repair will be done, and the reply waits for the owner.
