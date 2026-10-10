# Phase 1: Foundation - Pattern Map

**Mapped:** 2026-10-10
**Files analyzed:** ~75 new/modified (grouped by role below)
**Analogs found:** 9 in-repo (all partial or scaffold-level) / ~75. The rest are "generator + vendor reference".

## Ground truth about this codebase

The scaffold is untouched. Tracked app code is only: `app/Models/User.php`, `app/Providers/AppServiceProvider.php`, `app/Providers/Filament/AdminPanelProvider.php`, `app/Http/Controllers/Controller.php`, `database/factories/UserFactory.php`, three framework migrations, `tests/Pest.php`, `tests/TestCase.php`, `tests/Feature/ExampleTest.php`, `bootstrap/app.php`, `routes/{web,console}.php`, `phpunit.xml`, `.gitignore`. Every pattern in this phase sets precedent. Use `php artisan make:*` (always `--no-interaction`) and match the generator output.

Tracked-source gate: `.agents/`, `.claude/`, `.mcp.json` and `vendor/` are gitignored. They are cited below only as read-only convention references (generator stubs, vendor source), never as files to copy into or edit. In-repo analogs named below were all checked as tracked via `git ls-files`.

`.ai/rules/` does not exist yet. CLAUDE.md requires reading `.ai/rules/index.md` before editing; once task 01-01 creates it, later plans must read it first.

## File Classification

| New/Modified File | Role | Data Flow | Closest Analog | Match Quality |
|---|---|---|---|---|
| `examples/example-hvac/**` (md fixture) | config/fixture | file-I/O | none; contract in `docs/SPEC.md` 195-207 and RESEARCH "Example Folder Fixture" | no analog |
| `.gitignore` (add records rules) | config | n/a | `.gitignore` (existing, tracked) | exact (same file) |
| `app/Icm/Loader.php` | service | file-I/O, transform | none; RESEARCH Pattern 1 + "Ignoring records/" | no analog |
| `app/Icm/Validator.php` (+ PlainWords renderer) | service | transform | none; RESEARCH Pattern 2 | no analog |
| `app/Icm/FolderSync.php`, `Changelog.php`, `SyncResult.php` | service | batch, file-I/O | none; RESEARCH Pattern 3 | no analog |
| `app/Icm/ActiveConfig.php` | service | request-response (read) | none; RESEARCH Pattern 4 | no analog |
| `app/Providers/AppServiceProvider.php` (bind ActiveConfig scoped, throttle limiter, morph map) | provider | config | `app/Providers/AppServiceProvider.php` | exact (same file) |
| `app/Console/Commands/{IcmSync,IcmStatus,RecordsRebuild}.php` | command | batch | `make:command` stub (`vendor/laravel/framework/.../Console/stubs/console.stub`) | generator |
| `database/migrations/*_create_icm_versions_table.php` and contacts/conversations/messages/activities | migration | CRUD | `database/migrations/0001_01_01_000002_create_jobs_table.php` | role-match |
| `app/Models/{Contact,Conversation,Message,Activity,IcmVersion}.php` | model | CRUD | `app/Models/User.php` | role-match (attribute style) |
| `database/factories/*Factory.php` | factory | CRUD | `database/factories/UserFactory.php` | exact |
| `database/seeders/*` | seeder | batch | `database/seeders/DatabaseSeeder.php` | role-match |
| `app/Enums/*.php` | enum | n/a | none; `php artisan make:enum` | generator |
| `app/Channels/{TextChannel,FakeTextChannel,TwilioTextChannel,InboundText}.php` | service/interface | request-response | none; `make:interface` / `make:class` | no analog |
| `app/Actions/{RecordInbound,SendOwnerReply}.php` | service (action) | CRUD, transaction | none; RESEARCH Pattern 5 | no analog |
| `app/Http/Controllers/{FakeTextWebhookController,LeadController}.php` | controller | request-response | `app/Http/Controllers/Controller.php` (base only) | partial |
| `app/Http/Requests/{StoreLeadRequest,FakeTextWebhookRequest}.php` | request | request-response | none; `make:request` | generator |
| `routes/web.php` (webhook, leads, `/dev/chat`) | route | request-response | `routes/web.php` | exact (same file) |
| `bootstrap/app.php` (CSRF exemption) | config | request-response | `bootstrap/app.php` | exact (same file) |
| `app/Events/CustomerRecordChanged.php`, `app/Listeners/QueueCustomerRecordRender.php` | event/listener | event-driven | none; `make:event`, `make:listener` | generator |
| `app/Jobs/{ProcessInbound,RenderCustomerCard}.php` | job | event-driven, file-I/O | `jobs` table in `0001_01_01_000002_create_jobs_table.php` (infra only) | no analog |
| `app/Records/{CustomerCard,RecordsWriter}.php` | service | file-I/O, transform | none; RESEARCH Pattern 6 | no analog |
| `app/Filament/Resources/Contacts/**`, `Conversations/**` | resource | CRUD (read) | none; `make:filament-resource --view` | generator |
| `app/Filament/Pages/BusinessFolder.php` | page | request-response | none; `make:filament-page` | generator |
| `app/Livewire/{DevChat,ConversationThread}.php` + blade + `resources/views/layouts/app.blade.php` | component | request-response, polling | none; `make:livewire --class --emoji=false` | generator |
| `app/Models/User.php` (add `FilamentUser`) | model | n/a | `app/Models/User.php` | exact (same file) |
| `config/icm.php`, `config/channels.php` | config | n/a | `config/ai.php`, `config/services.php` | role-match |
| `phpunit.xml` (ICM_PATH env), `tests/Pest.php` (temp-copy beforeEach) | test config | n/a | same files | exact |
| `tests/Feature/{Icm,Inbound,Records,Filament,Quality}/*Test.php`, `tests/Unit/ArchitectureTest.php` | test | request-response | `tests/Feature/ExampleTest.php` (class style) + `tests/Pest.php` bindings | partial |
| `.ai/rules/**` | config | n/a | Boost `RuleRepository` (vendor) | tool-generated |
| `docs/sprints/sprint-0.md` (Walk-through log) | doc | n/a | same file, "Walk-through log" at end (line ~131) | exact |

