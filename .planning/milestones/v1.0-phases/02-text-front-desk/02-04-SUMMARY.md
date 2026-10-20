---
phase: 02-text-front-desk
plan: 04
subsystem: send-rule
tags: [icm, validator, send-rules, owner-alert, enums, filament, pest]

requires:
  - phase: 02-text-front-desk
    provides: CodeChecks word lists and findings(), Validator cross-file checks, Business Folder Front desk section (plans 02-02, 02-03)
provides:
  - "App\\Enums\\ReplyLabel: the fixed action vocabulary with sendOnOwnAllowed(), neverOnItsOwn() (the price/promise/discount/complaint floor) and values()"
  - "App\\Enums\\AlertReason: owner alert events with values() and plainWords()"
  - "Validator::sendingRulesErrors(): hard floor on send_on_own, unknown-key and both-lists sentences, structured owner_alert checks (D-08, D-09)"
  - "Validator::voiceErrors(): ai_disclosure and sign_off code-checked at sync with the file's own never_say (Pitfall 6)"
  - "Example rules/sending.md with draft_for_owner including other and a structured owner_alert"
  - "BusinessFolder::alwaysHeld() and the Always held for you section"
affects: [02-11 owner alerts, 02-14 send path floor, 02-16, 02-17, Business Folder page]

actuals:
  tokens: 8500
  tasks: 1
  commits: 3
plan_head_before: d674aa595bf467f53e274a33a52e0413c5592ad3
plan_head_after: d9fe7fa570ca0629338d47ef7e1513ee63973cd1

tech-stack:
  added: []
  patterns:
    - "A closed vocabulary lives in a backed enum whose static methods hold the rules about it, so the sync and the send path ask the same question"
    - "A nested map inside a typed file gets its own LaravelValidator::make with field names prefixed by the parent key, rendered through PlainWords"
    - "fixedTextError() is the one place that turns a CodeChecks finding into an owner sentence, shared by fixed replies and voice texts"

key-files:
  created:
    - app/Enums/ReplyLabel.php
    - app/Enums/AlertReason.php
    - tests/Feature/Icm/SendingRulesValidationTest.php
  modified:
    - app/Icm/Validator.php
    - app/Filament/Pages/BusinessFolder.php
    - resources/views/filament/pages/business-folder.blade.php
    - examples/example-hvac/rules/sending.md
    - examples/example-hvac/rules/CONTEXT.md
    - tests/Feature/Icm/ValidatorTest.php
    - tests/Feature/Filament/BusinessFolderPageTest.php
    - docs/SPEC.md
    - docs/sprints/sprint-1.md

key-decisions:
  - "send_on_own entries are checked in sendingRulesErrors() rather than with Rule::in, so price, promise, discount and complaint get the hard-floor sentence instead of a generic 'must be one of'"
  - "A label already refused by the floor is not also reported as 'in both lists'; the floor sentence is the more useful one"
  - "Fixed-text sentence is shared between replies and voice texts through fixedTextError(); the sentence says 'a fixed reply' or 'a fixed text'"
  - "alwaysHeld() always returns the three CodeChecks lists and returns an empty Never say list when nothing is live; the section itself is shown only while a version is live"

patterns-established:
  - "Owner-facing fixed vocabularies are enums with a values() helper that feeds Rule::in and PlainWords"

requirements-completed: [GATE-01, GATE-02]

coverage:
  - id: D1
    description: "send_on_own accepts only intake_question, scheduling and faq_answer; price, promise, discount and complaint are refused in plain words; unknown keys and keys in both lists are refused"
    requirement: GATE-01
    verification:
      - kind: integration
        ref: "tests/Feature/Icm/SendingRulesValidationTest.php#rejects send rules and voice texts that would loosen the floor and keeps nothing live"
        status: pass
    human_judgment: false
  - id: D2
    description: "owner_alert is a validated channels, right_away, after_hours group: text only, known events, life_safety always included, next_opening or right_away after hours, no phone number"
    requirement: GATE-01
    verification:
      - kind: integration
        ref: "tests/Feature/Icm/SendingRulesValidationTest.php#puts the structured send rules live with the example folder"
        status: pass
    human_judgment: false
  - id: D3
    description: "voice ai_disclosure and sign_off are refused at sync when they use a never_say phrase, name a price, offer a discount or make a promise"
    requirement: GATE-02
    verification:
      - kind: integration
        ref: "tests/Feature/Icm/SendingRulesValidationTest.php#rejects send rules and voice texts that would loosen the floor and keeps nothing live"
        status: pass
    human_judgment: false
  - id: D4
    description: "The Business Folder page lists what always waits for the owner and escapes the owner's never_say phrases"
    requirement: GATE-02
    verification:
      - kind: integration
        ref: "tests/Feature/Filament/BusinessFolderPageTest.php#lists what always waits for the owner: the checked phrases and the never_say list"
        status: pass
    human_judgment: true
    rationale: "Whether the section reads clearly to a non-technical owner is a judgment no test asserts; the content and escaping are tested."

duration: 15min
completed: 2026-10-12
status: complete
---

# Phase 2 Plan 04: Send Vocabulary and Owner Alert Summary

**Fixed send vocabulary with a code-enforced price/promise/discount/complaint floor (ReplyLabel enum), a validated channels/right_away/after_hours owner_alert, code-checked voice disclosure and sign-off, and an Always held for you section on the Business Folder page**

## Performance

