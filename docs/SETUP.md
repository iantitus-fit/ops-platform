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

`CACHE_STORE` must be a shared store, `database` (the `.env.example` default) or `redis`, because the booking lock that stops two customers taking the same time lives there (`08-RESEARCH.md` A4).

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

Boost rewrites only its own block in CLAUDE.md and AGENTS.md, starting from the
first place its tag name appears. Keep that tag name out of the project sections
above the block, or Boost overwrites them too (it did once). It saves
these answers in `boost.json` (committed), so rerunning `boost:install` later
starts from them.

Skip the `infer-conventions` skill it suggests until the app has real code. It
records patterns it finds at least three times, and a fresh scaffold has none.

## 4. Make your login and run it

```sh
php artisan make:filament-user
composer run dev
```

Open http://localhost:8000/admin and log in. `make:filament-user` creates an
owner with no phone, so open your profile and fill in "Mobile phone for
alerts"; without it, owner alerts are logged as "Owner alert not sent: No
phone on file" and never texted.

`composer run dev` opens a dashboard with four tabs (server, queue, logs,
vite); Tab switches between them, and `q` stops everything.

## 5. Run the tests

```sh
php artisan test
```

That runs the suite on SQLite. To run it on PostgreSQL 17 as well (needs Docker):

```sh
docker run --rm -d --name ops-pg-test -p 55432:5432 -e POSTGRES_PASSWORD=probe -e POSTGRES_DB=ops_test postgres:17
DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=ops_test DB_USERNAME=postgres DB_PASSWORD=probe php artisan test
docker rm -f ops-pg-test
```

Give the container a few seconds to start before the second command. GitHub
Actions runs both databases on every push (`.github/workflows/tests.yml`).

Migration checks (`migrate:fresh`, `migrate:rollback`, then `migrate` again) run with `--env=testing`, against a local, gitignored `.env.testing` (its own `APP_KEY`, and `DB_DATABASE=database/testing.sqlite`), so they never touch `database/database.sqlite`. The scratch file must be a real file, not `:memory:`: each artisan command is a new process, and an in-memory database starts empty every time, so a rollback would have nothing to undo. Create it once with `touch database/testing.sqlite` (gitignored by `database/.gitignore`); `migrate --no-interaction` will not create it. `php artisan test` is unaffected: `phpunit.xml` sets `DB_DATABASE` to `:memory:` before `.env.testing` is read, and an existing value is never replaced.

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

## 11. Texting through Twilio (after A2P)

The app texts through the fake channel until Twilio's A2P registration is
approved, and nothing in the build waits for it. When it is approved, follow
"Going live on Twilio" in `docs/sprints/sprint-1.md` (Notes for testing), then
set these five keys in `.env` and `TEXT_CHANNEL=twilio`:

- `TWILIO_ACCOUNT_SID`
- `TWILIO_AUTH_TOKEN`
- `TWILIO_MESSAGING_SERVICE_SID`
- `TWILIO_WEBHOOK_URL` (ends `/webhooks/text/twilio`)
- `TWILIO_STATUS_CALLBACK_URL` (ends `/webhooks/text/twilio/status`)

The two URLs must match what is typed into the Twilio Console exactly, or every
webhook is refused. `.env.example` has the same keys with a note.

## 12. Setting up a business: checks the sync cannot make

The sync checks the shape of a business's folder, but some facts it cannot
verify. Check these by hand for each business before it goes live.

- **`phone` in `business/profile.md` is the business's texting number on the
  platform**, the number the platform texts from (Ian, 2026-11-02). The
  booking page's "text us" lines link to it, so a customer who taps one lands
  in the platform's thread with the front desk and the change-request alert.
  Send a text to that number and confirm it arrives in the dashboard thread.
  A business without a number the platform can text from is not a fit.
  (`08-UI-SPEC.md` U-12; BLUEPRINT, Customer communication.)

