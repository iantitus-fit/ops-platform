# Phase 4: Room Standard - Context

**Gathered:** 2026-10-22
**Status:** Ready for planning

<domain>
## Phase Boundary

The standard every room is built to, proven end to end before any real room uses it. A room is a folder under `rooms/` in the business folder with a `CONTEXT.md` contract the sync checks; a Laravel module that registers its file types, tables, dashboard section and timeline entries; an optional connector slot (interface plus fake driver); a statement of what it writes to `records/`; and one boot test, empty and with its example folder, on SQLite and Postgres. The sync also starts checking connector files (`owns`, `reads`, `slot`) and refuses two owners for one kind of record.

No real room is built here. The first real rooms land in Phase 5 (company knowledge, marketing, content generation, Google Business Profile, money), the front desk and team wraps in Phase 6. Phase 4 proves the standard with a pretend room that lives only under `tests/`.

Requirements: ROOM-01, ROOM-02, ROOM-03, ROOM-04, ROOM-05, CONN-07.

</domain>

<decisions>
## Implementation Decisions

### Room folder and contract
- **D-01:** Room folders live under `rooms/<room>/` in the business folder (for example `rooms/marketing/`). The top level stays as it is (`business/`, `rules/`, `voice/`, `agents/`, `connectors/`, `tests/`). Affects: knowledge. — **Reversibility:** costly — every client folder and the example business are laid out this way once rooms land.
- **D-02:** A room's `CONTEXT.md` is a contract the sync checks: front matter with `type: room` naming the files the room owns, the ones it reads, what it writes to `records/` (or none) and its connector slot (or none); the body stays plain words for people and AI. Field names are the planner's call within that list. Affects: knowledge.
- **D-03:** The Loader change is narrow. Today every file named `CONTEXT.md` is a router (`app/Icm/Loader.php` `ROUTERS`) and its front matter is never read. Only a `CONTEXT.md` directly in `rooms/<room>/` is parsed as `type: room`. `CLAUDE.md`, `CONTEXT.md` and `START-HERE.md` everywhere else (folder root, `business/CONTEXT.md`, `business/services/CONTEXT.md`, `rules/CONTEXT.md`) stay routers, still fingerprinted into the version as today (`app/Icm/FolderSync.php`). Affects: knowledge.
- **D-04:** New kinds of file (ones that don't exist today, such as a Google Business Profile posting plan or a content brief) live in their room's folder. Today's files stay where they are, by kind (locked 2026-10-21). Affects: knowledge, marketing.
- **D-05:** "One fictional example file": rooms whose files already exist by kind (front desk, team, company knowledge, marketing) meet it through the example business's existing files, which their `CONTEXT.md` names; their room folder may hold only `CONTEXT.md`. The money room has no existing files, so its example is one new fictional file in `rooms/money/`, chosen in Phase 5 so that it carries no money rules backlog 999.1 would have to rework. The example business still shows every room furnished, money included. Affects: knowledge, finance.
- **D-06:** "Empty" in the boot test means the room's own files are absent, booted against a base folder that keeps the seven files the sync requires (profile, hours, lead-sources, voice, sending, replies, triage; `app/Icm/Validator.php` `SINGLETONS`). A room that owns one of those can never be empty of it, and its `CONTEXT.md` says so. The required list does not change in Phase 4. Affects: knowledge.
- **D-07:** A `rooms/<name>/` the engine has no room for is refused in plain words naming the folder and the rooms that exist; the previous version stays live (the same way an unknown file type is refused today). Affects: knowledge.
- **D-08:** An absent `rooms/<name>/` means the business doesn't use that room: its dashboard section is hidden, its module is idle (no timers or jobs run, no timeline entries appear). Adding the folder turns the room on at the next sync. Which rooms a business uses is its own call (2026-10-19). Affects: knowledge, customer communication, marketing, sales, operations, finance, team, owner intelligence.
- **D-09:** Rooms that can never be left out: the front desk; any room that owns one of the seven required files; and any room the front desk needs to run. That is the team room (owner alerts, and the life-safety alert goes out at any hour, can never go idle) and the owner intelligence room too if the customer card the front desk loads (DEC-03) is rendered there. With D-12 and ROOM-09, company knowledge and marketing are required as well. The sync names the room and the reason. Each room's never-left-out check starts in that room's phase, like D-15. Phase 4 requires no room, so the example folder and every test pass with no rooms/ folder. Affects: customer communication, team, knowledge, marketing, owner intelligence.
- **D-10:** A file owned by an absent room is refused in plain words naming the file and the room ("business/offers/first-visit-promotion.md belongs to the marketing room, which this business doesn't use; add rooms/marketing/ or remove the file"); the previous version stays live. Affects: knowledge, marketing.

### Who owns today's files
- **D-11:** Every file has exactly one owner: a room or the house. The house owns the routers (`CLAUDE.md`, `START-HERE.md`, the folder `CONTEXT.md` files outside `rooms/`) and the walk tests in `tests/`. `README.md` files, `changelog.md` and `records/` are skipped by the sync and need no owner. ROOM-01's wording changes to match (done in the context commit). Affects: knowledge.
- **D-12:** The ownership table:

  | File | Owner | Readers named so far |
  |---|---|---|
  | `business/profile.md` | company knowledge | every room |
  | `business/hours.md` | company knowledge | front desk (next open), schedule and booking |
  | `business/services/*.md` | company knowledge | front desk (intake, price sentences), schedule (duration, book_online) |
  | `business/faq.md` | company knowledge | front desk |
  | `voice/how-we-talk.md` | company knowledge | front desk, content generation, Google Business Profile |
  | `business/lead-sources.md` | marketing | leads, front desk (ROOM-09) |
  | `business/offers/*.md` | marketing | leads, front desk (ROOM-09) |
  | `rules/sending.md` | front desk | team (reads `owner_alert`) |
  | `rules/replies.md` | front desk | team (reads `owner_name`) |
  | `rules/triage.md` | front desk | |
  | `agents/*.md` | the room the agent serves (`agents/front-desk.md`: front desk) | team (reads `escalate_to` in `agents/front-desk.md`) |
  | `connectors/*.md` | the room whose slot the file fills (the text channel's: front desk; Google Business Profile's: its room) | |
  | routers, `tests/*.md` | the house | |

  Schedule and booking owns no file today and stays optional. Leads, content generation, Google Business Profile, owner intelligence, money and team own none of today's files. Affects: knowledge, customer communication, marketing, sales, operations, team.
- **D-13:** `rules/sending.md` is not split: front desk owns it, team reads `owner_alert`. Splitting `owner_alert` into a team-owned file is deferred. Affects: customer communication, team.
- **D-14:** In Phase 4 the table is written in `docs/SPEC.md` beside the folder tree (during execution, not in the context commit). Once a room lands, its `CONTEXT.md` front matter is the record the sync enforces; the SPEC row is the map; the room's boot test fails if the two disagree. `docs/BLUEPRINT.md` links to the table rather than copying it. Affects: knowledge.
- **D-15:** Ownership is enforced as each room lands. Two rooms claiming one file is refused from Phase 4. A file whose owning room is built in code but absent from the folder is refused from that room's phase (D-10). A file whose room isn't built yet passes. By Phase 9 every typed file is checked, with nothing to switch on at the end. Affects: knowledge.
- **D-16:** When a room lands, its folder joins `examples/example-hvac`, which every test copies. The only test change that allows is the number in `exampleFolderFileCount()` (`tests/Pest.php`). Any other test that has to change because of the ownership check is named in that room's plan and lands in that room's phase, never in the Phase 6 front desk and team wraps. Affects: knowledge, customer communication, team.
- **D-17:** A room may claim only files of the types its code registers. An owner editing `rooms/marketing/CONTEXT.md` to claim `business/faq.md` is refused in plain words ("business/faq.md is a knowledge file, which the company knowledge room handles; marketing can read it"). Moving a kind of file between rooms is a code change, so `CONTEXT.md` and the code can't drift. Affects: knowledge.

### Code home and first proof
- **D-18:** `app/Rooms/` is approved as a new `app/` folder (Ian). Add its row to the CLAUDE.md Map when Phase 4 creates it. This settles the folder only: the module shape inside it (a service provider per room, a modules package, or Filament clusters) is researched at plan time, as scoped 2026-10-21. Anything that needs a new composer package or its own top-level folder goes back to Ian. Migrations stay in `database/migrations`. Affects: none (code layout only).
- **D-19:** Code that exists today stays where it is, by kind, and the room points at it; code a room phase writes goes in `app/Rooms/<Room>/`. The room contract must allow a room's registrations to point at classes outside `app/Rooms/<Room>/` (the front desk's `TextChannel` slot stays in `app/Channels`). This settles the Phase 6 planning note "whether a wrap moves code into the room's module". Affects: customer communication, team.
- **D-20:** The standard is proven with a pretend room that exists only in tests and exercises every part: one file type with an example file, one table, a dashboard section, a timeline entry, and a connector slot with a fake driver plus a connector file declaring `owns` and `reads`. A second fixture connector claiming the same kind proves the refusal. Its code and folder live under `tests/`. It is registered from the test setup, never from `bootstrap/providers.php`, and never goes in `app/Rooms/` or `examples/example-hvac/`, so nothing ships, the tour never shows it, and `exampleFolderFileCount()` doesn't move. Its table's migration loads only in tests. Phase 4 shows all five success criteria end to end with it. Affects: none (test code only).
- **D-21:** An empty room's dashboard section shows one plain line when the room owns files and none of them are there yet. The line names those files from the room's `CONTEXT.md`, wherever they live (money's are in `rooms/money/`, other rooms' may not be). A room that owns no files (team in v2.0) is never shown as empty; its section shows whatever records it has. The platform renders the line from `CONTEXT.md` the same way for every room, so no room builds its own empty screen and one test covers it. The wording is a placeholder, settled with the rest of the wording once the full workflow exists (closing principle, 2026-10-17), and laid out in the phone design pass (Phase 11). No exclamation points or em dashes. Affects: knowledge.

