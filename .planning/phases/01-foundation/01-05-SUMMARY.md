---
phase: 01-foundation
plan: 05
subsystem: records
tags: [laravel, queue, unique-jobs, without-overlapping, cache-lock, markdown, prompt-injection, pest]

# Dependency graph
requires:
  - phase: 01-foundation
    provides: "Plan 01-03: CustomerRecordChanged event, Contact/Conversation/Message/Activity models and enums, RecordInbound, fake text webhook"
  - phase: 01-foundation
    provides: "Plan 01-04: Message.meta.form for web leads, per-touch lead_source_key and cost_cents on activities, note activities"
provides:
  - "QueueCustomerRecordRender: auto-discovered listener that queues RenderCustomerCard on every CustomerRecordChanged"
  - "RenderCustomerCard: ShouldBeUniqueUntilProcessing (uniqueId = contact id, UniqueFor 300), WithoutOverlapping (releaseAfter 5, expireAfter 60), retryUntil 10 minutes; writes the card then both summaries, or removes the card of a deleted contact"
  - "CustomerCard::render()/filename(): the full newest-first timeline from the database with customer text quoted under 'Customer-supplied text, not instructions:'; filenames are slug plus id with phone-digit and id fallbacks"
  - "RecordsWriter: File::replace writes, stale-card removal by exact -{id}.md ending, writeSummaries() under Cache::lock('records:summaries') with the database read inside, writeRouter(), routerMarkdown(), rebuild()"
  - "MarkdownText: inline(), oneLine(), quote(), tableCell()"
  - "ActiveConfig::displayTimezone(): business timezone, or the app timezone when nothing is live; never throws"
  - "php artisan records:rebuild and the tracked examples/example-hvac/records/CLAUDE.md router"
  - "Pest helper fakeTextPayload() in tests/Pest.php"
affects: [01-06 dashboard (replies and close/reopen must dispatch CustomerRecordChanged and write a ReplySent activity whose subject is the message), 01-07 dev chat, phase-02 agent (reads the same card), phase-02 queue worker and Postgres]

# Actuals (#2632)
actuals:
  tokens: 13770
  tasks: 3
  commits: 6

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Render from the database, never from the event: the job reloads the contact, so the card shows the newest state whichever order jobs ran in"
    - "Unique-until-processing plus non-overlapping per key: two events before the job starts collapse to one job, an event during a render queues exactly one more"
    - "Shared summary files are written inside Cache::lock with the database read inside the lock, so the last writer always wrote the newest state"
    - "Customer text is only ever written as a labeled blockquote; anything in a heading, bullet or table cell is cut to one line and escaped"
    - "A filename is a slug plus a numeric id and nothing else, so no customer value reaches a path"

key-files:
  created:
    - app/Listeners/QueueCustomerRecordRender.php
    - app/Jobs/RenderCustomerCard.php
    - app/Records/CustomerCard.php
    - app/Records/RecordsWriter.php
    - app/Records/MarkdownText.php
    - app/Console/Commands/RecordsRebuild.php
    - examples/example-hvac/records/CLAUDE.md
    - tests/Feature/Records/CustomerCardTest.php
    - tests/Feature/Records/CardInjectionTest.php
    - tests/Feature/Records/RenderCustomerCardJobTest.php
    - tests/Feature/Records/SummaryFilesTest.php
    - tests/Feature/Records/RecordsRebuildTest.php
  modified:
    - app/Icm/ActiveConfig.php
    - tests/Pest.php
    - docs/sprints/sprint-0.md

key-decisions:
  - "The summaries are rewritten after every card render, including the render of a deleted contact, because a new, renamed or deleted customer changes both files"
  - "open-leads.md shows the contact's phone in the Name column when the lead has no name (most first-time texters have none), then '(no name)' when there is no phone either; INDEX.md shows '(no name)' because it has its own Phone column"
  - "open-leads.md Source column shows the raw lead source key, not the display name, so it does not depend on a live version"
  - "Cards and summaries show times in the business timezone (ActiveConfig::displayTimezone()) as 'Y-m-d H:i' with no zone label, as the plan specifies"
  - "A name slug is capped at 60 characters so a very long name cannot overflow a path component"

patterns-established:
  - "Tests that need an inbound text use fakeTextPayload() from tests/Pest.php, one MessageSid per text"
  - "A new file in records/customers/ that is not a card must not end in -{id}.md; the stale-card sweep matches that exact ending"

requirements-completed: [RECS-01]

