---
phase: 01-foundation
fixed_at: 2026-10-11T00:00:00Z
review_path: .planning/phases/01-foundation/01-REVIEW.md
iteration: 1
findings_in_scope: 13
fixed: 10
skipped: 3
status: partial
---

# Phase 01: Code Review Fix Report

**Fixed at:** 2026-10-11
**Source review:** .planning/phases/01-foundation/01-REVIEW.md
**Iteration:** 1

**Summary:**
- Findings in scope: 13 (0 critical, 13 warning; Info findings are out of scope for `critical_warning`)
- Fixed: 10
- Skipped: 3

**Verification:** `workflow.use_worktrees` is `false` in `.planning/config.json`, so every edit, test run and commit happened in the main checkout on `gsd/phase-01-foundation` (no worktree, no temp branch). The full `php artisan test --compact` run in the main checkout at the end: 280 passed (1266 assertions), 0 failed. Baseline before any fix was 264 passed. Each fix also ran the narrowest relevant test files and `vendor/bin/pint --dirty --format agent`. Nothing was pushed or merged.

Tests were written or extended before the fix for the findings that touch the sync, sending or inbound recording. Most were seen failing first (WR-01, WR-04, WR-05, WR-06, WR-07, WR-10). WR-03, WR-08 and WR-12 had tests written in the same step as the change.

## Fixed Issues

### WR-01: A contact-creation race returns 500 and drops the lead

**Files modified:** `app/Actions/RecordInbound.php`, `tests/Feature/Inbound/ContactMatchingTest.php`
**Commit:** 5e21796
**Status:** fixed: requires human verification (concurrency logic)
**Applied fix:** When the unique violation is not a message dedupe, the whole record is retried once, so matching now finds the contact the other request created. The test uses a `Contact::creating` listener to inject a conflicting row on the first insert. That row is rolled back with the failed transaction, so the test proves the retry path and that the lead is kept, but it cannot prove behaviour against a genuinely committed second writer.

### WR-03: The card job retries in a tight loop on any exception

**Files modified:** `app/Jobs/RenderCustomerCard.php`, `tests/Feature/Records/RenderCustomerCardJobTest.php`
**Commit:** 8b1e138
**Applied fix:** Added `public int $maxExceptions = 3` and `backoff()` returning `[5, 30, 120]`. `retryUntil()` and the overlap middleware are unchanged. The test pins the two values; it does not run a worker, so the real retry timing is not exercised.

### WR-04: The records writer creates the business folder when the path is wrong

**Files modified:** `app/Records/RecordsWriter.php`, `app/Console/Commands/RecordsRebuild.php`, `tests/Feature/Records/RecordsRebuildTest.php`
**Commit:** 360be00
**Applied fix:** `writeCard`, `writeSummaries`, `writeRouter` and `rebuild` now throw `IcmFolderMissing` when `ICM_PATH` is not an existing directory, instead of creating it. `records:rebuild` catches it and exits 1 with the message. The reviewer's extra suggestion to reject a relative `ICM_PATH` in `IcmSync` and `IcmFolderMissing` was not done: it is a separate config-validation decision. The queue job will now fail (and stop after three exceptions) rather than create the folder.

### WR-05: A changelog write failure leaves a half-finished sync

**Files modified:** `app/Icm/FolderSync.php`, `tests/Feature/Icm/ChangelogTest.php`
**Commit:** 15b80f0
**Applied fix:** `recorded()` catches the write failure and returns the same result with a "changelog.md could not be updated: ..." notice. `icm:sync` prints it as a warning and exits 0. The Business folder page shows stored version notices, not the result's, so the dashboard does not display this particular notice; the command does.

### WR-06: Customer-controlled text is rendered as HTML in owner notifications

**Files modified:** `app/Filament/Resources/Conversations/Pages/ViewConversation.php`, `app/Livewire/ConversationThread.php`, `tests/Feature/Inbound/OwnerReplyTest.php`
**Commit:** bc167f3
**Applied fix:** Both notification titles now go through `e(...)`. The test for `ConversationThread` proves the escaping. The reopen path in `ViewConversation` has no test: Filament refuses to call a hidden action, so the stale-page route the review describes cannot be driven from a test (a test I tried failed on exactly that), and the fix there is the same one-line `e()`.

### WR-07: Close and Reopen trust a stale page and are not idempotent

**Files modified:** `app/Actions/CloseConversation.php`, `app/Actions/ReopenConversation.php`, `tests/Feature/Filament/ConversationResourceTest.php`
**Commit:** cfd4d97
**Status:** fixed: requires human verification (state logic)
**Applied fix:** `CloseConversation` re-reads the row under `lockForUpdate()` inside its transaction and returns without writing a note or dispatching the event if it is already closed. `ReopenConversation` refreshes and returns if the conversation is already open. Tests use a stale model instance to stand in for the second tab.

