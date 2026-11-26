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
5. Walk-test the candidate: run every scripted customer in
   `tests/` against it. Each walk replays Ian's recording of the conversation, and a walk with no recording, or one that fails a check, keeps the previous version live.
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
- Summary files: `today.md` (schedule), `time-to-booked.md` (how fast new
  customers book), `open-leads.md`, `who-owes-us.md`, `this-week.md`
  (numbers).
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
├── connectors/          type: connector (checked from Phase 4: slot, owns, reads)
├── tests/               type: walk_test
├── rooms/
│   └── <room>/
│       └── CONTEXT.md   type: room (the room's contract)
└── records/             GENERATED by the engine. Read-only. Ignored by the sync.
    ├── CLAUDE.md        Router for the records
    ├── today.md, time-to-booked.md, open-leads.md, who-owes-us.md, this-week.md
    └── customers/       INDEX.md plus one card per customer
```

One fact, one file. If the hours change, `hours.md` changes and nothing else.

A room is a part of the product a business can have or not: the engine knows its rooms in code, and a business has a room when its folder holds `rooms/<room>/CONTEXT.md`. New kinds of file a room defines live in its own folder under `rooms/<room>/` (Phase 4, D-01 and D-04), and the sync refuses one anywhere else, naming the folder it belongs in; today's kinds of file stay where they are (D-04). Only a CONTEXT.md directly inside `rooms/<room>/` is a contract; every other CONTEXT.md stays a router.

A file or folder name that holds a hidden character is refused in plain words, before any other check, and the previous version stays live. Hidden characters are a line break, a tab, a text-direction mark, and a zero-width, joiner or tag character (Unicode categories Cc, Cf, Zl and Zp). Every refusal line also shows such a character as `[line break]` or `[hidden character]` instead of printing it, so a name can never start a forged line in `changelog.md` or look like another name (05-CONTEXT D-19 and D-25; closes Phase 4 UAT gap G-04-1). Accented, Cyrillic and CJK names, and an emoji with its variation selector, pass. Hidden characters inside file values are not checked yet (CONTEXT Deferred Ideas).

### Who owns each file

Every file has exactly one owner: a room or the house (D-11). This is the map; the room's own `rooms/<room>/CONTEXT.md` is what the sync enforces. A pattern may use `*` for any run of characters.

| File | Owner | Readers named so far |
|---|---|---|
| `business/profile.md` | company knowledge | every room |
| `business/hours.md` | company knowledge | front desk (next open), schedule and booking, content generation, Google Business Profile |
| `business/services/*.md` | company knowledge | front desk (intake, price sentences), schedule and booking (duration, book_online), content generation, Google Business Profile |
| `business/faq.md` | company knowledge | front desk, content generation |
| `voice/how-we-talk.md` | company knowledge | front desk, content generation, Google Business Profile |
| `business/lead-sources.md` | marketing | leads, front desk (ROOM-09) |
| `business/offers/*.md` | marketing | leads, front desk (ROOM-09), content generation, Google Business Profile |
| `rules/sending.md` | front desk | team (reads `owner_alert`) |
| `rules/replies.md` | front desk | team (reads `owner_name`) |
| `rules/triage.md` | front desk | |
| `agents/*.md` | the room the agent serves (`agents/front-desk.md`: front desk) | team (meant to read `escalate_to` in `agents/front-desk.md`; nothing reads it yet, parked with the Owner Loop, backlog 999.5) |
| `connectors/*.md` | the room whose slot the file fills (the text channel's: front desk; Google Business Profile's: its room) | |
| `rooms/*/CONTEXT.md` | the room whose folder it is | |
| `rooms/content_generation/brief.md` | content generation | |
| `rooms/money/notes.md` | money | |
| `rooms/schedule_and_booking/booking.md` | schedule and booking | |
| `rooms/google_business_profile/categories.md` | Google Business Profile | |
| `CLAUDE.md` | the house | |
| `START-HERE.md` | the house | |
| `*CONTEXT.md` | the house (every CONTEXT.md outside `rooms/`) | |
| `tests/*.md` | the house | |

`rules/sending.md` is not split: the front desk owns it and the team reads `owner_alert` (D-13). `README.md` files, `changelog.md` and `records/` are skipped by the sync and need no owner (D-11). Leads, content generation, Google Business Profile, owner intelligence, money and team own none of today's files (D-12). Once a room lands, its CONTEXT.md is what the sync enforces, this table is the map, and the room's boot test fails if the two disagree (D-14). Ownership is enforced as each room lands, so a file whose room is not built yet passes (D-15). A room may claim only the kinds of file its code handles (D-17). A `*` pattern in a room's owns passes over routers, which stay the house's, so company knowledge's `business/services/*.md` does not claim the folder guide `business/services/CONTEXT.md`; a router named exactly, or a walk test however it is matched, is refused. A room that can never be left out (company knowledge and marketing from Phase 5, the front desk and team from Phase 6) gets one line naming it and why when its folder is absent, instead of a line for each file it owns (05-CONTEXT D-02).

### Kinds of record a connector may own

A connector file lists under `owns` the kinds of record its outside system is the truth for, and under `reads` the kinds it only copies. `owns` may hold only these eight keys (D-22):

| Key | Kind of record |
|---|---|
| `customers` | The people and companies the business deals with |
| `sales_activity` | Leads, quotes and follow-ups |
| `invoices` | What the business has billed |
| `payments` | What customers have paid |
| `expenses` | What the business has spent |
| `work_orders` | Jobs to be done and jobs done |
| `schedule` | Who is booked where, and when |
| `documents` | Files such as photos, permits and contracts |

Owning a kind says the outside system is the truth, and the platform never treats its own copy as the truth. A kind is accepted even when the platform keeps none of it yet, such as `invoices`. Ownership is per kind, per field waits for a client (D-22). A key written twice in one list, or in both `owns` and `reads` of one file, is refused.

### Kinds of record the platform owns

These are the platform's own. A connector may list them under `reads` but never under `owns` (D-23):

| Key | Kind of record |
|---|---|
| `projects` | The job a customer has asked for, from first text to paid |
| `texts` | Every message sent or received, with who or what sent it |
| `timeline` | The dated history of a customer or project |

Texts stay the platform's because the sent message is the platform's own row and every automated send goes through `OutboundSender`. Conversations, owner alerts and drafts are on neither list, so a connector file naming one is refused like any other unknown word. Both lists are the code's lists (`OwnableKind` and `PlatformKind`), and a test fails if SPEC and the code disagree (D-14). Moving a kind from this list onto the first is its own decision with Ian.

### What a business calls a project

`business/profile.md` names in `project_word` what this business calls a piece of work, from this list only, matched without regard to letter case. The customer card, the contact screen and the dashboard show it, and screens set the casing (Phase 7, D-12).

| Word | Plural |
|---|---|
| `Job` | `Jobs` |
| `Project` | `Projects` |
| `Matter` | `Matters` |
| `Case` | `Cases` |
| `Work order` | `Work orders` |
| `Engagement` | `Engagements` |

The list is `ProjectWord` in code, and a test fails if SPEC and the code disagree. Adding a word is a code change plus a row here; removing one breaks any live folder that uses it.

### File types (the front matter contract)

| type | File | Required front matter | What the engine does with it |
|---|---|---|---|
| business | business/profile.md | name, phone, timezone, service_area (list), project_word (one word from What a business calls a project, any letter case) | Identity in every agent prompt; timezone for all scheduling; project_word names a project on the customer card, the contact screen and the dashboard |
| hours | business/hours.md | weekly (mon to sun: open/close or closed), closures (dates), booking_lead_minutes | Availability for booking |
| lead_sources | business/lead-sources.md | sources: list of key, name, cost_cents_per_lead | Attribution and cost per lead on every contact |
| knowledge | business/faq.md and others | none beyond type | Body is read by agents as reference |
| service | business/services/*.md | name, slug, duration_minutes, book_online, price_policy (none, starting_at, fixed), price_cents (unless none), intake (list of key, ask, options), price_sentences (optional list: sentence, answers, amounts_cents), price_default (optional: sentence, amounts_cents) | Intake questions, booking length, what agents may say about price. The owner's price sentences are picked and sent by code from intake answers and checked at sync against the file's amounts; a price the model writes still waits for the owner |
| voice | voice/how-we-talk.md | ai_disclosure, sign_off, never_say (list) | Tone and disclosure for every outbound message |
| sending_rules | rules/sending.md | send_on_own (intake_question, scheduling, faq_answer only), draft_for_owner (price, promise, discount, complaint, other), owner_alert (channels, right_away, after_hours) | The send-or-draft gate, enforced in code |
| set_replies | rules/replies.md | owner_name, utility_phone (optional), holding_reply, partial_holding_reply, check_in_reply, added_info_reply, complaint_reply, handoff_reply, discount_reply (optional; the holding reply goes when absent), price_leads (same, higher, lower, changed; required once any service has price sentences, and the sync names the service), check_in_hours (optional, 1 to 12; off when absent) | Fixed texts sent without the owner; the sync runs the code checks on every reply and every lead (no price, discount, promise or never_say phrase), and only discount_reply may name a discount. A version live before a reply existed sends the holding reply in its place. The six fill-ins are {owner_name}, {business_name}, {next_open}, {today_or_next_open}, {next_opening} and {check_in_time}; each time is computed from hours.md when the text is sent, and the sync refuses {check_in_time} while check_in_hours is absent |
| offer | business/offers/*.md | name, slug, sentence, amount_cents or percent (exactly one; percent a whole number 1 to 100), start_date (Y-m-d), end_date (optional Y-m-d, on or after start_date) | One offer per file, every file kept and indexed by slug. Sent by code, never by the model: every live offer's sentence goes out in one text when a customer asks for a discount. Live by date in the business time zone, end date inclusive. The sync checks the sentence against its own field: the $ amounts must equal amount_cents and the percentages must equal percent, both ways, money is written as $ and digits, and the promise and never_say checks stay. The business confirms eligibility at the visit |
| room | rooms/<room>/CONTEXT.md | owns (list of paths or `*` patterns; `[]` or `none` when the room owns no files), reads (list of paths; `[]` or `none`), writes_records (list of paths under records/; `[]` or `none` when the room writes nothing), connector (the room's slot name, or `none`) | The contract of one room. The sync checks it from Phase 4: each list stays inside the folder, writes_records stays under records/, the connector names the slot the room's code offers, and a rooms/ folder for a room the engine does not have is refused. The word `none` and `[]` mean the same empty list; any other word where a list belongs is refused. Only a CONTEXT.md directly in rooms/<room>/ is a contract (D-03) |
| content_brief | rooms/content_generation/brief.md | none beyond type | Kept and compiled. Read by nothing that acts in v2.0: the brief is guidance a model reads once content generation's writer is built, and nothing in it is enforced (a rule that must hold goes in voice/how-we-talk.md never_say). A business keeps one brief: the sync refuses a second content brief wherever the room's contract owns points, in one line naming both files; the file at the path in the File column is the one kept, so the line is on the file the owner added (05-CONTEXT D-12, D-27, D-29) |
| money_notes | rooms/money/notes.md | none beyond type | Kept and compiled. Read by nothing that acts in v2.0: the owner's plain-words notes on how the business gets paid today, for whoever sets up the money work later (backlog 999.1). Holds no amounts, percents or terms. The sync refuses what looks like a card number (13 to 19 digits passing the card check digit, phones skipped) and every 9-digit number however it is grouped (routing, Social Security, employer ID), except a ZIP+4 written 60601-1234; a 9-digit number with a leading + is refused unless it is a phone. Digit groups count as one number when a dot, any one Unicode dash, or any run of horizontal whitespace (spaces, tabs, non-breaking spaces, two spaces included) holding at most one line break joins them, so a markdown hard break or an indented continuation line still joins; a blank line, a comma, or a space beside a dot keeps them apart. A span over 19 digits is also checked group by group: any run of whole groups that makes a card or exactly 9 digits is refused unless that run is a whole phone number, so a card written after a date is caught; a 9-digit number written right after another number in a span of 19 digits or fewer is an accepted miss (05-CONTEXT D-31). The refusal is one fixed line and never repeats the number (05-CONTEXT D-15, D-16, D-22, D-26, D-28). A file the check cannot finish reading (a very long run of digit groups) is refused with its own fixed line, "this file is too long for the number check to read; shorten it or split it", never let through (05-CONTEXT D-30). Bank account numbers have no shape, and CONN-01's no-secrets scan will cover rooms/money/. A business keeps one notes file: the sync refuses a second money notes file wherever the room's contract owns points, in one line naming both files; the file at the path in the File column is the one kept, so the line is on the file the owner added (05-CONTEXT D-27, D-29) |
| booking | rooms/schedule_and_booking/booking.md | step_minutes (5 to 240), drive_buffer_minutes (0 to 240), days_ahead (1 to 60), link_expires_days (1 to 30), reminder_hours_before (list of up to 5 whole hours, 1 to 336, no repeats; may be empty), send_window (start and end, 24-hour HH:MM, start before end), link_line, confirmation_line, reminder_line, visit_line (each up to 300 characters) | Every booking setting except booking_lead_minutes, which stays in business/hours.md. The four lines are the owner's exact words to customers and no code has a default for them: with no booking file nothing customer-facing is built from it. visit_line is what the front desk adds after its own words when a customer with a visit booked ahead asks about it (Phase 8 context D-39 as amended). The sync refuses a link_line without {link} exactly once, a confirmation, reminder or visit line without {day} and {time} exactly once each, any other word in braces ({service} is allowed at most once in the confirmation, reminder and visit lines), any line with an exclamation point or an em dash, and a send_window whose start is not before its end, each in a plain line naming the file and the field. A business keeps one booking file: the sync refuses a second booking file wherever the room's contract owns points, in one line naming both files; the file at the path in the File column is the one kept, so the line is on the file the owner added (Phase 8 context D-03, D-28) |
| gbp_categories | rooms/google_business_profile/categories.md | primary_category (text), additional_categories (optional list of text: at most 9, no repeats, none equal to the primary, compared ignoring case and extra spaces) | Kept and compiled. Read by nothing that acts in v2.0: the categories Google shows, checked for shape only; whether a name is one of Google's categories is checked when the real connector lands. It holds no description and no service area, which the folder already holds in business/profile.md. A business keeps one categories file: the sync refuses a second Google Business Profile categories file wherever the room's contract owns points, in one line naming both files; the file at the path in the File column is the one kept, so the line is on the file the owner added (05-CONTEXT D-06, D-07, D-27, D-29) |
| agent | agents/*.md | name, channel, provider, model, fallback (list), loads (list of folder paths), escalate_to | Builds the agent at runtime |
| automation | automations/*.md | trigger, wait, send, stop_when (list) | Scheduled sequences |
| connector | connectors/*.md | system, direction (in, out, both), slot, owns (list, or `none`), reads (list, or `none`), schedule | What syncs and which way, and which kinds of record this system owns. The sync checks `slot`, `owns` and `reads` from Phase 4; `system`, `direction` and `schedule` are passed through unchecked until CONN-01, which adds them and the no-secrets scan with the first real connector. The word `none` and `[]` mean the same empty list, as in the room contract; any other word where a list belongs is refused. One owner per fact: the sync refuses two connectors owning the same kind of record (`.planning/PROJECT.md`, "One owner per fact", 2026-10-19). No secrets: credentials live encrypted in the engine |
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
