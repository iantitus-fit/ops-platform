---
type: room
owns: [rooms/schedule_and_booking/booking.md]
reads: [business/hours.md, business/profile.md, business/services/*.md]
writes_records: none
connector: none
---

# Schedule and booking

This room is where Example Heating & Air's visits are booked and kept.

Customers book on a page, from a link the front desk texts for the services marked book_online. The owner can also book by hand, cancel a visit, mark a visit done and block off time.

The times offered come from business/hours.md, the length of the service and this room's booking.md.

It owns one file, rooms/schedule_and_booking/booking.md. Without it the page sends no link, no confirmation and no reminder, while booking by hand still works.

It reads business/hours.md for the days and times the business is open, business/profile.md for who the business is, and business/services/ for each service's length and whether it can be booked online.

A calendar kept somewhere else that owns the schedule means turning this room off. One system owns the schedule, never two.

It writes nothing to `records/`.

It has no outside system.
