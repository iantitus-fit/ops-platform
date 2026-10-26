---
phase: quick-261026-lrs
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
  tokens: 25000
  raw_tokens: 25000
  tasks: 1
  confidence: low

must_haves:
  truths:
    - "The step 'Install the browser for the accessibility check' keeps its name and timeout-minutes: 10 and runs only npx playwright install chromium: no env entry, no if/else, no system-package install, so on a cache hit it downloads nothing and touches no Ubuntu mirror"
    - "No line of .github/workflows/tests.yml runs apt or installs system packages, and the file adds no comment about it"
    - "Every other line of .github/workflows/tests.yml is unchanged from commit 0c226f8, including the steps 'Read the Playwright version' and 'Cache Playwright's browser download', the 30-minute job timeout and the ubuntu-24.04 pin"
    - "docs/BLUEPRINT.md Safety and compliance no longer lists the apt stall as an open item; a Built line starting 'CI installs no system packages', directly after the runner-pin Built line, gives runs 40378515760 and 40387844777, the host azure.archive.ubuntu.com, the image's own libraries serving Chrome for Testing, and the fonts no longer installed that no test depends on, and ends 'Affects: safety and compliance, knowledge.'"
    - "The runner-pin Built line no longer says the system packages install on every run; it says they did until quick task 261026-lrs"
    - "No test in tests/ takes a screenshot, measures text or names a font, so dropping the fonts changes no test result (re-confirmed by grep at execution time)"
    - "One local commit on gsd/quick-ci-runner holds exactly .github/workflows/tests.yml and docs/BLUEPRINT.md; nothing is pushed; if CI later reports missing libraries, apt is not added back: the libraries are listed for Ian"
  artifacts:
    - path: ".github/workflows/tests.yml"
      provides: "Browser step that installs Chrome for Testing only, served by the image's libraries and the existing cache"
      contains: "run: npx playwright install chromium"
    - path: "docs/BLUEPRINT.md"
      provides: "Safety and compliance Built line closing the apt item; corrected runner-pin line; apt open item removed"
      contains: "**Built:** CI installs no system packages"
  key_links:
    - from: ".github/workflows/tests.yml step 'Install the browser for the accessibility check'"
      to: ".github/workflows/tests.yml step 'Cache Playwright's browser download' (path ~/.cache/ms-playwright)"
      via: "the restored cache folder makes the install a no-op on a hit; on a miss the step downloads Chrome for Testing and the cache step saves it after the job"
      pattern: "path: ~/.cache/ms-playwright"
    - from: "docs/BLUEPRINT.md Built line 'CI installs no system packages'"
      to: ".planning/quick/261026-lrs-stop-running-apt-in-ci-replace-the-brows/"
      via: "markdown link to the quick task folder"
      pattern: "261026-lrs-stop-running-apt-in-ci-replace-the-brows"
---

<objective>
Stop running apt in CI, as Ian asked on 2026-10-26. In `.github/workflows/tests.yml`, the step "Install the browser for the accessibility check" becomes a single `npx playwright install chromium`: the restored cache makes it a no-op on a hit, and on a miss it downloads Chrome for Testing only. The `ubuntu-24.04` image already ships Google Chrome and Chromium (runner-images Ubuntu2404 readme, image 20260823), so the libraries Chrome for Testing needs are already present. Keep the cache step and the 10-minute step timeout. Close the apt open item in `docs/BLUEPRINT.md` Safety and compliance with a Built line and an Affects line, and correct the runner-pin Built line's now-false sentence, in the same commit (CLAUDE.md: BLUEPRINT is updated in the same commit as the change that affects an area).

Affects: safety and compliance, knowledge.

Purpose: apt stalled twice on the Azure mirror, and the second time failed the pgsql leg at the step's 10-minute limit even with the browser cached. The accessibility check (A11Y-01) runs with each room's boot test, so a red run from a mirror stall blocks every merge.

