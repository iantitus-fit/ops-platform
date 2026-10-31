# Phase 7: The Spine and Leads - Context

**Gathered:** 2026-10-29
**Status:** Ready for planning

<domain>
## Phase Boundary

1. **The spine (PROJ-01, PROJ-02).** A Project record ties a piece of work to its contact and the conversation it began in. It is open or closed, the owner can open, close, reopen and rename one, and it is ready to carry appointments by shape. `business/profile.md` gets one field naming what the business calls a project, from a fixed list in code. The customer card and the dashboard use that word. The front desk sees a project only because it loads the same card.
2. **The leads room (ROOM-07, LEAD-05).** A room at the standard (folder, `CONTEXT.md`, example file, module, boot test) that reads the lead sources and offers marketing owns. When an inbound creates a new contact, it opens a project for that contact, after the inbound commits and beside the shared inbound path, changing nothing the front desk does.

Not in this phase: appointments (Phase 8), estimates and payments (999.1), a project's service or service address, pipeline stages, a business-wide projects screen or records file (Phase 9), and anything that changes a contact's status.

</domain>

<decisions>
## Implementation Decisions

Earlier decisions are cited as "02 D-nn", "02.1 D-nn", "04 D-nn", "05 D-nn" and "06 D-nn", each from that phase's CONTEXT.md. Review items are cited with their file, as in "06 WR-09 (06-REVIEW-DISPOSITION.md)".

### Locked, carried forward (do not reopen)
- DEC-01 to DEC-14 (`PROJECT.md`), 02 D-01 to D-56, 02.1 D-01 to D-51, 04 D-01 to D-27, 05 D-01 to D-33, 06 D-01 to D-21.
- The alert-timing and one-alert rules as built, and the 02.1 IN-11 rule: never a missing alert for a duplicate (`02.1-REVIEW-DISPOSITION.md`). Phase 7 raises no new alert.
- The WCAG 2.2 Level AA rule and the chokepoint rule (CLAUDE.md). D-11 records how the chokepoint rule applies to projects.
- Carried facts this phase builds on, with no new decision needed:
  - "Project" in code and docs; the business's own word on screen (PROJECT.md, "The project is the spine"). The spine in v2.0 carries appointments only (PROJECT.md, the v2.0 floor plan).
  - Projects are a kind the platform owns; a connector may read them, never own them, and `reads: projects` is already accepted (04 D-23).
  - A room writes only while it is in the live folder (04 D-08; `app/Rooms/RecordRoomEntry.php:34`).
  - Contact matching stays phone, then email; a form's name never overwrites a stored name (02 D-43). A form lead is never texted first (02 D-41).
  - A contact has at most one open conversation (`conversations_one_open_per_contact`); only the owner closes one, and a later text starts a new one.
  - `RecordInbound` is the shared inbound path, not front desk code. The front desk room points at `RunFrontDesk`, `ProcessInbound`, `OutboundSender` and `app/SendRule` (`app/Rooms/FrontDesk/FrontDeskServiceProvider.php`). Editing `RecordInbound` would be a decision of its own, with its own Affects line, brought to Ian.
  - The front desk's code, prompt and rules stay frozen (PROJ-02). It loads the customer card fresh (02 D-36; `app/Ai/FrontDeskPrompt.php:104`).

### When a project opens (LEAD-05)
- **D-01:** A project opens automatically only when an inbound created the contact (`matched_by` new): a text from a number never seen, or a web form whose phone and email match no contact. A web form that matches a known contact by phone or email opens nothing; it is a second job (D-04), because anyone can type any number into the form (02 D-43). A text from a known number opens nothing. Affects: sales, customer communication.
- **D-02:** The open runs after the inbound's transaction commits, beside `RecordInbound` (an observer or listener; `RecordInbound` is not edited). It survives a failure and never doubles: it is retried if it fails, and does nothing if the contact already has a project, so a retry or two inbounds at once never open two. A failed open never rolls back or delays the inbound. Affects: sales, customer communication.
- **D-03:** The open checks that the leads room is live when it runs, not when the inbound arrived, so turning the room off stops a pending or retried open (04 D-08). Turning the room on later opens no projects for contacts that arrived while it was off; it starts with the next new lead, with no backfill. Leads is not added to the never-left-out list (04 D-09). Affects: sales, operations.

