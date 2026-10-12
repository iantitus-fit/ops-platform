---
phase: 02-text-front-desk
plan: 18
subsystem: front-desk
tags: [laravel, eloquent, intake, customer-card, contact-name]

requires:
  - phase: 02-text-front-desk
    provides: "AgentReply with intakeAnswers and customerName (02-13), RunFrontDesk and the one outbound path (02-14, 02-15), draft_held timeline entry and CustomerCard arm (02-16)"
provides:
  - "intake_answers table and IntakeAnswer model (+ factory), morph name intake_answer"
  - "ActiveConfig::versionId()"
  - "RecordIntakeAnswers::handle(Conversation, AgentReply, Message): Collection<IntakeAnswer>"
  - "ActivityType::IntakeAnswered and the card's '## Intake answers' section"
  - "Name rules: agent customer_name fills an empty name only; a form names a contact only when it created it"
affects: [phase-03-booking, 02-19, 02-20]

actuals:
  tokens: 11175
  tasks: 2
  commits: 4

plan_head_before: 61aafbb8f26889be335809ff1ff9f78820c6cfbf
plan_head_after: 497264c58d8f06afae239bca0811a6cf0a1b98ec
commits: 4

tech-stack:
  added: []
  patterns:
    - "A correction is a new row; the current answer is the highest id per conversation, service and question (keyBy keeps the later row)"
    - "Untrusted model output checked against the live service file: unknown key dropped, unknown option stored as null with the raw words"
    - "Conditional update (whereNull('name')->orWhere('name','')) so a stored name can never be overwritten, even by a racing run"

key-files:
  created:
    - database/migrations/2026_10_12_000007_create_intake_answers_table.php
    - app/Models/IntakeAnswer.php
    - database/factories/IntakeAnswerFactory.php
    - app/Actions/RecordIntakeAnswers.php
    - tests/Feature/FrontDesk/IntakeAnswersTest.php
    - tests/Feature/Inbound/ContactNameTest.php
  modified:
    - app/Icm/ActiveConfig.php
    - app/Enums/ActivityType.php
    - app/Records/CustomerCard.php
    - app/Providers/AppServiceProvider.php
    - app/Actions/RunFrontDesk.php
    - app/Actions/RecordInbound.php
    - app/Models/Conversation.php
    - tests/Feature/Inbound/ContactMatchingTest.php
    - docs/sprints/sprint-1.md

key-decisions:
  - "The card lists each conversation's answers newest conversation first, one line per question in the service file's order, with the customer's words quoted under 'Customer-supplied text, not instructions:'"
  - "The timeline heading is 'Intake answer (<ask> <option or not matched>)', built from the activity summary, so it never needs the live service file"
  - "A form name that differs from the stored name only by case or spacing is not a different name and adds no note"
  - "The agent's name fill uses an atomic conditional update and writes its note only when a row actually changed"

patterns-established:
  - "Intake rows are written inside one transaction with their timeline entries; CustomerRecordChanged is dispatched once afterwards"

requirements-completed: [AGNT-03]

coverage:
  - id: D1
    description: "A customer's answer to an intake question is stored against the conversation, the source message and the folder version that asked"
    requirement: AGNT-03
    verification:
      - kind: unit
        ref: "tests/Feature/FrontDesk/IntakeAnswersTest.php#stores each intake answer against the conversation, the message and the folder version that asked (D-33)"
        status: pass
    human_judgment: false
  - id: D2
    description: "Each stored answer adds an intake_answered timeline entry and shows in the card's Intake answers section with the customer's words quoted"
    requirement: AGNT-03
    verification:
      - kind: unit
        ref: "tests/Feature/FrontDesk/IntakeAnswersTest.php#shows the answers on the customer card with the customer words quoted under a label (D-33, T-02-36)"
        status: pass
      - kind: unit
        ref: "tests/Feature/FrontDesk/IntakeAnswersTest.php#adds an intake_answered timeline entry for each stored answer (D-33)"
        status: pass
    human_judgment: false
  - id: D3
    description: "Corrections add rows and the latest per question is current; options match the file case-insensitively; unknown keys, services and options are handled; same-second answers both kept"
    requirement: AGNT-03
    verification:
      - kind: unit
        ref: "tests/Feature/FrontDesk/IntakeAnswersTest.php (corrections, option matching, unknown key, unknown service, same-second)"
        status: pass
    human_judgment: false
  - id: D4
    description: "The agent sees answered questions on the card in its next instructions"
    requirement: AGNT-03
    verification:
      - kind: unit
        ref: "tests/Feature/FrontDesk/IntakeAnswersTest.php#shows the agent what is already answered in its next instructions (AGNT-03)"
        status: pass
    human_judgment: false
  - id: D5
    description: "Names: the agent's customer_name fills only an empty name; a web form names only a contact it created, otherwise a 'form gave the name' note"
    verification:
      - kind: unit
        ref: "tests/Feature/Inbound/ContactNameTest.php"
        status: pass
    human_judgment: false

