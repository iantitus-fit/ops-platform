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

## Current State

v1.0 Front Desk shipped 2026-10-20: Phases 1, 2 and 02.1 (76 plans). The folder drives the app, every lead lands on one timeline, and the text front desk replies behind the send-or-draft rule, with owner-written prices and offers sent by code. Booking and walk tests (RECS-02, BOOK-01 to BOOK-06, WALK-01 to WALK-03) are known gaps at the close. Record: `.planning/MILESTONES.md`; archives in `.planning/milestones/`.

v2.0 Model Home: Phase 4 Room Standard completed 2026-10-23 (9 plans; UAT 3 of 3 passed). The pretend room that proves the standard lives only in `tests/`; Phase 5 builds the first real rooms. Open items from its UAT and review are in `docs/BLUEPRINT.md` under the punch-list rule.

v2.0 Model Home: Phase 5 Standard Rooms completed 2026-10-26 (9 plans; UAT 2 of 2 passed; verification 4/4). Company knowledge, marketing, content generation, Google Business Profile and money are the first real rooms in the example business, each a folder with its contract, example file, dashboard section and boot test. The re-review after the fixes left one warning open: a money notes file with non-UTF-8 bytes between digit groups gets past the number check (05-REVIEW.md WR-01).

v2.0 Model Home: Phase 6 Front Desk and Team Rooms completed 2026-10-28 (7 plans; verification 5/5; Postgres full suite green in a local run, CI pgsql leg still to confirm after the push, WINDOWS.md entries 30 to 33). The front desk and the team are rooms in the example business with no behavior change, every owner alert is texted once (an atomic send claim, a unique de-duplication key), and a failed front-desk run is reported once, against the burst its run claimed. The review left four warnings open for Ian (06-REVIEW.md WR-01 to WR-04).

## Current Milestone: v2.0 Model Home

**Goal:** A cookie-cutter house that is a starting point for any business: every room built to one standard around the project, so a business is set up by walking the rooms and furnishing them at setup. Wording, policies and which rooms a business uses are the business's own decisions, not built in.

**Target features:**
- The room standard, as proposed in Key Decisions ("The model home is the next milestone"), settled in this milestone's requirements.
- The spine: a Project record with its appointments, and one field in `business/profile.md` for the word the business uses. Estimates and payments stay in backlog 999.1 (Ian, 2026-10-21, keeping "The project is the spine" as written).
- The floor plan, each room confirmed in the requirements scoping: front desk (built in v1.0, frozen as the default), leads, company knowledge, marketing, content generation, Google Business Profile (a connector slot), schedule and booking (seed SEED-261020-v30), money (the full standard, with no payment-link slot and no payment records), team (what is built today plus alerts sent exactly once; 999.5 stays unchanged), owner intelligence. Property and subcontractors are open.
- Booking and the walk-test gate: RECS-02, BOOK-01 to BOOK-06, WALK-01 to WALK-03, carried with their v1.0 IDs and wording.
- Alerts sent exactly once (WR-03, WR-04, WR-22) and the `gate:export` fix (WR-09).

Settled in scoping (Key Decisions, 2026-10-21): the room standard and the floor plan (rooms add folders, files stay by kind), the phone design pass as a v2.0 phase, a tour deploy, and the punch-list rule. Still open: a property room, a subcontractors room, a multi-year contract above several projects, regions as a scope for who sees what, per-field ownership, a first trade pack, and everything `docs/BLUEPRINT.md` marks "not yet decided". Requirements: `.planning/REQUIREMENTS.md` (40).

Affects: all ten areas (see Key Decisions, "The model home is the next milestone").

## Requirements

Full list with IDs: `.planning/REQUIREMENTS.md`. Roadmap: `.planning/ROADMAP.md`.

### Validated

