# Requirements: Ops Platform

**Defined:** 2026-10-21 (milestone v2.0 Model Home)
**Core Value:** Finish the customer conversation fast: minutes from first contact to a booked appointment, with the business running from a folder the owner controls and no change going live untested.

Sources: `.planning/PROJECT.md` Key Decisions ("The project is the spine", "The model home is the next milestone", "One owner per fact", and the v2.0 decisions of 2026-10-21), seed SEED-261020-v30, and Ian's scoping answers on 2026-10-21. RECS-02, BOOK-01 to BOOK-06 and WALK-01 to WALK-03 keep their IDs and wording from `.planning/milestones/v1.0-REQUIREMENTS.md`. The v1.0 requirements are archived there.

Affects: customer communication, marketing, sales, operations, finance, team, owner intelligence, knowledge, connectors and data, safety and compliance.

## v2.0 Requirements

Committed scope for v2.0 Model Home. Each maps to exactly one roadmap phase.

### Room Standard

Every room in the floor plan meets these five.

- [x] **ROOM-01**: Each room is a folder in the business folder with a `CONTEXT.md` contract and one fictional example file; a room folder holding only its `CONTEXT.md` (an empty room) passes the sync. Today's files stay where they are, by kind. Each room's `CONTEXT.md` names the files it owns and the ones it reads, and every file has exactly one owner: a room or the house. The house owns the routers (`CLAUDE.md`, `START-HERE.md`, the folder `CONTEXT.md` files outside `rooms/`) and the walk tests in `tests/`. *(Wording from the Phase 4 discuss, 2026-10-22, `04-CONTEXT.md` D-11. Affects: knowledge.)*
- [x] **ROOM-02**: Each room is a Laravel module that registers the room's file types with the sync (validated with plain-words errors like any other file), its tables, its dashboard section and its timeline entries.
- [x] **ROOM-03**: A room that talks to an outside system has a connector slot: an interface with a fake driver (the `TextChannel` pattern), and a connector file declaring `owns` and `reads`. A room with no outside system says so in its `CONTEXT.md`.
- [x] **ROOM-04**: Each room's `CONTEXT.md` states what the room writes to `records/`, which may be nothing; anything it writes is rendered from the database and read-only (DEC-03).
- [x] **ROOM-05**: Each room has one test that boots it with an empty room folder and with its example folder, passing on SQLite and Postgres.

### The Spine

- [x] **PROJ-01**: A Project record ties a piece of work to its contact and conversation, and its appointments hang off it (zero, one or many). Estimates and payments are not part of v2.0; they stay in backlog 999.1.
- [x] **PROJ-02**: `business/profile.md` has one field naming what the business calls a project (Job, Project, Matter), checked by the sync; the dashboard and the customer card use that word. The front desk sees it only because it loads that same card (DEC-03); its code, prompt and rules stay frozen. Card tests that change because a project now appears on the card change in the phase that adds the project, not in the front desk wrap.

### Rooms (the floor plan)

Ten rooms. Every new room meets the room standard and nothing more, except where a requirement below says otherwise. Property and subcontractors are open (Future Requirements).

