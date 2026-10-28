---
phase: 06-front-desk-and-team-rooms
plan: 06
subsystem: database
tags: [laravel, queue, serialization, claim-token, front-desk, owner-alerts, sqlite, postgres]

requires:
  - phase: 06-front-desk-and-team-rooms
    provides: 06-05 unique alert keys, the last piece of sent-and-stored-once for owner alerts
provides:
  - messages.claim_token (nullable string, 36) and the (conversation_id, claim_token) index
  - ProcessInbound::$claimToken, made in the constructor so it travels in the queued payload
  - ClaimInboundBurst writing the token in the same update that sets handled_at
  - ProcessInbound::failed() reporting exactly the burst its own run claimed, found by the token
  - ReportFrontDeskFailure::subjectOf(), the newest text message of a burst, else its newest row
  - MessageFactory::claimedBy()
affects: [06-07, team room, front desk room, owner alerts]

actuals:
  tokens: 8074
  tasks: 2
  commits: 4
plan_head_before: 27dd53785e5c42f3cf6bc2ae61e32a7bd49e5b0f
plan_head_after: 022c478a0550a1df9296fa6e9485f8b4de101aec

tech-stack:
  added: []
  patterns:
    - "A run identity made in the job constructor (a public property), so the copy failed() runs on, rebuilt from the stored payload, has it"
    - "The claim writes its token in the same conditional UPDATE that claims the row, so no claimed row ever lacks it"
    - "Serialize-then-call tests (unserialize(serialize($job))->failed()) because Queue::fake runs handle() on the pushed object and would hide a token chosen at run time"

key-files:
  created:
    - database/migrations/2026_10_27_000005_add_claim_token_to_messages_table.php
    - tests/Feature/FrontDesk/ClaimTokenTest.php
  modified:
    - app/Jobs/ProcessInbound.php
    - app/Actions/ClaimInboundBurst.php
    - app/Actions/ReportFrontDeskFailure.php
    - app/Models/Message.php
    - database/factories/MessageFactory.php
    - tests/Feature/FrontDesk/FrontDeskFailureTest.php
    - docs/BLUEPRINT.md
    - docs/sprints/sprint-1.1.md

key-decisions:
  - "$claimToken is declared with no default: a default would give every older payload the same token and break D-12 (3); the answer for payloads queued before this lands is a queue drain (Deploy note)"
  - "The RED for failed() is the later-text job, the run that claimed nothing and the form-only burst; the serialized-copy and STOP cases pass on the old id-ordering code only because those jobs' own message ids happened to match, so they stay as guards"
  - "ReportFrontDeskFailure::handle() keeps its signature (four callers); subjectOf() is a static helper beside it"

patterns-established:
  - "Token-matched lookup in failed(): the conversation's inbound customer rows where claim_token equals the job's token, never id ordering or a time window"

requirements-completed: [ALRT-02]

