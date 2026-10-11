# Requirements: Ops Platform

**Defined:** 2026-10-10
**Core Value:** Finish the customer conversation fast: minutes from first contact to a booked appointment, with the business running from a folder the owner controls and no change going live untested.

Sources: Phase 1 requirements are derived from the "Done when" list in docs/sprints/sprint-0.md (ingest slugs shown as `intel:`). Phase 2 and 3 requirements are derived from docs/SPEC.md (Principles, "The bridge", "File types", "Modules and build order", "Stack"). Items marked *(derived)* are implied by the SPEC but not spelled out there; confirm them in discuss-phase.

## v1 Requirements

Committed scope: SPEC says Sprints 0 to 2 are committed. Each maps to exactly one roadmap phase.

### Business Folder and Sync (Phase 1)

- [x] **ICM-01**: `php artisan icm:sync` reads `examples/example-hvac`, compiles a version and makes it active; `php artisan icm:status` shows the active version, its file count and when it went live. (intel: REQ-icm-sync-activate)
- [x] **ICM-02**: A broken folder change never goes live. Deleting a required field or setting `price_policy: maybe` makes the sync fail with a message that names the file and the field in plain words, the previous version stays active, and the failure is appended to the folder's `changelog.md`. (intel: REQ-icm-sync-reject-broken)

### Inbound and Timeline (Phase 1)

- [ ] **LEAD-01**: A text from a new number through the fake channel (`POST /webhooks/text/fake`) creates a contact, a conversation, a message and a timeline entry; a second text from the same number lands in the same conversation. (intel: REQ-inbound-text-fake)
- [ ] **LEAD-02**: A web form lead (`POST /leads`) with `source=angi` creates or matches a contact, records the source and the cost per lead from `business/lead-sources.md`, and lands on the same timeline. (intel: REQ-inbound-web-lead)
- [ ] **LEAD-03**: A text and a form from the same phone number end up on one contact. Phone numbers are stored in E.164 using giggsey/libphonenumber-for-php-lite. (intel: REQ-contact-match-by-phone)
- [ ] **LEAD-04**: A reply the owner types in the dashboard goes out through the fake text channel and is stored as sent. (intel: REQ-owner-reply-fake-channel)

### Records Snapshots (Phases 1 and 3)

- [ ] **RECS-01**: Every inbound text or form re-renders that customer's card in `records/customers/` from the database and updates `INDEX.md` and `open-leads.md`; two inbound events for one customer in the same second produce one card showing both; `php artisan records:rebuild` regenerates everything; the sync ignores `records/`. (intel: REQ-records-render-customer-card) Phase 1
- [ ] **RECS-02**: `records/today.md` (the schedule) is rendered from appointments on every booking change, and a nightly full rebuild of `records/` is scheduled to catch anything missed. *(derived: SPEC "The bridge" lists today.md and the nightly rebuild; Sprint 0 builds neither, and Sprint 2 introduces the scheduler)* Phase 3

### Dashboard (Phase 1)

- [ ] **DASH-01**: Filament dashboard screens: Contacts (list plus a timeline view), Conversations (thread with a reply box), and a Business Folder page (active version, files, last sync result, errors, a Sync Now button). (intel: REQ-dashboard-screens)
- [ ] **DASH-02**: A local-only test chat page at `/dev/chat` lets Ian text "as a customer" through the fake webhook and watch the thread update; it returns 404 outside the local environment. (intel: REQ-dev-chat-page)

### Quality Bar (Phase 1)

- [ ] **QUAL-01**: Every Pest test passes. Money fields are integer cents. Nothing reads configuration from disk except the sync. Two project rules are recorded with Boost in `.ai/rules`: all money values are integer cents, never floats; application code reads business configuration only through `App\Icm\ActiveConfig`. (intel: REQ-suite-green-money-config-rules)

### Text Front Desk (Phase 2)

