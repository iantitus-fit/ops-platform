# Sprint 1: Text Front Desk

Status: in progress. `.planning/` is the plan of record: this file is the
sprint's acceptance list and the walk-through log, and phase 2 in
`.planning/ROADMAP.md` holds the plans.

## Goal

A customer who texts gets a reply that moves the conversation forward without
the owner, from an agent defined in a file, and nothing risky is ever sent
without the owner. The agent's provider, model and fallbacks are edits to its
`agents/*.md` file that go live through the normal sync, and the real Twilio
driver sits behind the same `TextChannel` interface as the fake one.

## Done when (acceptance criteria)

1. A customer who texts through the fake channel (for example from `/dev/chat`)
   gets a reply from the agent in the same conversation. The first reply says it
   is an AI, uses the voice file's sign-off, and never uses a `never_say` phrase.
   The agent then asks the matching service's intake questions, and the answers
   show on the timeline and the customer card.
2. A message involving a price, a promise, a discount or a complaint is never
   sent: it appears as a draft in the dashboard and the owner is alerted, while
   intake questions, scheduling and FAQ answers send on their own, even when a
   scripted model tries to send the draft.
3. The owner can take over a conversation so the agent stops replying until it
   is handed back, and can approve, edit and send, or discard a draft from the
   dashboard. A customer who asks for a person is handed to the `escalate_to`
   contact.
4. Changing an agent's provider, model or fallback list is an edit to its
   `agents/*.md` file that goes live through the normal sync. A broken agent
   file is rejected with a plain-words message, and when the primary provider
   fails the agent falls through to its fallback.
5. The Twilio text driver sits behind the same `TextChannel` interface and is
   selected by config alone, verified against Twilio-shaped payloads. Going live
   on Ian's test number follows A2P approval and does not gate this phase. The
   full Pest suite passes on PostgreSQL in CI as well as on SQLite locally.

## Notes for testing

## Walk-through log

One line per concept, added the first time it shows up in this sprint.

