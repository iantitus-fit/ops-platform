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
