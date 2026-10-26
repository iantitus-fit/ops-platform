---
phase: quick-261026-jig
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - .github/workflows/tests.yml
  - docs/BLUEPRINT.md
autonomous: true
requirements: [A11Y-01]

estimate:
  tokens: 30000
  raw_tokens: 30000
  tasks: 1
  confidence: low

must_haves:
  truths:
    - "The CI job runs on the pinned image ubuntu-24.04, not the floating latest label, so GitHub moving its latest runner to Ubuntu 26 does not change the run of the browser accessibility check"
    - "The CI job stops after 30 minutes and the step 'Install the browser for the accessibility check' stops after 10 minutes, so a stalled mirror fails the run instead of hanging it"
    - "Playwright's Chromium download is cached in ~/.cache/ms-playwright under a key built from the Playwright version read out of package-lock.json, plus the runner image and architecture, so a Playwright bump starts a fresh cache with no edit to the workflow"
    - "On a cache hit the browser step installs only Chromium's system packages (npx playwright install-deps chromium); on a miss it runs npx playwright install --with-deps chromium exactly as before; the step keeps Ian's name"
    - "The actions/cache step is pinned by a full 40-character commit SHA with a version comment, and that SHA is what GitHub's actions/cache repository gives for the tag in the comment, like every other action in the file"
    - "docs/BLUEPRINT.md no longer lists the CI runner pin as open in Safety and compliance or points to it from Knowledge's open items; Safety and compliance's Where it stands has a Built line for it ending 'Affects: safety and compliance, knowledge.'"
    - "One commit holds exactly .github/workflows/tests.yml and docs/BLUEPRINT.md; nothing under app/, tests/, database/, routes/, config/, resources/ or bootstrap/ and no composer or npm manifest changes"
  artifacts:
    - path: ".github/workflows/tests.yml"
      provides: "Pinned runner, job and step timeouts, Playwright version step, SHA-pinned actions/cache step, cache-aware browser install"
      contains: "runs-on: ubuntu-24.04"
    - path: "docs/BLUEPRINT.md"
      provides: "Safety and compliance Built line closing the CI runner pin; open item and Knowledge pointer removed"
      contains: "**Built:** the CI runner pin"
  key_links:
    - from: ".github/workflows/tests.yml step id playwright"
      to: "package-lock.json packages['node_modules/playwright-core'].version"
      via: "node -p, written to GITHUB_OUTPUT as version"
      pattern: "node_modules/playwright-core"
    - from: ".github/workflows/tests.yml cache key"
      to: "steps.playwright.outputs.version"
      via: "actions/cache with.key expression"
      pattern: "steps\\.playwright\\.outputs\\.version"
    - from: ".github/workflows/tests.yml browser install step"
      to: "steps.playwright-cache.outputs.cache-hit"
      via: "step env variable read by the run script"
      pattern: "steps\\.playwright-cache\\.outputs\\.cache-hit"
---

<objective>
Make CI steadier, as Ian asked on 2026-10-26: pin the runner to `ubuntu-24.04`, cache Playwright's Chromium download keyed on the pinned Playwright version (1.63.0), and add `timeout-minutes: 30` to the job and `timeout-minutes: 10` to the step "Install the browser for the accessibility check". Close the CI runner pin open item in `docs/BLUEPRINT.md` Safety and compliance, with its Knowledge pointer, in the same commit (CLAUDE.md: BLUEPRINT is updated in the same commit as the change that affects an area).

Affects: safety and compliance, knowledge.

Purpose: on run 40378515760 the browser step took 15m52s on the pgsql leg while the sqlite leg's whole job took 4m45s. A floating runner label and an unbounded step make CI slow and unpredictable for the accessibility check that A11Y-01 runs with each room's boot test.

