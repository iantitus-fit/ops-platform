---
phase: 02-text-front-desk
reviewed: 2026-10-12T13:10:55Z
depth: standard
files_reviewed: 187
files_reviewed_list:
  - .env.example
  - .github/workflows/tests.yml
  - CLAUDE.md
  - app/Actions/ClaimInboundBurst.php
  - app/Actions/ComposeAutomatedText.php
  - app/Actions/DispatchInbound.php
  - app/Actions/HandBackConversation.php
  - app/Actions/InboundData.php
  - app/Actions/OutboundSender.php
  - app/Actions/OwnerAlertText.php
  - app/Actions/PauseConversation.php
  - app/Actions/RaiseOwnerAlert.php
  - app/Actions/RecordInbound.php
  - app/Actions/RecordIntakeAnswers.php
  - app/Actions/ResolveDraft.php
  - app/Actions/RunFrontDesk.php
  - app/Actions/SendFixedReply.php
  - app/Actions/SendLifeSafetyLine.php
  - app/Actions/SendOwnerAlert.php
  - app/Actions/SendOwnerReply.php
  - app/Ai/AgentFile.php
  - app/Ai/AgentReply.php
  - app/Ai/AgentRun.php
  - app/Ai/Agents/FrontDeskAgent.php
  - app/Ai/FrontDeskPrompt.php
  - app/Ai/FrontDeskUnavailable.php
  - app/Ai/RunFrontDeskAgent.php
  - app/Channels/FakeTextChannel.php
  - app/Channels/InboundText.php
  - app/Channels/RecipientOptedOut.php
  - app/Channels/TextKeywords.php
  - app/Channels/TwilioSignature.php
  - app/Channels/TwilioTextChannel.php
  - app/Console/Commands/GateExport.php
  - app/Enums/ActivityType.php
  - app/Enums/AlertReason.php
  - app/Enums/DraftResolution.php
  - app/Enums/GateOutcome.php
  - app/Enums/MessageKind.php
  - app/Enums/MessageStatus.php
  - app/Enums/PausedReason.php
  - app/Enums/ReplyLabel.php
  - app/Enums/SendOrigin.php
  - app/Filament/Pages/Auth/EditProfileWithPhone.php
  - app/Filament/Pages/Auth/RememberedLogin.php
  - app/Filament/Pages/BusinessFolder.php
  - app/Filament/Resources/Conversations/Pages/ViewConversation.php
  - app/Filament/Resources/Conversations/Schemas/ConversationInfolist.php
  - app/Filament/Widgets/DraftsWaiting.php
  - app/Filament/Widgets/NewLeads.php
  - app/Filament/Widgets/PausedConversationsWaiting.php
  - app/Filament/Widgets/TodaysConversations.php
  - app/Http/Controllers/TwilioStatusCallbackController.php
  - app/Http/Controllers/TwilioTextWebhookController.php
  - app/Http/Middleware/EnsureTextDriverIsTwilio.php
  - app/Http/Middleware/ValidateTwilioSignature.php
  - app/Http/Requests/TwilioTextWebhookRequest.php
  - app/Icm/ActiveConfig.php
  - app/Icm/BusinessClock.php
  - app/Icm/FolderSync.php
  - app/Icm/Loader.php
  - app/Icm/Validator.php
  - app/Jobs/ProcessInbound.php
  - app/Jobs/RespondToLifeSafety.php
  - app/Jobs/SendDueAlerts.php
  - app/Jobs/SweepUndispatched.php
  - app/Livewire/ConversationThread.php
  - app/Livewire/DevChat.php
  - app/Models/Contact.php
  - app/Models/Conversation.php
  - app/Models/IntakeAnswer.php
  - app/Models/Message.php
  - app/Models/OwnerAlert.php
  - app/Models/User.php
  - app/Providers/AppServiceProvider.php
  - app/Providers/Filament/AdminPanelProvider.php
  - app/Records/CustomerCard.php
  - app/SendRule/AsksForPerson.php
  - app/SendRule/Classifier.php
  - app/SendRule/ClassifierFailed.php
  - app/SendRule/ClassifierResult.php
  - app/SendRule/CodeChecks.php
  - app/SendRule/DecideSendOrDraft.php
  - app/SendRule/FakeClassifier.php
  - app/SendRule/FixedReplies.php
  - app/SendRule/JevClassifier.php
  - app/SendRule/LifeSafety.php
  - app/SendRule/TriageQuestions.php
  - app/SendRule/TriageReading.php
  - app/SendRule/Verdict.php
  - bootstrap/app.php
  - config/gate.php
  - config/services.php
  - database/factories/ContactFactory.php
  - database/factories/ConversationFactory.php
  - database/factories/IntakeAnswerFactory.php
  - database/factories/MessageFactory.php
  - database/factories/OwnerAlertFactory.php
  - database/factories/UserFactory.php
  - database/migrations/2026_10_12_000001_add_dispatch_columns_to_messages_table.php
  - database/migrations/2026_10_12_000002_backfill_dispatch_columns_on_messages.php
  - database/migrations/2026_10_12_000003_add_phone_to_users_table.php
  - database/migrations/2026_10_12_000004_create_owner_alerts_table.php
  - database/migrations/2026_10_12_000005_add_front_desk_columns_to_messages_table.php
  - database/migrations/2026_10_12_000006_add_draft_resolution_to_messages_table.php
  - database/migrations/2026_10_12_000007_create_intake_answers_table.php
  - database/migrations/2026_10_12_000008_add_pause_columns_to_conversations_table.php
  - database/migrations/2026_10_12_000009_add_sms_opt_out_to_contacts_table.php
  - docs/SETUP.md
  - docs/SPEC.md
  - docs/sprints/sprint-1.md
  - examples/example-hvac/agents/README.md
  - examples/example-hvac/agents/front-desk.md
  - examples/example-hvac/rules/CONTEXT.md
  - examples/example-hvac/rules/replies.md
  - examples/example-hvac/rules/sending.md
  - examples/example-hvac/rules/triage.md
  - map/CLAUDE.md
  - map/VERIFICATION.md
  - map/objects/contacts/contact-opt-out.md
  - map/objects/conversations/conversation-and-pause.md
  - map/objects/folder/agent-file-and-version.md
  - map/objects/intake/intake-answer.md
  - map/objects/messages/message-and-verdict.md
  - map/processes/answer-inbound.md
  - resources/views/filament/pages/business-folder.blade.php
  - resources/views/livewire/conversation-thread.blade.php
  - routes/console.php
  - routes/web.php
  - tests/Feature/Alerts/OwnerAlertTest.php
  - tests/Feature/Alerts/OwnerAlertTimingTest.php
  - tests/Feature/Alerts/ProfileTest.php
  - tests/Feature/Channels/TwilioSendTest.php
  - tests/Feature/Channels/TwilioStatusCallbackTest.php
  - tests/Feature/Channels/TwilioWebhookTest.php
  - tests/Feature/DevChatTest.php
  - tests/Feature/Filament/BusinessFolderPageTest.php
  - tests/Feature/Filament/ConversationViewTest.php
  - tests/Feature/Filament/DashboardWidgetsTest.php
  - tests/Feature/Filament/DraftReviewTest.php
  - tests/Feature/FrontDesk/AgentFallbackTest.php
  - tests/Feature/FrontDesk/DisclosureTest.php
  - tests/Feature/FrontDesk/DraftReplacementTest.php
  - tests/Feature/FrontDesk/FrontDeskAgentTest.php
  - tests/Feature/FrontDesk/FrontDeskFailureTest.php
  - tests/Feature/FrontDesk/FrontDeskPromptTest.php
  - tests/Feature/FrontDesk/GateExportTest.php
  - tests/Feature/FrontDesk/GateTest.php
  - tests/Feature/FrontDesk/GateVerdictTest.php
  - tests/Feature/FrontDesk/HandoffTest.php
  - tests/Feature/FrontDesk/HeldDraftTest.php
  - tests/Feature/FrontDesk/HoldingReplyTest.php
  - tests/Feature/FrontDesk/IntakeAnswersTest.php
  - tests/Feature/FrontDesk/LifeSafetyTest.php
  - tests/Feature/FrontDesk/ProcessInboundTest.php
  - tests/Feature/FrontDesk/ReplyCapTest.php
  - tests/Feature/FrontDesk/TakeOverTest.php
  - tests/Feature/Icm/ActiveConfigTest.php
  - tests/Feature/Icm/AgentFileTest.php
  - tests/Feature/Icm/BusinessClockTest.php
  - tests/Feature/Icm/ChangelogTest.php
  - tests/Feature/Icm/RepliesValidationTest.php
  - tests/Feature/Icm/SendingRulesValidationTest.php
  - tests/Feature/Icm/SyncIgnoresRecordsTest.php
  - tests/Feature/Icm/SyncTest.php
  - tests/Feature/Icm/TriageValidationTest.php
  - tests/Feature/Icm/ValidatorTest.php
  - tests/Feature/Inbound/ContactMatchingTest.php
  - tests/Feature/Inbound/ContactNameTest.php
  - tests/Feature/Inbound/DispatchInboundTest.php
  - tests/Feature/Inbound/FakeTextWebhookTest.php
  - tests/Feature/Inbound/OptOutTest.php
  - tests/Feature/Inbound/OwnerReplyTest.php
  - tests/Feature/Inbound/SweepUndispatchedTest.php
  - tests/Feature/Inbound/TextChannelTest.php
  - tests/Feature/Quality/TestSafetyNetTest.php
  - tests/Feature/Records/CustomerCardTest.php
  - tests/Feature/Records/RecordsRebuildTest.php
  - tests/Feature/SendRule/FixedRepliesTest.php
  - tests/Feature/SendRule/JevClassifierTest.php
  - tests/Pest.php
  - tests/Unit/ArchitectureTest.php
  - tests/Unit/Channels/TextKeywordsTest.php
  - tests/Unit/SendRule/AsksForPersonTest.php
  - tests/Unit/SendRule/CodeChecksTest.php
  - tests/Unit/SendRule/LifeSafetyTest.php
  - tests/Unit/SendRule/TriageReadingTest.php
