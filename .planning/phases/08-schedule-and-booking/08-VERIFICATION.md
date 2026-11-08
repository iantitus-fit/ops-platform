---
phase: 08-schedule-and-booking
verified: 2026-11-08T00:00:00Z
status: human_needed
score: 8/8 must-haves verified
covered_files: [".planning/phases/08-schedule-and-booking/08-01-PLAN.md",".planning/phases/08-schedule-and-booking/08-01-SUMMARY.md",".planning/phases/08-schedule-and-booking/08-02-PLAN.md",".planning/phases/08-schedule-and-booking/08-02-SUMMARY.md",".planning/phases/08-schedule-and-booking/08-03-PLAN.md",".planning/phases/08-schedule-and-booking/08-03-SUMMARY.md",".planning/phases/08-schedule-and-booking/08-04-PLAN.md",".planning/phases/08-schedule-and-booking/08-04-SUMMARY.md",".planning/phases/08-schedule-and-booking/08-05-PLAN.md",".planning/phases/08-schedule-and-booking/08-05-SUMMARY.md",".planning/phases/08-schedule-and-booking/08-06-PLAN.md",".planning/phases/08-schedule-and-booking/08-06-SUMMARY.md",".planning/phases/08-schedule-and-booking/08-07-PLAN.md",".planning/phases/08-schedule-and-booking/08-07-SUMMARY.md",".planning/phases/08-schedule-and-booking/08-08-PLAN.md",".planning/phases/08-schedule-and-booking/08-08-SUMMARY.md",".planning/phases/08-schedule-and-booking/08-09-PLAN.md",".planning/phases/08-schedule-and-booking/08-09-SUMMARY.md",".planning/phases/08-schedule-and-booking/08-10-PLAN.md",".planning/phases/08-schedule-and-booking/08-10-SUMMARY.md",".planning/phases/08-schedule-and-booking/08-11-PLAN.md",".planning/phases/08-schedule-and-booking/08-11-SUMMARY.md",".planning/phases/08-schedule-and-booking/08-12-PLAN.md",".planning/phases/08-schedule-and-booking/08-12-SUMMARY.md",".planning/phases/08-schedule-and-booking/08-13-PLAN.md",".planning/phases/08-schedule-and-booking/08-13-SUMMARY.md",".planning/phases/08-schedule-and-booking/08-14-PLAN.md",".planning/phases/08-schedule-and-booking/08-14-SUMMARY.md",".planning/phases/08-schedule-and-booking/08-15-PLAN.md",".planning/phases/08-schedule-and-booking/08-15-SUMMARY.md",".planning/phases/08-schedule-and-booking/08-16-PLAN.md",".planning/phases/08-schedule-and-booking/08-16-SUMMARY.md",".planning/phases/08-schedule-and-booking/08-17-PLAN.md",".planning/phases/08-schedule-and-booking/08-17-SUMMARY.md",".planning/phases/08-schedule-and-booking/08-18-PLAN.md",".planning/phases/08-schedule-and-booking/08-18-SUMMARY.md",".planning/phases/08-schedule-and-booking/08-19-PLAN.md",".planning/phases/08-schedule-and-booking/08-19-SUMMARY.md",".planning/phases/08-schedule-and-booking/08-20-PLAN.md",".planning/phases/08-schedule-and-booking/08-20-SUMMARY.md",".planning/phases/08-schedule-and-booking/08-21-PLAN.md",".planning/phases/08-schedule-and-booking/08-21-SUMMARY.md",".planning/phases/08-schedule-and-booking/08-22-PLAN.md",".planning/phases/08-schedule-and-booking/08-22-SUMMARY.md",".planning/phases/08-schedule-and-booking/08-23-PLAN.md",".planning/phases/08-schedule-and-booking/08-23-SUMMARY.md","app/Actions/OutboundSender.php","app/Actions/RunFrontDesk.php","app/Actions/SendBookingText.php","app/Actions/SendOwnerReply.php","app/Actions/SetAutomaticTexts.php","app/Filament/Pages/Schedule.php","app/Filament/Resources/Contacts/RelationManagers/AppointmentsRelationManager.php","app/Livewire/BookingPage.php","app/Records/RecordsWriter.php","app/Rooms/ScheduleAndBooking/Availability.php","app/SendRule/DecideSendOrDraft.php","app/Support/BookingLinkTokens.php","database/migrations/2026_11_07_000001_revoke_booking_links_sent_before_tokens_were_hidden.php","resources/views/components/booking/footer.blade.php","resources/views/livewire/booking-page.blade.php"]
covered_digest: "v3:sha256:3e4bc0300b8153d09c11658d9e00805c16d6dfc47026099a61079d14e0a931bb"
behavior_unverified: 0
overrides_applied: 0
re_verification:
  previous_status: gaps_found
  previous_score: 6/7
  gaps_closed:
    - "D-12 (a copy of the database or business folder holds no working booking link): plans 08-17, 08-18"
    - "WR-01 (a booking text is sent once): plan 08-19"
    - "WR-02 (a reminder due at the window's end survives a late sweep; a dropped reminder leaves a note): plan 08-19"
    - "WR-03 (a delivered link is not withdrawn by a recording failure after the send): plan 08-20 (one residual corner, see WR-08 below)"
    - "WR-04 (refusals told apart by constant and reason, not message text): plan 08-20"
    - "WR-05 (the footer states nothing the code does not own): plan 08-21"
    - "UI footer hover border: plan 08-21"
    - "IN-01 (Availability docblock on the drive buffer): plan 08-23"
    - "IN-02 (only SendBookingText names the booking text kind, by architecture test): plan 08-19"
    - "IN-03 (no-phone confirmation forecast): plan 08-23"
    - "IN-04 (Upcoming and Past do not overlap): plan 08-23"
    - "IN-05 (rate-limited notice retried without a reload): plan 08-22"
    - "UI zone name in plain words: plan 08-23"
  gaps_remaining: []
  regressions: []
