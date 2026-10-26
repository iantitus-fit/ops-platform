---
type: room
owns: none
reads: []
writes_records: []
connector: annex_feed
---

# Annex room

This room exists only in tests. It is a second pretend room, so the tests can show what happens when two rooms meet.

It owns nothing, so it can never claim a file a real room will own. It reads nothing from other rooms and writes nothing to `records/`. Its one outside system is the annex feed slot.
