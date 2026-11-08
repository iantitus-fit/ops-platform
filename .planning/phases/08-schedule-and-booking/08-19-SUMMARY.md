---
phase: 08-schedule-and-booking
plan: 19
subsystem: customer-communication
tags: [booking-text, send-once, send-window, architecture-test, laravel, pest]

requires:
  - phase: 08-schedule-and-booking
    provides: "08-11 and 08-12: the booking texts, the claim lease and the conditional outcome writes in SendBookingText; 08-18: the revoked old links"
provides:
  - "A pre-send lookup by meta booking_send_id: a row whose message already exists ends on it (sent, failed or stopped partway) and never sends a second text"
  - "markSent(): a failed sent-write is reported and logged critically with the row and message ids, never the text; the row stays claimed so the next sweep recovers it"
  - "SendBookingText::LATE_GRACE_MINUTES (5): a row due inside the send window still sends when reached up to 5 minutes after the close"
  - "drop() leaves 'Reminder text not sent: the visit started first.' (or Confirmation) for a visit not cancelled whose start passed, with no owner alert"
  - "BookingTextKindTest: only OutboundSender, SendBookingText and MessageKind may name the booking text kind"
affects: [08-20, phase-8-uat, secure-phase-8, first-deploy]

actuals:
  tokens: 6600
  tasks: 3
  commits: 5
plan_head_before: e85e659b705e28b03c4483945c39c56d8e421ad5
plan_head_after: 6d1c1cfebe5f15b39a08ffacea1731605e6a3ccc
commits: 5

tech-stack:
  added: []
  patterns:
    - "Recover from a crash between a send and its record by finding the sent thing through a key stored on it (meta booking_send_id), before doing anything new"
    - "Split a swallow-everything write into an or-throw core plus a swallowing wrapper, so one caller can tell a database error from a row another writer ended"
    - "A grace that applies only to a row that came due inside the window, so lateness of the sweep cannot drop it and cannot let a later-due row through"

key-files:
  created:
    - tests/Feature/Booking/BookingSendOnceTest.php
    - tests/Unit/BookingTextKindTest.php
  modified:
    - app/Actions/SendBookingText.php
    - docs/BLUEPRINT.md
    - docs/sprints/sprint-1.1.md
    - .planning/WINDOWS.md

key-decisions:
  - "Grace is 5 minutes (SendBookingText::LATE_GRACE_MINUTES), set in one place; the sweep runs every minute so 5 covers several missed runs; D-29 and ReminderSchedule are unchanged"
  - "A message found still queued is not sent again (it may have reached the customer): the row ends failed with 'The send stopped partway, so it was not sent again.' and a critical log record, with no timeline note"
  - "The drop note is written for any visit not cancelled whose start passed before the row went out; a cancelled visit stays silent (D-16); the unread reason keeps precedence"
  - "drop() reads the visit fresh (appointment()->first()) so the not-ahead refusal path sees a visit changed since the row loaded"

patterns-established:
  - "Pre-send lookup first: a text that went out is recorded even when the visit changed since"

requirements-completed: [BOOK-04]

