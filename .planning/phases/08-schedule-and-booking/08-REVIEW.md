---
phase: 08-schedule-and-booking
reviewed: 2026-11-07T00:00:00Z
depth: standard
files_reviewed: 88
files_reviewed_list:
  - .github/workflows/tests.yml
  - app/Actions/AddContactByHand.php
  - app/Actions/BlockTime.php
  - app/Actions/BookAppointment.php
  - app/Actions/CancelAppointment.php
  - app/Actions/CloseProject.php
  - app/Actions/ContactRefused.php
  - app/Actions/EditVisitNote.php
  - app/Actions/MarkAppointmentDone.php
  - app/Actions/MintBookingLink.php
  - app/Actions/OpenConversationForText.php
  - app/Actions/OpenProject.php
  - app/Actions/OutboundSender.php
  - app/Actions/OwnerAlertText.php
  - app/Actions/RemoveBlock.php
  - app/Actions/ResolveDraft.php
  - app/Actions/RunFrontDesk.php
  - app/Actions/SendBookingText.php
  - app/Actions/SendOwnerReply.php
  - app/Actions/SetAutomaticTexts.php
  - app/Enums/AlertReason.php
  - app/Enums/AppointmentActor.php
  - app/Enums/AppointmentKind.php
  - app/Enums/AppointmentState.php
  - app/Enums/BookingPageState.php
  - app/Enums/MessageKind.php
  - app/Enums/ProjectActor.php
  - app/Filament/Pages/Schedule.php
  - app/Filament/Resources/Contacts/ContactResource.php
  - app/Filament/Resources/Contacts/Pages/CreateContact.php
  - app/Filament/Resources/Contacts/Pages/ListContacts.php
  - app/Filament/Resources/Contacts/Pages/ViewContact.php
  - app/Filament/Resources/Contacts/RelationManagers/AppointmentsRelationManager.php
  - app/Filament/Resources/Contacts/RelationManagers/ProjectsRelationManager.php
  - app/Filament/Resources/Contacts/Schemas/ContactInfolist.php
  - app/Http/Middleware/KeepBookingLinkPrivate.php
  - app/Icm/BusinessClock.php
  - app/Icm/ConnectorChecks.php
  - app/Icm/FolderSync.php
  - app/Icm/RoomDropChecks.php
  - app/Jobs/SendDueBookingTexts.php
  - app/Livewire/BookingPage.php
  - app/Livewire/ConversationThread.php
  - app/Models/Appointment.php
  - app/Models/BookingLink.php
  - app/Models/BookingSend.php
  - app/Models/Contact.php
  - app/Models/Project.php
  - app/Models/ScheduleBlock.php
  - app/Providers/AppServiceProvider.php
  - app/Records/CustomerCard.php
  - app/Rooms/Leads/LeadsSection.php
  - app/Rooms/ScheduleAndBooking/Availability.php
  - app/Rooms/ScheduleAndBooking/BookingFileType.php
  - app/Rooms/ScheduleAndBooking/BookingLinkLookup.php
  - app/Rooms/ScheduleAndBooking/BookingRefused.php
  - app/Rooms/ScheduleAndBooking/BookingSettings.php
  - app/Rooms/ScheduleAndBooking/ReminderSchedule.php
  - app/Rooms/ScheduleAndBooking/ResolveBookingLink.php
  - app/Rooms/ScheduleAndBooking/ScheduleAndBookingServiceProvider.php
  - app/Rooms/ScheduleAndBooking/ScheduleSection.php
  - app/Rooms/ScheduleAndBooking/ServiceToBook.php
  - app/Rooms/ScheduleAndBooking/VisitActions.php
  - app/Rooms/ScheduleAndBooking/WithdrawUnsentLink.php
  - app/SendRule/ReplyParts.php
  - app/Support/PhoneNumber.php
  - bootstrap/providers.php
  - database/migrations/2026_11_02_000001_create_booking_links_table.php
  - database/migrations/2026_11_02_000002_create_appointments_table.php
  - database/migrations/2026_11_02_000003_add_kind_shape_check_to_appointments_table.php
  - database/migrations/2026_11_02_000004_create_booking_sends_table.php
  - database/migrations/2026_11_02_000005_add_automatic_texts_to_contacts_table.php
  - resources/css/app.css
  - resources/views/booking/rate-limited.blade.php
  - resources/views/components/booking/button.blade.php
  - resources/views/components/booking/day.blade.php
  - resources/views/components/booking/error.blade.php
  - resources/views/components/booking/footer.blade.php
  - resources/views/components/booking/notice.blade.php
  - resources/views/components/booking/sms.blade.php
  - resources/views/components/booking/time.blade.php
  - resources/views/filament/pages/schedule.blade.php
  - resources/views/filament/rooms/schedule-section.blade.php
  - resources/views/layouts/customer.blade.php
  - resources/views/livewire/booking-page.blade.php
  - resources/views/livewire/conversation-thread.blade.php
  - routes/console.php
  - routes/web.php
