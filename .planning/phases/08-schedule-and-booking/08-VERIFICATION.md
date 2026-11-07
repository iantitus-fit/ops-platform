---
phase: 08-schedule-and-booking
verified: 2026-11-07T00:00:00Z
status: gaps_found
score: 6/7 must-haves verified
covered_files: [".planning/phases/08-schedule-and-booking/08-01-PLAN.md",".planning/phases/08-schedule-and-booking/08-01-SUMMARY.md",".planning/phases/08-schedule-and-booking/08-02-PLAN.md",".planning/phases/08-schedule-and-booking/08-02-SUMMARY.md",".planning/phases/08-schedule-and-booking/08-03-PLAN.md",".planning/phases/08-schedule-and-booking/08-03-SUMMARY.md",".planning/phases/08-schedule-and-booking/08-04-PLAN.md",".planning/phases/08-schedule-and-booking/08-04-SUMMARY.md",".planning/phases/08-schedule-and-booking/08-05-PLAN.md",".planning/phases/08-schedule-and-booking/08-05-SUMMARY.md",".planning/phases/08-schedule-and-booking/08-06-PLAN.md",".planning/phases/08-schedule-and-booking/08-06-SUMMARY.md",".planning/phases/08-schedule-and-booking/08-07-PLAN.md",".planning/phases/08-schedule-and-booking/08-07-SUMMARY.md",".planning/phases/08-schedule-and-booking/08-08-PLAN.md",".planning/phases/08-schedule-and-booking/08-08-SUMMARY.md",".planning/phases/08-schedule-and-booking/08-09-PLAN.md",".planning/phases/08-schedule-and-booking/08-09-SUMMARY.md",".planning/phases/08-schedule-and-booking/08-10-PLAN.md",".planning/phases/08-schedule-and-booking/08-10-SUMMARY.md",".planning/phases/08-schedule-and-booking/08-11-PLAN.md",".planning/phases/08-schedule-and-booking/08-11-SUMMARY.md",".planning/phases/08-schedule-and-booking/08-12-PLAN.md",".planning/phases/08-schedule-and-booking/08-12-SUMMARY.md",".planning/phases/08-schedule-and-booking/08-13-PLAN.md",".planning/phases/08-schedule-and-booking/08-13-SUMMARY.md",".planning/phases/08-schedule-and-booking/08-14-PLAN.md",".planning/phases/08-schedule-and-booking/08-14-SUMMARY.md",".planning/phases/08-schedule-and-booking/08-15-PLAN.md",".planning/phases/08-schedule-and-booking/08-15-SUMMARY.md",".planning/phases/08-schedule-and-booking/08-16-PLAN.md",".planning/phases/08-schedule-and-booking/08-16-SUMMARY.md","app/Actions/BookAppointment.php","app/Actions/OutboundSender.php","app/Actions/RunFrontDesk.php","app/Actions/SendBookingText.php","app/Livewire/BookingPage.php","app/Records/CustomerCard.php","app/Rooms/ScheduleAndBooking/Availability.php","app/Rooms/ScheduleAndBooking/ScheduleAndBookingServiceProvider.php","routes/console.php"]
covered_digest: "v3:sha256:5c68e52c255774087b2377027e6f37520f20f733575ef3d2f488e007dfe18872"
behavior_unverified: 0
overrides_applied: 0
gaps:
  - truth: "D-12 (locked decision, BOOK-02 token handling): a copy of the database or of the business folder holds no working booking link"
    status: failed
    reason: "booking_links stores only the sha256 hash, but the plain token travels in the sent text. OutboundSender stores that text as messages.body (OutboundSender.php:257, sent from $message->body at :316), and CustomerCard prints sent reply bodies (CustomerCard.php:367, :417: $message?->body) into records/ in the business folder. A copy of the database or folder holds working links until each expires (1 to 30 days), books or is superseded. No redaction exists in app/ for booking links (the only '[link]' replacement is OwnerAlertText's, which cleans customer-typed text in alerts). 08-SECURITY.md carries this as open threats T-08-27 and T-08-01 (high), threats_open: 2. Ian ruled on 2026-11-07: fix in a gap plan, keep D-12 as written."
    artifacts:
      - path: "app/Actions/OutboundSender.php"
        issue: "deliver() stores the full text, token included, as messages.body; activity summaries, job payloads and failed-send messages (recordFailure / NOT_SENT) were not shown to be token-free"
      - path: "app/Records/CustomerCard.php"
        issue: "prints message bodies, including the booking URL, into the customer card written to records/"
      - path: "app/Actions/RunFrontDesk.php"
        issue: "line 609-610 builds route('booking.show', $token) into the reply text; the owner path through SendOwnerReply does the same (D-45)"
      - path: "tests/Feature/Booking/PageBookingTest.php"
        issue: "'stores only the hash' scans booking_links only, so it cannot see the leak"
    missing:
      - "Cover every place the full booking link lands, not only the two found: messages.body, the customer card in records/, timeline and activity summaries, queued job payloads and failed_jobs, and application logs."
      - "Store and print the sent text with the token replaced (for example '.../book/[link]') while the customer still receives the real link."
      - "Add a test that sends a link, then searches the database (every table) and the records folder and finds no working token anywhere."
      - "Correct 08-SECURITY.md: T-08-01's clause 'plain token never reaches the database' is false today; set threats_open to 0 only after the fix is verified."
      - "Add the text provider's own message log (Twilio keeps every sent body) to the attorney list in STATE.md blockers (Phase 02.1); the platform cannot redact it."
