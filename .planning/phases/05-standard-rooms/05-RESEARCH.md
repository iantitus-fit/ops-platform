# Phase 5: Standard Rooms - Research

**Researched:** 2026-10-25
**Domain:** Laravel 13 / Filament 5 room modules on the Phase 4 room standard; ICM folder sync (Loader, Validator, RoomChecks); plain-words refusals
**Confidence:** HIGH for the codebase findings (read this session, and checked by a throwaway prototype that ran the full suite); MEDIUM for the filesystem behavior outside this Mac

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

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
- **D-11:** The slot's interface is read-only, with one method that fetches the listing as Google has it. The fake driver returns a fictional listing. It covers the listing only, not reviews: reviews are records, and a reviews kind waits for D-22 with Ian. The fetched listing is not stored as records (the connector file reads none) and is not shown on the dashboard in this phase, because the example business is what the tour shows. Showing where Google differs from the folder comes with the real connector. Methods that send to Google (listing updates, posts, review replies) also come with the real connector, each through a chokepoint that records the owner's approval. Phase 5 sends nothing, so it adds no chokepoint. Affects: connectors and data, marketing, safety and compliance.

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

### Build order and docs
- **D-20:** Phase 5 is one phase on `gsd/phase-05-standard-rooms`. If the plan count runs high, the planner proposes a split before writing plans, along the marketing rooms (marketing, content generation, Google Business Profile) and the plain rooms (company knowledge, money), and Ian approves it. A split becomes a decimal phase (5.1) with its own UAT and merge. Later phase numbers don't change, so "Phase 6" keeps meaning the front desk and team wraps. Affects: none (build order only).
- **D-21:** The SPEC ownership table's reader changes from this discuss land in execution with the rooms, as Phase 4's table did (04 D-14), not in the context commit: Google Business Profile as a reader of hours, services and offers, and content generation as a reader of services, offers, hours and faq. So do the new file types in SPEC's file types table. The context commit carries `docs/BLUEPRINT.md`, `.planning/REQUIREMENTS.md` (ROOM-11, and the CONN-01 note) and `.planning/ROADMAP.md` (Phase 5 planning notes marked settled). Affects: knowledge.

### Claude's Discretion
- File names and type keys of the three new files (GBP categories, content brief, money notes), and of the example connector file in `connectors/`.
- The slot's interface name, its one method's name and return shape, and the fake's fictional listing. The slot name follows the 04 refusal example (`google_business_profile`) unless the planner finds a reason not to.
- What company knowledge and marketing list under `reads` (the 04 D-12 table has every room reading `business/profile.md`), and the plain-words body of each room's `CONTEXT.md`.
- Placeholder wording of every new refusal (never-left-out, the categories checks, the number check, hidden-character names) and of the section lines, in the style of `PlainWords`.
- How the never-left-out check is built beside the Phase 4 ownership checks in `App\Icm\RoomChecks`.

### Deferred Ideas (OUT OF SCOPE)

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
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| ROOM-08 | Company knowledge room, to the room standard | `Room(handles: business, hours, service, knowledge, voice)`; contract owns the five D-12 rows; never-left-out check (D-02); the `business/services/*.md` router trap (Pitfall 1); the example-business suite fallout (Q1) |
| ROOM-09 | Marketing room; owns `business/lead-sources.md` and `business/offers/*.md` | `Room(handles: lead_sources, offer)`; section reads `OfferSentences::liveToday()`; the existing offer and lead-source suites pass unchanged except two hard-coded `14`s (Pitfall 6) |
| ROOM-10 | Content generation room, no connector | new `content_brief` type; exact-path owns gives "at most one" and the empty line for free (Pattern 3); `connector: none` |
| ROOM-11 | Google Business Profile room with slot (interface plus fake), one categories file, connector file `owns: none` `reads: none` | new `gbp_categories` type; read-only slot bound the `TextChannel` way (Pattern 4); `none` is the bare word (Loader NONE_MEANS_EMPTY); connector fallout (Q1 table) |
| ROOM-14 | Money room to the full standard, no slot, no payment records | new `money_notes` type; D-16 number check (Q2); no tables so nothing DB-specific for Postgres |
| (carried) G-04-1 | Hidden characters in refusal lines | Q3: PCRE `\p{Cc}\p{Cf}\p{Zl}\p{Zp}` needs no package; three leaks the plan must close (Pitfalls 3, 4, 5) |
</phase_requirements>

## Summary

Phase 5 is five thin rooms plus three pieces of shared sync work. Each room is one provider under `app/Rooms/<Room>/`, one `RoomSection` subclass, one contract and (for three rooms) one new file type, copied from the pretend room. None has a table, a timeline entry or a model, so nothing is database-specific and the SQLite and Postgres boot runs differ only by the existing CI matrix. The shared work is the D-02 never-left-out check, the D-19 hidden-character refusal and mark, and the fallout in the existing suite when real rooms and a real GBP connector file join `examples/example-hvac`.

The most useful thing this research did was run the whole job in a throwaway copy of the repo (outside the repo, in the scratchpad): five providers registered, the example folder furnished, D-02 and D-19 coded in minimal form, `exampleFolderFileCount()` raised from 14 to 23. The full suite went from 1981 passed to 43 failing cases in 10 test files, and one more hard-coded count in an 11th (the prototype had already edited it), so 44 cases in 11 files. None of the 43 is caused by the never-left-out check itself (no existing test removes a room folder that does not exist yet). They are caused by real rooms handling file types, real rooms being registered, a real connector slot and file existing, and the example folder growing. Section "Question 1" lists every one by file and line, with the cause, so each can be assigned to the plan whose room causes it.

Two findings change the design, not just the test list. First, `business/services/*.md` cannot be listed in company knowledge's `owns` as `RoomChecks` stands: the pattern matches `business/services/CONTEXT.md` (a router) and the claim is refused as "belongs to the house" (Pitfall 1). Second, owning the three new files by exact path (not `rooms/<room>/*.md`) makes D-06, D-12 and D-15 "at most one file" and D-07 "its empty line names the file" true with no new folder-level check (Pattern 3).

**Primary recommendation:** One phase, about 9 plans in 4 waves like Phase 4 (shared checks tests-first, then company knowledge with the router fix, then marketing, content generation, money, then Google Business Profile last because it carries the connector fallout); no new composer or npm package for D-16 or D-19; hand-roll the Luhn check (about 8 lines, the only copy in `vendor/` is Faker, a dev dependency).

## Project Constraints (from CLAUDE.md)

- Files hold how the business runs; the database holds what happened. Code reads business configuration only through `App\Icm\ActiveConfig` (also `.ai/rules/app.md`). Dashboard sections and the marketing offers count therefore read through `ActiveConfig` and `OfferSentences`, never the folder.
- Money is integer cents. Phase 5 adds no money column or amount.
- Tests first for anything touching money, sending or the sync. Run `php artisan test` before calling a step done. Under GSD `tdd_mode` is true and `test_command` is `php artisan test` (`.planning/config.json`).
- Sending authority is enforced in code. Phase 5 sends nothing and adds no chokepoint (D-11); a plan that adds an agent action must name its chokepoint, so none does.
- Ian reads code with AI help. First use of a Laravel concept gets a plain-sentence explanation and one line in the walk-through log at the end of the current sprint file in `docs/sprints/` (the newest is `docs/sprints/sprint-1.1.md`). New concepts this phase: more than one real provider in `bootstrap/providers.php`, and a contract interface bound to a fake driver in a real room.
- Git under GSD: local commits, one per task, on `gsd/phase-05-standard-rooms`; never push, merge or rewrite history.
- No secrets in the repo or ICM folders; examples and test data are fictional (the money notes example holds no numbers).
- WCAG 2.2 AA fixed in the markup; every image has alt text; keyboard-only and screen reader walk at UAT. Executors record hand checks for UAT and never pause (memory: hand checks go to UAT).
- Any decision, plan or idea that changes an area gets an "Affects:" line, and `docs/BLUEPRINT.md` is updated in the same commit.
- Do not change dependencies without approval; do not create base folders without approval; do not delete tests or test files without approval (Boost rules in `CLAUDE.md`). This matters for two tests whose branch becomes unreachable (Open Question 4).
- Pest, `php artisan make:` commands with `--no-interaction`, `vendor/bin/pint --dirty --format agent` after PHP edits.
- `tests/Unit/ArchitectureTest.php` forbids `file_get_contents`, `file_put_contents`, `fopen`, `file`, `glob`, `scandir`, `parse_ini_file` in `App\Rooms`, `App\Support`, `App\Filament` and others, forbids `env()` anywhere in `App`, and limits `Symfony\Component\Finder\Finder` to `App\Icm\Loader`. Every new room check must work from the `IcmFile` the sync hands it. [VERIFIED: tests/Unit/ArchitectureTest.php:1-45]

