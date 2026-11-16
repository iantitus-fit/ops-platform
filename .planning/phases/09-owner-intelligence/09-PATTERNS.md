# Phase 9: Owner Intelligence - Pattern Map

**Mapped:** 2026-11-14
**Files analyzed:** 26 (14 new, 12 modified)
**Analogs found:** 25 / 26 (the scheduler `when()` entry has only a structural analog)

All analog paths below were confirmed git-tracked with `git ls-files`. Line numbers come from the reads in this session. Rules read: `.ai/rules/app.md` (ActiveConfig is the only config reader; money in cents). Tests rules: tests-first, Pest, `exampleFolderFileCount()`.

## File Classification

| New/Modified File | Role | Data Flow | Closest Analog | Match |
|---|---|---|---|---|
| `app/Console/Commands/GateExport.php` (mod) | command | batch / transform (JSON lines) | itself (`decision()` :89-118, filter :51) | exact |
| `app/Rooms/ScheduleAndBooking/VisitAskedAbout.php` (mod, :31) | constant | n/a | itself | exact |
| `app/Actions/SendOwnerReply.php` (mod, docblock :221 only) | action | n/a | itself | exact |
| `app/Rooms/OwnerIntelligence/DurationText.php` (new) | utility | transform | `BookingSettings::dayText/spanText` (static pure formatters, :102-129) | role-match |
| `app/Rooms/OwnerIntelligence/TimeToBooked.php` (new) | service (read-only query) | request-response, one SELECT + PHP | `LeadsSection::rows()` (eager-load, no per-row queries) + `TextKeywords` use in `RecordInbound::keywordIn()` | partial |
| `app/Rooms/OwnerIntelligence/TodayRecord.php` (new) | service (renderer) | transform | `app/Records/CustomerCard.php` (visitTime, zone naming, MarkdownText) | role-match |
| `app/Records/RecordsWriter.php` (mod) | service | file-I/O | itself: `writeSummaries()` :85-95, `write()` :183-186, `routerMarkdown()` :114-126, `rebuild()` :137-162 | exact |
| `app/Console/Commands/RecordsRebuild.php` (mod) | command | request-response | itself :31 | exact |
| `routes/console.php` (mod) | config (schedule) | event-driven | existing `Schedule::job(...)->withoutOverlapping()` lines | partial |
| `app/Events/ScheduleChanged.php` (new) | event | pub-sub | `app/Events/CustomerRecordChanged.php` | exact |
| `app/Listeners/QueueOwnerIntelligenceRedraw.php` (new) | listener | event-driven | `app/Listeners/QueueCustomerRecordRender.php` | exact |
| `app/Jobs/RedrawOwnerIntelligenceRecords.php` (new) | job | event-driven | `app/Jobs/RenderCustomerCard.php` | exact |
| `app/Actions/{Book,Cancel,MarkDone}Appointment, EditVisitNote, BlockTime, RemoveBlock` (mod) | action | CRUD | `BlockTime.php:73,104`, `RemoveBlock.php:38-50` (dispatch placement) | exact |
| `app/Icm/FolderSync.php` (mod, `queueCardsForRoomChanges()` ~:132) | service | event-driven | itself | exact |
| `app/Rooms/OwnerIntelligence/OwnerIntelligenceServiceProvider.php` (new) | provider | n/a | `app/Rooms/Leads/LeadsServiceProvider.php` | exact |
| `app/Rooms/OwnerIntelligence/OwnerIntelligenceSection.php` (new) | component (RoomSection) | request-response | `app/Rooms/Leads/LeadsSection.php` (`contentLines()`) | exact |
| `bootstrap/providers.php` (mod) | config | n/a | itself | exact |
| `examples/example-hvac/rooms/owner_intelligence/CONTEXT.md` (new) | config (room contract) | n/a | `examples/example-hvac/rooms/leads/CONTEXT.md` | exact |
| `examples/example-hvac/records/CLAUDE.md` (mod) | config (router copy) | n/a | `RecordsWriter::routerMarkdown()` | exact |
| `app/Filament/Resources/Contacts/Schemas/ContactInfolist.php` (mod) | component | request-response | itself: `next_appointment` TextEntry :49-52 | exact |
| `tests/Pest.php` (mod, :321 28 to 29) | test helper | n/a | itself | exact |
| `tests/Feature/FrontDesk/GateExportTest.php` (mod) | test | batch | itself (:136-196) | exact |
| `tests/Feature/Rooms/OwnerIntelligenceBootTest.php` (new) | test | request-response | `tests/Feature/Rooms/LeadsBootTest.php` | exact |
| `tests/Feature/OwnerIntelligence/TimeToBookedTest.php` (new) | test | request-response | `tests/Feature/Rooms/LeadsSectionTest.php:200-222` (query count) | exact |
| `tests/Feature/OwnerIntelligence/{TodayRecordTest,RedrawTest,NightlyRebuildTest}.php` (new) | test | file-I/O / event-driven | `tests/Feature/Records/RecordsRebuildTest.php`, `BookingScheduleTest.php:5-13`, `SweepUndispatchedTest.php:119-127` | role-match |
| `tests/Unit/OwnerIntelligence/DurationTextTest.php` (new) | test | transform | Unit tests (no TestCase) with Pest dataset | role-match |
| `tests/Feature/Filament/ContactTimeToBookedTest.php` (new) | test | request-response | `ContactByHandTest.php:203-221` | exact |

