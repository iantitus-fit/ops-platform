# Phase 6: Front Desk and Team Rooms - Pattern Map

**Mapped:** 2026-10-27
**Files analyzed:** 19 (new or modified)
**Analogs found:** 18 / 19

All analog paths are git-tracked source (checked `git ls-files` for app/Rooms and tests/Feature/Rooms; none are capability mirrors).

## File Classification

| New/Modified File | Role | Data Flow | Closest Analog | Match |
|---|---|---|---|---|
| `app/Rooms/FrontDesk/FrontDeskServiceProvider.php` | provider (room wrap) | config/descriptor | `app/Rooms/Marketing/MarketingServiceProvider.php` | exact |
| `app/Rooms/Team/TeamServiceProvider.php` | provider (room wrap) | config/descriptor | `app/Rooms/Marketing/MarketingServiceProvider.php` | exact |
| `bootstrap/providers.php` (modify) | config | n/a | itself (append two) | exact |
| `app/Rooms/Room.php` (add `claimedWidgets`) | model/descriptor | n/a | itself (trailing optional field) | exact |
| `app/Rooms/RoomRegistry.php` (add `guardClaimedWidgets()`) | service | validation | `guardSections()` L302-319 | exact |
| `examples/example-hvac/rooms/front_desk/CONTEXT.md`, `rooms/team/CONTEXT.md` | config (ICM) | n/a | `examples/example-hvac/rooms/marketing/CONTEXT.md` | exact |
| `tests/Pest.php` `exampleFolderFileCount()` 23 -> 25 | test helper | n/a | itself | exact |
| `tests/Feature/Rooms/FrontDeskBootTest.php`, `TeamBootTest.php` | test | request-response | `tests/Feature/Rooms/MarketingBootTest.php` | exact |
| `tests/Feature/Rooms/RoomOwnershipTest.php` (:77,:160,:185), `tests/Unit/Icm/RoomChecksTest.php` | test | request-response | same file; 05 D-23 pattern (see Notes) | role |
| Migration: `owner_alerts` send claim + dedupe key | migration | CRUD | `2026_10_12_000001_add_dispatch_columns_to_messages_table.php` | role |
| Migration: `messages` claim token | migration | CRUD | same | role |
| `app/Actions/SendOwnerAlert.php` (modify: atomic claim) | action | request-response | `SafetyCheckStatus::claimThenSend()` L122-143 | exact |
| `app/Actions/RaiseOwnerAlert.php` (modify: dedupe key) | action | CRUD | itself L185-193 `alreadyRaised()` | exact |
| `app/Jobs/SendDueAlerts.php` (modify: pick up released claims) | job | batch | itself | exact |
| `app/Actions/ClaimInboundBurst.php` (modify: write token) | action | CRUD | itself L28-35 | exact |
| `app/Actions/ReportFrontDeskFailure.php` (modify: once per token) | action | CRUD | itself | exact |
| `app/Jobs/ProcessInbound.php` (modify: token source, catch + failed()) | job | event-driven | itself | exact |
| `database/factories/OwnerAlertFactory.php`, `MessageFactory.php` (new columns) | factory | n/a | themselves | exact |
| ALRT tests (send claim, dedupe, token, serialize-then-failed()) | test | simulated races | `tests/Unit/ArchitectureTest.php` style for D-09; no race analog | partial |

## Pattern Assignments

### `app/Rooms/FrontDesk/FrontDeskServiceProvider.php` and `app/Rooms/Team/TeamServiceProvider.php`

**Analog:** `app/Rooms/Marketing/MarketingServiceProvider.php` (whole file, 24 lines). No `register()` or `boot()`; only `room()`.

```php
namespace App\Rooms\Marketing;

use App\Rooms\Room;
use App\Rooms\RoomServiceProvider;

class MarketingServiceProvider extends RoomServiceProvider
{
    protected function room(): Room
    {
        return new Room(
            key: 'marketing',
            label: 'marketing',
            handles: ['lead_sources', 'offer'],
            sections: [MarketingSection::class],
            neverLeftOutBecause: 'it owns the lead sources file the sync requires',
        );
    }
}
```

Phase 6 differences (from CONTEXT):
- Front desk: `key: 'front_desk'`, `label: 'front desk'`, `handles` the sending, replies, triage and `agent` types (check actual type names in the file-type list; D-02), `slot: 'text'`, `slotInterface: 'App\Channels\TextChannel'` (string, D-19), `tables: ['messages','conversations']`, `claimedWidgets: [TodaysConversations::class, PausedConversationsWaiting::class]`, `neverLeftOutBecause` (discretion). No `sections` (it adds no widget; widgets stay discovered in `app/Filament/Widgets`).
- Team: `key: 'team'`, `tables: ['owner_alerts']`, `claimedWidgets: [DraftsWaiting::class]`, `neverLeftOutBecause` about the owner alert and the life-safety alert at any hour. No handles, no timeline entries, no morph map (D-05).
- `NewLeads` stays unclaimed (D-03).