advisory:
  - finding: "WR-06 (08-REVIEW.md): a booking row whose sender died after queuing the message ends failed with a critical log line and no timeline note the owner can read"
    category: other
    reason: "Corner of the new send-once lookup (SendBookingText.php:222-231). No text goes out twice, which is what the gap required; the owner trace is missing. Open for Ian's triage."
    evidence_status: "08-REVIEW.md reading of the code; no failing test"
  - finding: "WR-07 (08-REVIEW.md): FrontDeskPrompt never tells the model that /book/[link] in its own history is a placeholder, so the D-12 hold will fire on follow-ups"
    category: other
    reason: "The hold is the designed backstop and the customer is protected; the cost is owner work on repeat link requests. A one-sentence prompt rule would cut it. Open for Ian's triage."
    evidence_status: "08-REVIEW.md; confirmed the prompt has no link rule"
  - finding: "WR-08 (08-REVIEW.md): a database fault at the moment OutboundSender releases its lock, after the text was sent, still reaches the callers' catch (Throwable) and withdraws a live link"
    category: other
    reason: "I read OutboundSender::send() (try/finally with $lock->release()) and RunFrontDesk::sendPassingReply(): the finding is real but needs a cache-store write failure at one instant, and the three fixes Ian ruled on for WR-03 are in place. The docblock claim that nothing after the send can throw is slightly too strong. Open for Ian's triage."
    evidence_status: "code read; not reproduced"
  - finding: "IN-09 (08-REVIEW.md): the revoke migration cannot tell a link sent before the fix from one sent after it"
    category: other
    reason: "It runs once at deploy. Any environment that minted links after 08-17 but has not yet run the migration would have them revoked. Not a goal failure for the first deploy."
    evidence_status: "migration read"