coverage:
  - id: D1
    description: "Every row a front-desk run claims carries that run's token, written by the claiming update; a tokenless claim makes a fresh one; a STOP text handled on arrival carries none"
    requirement: ALRT-02
    verification:
      - kind: integration
        ref: "tests/Feature/FrontDesk/ClaimTokenTest.php#writes the first queued job's token on both of Dana's texts when she texts twice before the run"
        status: pass
      - kind: integration
        ref: "tests/Feature/FrontDesk/ClaimTokenTest.php#writes one fresh token on every row it claims when called without a token, and the given token when called with one"
        status: pass
      - kind: integration
        ref: "tests/Feature/FrontDesk/ClaimTokenTest.php#stores a STOP text handled on arrival with no claim token"
        status: pass
    human_judgment: false
  - id: D2
    description: "The token is made with the job and survives serialization, so failed() on the rebuilt copy reports the right burst"
    requirement: ALRT-02
    verification:
      - kind: integration
        ref: "tests/Feature/FrontDesk/ClaimTokenTest.php#keeps the claim token in the serialized form of the job, the way the queue stores it"
        status: pass
      - kind: integration
        ref: "tests/Feature/FrontDesk/ClaimTokenTest.php#reports her claimed text, not her later one, when failed() runs on a copy rebuilt from the serialized job (D-12 (4))"
        status: pass
    human_judgment: false
  - id: D3
    description: "A stopped run tells the owner about exactly the texts it claimed: a later text, an earlier run's text and a STOP text are never blamed; a run that claimed nothing reports nothing; a form-only burst is reported against the form; the words stay fixed"
    requirement: ALRT-02
    verification:
      - kind: integration
        ref: "tests/Feature/FrontDesk/ClaimTokenTest.php#does not blame an earlier run's text for the later text's job, whose token claimed nothing (WR-22)"
        status: pass
      - kind: integration
        ref: "tests/Feature/FrontDesk/ClaimTokenTest.php#reports only its own run's text when two runs claimed different texts of one conversation (D-12 (3))"
        status: pass
      - kind: integration
        ref: "tests/Feature/FrontDesk/ClaimTokenTest.php#reports a web form lead claimed on its own, tied to the form (D-20)"
        status: pass
      - kind: integration
        ref: "tests/Feature/FrontDesk/FrontDeskFailureTest.php#tells the owner when the worker stops the run before it finishes"
        status: pass
    human_judgment: false
  - id: D4
    description: "Same tests green on Postgres (CI pgsql leg)"
    requirement: ALRT-02
    verification: []
    human_judgment: true
    rationale: "Hand check left unrun for UAT per Ian's standing rule; recorded as WINDOWS.md entry 32"

duration: 6min
completed: 2026-10-28
status: complete
---

# Phase 6 Plan 06: The claim token Summary

**A failed front-desk run now reports exactly the burst it claimed: ProcessInbound makes a claim token in its constructor, the claim writes it on every row it takes, and failed(), on the copy the queue rebuilds, finds the burst by that token instead of guessing by id.**

## Performance

- **Duration:** 6 min
- **Started:** 2026-10-28T16:07Z
- **Completed:** 2026-10-28T16:15Z
- **Tasks:** 2 (a tracer and an auto task, both TDD)
- **Files modified:** 10

## Accomplishments

- `messages.claim_token` and its index, written by the same conditional UPDATE that sets `handled_at`; a keyword text (STOP) handled on arrival keeps NULL and belongs to no run.
- `ProcessInbound::$claimToken` is a constructor-made public property, so it is in the serialized payload; a copy rebuilt with `unserialize(serialize($job))` reports the right burst.
- `failed()` selects the conversation's customer rows by token. A later text, an earlier run's text and a STOP text are never blamed (WR-22, success criterion 5); a run that claimed nothing reports nothing (D-13); a form-only burst is reported against the form (D-20). Note and alert words are unchanged and the exception's text never reaches either.
- Tracer gate: Task 1 verify (204 tests) re-run end to end and green before Task 2 began, so expansion went ahead.

## TDD Gate Compliance

- RED 1 `bd374f5`: all six cases failed on the planned assertions (undefined `$claimToken`, missing column, null token on a tokenless claim).
- GREEN 1 `f8d78a6`: 204 tests across the claim, inbound and failure files pass with no existing test changed.
- RED 2 `463aee9`: three cases fail on the old id-ordering `failed()`: the later text's job (it blamed the earlier run's text), the job whose token claimed nothing (it reported anyway), and the form-only burst (it reported nothing).
- GREEN 2 `022c478`: 272 tests in the touched areas pass, then the full suite: 2257 passed.
- No REFACTOR commit; nothing needed cleaning.

## Task Commits

1. **Task 1: claim token made with the job and written on every claimed row**
   - `bd374f5` test: the claim token travels with the job and lands on the claimed rows
   - `f8d78a6` feat: every claimed text carries the run's claim token (D-12), with the BLUEPRINT Built bullet and the walk-through line
2. **Task 2: failed() reports exactly its own run's burst**
   - `463aee9` test: failed() reports exactly its own run's burst, with `MessageFactory::claimedBy()`
   - `022c478` feat: failed() reports the burst its run claimed, by token (ALRT-02, WR-22), with the BLUEPRINT bullet extended

**Plan metadata:** the docs commit that carries this file, STATE.md, ROADMAP.md, REQUIREMENTS.md and WINDOWS.md.

