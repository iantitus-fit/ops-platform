---
phase: 02-text-front-desk
fixed_at: 2026-10-12T00:00:00Z
review_path: .planning/phases/02-text-front-desk/02-REVIEW.md
iteration: 1
findings_in_scope: 7
fixed: 6
partly_fixed: 1
skipped: 0
status: partial
---

# Phase 02: Code Review Fix Report

**Fixed at:** 2026-10-12
**Source review:** .planning/phases/02-text-front-desk/02-REVIEW.md
**Iteration:** 1

**Summary:**
- Findings in scope: 7 (CR-01, WR-01 to WR-06; Info findings out of scope)
- Fixed: 6
- Partly fixed: 1 (WR-03; the rest is deferred to Phase 02.1)
- Skipped: 0

**Verification:** every fix was written tests first (the new test was run and seen to fail, then the code was fixed). The narrow tests were run after each fix. The final `php artisan test --compact` run was in the main checkout (`workflow.use_worktrees` is `false`, so no worktree was made) and gave **1026 passed, 0 failed** (4227 assertions). The run before any fix gave 985 passed, 0 failed, so nothing predates or broke; the 41 extra tests are the new ones. Pint was run before each commit that touched PHP.

All commits are local on `gsd/phase-02-text-front-desk`. Nothing was pushed, merged, amended or rebased.

## Fixed Issues

### CR-01: A retry after the safety line was sent finds it "already answered" and skips the pause and the owner alert

**Files modified:** `app/Actions/SendLifeSafetyLine.php`, `tests/Feature/FrontDesk/LifeSafetyTest.php`, `map/processes/answer-inbound.md`, `map/objects/messages/message-and-verdict.md`, `map/objects/conversations/conversation-and-pause.md`
**Commits:** 2741862 (fix), 6643e49 (card wording: four pause call sites, not three)
**Status:** fixed: requires human verification (logic change)
**What changed and why it matters:** If a customer reported a gas leak, the safety text went out, and then the database hiccuped before the conversation was paused, the retry used to say "already answered" and stop. The AI would then keep chatting and you would never be told. Now the retry finishes the job: it pauses the conversation and texts you, unless an alert for that text already exists (which means both steps were done, so a later hand-back by you is left alone).
**Applied fix:** In the "already answered" branch of `answer()`, a new `finishHandOff()` checks for an existing life-safety `OwnerAlert` on that text. If none exists, it runs `PauseConversation` then `RaiseOwnerAlert` (alert text "Safety message sent"). I chose the alert-exists check over the review's plain "always pause and alert" because pause comes before alert, so an existing alert proves both are done, and it avoids re-pausing a conversation you handed back. Never sends a second line. Tests: `PauseConversation` throws once after a successful send, the retry pauses and alerts exactly once; and a repeat run after a hand-back does not re-pause.

### WR-01: The keyword net misses common ways of describing the same dangers (verified)

**Files modified:** `app/SendRule/LifeSafety.php`, `tests/Unit/SendRule/LifeSafetyTest.php`
**Commit:** d3f1fd3
**What changed and why it matters:** A customer who texts "I am smelling gas" or "something is burning" or "my son got electrocuted" now gets the safety message at once, instead of waiting for the AI and the classifier to notice. These lists are the floor that works even if everything else is down.
**Wordings added (none removed):**
- Gas (`GAS_PHRASES`): `smelling gas`, `smelled gas`, `smells gas`, `gas hissing`, `gas is hissing`, `hissing gas`, `smelling propane`, `smelled propane`, `smells propane`, `smelling natural gas`, `smelled natural gas`, `smells natural gas`, `smelling sulfur`, `smelled sulfur`, `smells sulfur`.
- Fire, burning and shock (`FIRE_PHRASES`): `something burning`, `something is burning`, `smells burning`, `smelling burning`, `smelled burning`, `smell of burning`, `smells of burning`, `electrocuted`, `electrocution`, `electric shock`, `electrical shock`, `got shocked`, `got a shock`.
- No bare "burning" was added. New tests (in the `hazardIn` data provider) cover each new wording, plus "a burning question about my furnace filter" and "the burning issue is the heat bill", both of which stay `null`.
- Known trade-off: `got a shock` and `got shocked` can fire on a non-electrical remark such as "I got a shock at the price". A false alarm costs the customer one safety message; a miss can cost a life, so it was kept.

