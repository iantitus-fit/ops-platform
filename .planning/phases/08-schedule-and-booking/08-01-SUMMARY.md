---
phase: 08-schedule-and-booking
plan: 01
subsystem: database
tags: [laravel, eloquent, appointments, sqlite, postgres, check-constraint, global-scope]

requires:
  - phase: 07-the-spine-and-leads
    provides: projects table and Contact/Project models (the link lives on the appointment, 07 D-09)
provides:
  - appointments table holding appointments and blocks (A3), behind two scoped models
  - booking_links (token hash only), booking_sends (one row per appointment and lead time)
  - contacts.automatic_texts_on_at and automatic_texts_on_by_user_id (off by default)
  - database-enforced per-kind row shape on Postgres (CHECK) and SQLite (triggers)
  - tests/Unit/AppointmentWritesTest.php scan with an empty allowed-writer list
affects: [08-02, 08-04, 08-06, 08-12, 08-13, 08-14, phase-09]

plan_head_before: ac9d8b0908e7093501e5c7d2437223d76bd1f6cf
plan_head_after: 898d36682d0cde59f3d05a2896e0377f68ef2479

actuals:
  tokens: 16000
  tasks: 3
  commits: 6

tech-stack:
  added: []
  patterns:
    - "Two Eloquent models over one table, each with a 'kind' global scope and a creating hook that sets its own kind"
    - "Per-kind row shape held in the database: Postgres CHECK, SQLite BEFORE INSERT/UPDATE abort triggers, one shared expression in the migration"
    - "Claim and outcome columns kept out of Fillable (OwnerAlert precedent)"

key-files:
  created:
    - database/migrations/2026_11_02_000001_create_booking_links_table.php
    - database/migrations/2026_11_02_000002_create_appointments_table.php
    - database/migrations/2026_11_02_000003_add_kind_shape_check_to_appointments_table.php
    - database/migrations/2026_11_02_000004_create_booking_sends_table.php
    - database/migrations/2026_11_02_000005_add_automatic_texts_to_contacts_table.php
    - app/Enums/AppointmentKind.php
    - app/Enums/AppointmentState.php
    - app/Enums/AppointmentActor.php
    - app/Models/Appointment.php
    - app/Models/ScheduleBlock.php
    - app/Models/BookingLink.php
    - app/Models/BookingSend.php
    - database/factories/AppointmentFactory.php
    - database/factories/ScheduleBlockFactory.php
    - database/factories/BookingLinkFactory.php
    - database/factories/BookingSendFactory.php
    - tests/Feature/Booking/AppointmentTableTest.php
    - tests/Unit/AppointmentWritesTest.php
  modified:
    - app/Models/Contact.php
    - app/Models/Project.php
    - database/factories/ContactFactory.php
    - docs/BLUEPRINT.md
    - docs/sprints/sprint-1.1.md

key-decisions:
  - "The shape constraint is one expression kept in the migration, used as a Postgres CHECK and, with NEW. prefixes, in two SQLite triggers (A3, planner's choice of how)"
  - "A removed block keeps its row as state cancelled with who removed it, rather than being deleted (D-15)"
  - "Contact's automatic-texts columns record only the owner's switch; the customer's turn-on is derived from inbound messages in 08-02, not stored (research A7, flagged for Ian in the plan)"

patterns-established:
  - "Scoped sibling models: Contact::appointments() and Project::appointments() return the scoped Appointment, so a block can never reach the card, timeline or contact screen"
  - "Appointment-writes scan: appointmentWritesIn() / appointmentWritersUnderApp(); each action plan adds its file to the list"

requirements-completed: [BOOK-03, BOOK-02, BOOK-04]

