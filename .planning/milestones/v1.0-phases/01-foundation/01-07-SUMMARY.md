---
phase: 01-foundation
plan: 07
subsystem: dashboard
tags: [livewire, filament, pest, arch-tests, seeders, sqlite]

requires:
  - phase: 01-foundation
    provides: "FolderSync, ActiveConfig, IcmVersion (plans 01-01, 01-02), RecordInbound/InboundData/InboundText/PhoneNumber (01-03), the Contacts and Conversations dashboard and the ConversationThread reply path (01-06)"
provides:
  - "Local-only /dev/chat page (Route::livewire behind EnsureLocalEnvironment) that texts the business as a customer through the in-process inbound path"
  - "Business Folder dashboard page: live version, files, last sync result, errors, and an inline Sync Now"
  - "App\\Icm\\SyncStatus (active(), latest()) with rejected-wins-on-tie"
  - "DemoContactsSeeder: four fictional contacts, called by DatabaseSeeder"
  - "QUAL-01 gate: integer-cents schema guard, arch guards for direct disk reads, File facade and env(), full suite green"
affects: [phase-02-front-desk-agent, phase-02-twilio]

actuals:
  tokens: 11000
  tasks: 3
  commits: 6

tech-stack:
  added: []
  patterns:
    - "Environment gating checked per request in middleware and again in the Livewire component, because Livewire updates bypass the route"
    - "Notification bodies carrying owner-written text are escaped with e() per line before joining with <br>"
    - "One Pest arch() per function name; a list passed to expect() silently checked nothing"
    - "Schema guard reads Schema::getTables() and Schema::getColumns() and is proven against throwaway tables"

key-files:
  created:
    - app/Http/Middleware/EnsureLocalEnvironment.php
    - app/Livewire/DevChat.php
    - resources/views/livewire/dev-chat.blade.php
    - resources/views/layouts/app.blade.php
    - app/Icm/SyncStatus.php
    - app/Filament/Pages/BusinessFolder.php
    - resources/views/filament/pages/business-folder.blade.php
    - database/seeders/DemoContactsSeeder.php
    - tests/Feature/DevChatTest.php
    - tests/Feature/Filament/BusinessFolderPageTest.php
    - tests/Feature/Quality/SeederTest.php
    - tests/Feature/Quality/MoneyIsIntegerCentsTest.php
  modified:
    - routes/web.php
    - database/seeders/DatabaseSeeder.php
    - tests/Unit/ArchitectureTest.php
    - docs/sprints/sprint-0.md
    - phpunit.xml

key-decisions:
  - "Sync Now error lines are escaped with e() before they go into the Filament notification body, because Filament runs the body through sanitizeHtml() which still allows a safe subset of tags"
  - "The money guard skips Laravel's own job_batches table, whose total_jobs column is a job count and not money; every other table is checked"
  - "phpunit.xml sets memory_limit to 512M because the suite outgrew PHP's 128M CLI default at about 260 tests"
  - "Seeded Dana Whitfield uses +12025550123, which is also the /dev/chat default From number, so a dev-chat text joins Dana's open thread instead of creating a fifth contact"

patterns-established:
  - "Livewire component tests check a refusal with ->assertStatus(404); Livewire::test() does not throw HttpException"
  - "Guards that look at the whole codebase are proven by temporarily adding an offending file and watching the rule fail"

requirements-completed: [DASH-02, DASH-01, QUAL-01]