human_verification:
  - test: "Keyboard-only walk of the booking page at desktop width and at 375 px, including Try again on the rate-limited notice"
    expected: "A customer opens a booking link, reaches every time, books by keyboard alone, and focus lands on the confirmation heading; the rate-limited notice takes focus on its heading, Tab reaches Try again, and a successful press lands focus on the page's heading"
    why_human: "CLAUDE.md: automated checks catch only part of WCAG; the browser tests drive the keys but a person judges the walk (WINDOWS.md row 55)"
  - test: "VoiceOver walk of the booking page (picker, check step, confirmation, rate-limited notice, footer disclosures)"
    expected: "Each time is announced with its day and time; focus moves to the confirmation; errors are read beside the field; the footer disclosures are announced as expandable"
    why_human: "Screen reader output is not machine-checkable here"
  - test: "200% zoom of the booking page, and 320 px reflow"
    expected: "The page reflows and stays usable. No test covers 320 px (08-UI-REVIEW.md)"
    why_human: "Reflow is judged by eye"
  - test: "Dev chat: ask to book a visit after intake; then ask 'what time are you coming?'"
    expected: "The offer comes back labeled scheduling with the link line (D-05); the second question is answered from the card (D-39); asking for the link again gives a fresh link line or a held draft, never a /book/[link] to the customer (D-12)"
    why_human: "Needs the live model (Jev); recorded for end-of-phase UAT (WINDOWS.md row 58)"
  - test: "Push the branch and read CI's pgsql leg for the whole suite"
    expected: "Both sqlite and pgsql legs green, including the booking CHECK constraint, the one-open-conversation partial index, the distinct-order query, BookingLinkLeakTest's table scan on pg_class and LinksBeforeTheFixTest"
    why_human: "Postgres runs only in CI after Ian pushes; unrun-verify rows 46 to 57 and later in .planning/WINDOWS.md"
  - test: "Attorney list: final privacy-notice wording for the booking page, and the text provider's own message log (Twilio keeps every sent body)"
    expected: "The attorney has the facts: the page sets a session cookie, the session row keeps the device address and browser name, the rate limiter counts taps by address, and the provider keeps sent bodies that the platform cannot redact"
    why_human: "Legal wording; recorded in STATE.md blockers (Phase 02.1) and docs/BLUEPRINT.md section 10"
  - test: "Owner screens walk: Schedule page (Upcoming and Past with a block spanning days, plain zone name in the Block time dialog) and the contact screen's Book by hand dialog (five confirmation lines), with keyboard and VoiceOver"
    expected: "Upcoming and Past never list the same row; the helper reads 'Times are in Central Time.'; the no-phone line shows for an email-only customer"
    why_human: "Owner-screen accessibility walk (WINDOWS.md row 52); the owner restyle belongs to Phase 11"
---

# Phase 8: Schedule and Booking Verification Report

**Phase Goal:** A customer can finish the conversation by booking a time, and the owner sees it and is told about it.
**Verified:** 2026-11-08
**Status:** human_needed
**Re-verification:** Yes, after gap closure (plans 08-17 to 08-23)

## Goal Achievement

The first verification (2026-11-07) found the booking path working end to end but one locked decision (D-12) broken and 12 further items Ian ruled into gap plans. I checked each against the code as it is now. D-12 is met: the sent text goes to the customer's phone with the working link, every stored or printed copy holds `/book/[link]`, and links sent before the fix are revoked by a migration. The twelve other items are in the code and pinned by tests. D-46, which no PLAN.md cites, is built as Ian wrote it. The remaining items are live checks that cannot run here (keyboard and VoiceOver walks, the live model, CI Postgres, attorney wording), so the status is `human_needed`, with no gaps.

