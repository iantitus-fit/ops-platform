# Phase 8: Schedule and Booking - Pattern Map

**Mapped:** 2026-11-02
**Files analyzed:** 41 (new and modified, grouped below)
**Analogs found:** 38 / 41 (3 partial or none, listed at the end)

All analog paths were confirmed git-tracked with `git ls-files`. None is a gitignored mirror. Project rules read: `CLAUDE.md`, `.ai/rules/index.md`, `.ai/rules/app.md` (money is integer cents; business config only through `App\Icm\ActiveConfig`). Table, model and column names below are the planner's call (CONTEXT Claude's Discretion); the analogs fix the shape, not the names. Line numbers refer to HEAD on branch `gsd/phase-08-schedule-and-booking`.

## File Classification

| New/Modified File | Role | Data Flow | Closest Analog | Match |
|---|---|---|---|---|
| `database/migrations/2026_11_0x_*` (appointments, booking_links, booking_sends; one concern per file) | migration | CRUD | `database/migrations/2026_10_29_000001_create_projects_table.php` | exact |
| `app/Models/Appointment.php`, `BookingLink.php`, `BookingSend.php` (+ blocks if separate) | model | CRUD | `app/Models/OwnerAlert.php` | exact |
| `database/factories/AppointmentFactory.php` etc. | factory | CRUD | `database/factories/OwnerAlertFactory.php` | exact |
| `app/Actions/BookAppointment.php` | action (write) | CRUD, transactional | `app/Actions/OpenProject.php` (+ `CloseProject`) | role-match |
| `app/Actions/CancelAppointment.php`, `MarkAppointmentDone.php`, `EditVisitNote.php` | action (write) | CRUD | `app/Actions/CloseProject.php` | exact |
| `app/Actions/BlockTime.php`, `RemoveBlock.php` | action (write) | CRUD | `app/Actions/CloseProject.php` | role-match |
| `app/Actions/MintBookingLink.php` | action | CRUD | `app/Actions/RaiseOwnerAlert.php` (store-once) | partial |
| `app/Actions/SendBookingText.php` | service/action | event-driven, claim + send | `app/Actions/SendOwnerAlert.php` | exact (claim) |
| `app/Jobs/SendDueBookingTexts.php` | job | batch, scheduled sweep | `app/Jobs/SendDueAlerts.php` | exact |
| `routes/console.php` (modify) | config | batch | its own `SendDueAlerts` line 18 | exact |
| `app/Actions/OutboundSender.php` (modify: D-21, D-26) | service (chokepoint) | request-response | itself, `whyBlocked()` 138-168, `deliver()` 193-205 | exact |
| `app/Enums/AlertReason.php` (modify: 2 cases) | enum | n/a | itself | exact |
| `app/Actions/RunFrontDesk.php` (modify: D-34 order, D-01 link) | service | event-driven | itself (see research Pitfall 9) | exact |
| `app/Actions/OwnerAlertText.php` (modify: booked body) | utility | transform | itself | exact |
| `app/Enums/ProjectActor.php` (modify) + 4 ternary sites | enum | n/a | itself | exact |
| `app/Actions/CloseProject.php` (modify: D-25) | action | CRUD | itself | exact |
| `app/Rooms/ScheduleAndBooking/ScheduleAndBookingServiceProvider.php` | provider | n/a | `app/Rooms/Money/MoneyServiceProvider.php` | exact |
| `app/Rooms/ScheduleAndBooking/BookingFileType.php` | file type | validation | `app/Rooms/Money/MoneyNotesType.php` | exact |
| `app/Rooms/ScheduleAndBooking/ScheduleSection.php` + `resources/views/filament/rooms/schedule-section.blade.php` | component | request-response | `app/Rooms/Leads/LeadsSection.php` + `leads-section.blade.php` | exact |
| `app/Rooms/ScheduleAndBooking/Availability.php`, `BookingSettings.php` | service | transform | `app/Icm/BusinessClock.php` (modify: make `windowOn` public) | role-match |
| `app/Icm/RoomChecks.php` or `ConnectorChecks.php` (D-36), `FolderSync.php` (D-37) | validation | transform | themselves | exact |
| `bootstrap/providers.php` (modify) | config | n/a | `LeadsServiceProvider` entry | exact |
| `app/Livewire/BookingPage.php` + `resources/views/livewire/booking-page.blade.php` | component | request-response | `app/Livewire/DevChat.php` + `dev-chat.blade.php` | role-match |
| `resources/views/layouts/customer.blade.php` | layout | n/a | `resources/views/layouts/app.blade.php` + `welcome.blade.php` lines 10-14 (Vite guard) | partial |
| `routes/web.php` (modify) + limiter in `AppServiceProvider.php:92` | route/config | request-response | `Route::livewire('/dev/chat')`; `RateLimiter::for('leads')` | exact |
| `app/Filament/Pages/SchedulePage.php` | page | CRUD list | `app/Filament/Pages/BusinessFolder.php` (page shell); table from `ProjectsRelationManager` | partial |
| `app/Filament/Resources/Contacts/RelationManagers/AppointmentsRelationManager.php` | component | CRUD | `ProjectsRelationManager.php` | exact |
| `app/Filament/Resources/Contacts/ContactResource.php` (modify: `canCreate`, add `create` page) + `Pages/CreateContact.php` | resource | CRUD | `ContactResource.php:30` | exact |
| `app/Records/CustomerCard.php` (modify: D-38) | service | transform | itself, 189-232 | exact |
| `examples/example-hvac/rooms/schedule_and_booking/{CONTEXT.md,booking.md}` | config (folder file) | n/a | `examples/example-hvac/rooms/money/{CONTEXT.md,notes.md}` | exact |
| `examples/example-hvac/rules/sending.md`, `agents/front-desk.md` (modify) | config | n/a | themselves | exact |
| `tests/Unit/AppointmentWritesTest.php` | test (architecture) | n/a | `tests/Unit/ProjectWritesTest.php` | exact |
| `tests/Feature/Rooms/ScheduleAndBookingBootTest.php` | test | n/a | `tests/Feature/Rooms/MoneyBootTest.php` (+ `LeadsBootTest.php` for section axe and browser) | exact |
| Booking page feature and browser tests | test | request-response | `tests/Feature/Rooms/LeadsBootTest.php`, `tests/Feature/Filament/ProjectsRelationManagerKeyboardTest.php` | role-match |
| Sends/sweep tests | test | batch | tests next to `SendDueAlerts` / `SendOwnerAlert` | role-match |
| `docs/SPEC.md`, `docs/BLUEPRINT.md` (modify) | docs | n/a | `SpecTables` compares SPEC rows (`tests/Rooms/SpecTables.php`) | exact |

