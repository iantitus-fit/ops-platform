# Phase 1: Foundation - Research

**Researched:** 2026-10-10
**Domain:** Laravel 13 / Filament 5 / Livewire 4 back office; filesystem-driven configuration (ICM folder sync); inbound lead recording; rendered markdown snapshots
**Confidence:** HIGH for stack, framework behavior and pitfalls (most probed against the installed vendor code or run live this session); MEDIUM for a few Postgres-forward and Filament-layout recommendations (flagged inline)

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**Validator scope**
- **D-01:** Orientation files (`README.md`, `CLAUDE.md`, `START-HERE.md`, `CONTEXT.md`) are not validated and are not counted in the file count shown by `icm:status`.
- **D-02:** Files with a later-sprint type (`agent`, `automation`, `connector`, `walk_test`) are skipped and reported as "not supported yet". This is a notice, not an error, and does not block activation.
- **D-03:** An unknown `type:` value, or a missing `type:` on any other `.md` file, is a plain-words error.
- **D-04:** A failed sync reports **all** errors, grouped by file, never only the first. Message style: `business/services/ac-repair.md: price_policy must be one of none, starting_at, fixed (you wrote maybe)`.
- **D-05:** Cross-file and within-file checks that are plain-words errors: two lead sources with the same `key`; two service files with the same `slug`; duplicate intake `key`s within one service.

**Compiled version and ActiveConfig**
- **D-06:** The compiled version (`icm_versions.compiled`) holds every typed file's front matter **and** markdown body, plus the untyped router files (`CLAUDE.md`, `CONTEXT.md`, `START-HERE.md`) as raw text. Sprint 1 agents must be able to load any routed path whole from `ActiveConfig`, never from disk (DEC-10/11).
- **D-07:** `ActiveConfig` exposes typed accessors for what the engine uses (for example `business()`, `hours()`, `leadSource('angi')`, `service('ac-repair')`, `sendingRules()`, `voice()`) plus a raw `file('business/faq.md')` lookup for routed reads. No generic dot-path `get()`.

**Sync runs and changelog**
- **D-08:** If the candidate's fingerprint equals the active version's, the sync is a no-op: no new `icm_versions` row. It reports "Already active: vN, no changes" and still appends a short "no changes" entry to `changelog.md`, so every attempt is recorded.
- **D-09:** `changelog.md` entries are newest first: a dated heading with the outcome (`## 2026-10-10 14:32 · Rejected` / `## … · Live as v13 (14 files)` / `## … · No changes`), then one bullet per error. Times are in the business timezone from `business/profile.md`. If `profile.md` is the broken file, use the active version's timezone.
- **D-10:** The Sync Now button on the Business Folder page runs the same sync action as `icm:sync` **inline** (not queued) and shows the result and any errors in a Filament notification.
- **D-11:** No rollback command in Phase 1. Rollback means fixing the file and syncing again, so the folder stays the source of truth. A rejected sync already leaves the previous version live.

**Lead matching and attribution**
- **D-12:** Contact matching: by `phone_e164` first, then by lowercased email. If neither matches, create a contact. A form with neither phone nor email is rejected with a 422.
- **D-13:** If a form's email matches an existing contact whose phone is **empty**, fill in `phone_e164`. If the stored phone is **different**, keep the stored phone and note "form gave a different phone: +1…" on the timeline. A form phone never overwrites a stored one.
- **D-14:** Attribution is first touch on the contact: `contacts.lead_source_key` and `acquisition_cost_cents` keep the first source. Every later touch records its own source and `cost_cents` on its timeline activity, so a repeat charge from Angi is still visible.
- **D-15:** A source key not in `lead-sources.md` is accepted, never dropped. The raw key is stored, cost is null and "unknown source: xyz" is noted on the timeline. A form with no `source` gets a default key (`direct`/`unknown`). Texts are recorded with source `text`.
- **D-16:** **One open conversation per contact**, shared across channels. — **Reversibility:** one-way — the migration moves `channel` off `conversations`, and every Filament query and card render built on it would change. **This deviates from `docs/sprints/sprint-0.md`**: `channel` (sms, web, voice, email) moves from `conversations` to `messages`. A web form creates a message in the contact's open conversation. Any contact has at most one `open` conversation. An inbound after the conversation is closed **starts a new conversation**. Closed ones are kept as past threads.
- **D-17:** The owner's reply box sends through `TextChannel` when the contact has a phone. With no phone, the reply box is disabled and shows the contact's email.
- **D-18:** `messages.provider_message_id` is unique per provider (a composite unique on `provider` + `provider_message_id`), so a retried webhook cannot record a message twice (SPEC "The bridge" step 1).
- **D-19:** The default phone region for E.164 parsing is US, set in config.

