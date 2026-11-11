---
phase: "8"
slug: "schedule-and-booking"
# status lifecycle: draft (seeded by plan-phase) → validated (set by validate-phase §6)
# audit-milestone §5.5 distinguishes NOT-VALIDATED (draft) from PARTIAL (validated + nyquist_compliant: false) (#2117)
status: validated
nyquist_compliant: true
wave_0_complete: true
created: "2026-11-02"
---

# Phase 8 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest 5.x on PHPUnit, Laravel `RefreshDatabase`; pest-plugin-browser 5.1.2 with Playwright 1.63.0 for browser tests (locator waits only: `waitForFunction()` and `waitForURL()` never wait in 5.1) |
| **Config file** | `phpunit.xml` (suites Unit, Feature; SQLite `:memory:`); `tests/Pest.php` (fresh copy of `examples/example-hvac` per Feature test) |
| **Quick run command** | `php artisan test --compact <the task's narrowest test file>` |
| **Full suite command** | `php artisan test --compact` |
| **Estimated runtime** | ~275 seconds full on SQLite at 3007 tests (2026-11-07); 3126 tests after plan 08-23 (2026-11-08); 3205 tests after plan 08-26, about 332 seconds (2026-11-11); Postgres runs in CI only, after Ian pushes |

---

## Sampling Rate

- **After every task commit:** Run the task's new test file plus the existing files the task's shared-code sweep named (RESEARCH.md, "Existing tests the shared-code changes force"), on SQLite; Pint on dirty files
- **After every plan wave:** Run all of `tests/Feature/Booking`, `tests/Unit/AppointmentWritesTest.php`, and every existing file in the forced-tests table the wave touched; Ian pushes and CI proves SQLite and Postgres before the next wave
- **Before `/gsd-verify-work`:** Full suite green on SQLite, then the CI `pgsql` leg green
- **Max feedback latency:** 170 seconds

---

## Per-Task Verification Map

Seeded from RESEARCH.md "Phase Requirements → Test Map". The planner replaces the Plan, Wave and Task ID columns with the real ones; each task writes its failing test first (tdd_mode).

