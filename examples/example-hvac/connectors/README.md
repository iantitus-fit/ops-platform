# Connectors

A connector file describes one outside system that works with this business. It names three things the sync checks:

- `slot`: the connector slot of the room this file belongs to. The room's folder must be in the business folder.
- `owns`: the kinds of record that outside system is the truth for, as a list, or `none`.
- `reads`: the kinds of record it only copies, as a list, or `none`.

`system`, `direction` and `schedule` are kept as written until the first real connector, and the sync does not check them yet. Two connector files may not own the same kind of record: one owns it and the other can read it. Credentials never go in this folder.
