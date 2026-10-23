# Phase 4: Room Standard - Pattern Map

**Mapped:** 2026-10-22
**Files analyzed:** 27 new or modified
**Analogs found:** 24 / 27 (3 have only a partial or research-only pattern)

All analog paths below were confirmed git-tracked (`git ls-files`); none is a gitignored mirror. Line numbers are from the working tree on branch `gsd/phase-04-room-standard`.

## Binding rules (from CLAUDE.md and `.ai/rules/index.md`)

| Rule file | Applies to | What it says | Binds these new files |
|---|---|---|---|
| `.ai/rules/app.md` | `app/**` | Money is integer cents. Business configuration is read only through `App\Icm\ActiveConfig`; only `App\Icm\Loader` reads the folder. | Everything under `app/Rooms/`, `app/Icm/RoomChecks.php`, `app/Enums/*Kind.php`, widgets. `RoomChecks` must work from the `LoadedFolder` the Loader hands it, never from disk. `RoomSection` reads the contract through `ActiveConfig` only. |
| `.ai/rules/tests.md` | `tests/**` | Money is integer cents. | Pretend-room code and every `tests/Feature/Rooms/*` test. The pretend table must have no money column. |
| `.ai/rules/migrations.md` | `database/migrations/**` | Money is integer cents. | The `activities` room/entry migration, and the pretend-room migration. |
| `.ai/rules/factories.md` | `database/factories/**` | Money is integer cents. | Only if a pretend-room model gets a factory (optional; it lives under `tests/`). |
| `CLAUDE.md` | all | Tests first for the sync; Laravel concept walk-through line per new concept in the current sprint file in `docs/sprints/`; add `app/Rooms/` row to the Map; local commits only; `docs/BLUEPRINT.md` updated with any area-changing decision; run `vendor/bin/pint --dirty --format agent` after PHP edits; use `php artisan make:` and `make:test --pest`. | All plans. |
| Arch tests (`tests/Unit/ArchitectureTest.php`) | classes under `App\` and `Tests\` | `File` facade, Symfony `Finder`/`Yaml` and disk functions limited to the Loader, Changelog, RecordsWriter. | Pretend-room classes in `tests/Rooms/` must not touch the filesystem; do folder work in test closures through `writeIcmFile()` / `icmPath()` helpers. |

## File Classification

| New/Modified File | Role | Data Flow | Closest Analog | Match Quality |
|---|---|---|---|---|
| `app/Rooms/Room.php` | model (readonly descriptor) | transform | `app/Channels/InboundText.php` (readonly DTO, constructor promotion) | role-match |
| `app/Rooms/RoomRegistry.php` | service (singleton registry) | request-response | `app/Icm/ActiveConfig.php` + `AppServiceProvider` bindings | partial |
| `app/Rooms/RoomServiceProvider.php` | provider | event-driven (boot) | `app/Providers/AppServiceProvider.php` | role-match |
| `app/Rooms/RoomFileType.php` | interface | transform | `app/Channels/TextChannel.php` | role-match |
| `app/Rooms/RoomSection.php` | component (Filament widget) | request-response | `app/Filament/Widgets/DraftsWaiting.php` / `NewLeads.php` | role-match |
| `app/Rooms/TimelineEntryType.php` | model (value object) | transform | `app/Channels/InboundText.php` | role-match |
| `app/Enums/OwnableKind.php`, `app/Enums/PlatformKind.php` | model (enum) | transform | `app/Enums/ActivityType.php` | exact |
| `app/Icm/RoomChecks.php` | service (whole-folder checks) | batch/transform | `app/Icm/Validator.php` `duplicateSlugErrors` / `singletonErrors` | exact |
| `app/Icm/Loader.php` (modify) | service | file-I/O | itself | exact |
| `app/Icm/Validator.php` (modify) | service | transform | itself | exact |
| `app/Icm/FolderSync.php` `compile()` (modify) | service | transform | itself | exact |
| `app/Icm/ActiveConfig.php` (modify: `hasRoom` etc.) | service | request-response | `displayTimezone()` and `offers()` in the same file | exact |
| `app/Providers/AppServiceProvider.php` (modify: bind `RoomRegistry`) | provider | config | itself | exact |
| `app/Providers/Filament/AdminPanelProvider.php` (modify: widgets from registry) | provider | config | itself | exact |
| `app/Enums/ActivityType.php` (modify: add `RoomEntry`) | enum | transform | itself | exact |
| `app/Records/CustomerCard.php` (modify: `section()` arm) | service | transform | itself | exact |
| `app/Models/Activity.php` + Contact timeline scope (modify) | model | CRUD | itself | exact |
| `database/migrations/<ts>_add_room_columns_to_activities_table.php` | migration | batch | `2026_10_12_000009_add_sms_opt_out_to_contacts_table.php` | exact |
| `CLAUDE.md` Map row, `docs/SPEC.md` tables, `docs/BLUEPRINT.md` link | docs | n/a | existing rows/sections in each | exact |
| `tests/Rooms/PretendRoom/PretendRoomServiceProvider.php` | provider (test-only) | event-driven | `AppServiceProvider` | role-match |
| `tests/Rooms/PretendRoom/PretendChannel.php` + `FakePretendChannel.php` | interface + fake driver | request-response | `TextChannel` + `FakeTextChannel` | exact |
| `tests/Rooms/PretendRoom/PretendRoomSection.php` | widget (test-only) | request-response | `DraftsWaiting` | role-match |
| `tests/Rooms/PretendRoom/migrations/*_create_pretend_notes_table.php` | migration (test-only) | CRUD | `2026_10_12_000007_create_intake_answers_table.php` | role-match |
| `tests/Fixtures/pretend-room/**` (contract, example file, 2 connector files) | fixture | file-I/O | `examples/example-hvac/` files | role-match |
| `tests/Pest.php` (modify: `enablePretendRoom()`, `layPretendRoomFolder()`) | test helper | file-I/O | `writeIcmFile()`, `breakFile()`, `activateExampleFolder()` | exact |
| `tests/Feature/Icm/ValidatorTest.php:67` (modify one string) | test | request-response | itself | exact |
| `tests/Feature/Rooms/*Test.php` (Contract, Ownership, PretendRoomBoot, ConnectorFile, SpecTables, Accessibility, Timeline) | test | request-response | `tests/Feature/Icm/ValidatorTest.php`, `SyncTest.php` | exact |

## Pattern Assignments

### `app/Channels/` connector slot, copied for the pretend room (interface + fake + binding)

**Analog:** `app/Channels/TextChannel.php`, `app/Channels/FakeTextChannel.php`, `app/Providers/AppServiceProvider.php`

**Interface shape** (`TextChannel.php` lines 1-23): namespace, one small interface, PHPDoc on every method, a `name()` method.
```php
interface TextChannel
{
    /** The driver name, stored as the provider on every message it carries. */
    public function name(): string;
    public function send(string $toE164, string $body): string;
    public function parseInbound(Request $request): InboundText;
}
```
For the pretend slot keep it to two methods (`name()` plus one verb such as `push(string $note): string`) so the fake stays tiny.

**Fake driver shape** (`FakeTextChannel.php` lines 32-100): `class FakeX implements X`, private typed arrays with `@var list<array{...}>` PHPDoc, a `name()` returning `'fake'`, record each call into the array, return a `'fake-'.Str::uuid()` id, and an accessor `sent()` for tests. Copy only: the arrays, `name()`, the method that records, `sent()`. Do NOT copy the `Log::info` body logging or opt-out logic.

**Binding pattern** (`AppServiceProvider.php` lines 204-220): fake is a singleton so remembered calls survive between asks; the interface is bound to a closure that picks a driver from config.
```php
$this->app->singleton(FakeTextChannel::class);
$this->app->bind(TextChannel::class, function (Application $app): TextChannel {
    $driver = config('channels.text.driver');

    return match ($driver) {
        'fake' => $app->make(FakeTextChannel::class),
        'twilio' => $app->make(TwilioTextChannel::class),
        default => throw new InvalidArgumentException("Unknown text channel driver [{$driver}]. Use fake or twilio."),
    };
});
```
The pretend room does this inside its own provider's `register()` (binding only, no `ActiveConfig`). D-19: the registry holds the slot name and the interface class name as strings, so real rooms can point at classes outside `app/Rooms/` (front desk stays `App\Channels\TextChannel`). The front desk's slot has no connector file today (D-25 allows this).

---

### `app/Rooms/RoomServiceProvider.php` and `tests/Rooms/PretendRoom/PretendRoomServiceProvider.php` (provider, event-driven)

**Analog:** `app/Providers/AppServiceProvider.php`

**Docblock-on-register convention** (lines 189-203): the PHPDoc above `register()` explains in plain words what is bound and why; copy that style because the walk-through rule asks for plain explanations tied to the code.

**register() versus boot() split** (lines 204-249): `register()` = only container bindings (`scoped`, `bind`, `singleton`); `boot()` = `Relation::enforceMorphMap([...])`, `RateLimiter::for`, schedule dev command. A room provider copies this split:
- `register()`: `$this->app->make(RoomRegistry::class)->add($this->room())` plus its slot bindings. No `ActiveConfig`, no `DB`, no `Filament::getPanel()` (RESEARCH Q5, Pitfall 1).
- `boot()`: `Relation::morphMap([...])` (merge, not `enforceMorphMap`, which would replace the app's list; see `AppServiceProvider.php:235`), schedule via `$this->callAfterResolving(Schedule::class, ...)` with `->when(fn () => app(ActiveConfig::class)->hasRoom('x'))`.

**Registry binding line to add** next to `$this->app->scoped(ActiveConfig::class);` (line 206):
```php
$this->app->singleton(RoomRegistry::class);
```
`AppServiceProvider` is first in `bootstrap/providers.php`; keep that order and say so in the base class PHPDoc.

**Registration from a test** (RESEARCH "Registering a late provider", run in scratch test): `$this->app->register(PretendRoomServiceProvider::class);` runs `register()` then `boot()`. Never in `bootstrap/providers.php` (D-20).

---

### `app/Rooms/RoomSection.php` and `tests/Rooms/PretendRoom/PretendRoomSection.php` (Filament widget, request-response)

**Analog:** `app/Filament/Widgets/DraftsWaiting.php` (and `NewLeads.php`)

**Header and required flags** (`DraftsWaiting.php` lines 1-22; `NewLeads.php` lines 16-22):
```php
namespace App\Filament\Widgets;