human_verification:
  - test: "Keyboard-only walk of the booking page at desktop width and at 375 px"
    expected: "A customer opens a booking link, reaches every time, books by keyboard alone, and focus lands on the confirmation heading"
    why_human: "Automated checks catch only part of WCAG (CLAUDE.md); the browser tests drive the keys but a person judges the walk"
  - test: "VoiceOver walk of the booking page"
    expected: "Each time is announced with its day and time; focus moves to the confirmation; errors are read beside the field"
    why_human: "Screen reader output is not machine-checkable here"
  - test: "200% zoom of the booking page"
    expected: "The page reflows and stays usable"
    why_human: "Reflow is judged by eye"
  - test: "Dev chat: ask to book a visit after intake; then ask 'what time are you coming?'"
    expected: "The offer comes back labeled scheduling with the link (D-05); the second question is answered from the card (D-39)"
    why_human: "Needs the live model (Jev); the executors recorded it for end-of-phase UAT"
  - test: "Push the branch and read CI's pgsql leg for the whole suite"
    expected: "Both sqlite and pgsql legs green, including the booking CHECK constraint, the one-open-conversation partial index and the distinct-order query"
    why_human: "Postgres runs only in CI after Ian pushes; recorded as unrun-verify items in .planning/WINDOWS.md"
---

# Phase 8: Schedule and Booking Verification Report

**Phase Goal:** A customer can finish the conversation by booking a time, and the owner sees it and is told about it.
**Verified:** 2026-11-07
**Status:** gaps_found
**Re-verification:** No, initial verification

## Goal Achievement

The booking path works end to end in the code: availability is computed server-side, the page books through one locked action, the appointment attaches to a project, shows on the card, the timeline and the schedule screen, the scheduler sends reminders through the chokepoint, and the owner is alerted through the shared alert path. One locked decision (D-12) is not met: a working booking link is stored in the database and printed into `records/`. Ian has already ruled on it and asked for a gap plan, so it is recorded here as a gap.

