---
status: diagnosed
phase: 08-schedule-and-booking
source: [08-VERIFICATION.md]
started: 2026-11-08T23:30:00Z
updated: 2026-11-11T16:13:42.829Z
---

## Current Test

[testing complete]

## Tests

### 1. Keyboard-only walk of the booking page at desktop width and at 375 px, including Try again on the rate-limited notice
expected: A customer opens a booking link, reaches every time, books by keyboard alone, and focus lands on the confirmation heading; the rate-limited notice takes focus on its heading, Tab reaches Try again, and a successful press lands focus on the page's heading
result: issue
reported: "Issue, minor. Everything else passed at desktop and 375 px: every day and time is reachable by Tab with a visible outline, Enter on a time moves focus to \"Check your time\", \"Book this time\" moves focus to \"You are booked\", nothing needs a mouse, and nothing is cut off or scrolls sideways at 375. The rate-limited notice appears after too many tries with \"Try again\", and the times come back after the minute. The one problem: the Space key does nothing on any expandable section, both the day headings and the footer's Privacy notice and Accessibility statement. Enter works on all of them. A disclosure should open and close with Space as well as Enter."
severity: minor

### 2. VoiceOver walk of the booking page (picker, check step, confirmation, rate-limited notice, footer disclosures)
expected: Each time is announced with its day and time; focus moves to the confirmation; errors are read beside the field; the footer disclosures are announced as expandable
result: issue
reported: "Issue, cosmetic. The VoiceOver walk otherwise passed: each day is read as a disclosure with expanded or collapsed; each time is read as one phrase with its day, for example \"8:00 AM, Tuesday, November 10, button\", in a list of 8; Enter on a time moves VoiceOver to \"Check your time, heading level 2\"; \"Book this time\" and \"Choose a different time\" are read as buttons; booking moves VoiceOver to \"You are booked, heading level 1\"; the footer's Privacy notice and Accessibility statement are read as disclosures with collapsed and expanded. The one problem: VoiceOver reads 9:00 AM as \"nine zero zero\" instead of as a time. Check the exact characters in the time's accessible text (for example a narrow no-break space before AM from date formatting, or a non-standard colon) and whether every time is affected. Not walked with VoiceOver: the rate-limited notice and the taken-time error; the browser tests cover their focus and button names."
severity: cosmetic

### 3. 200% zoom of the booking page, and 320 px reflow
expected: The page reflows and stays usable. No test covers 320 px (08-UI-REVIEW.md)
result: pass