What the planner found in that run's log (read with `gh run view --job 121768070543 --log`, so the executor and Ian know what each change does and does not fix):
- The 15m52s went to apt, not to the browser: "Fetched 21.4 MB in 15min 24s (23.1 kB/s)" from `azure.archive.ubuntu.com`. The Chromium, FFmpeg and headless shell downloads from `cdn.playwright.dev` took about 10 seconds together.
- So the Chromium cache saves about 10 seconds a leg and removes the dependency on Playwright's CDN; it does not touch the slow part. The 10-minute step timeout is what changes that run: the same stall would now fail the leg at 10 minutes instead of passing at 16, and Ian re-runs it.
- The runner was already Ubuntu 24.04 (apt read `noble`), so the pin changes nothing today; it stops a silent move to Ubuntu 26.
- The pgsql leg's "Run the tests" step took 9m43s on that run (sqlite: 3m27s). A normal pgsql leg is about 12 minutes, and about 21 with the browser step at its 10-minute cap, so the 30-minute job timeout leaves room.
The cache is planned exactly as Ian asked; the finding goes into the BLUEPRINT Built line and the SUMMARY, not into extra scope.

Output: one commit changing `.github/workflows/tests.yml` and `docs/BLUEPRINT.md`.
</objective>

<execution_context>
@/Users/iantitus/Projects/ops-platform/.claude/gsd-core/workflows/execute-plan.md
@/Users/iantitus/Projects/ops-platform/.claude/gsd-core/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md
@CLAUDE.md
@.github/workflows/tests.yml
@docs/BLUEPRINT.md

Read in docs/BLUEPRINT.md only these ranges: lines 437-445 (Knowledge open items) and lines 494-559 (section 10, Safety and compliance).

Facts already checked at planning time (2026-10-26), for the executor to re-confirm, not re-discover:
- `package.json` pins `"playwright": "1.63.0"`; `package-lock.json` has `packages['node_modules/playwright'].version` and `packages['node_modules/playwright-core'].version` both `1.63.0`. `node -p "require('./package-lock.json').packages['node_modules/playwright-core'].version"` prints `1.63.0`.
- The latest actions/cache release is `v6.1.0`. Both `gh api repos/actions/cache/commits/v6.1.0 --jq .sha` and `git ls-remote --tags https://github.com/actions/cache` gave `55cc8345863c7cc4c66a329aec7e433d2d1c52a9`. It is a lightweight tag, so there is no peeled line.
- House style for actions: `uses: owner/repo@<40-hex commit SHA> # <tag>`, e.g. `actions/checkout@3d3c42e5aac5ba805825da76410c181273ba90b1 # v7.0.1`.
- Locally available: python3 with PyYAML, ruby with YAML, gh (authenticated), node 24. actionlint is not installed. Do not install it, because adding a tool needs Ian's approval.
</context>

<tasks>

<task type="auto">
  <name>Task 1: Pin the runner, cache Chromium, add timeouts, and close the BLUEPRINT runner pin item (one commit)</name>
  <files>.github/workflows/tests.yml, docs/BLUEPRINT.md</files>
  <precondition>`git ls-remote --tags https://github.com/actions/cache` reaches GitHub, because the actions/cache SHA must be resolved from upstream and never guessed.</precondition>
  <reversibility rating="reversible">A workflow and docs edit in one local commit; reverting the commit restores the old CI behavior.</reversibility>
  <action>
Part A: `.github/workflows/tests.yml`. Change only what is listed here and keep every other line byte-for-byte.

