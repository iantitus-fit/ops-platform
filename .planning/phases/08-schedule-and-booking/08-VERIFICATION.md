---
phase: 08-schedule-and-booking
verified: 2026-11-11T23:00:00Z
status: human_needed
score: 10/11 must-haves verified
covered_files: [".planning/phases/08-schedule-and-booking/08-01-PLAN.md",".planning/phases/08-schedule-and-booking/08-01-SUMMARY.md",".planning/phases/08-schedule-and-booking/08-02-PLAN.md",".planning/phases/08-schedule-and-booking/08-02-SUMMARY.md",".planning/phases/08-schedule-and-booking/08-03-PLAN.md",".planning/phases/08-schedule-and-booking/08-03-SUMMARY.md",".planning/phases/08-schedule-and-booking/08-04-PLAN.md",".planning/phases/08-schedule-and-booking/08-04-SUMMARY.md",".planning/phases/08-schedule-and-booking/08-05-PLAN.md",".planning/phases/08-schedule-and-booking/08-05-SUMMARY.md",".planning/phases/08-schedule-and-booking/08-06-PLAN.md",".planning/phases/08-schedule-and-booking/08-06-SUMMARY.md",".planning/phases/08-schedule-and-booking/08-07-PLAN.md",".planning/phases/08-schedule-and-booking/08-07-SUMMARY.md",".planning/phases/08-schedule-and-booking/08-08-PLAN.md",".planning/phases/08-schedule-and-booking/08-08-SUMMARY.md",".planning/phases/08-schedule-and-booking/08-09-PLAN.md",".planning/phases/08-schedule-and-booking/08-09-SUMMARY.md",".planning/phases/08-schedule-and-booking/08-10-PLAN.md",".planning/phases/08-schedule-and-booking/08-10-SUMMARY.md",".planning/phases/08-schedule-and-booking/08-11-PLAN.md",".planning/phases/08-schedule-and-booking/08-11-SUMMARY.md",".planning/phases/08-schedule-and-booking/08-12-PLAN.md",".planning/phases/08-schedule-and-booking/08-12-SUMMARY.md",".planning/phases/08-schedule-and-booking/08-13-PLAN.md",".planning/phases/08-schedule-and-booking/08-13-SUMMARY.md",".planning/phases/08-schedule-and-booking/08-14-PLAN.md",".planning/phases/08-schedule-and-booking/08-14-SUMMARY.md",".planning/phases/08-schedule-and-booking/08-15-PLAN.md",".planning/phases/08-schedule-and-booking/08-15-SUMMARY.md",".planning/phases/08-schedule-and-booking/08-16-PLAN.md",".planning/phases/08-schedule-and-booking/08-16-SUMMARY.md",".planning/phases/08-schedule-and-booking/08-17-PLAN.md",".planning/phases/08-schedule-and-booking/08-17-SUMMARY.md",".planning/phases/08-schedule-and-booking/08-18-PLAN.md",".planning/phases/08-schedule-and-booking/08-18-SUMMARY.md",".planning/phases/08-schedule-and-booking/08-19-PLAN.md",".planning/phases/08-schedule-and-booking/08-19-SUMMARY.md",".planning/phases/08-schedule-and-booking/08-20-PLAN.md",".planning/phases/08-schedule-and-booking/08-20-SUMMARY.md",".planning/phases/08-schedule-and-booking/08-21-PLAN.md",".planning/phases/08-schedule-and-booking/08-21-SUMMARY.md",".planning/phases/08-schedule-and-booking/08-22-PLAN.md",".planning/phases/08-schedule-and-booking/08-22-SUMMARY.md",".planning/phases/08-schedule-and-booking/08-23-PLAN.md",".planning/phases/08-schedule-and-booking/08-23-SUMMARY.md",".planning/phases/08-schedule-and-booking/08-24-PLAN.md",".planning/phases/08-schedule-and-booking/08-24-SUMMARY.md",".planning/phases/08-schedule-and-booking/08-25-PLAN.md",".planning/phases/08-schedule-and-booking/08-25-SUMMARY.md",".planning/phases/08-schedule-and-booking/08-26-PLAN.md",".planning/phases/08-schedule-and-booking/08-26-SUMMARY.md","app/Actions/OutboundSender.php","app/Actions/ResolveDraft.php","app/Actions/RunFrontDesk.php","app/Actions/SendOwnerReply.php","app/Ai/FrontDeskPrompt.php","app/Livewire/ConversationThread.php","app/Rooms/ScheduleAndBooking/BookingFileType.php","app/Rooms/ScheduleAndBooking/BookingSettings.php","app/Rooms/ScheduleAndBooking/ServiceToBook.php","app/Rooms/ScheduleAndBooking/VisitAskedAbout.php","app/SendRule/ReplyParts.php","examples/example-hvac/rooms/schedule_and_booking/booking.md","resources/views/livewire/conversation-thread.blade.php"]
covered_digest: "v3:sha256:851b28a4f2a75784c11e321d44ccf226fcffe9c48b742994ed58a00e8c5fd01a"
behavior_unverified: 1
overrides_applied: 0
re_verification:
  previous_status: human_needed
  previous_score: 8/8
  gaps_closed:
    - "G-08-4 (UAT test 4, D-39 as amended): a booked customer's 'what time are you coming?' reply carries the owner's visit line built by code; a held draft offers the same line behind a box: plans 08-24, 08-25"
    - "G-08-4b (UAT test 4): the front desk's prompt no longer tells the model the owner confirms recorded bookings, and says what code adds: plan 08-26 (code half; live model effect is a human item)"
    - "G-08-1 and G-08-2: closed by Ian as environment and VoiceOver behaviour (a27c6f1, 1335509); not re-opened"
  gaps_remaining: []
  regressions: []
