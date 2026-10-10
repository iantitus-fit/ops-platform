# Constraints

Sources: docs/SPEC.md (SPEC, precedence 1) and docs/sprints/sprint-0.md (SPEC, precedence 2). Where the two overlap, SPEC.md is the higher-precedence source; no contradictions were found (see ../INGEST-CONFLICTS.md).

## Two layers: files hold how the business runs, database holds what happened
- source: docs/SPEC.md (Principle 3; "The two layers")
- type: protocol
- content: Layer 1 is the business folder (ICM): owner-editable, source of truth for configuration and knowledge. Layer 2 is the engine (the Laravel app): source of truth for records (contacts, conversations, messages, appointments, quotes, invoices, payments, the activity timeline, the audit log); runs the timers, channels and money. Records do not live in files because texts, calls and payment webhooks arrive in the same second and a ledger must survive concurrent writers and half-finished operations.

## Sync pipeline (files to engine)
- source: docs/SPEC.md ("The bridge": Files to engine)
- type: protocol
- content: 1) Read the folder; skip `records/` and `changelog.md`. 2) Parse each file: YAML front matter (structured settings the engine enforces) plus markdown body (knowledge agents read word for word). 3) Validate each file against its type; errors name the file, the field and the fix in plain words an owner can act on. 4) Fingerprint the candidate version (content hash, plus git commit if the folder is a repo). 5) Walk-test the candidate (from Sprint 2): run every scripted customer in `tests/`. 6) Activate only if steps 3 and 5 pass; otherwise the previous version stays live and the owner gets a plain message saying what broke. Every attempt, pass or fail, is appended to the folder's `changelog.md`.

## Engine reads configuration only from the active compiled version
- source: docs/SPEC.md ("The bridge"); docs/sprints/sprint-0.md (Done when #10; "Before starting"; Step 1)
- type: nfr
- content: The engine reads configuration only from the active compiled version (`App\Icm\ActiveConfig`), never from disk at request time. A half-edited file never reaches a customer; rollback means activating the previous version. Application code reads business configuration only through `App\Icm\ActiveConfig`, never from the ICM folder directly. `ActiveConfig` is bound in the service container as a singleton. Nothing reads configuration from disk except the sync.

## No change goes live untested
- source: docs/SPEC.md (Principle 4)
- type: nfr
- content: Every folder change is validated and walk-tested before it activates. A failure keeps the last good version live. (Walk tests begin in Sprint 2; Sprint 0 gates on validation only.)

## Records flow back as files (engine to files)
- source: docs/SPEC.md ("The bridge": Engine to files)
- type: protocol
- content: The engine writes a readable copy of what happened into `records/`. `records/customers/<name>.md`: one card per customer (contact details, lead source and cost, status, next appointment, balance, timeline newest first: texts, call summaries, forms, appointments, payments); full call transcripts beside the card in `records/customers/<name>/calls/`. `records/customers/INDEX.md`: name, phone and file for every customer. Summary files: `today.md` (schedule), `open-leads.md`, `who-owes-us.md`, `this-week.md` (numbers). `records/CLAUDE.md`: router for these files.

## Record rendering concurrency and durability rules
- source: docs/SPEC.md ("The bridge": How the files stay right when everything happens at once); docs/sprints/sprint-0.md (Step 3)
- type: protocol
- content: 1) Each webhook (text, call transcript, payment) is verified and written to the database inside a transaction; the provider's event id is unique so a retried webhook cannot record a payment twice. 2) The write fires an event that queues a re-render of that customer's card and any summary file it touches. 3) The re-render reads the database, not the event; three events in the same second produce a card showing all three in order, whatever order the jobs ran in. Renders for one customer never overlap, and an event that lands during a render always triggers one more. 4) Files are written to a temp file and renamed so an AI never reads half a card. 5) A nightly full rebuild catches anything missed. Sprint 0 implementation: queued `RenderCustomerCard` job, unique until it starts processing, never overlapping for the same customer.

## Records files are read-only
- source: docs/SPEC.md ("The bridge"); docs/DECISIONS.md (DEC-03)
- type: protocol
- content: Editing a records file changes nothing; the next render overwrites it. To change what happened (for example record a cash payment) the owner tells the dashboard or the admin agent, which writes the database, which re-renders the card.

