---
phase: 09-owner-intelligence
verified: 2026-11-18T09:00:00Z
status: human_needed
score: 4/4 roadmap success criteria verified (all plan must-haves verified; 0 gaps)
covered_files:
  - ".planning/phases/09-owner-intelligence/09-01-PLAN.md"
  - ".planning/phases/09-owner-intelligence/09-01-SUMMARY.md"
  - ".planning/phases/09-owner-intelligence/09-02-PLAN.md"
  - ".planning/phases/09-owner-intelligence/09-02-SUMMARY.md"
  - ".planning/phases/09-owner-intelligence/09-03-PLAN.md"
  - ".planning/phases/09-owner-intelligence/09-03-SUMMARY.md"
  - ".planning/phases/09-owner-intelligence/09-04-PLAN.md"
  - ".planning/phases/09-owner-intelligence/09-04-SUMMARY.md"
  - ".planning/phases/09-owner-intelligence/09-05-PLAN.md"
  - ".planning/phases/09-owner-intelligence/09-05-SUMMARY.md"
  - ".planning/phases/09-owner-intelligence/09-06-PLAN.md"
  - ".planning/phases/09-owner-intelligence/09-06-SUMMARY.md"
  - "app/Actions/BlockTime.php"
  - "app/Actions/BookAppointment.php"
  - "app/Actions/CancelAppointment.php"
  - "app/Actions/EditVisitNote.php"
  - "app/Actions/MarkAppointmentDone.php"
  - "app/Actions/RecordInbound.php"
  - "app/Actions/RemoveBlock.php"
  - "app/Console/Commands/GateExport.php"
  - "app/Events/ScheduleChanged.php"
  - "app/Filament/Resources/Contacts/Schemas/ContactInfolist.php"
  - "app/Icm/FolderSync.php"
  - "app/Jobs/RedrawOwnerIntelligenceRecords.php"
  - "app/Listeners/QueueOwnerIntelligenceRedraw.php"
  - "app/Records/RecordsWriter.php"
  - "app/Rooms/OwnerIntelligence/DurationText.php"
  - "app/Rooms/OwnerIntelligence/OwnerIntelligenceSection.php"
  - "app/Rooms/OwnerIntelligence/OwnerIntelligenceServiceProvider.php"
  - "app/Rooms/OwnerIntelligence/TimeToBooked.php"
  - "app/Rooms/OwnerIntelligence/TodayRecord.php"
  - "app/Rooms/ScheduleAndBooking/VisitAskedAbout.php"
  - "bootstrap/providers.php"
  - "examples/example-hvac/rooms/owner_intelligence/CONTEXT.md"
  - "routes/console.php"
covered_digest: "v3:sha256:64b1dd9e89ed8d871e62c238c1a9eaeb011d4888aaf7ac414a32d48c55330718"
behavior_unverified: 0
overrides_applied: 0
re_verification:
  previous_status: human_needed
  previous_score: 4/4
  gaps_closed: []
  gaps_remaining: []
  regressions: []
human_verification:
  - test: "Keyboard-only and VoiceOver walk of the contact screen's Time to booked line and the dashboard's Owner intelligence section; phone-width check for no sideways scroll"
    expected: "Label read with its value, section read under its heading, focus order on the contact screen unchanged, lines wrap at phone width (WINDOWS.md row 83)"
    why_human: "Screen reader and real-device behavior cannot be checked from code (CLAUDE.md WCAG rule; D-29). Never run by the verifier."
  - test: "At first deploy (Phase 12): cron runs php artisan schedule:run every minute and records/today.md names the new day after 00:10 business time (WINDOWS.md row 88)"
    expected: "Title date advances after 00:10 in the business zone; a late tick inside 00:10 to 01:00 still runs the rebuild once"
    why_human: "Needs a real scheduler and wall clock"
---

# Phase 9: Owner Intelligence Verification Report

