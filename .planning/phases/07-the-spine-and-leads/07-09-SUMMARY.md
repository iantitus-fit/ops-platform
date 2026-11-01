---
phase: 07-the-spine-and-leads
plan: 09
subsystem: ui
tags: [filament, livewire, accessibility, wcag, pest-browser, relation-manager]

requires:
  - phase: 07-the-spine-and-leads
    provides: "07-05 ProjectsRelationManager (the contact screen's project list) and the four project actions (D-11)"
provides:
  - "Project list drawn in the contact page's first response (G-07-2.1)"
  - "One stable Close/Reopen row button whose wire:key never changes, both directions confirmed (G-07-2.2)"
  - "Per-row aria-labels that name the project, escaped with e() (G-07-2.3)"
  - "A Pest browser proof that Enter keeps focus on the row's button after Close and after Reopen"
affects: [phase-07-uat, phase-11-styling]

gap_closure:
  resolved: [G-07-2.1, G-07-2.2, G-07-2.3]

actuals:
  tokens: 12116
  tasks: 3
  commits: 6
plan_head_before: 769eae0061287b791e5b23a2087621e79f2c2fdd
plan_head_after: 84e5c28fae8af12d74fcd82cbd6c0adf689d9842
commits: 6

tech-stack:
  added: []
  patterns:
    - "A relation manager a keyboard must reach sets protected static bool $isLazy = false"
    - "A row button whose label changes with state is one Filament action with closures, so Livewire keeps one wire:key and focus survives the re-render"
    - "Owner text going into extraAttributes() goes through e(), because Filament prints extra attributes unescaped"
    - "A Pest browser test calls visit() in its own body, or the plugin never starts its browser"

key-files:
  created:
    - tests/Feature/Filament/ProjectsRelationManagerKeyboardTest.php
  modified:
    - app/Filament/Resources/Contacts/RelationManagers/ProjectsRelationManager.php
    - tests/Feature/Filament/ProjectsRelationManagerTest.php
    - .planning/phases/07-the-spine-and-leads/07-UI-SPEC.md
    - docs/BLUEPRINT.md
    - docs/sprints/sprint-1.1.md
    - .planning/WINDOWS.md

key-decisions:
  - "Colour (Claude's discretion): the one toggle button stays gray while it reads Close and Filament's default primary while it reads Reopen, through a per-row colour closure; Action::getLivewireKey() reads only the action name and context, so the colour cannot change wire:key (proved by the wire:key guard). Phase 11 may choose one colour."
  - "T-07-26 mitigated by Ian's revision (2026-11-01): both directions ask, the dialog names the job and is decided from the project's state when the button is pressed, and the direction it stated is carried to the submit in a Hidden field, so the submit does what the dialog said or nothing."
  - "Dialog copy is Claude's wording (Ian may adjust): '{label} will be marked closed. You can reopen it at any time.' and '{label} will be marked open again. You can close it at any time.', submit labels Close and Reopen; headings 'Close Johnny?' and 'Reopen Job 1?' are Ian's."

patterns-established:
  - "Stable action name for a state-flipping row button (toggle) instead of two actions"
  - "rowLabel() / rowButtonName(): one label helper shared by the column and the buttons' accessible names"

requirements-completed: [PROJ-01, PROJ-02]

