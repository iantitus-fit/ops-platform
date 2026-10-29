---
phase: 06-front-desk-and-team-rooms
plan: 07
subsystem: database
tags: [laravel, queue, owner-alerts, front-desk, report-once, conditional-update, sqlite, postgres]

requires:
  - phase: 06-front-desk-and-team-rooms
    provides: 06-06 claim token, ReportFrontDeskFailure::subjectOf(), failed() reporting the run's own burst
provides:
  - messages.failure_reported_at (nullable timestamp), the once-per-token report claim
  - ReportFrontDeskFailure::handle() taking one conditional UPDATE on the token's report row before it writes, releasing it and rethrowing when the note or alert cannot be written
  - ProcessInbound::handle() catch reporting subjectOf($burst), with the try covering the dangerous-form alert, and private holdsText()
  - tests/Feature/FrontDesk/FailureReportOnceTest.php
affects: [phase 6 verification, team room, front desk room, owner alerts]

actuals:
  tokens: 4709
  tasks: 2
  commits: 4
plan_head_before: 03980306ce474443d2034e8aaff595b57c728d63
plan_head_after: 24e089fb615808b740102e7937b2ff96fb4ccb37

tech-stack:
  added: []
  patterns:
    - "Claim, release and rethrow (the SafetyCheckStatus::claimThenSend() shape) applied to a failure report: one conditional UPDATE decides the reporter, a throw gives the claim back"
    - "The claim sits on the token's report row (subjectOf over the token's rows), not on the row a caller passes, so callers that pass $burst->last() share one claim with callers that pass the newest text"
    - "Race tests call the paths in both orders on the same job and on unserialize(serialize($job)); no two-connection test"

key-files:
  created:
    - database/migrations/2026_10_27_000006_add_failure_reported_at_to_messages_table.php
    - tests/Feature/FrontDesk/FailureReportOnceTest.php
  modified:
    - app/Actions/ReportFrontDeskFailure.php
    - app/Jobs/ProcessInbound.php
    - app/Models/Message.php
    - tests/Feature/FrontDesk/FrontDeskFailureTest.php
    - docs/BLUEPRINT.md

key-decisions:
  - "The report claim is taken only when the passed text carries a claim_token; a text with none (rows from before 06-06, factory rows) runs today's alert-exists check unchanged"
  - "The claim is kept when the existing 'an alert for this text already exists' check then returns, because another writer (RunFrontDesk, OutboundSender) already told the owner"
  - "ProcessInbound::handle() returns holdsText($burst) from both catches, so a form-only burst still returns false and every burst that reached a catch before returns what it did"

patterns-established:
  - "One conditional UPDATE on a nullable timestamp column is the cross-path 'who reports' decision, atomic on SQLite and Postgres without a lock"

requirements-completed: [ALRT-02]

coverage:
  - id: D1
    description: "A failed run is reported once per claim token whichever of the run's catch, failed() and RunFrontDesk's own reports gets there first, including a burst whose newest row is a web form"
    requirement: ALRT-02
    verification:
      - kind: integration
        ref: "tests/Feature/FrontDesk/FailureReportOnceTest.php#reports once when failed() reports first and the catch then reports the same burst (D-11)"
        status: pass
      - kind: integration
        ref: "tests/Feature/FrontDesk/FailureReportOnceTest.php#reports once when RunFrontDesk reports that no agent answers texts and failed() then runs on the same job (D-11)"
        status: pass
      - kind: integration
        ref: "tests/Feature/FrontDesk/FailureReportOnceTest.php#reports once for a burst whose newest row is a web form, whichever row the first caller passes (D-11)"
        status: pass
      - kind: integration
        ref: "tests/Feature/FrontDesk/FrontDeskFailureTest.php#reports a crash once even when failed() runs after the job already reported it"
        status: pass
    human_judgment: false
  - id: D2
    description: "A report that cannot be written gives its claim back and rethrows, so the other path still tells the owner; the exception's words never reach the note or alert"
    requirement: ALRT-02
    verification:
      - kind: integration
        ref: "tests/Feature/FrontDesk/FailureReportOnceTest.php#gives the claim back when the report cannot be written, so the other path still tells the owner (02.1 IN-11)"
        status: pass
      - kind: integration
        ref: "tests/Feature/FrontDesk/FailureReportOnceTest.php#keeps the words fixed, and the exception message out of the note and the alert, on a report that broke and was retried"
        status: pass
    human_judgment: false
  - id: D3
    description: "The catch path reports the newest text message the run claimed (the form when it claimed only a form), and a failed web-form-only burst is reported on the catch path"
    requirement: ALRT-02
    verification:
      - kind: integration
        ref: "tests/Feature/FrontDesk/FailureReportOnceTest.php#reports a failed web-form-only burst on the catch path, tied to the form, and texts the lead nothing (D-20)"
        status: pass
      - kind: integration
        ref: "tests/Feature/FrontDesk/FailureReportOnceTest.php#reports her text, not the newer web form, when a run that claimed both throws (D-20, research A6)"
        status: pass
      - kind: integration
        ref: "tests/Feature/FrontDesk/FailureReportOnceTest.php#adds nothing when failed() runs after the catch reported a form-only burst (D-11, D-20)"
        status: pass
    human_judgment: false
  - id: D4
    description: "Same tests green on Postgres (CI pgsql leg), the phase gate before /gsd-verify-work"
    requirement: ALRT-02
    verification: []
    human_judgment: true
    rationale: "Hand check left unrun for UAT per Ian's standing rule; recorded as WINDOWS.md entry 33"

