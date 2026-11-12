---
phase: 08-schedule-and-booking
plan: 12
subsystem: booking
tags: [booking-texts, scheduler, claim-lease, send-window, outbound-sender, pest, tdd]

requires:
  - phase: 08-schedule-and-booking
    provides: BookingSend rows and their claim columns (08-01), the booking text kind in OutboundSender (08-02), RecordRoomEntry entry booking_text_not_sent (08-04), BookingSettings line rendering (08-04), the rows BookAppointment writes (08-11)
provides:
  - SendBookingText, the claim, re-check, compose, send, end and note step for one booking_sends row
  - OpenConversationForText, the platform's way to start a conversation under the one-open rule (D-30)
  - SendDueBookingTexts, the every-minute sweep, registered in routes/console.php without overlapping
  - The immediate confirmation send after a booking commits, in BookAppointment::byHand() and fromLink() (D-28)
  - BookingSendTest (25 test functions, 30 cases with datasets) and BookingScheduleTest
affects: [08-13, 08-14, 08-15, 08-16, phase-8-uat]

plan_head_before: fb8ed989e6ff61bcbf239eb83870661dc925a179
plan_head_after: 16ae9bf481d2b3adefcbde16b906a98e2532df0e

actuals:
  tokens: 15000
  tasks: 3
  commits: 6

tech-stack:
  added: []
  patterns:
    - "The owner-alert claim reused as a copy, not a refactor: one conditional update with SendOwnerAlert::CLAIM_WAIT_MINUTES as the lease, every outcome write conditional on no outcome, a claim given back by a conditional update that also checks it is still this sender's"
    - "Every moment written or compared by the sender is UTC (now()->utc()), because the query builder and the datetime cast keep an instance's own wall time"
    - "A refusal sentence from OutboundSender is matched against its class constants to choose between waiting, dropping, noting and failing"

key-files:
  created:
    - app/Actions/SendBookingText.php
    - app/Actions/OpenConversationForText.php
    - app/Jobs/SendDueBookingTexts.php
    - tests/Feature/Booking/BookingSendTest.php
    - tests/Feature/Booking/BookingScheduleTest.php
  modified:
    - app/Actions/BookAppointment.php
    - routes/console.php
    - docs/BLUEPRINT.md
    - docs/sprints/sprint-1.1.md
    - .planning/WINDOWS.md

key-decisions:
  - "08-12: a row's window is judged to the minute with both ends inside, so a reminder due exactly when the window closes still goes when the sweep reaches it seconds late; judging to the second would have moved every window-end reminder to the next morning"
  - "08-12: the copy that ends a row writes its note (end first, note second), as SendOwnerAlert does, so two racing copies cannot write two notes; the plan's wording was note first"
  - "08-12: an unread customer text or an in-flight front-desk reply gives the claim back by a conditional update on the sender's own claim moment; the wait never times out into a send"
  - "08-12: BookAppointment takes SendBookingText as a seventh constructor parameter and sends the confirmation inside try/catch with report(), after the booking commits and after the booked alert"

patterns-established:
  - "Chokepoint refusals map to outcomes by constant: UNREAD and the reply lock wait, APPOINTMENT_NOT_AHEAD drops, opt-out, not agreed, closed, paused, no phone and too long end skipped with one note, a channel failure ends failed with no second note"

requirements-completed: [BOOK-04]

