---
type: room
owns: none
reads: [rules/sending.md, rules/replies.md, agents/front-desk.md]
writes_records: none
connector: none
---

# Team

This room is how the owner of Example Heating & Air hears about things that need them and acts on them: the alert texts, the drafts waiting for approval, and who sent, edited or discarded each draft.

It owns no file.

It reads owner_alert in rules/sending.md for which alerts go out at once and which wait for opening time, and owner_name in rules/replies.md. It is meant to read escalate_to in agents/front-desk.md, to know whom to hand a customer to; nothing reads it yet, and today every hand-off goes to the owner. It also reads who resolved each draft from the conversation records the front desk keeps. It writes nothing to records/.

Alerts go out by text through the front desk's text channel. The team has no outside system of its own.