### 4. Dev chat: ask to book a visit after intake; then ask 'what time are you coming?'
expected: The offer comes back labeled scheduling with the link line (D-05); the second question is answered from the card (D-39); asking for the link again gives a fresh link line or a held draft, never a /book/[link] to the customer (D-12)
result: issue
reported: "Issue, major. Walked on +13175550176 and +13175550177 (+13175550175 was an aborted first try; its held draft can be ignored).\n\nPassed:\n- D-05 label: after the last intake answer the front desk's offer came back labeled scheduling with service ac-repair (message 10). It was held by the customer reading (see the notes), so the owner sent it with the booking-link box ticked and code added the link line (booking link 5).\n- D-12: \"Sorry, I lost that link. Can you send it again?\" was sent on its own, labeled scheduling, parts [model_words, booking_link], with a fresh link 6 and link 5 superseded in the same second. The model's own words hold no link of any form (message 13). The WR-07 source fix worked on its first live try.\n- The booking page (link 7, minted by you) and the owner's Book by hand both sent the confirmation at once with the right day and time. Book by hand refused an overlapping time with \"That time is taken by another booking or blocked time. Pick another time.\" under the field.\n\nFailed:\n1. D-39: on +13175550177, with a hand-booked AC repair visit for Thursday, November 12 at 10:00 AM on the card, \"What time are you coming?\" (customer reading scheduling 0.99, clean) got the draft \"Thanks for checking. I can't confirm the visit time myself, so the owner will follow up with you on exactly when we're coming.\", labeled other and held (label:other). The customer got the holding reply. The answer was not taken from the card (message 20).\n2. The front desk's booking texts say the owner will confirm, which is false now that the page books at once, and they repeat the link line's words. The held offer (message 10) said \"What day works best for you? The owner will confirm the time.\" The sent link-again reply (message 13) said \"Pick a time that works for you, and the owner will confirm the visit. Pick a time that works for you here: [link]\".\n\nLikely common source for 1 and 2, for diagnosis: FrontDeskPrompt's Phase 2 rule \"Never say a visit, a time or a booking is set, even when the thread mentions one; the owner confirms those.\", and nothing the model reads says the system adds a booking link where the customer books a time. Per Ian's METHOD file, fix what the model reads, not a rule that names the words to avoid. A fix there reopens D-05 (\"FrontDeskPrompt unchanged\") and D-39 (\"No new front-desk rule\"), so bring the proposed fix to Ian as a decision before planning it.\n\nNotes, not Phase 8 gaps. Record them, but plan no fixes for them in Phase 8:\n- The Phase 2 safety bars held three plain customer texts in a row: \"Hi, my AC isn't cooling. Can someone come take a look?\" (intent 0.78 against 0.85, asks_for_person 0.41 to 0.50), \"It's blowing warm air\" (complaint 0.16 against 0.15) and \"Over 10 years\" (complaint 0.20). This is calibration of D-47 and D-56 for later, using the stored verdicts; Phase 10's walk tests should script these texts.\n- On +13175550176 the reply cap (6 per hour) was reached before \"What time are you coming?\", so the assistant paused. Two of the six counted texts were holding replies from those holds, and the booking confirmation also counts toward the cap. Design question for later.\n- Dev chat shows the stored copy of each text, so a link sent through the front desk shows /book/[link] and cannot be clicked there. That is D-12 working; walking a booking in Dev chat needs a link minted by script. Not a product bug.\n- The local database had no owner login, so Ian made one with make:filament-user. It has no phone, so owner alerts were \"not sent: No phone on file\"."
severity: major

### 5. Push the branch and read CI's pgsql leg for the whole suite
expected: Both sqlite and pgsql legs green, including the booking CHECK constraint, the one-open-conversation partial index, the distinct-order query, BookingLinkLeakTest's table scan on pg_class and LinksBeforeTheFixTest
result: pass
evidence: "CI run 42643059174 at 689b786 (same as origin): sqlite and pgsql legs green for the whole suite"

### 6. Attorney list: final privacy-notice wording for the booking page, and the text provider's own message log (Twilio keeps every sent body)
expected: The attorney has the facts: the page sets a session cookie, the session row keeps the device address and browser name, the rate limiter counts taps by address, and the provider keeps sent bodies that the platform cannot redact
result: pass
evidence: "docs/BLUEPRINT.md section 10, 'The attorney list, before a real client', carries all four facts (Twilio body retention also in STATE.md's Phase 02.1 entry). The attorney's review itself stays a before-first-client item, not a Phase 8 step."

### 7. Owner screens walk: Schedule page (Upcoming and Past with a block spanning days, plain zone name in the Block time dialog) and the contact screen's Book by hand dialog (five confirmation lines), with keyboard and VoiceOver
expected: Upcoming and Past never list the same row; the helper reads 'Times are in Central Time.'; the no-phone line shows for an email-only customer
result: pass
evidence: "pass. Both dialogs say \"Times are in Central Time.\" A block from Thursday, November 12, 1:00 PM to Friday, November 13, 9:28 PM shows once in Upcoming as a single row (32 hr 28 min, Blocked by Ian Owner, Remove block) and never in Past. Past was empty while Tuesday's already-ended 9:00 AM visit stayed in Upcoming; after midnight Upcoming dropped to 7 rows without it. Book by hand refused a time inside the block with \"That time is taken by another booking or blocked time. Pick another time.\" For a new email-only contact, Book by hand first said \"No confirmation text will go out. This customer has not agreed to automatic texts.\"; after Turn on automatic texts (timeline: \"Ian Owner turned automatic texts on.\") it said \"No confirmation text will go out. This customer has no phone number on file.\"; booking Monday, November 16 at 10:00 AM logged \"Confirmation text not sent: the customer has no phone number on file.\"; Turn off logged \"Ian Owner turned automatic texts off.\" Keyboard: Tab reaches Block time, Enter opens it with focus inside, Tab moves through Starts, Ends, Note and the buttons, and Escape closes it with focus back on Block time. VoiceOver on owner screens moves to Phase 11, as in Phase 7. Cosmetic notes for Phase 11, not gaps: the Schedule table scrolls sideways at a normal laptop window width with the What column squeezed; the automatic-texts timeline badges show the raw type value (automatic_texts_turned_on), while other entries show a plain label, as D-46 specifies."
not_walked: "VoiceOver on owner screens moves to Phase 11, as in Phase 7"