coverage:
  - id: D1
    description: "WR-01: when the sent-write fails after the text went out, a critical log record names the row and message (never the text), the row stays claimed, and the next sweep after the lease sends nothing more and ends the row sent with that message's id"
    requirement: "BOOK-04"
    verification:
      - kind: integration
        ref: "tests/Feature/Booking/BookingSendOnceTest.php#never sends again when the row could not be marked sent (WR-01)"
        status: pass
    human_judgment: false
  - id: D2
    description: "WR-01: a message already carrying the row's booking_send_id ends the row (sent, failed with its error, or queued as failed with a plain reason and a critical log) and no text goes out again, including from another conversation of the contact"
    requirement: "BOOK-04"
    verification:
      - kind: integration
        ref: "tests/Feature/Booking/BookingSendOnceTest.php#ends the row on a sent message it finds instead of sending (WR-01); #ends the row failed on a failed message it finds, without sending (WR-01); #does not send again after a sender stopped mid-send (WR-01); #finds the message in another conversation of the contact (WR-01, D-30)"
        status: pass
    human_judgment: false
  - id: D3
    description: "WR-02: a row due at or before the window's close sends when reached up to 5 minutes late and moves to the next opening at 6; a row due after the close still moves (D-29)"
    requirement: "BOOK-04"
    verification:
      - kind: integration
        ref: "tests/Feature/Booking/BookingSendOnceTest.php#sends a row due at the close when the sweep is a few minutes late (WR-02); #still moves a row that came due after the close (D-29)"
        status: pass
    human_judgment: false
  - id: D4
    description: "WR-02: a reminder or confirmation the visit overtook leaves one note and no owner alert; a cancelled visit leaves none; the unread reason is kept"
    requirement: "BOOK-04"
    verification:
      - kind: integration
        ref: "tests/Feature/Booking/BookingSendOnceTest.php#notes a reminder the visit overtook (WR-02, D-27); #notes a confirmation the visit overtook (WR-02); #notes a visit marked done before its moved reminder came (WR-02); #writes no note for a cancelled visit, even one that would have started (WR-02, D-16); #keeps the unread reason for a row that waited on an unread customer text (WR-02, D-26)"
        status: pass
    human_judgment: false
  - id: D5
    description: "IN-02: an architecture test lists exactly OutboundSender, SendBookingText and MessageKind as the files under app/ that name the booking text kind, and flags a snippet of each naming style"
    verification:
      - kind: unit
        ref: "tests/Unit/BookingTextKindTest.php#only SendBookingText passes the booking text kind; #flags every way to name the kind; #passes names that only look like it"
        status: pass
    human_judgment: false
  - id: D6
    description: "The CI Postgres leg for BookingSendOnceTest (the meta->booking_send_id JSON read and the DB::beforeExecuting SQL match) after Ian pushes the phase branch"
    verification: []
    human_judgment: true
    rationale: "Needs the pushed branch and the CI pgsql run, which cannot happen inside this local plan; carried to end-of-phase UAT (WINDOWS entry 77)"

duration: 10min
completed: 2026-11-08
status: complete
---

# Phase 8 Plan 19: Booking texts sent once, late grace and the booking kind guard Summary

**A booking text is sent at most once even when its sent-write fails or the sender dies (lookup by meta booking_send_id, loud critical log), a reminder due at the window's close survives a sweep up to 5 minutes late, a reminder the visit overtook leaves a note, and an architecture test lets only SendBookingText pass the booking text kind.**

## Performance

- **Duration:** 10 min
- **Started:** 2026-11-08T18:24:21Z
- **Completed:** 2026-11-08T18:34:00Z
- **Tasks:** 3
- **Files modified:** 6 (SendBookingText, BLUEPRINT, sprint walk-through, WINDOWS, two new test files)

## Accomplishments

- WR-01 (resolved): `SendBookingText::send()` first looks for a message whose `meta.booking_send_id` is the row's id and ends the row on it (sent on the phone, failed with the message's own error, or stopped partway as failed with a plain reason and a critical log record) without composing or sending. `markSent()` reports a failed sent-write and writes `Log::critical` with `booking_send_id` and `message_id` only; the row stays claimed, so the next sweep after the 2-minute lease finds the message and ends the row on it. `end()` now wraps a new `endOrThrow()`.
- WR-02 (resolved), D-29 kept as written: `LATE_GRACE_MINUTES = 5`. `nextOpening()` takes the row and sends a row due at or before today's close when reached up to 5 minutes after it (20:00 sends at 20:00, 20:03, 20:05; moves at 20:06; a row due 20:02 reached 20:04 and a row due 21:00 still move). `ReminderSchedule` is untouched. `drop()` writes "Reminder text not sent: the visit started first." (Confirmation for lead 0) when the visit is not cancelled and its start has passed, with no owner alert; a cancelled visit writes none; the unread reason keeps precedence.
- IN-02 (resolved): `tests/Unit/BookingTextKindTest.php` scans app/ and expects exactly `app/Actions/OutboundSender.php`, `app/Actions/SendBookingText.php`, `app/Enums/MessageKind.php`; four naming styles are flagged and three look-alikes pass, so it is not vacuous.
- BLUEPRINT section 10 extended in three bullets (WR-01, WR-02, IN-02), each keeping its Affects line.

