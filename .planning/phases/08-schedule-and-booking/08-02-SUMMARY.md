---
phase: 08-schedule-and-booking
plan: 02
subsystem: safety-and-compliance
tags: [outbound-sender, chokepoint, automatic-texts, booking-text, tdd, pest, postgres]
requires:
  - phase: 08-schedule-and-booking
    provides: "08-01: contacts.automatic_texts_on_at, the scoped Appointment model with isBooked(), Contact::appointments(), ContactFactory::agreedToAutomaticTexts()"
provides:
  - "Contact::hasTextedBusiness() (derived, a first STOP never counts) and mayGetAutomaticTexts()"
  - "D-21 in OutboundSender::whyBlocked(): every Automated send needs a customer who texted or agreed (NOT_AGREED)"
  - "MessageKind::BookingText and the D-26 branch in OutboundSender (refuseBookingText(), BOOKING_TEXT_REFUSED, APPOINTMENT_NOT_AHEAD, UNREAD_CUSTOMER_TEXT)"
  - "ReplyParts::$bookingLine and the booking_link part, held to the owner-sentence rule in whyReadingBlocks()"
affects: [08-12 booking sender, 08-confirmation and reminder plans, 08 booking link plans, customer communication, safety and compliance, operations]
actuals:
  tokens: 10981
  tasks: 3
  commits: 4
plan_head_before: d8bc45528fcc7e11c98b120aac6211e3f1cd68bc
plan_head_after: 0224f30ae374b282c357c600b909e833bf3d7934
tech-stack:
  added: []
  patterns:
    - "A derived fact in place of a stored one: whereNotExists over the activities table, no column to backfill"
    - "A refusal sentence as a class constant so a caller can tell wait from drop by comparing it"
    - "Probe sweep: a temporary backtrace block names every existing test that reaches a new refusal, then is removed"
key-files:
  created:
    - tests/Feature/Booking/AutomaticTextsRuleTest.php
    - tests/Feature/Booking/OutboundBookingKindTest.php
  modified:
    - app/Models/Contact.php
    - app/Actions/OutboundSender.php
    - app/Enums/MessageKind.php
    - app/SendRule/ReplyParts.php
    - tests/Feature/FrontDesk/ProcessInboundTest.php
    - tests/Feature/FrontDesk/ReadingRequiredToSendTest.php
    - docs/BLUEPRINT.md
    - docs/sprints/sprint-1.1.md
key-decisions:
  - "Has-texted is derived from inbound SMS customer messages minus the message that opted the contact out; nothing stored or backfilled (research A7, flagged for Ian)"
  - "D-21 sits after the paused check and before the no-phone check, so closed and paused keep their own reasons first"
  - "A booking text with no meta.appointment_id as an int is refused with the BOOKING_TEXT_REFUSED prefix, never read as a fixed line; missing or empty meta.parts is refused the same way"
  - "The unread check counts any inbound message with handled_at null, web form included, not only SMS"
requirements-completed: [BOOK-04]
coverage:
  - id: D1
    description: "OutboundSender refuses every Automated text to a contact who has not texted the business and whose switch is off; opt-out, closed and paused keep their order; owner and life-safety sends are untouched"
    requirement: BOOK-04
    verification:
      - kind: unit
        ref: "tests/Feature/Booking/AutomaticTextsRuleTest.php (6 tests)"
        status: pass
    human_judgment: false
  - id: D2
    description: "Contact::hasTextedBusiness() is derived: a first STOP never counts and a later START does"
    verification:
      - kind: unit
        ref: "tests/Feature/Booking/AutomaticTextsRuleTest.php#never counts a first STOP, and counts a later START (D-20)"
        status: pass
    human_judgment: false
  - id: D3
    description: "A booking text goes out with no reading only for a booked, still-ahead appointment of this customer, with parts that hold no model words and no unread customer text; refusals tell wait from drop and raise no alert"
    requirement: BOOK-04
    verification:
      - kind: unit
        ref: "tests/Feature/Booking/OutboundBookingKindTest.php (18 tests)"
        status: pass
    human_judgment: false
  - id: D4
    description: "A booking_link part is held to the owner-sentence rule and ReplyParts places the booking line last"
    verification:
      - kind: unit
        ref: "tests/Feature/Booking/OutboundBookingKindTest.php#holds a booking link to the owner-sentence rule (D-01)"
        status: pass
    human_judgment: false
  - id: D5
    description: "The CI pgsql leg is green for the whole suite after Ian pushes the phase branch"
    verification: []
    human_judgment: true
    rationale: "Needs the pushed branch on GitHub Actions; the local Postgres 17 run is recorded below but the CI leg is Ian's to see"