## Files Created/Modified

- `database/migrations/2026_10_27_000005_add_claim_token_to_messages_table.php` - the column and the `(conversation_id, claim_token)` index, with a down()
- `app/Jobs/ProcessInbound.php` - `$claimToken`, constructor third argument, `handle()` passes it to the claim, `failed()` by token
- `app/Actions/ClaimInboundBurst.php` - optional token (fresh UUID when absent) written by the claiming update
- `app/Actions/ReportFrontDeskFailure.php` - `subjectOf()`; `handle()` unchanged
- `app/Models/Message.php` - `claim_token` fillable
- `database/factories/MessageFactory.php` - `claimedBy()`
- `tests/Feature/FrontDesk/ClaimTokenTest.php` - 14 cases
- `tests/Feature/FrontDesk/FrontDeskFailureTest.php` - four named cases re-pointed at a token; titles and assertions unchanged
- `docs/BLUEPRINT.md`, `docs/sprints/sprint-1.1.md` - one Built bullet (written in Task 1, extended in Task 2) and one walk-through line

## Decisions Made

- `$claimToken` has no default (see key-decisions); older payloads are handled by a queue drain, not code.
- `subjectOf()` is a static helper on `ReportFrontDeskFailure` so `handle()`'s four callers do not change (Pitfall 5).
- The BLUEPRINT bullet was added in Task 1's feat commit and extended in Task 2's, so each commit that changed the area carried its line.

## Deviations from Plan

None - plan executed exactly as written.

One finding worth Ian's eye, not a deviation: the plan expected the serialized-copy and later-text cases to fail on Task 1's `failed()`. They do not, because in those scenarios the job's own message id happens to be the claimed text, so id ordering gave the right answer by coincidence. The RED for Task 2 therefore comes from three other cases (a later text's job, a job whose token claimed nothing, a form-only burst), and I added one case ("does not blame an earlier run's text for the later text's job") to expose the real WR-22 failure. The serialize and STOP cases stay as guards.

**Total deviations:** 0.

## Issues Encountered

None.

## Deploy note

Any database queue this lands on, a developer's local one included, must hold no waiting `ProcessInbound` job when it lands (let a worker empty it first, for example `php artisan queue:work --stop-when-empty`). `$claimToken` stays declared with no default, because a default would hand every older payload the same token and break D-12 (3); a payload queued before this plan has no `$claimToken`, and `handle()` or `failed()` on it would stop on the uninitialized property. No deployed instance exists before the Phase 12 tour deploy, so nothing is waiting today.

## Hand check (left unrun)

Per Ian's standing rule for GSD runs, the Postgres check was not run and nothing paused. After Ian pushes the phase branch, the CI pgsql leg should run `tests/Feature/FrontDesk/ClaimTokenTest.php` and `FrontDeskFailureTest.php` green (D-12). A local run: `docker run -d --rm --name ops-pg -e POSTGRES_PASSWORD=postgres -e POSTGRES_DB=ops_test -p 55432:5432 postgres:17`, then `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=ops_test DB_USERNAME=postgres DB_PASSWORD=postgres php artisan test --compact tests/Feature/FrontDesk/ClaimTokenTest.php tests/Feature/FrontDesk/FrontDeskFailureTest.php`, then `docker stop ops-pg`. Recorded as `.planning/WINDOWS.md` entry 32 (`unrun-verify`).

## Known Stubs

None.

## Threat Flags

None. The migration adds one nullable identifier column; no new endpoint, auth path or trust boundary. T-6-13, T-6-14 and T-6-15 are mitigated by the token lookup, the unchanged fixed words and the serialize-then-failed() test.

## Next Phase Readiness

Ready for 06-07: the report once per token on both paths. The existing case 'reports a crash once even when failed() runs after the job already reported it' is hollow after this plan (its failed() job now has a fresh token and finds nothing); 06-07 re-points it to a same-token copy of the job that ran.

## Self-Check: PASSED

Created and modified files exist on disk; commits bd374f5, f8d78a6, 463aee9 and 022c478 are on the branch; the full suite (2257 tests) passes; every task's acceptance criteria pass; no unexpected file deletions.
