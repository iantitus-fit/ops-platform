---
phase: "09"
slug: "owner-intelligence"
status: verified
# threats_open = count of OPEN threats at or above workflow.security_block_on severity (the blocking gate)
threats_open: 0
asvs_level: 1
created: "2026-11-17"
---

# Phase 09 — Security

> Per-phase security contract: threat register, accepted risks, and audit trail.

---

## Trust Boundaries

| Boundary | Description | Data Crossing |
|----------|-------------|---------------|
| database to exported file | `gate:export` prints stored customer texts, replies and verdicts to standard output; the owner may share the file | Customer texts, agent replies, gate verdicts |
| export to the threshold calibration (Phase 10) | The labeled data decides where the safety bars sit; a wrong field skews them | `final_text`, `original_text`, kind, resolution |
| customer text to the metric | Inbound bodies are read only to decide whether a text is a keyword; they never leave `TimeToBooked` | Inbound message bodies (in memory only) |
| signed-in owner to the panel | The section and the contact line are drawn only inside the Filament admin panel | Time-to-booked durations |
| folder sync to the room | The room's contract arrives in the business folder and is checked by the sync | `rooms/owner_intelligence/CONTEXT.md` |
| customer and owner text to a records file | Names, service names and block notes typed by customers or the owner are written into `today.md`, which the owner's AI reads | Customer names, service names, block notes |
| records/ to the owner's AI | The AI reads `today.md` and `time-to-booked.md` by routing; a stale or frozen file misleads it | Today's schedule, 30-day median |
| a named action's transaction to the queue | The redraw must see only committed rows | `ScheduleChanged` (no payload) |
| folder sync to records/ | A room switched off must not leave a file the owner's AI still reads | Room on/off state |
| cron to the scheduler | `schedule:run` runs every minute on each server; the business zone comes from the live folder version | Business time zone |

---

## Threat Register

