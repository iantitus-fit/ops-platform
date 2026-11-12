---
phase: 08-schedule-and-booking
plan: 13
subsystem: operations
tags: [filament, livewire, appointments, accessibility, pest-browser, axe, laravel]

requires:
  - phase: 08-schedule-and-booking
    provides: "BookAppointment, the Appointments tab with Book by hand (08-06), the booking send rows and sender (08-11, 08-12), the browser helpers pressKey, focusedName, expectFocusOn, pageAxeReport (08-10)"
provides:
  - "CancelAppointment, MarkAppointmentDone and EditVisitNote, the next three named writers of the appointments table"
  - "The D-25 refusal in CloseProject, and CloseProject::blockedBy() which owns the sentence"
  - "Mark done, Cancel visit and Edit note row actions on the contact screen's Appointments tab, each with a dialog naming the visit"
  - "The Close dialog for a job with a visit ahead: names the visit, no confirm button, only Back"
  - "A real-browser keyboard, focus and axe test of the tab and its dialogs"
affects: [08-14, 08-15, 08-16, phase-11-owner-screen-pass]

actuals:
  tokens: 16176
  tasks: 3
  commits: 6

plan_head_before: ce6c7b183dc46fb655d6b72fe9c0a0eca7f4c468
plan_head_after: 791dceecb32ecf957e12677fecedec6b1636f7de

tech-stack:
  added: []
  patterns:
    - "A named action under the architecture scan, re-reading its row under a lock through the kind-scoped model so a block's id changes nothing (A3)"
    - "modalSubmitAction() as a closure, decided each time a dialog opens, so a screen explains while the action stays the guard"
    - "A Livewire browser event plus an Alpine x-on listener on a chosen button as a deliberate focus target when a row's own buttons leave the page"

key-files:
  created:
    - app/Actions/CancelAppointment.php
    - app/Actions/MarkAppointmentDone.php
    - app/Actions/EditVisitNote.php
    - tests/Feature/Booking/AppointmentActionsTest.php
    - tests/Feature/Filament/AppointmentsKeyboardTest.php
    - .planning/phases/08-schedule-and-booking/deferred-items.md
  modified:
    - app/Actions/CloseProject.php
    - app/Filament/Resources/Contacts/RelationManagers/AppointmentsRelationManager.php
    - app/Filament/Resources/Contacts/RelationManagers/ProjectsRelationManager.php
    - app/Rooms/ScheduleAndBooking/BookingRefused.php
    - tests/Feature/Filament/AppointmentsRelationManagerTest.php
    - tests/Feature/Projects/ProjectActionsTest.php
    - tests/Unit/AppointmentWritesTest.php
    - docs/BLUEPRINT.md
    - docs/sprints/sprint-1.1.md

key-decisions:
  - "CloseProject owns the D-25 sentence through a public blockedBy(); handle() throws it inside its lock and the Close dialog reads it when it opens, so the dialog and the guard cannot disagree"
  - "Focus after Cancel visit or Mark done goes to Book by hand through a dispatched browser event the button listens for, with $nextTick, not a fixed delay"
  - "The tab's axe run is scoped to the relation manager and the open modal and waits for page animations to finish; the contact details list above the tabs is pre-existing and deferred"
  - "A ticked 'Also close' that was never offered (another booked visit on the job) is ignored; the checkbox is built only on the job's last booked visit"

patterns-established:
  - "Actions re-read through the scoped Appointment model under lockForUpdate and return silently on null, so a block hydrated as an Appointment is never touched"
  - "One action per row verb, hidden by state (U-28), each button's aria-label built from its own label plus the visit and escaped with e()"

requirements-completed: [BOOK-03, A11Y-02]