### WR-02: A utility phone the sync accepts is silently dropped from the gas sentence (verified)

**Files modified:** `app/SendRule/LifeSafety.php`, `app/Actions/SendLifeSafetyLine.php`, `tests/Unit/SendRule/LifeSafetyTest.php`, `tests/Feature/FrontDesk/LifeSafetyTest.php`
**Commit:** 2a33e64
**What changed and why it matters:** If the owner wrote the gas company number as `(312) 555-0188`, the sync accepted it but the safety text left the number out, with no warning. Now any format the sync accepts (brackets, dashes, dots, `1-` prefix) is printed as `+13125550188`.
**Applied fix:** `gasLine()` now uses the same `PhoneNumber::toE164()` as the sync validator and prints only the E.164 digits, so no other folder words can ride along. `utilityPhone()` casts a bare number to text. Tests: `(312) 555-0188`, `312-555-0188`, `312.555.0188`, `+1 312 555 0188`, `1-312-555-0188`, text wrapped around a number, and a bare integer returned by a mocked `ActiveConfig`. Note: the unit test file now uses the app test case (`uses(TestCase::class)`) because the phone check reads `config('channels.phone_region')`. Observation: the sync validator rule for `utility_phone` is `string`, so an unquoted YAML number is rejected at sync today; the integer cast is defence in depth.

### WR-04: The claim rollback in `DispatchInbound` does not cover a push deferred by `afterCommit()`

