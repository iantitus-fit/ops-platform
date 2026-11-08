---
phase: "8"
slug: "schedule-and-booking"
status: open
# threats_open = count of OPEN threats at or above workflow.security_block_on severity (the blocking gate)
threats_open: 0
asvs_level: 1
created: "2026-11-07"
---

# Phase 8 — Security

> Per-phase security contract: threat register, accepted risks, and audit trail.

---

## Trust Boundaries

| Boundary | Description | Plan |
|----------|-------------|------|
| application code to the appointments table | any class could write a row; only named actions may (D-15) | 08-01 |
| a copy of the database to a working link | a leaked database must not hold usable tokens (D-12) | 08-01 |
| platform to customer phone | every automated text crosses here through OutboundSender | 08-02 |
| caller attributes to the sender | a caller names the kind and meta; the sender re-checks them | 08-02 |
| customer text to the front desk run | untrusted words decide, through the reading and the label, whether an alert goes | 08-03 |
| platform to the owner's phone | alert words quote customer text | 08-03 |
| owner-edited folder to the live version | the sync is the only gate between a folder edit and what customers receive | 08-04 |
| the live version to the appointments it governs | dropping the room would silence reminders for booked visits | 08-04 |
| customer page to availability | a tapped time arrives from the browser and must be one the server offers | 08-05 |
| owner's browser to the Filament action | form values (time, job id, note) arrive from the browser | 08-06 |
| two concurrent bookings | two workers may try one slot at once | 08-06 |
| anyone holding a URL to the booking action | the token is the only credential; it may be forwarded or leaked | 08-07 |
| the database to an attacker with a copy | stored tokens must not work | 08-07 |
| the public internet to /book/{token} | anonymous; the token is the only credential | 08-08 |
| the browser to Livewire's update endpoint | every tap is a POST whose payload the browser controls | 08-08 |
| model output to the customer's phone | the model chooses the label; code chooses whether a link rides along | 08-09 |
| a minted link to a send that may fail | a link that never reached the customer must not stay usable | 08-09 |
| the owner's browser to the approve action | the browser sends the box's value; the server decides whether a link may ride | 08-09 |
| test helpers to CI's verdict | a helper that swallows errors can turn a broken page into a pass | 08-10 |
| booking time to customer texts | due times decided at booking govern when the business texts a customer | 08-11 |
| the scheduler to the customer's phone | the platform starts a text with no customer text to answer | 08-12 |
| two senders (sweep and immediate) to one row | both may try the same row at once | 08-12 |
| owner's browser to the row actions | the record id and form values arrive from the browser | 08-13 |
| owner's browser to the schedule's actions | record ids and block times arrive from the browser | 08-14 |
| owner input to the contacts table | a hand-typed phone or email could duplicate or hijack a customer record | 08-15 |
| the switch to OutboundSender's D-21 check | the switch is the only stored consent for a never-texted contact | 08-15 |
| owner-typed visit note to the card the front desk reads | owner text enters a document a model reads | 08-16 |

---

## Threat Register

The register is the 16 plans' threat models (authored at plan time). T-08-SC appears in every plan with the same disposition and is listed once. Evidence was gathered at ASVS level 1 by the security auditor on 2026-11-07; the nine most security-relevant test files were re-run green (143 tests).

