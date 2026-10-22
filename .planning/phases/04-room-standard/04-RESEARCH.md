# Phase 4: Room Standard - Research

**Researched:** 2026-10-22
**Domain:** Laravel 13 module registration (service providers), the ICM folder sync (`app/Icm/*`), Filament 5 dashboard widgets, test-only registration, automated accessibility checks (axe, WCAG 2.2 AA)
**Confidence:** HIGH on how the room standard plugs into the existing code (every hook point was read and three of them were run in a scratch test); MEDIUM on the accessibility tool (documented and source-read, but not installed or run, because Ian forbade installs)

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

D-01 to D-27 are locked. They are copied verbatim from `.planning/phases/04-room-standard/04-CONTEXT.md` (the `<decisions>` block).

#### Room folder and contract
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

#### Who owns today's files
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

#### Code home and first proof
- **D-18:** `app/Rooms/` is approved as a new `app/` folder (Ian). Add its row to the CLAUDE.md Map when Phase 4 creates it. This settles the folder only: the module shape inside it (a service provider per room, a modules package, or Filament clusters) is researched at plan time, as scoped 2026-10-21. Anything that needs a new composer package or its own top-level folder goes back to Ian. Migrations stay in `database/migrations`. Affects: none (code layout only).
- **D-19:** Code that exists today stays where it is, by kind, and the room points at it; code a room phase writes goes in `app/Rooms/<Room>/`. The room contract must allow a room's registrations to point at classes outside `app/Rooms/<Room>/` (the front desk's `TextChannel` slot stays in `app/Channels`). This settles the Phase 6 planning note "whether a wrap moves code into the room's module". Affects: customer communication, team.
- **D-20:** The standard is proven with a pretend room that exists only in tests and exercises every part: one file type with an example file, one table, a dashboard section, a timeline entry, and a connector slot with a fake driver plus a connector file declaring `owns` and `reads`. A second fixture connector claiming the same kind proves the refusal. Its code and folder live under `tests/`. It is registered from the test setup, never from `bootstrap/providers.php`, and never goes in `app/Rooms/` or `examples/example-hvac/`, so nothing ships, the tour never shows it, and `exampleFolderFileCount()` doesn't move. Its table's migration loads only in tests. Phase 4 shows all five success criteria end to end with it. Affects: none (test code only).
- **D-21:** An empty room's dashboard section shows one plain line when the room owns files and none of them are there yet. The line names those files from the room's `CONTEXT.md`, wherever they live (money's are in `rooms/money/`, other rooms' may not be). A room that owns no files (team in v2.0) is never shown as empty; its section shows whatever records it has. The platform renders the line from `CONTEXT.md` the same way for every room, so no room builds its own empty screen and one test covers it. The wording is a placeholder, settled with the rest of the wording once the full workflow exists (closing principle, 2026-10-17), and laid out in the phone design pass (Phase 11). No exclamation points or em dashes. Affects: knowledge.