## Pattern Assignments

### Migrations (migration, CRUD)

**Analog:** `database/migrations/2026_10_29_000001_create_projects_table.php` (read in full).

Anonymous-class migration, docblock on `up()` that cites the decision, `foreignId()->constrained()` with explicit `cascadeOnDelete()` or `nullOnDelete()`, string columns for enum-cast values, nullable `timestamp` columns for outcomes, and raw `DB::statement` for a partial unique index with a matching `drop index if exists` in `down()`:

```php
Schema::create('projects', function (Blueprint $table) {
    $table->id();
    $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
    $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
    $table->string('opened_by');
    $table->foreignId('opened_by_user_id')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('opened_at');
    $table->timestamp('closed_at')->nullable();
    $table->timestamps();
    $table->index('contact_id');
});
DB::statement("create unique index projects_one_lead_project_per_contact on projects (contact_id) where opened_by = 'lead'");
```

Apply: appointments store `starts_at` / `ends_at` as `timestamp` (UTC), `state` as string, `service_slug` and `service_name` copied at booking (D-42), nullable `project_id`, and a `kind` of appointment|block if the one-table option (A3, resolved by Ian) is used. `booking_sends` gets `unique(appointment_id, lead_minutes)` plus the claim columns `due_at`, `send_claimed_at`, `sent_at`, `skipped_at`, `failed_at`, `failure`. `booking_links` stores `token_hash` (unique), never the token. Money rule: no money here. Partial unique indexes must work on SQLite and Postgres (as projects does).

### Models and factories (model, CRUD)