duration: 9min
completed: 2026-10-28
status: complete
---

# Phase 6 Plan 07: Report a failed run once per claim token Summary

**One conditional UPDATE on `messages.failure_reported_at` now decides which of the run's catch, the queue's failed() and RunFrontDesk's own reports writes the note and raises the alert, a report that breaks gives its claim back so the other path reports, and a failed web-form-only burst is reported on the catch path too.**

## Performance

- **Duration:** 9 min
- **Started:** 2026-10-28T16:27Z
- **Completed:** 2026-10-28T16:37Z
- **Tasks:** 2 (a tracer and an auto task, both TDD)
- **Files modified:** 7

## Accomplishments

- `messages.failure_reported_at` and the claim in `ReportFrontDeskFailure::handle()` (signature unchanged): given a text with a claim token, it loads the token's customer rows, takes `subjectOf()` of them as the report row, and updates that row's `failure_reported_at` while it is null; losing the update returns without writing. The note still comes before the alert, in today's words, and the existing "alert already exists" check stays after the claim because RunFrontDesk and OutboundSender raise `front_desk_failed` directly.
- Release on failure (02.1 IN-11): the note, the `CustomerRecordChanged` event and the alert sit inside one try; on any Throwable the claim row's `failure_reported_at` goes back to null and the exception is rethrown. A second note is the accepted duplicate, a missing alert is not.
- `ProcessInbound::handle()`: one try now covers `alertOwnerToDangerousForms()`, the no-text stop and the front-desk run. Both catches return `holdsText($burst)`, so the return value is unchanged for every burst that reached a catch before and a form-only burst still returns false. The Throwable catch reports `subjectOf($burst)`, so a failed form-only burst is reported on the catch path (D-20) and a mixed burst is reported against her text rather than the newer form (research A6).
- BLUEPRINT section 6: one Built bullet written in Task 1's feat commit and extended in place in Task 2's, so each commit that changed the area carries its line.
- Tracer gate: Task 1's verify (44 tests across the three FrontDesk files) re-run green before Task 2 began, so expansion went ahead.

## TDD Gate Compliance

- RED 1 `808a105`: four of the six new cases failed on the planned assertions. Three failed on `failure_reported_at` being unset (the column and claim did not exist; the attribute read as null), and the mixed-burst case failed on "2 is identical to 1", the second report really being written against the web form. The release case passed its throw and null checks trivially and failed once failed() should have set the claim. The no-token case passes on today's code and stays as a guard. The re-pointed crash-once case also passes on the old code, as the plan predicted.
- GREEN 1 `9218c33`: 44 tests across `FailureReportOnceTest`, `FrontDeskFailureTest` and `ClaimTokenTest` pass.
- RED 2 `592aa8f`: three cases failed on the planned assertions: the form-only burst's life-safety failure escaped `handle()` as an exception (the alert sat outside the try), `failed()` after it therefore had nothing to see, and the mixed-burst catch tied its alert to the form (`2` identical to `1`) instead of her text. The text-only case passes on old code and stays as a guard.
- GREEN 2 `24e089f`: the three FrontDesk, Inbound and Alerts folders (950 tests) pass, then the full suite: 2267 passed.
- No REFACTOR commit; nothing needed cleaning.

## Task Commits

1. **Task 1 (tracer): the catch and failed() race on one report claim per token**
   - `808a105` test: a failed run is reported once per claim token (new file plus the re-pointed crash-once case)
   - `9218c33` feat: report a failed run once per claim token (ALRT-02, D-11), with the migration, model, action and the BLUEPRINT Built bullet
