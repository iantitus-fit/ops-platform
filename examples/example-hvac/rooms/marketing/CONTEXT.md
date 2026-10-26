---
type: room
owns: [business/lead-sources.md, business/offers/*.md]
reads: [business/profile.md]
writes_records: none
connector: none
---

# Marketing

This room holds where Example Heating & Air's leads come from and what the business is offering: the lead sources in business/lead-sources.md and the offers in business/offers/. Both stay where they are, and the front desk and later the leads room read them.

It is never empty of business/lead-sources.md, which the sync requires.

It reads the business profile for the time zone that decides the day an offer is live. It writes nothing to `records/`. Where lead intake such as the web form sits is the leads room's call.

It has no outside system.