**bootstrap/providers.php**: append after `GoogleBusinessProfileServiceProvider::class` with `use` lines in the existing alphabetical-ish import block; `AppServiceProvider` stays `[0]` (MarketingBootTest asserts this).

---

### `app/Rooms/Room.php` (new trailing `claimedWidgets`)

**Analog:** itself, constructor L26-38. Add after `neverLeftOutBecause` as the last optional param, with a `@param list<class-string> $claimedWidgets` PHPDoc line in the existing style. Existing named-argument callers are unaffected.

```php
        public array $timelineEntries = [],
        public array $morphMap = [],
        public ?string $neverLeftOutBecause = null,
    ) {}
```

Pointer only: do not feed it to `widgetClasses()` / `addWidgetsTo()` (`RoomRegistry.php:93,101`), so the panel is unchanged (D-18).

---

### `app/Rooms/RoomRegistry.php` (`guardClaimedWidgets()`)

**Analog:** `guardSections()` L302-319, and the `add()` call list L44-55.

```php
$this->guardSections($room);
$this->guardTimelineEntries($room);   // add guardClaimedWidgets($room) here

private function guardSections(Room $room): void
{
    foreach ($room->sections as $section) {
        if (! class_exists($section)) {
            continue;   // placeholder names allowed in registry tests
        }
        if (! is_subclass_of($section, RoomSection::class)) {
            throw new LogicException("Room [{$room->key}] lists section [{$section}], which is not a RoomSection. ...");
        }
        if ($section::roomKey() !== $room->key) {
            throw new LogicException("Room [{$room->key}] lists section [{$section}], which belongs to room [{$section::roomKey()}].");
        }
    }
}
```

For the new guard: D-18 says the class exists, is a Filament widget (`Filament\Widgets\Widget` subclass), and only one room claims it (loop `$this->rooms` like `guardSlot` L238 / `guardMorphMap` L275 do for "already used by room [x]"). Note the existing guard lets nonexistent class names through for registry tests; D-18 says "the class exists", so this guard should refuse a missing class (differs from `guardSections`). Messages follow `"Room [{$room->key}] ... Pick another name."` style, `LogicException`.

---

### Room CONTEXT.md files (example folder)

**Analog:** `examples/example-hvac/rooms/marketing/CONTEXT.md`

```markdown
---
type: room
owns: [business/lead-sources.md, business/offers/*.md]
reads: [business/profile.md]
writes_records: none
connector: none
---

# Marketing
This room holds ... It writes nothing to `records/`. ... It has no outside system.
```

Front desk: `owns` the four files (sending, replies, triage, agents/front-desk.md), `connector: none` (D-01, no connector file). Team: `owns: []`-style (check how an owns-nothing room is written in 04 D-21 and `NeverLeftOutTest`), `reads: [rules/sending.md, rules/replies.md, agents/front-desk.md]` folder paths only; "who resolved each draft" goes in the body as a plain sentence (D-04). Update `tests/Pest.php:293` `exampleFolderFileCount()` from 23 to 25 (the only allowed change besides D-14).

---

### Boot tests: `tests/Feature/Rooms/FrontDeskBootTest.php`, `TeamBootTest.php`

**Analog:** `tests/Feature/Rooms/MarketingBootTest.php` (L25-95 main test).

```php
beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('boots the marketing room', function (bool $empty) { ... 
    $version = activateExampleFolder();
    $config = app(ActiveConfig::class);
    $room = app(RoomRegistry::class)->get('marketing');
    expect($version->file_count)->toBe(exampleFolderFileCount() - ...)
    expect($room->label)->toBe('marketing')->and($room->handles)->toBe([...])
        ->and($room->slot)->toBeNull()->and($room->tables)->toBe([])->and($room->timelineEntries)->toBe([])->and($room->morphMap)->toBe([]);
    $contract = $config->roomContract('marketing');
    expect($contract['owns'])->toBe(SpecTables::patternsOwnedBy(File::get(base_path('docs/SPEC.md')), 'marketing'))
        ->and($contract['reads'])->toBe([...])->and($contract['writes_records'])->toBe([])->and($contract['connector'])->toBe('none');
    $providers = require base_path('bootstrap/providers.php');
    expect($providers[0])->toBe(AppServiceProvider::class)->and($providers)->toContain(MarketingServiceProvider::class);
```

