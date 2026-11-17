---
phase: 09-owner-intelligence
reviewed: 2026-11-17T00:00:00Z
depth: standard
files_reviewed: 41
files_reviewed_list:
  - app/Actions/BlockTime.php
  - app/Actions/BookAppointment.php
  - app/Actions/CancelAppointment.php
  - app/Actions/EditVisitNote.php
  - app/Actions/MarkAppointmentDone.php
  - app/Actions/RemoveBlock.php
  - app/Actions/SendOwnerReply.php
  - app/Console/Commands/GateExport.php
  - app/Console/Commands/RecordsRebuild.php
  - app/Events/ScheduleChanged.php
  - app/Filament/Resources/Contacts/Schemas/ContactInfolist.php
  - app/Icm/FolderSync.php
  - app/Jobs/RedrawOwnerIntelligenceRecords.php
  - app/Listeners/QueueOwnerIntelligenceRedraw.php
  - app/Records/RecordsWriter.php
  - app/Rooms/OwnerIntelligence/DurationText.php
  - app/Rooms/OwnerIntelligence/OwnerIntelligenceSection.php
  - app/Rooms/OwnerIntelligence/OwnerIntelligenceServiceProvider.php
  - app/Rooms/OwnerIntelligence/TimeToBooked.php
  - app/Rooms/OwnerIntelligence/TodayRecord.php
  - app/Rooms/ScheduleAndBooking/VisitAskedAbout.php
  - bootstrap/providers.php
  - docs/BLUEPRINT.md
  - docs/SETUP.md
  - docs/SPEC.md
  - docs/sprints/sprint-1.1.md
  - examples/example-hvac/records/CLAUDE.md
  - examples/example-hvac/rooms/owner_intelligence/CONTEXT.md
  - routes/console.php
  - tests/Feature/Booking/ApprovedDraftVisitLineTest.php
  - tests/Feature/Filament/ContactTimeToBookedTest.php
  - tests/Feature/FrontDesk/GateExportTest.php
  - tests/Feature/OwnerIntelligence/NightlyRebuildTest.php
  - tests/Feature/OwnerIntelligence/RedrawTest.php
  - tests/Feature/OwnerIntelligence/TimeToBookedFileTest.php
  - tests/Feature/OwnerIntelligence/TimeToBookedTest.php
  - tests/Feature/OwnerIntelligence/TodayRecordTest.php
  - tests/Feature/Records/RecordsRebuildTest.php
  - tests/Feature/Rooms/OwnerIntelligenceBootTest.php
  - tests/Pest.php
  - tests/Unit/OwnerIntelligence/DurationTextTest.php
findings:
  critical: 0
  warning: 3
  info: 3
  total: 6
status: issues_found
---

# Phase 9: Code Review Report

**Reviewed:** 2026-11-17
**Depth:** standard
**Files Reviewed:** 41
**Status:** issues_found

## Summary

The phase adds the owner intelligence room (time to booked, `records/today.md`, `records/time-to-booked.md`), a `ScheduleChanged` event fired by the six appointment actions, a unique queued redraw job, a nightly `records:rebuild` schedule line, the contact-screen line, and the `gate:export` changes.

The core logic holds up under tracing. `DurationText` boundaries (59/60 seconds, 90 and 2880 minutes, zero parts dropped) are correct. `TimeToBooked` is a single SELECT whose subquery goes through the Appointment global scope, and its UTC string comparisons are consistent with how the columns are stored. The event is dispatched inside each transaction and held until commit. The job's unique lock, overlap middleware and retry settings are coherent, and `UniqueFor` exists in the installed framework. `TodayRecord` puts names and services through `MarkdownText::inline()`, the same convention `INDEX.md` uses, and quotes the block note under the card's own "Customer-supplied text, not instructions:" label. `ActiveConfig` is bound as scoped, so the queue worker and the scheduler closure do not see a stale config. No security problem was found, and no new agent action was added, so no new chokepoint is needed.

The defects are about the router file's lag behind a room toggle, a mismatch between the opt-out reconstruction and the record-time rule it claims to mirror, and the nightly rebuild's one-minute window.

## Warnings

### WR-01: A room toggle deletes the two files at once but leaves the router naming them until the nightly rebuild

**File:** `app/Records/RecordsWriter.php:114-133` (and `app/Icm/FolderSync.php:148-154`)
**Issue:** `routerMarkdown()` names `today.md` and `time-to-booked.md` only while the room is live (D-25: "a router never points the owner's AI at a file that is not there"). `writeRouter()` is called only from `rebuild()` (`RecordsWriter.php:190`, and nothing else in `app/`). When a sync turns the room off, `FolderSync` dispatches `ScheduleChanged`, the redraw job runs `writeOwnerIntelligence()`, and the two files are deleted. `records/CLAUDE.md` is not rewritten, so it keeps pointing the owner's AI at files that no longer exist until 00:10 the next night. When the room is turned on, the router does not name the new files for the same stretch. `docs/BLUEPRINT.md` says "with it off, the next sync or rebuild deletes both and the records router stops naming them", which is only true of the rebuild. `RecordsRebuildTest` checks the router text for each room state, but no test goes through a sync and then reads the router.
**Fix:** Rewrite the router in the same locked section that deletes or writes the room's files, so the two can never disagree:
```php
Cache::lock('records:owner-intelligence', 30)->block(10, function (): void {
    $this->writeRouter();   // router follows the room, under the same lock

    if (! $this->activeConfig->hasRoom('owner_intelligence')) {
        File::delete([...]);
        return;
    }
    // ...
});
```
Add a test: sync with the room present, then without it, then assert `records/CLAUDE.md` loses `today.md`.