## Pattern Assignments

### `app/Console/Commands/GateExport.php` (command, batch)

**Analog:** itself. Five edits (tests first).

**Kind filter** (current :50-53), replace the single kind with agent_reply plus the six code kinds:
```php
Message::query()
    ->where('kind', MessageKind::AgentReply->value)
    ->whereNotNull('gate_outcome')
    ->when($since, fn ($query) => $query->where('created_at', '>=', $since))
// becomes whereIn('kind', [MessageKind::AgentReply->value, ...array_map(fn (MessageKind $k): string => $k->value, MessageKind::assembledByCode())])
```

**decision() core** (current :86-118; the text lines to change):
```php
$gate = $message->gate ?? [];
$finalText = in_array($message->status, [MessageStatus::Draft, MessageStatus::Withdrawn], true) ? null : $message->body;   // :89 bug
$originalText = $message->original_body ?? $message->body;                                                              // :90 three meanings
...
if ($withoutText) {
    $line['verdict'] = $this->withoutText($gate);
    $line['final_text_length'] = $finalText === null ? null : mb_strlen($finalText);
    $line['original_text_length'] = mb_strlen($originalText);      // must become null-safe
} else { $line['final_text'] = $finalText; $line['original_text'] = $originalText; }
return [...$line, 'resolution' => ..., 'resolved_at' => ..., 'resolved_by' => ..., 'owner_edited' => ...];
```
Target (RESEARCH "Recommended decision()"): `$wentOut = in_array($message->status, MessageStatus::onTheCustomersPhone(), true)`; `$agentReply = $gate['inputs']['agent_reply'] ?? null`; `$originalText = is_string($agentReply) && trim($agentReply) !== '' ? $agentReply : null` (holdWithoutWords stores `''`, DecideSendOrDraft.php:144). Append after `owner_edited`: `status`, `kind`, `has_model_words` (private method mirroring `OutboundSender::partsOf()` :571-591), `answered_by_message_id` (`isset($message->meta['answered_by']) ? (int) ... : null`). Keep the docblock sentence "Holding, handoff and life-safety texts are not decisions and are left out." Output stays raw via `getOutput()->write($line.PHP_EOL, false, OutputInterface::OUTPUT_RAW)`.

**Tests (`GateExportTest.php`):** forced: :144-147 key list gets four new keys. Hollowed (add new tests where `body`, `original_body`, `gate.inputs.agent_reply` all differ): :150-166, :168-196. Keep new-kind fixtures OUT of `gateExportDecisions()` (it feeds `toHaveCount(5)` at :136, :243); build a second helper.

