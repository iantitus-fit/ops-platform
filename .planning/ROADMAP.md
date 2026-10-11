# Roadmap: Ops Platform

## Overview

Ops Platform is a client-owned operating system where the business runs from a folder. The committed roadmap (v1, SPEC Sprints 0 to 2) takes it from a fresh scaffold to the headline outcome in three phases. Phase 1 makes the folder drive the app: a validated, versioned sync, every way a lead can arrive landing on one timeline, a Filament dashboard, and customer cards rendered into `records/`. Phase 2 puts an AI text front desk on that timeline, with a send-or-draft gate enforced in code and the model chosen in a file. Phase 3 finishes the conversation with booking, reminders and owner alerts, and turns on the activation gate: scripted customers walk-test every folder change before it goes live. Sprints 3 to 6 (quote to invoice, voice, automations, settings by conversation and connectors) are parked in the Backlog and are pulled by a real client or job conversation, one at a time.

## Phases

**Phase Numbering:**
- Integer phases (1, 2, 3): Planned milestone work
- Decimal phases (2.1, 2.2): Urgent insertions (marked with INSERTED)
- 999.x: Backlog parking lot, not scheduled

Decimal phases appear between their surrounding integers in numeric order.

- [ ] **Phase 1: Foundation** - The folder drives the app and every way a lead can arrive lands on one timeline (SPEC Sprint 0)
- [ ] **Phase 2: Text Front Desk** - An AI front desk replies by text from an agent file, behind a send-or-draft gate enforced in code (SPEC Sprint 1)
- [ ] **Phase 3: Booking and the Gate** - Customers book from a link and every folder change is walk-tested before it goes live (SPEC Sprint 2)

## Phase Details

### Phase 1: Foundation

**Goal**: The folder drives the app, and every way a lead can arrive lands on one timeline that the owner can work from and the owner's own AI can read. No AI replies and no real texting yet.
**Depends on**: Nothing (first phase)
**Requirements**: ICM-01, ICM-02, LEAD-01, LEAD-02, LEAD-03, LEAD-04, RECS-01, DASH-01, DASH-02, QUAL-01
**Success Criteria** (what must be TRUE):
  1. `php artisan icm:sync` on `examples/example-hvac` compiles a version and makes it active, and `php artisan icm:status` shows the active version, its file count and when it went live.
  2. After a file is broken (a required field deleted, or `price_policy: maybe`), the next sync fails with a message that names the file and the field in plain words, the previous version stays active, and the failure is appended to the folder's `changelog.md`.
  3. A text from a new number through the fake channel (including from the local-only `/dev/chat` page, which returns 404 elsewhere) creates a contact, conversation, message and timeline entry; a second text joins the same conversation; and a web form lead with `source=angi` from the same phone number lands on the same contact with the source and cost per lead from `business/lead-sources.md`.
  4. The owner can open the Filament dashboard, see Contacts (list plus a timeline view) and Conversations (a thread with a reply box), type a reply that goes out through the fake channel and is stored as sent, and see the active version, files, last sync result, errors and a Sync Now button on the Business Folder page.
  5. Every inbound text or form re-renders that customer's card in `records/customers/` and updates `INDEX.md` and `open-leads.md`; two events for one customer in the same second produce one card showing both; `php artisan records:rebuild` regenerates everything; and the sync never reads `records/`.