### Second job, status and the conversation
- **D-04:** When an existing customer starts a second job, the owner opens the project by hand: the contact screen gets a "New <word>" action. Nothing is asked, flagged or alerted, so the alert rules are untouched. Affects: sales, customer communication.
- **D-05:** A project is open or closed. The owner closes and reopens it by hand on the contact screen. A contact may have more than one open project at once (a law office's matters, a repair while a replacement is quoted); there is no one-open rule like conversations have. Pipeline stages stay not yet decided (BLUEPRINT Sales). Affects: sales, operations.
- **D-06:** A project links to the conversation it began in, as a nullable reference on the projects table. An automatic open takes the conversation the lead arrived in; a hand-opened project takes the contact's open conversation at that moment, or none if there isn't one. No column is added to `conversations` or `messages`, which the front desk owns (06 D-04). Texts stay on the conversation and are never sorted into projects. Affects: customer communication, sales.
- **D-07:** Opening, closing, reopening or renaming a project never changes the contact's status. New leads lists Lead contacts from the last 7 days and every new lead now opens a project, so a status change on open would empty it. What makes a contact a Customer is left to Phase 8 or backlog 999.1 (deferred). Affects: sales, marketing.

### What a project holds
- **D-08:** Beyond its contact, its conversation (D-06), open or closed, and when it opened and closed, a project records who opened it and who closed it (a new lead, or the user), and a short name the owner types: one line, length-bounded, empty by default. Screens fall back to "<word> N" when there is no name. The name reaches the front desk only through the card (PROJ-02). The service and the service address are deferred (see Deferred). Affects: sales, operations, owner intelligence.
- **D-09:** Phase 7 meets criterion 3 by shape: the projects table has no appointment column and nothing assumes a single appointment. The appointments table and its link to a project arrive in Phase 8, with the link on the appointment. Phase 7's verification records criterion 3 as met by shape and names Phase 8 criterion 2 (a project with two appointments shows both) as the proof of PROJ-01's appointment clause, as the roadmap's planning note says. A verifier gap on criterion 3 is answered by that pointer, never by building an appointments table in Phase 7. PROJ-01 is not split or held back. Affects: sales, operations.
- **D-10:** The projects table is platform-owned: no room lists it in its `tables` (06 D-04), like `contacts` and `activities` today. Projects outlive the leads room being off, and booking will open them too (Deferred). The leads room reads projects and writes them only through the open action (D-11). Affects: sales, operations, connectors and data. — **Reversibility:** one-way — a new table by migration, which Phase 8's appointments will reference.
- **D-11:** Writing a project is not an agent action, by the same reasoning as 06 D-09: no model decides or writes it, so no checks-passed record is added. Every write still goes through one action each: open, close, reopen and rename, each recording who did it. An architecture test allows those four and nothing else to write the projects table. Reads stay free (the card, the dashboard, the contact screen). The chokepoint rule applies the day an agent opens, closes, reopens or renames a project. Affects: safety and compliance, sales.

### The business's word
- **D-12:** The `business/profile.md` field holds one word from a fixed list in code: Job, Project, Matter, Case, Work order, Engagement, each with its plural written in code. The value is matched without regard to case; the screens set the casing. A missing or unlisted value is refused in plain words naming `business/profile.md` and the field and listing the choices, and the previous version stays live (criterion 1). `docs/SPEC.md` lists the same words, and a test fails if the two disagree (the 04 D-22 pattern). Adding a word is a code change plus that SPEC line. The example business sets Job. Affects: knowledge, sales, customer communication. — **Reversibility:** costly — the words end up in every client's `business/profile.md`; removing one breaks a live folder.

### Where a project shows
- **D-13:** The customer card gets a "## <plural word>" section above the timeline, one line per project: its name or "<word> N", open or closed, and when and how it opened. Opening, closing, reopening and renaming each add a timeline entry. The section and the entries are platform types drawn by `CustomerCard`, like `owner_alerted` today (06 D-05), not leads-room entries: projects are a platform kind (04 D-23) and the owner can open one by hand, so they do not disappear when the leads room is off. The section is left out entirely when the contact has no project, so that card renders exactly as today. The owner-typed name is quoted under a label and neutralized like customer text so it cannot fake a heading; `tests/Feature/Records/CardInjectionTest.php` gains that case. Affects: customer communication, sales, owner intelligence.
- **D-14:** The contact screen lists the contact's projects with the New, Close, Reopen and Rename actions (D-04, D-05, D-08). Affects: sales, operations.
- **D-15:** The leads room claims New leads (`NewLeads`) as a pointer (06 D-03, 06 D-18), unchanged on screen; `tests/Feature/Rooms/FrontDeskBootTest.php:90` changes and is named under criterion 4 (D-22). The leads room adds the guard that refuses a widget one room claims and another adds in its sections, closing 06 WR-09 (06-REVIEW-DISPOSITION.md). Its own room section lists open projects. Two rules from the Phase 6 UI review, so the section adds no new Phase 11 items: each row's link to the contact is reachable by keyboard, and "opened" shows a date, not only relative time. When the leads room is off its section hides; every project still shows on the contact screen and the card. Affects: sales, owner intelligence, customer communication.
- **D-16:** In the open-projects section, a project opened automatically shows the lead's source by its name in `business/lead-sources.md` (from the contact's `lead_source_key`); a project the owner opened by hand shows who opened it, not the contact's first source. Affects: marketing, sales.
- **D-17:** `records/open-leads.md` is unchanged and its tests stay unchanged. The leads room declares `writes_records: none`. Projects reach the owner's AI through each customer card; a business-wide projects file joins the Phase 9 records work (Deferred). Affects: owner intelligence, sales.