Evidence the orchestrator already gathered (record in the SUMMARY; do not re-plan or redo):
1. Log of job 121816813348, attempt 2 of run 40387844777, read with gh. The step ran with the cache hit and so took the system-packages-only branch. apt's index refresh was fast (12.6 MB in 2 s from azure.archive.ubuntu.com and packages.microsoft.com). Then 25.9 MB of packages from http://azure.archive.ubuntu.com/ubuntu crawled: fonts-wqy-zenhei (7.5 MB) took about 3 minutes, fonts-freefont-ttf stalled about 90 s and was ignored, and the step hit its 10-minute timeout while fetching xvfb. apt's plan was "8 upgraded, 9 newly installed". New: fonts-freefont-ttf, fonts-ipafont-gothic, fonts-tlwg-loma-otf, fonts-unifont, fonts-wqy-zenhei, xfonts-cyrillic, xfonts-encodings, xfonts-scalable, xfonts-utils. Upgraded: gir1.2-glib-2.0, libglib2.0-0t64, libglib2.0-bin, libglib2.0-data, libnss3, libnss3-tools, xserver-common, xvfb. So every library on Playwright's Chromium dependency list was already installed on the image, and only newer versions were on offer. The only new packages were fonts. No library is missing.
2. Font check: no browser test uses screenshots or measures text. The browser tests (`tests/Feature/Rooms/*BootTest.php`, `tests/Feature/Rooms/RoomAccessibilityTest.php`) only run axe through `tests/Rooms/RoomSectionAxe.php`, with the WCAG 2.2 AA tags plus target-size. color-contrast uses computed CSS, and target-size finds no interactive targets in the room sections. The dropped fonts covered Chinese, Japanese, Thai and Cyrillic, plus GNU FreeFont, Unifont and X11 fonts. The dashboard is English. At planning time, `grep -rliE 'screenshot|measureText|toMatchSnapshot|getBoundingClientRect|font' tests/` returned nothing.
3. The first stall was run 40378515760: 15m24s at 23.1 kB/s from the same mirror (quick task 261026-jig).

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
@.planning/quick/261026-jig-make-ci-steadier-pin-runs-on-to-ubuntu-2/261026-jig-SUMMARY.md

In `docs/BLUEPRINT.md`, read only section "10. Safety and compliance": the "**Where it stands.**" list (about lines 524-547) and the "**Open items affecting this area.**" list (about lines 550-558). Knowledge's open items hold no pointer to the apt item (checked at planning time), so section 8 does not change.

Facts checked at planning time (2026-10-26), for the executor to rely on:
- Branch `gsd/quick-ci-runner`, HEAD `0c226f8`, tree clean. `.github/workflows/tests.yml` at `0c226f8` is identical to the 261026-jig commit `eb07487`.
- The browser step today (lines 85-93) has `timeout-minutes: 10`, an `env` block with one entry fed by the cache step's hit output, and a 5-line `run: |` script that branches on that entry.
- Local tools: python3 with PyYAML 6.0.3, ruby with YAML, gh. actionlint is not installed. Do not install it, because a new tool needs Ian's approval.
- The verify commands below were dry-run in the scratchpad against a simulated edit. They pass on the intended result and fail on the current file.
- Run every command from the repo root.
</context>

<!-- planner-discipline-allow: apt -->

<tasks>

<task type="auto">
  <name>Task 1: Install only Chromium in CI, close the BLUEPRINT apt item, correct the runner-pin line (one commit)</name>
  <files>.github/workflows/tests.yml, docs/BLUEPRINT.md</files>
  <precondition>On branch gsd/quick-ci-runner, and `git diff --quiet 0c226f8 -- .github/workflows/tests.yml docs/BLUEPRINT.md` exits 0 (neither file changed since planning).</precondition>
  <reversibility rating="reversible">A workflow and docs edit in one local commit. Reverting it restores the cache-aware branch script.</reversibility>
  <action>
Part A: `.github/workflows/tests.yml`. Change only the step named "Install the browser for the accessibility check" and keep every other line byte-for-byte.

1. Keep the line `- name: Install the browser for the accessibility check` and the line `timeout-minutes: 10` exactly as they are.
2. Delete the step's `env` block: the `env:` line and its one entry, which reads the cache step's hit output.
3. Replace the whole multi-line `run: |` script (the line `run: |` and the five script lines under it: the hit test, its branch, the else, the miss branch, and the closing line) with one line at the same 8-space indent as `timeout-minutes`: `run: npx playwright install chromium`. Write it as a plain one-line scalar, not a block. Ian's instruction is to replace the if/else with this, and the cache makes it a no-op on a hit.
4. Add no comment lines and no new steps. Do not add a sudo, package-manager, dpkg or font step, and do not add any flag that installs system packages. Ian's rule: apt does not come back. If libraries turn out to be missing, list them and stop. The negative check in `<verify>` covers the whole file, comments included, on purpose: Ian asked for no apt anywhere in the file, and this task adds no comments.
5. Leave "Read the Playwright version", "Cache Playwright's browser download", `runs-on: ubuntu-24.04`, the 30-minute job timeout, the services, the env and the matrix untouched. The python check below compares every step other than the browser step, and the rest of the job, against commit `0c226f8`.

