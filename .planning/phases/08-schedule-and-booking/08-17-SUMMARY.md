---
phase: 08-schedule-and-booking
plan: 17
subsystem: safety-and-compliance
tags: [booking-link, chokepoint, session, leak-test, send-rule, laravel]

requires:
  - phase: 08-schedule-and-booking
    provides: "08-09 booking link minted and sent through OutboundSender; 08-16 card and D-46"
provides:
  - "App\\Support\\BookingLinkTokens: hide(), namesBookingAddress(), holdsHiddenAddress()"
  - "OutboundSender stores every sent text with booking addresses written /book/[link] and hands the channel the working text"
  - "A failed send's record, the fake carrier's log line, a customer's pasted link, every records/ file and the session's previous URL carry no working token"
  - "The send rule holds a reply whose model words contain /book/ as code:booking_link; OutboundSender refuses any text holding /book/[link]"
  - "BookingLinkLeakTest: real sends, then every table, the business folder, the log and job payloads searched"
affects: [08-18, 08-19, 08-20, phase-8-uat]

actuals:
  tokens: 14600
  tasks: 4
  commits: 8
plan_head_before: 84c0854d5b54fafd9048c42b12b32d4caba23ff7
plan_head_after: eabdff420057901c61a921f063a1180e312100cb
commits: 8

tech-stack:
  added: []
  patterns:
    - "One pure function writes a booking address as /book/[link] wherever a copy is kept; the chokepoint splits the stored copy from the sent text"
    - "Subclass Laravel's StartSession and swap it in with $middleware->web(replace: [...])"

key-files:
  created:
    - app/Support/BookingLinkTokens.php
    - app/Http/Middleware/StartSessionHidingBookingTokens.php
    - tests/Feature/Booking/BookingLinkLeakTest.php
  modified:
    - app/Actions/OutboundSender.php
    - app/Actions/RecordInbound.php
    - app/Channels/FakeTextChannel.php
    - app/Records/RecordsWriter.php
    - app/SendRule/DecideSendOrDraft.php
    - app/SendRule/Verdict.php
    - bootstrap/app.php
    - docs/BLUEPRINT.md
    - docs/sprints/sprint-1.1.md
    - .planning/WINDOWS.md

key-decisions:
  - "hide() keeps the matched '/book/' in its own letter case and replaces only the token, so '/BOOK/<64 hex>' becomes '/BOOK/[link]' and nothing else changes"
  - "A failed send's DomainException keeps the raw gateway exception as its previous only when the hidden message equals the raw one"
  - "The front desk hold stores the six characters '/book/' as the finding's match, never the address, so the stored gate copies no token"

requirements-completed: [BOOK-02, BOOK-03]

duration: 25 min
completed: 2026-11-08
status: complete
---

# Phase 8 Plan 17: Booking links stay out of every stored copy Summary

**Gap D-12 closed going forward: OutboundSender stores each sent text with its booking address as /book/[link] while the customer's phone gets the working link; a failed send's record, the log, a pasted link, every records/ file and the session URL follow, and the front desk can never write a booking link of its own.**

## Performance

- **Duration:** 25 min
- **Started:** 2026-11-08T15:23:34Z
- **Completed:** 2026-11-08T15:48:00Z
- **Tasks:** 4 (Task 1 tracer, Tasks 2 to 4 auto, all tdd)
- **Files modified:** 13 (3 created)

## Accomplishments

- gap_ids D-12 are closed going forward by this plan. Rows and card files written before it are 08-18's.
- A scheduling reply and an owner-approved draft each reach the customer with a working 64-hex link whose hash matches `token_hash` and whose page opens, while the stored row holds `/book/[link]` and no table, file, log record or job payload holds the token.
- The front desk's own words that contain `/book/` are held as `code:booking_link` ("Held: writes its own booking link, which would not open") before the link line is appended; `OutboundSender::HIDDEN_BOOKING_LINK` refuses any text holding `/book/[link]` for every origin, an owner's unedited approval included, and the minted link is withdrawn with the superseded one put back.

