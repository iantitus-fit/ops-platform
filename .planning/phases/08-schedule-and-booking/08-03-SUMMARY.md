---
phase: 08-schedule-and-booking
plan: 03
subsystem: team-alerts
tags: [owner-alert, change-request, one-alert-order, front-desk, tdd, pest]
requires:
  - phase: 08-schedule-and-booking
    provides: "08-01: Appointment model with booked() and ahead() scopes, Contact::appointments()"
provides:
  - "AlertReason::AppointmentBooked ('Visit booked') and AlertReason::ChangeRequest ('Booked customer asked about the visit')"
  - "RunFrontDesk change-request decision: a booked customer's scheduling text raises one change_request in the one-alert order"
  - "OwnerAlertText body for appointment_booked: names the visit, quotes no customer text"
  - "change_request listed under right_away in examples/example-hvac/rules/sending.md"
affects: [08-07 page booking raises appointment_booked, team, customer communication, operations, safety and compliance]
requirements-completed: [BOOK-05]
actuals:
  tokens: 12750
  tasks: 3
  commits: 6
plan_head_before: f71ff7b37f85278d4cb71c2ae77a9c9f88e540c5
plan_head_after: 2d2710457f8b897026d72b22962d3c405f13c6e6
status: complete
tech-stack:
  added: []
  patterns:
    - "Remember where a run started: the highest owner_alerts id for the conversation, so one query answers 'was any alert stored during this run?' even for alerts other classes raised"
    - "Probe sweep: a temporary backtrace line named every existing test that reaches the new branch, then was removed"
key-files:
  created:
    - tests/Feature/Alerts/ChangeRequestAlertTest.php
    - tests/Feature/Alerts/BookingAlertTest.php
    - tests/Feature/Booking/NoTextBookingTest.php
  modified:
    - app/Enums/AlertReason.php
    - app/Actions/RunFrontDesk.php
    - app/Actions/OwnerAlertText.php
    - examples/example-hvac/rules/sending.md
    - tests/Feature/Icm/SendingRulesValidationTest.php
    - tests/Feature/Icm/ValidatorTest.php
    - docs/BLUEPRINT.md
    - docs/sprints/sprint-1.1.md
key-decisions:
  - "The change request is decided once after the agent replies (visitAskedAbout) and swaps in for draft_waiting and check_in at their raise sites; a higher-ranked alert stored since the run began (complaint, asked_for_person, life_safety, paused_message, front_desk_failed) wins"
  - "When a run ends normally with this text a change request and no alert of any kind stored, change_request is raised then (a sent scheduling reply still tells the owner)"
  - "RunFrontDesk reads the visit through $conversation->contact->appointments() and never imports Appointment (D-04 scan)"
  - "appointment_booked leaves out the They wrote and Draft lines (research Pitfall 8)"
coverage:
  - id: D1
    description: "A booked customer's scheduling text gets today's reply and one change_request alert naming the visit; the appointment is untouched"
    requirement: BOOK-05
    verification:
      - kind: unit
        ref: "tests/Feature/Alerts/ChangeRequestAlertTest.php#alerts the owner once when a booked customer asks to move their visit (D-34, D-04)"
        status: pass
    human_judgment: false
  - id: D2
    description: "The change request takes draft_waiting's and check_in's place and gives way to complaint, person, safety, paused and failure; burst raises one; boundary and after-hours timing"
    requirement: BOOK-05
    verification:
      - kind: unit
        ref: "tests/Feature/Alerts/ChangeRequestAlertTest.php (9 tests, 5 in the higher-alert dataset)"
        status: pass
    human_judgment: false
  - id: D3
    description: "No inbound text books, moves or cancels a visit (D-04)"
    verification:
      - kind: unit
        ref: "tests/Feature/Booking/NoTextBookingTest.php (2 tests)"
        status: pass
    human_judgment: false
  - id: D4
    description: "appointment_booked names the visit, quotes no customer text, waits for the opening after hours, is stored once per key"
    verification:
      - kind: unit
        ref: "tests/Feature/Alerts/BookingAlertTest.php (5 tests)"
        status: pass
    human_judgment: false
  - id: D5
    description: "CI pgsql leg green for the ordering dataset and the once-per-key case"
    human_judgment: true
    rationale: "Needs Ian to push the phase branch; not run locally on Postgres. Recorded as an unrun-verify entry in .planning/WINDOWS.md."
---

# Phase 8 Plan 03: Change request and booked alerts Summary

A booked customer who texts about scheduling now gets today's reply and one `change_request` owner alert naming the visit in the business zone, placed in the one-alert order; `appointment_booked` exists with its own quote-free words and timing, ready for plan 08-07 to raise.

## Accomplishments

