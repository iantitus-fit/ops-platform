# Setup (Ian's Mac)

About 20 minutes the first time. Steps 1 to 7 run in the terminal; steps 8 and 10
run in Claude Code, opened in this folder.

## 1. Install PHP and Composer

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/mac/8.5)"
```

This puts PHP 8.5, Composer and the Laravel installer in
`~/.config/herd-lite/bin` and adds that folder to your PATH. Close the terminal,
open a new one, and check:

```sh
php -v        # 8.5
composer -V
node -v
```

Laravel itself is not installed globally. Step 2 pulls it into the project.
(Laravel Herd, the desktop app, is the alternative. Use one or the other, not both.)

## 2. Install the app

```sh
cd ~/Projects/ops-platform
composer run setup
```

`setup` installs the PHP packages, creates `.env`, generates the app key,
creates the SQLite database, runs migrations and builds front-end assets.

## 3. Connect Claude Code to Laravel

```sh
php artisan boost:install
```

Space toggles an item, Enter confirms. The answers for this project:

- Features: AI Guidelines, Agent Skills and Boost MCP Server, all on.
- Third-party guidelines/skills: `filament/filament` on.
- Integrations: Laravel Cloud off until hosting is decided at first deploy.
- Agents: Claude Code and Codex (Codex reads AGENTS.md, which points it at
  CLAUDE.md's project rules).

Boost rewrites only the block between the `<laravel-boost-guidelines>` tags in
CLAUDE.md and AGENTS.md; the project sections above the tags stay. It saves
these answers in `boost.json` (committed), so rerunning `boost:install` later
starts from them.

Skip the `infer-conventions` skill it suggests until the app has real code. It
records patterns it finds at least three times, and a fresh scaffold has none.

## 4. Make your login and run it

```sh
php artisan make:filament-user
composer run dev
```

Open http://localhost:8000/admin and log in. `composer run dev` opens a
dashboard with four tabs (server, queue, logs, vite); Tab switches between
them, and `q` stops everything.

## 5. Run the tests

```sh
php artisan test
```

## 6. First commit

```sh
git init
git add .
git commit -m "Scaffold: Laravel 13, Filament 5, laravel/ai, Pest, Boost; spec and Sprint 0 plan"
```

`.claude/`, `.agents/`, `.codex/` and `.mcp.json` are gitignored on purpose. Boost and GSD regenerate them.

## 7. Install GSD Core for this project only

First check for an older global install, which would override the project copy:

```sh
ls ~/.claude/commands ~/.claude/skills 2>/dev/null | grep -i gsd
```

If that prints anything, stop and remove the global copy first
(`npx @opengsd/gsd-core@latest --claude --global --uninstall`). Then:

```sh
npx @opengsd/gsd-core@latest --claude --local
```

Restart Claude Code in this folder so the `/gsd-*` commands load.

## 8. Turn the docs into a GSD plan (Claude Code)

```
/gsd-ingest-docs --manifest docs/gsd-ingest.yaml
```

It lists the three docs it will read: approve. If it reports conflicts, read
`.planning/INGEST-CONFLICTS.md` before choosing **Create planning setup**. It
writes PROJECT.md, REQUIREMENTS.md, ROADMAP.md and STATE.md under `.planning/`
and commits them. DECISIONS.md goes in as locked decisions, so GSD won't reopen them.

Then read ROADMAP.md. Phase 1 should match Sprint 0 (Foundation). Everything
after booking (quote to invoice, voice, automations, connectors) waits until a
client or a job conversation pulls it.

## 9. Set GSD for this project (terminal)

```sh
T=.claude/gsd-core/bin/gsd-tools.cjs
node $T config-set workflow.use_worktrees false
node $T config-set git.branching_strategy phase
node $T config-set workflow.tdd_mode true
node $T config-set workflow.test_command "php artisan test"
```

Why each one:
- `use_worktrees false`: GSD's parallel workers each get a separate git
  checkout, and those checkouts have no `vendor/`, `.env` or database, so
  `php artisan test` fails there. One worker at a time is slower and easier to follow.
- `branching_strategy phase`: each phase builds on its own branch
  (`gsd/phase-01-...`). You review it and merge it.
- `tdd_mode true`: tests before code for logic, which is this repo's rule for
  money, sending and the sync.
- `test_command`: what GSD runs to check its own work.

Leave `mode` (interactive) and `model_profile` (balanced: Opus plans, Sonnet
builds) at their defaults.

## 10. The loop, one phase at a time (Claude Code)

```
/gsd-discuss-phase 1
/gsd-plan-phase 1
/gsd-execute-phase 1
/gsd-verify-work 1
```

Then merge the phase branch yourself (`git merge`). `/gsd-ship` is the other
option once the repo is on GitHub: it pushes the branch and opens a pull request
through the GitHub CLI (`gh`, already installed via Homebrew; `gh auth status`
confirms it's logged in). `/gsd-progress` tells you where you are; `/gsd-help`
lists everything. Skip `/gsd-autonomous` and yolo mode for now: the point of
this build is that you see each step.
