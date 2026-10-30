# Roadmap: Ops Platform

## Milestones

- ✅ **v1.0 Front Desk** — Phases 1, 2, 02.1 (shipped 2026-10-20)
- 🚧 **v2.0 Model Home** (Phases 4 to 12, in progress)

## Phases

<details>
<summary>✅ v1.0 Front Desk (Phases 1, 2, 02.1) — SHIPPED 2026-10-20</summary>

- [x] Phase 1: Foundation (7/7 plans) — completed 2026-10-11
- [x] Phase 2: Text Front Desk (41/41 plans) — completed 2026-10-13
- [x] Phase 02.1: Faster Answers (INSERTED) (28/28 plans) — completed 2026-10-18

Full details: [milestones/v1.0-ROADMAP.md](milestones/v1.0-ROADMAP.md)

</details>

### 🚧 v2.0 Model Home (In Progress)

**Milestone goal:** A cookie-cutter house that is a starting point for any business: every room built to one standard around the project, so a business is set up by walking the rooms and furnishing them at setup. Wording, policies and which rooms a business uses are the business's own decisions, not built in.

**Phase Numbering:**
- Integer phases (4 to 12): planned v2.0 work. Numbering starts at 4 because "Phase 3" means the old booking phase in the v1.0 docs and in seed SEED-261020-v30. That work is Phases 8, 9 and 10 here.
- Decimal phases (for example 8.1): urgent insertions, marked INSERTED.
- 999.x: Backlog parking lot, not scheduled.

**Not in any phase:** a property room, a subcontractors room, a multi-year contract above several projects, regions as a scope for who sees what, per-field ownership and a first trade pack are open and undecided. Estimates and payments stay in backlog 999.1. The owner loop stays in backlog 999.5.

- [x] **Phase 4: Room Standard** - Any room can be added to the business folder and checked, registered and tested the same way, and two systems can never own the same kind of record (completed 2026-10-23)
- [x] **Phase 5: Standard Rooms** - Marketing, content generation, Google Business Profile, company knowledge and money join the example business at the standard and nothing more (completed 2026-10-26)
- [x] **Phase 6: Front Desk and Team Rooms** - The two rooms that already work move in with no behavior change, and every owner alert is sent exactly once (completed 2026-10-28)
- [ ] **Phase 7: The Spine and Leads** - Every new lead opens a project in the business's own word, and the leads room joins at the standard
- [ ] **Phase 8: Schedule and Booking** - A customer books a time from a link, and the owner sees it and is told about it
- [ ] **Phase 9: Owner Intelligence** - Minutes to booked, today's schedule and the nightly rebuild can be read, and `gate:export` reports only what went out
- [ ] **Phase 10: Walk Tests and the Gate** - No folder change goes live until scripted customers have walked through it, and Dana books end to end
- [ ] **Phase 11: Phone Design Pass** - Every owner-facing screen works on a phone, including the dashboard, the Needs you card and the Business Folder page
- [ ] **Phase 12: Tour Deploy** - A staging instance shows the fictional example business behind an invite-only login

## Phase Details

### Phase 4: Room Standard

**Goal**: Any room can be added to the business folder and be checked, registered and tested the same way, and two systems can never both own the same kind of record.
**Depends on**: Nothing (first phase of v2.0; v1.0 shipped)
**Requirements**: ROOM-01, ROOM-02, ROOM-03, ROOM-04, ROOM-05, CONN-07, A11Y-01
**Success Criteria** (what must be TRUE):
  1. A room folder that holds only its `CONTEXT.md` passes `icm:sync`. When the room's fictional example file is added, the sync validates it, and a broken example is refused in plain words that name the file and the field while the previous version stays live.
  2. A room's `CONTEXT.md` names the files the room owns and the ones it reads, says what the room writes to `records/` (which may be nothing), and says whether it has an outside system. Today's files stay where they are, by kind.
  3. A room is one Laravel module that registers its file types with the sync, its tables, its dashboard section and its timeline entries, and one test boots it with an empty room folder and with its example folder, passing on SQLite and Postgres.
  4. A room that talks to an outside system declares a connector slot (an interface with a fake driver, the `TextChannel` pattern), and connector files say `owns` and `reads` instead of `objects`.
  5. A folder where two connectors own the same kind of record is refused with a plain-words message ("customers is owned by both connectors/a.md and connectors/b.md; pick one, the other can read it") and the previous version stays live.
  6. Each room's dashboard section, including the empty line (04-CONTEXT D-21), passes an automated accessibility check (axe rules, WCAG 2.2 Level AA) that runs with the room's boot test in CI, and the pretend room proves it. For the front desk and team rooms the check starts in Phase 11, because 02.1 D-48 holds styling until the phone walk.

