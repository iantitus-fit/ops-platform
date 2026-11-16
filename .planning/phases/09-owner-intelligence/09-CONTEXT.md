# Phase 9: Owner Intelligence - Context

**Gathered:** 2026-11-14
**Status:** Ready for planning

<domain>
## Phase Boundary

The owner and the owner's own AI can read how fast the business finishes a conversation and what is on today, and `gate:export` reports only what went out. Four things:

1. **Time to booked (BOOK-06).** The minutes from a contact's first inbound message to their first booking, worked out from the records, shown on the contact screen and as one summary line in the owner intelligence room's dashboard section and in a new `records/time-to-booked.md`.
2. **Today's schedule and the nightly rebuild (RECS-02).** `records/today.md` is redrawn on every booking or block change, and a platform nightly rebuild of `records/` runs just after midnight in the business's time zone.
3. **`gate:export` (GATE-05).** It counts only what reached the customer, gives `original_text` one meaning on every path, and exports the `answered` resolution and the 02.1 fixed-reply kinds (02 WR-09, 02.1 D-02).
4. **The owner intelligence room (ROOM-13).** A room at the standard: folder, `CONTEXT.md` that says it writes `today.md` and `time-to-booked.md`, module, dashboard section, boot test. It is optional.

Also carried in: 08 IN-13, the "the line changed after it was shown" wording (D-26).

Requirements: ROOM-13, RECS-02, BOOK-06, GATE-05.

Not in this phase: a time-to-booked line on the customer card (the card is unchanged, D-05), a breakdown by lead source, business-hours minutes, a redraw when a visit starts (D-11), a business-wide project view or projects records file (Deferred), `who-owes-us.md` and `this-week.md` (backlog 999.1), the MCP server for the owner's AI (backlog 999.4), handoff and life-safety texts in the export (Deferred), a briefing.

</domain>

<decisions>
## Implementation Decisions

### Citation key

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

### Locked, carried forward (do not reopen)
- DEC-01 to DEC-14 (PROJECT.md); 02 D-01 to D-56 (02-CONTEXT.md); 02.1 D-01 to D-51 (02.1-CONTEXT.md); 04 D-01 to D-27 (04-CONTEXT.md); 05 D-01 to D-33 (05-CONTEXT.md); 06 D-01 to D-21 (06-CONTEXT.md); 07 D-01 to D-23, D-21 and D-23 as amended (07-CONTEXT.md); 08 D-01 to D-46, D-39 as amended 2026-11-11 (08-CONTEXT.md).
- The alert-timing and one-alert rules, and 02.1 IN-11 ("never a missing alert for a duplicate"). Phase 9 raises no alert.
- PROJECT.md Key Decisions of 2026-10-19: "The project is the spine", "The model home is the next milestone", "One owner per fact"; and the room standard and v2.0 floor plan of 2026-10-21 ("a room gets a connector slot only when it talks to an outside system").
- WCAG 2.2 Level AA, the chokepoint rule and the 2026-11-11 visual rule (CLAUDE.md, Rules).
- Carried facts this phase builds on: records follow the database and are written whole through `RecordsWriter::write()` (DEC-03); the front desk loads the card rendered fresh from the database (02 D-36); existing code stays in `app/Records/` and the room points at it (04 D-19); `open-leads.md` and its tests are unchanged (07 D-17, 08 D-41); only the six named actions write appointments and blocks (08 D-15); `MessageStatus::onTheCustomersPhone()` is the one definition of what reached the customer.

Phase 9 adds no agent action: every new write (records files, the export's output) is code rendering from the database, so the chokepoint rule names no new chokepoint (06 D-09's reasoning).

