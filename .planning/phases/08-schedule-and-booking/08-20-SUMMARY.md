---
phase: 08-schedule-and-booking
plan: 20
subsystem: booking
tags: [laravel, outbound-sender, booking-link, refusal-reasons, wr-03, wr-04]

requires:
  - phase: 08-schedule-and-booking
    provides: "08-17 hidden booking tokens and OutboundSender HIDDEN_BOOKING_LINK; 08-19 SendBookingText once-only send, late grace and kind guard"
provides:
  - "A booking link that reached the customer is never withdrawn by a failure after the send, on the agent path and the owner's approved-draft path"
  - "OutboundSender holds every refusal sentence a caller reads as a public constant; SendBookingText compares by value and keeps no copy"
  - "App\\Enums\\BookingRefusal and BookingRefused::$reason; BookingPage::refuse() switches on the reason"
affects: [phase 8 verification, 08-REVIEW WR-03, 08-REVIEW WR-04, BOOK-02, BOOK-04, BOOK-05]

actuals:
  tokens: 12650
  tasks: 3
  commits: 6
plan_head_before: f7c95f9284f81aea2f8d23512a100fda1a082913
plan_head_after: 1a30898

tech-stack:
  added: []
  patterns:
    - "A caller that decides by an error compares against a constant the thrower owns, or reads a typed reason on the exception, never a copy of the words"
    - "A step that runs after the channel accepted a text reports its failure and never raises it, so a withdraw-on-throw caller cannot withdraw a delivered link"

key-files:
  created:
    - app/Enums/BookingRefusal.php
    - tests/Feature/Booking/RefusalSentencesTest.php
  modified:
    - app/Actions/RunFrontDesk.php
    - app/Actions/SendOwnerReply.php
    - app/Actions/OutboundSender.php
    - app/Actions/SendBookingText.php
    - app/Rooms/ScheduleAndBooking/BookingRefused.php
    - app/Livewire/BookingPage.php
    - tests/Feature/Booking/BookingLinkTest.php
    - tests/Feature/Booking/ApprovedDraftBookingLinkTest.php
    - tests/Feature/Booking/BookingPageTest.php
    - docs/BLUEPRINT.md
    - docs/sprints/sprint-1.1.md

key-decisions:
  - "WR-04 for OutboundSender: public string constants, not a reason enum on its DomainException, because constants are the house pattern there (NOT_AGREED, UNREAD_CUSTOMER_TEXT, APPOINTMENT_NOT_AHEAD) and no caller's catch changes"
  - "WR-04 for BookingRefused: a BookingRefusal enum reason beside field, as Ian asked, set by each of the 13 named constructors; BookingPage switches on it"
  - "WR-03: attachMessage() runs after the guarded send in its own reporting try, in RunFrontDesk through a small recordCarryingMessage() helper and inline in SendOwnerReply; OutboundSender::transmit() reports the post-send card redraw instead of raising it"

requirements-completed: [BOOK-02, BOOK-04]