- [x] **ROOM-06**: Front desk room: today's front desk wrapped in the room standard with no behavior change; the existing suite passes unchanged on both databases. The one allowed test change is the example folder's file count (`exampleFolderFileCount()` in `tests/Pest.php`) when the room's folder joins the example business. Ian also approved (Phase 6 discuss, 2026-10-27, `06-CONTEXT.md` D-14) moving `RoomOwnershipTest`'s three triage-only tests (lines 77, 160 and 185 today) in a prep plan that lands before the front desk wrap. *(Phase 4 discuss, 2026-10-22, `04-CONTEXT.md` D-16. Affects: customer communication.)*
- [x] **ROOM-07**: Leads room, to the room standard. It reads `business/lead-sources.md` and `business/offers/`, which the marketing room owns.
- [x] **LEAD-05**: When a new lead arrives (a text from a new number or a web form), a project opens for that contact. It hooks in beside the inbound path and changes nothing the front desk does; the suite stays green on both databases. What happens when an existing customer starts a second job is settled in the leads phase discuss.
- [x] **ROOM-08**: Company knowledge room, to the room standard.
- [x] **ROOM-09**: Marketing room, to the room standard. It owns `business/lead-sources.md` and `business/offers/`; the leads room and the front desk read them.
- [x] **ROOM-10**: Content generation room, to the room standard, with no connector.
- [x] **ROOM-11**: Google Business Profile room, to the room standard, with its connector slot (interface and fake driver). It owns one file in `rooms/google_business_profile/` holding the primary category and up to 9 additional categories, and reads `business/profile.md`, `business/hours.md`, `business/services/*.md` and `business/offers/*.md`. Its slot is read-only (one method fetches the listing), and its connector file declares `owns: none` and `reads: none`. *(Phase 5 discuss, 2026-10-25, `05-CONTEXT.md` D-06 to D-11. Affects: marketing, knowledge, connectors and data.)*
- [ ] **ROOM-12**: Schedule and booking room, to the room standard; booking (BOOK-01 to BOOK-05) lives here.
- [ ] **ROOM-13**: Owner intelligence room, to the room standard; it carries RECS-02 and BOOK-06.
- [x] **ROOM-14**: Money room, to the full room standard like every room: a module and a boot test, empty and with its example folder. No payment-link slot and no payment records (backlog 999.1).
- [x] **ROOM-15**: Team room: today's owner alerts, approvals and "who acted" wrapped in the room standard with no behavior change; the existing suite passes unchanged on both databases. The one allowed test change is the example folder's file count (`exampleFolderFileCount()` in `tests/Pest.php`) when the room's folder joins the example business. Backlog 999.5 stays unchanged. *(Phase 4 discuss, 2026-10-22, `04-CONTEXT.md` D-16. Affects: team.)*

### Connectors

- [x] **CONN-07**: Connector files declare `owns` and `reads` instead of `objects`. The sync refuses a folder where two connectors own the same kind of record and says so in plain words ("customers is owned by both connectors/a.md and connectors/b.md; pick one, the other can read it"). *(derived from "One owner per fact"; ROOM-03 needs it. CONN-01 stays unchanged in Future Requirements)*

### Booking and the Gate

Carried from v1.0 with their IDs and wording (seed SEED-261020-v30).

- [ ] **BOOK-01**: Availability is computed from `business/hours.md` (weekly hours, closures, `booking_lead_minutes`), the service's `duration_minutes`, and existing appointments, in the business `timezone` from `business/profile.md`; nothing outside open hours or inside the lead time is offered.
- [ ] **BOOK-02**: The agent texts a booking link for services with `book_online: true`; the customer opens a Livewire page, taps one of the offered times, and gets a confirmed appointment without typing.
- [ ] **BOOK-03**: Appointments are stored as records and appear on the contact's timeline, as the next appointment on the customer card, and on a dashboard schedule screen (DEC-08: inbox, schedule, money).
- [ ] **BOOK-04**: The customer receives reminders before the appointment, sent by the scheduler. *(derived: SPEC says "reminders"; timing, send window, which conversation and exactly-once settled in `.planning/phases/08-schedule-and-booking/08-CONTEXT.md` D-26 and D-29 to D-31)*
- [ ] **BOOK-05**: The owner is alerted (per `owner_alert` in `rules/sending.md`) when an appointment is booked and when a conversation needs a human.
- [ ] **BOOK-06**: The minutes from a contact's first inbound message to their booked appointment are recorded and visible on the contact and in a dashboard summary, so the headline metric can be read. *(derived from Principle 1; not an explicit Sprint 2 SPEC deliverable)*
- [ ] **WALK-01**: Walk-test files (`tests/*.md`, type `walk_test`: persona, channel, script, expect) are validated by the sync with plain-words errors and compiled into the candidate version.
- [ ] **WALK-02**: Every sync walk-tests the candidate version by running each scripted customer in `tests/` against it. Activation happens only if validation and every walk test pass; otherwise the previous version stays live, the message names the test and the check that failed, and the attempt is appended to `changelog.md` and shown on the Business Folder page.
- [ ] **WALK-03**: "Dana end to end": a scripted customer texts in, answers the intake questions, picks a time from the booking link, and ends with a booked appointment, an owner alert, an updated card and records, and a reminder queued. It passes as a walk test and as a feature test.

### Records

