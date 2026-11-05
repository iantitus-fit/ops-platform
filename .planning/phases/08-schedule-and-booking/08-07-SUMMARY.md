---
phase: 08-schedule-and-booking
plan: 07
subsystem: booking
tags: [booking-link, hashed-token, page-booking, project-actor, owner-alert, cache-lock, pest]

requires:
  - phase: 08-schedule-and-booking
    provides: "BookAppointment::byHand() with the schedule:book lock and BookingRefused (08-06), Availability::offers() (08-05), BookingSettings and the booking file (08-04), the AppointmentBooked alert (08-03), the booking_links and appointments tables (08-01)"
provides:
  - "App\\Actions\\MintBookingLink: a 256-bit token stored only as its sha256 hash, supersede of older unbooked links, revoke, attach the carrying message"
  - "App\\Actions\\BookAppointment::fromLink(): the customer's tap under the same lock, the job without guessing, one booked alert after commit"
  - "App\\Rooms\\ScheduleAndBooking\\ResolveBookingLink with BookingLinkLookup and App\\Enums\\BookingPageState: token to page state in U-14's order, plus closedState() for the booking action"
  - "ProjectActor::Customer and OpenProject::forBookingPage(), with the four readers of who opened a job turned into three-arm matches"
  - "BookingRefused::linkClosed()"
affects: [08-08, 08-09, 08-10, 08-11, 08-12, booking-page, text-the-link, schedule-page]

actuals:
  tokens: 24000
  tasks: 3
  commits: 6
plan_head_before: 6c059824b403dccd03ebe8a78f96bd6d67261b6c
plan_head_after: b7cec474e57c5f448df2d229ef990093111adc92

tech-stack:
  added: []
  patterns:
    - "A 256-bit random token found by the hash of what arrives, never stored or logged; an unknown and a revoked token read the same (no enumeration oracle)"
    - "The link's own state judged by closedState() on the row held under the lock, so the open-times list is computed once, inside offers(), and a just-taken last time reads as taken()"
    - "A unique-index backstop whose insert runs in a nested transaction (a savepoint) so a refusal does not spoil a Postgres transaction"
    - "An enum read with a match that has an arm for every case and no default, so a new case cannot be printed as the owner's"

key-files:
  created:
    - app/Actions/MintBookingLink.php
    - app/Rooms/ScheduleAndBooking/ResolveBookingLink.php
    - app/Rooms/ScheduleAndBooking/BookingLinkLookup.php
    - app/Enums/BookingPageState.php
    - tests/Feature/Booking/PageBookingTest.php
    - tests/Feature/Booking/BookingLinkTokenTest.php
  modified:
    - app/Actions/BookAppointment.php
    - app/Rooms/ScheduleAndBooking/BookingRefused.php
    - app/Enums/ProjectActor.php
    - app/Actions/OpenProject.php
    - app/Records/CustomerCard.php
    - app/Filament/Resources/Contacts/RelationManagers/ProjectsRelationManager.php
    - app/Rooms/Leads/LeadsSection.php
    - docs/BLUEPRINT.md
    - docs/sprints/sprint-1.1.md

key-decisions:
  - "fromLink() judges the link with ResolveBookingLink::closedState() on the BookingLink row it re-reads under the lock, never with the plain token and never with the open-times list, so a time taken meanwhile is taken() and not a closed link."
  - "The job choice returns only the chosen job's id (jobIdForPageBooking), so BookAppointment does not import the Project model; the jobs are reached through the contact's own projects() relation."
  - "The carrying message is the link's message_id, else the conversation's message whose meta.booking_link_id is the link; with neither, a LogicException naming the link id (never the token) is reported and no alert is raised."
  - "The Ideas inbox note on the on-my-way link already named the stored hashed token, so it needed no change."

patterns-established:
  - "Page-booking tests build their world with small prefixed helpers (pageBooking*, linkToken*) and never rely on another test file's helpers being loaded"
  - "A test that changes the folder after a link was minted re-runs FolderSync and calls app()->forgetScopedInstances() so ActiveConfig re-reads the live version"

