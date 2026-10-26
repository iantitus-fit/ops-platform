---
type: room
owns: [business/profile.md, business/hours.md, business/services/*.md, business/faq.md, voice/how-we-talk.md]
reads: none
writes_records: none
connector: none
---

# Company knowledge

This room holds what Example Heating & Air is and how it talks: the profile, the hours, the services, the FAQ and the voice. Those files stay where they are, in `business/` and `voice/`.

It is never empty of business/profile.md, business/hours.md and voice/how-we-talk.md, which the sync requires.

It reads nothing it does not own and writes nothing to `records/`. It has no outside system.