Part B: `docs/BLUEPRINT.md`. Make scoped edits only, and never rewrite the whole file.

1. In section "10. Safety and compliance", under "**Open items affecting this area.**", delete the whole bullet about apt stalling while fetching Chromium's system packages (dated "Ian, 2026-10-26", linking quick task 261026-jig; it is the bullet right after "IN-11 (outage alerts with no owner phone)."). Leave every other bullet in that list as it is.
2. In section 10's "**Where it stands.**" list, edit the bullet that starts "- **Built:** the CI runner pin". Replace only its sentence that starts "The system packages Chromium needs" and ends "took 15 of the step's 16 minutes." with this sentence: Until quick task 261026-lrs (next line), the system packages Chromium needs were installed from Ubuntu's mirror on every run; on run 40378515760 those packages, not the browser, took 15 of the step's 16 minutes. Keep the rest of that bullet, including "The job stops after 30 minutes and the browser install after 10." and its closing Affects line, unchanged. The sentence it replaces became false with this task.
3. Insert a new bullet directly after that runner-pin bullet, as the very next line. Write it as one line, with this exact text (only the line breaks of this plan are not part of it):
- **Built:** CI installs no system packages, closed by [quick task 261026-lrs](../.planning/quick/261026-lrs-stop-running-apt-in-ci-replace-the-brows/) (Ian, 2026-10-26): the browser step only runs `npx playwright install chromium` (`.github/workflows/tests.yml`), which does nothing when the cache hits, and Chrome for Testing runs on the libraries the `ubuntu-24.04` image already carries for its own Google Chrome and Chromium (image 20260823). Apt stalled twice on `azure.archive.ubuntu.com`: on run 40378515760 it took 15m24s at 23 kB/s, and on the re-run of run 40387844777 the pgsql leg hit the step's 10-minute limit on a cache hit, still downloading. That log showed every library Chromium needs already installed, with only newer versions on offer; the only new packages were fonts (Chinese, Japanese, Thai and Cyrillic fonts, GNU FreeFont, Unifont and X11 fonts). CI no longer installs those fonts, and no test depends on them: the browser tests run axe on English room sections, take no screenshots and measure no text. If a run reports missing libraries, they are listed for Ian and apt stays out. Affects: safety and compliance, knowledge.
4. Don't change the "Areas at a glance" table, section 8 (Knowledge) or any other section.

Part C: check, then commit.
1. Run the `<automated>` block below before committing. Its last check reads the uncommitted working tree.
2. Run `php artisan test --compact` (CLAUDE.md: run the suite before calling a step done). No PHP changed, and the local suite does not use CI's step, so this only proves nothing else moved. The 261026-jig close gave 2204 passed with 1 warning (the known browser-plugin rmdir warning).
3. Make ONE local commit on `gsd/quick-ci-runner` holding exactly these two files. Stage them by path, never with `git add -A`. Subject: "ci(261026-lrs): install only Chromium in CI, no apt; close the BLUEPRINT apt item". The body says in two or three sentences:
   - On the re-run of run 40387844777 the pgsql leg hit the 10-minute limit on a cache hit, with apt still downloading from azure.archive.ubuntu.com.
   - Only fonts were new; every library Chromium needs was already on the image.
   - No test depends on those fonts.
   Then a blank line, "Affects: safety and compliance, knowledge.", and the session's "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>" line.
4. Never push, never merge, never amend (CLAUDE.md Git rule).
5. After the commit, `git show --name-only --format= HEAD | sort` must print exactly `.github/workflows/tests.yml` and `docs/BLUEPRINT.md`.

Not done here, on purpose:
- No walk-through log line in `docs/sprints/`. Playwright's system-package install is not a Laravel concept, and Ian asked for one commit with two files. Instead, the SUMMARY explains in three or four plain sentences:
  - what the removed command did (installed the Linux libraries and fonts Chromium can use, through Ubuntu's package manager, from Ubuntu's mirror, on every run);
  - why the image already has the libraries (it ships Google Chrome and Chromium, which need the same ones);
  - what happens if a library is ever missing (Chromium fails to start, and Playwright names the missing libraries in the test log).
- The planning artifacts (SUMMARY, the WINDOWS.md entry, STATE.md) go in the quick workflow's own docs commit, not in this task's commit.
  </action>
  <verify>
    <automated>python3 -c "