### Observable Truths

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | SC1: a customer texted a link opens a page, taps one time and ends with a confirmed appointment without typing; only valid times offered | VERIFIED (regression check) | Unchanged since the first verification. `Availability.php` still carries the buffer docblock fix only (IN-01, comment change). AvailabilityTest, BookingPageTest, PageBookingTest, BookingLinkTokenTest, BookAppointmentTest pass in the 612-test run. |
| 2 | SC2: a booked appointment attaches to the project, appears on the timeline, the card and the schedule screen | VERIFIED (regression check) | AppointmentCardTest, SchedulePageTest, AppointmentsRelationManagerTest pass. `Schedule::showRows()` (Schedule.php:277-296) now splits Past/Upcoming by end time, so a span over midnight appears once (IN-04). |
| 3 | SC3: the customer is reminded before the appointment, sent by the scheduler, and a text is sent once | VERIFIED | `routes/console.php` every-minute job unchanged. `SendBookingText::send()` first looks up a message with `meta->booking_send_id` (:103) and ends the row on it, so a crash or a failed sent-write cannot resend (WR-01); `markSent` logs critical on a failed write (:246). `LATE_GRACE_MINUTES = 5` with the check that the row came due at or before the close (:54, :262-279), and `drop()` writes "the visit started first" (:347-358) (WR-02). BookingSendOnceTest passes (DB hook that fails the `sent_at` write, then a re-sweep after the lease). |
| 4 | SC4: the owner is alerted once when an appointment is booked and when a conversation needs a human | VERIFIED (regression check) | BookingAlertTest and ChangeRequestAlertTest pass. WR-03's fix keeps the booked alert working when the carrying-message record failed (found by `meta.booking_link_id`). |
| 5 | SC5: the room meets the standard (folder, CONTEXT.md, example file, module, boot test) | VERIFIED (regression check) | ScheduleAndBookingBootTest, ScheduleAndBookingSyncTest, BookingFileCheckTest pass. The D-46 revision removed the room entry the first version registered; the boot test's entry list was updated with it. |
| 6 | SC6: the booking page meets WCAG 2.2 AA (automated part); UI-SPEC carries it | VERIFIED (automated part; human walks below) | BookingPageBrowserTest (real browser, 375 and 1280 px) passes in the run: keyboard booking, 48 px targets, axe on every state, the footer hover, and the Try again focus path. `booking-page.blade.php:2-9` carries the Try again button and the notice's focus. 08-UI-REVIEW.md re-audit is 22/24, all warnings. |
| 7 | D-12 (locked): a copy of the database or the business folder holds no working booking link | VERIFIED | See "D-12 trace" below. BookingLinkLeakTest (16 cases) and LinksBeforeTheFixTest pass. |
| 8 | D-46 (Ian, 2026-11-07, adds to D-20): every change of the automatic-texts switch writes one platform timeline entry naming who, when and which state; a no-op or refused change writes none; the contact's columns still hold only the current agreement | VERIFIED | `SetAutomaticTexts::change()` writes `ActivityType::AutomaticTextsTurnedOn/Off` with "{name} turned automatic texts {on\|off}." inside the same `DB::transaction` and `lockForUpdate` as the change (SetAutomaticTexts.php:84-110); a refused change throws before any write (`hasTextedBusiness()`), and an unchanged switch returns false before the entry. `on()` keeps its first time and user; `off()` clears the two columns. The two cases exist in `ActivityType.php:20-21` (platform types, not room entries, so a room that is off cannot hide them); `CustomerCard.php:337-338` prints "Automatic texts turned on/off" under "System note:"; the contact timeline renders any non-`RoomEntry` type by its value (ContactInfolist.php:139). The `activities.type` column is a plain string, so no schema change was needed. AutomaticTextsSwitchTest (on, off, on with the schedule room off; refused change writes none; card and timeline) passes. |

**Score:** 8/8 truths verified (0 present, behavior-unverified)

### D-12 trace (what holds now)

- `BookingLinkTokens::hide()` replaces `/book/` plus 64 or more hex characters, any case, with `/book/[link]`, and leaves a bare 64-hex string (a `token_hash`) alone.
- `OutboundSender::deliver()` and `deliverExisting()` store `hide($body)` (OutboundSender.php:317, :350) and hand the raw text to the channel through `transmit()` (:378), so the customer's phone gets a working link (`FakeTextChannel::sent()` holds it; the test asserts it hashes to the stored `token_hash` and `GET /book/{token}` opens).
- A failed send's message, the `meta.error`, the timeline note and the `DomainException` message are hidden, and the raw exception is kept as `previous` only when it held no address (:384-391).
- `RecordInbound` hides a link a customer pastes back (:148); `RecordsWriter` hides every file at the one write funnel (:185); `FakeTextChannel` hides its log line (:64); the session's previous URL is hidden by `StartSessionHidingBookingTokens`.
- `DecideSendOrDraft` holds a reply whose own words name `/book/` (:72); `OutboundSender::whyBodyIsRefused()` refuses any text holding `/book/[link]` for every origin (:658), so the hidden form never reaches a customer.
- Migration `2026_11_07_000001` revokes every unbooked link sent before the fix, keeps a booked link and an earlier `revoked_at`, rewrites no stored text, logs the ids, queues card redraws and is idempotent. LinksBeforeTheFixTest runs it.
- BookingLinkLeakTest searches every table `Schema::getTables()` lists (raw and base64-decoded), the business folder, the log and the queue payloads after a send on the agent path and on an approved draft, and proves it is not vacuous by finding planted tokens.
- 08-SECURITY.md closes T-08-01 and T-08-27 with that evidence (`threats_open: 0`, 97 threats closed). The text provider's own log is on the attorney list (STATE.md blockers; BLUEPRINT section 10), because the platform cannot redact it.

