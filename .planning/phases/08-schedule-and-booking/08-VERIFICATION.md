---
phase: 08-schedule-and-booking
verified: 2026-11-12T22:00:00Z
status: passed
score: 11/11 must-haves verified
covered_files: [".planning/phases/08-schedule-and-booking/08-01-PLAN.md", ".planning/phases/08-schedule-and-booking/08-01-SUMMARY.md", ".planning/phases/08-schedule-and-booking/08-02-PLAN.md", ".planning/phases/08-schedule-and-booking/08-02-SUMMARY.md", ".planning/phases/08-schedule-and-booking/08-03-PLAN.md", ".planning/phases/08-schedule-and-booking/08-03-SUMMARY.md", ".planning/phases/08-schedule-and-booking/08-04-PLAN.md", ".planning/phases/08-schedule-and-booking/08-04-SUMMARY.md", ".planning/phases/08-schedule-and-booking/08-05-PLAN.md", ".planning/phases/08-schedule-and-booking/08-05-SUMMARY.md", ".planning/phases/08-schedule-and-booking/08-06-PLAN.md", ".planning/phases/08-schedule-and-booking/08-06-SUMMARY.md", ".planning/phases/08-schedule-and-booking/08-07-PLAN.md", ".planning/phases/08-schedule-and-booking/08-07-SUMMARY.md", ".planning/phases/08-schedule-and-booking/08-08-PLAN.md", ".planning/phases/08-schedule-and-booking/08-08-SUMMARY.md", ".planning/phases/08-schedule-and-booking/08-09-PLAN.md", ".planning/phases/08-schedule-and-booking/08-09-SUMMARY.md", ".planning/phases/08-schedule-and-booking/08-10-PLAN.md", ".planning/phases/08-schedule-and-booking/08-10-SUMMARY.md", ".planning/phases/08-schedule-and-booking/08-11-PLAN.md", ".planning/phases/08-schedule-and-booking/08-11-SUMMARY.md", ".planning/phases/08-schedule-and-booking/08-12-PLAN.md", ".planning/phases/08-schedule-and-booking/08-12-SUMMARY.md", ".planning/phases/08-schedule-and-booking/08-13-PLAN.md", ".planning/phases/08-schedule-and-booking/08-13-SUMMARY.md", ".planning/phases/08-schedule-and-booking/08-14-PLAN.md", ".planning/phases/08-schedule-and-booking/08-14-SUMMARY.md", ".planning/phases/08-schedule-and-booking/08-15-PLAN.md", ".planning/phases/08-schedule-and-booking/08-15-SUMMARY.md", ".planning/phases/08-schedule-and-booking/08-16-PLAN.md", ".planning/phases/08-schedule-and-booking/08-16-SUMMARY.md", ".planning/phases/08-schedule-and-booking/08-17-PLAN.md", ".planning/phases/08-schedule-and-booking/08-17-SUMMARY.md", ".planning/phases/08-schedule-and-booking/08-18-PLAN.md", ".planning/phases/08-schedule-and-booking/08-18-SUMMARY.md", ".planning/phases/08-schedule-and-booking/08-19-PLAN.md", ".planning/phases/08-schedule-and-booking/08-19-SUMMARY.md", ".planning/phases/08-schedule-and-booking/08-20-PLAN.md", ".planning/phases/08-schedule-and-booking/08-20-SUMMARY.md", ".planning/phases/08-schedule-and-booking/08-21-PLAN.md", ".planning/phases/08-schedule-and-booking/08-21-SUMMARY.md", ".planning/phases/08-schedule-and-booking/08-22-PLAN.md", ".planning/phases/08-schedule-and-booking/08-22-SUMMARY.md", ".planning/phases/08-schedule-and-booking/08-23-PLAN.md", ".planning/phases/08-schedule-and-booking/08-23-SUMMARY.md", ".planning/phases/08-schedule-and-booking/08-24-PLAN.md", ".planning/phases/08-schedule-and-booking/08-24-SUMMARY.md", ".planning/phases/08-schedule-and-booking/08-25-PLAN.md", ".planning/phases/08-schedule-and-booking/08-25-SUMMARY.md", ".planning/phases/08-schedule-and-booking/08-26-PLAN.md", ".planning/phases/08-schedule-and-booking/08-26-SUMMARY.md", "app/Actions/OutboundSender.php", "app/Actions/ResolveDraft.php", "app/Actions/RunFrontDesk.php", "app/Actions/SendOwnerReply.php", "app/Ai/FrontDeskPrompt.php", "app/Livewire/ConversationThread.php", "app/Rooms/ScheduleAndBooking/BookingFileType.php", "app/Rooms/ScheduleAndBooking/BookingSettings.php", "app/Rooms/ScheduleAndBooking/ServiceToBook.php", "app/Rooms/ScheduleAndBooking/VisitAskedAbout.php", "app/SendRule/ReplyParts.php", "docs/SETUP.md", "examples/example-hvac/rooms/schedule_and_booking/booking.md", "resources/views/livewire/conversation-thread.blade.php"]
covered_digest: "v3:sha256:956b10ec6c853384ce3881b39e8025dae59d33bf66c416a35f3b86d25c1ed754"
behavior_unverified: 0
overrides_applied: 0
re_verification:
  previous_status: human_needed
  previous_score: 10/11
  gaps_closed:
    - "Previous human item 1 (Dev chat re-walk, truth 11 and the live half of truth 9): walked live 2026-11-12 (UAT check 8). G-08-4b's 'owner will confirm' wording is gone from all three model replies; the visit line box and Send adding the line passed live. The residual (reply held by the Phase 2 bars and Jev's promise reading; link-again wording) was ruled a known limitation for Phase 10 by Ian, not a Phase 8 gap."
    - "Previous human item 2 (keyboard walk of the visit line checkbox): UAT check 9 passed (Tab, visible outline, name, Space). VoiceOver on owner screens is Phase 11."
    - "Previous human item 3 (CI pgsql leg): UAT check 10 passed, CI run #65 at 6b762eb green on sqlite and pgsql."
    - "Previous advisory WR-09, WR-10, IN-10, IN-11, IN-12: fixed in 8269ff1, 11d8b16, 6b762eb, a7ea1f8, 084cd03; read in code in this run (see Review fixes below)."
  gaps_remaining: []
  regressions: []
