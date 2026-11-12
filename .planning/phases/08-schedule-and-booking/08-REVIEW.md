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
  info: 6
  total: 8
status: issues_found
increments:
  - reviewed: 2026-11-12T00:00:00Z
    scope: "8269ff1^..6b762eb (WR-09, WR-10, IN-10, IN-11, IN-12 fixes)"
    findings: {critical: 0, warning: 0, info: 3}
    ids: [IN-13, IN-14, IN-15]
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

---

# Phase 8: Code Review Report (increment: fixes for WR-09, WR-10, IN-10, IN-11, IN-12)

**Reviewed:** 2026-11-12
**Depth:** standard
**Files Reviewed:** 10
**Status:** issues_found (info only)

## Summary

Each of the five commits does what its finding asked. The full suite passes (3,210 tests), and so do the three
changed test files on their own.

Project rules hold. Every send in the changed code still goes through `OutboundSender::sendExisting()` with the
owner origin. The text that goes out is always the line built fresh at send time (`$line`). The text the browser
held (`$visitLineShown`) is only compared against it and is never sent. So even if someone changed that value in the
browser, the worst outcome is that no line is added; a made-up line can never go out. `visitLine` stays `#[Locked]`.
No money is touched.

## Verdicts on the fixed findings

- **WR-09: closed.** The approve step now passes down the locked line that was on screen. The send adds a line only
  when the line it would build now is word-for-word the same; otherwise it sends the words alone and gives the
  reason `VISIT_CHANGED`. Tests cover a visit that moved and an earlier visit that was booked. The thread refreshes
  itself every 5 seconds (`wire:poll.5s`), so "on screen" means the most recent refresh, which is what the snapshot
  holds when Send is pressed. The reason's wording is too narrow; see IN-13.
- **WR-10: closed.** A booking file synced before `visit_line` existed now gets its own reason, and "online booking is
  turned off" is kept for the room off or no booking file. The pinned test row was updated. The prompt half of WR-10
  (the Booking section is left out for such a customer) was not changed, and leaving it out is right, because its
  sentences would be false. The new reason can almost never reach an owner; see IN-14.
- **IN-10: closed (docs only, as Ian ruled).** SETUP.md now says to add `visit_line:` before the next sync. The note
  sits under a heading that says the opposite of what it describes; see IN-15.
- **IN-11: closed.** `find()` now orders by `starts_at` then `id`, the same order as `CustomerCard::render()` (line 72)
  and `ContactInfolist::nextAppointmentLine()`. The new SQL-text test proves the order. The first IN-11 test would also
  pass without the fix on SQLite, and the test's own comment says so.
- **IN-12: closed.** Both Booking constants now say the line is added when the reply is sent as it is, and that the
  owner decides when it is held. That is true of the code: a held draft shows the visit-line box or the link box only
  under the same conditions the constants describe. Both sentences are pinned in `BookingSectionTest`.

## Info

### IN-13: "The visit changed after the line was shown" is also said when the visit did not change

**File:** `app/Actions/SendOwnerReply.php:237-241`, `app/Rooms/ScheduleAndBooking/VisitAskedAbout.php:27-31`, `docs/BLUEPRINT.md:133`
**Issue:** The WR-09 check compares the whole rendered line, but the line is built from four things: the visit's
start, the visit's service name, the owner's `visit_line` wording in `booking.md`, and the business time zone
(`VisitAskedAbout::line()`, `BookingSettings::renderVisitLine()`). If the owner edits the `visit_line` wording or the
time zone and syncs between the last screen refresh and the click, the visit is the same but the line is not. The
draft then goes without the line, and the owner reads "Draft sent without the visit line: the visit changed after the
line was shown". That is false: the visit did not change. The owner may go looking for a change to a visit that never
moved. The BLUEPRINT line ("it moved, or an earlier visit was booked") gives the same incomplete list. Because the
thread refreshes every 5 seconds, this can only happen in a short window, so it is rare. CLAUDE.md still lists wording
as something that gates a phase, and WR-10 was raised for the same kind of false reason.
**Fix:** Either use a reason that is true in every case, for example
`'the line changed after it was shown'`, or tell the cases apart. To tell them apart, also pass the visit id that was
on screen (a second `#[Locked]` property filled in `syncDraftFields()`), then choose the reason:
```php
$leftOff = $line === null
    ? $reason ?? ($visit === null ? VisitAskedAbout::NO_VISIT_AHEAD : VisitAskedAbout::NO_VISIT_LINE)
    : ($visit->id === $visitIdShown ? VisitAskedAbout::LINE_WORDING_CHANGED : VisitAskedAbout::VISIT_CHANGED);
```
Add one dataset row to the WR-09 test that resyncs `booking.md` with a different `visit_line`. Update the BLUEPRINT
line to match.

### IN-14: The WR-10 reason can only be reached by a downgrade the platform cannot do, so the real case stays silent

**File:** `app/Actions/SendOwnerReply.php:186-191` (`visitLineFor()`), `app/Rooms/ScheduleAndBooking/ScheduleSection.php:60`, `tests/Feature/Booking/ApprovedDraftVisitLineTest.php:159-169`
**Issue:** `NO_VISIT_LINE` reaches the owner only on the approve path, and only when the live version had `visit_line`
when the screen last refreshed and lost it by the time Send was pressed. A sync cannot do that, because `visit_line`
is required (`BookingFileType.php:42`). There is no rollback to an older version either. The test reaches the case
only by deleting the key from the stored compiled JSON (`approvedVisitLineOlderVersion()`). The case that can really
happen is a business whose live version was compiled before plan 08-24 shipped. In that case `visitLineFor()` returns
null, so the box is never drawn. The front desk adds no visit line. The Booking section is left out of the prompt
(`FrontDeskPrompt.php:296`). Nothing anywhere tells the owner why, and that is the confusion WR-10 was about. The
owner finds out only when a later sync is refused (IN-10).
**Fix:** Show the reason where the owner already looks for booking state. Next to `ScheduleSection::BOOKING_OFF_LINE`,
add a notice shown when `BookingSettings::fromConfig()?->visitLine === null` while a booking file is live, for example
"Visit lines stay off until visit_line is added to rooms/schedule_and_booking/booking.md and the folder is synced
again." Alternatively, have the approve step show `whyNoLine()` as a one-line hint under the draft when it returns
`NO_VISIT_LINE` for a scheduling draft. Keep the existing test; add one for the new notice.

### IN-15: The IN-10 note sits under a heading that says the sync cannot check it

**File:** `docs/SETUP.md:191-207`
**Issue:** Section 12 is titled "Setting up a business: checks the sync cannot make", and its opening says these are
facts the sync cannot verify, to be checked by hand. The new paragraph describes the opposite: a check the sync does
make, and one that refuses the whole folder. It is also loose text after the section's bullet list rather than a
bullet. Someone reading the heading to decide what to check by hand will be misled, and someone looking for upgrade
steps will not look under this heading. The example folder's `changelog.md` that IN-10 mentioned has no entry either.
**Fix:** Move the paragraph under its own short heading, for example "## 13. Upgrading a business folder", with the
`visit_line` note as its first bullet and the rules for the line (holds `{day}` and `{time}` once each, `{service}` at
most once). Optionally add a dated line to `examples/example-hvac/changelog.md`.

---

_Reviewed: 2026-11-12_
_Reviewer: Claude (gsd-code-reviewer)_
_Depth: standard_