**Phase Goal:** The owner and the owner's own AI can read how fast the business finishes a conversation and what is on today, and `gate:export` reports only what went out.
**Verified:** 2026-11-18
**Status:** human_needed
**Re-verification:** Yes. The 2026-11-17 report was stale because code-review fixes (commits 3a82ac4 to 4bb969f) landed after it. Every truth below was re-checked against the current code; the review-fix areas got the full three-level check.

## Goal Achievement

### Observable Truths (ROADMAP success criteria, the contract)

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | `records/today.md` is re-rendered from appointments on every booking change, and a nightly full rebuild of `records/` is scheduled | VERIFIED | `ScheduleChanged::dispatch()` is in BookAppointment (both the by-hand path, line 206, and the page path, line 266), CancelAppointment:88, MarkAppointmentDone:82, EditVisitNote:81, BlockTime:116, RemoveBlock:54 and `FolderSync.php:152` (room switch). `ScheduleChanged` is `ShouldDispatchAfterCommit`; `QueueOwnerIntelligenceRedraw` dispatches `RedrawOwnerIntelligenceRecords` (`ShouldBeUniqueUntilProcessing`, uniqueId 'owner-intelligence', WithoutOverlapping, backoff [5,30,120]); the job calls `RecordsWriter::writeOwnerIntelligence()`. `routes/console.php` schedules `records:rebuild` every minute: the `when()` closure reads `ActiveConfig::displayTimezone()` lazily, passes on any tick from 00:10 to before 01:00 business time when the per-date cache marker `records:rebuild:done:<date>` is absent, `onSuccess` sets the marker only after exit 0, plus `withoutOverlapping(30)` and `onOneServer()`; a throw in the filter is reported and returns false. Run by me: RedrawTest and NightlyRebuildTest pass (15 of 15 in the filtered re-run; 13 nightly tests, 15 redraw tests cover after-commit, no-op, unique, DST, late tick, failed-run retry, killed-run lock expiry). |
| 2 | Minutes from a contact's first message to their booked appointment are shown on the contact and in a dashboard summary | VERIFIED | `TimeToBooked` (one SELECT, PHP median), `DurationText::format`, `ContactInfolist::timeToBookedLine` (room-gated, throw reported and left out, null for no number) and `OwnerIntelligenceSection::contentLines()` -> `summaryLine()`. After WR-02, `RecordInbound::record()` stores `meta.keyword` (stop, start, help or null) on every inbound; `TimeToBooked::keywordOf()` trusts a present key (`array_key_exists`) and reconstructs only for older rows with no key. Data flow: messages JOIN conversations JOIN grouped appointments min(booked_at) -> seconds -> text. TimeToBookedTest, ContactTimeToBookedTest and OptOutTest (carrier opt-out then "YES" does not start the clock) pass. |
| 3 | `gate:export` counts only what went out, gives `original_text` one meaning on every path, and exports `answered` and the 02.1 fixed-reply kinds | VERIFIED | `GateExport::decision()`: `final_text` is the body only when `status` is in `MessageStatus::onTheCustomersPhone()` (Received, Sent, Delivered), else null; `original_text` is always `gate.inputs.agent_reply`, null if blank, no body fallback; the query covers `agent_reply` plus the six `MessageKind::assembledByCode()` kinds; `status`, `kind`, `has_model_words`, `answered_by_message_id` appended after `owner_edited`; `--without-text` swaps text for lengths. GateExportTest passes. `VisitAskedAbout::VISIT_CHANGED` present (D-26). |
| 4 | The owner intelligence room meets the standard (folder, `CONTEXT.md` that says it writes `today.md`, example file, module, boot test) | VERIFIED | `examples/example-hvac/rooms/owner_intelligence/CONTEXT.md` (contract `writes_records: [records/today.md, records/time-to-booked.md]`, owns none, connector none; its today.md sample now equals `TodayRecord` output, pinned by a TodayRecordTest case, IN-02); `OwnerIntelligenceServiceProvider` registered in `bootstrap/providers.php:28`; room has no file of its own by D-22 (the CONTEXT.md carries the samples). `OwnerIntelligenceBootTest` (tests/Feature/Rooms) passes. |

