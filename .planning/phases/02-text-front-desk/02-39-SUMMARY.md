---
phase: 02-text-front-desk
plan: 39
subsystem: ai
tags: [front-desk-prompt, time-zone, rules, example-folder, gap-closure, laravel, pest]

requires:
  - phase: 02-text-front-desk
    provides: Message::happenedAt() and send-time thread order (02-36), MessageStatus::onTheCustomersPhone() (02-38)
provides:
  - "## Right now" section last in the model's instructions, in the business's time zone
  - Bracketed send time on every earlier text in the history
  - Two RULES bullets (read the times; the thread is not a booking)
  - history() that keeps replies sent after the burst began
  - examples/example-hvac/business/services/furnace-repair.md
affects: [02-40, 02-41, end-of-phase UAT]

actuals:
  tokens: 6300
  tasks: 3
  commits: 6
plan_head_before: 261e7d1234f470ae007b22df935d38f07a051e34
plan_head_after: 2dff92c7a65dde188e5c5dd2fa5b9cdc5eec1dc8

tech-stack:
  added: []
  patterns:
    - "The changing part of the instructions (the time) goes last so everything above it stays a stable prefix"
    - "promptText() stays the customer's words alone; system text (times, dates) goes only in instructions() and history()"

key-files:
  created:
    - examples/example-hvac/business/services/furnace-repair.md
  modified:
    - app/Ai/FrontDeskPrompt.php
    - tests/Feature/FrontDesk/FrontDeskPromptTest.php
    - tests/Feature/FrontDesk/DraftReplacementTest.php
    - tests/Pest.php
    - tests/Feature/SendRule/JevClassifierTest.php
    - tests/Feature/Icm/ValidatorTest.php
    - tests/Feature/Icm/ActiveConfigTest.php
    - examples/example-hvac/business/services/CONTEXT.md
    - docs/sprints/sprint-1.md

key-decisions:
  - "history() skips the burst's own texts and any customer text newer than the burst's newest id, instead of stopping at the first burst text, so a reply that reached the customer while the burst waited stays in the history at its send time (G-02-1.2 decision)"
  - "Right now is built in a private rightNow() from now() converted with ActiveConfig::displayTimezone(), formatted to the minute"

patterns-established:
  - "Pest helpers for this file take the promptTest prefix (promptTestChicago, promptTestFreezeAtTenInTheMorning, promptTestDanaSundayThread, promptTestDanaTextsAndTheModelRuns)"

requirements-completed: [AGNT-02, AGNT-03]

coverage:
  - id: D1
    description: "The model's instructions end with a Right now section (weekday, date, time to the minute) in the business's zone, after the customer card"
    requirement: AGNT-02
    verification:
      - kind: integration
        ref: "tests/Feature/FrontDesk/FrontDeskPromptTest.php#tells the model the day and when each earlier text was sent, so yesterday's 'tomorrow at 9' reads right (G-02-2.2)"
        status: pass
      - kind: integration
        ref: "tests/Feature/FrontDesk/FrontDeskPromptTest.php#puts Right now last, after the customer card"
        status: pass
    human_judgment: false
  - id: D2
    description: "At 8:30 pm in Chicago, with UTC already on the next day, the section still says Chicago's day"
    requirement: AGNT-02
    verification:
      - kind: integration
        ref: "tests/Feature/FrontDesk/FrontDeskPromptTest.php#says the business's day when UTC has already moved on"
        status: pass
    human_judgment: false
  - id: D3
    description: "Each earlier text starts with its happenedAt() time in brackets in the business zone; a draft sent later shows its send time; placeholders and promptText() carry no time"
    requirement: AGNT-02
    verification:
      - kind: integration
        ref: "tests/Feature/FrontDesk/FrontDeskPromptTest.php#shows a draft the owner sent later at its send time"
        status: pass
      - kind: integration
        ref: "tests/Feature/FrontDesk/FrontDeskPromptTest.php#joins a burst into one prompt in arrival order"
        status: pass
      - kind: integration
        ref: "tests/Feature/FrontDesk/DraftReplacementTest.php#leaves a withdrawn draft out of the history the next run reads"
        status: pass
    human_judgment: false
  - id: D4
    description: "RULES and the instructions tell the model how to read the times and that the thread and card are not a booking"
    requirement: AGNT-03
    verification:
      - kind: integration
        ref: "tests/Feature/FrontDesk/FrontDeskPromptTest.php#tells the model how to read the times in the thread (G-02-2.2)"
        status: pass
      - kind: integration
        ref: "tests/Feature/FrontDesk/FrontDeskPromptTest.php#tells the model the thread is not a booking (G-02-2.4)"
        status: pass
    human_judgment: false
  - id: D5
    description: "History keeps a reply sent after the burst arrived and leaves out a customer text newer than the burst"
    requirement: AGNT-02
    verification:
      - kind: integration
        ref: "tests/Feature/FrontDesk/FrontDeskPromptTest.php#keeps a reply that went out after the burst arrived"
        status: pass
      - kind: integration
        ref: "tests/Feature/FrontDesk/FrontDeskPromptTest.php#leaves out a customer text that came in after the burst"
        status: pass
    human_judgment: false
  - id: D6
    description: "The example folder lists furnace repair (slug, intake questions, $89 as 8900 cents) and the agent loads it"
    requirement: AGNT-03
    verification:
      - kind: integration
        ref: "tests/Feature/FrontDesk/FrontDeskPromptTest.php#loads the furnace repair service and its intake questions"
        status: pass
      - kind: integration
        ref: "tests/Feature/FrontDesk/FrontDeskPromptTest.php#expands a folder entry to every typed file under it and keeps the others as written"
        status: pass
    human_judgment: false
  - id: D7
    description: "Live: Dana's held draft does not call the visit tomorrow or booked, and 'My furnace won't turn on.' from a new number gets a furnace-repair intake question"
    verification: []
    human_judgment: true
    rationale: "Needs live model keys; the executor was told not to run live hand checks. Carried to end-of-phase UAT (WINDOWS.md entry 9)"

