---
phase: 08-schedule-and-booking
plan: 24
subsystem: front-desk
tags: [visit-line, outbound-sender, booking-file, send-rule, pest]
gap_closure: true
gap_ids: [G-08-4]
status: complete

requires:
  - phase: 08-schedule-and-booking
    provides: "08-02 and 08-09 (the booking_link part and ServiceToBook), D-34 (the change_request alert and its visit condition), 02.1 D-49 (the OutboundSender per-part reading table)"
provides:
  - "A booked customer's scheduling reply goes out as the model's words plus the owner's visit_line, added by code after the verdict (G-08-4 code half)"
  - "App\\Rooms\\ScheduleAndBooking\\VisitAskedAbout: the one place that holds the visit-asked-about condition, the alert description and the rendered visit line, shared with plan 08-25"
  - "ReplyParts::visitLine and the visit_line part name; meta.visit_line_appointment_id on the sent row"
  - "OutboundSender arm: a visit_line part needs a clean reading of the customer's text"
  - "booking.md visit_line, validated by the sync like confirmation_line and reminder_line"
affects: [08-25, phase-08-uat]

actuals:
  tokens: 14500
  tasks: 3
  commits: 6

plan_head_before: 13355092bf359d424a2fc9244e17226f49c1030f
plan_head_after: 7165ce78b7f2b85176de70385c8be7a01f7fed3d

tech-stack:
  added: []
  patterns:
    - "A code-owned slot on ReplyParts, added after a passing verdict and kept out of codeSentences so a draft never carries it"
    - "A room class that holds one condition for both the agent send and the owner's approval (VisitAskedAbout, as ServiceToBook)"

key-files:
  created:
    - app/Rooms/ScheduleAndBooking/VisitAskedAbout.php
    - tests/Feature/Booking/VisitLineTest.php
  modified:
    - app/Actions/RunFrontDesk.php
    - app/Actions/OutboundSender.php
    - app/SendRule/ReplyParts.php
    - app/Rooms/ScheduleAndBooking/BookingSettings.php
    - app/Rooms/ScheduleAndBooking/BookingFileType.php
    - examples/example-hvac/rooms/schedule_and_booking/booking.md
    - tests/Feature/Booking/BookingLinkTest.php
    - tests/Feature/Rooms/BookingFileCheckTest.php
    - docs/SPEC.md
    - docs/BLUEPRINT.md

key-decisions:
  - "No new message kind: the visit line is a part on agent_reply, held to the owner-sentence rule by one OutboundSender arm (02.1 D-49 as amended by 08 D-26); no decision goes back to Ian"
  - "The visit line is considered only when no link was minted, so it never rides with the booking link line"
  - "BookingSettings::visitLine is nullable and renderVisit() returns null for a version compiled before visit_line existed: no line rides and nothing throws (D-03)"

patterns-established:
  - "Pest global helpers in a new test file are prefixed visitLine, and never rely on helpers from another test file"

requirements-completed: [ROOM-12, BOOK-03]

coverage:
  - id: D1
    description: "A booked customer's scheduling reply reaches the customer as the model's words plus 'Your visit for AC repair is on Thursday, November 5 at 10:00 AM. To change it, reply to this text.'; the row records parts [model_words, visit_line] and the visit's id; Jev's inputs hold the model's words alone; no link is minted; the change_request alert is unchanged"
    requirement: "BOOK-03"
    verification:
      - kind: unit
        ref: "tests/Feature/Booking/VisitLineTest.php#adds the owner's visit line to a booked customer's scheduling reply (D-39 amended)"
        status: pass
    human_judgment: false
  - id: D2
    description: "OutboundSender refuses a visit_line part over a complaint reading and sends it with a clean one"
    requirement: "ROOM-12"
    verification:
      - kind: unit
        ref: "tests/Feature/Booking/VisitLineTest.php#holds the visit line to a clean reading at the chokepoint (02.1 D-49)"
        status: pass
    human_judgment: false
  - id: D3
    description: "The line rides only on a scheduling intent or label with a visit ahead; never with a link, never in a held draft, left off past 1,600 characters, absent with the room off, no booking file or an older compiled version"
    requirement: "BOOK-03"
    verification:
      - kind: unit
        ref: "tests/Feature/Booking/VisitLineTest.php (intent or label, no visit ahead, room off / no booking file / older version, held draft, waiting draft, length limit cases)"
        status: pass
    human_judgment: false
  - id: D4
    description: "The sync refuses a booking file whose visit_line is missing, has a wrong or repeated placeholder, an exclamation point, an em dash or passes 300 characters, naming the file and the field and never quoting the owner's words"
    requirement: "ROOM-12"
    verification:
      - kind: unit
        ref: "tests/Feature/Rooms/BookingFileCheckTest.php (eight visit_line refusal rows, read-back, never-quote case)"
        status: pass
    human_judgment: false
---

# Phase 8 Plan 24: The owner's visit line on a booked customer's scheduling reply Summary

