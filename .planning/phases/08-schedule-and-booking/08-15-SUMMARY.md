---
phase: 08-schedule-and-booking
plan: 15
subsystem: sales
tags: [filament, livewire, contacts, automatic-texts, accessibility, pest-browser, axe, laravel]
status: complete

requires:
  - phase: 08-schedule-and-booking
    provides: "Contact::hasTextedBusiness() and mayGetAutomaticTexts() with the automatic_texts_on_at and automatic_texts_on_by_user_id columns (08-01, 08-02), OutboundSender's D-21 check, BookAppointment::byHand and the confirmation send (08-06, 08-11, 08-12), the Appointments tab (08-13), the browser helpers pressKey, focusedName, expectFocusOn, pageAxeReport (08-10)"
provides:
  - "AddContactByHand: phone-then-email matching, E.164 phone, email stored trimmed and lower-cased as RecordInbound stores it, a hand-added Lead that opens and sends nothing, ContactRefused carrying the field a refusal is about"
  - "The stock Create page (CreateContact) with the list's New contact action; ContactResource::canCreate() is true (D-19, reversing Phase 1)"
  - "SetAutomaticTexts on() and off(): the owner's switch for a never-texted contact, with who and when, refused for a customer who has texted"
  - "ViewContact's one stable automaticTexts header action and the infolist's Automatic texts line; the reply-box line above the owner's reply box"
  - "The contact screen's Next appointment line (D-38)"
  - "A real-browser keyboard, focus and axe proof of the New contact form and the switch"
affects: [08-16, phase-11-owner-screen-pass]

actuals:
  tokens: 16700
  tasks: 3
  commits: 6

plan_head_before: 3002af9b24bcc0ed046a65d6cfd11b7f36889565
plan_head_after: e66bad3388ce626f1ca3f026d963fa7590cbcc8f

tech-stack:
  added: []
  patterns:
    - "A stock Filament CreateRecord page whose handleRecordCreation() calls a domain action, and a refusal that names a field is rethrown as a ValidationException on data.<field>"
    - "One stable header action whose label, dialog and direction follow state, with the direction carried in a Hidden form field so a stale tab does what the dialog said (the 07-09 toggle, on a page header)"
    - "A protected lookup seam (matchByPhone) so a unique-index race is tested by a lookup that finds nothing"

key-files:
  created:
    - app/Actions/AddContactByHand.php
    - app/Actions/ContactRefused.php
    - app/Actions/SetAutomaticTexts.php
    - app/Filament/Resources/Contacts/Pages/CreateContact.php
    - tests/Feature/Filament/ContactByHandTest.php
    - tests/Feature/Booking/AutomaticTextsSwitchTest.php
    - tests/Feature/Filament/ContactScreenKeyboardTest.php
  modified:
    - app/Filament/Resources/Contacts/ContactResource.php
    - app/Filament/Resources/Contacts/Pages/ListContacts.php
    - app/Filament/Resources/Contacts/Pages/ViewContact.php
    - app/Filament/Resources/Contacts/Schemas/ContactInfolist.php
    - app/Livewire/ConversationThread.php
    - resources/views/livewire/conversation-thread.blade.php
    - docs/BLUEPRINT.md
    - docs/sprints/sprint-1.1.md
    - .planning/WINDOWS.md

key-decisions:
  - "A hand-added email is stored trimmed and lower-cased exactly as RecordInbound::normalizeEmail() does, and that value is both matched and stored, so a later form lead or inbound in any letter case lands on the hand-added contact (RecordInbound matches with an exact where, case-sensitive on Postgres); RecordInbound is untouched"
  - "AddContactByHand asks the phone first, then the email, inside a transaction, and turns a UniqueConstraintViolationException on phone_e164 into the same phone refusal; the phone lookup is a protected matchByPhone() so the race path is tested"
  - "The automaticTexts header action is one action with a Hidden direction field filled when the dialog opens, so the submit does what the dialog said even when the page went stale in a second tab; its button keeps one key so focus stays on it"
  - "The reply-box line is informational only: it shows when mayGetAutomaticTexts() is false, sits inside the form above the box and is tied by aria-describedby, and never disables the box (D-20)"
  - "The New contact page turns off Create another and trims the email in the field before Filament's email rule runs, so ' Sam@Example.com ' is valid and the stored value is normalized once"

patterns-established:
  - "The phone field is type=tel, not Filament's tel(): tel() adds a regex that would refuse '555' before the action could give its own not-a-US-number line"

