# Phase 6: Front Desk and Team Rooms - Context

**Gathered:** 2026-10-27
**Status:** Ready for planning

<domain>
## Phase Boundary

Two kinds of change, kept in separate plans:

1. **The wraps (ROOM-06, ROOM-15).** The front desk and the team, which already work, become rooms in the room standard with no behavior change. Each gets `rooms/<room>/CONTEXT.md` naming what it owns and reads, a module that points at today's code, and a boot test. The existing suite passes unchanged on SQLite and Postgres: same replies, same holds, same alerts. The only test changes allowed are the example folder's file count (04 D-16) and the three ownership tests approved in D-14.
2. **The fixes (ALRT-01, ALRT-02).**
   - Each owner alert is texted exactly once, even when its job runs twice or two workers race: an atomic send claim and a unique de-duplication key.
   - A failed front-desk run reports exactly the burst of texts it claimed, using a claim token stored on the claimed rows.
   - The alert-timing and one-alert rules behave as before.

The wraps land and pass the existing suite unchanged before any ALRT-01 or ALRT-02 work changes a test (criterion 1). Nothing from backlog 999.5 is built.

</domain>

<decisions>
## Implementation Decisions

Earlier decisions are cited as "04 D-nn", "05 D-nn", "02 D-nn" and "02.1 D-nn".

### Locked, carried forward (do not reopen)
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

### Front desk room
- **D-01:** The front desk registers a `text` slot pointing at `App\Channels\TextChannel`, with no connector file. `.env` (`channels.text.driver`) keeps picking the driver, as 04 D-25 allows. A connector file comes when Twilio goes live, with CONN-01. A wrap only registers what exists. Affects: connectors and data, customer communication.
- **D-02:** The front desk handles the `agent` type, so every `agents/*.md` is the front desk's (every channel agent). There is only one agent file today. The phase that adds a later room's agent (content generation, for example) gives that agent its own type and names its test changes in that phase. This keeps 04 D-12 ('the room the agent serves'): today's only agent serves the front desk, and a later room's agent gets its own type and lives in that room's folder (04 D-04), so agents/ holds channel agents only. Affects: customer communication, knowledge.