coverage:
  - id: D1
    description: "Appointments and blocks live in one table behind two scoped models; start and end round-trip in UTC; Contact and Project read only appointments"
    requirement: BOOK-03
    verification:
      - kind: integration
        ref: "tests/Feature/Booking/AppointmentTableTest.php#stores an appointment for a job and reads its span back in UTC"
        status: pass
      - kind: integration
        ref: "tests/Feature/Booking/AppointmentTableTest.php#keeps each kind to its own model"
        status: pass
      - kind: integration
        ref: "tests/Feature/Booking/AppointmentTableTest.php#lists zero, one and two appointments per job"
        status: pass
    human_judgment: false
  - id: D2
    description: "The database refuses a row of the wrong shape (ten cases) and accepts a right one, on SQLite"
    requirement: BOOK-03
    verification:
      - kind: integration
        ref: "tests/Feature/Booking/AppointmentTableTest.php#refuses a row of the wrong shape"
        status: pass
      - kind: integration
        ref: "tests/Feature/Booking/AppointmentTableTest.php#accepts a row of the right shape"
        status: pass
    human_judgment: false
  - id: D3
    description: "The same CHECK constraint holds on Postgres"
    requirement: BOOK-03
    verification: []
    human_judgment: true
    rationale: "Local runs are SQLite only; the Postgres leg runs in CI after Ian pushes the phase branch (WINDOWS entry 46)"
  - id: D4
    description: "A booking link stores only its hash and carries at most one appointment; each appointment and lead time has one send; contacts start with automatic texts off"
    requirement: BOOK-02
    verification:
      - kind: integration
        ref: "tests/Feature/Booking/AppointmentTableTest.php#keeps the token hash out of arrays"
        status: pass
      - kind: integration
        ref: "tests/Feature/Booking/AppointmentTableTest.php#refuses a second appointment through the same booking link"
        status: pass
      - kind: integration
        ref: "tests/Feature/Booking/AppointmentTableTest.php#keeps one send per appointment and lead time"
        status: pass
      - kind: integration
        ref: "tests/Feature/Booking/AppointmentTableTest.php#starts every contact with automatic texts off"
        status: pass
    human_judgment: false
  - id: D5
    description: "Nothing under app/ writes appointments, and the scan is proven to look"
    requirement: BOOK-04
    verification:
      - kind: unit
        ref: "tests/Unit/AppointmentWritesTest.php#only the named actions write the appointments table"
        status: pass
      - kind: unit
        ref: "tests/Unit/AppointmentWritesTest.php#flags every kind of write, so the scan is not vacuous"
        status: pass
    human_judgment: false

duration: 12min
completed: 2026-11-03
status: complete
---

# Phase 8 Plan 01: Booking records Summary

**One `appointments` table for appointments and blocks behind two globally scoped models, hashed-token booking links, one-per-lead-time send rows, per-kind row shape enforced by a Postgres CHECK and SQLite triggers, and a write-scan guarding the table**

## Performance

- **Duration:** 12 min
- **Started:** 2026-11-03T17:28:00Z
- **Completed:** 2026-11-03T17:40:00Z
- **Tasks:** 3
- **Files modified:** 23 (18 created, 5 modified)

## Accomplishments

- Appointments and blocks share one table; `Appointment` and `ScheduleBlock` each add a `kind` global scope and set their own kind on create, so `$contact->appointments` and `$project->appointments` can never return a block (A3).
- Start and end are UTC timestamps; the service slug and name are copied at booking; `booked_by`, `cancelled_by` and the user ids are stored on every row; no address column exists (D-06, D-15, D-42).
- `booking_links` stores only the sha256 token hash (hidden from arrays); `appointments.booking_link_id` is unique so a link books at most once; `booking_sends` is unique on `(appointment_id, lead_minutes)` with the owner-alert claim columns (D-12, D-13, D-31).
- `contacts` gains `automatic_texts_on_at` and `automatic_texts_on_by_user_id`, empty for every contact (D-20).
- The database refuses a wrong-shaped row: a CHECK constraint on Postgres and BEFORE INSERT/UPDATE abort triggers on SQLite, from one shared expression.
- `AppointmentWritesTest` scans `app/` for every way of writing the table, allows no writer yet, and proves each rule fires with 16 snippets.

## Task Commits

1. **Task 1 (tracer): appointment and block round-trip, each in its own model**
   - RED `43f9c66` (test)
   - GREEN `aab3041` (feat)
2. **Task 2: database refuses wrong shapes, one booking per link, one send per lead time, switch columns**
   - RED `38960ad` (test)
   - GREEN `716fbdb` (feat)
3. **Task 3: appointment-writes scan, BLUEPRINT and walk-through log**
   - `4c81e4a` (test)
   - `898d366` (docs)

**Plan metadata:** the docs(08-01) completion commit follows this SUMMARY.

## TDD Gate Compliance

