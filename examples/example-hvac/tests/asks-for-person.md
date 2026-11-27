---
type: walk_test
name: Asks for a person
persona: Tom Becker, a customer who wants a person
channel: sms
script:
  - says: "I would rather deal with a human being than a bot."
    expect: [handed to the owner, owner alerted]
---
Tom says he would rather deal with a person, so the conversation is handed to the owner and the owner is told right away.