**Score:** 4/4 roadmap truths verified, 0 behavior-unverified. All behavior-dependent plan truths (after-commit dispatch, no-op and rollback queue nothing, unique redraw, once per date, killed-run retry, router follows room) are each exercised by a named passing test, so none rests on symbol presence.

### Review-fix truths (re-checked from scratch)

| Fix | Claim | Status | Evidence |
|-----|-------|--------|----------|
| WR-01 | `writeOwnerIntelligence()` rewrites `records/CLAUDE.md` in the same `records:owner-intelligence` lock, so the router follows the room at the next sync | VERIFIED | `RecordsWriter.php:122-129`: inside `Cache::lock('records:owner-intelligence', 30)->block(10, ...)` it calls `writeRouter()` first, then writes or deletes the two files by `hasRoom()`. `rebuild()` calls `writeOwnerIntelligence()` and no longer writes the router separately. RedrawTest "rewrites the router at once when a sync turns the room off, and again when it comes back" passes (test commit 3a82ac4 before fix 28b85b3). This relaxes 09-04's "touch no other records file" wording for that call, by Ian's ruling; the router is the only added file. |
| WR-02 | keyword decision stored and trusted | VERIFIED | See truth 2. Test-first (3263376 then b2a4fc6). Still one SELECT (D-04; TimeToBookedTest one-query counts pass). |
| WR-03 | rebuild once per business date, any tick 00:10 to before 01:00; supersedes 09-06's "only 00:10" | VERIFIED | `routes/console.php` as described in truth 1; the superseded 00:10-only wording is recorded as Ian's ruling in 09-REVIEW-DISPOSITION.md. Tests: 00:25 late tick runs, second tick same date does not, failed run retried, killed-run lock (30 min) expires inside the window, DST days once each, zone read lazily, bad read reported. Test-first (35128c9, 311191c then e27fd7f, e7dfba1). |
| IN-02 | room sample equals TodayRecord output | VERIFIED | TodayRecordTest renders the D-13 day and compares to the CONTEXT.md sample; passes. |

### Required Artifacts

| Artifact | Status | Details |
|----------|--------|---------|
| `app/Console/Commands/GateExport.php` | VERIFIED | Substantive; uses `onTheCustomersPhone()` and `assembledByCode()` |
| `app/Rooms/ScheduleAndBooking/VisitAskedAbout.php` | VERIFIED | `VISIT_CHANGED` present |
| `app/Rooms/OwnerIntelligence/{DurationText,TimeToBooked,TodayRecord,OwnerIntelligenceSection,OwnerIntelligenceServiceProvider}.php` | VERIFIED | Substantive and wired (section via Room descriptor, TimeToBooked via section, infolist and RecordsWriter) |
| `app/Events/ScheduleChanged.php`, `app/Listeners/QueueOwnerIntelligenceRedraw.php`, `app/Jobs/RedrawOwnerIntelligenceRecords.php` | VERIFIED | Wired end to end, six actions plus FolderSync fire the event |
| `app/Records/RecordsWriter.php` | VERIFIED | `writeOwnerIntelligence()` writes/deletes by room state and rewrites the router under the lock; `rebuild()` calls it |
| `app/Actions/RecordInbound.php` | VERIFIED | `meta.keyword` stored on every inbound |
| `routes/console.php` | VERIFIED | `records:rebuild` schedule with window, per-date marker, `withoutOverlapping(30)`, `onOneServer()` |
| `examples/example-hvac/rooms/owner_intelligence/CONTEXT.md` | VERIFIED | Contract and samples present, sample equals real output |
| `bootstrap/providers.php` | VERIFIED | Provider registered |
| `docs/SPEC.md`, `docs/SETUP.md` | VERIFIED | SPEC bridge list names `time-to-booked.md`; SETUP has the `schedule:run` line and the 30-minute lock wording |

### Key Link Verification