### Observable Truths

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | SC1: a customer texted a link opens a page, taps one time and ends with a confirmed appointment without typing; only times inside hours, outside closures, past lead time, long enough, in the business zone are offered | VERIFIED | `Availability::openStarts()` (app/Rooms/ScheduleAndBooking/Availability.php:41-90) uses `BusinessClock::openWindowOn` (closures at BusinessClock.php:164), lead time from the later of now and next opening (:182-191), `start + duration <= close`, drive buffer and capacity count, zone from `ActiveConfig::timezone()`. `BookingPage::choose()` re-checks with `offers()` (exact instant); `book()` calls `BookAppointment::fromLink()`, which re-checks `offers()` and the link state inside `Cache::lock('schedule:book')` and a transaction, with a unique `booking_link_id` backstop. Page inputs are `#[Locked]`. I ran tests/Feature/Booking (AvailabilityTest, BookingPageTest, PageBookingTest, BookingLinkTokenTest, BookAppointmentTest, BookingScheduleTest): all pass. |
| 2 | SC2: a booked appointment attaches to the project the lead opened, appears on the timeline, as the next appointment on the card, and on a dashboard schedule screen; a project with two appointments shows both | VERIFIED | `BookAppointment::jobIdForPageBooking()` picks the open job tied to the link's conversation, else the only open job, else opens one, never reopens a closed one. `recordRoomEntry` writes the `visit_booked` timeline entry in `storeFromLink()`. `CustomerCard::nextAppointmentLines()` and `visitLines()` (CustomerCard.php:254, :281) print the next visit and each job's visits. `app/Filament/Pages/Schedule.php` and `ScheduleSection` (dashboard next five visits). AppointmentCardTest, SchedulePageTest, AppointmentsRelationManagerTest pass. |
| 3 | SC3: the customer is reminded before the appointment, sent by the scheduler | VERIFIED | `routes/console.php:22` `Schedule::job(new SendDueBookingTexts)->everyMinute()->withoutOverlapping()`. `SendBookingText` claims one row by conditional update, re-checks visit state, send window and unread customer text, then sends through `OutboundSender` as a booking text. `ReminderSchedule` writes one row per lead time (unique `appointment_id, lead_minutes`). BookingSendTest and BookingSendRowsTest and ReminderScheduleTest pass. Known risk, not a goal failure: review WR-01 (a crash between send and outcome write can resend) and WR-02 (a reminder at the window edge can drop on a one-minute delay), both open for Ian's triage. |
| 4 | SC4: the owner is alerted, once, when an appointment is booked and when a conversation needs a human, per `owner_alert` in `rules/sending.md` | VERIFIED | `BookAppointment::alertOwner()` raises `AlertReason::AppointmentBooked` through `RaiseOwnerAlert` keyed to the carrying message (never message 0). `RunFrontDesk::alertOrChangeRequest()` raises `ChangeRequest` in the one-alert order. The example `rules/sending.md` lists `change_request` under `right_away` and describes `appointment_booked` as open-hours / next-opening. BookingAlertTest and ChangeRequestAlertTest ('gives way to a higher alert') pass in the Booking run and the earlier validation. |
| 5 | SC5: the schedule and booking room meets the standard (folder, `CONTEXT.md`, example file, module, boot test) | VERIFIED | `examples/example-hvac/rooms/schedule_and_booking/{CONTEXT.md,booking.md}`; `ScheduleAndBookingServiceProvider` (`Room` with file type, section, three tables, five timeline entries, morph map) registered in `bootstrap/providers.php:26`. ScheduleAndBookingBootTest, ScheduleAndBookingSyncTest and BookingFileCheckTest pass (D-36 and D-37 refusals included). |
| 6 | SC6: the booking page meets WCAG 2.2 AA (keyboard alone, screen reader, day-and-time names, focus to the confirmation, text errors, 24 px targets, 200% zoom); passes the automated check and the UAT walks; UI-SPEC carries it | VERIFIED (automated part; human walks pending, listed below) | `booking-page.blade.php` moves focus (`tabindex="-1" x-init="$el.focus()"`) to the check heading and to the confirmation `h1` only when `$justBooked`. BookingPageBrowserTest (real browser, in the 502-test run) books by keyboard at 375 and 1280 widths, checks target sizes (with a 47.5 px stub proving the check can fail) and runs axe on every state. 08-UI-SPEC.md carries the contract. 08-UI-REVIEW.md scored 18/24, warnings only. The keyboard, VoiceOver and 200% zoom walks are UAT hand checks and have not been run. |
| 7 | D-12 (locked decision): a copy of the database or business folder holds no working booking link | FAILED | See Gaps. The plain token is stored in `messages.body` and printed to `records/` by `CustomerCard`. |

**Score:** 6/7 truths verified (0 present, behavior-unverified)