**Customer cards and records/**
- **D-20:** Card filename is name slug plus contact id: `records/customers/dana-whitfield-17.md`. A contact with no name falls back to `contact-15551234567-17.md`. When a contact's name changes, the old file is removed on re-render. `INDEX.md` maps name, phone and file.
- **D-21:** A card carries the full timeline, newest first, with every message and form in full (SPEC "The bridge"). No cap in Phase 1.
- **D-22:** `open-leads.md` lists contacts with `status=lead` **and** an open conversation, newest activity first, showing name, source, waiting since and a last-message preview.
- **D-23:** In `examples/example-hvac/records/`, only `README.md` and `CLAUDE.md` are tracked in git, and everything else generated (`customers/`, `INDEX.md`, `open-leads.md`) is ignored. **Ignore rules must be scoped to `examples/*/records/`** so they cannot match `app/Records/` on a case-insensitive macOS filesystem.

### Claude's Discretion
- If the very first sync fails with `profile.md` broken and there is no active version, changelog times fall back to the app timezone (UTC), and the entry says so.
- Tests point `ICM_PATH` at a temporary copy of the example folder, so a test run never writes cards or changelog lines into the repo.
- Dashboard layout details (Filament table columns beyond those in sprint-0.md, timeline presentation on the contact view), `/dev/chat` refresh mechanism (Livewire polling is fine), and the seeder's fictional data follow `docs/sprints/sprint-0.md` Step 3.
- Exact `ActiveConfig` method names, the compiled JSON shape, and the fingerprint algorithm (content hash plus git sha when the folder is a repo).
- How "superseded" versions are kept (all rows retained is fine).

### Deferred Ideas (OUT OF SCOPE)
- `icm:activate {version}`: re-activate a superseded version (rollback). Not in Phase 1; revisit when a client needs it.
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| ICM-01 | `icm:sync` reads `examples/example-hvac`, compiles a version, activates it; `icm:status` shows version, file count, go-live time | Loader/Validator/FolderSync design, `icm_versions` shape, Finder exclusion probe, fingerprint rule, example-folder fixture spec |
| ICM-02 | Broken folder never goes live; message names file + field; previous stays active; failure appended to `changelog.md` | Laravel Validator `failed()` + plain-words renderer (probed), YAML scalar gotchas (probed), changelog insert/atomic write, no-op + lock handling |
| LEAD-01 | Fake-channel text creates contact, conversation, message, timeline entry; second text same conversation | `TextChannel` + Twilio-shaped payload, `RecordInbound`, partial unique index, idempotency (D-18), CSRF exemption pitfall |
| LEAD-02 | `POST /leads` with `source=angi` matches/creates contact, records source + cost from `lead-sources.md` | FormRequest, `ActiveConfig::leadSource()`, D-12..D-15, activity columns for per-touch source/cost |
| LEAD-03 | Text and form from same phone land on one contact; E.164 via giggsey lite | libphonenumber probe results (555 area code trap), `isValidNumber` recommendation, `createOrFirst`/unique handling |
| LEAD-04 | Owner reply in dashboard goes out through fake channel, stored as sent | `SendOwnerReply` action, `FakeTextChannel`, embedded Livewire thread component, D-17 |
| RECS-01 | Every inbound re-renders card + `INDEX.md` + `open-leads.md`; same-second events -> one card with both; `records:rebuild`; sync ignores `records/` | Unique-until-processing + WithoutOverlapping job, summary-file race fix, `File::replace` atomic write, ordering tie-break, afterCommit dispatch |
| DASH-01 | Filament Contacts (list + timeline view), Conversations (thread + reply), Business Folder page | Filament 5 resource layout, `Livewire::test` (no extra package), `FilamentUser` 403 trap, Schemas `Livewire` component |
| DASH-02 | `/dev/chat` local-only page; 404 elsewhere | `Route::livewire`, Livewire 4 layout, env gating testable at runtime, no-Vite layout |
| QUAL-01 | Suite green, money integer cents, config only via `ActiveConfig`, two Boost rules in `.ai/rules` | Boost `RecordRule`/`RuleRepository` format, arch + schema guard tests |
</phase_requirements>

## Project Constraints (from CLAUDE.md)

Treat these with the same authority as locked decisions. [VERIFIED: /Users/iantitus/Projects/ops-platform/CLAUDE.md, read this session]

- Laravel app on PHP 8.5. Confirm package versions with `composer show` before relying on an API; do not assume a version.
- Activate the relevant project skill (`filament-development`, `laravel-best-practices`, `testing-best-practices`) when working in that domain.
- Follow existing code conventions; check sibling files. Use descriptive names (`isRegisteredForDiscounts`, not `discount()`).
- **Stick to the existing directory structure; do not create new base folders without approval.** (`examples/` is mandated by the docs and ROADMAP, `.ai/rules` by Boost; both are already approved by name. Do not invent others.)
- **Do not change the application's dependencies without approval.** (The only new dependency is `giggsey/libphonenumber-for-php-lite`, approved by CONTEXT.md. Anything else, including `pestphp/pest-plugin-livewire`, is a flagged decision below.)
- Do not create verification scripts or tinker when tests cover the behavior. Feature tests are preferred.
- Only create documentation files if explicitly requested. (`docs/sprints/sprint-0.md` "Walk-through log" is an existing file that the project process requires you to update; no new doc files.)
- Boost: use `search-docs` before version-specific Laravel-ecosystem work; use `database-schema` before writing migrations; Boost rules live in `.ai/rules` — open `.ai/rules/index.md` and read matching rule files before creating/editing files (the directory does not exist yet; it is created by task 01-01).
- PHP style: always curly braces; constructor property promotion; explicit return and parameter types on everything; TitleCase enum keys; prefer PHPDoc blocks over inline comments; array-shape PHPDoc types.
- Artisan: use `php artisan make:*` with `--no-interaction` (migrations, models, controllers, jobs, commands, enums, requests, `make:filament-*`, `make:livewire`). Use `make:class` for generic classes. Models get factories and seeders (`make:model --factory --seed` or equivalents). Prefer named routes and `route()`.
- Eloquent API resources only for APIs; this phase has none beyond webhooks/forms.
- Testing: Pest; create with `php artisan make:test --pest {Name}` (no `Feature/` prefix in name); most tests are Feature tests; use factories and their states; run the narrowest set with `php artisan test --compact` and re-run after each change; ask the user to run the full suite at the end. Do not delete tests.
- After modifying any PHP: run `vendor/bin/pint --dirty --format agent` (never `pint --test`).
- If a frontend change does not show or Vite manifest is missing: `npm run build` / `composer run dev`.
- Project process (STATE/DEC-01): TDD on, `php artisan test`, one branch per phase (`gsd/phase-01-foundation`), Ian merges, nothing pushes, no autonomous runs. Each new Laravel concept gets a short walk-through the first time it appears, logged in `docs/sprints/sprint-0.md` under "Walk-through log".

## Summary

Phase 1 is almost entirely greenfield on a clean Laravel 13.35 / Filament 5.10 / Livewire 4.4 / Pest 5.3 scaffold. [VERIFIED: `composer show --direct`, `composer show livewire/livewire`] The only app code is `User`, so every pattern here sets precedent. The stack needs exactly one new package (`giggsey/libphonenumber-for-php-lite`, already approved); `symfony/yaml` 8.1.8 and `league/commonmark` 2.10.3 are present as transitive framework dependencies. [VERIFIED: `composer show symfony/yaml`, `composer show league/commonmark`] Everything else (Finder, Process, Cache locks, unique jobs, `File::replace`, Validator, `createOrFirst`, Livewire `Route::livewire`, Filament `Schemas\Components\Livewire`) is already in the installed framework and was confirmed in `vendor/`.

The design that follows from the locked decisions is: `Loader` (Finder + YAML front matter) -> `Validator` (Laravel's validator for rule logic + a small plain-words renderer, because Laravel's own messages do not match D-04) -> `FolderSync` orchestrator (lock, fingerprint, no-op/reject/activate, changelog) -> `icm_versions` -> `ActiveConfig` (read-only view of the active row). Inbound is one `RecordInbound` action behind two thin routes; records are a unique-until-processing, non-overlapping per-contact job plus a lock-guarded summary writer that reads the DB inside the lock. Seven probes during research found traps that would each fail a success criterion if discovered late; they are in Common Pitfalls and should be treated as plan inputs, not footnotes.

**Primary recommendation:** Build in the sprint-0 order (01-01 folder+sync+rules, 01-02 records+inbound, 01-03 screens+snapshots), TDD with every test run against a temp copy of `examples/example-hvac`; use Laravel's validator with a custom plain-words renderer; use `File::replace` for every written file; make `RenderCustomerCard` `ShouldBeUniqueUntilProcessing` + `WithoutOverlapping` and have it read the DB inside a lock for the summary files; and settle the six items in Open Questions before writing plans.

## Architectural Responsibility Map

This is a single Laravel app (no separate SPA/API tier). Tiers here are Laravel layers.

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|-------------|----------------|-----------|
| Read/parse/validate the ICM folder | Console/Service (`App\Icm\Loader`, `Validator`) | — | The only code allowed to touch the folder (DEC-10). Invoked by `icm:sync` and the Sync Now action only. |
| Version storage + activation | Database (`icm_versions`) via `FolderSync` | — | Active version is the single runtime config source. |
| Config reads at runtime | Service (`ActiveConfig`, container) | Database | App code reads only through it; never disk. |
| Changelog writes | Service (`Changelog`) | Filesystem | Sync appends to the folder's `changelog.md` (sync is allowed to touch the folder). |
| Inbound text/form intake | API/Backend (routes + FormRequest + `RecordInbound`) | Database | Public HTTP endpoints, validated, transactional, idempotent. |
| Phone normalization / contact matching | API/Backend (`RecordInbound`, `PhoneNumber` helper) | Database (unique indexes) | Business rule enforced in code, backstopped by DB uniqueness. |
| Text sending | API/Backend (`TextChannel` driver) | — | Interface with Fake and Twilio-stub drivers selected by config. |
| Customer card + summaries | Queue/Job (`RenderCustomerCard`) + Service (`CustomerCard`, `RecordsWriter`) | Filesystem | Rendered from DB asynchronously; files are read-only output. |
| Dashboard screens | Frontend server (Filament resources/pages, Livewire) | API/Backend | Filament panel at `/admin`; thin, calls Actions. |
| `/dev/chat` | Frontend server (Livewire full-page) | API/Backend | Local-only; calls the same inbound code path. |
| Project rules | Repo tooling (`.ai/rules` via Boost) | — | Recorded through Boost `RecordRule`; guarded by tests. |

## Standard Stack

### Core (all installed unless noted)
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| laravel/framework | 13.35.0 | App, queue, validator, Finder, locks | [VERIFIED: composer show --direct] |
| filament/filament | 5.10.1 | Dashboard (Contacts, Conversations, Business Folder) | [VERIFIED: composer show --direct] |
| livewire/livewire | 4.4.7 | `/dev/chat`, thread component | [VERIFIED: composer show livewire/livewire] |
| symfony/yaml | 8.1.8 | Front matter parsing in `Loader` | [VERIFIED: composer show symfony/yaml] |
| league/commonmark | 2.10.3 | Installed; **not needed** for front matter (see Don't Hand-Roll) | [VERIFIED: composer show league/commonmark] |
| pestphp/pest + pest-plugin-laravel | 5.3.1 / 5.0.1 | Tests | [VERIFIED: composer show --direct] |
| laravel/boost | 2.10.3 | `.ai/rules` recording (`RecordRule`, `RuleRepository`) | [VERIFIED: composer show --direct; vendor/laravel/boost/src/Mcp/Tools/RecordRule.php read] |
| laravel/pint | 1.32.1 | Formatting (required after PHP edits) | [VERIFIED: composer show --direct] |

### New dependency (approved in CONTEXT.md)
| Library | Version | Purpose | Why |
|---------|---------|---------|-----|
| giggsey/libphonenumber-for-php-lite | 9.0.41 (PHP ^8.1; requires only symfony/polyfill-mbstring) | E.164 parse/format/validate | Mandated by PROJECT.md Constraints; Google libphonenumber port, lite = parse/format/validate only (no geocoder/carrier) [CITED: github.com/giggsey/libphonenumber-for-php-lite; packagist.org/packages/giggsey/libphonenumber-for-php-lite] |

Namespace is `libphonenumber\...` (not `giggsey\...`): `libphonenumber\PhoneNumberUtil`, `libphonenumber\PhoneNumberFormat`, `libphonenumber\NumberParseException`. [CITED: github.com/giggsey/libphonenumber-for-php-lite README; confirmed by running it in a scratch project this session]

### Supporting (already present, use these)
| Library | Purpose | When |
|---------|---------|------|
| `Symfony\Component\Finder\Finder` (via laravel/framework) | Walk the ICM folder | `Loader` only |
| `Illuminate\Support\Facades\File::replace()` | Atomic temp-file + rename write | Every write to `records/` and `changelog.md` |
| `Illuminate\Support\Facades\Cache::lock()` | Serialize syncs and summary-file writes | `FolderSync`, `RecordsWriter` |
| `Livewire\Livewire::test()` | Test Filament pages/resources and Livewire components | All Filament/Livewire tests (see Pitfall 11) |
| Pest arch (`pest-plugin-arch` installed) | Enforce "config only via ActiveConfig" | QUAL-01 guard |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Laravel Validator + plain-words renderer | Hand-written typed schema DSL | Laravel gives `required_unless`, wildcard arrays, `timezone`, `date_format`, `integer:strict`, `boolean:strict`; a DSL re-implements them. |
| `Livewire::test()` | `pestphp/pest-plugin-livewire` v5.0.0 (requires pest ^5, livewire ^4.3.3, php ^8.4; compatible) | Gives the `livewire()` helper the Filament docs and skill use. It is a dependency change, so it needs Ian's approval; not required to test. |
| `spatie/yaml-front-matter` | symfony/yaml + a 10-line splitter | Not installed; no benefit. Do not add. |

**Installation:**
```bash
composer require giggsey/libphonenumber-for-php-lite
```
(then `vendor/bin/pint --dirty --format agent`). No npm changes.

**Version verification:** `composer show giggsey/libphonenumber-for-php-lite -a` lists 9.0.41 as newest; Packagist metadata shows latest tag 2026-10-08, first release line 8.13.0 on 2022-11-09, 22.5M total / 2.2M monthly downloads, repository github.com/giggsey/libphonenumber-for-php-lite, not abandoned. [VERIFIED: packagist.org API, this session]

## Package Legitimacy Audit

The GSD seam `package-legitimacy check` supports only `npm|pypi|crates` (it rejects `composer`), so it could not be run for Packagist. Evidence below is from Packagist's API and the package README.

| Package | Registry | Age | Downloads | Source Repo | Verdict | Disposition |
|---------|----------|-----|-----------|-------------|---------|-------------|
| giggsey/libphonenumber-for-php-lite | Packagist | ~4 yrs (8.13.0 on 2022-11-09; 98 versions) | 22.5M total, ~2.2M/mo | github.com/giggsey/libphonenumber-for-php-lite (100 favers) | OK (manual; seam unsupported for composer) | Approved: named in PROJECT.md Constraints and CONTEXT.md |
| pestphp/pest-plugin-livewire | Packagist | v5.0.0 exists | — | github.com/pestphp/pest-plugin-livewire | Not recommended to install | Optional; requires Ian's approval (dependency rule). Not needed. |

**Packages removed due to [SLOP] verdict:** none
**Packages flagged as suspicious [SUS]:** none. (`pestphp/pest-plugin-livewire` is a first-party Pest plugin; it is optional and gated only by the "do not change dependencies" rule.)

## Architecture Patterns

### System Architecture Diagram

```
                         OWNER / IAN / OWNER'S AI edits files
                                      |
                                      v
 examples/example-hvac/  (ICM_PATH)   |   php artisan icm:sync     Filament "Sync Now"
 ├─ business/ voice/ rules/ ...       |         |                         |
 ├─ CLAUDE.md START-HERE.md ...       +---------+-------------------------+
 ├─ changelog.md  <------ appended ---+                 |  (same FolderSync, inline, under Cache::lock)
 └─ records/   <--+ (never read)                        v
                  |                  Loader (Finder; skip records/, changelog.md, README.md)
                  |                       |  raw files -> {path, type, front matter, body} + routers (raw)
                  |                       v
                  |                  Validator (Laravel Validator per type + cross-file checks)
                  |                       |  errors grouped by file + notices (later-sprint types)
                  |                       v
                  |                  FolderSync: fingerprint(sha256 of compiled bytes)
                  |                       |
                  |           same as active? --yes--> "No changes" (no row) --> changelog
                  |                       | no
                  |          errors? --yes--> icm_versions row status=rejected (active untouched) --> changelog
                  |                       | no
                  |                       v
                  |           transaction: old active -> superseded, new row status=active --> changelog
                  |                       v
                  |                 icm_versions (JSON compiled)
                  |                       |
                  |                       v
                  |           App\Icm\ActiveConfig  (scoped singleton; ONLY runtime config reader)
                  |                       |
   Customer text                          |  leadSource(key) -> name + cost_cents
   POST /webhooks/text/fake  --+          v
   /dev/chat (Livewire) -------+--> TextChannel::parseInbound -> InboundText DTO
   POST /leads (FormRequest) ---+--> RecordInbound (one DB transaction)
                                     │ 1 normalize phone (E.164, region US) / email
                                     │ 2 find-or-create Contact (phone, then email; D-12/13)
                                     │ 3 find-or-create the one open Conversation (D-16)
                                     │ 4 Message (unique provider+provider_message_id, D-18)
                                     │ 5 Activity (type, summary, source, cost_cents; D-14/15)
                                     │ 6 update contact.last_activity_at / conversation.last_message_at
                                     └─ afterCommit: event CustomerRecordChanged -> listener
                                                    -> dispatch ProcessInbound (empty) + RenderCustomerCard(contact)
   Owner reply (Filament thread component) --> SendOwnerReply --> TextChannel::send (Fake: stores+logs) --> same event
                                     v
              RenderCustomerCard(contact)  [ShouldBeUniqueUntilProcessing + WithoutOverlapping(contact id)]
                    reads DB fresh -> CustomerCard -> File::replace(records/customers/<slug>-<id>.md)
                    -> RecordsWriter::summaries()  [Cache::lock('records:summaries'); read DB INSIDE lock]
                       -> INDEX.md, open-leads.md  (File::replace)        records:rebuild = all contacts + records/CLAUDE.md
