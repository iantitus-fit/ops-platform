---
phase: 09-owner-intelligence
plan: 01
subsystem: testing
tags: [gate-export, labeled-data, send-rule, tdd, pest, booking, visit-line]

requires:
  - phase: 02.1-faster-answers
    provides: MessageKind::assembledByCode() and meta.parts on code-assembled texts
  - phase: 08-schedule-and-booking
    provides: the visit line on a held draft (08-25) and VisitAskedAbout::VISIT_CHANGED
provides:
  - gate:export with one meaning per field (final_text only for a text on the customer's phone, original_text always the model's own words)
  - the six code-assembled kinds and the answered resolution in the labeled data, with kind, has_model_words, status and answered_by_message_id
  - a --without-text export that carries no words, customer_reading.error included
  - a visit-line reason ("the line changed after it was shown") that is true in every case
affects: [phase-10-threshold-calibration, owner-intelligence, customer-communication]

actuals:
  tokens: 9600
  tasks: 3
  commits: 6

tech-stack:
  added: []
  patterns:
    - "GateExport::hasModelWords() mirrors OutboundSender::partsOf(); the test pins the table so the two cannot drift"
    - "A second fixture helper (gateExportCodeAssembled) so the five-line fixture feeding toHaveCount(5) stays untouched"

key-files:
  created:
    - .planning/phases/09-owner-intelligence/deferred-items.md
  modified:
    - app/Console/Commands/GateExport.php
    - tests/Feature/FrontDesk/GateExportTest.php
    - app/Rooms/ScheduleAndBooking/VisitAskedAbout.php
    - app/Actions/SendOwnerReply.php
    - tests/Feature/Booking/ApprovedDraftVisitLineTest.php
    - docs/BLUEPRINT.md

key-decisions:
  - "final_text goes through MessageStatus::onTheCustomersPhone(), so a queued or failed text is never read as sent (D-16, 02 WR-09)"
  - "original_text is read only from gate.inputs.agent_reply; blank or absent is null, with no fallback to body or original_body (D-17)"
  - "withoutText() swaps customer_reading.error for its length and leaves a fixed verdict with no inputs key (D-20; flagged for Ian at plan review)"
  - "A row with no meta.parts is read for model words the way OutboundSender::partsOf() reads it, so an old code_answer with no parts reports has_model_words true (Assumption A11)"

patterns-established:
  - "Export fields that depend on a send rule reuse the rule's own enum helper rather than a local status list"

requirements-completed: [GATE-05]

coverage:
  - id: D1
    description: "gate:export reports final_text only for a text that reached the customer and names each line's status"
    requirement: GATE-05
    verification:
      - kind: unit
        ref: "tests/Feature/FrontDesk/GateExportTest.php#exports final text only for a text that reached the customer (D-16)"
        status: pass
      - kind: unit
        ref: "tests/Feature/FrontDesk/GateExportTest.php#carries each line's status (D-16)"
        status: pass
    human_judgment: false
  - id: D2
    description: "original_text is always the model's own words from the stored verdict, null when the model wrote none"
    requirement: GATE-05
    verification:
      - kind: unit
        ref: "tests/Feature/FrontDesk/GateExportTest.php#gives original_text one meaning when body, original_body and agent_reply all differ (D-17)"
        status: pass
      - kind: unit
        ref: "tests/Feature/FrontDesk/GateExportTest.php#has no original text when the model wrote nothing (D-17)"
        status: pass
    human_judgment: false
  - id: D3
    description: "The export covers agent_reply plus the six code-assembled kinds with kind and has_model_words, leaves holding, handoff, life-safety and booking texts out, and prints in message id order"
    requirement: GATE-05
    verification:
      - kind: unit
        ref: "tests/Feature/FrontDesk/GateExportTest.php#exports each code-assembled kind with its kind and whether model words were in (D-18)"
        status: pass
      - kind: unit
        ref: "tests/Feature/FrontDesk/GateExportTest.php#reads model words the way the sender does (D-18)"
        status: pass
      - kind: unit
        ref: "tests/Feature/FrontDesk/GateExportTest.php#prints lines in message id order whatever their kind (GATE-05)"
        status: pass
    human_judgment: false
  - id: D4
    description: "An answered draft names the code_answer that answered it, both lines kept"
    requirement: GATE-05
    verification:
      - kind: unit
        ref: "tests/Feature/FrontDesk/GateExportTest.php#names the text that answered a draft and keeps both lines (D-19)"
        status: pass
    human_judgment: false
  - id: D5
    description: "--without-text carries no customer or reply words in an old field or a new one"
    requirement: GATE-05
    verification:
      - kind: unit
        ref: "tests/Feature/FrontDesk/GateExportTest.php#keeps every word out with --without-text, new kinds included (D-20)"
        status: pass
    human_judgment: false
  - id: D6
    description: "The owner's visit-line notice and stored reason read 'the line changed after it was shown' for a moved visit, another visit and edited line words"
    verification:
      - kind: unit
        ref: "tests/Feature/Booking/ApprovedDraftVisitLineTest.php#sends the draft without the visit line when the line changed after it was shown (WR-09, 08 IN-13)"
        status: pass
    human_judgment: false
  - id: D7
    description: "The CI Postgres leg passes for GateExportTest (whereIn on kind, JSON meta reads)"
    verification: []
    human_judgment: true
    rationale: "Postgres is not run locally; confirmed on CI after Ian pushes the phase branch (WINDOWS.md entry 80)"