findings:
  critical: 0
  warning: 5
  info: 6
  total: 11
status: issues_found
---

# Phase 8: Code Review Report

**Reviewed:** 2026-11-07
**Depth:** standard
**Files Reviewed:** 88 source, view, migration, route and config files. The other 62 paths in scope (tests, factories, docs, examples, icon components) were skimmed for reliability only and are not listed.
**Status:** issues_found

## Summary

The booking core is carefully built. I found no BLOCKER. The things I tried to break all held:

- **Booking link and page security.** The token is 256 random bits and only its hash is stored. Livewire state is `#[Locked]`. The tapped time is re-validated by `Availability::offers()` under the lock. A refused or closed link reads the same as an unknown one.
- **Concurrency and send claims.** Overlap checks happen inside the cache lock plus the transaction. The unique `booking_link_id` index is a backstop. `booking_sends` claims use the same conditional-update pattern as owner alerts.
- **Views.** There are no `{!! !!}` outputs in the booking views.
- **Timezone handling.** Carbon 3 elapsed-time arithmetic matches what `Availability` assumes.

The remaining defects are in the failure and edge paths. The first three below touch the "text goes out exactly once and means what it says" guarantee. The T-08-27 plain-token issue is already recorded in 08-SECURITY.md and is not repeated here.

No finding reopens a locked decision. IN-02 notes where a locked decision (D-26) leaves a soft spot.

## Warnings

### WR-01: A booking text can be sent twice when the outcome write fails or the process dies after the send

**File:** `app/Actions/SendBookingText.php:122-149` and `:393-413`
**Issue:** `send()` calls `OutboundSender::send()`. Only after that does it write `sent_at` through `end()`. `end()` swallows a database error and returns false. A process kill between the two steps, or a transient DB error, leaves the row claimed with no outcome. After `SendOwnerAlert::CLAIM_WAIT_MINUTES` the sweep re-claims it and sends the confirmation or reminder again. The message already carries `meta.booking_send_id`, but nothing looks for it before sending. The class docblock promises "a text is sent at most once". That promise only holds when the post-send write succeeds. The same lease pattern in `SendOwnerAlert` has the same hole, but a customer getting two reminders, or a repeat confirmation, is customer-visible.
**Fix:** Make the send idempotent on the message that already exists. Check for it before sending, and in `answerRefusal` and `end` paths:
```php
$existing = Message::query()
    ->where('conversation_id', $conversation->id)
    ->where('meta->booking_send_id', $send->id)
    ->first();

if ($existing !== null) {
    $this->end($send, ['sent_at' => $this->now(), 'message_id' => $existing->id]);

    return;
}
```
Also make the `sent_at` write retry once, or log loudly when `end()` returns false after a successful send.

### WR-02: A reminder due at the send-window boundary falls out of the window on a one-minute delay, and a reminder dropped at visit time leaves no trace

