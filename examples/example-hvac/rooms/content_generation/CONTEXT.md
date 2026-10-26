---
type: room
owns: [rooms/content_generation/brief.md]
reads: [voice/how-we-talk.md, business/profile.md, business/services/*.md, business/offers/*.md, business/hours.md, business/faq.md]
writes_records: none
connector: none
---

# Content generation

This room is where Example Heating & Air's posts and captions will be drafted later. Nothing drafts them yet.

It owns one content brief, rooms/content_generation/brief.md. A business keeps one brief, and the sync refuses a second one wherever it sits. Without the brief the room is empty.

The brief is guidance and nothing in it is enforced. A word or name that must never be used goes in voice/how-we-talk.md under never_say, where code checks every draft.

It reads six files:

- voice/how-we-talk.md for the tone and the never_say words.
- business/profile.md for who the business is.
- business/services/ for the services and their prices.
- business/offers/ for the offer sentences code inserts.
- business/hours.md for hours and closures, when a post depends on the time.
- business/faq.md for the questions customers ask.

It writes nothing to `records/`.

It has no connector and no outside system.