coverage:
  - id: D1
    description: "/dev/chat lets Ian text as a customer in the local environment (same InboundText, InboundData::fromText, RecordInbound path, in process), the thread updates by polling, and the page and component return 404 everywhere else"
    requirement: DASH-02
    verification:
      - kind: integration
        ref: "tests/Feature/DevChatTest.php (13 tests: 404 outside local, 200 in local, records a dev- text, refuses outside local on mount and send, empty body, number normalization, unreadable number, repeat text, reply appears on refresh, empty thread)"
        status: pass
    human_judgment: true
    rationale: "The page was exercised only through Livewire and HTTP tests; its look and the two-second polling in a real browser are covered by the phase UAT, not by the executor"
  - id: D2
    description: "Business Folder page shows the live version (vN, file count, live since in the business timezone), its files with types, the last sync result with errors and notices, and a Sync Now button that runs FolderSync inline and reports the outcome in a notification"
    requirement: DASH-01
    verification:
      - kind: integration
        ref: "tests/Feature/Filament/BusinessFolderPageTest.php (11 tests: first sync, live version and files, unchanged, rejected with v1 kept live, escaped error lines, rejected-wins tie, later check wins, no sync yet, busy lock, skipped later-sprint file)"
        status: pass
    human_judgment: true
    rationale: "The page was never opened in a browser by the executor; layout and the real notification pop-up are covered by the phase UAT"
  - id: D3
    description: "DemoContactsSeeder creates four fictional contacts with conversations, messages and activities, using libphonenumber-valid numbers and integer-cent costs from the example lead sources"
    requirement: QUAL-01
    verification:
      - kind: integration
        ref: "tests/Feature/Quality/SeederTest.php (9 tests)"
        status: pass
    human_judgment: false
  - id: D4
    description: "Money fields are integer cents: every money-named column across the schema ends in _cents with an integer type, the two cost columns exist as integers with integer model casts, and the guard flags float, decimal and unsuffixed columns while passing a table without money"
    requirement: QUAL-01
    verification:
      - kind: unit
        ref: "tests/Feature/Quality/MoneyIsIntegerCentsTest.php (6 tests)"
        status: pass
    human_judgment: false
  - id: D5
    description: "Nothing reads configuration from disk except the sync: no raw file-reading function in the listed App namespaces, the File facade only in Loader, Changelog and RecordsWriter, no env() in App"
    requirement: QUAL-01
    verification:
      - kind: unit
        ref: "tests/Unit/ArchitectureTest.php (7 per-function rules, the File facade rule and the env rule)"
        status: pass
    human_judgment: false
  - id: D6
    description: "Every Pest test passes"
    requirement: QUAL-01
    verification:
      - kind: other
        ref: "php artisan test --compact: 263 passed, 1204 assertions"
        status: pass
    human_judgment: false

duration: 10 min
completed: 2026-10-11
status: complete
plan_head_before: 6e2670a3646150aee13a82a610976dd196ccc546
plan_head_after: fcaf04c7c3cbdc2a1b8b6e4559c8f7ebfed8eec3
---

# Phase 1 Plan 7: Dev Chat, Business Folder Page and the QUAL-01 Gate Summary

**A local-only /dev/chat that records customer texts through the real inbound path, a Business Folder page with an inline Sync Now that reports through escaped Filament notifications, a four-contact demo seeder, and integer-cents and no-disk-read guards, with all 263 Pest tests green**

## Performance

- **Duration:** 10 min
- **Started:** 2026-10-11T14:57:23Z
- **Completed:** 2026-10-11T15:07:00Z
- **Tasks:** 3 (one tracer, two TDD)
- **Files modified:** 17 (12 created, 5 modified)

## Accomplishments

- /dev/chat works in the local environment: a typed text goes through `InboundText` -> `InboundData::fromText` -> `RecordInbound` in process, lands on the timeline with a `dev-` provider id, and the thread refreshes every two seconds, so an owner reply sent from the dashboard shows up. The route middleware and `DevChat::mount()` and `send()` each refuse outside local.
- The Business Folder page shows the live version, its file count, live-since time in the business timezone, the file list, and the last sync result with errors and "Not supported yet" notices. Sync Now runs `FolderSync::run()` inline and answers with "Live as vN (n files)", "Already active: vN, no changes", "Rejected. vN stays live." plus each error line, or "A sync is already running. Try again in a moment."
- `SyncStatus::latest()` picks the newest of the latest rejected attempt and the live version's last check, and gives a tie to the rejected attempt so a failure is never hidden.
- `DemoContactsSeeder` (called by `DatabaseSeeder`) adds Dana Whitfield, Marcus Lee, Priya Shah and Sam Ortiz with threads, timeline entries and costs of 4500, 3200, 0 and null. Verified on a scratch SQLite database with `migrate:fresh --seed` (4 contacts, 7 messages, 8 activities).
- QUAL-01 is guarded: the money guard walks every table's columns, the arch rules forbid direct disk reads in the listed namespaces, limit the `File` facade to the three classes allowed to write the folder, and ban `env()` in `App`. Each guard was proven by adding an offending file or table and watching it fail.