| From | To | Status |
|------|----|--------|
| `GateExport::decision()` to `MessageStatus::onTheCustomersPhone()` | WIRED |
| `GateExport::handle()` to `MessageKind::assembledByCode()` | WIRED |
| six actions to `ScheduleChanged::dispatch()` | WIRED |
| `FolderSync::queueCardsForRoomChanges` to `ScheduleChanged` (room switch) | WIRED |
| event to listener to job to `RecordsWriter::writeOwnerIntelligence()` | WIRED |
| `writeOwnerIntelligence()` to `writeRouter()` (same lock) | WIRED |
| `RecordInbound::record()` meta.keyword to `TimeToBooked::keywordOf()` | WIRED |
| `OwnerIntelligenceSection` to `TimeToBooked::summaryLine()` | WIRED |
| `ContactInfolist::timeToBookedLine` to `forContact()` and `DurationText::format()` | WIRED |
| `routes/console.php` `when()` to `ActiveConfig::displayTimezone()` (at schedule time, not load) and to the cache marker via `onSuccess` | WIRED |
| `bootstrap/providers.php` to `OwnerIntelligenceServiceProvider` | WIRED |

### Data-Flow Trace (Level 4)

| Artifact | Data | Source | Real data | Status |
|----------|------|--------|-----------|--------|
| Contact 'Time to booked' | seconds | messages (meta.keyword) JOIN conversations JOIN grouped appointments min(booked_at) | Yes | FLOWING |
| Section line / `time-to-booked.md` | median, count | same single SELECT, PHP median | Yes | FLOWING |
| `today.md` | rows | Appointment and ScheduleBlock queries in `TodayRecord` | Yes | FLOWING |
| `records/CLAUDE.md` | room flag | `ActiveConfig::hasRoom('owner_intelligence')` | Yes | FLOWING |
| `gate:export` lines | message rows | `Message` with `gate_outcome` | Yes | FLOWING |

### Behavioral Spot-Checks (run in this verification, SQLite)

| Behavior | Command | Result | Status |
|----------|---------|--------|--------|
| Export, room, redraw, nightly, time-to-booked, rooms, opt-out and unit tests | `php artisan test --compact tests/Feature/FrontDesk/GateExportTest.php tests/Feature/OwnerIntelligence tests/Feature/Rooms tests/Feature/Filament/ContactTimeToBookedTest.php tests/Feature/Inbound/OptOutTest.php tests/Unit` | 1041 passed, 6879 assertions | PASS |
| Records, booking and ICM sync | `php artisan test --compact tests/Feature/Records tests/Feature/Booking tests/Feature/Icm` | 841 passed, 4422 assertions | PASS |
| Nightly, redraw, router (filtered) | `php artisan test --compact --filter="NightlyRebuild\|writeOwnerIntelligence\|router" tests/Feature/OwnerIntelligence` | 15 passed | PASS |
| Full suite on CI, branch pushed at 4bb969f (same code as HEAD; later commits are .planning docs only, confirmed by `git diff --stat 4bb969f..HEAD`) | `gh run view 44226865631` plus job logs | headSha 4bb969f, conclusion success; `pest (sqlite)` and `pest (pgsql)` each "Tests: 3347 passed (26094 assertions)" | PASS |

The full suite was not re-run locally by me; the orchestrator's local run (3347 passed) and the CI run above are the evidence, and I re-ran the two phase-relevant slices myself.

### Probe Execution

No probe scripts declared by the phase. Not applicable.

### Requirements Coverage

| Requirement | Source Plan(s) | Description | Status | Evidence |
|-------------|----------------|-------------|--------|----------|
| GATE-05 | 09-01 | `gate:export` reports only what went out, one `original_text` meaning, `answered` and fixed-reply kinds | SATISFIED | Truth 3 |
| BOOK-06 | 09-02, 09-03, 09-04 | Minutes from first inbound message to booked appointment, on the contact and in a dashboard summary | SATISFIED | Truth 2 (incl. WR-02) |
| ROOM-13 | 09-03, 09-04 | Owner intelligence room to the room standard | SATISFIED | Truth 4 |
| RECS-02 | 09-04, 09-05, 09-06 | `today.md` on every booking change, nightly full rebuild | SATISFIED | Truth 1 (incl. WR-01, WR-03) |