**Analog:** `app/Models/OwnerAlert.php` lines 1-58 and `database/factories/OwnerAlertFactory.php` lines 1-45.

```php
#[Fillable(['conversation_id','message_id','user_id','reason','urgent','due_at','to_phone','body','sent_at','skipped_at','failed_at','failure','provider_message_id','dedupe_key'])]
class OwnerAlert extends Model
{
    /** @use HasFactory<OwnerAlertFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['reason' => AlertReason::class, 'due_at' => 'datetime', 'sent_at' => 'datetime', 'send_claimed_at' => 'datetime'];
    }
```

Rules to copy: claim columns (`send_claimed_at`) are NOT in `#[Fillable]` because only the conditional update writes them (OwnerAlert.php docblock). Factory `definition()` returns related factories (`Conversation::factory()`) and named states such as `claimed()`. Add the model nicknames to the morph map in `AppServiceProvider.php` (the `Relation::enforceMorphMap` list ending near line 85-90: `'owner_alert' => OwnerAlert::class, 'project' => Project::class`), or via the Room's `morphMap` as the Leads provider does with `parent::boot()`. Add the factories (Boost: factories for new models).

### Appointment actions (action, CRUD, transactional)

**Analog:** `app/Actions/CloseProject.php` lines 17-58 (read in full), with `app/Actions/OpenProject.php` lines 31-47 and 85-108 for opening a project.

Core shape to copy: one public `handle()`, a `DB::transaction`, re-read the row under `lockForUpdate()` and refresh, return early when it is already in the target state (idempotent, no second timeline entry), write the row, write exactly one timeline entry via `$contact->activities()->create([...])` with a fixed platform sentence, and dispatch `CustomerRecordChanged` after the transaction only when something changed:

```php
$closed = DB::transaction(function () use ($project, $user): bool {
    Project::query()->lockForUpdate()->find($project->id);
    $project->refresh();
    if ($project->closed_at !== null) { return false; }
    $project->update([...]);
    $project->contact->activities()->create([
        'type' => ActivityType::ProjectClosed,
        'summary' => 'Closed by the owner',
        'subject_type' => $project->getMorphClass(),
        'subject_id' => $project->id,
        'occurred_at' => $closedAt,
    ]);
    return true;
});
if ($closed) { CustomerRecordChanged::dispatch($project->contact_id); }
```

Apply to Cancel, MarkDone (only once start has passed, D-14), EditVisitNote, BlockTime, RemoveBlock (blocks write no timeline entry per research). Record who acted (user id or customer link). Research Pattern 9 says room entries should go through `app/Rooms/RecordRoomEntry.php` so a removed room goes quiet; read that class before choosing between it and `activities()->create`.

**BookAppointment specifics.** Copy the lock idiom from `OutboundSender.php:89` (`Cache::lock("reply:{$conversation->id}", 30)` then `$lock->block(...)`, release in `finally`) for `Cache::lock('schedule:book', 10)->block(5)` wrapped around a `DB::transaction` that re-runs the overlap check and inserts (research Pattern 4). To open a project it must call `OpenProject` (not write projects itself), and see Pitfall 10: do not `use App\Models\Project;` in any file that calls `->update(`/`->save(`; query via `$contact->projects()` instead. Add a `ProjectActor` case for a customer booking and a third public method on `OpenProject` beside `byHand()` / `forNewLead()`. `OpenProject::forNewLead` (lines 71-75) shows the savepoint-plus-`UniqueConstraintViolationException` pattern to reuse for any unique-index race:

```php
try {
    return DB::transaction(fn (): Project => $this->open($contact, ProjectActor::Lead, null, $conversationId));
} catch (UniqueConstraintViolationException) {
    return null;
}
```

`OpenProject::open()` line 101 has the binary ternary `$openedBy === ProjectActor::User ? 'Opened by the owner' : 'Opened by a new lead'`. This must become a `match` with an arm for the new actor and no default (research Pitfall 4). The same fix applies at `CustomerCard.php:200`, `ProjectsRelationManager.php:79`, `LeadsSection.php:110`.

### CloseProject (modify, D-25)