## Platform agents get the card fresh from the database
- source: docs/SPEC.md ("The bridge")
- type: protocol
- content: The platform's own agents use the same card rendered fresh from the database at the moment they need it, not read from disk, because a file can trail the database by a few seconds (for example a customer texts "I just paid" while the payment webhook lands). Same card, two delivery paths: on disk for the owner's AI, in context for the platform's agents. Card numbers never exist in this system; Stripe holds them. Later, an MCP server (`laravel/mcp`) answers live questions the files do not cover.

## Settings by conversation
- source: docs/SPEC.md ("The bridge": Settings by conversation)
- type: protocol
- content: The owner asks in conversation; an admin agent proposes an edit to the folder as a diff; the owner approves; the normal sync validates, tests and activates it. The AI never changes live configuration directly; it edits files like everyone else. (Sprint 6.)

## Where the folder lives
- source: docs/SPEC.md ("Where the folder lives")
- type: protocol
- content: Sprint 0: a path on disk set by `ICM_PATH`; an example business ships in `examples/`. Per client, later: a git repo the client owns, synced to wherever they and their AI can reach it (Dropbox, Google Drive, local folder); decide with the first client. Three ways to edit, all touching the same files: the owner's own AI, an editor page in the dashboard, or Ian.

## Knowledge retrieval: route, don't retrieve
- source: docs/SPEC.md ("Knowledge retrieval"); docs/DECISIONS.md (DEC-11)
- type: protocol
- content: Agents load the files their agent file and the folder's `CLAUDE.md` point to. pgvector only for big reference material (for example a 1,550-item supplier price list); that index is rebuilt from the files on sync, never edited directly.

## Business folder layout
- source: docs/SPEC.md ("The business folder")
- type: schema
- content: `<business>/` contains `CLAUDE.md` (router; points only, holds no facts), `START-HERE.md` (for the owner), `changelog.md` (written by the sync, every attempt newest first), `business/` (`CONTEXT.md`, `profile.md` type business, `hours.md` type hours, `lead-sources.md` type lead_sources, `faq.md` type knowledge, `services/` with `CONTEXT.md` and `<service>.md` type service, one file per service), `voice/how-we-talk.md` (type voice), `rules/` (`CONTEXT.md`, `sending.md` type sending_rules), `agents/` (type agent, Sprint 1), `automations/` (type automation, Sprint 5), `connectors/` (type connector, Sprint 6), `tests/` (type walk_test, Sprint 2), `records/` (GENERATED by the engine, read-only, ignored by the sync; `CLAUDE.md`, `today.md`, `open-leads.md`, `who-owes-us.md`, `this-week.md`, `customers/` with `INDEX.md` plus one card per customer). One fact, one file.

## File type contract: business
- source: docs/SPEC.md ("File types")
- type: schema
- content: File business/profile.md. Required front matter: name, phone, timezone, service_area (list). Engine use: identity in every agent prompt; timezone for all scheduling.

## File type contract: hours
- source: docs/SPEC.md ("File types")
- type: schema
- content: File business/hours.md. Required front matter: weekly (mon to sun: open/close or closed), closures (dates), booking_lead_minutes. Engine use: availability for booking.

## File type contract: lead_sources
- source: docs/SPEC.md ("File types")
- type: schema
- content: File business/lead-sources.md. Required front matter: sources, a list of key, name, cost_cents_per_lead. Engine use: attribution and cost per lead on every contact.

## File type contract: knowledge
- source: docs/SPEC.md ("File types")
- type: schema
- content: Files business/faq.md and others. Required front matter: none beyond type. Engine use: body is read by agents as reference.

