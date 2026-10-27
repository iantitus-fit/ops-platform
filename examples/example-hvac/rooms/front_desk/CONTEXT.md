---
type: room
owns: [rules/sending.md, rules/replies.md, rules/triage.md, agents/*.md]
reads: [business/profile.md, business/hours.md, business/services/*.md, business/faq.md, voice/how-we-talk.md, business/lead-sources.md, business/offers/*.md]
writes_records: none
connector: text
---

# Front desk

This room answers Example Heating & Air's customer texts. It uses the business's sending rules, set replies, triage and agent file to decide what to say and whether a reply goes out or waits for review. Those files stay where they are, in rules/ and agents/.

It is never empty of rules/sending.md, rules/replies.md and rules/triage.md, which the sync requires.

It reads the business profile, hours, services, FAQ, voice, lead sources and offers to write a reply. It writes nothing to records/.

It texts customers through the text channel. The engine's settings choose the text service, and this folder has no connector file for it yet.