**File:** `app/Rooms/ScheduleAndBooking/ReminderSchedule.php:96-110`, `app/Actions/SendBookingText.php:187-199` and `:263-272`
**Issue:** `ReminderSchedule::reminderDue()` deliberately moves an out-of-window reminder to exactly the window's end (for example 20:00). `SendBookingText::nextOpening()` then judges "inside the window" to the minute, so that row is inside only during the 20:00 minute. The sweep is a queued job that runs every minute (`Schedule::job(new SendDueBookingTexts)`), so queue latency over 59 seconds is realistic. A row reached at 20:01 gets `moveTo()` the next opening instead. For a visit early the next morning, that opening may be after the visit has started. `drop()` then ends the row. `drop()` only writes a timeline note when `waited_on_unread_at` is set, so the owner sees nothing: the reminder silently never goes out. The comment says "a few seconds late" is tolerated, but the schedule is built to sit on the edge, which makes this the common case rather than the rare one.
**Fix:** Schedule the moved reminder a margin before the close (for example `sendWindowEnd` minus 5 minutes, floored at the window start), or have `nextOpening()` treat a row whose due time was inside the window as still sendable for a short grace period. Separately, write a "Reminder text not sent: the visit started first" note when a row ends in `drop()` while its `due_at` was moved.

### WR-03: A booking link that reached the customer is revoked when a step after the send throws

**File:** `app/Actions/RunFrontDesk.php:625-643` and `app/Actions/SendOwnerReply.php:190-212`
**Issue:** In both places the link is minted, the text is sent, and then `attachMessage()` runs inside the same `try`. If `attachMessage()` (a single `save()`) throws, the `catch (Throwable)` branch calls `withdrawLink()` or `withdrawUnsentLink->handle()`. That revokes a link that is already in the customer's text and restores the older links, which were superseded on purpose. The customer taps "This link does not work". The owner is told nothing. In `SendOwnerReply` the exception is also rethrown to the owner as if the send failed, although the text went out. The failure window is small, but the cost is a dead link in a delivered text.
**Fix:** Withdraw only when the send itself threw. Run `attachMessage()` after the guarded block and treat its failure as report-only, since `BookAppointment::alertOwner()` already recovers the carrying message through `meta.booking_link_id`:
```php
try {
    $sent = $this->sendCombinedReply->handle(...);
} catch (DomainException $e) { $this->withdrawLink($link, $supersededIds); ... }

if ($link !== null) {
    try { $this->mintBookingLink->attachMessage($link, $sent); } catch (Throwable $e) { report($e); }
}
```

### WR-04: Refusal handling matches on exception message text copied from other classes

**File:** `app/Actions/SendBookingText.php:46`, `:56`, `:229-258` and `app/Livewire/BookingPage.php:189-203`
**Issue:** `SendBookingText::answerRefusal()` decides what to do by comparing the `DomainException` message to string literals. Several of them are copied from elsewhere:
- `'Another reply to this customer is being sent.'` from `OutboundSender::takeLock()`.
- `'The assistant is paused in this conversation.'` from `OutboundSender::whyBlocked()`.
- `'Texts are limited to 1,600 characters.'` from `OutboundSender::whyBodyIsRefused()`.
- Prefixes such as `'Opted out by texting STOP'`.

Note also that `takeLock()` has a second, different "being sent" sentence for the owner origin. A reworded sentence in `OutboundSender` silently turns a "wait and retry" into `report()` plus a permanently failed row (the final `else`). `BookingPage::refuse()` does the same thing by comparing `BookingRefused::taken()->getMessage()`, which builds a new exception only to read its text. Tests would catch a reword today, but the coupling is invisible at the edit site.
**Fix:** Put these on `OutboundSender` as constants or a small `Refusal` enum carried on the exception, the way `NOT_AGREED`, `UNREAD_CUSTOMER_TEXT` and `APPOINTMENT_NOT_AHEAD` already are, and compare against those. For `BookingRefused`, give it a `reason` property (the way it already has `field`) and switch on it.

### WR-05: Customer-facing page ships "placeholder wording for the example business" as its privacy notice

**File:** `resources/views/components/booking/footer.blade.php:9`
**Issue:** The footer of every booking page, for every business, says "This is placeholder wording for the example business." It also makes a factual claim about data handling ("does not ask for your name, phone number or address") that the code does not own, because the page and its rate limiter record the visitor's IP address. Real customers will read this on a live link. If the Phase 11 wording pass slips, it ships as is.
**Fix:** Either make the notice owner-supplied (a field or file read through `ActiveConfig`), or block going live until it is rewritten (for example a validator warning while the example text is in use). At minimum, drop the "placeholder" sentence and state only what is true.

## Info

### IN-01: Doc comment contradicts the code on drive buffer for hand bookings

