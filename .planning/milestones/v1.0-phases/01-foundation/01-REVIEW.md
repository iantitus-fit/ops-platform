---
phase: 01-foundation
reviewed: 2026-10-11T00:00:00Z
depth: standard
files_reviewed: 119
files_reviewed_list:
  - .env.example
  - .gitignore
  - app/Actions/CloseConversation.php
  - app/Actions/InboundData.php
  - app/Actions/RecordInbound.php
  - app/Actions/ReopenConversation.php
  - app/Actions/SendOwnerReply.php
  - app/Channels/FakeTextChannel.php
  - app/Channels/InboundText.php
  - app/Channels/TextChannel.php
  - app/Channels/TwilioTextChannel.php
  - app/Console/Commands/IcmStatus.php
  - app/Console/Commands/IcmSync.php
  - app/Console/Commands/RecordsRebuild.php
  - app/Enums/ActivityType.php
  - app/Enums/ContactStatus.php
  - app/Enums/ConversationStatus.php
  - app/Enums/IcmFileKind.php
  - app/Enums/MessageAuthor.php
  - app/Enums/MessageChannel.php
  - app/Enums/MessageDirection.php
  - app/Enums/MessageStatus.php
  - app/Enums/SyncOutcome.php
  - app/Enums/VersionStatus.php
  - app/Events/CustomerRecordChanged.php
  - app/Filament/Pages/BusinessFolder.php
  - app/Filament/Resources/Contacts/ContactResource.php
  - app/Filament/Resources/Contacts/Pages/ListContacts.php
  - app/Filament/Resources/Contacts/Pages/ViewContact.php
  - app/Filament/Resources/Contacts/Schemas/ContactInfolist.php
  - app/Filament/Resources/Contacts/Tables/ContactsTable.php
  - app/Filament/Resources/Conversations/ConversationResource.php
  - app/Filament/Resources/Conversations/Pages/ListConversations.php
  - app/Filament/Resources/Conversations/Pages/ViewConversation.php
  - app/Filament/Resources/Conversations/Schemas/ConversationInfolist.php
  - app/Filament/Resources/Conversations/Tables/ConversationsTable.php
  - app/Http/Controllers/FakeTextWebhookController.php
  - app/Http/Controllers/LeadController.php
  - app/Http/Middleware/EnsureLocalEnvironment.php
  - app/Http/Middleware/EnsureTextDriverIsFake.php
  - app/Http/Requests/FakeTextWebhookRequest.php
  - app/Http/Requests/StoreLeadRequest.php
  - app/Icm/ActiveConfig.php
  - app/Icm/Changelog.php
  - app/Icm/Exceptions/IcmFolderMissing.php
  - app/Icm/Exceptions/NoActiveVersion.php
  - app/Icm/FolderSync.php
  - app/Icm/IcmFile.php
  - app/Icm/LoadedFolder.php
  - app/Icm/Loader.php
  - app/Icm/PlainWords.php
  - app/Icm/SyncResult.php
  - app/Icm/SyncStatus.php
  - app/Icm/ValidationReport.php
  - app/Icm/Validator.php
  - app/Jobs/ProcessInbound.php
  - app/Jobs/RenderCustomerCard.php
  - app/Listeners/QueueCustomerRecordRender.php
  - app/Livewire/ConversationThread.php
  - app/Livewire/DevChat.php
  - app/Models/Activity.php
  - app/Models/Contact.php
  - app/Models/Conversation.php
  - app/Models/IcmVersion.php
  - app/Models/Message.php
  - app/Models/User.php
  - app/Providers/AppServiceProvider.php
  - app/Records/CustomerCard.php
  - app/Records/MarkdownText.php
  - app/Records/RecordsWriter.php
  - app/Support/PhoneNumber.php
  - bootstrap/app.php
  - composer.json
  - config/channels.php
  - config/icm.php
  - database/factories/ActivityFactory.php
  - database/factories/ContactFactory.php
  - database/factories/ConversationFactory.php
  - database/factories/IcmVersionFactory.php
  - database/factories/MessageFactory.php
  - database/migrations/2026_10_11_125152_create_icm_versions_table.php
  - database/migrations/2026_10_11_131450_create_contacts_table.php
  - database/migrations/2026_10_11_131451_create_conversations_table.php
  - database/migrations/2026_10_11_131452_create_messages_table.php
  - database/migrations/2026_10_11_131453_create_activities_table.php
  - database/seeders/DatabaseSeeder.php
  - database/seeders/DemoContactsSeeder.php
  - phpunit.xml
  - resources/views/filament/pages/business-folder.blade.php
  - resources/views/layouts/app.blade.php
  - resources/views/livewire/conversation-thread.blade.php
  - resources/views/livewire/dev-chat.blade.php
  - routes/web.php
  - tests/Feature/DevChatTest.php
  - tests/Feature/Filament/AdminAccessTest.php
  - tests/Feature/Filament/BusinessFolderPageTest.php
  - tests/Feature/Filament/ContactResourceTest.php
  - tests/Feature/Filament/ConversationResourceTest.php
  - tests/Feature/Icm/ActiveConfigTest.php
  - tests/Feature/Icm/ChangelogTest.php
  - tests/Feature/Icm/LoaderTest.php
  - tests/Feature/Icm/SyncIgnoresRecordsTest.php
  - tests/Feature/Icm/SyncTest.php
  - tests/Feature/Icm/ValidatorTest.php
  - tests/Feature/Inbound/ContactMatchingTest.php
  - tests/Feature/Inbound/FakeTextWebhookTest.php
  - tests/Feature/Inbound/LeadFormTest.php
  - tests/Feature/Inbound/OwnerReplyTest.php
  - tests/Feature/Inbound/TextChannelTest.php
  - tests/Feature/Quality/MoneyIsIntegerCentsTest.php
  - tests/Feature/Quality/ProjectRulesTest.php
  - tests/Feature/Quality/SeederTest.php
  - tests/Feature/Records/CardInjectionTest.php
  - tests/Feature/Records/CustomerCardTest.php
  - tests/Feature/Records/RecordsRebuildTest.php
  - tests/Feature/Records/RenderCustomerCardJobTest.php
  - tests/Feature/Records/SummaryFilesTest.php
  - tests/Pest.php
  - tests/Unit/ArchitectureTest.php
