---
phase: 02-text-front-desk
plan: 05
subsystem: send-rule
tags: [carbon, timezone, dst, business-hours, fixed-replies, pest]

requires:
  - phase: 02-text-front-desk
    provides: ActiveConfig hours(), timezone(), business(), replies(); owner-written holding_reply and handoff_reply (plans 02-03, 02-04)
provides:
  - "App\\Icm\\BusinessClock: isOpenAt(), nextOpening() and nextOpenPhrase(), all computed on the business time zone's wall clock"
  - "App\\SendRule\\FixedReplies: holdingReply() and handoffReply() with {owner_name}, {business_name} and {next_open} filled by code"
  - "config/gate.php with next_open.today_margin_minutes (60), the home for later send-rule numbers"
affects: [02-14 send path (disclosure and sign-off), 02-17 holding reply, 02-20 handoff reply]

actuals:
  tokens: 3500
  tasks: 1
  commits: 2

tech-stack:
  added: []
  patterns:
    - "Time arithmetic in the business zone with CarbonImmutable, never UTC"
    - "Fixed-reply fill-ins rendered with strtr from one array so a value containing braces is never re-expanded"

key-files:
  created:
    - config/gate.php
    - app/Icm/BusinessClock.php
    - app/SendRule/FixedReplies.php
    - tests/Feature/Icm/BusinessClockTest.php
    - tests/Feature/SendRule/FixedRepliesTest.php
  modified:
    - docs/sprints/sprint-1.md

key-decisions:
  - "{next_open} says 'today' only when moment + margin <= closing time, so exactly 60 minutes left reads 'today' and 59 does not (D-51)"
  - "A closure date closes the whole local day; closing time is exclusive, opening time is inclusive"
  - "nextOpening() scans today plus 13 days; a longer shutdown renders 'when we reopen'"

patterns-established:
  - "BusinessClock converts every incoming moment to ActiveConfig::timezone() first, so callers may pass UTC or any zone"
  - "Send-rule numbers live in config/gate.php, not in the business folder"

requirements-completed: [GATE-01, GATE-02]

coverage:
  - id: D1
    description: "BusinessClock computes the next opening from hours.md in the business zone, skipping closed days and closures, correct across the DST change (2026-10-31 14:00 CDT -> 2026-11-02 08:00 CST = 14:00 UTC)"
    requirement: GATE-01
    verification:
      - kind: unit
        ref: "tests/Feature/Icm/BusinessClockTest.php#finds the next opening in the business time zone across the end of daylight saving time"
        status: pass
    human_judgment: false
  - id: D2
    description: "{next_open} reads 'today' only with at least 60 minutes before close; exactly 60 reads today, 59 does not; otherwise names the next opening with am/pm, minutes only when present, and today/tomorrow/weekday"
    requirement: GATE-01
    verification:
      - kind: unit
        ref: "tests/Feature/Icm/BusinessClockTest.php#says today while the shop is open with at least an hour left"
        status: pass
    human_judgment: false
  - id: D3
    description: "FixedReplies renders holding and handoff replies with owner name, business name and next opening filled by code"
    requirement: GATE-02
    verification:
      - kind: unit
        ref: "tests/Feature/SendRule/FixedRepliesTest.php"
        status: pass
    human_judgment: false

duration: 12min
completed: 2026-10-11
status: complete
plan_head_before: c3d213193f28c28638bb395f116ac7096c97745f
plan_head_after: 1b901bd0d92d0a3de23b093d9be24c61ed7ba4a6
---

# Phase 2 Plan 05: Business Clock and Fixed Replies Summary

**Business-hours clock (open now, next opening, `{next_open}` phrase) working in the business time zone with closures, DST and a config-driven one-hour "today" margin, plus holding and handoff replies rendered with it**

## Performance

- **Duration:** about 12 min
- **Completed:** 2026-10-11
- **Tasks:** 1 (tracer, TDD: 2 commits)
- **Files modified:** 6 (5 created, 1 modified)