### WR-08: No upper bound on cost and price lets one number break every lead

**Files modified:** `app/Icm/Validator.php`, `app/Icm/PlainWords.php`, `tests/Feature/Icm/ValidatorTest.php`
**Commit:** 7eb25b7
**Applied fix:** Both `cost_cents_per_lead` and `price_cents` get `max:100000000` through a `MAX_CENTS` constant, and `PlainWords` words the new failure ("must be 100000000 or less (you wrote ...)"). Two dataset rows pin the owner-facing sentence. The cap value is the reviewer's suggestion ($1,000,000); Ian may want a different ceiling.

### WR-09: Email matching is unordered and can create duplicate emails

**Files modified:** `app/Actions/RecordInbound.php`, `tests/Feature/Inbound/ContactMatchingTest.php`
**Commit:** e352165
**Applied fix:** The email lookup orders by id, and an email already owned by another contact is not copied onto the phone-matched contact; a timeline note "form gave an email another customer already has: ..." is written instead. The note wording is mine, not the reviewer's.

### WR-10: Test data can collide with the fixed numbers the tests use

**Files modified:** `database/factories/ContactFactory.php`, `tests/Feature/Quality/FactoryPhoneTest.php` (new)
**Commit:** 0165f31
**Applied fix:** The factory now generates `+1{202|312}55502##`, leaving the 555-01XX block to hand-written test numbers. A new test checks generated numbers never fall in the 01XX block and are valid E.164. The full suite passed three times in a row after the change. One new test file was created, as the fix needed a place for the guard.

### WR-12: Sending an owner reply is not idempotent across failure and tabs

**Files modified:** `app/Actions/SendOwnerReply.php`, `tests/Feature/Inbound/OwnerReplyTest.php`
**Commit:** 3db901c
**Status:** fixed: requires human verification (concurrency logic)
**Applied fix:** A per-conversation `Cache::lock("reply:{id}", 30)` wraps the whole send. A second send while the lock is held throws a `DomainException` at once (non-blocking `get()` rather than the reviewer's `block(5, ...)`, so a second tab is told immediately and the test does not wait five seconds). If recording the sent text fails after the channel accepted it, the error is reported, a minimal "mark sent with provider id" update is tried, and nothing is raised. The reviewer's last line, passing an idempotency key to the provider, is Phase 2 (Twilio) work and was not done. The lock only works across processes on a cache store that supports locks (the app default, `database`, does).

## Skipped Issues

### WR-02: A dispatch failure after commit is never retried

**File:** `app/Actions/RecordInbound.php:42-46` and `:55-56`
**Reason:** Needs a design decision, not a code fix. The review offers two options with different costs: dispatch `ProcessInbound` inside the transaction (which only works while the queue is the database driver, and couples recording to that choice), or add a `dispatched_at` column to `messages` (a schema change that also has to cover `CustomerRecordChanged`). The right pick depends on how the Phase 2 agent (AGNT-02) will run, so it should be decided with Ian before Phase 2 starts rather than guessed here. The existing test `records a retried webhook once and queues nothing the second time` encodes the current behaviour and would need to change with it.
**Original issue:** The message is committed, then dispatch runs. If dispatch throws, the provider's retry hits the dedupe branch and returns early, so the job and the card render are never queued.

### WR-11: The lead throttle is per IP with no trusted-proxy setup

**File:** `app/Providers/AppServiceProvider.php:68`, `bootstrap/app.php:14-16`
**Reason:** Needs a deployment decision. The correct `trustProxies(at: [...])` value depends on the production topology (Laravel Cloud, a load balancer, a CDN), which is not recorded in the repo. Setting it blind would either leave every visitor in one bucket or make the limit spoofable. Keying on phone or email as well, or adding a honeypot, are product choices too.
**Original issue:** `$request->ip()` is the proxy's address behind a load balancer unless proxies are trusted, so the 10-per-minute limit can lock out all visitors, or be spoofed if trusted too broadly.

### WR-13: The public form can steer outbound texts to someone else's number (before Phase 2)

**File:** `app/Actions/RecordInbound.php:144-150`, `app/Jobs/ProcessInbound.php:23`
**Reason:** The review states nothing is wrong in Phase 1 and the real decision ("agent replies go only to a number that has texted in or was verified") is a Phase 2 sending-authority design question, tied to AGNT-02 and `rules/sending.md`. The small code part, recording `meta.matched_by`, only has value once that policy exists. Carry this into Phase 2 planning.
**Original issue:** `/leads` is unauthenticated and matches existing contacts by typed phone or email; once `ProcessInbound` replies, anyone could make the business text any customer's number.

---

_Fixed: 2026-10-11_
_Fixer: Claude (gsd-code-fixer)_
_Iteration: 1_
