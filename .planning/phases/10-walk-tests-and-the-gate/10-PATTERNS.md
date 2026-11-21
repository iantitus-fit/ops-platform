# Phase 10: Walk Tests and the Gate - Pattern Map

**Mapped:** 2026-11-21
**Files analyzed:** 33 new or modified
**Analogs found:** 31 / 33 (all analog paths verified git-tracked)

Notes for the planner: RESEARCH.md already holds line-level seams, the forced test edits (T1 to T3) and the map-card list. This file adds the concrete code to copy. Line numbers are from the current tree.

## File Classification

| New/Modified File | Role | Data Flow | Closest Analog | Match |
|---|---|---|---|---|
| `app/Icm/Walks/WalkFileChecks.php` | validator | transform | `app/Icm/Validator.php` `agentChannelErrors` (:714-734) + `ValidationReport` | exact |
| `app/Icm/Walks/WalkFile.php`, `WalkCheck.php`, `WalkFingerprint.php`, `WalkGateResult.php` | value object / utility | transform | `app/SendRule/ClassifierResult.php` (readonly VO with `toArray`), `SyncResult.php` | role-match |
| `app/Icm/Walks/ScratchWorld.php` | service | request-response (swap/restore) | `tests/Pest.php` beforeEach (:46-89) lists the same globals | partial |
| `app/Icm/Walks/WalkRunner.php` | service | event-driven (drives real path) | `runQueuedFrontDeskJobs()` `tests/Pest.php:174-189` | partial |
| `app/Icm/Walks/WalkGate.php` | service | batch | `FolderSync::sync()` (:60-100) | role-match |
| `app/Icm/Walks/CaptureDispatchInbound.php` | service | event-driven | `app/Actions/DispatchInbound.php` (subclass) | exact |
| `app/Icm/Walks/CalibrationSet.php`, `CalibrationReport.php` | service | batch/transform | `app/Console/Commands/GateExport.php` | role-match |
| `app/Ai/ReplayFrontDeskAgent.php`, `RecordingFrontDeskAgent.php` | service | request-response | `app/Ai/RunFrontDeskAgent.php` | exact |
| `app/SendRule/ReplayClassifier.php`, `RecordingClassifier.php` | service | request-response | `app/SendRule/FakeClassifier.php` | exact |
| `app/Models/WalkRecording.php` | model | CRUD | `app/Models/IcmVersion.php` | exact |
| `database/factories/WalkRecordingFactory.php` | factory | CRUD | `database/factories/IcmVersionFactory.php` | exact |
| `database/migrations/2026_11_2x_..._create_walk_recordings_table.php` | migration | CRUD | `2026_11_02_000004_create_booking_sends_table.php` | exact |
| `app/Console/Commands/WalksRecord.php`, `WalksReport.php` | command | batch | `app/Console/Commands/GateExport.php`, `IcmSync.php` | exact |
| `app/Icm/Loader.php` (:24, :31), `Validator.php` (:125), `FolderSync.php` (:79-92, :217-224, :281, :305), `SyncResult.php`, `Changelog.php` (:40-44) | modify | - | themselves | - |
| `app/Actions/RunFrontDesk.php` (:1587) | modify | - | itself | - |
| `app/Ai/FrontDeskPrompt.php` (new method at end) | modify | transform | itself | - |
| `examples/example-hvac/tests/*.md` | config | - | `examples/example-hvac/business/services/ac-repair.md` front matter style | role-match |
| `tests/Pest.php` helpers + strip `tests/` | test infra | - | `resetRecordsToTrackedFiles` use at :88, `exampleFolderFileCount` :319 | exact |
| `tests/Feature/FrontDesk/ReplyCapTest.php` (add after :167) | test | CRUD | same file :37-47, :78-167 | exact |
| `tests/Feature/Filament/BusinessFolderWalkLinesBrowserTest.php` | browser test | request-response | `ProjectsRelationManagerKeyboardTest.php`, `ContactScreenKeyboardTest.php:217-241`, `BookingPageBrowserTest.php:198,325` | exact |
| `tests/Feature/Walks/*`, `tests/Feature/Icm/WalkFileTest.php`, `WalkGateTest.php`, `tests/Unit/Icm/Walk*Test.php` | test | - | `tests/Feature/Icm/ValidatorTest.php`, `GateExportTest.php` | role-match |

## Pattern Assignments

### `app/Icm/Walks/WalkFileChecks.php` (validator, transform)

**Analog:** `app/Icm/Validator.php` `agentChannelErrors` (:714-734); the call goes on the existing `array_push` at :125 (line-neutral).