duration: 44min
completed: 2026-11-03
status: complete
---

# Phase 8 Plan 02: The chokepoint changes twice Summary

**OutboundSender now refuses every automatic text to a number that never texted or agreed (D-21), and carries a booking text kind that goes out with no reading only for a booked, still-ahead visit with no unread customer text (D-26), with a booking-link part held to the owner-sentence rule (D-01).**

## Performance

- **Duration:** 44 min
- **Started:** 2026-11-03T22:38:38Z
- **Completed:** 2026-11-03T23:22:25Z
- **Tasks:** 3 (tracer, sweep, booking kind)
- **Files modified:** 10 (2 new tests, 2 existing tests re-pointed)

## Accomplishments

- `Contact::hasTextedBusiness()` asks the messages table each time: an inbound SMS from the customer that is not the very message that opted them out. `mayGetAutomaticTexts()` is that or the owner's switch.
- `whyBlocked()` returns `OutboundSender::NOT_AGREED` for an Automated send to a contact who fails that, after opt-out, closed and paused. Owner replies and the life-safety line never see it. `ProcessInbound` is untouched.
- A probe run of the whole suite found every existing test that reached the new refusal; each was re-pointed by setup only and a second probe run found none.
- `MessageKind::BookingText` with `refuseBookingText()`: names an int appointment id, parts present with no `model_words`, the appointment found through the conversation's own contact, booked and strictly ahead, and no inbound message with `handled_at` null. Three constants separate "malformed" (`BOOKING_TEXT_REFUSED` prefix), "drop" (`APPOINTMENT_NOT_AHEAD`) and "wait" (`UNREAD_CUSTOMER_TEXT`). No owner alert for any of them.
- `ReplyParts::$bookingLine` is placed last and named `booking_link`; `whyReadingBlocks()` holds that part to `allowsOwnerSentences()`.

## Task Commits