## Task Commits

1. **Task 1: Dev chat (tracer)** - `3e561a4` (test, RED), `ba97585` (feat, GREEN)
2. **Task 2: Business Folder page** - `582b848` (test, RED), `46205bd` (feat, GREEN)
3. **Task 3: Seeder and QUAL-01 gate** - `fea8138` (test, RED), `fcaf04c` (feat, GREEN)

**Plan metadata:** committed separately after this file (docs: complete plan).

## Files Created/Modified

- `app/Http/Middleware/EnsureLocalEnvironment.php` - 404 unless `app()->environment('local')`, read per request
- `app/Livewire/DevChat.php` - local-only test chat: `from`, `body`, `send()`, computed `thread`
- `resources/views/livewire/dev-chat.blade.php` - heading, polling thread, From and message form with errors
- `resources/views/layouts/app.blade.php` - minimal layout with `{{ $slot }}` and no Vite reference
- `routes/web.php` - `Route::livewire('/dev/chat', DevChat::class)` named `dev.chat`
- `app/Icm/SyncStatus.php` - `active()` and `latest()` for the page
- `app/Filament/Pages/BusinessFolder.php` - page with the `syncNow` header action
- `resources/views/filament/pages/business-folder.blade.php` - live version, files, last sync sections
- `database/seeders/DemoContactsSeeder.php`, `database/seeders/DatabaseSeeder.php` - fictional demo data
- `tests/Feature/DevChatTest.php`, `tests/Feature/Filament/BusinessFolderPageTest.php`, `tests/Feature/Quality/SeederTest.php`, `tests/Feature/Quality/MoneyIsIntegerCentsTest.php`, `tests/Unit/ArchitectureTest.php` - tests and guards
- `phpunit.xml` - `memory_limit` 512M
- `docs/sprints/sprint-0.md` - eight walk-through log lines

## Decisions Made

- Notification body text is escaped with `e()` per line, joined with `<br>`. Filament renders the body through `sanitizeHtml()`, which strips scripts but still allows a safe subset of tags, so an owner-written value such as `<i>x</i>` would otherwise render as markup. A test mutation (removing `e()`) fails the escaping test.
- The money guard exempts `job_batches` only. Laravel's own queue table has `total_jobs`, a count of jobs. Every other table, including future ones, is checked.
- Seeded Dana uses +12025550123 as the plan specifies, which equals the /dev/chat default From number.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Livewire::test does not throw for an aborted component**
- **Found during:** Task 1 (GREEN)
- **Issue:** The RED tests expected `HttpException` from `Livewire::test(DevChat::class)` and from `->call('send')` outside local. Livewire's test harness records the 404 as the response status instead of throwing, so the expectations could never pass even though the component aborts correctly.
- **Fix:** The two tests now use `->assertStatus(404)`; the second also asserts nothing was recorded.
- **Files modified:** tests/Feature/DevChatTest.php
- **Verification:** DevChatTest 13/13 pass; the component's `abort_unless` is exercised by both tests.
- **Committed in:** ba97585

**2. [Rule 1 - Bug] Arch rule over a list of function names checked nothing**
- **Found during:** Task 3 (GREEN, mutation check)
- **Issue:** `arch(...)->expect([...functions])->not->toBeUsedIn([...])` stayed green with `file_get_contents`, `glob` and others used in `App\Http`. The `File` facade and `env` rules, written as single targets, did fail correctly.
- **Fix:** One `arch()` per function inside a `foreach`. Re-running the probe with all seven functions in `App\Http` fails seven rules; the probe file was removed afterwards.
- **Files modified:** tests/Unit/ArchitectureTest.php
- **Verification:** Mutation probe fails 7 per-function rules; clean tree passes 12/12.
- **Committed in:** fcaf04c

**3. [Rule 3 - Blocking] Full suite exhausted PHP's 128M memory limit**
- **Found during:** Task 3 (running `php artisan test --compact`)
- **Issue:** At 263 tests the single test process crossed the 128M CLI default (fatal "Allowed memory size exhausted" in a Filament view render in OwnerReplyTest). With `memory_limit=1G` the suite passes, so this is accumulation across the suite and not one leaking test.
- **Fix:** `<ini name="memory_limit" value="512M"/>` in phpunit.xml.
- **Files modified:** phpunit.xml
- **Verification:** `php artisan test --compact` passes: 263 tests, 1204 assertions.
- **Committed in:** fcaf04c