**Error shape and loop** (Validator.php:714-733):
```php
foreach ($files as $file) {
    $channel = $file->frontMatter['channel'] ?? null;
    if ($file->kind !== IcmFileKind::Typed || $file->type !== 'agent' || ! is_string($channel) || $channel === '') { continue; }
    ...
    $errors[] = $this->error($file->path, 'channel', 'channel '.PlainWords::oneLine($channel)." already has an agent in ...");
}
return $errors;  // list<array{file: string, field: ?string, message: string}>
```
**Hook line** (Validator.php:125): append `...(new WalkFileChecks)->errors($files)` to the array_push list. Do not add a `fileErrors` match arm. Because `WalkFileChecks` is constructed by `new`, it needs no constructor param (HandBuiltRegistrySyncTest builds Validator with 3 args).
**Refusal line style** (ValidatorTest.php dataset, :14-37): `'business/hours.md: weekly mon: open must be a 24-hour time such as 08:00 (you wrote 8:00)'`, `'...: price_cents is missing; it is required unless ...'`, `'... must be one of ...'` via `PlainWords::render('In', ...)`. Echo owner values through `PlainWords::oneLine`. `ValidationReport` (:34-60) runs `HiddenCharacters::marked` on every finished line. Copy the D-10 wording from RESEARCH Pattern 4.
**Loader change**: move `'walk_test'` from `LATER_SPRINT` (Loader.php:31) to `TYPED` (:24) on the same lines.

### `app/Icm/FolderSync.php` + `SyncResult.php` + `Changelog.php` (gate hook)

**compile() init and arm** (FolderSync.php:281, :305): init array `['service' => [], 'knowledge' => [], 'agent' => [], 'offer' => [], 'room' => [], 'connector' => []]` gains `'walk_test' => []`; add arm `'walk_test' => $compiled['index']['walk_test'][] = $file->path,` beside `'connector' => $compiled['index']['connector'][] = $file->path,` (otherwise `default =>` keeps only the last file).
**Gate placement** (FolderSync.php:79-92): after the `Unchanged` return, before `DB::transaction`. Reject path is existing `$this->reject($folder, $report, $fileCount, $fingerprint, $active, $root)` (:75-77).
**SyncResult** is `readonly` with 6 positional params (SyncResult.php:17-24). Add trailing optional `array $changelogErrors = []`, `array $staleNotes = []`. `recorded()` rebuilds it positionally at FolderSync.php:217-224: pass both new fields there or a changelog write failure silently drops them.
**Changelog** (Changelog.php:40-44):
```php
SyncOutcome::Rejected => ['Rejected', [...$result->errors, $this->liveBullet()]],
SyncOutcome::Activated => ["Live as {$result->version->label()} ({$result->fileCount} files)", array_map($this->noticeBullet(...), $result->notices)],
SyncOutcome::Unchanged => ['No changes', ["Already active: {$result->version->label()}"]],
```
Change Rejected to `[...($result->changelogErrors ?: $result->errors), ...$result->staleNotes, $this->liveBullet()]`; Activated appends staleNotes; Unchanged untouched.
**IcmSync console** (IcmSync.php:28-45): loop `foreach ($result->notices as $notice) { $this->warn($notice); }` is the pattern for printing `staleNotes` as `warn`.

### `app/SendRule/ReplayClassifier.php` / `RecordingClassifier.php` (service, request-response)

**Analog:** `app/SendRule/FakeClassifier.php`. Class docblock explains reading vs words check by empty draft.
**Interface** (Classifier.php): `public function classify(string $customerMessage, string $draftReply, TriageQuestions $questions): ClassifierResult` and `@throws ClassifierFailed`.
**Reading vs words test** (FakeClassifier.php:126-135):
```php
$this->calls[] = ['customer_message' => $customerMessage, 'draft_reply' => $draftReply];
$isReading = $draftReply === '';
...
if ($failure !== null) { throw new ClassifierFailed($failure); }
```
**Build result** (FakeClassifier.php:149-155 and ClassifierResult.php:24-34 shape):
```php
return new ClassifierResult(model: 'fake-jev', intent: $intent, intentConfidence: $confidence,
    intentProbabilities: $probabilities, yesNo: array_merge(array_map(fn () => 0.01, $questions->yesNo), $overrides));
// stored shape: ['model','intent','intent_confidence','intent_probabilities','yes_no']
```
Replay: rebuild from `toArray()` shape; out of recorded calls throws `ClassifierFailed` (D-27 fail closed). Recording decorator wraps `app(Classifier::class)` and stores `$result->toArray()` plus `customer_message`, `draft_reply`, kind. Extend `FakeClassifier` with `answerSequence()` for recording tests (no map citations, free to edit).

