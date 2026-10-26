---
phase: quick-261026-lrs
plan: 01
subsystem: ci
tags: [ci, github-actions, playwright, blueprint]
requires: [quick-261026-jig]
provides: ["CI browser step installs Chrome for Testing only; no system-package install"]
affects: [safety and compliance, knowledge]
key-files:
  modified:
    - .github/workflows/tests.yml
    - docs/BLUEPRINT.md
key-decisions:
  - "Apt does not come back to the workflow; missing libraries would be listed for Ian"
metrics:
  tasks: 1
  completed: 2026-10-26
status: complete
commits: 1
plan_head_before: 0c226f8c020e95646ac2f476618235e924187975
plan_head_after: f9401bc37f0de95f8bd82342a58d705caaafafbb
actuals:
  tokens: 4000
  tasks: 1
  commits: 1
---

# Phase quick-261026-lrs Plan 01: Stop running apt in CI Summary

The CI step "Install the browser for the accessibility check" now runs only `npx playwright install chromium` (10-minute timeout kept), and the BLUEPRINT apt open item is closed with a Built line.

## What changed

- `.github/workflows/tests.yml`: removed the `env` block and the if/else script from the browser step; it is now a one-line `run`. Every other line matches `0c226f8` (diff: 1 line added, 8 removed). No apt, install-deps or with-deps anywhere in the file.
- `docs/BLUEPRINT.md` (Safety and compliance): deleted the "apt can stall" open item, corrected the runner-pin Built line to say the packages were installed until 261026-lrs, and added the Built line "CI installs no system packages" directly after it (runs 40378515760 and 40387844777, host azure.archive.ubuntu.com, fonts finding, Affects line).
- Commit `f9401bc` on `gsd/quick-ci-runner` holds exactly those two files. Nothing pushed.

## Evidence (from the orchestrator, recorded)

1. Job 121816813348, attempt 2 of run 40387844777, cache hit: apt's index refresh was fast, then 25.9 MB of packages crawled from azure.archive.ubuntu.com (fonts-wqy-zenhei about 3 minutes) and the step hit its 10-minute limit fetching xvfb. The plan was 8 upgraded, 9 newly installed; the new ones were all fonts (freefont, ipafont-gothic, tlwg-loma-otf, unifont, wqy-zenhei, xfonts-*). Every library Chromium needs was already installed, with only newer versions on offer.
2. Font check: no test takes screenshots, measures text or names a font. `grep -rliE 'screenshot|measureText|toMatchSnapshot|getBoundingClientRect|font' tests/` returned nothing again at execution time.
3. First stall: run 40378515760, 15m24s at 23.1 kB/s (quick task 261026-jig).

## Plain explanation for Ian

The removed command asked Ubuntu's package manager to install the Linux libraries and fonts Chromium can use, from Ubuntu's mirror, on every run. The `ubuntu-24.04` image already has those libraries because it ships Google Chrome and Chromium, which need the same ones. Only fonts (Chinese, Japanese, Thai, Cyrillic and similar) were being added, and no test depends on them. If a library is ever missing, Chromium fails to start and Playwright names the missing libraries in the test log; then they get listed for you rather than adding apt back.

## Verification

- All `<automated>` checks passed before the commit (workflow comparison against `0c226f8`, YAML parse in Python and Ruby, no apt/install-deps/with-deps/BROWSER_CACHED, numstat 1/8 and 2/2, font grep empty, BLUEPRINT line order and content).
- `php artisan test --compact`: 2204 passed, 1 warning (the known browser-plugin rmdir warning).
- After commit: `git show --name-only --format= HEAD` lists exactly the two files.

## Carried human check (not run; for UAT)

After Ian pushes `gsd/quick-ci-runner`, on GitHub: (1) both legs, sqlite and pgsql, pass; (2) the browser step shows no package-manager output and finishes in seconds on a cache hit, downloading only Chrome for Testing on a miss; (3) "Run the tests" passes the room boot and accessibility tests with no "Host system is missing dependencies" message; (4) if libraries are named, do not add apt back: list them in Safety and compliance's open items for Ian. Recorded as an `unrun-verify` entry in `.planning/WINDOWS.md` (file `.github/workflows/tests.yml`); it replaces check (4) of row 25 (quick-261026-jig).

## Deviations from Plan

None - plan executed exactly as written.

## Known Stubs

None.

## Threat Flags

None. This change removes a root-level network package install and adds no new surface.

## Self-Check: PASSED

- FOUND: .github/workflows/tests.yml, docs/BLUEPRINT.md
- FOUND commit f9401bc (ancestor of HEAD)