coverage:
  - id: D1
    description: "The owner cancels a booked visit from the Appointments tab after a dialog that says the customer is not texted; it is final, records who, writes one timeline entry, and ends its pending texts so a cancelled visit's reminder never sends"
    requirement: BOOK-03
    verification:
      - kind: integration
        ref: "tests/Feature/Booking/AppointmentActionsTest.php#cancels a visit and ends its pending texts"
        status: pass
      - kind: integration
        ref: "tests/Feature/Filament/AppointmentsRelationManagerTest.php#cancels from the tab after the dialog"
        status: pass
    human_judgment: false
  - id: D2
    description: "Mark done appears from the start minute, records who, writes one entry, and closes the job only when the owner ticks the box on the job's last booked visit"
    requirement: BOOK-03
    verification:
      - kind: integration
        ref: "tests/Feature/Booking/AppointmentActionsTest.php#closes the job only when asked, on its last booked visit"
        status: pass
      - kind: integration
        ref: "tests/Feature/Filament/AppointmentsRelationManagerTest.php#shows Mark done at the start minute and not a minute before"
        status: pass
    human_judgment: false
  - id: D3
    description: "The owner keeps a visit note up to 200 characters; the timeline says the note changed and never quotes it"
    requirement: BOOK-03
    verification:
      - kind: integration
        ref: "tests/Feature/Booking/AppointmentActionsTest.php#keeps the visit note and writes a quiet entry"
        status: pass
      - kind: integration
        ref: "tests/Feature/Filament/AppointmentsRelationManagerTest.php#keeps a visit note from the tab and refuses 201 characters beside the field"
        status: pass
    human_judgment: false
  - id: D4
    description: "CloseProject refuses a job with a booked visit ahead, and the Close dialog names the visit and offers only Back, while a job with no visit ahead keeps its dialog unchanged"
    requirement: BOOK-03
    verification:
      - kind: integration
        ref: "tests/Feature/Projects/ProjectActionsTest.php#refuses to close a job with a visit booked ahead"
        status: pass
      - kind: integration
        ref: "tests/Feature/Filament/AppointmentsRelationManagerTest.php#names the visit in the Close dialog and offers only Back"
        status: pass
    human_judgment: false
  - id: D5
    description: "Only the named actions write appointments, and no class in App\\Ai or App\\SendRule uses any of them"
    requirement: BOOK-03
    verification:
      - kind: unit
        ref: "tests/Unit/AppointmentWritesTest.php#only the named actions write the appointments table"
        status: pass
    human_judgment: false
  - id: D6
    description: "In a real browser the tab is reached by Tab from the top, its dialogs work by keyboard, focus lands on Book by hand after Cancel visit or Mark done, and axe finds nothing on the tab or its open dialogs"
    requirement: A11Y-02
    verification:
      - kind: automated_ui
        ref: "tests/Feature/Filament/AppointmentsKeyboardTest.php"
        status: pass
    human_judgment: false
  - id: D7
    description: "The VoiceOver listen of the Appointments tab and its dialogs"
    requirement: A11Y-02
    verification: []
    human_judgment: true
    rationale: "A screen reader walk needs a person; recorded for the Phase 11 owner-screen pass (A11Y-03)"

duration: 25min
completed: 2026-11-07
status: complete
---

# Phase 8 Plan 13: The owner's visit actions on the contact screen Summary

