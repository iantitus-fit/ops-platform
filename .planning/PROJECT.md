# Ops Platform

## What This Is

A client-owned operating system for owner-operated service businesses (trades first). Leads arrive from every channel, an AI front desk finishes the conversation, and the job moves through booking, quote, invoice, payment and follow-up. One core (Ian's), one instance per client (theirs). The business runs from a folder: services, hours, intake questions, how we talk, what the AI may send on its own, and which model each agent uses live in plain files. The owner, Ian, or the owner's own AI changes the business by editing a file; the platform validates the change, tests it against scripted customers, and only then puts it live.

"Ops Platform" is a working name. The product name is an open decision.

## Core Value

Finish the customer conversation fast: minutes from first contact to a booked appointment, with the business running from a folder the owner controls and no change going live untested.

## Business Context

- **Customer**: Owner-operated service businesses with leads across calls, texts, web forms, Angi, Thumbtack, Google and referrals, and nobody free to answer them fast. Trades first.
- **Revenue model**: Ian's practice. One instance per client; the client owns their data and folder, Ian keeps the core. Pricing and packaging are not specified in the source docs.
- **Success metric**: Minutes from first contact to a booked appointment (SPEC headline metric: "first to complete the conversation, not first to reply").
- **Strategy notes**: docs/SPEC.md (product), docs/DECISIONS.md (locked decisions), docs/sprints/sprint-0.md (Sprint 0 plan). The 2026-10-08 practice direction is referenced by the docs but is not in the repo.

## Requirements

Full list with IDs: `.planning/REQUIREMENTS.md`. Roadmap: `.planning/ROADMAP.md`.

### Validated

- ✓ The folder drives the app: sync validates, versions and activates; a broken change never goes live and the previous version stays active — Phase 1
- ✓ Every way a lead can arrive (fake text channel, web form, dev chat) lands on one contact and one timeline, with source and cost per lead from the folder — Phase 1
- ✓ The owner works from a Filament dashboard: contacts, conversations with a reply box, and a Business Folder page — Phase 1
- ✓ Every event re-renders that customer's card in `records/` so the owner's own AI can route to a file instead of querying — Phase 1
- ✓ A text front desk built from `agents/*.md` replies, asks intake questions, discloses it is an AI, and obeys a send-or-draft gate enforced in code (code checks, the Jev classifier, a stored verdict per message) — Phase 2
- ✓ Owner takeover and draft review from the dashboard; a phone-friendly Needs you card opened from a text alert; Twilio driver behind the same channel interface; suite green on Postgres in CI — Phase 2

### Active

Committed scope (SPEC: Sprints 0 to 2), v1:

- [ ] Owner loop: staff approvers, approve by text reply, email alerts with the "handled" follow-up, re-alerts and assist mode (Phase 02.1, inserted; requirement IDs come from its discussion)
- [ ] Booking from availability in `hours.md`, a tap-to-choose booking link, appointments, reminders and owner alerts (Phase 3)
- [ ] Walk tests in `tests/*.md` run on every sync and gate activation; "Dana end to end" passes (Phase 3)

Pulled by a real client, one at a time (v2, Backlog in ROADMAP.md): quote/invoice/paid, voice, automations, settings by conversation and connectors.

### Out of Scope

- Shops with few leads happy on Google Sheets: give them files and folders only (the RyMac model), no platform.
- Replacing a $5M+ contractor's field-service dispatch: connect to their FSM tool instead (Signpost makes the same call).
- Multi-tenant SaaS: one instance per client (DEC-09). Tenancy would be a retrofit; accepted.
- Building telephony, voice models, payments or accounting: integrate instead.
- Stripe Cashier: it bills your own subscribers; this uses Stripe payment links.
- Card numbers anywhere in the system: Stripe holds them.
- Cloning Legacy's code: port its rules and tests only (DEC-12).
- Editing `records/` files by hand: they are read-only and the next render overwrites them.
- AI replies, Twilio, booking, money, voice and automations in Sprint 0 (Phase 1 boundary).

## Context

- Greenfield on a fresh scaffold (commit 84e3eae): Laravel 13.35, Filament 5.10 (admin panel at /admin), laravel/ai 1.2, Pest 5.3, Boost 2.10. Default tests pass and the panel login loads.
- The docs were ingested with `docs/gsd-ingest.yaml` (DECISIONS as locked ADR, SPEC, sprint-0). Conflict report: `.planning/INGEST-CONFLICTS.md` (0 blockers, 0 warnings, 5 info).
- Legacy (legacy-crm, commit 040f7ab) is the source of the money rules carried into Sprint 3 and of the lesson that a form-heavy system went unused. Its collisions ("job" meaning three things, "sent" and "total" ambiguity, silent tax defaults) stay behind.
- Ian is learning Laravel. Each new Laravel concept gets a short walk-through the first time it appears, logged in that sprint's file (docs/sprints/sprint-0.md "Walk-through log").
- One build serves the job search and the practice (DEC-13).
- Build method: spec first, then tests, then code. Claude Code builds; Ian reviews. A cartographer map starts in Sprint 1.

## Constraints

- **Tech stack**: Laravel 13.35, Filament 5.10 (Livewire 4), laravel/ai 1.2, Pest 5.3, Boost 2.10. Do not change dependencies without approval.
- **Runtime**: PHP 8.5 is the working runtime (php.new / Herd Lite, DEC-02). SPEC's PHP 8.3+ is the minimum.
- **Database**: SQLite in Sprint 0 (Pest on SQLite in memory). Postgres in CI and production from Sprint 1 (DEC-06). Queues: database driver locally; Redis and Horizon in production.
- **Config read path**: Application code reads business configuration only through `App\Icm\ActiveConfig` (container singleton), never from the ICM folder. Nothing reads configuration from disk except the sync.
- **Money**: All money values are integer cents, never floats. Payments decide invoice status.
- **Sending authority**: Per action, enforced in code, not only in the prompt. Prices, promises, discounts and complaints go to the owner as drafts.
- **AI disclosure**: The AI says it is an AI; a human is always one message away.
- **Records**: Written to the database first; files in `records/` are rendered from the database, read-only, written via temp file and rename. Platform agents get the card rendered fresh from the database, not from disk.
- **Folder layout**: One fact, one file. `records/` and `changelog.md` are skipped by the sync. Front matter is what the engine enforces; the markdown body is knowledge agents read.
- **Dependencies**: Phone numbers parsed with giggsey/libphonenumber-for-php-lite (no hand-rolled parsing); front matter with symfony/yaml and league/commonmark (already installed).
- **Process**: Tests before code for logic (TDD on); test command `php artisan test`; one git branch per phase that Ian merges; GSD commits locally per task and nothing pushes; no autonomous or yolo runs while Ian is learning Laravel.
- **Provider timing**: The build does not wait on Twilio A2P approval (DEC-05).

## Key Decisions

Locked by docs/DECISIONS.md (all dated 2026-10-10). GSD must not reopen these. IDs were assigned during ingest in source order.

<decisions status="locked" source="docs/DECISIONS.md">

- **DEC-01 GSD Core plans and runs the build.** Installed per project (`--claude --local`), not globally. Docs ingested with `docs/gsd-ingest.yaml`, decisions as locked. `use_worktrees` off (worktree checkouts have no `vendor/`, `.env` or database, so tests cannot run there); one branch per phase that Ian merges; TDD mode on; test command `php artisan test`; interactive mode; balanced models (Opus plans, Sonnet builds). No autonomous or yolo runs while Ian is learning Laravel. GSD commits locally per task; nothing pushes.
- **DEC-02 PHP via php.new.** Herd Lite: PHP 8.5, Composer, Laravel installer in `~/.config/herd-lite/bin`. Checked on the Linux build of the same Herd Lite 8.5: the lock file's platform requirements pass, including intl for Filament.
- **DEC-03 Records flow back as files, per customer, on every event.** Texts, call transcripts and payments are written to the database first; each one re-renders that customer's card in `records/customers/` from the database, plus the summary files, so the owner's AI routes to a file instead of querying. Platform agents get the same card rendered fresh from the database. Records files are read-only; changes go through the dashboard or the admin agent.
- **DEC-04 Scaffolded stack.** Laravel 13.35, Filament 5.10 (admin panel at /admin), laravel/ai 1.2, Pest 5.3, Boost 2.10. Default tests pass and the panel login loads. Boost is in composer.json but not installed: Ian runs `php artisan boost:install` on his Mac so the MCP config points at his PHP.
- **DEC-05 Fake text channel until A2P clears.** Ian registers A2P for his own test number; the build does not wait on approval.
- **DEC-06 Dev on SQLite, CI and production on Postgres from Sprint 1.** Zero local setup for Sprint 0; the suite runs on Postgres in CI so the two cannot drift.
- **DEC-07 laravel/ai for agents.** First-party, model-agnostic (Anthropic, OpenAI, Gemini, local, OpenRouter, any OpenAI-compatible endpoint), provider and model per agent, failover lists, client-owned keys possible. The model choice lives in each agent's file.
- **DEC-08 Filament 5 for the back office, Livewire for customer pages.** One UI stack. With ICM carrying configuration, the panel stays thin: inbox, schedule, money. Inertia with React stays available if a client-facing app ever needs it.
- **DEC-09 One instance per client.** Matches the client-owns-it offer. If this ever runs as multi-tenant SaaS, tenancy is a retrofit; accepted.
- **DEC-10 ICM is the foundation.** Files hold how the business runs (config and knowledge); the database holds what happened (records and money). Sync validates, walk-tests and only then activates; the engine reads config only from the active compiled version. Snapshots flow back into `records/` for the owner's own AI. Clients change their business by editing a file, and this is what sets the practice apart.
- **DEC-11 Route, don't retrieve.** Agents load routed files whole. pgvector only for large reference material, rebuilt from files.
- **DEC-12 Rebuild, not clone.** Port Legacy's rules and tests, not its code. Legacy's job/estimate collisions stay behind.
- **DEC-13 Laravel.** ContractorHUB closed a role on Laravel experience (10-03); the trades-software employers in the pipeline build on it. One build serves the job search and the practice.
- **DEC-14 Supersedes one clause of the 2026-10-08 practice direction** ("implement over SignPost-class tools ... rather than build a product"). The rest stands and is designed in: model- and vendor-agnostic, no lock-in, and the target experience of a dashboard with an AI inside that changes settings on request (settings by conversation, editing the folder). Implementing over existing tools stays the right call for a client who does not need to own the system.

</decisions>

Phase 2 decisions (full log: `.planning/phases/02-text-front-desk/02-CONTEXT.md`, D-01 to D-56). The ones later phases lean on:

- **D-01/D-02** A reply sends on its own only when the agent's label, the code checks and Jev all agree; the code checks are a hard veto and Jev can only hold, never force a send.
- **D-03, D-45, D-56** Fail closed: a Jev failure or low certainty holds the draft. Yes/no bars are per question in `config/gate.php`: price and discount 0.075, promise and complaint 0.15 (D-56 amends D-45 after UAT).
- **D-28/D-54** At most one live draft per conversation; an approved draft becomes the sent message (same row, `sent_at` records when it went out).
- **D-37** Before a real client goes live, every provider an agent file names gets a no-retained-copy (ZDR) route.
- Owner loop work (staff approvers, approve by reply, email alerts, assist mode) was split into Phase 02.1 on 2026-10-11.

Principle for closing phases (Ian, 2026-10-17, Phase 02.1 UAT):

- **02.1 closes on wiring, not business specifics.** A phase is done when the paths work: what is read, what is held, what is sent and who is alerted. Wording, which replies get a warm wrapper, where the AI notice sits, and what goes on the promise list get settled once the full workflow exists (first contact to finished job). Items held this way are listed in `docs/phase-2.2-owner-input.md` under "Held from the 2026-10-17 role play".

Open decisions (not locked; each has a trigger for deciding):

| Decision | Decide when | Notes |
|----------|-------------|-------|
| Product name | Before anything client-facing | Working name: ops-platform |
| Where client folders sync from (git repo, Dropbox, Drive) | With the first client | Sprint 0 uses a path on disk via `ICM_PATH` |
| Voice vendor (Vapi, Retell, ElevenLabs) | With the first client that needs phones | Backlog 999.2 |
| Hosting (Laravel Cloud or Forge) | At first deploy | One instance per client |
| Which legal entity registers A2P for Ian's own test number | Before Twilio goes live | DEC-05 says Ian registers; the entity is not stated. Does not gate the build |

Deploy notes (Ian, 2026-10-18, Phase 02.1 UAT test 4). Carry these into the first deploy:

- **Run the queue worker under a supervisor that restarts it.** In UAT the worker crashed overnight when the Mac slept (ProcessTimedOutException), and while it was down no health ping ran, so the owner was not told. The ping runs on the same worker, so it cannot report the worker itself being down.
- **Restart the worker after any `.env` or key change.** `queue:listen` keeps the `.env` values it started with: a restored `JEV_API_KEY` only took effect after the restart.
- **Owner-alert delivery to a real phone is untested.** The Twilio account is still a trial and A2P can't be registered. Check it at Twilio go-live.

## Frontend

Decided:

- Filament 5 for the back office, Livewire for customer pages (DEC-08, `docs/DECISIONS.md` 2026-10-10).
- The admin stays stock Filament with a brand pass before screenshots, not a custom theme. A UI-SPEC only for Phase 3's customer booking page. GSD's UI safety gate stays on (Ian, 2026-10-11).
- No styling changes until Ian's phone walk (02.1 D-48, `.planning/WINDOWS.md` entry 13).
- The Needs you card shows the customer's last message above the draft, and the thread runs oldest first (Phase 2 G-02-1.1).
- Customer-facing strings: no exclamation points and no em dashes (02.1 D-41).

Open (Ian, 2026-10-16). Decide each before its first use; never improvise:

1. How custom views get styled: a Filament theme CSS file so Tailwind classes compile, or Filament components only. Either way, no new inline style attributes.
2. Color: Filament semantic colors only (danger, warning, success, gray), never hex. Amber primary is a placeholder until the brand pass.
3. One small text size and one spacing scale, taken from Filament.
4. Phone layout, decided at the phone walk: which table columns hide below tablet width, and the thread's own scroll box versus page scroll. Checked at 375 and 390 wide.
5. Accessibility baseline: every input has a label, visible or aria-label; WCAG AA contrast in light and dark mode; whether dark mode stays on.
6. Errors the owner must fix show inline under the field. Decide whether channel failures stay toasts.
7. The thread's polling interval (5 s now), and whether long threads load in pages.
8. What "/" shows before Phase 3 (the stock Laravel page today).

## Evolution

This document evolves at phase transitions and milestone boundaries.

**After each phase transition:**
1. Requirements invalidated? Move to Out of Scope with reason.
2. Requirements validated? Move to Validated with phase reference.
3. New requirements emerged? Add to Active.
4. Decisions to log? Add to Key Decisions (new decisions are recorded here now, not in docs/DECISIONS.md).
5. "What This Is" still accurate? Update if drifted.

**After each milestone:**
1. Full review of all sections.
2. Core Value check: still the right priority?
3. Business Context check: customer, revenue model, success metric still accurate?
4. Audit Out of Scope: reasons still valid?
5. Update Context with current state.

---
*Last updated: 2026-10-17, Phase 02.1 UAT (closing principle)*
