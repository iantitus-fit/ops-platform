---
phase: 08-schedule-and-booking
plan: 06
subsystem: booking
tags: [book-by-hand, appointments, cache-lock, filament-relation-manager, dst, pest]

requires:
  - phase: 08-schedule-and-booking
    provides: "Availability::isFree() and the booking file's buffer (08-04, 08-05), the appointments table and scoped models (08-01), Contact::mayGetAutomaticTexts() (08-02)"
provides:
  - "App\\Actions\\BookAppointment::byHand(): the lock, the re-check, the job choice and one timeline entry"
  - "App\\Rooms\\ScheduleAndBooking\\BookingRefused: plain-word refusals that name their field"
  - "AppointmentsRelationManager: the contact screen's Appointments tab and Book by hand dialog, registered after Projects"
  - "The writes scan's first allowed writer and the arch rule keeping App\\Ai and App\\SendRule away from BookAppointment"
affects: [08-07, 08-08, 08-11, 08-12, 08-13, 08-14, booking-page, schedule-page]

actuals:
  tokens: 13288
  tasks: 3
  commits: 5
plan_head_before: 986ab63cafabc077afbaa432b3aeb17f9a5e1401
plan_head_after: 3a39d37ddeaa5822e6e59a9f8f01c2d9f94da733

tech-stack:
  added: []
  patterns:
    - "A refusal that names its field becomes a ValidationException on the open action dialog's state path, shown beside the field"
    - "Cache::lock('schedule:book') around one DB::transaction that re-checks isFree(); the cache lock is the guard on SQLite and Postgres"
    - "A required Select with an explicit 'new' option, so an unpicked job is refused rather than guessed"

key-files:
  created:
    - app/Actions/BookAppointment.php
    - app/Rooms/ScheduleAndBooking/BookingRefused.php
    - app/Filament/Resources/Contacts/RelationManagers/AppointmentsRelationManager.php
    - tests/Feature/Booking/BookAppointmentTest.php
    - tests/Feature/Filament/AppointmentsRelationManagerTest.php
  modified:
    - app/Filament/Resources/Contacts/ContactResource.php
    - tests/Feature/Filament/ProjectsRelationManagerTest.php
    - tests/Unit/AppointmentWritesTest.php
    - docs/BLUEPRINT.md
    - docs/sprints/sprint-1.1.md

key-decisions:
  - "The Book by hand modal heading is not wrapped in e(): Filament prints a modal heading through Blade's escaping, so e() would double-escape a name with an apostrophe. Notification titles keep e() because they render as HTML."
  - "BookAppointment writes the timeline entry inside the same transaction as the row, so a booked visit and its entry store together or not at all."
  - "The Job select's default is computed when the dialog opens: the only open job, 'new' with none, nothing with two or more."

patterns-established:
  - "Standalone relation-manager tests do not draw the action modal; assert the mounted schema's HTML (appointmentsTabDialogHtml) and the error bag keyed by '*.field'"

requirements-completed: [BOOK-03, BOOK-01]