### Required Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `app/Rooms/ScheduleAndBooking/Availability.php` | Server-side availability | VERIFIED | Substantive (193 lines), used by page, tap re-check and hand booking |
| `app/Actions/BookAppointment.php` | Locked hand and page booking, send rows, booked alert | VERIFIED | Lock, re-check, unique backstop, timeline entry, rows, alert |
| `app/Livewire/BookingPage.php` | Customer page | VERIFIED | Locked state, rate limit, fixed error line |
| `app/Actions/SendBookingText.php` | Scheduler-driven send | VERIFIED | Claim, re-checks, notes on refusal |
| `routes/console.php` | Scheduler registration | VERIFIED | Every-minute job, no overlap |
| `app/Rooms/ScheduleAndBooking/ScheduleAndBookingServiceProvider.php` | Room module | VERIFIED | Registered in bootstrap/providers.php |
| `app/Records/CustomerCard.php` | Appointments on the card | VERIFIED for appointments; HOLLOW on D-12 (prints the token) | |
| `app/Actions/OutboundSender.php` | Chokepoint for booking texts | VERIFIED for checks; stores the plain link (gap) | |

### Key Link Verification

| From | To | Via | Status | Details |
|------|----|-----|--------|---------|
| RunFrontDesk | MintBookingLink | `mintBookingLink->handle()` after the verdict, URL built by code from `route('booking.show')` | WIRED | RunFrontDesk.php:604-611 |
| SendOwnerReply | link line on approval | owner-approved draft gets the line (D-45) | WIRED | ApprovedDraftBookingLinkTest passes |
| BookingPage | BookAppointment::fromLink | `book()` | WIRED | BookingPage.php |
| BookAppointment | RaiseOwnerAlert | `alertOwner()` | WIRED | subject is the carrying message |
| BookAppointment | booking_sends rows | `writeSendRows()` | WIRED | rows created in the booking transaction |
| scheduler | SendBookingText | `SendDueBookingTexts` every minute | WIRED | routes/console.php:22 |
| SendBookingText | OutboundSender | booking text kind, opt-out first, D-21 rule | WIRED | OutboundBookingKindTest passes |
| appointment | project | `jobIdForPageBooking()`, `project_id` on the row | WIRED | |

### Data-Flow Trace (Level 4)

| Artifact | Data | Source | Real data | Status |
|----------|------|--------|-----------|--------|
| booking page picker | open starts | `Availability::openStarts()` over config, appointments and blocks | Yes | FLOWING |
| customer card visits | appointments | `$contact->appointments()` scoped query | Yes | FLOWING |
| schedule screen and dashboard section | appointments and blocks | scoped models | Yes | FLOWING |

### Behavioral Spot-Checks

| Behavior | Command | Result | Status |
|----------|---------|--------|--------|
| Booking, availability, send, alert, page, browser and room tests | `php artisan test --compact tests/Feature/Booking tests/Unit/Booking tests/Feature/Rooms/ScheduleAndBookingBootTest.php tests/Feature/Rooms/ScheduleAndBookingSyncTest.php tests/Feature/Rooms/BookingFileCheckTest.php tests/Unit/AppointmentWritesTest.php tests/Feature/Filament/SchedulePageTest.php tests/Feature/Filament/AppointmentsRelationManagerTest.php` | 502 passed, 2,974 assertions, 1 warning (no detail given), 79 s | PASS |
| The date-fragile "every day blocked" case that 08-14 deferred (it failed once the real date reached 2026-11-07) | included in the run above (BookingLinkTokenTest) | passes; fixed by commit 3002af9 | PASS |
| Full suite and `npm run build` | not re-run by me | the orchestrator reports 3007 tests, 21,457 assertions on SQLite, build passing | not re-verified |

### Probe Execution

Step 7c: SKIPPED. No `scripts/*/tests/probe-*.sh` exists and no plan declares a probe.

### Requirements Coverage

Every requirement ID the roadmap lists for Phase 8 appears in at least one plan's `requirements:` frontmatter and in REQUIREMENTS.md. No requirement maps to Phase 8 without a plan (no orphans). BOOK-06 maps to Phase 9 and is correctly absent.

| Requirement | Source plans | Description | Status | Evidence |
|-------------|--------------|-------------|--------|----------|
| ROOM-12 | 08-04, 08-14 | Schedule and booking room to the standard | SATISFIED | SC5 |
| BOOK-01 | 08-05, 08-06 | Availability from hours, closures, lead, duration, existing appointments, business timezone | SATISFIED | SC1 |
| BOOK-02 | 08-01, 08-07, 08-08, 08-09 | Link texted for `book_online` services; page; confirmed appointment without typing | SATISFIED as to function; the link handling falls short of D-12 (gap) | SC1, gap |
| BOOK-03 | 08-01, 08-06, 08-13, 08-14, 08-15, 08-16 | Appointment records on the timeline, the card and a schedule screen | SATISFIED | SC2 |
| BOOK-04 | 08-01, 08-02, 08-11, 08-12, 08-15 | Reminders sent by the scheduler | SATISFIED (WR-01, WR-02 open) | SC3 |
| BOOK-05 | 08-03, 08-07 | Owner alerted on booking and on a conversation needing a human | SATISFIED | SC4 |
| A11Y-02 | 08-08, 08-10, 08-13, 08-14 | Booking page WCAG 2.2 AA, automated check and UAT walks | SATISFIED for the automated part; UAT walks pending | SC6 |

