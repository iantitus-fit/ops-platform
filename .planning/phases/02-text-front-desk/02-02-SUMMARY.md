---
phase: 02-text-front-desk
plan: 02
subsystem: icm-sync
tags: [laravel-ai, icm, agent-file, validator, filament, pest]

requires:
  - phase: 02-text-front-desk
    provides: Postgres and SQLite test foundation, exampleFolderFileCount() helper (plan 02-01)
provides:
  - "agents/*.md is a typed file: validated in plain words, compiled, indexed by channel"
  - "ActiveConfig::agent(string $channel) reading the compiled version only"
  - "App\\Ai\\AgentFile with providerChain(), loads(), forChannel(), fromArray()"
  - "Example front-desk agent file (anthropic primary, openai fallback)"
  - "Business Folder page line naming the live front desk and its chain, or saying it is off"
affects: [02-13 fallback chain, 02-14 verdict, front-desk agent plans, Business Folder page]

actuals:
  tokens: 8900
  tasks: 2
  commits: 4
plan_head_before: de8f1bffef2f52345934afda90fdcbb2d9fb821a
plan_head_after: 69a57a2774ec44e1fa36f864da8be8355230585e

tech-stack:
  added: []
  patterns:
    - "Cross-file validator checks (agentChannelErrors, agentLoadsErrors) sit next to duplicateSlugErrors and report against the later file in path order"
    - "Provider names must match config/ai.php keys exactly, then Ai::textProvider() decides whether the provider can write text"
    - "Compiled index keyed by channel so a second agent can never silently replace the first"

key-files:
  created:
    - app/Ai/AgentFile.php
    - examples/example-hvac/agents/front-desk.md
    - tests/Feature/Icm/AgentFileTest.php
  modified:
    - app/Icm/Loader.php
    - app/Icm/Validator.php
    - app/Icm/FolderSync.php
    - app/Icm/ActiveConfig.php
    - app/Filament/Pages/BusinessFolder.php
    - resources/views/filament/pages/business-folder.blade.php
    - examples/example-hvac/agents/README.md
    - tests/Pest.php
    - tests/Feature/Filament/BusinessFolderPageTest.php
    - tests/Feature/Icm/ValidatorTest.php
    - tests/Feature/Icm/ActiveConfigTest.php
    - tests/Feature/Icm/SyncTest.php
    - docs/sprints/sprint-1.md

key-decisions:
  - "Provider names are checked against the exact keys of config/ai.php before Ai::textProvider() is called, because the SDK accepts 'Anthropic' (any case) and the plan requires lowercase exact keys"
  - "loads uses present|array|min:1 rather than required|array|min:1, because Laravel's required rejects an empty list with 'is missing' instead of the planned 'must have at least 1 item(s)'"
  - "A loads entry may point at a file that cannot be read (its own error is already reported); only later-sprint files and absent files are 'no file in the folder'"
  - "rejectedErrors() moved from ValidatorTest.php to tests/Pest.php so it is global when AgentFileTest runs alone"

patterns-established:
  - "Agent code reads the agent through AgentFile::forChannel(ActiveConfig, channel), never from disk"
  - "An agent file is validated by what code can honor: channel sms and escalate_to owner only"

requirements-completed: [AGNT-01, AGNT-04]

coverage:
  - id: D1
    description: "Agent file (type agent) is validated, compiled into the live version and indexed by channel; ActiveConfig::agent('sms') returns its front matter and body from the compiled copy"
    requirement: AGNT-01
    verification:
      - kind: integration
        ref: "tests/Feature/Icm/AgentFileTest.php#hands out the example front desk agent from the live version"
        status: pass
      - kind: integration
        ref: "tests/Feature/Icm/AgentFileTest.php#indexes the agent file by channel in the compiled version"
        status: pass
      - kind: integration
        ref: "tests/Feature/Icm/AgentFileTest.php#counts the agent file as part of the live version and skips nothing"
        status: pass
    human_judgment: false
  - id: D2
    description: "Provider chain is the primary provider and model followed by each fallback in file order, including the one-entry chain for fallback: []"
    requirement: AGNT-04
    verification:
      - kind: integration
        ref: "tests/Feature/Icm/AgentFileTest.php#reads the provider chain as the primary entry followed by each fallback entry"
        status: pass
      - kind: integration
        ref: "tests/Feature/Icm/AgentFileTest.php#goes live with a one entry chain when the fallback list is empty"
        status: pass
    human_judgment: false
  - id: D3
    description: "A broken agent file is rejected in plain words naming the file and field (12 cases: unknown, capitalised and non-text providers, fallback problems, repeated chain entry, wrong channel, wrong escalate_to, empty loads, loads that match nothing, records/), the previous version stays live, and the attempt is written to changelog.md"
    requirement: AGNT-01
    verification:
      - kind: integration
        ref: "tests/Feature/Icm/AgentFileTest.php#rejects a broken agent file in plain words and keeps the previous version live"
        status: pass
    human_judgment: false
  - id: D4
    description: "A second agent file on the sms channel is rejected with the one-agent-per-channel sentence; a folder with no agent file goes live"
    requirement: AGNT-01
    verification:
      - kind: integration
        ref: "tests/Feature/Icm/AgentFileTest.php#rejects a second agent file on the same channel"
        status: pass
      - kind: integration
        ref: "tests/Feature/Icm/AgentFileTest.php#goes live with no agent file, and says the front desk is off"
        status: pass
    human_judgment: false
  - id: D5
    description: "Business Folder page names the live front desk and its chain, or says the front desk is off"
    requirement: AGNT-04
    verification:
      - kind: integration
        ref: "tests/Feature/Filament/BusinessFolderPageTest.php#names the live front desk and the models it falls back to"
        status: pass
      - kind: integration
        ref: "tests/Feature/Filament/BusinessFolderPageTest.php#says the front desk is off when no agent file answers texts"
        status: pass
    human_judgment: false
  - id: D6
    description: "A sync that changes the agent file mid-job does not change that run (ActiveConfig is bound scoped); stated as a backstop in the plan, no test added here"
    requirement: AGNT-01
    verification: []
    human_judgment: true
    rationale: "Plan marks this truth as a backstop; ActiveConfig is already bound scoped in AppServiceProvider and no test exercises a mid-job sync in this plan"