coverage:
  - id: D1
    description: "The owner books by hand from the contact screen: the appointment lands on the chosen job with its span, service copy, booked_by user, and one timeline entry naming who booked"
    requirement: BOOK-03
    verification:
      - kind: unit
        ref: "tests/Feature/Booking/BookAppointmentTest.php#books a visit by hand on the open job"
        status: pass
      - kind: integration
        ref: "tests/Feature/Filament/AppointmentsRelationManagerTest.php#books by hand from the Appointments tab"
        status: pass
    human_judgment: false
  - id: D2
    description: "Past times and overlaps (drive buffer, blocks) are refused beside Starts; hours and lead do not apply; the check repeats under the lock and a held lock refuses with nothing stored"
    requirement: BOOK-01
    verification:
      - kind: unit
        ref: "tests/Feature/Booking/BookAppointmentTest.php#refuses an overlap with a booked visit and its drive buffer, and accepts one that starts where the buffer ends"
        status: pass
      - kind: unit
        ref: "tests/Feature/Booking/BookAppointmentTest.php#refuses while another booking holds the lock, and two overlapping bookings never both store"
        status: pass
      - kind: integration
        ref: "tests/Feature/Filament/AppointmentsRelationManagerTest.php#refuses an overlap beside Starts, drive buffer included"
        status: pass
    human_judgment: false
  - id: D3
    description: "The job choice never guesses or reopens: required select with defaults, 'Pick a job.' beside Job opens nothing, New is explicit, a closed or foreign job is refused"
    requirement: BOOK-03
    verification:
      - kind: integration
        ref: "tests/Feature/Filament/AppointmentsRelationManagerTest.php#refuses an unpicked job beside Job instead of opening one"
        status: pass
      - kind: unit
        ref: "tests/Feature/Booking/BookAppointmentTest.php#opens a new job when asked and never reopens a closed one"
        status: pass
      - kind: unit
        ref: "tests/Feature/Booking/BookAppointmentTest.php#refuses a closed job and another customer's job"
        status: pass
    human_judgment: false
  - id: D4
    description: "Owner-typed times are read in the business zone on both daylight-saving change days"
    requirement: BOOK-01
    verification:
      - kind: integration
        ref: "tests/Feature/Filament/AppointmentsRelationManagerTest.php#reads the owner's time in the business zone on both change days"
        status: pass
    human_judgment: false
  - id: D5
    description: "The confirmation-status line, blocks kept off the tab, an escaped hostile note, no Filament write action, and the arch rule keeping agent code from BookAppointment"
    requirement: BOOK-03
    verification:
      - kind: integration
        ref: "tests/Feature/Filament/AppointmentsRelationManagerTest.php#says whether a confirmation text will go out"
        status: pass
      - kind: unit
        ref: "tests/Unit/AppointmentWritesTest.php#no agent code books, cancels or marks a visit done"
        status: pass
    human_judgment: false
  - id: D6
    description: "The keyboard-only and screen reader walk of the Appointments tab and Book by hand dialog, and the CI pgsql leg for the lock, overlap and project row lock"
    verification: []
    human_judgment: true
    rationale: "Hand checks go to end-of-phase UAT (Ian's standing instruction); recorded in .planning/WINDOWS.md as unrun-verify entries; Postgres needs Ian to push the phase branch"

duration: 12min
completed: 2026-11-05
status: complete
---

# Phase 8 Plan 06: Book by hand Summary

**The owner books a visit by hand from the contact screen's new Appointments tab: BookAppointment::byHand() takes the schedule:book lock, re-checks the time in a transaction, picks or opens the job without guessing, and writes the row plus one timeline entry, with every refusal shown beside its field.**

## Performance

- **Duration:** 12 min
- **Started:** 2026-11-05T17:36:00Z
- **Completed:** 2026-11-05T17:48:00Z
- **Tasks:** 3
- **Files modified:** 10 (5 created, 5 modified), plus the WINDOWS ledger and planning state in the metadata commit

## Accomplishments
- `BookAppointment::byHand()` is the first named writer of the appointments table: room check, service from the folder (copied onto the row, D-42), past-time and note checks, then `Cache::lock('schedule:book')` and one transaction that re-checks `Availability::isFree()` with the booking file's buffer (0 with no file), chooses the job, creates the appointment for the contact and writes the `visit_booked` entry: "Booked by Ian Owner: AC repair, Thursday, November 5 at 10:00 AM."
- `BookingRefused` carries each refusal's sentence and the field it belongs to; the tab turns a field refusal into a validation error on that field and a field-less one (room off, lock held) into a notice.
- `AppointmentsRelationManager` lists appointments only (Time in the business zone, Service, the job's label in the business's word, a Status badge, the wrapped escaped Visit note), draws with the page, shows "No appointments yet" / "Use Book by hand to add one.", and offers Book by hand (Service, a native Starts date-time read in the business zone, a required Job select, a 200-character Visit note, and a status line saying whether a confirmation text will go out).
- The Job select defaults to the only open job, to New with none and to nothing with two or more; leaving it unpicked is refused beside Job with "Pick a job." and opens nothing.
- `AppointmentWritesTest` now allows exactly `app/Actions/BookAppointment.php`, and an arch rule keeps `App\Ai` and `App\SendRule` from using it. BLUEPRINT section 4 and the sprint walk-through are updated.