```

### Recommended Project Structure
```
app/
├── Actions/             # RecordInbound, SendOwnerReply, (FolderSync lives in Icm)
├── Channels/            # TextChannel (interface), FakeTextChannel, TwilioTextChannel (stub), InboundText (DTO)
├── Console/Commands/    # IcmSync, IcmStatus, RecordsRebuild (make:command; #[Signature]/#[Description] attributes)
├── Enums/               # ContactStatus, ConversationStatus, MessageDirection, MessageAuthor, MessageStatus, MessageChannel, VersionStatus, ActivityType
├── Events/              # CustomerRecordChanged (ShouldDispatchAfterCommit)
├── Filament/
│   ├── Pages/           # BusinessFolder
│   └── Resources/       # Contacts/..., Conversations/...  (v5 layout: Resource + Pages/ Schemas/ Tables/)
├── Http/
│   ├── Controllers/     # FakeTextWebhookController, LeadController
│   └── Requests/        # StoreLeadRequest, FakeTextWebhookRequest
├── Icm/                 # Loader, Validator, FolderSync, Changelog, ActiveConfig, IcmFile, SyncResult, exceptions
├── Jobs/                # ProcessInbound (empty), RenderCustomerCard
├── Listeners/           # QueueCustomerRecordRender (auto-discovered by type-hint)
├── Livewire/            # DevChat, ConversationThread (class-based: make:livewire ... --class --emoji=false)
├── Models/              # Contact, Conversation, Message, Activity, IcmVersion
├── Records/             # CustomerCard, RecordsWriter (INDEX/open-leads/CLAUDE.md), slug/filename helper
└── Support/             # PhoneNumber (libphonenumber wrapper)  -- only if a home is needed; no new base folders
config/icm.php, config/channels.php   # path (ICM_PATH) / text driver + phone region (D-19)
examples/example-hvac/               # fixture business (see below)
.ai/rules/                           # created by Boost RecordRule
tests/Feature/{Icm,Inbound,Records,Filament,Quality}/  tests/Unit/ArchitectureTest.php
```
`app/Actions`, `app/Channels`, `app/Icm`, `app/Records` are namespaces named by sprint-0.md; they are subfolders of the existing `app/`, not new base folders. [VERIFIED: docs/sprints/sprint-0.md:60-69, 90-96, 113]

### Pattern 1: Front matter splitting + parse (Loader)
**What:** Split `---\n<yaml>\n---\n<body>`; parse the YAML with default flags (never `PARSE_OBJECT`); turn any `ParseException` into a plain-words error naming the file and line (offset +1 for the opening `---`).
**Example:**
```php
// Source: probed this session against symfony/yaml 8.1.8
// preg_match('/\A---\R(.*?)\R---(?:\R|\z)(.*)\z/s', $raw, $m)  -> $m[1] yaml, $m[2] body
$data = Yaml::parse($m[1], Yaml::PARSE_DATETIME);   // see Pitfall 3 for why PARSE_DATETIME
// then normalize DateTimeInterface -> 'Y-m-d' strings so the compiled JSON is plain JSON
```
Reject: no leading `---` for typed-looking files (D-03 "missing type"), non-mapping YAML (`Yaml::parse('just text')` returns the string `'just text'`), empty front matter (returns `null`), parse exceptions (e.g. tabs: "A YAML file cannot contain tabs as indentation at line 2"). [VERIFIED: probe output this session]

### Pattern 2: Laravel Validator for logic, custom renderer for words
**What:** Per type, a rule array (`required|string`, `integer:strict`, `boolean:strict`, `Rule::in`, `required_unless:price_policy,none`, `timezone:all`, `present|array`, wildcards for `intake.*.key`). After `fails()`, iterate `$validator->failed()` (shape: `['price_policy' => ['In' => ['none','starting_at','fixed']], 'intake.1.key' => ['Required' => []]]`) and render each (rule name, params, path, `Arr::get($data, $path)`) into the D-04 style. Do not use Laravel's default message strings. [VERIFIED: probe this session — `failed()` output and `integer:strict`/`boolean:strict` behavior]
**Example:**
```php
// Source: probed this session (Laravel 13.35)
$v = Validator::make($fm, [
    'price_policy' => ['required', Rule::in(['none', 'starting_at', 'fixed'])],
    'price_cents'  => 'required_unless:price_policy,none|integer:strict|min:0',
    'book_online'  => 'required|boolean:strict',
    'duration_minutes' => 'required|integer:strict|min:1',
    'intake'       => 'required|array|min:1',
    'intake.*.key' => 'required|string',
    'intake.*.options' => 'sometimes|array',
]);
// render: "business/services/ac-repair.md: price_policy must be one of none, starting_at, fixed (you wrote maybe)"
```
Plain-integer rule is `integer:strict`: plain `integer` passes the string `'90'` (YAML `"90"`). Plain `string` correctly rejects the int a YAML `+15551234567` or `slug: 123` produces. [VERIFIED: probe]

### Pattern 3: Fingerprint + decision table (FolderSync)
**What:** Fingerprint = `sha256` over the sorted `relativePath + "\0" + rawBytes` of exactly the files that go into the compiled version (typed files + router files). Exclude `records/`, `changelog.md`, `README.md`, later-sprint-type files (they are not compiled). `git_sha` is stored in its own column and is **not** part of the fingerprint. Decision: `fingerprint == active.fingerprint` -> no-op (D-08); validation errors -> `rejected` row; else transaction (old active -> `superseded`, new -> `active`, `activated_at = now()`).
**Why:** `changelog.md` and `records/` are written by the app into the folder; if they were hashed, every sync/render would change the fingerprint and mint a version forever. Keeping git sha out keeps "commit without content change" a no-op.
**git_sha:** only when the folder is its own repo root (`is_dir($root.'/.git')`). `examples/example-hvac` sits inside the app repo, so `git rev-parse HEAD` there would record an unrelated app commit. (Claude's-discretion territory per CONTEXT.)

### Pattern 4: ActiveConfig as a scoped singleton
Bind with `$this->app->scoped(ActiveConfig::class, ...)`. Laravel's queue worker calls `$app->forgetScopedInstances()` after each job, so long-running workers see a sync done in another process; a plain `singleton` would serve stale config forever. [VERIFIED: vendor/laravel/framework/src/Illuminate/Queue/QueueServiceProvider.php:263 `$app->forgetScopedInstances();`] "Singleton" in the docs is satisfied in spirit (one instance per request/job); flag in the plan so Ian is not surprised. After an in-process sync (tests, Sync Now) call `app()->forgetInstance(ActiveConfig::class)` or expose `refresh()`.
`ActiveConfig` must lazily load; never call it in a service provider `boot()`/`register()` (migrations run before `icm_versions` exists). With no active version, accessors throw a plain-words `NoActiveVersion` exception ("No business folder version is live yet. Run php artisan icm:sync."); `RecordInbound` must catch this and still record the lead (cost null, timeline note) — never lose a lead.

### Pattern 5: Inbound action (single entry point)
`RecordInbound` takes a normalized DTO `{channel, source, phoneRaw?, email?, name?, body, providerName?, providerMessageId?, meta}` and runs in `DB::transaction`: normalize -> match/create contact -> open conversation -> message -> activity -> counters. Dispatch the event **after commit** (`ShouldDispatchAfterCommit` on the event, or `->afterCommit()` on the jobs). A duplicate `(provider, provider_message_id)` (D-18) returns the existing message without re-dispatching anything. [ASSUMED: duplicate returns HTTP 200 with the same body — standard webhook behavior; confirm in plan]
Race handling: wrap the optimistic insert in a nested `DB::transaction` (savepoint) and catch `Illuminate\Database\UniqueConstraintViolationException`, then re-select. `Model::createOrFirst()` exists in Laravel 13 [VERIFIED: Builder.php:763] and does this, but it inserts first; for the open conversation (usually already exists) do select-then-create-with-catch instead. Savepoints matter because on Postgres (Phase 2) a unique violation otherwise aborts the whole transaction. [ASSUMED: Postgres behavior from general knowledge; SQLite is the only DB in Phase 1]

### Pattern 6: Rendering without lost updates
1. Event fires after commit -> listener dispatches `RenderCustomerCard($contactId)`.
2. Job: `ShouldQueue, ShouldBeUniqueUntilProcessing`, `uniqueId()` = contact id, `#[UniqueFor(300)]` as a crash backstop, middleware `[(new WithoutOverlapping($id))->releaseAfter(5)->expireAfter(60)]`, and `retryUntil()` (released jobs consume attempts). `UntilProcessing` releases the lock when the job starts, so an event during a render queues exactly one more; two events before the job starts collapse to one job that reads both from the DB. [VERIFIED: vendor/laravel/framework/src/Illuminate/Queue/CallQueuedHandler.php lines 91, 144-149, 315-318 handle `ShouldBeUniqueUntilProcessing`]
3. Card timeline query: `orderByDesc('occurred_at')->orderByDesc('id')` — `occurred_at` has 1-second resolution, the tie-break makes same-second order deterministic.
4. Write the card with `File::replace($path, $markdown)` (tempnam in the same dir + `rename`). [VERIFIED: Filesystem.php:215-234]
5. Summary files (`INDEX.md`, `open-leads.md`): **read the DB inside** `Cache::lock('records:summaries', 30)->block(10, fn () => ...)` and write there. Per-contact serialization does not protect these shared files: job A (contact 1) and job B (contact 2) can run concurrently, B finishes last-but-with-older-read, and A's write drops contact 2. Reading inside the lock makes the last writer always have read the newest DB state.

