# Ops Platform: Spec

Working name. Written 2026-10-10. Status: scaffold only, Sprint 0 not started.

## What this is

A client-owned operating system for a service business. Leads come in from every
channel, an AI front desk finishes the conversation, and the job moves through
booking, quote, invoice, payment and follow-up, with connectors to whatever the
business already runs. One core (Ian's). One instance per client (theirs).

What sets it apart: **the business runs from a folder.** How the business works
lives in plain files using Ian's ICM method: services, hours, intake questions,
how we talk, what the AI may send on its own, follow-up sequences, which model
each agent uses. The owner, Ian, or the owner's own AI changes the business by
editing a file. The platform checks the change, tests it against scripted
customers, and only then puts it live.

## Who it is for

Owner-operated service businesses with leads arriving across channels (calls,
texts, web forms, Angi, Thumbtack, Google, referrals) and nobody free to answer
them fast. Trades first.

Not for:
- A shop with few leads that is happy on Google Sheets. Give them files and
  folders only (the RyMac model, no platform).
- A $5M+ contractor running full field-service dispatch. Connect to their FSM
  tool instead of replacing it. Signpost makes the same call.

## Principles

1. **First to complete the conversation, not first to reply.** Headline metric:
   minutes from first contact to a booked appointment.
2. **The record writes itself.** Calls, texts, forms and payments land on one
   timeline per customer. The owner never retypes anything. Legacy's lesson: a
   form-heavy system went unused.
3. **Files hold how the business runs. The database holds what happened.** See
   "The two layers."
4. **No change goes live untested.** Every folder change is validated and walk-
   tested before it activates. A failure keeps the last good version live.
5. **The AI says it is an AI.** A human is always one message away.
6. **Sending authority is per action and enforced in code**, not only in the
   prompt. Agents send intake questions, scheduling and FAQ answers on their own.
   Anything with a price, a promise, a discount or a complaint goes to the owner
   as a draft. The exception is the owner's own words: price sentences on a
   service and conditional offer sentences, checked at sync against their
   amounts, are sent by code without the owner; a price, promise, discount or
   complaint the model writes still goes to the owner (Phase 02.1). The safety check
   reads every customer text, and the one outbound path refuses any automated
   text that carries no such reading; only the safety line goes out without it
   (Phase 02.1 gap closure).
7. **Money is integer cents. Payments decide invoice status.** Carried over from
   Legacy.
8. **Integrate, don't build:** telephony, voice models, payments, accounting.
9. **One instance per client.** The client owns their data and their folder.
   Ian keeps the core.
10. **Model- and vendor-agnostic** (practice direction, 2026-10-08). Any model
    per agent, swappable. Connectors over lock-in.

## The two layers

### Layer 1: the business folder (ICM)

Owner-editable. Source of truth for configuration and knowledge.

### Layer 2: the engine (this Laravel app)

Source of truth for records: contacts, conversations, messages, appointments,
quotes, invoices, payments, the activity timeline, the audit log. Runs the
timers, the channels and the money.

Records do not live in files because texts, calls and payment webhooks arrive in
the same second, and a ledger has to survive concurrent writers and half-finished
operations. A file-based invoice works for one person typing. It breaks when three
channels write at once.

### The bridge

**Files to engine (sync).**

1. Read the folder. Skip `records/` and `changelog.md`.
2. Parse each file: YAML front matter (structured settings the engine enforces)
   plus a markdown body (knowledge the agents read word for word).
3. Validate each file against its type. Errors name the file, the field and
   the fix, in plain words an owner can act on.
4. Fingerprint the candidate version (content hash, plus the git commit if the
   folder is a repo).
5. Walk-test the candidate (from Sprint 2): run every scripted customer in
   `tests/` against it.
6. Activate only if steps 3 and 5 pass. Otherwise the previous version stays
   live and the owner gets a plain message saying what broke. Every attempt,
   pass or fail, is appended to the folder's `changelog.md`.

The engine reads configuration only from the active compiled version
(`App\Icm\ActiveConfig`), never from disk at request time. A half-edited file
never reaches a customer, and rollback means activating the previous version.

**Engine to files (records).** The engine writes a readable copy of what
happened into `records/`, so any AI finds it by routing instead of querying a
database:

- `records/customers/<name>.md`: one card per customer. Contact details, lead
  source and cost, status, next appointment, balance, and the timeline newest
  first: texts, call summaries, forms, appointments, payments. Full call
  transcripts sit beside the card in `records/customers/<name>/calls/`.
- `records/customers/INDEX.md`: name, phone and file for every customer, so an
  AI finds "Dana" in one read.
- Summary files: `today.md` (schedule), `open-leads.md`, `who-owes-us.md`,
  `this-week.md` (numbers).
- `records/CLAUDE.md`: the router for these files ("customer question: read
  INDEX.md, then the card").

How the files stay right when everything happens at once:

1. Each webhook (text, call transcript, payment) is verified and written to the
   database inside a transaction. The provider's event id is unique, so a
   retried webhook cannot record a payment twice.
2. The write fires an event, which queues a re-render of that customer's card
   and of any summary file it touches.
3. The re-render reads the database, not the event. Three events in the same
   second produce a card showing all three, in order, whatever order the jobs
   ran in. Renders for one customer never overlap, and an event that lands
   during a render always triggers one more.
4. Files are written to a temp file and renamed, so an AI never reads half a card.
5. A nightly full rebuild catches anything missed.

Records files are read-only. Editing one changes nothing, and the next render
overwrites it. To change what happened (record a cash payment, say), the owner
tells the dashboard or the admin agent, which writes the database, which
re-renders the card.

The platform's own agents use the same card, rendered fresh from the database
at the moment they need it rather than read from disk. A customer who texts "I
just paid" while the payment webhook lands has to get an answer that knows about
the payment, and a file can trail the database by a few seconds. Same card, two
delivery paths: on disk for the owner's AI, in context for the platform's agents.

Card numbers never exist in this system; Stripe holds them. A card holds what
the owner would tell a staff member. Later, an MCP server (`laravel/mcp`)
answers live questions the files do not cover.

**Settings by conversation** (the target experience from 2026-10-08). The owner
says "set up the receptionist for the weekend." An admin agent proposes an edit
to the folder as a diff, the owner approves it, and the normal sync validates,
tests and activates it. The AI never changes live configuration directly. It
edits files like everyone else.

### Where the folder lives

- Sprint 0: a path on disk set by `ICM_PATH`. An example business ships in
  `examples/`.
- Per client, later: a git repo the client owns, synced to wherever they and
  their AI can reach it (Dropbox, Google Drive, a local folder). Decide with the
  first client.
- Three ways to edit, all touching the same files: the owner's own AI, an editor
  page in the dashboard, or Ian.

### Knowledge retrieval: route, don't retrieve

Agents load the files that their agent file and the folder's `CLAUDE.md` point
to. A small business's knowledge fits in the model's context. pgvector comes in
only for big reference material (a 1,550-item supplier price list, say), and that
index is rebuilt from the files on sync, never edited directly.

## The business folder

```
<business>/
├── CLAUDE.md            Router for any AI reading the folder. Points only; holds no facts.
├── START-HERE.md        For the owner: how to change things and what happens after you save.
├── changelog.md         Written by the sync. Every attempt, pass or fail, newest first.
├── business/
│   ├── CONTEXT.md
│   ├── profile.md       type: business
│   ├── hours.md         type: hours
│   ├── lead-sources.md  type: lead_sources
│   ├── faq.md           type: knowledge
│   ├── services/
│   │   ├── CONTEXT.md
│   │   └── <service>.md type: service (one file per service)
│   └── offers/
│       ├── README.md
│       └── <offer>.md   type: offer (one file per offer)
├── voice/
│   └── how-we-talk.md   type: voice
├── rules/
│   ├── CONTEXT.md
│   ├── sending.md       type: sending_rules
│   └── replies.md       type: set_replies
├── agents/              type: agent (Sprint 1)
├── automations/         type: automation (Sprint 5)
├── connectors/          type: connector (Sprint 6)
├── tests/               type: walk_test (Sprint 2)
├── rooms/
│   └── <room>/
│       └── CONTEXT.md   type: room (the room's contract)
└── records/             GENERATED by the engine. Read-only. Ignored by the sync.
    ├── CLAUDE.md        Router for the records
    ├── today.md, open-leads.md, who-owes-us.md, this-week.md
    └── customers/       INDEX.md plus one card per customer
```

One fact, one file. If the hours change, `hours.md` changes and nothing else.

A room is a part of the product a business can have or not: the engine knows its rooms in code, and a business has a room when its folder holds `rooms/<room>/CONTEXT.md`. New kinds of file a room defines live in its own folder under `rooms/<room>/` (Phase 4, D-01 and D-04), and the sync refuses one anywhere else, naming the folder it belongs in; today's kinds of file stay where they are (D-04). Only a CONTEXT.md directly inside `rooms/<room>/` is a contract; every other CONTEXT.md stays a router.

### File types (the front matter contract)

| type | File | Required front matter | What the engine does with it |
|---|---|---|---|
| business | business/profile.md | name, phone, timezone, service_area (list) | Identity in every agent prompt; timezone for all scheduling |
| hours | business/hours.md | weekly (mon to sun: open/close or closed), closures (dates), booking_lead_minutes | Availability for booking |
| lead_sources | business/lead-sources.md | sources: list of key, name, cost_cents_per_lead | Attribution and cost per lead on every contact |
| knowledge | business/faq.md and others | none beyond type | Body is read by agents as reference |
| service | business/services/*.md | name, slug, duration_minutes, book_online, price_policy (none, starting_at, fixed), price_cents (unless none), intake (list of key, ask, options), price_sentences (optional list: sentence, answers, amounts_cents), price_default (optional: sentence, amounts_cents) | Intake questions, booking length, what agents may say about price. The owner's price sentences are picked and sent by code from intake answers and checked at sync against the file's amounts; a price the model writes still waits for the owner |
| voice | voice/how-we-talk.md | ai_disclosure, sign_off, never_say (list) | Tone and disclosure for every outbound message |
| sending_rules | rules/sending.md | send_on_own (intake_question, scheduling, faq_answer only), draft_for_owner (price, promise, discount, complaint, other), owner_alert (channels, right_away, after_hours) | The send-or-draft gate, enforced in code |
| set_replies | rules/replies.md | owner_name, utility_phone (optional), holding_reply, partial_holding_reply, check_in_reply, added_info_reply, complaint_reply, handoff_reply, discount_reply (optional; the holding reply goes when absent), price_leads (same, higher, lower, changed; required once any service has price sentences, and the sync names the service), check_in_hours (optional, 1 to 12; off when absent) | Fixed texts sent without the owner; the sync runs the code checks on every reply and every lead (no price, discount, promise or never_say phrase), and only discount_reply may name a discount. A version live before a reply existed sends the holding reply in its place. The six fill-ins are {owner_name}, {business_name}, {next_open}, {today_or_next_open}, {next_opening} and {check_in_time}; each time is computed from hours.md when the text is sent, and the sync refuses {check_in_time} while check_in_hours is absent |
| offer | business/offers/*.md | name, slug, sentence, amount_cents or percent (exactly one; percent a whole number 1 to 100), start_date (Y-m-d), end_date (optional Y-m-d, on or after start_date) | One offer per file, every file kept and indexed by slug. Sent by code, never by the model: every live offer's sentence goes out in one text when a customer asks for a discount. Live by date in the business time zone, end date inclusive. The sync checks the sentence against its own field: the $ amounts must equal amount_cents and the percentages must equal percent, both ways, money is written as $ and digits, and the promise and never_say checks stay. The business confirms eligibility at the visit |
| room | rooms/<room>/CONTEXT.md | owns (list of paths or `*` patterns; `[]` or `none` when the room owns no files), reads (list of paths; `[]` or `none`), writes_records (list of paths under records/; `[]` or `none` when the room writes nothing), connector (the room's slot name, or `none`) | The contract of one room. The sync checks it from Phase 4: each list stays inside the folder, writes_records stays under records/, the connector names the slot the room's code offers, and a rooms/ folder for a room the engine does not have is refused. The word `none` and `[]` mean the same empty list; any other word where a list belongs is refused. Only a CONTEXT.md directly in rooms/<room>/ is a contract (D-03) |
| agent | agents/*.md | name, channel, provider, model, fallback (list), loads (list of folder paths), escalate_to | Builds the agent at runtime |
| automation | automations/*.md | trigger, wait, send, stop_when (list) | Scheduled sequences |
| connector | connectors/*.md | system, direction (in, out, both), owns (list), reads (list), schedule | What syncs and which way, and which kinds of record this system owns. One owner per fact: the sync refuses two connectors owning the same kind of record (`.planning/PROJECT.md`, "One owner per fact", 2026-10-19). No secrets: credentials live encrypted in the engine |
| walk_test | tests/*.md | persona, channel, script (list of customer turns), expect (list of checks) | The activation gate |

The markdown body of every file is free text. Agents read it as knowledge: what
we do, what we don't, how to explain the diagnostic fee. The front matter is what
the engine enforces.

Example service file:

```markdown
---
type: service
name: AC repair
slug: ac-repair
duration_minutes: 90
book_online: true
price_policy: starting_at
price_cents: 8900
intake:
  - key: symptom
    ask: What is the system doing?
    options: [Not cooling, Won't turn on, Making noise, Leaking water, Something else]
  - key: system_age
    ask: About how old is the system?
    options: [Under 5 years, 5 to 10 years, Over 10 years, Not sure]
---
We repair central air, heat pumps and mini-splits. We do not service window units.
The $89 diagnostic fee is waived if the customer approves the repair the same visit.
```

## Modules and build order

| Sprint | Module | What ships | Laravel concepts introduced |
|---|---|---|---|
| 0 | Foundation | Folder sync and validation, versions, contacts, conversations, one timeline, fake text channel, web lead form, test chat page, records snapshot, dashboard screens | Routes, controllers, validation, Eloquent, migrations, factories, artisan commands, queued jobs, the service container, Filament resources, Pest |
| 1 | Text front desk | Agent built from `agents/*.md`, loads routed files, asks intake questions, send-or-draft gate in code, owner takeover, Twilio driver once A2P clears | AI SDK agents and tools, structured output, provider failover, queues |
| 2 | Booking and the gate | Availability from `hours.md`, booking link with tap-to-choose options, appointments, reminders, owner alerts. Walk tests run on every sync and gate activation. Dana end to end | Scheduler, notifications, Livewire pages, time zones |
| 3 | Quote, invoice, paid | Legacy money rules ported test-first, Stripe payment links by text, customer approval page | Policies, database transactions, signed URLs, webhooks |
| 4 | Voice | A vendor voice agent (Vapi, Retell or ElevenLabs) calling engine tools; calls and transcripts on the timeline; "they stopped texting, so call" | Webhook verification, tool endpoints |
| 5 | Automations | Sequences from `automations/*.md`: lost-lead check-in at 7 to 10 days with a referral ask, review asks at several points, reactivation | Scheduler, idempotent jobs |
| 6 | Settings by conversation, connectors | Admin agent edits the folder by diff; QuickBooks, Google Calendar, lead emails parsed (Angi, Thumbtack), Zapier webhooks, MCP server for the owner's AI | OAuth, inbound mail, MCP |

Sprints 0 to 2 are committed. Sprint 3 onward is pulled by a real client or a
job conversation, one at a time.

## Rules carried over from Legacy (Sprint 3)

From the money-path fixes in legacy-crm (commit 040f7ab) and its map. Each
becomes a Pest test before the code exists.

- A deposit invoice is a percent of the approved quote. The final invoice takes
  it back as its own credit line that records which invoice it came from. An
  invoice total is recalculated from its own lines, net of credits.
- Payments decide whether an invoice is paid or partly paid. Staff can set only
  draft, sent and void by hand.
- The next invoice number is the highest existing number plus one, never a count.
- A quote can be approved only while it is draft, sent or viewed. One function
  approves, whichever door the approval comes through (customer page with a
  signature, or staff without one).
- Customer links expire after a configurable number of days and answer 410 Gone.

What does **not** carry over (the collisions Legacy's map recorded):
- "Job" meaning three things (a required parent row, an approved estimate, a
  board). Here a job is one thing, defined once.
- "Sent" meaning different events on three objects, and "total" versus
  "grand_total." One name, one meaning.
- Tax falling back to 7% in one place and 0% in another when the rate is empty.
  Here an empty rate is a validation error, never a silent default.

## Stack

| Piece | Choice | Notes |
|---|---|---|
| Framework | Laravel 13.35, PHP 8.3+ | Scaffolded 2026-10-10 |
| Back office | Filament 5.10 (Livewire 4) | Owner and staff screens. Customer pages in Livewire |
| AI | laravel/ai 1.2 | Provider and model per agent, failover lists, client-owned keys possible |
| Tests | Pest 5.3 | SQLite in memory |
| Agent tooling | Laravel Boost 2.10 | MCP for Claude Code, Laravel guidelines, project rules in `.ai/rules` |
| Database | SQLite locally in Sprint 0; Postgres in CI and production from Sprint 1 | Legacy tested on SQLite and checked Postgres separately. Here CI runs the suite on Postgres so the two cannot drift |
| Queues | Database driver locally; Redis and Horizon in production | |
| Texts and numbers | Twilio | Fake channel until A2P registration clears |
| Payments | Stripe payment links | Not Cashier, which bills your own subscribers |
| Voice | Vendor, chosen with the first client that needs phones | |
| Hosting | Decided at first deploy (Laravel Cloud or Forge are the low-ops paths) | One instance per client |

## Open decisions

- Product name (working name: ops-platform).
- Where client folders sync from (Dropbox, Drive, or git only). Decide with the
  first client.
- Voice vendor. Decide with the first client that needs phones.
- Hosting. Decide at first deploy.
- Which entity registers A2P for Ian's own test number.

## How we build

- Spec first, then tests, then code. Each sprint has a plan in `docs/sprints/`
  with acceptance criteria.
- Claude Code builds. Ian reviews. Each new Laravel concept gets a short
  walk-through the first time it appears, logged in that sprint's file.
- Boost is installed, so Claude Code reads Laravel guidance and records project
  rules in `.ai/rules`.
- A cartographer map starts in Sprint 1, once there is something to map.