## TDD Gate Compliance

All three tasks have a RED commit before any GREEN commit; Task 2 has no GREEN commit because its code already existed.

- **Task 1 RED (72ae69c):** the tests failed on the planned behavior. `BookAppointment` and `AppointmentsRelationManager` did not exist, `getRelations()` lacked the tab, and the writes scan found no writer. Semantic assessment: the failures are the missing behavior, not a load or syntax fault in the tests; a missing class is how an absent action shows in a Laravel container resolve. GREEN ba9f2e7.
- **Task 2 RED (751f15d):** every one of the 18 new cases already passed against Task 1's code, because Task 1's action and form were written with the full rule set. Unexpected green was investigated, not waved through: two mutations (parsing the picker value in the business zone instead of the app zone; dropping the `closed_at` filter) each made named cases fail, so the tests are not hollow. No code change was needed, so there is no Task 2 feat commit.
- **Task 3 RED (6112bcb):** the four status-line cases failed on the planned assertion (the sentence was absent). The blocks, hostile note, no-write-action and arch cases already passed (the scoped relation, Blade escaping and Task 1's file shape provide them). GREEN 3a39d37, which also corrects how the status tests look at the dialog (see Deviations).

## Task Commits

1. **Task 1 (tracer): the owner books by hand** - `72ae69c` (test), `ba9f2e7` (feat)
2. **Task 2: the hand-booking rules** - `751f15d` (test; no code change needed)
3. **Task 3: status line, guards, docs** - `6112bcb` (test), `3a39d37` (feat)

**Plan metadata:** the docs(08-06) commit that follows this file.

## Files Created/Modified
- `app/Actions/BookAppointment.php` - `byHand()`, the lock, the re-check, the job choice, the timeline entry
- `app/Rooms/ScheduleAndBooking/BookingRefused.php` - field-naming refusals
- `app/Filament/Resources/Contacts/RelationManagers/AppointmentsRelationManager.php` - the tab and Book by hand
- `app/Filament/Resources/Contacts/ContactResource.php` - registers the tab after Projects
- `tests/Feature/Booking/BookAppointmentTest.php` - 16 cases at the action
- `tests/Feature/Filament/AppointmentsRelationManagerTest.php` - 22 cases at the tab
- `tests/Feature/Filament/ProjectsRelationManagerTest.php` - the one planned line, `getRelations()` now lists the new tab
- `tests/Unit/AppointmentWritesTest.php` - the allowed writer and the agent-code arch rule
- `docs/BLUEPRINT.md`, `docs/sprints/sprint-1.1.md` - Built bullet and walk-through line

## Decisions Made
- The modal heading is plain text, not `e()`: Blade escapes a modal heading, so `e()` would double-escape a name with an apostrophe. Notification titles keep `e()` (they render as HTML, the ProjectsRelationManager precedent).
- The `visit_booked` entry is written inside the booking transaction, so a visit and its entry store together.
- The Job select's default is computed per opening of the dialog from the contact's open jobs.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] The changed line in ProjectsRelationManagerTest moved from 153 to 154**
- **Found during:** Task 1 (RED)
- **Issue:** Pint will not leave `AppointmentsRelationManager::class` unimported or the import grouped, and the new `use` line sits above the test, so the line the plan names as :153 is now :154. The plan's `sed -n 153p` criterion is therefore met by the content on line 154, not by the line number.
- **Fix:** One added import and the one changed `expect(...)` line; nothing else in that file changed. `git diff --diff-filter=M` over `tests` lists exactly ProjectsRelationManagerTest.php and AppointmentWritesTest.php.
- **Files modified:** tests/Feature/Filament/ProjectsRelationManagerTest.php
- **Commit:** 72ae69c