**Cancel visit, Mark done (with an optional close on the job's last booked visit) and Edit note from the Appointments tab as named, locked, once-only actions, a CloseProject that refuses a job with a visit still ahead, and a real-browser keyboard, focus and axe proof of the tab and its dialogs**

## Performance

- **Duration:** 25 min
- **Started:** 2026-11-07T00:00:20Z
- **Completed:** 2026-11-07T00:25:51Z
- **Tasks:** 3
- **Files modified:** 15 (14 in the plan's commits, plus the phase's deferred-items.md)

## Accomplishments

- `CancelAppointment` is final, stores who and when, writes one timeline entry, ends every pending send row as skipped ("The visit was cancelled.") and sends nothing, so a cancelled visit's reminder never goes out (D-14, D-16, D-31).
- `MarkAppointmentDone` refuses a visit that has not started (to the minute), and closes the job through `CloseProject` only when the owner ticked the box and no other appointment on the job is still booked (D-24, 07 D-05).
- `EditVisitNote` keeps a trimmed note of up to 200 characters; the timeline says "Ian Owner changed the visit note." and never quotes the note (D-18).
- `CloseProject` refuses a job with a booked visit ahead; its `blockedBy()` owns the sentence, so the Projects tab's Close dialog for such a job reads the same words, shows no confirm button and offers only Back (D-25, U-31).
- Row actions `markDone`, `cancelVisit`, `editNote` are hidden by state (U-28), each button carries an escaped aria-label naming its visit (U-33, T-07-25).
- After Cancel visit or Mark done, keyboard focus lands on Book by hand, proved in real Chromium at desktop and phone width, and axe is clean on the tab and its open dialogs (D-43).

## Task Commits

TDD plan, each task a RED then GREEN pair:

1. **Task 1 (tracer): cancel a visit** - `a8249fa` (test, RED), `78c799a` (feat, GREEN)
2. **Task 2: mark done, the note, the close guard** - `2f30731` (test, RED), `e9f0f73` (feat, GREEN)
3. **Task 3: the tab by keyboard, focus, axe, docs** - `cfb203a` (test, RED), `791dcee` (feat, GREEN)

**Plan metadata:** the docs commit that follows this file.

## TDD Gate Compliance

Three `test(08-13)` commits precede three `feat(08-13)` commits; no refactor commit was needed.

- **RED, Task 1:** AppointmentActionsTest and the tab cases failed because `App\Actions\CancelAppointment` and the `cancelVisit` action did not exist, and the scan expected a writer that was missing. Semantic assessment: the target tests ran and failed on the planned assertions' missing behavior, not on syntax, load or fixture faults.
- **RED, Task 2:** 29 target cases failed on missing `MarkAppointmentDone`, `EditVisitNote`, `markDone`/`editNote` actions and the absent D-25 refusal ("Exception [DomainException] not thrown"); the 74 existing cases in the same files kept passing.
- **RED, Task 3:** the browser tests for Cancel visit and Mark done failed with focus on `BODY` instead of `Book by hand` (the research Pattern 11 failure), which is the planned assertion. The axe state failed on the contact details list (see Issues). The RED commit (`cfb203a`) holds the final test file, committed alone before the relation-manager change, so it fails against its own parent; the file had been adjusted (the `?relation=1` address and the axe scope) between that first failing run and the commit.

## Files Created/Modified

- `app/Actions/CancelAppointment.php` - cancel, final, ends pending sends
- `app/Actions/MarkAppointmentDone.php` - done after the start, optional close of the job
- `app/Actions/EditVisitNote.php` - the visit note
- `app/Actions/CloseProject.php` - the D-25 refusal and `blockedBy()`
- `app/Rooms/ScheduleAndBooking/BookingRefused.php` - `notStarted()` and `notBooked()` refusals
- `app/Filament/Resources/Contacts/RelationManagers/AppointmentsRelationManager.php` - the three row actions, the focus event and listener
- `app/Filament/Resources/Contacts/RelationManagers/ProjectsRelationManager.php` - the Close dialog for a job with a visit ahead, and its refusal as a danger notice
- `tests/Feature/Booking/AppointmentActionsTest.php`, `tests/Feature/Filament/AppointmentsRelationManagerTest.php`, `tests/Feature/Filament/AppointmentsKeyboardTest.php`, `tests/Feature/Projects/ProjectActionsTest.php`, `tests/Unit/AppointmentWritesTest.php`
- `docs/BLUEPRINT.md` (sections 3 and 4), `docs/sprints/sprint-1.1.md` (walk-through line, plan 08-13)

## Decisions Made

- The Close dialog's sentence lives in `CloseProject::blockedBy()` rather than being rebuilt in the relation manager, so the courtesy dialog and the guard share one source (the plan put the read in `CloseProject` and the dialog in the manager; this keeps them from drifting).
- The focus move uses a Livewire `dispatch()` that the Book by hand button hears with `x-on:...window` and acts on in `$nextTick`; no fixed delay was needed and the test passed on every run (3 repeated runs).
- Axe is scoped to `.fi-resource-relation-manager` and `.fi-modal-open`, after awaiting `document.getAnimations()`: an unscoped run flagged the contact details list (not this tab), and a run during the modal's fade flagged contrast that is gone once the fade finishes.
- The contact page draws its relation managers as tabs, one at a time, so the Appointments content is not on the page until its tab is chosen. The first browser test walks there by keyboard (Tab to "Appointments", Enter, Tab to Book by hand); the others open the page with `?relation=1`.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] The Appointments tab is a second tab, not on the page at load**
- **Found during:** Task 3 (RED run)
- **Issue:** the plan says the tab is reached by Tab from the top, but Filament lists two relation managers as tabs and draws one; the Appointments content was not present until its tab was activated, so every browser test failed to find the tab.
- **Fix:** the tab-walk test goes Tab to the "Appointments" tab, Enter, then Tab to Book by hand; the focus, dialog and axe tests open the page on `?relation=1`.
- **Files modified:** tests/Feature/Filament/AppointmentsKeyboardTest.php
- **Committed in:** cfb203a