coverage:
  - id: D1
    description: "The contact page's first response holds the project rows and buttons and no lazy placeholder, so Tab reaches the list (G-07-2.1)"
    requirement: PROJ-01
    verification:
      - kind: integration
        ref: "tests/Feature/Filament/ProjectsRelationManagerTest.php#shows on the contact page in its first response, so Tab can reach the list"
        status: pass
      - kind: unit
        ref: "tests/Feature/Filament/ProjectsRelationManagerTest.php#is not lazy"
        status: pass
    human_judgment: false
  - id: D2
    description: "Every row button carries an aria-label of its visible word plus the row's label, escaped, following a rename and present in the first response (G-07-2.3)"
    requirement: PROJ-02
    verification:
      - kind: integration
        ref: "tests/Feature/Filament/ProjectsRelationManagerTest.php#names each row button by its project, visible text unchanged"
        status: pass
      - kind: integration
        ref: "tests/Feature/Filament/ProjectsRelationManagerTest.php#escapes an owner-typed name in a button's name"
        status: pass
      - kind: integration
        ref: "tests/Feature/Filament/ProjectsRelationManagerTest.php#carries the names in the contact page's first response"
        status: pass
    human_judgment: false
  - id: D3
    description: "One Close/Reopen button per row with a stable wire:key; both directions ask with a dialog naming the job, decided when pressed; a stale page and a change while the dialog is open never do the opposite (G-07-2.2, T-07-26)"
    requirement: PROJ-02
    verification:
      - kind: integration
        ref: "tests/Feature/Filament/ProjectsRelationManagerTest.php#closes and reopens from one row button that keeps its place"
        status: pass
      - kind: integration
        ref: "tests/Feature/Filament/ProjectsRelationManagerTest.php#asks before closing and before reopening, naming the job"
        status: pass
      - kind: integration
        ref: "tests/Feature/Filament/ProjectsRelationManagerTest.php#does what the dialog said when the project changes while it is open"
        status: pass
    human_judgment: false
  - id: D4
    description: "In real Chromium with real Enter keys, focus stays on the row's button after a confirmed Close and after a confirmed Reopen (WCAG 2.4.3)"
    requirement: PROJ-02
    verification:
      - kind: e2e
        ref: "tests/Feature/Filament/ProjectsRelationManagerKeyboardTest.php (two cases)"
        status: pass
    human_judgment: false
  - id: D5
    description: "The VoiceOver and Safari retest of the whole contact-screen walk (announcements, look and spacing unchanged)"
    verification: []
    human_judgment: true
    rationale: "Screen reader announcements and visual spacing are not asserted by any automated test; recorded as WINDOWS entry 44 for the UAT retest"

duration: 11min
completed: 2026-11-01
status: complete
---

# Phase 7 Plan 09: Contact Screen Keyboard and Screen Reader Gaps Summary

**The contact screen's project list now draws with the page ($isLazy false), every row button is named for its project through an escaped aria-label, and Close and Reopen are one stable Filament action that asks first in both directions so keyboard focus stays on the row's button, proven with real Enter keys in Chromium.**

## Performance

- **Duration:** 11 min
- **Started:** 2026-11-01T15:30:16Z
- **Completed:** 2026-11-01T15:41:00Z
- **Tasks:** 3 (task 1 a tracer, all three TDD)
- **Files modified:** 7 (one created)

## Accomplishments

- G-07-2.1 resolved: `ProjectsRelationManager::$isLazy = false`, so a real GET of a contact page carries the rows and the New and Rename buttons and no `__lazyLoad` placeholder.
- G-07-2.3 resolved: `rowLabel()` is shared by the label column and `rowButtonName()`, which builds `aria-label = e(visible word + ' ' + row label)`; a hostile name (quote, ampersand, b and script elements) renders the exact escaped attribute, the parsed attribute equals the raw string, and nothing is injected; `Smith & Sons` is escaped exactly once.
- G-07-2.2 resolved: one `toggle` action (gray while it reads Close, primary while it reads Reopen) replaces `close` and `reopen`; its wire:key is byte-identical before Close, after Close and after Reopen; two Chromium cases show `document.activeElement` is `Reopen Job 1` after a keyboard Close and `Close Job 1` after a keyboard Reopen.
- T-07-26 mitigated by Ian's revision: both directions confirm ('Close Johnny?', 'Reopen Job 1?'), the dialog is decided when the button is pressed (a stale page opens the true Reopen dialog; cancelling changes nothing), and the direction is carried to the submit in a Hidden `direction` field, so a change while the dialog is open can never flip the action.
- 07-UI-SPEC.md, BLUEPRINT sections 3 and 10 and three walk-through lines (plan 07-09) updated in step; WINDOWS entries 44 and 45 recorded.

## Task Commits

1. **Task 1: Tracer, list in the first response (G-07-2.1)** - `48f04f1` (test), `3457128` (feat)
2. **Task 2: Row buttons name their project (G-07-2.3)** - `a55a36d` (test), `75f1c73` (feat)
3. **Task 3: One Close/Reopen button keeps focus (G-07-2.2)** - `5f85408` (test), `84e5c28` (feat)

