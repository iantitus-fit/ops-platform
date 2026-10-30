---
phase: 07-the-spine-and-leads
plan: 07
subsystem: rooms
tags: [laravel, filament, rooms, dashboard, eloquent, axe, wcag]

requires:
  - phase: 07-the-spine-and-leads
    provides: "Project model with withNumber()/label()/openedByUser() (07-02), the four project actions (07-04), LeadsServiceProvider and LeadsBootTest (07-06)"
provides:
  - "App\\Rooms\\Leads\\LeadsSection: the leads room's dashboard section, newest 20 open projects"
  - "resources/views/filament/rooms/leads-section.blade.php: rows with a keyboard link and an absolute date"
  - "LeadsServiceProvider room() now declares sections: [LeadsSection::class]"
  - "LeadsSectionTest and the axe runs in LeadsBootTest"
affects: [07-08 automatic project open, Phase 8 appointments, Phase 9 business-wide project view, Phase 11 phone design pass]

actuals:
  tokens: 6700
  tasks: 2
  commits: 4

plan_head_before: 8e4d17a1fa5f4abab67bf85ef49733fec2e73ef5
plan_head_after: f1e8b78770313f5c79f782dbef0afa51aebbe954
commits: 4

tech-stack:
  added: []
  patterns:
    - "A RoomSection subclass with its own Blade view that calls parent::getViewData() and adds rows, so the shared section view and RoomSection stay unchanged"
    - "Eager loading with ->with([...]) keeps the dashboard's query count flat as rows grow"
    - "Per-draw memo of lead source names looked up through ActiveConfig::leadSource()"

key-files:
  created:
    - app/Rooms/Leads/LeadsSection.php
    - resources/views/filament/rooms/leads-section.blade.php
    - tests/Feature/Rooms/LeadsSectionTest.php
  modified:
    - app/Rooms/Leads/LeadsServiceProvider.php
    - tests/Feature/Rooms/LeadsBootTest.php
    - examples/example-hvac/rooms/leads/CONTEXT.md
    - docs/BLUEPRINT.md
    - docs/sprints/sprint-1.1.md
    - .planning/WINDOWS.md

key-decisions:
  - "The section shows at most LeadsSection::SHOWN (20) rows and one total line past that (Claude's discretion flagged in the plan; every project stays on its contact's screen)"
  - "Own Blade view rather than editing the shared section view (research Pattern 6, A6), so RoomSectionTest and the other rooms are untouched"
  - "Lead source names are looked up through ActiveConfig::leadSource() once per distinct key per draw, falling back to the raw key and then 'unknown'"

patterns-established:
  - "A row carries a focusable named link and an absolute date inside a time element (Phase 6 UI review), with data-leads-row so tests count rows without parsing Filament markup"

requirements-completed: [ROOM-07, PROJ-02]

coverage:
  - id: D1
    description: "The leads section lists open projects newest first, each row a keyboard link to the contact with the date it opened in the display time zone"
    requirement: ROOM-07
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/LeadsBootTest.php#shows an open project on the dashboard"
        status: pass
      - kind: integration
        ref: "tests/Feature/Rooms/LeadsSectionTest.php#lists only open projects, newest first"
        status: pass
    human_judgment: false
  - id: D2
    description: "Each row says where the project came from: the lead source name, the raw key when unlisted, unknown when empty, or who opened it by hand (a removed user when gone)"
    requirement: ROOM-07
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/LeadsSectionTest.php#names the lead source of a project a new lead opened"
        status: pass
      - kind: integration
        ref: "tests/Feature/Rooms/LeadsSectionTest.php#names who opened a project by hand"
        status: pass
    human_judgment: false
  - id: D3
    description: "The section shows the business's word, caps at 20 rows with a total line, and keeps a flat query count"
    requirement: PROJ-02
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/LeadsSectionTest.php#uses the business's word"
        status: pass
      - kind: integration
        ref: "tests/Feature/Rooms/LeadsSectionTest.php#shows the newest 20 and says how many there are"
        status: pass
      - kind: integration
        ref: "tests/Feature/Rooms/LeadsSectionTest.php#runs the same number of queries for 3 and for 10 rows"
        status: pass
    human_judgment: false
  - id: D4
    description: "The section hides when the leads room is off while projects stay on the contact screen and card, and shows one failure line when it cannot load"
    requirement: ROOM-07
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/LeadsSectionTest.php#hides when the leads room is off, and the project stays elsewhere"
        status: pass
      - kind: integration
        ref: "tests/Feature/Rooms/LeadsSectionTest.php#shows one line when the section cannot load"
        status: pass
    human_judgment: false
  - id: D5
    description: "axe finds no WCAG 2.2 AA violation in the section with and without an open project"
    requirement: ROOM-07
    verification:
      - kind: automated_ui
        ref: "tests/Feature/Rooms/LeadsBootTest.php#finds no accessibility violation in the leads section"
        status: pass
    human_judgment: true
    rationale: "axe catches only part of WCAG; the keyboard-only and VoiceOver walk of the row links is recorded as an unrun-verify for end-of-phase UAT"