### The leads room
- **D-18:** The leads room (`rooms/leads/`) owns no files, lists no tables (D-10), has no connector slot, and is optional (D-03). It reads `business/lead-sources.md` and `business/offers/`, which marketing owns (04 D-12). Nothing in Phase 7 uses the offers; its `CONTEXT.md` says it reads them for later lead follow-ups. A room that owns no files is never shown as empty (04 D-21). Affects: marketing, sales.
- **D-19:** The web form stays shared and unowned, like `RecordInbound`, because it must keep taking leads when the leads room is off (a form lead goes to the owner and `open-leads.md` through the shared inbound path, 02 D-41). The leads room names no intake. The sentence in marketing's `CONTEXT.md` ("Where lead intake such as the web form sits is the leads room's call.") is updated to say so, in the example folder, in a plan with its tests, with no test change beyond what that file edit forces. Affects: marketing, sales, customer communication.
- **D-20:** The `needs` field from the 04 deferred note is not added in Phase 7. Booking no longer depends on leads (booking opens a project itself when the contact has none, Deferred), so no room needs another room's records yet. The note is carried to the Phase 8 discuss. Affects: knowledge, operations.
- **D-21:** No UI-SPEC for Phase 7. GSD's UI plan-gate returns block false (no static frontend evidence); the screens are stock Filament (the contact screen's project list and actions, and one room section); styling waits for Phase 11 (02.1 D-48); the leads section gets the axe check in its boot test (04 D-27, A11Y-01). Amended 2026-10-30 (Ian): a short 07-UI-SPEC.md records this stock-Filament contract, because GSD's ui-safety-gate scans every commit in the phase and needs a contract to check. It asks for no design work. Affects: safety and compliance, sales.

### Tests
- **D-22:** ROADMAP criterion 4 is reworded in the context commit to allow exactly these existing-test changes: card tests that now show a project; the `exampleFolderFileCount()` number (04 D-16); `tests/Feature/Rooms/FrontDeskBootTest.php:90`'s New leads guard (06 D-03); `profile.md` fixtures written from scratch, changed only by adding the new field line (criterion 1); and tests that assert the text of marketing's `CONTEXT.md`, changed only to the new sentence (D-19). Every other existing test stays unchanged, and each plan names each changed test by file and line. A plan that needs any other existing test to change stops and brings it to Ian. Affects: all ten areas (test discipline), led by safety and compliance.

### Settled during planning (2026-10-29)
- **D-23:** Narrows D-01. The automatic open does nothing if the contact is opted out when the job runs, so a first-ever STOP from a never-seen number opens no project. `RecordInbound` creates the contact before it knows the text is a keyword and stays unedited; the check sits in the open job. START or HELP from a new number still opens a project, and the owner can close it. Affects: sales, customer communication, safety and compliance.