## Accomplishments
- `BusinessClock` answers `isOpenAt()`, `nextOpening()` and `nextOpenPhrase()` from `ActiveConfig::hours()` on the business zone's wall clock; closure dates close the whole local day, close time is exclusive.
- The DST weekend is proven: Sat 2026-10-31 14:00 CDT gives Mon 2026-11-02 08:00 America/Chicago, which is 14:00 UTC, and the phrase still says "8 am Monday".
- The D-51 margin is exact and read from `config/gate.php` (`next_open.today_margin_minutes` = 60): 16:00 on a 17:00 close reads "today", 16:01 reads "when we open at 8 am tomorrow".
- `FixedReplies` fills `{owner_name}`, `{business_name}` and `{next_open}` so the time a holding or handoff text promises is computed, not written by hand or by a model.

## Task Commits

1. **Task 1 RED: failing business clock and fixed reply tests** - `02bdf4c` (test)
2. **Task 1 GREEN: business clock and fixed reply rendering** - `1b901bd` (feat)

No REFACTOR commit: nothing needed cleaning after GREEN.

**Plan metadata:** committed with this SUMMARY (docs: complete plan)

## TDD Gate Compliance

- **RED:** 20 tests in `BusinessClockTest` and `FixedRepliesTest` all failed with `Target class [App\Icm\BusinessClock] does not exist` / `[App\SendRule\FixedReplies] does not exist`. Semantic assessment: for a brand-new class the missing class is the planned failure, and every test reached its first line (the call into the new class) before failing; no syntax, fixture or collection errors. Pest's JSON summary is not a format `gsd check tdd-red-evidence` supports, so RED was judged by inspecting the real output rather than machine-classified.
- **GREEN:** the same 20 tests pass (50 assertions); full suite 415 passed.
- **REFACTOR:** not needed.

## Files Created/Modified
- `config/gate.php` - send-rule numbers kept in code; starts with `next_open.today_margin_minutes`
- `app/Icm/BusinessClock.php` - open/next-opening/phrase logic in the business zone
- `app/SendRule/FixedReplies.php` - holding and handoff reply rendering
- `tests/Feature/Icm/BusinessClockTest.php` - 12 cases including DST, Thanksgiving closure, 60/59-minute edge, 08:30 and pm openings, all-closed folder, config margin
- `tests/Feature/SendRule/FixedRepliesTest.php` - holding, handoff, `{business_name}` and weekend wording
- `docs/sprints/sprint-1.md` - walk-through log line on Carbon time zones, `travelTo()` and DST

## Decisions Made
- "Today" test is `moment + margin <= close`, so the boundary minute is inclusive and a half-minute short is not "today".
- Replies use `strtr` over one array of fill-ins so a business name containing braces cannot be expanded a second time.
- Added tests beyond the plan's list (afternoon `pm` opening, a UTC moment converted to the business zone, margin read from config, strictly-after semantics) to pin behavior the plan implies.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
None.

## Known Stubs
None.

## Threat Flags
None. T-02-09 (a promised time that is not true) is mitigated as planned: `{next_open}` is computed in the business zone with closures, the DST weekend and the exact 60-minute margin all under test.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Plans 02-14 (send path adds disclosure and sign-off), 02-17 (holding reply) and 02-20 (handoff reply) can call `FixedReplies::holdingReply($moment)` and `handoffReply($moment)`.
- `config/gate.php` exists for the classifier, alert and cap numbers later plans add.

## Self-Check: PASSED

- Created files exist: `config/gate.php`, `app/Icm/BusinessClock.php`, `app/SendRule/FixedReplies.php`, both test files.
- Commits `02bdf4c` and `1b901bd` are on `gsd/phase-02-text-front-desk`.
- `php artisan test --compact tests/Feature/Icm/BusinessClockTest.php tests/Feature/SendRule/FixedRepliesTest.php` passes (20 tests); full suite passes (415 tests).

---
*Phase: 02-text-front-desk*
*Completed: 2026-10-11*