### Time to booked (BOOK-06)
- **D-01:** The clock starts at the contact's earliest inbound `messages` row, a text or a web form (both go through `RecordInbound`), as BOOK-06 says ("first inbound message"). An inbound that is only a keyword the platform handles (STOP, START, HELP and the rest of the list in `app/Channels/TextKeywords.php`) never starts the clock; the next real message does, the same way a first STOP opens no project (07 D-23, 07-CONTEXT.md). A contact with no such inbound (one the owner added by hand, 08 D-19) has no start. Affects: owner intelligence, customer communication, sales.
- **D-02:** The clock stops at the contact's first booking ever, by when it was booked: the earliest `booked_at` across the contact's appointments, whichever project it is on and whatever the visit date. A page booking and a booking by hand both count. A later cancel does not erase it. A returning customer's later jobs add no new number. One number per contact. Affects: owner intelligence, operations, sales.
- **D-03:** Wall-clock elapsed time, whatever the opening hours. When there is no qualifying inbound before the earliest `booked_at` (a hand-added contact booked before they ever wrote), the contact has no number: nothing shows and the contact is left out of the summary, never shown as zero or negative. Affects: owner intelligence.
- **D-04:** Worked out on read from `messages` and `appointments` wherever it is shown; nothing is stored. No new column, no new writer beside 08 D-15's named actions, nothing to backfill for Phase 8's bookings. BOOK-06's "recorded" is met by the two recorded facts. The dashboard summary and `time-to-booked.md` compute in one query for all contacts, never one query per contact, and a test counts the queries so that stays true. Affects: owner intelligence, connectors and data.
- **D-05:** Each contact's number shows on the contact screen only, labeled "Time to booked". The customer card is unchanged, so what the front desk's model reads does not change in this phase (02 D-36 untouched) and no card test changes. The owner's AI reads the business-wide number from `records/time-to-booked.md` (D-23). Affects: owner intelligence, customer communication.
- **D-06:** Format, used on the contact screen, in the section and in `time-to-booked.md`: minutes up to 90 ("42 minutes"); above 90, hours and minutes ("3 hours 10 minutes"); past 48 hours, days and hours ("2 days 4 hours"). Singular forms ("1 minute", "1 hour", "1 day"); "under a minute" when less than a minute passed. One formatter, shared by every place. Affects: owner intelligence.
- **D-07:** The dashboard summary is one line in the owner intelligence room's section: the median time to booked for contacts whose first booking (D-02) fell in the last 30 days, with how many contacts that is. The 30 days are counted in the business's time zone (`business/profile.md`). The median of an even count is the mean of the two middle values, rounded to the whole minute. The median uses the D-06 format. With no such contact the section shows "No new customers booked in the last 30 days." Affects: owner intelligence.
- **D-08:** Everything in this group follows the room (04 D-08, 04-CONTEXT.md): with the owner intelligence room off, the section hides and the contact screen shows no Time to booked line. Nothing is lost, because the number is worked out on read (D-04). Affects: owner intelligence.

### Today's schedule and the nightly rebuild (RECS-02)
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

### gate:export (GATE-05)
- **D-16:** `final_text` is the body only when the status is sent or delivered (`MessageStatus::onTheCustomersPhone()`); otherwise null. Every line also carries the message's status, so a failed decision stays in the labeled data and a queued one reads as not yet out, never as sent (02 WR-09, 02-REVIEW-DISPOSITION.md). Affects: safety and compliance, customer communication, owner intelligence.
- **D-17:** `original_text` is always `gate.inputs.agent_reply`: the model's own words before the disclosure, the sign-off, the link line, the visit line or any owner edit, the same words the send rule judged. Null for a text with no model words, with no fallback to `body`. `final_text` stays the full text that went out. Affects: safety and compliance, owner intelligence. — **Reversibility:** costly — the export is the labeled dataset the thresholds are tuned from (02 D-07, 02.1 D-02/D-21); a later reader compares columns across exports made before and after.
- **D-18:** The export covers `agent_reply` plus 02.1's six kinds: `code_answer`, `partial_holding`, `complaint_holding`, `discount_holding`, `check_in_reply`, `added_info_reply` (02.1 D-02). Holding, handoff and life-safety texts stay out, and the command's docblock sentence stays: one line is one decision, and a holding reply follows a held draft whose line already carries that verdict. Booking texts stay out. Each line gains `kind` and whether model words were in (from `meta.parts`). If Phase 10's calibration needs the handoff or life-safety cases, it adds them there (Deferred). Affects: safety and compliance, owner intelligence.
- **D-19:** A draft closed as `answered` (02.1 D-21) keeps `resolution: answered` and gains `answered_by_message_id` from `meta.answered_by`, naming the `code_answer` line's `message_id`. Its `final_text` stays null because it never went out; the sentence's own line has the text. Affects: safety and compliance, owner intelligence.
- **D-20:** `--without-text` turns every new text into its length, as today. New fields (`status`, `kind`, the model-words flag, `answered_by_message_id`) are added and none is removed. `gate:export` stays a platform command in `app/Console/Commands/` (04 D-19). Tests come first, since this is the labeled data. Affects: safety and compliance.

