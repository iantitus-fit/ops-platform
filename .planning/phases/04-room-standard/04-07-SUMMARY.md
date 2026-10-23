---
phase: 04-room-standard
plan: 07
subsystem: testing
tags: [pest, pest-plugin-browser, composer, ci, accessibility, amphp]

requires:
  - phase: 04-room-standard
    provides: "Plan 04-01 room standard tracer (suite at 1781 tests on SQLite)"
provides:
  - "pestphp/pest-plugin-browser ^5.1 (locked v5.1.2) as a Composer dev dependency, approved by Ian at its own blocking checkpoint"
  - ".gitignore entries for tests/Browser/Screenshots and tests/Browser/Traces"
  - "ext-sockets in CI's setup-php extensions, so both database legs can install the plugin"
affects: [04-08 npm package and Chromium download, accessibility check A11Y-01, CI workflow]

plan_head_before: ba164ab77ef1575b5c816c87cb05382205aa9756
plan_head_after: 3fea0d8a8bdc1dde44735dfbd3f5b9ba74b8dc6f
commits: 1

actuals:
  tokens: 13936
  tasks: 2
  commits: 1

tech-stack:
  added: [pestphp/pest-plugin-browser ^5.1, amphp/amp, amphp/byte-stream, amphp/cache, amphp/dns, amphp/hpack, amphp/http, amphp/http-client, amphp/http-server, amphp/parser, amphp/pipeline, amphp/process, amphp/serialization, amphp/socket, amphp/sync, amphp/websocket, amphp/websocket-client, daverandom/libdns, kelunik/certificate, revolt/event-loop]
  patterns: ["Test-runner-only dependencies live in require-dev and never in require"]

key-files:
  created: []
  modified: [composer.json, composer.lock, .gitignore, .github/workflows/tests.yml]

key-decisions:
  - "Installed exactly the constraint Ian approved (^5.1, --dev only); Composer resolved v5.1.2 with 20 new packages, 0 updates, 0 removals and no advisories"
  - "Did not install Playwright or Chromium; that stays in Plan 04-08 and needs its own approval"

requirements-completed: [A11Y-01]

coverage:
  - id: D1
    description: "pestphp/pest-plugin-browser ^5.1 is a require-dev dependency (locked at v5.1.2), installed only after Ian's approval"
    requirement: A11Y-01
    verification:
      - kind: other
        ref: "composer show pestphp/pest-plugin-browser (versions : * v5.1.2); grep of composer.json shows the entry under require-dev only"
        status: pass
    human_judgment: false
  - id: D2
    description: "Browser plugin output folders are ignored by git"
    verification:
      - kind: other
        ref: "grep -qx 'tests/Browser/Screenshots' .gitignore && grep -qx 'tests/Browser/Traces' .gitignore"
        status: pass
    human_judgment: false
  - id: D3
    description: "CI's Set up PHP step includes the sockets extension, with no step limiting tests to one database leg"
    verification:
      - kind: other
        ref: "Symfony Yaml parse of .github/workflows/tests.yml finds sockets in the Set up PHP extensions (exit 0)"
        status: pass
    human_judgment: true
    rationale: "The workflow change cannot run until the branch is pushed and CI runs on both the sqlite and pgsql legs; Ian pushes and reviews."
  - id: D4
    description: "The existing suite still passes with the plugin installed"
    verification:
      - kind: integration
        ref: "php artisan test --compact: 1781 passed, 15236 assertions"
        status: pass
    human_judgment: false

duration: 3min
completed: 2026-10-22
status: complete
---

# Phase 4 Plan 07: Pest Browser Plugin Summary

**The Pest browser plugin (`pestphp/pest-plugin-browser` ^5.1, locked v5.1.2) is installed as a dev-only dependency after Ian's explicit approval, its screenshot and trace folders are git-ignored, and CI's PHP gains the `sockets` extension.**

## Performance