### Gap closure, item by item

| Gap | Plan | Status | Code evidence |
|-----|------|--------|---------------|
| D-12 | 08-17, 08-18 | CLOSED | See D-12 trace |
| WR-01 | 08-19 | CLOSED | `SendBookingText.php:103` lookup, `endOnExisting`, critical log at :222 and :246; BookingSendOnceTest |
| WR-02 | 08-19 | CLOSED | `LATE_GRACE_MINUTES`, `nextOpening()` grace limited to rows due at or before the close, drop note; D-29 kept as written |
| WR-03 | 08-20 | CLOSED for the three items ruled | `RunFrontDesk::recordCarryingMessage()` (:652-659) runs after the guarded send and only reports; the withdraw runs only in the catches around the send; same shape in `SendOwnerReply` (:195-219); the card redraw after a send is reported, not raised. One corner remains: WR-08 below |
| WR-04 | 08-20 | CLOSED | Refusal sentences are public constants on `OutboundSender` (:60-148); `SendBookingText::answerRefusal` compares to them; `BookingRefusal` enum (13 cases) on `BookingRefused`; `BookingPage::refuse()` switches on `$refused->reason` and reads no message text. RefusalSentencesTest pins both, including a source scan |
| WR-05 | 08-21 | CLOSED | `footer.blade.php:9` says only what the page does; no "placeholder wording" and no claim that nothing is asked. The attorney facts are in BLUEPRINT section 10 |
| UI footer hover | 08-21 | CLOSED | `hover:border-accent` sits on the `<details class="... border-2 ...">` (footer.blade.php:4, :12); BookingPageContractTest checks every hover border sits on a bordered element; the browser test hovers the summary |
| IN-01 | 08-23 | CLOSED | `Availability.php:89` and `:108` say hand bookings pass the file's drive buffer, or 0 with no booking file |
| IN-02 | 08-19 | CLOSED | `tests/Unit/BookingTextKindTest.php` lists the three files under `app/` that may name the booking text kind and flags a snippet of each kind |
| IN-03 | 08-23 | CLOSED | `AppointmentsRelationManager.php:240`, after the opted-out, not-agreed and no-file lines |
| IN-04 | 08-23 | CLOSED | `Schedule.php:277-296`: booked rows by `ends_at`, done rows by `starts_at`; a test for a block spanning days and for one ending exactly at today's start |
| IN-05 | 08-22 | CLOSED | `BookingPage::tryAgain()` goes through `withinLimit()`, which uses `RateLimiter::attempt()`, so it counts nothing while limited; the button and focus are in `booking-page.blade.php:2-9` |
| UI zone name | 08-23 | CLOSED | `'Times are in '.BookingSettings::zoneName(...)` at `Schedule.php:184` and `AppointmentsRelationManager.php:132` |

### Required Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `app/Support/BookingLinkTokens.php` | The one rule for hiding a booking address | VERIFIED | Substantive, used by OutboundSender, RecordInbound, RecordsWriter, FakeTextChannel, DecideSendOrDraft, the session middleware |
| `app/Actions/OutboundSender.php` | Chokepoint: stores hidden, sends working, refuses hidden form | VERIFIED | :317, :350, :378, :658 |
| `app/Actions/SendBookingText.php` | Scheduler send, sent once, grace, notes | VERIFIED | Lookup before any other check |
| `app/Enums/BookingRefusal.php`, `app/Rooms/ScheduleAndBooking/BookingRefused.php` | Refusal by reason | VERIFIED | 13 cases, used by BookingPage |
| `database/migrations/2026_11_07_000001_...php` | Revokes pre-fix links | VERIFIED | Run by LinksBeforeTheFixTest |
| `app/Actions/SetAutomaticTexts.php`, `app/Enums/ActivityType.php`, `app/Records/CustomerCard.php` | D-46 entries | VERIFIED | Wired and tested |
| `resources/views/components/booking/footer.blade.php`, `booking-page.blade.php`, `components/booking/notice.blade.php` | Page footer, Try again, focus | VERIFIED | Contract and browser tests |
| `app/Filament/Pages/Schedule.php`, `AppointmentsRelationManager.php` | Owner screens | VERIFIED | Plain zone name, Past by end, no-phone line |
| `tests/Feature/Booking/BookingLinkLeakTest.php`, `LinksBeforeTheFixTest.php`, `BookingSendOnceTest.php`, `RefusalSentencesTest.php`, `BookingPageThrottleTest.php`, `tests/Unit/BookingTextKindTest.php` | Proof for the gaps | VERIFIED | All pass |