### The owner intelligence room (ROOM-13)
- **D-21:** Key and folder `owner_intelligence` (`rooms/owner_intelligence/`, `app/Rooms/OwnerIntelligence/`), registered in `bootstrap/providers.php`. Its contract: `owns: none`, tables none (it reads `messages`, `appointments` and blocks, 06 D-04), `reads` as folder paths only (`business/profile.md` for the time zone), `writes_records: [records/today.md, records/time-to-booked.md]`, `connector: none`. It is optional and stays off the never-left-out list (D-09). Affects: owner intelligence, knowledge.
- **D-22:** The folder holds only `CONTEXT.md`, like leads and team (07 D-18). Criterion 4's "example file" is met the way those rooms met it: the room owns no file and an empty room is valid (ROOM-01). No example records are committed: generated records are never committed (01 D-23, the `.gitignore` rule `/examples/*/records/*`), and un-ignoring them would reopen that. The room's `CONTEXT.md` body shows a short sample of each file it writes (the D-13 day and the time-to-booked line); the acceptance tests render the real files. `exampleFolderFileCount()` changes only for the new `CONTEXT.md` (04 D-16). Affects: owner intelligence, knowledge.
- **D-23:** `records/time-to-booked.md` holds a title, the read-only note and the D-07 summary line (median, count, last 30 days, business zone), or its empty line. `this-week.md` stays with backlog 999.1, untouched. `docs/SPEC.md`'s bridge list gains the file during execution (as 04 D-14 did for its table); `docs/BLUEPRINT.md` section 7 gains it in this context commit. Affects: owner intelligence, knowledge.
- **D-24:** The room's dashboard section holds the time-to-booked line only (D-07). Today's visits already show in the schedule room's section (08 D-40) and are not repeated. The room owns no file, so it is never shown as empty (04 D-21). The section gets the axe check in the room's boot test (04 D-27). Affects: owner intelligence, safety and compliance.
- **D-25:** `records/CLAUDE.md` names `today.md` ("What is on today: read today.md") and `time-to-booked.md` only while the room is live. The tracked `examples/example-hvac/records/CLAUDE.md` matches the room-on text, and the existing test keeps the two equal. Affects: owner intelligence, knowledge.

