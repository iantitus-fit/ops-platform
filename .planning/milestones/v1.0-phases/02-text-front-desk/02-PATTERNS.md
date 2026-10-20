# Phase 2: Text Front Desk - Pattern Map

**Mapped:** 2026-10-11
**Files analyzed:** 52 new or modified (grouped below)
**Analogs found:** 44 exact or role-match / 52; 8 with no analog (listed at the end)

All analog paths below were confirmed git-tracked (`git ls-files`) or are in the tracked file list. No `.gsd` capability mirror paths are used. D-48 applies: every file RESEARCH.md placed under `app/Gate/` is under **`app/SendRule/`** here. `app/Ai/` is the second approved namespace.

## Project rules that apply (from `.ai/rules/index.md`)

| Glob | Rule file | What it demands | Applies to |
|------|-----------|-----------------|------------|
| `app/**` | `.ai/rules/app.md` | (1) Money is integer cents (`*_cents`, integer casts, never floats). (2) Business configuration is read only through `App\Icm\ActiveConfig`, never the ICM folder; only `App\Icm\Loader` reads the folder. | Every PHP file under `app/` below: `app/SendRule/*`, `app/Ai/*`, `app/Actions/*`, `app/Jobs/*`, `app/Channels/*`, `app/Http/*`, `app/Icm/*`, `app/Filament/*`, `app/Livewire/*`, `app/Models/*`, `app/Enums/*`, `app/Records/*`, `app/Providers/*` |
| `database/migrations/**` | `.ai/rules/migrations.md` | Money is integer cents. Also `MoneyIsIntegerCentsTest.php:31` fails any column named with `cents\|price\|cost\|amount\|total\|fee\|balance` unless it is an integer `*_cents` column (RESEARCH Pitfall 2). Gate detail goes in the `gate` JSON, never a `held_for_price` column. | all new migrations |
| `database/factories/**` | `.ai/rules/factories.md` | Money is integer cents. | `IntakeAnswerFactory`, `OwnerAlertFactory`, edits to `MessageFactory`/`ContactFactory`/`ConversationFactory`/`UserFactory` |
| `tests/**` | `.ai/rules/tests.md` | Money is integer cents (price-word tests use `price_cents`-style fixtures, never float dollars). Pest only; global helper functions need file-specific names (STATE 01-06 lesson). | every file under `tests/` |

Enforced by tests: `tests/Unit/ArchitectureTest.php` (only `App\Icm\Loader` uses Yaml/Finder; `IcmVersion` only in `App\Icm`, `App\Console\Commands`, `App\Filament\Pages`, `App\Models`, factories, seeders, tests; disk functions banned in `App\Http`, `App\Actions`, `App\Livewire`, `App\Filament`, `App\Channels`, `App\Jobs`, `App\Models`, `App\Records`, `App\Listeners`, `App\Events`, `App\Support`; `env()` banned in `App`). New `app/SendRule/*` and `app/Ai/*` code must respect these too, so use `ActiveConfig::versionId()` instead of `IcmVersion`.

## File Classification