2. **Task 2: the catch reports the same row failed() would, form-only bursts included**
   - `592aa8f` test: the catch path reports the same row as failed(), form-only bursts included
   - `24e089f` feat: both failure paths report the same row, form-only bursts included (D-20), with the BLUEPRINT bullet extended

**Plan metadata:** the docs commit that carries this file, STATE.md, ROADMAP.md, REQUIREMENTS.md and WINDOWS.md.

## Files Created/Modified

- `database/migrations/2026_10_27_000006_add_failure_reported_at_to_messages_table.php` - the nullable timestamp, with a down()
- `app/Actions/ReportFrontDeskFailure.php` - the report claim, release and rethrow; class docblock rewritten
- `app/Jobs/ProcessInbound.php` - widened try, `holdsText()`, catch reports `subjectOf($burst)`; handle() docblock updated
- `app/Models/Message.php` - `failure_reported_at` fillable and cast to datetime
- `tests/Feature/FrontDesk/FailureReportOnceTest.php` - 10 cases
- `tests/Feature/FrontDesk/FrontDeskFailureTest.php` - one line: the crash-once case's failed() now runs on `unserialize(serialize(Queue::pushed(ProcessInbound::class)->sole()))`; title, setup and assertions unchanged, no import needed
- `docs/BLUEPRINT.md` - one Built bullet in section 6

## Decisions Made

- The claim is only taken for a text that carries a claim token; tokenless texts keep today's path exactly (D-11 scope, and existing callers' tests stay unchanged).
- When the claim is won but the "alert already exists" check then returns, the claim is kept: the owner was already told by another writer, so giving it back would invite a second note.
- `failureOnceAlertThatBreaksOnce()` and its LifeSafety twin bind a `RaiseOwnerAlert` subclass as a singleton so the retrying path meets the same object that already threw.

## Deviations from Plan

None - plan executed exactly as written.

One test-assertion correction of my own, not a deviation from the plan: the form-only catch case first asserted `textsSentToCustomers()` empty, but the fake channel records the owner's alert text too. The assertion is now that the only text sent went to the owner's number, which is the same "nothing is texted to the lead" claim made precisely.

**Total deviations:** 0.

## Issues Encountered

None.

## For Ian

`frontDeskJobForTheOnlyConversation()` in `tests/Feature/FrontDesk/FrontDeskFailureTest.php` now has no caller. The plan asked me to leave it and tell you; removing it is your call.

## Hand check (left unrun)

Per Ian's standing rule for GSD runs, the Postgres check was not run and nothing paused. After Ian pushes the phase branch, the CI tests workflow should be green on both legs for the whole phase, `tests/Feature/FrontDesk/FailureReportOnceTest.php` included (D-11: the conditional update is atomic on SQLite and Postgres). This is the phase gate before `/gsd-verify-work`. A local run: `docker run -d --rm --name ops-pg -e POSTGRES_PASSWORD=postgres -e POSTGRES_DB=ops_test -p 55432:5432 postgres:17`, then `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=ops_test DB_USERNAME=postgres DB_PASSWORD=postgres php artisan test --compact`, then `docker stop ops-pg` (about 15 minutes). Recorded as `.planning/WINDOWS.md` entry 33 (`unrun-verify`).

## Known Stubs

None.

## Threat Flags

None. The migration adds one nullable timestamp column; no new endpoint, auth path or trust boundary. T-6-16 (a report that breaks leaves the claim for the other path), T-6-17 (one conditional UPDATE on the token's report row, tested in both orders and for a mixed burst) and T-6-18 (the try covers the dangerous-form alert and both paths report `subjectOf($burst)`) are mitigated and tested. The note and the alert keep today's words; an exception's message reaches neither.

## Next Phase Readiness

All seven plans of Phase 6 are done. The phase gate is the CI run on both databases after Ian pushes the branch (WINDOWS.md entries 30 to 33), then phase verification and `/gsd-verify-work`. Across the phase the only existing test changes are 06-01's three removals, the file count (23 to 25), 06-06's four named FrontDeskFailureTest cases and this plan's re-pointed crash-once case.

## Self-Check: PASSED

Created and modified files exist on disk; commits 808a105, 9218c33, 592aa8f and 24e089f are on the branch; the full suite (2267 tests) passes; every acceptance criterion passes (re-checked with fixed-string greps); no file deletions in any commit.
