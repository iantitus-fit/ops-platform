---
phase: 04-room-standard
plan: 08
subsystem: testing
tags: [playwright, npm, ci, github-actions, chromium, accessibility]

requires:
  - phase: 04-room-standard
    provides: "Plan 04-07: pestphp/pest-plugin-browser and ext-sockets in CI"
provides:
  - "npm dev dependency playwright pinned to exactly 1.63.0 (package.json and package-lock.json), approved by Ian at its own blocking checkpoint"
  - "CI steps Set up Node (actions/setup-node pinned by SHA, v7.1.0, node 24, npm cache), Install Node packages (npm ci) and Install the browser for the accessibility check (npx playwright install --with-deps chromium), before the tests on both the sqlite and pgsql legs"
  - "Playwright's Chromium (revision 1243) on Ian's Mac, installed by Ian"
affects: [04-room-standard accessibility check A11Y-01, CI workflow]

plan_head_before: 5e8917c06d0b28865943f3def731b31f534e8568
plan_head_after: c9c845764d5cbf31d80b50e1e61b805e8f30c454
commits: 1

actuals:
  tokens: 733
  tasks: 3
  commits: 1

tech-stack:
  added: [playwright 1.63.0, playwright-core 1.63.0, actions/setup-node v7.1.0]
  patterns: ["Browser-test driver pinned to an exact version, never a range", "Every GitHub Action pinned by full commit SHA with the tag as a trailing comment"]

key-files:
  created: []
  modified: [package.json, package-lock.json, .github/workflows/tests.yml]

key-decisions:
  - "playwright is pinned to exactly 1.63.0 with --save-exact; the days-old 1.64.x is avoided (Ian's decision 2, D-27)"
  - "The three CI steps carry no if: condition and no database guard, so they run on both matrix legs"
  - "Ian ran the Chromium download himself; the executor never ran npx playwright install"

patterns-established:
  - "Dependency approval gates: a new package enters the repo only after Ian's reply at its own blocking-human checkpoint, recorded in the SUMMARY with the date"

requirements-completed: [A11Y-01]

coverage:
  - id: D1
    description: "playwright is pinned to exactly 1.63.0 as a dev dependency and node_modules holds that version"
    requirement: "A11Y-01"
    verification:
      - kind: other
        ref: "node -e \"const p = require('./package.json'); process.exit(p.devDependencies && p.devDependencies.playwright === '1.63.0' ? 0 : 1)\""
        status: pass
      - kind: other
        ref: "node -e \"const v = require('./node_modules/playwright/package.json').version; process.exit(v === '1.63.0' ? 0 : 1)\""
        status: pass
    human_judgment: false
  - id: D2
    description: "CI sets up Node (SHA-pinned), runs npm ci and installs Chromium before the tests on both database legs, with no if: condition"
    requirement: "A11Y-01"
    verification:
      - kind: other
        ref: "php -r Symfony Yaml parse of .github/workflows/tests.yml (step order, 40-character SHA, no if on Set up Node)"
        status: pass
    human_judgment: false
  - id: D3
    description: "Chromium for Playwright is installed on Ian's Mac and matches playwright 1.63.0"
    requirement: "A11Y-01"
    verification:
      - kind: other
        ref: "npx playwright --version prints Version 1.63.0"
        status: pass
      - kind: other
        ref: "node -e \"const { chromium } = require('playwright'); process.exit(require('fs').existsSync(chromium.executablePath()) ? 0 : 1)\""
        status: pass
    human_judgment: false
  - id: D4
    description: "CI run on GitHub with the new Node, npm ci and Chromium steps on the sqlite and pgsql legs"
    requirement: "A11Y-01"
    verification: []
    human_judgment: true
    rationale: "The workflow was parsed and checked locally, but a real GitHub Actions run happens only after Ian pushes the branch; nothing in this plan can trigger it"

duration: 154min
completed: 2026-10-22
status: complete
---

# Phase 4 Plan 08: Playwright 1.63.0 and Chromium Summary

**npm dev package playwright pinned to exactly 1.63.0 after Ian's approval, CI installs Node, npm ci and Chromium before the tests on both database legs, and Ian's Mac has Playwright's matching Chromium**

## Performance

- **Duration:** 154 min wall clock, almost all of it waiting on Ian's two checkpoints
- **Started:** 2026-10-22T17:13:46-05:00 (ledger written at the first task commit)
- **Completed:** 2026-10-22T19:48:13-05:00
- **Tasks:** 3 (1 approval checkpoint, 1 tracer, 1 human-action checkpoint)
- **Files modified:** 3

## Accomplishments

- playwright 1.63.0 (and its same-version playwright-core) installed with `--save-exact`; package.json reads `"playwright": "1.63.0"`.
- Three CI steps added to `.github/workflows/tests.yml` between the Composer install and "Run the tests", on every matrix leg.
- Ian ran `npx playwright install chromium` himself; both verification checks pass (below).

## Ian's approval (Task 1)

Ian approved the npm dev package on 2026-10-22, verbatim:

> approved. Note: playwright pulls playwright-core at the same exact version (and possibly fsevents as an optional on macOS); that's expected. Report the package-lock.json additions in the summary so I can see exactly what came in.

Nothing was installed before this reply.

## package-lock.json additions (commit c9c8457)

`git show c9c8457 -- package-lock.json`: 30 lines added, 0 removed. fsevents was not added (it is already in the lockfile, and playwright 1.63.0 does not pull it). Every addition:

