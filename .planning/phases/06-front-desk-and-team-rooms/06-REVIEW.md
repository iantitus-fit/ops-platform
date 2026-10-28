---
phase: 06-front-desk-and-team-rooms
reviewed: 2026-10-28T00:00:00Z
depth: standard
files_reviewed: 39
files_reviewed_list:
  - app/Actions/ClaimInboundBurst.php
  - app/Actions/RaiseOwnerAlert.php
  - app/Actions/ReportFrontDeskFailure.php
  - app/Actions/SendOwnerAlert.php
  - app/Jobs/ProcessInbound.php
  - app/Jobs/SendDueAlerts.php
  - app/Models/Message.php
  - app/Models/OwnerAlert.php
  - app/Rooms/FrontDesk/FrontDeskServiceProvider.php
  - app/Rooms/Room.php
  - app/Rooms/RoomRegistry.php
  - app/Rooms/Team/TeamServiceProvider.php
  - bootstrap/providers.php
  - database/factories/MessageFactory.php
  - database/factories/OwnerAlertFactory.php
  - database/migrations/2026_10_27_000001_add_send_claimed_at_to_owner_alerts_table.php
  - database/migrations/2026_10_27_000002_add_dedupe_key_to_owner_alerts_table.php
  - database/migrations/2026_10_27_000003_backfill_dedupe_key_on_owner_alerts.php
  - database/migrations/2026_10_27_000004_add_unique_dedupe_key_to_owner_alerts_table.php
  - database/migrations/2026_10_27_000005_add_claim_token_to_messages_table.php
  - database/migrations/2026_10_27_000006_add_failure_reported_at_to_messages_table.php
  - docs/BLUEPRINT.md
  - docs/SPEC.md
  - docs/sprints/sprint-1.1.md
  - examples/example-hvac/rooms/front_desk/CONTEXT.md
  - examples/example-hvac/rooms/team/CONTEXT.md
  - tests/Feature/Alerts/AlertDedupeKeyTest.php
  - tests/Feature/Alerts/AlertSentOnceTest.php
  - tests/Feature/FrontDesk/ClaimTokenTest.php
  - tests/Feature/FrontDesk/FailureReportOnceTest.php
  - tests/Feature/FrontDesk/FrontDeskFailureTest.php
  - tests/Feature/Rooms/FrontDeskBootTest.php
  - tests/Feature/Rooms/HandBuiltRegistrySyncTest.php
  - tests/Feature/Rooms/RoomOwnershipTest.php
  - tests/Feature/Rooms/TeamBootTest.php
  - tests/Pest.php
  - tests/Unit/Icm/RoomChecksTest.php
  - tests/Unit/Rooms/RoomClaimedWidgetsTest.php
findings:
  critical: 0
  warning: 4
  info: 2
  total: 6
status: issues_found
---

# Phase 6: Code Review Report

**Reviewed:** 2026-10-28
**Depth:** standard
**Files Reviewed:** 39
**Status:** issues_found

## Summary

Phase 6 adds the claim/dedupe machinery (send claim on alerts, unique dedupe key, claim token on messages, report-once on failures) and registers the front desk and team rooms. The core atomic-claim logic (conditional updates whose affected-row count decides the winner, `createOrFirst` against a unique key, savepoint handling) is sound on the paths I traced, the migrations are reversible in the right order, and the tests exercise stale copies, stale claims, the 119/120 second boundary and serialize/unserialize of the job.

No security vulnerability or data-loss defect was found. The remaining problems are robustness gaps: one deploy-time hazard in the new `ProcessInbound::$claimToken` property, an inconsistency in which row a failure is reported against, an unguarded rollback that can mask the original error, and a registry guard that misses a duplicate-claim case. Tests were not executed as part of this review (read-only review; suite status is not asserted here).

## Warnings

### WR-01: `ProcessInbound::$claimToken` is uninitialized on any job queued before this deploy, so both `handle()` and `failed()` throw

**File:** `app/Jobs/ProcessInbound.php:59, 101, 181`
**Issue:** `public string $claimToken;` has no default and is set only in the constructor. A queued job payload serialized before this change has no such property, and `unserialize` does not call the constructor, so reading `$this->claimToken` throws `Error: must not be accessed before initialization`. That read is at line 101 in `handle()`, outside the `try`, and again at line 181 in `failed()`. The result for such a payload is that the burst is never claimed, and because `failed()` throws too, the owner is told nothing. The docblock justifies "no default on purpose" (a default would give every old payload the same token), but the consequence is a silent unanswered customer text across a deploy, which is the exact failure this phase exists to prevent. The same applies to any payload in a debounce waiting state at deploy time.
**Fix:** Make the property tolerant of the missing value and mint a token lazily, per-copy, in `handle()` rather than leaving it uninitialized:
```php
public ?string $claimToken = null;

public function __construct(public int $messageId, public int $conversationId, ?string $claimToken = null)
{
    $this->claimToken = $claimToken ?? (string) Str::uuid();
}

public function handle(...): bool
{
    $this->claimToken ??= (string) Str::uuid();
    $burst = $claimInboundBurst->handle($this->conversationId, $this->claimToken);
    ...
}

public function failed(?Throwable $exception): void
{
    if ($this->claimToken === null) {
        return; // legacy payload: nothing was claimed under a token
    }
    ...
}
```
A legacy payload that reaches `handle()` then claims under a fresh token held on that object, which is exactly what the in-process `failed()` needs (and a rebuilt copy with a null token correctly reports nothing, since it claimed nothing under a known token).

