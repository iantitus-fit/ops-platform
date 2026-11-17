---
phase: 09-owner-intelligence
verified: 2026-11-17T17:30:00Z
status: human_needed
score: 4/4 roadmap success criteria verified (plan must-haves verified; 1 CONTEXT-level warning, WR-01)
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
covered_digest: "v3:sha256:3247e2eaa761b8a0a23a43f421eca97dbe1d035e549115a8c6af4fffe1db093d"
behavior_unverified: 0
overrides_applied: 0
human_verification:
  - test: "Decide WR-01 (router lags a room toggle until the next 00:10 rebuild)"
    expected: "Ian chooses: accept and document, or have the redraw job also rewrite records/CLAUDE.md under the records:owner-intelligence lock (this relaxes 09-04's 'touch no other records file' truth for that one call)"
    why_human: "CONTEXT D-15 says the router stops naming the files at the next sync or rebuild; the plans scoped the router to rebuild only. A scope decision, not a code fact."
  - test: "Keyboard-only and VoiceOver walk of the contact screen's Time to booked line and the dashboard's Owner intelligence section; phone-width check for no sideways scroll"
    expected: "Label read with its value, section read under its heading, focus order on the contact screen unchanged, lines wrap at phone width (WINDOWS.md row 83)"
    why_human: "Screen reader and real-device behavior cannot be checked from code (CLAUDE.md WCAG rule; D-29)"
  - test: "Push the phase branch and confirm the CI pgsql leg is green for the whole suite, in particular GateExportTest, TimeToBookedTest, TodayRecordTest, TimeToBookedFileTest, RedrawTest, OwnerIntelligenceBootTest, ContactTimeToBookedTest (WINDOWS.md rows 80, 82, 84, 85, 86, 87)"
    expected: "Both sqlite and pgsql legs pass"
    why_human: "Postgres is not run locally; only SQLite was run in this verification"
  - test: "Confirm the 5 BookingPageBrowserTest failures seen on the dev machine (target size 21px, footer hover border) pass in CI (WINDOWS.md row 81)"
    expected: "Pass on CI; no Phase 9 file touches them"
    why_human: "Environment-dependent browser tests"
  - test: "At first deploy (Phase 12): cron runs php artisan schedule:run every minute and records/today.md names the new day after 00:10 business time (WINDOWS.md row 88)"
    expected: "Title date advances after 00:10 in the business zone"
    why_human: "Needs a real scheduler and wall clock"
---

# Phase 9: Owner Intelligence Verification Report

**Phase Goal:** The owner and the owner's own AI can read how fast the business finishes a conversation and what is on today, and `gate:export` reports only what went out.
**Verified:** 2026-11-17
**Status:** human_needed
**Re-verification:** No, initial verification

## Goal Achievement

### Observable Truths (ROADMAP success criteria, the contract)

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | `records/today.md` is re-rendered from appointments on every booking change, and a nightly full rebuild of `records/` is scheduled | VERIFIED | `ScheduleChanged::dispatch()` present in all six named actions (BookAppointment x2 paths, CancelAppointment, MarkAppointmentDone, EditVisitNote, BlockTime, RemoveBlock) and in `FolderSync::queueCardsForRoomChanges`. Event is `ShouldDispatchAfterCommit`; listener queues `RedrawOwnerIntelligenceRecords` (unique-until-processing, WithoutOverlapping, backoff). Job calls `RecordsWriter::writeOwnerIntelligence()`. `routes/console.php` schedules `records:rebuild` every minute gated to 00:10 in `ActiveConfig::displayTimezone()` read inside the `when()` closure, with `withoutOverlapping(120)` and `onOneServer()`. RedrawTest and NightlyRebuildTest pass. |
| 2 | Minutes from a contact's first message to their booked appointment are shown on the contact and in a dashboard summary | VERIFIED | `TimeToBooked` computes on read in one SELECT (grouped Appointment `joinSub`, keyword skip via `TextKeywords::actionFor`, strict-before check). `ContactInfolist` shows 'Time to booked' via `DurationText::format`; `OwnerIntelligenceSection` shows `summaryLine()` (30-day median, in business zone). Both gated by `hasRoom('owner_intelligence')`. TimeToBookedTest (incl. one-query counts), ContactTimeToBookedTest and the boot test pass. Customer card unchanged. |
| 3 | `gate:export` counts only what went out, gives `original_text` one meaning on every path, exports `answered` and the 02.1 fixed-reply kinds | VERIFIED | `GateExport::decision()`: `final_text` only when status is in `MessageStatus::onTheCustomersPhone()` (Received, Sent, Delivered); `original_text` is always `gate.inputs.agent_reply`, null if blank, no body fallback; kind filter is `agent_reply` plus `MessageKind::assembledByCode()` (six kinds); `status`, `kind`, `has_model_words`, `answered_by_message_id` appended; `--without-text` swaps text for lengths. GateExportTest passes. Also `VisitAskedAbout::VISIT_CHANGED` wired in `SendOwnerReply` (D-26). |
| 4 | The owner intelligence room meets the standard (folder, `CONTEXT.md` that says it writes `today.md`, example file, module, boot test) | VERIFIED | `rooms/owner_intelligence/CONTEXT.md` with contract `writes_records: [records/today.md, records/time-to-booked.md]`, `owns: none`, `connector: none`; `OwnerIntelligenceServiceProvider` registered in `bootstrap/providers.php` after ScheduleAndBooking; `exampleFolderFileCount()` is 29; `OwnerIntelligenceBootTest` passes (incl. axe). The room has no "example file" of its own by D-22 (owns no file); the CONTEXT.md carries the samples. |

