---
phase: 08-schedule-and-booking
reviewed: 2026-11-08T00:00:00Z
depth: standard
files_reviewed: 37
files_reviewed_list:
  - app/Actions/OutboundSender.php
  - app/Actions/RecordInbound.php
  - app/Actions/RunFrontDesk.php
  - app/Actions/SendBookingText.php
  - app/Actions/SendOwnerReply.php
  - app/Channels/FakeTextChannel.php
  - app/Enums/BookingRefusal.php
  - app/Filament/Pages/Schedule.php
  - app/Filament/Resources/Contacts/RelationManagers/AppointmentsRelationManager.php
  - app/Http/Middleware/StartSessionHidingBookingTokens.php
  - app/Livewire/BookingPage.php
  - app/Records/RecordsWriter.php
  - app/Rooms/ScheduleAndBooking/Availability.php
  - app/Rooms/ScheduleAndBooking/BookingRefused.php
  - app/SendRule/DecideSendOrDraft.php
  - app/SendRule/Verdict.php
  - app/Support/BookingLinkTokens.php
  - bootstrap/app.php
  - database/migrations/2026_11_07_000001_revoke_booking_links_sent_before_tokens_were_hidden.php
  - docs/BLUEPRINT.md
  - docs/sprints/sprint-1.1.md
  - resources/views/components/booking/footer.blade.php
  - resources/views/components/booking/notice.blade.php
  - resources/views/livewire/booking-page.blade.php
  - tests/Feature/Booking/ApprovedDraftBookingLinkTest.php
  - tests/Feature/Booking/BookingLinkLeakTest.php
  - tests/Feature/Booking/BookingLinkTest.php
  - tests/Feature/Booking/BookingPageBrowserTest.php
  - tests/Feature/Booking/BookingPageContractTest.php
  - tests/Feature/Booking/BookingPageTest.php
  - tests/Feature/Booking/BookingPageThrottleTest.php
  - tests/Feature/Booking/BookingSendOnceTest.php
  - tests/Feature/Booking/LinksBeforeTheFixTest.php
  - tests/Feature/Booking/RefusalSentencesTest.php
  - tests/Feature/Filament/AppointmentsRelationManagerTest.php
  - tests/Feature/Filament/SchedulePageTest.php
  - tests/Unit/BookingTextKindTest.php
findings:
  critical: 0
  warning: 3
  info: 3
  total: 6
status: issues_found
---

# Phase 8: Code Review Report (re-review after gap plans 08-17 to 08-23)

**Reviewed:** 2026-11-08
**Depth:** standard
**Files Reviewed:** 37 (the changed source, view, migration and doc files since dc77bb8, plus the tests that pin them)
**Status:** issues_found

## Summary

This is an incremental re-review of the code changed since the 2026-11-07 review. I read each changed file in full and traced what it calls: `OutboundSender`, `SendBookingText`, `SendDueBookingTexts`, `MintBookingLink`, `RunFrontDesk::sendPassingReply`, `SendOwnerReply::sendDraftAskingForLink`, `DecideSendOrDraft`, `Verdict`, `HeldReasons`, `FrontDeskPrompt::history`, `DevChat`, and Laravel's `StartSession`.

I found no BLOCKER. These held up when I tried to break them:

- **Hide and send split (D-12).** `OutboundSender` stores `hide($body)` and hands the raw text to the channel (`OutboundSender.php:317`, `:350`, `:378`). Both `deliver()` and `deliverExisting()` do this, and the length check runs on the raw text. A channel failure's message is hidden before it reaches the row, the activity note or the exception (`:384-391`).
- **Session replacement.** The `StartSession` override matches the vendor signature (`storeCurrentUrl(Request $request, $session)`). It runs before `saveSession`, is idempotent on non-GET requests, and `web(replace:)` swaps the class in place in the group.
- **Send-once.** The lookup by `meta->booking_send_id` happens before every other check. `markSent()` swallows only the outcome write and leaves the claim in place, so the next sweep ends the row on the existing message.
- **Show filters.** `showRows()` now splits booked rows by `ends_at`, so a span over midnight is in exactly one filter. The `orWhere` is wrapped in its own group, so it cannot escape the query's other constraints.
- **Rate limit retry.** `tryAgain()` goes through `RateLimiter::attempt()`, which counts nothing while the minute is not up, so it cannot be used to tap past the limit.