coverage:
  - id: D1
    description: "WR-03 agent path: a failure recording the carrying message after the send leaves the link live, the earlier link superseded, the reply sent, the failure reported, and the booked alert raised on the carrying message found by meta booking_link_id"
    requirement: BOOK-02
    verification:
      - kind: integration
        ref: "tests/Feature/Booking/BookingLinkTest.php#keeps a delivered link live when recording its message fails (WR-03)"
        status: pass
    human_judgment: false
  - id: D2
    description: "WR-03: a failing card redraw after the text channel accepted a text neither raises out of the send nor withdraws the link"
    requirement: BOOK-02
    verification:
      - kind: integration
        ref: "tests/Feature/Booking/BookingLinkTest.php#keeps the link when the card redraw after the send fails (WR-03)"
        status: pass
    human_judgment: false
  - id: D3
    description: "WR-03 owner path: the same failure on an approved scheduling draft leaves the link live and the owner sees Draft sent"
    requirement: BOOK-02
    verification:
      - kind: integration
        ref: "tests/Feature/Booking/ApprovedDraftBookingLinkTest.php#keeps a delivered link live and says Draft sent when recording its message fails (WR-03)"
        status: pass
    human_judgment: false
  - id: D4
    description: "WR-04: every sentence a caller reads is a public OutboundSender constant, returned or thrown exactly, and SendBookingText keeps no copy; a booking text meeting a reply in flight gives its claim back with no note"
    requirement: BOOK-04
    verification:
      - kind: unit
        ref: "tests/Feature/Booking/RefusalSentencesTest.php#returns its refusal sentences from its own constants"
        status: pass
      - kind: integration
        ref: "tests/Feature/Booking/RefusalSentencesTest.php#gives a booking text back while a reply is in flight (WR-04)"
        status: pass
      - kind: unit
        ref: "tests/Feature/Booking/RefusalSentencesTest.php#keeps no copy of a sender sentence in SendBookingText (WR-04)"
        status: pass
    human_judgment: false
  - id: D5
    description: "WR-04: BookingRefused carries 13 distinct BookingRefusal reasons and BookingPage decides by the reason, proven with refusals whose words were swapped; the page compares no exception message"
    requirement: BOOK-02
    verification:
      - kind: unit
        ref: "tests/Feature/Booking/RefusalSentencesTest.php#names each booking refusal by its reason (WR-04)"
        status: pass
      - kind: integration
        ref: "tests/Feature/Booking/BookingPageTest.php#tells a booking refusal apart by its reason, not its words (WR-04)"
        status: pass
    human_judgment: false

duration: 15min
completed: 2026-11-08
status: complete
---

# Phase 8 Plan 20: A delivered link survives a late failure, and refusals are told apart by constants and reasons Summary

**A booking link that reached the customer is never withdrawn by a failure after the send (agent and approved-draft paths), OutboundSender's refusal sentences are public constants that SendBookingText compares against, and BookingRefused carries a BookingRefusal reason that the booking page switches on.**

## Performance

- **Duration:** 15 min
- **Started:** 2026-11-08T19:19:08Z
- **Completed:** 2026-11-08T19:34:23Z
- **Tasks:** 3 (six code commits: RED then GREEN for each)
- **Files modified:** 13 (2 created)

## Accomplishments

- WR-03 resolved. In `RunFrontDesk::sendPassingReply()` and `SendOwnerReply::sendDraftAskingForLink()` the guarded try now covers minting and sending only; `attachMessage()` runs after it in its own try that reports and never withdraws. A link is taken back only when the send itself threw. `BookAppointment::alertOwner()` already finds the carrying message by `meta.booking_link_id`, and the new test books through the link and checks the alert lands on that message (D-33).
- `OutboundSender::transmit()` reports the card-redraw dispatch after a successful send instead of raising it, so "the send threw" now means "the text did not go out". The send() docblock says so.
- WR-04 resolved. Eight public constants (`OPTED_OUT`, `CLOSED`, `CLOSED_FOR_OWNER`, `PAUSED`, `NO_PHONE`, `REPLY_IN_FLIGHT`, `OWNER_REPLY_IN_FLIGHT`, `TOO_LONG`) hold exactly the words `whyBlocked()`, `takeLock()` and `whyBodyIsRefused()` returned before; those methods now use them. `SendBookingText::answerRefusal()` compares only against `OutboundSender` constants and its two private sentence copies are gone.
- `BookingRefused` takes a `BookingRefusal` reason first (13 cases, snake_case values); every named constructor passes its own, messages and fields unchanged. `BookingPage::refuse()` is now a `match` on the reason and `BookingPage.php` holds no `getMessage()`.
- BLUEPRINT section 1 Built bullet extended with the WR-03 sentence, Affects line kept. Walk-through log line added to `docs/sprints/sprint-1.1.md`.