---

### `app/Rooms/ScheduleAndBooking/VisitAskedAbout.php` (constant, D-26)

**Analog:** itself, :27-31.
```php
public const string VISIT_CHANGED = 'the visit changed after the line was shown';   // -> 'the line changed after it was shown'
```
Update its docblock (:27-29). One use: `app/Actions/SendOwnerReply.php:240` (docblock at :221). Tests (`tests/Feature/Booking/ApprovedDraftVisitLineTest.php`): :270 test title, :285 notification title, :289 `meta['visit_line_left_off']`. Add a third dataset row (line's own words edited). `docs/BLUEPRINT.md:133` same commit.

---

### `app/Rooms/OwnerIntelligence/OwnerIntelligenceServiceProvider.php` (provider)

**Analog:** `app/Rooms/Leads/LeadsServiceProvider.php` (read in full).
```php
class LeadsServiceProvider extends RoomServiceProvider
{
    protected function room(): Room
    {
        return new Room(key: 'leads', label: 'leads', sections: [LeadsSection::class], claimedWidgets: [NewLeads::class]);
    }
}
```
Copy minus `claimedWidgets` and minus the `boot()` override (nothing boots). Key `owner_intelligence`, label `'owner intelligence'`, `neverLeftOutBecause` left null. Docblock style: say what it owns (nothing), that it is optional, cite the D-numbers.

**bootstrap/providers.php:** append `OwnerIntelligenceServiceProvider::class` after `ScheduleAndBookingServiceProvider::class` (list is currently AppServiceProvider first, then rooms; keep AppServiceProvider first, boot test asserts it). Add the `use` in alphabetical order (after `MoneyServiceProvider`).

---

### `app/Rooms/OwnerIntelligence/OwnerIntelligenceSection.php` (RoomSection)

**Analog:** `app/Rooms/Leads/LeadsSection.php` :15-52.
```php
class LeadsSection extends RoomSection
{
    public static function roomKey(): string { return 'leads'; }

    /** @return list<string> */
    protected function contentLines(): array { ... return ["No open {$plural}."]; ... }
}
```
Copy `roomKey()` returning `'owner_intelligence'` and `contentLines()` returning one line (the D-07 median line, or "No new customers booked in the last 30 days."). Use the default shared view (no `$view` override, no `getViewData()` override); `RoomSection::getViewData()` already turns a throw into `FAILED_LINE` and `emptyLine()` is null because `owns` is empty. Reads zone via `app(ActiveConfig::class)->displayTimezone()`.

---

### `examples/example-hvac/rooms/owner_intelligence/CONTEXT.md` (room contract)

**Analog:** `examples/example-hvac/rooms/leads/CONTEXT.md`. Front matter shape:
```
---
type: room
owns: none
reads: [business/lead-sources.md, business/offers/*.md]
writes_records: none
connector: none
---
```
New values: `reads: [business/profile.md]`, `writes_records: [records/today.md, records/time-to-booked.md]` (entries must start with `records/`, RoomChecks.php:505-506). Body, in the leads style (short plain paragraphs, "It owns no file.", "It has no outside system.", "It hides while this room is off"): name both files, show a short sample of each (the D-13 day, the time-to-booked line), name the zone read from the profile. Folder holds only this one file (D-22).

---

### `app/Events/ScheduleChanged.php` (event) - exact analog `CustomerRecordChanged`

```php
namespace App\Events;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class CustomerRecordChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    public function __construct(public int $contactId) {}
}
```
Copy with no constructor args (or none promoted). Docblock states: fired by the six named actions inside their transaction, held until commit, carries nothing so the job reads the newest state.

**Dispatch placement:** `BlockTime.php` after `ScheduleBlock::create([...])` inside `store()` (:104, inside `DB::transaction` at :73); `RemoveBlock.php` after `$held->update([...])` inside the closure (:46-50) but only on the path that changed state (the early `return` at :39-41 must dispatch nothing); Book (`store()` and `storeFromLink()`), Cancel, MarkDone, EditVisitNote beside each existing `recordRoomEntry->handle(...)` call. Beware `tests/Unit/AppointmentWritesTest.php`: files importing `Appointment`/`ScheduleBlock` must not gain `->save(`/`->push(` etc.; `ScheduleChanged::dispatch()` is fine.

### `app/Listeners/QueueOwnerIntelligenceRedraw.php` (listener) - exact analog

```php
class QueueCustomerRecordRender
{
    public function handle(CustomerRecordChanged $event): void
    {
        RenderCustomerCard::dispatch($event->contactId);
    }
}
```
Copy; type-hint `ScheduleChanged`, dispatch `RedrawOwnerIntelligenceRecords::dispatch()`. No registration needed (auto-discovery by type-hint).

### `app/Jobs/RedrawOwnerIntelligenceRecords.php` (job) - exact analog `RenderCustomerCard`

**Imports/shape (:1-23):**
```php
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\UniqueFor;
use Illuminate\Queue\Middleware\WithoutOverlapping;

#[UniqueFor(300)]
class RenderCustomerCard implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;
    public int $maxExceptions = 3;
```
Copy `uniqueId()` as a constant string (e.g. `'owner-intelligence'`, no contact id), `middleware()` -> `[(new WithoutOverlapping('owner-intelligence'))->releaseAfter(5)->expireAfter(60)]`, `retryUntil()` `now()->addMinutes(10)`, `backoff()` `[5, 30, 120]`, and `handle(RecordsWriter $writer): void { $writer->writeOwnerIntelligence(); }`. Drop the contact loading.

---

### `app/Records/RecordsWriter.php` (service, file-I/O)

**New `writeOwnerIntelligence()`: analog `writeSummaries()` :85-95:**
```php
public function writeSummaries(): void
{
    $this->ensureBusinessFolderExists();

    Cache::lock('records:summaries', 30)->block(10, function (): void {
        File::ensureDirectoryExists($this->customersDirectory());
        $this->write($this->customersDirectory().'/INDEX.md', $this->indexMarkdown());
        $this->write($this->root().'/open-leads.md', $this->openLeadsMarkdown());
    });
}
```
Copy with lock `'records:owner-intelligence'`; DB read INSIDE the closure. Inside: `if ($this->activeConfig->hasRoom('owner_intelligence'))` write `today.md` and `time-to-booked.md` via `$this->write(...)`; else `File::delete` each if it exists (D-15). All file I/O stays in this class (arch test: `File` facade only in Loader, Changelog, RecordsWriter). Render in the room classes (`TodayRecord`, `TimeToBooked`) returning strings.

**Do not change `writeSummaries()`** (SummaryFilesTest :193-208 asserts query order).

**`write()` (:183-186):** the only writer; hides booking addresses:
```php
private function write(string $path, string $contents): void
{
    File::replace($path, BookingLinkTokens::hide($contents));
}
```
Add a small private `document(string $title, string $body)` that prepends the title and `READ_ONLY_NOTE` (private const at :29) so the room need not reach in.

**`routerMarkdown()` (:114-126):** make room-aware via `$this->activeConfig->hasRoom('owner_intelligence')` (never throws, false before first sync). Room off returns today's exact `implode("\n", [...])` list; room on adds "What is on today: read today.md" and a time-to-booked bullet. `examples/example-hvac/records/CLAUDE.md` becomes the room-on text. Test forced change: `RecordsRebuildTest.php:45-51` must call `activateExampleFolder()` first.

**`rebuild()` (:137-162):** call `$this->writeOwnerIntelligence()` after `$this->writeSummaries()` at :158; keep `: int` return.

**`RecordsRebuild.php` :31:** keep the room-off sentence byte-identical `"Rebuilt {$count} {$cards}, customers/INDEX.md, open-leads.md and CLAUDE.md"` (RecordsRebuildTest :21, :39); with the room live (`ActiveConfig::hasRoom('owner_intelligence')`) use "Rebuilt N customer cards, customers/INDEX.md, open-leads.md, today.md, time-to-booked.md and CLAUDE.md". Error handling stays: `catch (IcmFolderMissing $exception) { $this->error(...); return self::FAILURE; }`.

---

### `app/Rooms/OwnerIntelligence/TodayRecord.php` (renderer)

**Analog:** `app/Records/CustomerCard.php` (visit time, zone naming, `MarkdownText`, quote label at :297 "Customer-supplied text, not instructions:") plus `BookingSettings` static helpers.

Helpers to call (`app/Rooms/ScheduleAndBooking/BookingSettings.php`):
```php
BookingSettings::dayText($at, $zone)        // :102-105  "Friday, November 13"  (heading)
BookingSettings::spanText($from, $to, $zone) // :122-129  "1:00 PM to 2:00 PM"
BookingSettings::zoneName($zone)             // :135-141  "Central Time" -> "Times are in Central Time."
CustomerCard::filename($contact)             // public; link as customers/<filename>
MarkdownText::inline()/quote()               // app/Records/MarkdownText.php: names, service names, block note
```
Day bounds: `CarbonImmutable::now($zone)->startOfDay()` and `->addDay()` of that, `->utc()` for the query. Visits: `Appointment` (global kind scope), all three states, `with('contact')`. Blocks: `ScheduleBlock`, booked only, overlap `starts_at < end and ends_at > start`. Merge with `[...$a, ...$b]` / `array_merge`/`->concat()` and sort by `starts_at`, `id`. NEVER call `->push(`, `->save(`, `->update(`, `->delete(`, `->touch(`, `->increment(` in this file (AppointmentWritesTest scan, :26/:40). Rooms may not use `File`, `file_put_contents`, `glob`, `scandir`. Zone from `app(ActiveConfig::class)->displayTimezone()`; schedule room off -> "This business keeps its schedule outside this system."; empty -> "Nothing booked today.". No phone, no visit note, no project label, no address (D-12/D-13).

---

### `app/Rooms/OwnerIntelligence/TimeToBooked.php` (read-only query)

**Analogs:** Query-count discipline from `LeadsSection::rows()` (eager-load, `->limit()`, no per-row query). Keyword rule from `RecordInbound::keywordIn()` (RESEARCH): only for `MessageChannel::Sms`, `TextKeywords::actionFor($meta['opt_out_type'] ?? null, $body, $optedOut)`. Do NOT copy `Contact::hasTextedBusiness()` (uses `activities` in SQL).

Core query (prototyped in RESEARCH Pattern 2; copy verbatim): `Appointment::query()->whereNotNull('contact_id')->selectRaw('contact_id, min(booked_at) as first_booked_at')->groupBy('contact_id')` with optional `havingRaw('min(booked_at) >= ?', [...])` and `where('contact_id', ...)`, then `Message::query()->join('conversations', ...)->joinSub($first, 'first_booking', ...)->where('messages.direction', MessageDirection::In->value)->whereColumn('messages.created_at', '<', 'first_booking.first_booked_at')->orderBy(...)`. PHP walk per contact tracks `$optedOut` (`'stop'` true, `'start'` false), first non-keyword row starts the clock. Elapsed = `getTimestamp()` difference in seconds (never `diffInMinutes`, signed float in Carbon 3); `<= 0` means no number. Cutoff `CarbonImmutable::now($zone)->startOfDay()->subDays(29)->utc()`. Median in PHP (mean of middle two for even), count = contacts with a number. Exactly one SELECT, also for the single-contact call.

### `app/Rooms/OwnerIntelligence/DurationText.php` (formatter)

**Analog:** `BookingSettings` static pure formatters (`public static function dayText(CarbonInterface $at, string $zone): string`). Pure class, input whole seconds, one `public static function` (e.g. `format(int $seconds): string`). Rules per D-06 and RESEARCH Pattern 3 (under 60 s "under a minute"; <=90 min "N minute(s)"; <=48 h "H hour(s) M minute(s)"; else "D day(s) H hour(s)"; zero parts dropped is Assumption A3). Test lives in `tests/Unit/` (no TestCase; `tests/Pest.php` binds TestCase to Feature only).

---

### `routes/console.php` (schedule entry)

**Analog (structure only):** existing entries, e.g.
```php
// Sends owner alerts held until opening time, unless their draft was handled first (D-20).
Schedule::job(new SendDueAlerts)->everyMinute()->withoutOverlapping();
```
Style: one comment line citing the D-number, then one fluent chain. New entry per RESEARCH Pattern 1 (no existing analog for `when()`/`onOneServer()`/zone): `Schedule::command('records:rebuild')->name('records:rebuild')->everyMinute()->when(function (ActiveConfig $config): bool { try { return now($config->displayTimezone())->format('H:i') === '00:10'; } catch (Throwable $e) { report($e); return false; } })->withoutOverlapping(120)->onOneServer();` Add `use App\Icm\ActiveConfig; use Throwable;`. Never read the zone at file load. Test shape: `tests/Feature/Booking/BookingScheduleTest.php:5-13` and `tests/Feature/Inbound/SweepUndispatchedTest.php:119-127` read `app(Schedule::class)->events()`; use `$event->filtersPass(app())` with `travelTo`.

---

### `app/Filament/Resources/Contacts/Schemas/ContactInfolist.php` (component)

**Analog:** itself, :49-52.
```php
TextEntry::make('next_appointment')
    ->label('Next appointment')
    ->state(fn (Contact $record): ?string => self::nextAppointmentLine($record, $timezone))
    ->visible(fn (?string $state): bool => filled($state)),
```
Insert after it, before `automatic_texts` (:53): `TextEntry::make('time_to_booked')->label('Time to booked')->state(fn (Contact $record): ?string => self::timeToBookedLine($record))->visible(fn (?string $state): bool => filled($state))`. Private static helper returns null when the room is off (`app(ActiveConfig::class)->hasRoom('owner_intelligence')`, D-08), when the contact has no number (D-03), and on `Throwable` after `report($exception)`. Calls `TimeToBooked` with the contact id, formats with `DurationText`. Test with `Livewire::test(ViewContact::class, ['record' => $dana->getRouteKey()])` + `assertSee`/`assertDontSee` as in `tests/Feature/Filament/ContactByHandTest.php:203-221`.

---

### Tests

**`tests/Feature/Rooms/OwnerIntelligenceBootTest.php`** - analog `tests/Feature/Rooms/LeadsBootTest.php` (:1-60 read). Copy the `beforeEach` `actingAs(User::factory()->create())`, the `activateExampleFolder()` + `expect($version->file_count)->toBe(exampleFolderFileCount())`, the `Room` field assertions (all empty/null; `sections` `[OwnerIntelligenceSection::class]`; `claimedWidgets` `[]`), and the contract block (`owns` `[]` equals `SpecTables::patternsOwnedBy(File::get(base_path('docs/SPEC.md')), 'owner intelligence')`, `reads` `['business/profile.md']`, `writes_records` `['records/today.md','records/time-to-booked.md']`, `connector` `'none'`, `roomOwnedFiles()` `[]`). Add optional-room case (delete folder, count minus one, `hasRoom` false, section hidden, no Time to booked line) and axe via `RoomSectionAxe::assertClean(RoomSectionAxe::report($page, 'owner_intelligence'))` (with and without a qualifying contact).

**`tests/Feature/OwnerIntelligence/TimeToBookedTest.php`** - query-count analog `tests/Feature/Rooms/LeadsSectionTest.php:200-222`:
```php
DB::enableQueryLog();
$this->get('/admin')->assertOk();
$withThree = count(DB::getQueryLog());
DB::flushQueryLog();
// add 7 more, warm, flush, run again
expect($withTen)->toBe($withThree);
```
Adapt: call the class directly, warm `ActiveConfig` first, assert equal counts and `toBe(1)`. Pitfall 2: set `booked_at` and message `created_at` explicitly in UTC and `travelTo` a frozen now (MessageFactory has no time state).

**`TodayRecordTest`** - D-13 day per RESEARCH "Code Examples" (travelTo `2026-11-13 08:00` America/Chicago, `Appointment::factory()->for($dana)->done()->at(Carbon::parse('2026-11-13 09:00','America/Chicago'), 60)`, `ScheduleBlock::factory()->at(...)`, `app(RecordsWriter::class)->writeOwnerIntelligence()`, `File::get(icmPath('records/today.md'))`); write it before any code.

**`tests/Pest.php` :321** `return 28;` -> 29, nothing else counts a literal.

## Shared Patterns

### Records written whole through RecordsWriter only
**Source:** `app/Records/RecordsWriter.php` `write()` :183-186 and the arch test (`tests/Unit/ArchitectureTest.php:42-47`).
**Apply to:** `today.md`, `time-to-booked.md`, delete-when-room-off. Room classes return strings; only `RecordsWriter` touches the filesystem.

### Config only through ActiveConfig
**Source:** `.ai/rules/app.md`; usage `app(ActiveConfig::class)->displayTimezone()` / `->hasRoom('owner_intelligence')` (e.g. `LeadsSection::rows()`).
**Apply to:** scheduler closure, `TodayRecord`, `TimeToBooked`, section, infolist helper, `RecordsWriter`.

### After-commit event, unique queued job, lock around the DB read
**Source:** `CustomerRecordChanged` + `QueueCustomerRecordRender` + `RenderCustomerCard` + `writeSummaries()`.
**Apply to:** the redraw chain; the DB read is inside `Cache::lock(...)->block(10, ...)` so the last writer wins.

### Throw-safe display
**Source:** `LeadsSection::getViewData()` try/catch with `report($exception)` and `FAILED_LINE`; same `report()` then null/false pattern in the infolist helper and the scheduler `when()` closure.
**Apply to:** section, contact entry, scheduler closure.

### Markdown neutralizing
**Source:** `app/Records/MarkdownText.php` (`inline`, `quote`, `tableCell`), used in `CustomerCard`; precedent test `CardInjectionTest.php`.
**Apply to:** names, service names, block notes in `today.md`.

### Doc-comment style
Per CLAUDE.md/php rules: PHPDoc blocks over inline comments, explicit return types, constructor promotion, curly braces always, PlainWords wording (no exclamation points or em dashes). Run `vendor/bin/pint --dirty --format agent` after PHP edits; create files with `php artisan make:` (`make:event`, `make:listener`, `make:job`, `make:class`, `make:test --pest`).

## No Analog Found

| File | Role | Data Flow | Reason |
|---|---|---|---|
| `Schedule::command(...)->when(...)->onOneServer()` in `routes/console.php` | schedule config | event-driven | No existing entry uses `when()`, a zone, or `onOneServer()`; use RESEARCH Pattern 1 (verified against vendor source) |

## Metadata

**Analog search scope:** `app/Rooms/`, `app/Records/`, `app/Console/Commands/`, `app/Events/`, `app/Listeners/`, `app/Jobs/`, `app/Actions/`, `app/Filament/Resources/Contacts/`, `routes/console.php`, `bootstrap/providers.php`, `examples/example-hvac/rooms/`, `tests/Feature/Rooms/`, `tests/Rooms/`.
**Files read this session:** CONTEXT, RESEARCH (all but the Assumptions Log tail), LeadsServiceProvider, LeadsSection, CustomerRecordChanged, QueueCustomerRecordRender, RenderCustomerCard, leads CONTEXT.md, routes/console.php, RecordsRebuild, RecordsWriter (75-200), GateExport (40-125), ContactInfolist (30-70), LeadsBootTest (1-60), LeadsSectionTest (200-225), VisitAskedAbout (20-40), BookingSettings (98-142), BlockTime/RemoveBlock excerpts, providers.php.
**Tracked-source gate:** all analogs verified with `git ls-files`; none are mirror paths.
**Pattern extraction date:** 2026-11-14