## Summary

total: 7
passed: 4
issues: 3
pending: 0
skipped: 0
blocked: 0

## Gaps

- gap_id: G-08-1
  truth: "Every expandable section on the booking page (the day headings and the footer's Privacy notice and Accessibility statement) opens and closes from the keyboard with Space as well as Enter"
  status: failed
  reason: "User reported: Issue, minor. Everything else passed at desktop and 375 px: every day and time is reachable by Tab with a visible outline, Enter on a time moves focus to \"Check your time\", \"Book this time\" moves focus to \"You are booked\", nothing needs a mouse, and nothing is cut off or scrolls sideways at 375. The rate-limited notice appears after too many tries with \"Try again\", and the times come back after the minute. The one problem: the Space key does nothing on any expandable section, both the day headings and the footer's Privacy notice and Accessibility statement. Enter works on all of them. A disclosure should open and close with Space as well as Enter."
  severity: minor
  test: 1
  root_cause: "Investigation inconclusive - manual review needed. Nothing in the app swallows Space: no key handler in resources/js (app.js is empty), Livewire 4.4.7, Alpine or Boost's injected script; the day and footer disclosures are plain <details><summary>. In Chrome 157 and Chromium 153 driven by Playwright, Space opened and closed a day summary and both footer summaries. The symptom matches a page-wide preventDefault on Space keydown or keyup from outside the repo, most likely a browser extension, else a key remapper or a native macOS key path CDP cannot reproduce."
  artifacts:
    - path: "resources/views/components/booking/day.blade.php"
      issue: "clean, no key handling"
    - path: "resources/views/components/booking/footer.blade.php"
      issue: "clean, no key handling"
    - path: "tests/Feature/Booking/BookingPageBrowserTest.php"
      issue: "no test presses Space on a disclosure"
  missing:
    - "Ian: press Space on a day heading in an Incognito window with extensions off; press Space on a plain <details> page in the normal profile; if it fails only on the booking page, log defaultPrevented for Space keydown/keyup in the console"
    - "If the cause is outside the app, close G-08-1 as not an app defect with no code change"
    - "Optional guard either way: a browser test that opens and closes a day disclosure and both footer disclosures with Space (passes today in headless Chromium, so it pins, not red-to-green)"
  debug_session: ".planning/debug/booking-disclosures-ignore-space.md"
  needs: manual_review