| Threat ID | Category | Component | Severity | Disposition | Mitigation | Status | Evidence |
|-----------|----------|-----------|----------|-------------|------------|--------|----------|
| T-08-01 | Information disclosure | booking_links.token_hash | high | mitigate | Hash only, 64 characters, unique, hidden from arrays; the plain token is never stored: every stored or printed copy of a sent text writes the booking address as /book/[link] (plan 08-17) and links sent before that were revoked (plan 08-18) | closed | app/Support/BookingLinkTokens.php (hide()); OutboundSender deliver() stores BookingLinkTokens::hide($body) (app/Actions/OutboundSender.php:269) and deliverExisting() the same (:302), while the channel gets the working link; a failed send's message is hidden too (:336); RecordInbound stores a pasted link hidden (app/Actions/RecordInbound.php:148); every records/ file goes through one write funnel that hides (app/Records/RecordsWriter.php:185); the fake carrier's log line is hidden (app/Channels/FakeTextChannel.php:64); the session's previous URL is hidden (app/Http/Middleware/StartSessionHidingBookingTokens.php:32); the front desk's own words never carry a booking address (app/SendRule/DecideSendOrDraft.php:72) and OutboundSender refuses a text holding /book/[link] (:606). Tests: tests/Feature/Booking/BookingLinkLeakTest.php 'gives the customer a working link and stores the scheduling reply with it hidden (D-12)', 'stores an approved scheduling draft with its link hidden (D-12, D-45)', 'lists every table and finds a planted token, so the search is not vacuous', 'leaves no token in the log or in any job payload during a send (D-12)', 'keeps the token out of a failed send\'s record at the chokepoint (D-12)', 'writes every records file with the token hidden, even from a row written before the fix (D-12)', 'keeps the token out of the session\'s previous URL (D-12)'. Links sent before the fix: database/migrations/2026_11_07_000001_revoke_booking_links_sent_before_tokens_were_hidden.php revokes every link that had not booked and queues the card redraws; tests/Feature/Booking/LinksBeforeTheFixTest.php 'revokes every link sent before the fix that has not booked (D-12)', 'leaves a booked link and an earlier revocation as they were (D-12, U-14)', 'rewrites no stored text (D-12)', 'redraws the folder so no file holds a token from before the fix (D-12)'. Full suite green (3051 tests). Hash, hidden and unique as before: app/Models/BookingLink.php:29, app/Actions/MintBookingLink.php:55, migration 000001 token_hash unique, AppointmentTableTest 'keeps the token hash out of arrays' |
| T-08-02 | Tampering | appointments rows written outside the actions | medium | mitigate | AppointmentWritesTest scans app/ for every write shape | closed | tests/Unit/AppointmentWritesTest.php ('only the named actions write the appointments table' lists 6 actions; 'flags every kind of write' datasets) |
| T-08-03 | Tampering | malformed appointments row | medium | mitigate | CHECK on Postgres, triggers on SQLite, scoped models | closed | database/migrations/2026_11_02_000003_add_kind_shape_check_to_appointments_table.php; AppointmentTableTest 'refuses a row of the wrong shape' (10 cases); global scopes app/Models/Appointment.php:55 and ScheduleBlock.php |
| T-08-04 | Repudiation | who booked, cancelled, marked done | low | mitigate | actor and user id columns on every row | closed | migration 000002 booked_by/cancelled_by/*_user_id; set in BookAppointment.php:189,245, CancelAppointment.php:64, MarkAppointmentDone.php:66, BlockTime.php:108, RemoveBlock.php:47 |
| T-08-05 | Elevation of privilege (compliance) | automatic text to a never-texted number | high | mitigate | whyBlocked() refuses Automated unless hasTextedBusiness() or switch on; opt-out first | closed | app/Actions/OutboundSender.php:177 (opt-out first), :191-192 (NOT_AGREED); app/Models/Contact.php:70,92; only other channel send is the owner alert (SendOwnerAlert.php:71); AutomaticTextsRuleTest |
| T-08-06 | Spoofing | booking text naming another customer's appointment | medium | mitigate | appointment read through the conversation's own contact | closed | OutboundSender.php:389 `$conversation->contact->appointments()->whereKey(...)`; OutboundBookingKindTest 'refuses a booking text that does not name ... its customer' |
| T-08-07 | Tampering | model words smuggled into a booking text | high | mitigate | meta.parts present and no model_words | closed | OutboundSender.php:385; OutboundBookingKindTest 'refuses ... carries the model's words' |
| T-08-08 | Denial of service (safety) | reminder over an unanswered danger text | high | mitigate | inbound with handled_at null refuses as a wait | closed | OutboundSender.php:398-405 (UNREAD_CUSTOMER_TEXT); OutboundBookingKindTest 'waits while a customer text is unread' |
| T-08-09 | Tampering | booking link under a complaint reading | medium | mitigate | booking_link arm requires allowsOwnerSentences() | closed | OutboundSender.php:453; OutboundBookingKindTest 'holds a booking link to the owner-sentence rule' (both ways) |
| T-08-10 | Tampering | text that tries to move or cancel a visit | high | mitigate | no text writes an appointment; owner alerted | closed | arch rule tests/Unit/AppointmentWritesTest.php (App\Ai and App\SendRule not using the 6 actions; grep of app/Ai and app/SendRule finds none); NoTextBookingTest 'never books, moves or cancels a visit from a text' |
| T-08-11 | Denial of service (safety) | change request hiding a danger alert | high | mitigate | change_request never replaces or sits beside higher alerts | closed | app/Actions/RunFrontDesk.php:479-512 (swappable only DraftWaiting/CheckIn, higherAlertRaised); ChangeRequestAlertTest 'gives way to a higher alert' (5 reasons) |
| T-08-12 | Repudiation | promise approved from draft_waiting with visit unchanged | medium | mitigate | change request takes draft_waiting's place for a booked customer | closed | RunFrontDesk.php:479-486; ChangeRequestAlertTest 'swaps draft-waiting for the change request' |
| T-08-13 | Information disclosure | stale customer quote under Visit booked | low | mitigate | booked alert quotes no customer text | closed | app/Actions/OwnerAlertText.php:61 `$quotes = $reason !== AlertReason::AppointmentBooked`; BookingAlertTest 'quotes no customer text' |
| T-08-14 | Tampering | owner lines sent to customers | medium | mitigate | sync refuses missing/stray placeholders, ! and em dash | closed | app/Rooms/ScheduleAndBooking/BookingFileType.php:106-146 (lineErrors, {link} exactly once); BookingFileCheckTest 'refuses a broken booking file in plain words' |
| T-08-15 | Tampering (integrity) | two owners of the schedule | medium | mitigate | D-36 refusal while a connector owns schedule and room is live | closed | app/Icm/ConnectorChecks.php:76 scheduleOwnerErrors; ScheduleAndBookingSyncTest 'refuses a connector that owns the schedule while the room is live' |
| T-08-16 | Denial of service | dropping the room with visits booked | medium | mitigate | D-37 refusal naming count and earliest; previous stays live | closed | app/Icm/RoomDropChecks.php:33; wired app/Icm/FolderSync.php:68; ScheduleAndBookingSyncTest 'refuses dropping the room with a visit booked ahead' (live version id unchanged) |
| T-08-17 | Information disclosure | refusal text quoting folder content | low | mitigate | messages name fields and placeholders only | closed | BookingFileType.php:106-146 messages built from field and placeholder names only; docblock states T-08-17 |
| T-08-18 | Tampering | tapped start outside the offered list | high | mitigate | offers() recomputes server-side, exact instant; re-asked in the lock | closed | app/Rooms/ScheduleAndBooking/Availability.php:93-105 (equalTo at :98); BookAppointment.php:226; BookingPageTest 'refuses a time the server did not offer' |
| T-08-19 | Tampering | time from phone's clock or zone | medium | mitigate | moment passed in from server; zone from ActiveConfig | closed | Availability.php:53,95 `$this->config->timezone()`; BookingPage.php:73,113 CarbonImmutable::now() server side; browser value only an ISO instant checked by offers() |
| T-08-20 | Denial of service | expensive availability per request | low | accept | two range queries per draw, in PHP; page rate limit bounds calls | closed | Acceptance reason still holds: Availability.php:133-150 reads booked and blocks once per draw (2 queries); GET throttle AppServiceProvider.php:97 (30/min/IP), taps limited BookingPage.php:291. Needs an entry in the SECURITY.md accepted-risks log |
| T-08-21 | Tampering | double booking under a race | high | mitigate | Cache::lock around a transaction re-checking isFree() | closed | BookAppointment.php:148 lock, :174 isFree re-check in store(); BookAppointmentTest 'refuses while another booking holds the lock, and two overlapping bookings never both store' |
| T-08-22 | Elevation of privilege | job id from another contact or a closed job | medium | mitigate | job read through contact's projects(), closed_at null, row lock | closed | BookAppointment.php:180; BookAppointmentTest 'refuses a closed job and another customer's job' |
| T-08-23 | Tampering | stored XSS through visit note | medium | mitigate | Blade escaping and e() on aria-label and titles | closed | VisitActions.php:204 rowButtonName e(); no HtmlString/{!! in app/Filament appointment code; AppointmentsRelationManagerTest 'escapes a hostile visit note' |
| T-08-24 | Elevation of privilege | agent code booking a visit | medium | mitigate | arch rule; writes scan allows only the actions | closed | tests/Unit/AppointmentWritesTest.php arch('no agent code books, cancels or marks a visit done'); grep app/Ai app/SendRule finds no use |
| T-08-25 | Tampering | typed time stored in wrong zone | medium | mitigate | picker reads business zone; change-day tests | closed | AppointmentsRelationManager.php:131 ->timezone(displayTimezone()), :150 parse; AppointmentsRelationManagerTest 'reads the owner's time in the business zone on both change days' |
| T-08-26 | Spoofing | guessing a booking link | high | mitigate | 256-bit token; hash lookup; unknown and revoked same state | closed | MintBookingLink.php:41 bin2hex(random_bytes(32)); ResolveBookingLink.php:32-35,62 (DoesNotWork for both); BookingPageTest 'renders an unknown token and a revoked one the same' |
| T-08-27 | Information disclosure | a database copy yielding working links | high | mitigate | only the hash is stored; token never logged or put in an exception; every stored copy of a sent text hides the address (08-17) and links from before that are revoked (08-18) | closed | Evidence as T-08-01: no table, file, log line or queued job holds a working token after a send (BookingLinkLeakTest, which searches every table the database lists, the business folder, the log and the queue), and a copy or backup made before the fix holds only revoked links (LinksBeforeTheFixTest). The text provider's own log of every sent text cannot be redacted by the platform and is on the attorney list (STATE.md blockers, Phase 02.1; docs/BLUEPRINT.md section 10) |
| T-08-28 | Tampering | replaying a link to book twice | medium | mitigate | state check under the lock plus unique booking_link_id | closed | BookAppointment.php:220 closedState under lock, :248 UniqueConstraintViolationException; migration 000002 booking_link_id unique; BookingLinkTokenTest 'books at most once, ever' and 'keeps the unique index as the backstop' |
| T-08-29 | Tampering | booking a time not offered or service turned off | medium | mitigate | offers() and service re-check inside the lock | closed | BookAppointment.php:220-226; ResolveBookingLink.php closedState book_online check; BookingLinkTokenTest 're-checks the service at the tap' and 're-checks the time at the tap' |
| T-08-30 | Repudiation | page booking with no record of how it came | low | mitigate | booking_link_id and booked_by customer; alert subject is carrying message | closed | BookAppointment.php:243-245; alertOwner :324 |
| T-08-31 | Denial of service (alerts) | second booking alert dropped as duplicate | medium | mitigate | each link rides a new message, never an m0 key | closed | BookAppointment.php:324-345 (subject from link message or meta.booking_link_id, no subject means report and no alert); BookingAlertTest 'stores one booking alert per key and a second for another message'; BookingLinkTokenTest 'alerts a second booking from a new link' |
| T-08-32 | Tampering | tampered Livewire payload | high | mitigate | #[Locked] token, chosen, justBooked, error, version; choose()/book() re-check | closed | app/Livewire/BookingPage.php:35-51 (#[Locked] x6), :84 offers(), book() -> fromLink() re-checks in lock; BookingPageTest 'refuses a browser that changes what the server set (Locked)' and 'refuses a time the server did not offer' |
| T-08-33 | Denial of service | brute-forcing links or hammering taps | high | mitigate | GET limiter 30/min/IP; RateLimiter::attempt 20/min per token hash and IP | closed | AppServiceProvider.php:97, routes/web.php:41 throttle:booking; BookingPage.php:291-296; BookingPageThrottleTest ('limits the page GET', 'limits the taps inside the page') |
| T-08-34 | Information disclosure | token leaking through Referer or search | medium | mitigate | no-referrer and noindex as meta tags and headers | closed | app/Http/Middleware/KeepBookingLinkPrivate.php:21-22; routes/web.php:41; resources/views/layouts/customer.blade.php:6-7; BookingPageTest 'sends the private headers and the no-script line' |
| T-08-35 | Information disclosure | forwarded link showing who booked | medium | mitigate | page shows only business and service | closed | resources/views/livewire/booking-page.blade.php (only businessName, serviceName, visit time); BookingPageTest 'never shows the customer's name or phone in any state' |
| T-08-36 | Tampering | stored XSS via service or business name | medium | mitigate | Blade escaping everywhere, no raw output | closed | grep of resources/views/booking, components/booking, livewire/booking-page, layouts/customer finds no {!! / HtmlString / @php; every value is {{ }} |
| T-08-37 | Information disclosure | exception text on a failed book | low | mitigate | fixed plain line and report() | closed | BookingPage.php:131-133 (report + error = 'failed'); BookingPageTest 'keeps the check step when booking fails another way (T-08-37)' |
| T-08-38 | Tampering | model writing or altering a URL | high | mitigate | URL built by code from link_line and route(); model words pass classifier first; on approval code adds the line | closed | RunFrontDesk.php:609-610 renderLink(route('booking.show', $token)) after the verdict; SendOwnerReply.php:170-205 line added after owner's words; BookingLinkTest 'sends the scheduling reply with the owner's link line' |
| T-08-39 | Spoofing | link for wrong service or a guessed one | medium | mitigate | D-02 never guesses; book_online and room checked by ServiceToBook on both paths | closed | app/Rooms/ScheduleAndBooking/ServiceToBook.php:62-79,93-101 (resolve, book_online, SERVICE_UNCLEAR); used RunFrontDesk.php:604 and SendOwnerReply.php:173-174; BookingLinkTest D-02 cases |
| T-08-40 | Information disclosure | usable link left by a failed or refused send | medium | mitigate | length measured before minting; on exception revoke and restore superseded | closed | RunFrontDesk.php:652 linkFits, :689 withdrawLink, :608 idsAMintWouldSupersede; SendOwnerReply.php:200-207; BookingLinkTest 'revokes the link when the send is refused' / 'when anything else fails'; ApprovedDraftBookingLinkTest 'revokes the link and gives back the earlier one when the text channel fails' |
| T-08-41 | Tampering | link under a complaint reading | medium | mitigate | agent path needs allowsOwnerSentences(); on approval owner decides, box unchecked on bad reading, resolved_by recorded | closed | OutboundSender.php:453; SendOwnerReply.php:127,153 (bookingLinkStartsOn); ApprovedDraftBookingLinkTest 'starts the box unchecked when the reading is not clean' and 'sends the link when the owner checks a box that started unchecked' |
| T-08-42 | Repudiation (test integrity) | focus wait hiding every error | medium | mitigate | catches only the locator timeout, rethrows rest, proven by a case | closed | tests/Pest.php waitForFocusSelector (regex on "Timeout Nms exceeded", else rethrow); BookingPageBrowserTest 'rethrows anything but a timeout (IN-08)' |
| T-08-43 | Repudiation (test integrity) | size or axe check that never runs | medium | mitigate | helper proven on 47.5 px stub; browser tests must run, skip counts as failure | closed | BookingPageBrowserTest 'proves the size check can fail' (47.5 px stub); no ->skip/markTestSkipped in browser tests; CI installs Chromium and builds assets (.github/workflows/tests.yml); no-skip gate is the plan's fails_when (08-10-PLAN.md:133,169,199), not a phpunit failOnSkipped setting |
| T-08-44 | Denial of service (accessibility) | page a keyboard or screen-reader user cannot finish | high | mitigate | keyboard walks at two widths, names, focus, targets, axe in CI; VoiceOver walk at UAT | closed | BookingPageBrowserTest 'books a time by keyboard alone at desktop width', size dataset at 375 and 1280, 'finds no accessibility violation' (axe via pageAxeReport); live VoiceOver walk and CI-leg run remain UAT hand checks (WINDOWS.md entries 54-56, 60) |
| T-08-45 | Tampering (compliance) | reminder outside the send window | medium | mitigate | due times move to the window before, ends inclusive, wall clock | closed | app/Rooms/ScheduleAndBooking/ReminderSchedule.php:96-107; tests/Unit/Booking/ReminderScheduleTest.php (window ends, DST nights) |
| T-08-46 | Repudiation | duplicate reminders for one moment | low | mitigate | collapse at creation and unique (appointment_id, lead_minutes) | closed | ReminderSchedule.php:60-70 collapse; migration 000004:35 unique; ReminderScheduleTest 'keeps one row for two reminders that land together'; AppointmentTableTest 'keeps one send per appointment and lead time' |
| T-08-47 | Tampering (duplicate) | same reminder sent twice | medium | mitigate | one conditional update claims; 2-minute lease; conditional outcome writes | closed | app/Actions/SendBookingText.php:332 claim, :393 end (all whereNull outcome), SendOwnerAlert::CLAIM_WAIT_MINUTES = 2; BookingSendTest 'sends a row once when two senders race', 'texts once when two stale copies...', 'sends one text when the sweep enters while the first sender is mid-send' |
| T-08-48 | Elevation of privilege (compliance) | reminder to opted-out or never-agreed number | high | mitigate | chokepoint opt-out and D-21 at send time; refusals noted not retried | closed | OutboundSender.php:177,191; SendBookingText.php:136 (skip with note), answerRefusal; BookingSendTest 'notes and skips a refusal'; OutboundBookingKindTest 'checks opt-out first and the automatic-texts rule for a booking text' |
| T-08-49 | Denial of service (safety) | reminder over an unanswered danger text | high | mitigate | D-26 unread wait at chokepoint and in sender; never times out into a send | closed | OutboundSender.php:398-405; SendBookingText.php answerRefusal giveBack (no timeout path to a send); BookingSendTest 'waits while a customer text is unread, then sends' |
| T-08-50 | Tampering | booking text in a new thread while owner handles a paused one | medium | mitigate | paused open conversation used and refuses; no new conversation | closed | SendBookingText.php:131 openConversation()->first() (open includes paused); OutboundSender.php:187-188 paused refusal; BookingSendTest 'notes and skips a refusal' (paused case) |
| T-08-51 | Tampering (compliance) | text outside the send window | medium | mitigate | window re-checked at send time, due moved to next opening | closed | SendBookingText.php:121,187 (nextOpening, moveTo); BookingSendTest 'holds a row outside the send window and moves its due time', 'sends a row due exactly when the window closes' |
| T-08-52 | Denial of service | duplicate open conversation from racing sends | low | mitigate | savepoint plus partial unique index | closed | app/Actions/OpenConversationForText.php:32 nested DB::transaction, catch UniqueConstraintViolationException; conversations_one_open_per_contact (create_conversations migration:27); BookingSendTest 'keeps one open conversation when two sends race' |
| T-08-53 | Tampering | acting on another contact's appointment or a block via the tab | medium | mitigate | tab uses contact's scoped appointments; actions lock and re-read | closed | AppointmentsRelationManager.php:35 $relationship = 'appointments'; CancelAppointment.php:54 / MarkAppointmentDone.php:52 lockForUpdate()->find via scoped model; VisitActions.php:68,109 ROW_GONE; AppointmentActionsTest 'never cancels a block through the visit action' |
| T-08-54 | Repudiation | who cancelled or marked done | low | mitigate | user ids and times on the row, one timeline entry per action | closed | CancelAppointment.php:64-65,78; MarkAppointmentDone.php:66-67,72; AppointmentActionsTest 'cancels a visit and ends its pending texts', 'marks a started visit done' |
| T-08-55 | Tampering | reminder sent for a cancelled visit | medium | mitigate | cancel ends pending rows; sender re-checks state | closed | CancelAppointment.php:69 (BookingSend skipped_at); SendBookingText.php:104-108 isBooked/ahead drop; BookingSendTest 'never sends a cancelled visit's pending rows' |
| T-08-56 | Tampering | closing a job with a visit ahead from a stale screen | medium | mitigate | CloseProject refuses inside its lock | closed | app/Actions/CloseProject.php (lockForUpdate then blockedBy() inside the transaction); ProjectActionsTest 'refuses to close a job with a visit booked ahead'; AppointmentsRelationManagerTest 'refuses the close from a stale page and says why' |
| T-08-57 | Tampering | stored XSS via visit note in row label or notification | medium | mitigate | e() in every aria-label and title; Blade escaping | closed | VisitActions.php:77,118,163,204; AppointmentsRelationManager.php:165,174; AppointmentsRelationManagerTest 'escapes a hostile visit note' and 'escapes the service in the button name' |
| T-08-58 | Tampering | block over a booked visit or two blocks racing a booking | medium | mitigate | BlockTime overlaps checked under the same 'schedule:book' lock and transaction | closed | app/Actions/BlockTime.php:64 Cache::lock('schedule:book'), store() overlap query then BookingRefused::blockOverBooking; SchedulePageTest 'refuses a bad block beside the field' |
| T-08-59 | Elevation of privilege | appointment action on a block row or reverse | medium | mitigate | visible only for own kind; re-read through scoped model under lock; null does nothing | closed | VisitActions.php:211 isVisit + visible(), Schedule.php:243 ScheduleBlock::find / VisitActions Appointment::find; RemoveBlock.php:39, CancelAppointment.php:54 lockForUpdate; SchedulePageTest 'offers each row only its own kind's actions', 'refuses a cross-kind call at the action...', 'refuses a forged cross-kind call at the screen...' |
| T-08-60 | Tampering | stored XSS via block or visit note on the schedule | medium | mitigate | Blade escaping in cells; e() in aria-labels and titles | closed | Schedule.php:233-240 e() in aria-label, TextColumn state (escaped by default, no ->html()); SchedulePageTest 'escapes an owner's block note and visit note' |
| T-08-61 | Information disclosure | block shown on a customer's card or timeline | low | mitigate | blocks have no contact, no timeline entry; card uses scoped model | closed | ScheduleBlock global scope kind=block; BlockTime.php store() writes no activity; SchedulePageTest 'blocks time, shows the block under its day and writes no timeline entry'; AppointmentsRelationManagerTest 'never shows a block on the tab' |
| T-08-62 | Spoofing | duplicate contact for an existing number or email | medium | mitigate | phone-then-email matching beside the field; unique phone index backs it | closed | app/Actions/AddContactByHand.php (refuseAMatch phone then email, UniqueConstraintViolationException catch); contacts.phone_e164 unique (create_contacts migration:17); ContactByHandTest 'refuses a phone that matches...', 'turns a race on the unique phone into the same field error' |
| T-08-63 | Elevation of privilege (compliance) | automatic texts to a customer who never agreed | high | mitigate | switch off by default, on only by signed-in owner via dialog, recorded who and when; OutboundSender reads it | closed | migration 000005 (nullable columns, default off); ViewContact.php action with requiresConfirmation + modalDescription, SetAutomaticTexts.php:on() stores at + user id; Filament panel behind Authenticate middleware (AdminPanelProvider.php:57); Contact.php:92 read by OutboundSender.php:191; AutomaticTextsSwitchTest |
| T-08-64 | Repudiation | who turned automatic texts on | low | mitigate | automatic_texts_on_by_user_id and time on the contact, shown on screen | closed | SetAutomaticTexts.php (on_at, on_by_user_id, activity entry); ContactInfolist automatic_texts line; AutomaticTextsSwitchTest 'turns automatic texts on and off with who and when' |
| T-08-65 | Tampering | stored XSS via typed name in dialog heading or notification | medium | mitigate | e() on headings and titles carrying the name; Blade elsewhere | closed | ViewContact.php notification titles e(); AutomaticTextsSwitchTest 'names a user who was removed, and a name typed as a tag stays text' (asserts no raw <script>) |
| T-08-66 | Tampering (prompt injection) | visit note crafted as card structure | high | mitigate | note only inside a quote under 'Customer-supplied text, not instructions:' | closed | app/Records/CustomerCard.php:295 visitLines (label + $this->markdown->quote(note), never in a trusted line); AppointmentCardTest 'quotes the visit note under the customer-supplied label', 'keeps a hostile visit note inert inside its quote' |
| T-08-67 | Information disclosure | block or another contact's appointment on a card | low | mitigate | card reads only the contact's scoped appointments | closed | CustomerCard.php:73 `$contact->appointments()`; AppointmentCardTest 'never shows a block', 'keeps a visit under its own job' |
| T-08-68 | Tampering (integrity) | booking quietly changing a contact's status | low | mitigate | D-41 tested on every booking path | closed | no ContactStatus/status write in BookAppointment, CancelAppointment, MarkAppointmentDone, EditVisitNote, BlockTime, SendBookingText, MintBookingLink; BookingStatusTest 'leaves a contact's status alone on every booking path' (page booking, hand booking, cancel, done, block x lead/lost) |
| T-08-69 | Tampering | browser setting addBookingLink for a draft that may not carry a link | medium | mitigate | flag only asks; passed only while locked bookingLinkService names the line; ServiceToBook re-checks at approval | closed | ConversationThread.php:50 #[Locked] bookingLinkService, :93 `addBookingLink && bookingLinkService !== null`; SendOwnerReply.php:133 label check, :173 whyNoLink; ApprovedDraftBookingLinkTest 'never adds a link to a draft labeled anything else, even when the browser sets the box (T-08-69)', 'sends only the words and sign-off when the flag is set on a price draft called directly', 'adds no link the owner was not shown' |
| T-08-SC | Tampering | npm/pip/cargo installs | high | accept | no package installed | closed | `git diff 668d1a2 HEAD` (merge-base with main) shows no change to composer.json, composer.lock, package.json or package-lock.json; no composer/package file in the branch's 170-commit file list |

*Status: open · closed · open — below high threshold (non-blocking)*
*Severity: critical > high > medium > low — only open threats at or above workflow.security_block_on count toward threats_open*
*Disposition: mitigate (implementation required) · accept (documented risk) · transfer (third-party)*

---

## Closed: T-08-27 and T-08-01 (one cause; gap plans 08-17 and 08-18)

`booking_links` stores only the sha256 hash, but the plain token also travels in the link the customer is sent. `RunFrontDesk.php:609-610` (and the owner path through `SendOwnerReply`, D-45) puts `route('booking.show', $token)` into the reply text; `OutboundSender.php:257` stores that text as `messages.body`; `CustomerCard.php:417` prints sent reply bodies into the customer card in `records/`. A copy of the database or of the business folder therefore holds working links until each expires (1 to 30 days), books, or is superseded. D-12 says a copy of the database holds no working link, so the implementation falls short of it.

**Ian's ruling (2026-11-07):** fix it in a gap plan and keep D-12 as written. The gap plan must:

- Cover every place the full booking link lands, not only the two found: `messages.body`, the customer card in `records/`, timeline and activity summaries, queued job payloads and `failed_jobs`, and application logs.
  - Met by 08-17: the sent text (`messages.body`), a failed send's record, the fake carrier's log line, a customer's pasted link, every file under `records/` (the card, `customers/INDEX.md` and `open-leads.md` included, through RecordsWriter's one write funnel), timeline and alert quotes (they read the hidden row), the session's remembered page address and the queued job payloads all hold `/book/[link]` or no address. Met by 08-18 for what was written before: every link that had not booked was revoked, and the cards were redrawn.
- Store and print the sent text with the token replaced (for example `…/book/[link]`), while the customer still receives the real link.
  - Met by 08-17: `OutboundSender` sends the working link through the channel and stores `BookingLinkTokens::hide()` of it; the test 'gives the customer a working link and stores the scheduling reply with it hidden (D-12)' checks both.
- Add a test that sends a link, then searches the database and the records folder and finds no working token anywhere.
  - Met by 08-17: `BookingLinkLeakTest` sends a link on the agent path and on an owner-approved draft, then searches every table the database lists, the business folder, the log and the queued jobs. Plan 08-18's `LinksBeforeTheFixTest` runs the migration on rows and a card file written the old way and searches the folder again.

**Outside the platform's control (for the attorney list):** the text provider (Twilio) keeps its own log of every sent message body, booking links included. The platform cannot redact it; it belongs on the list of items an attorney reviews before a real client goes live (STATE.md blockers, Phase 02.1).
  - Met: the provider's log is on the attorney list in STATE.md blockers and in docs/BLUEPRINT.md section 10 (plan 08-18). The links in texts sent before 08-17 are revoked, but a text sent after it still reaches the provider's log with its working link until that link books, is replaced or expires. The host's request log, which records each page address and so each token, is a BLUEPRINT open item for the first deploy.

---

## Accepted Risks Log

| Risk ID | Threat Ref | Rationale | Accepted By | Date |
|---------|------------|-----------|-------------|------|
| AR-08-01 | T-08-20 | Availability reads booked visits and blocks with two range queries per draw, in PHP; the page's rate limits (30 GETs a minute per IP, tap limits in BookingPage) bound the cost. Accepted at plan time (08-05). | Plan 08-05 threat model | 2026-11-02 |
| AR-08-02 | T-08-SC | Phase 8 installs no package: composer.json, composer.lock, package.json and package-lock.json are unchanged since the phase branch left main (668d1a2). Accepted at plan time in every plan (04 D-18). | Plans 08-01 to 08-16 threat models | 2026-11-02 |

---

## Security Audit Trail

| Audit Date | Threats Total | Closed | Open | Run By |
|------------|---------------|--------|------|--------|
| 2026-11-07 | 70 | 68 | 2 | gsd-security-auditor (ASVS L1), at phase close |
| 2026-11-08 | 70 | 70 | 0 | gap plans 08-17 and 08-18; re-audit pending (/gsd-secure-phase 8) |

---

## Sign-Off

- [x] All threats have a disposition (mitigate / accept / transfer)
- [x] Accepted risks documented in Accepted Risks Log
- [x] `threats_open: 0` confirmed (T-08-27 and T-08-01 closed by gap plans 08-17 and 08-18; BookingLinkLeakTest, LinksBeforeTheFixTest and the full suite pass)
- [ ] `status: verified` set in frontmatter

**Approval:** pending the security re-audit (/gsd-secure-phase 8), which also adds the gap plans' threats from T-08-70 on and sets status

## Security Audit 2026-11-07

| Metric | Count |
|---|---|
| Threats found | 70 |
| Closed | 68 |
| Open | 2 |
