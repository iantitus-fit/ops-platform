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
| 1 | [Customer communication](#1-customer-communication) | Text built; booking on the roadmap; owner loop and voice in the backlog |
| 2 | [Marketing](#2-marketing) | Lead sources and offers built; review asks and reactivation in the backlog; reputation not yet decided |
| 3 | [Sales](#3-sales) | Handoff and owner price sentences built; quotes in the backlog; pipeline stages not yet decided |
| 4 | [Operations](#4-operations) | Booking on the roadmap; the project record decided; dispatch, crews and completion not yet decided |
| 5 | [Finance](#5-finance) | Invoices, payments and books in the backlog; bills and payroll not yet decided |
| 6 | [Team](#6-team) | Owner alerts and approvals built; staff users and routing in the backlog |
| 7 | [Owner intelligence](#7-owner-intelligence) | Records files built; briefs and reports partly backlog, partly idea |
| 8 | [Knowledge](#8-knowledge) | The folder and its sync built; walk tests on the roadmap; settings by conversation in the backlog |
| 9 | [Connectors and data](#9-connectors-and-data) | Text channel and web form built; the rest in the backlog |
| 10 | [Safety and compliance](#10-safety-and-compliance) | Life-safety, opt-out and disclosure built; A2P and the attorney list open |

**Model roles** across every area ([PROJECT.md, Key Decisions](../.planning/PROJECT.md#key-decisions)):

- Cheap models read and sort.
- Mid-tier models write drafts.
- Code and the owner decide money and safety.
- Frontier models are only for slow back-office work.
- No model reviews outbound texts as a final check.

**The spine** across every area ([PROJECT.md, The project is the spine](../.planning/PROJECT.md#key-decisions)):

- One record carries a piece of work from first contact to paid and warranty: the project. A lead becomes a project; estimates, appointments and payments hang off it, alongside the contact and the conversation.
- Code and docs call it a project. The word people see is the business's own, set by one field in `business/profile.md` (Job, Project, Matter).
- Decided, not built. Research behind it: [contractor operations](research/contractor-operations-2026-10-19.md).

**The build order** ([PROJECT.md, The model home is the next milestone](../.planning/PROJECT.md#key-decisions)):

- The next milestone is the model home: every room built to one standard around the project, a starting point each business furnishes at setup.
- The owner loop (Phase 02.2) is parked in backlog [999.5](../.planning/ROADMAP.md#phase-9995-owner-loop-backlog), pulled by the first client with staff.

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
- **Decided:** the front desk calls a piece of work by the business's own word for a project ([PROJECT.md, The project is the spine](../.planning/PROJECT.md#key-decisions)).
- **Built:** [Phase 2](../.planning/ROADMAP.md#phase-2-text-front-desk) and [Phase 02.1](../.planning/ROADMAP.md#phase-021-faster-answers-inserted).
- **Phase:** booking ([3](../.planning/ROADMAP.md#phase-3-booking-and-the-gate)).
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
- **Built:** lead sources and attribution ([Phase 1](../.planning/ROADMAP.md#phase-1-foundation)), offers ([Phase 02.1](../.planning/ROADMAP.md#phase-021-faster-answers-inserted)).
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
- **Decided:** a lead becomes a project, and estimates hang off it ([PROJECT.md, The project is the spine](../.planning/PROJECT.md#key-decisions)).
- **Built:**
  - Handoff and take over ([Phase 2](../.planning/ROADMAP.md#phase-2-text-front-desk)).
  - Owner price sentences ([Phase 02.1](../.planning/ROADMAP.md#phase-021-faster-answers-inserted)).
  - The open-leads file ([Phase 1](../.planning/ROADMAP.md#phase-1-foundation)).
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
- **Decided:** each piece of work is a project, and its appointments hang off it, one or many ([PROJECT.md, The project is the spine](../.planning/PROJECT.md#key-decisions)). Research: [contractor operations](research/contractor-operations-2026-10-19.md).
- **Phase:** booking, reminders, the schedule and `records/today.md` ([3](../.planning/ROADMAP.md#phase-3-booking-and-the-gate)).
  - Booking through a link or with times offered in the thread: settle in Phase 3 discuss ([2.1 input §2](phase-2.1-owner-input.md#2-scheduling-can-you-come-thursday-morning)).
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
- **Decided:** work is tracked on a project ([PROJECT.md, The project is the spine](../.planning/PROJECT.md#key-decisions)); who owns each stage of it is not yet decided.
- **Built:** text alerts to the owner, dashboard approvals and the Needs you card ([Phase 2](../.planning/ROADMAP.md#phase-2-text-front-desk)).
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
- **Decided:** the project is the record per-piece-of-work reporting will come from ([PROJECT.md, The project is the spine](../.planning/PROJECT.md#key-decisions)); what gets reported is not yet decided.
- **Built:** cards, INDEX and open leads ([Phase 1](../.planning/ROADMAP.md#phase-1-foundation)); `gate:export` ([Phase 2](../.planning/ROADMAP.md#phase-2-text-front-desk)).
- **Phase:** minutes to booked, `today.md` and the nightly rebuild ([3](../.planning/ROADMAP.md#phase-3-booking-and-the-gate)).
- **Backlog:**
  - `gate:export` fixes ([999.5](../.planning/ROADMAP.md#phase-9995-owner-loop-backlog)).
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

**Folder files.** The whole folder ([SPEC, The business folder](SPEC.md#the-business-folder)): `CLAUDE.md`, `START-HERE.md`, `business/`, `voice/`, `rules/`, `agents/`, `automations/`, `connectors/`, `tests/`.

**Data.**
- **Reads:** the folder, only at sync.
- **Writes:** compiled versions (`icm_versions`), read only through `ActiveConfig`.

**Connectors.** Where client folders sync from (git, Dropbox, Drive): decide with the first client.

**Model role.** The admin agent's model: not yet decided.

**Where it stands.**
- **Decided:** `business/profile.md` gets one field naming what this business calls a project (Job, Project, Matter), checked by the sync ([PROJECT.md, The project is the spine](../.planning/PROJECT.md#key-decisions)). Not built.
- **Built:** sync, versions, the Business Folder page ([Phase 1](../.planning/ROADMAP.md#phase-1-foundation)).
- **Phase:** walk tests gate every activation ([3](../.planning/ROADMAP.md#phase-3-booking-and-the-gate)).
- **Backlog:** settings by conversation ([999.4](../.planning/ROADMAP.md#phase-9994-settings-by-conversation-and-connectors-backlog)).
- **Unscheduled:** the folder as a client-owned repo, a dashboard editor page, pgvector for big reference material ([REQUIREMENTS](../.planning/REQUIREMENTS.md), FOLD-01, FOLD-02, KNOW-01).

**Depends on.** Nothing; every other area reads it.

**Feeds.** Every area.

**Open items affecting this area.** IN-11: onboarding should require an owner phone.

## 9. Connectors and data

**What it does for the business.** The platform works with what the business already runs. It pulls leads, payments and calls in, writes records back out, and keeps one timeline per customer.

**Who does each part.**
- **Code:**
  - Verifies each webhook.
  - Writes it inside a transaction, with the provider's event id unique.
  - Re-renders the card.
- **Person:** connects accounts. Credentials are stored encrypted in the engine, never in files.

**Folder files.** `connectors/*.md` (system, direction, objects, schedule).

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
- **Built:** [Phase 1](../.planning/ROADMAP.md#phase-1-foundation), [Phase 2](../.planning/ROADMAP.md#phase-2-text-front-desk).
- **Backlog:** [999.1](../.planning/ROADMAP.md#phase-9991-quote-invoice-paid-backlog), [999.2](../.planning/ROADMAP.md#phase-9992-voice-backlog), [999.4](../.planning/ROADMAP.md#phase-9994-settings-by-conversation-and-connectors-backlog).

**Depends on.** Safety and compliance (ZDR routes, credentials).

**Feeds.** Marketing, Sales, Operations, Finance, Owner intelligence.

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
- **Built:** life-safety, opt-out and disclosure ([Phase 2](../.planning/ROADMAP.md#phase-2-text-front-desk)); Jev on every text and outage mode ([Phase 02.1](../.planning/ROADMAP.md#phase-021-faster-answers-inserted)).
- **Backlog:** the fallback classifier on a ZDR route ([999.5](../.planning/ROADMAP.md#phase-9995-owner-loop-backlog)).
- **Open:**
  - Which entity registers A2P ([PROJECT, open decisions](../.planning/PROJECT.md#key-decisions)).
  - A ZDR route for every provider before a real client (Phase 2 D-37).
- **The attorney list, before a real client:**
  - the client agreement's safety and liability terms ([STATE](../.planning/STATE.md));
  - where the AI notice sits, if message shape (B) is adopted;
  - timed updates and overnight texts (recommendation, [2.2 input §2](phase-2.2-owner-input.md#2-complaints-a-follow-up-for-a-person-not-a-held-draft)).

**Depends on.** Knowledge, Connectors and data.

**Feeds.** Customer communication (what may go out), Team (safety alerts).

**Open items affecting this area.**
- The independent danger check in plain code.
- Jev tuning.
- Message shape (B) (notice placement).
- IN-11 (outage alerts with no owner phone).

---

## Ideas inbox

Ian's raw ideas, newest first. Each idea gets an `Affects:` line (Claude fills it in if it's left off). When an idea becomes a decision or a plan, move it to where it belongs and update the areas above in the same commit.

- *(empty)*