### Claude's Discretion
- Whether the open is an Eloquent model observer, an event listener or a queued job dispatched after commit, how it retries, and how "never two" is enforced on SQLite and Postgres (a lock, a conditional insert or a unique index), within D-02 and D-03.
- The profile field's key name, the plural strings, and the name's length bound.
- How "<word> N" is numbered (the reading from the discussion is the contact's own count, in opening order).
- Placeholder wording for the card section, the timeline entries, the contact screen actions, the room section and the sync refusal, in the `PlainWords` style, with no exclamation points or em dashes (2026-10-17 principle).
- The plain-words body of `rooms/leads/CONTEXT.md` and its `reads` list (folder paths only), from D-16 and D-18.
- The test layout for the room's boot test and the architecture test (D-11).

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Phase scope and requirements
- `.planning/ROADMAP.md` § "Phase 7: The Spine and Leads": goal, five success criteria (criterion 4 reworded per D-22), planning notes
- `.planning/ROADMAP.md` § "Phase 8: Schedule and Booking": criterion 2, the proof named by D-09
- `.planning/REQUIREMENTS.md`: PROJ-01, PROJ-02, ROOM-07, LEAD-05
- `.planning/PROJECT.md` § Key Decisions: DEC-03, "The project is the spine", "One owner per fact", "The room standard and the v2.0 floor plan"
- `CLAUDE.md` § Rules: the chokepoint rule, WCAG 2.2 AA, tests first, the BLUEPRINT "Affects:" rule

### Prior phase decisions (locked)
- `.planning/phases/04-room-standard/04-CONTEXT.md`: D-08 (rooms write only while live), D-09, D-12, D-16, D-19, D-21, D-22, D-23, D-27, and the deferred "Phase 7 discuss" note on `needs`
- `.planning/phases/05-standard-rooms/05-CONTEXT.md`: D-01 (folder names), D-02, D-18 (shared section view)
- `.planning/phases/06-front-desk-and-team-rooms/06-CONTEXT.md`: D-03, D-04, D-05, D-09, D-18
- `.planning/phases/06-front-desk-and-team-rooms/06-REVIEW-DISPOSITION.md`: WR-09
- `.planning/phases/06-front-desk-and-team-rooms/06-UI-REVIEW.md`: keyboard-reachable row links and exact times (D-15)
- `.planning/milestones/v1.0-phases/02-text-front-desk/02-CONTEXT.md`: D-36, D-41, D-42, D-43
- `.planning/milestones/v1.0-phases/02.1-faster-answers/02.1-CONTEXT.md`: D-48, D-49

### Area map and research
- `docs/BLUEPRINT.md`: sections 1, 2, 3, 4, 6, 7, 8 and 10, and the Ideas inbox, updated in this context commit
- `docs/SPEC.md`: the ownership table and kind lists (04 D-14, D-22, D-23); gains the project word list (D-12)
- `docs/research/contractor-operations-2026-10-19.md`: how a piece of work is shaped in four trades

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `App\Actions\RecordInbound` (`app/Actions/RecordInbound.php`): the shared inbound path for texts and web forms. `matchOrCreateContact()` (:256) returns `matched_by` new, phone or email; a new contact is created at :277 with status Lead; the conversation at :369. Everything runs in one transaction (:60). Not edited (D-02).
- `App\Events\CustomerRecordChanged` (`app/Events/CustomerRecordChanged.php`): after-commit, carries only the contact id; its one listener re-renders the card. It does not say whether the contact is new.
- `App\Actions\CloseConversation` / `ReopenConversation`: the house pattern for a close or reopen with a row lock and one timeline note, refusing a duplicate note from a second tab. Model for the project actions (D-11).
- `App\Records\CustomerCard` (`app/Records/CustomerCard.php:60`): renders the card; draws platform activity types itself and room entries through `RoomRegistry::timelineEntry()` (:301). No room owns the card.
- `App\Rooms\Room`, `RoomServiceProvider`, `RoomRegistry`, `RoomSection`: the room descriptor (`handles`, `sections`, `tables`, `claimedWidgets`, `neverLeftOutBecause`); `RoomRegistry::guardClaimedWidgets()` (:329) is where the WR-09 guard goes (D-15). `app/Rooms/Marketing/` is the nearest sibling.
- `App\Icm\Validator::rulesFor()` (`app/Icm/Validator.php:169`; the `business` rules at :172): the `business` type's rules, where the word field is added (D-12).

### Established Patterns
- `NewLeads` (`app/Filament/Widgets/NewLeads.php:28`): Lead contacts created in the last 7 days. `records/open-leads.md` (`app/Records/RecordsWriter.php:229`): Lead contacts with an open conversation. Neither knows about projects; both stay as they are (D-15, D-17).
- Nothing in `app/` sets `ContactStatus::Customer`; contacts have no address column (`database/migrations/2026_10_11_131450_create_contacts_table.php`).
- The partial unique index `conversations_one_open_per_contact` (`database/migrations/2026_10_11_131451_create_conversations_table.php:27`) is how "only one" is enforced on both databases today.

### Integration Points
- `bootstrap/providers.php`: the leads room provider is listed after the existing rooms.
- `examples/example-hvac/rooms/leads/CONTEXT.md` joins the example folder, so `exampleFolderFileCount()` (`tests/Pest.php:293`) moves; `examples/example-hvac/business/profile.md` gains the word (Job); `examples/example-hvac/rooms/marketing/CONTEXT.md` gets the D-19 sentence.
- `app/Filament/Resources/Contacts/Pages/ViewContact.php` and `Schemas/ContactInfolist.php`: the contact screen's project list and actions (D-14).
- Tests that assert card content: `tests/Feature/Records/CustomerCardTest.php`, `CardInjectionTest.php:36`, `tests/Feature/FrontDesk/IntakeAnswersTest.php:126,210`, `tests/Feature/Records/SummaryFilesTest.php`.
- 18 test files write a `profile.md`; those written from scratch gain the field line (D-22).

</code_context>

<specifics>
## Specific Ideas

- A law office's matters, or an HVAC repair while a replacement is being quoted, are the cases for more than one open project at once (D-05).
- The card for a contact with no project renders byte for byte as today (D-13), which keeps most card tests unchanged.

</specifics>

<deferred>
## Deferred Ideas

- **[deferred] Phase 8:** when booking needs a project and the contact has no open one, booking opens one. Affects: operations, sales.
- **[deferred] Phase 8:** a project may close on its own when its work is done (for example its last appointment is marked complete), so a returning customer's new booking does not land on an old job; and when a contact has two open projects, Phase 8 decides which one an appointment goes on. Affects: operations, sales.
- **[deferred] Phase 8:** the `needs` field from the 04 deferred note (D-20). Affects: knowledge, operations.
- **[deferred] Phase 8:** a project's service. The project opens before any intake answer exists, a stored slug goes stale when the owner renames a service file, and filling it later means a listener on `intake_answers`, a front-desk table. Affects: sales, operations.
- **[deferred] Phase 8:** a project's service address. No form field, intake question or contact column holds an address today, so only the owner could fill it; booking is the first step that needs one. Affects: operations, sales.
- **[deferred] Phase 8:** service history by address (BLUEPRINT Ideas inbox), re-pointed to the Phase 8 discuss, where the address first exists. Correction carried with it: an address match under a different name asks a person on a dashboard screen, not on the card, because `records/` cards are read-only (DEC-03). Affects: sales, operations, customer communication, owner intelligence.
- **[deferred] Phase 9:** a business-wide project view that does not depend on the leads room, and a business-wide projects records file. Affects: owner intelligence, sales.
- **[deferred] Phase 8 or backlog 999.1:** what makes a contact a Customer (D-07). Affects: sales, marketing.
- **Before the first client (BLUEPRINT section 6):** an automatic project open can be lost in a crash between the inbound's commit and its dispatch. The same pre-first-client sweep covers it; Phase 7 builds no sweep, because the owner's New action and Phase 8 booking cover a contact with no project. Affects: sales, team, safety and compliance.

### Reviewed Todos (not folded)
- `2026-10-17-phone-design-pass.md` (the phone design pass for the dashboard, the Needs you card and the Business Folder page) belongs to Phase 11 (DSGN); it matched on keywords only. It stays in `todos/pending`.

</deferred>

---

*Phase: 07-the-spine-and-leads*
*Context gathered: 2026-10-29*
