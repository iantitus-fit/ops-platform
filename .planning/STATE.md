---
gsd_state_version: "1.0"
current_phase: 02
current_phase_name: Text Front Desk
status: executing
stopped_at: Completed 02-30-PLAN.md
last_updated: "2026-10-12T17:33:05.027Z"
last_activity: 2026-10-12
last_activity_desc: Phase 02 execution started
state_head: f2eca7a99d46d9af2f270a9463b259f33f9430b9
progress:
  total_phases: 4
  completed_phases: 1
  total_plans: 41
  completed_plans: 37
  percent: 25
---

# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-10-11)

**Core value:** Finish the customer conversation fast: minutes from first contact to a booked appointment, with the business running from a folder the owner controls and no change going live untested.
**Current focus:** Phase 02 — Text Front Desk

## Current Position

Phase: 02 (Text Front Desk) — EXECUTING
Plan: 2 of 34
Status: Ready to execute
Last activity: 2026-10-12 — Phase 02 execution started

Progress: [███░░░░░░░] 25%

## Performance Metrics

**Velocity:**
- Total plans completed: 7
- Average duration: - min
- Total execution time: 0.0 hours

**By Phase:**

| Phase | Plans | Total | Avg/Plan |
|-------|-------|-------|----------|
| 01 | 7 | - | - |

**Recent Trend:**
- Last 5 plans: -
- Trend: -

*Updated after each plan completion*
**Per-Plan Metrics:**

| Plan | Duration | Tasks | Files |
|------|----------|-------|-------|
| Phase 01 P01 | 8 min | 3 tasks | 52 files |
| Phase 01 P02 | 8 min | 3 tasks | 11 files |
| Phase 01 P03 | 10 min | 3 tasks | 41 files |
| Phase 01 P04 | 5 min | 3 tasks | 10 files |
| Phase 01 P05 | 12 min | 3 tasks | 15 files |
| Phase 01 P06 | 8 min | 3 tasks | 22 files |
| Phase 01 P07 | 10 min | 3 tasks | 17 files |
| Phase 02 P01 | 4 min | 2 tasks | 12 files |
| Phase 02 P02 | 5 min | 2 tasks | 16 files |
| Phase 02 P08 | 6 min | 1 tasks | 10 files |
| Phase 02 P03 | 14 min | 1 tasks | 12 files |
| Phase 02 P09 | 14 min | 1 tasks | 9 files |
| Phase 02 P04 | 15 min | 1 tasks | 12 files |
| Phase 02 P05 | 12 min | 1 tasks | 6 files |
| Phase 02 P06 | 25 min | 1 tasks | 15 files |
| Phase 02 P10 | 3 min | 1 tasks | 12 files |
| Phase 02 P07 | 6 min | 2 tasks | 12 files |
| Phase 02 P11 | 15 min | 1 tasks | 8 files |
| Phase 02 P12 | 25 min | 1 tasks | 8 files |
| Phase 02 P13 | 6 min | 3 tasks | 13 files |
| Phase 02 P14 | 6 min | 1 tasks | 14 files |
| Phase 02 P15 | 10 min | 2 tasks | 13 files |
| Phase 02 P16 | 14 min | 1 tasks | 7 files |
| Phase 02 P18 | 6 min | 2 tasks | 15 files |
| Phase 02 P17 | 14 min | 2 tasks | 14 files |
| Phase 02 P19 | 12 min | 1 tasks | 13 files |
| Phase 02 P20 | 25 min | 2 tasks | 13 files |
| Phase 02 P21 | 30 min | 2 tasks | 10 files |
| Phase 02 P22 | 40 min | 1 tasks | 10 files |
| Phase 02 P23 | 25 min | 2 tasks | 12 files |
| Phase 02 P24 | 20 min | 1 tasks | 12 files |
| Phase 02 P25 | 7 min | 2 tasks | 13 files |
| Phase 02 P26 | 3 min | 1 tasks | 13 files |
| Phase 02 P27 | 13 min | 2 tasks | 8 files |
| Phase 02 P28 | 10 min | 2 tasks | 4 files |
| Phase 02 P29 | 30min | 3 tasks | 11 files |
| Phase 02 P30 | 20 min | 2 tasks | 4 files |

