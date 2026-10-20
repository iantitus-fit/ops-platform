---
phase: 02-text-front-desk
plan: 03
subsystem: send-rule
tags: [code-checks, hard-veto, icm, validator, set-replies, pest]

requires:
  - phase: 02-text-front-desk
    provides: Validator cross-file checks, agent file type, exampleFolderFileCount() helper (plans 02-01, 02-02)
provides:
  - "App\\SendRule\\CodeChecks::findings() and describe(): price, discount, promise and never_say hard veto, word lists as PHP constants"
  - "File type set_replies (rules/replies.md): owner_name, optional utility_phone, holding_reply, handoff_reply"
  - "Validator::repliesErrors(): fill-in check, code checks with the voice file's never_say, utility_phone check, all at sync"
  - "ActiveConfig::replies() reading the compiled set_replies singleton"
  - "Example folder rules/replies.md (10 typed files)"
affects: [02-04 send vocabulary, 02-05 business clock, 02-21 safety message, send rule, Business Folder page]

actuals:
  tokens: 7000
  tasks: 1
  commits: 2
plan_head_before: 03ebdad2abc7f673222d8912b0ed5602fc34d09d
plan_head_after: 8878cc38be171793b8e31ecc0752a30b9cb95157

tech-stack:
  added: []
  patterns:
    - "Phrase patterns are built from the word lists, not the text: the original text is matched with /iu, so the reported match is exactly what was written and curly and straight apostrophes match alike"
    - "Cross-file validator check that reads a sibling file (voice) from the candidate folder, never ActiveConfig"
    - "Static, stateless check class in App\\SendRule shared by the sync and (later) the send path"

key-files:
  created:
    - app/SendRule/CodeChecks.php
    - examples/example-hvac/rules/replies.md
    - tests/Unit/SendRule/CodeChecksTest.php
    - tests/Feature/Icm/RepliesValidationTest.php
  modified:
    - app/Icm/Loader.php
    - app/Icm/Validator.php
    - app/Icm/ActiveConfig.php
    - tests/Pest.php
    - tests/Feature/Icm/ValidatorTest.php
    - examples/example-hvac/rules/CONTEXT.md
    - docs/SPEC.md
    - docs/sprints/sprint-1.md

key-decisions:
  - "CodeChecks returns the earliest match in the text for each rule, as written, so the owner sees their own wording"
  - "A number counts as a price near a price word only when it is a plain number (digits, commas, one decimal) within three words; phone numbers, dates and times never are"
  - "A fill-in error and a code-check finding on the same field are both reported (one each); the code check runs with every {token} removed"
  - "A blank utility_phone is treated as not given"

patterns-established:
  - "Word lists for the hard veto are public constants on CodeChecks, never folder content"
  - "Adding a typed file to the example folder changes only exampleFolderFileCount() and the one dataset row that lists the types"

requirements-completed: [GATE-01, GATE-02]

coverage:
  - id: D1
    description: "CodeChecks finds prices, discounts, promises and never_say phrases, ignores ordinary scheduling text, and handles case, multibyte and curly apostrophes"
    requirement: GATE-02
    verification:
      - kind: unit
        ref: "tests/Unit/SendRule/CodeChecksTest.php"
        status: pass
    human_judgment: false
  - id: D2
    description: "An owner-written fixed reply that mentions a price, offers a discount, makes a promise, uses a never_say phrase or an unknown fill-in is refused at sync in plain words; a clean one goes live"
    requirement: GATE-01
    verification:
      - kind: integration
        ref: "tests/Feature/Icm/RepliesValidationTest.php"
        status: pass
    human_judgment: false
  - id: D3
    description: "The starting word lists are sensible for a real owner (they will hold some clear messages, which costs a tap and never a bad send)"
    verification: []
    human_judgment: true
    rationale: "Whether the starting price words, discount phrases and promise phrases are the right list is a judgment about real customer texts that no test asserts"

duration: 14min
completed: 2026-10-12
status: complete
---

# Phase 2 Plan 03: Hard Veto Code Checks and Owner-Written Fixed Replies Summary

**`App\SendRule\CodeChecks` hard veto (price, discount, promise, never_say) plus the `set_replies` file type, so a fixed reply that would send any of them without the owner is refused at sync**

## Performance

- **Duration:** 14 min
- **Started:** 2026-10-12T01:26:00Z (approximate)
- **Completed:** 2026-10-12T01:40:24Z
- **Tasks:** 1 (tracer, RED then GREEN)
- **Files modified:** 12

## Accomplishments

- `CodeChecks::findings()` reports `price`, `discount`, `promise`, `never_say` in that order, each at most once with its earliest match as written. Word lists are public constants. Matching is case-insensitive, multibyte-safe, whole-word (`compromise` is not `promise`) and treats curly and straight apostrophes alike. An empty never_say list vetoes nothing, and the other checks still run.
- `rules/replies.md` (`type: set_replies`) is a typed file: Loader, Validator rules, compiled index and `ActiveConfig::replies()`. The example folder now holds 10 typed files.
- The sync refuses a reply with an unknown fill-in, a price, discount, promise or never_say phrase (the never_say list comes from the voice file in the same candidate folder), and a `utility_phone` that is not a phone number, each in the plain sentences the plan specified.

## Task Commits

1. **Task 1 RED: failing code checks and fixed replies tests** - `5635e98` (test)
2. **Task 1 GREEN: code checks and owner-written fixed replies, checked at sync** - `8878cc3` (feat)