## File type contract: service
- source: docs/SPEC.md ("File types"; example service file)
- type: schema
- content: Files business/services/*.md. Required front matter: name, slug, duration_minutes, book_online, price_policy (none, starting_at, fixed), price_cents (unless none), intake (list of key, ask, options). Engine use: intake questions, booking length, what agents may say about price. Example: type service, name "AC repair", slug ac-repair, duration_minutes 90, book_online true, price_policy starting_at, price_cents 8900, intake with keys symptom (options Not cooling, Won't turn on, Making noise, Leaking water, Something else) and system_age (options Under 5 years, 5 to 10 years, Over 10 years, Not sure); body: "We repair central air, heat pumps and mini-splits. We do not service window units. The $89 diagnostic fee is waived if the customer approves the repair the same visit."

## File type contract: voice
- source: docs/SPEC.md ("File types")
- type: schema
- content: File voice/how-we-talk.md. Required front matter: ai_disclosure, sign_off, never_say (list). Engine use: tone and disclosure for every outbound message.

## File type contract: sending_rules
- source: docs/SPEC.md ("File types")
- type: schema
- content: File rules/sending.md. Required front matter: send_on_own (list of action keys), draft_for_owner (list), owner_alert. Engine use: the send-or-draft gate, enforced in code.

## File type contract: agent
- source: docs/SPEC.md ("File types")
- type: schema
- content: Files agents/*.md (Sprint 1). Required front matter: name, channel, provider, model, fallback (list), loads (list of folder paths), escalate_to. Engine use: builds the agent at runtime.

## File type contract: automation
- source: docs/SPEC.md ("File types")
- type: schema
- content: Files automations/*.md (Sprint 5). Required front matter: trigger, wait, send, stop_when (list). Engine use: scheduled sequences.

## File type contract: connector
- source: docs/SPEC.md ("File types")
- type: schema
- content: Files connectors/*.md (Sprint 6). Required front matter: system, direction (in, out, both), objects, schedule. Engine use: what syncs and which way. No secrets: credentials live encrypted in the engine.

## File type contract: walk_test
- source: docs/SPEC.md ("File types")
- type: schema
- content: Files tests/*.md (Sprint 2). Required front matter: persona, channel, script (list of customer turns), expect (list of checks). Engine use: the activation gate. The markdown body of every file is free text that agents read as knowledge; the front matter is what the engine enforces.

## Sending authority is per action and enforced in code
- source: docs/SPEC.md (Principle 6)
- type: nfr
- content: Agents send intake questions, scheduling and FAQ answers on their own. Anything with a price, a promise, a discount or a complaint goes to the owner as a draft. Enforced in code, not only in the prompt.

## The AI says it is an AI
- source: docs/SPEC.md (Principle 5)
- type: nfr
- content: The AI discloses it is an AI. A human is always one message away.

## Money is integer cents; payments decide invoice status
- source: docs/SPEC.md (Principle 7); docs/sprints/sprint-0.md (Done when #10)
- type: nfr
- content: All money values are stored as integer cents, never floats. Payments decide invoice status (carried over from Legacy).

## One instance per client; client owns data and folder
- source: docs/SPEC.md (Principle 9); docs/DECISIONS.md (DEC-09)
- type: nfr
- content: One instance per client. The client owns their data and their folder. Ian keeps the core.

## Model- and vendor-agnostic; integrate, don't build
- source: docs/SPEC.md (Principles 8 and 10)
- type: nfr
- content: Any model per agent, swappable; connectors over lock-in. Integrate rather than build telephony, voice models, payments, accounting.

## Product principles (non-testable framing)
- source: docs/SPEC.md (Principles 1 and 2)
- type: nfr
- content: Headline metric is minutes from first contact to a booked appointment ("first to complete the conversation, not first to reply"). The record writes itself: calls, texts, forms and payments land on one timeline per customer and the owner never retypes anything.

## Technology stack
- source: docs/SPEC.md ("Stack"); docs/DECISIONS.md (DEC-02, DEC-04, DEC-06, DEC-07, DEC-08)
- type: nfr
- content: Framework Laravel 13.35, PHP 8.3+ (scaffolded 2026-10-10; DECISIONS.md fixes the toolchain at PHP 8.5 via php.new). Back office Filament 5.10 (Livewire 4); customer pages in Livewire. AI laravel/ai 1.2 (provider and model per agent, failover lists, client-owned keys possible). Tests Pest 5.3, SQLite in memory. Agent tooling Laravel Boost 2.10 (MCP for Claude Code, Laravel guidelines, project rules in `.ai/rules`). Database SQLite locally in Sprint 0; Postgres in CI and production from Sprint 1. Queues database driver locally; Redis and Horizon in production. Texts and numbers Twilio (fake channel until A2P clears). Payments Stripe payment links (not Cashier). Voice: vendor, chosen with first client that needs phones. Hosting decided at first deploy (Laravel Cloud or Forge); one instance per client.

## Money rules carried over from Legacy (Sprint 3)
- source: docs/SPEC.md ("Rules carried over from Legacy")
- type: nfr
- content: Each becomes a Pest test before the code exists. A deposit invoice is a percent of the approved quote; the final invoice takes it back as its own credit line recording which invoice it came from; an invoice total is recalculated from its own lines, net of credits. Payments decide whether an invoice is paid or partly paid; staff can set only draft, sent and void by hand. The next invoice number is the highest existing number plus one, never a count. A quote can be approved only while draft, sent or viewed; one function approves whichever door the approval comes through (customer page with signature, or staff without). Customer links expire after a configurable number of days and answer 410 Gone. Not carried over: "job" meaning three things (here a job is one thing, defined once); "sent" meaning different events on three objects and "total" versus "grand_total" (one name, one meaning); tax silently defaulting to 7% in one place and 0% in another (here an empty rate is a validation error, never a silent default).

## Build process
- source: docs/SPEC.md ("How we build"); docs/DECISIONS.md (DEC-01)
- type: protocol
- content: Spec first, then tests, then code. Each sprint has a plan in `docs/sprints/` with acceptance criteria. Claude Code builds; Ian reviews. Each new Laravel concept gets a short walk-through the first time it appears, logged in that sprint's file (walk-through log). Boost is installed so Claude Code reads Laravel guidance and records project rules in `.ai/rules`. A cartographer map starts in Sprint 1.

## Sprint 0 data model: icm_versions
- source: docs/sprints/sprint-0.md (Step 1)
- type: schema
- content: Table `icm_versions`: fingerprint, git_sha (nullable), compiled (json), errors (json), status (active, rejected, superseded), activated_at. Eloquent model with JSON casts.

## Sprint 0 data model: contacts
- source: docs/sprints/sprint-0.md (Step 2)
- type: schema
- content: Table `contacts`: name, phone_e164 (unique, nullable), email, status (lead, customer, lost), lead_source_key, acquisition_cost_cents.

## Sprint 0 data model: conversations
- source: docs/sprints/sprint-0.md (Step 2)
- type: schema
- content: Table `conversations`: contact_id, channel (sms, web, voice, email), status (open, closed), last_message_at.

## Sprint 0 data model: messages
- source: docs/sprints/sprint-0.md (Step 2)
- type: schema
- content: Table `messages`: conversation_id, direction (in, out), author (customer, agent, owner), body, status (draft, queued, sent, delivered, failed), provider, provider_message_id, meta (json).

## Sprint 0 data model: activities
- source: docs/sprints/sprint-0.md (Step 2)
- type: schema
- content: Table `activities`: contact_id, type, summary, subject (polymorphic), occurred_at.

## Sprint 0 text channel interface
- source: docs/sprints/sprint-0.md (Step 2)
- type: api-contract
- content: `App\Channels\TextChannel` interface with `FakeTextChannel` (stores and logs) and a `TwilioTextChannel` stub for Sprint 1. The driver comes from config.

## Sprint 0 inbound routes and action
- source: docs/sprints/sprint-0.md (Step 2)
- type: api-contract
- content: `POST /webhooks/text/fake` (same shape Twilio will send) and `POST /leads` (web form). Both call one action, `App\Actions\RecordInbound`, which finds or creates the contact by phone, appends to the open conversation, writes the activity, then dispatches a `ProcessInbound` job. The job is empty in Sprint 0; the Sprint 1 agent plugs in there.

## Phone numbers stored as E.164
- source: docs/sprints/sprint-0.md (Step 2)
- type: nfr
- content: Phone numbers stored in E.164. Use giggsey/libphonenumber-for-php-lite rather than hand-rolled parsing.

## Sprint 0 ICM classes and commands
- source: docs/sprints/sprint-0.md (Step 1)
- type: protocol
- content: `App\Icm\Loader` reads the folder and splits front matter from body (symfony/yaml and league/commonmark already installed). `App\Icm\Validator` has one rule set per file type from SPEC.md with owner-actionable messages (example: "business/services/ac-repair.md: price_policy must be one of none, starting_at, fixed"). Artisan commands `icm:sync`, `icm:status`. Example business `examples/example-hvac/` ("Example Heating & Air") with every Sprint 0 file type (CLAUDE.md, START-HERE.md, changelog.md, business/profile.md, hours.md, lead-sources.md, faq.md, two services, voice/how-we-talk.md, rules/sending.md, records/README.md); agents/, automations/, connectors/ and tests/ exist with a README naming the sprint that fills them.

## /dev/chat is local-only
- source: docs/sprints/sprint-0.md (Done when #9; Step 3)
- type: nfr
- content: `/dev/chat` (Livewire) posts customer texts to the fake webhook and shows the thread. It returns 404 outside the local environment.

## Sprint 0 out-of-scope boundary
- source: docs/sprints/sprint-0.md ("Out of scope")
- type: nfr
- content: AI replies, Twilio, booking, money, voice, automations are not built in Sprint 0.