duration: 4min
completed: 2026-10-13
status: complete
---

# Phase 2 Plan 39: The model knows the time, and the thread is not a booking Summary

**FrontDeskPrompt now ends its instructions with a "Right now" section in the business's zone, dates every earlier text with its send time in brackets, tells the model the thread is not a booking, keeps replies sent after the burst began, and the example folder gains a furnace-repair service**

## Performance

- **Duration:** 4 min
- **Started:** 2026-10-13T14:54:16Z
- **Completed:** 2026-10-13T14:59:00Z
- **Tasks:** 3
- **Files modified:** 10 (one created)

## Accomplishments
- G-02-2.2 closed in code: instructions() adds `## Right now` last ("It is Monday, October 12, 2026, 4:30 pm in America/Chicago, the business's time zone."), converted from UTC with `ActiveConfig::displayTimezone()`. At 8:30 pm Chicago (01:30 UTC the next day) it still says Monday.
- Every earlier text in history() starts with `[Sun Oct 11, 9:31 am] ` from `Message::happenedAt()`; a draft the owner sent later shows its send time. The two placeholder turns and `promptText()` stay bare, so the classifier and ask-for-a-person input is unchanged.
- G-02-2.4: two RULES bullets (the thread and card are not what the business agreed to; never say a visit, time or booking is set; earlier texts carry send times and "tomorrow" means that text's day; never put a bracketed time in a reply).
- G-02-1.2 history decision applied: `continue` instead of `break` at the burst, customer texts newer than the burst's newest id left out, so an approved draft sent while the burst waited stays in the history.
- Furnace repair added to the example folder in ac-repair.md's format ($89 diagnostic as `price_cents: 8900`, `symptom` and `fuel` intake questions); `business/services/CONTEXT.md` lists it.

## Task Commits

1. **Task 1: Tracer, the time now and per-turn times**
   - RED `e88513a` (test): four new tests, five exact-string tests updated
   - GREEN `7be157d` (fix): `rightNow()` and `timeOf()` in FrontDeskPrompt
2. **Task 2: Rules and history after the burst**
   - RED `199b387` (test)
   - GREEN `f425e91` (fix): two RULES bullets, `continue` and the newer-customer-text skip
3. **Task 3: Furnace repair service**
   - RED `c825a45` (test): count 12, folder expansion, new load test
   - GREEN `2dff92c` (feat): furnace-repair.md, CONTEXT.md list, ActiveConfigTest count

**Plan metadata:** committed separately (docs: complete plan, with this SUMMARY, WINDOWS.md and the walk-through log line)

## TDD evidence

- **Task 1 RED:** 9 of 25 tests failed on the planned assertions (missing `## Right now`, missing bracketed times; the five old exact-string tests failed on the missing prefix). Semantic assessment: the failures were assertion failures on the planned behaviour, not load or syntax faults.
- **Task 2 RED:** 6 of 24 failed (the five rules sentences and 'keeps a reply that went out after the burst arrived', which lost the 10:03 draft to the old `break`). 'leaves out a customer text that came in after the burst' passed before the change because the old `break` also dropped it; it stays as a guard for the new skip rule.
- **Task 3 RED:** the count change (12) and the folder-expansion and load tests failed with no furnace-repair.md (20 failures across FrontDeskPromptTest, JevClassifierTest and tests/Feature/Icm).
- **Tracer gate:** Task 1's verify list re-run end-to-end after the fix: 49 passed. Logged "Tracer verified end-to-end, expanding".
- `gsd_run check tdd-red-evidence` was not used (Pest output is not one of its supported formats); RED was judged from the test names and failure messages above.

## Files Created/Modified
- `app/Ai/FrontDeskPrompt.php` - `rightNow()`, `timeOf()`, two RULES bullets, history() skips the burst and newer customer texts
- `examples/example-hvac/business/services/furnace-repair.md` - new fictional service
- `examples/example-hvac/business/services/CONTEXT.md` - lists it
- `tests/Feature/FrontDesk/FrontDeskPromptTest.php` - new and updated tests, `promptTest` helpers
- `tests/Feature/FrontDesk/DraftReplacementTest.php` - withdrawn-draft test expects the UTC bracketed time
- `tests/Pest.php`, `tests/Feature/SendRule/JevClassifierTest.php`, `tests/Feature/Icm/ValidatorTest.php`, `tests/Feature/Icm/ActiveConfigTest.php` - folder count 12, three services
- `docs/sprints/sprint-1.md` - walk-through log line (plan 02-39)

## Decisions Made
- history() skips the burst instead of stopping at it (recorded in its docblock). The burst's highest id marks "newer"; an empty burst skips nothing (`?? PHP_INT_MAX`).
- Followed the plan otherwise. No new PROJECT.md decision.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] ActiveConfigTest counted two services**
- **Found during:** Task 3 (full suite after adding furnace-repair.md)
- **Issue:** `tests/Feature/Icm/ActiveConfigTest.php` 'returns a service with its price as an integer...' asserts `services()` has 2 entries; the new file makes 3. The plan listed six files and did not name this one.
- **Fix:** changed the count to 3.
- **Files modified:** tests/Feature/Icm/ActiveConfigTest.php
- **Verification:** `php artisan test --compact` 1070 passed.
- **Committed in:** 2dff92c (Task 3 GREEN commit)