duration: 5 min
completed: 2026-10-12
status: complete
---

# Phase 2 Plan 02: Agent files through the sync Summary

**`agents/*.md` is now a real file type: the sync validates it in plain words, compiles it indexed by channel, `AgentFile::providerChain()` hands code the primary model plus fallbacks, and the Business Folder page names the model that answers texts.**

## Performance

- **Duration:** 5 min
- **Started:** 2026-10-12T01:27:31Z
- **Completed:** 2026-10-12T01:32:30Z
- **Tasks:** 2
- **Files modified:** 16 (3 created, 13 modified)

## Accomplishments
- Example `agents/front-desk.md` (anthropic claude-sonnet-5-5, fallback openai gpt-6.1-sol, six loads, escalate_to owner) goes live through `icm:sync`; the example folder is now 9 typed files.
- `ActiveConfig::agent('sms')` and `App\Ai\AgentFile` (readonly) read only the compiled version; `providerChain()` returns primary then fallbacks in file order.
- Validator rejects 12 kinds of broken agent file with the exact sentences from the plan, plus the D-35 second-agent rule; the previous version stays live and the attempt lands in `changelog.md`.
- Business Folder page shows `Front desk: Front desk answers texts with anthropic claude-sonnet-5-5, then openai gpt-6.1-sol.` or `Front desk: off. ...`.
- Walk-through log line for `Ai::textProvider()` added to `docs/sprints/sprint-1.md`.

## Task Commits

1. **Task 1: End-to-end example agent file through icm:sync (tracer)**
   - RED `d5b719c` (test)
   - GREEN `7c3c3e8` (feat)
2. **Task 2: Broken agent files rejected in plain words; Business Folder names the front desk (tdd)**
   - RED `4536a90` (test)
   - GREEN `69a57a2` (feat)

**Plan metadata:** committed separately after this file (docs: complete plan).

## TDD Evidence

- **Task 1 RED:** 6 tests, 5 errored/failed on the planned behavior (`ActiveConfig::agent()` undefined, `AgentFile` class missing, `index['agent']` missing, example file absent). The count test passed unexpectedly because it was equal to the old count; it was tightened to assert `agents/front-desk.md` is in `ActiveConfig::files()` before the RED commit. The tracer gate was cleared by re-running `<verify>` after GREEN (113 passed).
- **Task 2 RED:** 33 tests, 12 failed on the planned assertions (providers, repeat entry, loads checks, second agent, two Business Folder lines). `gsd_run check tdd-red-evidence` returned `RED_EVIDENCE_OK` / `target_test_failed` for target `it rejects a second agent file on the same channel` from the PHPUnit JUnit report. Semantic assessment: every failure was the sync exiting 0 (or the page text absent) for a case the plan says must be rejected or shown; none was a syntax, import or fixture fault. Rows for fallback-without-model, channel and escalate_to already passed from Task 1 rules and were kept as the plan's required cases.
- **GREEN:** Task 2 then passed 20/20 in `AgentFileTest`, 306/306 in the full suite.

