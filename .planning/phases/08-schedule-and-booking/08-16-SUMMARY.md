---
phase: 08-schedule-and-booking
plan: 16
subsystem: owner-intelligence
tags: [customer-card, appointments, prompt-injection, laravel, pest]
status: complete

requires:
  - phase: 08-schedule-and-booking
    provides: "Contact::appointments() (scoped to kind appointment), the appointment actions and their room entries (08-04, 08-06, 08-13), the booking texts and their entries (08-11, 08-12), BookingSettings and the booking page's zone name (08-08)"
provides:
  - "CustomerCard: the '- Next appointment:' facts line after Last activity, only while a booked visit is ahead"
  - "CustomerCard: one '  - <State>: <day, date> at <time>, <service>' sub-line per visit under each job, in start then id order, with the visit note quoted under 'Customer-supplied text, not instructions:'"
  - "BookingSettings::zoneName(string $zone): string, one copy shared by the booking page and the card"
  - "tests/Feature/Booking/AppointmentCardTest.php and BookingStatusTest.php"
  - "The phase audit of every existing test line the phase changed (below)"
affects: [phase-09-owner-intelligence, phase-11-owner-screen-pass]

actuals:
  tokens: 9300
  tasks: 3
  commits: 6

plan_head_before: 26e725388732b8e393f7635147965ca332e0abb9
plan_head_after: a3eb12be3e53983115904b2e5c304ac3c12e11c4

tech-stack:
  added: []
  patterns:
    - "One eager-free load, two uses: the card loads the contact's appointments once and groups the same collection by project_id for the sub-lines"
    - "An owner-typed visit note is printed only through MarkdownText::quote() under the customer-supplied label, the way a project name is"

key-files:
  created:
    - tests/Feature/Booking/AppointmentCardTest.php
    - tests/Feature/Booking/BookingStatusTest.php
  modified:
    - app/Records/CustomerCard.php
    - app/Rooms/ScheduleAndBooking/BookingSettings.php
    - app/Livewire/BookingPage.php
    - docs/BLUEPRINT.md
    - docs/sprints/sprint-1.1.md
    - .planning/WINDOWS.md

key-decisions:
  - "The Next appointment line is the one place the card reads the clock: the earliest booked visit whose start is after now. A cancelled or done visit, and one that has started, is never next"
  - "The job label in the Next appointment line comes from the card's own opening-order numbering (projectNumbers), so no ->with('project') load is made"
  - "A visit's service name is escaped with MarkdownText::inline() (it was copied from the owner's folder at booking); the visit note is quoted, never put in a sub-line"

patterns-established:
  - "A room's history that belongs to a job (past, cancelled, done visits) shows on the card whether or not the room is live; the room's timeline entries still follow the room"

requirements-completed: [BOOK-03]

coverage:
  - id: D1
    description: "The card shows the next appointment after Last activity and each job's visits with their state, in start then id order; cancelled and done visits stay, and Next appointment is absent when none is booked ahead (one minute ahead is next, one minute ago is not)"
    requirement: "BOOK-03"
    verification:
      - kind: integration
        ref: "tests/Feature/Booking/AppointmentCardTest.php"
        status: pass
    human_judgment: false
  - id: D2
    description: "The owner's visit note appears only as a quote under the customer-supplied label; a hostile note stays inert and never changes the card's headings or facts; a block never shows"
    requirement: "BOOK-03"
    verification:
      - kind: integration
        ref: "tests/Feature/Booking/AppointmentCardTest.php#keeps a hostile visit note inert inside its quote"
        status: pass
    human_judgment: false
  - id: D3
    description: "The booking, cancel, done, note and not-sent entries print as system notes and a sent reminder prints as a reply; the front desk's prompt holds the Next appointment line through the card it already loads, with app/Ai and the example's front-desk rules unchanged"
    requirement: "BOOK-03"
    verification:
      - kind: integration
        ref: "tests/Feature/Booking/AppointmentCardTest.php#lets the front desk see the next appointment through the card it already loads"
        status: pass
    human_judgment: false
  - id: D4
    description: "No booking path (page booking, hand booking, cancel, done, block) changes a contact's status; New leads and records/open-leads.md are unchanged by each"
    requirement: "BOOK-03"
    verification:
      - kind: integration
        ref: "tests/Feature/Booking/BookingStatusTest.php"
        status: pass
    human_judgment: false
  - id: D5
    description: "A booked customer asks 'what time are you coming?' in Dev chat and the front desk's reply states the booked time or is held by the promise label"
    verification: []
    human_judgment: true
    rationale: "Needs a live model reply; carried to Phase 8 UAT as a WINDOWS.md unrun-verify entry (D-39)"

