# Phase 1: Foundation - Context

**Gathered:** 2026-10-10
**Status:** Ready for planning

<domain>
## Phase Boundary

The folder drives the app, and every way a lead can arrive lands on one timeline that the owner can work from and the owner's own AI can read (SPEC Sprint 0). Delivers: `examples/example-hvac`, a validated and versioned sync (`icm:sync`, `icm:status`, `changelog.md` writes) read only through `App\Icm\ActiveConfig`; contacts, conversations, messages and activities fed by the fake text webhook and the web form through `RecordInbound`; a thin Filament dashboard (Contacts, Conversations with a reply box, Business Folder page) and a local-only `/dev/chat`; customer cards, `INDEX.md`, `open-leads.md` and `records/CLAUDE.md` rendered from the database; `records:rebuild`; the two Boost project rules in `.ai/rules`.

Requirements: ICM-01, ICM-02, LEAD-01, LEAD-02, LEAD-03, LEAD-04, RECS-01, DASH-01, DASH-02, QUAL-01.

Not in this phase: AI replies, Twilio (beyond a stub), booking, money, voice, automations, walk tests.

</domain>

<decisions>
## Implementation Decisions

### Validator scope
- **D-01:** Orientation files (`README.md`, `CLAUDE.md`, `START-HERE.md`, `CONTEXT.md`) are not validated and are not counted in the file count shown by `icm:status`.
- **D-02:** Files with a later-sprint type (`agent`, `automation`, `connector`, `walk_test`) are skipped and reported as "not supported yet". This is a notice, not an error, and does not block activation.
- **D-03:** An unknown `type:` value, or a missing `type:` on any other `.md` file, is a plain-words error.
- **D-04:** A failed sync reports **all** errors, grouped by file, never only the first. Message style: `business/services/ac-repair.md: price_policy must be one of none, starting_at, fixed (you wrote maybe)`.
- **D-05:** Cross-file and within-file checks that are plain-words errors: two lead sources with the same `key`; two service files with the same `slug`; duplicate intake `key`s within one service.

### Compiled version and ActiveConfig
- **D-06:** The compiled version (`icm_versions.compiled`) holds every typed file's front matter **and** markdown body, plus the untyped router files (`CLAUDE.md`, `CONTEXT.md`, `START-HERE.md`) as raw text. Sprint 1 agents must be able to load any routed path whole from `ActiveConfig`, never from disk (DEC-10/11).
- **D-07:** `ActiveConfig` exposes typed accessors for what the engine uses (for example `business()`, `hours()`, `leadSource('angi')`, `service('ac-repair')`, `sendingRules()`, `voice()`) plus a raw `file('business/faq.md')` lookup for routed reads. No generic dot-path `get()`.

### Sync runs and changelog
- **D-08:** If the candidate's fingerprint equals the active version's, the sync is a no-op: no new `icm_versions` row. It reports "Already active: vN, no changes" and still appends a short "no changes" entry to `changelog.md`, so every attempt is recorded.
- **D-09:** `changelog.md` entries are newest first: a dated heading with the outcome (`## 2026-10-10 14:32 · Rejected` / `## … · Live as v13 (14 files)` / `## … · No changes`), then one bullet per error. Times are in the business timezone from `business/profile.md`. If `profile.md` is the broken file, use the active version's timezone.
- **D-10:** The Sync Now button on the Business Folder page runs the same sync action as `icm:sync` **inline** (not queued) and shows the result and any errors in a Filament notification.
- **D-11:** No rollback command in Phase 1. Rollback means fixing the file and syncing again, so the folder stays the source of truth. A rejected sync already leaves the previous version live.