- `AlertReason` gains `AppointmentBooked` and `ChangeRequest` with plain words (D-33, D-34).
- `RunFrontDesk` notes the highest alert id when a run starts, decides after the agent replies whether the text is a change request, swaps it in for `draft_waiting` and `check_in`, defers to a higher-ranked alert stored during the run, and raises it at the end of a run that stored no alert. The post-agent part of `handle()` moved into `answerFromAgentReply()` so its early returns land back in `handle()`.
- The example folder lists `change_request` before `check_in` under `right_away`, with one plain sentence; it goes at once at any hour.
- No text writes an appointment: `NoTextBookingTest` runs a confirmation, a cancel request and a booking request and the rows are unchanged.
- `OwnerAlertText` omits the `They wrote:` and `Draft:` lines for `appointment_booked`.
- BLUEPRINT sections 6 and 1 and the sprint 1.1 walk-through log carry the new Built bullets and one concept line.

## Task Commits

| Task | Commit | Message |
|---|---|---|
| 1 RED | 90d3219 | test(08-03): a booked customer asking about their visit alerts the owner once |
| 1 GREEN | defbe67 | feat(08-03): a booked customer's scheduling text alerts the owner once, naming the visit |
| 2 RED | 585c36f | test(08-03): the change request's place in the one-alert order, and no text books |
| 2 GREEN | 8089593 | feat(08-03): change_request right away in the example, and the order proven |
| 3 RED | dcdaacc | test(08-03): the booked alert names the visit and waits for the opening after hours |
| 3 GREEN | 2d27104 | feat(08-03): the booked alert's own words and timing (D-33) |

## Deviations from Plan

### Approved by Ian (checkpoint, 2026-11-04)

**1. [Approved - existing test] SendingRulesValidationTest.php:25 also lists every AlertReason value**
- **Found during:** Task 1 GREEN (the previous executor stopped here)
- **Issue:** the dataset row 'an alert event nobody knows' expects a refusal line that lists every `AlertReason` value literally, so the two new cases turned it red. The plan names :10 and :82 in that file, not :25.
- **Fix:** one literal on line 25: the list now ends `..., safety_check_down, safety_check_missing, appointment_booked, change_request (you wrote soon)`. No assertion changed.
- **Effect on the plan's counts:** the added-line grep after Task 1 prints 2, not 1; the Task 2 numstat for SendingRulesValidationTest.php shows 3 removed lines, not 2 (:10, :25, :82). Task 2's name-only check still lists exactly the two named files.
- **Files modified:** tests/Feature/Icm/SendingRulesValidationTest.php
- **Commit:** defbe67

### Auto-fixed Issues

**2. [Rule 1 - Bug] The test helper stored the appointment start as if UTC**
- **Found during:** Task 1 RED to GREEN
- **Issue:** the RED helper stored 10:00 as UTC, so the visit read 4:00 AM in the business zone and the expected "10:00 AM" text could not match.
- **Fix:** the helper sets `travelTo` and the appointment start in UTC (`->utc()`) from business-zone times. The app stores and compares UTC.
- **Files modified:** tests/Feature/Alerts/ChangeRequestAlertTest.php
- **Commit:** defbe67

**Total deviations:** 2 (1 approved by Ian, 1 auto-fixed). **Impact:** none on behavior; both are in test code.

## Task 2 notes

- Ordering cases that already passed from Task 1's code (written RED, 18 of 19 tests green): the draft-waiting swap, the check-in swap, all five higher-alert rows, the burst, and the boundary. Only 'goes at once after hours' with change_request listed failed, as the plan predicted, because the example did not yet list it. The 'taken off right_away' row passes both before and after, because an unlisted reason already follows owner_alert timing.
- Probe (never committed, removed, file deleted): the swap branch and the end-of-run raise wrote the calling test file:line while `tests/Feature/FrontDesk`, `tests/Feature/Alerts` and `tests/Feature/Inbound` ran (974 tests). Every hit, 8 in all, was in `ChangeRequestAlertTest.php`. No existing test reaches the new branch.

## Verification

- `php artisan test --compact` (full suite, SQLite): 2525 passed.
- `php artisan test --compact tests/Feature/Alerts tests/Feature/Booking tests/Feature/Icm tests/Feature/FrontDesk`: 1167 passed (before Task 3 additions).
- Acceptance greps for all three tasks pass. Changed existing test files since the plan base: SendingRulesValidationTest.php and ValidatorTest.php only (numstat 3/3 and 1/1).
- `RunFrontDesk` has 0 `use App\Models\Appointment;` lines.
- Pint passed on every commit.

## Unrun checks (never a pause)

- **Postgres:** after Ian pushes the phase branch, confirm the CI pgsql leg is green, in particular `ChangeRequestAlertTest`'s ordering dataset and `BookingAlertTest`'s once-per-key case. Recorded as an `unrun-verify` entry in `.planning/WINDOWS.md`.

## Known Stubs

None.

## Threat Flags

None. No new endpoint, auth path or file access; T-08-10 to T-08-13 are mitigated and tested.

## Issues Encountered

None beyond the approved line 25 literal.

## Next

Ready for 08-04.

## Self-Check: PASSED

All created and modified files exist; commits 90d3219, defbe67, 585c36f, 8089593, dcdaacc and 2d27104 are on the branch.
