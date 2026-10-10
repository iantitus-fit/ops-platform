# Decisions (locked)

Source document: docs/DECISIONS.md (type ADR, manifest precedence 0, locked: true). The document is a dated log with no Status field; per the manifest and the doc's own statement ("decisions as locked"), every entry is treated as locked. Entries are in the source's order (newest first; all dated 2026-10-10). IDs (DEC-NN) are assigned by the synthesizer in source order for traceability; they do not appear in the source.

## DEC-01: GSD Core plans and runs the build
- source: docs/DECISIONS.md
- status: locked
- decision: GSD Core plans and runs the build. Installed per project (`--claude --local`), not globally. Docs are ingested with `docs/gsd-ingest.yaml`, decisions as locked. Settings: `use_worktrees` off (worktree checkouts have no `vendor/`, `.env` or database, so tests cannot run there); one branch per phase that Ian merges; TDD mode on; test command `php artisan test`; interactive mode; balanced models (Opus plans, Sonnet builds). No autonomous or yolo runs while Ian is learning Laravel. GSD commits locally per task; nothing pushes.
- scope: build process, GSD configuration, git workflow, test command

## DEC-02: PHP via php.new
- source: docs/DECISIONS.md
- status: locked
- decision: PHP via php.new (Herd Lite: PHP 8.5, Composer, Laravel installer in `~/.config/herd-lite/bin`). Checked on the Linux build of the same Herd Lite 8.5: the lock file's platform requirements pass, including intl for Filament.
- scope: PHP runtime and tooling

## DEC-03: Records flow back as files, per customer, on every event
- source: docs/DECISIONS.md
- status: locked
- decision: Records flow back as files, per customer, on every event (Ian's model). Texts, call transcripts and payments are written to the database first; each one re-renders that customer's card in `records/customers/` from the database, plus the summary files, so the owner's AI routes to a file instead of querying. Platform agents get the same card rendered fresh from the database. Records files are read-only; changes go through the dashboard or the admin agent.
- scope: records/ files, customer cards, summary files, read-only records, agent access to customer data

## DEC-04: Scaffolded stack
- source: docs/DECISIONS.md
- status: locked
- decision: Scaffolded Laravel 13.35, Filament 5.10 (admin panel at /admin), laravel/ai 1.2, Pest 5.3, Boost 2.10. Default tests pass and the panel login loads. Boost is in composer.json but not installed: Ian runs `php artisan boost:install` on his Mac so the MCP config points at his PHP.
- scope: scaffold, framework and package versions, Boost install

## DEC-05: Fake text channel until A2P clears
- source: docs/DECISIONS.md
- status: locked
- decision: Fake text channel until A2P clears. Ian registers A2P for his own test number; the build does not wait on approval.
- scope: text channel, A2P registration, Twilio timing

## DEC-06: Dev on SQLite, CI and production on Postgres from Sprint 1
- source: docs/DECISIONS.md
- status: locked
- decision: Dev on SQLite, CI and production on Postgres from Sprint 1. Zero local setup for Sprint 0; the suite runs on Postgres in CI so the two cannot drift (Legacy checked Postgres separately).
- scope: dev and CI database, production database

## DEC-07: laravel/ai for agents
- source: docs/DECISIONS.md
- status: locked
- decision: laravel/ai for agents. First-party, model-agnostic (Anthropic, OpenAI, Gemini, local, OpenRouter, any OpenAI-compatible endpoint), provider and model per agent, failover lists, client-owned keys possible. The model choice lives in each agent's file.
- scope: AI/agent SDK, model and provider selection

## DEC-08: Filament 5 for the back office, Livewire for customer pages
- source: docs/DECISIONS.md
- status: locked
- decision: Filament 5 for the back office, Livewire for customer pages. One UI stack. With ICM carrying configuration, the panel stays thin: inbox, schedule, money. Inertia with React stays available if a client-facing app ever needs it.
- scope: UI stack, back office, customer-facing pages

## DEC-09: One instance per client
- source: docs/DECISIONS.md
- status: locked
- decision: One instance per client. Matches the client-owns-it offer. If this ever runs as multi-tenant SaaS, tenancy is a retrofit; accepted.
- scope: deployment and tenancy model

## DEC-10: ICM is the foundation
- source: docs/DECISIONS.md
- status: locked
- decision: ICM is the foundation. Files hold how the business runs (config and knowledge); the database holds what happened (records and money). Sync validates, walk-tests and only then activates; the engine reads config only from the active compiled version. Snapshots flow back into `records/` for the owner's own AI. Clients change their business by editing a file, and this is what sets the practice apart.
- scope: ICM, configuration vs records split, sync and activation

## DEC-11: Route, don't retrieve
- source: docs/DECISIONS.md
- status: locked
- decision: Route, don't retrieve. Agents load routed files whole. pgvector only for large reference material, rebuilt from files.
- scope: knowledge retrieval, pgvector

## DEC-12: Rebuild, not clone
- source: docs/DECISIONS.md
- status: locked
- decision: Rebuild, not clone. Port Legacy's rules and tests, not its code. Legacy's job/estimate collisions stay behind.
- scope: Legacy port

## DEC-13: Laravel
- source: docs/DECISIONS.md
- status: locked
- decision: Laravel. ContractorHUB closed a role on Laravel experience (10-03); the trades-software employers in the pipeline build on it. One build serves the job search and the practice.
- scope: framework choice

## DEC-14: Supersedes one clause of the 2026-10-08 practice direction
- source: docs/DECISIONS.md
- status: locked
- decision: Supersedes one clause of the 2026-10-08 practice direction ("implement over SignPost-class tools ... rather than build a product"). The rest stands and is designed in: model- and vendor-agnostic, no lock-in, and the target experience of a dashboard with an AI inside that changes settings on request (settings by conversation, editing the folder). Implementing over existing tools stays the right call for a client who does not need to own the system.
- scope: practice direction (build a product vs implement over existing tools)