import yaml,subprocess
old=yaml.safe_load(subprocess.run(['git','show','0c226f8:.github/workflows/tests.yml'],capture_output=True,text=True,check=True).stdout)
new=yaml.safe_load(open('.github/workflows/tests.yml'))
N='Install the browser for the accessibility check'
os_=old['jobs']['pest'].pop('steps'); ns=new['jobs']['pest'].pop('steps')
assert old==new, 'something outside the steps moved'
names=[s.get('name') for s in ns]; i=names.index(N)
assert [s.get('name') for s in os_]==names, 'step list or order moved'
assert ns[i]=={'name':N,'timeout-minutes':10,'run':'npx playwright install chromium'}, ns[i]
assert os_[:i]+os_[i+1:]==ns[:i]+ns[i+1:], 'another step moved (the version and cache steps must match 0c226f8)'
print('workflow ok')" &&
ruby -ryaml -e 'YAML.load_file(".github/workflows/tests.yml"); puts "ruby parse ok"' &&
! grep -nE '(^|[^[:alnum:]_])apt([^[:alnum:]_]|$)|install-deps|with-deps|BROWSER_CACHED' .github/workflows/tests.yml && echo "no system-package install in tests.yml" &&
DW=$(git diff --numstat 0c226f8 -- .github/workflows/tests.yml) && test "$(printf '%s\n' "$DW" | cut -f1,2)" = "$(printf '1\t8')" && echo "tests.yml diff is the one step" &&
test -z "$(grep -rliE 'screenshot|measureText|toMatchSnapshot|getBoundingClientRect|font' tests/)" && echo "no test depends on fonts" &&
test "$(awk '/^## 10\. Safety and compliance/,/^---$/' docs/BLUEPRINT.md | grep -c 'apt can stall')" = 0 &&
test "$(grep -c "still install from Ubuntu's mirror" docs/BLUEPRINT.md)" = 0 &&
grep -E '^- \*\*Built:\*\* the CI runner pin' docs/BLUEPRINT.md | grep -q '261026-lrs' &&
L=$(awk '/^## 10\. Safety and compliance/,/^---$/' docs/BLUEPRINT.md | grep -E '^- \*\*Built:\*\* CI installs no system packages') && test "$(printf '%s\n' "$L" | wc -l | tr -d ' ')" = 1 &&
( for t in 40387844777 40378515760 azure.archive.ubuntu.com fonts 'Affects: safety and compliance, knowledge\.$'; do printf '%s\n' "$L" | grep -qE "$t" || { echo "Built line lacks $t"; exit 1; }; done ) &&
P=$(grep -n '^- \*\*Built:\*\* the CI runner pin' docs/BLUEPRINT.md | cut -d: -f1) && Q=$(grep -n '^- \*\*Built:\*\* CI installs no system packages' docs/BLUEPRINT.md | cut -d: -f1) && test "$Q" = "$((P+1))" &&
DB=$(git diff --numstat 0c226f8 -- docs/BLUEPRINT.md) && test "$(printf '%s\n' "$DB" | cut -f1,2)" = "$(printf '2\t2')" && echo "BLUEPRINT ok" &&
ST=$(git status --porcelain --untracked-files=all) && test "$(printf '%s\n' "$ST" | grep -v '\.planning/' | awk '{print $2}' | sort | tr '\n' ' ')" = ".github/workflows/tests.yml docs/BLUEPRINT.md " && echo "only the two files changed (run before the commit)"</automated>
    <human-check>After Ian pushes `gsd/quick-ci-runner`, the "tests" workflow on GitHub:
(1) both legs, pest (sqlite) and pest (pgsql), pass;
(2) "Install the browser for the accessibility check" shows no package-manager output (no "Reading package lists", no "Get:" lines from azure.archive.ubuntu.com) on either leg; on a cache hit it finishes in seconds with no download, and on a miss it downloads only Chrome for Testing, its headless shell and FFmpeg;
(3) "Run the tests" shows the room boot and accessibility tests passing, and Playwright prints no "Host system is missing dependencies" message in either step;
(4) if Playwright names missing libraries, do not add apt back: copy the library list into Safety and compliance's open items for Ian, and stop.
Do not pause the run for this. Record it as an `unrun-verify` entry in `.planning/WINDOWS.md` (file `.github/workflows/tests.yml`) with the gsd-tools `windows` command, noting that it replaces check (4) of row 25 (quick-261026-jig), which expected a system-packages-only install on a hit. List it in the SUMMARY (memory: hand checks go to UAT).</human-check>
  </verify>
  <done>