### Lead matching and attribution
- **D-12:** Contact matching: by `phone_e164` first, then by lowercased email. If neither matches, create a contact. A form with neither phone nor email is rejected with a 422.
- **D-13:** If a form's email matches an existing contact whose phone is **empty**, fill in `phone_e164`. If the stored phone is **different**, keep the stored phone and note "form gave a different phone: +1…" on the timeline. A form phone never overwrites a stored one.
- **D-14:** Attribution is first touch on the contact: `contacts.lead_source_key` and `acquisition_cost_cents` keep the first source. Every later touch records its own source and `cost_cents` on its timeline activity, so a repeat charge from Angi is still visible.
- **D-15:** A source key not in `lead-sources.md` is accepted, never dropped. The raw key is stored, cost is null and "unknown source: xyz" is noted on the timeline. A form with no `source` gets a default key (`direct`/`unknown`). Texts are recorded with source `text`.
- **D-16:** **One open conversation per contact**, shared across channels. — **Reversibility:** one-way — the migration moves `channel` off `conversations`, and every Filament query and card render built on it would change. **This deviates from `docs/sprints/sprint-0.md`**: `channel` (sms, web, voice, email) moves from `conversations` to `messages`. A web form creates a message in the contact's open conversation. Any contact has at most one `open` conversation. An inbound after the conversation is closed **starts a new conversation**. Closed ones are kept as past threads.
- **D-17:** The owner's reply box sends through `TextChannel` when the contact has a phone. With no phone, the reply box is disabled and shows the contact's email.
- **D-18:** `messages.provider_message_id` is unique per provider (a composite unique on `provider` + `provider_message_id`), so a retried webhook cannot record a message twice (SPEC "The bridge" step 1).
- **D-19:** The default phone region for E.164 parsing is US, set in config.

### Customer cards and records/
- **D-20:** Card filename is name slug plus contact id: `records/customers/dana-whitfield-17.md`. A contact with no name falls back to `contact-15551234567-17.md`. When a contact's name changes, the old file is removed on re-render. `INDEX.md` maps name, phone and file.
- **D-21:** A card carries the full timeline, newest first, with every message and form in full (SPEC "The bridge"). No cap in Phase 1.
- **D-22:** `open-leads.md` lists contacts with `status=lead` **and** an open conversation, newest activity first, showing name, source, waiting since and a last-message preview.
- **D-23:** In `examples/example-hvac/records/`, only `README.md` and `CLAUDE.md` are tracked in git, and everything else generated (`customers/`, `INDEX.md`, `open-leads.md`) is ignored. **Ignore rules must be scoped to `examples/*/records/`** so they cannot match `app/Records/` on a case-insensitive macOS filesystem.

### Settled at plan time (2026-10-10, answers to RESEARCH.md Open Questions)
- **D-24:** `User` implements `Filament\Models\Contracts\FilamentUser`; `canAccessPanel()` returns `true` for any authenticated user (single-owner app). Without it `/admin` returns 403 outside `local`.
- **D-25:** `examples/*/changelog.md` is gitignored (scoped to `examples/*/`, same care as D-23). The sync creates `changelog.md` with a short header when it is missing, then appends entries per D-08/D-09. The example folder does not exist yet, so `changelog.md` is simply never committed.
- **D-26:** Two Boost project rules are recorded in `.ai/rules` (via the Boost `record-rule` tool or `Laravel\Boost\Rules\RuleRepository`, never hand-written): (1) "Money is integer cents" — all money values are stored and passed as integer cents (`*_cents` integer columns, integer casts), never floats; convert for display only — on globs `app/**`, `database/migrations/**`, `database/factories/**` and `tests/**`. (2) "Business configuration is read only through ActiveConfig" — application code reads business configuration only through `App\Icm\ActiveConfig`, never from the ICM folder; only `App\Icm\Loader` (run by the sync) reads the folder — on glob `app/**`.
- **D-27:** The conversation view has Close and Reopen header actions. Close sets the conversation `closed`. Reopen is only available (and only succeeds) when the contact has no other `open` conversation, preserving D-16's one-open-conversation rule.

