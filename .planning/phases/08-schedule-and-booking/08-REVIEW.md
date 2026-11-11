---
phase: 08-schedule-and-booking
reviewed: 2026-11-11T00:00:00Z
depth: standard
files_reviewed: 31
files_reviewed_list:
  - app/Actions/OutboundSender.php
  - app/Actions/ResolveDraft.php
  - app/Actions/RunFrontDesk.php
  - app/Actions/SendBookingText.php
  - app/Actions/SendOwnerReply.php
  - app/Ai/FrontDeskPrompt.php
  - app/Livewire/ConversationThread.php
  - app/Rooms/ScheduleAndBooking/BookingFileType.php
  - app/Rooms/ScheduleAndBooking/BookingSettings.php
  - app/Rooms/ScheduleAndBooking/ServiceToBook.php
  - app/Rooms/ScheduleAndBooking/VisitAskedAbout.php
  - app/SendRule/ReplyParts.php
  - CLAUDE.md
  - docs/BLUEPRINT.md
  - docs/SETUP.md
  - docs/SPEC.md
  - docs/sprints/sprint-1.1.md
  - examples/example-hvac/rooms/schedule_and_booking/booking.md
  - resources/views/components/booking/footer.blade.php
  - resources/views/livewire/conversation-thread.blade.php
  - tests/Feature/Booking/ApprovedDraftBookingLinkTest.php
  - tests/Feature/Booking/ApprovedDraftVisitLineTest.php
  - tests/Feature/Booking/BookingLinkTest.php
  - tests/Feature/Booking/BookingPageContractTest.php
  - tests/Feature/Booking/BookingSendOnceTest.php
  - tests/Feature/Booking/VisitLineTest.php
  - tests/Feature/Filament/DraftVisitLineKeyboardTest.php
  - tests/Feature/FrontDesk/BookingSectionTest.php
  - tests/Feature/FrontDesk/FrontDeskPromptTest.php
  - tests/Feature/Rooms/BookingFileCheckTest.php
  - tests/Pest.php
findings:
  critical: 0
  warning: 2
  info: 3
  total: 5
status: issues_found
---

# Phase 8: Code Review Report (incremental, 67d16e0..HEAD)

**Reviewed:** 2026-11-11T00:00:00Z
**Depth:** standard
**Files Reviewed:** 31
**Status:** issues_found

## Summary

