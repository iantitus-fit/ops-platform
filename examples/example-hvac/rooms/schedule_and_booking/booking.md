---
type: booking
step_minutes: 60
drive_buffer_minutes: 30
days_ahead: 14
link_expires_days: 7
reminder_hours_before: [24]
send_window: {start: "08:00", end: "20:00"}
link_line: "Pick a time that works for you here: {link}"
confirmation_line: "You are booked for {service} on {day} at {time}. To change it, reply to this text."
reminder_line: "A reminder: {service} is on {day} at {time}. To change it, reply to this text."
visit_line: "Your visit for {service} is on {day} at {time}. To change it, reply to this text."
---

# How booking works

step_minutes is how far apart the start times are on the booking page. With 60, a customer sees 8:00, 9:00, 10:00 and so on.

drive_buffer_minutes is the time kept free between two visits so the technician can get from one house to the next.

days_ahead is how many days into the future the page offers times.

link_expires_days is how many days a booking link works after the front desk texts it.

reminder_hours_before is how many hours before a visit the customer gets a reminder. With [24] there is one reminder, a day ahead. An empty list sends none.

send_window is the earliest and latest time of day a booking text goes out. A text that would land outside it waits for the window to open.

visit_line is what the front desk adds after its own words when a customer with a visit booked ahead asks about it, so the day and time always come from the booking itself.

link_line, confirmation_line and reminder_line are the exact words the customer gets. The link line must hold {link} once. The confirmation and reminder lines must hold {day} and {time} once each, and may hold {service} once. No exclamation points or dashes, and no other words in braces.
