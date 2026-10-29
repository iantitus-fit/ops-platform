# Phase 7: The Spine and Leads - Research

**Researched:** 2026-10-29
**Domain:** Laravel 13 / Filament 5 / Pest 5 in-repo build: a platform-owned `projects` table, a profile word checked by the sync, a customer-card section, a contact-screen list with actions, and a leads room that opens a project beside the shared inbound path.
**Confidence:** HIGH. Almost everything here is in-repo; the riskiest question (what existing tests break) was answered by running the full suite against a prototype (see "Blast-radius spike").

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
Copied from `07-CONTEXT.md` `## Implementation Decisions` (D-01 to D-22). Full text lives there; the planner must read it. One-line index so nothing is missed:

- Carried forward, do not reopen: DEC-01 to DEC-14, 02 D-01 to D-56, 02.1 D-01 to D-51, 04 D-01 to D-27, 05 D-01 to D-33, 06 D-01 to D-21. `RecordInbound` is not edited. Front desk code, prompt and rules stay frozen. Phase 7 raises no new alert.
- **D-01** A project opens automatically only when an inbound created the contact (`matched_by` new). A web form matching a known contact by phone or email opens nothing. A text from a known number opens nothing.
- **D-02** The open runs after the inbound commits, beside `RecordInbound` (observer or listener). Survives failure, retried, never doubles, does nothing if the contact already has a project. A failed open never rolls back or delays the inbound.
- **D-03** The open checks the leads room is live when it runs. Room turned on later opens nothing for earlier contacts (no backfill). Leads is not on the never-left-out list.
- **D-04** Second job: the owner opens by hand with a "New <word>" action on the contact screen. Nothing asked, flagged or alerted.
- **D-05** Open or closed; owner closes and reopens by hand; a contact may have several open projects.
- **D-06** Nullable conversation reference on the projects table. Automatic open takes the conversation the lead arrived in; hand-opened takes the contact's open conversation at that moment or none. No column on `conversations` or `messages`.
- **D-07** No project action ever changes a contact's status.
- **D-08** Project holds: contact, conversation, open/closed, opened/closed times, who opened and who closed (a new lead, or the user), a short owner-typed name (one line, length-bounded, empty by default). Screens fall back to "<word> N".
- **D-09** Criterion 3 met by shape: no appointment column, nothing assumes one appointment. Phase 8 criterion 2 is the proof of PROJ-01's appointment clause. Never build an appointments table here.
- **D-10** `projects` is platform-owned: no room lists it in `tables`. One-way migration.
- **D-11** Writing a project is not an agent action. One action each for open, close, reopen, rename, each recording who did it. An architecture test allows those four and nothing else to write the table. Reads are free.
- **D-12** Profile field holds one word from a fixed list in code: Job, Project, Matter, Case, Work order, Engagement, each with a plural in code. Matched without regard to case. Missing or unlisted value refused in plain words naming `business/profile.md` and the field and listing the choices; previous version stays live. `docs/SPEC.md` lists the same words and a test fails if they disagree. Example business sets Job.
- **D-13** Card gets a `## <plural word>` section above the timeline, one line per project (name or "<word> N", open or closed, when and how opened). Open, close, reopen, rename each add a timeline entry. Section and entries are platform types drawn by `CustomerCard`, not leads-room entries. Section omitted entirely when the contact has no project. Owner-typed name quoted under a label and neutralized like customer text; `CardInjectionTest.php` gains that case.
- **D-14** Contact screen lists the contact's projects with New, Close, Reopen, Rename.
- **D-15** Leads room claims `NewLeads` as a pointer; `FrontDeskBootTest.php:90` changes. Leads room adds the guard that refuses a widget one room claims and another adds in its sections (closes 06 WR-09). Its own section lists open projects; each row's contact link reachable by keyboard; "opened" shows a date. Section hides when room is off; projects still show on contact screen and card.
- **D-16** Open-projects section shows the lead source by its name in `business/lead-sources.md` for automatic opens; for hand-opened shows who opened it.
- **D-17** `records/open-leads.md` unchanged, its tests unchanged. Leads room declares `writes_records: none`.
- **D-18** Leads room owns no files, lists no tables, no connector slot, optional. Reads `business/lead-sources.md` and `business/offers/`. Nothing uses offers yet; its `CONTEXT.md` says so. A room that owns no files is never shown as empty (04 D-21).
- **D-19** Web form stays shared and unowned. Marketing's `CONTEXT.md` sentence "Where lead intake such as the web form sits is the leads room's call." is updated in the example folder, in a plan with its tests.
- **D-20** The `needs` field is not added; carried to the Phase 8 discuss.
- **D-21** No UI-SPEC. Stock Filament screens; styling waits for Phase 11; leads section gets the axe check in its boot test.
- **D-22** Criterion 4 allows exactly these existing-test changes: card tests that now show a project; the `exampleFolderFileCount()` number; `FrontDeskBootTest.php:90`; `profile.md` fixtures written from scratch (add the field line only); tests asserting the text of marketing's `CONTEXT.md` (new sentence only). Each plan names each changed test by file and line. Any other existing test change stops and goes to Ian.

### Claude's Discretion
- Whether the open is an Eloquent observer, event listener or queued job dispatched after commit, how it retries, and how "never two" is enforced on SQLite and Postgres (lock, conditional insert or unique index), within D-02 and D-03.
- The profile field's key name, the plural strings, and the name's length bound.
- How "<word> N" is numbered (the discussion's reading: the contact's own count, in opening order).
- Placeholder wording for the card section, timeline entries, contact screen actions, room section and sync refusal, in the `PlainWords` style, no exclamation points or em dashes.
- The plain-words body of `rooms/leads/CONTEXT.md` and its `reads` list (folder paths only).
- The test layout for the room's boot test and the architecture test (D-11).

### Deferred Ideas (OUT OF SCOPE)
- Phase 8: booking opens a project when the contact has none; a project closing on its own; which of two open projects an appointment goes on; the `needs` field; a project's service; a project's service address; service history by address.
- Phase 9: a business-wide project view not depending on the leads room; a business-wide projects records file.
- Phase 8 or backlog 999.1: what makes a contact a Customer.
- Before first client: a sweep for an automatic open lost in a crash between commit and dispatch. Phase 7 builds no sweep.
- Not in this phase: appointments, estimates and payments (999.1), pipeline stages, anything that changes a contact's status.
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| PROJ-01 | A Project record ties a piece of work to its contact and conversation; appointments hang off it later. | "Projects table", "Four actions", "Project model and number N" below. Appointment clause is proven in Phase 8 (D-09). |
| PROJ-02 | `business/profile.md` has one word field checked by the sync; dashboard and card use it; front desk sees it only through the card. | "The business's word" (enum, Validator hook, `ActiveConfig::projectWord()`, SPEC test), "Customer card section". Blast-radius spike proves the front desk path needs no code change. |
| ROOM-07 | Leads room to the room standard, reads lead sources and offers. | "The leads room" (provider, example `CONTEXT.md`, section, boot test, WR-09 guard). |
| LEAD-05 | New lead (text from new number or form) opens a project beside the inbound path, front desk unchanged, green on both databases. | "Automatic open" (observer + gated job), spike results. |
</phase_requirements>