duration: 9min
completed: 2026-10-30
status: complete
---

# Phase 7 Plan 07: The Leads Section Summary

**The leads room's dashboard section lists the newest 20 open projects, each a keyboard link to its contact with an absolute opened date and where it came from (lead source name or who opened it), hides with the room, and passes axe.**

## Performance

- **Duration:** 9 min
- **Started:** 2026-10-30T23:33:01Z
- **Completed:** 2026-10-30T23:42:21Z
- **Tasks:** 2
- **Files modified:** 9 (3 created, 6 modified, plus WINDOWS.md)

## Accomplishments

- `LeadsSection` extends `RoomSection`, keeps the base heading, hiding and failure line through `parent::getViewData()`, and adds the rows; its own Blade view draws `<x-filament::link>` per row and a `<time datetime>` for the opened date in the display time zone.
- Rows carry their contact and opening user up front (`->with(['contact', 'openedByUser'])`); a test confirms the query count is identical for 3 and 10 rows, and I confirmed it fails with the eager load removed.
- D-16: a new-lead row says `source <name>` from `business/lead-sources.md` (the raw key when unlisted, `unknown` when empty); a hand-opened row says `opened by <user>` or `a removed user`.
- The cap works at the boundary: 19 and 20 open projects draw every row with no total line, 21 draw 20 and "Showing the newest 20 of 21 open jobs."
- Turning the room off hides only the section; the contact screen's project list and the customer card's Jobs section still show the project. A dropped `projects` table gives the one failure line while "Drafts waiting" still loads.

## Task Commits

1. **Task 1 (tracer): an open project shows in the leads section, linked and dated, and passes axe**
   - `120e00b` (test, RED)
   - `b4d43a9` (feat, GREEN)
2. **Task 2: sources, people, only open, cap, word, room off, failure line, flat queries**
   - `a266135` (test, RED)
   - `f1e8b78` (feat, GREEN)

**Plan metadata:** the docs commit that carries this SUMMARY, STATE.md, ROADMAP.md and state.json.

## TDD Gate Compliance

Both tasks have a `test(07-07)` commit before their `feat(07-07)` commit; no refactor commit was needed.