deferred:
  - truth: "A booked customer's 'What time are you coming?' and an unbooked customer's 'send the link again' send on their own (live model wording clears the Phase 2 bars and Jev's promise reading)"
    addressed_in: "Phase 10"
    evidence: "ROADMAP.md Phase 10 planning notes (line 375 to 376): calibrate the Phase 2 safety bars; 'Measure and settle the Booking section's wording (BOOKING_ONLINE and BOOKING_VISIT_BOOKED) with the walk tests ... so that a booked customer's \"What time are you coming?\" and an unbooked customer's request for the link again send on their own.' Also BLUEPRINT section 10 open items. Ian's ruling 2026-11-12, recorded in 08-UAT.md check 8 (known_limitation)."
  - truth: "VoiceOver walk of the owner screens (Schedule page, Block time and Book by hand dialogs, visit line checkbox)"
    addressed_in: "Phase 11"
    evidence: "ROADMAP Phase 11 planning notes, inputs from Phase 8 UAT check 7; 08-UAT.md Deferred Follow-Ups"
advisory:
  - finding: "IN-13 (08-REVIEW.md): the WR-09 reason 'the visit changed after the line was shown' is also said when only the owner's visit_line wording or the time zone changed and the visit did not"
    category: other
    reason: "Info, rare (5-second poll window plus a sync inside it). The send is safe: the draft goes without the line. Only the owner-facing reason can be wrong. Recorded open in 08-REVIEW-DISPOSITION.md, awaiting Ian's ruling."
    evidence_status: "code read in this run (SendOwnerReply.php sendDraftAskingForVisitLine, VisitAskedAbout::VISIT_CHANGED); no failing test"
  - finding: "IN-14: NO_VISIT_LINE (WR-10) is reachable only by deleting the key from stored compiled JSON; the real case (live version compiled before plan 08-24) shows no box and tells the owner nothing"
    category: other
    reason: "Info. A fresh or re-synced folder cannot reach it because visit_line is required by the sync. Open, awaiting Ian's ruling."
    evidence_status: "code read in this run (VisitAskedAbout::whyNoLine)"
  - finding: "IN-15: the IN-10 upgrade note sits in docs/SETUP.md section 12, titled 'checks the sync cannot make', though it describes a check the sync does make"
    category: other
    reason: "Info, documentation placement. Open, awaiting Ian's ruling."
    evidence_status: "docs/SETUP.md read in this run"
  - finding: "Live wording on the sent-on-its-own path: the model narrates what the Booking section says code adds ('listed just below', 'you'll get a confirmation right away') and Jev reads it as a promise; the Phase 2 bars on the customer's own text also held two of three replies"
    category: other
    reason: "Ian's ruling 2026-11-12: known limitation, routed to Phase 10 (see deferred). No customer saw anything false: the replies were held, and the visit-line path was correct in code and on the held-draft box live."
    evidence_status: "08-UAT.md check 8, gaps G-08-4 and G-08-4b live_walk notes"