### Pattern 7: Filament 5 resources and pages
- `php artisan make:filament-resource Contact --view --no-interaction` generates `Contacts/ContactResource.php`, `Pages/`, `Schemas/`, `Tables/` (delegating `form()`/`table()`). Delete the Create/Edit pages and keep `index` + `view` routes in `getPages()`; the owner does not hand-create contacts in Phase 1. [CITED: filamentphp.com/docs/5.x/resources/overview]
- Contact view timeline: infolist `RepeatableEntry` (or a custom Blade view via `protected string $view`, with `{{ $this->content }}`). [CITED: filamentphp.com/docs/5.x/resources/viewing-records]
- Conversation thread + reply box: build `App\Livewire\ConversationThread` (class-based; public `$body`, `sendReply()`), embed it in `ViewConversation` with `Filament\Schemas\Components\Livewire::make(ConversationThread::class, ['conversation' => $record])`. [VERIFIED: vendor/filament/schemas/src/Components/Livewire.php `make(string|Closure $component, array|Closure $data = [])`] The same message-list Blade partial can serve `/dev/chat`.
- Business Folder: `php artisan make:filament-page BusinessFolder --no-interaction`; header `Action::make('syncNow')->action(fn () => ...)` calling `FolderSync` inline, then `Filament\Notifications\Notification::make()->title(...)->body(errors)->success()|danger()->send()`. [CITED: filamentphp.com/docs/5.x/actions/overview, /navigation/custom-pages]
- Namespaces: actions are `Filament\Actions\*` only; `$navigationIcon` is `string | BackedEnum | null`; `$view` is `protected string`. [VERIFIED: .claude/skills/filament-development/SKILL.md; vendor/filament/filament/src/Pages/Page.php]

### Pattern 8: /dev/chat gating
Register `Route::livewire('/dev/chat', DevChat::class)` (Livewire 4 macro) behind a tiny middleware or `abort_unless(app()->environment('local'), 404)` evaluated **per request**, not at route-registration time, so a test can flip the environment. [VERIFIED: vendor/livewire/livewire/src/Mechanisms/HandleRouting/HandleRouting.php:16 `Route::macro('livewire', ...)`] Livewire 4's default layout is `layouts::app` (config `component_layout`) and the default `make:livewire` type is single-file with an emoji prefix; use `--class --emoji=false` to match Laravel conventions, and create `resources/views/layouts/app.blade.php` without `@vite` (see Pitfall 8). [VERIFIED: vendor/livewire/livewire/config/livewire.php:47,71-73; `php artisan make:livewire --help`]
"Posts to the fake webhook": call the same parse+`RecordInbound` path in-process (see Open Question 4). Use `wire:poll.2s` for refresh.

### Anti-Patterns to Avoid
- **Reading the folder anywhere except `Loader`.** Guard with an arch test (Validation Architecture).
- **Model observers for record events.** Fire an explicit `CustomerRecordChanged` from the two Actions so ordering and after-commit are controlled.
- **`Contact::updateOrCreate` for matching.** It cannot express "phone, then email, then fill-in-phone-only-if-empty, never overwrite" (D-12/13); write the explicit method.
- **Floats anywhere near money**, including `config` and test fixtures; cents are `integer`/`unsignedInteger`. `integer:strict` in the Validator.
- **Writing generated files with `file_put_contents`.** Use `File::replace`.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Phone parsing/E.164 | Regex normalizer | `libphonenumber\PhoneNumberUtil` | Region rules, extensions, vanity numbers (`1-800-FLOWERS` -> `+18003569377`), country codes |
| Front matter YAML | Line-by-line parser | `symfony/yaml` + `\A---\R(.*?)\R---` split | Anchors, quoting, lists; `league/commonmark` front-matter extension is unnecessary (we never render the markdown) |
| Validation logic | Custom type/required/in checks | Laravel `Validator` (`required_unless`, wildcards, `timezone`, `date_format`, `*:strict`) | Already does conditional rules and nested arrays; only the *wording* is custom |
| Directory walk | `scandir` recursion | `Symfony\Component\Finder\Finder` | Skips dotfiles/`.git`, no symlink following, deterministic sort |
| Atomic file write | tempnam + rename by hand | `File::replace()` | Same-directory temp file, permissions, symlink resolution |
| Mutual exclusion | Lock files | `Cache::lock()` / `WithoutOverlapping` / `ShouldBeUniqueUntilProcessing` | Atomic on database/array cache drivers (the `cache_locks` table migration exists) |
| Idempotent webhooks | App-level "seen" checks | Composite unique index (D-18) + catch `UniqueConstraintViolationException` | Check-then-insert races under concurrent retries |
| One open conversation per contact | App-level only check | Partial unique index `unique(contact_id) where status = 'open'` (raw `DB::statement`, valid on SQLite 3.51 and Postgres) | Concurrent inbounds otherwise create two open threads. [ASSUMED: planner choice; not locked by D-16] |
| Timezone names | Own list | `timezone:all` validation rule | Verified rule exists in probe |
| Rule recording | Hand-written `.ai/rules` | Boost `RecordRule` tool (`boost:execute-tool` is internal) | Produces `index.md` + area files in the exact format Boost reads |