findings:
  critical: 0
  warning: 13
  info: 6
  total: 19
status: issues_found
---

# Phase 01: Code Review Report

**Reviewed:** 2026-10-11
**Depth:** standard
**Files Reviewed:** 119
**Status:** issues_found

## Summary

All 119 files in the list were read in full. The phase is in better shape than most: the records renderer quotes customer text correctly, the loader/validator/sync path is carefully built, `ActiveConfig` is scoped so queue workers do not serve a stale version, money is integer cents end to end, and the morph map and partial unique index are sound. I found no proven BLOCKER. I did not downgrade anything to reach that count; each item below is either a narrow race, a failure-path gap, or a hazard that only bites once Phase 2 lands.

The strongest items are WR-01 (a lead can 500 on a contact-creation race), WR-02 (a dispatch failure after commit is never retried, because the dedupe path suppresses re-dispatch), WR-03 (the card job retries in a tight loop on any exception) and WR-04 (the records writer can silently create a bogus business folder).

No structural findings (fallow) were supplied and no external reviewer evidence was supplied.

Already-tracked items (01-SECURITY.md) are not re-reported: the seeded `test@example.com` login, the fake text driver default, the changelog key echo, the untested race-catch branches, and the T-01-01 symlink fix. WR-01 does add something new about the untested race branches: one of the races is not caught at all.

**Send double-submit (asked for explicitly): judged not a correctness bug in the UI.** `wire:submit` on `resources/views/livewire/conversation-thread.blade.php:34` gets Livewire 4's built-in form disabling. `vendor/livewire/livewire/dist/livewire.js:16160-16215` sets the submit button `disabled` and the textarea `readOnly` for the life of the request, so a double click cannot send twice. The residual hazard is server side and cross-tab, covered by WR-12.

## Warnings

