---
phase: 02-text-front-desk
reviewed: 2026-10-12T00:00:00Z
depth: standard
files_reviewed: 31
files_reviewed_list:
  - app/Actions/DispatchInbound.php
  - app/Actions/RaiseOwnerAlert.php
  - app/Actions/ReportFrontDeskFailure.php
  - app/Actions/RunFrontDesk.php
  - app/Actions/SendLifeSafetyLine.php
  - app/Ai/RunFrontDeskAgent.php
  - app/Filament/Pages/BusinessFolder.php
  - app/Jobs/ProcessInbound.php
  - app/Jobs/RespondToLifeSafety.php
  - app/SendRule/LifeSafety.php
  - config/gate.php
  - config/queue.php
  - docs/sprints/sprint-1.md
  - map/CLAUDE.md
  - map/objects/contacts/contact-opt-out.md
  - map/objects/conversations/conversation-and-pause.md
  - map/objects/folder/agent-file-and-version.md
  - map/objects/intake/intake-answer.md
  - map/objects/messages/message-and-verdict.md
  - map/processes/answer-inbound.md
  - map/VERIFICATION.md
  - phpunit.xml
  - tests/Feature/Alerts/OwnerAlertTimingTest.php
  - tests/Feature/Filament/BusinessFolderPageTest.php
  - tests/Feature/FrontDesk/AgentFallbackTest.php
  - tests/Feature/FrontDesk/FrontDeskFailureTest.php
  - tests/Feature/FrontDesk/LifeSafetyTest.php
  - tests/Feature/Inbound/DispatchInboundTest.php
  - tests/Feature/Quality/TestSafetyNetTest.php
  - tests/Pest.php
  - tests/Unit/SendRule/LifeSafetyTest.php
findings:
  critical: 1
  warning: 6
  info: 5
  total: 12
status: issues_found
---

# Phase 02: Code Review Report

**Reviewed:** 2026-10-12
**Depth:** standard
**Files Reviewed:** 31
**Status:** issues_found

## Summary

The change set hardens the front desk: failure reporting (`ReportFrontDeskFailure`, `ProcessInbound::failed()`), the safety-line paths (`SendLifeSafetyLine`, `RespondToLifeSafety`), the agent fallback chain, claim rollback in `DispatchInbound`, and queue timing. The structure is sound and the map documents match the code (line references spot-checked against `ProcessInbound`, `RunFrontDesk`, `SendLifeSafetyLine`, `config/gate.php`). The weak spots are in the safety path. A retry after partial completion silently drops the pause and the owner alert. The keyword list misses common phrasings. A validated utility phone number is dropped in common formats. `failed()` reports against the wrong text.

Some findings below were confirmed by running the code (`LifeSafety::hazardIn()` and `::line()` via `php -r`) and are marked "verified".

No fallow structural findings were supplied.

## Critical Issues

### CR-01: A retry after the safety line was sent finds it "already answered" and skips the pause and the owner alert

**File:** `app/Actions/SendLifeSafetyLine.php:82-84` (with `app/Jobs/RespondToLifeSafety.php:29,39-48`)
**Issue:** The class promises "The line is sent, or the owner is told; never neither." `answer()` sends the line (`:91`) and only then pauses (`:109`) and alerts (`:110`). If the process dies, or `PauseConversation` or `RaiseOwnerAlert` throws (a DB error), after the send succeeded, the run fails with the line already stored as `sent`.

- `RespondToLifeSafety` has `$tries = 3`, so the next attempt calls `answer()`. `alreadyAnswered()` sees the sent line and returns `null` immediately. The attempt succeeds as a no-op, so `failed()` never runs.
- The ProcessInbound second look (`RunFrontDesk::answerDanger`) takes the same early return.

Result: the customer got the line, but the conversation is never paused and the owner is never told. The AI then carries on answering a gas-leak conversation. The tests only cover the send-failed path and the lock-timeout path, not this one.
**Fix:** Make the early return depend on the whole outcome, not only on the send. Either do the pause and alert in `alreadyAnswered` mode too (both are idempotent):

```php
if ($this->alreadyAnswered($conversation, $inbound)) {
    $this->pauseConversation->handle($conversation, PausedReason::LifeSafety);
    $this->raiseOwnerAlert->handle($conversation, AlertReason::LifeSafety, $inbound, 'Safety message sent');

    return null;
}
```

Or run pause and alert in a `finally` right after the send attempt, and add a test where `PauseConversation` throws once after a successful send.

## Warnings

### WR-01: The keyword net misses common ways of describing the same dangers (verified)

**File:** `app/SendRule/LifeSafety.php:24-80`
**Issue:** The class says "when a text could mean danger the line is sent" and the keyword layer is the only guaranteed one. The Jev classifier is a fallback, and `RunFrontDesk` only reaches it after the agent has run. Running `hazardIn()` returns `null` for all of these:

