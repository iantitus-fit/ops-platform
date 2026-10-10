# Context

Running notes keyed by topic, with source attribution. Content below is carried from docs/SPEC.md (and docs/sprints/sprint-0.md where noted), not paraphrased beyond light trimming.

## What this is
- source: docs/SPEC.md ("What this is")
- Working name "Ops Platform" (open decision: product name). Written 2026-10-10. Status: scaffold only, Sprint 0 not started.
- A client-owned operating system for a service business. Leads come in from every channel, an AI front desk finishes the conversation, and the job moves through booking, quote, invoice, payment and follow-up, with connectors to whatever the business already runs. One core (Ian's). One instance per client (theirs).
- What sets it apart: the business runs from a folder. How the business works lives in plain files using Ian's ICM method: services, hours, intake questions, how we talk, what the AI may send on its own, follow-up sequences, which model each agent uses. The owner, Ian, or the owner's own AI changes the business by editing a file. The platform checks the change, tests it against scripted customers, and only then puts it live.

## Who it is for
- source: docs/SPEC.md ("Who it is for")
- Owner-operated service businesses with leads arriving across channels (calls, texts, web forms, Angi, Thumbtack, Google, referrals) and nobody free to answer them fast. Trades first.
- Not for: a shop with few leads that is happy on Google Sheets (give them files and folders only, the RyMac model, no platform); a $5M+ contractor running full field-service dispatch (connect to their FSM tool instead of replacing it; Signpost makes the same call).

## Modules and build order
- source: docs/SPEC.md ("Modules and build order")
- Sprint 0 Foundation: folder sync and validation, versions, contacts, conversations, one timeline, fake text channel, web lead form, test chat page, records snapshot, dashboard screens. Laravel concepts: routes, controllers, validation, Eloquent, migrations, factories, artisan commands, queued jobs, the service container, Filament resources, Pest.
- Sprint 1 Text front desk: agent built from `agents/*.md`, loads routed files, asks intake questions, send-or-draft gate in code, owner takeover, Twilio driver once A2P clears. Concepts: AI SDK agents and tools, structured output, provider failover, queues.
- Sprint 2 Booking and the gate: availability from `hours.md`, booking link with tap-to-choose options, appointments, reminders, owner alerts; walk tests run on every sync and gate activation; "Dana end to end". Concepts: scheduler, notifications, Livewire pages, time zones.
- Sprint 3 Quote, invoice, paid: Legacy money rules ported test-first, Stripe payment links by text, customer approval page. Concepts: policies, database transactions, signed URLs, webhooks.
- Sprint 4 Voice: a vendor voice agent (Vapi, Retell or ElevenLabs) calling engine tools; calls and transcripts on the timeline; "they stopped texting, so call". Concepts: webhook verification, tool endpoints.
- Sprint 5 Automations: sequences from `automations/*.md` (lost-lead check-in at 7 to 10 days with a referral ask, review asks at several points, reactivation). Concepts: scheduler, idempotent jobs.
- Sprint 6 Settings by conversation, connectors: admin agent edits the folder by diff; QuickBooks, Google Calendar, lead emails parsed (Angi, Thumbtack), Zapier webhooks, MCP server for the owner's AI. Concepts: OAuth, inbound mail, MCP.
- Sprints 0 to 2 are committed. Sprint 3 onward is pulled by a real client or a job conversation, one at a time.

## Open decisions
- source: docs/SPEC.md ("Open decisions")
- Product name (working name: ops-platform).
- Where client folders sync from (Dropbox, Drive, or git only). Decide with the first client.
- Voice vendor. Decide with the first client that needs phones.
- Hosting. Decide at first deploy.
- Which entity registers A2P for Ian's own test number. (Note: docs/DECISIONS.md DEC-05 states "Ian registers A2P for his own test number"; see INFO entry in INGEST-CONFLICTS.md.)

## Sprint 0 goal and planning notes
- source: docs/sprints/sprint-0.md
- Goal: the folder drives the app, and every way a lead can arrive lands on one timeline. No AI replies yet and no real texting yet; those are Sprint 1.
- Status: not started; plan written 2026-10-10. "If `.planning/` exists, this file has been ingested and phase 1 in `.planning/ROADMAP.md` is the plan of record."
- Before starting: run `php artisan boost:install` (see docs/SETUP.md), then ask Claude Code to record the two project rules via Boost (integer cents; config only via `App\Icm\ActiveConfig`).
- Sprint 0 is structured in three steps: Step 1 the business folder and the sync; Step 2 records and the ways in; Step 3 screens and snapshots. Each step lists the Laravel concepts to walk through (Step 1: artisan commands, config and `.env` (`ICM_PATH`), migrations, Eloquent model with JSON casts, the service container; Step 2: routes, controllers, form requests, Eloquent relationships, factories, queued jobs, database transactions; Step 3: Filament resources and pages, Livewire components, events and queued listeners, unique jobs, environment gating, seeders).
- A "Walk-through log" section exists (empty): one line per concept, added the first time it shows up in the sprint.

## Practice direction (2026-10-08) background
- source: docs/DECISIONS.md (DEC-14); docs/SPEC.md (Principle 10; "Settings by conversation")
- The original 2026-10-08 practice direction document is referenced but is not part of the ingest set. DEC-14 supersedes only its clause about implementing over SignPost-class tools rather than building a product; model/vendor-agnostic, no lock-in, and settings by conversation stand.

## Motivation: framework choice
- source: docs/DECISIONS.md (DEC-13)
- ContractorHUB closed a role on Laravel experience (10-03); trades-software employers in the pipeline build on Laravel. One build serves the job search and the practice. Ian is learning Laravel (DEC-01: no autonomous or yolo runs while Ian is learning).