Add one early check inside the existing locked transaction, before `$project->update`, that throws a `DomainException` naming the booked visit when the project has a booked appointment ahead (same plain-words DomainException style `OutboundSender` uses). The relation manager's close action turns it into a Filament notification (see `ProjectsRelationManager` use of `DomainException` and `Notification`). List the existing `CloseProject` tests to re-point in the plan.

### Confirmation and reminder send (service, event-driven claim)

**Analog:** `app/Actions/SendOwnerAlert.php` lines 32-183 and `app/Jobs/SendDueAlerts.php` lines 28-58.

Copy the claim by conditional update verbatim in shape (SendOwnerAlert.php 155-183):

```php
$claimed = OwnerAlert::query()
    ->whereKey($alert->id)
    ->whereNull('sent_at')->whereNull('skipped_at')->whereNull('failed_at')
    ->where(function (Builder $query): void {
        $query->whereNull('send_claimed_at')
            ->orWhere('send_claimed_at', '<=', now()->subMinutes(self::CLAIM_WAIT_MINUTES));
    })
    ->update(['send_claimed_at' => $claimedAt]);
// $claimed !== 1 means another sender has it
```

Reuse `SendOwnerAlert::CLAIM_WAIT_MINUTES` (the constant, 2) rather than refactoring the shared class. Also copy `end()` (194-210: one conditional update that sets exactly one of `sent_at` / `skipped_at` / `failed_at`, returns whether this caller ended it, never throws) and `skip()` (96-128). Error handling: `catch (Throwable $exception) { report($exception); ... }` and store a trimmed failure string (`reasonFrom()` 134-139). Differences for booking sends: the text goes through `OutboundSender::send()`, not `TextChannel` (06 D-09; only `OutboundSender` and `SendOwnerAlert` may use `TextChannel`), and a refusal writes one timeline note (D-27) and no owner alert.

**Sweep job:** copy `SendDueAlerts` lines 28-58 (`implements ShouldQueue`, `use Queueable`, `lazyById()->each()`, the same `whereNull` outcome filters and the claim-wait `where`). Add as first statement of `handle()`: `if (! app(ActiveConfig::class)->hasRoom('schedule_and_booking')) { return; }`.

**Scheduler entry:** `routes/console.php` line 18 style, with a one-line comment above it:

```php
// Sends owner alerts held until opening time, unless their draft was handled first (D-20).
Schedule::job(new SendDueAlerts)->everyMinute()->withoutOverlapping();
```

### OutboundSender changes (service chokepoint, D-21, D-26)

**Analog:** itself. `whyBlocked()` lines 138-168 is where D-21 goes: opt-out first (143-147), closed (149-151), paused for Automated only (153-155), no phone (157-165). Add the "has texted or switch on" refusal for `SendOrigin::Automated` after the opt-out check, using the same shape and plain-words string return:

```php
if ($origin === SendOrigin::Automated && $conversation->isPaused()) {
    return 'The assistant is paused in this conversation.';
}
```

`deliver()` lines 193-205 is where the new kind branches in, ahead of `refuseWithoutReading()`:

```php
$refusal = $this->whyBlocked($conversation, $origin) ?? $this->whyBodyIsRefused($body);
if ($refusal !== null) { throw new DomainException($refusal); }
if ($origin === SendOrigin::Automated) {
    $this->refuseWithoutReading($conversation, $attributes);
}
```

The new booking-text kind needs its own explicit branch (not the `default => []` in `partsOf()` at ~398): require `meta.appointment_id`, booked and ahead, no `model_words` part, and no unhandled customer message (`handled_at` null, research Pitfall 11), and skip `gate.customer_reading` for that kind only. Add a `booking_link` arm to `whyReadingBlocks()`. `DomainException` is the refusal type; `alertAboutRefusal` is not used for this kind. Existing tests that send Automated texts to a never-texted factory contact must be listed by file and line (research Pitfall 9 gives the starting set); add a `textedBusinessBefore($contact)` helper in `tests/Pest.php` rather than editing assertions.

### AlertReason (enum, 2 cases)

**Analog:** `app/Enums/AlertReason.php` lines 8-30: backed string enum, `values()` feeds the Validator's `owner_alert.right_away.*` `Rule::in`, and `plainWords()` is a `match ($this)` with no default.

