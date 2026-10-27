# Phase 6: Front Desk and Team Rooms - Research

**Researched:** 2026-10-27
**Domain:** Laravel 13 room wraps (room standard from Phases 4 and 5) plus race-safe alert sending and failed-run reporting on SQLite and Postgres
**Confidence:** HIGH (the wraps and both fixes were prototyped in a scratch copy of the repo and run against SQLite and Postgres 17; no file in the real repo was changed)

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

Earlier decisions are cited as "04 D-nn", "05 D-nn", "02 D-nn" and "02.1 D-nn".

**Locked, carried forward (do not reopen)**
- Phase 2 D-01 to D-56 (`02-CONTEXT.md`), DEC-01 to DEC-14 (`PROJECT.md`), 02.1 D-01 to D-51 (`02.1-CONTEXT.md`), 04 D-01 to D-27, and 05 D-01 to D-33.
- The alert-timing and one-alert rules as built (`RaiseOwnerAlert::isUrgent()`, `dueAt()`, `alreadyRaised()`; `SendDueAlerts`; `ReportFrontDeskFailure`).
- The 02.1 IN-11 rule, Ian's condition at the close-out: **never a missing alert for a duplicate** (`02.1-REVIEW-DISPOSITION.md`; `SafetyCheckStatus::claimThenSend()`).
- The WCAG 2.2 Level AA rule (CLAUDE.md). For the front desk and team rooms the automated check starts in Phase 11 (04 D-27).
- The chokepoint rule (CLAUDE.md; PROJECT.md "The chokepoint rule"; 02.1 D-49). D-09 records how it applies to owner alerts.
- Carried facts this phase builds on, with no new decision needed:
  - The folders are `rooms/front_desk/` and `rooms/team/` (05 D-01 naming).
  - Both rooms can never be left out (04 D-09).
  - The front desk owns `rules/sending.md`, `rules/replies.md`, `rules/triage.md` and `agents/front-desk.md`. The team owns no files and reads `owner_alert` in `rules/sending.md`, `owner_name` in `rules/replies.md` and `escalate_to` in `agents/front-desk.md` (04 D-12, D-13).
  - Today's code stays where it is and the room points at it (04 D-19).
  - A room that owns no files is never shown as empty (04 D-21).

**Front desk room**
- **D-01:** The front desk registers a `text` slot pointing at `App\Channels\TextChannel`, with no connector file. `.env` (`channels.text.driver`) keeps picking the driver, as 04 D-25 allows. A connector file comes when Twilio goes live, with CONN-01. A wrap only registers what exists. Affects: connectors and data, customer communication.
- **D-02:** The front desk handles the `agent` type, so every `agents/*.md` is the front desk's (every channel agent). There is only one agent file today. The phase that adds a later room's agent (content generation, for example) gives that agent its own type and names its test changes in that phase. This keeps 04 D-12 ('the room the agent serves'): today's only agent serves the front desk, and a later room's agent gets its own type and lives in that room's folder (04 D-04), so agents/ holds channel agents only. Affects: customer communication, knowledge.