- gap_id: G-08-2
  truth: "VoiceOver reads every time on the booking page as a time (9:00 AM as 'nine A M'), not as separate digits"
  status: failed
  reason: "User reported: Issue, cosmetic. The VoiceOver walk otherwise passed: each day is read as a disclosure with expanded or collapsed; each time is read as one phrase with its day, for example \"8:00 AM, Tuesday, November 10, button\", in a list of 8; Enter on a time moves VoiceOver to \"Check your time, heading level 2\"; \"Book this time\" and \"Choose a different time\" are read as buttons; booking moves VoiceOver to \"You are booked, heading level 1\"; the footer's Privacy notice and Accessibility statement are read as disclosures with collapsed and expanded. The one problem: VoiceOver reads 9:00 AM as \"nine zero zero\" instead of as a time. Check the exact characters in the time's accessible text (for example a narrow no-break space before AM from date formatting, or a non-standard colon) and whether every time is affected. Not walked with VoiceOver: the rate-limited notice and the taken-time error; the browser tests cover their focus and button names."
  severity: cosmetic
  test: 2
  root_cause: "Investigation inconclusive - manual VoiceOver check needed. Every time surface (picker button's sr-only name, the Check your time dd, the You are booked dd) exposes '9:00' + U+00A0 + 'AM'; Chromium's accessibility tree keeps it as one string; the macOS speech synthesizer reads plain space, U+00A0 and U+202F identically as a time in every voice tested. Left: VoiceOver mishandling U+00A0, VoiceOver's 'Read numbers as: Digits' setting, or VoiceOver splitting '9:00' at the colon in Chrome."
  artifacts:
    - path: "app/Livewire/BookingPage.php"
      issue: "timeLabel() (273-276) swaps the space for U+00A0 on every time, used at 248 (picker) and 266 (visit)"
    - path: "resources/views/components/booking/time.blade.php"
      issue: "the sr-only name carries U+00A0 (line 2)"
    - path: "resources/views/livewire/booking-page.blade.php"
      issue: "the dd values carry U+00A0 (lines 50, 88)"
    - path: "tests/Pest.php"
      issue: "normalizedName() (497) turns U+00A0 into a space, so tests cannot see it"
  missing:
    - "Ian: open the scratchpad probe vo-time-probe.html in Chrome with VoiceOver and note VoiceOver Utility > Verbosity > Text > Read numbers as"
    - "If the no-break space is the cause: U+00A0 only in the visible aria-hidden time, a plain space in the sr-only name and both dd values (whitespace-nowrap keeps the visual no-wrap), and a test that reads raw text"
    - "If every number reads as digits: VoiceOver setting, close as environment"
    - "If plain-space times also read as digits: a different spoken form conflicts with WCAG 2.5.3 label-in-name; bring to Ian as a decision"
  debug_session: ".planning/debug/voiceover-reads-time-as-digits.md"
  needs: manual_review
  # UAT note 2026-11-09, corrected 2026-11-11: the booked visit's time text is '9' ':'(U+003A) '0' '0' U+00A0 'A' 'M'. BookingPage::timeLabel() (app/Livewire/BookingPage.php:273) puts U+00A0 before AM/PM on every picker and visit time. The user's "8:00 AM, Tuesday, November 10, button" was quoted as the shape of the phrase, not as proof it was read correctly, so every time may be affected.

- gap_id: G-08-4
  truth: "With a visit booked ahead, the front desk's reply to 'What time are you coming?' sends on its own, labeled scheduling, and code adds the owner's visit_line with the appointment's day and time; the model's words never state them (D-39 as amended by Ian, 2026-11-11)"
  status: failed
  reason: "User reported: 1. D-39: on +13175550177, with a hand-booked AC repair visit for Thursday, November 12 at 10:00 AM on the card, \"What time are you coming?\" (customer reading scheduling 0.99, clean) got the draft \"Thanks for checking. I can't confirm the visit time myself, so the owner will follow up with you on exactly when we're coming.\", labeled other and held (label:other). The customer got the holding reply. The answer was not taken from the card (message 20)."
  severity: major
  test: 4
  root_cause: "Shared with G-08-4b. FrontDeskPrompt.php:41 tells the model 'Never say a visit, a time or a booking is set, even when the thread mentions one; the owner confirms those.' (added in f425e91 for G-02-2.4, before bookings were recorded by the system). Phase 8 put booked visits on the card (D-38) but D-05 and D-39 left the prompt unchanged, so the rule is now false. The card did carry 'Next appointment: Thursday, November 12, 2026 at 10:00 AM (Central Time)'; the model followed line 41 and deflected, and with no scheduling-shaped answer it labeled the reply other (scheduling is defined at :49 as 'using only the hours and notice in the files'), which DecideSendOrDraft holds as label:other. D-39 as written expects a reply stating the time to be held under the promise label, while the UAT truth says scheduling: D-39 can never give the UAT truth."
  artifacts:
    - path: "app/Ai/FrontDeskPrompt.php"
      issue: "line 41 false since Phase 8; :49 scheduling definition; :72 and 343-379 link note only in earlier turns"
    - path: "examples/example-hvac/agents/front-desk.md"
      issue: "loads omits booking.md; body says it leaves promises to the owner"
    - path: "app/SendRule/DecideSendOrDraft.php"
      issue: "62-64, 98-99 hold label:other"
    - path: "tests/Feature/FrontDesk/FrontDeskPromptTest.php"
      issue: "322-332 pin both line 41 sentences"
    - path: ".planning/phases/08-schedule-and-booking/08-CONTEXT.md"
      issue: "D-39 expects a promise hold, not the UAT truth's scheduling label"
  missing:
    - "Ian rules first: when a booked customer asks the time, does the answer wait for one owner tap (D-39 as written) or send on its own as scheduling (the UAT truth)? Amend whichever is wrong"
    - "Then Ian picks a fix option (A1 recommended; A2, B, C, D in the debug session): A1 corrects line 41 so visits recorded on the card are the business's bookings while a time the customer proposes is still not set, and adds a code-owned Booking section built from ServiceToBook's conditions (system adds the link line, page books at once, code texts the confirmation)"
    - "Keep the label check, Jev and CodeChecks as the nets; re-walk D-05, D-39 and the link-again case in Dev chat"
  debug_session: ".planning/debug/front-desk-booking-words.md"
  likely_source: "Likely common source for 1 and 2, for diagnosis: FrontDeskPrompt's Phase 2 rule \"Never say a visit, a time or a booking is set, even when the thread mentions one; the owner confirms those.\", and nothing the model reads says the system adds a booking link where the customer books a time. Per Ian's METHOD file, fix what the model reads, not a rule that names the words to avoid. A fix there reopens D-05 (\"FrontDeskPrompt unchanged\") and D-39 (\"No new front-desk rule\"), so bring the proposed fix to Ian as a decision before planning it."
  decision: "Ian, 2026-11-11: option A (rewrite FrontDeskPrompt line 41; conditional code-owned Booking section) plus a code-owned visit_line slot in ReplyParts filled under visitAskedAbout's condition, words from booking.md visit_line; no model flag, no new message kind (bring back to Ian if 02.1 D-49 needs one). Recorded as D-39 amended in 08-CONTEXT.md, reopening D-05."