- [ ] **AGNT-01**: Agent files (`agents/*.md`, type `agent`: name, channel, provider, model, fallback, loads, escalate_to) are validated by the sync with plain-words errors and compiled into the active version; a broken agent file is rejected like any other file.
- [ ] **AGNT-02**: On an inbound text, the `ProcessInbound` job runs the agent built from the active agent file: it loads the routed files named in `loads` whole (from `ActiveConfig`, never from disk) plus the customer's card rendered fresh from the database, and replies in the same conversation.
- [ ] **AGNT-03**: The agent asks the intake questions defined in the matching `business/services/*.md` file (using their options) and captures the answers as structured output against the contact or conversation, visible on the timeline and the customer card. *(derived: SPEC says "asks intake questions" and "structured output"; the storage shape is a discuss-phase decision)*
- [ ] **AGNT-04**: Provider and model are set per agent in its file, with a `fallback` list: if the primary provider or model fails, the agent falls through the list; swapping a model is an edit to the agent file, not code. (DEC-07)
- [ ] **GATE-01**: The send-or-draft gate is enforced in code from `rules/sending.md`: intake questions, scheduling and FAQ answers send on their own; anything with a price, a promise, a discount or a complaint is saved as a draft for the owner and never sent, whatever the model outputs. The owner is alerted as `owner_alert` specifies.
- [ ] **GATE-02**: The first outbound message to a customer discloses that it is an AI using `ai_disclosure` from `voice/how-we-talk.md`; `never_say` phrases are never sent; `sign_off` is applied; and a customer who asks for a person is handed to `escalate_to` and the owner is alerted. (Principle 5)
- [ ] **GATE-03**: The owner can take over a conversation from the dashboard; while taken over the agent sends nothing in that conversation, and the owner can hand it back.
- [ ] **GATE-04**: The owner can review a draft in the dashboard and approve, edit and send, or discard it. *(derived: SPEC says price/promise/discount/complaint "goes to the owner as a draft")*
- [ ] **TXT-01**: A `TwilioTextChannel` implements the `TextChannel` interface (outbound send, inbound webhook with signature verification and unique provider message id), selected by config with no other code change. Verified against Twilio-shaped payloads; going live on Ian's registered test number follows A2P approval and does not gate this phase. (DEC-05)
- [ ] **INFRA-01**: CI runs the full Pest suite on Postgres, and the suite also passes on SQLite locally, so the two cannot drift. (DEC-06)

### Booking and the Gate (Phase 3)

- [ ] **BOOK-01**: Availability is computed from `business/hours.md` (weekly hours, closures, `booking_lead_minutes`), the service's `duration_minutes`, and existing appointments, in the business `timezone` from `business/profile.md`; nothing outside open hours or inside the lead time is offered.
- [ ] **BOOK-02**: The agent texts a booking link for services with `book_online: true`; the customer opens a Livewire page, taps one of the offered times, and gets a confirmed appointment without typing.
- [ ] **BOOK-03**: Appointments are stored as records and appear on the contact's timeline, as the next appointment on the customer card, and on a dashboard schedule screen (DEC-08: inbox, schedule, money).
- [ ] **BOOK-04**: The customer receives reminders before the appointment, sent by the scheduler. *(derived: SPEC says "reminders"; timing and where it is configured are not defined, so decide in discuss-phase)*
- [ ] **BOOK-05**: The owner is alerted (per `owner_alert` in `rules/sending.md`) when an appointment is booked and when a conversation needs a human.
- [ ] **BOOK-06**: The minutes from a contact's first inbound message to their booked appointment are recorded and visible on the contact and in a dashboard summary, so the headline metric can be read. *(derived from Principle 1; not an explicit Sprint 2 SPEC deliverable)*
- [ ] **WALK-01**: Walk-test files (`tests/*.md`, type `walk_test`: persona, channel, script, expect) are validated by the sync with plain-words errors and compiled into the candidate version.
- [ ] **WALK-02**: Every sync walk-tests the candidate version by running each scripted customer in `tests/` against it. Activation happens only if validation and every walk test pass; otherwise the previous version stays live, the message names the test and the check that failed, and the attempt is appended to `changelog.md` and shown on the Business Folder page.
- [ ] **WALK-03**: "Dana end to end": a scripted customer texts in, answers the intake questions, picks a time from the booking link, and ends with a booked appointment, an owner alert, an updated card and records, and a reminder queued. It passes as a walk test and as a feature test.