```php
case CheckIn = 'check_in';
...
public function plainWords(): string
{
    return match ($this) {
        self::DraftWaiting => 'Draft waiting',
        self::Complaint => 'Complaint',
```

Add `AppointmentBooked` and `ChangeRequest` cases with `plainWords()` arms. Dedupe key stays `OwnerAlert::dedupeKey()` (`OwnerAlert.php:65-68`, `'c'.$conversationId.':m'.($messageId ?? 0).':'.$reason->value.':u'.($userId ?? 0)`). Never raise the booked alert with a null message id (key `m0` would drop a second booking, 02.1 IN-11). Also edit `examples/example-hvac/rules/sending.md`: change-request under `right_away`, booked left off.

### Room provider, file type, section (copy Money, Leads)

**Provider analog:** `app/Rooms/Money/MoneyServiceProvider.php` lines 13-24:

```php
class MoneyServiceProvider extends RoomServiceProvider
{
    protected function room(): Room
    {
        return new Room(
            key: 'money',
            label: 'money',
            fileTypes: [new MoneyNotesType],
            sections: [MoneySection::class],
        );
    }
}
```

For this room pass `key: 'schedule_and_booking'`, `fileTypes: [new BookingFileType]`, `sections: [ScheduleSection::class]`, `tables: [...]`, `timelineEntries: [...]`, `connector: none`. If the room needs a boot hook, copy `LeadsServiceProvider::boot()` (lines 40-45: call `parent::boot()` first; `register()`/`boot()` must read nothing from ActiveConfig, DB or Filament). Register the provider in `bootstrap/providers.php` after `LeadsServiceProvider::class`; `AppServiceProvider` stays first.

**File type analog:** `app/Rooms/Money/MoneyNotesType.php` lines 13-66. Implements `RoomFileType`: `type()` returns the type string, `rules(IcmFile $file): array` returns Validator-style rules (empty for Money; Booking returns real rules for step, duration-related keys, buffer, days ahead, link expiry days, reminder lead list, send window), `checks(IcmFile $file): array` returns a list of `['file' => $file->path, 'field' => ..., 'message' => ...]` (lines 43-47). Put the `{link}`-exactly-once and no-exclamation/em-dash checks (D-03, D-28) in `checks()`. `singleFileLabel()` / `singleFileDefaultPath()` (54-64) give one booking file per business (`rooms/schedule_and_booking/booking.md`); the `RoomChecks` check refuses a second file of that type.

**Section analog:** `app/Rooms/Leads/LeadsSection.php` lines 19-60: extends `RoomSection`, `protected string $view = 'filament.rooms.leads-section'`, `roomKey()`, `contentLines()` for the empty line (name the file that turns the rest on, D-03), a `rows` payload with a link to the contact and an absolute date inside a `<time>` element, a cap constant (`SHOWN = 20`), and a throw during row building is reported with the section's failure line so the dashboard still loads (05 D-32). Mirror the view in `resources/views/filament/rooms/leads-section.blade.php`.

**Sync checks.** D-36 (pure): add beside `ConnectorChecks::folderErrors()`/`RoomChecks::errors()`: room present and any `type: connector` file owns `OwnableKind::Schedule`; helper `ConnectorChecks::kinds($file, 'owns')`. D-37 (needs DB, first of its kind): in `FolderSync::sync()` after `$this->validator->validate($folder)` and before the reject decision; only when a live version exists. Keep `Validator` untouched.

### Availability and BusinessClock (service, transform)

**Analog:** `app/Icm/BusinessClock.php` (open file before planning; `windowOn()` is private, make it public or add `openWindowOn(CarbonInterface)`; `isOpenAt`, `nextOpening`, `DAYS_AHEAD = 13`). Config reads through `ActiveConfig` only (`hours()`, `service($slug)`, `timezone()`/`displayTimezone()`, `filesOfType()`). Research Pattern 3 gives the algorithm and verified DST behavior; inject `$now`. Overlap SQL is portable: `existing.starts_at < :newEnd + buffer AND existing.ends_at > :newStart - buffer` with PHP-computed UTC bounds.

### BookingPage Livewire component (component, request-response)

