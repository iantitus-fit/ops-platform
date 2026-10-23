---
phase: 04-room-standard
plan: 09
subsystem: testing
tags: [axe, wcag22, pest-browser, playwright, accessibility, rooms]

requires:
  - phase: 04-room-standard
    provides: "Plan 04-06: the room section view with the data-room-section wrapper and the D-21 empty line; the two-row pretend room boot test"
  - phase: 04-room-standard
    provides: "Plans 04-07 and 04-08: pestphp/pest-plugin-browser 5.1.2, playwright 1.63.0 and Chromium (local and CI)"
provides:
  - "Tests\\Rooms\\RoomSectionAxe: report(page, roomKey) and assertClean(report), the one reusable axe check every room's boot test calls on its own section"
  - "RoomAccessibilityTest: the pretend room's section passes axe (wcag2a, wcag2aa, wcag21a, wcag21aa, wcag22aa, target-size on) as the D-21 empty line and furnished, and an injected alt-less image is reported as image-alt"
  - "The spike answer: actingAs() carries into the browser test, so no login-form sign-in is needed"
affects: [04-room-standard phase verification, every real room's boot test from Phase 5, Phase 11 front desk and team rooms]

plan_head_before: 04f0d23e9122e4fc0886ac8c1a097899a2fee5fb
plan_head_after: 82d7c9baff6065f3f3eadb53c5150330ed40aace
commits: 2

actuals:
  tokens: 1400
  tasks: 2
  commits: 2

tech-stack:
  added: []
  patterns: ["Axe run through the Pest browser plugin's page->script(), scoped with an include context to the [data-room-section] element", "A check proves it has teeth: checked > 0 plus a deliberately injected violation that must be reported"]

key-files:
  created: [tests/Rooms/RoomSectionAxe.php, tests/Feature/Rooms/RoomAccessibilityTest.php]
  modified: [docs/sprints/sprint-1.1.md]

key-decisions:
  - "Signing the browser in: $this->actingAs($user) works as is under the Pest browser plugin (the in-process server carries the test's session cookie), so the real-login-form fallback was not needed"
  - "assertClean() fails with the JSON of the violations, so a failing run names each rule id and its targets"

patterns-established:
  - "Each room's boot test calls RoomSectionAxe::assertClean(RoomSectionAxe::report($page, '<room key>')) on its own section (D-27)"

requirements-completed: [A11Y-01]

coverage:
  - id: D1
    description: "A signed-in /admin loads in the Pest browser test (actingAs) and axe runs on the pretend room's section"
    requirement: "A11Y-01"
    verification:
      - kind: automated_ui
        ref: "tests/Feature/Rooms/RoomAccessibilityTest.php#finds no accessibility violation in the pretend section while it shows the empty line"
        status: pass
    human_judgment: false
  - id: D2
    description: "The pretend room's section passes axe with the five WCAG 2.2 AA tags and target-size on, both as the D-21 empty line and furnished"
    requirement: "A11Y-01"
    verification:
      - kind: automated_ui
        ref: "tests/Feature/Rooms/RoomAccessibilityTest.php#finds no accessibility violation in the pretend section while it shows the empty line"
        status: pass
      - kind: automated_ui
        ref: "tests/Feature/Rooms/RoomAccessibilityTest.php#finds no accessibility violation in the furnished pretend section"
        status: pass
    human_judgment: false
  - id: D3
    description: "The check cannot pass vacuously: zero evaluated rules fails, and an injected image without alt text is reported as image-alt with a message that names the rule id"
    requirement: "A11Y-01"
    verification:
      - kind: automated_ui
        ref: "tests/Feature/Rooms/RoomAccessibilityTest.php#reports an image without alt text injected into the section, so the check is not vacuous"
        status: pass
    human_judgment: false
  - id: D4
    description: "RoomSectionAxe is one reusable helper for every room's boot test"
    requirement: "A11Y-01"
    verification:
      - kind: other
        ref: "grep -q wcag22aa tests/Rooms/RoomSectionAxe.php && grep -q target-size tests/Rooms/RoomSectionAxe.php"
        status: pass
    human_judgment: false
  - id: D5
    description: "The tests workflow on GitHub is green on both the sqlite and pgsql legs, including PretendRoomBootTest and RoomAccessibilityTest"
    requirement: "A11Y-01"
    verification: []
    human_judgment: true
    rationale: "A GitHub Actions run happens only after Ian pushes the phase branch; nothing in this plan can trigger it. Recorded in .planning/WINDOWS.md as an unrun-verify entry for end-of-phase UAT"

duration: 4min
completed: 2026-10-22
status: complete
---

# Phase 4 Plan 09: Room Accessibility Check Summary

**axe with the WCAG 2.2 AA tags and target-size on, run in headless Chromium through the Pest browser plugin and scoped to a room's `[data-room-section]`, proven on the pretend room (empty line and furnished) with a reusable `RoomSectionAxe` helper and an injected-violation test showing the check catches `image-alt`**

## Performance

- **Duration:** 4 min
- **Started:** 2026-10-23T01:41:08Z
- **Completed:** 2026-10-23T01:45:30Z
- **Tasks:** 2 (1 tracer, 1 auto)
- **Files modified:** 3

## Accomplishments

- The spike settled the riskiest unknown on the first run: `$this->actingAs(User::factory()->create())` followed by `visit('/admin')` lands on the signed-in dashboard (the page shows `Pretend: nothing set up yet.`) and `window.axe` runs. The login-form fallback was not needed, and the PHPDoc on the test file says so.
- `Tests\Rooms\RoomSectionAxe` is the one reusable check. `report($page, $roomKey)` runs axe with context `{include: [['[data-room-section="<key>"]']]}`, `runOnly` tags `wcag2a, wcag2aa, wcag21a, wcag21aa, wcag22aa` and `target-size` forced on, and returns `checked` plus the violations (id, impact, help, targets). `assertClean()` fails when `checked` is 0 or any violation exists, and its failure message is the JSON of the violations.
- The pretend room's section is clean in both states: the D-21 empty line, and furnished (whole fixture plus one `ReceivePing`, so the content lines show). No markup change was needed in `resources/views/filament/rooms/section.blade.php`.
- The check has teeth: a test injects `<img src="/missing.png">` with no alt into the section, expects `image-alt` among the reported ids, and expects `assertClean()` to fail with a message naming `image-alt`.
- No database-driver guard and no skip, so the three tests run on both CI legs.

## Task Commits

1. **Task 1: Tracer spike, a signed-in /admin loads and axe runs on the pretend section** - `1d23cf0` (test)
2. **Task 2: The room standard's accessibility check** - `82d7c9b` (test)

**Plan metadata:** the `docs(04-09)` commits that add this SUMMARY and the state, roadmap and requirements updates.

## Files Created/Modified

- `tests/Rooms/RoomSectionAxe.php` - the reusable axe run scoped to one room's section, with `report()` and `assertClean()`
- `tests/Feature/Rooms/RoomAccessibilityTest.php` - empty-state, furnished-state and injected-violation tests
- `docs/sprints/sprint-1.1.md` - one walk-through line (plan 04-09) explaining a browser test

## Decisions Made

- actingAs() is the way the browser is signed in (see the spike result above); the real login form at `/admin/login` stays the documented fallback only if a later Pest browser plugin version stops carrying the session cookie.
- `assertClean()` calls `expect(...)->toBeEmpty($message)` with the violations as JSON, so the failure text lists each rule id and its targets without any extra code.

## Deviations from Plan

None - plan executed exactly as written.

One in-flight correction, not a deviation: the first run of the injected-violation test used `toThrow(Throwable::class, 'image-alt')`, which Pest read as a message match because `Throwable` is an interface. It now names `PHPUnit\Framework\ExpectationFailedException`. Fixed before the Task 2 commit.

## Issues Encountered

None. Every test run reports `"warnings":1`; that is the PHPUnit runner warning "Options --no-output and --testdox cannot be used together" from this environment's Pest wrapper, present before this plan too, and not caused by these tests.

## Unrun Verifications

The plan's `<human-check>` (CI green on both the sqlite and pgsql legs after Ian pushes the branch) was not run, as the standing rule says. It is recorded as an `unrun-verify` entry in `.planning/WINDOWS.md` and as coverage item D5 (human judgment) for end-of-phase UAT.

## Authentication Gates

None.

## User Setup Required

None - no external service configuration required.

## Threat Flags

None. The only new surface is test-only: an axe script injected by the test runner into pages it loads (T-04-30, accepted). Nothing is added to the product's pages, and no package was added (T-04-SC).

## Next Phase Readiness

- Plans 04-01 to 04-09 all have SUMMARYs. The phase is ready for verification, with the CI check (D5) and the rest of Ian's end-of-phase UAT outstanding.
- Phase 5 onward: each real room's boot test adds `RoomSectionAxe::assertClean(RoomSectionAxe::report($page, '<room key>'))` on its own section. The front desk and team rooms join in Phase 11 (D-27, 02.1 D-48).

## Self-Check: PASSED

- `tests/Rooms/RoomSectionAxe.php`, `tests/Feature/Rooms/RoomAccessibilityTest.php` and `docs/sprints/sprint-1.1.md` exist with the changes above.
- Commits `1d23cf0` and `82d7c9b` are ancestors of HEAD.
- `php artisan test --compact tests/Feature/Rooms` passed (182 tests); the whole suite passed (1956 tests, up from 1953).
- Acceptance greps: `wcag22aa` and `target-size` in the helper succeed, `getDriverName` in the test file is absent, `(plan 04-09)` in the sprint file succeeds.
- `git rev-list --count 04f0d23..HEAD` was 2 at SUMMARY time, matching `commits: 2`.

---
*Phase: 04-room-standard*
*Completed: 2026-10-22*
