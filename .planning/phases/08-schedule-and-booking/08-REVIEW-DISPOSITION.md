---
phase: 08
review: 08-REVIEW.md
titles: json
findings:
  - id: WR-01
    severity: warning
    disposition: open
    title: "A booking text can be sent twice when the outcome write fails or the process dies after the send"
  - id: WR-02
    severity: warning
    disposition: open
    title: "A reminder due at the send-window boundary falls out of the window on a one-minute delay, and a reminder dropped at visit time leaves no trace"
  - id: WR-03
    severity: warning
    disposition: open
    title: "A booking link that reached the customer is revoked when a step after the send throws"
  - id: WR-04
    severity: warning
    disposition: open
    title: "Refusal handling matches on exception message text copied from other classes"
  - id: WR-05
    severity: warning
    disposition: open
    title: "Customer-facing page ships \"placeholder wording for the example business\" as its privacy notice"
  - id: IN-01
    severity: info
    disposition: open
    title: "Doc comment contradicts the code on drive buffer for hand bookings"
  - id: IN-02
    severity: info
    disposition: open
    title: "The booking-text chokepoint trusts a caller-declared `meta.parts`"
  - id: IN-03
    severity: info
    disposition: open
    title: "The hand-booking dialog forecasts a confirmation text that may not be sent"
  - id: IN-04
    severity: info
    disposition: open
    title: "The Upcoming and Past filters overlap for a span that crosses midnight, and Past can hold a live visit"
  - id: IN-05
    severity: info
    disposition: open
    title: "The booking page's rate-limited notice has no way to retry without a reload"
  - id: IN-06
    severity: info
    disposition: deferred
    title: "The customer card's \"Next appointment\" line depends on the clock, so a rendered card goes stale"
open: 10
total: 11
recorded: 2026-11-07T23:45:44.400Z
---

# Phase 08: Code Review Disposition

| Finding | Severity | Disposition | Source |
|---------|----------|-------------|--------|
| WR-01 | warning | open | Ian, 2026-11-07: fix in the gap plan (08-VERIFICATION.md gap WR-01): before sending, look for a message already carrying meta.booking_send_id and end the row on it; log loudly when the sent_at write fails after a send |
| WR-02 | warning | open | Ian, 2026-11-07: fix in the gap plan (gap WR-02), D-29 kept as written: nextOpening() gets a short grace so a row due inside the window still sends when the sweep reaches it a little late; a moved row that is dropped writes "Reminder text not sent: the visit started first" |
| WR-03 | warning | open | Ian, 2026-11-07: fix in the gap plan (gap WR-03): withdraw the link only when the send itself threw; attachMessage() runs after and only reports its failure |
| WR-04 | warning | open | Ian, 2026-11-07: fix in the gap plan (gap WR-04): refusal reasons as constants or a reason on the exception, as the review suggests |
| WR-05 | warning | open | Ian, 2026-11-07: fix now, in the gap plan (gap WR-05): remove the placeholder sentence and any claim the code does not own (the page and its rate limiter see the visitor's IP address); final wording stays on the attorney list |
| IN-01 | info | open | Ian, 2026-11-07: fix in the gap plan as the review suggests (gap IN-01) |
| IN-02 | info | open | Ian, 2026-11-07: fix in the gap plan (gap IN-02) with an architecture test that only SendBookingText may pass MessageKind::BookingText |
| IN-03 | info | open | Ian, 2026-11-07: fix in the gap plan as the review suggests (gap IN-03) |
| IN-04 | info | open | Ian, 2026-11-07: fix in the gap plan as the review suggests (gap IN-04) |
| IN-05 | info | open | Ian, 2026-11-07: fix in the gap plan as the review suggests (gap IN-05) |
| IN-06 | info | deferred | Phase 9, where the nightly rebuild lives; the rebuild re-renders the card so the Next appointment line follows the clock (Ian, 2026-11-07) |

Dispositions: `open` (recorded, not yet triaged), `fixed`, `skipped`, `deferred`.
Set `deferred` by hand and put the reason in the Source cell; both are preserved. A `|` in the reason is kept as prose and escaped on the next run.
Re-running the gate keeps every row it can. A row the current review no longer reports is kept and its Source cell flagged, so a finding does not leave this record silently. ONE exception: when a finding id is REUSED by a different finding, the earlier decision cannot keep a row — the id is taken — and it is dropped. A RECORDED decision (anything but `open`) is named on the console when that happens; a row still at `open` is replaced silently, because `open` records no decision to lose.