requirements-completed: [BOOK-02, BOOK-05]

coverage:
  - id: D1
    description: "A minted link books the tapped time for the customer on the conversation's job, recorded as the customer through the link, with one timeline entry and no status change"
    requirement: BOOK-02
    verification:
      - kind: unit
        ref: "tests/Feature/Booking/PageBookingTest.php#books the tapped time for the customer through the link"
        status: pass
    human_judgment: false
  - id: D2
    description: "The token is 64 hex characters and only its sha256 hash is stored; no column holds the token"
    requirement: BOOK-02
    verification:
      - kind: unit
        ref: "tests/Feature/Booking/PageBookingTest.php#stores only the hash of the token (D-12)"
        status: pass
    human_judgment: false
  - id: D3
    description: "One booked alert on the text that carried the link, after commit, due at the next opening after hours, a second link alerts again, and nothing is raised (and the problem reported) when no carrying message exists"
    requirement: BOOK-05
    verification:
      - kind: unit
        ref: "tests/Feature/Booking/PageBookingTest.php#alerts the owner once on the text that carried the link (D-33)"
        status: pass
      - kind: unit
        ref: "tests/Feature/Booking/BookingLinkTokenTest.php#waits for the opening after hours (D-33, D-10)"
        status: pass
      - kind: unit
        ref: "tests/Feature/Booking/BookingLinkTokenTest.php#alerts a second booking from a new link (02.1 IN-11)"
        status: pass
      - kind: unit
        ref: "tests/Feature/Booking/BookingLinkTokenTest.php#raises nothing without the carrying message and reports it"
        status: pass
    human_judgment: false
  - id: D4
    description: "The job is picked without guessing (conversation's only open job, else the contact's only, else a new one opened by the customer), a closed job is never reopened, and every reader of who opened a job names the customer"
    requirement: BOOK-02
    verification:
      - kind: unit
        ref: "tests/Feature/Booking/PageBookingTest.php#opens a new job instead of guessing between two (D-23 step 3, D-22)"
        status: pass
      - kind: unit
        ref: "tests/Feature/Booking/PageBookingTest.php#lands every page booking on an open job of the link's contact (invariant)"
        status: pass
      - kind: integration
        ref: "tests/Feature/Booking/PageBookingTest.php#names the customer wherever a job's opener shows (D-22, Pitfall 4)"
        status: pass
    human_judgment: false
  - id: D5
    description: "A link resolves to one page state in U-14's order (does not work, replaced, expired at exactly expires_at, service unavailable, no times, picker) and shows the visit, not the replacement, until the visit ends"
    requirement: BOOK-02
    verification:
      - kind: unit
        ref: "tests/Feature/Booking/BookingLinkTokenTest.php#resolves each state a link can be in"
        status: pass
      - kind: unit
        ref: "tests/Feature/Booking/BookingLinkTokenTest.php#shows the visit, not the replacement (U-14)"
        status: pass
      - kind: unit
        ref: "tests/Feature/Booking/BookingLinkTokenTest.php#keeps the booked view past the link's expiry until the visit ends (U-14)"
        status: pass
    human_judgment: false
  - id: D6
    description: "A link books at most once ever (state check plus the unique index backstop), supersedes only unbooked links, and re-checks the service and the time at the tap, a just-taken last time reading as taken and not as a closed link"
    requirement: BOOK-02
    verification:
      - kind: unit
        ref: "tests/Feature/Booking/BookingLinkTokenTest.php#books at most once, ever (D-13)"
        status: pass
      - kind: unit
        ref: "tests/Feature/Booking/BookingLinkTokenTest.php#keeps the unique index as the backstop (D-13)"
        status: pass
      - kind: unit
        ref: "tests/Feature/Booking/BookingLinkTokenTest.php#refuses a just-taken last open time as taken, not closed (D-07, U-09)"
        status: pass
      - kind: unit
        ref: "tests/Feature/Booking/BookingLinkTokenTest.php#re-checks the service at the tap (D-02)"
        status: pass
    human_judgment: false
  - id: D7
    description: "The CI pgsql leg for the job choice under the row lock, the at-most-once savepoint and the meta->booking_link_id JSON read"
    verification: []
    human_judgment: true
    rationale: "Hand checks go to end-of-phase UAT (Ian's standing instruction); recorded in .planning/WINDOWS.md as an unrun-verify entry; Postgres needs Ian to push the phase branch"