| New/Modified File | Role | Data Flow | Closest Analog | Match |
|-------------------|------|-----------|----------------|-------|
| `app/SendRule/CodeChecks.php` | utility | transform | `app/Icm/Validator.php` (regex/plain-words checks) | role-match |
| `app/SendRule/Verdict.php` (+ `ReplyLabel` enum) | model/DTO | transform | `app/Actions/InboundData.php`, `app/Enums/MessageStatus.php` | role-match |
| `app/SendRule/Classifier.php` (interface) | service interface | request-response | `app/Channels/TextChannel.php` | exact |
| `app/SendRule/JevClassifier.php` | service | request-response (HTTP) | `app/Channels/TwilioTextChannel.php` (stub to fill) + RESEARCH "Code Examples" | role-match |
| `app/SendRule/FakeClassifier.php` | test double | request-response | `app/Channels/FakeTextChannel.php` | exact |
| `app/SendRule/DecideSendOrDraft.php` | service | transform | `app/Actions/SendOwnerReply.php` (whyCannotReply/whyBodyIsRefused refusal ladder) | role-match |
| `app/Ai/Agents/FrontDeskAgent.php` | service | request-response (LLM) | none in repo (laravel/ai convention); `vendor/laravel/ai` | no analog |
| `app/Actions/DispatchInbound.php` | action | event-driven (claim) | `app/Actions/RecordInbound.php` | role-match |
| `app/Actions/RunFrontDesk.php` (and `SendFixedReply`, `SendLifeSafetyLine`, `TakeOver`, `HandBack`, `ResolveDraft`) | action | request-response | `app/Actions/SendOwnerReply.php`, `CloseConversation.php`, `ReopenConversation.php` | exact |
| `app/Actions/OutboundSender.php` (shared chokepoint) | action | request-response | `app/Actions/SendOwnerReply.php` | exact |
| `app/Actions/SendOwnerReply.php` (modify) | action | request-response | itself | exact |
| `app/Actions/RecordInbound.php` (modify: claim, matched_by, opt-out, name) | action | CRUD | itself | exact |
| `app/Jobs/ProcessInbound.php` (modify) | job | event-driven | itself + `app/Jobs/RenderCustomerCard.php` | exact |
| `app/Jobs/SweepUndispatched.php`, `app/Jobs/SendDueAlerts.php` | job | batch | `app/Jobs/RenderCustomerCard.php` | role-match |
| `routes/console.php` (modify: Schedule) | config | batch | itself (only `inspire`) | partial |
| `app/Channels/TwilioTextChannel.php` (fill) | service | request-response | itself (stub) + `FakeTextChannel.php` | exact |
| `app/Channels/InboundText.php` (add `optOutType`) | DTO | transform | itself | exact |
| `app/Channels/FakeTextChannel.php` (opt-out simulation) | test double | request-response | itself | exact |
| `app/Http/Middleware/ValidateTwilioSignature.php`, `EnsureTextDriverIsTwilio.php` | middleware | request-response | `app/Http/Middleware/EnsureTextDriverIsFake.php` | exact |
| `app/Http/Controllers/TwilioTextWebhookController.php`, `TwilioStatusCallbackController.php` | controller | request-response | `app/Http/Controllers/FakeTextWebhookController.php` | exact |
| `app/Http/Requests/TwilioTextWebhookRequest.php` | request | validation | `app/Http/Requests/FakeTextWebhookRequest.php` | exact |
| `routes/web.php` (add Twilio routes) | route | request-response | itself, lines 14-16 | exact |
| `app/Icm/Loader.php` (TYPED/LATER_SPRINT) | utility | file-I/O | itself, lines 23, 30 | exact |
| `app/Icm/Validator.php` (agent, set_replies, triage, sending_rules rules) | utility | transform | itself, lines 21-27, 121-129 | exact |
| `app/Icm/FolderSync.php` (compile index) | service | batch | itself, lines 206-229 | exact |
| `app/Icm/ActiveConfig.php` (agent, replies, triage, versionId) | service | request-response | itself, lines 14-69, 118-121 | exact |
| `app/Icm/BusinessClock.php` (next opening) | utility | transform | `ActiveConfig::displayTimezone` + `hours()` | partial |
| `app/Enums/ActivityType.php`, new enums (`AgentMode`, `PausedReason`, `GateOutcome`, `MessageKind`) | enum | n/a | `app/Enums/MessageStatus.php`, `ActivityType.php` | exact |
| `app/Records/CustomerCard.php` (match arms, labels, intake section) | service | transform | itself, lines 152-157, 236 | exact |
| `app/Providers/AppServiceProvider.php` (morph map, binds, scheduler dev cmd) | provider | config | itself, lines 40-67 | exact |
| `app/Models/IntakeAnswer.php`, `OwnerAlert.php` | model | CRUD | `app/Models/Message.php`, `Activity.php` | exact |
| migrations: messages cols, conversations pause, contacts opt-out, users phone, intake_answers, owner_alerts, backfill | migration | CRUD | `2026_10_11_131451_create_conversations_table.php`, `..._131452_create_messages_table.php` | exact |
| factories: `IntakeAnswerFactory`, `OwnerAlertFactory`, state additions | factory | n/a | `database/factories/MessageFactory.php` | exact |
| `app/Filament/Pages/Auth/RememberedLogin.php`, `EditProfileWithPhone.php` | page | request-response | `app/Providers/Filament/AdminPanelProvider.php` + vendor Filament `Login`/`EditProfile` | partial |
| `app/Providers/Filament/AdminPanelProvider.php` (profile, login, widgets) | provider | config | itself, lines 22-58 | exact |
| `app/Filament/Widgets/*` (4 table widgets) | component | CRUD (read) | `app/Filament/Resources/Conversations/Tables/ConversationsTable.php` | role-match |
| `app/Filament/Resources/Conversations/Pages/ViewConversation.php` (Take over / Hand back) | page | request-response | itself, lines 18-47 | exact |
| `app/Filament/Resources/Conversations/Schemas/ConversationInfolist.php` (phone layout) | component | request-response | itself | exact |
| `app/Livewire/ConversationThread.php` + view (draft block, banner, opt-out) | component | request-response | itself, lines 25-60 | exact |
| `app/Filament/Pages/BusinessFolder.php` (no-phone warning) | page | request-response | itself | exact |
| `config/gate.php`, `config/services.php` (twilio, jev) | config | n/a | `config/channels.php`, `config/icm.php` | role-match |
| `examples/example-hvac/agents/front-desk.md`, `rules/replies.md`, `rules/triage.md`, `rules/sending.md` (edit) | config (ICM) | n/a | `examples/example-hvac/rules/sending.md`, `voice/how-we-talk.md` | exact |
| `.github/workflows/tests.yml` | config | n/a | none | no analog |
| `docs/sprints/sprint-1.md` | docs | n/a | `docs/sprints/sprint-0.md` | exact |
| `tests/Feature/FrontDesk/*`, `tests/Feature/Channels/*`, `tests/Feature/Icm/AgentFile*` | test | n/a | `tests/Feature/Inbound/OwnerReplyTest.php`, `tests/Feature/Icm/ValidatorTest.php` | exact |
| `tests/Pest.php` (safety net) | test config | n/a | itself | exact |
| `tests/Unit/ArchitectureTest.php` (new rules for `App\SendRule`) | test | n/a | itself | exact |
| `map/*` cartographer cards | docs | n/a | none in repo | no analog |