advisory:
  - finding: "WR-09 (08-REVIEW.md): an approved held draft sends the visit line rendered at send time, not necessarily the line the owner saw on the box"
    category: other
    reason: "Real, narrow. ConversationThread::approveDraft() passes only a boolean ($this->addVisitLine && $this->visitLine !== null) and SendOwnerReply::sendDraftAskingForVisitLine() calls VisitAskedAbout::find() again. The page polls every 5 seconds (conversation-thread.blade.php:1, wire:poll.5s), so the box text refreshes within 5 seconds of a change, and the line sent is true at send time. The customer is never told anything false. It breaks the stated 'a line is added only when it was on the owner's screen' contract only when the earliest visit changes inside that window. No must-have text requires the sent line to equal the shown line. Ian's triage; the review's fix (pass the shown line down and compare) is small."
    evidence_status: "code read in this run; no failing test exists for the change-between-render-and-click case"
  - finding: "WR-10 (08-REVIEW.md): VisitAskedAbout::whyNoLine() returns 'online booking is turned off' when booking is on but the live version predates visit_line"
    category: other
    reason: "Wording for the owner, reachable only in the in-between state after upgrade, before the owner adds visit_line and syncs. visit_line is now required by the sync, so a fresh folder cannot reach it. The prompt's Booking section is left out in the same state, which the plan accepted (D-03). Ian's triage."
    evidence_status: "code read (VisitAskedAbout.php whyNoLine, SendOwnerReply.php:237); test row pins the sentence"
  - finding: "IN-10 (08-REVIEW.md): visit_line is required, so an older booking.md is refused whole by the sync with no upgrade note in SETUP.md"
    category: other
    reason: "Recoverable: the refusal names the file and field and the previous version stays live. Owner-facing documentation edge."
    evidence_status: "BookingFileType.php:42 read"
  - finding: "IN-11 (08-REVIEW.md): VisitAskedAbout::find() orders by starts_at only, so two visits at the same minute can come back in either order, and the card can name a different one"
    category: other
    reason: "Two booked visits at one start minute for one contact is rare. The fix is one orderBy('id'). Ian's triage."
    evidence_status: "VisitAskedAbout.php find() read"
  - finding: "IN-12 (08-REVIEW.md): the Booking section tells the model 'the system adds a line after your words' as flat fact, which is not true of a reply the send rule holds as a draft until the owner ticks the box"
    category: other
    reason: "Wording the model reads. A held draft can reach the owner without a day or time, and the box then adds it. Small effect; folds into the live re-walk (human item 1)."
    evidence_status: "FrontDeskPrompt.php:92, :102 read"
  - finding: "WR-06..WR-08 and IN-09 from the earlier verification are fixed (WR-06, WR-07, WR-08 per 08-REVIEW-DISPOSITION.md) or unchanged (IN-09)"
    category: other
    reason: "Dispositions recorded by Ian on 2026-11-09; not re-weighed here."
    evidence_status: "08-REVIEW-DISPOSITION.md"
