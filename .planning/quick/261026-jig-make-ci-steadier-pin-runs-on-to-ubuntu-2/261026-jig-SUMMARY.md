---
phase: quick-261026-jig
plan: 01
subsystem: ci
tags: [github-actions, playwright, ci, blueprint]
requires: []
provides:
  - CI job pinned to ubuntu-24.04 with job and step timeouts
  - Playwright Chromium download cache keyed on the lockfile version
affects: [safety and compliance, knowledge]
key-files:
  modified:
    - .github/workflows/tests.yml
    - docs/BLUEPRINT.md
decisions:
  - "actions/cache pinned to 55cc8345863c7cc4c66a329aec7e433d2d1c52a9 (v6.1.0), resolved at run time with gh and confirmed with git ls-remote"
status: complete
commits: 1
plan_head_before: 767fae863304d2cd461c586e9959b41210e14d32
plan_head_after: eb07487fe924c349b08b975f7ee0d987694e3a96
actuals:
  tokens: 4000
  tasks: 1
  commits: 1
completed: 2026-10-26
---

# Quick 261026-jig: Make CI steadier Summary

CI now runs on `ubuntu-24.04` with a 30-minute job timeout, caches Playwright's Chromium download under a key built from the lockfile's Playwright version (1.63.0 today), and bounds the browser install step at 10 minutes.

## What changed

- `.github/workflows/tests.yml`: `runs-on: ubuntu-24.04`, `timeout-minutes: 30` on job `pest`. New step "Read the Playwright version" (reads `playwright-core` from `package-lock.json`, writes `version` and `image` to the step outputs). New SHA-pinned "Cache Playwright's browser download" step on `~/.cache/ms-playwright`, no restore-keys. "Install the browser for the accessibility check" keeps its name, gets `timeout-minutes: 10`, and runs `npx playwright install-deps chromium` on a cache hit or `npx playwright install --with-deps chromium` on a miss.
- `docs/BLUEPRINT.md`: the runner pin open item in Safety and compliance and its Knowledge pointer are gone; a Built line in Safety and compliance's Where it stands records the close and ends with the Affects line.

Commit: eb07487 (exactly those two files).

## Finding from run 40378515760 (what this does and does not fix)

The browser step's 15m52s went to apt fetching system packages from the Ubuntu mirror (21.4 MB in 15m24s at 23.1 kB/s). The Chromium, FFmpeg and headless shell downloads took about 10 seconds. So the cache saves roughly 10 seconds a leg and removes the dependency on Playwright's CDN, but does not touch the slow part. The 10-minute step timeout is what changes that run: the same stall now fails the leg at 10 minutes and Ian re-runs it. The runner was already Ubuntu 24.04, so the pin changes nothing today; it stops a silent move to Ubuntu 26.

## Plain explanation: the cache key

A cache key is a label GitHub stores a saved folder under. On each run, the workflow asks for a saved folder with exactly that label; if one exists (a hit) the browser files are restored instead of downloaded, and if not (a miss) the install runs and the folder is saved under that label afterward. The label here is built from the runner image, the CPU architecture and the Playwright version read from `package-lock.json`. When Playwright is bumped, the lockfile version changes, so the label changes, nothing matches, and a fresh browser download is saved under the new label. No edit to the workflow is needed.

## Deviations from Plan

None. Plan executed as written.

## Verification

- Every `<automated>` check passed (YAML parses in Python and Ruby, every `uses:` line is SHA-pinned with a version comment, the cache SHA matches the upstream v6.1.0 tag, the lockfile expression prints 1.63.0, BLUEPRINT checks, no app or manifest changes).
- `php artisan test --compact`: 2204 passed, 1 warning (the known browser plugin rmdir warning from Phase 5 close).

## Carried to UAT (not run)

The CI run itself cannot be checked locally. After Ian pushes `gsd/quick-ci-runner`, on the "tests" workflow: both legs pass; "Set up job" names Ubuntu 24.04; first run shows a cache miss and downloads Chromium (the other leg may warn that another job is creating the cache); a re-run shows a hit and the browser step installs only system packages, with no "Downloading Chrome for Testing" line; no step exceeds its timeout. Recorded as an `unrun-verify` entry in `.planning/WINDOWS.md` (uncommitted, for the orchestrator).

## Known Stubs

None.

## Threat Flags

None.

## Self-Check: PASSED

- `.github/workflows/tests.yml` and `docs/BLUEPRINT.md` exist and are in commit eb07487, which is an ancestor of HEAD.