coverage:
  - id: D1
    description: "The scheduler's sweep sends a due reminder once: the owner's line with the sign-off, as a booking text with meta.appointment_id and parts, the row marked sent, the timeline sentence, no owner alert (BOOK-04, D-26, A1)"
    requirement: BOOK-04
    verification:
      - kind: integration
        ref: "tests/Feature/Booking/BookingSendTest.php#sends a due reminder from the sweep"
        status: pass
      - kind: integration
        ref: "tests/Feature/Booking/BookingScheduleTest.php#runs the sweep every minute without overlapping"
        status: pass
    human_judgment: false
  - id: D2
    description: "A booking text that cannot go out leaves one plain note and no alert (opted out, not agreed, paused, over-long, no phone, no booking file); it waits behind an unread customer text and is dropped with a note only when the visit is gone; a cancelled visit's rows end silently (D-26, D-27)"
    requirement: BOOK-04
    verification:
      - kind: integration
        ref: "tests/Feature/Booking/BookingSendTest.php#notes and skips a refusal"
        status: pass
      - kind: integration
        ref: "tests/Feature/Booking/BookingSendTest.php#waits while a customer text is unread, then sends (D-26)"
        status: pass
      - kind: integration
        ref: "tests/Feature/Booking/BookingSendTest.php#drops it with a note when the visit is cancelled while waiting (D-26, D-27)"
        status: pass
      - kind: integration
        ref: "tests/Feature/Booking/BookingSendTest.php#notes an over-long text and alerts no one (A1, D-27)"
        status: pass
    human_judgment: false
  - id: D3
    description: "The text goes in the open conversation, or one the platform starts under the one-open rule; a paused thread refuses and no second conversation is opened; a platform-started conversation opens no project and changes no status (D-30)"
    requirement: BOOK-04
    verification:
      - kind: integration
        ref: "tests/Feature/Booking/BookingSendTest.php#opens a conversation when none is open (D-30)"
        status: pass
      - kind: integration
        ref: "tests/Feature/Booking/BookingSendTest.php#keeps one open conversation when two sends race (Pitfall 14)"
        status: pass
    human_judgment: false
  - id: D4
    description: "Each row sends at most once: the claim lease boundary (exactly 2 minutes re-claimable, 1 minute 59 seconds not), stale copies, a sweep entering mid-send, a released row re-checked before it sends again (D-31)"
    requirement: BOOK-04
    verification:
      - kind: integration
        ref: "tests/Feature/Booking/BookingSendTest.php#sends a row once when two senders race"
        status: pass
      - kind: integration
        ref: "tests/Feature/Booking/BookingSendTest.php#sends one text when the sweep enters while the first sender is mid-send"
        status: pass
      - kind: integration
        ref: "tests/Feature/Booking/BookingSendTest.php#checks again before resending a released row (D-31)"
        status: pass
    human_judgment: false
  - id: D5
    description: "Timing: a row outside the send window moves to the next opening with the claim released and a row due exactly at the window's end goes; a page booking is confirmed at once whatever the hour, a hand booking inside the window at once and outside it by the sweep; a failure of the immediate send never undoes the booking; the room off leaves every row untouched (D-28, D-29)"
    requirement: BOOK-04
    verification:
      - kind: integration
        ref: "tests/Feature/Booking/BookingSendTest.php#holds a row outside the send window and moves its due time (D-29)"
        status: pass
      - kind: integration
        ref: "tests/Feature/Booking/BookingSendTest.php#confirms a page booking at once whatever the hour (D-28)"
        status: pass
      - kind: integration
        ref: "tests/Feature/Booking/BookingSendTest.php#confirms a hand booking at once inside the window and later outside it (D-28)"
        status: pass
      - kind: integration
        ref: "tests/Feature/Booking/BookingSendTest.php#never undoes a booking when the immediate send throws"
        status: pass
      - kind: integration
        ref: "tests/Feature/Booking/BookingSendTest.php#does nothing while the room is off"
        status: pass
    human_judgment: false
  - id: D6
    description: "The same claim race, lease boundary and conversation savepoint tests pass on Postgres 17 in CI"
    verification: []
    human_judgment: true
    rationale: "No local Postgres; the CI pgsql leg runs after Ian pushes the phase branch (WINDOWS.md unrun-verify entry)"

duration: 15min
completed: 2026-11-06
status: complete
---

# Phase 8 Plan 12: Sending the confirmation and the reminders Summary