- **Duration:** 3 min for this continuation (Task 2 and close-out); Task 1 was the earlier executor's read-only checks and Ian's reply
- **Started:** 2026-10-22T21:57:05Z (continuation)
- **Completed:** 2026-10-22T21:59:31Z (task commit), SUMMARY follows
- **Tasks:** 2 (Task 1 checkpoint approved, Task 2 committed)
- **Files modified:** 4

## Task 1 Approval Record

Task 1 (`checkpoint:human-verify`, `gate="blocking-human"`) stopped the plan before any install. The earlier executor ran only read-only checks: `composer show -a` reported v5.1.2 from github.com/pestphp/pest-plugin-browser (MIT), and `composer require --dev "pestphp/pest-plugin-browser:^5.1" --dry-run --no-scripts` resolved with no conflict (20 new packages, 0 updates, 0 removals, no advisories). `composer why-not` could not run because the package was not yet in the project.

**Ian's reply: "approved", given 2026-10-22 (UTC), in reply to the checkpoint.** Nothing was installed before that reply.

## Accomplishments

- Installed `pestphp/pest-plugin-browser` `^5.1` under `require-dev` only. The lock resolves v5.1.2 plus 19 dependencies (amphp/amp, byte-stream, cache, dns, hpack, http, http-client, http-server, parser, pipeline, process, serialization, socket, sync, websocket, websocket-client; daverandom/libdns; kelunik/certificate; revolt/event-loop). No existing package changed version.
- Added `tests/Browser/Screenshots` and `tests/Browser/Traces` to `.gitignore` under a one-line comment.
- Added `sockets` to the "Set up PHP" extensions in `.github/workflows/tests.yml`. The matrix still runs `sqlite` and `pgsql`, and no step or condition limits tests to one database leg.
- Full suite passes with the plugin installed: 1781 tests, 15236 assertions. Playwright's absence did not fail anything.

## Task Commits

1. **Task 1: Ian approves the Composer dev package** - no commit (approval only; reply recorded above)
2. **Task 2: Install the plugin, ignore its output folders, give CI sockets** - `3fea0d8` (chore)

**Plan metadata:** committed separately after this file (docs: complete plan)

## Files Created/Modified

- `composer.json` - `pestphp/pest-plugin-browser: ^5.1` under require-dev
- `composer.lock` - 20 new locked packages, content-hash updated
- `.gitignore` - browser plugin output folders
- `.github/workflows/tests.yml` - `sockets` added to the PHP extensions list

## Decisions Made

- Followed the plan as specified: exact constraint `^5.1`, `--dev`, no other package, no Playwright or Chromium (Plan 04-08).

## Deviations from Plan

None - plan executed exactly as written.

`composer require` ran the project's post-autoload-dump scripts (`package:discover`, `filament:upgrade`). `filament:upgrade` republished Filament assets, but the files are identical to what is tracked, so `git status` showed only the four planned files changed.

## Issues Encountered

None. The tracer-style verify ran end to end: `composer show` reports v5.1.2, the workflow YAML parses and the PHP step lists sockets, and `php artisan test --compact` passed.

## Known Stubs

None.

## Threat Flags

None. The new package surface (T-04-SC, T-04-24, T-04-25) is exactly what the plan's threat model covers: approved at the blocking checkpoint, locked by composer.lock, dev dependency only.

## User Setup Required

None - no external service configuration required. The CI `sockets` change takes effect when Ian pushes the branch; it has not run on GitHub yet, so the first CI run on both database legs is the real check of that line.

## Next Phase Readiness

- Plan 04-08 can add the npm package and the Chromium download. The plugin cannot drive a browser until then.

## Self-Check: PASSED

- composer.json, composer.lock, .gitignore, .github/workflows/tests.yml: present and changed in `3fea0d8`.
- Commit `3fea0d8` is an ancestor of HEAD.
- All Task 2 acceptance criteria re-run and pass (composer.json entry under require-dev, both .gitignore lines, CI extension check, suite exit 0).

---
*Phase: 04-room-standard*
*Completed: 2026-10-22*
