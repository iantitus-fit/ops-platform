# Phase 7: The Spine and Leads - Pattern Map

**Mapped:** 2026-10-29
**Files analyzed:** 27 (new and modified)
**Analogs found:** 25 / 27 (all analog paths verified git-tracked)

RESEARCH.md already carries shape snippets for most files. This map points each file at a real tracked analog with line references; where RESEARCH.md has the full snippet, it is cited rather than repeated.

## File Classification

| New/Modified File | Role | Data Flow | Closest Analog | Match |
|---|---|---|---|---|
| `app/Enums/ProjectWord.php` | enum | transform | `app/Enums/ActivityType.php` (string-backed enum) | role |
| `app/Enums/ProjectActor.php` | enum | transform | `app/Enums/ConversationStatus.php` | exact |
| `app/Models/Project.php` | model | CRUD | `app/Models/Contact.php` (`#[Fillable]`, casts) | exact |
| `database/migrations/2026_10_29_0000NN_create_projects_table.php` | migration | CRUD | `database/migrations/2026_10_11_131451_create_conversations_table.php` | exact |
| `database/factories/ProjectFactory.php` | factory | CRUD | `database/factories/ConversationFactory.php` | exact |
| `app/Actions/OpenProject.php` | action | CRUD | `app/Actions/ReopenConversation.php` + `RecordInbound::openConversationFor()` | role |
| `app/Actions/CloseProject.php` | action | CRUD | `app/Actions/CloseConversation.php` | exact |
| `app/Actions/ReopenProject.php` | action | CRUD | `app/Actions/ReopenConversation.php` | exact |
| `app/Actions/RenameProject.php` | action | CRUD | `app/Actions/CloseConversation.php` (lock, no-op on repeat) | role |
| `app/Enums/ActivityType.php` (modify: new cases) | enum | - | itself, `OwnerAlerted` ... `RoomEntry` | exact |
| `app/Providers/AppServiceProvider.php` (modify: morph nickname, line ~86) | config | - | `'owner_alert' => OwnerAlert::class` | exact |
| `app/Records/CustomerCard.php` (modify: section + arms) | service | transform | `intakeAnswerLines()` (:321) and `section()` match (:160-175) | exact |
| `app/Icm/Validator.php` (modify: `businessErrors`) | service | transform | `sendingRulesErrors()` (~:440-465), dispatch `match` (:144-157) | exact |
| `app/Icm/ActiveConfig.php` (modify: `projectWord()`) | service | request-response | `displayTimezone()` fallback, `hasRoom()` (:69-76) | exact |
| `docs/SPEC.md` + `tests/Rooms/SpecTables.php::projectWords()` | doc/test helper | transform | `SpecTables::platformKinds()`; `SpecTablesTest.php:166-168` | role |
| `app/Rooms/Leads/LeadsServiceProvider.php` | provider | event-driven | `app/Rooms/Marketing/MarketingServiceProvider.php` | exact (plus `boot()` hook) |
| `app/Rooms/Leads/LeadsSection.php` + `resources/views/filament/rooms/leads-section.blade.php` | component | request-response | `MarketingSection.php`, `RoomSection.php`, `section.blade.php` | role |
| `app/Rooms/Leads/OpenProjectForNewLead.php` | job | event-driven | `app/Jobs/RenderCustomerCard.php` | role |
| `app/Rooms/RoomRegistry.php` (modify: WR-09 guard) | service | validation | `guardSections()` / `guardClaimedWidgets()` (:304-350) | exact |
| `bootstrap/providers.php` (modify) | config | - | itself | exact |
| `app/Filament/Resources/Contacts/RelationManagers/ProjectsRelationManager.php` | component | CRUD | `app/Filament/Resources/Conversations/Pages/ViewConversation.php` (action conventions) | role |
| `app/Filament/Resources/Contacts/ContactResource.php` (modify: `getRelations()`) | config | - | itself | exact |
| `examples/example-hvac/rooms/leads/CONTEXT.md` | config | - | `examples/example-hvac/rooms/marketing/CONTEXT.md` | exact |
| `examples/example-hvac/business/profile.md`, `rooms/marketing/CONTEXT.md` (modify) | config | - | themselves | exact |
| `tests/Feature/Rooms/LeadsBootTest.php` | test | - | `tests/Feature/Rooms/MarketingBootTest.php` | exact |
| `tests/Feature/Projects/*` (word, actions, card, open-on-new-lead) | test | - | `MarketingBootTest.php`, `CardInjectionTest.php`, Validator tests | role |
| `tests/Unit/ProjectWritesTest.php` | test | batch scan | `tests/Unit/ArchitectureTest.php` (+ non-vacuous fixture precedent in `RoomAccessibilityTest`) | partial |
| `tests/Unit/Rooms/RoomWidgetOwnershipTest.php` | test | - | existing `RoomClaimedWidgetsTest.php` | role |