**Key insight:** every piece of "deceptively simple" logic in this phase (phones, YAML scalars, same-second ordering, concurrent writers, atomic files) has a framework primitive that is already installed; the risk is not missing libraries but the specific traps below.

## Common Pitfalls

### Pitfall 1: YAML silently changes owner-written scalars
**What goes wrong:** `phone: +15551234567` parses to the integer `15551234567` (the `+` is lost); `slug: 123` is an int; unquoted `closures: [2026-12-25]` becomes Unix timestamps (`1798156800`); `book_online: yes` stays the string `'yes'`; `never_say: []` is an empty array. [VERIFIED: probe this session]
**How to avoid:** Validate with `string` / `integer:strict` / `boolean:strict` and give plain-words fixes ("phone must be text: wrap it in quotes, "+15551234567""). Parse with `Yaml::PARSE_DATETIME` and normalize dates to `Y-m-d` strings (JSON-safe) so unquoted dates work, or reject ints in `closures` with a "wrap the date in quotes" message — pick one in the plan. Times stay strings (`08:00` -> `'08:00'`); use `date_format:H:i` (rejects `8:00`).
**Warning signs:** Compiled JSON has numbers where text was written; a "valid" phone shows as `1.5551234567E+10`.

### Pitfall 2: Laravel `required` rejects empty lists
`never_say: []` and `closures: []` fail `required` (confirmed: `never_say` -> `Required` failure in probe). Use `present|array` for lists that may be empty (`closures`, `never_say`, `send_on_own`, `draft_for_owner`). `false` and `0` pass `required`, so `book_online: false` is fine.

### Pitfall 3: Finder `exclude('records')` is not path-anchored
`->exclude('records')` also excluded `business/records/b.md` in the probe. Use `->notPath('#^records(/|$)#')` (anchored regex) for the top-level `records/` and an explicit relative-path check for top-level `changelog.md` (`->notName('changelog.md')` would also hide a nested one). Finder ignores dotfiles and `.git` by default and does not follow symlinks. [VERIFIED: probe — only `business/profile.md` survived `exclude('records')->notName('changelog.md')`]

### Pitfall 4: Fingerprinting what the app itself writes
Hash only compiled inputs; never `changelog.md` or `records/`. Otherwise the changelog append from the previous sync makes every run "changed". A test: sync twice -> second reports "no changes" and creates no row (D-08).

### Pitfall 5: The sync of the example folder dirties tracked git files
`examples/example-hvac/changelog.md` is tracked (it ships in the example) and the sync appends to it, including "No changes" entries (D-08). Manual `icm:sync` runs will leave the working tree dirty. Tests use a temp copy (Discretion), so only manual runs are affected. See Open Question 6.

### Pitfall 6: `.gitignore` case-insensitivity on macOS (D-23 is real)
Probed in a scratch repo with `core.ignorecase=true`: an unanchored `records/` rule ignored `app/Records/CustomerCard.php`; the anchored rule set below ignored only generated files and left `app/Records/` alone. [VERIFIED: probe this session]
```gitignore
# generated records snapshots (keep README.md and CLAUDE.md tracked)
/examples/*/records/*
!/examples/*/records/README.md
!/examples/*/records/CLAUDE.md
```
Add a verification step to the plan: `git check-ignore -v examples/example-hvac/records/INDEX.md` (ignored), `git check-ignore -v app/Records/CustomerCard.php` (exit 1, not ignored), `git check-ignore -v examples/example-hvac/records/CLAUDE.md` (not ignored).