## Architectural Responsibility Map

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|-------------|----------------|-----------|
| Room folder, contract, example files | Business folder (ICM files) | -- | Files hold how the business runs |
| Contract and file checks, never-left-out, hidden-name refusal | API / Backend (sync: `Loader`, `Validator`, `RoomChecks`) | -- | The sync is the only reader of the folder (DEC-10); refusals keep the previous version live |
| Room description, file types, slot binding, section class | API / Backend (`app/Rooms/<Room>/` provider, registered in `bootstrap/providers.php`) | -- | `RoomServiceProvider::register()` records facts only |
| Dashboard section | Frontend Server (Filament widget, Livewire, server-rendered) | API / Backend (`ActiveConfig` reads) | `RoomSection::canView()` asks `ActiveConfig::hasRoom()`; first HTML carries the section |
| Section facts (service count, live offers, primary category) | API / Backend (`ActiveConfig`, `OfferSentences`) | Frontend Server | One answer to "is this offer live" shared with the front desk (D-17) |
| Google listing fetch (read-only slot) | API / Backend (interface + fake driver) | -- | Nothing reaches Google; `Http::preventStrayRequests()` is global in `tests/Pest.php` |
| Number refusal in money notes | API / Backend (`RoomFileType::checks()`) | -- | Runs inside the sync on the loaded file |
| Hidden-character mark in refusal lines | API / Backend (`ValidationReport::lines()`) | Frontend Server (Business Folder page, changelog print the lines) | Every consumer prints `lines()` output |
| Database / Storage | none | -- | `writes_records: none`, no tables, no models (D-05) |

## Standard Stack

No package is added. Everything below is already installed. [VERIFIED: composer.lock read this session, versions]

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| laravel/framework | v13.35.0 | Service providers, container binding, Pest-friendly testing | Already the app |
| filament/filament | v5.10.1 | `Widget` base for `RoomSection` | The shared section view is `filament.rooms.section` |
| livewire/livewire | v4.4.7 | Section widgets are Livewire components | Filament dependency |
| pestphp/pest | v5.3.1 | Test runner | Project rule |
| pestphp/pest-plugin-browser | v5.1.2 | axe check on each section (`tests/Rooms/RoomSectionAxe.php`) | Phase 4 D-27, approved 2026-10-22 |
| giggsey/libphonenumber-for-php-lite | 9.0.41 | Behind `App\Support\PhoneNumber::toE164()` for the D-16 phone skip | Already a production `require` in `composer.json` |
| symfony/yaml, symfony/finder | v8.1.8 | Loader only | Unchanged |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Hand-rolled Luhn (about 8 lines) | `Faker\Calculator\Luhn` | Faker is a dev dependency (`require-dev`), so it is absent from a production install. [VERIFIED: composer.json `require-dev` lists `fakerphp/faker`; `grep -rli luhn vendor` finds only Faker's `Luhn.php` and autoload maps] Not usable from `app/`. |
| PCRE `\p{..}` classes for D-19 | `intl` `Normalizer` / `IntlChar` | Not needed: `\p{Cc}\p{Cf}\p{Zl}\p{Zp}` is built into PHP's PCRE (PCRE2 10.44 here). Also `composer.json` does not declare `ext-intl`, so an `intl`-based check would add an undeclared extension requirement. [VERIFIED: probe, composer.json `require` has no `ext-*` lines] |

**Installation:** none.

## Package Legitimacy Audit

No external package is installed or recommended in this phase. The `gsd_run query package-legitimacy` seam was not needed.

| Package | Registry | Age | Downloads | Source Repo | Verdict | Disposition |
|---------|----------|-----|-----------|-------------|---------|-------------|
| (none) | -- | -- | -- | -- | -- | No install |

**Packages removed due to [SLOP] verdict:** none
**Packages flagged as suspicious [SUS]:** none

## Architecture Patterns

### System Architecture Diagram

```
 owner edits files          icm:sync / Business Folder "Sync now"
 examples/example-hvac  --> FolderSync::sync()
   rooms/<five>/CONTEXT.md        |
   rooms/content_generation/...   v
   rooms/google_business_profile/ Loader::load()  -- (D-19) name has Cc/Cf/Zl/Zp? --> Unrecognized "hidden_name"
   rooms/money/...                |  reads *.md, splits front matter, TYPED + RoomRegistry::registeredTypes()
   connectors/google-business-... v
                                  Validator::validate()
                                    |- per file: rules() + checks()   <-- RoomFileType (content_brief, gbp_categories, money_notes)
                                    |- RoomChecks::errors()
                                    |     |- contract / missing-contract / unknown-room lines
                                    |     |- NEW never-left-out (company_knowledge, marketing)  --> one line, skips per-file lines
                                    |     |- placement, claims (router-wildcard fix), double claims, unclaimed/absent
                                    |- ConnectorChecks::folderErrors()  (slot -> room present? one file per slot)
                                    v
                         ValidationReport::lines()  -- NEW print-time mark: [line break] / [hidden character]
                                    |
              errors? --yes--> stored Rejected version, previous stays live, lines shown on Business Folder page + changelog
                       --no---> compiled version Active
                                    |
                                    v
        ActiveConfig::hasRoom(key) / roomContract() / roomOwnedFiles() / filesOfType() / offers()
                                    |
        Filament /admin: five RoomSection widgets (canView = hasRoom) --> filament.rooms.section
              empty line (platform-drawn) OR contentLines() (D-17 facts)

 GBP slot:  app(GoogleListingSource::class) --> FakeGoogleListingSource (driver 'fake'); nothing leaves the app
```

### Recommended Project Structure
```
app/Rooms/
├── CompanyKnowledge/   CompanyKnowledgeServiceProvider.php, CompanyKnowledgeSection.php
├── Marketing/          MarketingServiceProvider.php, MarketingSection.php
├── ContentGeneration/  ContentGenerationServiceProvider.php, ContentGenerationSection.php, ContentBriefType.php
├── GoogleBusinessProfile/ ...ServiceProvider.php, ...Section.php, GbpCategoriesType.php,
│                          GoogleListingSource.php (interface), FakeGoogleListingSource.php
└── Money/              MoneyServiceProvider.php, MoneySection.php, MoneyNotesType.php (+ one small number-check helper)
bootstrap/providers.php           (append the five providers after AppServiceProvider)
examples/example-hvac/rooms/<five>/CONTEXT.md, one new file in three of them, connectors/<gbp>.md
tests/Feature/Rooms/<Room>BootTest.php   (one per room, copying PretendRoomBootTest's "boots the pretend room" shape)
```
All names are the planner's (CONTEXT Claude's Discretion). The directory layout follows the existing `app/Rooms/` row in `CLAUDE.md`.