use Filament\Widgets\TableWidget;

class NewLeads extends TableWidget
{
    protected static ?int $sort = 3;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';
```
`RoomSection` extends `Filament\Widgets\Widget` (not `TableWidget`, because it renders a Blade view with the D-21 line) and must keep `protected static bool $isLazy = false;` (RESEARCH Pitfall 3: lazy widgets are invisible to the first-HTML a11y check). Add `canView()` returning `app(ActiveConfig::class)->hasRoom(static::roomKey())`. The shared view must render `data-room-section="<key>"` for the axe scope.

**Registration into the panel** (`AdminPanelProvider.php` lines 35-40): today only `->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')`. Add one line after it, fed by the registry:
```php
->widgets(app(RoomRegistry::class)->widgetClasses())
```
Plus `RoomRegistry::addWidgetsTo(Panel $panel)` that both the provider and the test helper call (RESEARCH Pitfall 4).

The existing widgets are a table style; there is no existing Blade-view widget, so the view itself has no analog (see No Analog Found).

---

### `app/Icm/RoomChecks.php` (service, batch whole-folder checks)

**Analog:** `app/Icm/Validator.php` `duplicateSlugErrors` (lines 1434-1455) and `singletonErrors` (lines 1463-1490)

**Signature and error shape** (lines 1430-1434 and 1526-1529):
```php
/**
 * @param  Collection<int, IcmFile>  $files  Sorted by path.
 * @return list<array{file: string, field: ?string, message: string}>
 */
private function duplicateSlugErrors(Collection $files): array

private function error(string $file, ?string $field, string $message): array
{
    return ['file' => $file, 'field' => $field, 'message' => $message];
}
```
`RoomChecks` returns the same `{file, field, message}` list and has its own tiny private `error()` (or the Validator passes its own in).

**Core loop pattern: first owner wins, later file gets blamed** (lines 1438-1453):
```php
foreach ($files as $file) {
    $slug = $file->frontMatter['slug'] ?? null;

    if ($file->kind !== IcmFileKind::Typed || ! in_array($file->type, ['service', 'offer'], true) || ! is_string($slug) || $slug === '') {
        continue;
    }

    if (isset($owners[$file->type][$slug])) {
        $errors[] = $this->error($file->path, 'slug', 'slug '.PlainWords::oneLine($slug)." is already used by {$owners[$file->type][$slug]}");
    } else {
        $owners[$file->type][$slug] = $file->path;
    }
}
```
Use this for D-15 (two contracts claim one file), D-26 (two connector files own one kind: blame the later path, message "customers is owned by both connectors/a.md and connectors/b.md; pick one, the other can read it") and D-25 (two files fill one slot).

**Missing-thing pattern** (`singletonErrors` lines 1466-1484): iterate a const map, `filter` by `kind === Typed && type === ...`, `isEmpty()` branch, `firstWhere('path', ...) ?? first()` owner. Reuse for "slot registered by a room not in this folder" (D-25) and "owner room absent" (D-10).

**Every owner-written value goes through `PlainWords::oneLine()`** (`PlainWords.php` lines 333-338; used at `Validator.php:1447`).

**Where it plugs in** (`Validator.php` line 121): append `...$this->roomChecks->errors($files)` to the existing `array_push($errors, ...$this->duplicateSlugErrors($files), ...)`. `Validator::validate()` (lines 105-124) walks `Unrecognized` then `Typed` files; `RoomChecks` runs after that, on the collection already sorted by path.

**Per-file rules for the new `room` and `connector` types:** add arms to the `rulesFor()` match (default arm `[]` near line 268) in the existing style `'owns' => 'bail|present|array'`. See `Validator::fileErrors()` around lines 144-152 for the post-check `match ($file->type)`. `PlainWords::render('In', ...)` already produces "must be one of ...".

**Test-visible exact string:** `Validator::problemMessage()` (line 1496) renders `Loader::TYPED`; adding `room` and `connector` to `TYPED` changes it (see ValidatorTest below).

---

### `app/Icm/Loader.php` (modify)

**Analog:** itself. Edits, with the lines they touch:
- Line 23: append `'room'`, `'connector'` to `TYPED`.
- Line 30: change `LATER_SPRINT` to `['automation', 'walk_test']`.
- Lines 80-82, narrow the router branch (D-03):
```php
if (in_array($file->getFilename(), self::ROUTERS, true)) {
    return new IcmFile($path, IcmFileKind::Router, null, [], $raw, $raw);
}
```
Change the condition to also require `preg_match('#^rooms/[^/]+/CONTEXT\.md$#', $path) !== 1`, so only a CONTEXT.md directly in `rooms/<room>/` falls through to the front matter parse. `$path` is already `/`-separated via `relativePath()` (lines 130-133).
- Lines 117-121: add `in_array($type, $this->rooms->registeredTypes(), true) => IcmFileKind::Typed` to the `match (true)` for room-defined types; the Loader needs a constructor taking `RoomRegistry` (no `new Loader` in the app; `FolderSync` injects it).
- The Loader remains the only user of `Finder`/`Yaml` (arch rule).

---

### `app/Icm/FolderSync.php` `compile()` (modify)

**Analog:** itself, lines 208-231.
```php
$compiled = ['files' => [], 'index' => ['service' => [], 'knowledge' => [], 'agent' => [], 'offer' => []]];
...
match ($file->type) {
    'service' => $compiled['index']['service'][$file->frontMatter['slug'] ?? $file->path] = $file->path,
    'offer' => $compiled['index']['offer'][$file->frontMatter['slug'] ?? $file->path] = $file->path,
    'knowledge' => $compiled['index']['knowledge'][] = $file->path,
    'agent' => $compiled['index']['agent'][$file->frontMatter['channel'] ?? $file->path] = $file->path,
    null => null,
    default => $compiled['index'][$file->type] = $file->path,
};
```
Add arms BEFORE `default` (RESEARCH Pitfall 2): `'room' => $compiled['index']['room'][<folder name from path>] = $file->path`, `'connector' => $compiled['index']['connector'][] = $file->path`, and an arm for registered types keyed per file. Also seed `'room' => []`, `'connector' => []` in the initial `index` array. Reject path (`reject()`) is unchanged.

---

### `app/Icm/ActiveConfig.php` (modify: `hasRoom`, `roomContract`, `roomFilesPresent`, `connectors`)

**Analog:** `displayTimezone()` (lines 50-61) for the never-throwing guard, `offers()` (lines 181-194) for the "version compiled before this existed" tolerance.
```php
public function displayTimezone(): string
{
    try {
        return $this->timezone();
    } catch (NoActiveVersion) {
        return config('app.timezone');
    }
}

public function offers(): array
{
    $paths = $this->version()->compiled['index']['offer'] ?? [];
    $paths = array_values($paths);
    sort($paths, SORT_STRING);

    return array_map(fn (string $path) => [...$this->typedFile($path), 'path' => $path], $paths);
}
```
`hasRoom()` copies the try/catch-`NoActiveVersion` and the `?? []` index read; use `typedFile($path)` (as `offers()` does) for the contract's front matter. Nothing in providers' `register()`/`boot()` may call these (RESEARCH Q5).

---

### `app/Enums/OwnableKind.php`, `app/Enums/PlatformKind.php` (enum)

**Analog:** `app/Enums/ActivityType.php` (lines 1-18): `namespace App\Enums; enum X: string { case TitleCase = 'snake_case'; }`. Eight ownable cases: customers, sales_activity, invoices, payments, expenses, work_orders, schedule, documents. Three platform cases: projects, texts, timeline. Keys are TitleCase, values the YAML words. SPEC lists both, and a test compares `array_column(OwnableKind::cases(), 'value')` to the SPEC rows (D-14/D-22/D-23).

---

### `ActivityType::RoomEntry`, `CustomerCard::section()`, `Activity` (timeline)

**Analog:** the existing files.
- `app/Enums/ActivityType.php`: add `case RoomEntry = 'room_entry';` after `OptedIn`.
- `app/Records/CustomerCard.php` lines 156-170: `section()` has a `match ($activity->type)` with no default, one arm per case returning `[$label, $intro, $body]`. Add one arm in the same triple shape, e.g. `ActivityType::RoomEntry => $this->roomEntryParts($activity)`, falling back to `['Room entry', 'System note:', $activity->summary]` when the registry no longer knows the entry. Existing arms for the style: `ActivityType::OptedOut => ['Opted out of texts', 'System note:', $activity->summary],`.
- `app/Models/Activity.php` line 36 holds `'type' => ActivityType::class,`; add casts/fillable for `room` and `entry` the same way, and the D-08 read scope (RESEARCH Open Question 1, confirm with Ian).

---

### `database/migrations/<ts>_add_room_columns_to_activities_table.php` (migration)

**Analog:** `database/migrations/2026_10_12_000009_add_sms_opt_out_to_contacts_table.php` (adds a nullable column to an existing table).
```php
return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * When the customer texted STOP (D-40). A null value means they can be texted. The column holds no foreign key,
     * so SQLite adds it without rebuilding the table.
     */
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->timestamp('sms_opted_out_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn('sms_opted_out_at');
        });
    }
};
```
Copy the anonymous-class shape, the docblock explaining the column in plain words, `Schema::table`, and a `down()` that drops. Use two `->string('room')->nullable()` / `->string('entry')->nullable()` columns, no `after()` (MySQL-only), no foreign key (SQLite). Create with `php artisan make:migration` (project convention, prefix timestamps look like `2026_10_12_000009_`). Binding rule: `.ai/rules/migrations.md` (no money columns).

**Pretend-room table migration** (`tests/Rooms/PretendRoom/migrations/`): analog `database/migrations/2026_10_12_000007_create_intake_answers_table.php` for a plain create-table; loaded by the pretend provider's `boot()` with `$this->loadMigrationsFrom(__DIR__.'/migrations')` and run in the test via `$this->artisan('migrate')` (RESEARCH Pitfall 5).

---

### `tests/Pest.php` helpers (`enablePretendRoom()`, `layPretendRoomFolder()`)

**Analog:** `tests/Pest.php` helpers at lines 289-325.
```php
function icmPath(string $relative = ''): string
{
    return rtrim(config('icm.path').'/'.ltrim($relative, '/'), '/');
}

function activateExampleFolder(): IcmVersion
{
    $result = app(FolderSync::class)->run();

    expect($result->outcome)->toBe(SyncOutcome::Activated);

    return $result->version;
}

function writeIcmFile(string $relative, string $contents): void
{
    File::ensureDirectoryExists(dirname(icmPath($relative)));
    File::put(icmPath($relative), $contents);
}
```
Style: file-level free functions with a PHPDoc line, using `icmPath()` so everything stays in the temp copy created in the `beforeEach` (lines 36-80: `File::copyDirectory(base_path('examples/example-hvac'), $folder)`, `config(['icm.path' => $folder])`). `layPretendRoomFolder()` copies `tests/Fixtures/pretend-room/` over `icmPath()`; it is a free function so the arch rule on classes does not apply. `exampleFolderFileCount()` (lines 281-284) must stay `return 14;` (D-20).

---

### `tests/Feature/Rooms/*Test.php` (tests, request-response)

**Analog:** `tests/Feature/Icm/ValidatorTest.php` and `SyncTest.php`.

**Refusal test pattern** (`ValidatorTest.php` lines 60-72): write a broken file, run `icm:sync`, assert exit code 1 and exact plain-words line, then read the stored rejected version.
```php
$this->artisan('icm:sync')
    ->expectsOutputToContain($line)
    ->assertExitCode(1);

expect(rejectedErrors())->toBe([$line]);
```
Use `dataset(...)` with `[path, contents, exact line]` rows (as in lines 13-30 and the `->with([...])` at lines 66-72) for the room and connector refusals (D-07, D-10, D-15, D-17, D-22, D-23, D-25, D-26). Prove "previous version stays live" by running `activateExampleFolder()` first, then breaking, then asserting `ActiveConfig` still serves the first version (see the `use App\Icm\ActiveConfig; use App\Models\IcmVersion;` imports at top of `ValidatorTest.php`).

**Success sync pattern** (`SyncTest.php` lines 7-23): `Artisan::call('icm:sync')`, `IcmVersion::first()`, compare `file_count` to `exampleFolderFileCount()` plus the fixture's typed files (computed in the test).

**Router-vs-room pitfall test** (`SyncTest.php` lines 48-52 "does not count README, CONTEXT or START-HERE files" uses `writeIcmFile('business/extras/CONTEXT.md', ...)`) is the model for proving the four existing `CONTEXT.md` files stay routers and only `rooms/<x>/CONTEXT.md` is typed.

**The one named edit to an existing test** (`tests/Feature/Icm/ValidatorTest.php:67`): the expected string
`'notes/recipe.md: type must be one of business, hours, lead_sources, knowledge, service, voice, sending_rules, agent, set_replies, triage, offer (you wrote recipe)'`
becomes the same with `, room, connector` appended before ` (you wrote recipe)` (if the registry types come after, they are listed after; plan must fix the exact order). Shared setup: the `beforeEach` in `tests/Pest.php` applies to `Feature` tests (copy of the example folder, fake channel, `Http::preventStrayRequests()`, `Queue::fake([ProcessInbound::class])`). Pest/test authoring commands: `php artisan make:test --pest <Name>` with no suite directory in the name.

**Arch test** (`tests/Unit/ArchitectureTest.php`): add a rule for `App\Rooms` / `Tests\Rooms` beside the existing disk-function arch rules (lines 22-37 list the namespaces; add `App\Rooms` to the `not->toBeUsedIn` list).

---

## Shared Patterns

### Plain-words error shape
**Source:** `app/Icm/Validator.php:1526-1529` and `app/Icm/PlainWords.php:333-338`
**Apply to:** `RoomChecks`, any new Validator arm.
Every error is `['file' => ..., 'field' => ..., 'message' => ...]`; every owner-written value interpolated into a message goes through `PlainWords::oneLine()`. Messages for the room standard: use the sentences in CONTEXT.md `<specifics>` and RESEARCH Q3's table; keep them in `RoomChecks` in one place so they can be reworded (RESEARCH Open Question 4). No exclamation points or em dashes.

### Reject and keep previous version live
**Source:** `FolderSync::reject()` (unchanged).
**Apply to:** every new check. New checks only add errors to `ValidationReport`; nothing in the reject path changes.

### Run-time room gate, never boot-time
**Source:** `ActiveConfig::displayTimezone()` pattern (`app/Icm/ActiveConfig.php:54-61`) and `AppServiceProvider` scoped binding (`:206`).
**Apply to:** widgets (`canView()`), scheduled jobs (`->when()` and again inside `handle()`), timeline writers, CustomerCard.
Providers register unconditionally; every behaviour calls `hasRoom()` when it runs.

### Business configuration only through `ActiveConfig`
**Source:** `.ai/rules/app.md`.
**Apply to:** all `app/Rooms/*` and widgets. Do not read the folder from room code. Room contract data reaches the widget through new `ActiveConfig` methods.

### Concept walk-through log
**Source:** `CLAUDE.md` Rules and RESEARCH "Walk-through log: plain words" (nine ready-made lines).
**Apply to:** the plans that introduce a service provider, `register()` vs `boot()`, boot order, the singleton registry, test-time provider registration, `loadMigrationsFrom()` in tests, `canView()`, and the new enum case. One line each in the current sprint file in `docs/sprints/`.

### Migration style
**Source:** `database/migrations/2026_10_12_000009_add_sms_opt_out_to_contacts_table.php` (anonymous class, plain-words docblock on `up()`, `down()` drops).
**Apply to:** the activities migration and the pretend-room migration.

### Formatting and generation
**Source:** `CLAUDE.md`/Boost rules.
**Apply to:** all PHP: curly braces, constructor promotion, explicit return types, PHPDoc array shapes; scaffold with `php artisan make:class|make:migration|make:test --pest`; run `vendor/bin/pint --dirty --format agent`.

## No Analog Found

| File | Role | Data Flow | Reason |
|---|---|---|---|
| `app/Rooms/RoomRegistry.php` | registry | request-response | No container-held registry exists. Use RESEARCH Pattern 1/2 and the bound-singleton line in `AppServiceProvider`; keep it free of folder or `ActiveConfig` access. |
| `app/Rooms/RoomSection` Blade view (D-21 empty line, `data-room-section`) | view | request-response | The four existing widgets are `TableWidget`s with no custom Blade view. Use RESEARCH Q2 and Code Examples; keep the markup axe-clean (real heading/paragraph elements, no color-only meaning). |
| `tests/Feature/Rooms/RoomAccessibilityTest.php` | browser test | request-response | No browser/Playwright test exists and the plugin is not installed. Blocked on Ian approving `pestphp/pest-plugin-browser` and `playwright` (D-18, D-27). Use RESEARCH Q6 snippet; spike `actingAs` + `visit('/admin')` first. |

## Metadata

**Analog search scope:** `app/Channels`, `app/Providers`, `app/Icm`, `app/Enums`, `app/Filament/Widgets`, `app/Records`, `database/migrations`, `tests/Pest.php`, `tests/Feature/Icm`, `tests/Unit`.
**Files scanned:** about 25 read in full or by targeted range; `git ls-files` confirmed the analogs are tracked.
**Pattern extraction date:** 2026-10-22