- [ ] **RECS-02**: `records/today.md` (the schedule) is rendered from appointments on every booking change, and a nightly full rebuild of `records/` is scheduled to catch anything missed. *(derived: SPEC "The bridge" lists today.md and the nightly rebuild; Sprint 0 builds neither, and Sprint 2 introduces the scheduler)*

### Alerts and Export

Their home since 2026-10-21; the backlog 999.5 carry notes point here.

- [x] **ALRT-01**: Each owner alert is sent exactly once, with an atomic send claim and a unique de-duplication key (Phase 2 review WR-03, WR-04). The alert-timing and one-alert rules stand unchanged.
- [x] **ALRT-02**: A front-desk run that fails reports exactly the burst of texts it claimed, using a claim token stored on the claimed rows, not a time window in `ReportFrontDeskFailure` (Phase 2 review WR-22).
- [ ] **GATE-05**: `gate:export` reports only what went out (failed and queued sends are not counted as sent) and gives `original_text` the same way on every path, and it exports the `answered` resolution and the 02.1 fixed-reply kinds (Phase 2 review WR-09 and the 02.1 deferred note).

### Phone Design Pass

The layout is Ian's call at the pass, checked at 375 and 390 wide. Inputs: the pending todo `2026-10-17-phone-design-pass.md`, `docs/phase-2.2-owner-input.md` sections 5 and 6, and PROJECT.md Frontend open items 1 to 8. Doing it in v2.0 reopens the 2026-10-11 Frontend call (Ian, 2026-10-21).

- [ ] **DSGN-01**: On a phone the dashboard is one urgency-ordered list (safety and complaints first); each item shows who, what they asked, how long they have waited, and one clear tap.
- [ ] **DSGN-02**: The Needs you card works on a phone: the todo's items, plus the styling 02.1 D-48 held for the phone walk (UI-REVIEW fix 2).
- [ ] **DSGN-03**: The Business Folder page works on a phone: the todo's items, plus the Offers warning 02.1 D-48 held (UI-REVIEW fix 3).
- [ ] **DSGN-04**: Hold reasons say what is actually held (for example "waiting on the customer's answers for X") instead of "no price on file" (02.1 review IN-02). Words only; no change to what is held, sent or alerted.
- [ ] **DSGN-05**: Every owner-facing screen that exists by the phone design pass works on a phone at 375 and 390 wide, not only the three in DSGN-01 to DSGN-03: each room's dashboard section, the schedule screen and the minutes-to-booked summary. The customer booking page keeps its own UI-SPEC with booking, built phone-first (the part of the 2026-10-11 decision that stands), and the pass re-checks it at 375 and 390.

### Accessibility

Every page the platform serves meets WCAG 2.2 Level AA, fixed in the markup, never by an overlay widget. Automated checks catch only part of WCAG, so customer pages and owner screens also get a keyboard-only and screen reader walk at UAT (PROJECT.md Frontend; Ian, 2026-10-22).

- [x] **A11Y-01**: The room standard includes an automated accessibility check (axe rules, WCAG 2.2 Level AA) on each room's dashboard section, including the D-21 empty line, run with the room's boot test in CI; the pretend room proves it. For the front desk and team rooms the check starts in Phase 11, because 02.1 D-48 holds styling until the phone walk.
- [ ] **A11Y-02**: The customer booking page meets WCAG 2.2 Level AA: it works by keyboard alone and with a screen reader, each time is announced with its day and time, focus moves to the confirmation, errors are text beside the field, tap targets meet the 24-pixel minimum, and it stays usable at 200% zoom. It passes the automated check and a keyboard-only and VoiceOver walk at UAT, and its UI-SPEC carries this.
- [ ] **A11Y-03**: Every owner-facing screen passes the automated check and a keyboard-only and VoiceOver walk at 375 and 390 wide. PROJECT.md Frontend open item 5 is settled here.

### Tour Deploy

- [ ] **DEPL-02**: A staging instance for tours runs the fictional example business on the fake text channel, behind an invite-only login, with no real customer data; hosting (Laravel Cloud or Forge) is chosen in that phase. It comes after the phone design pass. Which other before-first-deploy items a tour needs (T-01-36, T-01-37, WR-08, WR-10 to WR-15, error tracking, backups) and how a visitor tries the front desk (`/dev/chat` is local-only today) are settled in that phase's discuss. The real-client items stay on the before-first-client list: ZDR routes, consent wording, A2P and Twilio go-live, the attorney list.
- [ ] **DEPL-03**: "/" no longer shows the stock Laravel page.
- [ ] **DEPL-04**: The queue worker runs under a supervisor that restarts it.