### Carried in from Phases 7 and 8
- **D-26 (08 IN-13, 08-REVIEW-DISPOSITION.md, as Ian deferred it on 2026-11-12):** `VisitAskedAbout::VISIT_CHANGED` becomes "the line changed after it was shown", which is true in every case (the visit moved, another visit became the one asked about, or the line's own words changed). The owner's notice then reads "Draft sent without the visit line: the line changed after it was shown". Tests first. The forced test changes are the three assertions in `tests/Feature/Booking/ApprovedDraftVisitLineTest.php` at lines 270, 285 and 289. `docs/BLUEPRINT.md` line 133 (section 1) changes in the same commit as the code. Stored `meta.visit_line_left_off` on drafts already sent keeps the old words; history is not rewritten. Affects: customer communication, operations, team.
- **D-27:** 07's "[deferred] Phase 9" line (a business-wide project view that does not depend on the leads room, and a business-wide projects records file) is not built here. It is tagged [deferred] (Deferred). Projects keep reaching the owner's AI through each card (07 D-17). Affects: owner intelligence, sales.

### Scope and the UI contract
- **D-28:** BOOK-06 and RECS-02's scope is confirmed as above (the ROADMAP planning note). Both stay in v2.0 coverage. Affects: owner intelligence, operations.
- **D-29:** A short stock-Filament UI-SPEC records the contract, the 07 D-21 and 07-UI-SPEC.md pattern: one contact-screen line and one room-section line, no design work. The 2026-11-11 visual rule holds: a finding only about looks goes to `.planning/todos/pending/2026-10-17-phone-design-pass.md`. Wording, order, keyboard use, focus, screen-reader names, contrast and the axe check gate the phase; the keyboard and VoiceOver walk is recorded for UAT. Affects: safety and compliance, owner intelligence.

### Claude's Discretion
- Names in the house style: the formatter, the query or class that works out time to booked, the redraw job or listener, the room's provider and section classes.
- The summary line's exact words, in the PlainWords style with no exclamation points or em dashes, settled by Ian at plan review (2026-11-16): two or more customers, "Half of your 12 new customers in the last 30 days booked within 42 minutes."; one customer, "Your 1 new customer in the last 30 days booked within 42 minutes." When the time is "under a minute", the sentence says "in under a minute" instead of "within under a minute": "Half of your 2 new customers in the last 30 days booked in under a minute." and "Your 1 new customer in the last 30 days booked in under a minute." The time uses DurationText (D-06), the empty line stays "No new customers booked in the last 30 days.", and records/time-to-booked.md carries the same sentence.
- How the D-14 redraw is wired (a new event, or the existing `CustomerRecordChanged` path plus a block hook), as long as it runs after commit, is queued and is locked like `writeSummaries()`.
- The exact minute past midnight for D-10, and how "midnight in the business zone" is expressed to Laravel's scheduler across daylight saving changes.
- The room's `CONTEXT.md` body wording, within D-21 and D-22.
- The test layout: the D-13 rendering tests, the query-count test (D-04), the export tests (D-16 to D-20) and the room's boot test.

</decisions>

<planning_notes>
## Planning notes
- Tests first for the export (D-16 to D-20), today's rendering (D-13) and the D-04 query count (CLAUDE.md, Rules).
- A plan that changes shared code (`RecordsWriter`, `GateExport`, `VisitAskedAbout`, the example folder) first sweeps for existing tests the change would hollow out, and names every forced test change by file and line, as 07 and 08 did. The known ones are 08's three IN-13 assertions (D-26) and `exampleFolderFileCount()` (D-22).
- The router text changes (D-25): the router equality test and the tracked `examples/example-hvac/records/CLAUDE.md` change together.
- No new package without Ian's approval (04 D-18).
- If the planner recommends splitting the phase, stop and bring it to Ian before any plan is written.
- First-time Laravel concepts expected: scheduling a command in a time zone, and a database query that works out a median for every contact at once. Each gets a plain explanation and a line in the walk-through log (CLAUDE.md, Rules).
</planning_notes>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Phase scope and requirements
- `.planning/ROADMAP.md` § "Phase 9: Owner Intelligence": goal, four success criteria, planning notes
- `.planning/REQUIREMENTS.md`: ROOM-13, RECS-02, BOOK-06, GATE-05
- `.planning/PROJECT.md` § Key Decisions: DEC-03; "The project is the spine", "The model home is the next milestone", "One owner per fact" (2026-10-19); the room standard and v2.0 floor plan (2026-10-21)
- `CLAUDE.md` § Rules: the chokepoint rule, WCAG 2.2 AA, tests first, the visual rule, the BLUEPRINT "Affects:" rule
- `.planning/seeds/SEED-261020-v30-phase-3-booking-and-the-gate.md`: where `records/today.md` and the nightly rebuild came from
- `docs/SPEC.md` § "The bridge": the records files (gains `time-to-booked.md`, D-23)
- `docs/BLUEPRINT.md` section 7 (Owner intelligence), section 4 (Operations), section 1 line 133 (D-26), and the room standard bullets; updated in this context commit

### Prior phase decisions (locked)
- `.planning/milestones/v1.0-phases/01-foundation/01-CONTEXT.md`: D-23 (records never committed, D-22)
- `.planning/milestones/v1.0-phases/02-text-front-desk/02-CONTEXT.md`: D-07 (the verdict record), D-36 (card loaded fresh)
- `.planning/milestones/v1.0-phases/02-text-front-desk/02-REVIEW-DISPOSITION.md`: WR-09; its full text is in git commit a09bffd (`.planning/phases/02-text-front-desk/02-REVIEW.md`, "WR-A09")
- `.planning/milestones/v1.0-phases/02.1-faster-answers/02.1-CONTEXT.md`: D-02 and its deferred line (export `answered` and the fixed-reply kinds), D-03 (the band waits on export data), D-21 (`answered`), D-30 (the fixed-reply kinds)
- `.planning/phases/04-room-standard/04-CONTEXT.md`: D-02 (the contract and `writes_records`), D-05, D-08, D-09, D-11, D-12, D-14, D-16, D-19, D-21, D-27
- `.planning/phases/05-standard-rooms/05-CONTEXT.md`: D-05 (`writes_records: none`, `connector: none` pattern)
- `.planning/phases/06-front-desk-and-team-rooms/06-CONTEXT.md`: D-04 (tables), D-09 (code is not an agent action)
- `.planning/phases/07-the-spine-and-leads/07-CONTEXT.md`: D-13, D-17, D-18, D-21, D-23, Deferred (the Phase 9 line)
- `.planning/phases/07-the-spine-and-leads/07-UI-SPEC.md`: the stock-Filament UI-SPEC shape (D-29)
- `.planning/phases/08-schedule-and-booking/08-CONTEXT.md`: D-14, D-15, D-18, D-19, D-22, D-23, D-35, D-38, D-40, D-41, Deferred (the Phase 9 line)
- `.planning/phases/08-schedule-and-booking/08-REVIEW-DISPOSITION.md`: IN-06 (D-11), IN-13 (D-26)

### Code this phase changes or builds beside
- `app/Records/RecordsWriter.php` (`write()` :183, `writeSummaries()` :85, `routerMarkdown()` :114, `rebuild()` :137): D-10, D-12, D-14, D-15, D-23, D-25
- `app/Records/CustomerCard.php`: unchanged (D-05); its `visitTime()` and zone naming are the pattern for D-12
- `app/Console/Commands/RecordsRebuild.php`, `routes/console.php`: D-10
- `app/Console/Commands/GateExport.php` (:51 the kind filter, :89 `final_text`, :90 `original_text`, :130 `withoutText()`): D-16 to D-20
- `app/Enums/MessageKind.php` (`assembledByCode()`), `app/Enums/MessageStatus.php` (`onTheCustomersPhone()`), `app/Enums/DraftResolution.php`, `app/Actions/ResolveDraft.php:130` (`closeAsAnswered()`, `meta.answered_by`), `app/Actions/SendCombinedReply.php`: D-16 to D-19
- `app/Channels/TextKeywords.php`, `app/Actions/RecordInbound.php`: D-01
- `app/Actions/BookAppointment.php`, `CancelAppointment.php`, `MarkAppointmentDone.php`, `EditVisitNote.php`, `BlockTime.php`, `RemoveBlock.php`, `app/Rooms/RecordRoomEntry.php`, `app/Listeners/QueueCustomerRecordRender.php`, `app/Jobs/RenderCustomerCard.php`: D-14
- `app/Rooms/ScheduleAndBooking/VisitAskedAbout.php:31`, `tests/Feature/Booking/ApprovedDraftVisitLineTest.php:270,285,289`: D-26
- `app/Rooms/` (`Room.php`, `RoomRegistry.php`, `RoomServiceProvider.php`, `RoomSection.php`), `app/Rooms/Leads/` as the nearest sibling, `examples/example-hvac/rooms/leads/CONTEXT.md`: D-21, D-22, D-24
- `app/Filament/Resources/Contacts/` (the contact screen): D-05
- `tests/Pest.php` (`exampleFolderFileCount()`), `tests/Feature/Rooms/*BootTest.php`: D-22, D-24
- `.gitignore` (`/examples/*/records/*`): D-22

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `RecordsWriter::write()` puts every records file in place whole and hides booking addresses; `summary()` builds a titled table with the read-only note and an empty line. `today.md` and `time-to-booked.md` reuse both.
- `writeSummaries()` takes the `records:summaries` lock and reads the database inside it, so the last writer wins with the newest state. The D-14 redraw follows the same rule.
- `records:rebuild` already rewrites the router, every card and both summaries; D-10 schedules it and D-15 adds the room's files.
- `CustomerCard` names the zone once (`BookingSettings::zoneName()`) and formats visit times; `today.md` uses the same zone and times.
- `ActiveConfig::displayTimezone()` and `hasRoom()` give the business zone and whether a room is live.
- `MessageStatus::onTheCustomersPhone()` is the one definition of what reached the customer (D-16).
- `TextKeywords` matches keywords the way Twilio does, in PHP, never in SQL (D-01).

### Established Patterns
- Rooms with no files: `rooms/leads/` and `rooms/team/` hold only `CONTEXT.md` with `owns: none` (D-22).
- Room sections are `RoomSection` classes with the axe check in the room's boot test (04 D-27).
- Scheduled jobs in `routes/console.php` use `withoutOverlapping()`; none runs in a business time zone yet (D-10).
- Records are skipped by the sync and never committed except `README.md` and `CLAUDE.md` (01 D-23).
- Writes to appointments and blocks only through the six named actions, under an architecture test (08 D-15).

### Integration Points
- `bootstrap/providers.php`: the owner intelligence room's provider joins the list.
- `examples/example-hvac/rooms/owner_intelligence/CONTEXT.md` joins the example folder, so `exampleFolderFileCount()` moves by one.
- `routes/console.php`: the nightly rebuild entry.
- The contact screen (`app/Filament/Resources/Contacts/`): the Time to booked line.
- `examples/example-hvac/records/CLAUDE.md`: the router text (D-25).

</code_context>

<specifics>
## Specific Ideas

- The D-13 day (Dana done at 9:00 AM, blocked 11:00 AM to 12:00 PM, Marcus booked at 1:00 PM, Lee cancelled at 3:30 PM) and its six reader questions are the acceptance examples for `today.md`.
- "Friday, November 13" in the heading so a reader can tell a stale file (D-12).
- "Time to booked", not "Minutes to booked", because the value is not always minutes (D-05, D-06).
- "No new customers booked in the last 30 days." (D-07) and "This business keeps its schedule outside this system." (D-15) are Ian's wording.

</specifics>

<deferred>
## Deferred Ideas

- **[deferred] When per-project reporting is decided (BLUEPRINT section 7; PROJECT.md, "The project is the spine"), most likely beside backlog 999.1's `this-week.md`:** a business-wide project view that does not depend on the leads room, and a business-wide projects records file (07-CONTEXT.md Deferred, D-27). Affects: owner intelligence, sales.
- **[deferred] Phase 10:** if the safety-bar calibration needs handoff or life-safety cases in `gate:export`, it adds them there (D-18). Affects: safety and compliance, owner intelligence.
- **[deferred] Backlog 999.4:** the owner intelligence room's connector slot, with the MCP server for the owner's AI (D-21). Affects: connectors and data, owner intelligence.
- **[deferred] Backlog 999.1:** `who-owes-us.md` and `this-week.md` (unchanged, D-23). Affects: finance, owner intelligence.

### Reviewed Todos (not folded)
- "Phone design pass for the dashboard, the Needs you card and the Business Folder page" (`.planning/todos/pending/2026-10-17-phone-design-pass.md`): matched on keywords (0.9); not folded, by Ian's instruction. It stays for Phase 11 and receives any look-only finding from this phase (D-29).

</deferred>

---

*Phase: 09-owner-intelligence*
*Context gathered: 2026-11-14*