**4. [Rule 3 - Blocking] Money guard flagged Laravel's job_batches.total_jobs**
- **Found during:** Task 3 (designing the guard against the real schema)
- **Issue:** The "total" pattern matches `job_batches.total_jobs`, a job count created by Laravel's queue migration.
- **Fix:** `job_batches` is the one table skipped, with a docblock saying why. No app table is exempt.
- **Files modified:** tests/Feature/Quality/MoneyIsIntegerCentsTest.php
- **Verification:** Guard passes on the real schema and flags the throwaway float, decimal and unsuffixed columns.
- **Committed in:** fea8138

---

**Total deviations:** 4 auto-fixed (2 bugs in test code, 2 blocking)
**Impact on plan:** No production behavior changed. All four are test or test-config corrections needed for the plan's own checks to be true.

## TDD Gate Compliance

RED and GREEN commits exist for all three tasks, in order (`test(01-07)` before `feat(01-07)`). The `gsd_run check tdd-red-evidence` classifier was not run: Pest's JSON summary is not one of its supported report formats, so the RED evidence below is the semantic assessment only.

- **Task 1 RED (3e561a4):** 12 of 13 tests failed for the planned reason (`Unable to find component: [App\Livewire\DevChat]`, and 404 instead of 200 for the local page). One test, "404 outside local", passed at RED because an absent route also answers 404; it stays as a regression guard and is covered by the component 404 tests, which did fail.
- **Task 2 RED (582b848):** all 11 tests failed (page class and route missing). The escaping test used a session read that does not see Filament notifications; it was corrected in the GREEN commit to read them through Filament's `Notifications` component, then mutation-checked (removing `e()` makes it fail).
- **Task 3 RED (fea8138):** the 9 seeder tests that needed data failed (no seeder). The money guard and arch tests passed at RED because earlier plans already satisfied them; they are characterization tests, and each was proven to bite by mutation (see deviation 2 and the money guard's throwaway-table tests).

## Issues Encountered

- The first GREEN run of the full suite died on memory (deviation 3). The `artisan test` wrapper does not pass `-d memory_limit` to its child, so the fix had to be in phpunit.xml.

## Known Stubs

None. The only empty-state text is the intended "No messages yet." and "No version is live yet. Click Sync Now." sentences.

## Threat Flags

None beyond the plan's threat model. T-01-31 (dev chat outside local) is mitigated by the route middleware plus `abort_unless` in `mount()` and `send()`, covered by DevChatTest. T-01-33 (escaping) is mitigated on the page by Blade echo and in notifications by `e()`. T-01-34 (repeated Sync Now) is covered by the busy-lock test.

## User Setup Required

None - no external service configuration required.

## Phase UAT notes for Ian

- The Task 3 human-check (browser walk) was not run by the executor and is recorded in `.planning/WINDOWS.md`. One step will not match the plan text: the human-check expects the dev-chat text from +12025550123 to create a separate contact beside "the seeded four", but Dana Whitfield is seeded with that same number, so the text joins Dana's open conversation (4 contacts, not 5). Use another number such as +13125550199 to see a new contact.
- Please run the complete suite yourself with `php artisan test --compact` (CLAUDE.md). The executor's run was 263 passed.
- `migrate:fresh --seed` is the way to seed; `db:seed` twice hits the unique phone and email indexes.

## Next Phase Readiness

- Phase 1 plan set is complete (7 of 7); ready for phase verification and Ian's UAT walk.
- `SendOwnerReply` remains the single place that writes outbound messages; Phase 2's agent and takeover should keep calling it.

## Self-Check: PASSED

- Created files verified on disk (12 of 12 found).
- Commits verified as ancestors of HEAD: 3e561a4, ba97585, 582b848, 46205bd, fea8138, fcaf04c.
- `php artisan test --compact`: 263 passed.

---
*Phase: 01-foundation*
*Completed: 2026-10-11*