behavior_unverified_items:
  - truth: "G-08-4b: the front desk's booking texts never say the owner will confirm the time and do not repeat the link line's words"
    test: "In Dev chat after php artisan icm:sync (live version carries visit_line): finish intake and read the offer; then ask for the link again"
    expected: "The offer is labeled scheduling with the owner's link line and its own words neither ask the customer to pick a time nor say the owner will confirm; the link-again reply does not repeat the link line"
    why_human: "What the model writes under the new prompt cannot be asserted with the scripted fake agent. Tests prove the model now reads the corrected rule and the Booking section; only the live model (Jev and the front-desk agent) shows whether it follows them. Recorded as WINDOWS.md entry 79."
human_verification:
  - test: "Dev chat re-walk (WINDOWS.md entry 79): (1) D-05 offer after the last intake answer; (2) D-39 as amended, on a contact with a visit booked ahead, 'What time are you coming?'; (3) the link-again case; (4) a held reply on a booked contact shows the visit line box and Send adds the line"
    expected: "(1) labeled scheduling with the link line, no 'owner will confirm' in the model's words; (2) sent on its own labeled scheduling, parts model_words and visit_line, the model gives no day or time, one change_request alert; (3) a fresh link line or a held draft, never /book/[link] to the customer; (4) the box reads 'The visit line will be added: ...' and Send adds the line after the words"
    why_human: "Needs the live model; the scripted tests cannot show how it labels or words these replies. This is the exact UAT check that failed (test 4), so it is the one that closes G-08-4 and G-08-4b."
  - test: "Keyboard-only and VoiceOver walk of the new visit line checkbox on the owner's conversation screen"
    expected: "One Tab from the draft box reaches the checkbox, its name is the visible line, Space unticks it, and VoiceOver reads it as a checkbox with that name"
    why_human: "CLAUDE.md: automated checks catch only part of WCAG. DraftVisitLineKeyboardTest covers axe, Tab, name and Space in real Chromium; VoiceOver on owner screens was moved to Phase 11 by Ian (UAT check 7)."
  - test: "Push the branch and read CI's pgsql leg for the whole suite"
    expected: "Both sqlite and pgsql legs green. CI run 42643059174 at 689b786 covered the phase before the gap plans; plans 08-24 to 08-26 (commits after 689b786) have run on SQLite only. The new code has no DISTINCT or raw SQL, so none is expected to differ"
    why_human: "Postgres runs only in CI after Ian pushes"
  - test: "Phone-width look at the booking page and the visit line box if Phase 11 has not yet restyled them; attorney list still before a real client"
    expected: "Nothing scrolls sideways at 375 px; attorney has the four facts in docs/BLUEPRINT.md section 10"
    why_human: "Already passed at UAT (checks 1, 3, 6); listed so the end-of-phase UAT record is complete"
---

# Phase 8: Schedule and Booking Verification Report

**Phase Goal:** A customer can finish the conversation by booking a time, and the owner sees it and is told about it.
**Verified:** 2026-11-11
**Status:** human_needed
**Re-verification:** Yes, after UAT gap closure (plans 08-24 to 08-26). The earlier report was `human_needed` 8/8.

## Goal Achievement

UAT passed the booking path (checks 1, 3, 5, 6, 7), closed G-08-1 and G-08-2 as environment, and failed check 4 on two linked symptoms: a booked customer asking "what time are you coming?" got a held deflection, and the front desk's booking texts said the owner would confirm a time the page books at once. Ian amended D-39 on 2026-11-11 and plans 08-24 to 08-26 were written to close both. I read the code each plan names, traced each path from the model's reply to the customer's phone and to the owner's approve button, and ran the tests. The code half of both gaps holds. The half that depends on how the live model words and labels its replies cannot be shown here, so that stays a human item and the status is `human_needed`. No blocker was found.