- GitHub Actions workflows and service containers (plan 02-01): `.github/workflows/tests.yml` is a recipe GitHub follows on every push. Its `pest` job runs the whole suite twice, once per entry in `matrix.database` (`sqlite` and `pgsql`), and the `services:` block starts a throwaway `postgres:17` container next to the job, with a health check so the tests wait until the database answers. The two actions it uses are pinned to full commit ids, so a changed tag upstream cannot change what runs.
- Environment variables beat `phpunit.xml` (plan 02-01): the `<env>` lines in `phpunit.xml` are defaults, and PHPUnit does not overwrite a variable that already exists. The workflow sets `DB_CONNECTION: pgsql` on the job, so the same test files run on Postgres in CI with no config change, and on your Mac you do the same by putting the five `DB_*` variables in front of `php artisan test`.
- `Http::preventStrayRequests()` (plan 02-01): in `tests/Pest.php`, inside the `beforeEach` that runs before every Feature test, this tells Laravel's HTTP client to throw on any request that no `Http::fake([...])` matches. A test that forgets to fake the model provider, Jev or Twilio now fails loudly instead of sending a real request with a real key, and `tests/Feature/Quality/TestSafetyNetTest.php` proves it, along with a check that the database driver in use is the one the run asked for.
- laravel/ai providers (plan 02-02): `config/ai.php` names each provider the app can talk to (`anthropic`, `openai`, and so on). `Ai::textProvider('anthropic')` builds one without calling it, which needs no key and no network; it throws when the name is unknown or when that provider cannot write text (`typesafe` only classifies). The sync uses that to check that an agent file names a provider that can write replies, and the Business Folder page shows the chain: the primary model first, then each fallback in file order.
- Claiming a row with a conditional update (plan 02-08): `Message::query()->whereKey($id)->whereNull('dispatched_at')->update([...])` is one SQL statement that changes the row only if the column is still empty, and `update()` returns how many rows it changed. Two requests racing for the same message cannot both get 1 back, so only one of them goes on to queue the job. `DispatchInbound` uses it so a text is queued exactly once, and `ClaimInboundBurst` uses it per row (with `handled_at`) so two runs never answer the same texts. No cache or lock is needed because the database settles the race.
- Debounced jobs (plan 02-08): `#[DebounceFor(20, maxWait: 90)]` on `ProcessInbound` tells Laravel to delay the job 20 seconds, and to replace it with a fresh delay when another job with the same `debounceId()` is queued (here the conversation id), so three quick texts become one run roughly 20 seconds after the last, and never later than 90 seconds after the first. The waiting token lives in the cache and can disappear, so the database claim above is what keeps a stray second run harmless. The sync queue in tests ignores delays, so the tests use `Queue::fake()` and call `handle()` directly.
- Data migrations (plan 02-08): a migration can change rows as well as tables. `2026_10_12_000002_backfill_dispatch_columns_on_messages.php` copies `created_at` into the two new columns for every existing message, with `DB::raw('created_at')` so the database does the copy in one statement. It is separate from the migration that adds the columns so a test can run it against rows it set up itself.
- Static methods and word-boundary patterns (plan 02-03): `CodeChecks::findings($text, $neverSay)` is called on the class itself, with no `new`, because it keeps no state between calls: the same text and lists always give the same answer. Each phrase becomes a regular expression with `\s+` between words, a character class so a curly and a straight apostrophe match alike, and `(?<![\p{L}\p{N}_])` and `(?![\p{L}\p{N}_])` at the edges, which mean "not directly next to another letter or digit". That is why `compromise` is not `promise`. The `iu` flags at the end make it ignore case and read the text as UTF-8, so accented letters work too.
- Cross-file validation (plan 02-03): a rule string like `required|string` can only look at one file's own fields. The reply texts must be checked against the `never_say` list in a different file, so `Validator::repliesErrors()` is a method that receives every loaded file and picks the voice file out of the same folder. It reads the candidate folder, never `ActiveConfig`, because the folder is not live until the sync accepts it.
- The scheduler (plan 02-09): `Schedule::job(new SweepUndispatched)->everyFiveMinutes()->withoutOverlapping();` in `routes/console.php` tells Laravel to put that job on the queue every five minutes, and `withoutOverlapping()` skips a run while the previous one still holds its lock. Something has to wake the scheduler each minute: locally `php artisan schedule:work` does it, and `AppServiceProvider` adds it to `composer run dev` (through `DevCommands::artisan`, only when the app is local). Production needs one cron line running `php artisan schedule:run` every minute, which arrives with the first deploy. The sweep leaves texts younger than two minutes to the webhook and older than 24 hours alone, and the backfill migration from plan 02-08 gave every earlier message a `dispatched_at`, so no old customer is ever texted because of it.
- Backed enums as a fixed vocabulary (plan 02-04): `enum ReplyLabel: string` in `app/Enums/ReplyLabel.php` is a closed list of the eight words an owner may write in `rules/sending.md`. Each `case` has a text value (`Price = 'price'`), and static methods on the enum hold the rules about them: `sendOnOwnAllowed()` is the three that may send on their own, and `neverOnItsOwn()` is the price, promise, discount and complaint floor. Because the floor is a method in code, `Validator::sendingRulesErrors()` and, later, the send path can ask the same question and cannot disagree. `AlertReason` does the same for owner alert events and adds `plainWords()`, a `match` over `$this` that turns each case into the words an owner reads.
- A second validator for a nested map (plan 02-04): the main rules in `rulesFor()` only say `owner_alert` is required. When it turns out to be a map, `Validator::ownerAlertErrors()` builds a second `LaravelValidator::make($alert, [...])` for its own fields (`channels`, `right_away`, `after_hours`) and prefixes each failed field with `owner_alert.` so `PlainWords` can say "owner_alert right_away item 1 must be one of ...". Rules that depend on a list's contents, such as "right_away must include life_safety", are plain PHP after that, because a rule string cannot express them.