- **The scheduler runs every minute.** In production cron runs
  `php artisan schedule:run` every minute (locally `composer run dev` already
  runs `schedule:work`). Held owner alerts, booking confirmations and
  reminders, and the nightly rebuild of `records/` just after midnight in the
  business's time zone depend on it. The rebuild runs once per business date,
  on the first tick from 00:10 to before 01:00, so a cron that missed 00:10
  (a deploy, a restart, a busy host) still runs it on a later tick in that
  hour. A date is marked done only after a run that succeeded, so a failed
  run (for example the business folder not mounted) is tried again on the
  next tick until 01:00. Each run holds the rebuild's lock for 30 minutes
  from when it starts. A normal stop releases the lock; only a hard kill
  (SIGKILL, a host crash) leaves it behind, and such a run marks nothing done.
  A run that started before about 00:29 and was hard-killed is tried again
  once its lock expires, inside the hour (a run started at 00:10 is tried
  again at 00:40 or 00:41); one that started at about 00:29 or later still
  holds the lock at the last tick, so that night has no rebuild. The 30-minute
  lock assumes a rebuild finishes inside 30 minutes. If no tick in that hour
  gets a run through, that
  night has no rebuild: `today.md`'s dated title shows it, a booking change
  still redraws `today.md`, and the next night catches up. In a few zones
  whose clocks jump at midnight (America/Havana, America/Santiago,
  America/Coyhaique, America/Asuncion, Africa/Cairo, Asia/Beirut,
  Atlantic/Azores) the hour from 00:10 can be missing one night a year, with
  the same result. US and European zones are not affected (Phase 9 D-10;
  `09-RESEARCH.md` Pattern 1; 09-REVIEW WR-03).

## 13. Upgrading a business folder

Some releases add a field the sync then requires. A folder synced before that
release is refused as a whole until the field is added, and the previous
version stays live. Add each field below before the next sync.

- **`visit_line:` in `rooms/schedule_and_booking/booking.md`.** A booking file
  written before the visit line needs it added; without it the sync refuses
  the whole folder (`08-REVIEW.md` IN-10). The line holds `{day}` and `{time}`
  once each, and `{service}` at most once.

## 14. Recording the walks and settling the bars (Ian)

A walk is a walk test file in a business folder, under `tests/`: a made-up
customer, the texts that customer sends one after another, and what the owner
expects to happen after each. Every sync that would make a change live replays
each walk from a recording of what the front desk model and Jev answered when
Ian ran it, and refuses the change if a walk fails. A walk with no recording is
refused too, so the version that is live stays live.

`walks:record` makes the recording. It plays each walk once with the live model
and the live Jev, against the business folder as it is on disk. It sends no
text, writes no contact, message, booking or alert, never changes the folder
and never makes a version. It keeps the model's replies and Jev's raw scores in
this instance's database, one row for each walk, stored by the walk's path, the
hash of its script and the wording it was recorded with. Recording a walk again
replaces its row. A recording is kept even when the walk's checks did not pass
under today's bars, because the bars are approved from these recordings. If the
model or Jev does not answer for a walk, nothing is kept for that walk, the
rest are still recorded, and the command ends with a failure.

Run every command in this section from the repository root. The business is the
folder `ICM_PATH` points at, which must be an absolute path when it is set. When
it is not set, the commands use the example business in `examples/example-hvac`.
The model and Jev keys come from `.env`, so these commands cost real calls.

Run `walks:record` for a business:

- before a new walk goes into that business's folder;
- when `changelog.md` names a walk as stale or as needing a new recording;
- at the first deploy, and on any new instance, before the first sync. A fresh
  install or a new instance has no recordings, so until `walks:record` has run
  for a business every one of its walks is refused as unrecorded (10 D-26, the
  same note sits in `ROADMAP.md` as a Phase 12 input).

The same is true on a developer machine: after Phase 10, the developer's own
`php artisan icm:sync` of the example folder refuses until
`php artisan walks:record` has run once.

The commands, as they work today:

- `php artisan walks:record` records every walk in the folder.
- `php artisan walks:record tests/dana.md` records only the walk named. A walk
  is named by its path in the folder, and a path that is not a walk test in the
  folder is refused. More than one path can be given.
- `php artisan walks:record tests/dana.md --variant=tests/Fixtures/booking-wording/drop-the-mechanics.json`
  records with the Booking section's two texts taken from a wording file in
  place of the front desk prompt as built, and stores the row under that
  wording's name. The sync never replays a wording row. The path is used as
  given when the file exists from where you are, and otherwise is read from the
  repository root.