**Plan metadata:** recorded in the docs(07-09) commit that follows this summary.

## Files Created/Modified

- `app/Filament/Resources/Contacts/RelationManagers/ProjectsRelationManager.php` - `$isLazy` false, `rowLabel()`, `rowButtonName()`, the `toggle` action with confirmation, Hidden direction and per-row colour
- `tests/Feature/Filament/ProjectsRelationManagerTest.php` - first-response, not-lazy, accessible-name, escaping, wire:key and confirmation cases; two Phase 7 tests rewritten
- `tests/Feature/Filament/ProjectsRelationManagerKeyboardTest.php` - the two real-keyboard browser cases
- `.planning/phases/07-the-spine-and-leads/07-UI-SPEC.md`, `docs/BLUEPRINT.md`, `docs/sprints/sprint-1.1.md` - contract, blueprint and walk-through log
- `.planning/WINDOWS.md` - entries 44 and 45

## Decisions Made

See key-decisions above. The colour decision is recorded in the objective, the UI-SPEC Color line and the action's docblock. WINDOWS entry 38 stays open: the UAT retest decides it.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Pest browser plugin starts only for a test that calls visit() in its own body**
- **Found during:** Task 3 (RED run of the keyboard test)
- **Issue:** the first draft called `visit()` inside a file-local helper; the plugin detects browser tests from the test closure, so the Playwright server never started and both cases failed with "WebSocket client is not connected" rather than on the focus expectation.
- **Fix:** the helper now returns the contact page address and the test calls `visit()` itself.
- **Files modified:** tests/Feature/Filament/ProjectsRelationManagerKeyboardTest.php
- **Verification:** RED then failed for the planned reasons ('BODY' for case 1; no confirmation open for case 2); GREEN passes.
- **Committed in:** `5f85408`

**2. [Rule 1 - Bug] assertTableActionMounted cannot take a record**
- **Found during:** Task 3 GREEN (my own RED test)
- **Issue:** `assertTableActionMounted('toggle')` compares the mounted context with one that has no record key, so it failed on a record action.
- **Fix:** the test reads `getMountedAction()->getName()` instead.
- **Files modified:** tests/Feature/Filament/ProjectsRelationManagerTest.php
- **Verification:** the whole file passes.
- **Committed in:** `84e5c28`

---

**Total deviations:** 2 auto-fixed (1 blocking, 1 bug in a new test)
**Impact on plan:** test-only; no change to what shipped.

## Issues Encountered

The `windows append` command prints the whole ledger; entry numbers 44 and 45 were read from its output. The pin guard was run as a shortened equivalent of the supplied script (absolute-root comparison only; no submodule case applies).

## TDD Gate Compliance

Each task has a `test(07-09)` commit before its `feat(07-09)` commit. RED evidence: Task 1, two failing assertions (first response held no "Job 1"; `isLazy()` true). Task 2, five failing assertions (no aria-label). Task 3, four Livewire cases erroring because no `toggle` action existed (the planned red), browser case 1 failing on focus ('BODY'), browser case 2 failing because Reopen opened no confirmation. The classifier command `gsd_run check tdd-red-evidence` was not used because Pest's JSON report is not one of its supported formats; semantic inspection of each failure was done by hand against the messages above.

## Known Stubs

None.

## Threat Flags

None. T-07-25 and T-07-26 are mitigated and tested; T-07-27 is accepted as planned (the list now renders inside the contact page's request).

## User Setup Required

None - no external service configuration required.

## Hand checks (recorded, not paused on)

- WINDOWS 44: UAT retest of entry 38 with VoiceOver in Safari and by keyboard.
- WINDOWS 45: after Ian pushes the phase branch, CI green on both legs with the new browser file included.

## Next Phase Readiness

The three UAT gaps from 07-UAT.md test 2 are closed in code; the phase is ready for the UAT retest (07-UAT.md test 2) and then verification. Full suite: 2428 tests passed on SQLite. D-22 holds: modified pre-existing test files are still exactly CardInjectionTest.php, FrontDeskBootTest.php and Pest.php.

## Self-Check: PASSED

---
*Phase: 07-the-spine-and-leads*
*Completed: 2026-11-01*