## Pattern Assignments

### Models: `Contact`, `Conversation`, `Message`, `Activity`, `IcmVersion` (model, CRUD)

**Analog:** `app/Models/User.php` (lines 1-36). Create with `php artisan make:model Contact --factory --no-interaction` (CLAUDE.md: models get factories and seeders; add `--seed` or write a seeder).

**Attribute-style mass assignment, factory generic, casts() method**:
```php
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

#[Fillable(['name', 'email', 'password'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed'];
    }
}
```
Copy: `#[Fillable([...])]` instead of `$fillable`, `casts()` method (not `$casts`), the `@use HasFactory<XFactory>` docblock, PHPDoc with array-shape return types. Cast status/type columns to the backed enums, `compiled`/`errors`/`notices`/`meta` to `array`, `*_cents` to `integer`. Add `Relation::enforceMorphMap()` in `AppServiceProvider::boot()` (RESEARCH data model).

### Factories (factory, CRUD)

**Analog:** `database/factories/UserFactory.php` (lines 1-48). Copy the `@extends Factory<Model>` docblock, `definition(): array` with `fake()->...` (the project uses the `fake()` helper, not `$this->faker`), and named states returning `static` via `$this->state(fn (array $attributes) => [...])` (e.g. `unverified()`). Add states such as `Conversation::factory()->closed()`, `IcmVersion::factory()->active()/rejected()`, `Contact::factory()->withoutPhone()`. Phone fixtures must be valid numbers like `+12025550123` (Pitfall 10).

### Migrations (migration, CRUD)