**File:** `app/Rooms/ScheduleAndBooking/Availability.php:108`
**Issue:** `isFree()` says "Booking by hand passes a buffer of 0." `BookAppointment::store()` (`app/Actions/BookAppointment.php:171-174`) passes `driveBufferMinutes` from the booking file, which matches D-08 ("every appointment's span ... the new one included"). The code is right and the comment is wrong. The docblock on `offers()` repeats the "Booking by hand does not call this; it asks isFree()" framing.
**Fix:** Change the sentence to "Booking by hand passes the file's drive buffer, or 0 with no booking file."

### IN-02: The booking-text chokepoint trusts a caller-declared `meta.parts`

**File:** `app/Actions/OutboundSender.php:375-407`
**Issue:** `refuseBookingText()` requires `meta.appointment_id` and a `meta.parts` list without `model_words`, but it never checks that the body was built from the owner's booking line. Any caller that sets `kind: BookingText` with a parts list of its own skips the safety reading entirely. Today only `SendBookingText` does that, and `tests/Unit/AppointmentWritesTest.php` keeps the AI namespaces away. D-26 (Ian's amendment) is what allows no reading, so this is not a reopened decision, only a note that the guard is declarative. The pre-existing `code_sentences` part has the same shape. CLAUDE.md asks the chokepoint to refuse "without the checks its kind needs".
**Fix:** Have the chokepoint recompute the line itself: accept the `BookingSend` id and re-render the owner's line from `BookingSettings`, then compare it to the body. Or add an architecture test that only `SendBookingText` may pass `MessageKind::BookingText`.

### IN-03: The hand-booking dialog forecasts a confirmation text that may not be sent

**File:** `app/Filament/Resources/Contacts/RelationManagers/AppointmentsRelationManager.php:229-238`
**Issue:** `confirmationStatus()` returns "A confirmation text will go out." for any contact who is not opted out and may get automatic texts. It does not check that the contact has a phone number. An email-only contact added by hand is a normal case (`AddContactByHand` allows it). `SendBookingText` then skips the row with a "no phone number" note. The forecast also says nothing when the booking is made outside the send window and the text waits until the next opening.
**Fix:** Add `blank($contact->phone_e164) => 'No confirmation text will go out. This customer has no phone number on file.'` before the default, and optionally a "will go out at the next opening" variant.

### IN-04: The Upcoming and Past filters overlap for a span that crosses midnight, and Past can hold a live visit

**File:** `app/Filament/Pages/Schedule.php:277-290`
**Issue:** Upcoming is `ends_at >= start of today` and Past is `starts_at < start of today`, both over booked rows. A booked block or visit that started yesterday and ends today or later appears in both. This is minor, but a block that spans days (for example "truck in the shop, Mon to Wed") shows under both filters and under two day headings.
**Fix:** Make Past `ends_at < start of today` for booked rows, or document that overlap is intended.

### IN-05: The booking page's rate-limited notice has no way to retry without a reload

**File:** `app/Livewire/BookingPage.php:111-129`, `resources/views/livewire/booking-page.blade.php:2-3`
**Issue:** Once `rateLimited` is true, the view renders only the notice. There is no button or poll that calls the component again. The notice says "Wait a minute, then try again", but the `#[Locked]` flag stays true until the customer reloads the page. The 20-per-minute limit is generous, so this is rare.
**Fix:** Clear the flag in `render()` when `RateLimiter::availableIn($key) === 0`, or add a "Try again" button that calls `back()`.

### IN-06: The customer card's "Next appointment" line depends on the clock, so a rendered card goes stale

**File:** `app/Records/CustomerCard.php:259-268`
**Issue:** The card is re-rendered only on `CustomerRecordChanged`. Its "Next appointment" line is filtered by `starts_at->isFuture()` at render time. Once the visit starts and nothing else changes, any stored card still names a visit that is already in the past as "Next". The class docblock was updated to admit the clock is read here, so this is known. It is worth a note because the same card is what an owner or the front desk reads.
**Fix:** Re-render affected cards on a schedule shortly after each booked visit's start (a small sweep keyed on `booked ahead` rows), or label the line with the time it was true.

---

_Reviewed: 2026-11-07_
_Reviewer: Claude (gsd-code-reviewer)_
_Depth: standard_