## Pattern Assignments

### Outbound sends: `app/Actions/OutboundSender.php`, `SendFixedReply`, `SendLifeSafetyLine`, `ResolveDraft`, and the `SendOwnerReply` edit (action, request-response)

**Rule:** `.ai/rules/app.md`. **Analog:** `app/Actions/SendOwnerReply.php` (lines cited are from that file).

**Imports and constructor injection** (lines 3-29): constructor takes `TextChannel`, constants for limits.
```php
public const int MAX_LENGTH = 1600;
public function __construct(private readonly TextChannel $textChannel) {}
```

**Lock then send** (lines 46-59). Pitfall 15: the shared sender must take the same `reply:{id}` lock but wait briefly instead of throwing for agent sends (use `$lock->block(5)`), while owner sends keep `get()` and the `DomainException`.
```php
$lock = Cache::lock("reply:{$conversation->id}", 30);
if (! $lock->get()) {
    throw new DomainException('Another reply to this customer is being sent. ...');
}
try { return $this->send($conversation, $body); } finally { $lock->release(); }
```

**Refusal ladder, the place for D-40 (opt-out) and D-24/D-25 (paused)** (lines 138-170): add the opt-out and paused refusals here, as new early returns in `whyCannotReply()`, returning plain sentences that the reply box shows ("Opted out by texting STOP on <date>"). Life-safety bypasses paused but never opt-out, so give the shared sender a `bool $bypassPause` argument, not a bypass of the opt-out check.
```php
public function whyCannotReply(Conversation $conversation): ?string
{
    if ($conversation->status === ConversationStatus::Closed) {
        return 'This conversation is closed. Reopen it to reply.';
    }
    $contact = $conversation->contact;
    if (blank($contact->phone_e164)) { ... }
    return null;
}
```

**Queued -> channel -> sent, with failure kept** (lines 74-115, 126-136, 175-193): store `Queued` row first, call `$this->textChannel->send($contact->phone_e164, $body)`, catch `Throwable` into `recordFailure`, then in a `DB::transaction` set `Sent`, write an `ActivityType::ReplySent` activity with `subject_type => $message->getMorphClass()`, update `last_message_at` / `last_activity_at`, then `CustomerRecordChanged::dispatch($contact->id)`. Post-send recording failure is `report()`ed and never raised. Copy this whole skeleton into the shared sender; `SendOwnerReply`, agent sends, holding/handoff replies, life-safety and approved drafts all call it.

**D-54 draft becomes the sent message:** `ResolveDraft` updates the existing `status = draft` row (keep `original_body` when edited) and passes it through the sender's "send an existing row" path instead of `$conversation->messages()->create([...])` at line 74.

**Authors:** use `MessageAuthor::Agent` for agent/fixed/life-safety sends (enum has `Customer`, `Agent`, `Owner`), `MessageAuthor::Owner` for owner sends. `MessageFactory::outbound()` already models agent sends.

---

### `app/Actions/TakeOver.php`, `HandBack.php`, `ResolveDraft.php` (action, request-response)

**Analog:** `app/Actions/CloseConversation.php` and `ReopenConversation.php` (not re-read here; they are the existing single-purpose state-change actions with `isAvailableFor()` and `DomainException` refusals, called from `ViewConversation`).

**Call-site pattern** (`app/Filament/Resources/Conversations/Pages/ViewConversation.php` lines 21-45), reuse verbatim for Take over / Hand back (D-26: one control per conversation):
```php
Action::make('reopen')
    ->label('Reopen conversation')
    ->visible(fn (): bool => app(ReopenConversation::class)->isAvailableFor($this->getRecord()))
    ->action(function (ReopenConversation $reopenConversation): void {
        try {
            $reopenConversation->handle($this->getRecord());
        } catch (DomainException $exception) {
            // The refusal names the customer, and Filament renders a title as HTML.
            Notification::make()->title(e($exception->getMessage()))->danger()->send();
            return;
        }
        Notification::make()->title('Conversation reopened')->success()->send();
    }),
```
Always wrap notification titles in `e()` (D-29, Pitfall 16).

