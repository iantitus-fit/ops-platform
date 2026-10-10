# Requirements

No document in the ingest set is typed PRD. Per the orchestrator's instruction, requirements are derived from the "Done when (acceptance criteria)" list of docs/sprints/sprint-0.md (type SPEC, manifest precedence 2). All requirements below belong to Sprint 0 / Phase 1. Later sprints (1 to 6) are described in docs/SPEC.md "Modules and build order" and are recorded in context.md, not as requirements (Sprints 0 to 2 committed; Sprint 3 onward pulled by a real client).

Acceptance text is taken from the source; "Tests:" lines in the sprint steps name the Pest tests expected to cover each area.

## REQ-icm-sync-activate
- source: docs/sprints/sprint-0.md (Done when #1; Step 1)
- description: The folder drives the app. Syncing the example business folder compiles a version and makes it active, and status can be inspected.
- acceptance: `php artisan icm:sync` reads `examples/example-hvac`, compiles a version and makes it active. `php artisan icm:status` shows the active version, its file count and when it went live. Test: a valid sync activates.
- scope: ICM folder sync, `icm_versions`, `App\Icm\Loader`, `App\Icm\Validator`, `App\Icm\ActiveConfig`, `icm:sync`, `icm:status`, `examples/example-hvac/`

## REQ-icm-sync-reject-broken
- source: docs/sprints/sprint-0.md (Done when #2; Step 1)
- description: A broken folder change never goes live; the failure is explained to the owner in plain words and the previous version stays active.
- acceptance: Break a file (delete a required field, or set `price_policy: maybe`) and sync again. The sync fails. The message names the file and the field in plain words. The previous version stays active. The failure is appended to the folder's `changelog.md`. Tests: each broken-file case is rejected with the right message; a rejected sync leaves the previous version active; the changelog line is written.
- scope: validation, plain-language errors, rollback to last good version, changelog.md

## REQ-inbound-text-fake
- source: docs/sprints/sprint-0.md (Done when #3; Step 2)
- description: An inbound text through the fake channel creates the contact, conversation, message and timeline entry, and later texts from the same number thread together.
- acceptance: A text from a new number through the fake channel creates a contact, a conversation, a message and a timeline entry. A second text from the same number lands in the same conversation.
- scope: `POST /webhooks/text/fake`, `App\Actions\RecordInbound`, `ProcessInbound` job (empty in Sprint 0), contacts, conversations, messages, activities, `TextChannel`/`FakeTextChannel`

## REQ-inbound-web-lead
- source: docs/sprints/sprint-0.md (Done when #4; Step 2)
- description: A web form lead is recorded with its source and cost per lead from the folder config and lands on the same timeline.
- acceptance: A web form lead with `source=angi` creates or matches a contact, records the source and the cost per lead from `business/lead-sources.md`, and lands on the same timeline.
- scope: `POST /leads`, lead source attribution, `acquisition_cost_cents`, `lead_source_key`

## REQ-contact-match-by-phone
- source: docs/sprints/sprint-0.md (Done when #5; Step 2)
- description: Channels converge on one contact per phone number.
- acceptance: A text and a form from the same phone number end up on one contact. Phone numbers are stored in E.164 using giggsey/libphonenumber-for-php-lite.
- scope: contact matching, `phone_e164` (unique, nullable)

## REQ-owner-reply-fake-channel
- source: docs/sprints/sprint-0.md (Done when #6; Step 2, Step 3)
- description: The owner can reply from the dashboard through the text channel.
- acceptance: A reply the owner types in the dashboard goes out through the fake channel and is stored as sent.
- scope: Conversations reply box, `TextChannel`, `FakeTextChannel`, message status

## REQ-records-render-customer-card
- source: docs/sprints/sprint-0.md (Done when #7; Step 3)
- description: Every inbound event re-renders the customer's card and summary files; a full rebuild command exists; the sync ignores `records/`.
- acceptance: Every inbound text or form re-renders that customer's card in `records/customers/` and updates `INDEX.md` and `open-leads.md`. Two inbound events for one customer in the same second produce one card that shows both. `php artisan records:rebuild` regenerates everything. The sync ignores `records/`. Tests: a card matches the database; two events for one customer yield one card showing both; the sync never reads `records/`.
- scope: `App\Records\CustomerCard`, queued `RenderCustomerCard` (unique until processing, no overlap per customer, temp file plus rename), `records/customers/INDEX.md`, `records/open-leads.md`, `records/CLAUDE.md`, `records:rebuild`

## REQ-dashboard-screens
- source: docs/sprints/sprint-0.md (Done when #8; Step 3)
- description: Filament dashboard screens for contacts, conversations and the business folder.
- acceptance: Dashboard: Contacts (list plus a timeline view), Conversations (thread with a reply box), and a Business Folder page (active version, files, last sync result, errors, a Sync Now button). Test: each Filament page loads for a logged-in user.
- scope: Filament Contacts resource (table: name, phone, source, status, last activity; view page with timeline), Conversations resource, Business Folder custom page, seeder with fictional contacts and conversations

## REQ-dev-chat-page
- source: docs/sprints/sprint-0.md (Done when #9; Step 3)
- description: A local-only test chat page for texting "as a customer".
- acceptance: A local-only test chat page at `/dev/chat` lets Ian text "as a customer" and watch the thread update. It returns 404 outside the local environment. Test: `/dev/chat` is 404 outside local.
- scope: `/dev/chat` Livewire page, posts to fake webhook, environment gating

## REQ-suite-green-money-config-rules
- source: docs/sprints/sprint-0.md (Done when #10; Before starting)
- description: Suite-wide quality bar for Sprint 0.
- acceptance: Every Pest test passes. Money fields are integer cents. Nothing reads configuration from disk except the sync. Two project rules are recorded with Boost: all money values are integer cents, never floats; application code reads business configuration only through `App\Icm\ActiveConfig`, never from the ICM folder directly.
- scope: test suite, integer cents money model, `ActiveConfig` as sole config read path, Boost project rules (`.ai/rules`)

## Out of scope for Sprint 0
- source: docs/sprints/sprint-0.md
- description: AI replies, Twilio, booking, money, voice, automations.