# Metrics
duration: 12min
completed: 2026-10-11
plan_head_before: d5f82b8479531b9e73be429a0bd40d7e8ab43123
plan_head_after: a05154d943b51fd0d6e37ee5540b959c6ea617ef
commits: 6
status: complete

coverage:
  - id: D1
    description: "An inbound text writes that customer's card to records/customers/<slug>-<id>.md from the database, through CustomerRecordChanged, QueueCustomerRecordRender, RenderCustomerCard and CustomerCard, with the phone, the timeline heading and the quoted, labeled body"
    requirement: RECS-01
    verification:
      - kind: integration
        ref: "tests/Feature/Records/CustomerCardTest.php#it writes the customer card into records/customers when a text arrives"
        status: pass
    human_judgment: false
  - id: D2
    description: "RECS-01 adjacency and ordering: two texts in the same second give one card showing both, the later one first; the full timeline (text, web form, note, owner reply) is newest first with 1,600-character bodies whole"
    requirement: RECS-01
    verification:
      - kind: integration
        ref: "tests/Feature/Records/CustomerCardTest.php (shows two texts from the same second in one card, the later one first; carries the full timeline newest first, every message in full)"
        status: pass
    human_judgment: false
  - id: D3
    description: "D-20 and RECS-01 encoding: filename is name slug plus id, accents become ASCII, symbol-only or missing names fall back to phone digits then id, a renamed contact's old card is removed, another contact's card (even with an id ending in the same digits) is untouched"
    requirement: RECS-01
    verification:
      - kind: integration
        ref: "tests/Feature/Records/CustomerCardTest.php (names the card after the contact and removes the old card when the name changes; builds a safe filename from any name; never removes the card of a contact whose id only ends in the same digits; keeps a very long name from overflowing the filename)"
        status: pass
    human_judgment: false
  - id: D4
    description: "Prompt-injection and markdown-spoofing hygiene (T-01-21, T-01-22): customer text, form fields and echoing notes are always blockquoted under the label; names, sources and previews are escaped to one line; a hostile name never reaches a path"
    requirement: RECS-01
    verification:
      - kind: integration
        ref: "tests/Feature/Records/CardInjectionTest.php (all five cases) and tests/Feature/Records/CustomerCardTest.php#it splits a customer text on every kind of line ending"
        status: pass
    human_judgment: false
  - id: D5
    description: "RenderCustomerCard job contract: unique until processing per contact for at most 300 seconds, one WithoutOverlapping (releaseAfter 5, expireAfter 60), retryUntil ten minutes, two events before the job starts queue one job, a deleted contact removes its card files"
    requirement: RECS-01
    verification:
      - kind: integration
        ref: "tests/Feature/Records/RenderCustomerCardJobTest.php (all seven cases)"
        status: pass
    human_judgment: false
  - id: D6
    description: "RECS-01 empty and idempotence: no activity renders 'No activity yet.', no contacts gives 'No customers yet.' and 'No open leads.', rendering twice gives byte-identical files, a card renders before any version is live"
    requirement: RECS-01
    verification:
      - kind: integration
        ref: "tests/Feature/Records/CustomerCardTest.php (says there is no activity yet...; renders the same bytes twice...; renders a card before any business folder version is live) and tests/Feature/Records/SummaryFilesTest.php#it writes empty summaries when there are no contacts"
        status: pass
    human_judgment: false
  - id: D7
    description: "D-22 summaries: INDEX.md lists name, phone and card link ordered by lowercased name then id; open-leads.md lists only leads with an open conversation, newest activity first, with waiting-since and a one-line preview; both update in the same render as the card; pipes and newlines never break a row"
    requirement: RECS-01
    verification:
      - kind: integration
        ref: "tests/Feature/Records/SummaryFilesTest.php (lists every customer in INDEX.md...; lists only leads with an open conversation...; updates INDEX.md and open-leads.md in the same render as the card)"
        status: pass
    human_judgment: false
  - id: D8
    description: "Summary writes hold Cache::lock('records:summaries') and read the database only inside it"
    requirement: RECS-01
    verification:
      - kind: integration
        ref: "tests/Feature/Records/SummaryFilesTest.php#it writes the summaries only while it holds the records lock, and reads the database inside it"
        status: pass
    human_judgment: false
  - id: D9
    description: "records:rebuild regenerates the router, every card and both summaries, removes card files that belong to no contact, and leaves other files alone; the tracked router equals the rendered one; icm:sync still reports 'Already active: v1, no changes' with records files present"
    requirement: RECS-01
    verification:
      - kind: integration
        ref: "tests/Feature/Records/RecordsRebuildTest.php (all four cases)"
        status: pass
    human_judgment: false
  - id: D10
    description: "D-23: generated cards, INDEX.md and open-leads.md under examples/*/records/ are ignored by git while records/CLAUDE.md and README.md stay tracked, and app/Records is not ignored"
    requirement: RECS-01
    verification:
      - kind: other
        ref: "git check-ignore -q examples/example-hvac/records/customers/dana-whitfield-17.md && git check-ignore -q examples/example-hvac/records/open-leads.md && ! git check-ignore -q examples/example-hvac/records/CLAUDE.md (exit 0); git check-ignore -q app/Records/CustomerCard.php (exit 1)"
        status: pass
    human_judgment: false
  - id: D11
    description: "records:rebuild run by hand against examples/example-hvac leaves git status clean apart from the new tracked router, and the generated cards and summaries read well to the owner"
    requirement: RECS-01
    verification:
      - kind: other
        ref: "php artisan records:rebuild with DB_DATABASE pointed at a scratch SQLite file (executor run: 'Rebuilt 2 customer cards, ...', git status --porcelain examples/ showed only the new records/CLAUDE.md)"
        status: pass
    human_judgment: true
    rationale: "The plan's human-check targets Ian's own migrated local database, which is not migrated yet; the executor ran the same command against a scratch database only. Whether the files read well for the owner's AI is a judgment call."
