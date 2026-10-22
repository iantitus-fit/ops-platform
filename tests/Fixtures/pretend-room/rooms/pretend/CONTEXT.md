---
type: room
owns: [rooms/pretend/notes/*.md]
reads: [business/profile.md]
writes_records: []
connector: pretend_feed
---

# Pretend room

This room exists only in tests. It is never part of the running product and no client folder has it.

It owns the notes under `rooms/pretend/notes/`. It reads the business profile so it knows the business name and time zone. It writes nothing to `records/`.

Its one outside system is the pretend feed slot, a stand-in that lets the tests prove a room can plug in a connector without a real service behind it.
