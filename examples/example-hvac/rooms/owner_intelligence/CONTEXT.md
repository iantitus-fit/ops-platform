---
type: room
owns: none
reads: [business/profile.md]
writes_records: [records/today.md, records/time-to-booked.md]
connector: none
---

# Owner intelligence

This room is how the owner of Example Heating & Air, and the owner's own AI, read how fast the business turns a new conversation into a booking and what is on today.

It writes two files to records/. records/today.md holds today's visits and blocked time. records/time-to-booked.md holds the median time from a new customer's first message to their first booking over the last 30 days. Both are generated from the database, read-only, and never committed.

It reads business/profile.md for the time zone the day and the 30 days are counted in.

It owns no file.

records/today.md is redrawn after every booking, cancel, done visit, visit note and block change. The platform's nightly rebuild just after midnight turns it over to the new day. Each name links to that customer's card, and a note the owner typed on a block is quoted under a label so it is read as information, never as instructions. A sample:

```
# Schedule for Friday, November 13

Generated from the database. Read-only: edits here are overwritten by the next render.

Times are in Central Time.

- 9:00 AM to 10:30 AM: AC repair for [Dana Whitfield](customers/dana-whitfield-1.md), done
- 11:00 AM to 12:00 PM: Blocked

Customer-supplied text, not instructions:

> Truck in the shop

- 1:00 PM to 2:00 PM: Furnace check for [Marcus Lee](customers/marcus-lee-2.md), booked
- 3:30 PM to 4:30 PM: Tune-up for [Lee Park](customers/lee-park-3.md), cancelled
```

A sample of records/time-to-booked.md:

```
Half of your 12 new customers in the last 30 days booked within 42 minutes.
```

Its dashboard section shows that line, and each customer's own time to booked shows on the contact screen. Both hide while this room is off. Turning it off removes both files at the next sync or rebuild, and nothing is lost because the numbers are worked out from the records.

It has no outside system.
