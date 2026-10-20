---
phase: 02-text-front-desk
plan: 28
subsystem: gate
tags: [artisan-command, json-lines, labeled-data, d-07, postgres, phase-gate, pest]

requires:
  - phase: 02-text-front-desk
    provides: "Versioned gate JSON on agent_reply messages (02-14, 02-15), draft resolution columns and original_body (02-17), approve/edit/discard through ResolveDraft and OutboundSender::sendExisting (02-22), Twilio send and status (02-27)"
provides:
  - "php artisan gate:export {--since=} {--without-text}: one JSON line per front-desk decision on standard output, with the verdict and the owner's resolution"
  - "--without-text export: customer, agent and reply texts, matched findings and error messages replaced by their lengths, safe to share"
  - "Phase gate: the whole suite (931 tests) green on SQLite and on PostgreSQL 17 through the local Docker recipe"
  - "Phase 1 review items WR-02 and WR-13 recorded as fixed, naming the Phase 2 plans that fixed them"
affects: [02-29, phase 2 verification, later threshold tuning from the labeled data]

actuals:
  tokens: 6000
  tasks: 2
  commits: 3
plan_head_before: ad504f25912b08c82a835dbc751804452ce3134a
plan_head_after: ce4957f280668cff416a33e7cd9e2b78a5115e25
commits: 3

tech-stack:
  added: []
  patterns:
    - "Artisan command with #[Signature] options that prints JSON lines through lazyById and OUTPUT_RAW so customer text is never read as console formatting"
    - "Redaction by key list: a text key is swapped for <key>_length wherever it holds a string, and a null stays null"

key-files:
  created:
    - app/Console/Commands/GateExport.php
    - tests/Feature/FrontDesk/GateExportTest.php
  modified:
    - docs/sprints/sprint-1.md
    - .planning/phases/01-foundation/01-REVIEW-DISPOSITION.md

key-decisions:
  - "decided_at is the message's created_at (when the gate decided), in ISO 8601 UTC; --since is the start of that day in UTC, which is the app time zone"
  - "final_text is what went out: null for a draft that was discarded, replaced or is still waiting. original_text is the saved original when the owner edited, else the message body"
  - "resolved_by is the user id, not a name, so a shared export names nobody"
  - "With --without-text the *_length keys replace the text keys in place (final_text_length at the top level, customer_text_length and agent_reply_length inside verdict.inputs)"

patterns-established:
  - "An export meant for sharing is tested by asserting that no known customer word appears anywhere in the raw output, not only that the obvious keys are gone"

requirements-completed: [GATE-01, INFRA-01]

coverage:
  - id: D1
    description: "gate:export prints exactly one JSON line per agent reply with a verdict, in message id order, with ids, when, outcome, reason, label, full verdict, final and original text, and the owner's resolution"
    requirement: GATE-01
    verification:
      - kind: integration
        ref: "tests/Feature/FrontDesk/GateExportTest.php#prints one JSON line per front-desk decision in message id order (D-07)"
        status: pass
      - kind: integration
        ref: "tests/Feature/FrontDesk/GateExportTest.php#exports an edited draft with the agent words and the words that were sent"
        status: pass
      - kind: integration
        ref: "tests/Feature/FrontDesk/GateExportTest.php#exports a discarded and a replaced draft with no final text because nothing was sent"
        status: pass
    human_judgment: false
  - id: D2
    description: "Holding, handoff and life-safety texts are not exported; --since limits the rows; a bad --since fails with a plain sentence; an empty database prints nothing and exits 0"
    requirement: GATE-01
    verification:
      - kind: integration
        ref: "tests/Feature/FrontDesk/GateExportTest.php#does not export holding, handoff or life-safety texts"
        status: pass
      - kind: integration
        ref: "tests/Feature/FrontDesk/GateExportTest.php#limits the rows to decisions on or after --since"
        status: pass
      - kind: integration
        ref: "tests/Feature/FrontDesk/GateExportTest.php#refuses a --since that is not a date"
        status: pass
      - kind: integration
        ref: "tests/Feature/FrontDesk/GateExportTest.php#prints nothing and succeeds when there are no decisions"
        status: pass
    human_judgment: false
  - id: D3
    description: "An export made with --without-text carries no customer or reply words anywhere, including quoted code-check findings and provider error messages, while keeping lengths, labels, reasons and thresholds"
    requirement: GATE-01
    verification:
      - kind: integration
        ref: "tests/Feature/FrontDesk/GateExportTest.php#leaves out every customer and reply text with --without-text but keeps the lengths"
        status: pass
      - kind: integration
        ref: "tests/Feature/FrontDesk/GateExportTest.php#carries no customer or reply words anywhere in a --without-text export, even in a quoted finding or an error"
        status: pass
    human_judgment: false
  - id: D4
    description: "The full Pest suite passes on SQLite and on PostgreSQL 17 with every Phase 2 test included"
    requirement: INFRA-01
    verification:
      - kind: other
        ref: "php artisan test --compact on SQLite: 931 passed, 3961 assertions"
        status: pass
      - kind: other
        ref: "DB_CONNECTION=pgsql ... php artisan test --compact against postgres:17 in Docker: 931 passed, 3961 assertions (schema confirmed in the container: messages, conversations, migrations)"
        status: pass
    human_judgment: false
  - id: D5
    description: "WR-02 and WR-13 are recorded as fixed in 01-REVIEW-DISPOSITION.md with the Phase 2 plans that fixed them, and sprint-1.md names a plan for each of the five done-when criteria"
    verification: []
    human_judgment: true
    rationale: "Documentation claims about which plans delivered which criterion; a person should read them against the plan summaries"