**Analog:** `app/Livewire/DevChat.php` lines 1-60 (the only customer-style full-page Livewire component): `namespace App\Livewire`, `#[Title('...')]`, `Livewire\Component`, action methods type-hint their dependencies (`send(RecordInbound $recordInbound, ActiveConfig $activeConfig)`), `$this->validate([...], [messages])`, `$this->addError('field', 'plain words')`, `abort_unless(...)` in `mount()`.

Differences: `#[Locked] public string $token`, `#[Layout('layouts.customer')]`, rate limit inside the methods with `RateLimiter::attempt(...)` (research Pitfall 1), never trust a client slot (recompute from `Availability`), keep the picker in the DOM hidden while the check step shows (Pitfall 6), calls `BookAppointment` only (never `OpenProject`, Pitfall 10 arch rule).

**Route analog:** `routes/web.php` lines 34-36:

```php
Route::livewire('/dev/chat', DevChat::class)
    ->middleware(EnsureLocalEnvironment::class)
    ->name('dev.chat');
```

and the limiter: `routes/web.php:30-32` (`->middleware('throttle:leads')`) with `RateLimiter::for('leads', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));` in `AppServiceProvider.php` (about line 92). Register `booking` next to it. A small noindex/referrer-policy middleware follows the `EnsureLocalEnvironment` style in `app/Http/Middleware/`.

**Layout analog:** `resources/views/layouts/app.blade.php` is a bare layout (`$slot`, inline style) and is the shell to extend. The `@vite` guard to copy is `resources/views/welcome.blade.php` lines 10-14 (`@if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))`). Add `npm run build` to `.github/workflows/tests.yml` (Ian pushes).

### Contact screen: relation manager, canCreate (component, CRUD)