**Analog:** `database/migrations/0001_01_01_000002_create_jobs_table.php` (anonymous class, `up()` and `down()`, `Schema::create`, `$table->id()`). Generate with `php artisan make:migration create_contacts_table --no-interaction`. Use `database-schema` Boost tool before writing. Money columns: `unsignedInteger('acquisition_cost_cents')`, `integer('cost_cents')`. Composite unique: `$table->unique(['provider', 'provider_message_id'])`. Partial unique index for one open conversation needs a raw `DB::statement('create unique index ... on conversations (contact_id) where status = \'open\'')` (RESEARCH Don't Hand-Roll). Deltas from sprint-0.md are listed in RESEARCH "Recommended Data Model": `channel` on `messages`, not `conversations` (D-16).

### `IcmSync`, `IcmStatus`, `RecordsRebuild` (command, batch)

**Analog:** the framework stub, via `php artisan make:command IcmSync --no-interaction`. Attribute style (this app's Laravel 13):
```php
#[Signature('icm:sync')]
#[Description('Command description')]
class IcmSync extends Command
{
    public function handle() { }
}
```
Add `: int` return, return `self::FAILURE` when the sync is rejected, write errors with `$this->error()` grouped by file (D-04). Commands auto-register from `app/Console/Commands`.

### `AppServiceProvider` (provider, config)

**Analog:** `app/Providers/AppServiceProvider.php` lines 11-24 (empty `register()` and `boot()` with docblocks). Put `$this->app->scoped(ActiveConfig::class, ...)` in `register()`; never call ActiveConfig in `boot()` (RESEARCH Pattern 4). Named rate limiter (`RateLimiter::for('leads', ...)`) and `Relation::enforceMorphMap()` go in `boot()`. Scoped vs singleton rationale: `vendor/laravel/framework/src/Illuminate/Queue/QueueServiceProvider.php:263` calls `$app->forgetScopedInstances()` after each job.

### Bootstrap CSRF exemption (config, request-response)

**Analog:** `bootstrap/app.php` lines 12-14, the empty `withMiddleware` closure:
```php
->withMiddleware(function (Middleware $middleware): void {
    //
})
```
Replace body with `$middleware->preventRequestForgery(except: ['webhooks/*', 'leads']);` (Pitfall 7). The panel already registers `Illuminate\Foundation\Http\Middleware\PreventRequestForgery` in `AdminPanelProvider` (line 17 import, middleware list), so the global exemption is by URI only. Tests cannot catch a missing exemption; add the Quality test plus a manual curl UAT.

### Routes (route, request-response)

**Analog:** `routes/web.php` (only `Route::get('/', ...)`). Add, using named routes (CLAUDE.md prefers `route()`):
```php
Route::post('/webhooks/text/fake', FakeTextWebhookController::class)->name('webhooks.text.fake');
Route::post('/leads', [LeadController::class, 'store'])->middleware('throttle:leads')->name('leads.store');
Route::livewire('/dev/chat', DevChat::class)->name('dev.chat'); // env gate evaluated per request
```
`Route::livewire` macro: `vendor/livewire/livewire/src/Mechanisms/HandleRouting/HandleRouting.php:16`. Fake webhook must 404 unless `channels.text.driver === 'fake'`.

### Controllers and form requests (controller, request-response)

**Analog:** `app/Http/Controllers/Controller.php` is the abstract base only, so no in-repo behavioral analog. Generate with `php artisan make:controller FakeTextWebhookController --invokable --no-interaction` and `php artisan make:request StoreLeadRequest --no-interaction`. Keep controllers thin: validate via FormRequest, build the DTO (explicit fields, never `$request->all()` into a model), call `RecordInbound`, return 200 TwiML `<Response/>` (`text/xml`) for the webhook, JSON for `/leads`. Duplicate provider id returns 200 (A1).

### `RecordInbound` / `SendOwnerReply` (action, transaction)

No in-repo analog. Generate with `php artisan make:class Actions/RecordInbound --no-interaction`. Follow RESEARCH Pattern 5 (single `DB::transaction`; nested transaction as savepoint; catch `Illuminate\Database\UniqueConstraintViolationException`; event fired after commit). Use constructor property promotion (CLAUDE.md PHP rules). Never use `updateOrCreate` for contact matching (D-12/13).

### Jobs and events (job/event, event-driven)

No in-repo job exists (only the `jobs` table). Generate: `php artisan make:job RenderCustomerCard --no-interaction`, `make:event CustomerRecordChanged`, `make:listener QueueCustomerRecordRender --event=CustomerRecordChanged`. Contract in RESEARCH Pattern 6: `ShouldQueue, ShouldBeUniqueUntilProcessing`, `uniqueId()` = contact id, `#[UniqueFor(300)]` (`Illuminate\Queue\Attributes\UniqueFor`), `WithoutOverlapping` middleware with `releaseAfter`/`expireAfter`, `retryUntil()`. Event implements `ShouldDispatchAfterCommit`. Queue behaviour verified at `vendor/laravel/framework/src/Illuminate/Queue/CallQueuedHandler.php`. With the `sync` queue in tests the job runs inline (Pitfall 13).

### `app/Records/*` (service, file-I/O)

No in-repo analog. Atomic write primitive to use for every generated file (`vendor/laravel/framework/src/Illuminate/Filesystem/Filesystem.php:215-234`):
```php
File::replace($path, $content);   // tempnam in same dir + rename
```
Summary files: read the DB inside `Cache::lock('records:summaries', 30)->block(10, fn () => ...)`. Order timelines `orderByDesc('occurred_at')->orderByDesc('id')`. Render customer bodies as `> ` blockquotes and escape `|` and newlines in table cells (Pitfall 14).

### Filament resources (resource, CRUD read)

No in-repo resource exists. Generate: `php artisan make:filament-resource Contact --view --no-interaction` and `Conversation`. Filament 5 layout is `Resources/Contacts/{ContactResource.php, Pages/, Schemas/, Tables/}`. Remove Create/Edit pages and keep `index` + `view` in `getPages()`. Embed the thread/reply box with `Filament\Schemas\Components\Livewire::make(ConversationThread::class, ['conversation' => $record])` (signature at `vendor/filament/schemas/src/Components/Livewire.php:29-44`). Actions use `Filament\Actions\*` only. Panel auto-discovery already wired (`AdminPanelProvider` `discoverResources`/`discoverPages` lines 33-36), so no provider change. Convention reference: the `filament-development` skill (`.agents/skills/filament-development/SKILL.md`, gitignored mirror; read-only reference).

### `BusinessFolder` page (page, request-response)

Generate: `php artisan make:filament-page BusinessFolder --no-interaction`. Header `Action::make('syncNow')` calling `FolderSync` inline, then `Filament\Notifications\Notification::make()->title()->body()->success()|danger()->send()` (D-10). `$view` is `protected string`; `$navigationIcon` is `string | BackedEnum | null`.

### `User` + Filament access (model)

**Analog/target:** `app/Models/User.php`. Add `implements FilamentUser` and:
```php
public function canAccessPanel(Panel $panel): bool
{
    return true; // single-owner instance
}
```
Reason: `vendor/filament/filament/src/Http/Middleware/Authenticate.php:31-39` aborts 403 outside `local` for users not implementing `FilamentUser` (Pitfall 9, Open Question 5; needs Ian's confirmation).

### Livewire components (`DevChat`, `ConversationThread`)

Generate: `php artisan make:livewire DevChat --class --emoji=false --no-interaction` (Livewire 4 default is single-file with emoji). Layout `resources/views/layouts/app.blade.php` must not use `@vite` (Pitfall 8). Use `wire:poll.2s`. Call `RecordInbound` in-process (A10). Gate with `abort_unless(app()->environment('local'), 404)` per request.

### Config files (`config/icm.php`, `config/channels.php`)

**Analog:** `config/services.php` / `config/ai.php` (env-backed arrays; read both with one Read before writing). Keys: `icm.path => env('ICM_PATH', base_path('examples/example-hvac'))`, `channels.text.driver => env('TEXT_CHANNEL', 'fake')`, `channels.phone_region => 'US'` (D-19). Read via `config()` only; `ICM_PATH` appears nowhere else.

### Tests

**Analog:** `tests/Pest.php` lines 15-17 (Feature suite binding):
```php
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');
```
Existing `tests/Feature/ExampleTest.php` is PHPUnit class style, not Pest. New tests are Pest closures via `php artisan make:test --pest SyncTest` (no `Feature/` prefix; subfolders like `Icm/SyncTest` are accepted by the generator path argument, confirm when running). Add to `tests/Pest.php` a global `beforeEach` that copies `examples/example-hvac` to a temp dir and sets `config(['icm.path' => $tmp])`, plus `afterEach` cleanup (Pitfall 13). `phpunit.xml` `<php>` block (lines 18-31) is where to add `<env name="ICM_PATH" value="/nonexistent"/>`. Use `Livewire\Livewire::test()` (no pest-plugin-livewire). Use `Queue::fake()`, `Event::fake()`, `Carbon::setTestNow`.

### `.gitignore` (config)

**Analog:** `.gitignore` (existing, flat list with a trailing comment-block for regenerated agent dirs). Append the anchored rules exactly (D-23, Pitfall 6):
```gitignore
# generated records snapshots (keep README.md and CLAUDE.md tracked)
/examples/*/records/*
!/examples/*/records/README.md
!/examples/*/records/CLAUDE.md
```
Verify: `git check-ignore -v examples/example-hvac/records/INDEX.md` (ignored), `app/Records/CustomerCard.php` (not ignored).

### `.ai/rules` (QUAL-01)

No file analog; use the Boost MCP `record-rule` tool (params `glob`, `title`, `note`) or fall back to `Laravel\Boost\Rules\RuleRepository` (`vendor/laravel/boost/src/Rules/RuleRepository.php`, constructor `__construct(protected string $directory)`, writes `index.md` plus area files). Do not hand-write. Needs Ian's go (Open Question 3). Rule texts are in RESEARCH "Code Examples".

## Shared Patterns

### PHP style (apply to every new PHP file)
Source: CLAUDE.md "php rules" and sibling files `User.php`, `UserFactory.php`. Curly braces always; constructor property promotion; explicit param and return types; TitleCase enum keys; PHPDoc blocks (not inline comments) with array shapes; run `vendor/bin/pint --dirty --format agent` after edits. `app/Models/User.php` shows the house docblock style (`/** Get the attributes ... @return array<string, string> */`).

### Generators first
Use `php artisan make:* --no-interaction` for models, migrations, controllers, requests, commands, jobs, events, listeners, enums, tests (`--pest`), `make:filament-resource`, `make:filament-page`, `make:livewire --class --emoji=false`; `make:class` for generic classes (`Icm/*`, `Actions/*`, `Records/*`, `Channels/*`).

### Config read path (QUAL-01)
Business configuration only via `App\Icm\ActiveConfig`; only `App\Icm\Loader` touches the folder; `Yaml` used only in `Loader` (arch test in RESEARCH "Pest arch guard").

### Money
Integer cents only (`*_cents` integer columns and casts); `integer:strict` in the Validator.

### Writes to the folder
Every written file (cards, summaries, `changelog.md`) goes through `File::replace()` (atomic).

### Never lose a lead
`RecordInbound` must catch `NoActiveVersion` from ActiveConfig and still record; unknown source stored with a timeline note (D-15).

### Walk-through log
Each new Laravel concept's first appearance gets one line in `docs/sprints/sprint-0.md` under "Walk-through log" (the file's last section).

## No Analog Found

| File group | Role | Data Flow | Reason / what to follow instead |
|---|---|---|---|
| `app/Icm/*` | service | file-I/O, transform | Greenfield; follow RESEARCH Patterns 1-4; `make:class` |
| `app/Actions/*` | action | transaction | Greenfield; RESEARCH Pattern 5 |
| `app/Channels/*` | interface + drivers | request-response | Greenfield; `make:interface`/`make:class`; driver chosen by `config('channels.text.driver')` |
| `app/Jobs/*`, `Events`, `Listeners` | job/event | event-driven | No jobs exist; RESEARCH Pattern 6 |
| `app/Records/*` | service | file-I/O | Greenfield; RESEARCH Pattern 6 |
| `app/Filament/*`, `app/Livewire/*` | UI | CRUD/polling | Run the Filament/Livewire generators and keep their output; vendor refs above |
| `examples/example-hvac/**` | fixture | file-I/O | Contract: `docs/SPEC.md` 195-207 and RESEARCH "Example Folder Fixture" (valid fictional phones, quoted YAML phone, 8 typed files) |
| `app/Enums/*` | enum | n/a | `php artisan make:enum`; backed string enums, TitleCase keys |

## Metadata

**Analog search scope:** all `git ls-files` (app, config, database, routes, tests, bootstrap, stubs, docs); vendor read for Filesystem, Queue, Filament, Livewire, Boost references.
**Files scanned:** ~40 tracked files plus 8 vendor files.
**Pattern extraction date:** 2026-10-10
**Note:** the repo's `stubs/` dir holds only laravel/ai stubs (agent, tool); they are not analogs for this phase.