- `php artisan walks:record --export=tests/Fixtures/walks` plays each walk again
  with the live model and Jev, replaces the stored row, and writes the
  recording as one JSON file in that folder named after the walk file, such as
  `tests/Fixtures/walks/dana.json`, or `dana.drop-the-mechanics.json` for a
  recording made with that wording. It does not export the rows already stored,
  and the folder is used exactly as given from where you are.
- `php artisan walks:record --calibration=tests/Fixtures/calibration/calibration-set.json --runs=3`
  records no walk. It asks Jev about each text in the calibration set three
  times and writes Jev's live scores into that file, only when every call
  answered. `--runs` is a whole number from 1 to 10 and is refused without
  `--calibration=`. The path is given with the option, never alone, and
  `--variant` and `--export` do not apply to it. This writes Jev's live scores
  into the committed calibration fixture. That file, like the exported walk
  fixtures, is committed only with the follow-up approved-values plan below,
  never on its own.
- `php artisan walks:report` only reads. For each bar Phase 10 lets move, it
  prints which plain texts would be held and which risky texts would send, at
  each candidate number, and the Booking wording results. With no
  `--calibration=` it reads `tests/Fixtures/calibration/calibration-set.json`.
- `php artisan walks:report --bar=complaint_max_yes=0.25` judges by a number you
  give in place of the one in `config/gate.php`. The name must be a bar in the
  `classifier` block of `config/gate.php`, and the value a number from 0 to 1.
  `price_max_yes`, `discount_max_yes` and `life_safety_min_yes` do not move and
  are refused. Give `--bar` more than once to try several at the same time.

A wording file is JSON with three fields. `name` uses only lowercase letters,
digits and dashes, and is never `as_built`. `booking_online` is the Booking
section's text for a customer with no visit ahead, and holds `{services}`
exactly once, where the list of services goes. `booking_visit_booked` is the text
for a customer with a visit booked ahead.
`tests/Fixtures/booking-wording/test-variant.json` shows the shape. A wording
holds no owner line and no booking address.

### The Phase 10 decision run (D-17)

Phase 10 builds the recording, the report and the gate, and stops there. The
bars and the Booking wording are approved together, by Ian, against one
recording. These are the steps, in order.

1. Write the two Booking wording files that D-18 describes, "say what the words
   are for" and "drop the mechanics". No plan wrote them, by Ian's instruction
   not to guess Booking wording. Put them outside the business folder:
   `tests/Fixtures/booking-wording/say-what-the-words-are-for.json` with the
   name `say-what-the-words-are-for`, and
   `tests/Fixtures/booking-wording/drop-the-mechanics.json` with the name
   `drop-the-mechanics`.
2. With `ICM_PATH` on the example folder, or unset, record and export every
   walk in one pass: `php artisan walks:record --export=tests/Fixtures/walks`.
   Today's Booking text is the baseline.
3. Record and export the three booking walks once with each wording:
   `php artisan walks:record tests/booked-time-question.md tests/link-again.md tests/dana.md --variant=tests/Fixtures/booking-wording/say-what-the-words-are-for.json --export=tests/Fixtures/walks`
   and
   `php artisan walks:record tests/booked-time-question.md tests/link-again.md tests/dana.md --variant=tests/Fixtures/booking-wording/drop-the-mechanics.json --export=tests/Fixtures/walks`
4. Record the calibration set:
   `php artisan walks:record --calibration=tests/Fixtures/calibration/calibration-set.json --runs=3`
5. Read the report: `php artisan walks:report`. Then run it again with
   `--bar` for each number under consideration, for example
   `php artisan walks:report --bar=complaint_max_yes=0.25`.
6. Decide, in one sitting: the bars, or a `rules/triage.md` reword for any
   check where the report says no number separates plain from real (D-13's
   stop), and the winning Booking wording, together, against this one
   recording.
7. Only then is the follow-up approved-values plan planned. It writes the
   approved values and the chosen wording test-first, commits the refreshed
   fixtures with them, and asserts D-14 against them. Phase 10 closes after that
   plan is executed, and not before.

Do not commit the exported fixtures, or the calibration set with its live
scores, on their own. Under today's bars the plain walks may fail against a
real recording, and CI would go red.