### Observable Truths

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | SC1: a customer texted a link opens a page, taps one time and ends with a confirmed appointment without typing; only valid times offered | VERIFIED (regression check) | No file of the booking page, Availability or the link path changed since the last verification (git diff 1335509..HEAD touches none). BookingLinkTest and the Booking, Rooms and leak suites pass in this run. UAT check 1 passed at desktop and 375 px. |
| 2 | SC2: a booked appointment attaches to the project, appears on the timeline, the card and the schedule screen | VERIFIED (regression check) | Unchanged files; UAT check 7 walked the Schedule page and card. |
| 3 | SC3: the customer is reminded before the appointment, by the scheduler, and a text is sent once | VERIFIED (regression check) | SendBookingText untouched since the last verification; BookingSendOnceTest passes. |
| 4 | SC4: the owner is alerted once when an appointment is booked and when a conversation needs a human | VERIFIED | The D-34 change_request alert now reads its visit through `VisitAskedAbout::find()` then `describe()` (RunFrontDesk.php:270-271). VisitLineTest asserts exactly one change_request alert with body containing 'AC repair, Thursday, November 5 at 10:00 AM' on the visit-line path. |
| 5 | SC5: the room meets the standard | VERIFIED (regression check) | ScheduleAndBookingBootTest, SyncTest and BookingFileCheckTest pass (tests/Feature/Rooms, 513 tests across the extra run). The booking file type gained `visit_line` (BookingFileType.php:42, :78) with its own refusal rows. |
| 6 | SC6: the booking page meets WCAG 2.2 AA (automated part); UI-SPEC carries it | VERIFIED (automated part) | Unchanged since last verification; UAT checks 1 to 3 passed the keyboard, VoiceOver and zoom walks. |
| 7 | D-12 (locked): a copy of the database or business folder holds no working booking link | VERIFIED (regression check) | BookingLinkLeakTest and BookingTextKindTest pass. New code adds no link: the visit line holds no `/book/`, and `OutboundSender` still refuses any text holding the hidden form. The prompt's Booking sections are tested to hold no `/book/` and no stored link line (BookingSectionTest, 'writes the Booking section with no booking address, exclamation point or em dash'). |
| 8 | D-46: the automatic-texts switch writes one timeline entry per change | VERIFIED (regression check) | UAT check 7 walked it: "Ian Owner turned automatic texts on/off." |
| 9 | G-08-4, D-39 as amended: with a visit booked ahead, a scheduling reply reaches the customer as the model's words then the owner's visit line, rendered by code; kind `agent_reply`, label scheduling, `meta.parts ['model_words','visit_line']`, `meta.visit_line_appointment_id` set, verdict judges the model's words alone | VERIFIED (code half; the live label depends on the model, see human item 1) | `RunFrontDesk::sendPassingReply()` (:610-620): when no link was minted and the reply has model words, `VisitAskedAbout::find()` (reading intent scheduling or label scheduling, earliest booked visit ahead, through the contact's own relation) then `line()` (BookingSettings::renderVisit, owner's `visit_line`) then `visitLineFits()` (<= 1,600 composed) then `withVisitLine()`. It goes the `SendCombinedReply` path, whose `meta.parts` come from `ReplyParts::partNames()` (`visit_line` placed after the fixed line, before the booking line, ReplyParts.php:37-59, :95-97). `OutboundSender::whyReadingBlocks()` holds `visit_line` to `allowsOwnerSentences()` (:545), so a complaint or failed reading refuses it. The link is only minted when `ServiceToBook` finds no visit ahead (`visitBookedAhead`), so the two lines never ride together. VisitLineTest (real send path through the queued job and the fake classifier): asserts the customer's phone received `composedFirstReply('Thanks for checking. Your visit for AC repair is on Thursday, November 5 at 10:00 AM. To change it, reply to this text.')`, kind, label, status, parts, appointment id, `gate.inputs.agent_reply` equal to the model's words alone, no BookingLink row, no classifier call containing the line, one change_request alert. Further rows pass for clean reading at the chokepoint, faq_answer with scheduling intent, no line when not about scheduling, link line when no visit ahead, room off / no file / old version, never in a held draft, a waiting price draft, and the 1,600 boundary both ways. No new MessageKind, no field on the model's structured reply, no Appointment import in RunFrontDesk (grep). |
| 10 | G-08-4, D-39 amended, D-45 analog: a held reply for a booked customer shows one native checkbox 'The visit line will be added: <line>'; Send adds the line after the owner's words; conditions re-checked at approval; refusal over 1,600; when the reading is not clean the box starts unchecked | VERIFIED | `ConversationThread::syncDraftFields()` (:333-361) fills the locked `visitLine` from `SendOwnerReply::visitLineFor()` every render and sets the box start from `visitLineStartsOn()` (clean reading only). The blade shows `data-visit-line-choice`, `#add-visit-line`, label text 'The visit line will be added: {{ $visitLine }}' only when `$visitLine !== null`. `approveDraft()` passes the box only while a line was drawn. `SendOwnerReply::sendDraft()` routes to `sendDraftAskingForVisitLine()`, which asks `whyNoLine()` again at approval, sends without the line and records `meta.visit_line_left_off` when a reason holds, refuses with TOO_LONG_WITH_VISIT_LINE over 1,600 and keeps the draft live, and otherwise sends words + line through `OutboundSender::sendExisting()` on the owner origin with `meta.visit_line_appointment_id`. `aboutScheduling()` takes intent OR label, so UAT message 20's shape (label other, reading scheduling) shows the box. ApprovedDraftVisitLineTest and DraftVisitLineKeyboardTest (real Chromium: axe, one Tab, name, Space) pass. WR-09 (below) is the one gap between the stated contract and the code; it does not stop the box working. |
| 11 | G-08-4b, D-39 amended: the model reads a corrected rule, and a Booking section that says what code adds, so its words do not restate the day, time or link or say the owner confirms | ⚠️ PRESENT_BEHAVIOR_UNVERIFIED | `FrontDeskPrompt::RULES` line 47 no longer holds 'Never say a visit, a time or a booking is set... the owner confirms those'. It now says a day or time the customer proposes is not set and a visit the card lists as booked is the business's own recorded booking. `bookingSection()` (:290-303) adds '## Booking' after the card and before 'Already with the owner' and 'Right now' (:150-155): BOOKING_VISIT_BOOKED for a booked customer, only while `whyNoLine()` is null; BOOKING_ONLINE with the slugs from `ServiceToBook::onlineServiceSlugs()` (sorted, book_online only) for an unbooked customer; nothing when booking does not apply. Neither constant holds `/book/`, an exclamation point or an em dash (tested). `booking.md` is not added to the agent's loads (tested). BookingSectionTest and FrontDeskPromptTest pass. Present and wired; whether the live model now labels the reply scheduling and stops saying the owner confirms is what UAT test 4 failed on and no test can assert. Routes to human item 1. |

**Score:** 10/11 truths verified (1 present, behavior-unverified)

### Required Artifacts (gap plans 08-24 to 08-26)

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `app/Rooms/ScheduleAndBooking/VisitAskedAbout.php` | One place for the condition, description and line | VERIFIED | `find()`, `describe()`, `line()`, `whyNoLine()`, `aboutScheduling()`; read-only queries, no writes |
| `app/SendRule/ReplyParts.php` | `visitLine` slot named `visit_line` | VERIFIED | Placed after the fixed line, before the booking line; kept out of codeSentences |
| `app/Rooms/ScheduleAndBooking/BookingSettings.php` | `renderVisit()` | VERIFIED | Returns null for a version compiled before the field |
| `examples/.../booking.md` | Owner-worded `visit_line` | VERIFIED | Present, with the explanation paragraph; no exclamation point or dash |
| `app/Rooms/ScheduleAndBooking/BookingFileType.php` | Sync check | VERIFIED | `required|string|max:300`; `lineErrors` with day and time once, service at most once |
| `app/Actions/SendOwnerReply.php` | `visitLineFor`, `visitLineStartsOn`, `sendDraft` branch | VERIFIED | As Truth 10 |
| `resources/views/livewire/conversation-thread.blade.php` | The box | VERIFIED | `data-visit-line-choice`, label tied to checkbox by `for`/`id`, 1.5rem target |
| `app/Ai/FrontDeskPrompt.php` | Corrected rule, Booking section | VERIFIED | `BOOKING_VISIT_BOOKED`, `BOOKING_ONLINE`, `bookingSection()` |
| `app/Rooms/ScheduleAndBooking/ServiceToBook.php` | `visitBookedAhead()`, `onlineServiceSlugs()` | VERIFIED | `check()` reads the VISIT_BOOKED condition through `visitBookedAhead()` |
| Tests: VisitLineTest, ApprovedDraftVisitLineTest, DraftVisitLineKeyboardTest, BookingSectionTest | Proof | VERIFIED | `verify.artifacts` all_passed on all three plans (13/13); 170 tests pass in the seven gap-plan files I ran |

### Key Link Verification

`verify.key-links` could not evaluate these plans: their `from` fields name methods, not files, so it returned "Source file not found". I checked each by hand against the pattern the plan gives.

| From | To | Via | Status | Details |
|------|----|-----|--------|---------|
| RunFrontDesk::sendPassingReply | SendCombinedReply and OutboundSender | `visitAskedAbout->find(` then `withVisitLine` | WIRED | RunFrontDesk.php:613-618, :622-626 |
| RunFrontDesk::handle | D-34 change_request alert | `visitAskedAbout->describe(` | WIRED | :270-271 |
| OutboundSender::whyReadingBlocks | `visit_line` part | `'visit_line' =>` arm | WIRED | :545 |
| BookingFileType::checks | `visit_line` | `lineErrors($file, 'visit_line'` | WIRED | :78 |
| ConversationThread::approveDraft | ResolveDraft::approve then sendDraft | `addVisitLine && $this->visitLine !== null` | WIRED | :104-110 |
| SendOwnerReply::sendDraft | OutboundSender::sendExisting | `whyNoLine(` then compose | WIRED | :230-250 |
| ConversationThread::syncDraftFields | visitLineFor, visitLineStartsOn | every render | WIRED | :345, :360 |
| FrontDeskPrompt::instructions | the Booking section | `bookingSection(` after the card | WIRED | :150-155 |
| ServiceToBook::check | visitBookedAhead | VISIT_BOOKED | WIRED | ServiceToBook check() |

### Data-Flow Trace (Level 4)

| Artifact | Data | Source | Real data | Status |
|----------|------|--------|-----------|--------|
| Customer's phone text | visit day and time | `$contact->appointments()->booked()->ahead()` then `renderVisit` of the owner's `visit_line` | Yes | FLOWING |
| Held-draft box | `visitLine` | `visitLineFor($live)`, refreshed every render and every 5 s poll | Yes | FLOWING |
| Prompt Booking section | service slugs | `ActiveConfig::services()` filtered by `book_online` | Yes | FLOWING |

### Behavioral Spot-Checks

| Behavior | Command | Result | Status |
|----------|---------|--------|--------|
| Gap-plan test files (VisitLineTest, ApprovedDraftVisitLineTest, BookingLinkTest, BookingSectionTest, FrontDeskPromptTest, BookingFileCheckTest, DraftVisitLineKeyboardTest) | `php artisan test --compact` on those seven files | 170 passed, 893 assertions, 15 s | PASS |
| Architecture, booking-text-kind, leak, refusal sentences and the whole Rooms directory | `php artisan test --compact tests/Unit/ArchitectureTest.php tests/Unit/BookingTextKindTest.php tests/Feature/Booking/BookingLinkLeakTest.php tests/Feature/Booking/RefusalSentencesTest.php tests/Feature/Rooms` | 513 passed, 2,709 assertions, 39 s | PASS |
| Debt markers in files changed since 1335509 | grep TBD, FIXME, XXX across app, resources, database, examples, tests, routes | none | PASS |
| Full suite | not re-run by me | orchestrator: 3205 tests green after 08-26 | not re-verified |

### Probe Execution

Step 7c: SKIPPED. No `scripts/*/tests/probe-*.sh` exists and no plan declares a probe.

### Requirements Coverage

Every ID the roadmap lists for Phase 8 appears in at least one plan's `requirements:` frontmatter and in REQUIREMENTS.md. No orphans; BOOK-06 maps to Phase 9.

| Requirement | Source plans | Status | Evidence |
|-------------|--------------|--------|----------|
| ROOM-12 | 08-04, 08-14, 08-24 | SATISFIED | Truth 5; the booking file type carries `visit_line` |
| BOOK-01 | 08-05, 08-06 | SATISFIED | Truth 1 (unchanged) |
| BOOK-02 | 10 plans incl. 08-17, 08-18, 08-20 to 08-22 | SATISFIED | Truths 1, 7 |
| BOOK-03 | 11 plans incl. 08-24 to 08-26 | SATISFIED | Truths 2, 8, 9, 10 |
| BOOK-04 | 7 plans | SATISFIED | Truth 3 |
| BOOK-05 | 08-03, 08-07 | SATISFIED | Truth 4 |
| A11Y-02 | 6 plans | SATISFIED for the automated part; UAT walks passed (checks 1 to 3) | Truth 6 |

Bookkeeping: REQUIREMENTS.md line 53 (BOOK-01) and line 57 (BOOK-05) are still unchecked, and traceability rows 216 and 220 still read "Gaps Found". The code supports marking both complete. Whoever closes the phase should update them.

### Anti-Patterns Found

| File | Line | Pattern | Severity | Impact |
|------|------|---------|----------|--------|
| app/Livewire/ConversationThread.php, app/Actions/SendOwnerReply.php | 104-110, 230-250 | The shown line is not passed down; the send re-renders (WR-09) | WARNING | Narrow window (5 s poll); line is true at send time; contract in docblocks and BLUEPRINT.md:133 overstated |
| app/Rooms/ScheduleAndBooking/VisitAskedAbout.php | whyNoLine | 'online booking is turned off' for a pre-visit_line version (WR-10) | WARNING | Owner-facing wording in the in-between state only |
| app/Rooms/ScheduleAndBooking/VisitAskedAbout.php | find | no `id` tie-break (IN-11) | INFO | Same-minute double booking only |
| app/Ai/FrontDeskPrompt.php | 92, 102 | flat 'the system adds a line' (IN-12) | INFO | Model reads a claim that is only true on a send, not a held draft |
| docs/SETUP.md | - | no upgrade note for required `visit_line` (IN-10) | INFO | Refusal names the field |

No TBD, FIXME or XXX marker in changed files. I weighed WR-09, WR-10 and IN-10 to IN-12 against the must-haves. None defeats one: Truth 10 requires the line be added only when drawn, that every condition be re-checked at approval, and that the owner's choice decide, all of which the code does; none requires that the sent text equal the shown text, though the docblock implies it. WR-09 is the one I would fix first, before a real client, because the box's accessible name is the line the owner agrees to.

### Human Verification Required

1. **Dev chat re-walk** (WINDOWS.md entry 79). This is the check that failed at UAT and is the only way to close Truth 11 and the live half of Truth 9. Expected: offer labeled scheduling with the link line and no "owner will confirm" in the model's words; "What time are you coming?" on a booked contact sends on its own with the model's words and the visit line; link-again gives a fresh link line or a held draft, never `/book/[link]`; a held reply shows the visit line box.
2. **Keyboard and VoiceOver walk** of the new visit line checkbox (axe, Tab, name and Space are tested in Chromium; VoiceOver on owner screens was moved to Phase 11 by Ian).
3. **Push and read CI's pgsql leg.** CI run 42643059174 at 689b786 predates the gap plans.
4. **Attorney list** stays a before-first-client item (docs/BLUEPRINT.md section 10).

### Gaps Summary

No gaps. Both UAT failures have their code fixed and tested: the visit line is built by code from the owner's `visit_line`, rides only under one shared condition on the automated path, is held to a clean reading at the chokepoint, and is offered behind a checkbox on a held draft; the model now reads a corrected rule and a Booking section built from the same conditions the send uses. Nothing here can show how the live model will label and word its replies, which is exactly what UAT test 4 failed on, so the Dev chat re-walk decides whether G-08-4 and G-08-4b are closed in practice. WR-09 and WR-10 are open warnings for Ian's triage and do not block.

---

_Verified: 2026-11-11_
_Verifier: Claude (gsd-verifier)_