All four IDs are declared in plan frontmatter (09-01 GATE-05; 09-02 BOOK-06; 09-03 ROOM-13, BOOK-06; 09-04 RECS-02, ROOM-13, BOOK-06; 09-05 and 09-06 RECS-02) and in REQUIREMENTS.md (lines 41, 58, 65, 73; traceability rows ROOM-13, BOOK-06, RECS-02, GATE-05 map to Phase 9, marked Complete). No orphaned requirements: REQUIREMENTS.md maps no other ID to Phase 9.

### Anti-Patterns Found

None. TBD/FIXME/XXX/TODO/HACK grep over the phase's modified app and route files (GateExport, the OwnerIntelligence module, event, listener, job, RecordsWriter, console.php, RecordInbound) returned no hits. No stubs or hollow props found. The 09-01 TDD gate (GREEN commits typed `fix(09-01)`) carries Ian's recorded override of 2026-11-17 (STATE.md decisions, de921bc); it does not affect behavior. Test-first order for the review fixes is visible in the log (3a82ac4 then 28b85b3, 3263376 then b2a4fc6, 35128c9 then e27fd7f, 311191c then e7dfba1, 11e1b51 then 4d38d46).

### Code review disposition

09-REVIEW-DISPOSITION.md: 12 findings, 0 open. WR-01 to WR-04 and IN-01, IN-02, IN-04, IN-06, IN-07 fixed (code verified above for WR-01 to WR-03 and IN-02; the rest are docblock or wording), IN-03 and IN-08 accepted by Ian, IN-05 deferred by Ian to Phase 12 planning (ROADMAP updated). Not gaps.

### Human Verification Required

Two items remain. Both are live hand checks that the project rules say the verifier never runs:

1. **Keyboard-only and VoiceOver walk, phone width** (WINDOWS.md row 83). Test: walk the contact screen's Time to booked line and the dashboard's Owner intelligence section by keyboard and with VoiceOver; check a phone-width viewport for no sideways scroll. Expected: label read with its value, section read under its heading, focus order unchanged, lines wrap. Why human: screen reader and real-device behavior.
2. **First-deploy cron check** (WINDOWS.md row 88, Phase 12). Test: with cron running `php artisan schedule:run` every minute, confirm `records/today.md` names the new day after 00:10 business time. Expected: the title date advances. Why human: needs a real scheduler and wall clock.

**Items from the 2026-11-17 report now closed (evidence in this run):**
- WR-01 decision: Ian ruled and it is fixed (28b85b3), verified above.
- CI pgsql leg (old item 3; WINDOWS.md rows 80, 82, 84, 85, 86, 87): CI run 44226865631 at 4bb969f shows `pest (pgsql)` success, 3347 passed, covering GateExportTest, TimeToBookedTest, OwnerIntelligenceBootTest, ContactTimeToBookedTest, TodayRecordTest, TimeToBookedFileTest, RedrawTest and the Booking folder.
- BookingPageBrowserTest on CI (old item 4; row 81): covered by the same green full-suite run on both legs.

**Housekeeping for the orchestrator (not changed by me; I was told to write only this file):** `.planning/WINDOWS.md` rows 80 to 82 and 84 to 87 still read `open` although CI evidence now satisfies them, and `.planning/phases/09-owner-intelligence/09-UAT.md` (status `testing`, 5 items all `pending`, sourced from the stale report) still lists the WR-01 decision and the CI items. Update both from this report so UAT carries only the two items above.

### Gaps Summary

No gaps against the roadmap success criteria or plan must-haves. Where the review fixes changed a plan truth (09-04 "touch no other records file" for `writeOwnerIntelligence()`, and 09-06 "only at 00:10"), the change is Ian's recorded ruling in 09-REVIEW-DISPOSITION.md, the code and tests match the new wording, and it is not a deviation. The phase goal is achieved in code, under test on SQLite and on CI Postgres. Status is `human_needed` solely because the two live hand checks above are unrun.

---

_Verified: 2026-11-18_
_Verifier: Claude (gsd-verifier)_