- In `.github/workflows/tests.yml`, "Install the browser for the accessibility check" has its name, `timeout-minutes: 10` and `run: npx playwright install chromium`, and nothing else.
- No apt, no system-package install and no install-with-dependencies flag remain anywhere in that file.
- Every other step and setting matches `0c226f8`.
- In `docs/BLUEPRINT.md`, the apt open item is gone from Safety and compliance.
- The runner-pin Built line says the packages were installed until 261026-lrs.
- The new "CI installs no system packages" Built line follows it directly, with both run numbers, the host and the fonts finding, and ends "Affects: safety and compliance, knowledge.".
- All `<automated>` checks pass, and `php artisan test --compact` passes.
- One local commit holds exactly the two files. Nothing is pushed.
  </done>
</task>

</tasks>

<threat_model>
## Trust Boundaries

| Boundary | Description |
|----------|-------------|
| CI job and Ubuntu apt mirror | Before this task, a network install from azure.archive.ubuntu.com ran as root on every run; after it, there is none |
| CI job and Playwright CDN | On a cache miss, Chrome for Testing downloads from Playwright's CDN, as before |
| GitHub Actions cache and job | A restored `~/.cache/ms-playwright` holds the Chromium binary the accessibility check runs (unchanged by this task) |

## STRIDE Threat Register

| Threat ID | Category | Component | Severity | Disposition | Mitigation Plan |
|-----------|----------|-----------|----------|-------------|-----------------|
| T-261026-lrs-01 | Denial of service | Browser step in .github/workflows/tests.yml | medium | mitigate | The mirror stall (runs 40378515760 and 40387844777) is removed at its source: the step runs only `npx playwright install chromium`, a no-op on a cache hit. `timeout-minutes: 10` stays on the step and 30 stays on the job, so a CDN stall on a miss still fails in bounded time |
| T-261026-lrs-02 | Tampering (silent loss of the check) | Accessibility check when a library is missing | low | mitigate | A missing library stops Chromium from starting, so the browser tests error and the run goes red; they cannot pass silently. `tests/Rooms/RoomSectionAxe.php` also asserts that axe checked more than zero rules. The human check names the "Host system is missing dependencies" message and the stop rule: list the libraries, no apt |
| T-261026-lrs-03 | Tampering (result drift) | axe results with fewer fonts installed | low | accept | No test takes screenshots or measures text (grep re-confirmed in `<verify>`). axe color-contrast reads computed CSS, the room sections are English, and the image keeps its default fonts |
| T-261026-lrs-SC | Tampering | npm/pip/cargo installs | high | accept | No package is added or upgraded. `npm ci` installs from the existing lockfile (Playwright pinned at 1.63.0). This task removes a root-level package install from CI and adds none, so the package-legitimacy gate has nothing to check |
</threat_model>

<verification>
Run from the repo root:
- Every command in Task 1's `<automated>` block passes, run before the commit.
- `php artisan test --compact` passes, with the known browser-plugin rmdir warning only.
- After the commit: `git show --name-only --format= HEAD | sort` prints exactly `.github/workflows/tests.yml` and `docs/BLUEPRINT.md`, `git log -1 --format=%s` shows the ci(261026-lrs) subject, and `git rev-parse --abbrev-ref HEAD` prints `gsd/quick-ci-runner`.
- Not checkable locally: whether the CI image's libraries serve Chrome for Testing. That is Task 1's `<human-check>`, carried to Ian after he pushes; it is not a blocker. On this Mac nothing can show a missing Linux library, so no local stop condition applies beyond these checks.
</verification>

<success_criteria>
- The browser step runs only `npx playwright install chromium` under Ian's step name, with the 10-minute timeout kept, and the cache step and the version step are unchanged.
- No apt or system-package install anywhere in `.github/workflows/tests.yml`.
- BLUEPRINT: the apt open item is closed by a Built line in Safety and compliance's Where it stands, ending "Affects: safety and compliance, knowledge.", and the runner-pin line no longer claims packages install on every run.
- The font finding is reported to Ian in the SUMMARY: no browser test depends on the fonts the system-package install used to add.
- One local commit with exactly the two files on `gsd/quick-ci-runner`; nothing pushed.
- The SUMMARY records the run 40387844777 evidence, the carried CI human check with its no-apt stop rule, and the plain explanation for Ian.
</success_criteria>

<output>
Create `.planning/quick/261026-lrs-stop-running-apt-in-ci-replace-the-brows/261026-lrs-SUMMARY.md` when done
</output>