## Task Commits

1. **Task 1 (tracer): a failure after the send leaves the delivered link live (WR-03)**
   - RED `256c1d3` test(08-20): a failure after the send leaves the delivered link live
   - GREEN `7e92704` fix(08-20): a failure after the send never withdraws a delivered booking link (WR-03)
2. **Task 2: the sender's refusal sentences are its constants (WR-04)**
   - RED `cc346d9` test(08-20): the sender's refusal sentences are its own constants
   - GREEN `33d274b` refactor(08-20): booking texts read the sender's refusals by its constants (WR-04)
3. **Task 3: a booking refusal carries its reason (WR-04)**
   - RED `030c4ee` test(08-20): a booking refusal is told apart by its reason
   - GREEN `1a30898` refactor(08-20): the booking page reads a refusal's reason, not its words (WR-04)

**Plan metadata:** the docs(08-20) commits that follow this file.

## TDD record

- **RED Task 1:** all three new cases failed on the planned assertion or the rethrown exception: both BookingLinkTest cases on `revoked_at` not null (the link was withdrawn), the ApprovedDraft case with the RuntimeException escaping `SendOwnerReply.php:202` (the owner would have seen an error). Semantic assessment: target tests executed and failed for the intended reason.
- **RED Task 2:** 8 of 9 dataset rows failed on `Undefined constant OutboundSender::...`; the ninth (`NOT_AGREED`) already existed. The 'gives a booking text back' case and the 'keeps no copy' case passed before GREEN: the first pins behavior that already held (the old private copy matched), the second only has constants to scan once they exist. Both are regression guards, not RED proof.
- **RED Task 3:** 13 dataset rows and the distinct-reasons case failed on `Undefined property BookingRefused::$reason`, the page-source case on the `getMessage()` it still contained, and the three BookingPageTest rows on `Class App\Enums\BookingRefusal not found`.
- No REFACTOR commits were needed beyond the plan's own refactor-typed GREEN commits.

## Files Created/Modified

- `app/Actions/RunFrontDesk.php` - link taken back only when the send threw; `recordCarryingMessage()` reports a failed attach
- `app/Actions/SendOwnerReply.php` - same shape for an approved draft; returns the sent row
- `app/Actions/OutboundSender.php` - 8 public sentence constants, methods use them; card redraw after a send is reported
- `app/Actions/SendBookingText.php` - compares by `OutboundSender` constants, no private sentence copies
- `app/Enums/BookingRefusal.php` - 13-case string enum
- `app/Rooms/ScheduleAndBooking/BookingRefused.php` - `public readonly BookingRefusal $reason`
- `app/Livewire/BookingPage.php` - `match` on the reason
- `tests/Feature/Booking/RefusalSentencesTest.php` (new), `BookingLinkTest.php`, `ApprovedDraftBookingLinkTest.php`, `BookingPageTest.php` - the new cases
- `docs/BLUEPRINT.md`, `docs/sprints/sprint-1.1.md` - one sentence and one walk-through line

## Decisions Made

- Constants, not a reason enum, for `OutboundSender` (house pattern, no change to how callers catch `DomainException`); a reason enum for `BookingRefused` because Ian asked for the property by name.
- `OPTED_OUT` is a prefix because the sentence ends with the opt-out date, so `SendBookingText` compares it with `str_starts_with`; every other constant is compared whole. `CLOSED` and `NO_PHONE` are compared whole too (only automated sends reach `SendBookingText`, so the owner variants never arrive there; the old prefix checks were looser than needed).

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Reply-lock wait set to 0 seconds, not 1, in the booking-send case**
- **Found during:** Task 2 (RED)
- **Issue:** The 'gives a booking text back' case uses `travelTo`, which freezes the clock. `Cache::lock(...)->block(1)` counts seconds on that clock and never ended, hanging the run.
- **Fix:** `refusalSentencesHoldReplyLock()` sets `gate.agent.lock_wait_seconds` to 0 for every case in the file, with a docblock saying why.
- **Files modified:** tests/Feature/Booking/RefusalSentencesTest.php
- **Committed in:** cc346d9