- gap_id: G-08-4b
  truth: "The front desk's booking texts never say the owner will confirm the time (the page books at once) and do not repeat the link line's words"
  status: failed
  reason: "User reported: 2. The front desk's booking texts say the owner will confirm, which is false now that the page books at once, and they repeat the link line's words. The held offer (message 10) said \"What day works best for you? The owner will confirm the time.\" The sent link-again reply (message 13) said \"Pick a time that works for you, and the owner will confirm the visit. Pick a time that works for you here: [link]\"."
  severity: major
  test: 4
  root_cause: "Shared with G-08-4. Same false rule (FrontDeskPrompt.php:41, backed by :37-38 and the agent body's 'It leaves promises and complaints to the owner'), and nothing the model reads says the system appends the owner's link line, that the page books at once, or that code texts the confirmation: booking.md is not in the agent's loads (front-desk.md:10-16) and the only booking note, '(The system added the booking link here.)', appears in earlier turns. So the model wrote its own 'pick a time ... the owner will confirm' and code appended the owner's link line (RunFrontDesk.php:608-615), which says the same thing."
  artifacts:
    - path: "app/Ai/FrontDeskPrompt.php"
      issue: "line 41 false since Phase 8; :49 scheduling definition; :72 and 343-379 link note only in earlier turns"
    - path: "examples/example-hvac/agents/front-desk.md"
      issue: "loads omits booking.md; body says it leaves promises to the owner"
    - path: "app/Actions/RunFrontDesk.php"
      issue: "601-615 append the link line after the model's words"
  missing:
    - "Ian rules first: when a booked customer asks the time, does the answer wait for one owner tap (D-39 as written) or send on its own as scheduling (the UAT truth)? Amend whichever is wrong"
    - "Then Ian picks a fix option (A1 recommended; A2, B, C, D in the debug session): A1 corrects line 41 so visits recorded on the card are the business's bookings while a time the customer proposes is still not set, and adds a code-owned Booking section built from ServiceToBook's conditions (system adds the link line, page books at once, code texts the confirmation)"
    - "Keep the label check, Jev and CodeChecks as the nets; re-walk D-05, D-39 and the link-again case in Dev chat"
  debug_session: ".planning/debug/front-desk-booking-words.md"
  likely_source: "Likely common source for 1 and 2, for diagnosis: FrontDeskPrompt's Phase 2 rule \"Never say a visit, a time or a booking is set, even when the thread mentions one; the owner confirms those.\", and nothing the model reads says the system adds a booking link where the customer books a time. Per Ian's METHOD file, fix what the model reads, not a rule that names the words to avoid. A fix there reopens D-05 (\"FrontDeskPrompt unchanged\") and D-39 (\"No new front-desk rule\"), so bring the proposed fix to Ian as a decision before planning it."
  decision: "Ian, 2026-11-11: option A (rewrite FrontDeskPrompt line 41; conditional code-owned Booking section) plus a code-owned visit_line slot in ReplyParts filled under visitAskedAbout's condition, words from booking.md visit_line; no model flag, no new message kind (bring back to Ian if 02.1 D-49 needs one). Recorded as D-39 amended in 08-CONTEXT.md, reopening D-05."