- **Duration:** about 15 min
- **Started:** 2026-10-12T01:33:00Z (approximate)
- **Completed:** 2026-10-12T01:50:00Z
- **Tasks:** 1 (tracer)
- **Files modified:** 12

## Accomplishments

- `ReplyLabel` and `AlertReason` enums define the closed vocabulary; `ReplyLabel::neverOnItsOwn()` is exactly price, promise, discount, complaint.
- `Validator::sendingRulesErrors()` refuses those four in `send_on_own` ("send_on_own cannot include price: anything about a price always waits for you"), unknown keys on either list, and a key in both lists. A folder edit can no longer make price, promise, discount or complaint send on their own.
- `owner_alert` is structured and validated: `channels` only `text`, `right_away` only known events and always including `life_safety`, `after_hours` of `next_opening` or `right_away`. The old one-sentence form is refused with a "must list channels, right_away and after_hours" line. No phone field exists.
- `Validator::voiceErrors()` runs `CodeChecks` on `ai_disclosure` and `sign_off` with the voice file's own `never_say`, sharing the sentence builder (`fixedTextError()`) with the fixed replies.
- Business Folder page shows "Always held for you": price words, discount phrases, promise phrases and the live never_say list, escaped.

## Task Commits

1. **Task 1 RED: failing vocabulary and owner alert tests** - `2b185ae` (test)
2. **Task 1 GREEN: fixed send vocabulary, structured owner alert, checked voice texts** - `27ac1c3` (feat)
3. **Spec table update** - `d9fe7fa` (docs)

**Plan metadata:** recorded in the docs(02-04) completion commit.

## TDD Gate Compliance

- RED (`2b185ae`): 18 failures and 5 errors across the three test files for the planned reasons: the enums did not exist, send_on_own was not checked, owner_alert was unstructured, voice texts were not checked, and `alwaysHeld()` / the section did not exist. Several dataset rows failed at their "search text is in the file" precondition because the example folder still held the old `sending.md`; that is the example-folder change GREEN makes, so the row is still red for the planned reason. Pest output is not a format `gsd check tdd-red-evidence` supports, so the semantic assessment is recorded here instead: target tests executed and failed on the planned behavior, not on syntax or fixture faults.
- GREEN (`27ac1c3`): all 147 tests in the Icm and BusinessFolder files pass, full suite 395/395.
- REFACTOR: none needed (the shared `fixedTextError()` was extracted during GREEN, and the existing replies tests stayed green).

## Files Created/Modified

- `app/Enums/ReplyLabel.php`, `app/Enums/AlertReason.php` - the fixed vocabularies
- `app/Icm/Validator.php` - `sendingRulesErrors()`, `ownerAlertErrors()`, `voiceErrors()`, `fixedTextError()`, tightened `draft_for_owner.*` rule
- `app/Filament/Pages/BusinessFolder.php`, `resources/views/filament/pages/business-folder.blade.php` - `alwaysHeld()` and its section
- `examples/example-hvac/rules/sending.md`, `rules/CONTEXT.md` - structured owner_alert, `other` in draft_for_owner, router text for replies.md
- `tests/Feature/Icm/SendingRulesValidationTest.php` (new), `ValidatorTest.php`, `tests/Feature/Filament/BusinessFolderPageTest.php`
- `docs/SPEC.md`, `docs/sprints/sprint-1.md` - spec table row and two walk-through log lines

## Decisions Made

See key-decisions above. The example `owner_alert` puts every urgent event in `right_away` and leaves `draft_waiting` out so that price, promise and discount drafts made after hours wait for the next opening (D-20); the timing itself is applied in code by plan 02-11.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Missing critical documentation] Updated the SPEC file-type table**
- **Found during:** Task 1 wrap-up
- **Issue:** `docs/SPEC.md` still described `owner_alert` as a bare field and the send lists as free-form, which would now mislead.
- **Fix:** Updated the sending_rules row to name the fixed keys and the owner_alert shape.
- **Files modified:** `docs/SPEC.md`
- **Committed in:** `d9fe7fa`

**2. [Rule 3 - Blocking] Test used array_intersect on enum cases**
- **Found during:** Task 1 GREEN
- **Issue:** `array_intersect` converts to strings and enum cases cannot be.
- **Fix:** Compared with `in_array(..., true)` in the test.
- **Files modified:** `tests/Feature/Icm/SendingRulesValidationTest.php`
- **Committed in:** `27ac1c3`

---

**Total deviations:** 2 auto-fixed (1 missing doc, 1 test bug)
**Impact on plan:** No scope creep. The plan listed neither file change but both follow directly from it.

## Issues Encountered

None.

## Threat Surface

T-02-07 and T-02-58 are mitigated at sync and tested. The send-time half of T-02-07 (floor enforced again in code) remains with plan 02-14, as the plan states. No new network or auth surface was added.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

`ReplyLabel::neverOnItsOwn()` and `AlertReason` are ready for the send path (02-14) and the alert sender (02-11). `ActiveConfig::sendingRules()['owner_alert']` now returns a map with `channels`, `right_away` and `after_hours`, so any code written against the old string must read those keys.

---
*Phase: 02-text-front-desk*
*Completed: 2026-10-12*

## Self-Check: PASSED

- FOUND: app/Enums/ReplyLabel.php, app/Enums/AlertReason.php, tests/Feature/Icm/SendingRulesValidationTest.php
- FOUND commits: 2b185ae, 27ac1c3, d9fe7fa
- Acceptance greps all pass; both automated verify commands pass (147/147 and 395/395).
