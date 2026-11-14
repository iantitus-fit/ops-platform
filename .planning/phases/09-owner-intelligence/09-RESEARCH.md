# Phase 9: Owner Intelligence - Research

**Researched:** 2026-11-14
**Domain:** Laravel 13.35.0 scheduler and time zones, one-query time-to-booked with a PHP median, `gate:export` JSON-lines semantics, records redraw after commit, an optional "no files" room at the room standard
**Confidence:** HIGH (every finding below was read from this repo or from `vendor/laravel/framework` this session; the few `[ASSUMED]` items are small wording and boundary choices listed in the Assumptions Log)

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

#### Citation key

Every earlier decision is cited with its phase and file. Full paths:

| Short | Full path |
|---|---|
| PROJECT.md, REQUIREMENTS.md, ROADMAP.md | `.planning/PROJECT.md`, `.planning/REQUIREMENTS.md`, `.planning/ROADMAP.md` |
| 01-CONTEXT.md | `.planning/milestones/v1.0-phases/01-foundation/01-CONTEXT.md` |
| 02-CONTEXT.md, 02-REVIEW-DISPOSITION.md | `.planning/milestones/v1.0-phases/02-text-front-desk/` |
| 02.1-CONTEXT.md | `.planning/milestones/v1.0-phases/02.1-faster-answers/02.1-CONTEXT.md` |
| 04-CONTEXT.md | `.planning/phases/04-room-standard/04-CONTEXT.md` |
| 05-CONTEXT.md | `.planning/phases/05-standard-rooms/05-CONTEXT.md` |
| 06-CONTEXT.md | `.planning/phases/06-front-desk-and-team-rooms/06-CONTEXT.md` |
| 07-CONTEXT.md, 07-UI-SPEC.md | `.planning/phases/07-the-spine-and-leads/` |
| 08-CONTEXT.md, 08-REVIEW-DISPOSITION.md | `.planning/phases/08-schedule-and-booking/` |

IDs that repeat across phases are always written with their phase: WR-09 (02 WR-09 is the `gate:export` finding; 08 WR-09 is the visit-line finding behind IN-13), IN-06 and IN-13 here are always 08's, and D-23 (01 D-23 is the records `.gitignore` rule; 07 D-23 is the STOP-opens-no-project rule).

#### Locked, carried forward (do not reopen)
- DEC-01 to DEC-14 (PROJECT.md); 02 D-01 to D-56 (02-CONTEXT.md); 02.1 D-01 to D-51 (02.1-CONTEXT.md); 04 D-01 to D-27 (04-CONTEXT.md); 05 D-01 to D-33 (05-CONTEXT.md); 06 D-01 to D-21 (06-CONTEXT.md); 07 D-01 to D-23, D-21 and D-23 as amended (07-CONTEXT.md); 08 D-01 to D-46, D-39 as amended 2026-11-11 (08-CONTEXT.md).
- The alert-timing and one-alert rules, and 02.1 IN-11 ("never a missing alert for a duplicate"). Phase 9 raises no alert.
- PROJECT.md Key Decisions of 2026-10-19: "The project is the spine", "The model home is the next milestone", "One owner per fact"; and the room standard and v2.0 floor plan of 2026-10-21 ("a room gets a connector slot only when it talks to an outside system").
- WCAG 2.2 Level AA, the chokepoint rule and the 2026-11-11 visual rule (CLAUDE.md, Rules).
- Carried facts this phase builds on: records follow the database and are written whole through `RecordsWriter::write()` (DEC-03); the front desk loads the card rendered fresh from the database (02 D-36); existing code stays in `app/Records/` and the room points at it (04 D-19); `open-leads.md` and its tests are unchanged (07 D-17, 08 D-41); only the six named actions write appointments and blocks (08 D-15); `MessageStatus::onTheCustomersPhone()` is the one definition of what reached the customer.

Phase 9 adds no agent action: every new write (records files, the export's output) is code rendering from the database, so the chokepoint rule names no new chokepoint (06 D-09's reasoning).

#### Time to booked (BOOK-06)
- **D-01:** The clock starts at the contact's earliest inbound `messages` row, a text or a web form (both go through `RecordInbound`), as BOOK-06 says ("first inbound message"). An inbound that is only a keyword the platform handles (STOP, START, HELP and the rest of the list in `app/Channels/TextKeywords.php`) never starts the clock; the next real message does, the same way a first STOP opens no project (07 D-23, 07-CONTEXT.md). A contact with no such inbound (one the owner added by hand, 08 D-19) has no start. Affects: owner intelligence, customer communication, sales.
- **D-02:** The clock stops at the contact's first booking ever, by when it was booked: the earliest `booked_at` across the contact's appointments, whichever project it is on and whatever the visit date. A page booking and a booking by hand both count. A later cancel does not erase it. A returning customer's later jobs add no new number. One number per contact. Affects: owner intelligence, operations, sales.
- **D-03:** Wall-clock elapsed time, whatever the opening hours. When there is no qualifying inbound before the earliest `booked_at` (a hand-added contact booked before they ever wrote), the contact has no number: nothing shows and the contact is left out of the summary, never shown as zero or negative. Affects: owner intelligence.
- **D-04:** Worked out on read from `messages` and `appointments` wherever it is shown; nothing is stored. No new column, no new writer beside 08 D-15's named actions, nothing to backfill for Phase 8's bookings. BOOK-06's "recorded" is met by the two recorded facts. The dashboard summary and `time-to-booked.md` compute in one query for all contacts, never one query per contact, and a test counts the queries so that stays true. Affects: owner intelligence, connectors and data.
- **D-05:** Each contact's number shows on the contact screen only, labeled "Time to booked". The customer card is unchanged, so what the front desk's model reads does not change in this phase (02 D-36 untouched) and no card test changes. The owner's AI reads the business-wide number from `records/time-to-booked.md` (D-23). Affects: owner intelligence, customer communication.
- **D-06:** Format, used on the contact screen, in the section and in `time-to-booked.md`: minutes up to 90 ("42 minutes"); above 90, hours and minutes ("3 hours 10 minutes"); past 48 hours, days and hours ("2 days 4 hours"). Singular forms ("1 minute", "1 hour", "1 day"); "under a minute" when less than a minute passed. One formatter, shared by every place. Affects: owner intelligence.
- **D-07:** The dashboard summary is one line in the owner intelligence room's section: the median time to booked for contacts whose first booking (D-02) fell in the last 30 days, with how many contacts that is. The 30 days are counted in the business's time zone (`business/profile.md`). The median of an even count is the mean of the two middle values, rounded to the whole minute. The median uses the D-06 format. With no such contact the section shows "No new customers booked in the last 30 days." Affects: owner intelligence.
- **D-08:** Everything in this group follows the room (04 D-08, 04-CONTEXT.md): with the owner intelligence room off, the section hides and the contact screen shows no Time to booked line. Nothing is lost, because the number is worked out on read (D-04). Affects: owner intelligence.