requirements-completed: [BOOK-04, BOOK-03]

coverage:
  - id: D1
    description: "The owner adds a contact by hand from the contacts list; it matches like an inbound (phone, then email, letter case ignored), is refused beside the field naming the contact, starts as a Lead in New leads, opens no project or conversation and sends nothing, and a later text or form lead lands on the same contact"
    requirement: "BOOK-04"
    verification:
      - kind: integration
        ref: "tests/Feature/Filament/ContactByHandTest.php"
        status: pass
      - kind: e2e
        ref: "tests/Feature/Filament/ContactScreenKeyboardTest.php#puts the refusal for a matching phone beside Phone, tied to the field"
        status: pass
    human_judgment: false
  - id: D2
    description: "The automatic-texts switch on a never-texted contact: one header action whose dialog and direction follow the switch, who and when recorded, the Automatic texts line, the reply-box line that never blocks the owner, and a hand booking's confirmation following the switch end to end"
    requirement: "BOOK-04"
    verification:
      - kind: integration
        ref: "tests/Feature/Booking/AutomaticTextsSwitchTest.php"
        status: pass
    human_judgment: false
  - id: D3
    description: "The contact screen's Next appointment line, present when a visit is booked ahead and absent otherwise"
    requirement: "BOOK-03"
    verification:
      - kind: integration
        ref: "tests/Feature/Filament/ContactByHandTest.php#shows the next appointment on the contact screen (D-38)"
        status: pass
    human_judgment: false
  - id: D4
    description: "The New contact form and the automatic-texts action and dialog are reached and used by keyboard, focus stays on the switch's button after it flips, and axe finds nothing on the create page, the contact screen or the open dialog"
    verification:
      - kind: e2e
        ref: "tests/Feature/Filament/ContactScreenKeyboardTest.php"
        status: pass
    human_judgment: true
    rationale: "Automated keyboard and axe checks pass in Chromium; the VoiceOver listen of the New contact page and the automatic-texts dialog is a human check carried to the Phase 11 owner-screen pass (A11Y-03, D-43)"

duration: 13 min
completed: 2026-11-07
---

# Phase 8 Plan 15: Contacts by hand and the automatic-texts switch Summary

**The owner can add a customer who called, matched like an inbound (phone, then email in any letter case) and refused beside the field when already saved, turn on automatic texts for a customer who agreed, see a plain line above the reply box when one has not, and see the next appointment on the contact screen.**

## Performance

- **Duration:** 13 min
- **Started:** 2026-11-07T16:16Z
- **Completed:** 2026-11-07T16:30Z
- **Tasks:** 3 (one tracer, two auto, all TDD)
- **Files modified:** 16 (7 created, 9 modified)

## Accomplishments

- **Contacts by hand (D-19).** `AddContactByHand` trims, refuses with a `ContactRefused` that names its field, matches the E.164 phone and then the normalized email, and creates a Lead inside a transaction. It never sends, opens no project and no conversation, and raises no alert. `ContactResource::canCreate()` is now true, the stock `CreateContact` page calls the action, and the list has a New contact header action.
- **The switch (D-20, D-21).** `SetAutomaticTexts::on()` and `off()` read the contact again under a row lock, refuse a customer who has texted, keep the first time and user on a second `on()`, and redraw the card. `ViewContact` has one `automaticTexts` header action whose label, dialog and direction follow the state; the infolist shows `Off` or `On, turned on by <name or a removed user> on <M j, Y>`; neither shows for a contact who has texted.
- **The reply-box line.** `ConversationThread::notAgreedToTextsLine()` prints "This customer has not texted the business or agreed to texts." in a `p` with an id inside the form, tied to the textarea by `aria-describedby`. It adds no inline style and never disables the box; `sendReply()` still sends.
- **End to end.** A hand booking for a never-texted Sam with the switch off sends nothing, skips its confirmation row and writes "Confirmation text not sent: the customer has not agreed to automatic texts."; with the switch on the confirmation goes out in a conversation the platform opened.
- **Next appointment (D-38).** The Contact section shows "Tuesday, November 3, 2026 at 10:00 AM, AC repair, Job 2" for the soonest booked visit ahead, left out when none is.
- **By keyboard.** Seven Chromium cases: Tab reaches New contact and the form's fields, a matching phone's refusal is beside Phone and in its describedby, the switch's dialog works by keyboard and focus stays on the button (now Turn off automatic texts), and axe is clean on the create page, the contact screen and the open dialog.

## TDD Gate Compliance