- **RED, Task 1** (`120e00b`): four targets failed on their planned assertions: 'boots the leads room' (sections expected `[LeadsSection::class]`, got `[]`), 'shows an open project on the dashboard' (no `data-room-section="leads"` in the response), and both axe datasets ("No elements found for include", the section did not exist). Semantic assessment: each failed because the section was absent, not from a syntax or fixture fault.
- **RED, Task 2** (`a266135`): 3 of 14 failed on assertions, as the plan predicted: 'a listed source' (page lacked `source Angi`, Task 1 printed `source angi`), 'no source' (lacked `source unknown`), and 'names who opened a project by hand' (lacked `opened by a removed user`). Semantic assessment: all three failed on the planned wording. The other 11 (the 'yelp' dataset, order, open only, two projects of one contact, empty line, the 19/20/21 cap, the business's word, room off, failure line, query count) passed at once, since they prove Task 1's section, as the plan allowed for.
- I did not run the `gsd_run check tdd-red-evidence` classifier: Pest's output is not one of its supported report formats (TAP, JUnit, swift-testing, unittest), so the RED evidence is the Pest JSON failure messages above, assessed by hand.

## Decisions Made

- The 20-row cap with a total line stands as planned (flagged assumption in the plan).
- The lead-source lookup calls `ActiveConfig::leadSource()` through a per-draw memo (`$known[$key] ??=`) rather than reading the whole source list. My first version built the name map from `leadSources()`; I switched so the code matches the plan's acceptance grep (`leadSource(`) and still looks up each key once per draw.

## Deviations from Plan

None - plan executed exactly as written.

The plan's Task 2 RED expectation (which cases would fail first) matched what happened. The tracer feedback gate ran in the default `end-of-phase` mode with an automated-only `<verify>`: I re-ran it after the GREEN commit (48 tests, passed), logged "Tracer verified end-to-end - expanding", and continued.

## Issues Encountered

- A `sed -i` call in macOS's BSD sed failed once while adding two imports to the test file; I made the same edit with a short Python script instead. No effect on the result.
- The first full-suite run exceeded the foreground timeout. I stopped it, then re-ran it after the final code change: 2386 tests passed.

## Laravel Concepts Introduced

- **A widget with its own view:** `LeadsSection` keeps the base class's heading, hiding and failure line by calling `parent::getViewData()` and adds its rows, drawn by its own Blade file, so the shared section view the other rooms use is untouched.
- **Eager loading (the N+1 problem):** `->with(['contact', 'openedByUser'])` makes Eloquent fetch every row's contact and user in one extra query each, instead of one query per row, so the query count is the same for 3 rows and for 10. The walk-through line was added to `docs/sprints/sprint-1.1.md` (plan 07-07).

## Known Stubs

None. The section's wording ("No open jobs.", "source Angi", "opened by ...") is a deliberate placeholder until the Phase 11 wording pass, as the plan states, and is not a stub that blocks the goal.

## Threat Flags

None. The section adds no endpoint, auth path or schema; it reads rows and prints them with escaped `{{ }}` only (T-07-17: the view has zero unescaped echoes). T-07-19 is covered by the 20-row cap, the eager loading and the tested failure line.

## Unrun Human Checks

Recorded as `unrun-verify` entries in `.planning/WINDOWS.md` for the end-of-phase UAT; nothing paused:

1. Keyboard-only and VoiceOver walk of `/admin` with three open projects: each row's contact link is reached by Tab, announced with the contact's name, opens the contact screen, and the opened date is read as a date (D-15, WCAG 2.2 AA).
2. After Ian pushes the phase branch, the CI pgsql leg is green with `LeadsBootTest` (axe included) and `LeadsSectionTest`.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Ready for 07-08 (the automatic project open for a new lead). Rows from an automatic open will already read `source <name>`.
- The section's cap and a business-wide project view are left to Phase 9, as the plan's Deferred note says.

## Self-Check

- Files: `LeadsSection.php`, `leads-section.blade.php`, `LeadsSectionTest.php` exist on disk.
- Commits `120e00b`, `b4d43a9`, `a266135`, `f1e8b78` are on `gsd/phase-07-the-spine-and-leads`.
- Existing-test rule (D-22): `git diff --name-only --diff-filter=M main -- tests` lists only `CardInjectionTest.php`, `FrontDeskBootTest.php` and `tests/Pest.php`; shared `section.blade.php`, `RoomSection.php` and `RoomSectionTest.php` are unchanged against main.
- Plan-level verification: `tests/Feature/Rooms tests/Feature/Filament tests/Unit` run inside the full suite, which passed 2386 of 2386 on SQLite.

## Self-Check: PASSED

---
*Phase: 07-the-spine-and-leads*
*Completed: 2026-10-30*