**2. [Rule 2 - Missing critical] A job's close refusal reached from the Projects tab needed a danger notice**
- **Found during:** Task 2
- **Issue:** the plan says the toggle's action still catches CloseProject's DomainException; the existing toggle did not catch anything, so a stale page's refused close would have thrown.
- **Fix:** the close branch catches `DomainException` and shows an escaped danger notification (test: refuses the close from a stale page).
- **Files modified:** app/Filament/Resources/Contacts/RelationManagers/ProjectsRelationManager.php
- **Committed in:** e9f0f73

**3. [Scope] axe is scoped to the tab, not the whole contact screen**
- **Found during:** Task 3
- **Issue:** the plan's "pageAxeReport() on the contact screen" fails on two `.fi-in-placeholder` color-contrast nodes in the contact details list above the tabs, which this plan did not touch.
- **Fix:** an axe helper in the keyboard test scopes the run to the tab and its dialog; the finding is logged in `.planning/phases/08-schedule-and-booking/deferred-items.md` and as a `lint-warning` entry in `.planning/WINDOWS.md` for Phase 11.
- **Files modified:** tests/Feature/Filament/AppointmentsKeyboardTest.php
- **Committed in:** cfb203a

---

**Total deviations:** 3 (1 blocking, 1 missing critical, 1 scope). 
**Impact on plan:** none beyond the test route to the tab and the axe scope; the behavior the plan named is all present and tested.

## Issues Encountered

- A first draft of one tab test assumed the Mark done dialog offered "Also close" on a job with two booked visits; by the plan's own definition neither is the last, so the test now marks the first done and then closes through the second.
- Mid-fade modal text read as a contrast fault in axe; awaiting the page's animations before the run removed it.
- The BSD grep on this machine reads `$` inside a basic pattern as an anchor, so two of the plan's criteria greps (`bool $alsoCloseProject`, `expectFocusOn($browser, 'Book by hand')`) return no match with plain `grep -q` here and match with `grep -F`; the strings are in the files.

## User Setup Required

None - no external service configuration required.

## Hand checks carried (never a pause)

Both left unrun and recorded as `unrun-verify` entries in `.planning/WINDOWS.md`:

- After Ian pushes the phase branch, the CI pgsql leg is green for `AppointmentActionsTest` and `ProjectActionsTest`'s D-25 cases (local runs are SQLite only).
- Phase 11 owner-screen pass (A11Y-03): the VoiceOver listen of the Appointments tab and its dialogs (D-43).

## Known Stubs

None.

## Threat Flags

None. The new surface is the three row actions, each locking and re-reading its row through the scoped model (T-08-53), recording who acted (T-08-54), ending pending sends (T-08-55), refusing the close inside CloseProject's lock (T-08-56) and escaping owner text in every aria-label and notification (T-08-57).

## Next Phase Readiness

- Ready for 08-14 (BlockTime and RemoveBlock): `tests/Unit/AppointmentWritesTest.php` already lists four writers and the agent-code arch rule covers all four visit actions; 08-14 adds its two writers to the same list.
- The Schedule page (08-14/08-15) can reuse the visit text and aria-label shape from the relation manager.
- Not touched: the existing ProjectsRelationManagerTest and ProjectsRelationManagerKeyboardTest, which pass unchanged (`git diff` against the plan's base is empty for both). The full suite passed: 2897 tests, no browser test skipped.

## Self-Check: PASSED

All created files exist; commits a8249fa, 78c799a, 2f30731, e9f0f73, cfb203a and 791dcee are ancestors of HEAD; `git rev-list --count` from the ledger base reports 6.

---
*Phase: 08-schedule-and-booking*
*Completed: 2026-11-07*