## v2 Requirements

Pulled by a real client or a job conversation, one at a time (SPEC: Sprint 3 onward). Tracked, not in the committed roadmap. Each group is parked as a Backlog phase (999.x) in ROADMAP.md and promoted with `/gsd-review-backlog` when pulled.

### Quote, Invoice, Paid (Backlog 999.1, Sprint 3)

Each rule below becomes a Pest test before the code exists. All money is integer cents.

- **MONY-01**: A quote can be approved only while draft, sent or viewed; one function approves, whichever door the approval comes through (customer page with signature, or staff without).
- **MONY-02**: Customers approve quotes on a Livewire page reached by a signed link; customer links expire after a configurable number of days and answer 410 Gone.
- **MONY-03**: A deposit invoice is a percent of the approved quote; the final invoice takes it back as its own credit line recording which invoice it came from; an invoice total is recalculated from its own lines, net of credits.
- **MONY-04**: Payments decide whether an invoice is paid or partly paid; staff can set only draft, sent and void by hand.
- **MONY-05**: The next invoice number is the highest existing number plus one, never a count.
- **MONY-06**: Stripe payment links are sent by text; the payment webhook is verified and written inside a transaction, with the provider event id unique so a retry cannot record a payment twice.
- **MONY-07**: An empty tax rate is a validation error, never a silent default; "job", "sent" and "total" each mean one thing, defined once.
- **MONY-08**: The customer card shows the balance, and `records/who-owes-us.md` and `records/this-week.md` are rendered.

### Voice (Backlog 999.2, Sprint 4)

- **VOIC-01**: A vendor voice agent (Vapi, Retell or ElevenLabs, chosen with the first client that needs phones) calls engine tool endpoints.
- **VOIC-02**: Calls, summaries and transcripts land on the customer's timeline; full transcripts sit beside the card in `records/customers/<name>/calls/`.
- **VOIC-03**: Voice vendor webhooks are verified; "they stopped texting, so call" triggers an outbound call.

### Automations (Backlog 999.3, Sprint 5)

- **AUTO-01**: Automation files (`automations/*.md`: trigger, wait, send, stop_when) are validated and compiled.
- **AUTO-02**: Lost-lead check-in at 7 to 10 days with a referral ask.
- **AUTO-03**: Review asks at several points in the job.
- **AUTO-04**: Reactivation sequences for past customers.
- **AUTO-05**: Sequences run as idempotent jobs and stop when their `stop_when` conditions are met.

### Settings by Conversation (Backlog 999.4, Sprint 6)

- **SETT-01**: The owner asks in conversation; an admin agent proposes an edit to the folder as a diff; the owner approves; the normal sync validates, tests and activates it.
- **SETT-02**: The AI never changes live configuration directly; it edits files like everyone else.
- **SETT-03**: The owner can tell the dashboard or the admin agent to change what happened (record a cash payment, say), which writes the database and re-renders the card.

### Connectors (Backlog 999.4, Sprint 6)

- **CONN-01**: Connector files (`connectors/*.md`: system, direction, objects, schedule) are validated; no secrets in files, credentials live encrypted in the engine.
- **CONN-02**: QuickBooks connector.
- **CONN-03**: Google Calendar connector.
- **CONN-04**: Lead emails parsed (Angi, Thumbtack) through inbound mail.
- **CONN-05**: Zapier webhooks.
- **CONN-06**: An MCP server (`laravel/mcp`) answers the owner's AI live questions the files do not cover.