| Task | RED | GREEN |
|------|-----|-------|
| 1 (tracer) | 9168db2 `test(08-15)` | 647bd6f `feat(08-15)` |
| 2 | 669f369 `test(08-15)` | 1c0197c `feat(08-15)` |
| 3 | 8400773 `test(08-15)` | e66bad3 `feat(08-15)` |

No REFACTOR commit; nothing needed cleaning after green.

**RED evidence and its semantic assessment.** The project's runner is Pest, whose compact JSON summary line is not one of the report formats `gsd_run check tdd-red-evidence` reads, so the classifier was not run; each RED below was read by inspection of the real failure, not self-attested from an exit code.

- **Task 1:** four cases failed on the planned behavior. Three errored with "Unable to find component: [App\Filament\Resources\Contacts\Pages\CreateContact]" (the feature did not exist) and the list case failed on `ContactResource::canCreate()` returning false. Valid RED.
- **Task 2:** 7 of 21 failed, all in `AutomaticTextsSwitchTest` ("Target class [App\Actions\SetAutomaticTexts] does not exist", and the switch action not visible, and the reply-box line missing). The new refusal cases added to `ContactByHandTest` (three phone spellings, email letter case, phone before email, no name, empty, unreadable, name length) **passed at RED**, because Task 1's GREEN already delivered `AddContactByHand`'s refusals and the plan allows "any refusal case Task 1 left failing"; none was left failing. The unique-phone race case also passed at RED but vacuously (its override named a method that did not exist yet); GREEN added the `matchByPhone()` seam, after which the case really takes the unique-index catch path.
- **Task 3:** the Next appointment case failed on the planned assertion (the line was absent); the absent-line case passed, as expected. All seven browser cases passed at RED because the form and the switch were built in Tasks 1 and 2; they are recorded as passing results, as the plan asked.

## Task Commits

1. **Task 1 RED:** 9168db2 `test(08-15): the owner adds a contact by hand`
2. **Task 1 GREEN:** 647bd6f `feat(08-15): the owner adds a contact by hand (D-19)`
3. **Task 2 RED:** 669f369 `test(08-15): matching refusals, the switch and the reply-box line`
4. **Task 2 GREEN:** 1c0197c `feat(08-15): the automatic-texts switch and the reply-box line (D-20, D-21)`
5. **Task 3 RED:** 8400773 `test(08-15): the next appointment line and the contact screen by keyboard`
6. **Task 3 GREEN:** e66bad3 `feat(08-15): the next appointment on the contact screen; the new screens by keyboard (D-38, D-43)`

## Existing tests (Ian's sweep note)

No existing test file changed. `tests/Feature/Filament/ContactResourceTest.php`, `ConversationViewTest.php` and `ConversationResourceTest.php` pass with their assertions unchanged, and so does the rest of `tests/Feature/Filament`, `tests/Feature/Booking` and `tests/Feature/Inbound`. No test relied on "never by hand" or on the reply box's exact markup. The full suite is 2980 passed on SQLite, and `app/Actions/RecordInbound.php` is byte-identical to the plan's base.

## Verification

- `php artisan test --compact tests/Feature/Filament tests/Feature/Booking tests/Feature/Inbound`: 701 passed, no browser test skipped.
- `php artisan test --compact`: 2980 passed, 21354 assertions, on SQLite. The one "warning" in the summary line is also present on the full run before this plan's last commit and belongs to no test of this plan.
- Acceptance criteria all pass: the greps for `CreateContact::route`, `return true;` in `canCreate()`, `PhoneNumber::toE164`, `mb_strtolower`, `notAgreedToTextsLine`, `aria-describedby`, `TextEntry::make('next_appointment')`, `Phase 8 context D-19` and `(plan 08-15)`; zero `waitForFunction` or `waitForURL` in the browser test; `RecordInbound.php` unchanged since BASE; no unnamed modified test file (none was modified).

## Deviations from Plan