## Files Created/Modified
- `app/Ai/AgentFile.php` - readonly typed view of one agent file: `providerChain()`, `loads()`, `forChannel()`, `fromArray()`.
- `app/Icm/Loader.php` - `agent` moved from later-sprint to typed.
- `app/Icm/Validator.php` - `agent` rule arm, `agentErrors()`, `providerProblem()`, `usableProviders()`, `agentChannelErrors()`, `agentLoadsErrors()`.
- `app/Icm/FolderSync.php` - `index['agent'][<channel>]` entry.
- `app/Icm/ActiveConfig.php` - `agent(string $channel)` accessor.
- `app/Filament/Pages/BusinessFolder.php`, `resources/views/filament/pages/business-folder.blade.php` - `frontDesk()`, `frontDeskLine()` and the "Front desk" section.
- `examples/example-hvac/agents/front-desk.md`, `agents/README.md` - example agent and updated explanation.
- `tests/Feature/Icm/AgentFileTest.php`, `tests/Feature/Filament/BusinessFolderPageTest.php`, `tests/Pest.php` - new tests, helper count 8 to 9, `rejectedErrors()` made global.
- `tests/Feature/Icm/ValidatorTest.php`, `ActiveConfigTest.php`, `SyncTest.php` - kept in step with the new typed file (see deviations).
- `docs/sprints/sprint-1.md` - walk-through log line.

## Decisions Made
- Exact-key provider check before `Ai::textProvider()` (the SDK accepts `Anthropic`, but the plan requires lowercase exact keys).
- `loads` uses `present|array|min:1` so an empty list reads "must have at least 1 item(s)".
- Unreadable-but-present files count as present for `loads`, avoiding duplicate cascade errors; later-sprint files do not count.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] `Anthropic` accepted as a provider**
- **Found during:** Task 2 GREEN
- **Issue:** `Ai::textProvider('Anthropic')` succeeds (SDK is case-insensitive), so the capital-letter row passed the sync.
- **Fix:** `providerProblem()` first requires an exact key of `config('ai.providers')`.
- **Files modified:** app/Icm/Validator.php
- **Verification:** dataset row "provider written with a capital" passes.
- **Committed in:** 69a57a2

**2. [Rule 1 - Bug] Empty `loads: []` reported as "missing"**
- **Found during:** Task 2 GREEN
- **Issue:** The Task 1 rule `required|array|min:1` reports an empty list as "loads is missing, and it is required".
- **Fix:** Rule changed to `bail|present|array|min:1`.
- **Files modified:** app/Icm/Validator.php
- **Verification:** "loads list left empty" row passes with `loads must have at least 1 item(s)`.
- **Committed in:** 69a57a2

**3. [Rule 3 - Blocking] `rejectedErrors()` not global when AgentFileTest runs alone**
- **Found during:** Task 2 RED
- **Issue:** The plan said to reuse the helper, but it was defined inside `ValidatorTest.php`, so it only exists when that file is loaded.
- **Fix:** Moved it to `tests/Pest.php` (file already in the plan's list).
- **Files modified:** tests/Pest.php, tests/Feature/Icm/ValidatorTest.php
- **Committed in:** 4536a90

**4. [Rule 1 - Bug] Existing tests stale after agent became typed**
- **Found during:** Task 1 and Task 2 GREEN
- **Issue:** `ValidatorTest` unknown-type message now ends `, agent`; `ActiveConfigTest` counted 8 typed files; `SyncTest` title said "eight files"; two `ValidatorTest` cases that delete files the agent loads now also report the agent's `loads item N` lines.
- **Fix:** Updated those expectations (no test deleted); the count uses `exampleFolderFileCount()`.
- **Files modified:** tests/Feature/Icm/ValidatorTest.php, ActiveConfigTest.php, SyncTest.php
- **Committed in:** 7c3c3e8, 69a57a2

**5. [Rule 1 - Bug] Test row used a folder that does hold a typed file**
- **Found during:** Task 2 GREEN
- **Issue:** The RED row "loads folder with no typed file" used `agents/`, which holds the agent file itself.
- **Fix:** Row now uses `automations/`.
- **Files modified:** tests/Feature/Icm/AgentFileTest.php
- **Committed in:** 69a57a2

**6. [Rule 1 - Bug] Stale docblock example**
- `BusinessFolder::noticeLine()` cited `agents/front-desk.md` as a skipped file; now cites `automations/follow-up.md`. Committed in 69a57a2.

---

**Total deviations:** 6 auto-fixed (5 bug, 1 blocking)
**Impact on plan:** All needed for the plan's stated behaviors and a green suite. No scope creep.

## Issues Encountered
None beyond the deviations above.

## Known Stubs
None.

## Threat Flags
None. T-02-04, T-02-05 and T-02-06 mitigations are implemented and tested (loads checks, `Rule::in` for channel and escalate_to, one agent per channel with channel-keyed index).

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Later plans read the agent through `AgentFile::forChannel(app(ActiveConfig::class), 'sms')`; plan 02-13 can iterate `providerChain()` for fallback behavior.
- No blockers.

## Self-Check: PASSED

- FOUND: app/Ai/AgentFile.php, examples/example-hvac/agents/front-desk.md, tests/Feature/Icm/AgentFileTest.php
- FOUND commits: d5b719c, 7c3c3e8, 4536a90, 69a57a2
- Full suite: 306 passed
- TDD gate: test(02-02) precedes feat(02-02) for both tasks

---
*Phase: 02-text-front-desk*
*Completed: 2026-10-12*
