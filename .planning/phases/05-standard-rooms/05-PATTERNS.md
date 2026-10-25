# Phase 5: Standard Rooms - Pattern Map

**Mapped:** 2026-10-25
**Files analyzed:** 38 new or modified (groups below)
**Analogs found:** 36 / 38 (all tracked in git; the pretend room analogs are under `tests/`, which is tracked)

The pretend room is the worked example of every room part. Paths: `tests/Rooms/PretendRoom/*` (code) and `tests/Fixtures/pretend-room/*` (folder). Real rooms differ from it in three ways: no tables, no timeline entries, no morph map (D-05), and they register in `bootstrap/providers.php` rather than through `enablePretendRoom()`.

## File Classification

| New/Modified File | Role | Data Flow | Closest Analog | Match |
|---|---|---|---|---|
| `app/Rooms/CompanyKnowledge/CompanyKnowledgeServiceProvider.php` | provider | config/registration | `tests/Rooms/PretendRoom/PretendRoomServiceProvider.php` | exact (minus slot, tables) |
| `app/Rooms/Marketing/MarketingServiceProvider.php` | provider | registration | same | exact |
| `app/Rooms/ContentGeneration/ContentGenerationServiceProvider.php` | provider | registration | same | exact (adds fileTypes) |
| `app/Rooms/Money/MoneyServiceProvider.php` | provider | registration | same | exact (adds fileTypes) |
| `app/Rooms/GoogleBusinessProfile/GoogleBusinessProfileServiceProvider.php` | provider | registration + slot binding | same | exact |
| `app/Rooms/<Room>/<Room>Section.php` (x5) | component (Filament widget) | request-response (read ActiveConfig) | `tests/Rooms/PretendRoom/PretendRoomSection.php` | exact |
| `app/Rooms/ContentGeneration/ContentBriefType.php` | file type | transform/validate | `tests/Rooms/PretendRoom/PretendNoteType.php` | exact |
| `app/Rooms/GoogleBusinessProfile/GbpCategoriesType.php` | file type | validate | `PretendNoteType.php` | exact |
| `app/Rooms/Money/MoneyNotesType.php` (+ number-check helper) | file type + utility | validate/transform | `PretendNoteType.php`; `app/Support/PhoneNumber.php` for the skip | role-match |
| `app/Rooms/GoogleBusinessProfile/GoogleListingSource.php` | interface (slot) | request-response | `tests/Rooms/PretendRoom/PretendFeed.php`; `app/Channels/TextChannel.php` | exact |
| `app/Rooms/GoogleBusinessProfile/FakeGoogleListingSource.php` | service (fake driver) | request-response | `tests/Rooms/PretendRoom/FakePretendFeed.php`; `app/Channels/FakeTextChannel.php` | exact |
| `bootstrap/providers.php` (modify) | config | registration | itself | exact |
| `app/Icm/RoomChecks.php` (modify: never-left-out, router wildcard, `$folders` from typed files) | service | batch/validate | itself (`errors()` ~L70-100, `ownershipErrors()` ~L110-145, `claims()` ~L225-245) | exact |
| `app/Rooms/Room.php` (modify: optional never-left-out reason flag) | model (value object) | n/a | itself | exact |
| `app/Icm/Loader.php` (modify: hidden-name refusal) | service | file-I/O | itself (`load()` L70-95; `unrecognized(...)` helper) | exact |
| `app/Icm/Validator.php` (modify: `problemMessage` for `hidden_name`) | service | validate | itself | exact |
| `app/Icm/ValidationReport.php` (modify `lines()` L39-47) | utility | transform | itself; `PlainWords::oneLine` L63 | exact |
| `examples/example-hvac/rooms/<five>/CONTEXT.md` | config (ICM contract) | n/a | `tests/Fixtures/pretend-room/rooms/pretend/CONTEXT.md` | exact |
| `examples/example-hvac/rooms/{content_generation,google_business_profile,money}/<file>.md` | config (ICM file) | n/a | `tests/Fixtures/pretend-room/rooms/pretend/notes/first-note.md` | exact |
| `examples/example-hvac/connectors/google-business-profile.md` | config (connector) | n/a | `tests/Fixtures/pretend-room/connectors/pretend-feed.md` | exact |
| `tests/Feature/Rooms/<Room>BootTest.php` (x5) | test | request-response | `tests/Feature/Rooms/PretendRoomBootTest.php` L275-325 | exact |
| `tests/Feature/Rooms/NeverLeftOutTest.php`, `tests/Feature/Icm/HiddenCharacterTest.php`, type/number-check tests | test | validate | `tests/Feature/Rooms/RoomOwnershipTest.php`, `RoomContractTest.php` | role-match |
| `tests/Pest.php` `exampleFolderFileCount()` L291 | test helper | n/a | itself | exact |
| Fallout fixes: 11 existing test files, `AnnexRoom.php`, annex fixture | test | n/a | RESEARCH Q1 table (every line listed there) | exact |
| `docs/SPEC.md`, `docs/BLUEPRINT.md`, `docs/sprints/sprint-1.1.md` (walk-through log) | docs | n/a | Phase 4 edits to the same tables | exact |

