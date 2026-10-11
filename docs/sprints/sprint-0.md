# Sprint 0: Foundation

Status: not started. Plan written 2026-10-10. If `.planning/` exists, this
file has been ingested and phase 1 in `.planning/ROADMAP.md` is the plan of record.

## Goal

The folder drives the app, and every way a lead can arrive lands on one
timeline. No AI replies yet and no real texting yet. Those are Sprint 1.

## Done when (acceptance criteria)

1. `php artisan icm:sync` reads `examples/example-hvac`, compiles a version and
   makes it active. `php artisan icm:status` shows the active version, its file
   count and when it went live.
2. Break a file (delete a required field, or set `price_policy: maybe`) and sync
   again. The sync fails. The message names the file and the field in plain
   words. The previous version stays active. The failure is appended to the
   folder's `changelog.md`.
3. A text from a new number through the fake channel creates a contact, a
   conversation, a message and a timeline entry. A second text from the same
   number lands in the same conversation.
4. A web form lead with `source=angi` creates or matches a contact, records the
   source and the cost per lead from `business/lead-sources.md`, and lands on
   the same timeline.
5. A text and a form from the same phone number end up on one contact.
6. A reply the owner types in the dashboard goes out through the fake channel
   and is stored as sent.
7. Every inbound text or form re-renders that customer's card in
   `records/customers/` and updates `INDEX.md` and `open-leads.md`. Two inbound
   events for one customer in the same second produce one card that shows both.
   `php artisan records:rebuild` regenerates everything. The sync ignores
   `records/`.
8. Dashboard: Contacts (list plus a timeline view), Conversations (thread with a
   reply box), and a Business Folder page (active version, files, last sync
   result, errors, a Sync Now button).
9. A local-only test chat page at `/dev/chat` lets Ian text "as a customer" and
   watch the thread update. It returns 404 outside the local environment.
10. Every Pest test passes. Money fields are integer cents. Nothing reads
    configuration from disk except the sync.

## Before starting

Run `php artisan boost:install` (see docs/SETUP.md), then ask Claude Code to
record two project rules with Boost:

- "Remember that all money values are stored as integer cents, never floats."
- "Remember that application code reads business configuration only through
  App\Icm\ActiveConfig, never from the ICM folder directly."

## Step 1: The business folder and the sync

Build:
- `examples/example-hvac/`, a fictional business ("Example Heating & Air"),
  with every Sprint 0 file type: CLAUDE.md, START-HERE.md, changelog.md,
  business/profile.md, hours.md, lead-sources.md, faq.md, two services,
  voice/how-we-talk.md, rules/sending.md, records/README.md. The agents/,
  automations/, connectors/ and tests/ folders exist with a README saying which
  sprint fills them.
- `App\Icm\Loader`: reads the folder and splits front matter from body
  (symfony/yaml and league/commonmark are already installed).