**2. [Rule 2 - Missing critical] Empty-burst guard in history()**
- **Found during:** Task 2
- **Issue:** with an empty burst `$burst->max('id')` is null, so `$message->id > null` would skip every customer text.
- **Fix:** `$burst->max('id') ?? PHP_INT_MAX`. RunFrontDeskAgent already rejects an empty burst; this keeps history() safe on its own.
- **Files modified:** app/Ai/FrontDeskPrompt.php
- **Committed in:** f425e91

---

**Total deviations:** 2 auto-fixed (1 blocking, 1 missing critical)
**Impact on plan:** Both small and necessary. No scope creep.

## Issues Encountered
None.

## Known Stubs

None.

## Threat Flags

None. T-02-92 (booking restated) is mitigated by the RULES bullet as planned (residual: model compliance, not a code guarantee). T-02-93 is mitigated: `promptText()` is untouched and 'joins a burst into one prompt in arrival order' passes unchanged. T-02-94: the new service file is fictional and money is 8900 integer cents. profile.md, faq.md and rules/triage.md were not edited.

## Human checks carried to UAT

Ian's standing decision for this run: no live hand check pauses execution. Task 3's human-check was not run by the executor and is recorded in `.planning/WINDOWS.md` (entry 9, unrun-verify). Carried to end-of-phase UAT:

**Live run with keys (G-02-2.2, G-02-2.4):** with keys in `.env`, run `php artisan icm:sync` (the new service makes a new version), then `php artisan queue:listen`.
- (a) From /dev/chat as Dana (+12025550123, whose seeded thread has "Tomorrow at 9 works." from an earlier day), text "How much for a new unit?". Expect the held draft not to say the visit is tomorrow, on the list or booked.
- (b) From a new number, text "My furnace won't turn on." Expect an intake question from the furnace repair service (`gate:export` shows label `intake_question`; the agent's service is `furnace-repair`), not a draft held as "other".

## Verification
- `php artisan test --compact tests/Feature/FrontDesk/FrontDeskPromptTest.php tests/Feature/FrontDesk/DraftReplacementTest.php tests/Feature/FrontDesk/FrontDeskAgentTest.php tests/Feature/FrontDesk/AgentFallbackTest.php`: 49 passed.
- `php artisan test --compact tests/Feature/FrontDesk/FrontDeskPromptTest.php tests/Feature/FrontDesk/DraftReplacementTest.php tests/Feature/FrontDesk/IntakeAnswersTest.php`: 45 passed.
- `php artisan test --compact tests/Feature/FrontDesk/FrontDeskPromptTest.php tests/Feature/SendRule/JevClassifierTest.php tests/Feature/Icm`: 218 passed.
- `php artisan test --compact` (full suite, SQLite): 1070 passed, 4433 assertions.
- The Postgres leg from 02-VALIDATION.md was not run here; nothing in this plan is driver specific (left for CI).
- Acceptance criteria: FrontDeskPrompt.php contains `## Right now`, `displayTimezone()` and `happenedAt()`; the `90 degrees` grep prints 0; the two RULES phrases are present; furnace-repair.md contains `slug: furnace-repair`, `price_cents: 8900`, `key: symptom`; CONTEXT.md lists `furnace-repair.md`; `exampleFolderFileCount()` returns 12; `git log --grep='(02-39)'` on profile.md, faq.md and rules/triage.md prints nothing; each `test(02-39)` commit precedes its fix/feat commit.

## Next Phase Readiness
- G-02-2.2 and G-02-2.4 are closed in code. The live check is the only open item and sits in WINDOWS.md.

## Self-Check: PASSED

All files exist, commits e88513a, 7be157d, 199b387, f425e91, c825a45 and 2dff92c are on gsd/phase-02-text-front-desk, and the full suite is green.

---
*Phase: 02-text-front-desk*
*Completed: 2026-10-13*