---

# Phase 8: Schedule and Booking Verification Report

**Phase Goal:** A customer can finish the conversation by booking a time, and the owner sees it and is told about it.
**Verified:** 2026-11-12
**Status:** passed
**Re-verification:** Yes. The previous report (2026-11-11) was `human_needed` 10/11 and went stale because five review-fix commits landed after it. This run re-read each fix in code, ran the tests for them, took the UAT record as the answer to the earlier human items after checking it against the code, and regenerated the fingerprint.

## Goal Achievement

The booking path itself (link, page, confirmed appointment, project and timeline and card and schedule screen, reminders, owner alerts, the room at the standard, the accessible page) is unchanged since the last full verification apart from the five fixes, and UAT walked it. The two UAT failures (G-08-4 and G-08-4b) have their code fixed and tested, and the live walk on 2026-11-12 showed the fix working where code controls the outcome. What did not hold live was the front desk sending two particular reply shapes on its own. Jev's promise reading and the Phase 2 bars held them. Ian ruled that a known limitation for Phase 10, and ROADMAP.md carries it as a specific Phase 10 item. That does not defeat the goal: the customer finishes the conversation by booking a time through the link, and held replies reach the owner, who can send them with the visit line.

### Observable Truths

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | SC1: a customer texted a link opens a page, taps one time and ends with a confirmed appointment without typing; only valid times offered | VERIFIED | Booking page, Availability and link path untouched since 1335509 (git diff names only the files listed under Review fixes and the gap plans). UAT check 1 passed at desktop and 375 px; UAT check 4 booked through a live link (confirmation sent at once with the right day and time). |
| 2 | SC2: a booked appointment attaches to the project, appears on the timeline, the card and the schedule screen | VERIFIED | UAT checks 4 and 7: hand-booked and page-booked visits on the card and the Schedule page; Upcoming and Past never list the same row. |
| 3 | SC3: the customer is reminded before the appointment, by the scheduler, sent once | VERIFIED | SendBookingText untouched; BookingSendOnceTest passes in the CI run. UAT check 8 saw a confirmation wait for the 8:00 send window and send at 9:00. |
| 4 | SC4: the owner is alerted once when an appointment is booked and when a conversation needs a human | VERIFIED | UAT check 8: one change_request alert for message 36 (not sent only because the dev owner has no phone, which is a setup note, not a code gap). VisitLineTest asserts exactly one change_request alert on the visit-line path; the D-34 alert reads its visit through `VisitAskedAbout::find()` and `describe()`, and `find()` now breaks ties by id so alert, line and card name the same visit (IN-11). |
| 5 | SC5: the room meets the standard | VERIFIED | ScheduleAndBookingBootTest, SyncTest and BookingFileCheckTest part of the green full suite (3,210 tests) and CI #65; `visit_line` required by `BookingFileType`. |
| 6 | SC6: the booking page meets WCAG 2.2 AA (automated part) and the walks | VERIFIED | Automated checks green; UAT checks 1 to 3 passed (G-08-1 and G-08-2 closed by Ian as environment and VoiceOver behaviour, no code change). VoiceOver on owner screens is Phase 11. |
| 7 | D-12 (locked): a copy of the database or business folder holds no working booking link | VERIFIED | BookingLinkLeakTest and BookingTextKindTest in the green suite and CI #65 (including the pg_class scan on the pgsql leg). UAT check 8: no booking address of any form in the model's words; link-again was held, nothing broken reached the customer. The Booking section constants hold no `/book/` (BookingSectionTest). |
| 8 | D-46: the automatic-texts switch writes one timeline entry per change | VERIFIED | UAT check 7 walked it. |
| 9 | G-08-4, D-39 as amended: with a visit booked ahead, a scheduling reply reaches the customer as the model's words then the owner's visit line rendered by code (`meta.parts model_words, visit_line`); a held draft offers the line behind a box | VERIFIED | Code read as before (RunFrontDesk::sendPassingReply, ReplyParts, OutboundSender::whyReadingBlocks, SendOwnerReply::sendDraftAskingForVisitLine). VisitLineTest (real queued send path with the fake classifier) asserts the phone received the words then the line, parts, appointment id, verdict judged on the words alone. Live (UAT check 8, message 37): reply labeled scheduling with no day or time in the words, so the code path is right; sending on its own was prevented by the customer reading (asks_for_person 0.08, in the unsure band) and the words check (promise 0.54). That is a Phase 2 and Jev calibration matter that Ian routed to Phase 10; it is not the visit-line code. The behavioral test exists and passes, so this truth is not left on presence alone. |
| 10 | G-08-4: a held reply for a booked customer shows one native checkbox with the line; Send adds it after the owner's words; every condition re-checked at approval; over 1,600 refused; line sent equals the line shown (WR-09) | VERIFIED | `ConversationThread::approveDraft()` now passes the locked `visitLine` that was on screen (`$this->addVisitLine ? $this->visitLine : null`, ConversationThread.php:110-113 in the diff), never a browser value. `SendOwnerReply::sendDraftAskingForVisitLine()` adds the line only when `$line === $visitLineShown` and otherwise sends the words alone with `meta.visit_line_left_off` `VISIT_CHANGED`. ApprovedDraftVisitLineTest has rows for a moved visit and an earlier visit booked after the render. Live (UAT checks 8 and 9): message 37's box read 'The visit line will be added: ...', the owner edited the words and sent with the box checked, and the customer got the words then the line (`visit_line_appointment_id` 9). Keyboard walk passed: one Tab, visible outline, name equals the visible line, Space toggles. |
| 11 | G-08-4b, D-39 amended: the model reads a corrected rule and a Booking section that says what code adds, so its words do not say the owner confirms | VERIFIED | `FrontDeskPrompt::RULES` line 47 no longer says the owner confirms; `bookingSection()` places `BOOKING_VISIT_BOOKED` or `BOOKING_ONLINE` after the card. IN-12 fix: both constants now say the line is added when the reply is sent as it is and that the owner decides when it is held, which matches the code (read in this run). BookingSectionTest and FrontDeskPromptTest pass. Live: UAT check 8 reports none of the three model replies says the owner confirms and no model reply carried a booking address. This was the item no test could assert; the live walk answered it. Residual wording (the model narrates what code adds; link-again repeats the link line's invitation) is the deferred Phase 10 item, not this truth. |

**Score:** 11/11 truths verified (0 present, behavior-unverified)

### Deferred Items

| # | Item | Addressed In | Evidence |
|---|------|-------------|----------|
| 1 | Sent-on-its-own for a booked customer's "What time are you coming?" and a link-again request (Booking section wording measured with the walk tests; Phase 2 bar calibration) | Phase 10 | ROADMAP.md Phase 10 planning notes lines 375 to 376; BLUEPRINT section 10 open items; Ian 2026-11-12 (08-UAT.md check 8). |
| 2 | VoiceOver on owner screens | Phase 11 | ROADMAP Phase 11 planning notes; 08-UAT.md Deferred Follow-Ups. |

### Advisory (not blocking)

| # | Finding | Category | Why advisory |
|---|---------|----------|--------------|
| 1 | IN-13: the WR-09 reason is worded as 'the visit changed' also when only the visit_line wording or time zone changed | other | Info; the send is safe; open, Ian's ruling pending |
| 2 | IN-14: NO_VISIT_LINE reason reachable only by hand-editing stored JSON; the real pre-08-24 case says nothing to the owner | other | Info; open, Ian's ruling pending |
| 3 | IN-15: SETUP.md upgrade note placed under 'checks the sync cannot make' | other | Info, docs placement; open, Ian's ruling pending |
| 4 | Live wording of sent-on-its-own replies held by Jev and the Phase 2 bars | other | Known limitation per Ian; deferred to Phase 10 |

### Review fixes verified in code (commits 8269ff1, 11d8b16, a7ea1f8, 084cd03, 6b762eb)

| Fix | What the code now does | Status |
|-----|------------------------|--------|
| WR-09 (8269ff1) | `ResolveDraft::approve` and `SendOwnerReply::sendDraft` take `?string $visitLineShown`; the line rides only when `$line === $visitLineShown`, else the draft goes as is with `VISIT_CHANGED` recorded | VERIFIED, tests pass |
| WR-10 (11d8b16) | `whyNoLine()` splits room-off/no-file (`BOOKING_OFF`) from a live file without `visit_line` (`NO_VISIT_LINE`); the leftover reason in `sendDraftAskingForVisitLine` no longer says 'booking is off' for a missing visit_line | VERIFIED |
| IN-11 (a7ea1f8) | `find()` orders `starts_at` then `id` | VERIFIED |
| IN-12 (084cd03) | Both Booking constants state the send-as-is vs held distinction; pinned in BookingSectionTest | VERIFIED |
| IN-10 (6b762eb) | `docs/SETUP.md` carries the `visit_line:` upgrade note (placement is IN-15) | VERIFIED (docs) |

### Required Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `app/Rooms/ScheduleAndBooking/VisitAskedAbout.php` | One place for the condition, description, line, reasons | VERIFIED | `find()` (id tie-break), `describe()`, `line()`, `whyNoLine()`, `VISIT_CHANGED`, `NO_VISIT_LINE` |
| `app/SendRule/ReplyParts.php` | `visit_line` slot | VERIFIED | Unchanged since last report |
| `app/Rooms/ScheduleAndBooking/BookingSettings.php`, `BookingFileType.php`, `examples/.../booking.md` | Owner's `visit_line`, required by the sync | VERIFIED | Unchanged since last report |
| `app/Actions/SendOwnerReply.php`, `ResolveDraft.php`, `app/Livewire/ConversationThread.php`, `conversation-thread.blade.php` | The box and the approval | VERIFIED | WR-09 wiring read end to end: locked property to approve to send |
| `app/Ai/FrontDeskPrompt.php` | Corrected rule, Booking section | VERIFIED | Constants updated by IN-12 |
| Tests: VisitLineTest, ApprovedDraftVisitLineTest, DraftVisitLineKeyboardTest, BookingSectionTest | Proof | VERIFIED | See spot-checks |

### Key Link Verification

| From | To | Via | Status |
|------|----|-----|--------|
| ConversationThread::approveDraft | ResolveDraft::approve then SendOwnerReply::sendDraft | locked `visitLine` passed down as `$visitLineShown` | WIRED |
| SendOwnerReply::sendDraftAskingForVisitLine | OutboundSender::sendExisting | `whyNoLine()`, `find()`, `line()`, compare to shown, then compose | WIRED |
| RunFrontDesk::sendPassingReply | SendCombinedReply and OutboundSender | `visitAskedAbout->find(` then `withVisitLine` | WIRED (unchanged) |
| FrontDeskPrompt::instructions | Booking section | `bookingSection(` after the card | WIRED (unchanged) |

### Data-Flow Trace (Level 4)

| Artifact | Data | Source | Real data | Status |
|----------|------|--------|-----------|--------|
| Customer's text | visit day and time | `$contact->appointments()->booked()->ahead()` ordered by start then id, rendered through the owner's `visit_line` | Yes (UAT message sent with line, appointment 9) | FLOWING |
| Held-draft box | `visitLine` | `visitLineFor()` each render and 5 s poll; passed down on Send | Yes (UAT check 8 and 9) | FLOWING |

### Behavioral Spot-Checks

| Behavior | Command | Result | Status |
|----------|---------|--------|--------|
| WR-09 to IN-12 test files | `php artisan test --compact tests/Feature/Booking/ApprovedDraftVisitLineTest.php tests/Feature/Booking/VisitLineTest.php tests/Feature/FrontDesk/BookingSectionTest.php tests/Feature/FrontDesk/FrontDeskPromptTest.php` | 112 passed, 562 assertions | PASS |
| Full suite | not re-run by me; orchestrator ran it 2026-11-12 | 3,210 tests, 22,642 assertions, 1 known browser-plugin warning | Taken from the orchestrator |
| CI | UAT check 10 | run #65 at 6b762eb green on sqlite and pgsql | Taken from UAT |
| Debt markers (TBD, FIXME, XXX) in the files changed by the five fixes | grep | none | PASS |

### Probe Execution

Step 7c: SKIPPED. No `scripts/*/tests/probe-*.sh` exists and no plan declares a probe.

### Requirements Coverage

Every ID the roadmap lists for Phase 8 appears in at least one plan's `requirements:` frontmatter and in REQUIREMENTS.md. No orphans; BOOK-06 maps to Phase 9.

| Requirement | Status | Evidence |
|-------------|--------|----------|
| ROOM-12 | SATISFIED | Truth 5 |
| BOOK-01 | SATISFIED | Truth 1 |
| BOOK-02 | SATISFIED | Truths 1, 7 |
| BOOK-03 | SATISFIED | Truths 2, 8, 9, 10 |
| BOOK-04 | SATISFIED | Truth 3 |
| BOOK-05 | SATISFIED | Truth 4 |
| A11Y-02 | SATISFIED | Truth 6; UAT keyboard, VoiceOver and zoom walks passed |

Bookkeeping, still open: REQUIREMENTS.md line 53 (BOOK-01) and line 57 (BOOK-05) are still unchecked `[ ]`, and traceability rows 216 and 220 still read "Gaps Found". The code and UAT support marking both complete and the rows "Complete". Whoever closes the phase should update them. This is a tracking inconsistency, not a goal gap.

### Anti-Patterns Found

None blocking. No TBD, FIXME or XXX in the files changed by the five fixes. The earlier WARNING and INFO findings (WR-09, WR-10, IN-10, IN-11, IN-12) are fixed. IN-13 to IN-15 are open info items listed under Advisory.

### Human Verification Required

None. The previous items are answered by UAT (checks 8, 9, 10), checked against the code above. Two things stay outside Phase 8 by Ian's ruling and are recorded under Deferred: live sent-on-its-own wording (Phase 10) and VoiceOver on owner screens (Phase 11). The attorney list (docs/BLUEPRINT.md section 10) remains a before-first-client item and is not a phase gate.

### Gaps Summary

No gaps. All eleven truths hold. The one place the live system fell short of a UAT expectation (replies held by the Phase 2 bars and Jev's promise reading) was ruled a known limitation by Ian and sits as a specific item in ROADMAP Phase 10, so it is deferred, not a Phase 8 gap.

---

_Verified: 2026-11-12_
_Verifier: Claude (gsd-verifier)_