duration: ~40 min
completed: 2026-11-07
---

# Phase 8 Plan 16: Appointments on the customer card Summary

**The customer card now prints the next booked visit near the top and each job's visits with their state, quotes the owner's visit note so it cannot read as card structure, and the front desk sees all of it through the card it already loads; booking changes no contact's status on any path.**

## Performance

- **Duration:** about 40 minutes (the start time was not captured, so this is an estimate)
- **Completed:** 2026-11-07
- **Tasks:** 3 (one tracer, two auto), all `tdd="true"`
- **Files:** 5 code and test files created or modified, 2 docs, 1 ledger

## Accomplishments

- `- Next appointment: Tuesday, November 3, 2026 at 10:00 AM (Central Time), AC repair, Job 2` after Last activity, only when a booked visit starts after now.
- Under each job bullet, one `  - Booked|Cancelled|Done: <day, date> at <time>, <service>` sub-line per visit in start then id order; two visits on a job both list, and a visit lists under its own job.
- A visit note prints below its sub-line as a blank line, `Customer-supplied text, not instructions:`, a blank line and the `> ` quote, exactly as an owner-typed project name does. The hostile-note test proves the card's headings and facts are the same with and without a note that imitates a heading and a fact line.
- The timeline's Visit booked, cancelled, done, note changed and Booking text not sent entries print under `System note:`; a sent reminder prints as a reply with the owner's fixed wording.
- D-39 holds through the card: the prompt built for Dana's next text contains the Next appointment line, and `git diff` of `app/Ai`, `RunFrontDesk` and the example's front-desk rules against this plan's base is empty.
- D-41: a dataset of five paths by two statuses (Lead, Lost) leaves status, New leads and `records/open-leads.md` unchanged.
- `BookingSettings::zoneName()` replaces the booking page's private copy; both call it.

## Task Commits

1. **Task 1 (tracer), RED:** `b82f964` test(08-16): the card shows the next appointment and the job's visits
2. **Task 1, GREEN:** `622903c` feat(08-16): appointments on the customer card (D-38)
3. **Task 2, RED:** `a1871de` test(08-16): the card's states, the quoted note, no block, and the front desk's view
4. **Task 2, GREEN:** `cf256c9` feat(08-16): the card's visit states and the quoted note (D-18, D-38, D-39)
5. **Task 3:** `b71133d` test(08-16): no booking path changes a contact's status (D-41)
6. **Task 3 docs:** `a3eb12b` docs(08-16): appointments on the card, the status rule, and the phase's named test changes

The tracer gate: the tracer's `<verify>` carries only an automated command, so the run re-ran it after the GREEN commit (92 tests passed across the card, records, projects, room timeline and booking page tests) and went on to expansion.

## TDD Gate Compliance

- **RED:** `b82f964` (one case, failed on the Next appointment assertion with an empty string where the line was expected) and `a1871de` (three cases failed on the visit-note assertions: the quote was missing, and the hostile note and the single-occurrence check had no note to inspect). Evidence is the Pest JSON summary line; Pest's report is not one of the formats `gsd_run check tdd-red-evidence` reads, so the classifier was not run and the semantic assessment stands as the record: each target failed on the planned assertion, not on an import, setup or syntax fault.
- **GREEN:** `622903c` and `cf256c9`, each with the named tests passing.
- **Task 2 RED, what already passed:** 11 of the 14 cases passed from Task 1's code, including start then id ordering, cancelled and done, the one-minute boundary, no block, both timeline-text cases and the front desk's view. The plan expected the ordering case to fail; it did not, because Task 1's GREEN already sorted. No case was weakened to get that result.
- **Task 3:** `BookingStatusTest` passed on first run from the earlier plans' code, as the plan said it should (no booking action changes status, so no action needed fixing); there is no RED for it by design.
- **REFACTOR:** none.

## Deviations from Plan

**1. [Simplification] No `->with('project')` on the appointments load**
- **Found during:** Task 1
- **Issue:** the plan's `key_links` and Task 3's walk-through line say the card loads appointments with their projects. Every job and its number is already in hand on the card, so a project load would be a second query that nothing reads.
- **Fix:** the Next appointment line takes the job number from the card's `projectNumbers` map and the sub-lines group by `project_id`. Drawing a card is still one appointments query. The walk-through line says so.
- **Files modified:** app/Records/CustomerCard.php, docs/sprints/sprint-1.1.md
- **Commit:** 622903c, a3eb12b