## Future Requirements

Carried unchanged from the v1.0 "v2 Requirements" sections.

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

- **CONN-01**: Connector files (`connectors/*.md`: system, direction, objects, schedule) are validated; no secrets in files, credentials live encrypted in the engine. The no-secrets scan also covers `rooms/money/` *(Phase 5 discuss, 2026-10-25, `05-CONTEXT.md` D-15. Affects: finance, safety and compliance.)*
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

### Open (no IDs; nothing decided)

- A property room (vehicles, equipment, insurance policies, inventory).
- A subcontractors room.
- A multi-year contract above several projects.
- Regions as a scope for who sees what.
- Per-field ownership (decided when a client needs it, per "One owner per fact").
- A first trade pack.

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

### v2.0 boundaries

| Feature | Reason |
|---------|--------|
| Estimates, quotes, invoices and payments on the project | Stay in backlog 999.1 ("The project is the spine"; Ian, 2026-10-21) |
| A payment-link slot in the money room | MONY-06 is in backlog 999.1 (Ian, 2026-10-21) |
| Owner loop work in the team room | Backlog 999.5 stays unchanged, pulled by the first client with staff |
| New front-desk depth | The text front desk is the finished default for its room |

## Traceability

Which phases cover which requirements. Filled in by the roadmap.

| Requirement | Phase | Status |
|-------------|-------|--------|
| ROOM-01 | Phase 4 | Complete |
| ROOM-02 | Phase 4 | Complete |
| ROOM-03 | Phase 4 | Complete |
| ROOM-04 | Phase 4 | Complete |
| ROOM-05 | Phase 4 | Complete |
| PROJ-01 | Phase 7 | Complete |
| PROJ-02 | Phase 7 | Complete |
| ROOM-06 | Phase 6 | Complete |
| ROOM-07 | Phase 7 | Complete |
| LEAD-05 | Phase 7 | Complete |
| ROOM-08 | Phase 5 | Complete |
| ROOM-09 | Phase 5 | Complete |
| ROOM-10 | Phase 5 | Complete |
| ROOM-11 | Phase 5 | Complete |
| ROOM-12 | Phase 8 | Gaps Found |
| ROOM-13 | Phase 9 | Pending |
| ROOM-14 | Phase 5 | Complete |
| ROOM-15 | Phase 6 | Complete |
| CONN-07 | Phase 4 | Complete |
| BOOK-01 | Phase 8 | Gaps Found |
| BOOK-02 | Phase 8 | Gaps Found |
| BOOK-03 | Phase 8 | Gaps Found |
| BOOK-04 | Phase 8 | Gaps Found |
| BOOK-05 | Phase 8 | Gaps Found |
| BOOK-06 | Phase 9 | Pending |
| WALK-01 | Phase 10 | Pending |
| WALK-02 | Phase 10 | Pending |
| WALK-03 | Phase 10 | Pending |
| RECS-02 | Phase 9 | Pending |
| ALRT-01 | Phase 6 | Complete |
| ALRT-02 | Phase 6 | Complete |
| GATE-05 | Phase 9 | Pending |
| DSGN-01 | Phase 11 | Pending |
| DSGN-02 | Phase 11 | Pending |
| DSGN-03 | Phase 11 | Pending |
| DSGN-04 | Phase 11 | Pending |
| DSGN-05 | Phase 11 | Pending |
| A11Y-01 | Phase 4 | Complete |
| A11Y-02 | Phase 8 | Gaps Found |
| A11Y-03 | Phase 11 | Pending |
| DEPL-02 | Phase 12 | Pending |
| DEPL-03 | Phase 12 | Pending |
| DEPL-04 | Phase 12 | Pending |

**Coverage:**
- v2.0 requirements: 43 total
- Mapped to phases: 43
- Unmapped: 0

---
*Requirements defined: 2026-10-21*
*Last updated: 2026-10-22 after adding accessibility (A11Y-01 to A11Y-03, Ian)*