### Key Link Verification

| From | To | Via | Status | Details |
|------|----|-----|--------|---------|
| OutboundSender::deliver | text channel | `transmit($..., $body)` with the raw text; row holds `hide($body)` | WIRED | customer gets a working link; row hidden |
| RecordsWriter | records/ files | `File::replace($path, BookingLinkTokens::hide($contents))` | WIRED | RecordsWriter.php:185 |
| RunFrontDesk / SendOwnerReply | MintBookingLink::attachMessage | after the guarded send, own try that only reports | WIRED | WR-03 |
| SendBookingText | OutboundSender constants | `$sentence === OutboundSender::...` | WIRED | WR-04 |
| BookingPage::refuse | BookingRefusal | `match ($refused->reason)` | WIRED | |
| Try again button | BookingPage::tryAgain | `wire:click="tryAgain"` | WIRED | booking-page.blade.php:6 |
| SetAutomaticTexts | activities | `$locked->activities()->create(...)` in the locking transaction | WIRED | D-46 |
| Schedule::showRows | appointments.ends_at | booked rows by end | WIRED | IN-04 |
| scheduler | SendBookingText | `SendDueBookingTexts` every minute | WIRED | unchanged |

### Data-Flow Trace (Level 4)

| Artifact | Data | Source | Real data | Status |
|----------|------|--------|-----------|--------|
| booking page picker | open starts | `Availability::openStarts()` | Yes | FLOWING |
| customer card | visits, system notes (D-46) | scoped queries on appointments and activities | Yes | FLOWING |
| Schedule page | Upcoming and Past rows | `showRows()` over appointments | Yes | FLOWING |
| sent text row | `messages.body` | `hide($body)` of the text the customer received | Yes, hidden by design | FLOWING |

### Behavioral Spot-Checks

| Behavior | Command | Result | Status |
|----------|---------|--------|--------|
| Booking, availability, send-once, leak, refusals, throttle, page, browser, room, schedule and hand-booking tests | `php artisan test --compact tests/Feature/Booking tests/Unit/Booking tests/Unit/BookingTextKindTest.php tests/Unit/ArchitectureTest.php tests/Feature/Rooms/ScheduleAndBookingBootTest.php tests/Feature/Rooms/ScheduleAndBookingSyncTest.php tests/Feature/Rooms/BookingFileCheckTest.php tests/Feature/Filament/SchedulePageTest.php tests/Feature/Filament/AppointmentsRelationManagerTest.php` | 612 passed, 3,716 assertions, 1 warning (no detail given), 91.5 s (SQLite, includes the real-browser tests) | PASS |
| Full suite | not re-run by me | orchestrator: 3126 passed, 22057 assertions on SQLite at HEAD after 08-23 | not re-verified |
| TBD/FIXME/XXX in the 39 app, resource, database, test and example files changed since dc77bb8 | grep | none | PASS |

Behavior-dependent truths (send once, link kept after a late failure, rate limit retry) each have a named passing test that triggers the transition, so none is left PRESENT_BEHAVIOR_UNVERIFIED.

### Probe Execution

Step 7c: SKIPPED. No `scripts/*/tests/probe-*.sh` exists and no plan declares a probe.

### Requirements Coverage

Every ID the roadmap lists for Phase 8 appears in at least one plan's `requirements:` frontmatter (08-01 to 08-23) and in REQUIREMENTS.md. No orphans. BOOK-06 maps to Phase 9.

