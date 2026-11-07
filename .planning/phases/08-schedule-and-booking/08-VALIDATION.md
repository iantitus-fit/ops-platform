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
| **Estimated runtime** | ~275 seconds full on SQLite (3007 tests, 2026-11-07); Postgres runs in CI only, after Ian pushes |

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

---

## Validation Sign-Off

- [x] All tasks have `<automated>` verify or Wave 0 dependencies
- [x] Sampling continuity: no 3 consecutive tasks without automated verify
- [x] Wave 0 covers all MISSING references
- [x] No watch-mode flags
- [x] Feedback latency < 170s for the per-task quick runs; the full SQLite suite now takes about 275 s
- [x] `nyquist_compliant: true` set in frontmatter

**Approval:** validated 2026-11-07 (validate-phase audit at phase close: 0 gaps; every test file in the map exists and the full suite was green, 3007 tests)

## Validation Audit 2026-11-07

| Metric | Count |
|---|---|
| Gaps found | 0 |
| Resolved | 0 |
| Escalated | 0 |