**Plan metadata:** committed after this file (docs: complete plan)

## TDD Gate Compliance

The task was a tracer, not `tdd="true"`, so the MVP+TDD gate did not apply, but the plan's RED then GREEN order was followed.

- **RED:** `CodeChecksTest` and `RepliesValidationTest` run before any code. 42 errors and 1 failure of 43 tests: `Class "App\SendRule\CodeChecks" not found`, `Call to undefined method ActiveConfig::replies()`, `rules/replies.md` does not exist, and "Expected status code 1 but received 0" for the missing-file case (the sync passed because the type was not required yet). Semantic assessment: every failure is the planned behavior being absent, not a syntax, fixture or discovery fault. The `gsd_run check tdd-red-evidence` classifier was not run: Pest's JSON summary is not one of its supported report formats.
- **GREEN:** the same files, unchanged, pass: 142 tests in `tests/Unit/SendRule/CodeChecksTest.php` and `tests/Feature/Icm`; full suite 358 passed.
- **REFACTOR:** none needed.

## Files Created/Modified

- `app/SendRule/CodeChecks.php` - the hard veto, shared by the sync and the later send rule
- `app/Icm/Validator.php` - `set_replies` rules, `SINGLETONS` entry, `repliesErrors()`
- `app/Icm/Loader.php` - `set_replies` added to `TYPED`
- `app/Icm/ActiveConfig.php` - `replies()`
- `examples/example-hvac/rules/replies.md` - Mike's holding and handoff texts, fictional utility phone
- `examples/example-hvac/rules/CONTEXT.md` - lists the new file
- `tests/Unit/SendRule/CodeChecksTest.php`, `tests/Feature/Icm/RepliesValidationTest.php` - the tests
- `tests/Pest.php` - `exampleFolderFileCount()` 9 to 10
- `tests/Feature/Icm/ValidatorTest.php` - the "type must be one of" row now ends with `set_replies`
- `docs/SPEC.md` - folder tree and file-type table gain `set_replies`
- `docs/sprints/sprint-1.md` - two walk-through log lines (static methods and word-boundary patterns; cross-file validation)

## Decisions Made

See `key-decisions` above. None changed a recorded project decision.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Discount phrases alone could not find "$50 off"**
- **Found during:** Task 1 (GREEN, reading the behavior list)
- **Issue:** the plan's behavior says `$50 off, guaranteed` returns price, discount, promise, never_say, but none of the listed `DISCOUNT_PHRASES` match an amount followed by "off"
- **Fix:** added a private `DISCOUNT_AMOUNT_PATTERNS` (a dollar amount or "N dollars/bucks" followed by "off"); the public `DISCOUNT_PHRASES` list is exactly the plan's list
- **Files modified:** app/SendRule/CodeChecks.php
- **Verification:** the "all four rules come back in order" row passes
- **Committed in:** 8878cc3

**2. [Rule 3 - Blocking] Existing test listed the old set of types**
- **Found during:** Task 1 (adding `set_replies` to `Loader::TYPED`)
- **Issue:** `ValidatorTest` expects the exact "type must be one of ..." sentence, which now ends with `set_replies`
- **Fix:** updated that one dataset row
- **Files modified:** tests/Feature/Icm/ValidatorTest.php
- **Committed in:** 8878cc3

**3. [Rule 2 - Missing critical] Docs and router file for the new type**
- **Issue:** the project map says `docs/SPEC.md` is the architecture and the CLAUDE.md rule requires a walk-through log line for new concepts; `rules/CONTEXT.md` is the owner's index of the rules folder
- **Fix:** added the SPEC row and tree line, two sprint-1 walk-through lines, and a `rules/CONTEXT.md` line
- **Files modified:** docs/SPEC.md, docs/sprints/sprint-1.md, examples/example-hvac/rules/CONTEXT.md
- **Committed in:** 8878cc3

---

**Total deviations:** 3 auto-fixed (1 bug, 1 blocking, 1 missing critical)
**Impact on plan:** all necessary; no scope creep.

## Issues Encountered

None.

## Known Stubs

None.

## Threat Flags

None. T-02-08 (fixed replies sent without review) is mitigated as planned: `repliesErrors` runs `CodeChecks` with the voice file's never_say at sync, and fill-ins are limited to three known names.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- `CodeChecks::findings()`, `describe()` and the three constants are ready for plans 02-04 and 02-05 and the send rule.
- `ActiveConfig::replies()` is ready for the holding and handoff send paths. The sign-off and AI disclosure are added by the send path (D-50), not stored in this file.
- The Business Folder page does not yet show the word lists (the plan's docblock says it will); that belongs to a later page plan.

---
*Phase: 02-text-front-desk*
*Completed: 2026-10-12*

## Self-Check: PASSED

- FOUND: app/SendRule/CodeChecks.php, examples/example-hvac/rules/replies.md, tests/Unit/SendRule/CodeChecksTest.php, tests/Feature/Icm/RepliesValidationTest.php
- FOUND commits: 5635e98 (RED), 8878cc3 (GREEN), both ancestors of HEAD
- Acceptance criteria re-run: constants and `findings(` in CodeChecks; `'set_replies' => 'rules/replies.md'` and `repliesErrors` in Validator; `type: set_replies` and `{next_open}` in replies.md; `Are you free Tuesday?` and a `compromise` row in CodeChecksTest; both automated commands exit 0 (full suite 358 passed)