- **RED, Task 1 (`43f9c66`):** 7 cases ran and all 7 errored with `Class "App\Models\Appointment" not found` / `"App\Models\BookingLink" not found`. The plan named this RED ("no table, no models"). Semantic assessment: the target tests executed and failed because the units under test did not exist, which is the intended reason, though it is a missing-class error rather than an assertion failure. Pest's JSON line is not a report format `gsd_run check tdd-red-evidence` reads, so the classifier was not run; this is self-assessed.
- **RED, Task 2 (`38960ad`):** 21 cases ran, 9 passed, 12 failed. The 10 wrong-shape cases failed on the planned assertion (`Exception [Illuminate\Database\QueryException] not thrown`); the sends case failed with `BookingSend` not found; the contact case failed with `agreedToAutomaticTexts()` undefined. The same-booking-link case and the right-shape case passed from the start, as the plan said of the former: Task 1's unique index already pinned D-13, so it is a guard, not a RED case.
- **GREEN:** both feat commits (`aab3041`, `716fbdb`) turned their RED cases green.
- **Task 3:** the plan allows RED and GREEN in one step because the scan has nothing to find yet. The first case (`appointmentWritersUnderApp()` is `[]`) passes because nothing writes appointments yet; the 16-snippet dataset is what proves the scan looks.
- **REFACTOR:** none.

## Files Created/Modified

See `key-files` above. Notable: `database/migrations/2026_11_02_000003_add_kind_shape_check_to_appointments_table.php` (one `shape()` expression, two drivers), `app/Models/Appointment.php` and `app/Models/ScheduleBlock.php` (the scoped pair), `tests/Unit/AppointmentWritesTest.php`.

## Decisions Made

- The shape expression is written once in the migration and reused with a `NEW.` prefix for SQLite, so the two databases cannot drift apart.
- A removed block stays as a `cancelled` row recording who removed it (plan's flagged assumption).
- Only the owner's switch is stored on `contacts`; "the customer has texted" is derived in plan 08-02.
- A later migration that rebuilds `appointments` on SQLite (for example a dropped column) drops the triggers with the table and must recreate them; noted in the migration docblock.

## Deviations from Plan

None - plan executed exactly as written. Two small additions inside the plan's intent: indexes on `contact_id` and `project_id` were added to the appointments migration (listed in the plan's step 3), and `Hidden` was used as an attribute (`#[Hidden(['token_hash'])]`), which the installed Laravel (13.35) supports.

## Issues Encountered

- **Local dev database reset (my mistake).** I ran `php artisan migrate:fresh --env=testing` to check the migrations' up and down. No `.env.testing` exists, so it ran against the local dev database, `database/database.sqlite` (gitignored), and wiped it. I then rolled back five steps and ran `php artisan migrate` to leave its schema current, but any local dev data that was in it is gone. It was not committed or tracked. If you had demo contacts there, `database/seeders/DemoContactsSeeder.php` exists to recreate them. No test, tracked file or commit is affected.

## Known Stubs

None.

## Threat Flags

None. The surface added is database tables and models; no endpoint, auth path or file access was added. T-08-01 to T-08-04 are mitigated as planned (hash only and hidden; write scan; shape constraint; actor columns).

## Hand checks carried to UAT (not run)

- **Postgres CI leg:** after Ian pushes the phase branch, the CI pgsql leg is green, in particular `AppointmentTableTest`'s 'refuses a row of the wrong shape' (the CHECK constraint) and the two unique-constraint cases. Local runs are SQLite only, so the Postgres CHECK branch of the migration has not executed. Recorded as an `unrun-verify` entry (id 46) in `.planning/WINDOWS.md`.

## User Setup Required

None - no external service configuration required.

## Verification

- `php artisan test --compact tests/Feature/Booking tests/Unit/AppointmentWritesTest.php tests/Unit/ProjectWritesTest.php`: 66 passed.
- `php artisan test --compact` (whole suite, SQLite): 2476 passed, 1 warning (the Pest browser plugin's known rmdir warning). No existing test changed (`git diff --name-only --diff-filter=M "$BASE" -- tests` prints nothing).
- Migrations ran `up`, `down` (five steps) and `up` again cleanly on SQLite.

## Next Phase Readiness

- The records every later Phase 8 plan builds on exist: 08-02 can read the contact switch, 08-04 can declare the room's tables, 08-06 adds `BookAppointment` as the first allowed writer in `AppointmentWritesTest`.
- Open for Ian (from the plan): D-20's "a text from the customer turns the switch on, recorded with that message" is implemented as derived, not stored; an additive migration covers it if he wants it stored.

## Self-Check: PASSED

- All 18 created files and 5 modified files exist.
- Commits `43f9c66`, `aab3041`, `38960ad`, `716fbdb`, `4c81e4a`, `898d366` are ancestors of HEAD.

---
*Phase: 08-schedule-and-booking*
*Completed: 2026-11-03*