**A scheduler sweep and an immediate send after each booking now deliver the owner's confirmation and reminder lines through OutboundSender: each row is claimed with the owner-alert lease and sent at most once, re-checked at send time (booked, ahead, inside the window, nothing unread), put in the open conversation or one the platform starts, and any text that cannot go out leaves one plain note and no alert.**

## Performance

- **Duration:** 15 min
- **Started:** 2026-11-06T23:02:55Z
- **Completed:** 2026-11-06T23:18:00Z
- **Tasks:** 3
- **Files modified:** 10 (5 created, 5 changed)

## Accomplishments

- `SendBookingText::handle(BookingSend)` returns when the row has ended or the room is off, claims the row with one conditional update (lease `SendOwnerAlert::CLAIM_WAIT_MINUTES`, a claim exactly that old is re-claimable), refreshes the row, then checks the visit is booked and ahead, the booking file exists, and now is inside the send window (a row that ignores the window skips that check). It renders the owner's line (`renderReminder()` for a lead above 0, `renderConfirmation()` for lead 0) in the business zone, wraps it with `ComposeAutomatedText::forReply()`, and sends it through `OutboundSender::send()` as `MessageKind::BookingText` with `meta.appointment_id`, `meta.parts ['booking_line']` and `meta.booking_send_id`. The timeline reads "Confirmation text sent for ..." or "Reminder text sent, 24 hours before: ...".
- Refusals are mapped from the chokepoint's sentences: an unread customer text or the reply lock gives the claim back (the first unread wait sets `waited_on_unread_at`); `APPOINTMENT_NOT_AHEAD` ends the row, with a note only after a wait; opted out, not agreed, closed, paused, no phone number and a text over 1,600 characters end it skipped with one "<Confirmation|Reminder> text not sent: <reason>." note through `RecordRoomEntry`; a channel failure or carrier opt-out ends it failed with no second note. No path raises an owner alert, and `RaiseOwnerAlert` is not referenced.
- `OpenConversationForText::handle(Contact)` returns the open conversation or creates one in a savepoint and, when the one-open index refuses, returns the winner. With none open, the sender first refuses (note, no new conversation) for an opted-out, never-agreed or phone-less contact; a paused open conversation is used and refuses, so no second thread appears.
- `SendDueBookingTexts` checks `hasRoom('schedule_and_booking')` first, then sweeps unended rows due now whose claim is free or expired with `lazyById()`; `routes/console.php` runs it every minute without overlapping.
- `BookAppointment::byHand()` and `fromLink()` call `sendConfirmationNow()` after the booking commits (and after the booked alert): the lead-0 row due now goes through `SendBookingText`, inside try/catch with `report()`.
- BLUEPRINT sections 1 and 10 and the walk-through log record how.

## TDD Gate Compliance