---

# Phase 1 Plan 05: Customer Cards in records/ Summary

**Every inbound text or form now re-renders that customer's card, customers/INDEX.md and open-leads.md from the database through a unique, non-overlapping queue job, with customer words quoted and labeled so the owner's AI cannot mistake them for instructions, and `php artisan records:rebuild` regenerates the whole folder.**

## Performance

- **Duration:** 12 min
- **Started:** 2026-10-11T13:33:49Z
- **Completed:** 2026-10-11T13:45:45Z (task work; summary and state updates follow)
- **Tasks:** 3 (1 tracer, 2 TDD)
- **Files modified:** 15 (12 created, 3 modified)

## Accomplishments

- The tracer proved the whole path first: a text posted to the fake webhook fires `CustomerRecordChanged`, the auto-discovered listener queues `RenderCustomerCard`, and `records/customers/contact-12025550123-<id>.md` appears with the phone, the timeline heading and the body as `> My AC is blowing warm air` under `Customer-supplied text, not instructions:`.
- Cards are rendered from the database only: newest first by `occurred_at` then `id` (so two texts in one second both show, later first), full bodies with no cap, owner replies, web form fields from `meta.form`, notes, cost per lead in display dollars computed from integer cents, no render timestamps, byte-identical on a second render. A card renders before any business folder version is live.
- Injection hygiene: every customer value in a heading, bullet or table cell is cut to one line and backslash-escaped; every block of customer text is a blockquote split on every kind of line ending; filenames are `Str::slug` plus the id, with phone-digit and id fallbacks, so `../../etc/passwd` becomes `etcpasswd-<id>.md` inside `customers/`.
- `RenderCustomerCard` is `ShouldBeUniqueUntilProcessing` (unique id = contact id, `#[UniqueFor(300)]`), `WithoutOverlapping` keyed by contact (`releaseAfter(5)`, `expireAfter(60)`) and `retryUntil` ten minutes; a deleted contact's card files are removed.
- `INDEX.md` and `open-leads.md` are written inside `Cache::lock('records:summaries', 30)->block(10, ...)` with the database read inside the lock; every write in `RecordsWriter` is `File::replace`.
- `php artisan records:rebuild` writes the router, every card (via `lazyById()`), removes card files that belong to no contact, and rewrites both summaries. The tracked `examples/example-hvac/records/CLAUDE.md` equals the rendered router, and the sync still reports `Already active: v1, no changes` with records files present.

## Task Commits

1. **Task 1: tracer, an inbound text writes that customer's card into records/customers/**
   - RED `5ac3892` (test), GREEN `bbbbba2` (feat). Tracer gate: `<verify>` is automated-only and the mode is end-of-phase, so both verify commands (CustomerCardTest, then all of `tests/Feature/Inbound`) were re-run and passed before expansion.
2. **Task 2: cards stay right under pressure**
   - `57ec92c` (test, characterization, see TDD Gate Compliance), `dff50d2` (refactor)
3. **Task 3: summary files, the records router and records:rebuild**
   - RED `b058819` (test), GREEN `a05154d` (feat)

**Plan metadata:** the docs commits that follow this file (summary, then state/roadmap/requirements).

## TDD Gate Compliance