1. Runner pin: in job `pest`, set `runs-on` to `ubuntu-24.04`, replacing the floating latest label on line 13.
2. Job timeout: add `timeout-minutes: 30` to job `pest`, on the line right after `runs-on`, at the same indent.
3. Resolve the actions/cache SHA now, at execution time. Get the latest release tag with `gh release view --repo actions/cache --json tagName --jq .tagName`. It was `v6.1.0` at planning time; use whatever it returns now. Get that tag's commit SHA with `gh api repos/actions/cache/commits/<tag> --jq .sha`. Confirm it with `git ls-remote --tags https://github.com/actions/cache`: the SHA must match the tag's line, or its peeled `^{}` line if the tag is annotated. Never type a SHA from memory or from this plan without that confirmation.
4. New step "Read the Playwright version", with `id: playwright`, placed right after "Install Node packages" and before the cache step. Its `run` block does three things. First, it assigns the version to a shell variable with `node -p` reading `require('./package-lock.json').packages['node_modules/playwright-core'].version`. It is a plain assignment, not inline inside an echo, so a missing lockfile entry fails the step under GitHub's default `bash -e` instead of yielding an empty key. Second, it appends `version=<that value>` to `"$GITHUB_OUTPUT"`. Third, it appends `image=$ImageOS` (the runner image name GitHub sets, e.g. ubuntu24) to `"$GITHUB_OUTPUT"`. Deriving the version from the lockfile, not hard-coding 1.63.0, is the planner's choice: the key follows a future Playwright bump with no edit. `playwright-core` is used because it is the package that names the browser build; it equals `playwright` at 1.63.0 today.
5. New step "Cache Playwright's browser download", with `id: playwright-cache`, placed right after step 4. It uses `actions/cache@<SHA from step 3> # <tag from step 3>`. `with.path` is `~/.cache/ms-playwright`. `with.key` is `playwright-chromium-${{ steps.playwright.outputs.image }}-${{ runner.arch }}-${{ steps.playwright.outputs.version }}`. Add no `restore-keys`: a partial match would restore an old browser build that Playwright would then download over anyway. Both matrix legs share the key on purpose. On the first run after a bump, both legs miss and one leg's save may log that another job is creating the cache. That is a warning, not a failure.
6. Ian's step "Install the browser for the accessibility check": keep the name exactly. Add `timeout-minutes: 10`. Add an `env` entry `BROWSER_CACHED: ${{ steps.playwright-cache.outputs.cache-hit }}`. Pass it through env rather than interpolating the expression into the script, the usual Actions hygiene. Make `run` a block script: when `"$BROWSER_CACHED"` equals `true`, run `npx playwright install-deps chromium` (system packages only; the browser came from the cache); otherwise run `npx playwright install --with-deps chromium`, unchanged from today. One step with one name and one timeout, not a hit/miss pair: Ian named one step and one timeout. The cache step's log already shows hit or miss.
7. Indent with 2 spaces like the rest of the file, and leave a blank line between steps. Don't touch any other step, the services block, the env block or the matrix.

Part B: `docs/BLUEPRINT.md`. These are scoped edits; never rewrite the whole file.

1. In section "10. Safety and compliance", under "**Open items affecting this area.**", delete the bullet that starts with "The CI runner pin (Ian, 2026-10-23" (currently line 555). Leave the other bullets in that list untouched.
2. In section "8. Knowledge", under "**Open items affecting this area.**", delete the one-line bullet that points the reader to Safety and compliance for the runner pin (currently line 442). The item it points to is closed now, and open-item lists hold only open items. This follows the G-04-1 precedent: G-04-1's close became a Built line in Where it stands, and nothing was left in the open list.
3. In section 10's "**Where it stands.**" list, insert a new bullet directly after the bullet that starts "**Built:** the automated accessibility check" (currently line 527). Write it in the file's plain style. It must start with "- **Built:** the CI runner pin" and end with "Affects: safety and compliance, knowledge." as the last text on the line. It must say these things, in this order:
   - It was closed by quick task 261026-jig (Ian, 2026-10-26). Link the words "quick task 261026-jig" to `../.planning/quick/261026-jig-make-ci-steadier-pin-runs-on-to-ubuntu-2/`.
   - CI runs on `ubuntu-24.04` instead of `ubuntu-latest` (`.github/workflows/tests.yml`), so GitHub moving its latest runner to Ubuntu 26 does not change the run of the browser accessibility check.
   - Playwright's Chromium download is cached, keyed on the Playwright version in `package-lock.json`.
   - The system packages Chromium needs still install from Ubuntu's mirror on every run. On run 40378515760 those packages, not the browser, took 15 of the step's 16 minutes.
   - The job stops after 30 minutes and the browser install after 10.
4. Don't change the "Areas at a glance" table or any other section.

Part C: check, then commit. Run the `<verify>` commands. Then make ONE local commit on the current branch `gsd/quick-ci-runner` holding exactly these two files: stage them by path, never with `git add -A`. Suggested subject: "ci(261026-jig): pin the runner to ubuntu-24.04, cache Chromium, add timeouts; close the BLUEPRINT runner pin". The body should name the run 40378515760 finding in one sentence and end with the session's Co-Authored-By line. Never push, never merge, never amend (CLAUDE.md Git rule).

