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