Adaptations: assert `claimedWidgets`, and that `addWidgetsTo()` / the panel's widget list and order equal the pre-wrap list (D-18). No RoomSection so no axe call (A11Y starts Phase 11, 04 D-27). Keep the "no database-driver guard, no skip" rule: runs on SQLite and Postgres. Add the SPEC ownership rows for both rooms if `SpecTables` needs them (04 D-14, `SpecTablesTest`). Keep the tests-with-`breakFile()`/`rejectedErrors()` refuse-case style for any contract refusal (MarketingBootTest L170-185).

---

### Migrations (send claim, dedupe key, claim token)

**Analog:** `database/migrations/2026_10_12_000001_add_dispatch_columns_to_messages_table.php` (docblock explains the why; `Schema::table`, nullable timestamps, index, symmetrical `down()`):

```php
Schema::table('messages', function (Blueprint $table) {
    $table->timestamp('dispatched_at')->nullable();
    $table->timestamp('handled_at')->nullable();
    $table->index(['direction', 'dispatched_at']);
});
// down: dropIndex([...]) then dropColumn([...])
```

Naming: `2026_10_27_00000N_<verb>_<what>_to_<table>_table.php` (sequence per the `2026_10_12_00000N` style; newest existing is `2026_10_22_000001`). Read `.ai/rules/migrations.md` before writing.

Constraints:
- `owner_alerts` (create: `2026_10_12_000004`) has `conversation_id` nullable since `2026_10_15_000001`, `message_id`/`user_id` nullable. Unique index over nullable columns does not dedupe NULLs on SQLite or Postgres (CONTEXT discretion note). Use a single non-null string `dedupe_key` column that is NULL for business-wide alerts (D-08, NULLs distinct is exactly what is wanted there) and a built string such as `conversation:message:reason:user` for conversation alerts; put `unique('dedupe_key')`. Note `message_id` and `user_id` can be null on conversation alerts, so build the key with a placeholder like `0`/`none` rather than relying on columns.
- Send claim: nullable `claimed_at` timestamp on `owner_alerts`; released after 2 minutes (D-06/D-07).
- Messages: nullable `claim_token` (string/uuid) with index; written by `ClaimInboundBurst` (D-12).
- Existing 000015 migration shows SQLite `change()` caveat; avoid `->change()` where possible. Dev-DB duplicates: dedupe or delete before adding the unique index (discretion).

---

### `app/Actions/SendOwnerAlert.php` (atomic send claim)

**Analog:** `SafetyCheckStatus::claimThenSend()` (`app/SendRule/SafetyCheckStatus.php:122-143`):

```php
$claimed = SafetyCheckOutage::query()->whereKey($outage->id)->whereNull($column)->update([$column => now()]);
if ($claimed === 0) { return; }
try {
    $alerts = $send();
} catch (Throwable $exception) {
    SafetyCheckOutage::query()->whereKey($outage->id)->update([$column => null]);
    throw $exception;
}
if (! $alerts->contains(fn (OwnerAlert $alert): bool => $alert->sent_at !== null)) {
    SafetyCheckOutage::query()->whereKey($outage->id)->update([$column => null]);
}
```

**Current code to change** (`SendOwnerAlert::handle`, L35-62): the non-atomic guard then send:

```php
if ($alert->sent_at !== null || $alert->skipped_at !== null || $alert->failed_at !== null) { return; }
if (blank($alert->to_phone)) { $this->recordFailure($alert, 'No phone on file'); return; }
try {
    $providerMessageId = $this->textChannel->send($alert->to_phone, $alert->body);
} catch (RecipientOptedOut) { ... } catch (Throwable $exception) { report($exception); $this->recordFailure(...); return; }
```

Insert the conditional update immediately before `textChannel->send` (D-07): `OwnerAlert::query()->whereKey($alert->id)->whereNull('sent_at','skipped_at','failed_at')->where(fn ($q) => $q->whereNull('claimed_at')->orWhere('claimed_at','<=', now()->subMinutes(2)))->update(['claimed_at' => now()]) === 1`, and return if 0. Keep "never throws" (D-19 comment) and the `addToTimeline` calls. After the `RecipientOptedOut`/failure paths the row has `failed_at` so the claim does not matter; claim is only left to expire when the worker died. Keep `skip()` guard consistent.

### `app/Jobs/SendDueAlerts.php`

Query L27-33 already selects unended, due alerts, and calls `SendOwnerAlert::handle`; since the claim lives inside `handle()`, a released (expired) claim is picked up with no change to the selector. Only change if you want to skip freshly claimed rows in SQL. Docblock statement "running the sweep twice cannot text twice" stays true.

### `app/Actions/RaiseOwnerAlert.php` (unique dedupe key)

**Analog:** itself. `alreadyRaised()` (L185-193) stays as the cheap pre-check; the unique index is the real guard:

