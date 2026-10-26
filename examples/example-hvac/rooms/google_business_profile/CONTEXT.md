---
type: room
owns: [rooms/google_business_profile/categories.md]
reads: [business/profile.md, business/hours.md, business/services/*.md, business/offers/*.md]
writes_records: none
connector: google_business_profile
---

# Google Business Profile

This room keeps Example Heating & Air's Google listing fed from this folder, so the business shows up for the right searches nearby and the people who find it become customers.

Google ranks local listings on relevance, distance and prominence. Relevance is how well the listing matches what someone searched for, distance is how far the business is from them, and prominence is how well known and well reviewed the business is. A complete, accurate listing helps all three.

The folder is the one source. Google's copy is never written back into the folder, and once the real connector lands the platform will show where the two differ.

It owns one file, rooms/google_business_profile/categories.md: the primary category and up to nine more. It holds no description and no service area, because the business is described in one place, business/profile.md. Without the file the room is empty.

It reads four things, and each feeds a part of the listing:

- business/profile.md feeds the name, the phone, the service area, and the body as the listing description.
- business/hours.md feeds the weekly hours, and its closures as holiday hours.
- business/services/ feeds the services list: each service's name, price policy and price.
- business/offers/ feeds the live offers, for offer posts later.

It writes nothing to `records/`.

Its outside system is Google, through the google_business_profile slot. For now the slot runs on a stand-in that sends nothing.