**1. [Rule 3 - Blocking] axe on the contact screen excludes the details list's placeholders**
- **Found during:** Task 3 RED.
- **Issue:** whole-document axe on the contact screen and the open dialog reports `color-contrast` on the gray placeholders "(no email)" and "(unknown)" in the Phase 7 details list. A hand-added contact always has at least the cost placeholder. 08-13 already carried this list's contrast to the Phase 11 owner-screen pass.
- **Fix:** `contactScreenKeyboardAxe()` in `ContactScreenKeyboardTest.php` runs axe on the whole page with `.fi-in-placeholder` excluded, so everything else (the new Automatic texts and Next appointment lines, the switch's button and dialog, the create page) is checked. The create page has no placeholders and passes unscoped.
- **Files modified:** `tests/Feature/Filament/ContactScreenKeyboardTest.php`
- **Commit:** 8400773

**2. [Rule 1 - Bug] The dialog heading is plain text, not run through e()**
- **Found during:** Task 2.
- **Issue:** the plan says names go "through e()"; a modal heading is rendered by Blade, so `e()` would print a literal `&lt;` for a name containing a tag (the 08-06 precedent). Notification titles do render as HTML, and those keep `e()` (the refusal notice does; the success titles carry no name).
- **Fix:** the heading is the plain string; a test names a contact `<script>alert(1)</script> Sam` and checks the screen never prints the tag raw.
- **Files modified:** `app/Filament/Resources/Contacts/Pages/ViewContact.php`, `tests/Feature/Booking/AutomaticTextsSwitchTest.php`
- **Commit:** 1c0197c

**3. [Rule 2 - Missing critical] Smaller choices the plan left open**
- The phone field is `type('tel')`, not Filament's `tel()`, whose regex would refuse "555" before the action could give its own not-a-US-number sentence.
- The email field trims in the field (`->trim()`) so Filament's `email` rule does not refuse a pasted ' Sam@Example.com '; the action still normalizes once.
- Create another is off on the New contact page; adding a contact lands on its screen, where the switch and Book by hand are.
- `AddContactByHand` dispatches `CustomerRecordChanged` after commit so the customer card is drawn for the new contact, as `OpenProject` and `SetAutomaticTexts` do. It writes no timeline entry (flagged assumption in the plan).
- The dialog tests read the mounted action (`getMountedAction()`), the Projects tab's pattern, because Filament draws a dialog in the browser and a Livewire test's HTML has none.
- **Commits:** 647bd6f, 1c0197c

**4. [Plan criterion note] One grep criterion is BSD-sensitive**
- `grep -q 'public function on(Contact $contact, User $user): void' app/Actions/SetAutomaticTexts.php` exits 1 under macOS BSD grep (it reads the `$` in the pattern as an anchor) and 0 under GNU grep and with `grep -qF`. The line is present at `app/Actions/SetAutomaticTexts.php:29`. Nothing to fix in code.

**Total deviations:** 4 (one blocking-scope, one bug-prevention, one set of small choices, one criterion note). **Impact:** none on scope; all plan truths hold.

## Authentication Gates

None.

## Known Stubs

None. No hardcoded empty value flows to a screen and no placeholder text was added. The two details-list placeholders in the axe note are Phase 7's.

## Threat Flags

None. The plan's register covers the new surface: the create page sits behind the panel's login like every contact screen (T-08-62 duplicates refused beside the field, backed by the unique phone index), the switch is off by default and recorded with who and when (T-08-63, T-08-64), and the dialog name is plain text with `e()` on notification titles that carry text (T-08-65).

## Carried hand checks (never a pause)

Both stay unrun by Ian's standing instruction for GSD runs and are recorded in `.planning/WINDOWS.md` as `unrun-verify` entries for the end-of-phase UAT:

1. After Ian pushes the phase branch, the CI pgsql leg is green for `ContactByHandTest`'s matching and the unique-phone race (this run was on SQLite only).
2. At the Phase 11 owner-screen pass (A11Y-03): the VoiceOver listen of the New contact page and the automatic-texts dialog (D-43).

Also carried, not new: the contact details list's placeholder contrast ("(no email)", "(unknown)"), already moved to the Phase 11 pass by 08-13.

## Flagged assumptions (unchanged from the plan)

U-26's "On, the customer texted" wording is never shown; adding a contact by hand writes no timeline entry; the not-a-US-number sentence is new wording; turning the switch off clears who turned it on.

## Next Phase Readiness

Ready for 08-16. The switch and the hand-added contact are in place for any later plan that reads `mayGetAutomaticTexts()`.

## Self-Check: PASSED

- Created files all present: `AddContactByHand.php`, `ContactRefused.php`, `SetAutomaticTexts.php`, `CreateContact.php`, `ContactByHandTest.php`, `AutomaticTextsSwitchTest.php`, `ContactScreenKeyboardTest.php`.
- Commits 9168db2, 647bd6f, 669f369, 1c0197c, 8400773 and e66bad3 are ancestors of HEAD; six commits measured from `plan_head_before`.