duration: 15min
completed: 2026-11-16
status: complete
plan_head_before: 69185287436c3d8fb7d0a06996d39c21ab293977
plan_head_after: 0d61e5a
---

# Phase 9 Plan 1: gate:export labeled data and the visit-line reason Summary

**gate:export now reports final_text only for texts on the customer's phone, gives original_text one meaning (the model's own words), exports the six code-assembled kinds and the answered link with kind, has_model_words and status, keeps --without-text word-free, and the owner's visit-line notice reads "the line changed after it was shown" in every case.**

## Performance

- **Duration:** 15 min
- **Started:** 2026-11-16T19:47:41Z
- **Completed:** 2026-11-16T20:03:00Z
- **Tasks:** 3
- **Files modified:** 6 (plus deferred-items.md created)

## Accomplishments

- A queued or failed reply is exported with `final_text` null and its status; only sent and delivered texts read as sent (D-16, 02 WR-09). Every line carries `status`.
- `original_text` is always `gate.inputs.agent_reply`, null when the model wrote nothing; `final_text` stays the full text that went out (D-17).
- The filter now takes `agent_reply` plus the six kinds from `MessageKind::assembledByCode()`; lines carry `kind` and `has_model_words` (read the way `OutboundSender::partsOf()` reads it); a draft closed as answered names its answering text in `answered_by_message_id` (D-18, D-19).
- `--without-text` swaps `customer_reading.error` for its length, leaves a fixed verdict with no `inputs` key, and gives a null length where the text is null (D-20).
- The visit-line left-off reason is one true sentence for a moved visit, another visit and an edited `visit_line`; BLUEPRINT section 7 gets a Built bullet and section 1 line 133 is updated (D-26, 08 IN-13).

## Task Commits

1. **Task 1 (tracer): only what reached the customer reads as sent**
   - RED `3e69fc5` (test) - failed on queued and failed rows exporting their body, and on the missing `status` key
   - GREEN `7c35359` (fix)
2. **Task 2: original_text, the six kinds, answered, --without-text**
   - RED `9ebfb8f` (test) - failed on the planned assertions (missing keys, non-agent_reply kinds absent, body used as original_text)
   - GREEN `ca63b61` (fix)
3. **Task 3: the visit-line reason**
   - RED `3844d36` (test) - the three rows failed on the old words
   - GREEN `0d61e5a` (fix)

**Plan metadata:** the docs commit that carries this SUMMARY, STATE.md, ROADMAP.md and REQUIREMENTS.md.

## TDD Gate Compliance

All three tasks have a `test(09-01)` commit before its `fix(09-01)` commit. No REFACTOR commit: nothing needed cleaning up. The `gsd check tdd-red-evidence` classifier was not run: Pest's JSON summary line is not one of its supported report formats (TAP, JUnit, swift-testing, unittest). The RED assessment was done by hand against the failure messages: each target failed on its planned assertion, none on a syntax, load or zero-test fault. Two guard cases pass on the old code on purpose ("leaves out holding, handoff, life-safety and booking texts" and the unchanged `:211-217` case); they pin an exclusion, not a new behavior.