**Score:** 4/4 roadmap truths verified, 0 behavior-unverified. Plan-level truths for 09-01 to 09-06 (D-01 to D-29) spot-checked against code and the passing tests listed below.

### Required Artifacts

| Artifact | Status | Details |
|----------|--------|---------|
| `app/Console/Commands/GateExport.php` | VERIFIED | `onTheCustomersPhone()` and `assembledByCode()` both used |
| `app/Rooms/ScheduleAndBooking/VisitAskedAbout.php` | VERIFIED | `VISIT_CHANGED` const present and used at `SendOwnerReply.php:241` |
| `app/Rooms/OwnerIntelligence/{DurationText,TimeToBooked,TodayRecord,OwnerIntelligenceSection,OwnerIntelligenceServiceProvider}.php` | VERIFIED | Substantive, wired (section via Room descriptor, TimeToBooked via section, infolist and RecordsWriter) |
| `app/Events/ScheduleChanged.php`, `app/Listeners/QueueOwnerIntelligenceRedraw.php`, `app/Jobs/RedrawOwnerIntelligenceRecords.php` | VERIFIED | Wired end to end |
| `app/Records/RecordsWriter.php` | VERIFIED | `writeOwnerIntelligence()` writes or deletes by room state inside `records:owner-intelligence` lock; called from `rebuild()` and the job |
| `routes/console.php` | VERIFIED | `Schedule::command('records:rebuild')` with 00:10 business-zone filter, exception reported and false |
| `examples/example-hvac/rooms/owner_intelligence/CONTEXT.md` | VERIFIED | Contract and samples present (sample formatting drifts from real output, see IN-02) |
| `docs/SPEC.md`, `docs/SETUP.md` | VERIFIED | SPEC bridge list names `time-to-booked.md`; SETUP has the `schedule:run` line |

### Key Link Verification

| From | To | Status |
|------|----|--------|
| `GateExport::decision()` to `MessageStatus::onTheCustomersPhone()` | WIRED |
| `GateExport::handle()` to `MessageKind::assembledByCode()` | WIRED |
| six actions to `ScheduleChanged::dispatch()` | WIRED |
| `FolderSync::queueCardsForRoomChanges` to `ScheduleChanged` (room switch) | WIRED |
| job to `RecordsWriter::writeOwnerIntelligence()` | WIRED |
| `OwnerIntelligenceSection` to `TimeToBooked::summaryLine()` | WIRED |
| `ContactInfolist::timeToBookedLine` to `forContact()` and `DurationText::format()` | WIRED |
| `routes/console.php` `when()` to `ActiveConfig::displayTimezone()` (at schedule time, not load) | WIRED |
| `bootstrap/providers.php` to `OwnerIntelligenceServiceProvider` | WIRED |

### Data-Flow Trace (Level 4)

| Artifact | Data | Source | Real data | Status |
|----------|------|--------|-----------|--------|
| Contact 'Time to booked' | seconds | messages JOIN conversations JOIN grouped appointments min(booked_at) | Yes | FLOWING |
| Section line / `time-to-booked.md` | median, count | same single SELECT, PHP median | Yes | FLOWING |
| `today.md` | rows | Appointment and ScheduleBlock queries in `TodayRecord` | Yes | FLOWING |
| `gate:export` lines | message rows | `Message` with `gate_outcome` | Yes | FLOWING |

### Behavioral Spot-Checks (run in this verification, SQLite)

