# Blueprint: the whole business by area

Written 2026-10-18. This file maps the business the platform runs, area by area, whatever order it gets built in.

It does not replace three other files, and it links to them instead of restating them:

- [SPEC.md](SPEC.md) is the build contract: principles, the two layers, file types, build order.
- [ROADMAP.md](../.planning/ROADMAP.md) is the status: what each phase delivers and where it stands.
- [PROJECT.md](../.planning/PROJECT.md) holds the decisions. [REQUIREMENTS.md](../.planning/REQUIREMENTS.md) holds the requirement IDs.

Only what those files and the owner-input docs ([phase 2](phase-2-owner-input.md), [phase 2.1](phase-2.1-owner-input.md), [phase 2.2](phase-2.2-owner-input.md)) already say is filled in. Everything else says **not yet decided**.

**The rule (CLAUDE.md, Rules):** any decision, plan or idea that changes an area gets an `Affects:` line naming every area it touches, and this file is updated in the same commit.

**Where it stands** uses five words:

- **Built:** a completed phase.
- **Phase:** a phase on the roadmap.
- **Decided:** settled in PROJECT.md, not yet on the roadmap.
- **Backlog:** a 999.x phase, pulled by a real client.
- **Idea:** written down somewhere, not on the roadmap.

## Areas at a glance