## Landing spots and the case that proves each

All cases are in `tests/Feature/Booking/BookingLinkLeakTest.php`.

| Landing spot | Case |
|---|---|
| messages.body, card, open-leads.md, timeline, alerts (agent path) | gives the customer a working link and stores the scheduling reply with it hidden |
| the approved draft row (D-45) | stores an approved scheduling draft with its link hidden |
| every table, the folder (search not vacuous) | lists every table and finds a planted token (activities.summary, base64 cache.value) |
| log and job payloads | leaves no token in the log or in any job payload during a send |
| failed send record, DomainException and its previous chain | keeps the token out of a failed send's record at the chokepoint |
| failed send, agent path, owner alerts | keeps the token out of a failed send's record on the agent path |
| the fake carrier's local log line | writes the fake carrier's local log line with the token hidden |
| a customer's pasted link | stores a customer text that pastes the link back with the link hidden |
| every RecordsWriter file, old rows | writes every records file with the token hidden, even from a row written before the fix |
| the session's previous URL | keeps the token out of the session's previous URL |
| hide() edges | hides a booking address and nothing else (9 cases) |
| namesBookingAddress / holdsHiddenAddress edges | tells a booking address, and the hidden form, in any letter case (16 cases) |

## Front desk hold and chokepoint refusal

- **code:booking_link**, `DecideSendOrDraft::decide()`: proved by 'holds a reply that copies /book/[link] from the thread, and none of it reaches the customer': one draft with `gate_reason` `code:booking_link`, first finding `['rule' => 'booking_link', 'match' => '/book/']`, the plain reason above, `BookingLink::count()` stays 1, exactly one more text to Dana (the holding reply, no `/book/`), no text holds the hidden form.
- **HIDDEN_BOOKING_LINK**, `OutboundSender::whyBodyIsRefused()`: proved by 'refuses an owner's unedited approval of a draft holding /book/[link]...' (draft stays live, second link revoked, first link put back, edited approval sends a working link) and 'refuses a text holding /book/[link] from every sender before any row is written' (Owner, Automated, LifeSafety; no outbound row; the real address from the owner still goes out).

## Task Commits

1. **Task 1 (tracer): customer's text keeps the working link, stored row holds /book/[link]** - `acd910d` (test, RED), `544aea9` (feat)
2. **Task 2: failed send, log, paste, records files, job payloads** - `15a2509` (test, RED), `0a052e5` (feat)
3. **Task 3: front desk hold and chokepoint refusal** - `76dbdc1` (test, RED), `2d4192e` (feat)
4. **Task 4: session previous URL, docs, WINDOWS entries** - `30c77c4` (test, RED), `eabdff4` (feat)

**Plan metadata:** recorded in the docs(08-17) commit that follows this file.

## TDD Gate Compliance

RED evidence was captured for the target test of each task from a real `--log-junit` run and validated with `check tdd-red-evidence` (`RED_EVIDENCE_OK`, `target_test_failed`) before each GREEN. Semantic assessment of each RED:

- Task 1: the scheduling-reply case failed on `bookingLeakPlacesHolding($token)` returning `messages.body` and the card file, the planned assertion. The hide() dataset errored on the missing class, as the plan expects.
- Task 2: the five target cases failed on the failed-send message, the fake log body, the stored paste and the records file holding the token. The log/payload guard passed by design.
- Task 3: the approval case failed on a new text reaching Dana (the unedited approval went out), the sender case on nothing being refused, the copied-reply case on no draft being held. The 16-case address dataset errored on the missing methods, as the plan expects.
- Task 4: the previous URL ended in the token instead of `/book/[link]`.

All RED commits precede their feat commits. No REFACTOR commits.

## Existing tests re-pointed