| Behavior | Command | Result | Status |
|----------|---------|--------|--------|
| Export, room, redraw, nightly, time-to-booked, unit and architecture tests | `php artisan test --compact tests/Feature/FrontDesk/GateExportTest.php tests/Feature/OwnerIntelligence tests/Feature/Rooms/OwnerIntelligenceBootTest.php tests/Feature/Rooms/RoomOwnershipTest.php tests/Unit` | 585 passed, 4385 assertions | PASS |
| Records, booking, contact line | `php artisan test --compact tests/Feature/Records tests/Feature/Booking tests/Feature/Filament/ContactTimeToBookedTest.php` | 555 passed, 3290 assertions | PASS |

Orchestrator reported a full SQLite suite of 3333 passed; not re-run in full here. Behavior-dependent truths (after-commit dispatch, no-op and rollback queue nothing, unique redraw, DST once-only) are each exercised by named tests in RedrawTest and NightlyRebuildTest, so none is left presence-only.

### Probe Execution

No probe scripts declared by the phase. Not applicable.

### Requirements Coverage

| Requirement | Source Plan(s) | Status | Evidence |
|-------------|----------------|--------|----------|
| GATE-05 | 09-01 | SATISFIED | Truth 3 |
| BOOK-06 | 09-02, 09-03, 09-04 | SATISFIED | Truth 2 |
| ROOM-13 | 09-03, 09-04 | SATISFIED | Truth 4 |
| RECS-02 | 09-04, 09-05, 09-06 | SATISFIED | Truth 1 |

All four IDs are declared in plan frontmatter and in REQUIREMENTS.md (marked Complete, traceability rows for Phase 9). No orphaned requirements: REQUIREMENTS.md maps no other ID to Phase 9.

### Anti-Patterns Found

None blocking. TBD/FIXME/XXX/TODO/HACK grep over the phase's modified app and route files returned no hits. No stubs or hollow props found. The 09-01 TDD gate override is recorded by Ian in STATE.md and does not affect behavior.

### Weighing the code review (WR-01 to WR-03, IN-01 to IN-03)

**WR-01 (router lags a room toggle): confirmed in code, WARNING, not a failed must-have.**
`RecordsWriter::writeRouter()` is called only from `rebuild()` (grep: no other caller in `app/`). A sync that turns the room off dispatches `ScheduleChanged`; the job deletes `today.md` and `time-to-booked.md` but leaves `records/CLAUDE.md` naming them until 00:10 (and the reverse when the room is turned on: files appear unnamed). CONTEXT D-15 reads "the next sync or rebuild deletes the two files ... and the router stops naming them (D-25)", so the router sentence is arguably expected at sync time. But the plans did not turn that into a truth: 09-04's D-15 truth says `writeOwnerIntelligence()` and `records:rebuild` "touch no other records file", and 09-04's D-25 truth is tested through `routerMarkdown()` and `rebuild()`. So the code meets every written must-have and the roadmap criteria. Impact is bounded: the owner's AI is pointed at a missing file (or misses a new one) for up to one day, never reads stale data. The reviewer's suggested fix would contradict 09-04's "no other records file" wording, so Ian must choose between accepting the lag with a docs correction (BLUEPRINT.md says the sync rewrites it) or changing the plan truth and the job. Recorded as a human decision item, not a gap.

**WR-02 (opt-out reconstruction):** real but narrow (carrier-side opt-out then a first inbound "YES"); affects only which message starts the clock for that contact. WARNING, accept or document.
**WR-03 (one-minute window):** real; a missed tick at 00:10 skips the night. Plan D-10 specified exactly 00:10 and the tests pin 00:09/00:11 as not passing, so it matches the plan. WARNING for Ian's triage.
**IN-01 to IN-03:** informational. IN-02 (CONTEXT.md sample formatting differs from real output) is a documentation drift in a file the owner's AI reads.

### Human Verification Required

See frontmatter `human_verification` (five items): the WR-01 decision, the keyboard/VoiceOver and phone-width walk, the CI pgsql leg, the BookingPageBrowserTest confirmation on CI, and the first-deploy cron check. All hand checks are already recorded as unrun-verify rows 80 to 88 in `.planning/WINDOWS.md`.

### Gaps Summary

No gaps against the roadmap success criteria or plan must-haves. The phase goal is achieved in code and under test on SQLite. Status is `human_needed` because the live hand checks and the Postgres leg are unrun and WR-01 needs Ian's scope decision.

---

_Verified: 2026-11-17_
_Verifier: Claude (gsd-verifier)_