Not done here, on purpose:
- No walk-through log line in `docs/sprints/`. A GitHub Actions cache is not a Laravel concept, and Ian asked for one commit with two files. Instead, the SUMMARY explains in three or four plain sentences what a cache key is and why a Playwright bump makes a new one.
- The planning artifacts (SUMMARY, the WINDOWS.md entry below, STATE.md) belong to the quick workflow's own docs commit, not to this task's commit.
  </action>
  <verify>
    <automated>python3 -c "import yaml,re; w=yaml.safe_load(open('.github/workflows/tests.yml')); j=w['jobs']['pest']; assert j['runs-on']=='ubuntu-24.04', j['runs-on']; assert j['timeout-minutes']==30; s=j['steps']; n=[x.get('name') for x in s]; i=n.index('Install the browser for the accessibility check'); c=[k for k,x in enumerate(s) if str(x.get('uses','')).startswith('actions/cache@')]; assert len(c)==1 and i > c[0]; cs=s[c[0]]; assert re.fullmatch(r'actions/cache@[0-9a-f]{40}', cs['uses']); assert cs['with']['path']=='~/.cache/ms-playwright'; assert 'steps.playwright.outputs.version' in cs['with']['key']; b=s[i]; assert b['timeout-minutes']==10; assert 'install-deps chromium' in b['run'] and 'install --with-deps chromium' in b['run']; print('workflow ok')" &&