- `App\Icm\Validator`: one rule set per file type from SPEC.md, with messages an
  owner can act on ("business/services/ac-repair.md: price_policy must be one
  of none, starting_at, fixed").
- `icm_versions` table: fingerprint, git_sha (nullable), compiled (json),
  errors (json), status (active, rejected, superseded), activated_at.
- `App\Icm\ActiveConfig`: the only way the app reads configuration. Bound in
  the service container as a singleton.
- Artisan commands: `icm:sync`, `icm:status`.

Tests: a valid sync activates. Each broken-file case is rejected with the right
message. A rejected sync leaves the previous version active. Files in records/
are ignored. The changelog line is written.

Laravel concepts to walk through: artisan commands, config and `.env` (`ICM_PATH`),
migrations, an Eloquent model with JSON casts, the service container.

## Step 2: Records and the ways in

Build:
- Migrations and models:
  - contacts: name, phone_e164 (unique, nullable), email, status (lead,
    customer, lost), lead_source_key, acquisition_cost_cents
  - conversations: contact_id, channel (sms, web, voice, email), status (open,
    closed), last_message_at
  - messages: conversation_id, direction (in, out), author (customer, agent,
    owner), body, status (draft, queued, sent, delivered, failed), provider,
    provider_message_id, meta (json)
  - activities: contact_id, type, summary, subject (polymorphic), occurred_at
- `App\Channels\TextChannel` interface, with `FakeTextChannel` (stores and logs)
  and a `TwilioTextChannel` stub for Sprint 1. The driver comes from config.
- Inbound routes: `POST /webhooks/text/fake` (same shape Twilio will send) and
  `POST /leads` (web form). Both call one action, `App\Actions\RecordInbound`,
  which finds or creates the contact by phone, appends to the open conversation,
  writes the activity, then dispatches a `ProcessInbound` job. The job is empty
  in Sprint 0; the Sprint 1 agent plugs in there.
- Phone numbers stored in E.164. Use giggsey/libphonenumber-for-php-lite rather
  than hand-rolled parsing.

Tests: acceptance criteria 3 to 6.

Laravel concepts to walk through: routes, controllers, form requests, Eloquent
relationships, factories, queued jobs, database transactions.

## Step 3: Screens and snapshots

Build:
- Filament: a Contacts resource (table: name, phone, source, status, last
  activity; view page with the timeline), a Conversations resource (thread and a
  reply box that sends through TextChannel), and a Business Folder custom page.
- `/dev/chat`: a Livewire page that posts customer texts to the fake webhook and
  shows the thread. Local environment only.
- Records: `App\Records\CustomerCard` renders a card from the database. A
  queued `RenderCustomerCard` job runs per customer (unique until it starts
  processing, never overlapping for the same customer) and writes through a
  temp file and rename. Also `records/customers/INDEX.md`,
  `records/open-leads.md`, `records/CLAUDE.md`, and `php artisan records:rebuild`.
- A seeder with a few fictional contacts and conversations.

Tests: a card matches the database. Two events for one customer yield one card
showing both. The sync never reads `records/`. `/dev/chat` is 404 outside local.
Each Filament page loads for a logged-in user.

Laravel concepts to walk through: Filament resources and pages, Livewire
components, events and queued listeners, unique jobs, environment gating, seeders.

## Out of scope

AI replies, Twilio, booking, money, voice, automations.

## Walk-through log

One line per concept, added the first time it shows up in this sprint.

- Artisan commands (plan 01-01): `app/Console/Commands/IcmSync.php` is a command you run as `php artisan icm:sync`. The `#[Signature('icm:sync')]` attribute above the class is its name, `#[Description(...)]` is the help text, and `handle()` is what runs and returns 0 for success or 1 for failure.
- Config files and `.env` (plan 01-01): `config/icm.php` holds the business folder path, and its value comes from `ICM_PATH` in `.env`, falling back to `examples/example-hvac`. Code asks for it with `config('icm.path')` and never reads `.env` directly, so it keeps working when Laravel caches configuration.
- Migrations (plan 01-01): `database/migrations/*_create_icm_versions_table.php` describes the `icm_versions` table in PHP. `php artisan migrate` builds it, and the `down()` method undoes it, so the database shape is recorded in git alongside the code.
- Eloquent model with JSON and enum casts (plan 01-01): `app/Models/IcmVersion.php` is one row of `icm_versions`. Its `casts()` method turns the JSON columns into PHP arrays and the `status` text into the `VersionStatus` enum, so code compares `VersionStatus::Active` instead of the string `'active'`.
- Factories (plan 01-01): `database/factories/IcmVersionFactory.php` builds fake `icm_versions` rows for tests, with named states `active()`, `rejected()` and `superseded()` for the three situations a version can be in.
- The service container and scoped bindings (plan 01-01): Laravel builds objects for you and hands them to anything that asks by type. `AppServiceProvider` binds `ActiveConfig` as "scoped", meaning one copy per web request or queued job, so a long-running queue worker picks up a new version after a sync instead of holding the old one forever.
- Cache locks (plan 01-01): `Cache::lock('icm:sync', 120)` in `FolderSync` is a "one at a time" token. If a sync already holds it, a second `icm:sync` gets `Busy` and changes nothing, so two syncs cannot activate two versions at once.
- Database transactions (plan 01-01): `DB::transaction(...)` in `FolderSync` makes "retire the old version" and "create the new one" succeed or fail together, so there is never a moment with zero or two live versions.
- Pest feature tests with a temp-folder harness (plan 01-01): `tests/Pest.php` copies `examples/example-hvac` into a fresh temp folder before every Feature test and points `icm.path` at it, so tests can break and rewrite files freely without touching the repo.
- Boost project rules (plan 01-01): the files in `.ai/rules` are rules Claude must follow when editing matching paths, such as "money is integer cents". They are written through Boost's `RuleRepository` rather than by hand, and `tests/Feature/Quality/ProjectRulesTest.php` fails if they disappear.
- Laravel's Validator (plan 01-02): `app/Icm/Validator.php` hands each file's front matter and a list of rules, such as `required|integer:strict|min:1`, to `Validator::make(...)`. After `passes()` or `fails()`, `failed()` returns which rule broke on which field, and `app/Icm/PlainWords.php` turns each pair into a sentence for the owner, so Laravel's own wording never reaches them. `integer:strict` and `boolean:strict` refuse `"90"` and `yes`, which plain `integer` and `boolean` would let through, and `bail` stops at the first broken rule per field so one mistake is reported once.
- Pest datasets (plan 01-02): `dataset('broken fields', [...])` in `tests/Feature/Icm/ValidatorTest.php` is a table of rows, each a file, a change and the exact sentence expected, and `->with('broken fields')` runs the same test body once per row, so adding a case is adding one line.
- Atomic file writes (plan 01-02): `app/Icm/Changelog.php` saves `changelog.md` with `File::replace`, which writes a temporary file next to it and renames it into place. A crash halfway leaves the old changelog whole instead of a half-written one.
- Fixing the clock in tests (plan 01-02): `Carbon::setTestNow(...)` in `tests/Feature/Icm/ChangelogTest.php` freezes what `now()` returns, so a test can expect the exact heading `## 2026-10-10 14:32 · Live as v1 (8 files)` and check the same moment shown in Chicago and New York time.
- Routes (plan 01-03): `routes/web.php` maps a URL and method to code. `Route::post('/webhooks/text/fake', ...)->name('webhooks.text.fake')` says "a POST to this address runs `FakeTextWebhookController`", and the name lets code and tests write `route('webhooks.text.fake')` instead of repeating the address.
- Controllers (plan 01-03): `app/Http/Controllers/FakeTextWebhookController.php` has one job, so it has a single `__invoke` method that Laravel runs when the route matches. It stays thin: read the text, hand it to `RecordInbound`, answer Twilio with an empty `<Response>`.
- Form requests (plan 01-03): `app/Http/Requests/FakeTextWebhookRequest.php` holds the checks on the incoming form body and runs before the controller. `rules()` says which fields are required and how long they may be, and the `after()` hook runs one more check once those pass, here whether `From` is a phone number we can read. When a check fails, `failedValidation()` answers 422 with the errors as JSON, because a webhook has no page to be sent back to.
- Middleware (plan 01-03): `app/Http/Middleware/EnsureTextDriverIsFake.php` runs before the controller and stops with a 404 unless `channels.text.driver` is `fake`, so the unsigned test webhook cannot be used once Twilio is on. `bootstrap/app.php` also tells Laravel's forgery check to skip `webhooks/*`, since Twilio cannot send the token a browser form carries. Tests skip that check, so only a real `curl` against `php artisan serve` shows it works.
- Eloquent relationships and enum casts (plan 01-03): methods like `Contact::openConversation()`, `Conversation::messages()` and `Activity::subject()` describe how two tables join, so code reads `$contact->openConversation` instead of writing a query. The `casts()` methods turn text columns into enums such as `MessageChannel::Sms`, and `Relation::enforceMorphMap` in `AppServiceProvider` stores the short name `message` in `activities.subject_type` instead of a class name.
- Factory states (plan 01-03): `ContactFactory::fromSource('angi', 4500)`, `ConversationFactory::closed()` and `MessageFactory::fromOwner()` build test rows in one line. Phone numbers use a real area code with the reserved 555-01XX line, because libphonenumber rejects numbers with a 555 area code.
- Queued jobs and `afterCommit` (plan 01-03): `app/Jobs/ProcessInbound.php` is a job, a small class put on the queue instead of run during the request. `->afterCommit()` holds it until the database transaction is saved, so a worker can never pick up a job for a message that is not in the database yet.
- Transactions and savepoints (plan 01-03): `RecordInbound` wraps the contact, conversation, message and timeline entry in one `DB::transaction`, so all four exist or none do. Creating the conversation sits in a transaction of its own inside that one, which is a savepoint, a checkpoint the database can roll back to without abandoning the outer transaction, for when two texts race to open the first conversation. The database index `conversations_one_open_per_contact` decides the winner.
- Events (plan 01-03): `app/Events/CustomerRecordChanged.php` is an announcement, "something changed for contact 17", that other code can listen for. `ShouldDispatchAfterCommit` delays it until the save is committed. Nothing listens yet; plan 01-05 adds the listener that re-renders the customer card.
- Interface binding in the container (plan 01-03): `TextChannel` is an interface, a list of what any text provider must do. `AppServiceProvider` tells the container "when someone asks for a `TextChannel`, read `config('channels.text.driver')` and hand over the fake or the Twilio one", so switching providers in Phase 2 is a `.env` change and not a code change.
- Composer packages (plan 01-03): `composer require giggsey/libphonenumber-for-php-lite` added the phone-number library to `composer.json` and pinned its exact version in `composer.lock`. `app/Support/PhoneNumber.php` wraps it, so the rest of the app asks for E.164 and never touches the library.
- Named rate limiters (plan 01-04): `RateLimiter::for('leads', ...)` in `AppServiceProvider::boot()` defines a limit with a name, here 10 requests a minute for each IP address. The route opts in with `->middleware('throttle:leads')`, and the 11th post in a minute gets a 429 "too many requests" answer, so a bot cannot flood the public lead form.
- Form request `after()` hooks (plan 01-04): `app/Http/Requests/StoreLeadRequest.php` lists the simple per-field rules in `rules()`, then its `after()` method runs one check that needs two fields at once: a lead must have a phone we can read or an email. Rules look at one field at a time, so "phone or email" belongs in the hook.
- JSON responses (plan 01-04): `LeadController::store` answers `response()->json(['received' => true])`, which sends `{"received":true}` with a 200 status and a JSON content type, something a website's script can read. A rejected form answers 422 with the errors as JSON, through the same `failedValidation()` override the fake text webhook uses.
- CSRF exemptions for public endpoints (plan 01-04): browsers prove a form came from our own pages with a hidden token, and Laravel's forgery check refuses posts without one with a 419. A lead form on a client's own website cannot carry that token, so `bootstrap/app.php` lists `leads` next to `webhooks/*` in `preventRequestForgery(except: [...])`. Only those two address patterns are exempt, so the admin panel keeps the check. Tests skip the check, so `LeadFormTest` asserts the exemption list and a real `curl` against `php artisan serve` is the final proof.
