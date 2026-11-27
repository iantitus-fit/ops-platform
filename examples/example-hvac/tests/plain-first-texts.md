---
type: walk_test
name: Plain first texts
persona: Sam Ortega, a homeowner texting for the first time
channel: sms
script:
  - says: "Hi, my AC isn't cooling. Can someone come take a look?"
    expect: [sent on its own]
  - says: "It's blowing warm air"
    expect: [sent on its own]
  - says: "Over 10 years"
    expect: [sent on its own]
---
Sam texts for the first time in three plain messages, and each reply must go out on its own without waiting for the owner.