findings:
  critical: 2
  warning: 19
  info: 17
  total: 38
status: issues_found
---

# Phase 02: Code Review Report

**Reviewed:** 2026-10-12T13:10:55Z
**Depth:** standard
**Files Reviewed:** 187
**Status:** issues_found

## Narrative Findings (AI reviewer)

The phase touched 187 files, so the orchestrator split the review into three parallel gsd-code-reviewer runs over the same resolved scope, merged here. Finding IDs are renumbered across the merge (each heading notes its slice and original ID): A = send path (app/Actions, app/SendRule, app/Ai, app/Jobs, app/Records, app/Console; 43 files), B = channels, HTTP, Filament/Livewire, models, ICM, routes, config, migrations, CI (68 files), C = tests, docs, example folder, CLAUDE.md and map/ (76 files).

## Summary

**Slice A — send path:** The send path is carefully built: one outbound path with a lock, queued-then-sent recording, fail-closed classifier, fixed-vocabulary plain reasons (so the owner alert's `$why` is never model text), and a thorough untrusted-input treatment of the agent's output. No money-handling or injection defects were found in the code reviewed.
The main weakness is the failure semantics around the burst claim. `ProcessInbound` marks the customer's texts handled before the front desk runs and has no timeout, retry-safe un-claim or `failed()` hook. Any unexpected exception, or the worker timeout that the provider chain can hit, silently drops a customer's text with no owner alert. Several sibling paths share this "claimed or flagged first, acted on second, no recovery" shape (dispatch claim, owner-alert send, life-safety job, missing agent file). The remaining findings are gaps in the hard-veto word lists and the alert link scrubber, and an export that mislabels failed sends.

**Slice B — channels, UI and data:** Part B covers channels, the Twilio webhook and status callback, Filament and Livewire UI, models, ICM sync, providers, config, migrations and CI. The signature path is sound in its main design: it verifies against the configured URL, uses `hash_equals`, rejects an empty token, and `bootstrap/app.php` correctly turns off trimming and empty-string conversion for the signed routes. Migrations are portable between SQLite and PostgreSQL (partial unique indexes are written as raw SQL and recreated after the SQLite table rebuild). No injection, secret or auth-bypass problem was found.
The defects are mostly at upgrade and edge boundaries. The worst one is that the Business Folder page crashes against a still-live Phase 1 version, and that page holds the Sync Now button. Several hostile or malformed inputs reach unguarded code. No structural findings (fallow) were supplied.

**Slice C — tests, docs and map:** Slice reviewed: the Pest suite (tests/Feature, tests/Unit, tests/Pest.php), docs, the example business folder, CLAUDE.md and the new map/ folder. The suite is green (931 passed, re-run during review) and is, on the whole, unusually disciplined: stray HTTP requests and stray model prompts throw, Jev is always the scripted fake, Twilio signatures are recomputed in the test from Twilio's published recipe (not from the app class), and security-sensitive paths (signature checks, escaping, locked Livewire properties, opt-out on every origin, `--without-text` export) have real negative tests.
No real client data was found in examples/, tests/, docs/ or map/. All phone numbers are in the 555-01xx range, plus Twilio's own published test vector (`+14158675309`). No secrets, keys or tokens (the Twilio token and `sk-` values in tests are obvious placeholders).
map/ cards: every `path:line` citation I checked (about 200, extracted by script and eyeballed against source) points at the symbol the card says it does. No app code changed between the cards' stamped commit (be50284) and HEAD, so the cards are current. The three VERIFICATION.md entries are true (grep for `agent_conversation*` over app/config/routes/resources/tests/database finds no referrer; "approval log" appears only at `config/gate.php:41`).
Four warnings: the test run is not isolated from the developer's `.env`, one assertion that cannot fail, one negative test that cannot catch most of what its name claims, and an "every accessor" test that does not cover the accessors added in this phase.

## Critical Issues

### CR-01: A claimed burst is lost, with no owner alert, when the front desk run fails or times out [slice A, was CR-A01]

**File:** `app/Jobs/ProcessInbound.php:55-74` (with `app/Ai/RunFrontDeskAgent.php:44-62`, `app/Actions/RunFrontDesk.php:79-180`)
**Issue:** `handle()` claims every unhandled customer text (sets `handled_at`) and only then runs `RunFrontDesk`. If `RunFrontDesk` throws anything other than `NoActiveVersion`, the job fails, and a retry calls `ClaimInboundBurst` again, finds nothing, and returns `false`. The customer's text is permanently marked handled, never answered, and the owner is never told. The class docblock on `RunFrontDesk` promises "This never throws ... so the owner is alerted instead (AGNT-04)", but that holds only for `FrontDeskUnavailable` and `DomainException` from the sender. Unguarded paths include:
- `OutboundSender::deliver()` DB failures (non-`DomainException`), `recordFailure()` DB failure inside `transmit()`, `storeDraft()` transaction failures, `RaiseOwnerAlert` create failures, `FixedReplies` or `AgentFile::fromArray` errors (`TypeError`, undefined key).
- A worker timeout. `ProcessInbound` sets no `$timeout`, `$tries` or `failed()`. The provider chain allows `gate.agent.timeout_seconds` = 40 per provider, so two slow providers plus the Jev call (8 s) exceed the default `queue:work --timeout=60`. The worker kills the job after the claim. With `retry_after` = 90 a second worker can also pick the job up while the first still runs, and that run finds nothing to claim.

**Fix:** Make the claim recoverable and make failure loud. For example:
```php
public int $tries = 1;
public int $timeout = 300; // above the worst-case provider chain + Jev

public function failed(?\Throwable $exception): void
{
    // Un-claim so the sweep or a retry can answer, or alert the owner at minimum.
    Message::query()
        ->where('conversation_id', $this->conversationId)
        ->whereNotNull('handled_at')
        ->where('direction', MessageDirection::In)
        ->where('author', MessageAuthor::Customer)
        ->whereNull('answered_at') // or alert instead of un-claim
        ->update(['handled_at' => null]);

    app(RaiseOwnerAlert::class)->handle(
        Conversation::findOrFail($this->conversationId),
        AlertReason::FrontDeskFailed,
        null,
        'Front desk crashed on a customer text',
    );
}
```
Alternatively wrap the `RunFrontDesk` call in `try/catch (Throwable)` that reports and raises a `FrontDeskFailed` alert for `$burst->last()`, mirroring the `FrontDeskUnavailable` path. Also set the queue `retry_after` above the job `$timeout`.
### CR-02: Business Folder page crashes while a Phase 1 version is live [slice B, was CR-B01]

**File:** `app/Filament/Pages/BusinessFolder.php:132`
**Issue:** `alertWarning()` reads `app(ActiveConfig::class)->sendingRules()['owner_alert']['channels']` and only catches `NoActiveVersion`. In Phase 1 `owner_alert` was a free-text string (`git show 90038239:examples/example-hvac/rules/sending.md` shows `owner_alert: Text the owner when a draft is waiting ...`). Any database that still has a Phase 1 version active after the Phase 2 deploy (the migration `000002` comment says such data exists) gets `"some text"['channels']`, which throws a `TypeError`. `alertWarning()` runs in the first `@php` block of `business-folder.blade.php:6`, so the whole page returns a 500, including the Sync Now button that would replace the old version. The only way out is the CLI (`php artisan icm:sync`).
**Fix:**
```php
try {
    $alert = app(ActiveConfig::class)->sendingRules()['owner_alert'] ?? null;
} catch (NoActiveVersion) {
    return null;
}

$channels = is_array($alert) ? ($alert['channels'] ?? []) : [];

if (! is_array($alert) || ! in_array('text', $channels, true) || User::query()->whereNotNull('phone')->exists()) {
    return null;
}
```
Also add a test that renders the page with a compiled version whose `owner_alert` is a string. The same string-or-array shape should be checked by the other readers of `owner_alert` (slice A).

## Warnings

### WR-01: A live folder with no SMS agent file drops customer texts silently [slice A, was WR-A01]

**File:** `app/Actions/RunFrontDesk.php:108-112`
**Issue:** `AgentFile::forChannel()` returns null when the live version has no agent for `sms`. `handle()` then `return`s with the burst already claimed, no timeline note and no owner alert. `Validator` only prevents two agents for one channel (lines 623-643); it does not require one. Unlike `NoActiveVersion` (documented, Pitfall 7), this is a live, valid folder that quietly stops answering customers.
**Fix:** Treat it like the unavailable case:
```php
if ($agent === null) {
    $this->noteThatNoModelAnswered($conversation, $burst);
    $this->raiseOwnerAlert->handle($conversation, AlertReason::FrontDeskFailed, $burst->last(), 'No front desk agent is set up for texts');

    return;
}
```
(or make the Validator require an `sms` agent).

### WR-02: `DispatchInbound` marks a message dispatched before the job is pushed, so a failed push strands it forever [slice A, was WR-A02]

**File:** `app/Actions/DispatchInbound.php:28-39`
**Issue:** `dispatched_at` is set first and `ProcessInbound::dispatch(...)` runs after. If the push throws (queue backend down, process killed between the two statements), `dispatched_at` stays set and `SweepUndispatched` (which selects `whereNull('dispatched_at')`) never retries it. The class docs and `RecordInbound` ("a crash after commit ... queues it then") rely on the sweep as the recovery, but it cannot recover this window.
**Fix:** Undo the claim when the push fails, and give the sweep a staleness path:
```php
try {
    ProcessInbound::dispatch($message->id, $message->conversation_id)->afterCommit();
} catch (\Throwable $e) {
    Message::query()->whereKey($message->id)->update(['dispatched_at' => null]);
    throw $e;
}
```
Optionally also sweep rows with `dispatched_at` older than N minutes and `handled_at` still null.

### WR-03: Owner alerts can be texted twice (non-atomic send claim) [slice A, was WR-A03]

**File:** `app/Actions/SendOwnerAlert.php:39-70`, `app/Jobs/SendDueAlerts.php:34-48`, `app/Actions/RaiseOwnerAlert.php:70-72`, `routes/console.php:14`
**Issue:** The "already ended" guard reads in-memory state (`sent_at`/`skipped_at`/`failed_at`), then calls the channel, then writes `sent_at`. There is no atomic claim, so two concurrent callers both send. Realistic overlaps: (1) `RaiseOwnerAlert` sends immediately while the every-minute `SendDueAlerts` sweep selects the same just-created row (a slow Twilio call easily spans the minute boundary); (2) two `SendDueAlerts` runs, because `Schedule::job(...)->withoutOverlapping()` only guards the dispatch, not the queued job, so a backlog lets runs overlap; (3) the channel accepts the text but the `sent_at` update fails (swallowed at lines 63-67), so the next sweep sends it again. The class docblock says "calling this twice cannot text twice", which is not true under concurrency.
**Fix:** Claim before sending with a conditional update, e.g. add a nullable `sending_at` (or reuse `failed_at`) and only proceed when `OwnerAlert::whereKey($alert->id)->whereNull('sent_at')->whereNull('skipped_at')->whereNull('failed_at')->whereNull('sending_at')->update(['sending_at' => now()]) === 1`. Release the claim on a channel failure.

### WR-04: `RaiseOwnerAlert` de-duplication is check-then-insert with no unique index [slice A, was WR-A04]

**File:** `app/Actions/RaiseOwnerAlert.php:46-48, 115-123`; `database/migrations/2026_10_12_000004_create_owner_alerts_table.php:36`
**Issue:** "Raising the same reason for the same message again is harmless" is enforced only by `alreadyRaised()` (a `SELECT ... exists`) followed by `create`. The table has no unique key on `(conversation_id, message_id, reason, user_id)`, so two concurrent runs (job retry overlapping a `retry_after` redelivery, the immediate life-safety job racing `ProcessInbound::answerDanger`) both pass the check and both text the owner. A null `message_id` or `user_id` would also defeat a plain unique index on most databases (NULLs are distinct), so the index must account for that.
**Fix:** Add a unique index (using a generated/coalesced column or a sentinel such as `0` for "none") and catch `UniqueConstraintViolationException` in `handle()` to skip. Combine with the claim in WR-03.

### WR-05: Life-safety job failures and lock timeouts end silently, and the customer then gets a "holding" text [slice A, was WR-A05]

**File:** `app/Jobs/RespondToLifeSafety.php:24-43`, `app/Actions/SendLifeSafetyLine.php:56-60, 94-98`, `app/Actions/RunFrontDesk.php:136-138, 367-369`
**Issue:** `SendLifeSafetyLine::answer()` catches only `DomainException`. Any other exception from the send (DB error) skips the pause and the owner alert, and `RespondToLifeSafety` has `$tries = 3` but no `failed()` hook, so after the last retry nothing tells the owner a customer in danger got no answer. `handle()` also returns `null` on `LockTimeoutException` (10 s) with no alert and no pause. In `RunFrontDesk` the classifier-triggered path ignores that `null`: if the line was not sent and the conversation is not paused, `storeDraft()` goes on to send the generic "owner will answer" holding reply to a person who just described a fire or gas leak.
**Fix:** Add `failed()` to `RespondToLifeSafety` that raises `AlertReason::LifeSafety` with "Safety message could not be sent". In `SendLifeSafetyLine::handle()`, on lock timeout raise the owner alert (the alert is idempotent). In `RunFrontDesk`, skip `holding()` when `$verdict->lifeSafety` is true.

### WR-06: Life-safety keyword lists miss common phrasings (and over-trigger on ordinary repair calls) [slice A, was WR-A06]

**File:** `app/SendRule/LifeSafety.php:24-84`
**Issue:** This is the only code-enforced layer for the safety line (the Jev layer runs only for replies that pass the label, length and code checks, so a customer text whose reply is vetoed is never checked by Jev). Lists are phrase-exact. Misses: "gas is leaking", "gas leaking", "smells of gas", "smell of propane", "gas odor", "electrical fire", "the house is burning", "burnt smell". False positives: `WATER_WORDS` ("water", "leak", "wet") plus `ELECTRICAL_WORDS` ("appliance", "appliances", "panel") means "my dishwasher appliance is leaking" or "water heater panel" triggers the "call 911" line and pauses the conversation, which is a routine plumbing text. The docblock says plain flooding must not trigger it, but "leaking appliance" is the commonest case.
**Fix:** Add the missed gas/fire phrasings (consider matching `gas` within a few words of `leak|leaking|smell|smells|odor|odour`), and narrow the water-electrical pairing (drop bare "appliance(s)" and "panel", or require "electrical panel", "breaker", "outlet", "socket", "wiring"). Add tests for each added phrase.

### WR-07: A form that describes a danger gets neither a safety line nor an owner alert [slice A, was WR-A07]

**File:** `app/Actions/RecordInbound.php:109-122`, `app/Jobs/ProcessInbound.php:63-65`
**Issue:** `respondToDanger()` returns early for non-SMS messages, and a web-only burst is claimed and dropped by `ProcessInbound`. A lead form saying "I smell gas in the basement" is therefore recorded only on the dashboard: no alert reaches the owner. The product decision (D-41) is that forms are not answered by text, but the owner alert for a danger is a different matter and `RaiseOwnerAlert` already exists with `AlertReason::LifeSafety`.
**Fix:** In `respondToDanger()` (or `ProcessInbound` for web-only bursts) call `RaiseOwnerAlert` with `AlertReason::LifeSafety` and "Web form describes a danger" when `LifeSafety::hazardIn($message->body)` matches; do not send text.

### WR-08: The owner-alert link scrubber is an allow-list of top-level domains [slice A, was WR-A08]

**File:** `app/Actions/OwnerAlertText.php:41, 95`
**Issue:** A bare domain is replaced only if it ends in one of ~24 listed endings (`com|net|org|...`) or is followed by `/`. `evil.de`, `evil.ru`, `evil.tk`, `evil.cc`, `evil.ai`, `evil.xyz`-style names outside the list, and any other TLD written without a path, survive into the alert and are tappable on a phone. The class exists specifically to stop a customer forging a tappable "alert" (Pitfall 16, D-21); the allow-list leaves an easy bypass.
**Fix:** Replace the TLD allow-list with a general rule, accepting a few false positives (the text is only a 120-character preview):
```php
$text = (string) preg_replace('~\b(?:[a-z0-9-]+\.)+[a-z]{2,}\b~iu', '[link]', $text);
```
Also fold fullwidth forms with `Normalizer::normalize($text, Normalizer::FORM_KC)` before scrubbing, and scrub `tel:`/`sms:`/`mailto:` schemes.

### WR-09: `gate:export` reports failed and queued sends as "what went out", and `original_text` differs by path [slice A, was WR-A09]

**File:** `app/Console/Commands/GateExport.php:86-110`
**Issue:** `final_text` is null only for `Draft` and `Withdrawn`. A message with status `Failed` (channel refused it, never reached the customer) or `Queued` is exported with `final_text` = its body, contradicting the docblock ("The final text is what went out"). `original_text` is `original_body ?? body`; for auto-sent and unedited-approved messages `body` is the composed text (disclosure and sign-off included), while for an edited draft `original_body` is the bare agent text, so the same column carries two different things. This is the labelled data thresholds will be tuned from.
**Fix:** `$finalText = in_array($message->status, [Sent, Delivered], true) ? $message->body : null;` and take the original from `$gate['inputs']['agent_reply'] ?? ...` (the agent's own words, always stored in the verdict) so every row is consistent.

### WR-10: The code-level price and discount veto has easy bypasses [slice A, was WR-A10]

**File:** `app/SendRule/CodeChecks.php:25, 41-53`
**Issue:** CLAUDE.md says sending authority is enforced in code. The hard veto matches `$` amounts, digits followed by "dollars/bucks", and a digit within three words of a price word. It does not catch spelled-out amounts ("two hundred dollars", "fifty bucks", "a hundred and fifty"), and `DISCOUNT_PHRASES` has "for free" but not "free estimate", "free quote", "complimentary", "on the house", "no cost". An agent that writes those words reaches Jev with nothing but its probability threshold between the text and the customer.
**Fix:** Add number words (one through ninety, hundred, thousand) as a price amount pattern, and add `free`, `complimentary`, `on the house`, `no cost` to the discount list with word-boundary matching. Add tests for each.

### WR-11: Unauthenticated request with a nested array field turns a 403 into a 500 [slice B, was WR-B01]

**File:** `app/Channels/TwilioSignature.php:31`
**Issue:** `compute()` runs on attacker-controlled form data before the signature is compared. For a field like `a[b][c]=1`, `$value` is an array containing an array, and `array_map(strval(...), $value)` hits "Array to string conversion". Laravel turns that warning into an `ErrorException`, so the middleware answers 500 (and reports the exception) instead of the intended 403. Anyone can generate error noise this way. Twilio never sends nested arrays.
**Fix:** In `matches()`, return false when any value is not a string or a flat list of strings, before computing:
```php
foreach ($params as $value) {
    if (is_array($value) && array_filter($value, fn ($v) => ! is_scalar($v)) !== []) {
        return false;
    }
}
```

### WR-12: Empty `MessageSid` with a `SmsSid` yields an empty provider message id [slice B, was WR-B02]

**File:** `app/Channels/TwilioTextChannel.php:99`
**Issue:** Because `bootstrap/app.php` disables `ConvertEmptyStringsToNull` for the Twilio routes, a blank `MessageSid=` arrives as `''`, not `null`. `$request->input('MessageSid') ?? $request->input('SmsSid')` keeps the `''` (`??` only skips null). The request validator accepts it (`required_without:SmsSid` is satisfied by `SmsSid`). The text is then stored with `provider_message_id = ''`, and the `(provider, provider_message_id)` unique index makes the next such text collide as a duplicate, so it is silently dropped.
**Fix:**
```php
providerMessageId: (string) ($this->nonEmpty($request->input('MessageSid')) ?? $request->input('SmsSid')),
```
or use `?:` and make the request rule require one non-empty id (`'MessageSid' => ['required_without:SmsSid', ...]` plus an `after()` check that the resolved id is not `''`).

### WR-13: Sync Now only catches `IcmFolderMissing`; invalid UTF-8 or a DB error becomes a 500 [slice B, was WR-B03]

**File:** `app/Filament/Pages/BusinessFolder.php:184`
**Issue:** `Loader::read()` runs `Yaml::parse` only on the front matter, so a body (or a router file such as `CLAUDE.md`, whose raw text is stored untouched) containing invalid UTF-8 (for example a Windows-1252 smart quote saved by Notepad) passes validation. `IcmVersion::create()` then casts `compiled` to JSON, which throws `JsonEncodingException`. `FolderSync::sync()` has no handler (the lock is released by `finally`, but the exception propagates), and `runSync()` only catches `IcmFolderMissing`. The owner clicking Sync Now sees a server error instead of a plain-words message, and `icm:sync` crashes the same way.
**Fix:** In `Loader::read()` (or `Validator`), treat a file whose text fails `mb_check_encoding($raw, 'UTF-8')` as unrecognized with a plain message ("the file must be saved as UTF-8"). In `runSync()`, also catch `Throwable`, `report($e)`, and show a generic danger notification.

### WR-14: `ConversationThread::$conversation` is a public, unlocked model property [slice B, was WR-B04]

**File:** `app/Livewire/ConversationThread.php:22`
**Issue:** `draftId` is `#[Locked]`, but the public `Conversation $conversation` is not. Livewire lets the browser set attributes on a public model property (for example `$wire.set('conversation.contact_id', 7)`), and the component passes that hydrated, tampered model to `SendOwnerReply`, `ResolveDraft::approve()` and the `$this->conversation->messages()` lookups. The same comment that justifies locking `draftId` ("so the browser cannot point it at another message") applies here. The risk is small in a single-owner app, but an owner session is one XSS away from sending texts to arbitrary contacts' threads.
**Fix:** Mark it `#[Locked] public Conversation $conversation;`, or store only the id (`#[Locked] public int $conversationId`) and resolve the model in a computed property.

### WR-15: The unauthenticated fake webhook is on by default in every environment [slice B, was WR-B05]

**File:** `routes/web.php:18`
**Issue:** `EnsureTextDriverIsFake` only checks `config('channels.text.driver') === 'fake'`, and the driver defaults to `fake` when `TEXT_CHANNEL` is unset. A production deploy that forgets `TEXT_CHANNEL=twilio` exposes `POST /webhooks/text/fake` with no signature, CSRF (exempted by `webhooks/*`) or rate limit. Anyone can post `From=<a customer's number>&Body=STOP` to opt that customer out, or inject texts that run the (paid) front desk agent. The middleware's own docblock says the route exists "only while the fake driver is on", but nothing ties "fake" to a non-production environment.
**Fix:** Require a non-production environment as well, for example `abort_unless(config('channels.text.driver') === 'fake' && ! app()->environment('production'), 404)`, and add `throttle` to the route.

### WR-16: The test run is not isolated from `.env`; the documented go-live step breaks the suite [slice C, was WR-C01]

**File:** `tests/Pest.php:34-64` (also `phpunit.xml`, `tests/Feature/SendRule/JevClassifierTest.php:162-165`)
**Issue:** The shared `beforeEach` pins the folder path, HTTP, the classifier, the agent and the queue, but not the text channel or the provider keys. `config/channels.php` reads `env('TEXT_CHANNEL', 'fake')`, `config/services.php` reads `TWILIO_*` and `JEV_*`, and `phpunit.xml` sets none of them, so `.env` values flow into the run (Laravel loads `.env` when no `.env.testing` exists). `docs/SETUP.md` section 11 and sprint-1 "Going live on Twilio" tell the owner to put `TEXT_CHANNEL=twilio` and the five `TWILIO_*` keys in `.env`. From that moment `php artisan test` fails broadly: `webhooks.text.fake` answers 404 for every test that does not set the driver itself (FakeTextWebhookTest, ContactMatchingTest, every FrontDesk test through `frontDeskCustomerTexts()`), and `JevClassifierTest` "pins the model from the environment setting" asserts `config('services.jev.model')` equals the default, so a `JEV_MODEL` override in `.env` fails it. A suite that goes red when the app is configured for production, and that can read live-looking credentials, is a reliability and safety problem for the tests-first rule in CLAUDE.md.
**Fix:** Pin the environment in the shared `beforeEach` (or as `<env>` lines in `phpunit.xml`):
```php
config([
    'channels.text.driver' => 'fake',
    'services.twilio.account_sid' => null,
    'services.twilio.auth_token' => null,
    'services.twilio.messaging_service_sid' => null,
    'services.twilio.webhook_url' => null,
    'services.twilio.status_callback_url' => null,
    'services.jev.key' => null,
    'services.jev.model' => 'typesafe/jev-1.13',
    'services.jev.base_url' => 'https://openrouter.ai/api/v1',
]);
```
Tests that need Twilio already set their own values, so nothing else changes.

### WR-17: The reply-cap "does not count" test cannot detect most of what it claims [slice C, was WR-C02]

**File:** `tests/Feature/FrontDesk/ReplyCapTest.php:121-133`
**Issue:** The test is named "does not count texts older than 24 hours, drafts, failed sends or the safety line (D-52)". `RunFrontDesk::replyCapReached()` (`app/Actions/RunFrontDesk.php:233-243`) filters on direction, author, a status list (Queued/Sent/Delivered), the not-life-safety kind, and two time windows against caps of 6 per hour and 20 per day. The setup is eight texts older than 24 hours, seven life-safety texts, one draft and one failed send. Only the life-safety group (7 within the hour, above the cap of 6) can trip a cap if the filter broke. Eight old texts are below the daily cap of 20, so removing or widening the 24-hour bound passes. One draft plus one failed send is two rows, below both caps, so deleting the status filter passes. A regression that starts counting drafts, failed sends or stale texts toward the daily cap would stay green.
**Fix:** Size each excluded group to trip the cap on its own, one group per case (a dataset), so each fails independently:
```php
// each case: enough excluded rows to reach the cap if they were wrongly counted
'older than 24h (daily cap)' => fn ($c) => replyCapAgentTextsSent($c, array_map(fn ($i) => 1500 + $i * 10, range(0, 19))), // 20 rows
'drafts'                     => fn ($c) => Message::factory()->for($c)->draft()->count(1)->create(), // see note
'failed sends (hourly cap)'  => fn ($c) => /* 6 outbound rows with status failed inside the hour */,
```
Note a draft cannot be repeated (one live draft per conversation index); use withdrawn rows and queued-then-failed rows for that group, or assert the status filter through a unit test on the query. Add a boundary row for a text 61 minutes old against the hourly window.

### WR-18: `toContain('fi-collapsed')` is always true, so "collapses the customer details section" is not tested [slice C, was WR-C03]

**File:** `tests/Feature/Filament/ConversationViewTest.php:64-78` (assertion at line 77)
**Issue:** The test claims the Customer section is collapsed on first render, then asserts `$html` contains the string `fi-collapsed`. I rendered the conversation view for a conversation with no draft at all: `fi-collapsed` occurs three times in the page regardless: twice from the sidebar chrome (`x-bind:class="{ 'fi-collapsed': ... }"` and `group.classList.add('fi-collapsed')`) and once from Filament's section template, `x-bind:class="isCollapsed && 'fi-collapsed'"`, which is emitted for every collapsible section whether or not it starts collapsed. Removing `->collapsed()` at `app/Filament/Resources/Conversations/Schemas/ConversationInfolist.php:36` would not fail this assertion. The earlier ordering assertions (draft box before the Customer heading) are real; only the collapse claim is vacuous.
**Fix:** Assert the server-rendered initial state, which Filament writes into the section's `x-data`:
```php
expect($html)->toMatch('/isCollapsed:\s*true/');
```
(or scope the match to the Customer section's wrapper, and add a counterpart showing a non-collapsed section renders `isCollapsed: false`).

### WR-19: "throws NoActiveVersion from every accessor" omits the accessors this phase added [slice C, was WR-C04]

**File:** `tests/Feature/Icm/ActiveConfigTest.php:7-23`
**Issue:** The dataset lists `version`, `timezone`, `business`, `hours`, `leadSources`, `leadSource`, `services`, `service`, `voice`, `sendingRules`, `files`, `file`. `ActiveConfig` now also has `versionId()`, `replies()`, `triage()` and `agent()` (all of which go through `version()` and are on the front desk's hot path: `RecordIntakeAnswers`, `FixedReplies`, `TriageQuestions::fromConfig`, `AgentFile::forChannel`). `ProcessInbound` relies on `NoActiveVersion` being thrown to "claim and leave quietly" before a folder is live (`ProcessInbound.php:69-71`), and `displayTimezone()` is documented as the one accessor that must not throw; neither contract is pinned by this test (the latter only indirectly via a customer-card test). If one of the new accessors stopped throwing (for example returned null or an empty array), the "no live folder" paths would misbehave silently and this test, by its name, would say they are covered.
**Fix:** Add rows `'versionId' => ['versionId', []]`, `'replies' => ['replies', []]`, `'triage' => ['triage', []]`, `'agent' => ['agent', ['sms']]`, and a separate test that `displayTimezone()` returns `config('app.timezone')` before a sync instead of throwing.

## Info

### IN-01: `HandBackConversation::handle()` accepts `$by` and never uses it [slice A, was IN-A01]

**File:** `app/Actions/HandBackConversation.php:31`
**Issue:** The timeline entry does not record who handed the conversation back, unlike `PauseConversation` which stores `paused_by`.
**Fix:** Either record the user in the activity summary or meta, or drop the parameter.

### IN-02: Unreachable branch in `RunFrontDesk` [slice A, was IN-A02]

**File:** `app/Actions/RunFrontDesk.php:138`
**Issue:** `$verdict->outcome === GateOutcome::Send ? $verdict->heldFor(...) : $verdict`. `TriageReading::read()` always adds `classifier:life_safety` to `$reasons` when `lifeSafety` is true, so a Send verdict can never have `lifeSafety`. The true branch is dead.
**Fix:** Remove the conditional or keep it as a documented defensive guard.

### IN-03: Test double lives in production code [slice A, was IN-A03]

**File:** `app/SendRule/FakeClassifier.php`
**Issue:** `FakeClassifier` reads every reply as sendable by default and sits in `app/`, one wrong container binding away from approving everything. It is bound only in `tests/Pest.php` today.
**Fix:** Move it under `tests/Support` (autoloaded dev-only), or assert in `AppServiceProvider` that `Classifier` resolves to `JevClassifier` outside the testing environment.

### IN-04: A burst with a danger text that is not the newest alerts the owner twice [slice A, was IN-A04]

**File:** `app/Actions/RunFrontDesk.php:189-198, 219-226`
**Issue:** `answerDanger()` raises the life-safety alert on the hazardous message, pauses, and `alertAboutPausedBurst()` then checks for an alert on `$burst->last()` only. If the hazard text is not the last in the burst, the owner gets a second, redundant `PausedMessage` alert for the same burst.
**Fix:** Check `OwnerAlert::whereIn('message_id', $burst->pluck('id'))->exists()`.

### IN-05: `ProcessInbound::$messageId` is never read [slice A, was IN-A05]

**File:** `app/Jobs/ProcessInbound.php:31`
**Issue:** The job ignores `$messageId` (it claims by conversation). Harmless, but it implies a per-message contract the job does not honour.
**Fix:** Remove it or document it as diagnostic only.

### IN-06: Opt-out error code defined twice with different types [slice B, was IN-B01]

**File:** `app/Channels/TwilioTextChannel.php:15` and `app/Http/Controllers/TwilioStatusCallbackController.php:19`
**Issue:** `OPTED_OUT_CODE` is `int 21610` in the channel and `string '21610'` in the controller; the channel casts it back to string to compare. Two copies can drift.
**Fix:** Keep one public constant (for example on `TwilioTextChannel`) and reference it from the controller.

### IN-07: Notification titles are escaped inconsistently [slice B, was IN-B02]

**File:** `app/Filament/Pages/BusinessFolder.php:187` and `:192`
**Issue:** Elsewhere the code wraps dynamic text in `e()` because "Filament renders a title as HTML" (`ViewConversation`, `ConversationThread`). Here `$exception->getMessage()` (contains the folder path) and `$result->message` (contains the version label) go in raw. Filament sanitizes HTML, so this is not exploitable, but it breaks the pattern the rest of the phase follows.
**Fix:** `->title(e($exception->getMessage()))` and `->title(e($result->message))`.

### IN-08: A delivery callback that beats the stored sid is dropped for good [slice B, was IN-B03]

**File:** `app/Http/Controllers/TwilioStatusCallbackController.php:56`
**Issue:** An unknown `MessageSid` is answered 200 and ignored, so Twilio does not retry. If a callback (especially a `failed` with error 21610) arrives in the short window between Twilio accepting the text and the sender saving `provider_message_id`, the status and the opt-out are lost and the message stays `sent`. The window is small but not zero.
**Fix:** Answer 404/409 for an unknown sid on a `failed`/`undelivered` status so Twilio retries, or have the sweep reconcile `sent` messages. Document the choice either way.

### IN-09: CI runs twice for every pull request branch [slice B, was IN-B04]

**File:** `.github/workflows/tests.yml:3`
**Issue:** `push` on `['**']` plus `pull_request` runs the whole sqlite and pgsql matrix twice for each push to a PR branch.
**Fix:** Limit `push` to the default branch (`branches: [main]`) or add `concurrency` with `cancel-in-progress`.

### IN-10: Pest scaffold leftovers in the shared bootstrap [slice C, was IN-C01]

**File:** `tests/Pest.php:81-83, 96-99`
**Issue:** `expect()->extend('toBeOne', ...)` and the empty `function something()` are the stock Pest installer examples; nothing uses them (and the empty function is a global name any future helper could collide with).
**Fix:** Delete both blocks.

### IN-11: The same helpers and constants are copied across a dozen files [slice C, was IN-C02]

**File:** `tests/Feature/FrontDesk/HeldDraftTest.php:34`, `HandoffTest.php:37`, `HoldingReplyTest.php:58`, `ReplyCapTest.php:52`, `LifeSafetyTest.php:47`, `TakeOverTest.php:67`, `Filament/DraftReviewTest.php:82`, `Inbound/OptOutTest.php:75`; `Inbound/FakeTextWebhookTest.php:29`
**Issue:** "texts that reached Dana's phone" is re-implemented eight times with eight names (`textsSentToDana`, `handoffTextsToDana`, ...), each beside its own copy of the owner/customer phone constants (`+12025550199`, `+12025550123`). `twilioText()` in FakeTextWebhookTest duplicates `fakeTextPayload()` from Pest.php, and four files build `'SM'.str_repeat(x, 32)` Sids by hand. `HoldingReplyTest::holdingReplyCustomerTextsAgain()` keeps a `static $count` that persists across tests in a run. Any change to the sent-text shape needs edits in every copy.
**Fix:** Add `textsSentTo(string $phone): array` and shared `OWNER_PHONE`/`CUSTOMER_PHONE` helpers to tests/Pest.php, replace `twilioText()` with `fakeTextPayload()`, and give `fakeTextPayload()` an optional `sid` letter parameter.

### IN-12: JevClassifierTest hard-codes the file count the helper exists to centralise [slice C, was IN-C03]

**File:** `tests/Feature/SendRule/JevClassifierTest.php:64`
**Issue:** `expect(exampleFolderFileCount())->toBe(11)` asserts a helper against its own literal. `tests/Pest.php:230-237` says a plan that adds a typed file "changes this one number"; this line is a second place to change and will fail for a reason unrelated to Jev.
**Fix:** Drop the `toBe(11)` assertion (the preceding `expectsOutputToContain` already compares against the helper) or assert only that `rules/triage.md` is present.

### IN-13: Several test names claim more than they assert [slice C, was IN-C04]

**File:** `tests/Feature/Icm/AgentFileTest.php:132`, `tests/Feature/Icm/ValidatorTest.php:87,100`, `tests/Feature/FrontDesk/ProcessInboundTest.php:340`, `tests/Feature/FrontDesk/HandoffTest.php:139`
**Issue:** (a) "goes live with no agent file, and says the front desk is off" asserts only that `AgentFile::forChannel` returns null; the "says off" text is tested elsewhere (BusinessFolderPageTest:151) so it is covered, but not here. (b) "requires each of the five single-instance types to exist exactly once" predates `set_replies` and `triage`, and only deletes/duplicates two types. (c) "names every missing single-instance type when the folder has no typed files" leaves agents/front-desk.md, replies.md and triage.md in place, so it is not "no typed files" and does not name the two newer types. (d) "sends one text when the queued job runs twice (AGNT-02 concurrency)" runs the job twice one after another; that proves idempotence of the claim, not a race. (e) "never leaves a customer asking for a person without alerting the owner, for each way of asking" has two datasets ('phrase', 'agent') and omits the third path the file itself tests above (Jev's `asks_for_person`).
**Fix:** Rename to what is asserted, or extend the assertions: assert the front-desk-off wording or drop it from the name; update (b)/(c) to cover all single-instance types; rename (d) to "...when the burst is claimed a second time"; add a 'jev' row to (e).

### IN-14: GateExportTest fixture drifts from the real verdict shape it says it copies [slice C, was IN-C05]

**File:** `tests/Feature/FrontDesk/GateExportTest.php:12-35`
**Issue:** The docblock says the fixture is "shaped like the one DecideSendOrDraft writes (version 1)", but `checks.length.limit` is 320 (real: 1600, see ProcessInboundTest:236) and `thresholds` is `['intent_confidence' => 0.7]` (real keys are `intent_min_confidence`, `gate_check_max_yes`, `life_safety_min_yes`, `asks_for_person_min_yes`, per TriageReadingTest). The export copies the verdict verbatim today, so nothing breaks, but if `GateExport` ever reads a real key the fixture will hide a mismatch.
**Fix:** Build the fixture from `config('gate.classifier')` and the real limit, or generate one verdict through the real flow in a single end-to-end export test.

### IN-15: sprint-1.md acceptance criterion 3 contradicts the map on `escalate_to` [slice C, was IN-C06]

**File:** `docs/sprints/sprint-1.md:28-32`
**Issue:** Criterion 3 says a customer who asks for a person "is handed to the `escalate_to` contact" and lists it as delivered by plans 02-19/02-20. `map/objects/folder/agent-file-and-version.md` (lines 25-26, checked against `AgentFile.php`, `Validator.php:212` and `RunFrontDesk.php:252`) shows `escalate_to` is stored and never read; the hand-off is hard-wired to the owner. The behaviour is right for today (only `owner` is allowed) but the acceptance text overclaims.
**Fix:** Reword to "handed to the owner" and add "`escalate_to` routing (staff users) is parked for Phase 2.1", matching the map.

### IN-16: CLAUDE.md map table omits several app/ folders a new reader will hit first [slice C, was IN-C07]

**File:** `CLAUDE.md:30-41`
**Issue:** The table lists `app/Icm`, `Channels`, `Actions`, `Ai`, `SendRule`, `Filament` but not `app/Records` (the customer-card and records writer, which writes into the business folder and has its own arch rule), `app/Jobs`, `app/Livewire`, `app/Enums`. Since this table is the "read this first" map, and the new `map/CLAUDE.md` row says its nouns include the customer card, a pointer to `app/Records/` is missing.
**Fix:** Add one-line rows for `app/Records/` (customer card, records writer) and `app/Jobs/` + `app/Livewire/`.

### IN-17: triage.md tells the owner a position that the code makes irrelevant [slice C, was IN-C08]

**File:** `examples/example-hvac/rules/triage.md:36`
**Issue:** The body says "`other` is listed first on purpose: the checker leans toward the first option when it is unsure". The sync accepts `other` anywhere (`TriageValidationTest` "accepts other listed last") and `TriageQuestions::fromConfig` moves it first (JevClassifierTest "still sends other first when the owner lists it last"). The owner text reads as a rule they must keep; it is not.
**Fix:** Say "`other` is always shown to the checker first, wherever you list it".

---

_Reviewed: 2026-10-12T13:10:55Z_
_Reviewer: Claude (gsd-code-reviewer, three slices merged by the execute-phase orchestrator)_
_Depth: standard_
