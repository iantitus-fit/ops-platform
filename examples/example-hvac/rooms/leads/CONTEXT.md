---
type: room
owns: none
reads: [business/lead-sources.md, business/offers/*.md]
writes_records: none
connector: none
---

# Leads

This room is where a new lead at Example Heating & Air becomes a job the business tracks, tied to the customer and the conversation it began in.

When a text comes from a number the business has never seen, or a web form matches no customer by phone or email, this room opens a job for that person once the message is saved, tied to the conversation it began in. A first text that only says STOP opens no job. A text or form from someone the business already knows opens nothing; the owner starts that customer's next job with New job on the customer's page. While this room is off a new lead opens no job, and turning it back on opens none for the leads that came in meanwhile.

It owns no file.

It reads business/lead-sources.md to show where each new lead's job came from, and business/offers/ for later lead follow-ups. Nothing reads the offers yet. It writes nothing to records/.

The web form is shared and owned by no room, so it keeps taking leads while this room is off.

Its dashboard section lists the open jobs, newest first, each with its customer, the day it opened and where it came from. It hides while this room is off, and every job stays on its customer's page and card.

It has no outside system.