---

### `app/Actions/DispatchInbound.php`, `app/Jobs/ProcessInbound.php`, `SweepUndispatched.php`, `SendDueAlerts.php`, `routes/console.php` (D-42)

**Rule:** `.ai/rules/app.md`.

**Analog for the single entry point:** `app/Actions/RecordInbound.php` lines 40-67. Replace line 63 `ProcessInbound::dispatch($message->id)->afterCommit();` with the atomic claim, and also call it from the "already recorded" returns (lines 44-46 and 53-56) so a retried webhook with `dispatched_at` null gets queued (WR-02). Keep `CustomerRecordChanged::dispatch(...)` at line 64.
```php
// RecordInbound.php:42-46 (retry path to change)
$existing = $this->alreadyRecorded($data);
if ($existing !== null) {
    return $existing;
}
```
Claim (from RESEARCH Pattern 3):
```php
$claimed = Message::query()->whereKey($messageId)->whereNull('dispatched_at')->update(['dispatched_at' => now()]);
if ($claimed === 1) { ProcessInbound::dispatch($messageId); }
```
Transaction idiom to copy for the burst claim in `ProcessInbound` (handled_at): `DB::transaction(fn (): Message => $this->record($data))` at `RecordInbound.php:49`; unique-violation catch at lines 50-61 (`UniqueConstraintViolationException`).

**Job shell** (`app/Jobs/ProcessInbound.php` lines 1-27): `implements ShouldQueue`, `use Queueable;`, constructor-promoted `public int $messageId`. Add `#[DebounceFor(20, maxWait: 90)]` and `debounceId()` per RESEARCH Pattern 3; cannot also be `ShouldBeUnique`. First step of `handle()` is the DB claim, so a duplicate run is harmless. `NoActiveVersion` (`App\Icm\Exceptions\NoActiveVersion`) must make the job return quietly (Pitfall 7).

**Analog for sweep jobs:** `app/Jobs/RenderCustomerCard.php` (existing queued job, role-match). **Schedule:** `routes/console.php` currently holds only `Artisan::command('inspire', ...)` (lines 6-8); append `Schedule::job(new SweepUndispatched)->everyFiveMinutes()->withoutOverlapping();` and `Schedule::job(new SendDueAlerts)->everyMinute()->withoutOverlapping();` and import `Illuminate\Support\Facades\Schedule`. Scheduler is a first-time Laravel concept here: add a walk-through log line to `docs/sprints/sprint-1.md`.

---

### Twilio channel: `app/Channels/TwilioTextChannel.php`, `InboundText.php`, `FakeTextChannel.php` (service, request-response)

**Rule:** `.ai/rules/app.md`; arch tests ban disk functions in `App\Channels` and `App\Http` (use `$request->getContent()`, `config()`, never `env()`).

**Interface to implement** (`app/Channels/TextChannel.php` lines 7-23): `name(): string`, `send(string $toE164, string $body): string` (returns provider id), `parseInbound(Request $request): InboundText`.

**Stub to fill** (`TwilioTextChannel.php` lines 8-25): `name()` already returns `'twilio'`; replace both `throw new LogicException(self::NOT_BUILT)` bodies. `send()` does `Http::withBasicAuth(sid, token)->asForm()->post(".../Messages.json", ['To'=>..., 'MessagingServiceSid'=>..., 'Body'=>..., 'StatusCallback'=>...])->throw()` and returns `$response->json('sid')`; map HTTP 400 code 21610 to a typed exception that sets `contacts.sms_opted_out_at` (D-40).

**`parseInbound` shape to mirror** (`FakeTextChannel.php` lines 59-71):
```php
return new InboundText(
    provider: $this->name(),
    providerMessageId: (string) $request->input('MessageSid'),
    from: (string) $request->input('From'),
    to: $to === null ? null : (string) $to,
    body: (string) $request->input('Body'),
    numMedia: (int) $request->input('NumMedia'),
);
```
Add `?string $optOutType` to `InboundText` (current fields `provider, providerMessageId, from, to, body, numMedia`) and read `$request->input('OptOutType')` in both channels.

**Fake send logging pattern to keep** (`FakeTextChannel.php` lines 29-44): body logged only when `app()->environment('local')`. Extend the fake with a "refused" list so tests assert nothing was sent to an opted-out number.

**Driver binding** (`AppServiceProvider.php` lines 40-48): already `match ($driver) { 'fake' => ..., 'twilio' => ..., default => throw new InvalidArgumentException(...) }`. No change needed beyond constructor args.

---

### Twilio webhook: controllers, request, middleware, routes (controller/middleware, request-response)