Incremental re-review of what changed since 67d16e0: the WR-06, WR-07 and WR-08 fixes, and gap plans 08-24 to 08-26 (the
owner's visit line on a booked customer's reply, on a held draft, and the front desk's "Booking" section). I read each
diff in full and traced the new paths: `RunFrontDesk::sendPassingReply()` through `ReplyParts` and `OutboundSender::whyReadingBlocks()`,
`ConversationThread::approveDraft()` through `ResolveDraft` and `SendOwnerReply::sendDraftAskingForVisitLine()`, and
`SendBookingText::endAfterFault()`.

The send-path logic holds up. The visit line is added after the verdict, only while no link rides, and it is held to the
owner-sentence rule by the chokepoint. A complaint, failed or missing reading never reaches it on the automated path,
because the model's words are held first (RunFrontDesk.php:367) and `allowsOwnerSentences()` is the same test. The
approval path draws its box from, and re-checks with, the same `VisitAskedAbout` the send uses. The lock release in
`OutboundSender::release()` is correct: it swallows only its own failure, and a send error still goes up. The WR-06 fault
path ends the row on an existing message and never sends twice. No security issue found: the visit line is rendered with a
single-pass `strtr()` from the owner's synced line and the appointment's own service name, the Livewire `visitLine` is
`#[Locked]`, and the label is escaped in Blade.

Two warnings, both about what the owner is told on the approve step. Three info items. The footer padding change
(footer.blade.php) is a looks-only change and is not a finding under the 2026-11-11 visual-direction rule.

## Warnings

### WR-09: The visit line sent on approval is not checked against the line the owner saw

**File:** `app/Livewire/ConversationThread.php:104-110`, `app/Actions/SendOwnerReply.php:521-545`
**Issue:** The box asks "The visit line will be added: <line>", and the docblocks and `docs/BLUEPRINT.md:133` promise "a line
is added only when it was on screen". The code only checks that some line was on screen: `approveDraft()` passes
`$this->addVisitLine && $this->visitLine !== null`, a boolean, and `sendDraftAskingForVisitLine()` then calls
`VisitAskedAbout::find()` again and renders whichever visit is earliest at that moment. If the visit moved, was cancelled
and replaced, or an earlier visit was booked between the owner's last render and the click, the text that goes out names a
different day or time than the one the owner agreed to. No test covers it: `adds no visit line the owner was not shown`
covers a line that was absent, and `follows the conditions while the same draft stays on screen` covers the screen
changing but never approves after the change. The customer is not told anything false (the line is true at send time), but the owner approved
other words, and the stated contract is broken on the one screen where the owner's sign-off is the control.
**Fix:** Carry the line the owner saw down to the send and compare. Pass `$this->visitLine` (the locked, on-screen text)
instead of a boolean, and in `sendDraftAskingForVisitLine()` send without the line, with a plain reason, when the fresh
line differs:
```php
// ConversationThread::approveDraft()
$this->addVisitLine ? $this->visitLine : null,

// SendOwnerReply::sendDraft(..., ?string $visitLineShown = null) / sendDraftAskingForVisitLine()
if ($visit === null || $line === null || $line !== $visitLineShown) {
    // left off: reason is NO_VISIT_AHEAD, or 'the visit changed since the line was shown'
}
```
Add a test that changes the appointment's start after the first render and before `approveDraft`, and asserts the
draft goes without the line and the notice says why.

### WR-10: A booking file synced before `visit_line` existed tells the owner "online booking is turned off"

**File:** `app/Rooms/ScheduleAndBooking/VisitAskedAbout.php:904-906` (in the diff; `whyNoLine()`), `app/Actions/SendOwnerReply.php:237`
**Issue:** `whyNoLine()` returns `ServiceToBook::BOOKING_OFF` ("online booking is turned off") when
`BookingSettings::fromConfig()?->visitLine === null`. That is also true when booking is on and working and only the live
version predates `visit_line`. The owner then reads "Draft sent without the visit line: online booking is turned off",
which is false and sends them to the wrong place. The cause is a missing line in `booking.md`. The test pins the wrong
sentence (`'a version synced before visit_line' => ['older version', 'online booking is turned off']`). CLAUDE.md lists
wording as something that still gates a phase. The same gap shows in the prompt: `bookingSection()` silently drops the
whole "Booking" section for such a customer, so the model is told nothing.
**Fix:** Give the older-version case its own reason, and keep `BOOKING_OFF` for the room off or no booking file:
```php
public const string NO_VISIT_LINE = 'the booking file has no visit_line yet, so add it and sync again';

$settings = BookingSettings::fromConfig($this->config);
if ($settings === null) {
    return ServiceToBook::BOOKING_OFF;
}
if ($settings->visitLine === null) {
    return self::NO_VISIT_LINE;
}
```
Update the `$reason ?? (...)` fallback at SendOwnerReply.php:237 to match, and the parameterised test row.

## Info

### IN-10: Making `visit_line` required refuses an existing owner's whole folder with no upgrade note

**File:** `app/Rooms/ScheduleAndBooking/BookingFileType.php:42`, `docs/SETUP.md`
**Issue:** `visit_line` is `required`. A folder whose `booking.md` was written before plan 08-24 is refused whole by the
sync, and the previous version stays live. So an owner who only wanted to change a price or an hour gets a refusal about a
field they have never seen, and nothing in SETUP.md or the example's changelog says to add it. The refusal does name the
file and field, so this is recoverable, and the older-version code path exists for the in-between state. It is still a
sharp edge for any folder that predates the field.
**Fix:** Add a line to the sync-refusal documentation or SETUP.md ("a booking file written before the visit line needs
`visit_line:` added"), or leave the field optional with no-line-sent behaviour until the owner adds it, as
`BookingSettings::renderVisit()` already supports.

### IN-11: The visit lookup has no tie-break, so the line and the customer card can name different visits

**File:** `app/Rooms/ScheduleAndBooking/VisitAskedAbout.php:872`
**Issue:** `find()` orders by `starts_at` only. `CustomerCard` picks the next appointment by `starts_at` then `id`
(CustomerCard.php:73). Two booked visits that start at the same minute, for instance one added by hand beside a page
booking, can come back in either order from `find()`, so the text the customer gets, the owner's change-request alert and the
card the model reads may name different visits, and the order can change between the send and an approval minutes later.
**Fix:** `->orderBy('starts_at')->orderBy('id')->first()`.

### IN-12: The Booking section's "each sentence is true" claim is stronger than the code

**File:** `app/Ai/FrontDeskPrompt.php` (`BOOKING_VISIT_BOOKED`, `BOOKING_ONLINE` and their docblocks)
**Issue:** The docblocks say each sentence is true because of code, and the section is built from the send's own
conditions. Both constants say flatly that "the system adds a line after your words". That holds for a reply that sends.
It is not true for a reply the send rule holds as a draft: the link is minted only at approval and only with the box
checked, and the visit line only at approval with its box checked, and neither is added when the combined text would pass
1,600 characters or when the reading's intent and label are not scheduling. The model is told it need not give a day, a
time or a link, so a held draft can reach the owner with neither in it. The owner sees the boxes and the effect is small, so
this is wording only.
**Fix:** Say "when your reply is sent as it is" in both constants, or add "when it is held for the owner, the owner decides
whether it is added", and update the two constant-pinned tests in `BookingSectionTest`.

---

_Reviewed: 2026-11-11T00:00:00Z_
_Reviewer: Claude (gsd-code-reviewer)_
_Depth: standard_