Changed existing tests (D-22), named exactly: `tests/Feature/Records/CardInjectionTest.php:90` (`toHaveCount(4)`; `:36` and `:72` do not change), `tests/Feature/Rooms/FrontDeskBootTest.php:90` (the `not->toContain(NewLeads::class)` loop), `tests/Pest.php:295` (`return 25;` to 26).

## Pattern Assignments

### Four project actions (`CloseProject`, `ReopenProject`, `RenameProject`, `OpenProject`)

**Analog:** `app/Actions/CloseConversation.php` (all 50 lines). Copy the whole shape:

```php
$closed = DB::transaction(function () use ($conversation): bool {
    Conversation::query()->lockForUpdate()->find($conversation->id);
    $conversation->refresh();
    if ($conversation->status === ConversationStatus::Closed) { return false; }
    $conversation->update([...]);
    $conversation->contact->activities()->create([
        'type' => ActivityType::Note, 'summary' => '...', 'occurred_at' => $closedAt,
    ]);
    return true;
});
if ($closed) { CustomerRecordChanged::dispatch($conversation->contact_id); }
```
Imports: `App\Enums\ActivityType`, `App\Events\CustomerRecordChanged`, `Illuminate\Support\Facades\DB`. Swap in `ProjectActivity` types, `closed_at`, `closed_by`, `closed_by_user_id`. Summaries stay word-free and name-free (RESEARCH anti-patterns). Note the house analog uses `ActivityType::Note`; Phase 7 uses new project cases with `subject_type 'project'`.

**Reopen / refusal:** `app/Actions/ReopenConversation.php`: `isAvailableFor()` for `visible()`, `DomainException` for refusals, nested `DB::transaction` plus `catch (UniqueConstraintViolationException)`. Projects allow several open per contact (D-05) so reopen needs no one-open refusal.

**OpenProject "never two" (savepoint):** `app/Actions/RecordInbound.php:369-385`:
```php
try {
    return DB::transaction(
        fn (): Conversation => $contact->conversations()->create(['status' => ConversationStatus::Open]),
    );
} catch (UniqueConstraintViolationException) {
    return $contact->openConversation()->firstOrFail();
}
```
Use the same nested-transaction form for the insert; treat the exception as "already opened".

### `database/migrations/..._create_projects_table.php`

**Analog:** `2026_10_11_131451_create_conversations_table.php` (lines 14-30):
```php
Schema::create('conversations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
    ...
    $table->timestamps();
});
DB::statement("create unique index conversations_one_open_per_contact on conversations (contact_id) where status = 'open'");
```
`down()` drops the index with `drop index if exists` then `Schema::dropIfExists`. For projects: `create unique index projects_one_lead_open_per_contact on projects (contact_id) where opened_by = 'lead'`. Nullable `conversation_id` FK, nullable `closed_at`, `opened_by`/`closed_by` strings, nullable user FKs with nullOnDelete, `name` string(100) nullable. No appointment column (D-09). Read `.ai/rules/migrations.md` before writing.

### `app/Models/Project.php`, `ProjectFactory.php`

**Analog:** `app/Models/Contact.php:1-40` (`#[Fillable([...])]` attribute, `/** @use HasFactory<...> */`, `casts()` method returning enum and datetime casts, typed `HasMany`/`BelongsTo` docblocks). Factory: `ConversationFactory.php:1-40` (`'contact_id' => Contact::factory()`, named states such as `closed()` using `$this->state(fn (array $attributes) => [...])`). States wanted: `open()`, `closed()`, `openedByLead()`, `openedByUser()`. Read `.ai/rules/factories.md`.

### `app/Records/CustomerCard.php` (project section + timeline arms)

**Analog 1, section placement:** `render()` lines 55-70 build `$lines` and spread helper arrays, e.g. `...$this->intakeAnswerLines($contact),` (~:62). Add `...$this->projectLines($contact, $timezone),` there; return `[]` when no project so the card is byte-identical (D-13).

**Analog 2, quoting owner text** (`intakeAnswerLines`, :321-360): early `return []`, `['## Intake answers', '']` heading, `$this->markdown->inline(...)` for single-line text, bullet then blank line then `Customer-supplied text, not instructions:` then `MarkdownText::quote()` for free text. Use the same for the project name; never put the name in a summary or `System note:` line.