**Both rooms: dashboard, tables, timeline**
- **D-03:** Today's dashboard widgets are split by who acts, as pointers only. The front desk claims Today's conversations (`TodaysConversations`) and Paused conversations (`PausedConversationsWaiting`). The team claims Drafts waiting (`DraftsWaiting`: the approvals and the Needs you card). New leads (`NewLeads`) stays unclaimed until the leads room (Phase 7, ROOM-07). The widgets are still discovered in `app/Filament/Widgets`, and `RoomRegistry::addWidgetsTo()` skips widgets the panel already has, so nothing moves or reorders. Affects: customer communication, team.
- **D-04 (Ian's words):** "Each table has one owner, listed in the Room's tables (code only; nothing reads it at run time). Front desk lists messages and conversations, team lists owner_alerts. No column moves. Team's front-matter reads stays folder paths only (rules/sending.md for owner_alert, rules/replies.md for owner_name, per 04 D-12); 'reads who resolved each draft' is a plain sentence in the body of rooms/team/CONTEXT.md, never a front-matter entry, because reads takes folder paths and a table name there would be refused. Texts stay the platform's for connectors (04 D-23)." Team also reads escalate_to in agents/front-desk.md (04 D-12 table); the quoted list left it out. "Who acted" today is `messages.resolved_by` and `messages.resolution`. Affects: team, customer communication.
- **D-05 (Ian's words):** "Neither wrap registers timeline entry types or model nicknames. Today's activity types (owner_alerted, note, draft_held and the rest) stay platform types drawn by CustomerCard exactly as now, and today's nicknames stay in AppServiceProvider's morph map. Registering them as room entries would change how the customer card prints them (room entries print as customer-supplied unless marked trusted, 04 WR-04), and card tests stay unchanged until Phase 7." So the customer card is unchanged in Phase 6. Affects: customer communication, team.

**Alerts sent exactly once (ALRT-01)**
- **D-06:** A claim with no outcome is retried, under the 02.1 IN-11 rule "never a missing alert for a duplicate". If a worker dies after claiming an alert and before recording sent, failed or skipped, the claim is released once the wait in D-07 has passed, and `SendDueAlerts` sends the alert again. The only duplicate this allows is a crash after the carrier accepted the text and before the row was written. Affects: team, safety and compliance.
- **D-07 (Ian's words):** "2 minutes, measured from the claim, and the claim is taken immediately before TextChannel::send, never when the alert is raised. The wait has to stay well above the text channel's 10-second request timeout (TwilioTextChannel line 59) so a slow send that is still running is never released. Worst case, an urgent alert lost to a crash goes out about 3 minutes late (2-minute wait plus the every-minute SendDueAlerts sweep)." Affects: team, safety and compliance.
- **D-08:** Business-wide alerts (`RaiseOwnerAlert::forBusiness()`: safety check down, back up or missing) get the atomic send claim like every alert, and no de-duplication key. `SafetyCheckStatus`'s own claim keeps deciding how many are raised, so its rule does not move into the alert table. The unique key covers the conversation alerts that today's `alreadyRaised()` check covers, and nothing more. Affects: team, safety and compliance.
- **D-09 (Ian's words):** "Owner alerts are code, not an agent action (BLUEPRINT Team: 'Model role. None; alert routing is code'), so the chokepoint rule does not apply and no checks-passed record is added. Record this so no later plan reopens it; the rule applies the day a model decides or writes an owner alert. As part of ALRT-01, a test proves SendOwnerAlert is the only code that texts an owner alert (TextChannel used only by OutboundSender and SendOwnerAlert), because exactly-once depends on every alert going through its claim." Today `TextChannel::send()` is called only at `app/Actions/OutboundSender.php:274` and `app/Actions/SendOwnerAlert.php:50`. Affects: team, safety and compliance.

**Failed-run report (ALRT-02)**
- **D-10:** A failed run raises one `front_desk_failed` alert and writes one timeline note, as today, tied to the newest text in the burst that run claimed, with today's words. Only which texts count changes. The one-alert rule is unchanged, and adding a count of unanswered texts waits for the wording pass (deferred). Affects: team, customer communication.
- **D-11:** The failure report is made once per claim token, atomically on SQLite and Postgres. The token is the report's de-duplication key. Whichever path gets there first writes the note and raises the alert: the `catch` in `ProcessInbound::handle()`, or `ProcessInbound::failed()` after a timeout or a dead worker. The other path finds the report already made. This closes today's check-then-write race in `ReportFrontDeskFailure`, which can write two notes. Affects: team, customer communication.
- **D-12 (Ian's words):** The token's source is the researcher's call, under four conditions:
  - "(1) the token is stored on every claimed row by the same conditional update that claims it;
  - (2) the failed() copy can find it;
  - (3) it is unique per run, so failed() never matches rows an earlier or later run claimed;
  - (4) it behaves the same on the sync queue the tests use (phpunit.xml QUEUE_CONNECTION=sync) and the database queue production uses, and at least one test serializes the job and calls failed() on a fresh unserialized copy, so a sync-only pass cannot hide a production miss. Proven on SQLite and Postgres."

  Background: `failed()` runs on a fresh copy of the job, so it cannot see a token chosen inside `handle()`. `ProcessInbound` is debounced per conversation (`#[DebounceFor(20, maxWait: 90)]`), so only one dispatched copy runs. Affects: team, customer communication.
- **D-13 (Ian's words):** "Nothing in Phase 6. BLUEPRINT open item under customer communication, decided before the first client goes live: a front-desk run that fails before claiming any text leaves that text unanswered and unreported until the customer texts again (ProcessInbound has $tries = 1 and SweepUndispatched only re-queues texts never dispatched). Today's failed() only reported this case by accident, on an older answered text (the WR-22 wrong-text report), and never for a conversation's first text." Affects: customer communication, team.

**Tests and build order**
- **D-14 (Ian's words; amends 04 D-16 and ROOM-06 for these three tests only):** "Ian approves, like 05 D-23, a second allowed test change for the front desk room beside the example folder's file count, for these three tests only (RoomOwnershipTest lines 77, 160 and 185). It amends 04 D-16 and ROOM-06's 'the one allowed test change' for these three only. The prep plan lands before the front desk wrap and passes on today's code; the wrap then changes only the file count, and every other test both wraps touch stays unchanged. Line 185 gets its own 'previous version stays live' unit case, written and passing before its feature test goes."
  - The three tests are 'refuses a claim of a file no room handles yet' (:77), 'names the room a file belongs to when the business folder does not have that room' (:160) and 'keeps the previous version live when the folder is refused for its owners' (:185) in `tests/Feature/Rooms/RoomOwnershipTest.php`.
  - In the prep plan, a test whose branch a test-only room can still reach with the real rooms registered stays a feature test, re-pointed. A test whose branch can't be reached follows the 05 D-23 pattern: a unit case with a hand-built `RoomRegistry`, written and passing first, then the feature test is removed.
- **D-15:** Build order follows criterion 1: the D-14 prep plan, then the front desk wrap, then the team wrap. Each wrap passes the existing suite unchanged on SQLite and Postgres. ALRT-01 and ALRT-02 come only after both wraps; their order between themselves is the planner's call. No fix goes into a wrap plan. Affects: none (build order only).

### Claude's Discretion
- Each room's never-left-out reason clause (`Room::$neverLeftOutBecause`). The front desk owns required files (sending, replies, triage). The team is needed because the front desk alerts the owner through it, and the life-safety alert goes out at any hour (04 D-09).
- The plain-words body of `rooms/front_desk/CONTEXT.md` and `rooms/team/CONTEXT.md`, and each contract's `reads` list (folder paths only), taken from the 04 D-12 table.
- The schema shape of the send claim (column names, and how `SendDueAlerts` picks up a released claim) and of the unique de-duplication key. One known constraint: on both SQLite and Postgres, a unique index treats NULLs as distinct, so a key built over `message_id` or `user_id` as nullable columns won't de-duplicate. Business-wide alerts need no key (D-08).
- How the unique-index migration treats any duplicate alert rows already stored in a development database. No client data exists (fictional examples only).
- Whether the boot test for a room that claims non-`RoomSection` widgets needs anything beyond Phase 4's existing checks.
- Placeholder wording of any new line, in the `PlainWords` style, with no exclamation points or em dashes (2026-10-17 principle).

### Deferred Ideas (OUT OF SCOPE)
- **D-16 [deferred] (Ian's words):** "Stays deferred under 04 D-13; team keeps reading owner_alert from rules/sending.md. Trigger: decided in the 999.5 Owner Loop discuss, when alert settings grow past owner_alert (staff approvers, email alerts, re-alerts and a backup recipient). BLUEPRINT records the deferral with that trigger."
- **D-17 [deferred]:** IN-11 (with no owner phone on file, a Jev outage adds a failed alert row every 5 minutes) is not in Phase 6. It comes from the 02.1 IN-11 rule, and its fix belongs to onboarding (requiring an owner phone), not to sending exactly once. It stays open in BLUEPRINT sections 6, 8 and 10, decided before the first client goes live.
- Wording pass: the failed-run alert could say how many texts went unanswered, as the outage back-up alert does (02.1 D-50).
- A run that fails before claiming any text (D-13).
- Splitting `owner_alert` out of `rules/sending.md` (D-16).
- A per-room agent type, in the phase that adds a second agent (D-02).
- A text-channel connector file, when Twilio goes live with CONN-01 (D-01).
- The phone design pass todo (Phase 11).
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| ROOM-06 | Front desk room: today's front desk wrapped in the room standard, no behavior change, existing suite unchanged on both databases; allowed test changes are the file count and the three D-14 tests | Wrap recipe, registry gotchas (sections guard, arch test), exact list of tests that break and why (sections "Wrap findings" and "Validation Architecture") |
| ROOM-15 | Team room: owner alerts, approvals and "who acted" wrapped, no behavior change, suite unchanged on both databases | Same recipe; `owns: none`, reads list, tables list, claimed widget pointer |
| ALRT-01 | Each owner alert sent exactly once: atomic send claim plus unique de-duplication key; timing and one-alert rules unchanged | Conditional-update claim, 2-minute stale release, string `dedupe_key` with `createOrFirst`, migration with backfill, verified on SQLite and Postgres |
| ALRT-02 | A failed run reports exactly the burst it claimed, via a claim token stored on the claimed rows | Constructor-generated UUID token, written by the claim's own conditional update, found by `failed()` after serialize/unserialize, report claimed once per token |
</phase_requirements>

## Summary

The wraps are small and mechanical, but two things in the locked decisions do not work as written, and one existing test fails that no decision names. I built both wraps and both fixes in a scratch copy of the repo (`/private/tmp/claude-501/-Users-iantitus-Projects-ops-platform/89b85380-8bfb-4a69-b5dc-e3e01c3fb97b/scratchpad/repo`) and ran the whole suite (2,211 tests) on SQLite, plus the alert, front desk, inbound, rooms, unit and sync suites on Postgres 17. With both wraps registered and `exampleFolderFileCount()` raised to 25, exactly four existing tests fail: the three D-14 tests, and `tests/Unit/ArchitectureTest.php` "only the outbound chokepoint and the alert sender send texts", because the front desk provider names `App\Channels\TextChannel`. Writing the interface as a string literal in the room descriptor (the descriptor already names classes as strings, 04 D-19) fixes that with no test change. Separately, D-03's mechanism fails at boot: `RoomRegistry::guardSections()` throws a `LogicException` for any existing class in `Room::$sections` that is not a `RoomSection`, and the four dashboard widgets are plain Filament table widgets. The widgets need a new pointer-only field on `Room`.

ALRT-01 and ALRT-02 fit the repo's existing idiom (one conditional `UPDATE` whose affected-row count decides who won, as in `ClaimInboundBurst`, `DispatchInbound` and `SafetyCheckStatus::claimThenSend()`). The prototype changed no existing test for ALRT-01, and changed exactly three existing tests for ALRT-02, all in `FrontDeskFailureTest`. The claim token should be a UUID generated in the job's constructor and passed to the claim; the constructor is the only place that survives `serialize()` and reaches the fresh copy `failed()` runs on. The tests in this repo run `ProcessInbound` through `Queue::fake()` and call `handle()` on the same in-memory object, so the serialize-then-`failed()` test D-12(4) asks for is what actually proves the token survives.

`ReportFrontDeskFailure` has four callers, not two: `RunFrontDesk` calls it twice (lines 203 and 214) in addition to the two `ProcessInbound` paths. Its public signature must not change, or all four callers and its existing tests move. Read the token from the `Message` it already receives.

**Primary recommendation:** Land D-14 prep, then the front desk wrap (with a pointer-only `claimedWidgets` field on `Room` and the slot interface as a string), then the team wrap, then ALRT-01 (conditional-update send claim plus a nullable-unique string `dedupe_key`), then ALRT-02 (constructor-generated UUID in `messages.claim_token`, written by `ClaimInboundBurst`'s own update, report claimed once per token by a conditional update).

## Architectural Responsibility Map

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|-------------|----------------|-----------|
| Room registration (descriptor, provider, contract) | API / Backend (service provider, `RoomRegistry`) | Database / Storage (business folder file `rooms/<room>/CONTEXT.md`, read by the sync) | Registration is code at boot; the folder contract is what the sync enforces |
| Dashboard widget claim | API / Backend (descriptor field, pointer only) | Frontend Server (Filament panel keeps discovering the widgets) | Nothing moves; the panel still discovers `app/Filament/Widgets` |
| Alert send claim and exactly-once | Database / Storage (conditional UPDATE, unique index) | API / Backend (`SendOwnerAlert`, `SendDueAlerts`) | Atomicity must be a database fact, not an in-memory check |
| Alert de-duplication | Database / Storage (unique index on `dedupe_key`) | API / Backend (`RaiseOwnerAlert` builds the key) | Two workers race on the insert; only the index can settle it |
| Claim token on a burst | Database / Storage (`messages.claim_token`) | API / Backend (`ProcessInbound` constructor, `ClaimInboundBurst`) | Token must be on the rows and in the serialized job payload |
| Failed-run report once per token | Database / Storage (conditional UPDATE on the newest claimed row) | API / Backend (`ReportFrontDeskFailure`) | Both report paths race; the update count decides |
| Exactly-once proof for texting owners | API / Backend (architecture test) | — | A static rule that only `SendOwnerAlert` sends alerts |

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| laravel/framework | 13.35.0 | Eloquent `createOrFirst`, `Schema` builder, queue serialization, `Cache::lock` | Installed; every API below was read in `vendor/` [VERIFIED: `composer show --direct`] |
| pestphp/pest | 5.3.1 | Tests, including `arch()` rules | Installed [VERIFIED: `composer show --direct`] |
| laravel/ai | 1.2.0 | Front desk agent (unchanged this phase) | Installed [VERIFIED: `composer show --direct`] |
| PHP | 8.5.12 | Runtime | [VERIFIED: `php --version`] |
| SQLite | 3.45.2 (PHP's library) | Local tests | [VERIFIED: `select sqlite_version()` through PDO] |
| PostgreSQL | 17 (CI image `postgres:17`) | CI database | [VERIFIED: `.github/workflows` lists `image: postgres:17`; prototype ran on `postgres:17`] |

### Supporting
None. No new package is needed. (The room wraps use `App\Rooms\*`; the fixes use Eloquent and the schema builder.)

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| String `dedupe_key` column, unique | Composite unique index with `COALESCE(message_id, 0)` expression | Needs raw `DB::statement` on both drivers and `createOrFirst` cannot look the row up by the key; a plain string column is portable and works with `createOrFirst` |
| String `dedupe_key` column | Postgres `NULLS NOT DISTINCT` (PG 15+) | SQLite has no equivalent, so behavior would differ between local tests and CI; rejected by the "identical on both" requirement |
| Constructor-generated UUID token | `$this->job->uuid()` or `debounceOwner` | Neither exists on a hand-built or sync-queue copy; fails D-12(4) |
| Conditional UPDATE on `messages` for the report claim | Alert-first insert with a unique key, note afterward | Reorders note and alert; the conditional update keeps today's note-then-alert order and avoids unique-violation handling |

**Installation:** none.

**Version verification:** versions above come from `composer show --direct` run in this session; no npm or PyPI package is installed.

## Package Legitimacy Audit

No external packages are installed in this phase, so the audit is empty. `gsd_run query package-legitimacy` was not needed.

**Packages removed due to [SLOP] verdict:** none
**Packages flagged as suspicious [SUS]:** none

## Architecture Patterns

### System Architecture Diagram

```
 BOOT                                         SYNC (icm:sync)
 bootstrap/providers.php                      Loader -> Validator -> RoomChecks
   FrontDeskServiceProvider ─register()──►    rooms/front_desk/CONTEXT.md  (type: room, connector: text)
   TeamServiceProvider      ─register()──►    rooms/team/CONTEXT.md        (type: room, owns: none)
        │                                        │ missing folder -> one never-left-out line, version refused
        ▼                                        ▼ ok -> compiled version live (ActiveConfig::hasRoom)
   RoomRegistry (singleton)  ◄── guards: key, slot, handles, sections, claimedWidgets (new)

 ALERT PATH (ALRT-01)
 RunFrontDesk / ProcessInbound / OutboundSender / SafetyCheckStatus
        │
        ▼
 RaiseOwnerAlert::handle ──► per recipient: createOrFirst(dedupe_key) ──► owner_alerts row
        │   (forBusiness: plain create, dedupe_key NULL)                     │ only a row THIS call created is sent now
        ▼                                                                    ▼
 SendOwnerAlert::handle(alert) ◄────────────── SendDueAlerts (every minute: due, unended, claim null or older than 2 min)
        │ 1. conditional UPDATE send_claimed_at (affected rows == 1 or return)
        │ 2. TextChannel::send   (the only owner-alert send)
        ▼ 3. conditional UPDATE sent_at/failed_at/skipped_at (only while all three still null); timeline entry only if it changed a row

 FAILED-RUN PATH (ALRT-02)
 DispatchInbound ──► new ProcessInbound(msgId, convId)   [constructor makes claimToken = uuid; serialized into payload]
        ▼ (debounce keeps the last dispatched copy)
 ProcessInbound::handle ──► ClaimInboundBurst(convId, token): per row UPDATE handled_at + claim_token WHERE handled_at IS NULL
        ├─ exception ──► ReportFrontDeskFailure(conv, $burst->last(), ...)   ┐ both read the token from the Message
        └─ timeout / dead worker ──► failed() on a FRESH copy:                │ and race on ONE conditional UPDATE
             newest SMS row WHERE claim_token = $this->claimToken ───────────┘ (failure_reported_at IS NULL)
        winner: write note, then RaiseOwnerAlert (message-keyed dedupe still applies)
```

### Recommended Project Structure
```
app/Rooms/FrontDesk/FrontDeskServiceProvider.php   # descriptor only, no register()/boot()
app/Rooms/Team/TeamServiceProvider.php             # descriptor only
app/Rooms/Room.php                                 # + claimedWidgets (pointer-only), trailing optional arg
app/Rooms/RoomRegistry.php                         # + guardClaimedWidgets()
examples/example-hvac/rooms/front_desk/CONTEXT.md
examples/example-hvac/rooms/team/CONTEXT.md
database/migrations/2026_10_28_00000N_*.php        # owner_alerts claim + dedupe_key; messages claim_token + failure_reported_at
tests/Feature/Rooms/FrontDeskBootTest.php, TeamBootTest.php
```
Everything else stays where it is (04 D-19).

### Pattern 1: A wrap provider is a descriptor
Model it on `MarketingServiceProvider` (no `register()` or `boot()` override). [VERIFIED: app/Rooms/Marketing/MarketingServiceProvider.php, read this session]. The prototype that passed the whole suite:

```php
// FrontDeskServiceProvider::room()
return new Room(
    key: 'front_desk',
    label: 'front desk',
    handles: ['sending_rules', 'set_replies', 'triage', 'agent'],
    slot: 'text',
    slotInterface: 'App\\Channels\\TextChannel',   // a string, see Pitfall 2
    tables: ['messages', 'conversations'],
    neverLeftOutBecause: 'it owns the sending, replies and triage files the sync requires',
);
// TeamServiceProvider::room()
return new Room(
    key: 'team',
    label: 'team',
    tables: ['owner_alerts'],
    neverLeftOutBecause: 'the front desk alerts the owner through it, and the life-safety alert goes out at any hour',
);
```
`handles` uses the file `type:` words from `Loader::TYPED`. [VERIFIED: app/Icm/Loader.php:24 `public const TYPED = ['business', 'hours', 'lead_sources', 'knowledge', 'service', 'voice', 'sending_rules', 'agent', 'set_replies', 'triage', 'offer', 'room', 'connector'];`]. After the front desk handles `sending_rules`, `set_replies`, `triage` and `agent`, every typed type except `room` and `connector` has a handler, which is why the "no room handles it yet" branches become unreachable.

Add both providers to `bootstrap/providers.php` after `GoogleBusinessProfileServiceProvider`, front desk first (CONTEXT code_context). Registration order does not matter to the registry (OrderIndependenceTest passed unchanged in the prototype).

### Pattern 2: The contracts (example folder)
Front desk, which passed the sync in the prototype:
```yaml
---
type: room
owns: [rules/sending.md, rules/replies.md, rules/triage.md, agents/*.md]
reads: [business/profile.md, business/hours.md, business/services/*.md, business/faq.md, voice/how-we-talk.md, business/lead-sources.md, business/offers/*.md]
writes_records: none
connector: text
---
```
- `connector: text` is required, not `none`: `RoomChecks::connectorErrors()` demands the contract's `connector` equal the room's slot name. [VERIFIED: app/Icm/RoomChecks.php, `$connector === ($room->slot ?? 'none')` in `connectorErrors()`]
- Do not list the text channel connector file under `owns` (04 D-25); there is none anyway (D-01).
- The `agents/*.md` entry is a `*` pattern that does not start with `*`, so it needs no YAML quoting (05-02 note: patterns starting with `*` must be quoted).

Team:
```yaml
---
type: room
owns: none
reads: [rules/sending.md, rules/replies.md, agents/front-desk.md]
writes_records: none
connector: none
---
```
`owns: none` is the bare word, which the Loader turns into an empty list. [VERIFIED: app/Icm/Loader.php, `NONE_MEANS_EMPTY` maps `'room' => ['owns', 'reads', 'writes_records']`]. The body of `rooms/team/CONTEXT.md` carries the plain sentence that the team reads who resolved each draft (`messages.resolved_by`, `messages.resolution`), never a front-matter entry (D-04).

The example folder gains two files, so `exampleFolderFileCount()` goes from 23 to 25. [VERIFIED: tests/Pest.php:293-296 returns 23 today; the prototype's whole-suite run with 25 had no count failures]

### Pattern 3: Claiming the four existing widgets (D-03) needs a new descriptor field
**What:** Add an optional trailing constructor argument to `Room` (suggested name `claimedWidgets`, `list<class-string>`), documented as pointer only: it never reaches the Filament panel. Add `RoomRegistry::guardClaimedWidgets()`: each class that exists must be a `Filament\Widgets\Widget`, and no widget may be claimed by two rooms (the same style as the other guards). The front desk claims `TodaysConversations` and `PausedConversationsWaiting`; the team claims `DraftsWaiting`; `NewLeads` stays unclaimed.
**Why not `sections`:** `RoomRegistry::guardSections()` throws for a section class that is not a `RoomSection`:
```php
// app/Rooms/RoomRegistry.php:309-311
if (! is_subclass_of($section, RoomSection::class)) {
    throw new LogicException("Room [{$room->key}] lists section [{$section}], which is not a RoomSection. Only a RoomSection hides itself while its room is off.");
}
```
[VERIFIED: read this session]. In the prototype, listing `TodaysConversations::class` in `sections` stopped the whole app at boot with exactly that message. `tests/Unit/Rooms/RoomRegistryTest.php:163-168` pins this refusal using `AccountWidget` (a plain widget), so loosening the guard would change an existing test and is not allowed. A trailing optional constructor argument changes no existing test (every test builds `new Room(key:, label:, ...)` with named arguments). New tests for the new guard are additive.
**Boot test addition (the Claude's Discretion item):** beyond Phase 4's checks, assert the claim is a pointer: the panel's widget list is identical with and without the claim, `/admin` still shows Drafts waiting, Today's conversations, Paused conversations and New leads in the same order, and the claimed classes are not in `RoomRegistry::widgetClasses()` (so `addWidgetsTo()` never touches them). The axe check is not run (D-27 starts the check for these rooms in Phase 11, and no new `RoomSection` is added).
**If Ian prefers zero code:** the fallback is to record the claim in each room's `CONTEXT.md` body as prose. It satisfies "pointers only" but nothing checks it. This is the one place the research asks the planner to confirm the mechanism with Ian before the front desk plan (see Open Questions 1).

### Pattern 4: Atomic claim by conditional UPDATE (ALRT-01, ALRT-02)
The repo already does this three times: `ClaimInboundBurst` (`->whereKey($id)->whereNull('handled_at')->update([...]) === 1`), `DispatchInbound`, and `SafetyCheckStatus::claimThenSend()`. Use the same shape. One `UPDATE ... WHERE <still unclaimed>` is atomic on SQLite and Postgres, and PDO reports the affected-row count on both [VERIFIED: prototype tests asserting one text and one sole row passed on SQLite and Postgres 17].

### Anti-Patterns to Avoid
- **Reading `$alert->sent_at` on the model to decide whether to send.** That is the WR-03 bug: the model in memory can be stale. The prototype's negative control confirmed it: with today's `SendOwnerAlert`, two stale copies of one alert text twice, and a second worker entering mid-send texts twice.
- **Choosing the token inside `handle()`.** `failed()` runs on a fresh copy and cannot see it (D-12 background).
- **Changing `ReportFrontDeskFailure::handle()`'s signature.** Four callers and several tests use it (see Pitfall 5).
- **A unique index over nullable `message_id` / `user_id`.** NULLs are distinct on both drivers, so it would not de-duplicate (see Pitfall 6).
- **Unconditional `$alert->update(['sent_at' => ...])` after the send.** It cannot tell that someone else already ended the alert.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Insert-or-find on a unique key | A try/catch around `create()` plus a second query | `Model::query()->createOrFirst(['dedupe_key' => $key], [...values])` | It wraps the insert in a savepoint when a transaction is open, so a Postgres unique violation does not abort the test transaction (`RefreshDatabase`); then it re-reads the row [VERIFIED: vendor/laravel/framework/src/Illuminate/Database/Eloquent/Builder.php:763-770 and `withSavepointIfNeeded` at :2038-2043] |
| Cross-worker "who goes first" | Cache locks or in-memory flags | One conditional `UPDATE` and its affected-row count | The cache driver in production is the database; the row itself is the truth, and the repo already uses this idiom |
| A per-run identifier | A time window or a queue job id | A UUID made in the job constructor (`Str::uuid()`) | A public property serializes with the job, so the unserialized copy `failed()` runs on has it |
| Proving only `SendOwnerAlert` texts owners | A custom scanner | The existing `arch()` rule in `tests/Unit/ArchitectureTest.php:58-66` plus one additive arch rule on `SendOwnerAlert` | The rule already exists for `TextChannel`; see Validation Architecture |
| Room registration plumbing | A new loader | `RoomServiceProvider` and `RoomRegistry` | Phase 4 built it; the wrap is a descriptor |

## Runtime State Inventory

This is not a rename phase, but it adds columns and a room folder to a running system, so the stored-state questions still apply.

| Category | Items Found | Action Required |
|----------|-------------|------------------|
| Stored data | `owner_alerts` rows already exist in any development database with no key and no claim column; `messages` rows have `handled_at` but no token | Migration backfills `dedupe_key` for conversation alerts, keeping only the first (lowest id) row of each duplicate group so the unique index can be created; business alerts stay NULL (D-08). Old `messages` rows keep `claim_token` NULL and so never match any job's token, which is the correct "earlier run" behavior. Data migration, not a code edit |
| Live service config | None. No external service holds an alert key or a token | None, verified by reading the code paths: only `owner_alerts` and `messages` are touched |
| OS-registered state | The scheduler entry `Schedule::job(new SendDueAlerts)->everyMinute()->withoutOverlapping()` [VERIFIED: routes/console.php:18] | None. The sweep keeps its schedule; only its query gains the stale-claim clause |
| Secrets and env vars | None added. `channels.text.driver` stays in `.env` (D-01) | None |
| Build artifacts | None. No package is installed | None |

## Common Pitfalls

### Pitfall 1: Raising the example file count is not enough; the D-14 prep must land first
**What goes wrong:** With both rooms registered, three `RoomOwnershipTest` cases fail (`:77` now reports "which the front desk room handles", `:160` syncs successfully when it expected a refusal, `:185` finds the folder activated instead of refused). [VERIFIED: scratch whole-suite run]
**Why:** Every typed file type now has a real handler, so the three branches (no room handles a type; an absent room that only handles a type; previous version live after that refusal) cannot be reached with the real rooms registered.
**How to avoid:** The D-14 prep plan. Two of the three already have unit coverage with a hand-built registry: `tests/Unit/Icm/RoomChecksTest.php` has "says no room handles a type yet when a present room claims a file of that type" (covers :77) and "names the absent room when the only room that handles a type is not in the files" (covers :160) [VERIFIED: read this session, lines 34-63]. Line 185 needs its own new case. I proved one works: a test that hand-builds `RoomRegistry` with one room (`rulebook`, handles `triage`), builds `new FolderSync(new Loader($reg), new Validator($rc = new RoomChecks($reg), $reg, new ConnectorChecks($reg, $rc)), app(Changelog::class), $reg)`, syncs a copy of the example folder with `rooms/` and `connectors/` removed and a `rooms/rulebook/CONTEXT.md` owning `rules/triage.md`, deletes that room folder, syncs again, and asserts `SyncOutcome::Rejected` and `ActiveConfig::versionId()` equal to the first version's id. It passed. It must live under `tests/Feature/` (not `tests/Unit/`), because only the Feature directory gets the app, `RefreshDatabase` and the temporary folder from `tests/Pest.php` (`->in('Feature')`); a file in `tests/Unit/` failed with "Target class [config] does not exist".
**Order inside the prep plan:** add the line-185 case and see it pass on today's code, confirm the two existing unit cases, then remove the three feature tests. Other feature tests already assert the previous version stays live under an ownership refusal (the test "keeps a room's own kind of file in its folder..." compares `versionId()` to the live id), so that behavior stays covered.
**Also update:** the BLUEPRINT carry note (docs/BLUEPRINT.md line 438) and the RoomChecksTest header comment that says the branches "stop existing once the front desk room handles triage in Phase 6".

### Pitfall 2: `ArchitectureTest` fails when the front desk provider names `TextChannel::class`
**What goes wrong:** `tests/Unit/ArchitectureTest.php` "only the outbound chokepoint and the alert sender send texts" fails with "Expecting 'App\Channels\TextChannel' not to be used on 'App\Rooms\FrontDesk\FrontDeskServiceProvider'". It is a fourth existing test that breaks, and D-14 does not cover it. [VERIFIED: scratch run; rule text at tests/Unit/ArchitectureTest.php:58-66, allowing only `App\Actions\OutboundSender`, `App\Actions\SendOwnerAlert`, `App\Channels`, `App\Providers`, `Tests`]
**How to avoid:** Pass the interface as a string, `slotInterface: 'App\\Channels\\TextChannel'`. `Room` already documents that "A Room names its classes as strings" (app/Rooms/Room.php docblock) and `RoomRegistry::guardSlot()` checks `interface_exists()` at registration, so a typo still fails at boot. With the string, the architecture test passes unchanged [VERIFIED: re-ran ArchitectureTest, 14 passed]. The alternative, adding `App\Rooms\FrontDesk` to the allowed list, is a fifth approved test change that D-14 did not grant; do not take it without Ian.

### Pitfall 3: Unit-test locations: Feature helpers do not exist in `tests/Unit`
`tests/Pest.php` binds `TestCase`, `RefreshDatabase`, the temporary folder and the fakes to `tests/Feature` only. A new "unit case" that touches the database or a folder must sit under `tests/Feature/` (hand-built objects, own file), or it errors with "Target class [config] does not exist". Pure `RoomChecks`/`RoomRegistry` cases can stay in `tests/Unit`.

### Pitfall 4: A fresh `ProcessInbound` per test hides the token problem
**What goes wrong:** Tests hold `ProcessInbound` back with `Queue::fake([ProcessInbound::class])` and run it by `app()->call([$job, 'handle'])` on the very object that was pushed. [VERIFIED: tests/Pest.php:171-185 `runQueuedFrontDeskJobs()`]. That object is never serialized, so a token chosen inside `handle()` would pass every sync test and fail in production.
**How to avoid:** Generate the token in the constructor, as a public typed property. In the prototype, `unserialize(serialize($job))->failed(...)` found the right rows and left a text that arrived after the claim alone (test passed on SQLite and Postgres). `CallQueuedHandler::failed()` builds its command by unserializing the stored payload [VERIFIED: vendor/laravel/framework/src/Illuminate/Queue/CallQueuedHandler.php:414-436, `$command = $this->getCommand($data)`]. The debounce decides which dispatched copy runs by an owner token in the cache; superseded copies are deleted without running (`deleteDebouncedJob`) [VERIFIED: vendor/laravel/framework/src/Illuminate/Queue/CallQueuedHandler.php:79-80 and :260-296], so exactly one token ever claims a burst. Keep the constructor's third argument optional (`?string $claimToken = null`) so existing `new ProcessInbound($id, $conversationId)` calls still work, and let tests pass a known token.

### Pitfall 5: `ReportFrontDeskFailure` has four callers
**What goes wrong:** Changing its signature broke 13 tests in my first prototype. `RunFrontDesk` calls it at `app/Actions/RunFrontDesk.php:203` ("no agent file answers texts") and `:214` ("every model failed"), and `ProcessInbound` calls it from `handle()`'s catch and from `failed()`. [VERIFIED: grep of app/]
**How to avoid:** Keep `handle(Conversation $conversation, Message $text, string $noteDetail, string $why)` as is. The claimed `Message` already carries `claim_token` (the claim reloads the rows after updating them). Inside, when `$text->claim_token !== null`, claim the report with one conditional update; when it is null (a row created by a factory in a test, or a legacy row), fall back to today's behavior. Then keep today's "an alert for this text already exists" check, because `RunFrontDesk` raises `front_desk_failed` alerts for the same message through `RaiseOwnerAlert` (`RunFrontDesk.php:1203` and `:1210`, and `OutboundSender.php:337`), and "one alert per text" must keep holding.

### Pitfall 6: A unique index over nullable columns does not de-duplicate
Confirmed on both drivers: two rows with `(a=1, b=NULL)` both insert under a unique index on `(a, b)`; in Postgres 17 a unique index on a nullable `k` also accepts many NULLs [VERIFIED: psql against `postgres:17`: `insert ... (null,1,null),(null,1,null)` returned `INSERT 0 2`]. SQLite behaves the same, and the prototype's "allows many null keys" test passed on both. So: one nullable string column holding a key built in code, with null `message_id` and `user_id` written as `0` (real ids start at 1), and business alerts leaving the column NULL.

### Pitfall 7: A duplicate insert inside a Postgres transaction poisons the transaction
In Postgres a unique violation aborts the current transaction, and `RefreshDatabase` wraps every test in one [VERIFIED: psql: after the duplicate-key error the rest of the batch did not run]. `createOrFirst` avoids it through its savepoint. Use it; do not write a bare `create()` in a try/catch. The prototype proved a duplicate `createOrFirst` returns the existing row with `wasRecentlyCreated === false`, and the table stays usable, on SQLite and Postgres.

### Pitfall 8: `skip()` racing a send
`SendDueAlerts` calls `skip()` for a draft alert whose draft was handled. If a send is in flight, `skip()` must not end the alert as skipped while the text goes out. Give `skip()` the same "claim is not fresh" condition in its conditional update, so a claimed alert is left to finish as sent.

### Pitfall 9: Time comparisons in the stale-claim clause
The claim column is a `timestamp` written with `now()`, and the stale test compares it to `now()->subMinutes(2)`. Both go through the same Carbon formatting, so the comparison is consistent on SQLite (text) and Postgres. The prototype test froze time, set `send_claimed_at` to 119 seconds ago (not released), travelled 2 seconds (released) and passed on both. Use `<=` so a claim exactly two minutes old is released.

## Code Examples

### Send claim and conditional end (ALRT-01) — the shape that passed on both databases
```php
// SendOwnerAlert
public const int CLAIM_WAIT_MINUTES = 2;   // D-07; the Twilio request timeout is ->timeout(10) at TwilioTextChannel.php:59

private function claimToSend(OwnerAlert $alert): bool
{
    return OwnerAlert::query()
        ->whereKey($alert->id)
        ->whereNull('sent_at')->whereNull('skipped_at')->whereNull('failed_at')
        ->where(fn ($query) => $query->whereNull('send_claimed_at')
            ->orWhere('send_claimed_at', '<=', now()->subMinutes(self::CLAIM_WAIT_MINUTES)))
        ->update(['send_claimed_at' => now()]) === 1;
}

/** @param array<string, mixed> $columns */
private function end(OwnerAlert $alert, array $columns): bool
{
    $ended = OwnerAlert::query()->whereKey($alert->id)
        ->whereNull('sent_at')->whereNull('skipped_at')->whereNull('failed_at')
        ->update($columns) === 1;

    if ($ended) {
        $alert->forceFill($columns)->syncOriginal();
    }

    return $ended;
}
```
Use: take the claim after the blank-phone check and immediately before `$this->textChannel->send(...)` (D-07: never when raised); route `sent_at`, `failed_at` and `skipped_at` through `end()` and write the `owner_alerted` timeline entry only when `end()` returned true. Keep the existing `try/catch (Throwable)` that makes `handle()` never throw: wrap `claimToSend()` and `end()` so a database error is `report()`ed and the method returns.
`SendDueAlerts` gains one clause so a fresh claim is not even loaded: `->where(fn ($q) => $q->whereNull('send_claimed_at')->orWhere('send_claimed_at', '<=', now()->subMinutes(SendOwnerAlert::CLAIM_WAIT_MINUTES)))`.

### Unique de-duplication key (ALRT-01)
```php
// RaiseOwnerAlert::handle, per pending recipient
$alert = OwnerAlert::query()->createOrFirst(
    ['dedupe_key' => "c{$conversation->id}:m".($subject?->id ?? 0).":{$reason->value}:u".($userId ?? 0)],
    [ /* conversation_id, message_id, user_id, reason, urgent, due_at, to_phone, body */ ],
);

if ($alert->wasRecentlyCreated && $dueAt->lessThanOrEqualTo(now())) {
    $this->sendOwnerAlert->handle($alert);
}
// return only alerts this call created, so "@return the alerts stored by this call" stays true
```
Keep `alreadyRaised()` as the cheap pre-check (it avoids building the body text for a duplicate); the unique index is the guarantee. `forBusiness()` stays a plain `create()` with no key (D-08).

Migration for `owner_alerts` (SQLite and Postgres): add `send_claimed_at` (nullable timestamp) and `dedupe_key` (nullable string), backfill the key for rows with a conversation, leaving later duplicates of a key NULL, then add `->unique('dedupe_key')`. The prototype migration ran under `RefreshDatabase` on SQLite and Postgres. `Schema::table` with `unique()` needs no table rebuild on SQLite because the index is created on an existing column, unlike the earlier `->change()` migration (2026_10_15_000001).

### Claim token (ALRT-02)
```php
// ProcessInbound
public string $claimToken;

public function __construct(public int $messageId, public int $conversationId, ?string $claimToken = null)
{
    $this->claimToken = $claimToken ?? (string) Str::uuid();
}
// handle(): $claimInboundBurst->handle($this->conversationId, $this->claimToken)

// failed(): rows of THIS run only; a text that arrived afterwards has no token
$text = Message::query()
    ->where('conversation_id', $conversation->id)
    ->where('direction', MessageDirection::In)->where('author', MessageAuthor::Customer)
    ->where('channel', MessageChannel::Sms)
    ->where('claim_token', $this->claimToken)
    ->latest('id')->first();

// ClaimInboundBurst::handle(int $conversationId, ?string $claimToken = null)  (default: a fresh uuid)
->update(['handled_at' => now(), 'claim_token' => $claimToken]) === 1   // same conditional update that claims (D-12 condition 1)
```
`messages` gets `claim_token` (string 36, nullable), `failure_reported_at` (nullable timestamp) and an index on `(conversation_id, claim_token)`.

### Report once per token (D-11)
```php
// ReportFrontDeskFailure::handle(), signature unchanged
if ($text->claim_token !== null) {
    $first = Message::query()->whereKey($text->id)->where('claim_token', $text->claim_token)
        ->whereNull('failure_reported_at')->update(['failure_reported_at' => now()]) === 1;

    if (! $first) {
        return;            // the other path already reported this run
    }
}
// then today's "an alert for this text already exists" check, then the note, then RaiseOwnerAlert
```
Both paths read the same newest row of the burst only if they pick the same subject. The `catch` passes `$burst->last()` (any channel); `failed()` picks the newest SMS row by token. They differ only for a burst whose newest row is a web form, and the claim is on the row it was given, so the two paths could each claim a different row and report twice. Make them agree: in both paths use the newest row with `channel = sms` among the rows of this token (in `handle()` the burst is known to hold an SMS, because the run stops earlier otherwise). That keeps the existing test "reports the customer text, not a web form that was claimed with it" true and makes the claim row the same for every path. If a claim step then throws, release it (`failure_reported_at = null`) and rethrow, like `claimThenSend()` does, so a failed report is retried by the other path (IN-11).

### Boot-test comparison for the front desk contract
`SpecTables::patternsOwnedBy($spec, 'front desk')` returns only the three `rules/*.md` rows, because SPEC's `agents/*.md` row has the owner phrase "the room the agent serves". The boot test must therefore expect `[...patternsOwnedBy('front desk'), 'agents/*.md']`. Do not change that SPEC cell: `tests/Feature/Rooms/SpecTablesTest.php` pins `'agents/*.md' => ['agents/*.md', 'the room the agent serves']`. Change the prose below the table instead ("A room that can never be left out (company knowledge and marketing from Phase 5)" gains front desk and team).

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Check `sent_at` on a model, then send | Conditional UPDATE claim, then send | This phase | Closes WR-03 |
| `alreadyRaised()` check then insert | Unique key plus `createOrFirst` | This phase | Closes WR-04 |
| `failed()` guesses the newest handled text at or before `messageId` | `failed()` reads rows by claim token | This phase | Closes WR-22; also stops a STOP/START keyword text (marked handled on arrival at `RecordInbound.php:140`) from ever being blamed |

**Deprecated/outdated:** none.

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|---------------|
| A1 | The field name `claimedWidgets` and a `guardClaimedWidgets()` guard are acceptable to Ian as the mechanism for D-03 (the locked decision says "pointers" but names no mechanism) | Pattern 3 | If Ian wants prose-only claims, the plan drops one `Room` field and one guard; low cost either way |
| A2 | Naming `TextChannel` as a string in the room descriptor is acceptable under the intent of the arch rule (the room never calls `send`) | Pitfall 2 | If Ian wants the arch list widened instead, that is a fifth approved test change |
| A3 | Two real worker processes racing on Postgres behave like the single-process simulations (stale in-memory copies, re-entrant send, duplicate key through `createOrFirst`). I did not run two OS processes | ALRT-01/02 | The database guarantees (single-statement UPDATE, unique index) are standard, but no test in the plan drives two real connections; if Ian wants that proof, add a PG-only test with two connections |
| A4 | `Str::uuid()` collisions are negligible as a per-run token | Claim token | Negligible; a collision would also need the same conversation |
| A5 | The empty row of the front desk boot test (agent file removed, 24 files) is the wanted "empty" shape; I confirmed the sync accepts a folder with no agent file and with the room folder present, but not that Ian wants that as "empty" | Validation Architecture | A different "empty" definition only changes one dataset row |
| A6 | A burst whose newest row is a web form is rare; unifying both report paths on "newest SMS of the token" is an acceptable tiny change from today's `catch`, which reports `$burst->last()` | Report once per token | If Ian wants byte-identical `catch` behavior, keep `$burst->last()` there and accept a second report only in that rare mixed case |

## Open Questions

1. **How should D-03's widget claim be recorded in code?**
   - What we know: `sections` cannot hold a plain Filament widget (guard throws); `RoomSectionTest` and `RoomRegistryTest` pin that refusal.
   - What's unclear: whether Ian wants a code pointer (new `Room` field plus guard) or prose only.
   - Recommendation: the new field with a guard and a boot-test proof that the panel is unchanged (A1). Settle it when the front desk plan is reviewed; it is the only mechanism question the research could not close by itself.
2. **Is the string-named `TextChannel` slot interface fine, or does Ian want the arch list widened?** (A2) Recommendation: the string; no test change.
3. **Should the report subject be unified on "newest SMS of the token" in both paths?** (A6) Recommendation: yes.
4. **Does Ian want a two-connection Postgres race test?** (A3) The simulations cover the claim logic; a real two-process test needs a PG-only skip on SQLite. Recommendation: not required for the success criteria.

## Environment Availability

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| PHP | Everything | yes | 8.5.12 | — |
| composer / vendor | Tests | yes | Laravel 13.35.0, Pest 5.3.1 | — |
| SQLite (via PDO) | Local tests | yes | 3.45.2 | — |
| Docker | Local Postgres run | yes | 29.8.2 | Rely on the CI `pgsql` job |
| Postgres 17 | Proving Postgres behavior | yes, through `docker run postgres:17` | 17 | CI job `pest (pgsql)` |
| Playwright / Chromium | Browser tests that already exist | yes (existing suite ran) | — | — |

The local machine has no `psql` or `pg_isready`; I used `docker exec` into a throwaway `postgres:17` container, then stopped it. A suite run on Postgres is slow (the alerts, front desk and inbound folders took about 10 minutes there versus about 95 seconds on SQLite), so plan Postgres runs for plan boundaries, not per task.

**Missing dependencies with no fallback:** none.

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Pest 5.3.1 on PHPUnit, Laravel `RefreshDatabase` [VERIFIED: composer show] |
| Config file | `phpunit.xml` (QUEUE_CONNECTION=sync, DB sqlite `:memory:`); `tests/Pest.php` binds helpers to `tests/Feature` only |
| Quick run command | `php artisan test --compact tests/Feature/Rooms tests/Unit` (about 22 seconds on SQLite) |
| Full suite command | `php artisan test --compact` (about 2.5 minutes on SQLite) |
| Postgres | Set `DB_CONNECTION=pgsql DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD`; CI sets these from its matrix |

### Phase Requirements to Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| ROOM-06 | D-14 prep: line-185 hand-built case; three feature tests removed | feature (hand-built registry) | `php artisan test --compact tests/Feature/Rooms/RoomOwnershipTest.php tests/Unit/Icm/RoomChecksTest.php` | prep adds one file; the two RoomChecks unit cases exist |
| ROOM-06 | Front desk room boots empty and with its example folder: registered room facts (handles, slot `text`, tables, no timeline, no morph), contract equals `[...patternsOwnedBy('front desk'), 'agents/*.md']`, `connector: text`, claimed widgets pointer-only, never-left-out single line when the folder is removed | feature | `php artisan test --compact tests/Feature/Rooms/FrontDeskBootTest.php` | Wave 0 |
| ROOM-15 | Team room boots: `owns` empty, `reads` equals the three paths, `tables` = `owner_alerts`, never-left-out line, `DraftsWaiting` still on `/admin` | feature | `php artisan test --compact tests/Feature/Rooms/TeamBootTest.php` | Wave 0 |
| ROOM-06/15 | Whole existing suite unchanged on both databases | full suite | `php artisan test --compact` | exists |
| ALRT-01 | Two stale copies of one alert text once; re-entrant second worker texts once; fresh claim not re-sent; claim 2 minutes old re-sent; `skip()` leaves a claimed alert alone; ended alerts stay ended | feature | `php artisan test --compact tests/Feature/Alerts` | new cases in a new file, existing tests unchanged |
| ALRT-01 | Duplicate `dedupe_key` returns the existing row, creates no second, sends no second text; NULL keys (business alerts) repeat freely; the table stays usable after the violation | feature | same | Wave 0 |
| ALRT-01 | Migration backfills a key and tolerates existing duplicate rows | feature | `php artisan test --compact tests/Feature/Alerts` | Wave 0 |
| ALRT-01 (D-09) | Only `SendOwnerAlert` texts an owner alert | arch | `php artisan test --compact tests/Unit/ArchitectureTest.php` | rule exists at :58-66; add `SendOwnerAlert` only used by `RaiseOwnerAlert`, `SendDueAlerts`, `Tests` |
| ALRT-02 | Token is written by the claim; `failed()` finds only this run's rows; a later text is not reported; a keyword (STOP) text marked handled at arrival is not blamed | feature | `php artisan test --compact tests/Feature/FrontDesk/FrontDeskFailureTest.php` | three existing tests change (below) |
| ALRT-02 (D-12(4)) | `unserialize(serialize($job))->failed()` on a fresh copy reports the right burst | feature | same | Wave 0 |
| ALRT-02 (D-11) | `catch` path then `failed()` (and the reverse, and `RunFrontDesk`'s own report then `failed()`) write one note and one alert | feature | same | exists in part ("reports a crash once even when failed() runs after...") |

### Existing tests that change, and when
- **Prep (D-14):** the three `RoomOwnershipTest` cases are removed after their replacements pass. Nothing else.
- **Front desk wrap:** only `exampleFolderFileCount()` in `tests/Pest.php`, 23 to 24. **Team wrap:** only the same number, 24 to 25. (The prototype added both rooms at once and used 25; the one-room-at-a-time numbers follow from each room adding one contract file.)
- **ALRT-01:** none (verified: alerts, front desk, inbound, records and channels folders, then the whole suite, on SQLite; alerts, front desk, inbound on Postgres).
- **ALRT-02:** exactly three tests in `tests/Feature/FrontDesk/FrontDeskFailureTest.php`: "tells the owner when the worker stops the run before it finishes", "reports the claimed text, not a newer one that has its own queued job (WR-03)" and "reports the customer text, not a web form that was claimed with it (WR-03)". They build rows with `handled_at` only; the fix is to give the rows a `claim_token` and the job the same token (`new ProcessInbound($id, $conversationId, $token)`; for the first test also `ClaimInboundBurst::handle($id, $token)`). Their assertions stay as they are. All other front desk, alerts and inbound tests pass unchanged.

### Sampling Rate
- **Per task commit:** the narrowest file for that task, on SQLite.
- **Per wave merge:** `php artisan test --compact tests/Feature/Rooms tests/Unit tests/Feature/Alerts tests/Feature/FrontDesk`.
- **Phase gate:** full suite green on SQLite and on `pgsql` (CI matrix) before `/gsd-verify-work`.

### Wave 0 Gaps
- [ ] `tests/Feature/Rooms/FrontDeskBootTest.php` and `TeamBootTest.php`, modeled on `MarketingBootTest.php`
- [ ] The line-185 hand-built-registry case (Feature directory)
- [ ] New alert race and dedupe tests, the serialize-then-`failed()` test, an additive arch rule
- [ ] Additive `RoomRegistryTest` cases for `claimedWidgets` (exists, is a Widget, not claimed twice)
- [ ] `OwnerAlertFactory`: add a state or two (claimed, stale claim) so tests do not hand-set columns (project factory rule)

## Security Domain

`security_enforcement` is not set to false in `.planning/config.json` (the file has no such key), so it applies.

### Applicable ASVS Categories

| ASVS Category | Applies | Standard Control |
|---------------|---------|-----------------|
| V2 Authentication | no | — (no screen or login changes) |
| V3 Session Management | no | — |
| V4 Access Control | no | No new route. The claimed widgets stay on the authenticated `/admin` panel |
| V5 Input Validation | yes, narrow | The alert key and token are built in code from integers, enum values and a UUID; nothing from a customer text reaches a key or a note (the existing "fixed words only" rule in `ReportFrontDeskFailure` stays) |
| V6 Cryptography | no | The claim token is an identifier, not a secret; `Str::uuid()` is sufficient |
| V11 Business logic (idempotency) | yes | The conditional-UPDATE claim and the unique key are the controls for ALRT-01 and ALRT-02 |

### Known Threat Patterns for this stack

| Pattern | STRIDE | Standard Mitigation |
|---------|--------|---------------------|
| Duplicate SMS to the owner (cost, alert fatigue) | Tampering / DoS | Atomic send claim plus unique key (ALRT-01) |
| A missing alert hidden by a duplicate guard | Denial of service | Stale-claim release after 2 minutes; claim taken only before the send; claim released if the report step throws (02.1 IN-11 rule) |
| Two workers writing contradictory outcomes (sent and skipped) | Tampering | Every outcome write is a conditional UPDATE while all three outcome columns are still null |
| Failure report blamed on the wrong customer text | Repudiation / Information disclosure | Rows selected by claim token, never by id ordering |
| Customer text content leaking into a note or alert | Information disclosure | Unchanged: fixed words only; the exception message never reaches a note |
| An agent action without a chokepoint | Elevation | Not applicable by D-09 (alert routing is code); the architecture rule keeps `SendOwnerAlert` the only owner-alert sender |

## Project Constraints (from CLAUDE.md)

- Files hold how the business runs; the database holds what happened; code reads business configuration only through `App\Icm\ActiveConfig`.
- Money is integer cents (no money in this phase).
- Tests first for anything touching sending or the sync; run `php artisan test` before calling a step done. TDD is on (`.planning/config.json`).
- Sending authority is enforced in code, not only in prompts. Owner alerts are code, not an agent action (D-09); the OutboundSender chokepoint pattern stays for customer sends.
- Ian reads code with AI help: the first time a Laravel concept appears, explain it in a few plain sentences tied to the code just written and add one line to the walk-through log at the end of `docs/sprints/sprint-1.1.md` (the newest sprint file). Concepts new in this phase: the conditional-UPDATE claim and its affected-row count, a unique index and NULLs, `createOrFirst` and savepoints, why a job property survives `serialize()` and what `failed()` runs on, a descriptor field that points without registering.
- Git under GSD: local commits, one per task, on `gsd/phase-06-front-desk-and-team-rooms`; never push or merge.
- No secrets in the repo; fictional example data only.
- WCAG 2.2 AA: the wraps change no screen; the axe check for these two rooms starts in Phase 11 (04 D-27).
- `docs/BLUEPRINT.md` gets an "Affects:" line for each decision and is updated in the same commit as any change to an area. This phase touches customer communication, team, knowledge, connectors and data, safety and compliance.
- Laravel Boost rules: use `php artisan make:` generators (`make:migration`, `make:test --pest`), pass `--no-interaction`, run `vendor/bin/pint --dirty --format agent` after PHP edits, do not add dependencies, do not create documentation files unprompted, run the narrowest tests and ask Ian to run the full suite. `.ai/rules/*.md` (app, migrations, tests, factories) hold only the integer-cents rule.
- Do not edit inside the Laravel Boost block in CLAUDE.md.
- Create factories when adding model columns that tests set (`OwnerAlertFactory`, `MessageFactory`).

## Plan Shape (suggested)

| Plan | Content | Tests that change |
|------|---------|-------------------|
| 06-01 prep | Line-185 hand-built-registry case; confirm :77 and :160 unit cases; remove the three feature tests; update BLUEPRINT carry note and the RoomChecksTest comment | the three D-14 tests only |
| 06-02 front desk wrap | `Room::claimedWidgets` plus guard plus additive registry tests; `FrontDeskServiceProvider`; example `rooms/front_desk/CONTEXT.md`; providers.php; boot test; SPEC prose; BLUEPRINT | `exampleFolderFileCount()` 23 to 24 only |
| 06-03 team wrap | `TeamServiceProvider`; `rooms/team/CONTEXT.md` (body sentence on who acted); boot test; providers.php; SPEC prose; BLUEPRINT | `exampleFolderFileCount()` 24 to 25 only |
| 06-04 ALRT-01 | owner_alerts migration with backfill; `SendOwnerAlert` claim and conditional end; `skip()` fix; `RaiseOwnerAlert` key; `SendDueAlerts` clause; factory states; architecture rule; tests first | none |
| 06-05 ALRT-02 | messages migration; `ClaimInboundBurst` token; `ProcessInbound` constructor token and `failed()`; `ReportFrontDeskFailure` report claim; serialize test | the three `FrontDeskFailureTest` cases |

Keep the two ALRT plans out of the wraps (D-15). Each plan ends with the test run on SQLite, and the phase ends with the CI matrix on Postgres.

## Sources

### Primary (HIGH confidence)
- This repo, read in this session: `app/Rooms/Room.php`, `RoomRegistry.php`, `RoomServiceProvider.php`, `Marketing/MarketingServiceProvider.php`, `GoogleBusinessProfile/GoogleBusinessProfileServiceProvider.php`, `app/Icm/RoomChecks.php`, `app/Icm/Loader.php`, `app/Actions/SendOwnerAlert.php`, `RaiseOwnerAlert.php`, `ReportFrontDeskFailure.php`, `ClaimInboundBurst.php`, `DispatchInbound.php`, `app/Jobs/ProcessInbound.php`, `SendDueAlerts.php`, `SweepUndispatched.php`, `app/SendRule/SafetyCheckStatus.php`, `app/Channels/TwilioTextChannel.php`, `tests/Pest.php`, `tests/Unit/ArchitectureTest.php`, `tests/Unit/Icm/RoomChecksTest.php`, `tests/Feature/Rooms/RoomOwnershipTest.php`, `MarketingBootTest.php`, `SpecTablesTest.php`, `tests/Feature/FrontDesk/FrontDeskFailureTest.php`, `.github/workflows/*.yml`, `docs/SPEC.md`, `docs/BLUEPRINT.md`.
- Installed Laravel 13.35.0 source: `Eloquent/Builder.php` (`createOrFirst`, `withSavepointIfNeeded`), `Queue/CallQueuedHandler.php`, `Queue/SyncQueue.php`, `Bus/DebounceLock.php`, `Foundation/Bus/PendingDispatch.php`.
- Prototype runs (scratch copy, not the repo): whole suite on SQLite with both wraps and both fixes (2,211 tests; failures limited to the three D-14 tests and the three ALRT-02 tests named above); alerts, front desk and inbound folders and the rooms, unit and sync folders on `postgres:17`; six targeted concurrency tests on SQLite and Postgres; a negative control against today's `SendOwnerAlert`.
- PostgreSQL 17 behavior of NULLs in unique indexes and of aborted transactions, run with `psql` inside the `postgres:17` container.

### Secondary (MEDIUM confidence)
- None.

### Tertiary (LOW confidence)
- None.

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH, nothing new and every API read in `vendor/`.
- Architecture: HIGH, prototyped end to end and run on both databases.
- Pitfalls: HIGH, each was reproduced in the prototype; the one residual risk is that two real OS processes were not driven (A3).

**Research date:** 2026-10-27
**Valid until:** 2026-11-26 (stable; revisit if Laravel is bumped past 13.35.0 or the room standard changes)