### Pitfall 7: CSRF blocks real webhook/form POSTs but never fails in tests
`PreventRequestForgery` (Laravel 13's CSRF middleware, registered in the panel provider too) is skipped when `runningInConsole() && runningUnitTests()`, so feature tests POST fine while a real `curl`/Twilio/website POST to `routes/web.php` returns 419. [VERIFIED: vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/PreventRequestForgery.php:95-133] `bootstrap/app.php` has no routing for `api`, so these routes live in `web.php`. Exempt them (`$middleware->preventRequestForgery(except: ['webhooks/*', 'leads'])` — signature `preventRequestForgery(array $except = [], bool $originOnly = false, bool $allowSameSite = false)` verified in Middleware.php:605 — or `->withoutMiddleware(PreventRequestForgery::class)` on the route group). Because tests cannot detect a missing exemption, add a test that asserts the exemption (inspect the route's excluded middleware, or `PreventRequestForgery::class` `except` list) and one manual `curl` UAT step.

### Pitfall 8: `@vite` in a layout breaks tests on a fresh clone
`public/build` is gitignored (present locally, absent in CI/fresh clone), so `@vite(...)` in the `/dev/chat` layout throws "Unable to locate file in Vite manifest" under test. Keep `layouts/app.blade.php` free of `@vite` (inline minimal CSS or Filament's own styles), or call `$this->withoutVite()` in those tests. [VERIFIED: .gitignore lists `/public/build`; CLAUDE.md Vite guidance]

### Pitfall 9: The Filament admin panel returns 403 to every user outside `local`
`App\Models\User` does not implement `FilamentUser`; Filament's `Authenticate` middleware then allows access only when `config('app.env') === 'local'`. [VERIFIED: vendor/filament/filament/src/Http/Middleware/Authenticate.php:31-39] Proven live: `actingAs(User::factory()->create())->get('/admin')` returned **403** in the testing environment, while `Livewire::test(Dashboard::class)` returned OK. Consequences: (a) any HTTP-level "Filament page loads for a logged-in user" test fails; (b) production later 403s the owner. Fix in 01-03 (or earlier): `User implements FilamentUser` with `canAccessPanel(Panel $panel): bool { return true; }` (single-owner instance), and a test that `get('/admin/contacts')` returns 200.

### Pitfall 10: libphonenumber `isValidNumber()` rejects "555" area codes
`(555) 123-4567` and `+1 555 123 4567` parse to `+15551234567` but `isValidNumber` is **false** (555 is not an assignable area code); `202-555-0100` and `(312) 555-0147` are valid; `555-0100` parses to junk `+15550100` while `isPossibleNumber` is true; `abc` and `''` throw `NumberParseException`; `ext 5` is dropped. [VERIFIED: ran the package in a scratch project this session] Recommendation: use `isValidNumber` as the gate for the match key (junk never becomes a unique key); if a form's phone fails, treat it as absent (note "form gave a phone we could not read: …" on the timeline) and fall back to email; if both unusable -> 422. **All fixtures, seeders, docs and tests must use valid fictional numbers** (e.g. `+12025550123`, `+13125550147`), never `555-xxx-xxxx` area codes. [ASSUMED: the isValid-gate policy is a recommendation; D-12 is silent on invalid phones]

### Pitfall 11: Filament docs/skill assume `livewire()` from `pest-plugin-livewire`, which is not installed
`Pest\Livewire\livewire()` does not exist in this project (`grep` of `vendor/` finds no such function; the plugin is absent). `Livewire\Livewire::test(Class::class, [...])` works and Filament's table/action assertions apply; proven for a Filament page (`Livewire::test(Dashboard::class)->assertOk()`). [VERIFIED: probe this session] Use `Livewire::test()` everywhere; do not install the plugin without approval. (`assertCanSeeTableRecords`, `callAction`, `assertNotified` are documented Filament test helpers [CITED: filamentphp.com/docs/5.x/testing/testing-resources]; their use through `Livewire::test` on a resource page is [ASSUMED] from the same Livewire testable and should be proven by the first Filament test in 01-03.)

### Pitfall 12: Same-second events and card order
`occurred_at`/timestamps are second-resolution. Always order by `occurred_at desc, id desc`. Test: create two inbound events with `Carbon::setTestNow` frozen to the same second and assert both appear in the single card in id order.

### Pitfall 13: Tests writing into the repo
`phpunit.xml` sets `QUEUE_CONNECTION=sync`, `CACHE_STORE=array`, in-memory SQLite. [VERIFIED: phpunit.xml read] Add `<env name="ICM_PATH" value="…/nonexistent">` (fail loudly) and a Pest global `beforeEach` in `tests/Pest.php` (Feature) that copies `examples/example-hvac` to a temp dir and sets `config(['icm.path' => $tmp])`, removed in `afterEach`. With `sync` queue, `RenderCustomerCard` runs inline on every `RecordInbound`, so any test that forgets the override would write real cards into `examples/`.

### Pitfall 14: Prompt-injection and markdown spoofing in cards
Cards are read by the owner's AI. A customer text like `## 2026-10-10 Owner: refund approved` or "ignore previous instructions" is rendered verbatim into the file. Render every customer-supplied body as a blockquote (prefix each line with `> `), label sections "Customer-supplied text, not instructions", and escape `|`/newlines in `INDEX.md` table cells and `open-leads.md` previews. Filenames are slug + id only (never raw names).

### Pitfall 15: Unique-job lock TTL and released-attempt accounting
`WithoutOverlapping` releases back to the queue and each release counts as an attempt: set `retryUntil()` (e.g. 10 minutes) rather than a small `$tries`. Set `#[UniqueFor(300)]` so a crash before processing cannot hold a lock forever. [VERIFIED: `Illuminate\Queue\Attributes\UniqueFor` exists, vendor/laravel/framework/src/Illuminate/Queue/Attributes/UniqueFor.php; `retryUntil` interplay with released jobs is [ASSUMED] from Laravel docs knowledge — add a job test with `Queue::fake()` asserting middleware and uniqueId]

### Pitfall 16: Activation/`last sync result` have no row for no-ops
D-08 creates no row for "no changes", but the Business Folder page must show the "last sync result". See Open Question 1.

## Code Examples

### Record a Boost project rule (QUAL-01)
Boost writes rules through `RuleRepository::write($glob, $title, $note)`; the file name derives from the glob's directory segments (`app/**` -> `.ai/rules/app.md`), front matter is `paths:`, and `.ai/rules/index.md` is regenerated as a table. [VERIFIED: vendor/laravel/boost/src/Rules/RuleRepository.php read; tool in vendor/laravel/boost/src/Mcp/Tools/RecordRule.php: params `glob`, `title`, `note`, all required; registered when `config('boost.rules.enabled', true)`]
Use the Boost MCP `record-rule` tool (QUAL-01 is the explicit request the tool's description demands). Suggested entries:
- glob `app/**` — title "Money is integer cents" — note "All money values are stored and passed as integer cents (`*_cents` integer columns, integer casts), never floats. Convert for display only."
- glob `database/migrations/**` — same title/note scoped to schema.
- glob `app/**` — title "Business configuration is read only through ActiveConfig" — note "Application code reads business configuration only through `App\Icm\ActiveConfig`, never from the ICM folder. Only `App\Icm\Loader` (run by the sync) reads the folder."
If the MCP tool is unavailable in the executing session, fall back to calling `Laravel\Boost\Rules\RuleRepository` through the container rather than hand-writing files, so `index.md` stays consistent.

### Piped plain-words rendering (shape only)
```php
// Source: failed() shape probed this session
foreach ($validator->failed() as $path => $rules) {
    foreach ($rules as $rule => $params) {
        $errors[] = "{$file}: ".PlainWords::for($rule, $path, $params, Arr::get($data, $path));
    }
}
// 'In'      -> "{$path} must be one of ".implode(', ', $params)." (you wrote {$value})"
// 'Required'-> "{$path} is missing, and it is required"
// 'Integer' -> "{$path} must be a whole number (you wrote {$value})"
```

### Ignoring records/ and changelog.md in the Loader
```php
// Source: Finder probe this session
$finder = (new Finder)->files()->in($root)->name('*.md')
    ->notPath('#^records(/|$)#')
    ->filter(fn (\SplFileInfo $f) => $f->getRelativePathname() !== 'changelog.md')
    ->sortByName();
```

### Public routes, CSRF exempt, throttled
```php
// routes/web.php  (exemption in bootstrap/app.php: $middleware->preventRequestForgery(except: ['webhooks/*', 'leads']))
Route::post('/webhooks/text/fake', FakeTextWebhookController::class)->name('webhooks.text.fake');
Route::post('/leads', [LeadController::class, 'store'])->middleware('throttle:leads')->name('leads.store');
```
The fake webhook route must 404 unless the configured text driver is `fake` (it is unauthenticated). Respond with empty TwiML (`<Response/>`, `text/xml`) so the Twilio driver in Phase 2 is a drop-in; Twilio sends `application/x-www-form-urlencoded` with `MessageSid`, `From`, `To`, `Body`, `NumMedia`, and expects TwiML back. [CITED: twilio.com/docs/messaging/guides/webhook-request]

### Pest arch guard for "config only via ActiveConfig"
```php
// Source: Pest arch syntax [CITED: pestphp.com/docs/arch-testing]
arch('only the Loader parses the ICM folder')
    ->expect('Symfony\Component\Yaml\Yaml')->toOnlyBeUsedIn('App\Icm\Loader');
arch('nothing outside the sync reads files directly')
    ->expect(['file_get_contents', 'file', 'fopen', 'glob', 'scandir'])
    ->not->toBeUsedIn(['App\Http', 'App\Actions', 'App\Livewire', 'App\Filament', 'App\Channels', 'App\Jobs']);
```
(`App\Records` writes files but must not read config; it may read nothing from the ICM folder.)

## State of the Art

| Old Approach | Current Approach | Impact |
|--------------|------------------|--------|
| `VerifyCsrfToken` | `PreventRequestForgery` (Laravel 13), `$middleware->preventRequestForgery(except:)`; `validateCsrfTokens()` kept as alias | Use the new name; exemption list is a static on the middleware |
| `$fillable`/`$table` properties | `#[Fillable]`, `#[Table]`, `#[Hidden]` Eloquent attributes (see `User`) | Follow sibling convention in models [VERIFIED: app/Models/User.php; vendor/.../Eloquent/Attributes/*] |
| `$signature`/`$description` properties | `#[Signature]`, `#[Description]` on commands; `make:command` stub already emits them | [VERIFIED: vendor/.../Console/stubs/console.stub] |
| `$tries`/`$backoff`/`$uniqueFor` properties | `#[Tries]`, `#[Backoff]`, `#[UniqueFor]` job attributes exist | Either is supported; follow sibling convention once one job exists |
| Livewire 3 SFC/class defaults | Livewire 4: default `make:livewire` = single-file with emoji; `Route::livewire()`; default layout `layouts::app` | Use `--class --emoji=false` and create the layout |
| Filament v3 `form()` in resource only | Filament 5: `Resources/<Plural>/{Resource,Pages,Schemas,Tables}`; `Filament\Schemas\Schema`; `Filament\Actions\*` only | Follow generator output [CITED: filamentphp.com/docs/5.x/resources/overview] |

**Deprecated/outdated:** Twilio `SmsSid`/`SmsMessageSid` params (use `MessageSid`) [CITED: twilio.com/docs/messaging/guides/webhook-request]. Unanchored `.gitignore` `records/` rules on macOS (Pitfall 6).

## Recommended Data Model (deltas from sprint-0.md in bold)

Source columns quoted from sprint-0.md:81-89 [VERIFIED: docs/sprints/sprint-0.md]:
- "contacts: name, phone_e164 (unique, nullable), email, status (lead, customer, lost), lead_source_key, acquisition_cost_cents"
- "conversations: contact_id, channel (sms, web, voice, email), status (open, closed), last_message_at"
- "messages: conversation_id, direction (in, out), author (customer, agent, owner), body, status (draft, queued, sent, delivered, failed), provider, provider_message_id, meta (json)"
- "activities: contact_id, type, summary, subject (polymorphic), occurred_at"
- "`icm_versions` table: fingerprint, git_sha (nullable), compiled (json), errors (json), status (active, rejected, superseded), activated_at."

Deltas required by CONTEXT (D-14, D-16, D-18) and by the dashboard/card:
- **`conversations.channel` removed; `messages.channel` (sms, web, voice, email) added** (D-16). Partial unique index: one `open` per contact.
- **`messages`: composite unique (`provider`, `provider_message_id`)** (D-18). NULL provider ids are allowed to repeat (SQLite and Postgres treat NULLs as distinct). Fake outbound ids: `fake-<uuid>`.
- **`activities`: nullable `lead_source_key`, `cost_cents` (integer)** for per-touch attribution (D-14); `subject` via `nullableMorphs('subject')` (note activities have no subject); `Relation::enforceMorphMap()` so class names are not stored. `occurred_at` indexed with `contact_id`.
- **`contacts.last_activity_at`** (denormalized, set in `RecordInbound`/`SendOwnerReply`) for the Contacts table "last activity" column and `open-leads.md` sorting. `email` stored lowercased (D-12 matches on lowercased email). Casts: `acquisition_cost_cents` => integer.
- **`icm_versions`**: add `file_count` (unsigned int) and `notices` (json) so `icm:status` and the Business Folder page do not parse the compiled JSON, and (see Open Question 1) `last_checked_at`. `compiled` nullable for `rejected` rows. Version label "vN" = row `id` (rejections consume numbers; alternative is a separate `version` column assigned on activation only).
- Enums (backed, TitleCase keys): `ContactStatus`, `ConversationStatus`, `MessageDirection`, `MessageAuthor`, `MessageStatus`, `MessageChannel`, `VersionStatus`, `ActivityType` (text received, form received, reply sent, note). Cast all status/type columns. Money columns end `_cents`.
- Reserved source keys that never trigger "unknown source": `text` (D-15) and the default for a form with no `source` (`direct`; D-15 writes "`direct`/`unknown`" — pick `direct`).

## Example Folder Fixture (`examples/example-hvac`)

Required by sprint-0.md:53-60; contract from SPEC.md:197-207 [VERIFIED: docs/SPEC.md:195-207 read]:

| type | File | Required front matter (verbatim SPEC) |
|---|---|---|
| business | business/profile.md | name, phone, timezone, service_area (list) |
| hours | business/hours.md | weekly (mon to sun: open/close or closed), closures (dates), booking_lead_minutes |
| lead_sources | business/lead-sources.md | sources: list of key, name, cost_cents_per_lead |
| knowledge | business/faq.md and others | none beyond type |
| service | business/services/*.md | name, slug, duration_minutes, book_online, price_policy (none, starting_at, fixed), price_cents (unless none), intake (list of key, ask, options) |
| voice | voice/how-we-talk.md | ai_disclosure, sign_off, never_say (list) |
| sending_rules | rules/sending.md | send_on_own (list of action keys), draft_for_owner (list), owner_alert |

Folder contents: `CLAUDE.md`, `START-HERE.md`, `changelog.md` (header only), `business/{CONTEXT.md,profile.md,hours.md,lead-sources.md,faq.md}`, `business/services/{CONTEXT.md,ac-repair.md,<second service>.md}`, `voice/how-we-talk.md`, `rules/{CONTEXT.md,sending.md}`, `agents/README.md`, `automations/README.md`, `connectors/README.md`, `tests/README.md` (each saying which sprint fills it), `records/README.md`, `records/CLAUDE.md`.

Fixture guidance: include a `lead-sources.md` with keys `angi` and `thumbtack` with non-zero `cost_cents_per_lead` (e.g. `4500`) and a free key (`0`); `ac-repair.md` exactly as the SPEC example (`price_cents: 8900`); a second service with `price_policy: none` and no `price_cents`; `profile.md` phone as a **quoted** valid number; a timezone such as `America/Chicago`. Compiled file count for the pristine fixture = profile, hours, lead-sources, faq, 2 services, how-we-talk, sending = **8** typed files (READMEs/routers excluded, D-01); the plan should assert whatever the fixture produces rather than hard-code "14" from D-09's example.

Validator rules to specify in the plan (not all fixed by the SPEC):
- Singleton types (`business`, `hours`, `lead_sources`, `voice`, `sending_rules`) must exist exactly once, otherwise `ActiveConfig::business()` etc. have nothing to return -> plain-words error naming the missing type. [ASSUMED: addition beyond D-01..D-05; low risk]
- `lead_sources`: `sources` non-empty list; `key` slug-like, unique (D-05); `name` string; `cost_cents_per_lead` `integer:strict|min:0`.
- `service`: `slug` unique (D-05) and slug-shaped; `intake.*.key` unique within the file (D-05); `options` optional list of strings.
- `hours.weekly`: each of mon..sun is the string `closed` or a map with `open`/`close` as `H:i`; `closures` `present|array` of `Y-m-d`; `booking_lead_minutes` `integer:strict|min:0`.
- `sending_rules.owner_alert`: SPEC gives no shape; Phase 1 should require it present and non-empty only, leaving the vocabulary for Phase 2 (ROADMAP notes it is undefined). [ASSUMED]
- Unknown extra front-matter keys: ignore in Phase 1 (forward compatible). [ASSUMED]

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|---------------|
| A1 | A duplicate webhook (same provider message id) returns 200 with no re-dispatch | Pattern 5 | Twilio retries until it sees 2xx; wrong code = retry storms |
| A2 | On Postgres a unique violation aborts the transaction unless wrapped in a savepoint | Pattern 5 | Only matters Phase 2; SQLite unaffected |
| A3 | Use `isValidNumber` (not `isPossibleNumber`) as the match-key gate; invalid form phone -> treated as absent + timeline note; both unusable -> 422 | Pitfall 10 | Valid but unassigned numbers would be dropped to "no phone"; D-12 is silent |
| A4 | Partial unique index "one open conversation per contact" is wanted at DB level | Don't Hand-Roll | Without it, app-level only; concurrent inbounds could create two open threads |
| A5 | Singleton file types must each exist exactly once; unknown extra keys ignored; `owner_alert` only required-non-empty | Fixture | Over/under-strict validation vs. Ian's expectation |
| A6 | `retryUntil()` is the right control for jobs released by `WithoutOverlapping` | Pitfall 15 | Jobs could fail after a few releases under contention |
| A7 | Filament table/action assertions work through `Livewire::test()` on resource pages (proven only for the Dashboard page) | Pitfall 11 | Fallback is adding `pest-plugin-livewire` (needs approval) |
| A8 | The "vN" label = `icm_versions.id` | Data Model | Gaps in numbering after rejections may confuse the owner |
| A9 | Default source key for a form without `source` is `direct` | Data Model | D-15 left it as "`direct`/`unknown`" |
| A10 | /dev/chat calls the inbound code path in-process rather than HTTP-posting to its own server | Pattern 8 | Literal HTTP self-call can deadlock a single-worker `php artisan serve` |

## Open Questions

1. **Where does "last sync result" come from when a no-op creates no row (D-08)?**
   - What we know: D-08 creates no `icm_versions` row for "no changes" but the Business Folder page must show the last sync result; reading `changelog.md` back from disk would violate "nothing reads from disk except the sync".
   - Unclear: persistence for no-op (and for "sync could not start").
   - Recommendation: add `last_checked_at` (+ `last_result` string) to the active row, updated on a no-op; the page shows the newest of {latest rejected row, active row's last check}. Alternative: a small `icm_sync_runs` table. Settle before 01-01 plans.
2. **Do router files participate in the fingerprint?** Recommendation: yes (they are compiled, so editing `CLAUDE.md` mints a new version); later-sprint-type files are not compiled and do not.
3. **Rules recording needs Ian's explicit go (STATE blocker).** `boost.json` shows installation done and `.ai/rules` does not exist [VERIFIED: ls]; QUAL-01 is the explicit request `RecordRule` requires. Confirm the exact two rule texts/globs above.
4. **/dev/chat "posts to the fake webhook":** in-process call of the shared parse+`RecordInbound` path (recommended) vs. a literal HTTP request. Confirm interpretation.
5. **`User implements FilamentUser`** (Pitfall 9): a small model change outside the listed scope but required for HTTP-level admin tests and any non-local deploy. Confirm.
6. **Tracked `changelog.md` in the example folder** gets modified by manual syncs. Options: accept the churn (and `git checkout` it), or gitignore `examples/*/changelog.md` and ship a header via the sync creating it when absent. Not specified by D-23.
7. **Conversation close/reopen UI:** D-16's "closed conversation starts a new one" is only reachable via model state in Phase 1 unless a Close action is added to the conversation view. Recommend a one-line header action; otherwise test via direct status update.

## Environment Availability

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| PHP | Everything | ✓ | 8.5.0 | — |
| Composer | `composer require giggsey/...` | ✓ | 2.8.12 | — |
| ext-intl, pdo_sqlite, sqlite3, mbstring | Filament, SQLite, libphonenumber | ✓ | present (`php -m`) | — |
| SQLite | Dev + tests | ✓ | CLI 3.51.0 (partial indexes OK) | — |
| Git | git_sha, `check-ignore` verification | ✓ | 2.50.1 | — |
| Node/npm | Only for `npm run build` if Vite assets needed | ✓ | v24.12.0 | Not needed if layout avoids `@vite` |
| Redis / Postgres / Horizon | Not used in Phase 1 | — | — | SQLite + database queue/cache (cache_locks table migration exists) |
| Twilio account | Not used | — | — | Fake channel (DEC-05) |
| Laravel Boost MCP `record-rule` tool | QUAL-01 rules | ✓ (installed, `.mcp.json` present) | boost 2.10.3 | `RuleRepository` via container |

**Missing dependencies with no fallback:** none.
**Missing dependencies with fallback:** none required for Phase 1.

## Validation Architecture

`workflow.nyquist_validation` is absent in `.planning/config.json` [VERIFIED: file read], so this section is included. `workflow.tdd_mode: true`, `workflow.test_command: "php artisan test"` [VERIFIED].

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Pest 5.3.1 + pest-plugin-laravel 5.0.1 (+ pest-plugin-arch installed); Livewire testing via `Livewire\Livewire::test()` |
| Config file | `phpunit.xml` (Unit + Feature suites; sqlite `:memory:`, `QUEUE_CONNECTION=sync`, `CACHE_STORE=array`); `tests/Pest.php` binds `TestCase` + `RefreshDatabase` to `Feature` only |
| Quick run command | `php artisan test --compact tests/Feature/Icm` (narrowest dir/file for the task) |
| Full suite command | `php artisan test --compact` |

Output note: `laravel/pao` makes `--compact` emit agent-oriented JSON. [VERIFIED: probe run produced JSON]

### Phase Requirements -> Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| ICM-01 | Valid sync of temp copy activates, row `active`, `icm:status` prints version/file count/time; second sync = "Already active", no new row | feature | `php artisan test --compact tests/Feature/Icm/SyncTest.php` | ❌ Wave 0 |
| ICM-01 | `ActiveConfig` accessors (`business`, `hours`, `leadSource`, `service`, `sendingRules`, `voice`, `file`), no-active exception, refresh after sync, scoped binding | feature | `... tests/Feature/Icm/ActiveConfigTest.php` | ❌ Wave 0 |
| ICM-02 | Table-driven (Pest dataset): each broken-file case -> exact D-04 message, exit code 1, previous version still `active`, `rejected` row stored, changelog entry newest-first (D-09) | feature | `... tests/Feature/Icm/ValidatorTest.php` and `SyncTest.php` | ❌ Wave 0 |
| ICM-02 | YAML traps: unquoted `+1555…`, int slug, unquoted date, `yes`, `[]` lists, tabs, unclosed front matter, missing `---`, unknown/missing `type:` (D-03), later-sprint type notice (D-02), duplicates (D-05), all errors reported (D-04) | feature | `... tests/Feature/Icm/ValidatorTest.php` | ❌ Wave 0 |
| ICM-02 | Sync ignores `records/` (file with garbage front matter and a nested `business/records/` file behave per Pitfall 3), `changelog.md` and README excluded from fingerprint | feature | `... tests/Feature/Icm/SyncIgnoresRecordsTest.php` | ❌ Wave 0 |
| LEAD-01 | Fake webhook (Twilio-shaped form body) from a new number creates contact, open conversation, inbound message, activity; second text same conversation; duplicate `MessageSid` records once | feature | `... tests/Feature/Inbound/FakeTextWebhookTest.php` | ❌ Wave 0 |
| LEAD-02 | `POST /leads` `source=angi` -> source + `cost_cents` from lead-sources; unknown source accepted + note (D-15); no phone/email -> 422 (D-12); throttle | feature | `... tests/Feature/Inbound/LeadFormTest.php` | ❌ Wave 0 |
| LEAD-03 | Text then form (and reverse) from same phone -> one contact; E.164 normalization, invalid phone policy; email match fill/keep phone (D-13); first-touch attribution kept, later touch cost on activity (D-14); closed conversation -> new conversation (D-16) | feature | `... tests/Feature/Inbound/ContactMatchingTest.php` | ❌ Wave 0 |
| LEAD-04 | Owner reply through `ConversationThread` -> outbound message `status=sent`, provider `fake`, activity written; no-phone contact -> reply disabled (D-17) | feature (Livewire) | `... tests/Feature/Inbound/OwnerReplyTest.php` | ❌ Wave 0 |
| RECS-01 | Card matches DB; two same-second events -> one file with both, id-ordered; name change removes old file (D-20); INDEX/open-leads content (D-22); `records:rebuild` regenerates + removes orphans + writes `records/CLAUDE.md` | feature | `... tests/Feature/Records/CustomerCardTest.php`, `RecordsRebuildTest.php` | ❌ Wave 0 |
| RECS-01 | Job contract: `ShouldBeUniqueUntilProcessing`, `uniqueId`, `WithoutOverlapping` key, event dispatched after commit; summary read inside lock | feature (`Queue::fake`, `Event::fake`) | `... tests/Feature/Records/RenderCustomerCardJobTest.php` | ❌ Wave 0 |
| RECS-01 | Injection hygiene: customer body blockquoted; `|` and newlines escaped in INDEX | feature | `... tests/Feature/Records/CardInjectionTest.php` | ❌ Wave 0 |
| DASH-01 | Contacts list/view, Conversations list/view with thread, Business Folder page loads and Sync Now (success + rejected notification); HTTP `get('/admin/...')` 200 for a logged-in user | feature (Livewire + HTTP) | `... tests/Feature/Filament` | ❌ Wave 0 |
| DASH-02 | `/dev/chat` 404 in `testing`, 200 when env flipped to `local`; submitting posts a text that lands on the timeline | feature | `... tests/Feature/DevChatTest.php` | ❌ Wave 0 |
| QUAL-01 | `.ai/rules/index.md` exists and rule files mention "integer cents" and "ActiveConfig"; schema guard (every money-named column ends `_cents` and is an integer type via `Schema::getColumns`); arch guards (Yaml only in Loader; no direct file reads outside the sync); webhook CSRF exemption present | feature + arch | `php artisan test --compact tests/Feature/Quality tests/Unit/ArchitectureTest.php` | ❌ Wave 0 |

### Sampling Rate
- **Per task commit:** the narrowest test file(s) for the task (`php artisan test --compact <path>`).
- **Per wave/plan merge:** `php artisan test --compact` for the touched directory; at plan end the full suite.
- **Phase gate:** `php artisan test --compact` fully green (ask Ian to run per CLAUDE.md), plus manual UAT: real `curl` POST to `/leads` and `/webhooks/text/fake` (CSRF exemption), `git check-ignore` checks, one `icm:sync` against the real example folder.

### Wave 0 Gaps
- [ ] `tests/Pest.php`: global `beforeEach` copying `examples/example-hvac` to a temp dir and setting `config(['icm.path' => …])`, `afterEach` cleanup; helpers `activateExampleFolder()` (runs the real sync) and `breakFile(string $path, callable $mutate)`.
- [ ] `phpunit.xml`: `ICM_PATH` env pointing at a nonexistent path so a missed override fails loudly.
- [ ] `tests/Feature/{Icm,Inbound,Records,Filament,Quality}/…` and `tests/Unit/ArchitectureTest.php` as listed above (create with `php artisan make:test --pest`).
- [ ] `examples/example-hvac/` fixture must exist before any sync test (it is plan 01-01's first task).
- [ ] Factories for `Contact`, `Conversation`, `Message`, `Activity`, `IcmVersion` (rules: models get factories + seeders).
- [ ] Framework install: only `composer require giggsey/libphonenumber-for-php-lite`.

## Security Domain

`security_enforcement` is not set to false in config (absent = enabled). [VERIFIED: .planning/config.json]

### Applicable ASVS Categories
| ASVS Category | Applies | Standard Control |
|---------------|---------|-----------------|
| V2 Authentication | yes | Filament panel login (existing); `User implements FilamentUser` for non-local |
| V3 Session Management | yes (inherited) | Laravel session via panel middleware stack (existing) |
| V4 Access Control | yes | Dashboard behind `Authenticate`; `/dev/chat` environment gate; fake webhook only when text driver is `fake` |
| V5 Input Validation | yes | `FormRequest` for `/leads` and the webhook; Laravel Validator for folder files; libphonenumber for phones; never `$request->all()` into models (`#[Fillable]`) |
| V6 Cryptography | no | Nothing new (no secrets in folder files; Twilio signature verification is Phase 2 TXT-01) |
| V8 Data Protection | yes | Customer PII lives in `records/` -> gitignored (D-23); no card numbers anywhere |
| V12 Files | yes | Path handling in Loader (Finder, no symlink follow, relative paths only); card filenames slug+id only; atomic writes |
| V13 API/Web service | yes | Throttle `/leads` (named limiter in `AppServiceProvider`); CSRF exemption scoped to the two routes only |

### Known Threat Patterns for this stack
| Pattern | STRIDE | Standard Mitigation |
|---------|--------|---------------------|
| Unauthenticated fake webhook open in production | Spoofing/Tampering | Route 404s unless `channels.text.driver === 'fake'`; Twilio driver verifies signature in Phase 2 |
| Lead-form spam / flooding | DoS | `throttle` limiter, validation caps (`max:` lengths), Phase 2 may add captcha |
| Prompt injection / markdown spoofing via customer text into cards | Tampering | Blockquote every customer body; label as untrusted; escape table cells |
| Path traversal/symlink escape through the ICM folder or card names | Tampering/Info disclosure | Finder (no follow links), relative paths, slug-only filenames |
| YAML object deserialization / tags | Tampering | Never pass `Yaml::PARSE_OBJECT`; probe showed `!php/object` yields `null` with default flags |
| Retried webhook double-recording | Repudiation/Tampering | Composite unique index + catch (D-18) |
| Mass assignment through `/leads` | Tampering | Explicit DTO fields; `#[Fillable]` allow-lists |
| PII in logs (fake channel "stores and logs") | Info disclosure | Log ids and lengths, not bodies, outside local |

## Sources

### Primary (HIGH confidence)
- Local vendor source read/executed this session: `vendor/laravel/framework` (Queue `CallQueuedHandler`, `QueueServiceProvider`, `Filesystem::replace`, `PreventRequestForgery`, `Middleware::preventRequestForgery`, Eloquent `Builder::createOrFirst`, attributes dirs, console stub), `vendor/laravel/boost` (`RecordRule`, `RuleRepository`, `config/boost.php`), `vendor/filament/filament` (`Authenticate`, `Pages/Page`), `vendor/filament/schemas` (`Components/Livewire`), `vendor/livewire/livewire` (`HandleRouting`, config).
- Probes run this session: YAML scalar behavior, Laravel Validator `failed()`/strict rules, Finder `exclude` semantics, `.gitignore` case-insensitivity in a scratch repo, libphonenumber behavior in a scratch project, Filament HTTP 403 vs `Livewire::test` in a temporary test (deleted afterwards).
- Repo docs: CONTEXT.md, REQUIREMENTS.md, STATE.md, ROADMAP.md, PROJECT.md, SPEC.md, DECISIONS.md, SETUP.md, docs/sprints/sprint-0.md, CLAUDE.md, phpunit.xml, composer.json, `.planning/config.json`.

### Secondary (MEDIUM confidence)
- filamentphp.com/docs/5.x: resources/overview, resources/viewing-records, testing/testing-resources, navigation/custom-pages, actions/overview.
- pestphp.com/docs/arch-testing.
- twilio.com/docs/messaging/guides/webhook-request.
- github.com/giggsey/libphonenumber-for-php-lite README; Packagist API (age, downloads, requires).

### Tertiary (LOW confidence)
- None relied upon; Postgres savepoint behavior and `retryUntil`/released-attempt interplay are tagged `[ASSUMED]` in the Assumptions Log.

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — versions confirmed with `composer show`; single new package verified on Packagist and run locally.
- Architecture: HIGH for sync/inbound/render patterns (each constraint traced to a vendor read or probe); MEDIUM for Filament layout choices (embedded Livewire thread component is idiomatic but first exercised in 01-03).
- Pitfalls: HIGH — 10 of 16 reproduced live this session.

**Research date:** 2026-10-10
**Valid until:** 2026-11-09 (Filament 5 and Livewire 4 move quickly; re-run `composer show` before planning if packages are updated)