### Unscheduled (no sprint assigned in the SPEC)

- **FOLD-01**: Per-client folder as a git repo the client owns, synced to wherever they and their AI can reach it (Dropbox, Google Drive, local folder). Decide with the first client.
- **FOLD-02**: An editor page in the dashboard for changing folder files.
- **KNOW-01**: A pgvector index for large reference material (a 1,550-item supplier price list, say), rebuilt from the files on sync, never edited directly.
- **DEPL-01**: First deploy: hosting chosen (Laravel Cloud or Forge), production on Postgres, Redis and Horizon for queues.

## Out of Scope

Explicitly excluded. Documented to prevent scope creep.

| Feature | Reason |
|---------|--------|
| AI replies, Twilio, booking, money, voice, automations in Phase 1 | Sprint 0 boundary: the folder and the ways in come first (docs/sprints/sprint-0.md) |
| Platform for shops with few leads | Give them files and folders only (the RyMac model) |
| Replacing a $5M+ contractor's field-service dispatch | Connect to their FSM tool instead; Signpost makes the same call |
| Multi-tenant SaaS | One instance per client (DEC-09); tenancy is an accepted retrofit |
| Building telephony, voice models, payments, accounting | Principle 8: integrate, don't build |
| Stripe Cashier | It bills your own subscribers; Stripe payment links are used instead |
| Storing card numbers | Stripe holds them; a card holds what the owner would tell a staff member |
| Cloning Legacy's code | DEC-12: port its rules and tests, not its code |
| Editing `records/` files by hand | Read-only; the next render overwrites them (DEC-03) |
| Vector retrieval as the default for knowledge | DEC-11: route, don't retrieve; pgvector only for large reference material |
| Inertia with React | Stays available if a client-facing app ever needs it (DEC-08); not planned |
| Autonomous or yolo GSD runs | DEC-01: Ian is learning Laravel and reviews each step |

## Traceability

Which phases cover which requirements. Each v1 requirement maps to exactly one phase.

| Requirement | Phase | Status |
|-------------|-------|--------|
| ICM-01 | Phase 1 | Complete |
| ICM-02 | Phase 1 | Complete |
| LEAD-01 | Phase 1 | Pending |
| LEAD-02 | Phase 1 | Pending |
| LEAD-03 | Phase 1 | Pending |
| LEAD-04 | Phase 1 | Pending |
| RECS-01 | Phase 1 | Pending |
| DASH-01 | Phase 1 | Pending |
| DASH-02 | Phase 1 | Pending |
| QUAL-01 | Phase 1 | Pending |
| AGNT-01 | Phase 2 | Pending |
| AGNT-02 | Phase 2 | Pending |
| AGNT-03 | Phase 2 | Pending |
| AGNT-04 | Phase 2 | Pending |
| GATE-01 | Phase 2 | Pending |
| GATE-02 | Phase 2 | Pending |
| GATE-03 | Phase 2 | Pending |
| GATE-04 | Phase 2 | Pending |
| TXT-01 | Phase 2 | Pending |
| INFRA-01 | Phase 2 | Pending |
| BOOK-01 | Phase 3 | Pending |
| BOOK-02 | Phase 3 | Pending |
| BOOK-03 | Phase 3 | Pending |
| BOOK-04 | Phase 3 | Pending |
| BOOK-05 | Phase 3 | Pending |
| BOOK-06 | Phase 3 | Pending |
| RECS-02 | Phase 3 | Pending |
| WALK-01 | Phase 3 | Pending |
| WALK-02 | Phase 3 | Pending |
| WALK-03 | Phase 3 | Pending |

**Coverage:**
- v1 requirements: 30 total
- Mapped to phases: 30
- Unmapped: 0

---
*Requirements defined: 2026-10-10*
*Last updated: 2026-10-10 after roadmap creation*