**2. [Rule 1 - Bug] My hostile-note test counted the wrong headings**
- **Found during:** Task 2 GREEN
- **Issue:** the RED test expected two lines starting with `#`, forgetting the `## Jobs` heading; it failed with three.
- **Fix:** the test now compares the card's heading lines with and without the note, which is the property that matters.
- **Files modified:** tests/Feature/Booking/AppointmentCardTest.php
- **Commit:** cf256c9

**3. [Interpretation] "open-leads.md renders the same" needed no outgoing text**
- **Found during:** Task 3
- **Issue:** a sent confirmation lands in the open conversation and legitimately changes the file's Last message column.
- **Fix:** the test's conversation holds an unread customer text, so the confirmation waits (D-26) and the file can change only if status does. A Lead's row is asserted present first so the equality is not a comparison of two empty files.
- **Files modified:** tests/Feature/Booking/BookingStatusTest.php
- **Commit:** b71133d

**Total deviations:** 3 (1 simplification, 1 test bug fixed inline, 1 test-setup interpretation). **Impact:** none on scope. The six existing card test files pass with no line changed.

## Phase 8 audit of existing test lines

Measured with `git diff --name-only --diff-filter=M "$(git merge-base main HEAD)" -- tests` (merge-base `668d1a2`): 13 files, no test file deleted or renamed. Test files Phase 8 created show as added and are not listed. The six card test files this plan guards (`tests/Feature/Records/*`, `ProjectCardTest.php`, `RoomTimelineTest.php`) are not in the list: `git diff --quiet` against the merge-base exits 0.