## Accumulated Context

### Decisions

Full log in PROJECT.md Key Decisions (DEC-01 to DEC-14 locked; do not reopen). Most relevant to current work:

- [DEC-01]: One branch per phase that Ian merges; TDD on; test command `php artisan test`; no worktrees; no autonomous or yolo runs
- [DEC-10]: Engine reads configuration only from the active compiled version via `App\Icm\ActiveConfig`; the sync is the only thing that reads the folder
- [DEC-03]: Records are written to the database first; `records/` cards are rendered from the database and are read-only
- [DEC-06]: SQLite in Sprint 0; Postgres in CI from Sprint 1 (Phase 2)
- [DEC-05]: Fake text channel; the build does not wait on A2P
- [Phase 01]: [01-01] vN version label is the icm_versions row id; router files (CLAUDE.md, CONTEXT.md, START-HERE.md) are fingerprinted so editing one mints a version
- [Phase 01]: [01-01] ActiveConfig singleton accessors assume business, hours, lead_sources, voice and sending_rules each exist once; plan 01-02 validator must enforce it
- [Phase 01]: [01-02] A single-instance type (business, hours, lead sources, voice, sending rules) is owned by the file at its usual path; any other file with that type gets the already-used-by error
- [Phase 01]: [01-02] Echoed owner values are cut to 80 characters total with line breaks collapsed (PlainWords::oneLine), so changelog.md stays one bullet per error
- [Phase 01]: [01-02] Each rejected sync attempt stores its own rejected icm_versions row; the Business Folder page (01-07) should show only the newest
- [Phase 01]: [01-03] A rejected fake-webhook post (missing or unreadable From) answers a JSON 422 via FormRequest failedValidation(); the default for a caller that does not ask for JSON is a 302 redirect
- [Phase 01]: [01-03] TextChannel is bound with bind() so each resolution reads channels.text.driver; FakeTextChannel stays a singleton so sent() persists; plan 01-04 must add leads to the forgery exemption in bootstrap/app.php
- [Phase 01]: [01-03] Contact creation is not wrapped in a savepoint (only the open conversation is); two concurrent first texts from one new number give the second a 500 and the provider retry succeeds; revisit when Twilio goes live in Phase 2
- [Phase 01]: [01-04] A rejected lead answers a JSON 422 for any caller via failedValidation() in StoreLeadRequest; the default for a plain form post is a 302 redirect
- [Phase 01]: [01-04] Note activities are written after the message so each carries the message as subject and the touch's occurred_at; a known cost of 0 (referral) is stored as 0 and null means unknown
- [Phase 01]: [01-04] Filling an empty email does not check whether another contact already owns it (accepted in the plan's flagged assumptions); FA-1 form field names still to confirm with Ian
- [Phase 01]: [01-05] The summaries (INDEX.md, open-leads.md) are rewritten after every card render, including a deleted contact's, inside Cache::lock('records:summaries') with the database read inside the lock
- [Phase 01]: [01-05] open-leads.md shows the phone in the Name column for a lead with no name and the raw source key in the Source column; INDEX.md shows (no name) because it has a Phone column
- [Phase 01]: [01-05] Task 2 tests were characterization tests (Task 1 GREEN already covered the behavior); mutation checks stand in for RED. Reply labeling on the card relies on a ReplySent activity whose subject is the message, which plan 01-06 must write
- [Phase 01]: [01-06] SendOwnerReply refuses on conversation state first (closed, then no phone) and on the body second (empty, then over 1,600 characters); a failed send keeps the message as failed with the error in meta, adds a Reply not sent note, and leaves the owner's text in the box
- [Phase 01]: [01-06] Owner replies are stored queued and set sent only after TextChannel::send returns an id; plan 01-07 and Phase 2 takeover should call SendOwnerReply rather than write outbound messages themselves
- [Phase 01]: [01-06] Pest test helpers are global functions across the whole suite: a name reused in two test files aborts the full run (caught by timelineEntry); give each helper a file-specific name
- [Phase 01]: [01-06] Task 3 human-check (browser walk of /admin: contacts, timeline, reply, close and reopen) not run by the executor; recorded in .planning/WINDOWS.md for phase UAT. Thread and reply box styling is inline and Filament components only
- [Phase 01]: [01-07] Sync Now escapes each rejected error line with e() before it goes in the Filament notification body, because Filament sanitizes the body but still allows a safe subset of HTML
- [Phase 01]: [01-07] The money guard skips only Laravel's job_batches table (total_jobs is a job count); every other table and column is checked
- [Phase 01]: [01-07] phpunit.xml sets memory_limit 512M because the suite outgrew PHP's 128M CLI default at about 260 tests
- [Phase 01]: [01-07] Seeded Dana Whitfield shares +12025550123 with the /dev/chat default From, so a dev-chat text joins her thread; Pest arch rules over a list of function names check nothing, use one arch() per function
- [Phase 02]: 02-01: Postgres failures fixed test-side only; version labels read from IcmVersion::label(), long-name fixture 250 characters, no column widened
- [Phase 02]: 02-01: CI actions pinned to full commit SHAs (checkout v7.0.1, setup-php 2.40.0); later-sprint fixtures moved to automations/follow-up.md; exampleFolderFileCount() in tests/Pest.php is the one example-folder file count (8)
- [Phase 02]: Agent provider names must match config/ai.php keys exactly; Ai::textProvider() then decides whether the provider can write text (the SDK accepts any case)
- [Phase 02]: Agent loads uses present|array|min:1 so an empty list reads 'must have at least 1 item(s)'; unreadable-but-present files count as present for loads
- [Phase 02]: 02-08: DispatchInbound is the only place ProcessInbound is queued (conditional update on dispatched_at); ProcessInbound is debounced by conversation and claims the burst by handled_at; the debounce is an optimisation, the database claim is the guarantee
- [Phase 02]: CodeChecks reports the earliest match as written; plain numbers within three words of a price word count as prices, phone numbers, dates and times never do
- [Phase 02]: Fixed replies (set_replies) are checked at sync with the voice file's never_say from the same candidate folder; fill-ins limited to owner_name, business_name, next_open
- [Phase 02]: ProcessInbound::handle() returns bool (true = reaches front desk) so the D-41 form-lead stop is testable; sweep queues form messages too and ProcessInbound is the single place that stops them
- [Phase 02]: 02-04: send_on_own entries are checked in code (not Rule::in) so price, promise, discount and complaint get the hard-floor sentence; ReplyLabel::neverOnItsOwn() is the single source for the floor
- [Phase 02]: 02-05: {next_open} reads 'today' when moment + 60-minute margin <= closing time (boundary inclusive); closure date closes the whole local day; nextOpening scans 14 days then renders 'when we reopen'
- [Phase 02]: 02-06: a Jev model string containing 'latest' is refused by JevClassifier before any request; request body is exactly model, state{customer_message,draft_reply}, questions
- [Phase 02]: 02-10: OwnerAlertText::clean swaps schemed links, emails, www, domain-with-path and bare domains with common endings, then digit runs of 7+; RaiseOwnerAlert is the one door for alerts and SendOwnerAlert never throws, storing failed_at and failure on the alert row
- [Phase 02]: 02-07: JevClassifier accepts only JSON numbers from 0 to 1 and a choice among the options sent; any gap, HTTP error, timeout, blank key or latest model throws ClassifierFailed (no retry); a missing yes/no counts as a certain yes in TriageReading
- [Phase 02]: 02-11: A delayed alert is stale only when its subject message is outbound and no longer a draft; skipped alerts also get an owner_alerted timeline entry; opening times are converted to the app time zone before storing.
- [Phase 02]: 02-12: phone field uses inputMode('tel') not ->tel(), because tel() adds a regex rule whose 'format is invalid' message sits beside the plain-words sentence
- [Phase 02]: No tools on FrontDeskAgent: the model only proposes words and a label, sending stays in code
- [Phase 02]: History adds a '(No reply was sent.)' assistant turn when the thread ends on an unanswered customer text, so turns alternate
- [Phase 02]: RunFrontDeskAgent throws InvalidArgumentException for an empty burst; plan 02-14 must return early on an empty ClaimInboundBurst
- [Phase 02]: AgentReply does not check reply length; the 1,600 character limit belongs to the send rule in plan 02-14
- [Phase 02]: 02-14: every cheap send-rule check (label, length, code) is recorded on the verdict even after an earlier one fails; Jev runs only when all three pass and can only hold
- [Phase 02]: 02-14: any classifier exception holds the reply as classifier_failed; OutboundSender also refuses blank and over-1,600-character bodies
- [Phase 02]: 02-15: A channel failure keeps the failed message and alerts the owner (front_desk_failed); only a refusal such as the owner holding the reply lock becomes a send_refused draft
- [Phase 02]: 02-15: OutboundSender::whyBlocked() words its reasons for whoever is sending; SendOwnerReply::whyCannotReply() delegates to it
- [Phase 02]: 02-16: a send_refused hold goes through the same storeDraft path, so it also lands on the timeline and alerts the owner as draft_waiting
- [Phase 02]: 02-18: Intake answers are rows (latest id per conversation, service and question is current); unknown keys dropped, unknown options stored null with raw words
- [Phase 02]: 02-18: A web form names a contact only when it created it; matched contacts get a 'form gave the name' note. The agent's customer_name fills only an empty name via an atomic conditional update
- [Phase 02]: 02-17: A replacement draft alerts only when urgency rises or no alert in its draft chain was actually sent; the chain is kept in the new draft's meta.replaces so three texts in a row give one alert
- [Phase 02]: 02-17: One live draft per conversation is a partial unique index (messages_one_live_draft_per_conversation); a replaced draft is withdrawn with resolution replaced and kept; a holding reply is sent once per waiting draft and approved, edited or discarded resolutions end the wait
- [Phase 02]: 02-19: Pause is checked in OutboundSender::whyBlocked() for Automated origin only, re-checked inside the send lock; RunFrontDesk alerts paused_message per burst and never asks the agent while paused
- [Phase 02]: 02-19: Adding a foreign key column to a SQLite table rebuilds it and turns a partial unique index into a plain one; migrations that add FK columns to conversations or messages must drop and recreate the partial index afterwards
- [Phase 02]: 02-20: RunFrontDesk order is paused check, reply cap, phrase handoff, agent, wants_person, verdict asksForPerson — Cap and phrase checks cost no model call; handoff alerts, texts, then pauses because OutboundSender refuses automated sends while paused
- [Phase 02]: 02-20: CodeChecks::phrasePattern made public so AsksForPerson reuses the veto matching — Avoids a drifting copy of the normalisation and word-boundary rules
- [Phase 02]: 02-21: A safety line that failed to send does not count as answering the text, so RunFrontDesk's second chance retries it; alerts stay single because RaiseOwnerAlert dedupes by message and reason
- [Phase 02]: 02-21: With no live business folder the safety line still goes out without disclosure or gas company number; Jev's life_safety reading sends the line instead of the asks_for_person handoff
- [Phase 02]: 02-22: Approved drafts go out through OutboundSender::sendExisting, not SendOwnerReply::handle, so approving a draft never pauses the assistant (D-25); a taken-over conversation keeps its pause.
- [Phase 02]: 02-22: The replaced/already-sent race is decided by a status check under lockForUpdate when sendExisting claims the draft row; ConversationThread::draftId is #[Locked] and looked up inside the conversation (T-02-44).
- [Phase 02]: 02-23: the not-alerted rule (alerts exist and every one failed) lives once on Message::alertFailure(), used by the thread flag and the DraftsWaiting row
- [Phase 02]: 02-23: home screen widgets set $isLazy = false so they render with the page
- [Phase 02]: 02-24: STOP is stored already dispatched and handled inside the record transaction; a repeat STOP keeps the first opt-out date
- [Phase 02]: 02-25: The opted-out alert in RunFrontDesk runs after the life-safety second chance and does not dedupe against the safety alert, so the owner of an opted-out customer who texts about danger gets both the urgent safety alert and the call-them alert; a carrier refusal (21610) is told apart from other send failures by the exception's previous being RecipientOptedOut
- [Phase 02]: 02-26: Twilio webhook routes (webhooks/text/twilio*) skip trimStrings and convertEmptyStringsToNull so the signature is checked over the form exactly as Twilio sent it
- [Phase 02]: 02-26: the real Twilio channel trusts OptOutType only; keyword matching in code stays in RecordInbound as the fallback
- [Phase 02]: 02-27: Twilio send errors carry only the HTTP status and Twilio code; a 2xx with no sid is a failed send
- [Phase 02]: 02-27: A 21610 status callback marks the contact opted out even if the message was already failed; callbacks touch only outbound rows in queued/sent/delivered/failed
- [Phase 02]: 02-28: gate:export decided_at is created_at in UTC; final_text is null when nothing was sent; resolved_by is the user id; --without-text also strips quoted findings and error messages
- [Phase 02]: Ian approved the new top-level map/ folder (map-root, 2026-10-12); the cartographer map covers the text front desk, with escalate_to, intake_answers.icm_version_id and intake_answers.message_id parked, and no owner-alert card until Phase 2.1
- [Phase 02]: 02-30: phpunit.xml pairs forced env lines with server lines because Laravel env() reads $_SERVER before $_ENV and PHPUnit 13 never sets $_SERVER for env

### Pending Todos

None yet.

### Blockers/Concerns

- [Phase 1, carried]: Open review items WR-02, WR-11 and WR-13 are deferred with reasons in 01-REVIEW-DISPOSITION.md; threats T-01-36 and T-01-37 stay open until first deploy (01-SECURITY.md).
- [Phase 1, carried]: UAT notes (01-UAT.md), not issues: owner and customer messages look alike; reply box has no inner padding; a stale Reopen disappears with no message; Business Folder's "8 files" leaves out the 5 router files; the card labels the "closed by owner" note as customer-supplied; the web form quote keeps a name's HTML raw; a read-only folder shows PHP's raw tempnam() message.
- [Phase 1, carried]: A web form does not fill an empty name on a matched contact; rejected syncs consume version numbers (IN-03).
- [Phase 2]: Live agent runs need a provider key in `.env`; tests should use laravel/ai fakes.
- [Phase 2]: Twilio going live waits on A2P approval for Ian's test number; the registering entity is unstated. Does not gate the build (DEC-05).
- [Phase 3]: Walk-test determinism, reminder timing and the BOOK-06 and RECS-02 scope are undefined in the SPEC.

### Roadmap Evolution

- Phase 02.1 inserted after Phase 2: Owner Loop: owner-input additions split out of Phase 2 during discuss (02-CONTEXT.md)

## Deferred Items

Items acknowledged and deferred at milestone close, most recent first:

| Category | Item | Status | Deferred At | Milestone |
|----------|------|--------|-------------|-----------|
| Backlog | Quote, invoice, paid (SPEC Sprint 3), 999.1 | Parked until a client pulls it | 2026-10-10 | v1 |
| Backlog | Voice (SPEC Sprint 4), 999.2 | Parked until a client needs phones | 2026-10-10 | v1 |
| Backlog | Automations (SPEC Sprint 5), 999.3 | Parked until a client pulls it | 2026-10-10 | v1 |
| Backlog | Settings by conversation, connectors (SPEC Sprint 6), 999.4 | Parked until a client pulls it | 2026-10-10 | v1 |

## Session Continuity

Last session: 2026-10-12T17:33:04.981Z
Stopped at: Completed 02-30-PLAN.md
Resume file: None