### WR-01: A contact-creation race returns 500 and drops the lead

**File:** `app/Actions/RecordInbound.php:48-53` (race at `:153`)
**Issue:** The `catch (UniqueConstraintViolationException)` assumes the only unique violation is the `(provider, provider_message_id)` message index, and it resolves by calling `alreadyRecorded()`. `contacts.phone_e164` is also unique (`database/migrations/2026_10_11_131450_create_contacts_table.php:17`). Two concurrent inbounds from a brand-new number (a form plus a text, or a form double-posted) both miss the `where('phone_e164')` lookup, and the second `Contact::create` throws the same exception class. For a form, `alreadyRecorded()` returns `null` immediately (no provider id), so the exception is rethrown and the visitor gets a 500. The lead's message is lost, even though the first request created the contact. For a text, the second SID is not recorded, so the same happens until Twilio retries. This is a different, uncaught race from the two catch branches already listed as untested.
**Fix:** Retry the whole record once when the violation is not a message dedupe.
```php
try {
    $message = DB::transaction(fn (): Message => $this->record($data));
} catch (UniqueConstraintViolationException $exception) {
    $existing = $this->alreadyRecorded($data);
    if ($existing !== null) {
        return $existing;
    }
    // The other writer created the contact first; matching will now find it.
    $message = DB::transaction(fn (): Message => $this->record($data));
}
```

### WR-02: A dispatch failure after commit is never retried

**File:** `app/Actions/RecordInbound.php:42-46` and `:55-56`
**Issue:** The message is committed first, then `ProcessInbound::dispatch(...)` and `CustomerRecordChanged::dispatch(...)` run. If either throws (queue database unavailable, listener error), the caller gets a 500 and the provider retries. On the retry, `alreadyRecorded()` returns the stored message at line 42 and the method returns early, so nothing is ever dispatched. The code treats "already recorded" as "already dispatched", and the test `records a retried webhook once and queues nothing the second time` locks that in. In Phase 2, `ProcessInbound` is where the front-desk agent replies (AGNT-02), so that text would never be answered, and the card would never re-render.
**Fix:** Make recording and enqueueing one atomic step. With the database queue driver, dispatch inside the transaction (drop `afterCommit()` for `ProcessInbound` so it is a row in the same transaction), or add a `dispatched_at` column on `messages` and have the dedupe branch dispatch when it is null.

### WR-03: The card job retries in a tight loop on any exception

**File:** `app/Jobs/RenderCustomerCard.php:45-48`
**Issue:** The job uses `retryUntil()` (10 minutes) with no `$tries`, no `$maxExceptions` and no `backoff()`. When `handle()` throws (disk full, permissions, `LockTimeoutException` from `writeSummaries`), the worker releases it with the default backoff of 0 and retries immediately, for up to 10 minutes. That is thousands of retries and log lines per failing job, and each retry competes for the 30s `records:summaries` lock. The docblock explains why a time limit replaces an attempt count, but it only accounts for the `WithoutOverlapping` releases, not real failures.
**Fix:**
```php
public int $maxExceptions = 3;

/** @return list<int> */
public function backoff(): array
{
    return [5, 30, 120];
}
```

### WR-04: The records writer creates the business folder when the path is wrong

**File:** `app/Records/RecordsWriter.php:52`, `:78`, `:90`, `:122`
**Issue:** `File::ensureDirectoryExists()` is recursive. If `ICM_PATH` is mistyped or the folder is not mounted yet, the first inbound text makes the queue worker `mkdir -p` the entire `<ICM_PATH>/records/customers` tree. The path now exists, so `Loader::load()` no longer throws `IcmFolderMissing` ("Set ICM_PATH in .env"). The owner instead gets a confusing "no file has type: business" rejection from a folder that only holds `records/`. `config/icm.php` also says `ICM_PATH` "must be an absolute path" but nothing enforces it, so a relative value resolves against whatever the process's working directory is (web and worker can differ), splitting records across two trees.
**Fix:** Refuse to write unless the business folder itself exists.
```php
private function ensureRoot(): void
{
    $icmRoot = config('icm.path');
    if (! File::isDirectory($icmRoot)) {
        throw new IcmFolderMissing($icmRoot);
    }
}
```
Call it at the top of `writeCard`, `writeSummaries`, `writeRouter` and `rebuild`, and have `IcmSync` and `IcmFolderMissing` treat a relative `ICM_PATH` as an error.