**2. [Rule 1 - Bug] The status-line tests asserted on a modal the standalone tab test never draws**
- **Found during:** Task 3 (GREEN)
- **Issue:** `Livewire::test(AppointmentsRelationManager::class)` does not render the action modal, so `assertSee()` could not see the new line even with the feature in place.
- **Fix:** The status cases read the mounted action schema's rendered HTML (`appointmentsTabDialogHtml()`), which still failed in RED for the planned reason and passes now.
- **Files modified:** tests/Feature/Filament/AppointmentsRelationManagerTest.php
- **Commit:** 3a39d37

**3. [Rule 1 - Bug] The plan's `e()` on the modal heading would double-escape**
- **Found during:** Task 1 (GREEN)
- **Issue:** Filament renders a modal heading through Blade's escaping, so `e()` shows `&#039;` for an apostrophe in a contact's name.
- **Fix:** Plain text heading; escaping is Blade's. T-08-23's escaping still holds for the visit note (tested with a hostile note) and the notification title.
- **Files modified:** app/Filament/Resources/Contacts/RelationManagers/AppointmentsRelationManager.php
- **Commit:** ba9f2e7

---

**Total deviations:** 3 auto-fixed (one Rule 3 about a line number, two Rule 1 in code or tests this plan wrote)
**Impact on plan:** None on scope or behavior.

## Issues Encountered
- Task 2's RED could not fail: Task 1 already satisfied every case. Recorded above with the mutation evidence rather than forcing a red.
- The cross-process race is covered by the lock's design (a shared cache store), as the plan says, not by a concurrent test; the lock-held and sequential-overlap cases prove the refusal paths.

## Human checks carried (not run, never a pause)
- After Ian pushes the phase branch, the CI pgsql leg should be green, in particular BookAppointmentTest's lock and overlap cases and the projects row lock. Recorded in `.planning/WINDOWS.md` as an unrun-verify entry.
- Keyboard-only and screen reader walk of the Appointments tab and the Book by hand dialog (native date-time input, the Job select, errors beside Starts, Job and Visit note, the status line), per CLAUDE.md's accessibility rule. Recorded in `.planning/WINDOWS.md` as an unrun-verify entry.

## Verification
- `php artisan test --compact tests/Feature/Booking tests/Feature/Filament tests/Feature/Projects tests/Unit/AppointmentWritesTest.php tests/Unit/ProjectWritesTest.php`: passed (the Task 3 run of Filament, Booking and the two Unit files: 287 passed).
- Full `php artisan test --compact` on SQLite: 2630 passed, 18924 assertions, 0 failed.
- `vendor/bin/pint --dirty --format agent` clean before every code commit.
- Acceptance checks run: the `Cache::lock('schedule:book'` and `byHand` signature greps, 0 `use App\Models\Project;` in BookAppointment, `AppointmentsRelationManager::class` in ContactResource, the Task 2 and Task 3 greps, and the BASE diff of modified tests (exactly the two files the plan names).

## Known Stubs
None.

## Threat Flags
None. T-08-21 (double booking) is mitigated by the lock plus the re-check, tested by a held lock and sequential overlaps; T-08-22 by reading the job through the contact's own projects with `closed_at` null under a row lock (a foreign job id and a closed one are both refused in tests); T-08-23 by Blade escaping and `e()` on titles (hostile note tested); T-08-24 by the arch rule and the writes scan; T-08-25 by the business-zone picker and both change-day tests.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- 08-07 can reuse the lock name and the re-check pattern for the customer's booking, and 08-11 and 08-12 can send the confirmation the status line forecasts.
- The Postgres run and the keyboard and screen reader walk wait for end-of-phase UAT.

## Self-Check: PASSED

---
*Phase: 08-schedule-and-booking*
*Completed: 2026-11-05*