### WR-02: A failure is reported against different rows depending on which path reports first

**File:** `app/Actions/ReportFrontDeskFailure.php:46-76` (callers `app/Actions/RunFrontDesk.php:203, 214`)
**Issue:** The report claim is taken on `subjectOf($rows)` (the newest SMS row of the token's burst, D-20), but the note, the `alreadyReported` check and the alert all use the caller's `$text`. `ProcessInbound` passes `subjectOf($burst)`, while `RunFrontDesk` passes `$burst->last()`, the newest row regardless of channel. For a burst whose newest row is a web form (the mixed-burst case the tests cover for the catch path), the "no agent file" and "every model failed" reports attach the note and the `front_desk_failed` alert to the web form message, while the catch and `failed()` paths attach them to the text. The claim guarantees only one report, but which message the owner's alert and the timeline entry point at (and which message `alertFailure()` / `ownerAlerts` surface on) now depends on a race the caller does not control. `FailureReportOnceTest` "reports once for a burst whose newest row is a web form" asserts only the count and the claim row, not the alert's `message_id`, so the divergence is untested.
**Fix:** After the claim is taken, use the claimed row as the subject so every path agrees:
```php
$subject = $claimedRow ?? $text;

$alreadyReported = OwnerAlert::query()
    ->where('message_id', $subject->id)
    ->where('reason', AlertReason::FrontDeskFailed->value)
    ->exists();
...
'subject_type' => $subject->getMorphClass(),
'subject_id' => $subject->id,
...
$this->raiseOwnerAlert->handle($conversation, AlertReason::FrontDeskFailed, $subject, $why);
```
Also add an assertion on `OwnerAlert::message_id` to the mixed-burst RunFrontDesk-first test.

### WR-03: Giving the report claim back can itself throw and replace the original exception

**File:** `app/Actions/ReportFrontDeskFailure.php:77-83`
**Issue:** In the `catch`, `Message::query()->whereKey(...)->update(['failure_reported_at' => null])` runs before `throw $exception`. If the cause of the original failure was a database problem (the very case the surrounding comments cite, 02.1 IN-11), this update can throw too. The new exception replaces the original, the original is never reported (`report()` is not called here), and the claim stays set, so the other path (`failed()`) then skips the report entirely. That is the outcome the release was meant to prevent: a missing alert.
**Fix:** Release the claim defensively and always rethrow the original:
```php
} catch (Throwable $exception) {
    if ($claimedRow !== null) {
        try {
            Message::query()->whereKey($claimedRow->id)->update(['failure_reported_at' => null]);
        } catch (Throwable $releaseFailure) {
            report($releaseFailure);
        }
    }

    throw $exception;
}
```

### WR-04: `RoomRegistry::guardClaimedWidgets` misses duplicates inside one room and overlap with the room's own sections

**File:** `app/Rooms/RoomRegistry.php:326-343`
**Issue:** The "a widget has one owner room" check iterates only `$this->rooms`, which does not yet contain the room being added. A room that lists the same widget twice in `claimedWidgets`, or claims a class that is also in its own `sections` (which `addWidgetsTo()` registers on the panel), passes. The latter would double-register the widget on the dashboard, which the claim mechanism exists to prevent, and neither case is covered by `RoomClaimedWidgetsTest`.
**Fix:**
```php
$seen = [];

foreach ($room->claimedWidgets as $widget) {
    if (in_array($widget, $seen, true)) {
        throw new LogicException("Room [{$room->key}] claims widget [{$widget}] more than once.");
    }

    if (in_array($widget, $room->sections, true)) {
        throw new LogicException("Room [{$room->key}] both adds and claims widget [{$widget}]. Add it in sections, or claim it, not both.");
    }

    $seen[] = $widget;
    // existing class_exists / is_subclass_of / other-room checks
}
```
and add a test case for each.

## Info

### IN-01: `Message` mass-assignment allows the two new claim columns, unlike the equivalent alert column

**File:** `app/Models/Message.php:38-39`
**Issue:** `OwnerAlert` deliberately keeps `send_claimed_at` out of `#[Fillable]` because only a conditional update may write it (documented in its class docblock). `Message` adds `claim_token` and `failure_reported_at`, which carry the same invariant (only the conditional updates in `ClaimInboundBurst` and `ReportFrontDeskFailure` should write them), to `#[Fillable]`. Nothing in app code mass-assigns them (the claims use query-builder `update()`, and the factory's `claimedBy()` state runs unguarded), so the entries are unnecessary and invite a later `create([...])` or `fill()` to bypass the claim.
**Fix:** Remove `'claim_token'` and `'failure_reported_at'` from the `Fillable` list; tests and factories keep working because factories create unguarded.

### IN-02: Recipient lookup and alert-row construction are duplicated between `handle()` and `forBusiness()`

**File:** `app/Actions/RaiseOwnerAlert.php:46-50, 102-106, 74, 117`
**Issue:** The "users with a phone, or `[null]`" lookup and the `to_phone` trimming expression are copied verbatim in both methods. A future change to what counts as a reachable recipient (for example normalising phones) has to be made twice, and `forBusiness()` omitting it would silently diverge the two alert kinds.
**Fix:** Extract a private `recipients(): Collection` helper and a private `phoneFor(?int $userId, Collection $recipients): ?string`, used by both.

---

_Reviewed: 2026-10-28_
_Reviewer: Claude (gsd-code-reviewer)_
_Depth: standard_