1. **Task 1 (tracer): the automatic-texts rule**: RED `fe31ca7` (test), GREEN folded into `83118ca` (feat, together with Task 2's re-pointed tests so the branch is never red after a feat commit)
2. **Task 2: the sweep**: `83118ca` (feat)
3. **Task 3: the booking text kind**: RED `bc78815` (test), GREEN `0224f30` (feat)

**Plan metadata:** the docs commit that carries this file (docs: complete plan)

## TDD Gate Compliance

- **RED 1 (`fe31ca7`):** `AutomaticTextsRuleTest.php`, 6 tests, 4 failing, 2 passing (the switch-on case and the owner/safety-line case, which today's rules already satisfy). The target assertion failed as planned: "Failed asserting that null is identical to 'The customer has not texted the business or agreed to automatic texts.'" (web-form lead, line 45). The other three failures were `Call to undefined method Contact::hasTextedBusiness()` (twice) and `Undefined constant OutboundSender::NOT_AGREED`: the new API did not exist yet, which is the intended reason. Semantic assessment: the target ran and failed for the planned reason; no setup, syntax or fixture fault.
- **GREEN 1 (`83118ca`):** 6/6 pass.
- **RED 2 (`bc78815`):** `OutboundBookingKindTest.php`, 18 tests (datasets expanded), 16 failing, 2 passing (the keep-the-reading-rule test and the clean-reading booking-link case, which today's code already satisfies). Failures were `Undefined constant MessageKind::BookingText` and `Unknown named parameter $bookingLine`, the new surface absent. Semantic assessment as above.
- **GREEN 2 (`0224f30`):** 18/18 pass.
- No REFACTOR commit (none needed). `gsd_run check tdd-red-evidence` was not run: Pest's JSON summary is not one of the formats that classifier reads (TAP, JUnit, swift-testing, unittest), so the semantic inspection above stands in for it.

## Sweep: existing tests re-pointed (Task 2)

The first probe run of the whole suite named three test call sites; the second, after re-pointing, named only `AutomaticTextsRuleTest.php` lines (44, 47, 111). Every re-point is setup only: the contact is built with `Contact::factory()->agreedToAutomaticTexts()`. No assertion line was removed or changed anywhere under `tests/`.

| File:line (changed setup) | Test | Re-point |
|---|---|---|
| tests/Feature/FrontDesk/ProcessInboundTest.php:357 | keeps a failed send on the record and raises it, when the channel will not take the text | `agreedToAutomaticTexts()` on the contact (probe find, line 365) |
| tests/Feature/FrontDesk/ProcessInboundTest.php:395 | says why a conversation cannot be written to (`$open`) | `agreedToAutomaticTexts()` (known hit, probe line 399) |
| tests/Feature/FrontDesk/ProcessInboundTest.php:397 | says why a conversation cannot be written to (`$noPhone`) | `agreedToAutomaticTexts()`, so it still reaches the no-phone check at :401 |
| tests/Feature/FrontDesk/ReadingRequiredToSendTest.php:297 | still raises the safety_check_missing alert when the conversation has no customer text to point at | `agreedToAutomaticTexts()` (probe find, line 86). A real inbound text would defeat that test's subject (no customer text), so the switch is the right re-point |

The helper `textedBusinessBefore()` was not needed and was not added.

Named non-hits, untouched: ProcessInboundTest.php:400 (closed first), TakeOverTest.php:140-141 (paused first), OptOutTest.php:158 (opt-out first). Research candidates the probe did not name, untouched: AnsweredDraftTest, FrontDeskFailureTest, FrontDeskInvariantTest, HoldingReplyTest, LifeSafetyTest and ArchitectureTest. ProcessInboundTest.php:377 ("makes an automated send wait for the lock") also looks like a candidate but takes the lock first and never reaches the check, so it was left alone.

## Files Created/Modified

- `app/Models/Contact.php` - `hasTextedBusiness()` (whereNotExists over opted_out activities) and `mayGetAutomaticTexts()`
- `app/Actions/OutboundSender.php` - `NOT_AGREED`, the D-21 branch, the booking text branch, `refuseBookingText()`, `kindOf()`, the `booking_link` arm, class docblock
- `app/Enums/MessageKind.php` - `BookingText`
- `app/SendRule/ReplyParts.php` - `bookingLine`, `booking_link` in `partNames()`
- `tests/Feature/Booking/AutomaticTextsRuleTest.php`, `tests/Feature/Booking/OutboundBookingKindTest.php` - new
- `tests/Feature/FrontDesk/ProcessInboundTest.php`, `ReadingRequiredToSendTest.php` - setup re-pointed
- `docs/BLUEPRINT.md` - three Built bullets (sections 10 and 1); `docs/sprints/sprint-1.1.md` - walk-through line (plan 08-02)

## Decisions Made

- Has-texted is derived, not stored (research A7), as planned; this is the point flagged for Ian.
- The unread check counts any inbound message with `handled_at` null, not only SMS, so a web-form lead's unread message also makes a booking text wait.
- `kindOf()` was pulled out of `partsOf()` so the enum-or-string handling lives in one place; it also now returns null for a value that is neither, where the old inline line would have returned it.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] The sweep named more than the known hit in ProcessInboundTest.php**
- **Found during:** Task 2
- **Issue:** The acceptance criterion "`git diff -U0 | grep -c '^+[^+]'` prints 2" for ProcessInboundTest.php assumed only :395 and :397 change. The probe found a second test in the same file (:357, a failed send) and one in ReadingRequiredToSendTest.php (:297). The plan expects probe finds, so this is the sweep working, but that one criterion prints 3.
- **Fix:** Re-pointed both by setup only, named them by file and line above and in the feat commit body. The criterion's purpose (no assertion changed) holds: the removed-assertion grep finds nothing.
- **Files modified:** tests/Feature/FrontDesk/ProcessInboundTest.php, tests/Feature/FrontDesk/ReadingRequiredToSendTest.php
- **Committed in:** 83118ca

**2. [Rule 3 - Blocking, tooling] Two acceptance greps need `-F` on macOS**
- The criteria `grep -q 'private function refuseBookingText(Conversation $conversation, array $attributes): void'` and `grep -q 'public ?string $bookingLine = null'` print no match under macOS BSD grep (the `$` is read as an anchor), though both lines exist. They pass with `grep -qF`. No code change.

---

**Total deviations:** 2 (one setup-only re-point beyond the named hit, one tooling note). **Impact on plan:** none on behavior; the D-21 sweep did its job.

## Issues Encountered

- The first probe run also failed `ArchitectureTest` ("file_put_contents not allowed in App\Actions"), caused by the probe itself. It was removed before the commit; the final suite is green.
- The Postgres run takes about 23 minutes against about 5 on SQLite.

## Test Runs

- **SQLite, full suite** (`php artisan test --compact`): 2500 passed, 0 failed, 18258 assertions, 1 warning (the known browser-plugin rmdir warning).
- **Postgres 17, full suite** (container `ops-pg-test` on port 55432, as docs/SETUP.md section 5 shows): 2500 passed, 0 failed, 18258 assertions, 1 warning. No Postgres-only failure. The container was removed afterwards.

## Authentication Gates

None.

## Unrun human check (not a pause)

The plan's `<human-check>`, the CI pgsql leg green for the whole suite once Ian pushes the phase branch, was left unrun. It is recorded as an `unrun-verify` entry in `.planning/WINDOWS.md`. The local Postgres 17 run above already passed.

## Laravel concepts met for the first time

- **A derived fact.** "Has this customer texted us" is not saved anywhere. `hasTextedBusiness()` asks the messages table each time, using `whereNotExists`, a subquery that says "and there is no opt-out entry pointing at this message". Nothing can drift out of step, and nothing needs filling in for old rows.
- **A refusal that tells wait from drop.** `OutboundSender` throws a `DomainException` whose sentence is a class constant. The caller (plan 08-12) compares the thrown sentence with `UNREAD_CUSTOMER_TEXT` to try again in a minute, or with `APPOINTMENT_NOT_AHEAD` to give up, and the sender never needs to know who called it.
- **Pest datasets.** `->with([...])` runs one test body once per entry, so each way a booking text can be refused is its own named case with one body to read.

## Known Stubs

None.

## Threat Flags

None. The only new surface is the existing outbound path; the plan's threat register (T-08-05 to T-08-09) is covered by the tests named above.

## Next Phase Readiness

Plans that send a confirmation or reminder can call `OutboundSender::send()` with `kind => MessageKind::BookingText` and `meta => ['appointment_id' => int, 'parts' => [...]]`, and branch on the two constants. Plans that add a booking link build a `ReplyParts` with `bookingLine`. Later plans that send an Automated text to a contact built by a factory must use `agreedToAutomaticTexts()` or a real inbound text.

---
*Phase: 08-schedule-and-booking*
*Completed: 2026-11-03*

## Self-Check: PASSED

- Created files exist: tests/Feature/Booking/AutomaticTextsRuleTest.php, tests/Feature/Booking/OutboundBookingKindTest.php.
- Commits exist on this branch: fe31ca7, 83118ca, bc78815, 0224f30.
- Probe gone: no `debug_backtrace` in OutboundSender.php, no storage/framework/d21-probe.txt.