**Analog:** `app/Http/Controllers/FakeTextWebhookController.php` lines 16-26.
```php
public function __invoke(FakeTextWebhookRequest $request, FakeTextChannel $channel, RecordInbound $recordInbound): Response
{
    $recordInbound->handle(InboundData::fromText($channel->parseInbound($request)));
    return response('<?xml version="1.0" encoding="UTF-8"?><Response></Response>', 200, ['Content-Type' => 'text/xml']);
}
```
Copy for `TwilioTextWebhookController`; branch before `RecordInbound` on `OptOutType` / whole-body STOP words (record opt-out on the contact, still return empty TwiML, no reply per D-39).

**Driver guard middleware:** `app/Http/Middleware/EnsureTextDriverIsFake.php` lines 17-22; mirror as `EnsureTextDriverIsTwilio` (`abort_unless(config('channels.text.driver') === 'twilio', 404);`).

**Signature middleware body:** RESEARCH Pattern 4 (verified vector `RSOYDt4T1cUTdK1PDd93/VVr8B8=`); signed URL comes from `config('services.twilio.webhook_url')`, never `fullUrl()`.

**Routes** (`routes/web.php` lines 14-16):
```php
Route::post('/webhooks/text/fake', FakeTextWebhookController::class)
    ->middleware(EnsureTextDriverIsFake::class)
    ->name('webhooks.text.fake');
```
Add `/webhooks/text/twilio` and `/webhooks/text/twilio/status` the same way. CSRF is already exempted for `webhooks/*` (`bootstrap/app.php:15`).

**Validation / 422:** `FakeTextWebhookRequest` (`app/Http/Requests/FakeTextWebhookRequest.php`, the 01-03/01-04 `failedValidation()` JSON 422 pattern); copy it for the Twilio request. The Twilio request must not require fields Twilio may omit.

---

### ICM sync: `Loader.php`, `Validator.php`, `FolderSync.php`, `ActiveConfig.php` (AGNT-01; utility/service, transform)

**Rule:** `.ai/rules/app.md` (config only through `ActiveConfig`; only `Loader` reads the folder).

**Loader** (`app/Icm/Loader.php` lines 23, 30): move `agent` from `LATER_SPRINT` to `TYPED`; add `set_replies`, `triage`.
```php
public const TYPED = ['business', 'hours', 'lead_sources', 'knowledge', 'service', 'voice', 'sending_rules'];
private const LATER_SPRINT = ['agent', 'automation', 'connector', 'walk_test'];
```

**Validator singletons** (`Validator.php` lines 21-27): add `'set_replies' => 'rules/replies.md'`, `'triage' => 'rules/triage.md'`.

**Validator per-type rules** (`Validator.php` lines 121-129 region, `match` arms returning Laravel rule arrays): copy the `voice` and `sending_rules` arms as the template.
```php
'sending_rules' => [
    'send_on_own' => 'bail|present|array',
    'send_on_own.*' => 'bail|string',
    'draft_for_owner' => 'bail|present|array',
    'draft_for_owner.*' => 'bail|string',
    'owner_alert' => 'bail|required',
],
```
Replace `owner_alert` with structured rules (`owner_alert.channels.*` in `Rule::in(['text'])` per Pitfall 6; reject `email`), tighten `send_on_own.*` to `Rule::in(['intake_question','scheduling','faq_answer'])` and `draft_for_owner.*` to `Rule::in(['price','promise','discount','complaint','other'])`, and a custom error for price/promise/discount/complaint in `send_on_own` ("price can never be sent without you..."). Reuse `Rule::in(...)` as at the `price_policy` rule (`['bail', 'required', Rule::in(['none', 'starting_at', 'fixed'])]`). Error wording convention (from `ValidatorTest.php` dataset): `'business/profile.md: timezone must be a time zone such as America/Chicago (you wrote Chicago)'`, via `PlainWords.php`; every new rule needs a dataset row there.

Second-agent-on-channel (D-35) follows `duplicateSlugErrors` and `singletonErrors` (called at `Validator.php` line 59): add an `agentChannelErrors` sibling. Provider check: `Ai::textProvider($name)` in try/catch (`LogicException`, `InvalidArgumentException`) mapped to plain words (RESEARCH laravel/ai section).

**Compile index** (`FolderSync.php` lines 206-229): service is keyed by slug; copy that arm for agents, keyed by channel. The default arm is the bug in Pitfall 4:
```php
match ($file->type) {
    'service' => $compiled['index']['service'][$file->frontMatter['slug'] ?? $file->path] = $file->path,
    'knowledge' => $compiled['index']['knowledge'][] = $file->path,
    null => null,
    default => $compiled['index'][$file->type] = $file->path,
};
```
Add `'agent' => $compiled['index']['agent'][$file->frontMatter['channel']] = $file->path,` and initialise `'agent' => []` in the `$compiled` literal at the top of `compile()`.