| # | Area | Where it stands |
|---|---|---|
| 1 | [Customer communication](#1-customer-communication) | Text built; the front desk room, the project word on the card and booking on the roadmap (Phases 6, 7, 8); owner loop and voice in the backlog |
| 2 | [Marketing](#2-marketing) | Lead sources and offers built; marketing, content generation and Google Business Profile rooms on the roadmap (Phase 5); review asks and reactivation in the backlog; reputation not yet decided |
| 3 | [Sales](#3-sales) | Handoff and owner price sentences built; the leads room opening a project on the roadmap (Phase 7); quotes in the backlog; pipeline stages not yet decided |
| 4 | [Operations](#4-operations) | The project record and booking on the roadmap (Phases 7, 8); dispatch, crews and completion not yet decided |
| 5 | [Finance](#5-finance) | The money room on the roadmap (Phase 5); invoices, payments and books in the backlog; bills and payroll not yet decided |
| 6 | [Team](#6-team) | Owner alerts and approvals built; the team room and alerts sent exactly once on the roadmap (Phase 6); staff users and routing in the backlog |
| 7 | [Owner intelligence](#7-owner-intelligence) | Records files built; the owner intelligence room on the roadmap (Phase 9); briefs and reports partly backlog, partly idea |
| 8 | [Knowledge](#8-knowledge) | The folder, its sync and the room standard built ([Phase 4](../.planning/ROADMAP.md#phase-4-room-standard)); company knowledge and walk tests on the roadmap (Phases 5, 10); settings by conversation in the backlog |
| 9 | [Connectors and data](#9-connectors-and-data) | Text channel, web form, and owns and reads built ([Phase 4](../.planning/ROADMAP.md#phase-4-room-standard)); the tour deploy on the roadmap (Phase 12); the rest in the backlog |
| 10 | [Safety and compliance](#10-safety-and-compliance) | Life-safety, opt-out and disclosure built; accessibility on the roadmap (Phases 4, 8, 11); the tour deploy on the roadmap (Phase 12); A2P and the attorney list open |

**Model roles** across every area ([PROJECT.md, Key Decisions](../.planning/PROJECT.md#key-decisions)):

- Cheap models read and sort.
- Mid-tier models write drafts.
- Code and the owner decide money and safety.
- Frontier models are only for slow back-office work.
- No model reviews outbound texts as a final check.

**The spine** across every area ([PROJECT.md, The project is the spine](../.planning/PROJECT.md#key-decisions)):

- One record carries a piece of work from first contact to paid and warranty: the project. A lead becomes a project; estimates, appointments and payments hang off it, alongside the contact and the conversation.
- Code and docs call it a project. The word people see is the business's own, set by one field in `business/profile.md` (Job, Project, Matter).
- On the roadmap: Phase 7 builds the project record, carrying appointments only in v2.0, and the business's word for it ([Phase 7](../.planning/ROADMAP.md#phase-7-the-spine-and-leads); [REQUIREMENTS](../.planning/REQUIREMENTS.md) PROJ-01, PROJ-02). Estimates and payments stay in [999.1](../.planning/ROADMAP.md#phase-9991-quote-invoice-paid-backlog). Research behind it: [contractor operations](research/contractor-operations-2026-10-19.md).

**The room standard** across every area ([PROJECT.md](../.planning/PROJECT.md#key-decisions), "The room standard and the v2.0 floor plan"; [REQUIREMENTS](../.planning/REQUIREMENTS.md) ROOM-01 to ROOM-05):

- Each room is a folder under `rooms/` with a `CONTEXT.md` contract the sync checks and one fictional example file; an empty room is valid. Today's files stay by kind, and new kinds of file live in their room's folder. Each `CONTEXT.md` names the files the room owns and reads, and every file has exactly one owner: a room or the house (the routers and the walk tests). The ownership table is in [SPEC.md](SPEC.md#who-owns-each-file) ([Phase 4 context](../.planning/phases/04-room-standard/04-CONTEXT.md) D-01 to D-17).
- An absent room folder means the business doesn't use that room: its section is hidden and its module is idle. The front desk, team, company knowledge and marketing can never be left out, nor owner intelligence if the customer card is rendered there ([Phase 4 context](../.planning/phases/04-room-standard/04-CONTEXT.md) D-08, D-09).
- Each room is a Laravel module that registers its file types with the sync, its tables, its dashboard section and its timeline entries.
- An empty room's dashboard section names the files that furnish it ([Phase 4 context](../.planning/phases/04-room-standard/04-CONTEXT.md) D-21).
- Each room's dashboard section, the empty line included, passes an automated accessibility check (axe rules, WCAG 2.2 Level AA) run with the room's boot test in CI; for the front desk and team rooms the check starts in Phase 11 ([REQUIREMENTS](../.planning/REQUIREMENTS.md) A11Y-01; [Phase 4 context](../.planning/phases/04-room-standard/04-CONTEXT.md) D-27).
- A room that talks to an outside system has a connector slot (an interface with a fake driver); the connector file names the slot it fills and declares `owns` and `reads`.
- Each room says what it writes to `records/`, and boots in one test, empty and with its example folder.
- **Decided:** every agent action (a send, a write, a change to a record or the folder) goes through a code chokepoint that refuses it without the checks its kind needs and records which checks passed and who or what approved it (the OutboundSender pattern, 02.1 D-49). A room that lets an agent act declares the agent's decision rights in a rules file the sync checks, as `rules/sending.md` does for the front desk, and a plan that adds an agent action names its chokepoint ([PROJECT.md](../.planning/PROJECT.md#key-decisions), "The chokepoint rule").
- The v2.0 floor plan, by area: front desk (1), marketing, content generation and Google Business Profile (2), leads (3), schedule and booking (4), money (5), team (6), owner intelligence (7), company knowledge (8).
- Open, no area yet: a property room (vehicles, equipment, insurance policies, inventory) and a subcontractors room.

**The build order** ([PROJECT.md, The model home is the next milestone](../.planning/PROJECT.md#key-decisions)):

- The next milestone is the model home: every room built to one standard around the project, a starting point each business furnishes at setup.
- v1 closes at 02.1. Booking and the walk-test gate (the old Phase 3) move into the model home.
- The owner loop (Phase 02.2) is parked in backlog [999.5](../.planning/ROADMAP.md#phase-9995-owner-loop-backlog), pulled by the first client with staff.
- The model home milestone started as v2.0 Model Home on 2026-10-21 ([PROJECT.md, Current Milestone](../.planning/PROJECT.md#current-milestone-v20-model-home)).
- The punch-list rule: UAT findings in a room that already works go to that area's open items here, not into a new decimal phase ahead of the next room; safety and correctness bugs are fixed right away, as a quick task or a decimal phase, and Ian decides which kind a finding is ([PROJECT.md](../.planning/PROJECT.md#key-decisions)).

---

## 1. Customer communication

**What it does for the business.** It answers every customer text quickly. It asks the intake questions, sends what the owner already wrote, and holds anything that needs a person. The customer always hears back and always knows they are talking to an AI. Calls are planned for later.

**Who does each part.**
- **Code:**
  - The send-or-draft rule: the code checks are a hard veto.
  - The owner's fixed replies, with their times computed from `hours.md`.
  - The owner's price and offer sentences.
  - The reply cap.
  - The one outbound path, which refuses an automated text that carries no reading.
- **Agent:** the front desk writes the reply and labels it.
- **Jev:** reads every customer text.
- **Person:** sends or discards drafts, takes over a conversation, and replies from the dashboard.

**Folder files.**
- `agents/front-desk.md`
- `voice/how-we-talk.md`
- `rules/sending.md`, `rules/replies.md`, `rules/triage.md`
- `business/faq.md`, `business/services/*.md`, `business/offers/*.md`
- `business/hours.md`, `business/profile.md`

**Data.**
- **Reads:**
  - contacts, conversations, messages and intake answers;
  - the customer card, rendered fresh from the database.
- **Writes:**
  - messages: sent texts and drafts, each with its stored verdict;
  - intake answers;
  - timeline activities;
  - owner alerts.

**Connectors.**
- Built: the text channel (Twilio, behind `TextChannel`; the fake channel until A2P clears).
- Later: a voice vendor, chosen with the first client that needs phones.
- Model providers: Anthropic, then OpenAI, for the front desk; OpenRouter for Jev.

**Model role.** A mid-tier model writes the reply; the agent file names the model and its fallbacks. Jev, a cheap model, reads the text. Code decides price and safety.

**Where it stands.**
- **Phase:** v2.0 wraps the front desk as a room with no behavior change; the Needs you card gets a phone layout and hold reasons get clearer words, words only ([Phase 6](../.planning/ROADMAP.md#phase-6-front-desk-and-team-rooms), [Phase 11](../.planning/ROADMAP.md#phase-11-phone-design-pass); [REQUIREMENTS](../.planning/REQUIREMENTS.md) ROOM-06, DSGN-02, DSGN-04; [PROJECT.md](../.planning/PROJECT.md#key-decisions)). The front desk owns `rules/sending.md`, `rules/replies.md`, `rules/triage.md` and `agents/front-desk.md` ([Phase 4 context](../.planning/phases/04-room-standard/04-CONTEXT.md) D-12).
- **Phase:** the business's own word for a project shows on the customer card the front desk loads; the front desk's code, prompt and rules stay frozen ([Phase 7](../.planning/ROADMAP.md#phase-7-the-spine-and-leads); [REQUIREMENTS](../.planning/REQUIREMENTS.md) PROJ-02; [PROJECT.md, The project is the spine](../.planning/PROJECT.md#key-decisions)).
- **Built:** [Phase 2](../.planning/milestones/v1.0-ROADMAP.md#phase-2-text-front-desk) and [Phase 02.1](../.planning/milestones/v1.0-ROADMAP.md#phase-021-faster-answers-inserted).
- **Phase:** booking; the booking page meets WCAG 2.2 Level AA and gets a keyboard-only and VoiceOver walk at UAT ([Phase 8](../.planning/ROADMAP.md#phase-8-schedule-and-booking); [REQUIREMENTS](../.planning/REQUIREMENTS.md) A11Y-02).
- **Backlog:**
  - Voice ([999.2](../.planning/ROADMAP.md#phase-9992-voice-backlog)).
  - Assist mode, and the customer update when the wait runs past the holding reply's promise ([999.5](../.planning/ROADMAP.md#phase-9995-owner-loop-backlog)).
- **Idea:** timed reassurance updates while a held question waits ([2.2 input §2](phase-2.2-owner-input.md#2-complaints-a-follow-up-for-a-person-not-a-held-draft)).

**Depends on.** Knowledge, Safety and compliance, Team (who is alerted).

**Feeds.** Sales (every lead), Operations (booking), Team (alerts and drafts), Owner intelligence (the timeline and records).

**Open items affecting this area.**
- Message shape (B): the model's warm reply with the owner's fixed text inside it ([2.2 input §7](phase-2.2-owner-input.md#7-held-from-the-2026-10-17-role-play-decide-when-the-full-workflow-exists)).
- The independent danger check in plain code.
- The WR-04 residual: a spelled-out amount with no money word.

## 2. Marketing

**What it does for the business.** It records where each lead came from and what that lead cost. It answers discount questions with the offers the business is running. Later it asks for reviews and wins back past customers.

**Who does each part.**
- **Code:**
  - First-touch attribution and cost per lead on every contact.
  - Offer sentences, sent by code while live by date.
- **Person:** writes the offers and the lead-source list.
- **Automations:** review asks, reactivation and the lost-lead referral ask are planned as sequences. Who writes their texts is not yet decided.

**Folder files.**
- `business/lead-sources.md`
- `business/offers/*.md`
- `automations/*.md` (later)

**Data.**
- **Reads:** lead sources, live offers.
- **Writes:** the source and cost per lead on the contact.

**Connectors.**
- Built: the web lead form (`source=angi`, for example).
- Later: lead emails parsed (Angi, Thumbtack), Zapier webhooks.

**Model role.** Offers are sent by code, never written by a model. For automations: not yet decided.

**Where it stands.**
- **Phase:** three v2.0 rooms sit in this area, each at the room standard only ([Phase 5](../.planning/ROADMAP.md#phase-5-standard-rooms); [REQUIREMENTS](../.planning/REQUIREMENTS.md) ROOM-09 to ROOM-11; [PROJECT.md](../.planning/PROJECT.md#key-decisions)):
  - marketing, which owns `business/lead-sources.md` and `business/offers/` (leads and the front desk read them);
  - content generation, with no connector; its model role is not yet decided;
  - Google Business Profile, a connector slot with a fake driver; what it owns and reads is not yet decided.
- **Built:** lead sources and attribution ([Phase 1](../.planning/milestones/v1.0-ROADMAP.md#phase-1-foundation)), offers ([Phase 02.1](../.planning/milestones/v1.0-ROADMAP.md#phase-021-faster-answers-inserted)).
- **Backlog:**
  - Review asks, reactivation and the lost-lead check-in with a referral ask ([999.3](../.planning/ROADMAP.md#phase-9993-automations-backlog)).
  - Lead emails and Zapier ([999.4](../.planning/ROADMAP.md#phase-9994-settings-by-conversation-and-connectors-backlog)).
- **Not yet decided:** campaigns beyond offers; reputation (watching and answering reviews).

**Depends on.** Knowledge, Connectors and data, Customer communication (texts go out through it).

**Feeds.** Sales (leads with a source), Owner intelligence (cost per lead).

**Open items affecting this area.** Message shape (B), for review asks and reactivation texts.

## 3. Sales

**What it does for the business.** It turns a lead into a booked job:
- It answers the price question with the owner's own sentence.
- It hands the customer to a person when they ask for one.
- It quotes, and it follows up a lead that went quiet.

**Who does each part.**
- **Code:** picks the owner's price sentence from the customer's intake answers.
- **Agent:**
  - Asks the intake questions.
  - Hands off when the customer asks for a person (`escalate_to`).
  - Holds any price the model writes.
- **Person:** answers held prices, takes over a conversation, and approves quotes.

**Folder files.**
- `business/services/*.md` (intake, `price_sentences`, `price_default`)
- `rules/replies.md` (`price_leads`, `handoff_reply`)
- `agents/front-desk.md` (`escalate_to`)

**Data.**
- **Reads:** intake answers, earlier price texts, and `records/open-leads.md`.
- **Writes:** drafts, intake answers, and quotes later.

**Connectors.** Later: Stripe, for payment links once a quote is approved.

**Model role.** A mid-tier model writes the conversation. Prices are the owner's sentences, sent by code. A price the model writes is held for the owner.

**Where it stands.**
- **Phase:** the leads room opens a project when a new lead arrives, beside the inbound path, with no front-desk change; a second job for an existing customer is settled in the leads phase discuss ([Phase 7](../.planning/ROADMAP.md#phase-7-the-spine-and-leads); [REQUIREMENTS](../.planning/REQUIREMENTS.md) ROOM-07, LEAD-05; [PROJECT.md](../.planning/PROJECT.md#key-decisions)).
- **Decided:** customers and sales activity can stay owned by an outside CRM; the platform shows copies on the project and flags any copy that differs ([PROJECT.md, One owner per fact](../.planning/PROJECT.md#key-decisions)).
- **Decided:** estimates hang off the project ([PROJECT.md, The project is the spine](../.planning/PROJECT.md#key-decisions)). Not scheduled; estimates stay in [999.1](../.planning/ROADMAP.md#phase-9991-quote-invoice-paid-backlog). A lead becoming a project is the Phase 7 line above.
- **Built:**
  - Handoff and take over ([Phase 2](../.planning/milestones/v1.0-ROADMAP.md#phase-2-text-front-desk)).
  - Owner price sentences ([Phase 02.1](../.planning/milestones/v1.0-ROADMAP.md#phase-021-faster-answers-inserted)).
  - The open-leads file ([Phase 1](../.planning/milestones/v1.0-ROADMAP.md#phase-1-foundation)).
- **Backlog:**
  - Quotes and the customer approval page ([999.1](../.planning/ROADMAP.md#phase-9991-quote-invoice-paid-backlog)).
  - The lost-lead check-in ([999.3](../.planning/ROADMAP.md#phase-9993-automations-backlog)).
  - Assist mode and `escalate_to` naming a staff user ([999.5](../.planning/ROADMAP.md#phase-9995-owner-loop-backlog)).
- **Not yet decided:** pipeline stages beyond open leads.

**Depends on.** Customer communication, Knowledge, Marketing (where leads come from).

**Feeds.** Operations (a booked job), Finance (an approved quote), Owner intelligence.

**Open items affecting this area.**
- Message shape (B).
- The WR-04 residual.

## 4. Operations

**What it does for the business.** It books the job, reminds the customer, and puts the day's schedule in front of the owner. Later the front desk can see a customer's job history.

**Who does each part.**
- **Code:**
  - Computes availability from `hours.md`, the service's length and existing appointments.
  - Sends the reminders.
- **Customer:** picks a time.
- **Person:** sees the schedule.

Dispatch, crews, job notes and completion: not yet decided. A $5M+ contractor's dispatch is out of scope; connect to their FSM tool instead ([SPEC](SPEC.md#who-it-is-for)).

**Folder files.**
- `business/hours.md` (weekly hours, closures, `booking_lead_minutes`)
- `business/services/*.md` (`duration_minutes`, `book_online`)
- `business/profile.md` (timezone)

**Data.**
- **Reads:** appointments, hours.
- **Writes:** appointments, reminders, `records/today.md`.

**Connectors.** Later: Google Calendar.

**Model role.** Not yet decided. Availability is computed by code.

**Where it stands.**
- **Phase:** the schedule and booking room holds booking; in v2.0 a project carries its appointments only ([Phase 7](../.planning/ROADMAP.md#phase-7-the-spine-and-leads), [Phase 8](../.planning/ROADMAP.md#phase-8-schedule-and-booking); [REQUIREMENTS](../.planning/REQUIREMENTS.md) ROOM-12, PROJ-01; [PROJECT.md](../.planning/PROJECT.md#key-decisions)).
- **Decided:** the schedule and work orders can stay owned by an outside tool (a spreadsheet, a calendar, a shared drive) until the business moves them in, one kind of record at a time ([PROJECT.md, One owner per fact](../.planning/PROJECT.md#key-decisions)).
- **Phase:** each piece of work is a project, and its appointments hang off it, zero, one or many ([Phase 7](../.planning/ROADMAP.md#phase-7-the-spine-and-leads); [REQUIREMENTS](../.planning/REQUIREMENTS.md) PROJ-01; [PROJECT.md, The project is the spine](../.planning/PROJECT.md#key-decisions)). Research: [contractor operations](research/contractor-operations-2026-10-19.md).
- **Phase:** booking, reminders and the schedule, with a booking page that meets WCAG 2.2 Level AA ([Phase 8](../.planning/ROADMAP.md#phase-8-schedule-and-booking); [REQUIREMENTS](../.planning/REQUIREMENTS.md) A11Y-02); `records/today.md` ([Phase 9](../.planning/ROADMAP.md#phase-9-owner-intelligence)).
  - Booking through a link or with times offered in the thread: settle when booking is discussed in the model home ([2.1 input §2](phase-2.1-owner-input.md#2-scheduling-can-you-come-thursday-morning)).
  - Booking never offers a time that has already passed ([2.2 input §3](phase-2.2-owner-input.md#3-alerts-and-routing)).
- **Idea:** the front desk sees job history ([2.2 input §7](phase-2.2-owner-input.md#7-held-from-the-2026-10-17-role-play-decide-when-the-full-workflow-exists)).
- **Backlog:** Google Calendar ([999.4](../.planning/ROADMAP.md#phase-9994-settings-by-conversation-and-connectors-backlog)).
- **Not yet decided:** dispatch, crews, job notes, completion.

**Depends on.** Sales, Knowledge, Customer communication (reminders go out by text).

**Feeds.** Finance (a finished job), Owner intelligence (the schedule, minutes to booked), Marketing (review asks after a job).

**Open items affecting this area.** Message shape (B), for booking and reminder texts.

## 5. Finance

**What it does for the business.** It bills the job and gets it paid: a deposit and final invoices, a payment link by text, and a balance on the customer card. Later it hands the books to the accounting tool.

**Who does each part.**
- **Code:** money rules ported from Legacy, test-first:
  - A deposit is a percent of the quote.
  - Credits are their own invoice lines.
  - Payments decide whether an invoice is paid.
  - The next invoice number is the highest plus one.
- **Stripe:** holds the card numbers.
- **Person:** can set an invoice to draft, sent or void by hand, and records a cash payment through the dashboard.

**Folder files.** Not yet decided.

**Data.**
- **Reads/writes:** quotes, invoices, payments, the balance.
- **Writes:** `records/who-owes-us.md`, `records/this-week.md`.

**Connectors.** Later: Stripe payment links and webhooks, QuickBooks.

**Model role.** Code and the owner decide money. No model sets an amount.

**Where it stands.**
- **Phase:** the v2.0 money room meets the full room standard (module and boot test, empty and with its example folder) with no payment-link slot and no payment records; estimates and payments stay in 999.1 ([Phase 5](../.planning/ROADMAP.md#phase-5-standard-rooms); [REQUIREMENTS](../.planning/REQUIREMENTS.md) ROOM-14; [PROJECT.md](../.planning/PROJECT.md#key-decisions)). Money's example is one new fictional file in `rooms/money/`, chosen in Phase 5 so it carries no money rules 999.1 would rework ([Phase 4 context](../.planning/phases/04-room-standard/04-CONTEXT.md) D-05).
- **Decided:** invoices, payments and expenses can stay owned by the accounting tool; the project shows copies with their IDs ([PROJECT.md, One owner per fact](../.planning/PROJECT.md#key-decisions)).
- **Decided:** payments hang off the project ([PROJECT.md, The project is the spine](../.planning/PROJECT.md#key-decisions)). Not scheduled; quotes and invoices stay in the backlog.
- **Backlog:**
  - Invoices and payments ([999.1](../.planning/ROADMAP.md#phase-9991-quote-invoice-paid-backlog)).
  - QuickBooks ([999.4](../.planning/ROADMAP.md#phase-9994-settings-by-conversation-and-connectors-backlog)).
- **Not yet decided:** cash flow beyond `this-week.md`, bills, the payroll handoff.

**Depends on.** Sales (an approved quote), Operations (a finished job), Connectors and data.

**Feeds.** Owner intelligence (who owes us, this week's numbers), Customer communication (payment links by text).

**Open items affecting this area.** Message shape (B), for payment link texts.

## 6. Team

**What it does for the business.** The right person hears about the right thing and can act on it from wherever they are:
- who has access;
- who can send drafts;
- who gets which alert, on which channel, and who backs them up.

**Who does each part.**
- **Business owner:** sets the rules and decides who handles what.
- **Team member:** acts on alerts and follow-ups ([2.2 input §1](phase-2.2-owner-input.md#1-who-the-owner-is), a recommendation).
- **Code:**
  - Sends alerts on the folder's timing.
  - Records who acted.

**Folder files.**
- `rules/sending.md` (`owner_alert`: channels, `right_away`, `after_hours`)
- `agents/front-desk.md` (`escalate_to`)
- `rules/replies.md` (`owner_name`)

**Data.**
- **Reads:** users, alert settings.
- **Writes:** owner alerts and who sent, edited or discarded each draft.

**Connectors.** The text channel for alerts. Email: backlog [999.5](../.planning/ROADMAP.md#phase-9995-owner-loop-backlog).

**Model role.** None; alert routing is code.

**Where it stands.**
- **Phase:** the team room wraps today's alerts, approvals and "who acted" with no behavior change; alerts are sent exactly once; the dashboard and every other owner-facing screen get a phone layout and pass the accessibility check and a keyboard-only and VoiceOver walk at 375 and 390 wide; 999.5 stays unchanged ([Phase 6](../.planning/ROADMAP.md#phase-6-front-desk-and-team-rooms), [Phase 11](../.planning/ROADMAP.md#phase-11-phone-design-pass); [REQUIREMENTS](../.planning/REQUIREMENTS.md) ROOM-15, ALRT-01, ALRT-02, DSGN-01, DSGN-05, A11Y-03; [PROJECT.md](../.planning/PROJECT.md#key-decisions)). Team reads `owner_alert` in `rules/sending.md`, which the front desk owns and which stays unsplit ([Phase 4 context](../.planning/phases/04-room-standard/04-CONTEXT.md) D-13).
- **Decided:** work is tracked on a project ([PROJECT.md, The project is the spine](../.planning/PROJECT.md#key-decisions)); who owns each stage of it is not yet decided.
- **Built:** text alerts to the owner, dashboard approvals and the Needs you card ([Phase 2](../.planning/milestones/v1.0-ROADMAP.md#phase-2-text-front-desk)).
- **Backlog:** the owner loop, [999.5](../.planning/ROADMAP.md#phase-9995-owner-loop-backlog):
  - staff users;
  - approve by reply;
  - email alerts and the "handled" follow-up;
  - re-alerts and a backup;
  - complaint routing;
  - the on-call switch.
- **Idea:** the available/unavailable switch ([2.2 input §4](phase-2.2-owner-input.md#4-the-busy-team-member-and-whether-held-drafts-are-needed-at-all)), and showing who sent each message ([§5](phase-2.2-owner-input.md#5-who-sent-it)).

**Depends on.** Knowledge.

**Feeds.** Customer communication (drafts sent, conversations taken over), Owner intelligence.

**Open items affecting this area.** IN-11: with no owner phone on file, a Jev outage adds a failed alert row every 5 minutes.

## 7. Owner intelligence

**What it does for the business.** The owner sees how the business is doing without asking anyone:
- customer cards and summary files their own AI can read;
- the minutes from first contact to a booked appointment;
- later, a briefing.

**Who does each part.**
- **Code:** renders the records from the database.
- **Owner's own AI:** reads them by routing.
- **Person:** reads the dashboard.

**Folder files.** `records/` is written by the engine, read-only and ignored by the sync. It holds `CLAUDE.md`, `customers/INDEX.md` and cards, `open-leads.md`, `today.md`, `who-owes-us.md` and `this-week.md`.

**Data.**
- **Reads:** everything on the timeline.
- **Writes:** `records/` files.
- Built: the send, edit and discard log, exported for tuning (`gate:export`).

**Connectors.** Later: an MCP server for the owner's AI.

**Model role.** Frontier models only for slow back-office work such as this.

**Where it stands.**
- **Phase:** the owner intelligence room carries minutes to booked, `today.md` and the nightly rebuild; `gate:export` reports only what went out ([Phase 9](../.planning/ROADMAP.md#phase-9-owner-intelligence), [Phase 11](../.planning/ROADMAP.md#phase-11-phone-design-pass); [REQUIREMENTS](../.planning/REQUIREMENTS.md) ROOM-13, RECS-02, BOOK-06, GATE-05; [PROJECT.md](../.planning/PROJECT.md#key-decisions)).
- **Decided:** a copy that differs from its owner shows on the card for a person to fix at the owner ([PROJECT.md, One owner per fact](../.planning/PROJECT.md#key-decisions)).
- **Decided:** the project is the record per-piece-of-work reporting will come from ([PROJECT.md, The project is the spine](../.planning/PROJECT.md#key-decisions)); what gets reported is not yet decided.
- **Built:** cards, INDEX and open leads ([Phase 1](../.planning/milestones/v1.0-ROADMAP.md#phase-1-foundation)); `gate:export` ([Phase 2](../.planning/milestones/v1.0-ROADMAP.md#phase-2-text-front-desk)).
- **Phase:** minutes to booked, `today.md` and the nightly rebuild ([Phase 9](../.planning/ROADMAP.md#phase-9-owner-intelligence)).
- **Backlog:**
  - `who-owes-us.md` and `this-week.md` ([999.1](../.planning/ROADMAP.md#phase-9991-quote-invoice-paid-backlog)).
  - The MCP server ([999.4](../.planning/ROADMAP.md#phase-9994-settings-by-conversation-and-connectors-backlog)).
- **Idea:** a daily briefing as an alert channel ([2.2 input §3](phase-2.2-owner-input.md#3-alerts-and-routing)).
- **Not yet decided:** reports beyond these; recorded decisions as a view.

**Depends on.** Every other area's records.

**Feeds.** Knowledge (the owner changes the folder), Team.

## 8. Knowledge

**What it does for the business.** It is how the business runs: services, hours, prices, replies and rules, in plain files the owner controls. A change goes live only after it passes its checks.

**Who does each part.**
- **Code:** the sync. It validates with plain-words errors, versions and activates, and writes `changelog.md`.
- **Person:** the owner, Ian or the owner's own AI edits files.
- **Agent:** later, an admin agent proposes edits as a diff for the owner to approve.
- **The installer:**
  - Ian sets the folder up with the owner at onboarding ([2.1 input](phase-2.1-owner-input.md)).
  - Setup questions are collecting in [2.2 input §1 to §3 and §7](phase-2.2-owner-input.md).
  - What the installer is and how it runs: not yet decided.

**Folder files.** The whole folder ([SPEC, The business folder](SPEC.md#the-business-folder)): `CLAUDE.md`, `START-HERE.md`, `business/`, `voice/`, `rules/`, `agents/`, `automations/`, `connectors/`, `tests/`, and `rooms/` from Phase 4.

**Data.**
- **Reads:** the folder, only at sync.
- **Writes:** compiled versions (`icm_versions`), read only through `ActiveConfig`.

**Connectors.** Where client folders sync from (git, Dropbox, Drive): decide with the first client.

**Model role.** The admin agent's model: not yet decided.

**Where it stands.**
- **Built:** the room standard. Rooms add folders under `rooms/` with a `CONTEXT.md` contract; today's files stay by kind, and every file has exactly one owner, a room or the house. Company knowledge owns `business/profile.md`, `business/hours.md`, `business/services/`, `business/faq.md` and `voice/how-we-talk.md` ([Phase 4 context](../.planning/phases/04-room-standard/04-CONTEXT.md) D-12) ([Phase 4](../.planning/ROADMAP.md#phase-4-room-standard); [REQUIREMENTS](../.planning/REQUIREMENTS.md) ROOM-01 to ROOM-05; [PROJECT.md](../.planning/PROJECT.md#key-decisions)).
- **Phase:** a company knowledge room; the Business Folder page gets a phone layout and passes the accessibility check and a keyboard-only and VoiceOver walk at 375 and 390 wide ([Phase 5](../.planning/ROADMAP.md#phase-5-standard-rooms), [Phase 11](../.planning/ROADMAP.md#phase-11-phone-design-pass); [REQUIREMENTS](../.planning/REQUIREMENTS.md) ROOM-08, DSGN-03, A11Y-03; [PROJECT.md](../.planning/PROJECT.md#key-decisions)).
- **Built:** the sync refuses a folder where two connector files own the same kind of record ([Phase 4](../.planning/ROADMAP.md#phase-4-room-standard); [REQUIREMENTS](../.planning/REQUIREMENTS.md) CONN-07; [PROJECT.md, One owner per fact](../.planning/PROJECT.md#key-decisions)).
- **Phase:** `business/profile.md` gets one field naming what this business calls a project (Job, Project, Matter), checked by the sync ([Phase 7](../.planning/ROADMAP.md#phase-7-the-spine-and-leads); [REQUIREMENTS](../.planning/REQUIREMENTS.md) PROJ-02; [PROJECT.md, The project is the spine](../.planning/PROJECT.md#key-decisions)).
- **Built:** sync, versions, the Business Folder page ([Phase 1](../.planning/milestones/v1.0-ROADMAP.md#phase-1-foundation)).
- **Phase:** walk tests gate every activation ([Phase 10](../.planning/ROADMAP.md#phase-10-walk-tests-and-the-gate)).
- **Backlog:** settings by conversation ([999.4](../.planning/ROADMAP.md#phase-9994-settings-by-conversation-and-connectors-backlog)).
- **Unscheduled:** the folder as a client-owned repo, a dashboard editor page, pgvector for big reference material ([REQUIREMENTS](../.planning/REQUIREMENTS.md), FOLD-01, FOLD-02, KNOW-01).

**Depends on.** Nothing; every other area reads it.

**Feeds.** Every area.

**Open items affecting this area.**
- IN-11: onboarding should require an owner phone.
- G-04-1, for Phase 5 (Ian, 2026-10-23, [Phase 4 UAT](../.planning/phases/04-room-standard/04-UAT.md)): every sync refusal line starts with the raw file path (`app/Icm/ValidationReport.php:45`), so a path containing a line break carries the break into the stored error line. Fixing it needs a decision on where paths get cleaned. Phase 5's roadmap criteria don't name it, so Phase 5's discuss picks it up. Affects: knowledge.
- G-04-2, wording (Ian, 2026-10-23, [Phase 4 UAT](../.planning/phases/04-room-standard/04-UAT.md)): after a killed sync, "A sync is already running. Try again in a moment." shows for up to 2 minutes, until the lock expires, when no sync is running. Reword it so the owner knows to wait. Wording only, under the 2026-10-17 principle: the lock does not change. Affects: knowledge.
- G-04-3, wording (Ian, 2026-10-23, [Phase 4 UAT](../.planning/phases/04-room-standard/04-UAT.md)): a room refusal reads "business/hours.md is a hours file", because the template puts the type name straight after "a" (`app/Icm/RoomChecks.php:273-274`). Reword it so it reads naturally. Wording only, under the 2026-10-17 principle: which files are refused does not change. Affects: knowledge.
- IN-10, test name ([Phase 4 review](../.planning/phases/04-room-standard/04-REVIEW.md)): the order-independence test for a refused room contract is titled "gives the same refusal", but it pins two line orders on purpose. Rename it to say what it checks. Affects: knowledge.
- The CI runner pin: see [Safety and compliance](#10-safety-and-compliance).

## 9. Connectors and data

**What it does for the business.** The platform works with what the business already runs. It pulls leads, payments and calls in, writes records back out, and keeps one timeline per customer.

**Who does each part.**
- **Code:**
  - Verifies each webhook.
  - Writes it inside a transaction, with the provider's event id unique.
  - Re-renders the card.
- **Person:** connects accounts. Credentials are stored encrypted in the engine, never in files.

**Folder files.** `connectors/*.md` (system, direction, owns, reads, schedule).

**Data.**
- **Pulls:**
  - Built: texts and web forms.
  - Later: lead emails, Zapier, Stripe payments, voice calls and transcripts.
  - What QuickBooks and Google Calendar pull: not yet decided.
- **Writes back:**
  - Built: `records/` files.
  - Later: the MCP server.
  - What goes back to QuickBooks and Google Calendar: not yet decided.

**Connectors.**
- Built: Twilio (fake until A2P), the web form.
- Later: Stripe, QuickBooks, Google Calendar, Angi and Thumbtack lead emails, Zapier, a voice vendor, MCP.
- Outside model providers today: OpenRouter (Jev), Anthropic and OpenAI (the front desk).

**Model role.** None.

**Where it stands.**
- **Built:** connector files declare `owns` and `reads`, and the sync refuses two owners for one kind of record; a room gets a connector slot only when it talks to an outside system ([Phase 4](../.planning/ROADMAP.md#phase-4-room-standard); [REQUIREMENTS](../.planning/REQUIREMENTS.md) CONN-07, ROOM-03; [PROJECT.md](../.planning/PROJECT.md#key-decisions)).
  - Phase 4 checks `owns`, `reads` and `slot`. A connector may own only kinds on one fixed list (customers, sales activity, invoices, payments, expenses, work orders, the schedule, documents). Projects, texts and the timeline are on a second list: the platform owns them and a connector may only read them. Both lists are in SPEC beside the ownership table: [kinds a connector may own](SPEC.md#kinds-of-record-a-connector-may-own) and [kinds the platform owns](SPEC.md#kinds-of-record-the-platform-owns), tied to the code by a test (D-14). A connector file lives only in `connectors/`; the sync refuses `type: connector` anywhere else (D-12, D-19; Ian confirmed 2026-10-23, [Phase 4 UAT](../.planning/phases/04-room-standard/04-UAT.md)). `system`, `direction` and `schedule` wait for CONN-01 ([Phase 4 context](../.planning/phases/04-room-standard/04-CONTEXT.md) D-22 to D-25).
- **Built:** one owner per fact. Each kind of record has one owner system named in its connector file (`owns`), every other system holds a copy (`reads`), and moving an owner is a two-file change checked by the sync ([Phase 4](../.planning/ROADMAP.md#phase-4-room-standard); [REQUIREMENTS](../.planning/REQUIREMENTS.md) CONN-07; [PROJECT.md, One owner per fact](../.planning/PROJECT.md#key-decisions)).
- **Phase:** the Google Business Profile room is the first real room with a connector slot, on its fake driver only; the tour deploy picks hosting ([Phase 5](../.planning/ROADMAP.md#phase-5-standard-rooms), [Phase 12](../.planning/ROADMAP.md#phase-12-tour-deploy); [REQUIREMENTS](../.planning/REQUIREMENTS.md) DEPL-02; [PROJECT.md](../.planning/PROJECT.md#key-decisions)).
- **Decided:** each contact and project keeps its ID in each system ([PROJECT.md, One owner per fact](../.planning/PROJECT.md#key-decisions)).
- **Built:** [Phase 1](../.planning/milestones/v1.0-ROADMAP.md#phase-1-foundation), [Phase 2](../.planning/milestones/v1.0-ROADMAP.md#phase-2-text-front-desk).
- **Backlog:** [999.1](../.planning/ROADMAP.md#phase-9991-quote-invoice-paid-backlog), [999.2](../.planning/ROADMAP.md#phase-9992-voice-backlog), [999.4](../.planning/ROADMAP.md#phase-9994-settings-by-conversation-and-connectors-backlog).

**Depends on.** Safety and compliance (ZDR routes, credentials).

**Feeds.** Marketing, Sales, Operations, Finance, Owner intelligence.

**Open items affecting this area.**
- IN-09, test coverage ([Phase 4 review](../.planning/phases/04-room-standard/04-REVIEW.md)): in the order-independence test, the connector arrangements "written first" and "written second" are the same input, because the sync reads files in name order (`app/Icm/Loader.php:77`); only the content swap changes what the checks see. Drop the duplicates, or add a comment saying why they stay. Affects: connectors and data.

## 10. Safety and compliance

**What it does for the business.** It keeps customers safe and the business legal:
- the 911 line for danger;
- honoring STOP;
- saying it is an AI;
- registering to text;
- the terms an attorney checks before a real client.

**Who does each part.**
- **Code:**
  - Life-safety keywords and the 911 line, sent before any other reply, worded in code with the owner's utility phone added.
  - STOP, START and HELP.
  - The AI disclosure on the first text.
  - Jev outage mode.
- **Jev and the agent:** two danger nets that depend on outside providers (Jev's reading, the agent's `possible_danger`).
- **Person:** Ian registers A2P; an attorney reviews the terms.

**Folder files.**
- `voice/how-we-talk.md` (`ai_disclosure`, `never_say`)
- `rules/replies.md` (`utility_phone`)
- `rules/triage.md` (Jev's questions)

**Data.**
- **Reads:** each customer text, with only the text sent to Jev.
- **Writes:** opt-out on the contact, the reading on each message, safety-check outages.

**Connectors.** OpenRouter (Jev). Twilio (A2P).

**Model role.** A cheap model reads (Jev) beside the keyword checks; code decides. No model reviews outbound texts as a final check.

**Where it stands.**
- **Phase:** the tour deploy runs the fictional example business on the fake text channel behind an invite-only login, with no real customer data; ZDR routes, consent wording, A2P and Twilio go-live and the attorney list stay before the first client ([Phase 12](../.planning/ROADMAP.md#phase-12-tour-deploy); [REQUIREMENTS](../.planning/REQUIREMENTS.md) DEPL-02; [PROJECT.md](../.planning/PROJECT.md#key-decisions)).
- **Phase:** every page the platform serves meets WCAG 2.2 Level AA, fixed in the markup, never by an overlay widget, and every image carries alt text. Automated checks catch only part of WCAG, so customer pages and owner screens also get a keyboard-only and screen reader walk at UAT ([Phase 4](../.planning/ROADMAP.md#phase-4-room-standard), [Phase 8](../.planning/ROADMAP.md#phase-8-schedule-and-booking), [Phase 11](../.planning/ROADMAP.md#phase-11-phone-design-pass); [REQUIREMENTS](../.planning/REQUIREMENTS.md) A11Y-01 to A11Y-03; [PROJECT.md, Frontend](../.planning/PROJECT.md#frontend)).
- **Built:** life-safety, opt-out and disclosure ([Phase 2](../.planning/milestones/v1.0-ROADMAP.md#phase-2-text-front-desk)); Jev on every text and outage mode ([Phase 02.1](../.planning/milestones/v1.0-ROADMAP.md#phase-021-faster-answers-inserted)).
- **Backlog:** the fallback classifier on a ZDR route ([999.5](../.planning/ROADMAP.md#phase-9995-owner-loop-backlog)).
- **Open:**
  - Which entity registers A2P ([PROJECT, open decisions](../.planning/PROJECT.md#key-decisions)).
  - A ZDR route for every provider before a real client (Phase 2 D-37).
- **The attorney list, before a real client:**
  - the client agreement's safety and liability terms ([STATE](../.planning/STATE.md));
  - where the AI notice sits, if message shape (B) is adopted;
  - timed updates and overnight texts (recommendation, [2.2 input §2](phase-2.2-owner-input.md#2-complaints-a-follow-up-for-a-person-not-a-held-draft));
  - an accessibility statement, with a way for a customer to report a barrier;
  - who answers for accessibility in the client agreement;
  - a privacy notice on the booking page;
  - the hours booking reminders may go out.

**Depends on.** Knowledge, Connectors and data.

**Feeds.** Customer communication (what may go out), Team (safety alerts).

**Open items affecting this area.**
- The independent danger check in plain code.
- Jev tuning.
- Message shape (B) (notice placement).
- IN-11 (outage alerts with no owner phone).
- The CI runner pin (Ian, 2026-10-23, Phase 4 transition): CI runs on `ubuntu-latest` (`.github/workflows/tests.yml:13`), which includes the browser accessibility check (Playwright's Chromium, installed with its system packages). Pin the runner to `ubuntu-24.04`, or re-check the run after GitHub moves `ubuntu-latest` to Ubuntu 26. Affects: safety and compliance, knowledge.

---

## Ideas inbox

Ian's raw ideas, newest first. Each idea gets an `Affects:` line (Claude fills it in if it's left off). When an idea becomes a decision or a plan, move it to where it belongs and update the areas above in the same commit.

- **Service history by address** (Ian, 2026-10-22, from a Hiller visit on ServiceTitan): a 17-year customer's history did not come up by name or address. For the Phase 7 discuss: whether history hangs off the person, the service address or both (in HVAC the equipment stays with the house); addresses normalized; automatic matches only on phone or email; an address match under a different name asks a person on the card rather than merging or creating a duplicate. Affects: sales, operations, customer communication, owner intelligence.
- **Security layers for each instance** (Ian, 2026-10-22, after PHP2Shell; sketched by Cowork, not decided): patch the PHP runtime within a day (managed hosting or a set routine); composer audit and Dependabot in CI; an edge firewall such as Cloudflare in front of each instance; only the booking page, lead endpoint and Twilio webhook public, with /admin behind multi-factor login; PHP features the app does not use switched off; per-client API keys with spend limits that can be rotated fast; a least-privilege database user; tested offsite backups; alerts on spikes in server and out-of-memory errors. Most of it settles in the Phase 12 discuss; composer audit and Dependabot can be a quick task. Affects: safety and compliance, connectors and data, owner intelligence.
- **On my way text and technician tracking** (Ian, 2026-10-22, seen from Hiller on ServiceTitan): first, a button on an appointment that sends the customer an owner-written "on the way" text through the text channel and the sender, recording who tapped it (Housecall Pro's On My Way works this way). Later, a live tracking link: the technician's phone shares location on the way, a signed public page with the technician's name and an ETA that stops at arrival, a map provider, and the A11Y-02 accessibility bar. Needs appointments (Phase 8); a technician who isn't the owner needs a staff login (999.5). A business whose field tool already sends these keeps it (one owner per fact). Affects: operations, customer communication, team, safety and compliance. The link works like ServiceTitan's: signed, carrying only the appointment and technician IDs, accepted only by the tracking page, and expiring; ours ends at arrival rather than after days, and reuses the signed-link mechanism of the Phase 8 booking link (Laravel temporary signed URLs).
- **Open-weight models the business owns** (Ian, 2026-10-22): open-weight models are close enough to the frontier that owning the weights, the deployment and the terms beats renting a model whose version, pricing and content policy can change without asking. Any agent file can name an open-weight model running on the business's own hardware or a private rented GPU (DEC-07), so no customer data leaves the business. The model works from the folder like every agent (DEC-10, DEC-11): a file edit still changes the business, the sync can check it, and a customer's data can still be deleted on request. Later and optional: tuning the readers and the drafting voice on the business's decision history (drafts the owner sent, edited or discarded; Jev readings and outcomes), each checked by an eval before use. Affects: connectors and data, safety and compliance, knowledge, customer communication, owner intelligence.
- **Compliance as a sync check** (Ian, 2026-10-22; sketch): a provider register (zero data retention, business associate agreement, date, who verified) and a compliance profile per business. The sync refuses an agent file naming a provider without the agreement the profile requires, and refuses outside-AI connections. A person attests; the sync enforces. Extends Phase 2 D-37; health businesses such as med-spas first. Affects: safety and compliance, connectors and data, knowledge, customer communication.
- **One door for outside AI** (Ian, 2026-10-22; sketch): staff and existing agents reach company data only through the platform's MCP server (CONN-06, 999.4): OAuth sign-in with a consent screen, read-only by default, rendered records rather than raw tables, tools shown per person, an owner-picked list of allowed AI apps in a folder file, every connection and call on the timeline, revocable tokens. Scopes reuse the Phase 4 kind lists. Affects: connectors and data, safety and compliance, knowledge, team, owner intelligence.
- **The AI operating system** (Ian, 2026-10-22; mechanism sketched by Cowork, not decided): the platform is the system agents run inside. At any time it answers five questions for every agent: what is running, what data it can reach, what it decides without a person, how its output is checked, who is accountable. The answers come only from what the code already enforces (agent files, rules files, connector owns and reads, chokepoint records), shown in one register: a dashboard section plus a records file the owner's own AI can read. No AI supervises other AIs (model roles, 2026-10-17). Works for any business, keeps the tools a business already pays for (one owner per fact), and covers agents the platform does not run. Affects: all ten areas.
- **Who patches PHP** (2026-10-22, PHP2Shell): the Phase 12 hosting choice weighs who patches the PHP runtime and how fast; CVE-2026-109618 and CVE-2026-109619 needed PHP 8.5.12 within a day of disclosure. Affects: connectors and data, safety and compliance.