### Anti-Patterns Found

| File | Line | Pattern | Severity | Impact |
|------|------|---------|----------|--------|
| app/Actions/OutboundSender.php | 257 | plain booking link stored as `messages.body` | BLOCKER | The D-12 gap |
| app/Records/CustomerCard.php | 367, 417 | sent body, link included, printed into `records/` | BLOCKER | The D-12 gap |
| resources/views/components/booking/footer.blade.php | 9 | "placeholder wording for the example business" shown as a privacy notice on every business's page (review WR-05) | WARNING | Real customers would read it; open for Ian's triage |
| app/Actions/SendBookingText.php | 122-149, 393-413 | resend possible after a crash between send and outcome write (WR-01) | WARNING | Affects "once" under failure |
| app/Actions/RunFrontDesk.php, SendOwnerReply.php | 625-643, 190-212 | link revoked after it reached the customer if a later step throws (WR-03) | WARNING | Customer's tap would fail |
| app/Actions/SendBookingText.php | 46, 56, 229-258 | refusal handling matches exception message text (WR-04) | WARNING | Fragile |

No TBD, FIXME or XXX marker appears in the phase's changed app, routes, resources, database, tests or examples files. 08-REVIEW.md: 0 critical, 5 warnings, 6 info, all open in 08-REVIEW-DISPOSITION.md.

### Human Verification Required

1. **Keyboard-only walk** of the booking page at desktop width and 375 px. Expected: every time reachable, booking completes, focus lands on the confirmation. Why human: automated checks catch only part of WCAG.
2. **VoiceOver walk.** Expected: each time announced with its day and time; focus moves to the confirmation; errors read beside the field.
3. **200% zoom.** Expected: page reflows and stays usable.
4. **Dev chat checks** (D-05 scheduling label with the link; D-39 "what time are you coming?"). Needs the live model.
5. **CI pgsql leg** after Ian pushes (WINDOWS.md unrun-verify entries).

### Other Notes

- `08-VALIDATION.md` frontmatter says `nyquist_compliant: false` while its sign-off checklist says it was set to `true` and the approval line says validated with 0 gaps. The frontmatter and the checklist disagree; fix the frontmatter when closing the phase.
- 08-16's audit lists one changed existing-test range no numbered plan named: `tests/Feature/FrontDesk/FrontDeskInvariantTest.php:188-198` (commit 4fe1d33, a Postgres speed fix; I confirmed the diff is 11 insertions and 1 deletion in that one file). Awaiting Ian's confirmation. Not a goal gap by itself.
- I did not re-run the full suite or `npm run build`; I re-ran the 502 tests above, including the real-browser tests.

### Gaps Summary

One gap blocks closing the phase. D-12 says a copy of the database holds no working booking link. Today the booking token is stored in plain text in `messages.body` and printed into the customer card in `records/`, so a copy of the database or the business folder holds working links until each expires, books or is superseded. Ian ruled on 2026-11-07 to fix this in a gap plan and keep D-12 as written. `/gsd-plan-phase 8 --gaps` should pick up the scope in the `gaps:` block above: every place the full link lands (messages.body, the card in records/, timeline and activity summaries, queued job payloads and failed_jobs, application logs), store and print the sent text with the token replaced while the customer still receives the real link, and a test that sends a link and then finds no working token in any table or in the records folder. The provider's own log is outside the platform's control and goes on the attorney list.

Everything else in the roadmap's six success criteria is present, wired and exercised by passing tests. The human walks and the CI Postgres leg remain for UAT, and the five open review warnings are for Ian's triage.

---

_Verified: 2026-11-07_
_Verifier: Claude (gsd-verifier)_