**A customer with a visit booked ahead who asks when it is now gets the model's words plus the owner's `visit_line`, rendered by code with the visit's service, day and time after the send rule's verdict; the sender holds the new part to a clean reading and the sync checks the line like the confirmation and reminder lines.**

**Duration:** about 20 minutes | **Started:** 2026-11-11T20:35Z | **Completed:** 2026-11-11T20:55Z | **Tasks:** 3 | **Files:** 12 (2 created, 10 modified)

## What was built

- **Task 1 (tracer).** `VisitAskedAbout` holds the D-34 condition unchanged (reading intent `scheduling` or label scheduling, the earliest booked visit ahead through the contact's own relation), `describe()` (the alert's words, so the D-34 alert text is unchanged) and `line()` (the rendered `visit_line`, null when the room is off, there is no booking file or the version has no line). `RunFrontDesk::sendPassingReply()` adds the line as one more `ReplyParts` slot when no link was minted and the text with it fits 1,600 characters, and records `meta.visit_line_appointment_id`. `RunFrontDesk` never imports `Appointment`. The example booking file gains the owner-worded line.
- **Task 2.** `OutboundSender::whyReadingBlocks()` gains the `'visit_line'` arm, held to `allowsOwnerSentences()` like `booking_link`. The new tests pin the condition: faq_answer label on a scheduling intent carries the line, a faq text does not, no visit ahead (none, cancelled, done, started a minute ago) carries the link line instead, room off, no booking file and an older compiled version send as before and still raise the alert once, a held draft never holds the line, a scheduling reply sent while a price draft waits carries it, 1,601 characters drops it and 1,600 keeps it.
- **Task 3.** `BookingFileType` requires `visit_line` and runs `lineErrors()` (day and time once each, service at most once, no other braces, no `!`, no em dash, 300 characters). SPEC booking row and BLUEPRINT sections 1 and 8 are updated.

## TDD Gate Compliance

RED then GREEN for each task, in order:

| Task | RED commit | GREEN commit |
|------|-----------|--------------|
| 1 | bd7e928 (sent text held the words alone: expected the visit line, got words only) | ef903af |
| 2 | 6f1f648 (complaint-reading case: the sender sent a visit_line part, no refusal; the other 16 cases passed at RED, pinning the condition Task 1 follows, as the plan said they would) | c97b685 |
| 3 | 79b64bb (eight refusal rows and the never-quote case: sync exit 0 instead of 1) | 7165ce7 |

No REFACTOR commit. Semantic assessment of RED: each target failed on the planned assertion, not on a load, syntax or fixture fault. The Task 2 RED test first failed with a Pest type error on a null refusal; the assertion was made explicit (`not->toBeNull` with a message) before the RED commit so the failure reads as the planned behaviour.

## Existing tests changed

- **tests/Feature/Booking/BookingLinkTest.php:245-247** (the case 'adds no link while a visit is booked ahead, and adds one after it is cancelled (D-01)'; the plan named line 246, which is now line 247 after the added comment at 245): the first expectation is re-pointed to the words plus the visit line, with a comment naming 08-24. No other line of the case changed.

No other existing test changed and no other test failed: the whole suite passes (3,164 tests, 22,328 assertions), and tests/Unit/AppointmentWritesTest.php passes with its allowed list unchanged.

## Deviations from Plan

None - plan executed exactly as written. Two small notes, neither a deviation: the Task 1 test file was committed at RED and extended in Task 2 (the plan's split), and `visitLineBookedDanaAsks()` accepts a closure for the reply words (the BookingLinkTest pattern) so the length cases can compute the sign-off after the folder is live.

## Verification

- Task 1 verify: VisitLineTest, BookingLinkTest, ChangeRequestAlertTest, AppointmentWritesTest, ReminderScheduleTest pass (92 tests); `tests/Feature/Booking tests/Feature/Alerts tests/Feature/FrontDesk` passes.
- Task 2 verify: VisitLineTest, OutboundBookingKindTest, ReadingRequiredToSendTest pass (70 tests); the broad command (with tests/Unit) passes (1,751 tests).
- Task 3 verify: BookingFileCheckTest, ScheduleAndBookingBootTest, ScheduleAndBookingSyncTest pass (58 tests); full `php artisan test --compact` passes (3,164 tests).
- All acceptance greps pass. Pint passes.

## Known Stubs

None.

## Threat Flags

None. Mitigations T-08-97 to T-08-101 are covered by the tests above (the visit is read through the texting contact's own relation, the sender arm, meta.parts and meta.visit_line_appointment_id, the sync checks that never quote the line, the nullable setting).

## Unrun hand checks

None carried by this plan. The live walk of G-08-4 (a real booked customer texting 'What time are you coming?') belongs to end-of-phase UAT and is not part of this plan's verification.

## Next

Ready for 08-25 (the owner's approval path reuses `VisitAskedAbout`).

## Self-Check: PASSED

- Created files exist: app/Rooms/ScheduleAndBooking/VisitAskedAbout.php, tests/Feature/Booking/VisitLineTest.php.
- Commits bd7e928, ef903af, 6f1f648, c97b685, 79b64bb, 7165ce7 are on the branch (6 commits measured from the ledger base).