| Requirement | Source plans | Status | Evidence |
|-------------|--------------|--------|----------|
| ROOM-12 | 08-04, 08-14 | SATISFIED | Truth 5 |
| BOOK-01 | 08-05, 08-06 | SATISFIED | Truth 1 |
| BOOK-02 | 08-01, 08-07 to 08-09, 08-17, 08-18, 08-20 to 08-22 | SATISFIED | Truths 1 and 7; the D-12 shortfall noted in the first report is closed |
| BOOK-03 | 08-01, 08-06, 08-13 to 08-17, 08-23 | SATISFIED | Truths 2 and 8 |
| BOOK-04 | 08-01, 08-02, 08-11, 08-12, 08-15, 08-19, 08-20 | SATISFIED | Truth 3 |
| BOOK-05 | 08-03, 08-07 | SATISFIED | Truth 4 |
| A11Y-02 | 08-08, 08-10, 08-13, 08-14, 08-21, 08-22 | SATISFIED for the automated part; UAT walks pending | Truth 6 |

Bookkeeping note: REQUIREMENTS.md still shows ROOM-12, BOOK-01 and BOOK-05 unchecked and "Gaps Found" in its traceability table (lines 40, 53, 57, 211, 216, 220). The code supports marking them complete; whoever closes the phase should update those rows.

### Anti-Patterns Found

| File | Line | Pattern | Severity | Impact |
|------|------|---------|----------|--------|
| app/Actions/SendBookingText.php | 222-231 | queued leftover ends the row failed with a log line and no owner-readable note (WR-06) | WARNING | Rare; no double send; owner does not see why a reminder never went |
| app/Ai/FrontDeskPrompt.php | 39-47 | model not told what `/book/[link]` in its history means (WR-07) | WARNING | The D-12 hold fires on repeat link requests, more owner drafts; the customer is never sent a broken link |
| app/Actions/OutboundSender.php, RunFrontDesk.php, SendOwnerReply.php | 182-186, 634-638, 203-211 | a fault at lock release after a send still withdraws a live link (WR-08) | WARNING | Needs a cache-store failure at one instant; docblock overclaims |
| database/migrations/2026_11_07_000001_... | up() | cannot tell links before the fix from after (IN-09) | INFO | Runs once at deploy |

No TBD, FIXME or XXX marker in the changed files. None of the three warnings defeats a must-have: WR-06 concerns the owner's trace, not whether a text is sent once; WR-07 concerns owner workload while the protection holds; WR-08 is a narrow residue of WR-03 after the three fixes Ian ruled on landed. They stay open for Ian's triage in 08-REVIEW-DISPOSITION.md.

### Human Verification Required

1. **Keyboard-only walk** of the booking page at desktop width and 375 px, including Try again. Expected: every time reachable, booking completes, focus lands on the confirmation; the notice takes focus and Tab reaches Try again.
2. **VoiceOver walk** of the booking page. Expected: each time announced with its day and time; focus moves to the confirmation; errors read beside the field.
3. **200% zoom and 320 px reflow.** No test covers 320 px.
4. **Dev chat checks** (D-05 scheduling label with the link; D-39 "what time are you coming?"; a repeat link request never sends `/book/[link]`). Needs the live model.
5. **CI pgsql leg** after Ian pushes (WINDOWS.md unrun-verify rows), including the leak test's `pg_class` table scan and the migration test.
6. **Attorney list:** final privacy wording and the text provider's own message log.
7. **Owner-screen walk** of the Schedule page and the Book by hand dialog.

### Gaps Summary

No gaps. All 13 items from the first verification are closed in the code and pinned by passing tests, and D-46 is built as Ian wrote it. The first verification's one blocker, D-12, is met: a copy of the database, the business folder, the log and the queue holds `/book/[link]` and no working token, links sent before the fix are revoked, and the provider's log is on the attorney list. The status is `human_needed` because the walks, the live model checks, the CI Postgres leg and the attorney wording cannot run in this environment. Three review warnings (WR-06 to WR-08) stay open for Ian's triage and do not block.

---

_Verified: 2026-11-08_
_Verifier: Claude (gsd-verifier)_