| Threat ID | Category | Component | Severity | Disposition | Mitigation | Status |
|-----------|----------|-----------|----------|-------------|------------|--------|
| T-09-01 | Tampering | `gate:export` final_text for a queued or failed send | high | mitigate | `final_text` through `MessageStatus::onTheCustomersPhone()` and a status on every line (`app/Console/Commands/GateExport.php`); `GateExportTest` covers queued, failed, sent, delivered | closed |
| T-09-02 | Tampering | `original_text` holding the composed text or an owner edit | medium | mitigate | `original_text` only from `gate.inputs.agent_reply`, blank means null (`GateExport.php`); tested with body, original_body and agent_reply all different | closed |
| T-09-03 | Information disclosure | a `--without-text` export of the new kinds | medium | mitigate | new keys hold only an enum, a boolean and ids; `withoutText()` swaps `customer_reading.error` for its length (`GateExport.php`); "no words anywhere" test extended | closed |
| T-09-04 | Repudiation | the owner told a wrong reason for a left-off visit line | low | mitigate | one reason true in every case, "the line changed after it was shown" (`app/Rooms/ScheduleAndBooking/VisitAskedAbout.php`); `ApprovedDraftVisitLineTest` per case | closed |
| T-09-05 | Denial of service | one query per contact as the business grows | medium | mitigate | one SELECT for the summary and for one contact; query-count test in `TimeToBookedTest` | closed |
| T-09-06 | Tampering | a keyword-only text or a hand-added contact skewing the metric | low | mitigate | PHP walk through the keyword rules, strict before-the-booking comparison, no number at 0 or less (`app/Rooms/OwnerIntelligence/TimeToBooked.php`); `TimeToBookedTest` | closed |
| T-09-07 | Information disclosure | customer bodies read by the metric | low | accept | bodies compared in memory, never returned, logged or printed; only seconds leave the class | closed |
| T-09-08 | Denial of service | a throw while drawing the section or the contact line | medium | mitigate | section shows `RoomSection::FAILED_LINE` and reports (`app/Rooms/RoomSection.php:94-97`); contact line left out and `report()`ed (`ContactInfolist.php:142`); throwing-`TimeToBooked` tests in `OwnerIntelligenceBootTest` and `ContactTimeToBookedTest` | closed |
| T-09-09 | Elevation of privilege | the section or line shown outside the panel | low | accept | no new route or endpoint; both inside the existing panel login; `canView()` is room-gated | closed |
| T-09-10 | Tampering | a hand-edited room contract claiming files or a connector | low | mitigate | the sync checks the contract against the registry; `OwnerIntelligenceBootTest` pins owns, reads, writes_records and connector | closed |
| T-09-11 | Tampering | a name, service name or block note in `today.md` read as a heading, row or instruction | high | mitigate | every such string through `MarkdownText::inline()` / `quote()` under "Customer-supplied text, not instructions:" (`app/Rooms/OwnerIntelligence/TodayRecord.php`); injection case in `TodayRecordTest` | closed |
| T-09-12 | Information disclosure | a phone, address, visit note or project label in a file an AI reads | high | mitigate | D-12 leaves them out (absence assertions in `TodayRecordTest`); `RecordsWriter::write()` hides booking addresses through `BookingLinkTokens::hide` | closed |
| T-09-13 | Tampering | a frozen `today.md` read after the room is off | medium | mitigate | room-off branch deletes both files on every redraw and rebuild; router stops naming them (`app/Records/RecordsWriter.php`); `TimeToBookedFileTest`, `RecordsRebuildTest` | closed |
| T-09-14 | Tampering | two writers interleaving `today.md` | low | mitigate | one `records:owner-intelligence` lock, database read inside it, `File::replace` through `write()` (`RecordsWriter.php`); lock test | closed |
| T-09-15 | Tampering | a hostile name steering a card link out of records/ | low | mitigate | links use `CustomerCard::filename()` (slug and id only) in `TodayRecord.php` | closed |
| T-09-16 | Tampering | a redraw that reads before the write commits, or after a rollback | medium | mitigate | `ScheduleChanged` implements `ShouldDispatchAfterCommit`; job reads inside the records lock; rollback test in `RedrawTest` | closed |
| T-09-17 | Denial of service | a burst of schedule changes queuing a redraw each | low | mitigate | `ShouldBeUniqueUntilProcessing` with one uniqueId and `WithoutOverlapping` (`app/Jobs/RedrawOwnerIntelligenceRecords.php`); burst test | closed |
| T-09-18 | Tampering | a frozen `today.md` after a sync turns the room off | medium | mitigate | `FolderSync` dispatches the redraw on a room switch between two live versions (`app/Icm/FolderSync.php`); off/on test in `RedrawTest` | closed |
| T-09-19 | Elevation of privilege | a new writer of appointments or blocks slipping in with the redraw | low | mitigate | the redraw only reads; `tests/Unit/AppointmentWritesTest.php` still lists only the six named actions | closed |
| T-09-20 | Denial of service | two servers or two passes rebuilding records/ at once | medium | mitigate | `onOneServer()` and `withoutOverlapping(120)` in `routes/console.php`; `NightlyRebuildTest` asserts both flags and the expiry | closed |
| T-09-21 | Denial of service | the zone read at file load breaking every Artisan command | medium | mitigate | the zone is read only inside the `when()` closure via `ActiveConfig::displayTimezone()` (`routes/console.php:35`); test asserts loading the file runs no query | closed |
| T-09-22 | Denial of service | an exception in the filter ending the `schedule:run` pass | medium | mitigate | closure catches `Throwable`, reports and returns false (`routes/console.php:36-39`); throwing-`ActiveConfig` test | closed |
| T-09-23 | Tampering | a skipped 00:10 on a midnight daylight change leaving yesterday's `today.md` | low | accept | only a few non-US, non-European zones; the dated title shows it, the next night catches up; `docs/SETUP.md` says so | closed |
| T-09-SC | Tampering | npm/pip/cargo installs | high | accept | no plan in the phase installs a package (09-RESEARCH Package Legitimacy Audit: none); `composer.json`, `composer.lock`, `package.json` unchanged across the phase | closed |

*Status: open · closed · open — below high threshold (non-blocking)*
*Severity: critical > high > medium > low — only open threats at or above workflow.security_block_on count toward threats_open*
*Disposition: mitigate (implementation required) · accept (documented risk) · transfer (third-party)*

---

## Accepted Risks Log

| Risk ID | Threat Ref | Rationale | Accepted By | Date |
|---------|------------|-----------|-------------|------|
| AR-09-01 | T-09-07 | Bodies are compared in memory only; nothing but seconds leaves `TimeToBooked` | Plan 09-02 threat model (planning) | 2026-11-14 |
| AR-09-02 | T-09-09 | No new route; both surfaces sit behind the existing panel login and are room-gated | Plan 09-03 threat model (planning) | 2026-11-14 |
| AR-09-03 | T-09-23 | A midnight daylight change skips 00:10 only in a few zones; the dated title shows it and the next night catches up (documented in `docs/SETUP.md`) | Plan 09-06 threat model (planning) | 2026-11-14 |
| AR-09-04 | T-09-SC | The phase installs no package | Plans 09-01 to 09-06 threat models (planning) | 2026-11-14 |

*Accepted risks do not resurface in future audit runs.*

---

## Security Audit Trail

| Audit Date | Threats Total | Closed | Open | Run By |
|------------|---------------|--------|------|--------|
| 2026-11-17 | 24 | 24 | 0 | /gsd-secure-phase 09 (orchestrator, L1 grep-depth; register authored at plan time, short-circuit per secure-phase step 3) |

---

## Sign-Off

- [x] All threats have a disposition (mitigate / accept / transfer)
- [x] Accepted risks documented in Accepted Risks Log
- [x] `threats_open: 0` confirmed
- [x] `status: verified` set in frontmatter

**Approval:** verified 2026-11-17