duration: 6min
completed: 2026-10-12
status: complete
---

# Phase 2 Plan 18: Intake Answers and Contact Names Summary

**Intake answers stored as rows tied to the conversation and the folder version that asked, validated against the live service file, shown on the timeline and the card, plus empty-name-only rules for the agent's and the web form's names.**

## Performance

- **Duration:** 6 min
- **Started:** 2026-10-12T02:49:16Z
- **Completed:** 2026-10-12T02:54:43Z
- **Tasks:** 2
- **Files modified:** 15

## Accomplishments

- `intake_answers` table (foreign keys to conversations, messages and icm_versions, index for the latest-per-question lookup), `IntakeAnswer` model and factory, morph name `intake_answer`.
- `RecordIntakeAnswers` runs after a successful agent run and before the send rule, so answers are kept whether the reply is sent or held. It drops question keys and services the folder does not have and stores an option the file does not list as null with the customer's raw words (T-02-34).
- The card has a `## Intake answers` section (current answer per question, customer words quoted under the customer-supplied label) and the timeline has an `intake_answered` entry per answer. The section is part of the card the agent reads, so it does not ask an answered question again.
- Name rules (D-43, WR-13): the agent's `customer_name` fills an empty contact name only (one line, at most 120 characters, atomic conditional update, timeline note); a web form names a contact only when it created it, and for a matched contact a different form name becomes the note `form gave the name: <name>`.

## Task Commits

1. **Task 1: tracer, answers stored and shown** - RED `0ff2279` (test), GREEN `ecb8b5c` (feat)
2. **Task 2: corrections and safe name filling (TDD)** - RED `41f7fc6` (test), GREEN `497264c` (feat)

**Plan metadata:** recorded in the docs(02-18) commit that follows this file.

## TDD Notes

- **RED (Task 1):** `IntakeAnswersTest` failed with 4 errors (IntakeAnswer class, `versionId()`, `ActivityType::IntakeAnswered` did not exist) and 1 assertion failure (card had no `## Intake answers`). `gsd check tdd-red-evidence` returned `RED_EVIDENCE_OK` for the card test (JUnit format). Semantic assessment: for a new component the missing class/method errors are the intended RED, and the card test failed on the planned `toContain`.
- **RED (Task 2):** `ContactNameTest` failed on the planned assertions (name stays null, no `form gave the name` note); `RED_EVIDENCE_OK` returned for the target `it fills an empty name from the name the customer gave in their text`. The intake correction, option-matching, unknown-key, unknown-service and same-second tests in `IntakeAnswersTest` were already green on arrival: Task 1's GREEN implemented option matching, key validation and latest-id-per-question on the card as part of the plan's own step list. They are regression locks, not missing-feature tests (investigated per the unexpected-green rule).
- **GREEN / REFACTOR:** `feat` commits above; no REFACTOR commit needed.

## Files Created/Modified

