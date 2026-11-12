---
phase: 08
review: 08-REVIEW.md
titles: json
findings:
  - id: IN-13
    severity: info
    disposition: deferred
    title: "\"The visit changed after the line was shown\" is also said when the visit did not change"
  - id: IN-14
    severity: info
    disposition: skipped
    title: "The WR-10 reason can only be reached by a downgrade the platform cannot do, so the real case stays silent"
  - id: IN-15
    severity: info
    disposition: fixed
    title: "The IN-10 note sits under a heading that says the sync cannot check it"
  - id: WR-09
    severity: warning
    disposition: fixed
    title: "The visit line sent on approval is not checked against the line the owner saw"
  - id: WR-10
    severity: warning
    disposition: fixed
    title: "A booking file synced before `visit_line` existed tells the owner \"online booking is turned off\""
  - id: IN-10
    severity: info
    disposition: fixed
    title: "Making `visit_line` required refuses an existing owner's whole folder with no upgrade note"
  - id: IN-11
    severity: info
    disposition: fixed
    title: "The visit lookup has no tie-break, so the line and the customer card can name different visits"
  - id: IN-12
    severity: info
    disposition: fixed
    title: "The Booking section's \"each sentence is true\" claim is stronger than the code"
  - id: WR-06
    severity: warning
    disposition: fixed
    title: "A booking row whose sender died after queuing the message ends as failed with no note anywhere the owner reads"
  - id: WR-07
    severity: warning
    disposition: fixed
    title: "The front-desk model is never told what `/book/[link]` in its own history means, so the new hold fires on ordinary follow-ups"
  - id: WR-08
    severity: warning
    disposition: fixed
    title: "A failure after the text went out, other than recording the carrying message, still withdraws a live link"
  - id: IN-07
    severity: info
    disposition: open
    title: "The late-send grace does not check that the row came due today or after the window opened"
  - id: IN-08
    severity: info
    disposition: open
    title: "The fake channel's docblock names the dev chat as a reader of `sent()`, and the dev chat does not read it"
  - id: IN-09
    severity: info
    disposition: open
    title: "The revoke migration cannot tell a link sent before the fix from one sent after it"
  - id: WR-01
    severity: warning
    disposition: fixed
    title: "A booking text can be sent twice when the outcome write fails or the process dies after the send"
  - id: WR-02
    severity: warning
    disposition: fixed
    title: "A reminder due at the send-window boundary falls out of the window on a one-minute delay, and a reminder dropped at visit time leaves no trace"
  - id: WR-03
    severity: warning
    disposition: fixed
    title: "A booking link that reached the customer is revoked when a step after the send throws"
  - id: WR-04
    severity: warning
    disposition: fixed
    title: "Refusal handling matches on exception message text copied from other classes"
  - id: WR-05
    severity: warning
    disposition: fixed
    title: "Customer-facing page ships \"placeholder wording for the example business\" as its privacy notice"
  - id: IN-01
    severity: info
    disposition: fixed
    title: "Doc comment contradicts the code on drive buffer for hand bookings"
  - id: IN-02
    severity: info
    disposition: fixed
    title: "The booking-text chokepoint trusts a caller-declared `meta.parts`"
  - id: IN-03
    severity: info
    disposition: fixed
    title: "The hand-booking dialog forecasts a confirmation text that may not be sent"
  - id: IN-04
    severity: info
    disposition: fixed
    title: "The Upcoming and Past filters overlap for a span that crosses midnight, and Past can hold a live visit"
  - id: IN-05
    severity: info
    disposition: fixed
    title: "The booking page's rate-limited notice has no way to retry without a reload"
  - id: IN-06
    severity: info
    disposition: deferred
    title: "The customer card's \"Next appointment\" line depends on the clock, so a rendered card goes stale"
open: 3
total: 25
recorded: 2026-11-12T00:00:00.000Z
---

# Phase 08: Code Review Disposition