## Deferred Follow-Ups

<!-- Recorded at the user's request as notes, not Phase 8 gaps: no fix is planned for them in Phase 8. -->
- test: 4
  idea: "The Phase 2 safety bars held three plain customer texts in a row: \"Hi, my AC isn't cooling. Can someone come take a look?\" (intent 0.78 against 0.85, asks_for_person 0.41 to 0.50), \"It's blowing warm air\" (complaint 0.16 against 0.15) and \"Over 10 years\" (complaint 0.20). This is calibration of D-47 and D-56 for later, using the stored verdicts; Phase 10's walk tests should script these texts."
  deferred_at: 2026-11-10
  went_to: "ROADMAP Phase 10 planning notes (calibrate before the tour deploy; reopens 02 D-47 and D-56, Ian decides in discuss-phase); BLUEPRINT 10 open items (2026-11-11)"
- test: 4
  idea: "On +13175550176 the reply cap (6 per hour) was reached before \"What time are you coming?\", so the assistant paused. Two of the six counted texts were holding replies from those holds, and the booking confirmation also counts toward the cap. Design question for later."
  deferred_at: 2026-11-10
  went_to: "ROADMAP Phase 10 planning notes (whether confirmations and reminders count toward the cap); BLUEPRINT 1 open items (2026-11-11)"
- test: 4
  idea: "Dev chat shows the stored copy of each text, so a link sent through the front desk shows /book/[link] and cannot be clicked there. That is D-12 working; walking a booking in Dev chat needs a link minted by script. Not a product bug."
  deferred_at: 2026-11-10
  went_to: "ROADMAP Phase 12 planning notes, under \"How a visitor tries the front desk on staging\"; BLUEPRINT 1 open items (2026-11-11)"
- test: 4
  idea: "The local database had no owner login, so Ian made one with make:filament-user. It has no phone, so owner alerts were \"not sent: No phone on file\"."
  deferred_at: 2026-11-10
  went_to: "docs/SETUP.md step 4 (fill in \"Mobile phone for alerts\" on the profile page) (2026-11-11)"
- test: 7
  idea: "VoiceOver walk of the owner screens (Schedule page, Block time dialog, Book by hand dialog) moves to Phase 11, as in Phase 7."
  deferred_at: 2026-11-11
  went_to: "ROADMAP Phase 11 planning notes, inputs from Phase 8 UAT check 7 (already success criterion 6) (2026-11-11)"
- test: 7
  idea: "Cosmetic, for Phase 11: the Schedule table scrolls sideways at a normal laptop window width with the What column squeezed."
  deferred_at: 2026-11-11
  went_to: "ROADMAP Phase 11 planning notes and the phone design pass todo (.planning/todos/pending/2026-10-17-phone-design-pass.md), under the visual direction rule; BLUEPRINT 4 open items (2026-11-11)"
- test: 7
  idea: "Cosmetic, for Phase 11: the automatic-texts timeline badges show the raw type value (automatic_texts_turned_on), while other entries show a plain label, as D-46 specifies."
  deferred_at: 2026-11-11
  went_to: "ROADMAP Phase 11 planning notes and the phone design pass todo (.planning/todos/pending/2026-10-17-phone-design-pass.md), under the visual direction rule; BLUEPRINT 4 open items (2026-11-11)"