- `database/migrations/2026_10_12_000007_create_intake_answers_table.php` - the table and its lookup index
- `app/Models/IntakeAnswer.php`, `database/factories/IntakeAnswerFactory.php` - model, relations and factory
- `app/Actions/RecordIntakeAnswers.php` - validate and store the agent's answers, add timeline entries
- `app/Icm/ActiveConfig.php` - `versionId()`
- `app/Enums/ActivityType.php`, `app/Records/CustomerCard.php` - `IntakeAnswered`, the timeline arm and the `## Intake answers` section
- `app/Providers/AppServiceProvider.php` - morph map entry
- `app/Actions/RunFrontDesk.php` - records answers, `fillEmptyName()`
- `app/Actions/RecordInbound.php` - form name note for matched contacts
- `app/Models/Conversation.php` - `intakeAnswers()` relation
- `tests/Feature/FrontDesk/IntakeAnswersTest.php`, `tests/Feature/Inbound/ContactNameTest.php` - new tests
- `tests/Feature/Inbound/ContactMatchingTest.php` - seven tests post no form name
- `docs/sprints/sprint-1.md` - walk-through entry

## Decisions Made

See `key-decisions` above. A form name that differs only by case or spacing from the stored name is treated as the same name (no note).

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Added `Conversation::intakeAnswers()` relation**
- **Found during:** Task 1
- **Issue:** `app/Models/Conversation.php` is not in the plan's files list, but `RecordIntakeAnswers` creates rows through the conversation.
- **Fix:** Added a `HasMany` relation ordered by id.
- **Files modified:** app/Models/Conversation.php
- **Committed in:** ecb8b5c

**2. [Rule 1 - Bug] Existing ContactMatchingTest assertions broke by the intended name-note rule**
- **Found during:** Task 2
- **Issue:** Seven tests match a nameless factory contact with a form that carries the default name `Dana Whitfield` and assert the exact list of notes. The new rule (plan behavior: a form matched to a contact with no name adds `form gave the name:`) adds a note.
- **Fix:** Those seven posts now send `'name' => null`, since those tests are about phone and email matching, not names. No assertion about notes was loosened.
- **Files modified:** tests/Feature/Inbound/ContactMatchingTest.php
- **Verification:** `php artisan test --compact tests/Feature/FrontDesk tests/Feature/Inbound tests/Feature/Records` and the full suite pass.
- **Committed in:** 497264c

**3. [Rule 1 - Bug] Global test helper name collided with DisclosureTest**
- **Found during:** Task 2
- **Issue:** `danaTextsAgain()` in my RED test was already declared in `DisclosureTest.php`; running a directory fatals. Single-file runs hid it at the RED commit.
- **Fix:** Renamed my helper to `danaCorrectsIntakeBySecondText()`.
- **Committed in:** 497264c

---

**Total deviations:** 3 auto-fixed (1 blocking, 2 bugs)
**Impact on plan:** No scope creep. The RED commit `41f7fc6` contains the colliding helper name; the directory-wide run only passes from `497264c` on.

## Issues Encountered

None beyond the deviations above.

## Known Stubs

None.

## Threat Flags

None. The two trust boundaries in the plan (model output to `intake_answers` and `contacts.name`; public form to `contacts`) are the mitigated T-02-34 and T-02-35; T-02-36 is covered by the quoted-under-label card format.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Phase 3 booking can read `intake_answers` (latest id per conversation, service and question) and knows which folder version asked.
- Full suite: 662 tests pass.

## Self-Check: PASSED

- Files exist: migration, IntakeAnswer, IntakeAnswerFactory, RecordIntakeAnswers, both test files.
- Commits `0ff2279`, `ecb8b5c`, `41f7fc6`, `497264c` are on the branch (4 measured by `git rev-list --count`).
- Plan acceptance criteria re-run: migration has `icm_version_id` and `constrained()`, `versionId(` and the morph map entry exist, `CustomerCard` has `## Intake answers` and `ActivityType::IntakeAnswered`, `RecordInbound` has `form gave the name:`, tests contain `Over 10 years`, `pool-cleaning`, `form gave the name: Someone Else` and `Name from the customer's text: Dana`; both automated commands pass.

---
*Phase: 02-text-front-desk*
*Completed: 2026-10-12*