### Claude's Discretion
- If the very first sync fails with `profile.md` broken and there is no active version, changelog times fall back to the app timezone (UTC), and the entry says so.
- Tests point `ICM_PATH` at a temporary copy of the example folder, so a test run never writes cards or changelog lines into the repo.
- Dashboard layout details (Filament table columns beyond those in sprint-0.md, timeline presentation on the contact view), `/dev/chat` refresh mechanism (Livewire polling is fine), and the seeder's fictional data follow `docs/sprints/sprint-0.md` Step 3.
- Exact `ActiveConfig` method names, the compiled JSON shape, and the fingerprint algorithm (content hash plus git sha when the folder is a repo).
- How "superseded" versions are kept (all rows retained is fine).
- Last sync result for a no-op (RESEARCH Open Question 1): default is `last_checked_at` + `last_result` on the active `icm_versions` row, updated on a no-op; the Business Folder page shows the newest of the latest rejected row and the active row's last check.
- Router files (`CLAUDE.md`, `CONTEXT.md`, `START-HERE.md`) feed the fingerprint, so editing one mints a new version (Open Question 2).
- `/dev/chat` "posts to the fake webhook" by calling the same parse + `RecordInbound` path in-process, not by an HTTP self-call (Open Question 4).

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Plan of record for this phase
- `docs/sprints/sprint-0.md`: acceptance criteria, the three build steps, table columns, class names, routes, Laravel concepts to walk through, and the walk-through log. **Note the D-16 deviation:** `channel` lives on `messages`, not `conversations`.
- `.planning/ROADMAP.md` § Phase 1: success criteria, plan sketch (01-01, 01-02, 01-03) and planning notes.
- `.planning/REQUIREMENTS.md`: ICM-01, ICM-02, LEAD-01 to LEAD-04, RECS-01, DASH-01, DASH-02, QUAL-01.

### Product and locked decisions
- `docs/SPEC.md` § "The bridge": the sync steps, records rendering rules (transaction, unique provider id, re-render reads the DB, no overlapping renders, temp file and rename).
- `docs/SPEC.md` § "The business folder" and "File types (the front matter contract)": the folder layout and the required front matter per type, which are the Validator's rule sets.
- `docs/DECISIONS.md`: DEC-01 to DEC-14 (locked; do not reopen), especially DEC-03 (records), DEC-05 (fake channel), DEC-06 (SQLite now), DEC-10 (ICM and ActiveConfig).
- `.planning/PROJECT.md` § Constraints: integer cents, config read path, folder layout, dependencies, process.
- `.planning/INGEST-CONFLICTS.md`: info notes on validator scope and records subset.

### Setup
- `docs/SETUP.md`: `boost:install` and how Boost project rules are recorded (QUAL-01's `.ai/rules` task).

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `symfony/yaml` 8.1 and `league/commonmark` 2.10 are installed (via the framework) for front matter and body parsing in `App\Icm\Loader`.
- Filament 5.10 admin panel (`app/Providers/Filament/AdminPanelProvider.php`) auto-discovers `app/Filament/Resources`, `app/Filament/Pages` and `app/Filament/Widgets`. New resources and pages drop in with no provider change.
- `jobs` table migration exists (queue driver `database` in `.env.example`) for `ProcessInbound` and `RenderCustomerCard`.
- `.env.example` already uses `DB_CONNECTION=sqlite`.

### Established Patterns
- The scaffold is untouched: no app models beyond `User`, and no custom routes (`routes/web.php` only has `/`). Every pattern in this phase sets precedent, so follow Laravel and Boost conventions (`php artisan make:*`, Pest feature tests, Pint).
- `laravel/ai` added `agent_conversations` tables. Leave them alone; they're unrelated to the app's `conversations` table, and the name overlap is only cosmetic.

### Integration Points
- `giggsey/libphonenumber-for-php-lite` is **not yet installed**. The docs mandate it (PROJECT.md Constraints), so adding it counts as approved.
- `.ai/rules` does not exist yet, so recording the two Boost rules is a real task (QUAL-01).
- New config: `ICM_PATH` (`.env`), text channel driver selection, default phone region (US).
- `App\Actions\RecordInbound` is the single entry point for both inbound routes. It dispatches the empty `ProcessInbound` job that Sprint 1 fills.

</code_context>

<specifics>
## Specific Ideas

- Error messages are written for an owner to act on, naming the file, the field, the allowed values and what they wrote.
- Changelog entries should be easy for an owner or their AI to scan, with a dated heading and bullets.
- Never lose a lead: unknown sources and odd phone and email combinations are recorded and noted, not rejected (except a form with no phone and no email).

</specifics>

<deferred>
## Deferred Ideas

- `icm:activate {version}`: re-activate a superseded version (rollback). Not in Phase 1; revisit when a client needs it.

</deferred>

---

*Phase: 01-foundation*
*Context gathered: 2026-10-10*