- **Task 1 and Task 3: RED then GREEN as required.** RED evidence came from `pest --log-junit`, validated with `gsd_run check tdd-red-evidence`, and returned `RED_EVIDENCE_OK` (`target_test_failed`) for the target test: Task 1 "writes the customer card into records/customers when a text arrives" (card file missing, no listener), Task 3 "updates INDEX.md and open-leads.md in the same render as the card" (neither file written). Semantic assessment: each target executed and failed on the planned `File::exists` assertion, not on a syntax, import or fixture fault. At the Task 3 RED commit all nine new tests were red (the other seven on the missing `writeSummaries()` and `records:rebuild`).
- **Task 2: no RED was possible, and none was invented.** The plan's own Task 1 GREEN steps (2 to 5) already specify the whole job contract, the escaping, the filename rules and the rename sweep, so the Task 1 implementation satisfied every Task 2 behavior. The first run of the new tests had 3 failures, and all three were mistakes in the tests, not the code (a missing `activateExampleFolder()`, a wrong needle for the form body, and `Str::slug('../../etc/passwd')` being `etcpasswd`, not `etc-passwd`). Once fixed, everything passed with no implementation change needed, which is the "unexpected GREEN" case in `tdd.md`; investigation found the cause above, so I committed the tests as `test(01-05)` (characterization, the commit message says so) rather than halting or fabricating a RED. There is no `feat` commit for Task 2.
- **Compensating check for Task 2:** five temporary mutations of the implementation, each reverted from a saved copy, were each caught by the intended tests: drop the `#` escape (2 failures: heading and source injection), drop `ShouldBeUniqueUntilProcessing` (2: queue-once and contract), drop the stale-card sweep (1: rename), sort oldest first (1: same-second order), quote only the first line (5: injection, line endings, timeline, notes). This shows the tests can fail for the right reasons; it is not RED evidence.

## Files Created/Modified

- `app/Listeners/QueueCustomerRecordRender.php` - queues the render job for the event's contact id
- `app/Jobs/RenderCustomerCard.php` - unique, non-overlapping render job; card then summaries
- `app/Records/CustomerCard.php` - card markdown and filename
- `app/Records/RecordsWriter.php` - atomic writes, stale-card sweep, summaries under lock, router, rebuild
- `app/Records/MarkdownText.php` - one-line escaping, block quotes, table cells
- `app/Console/Commands/RecordsRebuild.php` - `records:rebuild`
- `app/Icm/ActiveConfig.php` - `displayTimezone()`
- `examples/example-hvac/records/CLAUDE.md` - the tracked router
- `tests/Feature/Records/*Test.php` - 38 tests across CustomerCard, CardInjection, RenderCustomerCardJob, SummaryFiles and RecordsRebuild
- `tests/Pest.php` - shared `fakeTextPayload()` helper
- `docs/sprints/sprint-0.md` - 7 Walk-through log lines (listeners, unique jobs, job middleware, `retryUntil`, `Queue::fake()`, locks around shared files, `lazyById()`)

## Decisions Made

- Summaries are rewritten after every card render, including a deleted contact's, because any of those changes both files.
- `open-leads.md` puts the phone in the Name column for a lead with no name, since most first-time texters have none and a column of "(no name)" would tell the owner's AI nothing. The plan names the columns but not this fallback.
- The Source column shows the raw lead source key so the table does not depend on a live version.
- A name slug is cut to 60 characters before slugging so a very long name cannot overflow a path component.
- Times are the business timezone with no zone label in the file, as specified. See Next Phase Readiness.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Missing critical] Slug length cap**
- **Found during:** Task 1 (writing `CustomerCard::filename()`)
- **Issue:** The plan's filename rule had no length limit, so a very long contact name (the lead form allows 120 characters, texts have no name at all, but names can come from elsewhere) could exceed a filesystem path component limit and make the card write fail.
- **Fix:** The name is cut to 60 characters before `Str::slug`.
- **Files modified:** `app/Records/CustomerCard.php`
- **Verification:** `it keeps a very long name from overflowing the filename`
- **Committed in:** `bbbbba2`

**2. [Rule 3 - Blocking] Shared text helper moved into tests/Pest.php**
- **Found during:** Task 2 (three test files need to post a text)
- **Issue:** The helper defined inside `CustomerCardTest.php` only exists when that file is loaded, so running another test file alone would fail, and `tests/Pest.php` is not in the plan's file list.
- **Fix:** `fakeTextPayload()` lives in `tests/Pest.php` next to the other shared helpers.
- **Files modified:** `tests/Pest.php`
- **Committed in:** `57ec92c`