### WR-05: A changelog write failure leaves a half-finished sync

**File:** `app/Icm/FolderSync.php:135-141` (callers at `:67`, `:97`, `:129`)
**Issue:** `recorded()` writes `changelog.md` after the new version is already committed (or the rejected row created). If the write fails (read-only mount, permissions, a directory named `changelog.md`), the exception escapes `run()`. `IcmSync` and `BusinessFolder::runSync()` catch only `IcmFolderMissing`, so the owner sees a stack trace or a generic Livewire error, while the new version is in fact live. A retry then reports "Already active", so the failure is never surfaced as a sync result.
**Fix:** Catch the write failure inside `recorded()`, append a notice such as "changelog.md could not be updated: <reason>" to the result's notices, and return the result. A sync should not fail because its log file could not be written.

### WR-06: Customer-controlled text is rendered as HTML in owner notifications

**File:** `app/Filament/Resources/Conversations/Pages/ViewConversation.php:38`, `app/Livewire/ConversationThread.php:33`
**Issue:** Both pass `$exception->getMessage()` to `Notification::title()`. Filament renders titles through `str($title)->sanitizeHtml()` (`vendor/filament/notifications/src/Notification.php:411`), and the configured sanitizer allows safe elements plus `class` and `style` on every element (`vendor/filament/support/src/SupportServiceProvider.php:112-126`). `ReopenConversation` builds its message from `$contact->name`, which a public lead form fills with up to 120 characters of anything. A name such as `<a href="https://evil.example" style="position:fixed;inset:0">...</a>` becomes a clickable full-screen link in the owner's admin. The reopen refusal is reachable only on a stale page (the action is hidden otherwise), which keeps this from being a BLOCKER. `BusinessFolder::runSync()` already does the right thing with `e(...)`, so the codebase is inconsistent about it.
**Fix:** Escape customer- or provider-derived text before it reaches a title.
```php
Notification::make()->title(e($exception->getMessage()))->danger()->send();
```
Also escape in `ReopenConversation` where the name is built, or switch the notification to `->title('Cannot reopen')->body(e(...))`.

### WR-07: Close and Reopen trust a stale page and are not idempotent

**File:** `app/Actions/CloseConversation.php:17-35`, `app/Filament/Resources/Conversations/Pages/ViewConversation.php:25` and `:33`
**Issue:** The `visible()` check runs against the record loaded when the page opened. `CloseConversation::handle()` does not re-check status, so a second tab (or a double-confirm) closes an already-closed conversation again: `closed_at` moves, a second "Conversation closed by owner" activity is written, and another `CustomerRecordChanged` fires. `ReopenConversation::handle()` excludes the conversation itself from its "another open" check, so reopening an already-open one adds a duplicate "reopened" note. Timeline entries are the record of what happened, so duplicates are wrong data.
**Fix:** Refresh and guard at the top of each `handle()`.
```php
$conversation->refresh();
if ($conversation->status === ConversationStatus::Closed) {
    return;
}
```
(and the mirror for reopen).

### WR-08: No upper bound on cost and price lets one number break every lead

**File:** `app/Icm/Validator.php:112` and `:120` (columns at `database/migrations/2026_10_11_131450_create_contacts_table.php:21`, `..._131453_create_activities_table.php:21`)
**Issue:** `cost_cents_per_lead` and `price_cents` are validated as `integer:strict|min:0` only. The values land in `unsignedInteger` columns, which are plain 32-bit `integer` on PostgreSQL (the partial unique index in the conversations migration is written for Postgres). An owner typing 3000000000 passes validation and goes live, then every inbound from that source fails with "integer out of range" and returns 500. That breaks the documented rule that "A lead is never dropped for its source" (`RecordInbound.php` docblock). SQLite does not enforce the range, so no test catches it.
**Fix:** Cap the rules at a sane maximum, and add a validator test for it.
```php
'sources.*.cost_cents_per_lead' => 'bail|required|integer:strict|min:0|max:100000000',
'price_cents' => 'bail|required_unless:price_policy,none|integer:strict|min:0|max:100000000',
```