### `app/Ai/ReplayFrontDeskAgent.php` / `RecordingFrontDeskAgent.php` (service, request-response)

**Analog:** `app/Ai/RunFrontDeskAgent.php`. Must live in `App\Ai` and never name `FrontDeskAgent` outside it (ArchitectureTest :54-56); the replay class does not need the SDK at all.
**Signature to override** (RunFrontDeskAgent.php:59-66... handle at ~:59):
```php
public function handle(AgentFile $agent, Conversation $conversation, Collection $burst, ?Message $waitingDraft = null, bool $coverBoth = false, ?CarbonInterface $budgetFrom = null): AgentRun
```
Constructor is `__construct(private readonly FrontDeskPrompt $prompt)`; a subclass must call or pass it.
**Return shape** (RunFrontDeskAgent.php:84): `return new AgentRun(AgentReply::fromStructured($response->toArray()), $provider, $model, $failedAttempts, $startedAt);` Replay: `new AgentRun(AgentReply::fromStructured($stored), 'recorded', 'recorded', [], now()->toImmutable())`. Recording wrapper stores `$run->reply->toArray()`. A turn can make up to two agent calls, so store a list per turn.
**Failure type**: `throw new FrontDeskUnavailable($failedAttempts)` (:106) is how the chain reports exhaustion; use it for "needed a reply that was not recorded".

### `app/Icm/Walks/CaptureDispatchInbound.php`

**Analog:** `app/Actions/DispatchInbound.php` (its docblock says it is the only place ProcessInbound is queued). Subclass, override `handle(Message $message): bool` to record the message, and run `ProcessInbound` by hand as `runQueuedFrontDeskJobs()` does (`Pest.php:174-189`): `app()->call([$job, 'handle'])`.

### `app/Icm/Walks/ScratchWorld.php` (isolation)

No single analog; compose from `tests/Pest.php` beforeEach (:46-89), which sets the same globals:
```php
config(['channels.text.driver' => 'fake', ...]);
Http::preventStrayRequests();
$this->app->instance(Classifier::class, new FakeClassifier);
config(['icm.path' => $folder]);
```
Restore discipline (RESEARCH Pattern 1 table). Use `Illuminate\Filesystem\Filesystem`, never the `File` facade (arch :42-48). `Carbon::setTestNow($previous)` restore the previous value, not null.

### `app/Models/WalkRecording.php` (model, CRUD)

**Analog:** `app/Models/IcmVersion.php` (:1-52)
```php
#[Fillable(['fingerprint','git_sha','compiled', ...])]
class IcmVersion extends Model
{
    /** @use HasFactory<IcmVersionFactory> */
    use HasFactory;
    protected function casts(): array { return ['compiled' => 'array', 'activated_at' => 'datetime', ...]; }
}
```
Use `#[Fillable([...])]` attribute, `casts()` method, `payload` => `'array'`, `recorded_at` => `'datetime'`.

### `database/factories/WalkRecordingFactory.php`

**Analog:** `IcmVersionFactory.php:1-52`: `@extends Factory<IcmVersion>`, `definition()` with `'fingerprint' => hash('sha256', fake()->unique()->uuid())`, named states (`active()`, `rejected()`) via `$this->state(fn (array $attributes) => [...])`. Add states `stale()` and `calibration()`. No money columns.

### `database/migrations/..._create_walk_recordings_table.php`

**Analog:** `2026_11_02_000004_create_booking_sends_table.php`. Must run on sqlite and pgsql: use only portable builders (`json`, `string`, `timestamp`), no raw SQL.
```php
return new class extends Migration
{
    /** Run the migrations. (docblock explaining the key, as booking_sends does) */
    public function up(): void
    {
        Schema::create('booking_sends', function (Blueprint $table) {
            $table->id();
            ...
            $table->timestamps();
            $table->unique(['appointment_id', 'lead_minutes']);
        });
    }
    public function down(): void { Schema::dropIfExists('booking_sends'); }
};
```
Table: `id`, `kind`, `walk_path`, `script_hash` string(64), `fingerprint` string(64), `payload` json, `recorded_at` timestamp, timestamps, `unique(['walk_path','script_hash'])`. Name the file after the newest existing date (latest is `2026_11_07_000001_...`).

### `app/Console/Commands/WalksRecord.php`, `WalksReport.php` (command, batch)