## Summary

Phase 7 is almost entirely an in-repo build on patterns that already exist: `CloseConversation`/`ReopenConversation` are the template for the four project actions; `MarketingServiceProvider` plus `MarketingSection` are the template for the leads room; the `conversations_one_open_per_contact` partial unique index is the template for "never two"; the Validator's per-type error methods are the template for the word check; `intakeAnswerLines()` in `CustomerCard` is the template for quoting owner-typed text. No new Composer or npm package is needed.

The single riskiest question for criterion 4 ("only these existing tests change") was settled empirically. A throwaway prototype (a scratch copy of the repo, never the real tree) added the example `rooms/leads/CONTEXT.md`, `project_word: Job`, a projects table, a card section and timeline entry, and a `Contact::created` hook that dispatches an after-commit job. The full suite (2278 tests, SQLite, about 165 seconds) had exactly three failures, all on the D-22 list: `CardInjectionTest.php:90` (card heading count) and `FrontDeskBootTest.php:90` (two datasets). A first version of the hook, without a dispatch-time room check or a try/catch, failed nine more tests that call `Queue::fake()` and assert on pushed jobs; the fix is a room-live check at dispatch time as well as at run time, plus a try/catch around the dispatch. That is a design constraint the planner must carry into the plan, not a detail.

Two facts in CONTEXT.md are slightly off and should be corrected in the plans: the card test that changes is `tests/Feature/Records/CardInjectionTest.php:90`, not `:36` (line 36 posts a text with no example folder active, so no leads room and no project); and no existing test writes a `profile.md` from scratch or asserts marketing's `CONTEXT.md` sentence, so those two D-22 allowances are probably unused (verify again at plan time with the greps in "Sources").