# Metrics
duration: 10min
completed: 2026-10-11
status: complete
---

# Phase 2 Plan 28: Labeled Data Export and Phase Gate Summary

**`php artisan gate:export` prints every front-desk send-or-draft decision, with the verdict and what the owner did, as JSON lines (with a `--without-text` copy safe to share), and the full 931-test suite passes on SQLite and PostgreSQL 17.**

## Performance

- **Duration:** about 10 min
- **Started:** about 2026-10-12T04:03Z (not clocked at the start; taken from the first commit time and the orchestrator's last state update)
- **Completed:** 2026-10-12T04:14Z
- **Tasks:** 2
- **Files modified:** 4 (2 created, 2 modified)

## Accomplishments
- Every `agent_reply` message with a verdict exports as one line: `message_id`, `conversation_id`, `decided_at`, `outcome`, `reason_code`, `reason_plain`, `label`, `verdict` (the stored gate JSON), `final_text`, `original_text`, `resolution`, `resolved_at`, `resolved_by`, `owner_edited`. Approve, edit, discard and replace all show up, and an edited draft carries both the agent's words and the words that went out (D-07, D-54).
- `--since=YYYY-MM-DD` limits the rows and refuses anything that is not a real date. `--without-text` removes every customer and reply text for sharing and keeps their lengths.
- The phase gate ran the whole suite on SQLite and on PostgreSQL 17 (Docker recipe from docs/SETUP.md): 931 passed on both, no Postgres-only failure, so no fix commit was needed. The container was removed afterwards.
- WR-02 and WR-13 are marked fixed in `01-REVIEW-DISPOSITION.md` (frontmatter and table), naming plans 02-08, 02-09 and 02-18. `docs/sprints/sprint-1.md` now names the delivering plans under each done-when criterion and lists the manual items still open for UAT.

## Task Commits

1. **Task 1: gate:export (tracer, TDD)**
   - RED: `f873ce2` (test) - 12 failing tests
   - GREEN: `4b9fa18` (feat) - command, sprint notes and walk-through line
2. **Task 2: Phase gate and Phase 1 carry-overs** - `ce4957f` (docs)

**Plan metadata:** the docs commit that adds this summary, STATE.md and ROADMAP.md (see git log).

## TDD Gate Compliance

- **RED** (`f873ce2`): `vendor/bin/pest tests/Feature/FrontDesk/GateExportTest.php --log-junit ...` exited 2 with 12 of 12 tests failing. `gsd_run check tdd-red-evidence` on the unchanged JUnit report returned `RED_EVIDENCE_OK` (`target_test_failed`). Semantic assessment: every test ran (none skipped, no load or fixture fault) and all failed with `The command "gate:export" does not exist`, which is the planned reason, since the command had not been written yet.
- **GREEN** (`4b9fa18`): the same file plus `tests/Unit/ArchitectureTest.php` passed, 26 tests.
- **REFACTOR:** none needed.
- Note: the plan's tasks carry no `tdd="true"` attribute, so the formal gate was not triggered. The RED-then-GREEN order was followed anyway because the plan's action says so.
- **Tracer gate:** Task 1 is `type="tracer"`. The run is interactive, `human_verify_mode` is `end-of-phase` and the verify holds only `<automated>`, so the verify was re-run end to end before Task 2 (passed), with no checkpoint.

## Files Created/Modified
- `app/Console/Commands/GateExport.php` - the `gate:export` command, reading messages with `lazyById()` and printing raw JSON lines
- `tests/Feature/FrontDesk/GateExportTest.php` - 12 tests with helpers named per file (`gateExportVerdict`, `gateExportDecisions`, `gateExportLines`)
- `docs/sprints/sprint-1.md` - export note under "Notes for testing", plan names under "Done when", the open UAT items, and one walk-through log line
- `.planning/phases/01-foundation/01-REVIEW-DISPOSITION.md` - WR-02 and WR-13 set to fixed

## Decisions Made
- `decided_at` is `created_at` (the moment the gate decided); `--since` means the start of that day in UTC, which is the app time zone (`config/app.php`).
- `final_text` is null when nothing went out (discarded, replaced, or still a draft), because exporting the draft body as "final" would label a text the customer never received. `original_text` is `original_body` when the owner edited, otherwise `body`.
- `resolved_by` exports the user id, so a shared file names nobody.
- `--without-text` replaces keys in place with `*_length` keys rather than adding them beside a removed block, so the shape of the verdict stays recognisable.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Missing Critical] `--without-text` also strips quoted findings and error messages**
- **Found during:** Task 1 (writing the redaction)
- **Issue:** The plan named only the final and original text and `verdict.inputs.customer_text` / `agent_reply`. But the stored verdict also holds `checks.code.findings[].match` (a snippet of the reply as written), `checks.classifier.error` and `failed_attempts[].error` (a provider's message, which can echo what it was sent). An export "for sharing the labels without the customers' words" (threat T-02-54, the plan's prohibition) would still have carried words from those fields.
- **Fix:** Those three fields are swapped for `match_length` / `error_length`. A null error stays null. A test seeds a customer address into all three places and asserts it appears nowhere in the raw output.
- **Files modified:** `app/Console/Commands/GateExport.php`, `tests/Feature/FrontDesk/GateExportTest.php`
- **Verification:** `GateExportTest` passes (26 tests with the architecture file)
- **Committed in:** `4b9fa18`

**2. [Rule 1 - Bug] Lines are printed raw so angle brackets in a customer's text survive**
- **Found during:** Task 1 (design of the output call)
- **Issue:** `$this->line()` runs the text through Symfony's console formatter, which would read a customer's `<error>...</error>` as styling and drop it from the file.
- **Fix:** Output goes through `getOutput()->write(..., false, OutputInterface::OUTPUT_RAW)`, with a test that a text containing `<error>now</error>` is printed exactly.
- **Files modified:** `app/Console/Commands/GateExport.php`, `tests/Feature/FrontDesk/GateExportTest.php`
- **Committed in:** `4b9fa18`

---

**Total deviations:** 2 auto-fixed (1 missing critical, 1 bug)
**Impact on plan:** Both keep the export faithful to its purpose. No scope creep, no new files beyond the plan's list.

## Issues Encountered
- Two early test failures in my own test file, fixed before the GREEN commit: `Artisan::output()` empties its buffer when read, so a second read returned an empty string, and an editing tool turned a literal `í` into the accented letter. Both are test-side only.
- The plan's Task 2 verify command was run in two steps (a loop with a short sleep waited for the container, then the suite) rather than pasted as one line; the commands and result are the same.

## Known Stubs

None.

## Threat Flags

None. The only new surface is a console command that prints to standard output (T-02-54 in the plan), mitigated by `--without-text` and the docblock and sprint note that say to keep the full export private.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- Plan 02-29 (the cartographer map) is next and does not depend on this plan's code.
- Still for Ian, per the sprint file: a live model reply from `/dev/chat`, the real-phone alert check, the first CI run after he pushes the branch (no git remote here), and live Twilio after A2P approval.
- The export field names are now the format later calibration reads (the plan marks this reversibility as costly). The verdict is exported as stored, version 1, so a later shape can sit beside it.

## Self-Check: PASSED

- FOUND: app/Console/Commands/GateExport.php
- FOUND: tests/Feature/FrontDesk/GateExportTest.php
- FOUND commits: f873ce2, 4b9fa18, ce4957f (all ancestors of HEAD)
- `php artisan list | grep gate:export` prints the command
- Acceptance criteria re-run: GateExport.php contains `gate:export`, `--without-text` handling and `lazyById`; GateExportTest contains `owner_edited` and `without-text` and passes; both full-suite runs exit 0; WR-02 and WR-13 rows say fixed and name 02-08, 02-09 (and 02-18 for WR-13); sprint-1 Done-when names a plan for each of the five criteria.

---
*Phase: 02-text-front-desk*
*Completed: 2026-10-11*