```php
return OwnerAlert::query()
    ->where('conversation_id', $conversation->id)
    ->where('message_id', $subject?->id)
    ->where('reason', $reason->value)
    ->where('user_id', $userId)
    ->exists();
```

Create at L74-84 gets `'dedupe_key' => ...`; catch `UniqueConstraintViolationException` (Illuminate\Database) on the `create()` and treat as already raised (return no alert for that recipient, send nothing). `forBusiness()` passes no key (D-08).

---

### `ClaimInboundBurst` (claim token) and `ReportFrontDeskFailure` / `ProcessInbound` (ALRT-02)

**Analog:** `app/Actions/ClaimInboundBurst.php:28-38`, same conditional-update shape; add the token in the same update (D-12 condition 1):

```php
->update(['handled_at' => now()]) === 1,   // becomes ['handled_at' => now(), 'claim_token' => $token]
```

Token source: the researcher's call (see 06-RESEARCH.md); constraints: failed() runs on a fresh unserialized job copy, so a token generated in `handle()` is invisible to it. A token generated at dispatch (`DispatchInbound`) and carried as a public constructor property on `ProcessInbound` survives serialization (`public function __construct(public int $messageId, public int $conversationId) {}`, L55). Debounce means only one dispatched copy runs. Take the token as a `handle()` argument to the claim. Then `failed()` selects `Message::where('claim_token', $this->token)` and picks the newest SMS, else the newest row (D-20), replacing the WR-22 query at L141-150.

`ReportFrontDeskFailure::handle` (check-then-write at L35-42) becomes a once-per-token atomic claim (D-11): e.g. a unique `dedupe_key` on the alert/notes or a conditional update on a report marker, executed before writing the note and raising the alert. Keep fixed-words policy (class docblock) and note-then-alert order. The `catch` in `ProcessInbound::handle` (L92-97) and `failed()` both call it with the token.

### Factories

**Analogs:** `OwnerAlertFactory::definition()` (list of nullable columns; add `'claimed_at' => null`, `'dedupe_key' => null`) and `MessageFactory` (`'claim_token'` default null; existing states `outbound()`, `draft()` use `$this->state(fn (array $attributes) => [...])`). Read `.ai/rules/factories.md`.

---

## Shared Patterns

### Conditional-update claim (atomic on SQLite and Postgres)
**Source:** `ClaimInboundBurst.php:28-35` and `SafetyCheckStatus.php:122-143`. Apply to: send claim, report claim, token write. Release on failure, never leave a claim without an outcome, 2-minute wait (D-06/D-07).

### Never throws / fixed words
**Source:** `SendOwnerAlert` docblock L28-34 and `ReportFrontDeskFailure` class docblock. Apply to: any alert or failure path. Exception messages stay out of notes and alerts.

### Room wrap
**Source:** `MarketingServiceProvider` + `MarketingBootTest`. Apply to: both providers, both boot tests, both CONTEXT.md files. Each wrap changes only the file count (D-14/D-15).

### Test-first, both databases
CLAUDE.md: tests first for sending; Pest tests; no DB-driver skip. Simulated races only (D-21). One test must `serialize()`/`unserialize()` the job and call `failed()` on the copy (D-12 condition 4). Run `QUEUE_CONNECTION=sync` in phpunit.xml:32.

### Arch rule for D-09 (no new test)
`tests/Unit/ArchitectureTest.php` already holds "only the outbound chokepoint and the alert sender send texts" and passes unchanged; the `'App\Channels\TextChannel'` string in the front desk provider is not a code use (D-19).

## No Analog Found

| File | Role | Reason |
|---|---|---|
| Simulated-race and serialize-then-`failed()` tests | test | No existing test simulates a lost claim or deserializes a job; model on `SafetyCheckStatus` tests (search `tests/` for `claimThenSend` use) and write new. |

## Notes for the planner

- D-14 prep plan: `RoomOwnershipTest.php:77,160,185`. The 05 D-23 pattern is the one used in Phase 5 (unit case with hand-built `RoomRegistry` first, then remove the feature test); find it in `tests/Unit/Icm/RoomChecksTest.php` and git log for Phase 5 before copying.
- Order: prep plan, front desk wrap, team wrap, then ALRT-01/02 (D-15).
- Every plan that changes an area updates `docs/BLUEPRINT.md` "Affects:" lines (CLAUDE.md).

## Metadata

**Analog search scope:** `app/Rooms`, `app/Actions`, `app/Jobs`, `app/SendRule`, `database/migrations`, `database/factories`, `tests/Feature/Rooms`, `tests/Unit/ArchitectureTest.php`, `bootstrap/providers.php`, `examples/example-hvac/rooms`
**Files scanned:** ~25
**Pattern extraction date:** 2026-10-27