**ActiveConfig accessors** (`ActiveConfig.php` lines 53-71 and 118-121): copy the `voice()` / `sendingRules()` shape (`return $this->singleton('voice');`) for `replies()` and `triage()`; copy `services()`/`service(slug)` (index lookups via `$this->version()->compiled['index']['service'][$slug] ?? null` then `typedFile($path)`) for `agent(string $channel)`. Add `versionId(): int { return $this->version()->id; }` (Pitfall 5: arch test blocks `IcmVersion` outside `App\Icm`). Docblock style: one-sentence summary plus `@return array<string, mixed>`.

---

### Business-hours clock: `app/Icm/BusinessClock.php` (utility, transform)

**Rule:** `.ai/rules/app.md` (reads `hours()` through `ActiveConfig` only).
**Partial analog:** `ActiveConfig::timezone()` and `displayTimezone()` (lines 35-51) give the business time zone; `hours()` gives `weekly.{mon..sun}` as `closed` or `{open:'08:00', close:'17:00'}`, `closures` as `Y-m-d` strings (see `Validator::DAYS` and `dayRules`, lines 39, 38-56). Build with Carbon in the business zone; D-51's one-hour-before-close threshold is a config value in `config/gate.php`. No existing next-opening code; see "No Analog Found".

---

### Models, enums, migrations, factories

**Rule:** `.ai/rules/migrations.md`, `.ai/rules/factories.md`, `.ai/rules/app.md`.

**Migration analog (create table + partial unique index):** `database/migrations/2026_10_11_131451_create_conversations_table.php` lines 16-38.
```php
Schema::create('conversations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
    $table->string('status')->default('open');
    ...
});
DB::statement("create unique index conversations_one_open_per_contact on conversations (contact_id) where status = 'open'");
// down(): DB::statement('drop index if exists conversations_one_open_per_contact'); Schema::dropIfExists('conversations');
```
Copy for D-28's `create unique index messages_one_live_draft_per_conversation on messages (conversation_id) where status = 'draft'`. Use `Schema::table(...)` for added columns on `messages`, `conversations`, `contacts`, `users`. For `intake_answers` and `owner_alerts` copy the `messages` create (`...131452_create_messages_table.php`: `foreignId(...)->constrained()->cascadeOnDelete()`, `json('meta')->nullable()`, `index(['conversation_id', 'id'])`). `intake_answers.icm_version_id` is `foreignId('icm_version_id')->constrained()` (D-33). Column-naming guard: no `price`/`cost`/`amount`/`total`/`fee`/`balance` in names (put them in the `gate` JSON). Backfill migration sets `dispatched_at` and `handled_at` to `created_at` for existing rows (Pitfall 1).

**Models:** `app/Models/Message.php` and `Activity.php` (not re-read; they use casts for enum `status`/`author`/`direction` and `meta` as array). New columns need casts (`dispatched_at`, `handled_at`, `gate` array, new enums). `User.php` uses `#[Fillable(['name', 'email', 'password'])]`; add `'phone'`.

**Enums:** `app/Enums/MessageStatus.php` lines 5-12 (string-backed, TitleCase keys, already has `Draft`); `ActivityType.php` lines 5-10. Add cases for draft held, intake answer, takeover, hand back, opt-out, alert sent. Adding cases requires extending the exhaustive `match` in `CustomerCard.php:152-157` in the same task (Pitfall 8).

**Morph map** (`AppServiceProvider.php` lines 60-67): add `'intake_answer' => IntakeAnswer::class, 'owner_alert' => OwnerAlert::class` before any activity uses them as a subject.
```php
Relation::enforceMorphMap([
    'contact' => Contact::class,
    'conversation' => Conversation::class,
    'message' => Message::class,
    'user' => User::class,
]);
```

**Factories** (`database/factories/MessageFactory.php` lines 14-71): `definition()` plus named states (`outbound()`, `fromOwner()`, `web()`). Add states `draft()`, `held()`, `lifeSafety()` to `MessageFactory`; `paused()` / `optedOut()` to the conversation/contact factories; new `IntakeAnswerFactory`, `OwnerAlertFactory` in the same style (`@extends Factory<Model>` docblock, `fake()->...`).

---

### Customer card: `app/Records/CustomerCard.php` (service, transform)

**Analog:** itself. Exhaustive match at lines 152-157 (`[$label, $intro, $body] = match ($activity->type) { ActivityType::TextReceived => ..., ActivityType::FormReceived => ..., ActivityType::ReplySent => ..., ActivityType::Note => [...] };`) and hard-coded `"Reply from owner{$details}"` at line 236. Key the label on `$message->author` and add arms for every new `ActivityType`; add an "Intake answers" section (latest row per conversation/service/question, D-34). Existing tests: `tests/Feature/Records/CustomerCardTest.php`, `CardInjectionTest.php` (customer words stay quoted under a label).

