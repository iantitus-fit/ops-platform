# Phase 5: Standard Rooms - Context

**Gathered:** 2026-10-25
**Status:** Ready for planning

<domain>
## Phase Boundary

The first five real rooms join the example business, each built to the Phase 4 room standard and nothing more: company knowledge, marketing, content generation, Google Business Profile and money. Each is a folder under `rooms/` with a `CONTEXT.md` contract, a room module registered in `bootstrap/providers.php`, a dashboard section, and a boot test (empty and with its example folder) on SQLite and Postgres. Google Business Profile adds the first real connector slot, on its fake driver only. Phase 5 also closes G-04-1, the Phase 4 UAT gap where a path containing a line break leaks into a refusal line.

Not in this phase: any agent, any call that sends to Google, payment links or payment records, the front desk and team wraps (Phase 6), wording (the 2026-10-17 wording pass) and layout (the Phase 11 phone design pass).

Requirements: ROOM-08, ROOM-09, ROOM-10, ROOM-11, ROOM-14.

</domain>

<decisions>
## Implementation Decisions

Phase 4 decisions are cited as "04 D-nn" (`.planning/phases/04-room-standard/04-CONTEXT.md`).

### All five rooms
- **D-01:** Folder names are the full room names: `rooms/company_knowledge/`, `rooms/marketing/`, `rooms/content_generation/`, `rooms/google_business_profile/`, `rooms/money/`. Each is the room's key (lower snake case, `app/Rooms/Room.php`). Affects: knowledge, marketing, finance. — **Reversibility:** costly — every client folder and the example business carry these names once the rooms land.
- **D-02:** Company knowledge and marketing become rooms that can never be left out, from this phase (04 D-09: each owns files the sync requires). When a room that can never be left out is absent, the sync gives one line naming the room and the reason (placeholder wording), and skips the per-file "this file belongs to the X room, which this business doesn't use; add rooms/x/ or remove the file" lines for that room's files (`app/Icm/RoomChecks.php:358`). "Or remove the file" is wrong advice for required files, and one missing folder should not produce a line for every file the room owns: the same one-line-per-problem rule as Phase 4's placement check. Any test that has to change because of this is named in the plan and lands in this phase (04 D-16). Content generation, Google Business Profile and money stay optional (04 D-08). Affects: knowledge, marketing.
- **D-03:** Success criterion 1 reads with 04 D-05. Company knowledge and marketing meet "one fictional example file" through the example business's existing files, which their `CONTEXT.md` names, so their room folders may hold only `CONTEXT.md`. Content generation, Google Business Profile and money each get one new fictional file in their own room folder. Company knowledge and marketing can never be empty of the required files they own (`business/profile.md`, `business/hours.md` and `voice/how-we-talk.md`; `business/lead-sources.md`), so their empty boot test keeps those files, and each room's `CONTEXT.md` says so (04 D-06). Affects: knowledge, marketing, finance.
- **D-04:** Each room claims only the types its code handles (04 D-17), taken from the 04 D-12 ownership table. Company knowledge handles `business`, `hours`, `service`, `knowledge` and `voice`. Marketing handles `lead_sources` and `offer`. The content brief (D-12) and the money notes (D-14) are their own new types, defined by their rooms, never `knowledge`: a `knowledge` file belongs to company knowledge wherever it sits (Claude, from 04 D-17, approved by Ian 2026-10-25; type keys are the planner's). Affects: knowledge.
- **D-05:** None of the five rooms has tables, timeline entries or a `records/` output in this phase: `writes_records: none` for all five. Money has no slot, no payment link and no payment records (ROOM-14). Google Business Profile is the only room with a slot. Company knowledge, marketing, content generation and money write `connector: none`, and each `CONTEXT.md` body says the room has no outside system (ROOM-03). The web lead form stays where it is and is not a slot (Claude, from 04 D-19; approved by Ian 2026-10-25). Where the web lead form and other lead intake sit among the rooms is the leads room's call (Phase 7, ROOM-07); marketing does not claim it. Affects: knowledge, marketing, finance.

### Google Business Profile
- **D-06:** The room owns one file in `rooms/google_business_profile/` (file name is the planner's), holding only what Google needs that the folder doesn't already have: `primary_category` and `additional_categories`. It holds no description and no service area, because the business is described in one place. Affects: marketing, knowledge.
- **D-07:** The categories file is checked for shape only. `primary_category` is required text. `additional_categories` is an optional list of text, at most 9 (Google's limit), with no repeats and none equal to the primary, compared ignoring case and extra spaces. Whether a name is one of Google's categories is checked when the real connector lands. An owner who hasn't picked a category leaves the file out; the room is then a valid empty room, and its empty line names the file. Affects: marketing, knowledge.
- **D-08:** The room's `CONTEXT.md` reads every file it will feed Google:
  - `business/profile.md`: name, phone, `service_area`, and the body as the listing description.
  - `business/hours.md`: weekly hours, and closures as Google's holiday hours.
  - `business/services/*.md`: name, `price_policy` and price, as Google's services list.
  - `business/offers/*.md`: live offers, for offer posts later.

  `connector` names the room's slot. Affects: marketing, knowledge, connectors and data.
- **D-09:** The `CONTEXT.md` body says what the room is for: feeding Google's three local ranking factors (relevance, distance, prominence) and turning searchers into customers. The folder is the one source. Google's copy is never written back into the folder, and when the two differ the platform shows the difference. That waits for the real connector, because `direction` and `schedule` wait for CONN-01 (04 D-24). Google's 750-character description limit is checked when the real connector lands, not in this phase. Affects: marketing, connectors and data.
- **D-10:** The example connector file in `connectors/` declares `owns: none` and `reads: none`, each written as the bare word `none`, not `[none]`: the connector check refuses `[none]` as an unknown kind (`tests/Feature/Rooms/ConnectorFileTest.php:125`; only the bare word becomes an empty list, `app/Icm/Loader.php:194`). Google is the truth for no kind of record the platform has; the folder feeds the listing through the room's contract. Not `documents`: ownership is per kind, so owning documents would make Google the truth for every document the business keeps and stop any other connector from owning them. A reviews kind waits for the deferred idea, and only Ian can extend 04 D-22 to add it. Affects: connectors and data, marketing.
- **D-11:** The slot's interface is read-only, with one method that fetches the listing as Google has it. The fake driver returns a fictional listing. It covers the listing only, not reviews: reviews are records, and a reviews kind waits for 04 D-22 with Ian. The fetched listing is not stored as records (the connector file reads none) and is not shown on the dashboard in this phase, because the example business is what the tour shows. Showing where Google differs from the folder comes with the real connector. Methods that send to Google (listing updates, posts, review replies) also come with the real connector, each through a chokepoint that records the owner's approval. Phase 5 sends nothing, so it adds no chokepoint. Affects: connectors and data, marketing, safety and compliance.

### Content generation
- **D-12:** The room owns one content brief in `rooms/content_generation/`: who the business writes for, and the topics it covers and avoids, in plain words. Front matter is only its type, like `faq.md`. A folder holds at most one brief; no brief is the empty room. Tone stays in `voice/how-we-talk.md`, which the room reads. The brief is guidance a model reads, and nothing in it is enforced. A rule that must hold (a word or name never used) goes in `voice/how-we-talk.md` `never_say`, where code checks every draft, and the room's `CONTEXT.md` says so. Affects: marketing, knowledge.
- **D-13:** The room reads six files:
  - `voice/how-we-talk.md`: tone and `never_say`.
  - `business/profile.md`: who the business is.
  - `business/services/*.md`: what it does and its prices.
  - `business/offers/*.md`: the offer sentences code inserts.
  - `business/hours.md`: hours and closures, for time-sensitive posts.
  - `business/faq.md`: the questions customers ask.

  These follow Ian's goal that posts answer price, timing and redemption. The reads list is the room's map; when the agent is built, its own `loads` picks the smallest set it needs. `writes_records: none`, and `connector: none`, with the `CONTEXT.md` body saying the room has no connector (ROOM-10). Affects: marketing, knowledge.
- **D-14 (model role, BLUEPRINT only):** Content generation's model role is mid-tier drafts. Phase 5 adds no agent file, because every new room meets the standard and nothing more (2026-10-21). When its agent is built:
  - A mid-tier model named in its agent file drafts posts and captions. Mid-tier is a capability level, not a provider: the agent file can name any provider DEC-07 allows, including an open-weight model on the business's own hardware or a private GPU (BLUEPRINT Ideas inbox, open-weight models).
  - Prices and offers appear only as the folder's own sentences, inserted by code, never written by the model.
  - Every draft gets the same words check the front desk's model words get (`never_say`, no em dashes, no exclamation points).
  - The owner approves every draft, and publishing goes through a chokepoint that records the approval.
  - No model reviews a draft as a final check.

  Affects: marketing, customer communication, safety and compliance, connectors and data.

### Money
- **D-15:** Money's one example file is a notes file in `rooms/money/`: the owner's plain-words notes on how the business gets paid today (the ways customers pay, who bills, which tool keeps the books), for whoever sets up the money work later (999.1).
  - Front matter is only its type. A folder holds at most one; no notes file is the empty room.
  - Nothing reads it to act in v2.0. It is not what a customer is told: answers about paying go in `business/faq.md`.
  - It may name the accounting tool, but which system is the truth for invoices, payments and expenses is stated only in a connector file's `owns`.
  - It holds no amounts, percents or terms, and never account, routing or card numbers. The room's `CONTEXT.md` says so.
  - The no-secrets scan planned with CONN-01 covers `rooms/money/` as well as connector files.

  This settles the 04 D-05 deferred item. Affects: finance, knowledge, safety and compliance.
- **D-16:** The money notes file's check refuses:
  - a card number: 13 to 19 digits, written together or split by spaces or dashes, that pass the card check digit;
  - any 9-digit number, written together or as 123-45-6789 (a bank routing number, a Social Security number or a tax ID).

  The refusal is in plain words naming the file, for example: "<file> has what looks like a card, bank routing or Social Security number; account, card and ID numbers never go in the folder." The previous version stays live. Phone numbers are recognized with `App\Support\PhoneNumber` and skipped. Bank account numbers have no pattern, so the `CONTEXT.md` warning and the CONN-01 no-secrets scan still cover them. The check applies to the money notes file only. Affects: finance, safety and compliance.

### Dashboard sections
- **D-17:** Each furnished section shows facts from the folder, read from the live version through `ActiveConfig`, with no new tables, no records and no Google data.
  - Company knowledge: the number of services, and whether the FAQ is in.
  - Marketing: the number of lead sources, and the offers live today, decided by the same code that decides which offers the front desk sends (`App\SendRule\OfferSentences::isLive()`), so the two can never disagree.
  - Google Business Profile: the primary category, and a line saying the room is not connected to Google while it runs the fake driver or has no connector file.
  - Content generation and money: whether the brief or the notes are in.

  Affects: knowledge, marketing, finance.
- **D-18:** Every section uses Phase 4's shared section view (`App\Rooms\RoomSection`, `filament.rooms.section`) and the platform-drawn empty line (04 D-21). Wording is a placeholder until the 2026-10-17 wording pass, and layout waits for the Phase 11 phone design pass. No exclamation points or em dashes. Every section passes the axe check (A11Y-01, 04 D-27). The first real room section gets the keyboard-only and VoiceOver walk at Phase 5's UAT, carried from Phase 4; executors record it for UAT rather than pausing. Affects: knowledge, marketing, finance, safety and compliance.

### G-04-1: hidden characters in refusal lines
- **D-19:** Two parts:
  - The Loader refuses, in plain words, any file or folder name containing a character in Unicode's control, format, line separator or paragraph separator categories (Cc, Cf, Zl, Zp: line breaks, tabs, text-direction overrides, zero-width and tag characters), so no other check ever sees one.
  - `App\Icm\ValidationReport::lines()` replaces the same characters in every finished line with a plain-words mark such as `[line break]` or `[hidden character]` (placeholder wording). It runs after `PlainWords::oneLine`, so it covers file prefixes and quoted values such as a room contract's `owns` and `reads` entries, which never pass through the Loader's name check.

  This closes G-04-1 (`.planning/phases/04-room-standard/04-UAT.md`). Affects: knowledge, safety and compliance.

### Settled at plan time (Ian, 2026-10-25, from the research open questions)
- **D-22 (refines D-16):** The money notes check also refuses an employer ID written `12-3456789`, so it covers the tax ID D-16 names; a ZIP+4 written `60601-1234` stays allowed. How the check is built:
  - It matches maximal spans of digits joined by single spaces or dashes and decides on the whole span, never part of one.
  - The phone skip runs on the whole span, a leading `+` included, and never on a span of exactly 9 digits with no leading `+`, since no 9-digit number is a US phone.
  - Card grouping is not tightened to 4-4-4-4 or 4-6-5: any 13 to 19 digit span that passes the card check digit and is not a phone is refused. A false refusal costs the owner a rewrite; a missed card number costs more.

  Affects: finance, safety and compliance.
- **D-26 (refines D-22):** The money notes check refuses every maximal span of exactly 9 digits, however it is grouped (`123 45 6789` and `123-456-789` included), not only the three written forms. A span with a leading `+` is not skipped automatically: it gets the phone test on the whole span (D-22), skipped if `App\Support\PhoneNumber` accepts it and refused if not. The one exception is a ZIP+4 written `60601-1234` (5-4 with a dash). Affects: finance, safety and compliance.
- **D-27 (refines D-06, D-12, D-15):** The sync itself refuses a second file of each of the three single-file types (the Google Business Profile categories file, the content brief, the money notes), wherever the room's contract `owns` points, so the at-most-one rule holds even after the owner edits the contract. The refusal is in plain words naming both files (placeholder wording, for example "<file>: this business already has a content brief in <other>; keep one"), and the previous version stays live. The mechanism lands, tests first, with the first room file type (content generation) and is used by money and Google Business Profile. This replaces the exact-path `owns` as the guarantee and the "for Ian at UAT" notes that flagged it. Affects: knowledge, marketing, finance.
- **D-23:** The two Phase 4 feature tests whose branch real rooms make unreachable move to unit tests. Each branch gets a unit test with an empty `RoomRegistry` (no rooms; no slots), written and passing before its feature test is removed, in the same plan that makes the branch unreachable: company knowledge for `RoomContractTest`'s "no rooms yet", Google Business Profile for `ConnectorFileTest`'s "no connector slots yet". This approves removing those two feature tests only; every other test the rooms change is updated in place, not removed, and each plan names its changes (04 D-16). No registry method for unregistering rooms. Affects: knowledge, connectors and data.
- **D-24:** Success criterion 3 means no behavior change in the offer and lead-source suites. `tests/Feature/Icm/OfferValidationTest.php:85` replaces the literal `14` with `exampleFolderFileCount()`; every assertion about offers and lead sources stays as it is. The same reading covers `tests/Feature/SendRule/JevClassifierTest.php:64`, which pins `exampleFolderFileCount()` to 14: it becomes a check that the live version's file count equals `exampleFolderFileCount()`, so the number lives only in `tests/Pest.php`. Both are named in the plan of the room that moves the count and land in this phase, not Phase 6 (04 D-16). Affects: knowledge, marketing, customer communication.
- **D-25 (confirms D-19):** The name refusal keeps ZWJ (U+200D) and ZWNJ (U+200C) among the refused characters. A client with such a file name renames it; the refusal line says why. Affects: safety and compliance, knowledge.

### Settled after the code review (Ian, 2026-10-25, from `05-REVIEW.md`)
- **D-28 (refines D-22 and D-26):** The money notes check is widened (WR-01).
  - Digit groups are joined by one separator: a dot, any one Unicode dash (the hyphen included), or any run of horizontal whitespace (spaces, tabs, non-breaking spaces, two spaces included) counted as one separator, with at most one line break inside the run. So a markdown hard break (two trailing spaces, then a line break) and a list item whose next line is indented still join; a blank line between two numbers splits them. (Ian, 2026-10-25, clarifying the first wording.)
  - A span over 19 digits is also checked on its own group boundaries: any run of whole groups that makes a card (13 to 19 digits passing the check digit) or exactly 9 digits is refused, unless that run is a whole phone number. So `Paid 2026-10-25 4111 1111 1111 1111` is refused.
  - False refusals stay the accepted cost (D-22). Bank account numbers still have no pattern; the warning in `rooms/money/CONTEXT.md` stays.

  Affects: finance, safety and compliance.
- **D-29 (refines D-27):** Each single-file type declares its default path, the way `Validator::SINGLETONS` does for the required files. When that file is present it is the one kept, so the refusal line always names the file the owner added; if it is absent, the kept file is chosen as before (D-27). The sync still refuses the whole version; this decides only which file the line names. Affects: knowledge, finance, marketing.

### Settled after the review fixes (Ian, 2026-10-26, from `05-REVIEW-FIX.md`)
- **D-30 (refines D-22):** A scan that cannot finish refuses. When the money notes number check cannot finish reading the file (the pattern engine hits one of its limits, which a very long run of digit groups does: about 10,000 groups with PCRE's JIT on, about 50,000 with it off), the sync refuses the file in plain words, `rooms/money/notes.md: this file is too long for the number check to read; shorten it or split it`, and the previous version stays live. Before this, the check found nothing and let the file through, a card in it included. Affects: finance, safety and compliance.
- **D-31 (refines D-28):** A 9-digit number written in groups right after another number, in a span of 19 digits or fewer (`2026-10-25 123 45 6789` is 17), is an accepted miss, like a bank account number. The group-by-group check stays on spans over 19 digits, because checking every multi-group span would refuse ordinary lines, such as a date followed by a phone number, about one time in five. The warning in `rooms/money/CONTEXT.md` and the CONN-01 no-secrets scan cover it. Affects: finance, safety and compliance.

### Settled at UAT (Ian, 2026-10-26, from `05-UI-REVIEW.md`)
- **D-32:** A room section that fails never takes down the dashboard or another section. When building a section's empty line or its lines throws, the section shows one plain line under its own heading (placeholder wording until the wording pass: "This section could not load. The rest of the dashboard is fine.") and the error is reported to the log. It lives once, in `RoomSection`, so every room gets it. Before this, one room's error was a 500 for all of `/admin`, Drafts waiting included, because the sections are not lazy. Affects: knowledge, customer communication, team, safety and compliance.

### Settled at close (Ian, 2026-10-26, from the crash found while testing WR-01)
- **D-33:** The sync refuses any business-folder file that is not valid UTF-8, in plain words naming the file (placeholder wording until the wording pass: `<file>: this file is not saved as plain text (UTF-8); open it and save it as UTF-8`), and the previous version stays live. The check lives in the Loader beside the hidden-character name check (D-19), so no other check ever reads a bad file. Before this, such a file that passed every other check crashed `icm:sync` with a JsonEncodingException when the version was saved: the save rolled back, so nothing went live, but the owner got a crash instead of a line. Affects: knowledge, safety and compliance.

### Build order and docs
- **D-20:** Phase 5 is one phase on `gsd/phase-05-standard-rooms`. If the plan count runs high, the planner proposes a split before writing plans, along the marketing rooms (marketing, content generation, Google Business Profile) and the plain rooms (company knowledge, money), and Ian approves it. A split becomes a decimal phase (5.1) with its own UAT and merge. Later phase numbers don't change, so "Phase 6" keeps meaning the front desk and team wraps. Affects: none (build order only).
- **D-21:** The SPEC ownership table's reader changes from this discuss land in execution with the rooms, as Phase 4's table did (04 D-14), not in the context commit: Google Business Profile as a reader of hours, services and offers, and content generation as a reader of services, offers, hours and faq. So do the new file types in SPEC's file types table. The context commit carries `docs/BLUEPRINT.md`, `.planning/REQUIREMENTS.md` (ROOM-11, and the CONN-01 note) and `.planning/ROADMAP.md` (Phase 5 planning notes marked settled). Affects: knowledge.

### Claude's Discretion
- File names and type keys of the three new files (GBP categories, content brief, money notes), and of the example connector file in `connectors/`.
- The slot's interface name, its one method's name and return shape, and the fake's fictional listing. The slot name follows the 04 refusal example (`google_business_profile`) unless the planner finds a reason not to.
- What company knowledge and marketing list under `reads` (the 04 D-12 table has every room reading `business/profile.md`), and the plain-words body of each room's `CONTEXT.md`.
- Placeholder wording of every new refusal (never-left-out, the categories checks, the number check, hidden-character names) and of the section lines, in the style of `PlainWords`.
- How the never-left-out check is built beside the Phase 4 ownership checks in `App\Icm\RoomChecks`.

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Scope and decisions
- `.planning/ROADMAP.md` § Phase 5: Standard Rooms: goal, four success criteria, and the planning notes this file settles
- `.planning/REQUIREMENTS.md`: ROOM-08 to ROOM-11 and ROOM-14; ROOM-01 to ROOM-05 (the standard); CONN-07; CONN-01 (Future, the no-secrets scan D-15 extends); A11Y-01
- `.planning/PROJECT.md` § Key Decisions: model roles (2026-10-18), the room standard and the v2.0 floor plan (2026-10-21), one owner per fact (2026-10-19), the chokepoint rule (2026-10-22), the closing principle (2026-10-17), DEC-03, DEC-07, DEC-10
- `.planning/phases/04-room-standard/04-CONTEXT.md`: D-01 to D-27, especially D-05, D-06, D-08, D-09, D-12, D-14 to D-17, D-21 to D-25, D-27
- `.planning/phases/04-room-standard/04-UAT.md`: G-04-1 (closed by D-19), and the keyboard and VoiceOver walk carried into this phase's UAT

### Architecture docs
- `docs/SPEC.md` § The business folder, § Who owns each file, § Kinds of record a connector may own, § Kinds of record the platform owns, § File types: the room and connector contracts, and the table the D-21 reader edits land in
- `docs/BLUEPRINT.md`: Marketing, Finance, Knowledge, Connectors and data, Safety and compliance, Ideas inbox; the Affects rule
- `CLAUDE.md`: rules (tests first for the sync, Laravel concept walk-throughs, chokepoint rule, Affects lines)

### Code the rooms plug into
- `app/Rooms/Room.php`, `app/Rooms/RoomServiceProvider.php`, `app/Rooms/RoomRegistry.php`, `app/Rooms/RoomSection.php`, `app/Rooms/RoomFileType.php`: the room standard
- `tests/Rooms/PretendRoom/`: the worked example of every part (file type, slot with fake driver, section, provider)
- `app/Icm/RoomChecks.php`: ownership, contract and connector checks; where never-left-out sits
- `app/Icm/Loader.php`: `TYPED`, `NONE_MEANS_EMPTY` (`:52`, `:194`), where the D-19 name refusal goes
- `app/Icm/Validator.php`: `SINGLETONS` (the seven required files behind D-02)
- `app/Icm/ValidationReport.php`: `lines()` (`:39`), the D-19 print-time mark
- `app/Icm/PlainWords.php`: `oneLine()` (`:63`) and refusal style
- `app/Icm/ActiveConfig.php`: `hasRoom()`, `roomContract()`, `roomOwnedFiles()`, `filesOfType()`, `offers()`, `services()`, `leadSources()`
- `app/SendRule/OfferSentences.php`: `isLive()` (`:47`), reused by the marketing section (D-17)
- `app/Support/PhoneNumber.php`: `toE164()`, used to skip phone numbers in the D-16 check
- `app/Channels/TextChannel.php`, `app/Channels/FakeTextChannel.php`, `app/Providers/AppServiceProvider.php`: the slot pattern
- `bootstrap/providers.php`: where the five real room providers register, after `AppServiceProvider`
- `tests/Pest.php`: `exampleFolderFileCount()` (`:291`), the one test number a landing room may change (04 D-16)
- `tests/Feature/Rooms/ConnectorFileTest.php`: connector checks, including `[none]` refused (`:125`)
- `examples/example-hvac/`: the example business every test copies

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `RoomServiceProvider` plus `Room`: a real room is one provider under `app/Rooms/<Room>/` whose `room()` returns the description. The pretend room's provider shows a slot bound the `TextChannel` way.
- `RoomSection`: the shared view, `canView()` through `ActiveConfig::hasRoom()`, the empty line built from the contract, and `contentLines()` for the furnished lines D-17 names.
- `RoomFileType`: how a room defines a new kind of file (GBP categories, content brief, money notes).
- `OfferSentences::isLive()`: the single answer to "is this offer live today", already used by the front desk.
- `PhoneNumber::toE164()`: phone recognition for the D-16 skip.
- `tests/Rooms/RoomSectionAxe.php`: the axe check every section runs (A11Y-01).
- `tests/Rooms/SpecTables.php`: the SPEC-to-code check the D-21 reader edits must keep passing.

### Established Patterns
- `Loader::TYPED` holds today's types. Room types arrive through `RoomRegistry::registeredTypes()`, so new types never edit `TYPED` (04 D-19).
- Refusals have the shape `{file, field, message}` with plain words, and a refused sync keeps the previous version live.
- A room is on when its `rooms/<room>/CONTEXT.md` loads as a typed room file (`RoomChecks::isPresent()`); an absent room hides its section.
- Tests copy `examples/example-hvac`; CI runs SQLite and Postgres on every push.

### Integration Points
- `bootstrap/providers.php`: five new room providers.
- `RoomChecks`: the never-left-out check for company knowledge and marketing (D-02).
- `Loader`: the hidden-character name refusal (D-19).
- `ValidationReport::lines()`: the print-time mark (D-19).
- `examples/example-hvac/rooms/` and `examples/example-hvac/connectors/`: the five contracts, three new files and the GBP connector file; `exampleFolderFileCount()` moves accordingly.

</code_context>

<specifics>
## Specific Ideas

- Number-check refusal, placeholder: "<file> has what looks like a card, bank routing or Social Security number; account, card and ID numbers never go in the folder."
- Hidden-character marks, placeholder: `[line break]`, `[hidden character]`.
- The GBP `CONTEXT.md` body names Google's three local ranking factors: relevance, distance, prominence.
- Posts later answer price, timing and redemption, which is why content generation reads hours, offers and faq (D-13).
- New Laravel concepts this phase may introduce: registering more than one real provider in `bootstrap/providers.php`, and a contract interface bound to a fake driver in a real room. Explain each at first use and log it in the walk-through log (CLAUDE.md).

</specifics>

<deferred>
## Deferred Ideas

- **What a good Google profile needs** (Ian, 2026-10-25), for the BLUEPRINT Ideas inbox:
  - verified ownership and identical NAP everywhere (an optional address in `profile.md` for a storefront business; directories have no connector);
  - primary and secondary categories;
  - the description, with the vital part in its first 250 characters;
  - services with price or scope;
  - fresh photos and video with no text overlays, with a monthly reminder;
  - asking for reviews and replying to every one (review asks are in 999.3, replying is reputation, and a reviews kind would extend 04 D-22);
  - regular posts with Book or Call buttons, drafted by content generation for the owner's approval;
  - listing updates queued for approval when hours, closures or the service area change in the folder.

  Every send to Google goes through a chokepoint with the owner's approval recorded. Affects: marketing, knowledge, connectors and data, safety and compliance, customer communication.
- **Hidden characters inside file values** (Ian, 2026-10-25), for the BLUEPRINT Safety and compliance open items: the same characters D-19 refuses in names pass the sync today inside values (a service name, for example) and can reach agent prompts. Whether the sync refuses them in every value is its own decision. Affects: safety and compliance, knowledge.
- **A reviews kind of record:** waits for the profile idea above; extending 04 D-22 is Ian's call.
- **Showing where Google's listing differs from the folder, and the 750-character description check:** with the real Google connector and CONN-01.
- **Content generation's agent file:** when the room's agent is built, under D-14.

### Reviewed Todos (not folded)
- "Phone design pass for the dashboard, the Needs you card and the Business Folder page" (`.planning/todos/pending/2026-10-17-phone-design-pass.md`): belongs to Phase 11, as Phase 4 found; not re-asked.

</deferred>

---

*Phase: 05-standard-rooms*
*Context gathered: 2026-10-25*