| Entry | Name | Version | Dev / optional | License | Integrity |
|---|---|---|---|---|---|
| root `devDependencies` | playwright | `1.63.0` (exact, no caret) | dev | n/a | n/a |
| `node_modules/playwright` | playwright | 1.63.0 | dev, not optional | Apache-2.0 | `sha512-+7ziBLidS4NaNCdt57SUDT+wYmmd5fmiQejUic/kb+YsYSCPyOOE9sebzMjNmQrsnNpDJqd4WHvV/8lfKfUDUg==` |
| `node_modules/playwright-core` | playwright-core | 1.63.0 | dev, not optional | Apache-2.0 | `sha512-rYCsBF/M5HjUch52bbtVONEFjv6Xu8sm8h72dNlR5bzIE1fvC/bxgspzkjSfU+MweEMmPM8KJebG6nnyxo5mCg==` |

Both packages resolve from `https://registry.npmjs.org/`, require node >=20, and `playwright` depends only on `playwright-core` at the same exact version. package.json gained one line, the playwright devDependency.

## CI changes (commit c9c8457)

Added after "Install dependencies" and before "Run the tests", with no `if:`:

- **Set up Node** - `actions/setup-node@949feb2413d6458794dcd2491c4babbbce0c15c1 # v7.1.0`, `node-version: '24'`, `cache: npm`
- **Install Node packages** - `npm ci`
- **Install the browser for the accessibility check** - `npx playwright install --with-deps chromium`

The setup-node SHA matched the plan's expected value (confirmed read-only with `gh api` by the first executor).

## Chromium on Ian's Mac (Task 3)

Ian ran `npx playwright install chromium` from the project root on 2026-10-22. It printed nothing because the browser was already in the cache: playwright-core 1.63.0 wants Chromium revision 1243, and `~/Library/Caches/ms-playwright/chromium-1243` and `chromium_headless_shell-1243` already existed (dated Sep 9), so there was nothing to download. Verified by the continuation executor, from the project root:

- `npx playwright --version` printed `Version 1.63.0` (exit 0).
- `chromium.executablePath()` exists (exit 0). Path: `/Users/iantitus/Library/Caches/ms-playwright/chromium-1243/chrome-mac-arm64/Google Chrome for Testing.app/Contents/MacOS/Google Chrome for Testing`

The executor did not run any `npx playwright install` and installed nothing in the cache.

## Task Commits

1. **Task 1: Ian approves the npm dev package playwright, pinned to exactly 1.63.0** - no commit (approval only)
2. **Task 2: Pin playwright 1.63.0 and install Chromium in CI** - `c9c8457` (chore)
3. **Task 3: Ian installs Chromium for Playwright on his Mac** - no commit (action outside the repo)

**Plan metadata:** the `docs(04-08)` commit that adds this SUMMARY, STATE.md, ROADMAP.md and REQUIREMENTS.md.

## Files Created/Modified

- `package.json` - playwright 1.63.0 under devDependencies
- `package-lock.json` - the additions in the table above
- `.github/workflows/tests.yml` - Set up Node, Install Node packages, Install the browser for the accessibility check

## Decisions Made

None beyond the plan: exact pin, no range, CI steps on both legs, Chromium download by Ian.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] npm drops the lockfile entry node_modules/react 19.3.0 on any install**
- **Found during:** Task 2 (npm install --save-dev --save-exact playwright@1.63.0)
- **Issue:** On this machine (npm 11.6.2), any `npm install` removes the existing lockfile entry `node_modules/react` 19.3.0, an optional peer that is not installed. This is independent of playwright; the first executor reproduced it on a scratch copy. Left alone, the lockfile commit would have carried an unrelated deletion.
- **Fix:** The first executor restored the entry by hand so the commit is additions-only (30 added, 0 removed). `npm ci --dry-run` accepts the lockfile.
- **Files modified:** package-lock.json
- **Verification:** `git show c9c8457 -- package-lock.json` shows only additions; the `node_modules/react` entry is still at line 1591 of the lockfile.
- **Committed in:** c9c8457 (part of the Task 2 commit)
- **Watch for:** a future plain `npm install` may drop the react entry again. Review the lockfile diff before committing any later npm change.

---

**Total deviations:** 1 auto-fixed (1 blocking)
**Impact on plan:** Kept the lockfile change to the playwright additions only. No scope creep.

## Issues Encountered

None beyond the deviation above. The Chromium install printed no output because the browser was already cached, which is expected and was checked rather than assumed.

## Authentication Gates

None.

## Unrun Verifications

The workflow file was parsed and checked locally, but the new CI steps have not run on GitHub. That happens when Ian pushes the branch, so the first CI run on both the sqlite and pgsql legs is the check. Recorded as coverage item D4 (human judgment).

## User Setup Required

None - the one manual step (Chromium on the Mac) is done.

## Threat Flags

None. The new surface (npm package, GitHub Action, browser download) is the plan's threat register: T-04-SC (mitigated by the blocking-human checkpoint, exact pin and lockfile integrity hashes), T-04-26 (mitigated by the SHA pin) and T-04-27 (transferred).

## Next Phase Readiness

- The Pest browser plugin (04-07) now has its driver locally and in CI, so the axe accessibility check (A11Y-01) can be written in the plans that use it.
- Out-of-scope note from the orchestrator: other Playwright cache directories on this Mac (revision 1248 and ffmpeg) are being handled separately with Ian and are not part of this plan.

## Self-Check: PASSED

- package.json, package-lock.json and .github/workflows/tests.yml exist and carry the changes above.
- Commit `c9c8457` is an ancestor of HEAD.
- All three automated verify commands and both acceptance greps exit 0.
- `git rev-list --count 5e8917c..HEAD` was 1 at SUMMARY time, matching `commits: 1`.

---
*Phase: 04-room-standard*
*Completed: 2026-10-22*