### WR-09: Email matching is unordered and can create duplicate emails

**File:** `app/Actions/RecordInbound.php:148` and `:173-176`
**Issue:** When a contact is found by phone and has no email, the form's email is written onto it without checking whether another contact already has that address (`contacts.email` is indexed, not unique). From then on two contacts share an email. The email lookup at line 148 is `->first()` with no `ORDER BY`, so which of the two receives a later email-only form is up to the database. The test `lands on the contact the phone matches when the email belongs to someone else` only covers the case where the phone-matched contact already has an email.
**Fix:** Skip filling the email when another contact owns it (and add a note instead), and make the lookup deterministic: `->orderBy('id')->first()`.

### WR-10: Test data can collide with the fixed numbers the tests use

**File:** `database/factories/ContactFactory.php:28`, with `tests/Feature/Records/SummaryFilesTest.php:105`
**Issue:** The factory draws `+1{202|312}55501##`, i.e. every number from `+12025550100` to `+12025550199`. Tests then hard-code numbers inside that range (`+12025550177` in the open-leads test, `+12025550123` and `+12025550101..104` elsewhere). `fake()->unique()` only de-duplicates generated values, not explicit overrides. In `SummaryFilesTest` "lists only leads with an open conversation", five factory contacts are created next to the explicit `+12025550177`, so roughly 1 run in 80 fails with a unique-constraint error on `phone_e164` (about 5 draws, each 1 in 400 for that exact number). This is a flaky test, which is in scope for test reliability.
**Fix:** Keep the factory out of the range tests hard-code, e.g. generate `+1{area}55502##`, or give the fixed numbers in tests a block the factory never produces.

### WR-11: The lead throttle is per IP with no trusted-proxy setup

**File:** `app/Providers/AppServiceProvider.php:68`, `bootstrap/app.php:14-16`
**Issue:** `Limit::perMinute(10)->by($request->ip())` is the only abuse control on the public `/leads` endpoint. `bootstrap/app.php` never calls `trustProxies()`. Behind any load balancer or CDN, `$request->ip()` is the proxy's address unless the host platform injects trusted-proxy config, so every visitor shares one bucket and the 11th lead of the minute, from anyone, gets a 429 (a lost lead). If proxies are trusted too broadly instead, the limit is spoofable through `X-Forwarded-For`. Neither case is tested. There is also no honeypot or captcha, so one IP can still create 10 contacts a minute, each of which writes a card file.
**Fix:** Decide the production topology and set `$middleware->trustProxies(at: [...])` explicitly. Consider keying on the normalized phone or email as well as the IP.

### WR-12: Sending an owner reply is not idempotent across failure and tabs

**File:** `app/Actions/SendOwnerReply.php:63-89`
**Issue:** The text goes out at line 64, then a second transaction (lines 71-89) records it as sent. If that transaction throws, the customer has the text, the message stays `queued`, and the owner sees a 500 and naturally presses Send again, so the customer gets it twice. Separately, nothing serializes two sends for one conversation: two tabs (the single-tab double click is covered by Livewire, see Summary) send two texts. Once Twilio is live a slow carrier call widens that window. A customer-facing SMS cannot be recalled, so this is the one place idempotency matters most.
**Fix:** Take a per-conversation lock around the whole method, and wrap the post-send update so a failure there is logged and the message is still marked sent.
```php
return Cache::lock("reply:{$conversation->id}", 30)->block(5, function () use ($conversation, $body) {
    // existing body of handle()
});
```
Pass the stored message id to the provider as an idempotency key once Twilio is wired in.

### WR-13: The public form can steer outbound texts to someone else's number (before Phase 2)