duration: 45min
completed: 2026-11-05
status: complete
---

# Phase 8 Plan 07: Booking links and page bookings Summary

**A booking link is a 256-bit random token stored only as its sha256 hash; `BookAppointment::fromLink()` books the customer's tapped time under the same lock onto a job it can name without guessing (or a new job opened as the customer), `ResolveBookingLink` turns any token into the page state U-14 names, and the owner gets exactly one booked alert on the text that carried the link.**

## Performance

- **Duration:** about 45 min
- **Completed:** 2026-11-05
- **Tasks:** 3
- **Files modified:** 15 (6 created, 9 modified), plus the WINDOWS ledger and planning state in the metadata commit

## Accomplishments
- `MintBookingLink` mints `bin2hex(random_bytes(32))`, stores only the hash, supersedes the conversation's older unbooked links in the same transaction, and offers `revoke()` and `attachMessage()`. The token is returned once and is never stored, logged or put in an exception message.
- `BookAppointment::fromLink()` takes the shared `schedule:book` lock, re-reads the link row, refuses a closed link with `linkClosed()`, re-checks the tapped time with `Availability::offers()` (a taken one is `taken()` with field `starts_at`), picks the job, writes the appointment as `booked_by` customer with `booking_link_id`, and writes one timeline entry "Booked by the customer on the booking page: AC repair, Thursday, November 5 at 10:00 AM." The unique `booking_link_id` index is the backstop, its insert in a savepoint.
- The booked alert is raised after the transaction commits, on the link's `message_id` or else the conversation's message whose `meta.booking_link_id` names the link; with neither, a `LogicException` naming the link id is reported and nothing is raised (never an `m0` key). Hand bookings raise none.
- The job choice (D-23): the open job tied to the link's conversation when exactly one; else the contact's only open job; else `OpenProject::forBookingPage()` opens a new one as the customer, with the timeline line "Opened by the customer on the booking page". A closed job is never reopened.
- `ProjectActor::Customer` landed with its four readers (`CustomerCard`, `ProjectsRelationManager`, `OpenProject`'s summary, `LeadsSection`) each a `match` with an arm for every case and no default; the Lead and User wording is byte for byte as before.
- `ResolveBookingLink::handle($token, $now)` and `closedState($link, $now)` return one of nine `BookingPageState` values in U-14's order: revoked or unknown (the same does-not-work), a link with an appointment shows booked, cancelled or done until its expiry or the visit's end, then expired; superseded is replaced; at exactly `expires_at` is expired; service unavailable; no times; the picker.
- BLUEPRINT sections 3 and 10 and a sprint walk-through line record the link and the page booking.

## TDD Gate Compliance

All three tasks have a RED commit before their GREEN commit. RED evidence is the Pest run output (JSON summary line); the repository's runner does not emit a format the `check tdd-red-evidence` classifier reads, so the semantic assessment below stands in for it, as in 08-06.

- **Task 1 RED (f21c765):** 3 of 3 cases failed with `Target class [App\Actions\MintBookingLink] does not exist`. Semantic assessment: the planned behavior is absent (a missing action class is how absence shows in a Laravel container resolve); no syntax or fixture fault. GREEN ccbcbc9.
- **Task 2 RED (d084656):** 12 cases, 5 passed (Task 1's three and the two job-choice cases Task 1's conversation-job code already satisfied) and 7 failed on the planned behavior: "That job is closed. Pick an open job or a new one." for the contact's-only-job, two-jobs, every-closed, no-job and invariant cases, and `Undefined constant App\Enums\ProjectActor::Customer` for the labels case. GREEN 9292623.
- **Task 3 RED (2e2e4c5):** 25 cases, 11 passed and 14 failed. The 14: ten resolver dataset rows (`Class "App\Enums\BookingPageState" not found`), two U-14 cases and the just-taken case (no `ResolveBookingLink` or `handle()`), and the service re-check (a service turned off after the link was minted was not refused). The 11 that already passed (supersede-only-unbooked, at-most-once, the unique-index case, the time re-checks, after-hours timing, second link, meta fallback, no carrying message) were satisfied by Task 1's inline link checks and Task 2's work. Unexpected green was investigated: the unique-index case was mutated (the savepoint catch rethrowing the violation) and failed with the SQLSTATE integrity error, so it is not hollow. GREEN b7cec47.
- No REFACTOR commit.

## Task Commits

1. **Task 1 (tracer): a link books a time for the customer and alerts the owner once** - `f21c765` (test), `ccbcbc9` (feat)
2. **Task 2: the job without guessing, opened by the customer** - `d084656` (test), `9292623` (feat)
3. **Task 3: the link's states and the re-checks at the tap** - `2e2e4c5` (test), `b7cec47` (feat)

Tracer gate: the Task 1 verify (`tests/Feature/Booking tests/Feature/Alerts tests/Unit/AppointmentWritesTest.php`) was re-run end to end and passed (225 tests) before expansion.

**Plan metadata:** the docs(08-07) commit that follows this file.

## Files Created/Modified
- `app/Actions/MintBookingLink.php` - mint, supersede, revoke, attach the carrying message
- `app/Actions/BookAppointment.php` - `fromLink()`, the shared lock helper, the job choice, the alert after commit
- `app/Rooms/ScheduleAndBooking/ResolveBookingLink.php`, `BookingLinkLookup.php`, `app/Enums/BookingPageState.php` - the token to page state
- `app/Rooms/ScheduleAndBooking/BookingRefused.php` - `linkClosed()`
- `app/Enums/ProjectActor.php`, `app/Actions/OpenProject.php` - the Customer actor and `forBookingPage()`
- `app/Records/CustomerCard.php`, `app/Filament/Resources/Contacts/RelationManagers/ProjectsRelationManager.php`, `app/Rooms/Leads/LeadsSection.php` - the three-arm matches
- `tests/Feature/Booking/PageBookingTest.php` (12 cases), `tests/Feature/Booking/BookingLinkTokenTest.php` (25 cases)
- `docs/BLUEPRINT.md` (sections 3 and 10), `docs/sprints/sprint-1.1.md` (walk-through line)

## Decisions Made
- `fromLink()` calls `closedState()` on the row it re-reads under the lock instead of resolving a token, so no plain token is needed and the open-times list is computed once (inside `offers()`); this is what makes a just-taken last time `taken()` and not a closed link.
- The job choice returns the job's id only, so `BookAppointment` still does not import the Project model.
- The existing Ideas inbox note already named the stored hashed token, so it was left alone.

## New Laravel concepts (plain words)
- **A hashed link token:** `bin2hex(random_bytes(32))` makes 64 random characters; only `hash('sha256', $token)` is saved, so the page finds the row by hashing what the URL carries, and a copy of the database holds no link that works. A fast hash is right for a long random secret; bcrypt is salted, so a row could not be found from the token alone. (Walk-through line added in `docs/sprints/sprint-1.1.md`.)
- **A `match` with no default:** `match ($project->opened_by) { ... }` with an arm for every enum case makes PHP throw `UnhandledMatchError` if a new case is ever added without an arm, where the old `=== Lead ? a : b` would quietly print the new case as the owner's. (Same walk-through line.)
- **A savepoint from a nested transaction:** `DB::transaction()` inside another transaction becomes a savepoint, so a refused insert (the unique index) rolls back only that step and Postgres does not abort the outer transaction.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] A test assertion in Task 2 compared two Carbon objects by identity**
- **Found during:** Task 2 (GREEN)
- **Issue:** `->toBe($closed->fresh()->closed_at)` failed with "two variables reference the same object" because each `fresh()` is a different instance; it was the test's mistake, not the code's.
- **Fix:** The assertion now states what it means: the closed job still has a `closed_at`.
- **Files modified:** tests/Feature/Booking/PageBookingTest.php
- **Commit:** 9292623