## Pattern Assignments

### Room providers (all five)

**Analog:** `tests/Rooms/PretendRoom/PretendRoomServiceProvider.php`

**Imports and room() pattern** (lines 1-30; drop `tables`, `timelineEntries`, `morphMap`, and `slot` for the four slotless rooms):
```php
namespace App\Rooms\CompanyKnowledge;

use App\Rooms\Room;
use App\Rooms\RoomServiceProvider;

class CompanyKnowledgeServiceProvider extends RoomServiceProvider
{
    protected function room(): Room
    {
        return new Room(
            key: 'company_knowledge',                     // lower snake case, D-01
            label: 'company knowledge',                   // lower case; GBP is the open question (RESEARCH OQ1)
            handles: ['business', 'hours', 'service', 'knowledge', 'voice'],   // D-04
            sections: [CompanyKnowledgeSection::class],
        );
    }
}
```
Marketing: `handles: ['lead_sources', 'offer']`. Content generation, GBP, money: `fileTypes: [new ContentBriefType]` etc., no `handles` (they define their own type, and a room-defined type is not in `Loader::TYPED`).

**Slot binding pattern, GBP only** (PretendRoomServiceProvider lines 32-49; same shape as `app/Providers/AppServiceProvider.php` `TextChannel` binding):
```php
public function register(): void
{
    parent::register();

    $this->app->singleton(FakeGoogleListingSource::class);
    $this->app->bind(GoogleListingSource::class, function (Application $app): GoogleListingSource {
        $driver = config('google_business_profile.driver', 'fake');

        return match ($driver) {
            'fake' => $app->make(FakeGoogleListingSource::class),
            default => throw new InvalidArgumentException("Unknown Google Business Profile driver [{$driver}]. Use fake."),
        };
    });
}
```
and in `room()`: `slot: 'google_business_profile', slotInterface: GoogleListingSource::class`. `Room::slot` must equal the contract's `connector:` and the connector file's `slot:`. Do not copy `boot()` with `loadMigrationsFrom` (pretend room only; D-05 has no tables).

**Registration** (`bootstrap/providers.php`, whole file today):
```php
return [
    App\Providers\AppServiceProvider::class,          // must stay first: it binds RoomRegistry (AppServiceProvider docblock)
    App\Providers\Filament\AdminPanelProvider::class,
    // append the five room providers here
];
```

### Room sections (x5)

**Analog:** `tests/Rooms/PretendRoom/PretendRoomSection.php` (lines 12-36)
```php
class PretendRoomSection extends RoomSection
{
    public static function roomKey(): string { return 'pretend'; }

    /** @return list<string> */
    protected function contentLines(): array
    {
        $lines = [...];
        foreach (app(ActiveConfig::class)->filesOfType('pretend_note') as $note) { ... }
        return $lines;
    }
}
```
Per room (D-17), read only through `ActiveConfig` / `OfferSentences`, never the folder:
- company knowledge: `count(ActiveConfig::services())` and `ActiveConfig::file('business/faq.md') !== null`
- marketing: `count(ActiveConfig::leadSources())` and `app(\App\SendRule\OfferSentences::class)->liveToday()` (`app/SendRule/OfferSentences.php` L25-40; `isLive()` L47)
- content generation / money: `filesOfType('<type>') !== []`
- GBP: `filesOfType('gbp_categories')[0]['primary_category']` plus a fixed "not connected to Google" line. No `fetchListing()` call on the dashboard (D-11).
The empty line is platform-drawn by `RoomSection::emptyLine()` (`app/Rooms/RoomSection.php` L42-56); do not write one. No exclamation points or em dashes.