**Analog:** `GateExport.php` (attributes, JSON/RAW output) and `IcmSync.php` (:11-13).
```php
#[Signature('gate:export {--since= : Only decisions on or after this date (YYYY-MM-DD)} {--without-text : ...}')]
#[Description("Print the front desk's send-or-draft decisions ...")]
class GateExport extends Command
{
    public function handle(): int { ... $this->error('...'); return self::FAILURE; ... return self::SUCCESS; }
```
Raw write of customer text (GateExport.php:60-66): `$this->getOutput()->write($line.PHP_EOL, false, OutputInterface::OUTPUT_RAW);` so a customer's `<info>` is not read as console formatting; use this in the report. Inject collaborators in `handle(FolderSync $folderSync)` like IcmSync.php:17. Docblock style: explain decision ids and that files are not written. Command tests: `tests/Feature/Records/RecordsRebuildTest.php`, `tests/Feature/FrontDesk/GateExportTest.php` use `Artisan::call` or `$this->artisan(...)`; GateExportTest builds fixtures with a helper (`gateExportVerdict(...)`, :11-33) and asserts output lines. Do not call a live provider in any test: use `scriptFrontDeskReplies(...)` and `fakeClassifier()`.

### `app/Actions/RunFrontDesk.php` D-20 (line-neutral edit)

**Current** (RunFrontDesk.php:1587):
```php
->where(fn ($query) => $query->whereNull('kind')->orWhere('kind', '!=', MessageKind::LifeSafety));
```
**Edit to** `->where(fn ($query) => $query->whereNull('kind')->orWhereNotIn('kind', [MessageKind::LifeSafety, MessageKind::BookingText]));` on one line, docblock reworded inside its three lines (:1577-1579), so ~40 map citations hold. Update `map/objects/conversations/conversation-and-pause.md:21, :32` in the same commit; run check_map.py.

### `tests/Feature/FrontDesk/ReplyCapTest.php` addition (after :167)

**Helper** (:37-47): `replyCapAgentTextsSent($conversation, [5,10,...], MessageKind::BookingText)`; assertions via `replyCapTextsToDana()`.
**Pattern to copy** (:152-167, the "does not count ... safety line" test):
```php
$conversation = replyCapDanaTexts();
replyCapAgentTextsSent($conversation, [1, 2, 3, 4, 5, 6, 7], MessageKind::LifeSafety);
scriptFrontDeskReplies(['reply' => 'Yes, he is on the way.', 'label' => 'faq_answer']);
runQueuedFrontDeskJobs();
expect(replyCapTextsToDana())->toHaveCount(1)->and($conversation->fresh()->isPaused())->toBeFalse();
```
New case: seed 6+ `MessageKind::BookingText` rows in the last hour and 20 in the last day, expect still answered, not paused. Keep a case that holding replies still count.

### `tests/Pest.php` helpers

**Strip walks** (Pest.php:85-88): add `File::deleteDirectory($folder.'/tests');` after `File::delete($folder.'/changelog.md');`. Update doc at :316-318 (`exampleFolderFileCount()` returns 29 and stays 29).
**New helpers** follow the file's docblock-plus-function style, e.g. `scriptFrontDeskReplies` (:149-168) with a `$defaults` array merged `[...$defaults, ...$reply]`. Add `layExampleWalks(string ...$names)`, `minimalWalkFile()`, `seedRecording(...)`; the dataset rows in RoomOwnershipTest.php:80 load before the app boots, so use a plain constant string there, not a helper that needs the app.
`pageAxeReport(Page)` (:610-640): add an optional contrast switch; options array today is
```php
'runOnly' => ['type' => 'tag', 'values' => ['wcag2a','wcag2aa','wcag21a','wcag21aa','wcag22aa']],
'rules' => ['target-size' => ['enabled' => true]],
```
add `'color-contrast' => ['enabled' => false]` when exempt.

### `tests/Feature/Filament/BusinessFolderWalkLinesBrowserTest.php` (browser)