#### Today's schedule and the nightly rebuild (RECS-02)
- **D-09 (Ian's ruling on a named conflict: 04 D-08 and D-09, 04-CONTEXT.md, against DEC-03, PROJECT.md, and 08 IN-06, 08-REVIEW-DISPOSITION.md):** The nightly rebuild is the platform's. `records:rebuild` is scheduled by the platform and always runs, as card renders do today, so IN-06's fix never depends on a room. The owner intelligence room owns only what is new to it: `today.md` and `time-to-booked.md`, written only while the room is live. Card rendering stays in `app/Records/` (04 D-19), so 04 D-09's condition ("if the customer card the front desk loads is rendered there") is not met and the room stays optional. No locked decision changes. Affects: owner intelligence, operations, knowledge.
- **D-10:** The rebuild runs just after midnight in the business's time zone (from `business/profile.md`, through `ActiveConfig`), with `withoutOverlapping` and `onOneServer` like the existing scheduled jobs. If the zone changes, the next run follows the new zone. The same run turns `today.md` over to the new day and writes or removes the room's files by whether the room is live (D-15). A missing business folder is reported as `records:rebuild` reports it today. The command's summary sentence names what it wrote. Affects: owner intelligence, operations.
- **D-11:** The nightly rebuild is enough to close 08 IN-06, as its disposition says (Ian, 2026-11-07). The `records/` card can still say "Next appointment" for a visit that started earlier that day; the card the front desk loads is always fresh (02 D-36). A sweep that redraws a card when its visit starts was offered and declined. Affects: owner intelligence, operations.
- **D-12:** `records/today.md` lists all of today's visits with their state (booked, done, cancelled) plus blocks, earliest first, in the business's time zone. The heading names the date it covers ("Friday, November 13"), so a reader can tell when a missed nightly run left it showing yesterday, and the zone is named once ("Times are in Central Time."). Each row: the time range ("1:00 PM to 2:00 PM"); what (the service name, or "Blocked" with the block's owner note quoted, neutralized like 08 D-18's visit note); the customer's name linked to their card file; the state. No phone, no visit note, no project label. An empty day is one plain line: "Nothing booked today." Affects: owner intelligence, operations.
- **D-13 (acceptance examples, Ian):** The plan turns these into tests before any code, rendering one fictional day: 9:00 AM AC repair for Dana (done), 11:00 AM to 12:00 PM blocked, 1:00 PM furnace check for Marcus (booked), 3:30 PM tune-up for Lee (cancelled). The file must let a reader answer:
  1. What's left today? 1:00 PM furnace check, Marcus.
  2. Did Dana's visit get done? Yes, marked done.
  3. Anything cancelled today? Lee's 3:30 PM tune-up.
  4. When am I blocked? 11:00 AM to 12:00 PM.
  5. Is anything booked tomorrow? Not in this file; it covers today only.
  6. What's Marcus's address? Not in this file.

  Affects: owner intelligence, operations.
- **D-14:** `today.md` is redrawn after each of 08 D-15's named actions commits (Book, Cancel, MarkDone, EditVisitNote, BlockTime, RemoveBlock; BlockTime and RemoveBlock fire no record change today), queued and locked the way `writeSummaries()` is, plus the nightly turnover (D-10). The named actions stay the only writers of appointments and blocks; the redraw only reads. Affects: owner intelligence, operations.
- **D-15:** With the owner intelligence room off, the next sync or rebuild deletes `today.md` and `time-to-booked.md`, so the owner's AI never reads a frozen schedule, and the router stops naming them (D-25). With the schedule and booking room off and owner intelligence on, `today.md` is still written and says in one line: "This business keeps its schedule outside this system." Affects: owner intelligence, operations, connectors and data.

#### gate:export (GATE-05)
- **D-16:** `final_text` is the body only when the status is sent or delivered (`MessageStatus::onTheCustomersPhone()`); otherwise null. Every line also carries the message's status, so a failed decision stays in the labeled data and a queued one reads as not yet out, never as sent (02 WR-09, 02-REVIEW-DISPOSITION.md). Affects: safety and compliance, customer communication, owner intelligence.
- **D-17:** `original_text` is always `gate.inputs.agent_reply`: the model's own words before the disclosure, the sign-off, the link line, the visit line or any owner edit, the same words the send rule judged. Null for a text with no model words, with no fallback to `body`. `final_text` stays the full text that went out. Affects: safety and compliance, owner intelligence. — **Reversibility:** costly — the export is the labeled dataset the thresholds are tuned from (02 D-07, 02.1 D-02/D-21); a later reader compares columns across exports made before and after.
- **D-18:** The export covers `agent_reply` plus 02.1's six kinds: `code_answer`, `partial_holding`, `complaint_holding`, `discount_holding`, `check_in_reply`, `added_info_reply` (02.1 D-02). Holding, handoff and life-safety texts stay out, and the command's docblock sentence stays: one line is one decision, and a holding reply follows a held draft whose line already carries that verdict. Booking texts stay out. Each line gains `kind` and whether model words were in (from `meta.parts`). If Phase 10's calibration needs the handoff or life-safety cases, it adds them there (Deferred). Affects: safety and compliance, owner intelligence.
- **D-19:** A draft closed as `answered` (02.1 D-21) keeps `resolution: answered` and gains `answered_by_message_id` from `meta.answered_by`, naming the `code_answer` line's `message_id`. Its `final_text` stays null because it never went out; the sentence's own line has the text. Affects: safety and compliance, owner intelligence.
- **D-20:** `--without-text` turns every new text into its length, as today. New fields (`status`, `kind`, the model-words flag, `answered_by_message_id`) are added and none is removed. `gate:export` stays a platform command in `app/Console/Commands/` (04 D-19). Tests come first, since this is the labeled data. Affects: safety and compliance.

#### The owner intelligence room (ROOM-13)
- **D-21:** Key and folder `owner_intelligence` (`rooms/owner_intelligence/`, `app/Rooms/OwnerIntelligence/`), registered in `bootstrap/providers.php`. Its contract: `owns: none`, tables none (it reads `messages`, `appointments` and blocks, 06 D-04), `reads` as folder paths only (`business/profile.md` for the time zone), `writes_records: [records/today.md, records/time-to-booked.md]`, `connector: none`. It is optional and stays off the never-left-out list (D-09). Affects: owner intelligence, knowledge.
- **D-22:** The folder holds only `CONTEXT.md`, like leads and team (07 D-18). Criterion 4's "example file" is met the way those rooms met it: the room owns no file and an empty room is valid (ROOM-01). No example records are committed: generated records are never committed (01 D-23, the `.gitignore` rule `/examples/*/records/*`), and un-ignoring them would reopen that. The room's `CONTEXT.md` body shows a short sample of each file it writes (the D-13 day and the time-to-booked line); the acceptance tests render the real files. `exampleFolderFileCount()` changes only for the new `CONTEXT.md` (04 D-16). Affects: owner intelligence, knowledge.
- **D-23:** `records/time-to-booked.md` holds a title, the read-only note and the D-07 summary line (median, count, last 30 days, business zone), or its empty line. `this-week.md` stays with backlog 999.1, untouched. `docs/SPEC.md`'s bridge list gains the file during execution (as 04 D-14 did for its table); `docs/BLUEPRINT.md` section 7 gains it in this context commit. Affects: owner intelligence, knowledge.
- **D-24:** The room's dashboard section holds the time-to-booked line only (D-07). Today's visits already show in the schedule room's section (08 D-40) and are not repeated. The room owns no file, so it is never shown as empty (04 D-21). The section gets the axe check in the room's boot test (04 D-27). Affects: owner intelligence, safety and compliance.
- **D-25:** `records/CLAUDE.md` names `today.md` ("What is on today: read today.md") and `time-to-booked.md` only while the room is live. The tracked `examples/example-hvac/records/CLAUDE.md` matches the room-on text, and the existing test keeps the two equal. Affects: owner intelligence, knowledge.

#### Carried in from Phases 7 and 8
- **D-26 (08 IN-13, 08-REVIEW-DISPOSITION.md, as Ian deferred it on 2026-11-12):** `VisitAskedAbout::VISIT_CHANGED` becomes "the line changed after it was shown", which is true in every case (the visit moved, another visit became the one asked about, or the line's own words changed). The owner's notice then reads "Draft sent without the visit line: the line changed after it was shown". Tests first. The forced test changes are the three assertions in `tests/Feature/Booking/ApprovedDraftVisitLineTest.php` at lines 270, 285 and 289. `docs/BLUEPRINT.md` line 133 (section 1) changes in the same commit as the code. Stored `meta.visit_line_left_off` on drafts already sent keeps the old words; history is not rewritten. Affects: customer communication, operations, team.
- **D-27:** 07's "[deferred] Phase 9" line (a business-wide project view that does not depend on the leads room, and a business-wide projects records file) is not built here. It is tagged [deferred] (Deferred). Projects keep reaching the owner's AI through each card (07 D-17). Affects: owner intelligence, sales.

#### Scope and the UI contract
- **D-28:** BOOK-06 and RECS-02's scope is confirmed as above (the ROADMAP planning note). Both stay in v2.0 coverage. Affects: owner intelligence, operations.
- **D-29:** A short stock-Filament UI-SPEC records the contract, the 07 D-21 and 07-UI-SPEC.md pattern: one contact-screen line and one room-section line, no design work. The 2026-11-11 visual rule holds: a finding only about looks goes to `.planning/todos/pending/2026-10-17-phone-design-pass.md`. Wording, order, keyboard use, focus, screen-reader names, contrast and the axe check gate the phase; the keyboard and VoiceOver walk is recorded for UAT. Affects: safety and compliance, owner intelligence.

### Claude's Discretion
- Names in the house style: the formatter, the query or class that works out time to booked, the redraw job or listener, the room's provider and section classes.
- The summary line's exact words, in the PlainWords style with no exclamation points or em dashes, for example "Median time to booked: 42 minutes, across 12 customers in the last 30 days." Ian reads it at plan review.
- How the D-14 redraw is wired (a new event, or the existing `CustomerRecordChanged` path plus a block hook), as long as it runs after commit, is queued and is locked like `writeSummaries()`.
- The exact minute past midnight for D-10, and how "midnight in the business zone" is expressed to Laravel's scheduler across daylight saving changes.
- The room's `CONTEXT.md` body wording, within D-21 and D-22.
- The test layout: the D-13 rendering tests, the query-count test (D-04), the export tests (D-16 to D-20) and the room's boot test.

### Deferred Ideas (OUT OF SCOPE)
- **[deferred] When per-project reporting is decided (BLUEPRINT section 7; PROJECT.md, "The project is the spine"), most likely beside backlog 999.1's `this-week.md`:** a business-wide project view that does not depend on the leads room, and a business-wide projects records file (07-CONTEXT.md Deferred, D-27). Affects: owner intelligence, sales.
- **[deferred] Phase 10:** if the safety-bar calibration needs handoff or life-safety cases in `gate:export`, it adds them there (D-18). Affects: safety and compliance, owner intelligence.
- **[deferred] Backlog 999.4:** the owner intelligence room's connector slot, with the MCP server for the owner's AI (D-21). Affects: connectors and data, owner intelligence.
- **[deferred] Backlog 999.1:** `who-owes-us.md` and `this-week.md` (unchanged, D-23). Affects: finance, owner intelligence.

Not in this phase (CONTEXT Phase Boundary): a time-to-booked line on the customer card (D-05), a breakdown by lead source, business-hours minutes, a redraw when a visit starts (D-11), a business-wide project view or projects records file, `who-owes-us.md` and `this-week.md` (backlog 999.1), the MCP server for the owner's AI (backlog 999.4), handoff and life-safety texts in the export, a briefing.

Planning notes carried from CONTEXT: tests first for the export, today's rendering and the D-04 query count; a plan that changes shared code sweeps for hollowed tests and names each by file and line (done in "Forced and hollowed test changes" below); the router text and the tracked example change together; no new package without Ian (04 D-18); if the planner recommends splitting the phase, stop and bring it to Ian before any plan is written; first-time Laravel concepts (scheduling in a time zone, a query that works out a median for every contact at once) each get a plain explanation and one line in the walk-through log (`docs/sprints/sprint-1.1.md`, "## Walk-through log" at line 102, the newest sprint file).
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| ROOM-13 | Owner intelligence room, to the room standard; it carries RECS-02 and BOOK-06. | "Room pattern" below: provider, `RoomSection` with `contentLines()`, example `CONTEXT.md`, boot test modelled on `LeadsBootTest`, axe via `RoomSectionAxe`; `exampleFolderFileCount()` 28 to 29 at `tests/Pest.php:321`. |
| RECS-02 | `records/today.md` is rendered from appointments on every booking change, and a nightly full rebuild of `records/` is scheduled. | "Nightly rebuild in the business zone" (scheduler mechanics, verified in vendor source), "Redraw hook" (new after-commit event, unique queued job, locked writer), `TodayRecord` rendering with `BookingSettings::dayText/spanText/zoneName`. |
| BOOK-06 | Minutes from first inbound message to booked appointment, visible on the contact and in a dashboard summary. | "Time to booked query" (one SELECT, keyword filter in PHP, median in PHP, verified on SQLite), `DurationText` formatter, contact-screen `TextEntry`, section `contentLines()`. |
| GATE-05 | `gate:export` counts only what went out, gives `original_text` one meaning, exports `answered` and the 02.1 fixed-reply kinds. | "gate:export map": the six code locations, storage shape of `meta.parts` / `meta.answered_by` / `gate.inputs.agent_reply`, and the tests the change forces or hollows. |
</phase_requirements>

## Project Constraints (from CLAUDE.md and .ai/rules)

- Files hold how the business runs; the database holds what happened. Code reads business configuration only through `App\Icm\ActiveConfig` (`.ai/rules/app.md`). The new scheduler closure, `TodayRecord`, `RecordsWriter` and the section all go through `ActiveConfig`.
- Money is integer cents (not touched this phase).
- Tests first for anything touching money, sending or the sync; run `php artisan test` before calling a step done. GSD `tdd_mode: true`, `test_command: php artisan test` (`.planning/config.json`).
- Git under GSD: local commits, one per task, never push, never merge, never rewrite history.
- No secrets in the repo; examples and test data are fictional (the D-13 day uses fictional Dana, Marcus and Lee).
- WCAG 2.2 AA: wording, order, keyboard use, focus, screen-reader names, contrast and the axe check gate the phase; look-only findings go to `.planning/todos/pending/2026-10-17-phone-design-pass.md` (CLAUDE.md visual rule).
- Every agent action goes through a chokepoint; Phase 9 adds no agent action (CONTEXT, 06 D-09).
- `docs/BLUEPRINT.md` gets an "Affects:" line for any change and is updated in the same commit (D-26 touches line 133; D-23 touches the `docs/SPEC.md` bridge list).
- First-time Laravel concepts: explain in plain sentences and add one line to the walk-through log (`docs/sprints/sprint-1.1.md`).
- Boost/pint: run `vendor/bin/pint --dirty --format agent` after PHP edits; use `php artisan make:` commands (`make:event`, `make:listener`, `make:job`, `make:test --pest`); pass `--no-interaction`.
- Do not change dependencies without approval (04 D-18). **This phase needs no new package.**

## Summary

Phase 9 is four pieces of code on existing seams, with no new package. The one genuinely new mechanism is the nightly rebuild in the business's time zone, and Laravel 13.35.0's scheduler gives only a static `->timezone(string|DateTimeZone|enum)`; it cannot take a closure. Because `routes/console.php` is `require`d on every Artisan boot, and in the test suite during app creation before any business folder is live, resolving the zone from `ActiveConfig` at definition time would read the database at boot and could never see the live zone in a test. The prescriptive answer is an every-minute event with a `when()` closure, which Laravel calls (through the container, so it can type-hint `ActiveConfig`) only at `schedule:run` time: it passes only when `now()` in the business zone reads `00:10`. That is exact for every real-world offset (all are multiples of 15 minutes), follows a changed zone on the next run, and is verifiable in a unit-style test with `travelTo`.

Time to booked is one SELECT: a `messages` join `conversations` join a sub-select of each contact's `min(booked_at)` (with the 30-day cutoff as a `having` for the dashboard), ordered by contact then time. The keyword filter and the median run in PHP, which is required (`TextKeywords` must never be matched in SQL) and also avoids `percentile_cont`, which SQLite lacks. The shape was prototyped against SQLite this session; the model's `Appointment` global `kind` scope was confirmed to compile into the sub-select. A test that counts queries with the same 3-then-10 pattern as `LeadsSectionTest.php:208-222` keeps it at one.

`gate:export` needs five edits in one file plus a handful of test changes: the kind filter, `final_text` through `MessageStatus::onTheCustomersPhone()`, `original_text` from `gate.inputs.agent_reply` (null when blank, because `holdWithoutWords()` stores an empty string), and four appended keys (`status`, `kind`, model-words flag, `answered_by_message_id`). The redraw hook is a new `ScheduleChanged` event dispatched by the six named actions, a listener, and a `ShouldBeUniqueUntilProcessing` job that calls a new locked `RecordsWriter` method; the room's rendering classes must not use the `File` facade (an existing arch test), so the file writes and the delete-when-room-off both live in `RecordsWriter`.

**Primary recommendation:** Build in this order, tests first each time: (1) `gate:export` (isolated, labeled data), (2) the D-26 constant and its two test lines, (3) `DurationText` + `TimeToBooked` (pure PHP plus one query), (4) `TodayRecord` + `RecordsWriter::writeOwnerIntelligence()` + router text + rebuild sentence, (5) the `ScheduleChanged` event, listener, job and the six dispatch lines, (6) the `Schedule::command('records:rebuild')` entry, (7) the room (provider, section, example `CONTEXT.md`, boot test with axe), (8) the contact-screen entry, then docs (SPEC bridge list, BLUEPRINT, walk-through log). Do not recommend a phase split: no plan needs more than the existing seams.

## Architectural Responsibility Map

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|-------------|----------------|-----------|
| Nightly `records:rebuild` at 00:10 business time | API / Backend (scheduler + console command) | Database (reads zone via `ActiveConfig`) | A scheduled command, run by `schedule:run`; the zone decision happens in a `when()` closure at run time |
| Time to booked, per contact and median | API / Backend (a read-only query class in `app/Rooms/OwnerIntelligence/`) | Database (one SELECT) | Worked out on read (D-04); keyword matching and median are PHP; no stored column |
| `today.md` and `time-to-booked.md` rendering | API / Backend (`RecordsWriter` + room render classes) | Filesystem (`records/`, via `RecordsWriter::write()` only) | Records follow the database (DEC-03); only `RecordsWriter` may use the `File` facade (arch test) |
| Redraw after a booking or block change | API / Backend (event, listener, unique queued job) | Cache (`records:` lock) | After commit, queued, locked, read inside the lock, same as `writeSummaries()` |
| Contact-screen "Time to booked" line | Frontend Server (Filament infolist, server-rendered Livewire) | API / Backend (query class) | One `TextEntry` in `ContactInfolist`; no client logic |
| Dashboard summary line | Frontend Server (`RoomSection` widget) | API / Backend (query class) | Shared view `filament.rooms.section`, one `<p>` per line |
| `gate:export` | API / Backend (Artisan command, stdout) | Database (reads `messages`) | Platform command in `app/Console/Commands/` (04 D-19); writes no file |
| Room contract and example folder | Database/sync (`ActiveConfig` reads the compiled version) | Filesystem (example folder `rooms/owner_intelligence/CONTEXT.md`) | The sync checks the contract against `RoomRegistry` |

## Standard Stack

### Core (all already installed; nothing to add)

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| laravel/framework | 13.35.0 [VERIFIED: `composer show laravel/framework`] | Scheduler, events, queue, Cache locks | The project's framework |
| filament/filament | v5.10.1 [VERIFIED: `composer show filament/filament`] | Contact infolist entry, `RoomSection` widget | Existing admin panel |
| pestphp/pest | v5.3.1 [VERIFIED: `composer show pestphp/pest`] | Tests (Unit, Feature, browser axe) | Existing runner; `tests/Pest.php` binds `TestCase` + `RefreshDatabase` to `Feature` only |
| nesbot/carbon | ^3.8.4 [VERIFIED: framework `requires` in `composer show laravel/framework`] | Time zone arithmetic | Ships with Laravel |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| `everyMinute()` + `when()` for the nightly run | `dailyAt('00:10')->timezone($zone)` with the zone read at definition time | Rejected: `routes/console.php` is `require`d on every Artisan boot (`Foundation/Console/Kernel.php` `discoverCommands()` loops `$this->commandRoutePaths` and `require`s each), and in tests during app creation (`vendor/laravel/framework/src/Illuminate/Foundation/Testing/TestCase.php:59`, `$app->make(Kernel::class)->bootstrap();`), before `RefreshDatabase` has migrated or any folder is live; definition-time reads would hit the database at boot and could not see the business zone in a test |
| One SELECT plus PHP median | `percentile_cont` in SQL | Rejected: Postgres only; SQLite has no such function, and the suite runs on both (`phpunit.xml` `DB_CONNECTION=sqlite`; `.github/workflows/tests.yml` matrix `database: [sqlite, pgsql]`) |
| New `ScheduleChanged` event | Reuse `CustomerRecordChanged` | `CustomerRecordChanged(int $contactId)` needs a contact and a block has none; reusing it also redraws `today.md` on every text and send |

**Installation:** none. **Version verification:** all versions above were read from `composer show` this session.

## Package Legitimacy Audit

No external package is installed or changed by this phase. Nothing to audit.

| Package | Registry | Age | Downloads | Source Repo | Verdict | Disposition |
|---------|----------|-----|-----------|-------------|---------|-------------|
| (none) | n/a | n/a | n/a | n/a | n/a | n/a |

**Packages removed due to [SLOP] verdict:** none
**Packages flagged as suspicious [SUS]:** none

## Architecture Patterns

### System Architecture Diagram

```
 BOOK / CANCEL / DONE / EDIT NOTE / BLOCK / REMOVE BLOCK   (the six named actions, inside DB::transaction)
        |  ScheduleChanged::dispatch()  (ShouldDispatchAfterCommit: held until the outer commit)
        v
  Listener QueueOwnerIntelligenceRedraw  -->  Job RedrawOwnerIntelligenceRecords (unique until processing)
                                                          |
 NIGHTLY: schedule:run (every minute) --when(now in business zone == 00:10), onOneServer,
          withoutOverlapping--> records:rebuild --> RecordsWriter::rebuild()
                                                          |   (rebuild: router, every card, summaries, then ...)
                                                          v
                                   RecordsWriter::writeOwnerIntelligence()
                         Cache::lock('records:owner-intelligence') ->block(...)  [DB read INSIDE the lock]
                                  |                                   |
                  room owner_intelligence live?                  room off?
                          yes: write records/today.md          delete both files
                               write records/time-to-booked.md (via File facade, inside RecordsWriter only)
                                  ^
        TodayRecord (visits + blocks of the local day)      TimeToBooked (1 SELECT + PHP) + DurationText
        reads ActiveConfig::displayTimezone(), hasRoom('schedule_and_booking')

 FOLDER SYNC (room added or removed) -- FolderSync::queueCardsForRoomChanges --> ScheduleChanged::dispatch() so the
        files appear or are deleted at once

 READ PATHS (no writes):  Contact screen "Time to booked"  /  Room section line  -->  TimeToBooked (same class)
 gate:export (stdout JSON lines):  messages WHERE kind IN (agent_reply + six) AND gate_outcome NOT NULL
```

### Recommended Project Structure
```
app/
├── Console/Commands/GateExport.php            # edited (D-16..D-20); RecordsRebuild.php sentence (D-10)
├── Events/ScheduleChanged.php                 # new, ShouldDispatchAfterCommit
├── Listeners/QueueOwnerIntelligenceRedraw.php # new; auto-discovered by its handle() type-hint
├── Jobs/RedrawOwnerIntelligenceRecords.php    # new; copy RenderCustomerCard's unique/lock/backoff shape
├── Records/RecordsWriter.php                  # + writeOwnerIntelligence(), dynamic routerMarkdown(), rebuild() calls it
└── Rooms/OwnerIntelligence/
    ├── OwnerIntelligenceServiceProvider.php   # room(): key owner_intelligence, label 'owner intelligence', sections
    ├── OwnerIntelligenceSection.php           # RoomSection; contentLines() returns the one line
    ├── TimeToBooked.php                       # query + keyword filter + median (read only)
    ├── DurationText.php                       # the one formatter (pure PHP)
    └── TodayRecord.php                        # renders today.md body (read only)
examples/example-hvac/rooms/owner_intelligence/CONTEXT.md   # the folder's only file
examples/example-hvac/records/CLAUDE.md                     # tracked router, room-on text
routes/console.php                                          # + Schedule::command('records:rebuild') entry
bootstrap/providers.php                                     # + OwnerIntelligenceServiceProvider::class
tests/Unit/OwnerIntelligence/DurationTextTest.php
tests/Feature/OwnerIntelligence/{TimeToBookedTest,TodayRecordTest,RedrawTest,NightlyRebuildTest}.php
tests/Feature/Rooms/OwnerIntelligenceBootTest.php
tests/Feature/Filament/ContactTimeToBookedTest.php
```

### Pattern 1: Nightly rebuild at 00:10 in the business zone
**What:** An every-minute event whose `when()` closure decides, at run time, whether it is 00:10 in the business zone.
**When to use:** Any schedule whose zone comes from data that can change at run time.

How Laravel 13.35.0 evaluates this, all read from `vendor/laravel/framework/src/Illuminate/Console/Scheduling/`:
- `->timezone()` accepts only `\UnitEnum|\DateTimeZone|string` and stores it in a property [VERIFIED: ManagesFrequencies.php:672-681, `public function timezone($timezone) { $this->timezone = enum_value($timezone); return $this; }`]. A closure is not accepted.
- `isDue()` compares the cron expression with the current time converted to that zone [VERIFIED: Event.php:324-325, `$date = $date->setTimezone($this->timezone);`, then `(new CronExpression($this->expression))->isDue($date->toDateTimeString())`].
- `when()` closures are run later, by `ScheduleRunCommand`, through the container, so a closure may type-hint `ActiveConfig` [VERIFIED: ScheduleRunCommand.php:125 `if (! $event->filtersPass($this->laravel))`; Event.php:773-782 `$container->call($callback, ...)`; `filtersPass()` is public at Event.php:348]. `onOneServer()` is only consulted after the filters pass (ScheduleRunCommand.php:125-140), so the one-server lock is taken once, at 00:10, not every minute.
- `withoutOverlapping($expiresAt = 1440, ...)` [VERIFIED: ManagesAttributes.php:184] defaults to a 24-hour lock expiry. Give the nightly run `withoutOverlapping(120)` so a killed run cannot hold the lock past tomorrow's 00:10.
- The existing entries in `routes/console.php` are all `Schedule::job(new X)->everyFiveMinutes()->withoutOverlapping()` or `->everyMinute()->withoutOverlapping()`; none uses a zone, `onOneServer` or `when` [VERIFIED: routes/console.php:16-25]. The cache default is the `database` store, which supports locks, so `onOneServer` works [VERIFIED: config/cache.php:18 `'default' => env('CACHE_STORE', 'database')`].

```php
// routes/console.php  (add the imports: ActiveConfig, Throwable)
// Rebuilds every file in records/ just after midnight in the business's time zone (D-10). The zone is read when the
// scheduler asks, not when this file loads, so a changed zone takes effect on the next run.
Schedule::command('records:rebuild')
    ->name('records:rebuild')
    ->everyMinute()
    ->when(function (ActiveConfig $config): bool {
        try {
            return now($config->displayTimezone())->format('H:i') === '00:10';
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    })
    ->withoutOverlapping(120)
    ->onOneServer();
```
The try/catch matters: an exception thrown inside a `when()` closure is not caught per event, so it would end that `schedule:run` pass and skip the events after it. Optional optimization: `->cron('10,25,40,55 * * * *')` instead of `everyMinute()` runs the closure four times an hour and still hits local 00:10 for every zone whose offset is a multiple of 15 minutes; use only with a comment.

**Daylight saving, with evidence.** 00:10 is never skipped or repeated in any zone the business is likely to use. I computed, for every PHP time zone identifier, which 2024 to 2028 transitions cover wall-clock 00:10 [VERIFIED: PHP script run this session]: skipped on one night a year in `Africa/Cairo`, `America/Asuncion`, `America/Coyhaique`, `America/Havana`, `America/Santiago`, `Asia/Beirut`, `Atlantic/Azores` (transition `00:00 -> 01:00`), and repeated in `America/Havana` and `Atlantic/Azores`; `America/Chicago`, `America/New_York`, `America/Los_Angeles`, `America/Denver`, `America/Phoenix`, `Europe/London` and `Europe/Berlin` are unaffected. No minute in hour 00 is safe for those seven zones, so do not search for a "never skipped" minute: 00:10 is right for every US and European zone. A repeated 00:10 runs the rebuild twice (it is idempotent and `withoutOverlapping` keeps the two apart); a skipped one means no rebuild that night, which D-12's dated heading already lets a reader notice. Record this one line in `docs/SETUP.md`; do not write a test that pins the skipped-night behaviour.

### Pattern 2: Time to booked is one SELECT plus PHP
**What:** One query returns, for every contact whose first booking is in scope, that contact's inbound messages that came before the first booking, in order. PHP drops keyword-only texts, takes the first remaining message, subtracts, and (for the dashboard) takes the median.
**Why PHP does the filtering:** D-01 forbids matching keywords in SQL; `TextKeywords` says "The comparison is done in PHP, never in SQL, because LIKE differs between SQLite and Postgres" [VERIFIED: app/Channels/TextKeywords.php:9-14].

```php
// The query, prototyped on SQLite in memory with the real column names (scratch run this session):
$first = Appointment::query()                       // the model's global scope adds appointments.kind = 'appointment'
    ->whereNotNull('contact_id')
    ->selectRaw('contact_id, min(booked_at) as first_booked_at')
    ->groupBy('contact_id')
    ->when($sinceUtc !== null, fn ($q) => $q->havingRaw('min(booked_at) >= ?', [$sinceUtc->format('Y-m-d H:i:s')]))
    ->when($contactId !== null, fn ($q) => $q->where('contact_id', $contactId));

$rows = Message::query()
    ->join('conversations', 'conversations.id', '=', 'messages.conversation_id')
    ->joinSub($first, 'first_booking', 'first_booking.contact_id', '=', 'conversations.contact_id')
    ->where('messages.direction', MessageDirection::In->value)
    ->whereColumn('messages.created_at', '<', 'first_booking.first_booked_at')
    ->orderBy('conversations.contact_id')->orderBy('messages.created_at')->orderBy('messages.id')
    ->select('conversations.contact_id', 'messages.id', 'messages.channel', 'messages.body', 'messages.meta', 'messages.created_at', 'first_booking.first_booked_at')
    ->get();   // or ->cursor(): still one query
```
Verified facts: the compiled SQL keeps the kind scope inside the sub-select (`where "appointments"."kind" = ? group by "contact_id"`, printed via `php artisan tinker` this session); block rows have `contact_id` null by the database check so `whereNotNull('contact_id')` is belt-and-braces (migration `2026_11_02_000003`, shape check `{$c('kind')} = 'block' and {$c('contact_id')} is null`). The SQLite prototype returned exactly the expected rows: a STOP then a real text for a contact whose cancelled visit was booked first (D-02: a later cancel does not erase it); a hand-added contact whose only message came after its booking returned no row (D-03); a contact booked outside the window returned no row. Postgres cannot be run locally (no `psql`/`pg_isready`), so the Postgres leg is proven by CI's `pgsql` matrix; the SQL is plain ANSI (inner joins, `min`, `having`, a derived table, a text-bound timestamp compared against a timestamp column) so no driver branch is needed.

PHP side, per contact in order: keep a running `$optedOut` flag and walk the rows; skip a row when its channel is `sms` and `TextKeywords::actionFor($row->meta['opt_out_type'] ?? null, $row->body, $optedOut)` is not null, updating `$optedOut` on `'stop'` (true) and `'start'` (false); the first row not skipped starts the clock. Tracking `$optedOut` reproduces `RecordInbound::keywordIn()`, which only treats a message as a keyword for the `Sms` channel and gives the contact's current opt-out state to `actionFor()` [VERIFIED: app/Actions/RecordInbound.php `keywordIn()`: `if ($data->channel !== MessageChannel::Sms) { return null; }` then `TextKeywords::actionFor($data->meta['opt_out_type'] ?? null, $data->body, $contact->isOptedOut())`]. A plain "YES" from a contact who is not opted out is therefore a real message, exactly as at record time. (Note the existing `Contact::hasTextedBusiness()` finds the opt-out message through the `activities` table instead; D-01 locks the `TextKeywords` route, so do not copy that.)

Seconds, not Carbon diffs: Carbon 3's `diffInMinutes` returns a signed float [VERIFIED: PHP run this session: `$a->diffInMinutes($b)` gave `float(42.5)` and `$b->diffInMinutes($a)` gave `float(-42.5)`]. Subtract `getTimestamp()` integers instead and treat any result `<= 0` as "no number". Build the cutoff in the business zone and convert to UTC for the query: `CarbonImmutable::now($zone)->startOfDay()->subDays(29)->utc()` (today plus the previous 29 local days; see Assumptions A2). Median: sort the seconds, middle value, or mean of the two middle values for an even count; the count is the number of contacts that have a number.

**Query-count test (D-04).** Copy the shape of `tests/Feature/Rooms/LeadsSectionTest.php:208-222` (verified): enable `DB::enableQueryLog()`, run the summary once with 3 qualifying contacts, flush, add 7 more contacts (include a keyword-only first message, a hand-added contact and a second booking per contact), run again and assert both counts are equal to each other and to 1 (the one SELECT; warm `ActiveConfig` first by calling `app(ActiveConfig::class)->displayTimezone()` so the version query is not in the count). The contact screen uses the same class with `$contactId` set: also one query.

### Pattern 3: One formatter, `DurationText`
A pure class (no Laravel boot), so its test lives in `tests/Unit/OwnerIntelligence/DurationTextTest.php` with a Pest dataset (Unit tests do not extend `TestCase`; `tests/Pest.php` binds it to `Feature` only). Input is whole seconds. Rules to pin with a dataset, from D-06: under 60 seconds is "under a minute"; otherwise round seconds to the nearest whole minute; up to and including 90 minutes is "N minute(s)" ("1 minute", "42 minutes", "90 minutes"); above 90 minutes up to and including 48 hours is "H hour(s) M minute(s)" ("3 hours 10 minutes"); past 48 hours is "D day(s) H hour(s)" ("2 days 4 hours"). Decisions D-06 does not state (see Assumptions A3): drop a zero part ("3 hours", "2 days"), and in the days form drop the leftover minutes. The median takes the raw mean seconds into the same formatter; do not round to a minute first, or 31 seconds becomes "1 minute".

### Pattern 4: `today.md` and `time-to-booked.md`
- Render in the room's classes, write and delete only in `RecordsWriter`. The arch test says only these use the File facade [VERIFIED: tests/Unit/ArchitectureTest.php:42-47: `arch('only the loader, the changelog and the records writer use the File facade')` ... `->toOnlyBeUsedIn(['App\Icm\Loader', 'App\Icm\Changelog', 'App\Records\RecordsWriter'])`], and `file_get_contents`, `file_put_contents`, `glob`, `scandir` are barred from `App\Rooms`, `App\Jobs`, `App\Listeners`, `App\Events`.
- New `RecordsWriter::writeOwnerIntelligence()` mirrors `writeSummaries()` [VERIFIED: RecordsWriter.php:85-95]: `ensureBusinessFolderExists()`, then `Cache::lock('records:owner-intelligence', 30)->block(10, fn)`, reading the database inside the closure. Inside: `if ($this->activeConfig->hasRoom('owner_intelligence'))` write both files with the private `write()`, else `File::delete` each if present. `write()` is private and hides booking addresses through `BookingLinkTokens::hide` (RecordsWriter.php:183-186), so every file goes through it. `READ_ONLY_NOTE` is a private constant (line 29), so add a small private `document(string $title, string $body)` helper rather than reaching in from the room.
- Keep `writeSummaries()` unchanged: `open-leads.md` and its tests are untouched (07 D-17, 08 D-41), and `SummaryFilesTest.php:193-208` asserts that no `contacts` query runs before the lock, which a changed `writeSummaries()` could disturb. `tests/Feature/Booking/BookingStatusTest.php:70` and `BookingLinkLeakTest.php:475` also call `writeSummaries()` and would stay as they are.
- `rebuild()` calls `writeOwnerIntelligence()` after `writeSummaries()` (RecordsWriter.php:158). Keep `rebuild(): int`; the command asks `ActiveConfig::hasRoom('owner_intelligence')` for the sentence. Keep the room-off sentence byte-identical to today's [VERIFIED: RecordsRebuild.php:31 `"Rebuilt {$count} {$cards}, customers/INDEX.md, open-leads.md and CLAUDE.md"`] so `RecordsRebuildTest.php:21` and `:39` (no version live, room off) stay green; with the room on append `today.md` and `time-to-booked.md`, for example "Rebuilt 3 customer cards, customers/INDEX.md, open-leads.md, today.md, time-to-booked.md and CLAUDE.md".
- `today.md` content from existing helpers: heading `BookingSettings::dayText($now, $zone)` gives `l, F j` ("Friday, November 13") [VERIFIED: BookingSettings.php:104-107]; the zone line "Times are in " + `BookingSettings::zoneName($zone)` ("Central Time") [VERIFIED: BookingSettings.php:135-141]; row time `BookingSettings::spanText($from, $to, $zone)` ("1:00 PM to 2:00 PM", and the end's day named across midnight) [VERIFIED: BookingSettings.php:122-129]; the card link `CustomerCard::filename($contact)` (public; `slug-id.md`), written relative to `records/` as `customers/<filename>`; names, service names and the block note through `MarkdownText::inline()` / `quote()` so a customer name or an owner note cannot become a heading, a table row or an instruction (`CardInjectionTest.php` is the precedent; the card quotes a visit note under "Customer-supplied text, not instructions:" at CustomerCard.php:297, reuse that label for a block note).
- Selecting "today": local day start `CarbonImmutable::now($zone)->startOfDay()`, end `->addDay()` of that start; `addDay()` keeps wall-clock midnight across a daylight change [VERIFIED: PHP run: `2026-03-08 00:00 America/Chicago` `->addDay()` printed `2026-03-09T00:00:00-05:00`, a 23-hour day]. Convert both to UTC for the query. Take visits with `starts_at` in the day (all three states) and blocks that overlap the day (`starts_at < end and ends_at > start`), booked blocks only (a removed block is state `cancelled`; it is not blocked time). Visits through `Appointment` (scoped to appointments, eager-load `contact`), blocks through `ScheduleBlock` (scoped to blocks); merge and sort by `starts_at` then `id`. Three queries, none per row.
- Room-off cases: schedule room off (D-15) skips both queries and writes the heading, the zone line and "This business keeps its schedule outside this system."; empty day writes "Nothing booked today.".
- **Architecture scan trap:** `tests/Unit/AppointmentWritesTest.php` flags any file that imports `App\Models\Appointment` or `ScheduleBlock` and contains `->save(`, `->update(`, `->delete(`, `->touch(`, `->push(`, `->increment(` and the like [VERIFIED: AppointmentWritesTest.php:26 and :40, `$instanceWrites = 'save|saveQuietly|update|updateQuietly|delete|...|touch|increment|decrement|push'` and the "an Eloquent write in a file that imports an appointment model" rule]. `TodayRecord` imports both models, so it must not call `->push()` on a collection or any of those names. Use `[...$a, ...$b]`, `array_merge` or `->concat()`.
- Router text (D-25): make `routerMarkdown()` consult `$this->activeConfig->hasRoom('owner_intelligence')` (never throws; false before the first sync [VERIFIED: ActiveConfig.php:84-91]). Room off returns today's exact text (RecordsWriter.php:116-126) so nothing else changes; room on adds "What is on today: read today.md" and a time-to-booked bullet. The tracked `examples/example-hvac/records/CLAUDE.md` becomes the room-on text.

### Pattern 5: The redraw hook (D-14)
Recommended: a new event `ScheduleChanged implements ShouldDispatchAfterCommit`, dispatched by all six named actions, after the write, inside the same transaction. `CustomerRecordChanged` is the model: `class CustomerRecordChanged implements ShouldDispatchAfterCommit { use Dispatchable; public function __construct(public int $contactId) {} }` [VERIFIED: app/Events/CustomerRecordChanged.php]. Book, Cancel, MarkDone and EditVisitNote already reach `CustomerRecordChanged` through `RecordRoomEntry::handle()` (RecordRoomEntry.php:52), but `BlockTime` and `RemoveBlock` write no timeline entry and have no contact, so a contact-keyed event cannot carry them [VERIFIED: BlockTime.php and RemoveBlock.php docblocks: "writes no timeline entry"]. One new event for all six keeps D-14 literal ("after each of the named actions commits") and means no-op calls (an already-cancelled visit, the same note again) dispatch nothing, because each action returns before its write. Where to add the line: after `ScheduleBlock::create(...)` in `BlockTime::store()` (inside the lock and transaction), after the `update` in `RemoveBlock`, and beside each existing `recordRoomEntry->handle(...)` call in `BookAppointment::store()`, `storeFromLink()`, `CancelAppointment`, `MarkAppointmentDone`, `EditVisitNote`. Do not use Eloquent model events on `Appointment`/`ScheduleBlock`: every `Appointment::factory()->create()` in the suite would then run a redraw (the sync queue runs jobs inline in tests, `phpunit.xml` `QUEUE_CONNECTION=sync`), hollowing the speed and isolation of dozens of existing tests.

Listener `QueueOwnerIntelligenceRedraw` (auto-discovered by the `handle(ScheduleChanged $event)` type-hint, as `QueueCustomerRecordRender` is [VERIFIED: QueueCustomerRecordRender.php docblock]) dispatches the job. The job copies `RenderCustomerCard`'s shape [VERIFIED: RenderCustomerCard.php]: `#[UniqueFor(300)]`, `implements ShouldBeUniqueUntilProcessing, ShouldQueue`, `uniqueId(): string` returning a constant, `WithoutOverlapping` middleware, `$maxExceptions = 3`, `backoff()`, `retryUntil()`; `handle(RecordsWriter $writer)` calls `$writer->writeOwnerIntelligence()`. Two events before it starts queue one job that reads the newest state, and the read is inside the lock, so the last writer wins with the database as it is.

Also dispatch `ScheduleChanged` from `FolderSync::queueCardsForRoomChanges()` when `$changed` contains `owner_intelligence` or `schedule_and_booking`, before its `Activity` query, so turning the room off deletes the files, and turning it on writes them, without waiting for midnight (D-15 "the next sync or rebuild"). That method already computes `$changed` from `array_diff` of the old and new room keys and runs after `app()->forgetInstance(ActiveConfig::class)` so the job sees the new version [VERIFIED: FolderSync.php:109 `app()->forgetInstance(ActiveConfig::class);`, :111 `$this->queueCardsForRoomChanges($active, $version);`, method at :132].

Known gap to surface to Ian (Open Question 2): a contact rename changes the card filename (`slug-id.md`), so a `today.md` link can go stale until the next booking change or the nightly run. D-14 lists only the six actions; do not widen it unasked.

### Pattern 6: The room (ROOM-13)
Nearest siblings with no owned file and no tables: `app/Rooms/Leads/` (has a section) and `app/Rooms/Team/` (none) [VERIFIED: LeadsServiceProvider.php, TeamServiceProvider.php]. The new provider:
```php
class OwnerIntelligenceServiceProvider extends RoomServiceProvider
{
    protected function room(): Room
    {
        return new Room(key: 'owner_intelligence', label: 'owner intelligence', sections: [OwnerIntelligenceSection::class]);
    }
}
```
`neverLeftOutBecause` stays null (optional). No `register()`/`boot()` override: nothing reads `ActiveConfig` or the database at boot (`RoomServiceProvider` docblock). `OwnerIntelligenceSection extends RoomSection` with `roomKey()` returning `'owner_intelligence'` and `contentLines()` returning the summary line or the empty line; the default shared view `filament.rooms.section` draws one `<p>` per line [VERIFIED: resources/views/filament/rooms/section.blade.php]. `RoomSection::emptyLine()` returns null because `owns` is empty (`if ($owns === [] || $ownedFiles !== []) { return null; }`), so the platform's "nothing set up yet" line never shows (D-24). `RoomSection::getViewData()` already catches throwables and shows `FAILED_LINE`. Sections sort by room key; `owner_intelligence` falls between `money` and `schedule_and_booking` as the UI-SPEC says, with no `$sort` set.

Example folder: `examples/example-hvac/rooms/owner_intelligence/CONTEXT.md` with the front matter below (same shape as leads, which the sync already accepts [VERIFIED: examples/example-hvac/rooms/leads/CONTEXT.md]):
```
---
type: room
owns: none
reads: [business/profile.md]
writes_records: [records/today.md, records/time-to-booked.md]
connector: none
---
```
`writes_records` entries must start with `records/` (RoomChecks.php:505-506; `RoomContractTest.php:179` already accepts `writes_records: [records/today.md]`). The body must say it writes `records/today.md` and `records/time-to-booked.md`, show a short sample of each (D-22), name the zone-in-profile read, say it has no outside system and that it hides when off, and say it owns no file. Boot test modelled on `LeadsBootTest.php`: `expect($version->file_count)->toBe(exampleFolderFileCount())`, the `Room` fields, the contract (`owns` `[]`, `SpecTables::patternsOwnedBy(File::get(base_path('docs/SPEC.md')), 'owner intelligence')` `[]`, `reads` `['business/profile.md']`, `writes_records` `['records/today.md','records/time-to-booked.md']`, `connector` `'none'`), `roomOwnedFiles()` `[]`, the folder holds only `CONTEXT.md`, `bootstrap/providers.php` contains the provider with `AppServiceProvider` still first, an optional-room case (delete the folder, `exampleFolderFileCount() - 1`, `hasRoom` false, section hidden, no Time to booked line), and the axe case with `RoomSectionAxe::assertClean(RoomSectionAxe::report($page, 'owner_intelligence'))` (both with a qualifying contact and with none). `docs/SPEC.md` already names "owner intelligence" as a room that "own[s] none of today's files" (SPEC.md:238), so `SpecTablesTest`'s allowed owner list needs no change.

`tests/Pest.php:321` is `return 28;` inside `exampleFolderFileCount()`; the new `CONTEXT.md` makes it 29. Every other count in the suite is derived from that function (checked by grep: no literal 28 in `tests/` or `app/`).

### Pattern 7: Contact screen entry
In `ContactInfolist::configure()` add one `TextEntry` after `next_appointment` and before `automatic_texts` [VERIFIED: ContactInfolist.php:49 `TextEntry::make('next_appointment')` then :53 `TextEntry::make('automatic_texts')`], `->label('Time to booked')`, `->state(fn (Contact $record): ?string => ...)`, and `->visible(fn (?string $state): bool => filled($state))` like "Next appointment". The state closure must return null when `! app(ActiveConfig::class)->hasRoom('owner_intelligence')` (D-08), when the contact has no number (D-03), and on any `Throwable` after `report($exception)` (UI-SPEC error row, Ian 2026-11-14). Test with `Livewire::test(ViewContact::class, ['record' => $dana->getRouteKey()])` and `assertSee`/`assertDontSee`, as `ContactByHandTest.php:203` and `:208-221` does for "Next appointment".

### Anti-Patterns to Avoid
- **Reading the zone when `routes/console.php` loads.** It runs at every Artisan boot and in tests before any folder is live.
- **Matching keywords in SQL or storing a minutes column.** D-01 and D-04.
- **`->push()`, `->update()`, `->save()` in any file that imports `Appointment` or `ScheduleBlock`.** The arch scan will flag the file as a writer.
- **The `File` facade, `glob()` or `file_put_contents()` outside `RecordsWriter`.** Existing arch tests.
- **Changing `writeSummaries()`.** It is pinned by 07 D-17 / 08 D-41 and by `SummaryFilesTest`.
- **`Carbon::diffInMinutes` for the elapsed time.** Signed float in Carbon 3.
- **Putting the new fixtures into `gateExportDecisions()`.** That fixture feeds five count assertions (`GateExportTest.php:136`, `:243`).

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| "Is it 00:10 in the business zone" | A custom cron or a `schedule:run` wrapper | `Schedule::command()->everyMinute()->when(...)->withoutOverlapping(120)->onOneServer()` | Laravel's mutexes, per-minute single-server lock and skipped-event reporting |
| Date, zone and day-text strings | `date()` formatting | `BookingSettings::dayText/timeText/spanText/zoneName` | The booking page, the card and the owner screens already read alike; DST-safe |
| Markdown neutralizing | A new escaper | `MarkdownText::inline/quote/tableCell` | The card injection tests rely on one escaper |
| Writing a records file whole | `file_put_contents` | `RecordsWriter::write()` (via `File::replace` plus `BookingLinkTokens::hide`) | Atomic replace; a reader never sees half a file |
| Queue burst collapse and per-job lock | A cache key by hand | `ShouldBeUniqueUntilProcessing` + `#[UniqueFor]` + `WithoutOverlapping` as in `RenderCustomerCard` | Proven pattern in this repo |
| "Reached the customer" | A new status list | `MessageStatus::onTheCustomersPhone()` | The one definition (CONTEXT carried facts) |
| "Is this text a keyword" | A new word list | `TextKeywords::actionFor()` | Matches Twilio the same way the recorder did |
| Room registration, section gating, empty line, failure line | Custom widgets | `RoomServiceProvider`, `RoomSection` | The room standard (04) |

**Key insight:** every capability here already has one owner in the repo. The phase is wiring and rules, not new infrastructure.

## gate:export Map (GATE-05, D-16 to D-20)

### Where each thing lives today (all in `app/Console/Commands/GateExport.php`, read this session)

| Concern | Location | Verbatim |
|---------|----------|----------|
| Kind filter | :51 | `->where('kind', MessageKind::AgentReply->value)` followed by `->whereNotNull('gate_outcome')` (:52) and the `--since` filter on `created_at` (:53) |
| `final_text` | :89 | `$finalText = in_array($message->status, [MessageStatus::Draft, MessageStatus::Withdrawn], true) ? null : $message->body;` (the bug: a `Queued` or `Failed` row has a body, so it is exported as sent) |
| `original_text` | :90 | `$originalText = $message->original_body ?? $message->body;` (three meanings: the owner-edit original, else the body, which for an approved draft is the composed text with disclosure and sign-off) |
| Line head and tail | :92-101, :112-118 | keys in order `message_id, conversation_id, decided_at, outcome, reason_code, reason_plain, label, verdict`, then `final_text, original_text` (or the two `_length` keys), then `resolution, resolved_at, resolved_by, owner_edited` |
| `--without-text` | :103-106, :128-145 | `$line['original_text_length'] = mb_strlen($originalText);` and `withoutText()` replaces `gate.inputs.customer_text` and `.agent_reply` (:130), each code finding's `match`, the classifier `error`, and each failed attempt `error` with `_length` keys |
| Docblock sentence to keep | :17-23 | "Holding, handoff and life-safety texts are not decisions and are left out." |

### How the data is stored for each kind

- `agent_reply` (sent, held, edited, approved, discarded, replaced): the verdict is the stored `gate` JSON. `DecideSendOrDraft::decide()` writes `'inputs' => ['customer_text' => $customerText, 'agent_reply' => $reply->reply]` (DecideSendOrDraft.php:80), the model's own words before any disclosure, sign-off, link line or visit line. `holdWithoutWords()` (a price or discount question code could not answer) writes `'agent_reply' => ''` (DecideSendOrDraft.php:144), an **empty string, not null**: map blank to null for D-17. A draft carries no `meta.parts` (only `SendCombinedReply` writes it, SendCombinedReply.php:45 `'meta' => [...($attributes['meta'] ?? []), 'parts' => $parts->partNames()]`).
- The six code-assembled kinds, written by `SendCombinedReply::handle()`: `MessageKind::assembledByCode()` returns `[self::PartialHolding, self::ComplaintHolding, self::DiscountHolding, self::CheckInReply, self::AddedInfoReply, self::CodeAnswer]` (MessageKind.php:58-67). With no model words the row gets `gate_outcome` `GateOutcome::Send`, `gate_reason` `'fixed_reply'` and `'gate' => ['version' => 1, 'fixed' => true, 'reason' => ['code' => 'fixed_reply', 'plain' => null], 'customer_reading' => $reading->toArray()]` (SendCombinedReply.php:49-56), so there is **no `inputs.agent_reply`**: `original_text` is null, as D-17 wants. With model words it takes the caller's verdict attributes, which do carry `inputs.agent_reply`. Every one has `gate_outcome` set, so the existing `whereNotNull('gate_outcome')` stays right. The sender also writes `status` queued then sent or failed, so D-16 matters for these kinds too.
- "Were model words in" (D-18): read `meta.parts` the way the sender does. `OutboundSender::partsOf()` (OutboundSender.php:571-591): non-empty `meta.parts` is used as written; with none, `kind === null || kind === AgentReply` means `['model_words']`, and `CodeAnswer, PartialHolding, CheckInReply, AddedInfoReply` mean `['code_sentences', 'model_words']`, any other kind `[]`. `tests/Pest.php`'s `carriesNoModelWords()` mirrors the same rule. Implement a small private method in `GateExport` with the same rule and a parity test (a data-driven test over the eight kinds with and without `parts`); do not change `OutboundSender` for it.
- `answered` (D-19): `ResolveDraft::closeAsAnswered()` writes `'status' => MessageStatus::Withdrawn, 'resolution' => DraftResolution::Answered, 'resolved_by' => null` and `'meta' => [...($locked->meta ?? []), 'answered_by' => $answer->id]` (ResolveDraft.php:141-147). `answered_by_message_id` is `$message->meta['answered_by'] ?? null`, cast to int when present, null on every other line. `final_text` is already null (Withdrawn is not on the phone). The answering line is a `code_answer` row, now in the export, so its `message_id` matches.
- `MessageStatus::onTheCustomersPhone()` is `return [self::Received, self::Sent, self::Delivered];` (MessageStatus.php:29).

### Recommended `decision()` (add tests first)
```php
$gate = $message->gate ?? [];
$wentOut = in_array($message->status, MessageStatus::onTheCustomersPhone(), true);
$finalText = $wentOut ? $message->body : null;
$agentReply = $gate['inputs']['agent_reply'] ?? null;
$originalText = is_string($agentReply) && trim($agentReply) !== '' ? $agentReply : null;   // blank means no model words
// --without-text: 'final_text_length' => $finalText === null ? null : mb_strlen($finalText),
//                 'original_text_length' => $originalText === null ? null : mb_strlen($originalText),
// append after 'owner_edited' (keeps the first fourteen keys where they are):
//   'status' => $message->status->value, 'kind' => $message->kind->value,
//   'has_model_words' => $this->hasModelWords($message),
//   'answered_by_message_id' => isset($message->meta['answered_by']) ? (int) $message->meta['answered_by'] : null,
```
and the filter `->whereIn('kind', [MessageKind::AgentReply->value, ...array_map(fn (MessageKind $kind): string => $kind->value, MessageKind::assembledByCode())])`. Update the docblock to say what is now included, keep its "Holding, handoff and life-safety texts" sentence, and mention booking texts stay out. Key names `status`, `kind`, `answered_by_message_id` are fixed by D-18 to D-20; `has_model_words` is the planner's name (Assumptions A6).

## D-26 and the shared-code sweep

**D-26 confirmed.** `app/Rooms/ScheduleAndBooking/VisitAskedAbout.php:31` is `public const string VISIT_CHANGED = 'the visit changed after the line was shown';` with its docblock at :27-29 ("the visit moved, or another visit became the one asked about (WR-09)"); new text `'the line changed after it was shown'`. The constant is used once, at `app/Actions/SendOwnerReply.php:240` (`: VisitAskedAbout::VISIT_CHANGED;`), whose docblock at :221 also names it. In `tests/Feature/Booking/ApprovedDraftVisitLineTest.php`: **:270** is the test title `it('sends the draft without the visit line when the visit changed after the line was shown (WR-09)', ...)` (a name, not an assertion; rename it so the suite reads the same), **:285** is `->assertNotified(Notification::make()->title('Draft sent without the visit line: the visit changed after the line was shown')->warning());` and **:289** is `->and($draft->fresh()->meta['visit_line_left_off'])->toBe('the visit changed after the line was shown')`. Only :285 and :289 fail on the code change; the two dataset rows below (`'the visit moved to another day'`, `'an earlier visit booked'`) describe cases and keep their names. Nothing else in `app/`, `tests/` or `resources/` contains the string [VERIFIED: grep this session; the only other hits are `docs/BLUEPRINT.md:133` and the historical 08 review files, which are not edited]. `app/Livewire/ConversationThread.php:133` only prints the stored `visit_line_left_off`, so old rows keep the old words with no code change. Change `docs/BLUEPRINT.md:133` (the quoted reason inside the long "Built" bullet) in the same commit, and add the new case the reason now covers (the line's own words changed) as a third dataset row: edit the booking file's `visit_line` after the owner sees it. Tests first: write the new assertions, watch :285/:289 fail on the old constant, then change it.

### Forced and hollowed test changes (file and line)

| Test | Line | Change | Why |
|------|------|--------|-----|
| `tests/Pest.php` | :321 `return 28;` | 29 | The new example `CONTEXT.md`; every other count derives from `exampleFolderFileCount()` |
| `tests/Feature/Booking/ApprovedDraftVisitLineTest.php` | :270, :285, :289 | as D-26 above | The constant's words change |
| `tests/Feature/Records/RecordsRebuildTest.php` | :45-51 (`keeps the tracked router in the example folder equal to the rendered one`) | call `activateExampleFolder()` first, then compare | `routerMarkdown()` becomes room-aware; with no version live it returns the room-off text and no longer equals the tracked room-on file |
| `tests/Feature/Records/RecordsRebuildTest.php` | :21, :39 (`expectsOutput('Rebuilt ... and CLAUDE.md')`) | **no change** if the room-off sentence stays byte-identical | No version is live in those tests, so the room is off; add new tests for the room-on sentence |
| `tests/Feature/Records/RecordsRebuildTest.php` | :34 | no change | Compares the written router with `routerMarkdown()`; both room-off |
| `tests/Feature/FrontDesk/GateExportTest.php` | :144-147 (`array_keys($lines[0])` list) | append the four new keys | Forced by D-20 |
| `tests/Feature/FrontDesk/GateExportTest.php` | :168-184, :186-196 | hollowed: add new tests where `body`, `original_body` and `gate.inputs.agent_reply` all differ | In the existing fixture all three are equal (or `original_body` equals `agent_reply`), so these tests would pass if the code still read `original_body`; they no longer prove D-17 |
| `tests/Feature/FrontDesk/GateExportTest.php` | :150-166 | hollowed for `original_text` only (it never asserts it) | Add the `own` line's `original_text` (`'What is the system doing?'`, the agent reply, not the body `'What is the system doing? - Dana (AI assistant)'`) |
| `tests/Feature/FrontDesk/GateExportTest.php` | :136, :243 `toHaveCount(5)`; :211-217 | unchanged | Keep new-kind fixtures out of `gateExportDecisions()`; build a second helper so these stay true |
| `tests/Feature/Booking/AppointmentActionsTest.php` and the other Booking tests that run the six actions | none expected | re-run | The actions now dispatch `ScheduleChanged`; with the sync queue the redraw runs inline in the test's temporary folder (the example folder includes the new room), so confirm none of them counts queries or jobs through the action path. Searched: `DB::listen`/`beforeExecuting` in Booking tests are at `VisitLineTest.php:329` (calls `VisitAskedAbout::find` only), `AutomaticTextsRuleTest.php:109`, `BookingLinkTest.php:289`, `BookingSendTest.php:244` (conversation race), `BookingSendOnceTest.php:137` (booking_sends update); none wraps an action |
| `tests/Unit/AppointmentWritesTest.php` | :26-45 | no change to the allowed list; new files must not trip it | See the scan trap in Pattern 4 |
| `tests/Feature/Records/SummaryFilesTest.php`, `RenderCustomerCardJobTest.php` | none | `writeSummaries()` and `RenderCustomerCard` are unchanged | Keeps `open-leads.md` and card tests as they are (07 D-17, 08 D-41, D-05) |
| Customer card tests (`CustomerCardTest`, `AppointmentCardTest`, `CardInjectionTest`) | none | The card is unchanged (D-05) | |

## Common Pitfalls

### Pitfall 1: The business zone read at boot
**What goes wrong:** `dailyAt('00:10')->timezone(app(ActiveConfig::class)->displayTimezone())` in `routes/console.php` queries the database on every Artisan command (including `migrate` on a fresh database, where it throws) and, in tests, runs during app creation before a folder is live, so it always sees the app zone.
**How to avoid:** the `when()` closure in Pattern 1. **Warning sign:** a nightly test that passes only after the schedule is "rebuilt" by hand.

### Pitfall 2: Test data stored in the wrong zone
**What goes wrong:** Eloquent saves a Carbon's wall time and drops the zone. `AppointmentFactory::at()` converts to UTC for that reason (its docblock says so, AppointmentFactory.php `at()`); `booked_at` is **not** touched by `at()` and defaults to `now()`; `MessageFactory` has no time state at all.
**How to avoid:** in every time-to-booked test set `booked_at` and the message's `created_at` explicitly, each `->utc()` (for example `Carbon::parse('2026-11-10 09:00', 'America/Chicago')->utc()`), and freeze "now" with `travelTo(...)`. **Warning sign:** a time to booked that is off by the zone offset.

### Pitfall 3: An empty-string agent reply read as words
**What goes wrong:** `agent_reply` is `''` for a held draft with no model words, so `$gate['inputs']['agent_reply'] ?? null` is not null. **How to avoid:** `trim() !== ''` as in the recommended `decision()`. Test it with a `holdWithoutWords()`-shaped fixture.

### Pitfall 4: Redraw side effects in unrelated tests
**What goes wrong:** the six actions now cause a job; QUEUE is `sync` in tests, so the job runs inline and writes `records/today.md` into each test's temp folder. A test that fakes the queue and counts pushes would see an extra job. **How to avoid:** the sweep above (none found); new tests that `Queue::fake()` must list `RedrawOwnerIntelligenceRecords` or use `Queue::fake([ProcessInbound::class])` as `tests/Pest.php` does.

### Pitfall 5: The after-commit event inside the booking lock
`BookAppointment::underLock()` holds the cache lock `schedule:book` around `DB::transaction($write)` and releases it in `finally`; `ShouldDispatchAfterCommit` fires at that commit, so with the sync queue the redraw runs while the lock is still held. It takes a different lock (`records:owner-intelligence`) and only reads appointments, so there is no deadlock; in production the job is queued and runs later.

### Pitfall 6: Stale card link after a rename
See Open Question 2.

### Pitfall 7: `Contact::hasTextedBusiness()` is not the keyword rule
It identifies the opt-out message through the `activities` table in SQL. D-01 says PHP and `TextKeywords`; use the PHP walk in Pattern 2.

### Pitfall 8: Section order and the dashboard assertion
`DashboardWidgetsTest.php:174` and the room boot tests assert the order of the four home widgets only, with no version live or with rooms whose sections come from other keys; a new section sorted by key cannot reorder those. Check by running the Filament and Rooms folders.

## Code Examples

### The six acceptance assertions for `today.md` (D-13), shape for the test
```php
// Freeze the day, then build it. Dana 9:00 AM AC repair done; block 11:00 AM-12:00 PM "Truck in the shop";
// Marcus 1:00 PM furnace check booked; Lee 3:30 PM tune-up cancelled. All times America/Chicago, stored UTC.
$this->travelTo(Carbon::parse('2026-11-13 08:00', 'America/Chicago'));
activateExampleFolder();
Appointment::factory()->for($dana)->done()->at(Carbon::parse('2026-11-13 09:00', 'America/Chicago'), 60)->create(['service_name' => 'AC repair']);
ScheduleBlock::factory()->at(Carbon::parse('2026-11-13 11:00', 'America/Chicago'), 60)->create(['note' => 'Truck in the shop']);
// ... Marcus (booked), Lee (->cancelled())
app(RecordsWriter::class)->writeOwnerIntelligence();
$today = File::get(icmPath('records/today.md'));
expect($today)->toContain('# Friday, November 13')->toContain('Times are in Central Time.')
    ->toContain('1:00 PM to 2:00 PM')->toContain('Furnace check')
    ->not->toContain('+1')->not->toContain('Elm Street');         // answer 6: no phone, no address
// order: Dana 9:00, blocked 11:00, Marcus 1:00, Lee 3:30 (assert with a regex over the four time ranges)
```
`Appointment::factory()` creates a project per contact (`project_id` closure) and a `booked_by_user_id` user; it needs no extra setup. Dana, Marcus and Lee each need a `Contact::factory()->create(['name' => ...])`.

### Scheduler test shape
```php
$event = collect(app(Schedule::class)->events())->first(fn (Event $event): bool => str_contains((string) $event->command, 'records:rebuild'));
expect($event)->not->toBeNull()->and($event->withoutOverlapping)->toBeTrue()->and($event->onOneServer)->toBeTrue();
activateExampleFolder();                                           // business/profile.md timezone is America/Chicago
$this->travelTo(Carbon::parse('2026-11-14 00:10', 'America/Chicago'));
expect($event->filtersPass(app()))->toBeTrue();                    // public method, Event.php:348
$this->travelTo(Carbon::parse('2026-11-14 00:09', 'America/Chicago'));
expect($event->filtersPass(app()))->toBeFalse();
// zone change: breakFile('business/profile.md', fn (string $c) => str_replace('America/Chicago', 'America/New_York', $c));
// $this->artisan('icm:sync'); at 00:10 Chicago (01:10 New York) it is false; at 00:10 New York it is true.
// DST: loop every minute of 2026-11-01 (25 local hours) and 2026-03-08 (23) in Chicago; the count of true is 1 each.
```
`Event::$command`, `$withoutOverlapping` and `$onOneServer` are public properties [VERIFIED: ManagesAttributes.php:65 `public $withoutOverlapping = false;`, :79 `public $onOneServer = false;`; Event.php:34 `public $command;`; `->name()` sets `description`, so `$event->description === 'records:rebuild'` also works]. Existing precedent: `tests/Feature/Booking/BookingScheduleTest.php:5-13` and `tests/Feature/Inbound/SweepUndispatchedTest.php:119-127` read `app(Schedule::class)->events()` the same way, which also proves the schedule is already loaded in tests without an Artisan call.

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Carbon 2 `diffInMinutes` returned an absolute int | Carbon 3 returns a signed float | Carbon 3.x (installed ^3.8.4) | Subtract timestamps; never display a diff directly |
| `Schedule::timezone` for the whole app | per-event `->timezone()` is a static string/`DateTimeZone` | unchanged in 13.35.0 | A runtime zone needs a `when()` check, Pattern 1 |
| Laravel 13 `ScheduleListCommand` converts cron expressions to a display zone (`CronExpressionTimezoneConverter`) | `schedule:list` shows event cron text in the app zone | 13.x | Informational: our event's expression is `* * * * *`, so `schedule:list` shows it every minute; the 00:10 rule lives only in the `when()` closure. Name the event so a reader of `schedule:list` finds it, and put the rule in a comment |

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|---------------|
| A1 | 00:10 is the minute ("just after midnight"); 00:05 or 00:15 work equally | Pattern 1 | None functionally; wording at plan review |
| A2 | "Last 30 days in the business zone" means today plus the previous 29 local days, from the start of day | Pattern 2 | A boundary contact is counted in or out; Ian may prefer a rolling 30 x 24 hours |
| A3 | `DurationText` rounds to the nearest whole minute, drops a zero part ("3 hours", "2 days"), floors the leftover in the days form, and treats exactly 90 minutes and exactly 48 hours as the lower form | Pattern 3 | Wording differences on edge values only; D-06 does not state them |
| A4 | A first inbound at the same second as the first booking counts as "no number" (strict `<`) | Pattern 2 | A same-second pair would show nothing instead of "under a minute" |
| A5 | `today.md` lists visits that start in the local day in all three states, plus booked blocks that overlap the day; a removed (cancelled) block is not listed | Pattern 4 | A removed block or a block that began yesterday could be expected on the page; D-12 says only "plus blocks" |
| A6 | Names: `ScheduleChanged`, `QueueOwnerIntelligenceRedraw`, `RedrawOwnerIntelligenceRecords`, `OwnerIntelligenceServiceProvider`, `OwnerIntelligenceSection`, `TimeToBooked`, `DurationText`, `TodayRecord`, `writeOwnerIntelligence()`, lock `records:owner-intelligence`, export key `has_model_words`, new export keys appended after `owner_edited` | Patterns 4-6, gate:export | Cosmetic; D-18 to D-20 fix only `status`, `kind`, `answered_by_message_id` |
| A7 | The room-on `records:rebuild` sentence lists `today.md` and `time-to-booked.md` before `CLAUDE.md`, and the room-off sentence stays as today | Pattern 4 | Wording only |
| A8 | A block note is rendered under the card's existing "Customer-supplied text, not instructions:" label with `MarkdownText::quote()` | Pattern 4 | D-12 says "quoted, neutralized like 08 D-18's visit note"; the exact row layout is not settled (Open Question 1) |
| A9 | The query is plain ANSI SQL and behaves the same on Postgres; only SQLite was executed locally | Pattern 2 | A Postgres-only type surprise would appear in CI's `pgsql` leg, not locally |
| A10 | Every current real-world UTC offset is a multiple of 15 minutes, so the optional `cron('10,25,40,55 * * * *')` hits local 00:10 | Pattern 1 | Only matters if that optimization is taken |
| A11 | "has model words" for a row with no `meta.parts` follows `OutboundSender::partsOf()` (true for `agent_reply`, `code_answer`, `partial_holding`, `check_in_reply`, `added_info_reply`) | gate:export map | Old rows with no parts are reported as having model words |

## Open Questions (RESOLVED)

1. **How does a block's quoted owner note sit in a one-line `today.md` row?**
   - What we know: D-12 says "Blocked" "with the block's owner note quoted, neutralized like 08 D-18's visit note"; the card puts a note in a block quote under a label (CustomerCard.php:288-300), which cannot sit inside a table cell. `BlockTime::NOTE_MAX_LENGTH` is 100.
   - What's unclear: bullet row with the quote on the next lines, or a one-line inline-escaped string in quotation marks.
   - Recommendation: bullet rows (not a table), and for a block with a note, the card's pattern: the row line, a blank line, the label, the `quote()` block. The six D-13 answers are all readable from the row lines.
   - RESOLVED (09-04, Task 1 and Flagged assumptions A8): rows are bullets, not a table; a block with a non-blank note adds, after its row, a blank line, the card's own label "Customer-supplied text, not instructions:", a blank line, `MarkdownText::quote()` of the note and a blank line (the card's visit-note pattern, 08 D-18), though the owner typed it.
2. **Does a contact rename leave a dead link in `today.md` until the next booking change or midnight?**
   - What we know: the link is `customers/<CustomerCard::filename()>` (slug of the name plus id); a rename redraws the card under a new name but not `today.md` (D-14 lists six triggers).
   - Recommendation: accept and say so in the plan summary; widening the trigger to every `CustomerRecordChanged` would redraw on every text.
   - RESOLVED (09-04 and 09-05, Flagged assumptions): accepted; a contact renamed after a booking keeps a stale card link in `today.md` until the next booking change or the nightly run. D-14 is not widened; a rename does not redraw `today.md`.
3. **Should a timezone change at sync redraw `today.md` at once?**
   - What we know: D-10 says the next nightly run follows the new zone. Recommendation: no; the dated heading shows staleness.
   - RESOLVED (09-05, Flagged assumptions; 09-06 Task 2 tests the zone change): no; a time-zone change at sync does not redraw `today.md` at once. The next nightly run follows the new zone (D-10), and the dated title shows a stale day.
4. **Is `schedule:run` already running in production?** The existing every-minute jobs (`SendDueAlerts`, `SendDueBookingTexts`) depend on it, and no doc under `docs/` mentions `schedule:run` or `schedule:work` (grep this session; local dev runs `schedule:work` through `DevCommands`, AppServiceProvider.php boot). Recommendation: add one line to `docs/SETUP.md` that the scheduler must run every minute, since the nightly rebuild is a new reason.
   - RESOLVED (09-06, Task 2 and Flagged assumptions): `docs/SETUP.md` section 12 gains a bullet that cron runs `php artisan schedule:run` every minute in production (locally `composer run dev` runs `schedule:work`), naming the jobs that depend on it, the nightly rebuild among them. Whether it runs in production is confirmed at first deploy (Phase 12) through 09-06's end-of-phase human-check, recorded in `.planning/WINDOWS.md`, never a pause.

## Environment Availability

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| PHP | everything | yes | 8.5.12 (`php --version`) | none needed |
| Composer packages (laravel 13.35.0, filament 5.10.1, pest 5.3.1) | everything | yes | see Standard Stack | none needed |
| Node and Playwright Chromium | the axe browser tests (`visit('/admin')`) | yes | node v24.12.0; `~/Library/Caches/ms-playwright` holds `chromium-1223`, `chromium_headless_shell-1243` and older | CI installs it (`npx playwright install chromium`) |
| SQLite | default test database | yes | built in (`DB_CONNECTION=sqlite`, `:memory:`) | none needed |
| PostgreSQL | CI's second leg | **no** locally (`pg_isready` and `psql` not found) | CI uses `postgres:17` | The Postgres leg is proven in CI on push; keep every query driver-neutral |
| `pint` | formatting | yes | `vendor/bin/pint` | none needed |

**Missing dependencies with no fallback:** none. **With fallback:** local Postgres (CI covers it; record "pgsql leg to confirm in CI" in the summary, as earlier phases did).

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Pest 5.3.1 on PHPUnit; `tests/Pest.php` binds `TestCase` + `RefreshDatabase` to `Feature`; `Unit` is plain |
| Config file | `phpunit.xml`, `tests/Pest.php` |
| Quick run command | `php artisan test --compact tests/Unit/OwnerIntelligence tests/Feature/OwnerIntelligence tests/Feature/Records tests/Feature/FrontDesk/GateExportTest.php` |
| Full suite command | `php artisan test --compact` (also what `.planning/config.json` `test_command` runs, as `php artisan test`) |
| Baseline | Before any edit: `php artisan test --compact tests/Feature/FrontDesk/GateExportTest.php tests/Feature/Records` passed 56 tests, 269 assertions (run this session) |

### Phase Requirements to Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| GATE-05 | `final_text` null unless sent or delivered; `status` on every line; failed and queued not counted as out | feature | `php artisan test --compact tests/Feature/FrontDesk/GateExportTest.php` | exists, extend |
| GATE-05 | `original_text` is `gate.inputs.agent_reply` on every path; null for blank or absent | feature | same | exists, extend |
| GATE-05 | six kinds in; holding, handoff, life-safety, booking out; `kind` and `has_model_words` on lines | feature | same | exists, extend |
| GATE-05 | `answered` draft keeps `resolution`, gains `answered_by_message_id`, null `final_text`; answering `code_answer` line present | feature | same | exists, extend |
| GATE-05 | `--without-text` carries no text in new fields; lengths null where text is null | feature | same | exists, extend |
| BOOK-06 | D-06 formatter boundaries and singular forms | unit | `php artisan test --compact tests/Unit/OwnerIntelligence/DurationTextTest.php` | Wave 0 |
| BOOK-06 | D-01 keyword skipped (SMS only, opted-out walk), web first message counts; D-02 earliest `booked_at` across projects, cancel keeps it, page and hand bookings; D-03 none or non-positive gives no number; hand-added contact excluded | feature | `php artisan test --compact tests/Feature/OwnerIntelligence/TimeToBookedTest.php` | Wave 0 |
| BOOK-06 | D-07 median odd and even, 30-day window in the business zone (boundary day), count, empty line wording | feature | same | Wave 0 |
| BOOK-06 | D-04 query count equal for 3 and 10 contacts and equal to 1 | feature | same | Wave 0 |
| BOOK-06 | contact screen line shown with a number, hidden with none, hidden with room off, hidden and reported when the query throws | feature (Livewire) | `php artisan test --compact tests/Feature/Filament/ContactTimeToBookedTest.php` | Wave 0 |
| RECS-02 | D-13 day: order, states, six reader answers, heading date, zone line, no phone/note/address, empty day, schedule room off line | feature | `php artisan test --compact tests/Feature/OwnerIntelligence/TodayRecordTest.php` | Wave 0 |
| RECS-02 | injection: a block note and a customer name with `#`, `|`, a newline and an instruction-like sentence stay inside their row or quote | feature | same | Wave 0 |
| RECS-02 | each of the six actions redraws `today.md`; a no-op (second cancel, same note) does not dispatch; block and remove redraw; room-off delete on sync and on rebuild; lock held means a timeout and no read before the lock | feature | `php artisan test --compact tests/Feature/OwnerIntelligence/RedrawTest.php` | Wave 0 |
| RECS-02 | nightly event: flags, `when()` true at 00:10 in the zone and false at 00:09 or in another zone's 00:10, follows a changed zone after a sync, one pass over the 25-hour and 23-hour days; `records:rebuild` turns `today.md` over, writes or deletes the room files, new sentence, missing folder reported | feature | `php artisan test --compact tests/Feature/OwnerIntelligence/NightlyRebuildTest.php tests/Feature/Records/RecordsRebuildTest.php` | Wave 0 / exists, extend |
| RECS-02 | router text room on and off; tracked example equals room-on text | feature | `php artisan test --compact tests/Feature/Records/RecordsRebuildTest.php` | exists, edit :45-51 |
| ROOM-13 | boot, contract against SPEC, folder holds only `CONTEXT.md`, `CONTEXT.md` says it writes `today.md` and `time-to-booked.md` with a sample of each, provider listed, optional (left out is valid), section position between money and schedule, summary and empty lines | feature | `php artisan test --compact tests/Feature/Rooms/OwnerIntelligenceBootTest.php` | Wave 0 |
| ROOM-13 / A11Y-01 | axe finds no violation in the section with and without a qualifying contact (real Chromium) | browser | same file | Wave 0 |
| D-26 | owner notice and stored reason read "the line changed after it was shown"; third case, the line's words changed | feature | `php artisan test --compact tests/Feature/Booking/ApprovedDraftVisitLineTest.php` | exists, edit :270, :285, :289 |
| Shared-code safety | arch scan still lists only the six writers; File facade only in `RecordsWriter`; example count 29 everywhere | unit/feature | `php artisan test --compact tests/Unit tests/Feature/Icm tests/Feature/Rooms` | exists |

### Sampling Rate
- **Per task commit:** the single test file for that task, `php artisan test --compact <file>` (Phase 8's proven verify form).
- **Per wave merge:** the quick run command above plus `tests/Feature/Booking tests/Feature/Rooms tests/Feature/Filament tests/Unit`.
- **Phase gate:** `php artisan test --compact` green (SQLite locally; the Postgres leg in CI after the push, which Ian does) before `/gsd-verify-work`.

### Wave 0 Gaps
- [ ] `tests/Unit/OwnerIntelligence/DurationTextTest.php` (BOOK-06 formatter)
- [ ] `tests/Feature/OwnerIntelligence/TimeToBookedTest.php` (BOOK-06, D-04 query count)
- [ ] `tests/Feature/OwnerIntelligence/TodayRecordTest.php` (RECS-02, D-13)
- [ ] `tests/Feature/OwnerIntelligence/RedrawTest.php` (RECS-02, D-14, D-15)
- [ ] `tests/Feature/OwnerIntelligence/NightlyRebuildTest.php` (RECS-02, D-10)
- [ ] `tests/Feature/Rooms/OwnerIntelligenceBootTest.php` (ROOM-13, A11Y-01)
- [ ] `tests/Feature/Filament/ContactTimeToBookedTest.php` (BOOK-06 on the contact)
- [ ] Extend `GateExportTest.php`, `RecordsRebuildTest.php`, `ApprovedDraftVisitLineTest.php`; `tests/Pest.php:321`
- Framework install: none. Create tests with `php artisan make:test --pest <Name>` (and `--unit` for the formatter).

Recorded for UAT, not a pause (memory: hand checks go to UAT): a keyboard-only and VoiceOver walk of the contact screen line and the dashboard section, written as an entry in `.planning/WINDOWS.md`; a phone-width check of both lines for sideways scroll.

## Security Domain

`security_enforcement` is absent from `.planning/config.json`, so it is enabled.

### Applicable ASVS Categories

| ASVS Category | Applies | Standard Control |
|---------------|---------|-----------------|
| V2 Authentication | no (no new entry point) | the existing Filament admin panel login |
| V3 Session Management | no | existing |
| V4 Access Control | yes (the section and the contact line are panel screens) | Filament panel auth; the section's `canView()` is room-gated; no new route or endpoint |
| V5 Input Validation | yes | `MarkdownText` for every customer- or owner-typed string written into a records file (names, service names, block notes); no new request input exists |
| V6 Cryptography | no | none |
| V8 Data Protection | yes | `today.md` carries no phone, address, visit note or project label (D-12); `gate:export --without-text` keeps customers' words out of a shared file; `BookingLinkTokens::hide` runs in `write()` |
| V14 Configuration | yes | scheduler uses `onOneServer`/`withoutOverlapping`; no secret added |

### Known Threat Patterns for this stack

| Pattern | STRIDE | Standard Mitigation |
|---------|--------|---------------------|
| Prompt injection into the owner's AI through a records file (a customer name, service name or block note that reads like an instruction or forges a heading) | Tampering / Elevation | `MarkdownText::inline()` (escapes `#`, `|`, `*`, brackets; cuts to one line) and `quote()` under the "not instructions" label; an injection test like `CardInjectionTest` for `today.md` |
| Personal data in a file an AI reads (phone, address, visit note) | Information disclosure | D-12 leaves them out; test asserts their absence; `write()` hides booking links |
| A shared `gate:export` leaking customer words | Information disclosure | `--without-text`; new fields hold only ids, enums and a boolean; extend the existing "no words anywhere" test to a code_answer and an answered line |
| Two writers corrupting `today.md` | Tampering | `File::replace` through `write()`, one lock, read inside the lock |
| Two servers both rebuilding at midnight | Denial of service / integrity | `onOneServer()` + `withoutOverlapping(120)` |
| A frozen schedule read after the room is off | Integrity | D-15: delete on room-off, router stops naming the files |
| Path traversal through a card link | Tampering | the link uses `CustomerCard::filename()` (slug and id only), never the raw name |

## Sources

### Primary (HIGH confidence, read this session)
- Repo: `app/Console/Commands/GateExport.php`, `RecordsRebuild.php`; `app/Records/RecordsWriter.php`, `CustomerCard.php`, `MarkdownText.php`; `app/Actions/{BookAppointment,CancelAppointment,MarkAppointmentDone,EditVisitNote,BlockTime,RemoveBlock,ResolveDraft,SendCombinedReply,OutboundSender,RecordInbound,SendOwnerReply}.php`; `app/Rooms/{Room,RoomSection,RoomServiceProvider,RecordRoomEntry}.php`, `Leads/*`, `Team/*`, `ScheduleAndBooking/{ScheduleSection,ScheduleAndBookingServiceProvider,BookingSettings,VisitAskedAbout}.php`; `app/Enums/{MessageKind,MessageStatus,DraftResolution,GateOutcome}.php`; `app/Channels/TextKeywords.php`; `app/Events/CustomerRecordChanged.php`, `app/Listeners/QueueCustomerRecordRender.php`, `app/Jobs/RenderCustomerCard.php`; `app/Icm/{ActiveConfig,FolderSync,BusinessClock}.php`; `app/Filament/Resources/Contacts/Schemas/ContactInfolist.php`; `app/Models/{Appointment,ScheduleBlock,Message,Contact}.php`; `app/SendRule/{ReplyParts,DecideSendOrDraft,Verdict}.php`; `routes/console.php`, `bootstrap/providers.php`, `phpunit.xml`, `config/{cache,database,queue,app}.php`, `.github/workflows/tests.yml`; `tests/Pest.php`, `tests/Unit/{ArchitectureTest,AppointmentWritesTest}.php`, `tests/Feature/{FrontDesk/GateExportTest,Records/*,Rooms/{LeadsBootTest,TeamBootTest,RoomContractTest,RoomAccessibilityTest},Booking/ApprovedDraftVisitLineTest,Booking/BookingScheduleTest,Inbound/SweepUndispatchedTest,Filament/ContactByHandTest}.php`; `docs/SPEC.md`, `docs/BLUEPRINT.md`, `docs/sprints/sprint-1.1.md`; the 09 CONTEXT and UI-SPEC.
- `vendor/laravel/framework/src/Illuminate/Console/Scheduling/{Event,ManagesFrequencies,ManagesAttributes,ScheduleRunCommand,CacheSchedulingMutex}.php` and `Foundation/Console/Kernel.php` (Laravel v13.35.0, `composer show`).
- Executed this session: `php artisan schedule:list`; a PHP DST scan over every time zone identifier; a Carbon 3 diff and `addDay()` check; an in-memory SQLite prototype of the time-to-booked query; `php artisan tinker` printing the compiled SQL (kind scope inside the sub-select); the baseline `php artisan test` run (56 passed).

### Secondary (MEDIUM)
- None used. No web search was needed: every question was answerable from this repo and its vendor source.

### Tertiary (LOW)
- None.

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH (no change; versions read from `composer show`)
- Architecture: HIGH for the scheduler, query, export and room (read from source and executed); MEDIUM for the redraw hook's exact insertion points inside the six actions (described by method, not edited)
- Pitfalls: HIGH (each tied to a file and line read this session)

**Research date:** 2026-11-14
**Valid until:** 2026-12-14 (stable; revisit if `laravel/framework` is bumped past 13.35.x, since the scheduler internals cited are version-specific)
