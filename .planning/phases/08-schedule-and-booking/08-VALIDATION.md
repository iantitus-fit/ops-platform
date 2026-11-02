---
phase: "8"
slug: "schedule-and-booking"
# status lifecycle: draft (seeded by plan-phase) → validated (set by validate-phase §6)
# audit-milestone §5.5 distinguishes NOT-VALIDATED (draft) from PARTIAL (validated + nyquist_compliant: false) (#2117)
status: draft
nyquist_compliant: false
wave_0_complete: false
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
| **Estimated runtime** | ~170 seconds full on SQLite; Postgres runs in CI only, after Ian pushes |

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
| TBD | TBD | TBD | BOOK-01 | TBD | Only times inside hours, outside closures, past the lead, long enough, in the business zone; none in the past; DST days; overlap, buffer (appointments only, A2) and capacity | unit/feature | `php artisan test --compact tests/Feature/Booking/AvailabilityTest.php` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | BOOK-02 | TBD | Link appended only under D-01's conditions, never written by the model; token stored hashed; superseded, revoked, expired, single-use states | feature | `php artisan test --compact tests/Feature/Booking/BookingLinkTest.php tests/Feature/Booking/BookingLinkTokenTest.php` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | BOOK-02 | TBD | Page renders each state; tap, check, book; a taken time gives a fresh list; rate limit trips on Livewire taps, not only the GET | Livewire feature | `php artisan test --compact tests/Feature/Booking/BookingPageTest.php tests/Feature/Booking/BookingPageThrottleTest.php` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | BOOK-03 | TBD | Appointment on the card, timeline, schedule page and section; two on one project show both; a contact with no appointment renders as before; the card never sees a block (A3) | feature | `php artisan test --compact tests/Feature/Booking/AppointmentCardTest.php tests/Feature/Booking/SchedulePageTest.php tests/Feature/Records/CustomerCardTest.php` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | BOOK-04 | TBD | Reminder due time moves to the window end, skipped silently if past, same-moment dedupe, DST; claim exactly once; cancelled never sends; wrapped by ComposeAutomatedText (A1) | unit/feature | `php artisan test --compact tests/Unit/Booking/ReminderScheduleTest.php tests/Feature/Booking/BookingSendTest.php tests/Feature/Booking/OutboundBookingKindTest.php tests/Feature/Booking/BookingScheduleTest.php` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | BOOK-05 | TBD | Booked alert once, after hours waits to the next opening, none for a hand booking; change-request alert ranks per D-34 | feature | `php artisan test --compact tests/Feature/Alerts/BookingAlertTest.php tests/Feature/Alerts/ChangeRequestAlertTest.php` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | D-04, D-41 | TBD | No inbound text creates or changes an appointment; a booking leaves the contact's status unchanged | feature | `php artisan test --compact tests/Feature/Booking/NoTextBookingTest.php tests/Feature/Booking/BookAppointmentTest.php` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | D-15, A3 | TBD | Only the named actions write appointment and block rows; an appointment row always has contact, project and service, a block row never does | arch scan + feature | `php artisan test --compact tests/Unit/AppointmentWritesTest.php` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | ROOM-12 | TBD | Boot test (empty and example), registered room, contract matches SPEC rows, axe on the section, D-36 and D-37 refusals | feature | `php artisan test --compact tests/Feature/Rooms/ScheduleAndBookingBootTest.php tests/Feature/Rooms/ScheduleAndBookingSyncTest.php` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | A11Y-02 | TBD | axe on the booking page in each state; Tab order; time buttons named with day and time; focus to the confirmation and to the error line; 48 px targets at 375; contract file scan | browser + file scan | `php artisan test --compact tests/Feature/Booking/BookingPageBrowserTest.php tests/Feature/Booking/BookingPageContractTest.php` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Pest.php` helpers: a booking-file fixture writer, an hours fixture that opens at 00:00 for DST, a booking-link factory path; the key-press helper moved here (IN-11)
- [ ] `tests/Fixtures/` booking and DST hours files
- [ ] Factories for the new models (appointment, block, booking link, booking send)
- [ ] CI: an `npm run build` step in `.github/workflows/tests.yml` so browser tests get real CSS (Ian pushes it)

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

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 170s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