None. The three sweeps (Booking, FrontDesk, Records, Inbound, Alerts, Channels, SendRule, Unit/Channels, Unit/SendRule, ArchitectureTest, Filament) and the full suite (3045 tests) passed without changing any existing test.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Test expectations hard-coded `http://localhost`**
- **Found during:** Task 1 GREEN
- **Issue:** the committed RED cases for hide() expected `http://localhost/book/[link]`, but `route()` in the suite yields `http://localhost:8000/...`.
- **Fix:** expectations are built with `str_replace($token, '[link]', route(...))`.
- **Files modified:** tests/Feature/Booking/BookingLinkLeakTest.php
- **Committed in:** 544aea9

**2. [Rule 1 - Bug] The planted-token case activated the folder twice**
- **Found during:** Task 1 RED
- **Issue:** `frontDeskCustomerTexts()` already activates the example folder, so an extra `activateExampleFolder()` failed on `Unchanged`.
- **Fix:** removed the extra call.
- **Committed in:** acd910d

**3. [Rule 1 - Bug] Records-file case needed a timeline entry and a files-only search**
- **Found during:** Task 2 RED
- **Issue:** the card is drawn from activities, so a message row alone never reached it; and the all-tables search flagged the test's own planted row in `messages.body`.
- **Fix:** the case creates a ReplySent activity for the message and uses a new files-only helper `bookingLeakFilesHolding()`.
- **Committed in:** 15a2509

**4. [Plan shape] Failed send case split in two**
- The plan's "part one" and "part two" of the failed-send case are two `it()` blocks (the chokepoint, and the agent path), so each runs from a clean app. A small helper `bookingLeakRunSchedulingReply()` runs the steps without reading the token.

---

**Total deviations:** 3 auto-fixed (test-only bugs), 1 shape change. **Impact on plan:** none on behaviour; every planned assertion is kept.

## Issues Encountered

None. The Pest `--log-junit` report is read by the GSD RED classifier as JUnit, so no reporter rerun was needed.

## Authentication Gates

None.

## Known Stubs

None. Stub scan of the files created or modified found no hard-coded empty values flowing to a UI, no placeholder text and no unwired components.

## Threat Flags

None. No new network endpoint, auth path, file access or schema change; the session middleware replaces Laravel's own in the web group and changes only the stored address.

## Hand checks left unrun (WINDOWS.md, never a pause)

- **Entry 74** (`tests/Feature/Booking/BookingLinkLeakTest.php`): after Ian pushes the phase branch, the CI pgsql leg is green for BookingLinkLeakTest, in particular the table listing through `pg_class` with schema-qualified names, and the `meta->booking_link_id` read. Not run locally: no Postgres here.
- **Entry 75** (`app/Support/BookingLinkTokens.php`): Phase 8 UAT notes. Dev chat and held-draft checks now show the link as `.../book/[link]` in the thread, which is expected; booking page walks take a working link from the terminal; asking Dev chat for the link again must give a fresh link line or a held draft, never `/book/[link]`.

## Walk-through log

Three lines marked `(plan 08-17)` were added to `docs/sprints/sprint-1.1.md`: listing every table with `Schema::getTables()`, swapping Laravel's middleware with `web(replace:)`, and listening to the log and queue in a test. BLUEPRINT section 10 has one Built bullet (Affects: safety and compliance, customer communication, team).

## Next Phase Readiness

- 08-18 can revoke links and clean rows and card files written before this plan, and add the text provider's log and the host's request log to the attorney list and open items (T-08-75, T-08-96).
- Nothing blocks 08-18.

## Self-Check: PASSED

- Files exist: BookingLinkTokens.php, StartSessionHidingBookingTokens.php, BookingLinkLeakTest.php, this SUMMARY.
- Commits exist on the branch: acd910d, 544aea9, 15a2509, 0a052e5, 76dbdc1, 2d4192e, 30c77c4, eabdff4.
- Every task's acceptance criteria were re-run and pass; the full suite is green (3045 tests).

---
*Phase: 08-schedule-and-booking*
*Completed: 2026-11-08*