### Both rooms: dashboard, tables, timeline
- **D-03:** Today's dashboard widgets are split by who acts, as pointers only. The front desk claims Today's conversations (`TodaysConversations`) and Paused conversations (`PausedConversationsWaiting`). The team claims Drafts waiting (`DraftsWaiting`: the approvals and the Needs you card). New leads (`NewLeads`) stays unclaimed until the leads room (Phase 7, ROOM-07). The widgets are still discovered in `app/Filament/Widgets`, and `RoomRegistry::addWidgetsTo()` skips widgets the panel already has, so nothing moves or reorders. Affects: customer communication, team.
- **D-04 (Ian's words):** "Each table has one owner, listed in the Room's tables (code only; nothing reads it at run time). Front desk lists messages and conversations, team lists owner_alerts. No column moves. Team's front-matter reads stays folder paths only (rules/sending.md for owner_alert, rules/replies.md for owner_name, per 04 D-12); 'reads who resolved each draft' is a plain sentence in the body of rooms/team/CONTEXT.md, never a front-matter entry, because reads takes folder paths and a table name there would be refused. Texts stay the platform's for connectors (04 D-23)." Team also reads escalate_to in agents/front-desk.md (04 D-12 table); the quoted list left it out. "Who acted" today is `messages.resolved_by` and `messages.resolution`. Affects: team, customer communication.
- **D-05 (Ian's words):** "Neither wrap registers timeline entry types or model nicknames. Today's activity types (owner_alerted, note, draft_held and the rest) stay platform types drawn by CustomerCard exactly as now, and today's nicknames stay in AppServiceProvider's morph map. Registering them as room entries would change how the customer card prints them (room entries print as customer-supplied unless marked trusted, 04 WR-04), and card tests stay unchanged until Phase 7." So the customer card is unchanged in Phase 6. Affects: customer communication, team.

### Alerts sent exactly once (ALRT-01)
- **D-06:** A claim with no outcome is retried, under the 02.1 IN-11 rule "never a missing alert for a duplicate". If a worker dies after claiming an alert and before recording sent, failed or skipped, the claim is released once the wait in D-07 has passed, and `SendDueAlerts` sends the alert again. The only duplicate this allows is a crash after the carrier accepted the text and before the row was written. Affects: team, safety and compliance.
- **D-07 (Ian's words):** "2 minutes, measured from the claim, and the claim is taken immediately before TextChannel::send, never when the alert is raised. The wait has to stay well above the text channel's 10-second request timeout (TwilioTextChannel line 59) so a slow send that is still running is never released. Worst case, an urgent alert lost to a crash goes out about 3 minutes late (2-minute wait plus the every-minute SendDueAlerts sweep)." Affects: team, safety and compliance.
- **D-08:** Business-wide alerts (`RaiseOwnerAlert::forBusiness()`: safety check down, back up or missing) get the atomic send claim like every alert, and no de-duplication key. `SafetyCheckStatus`'s own claim keeps deciding how many are raised, so its rule does not move into the alert table. The unique key covers the conversation alerts that today's `alreadyRaised()` check covers, and nothing more. Affects: team, safety and compliance.
- **D-09 (Ian's words):** "Owner alerts are code, not an agent action (BLUEPRINT Team: 'Model role. None; alert routing is code'), so the chokepoint rule does not apply and no checks-passed record is added. Record this so no later plan reopens it; the rule applies the day a model decides or writes an owner alert. As part of ALRT-01, a test proves SendOwnerAlert is the only code that texts an owner alert (TextChannel used only by OutboundSender and SendOwnerAlert), because exactly-once depends on every alert going through its claim." Today `TextChannel::send()` is called only at `app/Actions/OutboundSender.php:274` and `app/Actions/SendOwnerAlert.php:50`. Affects: team, safety and compliance.

### Failed-run report (ALRT-02)
- **D-10:** A failed run raises one `front_desk_failed` alert and writes one timeline note, as today, tied to the newest text in the burst that run claimed, with today's words. Only which texts count changes. The one-alert rule is unchanged, and adding a count of unanswered texts waits for the wording pass (deferred). Affects: team, customer communication.
- **D-11:** The failure report is made once per claim token, atomically on SQLite and Postgres. The token is the report's de-duplication key. Whichever path gets there first writes the note and raises the alert: the `catch` in `ProcessInbound::handle()`, or `ProcessInbound::failed()` after a timeout or a dead worker. The other path finds the report already made. This closes today's check-then-write race in `ReportFrontDeskFailure`, which can write two notes. Affects: team, customer communication.
- **D-12 (Ian's words):** The token's source is the researcher's call, under four conditions:
  - "(1) the token is stored on every claimed row by the same conditional update that claims it;
  - (2) the failed() copy can find it;
  - (3) it is unique per run, so failed() never matches rows an earlier or later run claimed;
  - (4) it behaves the same on the sync queue the tests use (phpunit.xml QUEUE_CONNECTION=sync) and the database queue production uses, and at least one test serializes the job and calls failed() on a fresh unserialized copy, so a sync-only pass cannot hide a production miss. Proven on SQLite and Postgres."

  Background: `failed()` runs on a fresh copy of the job, so it cannot see a token chosen inside `handle()`. `ProcessInbound` is debounced per conversation (`#[DebounceFor(20, maxWait: 90)]`), so only one dispatched copy runs. Affects: team, customer communication.
- **D-13 (Ian's words):** "Nothing in Phase 6. BLUEPRINT open item under customer communication, decided before the first client goes live: a front-desk run that fails before claiming any text leaves that text unanswered and unreported until the customer texts again (ProcessInbound has $tries = 1 and SweepUndispatched only re-queues texts never dispatched). Today's failed() only reported this case by accident, on an older answered text (the WR-22 wrong-text report), and never for a conversation's first text." Affects: customer communication, team.

### Tests and build order
- **D-14 (Ian's words; amends 04 D-16 and ROOM-06 for these three tests only):** "Ian approves, like 05 D-23, a second allowed test change for the front desk room beside the example folder's file count, for these three tests only (RoomOwnershipTest lines 77, 160 and 185). It amends 04 D-16 and ROOM-06's 'the one allowed test change' for these three only. The prep plan lands before the front desk wrap and passes on today's code; the wrap then changes only the file count, and every other test both wraps touch stays unchanged. Line 185 gets its own 'previous version stays live' unit case, written and passing before its feature test goes."
  - The three tests are 'refuses a claim of a file no room handles yet' (:77), 'names the room a file belongs to when the business folder does not have that room' (:160) and 'keeps the previous version live when the folder is refused for its owners' (:185) in `tests/Feature/Rooms/RoomOwnershipTest.php`.
  - In the prep plan, a test whose branch a test-only room can still reach with the real rooms registered stays a feature test, re-pointed. A test whose branch can't be reached follows the 05 D-23 pattern: a unit case with a hand-built `RoomRegistry`, written and passing first, then the feature test is removed.

  Affects: knowledge, customer communication.
- **D-15:** Build order follows criterion 1: the D-14 prep plan, then the front desk wrap, then the team wrap. Each wrap passes the existing suite unchanged on SQLite and Postgres. ALRT-01 and ALRT-02 come only after both wraps; their order between themselves is the planner's call. No fix goes into a wrap plan. Affects: none (build order only).

### Settled as out of scope
- **D-16 [deferred] (Ian's words):** "Stays deferred under 04 D-13; team keeps reading owner_alert from rules/sending.md. Trigger: decided in the 999.5 Owner Loop discuss, when alert settings grow past owner_alert (staff approvers, email alerts, re-alerts and a backup recipient). BLUEPRINT records the deferral with that trigger." Affects: team, customer communication.
- **D-17 [deferred]:** IN-11 (with no owner phone on file, a Jev outage adds a failed alert row every 5 minutes) is not in Phase 6. It comes from the 02.1 IN-11 rule, and its fix belongs to onboarding (requiring an owner phone), not to sending exactly once. It stays open in BLUEPRINT sections 6, 8 and 10, decided before the first client goes live. Criterion 3 keeps 999.5 untouched. Affects: team, knowledge, safety and compliance.

### Settled during planning (2026-10-27)
- **D-18:** D-03's claims go in a new trailing optional `Room` field, `claimedWidgets` (pointer only, never reaches the panel), checked at boot by `RoomRegistry::guardClaimedWidgets()` (the class exists, is a Filament widget, and only one room claims it); a boot test proves the panel's widgets and their order are unchanged. Keeps 04 D-17 (code and CONTEXT.md can't drift). Affects: customer communication, team.
- **D-19:** The front desk names its slot interface as the string `'App\Channels\TextChannel'`, as `Room` names its classes; the existing arch rule (`tests/Unit/ArchitectureTest.php`, "only the outbound chokepoint and the alert sender send texts") passes unchanged and is D-09's proof, with no new or tightened arch test. Affects: customer communication, safety and compliance.
- **D-20:** Both failure paths tie the report to the newest SMS the run claimed, or, when it claimed no SMS, the newest row it claimed (a web lead form alone, via `LeadController`, `RecordInbound`, `ProcessInbound`); a failed form-only burst is never unreported, with a test on both the `catch` path and `failed()`. Affects: team, customer communication.
- **D-21:** No two-connection race test; the send claim, the de-duplication key and the report claim are proven with simulated races on both databases. Affects: team, safety and compliance.

### Claude's Discretion
- Each room's never-left-out reason clause (`Room::$neverLeftOutBecause`). The front desk owns required files (sending, replies, triage). The team is needed because the front desk alerts the owner through it, and the life-safety alert goes out at any hour (04 D-09).
- The plain-words body of `rooms/front_desk/CONTEXT.md` and `rooms/team/CONTEXT.md`, and each contract's `reads` list (folder paths only), taken from the 04 D-12 table.
- The schema shape of the send claim (column names, and how `SendDueAlerts` picks up a released claim) and of the unique de-duplication key. One known constraint: on both SQLite and Postgres, a unique index treats NULLs as distinct, so a key built over `message_id` or `user_id` as nullable columns won't de-duplicate. Business-wide alerts need no key (D-08).
- How the unique-index migration treats any duplicate alert rows already stored in a development database. No client data exists (fictional examples only).
- Whether the boot test for a room that claims non-`RoomSection` widgets needs anything beyond Phase 4's existing checks.
- Placeholder wording of any new line, in the `PlainWords` style, with no exclamation points or em dashes (2026-10-17 principle).

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Phase scope and requirements
- `.planning/ROADMAP.md` § "Phase 6: Front Desk and Team Rooms": goal, five success criteria, planning notes
- `.planning/REQUIREMENTS.md`: ROOM-06 (with the D-14 amendment), ROOM-15, ALRT-01, ALRT-02
- `.planning/PROJECT.md` § Key Decisions: DEC-01 to DEC-14, "The chokepoint rule", "The model home is the next milestone"
- `CLAUDE.md` § Rules: the chokepoint rule, WCAG 2.2 AA, tests first for sending, the BLUEPRINT "Affects:" rule

### Prior phase decisions (locked)
- `.planning/milestones/v1.0-phases/02-text-front-desk/02-CONTEXT.md`: Phase 2 D-01 to D-56 (owner alerts D-18 to D-20, the burst claim, the front desk)
- `.planning/milestones/v1.0-phases/02.1-faster-answers/02.1-CONTEXT.md`: 02.1 D-01 to D-51 (OutboundSender D-49, outage alerts D-50)
- `.planning/phases/04-room-standard/04-CONTEXT.md`: room standard D-01 to D-27, especially D-09, D-12, D-13, D-16, D-19, D-21, D-23, D-25, D-27
- `.planning/phases/05-standard-rooms/05-CONTEXT.md`: 05 D-01 (folder names), D-02 (never-left-out line), D-23 (moving unreachable feature tests), D-32

### Review items behind the fixes
- `.planning/milestones/v1.0-phases/02-text-front-desk/02-REVIEW-DISPOSITION.md`: WR-03 (non-atomic send claim), WR-04 (check-then-insert de-dup, no unique index), WR-22 (failed() reports the newest text, not the burst; preferred fix is a claim token on the claimed rows)
- `.planning/milestones/v1.0-phases/02.1-faster-answers/02.1-REVIEW-DISPOSITION.md`: IN-11 close-out, Ian's condition "never a missing alert for a duplicate"

### Area map
- `docs/BLUEPRINT.md`: sections 1 (Customer communication), 6 (Team), 8 (Knowledge), 9 (Connectors and data) and 10 (Safety and compliance), updated in this context commit
- `docs/SPEC.md`: the ownership table and the two connector-kind lists (04 D-14, D-22, D-23)

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `App\Rooms\Room`, `RoomServiceProvider`, `RoomRegistry`: a room is a descriptor (`handles`, `slot`, `slotInterface`, `sections`, `tables`, `neverLeftOutBecause`). `app/Rooms/Marketing/MarketingServiceProvider.php` is the nearest sibling: a wrap of existing files with no register() or boot().
- `RoomRegistry::addWidgetsTo()` (`app/Rooms/RoomRegistry.php:101`) skips widgets the panel already has, so claiming the existing widgets is a pointer (D-03).
- `SafetyCheckStatus::claimThenSend()` (`app/SendRule/SafetyCheckStatus.php:122`): the existing atomic-claim-then-release pattern. A conditional update claims, and the claim is released when the send threw or reached nobody. This is the model for D-06.
- `ClaimInboundBurst` (`app/Actions/ClaimInboundBurst.php`): one conditional update per row, atomic on both databases. This is where the claim token is written (D-12 condition 1).

### Established Patterns
- `SendOwnerAlert::handle()` never throws, ends an alert as sent, failed or skipped, and checks `sent_at`/`skipped_at`/`failed_at` before texting. That check is not atomic (WR-03).
- `RaiseOwnerAlert::alreadyRaised()` checks before it inserts, on conversation, message, reason and user, with no unique index (WR-04). `forBusiness()` dedupes nothing on purpose (D-08).
- `SendDueAlerts` runs every minute and sends due, unended alerts. It is the sweep that re-sends a released claim (D-06, D-07).
- `ProcessInbound`: `$tries = 1`, `$timeout = 300`, `$failOnTimeout = true`, debounced per conversation. `handle()`'s catch reports `$burst->last()`, which is the right burst. `failed()` searches for the newest handled text at or before `messageId`, which is the WR-22 bug.
- `ReportFrontDeskFailure` dedupes on `message_id` + reason by checking before writing. The note is written before the alert.
- Tests run on `QUEUE_CONNECTION=sync` (`phpunit.xml:32`). Production uses the database queue (`.env.example`).

### Integration Points
- `bootstrap/providers.php`: the two new room providers are listed after the existing rooms.
- `examples/example-hvac/rooms/front_desk/CONTEXT.md` and `rooms/team/CONTEXT.md` join the example folder, so `exampleFolderFileCount()` (`tests/Pest.php:293`, now 23) moves.
- `tests/Feature/Rooms/RoomOwnershipTest.php:77,160,185` and `tests/Unit/Icm/RoomChecksTest.php`: the D-14 prep plan.
- New migrations for the send claim and de-dup key on `owner_alerts`, and for the claim token on `messages`, go in `database/migrations`.

</code_context>

<specifics>
## Specific Ideas

- Worst case for an urgent alert lost to a crash is about 3 minutes late: the 2-minute wait plus the every-minute sweep (D-07).
- The claim is taken immediately before `TextChannel::send`, never when the alert is raised, so an alert held until opening time is never "claimed" while it waits (D-07).
- The serialize-then-`failed()` test exists so that passing on the sync queue can't hide a miss on the database queue (D-12).

</specifics>

<deferred>
## Deferred Ideas

- **Wording pass:** the failed-run alert could say how many texts went unanswered, as the outage back-up alert does (02.1 D-50). BLUEPRINT section 6 open item. Affects: team, customer communication.
- **A run that fails before claiming any text** leaves that text unanswered and unreported until the customer texts again. BLUEPRINT section 1 open item, decided before the first client goes live (D-13).
- **Splitting `owner_alert` out of `rules/sending.md`:** stays deferred under 04 D-13, decided in the 999.5 Owner Loop discuss (D-16).
- **IN-11:** decided before the first client goes live (D-17).
- **A per-room agent type,** in the phase that adds a second agent (D-02).
- **A text-channel connector file,** when Twilio goes live with CONN-01 (D-01).

### Reviewed Todos (not folded)
- `2026-10-17-phone-design-pass.md` (the phone design pass for the dashboard, the Needs you card and the Business Folder page) belongs to Phase 11 (DSGN), and the wraps change no screen. It stays in `todos/pending`.

</deferred>

---

*Phase: 06-front-desk-and-team-rooms*
*Context gathered: 2026-10-27*