**Primary recommendation:** Build in this order: (1) the word (enum, SPEC section and test, Validator check, `ActiveConfig::projectWord()`, example `project_word: Job` in the same commit as the required check); (2) the projects table, model, factory, `ActivityType` cases, morph nickname and the four actions with a write-confinement test; (3) the card section and timeline arms; (4) the contact-screen relation manager; (5) the leads room (provider, example folder, section, WR-09 guard, boot test, `FrontDeskBootTest.php:90`, file count); (6) the automatic open (observer in the leads provider's `boot()`, gated job). Dispatch only while the leads room is live, report and swallow a dispatch failure, and enforce "never two" with a partial unique index plus a savepoint around the insert.

## Architectural Responsibility Map

This app is a server-side Laravel monolith with Filament pages (Livewire); there is no separate SPA. Tiers below are the monolith's layers.

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|-------------|----------------|-----------|
| Project word list and validation | Sync (`App\Icm\Validator`, enum in `App\Enums`) | ActiveConfig (read) | The sync is the only thing that reads the folder (DEC-10); screens read the compiled copy through `ActiveConfig` only (`.ai/rules/app.md`). |
| Project record and its four writes | API/Backend (`app/Actions`, `app/Models`) | Database (migration, partial unique index) | Platform-owned kind (04 D-23); one action per write (D-11). |
| Automatic open on a new lead | Backend (leads room observer + queued job) | Database (unique index backstop) | After-commit, retried, idempotent (D-02). |
| Customer card section and timeline arms | Backend (`CustomerCard`) | Database | Rendered from the database; read by the owner's AI and the front desk. |
| Contact-screen project list and actions | Filament page (relation manager on `ContactResource`) | Backend actions | Owner UI; calls the four actions. |
| Open-projects dashboard section | Filament widget (`RoomSection` subclass, own view) | ActiveConfig (word, lead source names) | Room-owned dashboard content, hidden when the room is off. |
| Room contract, example folder, boot test | Folder (`rooms/leads/CONTEXT.md`) + room provider | Registry | Room standard (ROOM-01 to ROOM-05). |
| Widget-ownership guard (WR-09) | Backend (`RoomRegistry`) | | Boot-time registry check, same place as `guardClaimedWidgets()`. |

## Standard Stack

All already installed; versions read from `composer.json`/`composer show` this session. No dependency change is needed or allowed without Ian's approval (CLAUDE.md, Boost "Do not change the application's dependencies without approval").

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| laravel/framework | ^13.17 [VERIFIED: composer.json] | Eloquent, queue, events, validation | The app |
| filament/filament | v5.10.1 [VERIFIED: `composer show filament/filament`] | Relation manager, actions, widgets | Existing admin panel |
| pestphp/pest | ^5.3 [VERIFIED: composer.json] | Tests, arch tests | Project test runner |
| pestphp/pest-plugin-browser | ^5.1 [VERIFIED: composer.json] | axe check on the leads section | Room standard A11Y-01 (Playwright already in `node_modules`) |
| PHP | 8.5.12 [VERIFIED: `php -v`] | Enum case named `Case` is legal (tested in this session) | |

### Supporting
None new. Use existing `App\Icm\PlainWords`, `App\Icm\HiddenCharacters`, `App\Records\MarkdownText`, `App\Rooms\RoomSection`.

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Eloquent `created` hook that dispatches a gated job | Edit `RecordInbound` to raise an event | Forbidden: `RecordInbound` is shared inbound code, editing it is its own decision (CONTEXT carried facts). |
| Relation manager for the contact-screen list | `RepeatableEntry` with per-row actions in `ContactInfolist` | Infolists have no per-row actions; relation manager gives table, actions and keyboard support from Filament. Needs `isReadOnly()` override (below). |
| `closed_at IS NULL` means open | A `status` column like `conversations` | Status plus `closed_at` can drift; pipeline stages are explicitly undecided. Either works; recommend no status column. |

**Installation:** none.

## Package Legitimacy Audit

This phase installs no external package. Nothing to audit. If a plan finds it wants one, it stops and asks Ian first.

**Packages removed due to [SLOP] verdict:** none
**Packages flagged as suspicious [SUS]:** none

## Blast-radius spike (the evidence behind the test plan)

Method [VERIFIED: ran 2026-10-29]: `git archive HEAD` plus `vendor` and `node_modules` copied to the session scratchpad (`.../scratchpad/spike`); the real repo tree was never edited. Baseline: 2278 passed, 169 s. Prototype changes: example `rooms/leads/CONTEXT.md` (owns none, reads lead sources and offers), `project_word: Job` in the example profile, `exampleFolderFileCount()` 25 to 26, a minimal `LeadsServiceProvider` claiming `NewLeads`, a `projects` table with the partial unique index, `ActivityType` project cases, a morph nickname, a `## Jobs` card section, and a job opening a project plus a timeline entry for a contact that has an inbound activity and no project.

| Variant | Result |
|---------|--------|
| `Contact::created` dispatches the job (`->afterCommit()`) with no room check at dispatch | 12 failures: the 3 below plus 9 tests that use `Queue::fake()` and count pushed jobs: `DispatchInboundTest` (2, one an error because `dispatchTestFailingQueue()` makes any push throw), `SweepUndispatchedTest` (6), `RenderCustomerCardJobTest` (2). Not on the D-22 list. |
| Same, but dispatch only when `hasRoom('leads')`, with the dispatch in try/catch and `report()` | 3 failures, all on the D-22 list (full suite, 2278 tests, 165 s): `tests/Feature/Records/CardInjectionTest.php:90` ("quotes a note that echoes what the customer typed": `headingLines($card)->toHaveCount(4)` became 6 because the card gained `## Jobs` and a project timeline heading), and `tests/Feature/Rooms/FrontDeskBootTest.php:90` (both datasets, `expect($registered->claimedWidgets)->not->toContain(NewLeads::class);`). |

What did NOT change, though CONTEXT listed them as card-content tests that might: `CustomerCardTest`, `IntakeAnswersTest.php:126,210`, `SummaryFilesTest`, `RecordsRebuildTest`, `FrontDeskInvariantTest`, `FrontDeskPromptTest`, the Seeder test, `NeverLeftOutTest`, `OrderIndependenceTest`. A plan must still re-run the suite, because the real card wording differs from the prototype's, but the expected blast radius is those three, not more.

Not run: PostgreSQL (no local Postgres on this machine: `pg_isready` and `psql` not found). The prototype's migration uses the same partial-index form as the existing conversations migration, which CI already runs on Postgres 17. CI (`.github/workflows/tests.yml`, matrix sqlite and pgsql) is the Postgres proof; per the STATE blocker, Ian pushes and confirms.

## Architecture Patterns

### System Architecture Diagram

```
Inbound (text webhook | web form POST)
        |
        v
RecordInbound::handle()  -- shared path, NOT edited
  DB::transaction {
     matchOrCreateContact()  --(new)--> Contact::create ---> [Eloquent "created" event]
     openConversationFor(), Message::create, Activity::create ...       |
  }                                                                      |  hook (registered in LeadsServiceProvider::boot)
  commit                                                                 |  runs inside the transaction, so:
        |                                                                |    1. if !ActiveConfig::hasRoom('leads') -> stop (D-03, keeps Queue::fake() tests green)
        |                                                                |    2. try { OpenProjectForNewLead::dispatch($id)->afterCommit() } catch -> report()
        v                                                                v
   (dispatch ProcessInbound, CustomerRecordChanged ...)       [queue] OpenProjectForNewLead (after the commit)
                                                                         |
                                                       handle(): hasRoom('leads')? no -> return      (D-03, re-check at run time)
                                                       contact missing / no inbound activity / any project exists -> return  (D-01, D-02)
                                                       DB::transaction { lock contact row;  savepoint { OpenProject::handle(contact, Lead) } }
                                                         UniqueConstraintViolationException -> return (race lost, never two)
                                                                         |
                                                                         v
                                                  OpenProject action: insert projects row (+ conversation_id = contact's first conversation)
                                                                      + Activity(ProjectOpened) ; after commit CustomerRecordChanged
                                                                         |
                                                                         v
                                          CustomerCard::render()  ## <Jobs> section + timeline  --> records/ card, and FrontDeskPrompt (loads the same card fresh)

Owner (Filament contact screen)  --New / Close / Reopen / Rename-->  OpenProject / CloseProject / ReopenProject / RenameProject  (same actions, opened_by = user)
Dashboard                        --LeadsSection (room live only)---> reads projects, lead-sources.md names (ActiveConfig), contact URLs
Sync (icm:sync)                  --Validator business rules-------> project_word in ProjectWord list, else refuse, previous version stays live
```

### Recommended Project Structure
```
app/
  Enums/ProjectWord.php            # six cases, singular()/plural(), tryFromProfile()
  Enums/ProjectActor.php           # Lead, User  (who opened/closed)
  Models/Project.php               # #[Fillable([...])] like Contact; contact(), conversation(), openedBy(), closedBy()
  Actions/OpenProject.php          # the four writers (D-11)
  Actions/CloseProject.php
  Actions/ReopenProject.php
  Actions/RenameProject.php
  Rooms/Leads/LeadsServiceProvider.php   # room(); boot() registers the Contact hook (calls parent::boot())
  Rooms/Leads/LeadsSection.php           # extends RoomSection, own view
  Rooms/Leads/OpenProjectForNewLead.php  # queued job (or app/Jobs/, planner's call; room code goes in app/Rooms/<Room>/ per CLAUDE.md map)
  Filament/Resources/Contacts/RelationManagers/ProjectsRelationManager.php
database/migrations/2026_10_29_0000NN_create_projects_table.php
database/factories/ProjectFactory.php
resources/views/filament/rooms/leads-section.blade.php
examples/example-hvac/rooms/leads/CONTEXT.md
tests/Feature/Projects/...         # new files only (do not edit existing tests beyond D-22)
```

### Pattern 1: The four actions copy `CloseConversation` / `ReopenConversation`
**What:** `DB::transaction`, re-read the row under `lockForUpdate()`, refuse a duplicate (second tab) without a second timeline note, write one `Activity`, dispatch `CustomerRecordChanged` after the transaction. Reopen wraps the write in try/catch for the unique violation where relevant.
**Source (read this session):** `app/Actions/CloseConversation.php` and `app/Actions/ReopenConversation.php`.
```php
// shape only; mirrors CloseConversation::handle()
$changed = DB::transaction(function () use ($project, $user): bool {
    Project::query()->lockForUpdate()->find($project->id);
    $project->refresh();
    if ($project->closed_at !== null) { return false; }          // idempotent for a second tab
    $project->update(['closed_at' => now(), 'closed_by' => ProjectActor::User, 'closed_by_user_id' => $user?->id]);
    $project->contact->activities()->create([
        'type' => ActivityType::ProjectClosed,
        'summary' => 'closed by the owner',                       // no owner-typed text in a summary
        'subject_type' => 'project', 'subject_id' => $project->id,
        'occurred_at' => now(),
    ]);
    return true;
});
if ($changed) { CustomerRecordChanged::dispatch($project->contact_id); }
```
Rename: no-op (no timeline entry) when the normalized name equals the stored one. Open: a single `OpenProject::handle(Contact $contact, ProjectActor $by, ?User $user = null): Project` used by both the leads job and the "New <word>" button; the conversation reference is `$contact->openConversation` for a hand open and the contact's oldest conversation for an automatic open (D-06).

### Pattern 2: "Never two" is a partial unique index, and the insert runs in a savepoint
`conversations_one_open_per_contact` is the in-repo precedent:
```php
// database/migrations/2026_10_11_131451_create_conversations_table.php:27
DB::statement("create unique index conversations_one_open_per_contact on conversations (contact_id) where status = 'open'");
```
For projects use `create unique index projects_one_lead_open_per_contact on projects (contact_id) where opened_by = 'lead'`: at most one automatic project per contact ever, which is exactly "a contact is new once". Hand-opened projects are not constrained (D-05). The job still checks "contact has any project" first (D-02 says do nothing if one exists) and takes a `lockForUpdate()` on the contact row (a no-op on SQLite, real on Postgres) so the check and the insert are serialized with the owner's New button.
**Pitfall that bites on Postgres only:** a unique violation aborts the surrounding transaction unless the insert is inside a nested `DB::transaction` (a savepoint). `RecordInbound::openConversationFor()` does exactly this (`return DB::transaction(fn () => ...create...)` with `catch (UniqueConstraintViolationException)`, at `app/Actions/RecordInbound.php:378`). Copy it, and treat the exception as "already opened, do nothing".

### Pattern 3: Hook beside `RecordInbound` without editing it
Register in `LeadsServiceProvider::boot()` (calling `parent::boot()`, which does the morph-map merge) a `Contact::created(...)` listener. It must:
1. return at once unless `app(ActiveConfig::class)->hasRoom('leads')` (verified safe: `hasRoom()` never throws and returns false with no live version, `app/Icm/ActiveConfig.php:69-76`);
2. dispatch `OpenProjectForNewLead::dispatch($contact->id)->afterCommit()` inside `try { } catch (Throwable $e) { report($e); }`.
`->afterCommit()` on a dispatched job is already used in this repo (`RespondToLifeSafety::dispatch($message->id, $hazard)->afterCommit();`, `app/Actions/RecordInbound.php:121`). If the inbound transaction rolls back (the unique-violation retry in `RecordInbound::handle()`), Laravel discards the pending after-commit job with it. The try/catch is what keeps `DispatchInboundTest::it_queues_a_text_whose_push_failed_when_the_provider_retries_it` green and what delivers D-02's "a failed open never rolls back or delays the inbound".
Do not register the hook in `RoomServiceProvider::register()`: its docblock forbids touching `ActiveConfig` there; the hook only reads it when an event fires, which is fine from `boot()`.

The job decides "an inbound created this contact" by `contact->activities()->whereIn('type', [text_received, form_received])->exists()`. A factory-made contact has no such activity at dispatch time, so factories never open projects even when the example folder is active. The job also: skips when `hasRoom('leads')` is false at run time (D-03), skips when the contact already has any project (D-02), and uses `public int $tries` plus a `backoff()` like `RenderCustomerCard`.

### Pattern 4: Validator hook for the word
`Validator::fileErrors()` already dispatches per type (`'sending_rules' => $this->sendingRulesErrors($file)` at `app/Icm/Validator.php:152`); add `'business' => $this->businessErrors($file)`. Do NOT add `project_word` to the `'business'` rules array at `Validator.php:172`: `Rule::in` is case-sensitive, and the stock `Required` message ("... is missing, and it is required") does not list the choices D-12 demands. Use `PlainWords::render('In', $labels, 'project_word', $value)` for an unlisted value (the existing sending-rules code does this at `Validator.php:456`) and one custom sentence for a missing value that lists the choices. Both go through `$this->error($file->path, 'project_word', ...)`, so the line reads `business/profile.md: project_word ...` (the file is the prefix, as `ValidationReport::lines()` builds it).

### Pattern 5: The leads room is the marketing room plus a claimed widget and a section
`MarketingServiceProvider` (no `register()`, only `room()`) is the template. The leads room:
```php
new Room(
    key: 'leads', label: 'leads',
    sections: [LeadsSection::class],
    claimedWidgets: [NewLeads::class],
    // no handles, fileTypes, slot, tables, timelineEntries, morphMap, neverLeftOutBecause
);
```
Contract (`examples/example-hvac/rooms/leads/CONTEXT.md`), front matter keys per the `room` rules at `Validator.php`: `owns: none`, `reads: [business/lead-sources.md, business/offers/*.md]`, `writes_records: none`, `connector: none`. Its body states: reads the lead sources to name each automatic project's source; reads the offers for later lead follow-ups and uses none today; owns no file; writes nothing to `records/`; no outside system; lead intake (the web form) is shared and stays when the room is off (D-18, D-19).
SPEC: `leads` is already an allowed owner name in `SpecTablesTest` ("names only owners from the floor plan"), and a room that owns nothing compares `[]` with `SpecTables::patternsOwnedBy(..., 'leads')` exactly as `TeamBootTest` does.

### Pattern 6: The leads section needs its own view (links and dates)
`RoomSection`'s shared view prints each line as `<p>{{ $line }}</p>` (`resources/views/filament/rooms/section.blade.php`), so it cannot carry a keyboard-reachable link or a date column. Override `$view` with `filament.rooms.leads-section`, keep the same wrapper (`<x-filament-widgets::widget><div data-room-section="leads"><x-filament::section :heading="$heading">`) so `RoomSectionAxe::report($page, 'leads')` finds it, and keep the D-32 failure behaviour by building rows inside the same try/catch that `RoomSection::getViewData()` uses (extend `getViewData()` and put the row query in a `try`, falling back to `RoomSection::FAILED_LINE`). Rows: label ("<word> N" or the name), contact (plain `<a href>` to `ContactResource::getUrl('view', ...)`, text is the contact's name or phone or "(no name)"), opened as an absolute date and time in `ActiveConfig::displayTimezone()` (`Y-m-d H:i`, like the card), and "Source: <name from lead-sources.md>" for `opened_by = lead` or "Opened by <user name>" for `user` (D-16; unknown key falls back to the key, as the card's `leadSource()` does). Empty state: one plain "No open <plural>." line (never the D-21 empty line, because the room owns nothing, `RoomSection::emptyLine()` returns null for `owns: []`). Eager-load contact, avoid N+1 (laravel-best-practices).

### Pattern 7: Contact screen = relation manager with `isReadOnly()` overridden
`ViewContact` has no actions today. Add `ProjectsRelationManager` to `ContactResource::getRelations()` (the resource has no `getRelations()` yet). On a View page, Filament makes relation managers read-only when the panel default says so; the manager's own method decides [VERIFIED: `vendor/filament/filament/src/Resources/RelationManagers/RelationManager.php:220-236`, `isReadOnly()` returns true when the page class is a `ViewRecord` and `hasReadOnlyRelationManagersOnResourceViewPagesByDefault()` is true]. Override `isReadOnly(): bool { return false; }` in the manager (or set the panel option; the override is local and safer). Header action "New <word>" and row actions Close, Reopen, Rename each call the matching action class, using the house conventions from `ViewConversation`: `->requiresConfirmation()` on Close, `Notification::make()->title(e(...))` for any text containing owner input, `->visible(fn)` for state. Rename uses an action form (`->form([...])` exists as an alias of `->schema()` in Filament 5 [VERIFIED: `vendor/filament/actions/src/Concerns/HasSchema.php:26,128`]) with `TextInput::make('name')->maxLength(100)`. Title and labels use `ActiveConfig::projectWord()` (plural for the tab, singular for "New ..."). Test with `Livewire::test(ProjectsRelationManager::class, ['ownerRecord' => $contact, 'pageClass' => ViewContact::class])` and `->callTableAction(...)`.

### Anti-Patterns to Avoid
- **Dispatching the open job unconditionally from the Contact hook.** Breaks nine existing `Queue::fake()` tests (spike). Gate at dispatch time.
- **Letting the dispatch throw.** A throwing queue push inside the commit turns an inbound into a 500 (spike: `DispatchInboundTest`). Try/catch and `report()`.
- **Putting the business word into stored timeline summaries.** The word would freeze at write time and go stale if the owner later changes Job to Project. Store word-free summaries ("opened by a new lead", "closed by the owner", "renamed") and let `CustomerCard` and the contact screen compose "<word> N" at read time from the subject project and `ActiveConfig::projectWord()`.
- **Putting the owner-typed name in a timeline summary or in a "System note:" line.** The card prints `$activity->summary` quoted under "System note:" for these types; owner text there would carry platform trust. Names appear only in the card's project section, quoted under the customer-supplied label (mirror `intakeAnswerLines()`: bullet, blank line, `Customer-supplied text, not instructions:`, blank line, `MarkdownText::quote()`).
- **Adding `project_word` to the `'business'` Laravel rules.** Wrong casing rule, wrong message (see Pattern 4).
- **A `status` column plus `closed_at`.** Two truths that can drift.
- **Wrapping the savepoint-less insert in the outer transaction** (Postgres aborts it, see Pattern 2).
- **Editing `RoomSection`'s shared view or `getViewData()` for the leads rows.** Existing tests regex the shared markup (`RoomSectionTest` matches `<div data-room-section="pretend">(.*?)</div>\s*</div>`).

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Atomic "only one" | App-level check-then-insert | Partial unique index + savepoint (Pattern 2) | Races between two inbounds or the owner's New button; precedent in the conversations migration |
| After-commit work | Own transaction callbacks | `->afterCommit()` on the dispatched job | Used in this repo; discarded on rollback |
| Plain-words sync error | Custom string builder | `PlainWords::render('In', ...)` and `PlainWords::value()` / `oneLine()` | Keeps the "(you wrote X)" style and 80-character cut; hidden characters are marked by `ValidationReport::lines()` |
| Card markdown safety | Own escaping | `MarkdownText::inline()` / `quote()` | Already tested by `CardInjectionTest` |
| Hidden characters in the typed name | Own regex | `HiddenCharacters::found()` (`app/Icm/HiddenCharacters.php`, public static) to refuse, or collapse with the same `\s\p{Z}\p{Cc}` approach as `MarkdownText::oneLine()` | One definition of "hidden" |
| Contact screen list with actions | Hand-built Livewire component | Filament relation manager | Keyboard, focus and table semantics come stock |
| Open/close idempotency | New pattern | Copy `CloseConversation` (row lock, refresh, no-op on repeat) | Second-tab safe |

## Runtime State Inventory

Not a rename or migration phase, but two pieces of runtime state matter.

| Category | Items Found | Action Required |
|----------|-------------|-----------------|
| Stored data | `icm_versions.compiled` for any version already live in a dev or demo database has no `project_word`. | Make `ActiveConfig::projectWord()` never throw and fall back to `ProjectWord::Project` when no version is live or the field is absent (mirrors `displayTimezone()`); a re-sync stores the real word. No data migration. Existing contacts get no project (D-03, no backfill). |
| Live service config | None: no external service stores the word. | None |
| OS-registered state | None | None |
| Secrets/env vars | None; no new env key (queue connection already `QUEUE_CONNECTION` in `.env.example`/`phpunit.xml`). | None |
| Build artifacts | None | None |

## Common Pitfalls

### Pitfall 1: The required profile field and the example folder must land together
**What goes wrong:** If the Validator starts requiring `project_word` before `examples/example-hvac/business/profile.md` has it, every test that syncs the example folder (nearly all Feature tests via `activateExampleFolder()` and `tests/Pest.php`'s `beforeEach`) goes red.
**How to avoid:** One task adds the `project_word: Job` line and the check; write the failing test first (CLAUDE.md: tests first for anything touching the sync), commit the failing test, then the code and the example line together.

### Pitfall 2: Adding the leads folder moves the file count
`exampleFolderFileCount()` is `return 25;` at `tests/Pest.php:295` and is the only count (the spike changed it to 26 and no other test needed to change). Change it in the same task that adds `examples/example-hvac/rooms/leads/CONTEXT.md`.

### Pitfall 3: The example folder makes the leads room live in almost every test
Any test that calls `activateExampleFolder()` and then sends a text or form from a new number now opens a project and writes a timeline entry. The spike shows only `CardInjectionTest.php:90` is sensitive to that, but a plan that changes card wording must re-run `tests/Feature/Records`, `tests/Feature/FrontDesk`, `tests/Feature/Filament` and `tests/Feature/Inbound`.

### Pitfall 4: `Queue::fake()` tests and the dispatch gate
Tests with a bare `Queue::fake()` (`DispatchInboundTest`, `SweepUndispatchedTest`, `RenderCustomerCardJobTest`) pass only because nothing is pushed when the leads room is not live. Keep the dispatch-time `hasRoom('leads')` gate. `tests/Pest.php:78` holds only `ProcessInbound` back (`Queue::fake([ProcessInbound::class])`), so the open job runs on the sync queue in the normal Feature test, immediately after the commit.

### Pitfall 5: A new `ActivityType` case needs a `CustomerCard` arm
`CustomerCard::section()` uses an exhaustive `match ($activity->type)` (`app/Records/CustomerCard.php:160-175`, last arm `ActivityType::RoomEntry => $this->roomEntryParts($activity)`). A missing arm is an `UnhandledMatchError` on every card render for a contact with such an activity. `ContactInfolist::activityBadge()` prints `$type->value` for non-room types, so it needs no arm but shows raw values (placeholder wording until Phase 11).

### Pitfall 6: The morph map is enforced
`Relation::enforceMorphMap([...])` in `AppServiceProvider::boot()` lists `'owner_alert' => OwnerAlert::class,` (line 86). A `project` subject on an `Activity` needs `'project' => Project::class` there, because `CustomerCard` calls `->with('subject')`. This is a platform model, so it goes in the app's map, not a room's `morphMap` (06 D-05 spirit; rooms add nicknames only for room models).

### Pitfall 7: Enum case named `Case`
`ProjectWord::Case` is legal PHP (tested: `enum W: string { case Case = "case"; }` runs on 8.5.12). "Work order" needs a multi-word value: match input after trim, collapse of internal whitespace and `mb_strtolower`, so `work  ORDER` is accepted.

### Pitfall 8: Postgres and the unique violation
See Pattern 2. A test that proves "never two" must run on Postgres in CI; locally it only proves the SQLite path. Simulate the race the way Phase 6 does (06 D-21: no two-connection race test, simulated races on both databases): create the lead project first, then run the job and assert one row and no exception.

### Pitfall 9: A keyword text from an unknown number creates a contact
`RecordInbound::record()` creates the contact before it knows the text is STOP, so a STOP from a never-seen number is a "new contact" and, by D-01, would open a project. See Open Question 1.

### Pitfall 10: Filament relation manager is read-only on a view page by default
Without the `isReadOnly()` override the New/Close/Reopen/Rename actions silently do not render.

## Code Examples

### The word enum (shape)
```php
// app/Enums/ProjectWord.php - the same six words, in this order, appear in docs/SPEC.md
enum ProjectWord: string
{
    case Job = 'job';
    case Project = 'project';
    case Matter = 'matter';
    case Case = 'case';
    case WorkOrder = 'work order';
    case Engagement = 'engagement';

    public function singular(): string { return ucfirst($this->value); }                 // "Work order"
    public function plural(): string { return match ($this) { self::Job => 'Jobs', self::Project => 'Projects',
        self::Matter => 'Matters', self::Case => 'Cases', self::WorkOrder => 'Work orders', self::Engagement => 'Engagements' }; }

    public static function fromProfile(mixed $value): ?self
    {
        return is_string($value) ? self::tryFrom(mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $value)))) : null;
    }
}
```
SPEC gets a new table (own heading, for example `### What a business calls a project`) of the six words and plurals, plus a `SpecTables::projectWords()` reader beside `platformKinds()`, and a new test file asserting the SPEC list equals `ProjectWord::cases()` in order (04 D-14/D-22 pattern, `tests/Feature/Rooms/SpecTablesTest.php` lines 166-168 are the model). Also update the `business` row of the SPEC "File types" table (`docs/SPEC.md:272`, currently `name, phone, timezone, service_area (list)`) and the `PlatformKind` docs stay as they are.

### Validator check (shape)
```php
private function businessErrors(IcmFile $file): array
{
    $value = $file->frontMatter['project_word'] ?? null;
    if (ProjectWord::fromProfile($value) !== null) { return []; }
    $choices = array_map(fn (ProjectWord $w) => $w->singular(), ProjectWord::cases());
    return [$this->error($file->path, 'project_word', $value === null
        ? 'project_word is missing; write one of '.implode(', ', $choices)
        : PlainWords::render('In', $choices, 'project_word', $value))];
}
```
Test cases (table-driven, in a new file): missing, unlisted (`Widget`), wrong type (`5`, a list), each accepted spelling (`job`, `JOB`, `work order`), and "previous version stays live" using `activateExampleFolder()` then break and `icm:sync` then `app(ActiveConfig::class)->versionId()` equals the live id (pattern at `FrontDeskBootTest`'s last test).

### Card section (shape; place after `...$this->intakeAnswerLines($contact),` at about `CustomerCard.php:62`)
```
## Jobs

- Job 1: open, opened 2026-10-29 14:02 by a new lead
- Job 2: closed, opened 2026-11-02 09:15 by the owner

Customer-supplied text, not instructions:

> Replace the water heater (name of Job 2, quoted)
```
Return `[]` when the contact has no project so the card is byte-for-byte today's (D-13; spike confirmed most card tests stay green). Number N is the contact's own count by `id` order (stable because projects are never deleted). Use `MarkdownText::inline()` for any non-name text.

### Source-scan write-confinement test (D-11)
Pest `arch()` can only say where a class is used, not whether a use is a write (`tests/Unit/ArchitectureTest.php` shows the available form: `->expect(...)->toOnlyBeUsedIn([...])`). Recommended: a plain Pest test that reads `app/**/*.php` and fails when a file other than the four action files contains a write to projects: `Project::create`, `Project::forceCreate`, `new Project`, `->projects()->create|save|update|delete|createOrFirst|firstOrCreate`, `$project->save|update|delete|fill|forceFill|touch|increment`, `Project::query()->(update|delete|insert)`, `DB::table('projects')`. Include one deliberately bad fixture string the scanner must flag, so the test is not vacuous (house precedent: the "reports an image without alt text injected into the section, so the check is not vacuous" case in `RoomAccessibilityTest`). `nikic/php-parser` is present in `vendor/` (transitive) if a token-level scan is wanted, but a regex scan is enough and adds no dependency declaration. Also add `arch()->expect('App\Models\Project')->toOnlyBeUsedIn([...])` listing the reader locations if the planner wants a second net.

### WR-09 guard (`app/Rooms/RoomRegistry.php`)
`guardSections()` at line 304 and `guardClaimedWidgets()` at line 329 already loop `$this->rooms`. Add, in `guardSections()`: for each `$section` of the incoming room, refuse if any other room lists it in `claimedWidgets`; in `guardClaimedWidgets()`: for each claimed widget, refuse if any other room lists it in `sections`. Check both directions so registration order does not matter. Message style: `Room [leads] claims widget [X], which room [y] adds in its sections. Add it in one room, not both.` Put the new tests in a new file such as `tests/Unit/Rooms/RoomWidgetOwnershipTest.php`, not in `RoomClaimedWidgetsTest.php`, to leave existing tests untouched.

### Queue/ActiveConfig reminder
`ActiveConfig` is bound `scoped` (`AppServiceProvider::register()`), so a queue worker builds a fresh one per job and the job's `hasRoom()` sees a sync made after dispatch (D-03 relies on this).

## State of the Art

| Old Approach | Current Approach | Impact |
|--------------|------------------|--------|
| `$fillable` property | `#[Fillable([...])]` attribute on models | House style (`app/Models/Contact.php:13-22`); use it on `Project`. |
| Filament actions `->form()` | `->schema()` (with `->form()` kept as alias) | Either works in 5.10.1 [VERIFIED: vendor]. |
| Relation managers editable on view pages | Read-only on view pages by default | Override `isReadOnly()` (Pattern 7). |

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|---------------|
| A1 | `ActiveConfig::projectWord()` should fall back to "Project" when no version is live or the field is absent in an older compiled version | Runtime State Inventory | A card render before first sync or in a dev DB with an older version would otherwise throw `NoActiveVersion`. Low risk; Ian may prefer a different default word. |
| A2 | The "New <word>" action is not gated by the leads room (it is a platform action) | Pattern 7 | If Ian wants it hidden when the leads room is off, one `visible()` condition changes. D-15 only says the section hides when the room is off and the contact screen still shows every project. |
| A3 | Recommended schema: `closed_at` null means open; `opened_by` and `closed_by` string enums (`lead`, `user`) plus nullable `opened_by_user_id` and `closed_by_user_id` (nullOnDelete); name `string(100)` nullable | Project table | Pure design; discretion per D-08. Changing later is a Phase 8 migration. |
| A4 | Timeline summaries carry no business word and no owner text; the card composes "<word> N" at read time | Anti-Patterns | If Ian wants frozen historical wording, summaries can store it; costs staleness when the word changes. |
| A5 | A regex source scan is an acceptable "architecture test" for D-11 | Code Examples | If Ian expects Pest `arch()` specifically, only a `toOnlyBeUsedIn` on the model is expressible, which cannot tell reads from writes. |
| A6 | The leads section deserves its own Blade view instead of changing the shared section view | Pattern 6 | Changing the shared view risks regexes in `RoomSectionTest`; own view is the safe path. |
| A7 | Open-job retry: `tries` 5 with a backoff list like `RenderCustomerCard::backoff()`; no sweep (Deferred) | Pattern 3 | Matches the deferral; a lost dispatch is covered by the owner's New action. |
| A8 | Whether to skip the automatic open for a contact who is already opted out when the job runs | Open Question 1 | A STOP from an unknown number would otherwise open a project. |

## Open Questions

1. **Should a STOP (or START/HELP) from a never-seen number open a project?**
   - What we know: `RecordInbound::record()` creates the contact, then `keywordIn()` classifies the text (`app/Actions/RecordInbound.php:~100-130`). D-01 literally says a text from a never-seen number opens a project. The spike did not exercise this.
   - What's unclear: whether Ian wants a project for someone whose first and only message was STOP.
   - Recommendation: take it to Ian before planning the job's guard. Cheapest compatible behaviour: skip when `$contact->isOptedOut()` at job time (one line, no `RecordInbound` change). Without a ruling, build D-01 literally and record the case in the walk-through.
2. **Where does "card test that now shows a project" end?** CONTEXT names `CardInjectionTest.php:36`; the real changed test is `:90`. The plans should name `:90` and record that `:36` is unchanged. Re-run the greps in Sources at plan time because the real card wording may touch other `toContain`/`toHaveCount` assertions.
3. **Number format for "<word> N"** (e.g. "Job 2"): the discussion reading is the contact's own count in opening order. Confirm there is no objection to showing the same label on the dashboard section for different contacts ("Job 1" appears many times; the contact link disambiguates). Recommendation: keep it, and show the contact name in the same row.
4. **Name length bound:** recommend 100 characters, one line, hidden characters refused, whitespace collapsed (A3).

## Environment Availability

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| PHP | everything | yes | 8.5.12 | none |
| Composer packages / `vendor/` | everything | yes | Laravel 13.x, Filament 5.10.1, Pest 5.x | none |
| Node + Playwright Chromium | axe check on the leads section | yes (full suite ran, browser tests included, in the spike with copied `node_modules`) | pest-plugin-browser ^5.1 | CI installs via `npx playwright install chromium` |
| SQLite | default test DB | yes | `DB_CONNECTION=sqlite`, `:memory:` (`phpunit.xml`) | none |
| PostgreSQL | second CI leg | no (`pg_isready`, `psql` not found) | n/a | CI matrix `[sqlite, pgsql]` with `postgres:17` service (`.github/workflows/tests.yml`); Ian confirms after pushing |
| Queue driver | job tests | yes | `sync` in tests | none |

**Missing dependencies with no fallback:** none.
**Missing dependencies with fallback:** local Postgres; CI covers it. Plans must say which tests depend on Postgres behaviour (the savepoint and index tests) so Ian reads the CI leg.

## Validation Architecture

`workflow.nyquist_validation` is not set to false in `.planning/config.json` (the file has only `workflow.use_worktrees`, `tdd_mode: true`, `test_command`, and git branching), so this section applies. `tdd_mode` is true: each plan writes the failing test first.

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Pest 5.x on PHPUnit, `pest-plugin-browser` for axe |
| Config file | `phpunit.xml`, `tests/Pest.php` (Feature tests extend `TestCase`, `RefreshDatabase`, a fresh copy of `examples/example-hvac` per test) |
| Quick run command | `php artisan test --compact tests/Feature/Projects` (narrowest path per task; CLAUDE.md: run the narrowest set, ask Ian to run the full suite) |
| Full suite command | `php artisan test --compact` (about 165 s on SQLite, 2278 tests at baseline) |
| Format | `vendor/bin/pint --dirty --format agent` before finalizing PHP changes |

### Phase Requirements to Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| PROJ-02 / criterion 1 | Sync accepts each listed word in any case; refuses missing and unlisted in plain words naming `business/profile.md` and `project_word`, previous version stays live | Feature (table-driven) | `php artisan test --compact tests/Feature/Projects/ProjectWordTest.php` | Wave 0 (new) |
| PROJ-02 | SPEC word table equals `ProjectWord::cases()`; `SpecTables::projectWords()` reads only rows under its heading | Feature | `php artisan test --compact tests/Feature/Projects/ProjectWordSpecTest.php` | Wave 0 (new) |
| PROJ-01 | Project belongs to contact and optionally a conversation; no appointment column; open/closed from `closed_at`; "<word> N" numbering | Feature | `php artisan test --compact tests/Feature/Projects/ProjectModelTest.php` | Wave 0 (new) |
| D-11 | Four actions: who is recorded, timeline entry each, second-tab repeat adds no second entry, rename no-op, contact status never changes | Feature | `php artisan test --compact tests/Feature/Projects/ProjectActionsTest.php` | Wave 0 (new) |
| D-11 | Nothing else writes the table (scanner flags a bad fixture, passes the tree) | Unit | `php artisan test --compact tests/Unit/ProjectWritesTest.php` | Wave 0 (new) |
| PROJ-02 / criterion 2 | Card section above timeline with the business word; omitted when no project; name quoted and neutralized | Feature | `php artisan test --compact tests/Feature/Records/CardInjectionTest.php` (gains the new case) and a new `tests/Feature/Projects/ProjectCardTest.php` | CardInjectionTest exists; new file Wave 0 |
| criterion 2 | A business that sets "Matter" sees "Matter" on card, contact screen and dashboard | Feature | `ProjectCardTest`, `ProjectsRelationManagerTest`, `LeadsBootTest` | Wave 0 (new) |
| LEAD-05 / D-01 | New number text and non-matching form open one project; known number, matching form and factory contact open none | Feature | `php artisan test --compact tests/Feature/Projects/OpenOnNewLeadTest.php` | Wave 0 (new) |
| D-02 | Retry/duplicate never two (pre-existing project, savepoint path); failed dispatch does not break the inbound; the project links to the lead's conversation | Feature | same file | Wave 0 (new) |
| D-03 | Room off at arrival: no job pushed; room off at run time: no project; room switched on later: no backfill | Feature | same file | Wave 0 (new) |
| ROOM-07 / criterion 5 | Boots empty and with example folder on both databases; contract matches SPEC rows; reads exactly lead sources and offers; `writes_records` empty; claims `NewLeads`; section axe-clean | Feature + browser | `php artisan test --compact tests/Feature/Rooms/LeadsBootTest.php` | Wave 0 (new) |
| D-15 | WR-09: a room cannot claim a widget another room adds, in either registration order | Unit | `php artisan test --compact tests/Unit/Rooms/RoomWidgetOwnershipTest.php` | Wave 0 (new) |
| D-14 | New/Close/Reopen/Rename work from the contact screen; read-only override in place | Feature (Livewire) | `php artisan test --compact tests/Feature/Filament/ProjectsRelationManagerTest.php` | Wave 0 (new) |
| criterion 4 | Front desk unchanged: existing `tests/Feature/FrontDesk` and `FrontDeskInvariantTest` stay green unchanged | regression | `php artisan test --compact tests/Feature/FrontDesk` | exists |

### Sampling Rate
- **Per task commit:** the new test file plus the neighbouring existing directory (`tests/Feature/Records`, `tests/Feature/Rooms`, `tests/Feature/Icm`, `tests/Feature/Inbound` as relevant).
- **Per wave merge:** `php artisan test --compact tests/Feature tests/Unit` for the touched areas; ask Ian to run the full suite (CLAUDE.md).
- **Phase gate:** full suite green on SQLite, then Ian pushes and confirms the CI pgsql leg before verification.

### Wave 0 Gaps
- [ ] `tests/Rooms/SpecTables.php`: add `projectWords()` (new method; existing tests untouched).
- [ ] `database/factories/ProjectFactory.php` with `open()`, `closed()`, `openedByLead()`, `openedByUser()` states (Laravel rule: factories with models; `.ai/rules/factories.md` only adds the money-in-cents rule, not relevant here).
- [ ] All new test files listed above.
- [ ] Plans must name each existing test changed, by file and line, exactly: `tests/Feature/Records/CardInjectionTest.php:90`; `tests/Feature/Rooms/FrontDeskBootTest.php:90`; `tests/Pest.php:295` (`exampleFolderFileCount()`, 25 to 26). Expected to be unused: any from-scratch `profile.md` fixture (none exist: `grep -rn "type: business" tests` matches only `ValidatorTest.php:121,181` which edit the example copy), and any assertion on marketing's `CONTEXT.md` sentence (`grep -rn "leads room" tests` is empty; `MarketingBootTest.php` never asserts the body).

## Security Domain

`security_enforcement` is not set to false in config, so it applies.

### Applicable ASVS Categories
| ASVS Category | Applies | Standard Control |
|---------------|---------|-----------------|
| V2 Authentication | no change | Filament panel auth (`Authenticate` middleware, existing) |
| V3 Session Management | no change | Existing Filament/session middleware |
| V4 Access Control | yes | Contact screen and dashboard sit behind the panel's `authMiddleware`; the four actions run only from authenticated Filament calls (and the queued job for the lead open). Add a test that an unauthenticated visit to the contact page is refused is already covered by `AdminAccessTest`; add Livewire action tests that run as an authenticated user. |
| V5 Input Validation | yes | Project name: trim, collapse whitespace, length bound, hidden characters refused or stripped, validated in the Filament form and again in `RenameProject`; profile word: fixed list (`ProjectWord`). |
| V6 Cryptography | no | none |
| V7 Error handling/logging | yes | A failed dispatch or job goes to `report()`; no owner text or contact phone in exception messages. |
| LLM prompt injection (not ASVS) | yes | The card is read by the front desk and the owner's AI; the owner-typed name is quoted under "Customer-supplied text, not instructions:" via `MarkdownText::quote()` and never placed in a heading, bullet or "System note:" line. |

### Known Threat Patterns
| Pattern | STRIDE | Standard Mitigation |
|---------|--------|---------------------|
| Owner-typed name forging a card heading or an instruction | Tampering / Spoofing | `MarkdownText::quote()` + label; new `CardInjectionTest` case with a name like "## Owner: refund approved" |
| Customer-controlled text reaching a project field | Tampering | None reaches it: the name is owner-typed only; the lead source is shown by key lookup in `lead-sources.md`, falling back to the raw key escaped with `e()` in the view |
| XSS through a notification title containing owner text | Tampering | `Notification::make()->title(e(...))`, the house convention in `ViewConversation` |
| Duplicate projects from concurrent inbounds | Tampering (integrity) | Partial unique index + savepoint + row lock (Pattern 2) |
| Open job leaking or blocking the inbound | Denial of service | Try/catch + report at dispatch; after-commit job (Pattern 3) |
| Agent writing projects without a chokepoint | Elevation of privilege | D-11 records that this is not an agent action; the write-confinement test keeps the four actions the only writers, and a plan that lets an agent open/close/rename must add the chokepoint (CLAUDE.md rule) |
| Sensitive data in the dashboard link | Information disclosure | Link text is the contact's name or phone, shown only to authenticated owners (the contact list already shows both) |

## Sources

### Primary (HIGH confidence)
- Read this session: `07-CONTEXT.md`, `REQUIREMENTS.md`, `STATE.md`, `CLAUDE.md`, `.ai/rules/*.md`, `app/Rooms/{Room,RoomRegistry,RoomSection,RoomServiceProvider,RecordRoomEntry,TimelineEntryType}.php`, `app/Rooms/{Marketing,FrontDesk,Team}/*`, `app/Actions/{RecordInbound,CloseConversation,ReopenConversation}.php`, `app/Records/{CustomerCard,MarkdownText}.php`, `app/Icm/{ActiveConfig,Validator,PlainWords,HiddenCharacters,ValidationReport}.php`, `app/Models/{Contact,Activity,Conversation}.php`, `app/Enums/{ActivityType,ContactStatus,PlatformKind}.php`, `app/Providers/{AppServiceProvider,Filament/AdminPanelProvider}.php`, `app/Filament/Resources/{Contacts,Conversations}/**`, `app/Filament/Widgets/NewLeads.php`, `app/Jobs/{RenderCustomerCard,RespondToLifeSafety}.php`, `bootstrap/providers.php`, `phpunit.xml`, `tests/Pest.php`, `tests/Unit/ArchitectureTest.php`, `tests/Rooms/{SpecTables,RoomSectionAxe}.php`, `tests/Feature/Rooms/{FrontDeskBootTest,TeamBootTest,MarketingBootTest,RoomAccessibilityTest,RoomSectionTest,SpecTablesTest,NeverLeftOutTest}.php`, `tests/Feature/Records/CardInjectionTest.php`, `tests/Feature/Icm/ValidatorTest.php`, `docs/SPEC.md` (ownership, kinds, file types), `docs/BLUEPRINT.md` (Phase 7 lines), the three migrations for contacts, conversations, activities, `.github/workflows/tests.yml`, `examples/example-hvac/**`.
- Vendor source: `vendor/filament/filament/src/Resources/RelationManagers/RelationManager.php:220-236`; `vendor/filament/actions/src/Concerns/HasSchema.php:26,128`; `vendor/laravel/framework/src/Illuminate/Events/Dispatcher.php:606-607,743-745` (after-commit handling); `composer show filament/filament` (v5.10.1).
- Prototype run: scratch copy of the repo, baseline and two prototype runs (results above). The real repo tree was not modified.

Verbatim values the plans depend on (read this session):
- `app/Enums/ActivityType.php:11,20`: `case OwnerAlerted = 'owner_alerted';` ... `case RoomEntry = 'room_entry';` (the enum's last case; new cases append after it).
- `tests/Feature/Rooms/FrontDeskBootTest.php:90`: `expect($registered->claimedWidgets)->not->toContain(NewLeads::class);`
- `tests/Feature/Records/CardInjectionTest.php:90`: `->and(headingLines($card))->toHaveCount(4);` (line 72 has the same expression in a test that does not activate the example folder and does not change).
- `tests/Pest.php:295`: `return 25;` in `exampleFolderFileCount()`.
- `app/Providers/AppServiceProvider.php:86`: `'owner_alert' => OwnerAlert::class,` (inside `Relation::enforceMorphMap([...])`).
- `app/Actions/RecordInbound.php:281`: `'status' => ContactStatus::Lead,` (the only place a contact is created from an inbound; `matchOrCreateContact()` returns `'new'` as the third element).
- `app/Icm/ActiveConfig.php:69-76`: `hasRoom()` returns `isset($this->version()->compiled['index']['room'][$key])`, `false` on `NoActiveVersion`.
- `.planning/config.json`: `"tdd_mode": true`, `"test_command": "php artisan test"`, `"branching_strategy": "phase"`.

### Secondary (MEDIUM confidence)
- None.

### Tertiary (LOW confidence)
- None. No web research was needed; the work is internal and the Laravel/Filament behaviours relied on were read from installed vendor source.

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH, nothing new, versions read from the lock.
- Architecture: HIGH, each pattern has an in-repo precedent and the open hook was exercised by a full-suite prototype.
- Pitfalls: HIGH for SQLite behaviour and test blast radius (run); MEDIUM for Postgres-only behaviour (savepoint, partial index), proven by existing precedent but not run locally.

**Research date:** 2026-10-29
**Valid until:** 2026-11-28 (stable internal code; re-check if any other branch edits `RecordInbound`, `RoomRegistry`, `CustomerCard` or the example folder before planning).
