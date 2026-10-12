# Roadmap: Ops Platform

## Overview

Ops Platform is a client-owned operating system where the business runs from a folder. The committed roadmap (v1, SPEC Sprints 0 to 2) takes it from a fresh scaffold to the headline outcome in three phases. Phase 1 makes the folder drive the app: a validated, versioned sync, every way a lead can arrive landing on one timeline, a Filament dashboard, and customer cards rendered into `records/`. Phase 2 puts an AI text front desk on that timeline, with a send-or-draft gate enforced in code and the model chosen in a file. Phase 3 finishes the conversation with booking, reminders and owner alerts, and turns on the activation gate: scripted customers walk-test every folder change before it goes live. Sprints 3 to 6 (quote to invoice, voice, automations, settings by conversation and connectors) are parked in the Backlog and are pulled by a real client or job conversation, one at a time.

## Phases

**Phase Numbering:**
- Integer phases (1, 2, 3): Planned milestone work
- Decimal phases (2.1, 2.2): Urgent insertions (marked with INSERTED)
- 999.x: Backlog parking lot, not scheduled

Decimal phases appear between their surrounding integers in numeric order.

- [x] **Phase 1: Foundation** - The folder drives the app and every way a lead can arrive lands on one timeline (SPEC Sprint 0) (completed 2026-10-11)
- [ ] **Phase 2: Text Front Desk** - An AI front desk replies by text from an agent file, behind a send-or-draft gate enforced in code (SPEC Sprint 1)
- [ ] **Phase 02.1: Owner Loop** (INSERTED) - Staff approvers, approve by text reply, email alerts with the "handled" follow-up, re-alerts and assist mode
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
**Plans**: 7/7 plans complete (sprint-0.md's three steps refined into seven; one plan per wave, run in order)

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
- [x] 01-05-PLAN.md — Customer cards in records/: `CustomerCard`, unique `RenderCustomerCard`, `INDEX.md`, `open-leads.md`, `records/CLAUDE.md`, `records:rebuild` (RECS-01)

**Wave 6** *(blocked on Wave 5 completion)*
- [x] 01-06-PLAN.md — Owner works from the dashboard: Conversations (thread, reply through `TextChannel`, Close/Reopen) and Contacts (list plus timeline), `FilamentUser` (LEAD-04, DASH-01)

**Wave 7** *(blocked on Wave 6 completion)*
- [x] 01-07-PLAN.md — `/dev/chat`, Business Folder page with Sync Now, seeder, QUAL-01 gate with the full suite green (DASH-02, DASH-01, QUAL-01)

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

**Plans**: 15/29 plans executed (one plan per wave, run in order; every plan adds to the shared sprint-1.md walk-through log and most share the front-desk files; re-sliced from 17 on 2026-10-11 so each plan fits its context budget)

Plans:
**Wave 1**
- [x] 02-01-PLAN.md — Wave 0: the suite passes on Postgres and SQLite, CI runs both, stray network calls fail every test, the example folder's file count in one test helper, `docs/sprints/sprint-1.md` opened (INFRA-01)

**Wave 2**
- [x] 02-02-PLAN.md — Agent files validated and compiled by channel, provider chain, Business Folder shows the front desk (AGNT-01, AGNT-04)

**Wave 3**
- [x] 02-03-PLAN.md — Code checks (the hard veto) and `rules/replies.md` checked at sync (GATE-01, GATE-02)

**Wave 4**
- [x] 02-04-PLAN.md — Fixed send vocabulary with its floor, structured `owner_alert`, code-checked voice texts, "Always held for you" (GATE-01, GATE-02)

**Wave 5**
- [x] 02-05-PLAN.md — Business clock and `{next_open}`: fixed replies promise a true time (GATE-01, GATE-02)

**Wave 6**
- [x] 02-06-PLAN.md — `rules/triage.md` and the Jev request behind `Classifier` (GATE-01)

**Wave 7**
- [x] 02-07-PLAN.md — Jev fails closed, triage files checked, the fake in every test, thresholds D-45 to D-47 (GATE-01)

**Wave 8**
- [x] 02-08-PLAN.md — `dispatched_at` claim, debounced `ProcessInbound`, burst claim, backfill (AGNT-02)

**Wave 9**
- [x] 02-09-PLAN.md — Sweep on the scheduler; form leads never reach the front desk (AGNT-02)

**Wave 10**
- [x] 02-10-PLAN.md — Owner alerts by text: phone on the user, code-built alert, never silent (GATE-01)

**Wave 11**
- [x] 02-11-PLAN.md — After-hours alerts wait for opening time; every alert on the timeline (GATE-01)

**Wave 12**
- [x] 02-12-PLAN.md — Profile phone, remembered login, alert link lands on the conversation, alert warnings (GATE-01)

**Wave 13**
- [x] 02-13-PLAN.md — The front-desk agent: whole routed files plus the fresh card, structured reply, provider fallback (AGNT-02, AGNT-04)

**Wave 14**
- [x] 02-14-PLAN.md — Text in, reply out or draft: the three-check send rule on one outbound chokepoint (AGNT-02, GATE-01)

**Wave 15**
- [x] 02-15-PLAN.md — Disclosure and sign-off, never_say veto, full verdict record, owner replies on the chokepoint, front desk failures reach the owner (GATE-01, GATE-02, AGNT-04)

**Wave 16**
- [ ] 02-16-PLAN.md — Held drafts alert the owner and show on the timeline (GATE-01)

**Wave 17**
- [ ] 02-17-PLAN.md — One live draft, the GATE-01 decision table, the holding reply (GATE-01, GATE-04)

**Wave 18**
- [ ] 02-18-PLAN.md — Intake answers stored and shown on the card; safe name filling (AGNT-03)

**Wave 19**
- [ ] 02-19-PLAN.md — Take over and hand back on one paused state (GATE-03)

**Wave 20**
- [ ] 02-20-PLAN.md — Owner reply pauses, request for a person, reply cap (GATE-03, GATE-02)

**Wave 21**
- [ ] 02-21-PLAN.md — Life-safety line: immediate, code-fixed, through a pause, then the owner (GATE-02, GATE-01)

**Wave 22**
- [ ] 02-22-PLAN.md — Approve, edit or discard a draft (GATE-04)

**Wave 23**
- [ ] 02-23-PLAN.md — Phone-friendly conversation view and home screen widgets (GATE-04)

**Wave 24**
- [ ] 02-24-PLAN.md — STOP recorded and every text to that number blocked (TXT-01)

**Wave 25**
- [ ] 02-25-PLAN.md — START, YES and HELP, simulated Advanced Opt-Out, every outbound path proven blocked (TXT-01)

**Wave 26**
- [ ] 02-26-PLAN.md — Twilio driver, receiving: signed inbound webhook selected by config (TXT-01)

**Wave 27**
- [ ] 02-27-PLAN.md — Twilio driver, sending: Messaging Service, 21610, status callbacks, go-live steps (TXT-01)

**Wave 28**
- [ ] 02-28-PLAN.md — Labeled data export (`gate:export`) and the phase gate on both databases (GATE-01, INFRA-01)

**Wave 29** *(has a decision checkpoint)*
- [ ] 02-29-PLAN.md — Cartographer map: inventory, Ian's ruling, catalog and cards (AGNT-02, GATE-01)

**UI hint**: yes

**Planning notes** (settle these in discuss-phase):
- The `send_on_own` and `draft_for_owner` action-key vocabulary is not defined in the SPEC beyond Principle 6's examples; define it and make the Validator reject unknown keys.
- Tests should use laravel/ai fakes; live runs and `/dev/chat` need a provider key in `.env`.
- Where intake answers are stored (AGNT-03) is open; the card must still show them.
- The agent reads the customer's card rendered fresh from the database (DEC-03), not the file on disk.
- Production deploy is not in this phase; DEPL-01 stays in v2. CI on Postgres is.
- Per the SPEC, a cartographer map starts in this sprint.

### Phase 02.1: Owner Loop (INSERTED)

**Goal:** The owner and the people they trust can act on the front desk from wherever they are: more than one person can approve drafts, a draft can be approved by replying to the alert text, the office hears by email, nothing waits unseen, and the agent steps back without going silent when a person joins the conversation.
**Depends on:** Phase 2
**Requirements**: TBD (define new IDs in REQUIREMENTS.md during `/gsd-discuss-phase 02.1`)
**Scope** (split out of Phase 2 on 2026-10-11; see `.planning/phases/02-text-front-desk/02-CONTEXT.md` § Deferred Ideas and `docs/phase-2-owner-input.md`):
- Staff users invited by the owner, each with a phone, an email, alert choices and a "can approve drafts" switch; user management and Sync Now stay owner-only (the first role split); sign out everywhere and remove a user.
- Approve by replying to the alert with a short code (K7 sends the draft as written, K7 plus words sends those words); only a registered phone can approve; a code for a replaced draft is refused with "The customer wrote again".
- Email alerts; the "handled" follow-up on the other channel names who acted and how.
- A draft nobody acts on: re-alert after a set time, a backup recipient, and an update to the customer if the wait runs past the holding reply's promise.
- Assist mode: an owner reply stops the agent sending on its own but it keeps drafting suggestions; it returns after a per-business quiet period (replaces Phase 2's owner-reply takeover).
- The after-hours on-call switch per business (urgent service requests such as plain flooding).
- `escalate_to` may name a staff user.
- The LLM fallback classifier driver on a ZDR route.
- A user's own phone texting the business. Phase 2 treats it as a customer (02-CONTEXT D-53), so an owner who replies to an alert text gets an agent answer as if they were a customer; the reply-code parser has to recognise a registered phone first.

**Success Criteria** (what must be TRUE):
  1. The owner invites a user from the dashboard, gives them the approve switch, and every approval, edit and discard records who did it; a user without the switch cannot approve, and only the owner manages users or runs Sync Now.
  2. Replying to an alert text with its code sends the draft (or the replacement words) through the same path as a dashboard approval, recorded on the timeline; a reply with no valid code, or from an unregistered phone, sends nothing and gets a short help text.
  3. When a draft is handled on one channel, the other channel gets a follow-up naming who handled it and how; a draft left untouched past the set time re-alerts and reaches the backup recipient.
  4. After a person replies in a conversation the agent only drafts there until the quiet period passes, then resumes on its own; Take over still stops it fully until handed back.
  5. With the on-call switch on, an after-hours urgent request alerts the on-call person right away; with it off, it waits for opening time.

**Plans:** 0 plans

Plans:
- [ ] TBD (run /gsd-plan-phase 02.1 to break down)

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
| 1. Foundation | 7/7 | Complete    | 2026-10-11 |
| 2. Text Front Desk | 15/29 | In Progress | - |
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