ruby -ryaml -e 'YAML.load_file(".github/workflows/tests.yml"); puts "ruby parse ok"' &&
test "$(grep -E '^ +uses: ' .github/workflows/tests.yml | grep -cvE '@[0-9a-f]{40} # v?[0-9]+(\.[0-9]+)*$')" = 0 && echo "every action SHA-pinned with a version comment" &&
L=$(grep -oE 'actions/cache@[0-9a-f]{40} # v[0-9.]+' .github/workflows/tests.yml) && SHA=$(echo "$L" | sed -E 's/.*@([0-9a-f]{40}).*/\1/') && TAG=$(echo "$L" | awk '{print $3}') && UPSTREAM=$(git ls-remote --tags https://github.com/actions/cache) && echo "$UPSTREAM" | grep -E "refs/tags/${TAG}(\^\{\})?\$" | grep -q "^$SHA" && echo "actions/cache SHA is upstream $TAG" &&
test "$(node -p "require('./package-lock.json').packages['node_modules/playwright-core'].version")" = "1.63.0" && echo "lockfile expression reads 1.63.0" &&
test "$(awk '/^## 10\. Safety and compliance/,/^---$/' docs/BLUEPRINT.md | grep -c 're-check the run after GitHub moves')" = 0 &&
test "$(awk '/^## 8\. Knowledge/,/^## 9\. /' docs/BLUEPRINT.md | grep -c 'The CI runner pin: see')" = 0 &&
test "$(awk '/^## 10\. Safety and compliance/,/^---$/' docs/BLUEPRINT.md | grep -cE '^- \*\*Built:\*\* the CI runner pin.*Affects: safety and compliance, knowledge\.$')" = 1 && echo "BLUEPRINT ok" &&
APPCHANGES=$(git status --porcelain -- app tests database routes config resources bootstrap composer.json composer.lock package.json package-lock.json) && test -z "$APPCHANGES" && echo "no app, test or manifest changes"</automated>
    <human-check>After Ian pushes `gsd/quick-ci-runner`, the "tests" workflow on GitHub: (1) both legs, pest (sqlite) and pest (pgsql), pass; (2) "Set up job" names the Ubuntu 24.04 image; (3) on the first run, "Cache Playwright's browser download" reports a miss and the browser step downloads Chromium; the post-job cache save succeeds on one leg, and the other may warn that another job is creating the cache; (4) on a re-run, the cache step reports a hit and the browser step's log shows only system packages being installed, with no "Downloading Chrome for Testing" line; (5) no step exceeds its timeout. Do not pause the run for this. Record it as an `unrun-verify` entry in `.planning/WINDOWS.md` (file `.github/workflows/tests.yml`) with the gsd-tools `windows` command, and list it in the SUMMARY (memory: hand checks go to UAT).</human-check>
  </verify>
  <done>
- `.github/workflows/tests.yml` runs job `pest` on `ubuntu-24.04` with `timeout-minutes: 30`.
- Before the browser step, it reads the Playwright version from `package-lock.json` and restores and saves `~/.cache/ms-playwright` with a SHA-pinned actions/cache, keyed on runner image, architecture and that version.
- "Install the browser for the accessibility check" keeps its name, has `timeout-minutes: 10`, and runs install-deps on a hit and the original install command on a miss.
- `docs/BLUEPRINT.md` has the Built line in Safety and compliance's Where it stands, ending with the Affects line, and neither open-items list mentions the runner pin.
- All `<automated>` checks pass, and one local commit holds exactly the two files.
  </done>
</task>

</tasks>

<threat_model>
## Trust Boundaries

| Boundary | Description |
|----------|-------------|
| CI job and third-party action code | `actions/cache` runs inside the job with the job's token (`permissions: contents: read`) |
| GitHub Actions cache and job | A restored `~/.cache/ms-playwright` holds the Chromium binary the accessibility check then runs |
| CI job and Ubuntu apt mirror / Playwright CDN | Network installs that can stall or fail |

## STRIDE Threat Register

| Threat ID | Category | Component | Severity | Disposition | Mitigation Plan |
|-----------|----------|-----------|----------|-------------|-----------------|
| T-261026-jig-01 | Tampering | actions/cache step in .github/workflows/tests.yml | medium | mitigate | Pinned by a full 40-character commit SHA resolved at execution time from the upstream repo (gh api, confirmed by git ls-remote) with the tag in a comment; the verify asserts the SHA matches the upstream tag and that every `uses:` line is SHA-pinned |
| T-261026-jig-02 | Tampering | Restored browser cache | low | accept | The key needs an exact match (no restore-keys); GitHub scopes caches by branch, and a fork's pull request cannot write a cache that the main branch reads; the job has only `contents: read` and no secrets beyond the throwaway Postgres password, so a poisoned binary could at worst fail or fake the accessibility check in CI |
| T-261026-jig-03 | Denial of service | Browser install step and pest job | low | mitigate | `timeout-minutes: 10` on "Install the browser for the accessibility check" and `timeout-minutes: 30` on the job turn a stalled mirror (15m24s at 23.1 kB/s on run 40378515760) into a bounded failure |
| T-261026-jig-04 | Information disclosure | Cache contents | low | accept | The cache holds only public Chromium, FFmpeg and headless shell builds from Playwright's CDN; no env, `.env` or key material lives under `~/.cache/ms-playwright` |
| T-261026-jig-SC | Tampering | npm/pip/cargo installs | high | accept | No package is added or upgraded: `npm ci` installs from the existing lockfile (Playwright already pinned at 1.63.0 in Phase 4 plan 04-08), and no package manager install task exists in this plan, so the package-legitimacy gate has nothing to check |
</threat_model>

<verification>
Run from the checkout root:
- Every command in Task 1's `<automated>` block passes.
- `php artisan test --compact` passes (CLAUDE.md: run the suite before calling a step done). No PHP changed, so the result matches the Phase 5 close.
- After the commit: `git show --name-only --format= HEAD | sort` prints exactly `.github/workflows/tests.yml` and `docs/BLUEPRINT.md`. `git log -1 --format=%s` shows the ci(261026-jig) subject. `git status` shows no other change from this task.
- Not checkable locally: the CI run itself. That is Task 1's `<human-check>`, carried to Ian after he pushes; it is not a blocker.
</verification>

<success_criteria>
- Runner pinned to `ubuntu-24.04`; job timeout 30 minutes; browser step timeout 10 minutes under Ian's step name.
- Chromium download cached in `~/.cache/ms-playwright`, keyed on the Playwright version derived from `package-lock.json` (1.63.0 today), runner image and architecture; install-deps on a hit, the original install on a miss.
- actions/cache pinned by a verified upstream commit SHA with a version comment, matching house style.
- BLUEPRINT: runner pin closed in Safety and compliance with a Built line ending "Affects: safety and compliance, knowledge."; the Knowledge pointer removed.
- One local commit with exactly the two files; no app code, tests or manifests touched; nothing pushed.
- SUMMARY records the run 40378515760 finding: apt took 15m24s, so the cache does not fix that stall and the 10-minute timeout makes it fail fast. It also records the carried CI human check, and explains the cache key in plain sentences for Ian.
</success_criteria>

<output>
Create `.planning/quick/261026-jig-make-ci-steadier-pin-runs-on-to-ubuntu-2/261026-jig-SUMMARY.md` when done
</output>