- "I am smelling gas", "I smelled gas", "it smells gas in here"
- "gas is hissing from the stove"
- "something is burning", "smells like something burning", "the furnace smells burning"
- "my son got electrocuted by the outlet", "got a shock from the panel"

The lists only cover `smell`, `smells like`, `smell of` and a few more fixed forms.
**Fix:** Add inflections and nearby wordings (`smelling gas`, `smelled gas`, `smells gas`, `gas hissing`, `hissing gas`, `something burning`, `smells burning`, `electrocuted`, `electric shock`, `got shocked`). Add each to the `hazardIn` data-provider in `tests/Unit/SendRule/LifeSafetyTest.php`. Treat the lists as the owner-safety floor, not a best effort.

### WR-02: A utility phone the sync accepts is silently dropped from the gas sentence (verified)

**File:** `app/SendRule/LifeSafety.php:157`
**Issue:** The sync validator accepts any number `PhoneNumber::toE164()` parses with the configured region (`app/Icm/Validator.php:738`), so `(312) 555-0188` and `312-555-0188` pass. `gasLine()` requires `^\+?[0-9]`, so a number that starts with `(` fails the regex. `LifeSafety::line('gas', '(312) 555-0188')` returns the sentence with no gas-company line, and nothing tells the owner. The unit tests only use `+13125550188`. `utilityPhone()` also drops a YAML-unquoted integer, because `is_string` fails.
**Fix:** Normalize with the same helper the validator uses and print the E.164 form:

```php
$e164 = PhoneNumber::toE164(is_scalar($utilityPhone) ? (string) $utilityPhone : null);

if ($e164 === null) {
    return self::GAS_LINE;
}

return self::GAS_LINE.' '.self::GAS_COMPANY_LINE.' '.$e164.'.';
```

Make `utilityPhone()` cast ints to strings, and add a test with `(312) 555-0188`.

### WR-03: `ProcessInbound::failed()` reports on the newest customer text, not on the burst that failed

**File:** `app/Jobs/ProcessInbound.php:146-156`
**Issue:** `failed()` takes `latest('id')` of all inbound customer messages, with no channel filter and no `handled_at` filter. Two real consequences:

- A text that arrived after the burst was claimed (it has its own queued job) gets a "Front desk stopped before answering" note and alert. `ReportFrontDeskFailure` then suppresses any later real failure report for that text.
- If `handle()` already reported `$burst->last()` and the job then times out, `failed()` reports a different message and the owner is alerted twice. The dedup in `ReportFrontDeskFailure` is keyed on `message_id`, and the ids differ.

A web-form message would also be reported as a front-desk failure.
**Fix:** Report on the claimed burst's newest SMS text: pass or store the burst, or query:

```php
$text = $conversation->messages()
    ->where('direction', MessageDirection::In)
    ->where('author', MessageAuthor::Customer)
    ->where('channel', MessageChannel::Sms)
    ->whereNotNull('handled_at')
    ->latest('id')
    ->first();
```

Even better, key the dedup in `ReportFrontDeskFailure` on conversation plus a window, or carry `$this->messageId` into the lookup.

### WR-04: The claim rollback in `DispatchInbound` does not cover a push deferred by `afterCommit()`

**File:** `app/Actions/DispatchInbound.php:43-49`
**Issue:** `->afterCommit()` is what makes the dispatch wait for an open transaction. Today the callers (`RecordInbound`, `SweepUndispatched`) run outside any transaction, so the push happens inside the `try`. If a caller ever wraps `handle()` in `DB::transaction`, the push runs at commit time, outside this `try`. A failed push then leaves `dispatched_at` set, the sweep (`whereNull('dispatched_at')`) never picks the text up, and it is never answered. The docblock promises that a failed push gives the claim back.
**Fix:** Either drop `afterCommit()` here, since `dispatched_at` is set before the push and nothing in this action needs the commit to have happened. Or guard the case:

```php
if (DB::transactionLevel() > 0) {
    throw new LogicException('DispatchInbound must run outside a transaction.');
}
```

Add a test that calls `handle()` inside `DB::transaction()` with the failing queue.

### WR-05: Retry and failure semantics of `RespondToLifeSafety` do not match its comments

**File:** `app/Jobs/RespondToLifeSafety.php:26-29,50-67`
**Issue:**

- "A failed run is tried again" is misleading. `SendLifeSafetyLine` now catches every `Throwable` from the send (`:103`) and returns normally, so a transient channel failure never fails the job and is never retried by it. Only a pause or alert failure fails the job, and that retry hits CR-01. The effective retry for a failed send is the ProcessInbound second look.
- `failed()` always writes "Safety message could not be sent", even when the line did go out and only the pause or alert failed.
- There is no `$timeout`, so the worker default applies to a job that can block 5 s on the reply lock and 10 s on the safety lock before talking to Twilio.