The remaining defects are in the corners the fixes left. Three are worth fixing:

- A queued booking message left by a dead sender ends its row as failed with no trace the owner can read.
- The front-desk model is never told what `/book/[link]` in its own history means, so the new hold will fire on ordinary follow-ups.
- WR-03 is only mostly fixed: a failure after the text went out, other than the recording step, still withdraws a live link.

No finding reopens a locked decision.

## Previous Review Findings (2026-11-07)

| Id | Status | Evidence |
|----|--------|----------|
| WR-01 (booking text sent twice when the outcome write fails or the process dies) | Fixed, with one soft spot (see WR-06) | `SendBookingText.php:103-109` looks for a message carrying `meta.booking_send_id` before anything else. `:202-232` ends the row on a sent or delivered message, on a failed one with its own error, and on a still-queued one without resending. `:239-251` reports the failed outcome write and logs a critical record naming the row and message but never the text. Pinned by `tests/Feature/Booking/BookingSendOnceTest.php` (the DB hook that throws on the `set "sent_at"` update, then the 3-minute re-sweep). |
| WR-02 (reminder at the window boundary falls out on a late sweep; a dropped reminder leaves no trace) | Fixed, with one soft spot (see IN-07) | `SendBookingText.php:54` (`LATE_GRACE_MINUTES = 5`) and `:262-279` (`nextOpening()` grace). `:347-358` (`drop()`) writes "Reminder/Confirmation text not sent: the visit started first." for a non-cancelled visit whose start has passed. Pinned by the datasets and the overtaken-visit tests in `BookingSendOnceTest.php`. |
| WR-03 (link revoked when a step after the send throws) | Partly fixed (see WR-08) | `RunFrontDesk.php:617-645` and `:652-659`, and `SendOwnerReply.php:195-219`, run `attachMessage()` after the guarded block and only report its failure. A failure after the channel accepted the text that is not the recording step still reaches the `catch (Throwable)` branches that withdraw the link (`RunFrontDesk.php:634-638`, `SendOwnerReply.php:203-211`). |
| WR-04 (refusal handling matches on copied message text) | Fixed | Every sentence a caller reads is a public constant on `OutboundSender.php:60-148`, and `SendBookingText.php:309-338` compares only with those. `BookingRefused.php:16` carries a `BookingRefusal` reason and `BookingPage.php:209-216` matches on it. `RefusalSentencesTest.php` pins both (including a source scan that `SendBookingText` keeps no copy of a sentence). |
| WR-05 (placeholder privacy wording on the customer page) | Fixed | `resources/views/components/booking/footer.blade.php:9` now says only what the page does: it asks for nothing typed, and it receives the device's address and uses it to limit repeated tries. The session-cookie and session-row facts are recorded for the attorney in `docs/BLUEPRINT.md` section 10, so I do not raise them again. |
| IN-01 (doc comment contradicts the code on the drive buffer) | Fixed | `Availability.php:89` and `:108` now say hand bookings pass the file's drive buffer, or 0 with no booking file. |
| IN-02 (booking-text chokepoint trusts caller-declared parts) | Fixed as Ian ruled (architecture test) | `tests/Unit/BookingTextKindTest.php:46-52` pins the three files under `app/` that may name the kind, and the snippet datasets prove the scan matches. The scan is lexical, so `MessageKind::from($variable)` would pass it; that is the accepted shape of the ruling, not a new defect. |
| IN-03 (hand-booking dialog forecasts a text for a contact with no phone) | Fixed | `AppointmentsRelationManager.php:240` returns "No confirmation text will go out. This customer has no phone number on file." The optional "next opening" variant is declined in the docblock at `:224-230`, which is reasonable. |
| IN-04 (Upcoming and Past overlap for a span over midnight) | Fixed | `Schedule.php:277-296`: Past is booked rows with `ends_at` before today plus done visits that started before today, and Upcoming is booked rows with `ends_at` from today on. |
| IN-05 (rate-limited notice cannot be retried without a reload) | Fixed | `BookingPage.php:153-156` (`tryAgain()`) and `booking-page.blade.php:2-9` (Try again button, notice focus, heading focus after a retry). |
| IN-06 (customer card's Next appointment line goes stale) | Deferred by Ian to Phase 9 (the nightly rebuild); not re-reviewed | Unchanged in `app/Records/CustomerCard.php`. |

## Warnings

### WR-06: A booking row whose sender died after queuing the message ends as failed with no note anywhere the owner reads

**File:** `app/Actions/SendBookingText.php:222-231` and `:83-89`
**Issue:** The new send-once lookup (`:103-109`) finds a leftover message in the `queued` state. It ends the row failed with "The send stopped partway, so it was not sent again." and a `Log::critical` record, but writes no timeline note. The customer card does not draw `booking_sends` rows. The only record the owner can read is the critical log line.

A `queued` leftover is not always a text that reached the customer. `OutboundSender::deliver()` creates the row as `queued` (`OutboundSender.php:312-320`) before the channel is called (`:378`). A sender that dies between those two lines leaves a queued message and no text on any phone. The confirmation or reminder then silently never goes out. This is the same "silently never goes out" shape the previous WR-02 raised, and D-27 says the owner reads a note on the card. The `Failed` branch is fine because `OutboundSender::recordFailure()` already wrote its own note.

A smaller related gap sits in `handle()` at `:83-89`. Its `catch (Throwable)` ends the row failed without asking whether a message already exists. If a non-`DomainException` escapes `OutboundSender::send()` after the channel accepted the text (see WR-08), the row says "failed" while the customer has the text. The send-once lookup never runs again because the row has ended.
**Fix:** Give the queued branch a timeline note, using the same room entry `skip()` writes. Say it plainly: "Reminder text may not have reached the customer: the send stopped partway." Run the existing-message lookup inside the `catch` in `handle()` too:
```php
} catch (Throwable $exception) {
    report($exception);

    $existing = Message::query()->where('meta->booking_send_id', $send->id)->latest('id')->first();

    if ($existing !== null) {
        $this->endOnExisting($send, $existing);

        return;
    }

    $this->end($send, ['failed_at' => $this->now(), 'failure' => $this->reasonFrom($exception->getMessage())]);
}
```

### WR-07: The front-desk model is never told what `/book/[link]` in its own history means, so the new hold fires on ordinary follow-ups

**File:** `app/SendRule/DecideSendOrDraft.php:72-74`, `app/Ai/FrontDeskPrompt.php:39-47` and `:140-175`
**Issue:** After 08-17 every sent booking link is stored as `https://host/book/[link]`. `FrontDeskPrompt::history()` feeds those stored bodies back to the model as `assistant` turns. Nothing in the prompt's rules mentions links, URLs or that text: `grep -n -i "link\|url\|http\|book"` on the file finds only the price rule and the "never say a visit is set" rule. A model that sees its own earlier turn ending in `/book/[link]` has a strong pull to repeat or paraphrase it ("you can use /book/[link] to pick a time"). `namesBookingAddress()` then holds the reply as a draft with "Held: writes its own booking link, which would not open".

The hold is correct as a backstop, but as the only defence it turns every such follow-up into owner work, on the exact scheduling path that was meant to send on its own. It also teaches the model nothing. The plan's own UAT note expects this ("asking Dev chat for the link again must give a fresh link line or a held draft"), so it is a known cost. It is still the one place where a single prompt sentence would cut the hold rate.
**Fix:** Add one rule to `FrontDeskPrompt::RULES`, near the scheduling label: "A booking link in an earlier text of yours shows as /book/[link]. That is a placeholder for a working link the system added. Never write a booking address yourself. If the customer needs a link again, reply with the scheduling label and no address, and the system adds a fresh one." Add a prompt-content test beside the existing rule tests.

### WR-08: A failure after the text went out, other than recording the carrying message, still withdraws a live link

**File:** `app/Actions/OutboundSender.php:182-186`, `app/Actions/RunFrontDesk.php:634-638`, `app/Actions/SendOwnerReply.php:203-211`
**Issue:** 08-20 moved `attachMessage()` out of the guarded block, but the guard still withdraws on any `Throwable` from the send. `OutboundSender::send()` and `sendExisting()` end with `try { return $this->deliver(...); } finally { $lock->release(); }`. The docblock at `:162-170` says a failure after the channel accepted the text is "never raised". That holds for everything inside `transmit()`, but not for `$lock->release()`. With the database cache store (`.env.example`: `CACHE_STORE=database`), `release()` is a `DELETE` query. If it throws, the exception replaces the return value even though the customer already has the text. The caller then withdraws the live link:

- In `RunFrontDesk` the `catch (Throwable)` at `:634` calls `withdrawLink()` and rethrows.
- In `SendOwnerReply` the `catch (Throwable)` at `:203` calls `withdrawUnsentLink->handle()`, and the owner is told the send failed.

The result is the customer-visible dead link the previous WR-03 described. It needs a database fault at one specific moment, so it is rare, but the docblocks (`OutboundSender.php:168-170`, `RunFrontDesk.php:592-594`) now claim it cannot happen.
**Fix:** Make the release unable to hide a completed send:
```php
try {
    $message = $this->deliver($conversation, trim($body), $author, $origin, $attributes);
} finally {
    try {
        $lock->release();
    } catch (Throwable $failure) {
        report($failure);
    }
}

return $message;
```
Apply the same shape to `sendExisting()`. If `deliver()` itself threw, the original exception still propagates because `finally` runs first and the inner `try` swallows only the release failure.

## Info

### IN-07: The late-send grace does not check that the row came due today or after the window opened

**File:** `app/Actions/SendBookingText.php:272-276`
**Issue:** `$dueInsideTheWindow` is `due_at <= today's close` only. A row due on an earlier day, or earlier than today's opening, also passes it. Take a sweep that was down for a day and comes back at 20:03: a row due yesterday 20:00, or today 03:00, sends at 20:03 instead of waiting for tomorrow's opening. The text still goes out inside the 5-minute grace after the close, so the harm is small, but the docblock says "came due inside the window". The window's hours stay on the attorney list (D-44), which is a reason to keep the grace tight.
**Fix:** Require the due time to be on today's local date and at or after today's opening:
```php
$due = $send->due_at->copy()->setTimezone($zone)->startOfMinute();
$dueInsideTheWindow = $due->isSameDay($local) && $due->greaterThanOrEqualTo($opens) && $due->lessThanOrEqualTo($closes);
```

### IN-08: The fake channel's docblock names the dev chat as a reader of `sent()`, and the dev chat does not read it

**File:** `app/Channels/FakeTextChannel.php:44-47` and `:96-100`
**Issue:** The docblocks say `sent()` and `refused()` are "the fake carrier's own memory for tests and the dev chat". `DevChat::thread()` (`app/Livewire/DevChat.php:88-96`) reads the `messages` table, so after D-12 it shows `/book/[link]`, never the working link. `FakeTextChannel::$sent` is also per instance, so it could never carry a link from one request to the next. Nothing breaks. The plan records that booking-page walks take a working link from the terminal, and a reader of this class would still expect the dev chat to be able to open the link.
**Fix:** Drop "and the dev chat" from the two docblocks and say that the dev chat shows the stored copy.

### IN-09: The revoke migration cannot tell a link sent before the fix from one sent after it

**File:** `database/migrations/2026_11_07_000001_revoke_booking_links_sent_before_tokens_were_hidden.php:23-33`
**Issue:** The query revokes every link with no `revoked_at` and no appointment. That is right on a database that moves from the old code to the new in one deploy. It is wrong on any database that ran 08-17's code first and migrates later, such as a staging or tour database whose `migrate` step was skipped or deferred. Every link minted since the fix is then revoked, and the customers who hold them get "This link does not work". The migration has no cutoff, and `down()` is a no-op by design, so the recovery is the log line's id list by hand.
**Fix:** Bound the revoke to links created before the fix shipped, for example `->where('created_at', '<', '2026-11-08 00:00:00')` with the cutoff written as a constant and explained in the docblock. Leave later links alone.

---

_Reviewed: 2026-11-08_
_Reviewer: Claude (gsd-code-reviewer)_
_Depth: standard_