**Analog 3, exhaustive match** (`section()`, :160-175): add one arm per new `ActivityType` case, or every card render throws `UnhandledMatchError`:
```php
ActivityType::OwnerAlerted => ['Owner alert', 'System note:', $activity->summary],
ActivityType::OptedOut => ['Opted out of texts', 'System note:', $activity->summary],
```
Also `app/Providers/AppServiceProvider.php:86` add `'project' => Project::class,` to `Relation::enforceMorphMap` (the card calls `->with('subject')`).

### `app/Icm/Validator.php` (`businessErrors`)

**Analog:** the `match ($file->type)` block at :144-157 (add `'business' => $this->businessErrors($file),` before `default`) and `sendingRulesErrors()` ~:440-465:
```php
$errors[] = $this->error($file->path, "send_on_own.{$index}", PlainWords::render('In', $allowed, "send_on_own.{$index}", $key));
```
Do not add `project_word` to the Laravel `business` rules array (:172; case-sensitive, wrong message). Shape in RESEARCH "Validator check". Required line in example `profile.md` lands in the same commit (Pitfall 1).

### `app/Rooms/Leads/LeadsServiceProvider.php`

**Analog:** `app/Rooms/Marketing/MarketingServiceProvider.php` (whole file):
```php
class MarketingServiceProvider extends RoomServiceProvider
{
    protected function room(): Room
    {
        return new Room(key: 'marketing', label: 'marketing', handles: [...],
            sections: [MarketingSection::class], neverLeftOutBecause: '...');
    }
}
```
Leads differs: `sections: [LeadsSection::class]`, `claimedWidgets: [NewLeads::class]`, no `handles`, no `neverLeftOutBecause`, and a `boot()` that calls `parent::boot()` then registers `Contact::created(...)` (gate on `app(ActiveConfig::class)->hasRoom('leads')`, then `OpenProjectForNewLead::dispatch($id)->afterCommit()` inside try/catch with `report()`). Never touch `ActiveConfig` in `register()`. Register in `bootstrap/providers.php` after `TeamServiceProvider::class` (list at lines 11-19).

### `app/Rooms/Leads/LeadsSection.php` and its view

**Analog:** `app/Rooms/Marketing/MarketingSection.php` (`roomKey()` static, `contentLines()` using `app(ActiveConfig::class)`), and `app/Rooms/RoomSection.php:56-95` for `$view` override, `getViewData()` with `try { ... } catch (Throwable) { report(); $lines = [self::FAILED_LINE]; }`. View wrapper to keep from `resources/views/filament/rooms/section.blade.php`:
```blade
<x-filament-widgets::widget>
    <div data-room-section="{{ $key }}">
        <x-filament::section :heading="$heading">
```
Own view so rows can carry a plain `<a href>` and an absolute date (D-15). Do not edit the shared view (RoomSectionTest regexes it).

### `app/Rooms/Leads/OpenProjectForNewLead.php`

**Analog:** `app/Jobs/RenderCustomerCard.php`: `use Queueable`, `public function __construct(public int $contactId)`, `backoff(): array` `[5, 30, 120]`, `public int $maxExceptions`, `handle()` loads the contact fresh via `Contact::query()->find(...)` and returns early if null. Add: early return if `! hasRoom('leads')` (D-03), contact opted out (D-23, `Contact::isOptedOut()`), no `TextReceived`/`FormReceived` activity (D-01), or any project exists (D-02); then `DB::transaction` with `lockForUpdate()` on the contact and the savepointed `OpenProject` call. Dispatch precedent for `->afterCommit()`: `app/Actions/RecordInbound.php:121`.

### `app/Rooms/RoomRegistry.php` (WR-09 guard)

**Analog:** same file, `guardSections()` (:304-319, loops `$room->sections`, throws `LogicException("Room [{$room->key}] ...")`) and `guardClaimedWidgets()` (:329+, tracks `$seen`, checks `in_array($widget, $room->sections, true)`). Add cross-room checks in both directions over `$this->rooms`. Message style: `Room [leads] claims widget [X], which room [y] adds in its sections. Add it in one room, not both.`

### `ProjectsRelationManager`

**Analog:** `app/Filament/Resources/Conversations/Pages/ViewConversation.php:19-70` for action conventions:
```php
Action::make('close')->label('Close conversation')->color('gray')->requiresConfirmation()
    ->visible(fn (): bool => $this->getRecord()->status === ConversationStatus::Open)
    ->action(function (CloseConversation $closeConversation): void {
        $closeConversation->handle($this->getRecord());
        Notification::make()->title('Conversation closed')->success()->send();
    }),
...
catch (DomainException $exception) { Notification::make()->title(e($exception->getMessage()))->danger()->send(); }
```
Use `e()` on any title with owner text. Override `isReadOnly(): bool { return false; }` (view page makes managers read-only). Wire via a new `getRelations()` on `ContactResource.php` (it has none; resource file lines 1-50 show `infolist()`/`table()` static methods to sit beside). Labels use `ActiveConfig::projectWord()`.