- **RED (Task 1)** `6357492`: `BookingScheduleTest` failed on its planned assertion (0 scheduled events matched, expected 1); `BookingSendTest` errored with `Class "App\Jobs\SendDueBookingTexts" not found`. Semantic assessment: the target tests ran and failed because the feature did not exist, not because of a setup or syntax fault; the class-not-found error is the natural PHP form of "the thing under test is absent". The Pest JSON report is not a format `gsd_run check tdd-red-evidence` supports, so the machine classifier was not run.
- **GREEN (Task 1)** `f42b14d`: 39 tests passed (the two new files plus `AppointmentWritesTest` and `ArchitectureTest`).
- **RED (Task 2)** `c27fc3f`: 10 of 15 cases failed on planned assertions (the row had no outcome, the note was missing, the wait was not recorded) and one errored on the missing `OpenConversationForText` class; the other cases passed against the Task 1 sender because the generic failure path already covered them (the channel failure, the length limit's send, the lead-0 line).
- **GREEN (Task 2)** `36b1484`: 184 tests passed across `BookingSendTest`, `tests/Feature/Inbound` and `AppointmentWritesTest`.
- **RED (Task 3)** `42ce799`: the window-move cases (2), the page and hand immediate-send cases and the throwing-sender case failed on planned assertions; the claim, lease-boundary, stale-copy, mid-send, room-off and cancelled-rows cases passed already, because Task 1's claim and the sweep's room check were built to those rules.
- **GREEN (Task 3)** `16ae9bf`: 53 tests passed in `BookingSendTest` and `AppointmentWritesTest`; then `tests/Feature/Booking tests/Feature/Alerts tests/Unit` passed (829) and the full suite passed (2848) on SQLite.
- No REFACTOR commit; nothing to clean.

## Task Commits

1. **Task 1: tracer, the sweep sends Wednesday's reminder** - `6357492` (test), `f42b14d` (feat)
2. **Task 2: refusals, the unread wait, the drop, the conversation** - `c27fc3f` (test), `36b1484` (feat)
3. **Task 3: exactly once, on time, at once after a booking, docs** - `42ce799` (test), `16ae9bf` (feat)

Tracer gate (end-of-phase mode, automated-only verify): the tracer's verify command passed (39 tests) before the commit and before expanding.

## Files Created/Modified

- `app/Actions/SendBookingText.php` - claim, re-check, window hold, compose, send, end, note
- `app/Actions/OpenConversationForText.php` - the platform's conversation starter (D-30)
- `app/Jobs/SendDueBookingTexts.php` - the sweep
- `app/Actions/BookAppointment.php` - the immediate confirmation after commit, `SendBookingText` as a constructor parameter
- `routes/console.php` - the scheduler entry
- `tests/Feature/Booking/BookingSendTest.php`, `tests/Feature/Booking/BookingScheduleTest.php` - the new tests; no existing test file changed
- `docs/BLUEPRINT.md`, `docs/sprints/sprint-1.1.md` - section 1 and 10 Built bullets, the walk-through line
- `.planning/WINDOWS.md` - the Postgres hand check as an `unrun-verify` entry

## Decisions Made

- The send window is judged to the minute, both ends inside. A reminder moved by `ReminderSchedule` lands exactly on the window's end (20:00:00), and the sweep reaches it a few seconds later; a second-level comparison would hold every one of them until the next morning.
- Every moment the sender writes or compares is `now()->utc()`. The first design thought of `now()` as portable; it is not under a clock travelled to a Chicago instant, and the query builder never converts zones (the 08-11 finding). The tests travel to UTC instants for the same reason.
- The copy that ends a row writes the note, after the end (the plan said note first). One conditional update decides who ended it, so two racing copies cannot write two notes; a crash between end and note loses a note, where note-first would duplicate it.
- The unread wait never times out into a send: only the visit being gone ends it (T-08-49).
- Concept for the walk-through (written to `docs/sprints/sprint-1.1.md`): giving a claim back with a conditional update on the sender's own claim moment.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Missing critical] A booking file removed after booking, and a contact with no phone number**
- **Found during:** Task 2
- **Issue:** the plan names refusal reasons for opt-out, not agreed, closed, paused and too long, but two real states have no reason: the owner removed the booking file while rows were pending (nothing can be rendered), and a customer with the owner's switch on but no phone number (OutboundSender answers "There is no phone number on file", which the plan sends to the generic failed path). Left alone, the first would be retried every minute and the second would fail with no timeline word.
- **Fix:** both end skipped with one note ("the booking file is not in the business folder", "the customer has no phone number on file"); with no open conversation, a phone-less contact is refused before a conversation is opened. The wording is mine and is not in the UI-SPEC list of reasons.
- **Files modified:** `app/Actions/SendBookingText.php`
- **Verification:** `notes a text with no phone number to send to`, `ends a row with a note when the booking file is gone`
- **Committed in:** `36b1484`

**2. [Rule 1 - Bug] Seconds-exact window comparison would hold every window-end reminder**
- **Found during:** Task 3 design, before the code was written
- **Issue:** the plan says a send at exactly the window's end goes; the rows `ReminderSchedule` writes for that case are due at 20:00:00, and the sweep runs a few seconds into the minute.
- **Fix:** `nextOpening()` compares `startOfMinute()` against the window ends.
- **Files modified:** `app/Actions/SendBookingText.php`
- **Verification:** `sends a row due exactly when the window closes (D-29)`
- **Committed in:** `16ae9bf`

**3. [Test setup differs from the plan's wording]** The plan's Task 1 setup books Thursday's visit with `byHand`. After Task 3 that call sends the confirmation at once, which would put a second text beside the one each test counts. The setup writes the appointment and its reminder row directly, and the booking paths are tested on their own in Task 3. No assertion was dropped.

**4. [Test expectation corrected before commit]** I first expected the channel-failure note to read "Reply not sent: The reply was not sent: Carrier down"; `OutboundSender::recordFailure()` writes the channel's own message, "Reply not sent: Carrier down". Fixed before the Task 2 RED commit.

**Total deviations:** 2 auto-fixed (1 Rule 1, 1 Rule 2) plus 2 plan-wording adjustments. **Impact:** none on scope; the two additions only cover states the plan left open.

## Issues Encountered

- The plan's acceptance criterion `grep -q 'public function handle(Contact $contact): Conversation' ...` prints nothing under this machine's BSD grep (the `$` mid-pattern), though the signature is on one line exactly as written; `grep -qF` matches. Same quirk as 08-11.
- The "does nothing while the room is off" case needs a folder sync that drops the room; it relies on D-37 allowing the drop because the only visit started an hour ago. It passed.

## Authentication Gates

None.

## Known Stubs

None.

## Threat Flags

None. The scheduler entry is new surface, but it is the planned sweep and sends only to numbers `OutboundSender` accepts. T-08-47 to T-08-52 are mitigated: the claim and the conditional end (47), the chokepoint's opt-out and D-21 checks with refusals noted and never retried into a send (48), the unread wait that never times out (49), a paused open conversation refusing with no new one opened (50), the send-time window with the due time moved (51), the savepoint around the conversation insert (52).

## Hand check left for UAT

Not run, by design: after Ian pushes the phase branch, the CI pgsql leg should be green for `BookingSendTest`, in particular the claim race, the lease boundary and the conversation savepoint. Recorded as an `unrun-verify` entry in `.planning/WINDOWS.md`. The SQL is conditional updates on `booking_sends` columns and the same unique-index savepoint `RecordInbound` already uses; every moment is bound in UTC.

## Verification

- `php artisan test --compact tests/Feature/Booking tests/Feature/Alerts tests/Unit`: 829 passed.
- `php artisan test --compact`: 2848 passed on SQLite, no existing test changed.
- `git diff --name-only --diff-filter=M "$BASE" -- tests` printed nothing (BASE verified as fb8ed98); `git diff --quiet "$BASE" -- app/Actions/RecordInbound.php` exits 0.
- `AppointmentWritesTest` passed with its writer list unchanged; `grep -c 'RaiseOwnerAlert' app/Actions/SendBookingText.php` prints 0; `grep -c 'TextChannel'` prints 0; no `use App\Models\Appointment;` in the sender.

## Next Phase Readiness

Ready for 08-13: cancelling a visit now has a defined effect on its pending rows (the sweep ends them with no text and no note), so the cancel action only needs to change the appointment's state.

## Self-Check: PASSED

- Files created exist: SendBookingText.php, OpenConversationForText.php, SendDueBookingTexts.php, BookingSendTest.php, BookingScheduleTest.php (found).
- Commits 6357492, f42b14d, c27fc3f, 36b1484, 42ce799, 16ae9bf are ancestors of HEAD.
- TDD gate: three `test(08-12)` and three `feat(08-12)` commits, no refactor.