## Task Commits

1. **Task 1 (tracer, WR-01): RED** - `c8b0e84` (test)
   **Task 1: GREEN** - `5bdeccf` (fix)
2. **Task 2 (WR-02): RED** - `3398faa` (test)
   **Task 2: GREEN** - `3549f16` (fix)
3. **Task 3 (IN-02)** - `6d1c1cf` (test; no red step by design, a guard over code that already holds the rule)

**Plan metadata:** the docs(08-19) commit that follows this file.

## TDD Gate Compliance

- Task 1 RED: `BookingSendOnceTest.php` five cases, all failing on the planned assertions (no critical log record on a failed sent-write; a second text going out in the four found-message cases). JUnit report classified `RED_EVIDENCE_OK` / `target_test_failed` for "never sends again when the row could not be marked sent (WR-01)". Semantic assessment: the target ran, its first assertions (one text, row claimed, no outcome) passed and the failure was the critical-count assertion, the intended reason.
- Task 2 RED: seven new cases failed (20:03, 20:05, 19:58-reached-20:04 and the three started-visit notes); the 20:00, 20:06, 20:02-reached-20:04, 21:00, cancelled and unread cases passed as guards, as the plan predicted. Classified `RED_EVIDENCE_OK` for "notes a reminder the visit overtook (WR-02, D-27)"; the record's actual was an empty note list, the planned assertion.
- GREEN commits follow each RED commit. No REFACTOR commit was needed.

## Deviations from Plan

None - plan executed exactly as written. The one-line acceptance check `grep -q 'private function markSent(BookingSend $send, Message $message): void'` returns no match as a basic regex on this macOS grep, but the same string matches with `grep -F`, and the method exists at that exact signature.

## Existing tests changed

None. BookingSendTest, BookingSendRowsTest, ReminderScheduleTest and AppointmentActionsTest pass as written (88 tests in the Task 2 verify run). No existing test failed on the new started-visit note.

## Known Stubs

None.

## Issues Encountered

None. `php artisan test --compact` is green on SQLite: 3076 passed, 21762 assertions.

## Carried hand checks (not run, per project rule)

- After Ian pushes the phase branch, the CI pgsql leg is green for BookingSendOnceTest (the `meta->booking_send_id` JSON read and the `DB::beforeExecuting` SQL match on Postgres). Recorded as `unrun-verify` in `.planning/WINDOWS.md`, entry 77, for end-of-phase UAT.

## Threat Flags

None. T-08-80 (duplicate send), T-08-82 (silent drop) and T-08-83 (kind named elsewhere) are mitigated and tested; T-08-81 (send up to 5 minutes past the close) is accepted as planned and bounded by `LATE_GRACE_MINUTES`.

## Next Phase Readiness

- Ready for 08-20. The send path is the same chokepoint as before: every text still goes through OutboundSender as a booking text.
- The grace number lives in one place (`SendBookingText::LATE_GRACE_MINUTES`) if Ian wants another.

## Self-Check: PASSED

- Files exist: `app/Actions/SendBookingText.php`, `tests/Feature/Booking/BookingSendOnceTest.php`, `tests/Unit/BookingTextKindTest.php`.
- Commits exist on the branch: `c8b0e84`, `5bdeccf`, `3398faa`, `3549f16`, `6d1c1cf`.

---
*Phase: 08-schedule-and-booking*
*Completed: 2026-11-08*