**2. [Rule 1 - Bug] The Task 3 resolver dataset named the enum at load time**
- **Found during:** Task 3 (RED)
- **Issue:** `->with([... BookingPageState::DoesNotWork ...])` evaluates when the file loads, so with the enum absent Pest reported "DatasetMissing" instead of a failing case: a load fault, not RED.
- **Fix:** The dataset carries the case name as a string and the test reads `BookingPageState::{$stateName}` inside the test, so the missing enum fails the test itself.
- **Files modified:** tests/Feature/Booking/BookingLinkTokenTest.php
- **Commit:** 2e2e4c5

### Notes (no code change)
- The plan's `grep -q` criteria that contain `$` before a letter (the `fromLink` and `closedState` signatures) do not match under macOS BSD grep's basic regex; the same strings match with `grep -F`, so the criteria are met by the text on disk.
- Task 3 asked to change the Ideas inbox note if it still named Laravel temporary signed URLs; it already named the stored hashed token and only mentions signed URLs as what it replaces.

---

**Total deviations:** 2 auto-fixed (both Rule 1, in tests this plan wrote)
**Impact on plan:** None on scope or behavior.

## Issues Encountered
- 11 of Task 3's 25 RED cases passed against earlier code, as recorded in TDD Gate Compliance, with mutation evidence for the unique-index case.