---

### Filament: panel, profile, login, widgets, conversation view

**Rule:** `.ai/rules/app.md`. Skill: `filament-development`.

**Panel provider** (`app/Providers/Filament/AdminPanelProvider.php` lines 26-57): add `->profile(EditProfileWithPhone::class)` and replace `->login()` (line 30) with `->login(RememberedLogin::class)`; drop `AccountWidget`/`FilamentInfoWidget` from `->widgets([...])` (lines 40-43) and rely on `discoverWidgets(in: app_path('Filament/Widgets'), ...)` (line 39) for the four new widgets.

**Widgets:** table pattern from `app/Filament/Resources/Conversations/Tables/ConversationsTable.php` (note the `orderByRaw('last_activity_at is null')` guard noted at line 40 of `ContactsTable.php` for NULL ordering across SQLite/Postgres). Extend `Filament\Widgets\TableWidget`.

**Conversation view:** `ConversationInfolist.php` (Customer section, 4 columns): make `->collapsible()->collapsed()`, add the "needs you" block above the thread. `ConversationThread.php` lines 25-60 pattern:
```php
public function sendReply(SendOwnerReply $sendOwnerReply): void
{
    try { $sendOwnerReply->handle($this->conversation, $this->body); }
    catch (DomainException $exception) {
        Notification::make()->title(e($exception->getMessage()))->danger()->send();
        return;
    }
    $this->reset('body');
    Notification::make()->title('Reply sent')->success()->send();
}
```
Copy for `approveDraft`, `editAndSend`, `discardDraft` (all via the shared sender / `ResolveDraft`; the replaced-draft refusal "The customer wrote again, here's the new draft" is a `ValidationException` or `DomainException` that leaves the edit in the box). `#[Computed]` `replyBlockedReason()` already drives the "reply box replaced by a sentence" UI: reuse for the opt-out sentence. Existing test pattern: `Livewire::test(ConversationThread::class, ['conversation' => $conversation])->set('body', ...)->call('sendReply')->assertHasNoErrors();` (`OwnerReplyTest.php`).

**Business Folder warning (D-19):** `app/Filament/Pages/BusinessFolder.php`, tests in `tests/Feature/Filament/BusinessFolderPageTest.php`.

---

### ICM example folder files (config, ICM)

**Analog for `rules/replies.md`, `rules/triage.md`, `agents/front-desk.md`:** `examples/example-hvac/rules/sending.md`:
```
---
type: sending_rules
send_on_own: [intake_question, scheduling, faq_answer]
draft_for_owner: [price, promise, discount, complaint]
owner_alert: Text the owner when a draft is waiting or a customer asks for a person
---
(markdown body in plain words)
```
Front matter `type:` first, then flat keys, then a plain-words body. `voice/how-we-talk.md` carries `ai_disclosure`, `sign_off`, `never_say`. Edit `sending.md` to add `other` and the structured `owner_alert`. Update `agents/README.md` ("arrive in Sprint 1") and `rules/CONTEXT.md` router text in the same task.

---

### Tests: `tests/Feature/FrontDesk/*`, `Channels/*`, `Icm/AgentFile*`, `tests/Pest.php`

**Rule:** `.ai/rules/tests.md`. Skill: `testing-best-practices`.

**Global setup** (`tests/Pest.php` lines 15-30): `pest()->extend(TestCase::class)->use(RefreshDatabase::class)->beforeEach(...)` copies `examples/example-hvac` to a temp folder and sets `config(['icm.path' => $folder])`; add the Pitfall 7 safety net here (`Http::preventStrayRequests()`, `FrontDeskAgent::fake()->preventStrayPrompts()`, bind `Classifier` to `FakeClassifier`).

**Helper naming:** `OwnerReplyTest.php` lines 24-33 defines `openConversationWithQuestion()` as a file-level global function; helper names are global across the suite, so name new ones per file (e.g. `frontDeskOpenThread()`).

**Validator dataset pattern** (`tests/Feature/Icm/ValidatorTest.php` lines 22-45): rows of `[file, search, replace, expected plain-words line]`, run through `breakFile(...)` and read back through `rejectedErrors()`. Copy for every new agent/replies/triage/sending-rules rule. Convert the three `type: agent` fixtures in `ChangelogTest.php:72-76`, `SyncTest.php:48-65`, `BusinessFolderPageTest.php:134-138` to `type: automation` (Pitfall 3).

**Livewire/Filament tests:** `Livewire::test(...)` per `OwnerReplyTest.php`. **Arch tests:** add `arch('only SendRule code applies the send-or-draft decision')`-style rules in `tests/Unit/ArchitectureTest.php` in the existing `arch(...)->expect(...)->toOnlyBeUsedIn([...])` style (lines 1-27).