## Files Created/Modified

- `app/Console/Commands/GateExport.php` - the kind filter, `final_text`, `original_text`, four appended keys, `hasModelWords()`, null-safe `--without-text`, docblocks
- `tests/Feature/FrontDesk/GateExportTest.php` - `gateExportFixedVerdict()` and `gateExportCodeAssembled()` helpers, D-16 to D-20 cases, the 18-key list, the own line's `original_text`
- `app/Rooms/ScheduleAndBooking/VisitAskedAbout.php` - `VISIT_CHANGED` words and docblock
- `app/Actions/SendOwnerReply.php` - `sendDraftAskingForVisitLine()` docblock
- `tests/Feature/Booking/ApprovedDraftVisitLineTest.php` - renamed test, new words, the "words edited" row
- `docs/BLUEPRINT.md` - section 7 Built bullet, line 133 reason and sources
- `.planning/phases/09-owner-intelligence/deferred-items.md` - the five local browser-test failures

## Decisions Made

- `hasModelWords()` copies the rule of `OutboundSender::partsOf()` instead of calling it (it is private, and the plan says not to change OutboundSender); the test table pins them together.
- The "words edited" test row replaces only the `visit_line:` line of the booking file, not every "To change it, reply to this text." in it, so the confirmation and reminder lines stay valid and the case isolates the visit line.
- `GateExport` never names the booking text kind by case or quoted value (08 IN-02); `BookingTextKindTest` passes.

## Deviations from Plan

None - plan executed exactly as written.

Minor execution notes, not deviations: the acceptance sentence "Holding, handoff and life-safety texts are not decisions and are left out." is kept as one line in the docblock so the plan's grep matches. The plan's Task 3 verify command passes a file and its directory to Pest together, which Pest refuses (`TestCaseAlreadyInUse`); the file and the directory were run in separate calls with the same coverage.

## Issues Encountered

- Five Pest browser tests in `tests/Feature/Booking/BookingPageBrowserTest.php` fail on this machine (target size 21 px, footer hover border `rgb(0, 0, 0)`). `npm run build` did not change it, and no file in this plan touches the booking page, its views or CSS. Not fixed (scope boundary); logged in `deferred-items.md` and as WINDOWS.md entry 81.
- Full suite on SQLite: 3238 tests, 3233 pass, the five failures above. Every other test is green, including all of `tests/Feature/FrontDesk`, `tests/Feature/Booking` apart from those five, and `BookingTextKindTest`.

## Unrun Human Checks

- Postgres: after Ian pushes the phase branch, the CI pgsql leg should be green for `GateExportTest` (whereIn on `kind`, JSON `meta` reads). Not run locally. Recorded as WINDOWS.md entry 80 (unrun-verify) for the end-of-phase UAT.
- The five browser tests above are to be confirmed on CI (entry 81).

## Known Stubs

None.

## Threat Flags

None. The export only reads; D-26 changes a reason string on the owner's own send through the existing OutboundSender chokepoint. T-09-01 to T-09-04 are mitigated and each has a test.

## Next Phase Readiness

- Plans 09-02 to 09-06 do not depend on this plan's files.
- Phase 10's threshold calibration can read the labeled data with one meaning per field; exports made before this change are not comparable on `original_text` (D-17, rated costly).
- Walk-through log: no new Laravel concept appeared beyond `whereIn` on a column and a Pest dataset closure, both already used in the codebase.

## Self-Check: PASSED

- Files exist: GateExport.php, GateExportTest.php, VisitAskedAbout.php, SendOwnerReply.php, ApprovedDraftVisitLineTest.php, BLUEPRINT.md, deferred-items.md.
- Commits present: 3e69fc5, 7c35359, 9ebfb8f, ca63b61, 3844d36, 0d61e5a (6 measured from the plan ledger).

---
*Phase: 09-owner-intelligence*
*Completed: 2026-11-16*