| Finding | Severity | Disposition | Source |
|---------|----------|-------------|--------|
| IN-13 | info | deferred | Ian, 2026-11-12: deferred to Phase 9, next to IN-06. The fix when picked up: a reason that is true in every case, "the line changed after it was shown", plus the BLUEPRINT line to match. Not now, because a code change would make the passed verification stale (increment review 2026-11-12, 8269ff1^..6b762eb) |
| IN-14 | info | skipped | Ian, 2026-11-12: closed, no change. No folder can be in that state: the example folder is resynced at version 2 with visit_line, and every folder synced from now on must carry it (IN-10) (increment review 2026-11-12, 8269ff1^..6b762eb) |
| IN-15 | info | fixed | Ian, 2026-11-12: fixed now: the visit_line note moved in docs/SETUP.md under its own heading, "## 13. Upgrading a business folder", as its first bullet, with the line's rules ({day} and {time} once each, {service} at most once) (increment review 2026-11-12, 8269ff1^..6b762eb) |
| WR-09 | warning | fixed | Ian, 2026-11-11: fixed test first as ruled: the approve step passes down the visit line on screen, taken from the locked visitLine, and the send leaves the line off with the reason "the visit changed after the line was shown" (VisitAskedAbout::VISIT_CHANGED, in meta.visit_line_left_off and the owner's notice) when the line it would add now differs |
| WR-10 | warning | fixed | Ian, 2026-11-11: fixed test first as ruled: a booking file synced before visit_line gets its own reason, "the booking file has no visit_line yet, so add it and sync again" (VisitAskedAbout::NO_VISIT_LINE), in whyNoLine() and the send's fallback; "online booking is turned off" stays for the room off or no booking file, and the pinned test row now expects the new reason |
| IN-10 | info | fixed | Ian, 2026-11-11: fixed as ruled, docs only: visit_line stays required, and docs/SETUP.md now says a booking file written before the visit line needs visit_line added before the next sync |
| IN-11 | info | fixed | Ian, 2026-11-11: fixed test first as ruled: VisitAskedAbout::find() orders by starts_at then id, as CustomerCard does, so two visits at the same minute give the line, the alert and the card the same visit |
| IN-12 | info | fixed | Ian, 2026-11-11: fixed test first as ruled: both Booking section constants say the line is added when the reply is sent as it is, and that when it is held for the owner the owner decides whether it is added; the two pinned tests in BookingSectionTest check those sentences |
| WR-06 | warning | fixed | Ian, 2026-11-09: fixed test first as ruled: the queued-then-died branch writes the D-27 note ("Reminder/Confirmation text may not have reached the customer: the send stopped partway.") and handle()'s catch ends the row on an existing message by meta.booking_send_id (78be6c8, 422621f) (not in the current review) |
| WR-07 | warning | fixed | Ian, 2026-11-09: fixed at the source, no new prompt rule: FrontDeskPrompt shows an earlier text that carried a booking link as its words and "(The system added the booking link here.)", in the history and in the customer card the model reads; the DecideSendOrDraft hold is unchanged (5aec7f7, ec20f2a, e7aba5c) (not in the current review) |
| WR-08 | warning | fixed | Ian, 2026-11-09: fixed test first as ruled: OutboundSender reports a failed lock release and never raises it, so once the channel accepted a text nothing withdraws its link or tells the owner it failed (ad5b653, 2302d1c) (not in the current review) |
| IN-07 | info | open | - (not in the current review) |
| IN-08 | info | open | - (not in the current review) |
| IN-09 | info | open | - (not in the current review) |
| WR-01 | warning | fixed | Ian, 2026-11-07: fix in the gap plan (08-VERIFICATION.md gap WR-01): before sending, look for a message already carrying meta.booking_send_id and end the row on it; log loudly when the sent_at write fails after a send (not in the current review); fixed in the gap plans, confirmed by the 2026-11-08 re-review (Ian, 2026-11-09) (not in the current review) |
| WR-02 | warning | fixed | Ian, 2026-11-07: fix in the gap plan (gap WR-02), D-29 kept as written: nextOpening() gets a short grace so a row due inside the window still sends when the sweep reaches it a little late; a moved row that is dropped writes "Reminder text not sent: the visit started first" (not in the current review); fixed in the gap plans, confirmed by the 2026-11-08 re-review (Ian, 2026-11-09) (not in the current review) |
| WR-03 | warning | fixed | Ian, 2026-11-07: fix in the gap plan (gap WR-03): withdraw the link only when the send itself threw; attachMessage() runs after and only reports its failure (not in the current review); fixed in 08-20, re-review 2026-11-08 found it partly fixed, the remainder tracked and fixed as WR-08 (Ian, 2026-11-09) (not in the current review) |
| WR-04 | warning | fixed | Ian, 2026-11-07: fix in the gap plan (gap WR-04): refusal reasons as constants or a reason on the exception, as the review suggests (not in the current review); fixed in the gap plans, confirmed by the 2026-11-08 re-review (Ian, 2026-11-09) (not in the current review) |
| WR-05 | warning | fixed | Ian, 2026-11-07: fix now, in the gap plan (gap WR-05): remove the placeholder sentence and any claim the code does not own (the page and its rate limiter see the visitor's IP address); final wording stays on the attorney list (not in the current review); fixed in the gap plans, confirmed by the 2026-11-08 re-review (Ian, 2026-11-09) (not in the current review) |
| IN-01 | info | fixed | Ian, 2026-11-07: fix in the gap plan as the review suggests (gap IN-01) (not in the current review); fixed in the gap plans, confirmed by the 2026-11-08 re-review (Ian, 2026-11-09) (not in the current review) |
| IN-02 | info | fixed | Ian, 2026-11-07: fix in the gap plan (gap IN-02) with an architecture test that only SendBookingText may pass MessageKind::BookingText (not in the current review); fixed in the gap plans, confirmed by the 2026-11-08 re-review (Ian, 2026-11-09) (not in the current review) |
| IN-03 | info | fixed | Ian, 2026-11-07: fix in the gap plan as the review suggests (gap IN-03) (not in the current review); fixed in the gap plans, confirmed by the 2026-11-08 re-review (Ian, 2026-11-09) (not in the current review) |
| IN-04 | info | fixed | Ian, 2026-11-07: fix in the gap plan as the review suggests (gap IN-04) (not in the current review); fixed in the gap plans, confirmed by the 2026-11-08 re-review (Ian, 2026-11-09) (not in the current review) |
| IN-05 | info | fixed | Ian, 2026-11-07: fix in the gap plan as the review suggests (gap IN-05) (not in the current review); fixed in the gap plans, confirmed by the 2026-11-08 re-review (Ian, 2026-11-09) (not in the current review) |
| IN-06 | info | deferred | Phase 9, where the nightly rebuild lives; the rebuild re-renders the card so the Next appointment line follows the clock (Ian, 2026-11-07) (not in the current review) |

Dispositions: `open` (recorded, not yet triaged), `fixed`, `skipped`, `deferred`.
Set `deferred` by hand and put the reason in the Source cell; both are preserved. A `|` in the reason is kept as prose and escaped on the next run.
Re-running the gate keeps every row it can. A row the current review no longer reports is kept and its Source cell flagged, so a finding does not leave this record silently. ONE exception: when a finding id is REUSED by a different finding, the earlier decision cannot keep a row — the id is taken — and it is dropped. A RECORDED decision (anything but `open`) is named on the console when that happens; a row still at `open` is replaced silently, because `open` records no decision to lose.