## Shared Patterns

### Plain-words refusals and notifications
**Source:** `SendOwnerReply.php` lines 142-170, `ViewConversation.php` lines 35-45.
**Apply to:** every Filament/Livewire action in this phase. Refusals are short sentences; Filament notification titles always pass through `e()`.

### Single outbound chokepoint
**Source:** `SendOwnerReply.php` (above).
**Apply to:** agent replies, holding/handoff, life-safety, approved drafts, owner alerts (alerts go to the owner's phone, so the owner's own opt-out/21610 must surface per Pitfall 17, but alerts do not use the customer-conversation pause).
Opt-out and paused checks live only here. The life-safety line passes pause, never opt-out.

### Config only through ActiveConfig
**Source:** `.ai/rules/app.md`, `app/Icm/ActiveConfig.php`.
**Apply to:** `CodeChecks` (reads `voice()['never_say']`), `DecideSendOrDraft` (reads `sendingRules()`), `RunFrontDesk` (loads whole files via `file()`), fixed replies (`replies()`), `BusinessClock` (`hours()`, `timezone()`), alerts (`sendingRules()['owner_alert']`). Numbers (thresholds, caps, windows) live in `config/gate.php`, read through `config()`, and are copied onto each verdict (D-45, D-46, D-47). The code-check word lists are code, not folder content (RESEARCH anti-patterns).

### Transactions and record-changed event
**Source:** `SendOwnerReply.php` lines 92-117; `RecordInbound.php` lines 49, 63-64.
**Apply to:** all actions that write messages/activities: wrap writes in `DB::transaction`, create a timeline `Activity` through `$contact->activities()->create([... 'subject_type' => $message->getMorphClass(), 'subject_id' => ..., 'occurred_at' => ...])`, then `CustomerRecordChanged::dispatch($contact->id)`.

### Logging customer data
**Source:** `FakeTextChannel.php` lines 29-44.
**Apply to:** `JevClassifier`, `TwilioTextChannel`, agent runs: log ids/lengths, never bodies outside `local`; never log the Twilio token, signature or API keys.

### Docblock and type conventions
**Source:** `SendOwnerReply.php`, `ActiveConfig.php`; Boost php rules: explicit return types, constructor promotion, `private const` / `public const int`, PHPDoc with array shapes, curly braces always. Run `vendor/bin/pint --dirty --format agent` after PHP edits; use `php artisan make:*` generators (`--no-interaction`).

### Walk-through log (CLAUDE.md)
**Apply to:** each new Laravel concept (scheduler, debounced jobs, partial unique index reuse, middleware signature check, Filament profile page, queued sweeps, laravel/ai agent): one line in `docs/sprints/sprint-1.md` (created in the first plan per D-55; format analog `docs/sprints/sprint-0.md` log at its end).

## No Analog Found

| File | Role | Data Flow | Reason |
|------|------|-----------|--------|
| `app/Ai/Agents/FrontDeskAgent.php` | service | request-response (LLM) | No agent exists; use RESEARCH Pattern 1 and `vendor/laravel/ai` (laravel/ai convention `app/Ai/Agents`; fakes keyed by agent class). |
| `app/SendRule/JevClassifier.php` (HTTP body) | service | request-response | No Http client usage in repo yet; use RESEARCH "Code Examples" and `Http::fake()`; interface shape from `TextChannel`. |
| `app/Icm/BusinessClock.php` | utility | transform | No date/hours math exists; use RESEARCH "Next opening time" and OQ-5/D-51. |
| `app/Filament/Pages/Auth/RememberedLogin.php`, `EditProfileWithPhone.php` | page | request-response | No custom Filament auth pages; subclass vendor `Filament\Auth\Pages\Login` / `EditProfile` (RESEARCH Filament section). |
| `.github/workflows/tests.yml` | config | n/a | No workflows exist; use RESEARCH INFRA-01 YAML. |
| `app/Filament/Widgets/*` | component | read | `discoverWidgets` is wired but no widget class exists; closest is `ConversationsTable.php`, vendor `TableWidget`. |
| `map/` cartographer cards | docs | n/a | Lives in Ian's `~/Projects/cartographer` rules; last task, with a human-verify checkpoint. |
| Debounced job / `Schedule::job` usage | job/config | event-driven / batch | No scheduled or debounced job precedent; `routes/console.php` is only `inspire`. |

## Metadata

**Analog search scope:** `app/`, `database/`, `routes/`, `tests/`, `examples/example-hvac/`, `config/`, `.ai/rules/`.
**Files scanned:** about 20 read in full or in ranges; tracked-file list reviewed (`git ls-files app tests database config routes`).
**Tracked-source gate:** all analog paths appear in `git ls-files` output; no gitignored mirror paths used.
**Pattern extraction date:** 2026-10-11