**3. [Rule 1 - Bug] Task 2 same-second test counted INDEX.md as a second card**
- **Found during:** Task 3 (after summaries started writing `INDEX.md` into `customers/`)
- **Issue:** The earlier test asserted `customers/` held exactly one file, which stopped being true when `INDEX.md` arrived.
- **Fix:** The test now ignores `INDEX.md` when counting cards.
- **Files modified:** `tests/Feature/Records/CustomerCardTest.php`
- **Committed in:** `a05154d`

**4. [Plan wording] `Queue::fake()` without class names**
- **Found during:** Task 2
- **Issue:** The acceptance criteria require the literal `Queue::fake()`, while the testing skill prefers class names unless the test asserts the complete result.
- **Fix:** The job tests use a bare `Queue::fake()` and assert the complete result with `Queue::assertCount()` plus `assertPushed` for both `ProcessInbound` and `RenderCustomerCard`.
- **Committed in:** `57ec92c`

---

**Total deviations:** 3 auto-fixed (1 missing critical, 1 blocking, 1 bug) plus 1 wording alignment
**Impact on plan:** All necessary for correctness or to keep the tests independent. No scope creep. The `retryUntil()` return type is `DateTimeInterface` (what the Laravel best-practice rule uses) rather than the plan's `DateTime`; `now()->addMinutes(10)` satisfies both.

## Issues Encountered

- The plan's human-check wants a migrated local database, and Ian's `database/database.sqlite` has pending migrations. I did not migrate it; the same command ran against a scratch SQLite file (`DB_DATABASE` override) and the real `examples/example-hvac` folder. `git status --porcelain examples/` showed only the new `records/CLAUDE.md`, and I deleted the ignored files that run generated.
- Reading `.env` is blocked by a secret-read guard, so the folder and database settings were checked with `php artisan config:show` instead.
- Task 2 had no honest RED; see TDD Gate Compliance.

## Authentication Gates

None.

## Known Stubs

None introduced. The two stubs from 01-03 (`ProcessInbound::handle`, `TwilioTextChannel`) are unchanged.

## Threat Flags

None beyond the plan's threat model. T-01-21 (spoofing through customer text), T-01-22 (path traversal through names), T-01-23 (PII in git, checked with `git check-ignore`), T-01-24 (partial reads, `File::replace`) and T-01-25 (lost summary updates, lock plus unique job) are each mitigated and covered by a test or check above.

## User Setup Required

None - no external service configuration required. Locally, with `QUEUE_CONNECTION=database`, cards appear only while a queue worker runs (`composer run dev` or `php artisan queue:work`); `php artisan records:rebuild` renders everything on demand.

## Verification

- `php artisan test --compact tests/Feature/Records`: 38 passed (141 assertions).
- `php artisan test --compact tests/Feature/Icm tests/Feature/Inbound tests/Feature/Quality tests/Unit/ArchitectureTest.php`: 147 passed, no regression.
- `php artisan test --compact` (whole suite): 187 passed, 768 assertions.
- `vendor/bin/pint --dirty --format agent`: passed.
- Gitignore check passes (generated records files ignored, `records/CLAUDE.md` tracked, `app/Records` not ignored).
- All acceptance criteria re-run and passing, including `grep -c file_put_contents app/Records/RecordsWriter.php` printing 0.
- Per CLAUDE.md, Ian should run the complete suite once: `php artisan test --compact`.

## Next Phase Readiness

- Plan 01-06: owner replies and close/reopen must dispatch `CustomerRecordChanged`, and a reply shows on the card only when it has a `ReplySent` activity whose subject is its `Message` (the card reads channel and status from that message). Close and reopen change `open-leads.md` membership, which the job already rewrites on every render.
- Plan 01-06 and 01-07 can reuse `ActiveConfig::displayTimezone()` for display times.
- Cards and summaries print times as `Y-m-d H:i` in the business timezone with no zone label, as the plan specifies. If the owner's AI turns out to misread them, adding the zone name to the card header is a one-line follow-up.
- Open assumption A6 (`retryUntil` is the right control for jobs released by `WithoutOverlapping`) is pinned by tests but only a real worker under Phase 2 load will confirm it.
- The unique lock and `Cache::lock` need a shared cache store in production (database or Redis); the array store in tests is per process.

---
*Phase: 01-foundation*
*Completed: 2026-10-11*

## Self-Check: PASSED

- All 12 created files and 3 modified files exist on disk.
- Commits found on the branch: `5ac3892`, `bbbbba2`, `57ec92c`, `dff50d2`, `b058819`, `a05154d`.
- Measured commit count for the plan: 6 code commits (`d5f82b8..a05154d`), plus the summary commit.
