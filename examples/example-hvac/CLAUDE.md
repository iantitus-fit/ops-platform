# Example Heating & Air: router

This file only points. It holds no facts. One fact lives in one file; read the file that owns it.

- Who the business is, its hours and where its leads come from: `business/CONTEXT.md`
- What we sell, how long it takes and what we ask first: `business/services/CONTEXT.md`
- How messages should sound, and what is never said: `voice/how-we-talk.md`
- What may be sent without the owner and what waits for review: `rules/CONTEXT.md`
- What each part of the business does and which files it owns: `rooms/`
- Outside systems that work with the business: `connectors/`
- Who answers customers on each channel: `agents/`
- Follow-up sequences (not live yet): `automations/`
- Scripted customers that test a change (not live yet): `tests/`
- What happened with each customer (generated, read-only): `records/CLAUDE.md`

To change the business, edit the file that owns the fact, then run the sync. Never edit anything in `records/`.