**Fix:** After fixing CR-01, adjust the docblock. Choose the `failed()` alert text from whether a sent safety line exists for the text, for example "Safety message sent, but check the conversation was paused". Set an explicit `$timeout`.

### WR-06: `LifeSafety` fails open when a regex errors (verified)

**File:** `app/SendRule/LifeSafety.php:170,182`
**Issue:** `preg_match(...) === 1` treats an error (`false`, for example invalid UTF-8 with the `/u` flag) as "no hazard". `LifeSafety::hazardIn("smoke \xff")` returns `null`. `(string) preg_replace(...)` turns a `null` error into an empty string. For the function that decides whether to send the safety line, an error should mean "assume hazard". Reachability is low (Twilio sends valid UTF-8, and Postgres rejects invalid bytes), but the public `/leads` form and the fake webhook accept any bytes.
**Fix:** Scrub the input first with `mb_convert_encoding($text, 'UTF-8', 'UTF-8')` or `mb_scrub()` before matching. Check `preg_last_error() !== PREG_NO_ERROR` and fall back to `'general'` (or a plain `stripos` pass).

## Info

### IN-01: Exception classification by message prefix

**File:** `app/Actions/RunFrontDesk.php:166`
**Issue:** `str_starts_with($exception->getMessage(), OutboundSender::NOT_SENT)` ties control flow to message text. The opt-out branch just below already uses `getPrevious()`. Any reword of `NOT_SENT` silently turns a failed send into a held draft with `send_refused`.
**Fix:** Throw a small `ChannelSendFailed extends DomainException` (and a `SendRefused` for the rest) from `OutboundSender::transmit()` and catch by type.

### IN-02: Raw provider exception text is persisted on every message verdict

**File:** `app/Ai/RunFrontDeskAgent.php:74` (stored via `app/SendRule/DecideSendOrDraft.php:61`)
**Issue:** `failed_attempts[].error` keeps 200 characters of `getMessage()` in the message's `gate` JSON. `ReportFrontDeskFailure` explicitly keeps exception text out of notes and alerts because it can hold SQL, customer words or a key. Provider errors often echo part of the request or key. This is the same data in a different place.
**Fix:** Store `class_basename($exception)` plus an HTTP status when available, and keep the message in the log only.

### IN-03: Owner-alert settings are parsed in two places, and `alertWarning()` has no type guard

**File:** `app/Filament/Pages/BusinessFolder.php:134-141`, `app/Actions/RaiseOwnerAlert.php:133-142`
**Issue:** Both read `sendingRules()['owner_alert']` and handle the Phase 1 sentence shape separately. `alertWarning()` passes `$alert['channels']` straight to `in_array`, which throws a `TypeError` (page crash) if a version ever holds a non-array `channels`.
**Fix:** Expose one `OwnerAlertSettings` reader (or make `RaiseOwnerAlert::ownerAlertSetting()` public) and cast with `(array)`.

### IN-04: `RaiseOwnerAlert` check-then-insert is not atomic, and a no-recipient alert can be deferred

**File:** `app/Actions/RaiseOwnerAlert.php:47-69`
**Issue:**

- `alreadyRaised()` followed by `create()` has no unique index behind it. Two concurrent raisers (for example the lock-timeout path of `SendLifeSafetyLine` and the run that holds the lock) can each insert and text the owner twice.
- With no recipient, the "stored as failed so the gap shows" alert is only marked failed when `SendOwnerAlert` runs. For a non-urgent reason raised after hours, that is deferred until the next opening.

**Fix:** Add a unique index on `(conversation_id, message_id, reason, user_id)` (note nullable columns on SQLite and Postgres) or use `firstOrCreate` inside a lock. Send the no-recipient alert immediately regardless of `due_at`.

### IN-05: Test scaffolding leftovers and a narrow queue guard

**File:** `tests/Pest.php` (`something()`), `config/queue.php:52,43`, `tests/Feature/FrontDesk/FrontDeskFailureTest.php` (timeout test)
**Issue:**

- `tests/Pest.php` still defines the scaffold `function something() { // .. }`, which is dead code.
- The new `retry_after` guard and its test cover only `database` and `redis`. `beanstalkd` stays at 90 s, below the 300 s `ProcessInbound` timeout. It is unused today but is the same hazard if the connection is switched.

**Fix:** Delete `something()`. Raise the `beanstalkd` default (and note the SQS visibility timeout) or add a test that every in-use connection's `retry_after` exceeds 300.

---

_Reviewed: 2026-10-12_
_Reviewer: Claude (gsd-code-reviewer)_
_Depth: standard_