## Human checks carried (not run, never a pause)
- After Ian pushes the phase branch, the CI pgsql leg should be green, in particular `PageBookingTest`'s job choice under the row lock, `BookingLinkTokenTest`'s at-most-once savepoint and the `meta->booking_link_id` JSON read. Recorded in `.planning/WINDOWS.md` as an `unrun-verify` entry.

## Verification
- `php artisan test --compact tests/Feature/Booking tests/Feature/Alerts tests/Unit/AppointmentWritesTest.php`: 259 passed.
- `php artisan test --compact tests/Feature/Booking/PageBookingTest.php tests/Feature/Projects tests/Feature/Filament/ProjectsRelationManagerTest.php tests/Feature/Rooms/LeadsSectionTest.php tests/Feature/Records tests/Unit/ProjectWritesTest.php tests/Unit/AppointmentWritesTest.php`: 218 passed, with none of the existing project, leads, relation-manager or records tests edited (the BASE diff of modified files under those paths is empty).
- Full `php artisan test --compact` on SQLite: 2667 passed, 19097 assertions, 0 failed (1 warning, the known Pest browser plugin one).
- `vendor/bin/pint --dirty --format agent` clean before every commit.
- Acceptance criteria: the `hash('sha256'`, `random_bytes(32)`, `fromLink`, `AlertReason::AppointmentBooked`, `closedState(` and signature greps, `case Customer = 'customer';`, `forBookingPage`, the four `ProjectActor::Customer =>` arms, 9 `BookingPageState` cases, 0 `use App\Models\Project;` in BookAppointment, `Phase 8 context D-22, D-23` in BLUEPRINT and `(plan 08-07)` in the sprint file.

## Known Stubs
None.

## Threat Flags
None. T-08-26 and T-08-27 (guessing a link, a database copy): 256-bit token, hash-only storage, an unknown and a revoked token resolve to the same state, and a test asserts no column holds the token and the exception names the link id only. T-08-28 (replay): state check under the lock plus the unique `booking_link_id` index, mutation-checked. T-08-29: `offers()` and the service re-check run inside the lock. T-08-30 and T-08-31: `booking_link_id` and `booked_by` customer on the row, and each link rides a new message so a second booking alerts again.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- 08-08 can call `ResolveBookingLink::handle($token, now())` for the page's state and `BookAppointment::fromLink()` for the tap, catching `BookingRefused::taken()` to show fresh times and `linkClosed()` for the closed view.
- 08-09 must write `meta.booking_link_id` on the link-carrying message at send time and call `MintBookingLink::attachMessage()`; until then the booked alert finds the message by the meta fallback.
- The Postgres run waits for Ian to push the phase branch.

## Self-Check: PASSED

---
*Phase: 08-schedule-and-booking*
*Completed: 2026-11-05*