### New file types (ContentBriefType, GbpCategoriesType, MoneyNotesType)

**Analog:** `tests/Rooms/PretendRoom/PretendNoteType.php` (lines 12-35)
```php
class PretendNoteType implements RoomFileType
{
    public function type(): string { return 'pretend_note'; }

    public function rules(IcmFile $file): array
    {
        return ['title' => 'bail|required|string', 'pinned' => 'bail|required|boolean:strict'];
    }

    public function checks(IcmFile $file): array
    {
        if (/* fine */) { return []; }

        return [['file' => $file->path, 'field' => null, 'message' => 'a pinned note needs some words under its front matter']];
    }
}
```
Rules: keep reading only from the `IcmFile` (`ArchitectureTest` forbids file reads in `App\Rooms`). Error shape is `{file, field, message}`; quote any owner value through `PlainWords::oneLine()`. Type keys are lower snake case and must not be in `Loader::TYPED` or the reserved `room, connector, automation, walk_test`.
- `ContentBriefType` / `MoneyNotesType`: front matter is only `type` (rules empty or `type` only).
- `GbpCategoriesType::rules()`: `'primary_category' => 'bail|required|string'`, `'additional_categories' => 'nullable|array'`. Do not use `max:9` (prints wrongly for arrays, `PlainWords.php:32`); count in `checks()` and write the sentence. Compare with `mb_strtolower(preg_replace('/\s+/u', ' ', trim($v)))`.
- `MoneyNotesType::checks()`: scan `$file->body`; skip spans that `App\Support\PhoneNumber::toE164()` accepts (never for a bare 9-digit span); refuse 13-19 digit spans passing Luhn and `^\d{9}$` / `^\d{3}-\d{2}-\d{4}$`. Luhn (RESEARCH Code Examples, hand-rolled; Faker's is dev-only):
```php
private function passesCardCheck(string $digits): bool
{
    $sum = 0;
    foreach (array_reverse(str_split($digits)) as $position => $digit) {
        $value = (int) $digit * ($position % 2 === 1 ? 2 : 1);
        $sum += $value > 9 ? $value - 9 : $value;
    }
    return $sum % 10 === 0;
}
```
Message (D-16): "<file> has what looks like a card, bank routing or Social Security number; account, card and ID numbers never go in the folder."

Own each new file by exact path in the contract (`owns: [rooms/money/notes.md]`), not `rooms/money/*.md`: a second typed file is then refused by the existing unclaimed line and the empty line names the file (RESEARCH Pattern 3).

### GBP slot interface and fake

**Analogs:** `tests/Rooms/PretendRoom/PretendFeed.php` (interface, docblocked methods) and `FakePretendFeed.php` (fake that reaches nothing outside the app, remembers calls, `name()` returns `'fake'`).
```php
interface GoogleListingSource
{
    /** @return array<string, mixed> The listing as Google has it. */
    public function fetchListing(): array;
}
```
Read-only, one method, no send method, no chokepoint (D-11). The fake returns a fictional listing. Keep `name()` if you want the same driver-name assertion the pretend boot test uses (`expect(app($room->slotInterface))->toBeInstanceOf(Fake...::class)`).

### Example-folder contracts

**Analog:** `tests/Fixtures/pretend-room/rooms/pretend/CONTEXT.md` (front matter L1-7, body L9-15)
```
---
type: room
owns: [rooms/pretend/notes/*.md]
reads: [business/profile.md]
writes_records: []
connector: pretend_feed
---
```
Real rooms differ: `writes_records: none` and `connector: none` (bare words, not `[none]`; `Loader::NONE_MEANS_EMPTY`), and each body states in plain words that the room has no outside system (ROOM-03). The `owns` list must equal the SPEC ownership table row character for character (`SpecTables::patternsOwnedBy`). Company knowledge `owns` lists `business/profile.md`, `business/hours.md`, `business/services/*.md`, `business/faq.md`, `voice/how-we-talk.md` per SPEC (router fix needed for the wildcard, see Shared Patterns). Body states which required files the room keeps (D-03).

**Connector file analog:** `tests/Fixtures/pretend-room/connectors/pretend-feed.md`
```
---
type: connector
system: Pretend Feed
direction: in
slot: pretend_feed
owns: [documents]
reads: [customers, projects]
schedule: hourly
---
```
GBP differs: `slot: google_business_profile`, `owns: none`, `reads: none` (bare word, D-10; `[none]` is refused, `tests/Feature/Rooms/ConnectorFileTest.php:125`). `direction` and `schedule` values wait for CONN-01 (04 D-24); copy whatever the existing connector test accepts. No credentials.

### Boot tests (x5)

**Analog:** `tests/Feature/Rooms/PretendRoomBootTest.php` lines 275-325 (`it('boots the pretend room', ...)` with an `empty` and a `with its example folder` dataset)
```php
$version = activateExampleFolder();
$config = app(ActiveConfig::class);
expect($version->file_count)->toBe(exampleFolderFileCount() + $extraTypedFiles)
    ->and($config->hasRoom('pretend'))->toBeTrue();
foreach (['business', 'hours', 'lead_sources', 'voice', 'sending_rules', 'set_replies', 'triage'] as $required) {
    expect($version->compiled['index'])->toHaveKey($required);
}
expect($config->roomContract('pretend')['owns'])->toBe(SpecTables::patternsOwnedBy(File::get(base_path('tests/Fixtures/pretend-room-spec.md')), 'pretend'));
$dashboard = $this->get('/admin')->assertOk()->assertSee('data-room-section="pretend"', false);
```
Differences for real rooms: no `enablePretendRoom()` (providers are registered); read the SPEC from `docs/SPEC.md` (the real SPEC row; owner word must be in the allow-list at `SpecTablesTest.php:67-79`, `'content generation'`, `'Google Business Profile'`, `'money'`); skip `Schema::hasTable` and the ping and timeline block (no tables or entries); empty variant asserts the platform-drawn empty line (`'<Label>: nothing set up yet. Add rooms/<key>/<file>.md to start.'`); company knowledge and marketing empty variants keep their required files (D-03). End each with `RoomSectionAxe::assertClean(RoomSectionAxe::report($page, '<key>'))` (`tests/Rooms/RoomSectionAxe.php`). No database-driver guard or skip: CI runs SQLite and Postgres. GBP boot test also: slot resolves to the fake, `Http::fake()` then `Http::assertNothingSent()` (`Http::preventStrayRequests()` is global, `tests/Pest.php:60`).

### `app/Icm/RoomChecks.php` (never-left-out, router wildcard, folders)

**Analog:** itself. Insert the never-left-out loop inside `ownershipErrors()` before `$this->claims(...)` and add a skip-types test in the unclaimed loop (RESEARCH Code Examples, prototype shape). Existing code to extend:
```php
foreach ($files as $file) {   // unclaimed loop, ~L128-138
    if ($file->kind !== IcmFileKind::Typed || in_array($file->type, ['room', 'connector'], true) || isset($misplaced[$file->path]) || isset($claimants[$file->path])) {
        continue;      // add: || in_array($file->type, $skipTypes, true)
    }
```
Router wildcard (Pitfall 1), at the `claims()` router exemption (~L235):
```php
if ($file->kind === IcmFileKind::Router && str_starts_with($file->path, "rooms/{$room->key}/")) { continue; }
// add: || str_contains($entry, '*')   (exact router names stay refused)
```
Fire the never-left-out line only when `rooms/<key>/` has no loaded file at all (Pitfall 2), and build `$folders` from Typed/Router files only (Pitfall 3). Error construction idiom: `$this->error($path, $field|null, $message)`; keep lines one-per-problem, in `PlainWords` style, built from `PlainWords::oneLine()` for owner values. Tests-first (CLAUDE.md rule for sync work).

### `app/Icm/Loader.php` and `ValidationReport.php` (D-19)

**Loader analog:** itself, `load()` lines 79-82 (the README skip is the first thing in the loop; run the name check on the full relative path before it):
```php
foreach ($finder as $file) {
    if ($file->getFilename() === 'README.md') { continue; }
    $icmFile = $this->read($this->relativePath($file), $file);
```
Check with `preg_match('/[\p{Cc}\p{Cf}\p{Zl}\p{Zp}]/u', $path) !== 0` (the `!== 0` also refuses malformed UTF-8) and return the same Unrecognized shape the Loader already uses for other bad files; add the matching plain-words sentence beside the existing messages in `Validator::problemMessage`.

**ValidationReport analog** (lines 39-47):
```php
$sorted = $this->errors;
usort($sorted, fn (array $a, array $b) => strcmp($a['file'], $b['file']));
return array_map(fn (array $error) => "{$error['file']}: {$error['message']}", $sorted);
```
Wrap the finished line in `preg_replace_callback('/[\p{Cc}\p{Cf}\p{Zl}\p{Zp}]/u', ..., mb_scrub($line))` mapping `\n \r U+2028 U+2029 U+0085` to `[line break]` and the rest to `[hidden character]`. The `PlainWords::oneLine` sibling (L63-68) shows the `preg_replace('/\s+/u', ...)` idiom and its null problem on malformed input.

### Test fallout fixes

**Analog:** the RESEARCH Question 1 table is the authoritative list (file, line, cause, fix shape); assign each row to the plan whose room causes it (04 D-16). Pattern for the one allowed helper change, `tests/Pest.php:291-294`:
```php
function exampleFolderFileCount(): int
{
    return 14;   // becomes 23 with five contracts, three new files and the GBP connector file
}
```
Also replace the two hard-coded `14`s (`OfferValidationTest.php:85`, `JevClassifierTest.php:64`) with `exampleFolderFileCount()` and raise it openly (RESEARCH Pitfall 6, OQ5). Use a near-miss that differs on case-insensitive disks for `PretendRoomBootTest.php:115` (Pitfall 7). Never delete the two unreachable-branch tests without Ian's approval (OQ4).

## Shared Patterns

### Plain-words refusal shape
**Source:** `app/Icm/RoomChecks.php` `$this->error(file, field|null, message)`; `app/Icm/PlainWords.php` `oneLine()` L63
**Apply to:** every new check (never-left-out, categories, number check, hidden name). Messages are one line, start lower case, name the file, say what to do, never end in an exclamation point, no em dashes. Quote owner values only through `PlainWords::oneLine()`.

### Read business config only through ActiveConfig
**Source:** `.ai/rules/app.md`; `app/Icm/ActiveConfig.php` (`hasRoom`, `roomContract`, `filesOfType`, `file`, `services`, `leadSources`, `offers`)
**Apply to:** all five sections and any helper. No file reads in `App\Rooms` (`tests/Unit/ArchitectureTest.php`), no `env()` in `App`.

### Room-defined types never touch `Loader::TYPED`
**Source:** `app/Rooms/RoomRegistry.php` guards (`guardFileTypes`, `guardSlot`, `guardSections`)
**Apply to:** the three file types and the GBP slot. Keys lower snake case; section `roomKey()` equals the room key; slot interface must exist.

### Contract values `none`
**Source:** `Loader::NONE_MEANS_EMPTY` (`app/Icm/Loader.php` ~L52, ~L194)
**Apply to:** every example contract (`writes_records`, `connector`, `reads`) and the GBP connector file's `owns`/`reads`. Bare `none`, never `[none]`.

### Code style
**Source:** `CLAUDE.md` / Boost rules
**Apply to:** all PHP: curly braces always, constructor promotion, explicit return types, PHPDoc with array shapes over inline comments, `php artisan make:class --no-interaction` for generic classes, `vendor/bin/pint --dirty --format agent` after edits, Pest tests via `php artisan make:test --pest`. Add a plain-sentence explanation of "more than one real provider in providers.php" and "a contract interface bound to a fake driver in a real room" to the sprint walk-through log at first use. `docs/BLUEPRINT.md` update with an "Affects:" line in the same commit as any decision change.

## No Analog Found

| File | Role | Data Flow | Reason |
|---|---|---|---|
| Number-check helper (Luhn plus span tokenizer) in `app/Rooms/Money/` | utility | transform | Nothing in the codebase scans text for numbers; use RESEARCH Q2 and its Luhn excerpt. `PhoneNumber::toE164()` is the only related piece. |
| Never-left-out constant or `Room` flag | model/config | n/a | No existing "required room" concept; shape is the planner's call (RESEARCH Pattern 2, A4). |

## Metadata

**Analog search scope:** `app/Rooms`, `app/Icm`, `app/Providers`, `app/SendRule`, `tests/Rooms`, `tests/Fixtures/pretend-room`, `tests/Feature/Rooms`, `tests/Pest.php`, `bootstrap`, `examples/example-hvac`
**Files scanned:** about 30 read or excerpted
**Tracked-source gate:** `git ls-files` confirmed every cited `app/`, `tests/`, `bootstrap/` and `examples/` path is tracked; no gitignored mirror paths are cited.
**Pattern extraction date:** 2026-10-25