**Plans**: 9/9 plans complete

Plans:
**Wave 1**
- [x] 04-01-PLAN.md — Tracer: a pretend room registers as a service provider and its contract-only folder turns it on, from the sync to the dashboard; registry contract and guards
- [x] 04-07-PLAN.md — Ian approves, then install, the Pest browser plugin; CI gets sockets

**Wave 2** *(blocked on Wave 1 completion)*
- [x] 04-02-PLAN.md — The room contract is checked (fields, with none or [] for an empty list; paths, records statement, unknown rooms) and the pretend room gets its connector slot
- [x] 04-08-PLAN.md — Ian approves, then install, playwright 1.63.0 exactly; CI gets Node and Chromium; Ian installs Chromium on his Mac

**Wave 3** *(blocked on Wave 2 completion)*
- [x] 04-03-PLAN.md — Rooms register their own file types, which must sit in the room's folder (D-04); one owner per file; the ownership table in SPEC tied to code

**Wave 4** *(blocked on Wave 3 completion)*
- [x] 04-04-PLAN.md — Connector files say owns, reads and slot (none or [] for an empty list, the room contract's rule); the two kind lists; two owners of one kind refused

**Wave 5** *(blocked on Wave 4 completion)*
- [x] 04-05-PLAN.md — Room timeline entries, hidden when a room is removed (stored cards redrawn at that sync) and never deleted; the pretend room's table and slot in use

**Wave 6** *(blocked on Wave 5 completion)*
- [x] 04-06-PLAN.md — The empty room's one plain line and the room boot test, empty and with its example folder

**Wave 7** *(blocked on Wave 6 completion)*
- [x] 04-09-PLAN.md — The room standard's accessibility check (axe, WCAG 2.2 AA), proven on the pretend room

**Planning notes** (settle these in discuss-phase):
- Which room owns each file in today's folder is not written down yet. This phase writes that table for all ten rooms across every top-level folder in the example business (`agents/`, `automations/`, `business/`, `connectors/`, `rules/`, `tests/`, `voice/`), including `business/profile.md` and `business/hours.md`. Every file has exactly one owning room (ROOM-01). Whether the sync enforces that, and from which phase (as each room lands, or once all ten exist), is open.
- How the standard is proven before a real room uses it (a room that exists only in tests, or the first rooms in Phase 5) is open.
- Where room modules live in the code is open. CLAUDE.md names the `app/` folders, and a new one needs Ian's approval.
- Which kinds of record a connector can own (PROJECT.md, "One owner per fact", lists customers, sales activity, invoices, payments, expenses, work orders, the schedule and documents) and where that list lives.
- What a room's dashboard section shows while the room is empty.
- `objects` becomes `owns` and `reads` in the connector file type in `docs/SPEC.md` too (PROJECT.md, "One owner per fact"). The Affects line and `docs/BLUEPRINT.md` rule applies.
- New concepts expected: Laravel service providers (how a module registers itself) and boot order. Explain at first use (CLAUDE.md).

### Phase 5: Standard Rooms

**Goal**: The rooms that need only the standard join the example business: marketing, content generation, Google Business Profile, company knowledge and money, each a folder the owner can open and furnish.
**Depends on**: Phase 4
**Requirements**: ROOM-08, ROOM-09, ROOM-10, ROOM-11, ROOM-14
**Success Criteria** (what must be TRUE):
  1. `icm:sync` on `examples/example-hvac` activates a version that includes the five room folders, each with its `CONTEXT.md` and one fictional example file. Reducing any room folder to its `CONTEXT.md` alone still syncs, and each room's boot test passes on SQLite and Postgres.
  2. The owner sees the marketing, content generation, Google Business Profile and company knowledge rooms in the dashboard, each with its own section. The money room meets the full standard like every room (module, and a boot test empty and with its example folder), with no payment-link slot and no payment records.
  3. Marketing owns `business/lead-sources.md` and `business/offers/`, both still where they are. Source and cost per lead, offers and prices behave exactly as before, and the existing suite for them passes unchanged.
  4. The Google Business Profile room has a connector slot (an interface with a fake driver) and a connector file that declares `owns` and `reads`; with only the fake driver, nothing reaches Google. Content generation says in its `CONTEXT.md` that it has no connector.

**Plans**: 9/9 plans complete
**UI hint**: yes

Plans:
**Wave 1**
- [x] 05-01-PLAN.md — Make the existing suite hold when real rooms land: narrow the annex test room, build the growing room, type and slot lists from code (tests only)
- [x] 05-02-PLAN.md — Never-left-out check (D-02) at unit level, and owns patterns pass over the house's routers (Pitfall 1)

**Wave 2** *(blocked on Wave 1 completion)*
- [x] 05-03-PLAN.md — Company knowledge lands: provider, contract, section, boot test with axe; D-23 rooms test to unit; D-24 counts

**Wave 3** *(blocked on Wave 2 completion)*
- [x] 05-04-PLAN.md — G-04-1 closed: hidden characters in names refused and marked in every refusal line (D-19, D-25)
- [x] 05-05-PLAN.md — Marketing lands with offers live today from the front desk's code; company knowledge and marketing can never be left out, through the sync

**Wave 4** *(blocked on Wave 3 completion)*
- [x] 05-06-PLAN.md — Content generation lands with its content brief type and no connector; SPEC rows and reads

**Wave 5** *(blocked on Wave 4 completion)*
- [x] 05-07-PLAN.md — Money lands with its notes file, no slot or records; the check that refuses card numbers and every 9-digit number however grouped (D-16, D-22, D-26)

**Wave 6** *(blocked on Wave 5 completion)*
- [x] 05-08-PLAN.md — Google Business Profile lands: read-only slot on a fake, connector file owning and reading none, categories file; D-23 slots test to unit

**Wave 7** *(blocked on Wave 6 completion)*
- [x] 05-09-PLAN.md — Google Business Profile categories checked for shape (D-07)

**Planning notes** (settled by [05-CONTEXT.md](phases/05-standard-rooms/05-CONTEXT.md), 2026-10-25):
- What the Google Business Profile room owns and reads (ROOM-11). Settled: D-06 to D-11.
- Content generation's model role is not decided. Settled: mid-tier drafts, BLUEPRINT only, no agent file this phase (D-14).
- Which of today's files company knowledge and marketing own beyond the two marketing names, taken from the ownership table written in Phase 4. Settled: the Phase 4 table as written; both rooms can never be left out (D-02 to D-04).
- Which of these rooms talk to an outside system and which say they have none (ROOM-03). Only Google Business Profile (a slot) and content generation (none) are decided. Settled: only Google Business Profile has a slot; company knowledge, marketing, content generation and money have none (D-05, D-11, D-13).
- This is five rooms in one phase. If discuss shows it is too much for one branch, split it at plan time along the marketing rooms (marketing, content generation, Google Business Profile) and the plain rooms (company knowledge, money). Settled: the planner proposes a split before writing plans and Ian approves it; a split becomes 5.1 (D-20).

### Phase 6: Front Desk and Team Rooms

**Goal**: The two rooms that already work move into the model home without a behavior change, and every owner alert reaches the owner exactly once.
**Depends on**: Phase 5 (marketing owns the offers and lead sources the front desk reads)
**Requirements**: ROOM-06, ROOM-15, ALRT-01, ALRT-02
**Success Criteria** (what must be TRUE):
  1. The front desk and team wraps land first: both pass the existing suite unchanged on SQLite and Postgres before any ALRT-01 or ALRT-02 work changes a test.
  2. The front desk is a room (folder, `CONTEXT.md` naming what it owns and reads, module, boot test), and the existing suite passes unchanged on SQLite and Postgres: same replies, same holds, same alerts.
  3. The team room wraps today's owner alerts, approvals and "who acted" the same way, with the existing suite unchanged on both databases. Nothing from backlog 999.5 is built.
  4. Each owner alert is texted exactly once, even when the job that sends it runs twice or two workers race for it (an atomic send claim and a unique de-duplication key). The alert-timing and one-alert rules behave as before.
  5. When a front-desk run fails, the owner is alerted for exactly the burst of texts that run claimed, using a claim token stored on the claimed rows, and a text that arrives afterward is not swept into that report.

**Plans**: 7/7 plans complete

Plans:
**Wave 1**
- [x] 06-01-PLAN.md — D-14 prep: the line-185 case on a hand-built registry, then the three triage-only ownership tests removed (tests only)

**Wave 2** *(blocked on Wave 1 completion)*
- [x] 06-02-PLAN.md — Front desk wrap: provider, contract, boot test, text slot named as a string, claimedWidgets pointer and its guard; file count 23 to 24

**Wave 3** *(blocked on Wave 2 completion)*
- [x] 06-03-PLAN.md — Team wrap: provider, contract that owns nothing, boot test, Drafts waiting claim, never left out; file count 24 to 25

**Wave 4** *(blocked on Wave 3 completion)*
- [x] 06-04-PLAN.md — ALRT-01 send claim: one conditional update right before the text, 2-minute release, the sweep and skip() respect it

**Wave 5** *(blocked on Wave 4 completion)*
- [x] 06-05-PLAN.md — ALRT-01 unique de-duplication key: createOrFirst on a nullable unique dedupe_key, backfill, business alerts keyless

**Wave 6** *(blocked on Wave 5 completion)*
- [x] 06-06-PLAN.md — ALRT-02 claim token: made in ProcessInbound's constructor, written by the claim, failed() finds its burst by it

**Wave 7** *(blocked on Wave 6 completion)*
- [x] 06-07-PLAN.md — ALRT-02 report once per token on every path, release on failure, form-only bursts reported on the catch path

**Planning notes** (settle these in discuss-phase):
- The wraps and the fixes are different kinds of change. The wraps land and pass the existing suite unchanged before any ALRT-01 or ALRT-02 work changes a test (criterion 1, Ian, 2026-10-21); keep the fixes out of the wrap plans.
- The review items behind ALRT-01 and ALRT-02 (WR-03, WR-04, WR-22) are written up in `.planning/milestones/v1.0-phases/02-text-front-desk/02-REVIEW-DISPOSITION.md`.
- The alert path fixed here is the one booking uses for BOOK-05 in Phase 8.
- Settled by `.planning/phases/04-room-standard/04-CONTEXT.md` D-19 (2026-10-22): a wrap only registers what exists. Code that exists today stays where it is, by kind, and the room points at it; the one allowed test change is the example folder's file count (D-16).
- Card tests stay unchanged here. Card tests that change because a project now appears on the card change in Phase 7.

### Phase 7: The Spine and Leads

**Goal**: Every new lead opens a project, named in the business's own word, that ties the piece of work to its contact and conversation.
**Depends on**: Phase 5 (marketing owns the lead sources and offers the leads room reads), Phase 6 (the front desk wrap lands before the card changes)
**Requirements**: PROJ-01, PROJ-02, ROOM-07, LEAD-05
**Success Criteria** (what must be TRUE):
  1. `business/profile.md` has one field naming what the business calls a project (Job, Project, Matter). The sync accepts a valid word and refuses a missing or invalid one in plain words that name the file and the field, with the previous version staying live.
  2. A text from a new number, or a web form lead, opens a project for that contact. The project is tied to the contact and the conversation, shows on the customer card and in the dashboard, and uses the business's word (a business that sets "Matter" sees "Matter").
  3. A project is ready to carry appointments, zero, one or many. The appointment records themselves arrive with booking, which attaches them in Phase 8.
  4. The front desk behaves exactly as before, and it sees the project only because it loads the same customer card. The only existing tests that change are: card tests that now show a project; the `exampleFolderFileCount()` number (04 D-16); `tests/Feature/Rooms/FrontDeskBootTest.php:90`'s New leads guard (06 D-03); `profile.md` fixtures written from scratch, changed only by adding the new field line; and tests that assert the text of marketing's `CONTEXT.md`, changed only to the new sentence. Each plan names each changed test by file and line, a plan that needs any other existing test to change stops and brings it to Ian, and the suite is green on SQLite and Postgres (07 D-22).
  5. The leads room meets the standard (folder, `CONTEXT.md`, example file, module, boot test) and reads the lead sources and offers that marketing owns.

**Plans**: 5/8 plans executed

Plans:
**Wave 1**
- [x] 07-01-PLAN.md — The business's word: `project_word` in `business/profile.md`, checked by the sync, read by `ActiveConfig::projectWord()`, listed in SPEC (D-12)

**Wave 2** *(blocked on Wave 1 completion)*
- [x] 07-02-PLAN.md — The spine: the platform `projects` table (one lead project per contact, no appointment column), the owner's open through `OpenProject`, the timeline entry on the card

**Wave 3** *(blocked on Wave 2 completion)*
- [x] 07-03-PLAN.md — The card's "## <Plural word>" section above the timeline, names quoted, seen by the front desk only through the card (D-13); CardInjectionTest gains its case

**Wave 4** *(blocked on Wave 3 completion)*
- [x] 07-04-PLAN.md — Close, reopen and rename through their own actions, and a test that only the four actions write projects (D-11)

**Wave 5** *(blocked on Wave 4 completion)*
- [x] 07-05-PLAN.md — The contact screen: projects listed in the business's word with New, Close, Reopen and Rename (D-04, D-14)

**Wave 6** *(blocked on Wave 5 completion)*
- [ ] 07-06-PLAN.md — The leads room at the standard: provider, contract, boot test, claims New leads, the WR-09 guard, marketing's web form sentence; file count 25 to 26, FrontDeskBootTest.php:90

**Wave 7** *(blocked on Wave 6 completion)*
- [ ] 07-07-PLAN.md — The leads section: open projects with a keyboard link, an absolute date and their source or opener, axe clean (D-15, D-16)

**Wave 8** *(blocked on Wave 7 completion)*
- [ ] 07-08-PLAN.md — A new lead opens a project after the inbound commits, never two, never in the inbound's way, not for a first STOP (LEAD-05, D-01 to D-03, D-23); CardInjectionTest.php:90

**UI hint**: yes

**Planning notes** (settle these in discuss-phase):
- What happens when an existing customer starts a second job (LEAD-05) is open: a new project, the same project, or a question for the owner.
- Service history by address: whether history hangs off the person, the service address or both, and how an address match under a different name is handled (`docs/BLUEPRINT.md`, Ideas inbox, "Service history by address").
- Where the appointment table and the project-to-appointment link first appear (here or in Phase 8) is open. PROJ-01 is mapped to this phase, but its appointment clause is only proven when booking attaches an appointment in Phase 8.
- What the card and the dashboard show for a project (a line on the card, a list, a screen of its own). Estimates and payments are not part of it (backlog 999.1).
- Research behind the shape: `docs/research/contractor-operations-2026-10-19.md` (roofing, HVAC, remodel, landscaping contract).
- Not decided here: a multi-year contract above several projects, a property room, subcontractors, regions.

### Phase 8: Schedule and Booking

**Goal**: A customer can finish the conversation by booking a time, and the owner sees it and is told about it.
**Depends on**: Phase 6 (alerts sent exactly once), Phase 7 (an appointment attaches to the project the lead opened)
**Requirements**: ROOM-12, BOOK-01, BOOK-02, BOOK-03, BOOK-04, BOOK-05, A11Y-02
**Success Criteria** (what must be TRUE):
  1. A customer who is texted a booking link opens a page, taps one of the offered times and ends with a confirmed appointment, without typing. Only times inside `hours.md` hours, outside closures, past `booking_lead_minutes`, long enough for the service, and in the business timezone are offered.
  2. A booked appointment attaches to the project the lead opened and appears on the contact's timeline, as the next appointment on the customer card, and on a dashboard schedule screen. A project with two appointments shows both.
  3. The customer is reminded before the appointment, sent by the scheduler.
  4. The owner is alerted, once, when an appointment is booked and when a conversation needs a human, per `owner_alert` in `rules/sending.md`.
  5. The schedule and booking room meets the standard (folder, `CONTEXT.md`, example file, module, boot test).
  6. The booking page meets WCAG 2.2 Level AA: it works by keyboard alone and with a screen reader, each time is announced with its day and time, focus moves to the confirmation, errors are text beside the field, tap targets meet the 24-pixel minimum, and it stays usable at 200% zoom. It passes the automated check and a keyboard-only and VoiceOver walk at UAT, and its UI-SPEC carries this.

**Plans**: TBD
**UI hint**: yes

**Planning notes** (settle these in discuss-phase):
- Reminder timing and where it is configured are not defined (BOOK-04); `hours.md` only carries `booking_lead_minutes`.
- The booking page is a Livewire customer page and gets its own UI spec, built phone-first (PROJECT.md, Frontend; the part of the 2026-10-11 decision that stands). Phase 11 re-checks it at 375 and 390 (DSGN-05). Run `/gsd-ui-phase 8` before planning.
- New concepts expected: scheduler, notifications, Livewire pages, time zones. Time zone handling comes from `business/profile.md`.
- Which project an appointment attaches to when a contact has more than one follows the second-job decision in Phase 7.
- Booking never offers a time that has already passed (`docs/phase-2.2-owner-input.md` §3).
- Whether the room declares a connector slot now or says it has none (Google Calendar is backlog 999.4), and whether the schedule can stay owned by an outside tool in v2.0 ("One owner per fact").
- `records/today.md` and the nightly rebuild are in Phase 9.

### Phase 9: Owner Intelligence

**Goal**: The owner and the owner's own AI can read how fast the business finishes a conversation and what is on today, and `gate:export` reports only what went out.
**Depends on**: Phase 8
**Requirements**: ROOM-13, RECS-02, BOOK-06, GATE-05
**Success Criteria** (what must be TRUE):
  1. `records/today.md` (the schedule) is re-rendered from appointments on every booking change, and a nightly full rebuild of `records/` is scheduled.
  2. The minutes from a contact's first message to their booked appointment are shown on the contact and in a dashboard summary.
  3. `gate:export` counts only what went out (a failed or queued send is not counted as sent), gives `original_text` the same way on every path, and exports the `answered` resolution and the 02.1 fixed-reply kinds.
  4. The owner intelligence room meets the standard (folder, `CONTEXT.md` that says it writes `today.md`, example file, module, boot test).

**Plans**: TBD
**UI hint**: yes

**Planning notes** (settle these in discuss-phase):
- BOOK-06 and RECS-02 are derived from the SPEC rather than named in the Sprint 2 row; confirm scope.
- What the dashboard summary shows (one average, a list, by lead source) is open; BOOK-06 says only that the minutes are visible.
- What counts as the first message and the booking when a contact has more than one project.
- Whether the room declares a connector slot now or says it has none (an MCP server for the owner's AI is backlog 999.4).
- Detail for GATE-05 is in `.planning/milestones/v1.0-phases/02-text-front-desk/02-REVIEW-DISPOSITION.md` (WR-09) and the 02.1 deferred note.

### Phase 10: Walk Tests and the Gate

**Goal**: No folder change goes live until scripted customers have walked through it, and "Dana end to end" shows a customer going from first text to a booked appointment.
**Depends on**: Phase 8, Phase 9 (Dana's records include `today.md`)
**Requirements**: WALK-01, WALK-02, WALK-03
**Success Criteria** (what must be TRUE):
  1. Walk-test files in `tests/` (persona, channel, script, expect) are validated by the sync, and a broken one is refused in plain words that name the file and the field. Valid ones are compiled into the candidate version.
  2. Every sync runs each scripted customer in `tests/*.md` against the candidate version. After a change that breaks one (for example the agent would now quote a price that is not in the folder, or the expected time is no longer offered) the sync fails, names the test and the check that failed in plain words, keeps the previous version live, writes the attempt to `changelog.md` and shows it on the Business Folder page.
  3. When validation and every walk test pass, the candidate goes live as before.
  4. "Dana end to end" passes: a scripted customer texts in, answers the intake questions, picks a time and is booked, with the owner alerted, the card and records updated, and a reminder queued. It passes as a walk test and as a feature test.

**Plans**: TBD
**UI hint**: yes

**Planning notes** (settle these in discuss-phase):
- How a walk test drives the agent deterministically (scripted model versus a live model) is not defined in the SPEC; the activation gate cannot depend on a flaky call. The same question applies to the clock (times offered, reminders) and to how the Jev reading is handled in a walk test. Jev is a real service; no stand-in without Ian.
- New concepts expected: running the front desk against a scripted customer, and a sync step that can refuse activation.

### Phase 11: Phone Design Pass

**Goal**: The dashboard, the Needs you card and the Business Folder page work on a phone, so an owner knows what to tap first. Checked at 375 and 390 wide.
**Depends on**: Phase 10 (the screens the pass covers now exist)
**Requirements**: DSGN-01, DSGN-02, DSGN-03, DSGN-04, DSGN-05, A11Y-03
**Success Criteria** (what must be TRUE):
  1. On a phone the dashboard is one urgency-ordered list, safety and complaints first. Each item shows who, what they asked, how long they have waited, and one clear tap.
  2. The Needs you card works on a phone: the draft box shows the whole text, the "Customer wrote" label and the "wrote again" badge read apart, Take over is no longer the biggest button, and a reply a person approved shows that person's name instead of "Front desk". The styling 02.1 D-48 held is applied.
  3. The Business Folder page works on a phone: the Offers warning has an icon and points to the file to fix, the Front desk card does not repeat its label or show model IDs, and developer detail (the fingerprint, "Not a git repository", file types such as "router") is out of the owner's way.
  4. A held draft says what is actually held (for example "waiting on the customer's answers for X") instead of "no price on file". Only words change: what is held, sent or alerted does not.
  5. Every other owner-facing screen that exists by now works on a phone at 375 and 390 wide: each room's dashboard section, the schedule screen and the minutes-to-booked summary. The customer booking page, built phone-first in Phase 8, is re-checked at 375 and 390.
  6. Every owner-facing screen passes the automated accessibility check and a keyboard-only and VoiceOver walk at 375 and 390 wide. The front desk and team rooms' dashboard sections join the automated check here (A11Y-01). PROJECT.md Frontend open item 5 is settled here.

**Plans**: TBD
**UI hint**: yes

**Planning notes** (settle these in discuss-phase):
- The layout is Ian's call at the pass. Inputs: the pending todo `.planning/todos/pending/2026-10-17-phone-design-pass.md`, `docs/phase-2.2-owner-input.md` sections 5 and 6, and PROJECT.md Frontend open items 1 to 8. Item 8, what "/" shows, is DEPL-03 in Phase 12.
- Doing this pass reopens the 2026-10-11 Frontend decision (Ian, 2026-10-21), except the booking page's own UI-SPEC, built phone-first in Phase 8, which stands. Run `/gsd-ui-phase 11` first.
- The hold-reason words live in `RunFrontDesk::noPriceReason()` and `Verdict::plainReasonFor()` (the todo). Tests that quote hold-reason words, walk tests included, change with them.

### Phase 12: Tour Deploy

**Goal**: A staging instance runs the fictional example business on the fake text channel, behind an invite-only login, so the model home can be shown with no real customer data.
**Depends on**: Phase 11 (DEPL-02 says the tour deploy comes after the phone design pass)
**Requirements**: DEPL-02, DEPL-03, DEPL-04
**Success Criteria** (what must be TRUE):
  1. A person with an invite signs in to the staging instance and sees the example business running on the fake text channel. A person without an invite cannot get in.
  2. The instance holds only the fictional example business: no real customer data and no real client folder.
  3. "/" no longer shows the stock Laravel page.
  4. The queue worker runs under a supervisor that restarts it: stop the worker and it comes back without anyone at the keyboard.

**Plans**: TBD
**UI hint**: yes

**Planning notes** (settle these in discuss-phase):
- Hosting (Laravel Cloud or Forge) is chosen in this phase (PROJECT.md, Open decisions).
- Which other before-first-deploy items a tour needs: T-01-36, T-01-37, WR-08, WR-10 to WR-15, error tracking, backups (STATE.md, Blockers/Concerns). Also which provider keys the instance carries, since live front-desk runs need one.
- How a visitor tries the front desk on staging (`/dev/chat` is local-only today), and whether the text a visitor types is kept.
- What "/" shows instead of the stock page: a redirect to `/admin` or a plain page (Ian, 2026-10-16).
- Deploy notes carry in from PROJECT.md: restart the worker after any `.env` or key change.
- The real-client items stay on the before-first-client list: ZDR routes, consent wording, A2P and Twilio go-live, the attorney list. DEPL-01 is unchanged.

## Progress

**Execution Order:**
Phases execute in numeric order: 4 → 5 → 6 → 7 → 8 → 9 → 10 → 11 → 12

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 4. Room Standard | 9/9 | Complete    | 2026-10-23 |
| 5. Standard Rooms | 9/9 | Complete    | 2026-10-26 |
| 6. Front Desk and Team Rooms | 7/7 | Complete    | 2026-10-28 |
| 7. The Spine and Leads | 5/8 | In Progress | - |
| 8. Schedule and Booking | 0/TBD | Not started | - |
| 9. Owner Intelligence | 0/TBD | Not started | - |
| 10. Walk Tests and the Gate | 0/TBD | Not started | - |
| 11. Phone Design Pass | 0/TBD | Not started | - |
| 12. Tour Deploy | 0/TBD | Not started | - |

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

### Phase 999.5: Owner Loop (BACKLOG)

**Goal:** The owner and the people they trust can act on the front desk from wherever they are: more than one person can approve drafts, a draft can be approved by replying to the alert text, the office hears by email, nothing waits unseen, and the agent steps back without going silent when a person joins the conversation.
**Parked:** moved here unchanged from Phase 02.2 on 2026-10-19 (Ian), pulled by the first client with staff. See PROJECT.md Key Decisions, "The model home is the next milestone". `docs/phase-2.2-owner-input.md` stays as the bank of setup questions. Alerts sent exactly once (WR-03, WR-04, WR-22) and the `gate:export` fix (WR-09) now live in v2.0 requirements ALRT-01, ALRT-02 and GATE-05 (Ian, 2026-10-21).
**Requirements:** TBD (define new IDs in REQUIREMENTS.md when promoted)
**Scope** (split out of Phase 2 on 2026-10-11; renumbered from 02.1 to 02.2 on 2026-10-14 when Faster Answers went first; see `.planning/milestones/v1.0-phases/02-text-front-desk/02-CONTEXT.md` § Deferred Ideas and `docs/phase-2-owner-input.md`):
- Staff users invited by the owner, each with a phone, an email, alert choices and a "can approve drafts" switch; user management and Sync Now stay owner-only (the first role split); sign out everywhere and remove a user.
- Approve by replying to the alert with a short code (K7 sends the draft as written, K7 plus words sends those words); only a registered phone can approve; a code for a replaced draft is refused with "The customer wrote again".
- Email alerts; the "handled" follow-up on the other channel names who acted and how.
- A draft nobody acts on: re-alert after a set time, a backup recipient, and an update to the customer if the wait runs past the holding reply's promise.
- Assist mode: an owner reply stops the agent sending on its own but it keeps drafting suggestions; it returns after a per-business quiet period (replaces Phase 2's owner-reply takeover).
- The after-hours on-call switch per business (urgent service requests such as plain flooding).
- `escalate_to` may name a staff user.
- Complaint alerts can go to a person the owner names (for example an office manager), chosen at setup and changeable later, with the owner as backup when that person cannot be reached; a complaint always reaches someone (Ian, 2026-10-16).
- When alerts route to different people, revisit whether the check-in person also needs an alert for a check-in held as a complaint; in 02.1 one customer text raises one alert, the more serious one, so that text raises only the complaint alert (Ian, 2026-10-18, `02.1-CONTEXT.md` D-34, G-02.1-1).
- The LLM fallback classifier driver on a ZDR route.
- A user's own phone texting the business. Phase 2 treats it as a customer (02-CONTEXT D-53), so an owner who replies to an alert text gets an agent answer as if they were a customer; the reply-code parser has to recognise a registered phone first.
- WR-03, WR-04 (owner alerts sent exactly once): see v2.0 requirement ALRT-01 in `.planning/REQUIREMENTS.md`.
- WR-22 (a failed front-desk run alerts for exactly the burst it claimed): see v2.0 requirement ALRT-02 in `.planning/REQUIREMENTS.md`.
- WR-09 (`gate:export`): see v2.0 requirement GATE-05 in `.planning/REQUIREMENTS.md`.
- Carried from the Phase 2 review (2026-10-12, `02-REVIEW-DISPOSITION.md` WR-17 to WR-19), test hardening: the reply-cap "does not count" test cannot detect most of what it claims (WR-17); `toContain('fi-collapsed')` is always true, so the collapsed customer details are not tested (WR-18); "throws NoActiveVersion from every accessor" omits the accessors Phase 2 added (WR-19).
- Carried from the Phase 2 UI review (`02-UI-REVIEW.md`): a held draft's Send and Discard buttons show a loading state and are disabled while running, and Discard asks for confirmation.

- Carried from the 02.1 Faster Answers discussion (2026-10-14, `.planning/milestones/v1.0-phases/02.1-faster-answers/02.1-CONTEXT.md` § Deferred Ideas): a reply code for a draft with newer customer messages must not send blindly; the "handled" follow-up covers drafts auto-closed as `answered`; the customer update past the holding reply's promise ships together with the owner re-alert; the WR-09 part (exporting `answered` and the fixed-reply kinds) is in v2.0 requirement GATE-05.
- The fallback classifier (Ian, 2026-10-15, superseding the 2026-10-14 open question): a runtime fallback when Jev fails, on a route other than OpenRouter and on a ZDR route, and trigger-and-hold only: it can send the safety line, hold or hand off, never pass a send. It gets message text only (02-CONTEXT D-05). Its effect on 02-CONTEXT D-03's "fail closed" is settled in 02.2 discuss. Office admin and backup recipients get the `safety_check_down` alert (02.1-CONTEXT D-50) once 02.2 builds staff users.

**Success Criteria** (what must be TRUE):
  1. The owner invites a user from the dashboard, gives them the approve switch, and every approval, edit and discard records who did it; a user without the switch cannot approve, and only the owner manages users or runs Sync Now.
  2. Replying to an alert text with its code sends the draft (or the replacement words) through the same path as a dashboard approval, recorded on the timeline; a reply with no valid code, or from an unregistered phone, sends nothing and gets a short help text.
  3. When a draft is handled on one channel, the other channel gets a follow-up naming who handled it and how; a draft left untouched past the set time re-alerts and reaches the backup recipient.
  4. After a person replies in a conversation the agent only drafts there until the quiet period passes, then resumes on its own; Take over still stops it fully until handed back.
  5. With the on-call switch on, an after-hours urgent request alerts the on-call person right away; with it off, it waits for opening time.

**Plans:** 0 plans

Plans:
- [ ] TBD (promote with /gsd-review-backlog when ready)

Unscheduled v2 items with no sprint in the SPEC (FOLD-01, FOLD-02, KNOW-01, DEPL-01) are listed in REQUIREMENTS.md and will be attached to a phase when a client or the first deploy pulls them.