### WR-02: Opt-out reconstruction does not match the record-time rule it says it mirrors

**File:** `app/Rooms/OwnerIntelligence/TimeToBooked.php:169-203`
**Issue:** The docblock says the loop mirrors `RecordInbound::keywordIn()`. That method decides with `$contact->isOptedOut()`, the contact's opt-out state at that moment. The loop instead assumes every contact starts not opted out and flips the flag only on STOP or START messages seen before the first booking. A contact can be opted out without any inbound STOP: `TwilioStatusCallbackController::markOptedOut()` (line 89-97) and `OutboundSender.php:743` set `sms_opted_out_at` when a carrier error reports a block. If that contact's first inbound text is "YES", `RecordInbound` stores it as a `start` keyword (handled, opt-in recorded). `TimeToBooked` sees `optedOut = false`, treats "YES" as an ordinary message and starts the clock on it. The same contact can also show a different number from the one a rebuild of their history would give, so the number is not stable. The case is narrow (the YES is the first inbound text, after a carrier-side opt-out) but it breaks the stated "decided at record time" guarantee (D-01) with no test.
**Fix:** Stop re-deriving the decision. Record the keyword result on the message at record time, for example `meta.keyword = 'stop'|'start'|'help'` set in `RecordInbound::record()` where `$keyword` is already known, and have `firstRealMessageSeconds()` skip rows where `meta.keyword` is set. For rows recorded before this change, keep the current reconstruction as the fallback. A cheaper option is to document the carrier-opt-out case as a known approximation and add a test that pins it.

### WR-03: The nightly rebuild has a one-minute window and no catch-up

**File:** `routes/console.php:28-43`
**Issue:** The rebuild runs only when `now($zone)->format('H:i') === '00:10'`. If `schedule:run` is not invoked in that exact minute (a deploy or restart, a stalled cron, a busy host), the rebuild is skipped for the whole day. `docs/SETUP.md` documents only the daylight-saving skip in a few zones. A missed night leaves `today.md` titled with yesterday's date (so it is visibly stale, as the code intends) and the card "Next appointment" line (Phase 8 IN-06) stale. Nothing retries until the same minute the next night, or until a booking change redraws `today.md`. The cards are only rebuilt by this run.
**Fix:** Pass the gate over a short window and guard it with a once-per-local-date marker, so a late tick still runs it once:
```php
->when(function (ActiveConfig $config): bool {
    try {
        $local = now($config->displayTimezone());

        return $local->format('H:i') >= '00:10'
            && $local->format('H:i') < '01:00'
            && Cache::add('records-rebuild:'.$local->toDateString(), true, now()->addDay());
    } catch (Throwable $exception) { report($exception); return false; }
})
```
Use a marker that is set only after a successful run, or accept one run per date. Update `NightlyRebuildTest`, whose cases pin 00:09 and 00:11 as "does not pass".

## Info

### IN-01: `EditVisitNote` fires `ScheduleChanged` for a change nothing in the new files shows

**File:** `app/Actions/EditVisitNote.php:76`
**Issue:** `TodayRecord` never prints a visit note (D-12), and the time-to-booked numbers do not depend on notes. Every note edit queues a redraw job that rewrites two files with identical content. It is harmless and documented as D-14, but it does a write that has no visible effect and widens the unique-job window for real changes.
**Fix:** Either drop the dispatch from `EditVisitNote`, or keep it and note in the docblock that it is deliberate so a later reader does not treat it as an omission.

### IN-02: The room's CONTEXT.md sample does not match what `TodayRecord` writes

**File:** `examples/example-hvac/rooms/owner_intelligence/CONTEXT.md:18-30`
**Issue:** The sample shows plain `9:00 AM to 10:30 AM: AC repair for Dana Whitfield, done`. The real bullet is `- 9:00 AM to 10:30 AM: AC repair for [Dana Whitfield](customers/dana-whitfield-1.md), done`: it has a leading `- `, a link to the card, and a `# ` title. A block with a note also writes the "Customer-supplied text, not instructions:" label and a quote. This file is part of the business folder, so the owner and the owner's AI read it as the description of the file.
**Fix:** Make the sample match the real output, including the bullet, the link and a block with a note, and keep a test that renders the sample from `TodayRecord` so the two cannot drift.

### IN-03: Platform code now imports the optional room's classes

**File:** `app/Records/RecordsWriter.php:13-15`, `app/Filament/Resources/Contacts/Schemas/ContactInfolist.php:9-10`
**Issue:** `OwnerIntelligenceServiceProvider` states "nothing depends on it" so the room can be absent (D-09). `RecordsWriter` takes `TodayRecord` and `TimeToBooked` in its constructor, and `ContactInfolist` imports `TimeToBooked` and `DurationText`. They are gated by `hasRoom()`, so behavior is correct, but the room's classes are always instantiated and the dependency runs from `app/Records` and `app/Filament` into `app/Rooms/OwnerIntelligence`, against the map's split between platform code and room code. This is a design note, not a defect.
**Fix:** If the layering matters, move the shared pieces (`DurationText`, the two record builders) to a neutral namespace such as `app/Records/`, or resolve them lazily inside the `hasRoom()` branch so an absent room never builds them.

---

_Reviewed: 2026-11-17_
_Reviewer: Claude (gsd-code-reviewer)_
_Depth: standard_