**File:** `app/Actions/RecordInbound.php:144-150`, `app/Jobs/ProcessInbound.php:23`
**Issue:** `/leads` is unauthenticated and matches an existing contact by whatever phone or email the visitor typed, then appends the message to that contact's open conversation and bumps `last_activity_at`. In Phase 1 that only pollutes a thread. When `ProcessInbound` starts replying (AGNT-02), the same path becomes "anyone can make the business text any customer's phone", limited only by 10 posts a minute per IP. The same endpoint also lets a visitor claim any lead-source key (`angi` carries a real cost), which feeds cost-per-lead numbers. Nothing here is wrong today, but the hole is built in this phase.
**Fix:** Record the match method on the message (`meta.matched_by`) now, and decide in Phase 2 that agent replies go only to a number that has texted in, or that was verified, not one typed into a form. Consider signing source keys for known directories.

## Info

### IN-01: The "Regex" validation message is wrong for slugs and intake keys

**File:** `app/Icm/PlainWords.php:38` (patterns at `app/Icm/Validator.php:110,116,122`)
**Issue:** One sentence, "dashes or underscores", is used for three different patterns. A service `slug` allows dashes only, and an intake `key` allows underscores only. An owner told that underscores are fine gets rejected again for a slug like `ac_repair`. The tests pin the current text.
**Fix:** Pass the pattern in, or branch on the field name, so each message states what that field allows.

### IN-02: Scaffolding and duplicate helpers left behind

**File:** `tests/Pest.php:47` and `:62`, `tests/Feature/Inbound/FakeTextWebhookTest.php:29`, `app/Icm/Changelog.php:46`
**Issue:** `toBeOne` and `something()` are Pest scaffold leftovers that nothing uses. `twilioText()` duplicates `fakeTextPayload()` from `Pest.php`. The `SyncOutcome::Busy => throw` arm in `Changelog::entry()` can never run because `FolderSync` never records a busy result.
**Fix:** Delete the scaffold, have `FakeTextWebhookTest` use `fakeTextPayload()`, and drop the unreachable arm (or leave a one-line comment saying it is a guard).

### IN-03: Rejected syncs add a row each and consume version numbers

**File:** `app/Icm/FolderSync.php:114`, `app/Models/IcmVersion.php:51`
**Issue:** `label()` is `'v'.$id` and a rejected attempt inserts a row, so the next live version skips numbers (v1 live, one rejection, next activation reads "v3"). The owner never saw a v2 as live. A scheduled sync against a broken folder also adds one row per run, indefinitely.
**Fix:** Number only activated versions (a separate counter), and update the existing rejected row for the same fingerprint instead of inserting another.

### IN-04: The loader silently ignores files with an uppercase extension

**File:** `app/Icm/Loader.php:53`
**Issue:** `->name('*.md')` is case-sensitive, so `Faq.MD` (common from Windows and some editors) is skipped without a notice, and the owner's content never goes live.
**Fix:** `->name('/\.md$/i')`.

### IN-05: `records:rebuild` can delete a card written during the rebuild

**File:** `app/Records/RecordsWriter.php:126-136`
**Issue:** The rebuild snapshots expected filenames while iterating contacts, then deletes every unlisted `.md` in the folder. A contact created, and carded by a queue job, after the iteration passes its id is deleted as an orphan. It self-heals on that contact's next event, but the index then points at a missing file.
**Fix:** Take the `records:summaries` lock (or a rebuild lock the job also checks) around the whole rebuild.

### IN-06: `ConversationThread` relies entirely on the Filament panel for authorization

**File:** `app/Livewire/ConversationThread.php:28-40`
**Issue:** `sendReply()` sends an SMS to a customer with no check of its own that the caller is signed in. It is protected today because the component is only reachable through the authenticated Filament page and Livewire's checksummed snapshot, and `OwnerReplyTest` always calls `actingAs` first, so the unauthenticated case is untested. Sending authority is a project rule, so it is worth one line of defence.
**Fix:** Add `abort_unless(auth()->check(), 403);` at the top of `sendReply()` and a test that a guest call sends nothing.

---

_Reviewed: 2026-10-11_
_Reviewer: Claude (gsd-code-reviewer)_
_Depth: standard_