**2. [Rule 3 - Blocking] Task 3 datasets name the reason by its case name**
- **Found during:** Task 3 (RED)
- **Issue:** A dataset holding `BookingRefusal::...` cases cannot be built while the enum is missing, so Pest failed the whole file at collection (`DatasetMissing`) and hid every existing case. That is a load failure, not a RED.
- **Fix:** The datasets in RefusalSentencesTest and BookingPageTest hold the case name as a string and resolve it with `constant()` inside the test body, so only the new cases fail while the enum is missing.
- **Files modified:** tests/Feature/Booking/RefusalSentencesTest.php, tests/Feature/Booking/BookingPageTest.php
- **Committed in:** 030c4ee

**3. [Plan shape] recordCarryingMessage() helper in RunFrontDesk**
- The plan said to call `attachMessage()` "inside its own try"; in `RunFrontDesk` that try lives in a private `recordCarryingMessage()` method to keep `sendPassingReply()` short. `grep -c 'attachMessage('` still prints 1 in both actions. `SendOwnerReply` has the try inline.

---

**Total deviations:** 2 auto-fixed (both Rule 3, test-only), 1 shape note.
**Impact on plan:** None on behavior or wording; no scope creep.

## Existing tests that had to change

None. Every existing case in BookingLinkTest, ApprovedDraftBookingLinkTest, BookingSendTest, OutboundBookingKindTest and BookingPageTest stays as written and passes.

## Issues Encountered

- The full-suite and Booking runs each print one Pest warning with no detail (`warnings: 1`); it is not from this plan's files (the run exits 0 and every test passes). Left alone as out of scope.

## Verification

- Task 1: `tests/Feature/Booking/BookingLinkTest.php ApprovedDraftBookingLinkTest.php BookingLinkLeakTest.php PageBookingTest.php` 106 passed; `tests/Feature/FrontDesk tests/Feature/Alerts tests/Feature/Filament/DraftReviewTest.php` 852 passed. Tracer verified end-to-end before expanding.
- Task 2: RefusalSentencesTest, BookingSendTest, BookingSendOnceTest, OutboundBookingKindTest, OptOutTest, TakeOverTest 116 passed. Acceptance greps: 8 constants, 0 private string constants in SendBookingText, `OutboundSender::PAUSED` and `OutboundSender::REPLY_IN_FLIGHT` present.
- Task 3: `tests/Feature/Booking tests/Feature/Filament/AppointmentsRelationManagerTest.php tests/Feature/Filament/SchedulePageTest.php tests/Unit` 945 passed. Acceptance greps: 13 cases, `public readonly BookingRefusal $reason`, 0 `getMessage()` in BookingPage.
- `php artisan test --compact`: 3108 passed, 21909 assertions, exit 0.
- `vendor/bin/pint --dirty --format agent`: passed on each task.

## Hand checks

None carried. The plan has no `<human-check>`; the browser behavior of the booking page is unchanged (the taken and failed lines are drawn as before), so nothing is recorded in `.planning/WINDOWS.md`.

## Known Stubs

None.

## Threat Flags

None. No new endpoint, auth path or schema; T-08-84, T-08-85 and T-08-86 are mitigated as planned.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

WR-03 and WR-04 are closed. The send paths and the booking page no longer depend on exception wording or on a post-send step succeeding. Ready for the next gap-closure plan or phase verification.

## Self-Check: PASSED

- FOUND: app/Enums/BookingRefusal.php, tests/Feature/Booking/RefusalSentencesTest.php
- FOUND commits: 256c1d3, 7e92704, cc346d9, 33d274b, 030c4ee, 1a30898 (all ancestors of HEAD)