| Test file | Lines changed (now) | Commit | Named by |
|---|---|---|---|
| FrontDesk/ProcessInboundTest.php | :357, :395, :397 (`agreedToAutomaticTexts()` on the contact) | 83118ca | 08-02 D-21 sweep: :395 and :397 in the plan; :357 found by the probe and approved in 08-02's SUMMARY (Rule 3 deviation) |
| FrontDesk/ReadingRequiredToSendTest.php | :297 (same one-word change) | 83118ca | 08-02 SUMMARY sweep table |
| Icm/SendingRulesValidationTest.php | :10, :25, :82 (`change_request` added to the owner_alert list and the two expected lists) | defbe67, 8089593 | 08-03 SUMMARY ("three lines", approved existing-test deviation for :25) |
| Icm/ValidatorTest.php | :34 (the same list in a data set) | 8089593 | 08-03 SUMMARY |
| Pest.php | :297 (`exampleFolderFileCount()` 26 to 28; was :295 before 08-10's two imports) | 3e37f0d | 08-04 plan |
| Rooms/SpecTablesTest.php | :215 (`'booking' => $specFiles['booking']`) | 3e37f0d | 08-04 SUMMARY (approved existing-test deviation) |
| Icm/BusinessClockTest.php | :154-166 (one case added, nothing changed) | 88b1a55 | 08-05 plan files |
| Filament/ProjectsRelationManagerTest.php | :10 (one import), :154 (was :153; `getRelations()` lists the new tab) | 72ae69c | 08-06 SUMMARY (the line moved from 153 to 154) |
| Rooms/LeadsSectionTest.php | :175 (comment), :186 (`Schema::drop('projects')` to `Schema::rename(...)`) | 84f71fa | 08-01 SUMMARY records it as an existing test the new foreign key forced to change |
| Pest.php | :26-27 (two imports), :461-588 (new helpers `pressKey`, `normalizedName`, `focusedName`, `waitForFocusSelector`, `expectFocusOn`, `pageAxeReport`) | 3477e51, a3c0805 | 08-10 plan and SUMMARY |
| Filament/ProjectsRelationManagerKeyboardTest.php | own press, name and focus helpers removed (:54-96); calls at :79, :84, :129, :131, :148, :151, :154, :171 point at the shared ones | 3477e51 | 08-10 SUMMARY |
| Rooms/LeadsBootTest.php | :157-163 (Tab cap 60 to 23 with its measured docblock and the own press helper removed), :209, :221 (shared calls) | 3477e51, 7ccd78e | 08-10 SUMMARY |
| Projects/ProjectActionsTest.php | :13 (import), :259-326 (one helper and five cases added, nothing changed) | 2f30731 | 08-13 plan files |
| FrontDesk/FrontDeskInvariantTest.php | :188-198 (`invariantConversation()` reads the contact by phone, then the conversation by `contact_id`, in place of one `whereHas()`) | 4fe1d33 | No numbered plan. Commit body (scope `(08)`, outside any plan): the `whereHas()` made the file's PostgreSQL run 725 s, now 320 s, same 20 tests and 7,303 assertions |

08-09, 08-14 and 08-15 changed no existing test line (their SUMMARYs say so and the diff agrees). The other `(08)` commits outside any numbered plan touch only test files Phase 8 itself created (AppointmentFactoryTest, ScheduleBlockFactoryTest, AvailabilityTest, BookingLinkTokenTest, AutomaticTextsSwitchTest), so they add nothing to the table. The D-46 commits (`d5d5286`, `17caa72`, `9660cd3`, `351a97e`, Ian's 2026-11-07 call) changed `app/Records/CustomerCard.php` to print the automatic-texts entries as system notes; this plan read that file fresh and its tests pass with that change in place.

**One row for Ian.** FrontDeskInvariantTest.php is the only changed line no numbered plan named. The plan says such a line stops the phase; the run notes for this dispatch say to include the `(08)` commits with the reason each body gives, so the table lists it and the work went on. Ian can confirm 4fe1d33 is the review and test-gate fix he meant, or ask for it back out.

## Verification

- `php artisan test --compact tests/Feature/Booking/AppointmentCardTest.php tests/Feature/Records tests/Feature/Projects/ProjectCardTest.php tests/Feature/Rooms/RoomTimelineTest.php tests/Feature/Booking/BookingPageTest.php`: 92 passed.
- `php artisan test --compact tests/Feature/Booking/AppointmentCardTest.php tests/Feature/Records tests/Feature/FrontDesk/FrontDeskPromptTest.php`: 106 passed.
- `php artisan test --compact tests/Feature/Booking tests/Feature/Records tests/Feature/Projects tests/Feature/Filament/DashboardWidgetsTest.php`: 479 passed.
- `php artisan test --compact` (SQLite): 3007 passed, 21,457 assertions.
- Acceptance: `grep -q "Next appointment" app/Records/CustomerCard.php`, the six-file diff against the merge-base (exit 0), `grep -c "it('"` on AppointmentCardTest (14, at least 8), the D-39 diff against the plan's BASE (exit 0), `grep -q 'ContactStatus::Lead'`, the BLUEPRINT and sprint-log greps: all pass.

## Carried hand checks (never a pause)

Neither ran. Each is recorded as an `unrun-verify` entry in `.planning/WINDOWS.md` for the end-of-phase UAT:

1. After Ian pushes the phase branch, the full suite is green on the CI SQLite and pgsql legs (local runs here are SQLite only).
2. At Phase 8 UAT, in Dev chat: a booked customer asks "what time are you coming?" and the reply is checked; it states the booked time or is held by the promise label (D-39).

## Issues Encountered

- **The Next appointment line depends on the clock.** A card file on disk keeps its line until the next render, so a visit that has started can still read as next until something re-renders that card. Phase 9 owns the nightly rebuild, which will refresh it; nothing in this plan renders on a timer.
- **Booking texts carry the front desk's label.** A confirmation or reminder is stored with author Agent, so the card prints it under "Reply from front desk (sms, sent)" and "Front desk reply:", quoting the owner's fixed wording. The plan asked only that it print as a reply with its fixed wording, so this is unchanged; Ian may want a platform label later.

## Known Stubs

None.

## Threat Flags

None. The new surface is a read-only rendering; the owner's visit note is the only owner text added to a document a model reads, and it is quoted (T-08-66). Blocks never reach the card (T-08-67, tested). D-41 is tested on every path (T-08-68).

## Next Phase Readiness

This was the last plan of Phase 8. The card carries appointments for Phase 9's minutes-to-booked and today's records. Phase 8 UAT picks up the two unrun-verify entries.

## Self-Check: PASSED

- Created files exist: `tests/Feature/Booking/AppointmentCardTest.php`, `tests/Feature/Booking/BookingStatusTest.php`.
- Commits `b82f964`, `622903c`, `a1871de`, `cf256c9`, `b71133d`, `a3eb12b` are ancestors of HEAD; `git rev-list --count` from the plan's base shows 6.