### Pattern 1: A real room is one provider
**What:** Extend `RoomServiceProvider`, return a `Room` from `room()`, list the provider in `bootstrap/providers.php` after `AppServiceProvider`.
**Verbatim `Room` constructor (the only parameters that exist):** [VERIFIED: app/Rooms/Room.php:24-35]
```php
public function __construct(
    public string $key,
    public string $label,
    public array $handles = [],
    public array $fileTypes = [],
    public ?string $slot = null,
    public ?string $slotInterface = null,
    public array $sections = [],
    public array $tables = [],
    public array $timelineEntries = [],
    public array $morphMap = [],
) {}
```
**Verbatim `RoomFileType` interface:** [VERIFIED: app/Rooms/RoomFileType.php:10-31] `type(): string`, `rules(IcmFile $file): array`, `checks(IcmFile $file): array` (returns `list<array{file: string, field: ?string, message: string}>`).
**Registry guards that bite:** a room-defined type may not be in `Loader::TYPED` or the reserved list `['room', 'connector', 'automation', 'walk_test']`; keys and type names are lower snake case (`/^[a-z0-9]+(_[a-z0-9]+)*$/`); a slot name is unique; a slot interface must exist; a section must be a `RoomSection` whose `roomKey()` equals the room's key. [VERIFIED: app/Rooms/RoomRegistry.php:25, 33, 195-216 (guardFileTypes), 218-250 (guardSlot), 301-318 (guardSections)]
**Example (illustrative; class names are the planner's):**
```php
class CompanyKnowledgeServiceProvider extends RoomServiceProvider
{
    protected function room(): Room
    {
        return new Room(
            key: 'company_knowledge',
            label: 'company knowledge',
            handles: ['business', 'hours', 'service', 'knowledge', 'voice'],
            sections: [CompanyKnowledgeSection::class],
        );
    }
}
```
This exact shape registered and synced in the prototype. `RoomRegistry::all()` returns rooms in key order, so widgets and the "the rooms are: ..." sentence come out alphabetical: company_knowledge, content_generation, google_business_profile, marketing, money.

### Pattern 2: The never-left-out check (D-02)
Prototype result, using a constant map in `RoomChecks` (a `Room` constructor flag, such as a trailing `?string $neverLeftOutBecause = null`, is cleaner because the reason sits beside the room; both work and `Room` is `readonly` so the trailing optional parameter is source-compatible with every existing `new Room(...)` call):

```
both absent:
rooms/company_knowledge/CONTEXT.md: the company knowledge room can never be left out: it owns the profile, hours and voice files the sync requires; add rooms/company_knowledge/CONTEXT.md
rooms/marketing/CONTEXT.md: the marketing room can never be left out: it owns the lead sources file the sync requires; add rooms/marketing/CONTEXT.md
```
No per-file "which this business doesn't use" lines appeared, because the check adds the absent room's handled types to a skip list consulted in the same loop that calls `unclaimedOrAbsentError()`. [VERIFIED: prototype run; the loop it edits is app/Icm/RoomChecks.php:128-138] One more design point is in Pitfall 2 (a second line for the same problem).

### Pattern 3: Own the new files by exact path
Own `rooms/content_generation/brief.md`, `rooms/google_business_profile/categories.md`, `rooms/money/notes.md` (file names are the planner's), not `rooms/<room>/*.md`. Prototype results:
- A second typed file in the folder is refused by the existing unclaimed line, so "a folder holds at most one" (D-06, D-12, D-15) needs no folder-level check (`RoomFileType::checks()` sees one file at a time): `rooms/content_generation/second.md: no room lists this content_brief file under owns; the content generation room handles it, so add it to rooms/content_generation/CONTEXT.md`.
- The platform-drawn empty line names the file, as D-07 requires: `Google Business Profile: nothing set up yet. Add rooms/google_business_profile/categories.md to start.` With a wildcard it would read "Add a file to rooms/google_business_profile/ to start." [VERIFIED: prototype dashboard HTML; the wording comes from `RoomSection::emptyLine()`, app/Rooms/RoomSection.php:42-56]
- The SPEC ownership table row is then the exact path, and the boot test's `SpecTables::patternsOwnedBy($spec, '<owner>')` compares it to the contract's `owns` list with `toBe`, so order and text must match. The owner word must be one of `'content generation'`, `'Google Business Profile'`, `'money'` (the allowed list in `tests/Feature/Rooms/SpecTablesTest.php:67-79`).

### Pattern 4: The read-only slot, bound the `TextChannel` way
Interface with one method (D-11), a fake that returns a fictional listing, a binding in the room provider's `register()` that reads the driver from config with a default of `fake` and throws `InvalidArgumentException` on any other value. The pretend room does exactly this (`tests/Rooms/PretendRoom/PretendRoomServiceProvider.php`), and it needs no config file because of the default. `Room::slot` must equal the contract's `connector:` value and the connector file's `slot:`; `slotInterface` must be an existing interface. The prototype's slot name `google_business_profile` registered and synced. The "nothing reaches Google" test is: resolve the interface, assert it is the fake, `Http::fake()` then `Http::assertNothingSent()` (`Http::preventStrayRequests()` is already global). [VERIFIED: tests/Pest.php:60]

### Pattern 5: Sections read only through `ActiveConfig` and `OfferSentences`
`RoomSection` supplies `canView()`, the empty line and `getViewData()`; a room overrides `contentLines()`. Marketing's "offers live today" is `app(OfferSentences::class)->liveToday()` (it picks the business time zone itself). [VERIFIED: app/SendRule/OfferSentences.php:25-40] Company knowledge: `ActiveConfig::services()` count and `ActiveConfig::file('business/faq.md') !== null`. Content generation and money: `ActiveConfig::filesOfType('<type>') !== []`. GBP: `filesOfType('gbp_categories')[0]['primary_category']` plus a fixed line that the room is not connected to Google.

A room whose contract owns nothing is never shown empty (Phase 4 decision, STATE.md), but all five rooms here own files, so each shows its empty line when its owned files are absent. For company knowledge and marketing the owned files are required files, so the empty line is unreachable in practice; their empty boot test keeps those files (D-03).

### Anti-Patterns to Avoid
- **Wildcard owns for the single-file rooms** (see Pattern 3).
- **`max:9` rule for `additional_categories`.** `PlainWords::render('Max', ...)` prints "must be 9 or less (you wrote a list)" for an array, which reads wrongly. Count in `checks()` and write the sentence. [VERIFIED: app/Icm/PlainWords.php:32]
- **Passing a bare digit run to `PhoneNumber::toE164()` for the 9-digit rule** (Q2).
- **A `knowledge` file claimed through a glob of the whole `business/` folder** just to satisfy a test (Open Question 8).
- **Editing `Loader::TYPED`** for a room type (04 D-19).
- **`env()` or file reads in `App\Rooms`** (ArchitectureTest).

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Room registration, section hiding | A new registry or route | `RoomServiceProvider`, `RoomRegistry`, `RoomSection` | Phase 4 standard; guards already written |
| Empty-room line | A per-room empty screen | `RoomSection::emptyLine()` (platform-drawn, D-21) | One wording, one axe check |
| Offer live today | A second date comparison | `OfferSentences::liveToday()` / `isLive()` | The front desk and the dashboard can never disagree (D-17) |
| Phone recognition | Your own phone regex | `App\Support\PhoneNumber::toE164()` | libphonenumber rules; see Q2 for how to feed it |
| Hidden-character detection | Normalization, `intl`, manual code-point tables | PCRE `/[\p{Cc}\p{Cf}\p{Zl}\p{Zp}]/u` | Built into PHP's PCRE2 (10.44 here) |
| Plain-words value quoting | `"$value"` in a message | `PlainWords::oneLine()` / `value()` | Already collapses line breaks and cuts to 80 characters |
| Card check digit | A package | A small private Luhn function | No production package exists in `vendor/`; 8 lines; it is a checksum, not security crypto |

**Key insight:** every new check is a pure function of the `IcmFile` or file list the sync already holds, so it belongs in `checks()`, `RoomChecks` or `ValidationReport`, never in a new reader of the folder.

## Question 1: Existing tests the D-02 never-left-out check, and the rooms landing, would refuse or change

**Direct answer to Ian's question (what the never-left-out check itself refuses).** No existing test builds or trims a folder in a way the D-02 check refuses. Every test that syncs copies `examples/example-hvac` (`tests/Pest.php:78-83`) and then adds or edits files. None removes a `rooms/company_knowledge/` or `rooms/marketing/` folder, because those folders do not exist yet; none builds a minimal folder from scratch for a sync. Evidence: `grep` of `tests/` for `deleteDirectory`, `File::delete`, `File::move`, `copyDirectory`, `sys_get_temp_dir`, `new Loader`, `new LoadedFolder`, `new IcmFile`, `->validate(`, `RoomChecks` found only `tests/Pest.php` copying the example folder and tests deleting `rooms/pretend`, `rooms/annex`, `connectors` or single files. The two tests that look like a bare folder (`tests/Feature/Icm/ActiveConfigTest.php:84` deletes the whole `icm.path` and expects the missing-folder error before any validation; `tests/Feature/Rooms/SpecTablesTest.php:93` loads `base_path('examples/example-hvac')` directly) are not refused. [VERIFIED: grep this session; prototype full-suite run with the check live and the example folder furnished]

The honest answer is therefore "none by D-02 itself, but 44 cases in 11 files by the rooms landing". The prototype (five providers registered, example folder furnished with five contracts, three new files and the GBP connector file, D-02 and D-19 coded, count 23) ran the whole suite (1981 tests): 1933 passed, 43 failed (42 before D-19 was coded, one more after), and 5 errors that are only `ProjectRulesTest` finding no `.ai/rules` in the scratch copy. The prototype had already changed the `14` in `JevClassifierTest.php:64`, so that case is the 44th. [VERIFIED: prototype, `php artisan test --compact`, SQLite, macOS APFS]

Line numbers are the failing assertion's line, with the test's own `it(` line in brackets. "Cause" says which plan's room triggers it, so each lands with its room (04 D-16).

| Test (file:line) | Fails because | Cause (lands with) | Fix shape |
|---|---|---|---|
| `tests/Pest.php:293` | `return 14;` | any furnished room | the one allowed change: new count (23 if the example holds 5 contracts + 3 new files + 1 connector file) |
| `tests/Feature/Icm/OfferValidationTest.php:85` [82] | hard-coded `->toBe(14)` | example grows | use `exampleFolderFileCount()`; **this is a second hard-coded count the Phase 4 D-16 rule did not allow for, and success criterion 3 says "the existing suite for them passes unchanged"** (Open Question 5) |
| `tests/Feature/SendRule/JevClassifierTest.php:64` [~59] | hard-coded `expect(exampleFolderFileCount())->toBe(14)` | example grows | same |
| `tests/Feature/Icm/SyncIgnoresRecordsTest.php:33` [30] | writes `business/records/notes.md` with `type: knowledge`; company knowledge handles `knowledge` and no `owns` entry lists it, so the sync is Rejected | company knowledge | change the fixture file's type, or add it to the test's contract; name in plan |
| `tests/Feature/Icm/ValidatorTest.php:93` [87] | extra line `business/profile-old.md: no room lists this business file under owns; the company knowledge room handles it...` | company knowledge | add the line to the expected list |
| `tests/Feature/Icm/ValidatorTest.php:63` [58, dataset `a type nobody knows`] | the "type must be one of" sentence now ends `..., connector, content_brief, gbp_categories, money_notes` | first room that defines a type | build the expected sentence from `Loader::TYPED` plus `registeredTypes()` or update the string |
| `tests/Feature/Rooms/RoomFileTypeTest.php:98` [92], `:106` [101] | same type-list sentence (with and without `pretend_note`) | first room that defines a type | same |
| `tests/Feature/Rooms/RoomContractTest.php:213` [205] | `the rooms are: pretend` now lists the real rooms | first registered room | expected string changes |
| `tests/Feature/Rooms/RoomContractTest.php:223` [217] | expects `..., and no rooms yet`; the branch (`RoomChecks.php:91-93`) can no longer run in the app | first registered room | **cannot be fixed by editing the expectation; see Open Question 4** |
| `tests/Feature/Rooms/RoomContractTest.php:91` [78] | `rooms/pretend/CONTEXT.md` written as `type: knowledge` also gets `no room lists this knowledge file...` | company knowledge | change the second case's type to one no room handles, or expect two lines |
| `tests/Feature/Rooms/RoomContractTest.php:202` [196] | the folder-without-contract test uses a `type: knowledge` note and now also gets the unclaimed line | company knowledge | write a `pretend_note` file instead |
| `tests/Feature/Rooms/RoomContractTest.php:280-290` [280] (only after D-19) | the G-04-1 test asserts the `type must be room` line for `rooms/pre\n# tend/CONTEXT.md`; the Loader now refuses the name first, so that line never appears (actual size 0) | D-19 | rewrite to assert the Loader refusal, the `[line break]` mark, and the folder line (Pitfall 5) |
| `tests/Feature/Rooms/PretendRoomBootTest.php:51` [39] | `roomKeys()` is now six rooms, not `['pretend']` | example rooms | compare against the five plus pretend, or filter |
| `tests/Feature/Rooms/PretendRoomBootTest.php:133` [132] | asserts `providers.php` is exactly `[AppServiceProvider, AdminPanelProvider]` | the first provider in `providers.php` | assert `PretendRoomServiceProvider` is absent and the five real ones present |
| `tests/Feature/Rooms/PretendRoomBootTest.php:118` [115] | writes `Rooms/pretend/CONTEXT.md` (capital R) expecting a different folder from `rooms/`; on macOS APFS (case-insensitive by default) it is the same folder, so the file becomes the pretend contract of a room that is not enabled in that test. It would pass on Linux CI and fail on Ian's Mac. | example `rooms/` folder | pick a near-miss that differs on a case-insensitive disk too (Pitfall 7) |
| `tests/Feature/Rooms/SpecTablesTest.php:104` [91] | the three new example files match no SPEC ownership row | content generation, GBP, money | add the SPEC rows (D-21 edits land in execution) |
| `tests/Feature/Rooms/RoomOwnershipTest.php:52` [47], `:63` [58], `:70` [66], `:109` [103], `:118` [112], `:126` [121], `:132` [129], `:156` [153], `:195` [189] | the annex test room also handles `knowledge` and its fixture owns `business/faq.md`, so every annex test now double-claims the example's `business/faq.md` with company knowledge, or the "no room lists / room absent" cases no longer apply because company knowledge claims the FAQ | company knowledge | fix `tests/Rooms/PretendRoom/AnnexRoom.php` (`handles: ['knowledge', 'pretend_note']`) and `tests/Fixtures/annex-room/rooms/annex/CONTEXT.md` (`owns: [business/faq.md]`) once, so annex handles only `pretend_note` and owns something no real room handles; then rewrite the four cases about the FAQ to use the pretend note |
| `tests/Feature/Rooms/OrderIndependenceTest.php:97` [78] | the two refusal lines now say `which the company knowledge room handles` instead of `which no room handles yet` | company knowledge | expected strings |
| `tests/Feature/Rooms/OrderIndependenceTest.php:108` [103] | uses the annex fixture (same double claim) | company knowledge | AnnexRoom fix above |
| `tests/Feature/Rooms/ConnectorFileTest.php:51` [43], `:88` [81, six dataset rows], `:154` [146], `:287` [281] | compare `compiled['index']['connector']` to `[PRETEND_CONNECTOR_PATH]` or `[]`; the example folder now holds the GBP connector file | GBP connector file in the example | expected lists include the GBP path, or filter it |
| `tests/Feature/Rooms/ConnectorFileTest.php:241` [235], `:259` [252], `:298` [291] | `the slots are: annex_feed, pretend_feed` now includes `google_business_profile` | GBP slot | expected strings |
| `tests/Feature/Rooms/ConnectorFileTest.php:249` [244] | expects `this engine has no connector slots yet`; branch unreachable once any room has a slot | GBP slot | **Open Question 4** |
| `tests/Feature/Rooms/ConnectorFileTest.php:191` [189], `:209` [200], `:220` [215], `:232` [225] | use `layTwoRoomsWithSlots()` which lays the annex fixture | company knowledge | AnnexRoom fix above |

Tests I checked that do **not** change: `PretendRoomBootTest` "boots the pretend room" (it asserts counts relative to `exampleFolderFileCount()` and its `assertDontSee('nothing set up yet')` still holds because all five example rooms are furnished; fragile if one example file is ever removed), `RoomAccessibilityTest` (axe, browser), `RoomSectionTest`, `RoomTimelineTest`, `ChangelogTest`, `BusinessFolderPageTest`, `ActiveConfigTest`, `FrontDesk/*`, `Records/*`. `tests/Feature/Rooms/RoomOwnershipTest.php:206` ("passes the example folder untouched when no room handles any of its files") still passes but its title is no longer true.

**Totals:** 44 cases in 11 files (including the D-19 case and the `JevClassifierTest` count), of which about 14 are connector cases that land with the GBP plan and about 20 are the annex and `knowledge` cluster that lands with company knowledge. Because a first-landing room carries disproportionate fallout, the plan that registers company knowledge should be last-but-one in its wave or the fallout fixes should be a separate tests-first plan before any room lands.

## Question 2: What `PhoneNumber::toE164()` accepts, and whether 9-digit or 13-to-19-digit runs would be skipped as phones (D-16)

**What the code does.** [VERIFIED: app/Support/PhoneNumber.php:18-39] It trims; returns `null` for an empty string; calls `PhoneNumberUtil::parse($trimmed, config('channels.phone_region'))` (region `US` unless `PHONE_REGION` is set, `config/channels.php`); returns `null` on `NumberParseException` or when `isValidNumber()` is false; otherwise the E.164 form. Nothing else.

**Probe results** (PHP 8.5.12, libphonenumber-lite 9.0.41, region US). [VERIFIED: throwaway `tinker` probes in the scratchpad; nothing added to the repo]

Accepted (returns E.164): `312-555-0147`, `(312) 555-0147`, `312.555.0147`, `312 555 0147`, `3125550147`, `13125550147`, `1-312-555-0147`, `+1 312 555 0147`, `+1 (312) 555-0147`, `+13125550147`, `800-555-0199`, `1800 555 0199`, `1-800-FLOWERS` (letters are read), `+44 20 7946 0958`. The example business phone is `+13125550147` (`examples/example-hvac/business/profile.md`). `847-123-4567` is refused (a number must be valid, not just 10 digits), so a made-up 10-digit number is not a phone.

Refused (`null`): `123456789`, `123-45-6789`, `123 45 6789`, `12-3456789`, `021000021` (a routing-number shape), `999999999`, `000000000`, `111111111`; 8 and 7 digits; every known test card (`4111111111111111`, `4111 1111 1111 1111`, `4111-1111-1111-1111`, `378282246310005`, `3782 822463 10005`, `6011111111111117`, `5555555555554444`, `30569309025904`, `4222222222222`); `1234567890123`, `1234567890123456789`, a 20-digit run; `312-555-0147 773-555` and `3125550147773` (a phone with extra digits glued on is not a phone).

**9-digit runs that ARE taken as a phone.** There is a small fixed set. Exhaustive enumeration of every 9-digit string beginning `011` (10^6 strings): 56 accepted (`011431110` to `011431119`, `011491640` to `011491649`, `011491680` to `011491699`, a few `0119896xx`), read as an international call from the US (IDD prefix `011`, then country code). Exhaustive enumeration of every string beginning `113` (10^6): 10,000 accepted, exactly `113100000` to `113109999` (read as `+1` plus a 7-digit number, for example `113108819` gives `+13108819`). A stratified sample of 300 completions for each of the 1,000 three-digit prefixes (300,000 parses) found no other prefix; that sample cannot rule out a set smaller than about 1 in 300 inside some other prefix, so the full set is "at least 10,056". All of them are also accepted in the `123-45-6789` and `123 45 6789` shapes (`113-10-8819` is shaped like a valid Social Security number). So a real 9-digit number in those two ranges would be skipped, not refused, if the 9-digit rule feeds the bare run to `toE164()`.

**13-to-19-digit runs that ARE taken as a phone.** A run beginning `011` plus a country code is read as an international call: `011442079460958`, `011 44 20 7946 0958` and `011-44-20-7946-0958` all give `+442079460958`. In 900,000 random 13-19-digit tries (half beginning `011`, each contiguous, in 4-digit groups with spaces and with dashes) 27,399 were accepted; every one of the first eight printed began `011`, so the bulk is the international-prefix case. A separate probe of 200,000 random 13-19-digit strings not starting with 0 accepted 13, every printed one beginning `111` (for example `1119458222458`), read as `+1...`. In 450,000 Luhn-valid card-like numbers across Visa, Mastercard (51-55, 2221-2720), Amex (34, 37), Discover (6011, 65), JCB (35), Diners (30, 36, 38), UnionPay (62) and the prefixes 1, 7, 8, 9, only 18 were accepted, all with prefix `1` (the UATP prefix) and followed by `11`. In 200,000 random 13-19-digit strings not starting 0, 13 were accepted, all starting `111`. In short: no real card brand's number is skipped as a phone, but a 15-digit Luhn-valid run beginning `011` or `111` would be skipped. That is the safe direction for the 13-19 rule (it avoids refusing a dialed international number that happens to pass Luhn, about one in ten).

**Consequences for D-16, recommended (the check's construction is the planner's call):**
1. Run the phone skip on the whole candidate span, not on the digits alone. `+34 912345678` has a contiguous 9-digit run (`912345678`) but is a Spanish phone; feeding `toE164()` the span `+34 912345678` skips it, while the bare `912345678` correctly stays refused (`toE164()` returns null in region US).
2. Do not run the phone skip on a span that is exactly 9 digits with no leading `+`. A 9-digit run cannot be a US phone, and the only 9-digit strings `toE164()` accepts are the 10,056 above, which are indistinguishable from SSN-shaped numbers.
3. Tokenize before testing. Two phone numbers written side by side (`312-555-0147 773-555-0100`) form a 20-digit span, so they are neither a card (more than 19 digits) nor 9 digits. But a regex such as `\d(?:[ -]?\d){12,18}` with backtracking can end a match mid-span: `312-555-0147 773` is 13 digits and passes Luhn about 1 time in 10, which would refuse an innocent note. Match maximal spans (digit groups joined by single spaces or dashes), decide on the whole span, and consider requiring card-like grouping (contiguous, 4-4-4-4, 4-6-5) for the spaced or dashed form. D-16 says only "written together or split by spaces or dashes", so tightening the grouping is a design choice to confirm with Ian, not a new decision.
4. The two 9-digit forms in D-16 are `123456789` and `123-45-6789`. An employer ID is written `12-3456789` (2-7), which neither form matches, although D-16 lists "a tax ID". See Open Question 3.
5. A leading `+` is not part of any D-16 form, and a ZIP+4 (`60601-1234`, 5-4) is not matched by those two forms and is left alone; a ZIP+4 written together (`606011234`) would be refused. Say so in the money room's `CONTEXT.md` body wording only if the planner wants.

**Luhn.** Standard mod-10 over the digit string; about 8 lines (see Code Examples). The only Luhn in `vendor/` is `Faker\Calculator\Luhn`, a dev-only class. No package.

## Question 3: Does D-19 (Cc, Cf, Zl, Zp) leave `café.md` alone when macOS stores the accent as a combining mark?

**Short answer: yes, in both forms.** [VERIFIED: probe, PCRE 10.44 / PHP 8.5.12]

`/[\p{Cc}\p{Cf}\p{Zl}\p{Zp}]/u` against `caf\u{00E9}.md` (NFC, bytes `636166c3a92e6d64`): no match. Against `cafe\u{0301}.md` (NFD, `e` plus U+0301, bytes `63616665cc812e6d64`): no match. U+0301 is category Mn (a nonspacing mark), which is not in the list; `ñ` as `n` + U+0303, decomposed Hangul jamo, the variation selector U+FE0F (Mn), skin-tone modifiers (Sk) and all of NBSP U+00A0, U+3000 and U+2002 (Zs), private use U+E000 (Co) and a noncharacter U+FFFE (Cn) also pass.

**How the Loader reads names.** `Loader::load()` builds `(new Finder)->files()->in($root)->name('*.md')->notPath('#^records(/|$)#')->filter(...isLink...)->filter(...changelog.md...)->sortByName()`. For each file it takes `$file->getRelativePathname()` through `relativePath()`, which only does `str_replace('\\', '/', ...)`. There is no `Normalizer` call and no `mb_*` conversion anywhere in the Loader. The name is whatever bytes the directory listing returns. [VERIFIED: app/Icm/Loader.php:73-79, 151-156]

**What macOS returns.**
- APFS (this machine: `File System Personality: APFS`, mounted `apfs`; Apple's default, case-insensitive variant) preserves the normalization of the name as created and is normalization-insensitive for lookup. Probe on this Mac: creating `café.md` in NFC and listing through the Symfony Finder returned the NFC bytes `636166c3a92e6d64`; writing the NFD name afterward wrote to the same file (the listing still showed one file, in the NFC form), and `file_exists` was true for both forms; in a fresh directory the NFD name came back as NFD bytes `63616665cc812e6d64`. So a name arrives as NFC or NFD depending on the program that created it, and either passes the D-19 pattern. [VERIFIED: probe; CITED: https://developer.apple.com/library/archive/documentation/FileManagement/Conceptual/APFS_Guide/FAQ/FAQ.html "preserves both case and normalization of the filename on disk"]
- HFS+ (older volumes) stores names decomposed (Unicode 3.2 NFD) and returns the NFD form, so `café.md` comes back as `e` + U+0301; still passes. [CITED: same Apple FAQ, via search result; the `yorkxin` and `filename-checker` pages quote it]
- Linux and SMB shares return the stored bytes (SMB from a Mac client may have decomposed them). [ASSUMED] for those two; the pattern passes either form regardless.

**Where D-19 would refuse a name an owner might reasonably use.** All of these match and are refused: ZWJ U+200D (used in emoji sequences such as a family emoji, and in Indic scripts), ZWNJ U+200C (used in Persian, Kurdish and some Indic words), LRM and RLM U+200E U+200F, soft hyphen U+00AD, BOM U+FEFF, word joiner U+2060, bidi isolates U+2066-2069, tag characters U+E0000 range, Mongolian vowel separator U+180E, plus the intended line breaks, tab, NEL U+0085, U+2028, U+2029, and the right-to-left override U+202E. That is the D-19 intent (Trojan Source and invisible names), so the cost is only that a business with a Persian or emoji-sequence file name must rename it. Flagged as Open Question 10 for Ian. Accented Latin, Cyrillic, Greek, CJK and the variation selector are unaffected.

**Invalid UTF-8 names.** `preg_match()` with `/u` returns `false` (`Malformed UTF-8 characters`) rather than 0 for a name that is not valid UTF-8, and `preg_replace()` with `/u` returns `null`. A check written `if (preg_match(...) === 1)` would let such a name through. Write it as `!== 0` (false counts as refuse), or `mb_check_encoding()` first. APFS accepts only valid UTF-8 names, but Linux and SMB do not. `PlainWords::oneLine()` already has the null problem: it does `trim((string) preg_replace('/\s+/u', ' ', $text))`, which returns an empty string for malformed input. [VERIFIED: probe; app/Icm/PlainWords.php:63-68]

**D-19 package check.** Nothing is needed. The name check is one `preg_match`; the mark is one `preg_replace_callback`, both PHP core with PCRE Unicode properties; `mb_scrub()` (mbstring, already relied on by `PlainWords::oneLine()`'s `mb_strlen` and `mb_substr`) cleans a malformed line first if the planner wants no null case.

## Package confirmation (D-16, D-19)

[VERIFIED: composer.json, composer.lock and package.json read this session]
- `composer.json` `require`: `php ^8.3`, `filament/filament ^5.0`, `giggsey/libphonenumber-for-php-lite ^9.0`, `laravel/ai ^1.2`, `laravel/framework ^13.17`, `laravel/tinker ^3.0`. No `ext-*` line.
- D-16 (Luhn card check, 9-digit pattern, phone skip): `preg_match_all`, a hand-written Luhn, and the existing `App\Support\PhoneNumber` (libphonenumber is already a production dependency). **No new composer or npm package.**
- D-19 (Unicode category matching in names and in `ValidationReport::lines()`): PCRE `\p{..}` with `/u`. **No new composer or npm package.**
- `package.json` has no runtime dependency; nothing frontend is touched (the shared section view already exists).
- Nothing in this phase needs to come back to Ian under 04 D-18.

## Common Pitfalls

### Pitfall 1: `business/services/*.md` cannot be claimed while `business/services/CONTEXT.md` exists
**What goes wrong:** With company knowledge's `owns` set to the SPEC row `business/services/*.md`, the example folder was refused: `rooms/company_knowledge/CONTEXT.md: business/services/CONTEXT.md belongs to the house (routers and walk tests); company knowledge can read it`. **Why:** `Str::is('business/services/*.md', 'business/services/CONTEXT.md')` is true, the router is "claimable", and `RoomChecks::claims()` exempts only routers inside the claiming room's own folder: [VERIFIED: app/Icm/RoomChecks.php:235] `if ($file->kind === IcmFileKind::Router && str_starts_with($file->path, "rooms/{$room->key}/")) {`, with the refusal at `:258-260` (`"{$file->path} belongs to the house (routers and walk tests); {$room->label} can read it"`). The example folder ships `business/services/CONTEXT.md` and it is the owner's guide, so removing it is not an option. **Fix verified in the prototype:** also skip a router when the `owns` entry contains `*` (`str_contains($entry, '*')`); an entry that names a router exactly (`CLAUDE.md`, `START-HERE.md`) is still refused, so the four cases in `tests/Feature/Rooms/RoomOwnershipTest.php:73-90` keep passing. With it the example folder syncs. This is a change to a Phase 4 check and needs a test of its own. `business/offers/*.md` has no router in the example (the folder holds `README.md`, which the Loader skips), but the same fix covers an owner who adds one.

### Pitfall 2: One problem, two lines (D-02 and the missing-contract line)
**What goes wrong:** When `rooms/marketing/` holds a file but no `CONTEXT.md`, the existing line fires (`the marketing room's folder has files but no CONTEXT.md; add the room's contract with type: room`) and so does the new never-left-out line, both on `rooms/marketing/CONTEXT.md`, plus one stray line for the loose file. [VERIFIED: prototype output] **Avoid:** emit the never-left-out line only when `rooms/<key>/` has no loaded file at all (use the same `$folders` collection `errors()` already builds, `RoomChecks.php:80`), and keep skipping the per-file lines. When the contract exists but is broken (`type: room` with fields missing), the contract's own lines already say what to fix; the prototype showed the never-left-out line correctly does not fire there because the contract loads as a typed room file, but the unclaimed lines for the room's required files do (`business/lead-sources.md: no room lists this lead_sources file under owns...`). Decide whether the skip list should also apply when the contract is present but its `owns` is unusable; D-02 only speaks of an absent room.

### Pitfall 3: A hidden-name file still feeds the room-folder lines
**What goes wrong:** With the Loader turning `rooms/pre\n# tend/CONTEXT.md` into an Unrecognized file with a hidden-name problem, `RoomChecks::errors()` still builds `$folders` from every file path, including Unrecognized ones, and the line `rooms/pre[line break]# tend/: this engine has no pre # tend room; the rooms are: ...` still appears (seen in the prototype). The decision text says "so no other check ever sees one"; that holds for the claim and placement checks (they skip non-Typed files) but not for the folder list. **Fix:** build `$folders` only from Typed (and Router) files, or from files with no hidden-name problem. [VERIFIED: app/Icm/RoomChecks.php:80, 88-94; prototype]

### Pitfall 4: `ValidationReport::lines()` and malformed UTF-8
**What goes wrong:** A `preg_replace_callback('/.../u', ...)` over `"{$error['file']}: {$error['message']}"` returns `null` when any name is not valid UTF-8, and a line then becomes an empty string. **Avoid:** `mb_scrub()` first, or use `?? ` with a fixed `[hidden character]`. The mark must run on the whole finished line, after `PlainWords::oneLine`, as D-19 says; note `oneLine()` already turns `\n`, `\r`, `\t`, U+0085, U+2028, U+2029 and NBSP into spaces (so `[line break]` can only appear in the file prefix and in values that bypass `oneLine`), while ZWSP, RLO and other Cf characters pass through it untouched. [VERIFIED: probe, `oneLine` output]

### Pitfall 5: The G-04-1 test and the refusal line shape
`tests/Feature/Rooms/RoomContractTest.php:280-290` currently proves the break stays out of the contract-type line. After D-19 that line no longer exists for a hidden-name folder. The prototype's rejected lines for `rooms/pre\n# tend/CONTEXT.md` and `business/ho<U+202E>urs2.md` were `rooms/pre[line break]# tend/CONTEXT.md: the name has a hidden character, such as a line break; rename the file or folder` and `business/ho[hidden character]urs2.md: the name has a hidden character, ...` (plus the folder line from Pitfall 3 until fixed). The Loader should check the name before the README skip (`Loader.php:80-82` skips `README.md` before `read()`), or a `README.md` in a hidden-named folder is silently passed; the check must run on the full relative path (folder segments included). A folder with a hidden name that holds no `.md` file is never listed (`->name('*.md')`) and can reach no refusal line, so it needs no check.

### Pitfall 6: Two hard-coded counts, and "passes unchanged"
`tests/Feature/Icm/OfferValidationTest.php:85` and `tests/Feature/SendRule/JevClassifierTest.php:64` hard-code `14`, against the Phase 4 D-16 rule that only `exampleFolderFileCount()` changes. Success criterion 3 says the marketing offer and lead-source suites "pass unchanged"; `OfferValidationTest.php:85` is in that suite. Either the plan changes that one token to `exampleFolderFileCount()` and says so openly, or the criterion is read as "no behavior change". Raise it; do not decide it silently.

### Pitfall 7: Case-insensitive disks and `Rooms/` versus `rooms/`
`tests/Feature/Rooms/PretendRoomBootTest.php:115-123` proves only `rooms/<room>/CONTEXT.md` is a contract by writing `Rooms/pretend/CONTEXT.md`. Once the example folder has a `rooms/` directory, that write lands in the same directory on macOS (APFS default) and Windows, and the test fails there while passing on Linux CI. [VERIFIED: failing in the prototype on macOS; Apple FAQ for the default] The plan should pick a near-miss that is distinct on any disk (for example a contract-named file one folder deeper, or `rooms/pretend/Context.md`), and a reviewer on the Mac will see the failure first.

### Pitfall 8: A Room label for a proper noun
`Room::label` is documented as "The plain name used mid-sentence, lower case". The SPEC owner word is `Google Business Profile` (capitalized, in the allowed list at `SpecTablesTest.php:67-79`). Sentences are built `"the {$room->label} room"` and headings `ucfirst($label)`. Registering the label as `Google Business Profile` read fine in the prototype (`the Google Business Profile room`, heading `Google Business Profile`); a lower-case label would read `google business profile room`. This is a planner decision (Open Question 1), not a bug.

### Pitfall 9: Do not use `max:9` for the categories list
See Anti-Patterns. Also compare "ignoring case and extra spaces" the way `PlainWords::oneLine()` collapses space (`/\s+/u`) plus `mb_strtolower`, so `HVAC  Contractor` equals `hvac contractor`.

### Pitfall 10: Every new section must be furnished in the example folder or the pretend room's `assertDontSee('nothing set up yet')` breaks
It currently holds only because all five example rooms own a live file.

## Code Examples

Illustrative skeletons; names and wording are the planner's. Values quoted from the repo are in the verified blocks above.

### Never-left-out beside the ownership checks (prototype shape)
```php
// inside RoomChecks::ownershipErrors(), before $this->claims(...)
$presentKeys = array_map(fn (Room $room): string => $room->key, $present);
$skipTypes = [];

foreach (self::NEVER_LEFT_OUT as $key => $reason) {       // or a flag on Room
    $room = $this->rooms->get($key);

    if ($room !== null && ! in_array($key, $presentKeys, true) && ! $folders->contains($key)) {
        $errors[] = $this->error("rooms/{$key}/CONTEXT.md", null, "the {$room->label} room can never be left out: {$reason}; add rooms/{$key}/CONTEXT.md");
        array_push($skipTypes, ...$room->handledTypes());
    }
}
// ...and in the unclaimed loop: `|| in_array($file->type, $skipTypes, true)` => continue
```
(`! $folders->contains($key)` is the Pitfall 2 refinement; the prototype did not have it.)

### Luhn, and the number scan (D-16)
```php
private function passesCardCheck(string $digits): bool
{
    $sum = 0;
    foreach (array_reverse(str_split($digits)) as $position => $digit) {
        $value = (int) $digit * ($position % 2 === 1 ? 2 : 1);
        $sum += $value > 9 ? $value - 9 : $value;
    }

    return $sum % 10 === 0;
}
```
Scan maximal spans, then: span passes `PhoneNumber::toE164()` (never for a bare 9-digit span) -> skip; digits are 13-19 and `passesCardCheck` -> refuse; span matches `^\d{9}$` or `^\d{3}-\d{2}-\d{4}$` -> refuse.

### D-19 name check and print-time mark
```php
// Loader, before the README skip, on the full relative path
if (preg_match('/[\p{Cc}\p{Cf}\p{Zl}\p{Zp}]/u', $path) !== 0) {      // false (malformed) also refuses
    return $this->unrecognized($path, '', 'hidden_name');
}

// ValidationReport::lines(), on the finished line
preg_replace_callback('/[\p{Cc}\p{Cf}\p{Zl}\p{Zp}]/u', fn (array $m): string => in_array($m[0], ["\n", "\r", "\u{2028}", "\u{2029}", "\u{85}"], true) ? '[line break]' : '[hidden character]', mb_scrub($line));
```

### The GBP slot (read-only, one method)
```php
interface GoogleListingSource
{
    /** @return array<string, mixed> */
    public function fetchListing(): array;
}
```
Bound in the provider's `register()` after `parent::register()`, driver from `config('...driver', 'fake')`, `match` with `'fake' => $app->make(FakeGoogleListingSource::class)` and a default that throws `InvalidArgumentException`.

### Example-folder contracts (shape that synced)
```
---
type: room
owns: [rooms/money/notes.md]
reads: none
writes_records: none
connector: none
---
```
and the connector file: `type: connector`, `system`, `direction`, `slot: google_business_profile`, `owns: none`, `reads: none`, `schedule` (the bare word `none`, never `[none]`, per `Loader::NONE_MEANS_EMPTY` and `ConnectorFileTest.php:125`).

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| A room is a test-only pretend room | Real rooms in `bootstrap/providers.php` after `AppServiceProvider` | Phase 5 | `PretendRoomBootTest` assertions about the provider list and room list change |
| HFS+ forced NFD file names | APFS preserves whatever normalization the creating program wrote, normalization-insensitive on lookup | macOS 10.13 (2017) | A name arrives NFC or NFD; D-19 passes both |

**Deprecated/outdated:** the branch messages `...and no rooms yet` (`RoomChecks.php:91-93`) and `this engine has no connector slots yet` (`ConnectorChecks.php`, the `$slots === []` branch) become unreachable in the running app.

## Sizing (for the D-20 split decision)

Counts are estimates grounded in the prototype and the Phase 4 pretend room.

| Piece | App files | Example files | New test files / cases | Existing tests that change |
|---|---|---|---|---|
| Never-left-out + router-wildcard (shared, tests-first) | `RoomChecks.php` (+ `Room.php` if flagged) | -- | 1 file, about 8 cases | none by itself |
| D-19 name refusal + mark (shared) | `Loader.php`, `Validator.php` (`problemMessage`), `ValidationReport.php`, `RoomChecks.php` | -- | 1 to 2 files, about 10 cases (each category, folder, README in hidden folder, NFC and NFD kept, quoted `owns` entry, malformed UTF-8) | `RoomContractTest.php:280` |
| Suite fallout (shared) | `AnnexRoom.php`, annex fixture | -- | -- | 11 files, 43 cases (table above) |
| Company knowledge | provider, section (2) | 1 contract | boot test, about 5 cases | carries about 20 of the 43 |
| Marketing | provider, section (2) | 1 contract | boot test, about 5 cases (live-offer line uses `isLive`) | `OfferValidationTest.php:85` |
| Content generation | provider, section, type (3) | contract + brief (2) | boot test + type tests, about 8 cases | type-sentence tests (first type) |
| Google Business Profile | provider, section, type + checks, interface, fake (5) | contract + categories + connector file (3) | boot, categories shape (7 rules), slot and fake, connector test, about 14 cases | about 14 `ConnectorFileTest` cases |
| Money | provider, section, type, number check (4) | contract + notes (2) | boot + number-check tests (cards by brand, spaced and dashed, 9-digit, `123-45-6789`, phone skip, 13-19 digits failing Luhn pass, EIN), about 15 cases | none beyond the type sentence |
| Docs | -- | -- | `SpecTablesTest` rows | `docs/SPEC.md` (ownership rows, file types rows, reader edits per D-21), `docs/BLUEPRINT.md`, the sprint walk-through log, `tests/Pest.php` count |

**Split analysis.** About 9 plans, the same as Phase 4 (9 plans; the longest was 154 minutes). D-02 and the router fix span both halves of the proposed split (plain rooms: company knowledge, money; marketing rooms: marketing, content generation, GBP), and the heaviest fallout lands with the first room that handles `knowledge` (company knowledge, plain half) and the connector fallout lands with GBP (marketing half). A split into 5 and 5.1 would therefore leave 5 owning almost all of the test rewrites and 5.1 owning the connector ones, with a half-furnished example folder between merges. Recommendation: do not split unless Ian wants smaller merges; if the planner proposes one, split after the shared plans and company knowledge, with GBP first in 5.1. Suggested waves: (1) shared never-left-out and D-19 tests and code, plus the annex and fallout fixes that do not depend on a room; (2) company knowledge with the router fix and the example contract; (3) marketing, content generation, money in parallel; (4) GBP with the slot, connector file and connector fallout, then docs.

## Runtime State Inventory

This phase adds rooms and checks; it is not a rename. Omitted as not applicable, with one state point stated explicitly: **stored data** — a live `icm_versions` row compiled before this phase has no room entries in its `compiled['index']['room']`; `ActiveConfig::hasRoom()` returns false for it and the sections hide, and the first sync after the example folder gains the rooms writes a new version. No migration. [VERIFIED: app/Icm/ActiveConfig.php:69-77 read]

## Environment Availability

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| PHP | everything | yes | 8.5.12 | -- |
| Composer dependencies (`vendor/`) | everything | yes | see Standard Stack | -- |
| SQLite (in-memory) | local tests | yes | via `phpunit.xml` | -- |
| PostgreSQL | ROOM-05 on Postgres | no locally (`pg_isready` not found); `docker` is installed | -- | CI matrix (`.github/workflows/tests.yml` runs `sqlite` and `pgsql`, Postgres 17 service); the five rooms add no table, so a Postgres difference could only come from existing code |
| Node 24, Playwright 1.63, Chromium | axe checks on each section | yes (Chromium builds cached, browser tests ran in the prototype) | node v24.12.0 | CI installs with `npx playwright install --with-deps chromium` |

**Missing dependencies with no fallback:** none. **With fallback:** Postgres locally (use CI, or Docker if Ian wants a local run).

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Pest 5.3.1 with pest-plugin-browser 5.1.2 (axe), on PHPUnit |
| Config file | `phpunit.xml`; `tests/Pest.php` (Feature tests extend `Tests\TestCase`, `RefreshDatabase`, copy `examples/example-hvac` to a temp folder) |
| Quick run command | `php artisan test --compact tests/Feature/Rooms` |
| Full suite command | `php artisan test --compact` (about 2 minutes 10 seconds in the prototype) |

### Phase Requirements to Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| ROOM-08 | Company knowledge boots empty (required files kept) and with example; contract owns equals the SPEC rows; never-left-out line | feature + browser axe | `php artisan test --compact tests/Feature/Rooms/CompanyKnowledgeBootTest.php` | Wave 0 |
| ROOM-09 | Marketing boots; section's live offers equals `OfferSentences` answer; existing offer and lead-source suites pass | feature | `php artisan test --compact tests/Feature/Rooms/MarketingBootTest.php tests/Feature/Icm/OfferValidationTest.php` | Wave 0 (boot), exists (offers) |
| ROOM-10 | Content generation boots empty and with brief; second brief refused; `connector: none` | feature | `php artisan test --compact tests/Feature/Rooms/ContentGenerationBootTest.php` | Wave 0 |
| ROOM-11 | GBP boots; categories shape (required, at most 9, no repeats, none equal to primary, ignoring case and spaces); slot resolves to the fake; `[none]` refused, `none` accepted; nothing sent | feature + Http fake | `php artisan test --compact tests/Feature/Rooms/GoogleBusinessProfileBootTest.php` | Wave 0 |
| ROOM-14 | Money boots; card (Luhn) and 9-digit refusals; phones skipped; no slot, no records | feature | `php artisan test --compact tests/Feature/Rooms/MoneyBootTest.php` | Wave 0 |
| G-04-1 | Hidden-character names refused; marks on file prefixes and quoted values | feature | `php artisan test --compact tests/Feature/Icm/HiddenCharacterTest.php` | Wave 0 |
| D-02 | Never-left-out line and skipped per-file lines | feature | `php artisan test --compact tests/Feature/Rooms/NeverLeftOutTest.php` | Wave 0 |
| ROOM-05 on Postgres | Same boot tests | CI matrix | push; `php artisan test` with `DB_CONNECTION=pgsql` | CI only |

### Sampling Rate
- **Per task commit:** the narrowest file for the task (`php artisan test --compact <file>`).
- **Per wave merge:** `php artisan test --compact tests/Feature/Rooms tests/Feature/Icm`.
- **Phase gate:** full `php artisan test --compact` green on SQLite, then CI green on both databases before `/gsd-verify-work`.

### Wave 0 Gaps
- [ ] `tests/Feature/Rooms/NeverLeftOutTest.php` (D-02, router wildcard)
- [ ] `tests/Feature/Icm/HiddenCharacterTest.php` (D-19; includes NFC and NFD `café.md`, ZWJ, RLO, U+2028, malformed UTF-8)
- [ ] one boot test per room, copying `PretendRoomBootTest.php:284-323` (the `it('boots the pretend room'` block), each ending with `RoomSectionAxe::assertClean(RoomSectionAxe::report($page, '<key>'))`
- [ ] fixes to the 44 existing cases above, assigned to the plan whose room causes them
- [ ] Framework install: none

## Security Domain

### Applicable ASVS Categories
| ASVS Category | Applies | Standard Control |
|---------------|---------|-----------------|
| V2 Authentication | no (Filament's existing login, unchanged) | -- |
| V3 Session Management | no (unchanged) | -- |
| V4 Access Control | yes | Sections render only inside the authenticated Filament panel; `RoomSection::canView()` plus Livewire refusing a hidden widget's requests (`RoomSectionTest.php:55-62`) |
| V5 Input Validation | yes | Owner-written files: `RoomFileType::rules()` and `checks()`, PlainWords-quoted values, D-19 name refusal |
| V6 Cryptography | no | -- |
| V7 Error handling and logging | yes | Refusal lines are stored, written to `changelog.md` and shown on the Business Folder page; D-19 stops control characters and bidi overrides entering them |
| V8 Data protection | yes | D-16: the money notes file refuses card-shaped and 9-digit numbers so account, card and ID numbers never enter the folder |
| V12 Files | yes | Names refused, not normalized; no new file read outside the Loader |

### Known Threat Patterns for this stack
| Pattern | STRIDE | Standard Mitigation |
|---------|--------|---------------------|
| A file name with a line break starts a new line or heading in a stored refusal or `changelog.md` (G-04-1) | Tampering | D-19 Loader refusal plus print-time mark |
| Right-to-left override or zero-width characters make a name or path look like another (Trojan Source) | Spoofing | Same; Cf is in the refused set |
| Card, bank routing or Social Security number written into the folder | Information disclosure | D-16 refusal; CONN-01's later scan |
| A model reads a content brief containing instructions | Tampering | Out of scope: no agent this phase (D-14); the brief is guidance only and `never_say` is enforced elsewhere |
| A slot driver reaching Google | Information disclosure / Tampering | Only the fake is bound; unknown driver throws; `Http::preventStrayRequests()` global in tests; no send method (D-11) |
| Access to a hidden room's section by direct Livewire request | Elevation of privilege | `canView()` and Filament's refusal, proven for the pretend room |

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|---------------|
| A1 | Linux ext4 and SMB shares return file names as stored bytes (and an Apple SMB client may decompose to NFD) | Q3 | None for D-19: the pattern passes NFC and NFD either way; matters only for the explanation |
| A2 | The set of 9-digit strings `toE164()` accepts is "at least 10,056" (`011` set and `113100000-113109999` enumerated exhaustively; other prefixes sampled at 300 each, not enumerated) | Q2 | A few more skippable SSN-shaped numbers than stated; recommendation 2 (no phone skip on bare 9 digits) removes the exposure whatever the set is |
| A3 | A 13-19-digit run beginning `011` or `111` skipped as a phone is acceptable because no real card brand begins that way | Q2 | A fictional or niche card with such a prefix would pass the check; UATP (prefix 1, 15 digits) did show `111` hits in 18 of 450,000 |
| A4 | The never-left-out `Room` flag (versus a constant in `RoomChecks`) is the better shape | Pattern 2 | Cosmetic; either works in the prototype |
| A5 | Tightening card grouping to contiguous, 4-4-4-4 or 4-6-5 is within D-16's intent | Q2 | Ian may want any spaced or dashed 13-19-digit run checked, at the cost of the false refusals described |
| A6 | All five example rooms must stay furnished for the pretend boot test's `assertDontSee('nothing set up yet')` | Pitfall 10 | A test failure, not a behavior one |

## Open Questions

1. **What label does the Google Business Profile room carry?**
   - Known: `Room::label` is documented lower case; SPEC and the owner allow-list use `Google Business Profile`; the prototype read fine with the capitalized label.
   - Unclear: whether sentences should say `the Google Business Profile room` or a lower-case form.
   - Recommendation: capitalized proper noun, and update the `Room` docblock.

2. **Is changing `RoomChecks::claims()` to skip routers matched by a wildcard acceptable?**
   - Known: required for `business/services/*.md` (Pitfall 1); exact router names stay refused.
   - Unclear: Ian's view on touching a Phase 4 check.
   - Recommendation: do it, tests-first, in the first shared plan.

3. **Employer ID format `12-3456789`.**
   - Known: D-16 lists "a tax ID" but its two forms (`123456789`, `123-45-6789`) do not match the usual 2-7 grouping.
   - Recommendation: add the 2-7 form, or reword D-16's "tax ID" claim; Ian's call, because D-16 is a locked decision.

4. **Two tests become untestable by editing their expectation, and CLAUDE.md says never delete a test without approval.**
   - `tests/Feature/Rooms/RoomContractTest.php:217-225` (`...and no rooms yet`) and `tests/Feature/Rooms/ConnectorFileTest.php:244-250` (`no connector slots yet`) assert branches that need an empty registry or no slots.
   - Recommendation: keep both branches covered by moving each to a unit test that builds `new RoomChecks(new RoomRegistry)` and `new ConnectorChecks(...)` with no rooms (both are pure functions of the file list), and ask Ian to approve removing the feature tests.

5. **Success criterion 3 versus `OfferValidationTest.php:85`.** A hard-coded `14` there must become `exampleFolderFileCount()`; confirm that counts as "passes unchanged".

6. **Where does the never-left-out reason text live, and what does it say?** Placeholder wording only (D-02); the prototype used `it owns the profile, hours and voice files the sync requires` and `it owns the lead sources file the sync requires`.

7. **Which near-miss replaces `Rooms/pretend/CONTEXT.md` in `PretendRoomBootTest.php:115`?** Planner's choice (Pitfall 7).

8. **Owner experience for extra `knowledge` files.** Because D-04 gives company knowledge every `knowledge` file wherever it sits, an owner who adds `business/warranty.md` is refused with `no room lists this knowledge file under owns; the company knowledge room handles it, so add it to rooms/company_knowledge/CONTEXT.md`. Actionable, and consistent with the ownership rule; confirm it is wanted, since the alternative (an owns glob over `business/*.md`) would make SPEC's row and the boot comparison say something else.

9. **Hidden-name files feeding the folder lines** (Pitfall 3): confirm `$folders` should ignore them.

10. **D-19 refuses ZWJ and ZWNJ in names** (emoji sequences; Persian, Kurdish, Indic words). Intended, but it means a client with such a file name renames it. Ian's call whether to accept that or exempt U+200C and U+200D.

## Sources

### Primary (HIGH confidence)
- Codebase files read this session: `app/Icm/RoomChecks.php`, `Loader.php`, `Validator.php`, `ValidationReport.php`, `PlainWords.php`, `ConnectorChecks.php`, `FolderSync.php`, `ActiveConfig.php`; `app/Rooms/Room.php`, `RoomServiceProvider.php`, `RoomRegistry.php`, `RoomFileType.php`, `RoomSection.php`; `app/Support/PhoneNumber.php`; `app/SendRule/OfferSentences.php`; `tests/Pest.php`; `tests/Feature/Rooms/*`; `tests/Rooms/PretendRoom/*`; `tests/Fixtures/*`; `examples/example-hvac/*`; `composer.json`, `composer.lock`, `package.json`, `phpunit.xml`, `.github/workflows/tests.yml`; `docs/SPEC.md` (ownership, kinds, file types); `.planning/phases/05-standard-rooms/05-CONTEXT.md`, `04-CONTEXT.md`, `04-UAT.md`, `STATE.md`, `REQUIREMENTS.md`.
- Throwaway prototype (outside the repo, in the session scratchpad): five providers, furnished example folder, D-02 and D-19 in minimal form; full-suite runs before and after (`full1.json`, `full2.json`); PHP probes for `PhoneNumber`, PCRE categories, Finder listing on APFS. Nothing was added to the repo.

### Secondary (MEDIUM confidence)
- Apple File System Guide, FAQ "How does Apple File System handle filenames?" https://developer.apple.com/library/archive/documentation/FileManagement/Conceptual/APFS_Guide/FAQ/FAQ.html (APFS preserves case and normalization, normalization-insensitive; case-insensitive default on macOS; HFS+ stores decomposed form)

### Tertiary (LOW confidence)
- Behavior of Linux and SMB file name normalization (training knowledge, A1).

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH, nothing added; versions read from `composer.lock`
- Architecture and patterns: HIGH, each pattern ran in the prototype
- Test fallout (Q1): HIGH, measured by a full-suite run; the cause column is reasoned from the failure output, and the split of fallout by room was not re-run room by room
- Phone and number behavior (Q2): HIGH for what was probed; the 9-digit accepted set is exhaustive for two prefixes and sampled for the rest (A2)
- Names and normalization (Q3): HIGH on this Mac and for the pattern, MEDIUM for other filesystems
- Pitfalls: HIGH, each observed

**Research date:** 2026-10-25
**Valid until:** about 30 days, or until a Phase 4 file listed above changes