| Task ID | Plan | Wave | Requirement | Threat Ref | Secure Behavior | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|------------|-----------------|-----------|-------------------|-------------|--------|
| 08-01-T1, 08-01-T2, 08-01-T3 | 08-01 | 1 | BOOK-03 (records), D-15, A3 | T-08-01 to T-08-04 | Appointments and blocks in one table behind two scoped models; the database refuses a wrong-shaped row on both drivers; one booking per link; one send per lead time; the writes scan proven not vacuous | feature + arch scan | `php artisan test --compact tests/Feature/Booking/AppointmentTableTest.php tests/Unit/AppointmentWritesTest.php` | ✅ | ✅ green |
| 08-02-T1, 08-02-T2 | 08-02 | 2 | D-21 (BOOK-04) | T-08-05 | Automated sends refused to a never-texted, never-agreed contact at the chokepoint; opt-out first; owner and safety line unchanged; every existing test the rule reached found by probe and re-pointed by setup | feature | `php artisan test --compact tests/Feature/Booking/AutomaticTextsRuleTest.php tests/Feature/FrontDesk tests/Feature/Inbound` | ✅ | ✅ green |
| 08-02-T3 | 08-02 | 2 | BOOK-04 (D-26), D-01 | T-08-06 to T-08-09 | The booking text kind: no reading needed only when booked, ahead, owner parts, nothing unread; wait versus drop sentences; booking_link held to the owner-sentence rule | feature | `php artisan test --compact tests/Feature/Booking/OutboundBookingKindTest.php tests/Feature/FrontDesk/ReadingRequiredToSendTest.php` | ✅ | ✅ green |
| 08-03-T1, 08-03-T2, 08-03-T3 | 08-03 | 3 | BOOK-05, D-04 | T-08-10 to T-08-13 | Change request alerts once naming the visit, in its place in the one-alert order; booked alert's own words and timing; no text writes an appointment | feature | `php artisan test --compact tests/Feature/Alerts/ChangeRequestAlertTest.php tests/Feature/Alerts/BookingAlertTest.php tests/Feature/Booking/NoTextBookingTest.php` | ✅ | ✅ green |
| 08-04-T1, 08-04-T2, 08-04-T3 | 08-04 | 4 | ROOM-12 | T-08-14 to T-08-17 | Boot test (empty and example), contract matches SPEC, axe on the section; booking file checks; D-36 and D-37 refusals | feature | `php artisan test --compact tests/Feature/Rooms/ScheduleAndBookingBootTest.php tests/Feature/Rooms/BookingFileCheckTest.php tests/Feature/Rooms/ScheduleAndBookingSyncTest.php` | ✅ | ✅ green |
| 08-05-T1, 08-05-T2, 08-05-T3 | 08-05 | 5 | BOOK-01 | T-08-18 to T-08-20 | Only times inside hours, outside closures, past the lead (from the next opening after hours), long enough, in the business zone; none past; DST days; overlap, buffer on appointments only (A2), capacity | feature | `php artisan test --compact tests/Feature/Booking/AvailabilityTest.php tests/Feature/Icm/BusinessClockTest.php` | ✅ | ✅ green |
| 08-06-T1, 08-06-T2, 08-06-T3 | 08-06 | 6 | BOOK-03, D-41 | T-08-21 to T-08-25 | Book by hand: past and overlap refused beside the field under the lock; the job never guessed or reopened; status and alerts untouched; owner times in the business zone | feature | `php artisan test --compact tests/Feature/Booking/BookAppointmentTest.php tests/Feature/Filament/AppointmentsRelationManagerTest.php` | ✅ | ✅ green |
| 08-07-T1, 08-07-T2, 08-07-T3 | 08-07 | 7 | BOOK-02, BOOK-05 | T-08-26 to T-08-31 | Token stored hashed; superseded, revoked, expired, single-use states in U-14's order; D-23 job choice; booked alert once on the carrying text | feature | `php artisan test --compact tests/Feature/Booking/PageBookingTest.php tests/Feature/Booking/BookingLinkTokenTest.php` | ✅ | ✅ green |
| 08-08-T1, 08-08-T2 | 08-08 | 8 | BOOK-02, A11Y-02 | T-08-32 to T-08-37 | Page renders each state; tap, check, book; a taken time gives a fresh list; rate limit trips on Livewire taps, not only the GET; nothing personal shown | Livewire feature | `php artisan test --compact tests/Feature/Booking/BookingPageTest.php tests/Feature/Booking/BookingPageThrottleTest.php` | ✅ | ✅ green |
| 08-08-T3 | 08-08 | 8 | A11Y-02 | T-08-36 | UI-SPEC contract file scan (no inline style, hex, raw palette, off-scale sizes, arbitrary values, heroicons, exclamation points or em dashes) | file scan | `php artisan test --compact tests/Feature/Booking/BookingPageContractTest.php` | ✅ | ✅ green |
| 08-09-T1, 08-09-T2, 08-09-T4 | 08-09 | 9 | BOOK-02 | T-08-38 to T-08-41 | Link appended only under D-01's conditions, never written by the model; D-02 service resolution; revoke on refusal; supersede; a held draft records its service and never a link; D-05 line | feature | `php artisan test --compact tests/Feature/Booking/BookingLinkTest.php tests/Feature/FrontDesk` | ✅ | ✅ green |
| 08-09-T3 | 08-09 | 9 | BOOK-02 (D-45) | T-08-39 to T-08-41, T-08-69 | The link on an approved scheduling draft: added at send time, the box off, the box's starting state by reading with the owner deciding, every D-01 and D-02 condition failing at approval with its reason in the notice, no link the owner was not shown, length refusal, revoke, D-33 key; the checkbox by keyboard and axe | feature + browser | `php artisan test --compact tests/Feature/Booking/ApprovedDraftBookingLinkTest.php tests/Feature/Filament/DraftBookingLinkKeyboardTest.php` | ✅ | ✅ green |
| 08-10-T1, 08-10-T2, 08-10-T3 | 08-10 | 10 | A11Y-02 | T-08-42 to T-08-44 | Keyboard walk at desktop and 375; time names with the day; focus to check, confirmation and error; 48 px targets with 8 px gaps; axe on every state; 07 IN-08, IN-09, IN-11 | browser | `php artisan test --compact tests/Feature/Booking/BookingPageBrowserTest.php tests/Feature/Rooms/LeadsBootTest.php tests/Feature/Filament/ProjectsRelationManagerKeyboardTest.php` | ✅ | ✅ green |
| 08-11-T1, 08-11-T2, 08-11-T3 | 08-11 | 11 | BOOK-04 | T-08-45, T-08-46 | Reminder due time moves to the window end, skipped silently if past, same-moment dedupe, DST; page confirmation at once, hand confirmation inside the window | unit + feature | `php artisan test --compact tests/Unit/Booking/ReminderScheduleTest.php tests/Feature/Booking/BookingSendRowsTest.php` | ✅ | ✅ green |
| 08-12-T1, 08-12-T2, 08-12-T3 | 08-12 | 12 | BOOK-04 | T-08-47 to T-08-52 | Scheduler sends once through the chokepoint, wrapped by ComposeAutomatedText (A1); refusals note once, no alert; unread wait and drop; platform-started conversation; claim and lease | feature | `php artisan test --compact tests/Feature/Booking/BookingSendTest.php tests/Feature/Booking/BookingScheduleTest.php` | ✅ | ✅ green |
| 08-13-T1, 08-13-T2, 08-13-T3 | 08-13 | 13 | BOOK-03, A11Y-02 (D-43) | T-08-53 to T-08-57 | Cancel, mark done (with the optional close), visit note; close refused with a visit ahead; the tab by keyboard with focus kept, axe clean | feature + browser | `php artisan test --compact tests/Feature/Booking/AppointmentActionsTest.php tests/Feature/Projects/ProjectActionsTest.php tests/Feature/Filament/AppointmentsKeyboardTest.php` | ✅ | ✅ green |
| 08-14-T1, 08-14-T2, 08-14-T3 | 08-14 | 14 | BOOK-03, ROOM-12, A11Y-02 (D-43) | T-08-58 to T-08-61 | Schedule grouped by business day with two visits on one job; Show filter; Block time and Remove block; the section's next five; only the six named actions write appointments; keyboard and axe | feature + browser + arch scan | `php artisan test --compact tests/Feature/Filament/SchedulePageTest.php tests/Feature/Filament/ScheduleKeyboardTest.php tests/Feature/Rooms/ScheduleAndBookingBootTest.php tests/Unit/AppointmentWritesTest.php` | ✅ | ✅ green |
| 08-15-T1, 08-15-T2, 08-15-T3 | 08-15 | 15 | BOOK-04, BOOK-03 (D-19, D-20) | T-08-62 to T-08-65 | Add contact by hand with matching refusals; the automatic-texts switch with who and when; the reply-box line; a hand booking's confirmation follows the switch; Next appointment on the contact screen | feature + browser | `php artisan test --compact tests/Feature/Filament/ContactByHandTest.php tests/Feature/Booking/AutomaticTextsSwitchTest.php tests/Feature/Filament/ContactScreenKeyboardTest.php` | ✅ | ✅ green |
| 08-16-T1, 08-16-T2, 08-16-T3 | 08-16 | 16 | BOOK-03, D-41 | T-08-66 to T-08-68 | Appointment on the card and timeline; two on one project show both; a contact with no appointment renders as before; the card never sees a block (A3); the note quoted; no booking changes a status | feature | `php artisan test --compact tests/Feature/Booking/AppointmentCardTest.php tests/Feature/Booking/BookingStatusTest.php tests/Feature/Records` | ✅ | ✅ green |
| 08-17-T1, 08-17-T2, 08-17-T3, 08-17-T4 | 08-17 | 17 | BOOK-02, BOOK-03 (D-12, D-45) | T-08-01, T-08-27, T-08-70 to T-08-75, T-08-94 to T-08-96 | The customer gets the working link while every stored or printed copy says /book/[link]: messages, a failed send's record, the fake carrier's log, a pasted link, every records/ file, job payloads and the session's previous URL; a planted token proves the table search is not vacuous; a reply or draft holding /book/[link] is held or refused at the chokepoint before any row is written | feature | `php artisan test --compact tests/Feature/Booking/BookingLinkLeakTest.php tests/Feature/Booking/BookingLinkTest.php tests/Feature/Booking/ApprovedDraftBookingLinkTest.php` | ✅ | ✅ green |
| 08-18-T1, 08-18-T2 | 08-18 | 18 | BOOK-02 (D-12) | T-08-01, T-08-27, T-08-70, T-08-76 to T-08-79 | The one-time migration revokes every unbooked link sent before 08-17, leaves a booked link and an earlier revocation alone, rewrites no stored text, redraws the folder with no old token, logs its ids, changes nothing on a second run, and never fails on a card redraw that throws | feature | `php artisan test --compact tests/Feature/Booking/LinksBeforeTheFixTest.php` | ✅ | ✅ green |
| 08-19-T1, 08-19-T2, 08-19-T3 | 08-19 | 19 | BOOK-04 (WR-01, WR-02, IN-02) | T-08-80 to T-08-83 | A row whose sent-write failed is never sent again; a found message (sent, failed, queued, other conversation) ends the row; a row due at the window's close sends up to 5 minutes late; a visit that overtook its text leaves one note and no alert, a cancelled one none; only SendBookingText, OutboundSender and MessageKind name the booking text kind | feature + arch scan | `php artisan test --compact tests/Feature/Booking/BookingSendOnceTest.php tests/Unit/BookingTextKindTest.php` | ✅ | ✅ green |
| 08-20-T1, 08-20-T2, 08-20-T3 | 08-20 | 20 | BOOK-02, BOOK-04 (WR-03, WR-04) | T-08-37, T-08-84 to T-08-86 | A failure after the send never withdraws a delivered link, on the agent path and the approved-draft path; OutboundSender's refusal sentences are its constants and SendBookingText keeps no copy; each of the 13 booking refusals carries its reason and the page decides by the reason, not the words | feature | `php artisan test --compact tests/Feature/Booking/RefusalSentencesTest.php tests/Feature/Booking/BookingLinkTest.php tests/Feature/Booking/ApprovedDraftBookingLinkTest.php tests/Feature/Booking/BookingPageTest.php` | ✅ | ✅ green |
| 08-21-T1, 08-21-T2 | 08-21 | 21 | BOOK-02, A11Y-02 (WR-05, UI-FOOTER-HOVER) | T-08-87, T-08-88 | The privacy notice states only what the page does on every state; no hover border sits on an element without a border (with a snippet case against a vacuous pass); the footer disclosure's border turns to the accent on hover in Chromium | feature + file scan + browser | `php artisan test --compact tests/Feature/Booking/BookingPageTest.php tests/Feature/Booking/BookingPageContractTest.php tests/Feature/Booking/BookingPageBrowserTest.php` | ✅ | ✅ green |
| 08-22-T1, 08-22-T2 | 08-22 | 22 | BOOK-02, A11Y-02 (IN-05) | T-08-89, T-08-90 | Try again under the rate-limited notice keeps the notice inside the minute with no extra attempt and brings the times back after it; it cannot get around the limit; focus goes to the notice, then Try again, then the heading of what the page shows; the 429 page is unchanged | Livewire feature + browser | `php artisan test --compact tests/Feature/Booking/BookingPageThrottleTest.php tests/Feature/Booking/BookingPageBrowserTest.php` | ✅ | ✅ green |
| 08-23-T1, 08-23-T2, 08-23-T3 | 08-23 | 23 | BOOK-03 (UI-ZONE-NAME, IN-03, IN-04, IN-01) | T-08-25, T-08-91 to T-08-93 | Block time and Book by hand name the zone in plain words, never the raw id; Book by hand forecasts no confirmation for a customer with no phone; a span over midnight is under Upcoming until it ends and under Past only after, never both; Availability's docblocks match the drive buffer (grep prints 2) | feature | `php artisan test --compact tests/Feature/Filament/SchedulePageTest.php tests/Feature/Filament/AppointmentsRelationManagerTest.php` | ✅ | ✅ green |
| 08-24-T1, 08-24-T2, 08-24-T3 | 08-24 | 24 | ROOM-12, BOOK-03 (G-08-4, D-39 amended) | T-08-17, T-08-97 to T-08-101 | A booked customer's scheduling reply goes out as the model's words plus the owner's visit_line, added by code after the verdict and never seen by Jev; OutboundSender's visit_line arm needs a clean reading; the sync checks visit_line like the other booking lines and never quotes it | feature + arch scan | `php artisan test --compact tests/Feature/Booking/VisitLineTest.php tests/Feature/Booking/BookingLinkTest.php tests/Feature/Rooms/BookingFileCheckTest.php tests/Unit/AppointmentWritesTest.php` | ✅ | ✅ green |
| 08-25-T1, 08-25-T2, 08-25-T3 | 08-25 | 25 | BOOK-03 (G-08-4 held draft, D-45 pattern) | T-08-102 to T-08-104 | A held draft for a booked contact offers the visit line as an owner-checked box; every condition re-checked at approval; past 1,600 characters Send is refused; the line comes only from the server-locked property; keyboard reach and axe in real Chromium | feature + browser | `php artisan test --compact tests/Feature/Booking/ApprovedDraftVisitLineTest.php tests/Feature/Filament/DraftVisitLineKeyboardTest.php` | ✅ | ✅ green |
| 08-26-T1, 08-26-T2 | 08-26 | 26 | BOOK-02, BOOK-03 (G-08-4, G-08-4b) | T-08-105 to T-08-107 | The front desk reads that a booked visit is the business's and that code adds its day and time; the Booking section lists services booked online for an unbooked customer and is left out otherwise; no /book/, no `!`, no em dash | feature | `php artisan test --compact tests/Feature/FrontDesk/BookingSectionTest.php tests/Feature/FrontDesk/FrontDeskPromptTest.php` | ✅ | ✅ green |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [x] Factories for the new models (appointment, block, booking link, booking send) and ContactFactory::agreedToAutomaticTexts(): plan 08-01
- [x] `tests/Pest.php` helper textedBusinessBefore(): plan 08-02 (not needed: 08-02 derives the fact with `Contact::hasTextedBusiness()`); exampleFolderFileCount() 28: plan 08-04
- [x] DST hours and booking-file fixtures written per test with writeIcmFile() (no tests/Fixtures folder needed): plans 08-05 and 08-11
- [x] `tests/Pest.php` pressKey(), focusedName(), expectFocusOn() (IN-08, IN-11) and pageAxeReport(): plan 08-10
- [x] CI: an `npm run build` step in `.github/workflows/tests.yml` so browser tests get real CSS: plan 08-08 (Ian pushes it)

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Keyboard-only walk of the booking page | A11Y-02 | Automated checks catch only part of WCAG (CLAUDE.md) | At UAT: open a booking link, book a time by keyboard alone, at desktop and 375 px |
| VoiceOver walk of the booking page | A11Y-02 | Screen reader output is not machine-checkable here | At UAT: each time is announced with its day and time; focus lands on the confirmation |
| 200% zoom | A11Y-02 | Reflow judged by eye | At UAT: the page stays usable at 200% |
| Dev chat: offer comes back labeled `scheduling` with the link (D-05); "what time are you coming?" (D-39) | BOOK-02 | Needs the live model | At UAT, recorded by executors for end-of-phase UAT, never paused on |
| CI `pgsql` leg for BookingLinkLeakTest (`pg_class` table listing, `meta->booking_link_id` read), LinksBeforeTheFixTest (the migration's whereNotExists update and join) and BookingSendOnceTest (`meta->booking_send_id` read, the `DB::beforeExecuting` SQL match) | BOOK-02, BOOK-04 | No Postgres locally; CI runs only after Ian pushes | After Ian pushes the phase branch: the CI `pgsql` leg is green for these three files (WINDOWS entries 74 and 77) |
| Keyboard-only and VoiceOver walk of the footer disclosures and of the rate-limited notice with Try again | A11Y-02 | Announcements and the feel of focus moves are not asserted by any test | At UAT (WINDOWS entries 55, 61 and 78): the notice is announced when it appears, Try again is reachable by Tab, focus lands on the heading of what the page shows |
| Final privacy and accessibility wording on the booking page | BOOK-02 | An attorney decision (D-44), not a test | Stays on the attorney list with the facts 08-21 recorded |
| Dev chat re-walk after `icm:sync`: a booked customer asks when the visit is and the reply carries the visit line; an unbooked customer is offered the link; a held reply on a booked contact is approved with the visit line box (08-24 to 08-26) | BOOK-02, BOOK-03 | Needs the live model and a phone | At UAT (WINDOWS entry 79): the four-part re-walk 08-26 recorded |

---

## Validation Sign-Off

- [x] All tasks have `<automated>` verify or Wave 0 dependencies
- [x] Sampling continuity: no 3 consecutive tasks without automated verify
- [x] Wave 0 covers all MISSING references
- [x] No watch-mode flags
- [x] Feedback latency < 170s for the per-task quick runs; the full SQLite suite now takes about 275 s
- [x] `nyquist_compliant: true` set in frontmatter

**Approval:** validated 2026-11-07 (validate-phase audit at phase close: 0 gaps; every test file in the map exists and the full suite was green, 3007 tests)

**Re-approval:** validated 2026-11-08 (validate-phase re-audit after gap-closure plans 08-17 to 08-23: 0 gaps; every task in those plans has an automated verify, every test file in the map exists, the 13 files those plans added or changed ran green (311 tests), and the full suite was green, 3126 tests)

**Re-approval:** validated 2026-11-11 (validate-phase re-audit after gap-closure plans 08-24 to 08-26: 0 gaps; every task in those plans has an automated verify, every test file in the map exists, the 8 files those plans added or changed ran green (193 tests), and the full suite was green, 3205 tests)

## Validation Audit 2026-11-07

| Metric | Count |
|---|---|
| Gaps found | 0 |
| Resolved | 0 |
| Escalated | 0 |