**Phase gate** (QUAL-01): every Pest test passes, money fields are integer cents, nothing reads configuration from disk except the sync, and the two project rules (integer cents; config only through `App\Icm\ActiveConfig`) are recorded with Boost in `.ai/rules`.
**Plans**: 4/7 plans executed (sprint-0.md's three steps refined into seven; one plan per wave, run in order)

Plans:
**Wave 1**
- [x] 01-01-PLAN.md — Folder sync goes live: `examples/example-hvac`, `Loader`, `icm_versions`, `FolderSync`, `ActiveConfig`, `icm:sync`, `icm:status`, no-op and lock, the two Boost project rules and arch guard (ICM-01, QUAL-01)

**Wave 2** *(blocked on Wave 1 completion)*
- [x] 01-02-PLAN.md — Broken folder never goes live: `Validator` rule sets for all Sprint 0 types, plain-words errors, rejected versions, `changelog.md` for every attempt (ICM-02)

**Wave 3** *(blocked on Wave 2 completion)*
- [x] 01-03-PLAN.md — A text lands on the timeline: contacts, conversations, messages, activities, `TextChannel` with `FakeTextChannel` and `TwilioTextChannel`, `POST /webhooks/text/fake`, `RecordInbound`, `ProcessInbound`, E.164 (LEAD-01, LEAD-03)

**Wave 4** *(blocked on Wave 3 completion)*
- [x] 01-04-PLAN.md — Web form leads land on the same contact: `POST /leads`, matching by phone then email, first-touch attribution and cost per lead (LEAD-02, LEAD-03)

**Wave 5** *(blocked on Wave 4 completion)*
- [ ] 01-05-PLAN.md — Customer cards in records/: `CustomerCard`, unique `RenderCustomerCard`, `INDEX.md`, `open-leads.md`, `records/CLAUDE.md`, `records:rebuild` (RECS-01)

**Wave 6** *(blocked on Wave 5 completion)*
- [ ] 01-06-PLAN.md — Owner works from the dashboard: Conversations (thread, reply through `TextChannel`, Close/Reopen) and Contacts (list plus timeline), `FilamentUser` (LEAD-04, DASH-01)

**Wave 7** *(blocked on Wave 6 completion)*
- [ ] 01-07-PLAN.md — `/dev/chat`, Business Folder page with Sync Now, seeder, QUAL-01 gate with the full suite green (DASH-02, DASH-01, QUAL-01)

**UI hint**: yes

**Planning notes** (from the ingest conflict report and the SPEC; settle these in discuss-phase):
- Validator scope: Sprint 0 validates only the Sprint 0 file types (business, hours, lead_sources, knowledge, service, voice, sending_rules). Suggested default: `README.md` files and untyped orientation files (`CLAUDE.md`, `START-HERE.md`, `CONTEXT.md`) are not validated and not counted in the file count; files of later-sprint types (agent, automation, connector, walk_test) are skipped and reported as "not supported yet"; an unknown `type:` value is a plain-words error.
- The compiled version should carry markdown bodies as well as front matter. Sprint 1 agents load routed files whole and must get them from `ActiveConfig`, never from disk. Decide whether untyped router files (`CLAUDE.md`, `CONTEXT.md`) are snapshotted too.
- Sprint 0 builds only `INDEX.md`, `open-leads.md` and `records/CLAUDE.md` of the records set; `today.md` comes in Phase 3, `who-owes-us.md` and `this-week.md` in the Backlog.
- `records/` lives inside the ICM folder, so rendering for `examples/example-hvac` writes into the repo; decide what is gitignored (keep `records/README.md` tracked).
- `boost.json`, `.mcp.json` and `AGENTS.md` already exist, so `boost:install` looks done; `.ai/rules` does not exist yet, so recording the two rules is a real task.
- Each new Laravel concept gets a short walk-through the first time it appears, logged in docs/sprints/sprint-0.md under "Walk-through log".

### Phase 2: Text Front Desk

**Goal**: A customer who texts gets a reply that moves the conversation forward without the owner, from an agent defined in a file, and nothing risky is ever sent without the owner.
**Depends on**: Phase 1
**Requirements**: AGNT-01, AGNT-02, AGNT-03, AGNT-04, GATE-01, GATE-02, GATE-03, GATE-04, TXT-01, INFRA-01
**Success Criteria** (what must be TRUE):
  1. A customer who texts through the fake channel (for example from `/dev/chat`) gets a reply from the agent in the same conversation; the first reply says it is an AI, uses the voice file's sign-off, and never uses a `never_say` phrase; and the agent then asks the matching service's intake questions and the answers show on the timeline and the customer card.
  2. A message involving a price, a promise, a discount or a complaint is never sent: it appears as a draft in the dashboard and the owner is alerted, while intake questions, scheduling and FAQ answers send on their own, even when a scripted model tries to send the draft.
  3. The owner can take over a conversation so the agent stops replying until it is handed back, and can approve, edit and send, or discard a draft from the dashboard; a customer who asks for a person is handed to the `escalate_to` contact.
  4. Changing an agent's provider, model or fallback list is an edit to its `agents/*.md` file that goes live through the normal sync, a broken agent file is rejected with a plain-words message, and when the primary provider fails the agent falls through to its fallback.
  5. The Twilio text driver sits behind the same `TextChannel` interface and is selected by config alone, verified against Twilio-shaped payloads (going live on Ian's test number follows A2P approval and does not gate this phase), and the full Pest suite passes on Postgres in CI as well as on SQLite locally.

**Plans**: TBD
**UI hint**: yes

**Planning notes** (settle these in discuss-phase):
- The `send_on_own` and `draft_for_owner` action-key vocabulary is not defined in the SPEC beyond Principle 6's examples; define it and make the Validator reject unknown keys.
- Tests should use laravel/ai fakes; live runs and `/dev/chat` need a provider key in `.env`.
- Where intake answers are stored (AGNT-03) is open; the card must still show them.
- The agent reads the customer's card rendered fresh from the database (DEC-03), not the file on disk.
- Production deploy is not in this phase; DEPL-01 stays in v2. CI on Postgres is.
- Per the SPEC, a cartographer map starts in this sprint.

### Phase 3: Booking and the Gate

**Goal**: A customer can finish the conversation by booking a time, the owner sees and is told about it, and no folder change goes live until scripted customers have walked through it.
**Depends on**: Phase 2
**Requirements**: BOOK-01, BOOK-02, BOOK-03, BOOK-04, BOOK-05, BOOK-06, RECS-02, WALK-01, WALK-02, WALK-03
**Success Criteria** (what must be TRUE):
  1. A customer who is texted a booking link opens a page, taps one of the offered times and ends with a confirmed appointment; only times inside `hours.md` hours, outside closures, past `booking_lead_minutes`, long enough for the service, and in the business timezone are offered.
  2. A booked appointment appears on the contact's timeline, as the next appointment on the customer card, in `records/today.md` and on a dashboard schedule; the customer is reminded before it; the owner is alerted; and a nightly full rebuild of `records/` is scheduled.
  3. The minutes from a contact's first message to their booked appointment are shown on the contact and in a dashboard summary.
  4. Every sync runs each scripted customer in `tests/*.md` against the candidate version; after a change that breaks one (for example the agent would now quote a price, or the expected time is no longer offered) the sync fails, names the test and the check that failed in plain words, keeps the previous version live, writes the attempt to `changelog.md` and shows it on the Business Folder page.
  5. "Dana end to end" passes: a scripted customer texts in, answers the intake questions, picks a time and is booked, with the owner alerted and the card and records updated.

**Plans**: TBD
**UI hint**: yes

**Planning notes** (settle these in discuss-phase):
- How a walk test drives the agent deterministically (scripted model versus a live model) is not defined in the SPEC; the activation gate cannot depend on a flaky call.
- Reminder timing and where it is configured are not defined (BOOK-04); `hours.md` only carries `booking_lead_minutes`.
- BOOK-06 and RECS-02 are derived from the SPEC rather than named in the Sprint 2 row; confirm scope.
- New concepts expected: scheduler, notifications, Livewire pages, time zones. Time zone handling comes from `business/profile.md`.

## Progress

**Execution Order:**
Phases execute in numeric order: 1 → 2 → 3

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 1. Foundation | 4/7 | In Progress | - |
| 2. Text Front Desk | 0/TBD | Not started | - |
| 3. Booking and the Gate | 0/TBD | Not started | - |

## Backlog

Parked, not scheduled. SPEC: "Sprint 3 onward is pulled by a real client or a job conversation, one at a time." Promote with `/gsd-review-backlog` when a client pulls one. Backlog requirement IDs are v2 in REQUIREMENTS.md and are not part of v1 coverage.

### Phase 999.1: Quote, Invoice, Paid (BACKLOG)

**Goal:** Legacy's money rules ported test-first, Stripe payment links by text, and a customer approval page. SPEC Sprint 3.
**Requirements:** MONY-01, MONY-02, MONY-03, MONY-04, MONY-05, MONY-06, MONY-07, MONY-08 (v2)
**Plans:** 0 plans

Plans:
- [ ] TBD (promote with /gsd-review-backlog when ready)

### Phase 999.2: Voice (BACKLOG)

**Goal:** A vendor voice agent calling engine tools, with calls and transcripts on the timeline and "they stopped texting, so call". Voice vendor is chosen with the first client that needs phones. SPEC Sprint 4.
**Requirements:** VOIC-01, VOIC-02, VOIC-03 (v2)
**Plans:** 0 plans

Plans:
- [ ] TBD (promote with /gsd-review-backlog when ready)

### Phase 999.3: Automations (BACKLOG)

**Goal:** Sequences from `automations/*.md`: lost-lead check-in at 7 to 10 days with a referral ask, review asks, reactivation. SPEC Sprint 5.
**Requirements:** AUTO-01, AUTO-02, AUTO-03, AUTO-04, AUTO-05 (v2)
**Plans:** 0 plans

Plans:
- [ ] TBD (promote with /gsd-review-backlog when ready)

### Phase 999.4: Settings by Conversation and Connectors (BACKLOG)

**Goal:** An admin agent edits the folder by diff for the owner to approve; QuickBooks, Google Calendar, parsed lead emails (Angi, Thumbtack), Zapier webhooks, and an MCP server for the owner's AI. SPEC Sprint 6.
**Requirements:** SETT-01, SETT-02, SETT-03, CONN-01, CONN-02, CONN-03, CONN-04, CONN-05, CONN-06 (v2)
**Plans:** 0 plans

Plans:
- [ ] TBD (promote with /gsd-review-backlog when ready)

Unscheduled v2 items with no sprint in the SPEC (FOLD-01, FOLD-02, KNOW-01, DEPL-01) are listed in REQUIREMENTS.md and will be attached to a phase when a client or the first deploy pulls them.