#### What a connector can own
- **D-22:** The kinds a connector may own or read are one fixed list in code, keyed exactly as the 2026-10-19 decision names them: customers, sales activity, invoices, payments, expenses, work orders, the schedule, documents (key spelling such as `sales_activity` is the planner's, held to those eight). The code list is what the sync enforces. `docs/SPEC.md` lists the same eight beside the ownership table (in Phase 4 execution), and a test fails if the two disagree (the D-14 pattern). `owns` and `reads` check against the same list; a word on neither list is refused in plain words naming the file and listing the kinds. A kind on the list is accepted even when the platform keeps no records of it yet (invoices in v2.0): owning it says the outside system is the truth, and the platform never treats its own copy as truth. Ownership stays per kind; per-field waits for a client (2026-10-19). Affects: connectors and data. — **Reversibility:** costly — key names end up in every client's connector files.
- **D-23:** Projects, texts and the timeline go on a second list in the same code: kinds the platform owns, which a connector may read but never own. The refusal names the file and the kind and says the platform owns it. Texts stay the platform's for a locked reason: the sent message is the platform's own row (Phase 2 D-28/D-54) and every automated send goes through `OutboundSender` (02.1 D-49), so the text channel's connector file owns nothing. Other platform records (conversations, owner alerts, drafts) are on neither list and are refused like any unknown word. `reads: projects` is accepted from Phase 4 even though the Project record lands in Phase 7 (same rule as invoices). Moving a platform kind onto the ownable list is its own decision with Ian. SPEC lists both lists, tied to the code by the same test. Affects: connectors and data, customer communication.
- **D-24:** `connector` moves from a later-sprint type to a checked type. Phase 4 checks `owns`, `reads` and `slot` only (CONN-07), with plain-words errors. `system`, `direction` and `schedule` are allowed and passed through unchecked; SPEC's connector row says which fields Phase 4 checks and which wait for CONN-01. CONN-01 stays unchanged in Future Requirements and checks `system`, `direction`, `schedule` and the no-secrets scan together, with the first real connector. `schedule` has no defined format yet, so Phase 4 doesn't invent one. Affects: connectors and data.
- **D-25:** The slot link: the room's `CONTEXT.md` connector field names its slot (or none); the connector file's `slot` field says which slot it fills; slot names are registered by room code. That is the only link: the room's `CONTEXT.md` does not also list its connector file under `owns`, so ownership is stated once (the SPEC table row reads "the connector file filling the slot"). The sync refuses, in plain words naming the file: a slot no present room has, a slot name no room registers, and two files filling one slot. A room with a slot and no connector file is a valid empty room. `slot` is checked from Phase 4 alongside `owns` and `reads`, because ROOM-01 ownership depends on it. The front desk's text slot runs from `.env` today (`channels.text.driver`) with no connector file, which this allows; Phase 6 decides whether to add one. Affects: connectors and data.
- **D-26:** The two-owner refusal uses the success-criterion wording: "customers is owned by both connectors/a.md and connectors/b.md; pick one, the other can read it". The previous version stays live. Affects: connectors and data.

#### Accessibility (Ian, 2026-10-22)
- **D-27:** The room standard includes an automated accessibility check (axe rules, WCAG 2.2 Level AA) on each room's dashboard section, including the D-21 empty line, run with the room's boot test in CI (A11Y-01). The pretend room (D-20) proves it in Phase 4. For the front desk and team rooms the check starts in Phase 11, because 02.1 D-48 holds styling until the phone walk. Which tool runs the check is a research question; any new composer or npm package it needs goes back to Ian before it is added (D-18). Affects: all ten areas (every room's dashboard section), led by safety and compliance.

### Claude's Discretion
- How rooms add timeline entries, given `App\Enums\ActivityType` is one fixed list today; researched and planned.
- How a room registers its file types with the sync while today's types stay in `Validator`/`Loader::TYPED` (D-19 lets existing types stay put); the module shape inside `app/Rooms/` (D-18).
- Exact front matter field names in the room contract (D-02) and the `writes_records` shape (list of `records/` paths or none).
- Plain-words wording of the new refusals, in the style of the existing `PlainWords` messages; placeholder wording is fine (2026-10-17 principle).

### Deferred Ideas (OUT OF SCOPE)
- **Phase 7 discuss:** a room that needs another room's records (booking attaches to a project, and only leads opens one), so the room contract may need a `needs` field and a sync refusal when the room it needs is absent. Adding the field later is additive, so Phase 4 doesn't wait on it.
- **Later call:** split `owner_alert` out of `rules/sending.md` into a team-owned file (D-13).
- **Phase 6:** whether the front desk's text slot gets a connector file (D-25).
- **Phase 5:** the money room's one fictional example file, chosen so it carries no money rules 999.1 would rework (D-05).
- Reviewed todo not folded: "Phone design pass for the dashboard, the Needs you card and the Business Folder page" belongs to Phase 11.
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| ROOM-01 | Room folder with a `CONTEXT.md` contract; empty room passes; one owner per file | Q3: Loader narrow change, `room` typed file, `RoomChecks` whole-folder checks (unknown room, double claim, claim of an unregistered type); FolderSync reject path already keeps the previous version live |
| ROOM-02 | A room is a Laravel module registering file types, tables, dashboard section, timeline entries | Q1 (service provider per room + `RoomRegistry`), Q2 (widget), Q3 (file types), Q4 (timeline), Q5 (boot order) |
| ROOM-03 | Connector slot (interface + fake driver) and a connector file declaring `owns` / `reads`; a room with no outside system says so | Q3 connector checks (D-22 to D-26); slot pattern is the `TextChannel` binding in `AppServiceProvider` |
| ROOM-04 | `CONTEXT.md` states what the room writes to `records/` | Q3: `writes_records` field (list, may be empty), validated with `present|array` |
| ROOM-05 | One boot test per room, empty and with its example folder, on SQLite and Postgres | "Pretend room test harness" section; CI matrix confirmed |
| CONN-07 | Connectors say `owns` / `reads`; two owners refused in plain words | Q3: `OwnableKind` / `PlatformKind` lists, the two-owner check with the D-26 wording |
| A11Y-01 | Automated axe check (WCAG 2.2 AA) on each room's dashboard section incl. the empty line, in CI | Q6: Pest browser plugin recommendation, with exactly what Ian must approve |
</phase_requirements>

## Project Constraints (from CLAUDE.md)

Treat these with the same authority as the locked decisions. [VERIFIED: /Users/iantitus/Projects/ops-platform/CLAUDE.md, .ai/rules/app.md, .ai/rules/tests.md, .ai/rules/migrations.md]

- Files hold how the business runs; the database holds what happened. **Code reads business configuration only through `App\Icm\ActiveConfig`.** Only `App\Icm\Loader`, run by the sync, reads the folder. (A `RoomChecks` class in `app/Icm` must work from the `LoadedFolder` the Loader gives it, never from disk.)
- **Money is integer cents.** Nothing in Phase 4 stores money; the pretend table must not have a money column.
- **Tests first** for anything touching the sync or sending. Run `php artisan test` before calling a step done. `workflow.tdd_mode` is `true` in `.planning/config.json`.
- Sending authority is enforced in code. (Not touched in Phase 4; note that a connector file owning `texts` is refused by D-23, which protects `OutboundSender`.)
- **Ian is learning Laravel.** The first time a Laravel concept appears, explain it in a few plain sentences tied to the code just written and add one line to the walk-through log at the end of the current sprint file in `docs/sprints/` (see the `## Walk-through log: plain words` section below).
- Git under GSD: local commits, one per task, on the phase branch (`gsd/phase-04-room-standard`). Never push, never merge into main, never rewrite history.
- No secrets in the repo or ICM folders. Examples and test data are fictional.
- `docs/BLUEPRINT.md` gets updated in the same commit as any decision that changes an area ("Affects:" line).
- `app/Rooms/` is the one new base folder approved (D-18). Add its row to the CLAUDE.md Map in Phase 4. **Do not change the application's dependencies without approval** (Boost rule) which is why the accessibility packages come back to Ian.
- Laravel conventions: curly braces always, constructor promotion, explicit return types, TitleCase enum keys, PHPDoc over inline comments, `php artisan make:` commands, Pest tests via `php artisan make:test --pest`, run `vendor/bin/pint --dirty --format agent` after PHP edits.
- Boost rules say to read `.ai/rules/index.md` mapped rules before editing `app/**`, `tests/**`, `database/migrations/**`: done above (each file says money is integer cents; `app/**` adds the ActiveConfig rule).
- Tests under `Feature` get the shared `beforeEach` in `tests/Pest.php` (folder copy to a temp dir, fake channel, `Http::preventStrayRequests()`, `Queue::fake([ProcessInbound::class])`). Unit tests do not.
- `tests/Unit/ArchitectureTest.php` has arch rules that bind classes under `Tests\` too: the `File` facade is only allowed in `App\Icm\Loader`, `App\Icm\Changelog`, `App\Records\RecordsWriter`; Symfony `Finder` and `Yaml` only in the Loader. **A pretend-room class under `tests/Rooms/` must not use the `File` facade** (closures in test files are fine; classes are not).

## Summary

The room standard needs only plain Laravel pieces already in the repo: a service provider per room, one container-bound registry of "what rooms exist in code", and small hooks in `Loader`, `Validator`, `FolderSync::compile()`, `ActiveConfig`, `AdminPanelProvider` and `CustomerCard`. No new composer package is needed for the module shape, and no new top-level folder. The sync part (room contract, connector checks, two-owner refusal) is a pure addition to the existing report: it only adds errors, so the "refuse and keep the previous version live" path in `FolderSync::reject()` works unchanged. Three behaviours that the plan depends on were run in a scratch test outside the repo (SQLite): a provider registered after the app has booted runs `register()` then `boot()` at once; `loadMigrationsFrom()` plus `artisan('migrate')` creates the pretend table inside the test's transaction and it is gone in the next test; and a widget added to the already-built admin panel after boot appears on `/admin` and disappears when its `canView()` returns false.

The two decisions that need Ian before planning: (1) module shape, recommended plain service provider per room (no package, covered below), and (2) the accessibility tool. Colour contrast and the WCAG 2.2 target-size rule need a real browser (axe documents that contrast does not work in jsdom). The only option that runs inside the Pest suite on both CI databases with a real browser is the Pest browser plugin (`pestphp/pest-plugin-browser`) plus the `playwright` npm package plus a Chromium install in CI. It bundles axe, so there is no separate axe package. It costs two packages, a CI change and a browser download; the cheaper alternative (axe-core + jsdom, two npm packages, no browser) cannot check contrast and should be chosen only if Ian wants the lighter cost and accepts that gap.

**Primary recommendation:** Build the standard as `app/Rooms/` = `Room` descriptor + `RoomRegistry` singleton + abstract `RoomServiceProvider` (register-time only: static data), consumed at sync time by `Loader` / `Validator` / `FolderSync::compile()` and at request time by `AdminPanelProvider` (widgets) and `CustomerCard` (timeline); every "is this room on?" decision goes through a new never-throwing `ActiveConfig::hasRoom()` at the moment the code runs, never at boot; add one `ActivityType::RoomEntry` case plus nullable `room` and `entry` columns for timeline entries; prove accessibility with the Pest browser plugin scoped to the room section with the five WCAG tags.

## Architectural Responsibility Map

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|-------------|----------------|-----------|
| Room contract and file checks (`type: room`, `connector`, ownership) | Sync / API-Backend (`app/Icm`) | Business folder (files) | The folder is read only by the Loader; the Validator turns it into plain-words errors; the live copy is the compiled JSON on `icm_versions` |
| "Is this room on right now?" | API-Backend (`ActiveConfig`, per request or job) | — | D-08 says the active version decides at run time; `ActiveConfig` is the only reader of business configuration |
| Which rooms exist in code (file types, slots, widgets, timeline entry types) | API-Backend (`RoomRegistry`, filled in provider `register()`) | — | Static facts about code; safe at boot because they touch no database |
| Room tables | Database / Storage (`database/migrations`) | — | D-18: migrations stay in `database/migrations`; only the pretend room loads its own path (test-only) |
| Dashboard section and the empty line | Frontend Server (Filament widget, Livewire) | API-Backend (`ActiveConfig::hasRoom`, contract `owns`) | Existing widgets are Livewire components discovered under `app/Filament/Widgets`; `canView()` hides it |
| Timeline entries | Database (`activities` row) | API-Backend (registry renders label/intro), Frontend Server (infolist, `CustomerCard`) | The timeline is one table; rooms add rows of one new enum case and the registry knows how to draw each entry |
| Connector slot | API-Backend (interface + fake driver bound in the container) | Business folder (`connectors/*.md` names the slot) | Same pattern as `TextChannel` in `AppServiceProvider` |
| Accessibility check | Browser (axe running in headless Chromium) | Test runner (Pest) | Contrast and target size need real layout; the server only supplies the page |

## Standard Stack

### Core (already installed; ground truth from `composer show` this session)
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| laravel/framework | 13.35.0 | Service providers, container, scheduler, migrator | [VERIFIED: composer show --direct] |
| filament/filament | 5.10.1 | Admin panel, widgets, `canView()` | [VERIFIED: composer show --direct] |
| livewire/livewire | v4.4.7 | Widgets are Livewire components | [VERIFIED: composer show livewire/livewire] |
| pestphp/pest | 5.3.1 | Test runner | [VERIFIED: composer show --direct] |
| pestphp/pest-plugin-laravel | 5.0.1 | `actingAs`, `artisan`, `Livewire::test` helpers | [VERIFIED: composer show --direct] |
| symfony/yaml | (transitive; used by the Loader) | Front matter parsing; `connector: none` parses to the string `"none"` and `owns: []` to an empty list | [VERIFIED: php -r run this session] |
| PHP | 8.5.0 | Runtime | [VERIFIED: php -v] |

`pestphp/pest-plugin-arch` and `pestphp/pest-plugin-mutate` / `-profanity` are present under `vendor/pestphp` (arch is in use in `tests/Unit/ArchitectureTest.php`). [VERIFIED: ls vendor/pestphp]

### New (all need Ian's approval; nothing was installed)
| Package | Version | Purpose | When |
|---------|---------|---------|------|
| `pestphp/pest-plugin-browser` (composer, dev) | v5.1.2 (latest on Packagist; requires `pestphp/pest ^5.2.1`, `php ^8.4`, `symfony/process ^8.1.7`, `ext-sockets`, plus `amphp/amp`, `amphp/http-server`, `amphp/websocket-client`) | Runs the page in Chromium through Playwright and bundles axe-core v4.10.3 | Recommended for A11Y-01 |
| `playwright` (npm, dev) | plugin pins `^1.63.0`; npm latest is 1.64.1 | The browser driver the plugin talks to | Recommended for A11Y-01 |

Versions checked: `composer show -a pestphp/pest-plugin-browser` (Packagist metadata, requires list) and `npm view playwright version` (1.64.1) this session. The plugin's own `package.json` content (`"playwright": "^1.63.0"`) and the bundled `axe v4.10.3` header were read from the plugin's GitHub source at tag v5.1.2. [VERIFIED: composer show -a, gh api, npm view]

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Service provider per room | nwidart/laravel-modules (composer) | Needs a package (D-18), defaults to a top-level `Modules/` folder, module on/off lives in a repo file not in the business folder; see the Q1 table |
| Service provider per room | Filament clusters | A cluster only groups admin pages and resources; it registers no file types, tables, timers or timeline entries; see Q1 |
| Pest browser plugin | `axe-core` + `jsdom` run by Node from a Pest test (npm only) | Cheaper (no browser, no composer package, about a second per check) but axe documents that `color-contrast` does not work in jsdom, and target-size needs layout, so a green run would not cover those WCAG 2.2 AA checks |
| Pest browser plugin | Playwright + `@axe-core/playwright` driven by a separate npm script in CI | Not "run with the room's boot test"; needs a live server and seeded database outside the Pest transaction; most moving parts |

**Installation (for Ian to approve, NOT run):**
```bash
composer require --dev pestphp/pest-plugin-browser:^5.1
npm install --save-dev playwright@^1.63
npx playwright install --with-deps chromium
```

## Package Legitimacy Audit

| Package | Registry | Age | Downloads | Source Repo | Verdict | Disposition |
|---------|----------|-----|-----------|-------------|---------|-------------|
| playwright | npm | long-established (Microsoft) | 69.8M/wk | github.com/microsoft/playwright | SUS (reason `too-new`: latest release published 2026-10-14) | Flagged — planner adds a `checkpoint:human-verify`; the flag is the seam's "newest release is days old" heuristic, not a young package. Pin `^1.63.0`, do not install `@latest` blindly |
| axe-core | npm | long-established (Deque) | 49.5M/wk | github.com/dequelabs/axe-core | SUS (`too-new`: latest published 2026-10-05) | Only needed for the jsdom alternative; the Pest plugin bundles its own axe. Flagged the same way |
| jsdom | npm | long-established | 68.0M/wk | github.com/jsdom/jsdom | SUS (`too-new`: 2026-10-15) | Only for the jsdom alternative |
| @axe-core/playwright | npm | long-established (Deque) | 9.4M/wk | github.com/dequelabs/axe-core-npm | SUS (`too-new`: 2026-10-15) | Only for the not-recommended Node-driven option |
| pestphp/pest-plugin-browser | Packagist | v4.0.0 to v5.1.2 series | not retrievable | github.com/pestphp/pest-plugin-browser | not checked by the seam (the seam only supports npm, pypi, crates) | `[ASSUMED]` legitimate: name appears on the official Pest documentation page `pestphp.com/docs/browser-testing` (`composer require pestphp/pest-plugin-browser --dev`) and in the `pestphp` organisation; Ian approves the install anyway |

**Packages removed due to [SLOP] verdict:** none.
**Packages flagged as suspicious [SUS]:** playwright, axe-core, jsdom, @axe-core/playwright (all `too-new` only: `signals.postinstall` is `null` for each, repos are the vendors' own). The planner inserts a `checkpoint:human-verify` before each install. Every one of these is tied to Ian's approval anyway (D-18, D-27).

Source: `node .claude/gsd-core/bin/gsd-tools.cjs query package-legitimacy check --ecosystem npm playwright axe-core jsdom @axe-core/playwright` run this session.

## Architecture Patterns

### System Architecture Diagram

```
 SYNC TIME (php artisan icm:sync, "Sync Now" button)                    RUN TIME (request, queued job, scheduler tick)
 ────────────────────────────────────────────────                       ──────────────────────────────────────────────
 business folder on disk                                                 Filament /admin dashboard
   rooms/<room>/CONTEXT.md  (type: room)                                   │  Dashboard::getWidgets() -> canView() on each widget
   <room's example file>, connectors/*.md, today's files                   ▼
        │                                                                RoomSection widget ── canView(): ActiveConfig::hasRoom('<key>')
        ▼                                                                   │ yes: render content, or the D-21 empty line
 Loader (only file reader)                                                  │      (named files from the contract's `owns`)
   ├ ROUTERS check: CONTEXT.md is a router UNLESS path = rooms/<x>/CONTEXT.md        no: widget is not drawn at all (D-08)
   ├ type in TYPED + room-registered types?  ◄──────────┐
   └ -> LoadedFolder (IcmFile list)                      │               Timeline (CustomerCard, Contact infolist)
        │                                                │                 Activity rows, type = RoomEntry, room, entry
        ▼                                                │                 -> RoomRegistry says how to label each entry
 Validator                                               │                 -> rows of an absent room are not shown (scope)
   ├ per file: rulesFor() (today's types) + registered types' rules
   ├ whole folder: today's checks + RoomChecks ──────────┤               Scheduler / jobs (room code)
   │     unknown room (D-07)        absent owner room (D-10)                 Schedule::...->when(hasRoom) and hasRoom again in handle()
   │     double claim (D-15)        claim of unregistered type (D-17)
   │     connector owns/reads kinds (D-22/23)  slot checks (D-25)        ActiveConfig  (scoped: one per request/job)
   │     two owners of one kind (D-26)                                     reads compiled JSON of the ACTIVE icm_versions row
   ▼                                                                       never the disk, never at boot
 ValidationReport
   errors?  ── yes ──> FolderSync::reject(): store rejected version, previous stays live (unchanged code)
   │ no
   ▼
 FolderSync::sync(): fingerprint -> new IcmVersion -> compile() (new arms: room, connector, registered types)
   -> app()->forgetInstance(ActiveConfig::class)

 BOOT TIME (every process start; no database, no ActiveConfig)
 ────────────────────────────────────────────
 bootstrap/providers.php: AppServiceProvider, AdminPanelProvider, <RoomProvider>...
   register() x all  ->  RoomRegistry gets each Room descriptor (class names, file type names, slot name, widget classes, timeline entry types)
   boot()     x all  ->  Filament's boot builds the panel (AdminPanelProvider::panel() runs, reads RoomRegistry widgets)
                         room boot(): morph map, schedule hooks, event listeners
 (Pretend room: $app->register(PretendRoomServiceProvider::class) in test setup -> register() then boot() immediately)
```

### Recommended Project Structure
```
app/
├── Rooms/                         # D-18 (new). Add the row to the CLAUDE.md Map
│   ├── Room.php                   # readonly descriptor: key, label, types it handles, new RoomFileType objects, slot, widgets, timeline entry types, morph map
│   ├── RoomRegistry.php           # bound singleton; room providers add to it in register(); Loader/Validator/AdminPanelProvider/CustomerCard read it
│   ├── RoomServiceProvider.php    # abstract base: register() adds room(); boot() does morph map + schedule hooks
│   ├── RoomFileType.php           # interface for a NEW file type a room defines: type(), rules(IcmFile), checks(IcmFile)
│   ├── RoomSection.php            # abstract Filament Widget: canView() via hasRoom, shared empty-line view (D-21), $isLazy = false
│   └── TimelineEntryType.php      # value object: key, label, intro line
├── Enums/
│   ├── OwnableKind.php            # the eight D-22 kinds
│   └── PlatformKind.php           # projects, texts, timeline (D-23)
├── Icm/
│   ├── RoomChecks.php             # whole-folder checks, same {file, field, message} error shape as Validator::error()
│   └── (Loader, Validator, FolderSync, ActiveConfig get small edits)
database/migrations/               # one migration: nullable room + entry columns on activities (D-18: migrations stay here)
tests/
├── Rooms/PretendRoom/             # code that exists only in tests (D-20): provider, file type, interface + fake, widget, model, migrations/
├── Fixtures/pretend-room/         # the folder laid on top of a copy of examples/example-hvac
└── Feature/Rooms/                 # contract, boot, connector, SPEC-table, accessibility tests
```

### Pattern 1: the room as a service provider that only records facts at `register()`
**What:** `RoomServiceProvider::register()` adds one `Room` descriptor to `RoomRegistry`. It touches no database, no `ActiveConfig`, no Filament panel. Everything that depends on "is the room on" is decided later, when the code runs.
**When to use:** every real room (listed in `bootstrap/providers.php`, Phase 5 and 6) and the pretend room (registered with `$app->register()` from test setup).
**Example (proposed new code, not in the repo yet):**
```php
// Source: design built on the verified behaviour of Illuminate\Foundation\Application::register()
// (vendor/laravel/framework/src/Illuminate/Foundation/Application.php:891-933)
abstract class RoomServiceProvider extends ServiceProvider
{
    abstract protected function room(): Room;

    public function register(): void
    {
        $this->app->make(RoomRegistry::class)->add($this->room());
    }

    public function boot(): void
    {
        Relation::morphMap($this->room()->morphMap);   // merge = true by default
    }
}
```
RoomRegistry itself is bound in `AppServiceProvider::register()` next to the existing `$this->app->scoped(ActiveConfig::class);` (line 45) as `$this->app->singleton(RoomRegistry::class);`. AppServiceProvider is first in `bootstrap/providers.php`, so the singleton exists before a room provider registers. If a room provider were ever listed before it, `make()` would build a throwaway unshared registry: keep the order and say so in the base class PHPDoc.

### Pattern 2: the sync asks the registry, the registry never asks the sync
The registry holds code facts only. `Loader` accepts registry-added types as `Typed`; `Validator` runs a registered type's rules; `RoomChecks` needs the loaded files plus the registry. The registry never reads the folder (only the Loader does, per the arch test).

### Pattern 3: runtime gate through `ActiveConfig`
```php
// Proposed addition to ActiveConfig (never throws, like displayTimezone()):
public function hasRoom(string $key): bool
{
    try {
        return isset($this->version()->compiled['index']['room'][$key]);   // a version compiled before rooms existed has none
    } catch (NoActiveVersion) {
        return false;
    }
}
```
`displayTimezone()` in the same class already does this try/catch for the same "before the first sync" reason. [VERIFIED: app/Icm/ActiveConfig.php `displayTimezone`]

### Anti-Patterns to Avoid
- **Reading `ActiveConfig` in a provider's `register()` or `boot()`.** See Q5. It throws or queries a table that may not exist.
- **Deciding at boot whether to register a widget, listener or schedule entry.** The decision goes stale after the next sync in a long-running worker and breaks "adding the folder turns the room on at the next sync" (D-08).
- **A `static` array of rooms or a cached list on a class.** Laravel builds a fresh app per test, but PHP statics survive across tests in one process.
- **Moving today's types into the registry.** D-19 says they stay in `Loader::TYPED` and `Validator::rulesFor()`.
- **Putting the pretend room in `bootstrap/providers.php`, `app/Rooms/` or the example folder** (D-20).

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Parsing `owns` patterns | A regex engine for globs | `Illuminate\Support\Str::is($pattern, $path)` (the `*` wildcard) for matching a file path against an `owns` pattern | Already in the framework; the patterns are only paths, `business/services/*.md` style |
| Plain-words messages | New message plumbing | `PlainWords::oneLine()` for every owner-written value; `Validator::error()` shape `{file, field, message}`; `PlainWords::render('In', ...)` where "must be one of" reads right | Keeps the sync's output uniform and changelog-safe (a value can never start a new line or bullet) |
| Per-file rules for a room's new file type | A second validator | Laravel's validator via the same `LaravelValidator::make($file->frontMatter, $rules)` call `Validator::fileErrors()` already makes, with rules supplied by the room's `RoomFileType::rules()` | Same messages, same `PlainWords` mapping |
| The reject-and-keep-previous path | A second reject flow | `FolderSync::reject()` as it is | New checks only add errors to `ValidationReport` |
| Testing a provider that registers late | A custom bootstrap | `$this->app->register(PretendRoomServiceProvider::class)` | Runs `register()` then `boot()` when the app is already booted; run in the scratch test |
| Test-only table | `Schema::create` copied into the test | The room's own migration file loaded with `loadMigrationsFrom()` and run with `$this->artisan('migrate')` in the test | Proves the same mechanism a real room's table uses, and rolls back with the test transaction |
| Accessibility rules | Hand-written HTML assertions (labels, headings) | axe (via the Pest browser plugin, which bundles it) | A hand-rolled subset would not be "axe rules, WCAG 2.2 AA" (D-27) |
| Dashboard-section visibility | A custom Livewire hide/show | `Widget::canView()` (static), which `Page::getWidgetsSchemaComponents()` and `filterVisibleWidgets()` both call | Verified in `vendor/filament/filament/src/Pages/Page.php:497-499, 585-590` |

**Key insight:** every piece of this phase already has a seam in the repo (reject path, `PlainWords`, `ActiveConfig`, `canView`, `register()`/`boot()`); the work is small hooks plus tests, so a package would add more than it removes.

## Q1. The module shape inside `app/Rooms/`

Compared on Ian's three tests. Evidence: `composer show -a nwidart/laravel-modules` metadata (requires `wikimedia/composer-merge-plugin ^2.1`, `ext-json`, `ext-dom`, `ext-simplexml`, `php ^8.3`; versions list includes v13.0.0) [VERIFIED: composer show -a]; the package's own introduction page says modules live in "a top-level `Modules/` directory by default" [CITED: laravelmodules.com/docs/13/getting-started/introduction]; Filament cluster source at `vendor/filament/filament/src/Clusters` and `HasComponents` (`discoverClusters`, `registerToCluster`) [VERIFIED: vendor/filament/filament/src/Panel/Concerns/HasComponents.php].

| Option | (a) New composer package? | (b) Can a room point at classes outside its folder? (D-19) | (c) Pretend room registers from test setup only? (D-20) | Fits D-08 (on/off from the business folder at run time)? | Verdict |
|---|---|---|---|---|---|
| **Service provider per room + `RoomRegistry`** | No | Yes: the `Room` descriptor holds class names, any namespace (`TextChannel` stays in `app/Channels`) | Yes: `$this->app->register(PretendRoomServiceProvider::class)` in test setup. Run in a scratch test: `register()` then `boot()` fire in order, and `loadMigrationsFrom()` works | Yes: the provider is always registered, the room's behaviour checks `ActiveConfig::hasRoom()` when it runs | **Recommended** |
| nwidart/laravel-modules | Yes (plus a Composer plugin, `wikimedia/composer-merge-plugin`) | Partly: modules are folders with their own namespace; pointing outside works in PHP but fights the package's structure | Awkward: it discovers modules by scanning a configured directory and keeps enabled state in a file in the repo; the test-only module under `tests/` would need a custom path | No: a module is enabled or disabled by a repo-level status, not by the client's business folder | Reject |
| Filament clusters | No | n/a | n/a | n/a | Not a module shape: a `Cluster` groups admin Pages and Resources under one navigation item (`registerToCluster()` only handles classes that have `getCluster()`); it registers no file types, tables, timers or timeline entries, and still needs `discoverClusters` in the panel provider |
| Filament plugin per room (`Plugin` registered via `->plugin()`) | No | Yes | Cannot: plugins are attached inside `AdminPanelProvider::panel()`, which has already run before test setup, and it needs an edit there per room | Only covers the dashboard part | Reject as the shape; may be used inside a room later |

**Recommendation:** a plain Laravel service provider per room under `app/Rooms/<Room>/`, extending one abstract `RoomServiceProvider`, plus one `RoomRegistry`. No package, no new top-level folder, nothing for Ian to approve here beyond what D-18 already approved. This is also the plain-Laravel concept Ian expects to learn (04-CONTEXT specifics). Note: the `Room` descriptor must list classes by name (strings or closures), never `::class` of something that does not exist yet.

## Q2. How a room adds its dashboard section to the existing panel

**Claim to verify: "`AdminPanelProvider` only discovers `app/Filament/*`." Verified.** [VERIFIED: app/Providers/Filament/AdminPanelProvider.php:35-40]
```
->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
...
->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
```
So a widget class in `app/Rooms/...` or `tests/...` is never found by itself.

**When the panel is built.** `PanelProvider::register()` only queues a closure: `Filament::registerPanel(fn (): Panel => $this->panel(Panel::make()))` [VERIFIED: vendor/filament/filament/src/PanelProvider.php]. The facade defers it with `app->resolving(PanelRegistry::class, ...)` [VERIFIED: vendor/filament/filament/src/Facades/Filament.php:190-196]. `FilamentServiceProvider::packageBooted()` resolves `PanelRegistry` ("Register panels if they have not been registered already, by executing pending `resolving()` callbacks.", line 141-142). So `AdminPanelProvider::panel()` runs during **Filament's `boot()`**, which is after every provider's `register()` and before the app providers' `boot()` (package providers come before `bootstrap/providers.php` in the provider list [ASSUMED: standard Laravel ordering, consistent with the experiment below]). `Panel::register()` then calls `Livewire::component(...)` for everything queued up to that moment (`HasComponents::registerLivewireComponents`, end of the method) [VERIFIED].

**What can be registered from a room provider, and when:**

| What | From a room provider | When | Notes |
|---|---|---|---|
| Add widget class names to the registry | `register()` | Before the panel is built | Static data only |
| Panel reads the registry | `AdminPanelProvider::panel()`: `->widgets(app(RoomRegistry::class)->widgetClasses())` | During Filament boot | The only edit to `AdminPanelProvider`; adds nothing per room afterwards |
| Add a widget to an already-built panel (test setup, or any late registration) | `Filament::getPanel('admin')->widgets([...])` and `Livewire::component('<name>', <class>)` | After boot | Run in the scratch test (see below) |
| Render hooks (`FilamentView::registerRenderHook`) | `boot()` | Any time before render | Possible alternative to a widget; not recommended because widgets give `canView()` and match the four existing ones |

Experiment (scratch test outside the repo, SQLite): with a `Widget` subclass added by `Filament::getPanel('admin')->widgets([ScratchWidget::class])` after the app booted, `GET /admin` as a signed-in user contained the widget's markup; with `canView()` returning false it did not. The page also rendered without a separate `Livewire::component()` call on the first request; whether later Livewire update requests need that name registered was **not** tested, so keep the explicit `Livewire::component()` call (`[ASSUMED]` needed). [VERIFIED: scratch test `/private/tmp/claude-501/-Users-iantitus-Projects-ops-platform/6a549a9d-eea0-4d95-9ff7-7ed289d0deec/scratchpad/exp/ScratchRoomExperimentTest.php`, 4 tests passed]

**Important detail found in that experiment:** Filament widgets are lazy by default. The scratch widget did not appear in the first HTML until `protected static bool $isLazy = false;` was set (the four existing widgets set it too). The accessibility check reads the first HTML, so every room section must be non-lazy or it would check a placeholder. Put it in the abstract `RoomSection`.

**The hidden section (D-08).** `Widget::canView()` is a static method returning `true` by default (`vendor/filament/widgets/src/Widget.php:34-37`). `RoomSection::canView()` returns `app(ActiveConfig::class)->hasRoom(static::roomKey())`. Dashboard calls `canView()` on every widget when it renders (`Page.php:497-499` and `585-590`). Because `ActiveConfig` is scoped, this reads the live version once per request.

**Recommendation for real rooms (Phase 5+):** each room lists its widget class in its `Room` descriptor; `AdminPanelProvider::panel()` adds `->widgets(app(RoomRegistry::class)->widgetClasses())`. A room's widget may live anywhere (D-19). For the pretend room, the test-setup helper calls one registry method, `RoomRegistry::addWidgetsTo(Panel $panel)`, which both `AdminPanelProvider` and the test call, so the pretend room proves the same code path. In production `filament:cache-components` caches the panel's widget list at deploy time; because the list comes from code and not the business folder, a deploy refreshes it (`[ASSUMED]` how the cache interacts; confirm when Phase 5 lands the first real room).

**Dashboard section content and the D-21 empty line.** `RoomSection` renders one Blade view shared by every room: if the room's contract `owns` something and none of those files are in the live version, show the one plain line naming them; if the contract owns nothing, never show "empty"; otherwise render the room's own content. It needs two small `ActiveConfig` reads (`hasRoom`, and the room's contract `owns` plus which of those paths are in `files()`).

## Q3. How a room adds file types to the sync

**Read in full this session:** `app/Icm/Loader.php` (159 lines), `Validator.php` (1530 lines, all hook points), `FolderSync.php`, `PlainWords.php`, `ActiveConfig.php`, `IcmFile.php`, `LoadedFolder.php`, `ValidationReport.php`.

Existing code, verbatim (the values the proposal refers to):
```php
// app/Icm/Loader.php:23
public const TYPED = ['business', 'hours', 'lead_sources', 'knowledge', 'service', 'voice', 'sending_rules', 'agent', 'set_replies', 'triage', 'offer'];
// app/Icm/Loader.php:30
private const LATER_SPRINT = ['automation', 'connector', 'walk_test'];
// app/Icm/Loader.php:37
private const ROUTERS = ['CLAUDE.md', 'CONTEXT.md', 'START-HERE.md'];
```
[VERIFIED: app/Icm/Loader.php:23, 30, 37]

`Validator::SINGLETONS` (the seven required files, D-06) [VERIFIED: app/Icm/Validator.php:30-38]: `'business' => 'business/profile.md'`, `'hours' => 'business/hours.md'`, `'lead_sources' => 'business/lead-sources.md'`, `'voice' => 'voice/how-we-talk.md'`, `'sending_rules' => 'rules/sending.md'`, `'set_replies' => 'rules/replies.md'`, `'triage' => 'rules/triage.md'`.

### The registration path (proposal)

A `RoomRegistry` singleton (bound in `AppServiceProvider::register()`) holds a map of `Room` descriptors. A `Room` has two kinds of file-type claims:
- `handles: list<string>`: names of **existing** hard-coded types the room is the owner-room for (e.g. company knowledge handles `business`, `hours`, `knowledge`, `service`, `voice`). Their rules stay in `Validator::rulesFor()` (D-19). This is what D-17 checks a room's `owns` claim against, and what D-10 uses to name the room.
- `fileTypes: list<RoomFileType>`: **new** types the room defines (D-04); each supplies Laravel validation rules and optional extra checks. These are implicitly "handled" by that room.

Exactly where it hooks in:

| Existing code | Edit | Why |
|---|---|---|
| `Loader::read()`, ROUTERS check at the top (`if (in_array($file->getFilename(), self::ROUTERS, true)) { return ... Router ... }`) | Skip the router branch when the relative path matches `#^rooms/[^/]+/CONTEXT\.md$#`. Any other `CONTEXT.md` (root, `business/`, `business/services/`, `rules/`, `rooms/CONTEXT.md`, `rooms/x/sub/CONTEXT.md`) stays a router | D-03. Path is already `/` separated (`relativePath()`) |
| `Loader::TYPED` | Append `'room'` and `'connector'` | Both become checked types |
| `Loader::LATER_SPRINT` | Remove `'connector'`; leave `automation` and `walk_test` | D-24 |
| `Loader::read()` kind selection (`in_array($type, self::TYPED, true) => Typed`) | Also accept `$this->rooms->registeredTypes()`; Loader gets a constructor taking `RoomRegistry` (no `new Loader` anywhere: only `app(Loader::class)` in tests and constructor injection in `FolderSync`) | Room-defined types are Typed |
| `Validator::fileErrors()` -> `rulesFor()` `default => []` (line 268) | `default => $this->rooms->rulesFor($file)` plus new platform arms `'room' => [...]`, `'connector' => [...]` | Registered types validated like any other file; room and connector are platform types so their arms belong in this match |
| `Validator::fileErrors()` post-checks `match ($file->type)` (line 144-152, `default => []`) | `default => $this->rooms->checksFor($file)` | Extra checks a room's type needs |
| `Validator::validate()` (line 121, the `array_push($errors, ...)` of whole-folder checks) | Add `...$this->roomChecks->errors($files)` | One new class holds all D-07/D-10/D-15/D-17/D-22 to D-26 checks so the 1530-line Validator does not grow further |
| `Validator::problemMessage()` (line 1496 `PlainWords::render('In', Loader::TYPED, ...)`) | List `Loader::TYPED` plus registered types | Message must still list every valid type |
| `FolderSync::compile()` `match ($file->type)` (line 220-228; `default => $compiled['index'][$file->type] = $file->path` at line 226) | Add arms before `default`: `'room'` -> `index['room'][<key from path>] = path`; `'connector'` -> `index['connector'][] = path`; a registered type -> `index[$type][] = path` | Without this `default` makes `index['room']` a single path that the last room overwrites |
| `ActiveConfig` | Add `hasRoom()`, `roomContract()`, `roomFilesPresent()`, `connectors()`; each tolerates a version compiled before rooms existed (`?? []`) | The `offers()` method already documents that pattern |

**Recognising the room contract (D-03).** After the Loader change, `rooms/<room>/CONTEXT.md` is parsed like any typed file. A `RoomChecks` rule requires that a `CONTEXT.md` at that path has `type: room` (otherwise "rooms/x/CONTEXT.md must have type: room"), and a `type: room` file anywhere else is refused ("a room contract goes in rooms/<name>/CONTEXT.md"). A room folder whose `CONTEXT.md` has no front matter is refused by the existing `no_front_matter` message, because the file is no longer a router. Room key = the folder name, restricted to the same `[a-z0-9_]+` style as other keys.

**Contract fields (planner's call within D-02; recommended).** Rules in the existing style (`present|array` lets a list be empty):
```
'owns' => 'bail|present|array',        'owns.*' => 'bail|string'     // paths or Str::is patterns; [] for a room that owns no files
'reads' => 'bail|present|array',       'reads.*' => 'bail|string'
'writes_records' => 'bail|present|array', 'writes_records.*' => 'bail|string'   // each must start with records/ and contain no ..; [] = writes nothing (ROOM-04)
'connector' => 'bail|required|string'  // a slot name, or the word none
```
`connector: none` parses to the string `"none"` and `[]` to an empty list. [VERIFIED: php run this session]. Also refuse `..` and a leading `/` in every path-like value.

**The whole-folder checks** (all in `RoomChecks`, all return `{file, field, message}` and use `PlainWords::oneLine()` on every owner-written value):

| Decision | Check | File named in the error | Wording (placeholder) |
|---|---|---|---|
| D-07 | Any loaded file under `rooms/<name>/` where `<name>` is not a registered room | `rooms/<name>/` | "rooms/foo/ is not a room this engine has; the rooms are: a, b" (or "this engine has no rooms yet") |
| D-10 | A typed file whose type is handled by a registered room whose `rooms/<key>/CONTEXT.md` is not in the folder | the file | "business/offers/first-visit-promotion.md belongs to the marketing room, which this business doesn't use; add rooms/marketing/ or remove the file" (the sentence Ian accepted). Dormant in Phase 4: no real room is registered |
| D-15 | Two room contracts whose `owns` match one existing file, or have an identical `owns` entry | the later contract in path order | "business/faq.md is claimed by both rooms/a/CONTEXT.md and rooms/b/CONTEXT.md; pick one, the other can read it" |
| D-17 | A room's `owns` matches an existing typed file whose type the room does not handle | the contract | the sentence Ian accepted: "business/faq.md is a knowledge file, which the company knowledge room handles; marketing can read it"; if no registered room handles the type say "which no room handles yet" (D-15: a room not built yet passes, so for a type no room handles, pass) |
| D-25 | Contract `connector` slot differs from the slot the room's code registers | the contract | "rooms/a/CONTEXT.md says connector: x, but the room's slot is y" |
| D-22 / D-23 | In a connector file, `owns` entries must be `OwnableKind` values; `reads` entries `OwnableKind` or `PlatformKind`; a `PlatformKind` in `owns` gets the platform wording; any other word is refused naming the file and listing the kinds | the connector | "connectors/a.md: owns lists projects, which the platform owns; a connector can read it but never own it" and "owns has 'foo', which is not a kind of record; the kinds are: customers, ..." |
| D-25 | Slot problems, in this order: slot name no room registers; slot registered by a room that is not in this folder; two files fill one slot | the connector (later one for the duplicate) | "connectors/gbp.md fills the google_business_profile slot, but the Google Business Profile room is not in this folder" (Ian's accepted sentence) |
| D-26 | Two connector files `owns` the same kind | the later file in path order (matches how `agentChannelErrors()` assigns blame) | "customers is owned by both connectors/a.md and connectors/b.md; pick one, the other can read it" (exact success-criterion wording) |

D-09 (rooms that cannot be left out) is not built in Phase 4 (no room is required). Give `Room` an optional `requiredBecause` field when the first real room needs it; do not build it now.

**Fingerprint and version (FolderSync).** The fingerprint hashes `path\0raw\0` for every compiled file where `kind` is Typed or Router [VERIFIED: FolderSync `fingerprint()` and the `compiledFiles` filter]. A `rooms/x/CONTEXT.md` moving from Router to Typed does not change the hash (raw bytes are the same), and no current folder has a `rooms/` directory, so nothing already live changes fingerprint. Two consequences: (1) room contracts and connector files now count in `file_count` (Typed only), which is why `exampleFolderFileCount()` is the number that moves when a real room lands (D-16); (2) in Phase 4 the example folder has no rooms/ or connector files, so the number stays 14 [VERIFIED: tests/Pest.php:283 `return 14;`]. The reject path (`FolderSync::reject()`) stores the rejected version and returns `Rejected. {$active->label()} stays live.` regardless of which check failed, so criterion 1 ("a broken example is refused ... while the previous version stays live") and criterion 5 need no FolderSync change.

**One existing test must change, and it is not the file count.** `tests/Feature/Icm/ValidatorTest.php:67` asserts the exact unknown-type sentence listing `business, hours, lead_sources, knowledge, service, voice, sending_rules, agent, set_replies, triage, offer`. Adding `room` and `connector` to the list changes that string. The plan must name this edit (D-16 spirit: named, tiny, lands with the change).

## Q4. Timeline entries

**Verified facts.**
- `activities.type` is a plain string column: `$table->string('type');` [VERIFIED: database/migrations/2026_10_11_131453_create_activities_table.php:17].
- The model casts it to the enum: `'type' => ActivityType::class,` [VERIFIED: app/Models/Activity.php:36].
- The enum has exactly these cases: `TextReceived = 'text_received'`, `FormReceived = 'form_received'`, `ReplySent = 'reply_sent'`, `Note = 'note'`, `OwnerAlerted = 'owner_alerted'`, `DraftHeld = 'draft_held'`, `DraftDiscarded = 'draft_discarded'`, `DraftAnswered = 'draft_answered'`, `IntakeAnswered = 'intake_answered'`, `AgentPaused = 'agent_paused'`, `AgentResumed = 'agent_resumed'`, `OptedOut = 'opted_out'`, `OptedIn = 'opted_in'` [VERIFIED: app/Enums/ActivityType.php].
- Reading a row whose `type` is not one of those throws: Eloquent's enum cast calls `$enumClass::from($value)` [VERIFIED: vendor/laravel/framework/src/Illuminate/Database/Eloquent/Concerns/HasAttributes.php:1324-1329]. So a room cannot simply store its own string in `type` without breaking every reader.
- The one exhaustive consumer is `CustomerCard::section()`, a `match ($activity->type)` with one arm per case and no default [VERIFIED: app/Records/CustomerCard.php:156-170]. The Filament contact infolist shows the type as a badge and the summary [VERIFIED: ContactInfolist.php:54-60]. `Contact::timeline()` is the infolist's relation; `CustomerCard` queries `$contact->activities()` directly [VERIFIED: Contact.php, CustomerCard.php:78].
- `subject_type` is a morph with a morph map enforced: `Relation::enforceMorphMap([...])` in `AppServiceProvider::boot()` (line 74), and `enforceMorphMap($map, $merge = true)` merges [VERIFIED: Relation.php:542-547]. A room model used as an activity subject needs its alias added from the room provider's `boot()` with `Relation::morphMap([...])`.

**Options.**

| Option | Existing enum cast | Existing tests | Cost |
|---|---|---|---|
| Room stores its own string in `type`; cast relaxed to a custom cast returning a union | Changed | Every `$activity->type === ActivityType::X` style assertion and the `match` need review | High, touches 30+ files that mention `ActivityType` |
| Separate value object/table for room entries | Untouched | Untouched | A second timeline to merge and sort; two queries |
| **One new enum case `ActivityType::RoomEntry = 'room_entry'` plus nullable `room` and `entry` string columns on `activities`; the registry says how to draw each entry** | Untouched (additive) | Existing tests untouched; only the exhaustive `match` gains one arm | One small migration; one new arm |

**Recommendation:** the third option. A room declares its entry types in its `Room` descriptor as `TimelineEntryType(key, label, intro)`. Writing one: `Activity::create(['type' => ActivityType::RoomEntry, 'room' => 'pretend', 'entry' => 'note_added', 'summary' => ..., ...])`, only after `ActiveConfig::hasRoom('pretend')` is true (D-08: an idle room writes nothing). Drawing one: `CustomerCard::section()` gets `ActivityType::RoomEntry => $this->roomEntryParts($activity)`, which asks the registry for the label and intro and falls back to a generic "Room entry" plus the summary if the room's code is gone, so an old row can never throw. The Filament infolist badge shows `room_entry` for these rows unless a label is added; the summary line still carries the words (cosmetic, can wait for Phase 11 styling). The migration goes in `database/migrations` (D-18), adds two nullable strings with no `after()` (MySQL-only), and works on SQLite and Postgres.

**Reading rule for D-08 ("no timeline entries appear").** Recommend a single `Activity` query scope used by both `Contact::timeline()` and `CustomerCard`, that keeps rows with `room IS NULL` or a room present in the live version. This is an interpretation of D-08: see Open Question 1.

**Impact on existing tests and rendering:** none for the 13 existing cases; the new case is only reachable when a room writes it. Add a CustomerCard test for a room entry and for an entry of an absent room.

## Q5. Boot order and what is safe where

**Verified from `Illuminate\Foundation\Application` (vendor/laravel/framework/src/Illuminate/Foundation/Application.php).**
- `register($provider)` calls `$provider->register()`, then copies any `$bindings` / `$singletons` properties, then marks it registered. **If the app is already booted it then calls `bootProvider($provider)` immediately** (lines 891-933). `boot()` calls every provider's `boot` in registration order after all `register()` calls, and fires booting/booted callbacks (lines 1128-1163).
- Scratch test result (SQLite): a provider registered with `$this->app->register(...)` after boot logged `['register', 'boot']` in order. [VERIFIED: scratch test, passed]
- `ServiceProvider::loadMigrationsFrom()` uses `callAfterResolving('migrator', ...)` and runs at once if the migrator is already resolved (vendor/laravel/framework/src/Illuminate/Support/ServiceProvider.php:277-284, 310-317).
- `callAfterResolving` is the same helper Laravel's own `withSchedule` uses for the scheduler: `$this->app->afterResolving(Schedule::class, ...)` [VERIFIED: Foundation/Configuration/ApplicationBuilder.php:378-381]; `Schedule` events have `->when($callback)` [VERIFIED: Console/Scheduling/ManagesAttributes.php:227].

**How `ActiveConfig` is scoped (Ian asked for confirmation).** `$this->app->scoped(ActiveConfig::class);` [VERIFIED: app/Providers/AppServiceProvider.php:45]. A scoped binding is a singleton that the container can throw away: `Container::forgetScopedInstances()` unsets those instances [VERIFIED: Container.php:1755-1765], and the queue worker calls it after every job (`$app->forgetScopedInstances();` in `QueueServiceProvider.php:263`). `FolderSync` also calls `app()->forgetInstance(ActiveConfig::class);` after activating a new version [VERIFIED: app/Icm/FolderSync.php:96]. Inside one `ActiveConfig`, `version()` remembers the row it loaded (`$this->version ??= ...`) and throws `NoActiveVersion` when no row is active [VERIFIED: app/Icm/ActiveConfig.php `version()`]. So "one fresh instance per web request or queued job" is true for the worker and for web requests; the console `schedule:run` is a single process per minute.

**Safe in `register()`** (nothing here needs the database, config files from the business folder, or another provider having booted):
- `$this->app->bind/singleton/scoped(...)` (for example the slot interface bound to a closure that reads a driver name, the `TextChannel` pattern),
- `$this->app->make(RoomRegistry::class)->add($this->room())` (static descriptor only; the registry is bound in `AppServiceProvider::register()`),
- `mergeConfigFrom(...)`.
**Do not** in `register()`: resolve services that other providers bind later, touch Eloquent or `DB`, call `ActiveConfig`, call `Filament::getPanel()`.

**Belongs in `boot()`** (everything is registered by then):
- `Relation::morphMap([...])` for room models used as activity subjects,
- `Event::listen(...)` / `Gate` / observers,
- scheduler entries via `$this->callAfterResolving(Schedule::class, fn (Schedule $schedule) => $schedule->job(...)->when(fn () => app(ActiveConfig::class)->hasRoom('x')))`,
- `loadMigrationsFrom()` (only the pretend room; real rooms keep migrations in `database/migrations`, D-18),
- late Filament additions (`Filament::getPanel('admin')->widgets(...)`) only for the late-registered pretend room; real rooms rely on `AdminPanelProvider::panel()` reading the registry, which Filament boots before any app provider's `boot()`.

**What breaks if a provider reads `ActiveConfig` at boot** (all of these are concrete in this repo):
1. **`composer install` and `php artisan` setup commands boot the app before any table exists.** `composer.json` runs `@php artisan package:discover` and `@php artisan filament:upgrade` in `post-autoload-dump` (lines 61-65 of composer.json), and CI runs `composer install`. A boot-time `ActiveConfig` read queries `icm_versions` when there may be no database table yet, so CI's install step would fail with a query error. `php artisan migrate` and `migrate:fresh` in tests have the same problem.
2. **A fresh test database has no active version**, so `version()` throws `NoActiveVersion` for every test that has not run a sync.
3. **Stale across syncs in long-running processes.** A queue worker boots once; anything decided at boot (conditional widget list, listener, schedule entry) never sees the next sync. `FolderSync` swaps the version but cannot reach a decision already baked into the boot. That defeats D-08 ("adding the folder turns the room on at the next sync").
4. **Static or boot-cached room lists leak across tests** in one PHP process.
**Therefore:** providers register unconditionally; every behaviour checks `hasRoom()` at the moment it runs (the widget's `canView()`, the scheduled job's `->when()` and again at the top of the job's `handle()`, the timeline writer). Jobs already on the queue when a room is removed should also re-check on `handle()`.

## Q6. The accessibility check (A11Y-01, D-27)

**What the repo already has** (all read this session): no Pest browser plugin (`composer show --direct` has `pestphp/pest` 5.3.1 and `pest-plugin-laravel` 5.0.1 only; `vendor/pestphp` has `pest`, `pest-plugin`, `pest-plugin-arch`, `pest-plugin-laravel`, `pest-plugin-mutate`, `pest-plugin-profanity`); `package.json` devDependencies are `@tailwindcss/vite`, `concurrently`, `laravel-vite-plugin`, `tailwindcss`, `vite` (no Playwright, no axe-core); CI runs on `ubuntu-latest`, a `matrix.database: [sqlite, pgsql]` with a Postgres 17 service, and the test step is `php artisan test` [VERIFIED: .github/workflows/tests.yml:18, 67]. CI does not set up Node or run `npm`. The Filament admin does not use Vite (no `viteTheme`), and Filament assets are published into `public/` by `filament:upgrade` during `composer install`, which CI already runs, so the admin renders styled without `npm run build`. [VERIFIED: AdminPanelProvider.php; composer.json:61-65; .gitignore lists /public/css/filament]

**What a real browser is needed for.** The axe-core README states "There is limited support for JSDOM" and "Currently the `color-contrast` rule is known not to work with JSDOM" [CITED: github.com/dequelabs/axe-core README]. jsdom has no layout, so contrast and target size cannot be computed. The only axe rule tagged `wcag22aa` is `target-size` (WCAG 2.5.8, minimum 24 px) and the rule list says WCAG 2.2 rules are disabled by default [CITED: github.com/dequelabs/axe-core doc/rule-descriptions.md]. WCAG 2.2 AA ranges over the tags `wcag2a`, `wcag2aa`, `wcag21a`, `wcag21aa`, `wcag22aa`. `color-contrast` is tagged `wcag2aa`, impact serious [CITED: same doc].

**Options.**

| | A. Pest browser plugin (recommended) | B. axe-core + jsdom from a Pest test (Node script) | C. Playwright + `@axe-core/playwright`, separate npm script in CI |
|---|---|---|---|
| Composer packages | `pestphp/pest-plugin-browser` (dev) and its amphp dependencies | none | none |
| npm packages | `playwright` (dev) | `axe-core`, `jsdom` (dev) | `@playwright/test` or `playwright`, `@axe-core/playwright` |
| Browser in CI | Chromium via `npx playwright install --with-deps chromium` | none | Chromium, same install |
| Axe | Bundled (v4.10.3 in the plugin) | npm `axe-core` | npm `@axe-core/playwright` (peer `playwright-core >= 1.0.0`, depends on `axe-core ~4.14.0`) |
| Runs inside the Pest suite and its database | Yes: the plugin serves the Laravel app in-process (an amphp HTTP server handling requests with the test's own app instance), so the test's `RefreshDatabase` data and `actingAs` are the app's data | Yes: Pest renders HTML (Livewire test) and calls Node with `Process` | No: needs `php artisan serve` plus its own database setup, outside the Pest transaction |
| Both CI databases | Yes, with the matrix as it is (adds time to both legs) | Yes | Needs wiring per DB |
| Colour contrast and target size | Yes (real layout) | No (jsdom) | Yes |
| Page-level rules | Yes (the whole `/admin` page) | Only on a fragment | Yes |
| Whole Filament page or widget alone | Whole `/admin` dashboard (a URL is needed); axe is scoped to the room section with `include` so unstyled front desk widgets are not judged | The widget's HTML alone (`Livewire::test(Widget::class)->html()`) wrapped in a minimal document | Whole page |
| Added test time | One browser launch plus page load per test; plugin docs only say `--parallel` helps and give no figures | About a second | Highest |
| Maturity | v5.1.2 on Packagist for Pest 5; MIT; Pest team | mature libraries | mature libraries |

Plugin mechanics (read from the plugin source at v5.1.2 on GitHub): the Laravel driver is `src/Drivers/LaravelHttpServer.php` (amphp `SocketHttpServer` that calls the application's HTTP kernel and forwards `test()->prepareCookiesForRequest()` cookies); `InitScript::get()` injects `resources/js/axe.min.js` (header: `axe v4.10.3`) into every page; `assertNoAccessibilityIssues(int $level = 1)` runs `window.axe.run()` with **no options** and keeps violations whose impact rank is at or below the level (0 critical, 1 serious, 2 moderate, 3 minor); `Webpage::script(string $content): mixed` evaluates JavaScript on the page and returns the result. [VERIFIED: gh api repos/pestphp/pest-plugin-browser at tag v5.1.2] The Pest docs page confirms the install steps, the level wording and a CI recipe (`actions/setup-node`, `npm ci`, `npx playwright install --with-deps`) and does not state how the database is shared or whether `actingAs` works. [CITED: pestphp.com/docs/browser-testing]

**Because the built-in assertion takes no tags and scans the whole page, the room standard should call axe itself through `script()`**, with the five WCAG tags, `target-size` forced on, and scoped to the section:
```php
// Proposed (design). Source of the axe options: axe-core API (`axe.run(context, options)`), tags per doc/rule-descriptions.md
$report = $page->script(<<<'JS'
async () => {
  const results = await window.axe.run(
    { include: [['[data-room-section="pretend"]']] },
    { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'] },
      rules: { 'target-size': { enabled: true } } }
  );
  return {
    checked: results.passes.length + results.incomplete.length + results.violations.length,
    violations: results.violations.map(v => ({ id: v.id, impact: v.impact, help: v.help, targets: v.nodes.map(n => n.target) })),
  };
}
JS);
expect($report['checked'])->toBeGreaterThan(0);          // guards against a silently empty run
expect($report['violations'])->toBeEmpty(json_encode($report['violations']));
```
The `rules: {'target-size': {enabled: true}}` line is belt-and-braces because axe's docs say WCAG 2.2 rules are off by default; whether a `runOnly` tag selection alone enables a disabled-by-default rule was not verified (`[ASSUMED]`). `data-room-section="..."` is an attribute the shared `RoomSection` view must render.

**Recommendation: Option A, the Pest browser plugin.** It is the only option that checks contrast and target size, runs inside the same Pest test and database as the room's boot test, and needs no extra axe package. The test lives in `tests/Feature/Rooms/` so it inherits the shared `beforeEach` in `tests/Pest.php` (the temp folder copy, the fakes); `Http::preventStrayRequests()` applies to Laravel's HTTP client, not to the browser's own page loads. If Ian wants lighter cost and accepts no contrast check, fall back to Option B and say in the test name that contrast is manual.

**What Ian would have to approve (exact list):**
1. Composer dev package `pestphp/pest-plugin-browser` `^5.1` (v5.1.2 today), bringing `amphp/amp`, `amphp/http-server`, `amphp/websocket-client` and their dependencies. Constraint check done by hand against the lock: needs `pestphp/pest ^5.2.1` (installed 5.3.1), `php ^8.4` (8.5.0), `symfony/process ^8.1.7` (installed v8.1.7, exactly at the floor), `pestphp/pest-plugin ^5.0.0` (installed v5.0.0), `ext-sockets` (present locally). A real `composer require` dry run was not done (forbidden); mark `[ASSUMED]` that it resolves.
2. npm dev package `playwright` `^1.63.0` (+ `package-lock.json` change). Node here is v24.12.0, Playwright needs `node >= 20`.
3. CI change in `.github/workflows/tests.yml`: a Node setup step, `npm ci`, `npx playwright install --with-deps chromium` (optionally cache the browser directory), and, if the runner lacks it, `sockets` in the `extensions:` list (`[ASSUMED]` ubuntu runner already has it; the extension list today is `mbstring, intl, dom, fileinfo, zip, pdo_sqlite, pdo_pgsql`). The existing workflow pins every action by commit SHA; new actions should be pinned the same way.
4. `.gitignore` entries `tests/Browser/Screenshots` and `tests/Browser/Traces` (from the plugin docs).
5. A Chromium download locally (`npx playwright install`) and in CI, and the extra CI minutes on both matrix legs. Downloading and running time are `[ASSUMED]`; the docs give no figures.
6. The accessibility check running on both databases (simplest); the check does not depend on the database, so it could be limited to one leg with a driver guard if Ian wants fewer CI minutes.

**Spike first.** The riskiest unknown is whether `visit('/admin')` is signed in after `$this->actingAs($user)` in the shared test app (`[ASSUMED]`; the plugin forwards cookies but the Pest docs do not document `actingAs`) and whether Livewire/Alpine work with `SESSION_DRIVER=array`. Make "prove a signed-in `/admin` loads in the browser test and axe runs" the first task of the accessibility plan; fallback is logging in through the real login form (`App\Filament\Pages\Auth\RememberedLogin`).

## Pretend room test harness (what the planner needs for the rest of the phase)

**Where things live.** Code under `tests/Rooms/PretendRoom/` (namespace `Tests\Rooms\PretendRoom`, autoloaded by the existing `"Tests\\": "tests/"` PSR-4 entry [VERIFIED: composer.json:38-42]); fixture folder under `tests/Fixtures/pretend-room/`; tests under `tests/Feature/Rooms/`. Only `tests/Feature` and `tests/Unit` are test suites in `phpunit.xml`, so the support directories are not scanned for tests. Classes under `tests/Rooms/` must not use the `File` facade (arch rule).

**Registering it.** In test setup (a helper such as `enablePretendRoom()` in `tests/Pest.php`, or the test file's `beforeEach`): `app()->register(PretendRoomServiceProvider::class)`; `app(RoomRegistry::class)->addWidgetsTo(Filament::getPanel('admin'))`; `$this->artisan('migrate')`; then lay the fixture folder. Never in `bootstrap/providers.php`.

**Its table's migration.** `PretendRoomServiceProvider::boot()` calls `$this->loadMigrationsFrom(__DIR__.'/migrations')`. `RefreshDatabase` runs `migrate:fresh` once per process, in `setUp`, **before** the Pest `beforeEach` that registers the provider, and then only opens a transaction for every later test [VERIFIED: vendor/laravel/framework/src/Illuminate/Foundation/Testing/RefreshDatabase.php:15-99]. So the pretend table is not created by `RefreshDatabase`; the test (or the helper) runs `$this->artisan('migrate')`, which runs only the pending pretend migration. Run in the scratch test on SQLite: the table existed after that call and was gone, with its `migrations` row, in the next test. On PostgreSQL the same sequence relies on transactional DDL, which PostgreSQL has; not run locally (no `psql`, Docker present), covered by CI: `[ASSUMED]`. Each test builds a fresh application, so the migrator path registration does not leak.

**Laying its folder on the example.** `tests/Pest.php`'s `beforeEach` copies `examples/example-hvac` to a temp folder (`File::copyDirectory(base_path('examples/example-hvac'), $folder)`), deletes `changelog.md`, resets `records/`, and sets `config(['icm.path' => $folder])`. Helpers available: `icmPath()`, `writeIcmFile()`, `breakFile()`, `activateExampleFolder()`, `rejectedErrors()`, `exampleFolderFileCount()` (14). Add one helper, `layPretendRoomFolder()`, that copies `tests/Fixtures/pretend-room/` over `icmPath()` (the temp copy, never `examples/`), so "empty" = register the room and lay only `rooms/pretend/CONTEXT.md` (the seven required files stay, D-06), and "with its example" = lay the whole fixture. Expected typed-file count is `exampleFolderFileCount()` plus the fixture's room contract, example file and connector files; compute it in the test, do not change `exampleFolderFileCount()` (D-20).

**Ties between SPEC and code (D-14, D-22, D-23).** Write the ownership table, the eight ownable kinds and the three platform kinds in `docs/SPEC.md` with a stable, parse-friendly layout (one backticked key per table row, stable headings). The tests: (1) the ownable-kind rows equal `array_column(OwnableKind::cases(), 'value')` and the platform rows equal `PlatformKind`; (2) each SPEC ownership row whose owner is a registered room matches that room's `owns` in its contract. In Phase 4 no real room exists, so (2) is proven on the pretend room against a SPEC-shaped fixture string; the real SPEC table is parsed and checked only for shape (every owner is a known room name or "the house", and every `owns` pattern in the table appears in the D-12 list). SPEC's connector row also states that Phase 4 checks `owns`, `reads`, `slot` and passes `system`, `direction`, `schedule` through (D-24).

**The D-21 empty line.** One test for the shared view: register the pretend room with a contract that owns the example file, lay only the contract, sync, render the section, assert the single line, no `!` and no em dash, and that it names the files from the contract; a second case with a contract that owns no files must never show the empty line. This same render is the thing the accessibility test scans (the pretend room proves D-21 passes axe).

**Criteria coverage by test.** Criterion 1: contract-only folder passes `icm:sync`; adding the example validates; breaking it is refused naming file and field with the previous version live. 2: the contract fields are required. 3: the boot test, empty and with example, plus the SQLite and Postgres CI legs. 4: a connector with `owns` / `reads` and the slot (fake driver). 5: two fixture connectors, refused with the exact D-26 sentence, previous version live. 6: the accessibility test.

## Common Pitfalls

### Pitfall 1: Reading `ActiveConfig` at provider boot
**What goes wrong:** `composer install` (via `package:discover` and `filament:upgrade`) or `php artisan migrate` fails with a missing-table error; tests without a sync throw `NoActiveVersion`; a worker keeps an old answer after a sync.
**Why it happens:** boot runs at process start, before anything is synced.
**How to avoid:** providers record static facts only; every behaviour calls `hasRoom()` when it runs.
**Warning signs:** a provider `boot()` that mentions `ActiveConfig`, `IcmVersion` or `DB`.

### Pitfall 2: Room-defined types hit the `default` arm in `FolderSync::compile()`
**What goes wrong:** `default => $compiled['index'][$file->type] = $file->path` makes every unknown type a singleton; with two rooms the last wins and `ActiveConfig` cannot find the first.
**How to avoid:** explicit arms for `room`, `connector` and registered types, keyed per file.

### Pitfall 3: Lazy widgets are invisible to the first-HTML accessibility check
**What goes wrong:** the scratch widget was absent from the first HTML until `$isLazy = false` was set.
**How to avoid:** set it in `RoomSection`; assert the check actually evaluated rules (`checked > 0`).

### Pitfall 4: The panel is built before the test registers the pretend room
**What goes wrong:** the pretend widget never appears on `/admin`.
**How to avoid:** the same `RoomRegistry::addWidgetsTo(Panel)` method is called by `AdminPanelProvider::panel()` and by the test helper.

### Pitfall 5: The pretend table is missing
**What goes wrong:** `RefreshDatabase` already ran its migration before the provider was registered.
**How to avoid:** the helper calls `$this->artisan('migrate')` after registering the provider; the transaction rolls it back.

### Pitfall 6: Hidden exact-string tests
**What goes wrong:** `ValidatorTest.php:67` hard-codes the list of valid types. Adding `room` and `connector` changes it.
**How to avoid:** name this edit in the plan; keep it to the expected string.

### Pitfall 7: Contract file counted as a router today, typed tomorrow
**What goes wrong:** after the Loader change, `rooms/x/CONTEXT.md` counts in `file_count` and must carry valid front matter; a stray `rooms/x/CONTEXT.md` copied from a router template is refused.
**How to avoid:** a test for the router-vs-room distinction (all four other `CONTEXT.md` files in the example stay routers; the existing "does not count README, CONTEXT or START-HERE files" test in `SyncTest.php` still passes).

### Pitfall 8: Unescaped owner-written values in new messages
**What goes wrong:** a folder or kind name containing a newline or markdown breaks `changelog.md` entries.
**How to avoid:** `PlainWords::oneLine()` on every interpolated owner-written value, as the existing checks do.

### Pitfall 9: Morph map violations
**What goes wrong:** a room model used as an activity subject throws a `ClassMorphViolationException`-style error because `Relation::enforceMorphMap` is on.
**How to avoid:** the room provider's `boot()` merges its aliases with `Relation::morphMap()`.

### Pitfall 10: Arch rules apply to test classes
**What goes wrong:** a pretend-room class that uses `File` or reads the folder fails `tests/Unit/ArchitectureTest.php`.
**How to avoid:** keep pretend-room classes free of file access (no `File` facade, no `file_get_contents`-style calls) and do folder work in test closures through the existing helpers (`writeIcmFile()`, `icmPath()`, the new `layPretendRoomFolder()`).

## Code Examples

### Registering a late provider and running its migration inside a test (verified by running)
```php
// Source: scratch test run this session; behaviour of Application::register() and ServiceProvider::loadMigrationsFrom()
$this->app->register(PretendRoomServiceProvider::class);   // register() then boot(), because the app is already booted
$this->artisan('migrate')->assertExitCode(0);               // creates the pretend table inside this test's transaction
```

### A room-owned widget (proposed)
```php
abstract class RoomSection extends Widget
{
    protected static bool $isLazy = false;       // otherwise the first HTML is a placeholder (found in the scratch test)

    abstract protected static function roomKey(): string;

    public static function canView(): bool
    {
        return app(ActiveConfig::class)->hasRoom(static::roomKey());
    }
}
```

### Connector slot (the existing TextChannel pattern, copied for the pretend room)
```php
// Source: app/Providers/AppServiceProvider.php (existing)
$this->app->singleton(FakeTextChannel::class);
$this->app->bind(TextChannel::class, function (Application $app): TextChannel {
    $driver = config('channels.text.driver');
    return match ($driver) { 'fake' => $app->make(FakeTextChannel::class), ... };
});
```
The pretend room binds its own interface to its own fake the same way, inside its provider's `register()`.

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Provider `$defer = true` | Eager providers; `DeferrableProvider` only if needed | Laravel 5.8 to now | Room providers must stay eager so `register()` always runs before the panel is built |
| Filament panel config in one provider only | The panel closure runs lazily when `PanelRegistry` resolves, inside Filament's boot | Filament 3+ (verified in 5.10.1 source) | A room's `register()` can still feed `panel()` |
| Axe default rule set | Tag-selected runs; WCAG 2.2 rules (`target-size`) off by default | axe-core 4.5+ | The check must force `target-size` on |

**Deprecated/outdated:** `Page::getVisibleWidgets()` is marked `@deprecated` in favour of `getWidgetsSchemaComponents()`; both call `canView()`.

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|---------------|
| A1 | `$this->actingAs($user)` makes `visit('/admin')` signed in under the Pest browser plugin | Q6 | The accessibility test cannot reach the dashboard; fallback is the login form. Make it the first spike task |
| A2 | A `runOnly` tag selection alone does not enable `target-size`; forcing it with `rules` is needed | Q6 | Without the override the 2.2 rule might not run; the override is harmless if it already ran |
| A3 | `pestphp/pest-plugin-browser` v5.1.2 resolves against the current `composer.lock` | Q6, Standard Stack | Install conflict; Ian/planner finds out at the approval step |
| A4 | The `ubuntu-latest` runner with `shivammathur/setup-php` already has `ext-sockets` | Q6 | CI install fails until `sockets` is added to the `extensions:` list |
| A5 | Chromium download size and CI/test time added are modest (the docs give no figures) | Q6 | CI minutes higher than Ian expects |
| A6 | Registering Livewire component names for runtime-added widgets is needed for update requests (keep the explicit call) | Q2 | Harmless if not needed |
| A7 | `filament:cache-components` includes registry-supplied widgets because they are added in `panel()` | Q2 | Widget missing in production until the cache is rebuilt; check when the first real room lands |
| A8 | Package providers boot before `bootstrap/providers.php` providers (standard Laravel ordering) | Q2 | The registry would be filled after the panel closure runs; the scratch test did not exercise this order directly |
| A9 | PostgreSQL runs `migrate` inside the test transaction as SQLite did (transactional DDL) | Harness | The pretend-table test fails on the pgsql CI leg only; alternative is creating the table with `Schema::create` in the test |
| A10 | D-08 "no timeline entries appear" means entries of an absent room are hidden when read | Q4 | If Ian means only "none are written", the read scope is extra work |
| A11 | `canView()` hides a widget in the dashboard but does not authorise Livewire update requests to it | Security | A signed-in user could call a hidden widget's methods; fine for a single-owner panel, so keep widget data reads behind `hasRoom()` too |
| A12 | `pestphp/pest-plugin-browser` is the genuine Pest package (name from pestphp.com docs, but the seam does not cover Packagist) | Package Legitimacy | Slopsquat risk is low (official docs name it) and Ian approves the install |

## Open Questions

1. **Does D-08 mean old timeline rows of a removed room are hidden?**
   - What we know: D-08 says an absent room's "timeline entries" do not appear.
   - What's unclear: whether that means stop writing, or also hide rows already written while the room was on.
   - Recommendation: hide at read with one scope on `Activity` used by `Contact::timeline()` and `CustomerCard`; ask Ian when reviewing the plan.
2. **Type-level versus file-level owner for `agents/*.md` and `connectors/*.md`.**
   - What we know: D-12 gives owners per file for these two (the room an agent serves; the room whose slot a connector fills); D-10 and D-17 are type-level in the registry.
   - What's unclear: how D-10 names the owner room for an `agent` file in Phase 6.
   - Recommendation: Phase 4 only needs connector ownership by slot (D-25), which is file-level and built here. Leave `agent` ownership for the front desk room's phase; the `Room::handles` list can name `agent` then, with an optional per-file resolver added at that point.
3. **Does the Pest browser plugin work with `SESSION_DRIVER=array` and `actingAs` under Filament/Livewire?** Not run. First spike task.
4. **Where do the wording placeholders for the new refusals live?** They are placeholders (2026-10-17 principle); keep the sentences in `RoomChecks` so they can be reworded in one place.
5. **Required-room check (D-09).** Not built in Phase 4; the descriptor leaves room for a `requiredBecause` field.

## Environment Availability

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| PHP | everything | yes | 8.5.0 | — |
| `ext-sockets` | Pest browser plugin | yes (locally) | — | add to CI extensions |
| Node.js | Playwright | yes | v24.12.0 | — |
| npm | Playwright | yes | 11.6.2 | — |
| Playwright browsers | accessibility test | partially (Chromium builds from other projects exist under `~/Library/Caches/ms-playwright`; this project's required build not checked) | chromium-1223, chromium-1243 present | `npx playwright install` after approval |
| Docker | local Postgres run | yes | — | CI Postgres 17 service |
| `psql` / `pg_isready` | local Postgres checks | no | — | use Docker or the CI leg |
| Pest browser plugin, axe-core, jsdom | accessibility | no (not installed; Ian must approve) | — | Option B in Q6 needs different npm packages |
| Laravel Boost `search-docs` MCP tool | version-aware docs | not available to this agent | — | vendor source and official docs used instead |

**Missing dependencies with no fallback:** none for the sync work. The accessibility tool is blocked on Ian's approval.
**Missing dependencies with fallback:** local Postgres (CI covers it).

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Pest 5.3.1 with `pest-plugin-laravel` 5.0.1 and `pest-plugin-arch` |
| Config file | `phpunit.xml` (suites `tests/Unit`, `tests/Feature`); shared setup in `tests/Pest.php` |
| Quick run command | `php artisan test --compact tests/Feature/Rooms` |
| Full suite command | `php artisan test --compact` (CI runs `php artisan test` on `sqlite` and `pgsql`) |

### Phase Requirements to Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| ROOM-01 | Contract-only room folder passes `icm:sync`; example file validated; broken example refused naming file and field; previous version stays live | feature | `php artisan test --compact tests/Feature/Rooms/RoomContractTest.php` | Wave 0 |
| ROOM-01 | Unknown room folder refused (D-07); double claim refused (D-15); claim of an unregistered type refused (D-17); `CONTEXT.md` router vs room (D-03) | feature | same file / `tests/Feature/Rooms/RoomOwnershipTest.php` | Wave 0 |
| ROOM-02 | Pretend room registers file type, table, widget, timeline entry from test setup only | feature | `tests/Feature/Rooms/PretendRoomBootTest.php` | Wave 0 |
| ROOM-03 | Slot with fake driver; connector `owns` / `reads` checked; slot errors (D-25) | feature | `tests/Feature/Rooms/ConnectorFileTest.php` | Wave 0 |
| ROOM-04 | `writes_records` required, may be empty; bad path refused | feature | `RoomContractTest.php` | Wave 0 |
| ROOM-05 | Boot test empty and with example folder on both databases | feature | `PretendRoomBootTest.php` (CI matrix) | Wave 0 |
| CONN-07 | Two owners of one kind refused with the exact sentence; previous version live; ownable vs platform kinds | feature | `ConnectorFileTest.php` | Wave 0 |
| D-14/D-22/D-23 | SPEC lists equal code lists | feature | `tests/Feature/Rooms/SpecTablesTest.php` | Wave 0 |
| D-21 | One empty line, wording rules, never for a room owning no files | feature | `PretendRoomBootTest.php` | Wave 0 |
| A11Y-01 | axe, five WCAG tags, scoped to the pretend section incl. the empty line | browser (Pest plugin) | `tests/Feature/Rooms/RoomAccessibilityTest.php` | Wave 0 (after approval) |
| Timeline | Room entry drawn on the customer card; absent room's entry hidden; old row never throws | feature | `tests/Feature/Records/CustomerCardTest.php` (extend) or `RoomTimelineTest.php` | Wave 0 |
| Guard | `ActiveConfig::hasRoom()` false with no version; a provider reads no `ActiveConfig` at boot (arch/regression test) | feature | `PretendRoomBootTest.php` | Wave 0 |

### Sampling Rate
- **Per task commit:** the narrowest file for the task, `php artisan test --compact <file>`.
- **Per wave merge:** `php artisan test --compact tests/Feature/Rooms tests/Feature/Icm tests/Unit`.
- **Phase gate:** full suite green before `/gsd-verify-work`, on both databases in CI.

### Wave 0 Gaps
- [ ] `tests/Rooms/PretendRoom/*` (provider, file type, interface + fake driver, widget, model, migration) — covers ROOM-02, ROOM-03
- [ ] `tests/Fixtures/pretend-room/**` (contract, example file, two connector files) — covers ROOM-01, CONN-07
- [ ] `tests/Pest.php` helpers: `enablePretendRoom()`, `layPretendRoomFolder()` — keep `exampleFolderFileCount()` at 14
- [ ] `tests/Feature/Rooms/*` listed above
- [ ] The one named edit to `tests/Feature/Icm/ValidatorTest.php:67`
- [ ] Framework install for the browser check: composer + npm + `npx playwright install` — only after Ian approves

## Security Domain

`security_enforcement` is not set in `.planning/config.json`, so it is treated as enabled.

### Applicable ASVS Categories

| ASVS Category | Applies | Standard Control |
|---------------|---------|-----------------|
| V2 Authentication | no (no new auth) | existing Filament panel login |
| V3 Session Management | no | unchanged |
| V4 Access Control | yes | dashboard sections sit behind the Filament panel; `canView()` hides, it does not authorise (A11); keep data reads inside the widget behind `hasRoom()` |
| V5 Input Validation | yes | the room contract and connector files are owner-written input: Laravel validator rules (`present|array`, `string`), allow-lists (`OwnableKind`, `PlatformKind`, registered types), reject `..` and a leading `/` in path-like values, `PlainWords::oneLine()` on echoed values |
| V6 Cryptography | no | none |
| V14 Configuration | yes | connector files pass `system`, `direction`, `schedule` through unchecked in Phase 4 (D-24); they land in the compiled JSON in the database. CONN-01 adds the no-secrets scan with the first real connector. Until then, SPEC's connector row should repeat "no secrets in the folder" |
| Supply chain | yes | two new packages (composer and npm) only with Ian's approval; pin versions; no `latest` |

### Known Threat Patterns for this stack

| Pattern | STRIDE | Standard Mitigation |
|---------|--------|---------------------|
| Owner-written path in `owns`/`writes_records` escaping the folder (`../`, absolute) | Tampering | refuse such values at sync; the Loader still reads only inside the root (symlinks skipped) |
| Owner-written text with newlines inside an error message corrupting `changelog.md` | Tampering | `PlainWords::oneLine()` |
| A connector claiming to own `texts` to bypass `OutboundSender` | Elevation of privilege | `PlatformKind` refusal (D-23) |
| A room claiming another room's files | Tampering | D-15 and D-17 checks |
| Calling a hidden widget's Livewire endpoint directly | Information disclosure | read data only after `hasRoom()`; single-owner panel limits exposure (A11) |
| Browser test dependencies pulling a malicious npm package | Tampering | version pin, lockfile, Ian's approval, the `checkpoint:human-verify` the legitimacy audit asks for |

## Walk-through log: plain words

Written for Ian. The executor pastes these into the walk-through log at the end of the current sprint file in `docs/sprints/` when the code lands, one line per concept, tied to the real file it appears in. `docs/sprints/sprint-1.1.md` is the newest sprint file today; the executor picks the sprint file that is current when the code is written. The container, scoped bindings and interface binding are already logged in `sprint-0.md` (plan 01-01 and 01-03), so they are not repeated here.

**What a service provider is.** A service provider is a class whose job is to set things up when the app starts. Laravel builds the app, then asks every provider listed in `bootstrap/providers.php` to do its setup. `AppServiceProvider` is the one you already have: it tells the container how to build `ActiveConfig` and `TextChannel`. A room gets its own provider so that one file says everything the room adds: its file types, its slot, its dashboard section and its timeline entries.
- Log line: *Service provider (plan 04-xx): `app/Rooms/<Room>/<Room>ServiceProvider.php` is the one file where a room tells the app what it adds; Laravel runs every provider listed in `bootstrap/providers.php` when the app starts, so a room is added by adding one line there.*

**`register()` versus `boot()`.** Every provider has two steps. `register()` runs first for all providers, and it may only say what exists ("when someone asks for X, build it like this", "this room has these file types"). It must not use other services, because the others may not have been set up yet. `boot()` runs after every provider has registered, so it can use anything: listen for events, add to the schedule, add model nicknames. A room's `register()` hands its description to the `RoomRegistry`; its `boot()` adds the morph map names and the scheduled jobs.
- Log line: *register() versus boot() (plan 04-xx): in a room provider, `register()` only writes the room's description into `RoomRegistry` (no database, no `ActiveConfig`), and `boot()` does the things that need the whole app, like `Relation::morphMap()` and scheduling, because Laravel finishes every `register()` before the first `boot()`.*

**Boot order and why the dashboard hears about rooms.** Boot order is: all `register()` calls, then all `boot()` calls, each in the order the providers are listed. Filament builds the admin panel (runs `AdminPanelProvider::panel()`) during its own boot, which is after every `register()`. That is why a room can tell the registry about its widget in `register()` and the panel will already know about it when it is built. If a room waited until its own `boot()`, the panel would have been built without it.
- Log line: *Boot order (plan 04-xx): `AdminPanelProvider::panel()` runs while Filament boots, after every provider's `register()`, so `->widgets(app(RoomRegistry::class)->widgetClasses())` sees every room that registered itself; a room adding a widget in its own `boot()` would be too late.*

**Why a provider never asks "is this room on?".** Boot happens once, when a process starts. A queue worker can run for days, and the business folder can be synced in between. `ActiveConfig` is rebuilt for every web request and every queued job, so asking it at that moment always gets the live version. Asking at boot would give a stale answer, and on a fresh database (during `composer install` or in a test) there is no live version at all and the code would crash. So providers always register, and each behaviour asks `ActiveConfig::hasRoom()` when it runs.
- Log line: *Deciding at run time, not boot (plan 04-xx): `RoomSection::canView()` calls `ActiveConfig::hasRoom()` each time the dashboard draws, so adding or removing `rooms/<name>/` turns the room on or off at the next sync; a provider that asked at boot would keep its old answer in a long-running worker and would crash on a database with no live version.*

**A registry the rooms add to.** A "registry" here is just a list object the container keeps one copy of (`singleton`), so every class that asks for it gets the same list. Each room adds itself in `register()`; the sync and the dashboard read from it. It is how the sync learns about new file types without the hard-coded `Loader::TYPED` list having to change.
- Log line: *Singleton registry (plan 04-xx): `$this->app->singleton(RoomRegistry::class)` in `AppServiceProvider` means every class that asks for `RoomRegistry` gets the same object, so rooms can add themselves in `register()` and `Loader` and `Validator` read the same list at sync time.*

**Registering a provider from a test.** `$this->app->register(PretendRoomServiceProvider::class)` in a test does the same thing Laravel does at start-up, one provider at a time. Because the app has already started, Laravel runs `register()` and then `boot()` at once. That is how the pretend room exists only in tests: it is never listed in `bootstrap/providers.php`.
- Log line: *Registering a provider in a test (plan 04-xx): `$this->app->register(...)` after the app has started runs the provider's `register()` and then its `boot()` straight away, which is how the pretend room is switched on for a test without ever being listed in `bootstrap/providers.php`.*

**A table that exists only in tests.** `loadMigrationsFrom($path)` in a provider's `boot()` tells Laravel "there are more migrations in this folder". Laravel's test setup makes the database once, before our test code runs, so the test then runs `$this->artisan('migrate')` to build the pretend table. Each test runs inside a database transaction that is rolled back at the end, so the table and its record in the `migrations` table disappear after every test.
- Log line: *Migrations in a test (plan 04-xx): `loadMigrationsFrom()` plus `$this->artisan('migrate')` builds the pretend room's table inside the test's own transaction, and the rollback at the end of the test removes it again, so no other test ever sees it.*

**Hiding a dashboard section.** A Filament widget has a static method `canView()` that returns true by default. The dashboard asks every widget before drawing it. A room's widget answers by asking `ActiveConfig`, so a business without the room folder never sees the section.
- Log line: *Widget `canView()` (plan 04-xx): Filament calls the static `canView()` on each dashboard widget before drawing it; `RoomSection` returns whether the live version has this room, so no folder means no section.*

**A new enum case instead of a new column type.** `ActivityType` is a fixed list, and the model turns the `type` column into one of its cases, so an unknown word in that column would crash the page. Rooms get one extra case, `RoomEntry`, and two new nullable columns that say which room and which kind of entry; the registry knows how to label each one.
- Log line: *Adding an enum case (plan 04-xx): `ActivityType::RoomEntry` is one more case in the fixed list, so `Activity`'s enum cast keeps working for every existing row; the `room` and `entry` columns say which room wrote it and the registry says how to show it.*

## Sources

### Primary (HIGH confidence)
- Repository files read this session: `CLAUDE.md`, `.ai/rules/*.md`, `.planning/phases/04-room-standard/04-CONTEXT.md`, `app/Icm/{Loader,Validator,FolderSync,PlainWords,ActiveConfig,IcmFile,LoadedFolder,ValidationReport}.php`, `app/Providers/{AppServiceProvider,Filament/AdminPanelProvider}.php`, `bootstrap/providers.php`, `app/Channels/{TextChannel,FakeTextChannel}.php`, `app/Enums/{ActivityType,IcmFileKind}.php`, `app/Models/{Activity,Contact}.php`, `app/Records/CustomerCard.php`, `app/Filament/Widgets/*`, `database/migrations/2026_10_11_131453_create_activities_table.php`, `tests/Pest.php`, `tests/Unit/ArchitectureTest.php`, `phpunit.xml`, `.github/workflows/tests.yml`, `composer.json`, `package.json`, `docs/SPEC.md`, `docs/sprints/*`.
- Installed vendor source: `laravel/framework` 13.35.0 (`Foundation/Application.php`, `Support/ServiceProvider.php`, `Container/Container.php`, `Queue/QueueServiceProvider.php`, `Foundation/Testing/RefreshDatabase.php`, `Database/Eloquent/Concerns/HasAttributes.php`, `Database/Eloquent/Relations/Relation.php`, `Console/Scheduling/ManagesAttributes.php`); `filament/filament` 5.10.1 (`PanelProvider.php`, `PanelRegistry.php`, `FilamentServiceProvider.php`, `Facades/Filament.php`, `Panel/Concerns/HasComponents.php`, `Pages/Page.php`, `Pages/Dashboard.php`) and `filament/widgets` `Widget.php`.
- Scratch tests run this session (outside the repo, SQLite): `/private/tmp/claude-501/-Users-iantitus-Projects-ops-platform/6a549a9d-eea0-4d95-9ff7-7ed289d0deec/scratchpad/exp/ScratchRoomExperimentTest.php`.
- `pestphp/pest-plugin-browser` v5.1.2 source on GitHub (`src/Drivers/LaravelHttpServer.php`, `src/Playwright/InitScript.php`, `src/Api/Concerns/MakesConsoleAssertions.php`, `src/Api/Webpage.php`, `src/Enums/AccessibilityIssueLevel.php`, `package.json`, `resources/js/axe.min.js` header).
- axe-core: `github.com/dequelabs/axe-core` README (JSDOM statement) and `doc/rule-descriptions.md` (`target-size`, `color-contrast` rows).

### Secondary (MEDIUM confidence)
- `pestphp.com/docs/browser-testing` (install, accessibility levels, CI recipe).
- `laravelmodules.com/docs/13/getting-started/introduction` (default top-level `Modules/` directory).
- `composer show -a` Packagist metadata for `pestphp/pest-plugin-browser` and `nwidart/laravel-modules`; `npm view` for `playwright`, `axe-core`, `@axe-core/playwright`, `jsdom`.

### Tertiary (LOW confidence)
- None used for recommendations. The `package-legitimacy` seam does not cover Packagist.

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH for the sync, registry and widget parts (installed versions and source read); MEDIUM for the browser plugin (documented, not installed).
- Architecture: HIGH (hook points read; three behaviours run).
- Pitfalls: HIGH for those found in the repo or the scratch run; MEDIUM for Postgres behaviour and the browser plugin's login handling.

**Research date:** 2026-10-22
**Valid until:** 2026-11-05 for the Pest browser plugin and Playwright versions (fast-moving); 2026-11-21 for the Laravel and Filament findings (pinned by `composer.lock`).