- ✓ The folder drives the app: sync validates, versions and activates; a broken change never goes live and the previous version stays active — Phase 1, v1.0
- ✓ Every way a lead can arrive (fake text channel, web form, dev chat) lands on one contact and one timeline, with source and cost per lead from the folder — Phase 1, v1.0
- ✓ The owner works from a Filament dashboard: contacts, conversations with a reply box, and a Business Folder page — Phase 1, v1.0
- ✓ Every event re-renders that customer's card in `records/` so the owner's own AI can route to a file instead of querying — Phase 1, v1.0
- ✓ A text front desk built from `agents/*.md` replies, asks intake questions, discloses it is an AI, and obeys a send-or-draft gate enforced in code (code checks, the Jev classifier, a stored verdict per message) — Phase 2, v1.0
- ✓ Owner takeover and draft review from the dashboard; a phone-friendly Needs you card opened from a text alert; Twilio driver behind the same channel interface; suite green on Postgres in CI — Phase 2, v1.0
- ✓ A price or offer the owner wrote goes out at once, picked and sent by code from the customer's intake answers and checked at sync against the file's amounts; a price the model writes still holds — Phase 02.1, v1.0
- ✓ An easy answer never waits behind a hard question already with the owner, and a customer who writes while a draft waits always gets an owner-written reply (partial holding, check-in, added-info, complaint or discount) with only promises `hours.md` can keep — Phase 02.1, v1.0
- ✓ Jev reads the customer's text on every run; an automated text without a reading is refused at the sender; a Jev outage alerts the owner once and again when it is back — Phase 02.1, v1.0
- ✓ The room standard: a room is a folder under `rooms/` with a `CONTEXT.md` contract the sync checks (owns, reads, writes_records, connector), and a Laravel module that registers its file types, tables, dashboard section and timeline entries, with a connector slot only when it talks to an outside system; one test boots it empty and with its example folder on SQLite and Postgres, and its dashboard section passes an axe check (WCAG 2.2 AA) in CI (ROOM-01 to ROOM-05, A11Y-01) — Phase 4, v2.0
- ✓ Two systems never own the same kind of record: connector files declare `owns` and `reads`, live only in `connectors/`, and a folder where two of them own one kind is refused in plain words while the previous version stays live (CONN-07) — Phase 4, v2.0
- ✓ The first real rooms meet the standard in the example business: company knowledge, marketing (owns the lead sources and offers; its "live today" line asks the front desk's own rule), content generation (no connector), Google Business Profile (a read-only slot bound to a fake that sends nothing, one categories file of at most nine additional categories) and money (no slot, no payment records; its notes file refuses card and 9-digit numbers in one digit-free line). Leaving out company knowledge or marketing is refused by name, a file name with a hidden character is refused with the character marked, and a room section that fails shows one line instead of taking down the dashboard (ROOM-08 to ROOM-11, ROOM-14) — Phase 5, v2.0
- ✓ The front desk and the team are rooms at the standard with no behavior change: the front desk owns the sending, replies, triage and agent files and claims the two dashboard widgets it acts on; the team owns no file, reads three and owns the owner alerts; leaving either out is refused by name (ROOM-06, ROOM-15). Every owner alert is texted exactly once, with a send claim released after two minutes and one stored alert per conversation, message, reason and user; a failed front-desk run is reported once per claim token, against the burst its run claimed, a form-only burst included (ALRT-01, ALRT-02) — Phase 6, v2.0

### Active

Committed scope (SPEC: Sprints 0 to 2), v1:

- Owner loop: parked to backlog 999.5 on 2026-10-19 (Ian), pulled by the first client with staff. See Key Decisions, "The model home is the next milestone".
- [ ] Booking from availability in `hours.md`, a tap-to-choose booking link, appointments, reminders and owner alerts (was Phase 3; in v2.0 Model Home, v1 closed at 02.1 on 2026-10-19)
- [ ] Walk tests in `tests/*.md` run on every sync and gate activation; "Dana end to end" passes (was Phase 3; in v2.0 Model Home, v1 closed at 02.1 on 2026-10-19)
- [ ] The rest of v2.0 Model Home: see Current Milestone above; IDs in `.planning/REQUIREMENTS.md`.

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
- Shipped v1.0 on 2026-10-20: 16,327 lines of PHP in `app/` and 22,148 in `tests/`; the suite (1750 tests) passes on SQLite in the last local full run (02.1-VERIFICATION.md, after 261018-dxy) and on SQLite and PostgreSQL 17 in CI on main (runs #6 at 1aaee49 and #7 at 7328cb4; tests.yml runs both databases on every push). Open review items, deploy notes and before-first-deploy items are carried in `.planning/STATE.md` Blockers/Concerns.

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

Phase 2 decisions (full log: `.planning/milestones/v1.0-phases/02-text-front-desk/02-CONTEXT.md`, D-01 to D-56). The ones later phases lean on:

- **D-01/D-02** A reply sends on its own only when the agent's label, the code checks and Jev all agree; the code checks are a hard veto and Jev can only hold, never force a send.
- **D-03, D-45, D-56** Fail closed: a Jev failure or low certainty holds the draft. Yes/no bars are per question in `config/gate.php`: price and discount 0.075, promise and complaint 0.15 (D-56 amends D-45 after UAT).
- **D-28/D-54** At most one live draft per conversation; an approved draft becomes the sent message (same row, `sent_at` records when it went out).
- **D-37** Before a real client goes live, every provider an agent file names gets a no-retained-copy (ZDR) route.
- Owner loop work (staff approvers, approve by reply, email alerts, assist mode) was split into Phase 02.1 on 2026-10-11, renumbered to Phase 02.2 on 2026-10-14 when Faster Answers went first.

Phase 02.1 decisions (full log: `.planning/milestones/v1.0-phases/02.1-faster-answers/02.1-CONTEXT.md`, D-01 to D-51; UAT fixes in quick task 261018-dxy). The ones later phases lean on:

- **D-04/D-10** A price goes out on its own only as an owner-written sentence that code picks and sends, its amounts checked at sync against the file's prices; the model never writes a price that sends.
- **D-34** One owner-written line every time a customer writes while a draft waits, in a fixed order; the complaint reply goes out at most once per wait, and one customer text raises one owner alert, the more serious one (UAT, 2026-10-18).
- **D-42/D-45** Jev reads the customer's text on every run, and its safety, complaint and person readings act on every path.
- **D-49/D-50** The sender refuses an automated text without the reading its kind needs; a Jev outage sends one down alert and one back-up alert with the count of texts that arrived meanwhile.
- **D-46** One invariant test runs every combination of the waiting, complaint, hazard, price, check-in, pause, cap and failure cases.

Model roles (Ian, 2026-10-18):

- **Cheap models read and sort:** Jev and the keyword checks.
- **Mid-tier models write drafts:** each agent file names its own model and fallbacks.
- **Code and the owner decide money and safety.**
- **Frontier models only for slow back-office work**, such as owner intelligence.
- **No model reviews outbound texts as a final check.**
- Affects: customer communication, marketing, sales, operations, finance, team, owner intelligence, knowledge, connectors and data, safety and compliance (it sets each area's model role in `docs/BLUEPRINT.md`).

Principle for closing phases (Ian, 2026-10-17, Phase 02.1 UAT):

- **02.1 closes on wiring, not business specifics.** A phase is done when the paths work: what is read, what is held, what is sent and who is alerted. Wording, which replies get a warm wrapper, where the AI notice sits, and what goes on the promise list get settled once the full workflow exists (first contact to finished job). Items held this way are listed in `docs/phase-2.2-owner-input.md` under "Held from the 2026-10-17 role play".

The project is the spine (Ian, 2026-10-19):

- **Every business runs on one record that carries a piece of work from first contact to paid and warranty: the project.** Estimates, appointments and payments hang off it, alongside the contact and the conversation. Not built; today's data model centers on the contact and the conversation.
- **Code and docs call it a Project.** Laravel already uses "job" for queued jobs (`app/Jobs/`, the `jobs` table), and DEC-12 leaves Legacy's three meanings of "job" behind.
- **The word people see is the business's own.** One field in `business/profile.md` sets it (Job for a roofer, Project for a remodeler, Matter for a law office), checked by the sync like any other field; the dashboard, the customer cards and the front desk use it.
- **One shape covers the four trades researched:** a roofing project with an inspection and an install appointment, an HVAC call with one, a remodel with many, a landscaping maintenance contract with weekly ones. Research: `docs/research/contractor-operations-2026-10-19.md`.
- **Why (Ian):** "It's the blueprint for XYZ company that can be adapted to ABC company."
- Deciding the shape does not schedule it, and does not pull quotes, invoices or payments forward from backlog 999.1.
- Affects: sales, operations, finance, customer communication, team, owner intelligence, knowledge.

The model home is the next milestone (Ian, 2026-10-19):

- **The next milestone builds the model home:** a cookie-cutter house that is a starting point for any business, with rooms added along the way. Every room is built to the same standard around the project (the spine above). Wording, policies, which rooms a business uses and how it runs are the business's own decisions, made at setup: the furniture and paint, not the house.
- **The room standard, as proposed 2026-10-18 and settled in the milestone's requirements:** a folder in the business folder with a CONTEXT.md contract and one fictional example file (an empty room is valid); a Laravel module that registers the room's file types with the sync, its tables, its dashboard section and its timeline entries; a connector slot as an interface with a fake driver (the `TextChannel` pattern); what the room writes to `records/`; one test that the room boots empty and with the example folder.
- **Phase 02.2 (Owner Loop) moves to backlog 999.5 unchanged,** with its scope, success criteria and carry notes, pulled by the first client with staff. `docs/phase-2.2-owner-input.md` stays as the bank of setup questions. Correctness carries that move with it and need a home in the model home's requirements or the before-first-deploy list: alerts sent exactly once (WR-03, WR-04, WR-22) and the `gate:export` fix (WR-09).
- **The text front desk is the finished default for its room.** No new front-desk depth before the other rooms exist.
- **v1 closes at 02.1 (Ian, 2026-10-19).** Phase 3 (booking and the walk-test gate) moves into the model home: booking becomes the schedule room's basic setup, and walk tests become the gate on every folder change. Its requirements are recorded as known gaps at the v1 close, and its planning notes (walk-test determinism, reminder timing, BOOK-06 and RECS-02 scope) carry into the new milestone. The model home starts with `/gsd-new-milestone`. Why (Ian): "Better to make sure it's done now than later."
- **Why (Ian):** make it easier to work with a business when one signs on, show a Laravel team what he can build, and get it useful enough that someone says "that's a nice house, let me put some things in it and try something."
- **Reopens (Ian's explicit call, flagged before he answered):** the 2026-10-10 call that parked SPEC Sprints 3 to 6 until a client pulls them, and the SPEC's sprint order as the build order. DEC-01 to DEC-14 stand.
- Affects: customer communication, marketing, sales, operations, finance, team, owner intelligence, knowledge, connectors and data, safety and compliance.

One owner per fact (Ian, 2026-10-19):

- **Each kind of record has exactly one owner system,** named in that system's connector file: customers, sales activity, invoices, payments, expenses, work orders, the schedule, documents. Every other system that shows it holds a copy, and a copy never wins. A fact no connector claims belongs to the platform; projects, texts and the timeline start there.
- **The connector front matter splits `objects` into `owns` and `reads`.** The sync refuses a folder where two connectors own the same fact and says so in plain words ("customers is owned by both connectors/a.md and connectors/b.md; pick one, the other can read it").
- **A copy that differs from its owner shows on the card** for a person to fix at the owner. The platform never overwrites silently and never takes a copy's change as truth. Something the platform learns about a fact it does not own (a new email from a customer text) goes to the owner if that connector may write, or to a person.
- **Each contact and project keeps its ID in each system,** so one project shows its invoice, CRM account, folder and schedule rows without owning them.
- **Moving a fact's owner is a change to two connector files,** checked by the sync. That is how a business leaves a tool one fact at a time.
- Ownership is per kind of record. Per-field ownership (a CRM owns the name and phone, the accounting tool owns billing terms) is decided when a client needs it.
- Part of the room standard's connector slot (above). Shape tested against a real six-system stack: accounting, a CRM synced to it, file sharing, linked spreadsheets as the operational record, work orders on regional drives, an expense app. No real names or data in the repo.
- **Why (Ian):** it leaves the system less cluttered.
- **Cost:** every connector needs a check that compares its copies with the owner; that is build work per connector.
- Amends the connector file type in `docs/SPEC.md` (`objects` becomes `owns` and `reads`).
- Affects: connectors and data, knowledge, sales, operations, finance, owner intelligence.

The room standard and the v2.0 floor plan (Ian, 2026-10-21, settled in `.planning/REQUIREMENTS.md`):

- **The room standard is the five parts proposed on 2026-10-18** (ROOM-01 to ROOM-05): a room folder with a `CONTEXT.md` contract and one fictional example file (an empty room is valid); a Laravel module that registers the room's file types with the sync, its tables, its dashboard section and its timeline entries; a connector slot as an interface with a fake driver, with `owns` and `reads`; what the room writes to `records/`; one test that the room boots empty and with the example folder.
- **Rooms add folders; today's files stay where they are, by kind.** Each room's `CONTEXT.md` names the files it owns and the ones it reads, and every file has exactly one owning room.
- **A room gets a connector slot only when it talks to an outside system;** otherwise its `CONTEXT.md` says it has none.
- **The v2.0 floor plan is ten rooms:** front desk (built in v1.0, wrapped with no behavior change), leads, company knowledge, marketing, content generation, Google Business Profile (a connector slot), schedule and booking, owner intelligence, money (the full standard, with no payment-link slot and no payment records), team (what is built today, wrapped with no behavior change). Every new room meets the standard and nothing more, with two exceptions: the leads room opens a project when a new lead arrives, and the owner intelligence room carries the seed's records items. Marketing owns `business/lead-sources.md` and `business/offers/`; leads and the front desk read them.
- **The spine in v2.0 carries appointments only.** Estimates and payments stay in backlog 999.1, and the money room has no payment-link slot; "The project is the spine" stands as written.
- **Backlog 999.5 stays unchanged.** Alerts sent exactly once and the `gate:export` fix now live in ALRT-01, ALRT-02 and GATE-05; the 999.5 carry notes point there.
- **Open, nothing decided:** a property room, a subcontractors room, a multi-year contract above several projects, regions as a scope for who sees what, per-field ownership, a first trade pack.
- Affects: customer communication, marketing, sales, operations, finance, team, owner intelligence, knowledge, connectors and data, safety and compliance.

The phone design pass is a v2.0 phase (Ian, 2026-10-21):

- **The dashboard, the Needs you card, the Business Folder page and every other owner-facing screen that exists by then get a phone layout in v2.0** (DSGN-01 to DSGN-05), from Ian's call at the pass, with the styling 02.1 D-48 held for the phone walk.
- **Reopens (Ian's explicit call):** the 2026-10-11 Frontend decision (stock Filament with a brand pass; a UI-SPEC only for the booking page). The part that stands: the customer booking page keeps its own UI-SPEC with booking, built phone-first, and the pass re-checks it at 375 and 390.
- Hold-reason wording changes words only; what is held, sent or alerted does not change.
- Affects: customer communication, team, owner intelligence, knowledge.

A tour deploy in v2.0 (Ian, 2026-10-21):

- **A staging instance runs the fictional example business on the fake text channel, behind an invite-only login, with no real customer data** (DEPL-02), after the phone design pass. It brings the hosting decision forward (Laravel Cloud or Forge) and the "/" page and queue supervisor items (DEPL-03, DEPL-04). DEPL-01 is unchanged.
- The real-client items stay on the before-first-client list: ZDR routes, consent wording, A2P and Twilio go-live, the attorney list.
- Affects: connectors and data, safety and compliance, knowledge, customer communication.

The punch-list rule (Ian, 2026-10-21):

- **UAT findings in a room that already works go to that area's open items in `docs/BLUEPRINT.md`, not into a new decimal phase ahead of the next room.**
- **Exception:** safety and correctness bugs are fixed right away, as a quick task or, if they need one, a decimal phase. Ian decides which kind a finding is at the UAT.
- Say "UAT findings", not "test-walk findings", so the rule does not collide with the walk tests in WALK-01 to WALK-03.
- Affects: customer communication, marketing, sales, operations, finance, team, owner intelligence, knowledge, connectors and data, safety and compliance.

The chokepoint rule (Ian, 2026-10-22):

- **Every agent action (a send, a write, a change to a record or the folder) goes through a code chokepoint** that refuses it without the checks its kind needs and records which checks passed and who or what approved it (the OutboundSender pattern, 02.1 D-49).
- **A room that lets an agent act declares the agent's decision rights in a rules file the sync checks,** as `rules/sending.md` does for the front desk. A plan that adds an agent action names its chokepoint.
- **Why (Ian):** every agent can answer what it decided, how it was checked and who approved it, and the record it leaves is the business's own decision history.
- Affects: customer communication, marketing, sales, operations, finance, team, owner intelligence, knowledge, connectors and data, safety and compliance.

Open decisions (not locked; each has a trigger for deciding):

| Decision | Decide when | Notes |
|----------|-------------|-------|
| Product name | Before anything client-facing | Working name: ops-platform |
| Where client folders sync from (git repo, Dropbox, Drive) | With the first client | Sprint 0 uses a path on disk via `ICM_PATH` |
| Voice vendor (Vapi, Retell, ElevenLabs) | With the first client that needs phones | Backlog 999.2 |
| Hosting (Laravel Cloud or Forge) | At first deploy (the v2.0 tour deploy, DEPL-02) | One instance per client |
| Which legal entity registers A2P for Ian's own test number | Before Twilio goes live | DEC-05 says Ian registers; the entity is not stated. Does not gate the build |

Deploy notes (Ian, 2026-10-18, Phase 02.1 UAT test 4). Carry these into the first deploy:

- **Run the queue worker under a supervisor that restarts it.** In UAT the worker crashed overnight when the Mac slept (ProcessTimedOutException), and while it was down no health ping ran, so the owner was not told. The ping runs on the same worker, so it cannot report the worker itself being down.
- **Restart the worker after any `.env` or key change.** `queue:listen` keeps the `.env` values it started with: a restored `JEV_API_KEY` only took effect after the restart.
- **Owner-alert delivery to a real phone is untested.** The Twilio account is still a trial and A2P can't be registered. Check it at Twilio go-live.

## Frontend

Decided:

- Filament 5 for the back office, Livewire for customer pages (DEC-08, `docs/DECISIONS.md` 2026-10-10).
- The admin stays stock Filament with a brand pass before screenshots, not a custom theme. A UI-SPEC only for Phase 3's customer booking page. GSD's UI safety gate stays on (Ian, 2026-10-11). Reopened by the v2.0 phone design pass (Ian, 2026-10-21; Key Decisions), except the booking page's own UI-SPEC, built phone-first, which stands.
- No styling changes until Ian's phone walk (02.1 D-48, `.planning/WINDOWS.md` entry 13).
- The Needs you card shows the customer's last message above the draft, and the thread runs oldest first (Phase 2 G-02-1.1).
- Customer-facing strings: no exclamation points and no em dashes (02.1 D-41).
- Every page the platform serves meets WCAG 2.2 Level AA, fixed in the markup, never by an overlay widget. Every image carries alt text. Automated checks catch only part of WCAG, so customer pages and owner screens also get a keyboard-only and screen reader walk at UAT (Ian, 2026-10-22). REQUIREMENTS A11Y-01 to A11Y-03.

Open (Ian, 2026-10-16). Decide each before its first use; never improvise:

1. How custom views get styled: a Filament theme CSS file so Tailwind classes compile, or Filament components only. Either way, no new inline style attributes.
2. Color: Filament semantic colors only (danger, warning, success, gray), never hex. Amber primary is a placeholder until the brand pass.
3. One small text size and one spacing scale, taken from Filament.
4. Phone layout, decided at the phone walk: which table columns hide below tablet width, and the thread's own scroll box versus page scroll. Checked at 375 and 390 wide.
5. Accessibility baseline: every input has a label, visible or aria-label; WCAG AA contrast in light and dark mode; whether dark mode stays on. Settled in Phase 11 by A11Y-03, under the WCAG 2.2 Level AA rule in Decided.
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
*Last updated: 2026-10-28 after Phase 6*