**Files modified:** `app/Actions/DispatchInbound.php`, `tests/Feature/Inbound/DispatchInboundTest.php`, `map/processes/answer-inbound.md`, `map/objects/messages/message-and-verdict.md`
**Commit:** 8a36b93
**What changed and why it matters:** Today nothing breaks, but if any code ever wrapped this step in a database transaction and the queue was down, a customer's text would be marked "queued" while nothing was queued, and nobody would ever answer it. That can no longer happen.
**Option chosen:** drop `->afterCommit()` (the review's first option), not the transaction-level guard. Reasons: it is a one-line change that makes the docblock true everywhere; the guard would throw in every test, since `RefreshDatabase` runs each test inside a transaction; and pushing early is harmless because the job waits out a 20 second debounce before it reads the message, and finds nothing if the surrounding transaction rolled back. The test uses the real database queue with its `jobs` table dropped (the existing fake queue ignores `afterCommit`, so it could not show the defect) and wraps `handle()` in `DB::transaction`; it failed before the fix.

### WR-05: Retry and failure semantics of `RespondToLifeSafety` do not match its comments

**Files modified:** `app/Jobs/RespondToLifeSafety.php`, `app/Actions/SendLifeSafetyLine.php`, `tests/Feature/FrontDesk/LifeSafetyTest.php`, `map/processes/answer-inbound.md`, `map/objects/conversations/conversation-and-pause.md`
**Commit:** 9d97dd7 (done after CR-01, as asked)
**What changed and why it matters:** If the safety job fails for good, the owner's alert now tells the truth. "Safety message sent, but check the conversation was paused" when the customer did get the text, and "could not be sent" only when they did not. The job also has a time limit, so a stuck worker cannot hold a gas-leak response indefinitely.
**Applied fix:** Docblock on `$tries` rewritten to say what really retries (pause or alert failures; a refused send is handled inside `SendLifeSafetyLine` and by the second look). `failed()` picks its alert text from a new public `SendLifeSafetyLine::lineSentFor()` (the former private `alreadyAnswered`, now taking only the inbound message). Explicit `$timeout = 120` with `$failOnTimeout = true`, comfortably above the 5 second reply lock, 10 second safety lock and the Twilio call, and below the database and redis `retry_after` of 360 (tested).

### WR-06: `LifeSafety` fails open when a regex errors (verified)

**Files modified:** `app/SendRule/LifeSafety.php`, `tests/Unit/SendRule/LifeSafetyTest.php`
**Commit:** 3cb84f4
**What changed and why it matters:** A text with garbled bytes used to count as "no danger", even if it said "smoke". Now the garbled bytes are scrubbed so a danger next to them is still found, and a text with garbled bytes is treated as at least a general danger. When in doubt, the customer gets the safety message.
**Applied fix:** `hazardIn()` checks `mb_check_encoding`, scrubs with `mb_scrub`, then matches. If the text was not valid UTF-8 and nothing matched, it returns `general`. `containsAny()` and `withoutApplianceNames()` now throw on a regex error and `hazardIn()` turns that into `general`. Tests: `"smoke \xff"` is `general`, a gas smell next to invalid bytes is still `gas`, bare invalid bytes and an otherwise ordinary text with invalid bytes are `general`, and valid UTF-8 (`naïve café, I smell gas`) is unchanged. The regex-error branch itself has no test because the patterns cannot be made to fail through public input once the text is scrubbed. Trade-off: a garbage text from the public form or fake webhook now triggers a safety line; real Twilio texts are valid UTF-8, so this should not happen in practice.

## Partly Fixed Issues

### WR-03: `ProcessInbound::failed()` reports on the newest customer text, not on the burst that failed

**Files modified:** `app/Jobs/ProcessInbound.php`, `tests/Feature/FrontDesk/FrontDeskFailureTest.php`, `map/processes/answer-inbound.md`
**Commit:** bfc52cf
**What changed and why it matters:** When a text-answering run was stopped, the owner's "front desk stopped" alert could name the wrong text (a newer one still waiting for its own run, or an old one), and the owner could be alerted twice. Now it names the text that run actually claimed.
**Applied fix:** `failed()` now queries the newest customer SMS message that has been claimed (`handled_at` set) with `id <=` the message that queued the job. Extra finding while testing: the old code called `latest('id')` on `$conversation->messages()`, whose relation already orders by `created_at, id` ascending, so the SQL was `order by created_at asc, id asc, id desc` and it actually returned the **oldest** message in the conversation. The new query starts from `Message::query()`, avoiding that ordering. Tests: not an earlier answered text, not a newer unclaimed one, not a web form, one alert only after `handle()` already reported, and nothing when no text was claimed. Status: partly fixed. The wrong-text and web-form cases are fixed. A duplicate alert can still happen when one run claims texts newer than the message that queued it (see Orchestrator check). That path is deferred to Phase 02.1, alongside the owner-alert de-duplication work. The preferred fix is a claim token stored on the claimed rows, not a time window in `ReportFrontDeskFailure`.

## Skipped Issues

None.

## Map cards updated

Line references and wording were updated in the same commits in `map/processes/answer-inbound.md`, `map/objects/messages/message-and-verdict.md` and `map/objects/conversations/conversation-and-pause.md` (the `SendLifeSafetyLine`, `RespondToLifeSafety`, `DispatchInbound` and `ProcessInbound::failed()` citations, the new retry behaviour, the no-`afterCommit` note, and the pause call-site count).

---

_Fixed: 2026-10-12_
_Fixer: Claude (gsd-code-fixer)_
_Iteration: 1_

## Orchestrator check

Full suite re-run by the orchestrator after the fixer finished: 1026 passed, 0 failed.

**WR-03 is narrowed, not closed.** `ClaimInboundBurst` claims every unhandled customer text in the conversation, not only texts up to the message that queued the job. When the debounce fails open or the sweep dispatches a run for an older text, job X can claim a newer text Y. `failed()` then reports X (because of `id <= messageId`) while `handle()` reported `$burst->last()` = Y, so the duplicate alert WR-03 describes can still happen on that path. The bound does fix the opposite case (a later, separate run that claimed a newer text). A full fix needs the claimed burst to be identifiable after the worker dies, Decision (2026-10-12): the remaining path is deferred to Phase 02.1, alongside the owner-alert de-duplication work. The preferred fix is a claim token stored on the claimed rows, so `failed()` can find exactly the burst its run claimed. A per-conversation time window in `ReportFrontDeskFailure` was considered and is not preferred.