### What a connector can own
- **D-22:** The kinds a connector may own or read are one fixed list in code, keyed exactly as the 2026-10-19 decision names them: customers, sales activity, invoices, payments, expenses, work orders, the schedule, documents (key spelling such as `sales_activity` is the planner's, held to those eight). The code list is what the sync enforces. `docs/SPEC.md` lists the same eight beside the ownership table (in Phase 4 execution), and a test fails if the two disagree (the D-14 pattern). `owns` and `reads` check against the same list; a word on neither list is refused in plain words naming the file and listing the kinds. A kind on the list is accepted even when the platform keeps no records of it yet (invoices in v2.0): owning it says the outside system is the truth, and the platform never treats its own copy as truth. Ownership stays per kind; per-field waits for a client (2026-10-19). Affects: connectors and data. — **Reversibility:** costly — key names end up in every client's connector files.
- **D-23:** Projects, texts and the timeline go on a second list in the same code: kinds the platform owns, which a connector may read but never own. The refusal names the file and the kind and says the platform owns it. Texts stay the platform's for a locked reason: the sent message is the platform's own row (Phase 2 D-28/D-54) and every automated send goes through `OutboundSender` (02.1 D-49), so the text channel's connector file owns nothing. Other platform records (conversations, owner alerts, drafts) are on neither list and are refused like any unknown word. `reads: projects` is accepted from Phase 4 even though the Project record lands in Phase 7 (same rule as invoices). Moving a platform kind onto the ownable list is its own decision with Ian. SPEC lists both lists, tied to the code by the same test. Affects: connectors and data, customer communication.
- **D-24:** `connector` moves from a later-sprint type to a checked type. Phase 4 checks `owns`, `reads` and `slot` only (CONN-07), with plain-words errors. `system`, `direction` and `schedule` are allowed and passed through unchecked; SPEC's connector row says which fields Phase 4 checks and which wait for CONN-01. CONN-01 stays unchanged in Future Requirements and checks `system`, `direction`, `schedule` and the no-secrets scan together, with the first real connector. `schedule` has no defined format yet, so Phase 4 doesn't invent one. Affects: connectors and data.
- **D-25:** The slot link: the room's `CONTEXT.md` connector field names its slot (or none); the connector file's `slot` field says which slot it fills; slot names are registered by room code. That is the only link: the room's `CONTEXT.md` does not also list its connector file under `owns`, so ownership is stated once (the SPEC table row reads "the connector file filling the slot"). The sync refuses, in plain words naming the file: a slot no present room has, a slot name no room registers, and two files filling one slot. A room with a slot and no connector file is a valid empty room. `slot` is checked from Phase 4 alongside `owns` and `reads`, because ROOM-01 ownership depends on it. The front desk's text slot runs from `.env` today (`channels.text.driver`) with no connector file, which this allows; Phase 6 decides whether to add one. Affects: connectors and data.
- **D-26:** The two-owner refusal uses the success-criterion wording: "customers is owned by both connectors/a.md and connectors/b.md; pick one, the other can read it". The previous version stays live. Affects: connectors and data.

### Claude's Discretion
- How rooms add timeline entries, given `App\Enums\ActivityType` is one fixed list today; researched and planned.
- How a room registers its file types with the sync while today's types stay in `Validator`/`Loader::TYPED` (D-19 lets existing types stay put); the module shape inside `app/Rooms/` (D-18).
- Exact front matter field names in the room contract (D-02) and the `writes_records` shape (list of `records/` paths or none).
- Plain-words wording of the new refusals, in the style of the existing `PlainWords` messages; placeholder wording is fine (2026-10-17 principle).

### Context commit (Ian, 2026-10-22)
Beside this file and the discussion log, the same single commit carries: `docs/BLUEPRINT.md` (each decision's Affects line and the area edits it names, the empty-room bullet in the room standard block, the spine and one-owner-per-fact bullets moved from Decided to Phase at Phases 7 and 4); `.planning/REQUIREMENTS.md` (ROOM-01 "a room or the house"; the file-count exception on ROOM-06 and ROOM-15); `.planning/ROADMAP.md` (the Phase 6 note marked settled by this file). The SPEC ownership table and the two kind lists land in Phase 4 execution with the test that ties them to code.

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Scope and decisions
- `.planning/ROADMAP.md` § Phase 4: Room Standard — goal, five success criteria, planning notes this file settles
- `.planning/REQUIREMENTS.md` — ROOM-01 to ROOM-05, CONN-07 (and ROOM-06, ROOM-15 for D-16/D-19); CONN-01 stays in Future Requirements
- `.planning/PROJECT.md` § Key Decisions — "The model home is the next milestone", "One owner per fact" (2026-10-19), "The room standard and the v2.0 floor plan" (2026-10-21), the closing principle (2026-10-17), DEC-03, DEC-10
- `.planning/milestones/v1.0-phases/02-text-front-desk/02-CONTEXT.md` — D-28, D-54 (sent message is the platform's row), cited by D-23
- `.planning/milestones/v1.0-phases/02.1-faster-answers/02.1-CONTEXT.md` — D-49 (every automated send goes through OutboundSender), cited by D-23

### Architecture docs
- `docs/SPEC.md` § The two layers, § The business folder, § File types — folder tree and front matter contract; the ownership table and both kind lists are added here in Phase 4
- `docs/BLUEPRINT.md` — the room standard block and area "Where it stands" lines; the Affects rule
- `CLAUDE.md` — Map (add `app/Rooms/`), rules (tests first for the sync, Laravel concept walk-throughs)

### Code the standard plugs into
- `app/Icm/Loader.php` — `TYPED`, `LATER_SPRINT` (`connector` moves out), `ROUTERS` (narrow change, D-03)
- `app/Icm/Validator.php` — `SINGLETONS` (the seven required files, D-06), `rulesFor()`, plain-words errors
- `app/Icm/FolderSync.php` — fingerprint and reject-keeps-previous-version path
- `app/Icm/PlainWords.php` — refusal message style
- `app/Channels/TextChannel.php`, `app/Channels/FakeTextChannel.php`, `app/Providers/AppServiceProvider.php` — the connector slot pattern
- `app/Enums/ActivityType.php`, `app/Models/Activity.php` — the timeline today
- `app/Filament/Widgets/` — dashboard sections today
- `tests/Pest.php` — `exampleFolderFileCount()` (D-16)
- `examples/example-hvac/` — the example business every test copies

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `TextChannel` interface + `FakeTextChannel` + driver picked from config in `AppServiceProvider`: the connector slot pattern, copied as-is for the pretend room's slot.
- `PlainWords` and `Validator::error()`: every new refusal (unknown room, absent owner room, double claim, unknown kind, platform kind, slot problems) uses the same shape `{file, field, message}`.
- `FolderSync` reject path: a refused folder already keeps the previous version live and logs to `changelog.md`; new checks only add errors to the report.
- `exampleFolderFileCount()` in `tests/Pest.php`: the one test number a landing room may change.

### Established Patterns
- File types are hard-coded today (`Loader::TYPED`, `Validator::rulesFor()` match). Nothing plugs in from outside yet; the room module must add a registration path without moving today's types (D-19).
- Router files (`ROUTERS`) are kept raw, fingerprinted and never validated.
- Singletons (`Validator::SINGLETONS`) enforce exactly-one files with plain-words messages; ownership checks are the same kind of whole-folder check (`duplicateSlugErrors`, `singletonErrors` sit beside them).
- Tests copy `examples/example-hvac`; CI runs SQLite and Postgres on every push.

### Integration Points
- `Loader::load()` — recognising `rooms/<room>/CONTEXT.md` as `type: room`, and `connector` as a checked type.
- `Validator::validate()` — whole-folder checks for rooms, ownership and connectors.
- `bootstrap/providers.php` — where real room modules register (never the pretend room).
- Filament panel widgets and `ActivityType` — where room dashboard sections and timeline entries plug in.

</code_context>

<specifics>
## Specific Ideas

- Refusal examples Ian accepted: "business/offers/first-visit-promotion.md belongs to the marketing room, which this business doesn't use; add rooms/marketing/ or remove the file"; "business/faq.md is a knowledge file, which the company knowledge room handles; marketing can read it"; "connectors/gbp.md fills the google_business_profile slot, but the Google Business Profile room is not in this folder"; "customers is owned by both connectors/a.md and connectors/b.md; pick one, the other can read it".
- Empty-room line, placeholder: "Money: nothing set up yet. Add a file to rooms/money/ to start." No exclamation points or em dashes.
- New Laravel concepts expected: service providers (how a room registers itself) and boot order. Explain at first use and log in the walk-through log (CLAUDE.md).

</specifics>

<deferred>
## Deferred Ideas

- **Phase 7 discuss:** a room that needs another room's records (booking attaches to a project, and only leads opens one), so the room contract may need a `needs` field and a sync refusal when the room it needs is absent. Adding the field later is additive, so Phase 4 doesn't wait on it.
- **Later call:** split `owner_alert` out of `rules/sending.md` into a team-owned file (D-13).
- **Phase 6:** whether the front desk's text slot gets a connector file (D-25).
- **Phase 5:** the money room's one fictional example file, chosen so it carries no money rules 999.1 would rework (D-05).

### Reviewed Todos (not folded)
- "Phone design pass for the dashboard, the Needs you card and the Business Folder page" (`2026-10-17-phone-design-pass.md`): belongs to Phase 11, the phone design pass.

</deferred>

---

*Phase: 04-room-standard*
*Context gathered: 2026-10-22*