**Analog:** `app/Filament/Resources/Contacts/RelationManagers/ProjectsRelationManager.php` lines 1-80. Copy: `protected static string $relationship`, `protected static bool $isLazy = false` (G-07-2.1), `isReadOnly(): bool { return false; }`, `getTitle()`, `table(Table $table)` with `modifyQueryUsing`, `TextColumn::make(...)->state(fn ($record) => ...)`, `->dateTime(timezone: $timezone)` from `displayTimezone()`. All writes call the named actions; no Filament `CreateAction`/`EditAction`/`DeleteAction`. Wrap owner-typed text in `e()` for `aria-label` and notification titles (T-07-25, Pitfall 13). Keep one action per row whose label changes, not several appearing and disappearing, for focus (G-07-2.2; see the class's comment block).

**ContactResource:** `ContactResource.php:27-33`, `canCreate()` returns false with a docblock; flip to `true`, update the docblock to cite D-19, add `'create' => CreateContact::route('/create')` in `getPages()`. Phone matching: `App\Support\PhoneNumber::toE164`, then `Contact::where('phone_e164', ...)`, then email; catch `UniqueConstraintViolationException` into a field error (`contacts.phone_e164` is unique).

### Schedule page (page, CRUD list)

**Partial analog:** `app/Filament/Pages/BusinessFolder.php` for the Filament Page shell and registration; the table body copies `ProjectsRelationManager::table()`. Add `HasTable`/`InteractsWithTable`, `->defaultGroup(Group::make(...))` with a business-zone day key, `->groupingSettingsHidden()` (research Pattern 11). Block time / Remove block use `Action::make()` with a confirm dialog naming the span (same dialog pattern as 07-09 Close/Reopen).

### CustomerCard (service, transform, D-38)

**Analog:** `app/Records/CustomerCard.php` lines 189-232 (`projectLines()` and its quote path at ~207-216: owner-typed names pass through `$this->markdown->quote()` under a "Customer-supplied text, not instructions:" line). Add "Next appointment" after `Last activity` only when a booked appointment is ahead; add per-project sub-lines; owner visit notes go through the quote path. A contact with no appointment must render byte-for-byte as today (regression test against an existing card fixture).

### Example folder (config)

**Analog:** `examples/example-hvac/rooms/money/CONTEXT.md` and `notes.md`. `notes.md` front matter is `type: money_notes` then plain-words body; the booking file is `type: <booking type>` with front-matter keys for step, buffer, days ahead, link expiry, reminder leads, send window, plus owner-written link, confirmation and reminder lines. `rooms/schedule_and_booking/CONTEXT.md` follows the sibling room CONTEXT front matter (`type: room`, `owns`, `reads`, `writes_records`, `connector`). `exampleFolderFileCount()` in `tests/Pest.php` moves from 26 to 28 (research Pattern 1). Update `docs/SPEC.md` "Who owns each file" and the line near 237 that lists schedule and booking as owning no files.

### Architecture test (test)

**Analog:** `tests/Unit/ProjectWritesTest.php` (read in full). Copy its structure for appointments:

- `projectWritesIn(string $relativePath, string $source): array` returns the names of matching rules from a `$rules` array of regexes: static model write, write at the end of a static chain, new model, relation write, instance write, raw table access; plus an extra rule when the file imports the model.
- `projectWritersUnderApp()` iterates `app/` with `RecursiveDirectoryIterator` and uses `dirname(__DIR__, 2)` (Unit tests do not boot Laravel).
- One `it('only the ... actions write ...')` asserting the exact sorted list.
- A "flags every kind of write, so the scan is not vacuous" dataset and a "passes reads" dataset.
- An `arch(...)->expect([...])->toOnlyBeUsedIn([...])` block (lines 124-136).

Name function(s) distinctly (`appointmentWritesIn`, not `projectWritesIn`) since Pest functions are global. Extend the existing arch rule's `toOnlyBeUsedIn` only deliberately: `OpenProject` and `CloseProject` may be called from `App\Actions` (so `BookAppointment` is fine), `App\Filament\Resources\Contacts\RelationManagers` and `App\Rooms\Leads`; `App\Livewire\BookingPage` and `App\Rooms\ScheduleAndBooking` may not.

### Room boot test (test)

**Analog:** `tests/Feature/Rooms/MoneyBootTest.php` lines 1-50: `beforeEach` `actingAs(User::factory()->create())`, a dataset `it('boots the ...', function (bool $empty) { ... })` that deletes the room's file for the empty case (`File::delete(icmPath(...))`), `activateExampleFolder()`, `app(RoomRegistry::class)->get('<key>')`, assertions on label, handles, fileTypes, slot null, tables, timelineEntries, morphMap, `roomContract()` compared with `Tests\Rooms\SpecTables`, the section content, and the axe check via `Tests\Rooms\RoomSectionAxe`. No driver guard and no skip (CI runs SQLite and Postgres). This room asserts real `tables` and `timelineEntries`. For browser keyboard tests use `tests/Feature/Filament/ProjectsRelationManagerKeyboardTest.php` and `tests/Feature/Rooms/LeadsBootTest.php`, with Playwright locator waits only (`waitForFunction`/`waitForURL` never wait in pest-plugin-browser 5.1).

## Shared Patterns

### Exactly-once claim by conditional update
**Source:** `app/Actions/SendOwnerAlert.php` 155-183 and 194-210; sweep in `app/Jobs/SendDueAlerts.php` 37-58.
**Apply to:** `SendBookingText`, `SendDueBookingTexts`, the post-commit immediate confirmation (the sweep is the backstop; both race on the same claim).

### One timeline entry per action, fixed platform wording, event after commit
**Source:** `app/Actions/CloseProject.php` 28-57; `app/Actions/OpenProject.php` 99-105.
**Apply to:** all six appointment actions, the booking-text sent/not-sent notes (D-27), `SetAutomaticTexts`. Wording has no exclamation points or em dashes (02.1 D-41).

### Single outbound chokepoint
**Source:** `app/Actions/OutboundSender.php::send()` 87-98 (lock `reply:{id}`), `whyBlocked()` 138-168, `deliver()` 193-205.
**Apply to:** the link text (combined text, via `SendCombinedReply`), confirmations and reminders. `TextChannel` is used only here and in `SendOwnerAlert`. Refusals are plain-words `DomainException`.

### Locks
**Source:** `OutboundSender.php:89` (`Cache::lock(...)`, `block(...)`, release in `finally`); `FolderSync` uses `Cache::lock('icm:sync', 120)`.
**Apply to:** `BookAppointment` (`Cache::lock('schedule:book', 10)`), plus a transaction re-check. `lockForUpdate()` is a no-op on SQLite (research Pattern 4), so it cannot be the only guard.

### Config only through ActiveConfig
**Source:** `.ai/rules/app.md`; `ActiveConfig::hasRoom()`, `displayTimezone()`, `hours()`, `service()`, `filesOfType()`.
**Apply to:** `Availability`, `BookingSettings`, the sweep, the page, the card lines, sync-time code outside `Loader`.

### Writes to a table through named actions only, enforced by a source-scan test
**Source:** `tests/Unit/ProjectWritesTest.php`.
**Apply to:** appointments/blocks (new test) and the unchanged project test (keep it passing: files that import `App\Models\Project` may not call `->update(`/`->save(`).

### Owner-typed text neutralized
**Source:** `CustomerCard.php` quote path (~207-216); `ProjectsRelationManager::rowButtonName()` wraps with `e()`.
**Apply to:** visit note, block note, service names in `aria-label`, notification titles, schedule rows.

### Models: claim columns not fillable, casts via `casts()`, factories with states
**Source:** `app/Models/OwnerAlert.php`, `database/factories/OwnerAlertFactory.php`.
**Apply to:** `BookingSend`, `BookingLink` (hash column never exposed), `Appointment`.

### Sync errors shape
**Source:** `MoneyNotesType::checks()` 43-47: `['file' => $file->path, 'field' => null|string, 'message' => 'plain words']`.
**Apply to:** booking file checks, D-36, D-37.

## No Analog Found (use RESEARCH.md patterns)

| File | Role | Data Flow | Reason |
|---|---|---|---|
| `resources/views/layouts/customer.blade.php` and the public booking Blade partials | layout/view | request-response | No customer-facing page exists yet (CONTEXT code_context). Start from `layouts/app.blade.php` and the `welcome.blade.php` Vite guard; follow `08-UI-SPEC.md` |
| `Availability` (slot generation, DST-safe) | service | transform | `BusinessClock` supplies windows, not slots or overlap counts; algorithm and verified DST facts are in RESEARCH Pattern 3 |
| `MintBookingLink` / token hashing and state resolution | action | CRUD | No bearer-token table exists; use RESEARCH Pattern 2 (`hash('sha256', ...)` of a high-entropy token, unique `token_hash`). `RaiseOwnerAlert` is only a store-once analog for `createOrFirst` |

## Existing tests the plans must sweep (named by file, line fixed after a run)

Starting points from RESEARCH Pitfall 4 and 9 (verify by running, not by this list): tests that send Automated texts to a never-texted factory contact (`tests/Feature/FrontDesk/{AnsweredDraftTest,FrontDeskFailureTest,FrontDeskInvariantTest,HoldingReplyTest,LifeSafetyTest,ProcessInboundTest,ReadingRequiredToSendTest,TakeOverTest}.php`, `tests/Feature/Inbound/OptOutTest.php`, `tests/Unit/ArchitectureTest.php`); direct `whyBlocked()` assertions at `ProcessInboundTest.php:399-401`, `TakeOverTest.php:140-141`, `OptOutTest.php:158`; tests touching `ProjectActor` (`ProjectCardTest`, `ProjectsRelationManagerTest`, `LeadsSectionTest`, `ProjectActionsTest`, `ProjectRecordTest`, `OpenOnNewLeadTest`); `exampleFolderFileCount()` consumers (every `*BootTest`); `CloseProject` tests; `RunFrontDesk` alert-order tests; card tests; `BusinessClockTest`.

## Metadata

**Analog search scope:** `app/Actions`, `app/Jobs`, `app/Livewire`, `app/Models`, `app/Enums`, `app/Rooms`, `app/Filament`, `app/Icm`, `app/Records`, `routes`, `resources/views`, `database/migrations`, `database/factories`, `tests/Unit`, `tests/Feature/Rooms`, `tests/Rooms`, `examples/example-hvac/rooms`
**Files read in full or by range:** about 20 (plus CONTEXT.md in full and RESEARCH.md lines 1-532 of 771; the tail of RESEARCH.md past line 532, including the Validation Architecture, was not read here)
**Pattern extraction date:** 2026-11-02