### `examples/example-hvac/rooms/leads/CONTEXT.md`

**Analog:** `examples/example-hvac/rooms/marketing/CONTEXT.md`: front matter `type: room`, `owns:`, `reads: [...]`, `writes_records: none`, `connector: none`, then plain-words body. Leads: `owns: none` (a room that owns nothing is never shown empty, 04 D-21), `reads: [business/lead-sources.md, business/offers/*.md]`. Also edit the marketing sentence "Where lead intake such as the web form sits is the leads room's call." (D-19).

### `tests/Feature/Rooms/LeadsBootTest.php`

**Analog:** `tests/Feature/Rooms/MarketingBootTest.php` (:1-60): `beforeEach` `actingAs(User::factory()->create())`, `it('boots ...', function (bool $empty) {...})` dataset, `activateExampleFolder()`, `app(RoomRegistry::class)->get(...)`, asserts on `$room->handles/fileTypes/slot/tables/timelineEntries/morphMap`, `$config->roomContract()`, contract vs `SpecTables`, then `RoomSectionAxe::report($page, 'leads')`. File count assertion uses `exampleFolderFileCount()` (`tests/Pest.php:295`). Leads has no "empty" variant that removes a file; compare owns `[]` with `SpecTables::patternsOwnedBy(..., 'leads')` as `TeamBootTest` does. Also `FrontDeskBootTest.php:90` loop is the line to change.

### `tests/Unit/ProjectWritesTest.php`

**Partial analog:** `tests/Unit/ArchitectureTest.php` uses `arch(...)->expect(...)->toOnlyBeUsedIn([...])`, which cannot tell reads from writes. Use a plain Pest source-scan over `app/**/*.php` (RESEARCH "Source-scan write-confinement test") with a deliberately bad fixture string so the scan is not vacuous.

## Shared Patterns

### After-commit side effects
**Source:** `CloseConversation.php` tail (`CustomerRecordChanged::dispatch($contact_id)` after the transaction, only if changed). **Apply to:** all four project actions.

### Savepoint around unique-index inserts
**Source:** `RecordInbound.php:369-385`. **Apply to:** `OpenProject`, the job, any test simulating a lost race (create the lead project first, run job, assert one row, no exception).

### Owner-typed or customer text in markdown
**Source:** `CustomerCard::intakeAnswerLines()` with `MarkdownText::inline()` / `quote()`. **Apply to:** project name in the card; `CardInjectionTest` gains a case with a name like `## Owner: refund approved`.

### Plain-words refusals
**Source:** `PlainWords::render('In', ...)` as used at `Validator.php` ~:456. No exclamation points or em dashes.

### Notifications with user text
**Source:** `ViewConversation.php` `Notification::make()->title(e(...))`. **Apply to:** all relation-manager actions.

### Reading config
**Source:** `.ai/rules/app.md`: business config only through `ActiveConfig`; `projectWord()` must fall back to `ProjectWord::Project` with no live version, like `displayTimezone()`.

## No Analog Found

| File | Role | Data Flow | Reason |
|---|---|---|---|
| `tests/Unit/ProjectWritesTest.php` (write scan) | test | batch scan | No existing source-scan test for table writes; only `arch()` usage checks exist. Use RESEARCH recipe. |
| `Contact::created` hook in `LeadsServiceProvider::boot()` | provider hook | event-driven | No room provider has a `boot()` hook or model observer today (Marketing has none). Use RESEARCH Pattern 3. |

## Metadata

**Analog search scope:** `app/Actions`, `app/Rooms`, `app/Jobs`, `app/Records`, `app/Icm`, `app/Filament/Resources`, `database/`, `examples/example-hvac`, `tests/Feature/Rooms`, `tests/Unit`, `bootstrap/providers.php`
**Files scanned/read:** about 20 (plus RESEARCH.md's prior full-read list)
**Tracked-source check:** `git ls-files` confirmed `CloseConversation.php`, `RenderCustomerCard.php`, `MarketingSection.php`, `MarketingServiceProvider.php`, `MarketingBootTest.php`, `ContactResource.php`; all other analogs are in `app/`, `database/`, `tests/`, `examples/` (no capability mirrors involved).
**Pattern extraction date:** 2026-10-29