**Setup** (ProjectsRelationManagerKeyboardTest.php:58-77): `$this->actingAs($owner)`, build the world before `visit()` (plugin starts the browser only if the body calls it), `$page = visit($url); $page->assertSee('...'); $browser = $page->page();`. Desktop vs phone: `visit($url)->on()->mobile()` (:70 area) or the form Ian asked for, `visit(...)->resize(375, 812)` (BookingPageBrowserTest.php:198) and datasets `'at 375 CSS px' => [375], 'at desktop width' => [1280]` (:325-337).
**Axe exclusion pattern** (ContactScreenKeyboardTest.php:217-241):
```php
$context = json_encode(['exclude' => [['.fi-in-placeholder']]], JSON_THROW_ON_ERROR);
$options = json_encode(['runOnly' => [...], 'rules' => ['target-size' => ['enabled' => true]]], JSON_THROW_ON_ERROR);
$report = $browser->evaluate(<<<JS
async () => { const results = await window.axe.run({$context}, {$options}); return { checked: ..., violations: results.violations.map(v => ({ id: v.id, impact: v.impact, help: v.help, targets: v.nodes.map(n => n.target) })) }; }
JS);
```
State in the docblock why contrast is exempt (Phase 11 gap, as the Contact test's docblock does). World: `IcmVersion::factory()->active()` plus `->rejected()` with `errors` set to a failed-walk line, an unrecorded line and a walk-file line (factory state at IcmVersionFactory.php:52 sets `compiled => null`). Helpers: `focusedName`, `pressKey`, `expectFocusOn` in Pest.php. Scroll-width check at 375: `document.documentElement.scrollWidth <= window.innerWidth`.

### `examples/example-hvac/tests/*.md`

No walk analog exists (folder holds only README.md). Copy front matter style from RESEARCH Pattern 4 (flat YAML, plain `name`, `persona`, `channel: sms`). Plain names, fictional customers; no `!` or em dashes. Dated data: `first-visit-promotion.md` ends 2026-12-31, hours close 2026-11-26 and 2026-12-25, so name `start` inside those dates for offer, time or Dana walks. Also edit `tests/README.md:3`, `examples/example-hvac/CLAUDE.md:13`, `docs/SPEC.md:89,:195` ("Sprint 2" wording).

### `app/Ai/FrontDeskPrompt.php` fingerprint method

Add the new method after `textOf` (end of class, ~:469) so cited lines (:37-72, :93, :105, :136-151, :261-283) do not move. Hash `RULES`, agent body, loaded files, the two BOOKING constants and `TriageQuestions::toJevQuestions()` as JSON; never `instructions()` whole.

## Shared Patterns

### Fake collaborators in tests (no live model or Jev)
**Source:** `tests/Pest.php:46-75`: `Http::preventStrayRequests()`, `$this->app->instance(Classifier::class, new FakeClassifier)`, `FrontDeskAgent::fake()->preventStrayPrompts()`, `Queue::fake([ProcessInbound::class])`. Apply to every Walks test and the `walks:record` test. Executors never run the live recording.

### Plain refusal lines
**Source:** `app/Icm/ValidationReport.php` (:34-60) and `PlainWords::oneLine`/`render`. Apply to every owner-facing gate string; test no `!` or em dash.

### Architecture constraints
**Source:** `tests/Unit/ArchitectureTest.php` (:11-19 IcmVersion only in `App\Icm`, `App\Console\Commands`, `App\Filament\Pages`, `App\Models`, Database, Tests; :42-48 no `File` facade; :54-56 `FrontDeskAgent` only in `App\Ai`; :58-66 `TextChannel` only where allowed) and `AppointmentWritesTest.php`. Apply to all `app/Icm/Walks/*` and `app/Ai/*Replay*` files. Never name anything `Gate`.

### Business config read
**Source:** `.ai/rules/app.md`: read config only via `ActiveConfig` (Loader alone reads the folder). Walks read the candidate through `ActiveConfig::filesOfType('walk_test')` (ActiveConfig.php:309-315) in the scratch DB; `forgetInstance(ActiveConfig::class)` on open and close.

### Money and tests rules
**Source:** `.ai/rules/migrations.md`, `factories.md`, `tests.md`: all money integer cents (none expected in this phase). Pint after PHP edits; Pest feature tests; tests first.

### Map card updates
Any change to a cited file updates its card in the same commit and runs `python3 ~/Projects/cartographer/tools/check_map.py ~/Projects/ops-platform --extra-root examples/example-hvac` (see RESEARCH table of cards).

## No Analog Found

| File | Role | Data Flow | Reason |
|---|---|---|---|
| `app/Icm/Walks/ScratchWorld.php` | service | swap/restore | Nothing in app swaps DB, clock, channel and bindings at runtime; compose from Pest.php beforeEach and RESEARCH Pattern 1 |
| `examples/example-hvac/tests/*.md` walk files | config | - | No walk_test file exists yet; use RESEARCH Pattern 4 syntax |

## Metadata

**Analog search scope:** `app/Icm`, `app/Ai`, `app/SendRule`, `app/Console/Commands`, `app/Models`, `database/`, `tests/Pest.php`, `tests/Feature/{FrontDesk,Icm,Filament,Booking}`
**Files read:** about 25; tracked-source gate: the six core analogs verified with `git ls-files`
**Pattern extraction date:** 2026-11-21
